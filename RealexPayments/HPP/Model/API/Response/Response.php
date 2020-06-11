<?php

namespace RealexPayments\HPP\Model\API\Response;

class Response
{
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_logger;

    private $_array;

    /**
     * Response constructor.
     *
     * @param \RealexPayments\HPP\Helper\Data   $helper
     * @param \RealexPayments\HPP\Logger\Logger $logger
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        \RealexPayments\HPP\Logger\Logger $logger
    ) {
        $this->_helper = $helper;
        $this->_logger = $logger;
    }

    /**
     * @desc Parse the response xml and return this class containing returned fields
     *
     * @return this
     */
    public function parse($response)
    {
        if (!isset($response) || empty($response)) {
            return false;
        }

        $this->_helper->logDebug('Remote XML response:'.$this->_helper->stripXML($response));
        //load the xml
        try {
            $doc = new \SimpleXMLElement($response);
        } catch (\Exception $e) {
            $this->_helper->critical($e);

            return false;
        }
        //read the minimum fields for validation
        $timestamp = $doc['timestamp'];
        $this->_array['MERCHANT_ID'] = (string) $doc->merchantid;
        $this->_array['ORDER_ID'] = (string) $doc->orderid;
        $this->_array['RESULT'] = (string) $doc->result;
        $this->_array['MESSAGE'] = (string) $doc->message;
        $this->_array['PASREF'] = (string) $doc->pasref;
        $this->_array['AUTHCODE'] = (string) $doc->authcode;
        //is this a valid response
        if ($this->_array['RESULT'] != '00') {
            $this->_logger->critical('Invalid response received from gateway:'.
                print_r($this->_helper->stripFields($this->_array), true));

            return $this;
        }
        //compute the hash
        $realexsha1 = (string) $doc->sha1hash;
        $fieldsToSign = $timestamp.'.'.$this->_array['MERCHANT_ID'].'.'.
            $this->_array['ORDER_ID'].'.'.$this->_array['RESULT'].'.'.$this->_array['MESSAGE'].'.'.
            $this->_array['PASREF'].'.'.$this->_array['AUTHCODE'];
        $sha1hash = $this->_helper->signFields($fieldsToSign);

        //Check to see if hashes match or not
        if ($sha1hash !== $realexsha1){
            $this->_logger->critical('Bad response received from gateway:'.
                print_r($this->_helper->stripFields($this->_array), true));

            return false;
        }
        //set the rest of the fields
        $this->_array['ACCOUNT'] = (string) $doc->account;
        $this->_array['CVNRESULT'] = (string) $doc->cvnresult;
        $this->_array['AVSPOSTCODERESPONSE'] = (string) $doc->avspostcoderesponse;
        $this->_array['AVSADDRESSRESPONSE'] = (string) $doc->avsaddressresponse;
        $this->_array['BATCHID'] = (string) $doc->batchid;
        $this->_array['TIMETAKEN'] = (string) $doc->timetaken;
        $this->_array['AUTHTIMETAKEN'] = (string) $doc->authtimetaken;

        return $this;
    }

    public function toArray()
    {
        return $this->_array;
    }
}
