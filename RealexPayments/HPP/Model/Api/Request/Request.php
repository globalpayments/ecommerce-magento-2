<?php

namespace RealexPayments\HPP\Model\Api\Request;

class Request
{
    const TYPE_SETTLE = 'settle';
    const TYPE_MULTISETTLE = 'multisettle';
    const TYPE_REBATE = 'rebate';
    const TYPE_HOLD = 'hold';
    const TYPE_RELEASE = 'release';
    const TYPE_VOID = 'void';
    const TYPE_PAYER_EDIT = 'payer-edit';
    const TYPE_QUERY = 'query';
    const TYPE_PAYMENT_CREDIT = 'payment-credit';
    const TYPE_PAYMENT_VOID = 'payment-void';
    const TYPE_PAYMENT_SETTLE = 'payment-settle';
    const NOT_AVAILABLE = 'N/A';
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;
    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    private $_addressRepository;

    /**
     * @var \XMLWriter
     */
    private $_xmlWriter;

    private $_merchantId;
    private $_account;
    private $_orderId;
    private $_pasRef;
    private $_authCode;
    private $_amount;
    private $_currency;
    private $_comments;
    private $_refundHash;
    private $_type;
    private $_payerRef;
    private $_payer;
    private $_paymentMethod;
    private $_timestamp;
    private $_multiSettleType;

    /**
     * Request constructor.
     *
     * @param  \RealexPayments\HPP\Helper\Data  $helper
     * @param  \Magento\Customer\Api\AddressRepositoryInterface  $addressRepository
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
    ) {
        $this->_helper = $helper;
        $this->_addressRepository = $addressRepository;
    }

    public function setType($type)
    {
        $this->_type = $type;

        return $this;
    }

    public function setMerchantId($merchantId)
    {
        $this->_merchantId = $merchantId;

        return $this;
    }

    public function setAccount($account)
    {
        $this->_account = $account;

        return $this;
    }

    public function setOrderId($orderId)
    {
        $this->_orderId = $orderId;

        return $this;
    }

    public function setPasref($pasRef)
    {
        $this->_pasRef = $pasRef;

        return $this;
    }

    public function setAuthCode($authCode)
    {
        $this->_authCode = $authCode;

        return $this;
    }

    public function setCurrency($currency)
    {
        $this->_currency = $currency;

        return $this;
    }

    public function setAmount($amount)
    {
        $this->_amount = $amount;

        return $this;
    }

    public function setComments($comments)
    {
        $this->_comments = $comments;

        return $this;
    }

    public function setRefundHash($refundHash)
    {
        $this->_refundHash = $refundHash;

        return $this;
    }

    public function setPayer($payer)
    {
        $this->_payer = $payer;

        return $this;
    }

    public function setPayerRef($payerRef)
    {
        $this->_payerRef = $payerRef;

        return $this;
    }

    public function setStoreId($storeId)
    {
        $this->_helper->setStoreId($storeId);
        return $this;
    }

    public function setPaymentMethod($method)
    {
        $this->_paymentMethod = $method;
        return $this;
    }

    public function setMultiSettleType($type)
    {
        $this->_multiSettleType = $type;
        return $this;
    }

    /**
     * @desc Build the request and return the xml
     *
     * @return string
     */
    public function build()
    {
        if ($this->_paymentMethod == 'paypal') {
            if ($this->_type == self::TYPE_PAYMENT_SETTLE) {
                return $this->_buildPaypalSettleRequestXml();
            } else {
                return $this->_buildPaypalRequestXml();
            }
        } elseif ($this->_paymentMethod && $this->_type == self::TYPE_PAYMENT_CREDIT) {
            return $this->_buildApmCreditRequestXml();
        } else {
            return $this->_buildRequestXml();
        }
    }

