<?php

namespace RealexPayments\HPP\Model\Config\Source;

class IframeMode implements \Magento\Framework\Option\ArrayInterface
{
    const IFRAMEMODE_EMBEDDED = 'embedded';
    const IFRAMEMODE_LIGHTBOX = 'lightbox';

    /**
     * Possible iframe modes.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::IFRAMEMODE_EMBEDDED,
                'label' => 'Embedded',
            ],
            [
                'value' => self::IFRAMEMODE_LIGHTBOX,
                'label' => 'Lightbox',
            ],
        ];
    }
}
