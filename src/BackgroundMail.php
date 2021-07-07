<?php

namespace FR\BackgroundMail;

use Exception;
use FR\BackgroundMail\Storage\StorageFactory;
use FR\BackgroundMail\Helper\Util;

/**
 * @author Faisal Rehman <faisalrehmanid@hotmail.com>
 *
 * This class provide background mass mail implementation
 *
 * How to use this class?
 * Check examples folder given on root
 *
 */
class BackgroundMail
{
    /**
     * Configurations
     *
     * @var array
     */
    protected $config;

    /**
     * Configurations
     *
     * NOTE: Same $config will be inject to the PHP script that will run specific worker in
     * this case Execute.php will get this same $config
     *
     * @param array $config = [
     *  'logs_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/error-logs-gearman',
     *  'recipients_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/recipients',
     *  'autoload_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/vendor/autoload.php',
     *  'execute_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/src/Execute.php',
     *  'job_details_url' => 'https:/testurl.com/job-details/{job_id}', // Optional
     *  'timezone' => 'Asia/Karachi',
     *  'number_of_retry_for_send_mail' => 10,
     *  'number_of_send_mail_workers_for_background_job' => 2,
     *  'gearman' => [
     *      'client' => [
     *          'servers' => '127.0.0.1:4730' // Multiple servers can be separate by a comma
     *      ],
     *      'worker' => [
     *          'servers' => '127.0.0.1:4730' // Multiple servers can be separate by a comma
     *      ]
     *  ],
     *  'storage' => [
     *      'db' => [    // @see \FR\Db\DbFactory::init() for MySQL
     *          'driver' => 'pdo_mysql',
     *          'hostname' => 'localhost',
     *          'port' => '3306',
     *          'username' => 'root',
     *          'password' => '',
     *          'database' => 'test_fr_db_mysql',
     *          'charset' => 'utf8mb4'
     *      ],
     *   // 'db' => [    // @see \FR\Db\DbFactory::init() for Oracle
     *   //    'driver' => 'oci8',
     *   //    'connection' => 'ERPDEVDB',
     *   //    'username' => 'MASS',
     *   //    'password' => 'MASS',
     *   //    'character_set' => 'AL32UTF8'
     *   // ],
     *      'jobs_table'      => 'test_fr_db_mysql.background_mail_jobs',
     *      'sent_log_table'  => 'test_fr_db_mysql.background_mail_job_sent_log',
     *      'templates_table' => 'test_fr_db_mysql.background_mail_job_templates'
     *  ],
     *  'oracle_home' => '/home/oracle/app/oracle/product/19.0.0/client_1', // Only for oracle
     *  'ld_library_path' => '/home/oracle/app/oracle/product/19.0.0/client_1/lib', // Only for oracle
     *
     *  // Use `which` command to check complete path like `which php` and in this case /bin/php
     *  'cmd' => [
     *      'php' => '/bin/php',
     *      'pkill' => '/bin/pkill',
     *      'gearadmin' => '/bin/gearadmin'
     *  ],
     *
     *  // Remove stuck workers from memory which are older than given days
     *  'remove_stucked_workers_after_days' => 5,
     *
     *  // List of response codes need to retry when email 'Not Sent'. If empty array is given
     *  // it will retry to send all 'Not Sent' emails but if codes are specified it will only
     *  // consider to retry those `Not Sent` emails having that response code.
     *  'retry_exception_codes' => [
     *   432,
     *  ]
     * ];
     */
    public function __construct(array $config)
    {
        // Where to log error and std output files
        @$logs_path = $config['logs_path'];

        // Writeable base path of recipients CSV file
        @$recipients_path = $config['recipients_path'];

        // File path of autoload.php
        @$autoload_path = $config['autoload_path'];

        // PHP script to run specific worker based on worker_id
        @$execute_path = $config['execute_path'];

        // Timezone setting
        @$timezone = $config['timezone'];
        // Set default timezone
        date_default_timezone_set($config['timezone']);

        // Number of retry when email 'Not Sent'. Could be 0 if don't want to retry
        @$number_of_retry_for_send_mail = $config['number_of_retry_for_send_mail'];

        // Number of SendMailWorker assign to each background job. Value must be atleast 1
        @$number_of_send_mail_workers_for_background_job = $config['number_of_send_mail_workers_for_background_job'];

        // Gearman config
        @$gearman = $config['gearman'];

        // Storage config where to store sent log
        @$storage = $config['storage'];

        // Optional: Used only when using Oracle storage
        @$oracle_home = $config['oracle_home'];

        // Optional: Used only when using Oracle storage
        @$ld_library_path = $config['ld_library_path'];

        // List of all cmd commands used in workers
        @$cmd = $config['cmd'];

        // Remove stuck workers from memory which are older than given days
        @$remove_stucked_workers_after_days = $config['remove_stucked_workers_after_days'];

        // List of response codes need to retry when email 'Not Sent'
        @$retry_exception_codes = $config['retry_exception_codes'];

        // Validate logs_path
        if (!$logs_path ||
            !is_string($logs_path)) {
            throw new \Exception('`logs_path` cannot be empty and must be string');
        }
        // Logs path must be valid directory path
        if (!is_dir($logs_path)) {
            throw new \Exception('Directory not found at: `' . $logs_path . '`');
        }
        // Logs path must be writeable
        $code = substr(sprintf('%o', fileperms($logs_path)), -4);
        if (!in_array($code, ['0777', '0775'])) {
            throw new \Exception('`' . $logs_path . '` dir permissions must be 777 or 775 for all files and directories recursively');
        }

        // Validate recipients_path
        if (!$recipients_path ||
            !is_string($recipients_path)) {
            throw new \Exception('`recipients_path` cannot be empty and must be string');
        }
        // Recipients path must be valid directory path
        if (!is_dir($recipients_path)) {
            throw new \Exception('Directory not found at: `' . $recipients_path . '`');
        }
        // Recipients path must be writeable
        $code = substr(sprintf('%o', fileperms($recipients_path)), -4);
        if (!in_array($code, ['0777', '0775'])) {
            throw new \Exception('`' . $recipients_path . '` dir permissions must be 777 or 775 for all files and directories recursively');
        }

        // Validate autoload_path
        if (!$autoload_path ||
            !is_string($autoload_path)) {
            throw new \Exception('`autoload_path` cannot be empty and must be string');
        }
        //  autoload_path must be valid file path
        if (!is_file($autoload_path)) {
            throw new \Exception('File not found at: `' . $autoload_path . '`');
        }

        // Validate execute_path
        if (!$execute_path ||
            !is_string($execute_path)) {
            throw new \Exception('`execute_path` cannot be empty and must be string');
        }
        // Execute path must be valid PHP file
        if (!is_file($execute_path)) {
            throw new \Exception('File not found at: `' . $execute_path . '`');
        }

        // Validate timezone
        if (!$timezone ||
            !is_string($timezone)) {
            throw new \Exception('`timezone` cannot be empty and must be string');
        }

        // Validate number_of_retry_for_send_mail
        if (!is_int($number_of_retry_for_send_mail) ||
            $number_of_retry_for_send_mail < 0) {
            throw new \Exception('`number_of_retry_for_send_mail` cannot be empty and must be positive int');
        }

        // Validate number_of_send_mail_workers_for_background_job
        if (!is_int($number_of_send_mail_workers_for_background_job) ||
            $number_of_send_mail_workers_for_background_job < 1) {
            throw new \Exception('`number_of_send_mail_workers_for_background_job` cannot be empty and must be int and value must be atleast 1');
        }

        // Validate gearman
        if (empty($gearman) ||
            !is_array($gearman)) {
            throw new \Exception('`gearman` cannot be empty and must be array');
        }

        if (empty($gearman['client']) ||
            !is_array($gearman['client'])) {
            throw new \Exception('`gearman` must have `client` key and must be array');
        }

        if (empty($gearman['worker']) ||
            !is_array($gearman['worker'])) {
            throw new \Exception('`gearman` must have `worker` key and must be array');
        }

        if (!$gearman['client']['servers'] ||
            !is_string($gearman['client']['servers'])) {
            throw new \Exception('`gearman[`client`]` must have key `servers` with string value');
        }

        if (!$gearman['worker']['servers'] ||
            !is_string($gearman['worker']['servers'])) {
            throw new \Exception('`gearman[`worker`]` must have key `servers` with string value');
        }

        // Validate storage
        if (empty($storage) ||
            !is_array($storage)) {
            throw new \Exception('`storage` cannot be empty and must be array');
        }

        if (!$storage['db'] ||
            !is_array($storage['db'])) {
            throw new \Exception('`storage` must have key `db` with array as value');
        }

        if (!$storage['jobs_table'] ||
            !is_string($storage['jobs_table'])) {
            throw new \Exception('`storage` must have key `jobs_table` with string value');
        }

        $parts = explode('.', $storage['jobs_table']);
        if (count($parts) != 2) {
            throw new \Exception('`jobs_table` name format must be like: schema.table_name');
        }

        if (!$storage['sent_log_table'] ||
            !is_string($storage['sent_log_table'])) {
            throw new \Exception('`storage` must have key `sent_log_table` with string value');
        }

        $parts = explode('.', $storage['sent_log_table']);
        if (count($parts) != 2) {
            throw new \Exception('`sent_log_table` name format must be like: schema.table_name');
        }

        if (!$storage['templates_table'] ||
            !is_string($storage['templates_table'])) {
            throw new \Exception('`storage` must have key `templates_table` with string value');
        }

        $parts = explode('.', $storage['templates_table']);
        if (count($parts) != 2) {
            throw new \Exception('`templates_table` name format must be like: schema.table_name');
        }

        // Validate oracle_home
        if ($oracle_home &&
            !is_string($oracle_home)) {
            throw new \Exception('`oracle_home` must be string');
        }

        // Validate ld_library_path
        if ($ld_library_path &&
            !is_string($ld_library_path)) {
            throw new \Exception('`ld_library_path` must be string');
        }

        // Validate cmd
        if (empty($cmd) ||
            !is_array($cmd)) {
            throw new \Exception('`cmd` cannot be empty and must be array');
        }
        if (!@$cmd['php'] ||
            !is_string(@$cmd['php'])) {
            throw new \Exception('`php` command required in cmd key. Must be string');
        }
        if (!@$cmd['pkill'] ||
            !is_string(@$cmd['pkill'])) {
            throw new \Exception('`pkill` command required in cmd key. Must be string');
        }
        if (!@$cmd['gearadmin'] ||
            !is_string(@$cmd['gearadmin'])) {
            throw new \Exception('`gearadmin` command required in cmd key. Must be string');
        }

        // Validate remove_stucked_workers_after_days
        if (!is_int($remove_stucked_workers_after_days) ||
            $remove_stucked_workers_after_days < 0) {
            throw new \Exception('`remove_stucked_workers_after_days` cannot be empty and must be positive int');
        }

        // Validate retry_exception_codes
        if (!is_array($retry_exception_codes)) {
            throw new \Exception('`retry_exception_codes` must be array');
        }

        $this->config = $config;
    }

