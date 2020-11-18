<?php

namespace RealexPayments\HPP\Model;

use Magento\Sales\Model\Order as parentOrder;

class Order extends parentOrder
{
    /**
     * {@inheritdoc}
     */
    public function canHold()
    {
        // Hold is not supported when payment method is Paypal.
        return $this->isOrderWithPaypal() ? false : parent::canHold();
    }

    /**
     * {@inheritdoc}
     */
    public function canUnhold()
    {
        // Unhold is not supported when payment method is Paypal.
        return $this->isOrderWithPaypal() ? false : parent::canUnhold();
    }

    /**
     * {@inheritdoc}
     */
    public function hold()
    {
        $method = $this->getPayment()->getMethodInstance();
        if ($method->getCode() == 'realexpayments_hpp') {
            $method->hold($this->getPayment());
        }
        return parent::hold();
    }

    /**
     * {@inheritdoc}
     */
    public function unhold()
    {
        $method = $this->getPayment()->getMethodInstance();

        if ($method->getCode() == 'realexpayments_hpp') {
            $method->acceptPayment($this->getPayment());
        }

        return parent::unhold();
    }

    /**
     * @return $this
     */
    public function reconcile()
    {
        $method = $this->getPayment()->getMethodInstance();

        if ($this->getData('method') == 'realexpayments_hpp') {
            $method->reconcile($this);
        }

        return $this;
    }

    /**
     * Retrieve order reconcile availability.
     *
     * @return bool
     */
    public function canReconcile()
    {
        $reconcileStates = [
            self::STATE_NEW,
            self::STATE_PENDING_PAYMENT,
        ];
        if (!in_array($this->getState(), $reconcileStates)) {
            return false;
        }

        $additionalInfo = $this->getPayment()->getAdditionalInformation();
        if (isset($additionalInfo['PASREF']) || !isset($additionalInfo['ORDER_ID'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if order was created using Paypal.
     *
     * @return bool
     */
    protected function isOrderWithPaypal()
    {
        $payment = $this->getPayment();

        if ($payment->getMethod() == PaymentMethod::METHOD_CODE) {
            $additionalInformation = $payment->getAdditionalInformation();
            if (isset($additionalInformation['PAYMENTMETHOD'])
                && $additionalInformation['PAYMENTMETHOD'] == 'paypal'
            ) {
                return true;
            }
        }

        return false;
    }
}
