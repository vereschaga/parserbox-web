<?php

namespace AwardWallet\Engine\sunshine\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@sunshinerewards.com',
        ];
    }
}
