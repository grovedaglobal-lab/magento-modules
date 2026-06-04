<?php
namespace Vendor\Marketplace\Block\Coupon;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Data\FormFactory;
use Magento\Rule\Block\Conditions as ConditionsRenderer;
use Magento\Rule\Block\Actions as ActionsRenderer;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Model\VendorFactory;

class Conditions extends Template
{
    protected $formFactory;
    protected $conditionsRenderer;
    protected $actionsRenderer;
    protected $ruleFactory;
    protected $customerSession;
    protected $vendorFactory;

    public function __construct(
        Context $context,
        FormFactory $formFactory,
        ConditionsRenderer $conditionsRenderer,
        ActionsRenderer $actionsRenderer,
        RuleFactory $ruleFactory,
        CustomerSession $customerSession,
        VendorFactory $vendorFactory,
        array $data = []
    ) {
        $this->formFactory = $formFactory;
        $this->conditionsRenderer = $conditionsRenderer;
        $this->actionsRenderer = $actionsRenderer;
        $this->ruleFactory = $ruleFactory;
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        parent::__construct($context, $data);
    }

    public function getConditionsHtml()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->ruleFactory->create();
        if ($id) {
            $model->load($id);
        }

        $formName = 'sales_rule_form';
        $conditionsFieldSetId = $model->getConditionsFieldSetId($formName);

        $newChildUrl = $this->getUrl(
            'vendor_marketplace/coupon/newConditionHtml/form/' . $conditionsFieldSetId,
            ['_secure' => true]
        );

        $form = $this->formFactory->create();
        $form->setHtmlIdPrefix('rule_');

        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $renderer->setTemplate(
            'Vendor_Marketplace::coupon/promo/fieldset.phtml'
        )->setNewChildUrl(
                $newChildUrl
            )->setFieldSetId(
                $conditionsFieldSetId
            );

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            [
                'legend' => __(
                    'Apply the rule only if the following conditions are met (leave blank for all products).'
                )
            ]
        )->setRenderer(
                $renderer
            );

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name' => 'conditions',
                'label' => __('Conditions'),
                'title' => __('Conditions'),
                'required' => true,
                'data-form-part' => $formName
            ]
        )->setRule(
                $model
            )->setRenderer(
                $this->conditionsRenderer
            );

        $form->setValues($model->getData());
        $this->setConditionFormName($model->getConditions(), $formName);

        return $form->toHtml();
    }

    public function getActionsHtml()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->ruleFactory->create();
        if ($id) {
            $model->load($id);
        }

        $formName = 'sales_rule_form';
        $actionsFieldSetId = $model->getActionsFieldSetId($formName);

        $newChildUrl = $this->getUrl(
            'vendor_marketplace/coupon/newConditionHtml/prefix/actions/form/' . $actionsFieldSetId,
            ['_secure' => true]
        );

        $form = $this->formFactory->create();
        $form->setHtmlIdPrefix('rule_');

        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $renderer->setTemplate(
            'Vendor_Marketplace::coupon/promo/fieldset.phtml'
        )->setNewChildUrl(
                $newChildUrl
            )->setFieldSetId(
                $actionsFieldSetId
            );

        $fieldset = $form->addFieldset(
            'actions_fieldset',
            [
                'legend' => __(
                    'Apply the rule only to cart items matching the following conditions (leave blank for all products).'
                )
            ]
        )->setRenderer(
                $renderer
            );

        $fieldset->addField(
            'actions',
            'text',
            [
                'name' => 'actions',
                'label' => __('Actions'),
                'title' => __('Actions'),
                'required' => true,
                'data-form-part' => $formName
            ]
        )->setRule(
                $model
            )->setRenderer(
                $this->actionsRenderer
            );

        $form->setValues($model->getData());
        $this->setActionFormName($model->getActions(), $formName);

        return $form->toHtml();
    }

    private function setConditionFormName(\Magento\Rule\Model\Condition\AbstractCondition $conditions, $formName)
    {
        $conditions->setFormName($formName);
        if ($conditions->getConditions() && is_array($conditions->getConditions())) {
            foreach ($conditions->getConditions() as $condition) {
                $this->setConditionFormName($condition, $formName);
            }
        }
    }

    private function setActionFormName(\Magento\Rule\Model\Condition\AbstractCondition $actions, $formName)
    {
        $actions->setFormName($formName);
        if ($actions->getActions() && is_array($actions->getActions())) {
            foreach ($actions->getActions() as $condition) {
                $this->setActionFormName($condition, $formName);
            }
        }
    }
}
