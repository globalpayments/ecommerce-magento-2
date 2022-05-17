<?php

namespace RealexPayments\HPP\Model\Api;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use RealexPayments\HPP\Model\Config\Source\SettleMode;
use RealexPayments\HPP\Helper\CardStorage as CardStorageHelper;

class RealexPaymentManagement implements \RealexPayments\HPP\Api\RealexPaymentManagementInterface
{
    const FRAUD_ACTIVE = 'ACTIVE';
    const FRAUD_HOLD = 'HOLD';
    const FRAUD_BLOCK = 'BLOCK';
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var CardStorageHelper
     */
    private $cardStorageHelper;

    /**
     * @var \RealexPayments\HPP\Api\RemoteXMLInterface
     */
    private $_remoteXml;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_session;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    private $_transactionBuilder;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_logger;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $_orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    private $_orderHistoryFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $_customerRepository;

    /** @var OrderRepositoryInterface */
    private $_orderRepository;

    /**
     * RealexPaymentManagement constructor.
     *
     * @param \RealexPayments\HPP\Helper\Data $helper
     * @param CardStorageHelper $cardStorageHelper
     * @param \RealexPayments\HPP\Api\RemoteXMLInterface $remoteXml
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \RealexPayments\HPP\Logger\Logger $logger
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        CardStorageHelper $cardStorageHelper,
        \RealexPayments\HPP\Api\RemoteXMLInterface $remoteXml,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \RealexPayments\HPP\Logger\Logger $logger,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\OrderRepository $orderRepository
    ) {
        $this->_helper = $helper;
        $this->cardStorageHelper = $cardStorageHelper;
        $this->_session = $session;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_logger = $logger;
        $this->_orderSender = $orderSender;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_customerRepository = $customerRepository;
        $this->_remoteXml = $remoteXml;
        $this->_orderRepository = $orderRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function processResponse($order, $response)
    {
        $payment = $order->getPayment();
        if (!$this->_validateResponseFields($response)) {
            try {
                $response = $this->_helper->trimCardDigits($response);
                $this->_helper->setAdditionalInfo($payment, $response);
                $order->save();
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }

            return false;
        }

        $pasref = $response['PASREF'];

        $amount = $this->_helper->amountFromRealex($response['AMOUNT'], $order->getBaseCurrencyCode());
        $response = $this->_helper->trimCardDigits($response);

        $settleMode = $this->_helper->getConfigData('settle_mode', $order->getStoreId());
        $isAutoSettle = $settleMode == SettleMode::SETTLEMODE_AUTO;

        $confirmedPaymentStatus = $this->getDefaultPaymentSuccessfulStatus($order);

        //Set information
        $payment->setTransactionId($pasref);
        $this->_helper->setAdditionalInfo($payment, $response);
        $payment->setAdditionalInformation('AUTO_SETTLE_FLAG', $settleMode);

        //Add order Transaction
        $this->_addTransaction($payment, $order, $pasref, $response, $isAutoSettle);
        //place payment
        $payment->getMethodInstance()
            ->setIsInitializeNeeded(false);

        $fraud = $this->checkFraud($response, $order, $payment, $isAutoSettle, $pasref, $amount);
        $payment->place();
        if ($fraud) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW)
                ->setStatus(\Magento\Sales\Model\Order::STATUS_FRAUD);
        } else {
            //Write comment
            $this->_paymentIsAuthorised($order, $pasref, $amount);

            //Should we invoice
            if ($isAutoSettle) {
                $this->_invoice($order, $pasref, $amount);
            }

            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus($confirmedPaymentStatus);
        }

        $order->save();
        //Send order email
        if (!$order->getEmailSent() && !$fraud) {
            $this->_orderSender->send($order);
        }

        //Store payer details if applicable
        $customerId = $order->getCustomerId();
        if (!empty($customerId)) {
            $this->cardStorageHelper->handleCardStorage($response, $customerId);
        }

        return true;
    }

    /**
     * @param  OrderInterface|Order  $order
     * @param  array  $response
     *
     * @return bool
     * @throws \Exception
     */
    public function processResponseApm($order, $response)
    {
        if (!$this->_helper->isApmEnabled()) {
            return false;
        }
        if (!$this->_helper->isOrderPendingPayment($order)) {
            return false;
        }

        $payment = $order->getPayment();

        // only 00/successful should be a final result in APM too, in this particular case (Status URL updates)
        $isValidResponse = !(
            $response == null ||
            !isset($response['result']) ||
            !isset($response['pasref']) ||
            !in_array($response['result'], ['00'])
        );

        if (!$isValidResponse) {
            try {
                $this->_helper->setAdditionalInfo($payment, $response);
                $this->_orderRepository->save($order);

                $this->_helper->cancelOrder($order);
            } catch (\Exception $e) {
                $this->_logger->critical($e->getMessage());
            }

            $this->_logger->critical("Async - Response fields couldn't be validated");
            return false;
        }

        $pasref = $response['pasref'];

        $settleMode = $this->_helper->getConfigData('settle_mode', $order->getStoreId());
        $isAutoSettle = $settleMode == SettleMode::SETTLEMODE_AUTO;

        $confirmedPaymentStatus = $this->getDefaultPaymentSuccessfulStatus($order);

        //Set information
        $payment->setTransactionId($pasref);
        $this->_helper->setAdditionalInfo($payment, $response);
        $payment->setAdditionalInformation('AUTO_SETTLE_FLAG', $settleMode);

        //Add order Transaction
        $this->_addTransaction($payment, $order, $pasref, $response, $isAutoSettle);

        //place payment
        $payment
            ->getMethodInstance()
            ->setIsInitializeNeeded(false);

        $payment->place();

        //Write comment
        $this->_paymentIsAuthorised($order, $pasref, $order->getTotalDue());

        //Should we invoice
        if ($isAutoSettle) {
            $this->_invoice($order, $pasref, $order->getBaseGrandTotal());
        }

        $order
            ->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->setStatus($confirmedPaymentStatus);

        $this->_orderRepository->save($order);
        //Send order email
        if (!$order->getEmailSent()) {
            $this->_orderSender->send($order);
        }

        return true;
    }

