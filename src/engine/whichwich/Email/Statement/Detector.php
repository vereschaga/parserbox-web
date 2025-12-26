<?php

namespace AwardWallet\Engine\whichwich\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/\bwhichwich@c[.]pxsmail[.]com\b/i',
        ];
    }
}
