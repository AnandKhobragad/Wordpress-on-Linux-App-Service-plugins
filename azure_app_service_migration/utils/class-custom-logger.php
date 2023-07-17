<?php
// CustomLogger.php

class Azure_app_service_migration_Custom_Logger
{
    // Initialize the custom logging functionality
    public static function init()
    {
        // Initialize log file
        $log_file_dir = dirname(AASM_MIGRATION_LOGFILE_PATH);
        if (!file_exists($log_file_dir))
        {
            mkdir($log_file_dir, 0777, true);
        }
    }

    // Clear Log file
    public static function reset_log_file()
    {
        $log_file = AASM_MIGRATION_LOGFILE_PATH;
        file_put_contents($log_file, 'Azure App Service Migration Logs' . PHP_EOL . PHP_EOL);
    }

    // Write log messages to the custom log file
    // parameters: service_type = {IMPORT/EXPORT}
    public static function writeToLog($status, $message = '', $service_type = '')
    {
        // Define the log file path and name
        $log_file = AASM_MIGRATION_LOGFILE_PATH;
        
        // Get the current date and time
        $current_time = date('Y-m-d H:i:s');

        // Format the log message
        $log_message = "[{$current_time}] {$service_type} {$status} {$message}" . PHP_EOL;

        // Append the log message to the log file
        file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
    }

    // Custom error handler
    // To Do: Remove $should_update_status_option parameter
    public static function logInfo($service_type, $message, $should_update_status_option = false)
    {
        // Get the current date and time
        $current_time = date('Y-m-d H:i:s');
        $info_message = "AASM_LOG: [{$current_time}]: {$service_type} {$message}";
        self::writeToLog($info_message);
    }

    public static function logError($service_type, $message)
    {
        // Get the current date and time
        $current_time = date('Y-m-d H:i:s');
        $error_message = "AASM_ERROR: [{$current_time}]: {$service_type} {$message}";
        self::writeToLog($error_message);

        // echo status to return to server
        $migration_status = array( 'type' => 'error', 'message' => $error_message );
        echo json_encode($migration_status);

        wp_die();
    }

    // Custom error handler
    public static function done($service_type)
    {
        // Get the current date and time
        $current_time = date('Y-m-d H:i:s');
        $info_message = "AASM_LOG [{$current_time}]: {$service_type} Finished.";
        self::writeToLog($info_message);
    }

    // Custom exception handler
    public static function handleException($exception, $echo_status = true)
    {
        // Get the exception details
        $message = 'Exception: ' . $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();

        // Build the log message with details
        $log_message = "Exception:\n";
        $log_message .= "Message: {$message}\n";
        $log_message .= "File: {$file}\n";
        $log_message .= "Line: {$line}\n";
        $log_message .= "Trace:\n{$trace}";

        // Log the exception details
        self::writeToLog($log_message);

        if ($echo_status) {
            // echo status to return to server
            $migration_status = array( 'type' => 'exception', 'message' => $log_message );
            echo json_encode($migration_status);

            wp_die();
        }
    }

    public static function update_migration_status($data)
    {
        update_option( AASM_MIGRATION_STATUS, $data );
    }
}

// Initialize the custom logger
Azure_app_service_migration_Custom_Logger::init();
