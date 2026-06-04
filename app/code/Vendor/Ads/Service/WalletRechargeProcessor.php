<?php
namespace Vendor\Ads\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Ads\Model\Payment\WalletRecharge as WalletRechargePaymentMethod;
use Vendor\Ads\Api\WalletRepositoryInterface;
use Vendor\Ads\Helper\Config as AdsConfig;
use Magento\Framework\App\ObjectManager;

class WalletRechargeProcessor
{
    protected $resource;
    protected $adsConfig;
    protected $razorpayGateway;
    protected $walletRepository;
    protected $walletRechargeHistoryService;
    protected $productRepository;
    protected $productFactory;
    protected $cartManagement;
    protected $cartRepository;
    protected $customerRepository;
    protected $storeManager;
    protected $orderRepository;
    protected $invoiceService;
    protected $transactionFactory;
    protected $addressFactory;

    public function __construct(
        ResourceConnection $resource,
        AdsConfig $adsConfig,
        RazorpayGateway $razorpayGateway,
        WalletRepositoryInterface $walletRepository,
        WalletRechargeHistoryService $walletRechargeHistoryService,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        AddressFactory $addressFactory
    ) {
        $this->resource = $resource;
        $this->adsConfig = $adsConfig;
        $this->razorpayGateway = $razorpayGateway;
        $this->walletRepository = $walletRepository;
        $this->walletRechargeHistoryService = $walletRechargeHistoryService;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->addressFactory = $addressFactory;
    }

    public function calculateAmounts(float $amount, string $taxMode): array
    {
        if ($amount <= 0) {
            throw new LocalizedException(__('Recharge amount must be greater than zero.'));
        }

        $taxMode = in_array($taxMode, ['exclusive', 'inclusive'], true) ? $taxMode : 'exclusive';

        if ($taxMode === 'inclusive') {
            $total = round($amount, 4);
            $base = round($total / 1.18, 4);
            $gst = round($total - $base, 4);
        } else {
            $base = round($amount, 4);
            $gst = round($base * 0.18, 4);
            $total = round($base + $gst, 4);
        }

        return [
            'base_amount' => $base,
            'gst_amount' => $gst,
            'total_amount' => $total,
            'tax_mode' => $taxMode,
            'total_amount_paise' => (int)round($total * 100),
        ];
    }

