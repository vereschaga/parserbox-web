<?php

namespace AwardWallet\Engine\maxermas\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/\bmaxandermas[.]/i',
        ];
    }
}
