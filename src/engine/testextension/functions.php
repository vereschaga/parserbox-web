<?php

class TAccountCheckerTestextension extends TAccountChecker
{
    public function GetHistoryColumns()
    {
        return [
            "Type"            => "Info",
            "Eligible Nights" => "Info",
            "Post Date"       => "PostingDate",
            "Description"     => "Description",
            "Starpoints"      => "Miles",
            "Bonus"           => "Bonus",
        ];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation Number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    // GetHistoryRows implemented in extension.js
}