    public function createPendingPayment(int $vendorId, int $customerId, float $amount): array
    {
        $calc = $this->calculateAmounts($amount, $this->adsConfig->getWalletRechargeTaxMode());
        $connection = $this->resource->getConnection();
        $paymentTable = $this->resource->getTableName('vendor_ads_payment');

        $connection->beginTransaction();
        try {
            $connection->insert($paymentTable, [
                'vendor_id' => $vendorId,
                'customer_id' => $customerId,
                'base_amount' => $calc['base_amount'],
                'gst_amount' => $calc['gst_amount'],
                'total_amount' => $calc['total_amount'],
                'tax_mode' => $calc['tax_mode'],
                'status' => 'pending',
            ]);

            $paymentId = (int)$connection->lastInsertId($paymentTable);

            $order = $this->razorpayGateway->createOrder(
                $calc['total_amount_paise'],
                'vendor_ads_' . $paymentId,
                [
                    'vendor_id' => (string)$vendorId,
                    'payment_ref' => (string)$paymentId,
                ]
            );

            if (empty($order['id'])) {
                throw new LocalizedException(__('Unable to create Razorpay order.'));
            }

            $connection->update(
                $paymentTable,
                ['razorpay_order_id' => $order['id']],
                ['id = ?' => $paymentId]
            );
            
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return [
            'id' => $paymentId,
            'vendor_id' => $vendorId,
            'customer_id' => $customerId,
            'base_amount' => $calc['base_amount'],
            'gst_amount' => $calc['gst_amount'],
            'total_amount' => $calc['total_amount'],
            'tax_mode' => $calc['tax_mode'],
            'total_amount_paise' => $calc['total_amount_paise'],
            'razorpay_order_id' => $order['id'],
            'razorpay_order' => $order,
        ];
    }

    public function processCapturedPayment(string $razorpayOrderId, string $razorpayPaymentId, ?int $vendorId = null, ?int $customerId = null): array
    {
        file_put_contents('/tmp/ads_debug.log', "DEBUG: processCapturedPayment started. Order: $razorpayOrderId, Payment: $razorpayPaymentId\n", FILE_APPEND);
        $connection = $this->resource->getConnection();
        $paymentTable = $this->resource->getTableName('vendor_ads_payment');
        $row = [];

        $connection->beginTransaction();
        try {
            file_put_contents('/tmp/ads_debug.log', "DEBUG: fetchRow started\n", FILE_APPEND);
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($paymentTable)
                    ->where('razorpay_order_id = ?', $razorpayOrderId)
                    ->forUpdate(true)
            );

            if (!$row) {
                file_put_contents('/tmp/ads_debug.log', "DEBUG: row not found\n", FILE_APPEND);
                throw new LocalizedException(__('Payment record not found.'));
            }

            file_put_contents('/tmp/ads_debug.log', "DEBUG: row found. status: " . $row['status'] . "\n", FILE_APPEND);

            if ($vendorId !== null && (int)$row['vendor_id'] !== $vendorId) {
                throw new LocalizedException(__('Vendor mismatch for this payment.'));
            }

            if ($customerId !== null && (int)$row['customer_id'] !== $customerId) {
                throw new LocalizedException(__('Customer mismatch for this payment.'));
            }

            if ($row['status'] === 'success') {
                $connection->commit();
                file_put_contents('/tmp/ads_debug.log', "DEBUG: already success\n", FILE_APPEND);
                return ['status' => 'already_processed', 'payment' => $row];
            }

            $connection->update(
                $paymentTable,
                ['status' => 'processing', 'razorpay_payment_id' => $razorpayPaymentId],
                ['id = ?' => (int)$row['id']]
            );
            $connection->commit();
            file_put_contents('/tmp/ads_debug.log', "DEBUG: committed initial status update\n", FILE_APPEND);

            $paymentData = $this->razorpayGateway->fetchPayment($razorpayPaymentId);
            file_put_contents('/tmp/ads_debug.log', "DEBUG: rzp fetchPayment done. status: " . ($paymentData['status'] ?? 'unknown') . "\n", FILE_APPEND);

            if ((string)($paymentData['order_id'] ?? '') !== $razorpayOrderId) {
                throw new LocalizedException(__('Razorpay order mismatch.'));
            }

            $expectedAmountPaise = (int)round(((float)$row['total_amount']) * 100);
            if ((int)($paymentData['amount'] ?? 0) !== $expectedAmountPaise) {
                throw new LocalizedException(__('Razorpay amount mismatch.'));
            }

            $paymentStatus = (string)($paymentData['status'] ?? '');
            if (!in_array($paymentStatus, ['captured', 'authorized'], true)) {
                throw new LocalizedException(__('Payment is not captured.'));
            }

            $row['razorpay_payment_id'] = $razorpayPaymentId;

            file_put_contents('/tmp/ads_debug.log', "DEBUG: Before Wallet Credit\n", FILE_APPEND);
            $this->ensureWalletCredit($row);
            file_put_contents('/tmp/ads_debug.log', "DEBUG: Wallet credited successfully\n", FILE_APPEND);

            // Update status promptly so the user sees 'Success' even if order generation is slow
            $connection->update(
                $paymentTable,
                [
                    'status' => 'success',
                    'razorpay_payment_id' => $razorpayPaymentId
                ],
                ['id = ?' => (int)$row['id']]
            );

            // Try to generate order/invoice, but don't fail the recharge if it fails
            $magentoOrderId = null;
            try {
                file_put_contents('/tmp/ads_debug.log', "DEBUG: Attempting to ensureOrderAndInvoice\n", FILE_APPEND);
                $magentoOrderId = $this->ensureOrderAndInvoice($row, $razorpayPaymentId);
                
                // If order created, update the payment record with order ID
                if ($magentoOrderId) {
                    $connection->update(
                        $paymentTable,
                        ['magento_order_id' => $magentoOrderId],
                        ['id = ?' => (int)$row['id']]
                    );
                    $this->updateRechargeHistoryPaymentDetails(
                        (int)$row['id'],
                        $razorpayPaymentId,
                        (int)$magentoOrderId
                    );
                }
                file_put_contents('/tmp/ads_debug.log', "DEBUG: Invoicing completed. Order ID: $magentoOrderId\n", FILE_APPEND);
            } catch (\Exception $invoicingError) {
                \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)
                    ->error('Wallet Recharge Invoicing Error (Recharge succeeded, but order/invoice failed): ' . $invoicingError->getMessage(), [
                        'payment_id' => $row['id'],
                        'razorpay_payment_id' => $razorpayPaymentId
                    ]);
                file_put_contents('/tmp/ads_debug.log', "DEBUG: Invoicing failed but recharge is success. Error: " . $invoicingError->getMessage() . "\n", FILE_APPEND);
            }

            return [
                'status' => 'success',
                'payment_id' => (int)$row['id'],
                'magento_order_id' => $magentoOrderId,
                'wallet_credited' => true
            ];
        } catch (\Throwable $e) {
            $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
            $logger->error('WalletRechargeProcessor fatal error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId
            ]);
            if ($connection->getTransactionLevel() > 0) {
                $connection->rollBack();
            }

