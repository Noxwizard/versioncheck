<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
{
    "stable": {
        "3.0": {
            "current": "3.0.14",
            "announcement": "https://www.phpbb.com/community/viewtopic.php?f=14&t=2313941",
            "eol": null,
            "security": false
        },
        ...
    },
    "unstable": {
        "3.0": {
            "current": "3.0.14",
            "announcement": "https://www.phpbb.com/community/viewtopic.php?f=14&t=2313941",
            "eol": null,
            "security": false
        },
        ...
    }
}
*/

class phpbb extends SoftwareCheck
{
    public static $name = 'phpBB';
    public static $vendor = 'phpBB Limited';
    public static $homepage = 'https://www.phpbb.com';
    public static $type = 'json';
    public static $enabled = true;
    var $uri = 'https://version.phpbb.com/phpbb/versions.json';

    function get_data()
    {
        $data = http::get($this->uri);
        $json_decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        
        return $json_decoded;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0 || !array_key_exists('stable', $data))
        {
            return $data;
        }

        $versions = array();
        foreach($data['stable'] as $branch => $value)
        {
            if (array_key_exists('current', $data['stable'][$branch]))
            {
                $version_info = [
                    'version' => $data['stable'][$branch]['current']
                ];

                if (array_key_exists('announcement', $data['stable'][$branch]))
                {
                    $version_info['announcement'] = $data['stable'][$branch]['announcement'];
                }

                $version_info['release_date'] = strftime("%Y-%m-%d %H:%M:%S");
                $version_info['estimated'] = true;
                $versions[$branch] = $version_info;
            }
        }

        return $versions;
    }
}