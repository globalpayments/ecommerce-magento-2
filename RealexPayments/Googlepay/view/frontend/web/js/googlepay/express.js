define(
    [
        'uiComponent',
        'mage/translate',
        'jquery',
        'RealexPayments_Googlepay/js/googlepay/button',
        'RealexPayments_Googlepay/js/googlepay/interface',
    ],
    function (
        Component,
        $t,
        $,
        button,
        googlePayInterface
    ) {
        'use strict';

        return Component.extend({

            defaults: {
                id: null,
                clientToken: null,
                quoteId: 0,
                displayName: null,
                actionSuccess: null,
                grandTotalAmount: 0,
                isLoggedIn: false,
                storeCode: "default",
                currencyCode: null,
                sandbox: null,
                googleMerchantName: null,
                googleMerchantId: null,
                globalpayMerchantId: null,
                allowedCards: null,
            },

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                var paymentInterface = new googlePayInterface();
                paymentInterface.setGrandTotalAmount(parseFloat(this.grandTotalAmount).toFixed(2));
                paymentInterface.setDisplayName(this.displayName);
                paymentInterface.setQuoteId(this.quoteId);
                paymentInterface.setActionSuccess(this.actionSuccess);
                paymentInterface.setIsLoggedIn(this.isLoggedIn);
                paymentInterface.setStoreCode(this.storeCode);
                paymentInterface.setCurrencyCode(this.currencyCode);
                paymentInterface.setAllowedPaymentMethods(this.allowedCards);
                paymentInterface.setGatewayMerchantId(this.globalpayMerchantId);
                paymentInterface.setSandbox(this.sandbox);
                paymentInterface.setGoogleMerchantName(this.googleMerchantName);
                paymentInterface.setGoogleMerchantId(this.googleMerchantId);

                // Attach the button
                button.init(
                    document.getElementById(this.id),
                    paymentInterface
                );

                return this;
            }
        });
    }
);
