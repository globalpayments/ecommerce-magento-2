<?php

namespace RealexPayments\HPP\Block\Process\Result;

class Observe extends \Magento\Framework\View\Element\Template
{
    const OBSERVE_KEY = 'realexpayments_hpp_observe';

    private $_observe;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;

    /**
     * Process constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array                                            $data
     * @param \Magento\Framework\Message\ManagerInterface      $messageManager
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    public function _prepareLayout()
    {
        $observe = $this->_checkoutSession->getData(self::OBSERVE_KEY);
        if (isset($observe)) {
            $this->_checkoutSession->setData(self::OBSERVE_KEY, '0');
            if ($observe == '1') {
                $this->_observe = true;
            } else {
                $this->_observe = false;
            }
        }

        return parent::_prepareLayout();
    }

    /**
     * @return bool
     */
    public function getObserve()
    {
        return $this->_observe;
    }
}
