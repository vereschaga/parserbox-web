<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It5033978 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "singaporeair/it-10509230.eml, singaporeair/it-11.eml, singaporeair/it-1472993.eml, singaporeair/it-1474064.eml, singaporeair/it-1475010.eml, singaporeair/it-1476692.eml, singaporeair/it-1503488.eml, singaporeair/it-1513397.eml, singaporeair/it-1518470.eml, singaporeair/it-1521835.eml, singaporeair/it-1526220.eml, singaporeair/it-1530016.eml, singaporeair/it-1559591.eml, singaporeair/it-1586354.eml, singaporeair/it-1587130.eml, singaporeair/it-1619989.eml, singaporeair/it-1654172.eml, singaporeair/it-1671591.eml, singaporeair/it-1707799.eml, singaporeair/it-1707802.eml, singaporeair/it-1964065.eml, singaporeair/it-1973376.eml, singaporeair/it-2064683.eml, singaporeair/it-2064908.eml, singaporeair/it-2125291.eml, singaporeair/it-2301019.eml, singaporeair/it-2996272.eml, singaporeair/it-3.eml, singaporeair/it-3915613.eml, singaporeair/it-4415250.eml, singaporeair/it-4428769.eml, singaporeair/it-4459041.eml, singaporeair/it-4614656.eml, singaporeair/it-4933820.eml, singaporeair/it-5033978.eml, singaporeair/it-5071095.eml, singaporeair/it-5071097.eml, singaporeair/it-5096126.eml, singaporeair/it-5100957.eml, singaporeair/it-5628981.eml, singaporeair/it-5700549.eml, singaporeair/it-6119291.eml, singaporeair/it-6174818.eml, singaporeair/it-6232714.eml, singaporeair/it-6566219.eml, singaporeair/it-6566224.eml, singaporeair/it-6805587.eml";

    public $reFrom = "booking@singaporeair.com.sg";
    public $reSubject = [
        "eall" => "Check-in confirmation",
        "all2" => "Share check-in confirmation",
        "all3" => "Shared itinerary from Singaporeair.com",
        "all4" => "Redemption Booking Confirmation",
        "all5" => "Booking confirmation",
        "en"   => "SQ Mobile Check-in Confirmation",
    ];
    public $reBody = ['singaporeair.com', 'silkair.com'];
    public $reBody2 = [
        "en" => "Arrives",
        "fr" => "Départ",
        "zh" => ["出發"],
        "ko" => "출발",
        "de" => "Abflug",
        "es" => "Salida",
        "pt" => "Saída",
        "ru" => "Вылет",
        "ja" => ["出発", 'お客様の旅程'],
    ];

    public static $dictionary = [
        "en" => [],
        "fr" => [
            "confirm your online check-in"           => "confirm your online check-in",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "Référence de la réservation:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "Sièges sélectionnés",
            "Passengers"                             => "Passagers",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "Départ",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
        ],
        "zh" => [
            "confirm your online check-in"           => "NOTTRANSLATED",
            "Shared itinerary from Singaporeair.com" => "Shared itinerary from Singaporeair.com",
            "Booking reference:"                     => "訂位代號:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "NOTTRANSLATED",
            "Passengers"                             => "搭機旅客",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "出發",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
        ],
        "ko" => [
            "confirm your online check-in"           => "NOTTRANSLATED",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "예약번호:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "NOTTRANSLATED",
            "Passengers"                             => "승객",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "출발",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
        ],
        "de" => [
            "confirm your online check-in"           => "NOTTRANSLATED",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "Buchungsreferenz:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "NOTTRANSLATED",
            "Passengers"                             => "Passagiere",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "Abflug",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
        ],
        "es" => [
            "confirm your online check-in"           => "confirm your online check-in",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "Referencia de reserva:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "NOTTRANSLATED",
            "Passengers"                             => "Pasajeros",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "Salida",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
        ],
        "pt" => [
            "confirm your online check-in"           => "NOTTRANSLATED",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "Código de reserva:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "NOTTRANSLATED",
            "Passengers"                             => "Passageiros",
            "Total Cost"                             => "Amount paid",
            "Departs"                                => "Saída",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
        ],
        "ru" => [
            "confirm your online check-in"           => "confirm your online check-in",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "Код бронирования:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "Выбранное место",
            "Passengers"                             => "Пассажиры",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "Вылет",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
            'stop'                                   => 'останов',
        ],
        "ja" => [
            "confirm your online check-in"           => "NOTTRANSLATED",
            "Shared itinerary from Singaporeair.com" => "NOTTRANSLATED",
            "Booking reference:"                     => "予約番号:",
            "Itinerary Share -"                      => "NOTTRANSLATED",
            "Seats selected"                         => "選択された座席",
            "Passengers"                             => "搭乗者",
            "Total Cost"                             => "Total Cost",
            "Departs"                                => "出発",
            "Operated by"                            => "NOTTRANSLATED",
            "Number of stops:"                       => "NOTTRANSLATED",
            'stop'                                   => 'NOTTRANSLATED',
        ],
    ];

    public $lang = "";
    public $subjConfNo = "";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        if (
            strpos($this->http->Response['body'], $this->t('confirm your online check-in')) !== false
            || strpos($this->http->Response['body'], $this->t('confirm your check-in')) !== false
            || strpos($this->http->Response['body'], $this->t('Shared itinerary from Singaporeair.com')) !== false
            || $this->http->FindSingleNode("//text()[normalize-space(.)='Itinerary Share']")
        ) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        } else {
            if (!($it['RecordLocator'] = $this->nextText($this->t("Booking reference:")))) {
                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Itinerary Share -')]", null, true, "#\s+-\s+(\w+)#");
            }
        }

        if ((!$it['RecordLocator'] || $it['RecordLocator'] === CONFNO_UNKNOWN) && !empty($this->subjConfNo)) {
            $it['RecordLocator'] = $this->subjConfNo;
        }

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique(
            array_merge(
                $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Seats selected") . "']/ancestor::table[1]//tr[normalize-space(./td[6])]/td[6]/descendant::text()[normalize-space(.)]", null, "#(.*?)\s+-#"),
                $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passengers") . "']/ancestor::tr[1]/following-sibling::tr")
            )
        );

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("(//text()[normalize-space(.)='" . $this->t("Total Cost") . "'])[last()]/ancestor::td[1]/following-sibling::td[2]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='" . $this->t("Total Cost") . "'])[last()]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($it['Currency'])) {
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("(//text()[normalize-space(.)='" . $this->t("Total Cost") . "'])[last()]/ancestor::tr[1]/following-sibling::tr[1]", null, false, "#[A-Z]{3}\s+(.+)$#"));
            $it['Currency'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='" . $this->t("Total Cost") . "'])[last()]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#([A-Z]{3})#");
        }
        $it = array_filter($it);
        // Tax
        // SpentAwards
        $node = $this->http->FindSingleNode("(//text()[normalize-space(.)='{$this->t("Total Cost")}'])[1]/ancestor::tr[1]/following-sibling::tr[1][contains(.,'Miles')]");

        if (!empty($node)) {
            $it['SpentAwards'] = $node;
        }
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        //		$xpath = "//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::table[1]//tr[normalize-space(./td[1]) and normalize-space(./td[4]) and not(" . $this->contains($this->t("Departs")) . ")]";
        $xpath = "//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::table[1]/descendant::tr[normalize-space(descendant::td[1]) and normalize-space(descendant::td[4]) and not(" . $this->contains($this->t("Departs")) . ")]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[1]//tr[2]/td[1]", $root)));

            if (isset($lastdate) && $lastdate > $date) {
                $date = $lastdate;
            }

            if (!$date) { //for emails like it-5628981.eml
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[last()]", $root)));
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s*(\d+)$#");

            $dep = $this->http->FindSingleNode("(./td[2]//*[(name()='strong' or name()='b') and normalize-space(.)])[1]", $root);

            // San Francisco FGH
            if (preg_match('/^(.*?)\s*([A-Z]{3})?\s*$/', $dep, $matches)) {
                if (!empty($matches[1])) {
                    $itsegment['DepName'] = $matches[1];
                }
                $itsegment['DepCode'] = empty($matches[2]) ? TRIP_CODE_UNKNOWN : $matches[2];
            }

            // DepDate
            if (!($time = $this->http->FindSingleNode("(./td[2]//*[(name()='strong' or name()='b') and normalize-space(.)])[1]/following::text()[normalize-space(.)][1][contains(translate(., '1234567890', 'dddddddddd'),  'd:dd')]", $root))) {
                $time = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root);
            }

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root);
            }
            $itsegment['DepDate'] = strtotime($this->normalizeDate($time), $date);

            $arr = $this->http->FindSingleNode("(./td[3]//*[(name()='strong' or name()='b') and normalize-space(.)])[1]", $root);
            // San Francisco FGH
            if (preg_match('/^(.*?)\s*([A-Z]{3})?\s*$/', $arr, $matches)) {
                if (!empty($matches[1])) {
                    $itsegment['ArrName'] = $matches[1];
                }
                $itsegment['ArrCode'] = empty($matches[2]) ? TRIP_CODE_UNKNOWN : $matches[2];
            }

            // ArrDate
            if (!($time = $this->http->FindSingleNode("(./td[3]//*[(name()='strong' or name()='b') and normalize-space(.)])[1]/following::text()[normalize-space(.)][1][contains(translate(., '1234567890', 'dddddddddd'),  'd:dd')]", $root))) {
                $time = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]/descendant::text()[normalize-space(.)][1]", $root);
            }

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root);
            }
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($time), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }
            $lastdate = $itsegment['ArrDate'];

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s*\d+$#");

            // Operator
            $itsegment['Operator'] = trim($this->http->FindSingleNode("./td[1]//text()[contains(., '" . $this->t("Operated by") . "')]", $root, true, "#" . $this->t("Operated by") . "\s+(.+)#"), ": ");

            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[5]", $root, null, "#(.*?)(\s*\(\w\)|$)#");

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[5]", $root, null, "#\((\w)\)#");

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            if (!($itsegment['Stops'] = $this->re("#" . $this->t("Number of stops:") . "\s+(\d+)#", $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root)))) {
                $itsegment['Stops'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root);
            }

            if (stripos($itsegment['Stops'], $this->t('stop')) === false && !preg_match('#\d+#', $itsegment['Stops'])) { //for emails like it-5628981.eml
                $node = $itsegment['Stops'];
                $itsegment['Stops'] = null;

                if (preg_match('#(.*?)(?:\s*\((\w)\)|$)#', $node, $m)) {
                    $itsegment['Cabin'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $itsegment['BookingClass'] = $m[2];
                    }
                }
            }

            if (isset($it['Passengers']) && ($cnt = count($it['Passengers'])) > 0 && $this->http->XPath->query("./ancestor::table[2]/descendant::img[contains(@alt,'Passenger Icon 1')][1]/ancestor::tr[1]", $root)->length > 0) {
                $num = $this->http->XPath->query("./preceding-sibling::tr", $root)->length + 1;
                $itsegment['Seats'] = $this->http->FindSingleNode("./following::img[contains(@alt,'Passenger Icon 1')][{$num}]/ancestor::td[1]/following::td[2]", $root, true, "#^\d+[A-Z]#i");

                if ($cnt > 1) {
                    $node = $this->http->FindNodes("./following::img[contains(@alt,'Passenger Icon 1')][{$num}]/ancestor::tr[1]/following::img[contains(@alt,'Passenger Icon')][position()<{$cnt}]/ancestor::td[1]/following::td[2]", $root, "#^\d+[A-Z]#i");

                    foreach ($node as $s) {
                        $itsegment['Seats'] .= ', ' . $s;
                    }
                }
            }

            //differents combinations for checkout Codes etc.
            if (isset($itsegment['DepName']) && isset($itsegment['ArrName']) && !$itsegment['DepName'] && !$itsegment['ArrName']) {
                $itsegment['DepCode'] = $this->http->FindSingleNode("./preceding::table[1]", $root, true, "#\(([A-Z]{3})\)\s+to\s+\([A-Z]{3}\)#");
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./preceding::table[1]", $root, true, "#\([A-Z]{3}\)\s+to\s+\(([A-Z]{3})\)#");
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root)), $date);
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root)), $date);
            }

            if ($itsegment['DepCode'] == TRIP_CODE_UNKNOWN && $itsegment['ArrCode'] == TRIP_CODE_UNKNOWN) {
                $itsegment['DepCode'] = $this->http->FindSingleNode("./preceding::table[1]", $root, true, "#\(([A-Z]{3})\)\s+to\s+\([A-Z]{3}\)#");
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./preceding::table[1]", $root, true, "#\([A-Z]{3}\)\s+to\s+\(([A-Z]{3})\)#");

                if (!$itsegment['DepCode'] && !$itsegment['ArrCode'] && isset($itsegment['DepName']) && isset($itsegment['ArrName'])) {
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::table[1]", $root, true, "#{$itsegment['DepName']}\s*\(([A-Z]{3})\)\s+to\s+#");
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::table[1]", $root, true, "#\s*\([A-Z]{3}\)\s+to\s+{$itsegment['ArrName']}\s*\(([A-Z]{3})\)#");
                }

                if (!$itsegment['DepCode'] && !$itsegment['ArrCode'] && isset($itsegment['DepName']) && isset($itsegment['ArrName'])) {
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./ancestor::table[1]", $root, true, "#{$itsegment['DepName']}\s*\(([A-Z]{3})\)\s+to\s+#");
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./ancestor::table[1]", $root, true, "#\s*\([A-Z]{3}\)\s+to\s+{$itsegment['ArrName']}\s*\(([A-Z]{3})\)#");
                }

                if (!$itsegment['DepCode']) {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (!$itsegment['ArrCode']) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            if (!empty($itsegment['FlightNumber'])) {//like 1513397 (wrong segmented)
                $it['TripSegments'][] = $itsegment;
            }
        }
        $itineraries[] = $it;
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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody[0]) === false && strpos($body, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && stripos($body, $re) !== false) {
                return true;
            }

            if (is_array($re)) {
                foreach ($re as $item) {
                    if (stripos($body, $item) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->emailDate = strtotime($parser->getHeader('date'));

        if (preg_match("#(?:Check-in\s+Confirmation\s+|from Singaporeair.com)\s*-?\s*([A-Z\d]{5,})#i", $parser->getSubject(), $m)) {
            $this->subjConfNo = $m[1];
        }

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->SetBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $lang => $re) {
            $w = (array) $re;

            foreach ($w as $item) {
                if (stripos($body, $item) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ReservationsAir' . ucfirst($this->lang),
            'parsedData' => [
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

    private function contains($field, $dm = '.')
    {
        $w = (array) $field;

        return implode(' or ', array_map(function ($s) use ($dm) {
            return "contains(normalize-space({$dm}),'{$s}')";
        }, $w));
    }

    private function eq($field, $dm = '.')
    {
        $w = (array) $field;

        return implode(' or ', array_map(function ($s) use ($dm) {
            return "normalize-space({$dm})='{$s}'";
        }, $w));
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        $check = $this->re("#(\d{4})#", $str) ? true : false;
        $year = date("Y", $this->date);
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s+[AP]M)#", //Sep 20, 2015, 03:30 PM
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4}),?\s+(\d+:\d+):00\s+([AP]M)#", //Sep 20, 2015 03:30:00 PM

            "#^(\d+:\d+\s+[AP]M)\((\d+)([^\d\s]+)\)$#",
            "#^(\d+:\d+\s+[AP]M)（(\d+)([^\d\s]+)）$#", //korean (

            // bad [ap]m times
            "#(1[3456789]:\d+)\s+[AP]M#",
            "#(2\d:\d+)\s+[AP]M#",
            "#(00:\d+)\s+[AP]M#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4 $5",
            "$2 $3 $year, $1",
            "$2 $3 $year, $1",

            // bad [ap]m times
            "$1",
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (!empty(trim($str)) && preg_match("#[^\d\s-\./:APM]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        if ($year == 1970 && $check) {
            $this->date = strtotime($str);
        }

        if (strtotime($str) < $this->date) {
            $str = preg_replace("#\d{4}#", $year + 1, $str);
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
        return (float) str_replace(" ", "", str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s)));
    }
}
