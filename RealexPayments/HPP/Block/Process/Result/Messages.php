<?php

namespace RealexPayments\HPP\Block\Process\Result;

class Messages extends \Magento\Framework\View\Element\Messages
{
    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        $messages = $this->messageManager->getMessages(true, 'realex_messages');
        $this->addMessages($messages);

        return parent::_prepareLayout();
    }
}
