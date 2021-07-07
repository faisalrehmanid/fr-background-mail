<?php

namespace FR\BackgroundMail\Storage;

use FR\Db\DbFactory;

class StorageFactory
{
    /**
     * Initialize or create storage object based on driver selection
     * 
     * // PDO MySQL connection configuration
     * $config =  [
     *                  'db' => [
     *                                     'driver' => 'pdo_mysql',
     *		                               'hostname' => 'localhost',
     *                                     'port' => '3306',
     *                                     'username' => 'root',
     *                                     'password' => '',
     *                                     'database' => 'database-name',
     *                                     'charset' => 'utf8mb4'
     *                  ],
     *	                'jobs_table'      => 'schema.background_mail_jobs',
     *	                'sent_log_table'  => 'schema.background_mail_job_sent_log',
     *	                'templates_table' => 'schema.background_mail_job_templates'
     * ];
     *
     * // Oracle connection configuration
     * $config =  [
     *                  'db' => [
     *                                      'driver' => 'oci8',
     *		                                'connection' => 'ERPDEVDB',
     *		                                'username' => 'USER',
     *		                                'password' => 'PASSWORD',
     *		                                'character_set' => 'AL32UTF8'
     *                  ],
     *	                'jobs_table'      => 'schema.background_mail_jobs',
     *	                'sent_log_table'  => 'schema.background_mail_job_sent_log',
     *	                'templates_table' => 'schema.background_mail_job_templates'
     * ];
     *
     * @param array $config Storage configuration
     * @throws \Exception When invalid driver given in connection configuration
     * @return object \FR\BackgroundMail\Storage\StorageInterface
     */
    public function init(array $config)
    {
        $db = $config['db'];
        $driver = $db['driver'];

        $jobs_table = $config['jobs_table'];
        $sent_log_table = $config['sent_log_table'];
        $templates_table = $config['templates_table'];

        $driver = strtolower($driver);
        if (in_array($driver, ['oci8'])) {

            $DB = new DbFactory();
            $DB = $DB->init($db);

            return new Oracle\Storage(
                $DB,
                $jobs_table,
                $sent_log_table,
                $templates_table
            );
        }

        if (in_array($driver, ['pdo_mysql'])) {

            $DB = new DbFactory();
            $DB = $DB->init($db);

            return new MySQL\Storage(
                $DB,
                $jobs_table,
                $sent_log_table,
                $templates_table
            );
        }

        $drivers = ['pdo_mysql', 'oci8'];
        throw new \Exception('Invalid driver. Driver must be: ' . implode(', ', $drivers));
    }
}
