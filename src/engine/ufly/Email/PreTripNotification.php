<?php

namespace AwardWallet\Engine\ufly\Email;

class PreTripNotification extends \TAccountChecker
{
    public $mailFiles = "ufly/it-4450118.eml, ufly/it-7425471.eml, ufly/it-7445373.eml, ufly/it-8783597.eml, ufly/it-8860218.eml";

    public $reFrom = "Reservations@suncountry.com";
    public $reBody = [
        'en' => ['Sun Country Airlines', ['Your confirmation code is', 'Your code to check-in online is', 'Your Flight Record Locator is', 'Your flight time has changed']],
    ];
    public $reSubject = [
        'Sun Country Airlines - Pre Trip Notification',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'recLoc'   => ['Your confirmation code is', 'Your code to check-in online is', 'Your Flight Record Locator is'],
            'noRecLoc' => 'Your flight time has changed',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $NBSP = chr(194) . chr(160);
        $this->http->SetBody(str_replace($NBSP, ' ', html_entity_decode($this->http->Response['body'])));
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'PreTripNotification' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[starts-with(@alt,'Sun Country')]")->length > 0) {
            return $this->AssignLang();
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
        $cnt = 3 * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('recLoc'))}]", null, true, "#([A-Z\d]+)\s*$#");

        if (empty($it['RecordLocator']) && $this->http->XPath->query("//text()[{$this->starts($this->t('noRecLoc'))}]")->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $it['Passengers'] = array_values(array_filter(explode(',', $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Thank you for choosing Sun Country Airlines')]/preceding::text()[normalize-space(.)!=''][1]"))));
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Your')]/ancestor::*[1][contains(.,'is available') or contains(.,'is scheduled') or contains(.,' scheduled to depart')]");

        if (preg_match("#Your\s+flight\s+\#(\d+)\s+from\s+(.+?)\s+to\s+(.+?)\s+on\s+(.+?)\s+is\s+available\s+for\s+check#", $node, $m) //format #1
            || preg_match("#Your\s+flight\s+\#(\d+)\s+from\s+(.+?)\s+to\s+(.+?)\s+is\s+scheduled\s+to\s+depart\s+on\s+(.+?)\.#", $node, $m)  //format #2
        ) {
            $seg['FlightNumber'] = $m[1];
            $seg['AirlineName'] = 'SY';
            $seg['DepName'] = $m[2];
            $seg['ArrName'] = $m[3];
            $seg['DepDate'] = strtotime($this->normalizeDate($m[4]));
        } elseif (preg_match("#Your\s+flight\s+([A-Z\d]{2})\s*(\d+)\s+from\s+(.+?)\s+to\s+(.+?)\s+originally\s+scheduled\s+to\s+depart\s+on\s+(.+?)\s+has\s+changed#", $node, $m)) { //format #3
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
            $seg['DepName'] = $m[3];
            $seg['ArrName'] = $m[4];
            $seg['DepDate'] = strtotime($this->normalizeDate($m[5]));
            $newDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'New departure')]/following::text()[string-length(normalize-space(.))>2][1]");

            if (!empty($newDate)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($newDate));
            }
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrDate'] = MISSING_DATE;

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            // 04/29/2016 at 07:30 PM
            '#\s*(\d+)\/(\d+)\/(\d+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#i',
            // 11:10 PM on 06/21/2015
            '#\s*(\d+:\d+(?:\s*[ap]m)?)\s*on\s*(\d+)\/(\d+)\/(\d+)\s*$#i',
            '#\s*(\d+)\/(\d+)\/(\d+)\s*$#i',
        ];
        $out = [
            '$3-$1-$2 $4',
            '$4-$2-$3 $1',
            '$3-$1-$2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
                if ($this->http->XPath->query("//node()[contains(normalize-space(.),'{$reBody[0]}') or contains(@alt, '{$reBody[0]}')]")->length === 0) {
                    return false;
                }

                foreach ($reBody[1] as $re) {
                    if ($this->http->XPath->query("//text()[{$this->starts($re)}]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
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
}
