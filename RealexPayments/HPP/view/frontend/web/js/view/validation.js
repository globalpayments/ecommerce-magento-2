define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'RealexPayments_HPP/js/model/validation',
    ],
    function (Component, additionalValidators, hppValidator) {
        'use strict';
        additionalValidators.registerValidator(hppValidator);
        return Component.extend({});
    }
);