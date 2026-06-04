namespace Vendor\Marketplace\Controller\Print;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;

class Manage extends Action
{
protected $resultPageFactory;
protected $customerSession;

public function __construct(
Context $context,
PageFactory $resultPageFactory,
CustomerSession $customerSession
) {
$this->resultPageFactory = $resultPageFactory;
$this->customerSession = $customerSession;
parent::__construct($context);
}

public function execute()
{
if (!$this->customerSession->isLoggedIn()) {
return $this->resultRedirectFactory->create()->setPath('marketplace/account/login');
}

$resultPage = $this->resultPageFactory->create();
$resultPage->getConfig()->getTitle()->set(__('Manage Print Settings'));
return $resultPage;
}
}