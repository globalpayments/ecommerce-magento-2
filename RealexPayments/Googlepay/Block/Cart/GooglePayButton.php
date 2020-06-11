<?php

namespace RealexPayments\Googlepay\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class GooglePayButton extends Template
{

    protected $checkoutSession;
    protected $scopeConfig;
    /**
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        DefaultConfigProvider $defaultConfigProvider,
        CustomerSession $customerSession,
        UrlInterface $url,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->checkoutSession = $checkoutSession;
        $this->payment = $payment;
        $this->defaultConfigProvider = $defaultConfigProvider;
        $this->customerSession = $customerSession;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManagerInterface;
    }

    public function getConfigParam($code) {
        $fullConfigPath = 'payment/realexpayments_googlepay/' . $code;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue($fullConfigPath, $storeScope);
    }

    protected function _toHtml()
    {
        //if($this->canPayWithGooglePay()) {
            return parent::_toHtml();
        //}
    }

    public function hasQuote() {
        if($this->checkoutSession->getQuote()) {
            if($this->checkoutSession->getQuote()->getAllItems()) {
                return true;
            }
        }
        return false;
    }

    public function isGoolgePayExpressEnabled() {
        return $this->getConfigParam('display_at_cart');
    }

    public function canPayWithGooglePay() {

        if($this->hasQuote() && $this->isGoolgePayExpressEnabled()) {
            return true;
        }
        return false;
    }

    public function getGoogleMerchantName()
    {
        return $this->getConfigParam('google_merchant_name');
    }

    public function getGoogleMerchantId()
    {
        return $this->getConfigParam('google_merchant_id');
    }

    public function getMerchantId()
    {
        return $this->getConfigParam('globalpay_merchant_id');
    }

    public function getActionSuccess()
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    public function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getAmount()
    {
        return $this->checkoutSession->getQuote()->getGrandTotal();
    }

    public function getCurrencyCode() {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    public function getStorecode() {
        return $this->storeManager->getStore()->getCode();
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

    public function getGoogleAllowedShippingCountries() {

        if($this->getConfigParam('allowspecific')) {
            return $this->convertConfigStringToArray($this->getConfigParam('specificcountry'));
        }

        return [];
    }


    public function getQuoteId()
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }

        return '';
    }

}
