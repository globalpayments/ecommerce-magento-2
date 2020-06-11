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
}
