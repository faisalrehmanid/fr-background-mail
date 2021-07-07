<?php
require __DIR__ . '/config.php';

use FR\BackgroundMail\BackgroundMail;

try {
    $BackgroundMail = new BackgroundMail($config);

    // Delete sent log upto given datetime inclusive
    $upto = '2020-01-01 17:00:00'; // Datetime format: Y-m-d H:i:s
    $BackgroundMail->deleteSentLog($upto);

    echo 'Sent Log Deleted';
} catch (\Exception $e) {
    $message = $e->getMessage();
    echo $message;

    pr($e);
}
