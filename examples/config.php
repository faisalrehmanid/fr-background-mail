<?php

require __DIR__ . '/../vendor/autoload.php';

$config = [
    'logs_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/error-logs-gearman',
    'recipients_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/examples/mail-recipients-csv',
    'autoload_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/vendor/autoload.php',
    'execute_path' => '/var/www/virtual/test/httpdocs/public/faisalrehmanid-fr-background-mail/src/Execute.php',
    'job_details_url' => 'https:/testurl.com/job-details/{job_id}', // Optional
    'timezone' => 'Asia/Karachi',
    'number_of_retry_for_send_mail' => 10,
    'number_of_send_mail_workers_for_background_job' => 3,
    'gearman' => [
        'client' => [
            'servers' => '127.0.0.1:4730' // Multiple servers can be separate by a comma
        ],
        'worker' => [
            'servers' => '127.0.0.1:4730' // Multiple servers can be separate by a comma
        ]
    ],
    'storage' => [
        'db' => [    // @see \FR\Db\DbFactory::init() for MySQL
            'driver' => 'pdo_mysql',
            'hostname' => 'localhost',
            'port' => '3306',
            'username' => 'root',
            'password' => '',
            'database' => 'test_fr_db_mysql',
            'charset' => 'utf8mb4',
        ],
        /*
        'db' => [    // @see \FR\Db\DbFactory::init() for Oracle
            'driver' => 'oci8',
            'connection' => 'ERPDEVDB',
            'username' => 'USER',
            'password' => 'PASSWORD',
            'character_set' => 'AL32UTF8',
        ],
        */
        'jobs_table' => 'test_fr_db_mysql.background_mail_jobs',
        'sent_log_table' => 'test_fr_db_mysql.background_mail_job_sent_log',
        'templates_table' => 'test_fr_db_mysql.background_mail_job_templates'
    ],
    'oracle_home' => '/home/oracle/app/oracle/product/19.0.0/client_1', // Only for oracle
    'ld_library_path' => '/home/oracle/app/oracle/product/19.0.0/client_1/lib', // Only for oracle

    // Use `which` command to check complete path like `which php` and in this case /bin/php
    'cmd' => [
        'php' => '/bin/php',
        'pkill' => '/bin/pkill',
        'gearadmin' => '/bin/gearadmin'
    ],

    // Remove stuck workers from memory which are older than given days
    'remove_stucked_workers_after_days' => 5,

    // List of response codes need to retry when email 'Not Sent'. If empty array is given
    // it will retry to send all 'Not Sent' emails but if codes are specified it will only
    // consider to retry those `Not Sent` emails having that response code.
    'retry_exception_codes' => [
        432
    ]
];
