<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Magento\Framework\Controller\Result\JsonFactory;

class AttributeOptions extends Action
{
    protected $customerSession;
    protected $attributeRepository;
    protected $jsonFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        Repository $attributeRepository,
        JsonFactory $jsonFactory
    ) {
        $this->customerSession = $customerSession;
        $this->attributeRepository = $attributeRepository;
        $this->jsonFactory = $jsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['error' => true, 'message' => __('Please login first.')]);
        }

        $attributeId = $this->getRequest()->getParam('attribute_id');
        if (!$attributeId) {
            return $result->setData(['error' => true, 'message' => __('Attribute ID is required.')]);
        }

        try {
            $attribute = $this->attributeRepository->get($attributeId);
            $options = [];

            if ($attribute->usesSource()) {
                foreach ($attribute->getSource()->getAllOptions() as $option) {
                    if (empty($option['value'])) {
                        continue;
                    }
                    $options[] = [
                        'value' => $option['value'],
                        'label' => $option['label']
                    ];
                }
            }

            return $result->setData(['success' => true, 'options' => $options]);

        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
