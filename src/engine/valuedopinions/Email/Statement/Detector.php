<?php

namespace AwardWallet\Engine\valuedopinions\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]valuedopinions[.]com\b/i',
        ];
    }
}