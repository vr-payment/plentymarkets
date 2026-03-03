/**
 * VR Payment PWA Integration
 * Intercepts checkout payment execution to handle redirects
 */
(function() {
    console.log('[VR Payment] PWA integration script loaded');
    
    // Wait for Vue/App to be ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[VR Payment] DOM loaded, setting up payment interceptor');
        
        // Try to intercept API responses
        if (window.fetch) {
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                return originalFetch.apply(this, args).then(response => {
                    // Clone response so we can read it
                    const clonedResponse = response.clone();
                    
                    // Check if this is the doExecutePayment call
                    if (args[0] && args[0].includes && args[0].includes('doExecutePayment')) {
                        clonedResponse.json().then(data => {
                            console.log('[VR Payment] Intercepted doExecutePayment response:', data);
                            
                            // Check for redirect
                            if (data && data.data && data.data.type === 'redirect' && data.data.value) {
                                console.log('[VR Payment] Redirect detected, redirecting to:', data.data.value);
                                // Small delay to ensure order is saved
                                setTimeout(function() {
                                    window.location.href = data.data.value;
                                }, 100);
                            }
                        }).catch(err => {
                            console.error('[VR Payment] Error parsing response:', err);
                        });
                    }
                    
                    return response;
                });
            };
            console.log('[VR Payment] Fetch interceptor installed');
        }
        
        // Backup: Check sessionStorage periodically
        setInterval(function() {
            var vrPaymentData = sessionStorage.getItem('vrpayment_redirect');
            if (vrPaymentData) {
                try {
                    var data = JSON.parse(vrPaymentData);
                    if (data.url) {
                        sessionStorage.removeItem('vrpayment_redirect');
                        console.log('[VR Payment] SessionStorage redirect found:', data.url);
                        window.location.href = data.url;
                    }
                } catch(e) {
                    console.error('[VR Payment] Error with sessionStorage redirect:', e);
                }
            }
        }, 500);
    });
})();
