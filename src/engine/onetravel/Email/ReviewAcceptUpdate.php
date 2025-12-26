<?php

namespace AwardWallet\Engine\onetravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ReviewAcceptUpdate extends \TAccountChecker
{
    public $mailFiles = "onetravel/it-11512203.eml";

    public $reFrom = "@onetravel.com";
    public $reBody = [
        'en' => ['The below itinerary is no longer valid', 'Continue to review changes'],
    ];
    public $reSubject = [
        'Review & Accept Updated Itinerary',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'onetravel.com')] | //img[contains(@src,'onetravel.com')]")->length > 0) {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Status'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('The below itinerary is'))}]",
            null, true, "#{$this->opt($this->t('The below itinerary is'))}\s+(.+?)(?:\.|$)#");
        $it['Passengers'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true,
            "#{$this->opt($this->t('Dear'))}\s+(.+?)(?:,|$)#");

        $xpath = "//text()[{$this->starts($this->t('Flight'))} and not({$this->contains($this->t('Duration'))})]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = implode("\n",
                $this->http->FindNodes("./td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+)\s+{$this->opt($this->t('Flight'))}\s+(\d+)(?:\s+(.+))?#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['Aircraft'] = $m[3];
                }
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]//text()[normalize-space(.)!=''][1]",
                $root);
            $seg['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]//text()[normalize-space(.)!=''][2]",
                $root);
            $node = implode("\n",
                $this->http->FindNodes("./following::tr[1]/td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                    $root));

            if (preg_match("#(\d+:\d+(?:\s*[ap]m)?)\s+\-\s+(.+)\s+(\d+:\d+(?:\s*[ap]m)?)\s+\-\s+(.+)#i", $node, $m)) {
                $dateDep = $this->normalizeDate($m[2]);
                $dateArr = $this->normalizeDate($m[4]);
                $seg['DepDate'] = strtotime($m[1], $dateDep);
                $seg['ArrDate'] = strtotime($m[3], $dateArr);
            }
            $node = implode("\n",
                $this->http->FindNodes("./following::tr[1]/td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='']",
                    $root));
            $seg['BookingClass'] = $this->re("#{$this->opt($this->t('Coach'))}\[([A-Z]{1,2})\]#", $node);

            if (preg_match("#(\d+)\s*stop#i", $node, $m)) {
                $seg['Stops'] = $m[1];
            } elseif (preg_match("#non[\- ]*stop#i", $node, $m)) {
                $seg['Stops'] = 0;
            }

            if (preg_match("#^\s*(\d+[hrs ]+\d+[mins]+)#im", $node, $m)) {
                $seg['Duration'] = $m[1];
            }
            $seg['Operator'] = trim($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[{$this->eq($this->t('Operated by'))}]/ancestor::td[1]",
                $root, true, "#{$this->opt($this->t('Operated by'))}\s+(.+)#"), " /");

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //15 Jul, Sun
            '#^(\d+)\s+(\w+),\s+(\w+)$#u',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];
        $outWeek = [
            '$3',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
