<?php
namespace Vendor\BulkImageUpload\Model;

use Magento\Framework\Filesystem\Io\File;
use ZipArchive;

class ZipExtractor
{
    protected $file;
    public function __construct(File $file) { $this->file = $file; }

    public function extract($zipPath, $destDir)
    {
        if (!$this->file->fileExists($destDir, false)) $this->file->mkdir($destDir, 0775);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new \Exception("Could not open ZIP.");

        $extractedFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, '__MACOSX') !== false || strpos($filename, '.DS_Store') !== false) continue;

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg'])) {
                $destPath = $destDir . DIRECTORY_SEPARATOR . basename($filename);
                $fp = $zip->getStream($filename);
                if (!$fp) continue;
                $outFp = fopen($destPath, 'w');
                stream_copy_to_stream($fp, $outFp);
                fclose($fp);
                fclose($outFp);
                $extractedFiles[] = $destPath;
            }
        }
        $zip->close();
        return $extractedFiles;
    }
}
