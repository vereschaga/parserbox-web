<?php

namespace AwardWallet\Engine\jetstar\Email;

class It4423947 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "jetstar/it-4423947.eml, jetstar/it-4429051.eml, jetstar/it-4463551.eml, jetstar/it-4466975.eml";

    public static $dictionary = [
        "en" => [],
        'ja' => [
            'Reservation Number:' => '予約番号:',
            'Name:'               => '搭乗者名:',
            'Date:'               => '搭乗日:',
            'Flight:'             => '便名:',
            'Depart:'             => '出発:',
            'Seat:'               => '座席:',
            'Arrive:'             => '到着:',
        ],
    ];

    public $lang = "en";

    private $reFrom = "no-reply@jetstar.com";
    private $reSubject = [
        "en"=> "Check-in confirmation for",
    ];
    private $reBody = 'Jetstar';
    private $reBody2 = [
        'ja' => '裏面に印字されているとバーコードを読み取れない場合があります',
        "en" => "Just print the attached PDF",
    ];

    /** @var \HttpBrowser */
    private $pdf;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace("�", " ", $this->http->Response["body"])); // bad fr char " :"

        $this->pdf = clone $this->http;
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return null;
        }
        $this->pdf->SetBody($html);
        $html = text($html);

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false || stripos($html, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Reservation Number:", $this->http);

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText($this->t("Name:"))];

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

        $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(., '{$this->t('Date:')}')])[1]", null, true, "#{$this->t('Date:')}\s*(.*?)\s*(?:Flight:|$)#")));

        if (false === $date) {
            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(., '{$this->t('Date:')}')])[1]/following::text()[normalize-space(.)][1]", null, true, "#{$this->t('Date:')}\s*(.*?)\s*(?:Flight:|$)#")));
        }

        if (empty($date)) {
            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(., '{$this->t('Date:')}')])[1]/following::text()[normalize-space(.)][1]", null, true, "#^\s*(\d{1,2} \w+ \d{2,4})$#")));
        }

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->pdf->FindSingleNode("(//text()[contains(., '{$this->t('Flight:')}')])[1]", null, true, "#\w{2}(\d+)\)?$#");

        if (empty($itsegment['FlightNumber'])) {
            $itsegment['FlightNumber'] = $this->pdf->FindSingleNode("(//text()[contains(., '{$this->t('Flight:')}')])[1]/following::text()[normalize-space(.)][1]", null, true, "#\w{2}(\d+)\)?$#");
        }

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->nextText($this->t("Depart:"));

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(., '{$this->t('Depart:')}')])[1]/following::text()[not(contains(normalize-space(.), '{$this->t('Depart:')}'))][contains(., ':')][1]")), $date);

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->nextText($this->t("Arrive:"));

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(., '{$this->t('Arrive:')}')])[1]/following::text()[not(contains(normalize-space(.), '{$this->t('Arrive:')}'))][contains(., ':')][1]")), $date);

        // AirlineName
        $itsegment['AirlineName'] = $this->pdf->FindSingleNode("(//text()[contains(., '{$this->t('Flight:')}')])[1]", null, true, "#(\w{2})\d+\)?$#");

        if (empty($itsegment['AirlineName'])) {
            $itsegment['AirlineName'] = $this->pdf->FindSingleNode("(//text()[contains(., '{$this->t('Flight:')}')])[1]/following::text()[normalize-space(.)][1]", null, true, "#(\w{2})\d+\)?$#");
        }

        // Operator
        // Aircraft
        // TraveledMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats

        if (preg_match("#\d+[^\d\s]$#", $seat = $this->nextText($this->t("Seat:")))) {
            $itsegment['Seats'] = $seat;
        } elseif (preg_match("#\d+[^\d\s]$#", $seat = $this->pdf->FindSingleNode("(//text()[starts-with(., '{$this->t('Seat:')}')])[1]", null, true, "#{$this->t('Seat:')}\s*(.+)#"))) {
            $itsegment['Seats'] = $seat;
        }

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    private function nextText($field, $source = false, $n = 1)
    {
        if ($source === false) {
            $source = $this->pdf;
        }

        return $source->FindSingleNode("(//text()[starts-with(normalize-space(.), '{$field}')])[1]/following::text()[normalize-space(.)][not(contains(normalize-space(.), '{$field}'))][{$n}]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //	    $this->logger->alert($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+(\w+)\s+(\d{2})$#",
            "#^(\d{2})(\d{2})\s+/\s+\d+:\d+[ap]m$#",
        ];
        $out = [
            "$1 $2 20$3",
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