    private function checkFraud($response, $order, $payment, $isAutoSettle, $pasref, $amount)
    {
        $fraud = false;
        if (isset($response['HPP_FRAUDFILTER_RESULT'])) {
            $fraudAction = $response['HPP_FRAUDFILTER_RESULT'];
            if (
                !isset($response['HPP_FRAUDFILTER_MODE'])
                || (
                    isset($response['HPP_FRAUDFILTER_MODE'])
                    && $response['HPP_FRAUDFILTER_MODE'] == 'ACTIVE'
                )
            ) {
                if ($fraudAction == self::FRAUD_HOLD) {
                    //send order for review
                    $payment->setIsFraudDetected(true)
                        ->setIsTransactionPending(true);
                    $fraud = true;
                    //Should we invoice
                    if ($isAutoSettle) {
                        $this->_invoice($order, $pasref, $amount);
                    }
                }
                if ($fraudAction == self::FRAUD_BLOCK) {
                    //send order for decline
                    $payment->setIsFraudDetected(true)
                        ->setIsTransactionPending(true);
                    $fraud = true;
                }
            }
        }

        return $fraud;
    }

    /**
     * @param  Order  $order
     *
     * @return bool|mixed
     */
    public function getDefaultPaymentSuccessfulStatus($order)
    {
        $confirmedPaymentStatus = $this->_helper->getConfigData('payment_successful', $order->getStoreId());
        if (empty($confirmedPaymentStatus)) {
            $confirmedPaymentStatus = $order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
        }

        return $confirmedPaymentStatus;
    }

    /**
     * {@inheritdoc}
     */
    public function restoreCart($cartId)
    {
        $session = $this->_session;
        $order = $session->getLastRealOrder();
        if ($order->getId()) {
            // restore the quote
            if ($session->restoreQuote()) {
                $this->_helper->cancelOrder($order);
            }
        }
    }

    /**
     * @desc Create an invoice
     *
     * @param  \Magento\Sales\Model\Order  $order
     * @param  string  $pasref
     * @param  string  $amount
     */
    private function _invoice($order, $pasref, $amount)
    {
        $invoice = $order->prepareInvoice();
        $invoice->getOrder()->setIsInProcess(true);

        // set transaction id so you can do a online refund from credit memo
        $invoice->setTransactionId($pasref);
        $invoice->register()
            ->pay()
            ->save();

        $message = __(
            'Invoiced amount of %1 Transaction ID: %2',
            $order->getBaseCurrency()->formatTxt($amount),
            $pasref
        );
        $this->_addHistoryComment($order, $message);
    }

    /**
     * @desc Called after payment is authorised
     *
     * @param  \Magento\Sales\Model\Order  $order
     * @param  string  $pasref
     * @param  string  $amount
     */
    private function _paymentIsAuthorised($order, $pasref, $amount)
    {
        $message = __(
            'Authorised amount of %1 Transaction ID: %2',
            $order->getBaseCurrency()->formatTxt($amount),
            $pasref
        );
        $this->_addHistoryComment($order, $message);
    }

    /**
     * @param  OrderInterface|Order  $order
     * @param  string  $message
     */
    public function addHistoryComment($order, $message)
    {
        $this->_addHistoryComment($order, $message);
    }

    /**
     * @desc Add a comment to order history
     *
     * @param  \Magento\Sales\Model\Order  $order
     * @param  string  $message
     */
    private function _addHistoryComment($order, $message)
    {
        $history = $this->_orderHistoryFactory->create()
            ->setComment($message)
            ->setEntityName('order')
            ->setOrder($order);

        $history->save();
    }

    /**
     * @desc Validates the response fields
     *
     * @param  array  $response
     *
     * @return bool
     */
    private function _validateResponseFields($response)
    {
        // 01 means pending in terms of APM and is a valid result code
        $successfulResultCodes = $this->isTransactionApm($response) ? ['00', '01'] : ['00'];
        if ($response == null ||
            !isset($response['RESULT']) ||
            !isset($response['PASREF']) ||
            !isset($response['AMOUNT']) ||
            !in_array($response['RESULT'], $successfulResultCodes) ||
            !ctype_digit($response['AMOUNT'])) {
            return false;
        }

        return true;
    }

    /**
     * @param $response
     *
     * @return bool
     */
    public function isTransactionApm($response)
    {
        return $this->_helper->isApmEnabled() && !isset($response['AUTHCODE']);
    }

    /**
     * @desc Add order transaction
     *
     * @param  \Magento\Sales\Model\Order\Payment  $payment
     * @param  \Magento\Sales\Model\Order  $order
     * @param  string  $pasref
     * @param  array  $response
     */
    private function _addTransaction($payment, $order, $pasref, $response, $isAutoSettle)
    {
        $type = $isAutoSettle
            ? \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE
            : \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH;
        $transaction = $this->_transactionBuilder
            ->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($pasref)
            ->setFailSafe(true)
            ->build($type);
        $transaction->setAdditionalInformation(
            \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
            $this->_helper->stripFields($response)
        )->setIsClosed(false);

        return $transaction;
    }
}
