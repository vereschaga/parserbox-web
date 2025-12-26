<?php

namespace AwardWallet\Engine\quiznos\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]quiznos[.]com\b/i',
        ];
    }
}
