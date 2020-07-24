<?php

$db_host = '';
$db_port = '';
$db_user = '';
$db_password = '';
$db_name = '';
$db_prefix = '';

$script_path = '/';

$provider_configs = [
    'google'    => [
        'clientId'      => '',
        'clientSecret'  => '',
        'redirectUri'   => 'http://localhost/versioncheck/login.php?provider=google',
    ],
    'github'    => [
        'clientId'      => '',
        'clientSecret'  => '',
        'redirectUri'   => 'http://localhost/versioncheck/login.php?provider=github',
    ],
    'gitlab'    => [
        'clientId'      => '',
        'clientSecret'  => '',
        'redirectUri'   => 'http://localhost/versioncheck/login.php?provider=gitlab',
    ],
    'mailgun'   => [
        'endpoint'      => '',
        'api_key'       => '',
        'domain'        => '',
    ],
];