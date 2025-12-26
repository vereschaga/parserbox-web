<?php

namespace AwardWallet\Engine\wideroe\Email;

use AwardWallet\Engine\MonthTranslate;

class Reiseinformasjon extends \TAccountChecker
{
    public $mailFiles = "wideroe/it-12018713.eml, wideroe/it-13442327.eml";

    public $reFrom = [
        'wias.no',
        'wideroe.no',
    ];
    public $reBody = [
        'no' => ['Informasjon om flyvning', 'Bestillingsreferanse'],
    ];
    public $reSubject = [
        'no' => 'Reiseinformasjon  ref:',
    ];
    public $lang = '';
    public $subject;
    public static $dict = [
        'no' => [
            //			"Bestillingsreferanse:" => "",
            //			"Passasjer(er):" => "",
            //			"Utreise" => "",
            //			"Retur" => "",
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $this->AssignLang();
        $its = $this->parseEmail();

        $result = ['Itineraries' => $its];

        return [
            'emailType'  => 'Reiseinformasjon' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reFrom as $reFrom) {
            if (stripos($body, $reFrom) !== false) {
                return $this->AssignLang();
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach ($this->reFrom as $reFrom) {
            if (stripos($headers["from"], $reFrom) !== false) {
                $find = true;
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail()
    {
        $its = [];
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Bestillingsreferanse:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#\s*([A-Z\d]+)#");

        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Passasjer(er):")) . "][1]/following::table[1]//tr/td[1]");

        $xpath = "//text()[" . $this->eq($this->t("Utreise")) . "]/ancestor::*[" . $this->contains($this->t("Retur")) . "][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("#(.+)\s*-(.+)\s*(?:" . $this->opt($this->t("Utreise")) . ")\s+(.+)\s+(?:" . $this->opt($this->t("Retur")) . ")\s+(.+)#", $text, $m)) {
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                $seg['DepName'] = trim($m[1]);
                $seg['ArrName'] = trim($m[2]);

                if ($nodes->length == 1) {
                    if (preg_match("#/\s*([A-Z]{3})\s*-\s*([A-Z]{3})\b#", $this->subject, $mat)) {
                        $seg['DepCode'] = $mat[1];
                        $seg['ArrCode'] = $mat[2];
                    } else {
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                }
                $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[4]));

                if (strpos($this->http->Response['body'], 'Takk for at du bestilte reisen på wideroe.no') !== false) {
                    $seg['AirlineName'] = "WF";
                }
            }
            $it['TripSegments'][] = $seg;
        }

        $its[] = $it;

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*[^\d\s]+\s+(\d+)[\s\.]+(\w+)[\.\s]+(\d{4})\s+(\d+:\d+)\s*$#u', // Tor 06. okt 2016 14:25
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
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
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $reBody[0] . '")]')->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "{$text} = \"{$s}\""; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field)) . ')';
    }

    private function amount($s)
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }
}
