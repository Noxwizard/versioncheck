<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
[
    {
        "description":"7.1.1 - Windows Installer, 32-bit",
        "edition":"",
        "zipUrl":"https://www.atlassian.com/software/bamboo/downloads/binary/atlassian-bamboo-7.1.1-windows-x32.exe",
        "tarUrl":null,
        "md5":"",
        "size":"297.0 MB",
        "released":"22-Jul-2020",
        "type":"Binary",
        "platform":"Windows",
        "version":"7.1.1",
        "releaseNotes":"https://confluence.atlassian.com/display/BAMBOO/Bamboo+7.1+Release+Notes",
        "upgradeNotes":"https://confluence.atlassian.com/display/BAMBOO/Bamboo+Upgrade+Guide"
    },
    ...
]
*/

class atlassian_bamboo extends SoftwareCheck
{
    public static $name = 'Bamboo';
    public static $vendor = 'Atlassian';
    public static $homepage = 'https://www.atlassian.com/software/bamboo';
    public static $type = 'json';
    public static $enabled = true;

    var $uris = [
        'https://my.atlassian.com/download/feeds/current/bamboo.json'
    ];

    function get_data()
    {
        $data = array();
        foreach ($this->uris as $uri)
        {
            $raw_data = http::get($uri);
            $json = json_decode($raw_data, true, 512, JSON_THROW_ON_ERROR);
            $data = array_merge($data, $json);
        }

        return $data;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0)
        {
            return $data;
        }

        $versions = array();
        foreach($data as $release)
        {
            if (array_key_exists('version', $release))
            {
                // Determine branch from first two octets
                $version = $release['version'];
                $version_parts = explode('.', $version);
                if (count($version_parts) < 3)
                {
                    continue;
                }
                $branch = $version_parts[0] . '.' . $version_parts[1];

                // There are multiple entries per version for different OSes, we only need one
                if (!array_key_exists($branch, $versions))
                {
                    $version_info = [
                        'version' => $version
                    ];

                    if (array_key_exists('releaseNotes', $release))
                    {
                        $version_info['announcement'] = $release['releaseNotes'];
                    }

                    // If there's a release date, use it, otherwise mark it as right now
                    if (array_key_exists('released', $release))
                    {
                        if (($timestamp = strtotime($release['released'])) !== false)
                        {
                            $time = date("Y-m-d H:i:s", $timestamp);
                        }
                        else
                        {
                            $time = date("Y-m-d H:i:s");
                            $version_info['estimated'] = true;
                        }
                    }
                    else
                    {
                        $time = date("Y-m-d H:i:s");
                        $version_info['estimated'] = true;
                    }
                    $version_info['release_date'] = $time;

                    $versions[$branch] = $version_info;
                }
            }
        }

        return $versions;
    }
}