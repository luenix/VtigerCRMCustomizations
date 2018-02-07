<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Stefan Warnat <support@stefanwarnat.de>
 * Date: 20.07.15 10:02
 * You must not use this file without permission.
 */
function getFolderId($folder, $connectionID) {
    sw_autoload_register("CloudFile", "~/modules/CloudFile/lib");

    $adapter = \CloudFile\Connection::getAdapter($connectionID);

    $adapter->chdir($folder);
    return $adapter->getCurrentPathKey();
}