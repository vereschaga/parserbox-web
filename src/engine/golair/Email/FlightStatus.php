<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightStatus extends \TAccountCheckerExtended
{
    public $mailFiles = "golair/it-10331724.eml";

    public $reFrom = "statusvoo@voegol.com.br";
    public $reSubject = [
        "GOL - STATUS DE VOO",
    ];

    public $reBody = "http://www.voegol.com.br";
    public $reBody2 = [
        "Status do Voo -",
    ];

    public $date;
    public $lang = 'pt';

    public function parseHtml()
    {
        $it = [];

        $it['Kind'] = "T";
        // RecordLocator

        $it['RecordLocator'] = CONFNO_UNKNOWN;
        // TripNumber
        // Passengers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//img[contains(@src, 'EMailImagens/boxCenterP.jpg')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding::td[starts-with(normalize-space(), 'Voo') and .//img]", $root, true, "#Voo\s*[A-Z\d]{2}\s*(\d{1,5})#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[1]/td[1]//descendant::text()[normalize-space()][1]", $root, true, "#^([A-Z]{3})$#");

            // DepName
            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]//tr[1]/td[1]", $root, true, "#:\s*(\d+\D\d+\s+-\s+\d+.+)#"));

            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Terminal')]/ancestor::tr[1]/following::tr[1]", $root, true, "#(.+) - .+#");

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[1]/td[2]//descendant::text()[normalize-space()][1]", $root, true, "#^([A-Z]{3})$#");

            // ArrName
            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]//tr[1]/td[2]", $root, true, "#:\s*(\d+\D\d+\s+-\s+\d+.+)#"));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding::td[starts-with(normalize-space(), 'Voo') and .//img]", $root, true, "#Voo\s*([A-Z\d]{2})\s*\d{1,5}#");

            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        return [$it];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHtmlBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            if (stripos($body, $reBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = \AwardWallet\Common\Parser\Util\EmailDateHelper::calculateOriginalDate($this, $parser);

        if (empty($this->date)) {
            $this->http->Log("Year is not detected", LOG_LEVEL_NORMAL);

            return [];
        }

        $itineraries = [];

        $body = $parser->getHTMLBody();

        $itineraries = $this->parseHtml();

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ["pt"];
    }

    private function normalizeDate($str)
    {
        if (empty($str)) {
            return false;
        }
        $in = [
            '#^\s*(\d+)h(\d+)\s+-\s+(\d+)\s+(\w+)\s*$#u', //16h45 - 27 Dez
        ];
        $out = [
            '$3 $4 ' . date("Y", $this->date) . ' $1:$2',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $str = strtotime($str);

        if ($str < strtotime("-2day", $this->date)) {
            $str = strtotime("+1year", $str);
        }

        return $str;
    }
}
