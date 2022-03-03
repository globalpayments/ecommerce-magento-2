define(
    [
        'jquery',
        'require',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/model/customer'
    ],
    function($, require, quote, urlBuilder, storage, customer) {
        'use strict';
        return function(event) {
            var serviceUrl,
                payload;

            /** Restore cart for guest and registered customer. */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/realexpayments-restore-cart', {
                    quoteId: quote.getQuoteId()
                });
                payload = {
                    cartId: quote.getQuoteId()
                };
            } else {
                serviceUrl = urlBuilder.createUrl('/carts/mine/realexpayments-restore-cart', {});
                payload = {
                    cart_id: quote.getQuoteId()
                };
            }

            /**
             * This component is being required separately because it's only available in Magento 2.4.3,
             * so, in lower versions we catch the error that is being thrown, and just continue the normal flow
             */
            require(
                [
                    'Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry'
                ],
                function (registry) {
                    /**
                     * If we have a reCaptcha token for checkout, we remove it,
                     * so Magento can generate a new one
                     */
                    if (registry.tokens.hasOwnProperty('recaptcha-checkout-place-order')) {
                        delete registry.tokens['recaptcha-checkout-place-order'];
                    }
                },
                function (error) { }
            );

            return storage.post(
                serviceUrl, JSON.stringify(payload)
            );
        };
    }
);
