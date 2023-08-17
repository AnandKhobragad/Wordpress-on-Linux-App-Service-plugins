<?php
class Azure_app_service_migration_Export {

    public static function export($params) {

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
		
		// First time functions executed here
		if ( isset($params['is_first_request']) && $params['is_first_request']) {
			// initalize import log file
			Azure_app_service_migration_Custom_Logger::delete_log_file(AASM_EXPORT_SERVICE_TYPE);			
			Azure_app_service_migration_Custom_Logger::init(AASM_EXPORT_SERVICE_TYPE);
			Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Started with the export process.');
			
			$params['password'] = isset($_REQUEST['confpassword']) ? $_REQUEST['confpassword'] : "";
			$params['dontexptpostrevisions'] = isset($_REQUEST['dontexptpostrevisions']) ? $_REQUEST['dontexptpostrevisions'] : "";
			$params['dontexptsmedialibrary'] = isset($_REQUEST['dontexptsmedialibrary']) ? $_REQUEST['dontexptsmedialibrary'] : "";
			$params['dontexptsthems'] = isset($_REQUEST['dontexptsthems']) ? $_REQUEST['dontexptsthems'] : "";
			$params['dontexptmustuseplugins'] = isset($_REQUEST['dontexptmustuseplugs']) ? $_REQUEST['dontexptmustuseplugs'] : "";
			$params['dontexptplugins'] = isset($_REQUEST['dontexptplugins']) ? $_REQUEST['dontexptplugins'] : "";
			$params['dontdbsql'] = isset($_REQUEST['donotdbsql']) ? $_REQUEST['donotdbsql'] : "";

			// delete enumerate csv file
			if (file_exists(AASM_EXPORT_ENUMERATE_FILE)) {
				Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Deleting the previously generated enumerate csv file.');
				unlink(AASM_EXPORT_ENUMERATE_FILE);
			}

			Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Deleting the previously generated exported file.');
			Azure_app_service_migration_Export_FileBackupHandler::deleteExistingZipFiles();

			// generate zip file name
			$params['zip_file_name'] = Azure_app_service_migration_Export_FileBackupHandler::generateZipFileName();
			Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Zip file name is generated as: ' . $zipFileName);
			
			// clear is_first_request param
			unset($params['is_first_request']);
		}

		$params['completed'] = false;

		// Loop over filters
		if ( ( $filters = AASM_Common_Utils::get_filter_callbacks( 'aasm_export' ) ) ) {
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

					// exit after export process is completed
					if ($params['completed']) {
						Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Export successfully completed.', true);
						exit;
					}

					$response = wp_remote_post(                                                                                                        
						admin_url( 'admin-ajax.php?action=export' ) ,
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
}