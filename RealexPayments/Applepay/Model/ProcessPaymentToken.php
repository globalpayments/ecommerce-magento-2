<?php
namespace RealexPayments\Applepay\Model;

use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\Api\Entities\Enums\TransactionModifier;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Transaction;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ProcessPaymentToken {

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

    public function getMerchantId() {
        return $this->getConfigParam('merchant_id');
    }

    public function getAccountId() {
        return $this->getConfigParam('merchant_account');
    }

    public function getSharedSecret() {
        return $this->_encryptor->decrypt($this->getConfigParam('shared_secret'));
    }

    public function getSandboxUrl() {
        return $this->getConfigParam('sandbox_api_url');
    }

    public function getLiveUrl() {
        return $this->getConfigParam('live_api_url');
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

    public function getApiUrl() {

        $apiUrl = $this->getLiveUrl();

        if($this->isSandboxMode()) {
            $apiUrl = $this->getSandboxUrl();
        }

        return $apiUrl;
    }

    /**
     * Returns array
     *
     * @api
     * @param string $paymentToken
     * @param string $quoteId
     * @return array
     */
    public function processPaymentToken($paymentToken, $quoteId)
    {

        if($this->getIsDebug()) {
            $this->_paymentLogger->info("Realex processing apple pay transaction");
            $this->_paymentLogger->info("Token: " . $paymentToken);
        }

        if(!$this->getMerchantId()) {
            $this->_logger->critical("Realex Apple Error: Merchant ID hasn't been set.");
        }

        if(!$this->getAccountId()) {
            $this->_logger->critical("Realex Apple Error: Account ID hasn't been set.");
        }

        if(!$this->getSharedSecret()) {
            $this->_logger->critical("Realex Apple Error: Secret hasn't been set.");
        }

        $result = array();
        $result['status'] = false;

        $config = new ServicesConfig();
        $config->merchantId = $this->getMerchantId();
        $config->accountId =  $this->getAccountId();
        $config->sharedSecret = $this->getSharedSecret();
        $config->serviceUrl = $this->getApiUrl();
        ServicesContainer::configure($config);

        $card = new CreditCardData();
        $card->token = $paymentToken;
        $card->mobileType = EncyptedMobileType::APPLE_PAY;

        $response = new Transaction();

        try {
            // process an auto-capture authorization
            $response = $card->charge()->withModifier(TransactionModifier::ENCRYPTED_MOBILE)->execute();

            if($this->getIsDebug()) {
                $this->_paymentLogger->debug("Response: " . print_r($response, true));
            }

        } catch (ApiException $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();

            if($this->getIsDebug()) {
                $this->_paymentLogger->debug("Error: " . $e->getMessage());
            }

            $this->_logger->critical("Realex Google Failed: " . $e->getMessage());
        }

        if (isset($response)) {

            if($response->responseCode === '00') {

                $result['status'] = true;
                $result['responseCode'] = $response->responseCode;
                $result['responseMessage'] = $response->responseMessage;
                $result['orderId'] = $response->orderId;
                $result['authorizationCode'] = $response->authorizationCode;
                $result['paymentsReference'] = $response->transactionId;
                $result['schemeReferenceData'] = $response->schemeId;

            }
        }

        return json_encode($result);

    }
}
