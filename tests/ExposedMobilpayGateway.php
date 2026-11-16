<?php

namespace Adrianbarbos\Mobilpay\Tests;

use Adrianbarbos\Mobilpay\MobilpayGateway;

class ExposedMobilpayGateway extends MobilpayGateway
{
    /**
     * @return array
     */
    public function exposedData(): array
    {
        return $this->data;
    }
}
