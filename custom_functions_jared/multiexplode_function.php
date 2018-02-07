<?php

function multiexplode (
    array $delimiters,
    $string
) {
    return explode($delimiters[0], str_replace($delimiters, $delimiters[0], $string));
}
