<?php

namespace RealexPayments\HPP\Model\Config\Source;

class FraudMode implements \Magento\Framework\Option\ArrayInterface
{
    const FRAUDMODE_PASSIVE = 'PASSIVE';
    const FRAUDMODE_OFF = 'OFF';
    const FRAUDMODE_DEFAULT = 'default';

    /**
     * Possible Fraud Modes.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::FRAUDMODE_PASSIVE,
                'label' => 'Passive',
            ],
            [
                'value' => self::FRAUDMODE_OFF,
                'label' => 'Off',
            ],
            [
                'value' => self::FRAUDMODE_DEFAULT,
                'label' => 'Default',
            ],
        ];
    }
}
