<?php

namespace RealexPayments\HPP\Block\Checkout\Onepage;

use RealexPayments\HPP\Api\RealexPaymentManagementInterface;
use RealexPayments\HPP\Helper\Data;
use RealexPayments\HPP\Model\Api\RealexPaymentManagement;

class Success extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $_customerSession;

    /** @var RealexPaymentManagementInterface|RealexPaymentManagement */
    private $_paymentManagement;

    /** @var Data */
    private $_dataHelper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Customer\Model\Session                  $customerSession
     * @param RealexPaymentManagementInterface                 $paymentManagement
     * @param Data                                             $dataHelper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        RealexPaymentManagementInterface $paymentManagement,
        Data $dataHelper,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_paymentManagement = $paymentManagement;
        $this->_dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    /**
     * Return success message if realex payment.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $customerId = $this->_customerSession->getCustomerId();
        $order = $this->_checkoutSession->getLastRealOrder();
        if (!$order) {
            return '';
        }
        if ($order->getId()) {
            if ($order->getPayment()->getMethodInstance()->getCode() == 'realexpayments_hpp') {
                $fields = $order->getPayment()->getAdditionalInformation();
                $newPayer = ($customerId && ((isset($fields['PAYER_SETUP']) && $fields['PAYER_SETUP'] == '00')
                            || (isset($fields['PMT_SETUP']) && $fields['PMT_SETUP'] == '00')));
                $isApmPending = $this->_dataHelper->isOrderPendingPayment($order) && $this->_paymentManagement->isTransactionApm($fields);
                $this->addData(
                    [
                        'is_realex'      => true,
                        'new_payer'      => $newPayer,
                        'is_apm_pending' => $isApmPending
                    ]
                );

                return parent::_toHtml();
            }
        }

        return '';
    }
}
