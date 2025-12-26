<?php

namespace AwardWallet\Engine\icelandair\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-13338375.eml, icelandair/it-13518316.eml, icelandair/it-8762588.eml";

    public $reFrom = "icelandair";
    public $reBody = [
        'en' => ['Your booking nr', 'Check in'],
    ];
    public $reSubject = [
        'en' => 'Now you can check-in',
    ];
    public $lang = 'icelandair/it-8762588.eml';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'CheckIn' . ucfirst($this->lang),
        ];
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'icelandair')]")->length > 0) {
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
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $its = [];
        $xpath = "//text()[{$this->eq($this->t('FROM'))}]/ancestor::table[{$this->contains($this->t('Your booking nr'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Your booking nr'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            //			$it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(.,'Click here')]/ancestor::*[starts-with(normalize-space(.),'Are you')][1]/preceding::text()[string-length(normalize-space(.))>2][1]");
            $Passenger = $this->http->FindSingleNode("//text()[contains(.,'Click here')]/ancestor::td[starts-with(normalize-space(.),'Are you')][1]/preceding-sibling::td[normalize-space()][1]");

            if (!empty($Passenger)) {
                $it['Passengers'][] = $Passenger;
            }

            $seg = [];
            $seg['DepCode'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('FROM'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            $seg['DepName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('FROM'))}]/following::text()[normalize-space(.)!=''][2]", $root);
            $seg['ArrCode'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('TO'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            $seg['ArrName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('TO'))}]/following::text()[normalize-space(.)!=''][2]", $root);
            $arr = $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('FLIGHT'))}]/ancestor::tr[1][{$this->contains($this->t('DATE'))}]/following::tr[contains(.,':')][1]/descendant::td[count(descendant::td)=0 and normalize-space(.)!='']", $root);

            if (count($arr) !== 4) {
                $this->http->Log("wrong table format");

                continue;
            }
            $seg['FlightNumber'] = $arr[0];
            $date = strtotime($this->normalizeDate($arr[1]));
            $seg['DepDate'] = strtotime($arr[2], $date);
            $seg['ArrDate'] = strtotime($arr[3], $date);

            if ($seg['ArrDate'] < $seg['DepDate']) {
                $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
            }

            if ($this->http->FindSingleNode("//a[contains(@href, 'click.email.icelandair.is') and " . $this->contains("Your baggage allowance") . "]")) {
                $seg['AirlineName'] = "FI";
            }

            $it['TripSegments'][] = $seg;

            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\/(\d+)\/(\d+)$#',
        ];
        $out = [
            '$3-$2-$1',
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
