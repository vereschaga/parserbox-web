<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class MobileBoardingPass2016 extends \TAccountChecker
{
    public $mailFiles = "swissair/it-11828298.eml, swissair/it-11944238.eml";
    public $reFrom = "@notifications.swiss.com>";
    public $reSubject = [
        "fr" => "SWISS carte d'embarquement mobile",
        "it" => "SWISS carta d'imbarco mobile",
    ];
    public $reBody = ["swiss.com", "Boarding pass"];

    public $reBody2 = [
        "fr" => "SWISS vous souhaite un agréable vol",
        "it" => "SWISS le augura un piacevole volo",
    ];

    public static $dictionary = [
        "fr" => [
        ],
        "it" => [
            "Siège"        => "Posto",
            "Embarquement" => "Imbarco",
            "Statut"       => "Livello",
            "Nom:"         => "Nome:",
            "Vol:"         => "Volo:",
            "Date:"        => "Data:",
        ],
    ];

    public $lang;

    public function parseHtml($parse)
    {
        $itineraries = [];

        $condition1 = $this->http->XPath->query("//text()[{$this->starts($this->t('Siège'))}]/ancestor::tr[1][{$this->contains($this->t('Embarquement'))} and {$this->contains($this->t('Statut'))}]")->length > 0;
        $segments = $this->http->XPath->query("//text()[{$this->starts($this->t('Nom:'))}]/ancestor::tr[1]/following-sibling::tr[1][{$this->starts($this->t('Vol:'))}]/ancestor::table[1]");

        if ($condition1 && $segments->length > 0) {
            $root = $segments->item(0);

            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            // Passengers
            $it['Passengers'] = [$this->nextText($this->t("Nom:"), $root)];

            // TicketNumbers
            $it['TicketNumbers'] = [$this->nextText($this->t("E-Tkt:"), $root)];

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^[A-Z]{3}\s*\-\s*[A-Z]{3}\s*,\s*\w{2}(\d+)$#",
                $this->nextText($this->t("Vol:"), $root));

            // DepCode
            $itsegment['DepCode'] = $this->re("#^([A-Z]{3})\s*\-\s*[A-Z]{3}\s*,\s*\w{2}\d+$#",
                $this->nextText($this->t("Vol:"), $root));

            // DepDate
            $date = $this->normalizeDate($this->nextText($this->t("Date:"), $root));
            $itsegment['DepDate'] = EmailDateHelper::calculateDateRelative($date, $this, $parse);

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#^[A-Z]{3}\s*\-\s*([A-Z]{3})\s*,\s*\w{2}\d+$#",
                $this->nextText($this->t("Vol:"), $root));

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^[A-Z]{3}\s*\-\s*[A-Z]{3}\s*,\s*(\w{2})\d+$#",
                $this->nextText($this->t("Vol:"), $root));

            // Seats
            $itsegment['Seats'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Siège'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]",
                null, true, "#(\d+\w)#");

            $it['TripSegments'][] = $itsegment;

            $itineraries[] = $it;
        }

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ((strpos($body, $this->reBody[0]) === false) || (strpos($body, $this->reBody[1]) === false)) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->lang = "";

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->http->Log("can't determine the language");

            return null;
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml($parser),
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s*(\D+)$#", //30July
        ];
        $out = [
            "$1 $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]",
            $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
