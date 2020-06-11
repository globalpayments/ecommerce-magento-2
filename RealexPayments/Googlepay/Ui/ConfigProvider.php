<?php

namespace RealexPayments\Googlepay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'realexpayments_googlepay';

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
        $fullConfigPath = 'payment/realexpayments_googlepay/' . $code;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue($fullConfigPath, $storeScope);
    }

    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'google_merchant_name' => $this->getGoogleMerchantName(),
                    'google_merchant_id' => $this->getGoogleMerchantId(),
                    'globalpay_merchant_id' => $this->getGlobalpayMerchantId(),
                    'sandbox' => $this->getIsSandbox(),
                    'success_action' => $this->getActionSuccess(),
                    'google_allowed_cards' => $this->getGoogleAllowedCards(),
                ]
            ]
        ];
    }

    public function getGlobalpayMerchantId() {
        return $this->getConfigParam('globalpay_merchant_id');
    }

    public function getGoogleMerchantName()
    {
        return $this->getConfigParam('google_merchant_name');
    }

    public function getGoogleMerchantId()
    {
        return $this->getConfigParam('google_merchant_id');
    }

    public function getActionSuccess()
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    public function convertConfigStringToArray($string) {
        return explode(",", $string);
    }

    public function getGoogleAllowedCards() {
        return $this->convertConfigStringToArray($this->getConfigParam('google_payment_cards'));
    }

    public function getIsSandbox() {
        if($this->getConfigParam('environment') == 'sandbox') {
            return true;
        }
        return false;
    }
}