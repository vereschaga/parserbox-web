<?php

namespace AwardWallet\Engine\myer\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]myerone\b/',
        ];
    }
}
