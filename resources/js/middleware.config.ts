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
              const { data } = await context.client.post(
                url,
                { originUrl: params.originUrl, lang: params.lang },
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
              params: { orderId: string },
            ) => {
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/order-checkout-data?orderId=${params.orderId}`;
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
              params: { orderId: string; paymentMethodId: string },
            ) => {
              const url = `${process.env.API_ENDPOINT}/rest/storefront/vrpayment/pay-order`;
              const { data } = await context.client.post(
                url,
                { orderId: params.orderId, paymentMethodId: params.paymentMethodId },
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
