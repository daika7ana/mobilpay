<?php

namespace Adrianbarbos\Mobilpay;

use Omnipay\Omnipay;

class MobilpayGateway
{
    use DataTrait;

    protected $data;

    public function __construct()
    {
        $this->initData();
    }

    public function purchase(bool $autoRedirect = false)
    {
        try {
            $gateway = Omnipay::create('MobilPay');
            $gateway->setMerchantId(config('mobilpay.merchant_id'));
            $gateway->setPublicKey(config('mobilpay.public_key_path'));

            $response = $gateway->purchase($this->data)->send();

            if ($autoRedirect) {
                $response->redirect();
            }

            return $response;
        } finally {
            // scoped per request, but the facade caches one instance on the app; drop this request's data
            $this->initData();
        }
    }

    /**
     * @return \Omnipay\MobilPay\Message\CompletePurchaseResponse
     */
    public function response(bool $autoSendResponse = true, ?array $payload = null)
    {
        $gateway = Omnipay::create('MobilPay');
        $gateway->setPrivateKey(config('mobilpay.private_key_path'));

        /** @var \Omnipay\MobilPay\Message\CompletePurchaseResponse $response */
        $response = $gateway->completePurchase($payload ?? $_POST)->send();

        if ($autoSendResponse) {
            $response->sendResponse();
        }

        return $response;
    }
}
