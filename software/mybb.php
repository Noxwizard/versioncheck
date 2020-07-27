<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
{
  "mybb":
  {
	"friendly_name": "MyBB 1.8.23",
	"latest_version": "1.8.23",
	"version_code": "1823",
	"download_url": "https://resources.mybb.com/downloads/mybb_1823.zip",
	"release_date": "Jul 17, 2020",
	"download_size": "2.14 MB",
	"type": "security &amp; maintenance"
  }
}
*/

class mybb extends SoftwareCheck
{
    public static $name = 'MyBB';
    public static $vendor = 'MyBB Group';
    public static $homepage = 'https://mybb.com';
    public static $type = 'json';
    public static $enabled = true;
    var $uri = 'https://mybb.com/version_check.json';

    function get_data()
    {
        $data = http::get($this->uri);
        $json_decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        
        return $json_decoded;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0 || !array_key_exists('mybb', $data))
        {
            return $data;
        }

        $versions = array();
        $version_info = array();
        if (array_key_exists('latest_version', $data['mybb']))
        {
            // Determine branch from first two octets
            $version = $data['mybb']['latest_version'];
            $version_parts = explode('.', $version);
            if (count($version_parts) < 3)
            {
                return $versions;
            }
            $branch = $version_parts[0] . '.' . $version_parts[1];
            $version_info['version'] = $version;

            // If there's a release date, use it, otherwise mark it as right now
            if (array_key_exists('release_date', $data['mybb']))
            {
                //strptime($strf, '%b %d, %Y')
                if (($timestamp = strtotime($data['mybb']['release_date'])) !== false)
                {
                    $time = strftime("%Y-%m-%d %H:%M:%S", $timestamp);
                }
                else
                {
                    $time = strftime("%Y-%m-%d %H:%M:%S");
                    $version_info['estimated'] = true;
                }
            }
            else
            {
                $time = strftime("%Y-%m-%d %H:%M:%S");
                $version_info['estimated'] = true;
            }
            $version_info['release_date'] = $time;

            $versions[$branch] = $version_info;
        }

        return $versions;
    }
}