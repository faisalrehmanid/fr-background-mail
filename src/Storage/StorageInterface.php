<?php

namespace FR\BackgroundMail\Storage;

interface StorageInterface
{
    /**
     * Validate storage
     *
     * @return void
     * @throws \Exception Database connection error
     *                    Database tables not found
     */
    public function validateStorage();

    /**
     * Get job details
     *
     * @param string $job_id
     * @return array
     */
    public function getJobById($job_id);

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
    );

    /**
     * Get template details by code
     *
     * @param string $template_code
     * @return array
     */
    public function getTemplateByCode($template_code);

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
    );

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
    );

    /**
     * Update job stats details by job_id
     *
     * @param string $job_id
     * @param int $retry_number
     * @param string $sent_status 'Sent' | 'Not Sent'
     * @param string $sent_at Format Y-m-d H:i:s
     * @return void
     */
    public function updateJobStatsById($job_id, $retry_number, $sent_status, $sent_at);

    /**
     * Update job status canceled by job_id
     *
     * @param string $job_id
     * @param string $job_canceled_count
     * @return void
     */
    public function updateCanceledStatus($job_id, $job_canceled_count);

    /**
     * Delete sent log upto given datetime inclusive
     *
     * @param string $upto Datetime format: Y-m-d H:i:s
     * @return void
     * @throws \Exception Invalid datetime format it must be: `Y-m-d H:i:s`
     */
    public function deleteSentLog($upto);
}
