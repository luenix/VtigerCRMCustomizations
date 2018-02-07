<?php

function __autoload($class_name)
{
    /** @noinspection PhpIncludeInspection */
    require_once $class_name . '.php';
}

date_default_timezone_set('America/Los_Angeles');

// Parameters to pass into MyAvailabilityMap->generatePoints().
$points_filename = './points.html';
$debug_output = true;
$debug_rows = false;

AvailabilityMap::generatePoints($points_filename, $debug_output, $debug_rows);

if (file_exists($points_filename)) {
    $new_points_filename = substr($points_filename, 0, strrpos($points_filename, '.')) . '_' . date('Y-m-dTH-i-s') . substr($points_filename, strrpos($points_filename, '.'));
    if (copy($points_filename, $new_points_filename)) {
        chmod($new_points_filename, 0644);
        chown($new_points_filename, 'apache');
        chgrp($new_points_filename, 'apache');
        echo 'Copied created file \'' . $points_filename . '\' to \'' . $new_points_filename . '\'' . PHP_EOL;
    }
}
