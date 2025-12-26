<?php

namespace AwardWallet\Engine\virginamerica\Email;

class AirReservation extends \TAccountChecker
{
    use \PriceTools;
    public $mailFiles = "virginamerica/it-1724622.eml, virginamerica/it-1724679.eml, virginamerica/it-1729160.eml, virginamerica/it-1729163.eml, virginamerica/it-3706987.eml, virginamerica/it-5405746.eml, virginamerica/it-5418724.eml, virginamerica/it-5427109.eml, virginamerica/it-5885090.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirReservation',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'virginamerica@elevate.virginamerica.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'virginamerica@elevate.virginamerica.com') !== false
            || isset($headers['from']) && stripos($headers['from'], 'reply@elevate.virginamerica.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(normalize-space(), 'Virgin America Reservation')]")->length > 0;
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(normalize-space(text()), 'Your Confirmation Code:')]", null, true, "#:\s*([A-Z\d]+)#");

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Traveler')]", null, "#Traveler [\S]+([\w\s]+)#");

        $it['AccountNumbers'] = $this->http->FindNodes("//text()[starts-with(.,'Elevate #')]", null, "#Elevate \#.*?([A-Z\d\-]+)$#");

        // TotalCharge, currency
        $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//*[normalize-space(text())='TOTAL']/ancestor::td[1]/following-sibling::td"));
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//*[normalize-space(text())='TOTAL']/ancestor::td[1]/following-sibling::td"));

        // SpentAwards
        $it['SpentAwards'] = $this->http->FindSingleNode("//*[normalize-space(text())='TOTAL POINTS REDEEMED']/ancestor::td[1]/following-sibling::td");

        // BaseFare
        $it['BaseFare'] = $this->cost(preg_replace("#[\.,](\d{3})#", "$1", $this->http->FindSingleNode("//div[contains(normalize-space(), 'Base Fare')]/ancestor::td[1]/following-sibling::td[not(contains(., 'pts'))]")));

        // Tax
        $it['Tax'] = $this->http->FindSingleNode("//div[contains(normalize-space(), 'Federal Tax')]/ancestor::td[1]/following-sibling::td", null, true, "#[\S]{1}([\d.]+)#");

        $rows = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Date:')]/preceding-sibling::tr[4]//*[contains(text(),' to ') and contains(text(),'(')]");
        $allSeats = array_map(
            function ($s) {
                $s = preg_replace('#\,\s*#', ' ', $s);
                $r = "";

                if (preg_match_all("#\s*(\d{1,2}[A-Z]{1})\s*#i", $s)) {
                    $r = trim($s);
                }

                return explode(" ", $r);
            }, $this->http->FindNodes("//td[contains(normalize-space(), 'Seats')]/following-sibling::td"));
        $i = 0;

        foreach ($rows as $row) {
            $seg = [];

            // DepName, DepCode, ArrName, ArrCode
            $depArrName = $this->http->FindSingleNode(".", $row);

            if (preg_match("#(?<depName>.*?)\s*\((?<depCode>[A-Z]{3})\)\s*to\s*(?<arrName>.*?)\s*\((?<arrCode>[A-Z]{3})\)#", $depArrName, $math)) {
                $seg['DepName'] = $math['depName'];
                $seg['DepCode'] = $math['depCode'];
                $seg['ArrName'] = $math['arrName'];
                $seg['ArrCode'] = $math['arrCode'];
            }

            // FlightNumber, AirlineName
            $flightNumAirName = $this->getNode('Flight', $row);

            if (preg_match("#([A-Z\d]{2})\s*([\d]+)#", $flightNumAirName, $v)) {
                $seg['FlightNumber'] = $v[2];
                $seg['AirlineName'] = $v[1];
            }

            // DepDate
            $date = $this->getNode('Date', $row);

            if (preg_match("#([\d]{2})([\w]{3})([\d]{4})#", $date, $var)) {
                $date = $var[1] . ' ' . $var[2] . ' ' . $var[3];
            }
            $deptime = $this->getNode('Depart', $row);
            $seg['DepDate'] = strtotime($date . ' ' . $deptime);

            // ArrDate
            $arrtime = $this->getNode("Arrive", $row);
            $seg['ArrDate'] = strtotime($date . ' ' . $arrtime);

            // Cabin
            // stops
            $seg['Stops'] = $this->getNode("Stops", $row);

            // Seats
            $seg['Seats'] = '';

            foreach ($allSeats as $seat) {
                if (isset($seat[$i])) {
                    $seg['Seats'] .= $seat[$i] . ', ';
                }
            }
            $seg['Seats'] = substr($seg['Seats'], 0, -2);
            $i++;
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    protected function getNode($str, $row)
    {
        return $this->http->FindSingleNode("./following::tr[count(descendant::tr)=0 and contains(.,'{$str}:')][1]/td[2]", $row);
    }
}
