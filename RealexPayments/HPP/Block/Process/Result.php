<?php

namespace RealexPayments\HPP\Block\Process;

class Result extends \Magento\Framework\View\Element\Template
{
    const REGISTRY_KEY = 'realexpayments_hpp_params';

    /**
     * Core registry.
     *
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * Process constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array                                            $data
     * @param \Magento\Framework\Registry                      $registry
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    public function _prepareLayout()
    {
        $params = $this->coreRegistry->registry(self::REGISTRY_KEY);
        $this->setParams($params);

        return parent::_prepareLayout();
    }
}
