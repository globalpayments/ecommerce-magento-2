define(["jquery"], function ($) {
        "use strict";

        return function (config) {
            var interval = config.interval || 2000;
            var cycles = 0;
            var loader = $('#gp-apm-result .gp-apm-loader');
            var panic = function () {
                loader.text('Something went wrong');
            };

            if (config.hasOwnProperty('statusFetchUrl') && config.hasOwnProperty('finalRedirectUrl')) {
                var fetchStatus = function() {
                    $.get({
                        url: config.statusFetchUrl
                    }).done(function (res) {
                        if (res.hasOwnProperty('isPending') && res.isPending === false) {
                            window.top.location = config.finalRedirectUrl;
                        }
                    }).always(function () {
                        cycles++;
                        if (cycles >= 3) {
                            window.top.location = config.finalRedirectUrl;
                        }
                        else {
                            setTimeout(fetchStatus, interval);
                        }
                    });
                }

                setTimeout(fetchStatus, interval);
            } else {
                console.error(config.statusFetchUrl, config.finalRedirectUrl);
                panic();
            }
        }
    }
)
