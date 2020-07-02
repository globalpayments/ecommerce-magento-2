<?php
namespace RealexPayments\HPP\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Reconcile extends \Magento\Sales\Controller\Adminhtml\Order
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'RealexPayments_HPP::actions_reconcile';

    /**
     * @var \RealexPayments\HPP\API\RealexPaymentManagementInterface
     */
    private $_paymentManagement;

    public function __construct(
        Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Translate\InlineInterface $translateInline,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        \RealexPayments\HPP\API\RealexPaymentManagementInterface $paymentManagement
    ) {
        $this->_paymentManagement = $paymentManagement;
        parent::__construct($context, $coreRegistry, $fileFactory, $translateInline, $resultPageFactory, $resultJsonFactory, $resultLayoutFactory, $resultRawFactory, $orderManagement, $orderRepository, $logger);
    }

    /**
     * Attempt to reconcile the order payment.
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /**
         * @var \RealexPayments\HPP\Model\Order\Interceptor
         */
        $order = $this->_initOrder();
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($order) {
            try {
                $payment = $order->getPayment();
                $order->getPayment()->getMethodInstance()->reconcile($payment);
                $queryResponse = $payment->getTransactionAdditionalInfo()[\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS];
                $processResponse = $this->_paymentManagement->processResponse($order, $queryResponse);
                if ($processResponse) {
                    $this->messageManager->addSuccessMessage(__('The payment has been reconciled.'));
                } else {
                    $this->messageManager->addErrorMessage(__('Invalid response fields. We can\'t reconcile the payment right now.'));
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('We can\'t reconcile the payment right now.'));
                $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            }
            $resultRedirect->setPath('sales/*/view', ['order_id' => $order->getId()]);
            return $resultRedirect;
        }
        $resultRedirect->setPath('sales/*/');

        return $resultRedirect;
    }
}
