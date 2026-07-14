const test = require('node:test');
const assert = require('node:assert/strict');

test('runs a fresh status request after an in-flight request at timeout', () => {
    const requests = [];
    global.document = {};
    global.window = {};
    global.jQuery = function(element) {
        return global.jQuery.select(element);
    };
    global.jQuery.select = function(element) {
        return {
            on: function() {},
            ready: function() {},
            val: function() { return element && element.value; }
        };
    };
    global.jQuery.ajax = function(options) {
        const alwaysCallbacks = [];
        const request = {
            options: options,
            always: function(callback) {
                alwaysCallbacks.push(callback);
                return request;
            },
            complete: function(response) {
                options.success(response);
                alwaysCallbacks.forEach(function(callback) { callback(); });
            }
        };
        requests.push(request);
        return request;
    };

    const payment = require('../assets/js/payment.js');
    let timeoutCalls = 0;
    payment.appendLog = function() {};
    payment.handlePaymentStatusResponse = function() {};
    payment.handlePollingTimeout = function() { timeoutCalls++; };

    const pollData = {
        active: true,
        requestPending: false,
        timeoutPending: false,
        orderId: 123,
        orderKey: 'wc_order_key'
    };

    payment.requestPaymentStatus(pollData);
    assert.equal(requests[0].options.data.force_transaction_check, false, 'ordinary polls should remain throttled');
    pollData.timeoutPending = true;
    requests[0].complete({ success: true, data: {} });

    assert.equal(requests.length, 2, 'a fresh final status request should be queued');
    assert.equal(requests[1].options.data.force_transaction_check, true, 'the final timeout check should bypass throttling');
    assert.equal(timeoutCalls, 0, 'cancellation must wait for the fresh status response');

    requests[1].complete({ success: true, data: {} });
    assert.equal(timeoutCalls, 1, 'timeout handling runs after the fresh status response');
});

test('loads missing readers when SumUp is selected after another gateway', () => {
    const payment = require('../assets/js/payment.js');
    const loadReaders = payment.loadReaders;
    let loadCalls = 0;

    assert.equal(
        typeof payment.handlePaymentMethodChange,
        'function',
        'the payment interface should react when a gateway is opened'
    );

    payment.loadReaders = function() { loadCalls++; };
    payment.handlePaymentMethodChange({ currentTarget: { value: 'pos_cash' } });
    assert.equal(loadCalls, 0, 'opening another gateway must not load SumUp readers');

    payment.handlePaymentMethodChange({
        currentTarget: { value: 'sumup_terminal_for_woocommerce' }
    });
    assert.equal(loadCalls, 1, 'opening SumUp must load its readers');

    payment.loadReaders = loadReaders;
});

test('reloads an empty SumUp panel from the current payment page', () => {
    const payment = require('../assets/js/payment.js');
    const select = global.jQuery.select;
    const loads = [];
    const emptyResult = {
        length: 0,
        first: function() { return emptyResult; },
        find: function() { return emptyResult; }
    };
    const paymentBox = {
        length: 1,
        find: function() { return emptyResult; },
        load: function(url, callback) {
            loads.push(url);
            callback('', 'success');
        }
    };
    global.jQuery.select = function(selector) {
        if (selector === '.sumup-reader-card') return emptyResult;
        if (selector === '.payment_box.payment_method_sumup_terminal_for_woocommerce') return paymentBox;
        if (selector === '.sumup-payment-log-textarea') return emptyResult;
        return emptyResult;
    };
    global.window.location = { href: 'https://example.test/wcpos-checkout/order-pay/123/?key=wc_order_key' };

    payment.readerRequest = null;
    payment.loadReaders();

    assert.deepEqual(loads, [
        'https://example.test/wcpos-checkout/order-pay/123/?key=wc_order_key .payment_box.payment_method_sumup_terminal_for_woocommerce'
    ]);
    assert.equal(payment.readerRequest, null, 'the panel can be retried after loading finishes');

    global.jQuery.select = select;
});

