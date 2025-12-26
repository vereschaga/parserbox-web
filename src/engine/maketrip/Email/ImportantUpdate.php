<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

class ImportantUpdate extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-11962379.eml";

    public $reFrom = "makemytrip.com";
    public $reBody = [
        'en' => ['Please note the change', 'IMPORTANT NOTIFICATION'],
    ];
    public $reSubject = [
        'Important Update for MakeMyTrip Booking',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
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
        if ($this->http->XPath->query("//img[@alt='mmt_logo' or contains(@src,'makemytrip.com')] | //a[contains(@href,'makemytrip.com')]")->length > 0) {
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
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID'))}]", null, true,
            "#{$this->opt($this->t('Booking ID'))}[:\s]+(\w+)#");

        if (empty($it['TripNumber'])) {
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('MakeMyTrip Booking ID'))}]",
                null, true, "#{$this->opt($this->t('MakeMyTrip Booking ID'))}\s+(\w+)#");
        }

        //Please note the change in arrival terminal for your booking. Your Indigo flight 6E-475 from Bangalore (BLR) to Delhi (DEL) on 06 Apr 2018 will now arrive at Terminal 2 (earlier known as Haj Terminal) which is located near Terminal T3, Indira Gandhi International Airport, New Delhi.
        $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Please note the change'))}]");
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        if (preg_match("#{$this->opt($this->t('flight'))}\s+([A-Z\d]{2})[\s\-]*(\d+)#", $text, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }

        if (preg_match("#{$this->opt($this->t('from'))}\s+(.*?)\s*\(([A-Z]{3})\)\s+{$this->opt($this->t('to'))}\s+(.*?)\s*\(([A-Z]{3})\)#",
            $text, $m)) {
            //				$seg['DepName'] = $m[1];
            $seg['DepCode'] = $m[2];
            //				$seg['ArrName'] = $m[3];
            $seg['ArrCode'] = $m[4];
        }

        if (preg_match("#{$this->opt($this->t('on'))}\s+(\d+\s+\D+\s+\d{4})#", $text, $m)) {
            $seg['DepDate'] = $this->normalizeDate($m[1]);
            $seg['ArrDate'] = MISSING_DATE;
        }

        if (preg_match("#{$this->opt($this->t('will now arrive at Terminal'))}\s+(\w+)#", $text, $m)) {
            $seg['ArrivalTerminal'] = $m[1];
        }

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+\s+\D+\s+\d{4})$#',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
