<?php

namespace AwardWallet\Engine\upromise\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@upromise-email.com',
            '/[.@]upromise[.]com/',
        ];
    }
}
