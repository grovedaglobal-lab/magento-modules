<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Module\ModuleListInterface;

class MarketplaceModules implements OptionSourceInterface
{
    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * @param ModuleListInterface $moduleList
     */
    public function __construct(ModuleListInterface $moduleList)
    {
        $this->moduleList = $moduleList;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => 'custom', 'label' => __('Custom / Manual Configuration')],
            ['value' => 'webkul', 'label' => __('Webkul Marketplace (Preset)')],
            ['value' => 'cedcommerce', 'label' => __('CedCommerce Marketplace (Preset)')],
            ['value' => 'vnecoms', 'label' => __('Vnecoms Marketplace (Preset)')]
        ];

        $modules = $this->moduleList->getNames();

        foreach ($modules as $moduleName) {
            if (strpos($moduleName, 'Marketplace') !== false || strpos($moduleName, 'Vendor') !== false) {
                if (stripos($moduleName, 'Webkul') !== false)
                    continue;
                if (stripos($moduleName, 'Ced') !== false && stripos($moduleName, 'Marketplace') !== false)
                    continue;
                if (stripos($moduleName, 'Vnecoms') !== false)
                    continue;
                if (stripos($moduleName, 'Tax_IndianGST') !== false)
                    continue;

                $options[] = [
                    'value' => $moduleName,
                    'label' => $moduleName . ' (Detected)'
                ];
            }
        }

        return $options;
    }
}
