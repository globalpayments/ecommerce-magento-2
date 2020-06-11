define(
    [
        'uiComponent',
        "knockout",
        "jquery",
        'mage/translate',
        'mage/storage',
    ],
    function (
        Component,
        ko,
        jQuery,
        $t,
        storage
    ) {
        'use strict';

        var that;

        return {
            init: function (element, context) {

                // No element or context
                if (!element || !context) {
                    return;
                }

                this.context = context;

                this.initButton(element);

            },

            initButton: function(element) {

                var that = this;

                if(this.deviceSupported()) {

                    var paymentButton = document.createElement('div');
                    paymentButton.className = "apple-pay-button apple-pay-button-black";
                    paymentButton.title = $t("Pay with Apple Pay");
                    paymentButton.alt = $t("Pay with Apple Pay");

                    paymentButton.addEventListener('click', function (e) {
                        e.preventDefault();

                        var options = options || {};

                        var applePaySession = that.createApplePaySession(options);
                        applePaySession.begin();

                    });

                    element.appendChild(paymentButton);
                }

            },

            createApplePaySession: function(options) {

                var applePaySession = new ApplePaySession(1, this.buildApplePaymentRequest(options));

                applePaySession.onvalidatemerchant = function (event) {

                    this.onApplePayValidateMerchant(event, applePaySession);

                }.bind(this);

                applePaySession.onpaymentauthorized = function (event) {

                    this.onApplePayPaymentAuthorize(event, applePaySession, options);

                }.bind(this);

                applePaySession.oncancel = function (event){

                    console.log(event);
                    console.log("Cancel pressed");

                }.bind(this);

                return applePaySession;

            },

            buildApplePaymentRequest: function(options) {

                return {
                    countryCode: "GB",
                    currencyCode: this.context.getCurrencyCode(),
                    merchantCapabilities: [
                        "supports3DS"
                    ],
                    supportedNetworks: [
                        "visa",
                        "masterCard",
                        "amex",
                        "discover"
                    ],
                    total: {
                        "label": "Total",
                        "type": "final",
                        "amount": this.context.getGrandTotalAmount()
                    }
                }

            },

            onApplePayValidateMerchant: function(event, session) {

                var serviceUrl = 'rest/V1/applepay/validateMerchant';
                var payload = {
                    validationUrl: event.validationURL,
                    quoteId: this.context.getQuoteId()
                };

                storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).done(function (response) {

                    session.completeMerchantValidation(JSON.parse(response));

                }).fail(function (response) {

                    console.log("Failure response");
                    console.log(response);

                });

            },

            onApplePayPaymentAuthorize: function(event, session, options) {

                var that = this;

                var serviceUrl = 'rest/V1/applepay/processPaymentToken';
                var payload = {
                    paymentToken: JSON.stringify(event.payment.token.paymentData),
                    quoteId: this.context.getQuoteId()
                };

                storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).done(function (response) {

                    var tokenProcessResponse = JSON.parse(response);

                    if (!tokenProcessResponse.status) {
                        console.error('Error processing Apple Pay with merchant:', tokenProcessResponse.message);
                        alert($t("We're unable to take your payment through Apple Pay. Please try an again or use an alternative payment method."));
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        return;
                    }

                    that.context.placeOrder();

                    session.completePayment(ApplePaySession.STATUS_SUCCESS);

                }).fail(function (response) {

                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    alert($t("We're unable to take your payment through Apple Pay. Please try an again or use an alternative payment method."));
                    return;

                });

            },

            getApiUrl: function (uri) {
                if (this.getIsLoggedIn() === true) {
                    return "rest/V1/carts/mine/" + uri;
                } else {
                    return "rest/V1/guest-carts/" + this.context.getQuoteId() + "/" + uri;
                }
            },

            getRegionId: function (countryCode, regionName) {
                if (typeof regionName !== 'string') {
                    return null;
                }

                regionName = regionName.toLowerCase().replace(/[^A-Z0-9]/ig, '');

                if (typeof this.countryDirectory[countryCode] !== 'undefined' && typeof this.countryDirectory[countryCode][regionName] !== 'undefined') {
                    return this.countryDirectory[countryCode][regionName];
                }

                return 0;
            },

            deviceSupported: function () {

                if (location.protocol != 'https:') {
                    console.warn("Apple Pay requires your checkout be served over HTTPS");
                    return false;
                }

                if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
                    console.warn("Apple Pay is not supported on this device/browser");
                    return false;
                }

                return true;
            }
        };
    }
);