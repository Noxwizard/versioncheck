<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');
/*
window.smfVersion = "SMF 2.0.17";
*/

class smf extends SoftwareCheck
{
    public static $name = 'Simple Machines Forum';
    public static $vendor = 'Simple Machines';
    public static $homepage = 'https://www.simplemachines.org';
    public static $type = 'custom';
    public static $enabled = true;
    var $branches = ["2.0", "2.1"];
    var $uri = 'http://www.simplemachines.org/smf/current-version.js';

    function get_data()
    {
        $results = array();
        foreach($this->branches as $branch)
        {
            $data = http::get($this->uri . '?version=SMF+' . $branch);
            
            preg_match('/SMF [0-9.]*/', $data, $matches);
            if (count($matches))
            {
                $results = array_merge($results, $matches);
            }
        }
        
        return $results;
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
            // Determine branch from first two octets
            $version = str_replace('SMF ', '', $release);
            if ($version === false)
            {
                continue;
            }

            $version_parts = explode('.', $version);
            if (count($version_parts) < 3)
            {
                continue;
            }
            $branch = $version_parts[0] . '.' . $version_parts[1];

            $version_info = [
                'version'       => $version,
                'estimated'     => true,
                'release_date'  => date("Y-m-d H:i:s"),
            ];

            $versions[$branch] = $version_info;
        }

        return $versions;
    }
}