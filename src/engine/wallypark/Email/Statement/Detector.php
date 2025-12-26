<?php

namespace AwardWallet\Engine\wallypark\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]wallypark[.]com\b/i',
        ];
    }
}
