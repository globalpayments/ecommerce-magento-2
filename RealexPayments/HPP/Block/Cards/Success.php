<?php

namespace RealexPayments\HPP\Block\Cards;

class Success extends \Magento\Framework\View\Element\Template
{
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
        $this->_isScopePrivate = true;
    }
}
