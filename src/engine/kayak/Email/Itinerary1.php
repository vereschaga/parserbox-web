<?php

namespace AwardWallet\Engine\kayak\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "kayak/it-1.eml, kayak/it-2.eml, kayak/it-4.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Your KAYAK reservation receipt') !== false
            || isset($headers['from']) && preg_match('/@kayak\.com/ims', $headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@kayak\.com/ims", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Your KAYAK reservation receipt') !== false
            || strpos($body, 'KAYAK.com') !== false
            || $this->checkMails($body);
    }

    public function checkMails($input = '')
    {
        preg_match('/[\.@]kayak\.com/ms', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = null;

        $html = strip_tags($parser->getHTMLBody(), "<table><tbody><tr><td><span>");
        $html = preg_replace("/\<span\s[^\>]*\>/im", "<span>", $html);
        $html = preg_replace("/\<span\>\s*\<span\>/im", "<xpm>", $html);
        $html = preg_replace("/\<\/span\>\s*\<\/span\>/im", "</xpm>", $html);
        $html = strip_tags($html, "<table><tbody><tr><td><xpm>");

        $html = preg_replace("/\s+/im", " ", $html);
        $html = preg_replace("/\n+|\n/im", " ", $html);

        $number = $this->http->FindSingleNode("//td[(contains(normalize-space(.), 'Confirmation Number') or contains(normalize-space(.), 'Your confirmation number:')) and not(contains(normalize-space(.), 'will provide customer service for this booking'))]");

        if (!empty($number)) {
            $html = preg_replace("/\<td\s[^\>]*\>/im", "<td>", $html);
            $html = preg_replace("/\<tr\s[^\>]*\>/im", "<tr>", $html);
            $html = preg_replace("/\<table\s[^\>]*\>/im", "<table>", $html);
            $html = preg_replace("/[^\w\d<>\s:\-\/\"'\.\$@;&]+/im", "", $html);

            for ($i = 0; $i <= 4; $i++) {
                $html = preg_replace("/<td>(\n|\s|&nbsp;)*?<\/td>/im", "", $html);
                $html = preg_replace("/<tr>(\n|\s|&nbsp;)*?<\/tr>/im", "", $html);
            }
            $html = preg_replace("/<table>(\n|\s|&nbsp;)*?<\/table>/im", "", $html);
            $this->http->SetBody($html);
            $it = $this->carRental();
        } else {
            $this->http->SetBody($html);
            $it = $this->airTrip();
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$it],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function airTrip()
    {
        $it['Kind'] = 'T';

        preg_match('/:\s(?P<locator>[A-Z0-9]+)/u', $this->http->FindSingleNode("//td[contains(normalize-space(text()), 'Record Locator')]"), $locator);

        $it['RecordLocator'] = $locator['locator'] ?? '';
        $it['Passengers'] = $this->http->FindNodes("//tr[contains(., 'Name') and not(contains(., 'Traveler')) and count(td)=5]/following-sibling::tr/td[1]");
        $it['TotalCharge'] = preg_replace('~[^0-9,\.\s]+~', '', $this->http->FindSingleNode("//tr[contains(., 'Grand Total') and not(contains(., 'Tickets '))]/td[2]"));
        $it['Currency'] = $this->_getCurrency(preg_replace('~[0-9,\.\s]+~', '', $this->http->FindSingleNode("//tr[contains(., 'Grand Total') and not(contains(., 'Tickets '))]/td[2]")));
        $rows = $this->http->XPath->query("//table[contains(., 'See fare rules') and not(contains(., 'Booking'))]/*[count(tr) > 1]/tr[not(contains(., 'See fare rules')) and not(contains(., 'Connection'))]");
        $i = 0;
        $tmp = [];

        foreach ($rows as $row) {
            $row = trim($row->textContent);
            $row = preg_replace('~\n+~', "\n", $row);

            if (!empty($row)) {
                $tmp[$i][] = $row;
                preg_match("~(?P<next>Fare code)~", $row, $result);

                if (isset($result['next'])) {
                    $i++;
                }
            }
        }
        $depDate = $this->http->FindSingleNode("//td[contains(., 'Departure') and not(contains(., 'See fare rules'))]");
        $depDate = preg_replace('/^.*?([A-Za-z]+,\s?([A-Za-z\s0-9]+)).*$/u', '$2', $depDate);

        $arrDate = $this->http->FindSingleNode("//td[contains(., 'Return Flight') and not(contains(., 'See fare rules'))]");
        $arrDate = preg_replace('/^.*?([A-Za-z]+,\s?([A-Za-z\s0-9]+)).*$/u', '$2', $arrDate);
        $it['TripSegments'] = $this->getSegments($tmp, $depDate, $arrDate);

        return $it;
    }

    public function correctFindNodes($array)
    {
        if (is_array($array)) {
            reset($array);

            return $array[@key($array)];
        }

        return $array;
    }

    private function getSegments($raws, $depDate, $arrDate)
    {
        $depYear = strtotime("-4 Month") > strtotime(trim($depDate) . ' ' . date('Y')) ? ' ' . date('Y', strtotime("+1 Year")) : ' ' . date('Y');
        $arrYear = strtotime("-4 Month") > strtotime(trim($arrDate) . ' ' . date('Y')) ? ' ' . date('Y', strtotime("+1 Year")) : ' ' . date('Y');

        $result = $tmp = [];

        foreach ($raws as $key => $raw) {
            foreach ($raw as $value) {
                $value = preg_replace("~[^[:print:]]~", "", $value);

                if (!empty($value)) {
                    $tmp[$key][] = preg_replace("~^.*?(Landing:.*)$~", "$1", preg_replace("~\s+~", " ", $value));
                }
            }
        }

        foreach ($tmp as $rawSegment) {
            $segment['AirlineName'] = trim(preg_replace('/^(.*)?(Flight.*)$/u', '$1', $rawSegment[0]));
            $segment['FlightNumber'] = trim(preg_replace('/^(.*)?(Flight\s([0-9]+)\s).*$/u', '$3', $rawSegment[0]));
            $aircraft = explode('|', $rawSegment[2]);
            $segment['Aircraft'] = $aircraft[1];

            $segment['DepCode'] = trim(preg_replace('/^.*?(([A-Z]{3}):(.*)).*$/u', '$2', $rawSegment[0]));
            $segment['DepName'] = trim(preg_replace('/^.*?(([A-Z]{3}):(.*)).*$/u', '$3', $rawSegment[0]));
            $segment['DepDate'] = strtotime($depDate . $depYear . ' ' . preg_replace('/^.*?(Take-off:\s?([\d:aA|pP]+)).*$/u', '$2', $rawSegment[0]) . 'm');

            $segment['ArrCode'] = trim(preg_replace('/^.*?(([A-Z]{3}):(.*)).*$/u', '$2', $rawSegment[1]));
            $segment['ArrName'] = trim(preg_replace('/^.*?(([A-Z]{3}):(.*)).*$/u', '$3', $rawSegment[1]));
            $segment['ArrDate'] = strtotime($arrDate . $arrYear . ' ' . preg_replace('/^.*?(Landing:\s?([\d:aA|pP]+)).*$/u', '$2', $rawSegment[1]) . 'm');

            $result[] = $segment;
        }

        return $result;
    }

    private function carRental()
    {
        $it['Kind'] = 'L';
        $number = $this->http->FindSingleNode("//td[(contains(normalize-space(.), 'Confirmation Number') or contains(normalize-space(.), 'Your confirmation number:')) and not(contains(normalize-space(.), 'will provide customer service for this booking'))]");
        preg_match('~(:?:\s?(?P<number>[0-9A-Z\-]+))~', $number, $numberR);
        $it['Number'] = $numberR['number'] ?? '';
        $renter = $this->http->FindSingleNode("//tr[contains(., 'Reward No.') and not(.//tr)]/following-sibling::tr[1]/td[1]");

        if (empty($renter)) {
            $renter = $this->http->FindSingleNode("//td[contains(normalize-space(text()), 'Reward No')]/../following-sibling::tr[1]/td[1]");
        }
        $it['RenterName'] = trim(preg_replace("~[a-z\.\-\_]+@(.*)~", '', preg_replace("~[^A-Za-z@\s\.]~u", '', $renter)));
        // $it['RentalCompany'] = trim($this->http->FindSingleNode("//text()[contains(., 'You reserved a rental car on')]", null, true, "/You reserved a rental car on (.+)$/i"), " .");
        $it['RentalCompany'] = trim($this->http->FindSingleNode("//text()[contains(., 'will provide customer service')]", null, true, "/(.+) will provide customer service/i"), " .");
        $it["CarType"] = $this->http->FindSingleNode("//tr[td[contains(., 'Per Day') and not(.//td)]]/following-sibling::tr[1]/td[1]");

        if (preg_match('#(.*?)\s+-\s+(.*)#ims', $it['CarType'], $matches)) {
            $it['CarType'] = $matches[1];
            $it['CarModel'] = $matches[2];
        }
        $pickUpDate = strtotime(preg_replace('~[^A-Za-z0-9:\s]\s~u', '', $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Pick up') and not(contains(., 'Your Car')) and not(contains(., 'Get directions') and string-length(normalize-space(.)) != '')]/../following-sibling::tr[1]/td[1]")));

        if ($pickUpDate === false) {
            $pickUpDate = strtotime(preg_replace('~[^A-Za-z0-9:\s]\s~u', '', $this->http->FindSingleNode("//tr[contains(normalize-space(.), 'Pick up') and not(contains(., 'Drop off')) and not(contains(., 'Your Car')) and not(contains(., 'Get directions'))]/following-sibling::tr[1]")));
        }
        $it['PickupDatetime'] = $pickUpDate;

        $dropoffDate = strtotime(preg_replace('~[^A-Za-z0-9:\s]\s~u', '', $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Pick up') and not(contains(., 'Your Car')) and not(contains(., 'Get directions') and string-length(normalize-space(.)) != '')]/../following-sibling::tr[1]/td[2]")));

        if ($dropoffDate === false) {
            $dropoffDate = strtotime(preg_replace('~[^A-Za-z0-9:\s]\s~u', '', $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Drop off') and not(contains(., 'Your Car')) and not(contains(., 'Get directions'))]/../following-sibling::tr[1]/td[1][contains(., 'AM') or contains(., 'PM')]")));
        }
        $it['DropoffDatetime'] = $dropoffDate;

        $it['PickupLocation'] = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Pick up') and not(contains(., 'Your Car ')) and not(contains(., 'Get directions ') and string-length(normalize-space(.)) != '')]/../following-sibling::tr[2]/td[1]", null, false);
        $dropOff = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Pick up') and not(contains(., 'Your Car ')) and not(contains(., 'Get directions ') and string-length(normalize-space(.)) != '')]/../following-sibling::tr[2]/td[2]", null, false);

        if (empty($dropOff)) {
            $dropOff = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Drop off') and not(contains(., 'Your Car')) and not(contains(., 'Get directions'))]/../following-sibling::tr[2]/td[1]");
        }
        $it['DropoffLocation'] = $dropOff;

        if (strpos($it['DropoffLocation'], 'same location') !== false) {
            $it['DropoffLocation'] = $it['PickupLocation'];
        }

        $total = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Car Total') and not(contains(normalize-space(.), 'Per Day'))]/following-sibling::td[2]");
        $it['TotalCharge'] = preg_replace('~[^0-9\.,\s]~', '', $total);
        $it['Currency'] = $this->_getCurrency(preg_replace('~[0-9\.,\s]~', '', $total));

        $carType = preg_replace('~[^0-9A-Za-z\.,\s\-]~', '', $this->http->FindSingleNode("//td[contains(., 'Your Car') and not(contains(., 'Additional rental terms'))]/../following-sibling::tr[1]/td"));

        if (!empty($carType)) {
            $it['CarType'] = $carType;
        }

        return $it;
    }

    private function _getCurrency($curSign = '$')
    {
        switch ($curSign) {
            case "$":
                $result = 'USD';

                break;

            default:
                $result = $curSign;

                break;
        }

        return $result;
    }
}
