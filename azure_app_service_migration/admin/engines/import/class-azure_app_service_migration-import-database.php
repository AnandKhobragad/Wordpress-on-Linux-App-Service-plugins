<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'ABSPATH undefined.' );
}

class Azure_app_service_migration_Import_Database {
/*
    private $import_zip_path = null;
    private $params = null;
    private $new_database_name;
    private $old_database_name;
    private $database_manager;
    private $db_temp_dir;

    public function __construct($import_zip_path, $params) {
        global $wpdb;
        $hostname = $wpdb->dbhost;
        $username = $wpdb->dbuser;
        $password = $wpdb->dbpassword;

        $this->database_manager = new AASM_Database_Manager();
        $this->old_database_name = $wpdb->$dbname;
        $this->new_database_name = $this->generate_unique_database_name($this->old_database_name, $this->database_manager);
        $this->params = $params;
        $this->db_temp_dir = AASM_DATABASE_TEMP_DIR;            // Temporary directory for extracting sql files
        $this->import_zip_path = ($import_zip_path === null)    // Path to the uploaded import zip file
                                ? AASM_IMPORT_ZIP_LOCATION
                                : $import_zip_path;
    }*/

    public static function import_database($params)
    {
        // Flag to hold if file data has been processed
		$completed = true;

		// create extractor object for import zip file
		//$archive = new AASM_Zip_Extractor( $this->import_zip_path );

        // extract database sql files into temporary directory
        //$archive->extract_database_files(AASM_DATABASE_RELATIVE_PATH_IN_ZIP, $this->db_temp_dir);

        $database_manager = new AASM_Database_Manager();

        if (!isset($params['old_database_name']))   {
            $params['old_database_name'] = self::get_old_database_name();
        }

        // create new database
        if (!isset($params['new_database_name']))   {
            $params['new_database_name'] = self::generate_unique_database_name($params['old_database_name'], $database_manager);
        }

        if (!isset($params['status'])) {
            $params['status'] = array();
        }

        // Create new database
        if (!isset($params['status']['create_database_status'])) {
            if ( !$database_manager->create_database($params['new_database_name'])) {
                throw new Exception('Failed to create new database: ' . $params['new_database_name']);
            }
            $params['status']['create_database_status'] = true;
        }

        // Retrieve the 'siteurl' and 'home' values from the original database options table
        if (!isset($params['originaldb_data'])) {
            $params['originaldb_data'] = $database_manager->get_originaldb_data();
        }

        // Import each table sql file into the new database
        if (!isset($params['status']['import_db_sql_files'])) {
            if ( ! self::import_db_sql_files($params, $database_manager)) {
                $params['completed'] = false;
                return $params;
            }
            $params['status']['import_db_sql_files'] = true;
        }

        // update DB_NAME constant in wp-config
        if (!isset($params['status']['update_dbname_wp_config'])) {
            self::update_dbname_wp_config($params['new_database_name']);
            $params['status']['update_dbname_wp_config'] = true;
        }

        if (!isset($params['status']['update_originaldb_data'])) {
            if(!$database_manager->update_originaldb_data($params['new_database_name'], $params['originaldb_data'])) {
                throw new Exception("Couldn't update required original DB values into imported database.");
            }
            $params['status']['update_originaldb_data'] = true;
        }

        if (!isset($params['status']['activate_w3tc_plugin'])) {
            // imports w3tc options from original DB to new DB
            if ( isset( $params['retain_w3tc_config'] ) && strtoupper($params['retain_w3tc_config']) == "TRUE" ) {
                self::activate_w3tc_plugin();
            }
            $params['status']['activate_w3tc_plugin'] = true;
        }

        // Clean DB sql file placeholder directory
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Clearing Database files placeholder directory.', true);
        if (!isset($params['status']['clear_db_directory'])) {
            AASM_Common_Utils::clear_directory_recursive(AASM_DATABASE_TEMP_DIR);
            $params['status']['clear_db_directory'] = true;
        }

        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Database import complete.', true);
        $params['completed'] = true;

        if ($params['completed']) {
            unset($params['old_database_name']);
            unset($params['new_database_name']);
            unset($params['status']);
            unset($params['originaldb_data']);
            $params['priority'] = 20;
        }

        return $params;
    }

    public static function get_old_database_name() {
        global $wpdb;
        $hostname = $wpdb->dbhost;
        $username = $wpdb->dbuser;
        $password = $wpdb->dbpassword;

        return $wpdb->$dbname;
    }

