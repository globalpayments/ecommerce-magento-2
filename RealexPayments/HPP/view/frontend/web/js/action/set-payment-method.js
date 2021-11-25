define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default'
    ],
    function($, Component) {
        'use strict';
        /**
         * This function adds extension attributes to paymentData
         * the attributes param must be an array of objects,
         * with each object having a code and a value.
         * You also need to add those attributes to your extension_attributes.xml,
         * where they need to be set for: Magento\Quote\Api\Data\PaymentInterface
         *
         * @param attributes
         * @param paymentData
         */
        var addExtensionAttributes = function(attributes, paymentData) {
            if (Array.isArray(attributes)) {
                for (var i = 0; i < attributes.length; i++) {
                    var currentAttribute = attributes[i];
                    paymentData.extension_attributes[currentAttribute.code] = currentAttribute.value;
                }
            }
        }

        return function (extensionAttributes) {
            return Component.extend({
                defaults: {
                    redirectAfterPlaceOrder: false,
                    pluginData: {
                        'method': this.getCode()
                    }
                },

                getData: function() {
                    /**
                     * If we have the extensionAttributes parameter,
                     * we add them to the paymentData object
                     */
                    if (extensionAttributes && extensionAttributes.length > 0) {
                        if (!this.pluginData.extension_attributes) {
                            this.pluginData.extension_attributes = {};
                        }
                        addExtensionAttributes(extensionAttributes, this.pluginData);
                    }

                    return this.pluginData;
                }
            });
        }
    }
);
