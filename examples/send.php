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
        'username' => 'no-reply@test.com',
        'password' => 'password'
    ];

    /*
    // Attachments

    // Attachment 1
    // `path` and `name` required. `path` Could be URL or complete file path on server
    $attachment_1 = [
        'path' => 'https://test.com/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-1.txt',
        'name' => 'attachment-name-1.txt'
    ];
    // Attachment 2
    $attachment_2 = [
        'path' => 'https://test.com/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-2.txt',
        'name' => 'attachment-name-2.txt'
    ];
    // Attachment 3
    $attachment_3 = [
        'path' => 'https://test.com/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-3.txt',
        'name' => 'attachment-name-3.txt'
    ];
    // Attachment 4
    $attachment_4 = [
        'path' => 'https://test.com/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-4.txt',
        'name' => 'attachment-name-4.txt'
    ];
    // Attachment 5
    $attachment_5 = [
        'path' => 'https://test.com/faisalrehmanid-fr-background-mail/examples/sample-attachments/attachment-5.txt',
        'name' => 'attachment-name-5.txt'
    ];
    */

    $index = 0;
    $recipients = [];
    $keys = [];
    if (($handle = fopen("./to.csv", "r")) !== FALSE) {
        while (($row = fgetcsv($handle)) !== FALSE) {
            if ($index == 0) { // Read header row
                $keys = $row;
            } elseif ($index > 0) // Skip first header row
            {
                $vars = [];
                foreach ($row as $k => $v)
                    $vars[$keys[$k]] = $v;

                require('body.php');

                foreach ($vars as $key => $value)
                    $body = str_replace($key, $value, $body);

                $to = $vars['___TO___'];

                $recipients[] = [
                    "smtp_json" => json_encode($smtp),
                    "from" => "no-reply@test.com: No Reply",
                    "sender" => "",
                    "return_path" => "",
                    "subject" => "Test Subject",
                    "body" => $body,
                    "to" => $to,
                    "reply_to" => "",
                    "cc" => "",
                    "bcc" => "",
                    "attachment_1_json" => "",
                    "attachment_2_json" => "",
                    "attachment_3_json" => "",
                    "attachment_4_json" => "",
                    "attachment_5_json" => "",
                    "attachment_6_json" => "",
                ];
            }
            $index++;
        }

        fclose($handle);
    }
    // pr($recipients);

    // Sending email to $recipients
    $BackgroundMail = new BackgroundMail($config);

    // $notify_to is optional and can be empty string but when given 
    // it will send notification email about job status to given email addresses

    // Notification email will be sent when:
    //  * Job has been Started
    //  * Job has been Completed
    //  * Job has been Canceled
    $notify_to = 'faisal.rehman@test.com: Faisal Rehman; faisalrehmanid@hotmail.com;';

    // Send mail or mass mail in background
    $job_id = $BackgroundMail->send($recipients, $notify_to);

    echo '64 Chars unique job id: ' . $job_id;
} catch (\Exception $e) {
    $message = $e->getMessage();
    echo $message;

    pr($e);
}
