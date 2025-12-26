<?php

namespace AwardWallet\Engine\mileageplus\Email;

class Rental extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-8.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "RentalConfirmation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'united.com Rental Car Confirmation') !== false
            || isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "Thank you for choosing united.com") !== false && stripos($body, "Rental Car Confirmation for") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    // united.com Rental Car Confirmation

    protected function ParseEmail()
    {
        $it = ["Kind" => "L"];

        if ($date = $this->http->FindSingleNode("//td[contains(., 'Issue Date') and not(.//td)]/following-sibling::td[last()]")) {
            $it["ReservationDate"] = strtotime($date);
        }
        $it["RenterName"] = $this->http->FindSingleNode("//td[contains(., 'Name:') and not(.//td)]/following-sibling::td[last()]");
        $it["Number"] = $this->http->FindSingleNode("//td[contains(., 'Confirmation') and contains(., 'Number:') and not(.//td)]/following-sibling::td[last()]");
        $it["PickupDatetime"] = $this->fixDate($this->http->FindSingleNode("//td[contains(., 'Pick up:') and not(.//td)]/following-sibling::td[last()]"));
        $it["PickupLocation"] = $this->http->FindSingleNode("//tr[td[contains(., 'Pick up:') and not(.//td)]]/following-sibling::tr[1][td[1][normalize-space(.) = '']]/td[last()]");
        $it["DropoffDatetime"] = $this->fixDate($this->http->FindSingleNode("//td[contains(., 'Return:') and not(.//td)]/following-sibling::td[last()]"));
        $it["DropoffLocation"] = $this->http->FindSingleNode("//tr[td[contains(., 'Return:') and not(.//td)]]/following-sibling::tr[1][td[1][normalize-space(.) = '']]/td[last()]");

        foreach ([
            "Car Company:" => "RentalCompany",
            "Phone:" => "PickupPhone",
            "Car Type:" => "CarType",
            "Car Options:" => "CarOptions",
            "Rental Rate:" => "Rate",
            "Estimated Total:" => "TotalCharge",
        ] as $search => $key) {
            $it[$key] = $this->http->FindSingleNode("//td[contains(., '" . $search . "') and not(.//td)]/following-sibling::td[last()]");
        }

        if ($it["PickupLocation"] == $it["DropoffLocation"] && isset($it["PickupPhone"])) {
            $it["DropoffPhone"] = $it["PickupPhone"];
        }

        if (isset($it["CarType"]) && isset($it["CarOptions"])) {
            $it["CarType"] .= ", " . $it["CarOptions"];
        }
        unset($it["CarOptions"]);

        if (preg_match("/^(\D+)[\d\.\,]+/", $it["Rate"], $m)) {
            $it["Currency"] = $m[1];

            switch ($it["Currency"]) {
                case "$":
                    $it["Currency"] = "USD";

                    break;
            }
        }
        unset($it["Rate"]);

        if (!isset($it["RentalCompany"])) {
            $it["RentalCompany"] = $this->http->FindSingleNode("//td[contains(., 'Confirmation Number') and not(.//td)]", null, true, "/^(.+) Confirmation Number:/");
        }

        if (empty($it["PickupLocation"]) && $this->http->FindSingleNode("//text()[contains(., 'Rental Car Cancellation')]")) {
            $it["Status"] = "Cancelled";
            $it["Cancelled"] = true;
        }

        return [$it];
    }

    private function fixDate($date)
    {
        // Tue., Apr. 1, 2014 8:00PM
        // Sun., 30 Sep., 2012 10:00AM - needs fixing
        if (preg_match("/^(\w+\.\,) (\d+) (\w+\.)(\, \d{4} \d+:\d+[AP]M)$/", $date, $m)) {
            $date = $m[1] . " " . $m[3] . " " . $m[2] . $m[4];
        }

        return strtotime($date);
    }
}
