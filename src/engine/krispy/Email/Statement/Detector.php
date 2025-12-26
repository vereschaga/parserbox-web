<?php

namespace AwardWallet\Engine\krispy\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@]krispykreme[.]/',
        ];
    }
}
