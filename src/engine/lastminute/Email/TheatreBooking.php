<?php

namespace AwardWallet\Engine\lastminute\Email;

class TheatreBooking extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-7654505.eml, lastminute/it-7681208.eml, lastminute/it-7683863.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'emailType'  => "TheatreBooking",
            'parsedData' => ["Itineraries" => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@lastminute.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "Theatre and Entertainment bookings") !== false && stripos($body, 'lastminute') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@lastminute.com") !== false;
    }

    protected function ParseEmail()
    {
        $it = ["Kind" => "E"];

        //TripNumber
        $it["TripNumber"] = $this->http->FindSingleNode("//*[text()='Booking confirmation']/ancestor::table[1]//*[contains(text(),'Booking number') or contains(text(),'Booking ID')]//ancestor-or-self::td[1]/following-sibling::td");
        //DinerName
        $it["DinerName"] = $this->http->FindSingleNode("//*[text()='Booking confirmation']/ancestor::table[1]//*[contains(text(),'Booked in name')]/ancestor-or-self::td[1]/following-sibling::td");

        //ConfNo
        $it["ConfNo"] = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Booking ID') or contains(text(),'Booking reference')]/ancestor-or-self::td[1]/following-sibling::td");
        //Name
        $it["Name"] = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Your booking')]/ancestor-or-self::td[1]/following-sibling::td");
        //Guests
        $it["Guests"] = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Your booking')]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#^(\d+)\s*x\s*#s");
        //StartDate	*	unixtime	*	Дата начала
        $date = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Date')]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $time = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Time')]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $it["StartDate"] = strtotime($date . ' ' . $time);
        //Address	*	string	*	Адрес
        $it["Address"] = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Address') or contains(text(),'Location') or contains(text(), 'Street name')]/ancestor-or-self::td[1]/following-sibling::td[1]");

        //TotalCharge
        //Currency
        $total = $this->http->FindSingleNode("//*[text()='Booking Details']/ancestor::table[1]//*[contains(text(),'Total amount') or contains(text(),'Total')]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $total = preg_replace(['/\$/', '/\€/', '/\£/'], ['USD', 'EUR', 'GBP'], $total);

        if (preg_match("#([A-Z]{3})\s*([\d., ]+)#", $total, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $this->normalizePrice($m[2]);
        }

        //Status
        if (!empty($this->http->FindSingleNode("(//*[contains(.,'Your booking is confirmed')])[1]"))) {
            $it['Status'] = "Confirmed";
        }
        //EventType
        $it['EventType'] = EVENT_SHOW;

        return [$it];
    }

    private function normalizePrice($price)
    {
        if (preg_match("#([.,])\d{2}($|[^\d])#", $price, $m)) {
            $delimiter = $m[1];
        } else {
            $delimiter = '.';
        }
        $price = preg_replace('/[^\d\\' . $delimiter . ']+/', '', $price);
        $price = (float) str_replace(',', '.', $price);

        return $price;
    }
}