    private function _buildRequestXml()
    {
        $this->_startXmlWriter();

        $this->_addSimpleElement('merchantId');
        $this->_addSimpleElement('account');
        $this->_addSimpleElement('orderId');
        $this->_addSimpleElement('pasRef');
        $this->_addSimpleElement('authCode');
        $this->_addAmount();
        $this->_addPayer();
        $this->_addComments();
        $this->_addSimpleElement('refundHash');

        $this->_addSha1Hash([
            $this->_timestamp,
            $this->_merchantId,
            $this->_orderId,
            $this->_amount,
            $this->_currency,
            $this->_payerRef,
        ]);

        return $this->_endXmlWriterAndGetXml();
    }

    private function _buildPaypalRequestXml()
    {
        $this->_startXmlWriter();
        $this->_addSimpleElement('merchantId');
        $this->_addSimpleElement('account');
        $this->_addAmount();
        $this->_addSimpleElement('orderId');
        $this->_addSimpleElement('pasRef');
        $this->_addSimpleElement('paymentMethod');

        $this->_xmlWriter->startElement('paymentmethoddetails');
        $this->_xmlWriter->endElement();

        $this->_addComments();

        $this->_addSha1Hash([
            $this->_timestamp,
            $this->_merchantId,
            $this->_orderId,
            $this->_amount,
            $this->_currency,
            $this->_paymentMethod,
        ]);

        $this->_addSimpleElement('refundHash');

        return $this->_endXmlWriterAndGetXml();
    }

    private function _buildPaypalSettleRequestXml()
    {
        $this->_startXmlWriter();
        $this->_addSimpleElement('merchantId');
        $this->_addSimpleElement('account');
        $this->_addAmount();
        $this->_addSimpleElement('orderId');
        $this->_addSimpleElement('pasRef');

        if ($this->_multiSettleType) {
            $this->_xmlWriter->startElement('multisettle');
            $this->_xmlWriter->writeAttribute('type', $this->_multiSettleType);
            $this->_xmlWriter->endElement();
        }

        $this->_addSimpleElement('paymentMethod');
        $this->_xmlWriter->startElement('paymentmethoddetails');
        $this->_xmlWriter->endElement();

        $this->_addComments();

        $this->_addSha1Hash([
            $this->_timestamp,
            $this->_merchantId,
            $this->_orderId,
            $this->_amount,
            $this->_currency,
            $this->_paymentMethod,
        ]);

        return $this->_endXmlWriterAndGetXml();
    }

    private function _buildApmCreditRequestXml()
    {
        $this->_startXmlWriter();
        $this->_addSimpleElement('merchantId');
        $this->_addSimpleElement('account');
        $this->_addAmount();
        $this->_addSimpleElement('orderId');
        $this->_addSimpleElement('pasRef');
        $this->_addSimpleElement('paymentMethod');
        $this->_addComments();

        $this->_addSha1Hash([
            $this->_timestamp,
            $this->_merchantId,
            $this->_orderId,
            $this->_amount,
            $this->_currency,
            $this->_paymentMethod,
        ]);

        $this->_addSimpleElement('refundHash');

        return $this->_endXmlWriterAndGetXml();
    }

    private function _startXmlWriter()
    {
        $this->_xmlWriter = new \XMLWriter();
        $this->_xmlWriter->openMemory();

        $this->_xmlWriter->setIndent(true);
        $this->_xmlWriter->setIndentString(' ');
        $this->_xmlWriter->startDocument('1.0', 'UTF-8');

        // Start the root node.
        $this->_xmlWriter->startElement('request');

        if (isset($this->_type)) {
            $this->_xmlWriter->writeAttribute('type', $this->_type);
        }

        $this->_timestamp = $this->_helper->generateTimestamp();
        $this->_xmlWriter->writeAttribute('timestamp', $this->_timestamp);
    }

    private function _endXmlWriterAndGetXml()
    {
        $this->_xmlWriter->endElement();
        $this->_xmlWriter->endDocument();

        $xml = $this->_xmlWriter->outputMemory();

        $this->_helper->logDebug('Remote XML request:'.$this->_helper->stripXML($xml));

        return $xml;
    }

