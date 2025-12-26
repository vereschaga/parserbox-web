<?php

namespace AwardWallet\Engine\gordonb\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]craftworksrestaurants[.]com/',
        ];
    }
}
