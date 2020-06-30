define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'realexpayments_hpp',
                component: 'RealexPayments_HPP/js/view/payment/method-renderer/hpp-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
