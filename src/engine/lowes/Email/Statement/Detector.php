<?php

namespace AwardWallet\Engine\lowes\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]viagogo[.]com/',
            '/[@.]ra[.]co/',
        ];
    }
}
