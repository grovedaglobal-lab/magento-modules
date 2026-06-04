<?php
namespace Tax\IndianGST\Model\Order\Pdf;

class Creditmemo extends \Magento\Sales\Model\Order\Pdf\Creditmemo
{
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

        // GST Columns
        $lines[0][] = ['text' => __('CGST'), 'feed' => 295, 'align' => 'right'];
        $lines[0][] = ['text' => __('SGST'), 'feed' => 355, 'align' => 'right'];
        $lines[0][] = ['text' => __('IGST'), 'feed' => 415, 'align' => 'right'];

        $lines[0][] = ['text' => __('Row Total'), 'feed' => 565, 'align' => 'right'];

        $lineBlock = ['lines' => $lines, 'height' => 5];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->y -= 20;
    }
}
