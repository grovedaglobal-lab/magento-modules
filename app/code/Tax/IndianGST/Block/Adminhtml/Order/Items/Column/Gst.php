<?php
namespace Tax\IndianGST\Block\Adminhtml\Order\Items\Column;

use Magento\Sales\Block\Adminhtml\Items\Column\DefaultColumn;

class Gst extends DefaultColumn
{
    public function getHeader()
    {
        return $this->getData('header');
    }

    public function getGstAmount()
    {
        $item = $this->getItem();
        $type = $this->getData('gst_type');
        
        $key = 'indiangst_' . $type . '_amount';
        return (float)$item->getData($key);
    }
    
    public function getGstRate()
    {
        $item = $this->getItem();
        $totalRate = (float)$item->getData('indiangst_percent');
        $type = $this->getData('gst_type');
        
        // Return 0 if no amount for this type to avoid confusion, 
        // OR always show potential rate?
        // Screenshot shows 0.0000% if amount is 0.00
        
        $amount = $this->getGstAmount();
        if ($amount <= 0.001) {
            return 0.0;
        }

        if ($type == 'igst') {
             return $totalRate;
        } else {
             return $totalRate / 2;
        }
    }
}
