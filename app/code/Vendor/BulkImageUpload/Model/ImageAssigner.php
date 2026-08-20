<?php
namespace Vendor\BulkImageUpload\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Exception;

class ImageAssigner
{
    protected $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function assignImages($sku, $images, $result, $maxImages = 10, $vendorId = null)
    {
        try {
            $product = $this->productRepository->get($sku);

            if ($product->getStatus() == Status::STATUS_DISABLED) {
                $result->addSkippedSku($sku, "Product is disabled.");
                return;
            }

            if ($vendorId !== null) {
                $productVendorId = (int)$product->getData('vendor_id');
                if ($productVendorId !== 0 && $productVendorId !== (int)$vendorId) {
                    $result->addUnauthorizedSku($sku);
                    return;
                }
            }

            $newCount = 1 + count($images['gallery']);
            if ($newCount > $maxImages) {
                $result->addExceededSku($sku, $newCount, $maxImages);
                $images['gallery'] = array_slice($images['gallery'], 0, $maxImages - 1);
            }

            $existingMedia = $product->getMediaGalleryEntries();
            if ($existingMedia) {
                $product->setMediaGalleryEntries([]);
            }

            if (!empty($images['base']) && file_exists($images['base'])) {
                $product->addImageToMediaGallery($images['base'], ['image', 'small_image', 'thumbnail'], false, false);
            }

            if (!empty($images['gallery'])) {
                foreach ($images['gallery'] as $galleryPath) {
                    if (file_exists($galleryPath)) {
                        $product->addImageToMediaGallery($galleryPath, [], false, false);
                    }
                }
            }

            $this->productRepository->save($product);
            $result->incrementSuccess();
        } catch (Exception $e) {
            $result->addFailure($sku, "Error: " . $e->getMessage());
        }
    }
}