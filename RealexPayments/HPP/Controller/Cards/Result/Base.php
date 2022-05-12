<?php

namespace RealexPayments\HPP\Controller\Cards\Result;

use RealexPayments\HPP\Block\Process;
use RealexPayments\HPP\Helper\CardStorage as CardStorageHelper;

class Base extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var CardStorageHelper
     */
    private $cardStorageHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;

    /**
     * @var \Magento\Framework\Registry\Registry
     */
    private $coreRegistry;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_logger;

    /**
     * Result constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \RealexPayments\HPP\Helper\Data $helper
     * @param CardStorageHelper $cardStorageHelper
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \RealexPayments\HPP\Logger\Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \RealexPayments\HPP\Helper\Data $helper,
        CardStorageHelper $cardStorageHelper,
        \Magento\Framework\Registry $coreRegistry,
        \RealexPayments\HPP\Logger\Logger $logger
    ) {
        $this->_helper = $helper;
        $this->cardStorageHelper = $cardStorageHelper;
        $this->_url = $context->getUrl();
        $this->coreRegistry = $coreRegistry;
        $this->_logger = $logger;

        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $response = $this->getRequest()->getParams();
            //the default
            $params['returnUrl'] = $this->_url->getUrl('/');

            if ($response) {
                $result = $this->_handleResponse($response);
                $params['returnUrl'] = $this->_url->getUrl('realexpayments_hpp/cards/success');
                $customerId = $response['MAGENTO_CUSTOMER_ID'];

                if ($result && $customerId) {
                    $this->cardStorageHelper->handleCardStorage($response, $customerId);
                }
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
        $this->coreRegistry->register(Process\Result::REGISTRY_KEY, $params);

        $this->_view->loadLayout();
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();
    }

    /**
     * Handles the response received from HPP.
     *
     * @param array $response
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
        // happy with the response
        return true;
    }

    /**
     * Validate response using sha1 signature.
     *
     * @param array $response
     *
     * @return bool
     */
    private function _validateResponse($response)
    {
        $timestamp = $response['TIMESTAMP'];
        $result = $response['RESULT'];
        $orderid = $response['ORDER_ID'];
        $message = $response['MESSAGE'];
        $authcode = $response['AUTHCODE'];
        $pasref = $response['PASREF'];
        $realexsha1 = $response['SHA1HASH'];

        $merchantid = $this->_helper->getConfigData('merchant_id');

        $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result.$message.$pasref.$authcode");

        //Check to see if hashes match or not
        if ($sha1hash !== $realexsha1) {
            return false;
        }

        return true;
    }
}