    /**
     * Get Storage
     *
     * @return object \FR\BackgroundMail\Storage\StorageInterface
     * @throws \Exception Databasae not connected
     */
    public function getStorage()
    {
        $StorageFactory = new StorageFactory();
        $Storage = $StorageFactory->init($this->config['storage']);

        return $Storage;
    }

    /**
     * Create database structure if already not created
     *
     * @return bool return true when created otherwise false
     */
    public function createDBStructure()
    {
        return $this->getStorage()->createDBStructure();
    }

    /**
     * Send mail or mass mail in background
     *
     * @param string $recipients_csv_file_name List of all recipients in CSV file.
     * This file must exists inside of path given in $config['recipients_path']
     *
     * CSV file can have following header row:
     *
     * // SMTP details for authenticated email. If provided it will be used otherwise
     * // system configured send mail will be used to send emails
     * ___SMTP_JSON___, e.g. '{"host":"smtp.office365.com","port":"587","encryption":"tls","username":"from1@email.com","password":"your-smtp-password"}'
     *
     * // From emails can be multiple. But atlease one from email must be required
     * // Email and personal name can be separated by : and multiple emails separated by ;
     * ___FROM___,  e.g. "from1@email.com: From 1; from2@email.com;"
     *
     * // Sender is optional but if from is multiple it must be single email address
     * // indicate who physically sent the message
     * ___SENDER___, e.g. "from1@email.com: From 1"
     *
     * // Return path specifies where bounse notification should be sent.
     * // It is optional but when given it must be single valid email address and not include
     * // a personal name
     * ___RETURN_PATH___, e.g. "from2@email.com"
     *
     * // Email subject required. Can use template variables like ___NAME___
     * ___SUBJECT___, e.g. "Email Subject"
     *
     * // Email message body required. Can use template variables like ___NAME___
     * ___BODY___, e.g. Email Message Body
     *
     * // To email address required. Can be multiple emails separated by ;
     * ___TO___, e.g. "to1@email.com: To1; to2@email.com;",
     *
     * // Reply to is optional. Can be multiple emails separated by ;
     * ___REPLY_TO___, e.g. "reply-to1@email.com: Reply To1; reply-to2@email.com",
     *
     * // CC is optional. Can be multiple emails separated by ;
     * ___CC___, e.g. "cc1@email.com: CC1; cc2@email.com;",
     *
     * // BCC is optional. Can be multiple emails separated by ;
     * ___BCC___, e.g. "bcc1@email.com: BCC1; bcc2@email.com;",
     *
     * // Attachment json must have `path` and `name` keys
     * // `path` required. Could be URL or complete file path on server
     * // `name` required. The name of attachment file when received
     * // NOTE: `allow_url_fopen` must be On in php.ini when using URL in path
     * ___ATTACHMENT_1_JSON___, e.g. '{"path":"https:\/\/test.com\/sample-attachments\/attachment-1.txt","name":"attachment-1.txt"}',
     * ___ATTACHMENT_2_JSON___, e.g. '{"path":"https:\/\/test.com\/sample-attachments\/attachment-2.txt","name":"attachment-2.txt"}',
     * ___ATTACHMENT_3_JSON___, e.g. '{"path":"https:\/\/test.com\/sample-attachments\/attachment-3.txt","name":"attachment-3.txt"}',
     * ___ATTACHMENT_4_JSON___, e.g. '{"path":"https:\/\/test.com\/sample-attachments\/attachment-4.txt","name":"attachment-4.txt"}',
     * ___ATTACHMENT_5_JSON___, e.g. '{"path":"https:\/\/test.com\/sample-attachments\/attachment-5.txt","name":"attachment-5.txt"}',
     * ___ATTACHMENT_6_JSON___, e.g. '{"path":"https:\/\/test.com\/sample-attachments\/attachment-5.txt","name":"attachment-6.txt"}',
     *
     * @param string $notify_to Optional and used to send nofication email
     *      for background job status can be multiple and separated by ;
     *      e.g, user1@test.com: User1; user2@test.com;
     *      Notification email will be sent:
     *           When job has been Started
     *           When job has been Completed
     *           When job has been Canceled
     *
     * @return string $job_id Always return 64 char unique job id
     * @throws \Exception Database connection error
     *                    Database tables not found
     *                    Invalid recipients_csv_file_path CSV file
     *                    Gearman error
     */
    public function send($recipients_csv_file_name, $notify_to = '')
    {
        $config = $this->config;

        // Validate database connection and table structure
        $this->getStorage()->validateStorage();

        // Check file exists
        $recipients_csv_file_path = $config['recipients_path'] . '/' . $recipients_csv_file_name;
        if (!is_file($recipients_csv_file_path)) {
            throw new \Exception('Invalid recipients_csv_file_path CSV file not found at: `' . $recipients_csv_file_path . '`');
        }

        // Unique background worker id show in command line when run: `gearadmin --status`
        $worker_id = 'MailBackgroundWorker-' . date('Y-m-d') . '-' . Util::generateUniqueId(16);
        // Start worker to listen for incomming jobs
        $command = $config['cmd']['php'] . ' ' . $config['execute_path'] . ' ' . $worker_id . ' ' . base64_encode(json_encode($config)) . ' 1>' . $config['logs_path'] . '/SendMassMailBackgroundOutput.log 2>' . $config['logs_path'] . '/SendMassMailBackgroundError.log /dev/null & ';
        exec($command);

        // Gearman client
        $client = new \GearmanClient();
        // Add server
        $client->addServers($config['gearman']['client']['servers']);

        // Generate unique 64 chars job_id
        $job_id = Util::generateUniqueId(64);

        $job = [
            'job_id' => $job_id,
            'notify_to' => $notify_to,
            'recipients_csv_file_name' => $recipients_csv_file_name,
            'retry_number' => 0
        ];
        $job = json_encode($job);

        // Assign background job to that unique worker started above
        $client->doBackground($worker_id, $job);

        // When error return from gearman
        if ($client->returnCode() != GEARMAN_SUCCESS) {
            throw new \Exception('Gearman error');
        }

        return $job_id;
    }

