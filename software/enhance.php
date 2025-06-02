<?php
require_once __DIR__ . '/abstract.php';
include_once(__DIR__ . '/../methods/http.php');

/*
Example structure of Enhance release notes:

# 12.6.0

### 27th May 2025

Latest

**_Important: You must update all the servers in your cluster when applying this update._**

### Enhanced

- Reinstated backup failure logging to the activity log for customers on version 12.x.
- Moved logic for management of local .my.cnf file to appcd service for improved performance.

### Fixed

- Race condition where the local .my.cnf had been modified and website files were restored before databases which prevented a restore of the database.
- Descriptive text for email rate limits was incorrect in English language.

# 12.5.1

### 21st May 2025

### Fixed

- Using SSO when a previously expired session existed would require a hard refresh.
*/

class enhance extends SoftwareCheck
{
    public static $name = 'Enhance Platform';
    public static $vendor = 'Enhance';
    public static $homepage = 'https://enhance.com/';
    public static $type = 'html';
    public static $enabled = true;
    var $uri = 'https://enhance.com/support/release-notes.html';

    function get_data()
    {
        $data = http::get($this->uri);
        return $data;
    }

    function get_versions($data = '')
    {
        if (empty($data)) {
            return array();
        }

        // Initialize versions array
        $versions = array();
        $branch_versions = array();
        
        // Use DOMDocument for proper HTML parsing
        $dom = new DOMDocument();
        
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        
        // Load the HTML content
        $dom->loadHTML($data);
        
        // Reset errors
        libxml_clear_errors();
        
        // Get all H1 elements (version headers)
        $h1Elements = $dom->getElementsByTagName('h1');
        
        foreach ($h1Elements as $h1) {
            $version_text = trim($h1->textContent);
            
            // Skip non-core versions (Appcd, WHMCS module, etc.)
            if (preg_match('/^(Appcd|WHMCS module|PHP packages)/i', $version_text)) {
                continue;
            }
            
            // Extract version number using regex
            if (preg_match('/^(\d+\.\d+\.\d+)/', $version_text, $matches)) {
                $version = $matches[1];
            } else {
                // Skip entries that don't start with a version number
                continue;
            }
            
            // Extract version components
            $version_parts = explode('.', $version);
            if (count($version_parts) < 3) {
                continue;
            }
            
            // Determine branch (major.minor)
            $branch = $version_parts[0] . '.' . $version_parts[1];
            
            // Initialize version info
            $version_info = array(
                'version' => $version,
                'release_date' => date("Y-m-d H:i:s"), // Default to current date/time
                'estimated' => true
            );
            
            // Find the release date (in H3 tag after the H1)
            $node = $h1->nextSibling;
            while ($node) {
                if ($node->nodeType === XML_ELEMENT_NODE && $node->tagName === 'h3') {
                    $date_text = trim($node->textContent);
                    
                    // Try to parse the date (format like "27th May 2025")
                    if (($timestamp = strtotime($date_text)) !== false) {
                        $version_info['release_date'] = date("Y-m-d H:i:s", $timestamp);
                        $version_info['estimated'] = false;
                    }
                    break;
                }
                $node = $node->nextSibling;
            }
            
            // Check if this is the latest version by looking for "Latest" text
            $node = $h1;
            $is_latest = false;
            
            // Look for "Latest" text in the next few siblings
            $sibling_count = 0;
            $max_siblings_to_check = 10; // Limit how far we look
            
            while ($node && $sibling_count < $max_siblings_to_check) {
                $node = $node->nextSibling;
                $sibling_count++;
                
                if (!$node) {
                    break;
                }
                
                // Check text nodes and element nodes
                if ($node->nodeType === XML_TEXT_NODE) {
                    if (stripos($node->textContent, 'Latest') !== false) {
                        $is_latest = true;
                        break;
                    }
                } elseif ($node->nodeType === XML_ELEMENT_NODE) {
                    // Skip to next H1 (which would be the next version)
                    if ($node->tagName === 'h1') {
                        break;
                    }
                    
                    // Check if this element contains "Latest"
                    if (stripos($node->textContent, 'Latest') !== false) {
                        $is_latest = true;
                        break;
                    }
                }
            }
            
            // Add announcement link for latest version
            if ($is_latest) {
                $version_info['announcement'] = 'https://enhance.com/support/release-notes.html#' . $version;
            }
            
            // Store in branch_versions for later processing
            if (!isset($branch_versions[$branch])) {
                $branch_versions[$branch] = array();
            }
            
            $branch_versions[$branch][] = array(
                'version' => $version,
                'info' => $version_info,
                'patch' => (int)$version_parts[2]
            );
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
        
        return $versions;
    }
}
