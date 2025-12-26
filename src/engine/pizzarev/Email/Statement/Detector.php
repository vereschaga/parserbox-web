<?php

namespace AwardWallet\Engine\pizzarev\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]pizzarev[.]com/',
        ];
    }
}
