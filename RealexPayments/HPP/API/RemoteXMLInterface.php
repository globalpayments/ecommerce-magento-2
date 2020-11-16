<?php

namespace RealexPayments\HPP\API;

/**
 * Interface RemoteXMLInterface.
 *
 * @api
 */
interface RemoteXMLInterface
{
    /**
     * Settle a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param int                                  $amount
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function settle($payment, $amount);

    /**
     * Multi-Settle a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param int                                  $amount
     * @param bool                                 $complete
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function multisettle($payment, $amount, $complete = false);

    /**
     * Rebate a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface                $payment
     * @param int                                                 $amount
     * @param array \Magento\Sales\Model\Order\Creditmemo\Comment $comments
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function rebate($payment, $amount, $comments);

    /**
     * Void a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface                $payment
     * @param array \Magento\Sales\Model\Order\Creditmemo\Comment $comments
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function void($payment, $comments);

    /**
     * Update a payer with Realex using the Remote API.
     *
     * @param string                                       $merchantId
     * @param string                                       $account
     * @param string                                       $payerRef
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function payerEdit($merchantId, $account, $payerRef, $customer);

    /**
     * Release a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface                $payment
     * @param array \Magento\Sales\Model\Order\Creditmemo\Comment $comments
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function releasePayment($payment, $comments);

    /**
     * Hold a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface                $payment
     * @param array \Magento\Sales\Model\Order\Creditmemo\Comment $comments
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function holdPayment($payment, $comments);

    /**
     *  Query the status of a transaction with Realex using the Remote API.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    public function query($payment);
}
