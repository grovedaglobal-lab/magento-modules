<?php
namespace Vendor\Marketplace\Controller\Coupon;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Customer\Model\Session as CustomerSession;

class NewConditionHtml extends Action
{
    protected $ruleFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        RuleFactory $ruleFactory,
        CustomerSession $customerSession
    ) {
        $this->ruleFactory = $ruleFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute()
    {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
        $logger->info('NewConditionHtml execute called');

        if (!$this->customerSession->isLoggedIn()) {
            $logger->info('NewConditionHtml: User not logged in');
            return;
        }

        $id = $this->getRequest()->getParam('id');
        $typeParam = $this->getRequest()->getParam('type');
        $logger->info('NewConditionHtml: Params', ['id' => $id, 'type' => $typeParam]);

        $typeArr = explode('|', str_replace('-', '/', $typeParam));
        $type = $typeArr[0];

        $model = $this->_objectManager->create($type)
            ->setId($id)
            ->setType($type)
            ->setRule($this->ruleFactory->create())
            ->setPrefix('conditions');

        if ($this->getRequest()->getParam('prefix')) {
            $model->setPrefix($this->getRequest()->getParam('prefix'));
        }

        if (!empty($typeArr[1])) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof \Magento\Rule\Model\Condition\AbstractCondition) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $model->setFormName($this->getRequest()->getParam('form_namespace'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }
}
