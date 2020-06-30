<?php

namespace RealexPayments\HPP\Controller\Cards;

use Magento\Framework\App\RequestInterface;

class View extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $_customerSession;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session       $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->_customerSession = $customerSession;
    }

    /**
     * Check customer authentication.
     *
     * @param RequestInterface $request
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        if (!$request->isDispatched()) {
            return parent::dispatch($request);
        }
        if (!$this->_customerSession->authenticate()) {
            $this->_actionFlag->set('', 'no-dispatch', true);
        }

        return parent::dispatch($request);
    }

    /**
     * View cards.
     */
    public function execute()
    {
        $this->_view->loadLayout();
        $this->_view->getPage()->getConfig()->getTitle()->set(__('My Stored Cards'));
        $navigationBlock = $this->_view->getLayout()->getBlock('customer_account_navigation');
        if ($navigationBlock) {
            $navigationBlock->setActive('realexpayments_hpp/cards/view');
        }
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();
    }
}
