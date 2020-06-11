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
            allowedCardNetworks: ["AMEX", "DISCOVER", "INTERAC", "JCB", "MASTERCARD", "VISA"],
            allowedCardAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
            googlePayClient: null,
            shippingLookupResponse: null,
            shippingMethods: [],
            shippingCosts: [],
            shippingMethodsSave: {},
            countryDirectory: null,


            init: function (element, context) {

                if (!this.countryDirectory) {
                    storage.get("rest/V1/directory/countries").done(function (result) {
                        this.countryDirectory = {};
                        let i, data, x, region;
                        for (i = 0; i < result.length; ++i) {
                            data = result[i];
                            this.countryDirectory[data.two_letter_abbreviation] = {};
                            if (typeof data.available_regions !== 'undefined') {
                                for (x = 0; x < data.available_regions.length; ++x) {
                                    region = data.available_regions[x];
                                    this.countryDirectory[data.two_letter_abbreviation][region.name.toLowerCase().replace(/[^A-Z0-9]/ig, '')] = region.id;
                                }
                            }
                        }
                    }.bind(this));
                }

                // No element or context
                if (!element || !context) {
                    return;
                }

                this.context = context;

                this.onPaymentAuthorized = this.onPaymentAuthorized.bind(this);
                this.onPaymentDataChanged = this.onPaymentDataChanged.bind(this);

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
                        allowedCardNetworks: this.context.getAllowedPaymentMethods(),
                        billingAddressRequired: true,
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
                            onPaymentAuthorized: this.onPaymentAuthorized,
                            onPaymentDataChanged: this.onPaymentDataChanged
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

            onPaymentDataChanged: async function(intermediatePaymentData) {

                try {

                    let shippingAddress = intermediatePaymentData.shippingAddress;
                    let shippingOptionData = intermediatePaymentData.shippingOptionData;
                    let paymentDataRequestUpdate = {};

                    if (intermediatePaymentData.callbackTrigger == "INITIALIZE" || intermediatePaymentData.callbackTrigger == "SHIPPING_ADDRESS") {

                            paymentDataRequestUpdate.newShippingOptionParameters = await this.getGoogleDefaultShippingOptions(shippingAddress);
                            let selectedShippingOptionId = paymentDataRequestUpdate.newShippingOptionParameters.defaultSelectedOptionId;
                            paymentDataRequestUpdate.newTransactionInfo = this.calculateNewTransactionInfo(selectedShippingOptionId);

                    }
                    else if (intermediatePaymentData.callbackTrigger == "SHIPPING_OPTION") {
                        paymentDataRequestUpdate.newTransactionInfo = this.calculateNewTransactionInfo(shippingOptionData.id);
                    }

                    return paymentDataRequestUpdate;
                }
                catch(error) {

                    console.log(error);

                }

            },

            calculateNewTransactionInfo: function(shippingOptionId) {
                let newTransactionInfo = this.getGoogleTransactionInfo();

                let shippingCost = this.getShippingCosts()[shippingOptionId];

                newTransactionInfo.displayItems.push({
                    type: "LINE_ITEM",
                    label: "Shipping cost",
                    price: shippingCost,
                    status: "FINAL"
                });

                let totalPrice = 0.00;
                newTransactionInfo.displayItems.forEach(displayItem => totalPrice += parseFloat(displayItem.price));
                newTransactionInfo.totalPrice = totalPrice.toString();

                return newTransactionInfo;
            },

            getGoogleTransactionInfo: function() {

                return {
                    displayItems: [
                        {
                            label: "Total",
                            type: "SUBTOTAL",
                            price: this.context.getGrandTotalAmount(),
                        }
                    ],
                    currencyCode: this.context.getCurrencyCode(),
                    totalPriceStatus: "FINAL",
                    totalPrice: this.context.getGrandTotalAmount(),
                    totalPriceLabel: "Total"
                };
            },


            getShippingCosts: function() {
                return this.shippingCosts;
            },

            getGoogleShippingAddressParameters: function() {
                return  {
                    phoneNumberRequired: true
                };
            },

            getGoogleDefaultShippingOptions: async function(shippingAddress) {

                    let payload = {
                        address: {
                            city: shippingAddress.locality,
                            region: shippingAddress.administrativeArea,
                            country_id: shippingAddress.countryCode.toUpperCase(),
                            postcode: shippingAddress.postalCode,
                            save_in_address_book: 0
                        }
                    };

                    this.shippingAddress = payload.address;
                    this.shippingLookupResponse = {}

                    await storage.post(
                        this.context.getApiUrl("estimate-shipping-methods"),
                        JSON.stringify(payload)
                    ).done(function (result) {

                        this.shippingMethods = []
                        this.shippingCosts = {}

                        if (result.length === 0) {
                            this.shippingLookupResponse = this.getGoogleUnserviceableAddressError();
                        }

                        // Create a list of shipping methods
                        for (let i = 0; i < result.length; i++) {

                            if (typeof result[i].method_code !== 'string') {
                                continue;
                            }

                            let method = {
                                id: result[i].method_code,
                                label: result[i].method_title,
                                description: parseFloat(result[i].amount).toFixed(2) + " - " + (result[i].carrier_title ? result[i].carrier_title : ""),
                                amount: parseFloat(result[i].amount).toFixed(2),
                                method_code: result[i].method_code,
                                carrier_code: result[i].carrier_code
                            };

                            //let cost = {};
                            this.shippingCosts[result[i].method_code] = parseFloat(result[i].amount).toFixed(2);

                            // Add method object to array.
                            //this.shippingCosts.push(cost);
                            this.shippingMethods.push(method);
                            this.shippingMethodsSave[result[i].method_code] = method;
                        }

                        // Format the shipping methods into the required google format.

                        let googleShippingResponse = {}
                        if(this.shippingMethods.length > 0) {

                            // Set the first one as the default.
                            googleShippingResponse.defaultSelectedOptionId = this.shippingMethods[0].id;
                            googleShippingResponse.shippingOptions = [];

                            for (let i = 0; i < this.shippingMethods.length; i++) {
                                googleShippingResponse.shippingOptions.push(
                                    {
                                        "id": this.shippingMethods[i].id,
                                        "label": this.shippingMethods[i].label,
                                        "description": this.shippingMethods[i].description
                                    }
                                )
                            }
                        }

                        this.shippingLookupResponse = googleShippingResponse;

                        return this.shippingLookupResponse;

                    }.bind(this)).fail(function (result) {
                        this.shippingLookupResponse = this.getGoogleUnserviceableAddressError();
                        return this.shippingLookupResponse;
                    }.bind(this));
                return this.shippingLookupResponse;
            },

            getGoogleUnserviceableAddressError: function() {
                return {
                    reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
                    message: "Cannot ship to the selected address",
                    intent: "SHIPPING_ADDRESS"
                };
            },


            getGooglePaymentDataRequest: function() {

                var paymentDataRequest = Object.assign({}, {
                    apiVersion: this.apiVersion,
                    apiVersionMinor: this.apiVersionMinor
                });

                paymentDataRequest.allowedPaymentMethods = [this.getCardPaymentMethod()];
                paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();

                paymentDataRequest.merchantInfo = {
                    merchantName: this.context.getGoogleMerchantName()
                };

                // Pass merchant ID in PRODUCTION.
                if(!this.context.getSandbox()) {
                    paymentsClientConfig.merchantInfo.merchantId = this.context.getGoogleMerchantId();
                }

                paymentDataRequest.callbackIntents = ["SHIPPING_ADDRESS",  "SHIPPING_OPTION", "PAYMENT_AUTHORIZATION"];
                paymentDataRequest.emailRequired = true;
                paymentDataRequest.shippingAddressRequired = true;
                paymentDataRequest.shippingAddressParameters = this.getGoogleShippingAddressParameters();
                paymentDataRequest.shippingOptionRequired = true;

                return paymentDataRequest;
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

                                    jQuery("body").loader('show');

                                    var paymentDataRequest = that.getGooglePaymentDataRequest();
                                    paymentDataRequest.transactionInfo = that.getGoogleTransactionInfo();

                                    that.getGooglePaymentsClient().loadPaymentData(paymentDataRequest)
                                    .then(function(paymentData) {


                                    })
                                    .catch(function(err) {
                                        // show error in developer console for debugging
                                        jQuery("body").loader('hide');
                                        console.error(err);
                                    });


                                }
                            });

                            element.appendChild(button);

                        }
                    }).catch(function(err) {
                        // show error in developer console for debugging
                        jQuery("body").loader('hide');
                        console.error(err);
                    });

            },

            onPaymentAuthorized: function(paymentData) {

                var that = this;

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
                        that.placeOrder(tokenProcessResponse, paymentData, resolve);
                        //that.context.setPaymentMethodNonce(tokenProcessResponse.paymentsReference);
                        //that.context.placeOrder();

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

            placeOrder(paymentToken, paymentResponse, resolve) {

                let that = this;
                let shippingContact = paymentResponse.shippingAddress;
                let billingContact = paymentResponse.paymentMethodData.info.billingAddress;

                let payload = {
                    "addressInformation": {
                        "shipping_address": {
                            "email": paymentResponse.email,
                            "telephone": shippingContact.phoneNumber,
                            "firstname": shippingContact.name.split(' ').slice(0, -1).join(' '),
                            "lastname": shippingContact.name.split(' ').slice(-1).join(' '),
                            "street": [shippingContact.address1, shippingContact.address2, shippingContact.address3],
                            "city": shippingContact.locality,
                            "region": shippingContact.administrativeArea,
                            "region_id": this.getRegionId(shippingContact.countryCode.toUpperCase(), shippingContact.administrativeArea),
                            "region_code": null,
                            "country_id": shippingContact.countryCode.toUpperCase(),
                            "postcode": shippingContact.postalCode,
                            "same_as_billing": 0,
                            "customer_address_id": 0,
                            "save_in_address_book": 0
                        },
                        "billing_address": {
                            "email": paymentResponse.email,
                            "telephone": '0000000000',
                            "firstname": shippingContact.name.split(' ').slice(0, -1).join(' '),
                            "lastname": shippingContact.name.split(' ').slice(-1).join(' '),
                            "street": ["N/A", "N/A"],
                            //"street": "N/A",
                            "city": "N/A",
                            "region": "N/A",
                            "region_id": null,
                            "region_code": null,
                            "country_id": billingContact.countryCode.toUpperCase(),
                            "postcode": billingContact.postalCode,
                            "same_as_billing": 0,
                            "customer_address_id": 0,
                            "save_in_address_book": 0
                        },
                        "shipping_method_code": this.shippingMethodsSave[paymentResponse.shippingOptionData.id].method_code,
                        "shipping_carrier_code": this.shippingMethodsSave[paymentResponse.shippingOptionData.id].carrier_code
                    }
                };

                // Set addresses
                storage.post(
                    this.context.getApiUrl("shipping-information"),
                    JSON.stringify(payload)
                ).done(function () {

                    let paymentPayload = {
                        "email": paymentResponse.email,
                        "paymentMethod": {
                            "method": "realexpayments_googlepay",
                            'additional_data': {
                                'payment_ref': paymentToken.paymentsReference,
                                'order_ref': paymentToken.orderId,
                                'card': paymentResponse.paymentMethodData.description
                            }
                        }
                    }

                    storage.post(
                        this.context.getApiUrl("payment-information"),
                        JSON.stringify(paymentPayload)
                    ).done(function (r) {

                        resolve({transactionState: 'SUCCESS'});
                        document.location = this.context.getActionSuccess();

                    }.bind(this)).fail(function (r) {

                        alert($t("We're unable to take your payment through Google Pay. Please try an again or use an alternative payment method."));

                        resolve({
                            transactionState: 'ERROR',
                            error: {
                                intent: 'PAYMENT_AUTHORIZATION',
                                message: 'Unable to take payment',
                                reason: 'PAYMENT_DATA_INVALID'
                            }
                        });

                        jQuery("body").loader('hide');

                        return false;
                    });

                }.bind(this)).fail(function (r) {

                    resolve({
                        transactionState: 'ERROR',
                        error: {
                            intent: 'PAYMENT_AUTHORIZATION',
                            message: 'Unable to take payment',
                            reason: 'PAYMENT_DATA_INVALID'
                        }
                    });
                    jQuery("body").loader('hide');

                });

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

        };
    }
);