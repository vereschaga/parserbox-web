<?php

namespace AwardWallet\Engine\sony\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]sonyrewards\b/',
        ];
    }
}
