<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
[
    {
        "description": "8.5.5 (ZIP Archive)",
        "edition": "Enterprise",
        "zipUrl": "https://www.atlassian.com/software/jira/downloads/binary/atlassian-jira-software-8.5.5.zip",
        "tarUrl": null,
        "md5": "04b1fd8ba949ce88a32ee6467892abdb",
        "size": "330.8 MB",
        "released": "07-Jun-2020",
        "type": "Binary",
        "platform": "Windows",
        "version": "8.5.5",
        "releaseNotes": "https://confluence.atlassian.com/display/JIRASOFTWARE/JIRA+Software+8.5.x+release+notes",
        "upgradeNotes": "https://confluence.atlassian.com/display/JIRASOFTWARE/JIRA+Software+8.5.x+upgrade+notes"
    },
    ...
]
*/

class atlassian_jira extends SoftwareCheck
{
    public static $name = 'Jira';
    public static $vendor = 'Atlassian';
    public static $homepage = 'https://www.atlassian.com/software/jira';
    public static $type = 'json';
    public static $enabled = true;

    // The archive is needed because the Enterprise releases are listed there
    var $uris = [
        'https://my.atlassian.com/download/feeds/current/jira-software.json',
        'https://my.atlassian.com/download/feeds/archived/jira-software.json',
    ];

    function get_data()
    {
        $data = array();
        foreach ($this->uris as $uri)
        {
            //$raw_data = file_get_contents(__DIR__ . '/jira.json');
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