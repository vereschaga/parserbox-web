<?php

namespace AwardWallet\Engine\mnogo\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]mnogo[.]ru\b/i',
        ];
    }
}
