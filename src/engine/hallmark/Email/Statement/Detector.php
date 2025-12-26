<?php

namespace AwardWallet\Engine\hallmark\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]hallmarkonline[.]com\b/i',
        ];
    }
}
