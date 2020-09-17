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
                var shippingAddress = quote.shippingAddress();

                var isValid = true;

                if (!this.isValidName(shippingAddress.firstname ||
                    !this.isValidName(shippingAddress.lastname)) ||
                    !this.isValidShippingStreet(shippingAddress.street)) {
                    isValid = false;
                    messageList.addErrorMessage({
                        message: $t('Please check the following fields: First name, Last name and Shipping address. ' +
                            'The selected payment method only allows letters, numbers, spaces or punctuation only, and no more than 50 characters.')
                    });
                }

                return isValid;
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
             * Validate shipping street
             *
             * @param {Array} shippingStreet
             * @return {Boolean}
             */
            isValidShippingStreet: function (shippingStreet) {
                var isValid = true;

                let pattern = /([^A-Za-z0-9\xC0-\xD6\xD8-\xf6\xf8-\xff\/\.\-_',\s])+/u;

                shippingStreet.forEach(function (item) {
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