<?php
require_once 'vendor/autoload.php';
require __DIR__ . '/common.php';
require __DIR__ . '/user.php';

// Set up a user session
$user = new User();
$user->start_session();

if ($user->user_id == GUEST_USER)
{
    header('Location: ' . $script_path);
    exit;
}

// Is the user updating their email?
$error = '';
if (isset($_POST['submit']))
{
    if (!isset($_POST['token']) || $_POST['token'] != $user->form_token)
    {
        $error = 'Invalid or missing form token';
    }
    else if (isset($_POST['subemail']))
    {
        // Does it look like an email address?
        $atpos = strpos($_POST['subemail'], '@');
        $dotpos = strpos($_POST['subemail'], '.');
        if ($atpos !== false && $dotpos !== false && $dotpos > $atpos)
        {
            $sql = 'UPDATE ' . USER_TABLE . ' SET subscription_email = :subemail WHERE id = :user_id';
            $sth = $db->prepare($sql);
            $result = $sth->execute(array(':user_id' => $user->user_id, ':subemail' => $_POST['subemail']));
            if ($result === false)
            {
                $error = 'Error while updating profile';
            }
        }
        else
        {
            $error = 'Email address is not valid';
        }
    }
}

$linked_providers = array();

$sql = 'SELECT provider_id FROM ' . LINKS_TABLE . ' WHERE user_id = :user_id';
$sth = $db->prepare($sql);
$sth->execute(array(':user_id' => $user->user_id));
$result = $sth->fetchAll(PDO::FETCH_ASSOC);
if ($result !== false)
{
    foreach($result as $row)
    {
        $linked_providers[] = (int) $row['provider_id'];
    }
}

// Is the user trying to unlink a provider?
if (isset($_GET['mode']))
{
    if ($_GET['mode'] == 'unlink')
    {
        if (!isset($_GET['token']) || $_GET['token'] != $user->form_token)
        {
            $error = 'Invalid or missing form token';
        }
        else if (isset($_GET['provider']))
        {
            $provider = $_GET['provider'];
            if (array_key_exists($provider, $provider_maps))
            {
                if (count($linked_providers) > 1)
                {
                    $sql = 'DELETE FROM ' . LINKS_TABLE . ' WHERE provider_id = :provider AND user_id = :user_id';
                    $sth = $db->prepare($sql);
                    $result = $sth->execute(array(':provider' => $provider_maps[$provider], ':user_id' => $user->user_id));
                    if ($result === false)
                    {
                        $error = 'Error while deleting login provider';
                    }
                }
                else
                {
                    $error = 'Cannot unlink last social login provider, must have at least one';
                }
            }
            else
            {
                $error = 'Invalid provider specified';
            }
        }
        else
        {
            $error = 'No provider specified';
        }
    }
    else
    {
        $error = 'Invalid mode';
    }
}


$sql = 'SELECT username, email, subscription_email FROM ' . USER_TABLE . ' WHERE id = :user_id';
$sth = $db->prepare($sql);
$sth->execute(array(':user_id' => $user->user_id));
$result = $sth->fetch(PDO::FETCH_ASSOC);
if ($result === false)
{
    header('Location: ' . $script_path);
    exit;
}


// Is the user wanting their account deleted?
$delete = isset($_POST['delete']);
if ($delete)
{
    if (!isset($_POST['token']) || $_POST['token'] != $user->form_token)
    {
        $error = 'Invalid or missing form token';
    }
    // Did they check the box that says "I understand"
    else if (isset($_POST['understood']))
    {
        // Users
        $sql = 'DELETE FROM ' . USER_TABLE . ' WHERE id = :user_id';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(':user_id' => $user->user_id));
        if ($result === false)
        {
            echo 'Error while deleting user';
            exit;
        }

        // Linked providers
        $sql = 'DELETE FROM ' . LINKS_TABLE . ' WHERE user_id = :user_id';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(':user_id' => $user->user_id));
        if ($result === false)
        {
            echo 'Error while deleting linked providers';
            exit;
        }

        // Subscriptions
        $sql = 'DELETE FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(':user_id' => $user->user_id));
        if ($result === false)
        {
            echo 'Error while deleting subscriptions';
            exit;
        }

        // Sessions
        $sql = 'DELETE FROM ' . SESSION_TABLE . ' WHERE user_id = :user_id';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(':user_id' => $user->user_id));
        if ($result === false)
        {
            echo 'Error while deleting user sessions';
            exit;
        }

        $user->session_kill();
    }
    else
    {
        $error = 'To delete your account, you must also check the box';
    }
}

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('profile.html', [
    'github'    => in_array(PROVIDER_GITHUB, $linked_providers),
    'gitlab'    => in_array(PROVIDER_GITLAB, $linked_providers),
    'google'    => in_array(PROVIDER_GOOGLE, $linked_providers),

    'username'  => $result['username'],
    'email'     => $result['email'],
    'subemail'  => $result['subscription_email'],

    'error'     => $error,
    'user'      => $user,
    'delete'    => $delete,
]);