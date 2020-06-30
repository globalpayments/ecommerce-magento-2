<?php

namespace RealexPayments\HPP\Model\Config\Source;

class SettleMode implements \Magento\Framework\Option\ArrayInterface
{
    const SETTLEMODE_AUTO = 'auto';
    const SETTLEMODE_DELAYED = 'delayed';
    const SETTLEMODE_MULTI = 'multi';

    /**
     * Possible settle modes.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::SETTLEMODE_AUTO,
                'label' => 'Auto Settle',
            ],
            [
                'value' => self::SETTLEMODE_DELAYED,
                'label' => 'Delayed Settle',
            ],
            [
                'value' => self::SETTLEMODE_MULTI,
                'label' => 'Multi Settle',
            ],
        ];
    }
}
