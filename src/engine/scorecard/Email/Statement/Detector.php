<?php

namespace AwardWallet\Engine\scorecard\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    // otc subject "Your ScoreCard Rewards Code"
    protected function getFrom(): array
    {
        return [
            '/[@.]scorecardrewards[.]com\b/i',
        ];
    }
}
