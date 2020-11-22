<?php

function detectAndLoadVendor($path = __DIR__): void
{
    $path = realpath($path);
    if ('/' == $path) {
        die("Could not find a suitable /vendor directory! Maybe you need to run composer install!\n");
    }

    foreach (new DirectoryIterator($path) as $fileInfo) {
        if ($fileInfo->isDir() && 'vendor' == $fileInfo->getFilename()) {
            define('VENDOR_PATH', $fileInfo->getRealPath());
            require_once VENDOR_PATH.'/autoload.php';

            return;
        }
    }
    detectAndLoadVendor($path.'/../');
}

detectAndLoadVendor();
