<?php

class http
{
    static function get($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Version Checker');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $result = curl_exec($ch);
        $headers = curl_getinfo($ch);

        curl_close($ch);

        if ($headers['header_size'] == 0 || $headers['http_code'] != 200)
        {
            throw new \RuntimeException($result);
        }

        return $result;
    }
}