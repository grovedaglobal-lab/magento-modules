<?php
namespace Vendor\Marketplace\Model\File;

use Magento\Framework\File\Uploader as MagentoUploader;

class Uploader extends MagentoUploader
{
    /**
     * Override to add WebP support
     * 
     * @return bool
     */
    protected function _validateImageType()
    {
        // Get the file extension
        $extension = strtolower(pathinfo($this->_file['name'], PATHINFO_EXTENSION));

        // If it's a WebP file, skip the strict MIME type validation
        if ($extension === 'webp') {
            // Check if the file is actually a WebP file by reading its header
            $handle = fopen($this->_file['tmp_name'], 'rb');
            if ($handle) {
                $header = fread($handle, 12);
                fclose($handle);

                // WebP file signature: RIFF....WEBP
                if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
                    return true;
                }
            }
            return false;
        }

        // For other image types, use the parent validation
        return parent::_validateImageType();
    }
}
