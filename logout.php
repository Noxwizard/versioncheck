<?php
require __DIR__ . '/common.php';
require __DIR__ . '/user.php';

// Set up a user session
$user = new User();
$user->start_session();

$user->session_kill();

header('Location: ' . $script_path);