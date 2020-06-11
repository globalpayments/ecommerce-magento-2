<?php

namespace RealexPayments\HPP\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class RealexPaymentsConfigProvider implements ConfigProviderInterface
{
    /**
     * @var PaymentHelper
     */
    private $_paymentHelper;

    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var string[]
     */
    protected $_methodCodes = [
        'realexpayments_hpp',
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    private $methods = [];

    /**
     * RealexPaymentsConfigProvider constructor.
     *
     * @param PaymentHelper                   $paymentHelper
     * @param \RealexPayments\HPP\Helper\Data $helper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \RealexPayments\HPP\Helper\Data $helper
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_helper = $helper;

        foreach ($this->_methodCodes as $code) {
            $this->methods[$code] = $this->_paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * Set configuration for RealexPayments HPP.
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'realexpayments_hpp' => [
                ],
            ],
        ];

        foreach ($this->_methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment'] [$code]['redirectUrl'] = $this->getMethodRedirectUrl($code);
                $config['payment'] [$code]['iframeEnabled'] = $this->_helper->getConfigData('iframe_enabled');
                $config['payment'] [$code]['iframeMode'] = $this->_helper->getConfigData('iframe_mode');
            }
        }

        return $config;
    }

    /**
     * Return redirect URL for method.
     *
     * @param string $code
     *
     * @return mixed
     */
    private function getMethodRedirectUrl($code)
    {
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }
}
