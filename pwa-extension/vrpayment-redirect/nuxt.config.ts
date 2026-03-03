export default defineNuxtConfig({
  app: {
    head: {
      script: [
        {
          children: `
            console.log('[VR Payment] Early script injection');
            // Early interceptor in case the plugin loads late
            if (typeof window !== 'undefined' && window.fetch && !window.__vrpayment_interceptor_installed) {
              window.__vrpayment_interceptor_installed = true;
              const originalFetch = window.fetch;
              
              window.fetch = function(...args) {
                return originalFetch.apply(this, args).then(async (response) => {
                  const clonedResponse = response.clone();
                  
                  try {
                    const url = args[0]?.toString() || '';
                    if (url.includes('doExecutePayment')) {
                      const data = await clonedResponse.json();
                      console.log('[VR Payment] Early interceptor - doExecutePayment response:', data);
                      
                      if (data?.data?.type === 'redirect' && data?.data?.value) {
                        console.log('[VR Payment] Redirecting to:', data.data.value);
                        setTimeout(() => { window.location.href = data.data.value; }, 100);
                      }
                    }
                  } catch (err) {
                    // Ignore parse errors
                  }
                  
                  return response;
                });
              };
            }
          `,
          type: 'text/javascript'
        }
      ]
    }
  }
});
