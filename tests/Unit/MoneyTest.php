<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_increment_rounds_up_without_floating_point(): void
    {
        $this->assertSame('20000.01', Money::minimum(null, '20000.00'));
        $this->assertSame('22000.02', Money::minimum('20000.01', '20000.00'));
        $this->assertSame('23100.00', Money::minimum('21000.00', '20000.00'));
        $this->assertNull(Money::minimum('9999999999.99', '20000.00'));
    }
}
