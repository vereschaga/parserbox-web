<?php

namespace AwardWallet\Engine\msccruises\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/\bmsccruises[.]ca\b/i',
        ];
    }
}
