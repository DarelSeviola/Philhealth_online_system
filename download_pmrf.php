<?php
// Absolute file path
$file = __DIR__ . "/assets/forms/PhilHealth_PMRF_Form.pdf";

// Check if file exists
if (!file_exists($file)) {
    http_response_code(404);
    exit("File not found.");
}

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers to force download
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="PhilHealth_PMRF_Form.pdf"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output file
readfile($file);
exit;