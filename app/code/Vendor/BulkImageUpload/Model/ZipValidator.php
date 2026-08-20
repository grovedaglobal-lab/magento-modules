<?php
namespace Vendor\BulkImageUpload\Model;

use Exception;
use ZipArchive;

class ZipValidator
{
    private $maxSize = 1073741824; // 1 GB in bytes
    private $allowedExtensions = ['jpg', 'jpeg', 'png'];
    private $dangerousExtensions = ['php', 'phtml', 'js', 'sh', 'exe'];
    private $maxFiles = 1000;

    public function validate($filePath)
    {
        $errors = [];
        if (!file_exists($filePath)) return ['isValid' => false, 'errors' => ['File not found.']];
        if (filesize($filePath) > $this->maxSize) $errors[] = "File exceeds max allowed size of 1GB.";

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return ['isValid' => false, 'errors' => ['Not a valid ZIP archive.']];
        
        if ($zip->numFiles === 0) {
            $errors[] = "ZIP is empty.";
        } elseif ($zip->numFiles > $this->maxFiles) {
            $errors[] = "ZIP contains more than $this->maxFiles files.";
        }

        $dangerousFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'];
            
            if (strpos($filename, '__MACOSX') !== false || strpos($filename, '.DS_Store') !== false) continue;

            if (strpos($filename, '/') !== false && trim($filename, '/') !== $filename) {
                if (substr($filename, -1) !== '/') {
                    $errors[] = "ZIP must contain images at root level only.";
                    break;
                }
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext && in_array($ext, $this->dangerousExtensions)) $dangerousFiles[] = $filename;
        }

        if (!empty($dangerousFiles)) $errors[] = "Unsafe files detected: " . implode(', ', $dangerousFiles);
        $zip->close();

        return ['isValid' => empty($errors), 'errors' => $errors];
    }
}
