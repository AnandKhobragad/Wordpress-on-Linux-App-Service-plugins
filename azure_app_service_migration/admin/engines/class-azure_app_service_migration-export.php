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
			file_put_contents('/home/d.txt', 'starting export' . PHP_EOL);
			// delete existing log file
			Azure_app_service_migration_Custom_Logger::delete_log_file(AASM_EXPORT_SERVICE_TYPE);
			
			// initalize import log file
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
				unlink(AASM_EXPORT_ENUMERATE_FILE);
			}
			
			// clear is_first_request param
			unset($params['is_first_request']);
		}

		$params['completed'] = false;

		// Loop over filters
		if ( ( $filters = AASM_Common_Utils::get_filter_callbacks( 'aasm_export' ) ) ) {
			while ( $hooks = current( $filters ) ) {
				file_put_contents('/home/d.txt', 'hook priority is ' . key($filters) . PHP_EOL, FILE_APPEND);
				if ( intval( $params['priority'] ) === key( $filters ) ) {
					foreach ( $hooks as $hook ) {
						try {
							// Run function hook
							file_put_contents('/home/d.txt', 'RUNNING HOOOK ' . $hook['function'] . PHP_EOL, FILE_APPEND);
							$params = call_user_func_array( $hook['function'], array( $params ) );
						} catch ( Exception $e ) {
							Azure_app_service_migration_Custom_Logger::handleException($e);
							exit;
						}
					}

					// exit after export process is completed
					if ($params['completed']) {
						file_put_contents('/home/d.txt', 'export completed' . PHP_EOL, FILE_APPEND);
						Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Export successfully completed.', true);
						exit;
					}

					file_put_contents('/home/d.txt', 'Making async http call' . PHP_EOL, FILE_APPEND);
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