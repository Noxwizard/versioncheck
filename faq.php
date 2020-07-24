<?php
require_once 'vendor/autoload.php';
require __DIR__ . '/common.php';
require __DIR__ . '/user.php';

// Set up a user session
$user = new User();
$user->start_session();

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('faq.html', [
    'user'          => $user,
]);