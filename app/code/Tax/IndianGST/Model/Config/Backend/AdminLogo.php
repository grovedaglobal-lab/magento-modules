<?php
namespace Tax\IndianGST\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Image;

/**
 * Backend model for the Admin Invoice Logo image upload field.
 * Stores the logo under pub/media/tax/indiangst/admin_logo/.
 */
class AdminLogo extends Image
{
    /**
     * Upload sub-directory inside pub/media.
     */
    const UPLOAD_DIR = 'tax/indiangst/admin_logo';

    /**
     * @inheritdoc
     */
    protected function _getUploadDir(): string
    {
        return $this->_mediaDirectory->getAbsolutePath(self::UPLOAD_DIR);
    }

    /**
     * Do not prepend scope info to filename — keep paths simple.
     *
     * @inheritdoc
     */
    protected function _addWhetherScopeInfo(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function _getAllowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'gif', 'png', 'svg'];
    }
}