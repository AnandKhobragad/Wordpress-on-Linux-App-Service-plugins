<?php
class Azure_app_service_migration_Export_FileBackupHandler
{
    public static function handle_wp_filebackup($params)
    {
        try {
            $param = isset($_REQUEST['param']) ? $_REQUEST['param'] : "";
            if (!empty($param)) {
                if ($param == "wp_filebackup") {
                    $password = isset($params['confpassword']) ? $params['confpassword'] : "";
                    $dontexptpostrevisions = isset($params['dontexptpostrevisions']) ? $params['dontexptpostrevisions'] : "";
                    $dontexptsmedialibrary = isset($params['dontexptsmedialibrary']) ? $params['dontexptsmedialibrary'] : "";
                    $dontexptsthems = isset($params['dontexptsthems']) ? $params['dontexptsthems'] : "";
                    $dontexptmustuseplugins = isset($params['dontexptmustuseplugs']) ? $params['dontexptmustuseplugs'] : "";
                    $dontexptplugins = isset($params['dontexptplugins']) ? $params['dontexptplugins'] : "";
                    $dontdbsql = isset($params['donotdbsql']) ? $params['donotdbsql'] : "";

                    if (!isset($params['status'])) {
                        $params['status'] = array();
                    }
                    
                    $zipFilePath = self::getZipFilePath($params['zip_file_name']);
                    //Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Zip file path is: ' . $zipFilePath);

                    $excludedFolders = self::getExcludedFolders($dontexptsmedialibrary, $dontexptsthems, $dontexptmustuseplugins, $dontexptplugins);

                    // Enumerate wp-content directory into a csv file
                    if (!isset($params['status']['enumerate_content']) || !$params['status']['enumerate_content']) {
                        if (!isset($params['status']['enumerate_content'])) {
                            Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Enumerating Wp-Content folder...');
                        }

                        $params['status']['enumerate_content'] = false;
                        $enumerate_start_index = isset($params['enumerate_start_index'])
                                                ? $params['enumerate_start_index']
                                                : 0;
                        try {
                            $enumerate_result = self::enumerateContent($enumerate_start_index, $excludedFolders);
                            if ($enumerate_result['completed']) {
                                unset($params['enumerate_start_index']);
                                $params['status']['enumerate_content'] = true;
                            } else {
                                $params['enumerate_start_index'] = $enumerate_result['enumerate_start_index'];
                            }
                        } catch (Exception $ex) {
                            throw $ex;
                        }
                        // start new session to continue rest of export
                        $params['completed'] = false;
                        return $params;
                    }

                    // Generate Zip Archive
                    $zipCreated = false;
                    try {
                        $zipCreated = self::createZipArchive($zipFilePath, $excludedFolders, $dontdbsql, $password, $dontexptpostrevisions, $params);
                    } catch (Exception $ex) {
                        throw $ex;
                    }

                    if ($zipCreated) {
                        Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Content is exported and Ready to download');
                        unset($params['status']);
                        $params['completed'] = true;
                        return $params;
                    } else {
                        $params['completed'] = false;
                        return $params;
                    }
                }
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'An exception occurred: ' . $e->getMessage());
            echo json_encode(array(
                "status" => 0,
                "message" => "An exception occurred: " . $e->getMessage(),
            ));
            throw $e;
        }
        return $params;
    }

