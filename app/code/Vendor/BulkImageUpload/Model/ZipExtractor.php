<?php
namespace Vendor\BulkImageUpload\Model;

use Magento\Framework\Filesystem\Io\File;
use ZipArchive;

class ZipExtractor
{
    protected $file;
    public function __construct(File $file) { $this->file = $file; }

    public function extract($zipPath, $destDir, $result = null)
    {
        if (!$this->file->fileExists($destDir, false)) {
            $this->file->mkdir($destDir, 0775);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            if ($result) {
                $result->addGlobalError("Could not open or extract ZIP archive: " . basename($zipPath));
            }
            return [];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extractedFiles = [];
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, '__MACOSX') !== false || strpos($filename, '.DS_Store') !== false) {
                continue;
            }
            if (substr($filename, -1) === '/') {
                continue;
            }

            $baseName = basename($filename);
            $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExtensions)) {
                $destPath = $destDir . DIRECTORY_SEPARATOR . $baseName;
                $fp = $zip->getStream($filename);
                if (!$fp) {
                    if ($result) $result->addSkippedFile($baseName, "Failed to read file from ZIP archive.");
                    continue;
                }
                $outFp = fopen($destPath, 'w');
                if ($outFp) {
                    stream_copy_to_stream($fp, $outFp);
                    fclose($outFp);
                    $extractedFiles[] = $destPath;
                } else {
                    if ($result) $result->addSkippedFile($baseName, "Failed to write extracted file to disk.");
                }
                fclose($fp);
            } else {
                if ($result) {
                    $result->addSkippedFile($baseName, "Unsupported file format (.$ext). Allowed: .jpg, .jpeg, .png, .webp");
                }
            }
        }
        $zip->close();
        return $extractedFiles;
    }
}
