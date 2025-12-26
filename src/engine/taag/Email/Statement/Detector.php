<?php

namespace AwardWallet\Engine\taag\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@loyaltyplus.aero',
        ];
    }
}
