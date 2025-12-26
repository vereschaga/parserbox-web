<?php

namespace AwardWallet\Engine\velocity\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "velocity/it-12213748.eml, velocity/it-4587581.eml";

    public $reFrom = "@virginaustralia.com";
    public $reBody = [
        'en' => [
            'Thank you for choosing to travel with Virgin Australia',
            'You can also access your online boarding pass via this URL',
        ],
    ];
    public $reSubject = [
        '#Retrieve\s+your\s+Virgin\s+Australia\s+Boarding\s+Pass\s+for\s+(.+?)\s+\-\s+[A-Z\d]{5,}\s+\-#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'RegExpSubj' => '#Retrieve your Virgin Australia Boarding Pass for (.+?) - [A-Z\d]{5,} \-#',
            'Departs'    => ['Departs', 'Departs:'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $this->AssignLang();

        $its[] = $this->parseEmail($parser->getSubject());
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'virginaustralia.com')] | //a[contains(@href,'virginaustralia.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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

    private function parseEmail($subject)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Ref'))}]/following::text()[normalize-space(.)!=''][1]");

        if (!empty($pax = $this->re($this->t('RegExpSubj'), $subject))) {
            $it['Passengers'][] = $pax;
        } else {
            $it['Passengers'][] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Hi ")]', null,
                true, '/^Hi\s+(.+?),\s+you\'ve\s+successfully/i');
        }

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $node = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Date'))})]/following::text()[normalize-space(.)!=''][1]");

        if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }
        $date = strtotime($this->http->FindSingleNode("//text()[({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Date'))})]/following::text()[normalize-space(.)!=''][2]"));
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departs'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, "#^\s*(\d+:\d+)\s*#");
        $seg['DepDate'] = strtotime($time, $date);
        $seg['ArrDate'] = MISSING_DATE;

        $seg['DepCode'] = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Date'))})]/following::text()[normalize-space(.)!=''][3]",
            null, true, "#\(([A-Z]{3})\)#");
        $seg['ArrCode'] = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Date'))})]/following::text()[normalize-space(.)!=''][4]",
            null, true, "#\(([A-Z]{3})\)#");

        if ($seg['DepDate'] > strtotime("-1 month")) {
            $url = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('You can also access your online boarding pass via this URL'))}])[1]/following::a[1][contains(@href,'https://va.aero/')]/@href");

            if (!empty($url)) {
                $this->http->GetURL($url);
                $seg['Seats'][] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Seat'])[1]/following::text()[normalize-space(.)!=''][1]",
                    null, true, "#^\s*(\d+[a-z])\s*$#i");
                $seg['DepartureTerminal'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Terminal'])[1]/following::text()[normalize-space(.)!=''][1]",
                    null, true, "#^\s*(\w)\s*$#");
                $seg['BookingClass'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Fare'])[1]/following::text()[normalize-space(.)!=''][1]",
                    null, true, "#^\s*([A-Z]{1,2})\s*\/\s*.+$#");
                $seg['Cabin'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Fare'])[1]/following::text()[normalize-space(.)!=''][1]",
                    null, true, "#^\s*[A-Z]{1,2}\s*\/\s*(.+)$#");

                if (empty($it['Passengers']) || (isset($it['Passengers'][0]) && strpos($it['Passengers'][0],
                            ' ') === false)
                ) {
                    $it['Passengers'][0] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Name'])[1]/following::text()[normalize-space(.)!=''][1]",
                        null, true, "#^\s*(.+?\/.+?)\s*$#");
                }
            }
        }
        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
