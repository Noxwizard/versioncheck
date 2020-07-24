<?php
include('config.php');

define('GUEST_USER', 0);

define('LINKS_TABLE', $db_prefix . 'linked_providers');
define('NOTIFICATIONS_TABLE', $db_prefix . 'notifications');
define('SESSION_TABLE', $db_prefix . 'sessions');
define('SUBSCRIPTION_TABLE', $db_prefix . 'subscriptions');
define('USER_TABLE', $db_prefix . 'users');
define('VERSION_TABLE', $db_prefix . 'version_info');

define('PROVIDER_NULL', 0);
define('PROVIDER_GITHUB', 1);
define('PROVIDER_GITLAB', 2);
define('PROVIDER_GOOGLE', 3);

// Connect to the database
$dsn = "mysql:host=$db_host;dbname=$db_name";
if (empty($db_port)){
    $dsn .= ";port=3306";
} else {
    $dsn .= ';port=' . (int) $db_port;
}

$options = array(
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
);

try {
    $db = new PDO($dsn, $db_user, $db_password, $options);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit;
}
unset($db_user);
unset($db_password);
