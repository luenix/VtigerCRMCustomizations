<?php

function safe_GET(
    $name = null,
    $value = false,
    $option = "default"
) {
    $option = false; // Old version depricated part
    $content = '';

    if (!empty($_GET[$name])) {
        $content = trim($_GET[$name]);
    } else {
        $content = (!empty($value) && !is_array($value)) ? trim($value) : false;
    }

    if (is_numeric($content)) {
        $content = preg_replace("@([^0-9])@Ui", "", $content);
    } elseif (is_bool($content)) {
        $content = ($content ? true : false);
    } elseif (is_float($content)) {
        $content = preg_replace("@([^0-9\,\.\+\-])@Ui", "", $content);
    } elseif (
        is_string($content) &&
        !filter_var($content, FILTER_VALIDATE_URL) &&
        !filter_var($content, FILTER_VALIDATE_EMAIL) &&
        !filter_var($content, FILTER_VALIDATE_IP) &&
        !filter_var($content, FILTER_VALIDATE_FLOAT)
    ) {
        $content = preg_replace("@([^a-zA-Z0-9\+\-\_\*\@\$\!\;\.\?\#\:\=\%\/\ ]+)@Ui", "", $content);
    } else {
        $content = false;
    }

    return $content;
}
