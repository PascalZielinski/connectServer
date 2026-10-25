<?php

namespace App\Service;

use DeviceDetector\DeviceDetector;

final class UserAgentParser
{
    public static function describe(?string $userAgent): string
    {
        $detector = new DeviceDetector(mb_substr($userAgent ?? '', 0, 512));
        $detector->parse();

        $browser = $detector->getClient('name') ?: 'Unbekannter Browser';
        $os = $detector->getOs('name') ?: 'Unbekanntes System';

        return sprintf('%s auf %s', $browser, $os);
    }
}