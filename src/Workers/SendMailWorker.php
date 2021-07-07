<?php

namespace FR\BackgroundMail\Workers;

use FR\BackgroundMail\Storage\StorageInterface;
use FR\BackgroundMail\Helper\Util;

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
     * Simple file attachment
     *
     * @param string $path Complete path or URL of the file
     * @param string $name Name of the file when user receive email in inbox
     * @return object Swift_Attachment
     */
    private function simpleAttachment($path, $name)
    {
        $Swift_Attachment = \Swift_Attachment::fromPath($path);
        if ($name) {
            $Swift_Attachment->setFilename($name);
        }

        return $Swift_Attachment;
    }

    /**
      * Inline file attachment will be parse by email client
      *
      * @param string $path Complete path or URL of the file
      * @param string $name Name of the file when user receive email in inbox
      * @param string $content_type File content type
      * @return object Swift_Attachment
      */
    private function inlineAttachment($path, $name, $content_type)
    {
        $contents = \file_get_contents($path);
        $Swift_Attachment = (new \Swift_Attachment())
                        ->setFilename($name)
                        ->setContentType($content_type)
                        ->setBody(trim($contents));

        $Headers = $Swift_Attachment->getHeaders();
        $HeaderContentType = $Headers->get('Content-Type');
        $HeaderContentType->setValue($content_type);
        $HeaderContentType->setParameters([
            'charset' => 'UTF-8',
            'method' => 'REQUEST',
            'name' => $name
        ]);
        $Headers->remove('Content-Disposition');

        return $Swift_Attachment;
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
        &$context
    ) {
        $Storage = $context['Storage'];

        /// sleep(10);
        if (is_object($job)) {
            $json = $job->workload();
        } else {
            $json = $job;
        }

        $data = json_decode($json, true);

        @$smtp = json_decode($data['smtp_json'], true);
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
            if (empty($smtp)) {
                $SwiftTransport = new \Swift_SendmailTransport();
            }

            if (!empty($smtp)) {
                $SwiftTransport = new \Swift_SmtpTransport();

                if (@$smtp['host']) {
                    $SwiftTransport->setHost($smtp['host']);
                }
                if (@$smtp['port']) {
                    $SwiftTransport->setPort($smtp['port']);
                }
                if (@$smtp['encryption']) {
                    $SwiftTransport->setEncryption($smtp['encryption']);
                }
                if (@$smtp['username']) {
                    $SwiftTransport->setUsername($smtp['username']);
                }
                if (@$smtp['password']) {
                    $SwiftTransport->setPassword($smtp['password']);
                }
            }

            $SwiftMailer = new \Swift_Mailer($SwiftTransport);
            $SwiftMessage = new \Swift_Message();
            $SwiftMessage->setContentType('multipart/alternative');

            if (!empty($from)) {
                $SwiftMessage->setFrom($from);
            } else {
                throw new \Exception('From email could not be empty', 500);
            }

            if (!empty($sender)) {
                $SwiftMessage->setSender($sender);
            }

            if (!empty($return_path)) {
                $SwiftMessage->setReturnPath($return_path);
            }

            if (!empty($subject)) {
                $SwiftMessage->setSubject($subject);
            } else {
                throw new \Exception('Subject could not be empty', 500);
            }

            if (!empty($body)) {
                $SwiftMessage->setBody($body, 'text/html');
                $SwiftMessage->addPart(strip_tags($body), 'text/plain');
            } else {
                throw new \Exception('Body could not be empty', 500);
            }

            if (!empty($to)) {
                $SwiftMessage->setTo($to);
            } else {
                throw new \Exception('To email could not be empty', 500);
            }

            if (!empty($reply_to)) {
                $SwiftMessage->setReplyTo($reply_to);
            }

            if (!empty($cc)) {
                $SwiftMessage->setCc($cc);
            }

            if (!empty($bcc)) {
                $SwiftMessage->setBcc($bcc);
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
                        $Swift_Attachment = $this->inlineAttachment($path, $name, $content_type);
                    } else {
                        $Swift_Attachment = $this->simpleAttachment($path, $name);
                    }

                    @$SwiftMessage->attach($Swift_Attachment);
                }
            }

            $sent = $SwiftMailer->send($SwiftMessage);

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
            $exception_json
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
