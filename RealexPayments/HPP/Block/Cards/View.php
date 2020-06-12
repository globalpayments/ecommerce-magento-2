<?php

namespace RealexPayments\HPP\Block\Cards;

class View extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * View constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_urlBuilder = $context->getUrlBuilder();
        $this->_isScopePrivate = true;
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
