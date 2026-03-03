/**
 * VR Payment PWA Redirect Handler
 * Intercepts checkout execution to handle payment redirects
 */
export const useVRPaymentRedirect = () => {
  const { data, execute: originalExecute } = useSdk();

  /**
   * Wrapper around checkout execution that handles VR Payment redirects
   */
  const executePaymentWithRedirect = async (params: any) => {
    console.log('[VR Payment] Intercepting payment execution');
    
    try {
      // Call the original execute payment
      const result = await originalExecute(params);
      
      console.log('[VR Payment] Payment execution result:', result);
      
      // Check if this is a VR Payment redirect
      if (result?.data?.type === 'redirect' && result?.data?.value) {
        console.log('[VR Payment] Redirect detected, redirecting to:', result.data.value);
        
        // Give a small delay to ensure any state updates complete
        setTimeout(() => {
          window.location.href = result.data.value;
        }, 100);
        
        return result;
      }
      
      return result;
      
    } catch (error) {
      console.error('[VR Payment] Error during payment execution:', error);
      throw error;
    }
  };

  return {
    executePaymentWithRedirect
  };
};
