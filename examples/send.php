<?php

require __DIR__ . '/config.php';

use FR\BackgroundMail\BackgroundMail;

try {
    // Prepare recipients list
    // @see \FR\BackgroundMail\BackgroundMail::send() for reference

    // SMTP Details
    $smtp = [
        'host' => 'smtp.office365.com',
        'port' => '587',
        'encryption' => 'tls',
        'username' => 'no-reply@lums.edu.pk',
        'password' => ''
    ];

    // Attachments

    // Attachment 1 using URL, Default type will be 'simple'
    $attachment_1 = [
        // Required. Complete path or URL of the file
        'path' => 'https://test.lums.edu.pk/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-1.txt',
        // Required. Name of the file when user receive email in inbox
        'name' => 'attachment-name-1.txt'
    ];

    // Attachment 2 using complete path, Default type will be 'simple'
    $attachment_2 = [
        // Required. Complete path or URL of the file
        'path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-2.txt',
        // Required. Name of the file when user receive email in inbox
        'name' => 'attachment-name-2.txt'
    ];

    // Attachment 3
    $attachment_3 = [
        'path' => 'https://test.lums.edu.pk/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-3.txt',
        'name' => 'attachment-name-3.txt'
    ];
    // Attachment 4
    $attachment_4 = [
        'path' => 'https://test.lums.edu.pk/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-4.txt',
        'name' => 'attachment-name-4.txt'
    ];

    // Attachment 5 using URL, with inline type
    $attachment_5 = [
        // Required. Complete path or URL of the file
        'path' => 'https://test.lums.edu.pk/faisalrehmanid-fr-background-mail/examples/sample-attachments/image.jpg',
        // Required. Name of the file when user receive email in inbox
        'name' => 'image.jpg',
        // Optional. inline | simple Default is 'simple'. Case insensitive
        'type' => 'inline',
        // Required. When type is inline
        'content_type' => 'image/jpeg'
    ];

    // Attachment 6 using path and inline
    $attachment_6 = [
        // Required. Complete path or URL of the file
        'path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/examples/sample-attachments/invite.ics',
        // Required. Name of the file when user receive email in inbox
        'name' => 'invite.ics',
        // Optional. inline | simple Default is 'simple'. Case insensitive
        'type' => 'inline',
        // Required. When type is inline
        'content_type' => 'text/calendar'
    ];

    $index = 0;
    $recipients = [];
    $keys = [];
    if (($handle = fopen('./mail-recipients-csv/to.csv', 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if ($index == 0) { // Read header row
                $keys = $row;
            } elseif ($index > 0) { // Skip first header row
                $vars = [];
                foreach ($row as $k => $v) {
                    $vars[$keys[$k]] = $v;
                }

                require 'body.php';

                $recipients[] = [
                    '___SMTP_JSON___' => json_encode($smtp),
                    '___FROM___' => 'no-reply@lums.edu.pk: No Reply',
                    '___SENDER___' => '',
                    '___RETURN_PATH___' => '',
                    '___SUBJECT___' => 'Hi, ___NAME___',
                    '___BODY___' => $body,
                    '___TO___' => $vars['___TO___'],
                    '___REPLY_TO___' => '',
                    '___CC___' => '',
                    '___BCC___' => '',
                    '___ATTACHMENT_1_JSON___' => json_encode($attachment_1),
                    '___ATTACHMENT_2_JSON___' => json_encode($attachment_2),
                    '___ATTACHMENT_3_JSON___' => json_encode($attachment_3),
                    '___ATTACHMENT_4_JSON___' => json_encode($attachment_4),
                    '___ATTACHMENT_5_JSON___' => json_encode($attachment_5),
                    '___ATTACHMENT_6_JSON___' => json_encode($attachment_6),

                    // Custom variables
                    '___NAME___' => $vars['___NAME___'],
                ];
            }
            $index++;
        }

        fclose($handle);
    }

    // Create recipients CSV
    $recipient_csv_name = 'to-final.csv';
    $recipient_csv_path = $config['recipients_path'] . '/' . $recipient_csv_name;

    // Header Row
    $header = array_keys($recipients[0]);
    $fp = fopen($recipient_csv_path, 'w');
    fputcsv($fp, $header);
    foreach ($recipients as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    // Sending email to $recipients
    $BackgroundMail = new BackgroundMail($config);

    // $notify_to is optional and can be empty string but when given
    // it will send notification email about job status to given email addresses

    // Notification email will be sent when:
    //  * Job has been Started
    //  * Job has been Completed
    //  * Job has been Canceled
    $notify_to = 'faisal.rehman@test.lums.edu.pk: Faisal Rehman; faisalrehmanid@hotmail.com;';

    // Send mail or mass mail in background
    $job_id = $BackgroundMail->send($recipient_csv_name, $notify_to);

    echo '64 Chars unique job id: ' . $job_id;
} catch (\Exception $e) {
    $message = $e->getMessage();
    echo $message;

    pr($e);
}
