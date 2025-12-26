<?php

namespace AwardWallet\Engine\menswearhouse\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]menswearhouse[.]/i',
        ];
    }
}
