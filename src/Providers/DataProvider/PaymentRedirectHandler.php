<?php
namespace VRPayment\Providers\DataProvider;

use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;

class PaymentRedirectHandler
{
    use Loggable;

    public function call(Twig $twig, $arg): string
    {
        // Inject JavaScript that intercepts PWA's doExecutePayment response
        // This handles the redirect that PWA receives but doesn't process
        return '<script>
(function() {
    console.log("[VR Payment] PWA integration script loaded");
    
    // Intercept fetch calls to catch doExecutePayment response
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            return originalFetch.apply(this, args).then(response => {
                const clonedResponse = response.clone();
                
                // Check if this is doExecutePayment
                if (args[0] && args[0].toString().includes("doExecutePayment")) {
                    clonedResponse.json().then(data => {
                        console.log("[VR Payment] Intercepted doExecutePayment:", data);
                        
                        // Check for redirect in response
                        if (data && data.data && data.data.type === "redirect" && data.data.value) {
                            console.log("[VR Payment] Redirecting to:", data.data.value);
                            setTimeout(function() {
                                window.location.href = data.data.value;
                            }, 100);
                        }
                    }).catch(function(err) {
                        console.error("[VR Payment] Error parsing response:", err);
                    });
                }
                
                return response;
            });
        };
        console.log("[VR Payment] Fetch interceptor installed successfully");
    }
})();
</script>';
    }
}
