<?php

namespace AwardWallet\Engine\surveysavvy\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@surveysavvy.com',
        ];
    }
}
