<?php
declare(strict_types=1);

namespace Tax\IndianGST\Block\Adminhtml\Vendor\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        $id = $this->getModelId();
        if ($id) {
            return [
                'label' => __('Delete Vendor'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to do this?'
                ) . '\', \'' . $this->getUrl('*/*/delete', ['entity_id' => $id]) . '\')',
                'sort_order' => 20,
            ];
        }
        return [];
    }
}
