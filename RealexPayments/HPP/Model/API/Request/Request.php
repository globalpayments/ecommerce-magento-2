<?php

namespace RealexPayments\HPP\Model\API\Request;

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
    const NOT_AVAILABLE = 'N/A';
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;
    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    private $_addressRepository;

    private $_merchantid;
    private $_account;
    private $_orderid;
    private $_pasref;
    private $_authcode;
    private $_amount;
    private $_currency;
    private $_comments;
    private $_refundHash;
    private $_type;
    private $_payerRef;
    private $_payer;

    /**
     * Request constructor.
     *
     * @param \RealexPayments\HPP\Helper\Data                  $helper
     * @param \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        \Magento\Customer\Api\AddressRepositoryInterface  $addressRepository
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
        $this->_merchantid = $merchantId;

        return $this;
    }

    public function setAccount($account)
    {
        $this->_account = $account;

        return $this;
    }

    public function setOrderId($orderId)
    {
        $this->_orderid = $orderId;

        return $this;
    }

    public function setPasref($pasref)
    {
        $this->_pasref = $pasref;

        return $this;
    }

    public function setAuthCode($authcode)
    {
        $this->_authcode = $authcode;

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

    public function setStoreId($storeId) {
        $this->_helper->setStoreId($storeId);
        return $this;
    }

    /**
     * @desc Build the request and return the xml
     *
     * @return string
     */
    public function build()
    {
        $timestamp = strftime('%Y%m%d%H%M%S');
        //Open the xml writer
        $writer = new \XMLWriter();
        $writer->openMemory();

        $writer->setIndent(true);
        $writer->setIndentString(' ');
        $writer->startDocument('1.0', 'UTF-8');
        //start the root node
        $writer->startElement('request');
        //write the request type attribute
        if (isset($this->_type)) {
            $writer->writeAttribute('type', $this->_type);
        }
        $writer->writeAttribute('timestamp', $timestamp);
        $writer->writeElement('merchantid', $this->_merchantid);
        if (isset($this->_account)) {
            $writer->writeElement('account', $this->_account);
        }
        $writer->writeElement('orderid', $this->_orderid);
        if (isset($this->_pasref)) {
            $writer->writeElement('pasref', $this->_pasref);
        }
        if (isset($this->_authcode)) {
            $writer->writeElement('authcode', $this->_authcode);
        }
        if (isset($this->_amount)) {
            $writer->startElement('amount');
            if (isset($this->_currency)) {
                $writer->writeAttribute('currency', $this->_currency);
            }
            $writer->text($this->_amount);
            $writer->endElement();
        }
        if (isset($this->_payer) && isset($this->_payerRef)) {
            $this->_addPayer($writer);
        }
        //Add comments
        $writer->startElement('comments');
        $this->_addComments($writer);
        $writer->endElement();
        //refund hash
        if (isset($this->_refundHash)) {
            $writer->writeElement('refundhash', $this->_refundHash);
        }
        //sign the fields
        $signature = "$timestamp.$this->_merchantid.$this->_orderid.$this->_amount.$this->_currency.$this->_payerRef";
        $sha1hash = $this->_helper->signFields($signature);
        $writer->writeElement('sha1hash', $sha1hash);
        // close the root element and document
        $writer->endElement();
        $writer->endDocument();

        $xml = $writer->outputMemory();
        $this->_helper->logDebug('Remote XML request:'.$this->_helper->stripXML($xml));
        //return the xml
        return $xml;
    }

    /**
     * @desc Add the comments to the xml
     *
     * @param \XMLWriter $writer
     */
    private function _addComments($writer)
    {
        if (isset($this->_comments)) {
            $commentId = 1;
            foreach ($this->_comments as $key => $value) {
                $writer->startElement('comment');
                $writer->writeAttribute('id', $commentId);
                // Type returned is \Magento\Sales\Model\Order\Creditmemo\Comment
                $writer->text($value->getComment());
                $writer->endElement();
                ++$commentId;
            }
        }
    }

    /**
     * @desc Add the payer to the xml
     *
     * @param \XMLWriter $writer
     */
    private function _addPayer($writer)
    {
        $writer->startElement('payer');
        $writer->writeAttribute('ref', $this->_payerRef);
        $writer->writeAttribute('type', 'Subscriber');

        $writer->writeElement('title', $this->_payer->getPrefix());
        $writer->writeElement('firstname', $this->_payer->getFirstname());
        $writer->writeElement('surname', $this->_payer->getLastname());
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
            $writer->writeElement('company', $address->getCompany());
            $street = $address->getStreet();
            $writer->startElement('address');
            $writer->writeElement('line1', isset($street[0]) ? $street[0] : self::NOT_AVAILABLE);
            $writer->writeElement('line2', isset($street[1]) ? $street[1] : self::NOT_AVAILABLE);
            $writer->writeElement('line3', isset($street[2]) ? $street[2] : self::NOT_AVAILABLE);
            $writer->writeElement('city', $address->getCity());
            if ($address->getRegionId()) {
                $writer->writeElement('county', $address->getRegionId());
            }
            $writer->writeElement('postcode', $address->getPostcode());
            $writer->startElement('country');
            $writer->writeAttribute('code', $address->getCountryId());
            $writer->text($address->getCountryId());
            $writer->endElement();
            $writer->endElement();
            $writer->startElement('phonenumbers');
            $writer->writeElement('home', $address->getTelephone());
            $writer->writeElement('fax', $address->getFax());
            $writer->endElement();
        }
        $writer->writeElement('email', $this->_payer->getEmail());

        $writer->endElement();
    }
}
