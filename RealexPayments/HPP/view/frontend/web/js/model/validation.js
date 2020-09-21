define(
    [
        'mage/translate',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote'
    ],
    function ($t, messageList, quote) {
        'use strict';
        return {
            validate: function () {
                var shippingAddress = quote.shippingAddress(),
                    billingAddress = quote.billingAddress();

                if (!this.isValidName(shippingAddress.firstname)) {
                    messageList.addErrorMessage({
                        message: $t('Please check the First name. ' +
                            'The selected payment method only allows letters, numbers, spaces or punctuation only, and no more than 60 characters.')
                    });

                    return false;
                }

                if (!this.isValidName(shippingAddress.lastname)) {
                    messageList.addErrorMessage({
                        message: $t('Please check the Last name. ' +
                            'The selected payment method only allows letters, numbers, spaces or punctuation only, and no more than 60 characters.')
                    });

                    return false;
                }

                if (!this.isValidAddress(shippingAddress.street)) {
                    messageList.addErrorMessage({
                        message: $t('Please check the Shipping address. ' +
                            'The selected payment method only allows letters, numbers, spaces or punctuation only, and no more than 50 characters.')
                    });

                    return false;
                }

                if (!this.isValidAddress(billingAddress.street)) {
                    messageList.addErrorMessage({
                        message: $t('Please check the Billing address. ' +
                            'The selected payment method only allows letters, numbers, spaces or punctuation only, and no more than 50 characters.')
                    });

                    return false;
                }

                return true;
            },

            /**
             * Validate name
             *
             * @param {string} name
             * @return {Boolean}
             */
            isValidName: function (name) {
                var isValid = true;

                let pattern = /([^A-Za-z0-9\xC0-\xD6\xD8-\xf6\xf8-\xff\[\]\/\.\-_',\s])+/u;

                if (pattern.test(name) || name.length>60) {
                    isValid = false;
                }

                return isValid;
            },

            /**
             * Validate address
             *
             * @param {Array} address
             * @return {Boolean}
             */
            isValidAddress: function (address) {
                var isValid = true;

                let pattern = /([^A-Za-z0-9\xC0-\xD6\xD8-\xf6\xf8-\xff\/\.\-_',\s])+/u;

                address.forEach(function (item) {
                    if (pattern.test(item) || item.length>50) {
                        isValid = false;

                        return false;
                    }
                });

                return isValid;
            }
        }
    }
);