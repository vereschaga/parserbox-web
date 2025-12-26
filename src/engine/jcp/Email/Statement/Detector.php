<?php

namespace AwardWallet\Engine\jcp\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@jcprewards.com',
        ];
    }
}
