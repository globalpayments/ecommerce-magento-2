<?php

namespace RealexPayments\HPP\Controller\Process;

class SessionResult extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    private $_order;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_logger;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_session;

    /**
     * Result constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \RealexPayments\HPP\Helper\Data       $helper
     * @param \Magento\Sales\Model\OrderFactory     $orderFactory
     * @param \RealexPayments\HPP\Logger\Logger     $logger
     * @param \Magento\Checkout\Model\Session       $session
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \RealexPayments\HPP\Helper\Data $helper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \RealexPayments\HPP\Logger\Logger $logger,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->_helper = $helper;
        $this->_orderFactory = $orderFactory;
        $this->_url = $context->getUrl();
        $this->_logger = $logger;
        $this->_session = $session;
        parent::__construct($context);
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $response = $this->getRequest()->getParams();
        if (!$this->_validateResponse($response)) {
            $this->messageManager->addError(
                __('Your payment was unsuccessful. Please try again or use a different card / payment method.'),
                'realex_messages'
            );
            $this->_redirect('checkout/cart');

            return;
        }
        $result = boolval($response['result']);
        if ($result) {
            $this->_session->getQuote()
                  ->setIsActive(false)
                  ->save();
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_cancel();
            $this->_session->setData(\RealexPayments\HPP\Block\Process\Result\Observe::OBSERVE_KEY, '1');
            $this->messageManager->addError(
                __('Your payment was unsuccessful. Please try again or use a different card / payment method.'),
                'realex_messages'
            );
            $this->_redirect('checkout/cart');
        }
    }

    private function _validateResponse($response)
    {
        if (!isset($response) || !isset($response['timestamp']) || !isset($response['order_id']) ||
            !isset($response['result']) || !isset($response['hash'])) {
            return false;
        }

        $timestamp = $response['timestamp'];
        $merchantid = $this->_helper->getConfigData('merchant_id');
        $orderid = $response['order_id'];
        $result = $response['result'];
        $hash = $response['hash'];
        $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result");

        //Check to see if hashes match or not
        if ($sha1hash !== $hash){
            return false;
        }
        
        $order = $this->_getOrder($orderid);

        return $order->getId();
    }

    /**
     * Cancel the order and restore the quote.
     */
    private function _cancel()
    {
        // restore the quote
        $this->_session->restoreQuote();

        $this->_helper->cancelOrder($this->_order);
    }

    /**
     * Get order based on increment_id.
     *
     * @param $incrementId
     *
     * @return \Magento\Sales\Model\Order
     */
    private function _getOrder($incrementId)
    {
        if (!$this->_order) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }

        return $this->_order;
    }
}
