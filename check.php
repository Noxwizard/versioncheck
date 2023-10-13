<?php
require __DIR__ . '/common.php';

// Get all of the software to check
$classes = array();
$dir = new DirectoryIterator('software');
foreach ($dir as $fileinfo)
{
    if (!$fileinfo->isDot() && $fileinfo->getExtension() == 'php')
    {
        include_once('software' . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $classes[] = strtolower($fileinfo->getBasename('.php'));
    }
}


// Check for updates
foreach ($classes as $class)
{
    if (!class_exists($class, false))
        continue;

    $software = new $class();
    if (!is_subclass_of($software, 'SoftwareCheck', false))
        continue;

    if (!$class::$enabled)
    {
        //echo 'Skipping disabled class: ' . $class . "\n";
        continue;
    }

    $versions = array();
    try {
        $data = $software->get_data();
        $versions = $software->get_versions($data);
    } catch (Exception $e)
    {
        echo "Exception while getting version data for $class: $e";
        continue;
    }
    
    /*
    Array (
        [3.0] => Array (
                [version] => 3.0.14
                [announcement] => https://www.phpbb.com/community/viewtopic.php?f=14&t=2313941
                [estimated] => 1
                [release_date] => 2020-06-28 22:23:08
            ),
        ...
    */
    $updates = array();
    $sql = 'SELECT id, version FROM ' . VERSION_TABLE . ' WHERE software = :software AND branch = :branch';
    $sth = $db->prepare($sql);
    foreach ($versions as $branch => $version_info)
    {
        $sth->execute(array(':software' => $class, ':branch' => $branch));
        $row = $sth->fetch();
        $id = 0;
        if ($row !== false && count($row) > 0)
        {
            $id = (int)$row['id'];
        }

        if ($id > 0)
        {
            if ($row['version'] != $version_info['version'])
            {
                //echo 'Updating ' . $class . ' from ' . $row['version'] . ' to ' . $version_info['version'] . ': ' . $id . "\n";
                $sql = 'UPDATE ' . VERSION_TABLE . ' SET 
                    version = :version,
                    announcement = :announcement,
                    release_date = :release_date,
                    last_check = :last_check,
                    estimated = :estimated
                WHERE id = :id';
                $uph = $db->prepare($sql);
                $ret = $uph->execute(array(
                    ':id'           => (int) $id,
                    ':version'      => $version_info['version'],
                    ':announcement' => array_key_exists('announcement', $version_info) ? $version_info['announcement'] : '',
                    ':release_date' => $version_info['release_date'],
                    ':last_check'   => date("Y-m-d H:i:s"),
                    ':estimated'    => array_key_exists('estimated', $version_info) ? (int)$version_info['estimated'] : 0,
                ));

                if (!$ret)
                {
                    echo 'Error while updating version information' . "\n";
                    print_r($uph->errorInfo());
                    print_r($uph->debugDumpParams());
                    //exit;
                    continue;
                }

                $updates[$class][$branch] = $version_info['version'];
            }
            else
            {
                //echo 'Updating ' . $class . ' ' . $row['version'] . ' checked time: ' . $id . "\n";
                $sql = 'UPDATE ' . VERSION_TABLE . ' SET 
                    last_check = :last_check
                WHERE id = :id';
                $uph = $db->prepare($sql);
                $ret = $uph->execute(array(
                    ':id'           => (int) $id,
                    ':last_check'   => date("Y-m-d H:i:s"),
                ));

                if (!$ret)
                {
                    echo 'Error while updating check time' . "\n";
                    print_r($uph->errorInfo());
                    print_r($uph->debugDumpParams());
                    //exit;
                    continue;
                }
            }
        }
        else
        {
            //echo 'Inserting new software branch: ' . $class . ' - ' . $branch . ' - ' . $version_info['version'] . "\n";
            $sql = 'INSERT INTO ' . VERSION_TABLE . ' (software, branch, version, announcement, release_date, last_check, estimated) VALUES (
                :software, :branch, :version, :announcement, :release_date, :last_check, :estimated
            )';
            $uph = $db->prepare($sql);
            $ret = $uph->execute(array(
                ':software'     => $class,
                ':branch'       => $branch,
                ':version'      => $version_info['version'],
                ':announcement' => array_key_exists('announcement', $version_info) ? $version_info['announcement'] : '',
                ':release_date' => $version_info['release_date'],
                ':last_check'   => date("Y-m-d H:i:s"),
                ':estimated'    => array_key_exists('estimated', $version_info) ? (int)$version_info['estimated'] : 0,
            ));

            if (!$ret)
            {
                echo 'Error while adding new version information' . "\n";
                print_r($uph->errorInfo());
                print_r($uph->debugDumpParams());
                //exit;
                continue;
            }

            $updates[$class][$branch] = $version_info['version'];
        }

        //echo $software::$name . "\n";
        //echo $branch ."\n";
        //print_r($version_info);
    }


    // Log the updates so we can send notifications
    // This assumes all pending updates were already sent
    $sql = 'INSERT INTO ' . NOTIFICATIONS_TABLE . ' (software, branch, version) VALUES (:software, :branch, :version)';
    $sth = $db->prepare($sql);
    foreach ($updates as $software => $data)
    {
        foreach ($data as $branch => $version)
        {
            $ret = $sth->execute(array(
                ':software'     => $software,
                ':branch'       => $branch,
                ':version'      => $version,
            ));
    
            if (!$ret)
            {
                echo 'Error while adding notification information' . "\n";
                print_r($sth->errorInfo());
                print_r($sth->debugDumpParams());
                //exit;
                continue;
            }
        }
    }
}