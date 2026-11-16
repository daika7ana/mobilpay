# Mobilpay

Laravel wrapper around the [Omnipay](https://github.com/thephpleague/omnipay) MobilPay driver
(`daika7ana/omnipay-mobilpay`): config-driven purchase and IPN handling behind the `Mobilpay` facade.

[![CI](https://github.com/daika7ana/mobilpay/actions/workflows/ci.yml/badge.svg)](https://github.com/daika7ana/mobilpay/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/daika7ana/mobilpay.svg)](https://packagist.org/packages/daika7ana/mobilpay)
[![Downloads](https://img.shields.io/packagist/dt/daika7ana/mobilpay.svg)](https://packagist.org/packages/daika7ana/mobilpay)
[![PHP](https://img.shields.io/packagist/php-v/daika7ana/mobilpay.svg)](https://packagist.org/packages/daika7ana/mobilpay)
[![License](https://img.shields.io/packagist/l/daika7ana/mobilpay.svg)](LICENSE)

## Install

```bash
composer require daika7ana/mobilpay:^1.3
```

## Requirements

PHP 8.2 or newer, Laravel (or `illuminate/support`) 10, 11, 12 or 13, and `daika7ana/omnipay-mobilpay`,
which is pulled in as a dependency.

## Configuration

The service provider and the `Mobilpay` facade are discovered automatically. Publish the config:

```bash
php artisan vendor:publish --provider="Adrianbarbos\Mobilpay\MobilpayServiceProvider"
```

`config/mobilpay.php` holds the gateway credentials and the purchase defaults:

| Key | Default | Used for |
| --- | --- | --- |
| `merchant_id` | `''` | merchant signature sent with every purchase |
| `public_key_path` | `''` | Netopia certificate, required by `purchase()` |
| `private_key_path` | `''` | your private key, required by `response()` |
| `testMode` | `true` | sandbox endpoint when true |
| `currency` | `'RON'` | default `currency` |
| `confirm_url` | `''` | IPN endpoint |
| `return_url` | `''` | browser return endpoint |
| `cancel_url` | `''` | accepted and normalised, never sent — see the notes |

Relative URLs such as `/mobilpay/ipn` are resolved with `url()`, so they become absolute before they
reach the provider. Empty values stay empty.

## Usage

### Start a payment

Chain the setters on the facade, then call `purchase()`:

```php
use Adrianbarbos\Mobilpay\Mobilpay;

$response = Mobilpay::setOrderId('ORDER-1')
    ->setAmount('10.00')
    ->setCurrency('RON')
    ->setDetails('Test payment')
    ->setConfirmUrl('https://example.com/mobilpay/ipn')
    ->setReturnUrl('https://example.com/mobilpay/return')
    ->setAdditionalParams(['customer_id' => 42])
    ->purchase();

return view('payments.redirect', [
    'url'    => $response->getRedirectUrl(),
    'fields' => $response->getRedirectData(),
]);
```

`purchase()` returns the Omnipay `PurchaseResponse` and does not redirect. `purchase(autoRedirect: true)`
additionally calls `$response->redirect()`, which sends the provider's auto-submitting form with
`header()` and `echo` — the classic shortcut, not what you want from an Inertia or API handler.

Each call starts from a fresh copy of the config defaults: the data set by the setters is discarded
after `purchase()`, so set order id, amount and the rest again for the next payment. The reset runs in a
`finally` block, so it also holds in long-lived workers where the facade keeps one gateway instance.

Available setters: `setOrderId()`, `setAmount()`, `setCurrency()`, `setDetails()`, `setConfirmUrl()`,
`setReturnUrl()`, `setCancelUrl()`, `setTestMode()`, `setAdditionalParams()`, `setBillingAddress()`,
`setShippingAddress()`.

### Addresses

`setBillingAddress()` and `setShippingAddress()` take the same associative array
(`type` plus `firstName`, `lastName`, `fiscalNumber`, `identityNumber`, `country`, `county`, `city`,
`zipCode`, `address`, `email`, `mobilePhone`, `bank`, `iban`). Missing keys are filled with empty
strings and an unrecognised `type` falls back to `person`.

Only the billing address reaches the provider: the driver maps `billingAddress` into the order and
ignores `shippingAddress`.

### Handle the IPN

The `confirm_url` route receives `env_key`, `data`, `cipher` and `iv` and must answer with the `<crc>`
XML acknowledgement. By default `response()` echoes it for you, keeping older controllers working:

```php
use Adrianbarbos\Mobilpay\Mobilpay;

$response = Mobilpay::response();  // reads $_POST and echoes the acknowledgement
$data = $response->getData();      // decrypted payload, notification under ['objPmNotify']
$action = $response->getMessage(); // the action the provider reports

// confirmed_pending, paid_pending, paid  -> pending; a further IPN follows
// confirmed                              -> money captured
// canceled                               -> cancelled
// credit                                 -> refunded

// the acknowledgement is already echoed; no return value needed
```

To answer with the acknowledgement yourself, turn the echo off:

```php
$response = Mobilpay::response(autoSendResponse: false, payload: $request->all());

// ... update the order from $response->getData() and $response->getMessage() ...

return response($response->getCrcResponse(), 200, ['Content-Type' => 'application/xml']);
```

- `payload` passes the parsed request data instead of reading `$_POST`, which is what runtimes such as
  Octane need.
- `getCrcResponse()` returns the XML body and requires `daika7ana/omnipay-mobilpay` ^1.6;
  `sendResponse()`, which `response()` calls by default, is deprecated upstream because it writes
  headers and echoes directly.
- `getData()` returns the decrypted payload as an array, with the notification under
  `['objPmNotify']`. `isSuccessful()` is true only for `confirmed`; `isPending()` covers
  `confirmed_pending`, `paid_pending` and `paid`.

## Notes

- `cancel_url` / `setCancelUrl()` are accepted and turned into absolute URLs, but the underlying card
  request emits only the confirm and return URLs, so the value never leaves your application.
- `testMode` switches the endpoint: `true` posts to `https://sandboxsecure.mobilpay.ro`, `false` to
  `https://secure.mobilpay.ro`.
- The certificate (`public_key_path`) is needed to build a purchase; the private key
  (`private_key_path`) is needed to decrypt an IPN.

## Development

```bash
composer test
```

PHPUnit 11 only. The tests stub the config helpers and generate a throwaway key and certificate in the
system temp directory, so they run without a Laravel application, credentials or network access.
