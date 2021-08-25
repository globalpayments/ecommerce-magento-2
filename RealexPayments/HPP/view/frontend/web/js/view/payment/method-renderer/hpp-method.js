/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'RealexPayments_HPP/js/action/set-payment-method',
        'RealexPayments_HPP/js/action/lightbox',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'RealexPayments_HPP/js/model/realex-payment-service',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor'
    ],
    function(ko, $, Component, setPaymentMethodAction, lightboxAction, quote,
        additionalValidators, realexPaymentService, fullScreenLoader, errorProcessor) {
        'use strict';
        var paymentMethod = ko.observable(null);

        return Component.extend({
            self: this,
            defaults: {
                template: 'RealexPayments_HPP/payment/hpp-form'
            },
            isInAction: realexPaymentService.isInAction,
            isLightboxReady: realexPaymentService.isLightboxReady,
            iframeHeight: realexPaymentService.iframeHeight,
            iframeWidth: realexPaymentService.iframeWidth,
            initialize: function() {
                this._super();
                $(window).bind('message', function(event) {
                    realexPaymentService.iframeResize(event.originalEvent.data);
                });
            },
            resetIframe: function() {
                this.isLightboxReady(false);
                this.isInAction(false);
            },
            /**
             * Get action url for payment method iframe.
             * @returns {String}
             */
            getActionUrl: function() {
                return this.isInAction() ? window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl : '';
            },
            /** Redirect */
            continueToPayment: function() {
                this.resetIframe();
                if (this.validate() && additionalValidators.validate()) {
                    if (window.checkoutConfig.payment[quote.paymentMethod().method].iframeEnabled === '1') {
                        setPaymentMethodAction()
                            .done(
                                function() {
                                    realexPaymentService.isInAction(true);
                                    realexPaymentService.isLightboxReady(true);
                                    if (window.checkoutConfig.payment[quote.paymentMethod().method].iframeMode === 'lightbox') {
                                        lightboxAction();
                                    } else {
                                        // capture all click events
                                        document.addEventListener('click', function cb(event) {
                                            realexPaymentService.stopEventPropagation(event);
                                            if (realexPaymentService.leaveIframeForLinks(event)) {
                                                event.currentTarget.removeEventListener(event.type, cb);
                                            }
                                        });
                                        //These two are left to indicate the different methods this can be used.
                                        //document.addEventListener('click', realexPaymentService.stopEventPropagation, true);
                                        //document.addEventListener('click', realexPaymentService.leaveEmbeddedIframe, true);
                                    }
                                }
                            ).fail(
                                function(response) {
                                    errorProcessor.process(response);
                                    fullScreenLoader.stopLoader();
                                }
                            );
                    } else {
                        setPaymentMethodAction()
                            .done(
                                function() {
                                    $.mage.redirect(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl);
                                }
                            ).fail(
                                function(response) {
                                    errorProcessor.process(response);
                                    fullScreenLoader.stopLoader();
                                }
                            );
                    }
                    return false;
                }
            },
            validate: function() {
                return true;
            },
            /**
             * Hide loader when iframe is fully loaded.
             * @returns {void}
             */
            iframeLoaded: function() {
                fullScreenLoader.stopLoader();
            }
        });
    }
);