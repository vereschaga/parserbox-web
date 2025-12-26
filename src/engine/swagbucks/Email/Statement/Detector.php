<?php

namespace AwardWallet\Engine\swagbucks\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@swagbucks.com',
        ];
    }
}
