<?php

namespace RealexPayments\HPP\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Api\Data\OrderInterface;
use RealexPayments\HPP\Model\Config\Source\Environment;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{
    const METHOD_CODE = 'realexpayments_hpp';
    const CUSTOMER_ID = 'customer';

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $_encryptor;

    /**
     * @var \Magento\Directory\Model\Config\Source\Country
     */
    private $_country;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    private $_moduleList;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $_quoteRepository;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_realexLogger;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $_productMetadata;

    /**
     * @var \Magento\Framework\Module\ResourceInterface
     */
    private $_resourceInterface;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $_resolver;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $_customerRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $_session;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $_orderFactory;

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    protected $_deploymentConfig;

    protected $_storeId;

    /**
     * Data constructor.
     *
     * @param  \Magento\Framework\App\Helper\Context  $context
     * @param  \Magento\Framework\Encryption\EncryptorInterface  $encryptor
     * @param  \Magento\Directory\Model\Config\Source\Country  $country
     * @param  \Magento\Quote\Api\CartRepositoryInterface  $quoteRepository
     * @param  \Magento\Framework\Module\ModuleListInterface  $moduleList
     * @param  \Magento\Store\Model\StoreManagerInterface  $storeManager
     * @param  \RealexPayments\HPP\Logger\Logger  $realexLogger
     * @param  \Magento\Framework\App\ProductMetadataInterface  $productMetadata
     * @param  \Magento\Framework\Module\ResourceInterface  $resourceInterface
     * @param  \Magento\Framework\Locale\ResolverInterface  $resolver
     * @param  \Magento\Customer\Api\CustomerRepositoryInterface  $customerRepository
     * @param  \Magento\Customer\Model\Session  $session
     * @param  \Magento\Framework\App\DeploymentConfig  $deploymentConfig
     * @param  \Magento\Sales\Model\OrderFactory  $orderFactory
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Directory\Model\Config\Source\Country $country,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \RealexPayments\HPP\Logger\Logger $realexLogger,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Module\ResourceInterface $resourceInterface,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\Session $session,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->_country = $country;
        $this->_moduleList = $moduleList;
        $this->_quoteRepository = $quoteRepository;
        $this->_storeManager = $storeManager;
        $this->_realexLogger = $realexLogger;
        $this->_productMetadata = $productMetadata;
        $this->_resourceInterface = $resourceInterface;
        $this->_resolver = $resolver;
        $this->_customerRepository = $customerRepository;
        $this->_session = $session;
        $this->_deploymentConfig = $deploymentConfig;
        $this->_orderFactory = $orderFactory;
    }

    public function setStoreId($storeId)
    {
        $this->_storeId = $storeId;
        return $this;
    }

    /**
     * @desc Sign fields
     *
     * @return string
     */
    public function signFields($fields, $account = null)
    {
        //do we need to use a specific config
        if (!isset($account)) {
            $account = 'shared_secret';
        }
        $secret = $this->getEncryptedConfigData($account);
        $sha1hash = sha1($fields);
        $tmp = "$sha1hash.$secret";

        return sha1($tmp);
    }

    /**
     * @desc Sign query fields.
     *
     * @param string $fields
     *
     * @return string
     */
    public function signQueryFields($fields)
    {
        $sha1hash = sha1($fields);
        $tmp = "$sha1hash. ";

        return sha1($tmp);
    }

    /**
     * @desc Check if configuration is set to sandbox mode
     *
     * @return bool
     */
    public function isSandboxMode()
    {
        return $this->getConfigData('environment') == Environment::ENVIRONMENT_SANDBOX;
    }

    /**
     * @desc Get hpp form url
     *
     * @return string
     */
    public function getFormUrl()
    {
        if ($this->isSandboxMode()) {
            return $this->getConfigData('sandbox_payment_url');
        }

        return $this->getConfigData('live_payment_url');
    }

    /**
     * @desc Get remote api url
     *
     * @return string
     */
    public function getRemoteApiUrl()
    {
        if ($this->isSandboxMode()) {
            return $this->getConfigData('sandbox_api_url');
        }

        return $this->getConfigData('live_api_url');
    }

    /**
     * @desc Sets all the fields that is posted to HPP for a OTB Card transaction
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOTBFormFields()
    {
        if (!$this->_session->isLoggedIn()) {
            return [];
        }

        $timestamp = strftime('%Y%m%d%H%M%S');
        $merchantId = trim($this->getConfigData('merchant_id'));
        $merchantAccount = trim($this->getConfigData('merchant_account'));
        $fieldOrderId = uniqid() . '_' . $timestamp;
        $orderCurrencyCode = $this->_storeManager->getStore()->getBaseCurrency()->getCode();
        $amount = 0;
        $customer = $this->_session->getCustomer();
        $customerBillingAddress = $customer->getDefaultBillingAddress();
        $customerShippingAddress = $customer->getDefaultShippingAddress();
        $customerId = $customer->getId();
        $settleMode = $this->getConfigData('settle_mode');
        $autoSettle = ($settleMode == \RealexPayments\HPP\Model\Config\Source\SettleMode::SETTLEMODE_AUTO) ? '1' : '0';
        $cardPaymentText = $this->getConfigData('card_btn_text');
        $realexLang = $this->getConfigData('lang');
        $varRef = self::CUSTOMER_ID . '_' . $customerId;
        $prodId = '';
        $shopperLocale = $this->_resolver->getLocale();
        $otbEnabled = true;
        $iframeEnabled = '1';

        $formFields = [];
        $formFields['MERCHANT_ID'] = $merchantId;
        $formFields['ACCOUNT'] = $merchantAccount;
        $formFields['ORDER_ID'] = $fieldOrderId;
        $formFields['AMOUNT'] = $amount;
        $formFields['CURRENCY'] = $orderCurrencyCode;
        $formFields['TIMESTAMP'] = $timestamp;
        $formFields['AUTO_SETTLE_FLAG'] = $autoSettle;
        $formFields['CUST_NUM'] = $customerId;
        $formFields['VAR_REF'] = $varRef;
        $formFields['PROD_ID'] = $prodId;
        $formFields['HPP_VERSION'] = '2';
        $formFields['HPP_CUSTOMER_EMAIL'] = $customer->getEmail();
        $formFields['MAGENTO_CUSTOMER_ID'] = $customerId;
        if (isset($realexLang) && !empty($realexLang)) {
            $formFields['HPP_LANG'] = $realexLang;
        }
        if (isset($cardPaymentText) && !empty($cardPaymentText)) {
            $formFields['CARD_PAYMENT_BUTTON'] = $cardPaymentText;
        }
        if (isset($paymentMethods) && !empty($paymentMethods)) {
            $formFields['PM_METHODS'] = $paymentMethods;
        }
        if ($otbEnabled) {
            $formFields['VALIDATE_CARD_ONLY'] = '1';
        }
        $baseUrl = $this->_storeManager->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);

        if ($iframeEnabled) {
            $formFields['HPP_POST_DIMENSIONS'] = $baseUrl;
        }

        $formFields['MERCHANT_RESPONSE_URL'] =
            $this->getMerchantBaseResponseUrl() . '/realexpayments_hpp/cards/result';

        $hppBillingFields = [
            'HPP_BILLING_STREET1' => $customerBillingAddress->getStreetLine(1),
            'HPP_BILLING_STREET2' => $customerBillingAddress->getStreetLine(2),
            'HPP_BILLING_STREET3' => $customerBillingAddress->getStreetLine(3),
            'HPP_BILLING_CITY' => $customerBillingAddress->getCity(),
            'HPP_BILLING_POSTALCODE' => $customerBillingAddress->getPostcode(),
            'HPP_BILLING_COUNTRY' => $this->getCountryNumericCode($customerBillingAddress->getCountryId())
        ];
        $hppShippingFields = [
            'HPP_SHIPPING_STREET1' => $customerShippingAddress->getStreetLine(1),
            'HPP_SHIPPING_STREET2' => $customerShippingAddress->getStreetLine(2),
            'HPP_SHIPPING_STREET3' => $customerShippingAddress->getStreetLine(3),
            'HPP_SHIPPING_CITY' => $customerShippingAddress->getCity(),
            'HPP_SHIPPING_POSTALCODE' => $customerShippingAddress->getPostcode(),
            'HPP_SHIPPING_COUNTRY' => $this->getCountryNumericCode($customerShippingAddress->getCountryId())
        ];

        if (array_values($hppBillingFields) === array_values($hppShippingFields)) {
            $formFields['HPP_ADDRESS_MATCH_INDICATOR'] = 'TRUE';
        } else {
            $formFields['HPP_ADDRESS_MATCH_INDICATOR'] = 'FALSE';
        }

        $hppAddressFields = array_merge($hppBillingFields, $hppShippingFields);

        foreach ($hppAddressFields as $hppProp => $hppValue) {
            $formFields[$hppProp] = $hppValue;
        }

        //Load payer ref customer attribute
        $payerAttr = $this->_customerRepository->getById($customerId)
            ->getCustomAttribute('realexpayments_hpp_payerref');
        $payerRef = (isset($payerAttr) && $payerAttr != null) ? $payerAttr->getValue() : '';

        $formFields = $this->setCardStorageFields($formFields, $payerRef);
        $fieldsToSign = "$timestamp.$merchantId.$fieldOrderId.$amount.$orderCurrencyCode.$payerRef.";

        $sha1hash = $this->signFields($fieldsToSign);
        $this->logDebug('Gateway Request:' . print_r($this->stripFields($formFields), true));

        $formFields['SHA1HASH'] = $sha1hash;
        // Sort the array by key using SORT_STRING order
        ksort($formFields, SORT_STRING);

        return $formFields;
    }

    /**
     * Set Card Storage Fields.
     *
     * @param array $formFields
     * @param string $payerRef
     *
     * @return $array
     */
    public function setCardStorageFields($formFields, $payerRef)
    {
        if (!isset($payerRef) || $payerRef == '') {
            $formFields['CARD_STORAGE_ENABLE'] = '1';
            $formFields['PAYER_EXIST'] = '0';
            $formFields['PAYER_REF'] = '';
            $formFields['PMT_REF'] = '';
        } else {
            $formFields['HPP_SELECT_STORED_CARD'] = $payerRef;
            $formFields['PAYER_EXIST'] = '1';
        }
        $formFields['OFFER_SAVE_CARD'] = $this->getConfigData('card_offer_save');

        return $formFields;
    }

    public function getDMSessionId()
    {
        $sessionId = $this->_session->getData('DM_SessionId');
        if (!isset($sessionId) || empty($sessionId)) {
            $sessionId = uniqid();
            $this->_session->setData('DM_SessionId', $sessionId);
        }

        return $sessionId;
    }

    /**
     * @desc Logs debug information if enabled
     *
     * @param mixed
     */
    public function logDebug($message)
    {
        if ($this->getConfigData('debug_log') == '1') {
            $this->_realexLogger->debug($message);
        }
    }

    /**
     * @desc Cancels the order
     *
     * @param \Magento\Sales\Model\Order $order
     */
    public function cancelOrder($order)
    {
        $orderStatus = $this->getConfigData('payment_cancelled');
        $order->setActionFlag($orderStatus, true);
        $order->cancel()->save();
    }

    /**
     * @desc Load a quote based on id
     *
     * @param $quoteId
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote($quoteId)
    {
        // get quote from quoteId
        $quote = $this->_quoteRepository->get($quoteId);

        return $quote;
    }

    /**
     * @desc Removes the response fields that we don't want stored
     *
     * @param array $response
     *
     * @return array
     */
    public function stripFields($response)
    {
        if ($this->isSandboxMode()) {
            return $response;
        }
        $returnedFields = [];
        $excludedFields = [
            'SHA1HASH',
            'REFUNDHASH',
            'EXPDATE',
            'SAVED_PMT_EXPDATE',
        ];
        foreach ($response as $key => $field) {
            if (!in_array(strtoupper($key), $excludedFields)) {
                $returnedFields[$key] = $field;
            }
        }
        if (array_key_exists('CARDTYPE', $returnedFields) && $returnedFields['CARDTYPE'] == 'SWITCH') {
            $returnedFields['CARDTYPE'] = 'MC';
        }

        return $returnedFields;
    }

    /**
     * @desc Trims the response card digits field to only contain the last 4
     *
     * @param array $response
     *
     * @return array
     */
    public function trimCardDigits($response)
    {
        if (isset($response['CARDDIGITS']) && strlen($response['CARDDIGITS']) > 4) {
            $response['CARDDIGITS'] = substr($response['CARDDIGITS'], -4);
        }
        if (isset($response['SAVED_PMT_DIGITS']) && strlen($response['SAVED_PMT_DIGITS']) > 4) {
            $response['SAVED_PMT_DIGITS'] = substr($response['SAVED_PMT_DIGITS'], -4);
        }

        return $response;
    }

    /**
     * @desc Strips and trims the response and returns a new array of fields
     *
     * @param array $response
     *
     * @return array
     */
    public function stripTrimFields($response)
    {
        $fields = $this->stripFields($response);

        return $this->trimCardDigits($fields);
    }

    /**
     * @desc Strips and trims the xml and returns the new xml
     *
     * @param string $xml
     *
     * @return string
     */
    public function stripXML($xml)
    {
        $patterns = [
            '/(<sha1hash>).+(<\/sha1hash>)/',
            '/(<md5hash>).+(<\/md5hash>)/',
            '/(<refundhash>).+(<\/refundhash>)/',
        ];

        return preg_replace($patterns, '', $xml);
    }

    /**
     * @desc Converts the magento decimal amount into a int one used by Realex
     *
     * @param float $amount
     * @param string $currencyCode
     *
     * @return int
     */
    public function amountFromMagento($amount, $currencyCode)
    {
        $minor = $this->_getCurrencyMinorUnit($currencyCode);

        return round($amount * $minor);
    }

    /**
     * @desc Converts the realex int amount into a decimal one used by Realex
     *
     * @param string $amount
     * @param string $currencyCode
     *
     * @return float
     */
    public function amountFromRealex($amount, $currencyCode)
    {
        $minor = $this->_getCurrencyMinorUnit($currencyCode);

        return floatval($amount) / $minor;
    }

    /**
     * @desc Gets the amount of currency minor units. This would be used to divide or
     * multiply with. eg. cents with 2 minor units would mean 10^2 = 100
     *
     * @param string $currencyCode
     *
     * @return int
     */
    private function _getCurrencyMinorUnit($currencyCode)
    {
        if ($this->checkForFirstMinorUnit($currencyCode)) {
            return 1;
        }
        switch ($currencyCode) {
            case 'BHD':
            case 'IQD':
            case 'JOD':
            case 'KWD':
            case 'LYD':
            case 'OMR':
            case 'TND':
                return 1000;
            case 'CLF':
                return 10000;
        }

        return 100;
    }

    private function checkForFirstMinorUnit($currencyCode)
    {
        return in_array(
            $currencyCode,
            [
                'BYR',
                'BIF',
                'CLP',
                'DJF',
                'GNF',
                'ISK',
                'KMF',
                'KRW',
                'PYG',
                'RWF',
                'UGX',
                'UYI',
                'VUV',
                'VND',
                'XAF',
                'XOF',
                'XPF',
            ]
        );
    }

    /**
     * @desc Sets additional information fields on the payment class
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param array $response
     */
    public function setAdditionalInfo($payment, $response)
    {
        $fields = $this->stripFields($response);
        foreach ($fields as $key => $value) {
            $payment->setAdditionalInformation($key, $value);
        }
    }

    /**
     * @desc Gives back configuration values
     *
     * @param $field
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        return $this->getConfig($field, self::METHOD_CODE, $storeId);
    }

    /**
     * @return bool
     */
    public function isApmEnabled() {
        // if apm is gonna be controlled via a shop setting at some point
        // pending transactions that did not receive a final status by the time the setting was turned off won't be processed

        return true;
    }

    /**
     * @desc Gives back configuration values as flag
     *
     * @param $field
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfig($field, self::METHOD_CODE, $storeId, true);
    }

    /**
     * @desc Gives back encrypted configuration values
     *
     * @param $field
     * @param null $storeId
     *
     * @return mixed
     */
    public function getEncryptedConfigData($field, $storeId = null)
    {
        return $this->_encryptor->decrypt(trim($this->getConfigData($field, $storeId)));
    }

    /**
     * @desc Retrieve information from payment configuration
     *
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     *
     * @return bool|mixed
     */
    public function getConfig($field, $paymentMethodCode, $storeId, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if (!$storeId) {
            $storeId = $this->_storeId;
        }

        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore();
        }

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    public function getMerchantBaseResponseUrl()
    {
        $mode = $this->_deploymentConfig->get(\Magento\Framework\App\State::PARAM_MODE);
        $devUrl = $this->_deploymentConfig->get('dev_realexpayments_hpp_response_url');

        if ($mode == \Magento\Framework\App\State::MODE_DEVELOPER && $devUrl) {
            $responseUrl = $devUrl;
        } else {
            $responseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        }

        return trim($responseUrl, '/');
    }

    /**
     * @param OrderInterface $order
     *
     * @return bool
     */
    public function isOrderPendingPayment($order)
    {
        return array_key_exists(
                $order->getStatus(),
                $order->getConfig()->getStateStatuses(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
            );
    }

    /**
     * Get order based on increment id.
     *
     * @param $incrementId
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrderByIncrement($incrementId)
    {
        return $this->_orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * @return array
     */
    private function getCountryNumericCodes()
    {
        return [
            'AF' => '004',
            'AX' => '248',
            'AL' => '008',
            'DZ' => '012',
            'AS' => '016',
            'AD' => '020',
            'AO' => '024',
            'AI' => '660',
            'AQ' => '010',
            'AG' => '028',
            'AR' => '032',
            'AM' => '051',
            'AW' => '533',
            'AU' => '036',
            'AT' => '040',
            'AZ' => '031',
            'BS' => '044',
            'BH' => '048',
            'BD' => '050',
            'BB' => '052',
            'BY' => '112',
            'BE' => '056',
            'BZ' => '084',
            'BJ' => '204',
            'BM' => '060',
            'BT' => '064',
            'BO' => '068',
            'BQ' => '535',
            'BA' => '070',
            'BW' => '072',
            'BV' => '074',
            'BR' => '076',
            'IO' => '086',
            'BN' => '096',
            'BG' => '100',
            'BF' => '854',
            'BI' => '108',
            'CV' => '132',
            'KH' => '116',
            'CM' => '120',
            'CA' => '124',
            'KY' => '136',
            'CF' => '140',
            'TD' => '148',
            'CL' => '152',
            'CN' => '156',
            'CX' => '162',
            'CC' => '166',
            'CO' => '170',
            'KM' => '174',
            'CG' => '178',
            'CD' => '180',
            'CK' => '184',
            'CR' => '188',
            'CI' => '384',
            'HR' => '191',
            'CU' => '192',
            'CW' => '531',
            'CY' => '196',
            'CZ' => '203',
            'DK' => '208',
            'DJ' => '262',
            'DM' => '212',
            'DO' => '214',
            'EC' => '218',
            'EG' => '818',
            'SV' => '222',
            'GQ' => '226',
            'ER' => '232',
            'EE' => '233',
            'ET' => '231',
            'SZ' => '748',
            'FK' => '238',
            'FO' => '234',
            'FJ' => '242',
            'FI' => '246',
            'FR' => '250',
            'GF' => '254',
            'PF' => '258',
            'TF' => '260',
            'GA' => '266',
            'GM' => '270',
            'GE' => '268',
            'DE' => '276',
            'GH' => '288',
            'GI' => '292',
            'GR' => '300',
            'GL' => '304',
            'GD' => '308',
            'GP' => '312',
            'GU' => '316',
            'GT' => '320',
            'GG' => '831',
            'GN' => '324',
            'GW' => '624',
            'GY' => '328',
            'HT' => '332',
            'HM' => '334',
            'VA' => '336',
            'HN' => '340',
            'HK' => '344',
            'HU' => '348',
            'IS' => '352',
            'IN' => '356',
            'ID' => '360',
            'IR' => '364',
            'IQ' => '368',
            'IE' => '372',
            'IM' => '833',
            'IL' => '376',
            'IT' => '380',
            'JM' => '388',
            'JP' => '392',
            'JE' => '832',
            'JO' => '400',
            'KZ' => '398',
            'KE' => '404',
            'KI' => '296',
            'KP' => '408',
            'KR' => '410',
            'KW' => '414',
            'KG' => '417',
            'LA' => '418',
            'LV' => '428',
            'LB' => '422',
            'LS' => '426',
            'LR' => '430',
            'LY' => '434',
            'LI' => '438',
            'LT' => '440',
            'LU' => '442',
            'MO' => '446',
            'MK' => '807',
            'MG' => '450',
            'MW' => '454',
            'MY' => '458',
            'MV' => '462',
            'ML' => '466',
            'MT' => '470',
            'MH' => '584',
            'MQ' => '474',
            'MR' => '478',
            'MU' => '480',
            'YT' => '175',
            'MX' => '484',
            'FM' => '583',
            'MD' => '498',
            'MC' => '492',
            'MN' => '496',
            'ME' => '499',
            'MS' => '500',
            'MA' => '504',
            'MZ' => '508',
            'MM' => '104',
            'NA' => '516',
            'NR' => '520',
            'NP' => '524',
            'NL' => '528',
            'NC' => '540',
            'NZ' => '554',
            'NI' => '558',
            'NE' => '562',
            'NG' => '566',
            'NU' => '570',
            'NF' => '574',
            'MP' => '580',
            'NO' => '578',
            'OM' => '512',
            'PK' => '586',
            'PW' => '585',
            'PS' => '275',
            'PA' => '591',
            'PG' => '598',
            'PY' => '600',
            'PE' => '604',
            'PH' => '608',
            'PN' => '612',
            'PL' => '616',
            'PT' => '620',
            'PR' => '630',
            'QA' => '634',
            'RE' => '638',
            'RO' => '642',
            'RU' => '643',
            'RW' => '646',
            'BL' => '652',
            'SH' => '654',
            'KN' => '659',
            'LC' => '662',
            'MF' => '663',
            'PM' => '666',
            'VC' => '670',
            'WS' => '882',
            'SM' => '674',
            'ST' => '678',
            'SA' => '682',
            'SN' => '686',
            'RS' => '688',
            'SC' => '690',
            'SL' => '694',
            'SG' => '702',
            'SX' => '534',
            'SK' => '703',
            'SI' => '705',
            'SB' => '090',
            'SO' => '706',
            'ZA' => '710',
            'GS' => '239',
            'SS' => '728',
            'ES' => '724',
            'LK' => '144',
            'SD' => '729',
            'SR' => '740',
            'SJ' => '744',
            'SE' => '752',
            'CH' => '756',
            'SY' => '760',
            'TW' => '158',
            'TJ' => '762',
            'TZ' => '834',
            'TH' => '764',
            'TL' => '626',
            'TG' => '768',
            'TK' => '772',
            'TO' => '776',
            'TT' => '780',
            'TN' => '788',
            'TR' => '792',
            'TM' => '795',
            'TC' => '796',
            'TV' => '798',
            'UG' => '800',
            'UA' => '804',
            'AE' => '784',
            'GB' => '826',
            'US' => '840',
            'UM' => '581',
            'UY' => '858',
            'UZ' => '860',
            'VU' => '548',
            'VE' => '862',
            'VN' => '704',
            'VG' => '092',
            'VI' => '850',
            'WF' => '876',
            'EH' => '732',
            'YE' => '887',
            'ZM' => '894',
            'ZW' => '716',
        ];
    }

    /**
     * @param $alpha2
     *
     * @return mixed|string
     */
    public function getCountryNumericCode($alpha2)
    {
        $countries = $this->getCountryNumericCodes();

        return isset($countries[$alpha2]) ? $countries[$alpha2] : '';
    }
}
