<?php

namespace Adrianbarbos\Mobilpay;

/**
 * Class Mobilpay.
 * @method static \Omnipay\MobilPay\Message\PurchaseResponse purchase(bool $autoRedirect = false)
 * @method static \Omnipay\MobilPay\Message\CompletePurchaseResponse response(bool $autoSendResponse = true, array|null $payload = null)
 * @method static MobilpayGateway setOrderId($orderId)
 * @method static MobilpayGateway setAmount($amount)
 * @method static MobilpayGateway setCurrency($currency)
 * @method static MobilpayGateway setDetails($setDetails)
 * @method static MobilpayGateway setConfirmUrl($url)
 * @method static MobilpayGateway setReturnUrl($url)
 * @method static MobilpayGateway setCancelUrl($url) accepted but unused: the MobilPay card request emits no cancel URL
 * @method static MobilpayGateway setTestMode(bool $flag)
 * @method static MobilpayGateway setAdditionalParams(array $value)
 * @method static MobilpayGateway setBillingAddress(array $value)
 */
class Mobilpay extends \Illuminate\Support\Facades\Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor()
    {
        return 'mobilpay';
    }
}
