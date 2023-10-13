<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
downloads([
    {
        "description":"7.6.1 - Windows Installer (64 bit)",
        "edition":"None",
        "zipUrl":"https://www.atlassian.com/software/confluence/downloads/binary/atlassian-confluence-7.6.1-x64.exe",
        "tarUrl":null,
        "md5":"101d74a22ee819b3d390a73435fef809",
        "size":"686.1 MB",
        "released":"15-Jul-2020",
        "type":"Binary",
        "platform":"Windows",
        "version":"7.6.1",
        "releaseNotes":"https://confluence.atlassian.com/display/DOC/Confluence+7.6+Release+Notes",
        "upgradeNotes":"https://confluence.atlassian.com/display/DOC/Confluence+7.6+Upgrade+Notes"
    },
    ...
])
*/

class atlassian_confluence extends SoftwareCheck
{
    public static $name = 'Confluence';
    public static $vendor = 'Atlassian';
    public static $homepage = 'https://www.atlassian.com/software/confluence';
    public static $type = 'json';
    public static $enabled = true;

    // The archive is needed because the other Enterprise releases are listed there
    var $uris = [
        'https://my.atlassian.com/download/feeds/current/confluence.json',
        'https://my.atlassian.com/download/feeds/archived/confluence.json',
    ];

    function get_data()
    {
        $data = array();
        foreach ($this->uris as $uri)
        {
            $raw_data = http::get($uri);
            if (strncmp('downloads(', $raw_data, 10) === 0)
            {
                $trimmed = substr($raw_data, 10, -1);
                $json = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                $data = array_merge($data, $json);
            }
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