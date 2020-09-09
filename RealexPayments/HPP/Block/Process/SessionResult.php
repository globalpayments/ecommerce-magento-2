<?php


namespace RealexPayments\HPP\Block\Process;

use Magento\Framework\View\Element\Template;

/**
 * Class SessionResult
 *
 * @package RealexPayments\HPP\Block\Process
 */
class SessionResult extends \Magento\Framework\View\Element\Template
{
    /** @inheritDoc */
    public function __construct(Template\Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

}