            try {
                if (!empty($row['id'])) {
                    $connection->update(
                        $paymentTable,
                        ['status' => 'failed'],
                        ['id = ?' => (int)$row['id']]
                    );
                }
            } catch (\Exception $ignore) {
            }

            throw $e;
        }
    }

    protected function updateRechargeHistoryPaymentDetails(
        int $paymentId,
        string $razorpayPaymentId,
        int $magentoOrderId
    ): void {
        if ($paymentId <= 0 || $razorpayPaymentId === '' || $magentoOrderId <= 0) {
            return;
        }

        $connection = $this->resource->getConnection();
        $historyTable = $this->resource->getTableName('vendor_ads_wallet_recharge_history');

        $connection->update(
            $historyTable,
            [
                'razorpay_payment_id' => $razorpayPaymentId,
                'magento_order_id' => $magentoOrderId,
            ],
            ['note = ?' => 'Razorpay recharge payment #' . $paymentId]
        );
    }

    public function cancelPendingPayment(string $razorpayOrderId, int $vendorId, int $customerId): bool
    {
        if ($razorpayOrderId === '' || $vendorId <= 0 || $customerId <= 0) {
            return false;
        }

        $connection = $this->resource->getConnection();
        $paymentTable = $this->resource->getTableName('vendor_ads_payment');

        return (bool)$connection->update(
            $paymentTable,
            ['status' => 'cancelled'],
            [
                'razorpay_order_id = ?' => $razorpayOrderId,
                'vendor_id = ?' => $vendorId,
                'customer_id = ?' => $customerId,
                'status = ?' => 'pending',
            ]
        );
    }

    public function ensureOrderAndInvoice(array $paymentRow, string $razorpayPaymentId): int
    {
        if (!empty($paymentRow['magento_order_id'])) {
            return (int)$paymentRow['magento_order_id'];
        }

        $customer = $this->customerRepository->getById((int)$paymentRow['customer_id']);
        $store = $this->storeManager->getStore();
        $rechargeSku = $this->adsConfig->getWalletRechargeProductSku();
        $product = $this->getOrCreateRechargeProduct($rechargeSku, (int)$store->getId());

        $taxClassId = (int)$this->adsConfig->getWalletRechargeTaxClassId();
        if ($taxClassId > 0) {
            $product->setTaxClassId($taxClassId);
        }

        $cartId = $this->cartManagement->createEmptyCart();
        $quote = $this->cartRepository->get($cartId);
        $quote->setStore($store);
        $quote->assignCustomer($customer);

        $request = new DataObject(['qty' => 1]);
        $quoteItem = $quote->addProduct($product, $request);
        if (is_string($quoteItem)) {
            throw new LocalizedException(
                __('Unable to add recharge product SKU "%1" to quote: %2', $rechargeSku, $quoteItem)
            );
        }

        if ($quoteItem instanceof QuoteItem && $quoteItem->getHasError()) {
            $messages = $quoteItem->getMessage();
            if (is_array($messages)) {
                $messages = implode(' ', $messages);
            }
            throw new LocalizedException(
                __('Unable to add recharge product SKU "%1" to quote: %2', $rechargeSku, (string)$messages)
            );
        }

        $priceIncludesTax = (bool)$this->storeManager->getStore()->getConfig('tax/calculation/price_includes_tax');
        
        if ($priceIncludesTax) {
            $priceToSet = (float)$paymentRow['total_amount'];
        } else {
            $priceToSet = (float)$paymentRow['base_amount'];
        }

        $quoteItem->setCustomPrice($priceToSet);
        $quoteItem->setOriginalCustomPrice($priceToSet);
        $quoteItem->getProduct()->setIsSuperMode(true);

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->addData($this->buildBillingAddressData($paymentRow, $customer));

        $quote->setInventoryProcessed(false);
        $quote->getPayment()->importData(['method' => WalletRechargePaymentMethod::METHOD_CODE]);
        
        // Ensure Shipping Address is set to trigger Indian GST calculation (even for virtual products)
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($this->buildBillingAddressData($paymentRow, $customer));
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->setShippingMethod('flatrate_flatrate');
        
        $quote->collectTotals();
        $quote->setSendConfirmation(false);
        $this->cartRepository->save($quote);

        try {
            $orderId = (int)$this->cartManagement->placeOrder($cartId);
        } catch (\Exception $e) {
            // If the error is about email (Connection refused), try to find if the order was actually created
            if (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'mail') !== false) {
                $orderId = $this->findOrderIdByQuoteId($cartId);
                if (!$orderId) {
                    throw $e; // If no order was created, it's a real failure
                }
                // If order was created, we can ignore the mail error
            } else {
                throw $e;
            }
        }
        $order = $this->orderRepository->get($orderId);
        $order->setData('is_wallet_recharge', 1);
        $this->orderRepository->save($order);

        // SYNC GST BREAKDOWN: If the totals didn't pick it up, manually inject from payment table
        // This ensures CGST/SGST/IGST show up in the email/invoice
        $this->syncIndianGstToOrder($order, $paymentRow);

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice && $invoice->getTotalQty()) {
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->setTransactionId($razorpayPaymentId);
                $invoice->register();
                $invoice->pay();
                
                // For virtual products, the order should move to processing/complete
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setIsInProcess(true);

                $transaction = $this->transactionFactory->create();
                $transaction->addObject($invoice)->addObject($order)->save();

                // Send invoice email in the background
                try {
                    $invoiceSender = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Sales\Model\Order\Email\Sender\InvoiceSender::class);
                    $invoiceSender->send($invoice);
                } catch (\Exception $e) {
                    \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)
                        ->error('Failed to send wallet recharge invoice email: ' . $e->getMessage());
                }
            }
        }

        // Send Custom Wallet Recharge Confirmation Email
        $this->sendOrderEmail($orderId);

        return $orderId;
    }

    protected function getOrCreateRechargeProduct(string $rechargeSku, int $storeId)
    {
        try {
            $product = $this->productRepository->get($rechargeSku, false, $storeId, true);
        } catch (NoSuchEntityException $e) {
            $product = $this->createRechargeProduct($rechargeSku, $storeId);
        }

        if ($product->getTypeId() !== ProductType::TYPE_VIRTUAL) {
            throw new LocalizedException(__('Recharge product SKU "%1" must be virtual.', $rechargeSku));
        }

        $productNeedsSave = false;
        
        // 1. Check Status
        if ((int)$product->getStatus() !== ProductStatus::STATUS_ENABLED) {
            $product->setStatus(ProductStatus::STATUS_ENABLED);
            $productNeedsSave = true;
        }

        // 2. Check Visibility
        if ((int)$product->getVisibility() !== ProductVisibility::VISIBILITY_NOT_VISIBLE) {
            $product->setVisibility(ProductVisibility::VISIBILITY_NOT_VISIBLE);
            $productNeedsSave = true;
        }

        // 3. Check Stock Settings (Crucial for virtual products)
        $stockItem = null;
        try {
            $stockRegistry = ObjectManager::getInstance()->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
            $stockItem = $stockRegistry->getStockItem($product->getId());
        } catch (\Exception $e) {
            // Fallback to stock data if registry fails
        }

        $manageStock = 1;
        $isInStock = 0;
        
        if ($stockItem && $stockItem->getId()) {
            $manageStock = (int)$stockItem->getManageStock();
            $isInStock = (int)$stockItem->getIsInStock();
        } else {
            $stockData = $product->getStockData();
            if (is_array($stockData)) {
                $manageStock = isset($stockData['manage_stock']) ? (int)$stockData['manage_stock'] : 1;
                $isInStock = isset($stockData['is_in_stock']) ? (int)$stockData['is_in_stock'] : 0;
            }
        }

        if ($manageStock !== 0 || $isInStock !== 1) {
            $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 0,
                'is_in_stock' => 1,
                'qty' => 9999
            ]);
            $productNeedsSave = true;
        }

        // 4. Check Website Association
        $store = $this->storeManager->getStore($storeId);
        $websiteId = (int)$store->getWebsiteId();
        $websiteIds = array_map('intval', (array)$product->getWebsiteIds());
        if ($websiteId > 0 && !in_array($websiteId, $websiteIds, true)) {
            $websiteIds[] = $websiteId;
            $product->setWebsiteIds(array_values(array_unique($websiteIds)));
            $productNeedsSave = true;
        }

        if ($productNeedsSave) {
            try {
                $this->productRepository->save($product);
                $product = $this->productRepository->get($rechargeSku, false, $storeId, true);
            } catch (\Exception $e) {
                // If save fails due to stock item issues, we try to proceed anyway if the product exists
                // The error usually happens in indexers/observers that aren't critical for the cart add
                \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)
                    ->warning('Recharge product save failed but continuing: ' . $e->getMessage());
            }
        }

        return $product;
    }

    protected function createRechargeProduct(string $rechargeSku, int $storeId)
    {
        $connection = $this->resource->getConnection();
        $defaultAttributeSetId = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_entity_type'), ['default_attribute_set_id'])
                ->where('entity_type_code = ?', 'catalog_product')
                ->limit(1)
        );

        if ($defaultAttributeSetId <= 0) {
            throw new LocalizedException(__('Unable to resolve default product attribute set.'));
        }

        $store = $this->storeManager->getStore($storeId);
        $websiteId = (int)$store->getWebsiteId();

        $product = $this->productFactory->create();
        $product->setSku($rechargeSku);
        $product->setName('Wallet Recharge');
        $product->setAttributeSetId($defaultAttributeSetId);
        $product->setTypeId(ProductType::TYPE_VIRTUAL);
        $product->setVisibility(ProductVisibility::VISIBILITY_NOT_VISIBLE);
        $product->setStatus(ProductStatus::STATUS_ENABLED);
        $product->setPrice(1.00);
        $product->setWebsiteIds([$websiteId]);
        $product->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 0,
            'is_in_stock' => 1,
            'qty' => 9999,
        ]);

        $taxClassId = (int)$this->adsConfig->getWalletRechargeTaxClassId();
        if ($taxClassId > 0) {
            $product->setTaxClassId($taxClassId);
        }

        // Set Indian GST Attributes
        $product->setData('gst_rate', '18'); // 18% GST
        $product->setData('hsn_code', '998311'); // Management consulting and management services / Advertising services

        $this->productRepository->save($product);

        return $this->productRepository->get($rechargeSku, false, $storeId, true);
    }

    protected function buildBillingAddressData(array $paymentRow, $customer): array
    {
        $vendorAddress = $this->getVendorContactAddress((int)$paymentRow['vendor_id']);
        $customerBilling = $this->getCustomerDefaultBillingAddressData($customer);

        $countryRaw = trim((string)($vendorAddress['country_id'] ?? ''));
        if ($countryRaw === '') {
            $countryRaw = trim((string)($customerBilling['country_id'] ?? ''));
        }
        if ($countryRaw === '') {
            $countryRaw = (string)$this->storeManager->getStore()->getConfig('general/country/default');
        }

        $countryId = $this->resolveCountryId($countryRaw);
        if ($countryId === '') {
            throw new LocalizedException(
                __('Vendor contact address country "%1" is invalid. Please update vendor profile country and try again.', $countryRaw !== '' ? $countryRaw : '-')
            );
        }

        $state = trim((string)($vendorAddress['state'] ?? ''));
        if ($state === '') {
            $state = trim((string)($customerBilling['region'] ?? ''));
        }
        $regionData = $this->resolveRegionData($countryId, $state);

        $street = trim((string)($vendorAddress['street'] ?? ''));
        if ($street === '') {
            $street = trim((string)($customerBilling['street'] ?? ''));
        }
        if ($street === '') {
            throw new LocalizedException(__('Vendor contact address street is missing. Please update vendor profile address and try again.'));
        }

        $city = trim((string)($vendorAddress['city'] ?? ''));
        if ($city === '') {
            $city = trim((string)($customerBilling['city'] ?? ''));
        }
        if ($city === '') {
            throw new LocalizedException(__('Vendor contact address city is missing. Please update vendor profile city and try again.'));
        }

        $postcode = trim((string)($vendorAddress['postcode'] ?? ''));
        if ($postcode === '') {
            $postcode = trim((string)($customerBilling['postcode'] ?? ''));
        }
        if ($postcode === '') {
            throw new LocalizedException(__('Vendor contact address postcode is missing. Please update vendor profile postal code and try again.'));
        }

        $telephone = trim((string)($vendorAddress['telephone'] ?? ''));
        if ($telephone === '') {
            $telephone = trim((string)($customerBilling['telephone'] ?? ''));
        }
        if ($telephone === '') {
            throw new LocalizedException(__('Vendor contact phone is missing. Please update vendor profile phone and try again.'));
        }

        $email = trim((string)($vendorAddress['email'] ?? ''));
        if ($email === '') {
            $email = trim((string)($customerBilling['email'] ?? ''));
        }
        if ($email === '') {
            $email = (string)$customer->getEmail();
        }

        return [
            'firstname' => (string)$customer->getFirstname(),
            'lastname' => (string)$customer->getLastname(),
            'street' => $street,
            'city' => $city,
            'postcode' => $postcode,
            'telephone' => $telephone,
            'country_id' => $countryId,
            'email' => $email,
            'region' => $regionData['region'],
            'region_id' => $regionData['region_id'],
        ];
    }

    protected function getCustomerDefaultBillingAddressData($customer): array
    {
        $addresses = (array)$customer->getAddresses();
        if (empty($addresses)) {
            return [];
        }

        $defaultBillingId = (int)$customer->getDefaultBilling();
        $selected = null;
        foreach ($addresses as $address) {
            if ($defaultBillingId > 0 && (int)$address->getId() === $defaultBillingId) {
                $selected = $address;
                break;
            }
        }

        if ($selected === null) {
            $selected = $addresses[0];
        }

        $regionValue = '';
        $region = $selected->getRegion();
        if (is_object($region) && method_exists($region, 'getRegion')) {
            $regionValue = trim((string)$region->getRegion());
        } elseif (is_string($region)) {
            $regionValue = trim($region);
        }

        $streetValue = $selected->getStreet();
        if (is_array($streetValue)) {
            $streetValue = implode(', ', array_filter($streetValue));
        }

        return [
            'street' => trim((string)$streetValue),
            'city' => trim((string)$selected->getCity()),
            'postcode' => trim((string)$selected->getPostcode()),
            'telephone' => trim((string)$selected->getTelephone()),
            'country_id' => trim((string)$selected->getCountryId()),
            'region' => $regionValue,
            'email' => trim((string)$customer->getEmail()),
        ];
    }

    protected function resolveCountryId(string $country): string
    {
        $country = trim($country);
        if ($country === '') {
            return '';
        }

        $connection = $this->resource->getConnection();
        $countryTable = $this->resource->getTableName('directory_country');
        if (!$connection->isTableExists($countryTable)) {
            return '';
        }

        $countryColumns = array_keys((array)$connection->describeTable($countryTable));

        $upper = strtoupper($country);

        if (preg_match('/\(([A-Z]{2})\)$/', $upper, $matches)) {
            $upper = $matches[1];
        }

        if (preg_match('/^[A-Z]{2}$/', $upper)) {
            $exists = (string)$connection->fetchOne(
                $connection->select()
                    ->from($countryTable, ['country_id'])
                    ->where('country_id = ?', $upper)
                    ->limit(1)
            );
            if ($exists !== '') {
                return $exists;
            }
        }

        if (preg_match('/^[A-Z]{3}$/', $upper)) {
            $fromIso3 = (string)$connection->fetchOne(
                $connection->select()
                    ->from($countryTable, ['country_id'])
                    ->where('iso3_code = ?', $upper)
                    ->limit(1)
            );
            if ($fromIso3 !== '') {
                return $fromIso3;
            }
        }

        $nameColumns = ['full_name_locale', 'full_name_english'];
        foreach ($nameColumns as $nameColumn) {
            if (!in_array($nameColumn, $countryColumns, true)) {
                continue;
            }

            $fromName = (string)$connection->fetchOne(
                $connection->select()
                    ->from($countryTable, ['country_id'])
                    ->where(sprintf('LOWER(%s) = LOWER(?)', $nameColumn), $country)
                    ->limit(1)
            );
            if ($fromName !== '') {
                return $fromName;
            }
        }

        $countryNameTable = $this->resource->getTableName('directory_country_name');
        if ($connection->isTableExists($countryNameTable)) {
            $countryNameColumns = array_keys((array)$connection->describeTable($countryNameTable));
            if (in_array('name', $countryNameColumns, true) && in_array('country_id', $countryNameColumns, true)) {
                $fromDirectoryCountryName = (string)$connection->fetchOne(
                    $connection->select()
                        ->from($countryNameTable, ['country_id'])
                        ->where('LOWER(name) = LOWER(?)', $country)
                        ->limit(1)
                );

                if ($fromDirectoryCountryName !== '') {
                    return $fromDirectoryCountryName;
                }
            }
        }

        $normalizedInput = $this->normalizeCountryValue($country);
        if ($normalizedInput !== '') {
            try {
                $collectionFactory = ObjectManager::getInstance()->get(\Magento\Directory\Model\ResourceModel\Country\CollectionFactory::class);
                $countries = $collectionFactory->create()->loadByStore();
                $options = (array)$countries->toOptionArray(false);

                foreach ($options as $option) {
                    $code = strtoupper(trim((string)($option['value'] ?? '')));
                    $label = (string)($option['label'] ?? '');
                    if ($code === '') {
                        continue;
                    }

                    if ($this->normalizeCountryValue($label) === $normalizedInput) {
                        return $code;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        return '';
    }

    protected function normalizeCountryValue(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    protected function getVendorContactAddress(int $vendorId): array
    {
        if ($vendorId <= 0) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $profileTable = $this->resource->getTableName('vendor_profile');

        if (!$connection->isTableExists($profileTable)) {
            return [];
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from($profileTable, [
                    'address',
                    'city',
                    'state',
                    'zip_code',
                    'country',
                    'phone',
                    'email',
                    'pickup_address',
                    'pickup_city',
                    'pickup_state',
                    'pickup_zip_code',
                    'pickup_country',
                    'pickup_phone',
                    'pickup_email',
                ])
                ->where('vendor_id = ?', $vendorId)
                ->limit(1)
        );

        if (!$row) {
            return [];
        }

        return [
            'street' => (string)($row['pickup_address'] ?: $row['address'] ?: ''),
            'city' => (string)($row['pickup_city'] ?: $row['city'] ?: ''),
            'state' => (string)($row['pickup_state'] ?: $row['state'] ?: ''),
            'postcode' => (string)($row['pickup_zip_code'] ?: $row['zip_code'] ?: ''),
            'country_id' => (string)($row['pickup_country'] ?: $row['country'] ?: ''),
            'telephone' => (string)($row['pickup_phone'] ?: $row['phone'] ?: ''),
            'email' => (string)($row['pickup_email'] ?: $row['email'] ?: ''),
        ];
    }

    protected function resolveRegionData(string $countryId, string $state): array
    {
        $connection = $this->resource->getConnection();
        $regionTable = $this->resource->getTableName('directory_country_region');

        if (!$connection->isTableExists($regionTable)) {
            return ['region_id' => null, 'region' => $state !== '' ? $state : ''];
        }

        $regionId = null;
        $regionName = $state;

        if ($state !== '') {
            $regionId = $connection->fetchOne(
                $connection->select()
                    ->from($regionTable, ['region_id'])
                    ->where('country_id = ?', $countryId)
                    ->where('code = ?', $state)
                    ->limit(1)
            );

            if (!$regionId) {
                $regionId = $connection->fetchOne(
                    $connection->select()
                        ->from($regionTable, ['region_id'])
                        ->where('country_id = ?', $countryId)
                        ->where('default_name = ?', $state)
                        ->limit(1)
                );
            }

            if (!$regionId) {
                $regionId = $connection->fetchOne(
                    $connection->select()
                        ->from($regionTable, ['region_id'])
                        ->where('country_id = ?', $countryId)
                        ->where('LOWER(code) = LOWER(?)', $state)
                        ->limit(1)
                );
            }

            if (!$regionId) {
                $regionId = $connection->fetchOne(
                    $connection->select()
                        ->from($regionTable, ['region_id'])
                        ->where('country_id = ?', $countryId)
                        ->where('LOWER(default_name) = LOWER(?)', $state)
                        ->limit(1)
                );
            }
        }

        $countryHasRegions = (int)$connection->fetchOne(
            $connection->select()
                ->from($regionTable, ['COUNT(*)'])
                ->where('country_id = ?', $countryId)
        ) > 0;

        if ($countryHasRegions && !$regionId) {
            throw new LocalizedException(
                __('Vendor contact address has invalid state/region "%1" for country "%2". Please update vendor profile address and try again.', $state !== '' ? $state : '-', $countryId)
            );
        }

        if ($regionId) {
            $regionName = (string)$connection->fetchOne(
                $connection->select()
                    ->from($regionTable, ['default_name'])
                    ->where('region_id = ?', (int)$regionId)
                    ->limit(1)
            );
        }

        return [
            'region_id' => $regionId ? (int)$regionId : null,
            'region' => (string)$regionName,
        ];
    }

    protected function ensureWalletCredit(array $paymentRow): void
    {
        $connection = $this->resource->getConnection();
        $walletTxnTable = $this->resource->getTableName('vendor_ads_wallet_transaction');

        $existingTxnId = (int)$connection->fetchOne(
            $connection->select()
                ->from($walletTxnTable, ['id'])
                ->where('reference_id = ?', (int)$paymentRow['id'])
                ->limit(1)
        );

        if ($existingTxnId) {
            return;
        }

        $vendorId = (int)$paymentRow['vendor_id'];
        $baseAmount = (float)$paymentRow['base_amount'];
        $gstAmount = (float)$paymentRow['gst_amount'];

        $wallet = $this->walletRepository->getOrCreate($vendorId);
        $oldBalance = (float)$wallet->getBalance();
        $newBalance = $oldBalance + $baseAmount;
        $wallet->setBalance($newBalance);
        $this->walletRepository->save($wallet);

        $connection->insert($walletTxnTable, [
            'vendor_id' => $vendorId,
            'type' => 'credit',
            'amount' => $baseAmount,
            'gst_amount' => $gstAmount,
            'reference_id' => (int)$paymentRow['id'],
        ]);

        $this->walletRechargeHistoryService->logRecharge(
            $vendorId,
            $baseAmount,
            $oldBalance,
            $newBalance,
            'vendor',
            'Razorpay recharge payment #' . (int)$paymentRow['id'],
            $paymentRow['razorpay_payment_id'] ?? null,
            (int)($paymentRow['magento_order_id'] ?? 0),
            $paymentRow['tax_mode'] ?? null
        );
    }

    protected function findOrderIdByQuoteId($quoteId): ?int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('sales_order'), 'entity_id')
            ->where('quote_id = ?', (int)$quoteId);
        $result = $connection->fetchOne($select);
        return $result ? (int)$result : null;
    }

    protected function sendOrderEmail(int $orderId): void
    {
        try {
            $publisher = \Magento\Framework\App\ObjectManager::getInstance()->get(\Vendor\Ads\Model\Email\Publisher::class);
            $publisher->execute((string)$orderId);
        } catch (\Throwable $e) {
            $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
            $logger->error('Failed to queue wallet recharge email: ' . $e->getMessage(), ['order_id' => $orderId]);
        }
    }

    protected function syncIndianGstToOrder($order, $paymentRow)
    {
        $gstAmount = (float)$paymentRow['gst_amount'];
        if ($gstAmount <= 0) {
            return;
        }

        $taxMode = (string)($paymentRow['tax_mode'] ?? 'exclusive');
        
        // Basic split (9% + 9% or 18% IGST)
        // For now we use the billing address region to decide
        $billingAddress = $order->getBillingAddress();
        $isInterState = true; // Default
        
        $adminState = self::getAdminState(); // Helper or Config
        if ($billingAddress && $billingAddress->getRegionCode() === $adminState) {
            $isInterState = false;
        }

        $cgst = $isInterState ? 0 : $gstAmount / 2;
        $sgst = $isInterState ? 0 : $gstAmount / 2;
        $igst = $isInterState ? $gstAmount : 0;

        // Set on Order
        $order->setBaseTaxAmount($gstAmount);
        $order->setTaxAmount($gstAmount);
        $order->setData('indiangst_cgst_amount', $cgst);
        $order->setData('indiangst_sgst_amount', $sgst);
        $order->setData('indiangst_igst_amount', $igst);
        $order->setData('indiangst_amount', $gstAmount);

        // Set on first item (Wallet Recharge)
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) continue;
            $item->setData('indiangst_cgst_amount', $cgst);
            $item->setData('indiangst_sgst_amount', $sgst);
            $item->setData('indiangst_igst_amount', $igst);
            $item->setData('indiangst_percent', 18);
            
            $breakup = [
                'type' => $isInterState ? 'IGST' : 'CGST_SGST',
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_tax' => $gstAmount,
                'rate' => 18,
                'hsn' => '998311'
            ];
            $item->setData('indiangst_breakup', json_encode($breakup));
            break; 
        }

        $this->orderRepository->save($order);
    }

    private static function getAdminState()
    {
        // Delhi as default or load from config
        return 'DL';
    }
}
