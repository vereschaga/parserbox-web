<?php

namespace AwardWallet\Engine\petrocanada\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@petro-canada.ca',
        ];
    }
}
