<?php
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

$error = '';
if (!isset($_POST['token']) || $_POST['token'] != $user->form_token)
{
    $error = 'Invalid or missing form token';
}
else if (!isset($_POST['software']) || (!isset($_POST['update']) && (!isset($_POST['action']) || ($_POST['action'] != 'set' && $_POST['action'] != 'unset'))))
{
    $error = 'Invalid or missing form fields';
}
else
{
    // See if this a valid software
    $software = $_POST['software'];
    $sql = 'SELECT COUNT(*) FROM ' . VERSION_TABLE . ' WHERE software = :software';
    $sth = $db->prepare($sql);
    $sth->execute(array(':software' => $software));
    if ($sth->fetchColumn() == 0)
    {
        $error = 'Invalid software';
    }
    else
    {
        $to_sub = array();
        $non_js = false;
        $branches = array();
        $sub_all = false;

        // We're coming off a non-JS page
        if (isset($_POST['update']))
        {
            if (isset($_POST[$software . '-sub']))
            {
                foreach ($_POST[$software . '-sub'] as $branch)
                {
                    $branches[] = $branch;
                }
            }

            $sub_all = isset($_POST[$software . '-all']);
            $non_js = true;
        }


        if ($non_js)
        {
            // We have all of the requested branches, purge all subscriptions for this software and re-add them
            $sql = 'DELETE FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id AND software = :software';
            $sth = $db->prepare($sql);
            $sth->execute(array(':user_id' => $user->user_id, ':software' => $software));

            // First handle the whole tree subscription since we don't want to add individual branches if this is set
            if (isset($to_sub[$software]['all']))
            {
                $sql = 'INSERT INTO ' . SUBSCRIPTION_TABLE . ' (user_id, software) VALUES (:user_id, :software)';
                $sth = $db->prepare($sql);
                $sth->execute(array(':user_id' => $user->user_id, ':software' => $software));
                if ($sth->rowCount() == 0)
                {
                    $error = 'Error updating software subscription';
                }
            }
            else
            {
                if (count($branches))
                {
                    // Check that valid branches were provided
                    $place_holders = implode(',', array_fill(0, count($branches), '?'));
                    $sql = 'SELECT branch FROM ' . VERSION_TABLE . ' WHERE software = ? AND branch IN (' . $place_holders . ')';
                    $sth = $db->prepare($sql);
                    $sth->execute(array_merge((array)$software, $branches));
                    $valid_branches = $sth->fetchAll(PDO::FETCH_ASSOC);
                    if ($valid_branches === false)
                    {
                        $error = 'No valid branches found';
                        $valid_branches = array();
                    }
                }

                foreach ($valid_branches as $branch => $value)
                {
                    $sql = 'INSERT INTO ' . SUBSCRIPTION_TABLE . ' (user_id, software, branch) VALUES (:user_id, :software, :branch)';
                    $sth = $db->prepare($sql);
                    $sth->execute(array(':user_id' => $user->user_id, ':software' => $software, ':branch' => $branch));
                    if ($sth->rowCount() == 0)
                    {
                        $error = 'Error updating branch subscription';
                        break;
                    }
                }
            }
        }
        else
        {
            $subscribing = ($_POST['action'] == 'set');

            // (Un)Subscribing to a single branch
            if (isset($_POST['branch']) && !empty($_POST['branch']))
            {
                // Check that valid branches were provided
                $sql = 'SELECT branch FROM ' . VERSION_TABLE . ' WHERE software = :software AND branch = :branch';
                $sth = $db->prepare($sql);
                $sth->execute(array(':software' => $software, ':branch' => $_POST['branch']));
                if ($sth->rowCount() == 0)
                {
                    $error = 'Branch not found';
                }

                // Remove any whole tree subscriptions for this software
                $sql = 'DELETE FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id AND software = :software AND branch IS NULL';
                $sth = $db->prepare($sql);
                $sth->execute(array(':user_id' => $user->user_id, ':software' => $software));

                if ($subscribing)
                {
                    $sql = 'INSERT INTO ' . SUBSCRIPTION_TABLE . ' (user_id, software, branch) VALUES (:user_id, :software, :branch)';
                }
                else
                {
                    $sql = 'DELETE FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id AND software = :software AND branch = :branch';
                }
                $sth = $db->prepare($sql);
                $sth->execute(array(':user_id' => $user->user_id, ':software' => $software, ':branch' => $_POST['branch']));
                if ($sth->rowCount() == 0)
                {
                    $error = 'Error updating branch subscription';
                }
            }
            // (Un)Subscribing to all branches
            else
            {
                // Remove any individual subscriptions for this software
                $sql = 'DELETE FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id AND software = :software';
                $sth = $db->prepare($sql);
                $sth->execute(array(':user_id' => $user->user_id, ':software' => $software));

                if ($subscribing)
                {
                    $sql = 'INSERT INTO ' . SUBSCRIPTION_TABLE . ' (user_id, software) VALUES (:user_id, :software)';
                    $sth = $db->prepare($sql);
                    $sth->execute(array(':user_id' => $user->user_id, ':software' => $software));
                    if ($sth->rowCount() == 0)
                    {
                        $error = 'Error updating software subscription';
                    }
                }
            }
        }
    }
}

if ($non_js)
{
    header('Location: ' . $script_path);
}
else
{
    $response = [
        'error' => $error,
    ];
    echo json_encode($response);
}