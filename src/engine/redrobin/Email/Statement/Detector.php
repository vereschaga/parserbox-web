<?php

namespace AwardWallet\Engine\redrobin\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@redrobin.com',
        ];
    }
}
