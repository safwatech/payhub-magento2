define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/quote',
    'mage/url',
], function (Component, placeOrder, quote, urlBuilder) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Payhub_Payments/payment/payhub',
            msisdn: '',
            birthYear: '',
        },
        initObservable: function () {
            this._super().observe(['msisdn', 'birthYear']);
            return this;
        },
        getCode: function () { return 'payhub'; },
        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {
                    payhub_msisdn: this.msisdn(),
                    payhub_birth_year: this.birthYear(),
                },
            };
        },
        afterPlaceOrder: function () {
            // initialize() on the server side stashed payhub_redirect_url
            // in the order's payment additional information; we pull it
            // from the standard checkout success URL params.
            var orderId = window.checkoutConfig.lastOrderId || quote.getQuoteId();
            window.location.replace('/payhub/flow/index/id/' + orderId);
        },
    });
});
