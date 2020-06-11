define(
    [
        'uiComponent',
        "knockout",
        "jquery",
        'mage/translate',
        'mage/storage',
        'https://pay.google.com/gp/p/js/pay.js',
    ],
    function (
        Component,
        ko,
        jQuery,
        $t,
        storage,
        googlePay
    ) {
        'use strict';

        var that;

        return {

            apiVersion: 2,
            apiVersionMinor: 0,
            allowedCardAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
            googlePayClient: null,


            init: function (element, context) {

                // No element or context
                if (!element || !context) {
                    return;
                }

                this.context = context;
                this.onPaymentAuthorized = this.onPaymentAuthorized.bind(this);

                this.initButton(element);

            },

            getGooglePaymentEnviroment: function() {
                if(this.context.getSandbox()) {
                    return 'TEST';
                }
                return 'PRODUCTION';
            },

            getGoogleTokenSpecification: function() {
                return {
                    type: 'PAYMENT_GATEWAY',
                    parameters: {
                        'gateway': 'globalpayments',
                        'gatewayMerchantId': this.context.getGatewayMerchantId()
                    }
                };
            },

            getBaseCardPaymentMethod: function() {
                return {
                    type: 'CARD',
                    parameters: {
                        allowedAuthMethods: this.allowedCardAuthMethods,
                        allowedCardNetworks: this.context.getAllowedPaymentMethods()
                    }
                };
            },

            getCardPaymentMethod: function() {
                return Object.assign(
                    {},
                    this.getBaseCardPaymentMethod(),
                    {
                        tokenizationSpecification: this.getGoogleTokenSpecification()
                    }
                );
            },

            getGooglePaymentsClient: function() {
                if (this.googlePayClient === null) {

                    let paymentsClientConfig = {
                        environment: this.getGooglePaymentEnviroment(),
                        merchantInfo: {
                            merchantName: this.context.getGoogleMerchantName(),
                        },
                        paymentDataCallbacks: {
                            onPaymentAuthorized: this.onPaymentAuthorized
                        }
                    };

                    // Pass merchant ID in PRODUCTION.
                    if(!this.context.getSandbox()) {
                        paymentsClientConfig.merchantInfo.merchantId = this.context.getGoogleMerchantId();
                    }

                    this.googlePayClient = new google.payments.api.PaymentsClient(paymentsClientConfig);
                }
                return this.googlePayClient;
            },

            getGoogleIsReadyToPayRequest: function() {
                return Object.assign(
                    {},
                    {
                        apiVersion: this.apiVersion,
                        apiVersionMinor: this.apiVersionMinor
                    },
                    {
                        allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
                    }
                );
            },

            getGooglePaymentDataRequest: function() {

                var paymentDataRequest = Object.assign({}, {
                    apiVersion: this.apiVersion,
                    apiVersionMinor: this.apiVersionMinor
                });

                paymentDataRequest.allowedPaymentMethods = [this.getCardPaymentMethod()];
                paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();

                paymentDataRequest.merchantInfo = {
                    merchantName: this.context.getGoogleMerchantName(),
                };

                // Pass merchant ID in PRODUCTION.
                if(!this.context.getSandbox()) {
                    paymentsClientConfig.merchantInfo.merchantId = this.context.getGoogleMerchantId();
                }

                paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];

                return paymentDataRequest;
            },

            getGoogleTransactionInfo: function() {

                return {
                    currencyCode: this.context.getCurrencyCode(),
                    totalPriceStatus: 'FINAL',
                    totalPrice: this.context.getGrandTotalAmount()
                };

            },

            initButton: function(element) {

                var that = this;

                this.getGooglePaymentsClient().isReadyToPay(this.getGoogleIsReadyToPayRequest())
                .then(function(response) {
                    if (response.result) {

                        const button = that.getGooglePaymentsClient().createButton({
                            buttonColor: 'black',
                            buttonType: 'long',
                            onClick: function() {

                                var paymentDataRequest = that.getGooglePaymentDataRequest();
                                paymentDataRequest.transactionInfo = that.getGoogleTransactionInfo();

                                that.getGooglePaymentsClient().loadPaymentData(paymentDataRequest)
                                /*.then(function(paymentData) {

                                    console.log(paymentData)
                                    console.log("In payment response");

                                })*/
                                .catch(function(err) {
                                    console.error(err);
                                });


                            }
                        });

                        element.appendChild(button);

                    }
                }).catch(function(err) {
                    console.error(err);
                });

            },

            onPaymentAuthorized: function(paymentData) {

                let that = this;

                return new Promise(function(resolve, reject) {

                    var serviceUrl = 'rest/V1/googlepay/processPaymentToken';
                    var payload = {
                        paymentToken: paymentData.paymentMethodData.tokenizationData.token,
                        quoteId: that.context.getQuoteId(),
                    };

                    storage.post(
                        serviceUrl,
                        JSON.stringify(payload)
                    ).done(function (response) {

                        var tokenProcessResponse = JSON.parse(response);

                        if (!tokenProcessResponse.status) {
                            console.error('Error processing Google Pay with merchant:', tokenProcessResponse.message);
                            resolve({
                                transactionState: 'ERROR',
                                error: {
                                    intent: 'PAYMENT_AUTHORIZATION',
                                    message: 'Unable to take payment',
                                    reason: 'PAYMENT_DATA_INVALID'
                                }
                            });
                            return;
                        }
                        // Pass the nonce back to the payment method
                        that.context.setPaymentConfirmationDetails(tokenProcessResponse, paymentData);
                        that.context.placeOrder();

                        resolve({transactionState: 'SUCCESS'});

                    }).fail(function (response) {

                        resolve({
                            transactionState: 'ERROR',
                            error: {
                                intent: 'PAYMENT_AUTHORIZATION',
                                message: 'Unable to take payment',
                                reason: 'PAYMENT_DATA_INVALID'
                            }
                        });

                    });

                })
            },

        };
    }
);