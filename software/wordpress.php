<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
{
  "offers": [
    {
      "response": "upgrade",
      "download": "https://downloads.wordpress.org/release/wordpress-5.4.2.zip",
      "locale": "en_US",
      "packages": {
        "full": "https://downloads.wordpress.org/release/wordpress-5.4.2.zip",
        "no_content": "https://downloads.wordpress.org/release/wordpress-5.4.2-no-content.zip",
        "new_bundled": "https://downloads.wordpress.org/release/wordpress-5.4.2-new-bundled.zip",
        "partial": false,
        "rollback": false
      },
      "current": "5.4.2",
      "version": "5.4.2",
      "php_version": "5.6.20",
      "mysql_version": "5.0",
      "new_bundled": "5.3",
      "partial_version": false
    },
    {
      "response": "autoupdate",
      "download": "https://downloads.wordpress.org/release/wordpress-5.4.2.zip",
      "locale": "en_US",
      "packages": {
        "full": "https://downloads.wordpress.org/release/wordpress-5.4.2.zip",
        "no_content": "https://downloads.wordpress.org/release/wordpress-5.4.2-no-content.zip",
        "new_bundled": "https://downloads.wordpress.org/release/wordpress-5.4.2-new-bundled.zip",
        "partial": false,
        "rollback": false
      },
      "current": "5.4.2",
      "version": "5.4.2",
      "php_version": "5.6.20",
      "mysql_version": "5.0",
      "new_bundled": "5.3",
      "partial_version": false,
      "new_files": true
    },
    ...
*/

class wordpress extends SoftwareCheck
{
    public static $name = 'WordPress';
    public static $vendor = 'WordPress Foundation';
    public static $homepage = 'https://wordpress.org';
    public static $type = 'json';
    public static $enabled = true;
    var $uri = 'https://api.wordpress.org/core/version-check/1.7/';

    function get_data()
    {
        $data = http::get($this->uri);
        $json_decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        
        return $json_decoded;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0 || !array_key_exists('offers', $data))
        {
            return array();
        }

        $versions = array();
        foreach($data['offers'] as $release)
        {
            $version_info = array();
            if (isset($release['response']) && isset($release['response']) == 'upgrade')
            {
                if (!isset($release['version']))
                {
                    continue;
                }

                // Determine branch from first two octets
                $version_parts = explode('.', $release['version']);
                if (count($version_parts) < 3)
                {
                    continue;
                }
                $branch = $version_parts[0] . '.' . $version_parts[1];
                
                $version_info['version'] = $release['version'];
                $version_info['release_date'] = date("Y-m-d H:i:s");
                $version_info['estimated'] = true;
                $versions[$branch] = $version_info;
            }
        }

        return $versions;
    }
}