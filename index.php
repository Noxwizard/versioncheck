<?php
require_once 'vendor/autoload.php';
require __DIR__ . '/common.php';
require __DIR__ . '/user.php';

// Set up a user session
$user = new User();
$user->start_session();

function sort_by_branch($a, $b)
{
    // Return result in descending order by multiplying by -1
    return version_compare($a['branch'], $b['branch']) * -1;
}

// Get the user's subscriptions
$subscriptions = array();
if ($user->user_id != GUEST_USER)
{
    $sql = 'SELECT software, branch FROM ' . SUBSCRIPTION_TABLE . ' WHERE user_id = :user_id';
    $sth = $db->prepare($sql);
    $sth->execute(array(':user_id' => $user->user_id));
    while (($row = $sth->fetch(PDO::FETCH_ASSOC)) != false)
    {
        if (empty($row['branch']))
        {
            $subscriptions[$row['software']]['all'] = true;
        }
        else
        {
            $subscriptions[$row['software']][$row['branch']] = true;
        }
    }
}

$all_software = array();
// Find out what software classes to load
$sql = 'SELECT DISTINCT software FROM ' . VERSION_TABLE;
$result = $db->query($sql);
$rows = $result->fetchAll();
$software_types = array();
foreach ($rows as $row)
{
    include('software' . DIRECTORY_SEPARATOR . $row['software'] . '.php');
    $class = new $row['software']();
    $all_software[$row['software']] = [
        'info'      => [
            'vendor'    => $class::$vendor,
            'name'      => $class::$name,
            'url'       => $class::$homepage,
            'class'     => $row['software'],
            'subscribed'=> isset($subscriptions[$row['software']]['all']),
        ],
        'releases'  => array(),
    ];
    unset($class);
}

// Load it all
// Eventually may to have to build template cache files during update, with a file for each software that all get included in the index
$sql = 'SELECT * FROM ' . VERSION_TABLE . ' ORDER BY software, branch DESC';
$result = $db->query($sql);

while (($row = $result->fetch(PDO::FETCH_ASSOC)) != false)
{
    $all_software[$row['software']]['releases'][] = [
        'branch'        => $row['branch'],
        'version'       => $row['version'],
        'release_date'  => date("d M Y", strtotime($row['release_date'])),
        'announcement'  => $row['announcement'],
        'last_check'    => $row['last_check'],
        'estimated'     => (bool)$row['estimated'],
        'subscribed'    => isset($subscriptions[$row['software']][$row['branch']]),
    ];
}

foreach ($all_software as &$software)
{
    usort($software['releases'], 'sort_by_branch');
}

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('index.html', [
    'all_software'  => $all_software,
    'user'          => $user,
]);