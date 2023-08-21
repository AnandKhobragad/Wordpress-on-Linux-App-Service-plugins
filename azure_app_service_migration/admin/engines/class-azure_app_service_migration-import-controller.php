<?php
class Azure_app_service_migration_Import_Controller {

    public static function import($params) {

		// Continue execution when user aborts
		@ignore_user_abort( true );

		// Set maximum execution time
		@set_time_limit( 0 );

		// Set maximum time in seconds a script is allowed to parse input data
		@ini_set( 'max_input_time', '-1' );

		// Set maximum backtracking steps
		@ini_set( 'pcre.backtrack_limit', PHP_INT_MAX );

		// Set params
		if ( empty( $params ) ) {
			$params = stripslashes_deep( array_merge( $_GET, $_POST ) );
		}

		// Set priority
		if ( ! isset( $params['priority'] ) ) {
			$params['priority'] = 5;
		}

		if (isset($params['is_first_request']) && !$params['is_first_request']) {
			Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Starting a new HTTP session.', true);
		}
		
		if ( isset($params['is_first_request']) && $params['is_first_request']) {
			// initalize import log file
			Azure_app_service_migration_Custom_Logger::delete_log_file(AASM_IMPORT_SERVICE_TYPE);
			Azure_app_service_migration_Custom_Logger::init(AASM_IMPORT_SERVICE_TYPE);

			//initialize status file
			self::initialize_status_file();
			
			// clear DB temp directory
			AASM_Common_Utils::clear_directory_recursive(AASM_DATABASE_TEMP_DIR);

			// clear is_first_request param
			unset($params['is_first_request']);
		}

		$params['completed'] = false;

		// Loop over filters
		if ( ( $filters = AASM_Common_Utils::get_filter_callbacks( 'aasm_import' ) ) ) {
			while ( $hooks = current( $filters ) ) {
				if ( intval( $params['priority'] ) === key( $filters ) ) {
					foreach ( $hooks as $hook ) {
						try {
							// Run function hook
							$params = call_user_func_array( $hook['function'], array( $params ) );
						} catch ( Exception $e ) {
							Azure_app_service_migration_Custom_Logger::handleException($e);
							exit;
						}
					}

					// exit after the last function of import process is completed
					if ($params['priority'] == 40 && $params['completed']) {
						Azure_app_service_migration_Custom_Logger::done(AASM_IMPORT_SERVICE_TYPE);
						exit;
					}
				
					$response = wp_remote_post(                                                                                                        
						admin_url( 'admin-ajax.php?action=aasm_import' ) ,
						array(                                               
						'method'    => 'POST',
						'timeout'   => 5,                                        
						'blocking'  => false,
						'sslverify' => false,
						'headers'   => AASM_Common_Utils::http_export_headers(array()),                                             
						'body'      => $params,
						'cookies'   => array(),
						)                                           
					);
					exit;
				}
				next( $filters );
			}
		}		
    }

	private static function initialize_status_file() {
		if (file_exists(AASM_IMPORT_STATUSFILE_PATH))
			unlink(AASM_IMPORT_STATUSFILE_PATH);
		
		// open statusfile
		$statusFile = fopen(AASM_IMPORT_STATUSFILE_PATH, 'w');
		
		// write status
		fputcsv($statusFile, ['info', 'Exporting...']);
		
		// close file
		fclose($statusFile);
	}
}