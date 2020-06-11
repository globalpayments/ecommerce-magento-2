define(
    [
        'Magento_Checkout/js/view/payment/default',
        'RealexPayments_Applepay/js/applepay/method',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/totals'
    ],
    function (Component, applePayMethod, quote, customerData, totals) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'RealexPayments_Applepay/payment/applepay'
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            initApplePayImplementation: function(id) {
                applePayMethod.init(
                    document.getElementById(id),
                    this
                );
            },

            /* Subscribe Observers */

            initObservable: function () {
                this._super();
                this.grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2);
                this.currencyCode = quote.totals()['base_currency_code'];

                quote.totals.subscribe(function () {
                    if (this.grandTotalAmount !== quote.totals()['base_grand_total']) {
                        this.grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2);
                    }
                }.bind(this));

                return this;
            },

            getQuoteId() {
                return window.checkoutConfig.quoteData.entity_id;
            },

            setPaymentMethodNonce: function (nonce) {
                this.paymentMethodNonce = nonce;
            },

            getGrandTotalAmount: function () {
                return "" + parseFloat(this.grandTotalAmount).toFixed(2);
            },

            getIsLoggedIn: function () {
                let customerInfo = customerInfo || this.getCustomerInfo();
                return customerInfo && customerInfo.firstname;
            },

            getCurrencyCode: function () {
                return window.checkoutConfig.quoteData.quote_currency_code;
            },

            getStoreCode: function () {
                return window.checkoutConfig.storeCode;
            },

            /*getPaymentRequest: function () {
                return {
                    total: {
                        label: this.getDisplayName(),
                        amount: 10.00
                    }
                };
            },*/

            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method_nonce': this.paymentMethodNonce
                    }
                };
                return data;
            },

        });
    }
);