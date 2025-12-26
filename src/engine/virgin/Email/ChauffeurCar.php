<?php

namespace AwardWallet\Engine\virgin\Email;

class ChauffeurCar extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@driven.virgin-atlantic.com";
    public $reBody = [
        'en' => ['Your booking information', 'Child seats'],
    ];
    public $reSubject = [
        'Virgin Atlantic Chauffeur Car Booking Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;
            $text = '';

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);

                if (preg_match("#filename=.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $text .= base64_decode($t);
                }

                if (preg_match("#quoted-printable#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $text .= quoted_printable_decode($t);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
        }
        $this->http->SetEmailBody($text);

        $this->AssignLang($text);

        $its = $this->parseEmailTransfer();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ChauffeurCar' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Virgin Atlantic']")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;
            $text = '';

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);

                if (preg_match("#filename=.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $text .= base64_decode($t);
                }

                if (preg_match("#quoted-printable#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $text .= quoted_printable_decode($t);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }

            if (stripos($text, 'Virgin Atlantic')) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailTransfer()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Booking reference')}')]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]+)#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Flight Booking reference')]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]+)#");

        if (empty($it['TripNumber'])) {
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Flight Booking reference:')]/following::text()[normalize-space(.)!=''][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
        }
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Passenger']/ancestor::td[1]/following::td[1]");
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

        $seg = [];
        $seg['Vehicle'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Vehicle')]/ancestor::td[1]/following-sibling::td[1]");
        $seg['Type'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Company name')]/ancestor::td[1]/following-sibling::td[1]");
        $date = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Date') and following::table[1][contains(.,'Time')]]/ancestor::td[1]/following-sibling::td[1]"));
        $seg['DepDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Time')][1]/ancestor::td[1]/following-sibling::td[1]"), $date);
        $seg['ArrDate'] = MISSING_DATE;
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['DepName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Airport')][1]/ancestor::td[1]/following-sibling::td[1]");

        if (!empty($seg['DepName'])) {
            $seg['FlightNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Flight number')][1]/ancestor::td[1]/following-sibling::td[1]");
            $seg['AirlineName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Airline')][1]/ancestor::td[1]/following-sibling::td[1]");
            $seg['ArrName'] = implode(";", $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Drop-off details')]/following::text()[string-length(normalize-space(.))>3][1]/ancestor::table[1]//text()[normalize-space(.)!='']"));
        } else {
            $street = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Street')][1]/ancestor::td[1]/following-sibling::td[1]");
            $town = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Town')][1]/ancestor::td[1]/following-sibling::td[1]");
            $country = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Pick-up details')]/following::text()[contains(.,'Country')][1]/ancestor::td[1]/following-sibling::td[1]");

            if (!empty($street)) {
                $seg['DepName'] = implode(";", [$street, $town, $country]);
            }
            $drop = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Drop-off details')]/following::text()[string-length(normalize-space(.))>3][1]");

            if (preg_match("#(.+)\s+\(([A-Z]{3})\),\s+(.+),\s+Flight number\s+(\d+)#", $drop, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $seg['AirlineName'] = $m[3];
                $seg['FlightNumber'] = $m[4];
            }
        }

        $it['TripSegments'][] = $seg;
        $its[] = $it;

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