test('leaves an existing SumUp reader selection untouched', (t) => {
    const payment = require('../assets/js/payment.js'), select = global.jQuery.select;
    const readerRequest = payment.readerRequest;
    let loadCalls = 0;
    const paymentBox = {
        length: 1,
        find: function(selector) { return { length: selector === '.sumup-reader-card' ? 1 : 0 }; },
        load: function() { loadCalls++; }
    };
    t.after(function() { global.jQuery.select = select; payment.readerRequest = readerRequest; });
    global.jQuery.select = function() { return paymentBox; };
    payment.readerRequest = null;
    payment.loadReaders();
    assert.equal(loadCalls, 0, 'an existing reader selection must not be replaced');
    assert.equal(payment.readerRequest, null, 'no reader request should start');
});

test('renders the network error when reader loading fails', (t) => {
    const payment = require('../assets/js/payment.js'), select = global.jQuery.select;
    const paymentData = global.sumupPaymentData;
    const paymentState = [payment.readerRequest, payment.readerRetryPending];
    let renderedMessage = '';
    const emptyResult = { length: 0, first: function() { return this; } };
    const errorResult = {
        length: 1,
        first: function() { return this; },
        find: function() { return this; },
        text: function(message) { renderedMessage = message; }
    };
    const paymentBox = {
        length: 1,
        find: function(selector) { return selector === '.woocommerce-error' ? errorResult : emptyResult; },
        load: function(_url, callback) { callback('', 'error'); }
    };
    t.after(function() {
        global.jQuery.select = select;
        if (paymentData === undefined) delete global.sumupPaymentData;
        else global.sumupPaymentData = paymentData;
        [payment.readerRequest, payment.readerRetryPending] = paymentState;
    });
    global.jQuery.select = function(selector) { return selector === '.payment_box.payment_method_sumup_terminal_for_woocommerce' ? paymentBox : emptyResult; };
    global.sumupPaymentData = { strings: { networkError: 'Unable to load readers.' } };
    payment.readerRequest = null;
    payment.readerRetryPending = false;
    payment.loadReaders();
    assert.equal(renderedMessage, 'Unable to load readers.');
    assert.equal(payment.readerRequest, null, 'the panel can be retried after a failed load');
});

test('retries reader loading after checkout refreshes during a request', () => {
    const payment = require('../assets/js/payment.js');
    const select = global.jQuery.select;
    const callbacks = [];
    let currentPaymentBox;
    let refreshedLoads = 0;
    const emptyResult = {
        length: 0,
        first: function() { return emptyResult; },
        find: function() { return emptyResult; }
    };
    const initialPaymentBox = {
        length: 1,
        find: function() { return emptyResult; },
        load: function(_url, callback) { callbacks.push(callback); }
    };
    const refreshedPaymentBox = {
        length: 1,
        find: function() { return emptyResult; },
        load: function(_url, callback) {
            refreshedLoads++;
            callback('', 'success');
        }
    };
    const selectedGateway = {
        length: 1,
        val: function() { return 'sumup_terminal_for_woocommerce'; }
    };
    global.jQuery.select = function(selector) {
        if (selector === 'input[name="payment_method"]:checked') return selectedGateway;
        if (selector === '.payment_box.payment_method_sumup_terminal_for_woocommerce') return currentPaymentBox;
        if (selector === '.sumup-payment-log-textarea') return emptyResult;
        return emptyResult;
    };
    global.window.location = { href: 'https://example.test/wcpos-checkout/order-pay/123/?key=wc_order_key' };

    currentPaymentBox = initialPaymentBox;
    payment.readerRequest = null;
    payment.readerRetryPending = false;
    payment.loadReaders();

    currentPaymentBox = refreshedPaymentBox;
    payment.handleCheckoutUpdated();
    callbacks[0]('', 'success');

    assert.equal(refreshedLoads, 1, 'the current payment box is retried after checkout replacement');
    assert.equal(payment.readerRequest, null, 'the queued retry completes normally');

    global.jQuery.select = select;
});
