<?php

namespace AwardWallet\Engine\oldchicago\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]oldchicago[.]com\b/i',
        ];
    }
}
