define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function($, quote, urlBuilder, storage, customer, fullScreenLoader) {
        'use strict';
        var agreementsConfig = window.checkoutConfig.checkoutAgreements;

        /**
         * This function adds extension attributes to paymentData
         * the attributes param must be an array of objects,
         * with each object having a code and a value.
         * You also need to add those attributes to your extension_attributes.xml,
         * where they need to be set for: Magento\Quote\Api\Data\PaymentInterface
         *
         * @param attributes
         * @param paymentData
         */
        var addExtensionAttributes = function(attributes, paymentData) {
            if (Array.isArray(attributes)) {
                for (var i = 0; i < attributes.length; i++) {
                    var currentAttribute = attributes[i];
                    paymentData.extension_attributes[currentAttribute.code] = currentAttribute.value;
                }
            }
        }

        return function(extensionAttributes) {

            var serviceUrl,
                payload,
                paymentData = quote.paymentMethod();
            if (paymentData.title) {
                delete paymentData.title;
            }
            // check if agreement is enabled if so add it to payload
            if (agreementsConfig.isEnabled) {
                var agreementForm = $('.payment-method._active div[data-role=checkout-agreements] input'),
                    agreementData = agreementForm.serializeArray(),
                    agreementIds = [];

                agreementData.forEach(function(item) {
                    agreementIds.push(item.value);
                });

                paymentData.extension_attributes = {
                    agreement_ids: agreementIds
                };
            }

            /**
             * If we have the extensionAttributes parameter,
             * we add them to the paymentData object
             */
            if (extensionAttributes && extensionAttributes.length > 0) {
                if (!paymentData.extension_attributes) {
                    paymentData.extension_attributes = {};
                }
                addExtensionAttributes(extensionAttributes, paymentData);
            }

            /** Checkout for guest and registered customer. */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/payment-information', {
                    quoteId: quote.getQuoteId()
                });
                payload = {
                    cartId: quote.getQuoteId(),
                    email: quote.guestEmail,
                    paymentMethod: paymentData,
                    billingAddress: quote.billingAddress()
                };
            } else {
                serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    paymentMethod: paymentData,
                    billingAddress: quote.billingAddress()
                };
            }
            if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) { delete paymentData.__disableTmpl; }
            fullScreenLoader.startLoader();

            return storage.post(
                serviceUrl, JSON.stringify(payload)
            );
        };
    }
);
