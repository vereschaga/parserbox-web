<?php

namespace AwardWallet\Engine\isay\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]i[-]say[.]com\b/i',
        ];
    }
}
