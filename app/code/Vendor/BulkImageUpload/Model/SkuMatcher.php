<?php
namespace Vendor\BulkImageUpload\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class SkuMatcher
{
    protected $productCollectionFactory;

    public function __construct(ProductCollectionFactory $productCollectionFactory)
    {
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function matchAndValidate($extractedFiles, $vendorId, $result)
    {
        $skuGroups = [];
        $skusToVerify = [];

        foreach ($extractedFiles as $filePath) {
            $filename = basename($filePath);
            $mime = @mime_content_type($filePath);
            $allowedMimes = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) {
                $result->addSkippedFile($filename, "Unsupported image type (" . ($mime ?: 'unknown') . "). Allowed: JPEG, PNG, WEBP.");
                continue;
            }

            $imgSize = @getimagesize($filePath);
            if (!$imgSize || $imgSize[0] < 500 || $imgSize[1] < 500) {
                $dims = ($imgSize && isset($imgSize[0], $imgSize[1])) ? "{$imgSize[0]}x{$imgSize[1]}px" : "unknown";
                $result->addSkippedFile($filename, "Image dimensions ({$dims}) too small or corrupt. Minimum 500x500px required.");
                continue;
            }
            
            if (preg_match('/^(.+?)(?:_(\d+))?\.(?:jpe?g|png|webp)$/i', $filename, $matches)) {
                $sku = $matches[1];
                $isGallery = isset($matches[2]) && $matches[2] !== '';
                if (!isset($skuGroups[$sku])) {
                    $skuGroups[$sku] = ['base' => null, 'gallery' => []];
                    $skusToVerify[] = $sku;
                }
                if ($isGallery) {
                    $skuGroups[$sku]['gallery'][] = $filePath;
                } else {
                    $skuGroups[$sku]['base'] = $filePath;
                }
            } else {
                $result->addSkippedFile($filename, "Filename does not match SKU format (expected <SKU>.jpg or <SKU>_1.jpg).");
            }
        }

        if (empty($skusToVerify)) {
            return [];
        }

        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('vendor_id')
            ->addFieldToFilter('sku', ['in' => array_unique($skusToVerify)]);
            
        $ownedSkus = [];
        $allFoundSkus = [];
        foreach ($collection as $product) {
            $sku = $product->getSku();
            $allFoundSkus[] = $sku;
            if ((int)$product->getData('vendor_id') === (int)$vendorId) {
                $ownedSkus[] = $sku;
            }
        }

        $finalGroups = [];
        foreach ($skuGroups as $sku => $data) {
            if (!in_array($sku, $allFoundSkus)) {
                $result->addNotFoundSku($sku);
                continue;
            }
            if (!in_array($sku, $ownedSkus)) {
                $result->addUnauthorizedSku($sku);
                continue;
            }
            if (empty($data['base'])) {
                $result->addSkippedSku($sku, "No base image found (e.g. '{$sku}.jpg' is required as the main image).");
                continue;
            }
            $finalGroups[$sku] = $data;
        }

        return $finalGroups;
    }
}
