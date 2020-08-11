<?php

namespace RealexPayments\HPP\Controller\Apm;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use RealexPayments\HPP\API\RealexPaymentManagementInterface;
use RealexPayments\HPP\Helper\Data;
use RealexPayments\HPP\Logger\Logger;
use RealexPayments\HPP\Model\API\RealexPaymentManagement;

/**
 * Class Result
 *
 * @package RealexPayments\HPP\Controller\Apm
 */
class Result extends Action implements CsrfAwareActionInterface
{
    /**
     * @var Data
     */
    private $_helper;

    /**
     * @var OrderRepository
     */
    private $_orderRepository;

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var UrlInterface
     */
    protected $_url;

    /**
     * @var Logger
     */
    private $_logger;

    /**
     * @var RealexPaymentManagementInterface|RealexPaymentManagement
     */
    private $_paymentManagement;

    /**
     * @var ResultFactory
     */
    private $_resultFactory;

    /**
     * Result constructor.
     *
     * @param Context                          $context
     * @param Data                             $helper
     * @param OrderRepository                  $orderRepository
     * @param Logger                           $logger
     * @param RealexPaymentManagementInterface $paymentManagement
     * @param ResultFactory                    $resultFactory
     */
    public function __construct(
        Context $context,
        Data $helper,
        OrderRepository $orderRepository,
        Logger $logger,
        RealexPaymentManagementInterface $paymentManagement,
        ResultFactory $resultFactory
    )
    {
        $this->_helper            = $helper;
        $this->_orderRepository   = $orderRepository;
        $this->_url               = $context->getUrl();
        $this->_logger            = $logger;
        $this->_paymentManagement = $paymentManagement;
        $this->_resultFactory     = $resultFactory;
        parent::__construct($context);
    }

    /**
     * this is a silent action, there should not be any session or assumption, treat it like CLI
     */
    public function execute()
    {
        $resultRaw = $this->_resultFactory->create(ResultFactory::TYPE_RAW);
        $resultRaw->setHttpResponseCode(503);

        try {
            $response = $this->getRequest()->getParams();

            if ($response) {
                $result = $this->_handleResponse($response);

                if ($result === false) {
                    // if no runtime exception and logic failed, resent notifications are just gonna fail again
                    $resultRaw->setHttpResponseCode(200);
                }
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());

            $resultRaw->setHttpResponseCode(503);
        }

        return $resultRaw;
    }

    /**
     * @param array $response
     *
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    private function _handleResponse($response)
    {
        if (empty($response)) {
            $this->_logger->critical(__('Async - Empty response received from gateway'));

            return false;
        }

        $this->_helper->logDebug(__('Async - Gateway response:') . print_r($this->_helper->stripTrimFields($response), true));

        // validate response
        $authStatus = $this->_validateResponse($response);
        if (!$authStatus) {
            $this->_logger->critical(__('Async - Invalid response received from gateway.'));

            return false;
        }
        //get the actual order id
        [$incrementId, $orderTimestamp] = explode('_', $response['orderid']);

        if ($incrementId) {
            $order = $this->_getOrder($incrementId);
            if ($order->getId()) {
                // process the response
                return $this->_paymentManagement->processResponseApm($order, $response);
            } else {
                $this->_logger->critical(__('Async - Gateway response has an invalid order id.'));

                return false;
            }
        } else {
            $this->_logger->critical(__('Async - Gateway response does not have an order id.'));

            return false;
        }
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
        $timestamp     = $response['timestamp'];
        $result        = $response['result'];
        $orderid       = $response['orderid'];
        $message       = $response['message'];
        $paymentMethod = $response['paymentmethod'];
        $pasref        = $response['pasref'];
        $realexsha1    = $response['sha1hash'];

        $merchantid = $this->_helper->getConfigData('merchant_id');

        $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result.$message.$pasref.$paymentMethod");

        //Check to see if hashes match or not
        if ($sha1hash !== $realexsha1) {
            $this->_logger->critical('Hashes dont match.');
            return false;
        }

        return true;
    }

    /**
     * Get order based on increment id.
     *
     * @param $incrementId
     *
     * @return Order
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function _getOrder($incrementId)
    {
        if (!$this->_order) {
            $this->_order = $this->_orderRepository->get($incrementId);
        }

        return $this->_order;
    }

    /** @inheritDoc */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /** @inheritDoc */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

}
