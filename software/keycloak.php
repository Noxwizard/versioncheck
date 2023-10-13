<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/github.php');

//https://docs.github.com/en/rest/reference/repos#list-releases

class keycloak extends SoftwareCheck
{
    public static $name = 'Keycloak';
    public static $vendor = 'RedHat';
    public static $homepage = 'https://www.keycloak.org';
    public static $type = 'github';
    public static $enabled = true;
    var $owner = 'keycloak';
    var $repo = 'keycloak';
    var $regex = '/^(\d+\.\d+\.\d+).*/';

    function get_data()
    {
        $data = github::get($this->owner, $this->repo);
        $json_decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        
        return $json_decoded;
    }

    function get_versions($data = array())
    {
        if (count($data) == 0)
        {
            return array();
        }

        $versions = array();
        foreach($data as $release)
        {
            // Ignore pre-releases
            if (isset($release['prerelease']) && $release['prerelease'] == true)
            {
                continue;
            }

            if (!isset($release['tag_name']))
            {
                continue;
            }

            preg_match($this->regex, $release['tag_name'], $matches);
            if (count($matches) != 2)
            {
                continue;
            }

            $version_info = array();
            $version_info['version'] = $matches[1];

            // Determine branch from first two octets
            $version = $version_info['version'];
            $version_parts = explode('.', $version);
            if (count($version_parts) < 3)
            {
                continue;
            }
            $branch = $version_parts[0] . '.' . $version_parts[1];

            if (($timestamp = strtotime($release['published_at'])) !== false)
            {
                $time = date("Y-m-d H:i:s", $timestamp);
            }
            else
            {
                $time = date("Y-m-d H:i:s");
                $version_info['estimated'] = true;
            }
            $version_info['release_date'] = $time;

            $version_info['announcement'] = $release['html_url'];

            // Multiple versions for the same branch will be found. We only want the latest version.
            if (isset($versions[$branch]))
            {
                if (version_compare($version_info['version'], $versions[$branch]['version'], '>'))
                {
                    $versions[$branch] = $version_info;
                }
                else
                {
                    continue;
                }
            }
            else
            {
                $versions[$branch] = $version_info;
            }
        }

        return $versions;
    }
}
