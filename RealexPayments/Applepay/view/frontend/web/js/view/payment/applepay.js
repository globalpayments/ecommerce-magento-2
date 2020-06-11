define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'realexpayments_applepay',
                component: 'RealexPayments_Applepay/js/view/payment/method-renderer/applepay'
            }
        );

        return Component.extend({});
    }
);