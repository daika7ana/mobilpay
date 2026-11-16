<?php

namespace Adrianbarbos\Mobilpay\Tests;

use Adrianbarbos\Mobilpay\MobilpayGateway;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Omnipay;
use PHPUnit\Framework\TestCase;

class MobilpayGatewayTest extends TestCase
{
    private string $certPath = '';

    private string $keyPath = '';

    protected function setUp(): void
    {
        // Omnipay eagerly discovers a PSR-18 client; inject a stub instead of requiring one
        Omnipay::setFactory(new StubGatewayFactory());

        $suffix = bin2hex(random_bytes(8));
        $this->certPath = sys_get_temp_dir() . "/mobilpay-test-{$suffix}.crt";
        $this->keyPath = sys_get_temp_dir() . "/mobilpay-test-{$suffix}.key";

        $options = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $key = openssl_pkey_new($options);
        $csr = openssl_csr_new(['commonName' => 'mobilpay-test'], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 1, $options);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem);

        file_put_contents($this->certPath, $certPem);
        file_put_contents($this->keyPath, $keyPem);

        $GLOBALS['mobilpay_test_config'] = [
            'mobilpay.merchant_id' => '1234567',
            'mobilpay.public_key_path' => $this->certPath,
            'mobilpay.private_key_path' => $this->keyPath,
            'mobilpay.currency' => 'RON',
        ];
    }

    protected function tearDown(): void
    {
        Omnipay::setFactory(null);

        @unlink($this->certPath);
        @unlink($this->keyPath);

        $GLOBALS['mobilpay_test_config'] = [];
    }

    public function testPurchaseUsesDataSetBeforeTheCall(): void
    {
        $gateway = (new MobilpayGateway())
            ->setOrderId('ORDER-X')
            ->setAmount('10.00')
            ->setDetails('regression')
            ->setConfirmUrl('https://app.test/ipn')
            ->setReturnUrl('https://app.test/return');

        $response = $gateway->purchase();
        $data = $response->getData();

        $privateKey = openssl_pkey_get_private('file://' . $this->keyPath);
        $this->assertNotFalse($privateKey);

        $sealed = base64_decode($data['data'], true);
        $plain = '';
        $opened = openssl_open(
            $sealed,
            $plain,
            base64_decode($data['env_key'], true),
            $privateKey,
            $data['cipher'],
            base64_decode($data['iv'], true),
        );

        $this->assertTrue($opened);
        $this->assertStringContainsString('id="ORDER-X"', $plain);
        $this->assertStringContainsString('amount="10.00"', $plain);
        $this->assertStringContainsString('regression', $plain);
    }

    public function testPurchaseClearsStateAfterTheCall(): void
    {
        $gateway = (new ExposedMobilpayGateway())
            ->setOrderId('ORDER-Y')
            ->setAmount('20.00')
            ->setDetails('second request')
            ->setConfirmUrl('https://app.test/ipn')
            ->setReturnUrl('https://app.test/return')
            ->setAdditionalParams(['foo' => 'bar']);

        $gateway->purchase();

        $data = $gateway->exposedData();

        $this->assertSame('', $data['orderId']);
        $this->assertSame('', $data['amount']);
        $this->assertSame([], $data['params']);
    }

    public function testResponseWithoutAutoSendEchoesNothingAndExposesErrorAcknowledgement(): void
    {
        ob_start();
        $response = (new MobilpayGateway())->response(false, self::undecryptablePayload());
        $echoed = ob_get_clean();

        $this->assertSame('', $echoed, 'autoSendResponse: false must not echo');
        $this->assertSame([], $response->getData());

        $crc = $this->crcAcknowledgement($response);

        $this->assertStringContainsString('error_type=', $crc);
        $this->assertStringContainsString('error_code=', $crc);
    }

    public function testResponseByDefaultSendsTheXmlAcknowledgement(): void
    {
        ob_start();
        (new MobilpayGateway())->response(true, self::undecryptablePayload());
        $echoed = (string) ob_get_clean();

        $this->assertStringContainsString('<crc', $echoed);
    }

    /**
     * @return array<string, string>
     */
    private static function undecryptablePayload(): array
    {
        return [
            'env_key' => 'not-base64',
            'data' => 'not-base64',
            'cipher' => 'aes-256-cbc',
            'iv' => '',
        ];
    }

    private function crcAcknowledgement($response): string
    {
        if (method_exists($response, 'getCrcResponse')) {
            return $response->getCrcResponse();
        }

        // shortcut: driver 1.5.0 has no getCrcResponse() (added in 1.6.0); drop this fallback after the ^1.6 bump
        ob_start();
        $response->sendResponse();

        return (string) ob_get_clean();
    }
}

/**
 * Test double: Omnipay's gateway factory builds a real PSR-18 client eagerly, so the
 * stub client is injected here instead.
 */
class StubGatewayFactory extends GatewayFactory
{
    public function create($class, ?ClientInterface $httpClient = null, ?\Symfony\Component\HttpFoundation\Request $httpRequest = null)
    {
        return parent::create($class, new StubHttpClient(), $httpRequest);
    }
}

class StubHttpClient implements ClientInterface
{
    public function request($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1')
    {
        throw new \LogicException('Tests must not send HTTP requests.');
    }
}
