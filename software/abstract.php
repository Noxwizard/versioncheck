<?php
abstract class SoftwareCheck
{
    public static $name;
    public static $vendor;
    public static $homepage;
    public static $type;
    public static $enabled;

    abstract protected function get_data();
    abstract protected function get_versions($data = array());
}