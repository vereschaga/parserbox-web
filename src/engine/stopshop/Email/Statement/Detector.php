<?php

namespace AwardWallet\Engine\stopshop\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@stopandshop.com',
        ];
    }
}
