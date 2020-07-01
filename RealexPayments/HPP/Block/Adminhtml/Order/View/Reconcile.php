<?php
namespace RealexPayments\HPP\Block\Adminhtml\Order\View;

class Reconcile extends \Magento\Sales\Block\Adminhtml\Order\View
{

    protected function _construct()
    {
        parent::_construct();

        if ($this->_isAllowedAction('Magento_Sales::invoice') && $this->getOrder()->canReconcile()) {
            $onClick = "setLocation('{$this->getReconcileUrl()}')";
            $this->addButton(
                'order_reconcile',
                ['label' => __('Reconcile'), 'onclick' => $onClick, 'class' => 'realexpayments-hpp-reconcile']
            );
        }
    }

    /**
     * Reconcile URL getter
     *
     * @return string
     */
    public function getReconcileUrl()
    {
        return $this->getUrl('realexpayments_hpp/order/reconcile/');
    }

}