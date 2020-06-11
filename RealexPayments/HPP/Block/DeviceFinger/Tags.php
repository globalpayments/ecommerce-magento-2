<?php

namespace RealexPayments\HPP\Block\DeviceFinger;

class Tags extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $_customerSession;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session                  $customerSession
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \RealexPayments\HPP\Helper\Data $helper,
        array $data = []
    ) {
        $this->_customerSession = $customerSession;
        $this->_helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getDeviceFingerEnabled()
    {
        return $this->_helper->getConfigData('dm_devicefinger_enabled');
    }

    /**
     * @return string
     */
    public function getOrgId()
    {
        return $this->_helper->getConfigData('dm_devicefinger_org');
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->_helper->getConfigData('dm_devicefinger_merchant');
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->_helper->getDMSessionId();
    }
}
