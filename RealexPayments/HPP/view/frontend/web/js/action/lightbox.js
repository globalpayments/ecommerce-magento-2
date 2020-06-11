define(
    [
        'jquery',
        'RealexPayments_HPP/js/action/restore-cart',
        'RealexPayments_HPP/js/model/realex-payment-service',
        'Magento_Ui/js/modal/modal',
        'mage/translate'
    ],
    function($, restoreCartAction, realexPaymentService, modal, $t) {
        'use strict';

        return function() {

            var options = {
                type: 'popup',
                responsive: true,
                innerScroll: false,
                buttons: [{
                    class: '',
                    text: $t('Cancel'),
                    click: function() {
                        this.closeModal();
                    }
                }],
                closed: function() {
                    restoreCartAction();
                    realexPaymentService.isInAction(false);
                    realexPaymentService.isLightboxReady(false);
                }
            };
            $("#realex-iframe-container").modal(options).modal('openModal');
        };
    }
);
