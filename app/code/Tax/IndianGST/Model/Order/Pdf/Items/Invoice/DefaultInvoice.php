<?php
namespace Tax\IndianGST\Model\Order\Pdf\Items\Invoice;

use Magento\Sales\Model\Order\Pdf\Items\AbstractItems;

class DefaultInvoice extends AbstractItems
{
    /**
     * Draw item line
     *
     * @return void
     */
    public function draw()
    {
        $order = $this->getOrder();
        $item = $this->getItem();
        $pdf = $this->getPdf();
        $page = $this->getPage();
        $lines = [];

        // --- Data Extraction ---
        $cgst = (float) $item->getData('indiangst_cgst_amount');
        $sgst = (float) $item->getData('indiangst_sgst_amount');
        $igst = (float) $item->getData('indiangst_igst_amount');
        $percent = (float) $item->getData('indiangst_percent');

        $cgstRate = ($cgst > 0) ? ($percent / 2) : 0;
        $sgstRate = ($sgst > 0) ? ($percent / 2) : 0;
        $igstRate = ($igst > 0) ? $percent : 0;

        // --- Column 1: Product Name, SKU, HSN ---
        // We wrap text at 140 width
        $nameText = $this->string->split($item->getName(), 35, true, true);
        $skuText = 'SKU: ' . $this->getSku($item);

        $lines[0] = [['text' => $nameText, 'feed' => 35]];
        // Add SKU on new line logic below (handled by standard drawLineBlocks if passed as array)
        // But drawLineBlocks is dumb, we construct $lines manually.

        // Let's modify lines[0] to have multi-line text for product
        // Actually, $lines[0][] = array puts it in the first line block.
        // We want SKU to appear below name.

        // Simpler: Just append SKU to the text array
        $productColumn = $nameText; // Array of lines
        $productColumn[] = $skuText;

        // Get HSN
        $hsn = $item->getOrderItem()->getProduct()->getData('hsn_code');
        if (!$hsn) {
            // Try from tax_indiangst_rates table if not on product
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('tax_indiangst_rates');
            $taxClassId = $item->getOrderItem()->getProduct()->getTaxClassId();
            $hsn = $connection->fetchOne(
                $connection->select()->from($tableName, ['hsn_code'])->where('tax_class_id = ?', $taxClassId)
            );
        }

        if ($hsn) {
            $productColumn[] = 'HSN/SAC: ' . $hsn;
        }

        $lines[0][0] = ['text' => $productColumn, 'feed' => 35];

        // --- Column 2: Price ---
        $lines[0][] = [
            'text' => $order->formatPriceTxt($item->getPrice()),
            'feed' => 205,
            'align' => 'right'
        ];

        // --- Column 3: Qty ---
        $lines[0][] = [
            'text' => $item->getQty() * 1,
            'feed' => 245,
            'align' => 'right'
        ];

        // --- Column 4: CGST ---
        $cgstText = [];
        if ($cgst > 0.001) {
            $cgstText[] = $order->formatPriceTxt($cgst);
            $cgstText[] = '(' . $cgstRate . '%)';
        } else {
            $cgstText[] = $order->formatPriceTxt(0);
        }
        $lines[0][] = [
            'text' => $cgstText,
            'feed' => 295,
            'align' => 'right'
        ];

        // --- Column 5: SGST ---
        $sgstText = [];
        if ($sgst > 0.001) {
            $sgstText[] = $order->formatPriceTxt($sgst);
            $sgstText[] = '(' . $sgstRate . '%)';
        } else {
            $sgstText[] = $order->formatPriceTxt(0);
        }
        $lines[0][] = [
            'text' => $sgstText,
            'feed' => 355,
            'align' => 'right'
        ];

        // --- Column 6: IGST ---
        $igstText = [];
        if ($igst > 0.001) {
            $igstText[] = $order->formatPriceTxt($igst);
            $igstText[] = '(' . $igstRate . '%)';
        } else {
            $igstText[] = $order->formatPriceTxt(0);
        }
        $lines[0][] = [
            'text' => $igstText,
            'feed' => 415,
            'align' => 'right'
        ];

        // --- Column 7: Row Total ---
        $lines[0][] = [
            'text' => $order->formatPriceTxt($item->getRowTotal()),
            'feed' => 565,
            'align' => 'right'
        ];

        // Draw
        $lineBlock = ['lines' => $lines, 'height' => 20];
        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $this->setPage($page);
    }
}
