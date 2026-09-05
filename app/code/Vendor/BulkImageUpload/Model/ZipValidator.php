<?php
namespace Vendor\BulkImageUpload\Model;

use Exception;
use ZipArchive;

class ZipValidator
{
    private $maxSize = 1073741824; // 1 GB in bytes
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    private $dangerousExtensions = ['php', 'phtml', 'js', 'sh', 'exe'];
    private $maxFiles = 1000;

    public function validate($filePath)
    {
        $errors = [];
        if (!file_exists($filePath)) {
            return ['isValid' => false, 'errors' => ['File not found on server.']];
        }
        if (filesize($filePath) > $this->maxSize) {
            $errors[] = "ZIP file exceeds maximum allowed size of 1GB.";
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return ['isValid' => false, 'errors' => ['The file is not a valid ZIP archive or is corrupted.']];
        }
        
        if ($zip->numFiles === 0) {
            $errors[] = "The ZIP archive is empty.";
        } elseif ($zip->numFiles > $this->maxFiles) {
            $errors[] = "The ZIP archive contains more than {$this->maxFiles} files.";
        }

        $invalidFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'];
            
            if (strpos($filename, '__MACOSX') !== false || strpos($filename, '.DS_Store') !== false) continue;

            // Zip Slip / Directory Traversal Check
            if (strpos($filename, '..') !== false) {
                $errors[] = "ZIP archive contains unsafe path traversal characters.";
                break;
            }

            if (substr($filename, -1) === '/') continue; // allow directory entries

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!$ext || !in_array($ext, $this->allowedExtensions)) {
                $invalidFiles[] = $filename;
            }
        }

        if (!empty($invalidFiles)) {
            $errors[] = "Only image files (.jpg, .jpeg, .png, .webp) are allowed inside the ZIP archive. Found unsupported files: " . implode(', ', array_slice($invalidFiles, 0, 5));
        }
        $zip->close();

        return ['isValid' => empty($errors), 'errors' => $errors];
    }
}
