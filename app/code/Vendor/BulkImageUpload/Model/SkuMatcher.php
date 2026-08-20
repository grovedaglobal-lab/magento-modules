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
            if ($mime !== 'image/jpeg') { $result->addSkippedFile($filename, "Not a JPEG."); continue; }

            $imgSize = @getimagesize($filePath);
            if (!$imgSize || $imgSize[0] < 500 || $imgSize[1] < 500) { $result->addSkippedFile($filename, "Invalid dimension or corrupt."); continue; }
            
            if (preg_match('/^(.+?)(?:_(\d+))?\.jpe?g$/i', $filename, $matches)) {
                $sku = $matches[1];
                $isGallery = isset($matches[2]) && $matches[2] !== '';
                if (!isset($skuGroups[$sku])) { $skuGroups[$sku] = ['base' => null, 'gallery' => []]; $skusToVerify[] = $sku; }
                if ($isGallery) $skuGroups[$sku]['gallery'][] = $filePath;
                else $skuGroups[$sku]['base'] = $filePath;
            } else {
                $result->addSkippedFile($filename, "Does not match SKU format.");
            }
        }

        if (empty($skusToVerify)) return [];

        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('vendor_id')
            ->addFieldToFilter('sku', ['in' => $skusToVerify]);
            
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
            if (!in_array($sku, $allFoundSkus)) { $result->addNotFoundSku($sku); continue; }
            if (!in_array($sku, $ownedSkus)) { $result->addUnauthorizedSku($sku); continue; }
            if (empty($data['base'])) { $result->addSkippedSku($sku, 'No base image.'); continue; }
            $finalGroups[$sku] = $data;
        }

        return $finalGroups;
    }
}
