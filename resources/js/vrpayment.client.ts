/**
 * vRPayment Client Plugin
 * Intercepts doExecutePayment to handle payment redirects
 * 
 * This file goes to "apps/web/app/plugins"
 */

console.log('[vrpayment] PLUGIN LOADED');

export default defineNuxtPlugin(() => {
  
  // Only run on client side
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);
  console.log('[vrpayment]: url=', url);

  if (url.pathname.endsWith('/checkout') && url.searchParams.get('vRPayment_failed') === '1') {
    console.log('[vrpayment]: url.pathname.endsWith(/checkout) && url.searchParams.get(vRPayment_failed) === 1');
    const orderId = url.searchParams.get('orderId');
    if (orderId) {
      try {
        vRPaymentRestoreCart(orderId);
        console.log('[vrpayment] basket restored');
      } catch (err) {
        console.error('[vrpayment] basket restore failed: ', err);
      }
    } else {
      console.warn('[vrpayment] OrderId is not present:');
    }
    try {
      const { send } = (window as any).$nuxt?.$nuxt?.useNotification?.() ?? {};
      if (send) {
        send({ type: 'negative', message: 'Your payment could not be completed. Please try again.' });
      } else {
        showFallbackBanner();
      }
    } catch(err) {
      showFallbackBanner();
    }
    url.searchParams.delete('vRPayment_failed');
    url.searchParams.delete('orderId');
    url.searchParams.delete('transactionId');
    window.history.replaceState({}, '', url.toString());
  }

  function showFallbackBanner() {
    const banner = document.createElement('div');
    banner.style.cssText =
      'position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#fee;border:1px solid #f99;padding:12px 20px;border-radius:6px;z-index:99999;color:#900;';
    banner.textContent = 'Your payment could not be completed. Please try again.';
    document.body.appendChild(banner);
    setTimeout(() => banner.remove(), 6000);
  }

  function redirect(url: string) {
    console.log('[vrpayment]: redirect url: ', url);
    sessionStorage.setItem('vRPayment_pending_redirect', url);
    localStorage.setItem('vRPayment_pending_redirect', url);
    if ((window as any).__vRPayment_should_redirect) {
      return;
    }
    (window as any).__vRPayment_should_redirect = true;

    // Create overlay to prevent interaction
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.9);z-index:999999;display:flex;align-items:center;justify-content:center;font-size:24px;';
    overlay.innerHTML = '<div>Redirecting to payment page...</div>';
    document.body.appendChild(overlay);

    // Immediate synchronous redirect
    window.location.href = url;

    // Early return test
    return;

    // If that didn't work, try other methods in rapid succession
    window.location.replace(url);
    window.location.assign(url);
    (window as any).location = url;
                    
    // Prevent any code from continuing
    throw new Error('vrpayment redirect initiated');
  }

  async function vRPaymentRegisterReturnContext(originUrl: string, lang: string) {
    const sdk = useSdk() as any;
    await sdk.plentysystems.vRPaymentRegisterReturnContext({
      originUrl: originUrl,
      lang: lang,
    });
  }

  async function vRPaymentRestoreCart(orderId: string) {
    const sdk = useSdk() as any;
    await sdk.plentysystems.vRPaymentRestoreCart({
      orderId: orderId
    });
  }
  
  // Intercept XMLHttpRequest as well (in case PWA uses axios)
  if (window.XMLHttpRequest) {
    const originalXHROpen = XMLHttpRequest.prototype.open;
    const originalXHRSend = XMLHttpRequest.prototype.send;
    
    XMLHttpRequest.prototype.open = function(this: XMLHttpRequest, method: string, url: string | URL, ...rest: any[]) {
      (this as any).__vRPayment_url = url.toString();
      console.log('[vrpayment]: XMLHttpRequest=', url);
      return originalXHROpen.apply(this, [method, url, ...rest] as any);
    };
    
    XMLHttpRequest.prototype.send = function(body?: any) {
      const xhr = this;
      const url = (xhr as any).__vRPayment_url || '';

      if (url.toLowerCase().includes('doexecutepayment')) {
        xhr.addEventListener('readystatechange', function() {
          if (xhr.readyState === 4 && xhr.status === 200) {
            try {
              const data = JSON.parse(xhr.responseText);

              if ((data?.data?.type === 'redirect' || data?.data?.type === 'redirectUrl') && data?.data?.value) {
                redirect(data.data.value);
              }
            } catch (err) {
              console.error('[vrpayment] Error parsing XHR response:', err);
            }
          }
        }, true);
      }

      if (url.toLowerCase().includes('dopreparepayment')) {
        try {
          const originUrl = window.location.origin;
          const lang = (document.documentElement.lang || 'en').slice(0, 2);
          
          // Wait for context registration before sending the actual request
          vRPaymentRegisterReturnContext(originUrl, lang)
            .catch((err: any) => console.error('[vrpayment] Context registration failed:', err))
            .finally(() => {
              originalXHRSend.call(xhr, body);
            });
          
          return;
        } catch (err) {
          console.error('[vrpayment] Error in dopreparepayment interception:', err);
        }
      }
      return originalXHRSend.call(this, body);
    };
  }
});