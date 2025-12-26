<?php

namespace AwardWallet\Engine\luxair\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, saudisrabianairlin/BoardingPass

class YourBoardingPassNonPdf extends \TAccountChecker
{
    public $mailFiles = "luxair/it-7524501.eml, luxair/it-8328185.eml, luxair/it-8625255.eml";
    public $reFrom = "@luxair.lu";
    public $detectSubject = [
        "en" => "Your Boarding Pass Confirmation",
        'Your Boarding Pass',
        'Your Email Confirmation',
        'Boarding Pass Confirmation',
        // fr
        'Carte d’embarquement',
    ];

    public $reBody2 = [
        "fr"=> "Numéro de Réservation:",
        "de"=> "Buchungsreferenz",
        "en"=> "Booking Reference",
    ];

    public static $dictionary = [
        "fr" => [
            'Numéro de Réservation:' => ['Numéro de Réservation:', 'Numéro de réservation:'],
            //			'Passager:' => '',
            //			'Vol:' => '',
            //			'De:' => '',
            //			'À:' => '',
        ],
        "de" => [
            'Numéro de Réservation:' => 'Buchungsreferenz',
            'Passager:'              => 'Passagier',
            'Vol:'                   => 'Flug',
            'De:'                    => 'Von:',
            'À:'                     => 'Nach:',
        ],
        "en" => [
            'Numéro de Réservation:' => ['Booking Reference', 'Booking Reference:'],
            'Passager:'              => ['Passenger', 'Passenger:'],
            'Vol:'                   => ['Flight', 'Flight:'],
            'De:'                    => 'From:',
            'À:'                     => 'To:',
        ],
    ];

    public $lang = "fr";

    private $providerCode;
    private static $detectsProvider = [
        'airtransat' => [
            'from'           => ['@airtransat.com'],
            //            'subjUniqueName' => [''],
            'bodyHtml'       => [
                'flying with Air Transat',
                'voyager avec Air Transat',
                'www.airtransat.com',
            ],
        ],
        'luxair' => [
            'from'           => ['@luxair.lu'],
            //            'subjUniqueName' => [''],
            'bodyHtml'       => [
                'Luxair would like',
                '//a[contains(@href, "www.luxair.lu")]',
            ],
        ],
        'kuwait' => [
            'from' => ['e-booking@kuwaitairways.com'],
            //            'subjUniqueName' => [],
            'bodyHtml' => ['www.kuwaitairways.com',
                'choosing Kuwait Airways',
                '//a[contains(@href, "www.kuwaitairways.com")]',
            ],
        ],

        'sata' => [
            'from' => ['no-reply@sata.pt'],
            // 'subjUniqueName' => [],
            'bodyHtml' => ['www.azoresairlines.pt', 'Grupo SATA',
                '//a[contains(@href, "www.azoresairlines.pt")]', ],
        ],
        //        '' => [
        //            'from' => [''],
        //            'subjUniqueName' => [],
        //            'bodyHtml' => [],
        //        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Numéro de Réservation:"));

        // Passengers
        $it['Passengers'] = [$this->nextText($this->t("Passager:"))];

        $xpath = "//text()[{$this->eq($this->t("Vol:"))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)#", $this->nextText($this->t("Vol:"), $root));

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->nextText($this->t("De:"), $root);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("De:")) . "]/ancestor::td[1][count(./descendant::text()[normalize-space(.)])=4]/descendant::text()[normalize-space(.)][3]",
                $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("De:")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]",
                $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->nextText($this->t("À:"), $root);

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("À:")) . "]/ancestor::td[1][count(./descendant::text()[normalize-space(.)])=4]/descendant::text()[normalize-space(.)][3]",
                $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("À:")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]",
                $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+#", $this->nextText($this->t("Vol:"), $root));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#^([A-Z]+)$#", $this->nextText($this->t("Vol:"), $root, 3));

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            if (!empty($detect['subjUniqueName']) && $this->containsText($headers['subject'], $detect['subjUniqueName']) === true
                || !empty($detect['from']) && $this->containsText($headers['from'], $detect['from']) === true
            ) {
                $this->providerCode = $code;

                foreach ($this->detectSubject as $dSubject) {
                    if (stripos($headers["subject"], $dSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detectsProvider as $code => $detect) {
            $detectedProvider = false;

            if (!empty($detect['bodyHtml'])) {
                foreach ($detect['bodyHtml'] as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && $this->http->XPath->query("//node()[{$this->contains($search)}]")->length > 0)
                    ) {
                        $this->providerCode = $code;
                        $detectedProvider = true;

                        break;
                    }
                }
            }

            if ($detectedProvider === false) {
                continue;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfs = $parser->searchAttachmentByName(".*.pdf");

        if (isset($pdfs[0])) {
            return null;
        }// pdf parse in YourBoardingPassPdf

        $this->http->FilterHTML = true;
        $this->http->setBody($parser->getHTMLBody());
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (stripos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        if (empty($this->providerCode)) {
            foreach (self::$detectsProvider as $code => $detect) {
                if (!empty($detect['subjUniqueName']) && $this->containsText($parser->getSubject(), $detect['subjUniqueName']) === true
                    || !empty($detect['from']) && $this->containsText($parser->getCleanFrom(), $detect['from']) === true
                ) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($detect['bodyHtml'])) {
                    foreach ($detect['bodyHtml'] as $search) {
                        if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                            || (stripos($search, '//') === false && $this->http->XPath->query("//node()[{$this->contains($search)}]")->length > 0)
                        ) {
                            $this->providerCode = $code;

                            break;
                        }
                    }
                }
            }
        }

        $result = [
            'providerCode'  => $this->providerCode,
            'emailType'     => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData'    => [
                'Itineraries' => $itineraries,
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProvider);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//         $this->logger->debug('$date in = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            // 18/07/2017 - 07:15
            "#^(\d+)/(\d+)/(\d{4})\s+-\s+(\d+:\d+)$#",
            // 27 JUN 2022 - 12:15
            "#^\s*(\d+)\s*([[:alpha:]]+)\s+(\d{4})\s+-\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//         $this->logger->debug('$date out = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
