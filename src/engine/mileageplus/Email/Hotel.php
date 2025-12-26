<?php

namespace AwardWallet\Engine\mileageplus\Email;

class Hotel extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "PartnerHotel",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "The booking you recently made on the United Airlines website is confirmed") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\.com/", $from);
    }

    // The booking you recently made on the United Airlines website is confirmed

    protected function ParseEmail()
    {
        $it = ["Kind" => "R"];
        $it["GuestNames"] = [$this->http->FindSingleNode("//td[contains(., 'Customer name:') and not(.//td)]/following-sibling::td[1]")];
        $it["ConfirmationNumber"] = $this->http->FindSingleNode("(//td[(contains(., 'Itinerary Number:') or contains(., 'Itinerary Number')) and not(.//td)]/following-sibling::td[1])[1]");
        $it['HotelName'] = $this->http->FindSingleNode("//img[@id='confirm-hotel-image']/@alt");

        if (empty($it["HotelName"])) {
            $it["HotelName"] = $this->http->FindSingleNode("//*[@class='confirm-info-title']");
        }
        $it['Address'] = $this->http->FindSingleNode("//td[contains(., 'Address:') and not(.//td)]/following-sibling::td[1]");
        $it['Phone'] = $this->http->FindSingleNode("//td[contains(., 'Phone:') and not(.//td)]/following-sibling::td[1]");
        $it['Fax'] = $this->http->FindSingleNode("//td[contains(., 'Fax:') and not(.//td)]/following-sibling::td[1]");

        if (empty($it['Phone']) && !empty($it['Fax'])) {
            $it['Phone'] = $it['Fax'];
        }
        $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//td[contains(., 'Check-in:') and not(.//td)]/following-sibling::td[1]"));
        $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//td[contains(., 'Check-out:') and not(.//td)]/following-sibling::td[1]"));
        $it['Guests'] = $this->http->FindSingleNode("//td[contains(., 'Number of guests:') and not(.//td)]/following-sibling::td[1]", null, true, '/Adults?: (\d+)/');
        $it['RateType'] = $it['RoomType'] = $this->http->FindSingleNode("//th[contains(., 'Room Type')]/ancestor::table[1]/tbody/tr[1]/td[2]");
        $currency = $this->http->FindSingleNode("//*[contains(text(), 'Cost per night and per room in')]", null, true, '/Cost per night and per room in (.+)/');

        if (preg_match("/[A-Z]{3}/", $currency, $m)) {
            $it['Currency'] = $m[0];
        } else {
            $it['Currency'] = $currency;
        }
        $it['Rate'] = $this->http->FindSingleNode("//th[contains(., 'Total per night')]/ancestor::table[1]/tbody/tr[1]/td[last()]");

        if (!empty($it['Rate'])) {
            $it['Rate'] .= ' per night';
        }
        $it['Cost'] = str_ireplace(",", "", $this->http->FindSingleNode("//tr[contains(., 'Total Per room') and not(.//tr)]/td[last()]", null, true, '/[\d\.\,]+$/'));
        $it['Taxes'] = str_ireplace(",", "", $this->http->FindSingleNode("//td[contains(., 'Tax Recovery Charges')]/following-sibling::td[last()]", null, true, '/[\d\.\,]+$/'));
        $it['Total'] = str_ireplace(",", "", $this->http->FindSingleNode("//th[contains(., 'Total cost of stay')]/ancestor::table/tfoot/tr[1]/td[last()]", null, true, '/[\d\.\,]+$/'));
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//tr[contains(., 'Cancellation Policy') and not(.//tr)]/following-sibling::tr[2]");

        if ($this->http->FindSingleNode("//text()[contains(., 'Your reservation is confirmed')]")) {
            $it['Status'] = "Confirmed";
        }

        return [$it];
    }
}
