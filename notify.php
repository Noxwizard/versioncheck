<?php
require_once 'vendor/autoload.php';
require __DIR__ . '/common.php';
set_time_limit(0);

use Mailgun\Mailgun;

// Load all of the software we may notify about
$classes = array();
$dir = new DirectoryIterator('software');
foreach ($dir as $fileinfo)
{
    if (!$fileinfo->isDot() && $fileinfo->getExtension() == 'php')
    {
        include_once('software' . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
    }
}

// Get the software updates
$updates = array();
$sql = 'SELECT software, branch, version, last_sent_user FROM ' . NOTIFICATIONS_TABLE;
foreach ($db->query($sql) as $row)
{
    $updates[$row['software']][$row['branch']] = [
        'version'       => $row['version'],
        'last_sent_user'=> $row['last_sent_user'],
    ];
}

foreach ($updates as $software => $branches)
{
    $class = new $software();
    foreach ($branches as $branch => $data)
    {
        $version = $data['version'];
        $last_user = $data['last_sent_user'];

        // Get the announcement URL
        $sql = 'SELECT announcement FROM ' . VERSION_TABLE . ' WHERE software = :software AND branch = :branch AND version = :version';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(':software' => $software, ':branch' => $branch, ':version' => $version));
        if ($result === false)
        {
            print_r($sth->debugDumpParams());
            throw new RuntimeException($sth->errorInfo());
        }
        if (!empty($result['announcement']))
        {
            $site = $result['announcement'];
        }
        else
        {
            $site = $class::$homepage;
        }

        // Find the users who want to be notified about either this branch or all updates
        // We only need to prepare the query once
        $sql = 'SELECT u.id, u.subscriber_token, u.subscription_email FROM ' . SUBSCRIPTION_TABLE . ' s
                JOIN ' . USER_TABLE . ' u ON (u.id = s.user_id) 
                WHERE s.software = :software 
                AND (s.branch = :branch OR s.branch IS NULL)
                AND u.id > :last_user
                ORDER BY u.id LIMIT 500';
        $sth = $db->prepare($sql);
        while (true)
        {
            $result = $sth->execute(array(':software' => $software, ':branch' => $branch, ':last_user' => $last_user));
            if ($result === false)
            {
                print_r($sth->debugDumpParams());
                throw new RuntimeException($sth->errorInfo());
            }

            if ($sth->rowCount() == 0)
            {
                $sql = 'DELETE FROM ' . NOTIFICATIONS_TABLE . ' WHERE software = :software AND branch = :branch AND version = :version';
                $uph = $db->prepare($sql);
                $result = $uph->execute(array(':software' => $software, ':branch' => $branch, ':version' => $version));
                if ($result === false)
                {
                    print_r($uph->debugDumpParams());
                    throw new RuntimeException($uph->errorInfo());
                }
                break;
            }

            $user_templates = array();
            $user_emails = array();
            $last_user_seen = $last_user;
            while (($row = $sth->fetch(PDO::FETCH_ASSOC)) != false)
            {
                $last_user_seen = $row['id'];
                $user_emails[] = $row['subscription_email'];
                $user_templates[$row['subscription_email']] = [
                    'user_id'   => $row['id'],
                    'sub_email' => $row['subscription_email'],
                    'sub_token' => $row['subscriber_token'],
                ];
            }

            // Build the email to send
            $unsub_address = "https://versioncheck.net/unsubscribe.php?" . 
                "sub_token=%recipient.sub_token%" . 
                "&user=%recipient.user_id%" . 
                "&sub_email=%recipient.sub_email%" . 
                "&software={$class::$name}" . 
                "&branch=$branch";
            $message = "Version $version of {$class::$name} was just released. Visit their site for more information: $site\r\n\r\n" . 
            "Thanks for using versioncheck.net!\r\n\r\n\r\n" . 
            "You have received this message because you are subscribed to notifications for {$class::$name}. If you no longer wish to " . 
            "receive notifications for this software, you can unsubcribe here: $unsub_address";

            $html_message = <<<HERE
<!DOCTYPE html>
<p>Version $version of {$class::$name} was just released. Visit their site for more information: <a href="$site">$site</a></p>
<p>Thanks for using <a href="https://versioncheck.net">versioncheck.net</a>!</p>
<p><small>You have received this message because you are subscribed to notifications for {$class::$name}. If you no longer wish to
receive notifications for this software, you can unsubcribe here: <a href="$unsub_address">Unsubscribe</a></small></p>
HERE;


            // Set up the MailGun client
            $mgClient = Mailgun::create($provider_configs['mailgun']['api_key'], $provider_configs['mailgun']['endpoint']);
            $domain = 'mg.versioncheck.net';
            $params =  array(
                'from'      => 'VersionCheck <mail@versioncheck.net>',
                'to'        => $user_emails,
                'subject'   => "{$class::$name} $version Released",
                'text'      => $message,
                'html'      => $html_message,
                'recipient-variables' => json_encode($user_templates)
            );

            // Send the emails
            $mgClient->messages()->send($domain, $params);


            // Update the last_user_sent field before we grab the next batch
            $sql = 'UPDATE ' . NOTIFICATIONS_TABLE . ' SET last_sent_user = :user_id WHERE software = :software AND branch = :branch AND version = :version';
            $uph = $db->prepare($sql);
            $result = $uph->execute(array(
                ':user_id'  => $last_user_seen,
                ':software' => $software, 
                ':branch'   => $branch, 
                ':version'  => $version
            ));
            if ($result === false)
            {
                print_r($uph->debugDumpParams());
                throw new RuntimeException(var_export($uph->errorInfo(), true));
            }

            $last_user = $last_user_seen;
        }
    }
}