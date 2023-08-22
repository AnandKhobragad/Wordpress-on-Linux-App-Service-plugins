<?php
class Azure_app_service_migration_Status {

    public static function export_status($params) {
		// Open Import csv file
		$export_status_file = fopen(AASM_EXPORT_STATUSFILE_PATH, 'r');
		if (!$export_status_file) {
			echo json_encode(array('status' => '', 'message' => 'Could not read export status'));
			wp_die();
		}

		// get the first row (status) from csv file
		$row = fgetcsv($export_status_file);
		if (!$row) {
			echo json_encode(array('status' => '', 'message' => 'Could not read export status'));
			wp_die();
		}
		fclose($export_status_file);

		// flush status message to browser
		$export_status = array( 'status' => $row[0], 'message' => $row[1] );
		echo json_encode($export_status);
		wp_die();
    }

	public static function import_status() {
		// Open Import csv file
		$import_status_file = fopen(AASM_IMPORT_STATUSFILE_PATH, 'r');
		if (!$import_status_file) {
			echo json_encode(array('status' => '', 'message' => 'Could not read import status'));
			wp_die();
		}

		// get the first row (status) from csv file
		$row = fgetcsv($import_status_file);
		if (!$row) {
			echo json_encode(array('status' => '', 'message' => 'Could not read import status'));
			wp_die();
		}
		fclose($import_status_file);

		// flush status message to browser
		$import_status = array( 'status' => $row[0], 'message' => $row[1] );
		echo json_encode($import_status);
		wp_die();
	}
}