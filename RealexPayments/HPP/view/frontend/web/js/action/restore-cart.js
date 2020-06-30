define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader) {
        'use strict';
        return function() {

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
            fullScreenLoader.startLoader();

            return storage.post(
                serviceUrl, JSON.stringify(payload)
            ).done(
                function() {
                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function(response) {
                    errorProcessor.process(response);
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
