<?php
// To Do: make this static
class AASM_Database_Manager {
    public function __construct() {
    }

    public function create_database($databaseName) {
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Creating new database.', true);
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $query = "CREATE DATABASE $databaseName $charsetCollate;";
        return $wpdb->query($query) !== false;
    }

    public function drop_database($databaseName) {
        global $wpdb;
        $query = "DROP DATABASE IF EXISTS $databaseName;";
        return $wpdb->query($query) !== false;
    }

    public function rename_database($oldName, $newName) {
        global $wpdb;
        $query = "ALTER DATABASE $oldName RENAME TO $newName;";
        return $wpdb->query($query) !== false;
    }

    public function run_query($databaseName, $query) {
        global $wpdb;
        $wpdb->select($databaseName);
        return $wpdb->get_results($query);
    }

    public function import_sql_file($databaseName, $sqlFilePath) {
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Importing sql file ' . basename($sqlFilePath) . ' to new database', true);
    
        global $wpdb;
        $wpdb->select($databaseName);
    
        // Read in entire file
        $sqlContent = file_get_contents($sqlFilePath);
    
        // Execute the SQL statements using mysqli_multi_query
        if ($wpdb->dbh->multi_query($sqlContent)) {
            do {
                // Consume all results for the executed queries
                $wpdb->dbh->store_result();
            } while ($wpdb->dbh->more_results() && $wpdb->dbh->next_result());
        } else {
            // Query execution failed, and there is an error.
            echo "Database Error: " . $wpdb->dbh->error;
            return false;
        }
    
        return true;
    }

    public function database_exists($databaseName) {
        global $wpdb;
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$databaseName';";
        $result = $wpdb->get_row($query);
        return !empty($result);
    }
    
    // Update the 'siteurl' and 'home' values in the options table of the original WordPress database
    public function update_originaldb_data($newDatabaseName,$originalDataToUpdate )
    {
        $databaseConstants = $this->get_database_constants();
        
        $newWpDb = new wpdb($databaseConstants['DB_USER'],
                             $databaseConstants['DB_PASSWORD'], 
                             $newDatabaseName, 
                             $databaseConstants['DB_HOST']);

        // Extract the 'siteurl' and 'home' values from the retrieved data
        $newSiteURL = $originalDataToUpdate['siteurl'];
        $newHomeURL = $originalDataToUpdate['homeurl'];
        global $table_prefix;
        $optionsTable = $table_prefix . 'options';

        // Update the 'siteurl' option in the options table with the new value
        $siteurlUpdated = $newWpDb->update(
                            $optionsTable,
                            array('option_value' => $newSiteURL),
                            array('option_name' => 'siteurl')
                        );

        // Update the 'home' option in the options table with the new value
        $homeUpdated = $newWpDb->update(
                        $optionsTable,
                        array('option_value' => $newHomeURL),
                        array('option_name' => 'home')
                    );
        // Return true if both 'siteurl' and 'home' were successfully updated, otherwise return false
        return $siteurlUpdated && $homeUpdated;
    }

    // Retrieve the 'siteurl' and 'home' values from the original database options table
    public function get_originaldb_data()
    {
        global $wpdb;

        // Query the options table to get the 'siteurl' value
        $siteURL = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s", 'siteurl')
        );
        // Query the options table to get the 'home' value
        $homeURL = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s", 'home')
        );
        // Return the 'siteurl' and 'home' values as an associative array
        return array('siteurl' => $siteURL, 'homeurl' => $homeURL);
    }   

      // Method to get the database constants from wp-config.php
      public function get_database_constants() {
        // Get the path to wp-config.php
        $wpConfigPath = ABSPATH . 'wp-config.php';
    
        // Check if wp-config.php exists and is readable
        if (!file_exists($wpConfigPath)) {
            echo 'Error: wp-config.php not found.';
            return array();
        }
    
        // Load the wp-config.php file to access the constants directly
        include $wpConfigPath;
    
        // Get the database constants
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        $dbUser = defined('DB_USER') ? DB_USER : '';
        $dbPassword = defined('DB_PASSWORD') ? DB_PASSWORD : '';
        $dbHost = defined('DB_HOST') ? DB_HOST : '';
        // Return the database constants as an associative array
        return array(
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASSWORD' => $dbPassword,
            'DB_HOST' => $dbHost
        );
    }  
}