    private function _addSimpleElement($name)
    {
        $varName = "_$name";
        if (isset($this->{$varName})) {
            $this->_xmlWriter->writeElement(strtolower($name), $this->{$varName});
        }
    }

    private function _addAmount()
    {
        if (isset($this->_amount)) {
            $this->_xmlWriter->startElement('amount');
            if (isset($this->_currency)) {
                $this->_xmlWriter->writeAttribute('currency', $this->_currency);
            }
            $this->_xmlWriter->text($this->_amount);
            $this->_xmlWriter->endElement();
        }
    }

    private function _addSha1Hash($signatureArray)
    {
        $signature = implode('.', $signatureArray);
        $sha1hash = $this->_helper->signFields($signature);
        $this->_xmlWriter->writeElement('sha1hash', $sha1hash);
    }

    private function _addComments()
    {
        if (isset($this->_comments)) {
            $this->_xmlWriter->startElement('comments');

            $commentId = 1;
            foreach (array_values($this->_comments) as $key => $value) {
                $this->_xmlWriter->startElement('comment');
                $this->_xmlWriter->writeAttribute('id', $commentId);
                $this->_xmlWriter->text($value->getComment());
                $this->_xmlWriter->endElement();
                ++$commentId;
            }

            $this->_xmlWriter->endElement();
        }
    }

    private function _addPayer()
    {
        if (!isset($this->_payer) || !isset($this->_payerRef)) {
            return;
        }

        $this->_xmlWriter->startElement('payer');
        $this->_xmlWriter->writeAttribute('ref', $this->_payerRef);
        $this->_xmlWriter->writeAttribute('type', 'Subscriber');

        $this->_xmlWriter->writeElement('title', $this->_payer->getPrefix());
        $this->_xmlWriter->writeElement('firstname', $this->_payer->getFirstname());
        $this->_xmlWriter->writeElement('surname', $this->_payer->getLastname());

        $addressId = $this->_payer->getDefaultBilling();
        if (!isset($addressId) || empty($addressId)) {
            $addressId = $this->_payer->getDefaultShipping();
        }
        if (!isset($addressId) || empty($addressId)) {
            $address = null;
        } else {
            $address = $this->_addressRepository->getById($addressId);
        }

        if (isset($address)) {
            $this->_xmlWriter->writeElement('company', $address->getCompany());
            $street = $address->getStreet();
            $this->_xmlWriter->startElement('address');
            $this->_xmlWriter->writeElement(
                'line1',
                isset($street[0]) ? $street[0] : self::NOT_AVAILABLE
            );
            $this->_xmlWriter->writeElement(
                'line2',
                isset($street[1]) ? $street[1] : self::NOT_AVAILABLE
            );
            $this->_xmlWriter->writeElement(
                'line3',
                isset($street[2]) ? $street[2] : self::NOT_AVAILABLE
            );
            $this->_xmlWriter->writeElement('city', $address->getCity());

            if ($address->getRegionId()) {
                $this->_xmlWriter->writeElement('county', $address->getRegionId());
            }

            $this->_xmlWriter->writeElement('postcode', $address->getPostcode());
            $this->_xmlWriter->startElement('country');
            $this->_xmlWriter->writeAttribute('code', $address->getCountryId());
            $this->_xmlWriter->text($address->getCountryId());
            $this->_xmlWriter->endElement();
            $this->_xmlWriter->endElement();
            $this->_xmlWriter->startElement('phonenumbers');
            $this->_xmlWriter->writeElement('home', $address->getTelephone());
            $this->_xmlWriter->writeElement('fax', $address->getFax());
            $this->_xmlWriter->endElement();
        }

        $this->_xmlWriter->writeElement('email', $this->_payer->getEmail());

        $this->_xmlWriter->endElement();
    }
}
