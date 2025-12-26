<?php

namespace AwardWallet\Engine\quidco\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]quidco[.]com/',
        ];
    }
}
