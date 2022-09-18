<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');


/*
https://dl.min.io/server/minio/release/linux-amd64/minio.sha256sum
c1401d77422171d5c8b6218f2f4f6836308ffaad8218db3f99164e66ef8ed6db minio.RELEASE.2022-09-17T00-09-45Z
*/

class minio extends SoftwareCheck
{
    public static $name = 'MinIO';
    public static $vendor = 'MinIO, Inc.';
    public static $homepage = 'https://min.io/';
    public static $type = 'custom';
    public static $enabled = true;
    var $regex = '/^[a-f0-9]{64} minio.RELEASE.(\d+)-(\d+)-(\d+)T(\d+)-(\d+)-(\d+)Z/';
    var $uri = 'https://dl.min.io/server/minio/release/linux-amd64/minio.sha256sum';

    function get_data()
    {
        $data = http::get($this->uri);
        
        if (preg_match($this->regex, $data, $matches))
        {
            // It's a variant of RFC3339, but with dashes
            $release_stamp = gmmktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
            
            $info = [];
            $info['version'] = "RELEASE.{$matches[1]}-{$matches[2]}-{$matches[3]}T{$matches[4]}-{$matches[5]}-{$matches[6]}Z";
            $info['release_date'] = strftime("%Y-%m-%d %H:%M:%S", $release_stamp);

            return [$info];
        }
        
        return array();
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
            $branch = '-';

            $version_info = [
                'version'       => $release['version'],
                'estimated'     => false,
                'release_date'  => $release['release_date'],
            ];

            $versions[$branch] = $version_info;
        }

        return $versions;
    }
}