<?php

namespace AwardWallet\Engine\junky\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]activejunky[.]com\b/i',
        ];
    }
}
