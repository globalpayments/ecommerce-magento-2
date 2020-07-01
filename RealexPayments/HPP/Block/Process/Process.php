<?php

namespace RealexPayments\HPP\Block\Process;

use Symfony\Component\Config\Definition\Exception\Exception;

class Process extends \Magento\Payment\Block\Form
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;

    /**
     * @var \Magento\Checkout\Model\Order
     */
    private $_order;

    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * Process constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\OrderFactory                $orderFactory
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \RealexPayments\HPP\Helper\Data                  $helper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \RealexPayments\HPP\Helper\Data $helper,
        array $data = []
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_helper = $helper;
        parent::__construct($context, $data);
        $this->_getOrder();
    }

    /**
     * @return string
     */
    public function getFormUrl()
    {
        $result = '';
        try {
            $order = $this->_order;
            if ($order->getPayment()) {
                $result = $this->_order->getPayment()->getMethodInstance()->getFormUrl();
            }
        } catch (Exception $e) {
            // do nothing for now
            throw($e);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getFormFields()
    {
        $result = [];
        try {
            $payment = $this->_order->getPayment();
            if ($payment) {
                $result = $payment->getMethodInstance()->getFormFields();
                $additionalInfo = [];
                $additionalInfo['MERCHANT_ID'] = $result['MERCHANT_ID'];
                $additionalInfo['ACCOUNT'] = $result['ACCOUNT'];
                $additionalInfo['ORDER_ID'] = $result['ORDER_ID'];
                $additionalInfo['AMOUNT'] = $result['AMOUNT'];
                $this->_helper->setAdditionalInfo($payment, $additionalInfo);
                $this->_order->save();
            }
        } catch (Exception $e) {
            // do nothing for now
            $this->_helper->logDebug('Form fields exception:'.$e);
        }

        return $result;
    }

    /**
     * Get order object.
     *
     * @return \Magento\Sales\Model\Order
     */
    private function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }

        return $this->_order;
    }

    /**
     * Get frontend checkout session object.
     *
     * @return \Magento\Checkout\Model\Session
     */
    private function _getCheckout()
    {
        return $this->_checkoutSession;
    }
}
