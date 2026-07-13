const test = require('node:test');
const assert = require('node:assert/strict');

test('runs a fresh status request after an in-flight request at timeout', () => {
    const requests = [];
    global.document = {};
    global.window = {};
    global.jQuery = function() {
        return {
            on: function() {},
            ready: function() {}
        };
    };
    global.jQuery.ajax = function(options) {
        const alwaysCallbacks = [];
        const request = {
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
    pollData.timeoutPending = true;
    requests[0].complete({ success: true, data: {} });

    assert.equal(requests.length, 2, 'a fresh final status request should be queued');
    assert.equal(timeoutCalls, 0, 'cancellation must wait for the fresh status response');

    requests[1].complete({ success: true, data: {} });
    assert.equal(timeoutCalls, 1, 'timeout handling runs after the fresh status response');
});
