<?php

namespace RealexPayments\HPP\Model\API;

use RealexPayments\HPP\Model\Config\Source\SettleMode;

class RealexPaymentManagement implements \RealexPayments\HPP\API\RealexPaymentManagementInterface
{
    const FRAUD_ACTIVE = 'ACTIVE';
    const FRAUD_HOLD = 'HOLD';
    const FRAUD_BLOCK = 'BLOCK';
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var \RealexPayments\HPP\API\RemoteXMLInterface
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

    /**
     * RealexPaymentManagement constructor.
     *
     * @param \RealexPayments\HPP\Helper\Data                                 $helper
     * @param \RealexPayments\HPP\API\RemoteXMLInterface                      $remoteXml
     * @param \Magento\Checkout\Model\Session                                 $session
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \RealexPayments\HPP\Logger\Logger                               $logger
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender             $orderSender
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory                $orderHistoryFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface               $customerRepository
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        \RealexPayments\HPP\API\RemoteXMLInterface $remoteXml,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \RealexPayments\HPP\Logger\Logger $logger,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->_helper = $helper;
        $this->_session = $session;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_logger = $logger;
        $this->_orderSender = $orderSender;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_customerRepository = $customerRepository;
        $this->_remoteXml = $remoteXml;
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

        $confirmedPaymentStatus = $this->_helper->getConfigData('payment_successful', $order->getStoreId());
        if (empty($confirmedPaymentStatus)) {
            $confirmedPaymentStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;
        }

        //Set information
        $payment->setTransactionId($pasref);
        $this->_helper->setAdditionalInfo($payment, $response);
        $payment->setAdditionalInformation('AUTO_SETTLE_FLAG', $settleMode);

        //Add order Transaction
        $this->_addTransaction($payment, $order, $pasref, $response, $isAutoSettle);
        //place payment
        $payment->getMethodInstance()
                ->setIsInitializeNeeded(false);

        $fraud = $this->checkFraud($response, $payment, $isAutoSettle, $pasref, $amount);
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
            $this->_handleCardStorage($response, $customerId);
        }

        return true;
    }

    private function checkFraud($response, $payment, $isAutoSettle, $pasref, $amount)
    {
        $fraud = false;
        if (isset($response['HPP_FRAUDFILTER_RESULT'])) {
            $fraudAction = $response['HPP_FRAUDFILTER_RESULT'];
            if (!isset($response['HPP_FRAUDFILTER_MODE'])
              || (isset($response['HPP_FRAUDFILTER_MODE']) && $response['HPP_FRAUDFILTER_MODE'] == 'ACTIVE')) {
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
     * @param \Magento\Sales\Mode\Order $order
     * @param string                    $pasref
     * @param string                    $amount
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
     * @desc Handles the card storage fields
     *
     * @param array  $response
     * @param string $customerId
     */
    private function _handleCardStorage($response, $customerId)
    {
        try {
            $paymentSetup = isset($response['PMT_SETUP']) ? $response['PMT_SETUP'] : false;
            $payerRef = isset($response['SAVED_PAYER_REF']) ? $response['SAVED_PAYER_REF'] : false;
            //Is there a payment setup?
            if ($paymentSetup) {
                $payerSetup = isset($response['PAYER_SETUP']) ? $response['PAYER_SETUP'] : false;
                //Are we setting up a new payer?
                if ($payerSetup == '00') {
                    //Store payer ref against the customer
                    $this->_storeCustomerPayerRef(
                        $response['MERCHANT_ID'],
                        $response['ACCOUNT'],
                        $payerRef,
                        $customerId
                    );
                }
            }

            $cardRef = isset($response['SAVED_PMT_REF']) ? $response['SAVED_PMT_REF'] : false;
            if ($cardRef) {
                //Store card details
                $this->_helper->logDebug('Customer '.$customerId.' added a new card:'.$cardRef);
            }

            $cardsEdited = isset($response['HPP_EDITED_PMT_REF']) ? $response['HPP_EDITED_PMT_REF'] : false;
            $cardsDeleted = isset($response['HPP_DELETED_PMT_REF']) ? $response['HPP_DELETED_PMT_REF'] : false;
            if ($cardsEdited) {
                $this->_manageEditedCards($cardsEdited);
            }
            if ($cardsDeleted) {
                $this->_manageDeletedCards($cardsDeleted);
            }
        } catch (\Exception $e) {
            //card storage exceptions should not stop a transaction
            $this->_logger->critical($e);
        }
    }

    /**
     * @desc Store the payer ref against the customer
     *
     * @param string $merchantId
     * @param string $account
     * @param string $payerRef
     * @param string $customerId
     */
    private function _storeCustomerPayerRef($merchantId, $account, $payerRef, $customerId)
    {
        $this->_helper->logDebug('Storing payer ref:'.$payerRef.' for customer: '.$customerId);

        $customer = $this->_customerRepository->getById($customerId);
        $customer->setCustomAttribute('realexpayments_hpp_payerref', $payerRef);
        $this->_customerRepository->save($customer);
        //Update payer in realex
        try {
            $this->_remoteXml->payerEdit($merchantId, $account, $payerRef, $customer);
        } catch (\Exception $e) {
            //Let it fail but still setup the rest of the payment
            $this->_logger->critical($e);
        }
    }

    /**
     * @desc Manage cards that were edited while the user was on hpp
     *
     * @param string $cards
     */
    private function _manageEditedCards($cards)
    {
        $this->_helper->logDebug('Customer edited the following cards:'.$cards);
    }

    /**
     * @desc Manage cards that were deleted while the user was on hpp
     *
     * @param string $cards
     */
    private function _manageDeletedCards($cards)
    {
        $this->_helper->logDebug('Customer deleted the following cards:'.$cards);
    }

    /**
     * @desc Called after payment is authorised
     *
     * @param \Magento\Sales\Mode\Order $order
     * @param string                    $pasref
     * @param string                    $amount
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
     * @desc Add a comment to order history
     *
     * @param \Magento\Sales\Mode\Order $order
     * @param string                    $message
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
     * @param array $response
     *
     * @return bool
     */
    private function _validateResponseFields($response)
    {
        if ($response == null ||
           !isset($response['RESULT']) ||
           !isset($response['PASREF']) ||
           !isset($response['AMOUNT']) ||
           $response['RESULT'] != '00' ||
           !ctype_digit($response['AMOUNT'])) {
            return false;
        }

        return true;
    }

    /**
     * @desc Add order transaction
     *
     * @param \Magento\Sales\Mode\Order\Payment $payment
     * @param \Magento\Sales\Mode\Order         $order
     * @param string                            $pasref
     * @param array                             $response
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
