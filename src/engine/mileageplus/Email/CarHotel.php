<?php

namespace AwardWallet\Engine\mileageplus\Email;

// TODO: merge with parsers amextravel/Reservation (in favor of amextravel/Reservation)

class CarHotel extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-2188073.eml, mileageplus/it-3580747.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "CarHotel",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["subject"]) && preg_match("/United Reservation \d+$/", trim($headers["subject"]))
        || isset($headers["from"]) && (stripos($headers["from"], "mileageplus-cathotels@united.com") !== false || stripos($headers["from"], "mileageplus-carhotel@united.com") !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href, 'hotelandcarawards.mileageplus.com')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\.com/", $from);
    }

    public static function getEmailTypesCount()
    {
        // car and hotel
        return 2;
    }

    // subject: United Reservation 12345
    // from: mileageplus-carhotels@united.com

    protected function ParseEmail()
    {
        $common = $its = [];
        $date = $this->http->FindSingleNode("//td[contains(., 'Booking Number') and contains(., 'Booking Date') and not(.//td)]", null, true, "/Booking Date (\d+\/\d+\/\d+)/");

        if ($date && $date = strtotime($date)) {
            $common["ReservationDate"] = $date;
        }
        $lines = array_filter($this->http->FindNodes('//tr[(contains(., "Traveler information") or contains(., "TRAVELER INFORMATION")) and not(.//tr)]/following-sibling::tr[contains(., "LEAD TRAVELER")]//text()'));

        if (array_shift($lines) === 'LEAD TRAVELER') {
            $common['Travelers'] = [array_shift($lines)];
        }
        $payment = $this->http->FindSingleNode("//td[contains(., 'Payments received') and not(.//td)]/following-sibling::td[1]");

        if (preg_match("/([\d\,]+) miles/", $payment, $m)) {
            $common["SpentAwards"] = $m[1];
        }

        if (preg_match("/\\\$([\d\.\,]+)/", $payment, $m)) {
            $common["Total"] = str_replace(",", "", $m[1]);
            $common["Currency"] = "USD";
        }
        $blocks = $this->http->XPath->query('//tr[(contains(., "Your Itinerary") or contains(., "YOUR ITINERARY")) and not(.//tr)]/following-sibling::tr//tr[contains(., "Confirmation:") and not(.//tr)]/parent::*');

        foreach ($blocks as $block) {
            switch (strtolower($this->http->FindSingleNode('./tr[1]', $block))) {
                case 'room':
                    $its[] = $this->ParseHotel($block, $common);

                    break;

                case 'car':
                    $its[] = $this->ParseCar($block, $common);

                    break;
            }
        }

        if (count($its) == 1) {
            switch ($its[0]['Kind']) {
                case 'R':
                    $map = [
                        'Total'       => 'Total',
                        'Currency'    => 'Currency',
                        'SpentAwards' => 'SpentAwards',
                    ];

                    break;

                case 'L':
                    $map = [
                        'TotalCharge' => 'Total',
                        'Currency'    => 'Currency',
                        'SpentAwards' => 'SpentAwards',
                    ];

                    break;
            }

            if (isset($map)) {
                foreach ($map as $k => $n) {
                    if (isset($common[$n])) {
                        $its[0][$k] = $common[$n];
                    }
                }
            }
        }

        return $its;
    }

    protected function ParseHotel($root, $common)
    {
        $it = [];
        $it["Kind"] = "R";

        foreach ($this->http->XPath->query('//*[@class="crsName"]') as $text) {
            $text->nodeValue = '';
        }
        $it["ConfirmationNumber"] = $this->http->FindSingleNode(".//tr[contains(., 'Confirmation:') and not(.//tr)]", $root, true, "/Confirmation: (\S+)/");
        $it["GuestNames"] = $common["Travelers"];
        $it["HotelName"] = $this->http->FindSingleNode(".//tr[td[not(.//td) and .//img[contains(@src, 'symbol_star')]]]/preceding-sibling::tr[1]", $root);
        $nodes = $this->http->XPath->query(".//tr[td[not(.//td) and .//img[contains(@src, 'symbol_star')]]]/following-sibling::tr[1]", $root);

        if ($nodes->length > 0) {
            $info = $nodes->item(0);
            $lines = $this->http->FindNodes(".//text()[normalize-space()!='']", $info);
            // last line - possibly phone
            $phone = array_pop($lines);

            if (preg_match("/[\d\-]{6,}$/", $phone)) {
                $it["Phone"] = preg_replace('/^[^\d\-\+]+/', '', $phone);
            } else {
                $lines[] = $phone;
            }
            $it["Address"] = implode(", ", $lines);
        }
        $it["RoomType"] = $this->http->FindSingleNode(".//td[contains(., 'Room type:') and not(.//td)]", $root, true, "/Room type: (.+)$/");

        if (!isset($it['RoomType'])) {
            $it["RoomType"] = $this->http->FindSingleNode(".//td[contains(., 'Room type:') and not(.//td)]/parent::*", $root, true, "/Room type: (.+)$/");
        }
        $it["RoomTypeDescription"] = $this->http->FindSingleNode(".//tr[td[not(.//td) and .//img[contains(@src, 'symbol_star')]]]/following-sibling::tr[contains(., 'Room description')]//ul", $root);

        if (!isset($it['RoomTypeDescription'])) {
            $it["RoomTypeDescription"] = $this->http->FindSingleNode(".//td[contains(., 'Room description') and not(.//td)]/parent::*", $root, true, "/Room description (.+)$/");
        }
        $it["CheckInDate"] = strtotime($this->http->FindSingleNode(".//td[contains(., 'Check-in:') and not(.//td)]/following-sibling::td[1]", $root));
        $it["CheckOutDate"] = strtotime($this->http->FindSingleNode(".//td[contains(., 'Check-out:') and not(.//td)]/following-sibling::td[1]", $root));
        $it["Guests"] = $this->http->FindSingleNode(".//td[contains(., 'Guests:') and not(.//td)]/following-sibling::td[1]", $root, true, "/(\d+) Adult/");
        $it["Kids"] = $this->http->FindSingleNode(".//td[contains(., 'Guests:') and not(.//td)]/following-sibling::td[1]", $root, true, "/(\d+) Child/");

        return $it;
    }

    protected function ParseCar($root, $common)
    {
        $it = [];
        $it["Kind"] = "L";
        $it["Number"] = $this->http->FindSingleNode(".//tr[contains(., 'Confirmation:') and not(.//tr)]", $root, true, "/Confirmation: (\S+)/");

        if (isset($common["Travelers"][0])) {
            $it["RenterName"] = $common["Travelers"][0];
        }
        $it["RentalCompany"] = $this->http->FindSingleNode(".//tr[contains(., 'Confirmation:') and not(.//tr)]", $root, true, "/Confirmation: \S+ (.+)$/");
        $it["PickupDatetime"] = strtotime($this->http->FindSingleNode(".//td[contains(., 'Pick-up') and not(contains(., 'location')) and not(.//td)]/following-sibling::td[1]", $root));
        $it["DropoffDatetime"] = strtotime($this->http->FindSingleNode(".//td[contains(., 'Drop-off') and not(contains(., 'location')) and not(.//td)]/following-sibling::td[1]", $root));
        $it["PickupLocation"] = $this->http->FindSingleNode(".//td[contains(., 'Pick-up location') and not(.//td)]/following-sibling::td[1]", $root);
        $it["DropoffLocation"] = $this->http->FindSingleNode(".//td[contains(., 'Drop-off location') and not(.//td)]/following-sibling::td[1]", $root);

        if (!isset($it["DropoffLocation"])) {
            $it["DropoffLocation"] = $it["PickupLocation"];
        }
        $it["CarType"] = $this->http->FindSingleNode(".//td[contains(., 'Car type:') and not(.//td)]", $root, true, "/Car type: (.+)$/");

        return $it;
    }
}
