<?php

namespace RealexPayments\HPP\Block\Info;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'RealexPayments_HPP::info/info.phtml';

    /**
     * Prepare Realex related payment info.
     *
     * @param \Magento\Framework\DataObject|array $transport
     *
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];
        $orderId = $this->getInfo()->getAdditionalInformation('ORDER_ID');
        $cardType = $this->getInfo()->getAdditionalInformation('CARDTYPE');
        $paymentMethod = $this->getInfo()->getAdditionalInformation('PAYMENTMETHOD');
        $cardDigits = $this->getInfo()->getAdditionalInformation('CARDDIGITS');
        $result = $this->getInfo()->getAdditionalInformation('RESULT');
        $authCode = $this->getInfo()->getAdditionalInformation('AUTHCODE');
        $message = $this->getInfo()->getAdditionalInformation('MESSAGE');
        $pasref = $this->getInfo()->getAdditionalInformation('PASREF');
        $cvn = $this->getInfo()->getAdditionalInformation('CVNRESULT');
        $avsAddress = $this->getInfo()->getAdditionalInformation('AVSADDRESSRESULT');
        $avsPost = $this->getInfo()->getAdditionalInformation('AVSPOSTCODERESULT');
        $fraudResult = $this->getInfo()->getAdditionalInformation('HPP_FRAUDFILTER_RESULT');
        $chosenPayment = $this->getInfo()->getAdditionalInformation('HPP_CHOSEN_PMT_REF');
        $eci = $this->getInfo()->getAdditionalInformation('ECI');
        $cavv = $this->getInfo()->getAdditionalInformation('CAVV');
        $xid = $this->getInfo()->getAdditionalInformation('XID');

        $data = $this->checkAndSet($data, $orderId, 'Order Id');
        $data = $this->checkAndSet($data, $cardType, 'Card Type');
        $data = $this->checkAndSet($data, $paymentMethod, 'Payment Method');
        $data = $this->checkAndSet($data, $result, 'Result');
        $data = $this->checkAndSet($data, $authCode, 'Auth Code');
        $data = $this->checkAndSet($data, $message, 'Message');
        $data = $this->checkAndSet($data, $pasref, 'Pas Ref');
        $data = $this->checkAndSet($data, $cvn, 'CVN Result');
        $data = $this->checkAndSet($data, $avsAddress, 'AVS Address Result');
        $data = $this->checkAndSet($data, $avsPost, 'AVS Postcode Result');
        $data = $this->checkAndSet($data, $fraudResult, 'Fraud Filter Result');
        $data = $this->checkAndSet($data, $chosenPayment, 'Chosen Payment Ref');
        if ($cardDigits) {
            $data[(string) __('Card Number')] = sprintf('xxxx-%s', $cardDigits);
        }
        if ($eci && $cardType) {
            $data[(string) __('3D Secure Status')] = $this->_interpretEci($eci, $cardType);
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }

    private function checkAndSet($data, $field, $text)
    {
        if ($field) {
            $data[(string) __($text)] = $field;
        }

        return $data;
    }

    private function _interpretEci($eci, $cardType)
    {
        $status = 'Not 3D Secure';
        if ($cardType == 'VISA' || $cardType == 'AMEX') {
            switch ($eci) {
                case '5':
                    $status = 'Fully 3D Secure';
                    break;
                case '6':
                    $status = 'Merchant 3D Secure';
                    break;
                default:
                    $status = 'Not 3D Secure';
            }
        }
        if ($cardType == 'MC') {
            switch ($eci) {
                case '2':
                    $status = 'Fully 3D Secure';
                    break;
                case '1':
                    $status = 'Merchant 3D Secure';
                    break;
                default:
                    $status = 'Not 3D Secure';
            }
        }

        return $status;
    }
}
