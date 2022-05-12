<?php

namespace RealexPayments\HPP\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use RealexPayments\HPP\Api\RemoteXMLInterface;
use RealexPayments\HPP\Logger\Logger;

class CardStorage
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Logger
     */
    private $realexLogger;

    /**
     * @var Data;
     */
    private $realexHelper;

    /**
     * @var RemoteXMLInterface
     */
    private $remoteXml;

    /**
     * @var Session
     */
    private $session;

    /**
     * CardStorage constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param Logger $realexLogger
     * @param Data $realexHelper
     * @param RemoteXMLInterface $remoteXML
     * @param Session $session
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        Logger $realexLogger,
        Data $realexHelper,
        RemoteXMLInterface $remoteXML,
        Session $session
    ) {
        $this->customerRepository = $customerRepository;
        $this->realexLogger = $realexLogger;
        $this->realexHelper = $realexHelper;
        $this->remoteXml = $remoteXML;
        $this->session = $session;
    }

    /**
     * Handles the card storage fields
     *
     * @param array $response
     * @param string $customerId
     */
    public function handleCardStorage($response, $customerId)
    {
        try {
            $paymentSetup = isset($response['PMT_SETUP']) ? $response['PMT_SETUP'] : false;
            $payerRef = isset($response['SAVED_PAYER_REF']) ? $response['SAVED_PAYER_REF'] : false;
            //Is there a payment setup?
            if ($paymentSetup) {
                $payerSetup = isset($response['PAYER_SETUP']) ? $response['PAYER_SETUP'] : false;
                //Are we setting up a new payer?
                if ($payerSetup == '00') {
                    //Store payer ref against the customer
                    $this->storeCustomerPayerRef(
                        $response['MERCHANT_ID'],
                        $response['ACCOUNT'],
                        $payerRef,
                        $customerId
                    );
                }
            }

            $cardRef = isset($response['SAVED_PMT_REF']) ? $response['SAVED_PMT_REF'] : false;
            if ($cardRef) {
                //Store card details
                $this->realexHelper->logDebug('Customer ' . $customerId . ' added a new card:' . $cardRef);
            }

            $cardsEdited = isset($response['HPP_EDITED_PMT_REF']) ? $response['HPP_EDITED_PMT_REF'] : false;
            $cardsDeleted = isset($response['HPP_DELETED_PMT_REF']) ? $response['HPP_DELETED_PMT_REF'] : false;
            if ($cardsEdited) {
                $this->manageEditedCards($cardsEdited);
            }
            if ($cardsDeleted) {
                $this->manageDeletedCards($cardsDeleted);
            }
        } catch (\Exception $e) {
            //card storage exceptions should not stop a transaction
            $this->realexLogger->critical($e);
        }
    }

    /**
     * Store the payer ref against the customer
     *
     * @param string $merchantId
     * @param string $account
     * @param string $payerRef
     * @param string $customerId
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InputMismatchException
     */
    private function storeCustomerPayerRef($merchantId, $account, $payerRef, $customerId)
    {
        $this->realexHelper->logDebug('Storing payer ref:'.$payerRef.' for customer: '.$customerId);

        $customer = $this->customerRepository->getById($customerId);
        $customer->setCustomAttribute('realexpayments_hpp_payerref', $payerRef);
        $this->customerRepository->save($customer);
        //Update payer in realex
        try {
            $this->remoteXml->payerEdit($merchantId, $account, $payerRef, $customer);
        } catch (\Exception $e) {
            //Let it fail but still setup the rest of the payment
            $this->realexLogger->critical($e);
        }
    }

    /**
     * Manage cards that were edited while the user was on hpp
     *
     * @param string $cards
     */
    private function manageEditedCards($cards)
    {
        $this->realexHelper->logDebug('Customer edited the following cards:' . $cards);
    }

    /**
     * Manage cards that were deleted while the user was on hpp
     *
     * @param string $cards
     */
    private function manageDeletedCards($cards)
    {
        $this->realexHelper->logDebug('Customer deleted the following cards:' . $cards);
    }
}
