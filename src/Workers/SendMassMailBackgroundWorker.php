<?php

namespace FR\BackgroundMail\Workers;

use FR\BackgroundMail\Storage\StorageInterface;
use FR\BackgroundMail\Helper\Util;

class SendMassMailBackgroundWorker
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
     * Unique worker_id
     *
     * @var string
     */
    protected $worker_id;

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
    public function listen($worker_id = 'MailBackgroundWorker')
    {
        // Drop all idle gearman functions that doing nothing
        Util::dropIdleGearmanFunctions($this->config['cmd']['gearadmin']);

        // Remove stuck workers from memory which are older than given days
        Util::removeStuckedWrokers(
            $this->config['cmd']['gearadmin'],
            $this->config['cmd']['pkill'],
            $this->config['remove_stucked_workers_after_days']
        );

        // Gearman servers from config
        // Comma separated servers e.g. 127.0.0.1:4730,127.0.0.1:4731
        $servers = $this->config['gearman']['worker']['servers'];

        $this->worker_id = $worker_id;
        $worker = new \GearmanWorker();
        $worker->addServers($servers);
        $worker->addFunction(
            $worker_id,
            [$this, 'sendMassMailBackground'],
            [
                'Storage' => $this->Storage,
                'config' => $this->config
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
     * Send mail or mass mail in background
     *
     * @param object | json $job [
     *         'job_id'                    => $job_id,                    // 64 Char unique job id
     *         'notify_to'                 => $notify_to,                 // Send notification about job status to this email, can be multiple and separated by ;
     *         'recipients_csv_file_name'  => $recipients_csv_file_name,  // Name of recipients CSV file in $config['recipients_path']
     *         'retry_number'              => 0                           // Initialy it will be 0
     * ]
     * @param array $context [
     *      'config'  => $config,
     *      'Storage' => $Storage
     * ]
     * @return void
     */
    public function sendMassMailBackground($job, &$context)
    {
        $config = $context['config'];
        $Storage = $context['Storage'];
        $send_mass_mail_background_worker_id = $this->worker_id;

        if (is_object($job)) {
            $json = $job->workload();
        } else {
            $json = $job;
        }

        $data = json_decode($json, true);
        $job_id = $data['job_id'];
        $notify_to = $data['notify_to'];
        $recipients_csv_file_name = $data['recipients_csv_file_name'];
        $retry_number = $data['retry_number'];

        $recipients_csv_file_path = $config['recipients_path'] . '/' . $recipients_csv_file_name;
        $recipients_count = Util::countCsvRecords($recipients_csv_file_path);

        if ($recipients_count > 0) {
            // Start number of workers according to the job size
            $number_of_send_mail_workers_for_background_job = $config['number_of_send_mail_workers_for_background_job'];
            if ($recipients_count < $number_of_send_mail_workers_for_background_job) {
                $number_of_send_mail_workers_for_background_job = $recipients_count;
            }

            // Start SendMailWorkers for each background job
            $send_mail_worker_ids = [];
            for ($i = 0; $i < $number_of_send_mail_workers_for_background_job; $i++) {
                // Prepare worker id
                $send_mail_worker = str_replace('MailBackgroundWorker-', 'SendMailWorker-', $send_mass_mail_background_worker_id);

                // Unique worker name
                $send_mail_worker_id = $send_mail_worker . '-' . ($i + 1);

                if ($retry_number > 0) { // Worker name will tell retry number
                    $send_mail_worker_id .= 'Retry-' . $retry_number;
                }

                // This array will used to assign jobs to each worker
                $send_mail_worker_ids[] = $send_mail_worker_id;

                // Start worker to listen for incomming jobs
                $command = $config['cmd']['php'] . ' ' . $config['execute_path'] . ' ' . $send_mail_worker_id . ' ' . base64_encode(json_encode($config)) . ' 1>' . $config['logs_path'] . '/SendMailOutput.log 2>' . $config['logs_path'] . '/SendMailError.log /dev/null & ';
                exec($command);
            }

            // Workers has been started but wait for 1 Sec to assgin jobs to workers
            sleep(1); // Give some time to workers to be stable

            // Create Gearman Client
            $client = new \GearmanClient();
            // Add server to client
            $client->addServers($config['gearman']['client']['servers']);

            // Insert job details when retry_number is 0
            if ($retry_number == 0) {
                $job_status = 'Started';
                $job_total_count = $recipients_count;
                $job_executed_count = 0;
                $job_sent_count = 0;
                $job_not_sent_count = 0;
                $job_canceled_count = 0;
                $job_percent_completed = '0%';
                $job_time_spent = '0 Second';
                $job_started_at = date('Y-m-d H:i:s');
                $Storage->insertJob(
                    $job_id,
                    $job_status,
                    $job_total_count,
                    $job_executed_count,
                    $job_sent_count,
                    $job_not_sent_count,
                    $job_canceled_count,
                    $job_percent_completed,
                    $job_time_spent,
                    $job_started_at,
                    $notify_to,
                    $send_mass_mail_background_worker_id
                );

                // Send notification email that job has been Started
                if (!empty($notify_to)) {
                    // Prepare notification email body part
                    $vars = [];
                    $vars['___JOB_DETAILS_URL___'] = str_replace('{job_id}', $job_id, $config['job_details_url']);
                    $vars['___JOB_ID___'] = $job_id;
                    $vars['___JOB_STATUS___'] = $job_status;
                    $vars['___JOB_TOTAL_COUNT___'] = $job_total_count;
                    $vars['___JOB_EXECUTED_COUNT___'] = $job_executed_count;
                    $vars['___JOB_SENT_COUNT___'] = $job_sent_count;
                    $vars['___JOB_NOT_SENT_COUNT___'] = $job_not_sent_count;
                    $vars['___JOB_PERCENT_COMPLETED___'] = $job_percent_completed;
                    $vars['___JOB_TIME_SPENT___'] = $job_time_spent;
                    $vars['___JOB_STARTED_AT___'] = date('d M Y H:i:s', strtotime($job_started_at));
                    $vars['___JOB_ENDED_AT___'] = 'Unknown';
                    $template = $Storage->getTemplateByCode('job_started_template');
                    $subject = Util::replaceVarValues($vars, $template['subject']);
                    $body = Util::replaceVarValues($vars, $template['body']);

                    // Send notification email to notify_to
                    $task = [];
                    $task['job_id'] = $job_id;
                    $task['retry_number'] = $retry_number;
                    $task['to'] = $notify_to;
                    $task['smtp_json'] = $template['smtp_json'];
                    $task['from'] = $template['from'];
                    $task['subject'] = $subject;
                    $task['body'] = $body;
                    $task['reply_to'] = $template['reply_to'];
                    $task['cc'] = $template['cc'];
                    $task['bcc'] = $template['bcc'];
                    $task['notify'] = 'Yes';
                    $json = json_encode($task);

                    // Assign task to first worker
                    $client->addTask($send_mail_worker_ids[0], $json);
                    $client->runTasks();
                }
            }

            // Send mail to each recipient in CSV
            $index = 0;
            $headers = [];
            $worker = 0;
            if (($handle = fopen($recipients_csv_file_path, 'r')) !== false) {
                while (($row = fgetcsv($handle)) !== false) {
                    if ($index == 0) { // Read header row
                        $headers = $row;
                    } else { // Skip first header row
                        $vars = [];
                        foreach ($row as $k => $v) {
                            $vars[$headers[$k]] = $v;
                        }

                        @$smtp_json = $vars['___SMTP_JSON___'];
                        @$from = $vars['___FROM___'];
                        @$sender = $vars['___SENDER___'];
                        @$return_path = $vars['___RETURN_PATH___'];
                        @$subject = Util::replaceVarValues($vars, $vars['___SUBJECT___']);
                        @$body = Util::replaceVarValues($vars, $vars['___BODY___']);
                        @$to = $vars['___TO___'];
                        @$reply_to = $vars['___REPLY_TO___'];
                        @$cc = $vars['___CC___'];
                        @$bcc = $vars['___BCC___'];
                        @$attachment_1_json = $vars['___ATTACHMENT_1_JSON___'];
                        @$attachment_2_json = $vars['___ATTACHMENT_2_JSON___'];
                        @$attachment_3_json = $vars['___ATTACHMENT_3_JSON___'];
                        @$attachment_4_json = $vars['___ATTACHMENT_4_JSON___'];
                        @$attachment_5_json = $vars['___ATTACHMENT_5_JSON___'];
                        @$attachment_6_json = $vars['___ATTACHMENT_6_JSON___'];

                        $task = [
                            'job_id' => $job_id,
                            'retry_number' => $retry_number,
                            'smtp_json' => $smtp_json,
                            'from' => $from,
                            'sender' => $sender,
                            'return_path' => $return_path,
                            'subject' => $subject,
                            'body' => $body,
                            'to' => $to,
                            'reply_to' => $reply_to,
                            'cc' => $cc,
                            'bcc' => $bcc,
                            'attachment_1_json' => $attachment_1_json,
                            'attachment_2_json' => $attachment_2_json,
                            'attachment_3_json' => $attachment_3_json,
                            'attachment_4_json' => $attachment_4_json,
                            'attachment_5_json' => $attachment_5_json,
                            'attachment_6_json' => $attachment_6_json,
                        ];
                        $json = json_encode($task);

                        // Assign workers to do job
                        $client->addTask($send_mail_worker_ids[$worker], $json);

                        // Check if next worker available in list assign next job
                        if (@$send_mail_worker_ids[$worker + 1]) {
                            $worker++;
                        } else { // Next worker not available in list. Assign it to initial worker
                            $worker = 0;
                        }
                    }
                    $index++;
                }

                fclose($handle);
            }

            // Execute all tasks in parallel
            $done = $client->runTasks();

            // Check if all tasks has been completed
            if ($done) {
                // Fetch job details from database
                $row = $Storage->getJobById($job_id);

                // Send notification email that job has been Completed
                if ($row['job_status'] == 'Completed' && !empty($notify_to)) {
                    // Prepare notification email body part
                    $vars = [];
                    $vars['___JOB_DETAILS_URL___'] = str_replace('{job_id}', $job_id, $config['job_details_url']);
                    $vars['___JOB_ID___'] = $job_id;
                    $vars['___JOB_RETRY_NUMBER___'] = $retry_number;
                    $vars['___JOB_STATUS___'] = $row['job_status'];
                    $vars['___JOB_TOTAL_COUNT___'] = $row['job_total_count'];
                    $vars['___JOB_EXECUTED_COUNT___'] = $row['job_executed_count'];
                    $vars['___JOB_SENT_COUNT___'] = $row['job_sent_count'];
                    $vars['___JOB_NOT_SENT_COUNT___'] = $row['job_not_sent_count'];
                    $vars['___JOB_PERCENT_COMPLETED___'] = $row['job_percent_completed'];
                    $vars['___JOB_TIME_SPENT___'] = $row['job_time_spent'];
                    $vars['___JOB_STARTED_AT___'] = date('d M Y H:i:s', strtotime($row['job_started_at']));
                    $vars['___JOB_ENDED_AT___'] = date('d M Y H:i:s', strtotime($row['job_ended_at']));
                    $template = $Storage->getTemplateByCode('job_completed_template');
                    $subject = Util::replaceVarValues($vars, $template['subject']);
                    $body = Util::replaceVarValues($vars, $template['body']);

                    // Send notification email to notify_to
                    $task = [];
                    $task['job_id'] = $job_id;
                    $task['retry_number'] = $retry_number;
                    $task['to'] = $notify_to;
                    $task['smtp_json'] = $template['smtp_json'];
                    $task['from'] = $template['from'];
                    $task['subject'] = $subject;
                    $task['body'] = $body;
                    $task['reply_to'] = $template['reply_to'];
                    $task['cc'] = $template['cc'];
                    $task['bcc'] = $template['bcc'];
                    $task['notify'] = 'Yes';
                    $json = json_encode($task);

                    // Assign task to first worker
                    $client->addTask($send_mail_worker_ids[0], $json);
                    $client->runTasks();
                }

                // Loop through all workers to stop listening for task
                foreach ($send_mail_worker_ids as $i => $send_mail_worker_id) {
                    // Remove gearman worker process
                    $command = $config['cmd']['pkill'] . ' -f ' . $send_mail_worker_id;
                    exec($command);

                    // Remove gearman worker function
                    $command = $config['cmd']['gearadmin'] . ' --drop-function ' . $send_mail_worker_id;
                    exec($command);
                }

                // Dont retry if number_of_retry_for_send_mail is 0 or undefined
                if (@!$config['number_of_retry_for_send_mail']) {
                    // Send fail will cancel this job from queue
                    $job->sendFail();

                    // Job completed. Remove this worker
                    $command = $config['cmd']['pkill'] . ' -f ' . $send_mass_mail_background_worker_id;
                    exec($command);
                }

                // Dont retry more than number_of_retry_for_send_mail times
                if ($retry_number >= $config['number_of_retry_for_send_mail']) {
                    return true;
                }

                // Now job has been completed. Retry to send 'Not Sent' emails
                // for this job_id and retry_number and consider only retry_exception_codes if given
                $bool = $Storage->saveNotSentDataForJobIdAndRetryNumberInCSV(
                    $recipients_csv_file_path,
                    $job_id,
                    $retry_number,
                    $config['retry_exception_codes']
                );

                // Recipients found for retry
                if ($bool) {
                    $retry_number++;

                    // Retry again
                    $data = [
                        'job_id' => $job_id,
                        'notify_to' => $notify_to,
                        'recipients_csv_file_name' => $recipients_csv_file_name,
                        'retry_number' => $retry_number
                    ];
                    $json = json_encode($data);
                    $this->sendMassMailBackground($json, $context);
                }
            }
        }

        // Send fail will cancel this job from queue
        if (is_object(@$job)) {
            $job->sendFail();
        }

        // Job completed. Remove this worker process
        if (@$send_mass_mail_background_worker_id) {
            $command = $config['cmd']['pkill'] . ' -f ' . $send_mass_mail_background_worker_id;
            exec($command);
        }
    }
}
