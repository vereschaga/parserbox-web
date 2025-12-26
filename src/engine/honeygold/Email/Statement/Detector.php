<?php

namespace AwardWallet\Engine\honeygold\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]joinhoney[.]com\b/i',
        ];
    }
}
