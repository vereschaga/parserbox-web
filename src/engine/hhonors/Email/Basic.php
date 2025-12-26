<?php

namespace AwardWallet\Engine\hhonors\Email;

class Basic extends \TAccountChecker
{
    public function getEmailType()
    {
        if ($this->http->FindPreg("/Confirmation:\s+|Confirmation\s+#\d+/ims")) {
            return 'MailReservation';
        }

        if ($this->http->FindPreg("/Cancellation: /ims")) {
            return 'MailCancelReservation';
        }

        return 'Undefined';
    }

    public function ParseMailReservation()
    {
        $findAndJoin = function ($xpath, $regexp = null) {
            $nds = $this->http->FindNodes($xpath);

            if (!sizeof($nds)) {
                return null;
            }
            $nds = implode("", $nds);

            if (!isset($regexp)) {
                return CleanXMLValue($nds);
            }

            if (preg_match($regexp, $nds, $matches)) {
                if (isset($matches[1])) {
                    return CleanXMLValue($matches[1]);
                }

                return CleanXMLValue($matches[0]);
            } else {
                return null;
            }
        };
        $result = [];
        $result['Kind'] = 'R';
        $result['ConfirmationNumber'] = $this->http->FindPreg("/Confirmation\s*#?:\s*(\d+)/ims");
        $result['HotelName'] = $findAndJoin("//*[contains(@alt, 'Hotel')]/@alt", "/Hotel\s+Name:\s*(.*?)(?=\s*Hotel\s+Address)/ims");
        $result['CheckInDate'] = strtotime($findAndJoin("//td[contains(string(), 'Arrival') and not(.//td)]/following-sibling::td[1]//text()"));
        $result['CheckOutDate'] = strtotime($findAndJoin("//td[contains(string(), 'Departure') and not(.//td)]/following-sibling::td[1]//text()"));
        $street = $findAndJoin("//*[contains(@alt, 'Hotel')]/@alt", "/Hotel\s+Address:\s*(.*?)(?=\s*Hotel Phone)/ims");
        $address = $this->http->FindSingleNode("//*[contains(text(), '" . $street . "') and contains(text(), '|')]");

        if ($address) {
            $result["Address"] = preg_replace("/\s*\|\s*/", ", ", $address);
        } else {
            $result["Address"] = $street;
        }
        $result['Phone'] = $findAndJoin("//*[contains(@alt, 'Hotel')]/@alt", "/Phone:\s*(.*?)(?=\s*Link)/ims");
        $result['Guests'] = $findAndJoin("//td[contains(string(), 'Clients') and not(.//td)]/following-sibling::td[1]//text()", "/(\d+)\s*Adult/ims");
        // TODO: Kids
        $result['Rooms'] = $findAndJoin("//td[contains(string(), 'Rooms') and not(.//td|.//a)]/following-sibling::td[1]//text()");
        // Rate
        $rateNodes = $this->http->FindNodes("//td[contains(string(), 'Rate') and not(.//td)]/following-sibling::td[2]//text()");

        if (sizeof($rateNodes)) {
            $result['Rate'] = CleanXMLValue(preg_replace("/[^\d\.\,\s]+/ims", "", $rateNodes[sizeof($rateNodes) - 1]));
        }
        $result['RateType'] = $findAndJoin("//tr[contains(string(), 'Rate Type') and not(.//tr)]/following-sibling::tr[1]//text()");
        $result['CancellationPolicy'] = $findAndJoin("//table[(contains(string(), 'Cancellation Policy') or contains(string(), 'CANCELLATION POLICY')) and not(.//table)]/following-sibling::table//td//text()");
        $result['RoomType'] = $findAndJoin("//td[contains(string(), 'Room Type') and not(.//td)]/following-sibling::td[1]//text()");
        $result['RoomTypeDescription'] = $findAndJoin("//td[contains(string(), 'Preferences') and not(.//td)]/following-sibling::td[1]//text()");
        $result['Cost'] = (isset($result['Rate'])) ? $result['Rate'] : '';
        $result['Taxes'] = $findAndJoin("//td[contains(string(), 'Taxes') and not(.//td|.//a)]/following-sibling::td[2]//text()", "/([\d\.\,\s]+)/ims");
        $result['Total'] = $findAndJoin("//td[contains(string(), 'Total') and not(.//td|.//a)]/following-sibling::td[2]//text()", "/([\d\.\,\s]+)/ims");
        $result['Currency'] = $findAndJoin("//td[contains(string(), 'Total') and not(.//td|.//a)]/following-sibling::td[2]//text()", "/([^\d\.\,\s]+)/ims");

        if (!isset($result['Currency']) || strtolower($result['Currency']) == 'points') {
            unset($result['Currency'], $result['Total'], $result['Taxes'], $result['Cost']);
        }

        return [
            "Properties"  => [],
            "Itineraries" => $result,
        ];
    }

    public function ParseMailCancelReservation()
    {
        $result = [];
        $result['Kind'] = 'R';
        $result['ConfirmationNumber'] = $this->http->FindPreg("/>\s*Cancellation:\s*([^<]+)/ims");
        $result['Cancelled'] = true;

        return [
            "Properties"  => [],
            "Itineraries" => $result,
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // $this->http->LiveDebug();
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->http->FindPreg("/Group Event Confirmation #([\w\d]+)/ims");
        preg_match_all('/Confirmation\s+#:\s+([\d\w]+)/ims', $parser->getPlainBody(), $m);
        $it['ConfirmationNumbers'] = implode(', ', $m[1]);
        preg_match_all('/Guest:\s+([\w]+\s+[\w]+)/i', $parser->getPlainBody(), $m);
        $it['GuestNames'] = preg_replace('/ +/', ' ', implode(', ', $m[1]));

        if (preg_match('/<h2>\s*Hotel\s+Information.*?<strong>(.*?)<\/strong>(.*?)<\/td>/ims', $parser->getHTMLBody(), $m)) {
            $it['HotelName'] = $m[1];
            $address = str_replace(['&nbsp;', "\n", "\r"], ' ', strip_tags($m[2]));

            if (preg_match('/(.*)\s+([\d\-]+)/ims', $address, $matches)) {
                $it['Address'] = preg_replace('/ +/', ' ', $matches[1]);
                $it['Phone'] = $matches[2];
            } else {
                $it['Address'] = preg_replace('/ +/', ' ', $address);
            }
        }
        $it['Rooms'] = $this->http->FindPreg("/(\d+)\s+rooms total/ims");
        $it['Rate'] = str_replace(',', '', $this->http->FindPreg("/Rate per Night.*?rooms.*?([\d.,]+\s+\w+)/ims"));
        $it['RoomType'] = $this->http->FindPreg("/Total Cost per Night.*?>(\d+[\s\w]+)</ims");

        if (preg_match("/Estimated Guest Rooms Total: ([\d.,]+)\s+(\w+)/ims", $parser->getPlainBody(), $m)) {
            $it['Total'] = str_replace(',', '', $m[1]);
            $it['Currency'] = $m[2];
        }
        $it['CheckInDate'] = strtotime($this->http->FindPreg("/Guest Rooms.*?>\w+,(.*?)<.*?Number of Rooms/ms"));
        $it['CheckOutDate'] = strtotime('+1 day', $it['CheckInDate']);

        return [
            'parsedData' => ['Itineraries' => [$it], 'Properties' => []],
            'emailType'  => 'GroupEvent',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && stripos($headers["from"], "hiltonres.com") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (preg_match("#Group Event#", $parser->getPlainBody())) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@hiltonres\.com/ims', $from);
    }
}
