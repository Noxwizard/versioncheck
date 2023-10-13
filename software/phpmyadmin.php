<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
{
    "date": "2020-03-21", 
    "version": "5.0.2", 
    "releases": [
        {
            "date": "2020-03-21", 
            "php_versions": ">=5.5,<8.0", 
            "version": "4.9.5", 
            "mysql_versions": ">=5.5"
        }, 
        {
            "date": "2020-03-21", 
            "php_versions": ">=7.1,<8.0", 
            "version": "5.0.2", 
            "mysql_versions": ">=5.5"
        }
    ]
}
*/

class phpmyadmin extends SoftwareCheck
{
    public static $name = 'phpMyAdmin';
    public static $vendor = 'phpMyAdmin';
    public static $homepage = 'https://www.phpmyadmin.net/';
    public static $type = 'json';
    public static $enabled = true;
    var $uri = 'https://www.phpmyadmin.net/home_page/version.json';

    function get_data()
    {
        $data = http::get($this->uri);
        $json_decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        
        return $json_decoded;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0 || !array_key_exists('releases', $data))
        {
            return $data;
        }

        $versions = array();
        foreach($data['releases'] as $release)
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

                $version_info = array();
                if (array_key_exists('version', $release))
                {
                    $version_info['version'] = $release['version'];
                }

                // If there's a release date, use it, otherwise mark it as right now
                if (array_key_exists('date', $release))
                {
                    if (($timestamp = strtotime($release['date'])) !== false)
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

        return $versions;
    }
}