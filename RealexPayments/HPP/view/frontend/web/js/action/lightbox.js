define(
    [
        'jquery',
        'RealexPayments_HPP/js/action/restore-cart',
        'RealexPayments_HPP/js/model/realex-payment-service',
        'Magento_Ui/js/modal/modal',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate'
    ],
    function($, restoreCartAction, realexPaymentService, modal, fullScreenLoader, $t) {
        'use strict';

        return function() {

            var options = {
                type: 'popup',
                responsive: true,
                innerScroll: false,
                clickableOverlay: false,
                buttons: [{
                    class: '',
                    text: $t('Cancel'),
                    click: function() {
                        this.closeModal();
                    }
                }],
                closed: function() {
                    fullScreenLoader.startLoader();
                    restoreCartAction()
                        .fail(
                            function(response) {
                                errorProcessor.process(response);
                            }
                        ).always(
                            function() {
                                realexPaymentService.resetIframe();
                                fullScreenLoader.stopLoader();
                            }
                    );
                }
            };
            $("#realex-iframe-container").modal(options).modal('openModal');
        };
    }
);
