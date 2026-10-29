<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum CertificateStatus: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';

    public const int EXPIRING_SOON_DAYS = 30;

    public static function forExpiryDate(?CarbonInterface $expiresOn): self
    {
        return match (true) {
            $expiresOn === null => self::Valid,
            $expiresOn->isBefore(today()) => self::Expired,
            $expiresOn->isBefore(today()->addDays(self::EXPIRING_SOON_DAYS)) => self::ExpiringSoon,
            default => self::Valid,
        };
    }
}
