/**
 * VR Payment Client Plugin
 * Intercepts doExecutePayment to handle redirects
 */
export default defineNuxtPlugin((nuxtApp) => {
  console.log('[VR Payment] Plugin loaded');
  
  // Intercept fetch globally to catch doExecutePayment responses
  if (typeof window !== 'undefined' && window.fetch) {
    const originalFetch = window.fetch;
    
    window.fetch = function(...args: any[]) {
      return originalFetch.apply(this, args).then(async (response) => {
        // Clone response so we can read it
        const clonedResponse = response.clone();
        
        try {
          // Check if this is the doExecutePayment endpoint
          const url = args[0]?.toString() || '';
          if (url.includes('doExecutePayment') || url.includes('payment/execute')) {
            const data = await clonedResponse.json();
            console.log('[VR Payment] Intercepted payment response:', data);
            
            // Check for VR Payment redirect
            if (data?.data?.type === 'redirect' && data?.data?.value) {
              console.log('[VR Payment] Redirect found, redirecting to:', data.data.value);
              
              // Delay slightly to ensure order is saved
              setTimeout(() => {
                window.location.href = data.data.value;
              }, 100);
            }
          }
        } catch (err) {
          // Silently fail if we can't parse response
          console.debug('[VR Payment] Could not parse response (might not be JSON)');
        }
        
        return response;
      });
    };
    
    console.log('[VR Payment] Fetch interceptor installed');
  }
});
