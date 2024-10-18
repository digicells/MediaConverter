<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload Composer libraries
// At the top of your script
require_once __DIR__ . '/vendor/autoload.php';
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = $_FILES['docFile'];
    $target_format = strtolower($_POST['target_format']); // Ensure lowercase format
    
    // Move the uploaded file to the uploads directory
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $target_dir = "uploads/";

    // Generate new file name based on the target format
    $new_file_name = $target_dir . pathinfo($file_name, PATHINFO_FILENAME) . '.' . $target_format;

    // Check if the upload was successful
    if (move_uploaded_file($file_tmp, $target_dir . $file_name)) {
        switch (true) {
            case ($file_ext == 'txt' && $target_format == 'csv'):
                echo "Converting TXT to CSV...";
                convertTxtToCsv($target_dir . $file_name, $new_file_name);
                break;

            case ($file_ext == 'pdf' && $target_format == 'csv'):
                echo "Converting PDF to CSV...";
                convertPdfToCsv($target_dir . $file_name, $new_file_name);
                break;

            case ($file_ext == 'pdf' && $target_format == 'txt'):
                echo "Converting PDF to TXT...";
                convertPdfToTxt($target_dir . $file_name, $new_file_name);
                break;

            case ($file_ext == 'txt' && $target_format == 'pdf'):
                echo "Converting TXT to PDF...";
                convertTxtToPdf($target_dir . $file_name, $new_file_name);
                break;

            case ($file_ext == 'pdf' && ($target_format == 'docx' || $target_format == 'doc')):
                echo "Converting PDF to DOCX...";
                $new_docx_file_name = $target_dir . pathinfo($file_name, PATHINFO_FILENAME) . '.docx';
                if (convertPdfToDocx($target_dir . $file_name, $new_docx_file_name)) {
                    if ($target_format == 'doc') {
                        rename($new_docx_file_name, $new_file_name); // Rename DOCX to DOC
                    }
                    echo '<br><a href="' . $new_file_name . '" download>Download Converted ' . strtoupper($target_format) . '</a>';
                }
                break;

            case ($file_ext == 'docx' && in_array($target_format, ['txt', 'csv', 'html', 'pdf'])):
                echo "Converting DOCX to " . strtoupper($target_format) . "...";
                convertDocxToOther($target_dir . $file_name, $new_file_name, $target_format);
                break;

            default:
                die("Unsupported file extension: $file_ext or conversion to format: $target_format.");
        }
    } else {
        echo "Failed to move uploaded file.";
    }
}

// Convert TXT to PDF using FPDF
function convertTxtToPdf($source, $destination) {
    $pdf = new \FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    $lines = file($source); // Read the file into an array of lines
    foreach ($lines as $line) {
        $pdf->Cell(0, 10, utf8_decode(trim($line)), 0, 1); // Add each line to the PDF
    }

    $pdf->Output('F', $destination); // Save the PDF to the destination
}

// Convert TXT to CSV
function convertTxtToCsv($source, $destination) {
    $lines = file($source);
    $fp = fopen($destination, 'w');
    foreach ($lines as $line) {
        fputcsv($fp, str_getcsv($line)); // Convert each line to CSV format
    }
    fclose($fp);
}

// Convert PDF to CSV
function convertPdfToCsv($source, $destination) {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($source);
    $text = $pdf->getText();

    $lines = explode("\n", $text);
    $fp = fopen($destination, 'w');
    foreach ($lines as $line) {
        fputcsv($fp, str_getcsv($line));
    }
    fclose($fp);
}

// Convert PDF to TXT
function convertPdfToTxt($source, $destination) {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($source);
    $text = $pdf->getText();
    file_put_contents($destination, $text);
}

// Convert PDF to DOCX
function convertPdfToDocx($source, $destination) {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($source);
    $text = $pdf->getText();

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText($text);

    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($destination);
}

// Convert DOCX to TXT, CSV, HTML, or PDF
function convertDocxToOther($source, $destination, $target_format) {
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($source);
    $textContent = '';

    foreach ($phpWord->getSections() as $section) {
        $elements = $section->getElements();
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $textContent .= $element->getText() . PHP_EOL;
            }
        }
    }

    if ($target_format == 'txt') {
        file_put_contents($destination, $textContent);
    } elseif ($target_format == 'csv') {
        $csvContent = str_replace(PHP_EOL, ',', $textContent);
        file_put_contents($destination, $csvContent);
    } elseif ($target_format == 'html') {
        file_put_contents($destination, "<html><body>$textContent</body></html>");
    } elseif ($target_format == 'pdf') {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, utf8_decode($textContent));
        $pdf->Output('F', $destination);
    }
}
?>
