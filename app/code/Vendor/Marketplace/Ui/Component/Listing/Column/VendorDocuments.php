<?php
namespace Vendor\Marketplace\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class VendorDocuments extends Column
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!empty($item['documents_data'])) {
                    $docs = explode('|', $item['documents_data']);
                    $html = '<div style="margin-bottom: 5px;"><strong>ID: ' . $item['entity_id'] . '</strong></div>';
                    $html .= '<div class="vendor-documents-list" style="font-size: 11px; line-height: 1.4;">';
                    foreach ($docs as $doc) {
                        $parts = explode('::::', $doc);
                        if (count($parts) < 5)
                            continue;

                        $id = $parts[0];
                        $label = $parts[1];
                        $type = $parts[2];
                        $status = $parts[3];
                        $path = $parts[4];
                        $regDate = isset($parts[5]) ? $parts[5] : '';
                        $expDate = isset($parts[6]) ? $parts[6] : '';
                        $certNumber = isset($parts[7]) ? $parts[7] : '';
                        $issuer = isset($parts[8]) ? $parts[8] : '';
                        $isVisible = isset($parts[9]) ? $parts[9] : 1;

                        $statusLabel = 'Pending';
                        $statusColor = '#999';
                        if ($status == 1) {
                            $statusLabel = 'Approved';
                            $statusColor = '#00a651';
                        } elseif ($status == 2) {
                            $statusLabel = 'Rejected';
                            $statusColor = '#e22626';
                        }

                        $visibleLabel = $isVisible == 1 ? 'Visible' : 'Hidden';
                        $visibleColor = $isVisible == 1 ? '#007bdb' : '#999';

                        // Determine display label based on type
                        $displayLabel = $label ?: $type;
                        if ($type === 'organic_certification') {
                            $displayLabel = 'Organic: ' . ($label ?: 'Certification');
                        } elseif ($type === 'coa_report') {
                            $displayLabel = 'COA: ' . ($label ?: 'Report');
                        }

                        $purePath = str_replace('vendor/documents/', '', $path);
                        $fileUrl = $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]) . 'vendor/documents/' . $purePath;
                        $approveUrl = $this->urlBuilder->getUrl('vendor_marketplace/document/approve', ['entity_id' => $id]);
                        $rejectUrl = $this->urlBuilder->getUrl('vendor_marketplace/document/reject', ['entity_id' => $id]);

                        $html .= '<div style="margin-bottom: 8px; border-bottom: 1px solid #e0e0e0; padding-bottom: 6px;">';
                        $html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start;">';
                        $html .= '<div>';
                        $html .= '<strong style="color: #333;">' . $displayLabel . '</strong> ';
                        $html .= '<span style="color: ' . $statusColor . '; font-weight: bold; font-size: 10px;">[' . $statusLabel . ']</span>';
                        $html .= ' <span style="color: ' . $visibleColor . '; font-size: 10px;">(' . $visibleLabel . ')</span><br/>';

                        if ($certNumber || $issuer) {
                            $html .= '<span style="color: #444; font-size: 10px;">';
                            if ($certNumber)
                                $html .= 'No: ' . $certNumber . ' ';
                            if ($issuer)
                                $html .= '| By: ' . $issuer;
                            $html .= '</span><br/>';
                        }

                        if ($regDate || $expDate) {
                            $html .= '<span style="color: #666; font-size: 10px;">Reg: ' . ($regDate ?: '-') . ' | Exp: ' . ($expDate ?: '-') . '</span><br/>';
                        }
                        $html .= '<a href="' . $fileUrl . '" target="_blank" onclick="event.stopPropagation();" style="color: #007bdb; text-decoration: underline; font-size: 10px;">View File</a>';
                        $html .= '</div>';

                        $showUrl = $this->urlBuilder->getUrl('vendor_marketplace/document/show', ['entity_id' => $id]);
                        $hideUrl = $this->urlBuilder->getUrl('vendor_marketplace/document/hide', ['entity_id' => $id]);

                        $html .= '<div style="display: flex; flex-direction: column; gap: 4px; padding-left: 10px;">';

                        $html .= '<div style="display: flex; gap: 4px;">';
                        if ($status != 1) {
                            $html .= '<a href="' . $approveUrl . '" onclick="event.stopPropagation(); return confirm(\'Approve this document?\')" style="background: #00a651; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; display: inline-block; text-decoration: none;">' . __('Approve') . '</a>';
                        }
                        if ($status != 2) {
                            $html .= '<a href="' . $rejectUrl . '" onclick="event.stopPropagation(); return confirm(\'Reject this document?\')" style="background: #e22626; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; display: inline-block; text-decoration: none;">' . __('Reject') . '</a>';
                        }
                        $html .= '</div>';

                        $html .= '<div style="display: flex; gap: 4px;">';
                        if ($isVisible != 1) {
                            $html .= '<a href="' . $showUrl . '" onclick="event.stopPropagation();" style="background: #007bdb; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; display: inline-block; text-decoration: none;">' . __('Show') . '</a>';
                        } else {
                            $html .= '<a href="' . $hideUrl . '" onclick="event.stopPropagation(); return confirm(\'Hide this from frontend?\')" style="background: #e22626; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; display: inline-block; text-decoration: none;">' . __('Hide') . '</a>';
                        }
                        $html .= '</div>';

                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                    $html .= '</div>';

                    $item[$this->getData('name')] = $html;
                }
            }
        }

        return $dataSource;
    }
}
