<?php

namespace RealexPayments\Applepay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'realexpayments_applepay';

    protected $scopeConfig;
    protected $storeManager;
    protected $url;

    public function __construct(
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $url
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManagerInterface;
        $this->url = $url;
    }

    public function getConfigParam($code) {
        $fullConfigPath = 'payment/realexpayments_applepay/' . $code;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue($fullConfigPath, $storeScope);
    }

    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'globalpay_merchant_id' => $this->getGlobalpayMerchantId(),
                    'sandbox' => $this->getIsSandbox(),
                    'success_action' => $this->getActionSuccess(),
                ]
            ]
        ];
    }

    public function getGlobalpayMerchantId() {
        return $this->getConfigParam('globalpay_merchant_id');
    }

    public function getActionSuccess()
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    public function convertConfigStringToArray($string) {
        return explode(",", $string);
    }

    public function getIsSandbox() {
        if($this->getConfigParam('environment') == 'sandbox') {
            return true;
        }
        return false;
    }
}