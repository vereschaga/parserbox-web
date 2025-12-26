<?php

namespace AwardWallet\Engine\peetnik\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/\bpeets[.]com\b/i',
        ];
    }
}
