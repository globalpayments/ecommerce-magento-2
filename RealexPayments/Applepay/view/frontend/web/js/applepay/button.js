define(
    [
        'uiComponent',
        "knockout",
        "jquery",
        'mage/translate',
        'mage/storage',
        'Magento_Customer/js/customer-data',
    ],
    function (
        Component,
        ko,
        jQuery,
        $t,
        storage,
        customerData
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

                        jQuery("body").loader('show');
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

                applePaySession.onshippingcontactselected = function (event) {

                    return this.onShippingContactSelect(event, applePaySession);

                }.bind(this);

                applePaySession.onshippingmethodselected = function (event) {

                    return this.onShippingMethodSelect(event, applePaySession);

                }.bind(this);

                applePaySession.oncancel = function (event){

                    alert($t("We're unable to take your payment through Apple Pay. Please try an again or use an alternative payment method."));
                    jQuery("body").loader('hide');

                }.bind(this);

                return applePaySession;

            },

            buildApplePaymentRequest: function(options) {

                return {
                    countryCode: 'GB',
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
                    },
                    requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone'],
                    requiredBillingContactFields: ['postalAddress', 'name']
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

                    jQuery("body").loader('hide');

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
                        jQuery("body").loader('hide');
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        return;
                    }

                    that.placeOrder(tokenProcessResponse, event, session);
                    session.completePayment(ApplePaySession.STATUS_SUCCESS);


                }).fail(function (response) {

                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    alert($t("We're unable to take your payment through Apple Pay. Please try an again or use an alternative payment method."));
                    jQuery("body").loader('hide');

                });

                //session.completePayment(ApplePaySession.STATUS_SUCCESS);

            },

            placeOrder(paymentToken, event, session) {

                let shippingContact = event.payment.shippingContact;
                let billingContact = event.payment.billingContact;

                let payload = {
                    "addressInformation": {
                        "shipping_address": {
                            "email": shippingContact.emailAddress,
                            "telephone": shippingContact.phoneNumber,
                            "firstname": shippingContact.givenName,
                            "lastname": shippingContact.familyName,
                            "street": shippingContact.addressLines,
                            "city": shippingContact.locality,
                            "region": shippingContact.administrativeArea,
                            "region_id": this.context.getRegionId(shippingContact.countryCode.toUpperCase(), shippingContact.administrativeArea),
                            "region_code": null,
                            "country_id": shippingContact.countryCode.toUpperCase(),
                            "postcode": shippingContact.postalCode,
                            "same_as_billing": 0,
                            "customer_address_id": 0,
                            "save_in_address_book": 0
                        },
                        "billing_address": {
                            "email": shippingContact.emailAddress,
                            "telephone": '0000000000',
                            "firstname": billingContact.givenName,
                            "lastname": billingContact.familyName,
                            "street": billingContact.addressLines,
                            "city": billingContact.locality,
                            "region": billingContact.administrativeArea,
                            "region_id": this.context.getRegionId(billingContact.countryCode.toUpperCase(), billingContact.administrativeArea),
                            "region_code": null,
                            "country_id": billingContact.countryCode.toUpperCase(),
                            "postcode": billingContact.postalCode,
                            "same_as_billing": 0,
                            "customer_address_id": 0,
                            "save_in_address_book": 0
                        },
                        "shipping_method_code": this.shippingMethods[this.shippingMethod].method_code,
                        "shipping_carrier_code": this.shippingMethods[this.shippingMethod].carrier_code
                    }
                };

                // Set addresses
                storage.post(
                    this.context.getApiUrl("shipping-information"),
                    JSON.stringify(payload)
                ).done(function () {

                    let paymentPayload = {
                        "email": shippingContact.emailAddress,
                        "paymentMethod": {
                            "method": "realexpayments_applepay",
                            "additional_data": {
                                "payment_method_nonce": paymentToken.paymentsReference
                            }
                        }
                    }

                    storage.post(
                        this.context.getApiUrl("payment-information"),
                        JSON.stringify(paymentPayload)
                    ).done(function (r) {

                        //this.placeOrder();
                        // Clear the baset of items.
                        var sections = ['cart'];
                        customerData.invalidate(sections);
                        customerData.reload(sections, true);

                        document.location = this.context.getActionSuccess();
                        session.completePayment(ApplePaySession.STATUS_SUCCESS);

                    }.bind(this)).fail(function (r) {

                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        session.abort();
                        alert($t("We're unable to take your payment through Apple Pay. Please try an again or use an alternative payment method."));
                        console.error("ApplePay Unable to take payment", r);
                        jQuery("body").loader('hide');

                        return false;
                    });

                }.bind(this)).fail(function (r) {

                    console.error("ApplePay Unable to set shipping information", r);
                    session.completePayment(ApplePaySession.STATUS_INVALID_BILLING_POSTAL_ADDRESS);
                    jQuery("body").loader('hide');

                });

            },

            onShippingContactSelect: function (event, session) {

                let address = event.shippingContact;

                let payload = {
                    address: {
                        city: address.locality,
                        region: address.administrativeArea,
                        country_id: address.countryCode.toUpperCase(),
                        postcode: address.postalCode,
                        save_in_address_book: 0
                    }
                };

                this.shippingAddress = payload.address;

                storage.post(
                    this.context.getApiUrl("estimate-shipping-methods"),
                    JSON.stringify(payload)
                ).done(function (result) {
                    // Stop if no shipping methods.
                    if (result.length === 0) {
                        session.abort();
                        alert($t("There are no shipping methods available for you right now. Please try again or use an alternative payment method."));
                        return false;
                    }

                    let shippingMethods = [];
                    this.shippingMethods = {};

                    // Format shipping methods array.
                    for (let i = 0; i < result.length; i++) {
                        if (typeof result[i].method_code !== 'string') {
                            continue;
                        }

                        let method = {
                            identifier: result[i].method_code,
                            label: result[i].method_title,
                            detail: result[i].carrier_title ? result[i].carrier_title : "",
                            amount: parseFloat(result[i].amount).toFixed(2)
                        };

                        // Add method object to array.
                        shippingMethods.push(method);

                        this.shippingMethods[result[i].method_code] = result[i];

                        if (!this.shippingMethod) {
                            this.shippingMethod = result[i].method_code;
                        }
                    }

                    // Create payload to get totals
                    let totalsPayload = {
                        "addressInformation": {
                            "address": {
                                "countryId": this.shippingAddress.country_id,
                                "region": this.shippingAddress.region,
                                "regionId": this.context.getRegionId(this.shippingAddress.country_id, this.shippingAddress.region),
                                "postcode": this.shippingAddress.postcode
                            },
                            "shipping_method_code": this.shippingMethods[shippingMethods[0].identifier].method_code,
                            "shipping_carrier_code": this.shippingMethods[shippingMethods[0].identifier].carrier_code
                        }
                    };

                    // POST to endpoint to get totals, using 1st shipping method
                    storage.post(
                        this.context.getApiUrl("totals-information"),
                        JSON.stringify(totalsPayload)
                    ).done(function (result) {
                        // Set total
                        this.context.setGrandTotalAmount(result.base_grand_total);

                        // Pass shipping methods back
                        session.completeShippingContactSelection(
                            ApplePaySession.STATUS_SUCCESS,
                            shippingMethods,
                            {
                                label: this.context.getDisplayName(),
                                amount: this.context.getGrandTotalAmount()
                            },
                            [{
                                type: 'final',
                                label: $t('Shipping'),
                                amount: shippingMethods[0].amount
                            }]
                        );

                    }.bind(this)).fail(function (result) {
                        session.abort();

                        alert($t("We're unable to fetch the cart totals for you. Please try an alternative payment method."));
                        console.error("ApplePay: Unable to get totals", result);
                        jQuery("body").loader('hide');
                        return false;
                    });

                }.bind(this)).fail(function (result) {
                    session.abort();
                    alert($t("We're unable to find any shipping methods for you. Please try an alternative payment method."));
                    console.error("ApplePay: Unable to find shipping methods for estimate-shipping-methods", result);
                    jQuery("body").loader('hide');
                    return false;
                });
            },

            /**
             * Record which shipping method has been selected & Updated totals
             */
            onShippingMethodSelect: function (event, session) {
                let shippingMethod = event.shippingMethod;
                this.shippingMethod = shippingMethod.identifier;

                let payload = {
                    "addressInformation": {
                        "address": {
                            "countryId": this.shippingAddress.country_id,
                            "region": this.shippingAddress.region,
                            "regionId": this.getRegionId(this.shippingAddress.country_id, this.shippingAddress.region),
                            "postcode": this.shippingAddress.postcode
                        },
                        "shipping_method_code": this.shippingMethods[this.shippingMethod].method_code,
                        "shipping_carrier_code": this.shippingMethods[this.shippingMethod].carrier_code
                    }
                };

                storage.post(
                    this.context.getApiUrl("totals-information"),
                    JSON.stringify(payload)
                ).done(function (r) {
                    this.context.setGrandTotalAmount(r.base_grand_total);

                    session.completeShippingMethodSelection(
                        ApplePaySession.STATUS_SUCCESS,
                        {
                            label: this.context.getDisplayName(),
                            amount: r.base_grand_total
                        },
                        [{
                            type: 'final',
                            label: $t('Shipping'),
                            amount: shippingMethod.amount
                        }]
                    );
                }.bind(this));
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