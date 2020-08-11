<?php /** @noinspection PhpUnused */

namespace RealexPayments\HPP\Controller\Apm;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use RealexPayments\HPP\Helper\Data;

/**
 * Class StatusFetcher
 *
 * @package RealexPayments\HPP\Controller\Apm
 */
class StatusFetcher extends Action
{
    /** @var JsonFactory */
    protected $_resultJsonFactory;

    /** @var OrderRepositoryInterface */
    protected $_orderRepository;

    /** @var Data */
    protected $_helper;


    /** @inheritDoc */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        OrderRepositoryInterface $orderRepository,
        Data $helper
    )
    {
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_orderRepository   = $orderRepository;
        $this->_helper            = $helper;

        parent::__construct($context);
    }

    /** @inheritDoc */
    public function execute()
    {
        $request  = $this->getRequest();
        $response = $this->_resultJsonFactory->create();
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);

        $orderId = $request->getParam('order_id', false);

        if ($orderId === false) throw new NotFoundException(new Phrase("'order_id' is mandatory"));
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        if (!$request->isAjax()) throw new NotFoundException(new Phrase("Trying to access URL in non-AJAX context"));

        $order = $this->_orderRepository->get($orderId);

        if (!$order) throw new NotFoundException(new Phrase("Order couldn't be found"));

        $isPendingPayment = $this->_helper->isOrderPendingPayment($order);
        $orderStatus      = $order->getStatus();
        $payload          = [
            'status'    => $orderStatus,
            'isPending' => $isPendingPayment,
            'text'      => $isPendingPayment ? __("Your order is pending payment") : __("Your order is %s", $orderStatus)
        ];

        $response->setData($payload);

        return $response;
    }
}