    // Imports all sql files in wp-database/ directory inside the import zip file
    private static function import_db_sql_files($params, $database_manager) {
        // Initialize completed flag
        $completed = true;

        // Start time
		$start = microtime( true );

        if (!file_exists(AASM_DATABASE_SQL_DIR)) {
            mkdir(AASM_DATABASE_SQL_DIR, 0777, true);
        }

        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Importing Database tables and records.', true);
        $files = scandir(AASM_DATABASE_SQL_DIR);
        $table_records_files = [];

        // Import table structure and keep track of table records to be imported later
        foreach ($files as $file) {
            // break when timeout (20s) is reached
            if ( ( microtime( true ) - $start ) > 20 ) {
                $last_zip_index = $i;
                $completed = false;
                break;
            }

            // Reset time counter to prevent timeout
            set_time_limit(0);

            // Exclude current directory (.) and parent directory (..)
            if ($file != '.' && $file != '..') {
                $filePath = AASM_DATABASE_SQL_DIR . $file;

                // Check if the path is a file
                if (is_file($filePath) && str_ends_with($filePath, 'structure.sql')) {
                    if (!$database_manager->import_sql_file($params['new_database_name'], $filePath)) {
                        throw new Exception("Couldn't import " . $filePath . " into database.");
                    }

                    // delete sql schema structure file once imported
                    unlink($filePath);
                }
                else if (is_file($filePath) && str_ends_with($filePath, '.sql')) {
                    $table_records_files[] = $filePath;
                }
            }
        }

        if (! $completed) {
            return false;
        }

        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Finished importing Database tables. Importing database records...', true);
        // Import table records
        foreach ($table_records_files as $table_records) {
            set_time_limit(0);

            // break when timeout (20s) is reached
            if ( ( microtime( true ) - $start ) > 20 ) {
                $last_zip_index = $i;
                $completed = false;
                break;
            }

            if (!$database_manager->import_sql_file($params['new_database_name'], $table_records)) {
                Azure_app_service_migration_Custom_Logger::logError(AASM_IMPORT_SERVICE_TYPE, "Couldn't import " . $table_records . " into database.");
            }

            // delete table records file once imported
            unlink($table_records);
        }

        return $completed;
    }

    // Activates W3 Total Cache plugin
    private static function activate_w3tc_plugin() {
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Activating W3 Total Cache Plugin.', true);

        $plugin_to_activate = AASM_W3TC_PLUGIN_FILE_PATH;

        // Check if the plugin is already active.
        //To Do: deactivate and reactivate the plugin if already active
        if (is_plugin_active($plugin_to_activate)) {
            return;
        }

        // Activate the plugin.
        $activate = activate_plugin($plugin_to_activate);

        // Check if the activation was successful.
        if (is_wp_error($activate)) {
            Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Could not activate W3 Total Cache plugin.', true);
        }
    }

    // This function updates DB_NAME constant in wp-config.php file
    public static function update_dbname_wp_config($new_db_name) {
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Switching to new database.', true);
        // Path to the wp-config.php file
        $config_file_path = ABSPATH . 'wp-config.php';

        // To Do: Debug the commented method and replace with the following code
        // swap database names
        //$temp_database_name = $this->generate_unique_database_name();
        //$this->database_manager->rename_database($this->old_database_name, $temp_database_name);
        //$this->database_manager->rename_database($this->new_database_name, $this->old_database_name);
        // Read the contents of the wp-config.php file

        $config_file_contents = file_get_contents($config_file_path);

        // Replace the existing database_name value with the new one
        $updated_file_contents = preg_replace(
            "/define\(\'DB_NAME\', (.*)\);/",
            "define('DB_NAME', '" . $new_db_name . "');",
            $config_file_contents
        );

        // Write the updated contents back to the wp-config.php file
        file_put_contents($config_file_path, $updated_file_contents);
        
        // Adds AASM_MIGRATION_STATUS option to new database in addition to logging
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Updated Database name in wp-config.', true);
    }

    // Generates a unique database name. Retries 5 times
    private static function generate_unique_database_name($current_dbname, $database_manager) {
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Generating new database name.', true);
        $dbname_suffix = substr($current_dbname, 0, min(strlen($current_dbname), 10));

        for ($trycount = 0; $trycount < 5; $trycount++) {
            $new_dbname = $dbname_suffix . '_aasm_db_' . AASM_Common_Utils::generate_random_string_short();
            
            if (!($database_manager->database_exists($new_dbname)))
                return $new_dbname;
        }

        // To Do: Handle error here
        return $dbname_suffix;
    }
}