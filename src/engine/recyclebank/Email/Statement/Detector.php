<?php

namespace AwardWallet\Engine\recyclebank\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@recyclebank.com',
        ];
    }
}
