<?php

namespace AwardWallet\Engine\silvercar\Email;

use AwardWallet\Engine\MonthTranslate;

class ChauffeurService extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "silverstar-cars.co.uk";
    public $reBody = [
        'en' => ['SILVERSTAR CHAUFFEUR SERVICE LTD BOOKING CONFIRMATION', 'Job ID'],
    ];
    public $reSubject = [
        'SilverStar Chauffeur Service Ltd booking confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty(($body))) {
            $body = $parser->getPlainBody();
        }

        $this->AssignLang($body);

        $its = $this->parseEmail($body);
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'SilverStar')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
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

    private function parseEmail($text)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Job ID'))}[\s:\#]+(\d+)#", $text);
        $it['Passengers'][] = $this->re("#{$this->opt($this->t(''))}Passenger name[\s:]+(.+)\s+Account{$this->opt($this->t(''))}#",
            $text);
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

        $seg = [];
        $date = $this->normalizeDate($this->re("#{$this->opt($this->t(''))}Pick up date[\s:]+(.+)\s+{$this->opt($this->t('Pick up time'))}#",
            $text));
        $time = $this->re("#{$this->opt($this->t('Pick up time'))}[\s:]+(\d+:\d+(?:\s*[ap]m)?)\s+{$this->opt($this->t('Pick up place'))}#i",
            $text);
        $seg['DepDate'] = strtotime($time, $date);
        $seg['ArrDate'] = MISSING_DATE;
        $seg['DepName'] = $this->re("#{$this->opt($this->t('Pick up place'))}[\s:]+(.+)\s+{$this->opt($this->t('Destination'))}#",
            $text);
        $seg['ArrName'] = $this->re("#{$this->opt($this->t('Destination'))}[\s:]+(.+)\s+{$this->opt($this->t('Details'))}#",
            $text);

        if (count(array_filter(array_map('trim', $seg))) === 4) {
            $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        } else {
            $this->http->Log('Invalid array parsed');

            return null;
        }
        $node = $this->re("#{$this->opt($this->t('Details'))}[\s:]+(.+)\n#", $text);

        if (preg_match("#(.+?)\s+{$this->opt($this->t('TO'))}\s+(.+)#", $node, $m)) {
            if (preg_match("#\b([A-Z]{3})\b#", $seg['DepName'], $v)) {
                if ($t = $this->re("#\bT(\w)\b#", $m[1])) {
                    $seg['DepartureTerminal'] = $t;
                } elseif ($t = $this->re("#\bT(\w)\b#", $seg['DepName'])) {
                    $seg['DepartureTerminal'] = $t;
                }
                $seg['DepCode'] = $v[1];
                $seg['DepName'] = trim($m[1], "*");
            } else {
                $seg['DepName'] .= ' - ' . $m[1];
            }

            if (preg_match("#\b([A-Z]{3})\b#", $seg['ArrName'], $v)) {
                if ($t = $this->re("#\bT(\w)\b#", $m[2])) {
                    $seg['ArrivalTerminal'] = $t;
                } elseif ($t = $this->re("#\bT(\w)\b#", $seg['ArrName'])) {
                    $seg['ArrivalTerminal'] = $t;
                }
                $seg['ArrCode'] = $v[1];
                $seg['ArrName'] = trim($m[2], "*");
            } else {
                $seg['ArrName'] .= ' - ' . $m[2];
            }
        }

        $it['TripSegments'] = [$seg];

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sun 11 Mar 2018
            '#^\s*\w+\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#',
        ];
        $out = [
            '$1 $2 $3',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
