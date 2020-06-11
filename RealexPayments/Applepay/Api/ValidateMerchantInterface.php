<?php
namespace RealexPayments\Applepay\Api;


interface ValidateMerchantInterface {

    /**
     * Returns array
     *
     * @api
     * @param string $validationUrl
     * @param string $quoteId
     * @return array
     */

    public function validateMerchant($validationUrl, $quoteId);

}