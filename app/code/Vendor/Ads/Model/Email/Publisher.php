<?php
namespace Vendor\Ads\Model\Email;

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    const TOPIC_NAME = 'vendor.ads.wallet.recharge.email';

    protected $publisher;

    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    public function execute(string $orderId)
    {
        $this->publisher->publish(self::TOPIC_NAME, $orderId);
    }
}