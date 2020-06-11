<?php
namespace RealexPayments\Googlepay\Api;


interface ProcessPaymentTokenInterface {

    /**
     * Returns array
     *
     * @api
     * @param string $paymentToken
     * @param string $quoteId
     * @return array
     */

    public function processPaymentToken($paymentToken, $quoteId);

}