    /**
     * Get job details
     *
     * @param string $job_id
     * @return array
     */
    public function getJobById($job_id)
    {
        return $this->getStorage()->getJobById($job_id);
    }

    /**
     * Cancel background job using job_id
     *
     * @param string $job_id
     * @return void
     * @throws \Exception Invalid job id. job details not found
     * @throws \Exception Job status already completed
     * @throws \Exception Job status already canceled
     */
    public function cancelJob($job_id)
    {
        $config = $this->config;
        $job = $this->getStorage()->getJobById($job_id);

        if (empty($job)) {
            throw new \Exception('Invalid job id. job details not found');
        }

        if ($job['job_status'] == 'Completed') {
            throw new \Exception('Job status already completed');
        }

        if ($job['job_status'] == 'Canceled') {
            throw new \Exception('Job status already canceled');
        }

        // Shutdown running processes and drop gearman functions related to this job id
        Util::shutdownMailBackgroundWorker(
            $config['cmd']['gearadmin'],
            $config['cmd']['pkill'],
            $job['mail_background_worker']
        );

        // Update job status to Canceled
        $job_canceled_count = ((int) $job['job_total_count'] - (int) $job['job_executed_count']);
        $this->getStorage()->updateCanceledStatus($job['job_id'], $job_canceled_count);

        $notify_to = $job['job_notify_to'];
        if (!empty($notify_to)) {
            // Get updated job details
            $job = $this->getStorage()->getJobById($job_id);

            // Prepare notification email body part
            $vars = [];
            $vars['___JOB_DETAILS_URL___'] = str_replace('{job_id}', $job['job_id'], $config['job_details_url']);
            $vars['___JOB_ID___'] = $job['job_id'];
            $vars['___JOB_RETRY_NUMBER___'] = $job['job_retry_number'];
            $vars['___JOB_STATUS___'] = $job['job_status'];
            $vars['___JOB_TOTAL_COUNT___'] = $job['job_total_count'];
            $vars['___JOB_EXECUTED_COUNT___'] = $job['job_executed_count'];
            $vars['___JOB_SENT_COUNT___'] = $job['job_sent_count'];
            $vars['___JOB_NOT_SENT_COUNT___'] = $job['job_not_sent_count'];
            $vars['___JOB_CANCELED_COUNT___'] = $job['job_canceled_count'];
            $vars['___JOB_PERCENT_COMPLETED___'] = $job['job_percent_completed'];
            $vars['___JOB_TIME_SPENT___'] = $job['job_time_spent'];
            $vars['___JOB_STARTED_AT___'] = date('d M Y H:i:s', strtotime($job['job_started_at']));
            $vars['___JOB_CANCELED_AT___'] = date('d M Y H:i:s', strtotime($job['job_canceled_at']));
            $template = $this->getStorage()->getTemplateByCode('job_canceled_template');
            $body = $template['body'];
            foreach ($vars as $key => $value) {
                $body = str_replace($key, $value, $body);
            }
            $subject = $template['subject'];
            foreach ($vars as $key => $value) {
                $subject = str_replace($key, $value, $subject);
            }

            // Start worker to listen for incomming jobs
            $send_mail_worker_id = str_replace('MailBackgroundWorker-', 'SendMailWorker-', $job['mail_background_worker']) . '-Canceled';
            $command = $config['cmd']['php'] . ' ' . $config['execute_path'] . ' ' . $send_mail_worker_id . ' ' . base64_encode(json_encode($config)) . ' 1>' . $config['logs_path'] . '/SendMailOutput.log 2>' . $config['logs_path'] . '/SendMailError.log /dev/null & ';
            exec($command);

            // Create Gearman Client
            $client = new \GearmanClient();
            // Add server to client
            $client->addServers($config['gearman']['client']['servers']);

            // Send notification email to notify_to
            $task = [];
            $task['job_id'] = $job['job_id'];
            $task['retry_number'] = $job['job_retry_number'];
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

            // Assign task to worker
            $client->addTask($send_mail_worker_id, $json);
            $done = $client->runTasks();

            if ($done) {
                // Remove gearman worker process
                $command = $config['cmd']['pkill'] . ' -f ' . $send_mail_worker_id;
                exec($command);

                // Remove gearman worker function
                $command = $config['cmd']['gearadmin'] . ' --drop-function ' . $send_mail_worker_id;
                exec($command);
            }
        }
    }

    /**
     * Delete sent log upto given datetime inclusive
     *
     * @param string $upto Datetime format: Y-m-d H:i:s
     * @return void
     * @throws \Exception Invalid datetime format it must be: `Y-m-d H:i:s`
     */
    public function deleteSentLog($upto)
    {
        $this->getStorage()->deleteSentLog($upto);
    }
}
