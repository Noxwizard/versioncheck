<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
Example structure of Enhance apt repository Packages file:

Package: ecp-core
Version: 12.6.0
Architecture: amd64
Maintainer: Enhance Ltd <backend@enhance.com>
Installed-Size: 87858
Depends: cron, curl, ecp-php56, ecp-php70, ecp-php71, ecp-php72, ecp-php73, ecp-php74, ecp-php80, ecp-php81, ecp-php82, ecp-php83, ecp-php84, libc6 (>= 2.34), libc6 (>= 2.38), libc6 (>= 2.39), libmilter1.0.1 (>= 8.14.1), libnss3-dev, libpam0g (>= 0.99.7.1), libssl3t64 (>= 3.0.0), linux-image-extra-virtual, openssh-server, openssl, quota, rsync, ufw, zlib1g (>= 1:1.1.4)
Conflicts: appcd
Replaces: appcd
Filename: pool/noble/ecp-core_12.6.0_amd64.deb
Size: 22096584
MD5sum: b8d084689177aa18b52ff7ee01443156
SHA1: b41084091113c5090f49a57f4f77b0f2d5774eec
SHA256: 4be9b14e6004debcdbc04819d07f2e78e2624760178cd7e72041722902f87704
Priority: optional
Description: [generated from Rust crate appcd]

Package: ecp-core
Version: 12.5.1
...
*/

class enhance extends SoftwareCheck
{
    public static $name = 'Enhance Platform';
    public static $vendor = 'Enhance';
    public static $homepage = 'https://enhance.com/';
    public static $type = 'apt';
    public static $enabled = true;
    var $uri = 'https://apt.enhance.com/dists/noble/main/binary-amd64/Packages';
    var $release_uri = 'https://apt.enhance.com/dists/noble/Release';

    function get_data()
    {
        // Get the Packages file
        $packages_data = http::get($this->uri);
        
        // Get the Release file for date information
        try {
            $release_data = http::get($this->release_uri);
        } catch (Exception $e) {
            // If we can't get the Release file, just use the Packages data
            $release_data = '';
        }
        
        return [
            'packages' => $packages_data,
            'release' => $release_data
        ];
    }

    function get_versions($data = array())
    {
        if (empty($data) || !isset($data['packages']) || empty($data['packages'])) {
            return array();
        }

        // Extract repository date from Release file if available
        $repo_date = date("Y-m-d H:i:s"); // Default to current date/time
        $estimated = true;
        
        if (!empty($data['release'])) {
            if (preg_match('/Date:\s+([^\n]+)/', $data['release'], $matches)) {
                $date_str = trim($matches[1]);
                if (($timestamp = strtotime($date_str)) !== false) {
                    $repo_date = date("Y-m-d H:i:s", $timestamp);
                    $estimated = false;
                }
            }
        }
        
        // Parse Packages file
        $versions = array();
        $branch_versions = array();
        $latest_version = null;
        $latest_version_full = null;
        
        // Split the Packages file into individual package entries
        $package_entries = explode("\n\n", $data['packages']);
        
        foreach ($package_entries as $entry) {
            // Check if this is an ecp-core package
            if (strpos($entry, 'Package: ecp-core') === false) {
                continue;
            }
            
            // Extract version
            if (preg_match('/Version:\s+(\d+\.\d+\.\d+)/', $entry, $matches)) {
                $version = $matches[1];
                
                // Extract version components
                $version_parts = explode('.', $version);
                if (count($version_parts) < 3) {
                    continue;
                }
                
                // Determine branch (major.minor)
                $branch = $version_parts[0] . '.' . $version_parts[1];
                $patch = (int)$version_parts[2];
                
                // Store in branch_versions for later processing
                if (!isset($branch_versions[$branch])) {
                    $branch_versions[$branch] = array();
                }
                
                $branch_versions[$branch][] = array(
                    'version' => $version,
                    'patch' => $patch,
                    'info' => array(
                        'version' => $version,
                        'release_date' => $repo_date,
                        'estimated' => $estimated
                    )
                );
                
                // Track the latest version (highest version number)
                if ($latest_version === null || version_compare($version, $latest_version_full, '>')) {
                    $latest_version = $branch;
                    $latest_version_full = $version;
                }
            }
        }
        
        // For each branch, keep only the highest patch version
        foreach ($branch_versions as $branch => $branch_data) {
            // Sort by patch version (descending)
            usort($branch_data, function($a, $b) {
                return $b['patch'] - $a['patch'];
            });
            
            // Keep only the highest patch version
            if (!empty($branch_data)) {
                $versions[$branch] = $branch_data[0]['info'];
            }
        }
        
        // Mark only the highest version as the latest with an announcement link
        if ($latest_version !== null && isset($versions[$latest_version])) {
            $versions[$latest_version]['announcement'] = 'https://enhance.com/support/release-notes.html#' . $latest_version_full;
        }
        
        return $versions;
    }
}
