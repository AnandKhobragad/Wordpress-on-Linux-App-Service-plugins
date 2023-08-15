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

                    $zipFileName = self::generateZipFileName();
                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Zip file name is generated as: ' . $zipFileName);

                    $zipFilePath = self::getZipFilePath($zipFileName);
                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Zip file path is: ' . $zipFilePath);

                    $excludedFolders = self::getExcludedFolders($dontexptsmedialibrary, $dontexptsthems, $dontexptmustuseplugins, $dontexptplugins);

                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Deleting the previously generated exported file.');
                    self::deleteExistingZipFiles();

                    // Enumerate wp-content directory into a csv file
                    $params = self::enumerateContent($params, $excludedFolders);

                    // if enumerate content is completed in current session then start new session for rest of the export
                    if ($params['continue_after_enumerate']) {
                        unset($params['continue_after_enumerate']);
                    } else {
                        return $params;
                    }

                    // Generate Zip Archive
                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Started generating the ZipArchive for ' . $zipFileName);
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
                        Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Failed to export after maximum retries.');
                        echo json_encode(array(
                            "status" => 0,
                            "message" => "Failed to export after maximum retries.",
                        ));
                    }

                    return $params;
                }
            }
        } catch (Exception $e) {
            throw $e;
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'An exception occurred: ' . $e->getMessage());
            echo json_encode(array(
                "status" => 0,
                "message" => "An exception occurred: " . $e->getMessage(),
            ));
        }
    }

    // Adds the list of files in wp-content directory to csv file 
    private static function enumerateContent($params, $excludedFolders) {
        if (!isset($params['status']['enumerate_content'])) {
            $params['status']['enumerate_content'] = false;
        }

        if ($params['status']['enumerate_content']) {
            $params['continue_after_enumerate'] = true;
            return $params;
        }
        
        // Start time
		$start = microtime( true );

        // Initialize completed flag
        $completed = true;

        // Initalize file to resume enumeration
        $enumerate_start_index = 0;
        if (isset($params['enumerate_start_index'])) {
            $enumerate_start_index = $params['enumerate_start_index'];
        }

        $enumerate_file_dir = dirname(AASM_EXPORT_ENUMERATE_FILE);
        if (!is_dir($enumerate_file_dir)) {
            mkdir($enumerate_file_dir, 0755, true);
        }

        // Open in append mode
        $csvFile = fopen($outputCSVPath, AASM_EXPORT_ENUMERATE_FILE);

        $directoryIterator = new DirectoryIterator($directoryPath);
        $lastIndex = $enumerate_start_index;
        foreach ($directoryIterator as $fileInfo) {
            // break when timeout (20s) is reached
            if ( ( microtime( true ) - $start ) > 10 ) {
                $enumerate_start_index = $lastIndex;
                $completed = false;
                break;
            }

            if ($fileInfo->isFile()) {
                if ($lastIndex >= $resumeIndex) {
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
                        fputcsv($csvFile, [$lastIndex, $filePath, $relativePath]);
                    }
                }
                $lastIndex++;
            }
        }

        fclose($csvFile);

        $params['enumerate_start_index'] = $enumerate_start_index;
        $params['continue_after_enumerate'] = false;
        
        if ($completed) {
            $params['status']['enumerate_content'] = true;
            unset($params['enumerate_start_index']);
        }
        return $params;
    }

    private static function generateZipFileName()
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

    private static function deleteExistingZipFiles()
    {
        try {
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

        $maxRetries = 3;
        $retryDelay = 5; // in seconds
        $retryCount = 0;
        $zipCreated = false;
        try {
                $zip = new ZipArchive();
                if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $wpContentFolderNameInZip = 'wp-content/';
                    $zip->addEmptyDir($wpContentFolderNameInZip);

                    if (!$dontdbsql) {
                        $wpDBFolderNameInZip = 'wp-database/';
                        $zip->addEmptyDir($wpDBFolderNameInZip);
                        
                        // Export Database Tables
                        $databaseExportResult = self::exportDatabaseTables($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions);
                        
                        $params['completed'] = $databaseExportResult['completed'];
                        if (!$params['completed']) {
                            $params['last_db_table_num'] = $databaseExportResult['last_db_table_num'];
                            return $params;
                        } 
                    }

                    $wp_root_path = get_home_path();
                    $folderPath = $wp_root_path . '/wp-content/';
                    self::addFilesToZip($zip, $folderPath, $wpContentFolderNameInZip, $excludedFolders, $password);

                    $zip->close();
                    $zipCreated = true;
                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Zip Archive closed successfully.');
                } else {
                    throw new Exception("Export failed... Couldn't open the Zip file: " . $zipFilePath);
                }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Zip creation error: ' . $e->getMessage());
            throw new AASM_Archive_Exception('Zip creation error:' . $e->getMessage());
        }
        return $zipCreated;
    }

    private static function exportDatabaseTables($zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions)
    {
        // Start time
		$start = microtime( true );

        global $wpdb;
        $tablesQuery = "SHOW TABLES";
        $tables = $wpdb->get_results($tablesQuery, ARRAY_N);
        
        try {
            $currentTable = null;
            for ($tableNum = 0; $tableNum < count($tables); $tableNum++) {
            //foreach ($tables as $table) {
                if ( ( microtime( true ) - $start ) > 20 ) {
                    $databaseExportResult = array();
                    $databaseExportResult['last_db_table_num'] = $tableNum;
                    $databaseExportResult['completed'] = $completed;
                    return $databasExportResult;
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

                if ($currentTable !== $tableName) {
                    $currentTable = $tableName;
                    Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Exporting Schema for table: ' . $currentTable);
                }

                self::exportTableRecords($wpdb, $tableName, $zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions);
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'DB Tables export exception: ' . $e->getMessage());
            throw new AASM_Export_Exception('DB Tables export exception:' . $e->getMessage());
        }
    }

    private static function exportTableRecords($wpdb, $tableName, $zip, $wpDBFolderNameInZip, $password, $dontexptpostrevisions)
    {
        $batchSize = 1000;
        $offset = 0;
        $batchNumber = 1;
        try {
            Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Exporting Records for table : ' . $tableName . '-started');            do {
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

                    if ($batchNumber === 1) {
                        $zip->addFromString($wpDBFolderNameInZip . $tableName . ".sql", $recordsContent);
                    } else {
                        $zip->appendFromString($wpDBFolderNameInZip . $tableName . ".sql", $recordsContent);
                    }

                    if ($password !== '') {
                        $zip->setEncryptionName($wpDBFolderNameInZip . $tableName . ".sql", ZipArchive::EM_AES_256, $password);
                    }
                }

                $offset += $batchSize;
                $batchNumber++;
            } while (!empty($records));

            Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Exporting Records for table: ' . $tableName . ' - completed');
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Table records export exception: ' . $e->getMessage());
            throw new AASM_Export_Exception('Table records export exception:' . $e->getMessage());
        }
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

    private static function addFilesToZip($zip, $folderPath, $wpContentFolderNameInZip, $excludedFolders, $password)
    {
        if (!isset($params['status']['add_files_to_zip'])) {
            $params['status']['add_files_to_zip'] = false;
        }

        // Return if zip archive was already created in previous sessions
        if ($params['status']['add_files_to_zip']) {
            return;
        }
        
        try {
            $iterator = new RecursiveDirectoryIterator($folderPath);
            $filteredElements = [];
            $filterIterator = new RecursiveCallbackFilterIterator($iterator, function ($current, $key, $iterator) use ($excludedFolders, &$filteredElements) {
                return self::filterCallback($current, $excludedFolders, $filteredElements);
            });

            $files = new RecursiveIteratorIterator($filterIterator);
            $cntbatchSize = 100;
            $batchNumber = 1;
            $currentBatchFiles = [];
            $currentFolder = null; // Variable to track the current folder being processed

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($folderPath)+-1);
                    $currentBatchFiles[] = [
                        'path' => $filePath,
                        'relativePath' => $relativePath,
                    ];

                    $folder = $relativePath;
                    if ($currentFolder !== $folder) {
                        $currentFolder = $folder;
                        Azure_app_service_migration_Custom_Logger::logInfo(AASM_EXPORT_SERVICE_TYPE, 'Exporting from wp-content path: ' . $currentFolder);
                    }

                    if (count($currentBatchFiles) >= $cntbatchSize) {
                        self::addFilesToZipBatch($zip, $currentBatchFiles, $wpContentFolderNameInZip, $password, $batchNumber);
                        $batchNumber++;
                        $currentBatchFiles = [];
                    }
                }
            }

            if (!empty($currentBatchFiles)) {
                self::addFilesToZipBatch($zip, $currentBatchFiles, $wpContentFolderNameInZip, $password, $batchNumber);
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Failing to add the file to ZipArchive: ' . $e->getMessage());
            throw new AASM_Archive_Exception('Failing to add the file to ZipArchive: ' . $e->getMessage());
        }
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

    private static function addFilesToZipBatch($zip, $currentBatchFiles, $wpContentFolderNameInZip, $password, $batchNumber)
    {
        try {
            foreach ($currentBatchFiles as $file) {
                $path = $file['path'];
                $relativePath = $file['relativePath'];
                $zip->addFile($path, $wpContentFolderNameInZip . $relativePath);

                if ($password !== '') {
                    $zip->setEncryptionName($wpContentFolderNameInZip . $relativePath, ZipArchive::EM_AES_256, $password);
                }
            }
        } catch (Exception $e) {
            Azure_app_service_migration_Custom_Logger::logError(AASM_EXPORT_SERVICE_TYPE, 'Failing to add the file to ZipArchive during batch: ' . $e->getMessage());
            throw new AASM_Archive_Exception('Failing to add the file to ZipArchive during batch:' . $e->getMessage());
        }
    }
}
?>