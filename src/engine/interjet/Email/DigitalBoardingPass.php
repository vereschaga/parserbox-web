<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;

class DigitalBoardingPass extends \TAccountChecker
{
    public $mailFiles = "interjet/it-12314136.eml";

    public $reFrom = '@interjet.com';
    public $reProvider = 'interjet.com';
    public $reBody = '@interjet.com';
    public $reBody2 = [
        "en" => "To enjoy the best flight experience",
        "es" => "Para mejorar la experiencia de vuelo",
    ];
    public $reSubject = [
        'Tu pase de abordar digital para tu vuelo',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
            //			"Flight number" => "",
            //			"Departure date" => "",
            //			"Seats" => "",
        ],
        'es' => [
            "Flight number"  => "Vuelo",
            "Departure date" => "Fecha de vuelo",
            "Seats"          => "Asientos",
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $its = $this->parseEmail();

        return [
            'emailType'  => "DigitalBoardingPass" . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
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

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $lang => $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function normalizeDate($dateStr)
    {
        //		$in = [
        //			"#^\s*[a-z]+\s*(\d{1,2})\s*([a-z]+)\s*(\d{2})\s*(?:at|om)\s*(\d+:\d+)\s*$#ui",
        //		];
        //		$out = [
        //			"$1 $2 20$3 $4",
        //		];
        //		$dateStr = preg_replace($in, $out, $dateStr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $dateStr, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $dateStr = str_replace($m[1], $en, $dateStr);
            }
        }

        return $dateStr;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $seg = [];
        $seg['AirlineName'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Flight number")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{2})\s*\d{1,5}\s*$#");
        $seg['FlightNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Flight number")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})\s*$#");
        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Departure date")) . "]/following::text()[normalize-space()][1]"));

        $node = implode("\n", $this->http->FindNodes("//img[contains(@src, 'mailings/icn-avion.png')]/ancestor::*[1]/preceding-sibling::*[1]//text()"));

        if (preg_match("#^\s*([A-Z]{3})\s+(.+)\s+(\d+:\d+[ ]*[APM]{2})\s*$#", $node, $m)) {
            $seg['DepCode'] = $m[1];
            $seg['DepName'] = trim($m[2]);

            if (!empty($date)) {
                $seg['DepDate'] = strtotime($date . ' ' . $m[3]);
            }
        }
        $node = implode("\n", $this->http->FindNodes("//img[contains(@src, 'mailings/icn-avion.png')]/ancestor::*[1]/following-sibling::*[1]//text()"));

        if (preg_match("#^\s*([A-Z]{3})\s+(.+)\s+(\d+:\d+[ ]*[APM]{2})\s*$#", $node, $m)) {
            $seg['ArrCode'] = $m[1];
            $seg['ArrName'] = trim($m[2]);

            if (!empty($date)) {
                $seg['ArrDate'] = strtotime($date . ' ' . $m[3]);
            }
        }
        $seats = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Seats")) . "]/following::text()[normalize-space()][1]");

        if (preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seats, $m)) {
            $seg['Seats'] = $m[1];
        }

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
