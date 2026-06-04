<?php
namespace Antigravity\MailHog\Model\Mail;

use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\TransportInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class Transport implements TransportInterface
{
    /** @var EmailMessageInterface */
    protected $message;

    /**
     * @param EmailMessageInterface $message
     */
    public function __construct(EmailMessageInterface $message)
    {
        $this->message = $message;
    }

    /**
     * Send a message
     *
     * @return void
     */
    public function sendMessage()
    {
        try {
            $email = $this->message->getSymfonyMessage();
            $transport = new EsmtpTransport('localhost', 1025);
            $mailer = new Mailer($transport);
            $mailer->send($email);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\MailException(
                new \Magento\Framework\Phrase($e->getMessage()),
                $e
            );
        }
    }

    /**
     * Get message
     *
     * @return EmailMessageInterface
     */
    public function getMessage()
    {
        return $this->message;
    }
}