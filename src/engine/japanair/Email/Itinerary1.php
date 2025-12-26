<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "japanair/it-2397074.eml, japanair/it-2685817.eml";
    public $reFrom = "/@jal\.com/";
    public $reFrom2 = "/@jal\.us/";
    public $reBody = 'The booking reference number is needed whenever in contact with Japan Airlines';
    public $reBody2 = "Operated by Japan Airlines";
    public $reBody3 = "The booking reference number is needed whenever in contact with Japan Airlines ";
    public $reBody4 = "Passengers Details";
    public $reBody5 = "onlinesupport@jal.us";
    public $reBody6 = "#(?:Traveller Information|Traveler Information)#";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2685817.eml"
            $this->reBody2 => function (&$itineraries) {
                $this->logger->info('Type: $this->reBody2');
                $it = [];
                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Reference/Confirmation Number')]/ancestor::tr[1]/following-sibling::*[1]//strong");
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[contains(@alt, 'Passenger Information')]/ancestor::tr[1]/following-sibling::*/td[1]/table//tr[1]/td", null, "#^\S+\s+(.+)$#ms");
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(), 'Total')]/../following-sibling::td[1]", null, true, "#([0-9,.]+)#"));
                // BaseFare
                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Total')]/../following-sibling::td[1]", null, true, "#[0-9,.]+\s+\S+\s+\(([^\)]+)\)#");
                // Tax
                $it['Tax'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(), 'Taxes/Others')]/../following-sibling::td[1]", null, true, "#([0-9,.]+)#"));
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[contains(text(), 'Total duration')]/ancestor::table[1]";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments roots not found: $xpath", LOG_LEVEL_ERROR);
                }

                foreach ($segments as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//tr[3]/td[3]", $root, true, "#\D+(\d+)#ms");
                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    // DepName
                    $itsegment['DepName'] = str_replace(' ,', ',', $this->http->FindSingleNode(".//tr[6]/td[1]/p[1]", $root, true, "#\d+:\d+\s+(.*?)\s+-#ms"));
                    $itsegment['DepartureTerminal'] = trim(str_ireplace('terminal', '', $this->http->FindSingleNode(".//tr[6]/td[1]/p[1]", $root, true, "#\d+:\d+\s+.*?\s+-(.*terminal.*)#ims")));

                    $date = explode('/', $this->http->FindSingleNode(".//tr[1]/td/table//tr/td[2]", $root, true, "#-\s+(\S+)#ms"));

                    if (count($date) != 3) {
                        return;
                    }

                    if (!$this->is_numeric_array($date)) {
                        return;
                    }

                    // DepDate
                    $itsegment['DepDate'] = strtotime(implode('.', $date) . ' ' . $this->http->FindSingleNode(".//tr[6]/td[1]/p[1]", $root, true, "#(\d+:\d+)#ms"));
                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    // ArrName
                    if (!$arrday = $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#^(\S+\s+\d+\s+\S+)$#ms")) {
                        $itsegment['ArrName'] = str_replace(' ,', ',', $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#\d+:\d+\s+(.*?)\s+-#ms"));
                        $itsegment['ArrivalTerminal'] = trim(str_ireplace('terminal', '', $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#\d+:\d+\s+.*?\s+-(.*terminal.*)#ims")));
                    } else {
                        $itsegment['ArrName'] = str_replace(' ,', ',', $this->http->FindSingleNode(".//tr[6]/td[1]/p[3]", $root, true, "#\d+:\d+\s+(.*?)\s+-#ms"));
                        $itsegment['ArrivalTerminal'] = trim(str_ireplace('terminal', '', $this->http->FindSingleNode(".//tr[6]/td[1]/p[3]", $root, true, "#\d+:\d+\s+.*?\s+-(.*terminal.*)#ims")));
                    }

                    if (preg_match("#(?<month>\w+)\s+(?<day>\d+)\s+(?<week>\w+)#", $arrday, $m)) {
                        $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week']));
                        $arrday = EmailDateHelper::parseDateUsingWeekDay($m['day'] . ' ' . $m['month'] . ' ' . $date[2], $weeknum);
                    }

                    // ArrDate
                    if (!$arrday) {
                        $itsegment['ArrDate'] = strtotime(implode('.', $date) . ' ' . $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#(\d+:\d+)#ms"));
                    } else {
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[6]/td[1]/p[3]", $root, true, "#(\d+:\d+)#ms"), $arrday);
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode(".//tr[3]/td[3]", $root, true, "#(\D+)\d+#ms");
                    // Aircraft

                    $itsegment['Aircraft'] = $this->http->FindSingleNode(".//tr[4]", $root);
                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode(".//*[contains(text(),'Cabin')]/..", $root, true, "#Cabin:\s+(\S+)#ms");
                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode(".//*[contains(text(),'Total duration')]/following-sibling::*[1]", $root);
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
            // Parsing subject "it-2397074.eml"
            $this->reBody4 => function (&$itineraries) {
                $this->logger->info('Type: $this->reBody4');
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Reference/Confirmation Number')]/../../../following-sibling::*[1]/td[2]/p[1]/strong/span");
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Passenger Information')]/ancestor::tr[1]/following-sibling::*/td[1]/table/tr[1]/td[1]/p/strong/span", null, "#^\S+\s+(.+)$#ms");
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(), 'Total')]/../../../following-sibling::*[1]/p/strong/span", null, true, "#([0-9,.]+)#"));
                // BaseFare
                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Total')]/../../../following-sibling::*[1]/p/strong/span", null, true, "#[0-9,.]+\s+\S+\s+\(([^\)]+)\)#");
                // Tax
                $it['Tax'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(), 'Tax / Surcharge')]/../../following-sibling::*[1]", null, true, "#([0-9,.]+)#"));
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[contains(text(), 'Total duration')]/ancestor::table[1]";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments roots not found: $xpath", LOG_LEVEL_ERROR);
                }

                foreach ($segments as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//tr[3]/td[3]/p/strong/span", $root, true, "#\D+(\d+)#ms");
                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    // DepName
                    $itsegment['DepName'] = str_replace(' ,', ',', $this->http->FindSingleNode(".//tr[6]/td[1]/p[1]", $root, true, "#\d+:\d+\s+(.*?)\s+-#ms"));
                    $itsegment['DepartureTerminal'] = trim(str_ireplace('terminal', '', $this->http->FindSingleNode(".//tr[6]/td[1]/p[1]", $root, true, "#\d+:\d+\s+.*?\s+-(.*terminal.*)#ims")));

                    $date = explode('/', $this->http->FindSingleNode(".//tr[1]/td/table/tr/td[2]/p/strong/span", $root, true, "#-\s+(\S+)#ms"));

                    if (count($date) != 3) {
                        return;
                    }

                    if (!$this->is_numeric_array($date)) {
                        return;
                    }

                    // DepDate
                    $itsegment['DepDate'] = strtotime(implode('.', $date) . ' ' . $this->http->FindSingleNode(".//tr[6]/td[1]/p[1]", $root, true, "#(\d+:\d+)#ms"));
                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    // ArrName

                    if (!$arrday = $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#^(\S+\s+\d+\s+\S+)$#ms")) {
                        $itsegment['ArrName'] = str_replace(' ,', ',', $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#\d+:\d+\s+(.*?)\s+-#ms"));
                        $itsegment['ArrivalTerminal'] = trim(str_ireplace('terminal', '', $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#\d+:\d+\s+.*?\s+-(.*terminal.*)#ims")));
                    } else {
                        $itsegment['ArrName'] = str_replace(' ,', ',', $this->http->FindSingleNode(".//tr[6]/td[1]/p[3]", $root, true, "#\d+:\d+\s+(.*?)\s+-#ms"));
                        $itsegment['ArrivalTerminal'] = trim(str_ireplace('terminal', '', $this->http->FindSingleNode(".//tr[6]/td[1]/p[3]", $root, true, "#\d+:\d+\s+.*?\s+-(.*terminal.*)#ims")));
                    }

                    if (preg_match("#(?<month>\w+)\s+(?<day>\d+)\s+(?<week>\w+)#", $arrday, $m)) {
                        $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week']));
                        $arrday = EmailDateHelper::parseDateUsingWeekDay($m['day'] . ' ' . $m['month'] . ' ' . $date[2], $weeknum);
                    }

                    // ArrDate
                    if (!$arrday) {
                        $itsegment['ArrDate'] = strtotime(implode('.', $date) . ' ' . $this->http->FindSingleNode(".//tr[6]/td[1]/p[2]", $root, true, "#(\d+:\d+)#ms"));
                    } else {
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[6]/td[1]/p[3]", $root, true, "#(\d+:\d+)#ms"), $arrday);
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode(".//tr[3]/td[3]/p/strong/span", $root, true, "#(\D+)\d+#ms");
                    // Aircraft
                    $itsegment['Aircraft'] = $this->http->FindSingleNode(".//tr[4]/td[2]/p/span", $root);
                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode(".//*[contains(text(),'Cabin')]/../following-sibling::*[1]", $root, true, "#^(\S+)#ms");
                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode(".//*[contains(text(),'Total duration')]/../following-sibling::*[1]//strong", $root, true, "#^(\S+)#ms");
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from) || preg_match($this->reFrom2, $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false)
                || (strpos($body, $this->reBody3) !== false && strpos($body, $this->reBody4) !== false)
                || (
                    (strpos($body, $this->reBody5) !== false || stripos($body, 'Thank you for choosing JAPAN AIRLINES') !== false)
                    && (strpos($body, 'Traveller Information') !== false || stripos($body, 'Traveler Information') !== false)
                );
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        foreach ($this->processors as $re => $processor) {
            if (stripos($re, '#') === false && stripos($body, $re) !== false) {
                $processor($itineraries);

                break;
            } elseif (stripos($re, '#') !== false && preg_match($re, $body) !== 0) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Itinerary1',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public function is_numeric_array($array)
    {
        foreach ($array as $key => $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
