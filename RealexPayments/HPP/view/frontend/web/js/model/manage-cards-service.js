define(
    [
        'ko',
        'jquery'
    ],
    function(ko, $) {
        'use strict';
        var iframeHeight = ko.observable('640px');
        var iframeWidth = ko.observable('100%');
        var iframeUrl = ko.observable($('#realex-iframe').val());

        return {
            iframeHeight: iframeHeight,
            iframeWidth: iframeWidth,
            iframeUrl: iframeUrl,
            iframeResize: function(event) {
                var data = JSON.parse(event);
                if (data.iframe) {
                    if (this.iframeHeight() != data.iframe.height) {
                        this.iframeHeight(data.iframe.height);
                    }
                    if (this.iframeWidth() != data.iframe.width) {
                        this.iframeWidth(data.iframe.width);
                    }
                }
            }
        };
    });
