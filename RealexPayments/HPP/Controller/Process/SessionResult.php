<?php

namespace RealexPayments\HPP\Controller\Process;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\Data\OrderInterface;
use RealexPayments\HPP\Api\RealexPaymentManagementInterface;
use RealexPayments\HPP\Model\Api\RealexPaymentManagement;

class SessionResult extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

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

    /** @var ResultFactory */
    private $_resultFactory;

    /** @var RealexPaymentManagementInterface|RealexPaymentManagement */
    private $_paymentManagement;

    /**
     * Result constructor.
     *
     * @param  \Magento\Framework\App\Action\Context  $context
     * @param  \RealexPayments\HPP\Helper\Data  $helper
     * @param  \RealexPayments\HPP\Logger\Logger  $logger
     * @param  \Magento\Checkout\Model\Session  $session
     * @param  ResultFactory  $resultFactory
     * @param  RealexPaymentManagementInterface|RealexPaymentManagement  $paymentManagement
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \RealexPayments\HPP\Helper\Data $helper,
        \RealexPayments\HPP\Logger\Logger $logger,
        \Magento\Checkout\Model\Session $session,
        ResultFactory $resultFactory,
        RealexPaymentManagementInterface $paymentManagement
    ) {
        $this->_helper = $helper;
        $this->_url = $context->getUrl();
        $this->_logger = $logger;
        $this->_session = $session;
        $this->_resultFactory = $resultFactory;
        $this->_paymentManagement = $paymentManagement;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Layout|void
     *
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

        $isApmPending = isset($response['apm_pending']) && $response['apm_pending'] == '1';

        if ($isApmPending) {
            return $this->_handleApm($this->_getOrder($response['order_id']), $response);
        }

        if ($result) {
            $this->_session->getQuote()
                ->setIsActive(false)
                ->save();
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_cancel();
            $this->_session->setData(\RealexPayments\HPP\Block\Process\Result\Observe::OBSERVE_KEY, '1');

            $message = !empty($response['order_id'])
                ? $this->_getAdditionalInformationFromOrderPayment($response['order_id'], 'MESSAGE')
                : '';

            if ($message) {
                $this->messageManager->addErrorMessage(
                    $message,
                    'realex_messages'
                );
            }
            $this->messageManager->addError(
                __('Your payment was unsuccessful. Please try again or use a different card / payment method.'),
                'realex_messages'
            );
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * @param  OrderInterface  $order
     * @param  array  $response
     *
     * @return bool|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Layout|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function _handleApm($order, $response)
    {
        if (!$this->_paymentManagement->isTransactionApm($response)) {
            return false;
        }

        if ($this->_helper->isOrderPendingPayment($order)) {
            return $this->_handlePendingApmConfirmation($response);
        }

        // copy pasted from current behaviour
        $isOrderPaid = $order->getStatus() === $this->_paymentManagement->getDefaultPaymentSuccessfulStatus($this->_order);
        if ($isOrderPaid) {
            /** @noinspection PhpUnhandledExceptionInspection */
            /** @noinspection PhpDeprecationInspection */
            $this->_session->getQuote()
                ->setIsActive(false)
                ->save();
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_cancel();
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_session->setData(\RealexPayments\HPP\Block\Process\Result\Observe::OBSERVE_KEY, '1');
            /** @noinspection PhpDeprecationInspection */
            $this->messageManager->addError(
                __('Your payment was unsuccessful. Please try again or use a different card / payment method.'),
                'realex_messages'
            );
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * @param array $response
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Layout
     */
    private function _handlePendingApmConfirmation($response)
    {
        if (isset($response['final']) && $response['final'] === '1') {
            $this->_redirect('checkout/onepage/success');
        }

        $params = [
            'statusFetchUrl' =>
                $this->_url->getUrl(
                    'realexpayments_hpp/apm/statusfetcher',
                    ['order_id' => $response['order_id']]
                ),
            'finalRedirectUrl' =>
                $this->_url->getUrl(
                    'realexpayments_hpp/process/sessionresult',
                    array_merge($response, ['final' => '1'])
                ),
            'interval' => 2000,
        ];

        $page = $this->_resultFactory->create(ResultFactory::TYPE_PAGE);

        /** @var \Magento\Framework\View\Element\Template $block */
        $block = $page->getLayout()->getBlock('realexpayments-hpp-process-sessionresult');

        $block->setData('params', $params);

        return $page;
    }

    private function _validateResponse($response)
    {
        if (
            !isset($response)
            || !isset($response['timestamp'])
            || !isset($response['order_id'])
            || !isset($response['result']) || !isset($response['hash'])
        ) {
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
            $this->_order = $this->_helper->getOrderByIncrement($incrementId);
        }

        return $this->_order;
    }

    /**
     * Retreive additional information from Order Payment.
     *
     * @param string $incrementId
     * @param string $key
     *
     * @return mixed
     */
    private function _getAdditionalInformationFromOrderPayment($incrementId, $key) {
        $order = $this->_getOrder($incrementId);
        if ($order) {
            $payment = $order->getPayment();
            if ($payment) {
                return $payment->getAdditionalInformation($key);
            }
        }

        return null;
    }
}
