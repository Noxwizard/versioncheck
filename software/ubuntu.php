<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
\n\n
Dist: breezy\n
Name: Breezy Badger\n
Version: 05.10\n
Date: Thu, 13 Oct 2005 19:34:42 UTC\n
Supported: 0\n
Description: This is the Breezy Badger release\n
Release-File: http://old-releases.ubuntu.com/ubuntu/dists/breezy/Release\n
\n
Dist: dapper\n
Name: Dapper Drake\n
Version: 6.06 LTS\n
Date: Thu, 01 Jun 2006 9:00:00 UTC\n
Supported: 0\n
Description: This is the Dapper Drake release\n
Release-File: http://old-releases.ubuntu.com/ubuntu/dists/dapper/Release\n
ReleaseNotes: http://changelogs.ubuntu.com/EOLReleaseAnnouncement\n
UpgradeTool: http://old-releases.ubuntu.com/ubuntu/dists/dapper/main/dist-upgrader-all/current/dapper.tar.gz\n
UpgradeToolSignature: http://old-releases.ubuntu.com/ubuntu/dists/dapper/main/dist-upgrader-all/current/dapper.tar.gz.gpg\n
...
*/

class ubuntu extends SoftwareCheck
{
    public static $name = 'Ubuntu';
    public static $vendor = 'Canonical';
    public static $homepage = 'https://ubuntu.com/';
    public static $type = 'custom';
    public static $enabled = true;
    var $uri = 'https://changelogs.ubuntu.com/meta-release';

    function get_data()
    {
        $data = http::get($this->uri);
        $releases = explode("\n\n", $data);
        
        return $releases;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0)
        {
            return $data;
        }

        $versions = array();
        foreach ($data as $release)
        {
            $branch = '';
            $version_info = array();
            $lines = explode("\n", $release);
            foreach ($lines as $line)
            {
                $line = trim($line);
                if (strlen($line) == 0)
                    continue;

                $parts = explode(': ', $line);
                if (count($parts) == 2)
                {
                    if ($parts[0] == 'Version')
                    {
                        // Determine branch from first two octets
                        $version_parts = explode('.', $parts[1]);
                        if (count($version_parts) < 2)
                        {
                            continue;
                        }
                        $branch = $version_parts[0] . '.' . $version_parts[1];

                        // Make sure LTS doesn't show up in the branch name
                        $branch = str_ireplace(' LTS', '', $branch);

                        $version_info['version'] = $parts[1];
                    }
                    else if ($parts[0] == 'Date')
                    {
                        if (($timestamp = strtotime($parts[1])) !== false)
                        {
                            $time = strftime("%Y-%m-%d %H:%M:%S", $timestamp);
                        }
                        else
                        {
                            $time = strftime("%Y-%m-%d %H:%M:%S");
                            $version_info['estimated'] = true;
                        }
                        $version_info['release_date'] = $time;
                    }
                    else if ($parts[0] == 'ReleaseNotes')
                    {
                        $version_info['announcement'] = $parts[1];
                    }
                    else if ($parts[0] == 'Supported') // Unused
                    {
                        $version_info['is_eol'] = ($parts[1] == '1') ? false : true;
                    }
                }
            }

            if (!empty($branch))
            {
                $versions[$branch] = $version_info;
            }
        }

        return $versions;
    }
}