<?php
namespace Tax\IndianGST\Model\Order\Pdf;

class Invoice extends \Magento\Sales\Model\Order\Pdf\Invoice
{
    /**
     * Get GST Helper
     * 
     * @return \Tax\IndianGST\Helper\Data
     */
    protected function getGstHelper()
    {
        return \Magento\Framework\App\ObjectManager::getInstance()->get(\Tax\IndianGST\Helper\Data::class);
    }

    /**
     * Insert order to pdf page
     *
     * @param \Zend_Pdf_Page $page
     * @param \Magento\Sales\Model\Order $obj
     * @param bool $putOrderId
     * @return void
     */
    protected function insertOrder(&$page, $obj, $putOrderId = true)
    {
        if ($putOrderId) {
            $this->_setFontRegular($page, 10);
            $page->drawText(__('Order # ') . $obj->getRealOrderId(), 35, 780, 'UTF-8');
            $page->drawText(
                __('Date: ') .
                $this->_localeDate->formatDate(
                    $this->_localeDate->scopeDate(
                        $obj->getStore(),
                        $obj->getCreatedAt(),
                        true
                    ),
                    \IntlDateFormatter::MEDIUM,
                    false
                ),
                35,
                765,
                'UTF-8'
            );
        }
    }

    /**
     * Draw header for item table
     *
     * @param \Zend_Pdf_Page $page
     * @return void
     */
    protected function _drawHeader(\Zend_Pdf_Page $page)
    {
        /* Add table head */
        $this->_setFontRegular($page, 10);
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(0.93, 0.92, 0.92));
        $page->setLineColor(new \Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $this->y, 570, $this->y - 15);
        $this->y -= 10;
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(0, 0, 0));

        //columns headers
        $lines[0][] = ['text' => __('Products'), 'feed' => 35];
        $lines[0][] = ['text' => __('Price'), 'feed' => 205, 'align' => 'right'];
        $lines[0][] = ['text' => __('Qty'), 'feed' => 245, 'align' => 'right'];
        $lines[0][] = ['text' => __('CGST'), 'feed' => 295, 'align' => 'right'];
        $lines[0][] = ['text' => __('SGST'), 'feed' => 355, 'align' => 'right'];
        $lines[0][] = ['text' => __('IGST'), 'feed' => 415, 'align' => 'right'];
        $lines[0][] = ['text' => __('Row Total'), 'feed' => 565, 'align' => 'right'];

        $lineBlock = ['lines' => $lines, 'height' => 5];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->y -= 20;
    }

    /**
     * Group items by vendor
     * 
     * @param \Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order $entity
     * @return array
     */
    protected function _getVendorGroups($entity)
    {
        $groups = [];
        foreach ($entity->getAllItems() as $item) {
            if ($item instanceof \Magento\Sales\Model\Order\Invoice\Item) {
                $orderItem = $item->getOrderItem();
            } else {
                $orderItem = $item;
            }

            if ($orderItem->getParentItem()) {
                continue;
            }

            $vId = $orderItem->getProduct()->getData('vendor_id') ?: 'admin';
            $groups[$vId][] = $item;
        }
        return $groups;
    }

    /**
     * Create PDF for invoice
     * 
     * @param array $invoices
     * @return \Zend_Pdf
     */
    public function getPdf($invoices = [])
    {
        $pdf = parent::getPdf($invoices);

        $i = 0;
        $resource = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('vendor_profile');
        $gstTableName = $resource->getTableName('tax_indiangst_vendor_profile');

        foreach ($invoices as $invoice) {
            $page = $pdf->pages[$i];

            // Draw Logo
            $this->insertLogo($page, $invoice->getStore());

            // Add "Tax Invoice" Label at top Right
            $this->_setFontBold($page, 16);
            $page->drawText(__('Tax Invoice/Bill of Supply/Cash Memo'), 275, 815, 'UTF-8');

            $this->_setFontRegular($page, 8);
            $page->drawText(__('Original for Recipient'), 480, 800, 'UTF-8');

            // --- Determine Seller for this specific Invoice ---
            $vendorId = null;
            foreach ($invoice->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem())
                    continue;
                $vendorId = $item->getOrderItem()->getProduct()->getData('vendor_id');
                if ($vendorId)
                    break;
            }

            if ($vendorId) {
                $select = $connection->select()
                    ->from(['vp' => $tableName])
                    ->joinLeft(
                        ['gst' => $gstTableName],
                        'vp.vendor_id = gst.vendor_code',
                        ['gstin', 'pan_number', 'business_name']
                    )
                    ->where('vp.vendor_id = ?', $vendorId);
                $vendorData = $connection->fetchRow($select);

                if ($vendorData) {
                    $y = 780;
                    $this->_setFontBold($page, 10);
                    $page->drawText(__('Sold By:'), 25, $y, 'UTF-8');
                    $y -= 12;
                    $this->_setFontBold($page, 9);
                    $page->drawText($vendorData['business_name'] ?: ($vendorData['shop_name'] ?: 'N/A'), 25, $y, 'UTF-8');
                    $y -= 10;
                    $this->_setFontRegular($page, 8);

                    if (!empty($vendorData['address'])) {
                        $page->drawText($vendorData['address'], 25, $y, 'UTF-8');
                        $y -= 10;
                    }
                    $location = [];
                    if (!empty($vendorData['city']))
                        $location[] = $vendorData['city'];
                    if (!empty($vendorData['state']))
                        $location[] = $vendorData['state'];
                    $locationText = implode(', ', $location);
                    if (!empty($vendorData['zip_code']))
                        $locationText .= ' - ' . $vendorData['zip_code'];

                    if ($locationText) {
                        $page->drawText($locationText, 25, $y, 'UTF-8');
                        $y -= 10;
                    }

                    if ($vendorData['gstin']) {
                        $page->drawText(__('GSTIN: %1', $vendorData['gstin']), 25, $y, 'UTF-8');
                        $y -= 10;
                    }
                    if ($vendorData['pan_number']) {
                        $page->drawText(__('PAN: %1', $vendorData['pan_number']), 25, $y, 'UTF-8');
                    }
                }
            } else {
                $this->_setFontBold($page, 10);
                $page->drawText(__('Sold By: Admin/Default'), 25, 780, 'UTF-8');
            }

            $i++;
        }

        return $pdf;
    }

    /**
     * Override insert items to group by vendor visually in the table
     */
    protected function _insertItems($page, $items, $order)
    {
        $vendorGroups = $this->_getVendorGroups($order);
        foreach ($vendorGroups as $vId => $vItems) {
            // Draw a separator/header for vendor
            $this->_setFontItalic($page, 8);
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.3));

            $vendorName = "Seller: Default";
            if ($vId !== 'admin') {
                $resource = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
                $tableName = $resource->getTableName('vendor_profile');
                $vName = $resource->getConnection()->fetchOne(
                    $resource->getConnection()->select()->from($tableName, 'shop_name')->where('vendor_id = ?', $vId)
                );
                if ($vName)
                    $vendorName = "Seller: " . $vName;
            }

            $page->drawText($vendorName, 35, $this->y, 'UTF-8');
            $this->y -= 10;
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));

            foreach ($vItems as $item) {
                // We need to match the item from the provided $items array to get the correct totals
                $matchingItem = null;
                foreach ($items as $pItem) {
                    if ($pItem->getOrderItemId() == $item->getId() || $pItem->getId() == $item->getId()) {
                        $matchingItem = $pItem;
                        break;
                    }
                }

                if ($matchingItem) {
                    $this->_drawItem($matchingItem, $page, $order);
                }
            }
        }
    }

    /**
     * Insert totals to multi-invoice pdf and add amount in words + signature
     */
    protected function _insertTotals($page, $total)
    {
        $page = parent::_insertTotals($page, $total);

        $label = $total->getLabel();
        if (strpos(strtolower((string) $label), 'grand total') !== false) {
            $amount = $total->getAmount();
            $words = $this->getGstHelper()->amountToWords($amount);

            $this->y -= 15;
            $this->_setFontBold($page, 10);
            $page->drawText(__('Total Amount in Words:'), 25, $this->y, 'UTF-8');
            $this->y -= 12;
            $this->_setFontItalic($page, 10);

            $wrapWords = $this->string->split($words, 100, true, true);
            foreach ($wrapWords as $wordLine) {
                $page->drawText($wordLine, 25, $this->y, 'UTF-8');
                $this->y -= 12;
            }

            // Fetch Vendor Data for THIS specific invoice's items
            $invoice = null;
            // Hack to find current invoice? Usually $total->getInvoice() might work or we use registry
            $vendorId = null;
            $items = $total->getOrder()->getAllItems(); // Fallback to order if invoice not direct
            foreach ($items as $item) {
                $vendorId = $item->getProduct()->getData('vendor_id');
                if ($vendorId)
                    break;
            }

            if ($vendorId) {
                $resource = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
                $connection = $resource->getConnection();
                $tableName = $resource->getTableName('vendor_profile');
                $vendorData = $connection->fetchRow(
                    $connection->select()->from($tableName)->where('vendor_id = ?', $vendorId)
                );

                if ($vendorData) {
                    $authorizedName = $vendorData['authorized_name'] ?: '';
                    $signatureImage = $vendorData['signature'] ?: '';

                    $this->y -= 10;
                    if ($signatureImage) {
                        try {
                            $mediaPath = \Magento\Framework\App\ObjectManager::getInstance()
                                ->get(\Magento\Framework\App\Filesystem\DirectoryList::class)
                                ->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
                            $fullPath = $mediaPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'signature' . DIRECTORY_SEPARATOR . ltrim($signatureImage, DIRECTORY_SEPARATOR);

                            if (file_exists($fullPath)) {
                                $image = \Zend_Pdf_Image::imageWithPath($fullPath);
                                $page->drawImage($image, 430, $this->y - 30, 530, $this->y + 10);
                                $this->y -= 40;
                            }
                        } catch (\Exception $e) {
                        }
                    } else {
                        $this->y -= 25;
                    }

                    $this->_setFontRegular($page, 10);
                    $page->drawText(__('Authorized Signatory (%1)', $vendorData['shop_name']), 430, $this->y, 'UTF-8');
                    if ($authorizedName) {
                        $this->y -= 12;
                        $page->drawText($authorizedName, 430, $this->y, 'UTF-8');
                    }
                    $page->drawLine(430, $this->y + 15 + ($signatureImage ? 40 : 0), 550, $this->y + 15 + ($signatureImage ? 40 : 0));
                }
            }
        }

        return $page;
    }
}
