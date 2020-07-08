<?php
namespace RealexPayments\Googlepay\Model;

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
        \RealexPayments\Googlepay\Logger\Logger $paymentLogger,
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
        $fullConfigPath = 'payment/realexpayments_googlepay/' . $code;
        $fallBackConfigPath = 'payment/realexpayments_hpp/' . $code;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $configParam = $this->scopeConfig->getValue($fullConfigPath, $storeScope);
        if ( is_null($configParam) ) {
            $configParam = $this->scopeConfig->getValue($fallBackConfigPath, $storeScope);
        }
        return $configParam;
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

    public function getQuoteFromMask($maskId) {

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskId, 'masked_id');
        if($quoteIdMask) {
            return $this->quoteRepository->get($quoteIdMask->getQuoteId());
        }

        return null;
    }

    public function getQuoteTotal() {

    }

    public function getQuoteCurrency() {

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
            $this->_paymentLogger->info("Realex processing google pay transaction");
            $this->_paymentLogger->info("Quote: " . $quoteId);
            $this->_paymentLogger->info("Token: " . $paymentToken);
        }

        if(!$this->getMerchantId()) {
            $this->_logger->critical("Realex Google Error: Merchant ID hasn't been set.");
        }

        if(!$this->getAccountId()) {
            $this->_logger->critical("Realex Google Error: Account ID hasn't been set.");
        }

        if(!$this->getSharedSecret()) {
            $this->_logger->critical("Realex Google Error: Secret hasn't been set.");
        }

        if(!$quote = $this->getQuoteFromMask($quoteId)) {
            $this->_logger->critical("Realex Google Error: Unable to load quote from masked id.");
        }


        $result = array();
        $result['status'] = false;

        $config = new ServicesConfig();
        $config->merchantId = $this->getMerchantId();
        $config->accountId =  $this->getAccountId();
        $config->sharedSecret = $this->getSharedSecret();
        $config->serviceUrl = $this->getApiUrl();
        ServicesContainer::configure($config);

        if($this->getIsDebug()) {
            $this->_paymentLogger->info($config->merchantId);
            $this->_paymentLogger->info($config->accountId);
            $this->_paymentLogger->info($config->sharedSecret);
        }

        $card = new CreditCardData();
        $card->token = $paymentToken;
        $card->mobileType = EncyptedMobileType::GOOGLE_PAY;

        $response = new Transaction();

        try {

            if($this->getIsDebug()) {
                $this->_paymentLogger->info("Total: " . $quote->getGrandTotal());
                $this->_paymentLogger->info("Currency: " . $quote->getCurrency()->getQuoteCurrencyCode());
            }

            $grandTotal = $quote->getGrandTotal();
            if($this->isSandboxMode()) {
                $grandTotal = 10.00;
            }

            $response = $card->charge($grandTotal)->withCurrency($quote->getCurrency()->getQuoteCurrencyCode())->withModifier(TransactionModifier::ENCRYPTED_MOBILE)->execute();

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
