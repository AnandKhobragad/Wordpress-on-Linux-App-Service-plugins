<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'ABSPATH undefined.' );
}

// ==================
// = Plugin Version =
// ==================
define( 'AASM_VERSION', '1.0.0' );

// ===============
// = Plugin Name =
// ===============
define( 'AASM_PLUGIN_NAME', 'azure_app_service_migration' );

// ===============
// = Plugin Name =
// ===============
define( 'AASM_IMPORT_ZIP_FILE_NAME', 'importfile' );

// ================
// = Import Zip File Storage Path =
// ================
define( 'AASM_IMPORT_ZIP_LOCATION', substr(AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH, 0, -1) . DIRECTORY_SEPARATOR . 
                                    'ImportedFile' . DIRECTORY_SEPARATOR);
define( 'AASM_EXPORT_ZIP_LOCATION', substr(AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH, 0, -1) . DIRECTORY_SEPARATOR . 
                                    'ExportedFile' . DIRECTORY_SEPARATOR);

define( 'AASM_EXPORT_ENUMERATE_FILE', substr(AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH, 0, -1) . DIRECTORY_SEPARATOR . 
'ExportedFile' . DIRECTORY_SEPARATOR . 'content_enumerate.csv');

// ================
// = Import Zip File Storage Path =
// ================
define( 'AASM_IMPORT_ZIP_FILE', AASM_IMPORT_ZIP_LOCATION . 'importfile.zip');

// ================
// = Import Zip File Storage Path =
// ================
define( 'AASM_IMPORT_ZIP_PATH', AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH . 
                                'storage' . DIRECTORY_SEPARATOR . 
                                'import' . DIRECTORY_SEPARATOR .
                                AASM_IMPORT_ZIP_FILE_NAME . '.zip');

// ================
// = Database sql files storage path in zip file =
// ================
define( 'AASM_LOG_FILE_LOCATION', substr(AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH, 0, -1) . DIRECTORY_SEPARATOR . 
                                'Logs' . DIRECTORY_SEPARATOR);
define( 'AASM_DATABASE_RELATIVE_PATH_IN_ZIP', 'wp-database' . DIRECTORY_SEPARATOR );

// ================
// = AASM plugin path in zip file =
// ================
define( 'AASM_PLUGIN_RELATIVE_PATH_IN_ZIP', 'wp-content' . DIRECTORY_SEPARATOR . 
                                            'plugins' . DIRECTORY_SEPARATOR .
                                            AASM_PLUGIN_NAME . DIRECTORY_SEPARATOR );

// ================
// = Directory to extract sql files to =
// ================
define( 'AASM_DATABASE_TEMP_DIR', AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH . 'storage' . 
                                DIRECTORY_SEPARATOR . 'dbtempdir' . DIRECTORY_SEPARATOR );

// ================
// = Directory to extract sql files to =
// ================
define( 'AASM_DATABASE_SQL_DIR', AASM_DATABASE_TEMP_DIR . 'wp-database' . 
                                DIRECTORY_SEPARATOR );

// ================
// = Uploads folder path =
// ================
define( 'AASM_UPLOADS_FOLDER_PATH', ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 
                                'uploads' . DIRECTORY_SEPARATOR );

// ================
// = W3TC plugin path =
// ================
define( 'AASM_W3TC_PLUGIN_DIR', 'wp-content' . DIRECTORY_SEPARATOR . 
                                'plugins' . DIRECTORY_SEPARATOR .
                                'w3-total-cache' . DIRECTORY_SEPARATOR );

// ================
// = W3TC config path =
// ================
define( 'AASM_W3TC_CONFIG_DIR', 'wp-content' . DIRECTORY_SEPARATOR . 
                                'w3tc-config' . DIRECTORY_SEPARATOR );
// ================
// = W3TC advanced cache file path =
// ================
define( 'AASM_W3TC_ADVANCED_CACHE_PATH', 'wp-content' . DIRECTORY_SEPARATOR . 
                                'advanced-cache.php' );

// ================
// = W3TC object cache path =
// ================
define( 'AASM_W3TC_OBJECT_CACHE_PATH', 'wp-content' . DIRECTORY_SEPARATOR . 
                                'object-cache.php');

// ================
// = W3TC db.php path =
// ================
define( 'AASM_W3TC_DB_PATH', 'wp-content' . DIRECTORY_SEPARATOR . 
                                'db.php');
                                

// ================
// = W3TC plugin file path =
// ================
define( 'AASM_W3TC_PLUGIN_FILE_PATH', ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 
                                    'plugins' . DIRECTORY_SEPARATOR .
                                    'w3-total-cache' . DIRECTORY_SEPARATOR . 
                                    'w3-total-cache.php');

// ================
// = W3TC config master file path =
// ================
define( 'AASM_W3TC_CONFIG_MASTER_PATH', ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 
                            'w3tc-config' . DIRECTORY_SEPARATOR .
                            'master.php');

// ================
// = DB records query separator =
// ================
define( 'AASM_DB_RECORDS_QUERY_SEPARATOR', '#$8@!J4*q6&$^+');

// ==============
// = Migration Status =
// ==============
define( 'AASM_MIGRATION_STATUS', 'aasm_migration_status' );

// ==============
// = Import Service Type =
// ==============
define( 'AASM_IMPORT_SERVICE_TYPE', 'IMPORT' );

// ==============
// = Export Service Type =
// ==============
define( 'AASM_EXPORT_SERVICE_TYPE', 'EXPORT' );

// ==============
// = Export Service Log file path =
// ==============
define( 'AASM_IMPORT_LOGFILE_PATH', substr(AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH, 0, -1) . DIRECTORY_SEPARATOR . 'Logs' . 
                                    DIRECTORY_SEPARATOR . 'import_log.txt');
// ==============
// = Export Service Log file path =
// ==============
define( 'AASM_EXPORT_LOGFILE_PATH', substr(AZURE_APP_SERVICE_MIGRATION_PLUGIN_PATH, 0, -1) . DIRECTORY_SEPARATOR . 'Logs' . 
                                    DIRECTORY_SEPARATOR . 'export_log.txt');
