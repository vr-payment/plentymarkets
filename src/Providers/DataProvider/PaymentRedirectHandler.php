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
        try {
            $this->getLogger(__METHOD__)->error('VRPayment::PaymentRedirectHandler_CALLED', [
                'arg' => $arg
            ]);
            
            /** @var FrontendSessionStorageFactoryContract $session */
            $session = pluginApp(FrontendSessionStorageFactoryContract::class);
            $redirectUrl = $session->getPlugin()->getValue('vRPaymentPendingRedirectUrl');
            
            $this->getLogger(__METHOD__)->error('VRPayment::CheckingForRedirect', [
                'redirectUrl' => $redirectUrl ?? 'null'
            ]);
            
            if ($redirectUrl) {
                // Clear the session value
                $session->getPlugin()->unsetKey('vRPaymentPendingRedirectUrl');
                
                $this->getLogger(__METHOD__)->error('VRPayment::RedirectingFromDataProvider', [
                    'redirectUrl' => $redirectUrl
                ]);
                
                return $twig->render('vRPayment::AutoRedirect', [
                    'redirectUrl' => $redirectUrl
                ]);
            }
            
            return '';
            
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('VRPayment::RedirectHandlerException', [
                'message' => $e->getMessage()
            ]);
            return '';
        }
    }
}
