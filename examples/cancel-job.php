<?php
require __DIR__ . '/config.php';

use FR\BackgroundMail\BackgroundMail;

try {
    $BackgroundMail = new BackgroundMail($config);

    // Cancel background job using job_id
    $job_id = $_GET['job_id'];
    $BackgroundMail->cancelJob($job_id);

    echo 'Job has been canceled';
} catch (\Exception $e) {
    $message = $e->getMessage();
    echo $message;

    pr($e);
}
