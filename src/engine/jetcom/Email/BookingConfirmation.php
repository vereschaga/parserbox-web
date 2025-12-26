<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-11546382.eml";

    public $reFrom = "jet2.com";
    public $reBody = [
        'en' => ['Online check-in', 'Luggage'],
    ];
    public $reSubjectRegExp = [
        '#Jet2\.com\s+\-\s+[A-Z\d]+\s+Booking\s+Confirmation#i',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record locator' => 'Your Confirmation Number',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
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
        if ($this->http->XPath->query("//img[contains(@src, 'jet2.com')] | //a[contains(@href, 'jet2.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubjectRegExp)) {
            foreach ($this->reSubjectRegExp as $reSubject) {
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::table[1]/descendant::text()[{$this->eq($this->t('Luggage'))}]/preceding::text()[normalize-space(.)!=''][1]");

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Total for your booking'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//text()[{$this->starts($this->t('Departs'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+?)\s+([A-Z]{3})\s+to\s+(.+?)\s+([A-Z]{3})#", $node, $m)) {
                if (preg_match("#(.+?)\s+{$this->opt($this->t('Terminal'))}\s+(.+)#i", $m[1], $v)) {
                    $seg['DepName'] = $v[1];
                    $seg['DepartureTerminal'] = $v[2];
                } else {
                    $seg['DepName'] = $m[1];
                }
                $seg['DepCode'] = $m[2];

                if (preg_match("#(.+?)\s+{$this->opt($this->t('Terminal'))}\s+(.+)#i", $m[3], $v)) {
                    $seg['ArrName'] = $v[1];
                    $seg['ArrivalTerminal'] = $v[2];
                } else {
                    $seg['ArrName'] = $m[3];
                }
                $seg['ArrCode'] = $m[4];
            }
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][2]", $root);
            $seg['DepDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Departs'))}[:\s]+(.+)#", $node));

            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][3]", $root);
            $seg['ArrDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Arrives'))}[:\s]+(.+)#", $node));

            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][4]", $root);
            $seg['Duration'] = $this->re("#{$this->opt($this->t('In the air'))}[:\s]+(.+)#", $node);

            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][5]", $root);

            if (preg_match("#{$this->opt($this->t('Flight'))}[\s:]+([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $wayText = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root);
            $node = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::table[1]/descendant::text()[{$this->eq($this->t('Seats'))}]/following::text()[contains(.,'{$wayText}')][1]/following::text()[normalize-space(.)!=''][1]", null, "#\b(\d+\w)\b#");

            if (!empty($node)) {
                $seg['Seats'] = $node;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        //		$year = date('Y', $this->date);
        $in = [
            //Sun 25 Feb 2018 at 14:30
            '#^\s*\w+\s+(\d+)\s+(\w+)\s+(\d{4})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $outWeek = [
            '',
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
