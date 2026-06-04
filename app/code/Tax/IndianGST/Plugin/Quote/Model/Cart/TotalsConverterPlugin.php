<?php
declare(strict_types=1);

namespace Tax\IndianGST\Plugin\Quote\Model\Cart;

use Magento\Quote\Model\Cart\TotalsConverter;

class TotalsConverterPlugin
{
    /**
     * @var \Magento\Quote\Api\Data\TotalSegmentExtensionFactory
     */
    protected $extensionFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Quote\Api\Data\TotalSegmentExtensionFactory $extensionFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Quote\Api\Data\TotalSegmentExtensionFactory $extensionFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->extensionFactory = $extensionFactory;
        $this->logger = $logger;
    }

    /**
     * @param TotalsConverter $subject
     * @param array $result
     * @param array $addressTotals
     * @return array
     */
    public function afterProcess(TotalsConverter $subject, $result, $addressTotals)
    {
        if (!is_array($addressTotals)) {
            return $result;
        }

        foreach ($result as $segment) {
            $code = $segment->getCode();
            if ($code === 'tax_indiangst') {
                $gstData = null;

                // Search for the matching total in the input array (it might be numeric-indexed)
                $targetTotal = null;
                foreach ($addressTotals as $at) {
                    if (is_array($at) && isset($at['code']) && $at['code'] === $code) {
                        $targetTotal = $at;
                        break;
                    } elseif (is_object($at) && method_exists($at, 'getCode') && $at->getCode() === $code) {
                        $targetTotal = $at;
                        break;
                    }
                }

                if ($targetTotal) {
                    if (is_array($targetTotal)) {
                        if (isset($targetTotal['indiangst_temp_data'])) {
                            $gstData = $targetTotal['indiangst_temp_data'];
                        } elseif (isset($targetTotal['extension_attributes'])) {
                            $gstData = $targetTotal['extension_attributes'];
                        }
                    } elseif (is_object($targetTotal)) {
                        if ($targetTotal instanceof \Magento\Framework\DataObject) {
                            $gstData = $targetTotal->getData('indiangst_temp_data')
                                ?: $targetTotal->getData('extension_attributes');
                        }
                    }
                }

                // If still not found, check the literal key just in case
                if (!$gstData && isset($addressTotals[$code])) {
                    $totalObject = $addressTotals[$code];
                    if (is_array($totalObject)) {
                        $gstData = $totalObject['indiangst_temp_data'] ?? null;
                    } elseif (is_object($totalObject) && $totalObject instanceof \Magento\Framework\DataObject) {
                        $gstData = $totalObject->getData('indiangst_temp_data');
                    }
                }

                if ($gstData) {
                    $logMsg = "GST TotalsPlugin: Found data for $code: " . json_encode($gstData) . "\n";
                    file_put_contents('/tmp/gst_totals_debug.log', $logMsg, FILE_APPEND);

                    $extensionAttributes = $segment->getExtensionAttributes();
                    if (!$extensionAttributes) {
                        $extensionAttributes = $this->extensionFactory->create();
                    }

                    // Cast to string to prevent null issues if values are empty
                    if (isset($gstData['cgst'])) {
                        $extensionAttributes->setCgst((string) $gstData['cgst']);
                    }
                    if (isset($gstData['sgst'])) {
                        $extensionAttributes->setSgst((string) $gstData['sgst']);
                    }
                    if (isset($gstData['igst'])) {
                        $extensionAttributes->setIgst((string) $gstData['igst']);
                    }
                    if (isset($gstData['is_inclusive'])) {
                        $extensionAttributes->setIsInclusive($gstData['is_inclusive']);
                    }

                    $segment->setExtensionAttributes($extensionAttributes);
                } else {
                    $logMsg = "GST TotalsPlugin: Data MISSING for $code\n";
                    file_put_contents('/tmp/gst_totals_debug.log', $logMsg, FILE_APPEND);
                }
            }
        }
        return $result;
    }
}
