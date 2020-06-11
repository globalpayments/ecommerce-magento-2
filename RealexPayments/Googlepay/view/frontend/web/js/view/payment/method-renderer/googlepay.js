define(
    [
        'Magento_Checkout/js/view/payment/default',
        'RealexPayments_Googlepay/js/googlepay/method',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/totals'
    ],
    function (Component, googlePayMethod, quote, customerData, totals) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'RealexPayments_Googlepay/payment/googlepay'
            },

            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            initGooglePayImplementation: function(id) {

                googlePayMethod.init(
                    document.getElementById(id),
                    this
                );

            },

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

            /* Subscribe Observers */

            getCustomerInfo: function () {
                var customer = customerData.get('customer');
                return customer();
            },

            getAllowedPaymentMethods: function() {
                return window.checkoutConfig.payment.realexpayments_googlepay.google_allowed_cards;
            },

            getQuoteId() {
                return window.checkoutConfig.quoteData.entity_id;
            },

            getGatewayMerchantId() {
                return window.checkoutConfig.payment.realexpayments_googlepay.globalpay_merchant_id;
            },

            getSandbox() {
                return window.checkoutConfig.payment.realexpayments_googlepay.sandbox;
            },

            getDisplayName: function () {
                return window.checkoutConfig.payment.realexpayments_googlepay.google_merchant_name;
            },

            getGoogleMerchantName: function () {
                return window.checkoutConfig.payment.realexpayments_googlepay.google_merchant_name;
            },

            getGoogleMerchantId: function () {
                return window.checkoutConfig.payment.realexpayments_googlepay.google_merchant_id;
            },

            getActionSuccess: function () {
                return window.checkoutConfig.payment.realexpayments_googlepay.success_action;
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


            setPaymentMethodNonce: function (nonce) {
                this.paymentMethodNonce = nonce;
            },

            setPaymentConfirmationDetails: function(tokenProcessResponse, paymentData) {

                this.tokenProcessResponse = tokenProcessResponse;
                this.paymentData = paymentData;

            },

            getTokenPaymentRef() {
                if(this.tokenProcessResponse) {
                    if(this.tokenProcessResponse.paymentsReference) {
                        return this.tokenProcessResponse.paymentsReference;
                    }
                }
                return '';
            },

            getTokenOrderId() {
                if(this.tokenProcessResponse) {
                    if(this.tokenProcessResponse.orderId) {
                        return this.tokenProcessResponse.orderId;
                    }
                }
                return '';
            },

            getTokenPaymentDescription() {
                if(this.paymentData) {
                    if(this.paymentData.paymentMethodData) {
                        return this.paymentData.paymentMethodData.description;
                    }
                }
                return '';
            },

            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_ref': this.getTokenPaymentRef(),
                        'order_ref': this.getTokenOrderId(),
                        'card': this.getTokenPaymentDescription()
                    }
                };
                return data;
            },

        });
    }
);