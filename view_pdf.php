<?php
if (isset($_GET['file'])) {
    $file = urldecode($_GET['file']);
    $file = basename($file); // Prevent directory traversal

    $filePath = "C:/xampp/uploads/" . $file;

    // Define allowed extensions
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions)) {
        die("File type not allowed.");
    }

    if (file_exists($filePath)) {
        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Decide whether to view inline or force download
        $inlineTypes = ['pdf', 'jpg', 'jpeg', 'png'];
        $disposition = in_array($ext, $inlineTypes) ? 'inline' : 'attachment';

        header("Content-Type: $mimeType");
        header("Content-Disposition: $disposition; filename=\"" . basename($filePath) . "\"");
        readfile($filePath);
        exit;
    } else {
        echo "File not found.";
    }
} else {
    echo "No file specified.";
}
?>