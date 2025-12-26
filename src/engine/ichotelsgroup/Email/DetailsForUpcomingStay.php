<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Schema\Parser\Email\Email;

class DetailsForUpcomingStay extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-183669624.eml, ichotelsgroup/it-183688494.eml, ichotelsgroup/it-33330383.eml, ichotelsgroup/it-33330405.eml, ichotelsgroup/it-33347410.eml, ichotelsgroup/it-35209129.eml, ichotelsgroup/it-57353152.eml, ichotelsgroup/it-64944695.eml, ichotelsgroup/it-65190063.eml, ichotelsgroup/it-72625700.eml, ichotelsgroup/it-72817244.eml";
    public $reFrom = ".ihg.com";
    public $reSubject = [
        "en"  => "Details for Your Upcoming Stay at ",
        "es"  => "Detalles de su próximo alojamiento en ",
        "en2" => "Thank You for Your Stay at ",
        "es2" => "Gracias por alojarse en ",
        "zh"  => "感谢您入住 InterContinental",
        "de"  => " Details Ihres bevorstehenden Aufenthalts im",
        "ko"  => "숙박 내역, 예약 확정",
        "pt"  => "Obrigado por sua estadia no",
    ];
    public $reBody = 'IHG';
    public $reBody2 = [
        "es"   => "Su reservación de hotel",
        "es2"  => "Su alojamiento",
        "de"   => "Ihre Hotelreservierung",
        "en"   => "Your Hotel Reservation",
        "en2"  => "Your hotel reservation",
        "en3"  => "Your Stay",
        "en4"  => "Reservation details",
        "zh"   => "您的住宿",
        "zh2"  => "前台",
        "zh3"  => "您的酒店预订",
        "ja"   => "フロントデスク",
        "ko"   => "내 호텔 예약",
        "pt"   => "Nº de confirmação:",
    ];

    public static $dictionary = [
        "pt" => [
            "Confirmation #:"     => "Nº de confirmação:",
            // "Dear" => "",
            "Your Hotel –"        => "Obrigado por se hospedar no",
            //"Your Hotel End"        => "",
            "Check In:"             => "Data de check-in:",
            "Check Out:"            => "Data de check-out:",
            // "Check-in is at" => "",
            // "check-out is at" => "",
            //"Map"                 => "",
            "Front Desk:"         => "Recepção:",
            //"Number of rooms:"    => "",
            //"Estimated Earnings:" => "",
            //"View Account"        => "",
            //"Total Balance"       => "",
        ],
        "ko" => [
            "Confirmation #:"     => "확인 번호",
            // "Dear" => "",
            //"Your Hotel –"        => "",
            "Your Hotel End"        => "에 오신 것을 환영합니다",
            "Check In:"             => "체크인",
            "Check Out:"            => "체크아웃 :",
            // "Check-in is at" => "",
            // "check-out is at" => "",
            //"Map"                 => "",
            "Front Desk:"         => "프런트 데스크:",
            "Number of rooms:"    => "객실 수 :",
            //"Estimated Earnings:" => "",
            //"View Account"        => "",
            //"Total Balance"       => "",
        ],
        "en" => [
            "Confirmation #:"     => ["Confirmation #:", "Confirmation #"],
            // "Dear" => "",
            "Check In:"           => ["Check In:", "Check-in Date:", "Check In", "Arrival Date"],
            "Check Out:"          => ["Check Out:", "Check-out Date:", "Check Out", "Departure Date"],
            // "Check-in is at" => "",
            // "check-out is at" => "",
            "Front Desk:"         => ["Front Desk:", "Front desk:", "Front Desk", "Front desk", "DIRECT:"],
            "Number of rooms:"    => ["Number of rooms:", "Number of rooms", "Number of Rooms:"],
            "Your hotel"          => ["Your hotel", "View More Reservation Details"],
            "Your Hotel –"        => "Thanks for staying at",
        ],
        "es" => [
            "Confirmation #:"     => ["N.º de confirmación:", "N.º de confirmación"],
            // "Dear" => "",
            "Your hotel"          => "Ver más detalles de la reservación",
            "Your Hotel –"        => "Su hotel –",
            "Check In:"           => ["Entrada:", "Fecha de entrada:", "Entrada"],
            "Check Out:"          => ["Salida:", "Fecha de salida:"],
            // "Check-in is at" => "",
            // "check-out is at" => "",
            "Map"                 => "Mapa",
            "Front Desk:"         => "Recepción:",
            "Number of rooms:"    => "Número de habitaciones:",
            "Estimated Earnings:" => "Ganancias estimadas:",
            "View Account"        => "Ver cuenta",
            "Total Balance"       => "Saldo total",
        ],
        "zh" => [
            "Confirmation #:"   => ["确认号码:", "确认号码", "確認號碼"],
            // "Dear" => "",
            "Your hotel"        => "您的酒店",
            "Your Hotel –"      => "欢迎来到",
            "Number of rooms:"  => "客房数量：",
            //            "Map" => "",

            "Check In:"           => ["入住登记日期:", "登记入住", "登記入住"],
            "Check Out:"          => ["退房日期:", "退房 :", "退房登記 :"],
            // "Check-in is at" => "",
            // "check-out is at" => "",
            "Front Desk:"         => ["前台:", "櫃台:"],
            "Estimated Earnings:" => "本次住宿所获积分：",
            "View Account"        => "查看账户",
            "Total Balance"       => "积分余额：",
        ],
        "ja" => [
            "Your hotel"            => 'ご利用のホテル',
            "Your Hotel End"        => "へようこそ",
            "Confirmation #:"       => '予約確認番号',
            "Dear"                  => "様",
            "Your Hotel –"          => "ご利用のホテル –",
            "Number of rooms:"      => ["客室数:", "客室数", "客室数 :"],
            "Map"                   => "地図",

            "Check In:"   => ["チェックイン:", "チェックイン"],
            "Check Out:"  => ["チェックアウト:", "チェックアウト", "チェックアウト :"],
            // "Check-in is at" => "",
            // "check-out is at" => "",
            "Front Desk:" => "フロントデスク:",
            //            "Estimated Earnings:" => "",
            //            "View Account" => "",
            "Total Balance" => "积分余额：",
        ],
        "de" => [
            "Confirmation #:"     => "Buchungsnummer",
            // "Dear" => "",
            //            "Your Hotel –"        => "Su hotel –",
            "Check In:"           => ["Anreise"],
            "Check Out:"          => ["Check-out:"],
            // "Check-in is at" => "",
            // "check-out is at" => "",
            //            "Map"                 => "Mapa",
            "Front Desk:"         => "Front desk:",
            "Number of rooms:"    => "Zahl der Zimmer:",
            //            "Estimated Earnings:" => "Ganancias estimadas:",
            //            "View Account"        => "Ver cuenta",
            //            "Total Balance"       => "Saldo total",
        ],
    ];

    public $lang = "";
    private $enDatesInverted = false;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $allDates = $this->http->FindNodes("//text()[contains(.,'/') and not(ancestor::style)]");

        if ($this->lang === 'es') {
            // it-33330405.eml, it-35209129.eml
            $this->enDatesInverted = true;
        } elseif (preg_match_all('/\b(\d{1,2})\/\d{1,2}\/\d{2,4}\b/', implode("\n", $allDates), $dateMatches)) {
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->enDatesInverted = true;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        // http://mi.ihg.com/p/rp/f66f7bc7631d95e5.png?mi_u=258760821&memberid=258760821&lastname=Hijazi&firstname=Sam&memlvl=PLATINUM&hotel_brand_code=IC&amb_member=False&karma_member=False&firstnamelastname=Sam Hijazi
        $statementImg = $this->http->FindSingleNode("//img[contains(@src, '.ihg.com') and contains(@src, 'memberid=') and contains(@src, 'firstnamelastname')]/@src");

        if (!empty($statementImg)) {
            if (preg_match("/memberid=(\d+)/", $statementImg, $number)
                && preg_match("/memlvl=(\w*)/", $statementImg, $level)
                && preg_match("/firstnamelastname=([\w \-]+)/", $statementImg, $name)
            ) {
                $st = $email->add()->statement();

                $st
                    ->setNoBalance(true)
                    ->setNumber($number[1])
                    ->setLogin($number[1])
                    ->addProperty("Name", $name[1])
                    ->addProperty("Level", !empty($level[1]) ? $level[1] : 'Club')
                ;
            }
        }

        $a = explode('\\', __CLASS__);
        $email->setType(end($a) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2;
        $cnt = $formats * count(self::$dictionary);

        return $cnt;
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->hotel();

        $r->general()->confirmation($this->nextText($this->t("Confirmation #:")));

        $xpathFragmentAccount = "//text()[{$this->eq($this->t('View Account'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[{$this->contains($this->t('Total Balance'))}][1]/preceding-sibling::tr[normalize-space()]";

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/ancestor::tr[1]/descendant::td[2]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode($xpathFragmentAccount . "[last()]", null, true, "/^{$patterns['travellerName']}$/u");
        }

        if (empty($traveller)) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }
        }

        if (empty($traveller)) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Dear'))}]", null, "/^({$patterns['travellerName']})[,\s]*{$this->opt($this->t('Dear'))}(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }
        }

        if ($traveller) {
            $r->general()->traveller($traveller);
        }

        $accountNumber = array_values(array_filter($this->http->FindNodes($xpathFragmentAccount . "[last()-1]/descendant::text()[normalize-space() and position()>1]", null, '/^\d{5,}$/')));

        if (isset($accountNumber[0])) {
            $r->program()->account($accountNumber[0], false, $traveller);
        }

        $hotelName = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your Hotel –")) . "]",
            null, true, "#{$this->opt($this->t('Your Hotel –'))}\s+(.+)#");
        // it-72625700
        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Welcome to")) . "]",
                null, true, "#{$this->opt($this->t('Welcome to'))}\s+(.+)#");
        }
        // it-72817244
        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your hotel"))} and not(following::text()[{$this->eq($this->t("Your hotel"))}])]/following::text()[normalize-space()][1][not(ancestor::a)]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Your Hotel End"))}]", null, true, "/^(.+){$this->opt($this->t('Your Hotel End'))}/");
        }

        if (empty($hotelName)) {
            // it-57353152.eml
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t("We look forward to welcoming you at"))}]", null, true, "/{$this->opt($this->t("We look forward to welcoming you at"))}\s*(.{3,}?)\s*[,.;!?]/");

            if ($hotelName_temp && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                $hotelName = $hotelName_temp;
            }
        }

        if (empty($hotelName)) {
            // it-33330383.eml, it-33330405.eml, it-35209129.eml
            $hotelName_temp =
                $this->http->FindSingleNode("(.//text()[{$this->eq($this->t("Confirmation #:"))}])[1]//preceding::table[count(./descendant::text()[normalize-space()!=''])>1][1]/descendant::text()[normalize-space()!=''][1]");

            if ($hotelName_temp && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                $hotelName = $hotelName_temp;
            }

            $node = implode("\n",
                $this->http->FindNodes("(.//text()[{$this->eq($this->t("Confirmation #:"))}])[1]//preceding::table[count(./descendant::text()[normalize-space()!=''])>1][1]/descendant::text()[normalize-space()!=''][position()>1]"));

            if (preg_match("#(.+)\s+{$this->opt($this->t('Front Desk:'))}\s+({$patterns['phone']})[ ]*\n#us", $node, $m)) {
                $address = preg_replace("#\s+#", ' ', $m[1]);
                $phone = trim($m[2]);
            }
        } else {
            // it-33347410.eml
            // it-65190063.eml
            $address = implode(', ',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Map'))}]/ancestor::tr[1]/preceding-sibling::tr/descendant::text()[normalize-space()]"));

            if (empty($address) && $hotelName) {
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your hotel'))} and not(following::text()[{$this->eq($this->t('Your hotel'))}])]/following::text()[normalize-space()][1]/ancestor::td[1][{$this->contains($hotelName)}]", null, true, "/^{$this->opt($hotelName)}\s*(.+)/iu")
                    ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your hotel'))} and not(following::text()[{$this->eq($this->t('Your hotel'))}])]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/^{$this->opt($hotelName)}\s*(.+)/iu")
                    ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Front Desk:'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/^{$this->opt($hotelName)}\s*(.+)/iu")
                ;
            }

            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Front Desk:'))}]/following::text()[string-length(normalize-space())>3][1]", null, true, "/^{$patterns['phone']}$/");

            $numberOfRooms = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Number of rooms:"))}]/following::text()[normalize-space()][1]", null, true, '/^\s*(\d{1,3})\s*$/');

            if ($numberOfRooms !== null) {
                $r->booked()->rooms($numberOfRooms);
            }
        }

        if (empty($hotelName)
            && empty($address)
            && $this->http->XPath->query("//a[starts-with(normalize-space(), 'We look forward to your arrival at')]")->length > 0) {
            $url = $this->http->FindSingleNode("//a[starts-with(normalize-space(), 'We look forward to your arrival at')]/@href");
            $http2 = clone $this->http;
            $http2->GetURL($url);
            sleep(2);
            $hotelName = $http2->FindSingleNode("//text()[normalize-space()='News']/ancestor::div[1]/following::div[1]/descendant::text()[normalize-space()][1][contains(normalize-space(), 'Get in Touch')]/following::text()[normalize-space()][1]");
            $address = $http2->FindSingleNode("//text()[normalize-space()='News']/ancestor::div[1]/following::div[1]/descendant::text()[normalize-space()][1][contains(normalize-space(), 'Get in Touch')]/following::text()[normalize-space()][3]");

            $r->setAddress($address);
        }

        $r->hotel()
            ->name($hotelName);

        if (isset($address)) {
            $address = preg_replace("/{$this->opt($this->t('Confirmation #:'))}.*/u", '', $address);
            $r->hotel()->address($address);
        } elseif ($hotelName
            && $this->http->FindSingleNode("//text()[ preceding::text()[normalize-space()][1][{$this->eq($this->t('Your hotel'))}] and following::text()[normalize-space()][1][{$this->starts($this->t('Front Desk:'))}] ]", null, true, "/^{$this->opt($hotelName)}$/iu")
        ) {
            $r->hotel()->noAddress();
        }

        if (isset($phone)) {
            $r->hotel()->phone($phone);
        }

        $earnedAwards = $this->nextText($this->t("Estimated Earnings:"));

        if (!empty($earnedAwards)) {
            $r->program()
                ->earnedAwards($earnedAwards);
        }

        $checkInVal = $this->nextText($this->t("Check In:"));
        $checkOutVal = $this->nextText($this->t("Check Out:"));

        $checkIn = strtotime($this->normalizeDate($checkInVal));
        $checkOut = strtotime($this->normalizeDate($checkOutVal));

        if (!empty($checkIn) && !empty($checkOut) && $checkOut - $checkIn > 14 * 86400) {
            // it-57353152.eml
            $this->enDatesInverted = true;
            $checkIn = strtotime($this->normalizeDate($checkInVal));
            $checkOut = strtotime($this->normalizeDate($checkOutVal));
        }

        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in is at'))}]", null, true, "/{$this->opt($this->t('Check-in is at'))}[-:\s]+({$patterns['time']})/");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-out is at'))}]", null, true, "/{$this->opt($this->t('check-out is at'))}[-:\s]+({$patterns['time']})/");

        if ($timeCheckIn && $checkIn) {
            $checkIn = strtotime($timeCheckIn, $checkIn);
        }

        if ($timeCheckOut && $checkOut) {
            $checkOut = strtotime($timeCheckOut, $checkOut);
        }

        $r->booked()->checkIn($checkIn)->checkOut($checkOut);
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);
                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            // 18/07/17 at 03:00 PM    |    08/21/17 – 11:00 AM
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2})\s+\D+\s+(\d{1,2}[:]+\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s*$/',
            // 2019/08/27 – 03:00 PM
            '/^\s*(\d{4})\/(\d{1,2})\/(\d{1,2})\s+\D+\s+(\d{1,2}[:]+\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s*$/',
            // 17/03/19
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2})\s*$/',
            // 年12月 8, 2020
            '/^年(\d+)月\s*(\d+)\,\s*(\d{4})$/',
            //8월 14, 2022
            '/^(\d+)\D+(\d+)\,\s*(\d{4})$/',
            //Agosto 7, 2022
            '/^(\w+)\s*(\d+)\,\s*(\d{4})$/u',
        ];
        $out[0] = $this->enDatesInverted ? '$1.$2.20$3, $4' : '$2.$1.20$3, $4';
        $out[1] = '$3.$2.$1, $4';
        $out[2] = $this->enDatesInverted ? '$1.$2.20$3' : '$2.$1.20$3';
        $out[3] = '$2.$1.$3';
        $out[4] = '$2.$1.$3';
        $out[5] = '$2 $1 $3';

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function nextText($field, $root = null): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]",
            $root);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function DateFormatForHotels($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "",
                preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "",
                preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateOut);
        } else {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1,
                $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)
        ) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);
                    //echo "$tempdate1\t$tempdate2\n";
                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }
}
