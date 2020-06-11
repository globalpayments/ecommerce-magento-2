<?php

namespace RealexPayments\Googlepay\Model\Config\Source;

class PaymentCards implements \Magento\Framework\Option\ArrayInterface
{
    const AMEX = 'AMEX';
    const DISCOVER = 'DISCOVER';
    const INTERAC = 'INTERAC';
    const JCB = 'JCB';
    const MASTERCARD = 'MASTERCARD';
    const VISA = 'VISA';

    /**
     * Possible Card type fields.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::AMEX,
                'label' => 'American Express',
            ],
            [
                'value' => self::DISCOVER,
                'label' => 'Discover',
            ],
            [
                'value' => self::INTERAC,
                'label' => 'Interac',
            ],
            [
                'value' => self::JCB,
                'label' => 'JCB',
            ],
            [
                'value' => self::MASTERCARD,
                'label' => 'Mastercard',
            ],
            [
                'value' => self::VISA,
                'label' => 'Visa',
            ]
        ];
    }
}
