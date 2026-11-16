<?php

namespace Adrianbarbos\Mobilpay\Tests;

use PHPUnit\Framework\TestCase;

class DataTraitTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mobilpay_test_config'] = [];
    }

    public function testUrlsStayEmptyWithoutConfig(): void
    {
        $data = (new ExposedMobilpayGateway())->exposedData();

        $this->assertEmpty($data['confirmUrl']);
        $this->assertEmpty($data['returnUrl']);
        $this->assertEmpty($data['cancelUrl']);
    }

    public function testRelativeUrlBecomesAbsolute(): void
    {
        $GLOBALS['mobilpay_test_config'] = [
            'mobilpay.confirm_url' => '/pay/confirm',
        ];

        $data = (new ExposedMobilpayGateway())->exposedData();

        $this->assertSame('https://app.test/pay/confirm', $data['confirmUrl']);
    }

    public function testAbsoluteUrlIsPreserved(): void
    {
        $GLOBALS['mobilpay_test_config'] = [
            'mobilpay.confirm_url' => 'https://payments.example.com/mobilpay/confirm?x=1',
        ];

        $data = (new ExposedMobilpayGateway())->exposedData();

        $this->assertSame('https://payments.example.com/mobilpay/confirm?x=1', $data['confirmUrl']);
    }

    public function testSettersAreChainableAndStored(): void
    {
        $gateway = new ExposedMobilpayGateway();

        $this->assertSame($gateway, $gateway->setOrderId('order-42'));
        $this->assertSame($gateway, $gateway->setAdditionalParams(['foo' => 'bar']));

        $data = $gateway->exposedData();

        $this->assertSame('order-42', $data['orderId']);
        $this->assertSame(['foo' => 'bar'], $data['params']);
    }
}
