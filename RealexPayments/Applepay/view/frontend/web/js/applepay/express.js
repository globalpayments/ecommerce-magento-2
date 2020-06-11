define(
    [
        'uiComponent',
        'mage/translate',
        'jquery',
        'RealexPayments_Applepay/js/applepay/button',
        'RealexPayments_Applepay/js/applepay/interface',
    ],
    function (
        Component,
        $t,
        $,
        button,
        applePayInterface
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
                currencyCode: null
            },

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                var paymentInterface = new applePayInterface();
                paymentInterface.setGrandTotalAmount(parseFloat(this.grandTotalAmount).toFixed(2));
                paymentInterface.setDisplayName(this.displayName);
                paymentInterface.setQuoteId(this.quoteId);
                paymentInterface.setActionSuccess(this.actionSuccess);
                paymentInterface.setIsLoggedIn(this.isLoggedIn);
                paymentInterface.setStoreCode(this.storeCode);
                paymentInterface.setCurrencyCode(this.currencyCode);

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
