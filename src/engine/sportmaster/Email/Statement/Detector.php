<?php

namespace AwardWallet\Engine\sportmaster\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]sportmaster[.]ru\b/i',
        ];
    }
}
