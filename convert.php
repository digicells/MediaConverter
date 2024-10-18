<?php
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if a file and target format have been uploaded
    if (isset($_FILES['file']) && isset($_POST['target_format'])) {
        $file = $_FILES['file'];
        $targetFormat = strtolower($_POST['target_format']);

        // Allowed formats for input and output
        $allowedFormats = ['jpeg', 'jpg', 'png', 'jfif', 'gif', 'webp', 'svg', 'psd', 'pdf'];

        // Validate file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedFormats)) {
            echo "Unsupported input format!";
            exit;
        }

        // Validate target format
        if (!in_array($targetFormat, $allowedFormats)) {
            echo "Unsupported target format!";
            exit;
        }

        // Temporary upload path
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Move the uploaded file to the server
        $tempFilePath = $uploadDir . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
            echo "File upload failed!";
            exit;
        }

        try {
            // Use Imagick for image processing
            $imagick = new Imagick($tempFilePath);

            // Set the format to the desired target format
            $imagick->setImageFormat($targetFormat);

            // Create the output file path
            $outputFilePath = $uploadDir . pathinfo($file['name'], PATHINFO_FILENAME) . '.' . $targetFormat;

            // Save the converted image
            if ($targetFormat == 'pdf') {
                $imagick->setImageCompressionQuality(100);
            }
            $imagick->writeImage($outputFilePath);

            // Output success message with download link
            echo "Image converted successfully! <a href='$outputFilePath' download>Download Converted Image</a>";
            
            // Clean up Imagick object
            $imagick->clear();
            $imagick->destroy();

            // Optionally, remove the temporary uploaded file
            unlink($tempFilePath);
        } catch (Exception $e) {
            echo "Error during image conversion: " . $e->getMessage();
        }
    } else {
        echo "No file or target format selected!";
    }
} else {
    echo "Invalid request!";
}
?>