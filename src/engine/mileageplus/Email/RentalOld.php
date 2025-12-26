<?php

namespace AwardWallet\Engine\mileageplus\Email;

class RentalOld extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "RentalConfirmationOld",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'united.com Rental Car Confirmation') !== false
            || isset($headers['from']) && stripos($headers['from'], 'united@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "Thank you for choosing united.com") !== false && stripos($body, "Your car reservation") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    // united.com Rental Car Confirmation

    protected function ParseEmail()
    {
        $it = ["Kind" => "L"];
        $it["RenterName"] = $this->http->FindSingleNode("//td[contains(., \"Driver's Name:\") and not(.//td)]/following-sibling::td[last()]");
        $it["Number"] = $this->http->FindSingleNode("//td[contains(., 'Confirmation Number:') and not(.//td)]/following-sibling::td[last()]");
        $it["RentalCompany"] = $this->http->FindSingleNode("//td[contains(., 'Confirmation Number:') and not(.//td)]", null, true, "/^(.+) Confirmation Number:/");
        $car = $this->http->FindSingleNode("//tr[contains(., 'Car rental details') and following-sibling::tr[contains(., 'Pick-up')]]/following-sibling::tr[1]");

        if (preg_match("/^(.+) - ([^-]+)$/", $car, $m)) {
            $it["CarType"] = $m[1];
            $it["CarModel"] = $m[2];
        }
        $it["PickupDatetime"] = strtotime($this->http->FindSingleNode("//td[contains(., 'Pick-up') and not(.//td)]/span[1]"));
        $it["PickupLocation"] = $this->http->FindSingleNode("//td[contains(., 'Pick-up') and not(.//td)]/span[2]");
        $it["DropoffDatetime"] = strtotime($this->http->FindSingleNode("//td[contains(., 'Drop-off') and not(.//td)]/span[1]"));
        $it["DropoffLocation"] = $this->http->FindSingleNode("//td[contains(., 'Drop-off') and not(.//td)]/span[2]");
        $total = $this->http->FindSingleNode("//td[contains(., 'Estimated total') and not(.//td)]");

        if (preg_match("/Estimated total (\D+)([\d\.\,]+)/", $total, $m)) {
            $it["TotalCharge"] = str_replace(",", "", $m[2]);

            if (stripos($m[1], '$') !== false) {
                $m[1] = "USD";
            }
            $it["Currency"] = $m[1];
        }

        return [$it];
    }
}
