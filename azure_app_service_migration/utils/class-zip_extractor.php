<?php
// TO DO: This file will be redundant and needs to be deleted once batch processing is implemented for import
class AASM_Zip_Extractor {
    private $zip_path = null;
    private $file_handle = null;
    private $eof = null;

    public function __construct( $zip_file_name ) {
        $this->zip_path = $zip_file_name;
        
        /*// Open input zip file for reading
        if ( ( $this->file_handle = @fopen( $zip_file_name, 'rb' ) ) === false ) {
            throw new AASM_File_Not_Found_Exception( "File Not Found: Couldn't find file at " . $zip_file_name );
        }*/
    }
    
    public function extract( $destination_dir, $files_to_exclude = [], $zip_start_index ) {
        // reset time counter to prevent timeout
        set_time_limit(0);

        // Start time
		$start = microtime( true );

        $destination_dir = $this->replace_forward_slash_with_directory_separator($destination_dir);
        if ($destination_dir === null) {
            throw new AASM_Archive_Destination_Dir_Exception ('Zip extract error: Target destination not provided.');
        }

        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Reading Zip file for extracting wp-content.', true);
        $zip = new ZipArchive();
        try {
            $zip->open($this->zip_path);
        } catch ( Exception $ex ) {
            throw $ex;
        }
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'finished reading zip file', true);

        // initialize completed flag
        $completed = true;
        
        // total number of files in the zip file
        $zip_num_files = $zip->numFiles;

        // track last zip_entry extracted in this session
        $last_zip_index = $zip_start_index;

        for ($i = $zip_start_index; $i<$zip_num_files; $i++) {
            Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Reading zip file index: ' . strval($i), true);
            // break when timeout (20s) is reached
            if ( ( microtime( true ) - $start ) > 20 ) {
                $last_zip_index = $i;
                $completed = false;
                break;
            }

            $filename = $this->replace_forward_slash_with_directory_separator($zip->getNameIndex($i));
            // remove AASM_IMPORT_ZIP_FILE_NAME prefix in $filename
            if (str_starts_with($filename, AASM_IMPORT_ZIP_FILE_NAME . DIRECTORY_SEPARATOR)) {
                $filename = substr($filename, strlen(AASM_IMPORT_ZIP_FILE_NAME)+1);
            }

            // determine if this file is to be excluded
            $should_exclude_file = false;
            for ( $j = 0; $j < count( $files_to_exclude ); $j++ ) {
                if ( str_starts_with( $filename , $this->replace_forward_slash_with_directory_separator( $files_to_exclude[ $j ] ) )) {
                    $should_exclude_file = true;
                    break;
                }
            }

            if ($should_exclude_file === false) {
                $path_file = $this->replace_forward_slash_with_directory_separator($destination_dir);
                if (str_starts_with($filename, AASM_DATABASE_RELATIVE_PATH_IN_ZIP)) {
                    $path_file = $this->replace_forward_slash_with_directory_separator(AASM_DATABASE_TEMP_DIR);
                }
                
                $new_dir = dirname($path_file . $filename);
                if (!str_ends_with($new_dir, DIRECTORY_SEPARATOR)) {
                    $new_dir .= DIRECTORY_SEPARATOR;
                }
                
                // Create Recursive Directory (if not exist)  
                if (!file_exists($new_dir)) {
                    mkdir($new_dir, 0777, true);
                }
                
                // write only files to new directory
                if ( !str_ends_with($filename, DIRECTORY_SEPARATOR)) {
                    if ( ! $zip->extractTo($path_file, $filename)) {
                        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Failed to extract the file: ' . $filename, true);
                    }
                }
            }
        }

        $zip->close();

        return array (
            'completed' => $completed,
            'last_zip_index' => $last_zip_index,
        );
    }

    public function extract_database_files($dir_to_extract = AASM_DATABASE_RELATIVE_PATH_IN_ZIP, $destination_dir) {
        
        if ($destination_dir === null)
            return;
        
        $dir_to_extract = $this->replace_forward_slash_with_directory_separator($dir_to_extract);
        $destination_dir = $this->replace_forward_slash_with_directory_separator($destination_dir);
        
        // Create Recursive Directory (if not exist)  
        if (!file_exists($destination_dir)) {
            mkdir($destination_dir, 0777, true);
        }
        
        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Reading zip file to extract database tables and records.', true);
        try {
            $zip = zip_open($this->zip_path);
        } catch ( Exception $ex ) {
            Azure_app_service_migration_Custom_Logger::handleException($ex);
        }

        $count=0;
        while ($zip_entry = zip_read($zip))
        {
            // reset time counter to prevent timeout
            set_time_limit(0);
            
            $filename = $this->replace_forward_slash_with_directory_separator(zip_entry_name($zip_entry));

            // remove AASM_IMPORT_ZIP_FILE_NAME prefix in $filename
            if (str_starts_with($filename, AASM_IMPORT_ZIP_FILE_NAME . DIRECTORY_SEPARATOR))
            {
                $filename = substr($filename, strlen(AASM_IMPORT_ZIP_FILE_NAME)+1);
            }

            if (str_starts_with($filename, $dir_to_extract) && str_ends_with($filename, '.sql')) {
                if (zip_entry_open($zip, $zip_entry, "r")) {
                    $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                    $path_file = $destination_dir . basename($filename);
                    $new_dir = dirname($path_file);

                    if (!str_ends_with($new_dir, DIRECTORY_SEPARATOR))
                    {
                        $new_dir .= DIRECTORY_SEPARATOR;
                    }

                    // Create Recursive Directory (if not exist)  
                    if (!file_exists($new_dir)) {
                        mkdir($new_dir, 0777, true);
                    }

                    // write only files to new directory
                    if ( !str_ends_with($path_file, DIRECTORY_SEPARATOR))
                    {
                        $fp = fopen($path_file, "w");
                        fwrite($fp, $buf);
                        fclose($fp);
                    }
                    zip_entry_close($zip_entry);
                }
                
            }
            $count++;
        }

        zip_close($zip);
    }

    public function replace_forward_slash_with_directory_separator ( $dir ) {
        return str_replace("/", DIRECTORY_SEPARATOR, $dir);
    }

    public function escape_windows_directory_separator( $path ) {
        return preg_replace( '/[\\\\]+/', '\\\\\\\\', $path );
    }

}