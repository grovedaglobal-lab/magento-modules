<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Commission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Save extends Action
{
    /**
     * @var \Vendor\Marketplace\Model\VendorCommissionFactory
     */
    protected $vendorCommissionFactory;

    /**
     * @param Context $context
     * @param \Vendor\Marketplace\Model\VendorCommissionFactory $vendorCommissionFactory
     */
    public function __construct(
        Context $context,
        \Vendor\Marketplace\Model\VendorCommissionFactory $vendorCommissionFactory
    ) {
        $this->vendorCommissionFactory = $vendorCommissionFactory;
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            try {
                // Fix for UI Component sending array for category_id
                if (isset($data['category_id']) && is_array($data['category_id'])) {
                    $categoryId = implode(',', $data['category_id']);
                    if (strpos($categoryId, ',') !== false) {
                        $parts = explode(',', $categoryId);
                        $categoryId = $parts[0];
                    }
                } else {
                    $categoryId = $data['category_id'] ?? null;
                }

                if (!$categoryId) {
                    throw new \Exception(__('Category is required.'));
                }

                $priceRanges = $data['price_ranges'] ?? [];

                // Debug Log
                file_put_contents(BP . '/var/log/commission_save.log', date('Y-m-d H:i:s') . " POST Data: " . print_r($data, true) . "\n", FILE_APPEND);

                if (isset($priceRanges['price_ranges'])) {
                    $priceRanges = $priceRanges['price_ranges'];
                } elseif (empty($priceRanges) && isset($data['data']['price_ranges'])) {
                    $priceRanges = $data['data']['price_ranges'];
                }

                file_put_contents(BP . '/var/log/commission_save.log', date('Y-m-d H:i:s') . " saving for category $categoryId: " . count($priceRanges) . " ranges\n", FILE_APPEND);

                $lastSavedId = null;

                foreach ($priceRanges as $rangeData) {
                    if (isset($rangeData['is_delete']) && $rangeData['is_delete'] == '1') {
                        if (isset($rangeData['entity_id']) && !empty($rangeData['entity_id'])) {
                            $model = $this->vendorCommissionFactory->create()->load($rangeData['entity_id']);
                            if ($model->getId()) {
                                $model->delete();
                            }
                        }
                        continue;
                    }

                    $model = $this->vendorCommissionFactory->create();
                    if (isset($rangeData['entity_id']) && !empty($rangeData['entity_id'])) {
                        $model->load($rangeData['entity_id']);
                    }

                    // Skip invalid rows if somehow frontend validation is bypassed or dynamic rows structure sends empty proto object
                    if (!isset($rangeData['min_price']) || $rangeData['min_price'] === '') {
                        continue;
                    }

                    $rangeSaveData = [
                        'category_id' => $categoryId,
                        'min_price' => $rangeData['min_price'],
                        'max_price' => $rangeData['max_price'] ?? null,
                        'commission_percent' => $rangeData['commission_percent'] ?? 0
                    ];

                    // Handle empty max_price as null for infinite
                    if (isset($rangeSaveData['max_price']) && trim($rangeSaveData['max_price'] ?? '') === '') {
                        $rangeSaveData['max_price'] = null;
                    }

                    $model->setData($rangeSaveData);
                    if (isset($rangeData['entity_id']) && !empty($rangeData['entity_id'])) {
                        $model->setEntityId($rangeData['entity_id']);
                    }

                    $model->save();
                    $lastSavedId = $model->getId();
                }

                $this->messageManager->addSuccessMessage(__('You saved the commission rule(s).'));

                if ($this->getRequest()->getParam('back') && $lastSavedId) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $lastSavedId]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                file_put_contents(BP . '/var/log/commission_save.log', date('Y-m-d H:i:s') . " error: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $this->getRequest()->getParam('entity_id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Check permission for action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::manage_commission');
    }
}
