<?php
namespace Tax\IndianGST\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class VendorTableField extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        $url = $this->getUrl('indiangst/system_config/columnList');

        // IDs of the dependent fields in system config
        $idColId = 'tax_indiangst_marketplace_integration_vendor_id_col';
        $codeColId = 'tax_indiangst_marketplace_integration_vendor_code_col';

        $script = <<<SCRIPT
<script>
    require(['jquery'], function($){
        $('#{$element->getHtmlId()}').change(function(){
            var tableName = $(this).val();
            var url = '{$url}';
            var idCol = $('#{$idColId}');
            var codeCol = $('#{$codeColId}');
            
            if(!tableName) return;
            
            // Show loading state
            var loadingOption = $('<option>', {text: 'Loading...', value: ''});
            
            // Save current value if possible, or just reset? 
            // Better to just load new columns.
            
            $.ajax({
                url: url,
                data: {table: tableName},
                type: 'GET',
                dataType: 'json',
                showLoader: true,
                success: function(data){
                    idCol.empty();
                    codeCol.empty();
                    
                    $.each(data, function(index, option){
                        idCol.append($('<option>', {
                            value: option.value,
                            text: option.label
                        }));
                        codeCol.append($('<option>', {
                            value: option.value,
                            text: option.label
                        }));
                    });
                },
                error: function() {
                     alert('Error fetching columns for table ' + tableName);
                }
            });
        });
    });
</script>
SCRIPT;

        return $html . $script;
    }
}
