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
    
    public function extract( $destination_dir, $files_to_exclude = [], $zip_entry_starting_point ) {
        // reset time counter to prevent timeout
        set_time_limit(0);

        $destination_dir = $this->replace_forward_slash_with_directory_separator($destination_dir);
        if ($destination_dir === null) {
            throw new AASM_Archive_Destination_Dir_Exception ('Zip extract error: Target destination not provided.');
        }

        Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Reading Zip file for extracting wp-content.', true);
        try {
            $zip = zip_open($this->zip_path);
        } catch ( Exception $ex ) {
            Azure_app_service_migration_Custom_Logger::handleException($ex);
        }

        // Defines if the current file is to be skipped
        // A file is skipped if it has been extracted previously
        $skip_file = true;

        // total number of files in the zip file
        $totalEntries = $zip->numFiles;

        // track last zip_entry extracted in this session
        $last_zip_entry = null;

        // maintain counter of zip_entry objects
        $count=0;
        while ($zip_entry = zip_read($zip)) {
            // break when timeout (20s) is reached
            if ( ( microtime( true ) - $start ) > 20 ) {
                
                // Throw exception if starting point could not be reached in 20s 
                if ( $skip_file ) {
                    throw new Exception('Failed to extract wp-content... Infinite loop encountered.');
                }

                $last_zip_entry = zip_entry_name($zip_entry);
                $completed = false;
                break;
            }
            $count++;

            // if zip_entry_starting_point is null, assign it to the first instance of zip_entry
            // This ensures we start extracting from beginning of zip file
            if (is_null($zip_entry_starting_point) && $count === 1)
                $zip_entry_starting_point = zip_entry_name($zip_entry);
            
            // Start extracting when zip_entry_starting_point is encountered
            if ( zip_entry_name($zip_entry) === $zip_entry_starting_point ) {
                $skip_file = false;
            }

            // continue to next zip_entry if the current one has been extracted in previous attempts
            if ( $skip_file )
                continue;
            
            $filename = $this->replace_forward_slash_with_directory_separator(zip_entry_name($zip_entry));
            // remove AASM_IMPORT_ZIP_FILE_NAME prefix in $filename
            if (str_starts_with($filename, AASM_IMPORT_ZIP_FILE_NAME . DIRECTORY_SEPARATOR)) {
                $filename = substr($filename, strlen(AASM_IMPORT_ZIP_FILE_NAME)+1);
            }

            // determine if this file is to be excluded
            $should_exclude_file = false;
            for ( $i = 0; $i < count( $files_to_exclude ); $i++ ) {
                if ( str_starts_with( $filename , $this->replace_forward_slash_with_directory_separator( $files_to_exclude[ $i ] ) )) {
                    $should_exclude_file = true;
                    break;
                }
            }

            // extract only wp-content files
            if(!str_starts_with($filename, 'wp-content' . DIRECTORY_SEPARATOR))
                $should_exclude_file = true;
            
            if ($should_exclude_file === false && zip_entry_open($zip, $zip_entry, "r")) {
                $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                $path_file = $this->replace_forward_slash_with_directory_separator($destination_dir . $filename);
                $new_dir = dirname($path_file);

                if (!str_ends_with($new_dir, DIRECTORY_SEPARATOR)) {
                    $new_dir .= DIRECTORY_SEPARATOR;
                }
                
                // Create Recursive Directory (if not exist)  
                if (!file_exists($new_dir)) {
                    mkdir($new_dir, 0777, true);
                }
                
                // write only files to new directory
                if ( !str_ends_with($path_file, DIRECTORY_SEPARATOR)) {
                    $fp = fopen($path_file, "w");
                    fwrite($fp, $buf);
                    fclose($fp);
                }
                
                zip_entry_close($zip_entry);            
            }
        }

        zip_close($zip);

        return array (
            'completed' => $completed,
            'last_zip_entry' => $last_zip_entry,
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