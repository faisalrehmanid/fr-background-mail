<?php

namespace FR\BackgroundMail\Storage\Oracle;

use FR\Db\DbInterface;
use FR\BackgroundMail\Storage\StorageInterface;

class Storage implements StorageInterface
{
    /**
     * @var object FR\Db\DbInterface
     */
    protected $DB;

    /**
     * Jobs table name Like: schema.table_name
     *
     * @var string
     */
    protected $jobs_table;

    /**
     * Sent log table name Like: schema.table_name
     *
     * @var string
     */
    protected $sent_log_table;

    /**
     * Templates table name Like: schema.table_name
     *
     * @var string
     */
    protected $templates_table;

    /**
     * @param object FR\Db\DbInterface $DB
     * @param string $jobs_table Like: schema.table_name
     * @param string $sent_log_table Like: schema.table_name
     * @param string $templates_table Like: schema.table_name
     * @throws \Exception `jobs_table` cannot be empty and must be string
     * @throws \Exception `sent_log_table` cannot be empty and must be string
     * @throws \Exception `templates_table` cannot be empty and must be string
     */
    public function __construct(
        DBInterface $DB,
        $jobs_table,
        $sent_log_table,
        $templates_table
    ) {
        if (!$jobs_table ||
            !is_string($jobs_table)) {
            throw new \Exception('`jobs_table` cannot be empty and must be string');
        }

        $parts = explode('.', $jobs_table);
        if (count($parts) != 2) {
            throw new \Exception('`jobs_table` name format must be like: schema.table_name');
        }

        if (!$sent_log_table ||
            !is_string($sent_log_table)) {
            throw new \Exception('`sent_log_table` cannot be empty and must be string');
        }

        $parts = explode('.', $sent_log_table);
        if (count($parts) != 2) {
            throw new \Exception('`sent_log_table` name format must be like: schema.table_name');
        }

        if (!$templates_table ||
            !is_string($templates_table)) {
            throw new \Exception('`templates_table` cannot be empty and must be string');
        }

        $parts = explode('.', $templates_table);
        if (count($parts) != 2) {
            throw new \Exception('`templates_table` name format must be like: schema.table_name');
        }

        $this->DB = $DB;
        $this->jobs_table = strtoupper($jobs_table);
        $this->sent_log_table = strtoupper($sent_log_table);
        $this->templates_table = strtoupper($templates_table);
    }

