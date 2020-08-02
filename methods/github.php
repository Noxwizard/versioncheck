<?php
class github
{
    static function get($owner, $repo)
    {
        global $provider_configs;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$owner/$repo/releases?per_page=10");
        curl_setopt($ch, CURLOPT_USERAGENT, 'Version Checker - versioncheck.net');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);

        if (isset($provider_configs['github']['clientId']))
        {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "{$provider_configs['github']['clientId']}:{$provider_configs['github']['clientSecret']}");
        }

        $result = curl_exec($ch);
        $headers = curl_getinfo($ch);

        curl_close($ch);

        if ($result === false || $headers['header_size'] == 0 || $headers['http_code'] != 200)
        {
            throw new \RuntimeException(curl_error($ch));
        }

        return $result;
    }
}