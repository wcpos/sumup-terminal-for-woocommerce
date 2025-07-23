/**
 * SumUp Terminal Admin JavaScript
 */

// Make sure the functions are available globally
window.sumupAdmin = {
    
    init: function() {
        console.log('SumUp Admin JS initialized');
        
        // Make sure ajaxurl is available
        if (typeof ajaxurl === 'undefined') {
            window.ajaxurl = sumupAdminData.ajaxUrl;
        }
        
        // Test that everything is working
        console.log('AJAX URL:', window.ajaxurl);
        console.log('Nonce:', sumupAdminData.nonce);
    },
    
    testJS: function() {
        alert('JavaScript is working! AJAX URL: ' + window.ajaxurl);
        console.log('Test JS function called successfully');
    },
    
    pairReader: function() {
        console.log('sumupPairReader called');
        const pairingCode = document.getElementById('sumup-pairing-code');
        const resultDiv = document.getElementById('sumup-pair-result');
        
        if (!pairingCode || !resultDiv) {
            console.error('Required elements not found');
            return;
        }
        
        const code = pairingCode.value.trim().toUpperCase();
        
        if (!code) {
            resultDiv.innerHTML = '<div style="color: #d63638; margin-top: 10px;">Please enter a pairing code.</div>';
            return;
        }
        
        resultDiv.innerHTML = '<div style="color: #0073aa; margin-top: 10px;">Pairing reader...</div>';
        console.log('Making AJAX request to:', window.ajaxurl);
        
        fetch(window.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'sumup_pair_reader',
                pairing_code: code,
                nonce: sumupAdminData.nonce
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                resultDiv.innerHTML = '<div style="color: #00a32a; margin-top: 10px;">✓ Reader paired successfully! Refreshing page...</div>';
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = '<div style="color: #d63638; margin-top: 10px;">✗ ' + (data.data || 'Pairing failed') + '</div>';
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            resultDiv.innerHTML = '<div style="color: #d63638; margin-top: 10px;">✗ Network error: ' + error.message + '</div>';
        });
    },
    
    unpairReader: function(readerId) {
        console.log('sumupUnpairReader called with ID:', readerId);
        
        if (!confirm(sumupAdminData.strings.confirmUnpair)) {
            return;
        }
        
        fetch(window.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'sumup_unpair_reader',
                reader_id: readerId,
                nonce: sumupAdminData.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(sumupAdminData.strings.unpairSuccess);
                location.reload();
            } else {
                alert(sumupAdminData.strings.unpairFailed + ' ' + (data.data || sumupAdminData.strings.unknownError));
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            alert(sumupAdminData.strings.networkError);
        });
    },
    

};

// Event delegation for button clicks
function setupEventListeners() {
    // Use event delegation to handle dynamically added buttons
    document.addEventListener('click', function(event) {
        // Check if the clicked element has the sumup-btn class
        if (event.target.classList.contains('sumup-btn')) {
            event.preventDefault();
            
            const action = event.target.getAttribute('data-action');
            console.log('Button clicked with action:', action);
            
            switch (action) {
                case 'test-js':
                    window.sumupAdmin.testJS();
                    break;
                case 'pair-reader':
                    window.sumupAdmin.pairReader();
                    break;
                case 'unpair-reader':
                    const readerId = event.target.getAttribute('data-reader-id');
                    if (readerId) {
                        window.sumupAdmin.unpairReader(readerId);
                    }
                    break;
                default:
                    console.warn('Unknown action:', action);
            }
        }
    });
}

// Initialize when DOM is ready
function initSumupAdmin() {
    window.sumupAdmin.init();
    setupEventListeners();
}

// Initialize based on DOM state
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSumupAdmin);
} else {
    initSumupAdmin();
}

// Legacy global functions for backward compatibility (if needed)
window.sumupTestJS = function() {
    window.sumupAdmin.testJS();
};

window.sumupPairReader = function() {
    window.sumupAdmin.pairReader();
};

window.sumupUnpairReader = function(readerId) {
    window.sumupAdmin.unpairReader(readerId);
};

 