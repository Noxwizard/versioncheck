<?php

require __DIR__ . '/vendor/autoload.php';

class User
{
    var $user_id = 0;
    var $provider_id = PROVIDER_NULL;
    var $session_id = false;
    var $ip = false;
    var $form_token = '';

    function __construct()
    {
        if (isset($_SERVER['REMOTE_ADDR']))
        {
            // Multiple IPs could be given, take the first
            $ip_parts = explode(',', $_SERVER['REMOTE_ADDR']);
            $this->ip = inet_ntop(inet_pton($ip_parts[0]));
        }
    }

    function start_session()
    {
        global $db;

        // See if it's an existing session
        if (isset($_COOKIE['session_id']))
        {
            $uuid = $_COOKIE['session_id'];
            if (uuid_is_valid($uuid))
            {
                $sql = 'SELECT user_id, provider_id, form_token FROM ' . SESSION_TABLE . ' WHERE session_id = :id AND session_ip = :ip';
                $sth = $db->prepare($sql);
                $sth->execute(array(':id' => $uuid, ':ip' => $this->ip));
                $result = $sth->fetch(PDO::FETCH_ASSOC);
                if ($result !== false)
                {
                    $this->user_id      = (int) $result['user_id'];
                    $this->provider_id  = (int) $result['provider_id'];
                    $this->session_id   = $uuid;
                    $this->form_token   = $result['form_token'];
                    return;
                }
            }
        }

        // Create a new session
        $this->session_create();
    }

    function session_create($user_id = false, $provider_id = false)
    {
        global $db;

        $this->session_id = uuid_create(UUID_TYPE_RANDOM);
        $this->form_token = uuid_create(UUID_TYPE_RANDOM);

        // If we've been given a user ID, we also need a provider ID
        if ($user_id !== false && $provider_id !== false)
        {
            $this->user_id = (int) $user_id;
            $this->provider_id = $provider_id;

            // Delete any old sessions from when they were a guest
            if (isset($_COOKIE['session_id']) && uuid_is_valid($_COOKIE['session_id']))
            {
                $sql = 'DELETE FROM ' . SESSION_TABLE . ' WHERE session_id = :session_id AND user_id = :user_id';
                $del = $db->prepare($sql);
                $del->execute(array(':session_id' => $_COOKIE['session_id'], ':user_id' => GUEST_USER));
            }
        }
        else
        {
            $this->user_id = GUEST_USER;
            $this->provider_id = PROVIDER_NULL;
        }

        $sql = 'INSERT INTO ' . SESSION_TABLE . ' (user_id, provider_id, session_id, session_ip, session_created, form_token) VALUES (
            :user_id, :provider_id, :session_id, :session_ip, :time, :form_token)';
        $uph = $db->prepare($sql);
        $uph->execute(array(
            ':user_id'      => $this->user_id,
            ':provider_id'  => $this->provider_id,
            ':session_id'   => $this->session_id,
            ':session_ip'   => $this->ip,
            ':form_token'   => $this->form_token,
            ':time'         => time(),
        ));

        $this->set_cookie('session_id', $this->session_id);
    }

    function session_kill()
    {
        global $db;

        $sql = 'DELETE FROM ' . SESSION_TABLE . ' WHERE session_id = :session_id AND user_id = :user_id';
        $del = $db->prepare($sql);
        $del->execute(array(':session_id' => $this->session_id, ':user_id' => $this->user_id));

        $this->set_cookie('session_id', $this->session_id, true);
    }

    function set_cookie($name, $value, $unset = false)
    {
        $secure = (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? true : false;
        $options = [
            'expires'   => !$unset ? strtotime('+14 days') : 1,
            'path'      => '/',
            'secure'    => $secure,
            'httponly'  => true,
        ];
        setcookie($name, $value, $options);
    }

    function add_provider($provider_id, $provider_user_id)
    {
        global $db;

        $sql = 'INSERT INTO ' . LINKS_TABLE . ' (user_id, provider_id, external_user_id) VALUES (' .
            ':user_id, :provider_id, :provider_userid)';
        $sth = $db->prepare($sql);
        $result = $sth->execute(array(
            ':user_id'          => $this->user_id,
            ':provider_id'      => $provider_id,
            ':provider_userid'  => $provider_user_id,
        ));

        if (!$result)
        {
            print_r($sth->errorInfo());
            return false;
        }
        return true;
    }

    function set_provider($provider_id)
    {
        $this->provider_id = $provider_id;
    }
}
