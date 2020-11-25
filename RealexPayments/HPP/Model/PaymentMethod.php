<?php

namespace RealexPayments\HPP\Model;

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;
use Magento\Sales\Model\Order\Address;
use RealexPayments\HPP\Model\Config\Source\ChallengePreference;
use RealexPayments\HPP\Model\Config\Source\DMFields;
use RealexPayments\HPP\Model\Config\Source\FraudMode;
use RealexPayments\HPP\Model\Config\Source\SettleMode;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
{
    const METHOD_CODE = 'realexpayments_hpp';
    const NOT_AVAILABLE = 'N/A';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var GUEST_ID , used when order is placed by guests
     */
    const GUEST_ID = 'guest';
    /**
     * @var CUSTOMER_ID , used when order is placed by customers
     */
    const CUSTOMER_ID = 'customer';

    /**
     * @var string
     */
    protected $_infoBlockType = 'RealexPayments\HPP\Block\Info\Info';

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canCaptureOnce = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * @var bool
     */
    protected $_canReviewPayment = true;

    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var \RealexPayments\HPP\Api\RemoteXMLInterface
     */
    private $_remoteXml;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $_urlBuilder;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $_resolver;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_realexLogger;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var \Magento\Framework\Module\ResourceInterface
     */
    protected $_resourceInterface;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_session;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $_customerRepository;

    /**
     * PaymentMethod constructor.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \RealexPayments\HPP\Helper\Data $helper
     * @param \RealexPayments\HPP\Api\RemoteXMLInterface $remoteXml
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \RealexPayments\HPP\Logger\Logger $realexLogger
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\Module\ResourceInterface $resourceInterface
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \RealexPayments\HPP\Helper\Data $helper,
        \RealexPayments\HPP\Api\RemoteXMLInterface $remoteXml,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \RealexPayments\HPP\Logger\Logger $realexLogger,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Module\ResourceInterface $resourceInterface,
        \Magento\Checkout\Model\Session $session,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_urlBuilder = $urlBuilder;
        $this->_helper = $helper;
        $this->_remoteXml = $remoteXml;
        $this->_storeManager = $storeManager;
        $this->_resolver = $resolver;
        $this->_request = $request;
        $this->_realexLogger = $realexLogger;
        $this->_productMetadata = $productMetadata;
        $this->_resourceInterface = $resourceInterface;
        $this->_session = $session;
        $this->_customerRepository = $customerRepository;
    }

    /**
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initialize($paymentAction, $stateObject)
    {
        /*
         * do not send order confirmation mail after order creation wait for
         * result confirmation from realex
         */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
        $storeId = $order->getStoreId();

        $status = $this->_helper->getConfigData('order_status', $storeId);
        if (!$status || array_key_exists(
            $status,
            $order->getConfig()->getStateStatuses(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
        )) {
            $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        } else {
            $state = \Magento\Sales\Model\Order::STATE_NEW;
        }

        $stateObject->setState($state);
        $stateObject->setStatus($status);
        $stateObject->setIsNotified(false);
    }

    /**
     * Assign data to info model instance.
     *
     * @param \Magento\Framework\DataObject|mixed $data
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        if (!$data instanceof \Magento\Framework\DataObject) {
            $data = new \Magento\Framework\DataObject($data);
        }

        $additionalData = $data->getAdditionalData();
        $infoInstance = $this->getInfoInstance();

        return $this;
    }

    /**
     * Checkout redirect URL.
     *
     * @return string
     * @see \Magento\Quote\Model\Quote\Payment::getCheckoutRedirectUrl()
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl(
            'realexpayments_hpp/process/process',
            ['_secure' => $this->_getRequest()->isSecure()]
        );
    }

    /**
     * Retrieve request object.
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }

    /**
     * Post request to gateway and return response.
     *
     * @param DataObject $request
     * @param ConfigInterface $config
     */
    public function postRequest(DataObject $request, ConfigInterface $config)
    {
        // Do nothing
        $this->_helper->logDebug('Gateway postRequest called');
    }

    /**
     * @desc Get hpp form url
     *
     * @return string
     */
    public function getFormUrl()
    {
        return $this->_helper->getFormUrl();
    }

    /**
     * @desc Sets all the fields that is posted to HPP
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFormFields()
    {
        $paymentInfo = $this->getInfoInstance();
        $order = $paymentInfo->getOrder();
        $timestamp = $paymentInfo->getAdditionalInformation('TIMESTAMP');
        if (!$timestamp) {
            $timestamp = strftime('%Y%m%d%H%M%S');
        }
        $merchantId = trim($this->_helper->getConfigData('merchant_id'));
        $merchantAccount = trim($this->_helper->getConfigData('merchant_account'));
        $realOrderId = $order->getRealOrderId();
        $fieldOrderId = $realOrderId . '_' . $timestamp;
        $orderCurrencyCode = $order->getBaseCurrencyCode();
        $amount = $this->_helper->amountFromMagento($order->getBaseGrandTotal(), $orderCurrencyCode);
        $customerId = $order->getCustomerId();
        $settleMode = $this->_helper->getConfigData('settle_mode');
        switch ($settleMode) {
            case SettleMode::SETTLEMODE_AUTO:
                $autoSettle = '1';
                break;
            case SettleMode::SETTLEMODE_MULTI:
                $autoSettle = 'MULTI';
                break;
            default:
                $autoSettle = '0';
                break;
        }
        $cardPaymentText = $this->_helper->getConfigData('payment_btn_text');
        $realexLang = $this->_helper->getConfigData('lang');
        $paymentMethods = $this->_helper->getConfigData('payment_methods');
        $varRef = $this->_helper->getConfigData('hpp_desc');
        $prodId = '';
        $shopperLocale = $this->_resolver->getLocale();
        $otbEnabled = $this->_helper->getConfigData('otb_enabled');
        $iframeEnabled = $this->_helper->getConfigData('iframe_enabled');

        if ($order->getBillingAddress()) {
            $billingCountryCode = $order->getBillingAddress()->getCountryId();
            $street = $order->getBillingAddress()->getStreet();
            if (isset($street[0]) && $billingCountryCode == "GB") {
                $addresBit = preg_replace('/\D/', '', $street[0]);
                if (strlen($addresBit) > 5) {
                    $addresBit = substr($addresBit, 0, 5);
                }
            } else {
                $addresBit = $street[0];
            }
            $postalBit = $order->getBillingAddress()->getPostcode();
            if ($billingCountryCode == "GB") {
                $postalBit = preg_replace('/\D/', '', $postalBit);
                if (strlen($postalBit) > 5) {
                    $postalBit = substr($postalBit, 0, 5);
                }
            }
            $billingPostalCode = $postalBit . '|' . $addresBit;
            /** @var \Magento\Sales\Model\Order $order */
            $billingFirstName = $order->getBillingAddress()->getFirstName();
            $billingLastName  = $order->getBillingAddress()->getLastname();
        } else {
            $billingCountryCode = '';
            $billingPostalCode = '';
            $billingFirstName = '';
            $billingLastName = '';
        }
        if ($order->getShippingAddress()) {
            $shippingCountryCode = $order->getShippingAddress()->getCountryId();
            $street = $order->getShippingAddress()->getStreet();
            if (isset($street[0]) && $shippingCountryCode == "GB") {
                $addresBit = preg_replace('/\D/', '', $street[0]);
                if (strlen($addresBit) > 5) {
                    $addresBit = substr($addresBit, 0, 5);
                }
            } else {
                $addresBit = $street[0];
            }
            $postalBit = $order->getShippingAddress()->getPostcode();
            if ($shippingCountryCode == "GB") {
                $postalBit = preg_replace('/\D/', '', $postalBit);
                if (strlen($postalBit) > 5) {
                    $postalBit = substr($postalBit, 0, 5);
                }
            }
            $shippingPostalCode = $postalBit . '|' . $addresBit;
        } else {
            $shippingCountryCode = '';
            $shippingPostalCode = '';
        }

        $formFields = [];
        $formFields['MERCHANT_ID'] = $merchantId;
        $formFields['ACCOUNT'] = $merchantAccount;
        $formFields['ORDER_ID'] = $fieldOrderId;
        $formFields['AMOUNT'] = $amount;
        $formFields['CURRENCY'] = $orderCurrencyCode;
        $formFields['TIMESTAMP'] = $timestamp;
        $formFields['AUTO_SETTLE_FLAG'] = $autoSettle;
        $formFields['SHIPPING_CODE'] = $shippingPostalCode;
        $formFields['SHIPPING_CO'] = $shippingCountryCode;
        $formFields['BILLING_CODE'] = $billingPostalCode;
        $formFields['BILLING_CO'] = $billingCountryCode;
        $formFields['CUST_NUM'] = empty($customerId) ? self::GUEST_ID : $customerId;
        $formFields['VAR_REF'] = $varRef;
        $formFields['PROD_ID'] = $prodId;
        $formFields['HPP_VERSION'] = '2';
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
        $baseUrl = $this->_storeManager->getStore($this->getStore())
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);

        if ($iframeEnabled) {
            $formFields['HPP_POST_DIMENSIONS'] = $baseUrl;
        }
        $formFields = $this->setDMFields($formFields, $order);
        $formFields = $this->setAPMFields($formFields, $order->getShippingAddress());
        $formFields['MERCHANT_RESPONSE_URL'] =
            $this->_helper->getMerchantBaseResponseUrl() . '/realexpayments_hpp/process/result';

        $cardStoreEnabled = $this->_helper->getConfigData('card_storage_enabled');
        if ($cardStoreEnabled && !empty($customerId)) {
            //Load payer ref customer attribute
            $payerAttr = $this->_customerRepository->getById($customerId)
                ->getCustomAttribute('realexpayments_hpp_payerref');
            $payerRef = (isset($payerAttr) && $payerAttr != null) ? $payerAttr->getValue() : '';

            $formFields = $this->setCardStorageFields($formFields, $payerRef);
            $fieldsToSign = "$timestamp.$merchantId.$fieldOrderId.$amount.$orderCurrencyCode.$payerRef.";
        } else {
            $fieldsToSign = "$timestamp.$merchantId.$fieldOrderId.$amount.$orderCurrencyCode";
        }
        //Fraud mode
        $fraudMode = $this->_helper->getConfigData('fraud_mode');
        if (isset($fraudMode) && !empty($fraudMode) && $fraudMode != FraudMode::FRAUDMODE_DEFAULT) {
            $formFields['HPP_FRAUDFILTER_MODE'] = $fraudMode;
            $fieldsToSign = $fieldsToSign . '.' . $fraudMode;
        }
        $sha1hash = $this->_helper->signFields($fieldsToSign);

        // additional hpp fields
        /** @var \Magento\Sales\Model\Order $order */
        /** @var Address $billingAddress */
        $billingAddress = $order->getBillingAddress();

        $hppChallengeRequestOptions = [
            ChallengePreference::CHALLENGE_NO_PREFERENCE => 'NO_PREFERENCE',
            ChallengePreference::CHALLENGE_NO_CHALLENGE => 'NO_CHALLENGE_REQUESTED',
            ChallengePreference::CHALLENGE_3DS_PREFERENCE => 'CHALLENGE_PREFERRED',
            ChallengePreference::CHALLENGE_3DS_MANDATE => 'CHALLENGE_MANDATED'
        ];

        //$hppChallengeRequestPreference = $this->_helper->getConfigData('hpp_challenge_preference') ?: "01";

        $additionalHppData = [
            //"HPP_CHALLENGE_REQUEST_INDICATOR" => isset($hppChallengeRequestOptions[$hppChallengeRequestPreference]) ? $hppChallengeRequestOptions[$hppChallengeRequestPreference] : "NO_PREFERENCE"
        ];

        $additionalHppData[] = [
            // customer fields
            "HPP_CUSTOMER_EMAIL" => $order->getCustomerEmail(),
        ];

        $phoneCodes = $this->getCountryPhoneCodes();
        $billingPhoneNumber = $billingAddress->getTelephone();
        if ($billingPhoneNumber && isset($phoneCodes[$billingAddress->getCountryId()])) {
            $phoneCode = $phoneCodes[$billingAddress->getCountryId()];

            $formattedPhoneNumber = preg_replace("/^0+|[^\d]/", '', $billingPhoneNumber);
            if (substr($formattedPhoneNumber, 0, strlen($phoneCode)) === $phoneCode) {
                $formattedPhoneNumber = substr($formattedPhoneNumber, strlen($phoneCode));
            }

            if (is_string($formattedPhoneNumber)) {
                $additionalHppData["HPP_CUSTOMER_PHONENUMBER_MOBILE"] = $phoneCode . '|' . $formattedPhoneNumber;
            }
        }

        $hppBillingFields = [
            "HPP_BILLING_STREET1" => $billingAddress->getStreetLine(1),
            "HPP_BILLING_STREET2" => $billingAddress->getStreetLine(2),
            "HPP_BILLING_STREET3" => $billingAddress->getStreetLine(3),
            "HPP_BILLING_CITY" => $billingAddress->getCity(),
            "HPP_BILLING_STATE" => in_array(
                $billingAddress->getCountryId(),
                ['US', 'CA']
            ) ? $billingAddress->getRegionCode() : '',
            "HPP_BILLING_POSTALCODE" => $billingAddress->getPostcode(),
            "HPP_BILLING_COUNTRY" => $this->getCountryNumericCode($billingAddress->getCountryId()),
        ];
        $additionalHppData[] = $hppBillingFields;

        $isOrderVirtual = $order->getIsVirtual();
        $shippingAddress = $order->getShippingAddress();

        $hppShippingFields = [
            "HPP_SHIPPING_STREET1" => !$isOrderVirtual && $shippingAddress ? $shippingAddress->getStreetLine(1) : '',
            "HPP_SHIPPING_STREET2" => !$isOrderVirtual && $shippingAddress ? $shippingAddress->getStreetLine(2) : '',
            "HPP_SHIPPING_STREET3" => !$isOrderVirtual && $shippingAddress ? $shippingAddress->getStreetLine(3) : '',
            "HPP_SHIPPING_CITY" => !$isOrderVirtual && $shippingAddress ? $shippingAddress->getCity() : '',
            "HPP_SHIPPING_STATE" => !$isOrderVirtual && $shippingAddress ? (in_array(
                $shippingAddress->getCountryId(),
                ['US', 'CA']
            ) ? $shippingAddress->getRegionCode() : '') : '',
            "HPP_SHIPPING_POSTALCODE" => !$isOrderVirtual && $shippingAddress ? $shippingAddress->getPostcode() : '',
            "HPP_SHIPPING_COUNTRY" => !$isOrderVirtual && $shippingAddress ? $this->getCountryNumericCode(
                $shippingAddress->getCountryId()
            ) : ''
        ];

        // order and type does matter
        if (array_values($hppBillingFields) === array_values($hppShippingFields)) {
            $additionalHppData["HPP_ADDRESS_MATCH_INDICATOR"] = "TRUE";
            $additionalHppData[] = $hppShippingFields;
        } else {
            $additionalHppData["HPP_ADDRESS_MATCH_INDICATOR"] = "FALSE";
            $additionalHppData[] = $hppShippingFields;
        }

        $additionalHppData["COMMENT1"] = 'magento2';

        if ($this->_helper->isApmEnabled()) {
            $additionalHppData["HPP_CUSTOMER_COUNTRY"]   = $billingCountryCode;
            $additionalHppData["HPP_CUSTOMER_FIRSTNAME"] = $billingFirstName;
            $additionalHppData["HPP_CUSTOMER_LASTNAME"]  = $billingLastName;
            $additionalHppData["HPP_TX_STATUS_URL"]      = $this->_helper->getMerchantBaseResponseUrl() . '/realexpayments_hpp/apm/result';
        }

        foreach ($additionalHppData as $additionalHppProp => $additionalHppValue) {
            if (is_array($additionalHppValue)) {
                foreach ($additionalHppValue as $additionalHppPropChild => $additionalHppValueChild) {
                    $formFields[$additionalHppPropChild] = $additionalHppValueChild;
                }
            } else {
                $formFields[$additionalHppProp] = $additionalHppValue;
            }
        }

        $this->_helper->logDebug(
            'Gateway Request:' .
            print_r($this->_helper->stripFields($formFields), true)
        );

        $formFields['SHA1HASH'] = $sha1hash;
        // Sort the array by key using SORT_STRING order
        ksort($formFields, SORT_STRING);

        return $formFields;
    }

    private function getCountryPhoneCodes()
    {
        return [
            'AD' => '376',
            'AE' => '971',
            'AF' => '93',
            'AG' => '1268',
            'AI' => '1264',
            'AL' => '355',
            'AM' => '374',
            'AN' => '599',
            'AO' => '244',
            'AQ' => '672',
            'AR' => '54',
            'AS' => '1684',
            'AT' => '43',
            'AU' => '61',
            'AW' => '297',
            'AZ' => '994',
            'BA' => '387',
            'BB' => '1246',
            'BD' => '880',
            'BE' => '32',
            'BF' => '226',
            'BG' => '359',
            'BH' => '973',
            'BI' => '257',
            'BJ' => '229',
            'BL' => '590',
            'BM' => '1441',
            'BN' => '673',
            'BO' => '591',
            'BR' => '55',
            'BS' => '1242',
            'BT' => '975',
            'BW' => '267',
            'BY' => '375',
            'BZ' => '501',
            'CA' => '1',
            'CC' => '61',
            'CD' => '243',
            'CF' => '236',
            'CG' => '242',
            'CH' => '41',
            'CI' => '225',
            'CK' => '682',
            'CL' => '56',
            'CM' => '237',
            'CN' => '86',
            'CO' => '57',
            'CR' => '506',
            'CU' => '53',
            'CV' => '238',
            'CX' => '61',
            'CY' => '357',
            'CZ' => '420',
            'DE' => '49',
            'DJ' => '253',
            'DK' => '45',
            'DM' => '1767',
            'DO' => '1809',
            'DZ' => '213',
            'EC' => '593',
            'EE' => '372',
            'EG' => '20',
            'ER' => '291',
            'ES' => '34',
            'ET' => '251',
            'FI' => '358',
            'FJ' => '679',
            'FK' => '500',
            'FM' => '691',
            'FO' => '298',
            'FR' => '33',
            'GA' => '241',
            'GB' => '44',
            'GD' => '1473',
            'GE' => '995',
            'GH' => '233',
            'GI' => '350',
            'GL' => '299',
            'GM' => '220',
            'GN' => '224',
            'GQ' => '240',
            'GR' => '30',
            'GT' => '502',
            'GU' => '1671',
            'GW' => '245',
            'GY' => '592',
            'HK' => '852',
            'HN' => '504',
            'HR' => '385',
            'HT' => '509',
            'HU' => '36',
            'ID' => '62',
            'IE' => '353',
            'IL' => '972',
            'IM' => '44',
            'IN' => '91',
            'IQ' => '964',
            'IR' => '98',
            'IS' => '354',
            'IT' => '39',
            'JM' => '1876',
            'JO' => '962',
            'JP' => '81',
            'KE' => '254',
            'KG' => '996',
            'KH' => '855',
            'KI' => '686',
            'KM' => '269',
            'KN' => '1869',
            'KP' => '850',
            'KR' => '82',
            'KW' => '965',
            'KY' => '1345',
            'KZ' => '7',
            'LA' => '856',
            'LB' => '961',
            'LC' => '1758',
            'LI' => '423',
            'LK' => '94',
            'LR' => '231',
            'LS' => '266',
            'LT' => '370',
            'LU' => '352',
            'LV' => '371',
            'LY' => '218',
            'MA' => '212',
            'MC' => '377',
            'MD' => '373',
            'ME' => '382',
            'MF' => '1599',
            'MG' => '261',
            'MH' => '692',
            'MK' => '389',
            'ML' => '223',
            'MM' => '95',
            'MN' => '976',
            'MO' => '853',
            'MP' => '1670',
            'MR' => '222',
            'MS' => '1664',
            'MT' => '356',
            'MU' => '230',
            'MV' => '960',
            'MW' => '265',
            'MX' => '52',
            'MY' => '60',
            'MZ' => '258',
            'NA' => '264',
            'NC' => '687',
            'NE' => '227',
            'NG' => '234',
            'NI' => '505',
            'NL' => '31',
            'NO' => '47',
            'NP' => '977',
            'NR' => '674',
            'NU' => '683',
            'NZ' => '64',
            'OM' => '968',
            'PA' => '507',
            'PE' => '51',
            'PF' => '689',
            'PG' => '675',
            'PH' => '63',
            'PK' => '92',
            'PL' => '48',
            'PM' => '508',
            'PN' => '870',
            'PR' => '1',
            'PT' => '351',
            'PW' => '680',
            'PY' => '595',
            'QA' => '974',
            'RO' => '40',
            'RS' => '381',
            'RU' => '7',
            'RW' => '250',
            'SA' => '966',
            'SB' => '677',
            'SC' => '248',
            'SD' => '249',
            'SE' => '46',
            'SG' => '65',
            'SH' => '290',
            'SI' => '386',
            'SK' => '421',
            'SL' => '232',
            'SM' => '378',
            'SN' => '221',
            'SO' => '252',
            'SR' => '597',
            'ST' => '239',
            'SV' => '503',
            'SY' => '963',
            'SZ' => '268',
            'TC' => '1649',
            'TD' => '235',
            'TG' => '228',
            'TH' => '66',
            'TJ' => '992',
            'TK' => '690',
            'TL' => '670',
            'TM' => '993',
            'TN' => '216',
            'TO' => '676',
            'TR' => '90',
            'TT' => '1868',
            'TV' => '688',
            'TW' => '886',
            'TZ' => '255',
            'UA' => '380',
            'UG' => '256',
            'US' => '1',
            'UY' => '598',
            'UZ' => '998',
            'VA' => '39',
            'VC' => '1784',
            'VE' => '58',
            'VG' => '1284',
            'VI' => '1340',
            'VN' => '84',
            'VU' => '678',
            'WF' => '681',
            'WS' => '685',
            'XK' => '381',
            'YE' => '967',
            'YT' => '262',
            'ZA' => '27',
            'ZM' => '260',
            'ZW' => '263',
        ];
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
    private function getCountryNumericCode($alpha2)
    {
        $countries = $this->getCountryNumericCodes();

        return isset($countries[$alpha2]) ? $countries[$alpha2] : '';
    }

    /**
     * Set Alternate Payment Method Fields.
     *
     * @param array $formFields
     * @param \Magento\Sales\Model\Order\Address|null $shipping
     *
     * @return $array
     */
    private function setAPMFields($formFields, $shipping)
    {
        $desc = $this->_helper->getConfigData('hpp_desc');
        $formFields['HPP_DESCRIPTOR'] = isset($desc) && !empty($desc) ? $desc : $formFields['VAR_REF'];
        $formFields['SHIPPING_ADDRESS_ENABLE'] = $this->_helper->getConfigData('apm_pass_shipping');
        $formFields['ADDRESS_OVERRIDE'] = $this->_helper->getConfigData('apm_address_override');
        if ($shipping) {
            $lastName = $shipping->getLastname();
            $lastName = isset($lastName) && !empty($lastName) ? ' ' . $lastName : '';
            $city = $shipping->getCity();
            $state = $shipping->getRegionCode();
            $postalCode = $shipping->getPostcode();
            $country = $shipping->getCountryId();
            $phone = $shipping->getTelephone();
            $name = $shipping->getFirstname() . $lastName;
            $street = $shipping->getStreet();
            $formFields['HPP_NAME'] = isset($name) ? $name : self::NOT_AVAILABLE;
            $formFields['HPP_STREET'] = isset($street[0]) ? $street[0] : self::NOT_AVAILABLE;
            $formFields['HPP_STREET2'] = isset($street[1]) ? $street[1] : self::NOT_AVAILABLE;
            $formFields['HPP_CITY'] = isset($city) ? $city : self::NOT_AVAILABLE;
            $formFields['HPP_STATE'] = isset($state) ? $state : self::NOT_AVAILABLE;
            $formFields['HPP_ZIP'] = isset($postalCode) ? $postalCode : self::NOT_AVAILABLE;
            $formFields['HPP_COUNTRY'] = isset($country) ? $country : self::NOT_AVAILABLE;
            $formFields['HPP_PHONE'] = isset($phone) ? $phone : self::NOT_AVAILABLE;
        } else {
            $formFields['SHIPPING_ADDRESS_ENABLE'] = 0;
        }

        return $formFields;
    }

    private function setDMFields($formFields, $order)
    {
        $enabled = $this->_helper->getConfigData('dm_enabled');
        $fields = $this->_helper->getConfigData('dm_fields');
        if (!isset($fields) || empty($fields) || !isset($enabled) || !$enabled) {
            return $formFields;
        }
        $dmProfile = $this->_helper->getConfigData('dm_profile');
        if (isset($dmProfile) && !empty($dmProfile)) {
            $formFields['HPP_FRAUD_DM_DECISIONMANAGERPROFILE'] = $dmProfile;
        }
        $sessionId = $this->_helper->getDMSessionId();
        $formFields['HPP_CUSTOMER_DEVICEFINGERPRINT'] = $sessionId;

        $fields = explode(',', $fields);
        if ($order->getBillingAddress()) {
            $formFields = $this->setDMBilling($formFields, $order, $fields);
        }
        if ($order->getShippingAddress()) {
            $formFields = $this->setDMShipping($formFields, $order, $fields);
        }
        $formFields = $this->setDMCustomer($formFields, $order, $fields);

        if (in_array(DMFields::DM_PRODUCTS_TOTAL, $fields)) {
            $formFields[DMFields::DM_PRODUCTS_TOTAL] = $order->getBaseTotalDue();
        }
        if (in_array(DMFields::DM_FRAUD_HOST, $fields)) {
            $formFields[DMFields::DM_FRAUD_HOST] = $_SERVER['HTTP_HOST'];
        }
        if (in_array(DMFields::DM_FRAUD_COOKIES, $fields)) {
            $formFields[DMFields::DM_FRAUD_COOKIES] = 'Yes';
        }
        if (in_array(DMFields::DM_FRAUD_BROWSER, $fields)) {
            $formFields[DMFields::DM_FRAUD_BROWSER] = $_SERVER['HTTP_USER_AGENT'];
        }
        if (in_array(DMFields::DM_FRAUD_IP, $fields)) {
            $formFields[DMFields::DM_FRAUD_IP] = $order->getRemoteIp();
        }
        if (in_array(DMFields::DM_FRAUD_TENDER, $fields)) {
            $formFields[DMFields::DM_FRAUD_TENDER] = $order->getPayment()->getMethodInstance()->getCode();
        }

        return $formFields;
    }

    private function setDMBilling($formFields, $order, $fields)
    {
        $billing = $order->getBillingAddress();
        $street = $billing->getStreet();
        if (in_array(DMFields::DM_BILL_STR1, $fields)) {
            $formFields[DMFields::DM_BILL_STR1] = isset($street[0]) ? $street[0] : self::NOT_AVAILABLE;
        }
        if (in_array(DMFields::DM_BILL_STR2, $fields)) {
            $formFields[DMFields::DM_BILL_STR2] = isset($street[1]) ? $street[1] : self::NOT_AVAILABLE;
        }
        if (in_array(DMFields::DM_BILL_CITY, $fields)) {
            $formFields[DMFields::DM_BILL_CITY] = $billing->getCity();
        }
        if (in_array(DMFields::DM_BILL_POSTAL, $fields)) {
            $formFields[DMFields::DM_BILL_POSTAL] = $billing->getPostcode();
        }
        if (in_array(DMFields::DM_BILL_STATE, $fields)) {
            $formFields[DMFields::DM_BILL_STATE] = $billing->getRegionCode();
        }
        if (in_array(DMFields::DM_BILL_COUNTRY, $fields)) {
            $formFields[DMFields::DM_BILL_COUNTRY] = $billing->getCountryId();
        }

        return $formFields;
    }

    private function setDMShipping($formFields, $order, $fields)
    {
        $shipping = $order->getShippingAddress();
        $street = $shipping->getStreet();
        if (in_array(DMFields::DM_SHIPPING_FIRST, $fields)) {
            $formFields[DMFields::DM_SHIPPING_FIRST] = $shipping->getFirstname();
        }
        if (in_array(DMFields::DM_SHIPPING_LAST, $fields)) {
            $formFields[DMFields::DM_SHIPPING_LAST] = $shipping->getLastname();
        }
        if (in_array(DMFields::DM_SHIPPING_PHONE, $fields)) {
            $formFields[DMFields::DM_SHIPPING_PHONE] = $shipping->getTelephone();
        }
        if (in_array(DMFields::DM_CUSTOMER_PHONE, $fields)) {
            $formFields[DMFields::DM_CUSTOMER_PHONE] = $shipping->getTelephone();
        }
        if (in_array(DMFields::DM_SHIPPING_METHOD, $fields)) {
            $formFields[DMFields::DM_SHIPPING_METHOD] = $order->getShippingDescription();
        }
        if (in_array(DMFields::DM_SHIPPING_STR1, $fields)) {
            $formFields[DMFields::DM_SHIPPING_STR1] = isset($street[0]) ? $street[0] : self::NOT_AVAILABLE;
        }
        if (in_array(DMFields::DM_SHIPPING_STR2, $fields)) {
            $formFields[DMFields::DM_SHIPPING_STR2] = isset($street[1]) ? $street[1] : self::NOT_AVAILABLE;
        }
        if (in_array(DMFields::DM_SHIPPING_CITY, $fields)) {
            $formFields[DMFields::DM_SHIPPING_CITY] = $shipping->getCity();
        }
        if (in_array(DMFields::DM_SHIPPING_POSTAL, $fields)) {
            $formFields[DMFields::DM_SHIPPING_POSTAL] = $shipping->getPostcode();
        }
        if (in_array(DMFields::DM_SHIPPING_STATE, $fields)) {
            $formFields[DMFields::DM_SHIPPING_STATE] = $shipping->getRegionCode();
        }
        if (in_array(DMFields::DM_SHIPPING_COUNTRY, $fields)) {
            $formFields[DMFields::DM_SHIPPING_COUNTRY] = $shipping->getCountryId();
        }

        return $formFields;
    }

    private function setDMCustomer($formFields, $order, $fields)
    {
        if (in_array(DMFields::DM_CUSTOMER_ID, $fields)) {
            $formFields[DMFields::DM_CUSTOMER_ID] = $order->getCustomerId();
        }
        if (in_array(DMFields::DM_CUSTOMER_DOB, $fields)) {
            //return dob with time portion removed. note the space in front
            $formFields[DMFields::DM_CUSTOMER_DOB] = str_replace(' 00:00:00', '', $order->getCustomerDOB());
        }
        if (in_array(DMFields::DM_CUSTOMER_EMAIL_DOMAIN, $fields)) {
            $email = $order->getCustomerEmail();
            if (isset($email)) {
                $atIndex = strpos($email, '@');
                if ($atIndex > 0) {
                    $formFields[DMFields::DM_CUSTOMER_EMAIL_DOMAIN] = substr($email, $atIndex + 1);
                }
            }
        }
        if (in_array(DMFields::DM_CUSTOMER_EMAIL, $fields)) {
            $formFields[DMFields::DM_CUSTOMER_EMAIL] = $order->getCustomerEmail();
        }
        if (in_array(DMFields::DM_CUSTOMER_FIRST, $fields)) {
            $name = $order->getCustomerFirstname();
            if (!isset($name) || empty($name)) {
                if ($order->getBillingAddress()) {
                    $name = $order->getBillingAddress()->getFirstname();
                }
            }
            $formFields[DMFields::DM_CUSTOMER_FIRST] = $name;
        }
        if (in_array(DMFields::DM_CUSTOMER_LAST, $fields)) {
            $lastName = $order->getCustomerLastname();
            if (!isset($lastName) || empty($lastName)) {
                if ($order->getBillingAddress()) {
                    $lastName = $order->getBillingAddress()->getLastname();
                }
            }
            $formFields[DMFields::DM_CUSTOMER_LAST] = $lastName;
        }

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
    private function setCardStorageFields($formFields, $payerRef)
    {
        return $this->_helper->setCardStorageFields($formFields, $payerRef);
    }

    /**
     * Capture.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::capture($payment, $amount);

        $order = $payment->getOrder();
        $currencyCode = $order->getBaseCurrencyCode();
        $realexAmount = $this->_helper->amountFromMagento($amount, $currencyCode);
        if ($payment->getAdditionalInformation('AUTO_SETTLE_FLAG') != SettleMode::SETTLEMODE_MULTI) {
            $response = $this->_remoteXml->settle($payment, $realexAmount);
        } else {
            $grand_total = $order->getBaseGrandTotal();
            $invoiced_total = $order->getBaseTotalInvoiced();
            $response = $this->_remoteXml->multisettle(
                $payment,
                $realexAmount,
                $invoiced_total + $amount >= $grand_total
            );
        }
        if (!isset($response) || !$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action failed'));
        }
        $fields = $response->toArray();
        if ($fields['RESULT'] != '00') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'The capture action failed. Gateway Response - Error ' . $fields['RESULT'] . ': ' .
                    $fields['MESSAGE']
                )
            );
        }
        $payment->setTransactionId($fields['PASREF'])
            ->setTransactionApproved(true)
            ->setParentTransactionId($payment->getAdditionalInformation('PASREF'))
            ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $fields);

        return $this;
    }

    /**
     * Refund specified amount for payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);
        $order = $payment->getOrder();
        $comments = $payment->getCreditMemo()->getComments();
        $grandTotal = $order->getBaseGrandTotal();
        $currencyCode = $payment->getOrder()->getBaseCurrencyCode();
        $realexAmount = $this->_helper->amountFromMagento($amount, $currencyCode);

        if ($grandTotal == $amount) {
            $response = $this->_remoteXml->rebate($payment, $realexAmount, $comments);
        } else {
            $response = $this->_remoteXml->rebate($payment, $realexAmount, $comments);
            //partial rebate code will come here
        }
        if (!isset($response) || !$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action failed'));
        }
        $fields = $response->toArray();
        if ($fields['RESULT'] != '00') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'The refund action failed. Gateway Response - Error ' . $fields['RESULT'] . ': ' .
                    $fields['MESSAGE']
                )
            );
        }
        $payment->setTransactionId($fields['PASREF'])
            ->setParentTransactionId($payment->getAdditionalInformation('PASREF'))
            ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $fields);

        return $this;
    }

    /**
     * Refund specified amount for payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        parent::void($payment);
        $response = $this->_remoteXml->void($payment, []);
        if (!isset($response) || !$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The void action failed'));
        }
        $fields = $response->toArray();
        if ($fields['RESULT'] != '00') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'The void action failed. Gateway Response - Error ' . $fields['RESULT'] . ': ' .
                    $fields['MESSAGE']
                )
            );
        }
        $payment->setTransactionId($fields['PASREF'])
            ->setParentTransactionId($payment->getAdditionalInformation('PASREF'))
            ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $fields);

        return $this;
    }

    /**
     * Accept under review payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function acceptPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        parent::acceptPayment($payment);
        $response = $this->_remoteXml->releasePayment($payment, []);
        if (!isset($response) || !$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The accept payment action failed'));
        }
        $fields = $response->toArray();
        if ($fields['RESULT'] != '00') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'The accept payment action failed. Gateway Response - Error ' . $fields['RESULT'] .
                    ': ' . $fields['MESSAGE']
                )
            );
        }
        $payment->setTransactionId($fields['PASREF'])
            ->setParentTransactionId($payment->getAdditionalInformation('PASREF'))
            ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $fields);

        return $this;
    }

    public function hold(\Magento\Payment\Model\InfoInterface $payment)
    {
        $response = $this->_remoteXml->holdPayment($payment, []);

        if (!isset($response) || !$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The hold action failed'));
        }
        $fields = $response->toArray();
        if ($fields['RESULT'] != '00') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'The hold action failed. Gateway Response - Error ' . $fields['RESULT'] . ': ' .
                    $fields['MESSAGE']
                )
            );
        }
        $payment->setTransactionId($fields['PASREF'])
            ->setParentTransactionId($payment->getAdditionalInformation('PASREF'))
            ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $fields);

        return $this;
    }

    /**
     * Reconcile order.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function reconcile(\Magento\Payment\Model\InfoInterface $payment)
    {
        $additionalInfo = $payment->getAdditionalInformation();
        $response = $this->_remoteXml->query($payment);

        if (!isset($response) || !$response) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The reconcile action failed'));
        }
        $fields = $response->toArray();
        $fields['AMOUNT'] = $additionalInfo['AMOUNT'];
        if ($fields['RESULT'] != '00') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'The reconcile action failed. Gateway Response - Error ' . $fields['RESULT'] . ': ' .
                    $fields['MESSAGE']
                )
            );
        }

        $payment->setTransactionId($fields['PASREF'])
            ->setParentTransactionId($payment->getAdditionalInformation('PASREF'))
            ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $fields);

        return $this;
    }
}
