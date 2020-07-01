<?php

namespace RealexPayments\HPP\Model;

use Magento\Sales\Model\Order as parentOrder;

class Order extends parentOrder
{
    /**
     * @return $this
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
     * @return $this
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
}
