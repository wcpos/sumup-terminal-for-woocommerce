/**
 * SumUp Terminal Payment Interface
 * Handles payment interactions on checkout pages.
 */

(function($) {
    'use strict';

    // Initialize the payment interface
    const sumupPayment = {
        
        /**
         * Initialize payment handlers
         */
        init: function() {
            this.bindEvents();
            this.setAjaxUrl();
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
            $(document).on('click', '.sumup-cancel-btn', this.cancelPayment.bind(this));
        },

        /**
         * Start payment on selected reader
         */
        startPayment: function(event) {
            event.preventDefault();
            
            const button = $(event.target);
            const readerId = button.data('reader-id');
            const orderId = button.data('order-id');
            const statusDiv = $('#payment-status-' + readerId);
            const cancelBtn = button.siblings('.sumup-cancel-btn');

            if (!readerId || !orderId) {
                this.showError(statusDiv, 'Missing reader ID or order ID');
                return;
            }

            // Update UI
            button.prop('disabled', true).text(sumupPaymentData.strings.startingPayment);
            statusDiv.html('<div class="sumup-status-message">' + sumupPaymentData.strings.startingPayment + '</div>');

            // Make AJAX request
            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sumup_create_checkout',
                    nonce: sumupPaymentData.nonce,
                    reader_id: readerId,
                    order_id: orderId
                },
                success: (response) => {
                    if (response.success) {
                        // Payment started successfully
                        button.text('Payment Started').prop('disabled', true);
                        cancelBtn.show();
                        statusDiv.html('<div class="sumup-status-success">' + response.data.message + '</div>');
                        
                        // You might want to poll for payment status here
                        this.pollPaymentStatus(readerId, orderId, statusDiv, button, cancelBtn);
                    } else {
                        this.handlePaymentError(response.data, statusDiv, button, cancelBtn);
                    }
                },
                error: (xhr) => {
                    this.handleAjaxError(xhr, statusDiv, button, cancelBtn);
                }
            });
        },

        /**
         * Cancel payment on selected reader
         */
        cancelPayment: function(event) {
            event.preventDefault();
            
            const button = $(event.target);
            const readerId = button.data('reader-id');
            const orderId = button.data('order-id');
            const statusDiv = $('#payment-status-' + readerId);
            const startBtn = button.siblings('.sumup-checkout-btn');

            if (!readerId) {
                this.showError(statusDiv, 'Missing reader ID');
                return;
            }

            // Confirm cancellation
            if (!confirm('Are you sure you want to cancel this payment?')) {
                return;
            }

            // Stop any active polling for this reader
            this.stopAllPollingForReader(readerId);

            // Update UI
            button.prop('disabled', true);
            statusDiv.html('<div class="sumup-status-message">Cancelling payment...</div>');

            // Make AJAX request
            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sumup_cancel_checkout',
                    nonce: sumupPaymentData.nonce,
                    reader_id: readerId,
                    order_id: orderId
                },
                success: (response) => {
                    if (response.success) {
                        // Payment cancelled successfully
                        this.resetPaymentInterface(startBtn, button, statusDiv);
                        statusDiv.html('<div class="sumup-status-cancelled">' + response.data.message + '</div>');
                    } else {
                        this.showError(statusDiv, response.data);
                        button.prop('disabled', false);
                    }
                },
                error: (xhr) => {
                    this.handleAjaxError(xhr, statusDiv, button, null);
                }
            });
        },

        /**
         * Poll payment status by checking order meta data
         */
        pollPaymentStatus: function(readerId, orderId, statusDiv, startBtn, cancelBtn) {
            
            // Store polling state
            const pollData = {
                readerId: readerId,
                orderId: orderId,
                statusDiv: statusDiv,
                startBtn: startBtn,
                cancelBtn: cancelBtn,
                pollCount: 0,
                maxPolls: 120, // 2 minutes at 1-second intervals
                intervalId: null
            };
            
            // Initial status message
            statusDiv.append('<div class="sumup-status-info">Follow the instructions on your card reader to complete the payment.</div>');
            
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
                    self.stopPolling(pollData);
                    self.showError(pollData.statusDiv, 'Payment polling timed out. Please check the reader and try again.');
                    self.resetPaymentInterface(pollData.startBtn, pollData.cancelBtn, pollData.statusDiv);
                    return;
                }
                
                // Make AJAX request to check payment status
                $.ajax({
                    url: window.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sumup_check_payment_status',
                        order_id: pollData.orderId
                    },
                    success: (response) => {
                        if (response.success) {
                            self.handlePaymentStatusResponse(pollData, response.data);
                        }
                    },
                    error: (xhr) => {
                        // Don't stop polling on network errors, just continue
                    }
                });
            }, 1000); // Poll every second
        },

        /**
         * Handle payment status response
         */
        handlePaymentStatusResponse: function(pollData, data) {
            const { status, message, continue_polling, submit_form } = data;
            
            // Update status message
            pollData.statusDiv.html('<div class="sumup-status-' + status.toLowerCase() + '">' + message + '</div>');
            
            // If we shouldn't continue polling, stop
            if (!continue_polling) {
                this.stopPolling(pollData);
                
                if (submit_form && status === 'PAID') {
                    // Payment successful - submit the checkout form
                    this.handleSuccessfulPayment(pollData);
                } else {
                    // Payment failed/cancelled - reset interface
                    this.resetPaymentInterface(pollData.startBtn, pollData.cancelBtn, pollData.statusDiv);
                }
            }
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
            
            // Final fallback: try to find any form on the page
            const anyForm = $('form').first();
            if (anyForm.length > 0) {
                anyForm.submit();
            } else {
                // No form found, just show success message
                pollData.statusDiv.html('<div class="sumup-status-success">Payment successful! Please refresh the page to continue.</div>');
            }
        },

        /**
         * Stop polling
         */
        stopPolling: function(pollData) {
            if (pollData.intervalId) {
                clearInterval(pollData.intervalId);
                pollData.intervalId = null;
            }
        },

        /**
         * Reset payment interface to initial state
         */
        resetPaymentInterface: function(startBtn, cancelBtn, statusDiv) {
            startBtn.prop('disabled', false).text('Start Payment');
            cancelBtn.hide();
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

            if (cancelBtn) {
                this.resetPaymentInterface(startBtn, cancelBtn, statusDiv);
            } else {
                startBtn.prop('disabled', false).text('Start Payment');
            }
            
            this.showError(statusDiv, errorMessage);
        },

        /**
         * Show error message
         */
        showError: function(statusDiv, message) {
            statusDiv.html('<div class="sumup-status-error" style="color: #d63638; padding: 5px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 3px;">' + 
                          sumupPaymentData.strings.paymentFailed + ' ' + message + '</div>');
        },

        /**
         * Stop all polling for a specific reader (utility function)
         */
        stopAllPollingForReader: function(readerId) {
            // This is a simple implementation - in a more complex scenario, 
            // you might want to track active polling instances
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        sumupPayment.init();
    });

})(jQuery); 