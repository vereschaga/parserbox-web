<?php

namespace AwardWallet\Engine\usaa\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    // otc subject Your USAA Security Code
    // accid 4881523
    protected function getFrom(): array
    {
        return [
            '/[@.]usaa[.]com\b/i',
        ];
    }
}
