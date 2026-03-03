# VR Payment PWA Redirect Handler

This PWA extension handles payment redirects for VR Payment in plentyShop PWA.

## Installation

### Option 1: Copy to PWA Apps Directory (Recommended)

1. Copy the entire `vrpayment-redirect` folder to your PWA's `apps/` directory:
   ```
   your-pwa-project/
   └── apps/
       └── vrpayment-redirect/
           ├── nuxt.config.ts
           ├── plugins/
           │   └── vrpayment.client.ts
           └── package.json
   ```

2. The app will be automatically loaded by the PWA.

### Option 2: Add to nuxt.config.ts extends (Alternative)

If you prefer not to use the apps directory, add this to your PWA's main `nuxt.config.ts`:

```typescript
export default defineNuxtConfig({
  extends: [
    './apps/vrpayment-redirect'
  ]
});
```

## How It Works

1. Intercepts all `fetch()` calls in the browser
2. Detects responses from `doExecutePayment` endpoint
3. Checks if response contains `type: "redirect"` with a payment URL
4. Automatically redirects browser to the VR Payment page
5. After payment, user returns to order confirmation

## Verification

Open browser console and look for:
- `[VR Payment] PWA integration script loaded`
- `[VR Payment] Fetch interceptor installed`
- During checkout: `[VR Payment] Redirecting to: https://...`

## Troubleshooting

If redirects aren't working:
1. Check browser console for VR Payment messages
2. Verify the plugin is returning `type: "redirect"` in backend logs
3. Ensure PWA cache is cleared
4. Try hard refresh (Ctrl+Shift+R / Cmd+Shift+R)
