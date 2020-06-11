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
                type: 'realexpayments_googlepay',
                component: 'RealexPayments_Googlepay/js/view/payment/method-renderer/googlepay'
            }
        );

        return Component.extend({});
    }
);