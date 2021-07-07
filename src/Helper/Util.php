<?php

namespace FR\BackgroundMail\Helper;

class Util
{
    /**
     * Validate single email address and convert email string to array
     * when email used with name. Email and name separated by :
     *
     * @param string $email e.g. some1@address.com: Name
     * @return string | array e.g. [some1@address.com => Name]
     */
    public static function emailToArray($email)
    {
        $parts = explode(':', $email);
        @$email = strtolower(trim($parts[0])); // Email
        @$name = trim($parts[1]); // Name

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
            if (!empty($name)) {
                return [$email => $name];
            } else {
                return $email;
            }
        }

        return '';
    }

    /**
     * Convert multiple emails string to array
     * Multiple emails separated by ; and each email and name separated by :
     *
     * @param string $emails e.g. some1@address.com; some2@address.com: Name; some3@address.com
     * @return array e.g [  some1@address.com,
     *                      [some2@address.com => Name],
     *                      some3@address.com
     *                   ]
     */
    public static function emailsToArray($emails)
    {
        $array = [];
        $emails = explode(';', trim(trim($emails), ';'));

        foreach ($emails as $k => $email) {
            $email = self::emailToArray($email);
            if (!empty($email)) {
                if (is_array($email)) {
                    $array = array_merge($array, $email);
                } else {
                    $array[] = $email;
                }
            }
        }

        return $array;
    }

    /**
     * Validate single email address
     *
     * @param string $email
     * @return string
     */
    public static function validateEmail($email)
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
            return $email;
        }

        return '';
    }

    /**
     * Replace variables with values
     *
     * @param array $vars
     * @param string $string
     * @return string
    */
    public static function replaceVarValues(array $vars, $string)
    {
        foreach ($vars as $key => $value) {
            $string = str_replace($key, $value, $string);
        }

        return $string;
    }

    /**
     * Count CSV records from given csv file path
     *
     * @param string $csv_file_path
     * @return int Number of records found in CSV minus header row
     */
    public static function countCsvRecords($csv_file_path)
    {
        ini_set('max_execution_time', '0');

        $count = 0;
        if (($handle = fopen($csv_file_path, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $count++;
            }
            fclose($handle);
        }

        // Less header row
        if ($count > 0) {
            $count = ($count - 1);
        }

        return $count;
    }

    /**
     * Generate Unique ID of fixed length
     *
     * @param int $length
     * @return string
     */
    public static function generateUniqueId($length = 32)
    {
        $length = intval($length) / 2;
        if ($length == 0) {
            return '';
        }

        if (function_exists('random_bytes')) {
            $random = random_bytes($length);
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $random = openssl_random_pseudo_bytes($length);
        }

        if ($random !== false && strlen($random) === $length) {
            return  bin2hex($random);
        }

        $unique_id = '';
        $characters = '0123456789abcdef';
        for ($i = 0; $i < ($length * 2); $i++) {
            $unique_id .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $unique_id;
    }

    /**
     * Parse `gearadmin --status` command line output
     *
     * @param string $gearadmin cmd command complete path e.g: /bin/gearadmin
     * @return array of statuses
     */
    public static function parseGearadminStatus($gearadmin)
    {
        // Save command output
        $output = [];
        // Execute command to get all gearman job statuses
        $command = $gearadmin . ' --status';
        exec($command, $output);

        if (!empty($output)) {
            $statuses = []; // Parse each string and save in $statuses array

            // Parse gearadmin --status $output
            foreach ($output as $k => $row) {
                // Flag to identify next column
                $space = 1;

                // Loop through all characters in each row
                for ($i = 0; $i < strlen($row); $i++) {
                    $chr = $row[$i];
                    // Replace any tab char with space
                    if ($chr == "\t") {
                        $chr = ' ';
                    }

                    if (trim($chr) != '') {
                        if ($space == 1) {
                            @$statuses[$k]['function'] .= $chr;
                        } // Function name
                        if ($space == 2) {
                            @$statuses[$k]['queue'] .= $chr;
                        }    // Number of jobs in queue
                        if ($space == 3) {
                            @$statuses[$k]['running'] .= $chr;
                        }  // Number of jobs running
                        if ($space == 4) {
                            @$statuses[$k]['workers'] .= $chr;
                        }  // Number of capable workers
                    } else {
                        $space++;
                    }
                }
            }

            return $statuses;
        }

        return [];
    }

    /**
     * Drop all idle gearman functions that doing nothing
     * Which means functions having 0 queue, 0 running, 0 workers
     *
     * It is a safe function and only drop that function having 0 0 0
     * when run `gearadmin --stauts` on command line
     *
     * @param string $gearadmin cmd command complete path e.g: /bin/gearadmin
     * @return void
     */
    public static function dropIdleGearmanFunctions($gearadmin)
    {
        $statuses = self::parseGearadminStatus($gearadmin);

        if (!empty($statuses)) {
            // Loop through all $statuses
            foreach ($statuses as $k => $status) {
                // Function name must not be empty and must not be '.'
                if ($status['function'] != '' && $status['function'] != '.') {
                    // Drop function that have no job in queue with no running job
                    // and have no running worker
                    if ($status['queue'] == '0' &&
                        $status['running'] == '0' &&
                        $status['workers'] == '0') {
                        // Execute command to drop function
                        $command = $gearadmin . ' --drop-function ' . $status['function'];
                        exec($command);
                    }
                }
            }
        }
    }

    /**
     * Remove stuck workers from memory which are older than given days
     *
     * @param string $gearadmin cmd command complete path e.g: /bin/gearadmin
     * @param string $pkill cmd command complete path e.g: /bin/pkill
     * @param string $remove_stucked_workers_after_days Number of days
     * @return void
     */
    public static function removeStuckedWrokers($gearadmin, $pkill, $remove_stucked_workers_after_days)
    {
        $statuses = self::parseGearadminStatus($gearadmin);

        if (!empty($statuses)) {
            foreach ($statuses as $status) {
                $worker_id = $status['function'];
                $parts = explode('-', $worker_id);

                // $date = Y-m-d
                @$date = $parts[1] . '-' . $parts[2] . '-' . $parts['3'];
                $DateTime = \DateTime::createFromFormat('Y-m-d', $date);

                if ($DateTime && $DateTime->format('Y-m-d') === $date) {
                    $days = floor((time() - strtotime($date)) / (60 * 60 * 24));
                    if ($days >= $remove_stucked_workers_after_days) {
                        // Remove background worker running process
                        $command = $pkill . ' -f ' . $worker_id;
                        exec($command);

                        // Drop function
                        $command = $gearadmin . ' --drop-function ' . $worker_id;
                        exec($command);
                    }
                }
            }
        }
    }

    /**
     * Shutdown mail background worker
     *
     * @param string $gearadmin cmd command complete path e.g: /bin/gearadmin
     * @param string $pkill cmd command complete path e.g: /bin/pkill
     * @param string $mail_background_worker_id
     * @return void
     */
    public static function shutdownMailBackgroundWorker($gearadmin, $pkill, $mail_background_worker_id)
    {
        // Remove background worker running process
        $command = $pkill . ' -f ' . $mail_background_worker_id;
        exec($command);

        // Remove send mail worker running process
        $send_mail_worker = str_replace('MailBackgroundWorker-', 'SendMailWorker-', $mail_background_worker_id);
        $command = $pkill . ' -f ' . $send_mail_worker;
        exec($command);

        // Drop functions related to mail_background_worker_id
        $statuses = self::parseGearadminStatus($gearadmin);

        if (!empty($statuses)) {
            $send_mail_worker_id = str_replace('MailBackgroundWorker-', '', $mail_background_worker_id);

            // Loop through all $statuses
            foreach ($statuses as $k => $status) {
                // Drop function name that must contain send_mail_worker_id
                if (strpos($status['function'], $send_mail_worker_id) !== false) {
                    // Execute command to drop function
                    $command = $gearadmin . ' --drop-function ' . $status['function'];
                    exec($command);
                }
            }
        }
    }

    /**
     * Delete file
     *
     * @param string $file_path
     * @return void
     */
    public static function deleteFile($file_path)
    {
        if (is_file($file_path)) {
            unlink($file_path);
        }
    }
}