    /**
     * Return SQL script of database structure
     *
     * @return string
     */
    public function getDBStructure()
    {
        $parts = explode('.', $this->jobs_table);
        @$jobs_table_name = $parts[1];

        $parts = explode('.', $this->sent_log_table);
        @$sent_log_table_name = $parts[1];

        $parts = explode('.', $this->templates_table);
        @$templates_table_name = $parts[1];

        $script = ' CREATE TABLE ' . $this->jobs_table . '
        (
            JOB_ID                      RAW(32)                NOT NULL,
            JOB_STATUS                  VARCHAR2(20 CHAR)      NOT NULL,
            JOB_TOTAL_COUNT             NUMBER                 NOT NULL,
            JOB_EXECUTED_COUNT          NUMBER                 NOT NULL,
            JOB_SENT_COUNT              NUMBER                 NOT NULL,
            JOB_NOT_SENT_COUNT          NUMBER                 NOT NULL,
            JOB_CANCELED_COUNT          NUMBER                 NOT NULL,
            JOB_PERCENT_COMPLETED       VARCHAR2(5 CHAR)       NOT NULL,
            JOB_TIME_SPENT              VARCHAR2(100 CHAR)         NULL,
            JOB_STARTED_AT              DATE                   NOT NULL,
            JOB_ENDED_AT                DATE,
            JOB_CANCELED_AT             DATE,
            JOB_NOTIFY_TO               VARCHAR2(4000 CHAR),
            JOB_RETRY_NUMBER            NUMBER                 DEFAULT 0,
            MAIL_BACKGROUND_WORKER      VARCHAR2(100 CHAR)     NOT NULL
        );
        
        CREATE UNIQUE INDEX ' . substr($this->jobs_table, 0, 27) . '_PK ON ' . $this->jobs_table . ' (JOB_ID);
        ALTER TABLE ' . $this->jobs_table . ' ADD (
          CONSTRAINT ' . substr($jobs_table_name, 0, 27) . '_PK
          PRIMARY KEY (JOB_ID) USING INDEX ' . substr($this->jobs_table, 0, 27) . '_PK
          ENABLE VALIDATE);

        CREATE TABLE ' . $this->sent_log_table . '
        (
            JOB_ID             RAW(32)                    NOT NULL,
            RETRY_NUMBER       NUMBER,
            SMTP_JSON          VARCHAR2(4000 CHAR),
            "FROM"             VARCHAR2(4000 CHAR),
            "SENDER"           VARCHAR2(4000 CHAR),
            RETURN_PATH        VARCHAR2(4000 CHAR),
            "SUBJECT"          VARCHAR2(4000 CHAR),
            "BODY"             CLOB,
            "TO"               VARCHAR2(4000 CHAR),
            REPLY_TO           VARCHAR2(4000 CHAR),
            "CC"               VARCHAR2(4000 CHAR),
            "BCC"              VARCHAR2(4000 CHAR),
            ATTACHMENT_1_JSON  VARCHAR2(4000 CHAR),
            ATTACHMENT_2_JSON  VARCHAR2(4000 CHAR),
            ATTACHMENT_3_JSON  VARCHAR2(4000 CHAR),
            ATTACHMENT_4_JSON  VARCHAR2(4000 CHAR),
            ATTACHMENT_5_JSON  VARCHAR2(4000 CHAR),
            ATTACHMENT_6_JSON  VARCHAR2(4000 CHAR),
            SENT_AT            DATE,
            SENT_STATUS        VARCHAR2(10 CHAR),
            EXCEPTION_CODE     VARCHAR2(10 CHAR),
            EXCEPTION_MESSAGE  VARCHAR2(4000 CHAR),
            EXCEPTION_JSON     CLOB
        );

        ALTER TABLE ' . $this->sent_log_table . ' ADD (
        CONSTRAINT ' . substr($sent_log_table_name, 0, 27) . '_FK 
        FOREIGN KEY (JOB_ID) 
        REFERENCES ' . $this->jobs_table . ' (JOB_ID)
        ON DELETE CASCADE
        ENABLE VALIDATE);  

        CREATE TABLE ' . $this->templates_table . '
        (
            TEMPLATE_CODE         VARCHAR2(50 CHAR)       NOT NULL,
            TEMPLATE_DESCRIPTION  VARCHAR2(100 CHAR)      NOT NULL,
            SMTP_JSON             VARCHAR2(4000 CHAR),
            "FROM"                VARCHAR2(4000 CHAR)     NOT NULL,
            "SUBJECT"             VARCHAR2(4000 CHAR)     NOT NULL,
            "BODY"                CLOB                    NOT NULL,
            REPLY_TO              VARCHAR2(4000 CHAR),
            CC                    VARCHAR2(4000 CHAR),
            BCC                   VARCHAR2(4000 CHAR)
        );

        CREATE UNIQUE INDEX ' . substr($this->templates_table, 0, 27) . '_PK ON ' . $this->templates_table . ' (TEMPLATE_CODE);
        ALTER TABLE ' . $this->templates_table . ' ADD (
        CONSTRAINT ' . substr($templates_table_name, 0, 27) . '_PK
        PRIMARY KEY (TEMPLATE_CODE) USING INDEX ' . substr($this->templates_table, 0, 27) . '_PK
        ENABLE VALIDATE); ';

        $script .= ' 
          -- Insert default email templates for notifications 

          INSERT  INTO ' . $this->templates_table . "(TEMPLATE_CODE, TEMPLATE_DESCRIPTION, SMTP_JSON, \"FROM\", \"SUBJECT\", \"BODY\", REPLY_TO, CC, BCC)
            VALUES ('job_started_template','When background job started','smtp-json','from@test.com','Background Mail Job Started','<p>Dear Concern,</p>\r\n\r\n<p>Your background mail job has been Started. Click on the link below to see updated sent log:</p>\r\n\r\n<p> >> <a href=\"___JOB_DETAILS_URL___\">Click Here To View Job Details</a></p>\r\n\r\n<p>Job summary is given below:</p>\r\n\r\n<table>\r\n	<tr>\r\n		<td>Job ID</td>\r\n		<td>___JOB_ID___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Status</td>\r\n		<td>___JOB_STATUS___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Total Count</td>\r\n		<td>___JOB_TOTAL_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Executed Count</td>\r\n		<td>___JOB_EXECUTED_COUNT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Sent Count</td>\r\n		<td>___JOB_SENT_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Not Sent Count</td>\r\n		<td>___JOB_NOT_SENT_COUNT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Percent Completed</td>\r\n		<td>___JOB_PERCENT_COMPLETED___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Time Spent</td>\r\n		<td>___JOB_TIME_SPENT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Started At</td>\r\n		<td>___JOB_STARTED_AT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Ended At</td>\r\n		<td>___JOB_ENDED_AT___</td>\r\n	</tr>\r\n</table>\r\n\r\n<p>*** This is an automatically generated email, please do not reply ***</p>\r\n\r\n<p>Thanks!</p>','reply-to','cc','bcc'); 
           
          INSERT  INTO " . $this->templates_table . "(TEMPLATE_CODE, TEMPLATE_DESCRIPTION, SMTP_JSON, \"FROM\", \"SUBJECT\", \"BODY\", REPLY_TO, CC, BCC) 
            VALUES ('job_completed_template','When background job completed','smtp-json','from@test.com','Background Mail Job Completed','<p>Dear Concern,</p>\r\n\r\n<p>Your background mail job has been Completed. Click on the link below to see updated sent log:</p>\r\n\r\n<p> >> <a href=\"___JOB_DETAILS_URL___\">Click Here To View Job Details</a></p>\r\n\r\n<p>Job summary is given below:</p>\r\n\r\n<table>\r\n	<tr>\r\n		<td>Job ID</td>\r\n		<td>___JOB_ID___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Status</td>\r\n		<td>___JOB_STATUS___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Total Count</td>\r\n		<td>___JOB_TOTAL_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Executed Count</td>\r\n		<td>___JOB_EXECUTED_COUNT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Sent Count</td>\r\n		<td>___JOB_SENT_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Not Sent Count</td>\r\n		<td>___JOB_NOT_SENT_COUNT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Percent Completed</td>\r\n		<td>___JOB_PERCENT_COMPLETED___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Time Spent</td>\r\n		<td>___JOB_TIME_SPENT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Started At</td>\r\n		<td>___JOB_STARTED_AT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Ended At</td>\r\n		<td>___JOB_ENDED_AT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Number of Retry</td>\r\n		<td>___JOB_RETRY_NUMBER___</td>\r\n	</tr>\r\n</table>\r\n\r\n<p>*** This is an automatically generated email, please do not reply ***</p>\r\n\r\n<p>Thanks!</p>','reply-to','cc','bcc');
        
          INSERT  INTO " . $this->templates_table . "(TEMPLATE_CODE, TEMPLATE_DESCRIPTION, SMTP_JSON, \"FROM\", \"SUBJECT\", \"BODY\", REPLY_TO, CC, BCC)
            VALUES ('job_canceled_template','When background job canceled','smtp-json','from@test.com','Background Mail Job Canceled','<p>Dear Concern,</p>\r\n\r\n<p>Your background mail job has been Canceled. Click on the link below to see updated sent log:</p>\r\n\r\n<p> >> <a href=\"___JOB_DETAILS_URL___\">Click Here To View Job Details</a></p>\r\n\r\n<p>Job summary is given below:</p>\r\n\r\n<table>\r\n	<tr>\r\n		<td>Job ID</td>\r\n		<td>___JOB_ID___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Status</td>\r\n		<td>___JOB_STATUS___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Total Count</td>\r\n		<td>___JOB_TOTAL_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Executed Count</td>\r\n		<td>___JOB_EXECUTED_COUNT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Sent Count</td>\r\n		<td>___JOB_SENT_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Not Sent Count</td>\r\n		<td>___JOB_NOT_SENT_COUNT___</td>\r\n	</tr>\r\n<tr>\r\n		<td>Canceled Count</td>\r\n		<td>___JOB_CANCELED_COUNT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Percent Completed</td>\r\n		<td>___JOB_PERCENT_COMPLETED___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Time Spent</td>\r\n		<td>___JOB_TIME_SPENT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Started At</td>\r\n		<td>___JOB_STARTED_AT___</td>\r\n	</tr>\r\n	<tr>\r\n		<td>Canceled At</td>\r\n		<td>___JOB_CANCELED_AT___</td>\r\n	</tr>\r\n</table>\r\n\r\n<p>*** This is an automatically generated email, please do not reply ***</p>\r\n\r\n<p>Thanks!</p>','reply-to','cc','bcc'); 
          ";

        return $script;
    }

    /**
     * Create database structure if already not created
     *
     * @return bool return true when created otherwise false
     */
    public function createDBStructure()
    {
        $query = ' SELECT OWNER, 
                          TABLE_NAME 
                    FROM ALL_TABLES
                        WHERE UPPER(OWNER || \'.\' || TABLE_NAME)
                                IN (:JOBS_TABLE, :SENT_LOG_TABLE, :TEMPLATES_TABLE) ';
        $values = [
            ':JOBS_TABLE' => str_replace('"', '', strtoupper($this->jobs_table)),
            ':SENT_LOG_TABLE' => str_replace('"', '', strtoupper($this->sent_log_table)),
            ':TEMPLATES_TABLE' => str_replace('"', '', strtoupper($this->templates_table)),
        ];

        $tables = $this->DB->fetchColumn($query, $values);
        if (empty($tables)) {
            $query = $this->getDBStructure();
            $this->DB->importSQL($query);

            return true;
        }

        return false;
    }

    /**
     * Validate storage
     *
     * @return void
     * @throws \Exception Database connection error
     *                    Database tables not found or missing some tables
     */
    public function validateStorage()
    {
        $query = ' SELECT OWNER, 
                          TABLE_NAME 
                    FROM ALL_TABLES
                        WHERE UPPER(OWNER || \'.\' || TABLE_NAME)
                                IN (:JOBS_TABLE, :SENT_LOG_TABLE, :TEMPLATES_TABLE) ';
        $values = [
            ':JOBS_TABLE' => str_replace('"', '', strtoupper($this->jobs_table)),
            ':SENT_LOG_TABLE' => str_replace('"', '', strtoupper($this->sent_log_table)),
            ':TEMPLATES_TABLE' => str_replace('"', '', strtoupper($this->templates_table)),
        ];
        $tables = $this->DB->fetchColumn($query, $values);
        if (count($tables) != 3) {
            throw new \Exception('Database tables not found or missing some tables');
        }
    }

    /**
     * Get job details
     *
     * @param string $job_id
     * @return array
     */
    public function getJobById($job_id)
    {
        $job_id = strtolower($job_id);
        $exp = $this->DB->getExpression();

        $query = ' SELECT 
                        ' . $exp->getUuid('JOB_ID') . ' JOB_ID,
                        JOB_STATUS,
                        JOB_TOTAL_COUNT,
                        JOB_EXECUTED_COUNT,
                        JOB_SENT_COUNT,
                        JOB_NOT_SENT_COUNT,
                        JOB_CANCELED_COUNT,
                        JOB_PERCENT_COMPLETED,
                        JOB_TIME_SPENT,
                        ' . $exp->getDate('JOB_STARTED_AT') . ' JOB_STARTED_AT,
                        ' . $exp->getDate('JOB_ENDED_AT') . ' JOB_ENDED_AT,
                        ' . $exp->getDate('JOB_CANCELED_AT') . ' JOB_CANCELED_AT,
                        JOB_NOTIFY_TO,
                        JOB_RETRY_NUMBER,
                        MAIL_BACKGROUND_WORKER
                    FROM ' . $this->jobs_table . '
                     WHERE ' . $exp->getUuid('JOB_ID') . ' = :JOB_ID ';
        $values = [
            ':JOB_ID' => $job_id
        ];
        $row = $this->DB->fetchRow($query, $values);

        return $row;
    }

    /**
     * Save not sent data for given job id and retry number in given CSV file path
     *
     * @param string $csv_path
     * @param string $job_id
     * @param string $retry_number
     * @param array $retry_exception_codes if given only consider list of exception codes for retry
     * @return bool true if any record found and saved to CSV file otherwise false
     */
    public function saveNotSentDataForJobIdAndRetryNumberInCSV(
        $csv_path,
        $job_id,
        $retry_number,
        array $retry_exception_codes
    ) {
        ini_set('max_execution_time', '0');

        $job_id = strtolower($job_id);
        $exp = $this->DB->getExpression();
        $WHERE = '';
        $values = [];

        $WHERE .= ' AND ' . $exp->getUuid('JOB_ID') . ' = :JOB_ID 
                    AND RETRY_NUMBER = :RETRY_NUMBER
                    AND SENT_STATUS  = :SENT_STATUS ';
        $values = array_merge($values, [
            ':JOB_ID' => $job_id,
            ':RETRY_NUMBER' => (int) $retry_number,
            ':SENT_STATUS' => 'Not Sent'
        ]);

        $IN = $exp->in($retry_exception_codes);
        if ($IN->getFragment()) {
            $WHERE .= ' AND EXCEPTION_CODE ' . $IN->getFragment();
            $values = array_merge($values, $IN->getValues());
        }

        $query = ' SELECT COUNT(JOB_ID) "TOTAL"
                    FROM    ' . $this->sent_log_table . '
                   WHERE   1 = 1 ' . $WHERE;
        $total_records = $this->DB->fetchKey('total', $query, $values);

        if ($total_records > 0) {
            $query = ' SELECT   SMTP_JSON "___SMTP_JSON___",
                            "FROM" "___FROM___",
                            SENDER "___SENDER___",
                            RETURN_PATH "___RETURN_PATH___",
                            "SUBJECT" "___SUBJECT___",
                            "BODY" "___BODY___",
                            "TO" "___TO___",
                            REPLY_TO "___REPLY_TO___",
                            CC "___CC___",
                            BCC "___BCC___",
                            ATTACHMENT_1_JSON "___ATTACHMENT_1_JSON___",
                            ATTACHMENT_2_JSON "___ATTACHMENT_2_JSON___",
                            ATTACHMENT_3_JSON "___ATTACHMENT_3_JSON___",
                            ATTACHMENT_4_JSON "___ATTACHMENT_4_JSON___",
                            ATTACHMENT_5_JSON "___ATTACHMENT_5_JSON___",
                            ATTACHMENT_6_JSON "___ATTACHMENT_6_JSON___"
                FROM    ' . $this->sent_log_table . '
                WHERE   1 = 1 ' . $WHERE;
            $query .= ' ORDER BY SENT_AT ASC ';

            $fp = fopen($csv_path, 'w+');
            $header = [
                '___SMTP_JSON___',
                '___FROM___',
                '___SENDER___',
                '___RETURN_PATH___',
                '___SUBJECT___',
                '___BODY___',
                '___TO___',
                '___REPLY_TO___',
                '___CC___',
                '___BCC___',
                '___ATTACHMENT_1_JSON___',
                '___ATTACHMENT_2_JSON___',
                '___ATTACHMENT_3_JSON___',
                '___ATTACHMENT_4_JSON___',
                '___ATTACHMENT_5_JSON___',
                '___ATTACHMENT_6_JSON___'
            ];
            fputcsv($fp, $header);

            $records_per_page = 1000;
            $total_page_numbers = ceil($total_records / $records_per_page);
            for ($page_number = 1; $page_number <= $total_page_numbers; $page_number++) {
                $chunk = $this->DB->fetchChunk(
                    $query,
                    $values,
                    $page_number,
                    $records_per_page
                );

                foreach ($chunk as $row) {
                    unset($row['meta_rownum']);
                    $row['___body___'] = (is_object($row['___body___'])) ? $row['___body___']->load() : $row['___body___'];

                    fputcsv($fp, $row);
                }
            }
            fclose($fp);

            return true;
        }

        return false;
    }

    /**
     * Get template details by code
     *
     * @param string $template_code
     * @return array
     */
    public function getTemplateByCode($template_code)
    {
        $query = ' SELECT 
                        SMTP_JSON,
                        "FROM",
                        "SUBJECT",
                        "BODY",
                        REPLY_TO,
                        CC,
                        BCC
                    FROM ' . $this->templates_table . '
                     WHERE LOWER(TEMPLATE_CODE) = LOWER(:TEMPLATE_CODE) ';
        $values = [
            ':TEMPLATE_CODE' => $template_code
        ];
        $row = $this->DB->fetchRow($query, $values);

        if (!empty($row)) {
            if (is_object($row['body'])) {
                $row['body'] = $row['body']->load();
            }
        }

        return $row;
    }

    /**
     * Insert job details
     *
     * @param string $job_id
     * @param string $job_status
     * @param int $job_total_count
     * @param int $job_executed_count
     * @param int $job_sent_count
     * @param int $job_not_sent_count
     * @param int $job_canceled_count
     * @param string $job_percent_completed
     * @param string $job_time_spent
     * @param string $job_started_at Format Y-m-d H:i:s
     * @param string $job_notify_to
     * @param string $mail_background_worker Gearman background worker id
     * @return void
     */
    public function insertJob(
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
        $job_notify_to,
        $mail_background_worker
    ) {
        $exp = $this->DB->getExpression();

        $data = [];
        $data['JOB_ID'] = $exp->setUuid($job_id);
        $data['JOB_STATUS'] = substr(trim($job_status), 0, 20);
        $data['JOB_TOTAL_COUNT'] = (int) trim($job_total_count);
        $data['JOB_EXECUTED_COUNT'] = (int) trim($job_executed_count);
        $data['JOB_SENT_COUNT'] = (int) trim($job_sent_count);
        $data['JOB_NOT_SENT_COUNT'] = (int) trim($job_not_sent_count);
        $data['JOB_CANCELED_COUNT'] = (int) trim($job_canceled_count);
        $data['JOB_PERCENT_COMPLETED'] = substr(trim($job_percent_completed), 0, 5);
        $data['JOB_TIME_SPENT'] = substr(trim($job_time_spent), 0, 100);
        $data['JOB_STARTED_AT'] = $exp->setDate($job_started_at);
        $data['JOB_NOTIFY_TO'] = substr(trim($job_notify_to), 0, 4000);
        $data['MAIL_BACKGROUND_WORKER'] = substr(trim($mail_background_worker), 0, 100);

        $this->DB->insert($this->jobs_table, $data);
    }

    /**
     * Insert sent log
     *
     * @param string $job_id
     * @param int    $retry_number
     * @param string $smtp_json
     * @param string $from
     * @param string $sender
     * @param string $return_path
     * @param string $subject
     * @param string $body
     * @param string $to
     * @param string $reply_to
     * @param string $cc
     * @param string $bcc
     * @param string $attachment_1_json
     * @param string $attachment_2_json
     * @param string $attachment_3_json
     * @param string $attachment_4_json
     * @param string $attachment_5_json
     * @param string $attachment_6_json
     * @param string $sent_at Format Y-m-d H:i:s
     * @param string $sent_status
     * @param string $exception_code
     * @param string $exception_message
     * @param string $exception_json
     * @return void
     */
    public function insertSentLog(
        $job_id,
        $retry_number,
        $smtp_json,
        $from,
        $sender,
        $return_path,
        $subject,
        $body,
        $to,
        $reply_to,
        $cc,
        $bcc,
        $attachment_1_json,
        $attachment_2_json,
        $attachment_3_json,
        $attachment_4_json,
        $attachment_5_json,
        $attachment_6_json,
        $sent_at,
        $sent_status,
        $exception_code,
        $exception_message,
        $exception_json
    ) {
        $exp = $this->DB->getExpression();

        $data = [];
        $data['JOB_ID'] = $exp->setUuid($job_id);
        $data['RETRY_NUMBER'] = (int) trim($retry_number);
        $data['SMTP_JSON'] = substr(trim($smtp_json), 0, 4000);
        $data['FROM'] = substr(trim($from), 0, 4000);
        $data['SENDER'] = substr(trim($sender), 0, 4000);
        $data['RETURN_PATH'] = substr(trim($return_path), 0, 4000);
        $data['SUBJECT'] = substr(trim($subject), 0, 4000);
        $data['BODY'] = $body;
        $data['TO'] = substr(trim($to), 0, 4000);
        $data['REPLY_TO'] = substr(trim($reply_to), 0, 4000);
        $data['CC'] = substr(trim($cc), 0, 4000);
        $data['BCC'] = substr(trim($bcc), 0, 4000);
        $data['ATTACHMENT_1_JSON'] = substr(trim($attachment_1_json), 0, 4000);
        $data['ATTACHMENT_2_JSON'] = substr(trim($attachment_2_json), 0, 4000);
        $data['ATTACHMENT_3_JSON'] = substr(trim($attachment_3_json), 0, 4000);
        $data['ATTACHMENT_4_JSON'] = substr(trim($attachment_4_json), 0, 4000);
        $data['ATTACHMENT_5_JSON'] = substr(trim($attachment_5_json), 0, 4000);
        $data['ATTACHMENT_6_JSON'] = substr(trim($attachment_6_json), 0, 4000);
        $data['SENT_AT'] = $exp->setDate($sent_at);
        $data['SENT_STATUS'] = substr(trim($sent_status), 0, 10);
        $data['EXCEPTION_CODE'] = substr(trim($exception_code), 0, 10);
        $data['EXCEPTION_MESSAGE'] = substr(trim($exception_message), 0, 4000);
        $data['EXCEPTION_JSON'] = $exception_json;

        $this->DB->insert($this->sent_log_table, $data);
    }

    /**
     * Update job stats details by job_id
     *
     * @param string $job_id
     * @param int $retry_number
     * @param string $sent_status 'Sent' | 'Not Sent'
     * @param string $sent_at Format Y-m-d H:i:s
     * @return void
     */
    public function updateJobStatsById($job_id, $retry_number, $sent_status, $sent_at)
    {
        $exp = $this->DB->getExpression();

        $query = ' UPDATE ' . $this->jobs_table . " SET 
                            JOB_EXECUTED_COUNT = CASE 
                            WHEN JOB_TOTAL_COUNT > JOB_EXECUTED_COUNT 
                                THEN (JOB_EXECUTED_COUNT + 1) 
                            ELSE JOB_EXECUTED_COUNT 
                        END,
                    JOB_SENT_COUNT = CASE 
                            WHEN JOB_TOTAL_COUNT > JOB_SENT_COUNT AND :SENT_STATUS = 'Sent'
                                THEN (JOB_SENT_COUNT + 1) 
                            ELSE JOB_SENT_COUNT 
                        END,
                    JOB_NOT_SENT_COUNT = CASE 
                            WHEN JOB_TOTAL_COUNT > JOB_NOT_SENT_COUNT AND :SENT_STATUS = 'Not Sent'
                                THEN (JOB_NOT_SENT_COUNT + 1) 
                            ELSE JOB_NOT_SENT_COUNT 
                        END,		
                    JOB_PERCENT_COMPLETED = CASE 
                            WHEN JOB_TOTAL_COUNT >= (JOB_EXECUTED_COUNT + 1) 
                                THEN CONCAT(ROUND((((JOB_EXECUTED_COUNT + 1) / JOB_TOTAL_COUNT) * 100)),'%')
                            ELSE JOB_PERCENT_COMPLETED
                        END,
                    JOB_STATUS = CASE
                            WHEN JOB_TOTAL_COUNT <= (JOB_EXECUTED_COUNT + 1) THEN 'Completed'
                            WHEN (JOB_EXECUTED_COUNT + 1) > 0 THEN 'Processing'
                            ELSE 'Started'
                        END,
                    JOB_ENDED_AT = CASE 
                            WHEN JOB_TOTAL_COUNT = (JOB_EXECUTED_COUNT + 1) THEN TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS')
                            ELSE JOB_ENDED_AT
                        END,
                    JOB_TIME_SPENT = TRIM(BOTH FROM 
                                        CASE WHEN TRUNC(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - ADD_MONTHS( JOB_STARTED_AT, MONTHS_BETWEEN(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS'), JOB_STARTED_AT))) = 1 THEN 
                                            TRUNC(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - ADD_MONTHS( JOB_STARTED_AT, MONTHS_BETWEEN(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS'), JOB_STARTED_AT))) || ' Day ' 
                                            WHEN TRUNC(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - ADD_MONTHS( JOB_STARTED_AT, MONTHS_BETWEEN(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS'), JOB_STARTED_AT))) > 1 THEN 
                                            TRUNC(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - ADD_MONTHS( JOB_STARTED_AT, MONTHS_BETWEEN(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS'), JOB_STARTED_AT))) || ' Days '
                                        ELSE
                                            ''
                                        END || 
                                        CASE WHEN TRUNC(24 * MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)) = 1 THEN 
                                            TRUNC(24 * MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)) || ' Hour '
                                            WHEN TRUNC(24 * MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)) > 1 THEN 
                                            TRUNC(24 * MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)) || ' Hours ' 
                                        END ||
                                        CASE WHEN TRUNC( MOD (MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60 ) = 1 THEN 
                                            TRUNC( MOD (MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60 ) || ' Minute '
                                            WHEN TRUNC( MOD (MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60 ) > 1 THEN 
                                            TRUNC( MOD (MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60 ) || ' Minutes '
                                        END || 
                                        CASE WHEN ROUND(MOD(MOD(MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60,1)*60) <= 1 THEN
                                            ROUND(MOD(MOD(MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60,1)*60) || ' Second '
                                            WHEN ROUND(MOD(MOD(MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60,1)*60) > 1 THEN
                                            ROUND(MOD(MOD(MOD(TO_DATE(:CURRENT_TIME, 'YYYY-MM-DD HH24:MI:SS') - JOB_STARTED_AT,1)*24,1)*60,1)*60) || ' Seconds '
                                            END
                                    ),
                    JOB_RETRY_NUMBER = :RETRY_NUMBER
                    WHERE " . $exp->getUuid('JOB_ID') . ' = :JOB_ID ';
        $values = [
            ':JOB_ID' => $job_id,
            ':RETRY_NUMBER' => $retry_number,
            ':SENT_STATUS' => $sent_status,
            ':CURRENT_TIME' => $sent_at,
        ];

        $this->DB->update($query, $values);
    }

    /**
     * Update job status canceled by job_id
     *
     * @param string $job_id
     * @param string $job_canceled_count
     * @return void
     */
    public function updateCanceledStatus($job_id, $job_canceled_count)
    {
        $job_id = strtolower($job_id);
        $exp = $this->DB->getExpression();

        $date = $exp->setDate(date('Y-m-d H:i:s'));
        $query = ' UPDATE ' . $this->jobs_table . ' SET
                    JOB_STATUS = :JOB_STATUS,
                    JOB_CANCELED_COUNT = :JOB_CANCELED_COUNT,
                    JOB_CANCELED_AT  = ' . $date->getFragment() .
            ' WHERE 
                       ' . $exp->getUuid('JOB_ID') . ' = :JOB_ID ';
        $values = [
            ':JOB_STATUS' => 'Canceled',
            ':JOB_CANCELED_COUNT' => $job_canceled_count,
            ':JOB_ID' => $job_id
        ];
        $values = array_merge($values, $date->getValues());

        $this->DB->update($query, $values);
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
        // Validate $upto datetime format: Y-m-d H:i:s
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $upto);
        if (($date && $date->format('Y-m-d H:i:s') == $upto) == false) {
            throw new \Exception('Invalid datetime format it must be: `Y-m-d H:i:s`');
        }

        $exp = $this->DB->getExpression();

        $query = ' DELETE FROM ' . $this->jobs_table . '
                    WHERE   ' . $exp->getDate('JOB_STARTED_AT') . ' <= :JOB_STARTED_AT ';
        $values = [
            ':JOB_STARTED_AT' => $upto
        ];

        $this->DB->delete($query, $values);
    }
}
