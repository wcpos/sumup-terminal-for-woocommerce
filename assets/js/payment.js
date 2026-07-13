/**
 * SumUp Terminal Payment Interface
 * Handles payment interactions on checkout pages.
 */

(function($) {
    'use strict';

    // Initialize the payment interface
    const sumupPayment = {
        activePolls: {},
        
        /**
         * Initialize payment handlers
         */
        init: function() {
            this.bindEvents();
            this.setAjaxUrl();
            this.restoreLogs();
            $(document.body).on('updated_checkout', this.restoreLogs.bind(this));
        },

        /**
         * Set the AJAX URL
         */
        setAjaxUrl: function() {
            if (typeof sumupPaymentData !== 'undefined' && sumupPaymentData.ajaxUrl) {
                window.ajaxurl = sumupPaymentData.ajaxUrl;
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Use event delegation for dynamic content
            $(document).on('click', '.sumup-checkout-btn', this.startPayment.bind(this));
            $(document).on('click', '.sumup-check-status-btn', this.checkPaymentStatus.bind(this));
            $(document).on('click', '.sumup-cancel-btn', this.cancelPayment.bind(this));
            $(document).on('click', '.sumup-toggle-log', this.toggleLogs.bind(this));
            $(document).on('click', '.sumup-copy-log', this.copyLogs.bind(this));
            $(document).on('click', '.sumup-clear-log', this.clearLogs.bind(this));
        },

        appendLog: function(level, message) {
            const textarea = $('.sumup-payment-log-textarea');
            if (!textarea.length) return;
            const line = '[' + new Date().toLocaleTimeString() + '] [' + level.toUpperCase() + '] ' + String(message);
            const lines = (textarea.val() ? textarea.val().split('\n') : []).concat(line).slice(-50);
            textarea.val(lines.join('\n')).scrollTop(textarea[0].scrollHeight);
            try { sessionStorage.setItem('sumup-payment-log-' + ($('#sumup-terminal-payment-interface').data('order-id') || 'unknown'), textarea.val()); } catch (error) {}
        },
        restoreLogs: function() {
            const textarea = $('.sumup-payment-log-textarea');
            if (!textarea.length) return;
            try { textarea.val(sessionStorage.getItem('sumup-payment-log-' + ($('#sumup-terminal-payment-interface').data('order-id') || 'unknown')) || ''); } catch (error) {}
            this.appendLog('info', sumupPaymentData.strings.panelReady);
        },
        toggleLogs: function(event) {
            const button = $(event.currentTarget);
            const expanded = button.attr('aria-expanded') === 'true';
            button.attr('aria-expanded', expanded ? 'false' : 'true').text(expanded ? sumupPaymentData.strings.logsHidden : sumupPaymentData.strings.logsShown);
            $('.sumup-log-content').prop('hidden', expanded);
        },
        copyLogs: function() {
            const textarea = $('.sumup-payment-log-textarea');
            if (!textarea.length) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textarea.val()).then(() => this.appendLog('success', sumupPaymentData.strings.logsCopied)).catch(() => this.appendLog('warning', sumupPaymentData.strings.logsCopyFailed));
            } else {
                textarea.trigger('focus').trigger('select');
                this.appendLog('warning', sumupPaymentData.strings.logsCopyFailed);
            }
        },

        clearLogs: function() {
            $('.sumup-payment-log-textarea').val('');
            try { sessionStorage.removeItem('sumup-payment-log-' + ($('#sumup-terminal-payment-interface').data('order-id') || 'unknown')); } catch (error) {}
            this.appendLog('info', sumupPaymentData.strings.logCleared);
        },
        /**
         * Start payment on selected reader
         */
        startPayment: function(event) {
            event.preventDefault();
            
            const button = $(event.currentTarget);
            const readerId = button.data('reader-id');
            const orderId = button.data('order-id');
            const orderKey = $('#sumup-terminal-payment-interface').attr('data-order-key') || '';
            const statusDiv = $('#payment-status-' + readerId);
            const cancelBtn = button.siblings('.sumup-cancel-btn');
            const statusBtn = button.siblings('.sumup-check-status-btn');

            if (!readerId || !orderId) {
                this.showError(statusDiv, 'Missing reader ID or order ID');
                return;
            }

            // Update UI
            $('.sumup-checkout-btn').prop('disabled', true);
            $('.sumup-check-status-btn').prop('disabled', true);
            button.text(sumupPaymentData.strings.startingPayment);
            this.setStatus(statusDiv, sumupPaymentData.strings.startingPayment, 'message');
            this.appendLog('info', sumupPaymentData.strings.startingPayment);

            // Make AJAX request
            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sumup_create_checkout',
                    nonce: sumupPaymentData.nonce,
                    reader_id: readerId,
                    order_id: orderId,
                    order_key: orderKey
                },
                success: (response) => {
                    if (response.success) {
                        // Payment started successfully
                        button.text(sumupPaymentData.strings.paymentStarted).prop('disabled', true);
                        statusBtn.prop('disabled', false);
                        cancelBtn.prop('hidden', false);
                        this.setStatus(statusDiv, response.data.message, 'success');
                        this.appendLog('success', response.data.message);
                        
                        // You might want to poll for payment status here
                        this.pollPaymentStatus(readerId, orderId, orderKey, statusDiv, button, cancelBtn);
                    } else {
                        this.handlePaymentError(response.data, statusDiv, button, cancelBtn);
                    }
                },
                error: (xhr) => {
                    statusBtn.prop('disabled', false);
                    this.handleAjaxError(xhr, statusDiv, button, cancelBtn);
                }
            });
        },

        checkPaymentStatus: function(event) {
            event.preventDefault();
            const button = $(event.currentTarget);
            const readerId = button.data('reader-id');
            const orderId = button.data('order-id');
            const activePoll = this.activePolls[readerId];
            if (activePoll && activePoll.requestPending) {
                this.appendLog('info', sumupPaymentData.strings.statusAlreadyChecking);
                return;
            }
            const pollData = activePoll || {
                readerId: readerId,
                orderId: orderId,
                orderKey: $('#sumup-terminal-payment-interface').attr('data-order-key') || '',
                statusDiv: $('#payment-status-' + readerId),
                startBtn: button.siblings('.sumup-checkout-btn'),
                cancelBtn: button.siblings('.sumup-cancel-btn'),
                active: true,
                manualOnly: true
            };
            if (!readerId || !orderId) {
                this.showError(pollData.statusDiv, 'Missing reader ID or order ID');
                return;
            }
            if (!activePoll) this.activePolls[readerId] = pollData;
            pollData.manualCheck = true;
            button.prop('disabled', true);
            this.setStatus(pollData.statusDiv, sumupPaymentData.strings.checkingStatus, 'message');
            this.requestPaymentStatus(pollData).always(() => {
                button.prop('disabled', false);
                pollData.manualCheck = false;
                if (pollData.manualOnly && pollData.active && this.activePolls[readerId] === pollData) {
                    delete this.activePolls[readerId];
                }
            });
        },
        /**
         * Cancel payment on selected reader
         */
        cancelPayment: function(event) {
            event.preventDefault();
            
            const button = $(event.currentTarget);
            const readerId = button.data('reader-id');
            const orderId = button.data('order-id');
            const statusDiv = $('#payment-status-' + readerId);
            const startBtn = button.siblings('.sumup-checkout-btn');
            const pollData = this.activePolls[readerId] || {
                readerId: readerId,
                orderId: orderId,
                orderKey: $('#sumup-terminal-payment-interface').attr('data-order-key') || '',
                statusDiv: statusDiv,
                startBtn: startBtn,
                cancelBtn: button,
                pollCount: 0,
                maxPolls: 150,
                active: true
            };

            if (!readerId) {
                this.showError(statusDiv, 'Missing reader ID');
                return;
            }

            // Confirm cancellation
            if (!confirm(sumupPaymentData.strings.cancelConfirm)) {
                return;
            }

            this.activePolls[readerId] = pollData;
            pollData.cancelRequested = true;
            this.requestCancellation(pollData);
        },

        requestCancellation: function(pollData) {
            if (pollData.cancellationPending || pollData.active === false) return;
            pollData.cancellationPending = true;
            pollData.cancelBtn.prop('disabled', true);
            this.setStatus(pollData.statusDiv, sumupPaymentData.strings.cancellingPayment, 'message');
            this.appendLog('info', sumupPaymentData.strings.cancellingPayment);
            return $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sumup_cancel_checkout',
                    nonce: sumupPaymentData.nonce,
                    reader_id: pollData.readerId,
                    order_id: pollData.orderId,
                    order_key: pollData.orderKey
                },
                success: (response) => {
                    pollData.cancellationPending = false;
                    if (pollData.active === false) return;
                    if (response.success) {
                        pollData.cancelMessage = response.data.message;
                        pollData.pollCount = 0;
                        pollData.cancelBtn.prop('hidden', true).prop('disabled', false);
                        pollData.startBtn.siblings('.sumup-check-status-btn').prop('disabled', false);
                        this.setStatus(pollData.statusDiv, response.data.message, 'cancelled');
                        this.appendLog('info', response.data.message);
                        if (!pollData.intervalId) {
                            pollData.pollCount = 0;
                            this.startPolling(pollData);
                        }
                    } else {
                        pollData.cancelRequested = false;
                        this.showError(pollData.statusDiv, response.data);
                        pollData.cancelBtn.prop('disabled', false);
                        this.resumePollingAfterCancellationFailure(pollData);
                    }
                },
                error: (xhr) => {
                    pollData.cancellationPending = false;
                    if (pollData.active === false) return;
                    pollData.cancelRequested = false;
                    pollData.cancelBtn.prop('disabled', false);
                    this.showError(pollData.statusDiv, this.ajaxErrorMessage(xhr));
                    this.resumePollingAfterCancellationFailure(pollData);
                }
            });
        },

        resumePollingAfterCancellationFailure: function(pollData) {
            if (pollData.active && !pollData.intervalId) {
                pollData.pollCount = 0;
                this.startPolling(pollData);
            }
        },
        /**
         * Poll payment status by checking order meta data
         */
        pollPaymentStatus: function(readerId, orderId, orderKey, statusDiv, startBtn, cancelBtn) {
            this.stopAllPollingForReader(readerId);
            // Store polling state
            const pollData = {
                readerId: readerId,
                orderId: orderId,
                orderKey: orderKey,
                statusDiv: statusDiv,
                startBtn: startBtn,
                cancelBtn: cancelBtn,
                pollCount: 0,
                maxPolls: 150, // 5 minutes at 2-second intervals
                intervalId: null,
                active: true
            };
            
            // Initial status message
            this.setStatus(statusDiv, sumupPaymentData.strings.followReader, 'info');
            this.appendLog('info', sumupPaymentData.strings.followReader);
            this.activePolls[readerId] = pollData;
            
            // Start polling
            this.startPolling(pollData);
        },

        /**
         * Start the polling process
         */
        startPolling: function(pollData) {
            const self = this;
            
            pollData.intervalId = setInterval(() => {
                pollData.pollCount++;
                
                // Check if we've exceeded max polling attempts
                if (pollData.pollCount > pollData.maxPolls) {
                    clearInterval(pollData.intervalId);
                    pollData.intervalId = null;
                    pollData.timeoutPending = true;
                    if (!pollData.requestPending) {
                        pollData.timeoutFinalCheckRunning = true;
                        self.requestPaymentStatus(pollData);
                    }
                    return;
                }

                self.requestPaymentStatus(pollData);
            }, 2000);
        },

        requestPaymentStatus: function(pollData) {
            if (pollData.requestPending) return $.Deferred().resolve().promise();
            pollData.requestPending = true;
            const request = $.ajax({
                    url: window.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sumup_check_payment_status',
                        order_id: pollData.orderId,
                        order_key: pollData.orderKey,
                        force_transaction_check: Boolean(pollData.timeoutFinalCheckRunning)
                    },
                    success: (response) => {
                        if (pollData.active === false) return;
                        if (response.success) {
                            this.handlePaymentStatusResponse(pollData, response.data);
                        } else {
                            const message = response.data || sumupPaymentData.strings.networkError;
                            this.appendLog('warning', message);
                            if (pollData.manualOnly || pollData.manualCheck) this.showError(pollData.statusDiv, message);
                        }
                    },
                    error: () => {
                        this.appendLog('warning', sumupPaymentData.strings.networkError);
                        if (pollData.manualOnly || pollData.manualCheck) this.showError(pollData.statusDiv, sumupPaymentData.strings.networkError);
                    }
                });
            request.always(() => {
                pollData.requestPending = false;
                if (pollData.timeoutPending && pollData.active) {
                    if (pollData.timeoutFinalCheckRunning) {
                        pollData.timeoutPending = false;
                        pollData.timeoutFinalCheckRunning = false;
                        this.handlePollingTimeout(pollData);
                    } else {
                        pollData.timeoutFinalCheckRunning = true;
                        this.requestPaymentStatus(pollData);
                    }
                }
            });
            return request;
        },

        handlePollingTimeout: function(pollData) {
            if (pollData.cancelRequested) {
                this.stopPolling(pollData);
                this.setStatus(pollData.statusDiv, sumupPaymentData.strings.cancellationPendingTimeout, 'timeout');
                this.appendLog('warning', sumupPaymentData.strings.cancellationPendingTimeout);
                return;
            }
            this.setStatus(pollData.statusDiv, sumupPaymentData.strings.pollTimedOut, 'timeout');
            this.appendLog('warning', sumupPaymentData.strings.pollTimedOut);
            pollData.cancelRequested = true;
            this.requestCancellation(pollData);
        },
        /**
         * Handle payment status response
         */
        handlePaymentStatusResponse: function(pollData, data) {
            const { status, message, continue_polling, submit_form, reader_status } = data;
            const readerMessage = this.formatReaderStatus(reader_status);
            const displayMessage = pollData.cancelRequested && continue_polling
                ? (pollData.cancelMessage || sumupPaymentData.strings.cancellingPayment)
                : (readerMessage && continue_polling ? readerMessage : message);
            this.setStatus(pollData.statusDiv, displayMessage, status.toLowerCase());
            if (pollData.lastMessage !== displayMessage) {
                this.appendLog(status === 'PAID' ? 'success' : 'info', displayMessage);
                pollData.lastMessage = displayMessage;
            }
            
            // If we shouldn't continue polling, stop
            if (!continue_polling) {
                this.stopAllPollingForReader(pollData.readerId);
                
                if (submit_form && status === 'PAID') {
                    // Payment successful - submit the checkout form
                    this.handleSuccessfulPayment(pollData);
                } else {
                    // Payment failed/cancelled - reset interface
                    this.resetPaymentInterface(pollData.startBtn, pollData.cancelBtn, pollData.statusDiv);
                }
            }
        },

        formatReaderStatus: function(readerStatus) {
            const states = {
                IDLE: sumupPaymentData.strings.readerReady,
                SELECTING_TIP: sumupPaymentData.strings.readerSelectingTip,
                WAITING_FOR_CARD: sumupPaymentData.strings.readerWaitingForCard,
                WAITING_FOR_PIN: sumupPaymentData.strings.readerWaitingForPin,
                WAITING_FOR_SIGNATURE: sumupPaymentData.strings.readerWaitingForSignature,
                UPDATING_FIRMWARE: sumupPaymentData.strings.readerUpdatingFirmware
            };
            if (!readerStatus || (!readerStatus.state && !readerStatus.status)) return '';
            if (readerStatus.status === 'OFFLINE') return sumupPaymentData.strings.readerOffline;
            return states[readerStatus.state] || sumupPaymentData.strings.readerStatus + ' ' + (readerStatus.state || readerStatus.status);
        },
        /**
         * Handle successful payment
         */
        handleSuccessfulPayment: function(pollData) {
            // Try to click the place order button first (WooCommerce order-pay page)
            const placeOrderBtn = $('#place_order');
            if (placeOrderBtn.length > 0) {
                placeOrderBtn.click();
                return;
            }
            
            // Try to submit the order review form (WooCommerce order-pay page)
            const orderReviewForm = $('#order_review');
            if (orderReviewForm.length > 0) {
                orderReviewForm.submit();
                return;
            }
            
            // Fallback: try standard checkout form
            const checkoutForm = $('form.checkout, form[name="checkout"]');
            if (checkoutForm.length > 0) {
                checkoutForm.submit();
                return;
            }
            
            this.setStatus(pollData.statusDiv, sumupPaymentData.strings.paymentSuccess, 'success');
        },

        /**
         * Stop polling
         */
        stopPolling: function(pollData) {
            pollData.active = false;
            if (pollData.intervalId) {
                clearInterval(pollData.intervalId);
                pollData.intervalId = null;
            }
            if (this.activePolls[pollData.readerId] === pollData) delete this.activePolls[pollData.readerId];
        },

        /**
         * Reset payment interface to initial state
         */
        resetPaymentInterface: function(startBtn, cancelBtn, statusDiv) {
            $('.sumup-checkout-btn').prop('disabled', false).text(sumupPaymentData.strings.startPayment);
            $('.sumup-check-status-btn').prop('disabled', false);
            cancelBtn.prop('hidden', true).prop('disabled', false);
        },

        /**
         * Handle payment errors
         */
        handlePaymentError: function(error, statusDiv, startBtn, cancelBtn) {
            this.resetPaymentInterface(startBtn, cancelBtn, statusDiv);
            this.showError(statusDiv, error);
        },

        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, statusDiv, startBtn, cancelBtn) {
            const errorMessage = this.ajaxErrorMessage(xhr);
            if (cancelBtn) {
                this.resetPaymentInterface(startBtn, cancelBtn, statusDiv);
            } else {
                startBtn.prop('disabled', false).text(sumupPaymentData.strings.startPayment);
            }
            this.showError(statusDiv, errorMessage);
        },

        ajaxErrorMessage: function(xhr) {
            let errorMessage = sumupPaymentData.strings.networkError;
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data;
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    // Keep default error message
                }
            }
            return errorMessage;
        },

        /**
         * Show error message
         */
        showError: function(statusDiv, message) {
            const errorMessage = sumupPaymentData.strings.paymentFailed + ' ' + message;
            this.setStatus(statusDiv, errorMessage, 'error');
            this.appendLog('error', errorMessage);
        },

        setStatus: function(statusDiv, message, level) {
            statusDiv.empty().append($('<div>').addClass('sumup-status-' + level).text(message || ''));
        },

        /**
         * Stop all polling for a specific reader (utility function)
         */
        stopAllPollingForReader: function(readerId) {
            if (this.activePolls[readerId]) this.stopPolling(this.activePolls[readerId]);
        }
    };

    if (typeof module === 'object' && module.exports) module.exports = sumupPayment;

    // Initialize when document is ready
    $(document).ready(function() {
        sumupPayment.init();
    });

})(jQuery);
