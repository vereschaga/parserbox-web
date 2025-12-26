<?php

namespace AwardWallet\Engine\shangrila\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]shangri-la[.]com\b/i',
        ];
    }
}
