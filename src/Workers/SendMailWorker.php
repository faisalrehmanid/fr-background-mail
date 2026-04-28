<?php

namespace FR\BackgroundMail\Workers;

use FR\BackgroundMail\Storage\StorageInterface;
use FR\BackgroundMail\Helper\Util;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class SendMailWorker
{
    /**
     * Configurations
     *
     * @var array $config
     * @see \FR\BackgroundMail\BackgroundMail::__construct $config
     */
    protected $config;

    /**
     * Storage to store sent log
     *
     * @var object \FR\BackgroundMail\Storage\StorageInterface
     */
    protected $Storage;

    /**
     * Inject Storage and config to worker
     *
     * @param StorageInterface $Storage
     * @param array $config
     * @see \FR\BackgroundMail\BackgroundMail::__construct $config
     */
    public function __construct(StorageInterface $Storage, array $config)
    {
        $this->Storage = $Storage;
        $this->config = $config;
    }

    /**
     * Worker listening for incomming jobs
     *
     * @param string $worker_id Used to show in command line when run: `gearadmin --status`
     * @return void
     */
    public function listen($worker_id = 'SendMailWorker')
    {
        // Gearman servers from config
        // Comma separated servers e.g. 127.0.0.1:4730,127.0.0.1:4731
        $servers = $this->config['gearman']['worker']['servers'];

        $worker = new \GearmanWorker();
        $worker->addServers($servers);
        $worker->addFunction(
            $worker_id,
            [$this, 'sendMail'],
            [
                'Storage' => $this->Storage,
            ]
        );
        while ($worker->work()) {
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                throw new \Exception('Return code: ' . $worker->returnCode());
                break;
            }
        }
    }

    /**
     * Build Graph API recipients array from parsed emails.
     *
     * @param array $emails
     * @return array
     */
    private function toGraphRecipients($emails)
    {
        $items = [];

        foreach ($this->toAddressList($emails) as $address) {
            $payload = [
                'emailAddress' => [
                    'address' => $address->getAddress(),
                ],
            ];

            if ($address->getName()) {
                $payload['emailAddress']['name'] = $address->getName();
            }

            $items[] = $payload;
        }

        return $items;
    }

    /**
     * Convert scalar and textual values into a bool.
     *
     * @param mixed $value
     * @param bool $default
     * @return bool
     */
    private function toBool($value, $default = true)
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Best effort MIME type lookup for attachment.
     *
     * @param string $path
     * @param string $fallback
     * @return string
     */
    private function guessMimeType($path, $fallback = 'application/octet-stream')
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (!empty($mime)) {
                return $mime;
            }
        }

        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = @$finfo->file($path);
            if (!empty($mime)) {
                return $mime;
            }
        }

        return $fallback;
    }

    /**
     * Send email using Microsoft Graph API.
     *
     * @param array $graphql
     * @param array $from
        * @param string|array $sender
     * @param string $subject
     * @param string $body
     * @param array $to
     * @param array $reply_to
     * @param array $cc
     * @param array $bcc
     * @param array $attachments
     * @throws \Exception
     * @return void
     */
    private function sendViaMicrosoftGraph(
        array $graphql,
        array $from,
        $sender,
        $subject,
        $body,
        array $to,
        array $reply_to,
        array $cc,
        array $bcc,
        array $attachments
    ) {
        $access_token = trim((string) (@$graphql['access_token'] ?: @$graphql['token'] ?: @$graphql['bearer_token']));
        if (empty($access_token)) {
            throw new \Exception('GRAPHQL access_token could not be empty', 500);
        }

        $endpoint = trim((string) (@$graphql['endpoint'] ?: @$graphql['url'] ?: @$graphql['send_mail_url']));
        if (empty($endpoint)) {
            $user_id = trim((string) (@$graphql['user_id'] ?: @$graphql['sender_user_id'] ?: @$graphql['user']));
            if (!empty($user_id) && strtolower($user_id) !== 'me') {
                $endpoint = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($user_id) . '/sendMail';
            } else {
                $endpoint = 'https://graph.microsoft.com/v1.0/me/sendMail';
            }
        }

        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $body,
            ],
            'toRecipients' => $this->toGraphRecipients($to),
        ];

        if (!empty($reply_to)) {
            $message['replyTo'] = $this->toGraphRecipients($reply_to);
        }

        if (!empty($cc)) {
            $message['ccRecipients'] = $this->toGraphRecipients($cc);
        }

        if (!empty($bcc)) {
            $message['bccRecipients'] = $this->toGraphRecipients($bcc);
        }

        $from_list = $this->toAddressList($from);
        if (!empty($from_list)) {
            $message['from'] = [
                'emailAddress' => [
                    'address' => $from_list[0]->getAddress(),
                    'name' => $from_list[0]->getName(),
                ],
            ];
        }

        $sender_list = $this->toAddressList($sender);
        if (!empty($sender_list)) {
            $message['sender'] = [
                'emailAddress' => [
                    'address' => $sender_list[0]->getAddress(),
                    'name' => $sender_list[0]->getName(),
                ],
            ];
        }

        $graph_attachments = [];
        for ($i = 1; $i <= 6; $i++) {
            $attachment = $attachments[$i];
            if (empty($attachment)) {
                continue;
            }

            @$path = trim($attachment['path']);
            @$name = trim($attachment['name']);
            @$type = strtolower(trim($attachment['type']));
            @$content_type = strtolower(trim($attachment['content_type']));

            if (!$path) {
                throw new \Exception('Attachment ' . $i . ' `path` could not be empty', 500);
            }

            $http = strtolower(substr($path, 0, 7));
            $https = strtolower(substr($path, 0, 8));
            if ($http != 'http://' && $https != 'https://' && !@is_file($path)) {
                throw new \Exception('Attachment ' . $i . ' file must be valid URL or complete file path not found', 500);
            }

            if (!$name) {
                throw new \Exception('Attachment ' . $i . ' name cannot be empty', 500);
            }

            $pathinfo = pathinfo($path);
            if (in_array(strtolower(@$pathinfo['extension']), ['ics'])) {
                $type = 'inline';
                $content_type = 'text/calendar';
            }

            if (!$content_type) {
                $content_type = $this->guessMimeType($path);
            }

            $attachment_payload = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $name,
                'contentType' => $content_type,
                'contentBytes' => base64_encode($this->readAttachmentContents($path)),
            ];

            if ($type === 'inline') {
                $attachment_payload['isInline'] = true;
                $attachment_payload['contentId'] = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name) . '_' . $i;
            }

            $graph_attachments[] = $attachment_payload;
        }

        if (!empty($graph_attachments)) {
            $message['attachments'] = $graph_attachments;
        }

        $payload = [
            'message' => $message,
            'saveToSentItems' => $this->toBool(@$graphql['save_to_sent_items'], true),
        ];

        $timeout = (int) @$graphql['timeout'];
        if ($timeout <= 0) {
            $timeout = 45;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Microsoft Graph request failed: ' . $error, 500);
        }

        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code < 200 || $http_code >= 300) {
            $error_details = trim((string) $response);
            $decoded = json_decode($response, true);
            if (!empty($decoded['error']['message'])) {
                $error_details = $decoded['error']['message'];
            }

            throw new \Exception('Microsoft Graph send failed. HTTP ' . $http_code . '. ' . $error_details, $http_code ?: 500);
        }
    }

    /**
     * Convert mixed email input into Symfony Address objects.
     *
     * @param string|array $emails
     * @return Address[]
     */
    private function toAddressList($emails)
    {
        $addresses = [];

        if (empty($emails)) {
            return $addresses;
        }

        if (is_string($emails)) {
            $addresses[] = new Address($emails);
            return $addresses;
        }

        foreach ((array) $emails as $email => $name) {
            if (is_int($email)) {
                $addresses[] = new Address($name);
            } else {
                $addresses[] = new Address($email, (string) $name);
            }
        }

        return $addresses;
    }

    /**
     * Read attachment from local path or URL.
     *
     * @param string $path Complete path or URL of the file
     * @return string
     */
    private function readAttachmentContents($path)
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \Exception('Unable to read attachment file: ' . $path, 500);
        }

        return $contents;
    }

    /**
     * Simple file attachment.
     *
     * @param string $path Complete path or URL of the file
     * @param string $name Name of the file when user receive email in inbox
     * @return DataPart
     */
    private function simpleAttachment($path, $name)
    {
        $contents = $this->readAttachmentContents($path);

        return new DataPart($contents, $name);
    }

    /**
     * Inline file attachment will be parse by email client
     *
     * @param string $path Complete path or URL of the file
     * @param string $name Name of the file when user receive email in inbox
     * @param string $content_type File content type
     * @return DataPart
     */
    private function inlineAttachment($path, $name, $content_type)
    {
        $contents = trim($this->readAttachmentContents($path));

        $attachment = new DataPart($contents, $name, $content_type);
        $attachment->asInline();

        return $attachment;
    }

    /**
     * Send single email and save sent log in storage
     *
     * @param object | json $job
     * @param array $context
     * @return void
     */
    public function sendMail(
        $job,
        $context
    ) {
        $Storage = $context['Storage'];

        /// sleep(10);
        if (is_object($job)) {
            $json = $job->workload();
        } else {
            $json = $job;
        }

        $data = json_decode($json, true);

        @$send_via = strtoupper(trim((string) $data['send_via']));
        @$smtp = json_decode($data['smtp_json'], true);
        @$graphql = json_decode($data['graphql_json'], true);
        @$from = Util::emailsToArray($data['from']);
        @$sender = Util::emailToArray($data['sender']); // Must be single email. Personal name is allowed
        @$return_path = Util::validateEmail($data['return_path']); // Must be single email. Personal name not allowed
        @$subject = trim($data['subject']);
        @$body = $data['body'];
        @$to = Util::emailsToArray($data['to']);
        @$reply_to = Util::emailsToArray($data['reply_to']);
        @$cc = Util::emailsToArray($data['cc']);
        @$bcc = Util::emailsToArray($data['bcc']);

        // Max 6 attachments
        $attachments = [];
        for ($i = 1; $i <= 6; $i++) {
            @$attachments[$i] = json_decode($data['attachment_' . $i . '_json'], true);
        }

        $sent_status = 'Not Sent'; // Default 'Not Sent' it will be override when 'Sent'
        $exception_code = '';
        $exception_message = '';
        $exception_json = '';

        try {
            if (!empty($from)) {
            } else {
                throw new \Exception('From email could not be empty', 500);
            }

            if (!empty($subject)) {
            } else {
                throw new \Exception('Subject could not be empty', 500);
            }

            if (!empty($body)) {
            } else {
                throw new \Exception('Body could not be empty', 500);
            }

            if (!empty($to)) {
            } else {
                throw new \Exception('To email could not be empty', 500);
            }

            if ($send_via === 'GRAPHQL') {
                if (empty($graphql)) {
                    throw new \Exception('GRAPHQL details could not be empty for GRAPHQL send mode', 500);
                }

                $this->sendViaMicrosoftGraph(
                    $graphql,
                    $from,
                    $sender,
                    $subject,
                    $body,
                    $to,
                    $reply_to,
                    $cc,
                    $bcc,
                    $attachments
                );
                $sent = true;
            } else {
                if (empty($smtp)) {
                    $transport = new SendmailTransport();
                }

                if (!empty($smtp)) {
                    $host = !empty($smtp['host']) ? trim($smtp['host']) : 'localhost';
                    $port = !empty($smtp['port']) ? intval($smtp['port']) : 0;
                    $encryption = !empty($smtp['encryption']) ? strtolower(trim($smtp['encryption'])) : '';
                    $tls = null;
                    if ($encryption == 'ssl') {
                        $tls = true;
                    }

                    $transport = new EsmtpTransport($host, $port, $tls);

                    if (@$smtp['username']) {
                        $transport->setUsername($smtp['username']);
                    }
                    if (@$smtp['password']) {
                        $transport->setPassword($smtp['password']);
                    }
                }

                $mailer = new Mailer($transport);
                $message = new Email();

                foreach ($this->toAddressList($from) as $address) {
                    $message->addFrom($address);
                }

                if (!empty($sender)) {
                    $sender_list = $this->toAddressList($sender);
                    if (!empty($sender_list)) {
                        $message->sender($sender_list[0]);
                    }
                }

                if (!empty($return_path)) {
                    $message->returnPath($return_path);
                }

                $message->subject($subject);
                $message->html($body);
                $message->text(strip_tags($body));

                foreach ($this->toAddressList($to) as $address) {
                    $message->addTo($address);
                }

                if (!empty($reply_to)) {
                    foreach ($this->toAddressList($reply_to) as $address) {
                        $message->addReplyTo($address);
                    }
                }

                if (!empty($cc)) {
                    foreach ($this->toAddressList($cc) as $address) {
                        $message->addCc($address);
                    }
                }

                if (!empty($bcc)) {
                    foreach ($this->toAddressList($bcc) as $address) {
                        $message->addBcc($address);
                    }
                }

                for ($i = 1; $i <= 6; $i++) {
                    $attachment = $attachments[$i];

                    if (!empty($attachment)) {
                        // Required. Complete path or URL of the file
                        @$path = trim($attachment['path']);

                        // Required. Name of the file when user receive email in inbox
                        @$name = trim($attachment['name']);

                        // Optional. inline | simple Default is 'simple'. Case insensitive
                        @$type = strtolower(trim($attachment['type']));

                        // Required. When type is inline
                        @$content_type = strtolower(trim($attachment['content_type']));

                        if (!$path) {
                            throw new \Exception('Attachment ' . $i . ' `path` could not be empty', 500);
                        }

                        $http = strtolower(substr($path, 0, 7));
                        $https = strtolower(substr($path, 0, 8));
                        if ($http != 'http://' && $https != 'https://' && !@is_file($path)) {
                            throw new \Exception('Attachment ' . $i . ' file must be valid URL or complete file path not found', 500);
                        }

                        if (!$name) {
                            throw new \Exception('Attachment ' . $i . ' name cannot be empty', 500);
                        }

                        // Forcefully inline iCalendar files
                        $pathinfo = pathinfo($path);
                        if (in_array(strtolower(@$pathinfo['extension']), ['ics'])) {
                            $type = 'inline';
                            $content_type = 'text/calendar';
                        }

                        if ($type == 'inline') {
                            $attachment_part = $this->inlineAttachment($path, $name, $content_type);
                        } else {
                            $attachment_part = $this->simpleAttachment($path, $name);
                        }

                        @$message->addPart($attachment_part);
                    }
                }

                $mailer->send($message);
                $sent = true;
            }

            if ($sent) {
                $sent_status = 'Sent';
            }
        } catch (\Exception $e) {
            $exception_code = $e->getCode();
            $exception_message = $e->getMessage();

            $response = [];
            $response['exception_class'] = get_class($e);
            $response['exception_details'] = (array) $e;
            $exception_json = json_encode($response, JSON_PRETTY_PRINT);
        }

        // Insert log into database
        $sent_at = date('Y-m-d H:i:s');
        @$Storage->insertSentLog(
            $data['job_id'],
            $data['retry_number'],
            $data['smtp_json'],
            $data['from'],
            $data['sender'],
            $data['return_path'],
            $data['subject'],
            $data['body'],
            $data['to'],
            $data['reply_to'],
            $data['cc'],
            $data['bcc'],
            $data['attachment_1_json'],
            $data['attachment_2_json'],
            $data['attachment_3_json'],
            $data['attachment_4_json'],
            $data['attachment_5_json'],
            $data['attachment_6_json'],
            $sent_at,
            $sent_status,
            $exception_code,
            $exception_message,
            $exception_json,
            $data['graphql_json'],
            $send_via
        );

        // Update job stats details by job_id only when it is not notify email
        if (@$data['notify'] !== 'Yes') {
            $Storage->updateJobStatsById(
                $data['job_id'],
                $data['retry_number'],
                $sent_status,
                $sent_at
            );
        }
    }
}
