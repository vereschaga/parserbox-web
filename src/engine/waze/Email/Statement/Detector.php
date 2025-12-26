<?php

namespace AwardWallet\Engine\waze\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@waze.com',
        ];
    }
}
