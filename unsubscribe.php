<?php
require_once 'vendor/autoload.php';
require __DIR__ . '/common.php';
require __DIR__ . '/user.php';

// Set up a user session
$user = new User();
$user->start_session();

$error = '';
if (isset($_GET['sub_token']) && isset($_GET['user']) && isset($_GET['sub_email']) && isset($_GET['software']) && isset($_GET['branch']))
{
    $sub_token = $_GET['sub_token'];
    $user_id = (int)$_GET['user'];
    $sub_email = $_GET['sub_email'];
    $software = $_GET['software'];
    $branch = $_GET['branch'];

    if (uuid_is_valid($sub_token))
    {
        // Check that this user is valid
        $sql = 'SELECT id FROM ' . USER_TABLE . ' WHERE id = :user_id AND subscription_email = :sub_email AND subscriber_token = :sub_token';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(
            ':user_id'      => $user_id,
            ':sub_email'    => $sub_email,
            ':sub_token'    => $sub_token,
        ));
        if ($result !== false)
        {
            if ($sth->rowCount() == 1)
            {
                // Verify and remove the subscription
                $sql = 'DELETE FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id AND software = :software AND (branch = :branch OR branch IS NULL)';
                $sth = $db->prepare($sql);
                $result = $sth->execute(array(
                    ':user_id'      => $user_id,
                    ':software'     => $software,
                    ':branch'       => $branch,
                ));
                if ($result !== false)
                {
                    if ($sth->rowCount() == 0)
                    {
                        $error = 'Invalid subscription information';
                    }
                    // Success, fall all the way through
                }
                else
                {
                    $error = 'An error was encountered while looking up subscription';
                }
            }
            else
            {
                $error = 'Invalid user information';
            }
        }
        else
        {
            $error = 'An error was encountered while looking up user';
        }
    }
    else
    {
        $error = 'Invalid subscriber token';
    }
}
else
{
    $error = 'Missing parameter';
}

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('unsubscribe.html', [
    'error'     => $error,
    'user'      => $user,
]);