<?php

namespace AwardWallet\Engine\shell\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]shell[.]com\b/i',
        ];
    }
}
