<?php
require __DIR__ . '/config.php';

use FR\BackgroundMail\BackgroundMail;

try {
    $BackgroundMail = new BackgroundMail($config);

    // Create database structure if already not created
    $result = $BackgroundMail->createDBStructure();

    if ($result)
        echo 'Database structure created successfully.';
    else
        echo 'Could not create database structure. Already exists.';
} catch (\Exception $e) {
    $message = $e->getMessage();
    echo $message;

    pr($e);
}
