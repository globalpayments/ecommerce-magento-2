<?php

namespace RealexPayments\HPP\Controller\Process\Result;

use Amazon\Payment\Model\PaymentManagement;

class Base extends \Magento\Framework\App\Action\Action
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
     * Core registry.
     *
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_logger;

    /**
     * @var \RealexPayments\HPP\Api\RealexPaymentManagementInterface|PaymentManagement
     */
    private $_paymentManagement;

    /**
     * Result constructor.
     *
     * @param  \Magento\Framework\App\Action\Context  $context
     * @param  \RealexPayments\HPP\Helper\Data  $helper
     * @param  \Magento\Framework\Registry  $coreRegistry
     * @param  \RealexPayments\HPP\Logger\Logger  $logger
     * @param  \RealexPayments\HPP\Api\RealexPaymentManagementInterface  $paymentManagement
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \RealexPayments\HPP\Helper\Data $helper,
        \Magento\Framework\Registry $coreRegistry,
        \RealexPayments\HPP\Logger\Logger $logger,
        \RealexPayments\HPP\Api\RealexPaymentManagementInterface $paymentManagement
    ) {
        $this->_helper = $helper;
        $this->_url = $context->getUrl();
        $this->coreRegistry = $coreRegistry;
        $this->_logger = $logger;
        $this->_paymentManagement = $paymentManagement;
        parent::__construct($context);
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        try {
            $response = $this->getRequest()->getParams();
            //the default
            $params['returnUrl'] = $this->_url->getUrl('checkout/cart');

            if ($response) {
                $result = $this->_handleResponse($response);
                $params['returnUrl'] = $this->_url
                    ->getUrl('realexpayments_hpp/process/sessionresult',
                        $this->_buildSessionParams($result, $response));
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
        $this->coreRegistry->register(\RealexPayments\HPP\Block\Process\Result::REGISTRY_KEY, $params);

        $this->_view->loadLayout();
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();
    }

    /**
     * @param  array  $response
     *
     * @return bool
     */
    private function _handleResponse($response)
    {
        if (empty($response)) {
            $this->_logger->critical(__('Empty response received from gateway'));

            return false;
        }

        $this->_helper->logDebug(__('Gateway response:').print_r($this->_helper->stripTrimFields($response), true));

        // validate response
        $authStatus = $this->_validateResponse($response);
        if (!$authStatus) {
            $this->_logger->critical(__('Invalid response received from gateway.'));

            return false;
        }
        //get the actual order id
        [$incrementId, $orderTimestamp] = explode('_', $response['ORDER_ID']);

        if (!$incrementId) {
            $this->_logger->critical(__('Gateway response does not have an order id.'));

            return false;
        }

        $order = $this->_getOrder($incrementId);
        if (!$order->getId()) {
            $this->_logger->critical(__('Gateway response has an invalid order id.'));

            return false;
        }

        if (!$this->_paymentManagement->isTransactionApm($response)) {
            // process the response
            return $this->_paymentManagement->processResponse($order, $response);
        }

        // apm scenario
        $fieldsToLog = $this->_helper->stripFields($response);
        $fieldsToLogString = '<b>Initial response</b> <br />';
        $fieldsToLogList = [
            'RESULT',
            'MESSAGE',
            'PASREF',
            'ORDER_ID',
            'TIMESTAMP',
            'AMOUNT',
            'HPP_APM_DESCRIPTOR',
            'PAYMENTMETHOD'
        ];
        foreach ($fieldsToLog as $fieldToLogKey => $fieldToLogValue) {
            if (!in_array($fieldToLogKey, $fieldsToLogList)) {
                continue;
            }

            $fieldsToLogString .= htmlspecialchars("{$fieldToLogKey}: {$fieldToLogValue}", ENT_QUOTES,
                    'UTF-8')."<br />";
        }
        $this->_paymentManagement->addHistoryComment($order, $fieldsToLogString);
        return true;
    }

    /**
     * Validate response using sha1 signature.
     *
     * @param  array  $response
     *
     * @return bool
     */
    private function _validateResponse($response)
    {
        $timestamp = $response['TIMESTAMP'];
        $result = $response['RESULT'];
        $orderid = $response['ORDER_ID'];
        $message = $response['MESSAGE'];
        $pasref = $response['PASREF'];
        $realexsha1 = $response['SHA1HASH'];

        $merchantid = $this->_helper->getConfigData('merchant_id');

        if ($this->_paymentManagement->isTransactionApm($response)) {
            $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result.$message.$pasref.");
        } else {
            $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result.$message.$pasref.{$response['AUTHCODE']}");
        }

        //Check to see if hashes match or not
        if ($sha1hash !== $realexsha1) {
            return false;
        }

        return true;
    }

    /**
     * Build params for the session redirect.
     *
     * @param  bool  $result
     *
     * @param  array  $response
     *
     * @return array|null
     */
    private function _buildSessionParams($result, $response)
    {
        $result = ($result) ? '1' : '0';
        $timestamp = strftime('%Y%m%d%H%M%S');
        $merchantid = $this->_helper->getConfigData('merchant_id');
        $isApmPending = $this->_paymentManagement->isTransactionApm($response) ? '1' : '0';

        // if no order id exists
        if (!$this->_order) {
            return null;
        } else {
            $orderid = $this->_order->getIncrementId();
        }

        $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result");

        return [
            'timestamp' => $timestamp,
            'order_id' => $orderid,
            'result' => $result,
            'hash' => $sha1hash,
            'apm_pending' => $isApmPending
        ];
    }

    /**
     * Get order based on increment id.
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
}
