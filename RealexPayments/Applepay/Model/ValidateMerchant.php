<?php
namespace RealexPayments\Applepay\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ValidateMerchant {


    protected $scopeConfig;
    protected $configProvider;
    protected $storeManager;
    protected $_paymentLogger;
    protected $_logger;
    protected $quoteIdMaskFactory;
    protected $quoteRepository;
    protected $_encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManagerInterface,
        \RealexPayments\Applepay\Logger\Logger $paymentLogger,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManagerInterface;
        $this->_paymentLogger = $paymentLogger;
        $this->_logger = $logger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository = $quoteRepository;
        $this->_encryptor = $encryptor;
    }

    public function getConfigParam($code) {
        $fullConfigPath = 'payment/realexpayments_applepay/' . $code;
        $fallBackConfigPath = 'payment/realexpayments_hpp/' . $code;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $configValue = $this->scopeConfig->getValue($fullConfigPath, $storeScope);
        if ( is_null($configValue) ) {
            $configValue = $this->scopeConfig->getValue($fallBackConfigPath, $storeScope);
        }
        return $configValue;
    }

    public function getEnvironment() {
        return $this->getConfigParam('environment');
    }

    public function getIsDebug() {
        return $this->getConfigParam('debug_log');
    }

    public function isSandboxMode() {
        if($this->getEnvironment() == 'sandbox') {
            return true;
        }
        return false;
    }

    public function getAppleMerchantId() {
        return $this->getConfigParam('apple_merchant_id');
    }

    public function getAppleMerchantPemPath() {
        return $this->getConfigParam('merchant_pem');
    }

    public function getAppleMerchantKeyPath() {
        return $this->getConfigParam('merchant_key');
    }

    public function getAppleMerchantKeyPassphrase() {
        return $this->_encryptor->decrypt($this->getConfigParam('merchant_key_passphrase'));
    }

    public function getAppleMerchantDomain() {
        return $this->getConfigParam('merchant_domain');
    }

    public function getAppleMerchantDisplayName() {
        return $this->getConfigParam('merchant_display_name');
    }

    /**
     * Returns array
     *
     * @api
     * @param string $validationUrl
     * @param string $quoteId
     * @return array
     */
    public function validateMerchant($validationUrl, $quoteId)
    {

        if($this->getIsDebug()) {
            $this->_paymentLogger->info("Realex processing apple pay validation");
            $this->_paymentLogger->info("Url: " . $validationUrl);
            $this->_paymentLogger->info("Quote ID: " . $quoteId);
        }

        if(!$this->getAppleMerchantId()) {
            $this->_logger->critical("Realex Apple Error: Merchant ID hasn't been set.");
            return null;
        }

        if(!$this->getAppleMerchantPemPath()) {
            $this->_logger->critical("Realex Apple Error: Merchant Pem path hasn't been set.");
            return null;
        }

        if(!$this->getAppleMerchantKeyPath()) {
            $this->_logger->critical("Realex Apple Error: Merchant Key path hasn't been set.");
            return null;
        }

        if(!$this->getAppleMerchantDomain()) {
            $this->_logger->critical("Realex Apple Error: Domain hasn't been set.");
            return null;
        }

        if(!$this->getAppleMerchantDisplayName()) {
            $this->_logger->critical("Realex Apple Error: Display name hasn't been set.");
            return null;
        }

        $validationPayload = array();
        $validationPayload['merchantIdentifier'] = $this->getAppleMerchantId();
        $validationPayload['domainName'] = $this->getAppleMerchantDomain();
        $validationPayload['displayName'] = $this->getAppleMerchantDisplayName();
        $validationPayload['initiative'] = "web";
        $validationPayload['initiativeContext'] = $this->getAppleMerchantDomain();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validationPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
        curl_setopt($ch, CURLOPT_SSLCERT, $this->getAppleMerchantPemPath());
        curl_setopt($ch, CURLOPT_SSLKEY, $this->getAppleMerchantKeyPath());

        if($this->getAppleMerchantKeyPassphrase() != null) {
            curl_setopt($ch, CURLOPT_KEYPASSWD, $this->getAppleMerchantKeyPassphrase());
        }

        $validationResponse = curl_exec($ch);
        curl_close($ch);

        return $validationResponse;
    }
}
