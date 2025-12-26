<?php

namespace AwardWallet\Engine\morrisons\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]morrisons[.]com\b/i',
        ];
    }
}
