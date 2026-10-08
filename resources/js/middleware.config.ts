/**
 * vRPayment Client Plugin
 * Extends to use custom API endpoints
 * 
 * This goes to "apps/server/middleware.config.ts"
 */

// ...
const config = {

  // ...

  integrations: {
    plentysystems: {
      // ...
      // Extend with vRPayment endpoints seen below
      // ...
      extensions: (extensions: any) => [
        ...extensions,
        {
          name: 'vrpayment',
          extendApiMethods: {
            // vRPaymentRegisterReturnContext sends PWA url to main shop to save it in session
            vRPaymentRegisterReturnContext: async (
              context: any,
              params: { originUrl: string; lang: string }
            ) => {
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/register-return`;
              // Local-dev convenience: shops behind a WAF (e.g. CloudFront) reject request bodies
              // containing a loopback origin (localhost / 127.0.0.1 / 0.0.0.0), so register-return
              // would 403 locally and the hosted payment page never opens. We rewrite the loopback
              // host to [::1] (IPv6 loopback) — the WAF accepts it and it matches Nuxt's default dev
              // bind — so register-return succeeds AND the post-payment success/fail redirect returns
              // to the local PWA. Inert in real deployments (their origin is never loopback).
              // Local requirement: allow http://[::1]:<port> in the middleware CORS origins, because
              // the return pages load on the [::1] origin.
              const originUrl = (params.originUrl || '').replace(/\/\/(localhost|127\.0\.0\.1|0\.0\.0\.0)(?=[:/]|$)/, '//[::1]');
              const { data } = await context.client.post(
                url,
                { originUrl, lang: params.lang },
                {
                  headers: { cookie: context.req?.headers?.cookie || '' }
                }
              );
              return data;
            },
            // vRPaymentRestoreCart restores cart in main shop
            vRPaymentRestoreCart: async (
              context: any,
              params: { orderId: string },
            ) => {
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/restore-cart`;
              const { data } = await context.client.post(
                url, { orderId: params.orderId },
                {
                  headers: { cookie: context.req?.headers?.cookie || '' },
                },
              );
              return data;
            },
            // vRPaymentGetOrderCheckoutData fetches order retry eligibility and available payment methods
            vRPaymentGetOrderCheckoutData: async (
              context: any,
              params: { orderId: string; accessKey?: string },
            ) => {
              const query = new URLSearchParams({ orderId: params.orderId });
              // accessKey is required by the access-key-guarded retry endpoint on the shop
              if (params.accessKey) {
                query.set('accessKey', params.accessKey);
              }
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/order-checkout-data?${query.toString()}`;
              const { data } = await context.client.get(
                url,
                {
                  headers: { cookie: context.req?.headers?.cookie || '' },
                },
              );
              return data;
            },
            // vRPaymentPayOrderRest submits a payment retry for an existing unpaid order
            vRPaymentPayOrderRest: async (
              context: any,
              params: { orderId: string; paymentMethodId: string; accessKey?: string },
            ) => {
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/pay-order`;
              const { data } = await context.client.post(
                url,
                {
                  orderId: params.orderId,
                  paymentMethodId: params.paymentMethodId,
                  // accessKey is required by the access-key-guarded retry endpoint on the shop
                  accessKey: params.accessKey ?? '',
                },
                {
                  headers: { cookie: context.req?.headers?.cookie || '' },
                },
              );
              return data;
            },
            // vRPaymentGetTransactionFailure fetches the user-facing decline message for a failed transaction
            vRPaymentGetTransactionFailure: async (
              context: any,
              params: { transactionId: string },
            ) => {
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/transaction-failure/${params.transactionId}`;
              const { data } = await context.client.get(
                url,
                {
                  headers: { cookie: context.req?.headers?.cookie || '' },
                },
              );
              return data;
            },
          },
        },
      ],
    },
  },
};

// ...
