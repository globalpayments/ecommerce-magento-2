define(
    [
        'uiComponent',
        'mage/translate',
        'mage/storage'
    ],
    function (
        Component,
        $t,
        storage
    ) {
        'use strict';

        return Component.extend({

            defaults: {
                clientToken: null,
                quoteId: 0,
                displayName: null,
                actionSuccess: null,
                grandTotalAmount: 0,
                isLoggedIn: false,
                storeCode: "default",
                shippingAddress: {},
                countryDirectory: null,
                currencyCode: null,
                googleMerchantId: null,
                googleMerchantName: null,
                globalpay_merchant_id: null,
                google_allowed_cards: null,
                sandbox: null,
                shippingMethods: {}
            },

            initialize: function () {

                this._super();

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
            },

            /**
             * Get region ID
             */
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


            setAllowedPaymentMethods: function (value) {
                this.google_allowed_cards = value;
            },

            getAllowedPaymentMethods: function () {
                return this.google_allowed_cards;
            },

            setGatewayMerchantId: function (value) {
                this.globalpay_merchant_id = value;
            },

            getGatewayMerchantId: function () {
                return this.globalpay_merchant_id;
            },

            setSandbox: function (value) {
                this.sandbox = value;
            },
            getSandbox: function () {
                return this.sandbox;
            },

            setGoogleMerchantName: function (value) {
                this.googleMerchantName = value;
            },
            getGoogleMerchantName: function () {
                return this.googleMerchantName;
            },

            setGoogleMerchantId: function (value) {
                this.googleMerchantId = value;
            },
            getGoogleMerchantId: function () {
                return this.googleMerchantId;
            },

            setClientToken: function (value) {
                this.clientToken = value;
            },
            getClientToken: function () {
                return this.clientToken;
            },

            setQuoteId: function (value) {
                this.quoteId = value;
            },
            getQuoteId: function () {
                return this.quoteId;
            },

            /**
             * Set and get display name
             */
            setDisplayName: function (value) {
                this.displayName = value;
            },
            getDisplayName: function () {
                return this.displayName;
            },

            /**
             * Set and get success redirection url
             */
            setActionSuccess: function (value) {
                this.actionSuccess = value;
            },
            getActionSuccess: function () {
                return this.actionSuccess;
            },

            /**
             * Set and get grand total
             */
            setGrandTotalAmount: function (value) {
                this.grandTotalAmount = parseFloat(value).toFixed(2);
            },
            getGrandTotalAmount: function () {
                return "" + parseFloat(this.grandTotalAmount).toFixed(2);
            },

            /**
             * Set and get is logged in
             */
            setIsLoggedIn: function (value) {
                this.isLoggedIn = value;
            },
            getIsLoggedIn: function () {
                return this.isLoggedIn;
            },

            /**
             * Set and get currency
             */
            setCurrencyCode: function (value) {
                this.currencyCode = value;
            },
            getCurrencyCode: function () {
                return this.currencyCode;
            },

            /**
             * Set and get store code
             */
            setStoreCode: function (value) {
                this.storeCode = value;
            },
            getStoreCode: function () {
                return this.storeCode;
            },

            /**
             * API Urls for logged in / guest
             */
            getApiUrl: function (uri) {
                if (this.getIsLoggedIn() === true) {
                    return "rest/" + this.getStoreCode() + "/V1/carts/mine/" + uri;
                } else {
                    return "rest/" + this.getStoreCode() + "/V1/guest-carts/" + this.getQuoteId() + "/" + uri;
                }
            },

        });
    });
