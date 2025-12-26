<?php

namespace AwardWallet\Engine\surveyhead\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@ipoll.com',
        ];
    }
}
