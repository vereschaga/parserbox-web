<?php

namespace AwardWallet\Engine\pcpoints\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]pcoptimum[.]ca\b/i',
        ];
    }
}
