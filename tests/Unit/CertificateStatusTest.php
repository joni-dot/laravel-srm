<?php

namespace Tests\Unit;

use App\Enums\CertificateStatus;
use Tests\TestCase;

class CertificateStatusTest extends TestCase
{
    public function test_status_follows_the_expiry_date()
    {
        $this->assertSame(CertificateStatus::Valid, CertificateStatus::forExpiryDate(null));
        $this->assertSame(CertificateStatus::Valid, CertificateStatus::forExpiryDate(today()->addDays(30)));
        $this->assertSame(CertificateStatus::ExpiringSoon, CertificateStatus::forExpiryDate(today()->addDays(29)));
        $this->assertSame(CertificateStatus::ExpiringSoon, CertificateStatus::forExpiryDate(today()));
        $this->assertSame(CertificateStatus::Expired, CertificateStatus::forExpiryDate(today()->subDay()));
    }
}
