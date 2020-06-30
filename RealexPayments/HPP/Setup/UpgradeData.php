<?php

namespace RealexPayments\HPP\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * Customer setup factory.
     *
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $this->_updateSchema102($customerSetup);
        }

        $setup->endSetup();
    }

    private function _updateSchema102($customerSetup)
    {
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'realexpayments_hpp_payerref',
            [

              'label' => 'Global Payments Payer Ref',
              'input' => 'text',
              'required' => false,
              'visible' => true,
              'system' => false,
              'is_used_in_grid' => false,
              'is_visible_in_grid' => false,
              'is_filterable_in_grid' => false,
              'is_searchable_in_grid' => false,
            ]
        );

        $customerSetup->getEavConfig()->getAttribute('customer', 'realexpayments_hpp_payerref')
              ->setData('used_in_forms', ['adminhtml_customer'])
              ->save();
    }
}
