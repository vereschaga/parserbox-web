<?php

namespace AwardWallet\Engine\scene\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.](?:sceneplus|scene)[.]ca\b/i',
        ];
    }
}