    // Adds the list of files in wp-content directory to csv file 
    private static function enumerateContent($enumerate_start_index, $excludedFolders) {       
        // Start time
		$start = microtime( true );

        // Initialize completed flag
        $completed = true;

        // Create enumerate file base directory
        $enumerate_file_dir = dirname(AASM_EXPORT_ENUMERATE_FILE);
        if (!is_dir($enumerate_file_dir)) {
            mkdir($enumerate_file_dir, 0755, true);
        }

        // Open in append mode
        $csvFile = fopen(AASM_EXPORT_ENUMERATE_FILE, 'a');

        // Initialize directory iterator
        $directoryIterator = new RecursiveDirectoryIterator(ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR, RecursiveDirectoryIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

        // Initialize indices
        $resumeIndex = $enumerate_start_index;
        $currentIndex = 0;

        foreach ($recursiveIterator as $fileInfo) {
            // break when timeout (10s) is reached
            if ( ( microtime( true ) - $start ) > 100 ) {
                if ($enumerate_start_index > $currentIndex) {
                    throw new Exception('Error enumerating wp-content.');
                }
                $enumerate_start_index = $currentIndex;
                $completed = false;
                break;
            }

            if ($fileInfo->isFile()) {
                if ($currentIndex >= $resumeIndex) {
                    $filePath = $fileInfo->getPathname();
                    // Initialize exclude file flag
                    $excludeFile = false;
                    foreach($excludedFolders as $excludedFolder) {
                        if (str_starts_with($filePath, AASM_Common_Utils::replace_forward_slash_with_directory_separator($excludedFolder))) {
                            $excludeFile = true;
                        }
                    }

                    // Add file to csv if it is not part of excluded folders
                    if (!$excludeFile) {
                        $relativePath = $filePath;
                        $rootDirPrefix = ABSPATH;
                        if (strpos($filePath, $rootDirPrefix) === 0) {
                            $relativePath = substr($filePath, strlen($rootDirPrefix));
                        }
                        fputcsv($csvFile, [$currentIndex, $filePath, $relativePath]);
                    }
                }
            }
            $currentIndex++;
        }

        fclose($csvFile);
        return array(
            'completed' => $completed,
            'enumerate_start_index' => $enumerate_start_index,
        );
    }

    public static function generateZipFileName()
    {
        $File_Name = $_SERVER['HTTP_HOST'];
        $datetime = date('Y-m-d_H-i-s');
        return $File_Name . '_' . $datetime . '.zip';
    }

    private static function getZipFilePath($zipFileName)
    {
        // Create the directory if it doesn't exist
        if (!is_dir(AASM_EXPORT_ZIP_LOCATION)) {
            mkdir(AASM_EXPORT_ZIP_LOCATION, 0777, true);
            // Set appropriate permissions for the directory (0777 allows read, write, and execute permissions for everyone)
        }
        return AASM_EXPORT_ZIP_LOCATION . $zipFileName;
    }

    private static function getExcludedFolders($dontexptsmedialibrary, $dontexptsthems, $dontexptmustuseplugins, $dontexptplugins)
    {
        $excludedFolders = [];
        if ($dontexptsmedialibrary) {
            $excludedFolders[] = 'uploads';
        }
        if ($dontexptsthems) {
            $excludedFolders[] = 'themes';
        }
        if ($dontexptmustuseplugins) {
            $excludedFolders[] = 'mu-plugins';
        }
        if ($dontexptplugins) {
            $excludedFolders[] = 'plugins';
        }
        return $excludedFolders;
    }

    public static function deleteExistingZipFiles()
    {
        try {
            // Return if export storage directory not present
            if (!is_dir(AASM_EXPORT_ZIP_LOCATION)) {
                return;
            }

            $File_Name = $_SERVER['HTTP_HOST'];
            $iterator = new DirectoryIterator(AASM_EXPORT_ZIP_LOCATION);
            foreach ($iterator as $file) {
                if ($file->isFile() && strpos($file->getFilename(), $File_Name) === 0 && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'zip') {
                    $filePath = $file->getPathname();
                    unlink($filePath);
                }
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'File Delete error: ' . $e->getMessage());
            throw new AASM_File_Delete_Exception('File Delete error:' . $e->getMessage());
        }
    }

    private static function createZipArchive($zipFilePath, $excludedFolders, $dontdbsql, $password, $dontexptpostrevisions, $params)
    {
        if (!isset($params['status']['create_zip_archive'])) {
            $params['status']['create_zip_archive'] = false;
        }

        // Return if zip archive was already created in previous sessions
        if ($params['status']['create_zip_archive']) {
            return true;
        }

        $zipCreated = false;
        try {
                $zip = new ZipArchive();
                if ($zip->open($zipFilePath, ZipArchive::CREATE) === true) {
                    $wpContentFolderNameInZip = 'wp-content/';
                    $zip->addEmptyDir($wpContentFolderNameInZip);
                    if (!$dontdbsql) {
                        $wpDBFolderNameInZip = 'wp-database/';
                        $zip->addEmptyDir($wpDBFolderNameInZip);
                        
                        // Export Database Tables
                        if (!self::exportDatabaseTables($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions, $params)) {
                            return false;
                        }
                    }

                    $wp_root_path = get_home_path();
                    $folderPath = $wp_root_path . '/wp-content/';

                    try {
                        if (!self::addFilesToZip($zip, $folderPath, $wpContentFolderNameInZip, $excludedFolders, $password, $params)) {
                            return false;
                        }
                    } catch ( Exception $ex ) {
                        throw $ex;
                    }

                    $zip->close();
                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Zip Archive closed successfully.');

                    return true;
                } else {
                    throw new Exception("Export failed... Couldn't open the Zip file: " . $zipFilePath);
                }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Zip creation error: ' . $e->getMessage());
            throw new AASM_Archive_Exception('Zip creation error:' . $e->getMessage());
        }
        return false;
    }

    private static function exportDatabaseTables($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions, &$params) {
        // Export database tables' structure if not completed in previous sessions
        if (!isset($params['status']['export_database_table_structure']) || !$params['status']['export_database_table_structure']) {
            $params['status']['export_database_table_structure'] = false;
            if (self::exportDatabaseTablesStructure($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions, $params)) {
                $params['status']['export_database_table_structure'] = true;
            }
            // return false to start a new session which will resume export database records
            return false;
        }

        // Export database tables' records if not completed in previous sessions
        if (!isset($params['status']['export_database_table_records']) || !$params['status']['export_database_table_records']) {
            $params['status']['export_database_table_records'] = false;
            if (self::exportDatabaseTablesRecords($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions, $params)) {
                $params['status']['export_database_table_records'] = true;
            }
            // return false to start a new session which will resume zipping of wp-content
            return false;
        }

        return true;

    }

    private static function exportDatabaseTablesStructure($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions, &$params)
    {
        // Start time
		$start = microtime( true );

        // Initialize completed flag
        $completed = true;

        // Initialize start table index
        if (!isset($params['start_db_table_structure_index'])) {
            $params['start_db_table_structure_index'] = 0;
        }
        $start_table_index = $params['start_db_table_structure_index'];

        global $wpdb;
        $tablesQuery = "SHOW TABLES";
        $tables = $wpdb->get_results($tablesQuery, ARRAY_N);
        
        try {
            for ($tableNum = $start_table_index; $tableNum < count($tables); $tableNum++) {
                if ( ( microtime( true ) - $start ) > 20 ) {
                    $params['start_db_table_structure_index'] = $tableNum;
                    $completed = false;
                }
                $tableName = $tables[$tableNum][0];
                $structureQuery = "SHOW CREATE TABLE {$tableName}";
                $structureResult = $wpdb->get_row($structureQuery, ARRAY_N);
                $tableStructure = $structureResult[1];
                $structureFilename = "{$tableName}_structure.sql";
                $zip->addFromString($wpDBFolderNameInZip . $structureFilename, $tableStructure);

                if ($password !== '') {
                    $zip->setEncryptionName($wpDBFolderNameInZip . $structureFilename, ZipArchive::EM_AES_256, $password);
                }
            }
            
            // return false if not completed to resume execution in a new session
            if (!$completed) {
                return false;
            } else {
                unset($params['start_db_table_structure_index']);
                return true;
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'DB Tables export exception: ' . $e->getMessage());
            throw new AASM_Export_Exception('DB Tables export exception:' . $e->getMessage());
        }
        
        return $completed;
    }

    private static function exportDatabaseTablesRecords($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions, &$params)
    {
        // Start time
		$start = microtime( true );

        // initialize completed flag
        $completed = true;

        // Initialize start table index
        if (!isset($params['start_db_table_records_index'])) {
            $params['start_db_table_records_index'] = 0;
        }
        $start_table_index = $params['start_db_table_records_index'];

        // Initialize db records offset
        if (!isset($params['db_records_offset'])) {
            $params['db_records_offset'] = 0;
        }
        $offset = $params['db_records_offset'];

        // Initialize db records batchNumber
        if (!isset($params['db_records_batchNumber'])) {
            $params['db_records_batchNumber'] = 0;
        }
        $batchNumber = $params['db_records_batchNumber'];

        $batchSize = 1000;

        global $wpdb;
        $tablesQuery = "SHOW TABLES";
        $tables = $wpdb->get_results($tablesQuery, ARRAY_N);

        try {
            for ($tableNum = $start_table_index; $tableNum < count($tables); $tableNum++) {
                // break when timeout (20s) is reached
                if ( ( microtime( true ) - $start ) > 10 ) {
                    $params['start_db_table_records_index'] = $tableNum;
                    $params['db_records_offset'] = $offset;
                    $params['db_records_batchNumber'] = $batchNumber;
                    $completed = false;
                    break;
                }

                $tableName = $tables[$tableNum][0];
                Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Exporting Records for table : ' . $tableName . '-started');       
                do {
                    if ($dontexptpostrevisions && $tableName == 'wp_posts') {
                        $recordsQuery = "SELECT * FROM {$tableName} WHERE post_type != 'revision' LIMIT {$offset}, {$batchSize}";
                    } else {
                        $recordsQuery = "SELECT * FROM {$tableName} LIMIT {$offset}, {$batchSize}";
                    }

                    $records = $wpdb->get_results($recordsQuery, ARRAY_A);
                    $recordsFilename = "{$tableName}_records_batch{$batchNumber}.sql";

                    if (!empty($records)) {
                        $recordsContent = "";

                        foreach ($records as $record) {
                            $recordValues = [];

                            foreach ($record as $value) {
                                $recordValues[] = self::formatRecordValue($value);
                            }

                            $recordsContent .= "INSERT INTO {$tableName} VALUES (" . implode(', ', $recordValues) . ");\n";
                        }
                        $zip->addFromString($wpDBFolderNameInZip . $recordsFilename . ".sql", $recordsContent);

                        if ($password !== '') {
                            $zip->setEncryptionName($wpDBFolderNameInZip . $tableName . ".sql", ZipArchive::EM_AES_256, $password);
                        }
                    }

                    $offset += $batchSize;
                    $batchNumber++;
                } while (!empty($records));

                $batchNumber = 0;

                Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Exporting Records for table: ' . $tableName . ' - completed');
            }

            if (!$completed) {
                return false;
            } else {
                unset($params['start_db_table_records_index']);
                unset($params['db_records_offset']);
                unset($params['db_records_batchNumber']);
                
                return true;
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Table records export exception: ' . $e->getMessage());
            throw new AASM_Export_Exception('Table records export exception:' . $e->getMessage());
        }

        return false;
    }

    private static function formatRecordValue($value)
    {
        try {
            if (is_null($value)) {
                return "NULL";
            } elseif (is_int($value) || is_float($value) || is_numeric($value)) {
                return $value;
            } elseif (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            } elseif (is_object($value) || is_array($value)) {
                return "'" . addslashes(serialize($value)) . "'";
            } elseif (is_string($value)) {
                if (is_numeric($value)) {
                    return $value;
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return "'" . $value . "'";
                } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                    return "'" . $value . "'";
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                    return "'" . $value . "'";
                } elseif (is_numeric($value) && (strpos($value, '.') !== false || strpos($value, 'e') !== false)) {
                    return $value;
                } else {
                    return "'" . addslashes($value) . "'";
                }
            } else {
                return "'" . addslashes($value) . "'";
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Table record format exception: ' . $e->getMessage());
            throw new AASM_Export_Exception('Table record format exception:' . $e->getMessage());
        }
    }

    private static function addFilesToZip($zip, $folderPath, $wpContentFolderNameInZip, $excludedFolders, $password, &$params)
    {
        // Start time
		$start = microtime( true );

        // Initialize completed flag
        $completed = true;
        
        if (!isset($params['status']['add_files_to_zip'])) {
            $params['status']['add_files_to_zip'] = false;
        }

        // Return if zip archive was already created in previous sessions
        if ($params['status']['add_files_to_zip']) {
            return true;
        }
        
        // Initialize enumerate file offset
        if (!isset($params['enumerate_file_offset'])) {
            $params['enumerate_file_offset'] = 0;
        }
        $enumerate_file_offset = $params['enumerate_file_offset'];

        try {
            // Open enumerate csv file
            $csvFile = fopen(AASM_EXPORT_ENUMERATE_FILE, 'r');
            if (!$csvFile) {
                throw new Exception('Could not read enumerate csv file: ' . AASM_EXPORT_ENUMERATE_FILE);
            }

            // Seek to the specified offset
            fseek($csvFile , $enumerate_file_offset);

            $currentOffset = $enumerate_file_offset;
            while (($row = fgetcsv($csvFile)) !== false) {
                
                $index = $row[0];
                $filePath = $row[1];
                $relativePath = $row[2];
                
                if (file_exists($filePath) && is_file($filePath)) {
                    $zip->addFile($filePath, $relativePath);
                }
                
                // Exit if time exceeds 10 seconds
                if ( ( microtime( true ) - $start ) > 10 ) {
                    $currentOffset = ftell($csvFile);
                    $completed = false;
                    break;
                }
            }

            if (!$completed) {
                $params['enumerate_file_offset'] = $currentOffset;
                return false;
            }

            // Update params
            $params['status']['add_files_to_zip'] = true;
            unset($params['enumerate_file_offset']);
            return true;
        } catch (Exception $ex) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Failed to zip wp-content: ' . $e->getMessage());
            throw new AASM_Archive_Exception('Failed to extract wp-content: ' . $e->getMessage());
        }
        
        return false;
    }

    private static function filterCallback($current, $excludedFolders, &$filteredElements)
    {
        $fileName = $current->getFilename();
        $filePath = $current->getPathname();
        $relativePath = substr($filePath, strlen(get_home_path()));
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePathParts = explode('/', $relativePath);
        $parentFolder = isset($relativePathParts[2]) ? $relativePathParts[2] : '';

        if ($fileName == "." || $fileName == "..") {
            return false;
        }

        if (in_array($parentFolder, $excludedFolders)) {
            return false;
        }

        if (in_array($relativePath, $filteredElements)) {
            return false;
        }

        $filteredElements[] = $relativePath;
        return true;
    }
}
?>