<?php

namespace RealexPayments\HPP\Model\Config\Source\Order\Status;

class NewOrderStatus extends \Magento\Sales\Model\Config\Source\Order\Status
{
    /**
     * @var string[]
     */
    protected $_stateStatuses = [
        \Magento\Sales\Model\Order::STATE_NEW,
        \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
    ];
}
