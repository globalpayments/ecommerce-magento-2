<?php

namespace RealexPayments\HPP\Block\Cards;

class View extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_session;

    /**
     * View constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $session
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_urlBuilder = $context->getUrlBuilder();
        $this->_session = $session;
        $this->_isScopePrivate = true;
    }

    /**
     * @return bool
     */
    public function customerHasAddress()
    {
        if ($this->_session->isLoggedIn()) {
            $customer = $this->_session->getCustomer();
            return (bool) $customer->getDefaultBillingAddress();
        }

        return false;
    }

    /**
     * @return string
     */
    public function getIframeUrl()
    {
        return $this->_urlBuilder->getUrl(
            'realexpayments_hpp/cards/redirect',
            ['_secure' => true]
        );
    }
}
