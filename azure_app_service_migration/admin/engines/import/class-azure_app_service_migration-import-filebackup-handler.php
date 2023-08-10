<?php

class Azure_app_service_migration_Import_FileBackupHandler
{
    public static function handle_upload_chunk()
    {
        $param = isset($_POST['param']) ? $_POST['param'] : "";

        if (!empty($param) && $param === "wp_ImportFile_chunks") {
            Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Uploading zip file.', true);
            
            $fileChunk = $_FILES['fileChunk'];
            $uploadDir = AASM_IMPORT_ZIP_LOCATION;
            // Create the directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
                // Set appropriate permissions for the directory (0777 allows read, write, and execute permissions for everyone)
            }

            // Get the latest chunk number in the upload directory
            $latestChunkNumber = self::getLatestChunkNumber($uploadDir);

            // Generate the chunk filename based on the latest chunk number
            $chunkFilename = $uploadDir . 'chunk_' . $latestChunkNumber;

            // Move the uploaded chunk file to the upload directory
            if (move_uploaded_file($fileChunk['tmp_name'], $chunkFilename)) {
                // Chunk uploaded successfully, perform further processing if needed

                // Send a success response
                echo 'Chunk uploaded successfully!';
            } else {
                // Error handling if failed to move the chunk file
                http_response_code(500);
                echo 'Failed to upload chunk.';
            }
        } else {
            // Send an error response
            http_response_code(400);
            echo 'Invalid action parameter.';
        }

        wp_die(); // Terminate the request
    }

    private static function getLatestChunkNumber($uploadDir)
    {
        $latestChunkNumber = 0;
        $counterFilePath = $uploadDir . 'chunk_counter.txt';

        // Check if the counter file exists
        if (file_exists($counterFilePath)) {
            // Read the current chunk number from the counter file
            $currentChunkNumber = intval(file_get_contents($counterFilePath));

            // Calculate the latest chunk number
            $latestChunkNumber = $currentChunkNumber + 1;
        } else {
            // Create the counter file with initial value 0
            file_put_contents($counterFilePath, '0');
            $latestChunkNumber = 0; // Start with chunk number 1
        }

        // Update the counter file with the latest chunk number
        file_put_contents($counterFilePath, $latestChunkNumber);

        return $latestChunkNumber;
    }   

    public static function handle_combine_chunks($params)
    {
        // exit if chunk number is not provided
        if (!isset($params['chunk_index']))
        {
            throw new Exception("Couldn't create zip file. Invalid chunk number provided.");
            return;
        }

        // register start time
        $start = microtime( true );
        $timeout = isset($params['timeout'])
                    ? $params['timeout']
                    : 10;

        $chunkIndex = $params['chunk_index'];

        // Handle the combine chunks action here
        $uploadDir = AASM_IMPORT_ZIP_LOCATION;
        $chunkPrefix = 'chunk_';
        $originalFilename = 'importfile.zip'; // Adjust the original file name

        // Remove the file if it already exists
        $filePath = $uploadDir . $originalFilename;
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
        
        // Create the original file
        $originalFilePath = $uploadDir . $originalFilename;

        // Open the original file in write mode
        $originalFile = fopen($originalFilePath, 'wb');

        $completed = true;
        if ($originalFile !== false) {
            $chunkFile = $uploadDir . $chunkPrefix . $chunkIndex;

            while (file_exists($chunkFile)) {
                Azure_app_service_migration_Custom_Logger::logInfo(AASM_IMPORT_SERVICE_TYPE, 'Extracting chunk file: ' . $chunkFile, true);
                if ( ( microtime( true ) - $start ) > $timeout ) {
					$completed = false;
					break;
				}

                // Read the content of the current chunk file
                $chunkContent = file_get_contents($chunkFile);

                if ($chunkContent !== false) {
                    // Write the chunk content to the original file
                    fwrite($originalFile, $chunkContent);

                    // Delete the chunk file after combining
                    unlink($chunkFile);
                } else {
                    // Error handling if failed to read chunk content
                    http_response_code(500);
                    fclose($originalFile);
                    throw new Exception('Failed to read chunk file: '. $chunkFile);
                }

                $chunkIndex++;
                $chunkFile = $uploadDir . $chunkPrefix . $chunkIndex;
            }
            // Close the original file
            fclose($originalFile);
            
            $params['completed'] = $completed;
            $params['chunk_index'] = $chunkIndex; 
            if ($completed) {
                $counterFilePath = $uploadDir . 'chunk_counter.txt';

                // Update the counter file with the value 0
                file_put_contents($counterFilePath, '-1');

                unset( $params['chunk_index'] );

                // sets enumerate_content as the next function to be executed
                $params['priority'] = 10;
                $params['zip_start_index'] = 0;
            }

            return $params;
            
            // Perform any further actions after combining the chunks
            
            // Create the $params array and assign the value of $retain_w3tc_config_value
            //$params = array(
            //    'retain_w3tc_config' => $retain_w3tc_config_value,
            //);
            // Call the import() method and pass the $params variable
            //Azure_app_service_migration_Import_Controller::import($params, $filePath);
        } else {
            // Error handling if failed to open the original file
            http_response_code(500);
            throw new Exception( 'Failed to open the original file: ' . $originalFilePath );
        }
    }

    public static function delete_chunks($uploadDir)
    {
        $uploadDir = AASM_IMPORT_ZIP_LOCATION;
        $chunkFiles = glob($uploadDir . 'chunk_*');
        foreach ($chunkFiles as $file) {
            unlink($file);
        }
    }

}

new Azure_app_service_migration_Import_FileBackupHandler();
