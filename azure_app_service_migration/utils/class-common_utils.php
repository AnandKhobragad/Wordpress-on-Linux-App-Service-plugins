<?php

class AASM_Common_Utils {
   
    public static function generate_random_string_short() {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($characters), 0, 4);
    }

    public static function delete_file($filePath) {
        if (file_exists($filePath)) {
            unlink($filePath); 
        }
    }

    public static function clear_directory_recursive($directoryPath) {
        // Remove trailing '/'
        if (str_ends_with($directoryPath, DIRECTORY_SEPARATOR)) {                                                                      
            $directoryPath = substr($directoryPath, 0, -1);                                                                        
        }
        
        // Retrieve list of files and directories in the directory
        $files = scandir($directoryPath);
      
        // Iterate over each file or directory
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $directoryPath . DIRECTORY_SEPARATOR . $file;
                if (is_file($path)) {
                    unlink($path);
                } elseif (is_dir($path)) {
                    // Recursively clear subdirectory
                    clear_directory_recursive( $path);
                    // Remove empty subdirectory
                    rmdir($path);
                }
            }
        }
        rmdir($directoryPath);
    }

    public static function replace_forward_slash_with_directory_separator ( $dir ) {
        return str_replace("/", DIRECTORY_SEPARATOR, $dir);
    }

    // gets all the callback functions registered to a filter
    public static function get_filter_callbacks($filter_tag) {
        global $wp_filter;

        $filters = array();

        if ( isset( $wp_filter[ $filter_tag ] ) ) {
            $filters = $wp_filter[ $filter_tag ];
            if ( isset( $filters->callbacks ) ) {
                $filters = $filters->callbacks;
            }

            ksort( $filters );
        }
        return $filters;
    }

    public static function http_export_headers( $headers = array() ) {
	
        $user = "";
        $password = "";

        // Set user
        if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
            $user = $SERVER['PHP_AUTH_USER'];
        } elseif ( isset( $_SERVER['REMOTE_USER'] ) ) {
            $user = $_SERVER['REMOTE_USER'];
        }
    
        // Set password
        if ( isset( $_SERVER['PHP_AUTH_PW'] ) ) {
            $password = $_SERVER['PHP_AUTH_PW'];
        }
        
        // Set Authorization header
        if ( ( $hash = base64_encode( sprintf( '%s:%s', $user, $password ) ) ) ) {
            $headers['Authorization'] = sprintf( 'Basic %s', $hash );
        }
        return $headers;
    }	
}