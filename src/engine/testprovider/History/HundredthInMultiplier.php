<?php

namespace AwardWallet\Engine\testprovider\History;

use AwardWallet\Engine\testprovider\Success;

class HundredthInMultiplier extends Success
{
    public function GetHistoryColumns()
    {
        return [
            "Activity Date"  => "PostingDate",
            "Description"    => "Description",
            "Award Miles"    => "Miles",
            "Amount"         => "Amount",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        return [
            [
                "Activity Date"  => 1493251200,
                "Description"    => "There should be 1.25 multiplier",
                "Amount"         => 100,
                "Award Miles"    => 125,
            ],
        ];
    }
}
