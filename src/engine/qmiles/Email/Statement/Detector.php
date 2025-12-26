<?php

namespace AwardWallet\Engine\qmiles\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    // otc subject "QR - OTP"
    protected function getFrom(): array
    {
        return [
            '/[@.]qmiles[.]com\b/i',
        ];
    }
}
