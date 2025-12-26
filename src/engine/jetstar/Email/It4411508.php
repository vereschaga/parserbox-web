<?php

namespace AwardWallet\Engine\jetstar\Email;

class It4411508 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public static $dictionary = [
        "en" => [
            "Booking Reference" => ["Booking Reference", "Booking reference"],
            "Booking Date"      => ["Booking Date", "Booking date"],
            "Flight Duration"   => ["Flight Duration", "Flight duration"],
        ],
        "ja" => [
            "Booking Reference" => "ご予約番号",
            "Passenger"         => "搭乗者",
            "Departing"         => "出発",
            "Flight Duration"   => "飛行時間",
            "Booking Date"      => "ご予約日:",
            "Payment of"        => "支払い",
        ],
        "zh" => [
            "Booking Reference" => ["預訂參考號", "订票参考"],
            "Passenger"         => ["乘客", "Passenger"],
            "Departing"         => ["出發", "出发"],
            "Flight Duration"   => ["飛行時間", "飞行时间"],
            "Booking Date"      => "預訂日期:",
            "Payment of"        => "已收到",
        ],
        "ko" => [
            "Booking Reference" => "예약 번호",
            "Passenger"         => "탑승객",
            "Departing"         => "출발",
            "Flight Duration"   => "비행 시간",
            "Booking Date"      => "여정 안내서 발행일:",
            "Payment of"        => "결제금액",
        ],
    ];
    public $mailFiles = "jetstar/it-1.eml, jetstar/it-10930319.eml, jetstar/it-11136566.eml, jetstar/it-12643778.eml, jetstar/it-12644129.eml, jetstar/it-13028629.eml, jetstar/it-1589154.eml, jetstar/it-1596917.eml, jetstar/it-1596918.eml, jetstar/it-2199371.eml, jetstar/it-2734433.eml, jetstar/it-2810710.eml, jetstar/it-3.eml, jetstar/it-4351863.eml, jetstar/it-4411508.eml, jetstar/it-4419299.eml, jetstar/it-4616828.eml, jetstar/it-4660099.eml, jetstar/it-5.eml, jetstar/it-5148964.eml, jetstar/it-5213417.eml, jetstar/it-5921262.eml, jetstar/it-6810735.eml, jetstar/it-8033646.eml, jetstar/it-8841525.eml, jetstar/it-8841531.eml, jetstar/it-9848843.eml";
    public $lang = "en";
    private $reFrom = "noreplyitineraries@jetstar.com";
    private $reSubject = [
        "en" => "Jetstar Flight Itinerary for",
        "ja" => "ジェットスター旅程表",
        "zh" => "Jetstar Flight Itinerary",
    ];
    private $reBody = 'Jetstar';
    private $reBody2 = [
        "en"  => "Departing",
        "ja"  => "出発",
        "zh"  => "出發",
        "zh2" => "出发",
        "ko"  => "출발",
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
        $text = $parser->getHtmlBody();
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--_\d{3}_.*#", "\n", $texts);
            $text = '';
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                $posEnd = stripos($texts, "Content-Type: ", $posBegin);
                $block = substr($texts, $posBegin, $posEnd - $posBegin);
                $posEnd = strripos($block, "\n\n");
                $block = substr($texts, $posBegin, $posEnd);

                if (preg_match("#: base64#is", $str)) {
                    $block = trim($block);
                    $block = htmlspecialchars_decode(base64_decode($block));

                    if (($blockBegin = stripos($block, '<blockquote')) !== false) {
                        $blockEnd = strripos($block, '</blockquote>', $blockBegin) + strlen('</blockquote>');
                        $block = substr($block, $blockBegin, $blockEnd - $blockBegin);
                    }
                    $text .= $block;
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $text .= quoted_printable_decode($block);
                } else {
                    $text .= htmlspecialchars_decode($block);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }

            if (stripos($text, 'charset=utf-8">') !== false) {
                $this->http->FilterHTML = true; // need for some emails!
                $this->http->SetBody($text);
            } else {
                $this->http->SetEmailBody($text, true);
            }
        }

        if (stripos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (mb_strpos(html_entity_decode($text), $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $text = $parser->getHtmlBody();
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--_\d{3}_.*#", "\n", $texts);
            $text = '';
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                $posEnd = stripos($texts, "Content-Type: ", $posBegin);
                $block = substr($texts, $posBegin, $posEnd - $posBegin);
                $posEnd = strripos($block, "\n\n");
                $block = substr($texts, $posBegin, $posEnd);

                if (preg_match("#: base64#is", $str)) {
                    $block = trim($block);
                    $block = htmlspecialchars_decode(base64_decode($block));

                    if (($blockBegin = stripos($block, '<blockquote')) !== false) {
                        $blockEnd = strripos($block, '</blockquote>', $blockBegin) + strlen('</blockquote>');
                        $block = substr($block, $blockBegin, $blockEnd - $blockBegin);
                    }
                    $text .= $block;
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $text .= quoted_printable_decode($block);
                } else {
                    $text .= htmlspecialchars_decode($block);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }

            if (stripos($text, 'charset=utf-8">') !== false) {
                $this->http->FilterHTML = true; // need for some emails!
                $this->http->SetBody($text);
            } else {
                $this->http->SetEmailBody($text, true);
            }
        }

        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            if (mb_strpos(html_entity_decode($this->http->Response["body"]), $re, 0, 'UTF-8') !== false) {
                $this->lang = trim($lang, '1234567890');

                break;
            }
        }
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'FlItinerary' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function parseSegmentFormat1($nodes)
    {
        $segments = [];

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::*[name()='strong' or name()='b'][1]", $root));

            if (strlen($date) < 6) {
                $date = $this->normalizeDate($this->http->FindSingleNode("(./td[1])[1]", $root, true, "#(\d{4}\D{1,5}\d{1,2}\D{1,5}\d{1,2}[^\)]+\))#"));
            }
            $date = strtotime($date);
            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z\d]{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            if ($code = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^([A-Z]{3})-#")) {
                $itsegment['DepCode'] = $code;
            }

            // DepName
            $itsegment['DepName'] = implode(" ", $this->http->FindNodes("./td[3]//strong", $root));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            if ($code = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^([A-Z]{3})-#")) {
                $itsegment['ArrCode'] = $code;
            }

            // ArrName
            $itsegment['ArrName'] = implode(" ", $this->http->FindNodes("./td[4]//strong", $root));

            // DepDate
            // ArrDate
            $dep = implode("\n", $this->http->FindNodes("./td[3]/descendant::text()", $root));

            if (preg_match("#(?<name>.+)\n(?<date>[^\n]*\d{4}.+)(?<time1>\d{2}):?(?<time2>\d{2}).*\d+:\d+[^\n]*(?<term>\n.+)?$#ms", $dep, $m)) {
                if (isset($m['term'])) {
                    $m['term'] = preg_replace("#\s+#", " ", $m['term']);

                    if (preg_match("#(.+)(?: - |, | – |\. |——)(.*(?:Terminal|T|航站|第).*)#u", $m['term'], $mat) or preg_match("#^(.*(?:Airport|空港))(.+(?:Terminal|第|航站)[\S\s]*)$#usU", $m['term'], $mat) or preg_match("#()(.+Terminal)\s*$#u", $m['term'], $mat)) {
                        $itsegment['DepName'] .= trim($mat[1]) !== '' ? '. ' . trim($mat[1]) : '';
                        $itsegment['DepartureTerminal'] = trim($mat[2]);
                    } else {
                        $itsegment['DepName'] .= '. ' . trim($m['term']);
                    }
                }
                $time = $m['time1'] . ':' . $m['time2'];
                $itsegment['DepDate'] = strtotime($time, $date);
            }
            $arr = implode("\n", $this->http->FindNodes("./td[4]/descendant::text()", $root));

            if (preg_match("#(?<name>.+)\n(?<date>[^\n]*\d{4}.+)(?<time1>\d{2}):?(?<time2>\d{2}).*\d+:\d+[^\n]*(?<term>\n.+)?$#ms", $arr, $ms)) {
                if (isset($ms['term'])) {
                    $ms['term'] = preg_replace("#\n#", " ", $ms['term']);

                    if (preg_match("#(.+)(?: - |, | – |\. |——)(.*(?:Terminal|T|航站|第).*)#u", $ms['term'], $mat) or preg_match("#(.*(?:Airport|空港))(.+(?:Terminal|第|航站)[\S\s]*)$#usU", $ms['term'], $mat)
                            or preg_match("#()(.+Terminal)\s*$#u", $ms['term'], $mat)
                            ) {
                        $itsegment['ArrName'] .= trim($mat[1]) !== '' ? '. ' . trim($mat[1]) : '';
                        $itsegment['ArrivalTerminal'] = trim($mat[2]);
                    } else {
                        $itsegment['ArrName'] .= '. ' . trim($ms['term']);
                    }
                }
                $time = $ms['time1'] . ':' . $ms['time2'];
                //				$this->logger->alert(trim($this->normalizeDate($ms['date'])) . ', ' . $time);
                $dateArr = trim($this->normalizeDate($ms['date'])) . ', ' . $time;

                $itsegment['ArrDate'] = strtotime($dateArr);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)!=''][not(contains(.,'.gif')) and not(normalize-space()='*')][2]", $root);

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)!=''][not(contains(.,'.gif')) and not(normalize-space()='*')][3]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $str = [];
            $str[] = str_replace(" ", "", explode(".", $itsegment['DepName'])[0] . ' to ' . explode(".", $itsegment['ArrName'])[0]);
            $str[] = str_replace(" ", "", explode(".", $itsegment['DepName'])[0] . ' To ' . explode(".", $itsegment['ArrName'])[0]);
            $str[] = str_replace(" ", "", explode(".", $itsegment['DepName'])[0] . ' 에서 ' . explode(".", $itsegment['ArrName'])[0]);
            $itsegment['Seats'] = array_filter($this->http->FindNodes("//*[" . $this->eq($str, 'normalize-space(translate(.," ",""))') . "]/ancestor::tr[1]/following-sibling::tr[position()>1]", null, "#\((\d{1,3}[A-Z])\)#"));

            // Duration
            $w = $this->t("Flight Duration");

            if (!is_array($w)) {
                $w = [$w];
            }
            $rule = implode(" or ", array_map(function ($s) {
                return "contains(normalize-space(.), '{$s}')";
            }, $w));
            $ruleReg = "(?:" . implode("|", $w) . ")";
            $itsegment['Duration'] = $this->http->FindSingleNode($q = "./td[2]/descendant::text()[{$rule}][1]", $root, true, "#{$ruleReg}:\s+(.+)#");

            if (!$itsegment['Duration']) {
                $itsegment['Duration'] = $this->http->FindSingleNode($q = "./td[2]/descendant::text()[{$rule}][1]/following::text()[normalize-space(.)][1]", $root);
            }

            // Meal
            // Smoking
            // Stops
            $segments[] = $itsegment;
        }

        return $segments;
    }

    private function parseSegmentFormat2($nodes)
    {
        $segments = [];

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[1]/descendant::text()[string-length(normalize-space(.))>2][1]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[2]/descendant::text()[string-length(normalize-space(.))>2][1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[3]/descendant::text()[string-length(normalize-space(.))>2][4]", $root);
            $itsegment['DepName'] = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[3]/descendant::text()[string-length(normalize-space(.))>2][1]", $root);

            if (!empty($node)) {
                $itsegment['DepName'] = $itsegment['DepName'] . ' - ' . $node;
            }
            // DepDate
            $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[3]/descendant::text()[string-length(normalize-space(.))>2][3]", $root, true, "#\d{4}#");

            if (preg_match("#(\d{2})(\d{2})#", $node, $m)) {
                $itsegment['DepDate'] = strtotime($m[1] . ':' . $m[2], $date);
            } else {
                $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[3]/descendant::text()[string-length(normalize-space(.))>2][3]", $root, true, "#\d+:\d+(?:\s*[ap]m)?#");

                if ($node) {
                    $itsegment['DepDate'] = strtotime($node, $date);
                } else {
                    $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[3]/descendant::text()[string-length(normalize-space(.))>2][2]", $root, true, "#\d+:\d+(?:\s*[ap]m)?#");
                    $itsegment['DepDate'] = strtotime($node, $date);
                }
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[4]/descendant::text()[string-length(normalize-space(.))>2][4]", $root);
            $itsegment['ArrName'] = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[4]/descendant::text()[string-length(normalize-space(.))>2][1]", $root);

            if (!empty($node)) {
                $itsegment['ArrName'] = $itsegment['ArrName'] . ' - ' . $node;
            }

            // ArrDate
            $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[4]/descendant::text()[string-length(normalize-space(.))>2][3]", $root, true, "#\d{4}#");

            if (preg_match("#(\d{2})(\d{2})#", $node, $m)) {
                $itsegment['ArrDate'] = strtotime($m[1] . ':' . $m[2], $date);
            } else {
                $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[4]/descendant::text()[string-length(normalize-space(.))>2][3]", $root, true, "#\d+:\d+(?:\s*[ap]m)?#");

                if ($node) {
                    $itsegment['ArrDate'] = strtotime($node, $date);
                } else {
                    $node = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[4]/descendant::text()[string-length(normalize-space(.))>2][2]", $root, true, "#\d+:\d+(?:\s*[ap]m)?#");
                    $itsegment['ArrDate'] = strtotime($node, $date);
                }
            }

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[2]/descendant::text()[string-length(normalize-space(.))>2][1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[2]/descendant::text()[string-length(normalize-space(.))>2][2]", $root);

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[2]/descendant::text()[string-length(normalize-space(.))>2][3]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $str = $this->http->FindSingleNode("(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[3]/descendant::text()[string-length(normalize-space(.))>2][1]", $root) . ' To ' . $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root);
            $itsegment['Seats'] = array_filter($this->http->FindNodes("//text()[normalize-space(.)='{$str}']/ancestor::tr[1]/following-sibling::tr[position()>1]", null, "#\((\d+\w)\)#"));

            // Duration
            $w = $this->t("Flight Duration");

            if (!is_array($w)) {
                $w = [$w];
            }
            $rule = implode(" or ", array_map(function ($s) {
                return "contains(normalize-space(.), '{$s}')";
            }, $w));
            $ruleReg = "(?:" . implode("|", $w) . ")";
            $itsegment['Duration'] = $this->http->FindSingleNode($q = "(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[2]/descendant::text()[{$rule}][1]", $root, true, "#{$ruleReg}:\s+(.+)#");

            if (!$itsegment['Duration']) {
                $itsegment['Duration'] = $this->http->FindSingleNode($q = "(./descendant::table[count(descendant::table)=0][string-length(normalize-space(.))>3])[2]/descendant::text()[{$rule}][1]/following::text()[normalize-space(.)][1]", $root);
            }

            // Meal
            // Smoking
            // Stops
            $segments[] = $itsegment;
        }

        return $segments;
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking Reference"));

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("(.//*[" . $this->eq($this->t("Booking Reference")) . "])[1]/following::text()[string-length(normalize-space(.))>2][1]");
        }

        $w = $this->t("Booking Date");

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(text(), '{$s}')";
        }, $w));
        $ruleReg = "(?:" . implode("|", $w) . ")";
        $dr = $this->http->FindSingleNode("//*[{$rule}]/ancestor-or-self::td[1]", null, true, "#{$ruleReg}\s*:?\s*(.+)#");

        if ($dr) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($dr));
        }

        if (($tc = $this->http->FindSingleNode("//strong[contains(@style,'#f15a23')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td/strong"))) {
            //some peace of indus code
            //use of 0x160 character in regexp
            if (preg_match('#' . $this->t("Payment of") . '[\s ]*(?:[\S])?[\s ]*([\d\.]+)[\s ]*(\S+)[\s ]*#i', $tc, $m)) {
                $it['TotalCharge'] = cost($m[1]);
                $it['Currency'] = ($m[2] == '円') ? 'JPY' : $m[2];
            }
        } else {
            $xpath = '//text()[contains(., "' . $this->t("Payment of") . '")]';
            $total = $this->http->FindSingleNode($xpath);

            if (preg_match('/([\d.]+)\s*([A-Z]{3})/', $total, $m)) {
                $it['TotalCharge'] = $m[1];
                $it['Currency'] = $m[2];
            }
        }

        // TripNumber
        // Passengers
        $xpath = "//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::tr[1]/following-sibling::tr/td[1]//text()[string-length(.) > 2][1]";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::table[2]/following-sibling::table/descendant::text()[string-length(.) > 2][1]";
        }
        $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes($xpath, null, "/((?:mr|miss|mrs|ms)\s+\w+\s+\w+)/i"))));

        // AccountNumbers
        $it['AccountNumbers'] = array_unique($this->http->FindNodes("//text()[contains(., 'Frequent Flyer number')]", null, "#Frequent Flyer number (.+)#"));

        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::table[2]/ancestor::tr[1]/following-sibling::tr[" . $this->contains($this->t("Flight Duration")) . "]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::tr[1]/following-sibling::tr[" . $this->contains($this->t("Flight Duration")) . "]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
            } else {
                $it['TripSegments'] = $this->parseSegmentFormat1($nodes);
            }
        } else {
            $it['TripSegments'] = $this->parseSegmentFormat2($nodes);
        }

        $itineraries[] = $it;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[string-length(normalize-space(.))>2][1]", $root);
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
        // $this->http->log($str);
        $year = date("Y", $this->date);

        $in = [
            "#^\s*[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4})\s*$#",
            "#^(\d{4})年\s+(\d+)月(\d+)日\s+\(土\)$#",
            "#^(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日(?:\s*\(金\))?$#",
            "#^(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日(?:\s*\(金\))?(?:\s*週三)?$#",
            "#^(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*.*#",
            "#^(\d{4})년\s*(\d+)월\s*(\d+)일\s*\(.+\)$#",
            "#^\s*(\d{4})\s*년\s*(\d+)\s*월\s*(\d+)\s*일\s+\w+\s*$#u",
        ];
        $out = [
            "$1",
            "$3.$2.$1",
            "$3.$2.$1",
            "$3.$2.$1",
            "$3.$2.$1",
            "$3.$2.$1",
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->http->log($str);
        if (preg_match("#[^\d\W\./]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $operation = ' or ')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode($operation, array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return $text . "=\"{$s}\""; }, $field));
    }
}
