<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class It3484561 extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-221251344.eml, hhonors/it-23422671.eml, hhonors/it-23443069.eml, hhonors/it-30995720.eml, hhonors/it-31486387.eml, hhonors/it-3484561.eml, hhonors/it-3486719.eml, hhonors/it-3486852.eml, hhonors/it-3486950.eml, hhonors/it-3487062.eml, hhonors/it-3487545.eml, hhonors/it-3508326.eml, hhonors/it-3508760.eml, hhonors/it-3508943.eml, hhonors/it-3642335.eml, hhonors/it-36636511.eml, hhonors/it-3686255.eml, hhonors/it-3692695.eml, hhonors/it-4237694.eml, hhonors/it-45607568.eml, hhonors/it-46506118.eml, hhonors/it-48394561.eml, hhonors/it-50412422.eml, hhonors/it-50546975.eml, hhonors/it-53651064.eml, hhonors/it-58598428.eml, hhonors/it-7163152.eml, hhonors/it-886904350.eml, hhonors/it-889136175.eml, hhonors/it-8919251.eml"; // +2 bcdtravel(html,url)[en]

    private $reBody = ["HHonors", "Hilton"];
    // !!! do not change order in $reBody2 !!!
    private $reBody2 = [
        0 => "Room Information:",
        3 => [
            'en'  => 'Your Room Information',
            'en2' => 'Total for Stay per Room Rate',
            'en3' => 'Your Upcoming Stay',
            'de'  => 'Informationen zu Ihrem Zimmer',
            'es'  => 'La información de su habitación',
            'pt'  => 'Informações do seu quarto',
            'ko'  => '객실 정보',
            'nl'  => 'Uw kamerinformatie',
            'ja'  => 'お客様の客室に関する情報',
            'ja2' => 'お客様の今後のご滞在予定',
            'it'  => 'Informazioni sulla tua camera',
            'zh'  => '您的客房信息',
            'pl'  => 'Informacje o pokoju',
            'tr'  => 'Oda Bilgileriniz',
        ],
        2 => 'Cancellation Confirmation',
        1 => "look forward to seeing",
        4 => "see you on",
        5 => 'Stay Dates:',
        6 => 'Cancellation #',
    ];

    private $http2; // for remote html-content

    private $code;
    private $bodies = [
        'gcampaigns' => [
            '//a[contains(@href,"manage.passkey.com")]',
            '//a[contains(@href,"book.passkey.com")]',
        ],
        'hhonors' => [
            '//a[contains(@href,"hilton.com")]',
        ],
    ];
    private $headers = [
        'gcampaigns' => [
            'from' => ['groupcampaigns@pkghlrss.com'],
            'subj' => [
                'Confirmation #',
                'Cancellation #',
                'の予約確認番号は',
            ],
        ],
        'hhonors' => [
            'from' => [
                '@res.hilton.com',
                'Hilton Honors', '@h4.hilton.com',
            ],
            'subj' => [
                'Confirmation #',
                'Cancellation #',
                'の予約確認番号は',
                'bevestigingsnummer',
                'La tua conferma di prenotazione per',
            ],
        ],
    ];

    private static $dict = [
        'tr' => [
            'Confirmation'       => 'Onay',
            //'Your Upcoming Stay' => '',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'Misafir:',
            'Guest Name:'           => 'Misafir adı:',
            'Rooms:'                => 'Odalar:',
            'Room Plan:'            => 'Oda planı:',
            'Your Room Information' => 'Oda Bilgileriniz',
            'Your Rate Information' => 'Fiyat Bilgileriniz',
            'Rate Per Night'        => 'Gecelik fiyat',
            //            'Honors Reward' => '',
            'Total for Stay'               => 'Konaklama için Toplam',
            'Total for Stay Per Room Rate' => 'Oda Başına Konaklama Fiyatı için Toplam',
            'Taxes'                        => 'Vergiler',
            'see you on'                   => 'tarihinde görüşmek üzere',
            'Adult'                        => 'Yetişkin',
            //'Child'                => '',
            'View Booking Details'               => 'Rezervasyon Detaylarını Görüntüle', // @alt for img with dates
            'Maps & Directions'                  => 'Haritalar ve Yönlendirmeler',
            'Rate Rules and Cancellation Policy' => 'Fiyat Kuralları ve İptal İlkesi',
            'Cancellations were required by '    => 'If you wish to cancel',
        ],
        'pt' => [
            'Confirmation'       => 'CONFIRMAÇÃO',
            //'Your Upcoming Stay' => '',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'Convidados:',
            'Guest Name:'           => 'Nome do convidado:',
            'Rooms:'                => 'Quartos:',
            'Room Plan:'            => 'Plano do quarto:',
            'Your Room Information' => 'Informações do seu quarto',
            'Your Rate Information' => 'Informações da sua tarifa',
            'Rate Per Night'        => 'Tarifa por diária',
            //            'Honors Reward' => '',
            'Total for Stay'               => 'Total da estada',
            'Total for Stay Per Room Rate' => 'Tarifa total da estada por quarto',
            'Taxes'                        => 'Impostos',
            'see you on'                   => 'nos vemos em',
            'Adult'                        => 'Adulto',
            //'Child'                => '',
            'View Booking Details'               => 'Exibir detalhes da reserva', // @alt for img with dates
            'Maps & Directions'                  => 'Mapas e trajetos',
            'Rate Rules and Cancellation Policy' => 'Regras da tarifa e política de cancelamento',
        ],
        'en' => [
            'Rate Per Night'               => ['Rate Per Night', 'Rate per night'],
            'Total for Stay Per Room Rate' => ['Total for Stay Per Room Rate', 'Total for Stay per Room Rate'],
            'Total for Stay'               => ['Total for Stay', 'Total price for Stay'],
            'Resort Charge'                => ['Resort Charge', 'Service Charge'],
        ],
        'de' => [
            'Confirmation'       => 'BESTÄTIGUNG',
            'Your Upcoming Stay' => 'Ihr nächster Aufenthalt',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'Gäste:',
            'Rooms:'                => 'Räume:',
            'Your Room Information' => 'Informationen zu Ihrem Zimmer',
            'Your Rate Information' => 'Informationen zu Ihrem Preis',
            'Rate Per Night'        => 'Preis pro Nacht',
            //            'Honors Reward' => '',
            'Total for Stay'               => 'Gesamt für den Aufenthalt',
            'Total for Stay Per Room Rate' => 'Gesamtpreis des Aufenthalts pro Zimmer',
            'Taxes'                        => 'Steuern',
            'see you on'                   => 'bis zum',
            'Adult'                        => 'Erwachsene',
            //            'Child' => '',
            //            'View Booking Details' => '', // @alt for img with dates
            'Maps & Directions'                  => 'Karten und Anreiseinformationen',
        ],
        'es' => [
            'Confirmation'       => 'CONFIRMACIÓN',
            'Your Upcoming Stay' => 'Su próxima estadía',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'Invitados:',
            'Rooms:'                => 'Habitaciones:',
            'Your Room Information' => 'La información de su habitación',
            'Your Rate Information' => 'La información de su tarifa',
            'Rate Per Night'        => 'Tarifa por noche',
            //            'Honors Reward' => '',
            'Total for Stay'               => 'Total de la estadía',
            'Total for Stay Per Room Rate' => 'Total de la estadía por tarifa de la habitación',
            //            'Taxes' => '',
            'see you on'           => 'nos vemos el',
            'Adult'                => 'Adultos',
            'Child'                => 'Niño',
            'View Booking Details' => 'Ver detalles de la reserva', // @alt for img with dates
        ],
        'ko' => [
            'Confirmation'       => '예약 확인',
            'Your Upcoming Stay' => '예정 투숙일',
            //            'Honors Reward ID:' => '',
            'Guests:'               => '손님 :',
            'Rooms:'                => '객실 :',
            'Your Room Information' => '객실 정보',
            'Your Rate Information' => '요금 정보',
            'Rate Per Night'        => '1박당 요금',
            //            'Honors Reward' => '',
            'Total for Stay'               => '투숙당 총계',
            'Total for Stay Per Room Rate' => '객실당 총 숙박료',
            'Taxes'                        => '세금',
            'Resort Charge'                => ['Service Charge'],
            'see you on'                   => '에 만나 뵙겠습니다',
            'Adult'                        => '성인',
            //            'Child' => '',
            'Rate Rules and Cancellation Policy' => '요금 규정 및 취소 정책',
            'View Booking Details'               => '예약 세부 정보 보기', // @alt for img with dates
            'Maps & Directions'                  => '지도 및 오시는 길',
        ],

        'nl' => [
            'Confirmation' => 'Bevestiging',
            //        'Your Upcoming Stay' => '',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'gasten:',
            'Rooms:'                => 'kamers:',
            'Your Room Information' => 'Uw kamerinformatie',
            'Your Rate Information' => 'Uw tariefinformatie',
            'Rate Per Night'        => 'Tarief per nacht',
            //            'Honors Reward' => '',
            'Total for Stay'               => ['Totale tarief voor', 'Totaal voor verblijf'],
            'Total for Stay Per Room Rate' => 'Totale tarief voor verblijf per kamer',
            //  'Taxes' => '세금',
            'Resort Charge' => ['Belastingen'],
            'see you on'    => 'see you soon',
            'Adult'         => 'Volwassen',
            //            'Child' => '',
            'Rate Rules and Cancellation Policy' => 'Tariefvoorwaarden en annuleringsbeleid',
            'Guest Name:'                        => 'Gast naam:',
            'Room Plan:'                         => 'Kamerplan:',
            'View Booking Details'               => 'Bekijk boekingsgegevens', // @alt for img with dates
        ],
        'ja' => [
            'Confirmation'       => '確認番号',
            'Your Upcoming Stay' => 'お客様の今後のご滞在予定',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'ゲスト：',
            'Rooms:'                => '部屋:',
            'Your Room Information' => 'お客様の客室に関する情報',
            'Your Rate Information' => 'お客様の料金に関する情報',
            'Rate Per Night'        => '1泊あたりの料金',
            //            'Honors Reward' => '',
            'Total for Stay'                     => ['ご滞在料金合計'],
            'Total for Stay Per Room Rate'       => '1室あたりのご滞在料金合計',
            'Taxes'                              => '税金',
            'Resort Charge'                      => ['Service Charge'],
            'see you on'                         => 'のお越しをお待ちしております。',
            'Adult'                              => '大人',
            'Child'                              => '子供',
            'Rate Rules and Cancellation Policy' => '料金規定とキャンセルポリシー',
            'Guest Name:'                        => 'お客様のお名前：',
            'Room Plan:'                         => '間取り：',
            'View Booking Details'               => '予約内容を表示する', // @alt for img with dates
            'Maps & Directions'                  => '地図と道順',
        ],
        'it' => [
            'Confirmation' => 'CONFERMA',
            //            'Your Upcoming Stay' => '',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'Ospiti:',
            'Rooms:'                => 'Camere:',
            'Your Room Information' => 'Piano della camera',
            'Your Rate Information' => 'Informazioni sulla tua tariffa',
            'Rate Per Night'        => 'Tariffa a notte',
            //            'Honors Reward' => '',
            'Total for Stay'                     => 'Totale per il soggiorno',
            'Total for Stay Per Room Rate'       => 'Tariffa totale per camera a soggiorno',
            'Taxes'                              => 'Tasse',
            'see you on'                         => 'ti attendiamo in data',
            'Adult'                              => 'Adulto',
            'Rate Rules and Cancellation Policy' => 'Termini e condizioni sulle tariffe e sulle cancellazioni',
            'Guest Name:'                        => 'Nome ospite:',
            'Room Plan:'                         => 'Piano della camera:',
            //            'Child' => '',
            //            'View Booking Details' => '', // @alt for img with dates
            'Maps & Directions'                  => 'Mappe e indicazioni',
        ],
        'zh' => [
            'Confirmation' => '确认',
            //            'Your Upcoming Stay' => '',
            //            'Honors Reward ID:' => '',
            'Guests:'               => '嘉賓：',
            'Rooms:'                => '客房：',
            'Your Room Information' => '您的客房信息',
            'Your Rate Information' => '您的房价信息',
            'Rate Per Night'        => '每晚房价',
            //            'Honors Reward' => '',
            'Total for Stay'                     => '住宿费用总计',
            'Total for Stay Per Room Rate'       => '每房住宿房价总计',
            'Taxes'                              => 'Service Charge',
            'see you on'                         => '恭候您',
            'Adult'                              => '成人',
            'Rate Rules and Cancellation Policy' => '房价规定和取消政策',
            'Guest Name:'                        => '客人姓名：',
            'Room Plan:'                         => '房间计划：',
            //            'Child' => '',
            'View Booking Details'               => '查看预订详情', // @alt for img with dates
            'Maps & Directions'                  => '地图和路线指引',
        ],
        'pl' => [
            'Confirmation' => 'Potwierdzenie',
            //            'Your Upcoming Stay' => '',
            //            'Honors Reward ID:' => '',
            'Guests:'               => 'Goście:',
            'Rooms:'                => 'Pokoje:',
            'Your Room Information' => 'Informacje o pokoju',
            'Your Rate Information' => 'Informacje o cenie',
            'Rate Per Night'        => 'Cena za noc',
            //            'Honors Reward' => '',
            'Total for Stay'               => 'Razem za pobyt',
            'Total for Stay Per Room Rate' => 'Razem za pobyt w pokoju',
            //            'Taxes' => '',
            //            'see you on' => '',
            'Adult'                              => 'Dorosły',
            'Rate Rules and Cancellation Policy' => 'Ceny i zasady anulowania rezerwacji',
            'Guest Name:'                        => 'Imię gościa:',
            'Room Plan:'                         => 'Plan pokoju:',
            //            'Child' => '',
            'View Booking Details' => 'Zobacz szczegóły rezerwacji', // @alt for img with dates
        ],
    ];

    private $emailSubject;
    private $lang = 'en';

    private $patterns = [
        'phone'         => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
        'travellerName' => '[A-z][-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
    ];

    public static function getEmailProviders()
    {
        return ['hhonors', 'gcampaigns'];
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $result = false;

        $this->emailSubject = $parser->getSubject();

        foreach ($this->reBody2 as $key => $reBody2) {
            if (is_string($reBody2)
                && (stripos($body, $reBody2) || $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody2}')]")->length > 0)) {
                switch ($key) {
                    case 0:
                    case 4:
                        $result = $this->parseEmail_1($email);
                        $type = $key + 1;

                        break 2;

                    case 1:
                        if (false !== stripos($body, 'Your Room Information')) {
                            break;
                        }
                        $result = $this->parseEmail_2($email);

                        if (true !== $result) {
                            $result = false;

                            continue 2;
                        }
                        $type = $key + 1;

                        break 2;

                    case 2:
                    case 5:
                        $result = $this->parseEmail_3($email); // cancelled
                        $type = $key + 1;

                        break 2;

                    case 3:
                        $result = $this->parseEmail_4($email);
                        $type = $key + 1;

                        break 2;
                }
            } elseif (is_array($reBody2)) {
                $this->lang = '';

                foreach ($reBody2 as $lang => $re) {
                    if (false !== stripos($body, $re) || $this->http->XPath->query("//text()[contains(normalize-space(.), '{$re}')]")->length > 0) {
                        $this->lang = substr($lang, 0, 2);

                        break;
                    }
                }

                if (!empty($this->lang)) {
                    switch ($key) {
                        case 3:
                            $result = $this->parseEmail_4($email);
                            $type = $key + 1;

                            break 2;
                    }
                }
            }
        }

        if (!$result && preg_match('/\bCancellation\s*#\s*\d{5,}/i', $parser->getSubject())) {
            $result = $this->parseEmail_3($email); // cancelled
            $type = '3';
        }

//        if ( !$result && ( $url = $this->http->FindSingleNode("//a[ descendant::img[normalize-space(@alt)='View Booking Details'] ]/@href") ) ) {
//            $this->parseUrl($email, $url);
//            $type = 'Url';
//        }

        $this->logger->debug('format-' . $type);
        $email->setType('It3484561' . $type . ucfirst($this->lang));

        if ((null != ($code = $this->getProvider($parser))) && ($code !== 'hhonors')) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody[0]) !== false || stripos($body, $this->reBody[1]) !== false) {
            foreach ($this->reBody2 as $re) {
                if (is_string($re)
                    && (stripos($body, $re) !== false || $this->http->XPath->query("//*[contains(normalize-space(),\"{$re}\")]")->length > 0)) {
                    return true;
                } elseif (is_array($re)) {
                    foreach ($re as $r) {
                        if (stripos($body, $r) !== false || $this->http->XPath->query("//*[contains(normalize-space(),\"{$r}\")]")->length > 0) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        /*
        types:
            parseEmail_1()
            parseEmail_2()
            parseEmail_3()
            parseEmail_4() * langs
            parseUrl()
        */
        return 4 + count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'hhonors') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("(//*[contains(normalize-space(text()), '{$str}')]/ancestor-or-self::td[1]/following-sibling::td[1])[1]");
    }

    private function parseEmail_1(Email $email)
    {
        $this->logger->debug(__METHOD__);
        // it-3484561.eml, it-3486719.eml, it-3642335.eml, it-3686255.eml, it-3692695.eml

        $text = text($this->http->Response["body"]);
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation(re("#Confirmation\s+(?:Number|\#)\s*:[^\w]*(\w+)#", $text));

        $pax = trim($this->http->FindSingleNode("(//text()[contains(normalize-space(.),'see you on')]/preceding::text()[normalize-space(.)!=''][1])[1]"),
            ', ');

        if (!empty($pax)) {
            $h->general()->traveller($pax);
            $accountNumber = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'points as of')]/ancestor::td[1]/preceding::text()[starts-with(normalize-space(.),'{$pax}')][1]/following::text()[normalize-space(.)!=''][1])[1]");

            if (!empty($accountNumber)) {
                $h->program()->account($accountNumber, false);
            }
        }

        $guests = re("#([\d]+)[^\w]*\s+Adult#", $this->getField("Guests:"));

        if (empty($guests)) {
            $guests = re("#^([\d]+)$#", $this->getField("Guests:"));
        }
        $kids = re("#(\d+)\s+Child#", $this->getField("Guests:"));
        $h->booked()
            ->guests($guests)
            ->kids($kids, false, true);

        $hotelName = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::table[descendant::text()[starts-with(normalize-space(.),'T:')]][1]//text()[normalize-space(.)!=''])[1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::td[descendant::text()[starts-with(normalize-space(.),'T:')]][1]//text()[normalize-space(.)!=''])[1]");
        }
        $address = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::table[descendant::text()[starts-with(normalize-space(.),'T:')]][1]//text()[normalize-space(.)!=''])[2]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::td[descendant::text()[starts-with(normalize-space(.),'T:')]][1]//text()[normalize-space(.)!=''])[2]");
        }
        $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/ancestor::table[2]/preceding-sibling::table[1]//a/@value");

        if (empty($phone)) {
            $str = preg_replace("#\s+#", ' ', implode(' ',
                $this->http->FindNodes("(//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::table[descendant::text()[starts-with(normalize-space(.),'T:')]][1]//text()[normalize-space(.)!=''])")));

            if (empty($str)) {
                $str = preg_replace("#\s+#", ' ', implode(' ',
                    $this->http->FindNodes("(//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::td[descendant::text()[starts-with(normalize-space(.),'T:')]][1]//text()[normalize-space(.)!=''])")));
            }

            if (preg_match("#T:[^\w]*\s*([\d \-]{6,})#", $str, $m)) {
                $phone = $m[1];
            }
        }
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        $checkInDate = strtotime(
            re("#(\w+)[,\.]?\s+(\w+)[,\.]\s+(\d+)#",
                $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::text()[normalize-space(.)!=''][1]")) . ' ' . re(2) . ' ' . re(3) . ", " .
            re("#\d+:\d+\s+[AP]M#", $this->getField("Check In:"))
        );
        $checkOutDate = strtotime(
            re("#(\w+)[,\.]?\s+(\w+)[,\.]\s+(\d+)$#",
                $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Confirmation Number:') or contains(normalize-space(.), 'Confirmation #:')]/preceding::text()[normalize-space(.)!=''][1]")) . ' ' . re(2) . ' ' . re(3) . ", " .
            re("#\d+:\d+\s+[AP]M#", $this->getField("Check Out:"))
        );
        $h->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        if (preg_match("#[^\w]*[\s]*([\d]+)#", $this->getField("Rooms:"), $m)) {
            $h->booked()->rooms($m[1]);
        }

        $rate = $this->getField("Rate per night");

        if (empty($rate)) {
            $nodes = $this->http->FindNodes("//*[contains(normalize-space(text()), 'Rate per night')]/ancestor-or-self::td[1]/ancestor::tr[1]/following-sibling::tr/td[last()][normalize-space()!='']");

            foreach ($nodes as $node) {
                if (preg_match("#^(\d[\d\.]*)\s+([A-Z]{3})$#", $node, $m)) {
                    $rateArr[] = $m[1];
                    $cur = $m[2];
                }
            }

            if (isset($cur, $rateArr) && count($rateArr) > 0) {
                $r = (float) array_sum($rateArr) / count($rateArr);
                $rate = 'Avg rate: ' . $r . ' per night';
            }
        }

        $rateType = $this->http->FindSingleNode("(//*[normalize-space(text())='Your Rate Information:' or normalize-space(text())='Your Plan Information:' or normalize-space(text())='Your Plan Information:']/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)!=''])[1][not(contains(.,':'))]");
        $roomType = trim($this->http->FindSingleNode("(//*[normalize-space(text())='Rooms:']/ancestor::tr[1]/../tr[1]//text()[normalize-space(.)!=''])[1][not(contains(normalize-space(.),'Your Room Information'))]"),
            ', ');

        if (!empty($rate) || !empty($rateType) || !empty($roomType)) {
            $r = $h->addRoom();
            $r->setType($roomType, true)
                ->setRate($rate, false, true)
                ->setRateType($rateType, false, true);
        }

        $cancellationPolicy = implode(" ",
            $this->http->FindNodes("//*[normalize-space(text())='RATE RULES AND CANCELLATION POLICY:' or normalize-space(text())='Rate Rules and Cancellation Policy:']/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]"));

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->http->FindSingleNode("//*[normalize-space(text())='RATE RULES AND CANCELLATION POLICY:' or normalize-space(text())='Rate Rules and Cancellation Policy:']/ancestor::tr[1]/following-sibling::tr[1]");
        }
        $h->general()->cancellation($cancellationPolicy, false, true);

        $tax = cost($this->getField("Taxes:"));

        if (!empty($tax)) {
            $h->price()
                ->tax($tax);
        }
        $h->price()
            ->cost(cost($this->getField("Rate:")), false, true)
            ->total(cost($this->getField("Total:")), false, true)
            ->currency(currency($this->getField("Total:")), false, true);

        if (!$h->getCheckInDate() && !$h->getCheckOutDate() && !$h->getNoCheckInDate() && !$h->getNoCheckOutDate()) {
            return false;
        }

        return true;
    }

    private function parseEmail_2(Email $email)
    {
        $this->logger->notice(__METHOD__);
        // it-3486852.eml, it-7163152.eml

        $text = text($this->http->Response["body"]);

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation(re("#Confirmation\s*:\s*(\w+)#", $text))
            ->traveller(str_replace(",", "",
                $this->http->FindSingleNode("//text()[contains(.,'we look forward to')]/preceding::text()[normalize-space(.)!=''][1]")));
        $phone = $this->http->FindSingleNode("(//text()[contains(., 'Confirmation:')]/ancestor::table[2]/preceding-sibling::table[1]//text()[normalize-space(.)!='' and not(ancestor::*[self::style])])[3]",
            null, true, "#T:\s*([\d\-\+\(\)\.]{5,})#");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Confirmation:')]/ancestor::table[2]/preceding-sibling::table[1]//text()[normalize-space(.)!='' and not(ancestor::*[self::style])])[position()=3 and contains(normalize-space(.),'T:')]/following::text()[1]",
                null, true, "#^\s*([\d\-\+\(\)\.]{5,})\s*$#");
        }

        if (empty($phone)) {
            $phone = re("#T:\s*([\d\-\+\(\)\. ]{5,})\b#", implode(" ",
                $this->http->FindNodes("(//text()[contains(., 'Confirmation:')]/ancestor::table[2]/preceding-sibling::table[1]//text()[normalize-space(.)!='' and not(ancestor::*[self::style])])")));
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("(//text()[contains(., 'Confirmation:')]/ancestor::table[2]/preceding-sibling::table[1]//text()[normalize-space(.) and not(ancestor::*[self::style])])[1]"))
            ->address(nice($this->http->FindSingleNode("(//text()[contains(., 'Confirmation:')]/ancestor::table[2]/preceding-sibling::table[1]//text()[normalize-space(.)!='' and not(ancestor::*[self::style])])[2]")))
            ->phone($phone);

        $h->booked()
            ->checkIn(strtotime(
                preg_replace("#(\d+),\s+(\w+)\.\s+(\d{4})#", "$1 $2 $3",
                    $this->http->FindSingleNode("//text()[contains(., 'Confirmation:')]/preceding::text()[normalize-space(.)!=''][1]",
                        null, true, "#(\w+.\s+\d+,\s+\d+|\d+,\s+\w+\.\s+\d{4})#")) . ", " .
                '00:00'
            ))
            ->checkOut(strtotime(
                preg_replace("#(\d+),\s+(\w+)\.\s+(\d{4})#", "$1 $2 $3",
                    $this->http->FindSingleNode("//text()[contains(., 'Confirmation:')]/preceding::text()[normalize-space(.)!=''][1]",
                        null, true, "#(\w+.\s+\d+,\s+\d+|\d+,\s+\w+\.\s+\d{4})$#")) . ", " .
                '00:00'
            ));

        if (!empty($h->getConfirmationNumbers()) && !empty($h->getHotelName()) && !empty($h->getCheckInDate())) {
            return true;
        } else {
            $email->removeItinerary($h);
        }
    }

    private function parseEmail_3(Email $email)
    {
        $this->logger->notice(__METHOD__);
        // it-4237694.eml, it-4237694.eml

        $text = text($this->http->Response["body"]);

        $h = $email->add()->hotel();

        // travellers
        $phrases['traveller'] = ["We're sorry to see you go!", "we’re sorry to see you go!", 'were sorry to see you go'];
        $travellerName = $this->http->FindSingleNode("//td[not(.//td) and {$this->contains($phrases['traveller'])}]", null, true, "/^\s*({$this->patterns['travellerName']})\s*\.?,\s*{$this->opt($phrases['traveller'])}/");
        $h->general()->traveller($travellerName);

        $h->general()
            ->confirmation(re("/(?:Cancellation\s+Number\s*:|Cancellation #)[^\w]*(\w+)/", $text))
            ->status('cancellation')
            ->cancelled();

        // hotelName
        // address
        // phone
        $hotelInfoTexts = $this->http->FindNodes("//text()[contains(normalize-space(.),'Cancellation Number:')]/ancestor::table[2]/preceding-sibling::table[normalize-space(.)][1]/descendant::text()[normalize-space(.)]");

        if (count($hotelInfoTexts) < 3) {
            $xpathFragmentHotel1 = '//text()[contains(normalize-space(.),"Stay Dates:")]/ancestor::td[1]/descendant::text()';
            $xpathFragmentHotel2 = '(contains(normalize-space(.),"by Hilton"))';
            $hotelInfoTexts = $this->http->FindNodes("{$xpathFragmentHotel1}[{$xpathFragmentHotel2}] | {$xpathFragmentHotel1}[normalize-space(.) and  ./preceding::text()[{$xpathFragmentHotel2}] ]");
        }
        $hotelInfoText = implode("\n", $hotelInfoTexts);

        if (strpos($hotelInfoText, 'Stay Dates:') !== false || empty($hotelInfoText)) {
            $hotelInfoTexts = $this->http->FindNodes("{$xpathFragmentHotel1}[starts-with(normalize-space(),'T:')]/preceding::text()[normalize-space()][2]");

            $fragmentTexts = $this->http->FindNodes("{$xpathFragmentHotel1}[normalize-space()]");

            if (isset($hotelInfoTexts[0])) {
                $key = array_search($hotelInfoTexts[0], $fragmentTexts);
                $hotelInfoTexts = $this->http->FindNodes("{$xpathFragmentHotel1}[normalize-space()!=''][position()>{$key}]");
                $hotelInfoText = implode("\n", $hotelInfoTexts);
            }
        }

        $hotelName = empty($hotelInfoTexts[0]) ? '' : $hotelInfoTexts[0];
        $address = empty($hotelInfoTexts[1]) ? '' : $hotelInfoTexts[1];
        $phone = $this->http->FindSingleNode("//text()[contains(., 'Cancellation Number:')]/ancestor::table[2]/preceding-sibling::table[1]//a/@value");

        if (empty($phone) && !empty($hotelInfoTexts[2]) && preg_match("/T:\s*({$this->patterns['phone']})/", $hotelInfoTexts[2], $matches)) {
            $phone = $matches[1];
        }

        if (empty($phone) && preg_match("/\bT:\s*({$this->patterns['phone']})/", $hotelInfoText, $matches)) {
            $phone = $matches[1];
        }
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone(preg_replace('/\s+/', ' ', $phone), true, true);

        // checkInDate
        // checkOutDate
        $datesText = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Stay Dates:")]/following::text()[normalize-space(.)][1]', null, true, '/.{6,}–.{6,}/');

        if (!$datesText) {
            $datesText = $this->http->FindSingleNode("//text()[contains(., 'Cancellation Number:')]/preceding::text()[normalize-space(.)][1]");
        }
        $dates = preg_split('/\s*–\s*/', $datesText);

        if (count($dates) === 2) {
            if (preg_match("/^\d+, \w+\. \d{4}$/", $dates[0]) && preg_match("/^\d+, \w+\. \d{4}$/", $dates[1])) {
                $dates[0] = str_replace([",", "."], '', $dates[0]);
                $dates[1] = str_replace([",", "."], '', $dates[1]);
            }
            $h->booked()
                ->checkIn2($dates[0])
                ->checkOut2($dates[1]);
        }
        $timeCheckInText = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Check In:")]/ancestor::td[1]/following-sibling::td[1]', null, true, "/\d+:\d+\s+[AP]M/i");

        if (!empty($timeCheckInText) && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($timeCheckInText, $h->getCheckInDate()));
        }
        $timeCheckOutText = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Check Out:")]/ancestor::td[1]/following-sibling::td[1]', null, true, "/\d+:\d+\s+[AP]M/i");

        if (!empty($timeCheckOutText) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($timeCheckOutText, $h->getCheckOutDate()));
        }

        return true;
    }

    private function parseEmail_4(Email $email)
    {
        $this->logger->debug(__METHOD__);
        // it-23422671.eml, it-23443069.eml, it-30995720.eml, it-31486387.eml

        $roots = $this->http->XPath->query("//text()[{$this->eq($this->t('Your Upcoming Stay'))}][1]/ancestor::*[title][1]");
        $itRoot = $roots->length > 0 ? $roots->item(0) : null;

        $html = $this->http->FindHTMLByXpath('.', null, $itRoot);
        $text = text($html);

        $h = $email->add()->hotel();

        $intro = $this->http->FindSingleNode("descendant::tr[{$this->starts($this->t('Your reservation for'))}]", $itRoot);

        if (preg_match("/has been (confirmed)[,.;!]/", $intro, $m)) {
            $h->general()->status($m[1]);
        }

        if ($confno = re("/{$this->opt($this->t('Confirmation'))}\s*(?:Number|#|\:)\s*([A-Z\d]{5,})\b/i", $text)) {
            $h->general()->confirmation($confno, $this->t('Confirmation'));
        } elseif ($confno = re("/{$this->opt($this->t('Confirmation'))}\s*#\s*([A-Z\d]{5,})\b/i", $this->emailSubject)) {
            $h->general()->confirmation($confno, $this->t('Confirmation'));
        } elseif (!empty(re("/{$this->opt($this->t('Confirmation'))}\s*(?:Number|#|\:|)\s*({$this->opt($this->t('Your Room Information'))})\b/i", $text))) {
            $h->general()->noConfirmation();
        }

        $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Your Upcoming Stay'))}][1]/following::text()[normalize-space()][1]", $itRoot);
        $address = $phone = null;
        $str = implode("\n",
            $this->http->FindNodes("(descendant::text()[normalize-space()='{$this->t('Your Upcoming Stay')}'][1]/following::table[descendant::text()[starts-with(normalize-space(.),'T:')] and not(ancestor::*[contains(@style,'display:none')])][1]//text()[normalize-space()])", $itRoot));

        if (preg_match("#\s*.+\n+(?<address>[\s\S]+)\n+[ ]*T:\s*(?<phone>[\s\S]*?)(?:{$this->t('Confirmation')}|$)#", $str, $matches)) {
            $address = preg_replace('/\s+/', ' ', trim($matches['address']));

            if (preg_match('/^([+(\d][-.\s\d)(]{5,}[\d)])/', $matches['phone'], $m)) {
                $phone = preg_replace('/\s+/', ' ', $m[1]);
            }
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("descendant::img[{$this->contains('/Location_Icon.', '@src')} or {$this->eq('Maps & Directions', '@alt')}]/ancestor::tr[1]/preceding::tr[normalize-space()][1][ descendant-or-self::*[{$this->contains('#464646', '@style')} or {$this->eq('#464646', '@bgcolor')}] ]", $itRoot);
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("descendant::img[{$this->contains('/Location_Icon.', '@src')} or {$this->eq('Maps & Directions', '@alt')}]/ancestor::tr[1]/preceding::tr[normalize-space()!=''][1][ count(.//a)=1 ]", $itRoot);
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->starts('Maps & Directions')}]/ancestor::table[1]/preceding::text()[normalize-space()!=''][1]", $itRoot);
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Maps & Directions'))}]/ancestor::table[1]/preceding::text()[normalize-space()!=''][1]", $itRoot);
        }

        if (!$address) {
            $addressTexts = $this->http->FindNodes("descendant::img[{$this->contains('/Location_Icon.', '@src')} or {$this->eq('Maps & Directions', '@alt')}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::tr[normalize-space() and not(descendant::a)]", $itRoot);
            $address = preg_replace("/\s*{$this->opt('Maps & Directions')}.*/", '', implode(' ', $addressTexts));
        }

        if (!$address) {
            $address = implode(' ', $this->http->FindNodes("descendant::text()[{$this->starts($this->t('Maps & Directions'))}]/ancestor::table[1]/descendant::text()[normalize-space()!=''][not({$this->starts('Maps & Directions')})]", $itRoot));
        }

        if (!$phone) {
            $phone = $this->http->FindSingleNode("descendant::img[{$this->contains('/phone_icon.', '@src')} or {$this->eq('contact us', '@alt')}]/ancestor::td[1]/following-sibling::td[normalize-space()]", $itRoot, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        }

        if (!$phone) {
            $phone = $this->http->FindSingleNode("descendant::text()[{$this->starts('Maps & Directions')}]/ancestor::table[1]/following::text()[normalize-space()!=''][1]", $itRoot, true, '/[+(\d][-. \d)(]{5,}[\d)]/u');
        }
        $h->hotel()
            ->name($hotelName);

        $address = trim($address, '> ');

        if (!empty($address)) {
            $h->hotel()
                ->address($address)
                ->phone($phone, false, true);
        } elseif (!empty(re("/{$this->opt($this->t('Confirmation'))}\s*(?:Number|#|\:)\s*([A-Z\d]{5,})\b/i", $hotelName))) {
            // FE: it-46506118.eml
            $maybeJunk = true;
            $h->hotel()->noAddress();
        }

        $date = 0;
        $travellerDate = $this->http->FindSingleNode("descendant::tr[not(.//tr) and contains(normalize-space(),'{$this->t('see you on')}')][1]", $itRoot);

        if (empty($travellerDate)) {
            $travellerDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation for'))}]");
        }

        if (preg_match("/{$this->t('see you on')} (.+)/i", $travellerDate, $matches)) {
            $date = preg_replace("/^\s*([^\d\W]{3,})-(\d{1,2})-(\d{4})\s*$/u", '$2 $1 $3', $matches[1]); // Sep-01-2018    ->    01 Sep 2018
            $h->booked()->checkIn(strtotime($date));
        } elseif (preg_match("/,\s+(.+)\s+{$this->t('see you on')} (.+)/i", $travellerDate, $matches)) {
            $date = preg_replace("/^\s*([^\d\W]{3,})-(\d{1,2})-(\d{4})\s*$/u", '$2 $1 $3', $matches[1]); // Sep-01-2018    ->    01 Sep 2018
            $h->booked()->checkIn(strtotime($date));
        } elseif (preg_match("/{$this->opt($this->t('Your reservation for'))}\s*(\w+\-\d+\-\d+)\s/i", $travellerDate, $matches)) {
            $date = preg_replace("/^\s*([^\d\W]{3,})-(\d{1,2})-(\d{4})\s*$/u", '$2 $1 $3', $matches[1]); // Sep-01-2018    ->    01 Sep 2018
            $h->booked()->checkIn(strtotime($date));
        }

        $guestName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Guest Name:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $itRoot, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (!$guestName) {
            $guestName = preg_match("/(.+),\s*.*?{$this->t('see you on')}/i", $travellerDate, $m) ? $m[1] : null;
        }

        if ($guestName) {
            $h->general()->traveller($guestName);
        }

        $account = $this->http->FindSingleNode("descendant::tr[{$this->starts($this->t('Honors Reward ID:'))}][1]", $itRoot,
            true, "#:\s*(\d{5,})\b#");

        if (!empty($account)) {
            $h->program()->account($account, false);
        }

        $guest = $this->http->FindSingleNode("descendant::text()[normalize-space()='{$this->t('Guests:')}'][1]/following::text()[normalize-space()][1]", $itRoot);

        if (preg_match("#([\d]+)[^\w]*\s+{$this->t('Adult')}#", $guest, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("#([\d]+)[^\w]*\s+{$this->t('Child')}#", $guest, $m)) {
            $h->booked()->kids($m[1]);
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("descendant::text()[normalize-space()='{$this->t('Rooms:')}'][1]/following::text()[normalize-space()][1]",
                $itRoot, true, "#^\s*(\d+)\s*$#"), false, true);

        $rules = [
            "contains(@src,'check_in=') and contains(@src,'check_in_t=')",
            "contains(@src,'check_in%3d') and contains(@src,'check_in_t%3d')",
            "contains(@src,'check_in%3D') and contains(@src,'check_in_t%3D')",
        ];
        $search = implode(' or ', $rules);
        $dateTimeNode = $this->http->FindSingleNode("(descendant::img[{$search}]/@src)[1]", $itRoot);

        if (!empty($dateTimeNode)) {
            $dateTimeNode = str_ireplace(
                ['%20', '+', '%3A', '%3D', '%253a', '%26'],
                [' ', ' ', ':', '=', ':', '&'],
                $dateTimeNode
            );
            $dateIn = $this->http->FindPreg("#check_in=(.+?)(?:\&|$)#u", false, $dateTimeNode);
            $timeIn = $this->http->FindPreg("#check_in_t=(.*?)(?:\&|$|%)#u", false, $dateTimeNode);

            if (empty($h->getCheckInDate()) && !empty($dateIn)) {
//                $this->logger->warning('1');
                if (!empty($this->re("/^(\d{4}.\d+.\d+)/", $dateIn))) {
                    $h->booked()->checkIn(strtotime($dateIn));
                }
            }

            if (!empty($timeIn) && !empty($h->getCheckInDate())) {
//                $this->logger->warning('2');
                $h->booked()->checkIn(strtotime($timeIn, $h->getCheckInDate()));
            }

            $dateOut = $this->http->FindPreg("#check_out=(.+?)(?:\&|$)#", false, $dateTimeNode);
            $timeOut = $this->http->FindPreg("#check_out_t=(.*?)(?:\&|$)#", false, $dateTimeNode);

            if (!empty($dateOut) && !empty($timeOut) && !empty($this->re("/^(\d{4}.\d+.\d+)/", $dateOut))) {
                $h->booked()->checkOut(strtotime($dateOut . ' ' . $timeOut));
            } elseif (!empty($dateOut) && !empty($this->re("/^(\d{4}.\d+.\d+)/", $dateOut))) {
                $h->booked()->checkOut(strtotime($dateOut));
            }
        }

        if (empty($h->getCheckInDate()) && empty($h->getCheckOutDate())) {
            $url = urldecode($this->http->FindSingleNode("//img[" . $this->eq($this->t("View Booking Details"), '@alt') . "]/ancestor::a[contains(@href, 'hilton.com')][1]/@href"));
//            $this->logger->debug('$url = '.print_r( $url,true));
            // http://l.h4.hilton.com/rts/go2.aspx? ... =|2020-11-10|2020-11-15|EN|12:00|16:00|VILOWN|HRCC DO NOT BOOK|5530702167|72372
            // ... =|2019-01-18|2019-01-23|en|11:00+AM|3:00+PM| ...
            // ... =|2018-09-01|2018-09-02|EN|12:00 PM|3:00 PM| ...
            $dateFormat = '\d{4}-\d{2}-\d{2}';
            $timeFormat = '\d{1,2}:\d{2}(?:\W[ap]m)?';

            if (preg_match("/\|(?<ciD>{$dateFormat})\|(?<coD>{$dateFormat})\|[A-Za-z]{2}\|(?<ciT>{$timeFormat})\|(?<coT>{$timeFormat})\|/i", $url, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m['ciD'] . ' ' . preg_replace('/\W([ap]m)/', '$1', $m['ciT'])))
                    ->checkOut(strtotime($m['coD'] . ' ' . preg_replace('/\W([ap]m)/', '$1', $m['coT'])))
                ;
            }
        }

        $roomType = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Room Plan:'))}]/following-sibling::td[normalize-space()][1]", $itRoot);

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("(descendant::tr[{$this->eq($this->t('Your Room Information'))}]/following::text()[normalize-space()][1][not(contains(.,':')) and not(ancestor::*[contains(@style,'display:none') or contains(@style,'display: none')])])[1]", $itRoot);
        }

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("descendant::tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Rooms:'))}] ]/preceding-sibling::tr[normalize-space()][1][not(contains(normalize-space(.),'{$this->t('Your Room Information')}')) and not(contains(.,':'))]", $itRoot);
        }
        $rateType = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Your Rate Information'))}]/following-sibling::td[normalize-space()][1][not({$this->contains($this->t('Your Rate Information'))})]", $itRoot);

        if (!$rateType) {
            $rateType = $this->http->FindSingleNode("(descendant::tr[{$this->eq($this->t('Your Rate Information'))}]/following::text()[normalize-space()][1][not(contains(.,':')) and not(ancestor::*[contains(@style,'display:none') or contains(@style,'display: none')])])[1]", $itRoot);
        }

        $rate = $this->http->FindSingleNode("//text()[normalize-space()='Rate:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (!empty($roomType) || !empty($rateType) || !empty($rate)) {
            $r = $h->addRoom();
            $r
                ->setType($roomType, false, true)
                ->setRateType($rateType, false, true)
                ->setRate($rate, false, true)
            ;
        }

        $xpath = "descendant::text()[{$this->contains($this->t('Rate Per Night'))}][1]/ancestor::*[{$this->contains($this->t('Total for Stay Per Room Rate'))}][1]//tr[not(.//tr)][normalize-space()]";
        $nodes = $this->http->XPath->query($xpath, $itRoot);

        if ($nodes->length == 0) {
            $xpath = "descendant::text()[{$this->contains($this->t('Honors Reward'))}][1]/ancestor::*[{$this->contains($this->t('Total for Stay'))}][1]//tr[not(.//tr)][normalize-space()]";
            $nodes = $this->http->XPath->query($xpath, $itRoot);
        }
        $rateInformation = '';

        foreach ($nodes as $root) {
            $rateInformation .= implode(' ', $this->http->FindNodes(".//text()", $root)) . "\n";
        }

        if (!empty($rateInformation)) {
            if (preg_match("#(?:{$this->opt($this->t('Rate Per Night'))}:*|{$this->opt($this->t('Honors Reward ID:'))}.+)\s*([\s\S]+?)\s*{$this->opt($this->t('Total for Stay'))}#", $rateInformation, $m)
                && preg_match_all("/(?:^|\n)\s*(?<d1>[\w\-]*\d{4})(?: - (?<d2>[\w\-]*\d{4}))?\s+(?:(?<rate>\d[\d,.]*)[ ]*(?<curr>[A-Z]{3}|(?i)Points)\b|(?<freeNights>{$this->opt($this->t('5th Standard Reward Night Free'))}))/",
                    $m[1], $ratesText)
            ) {
                $freeNights = null;

                foreach ($ratesText['freeNights'] as $key => $value) {
                    if (preg_match("/^{$this->opt($this->t('5th Standard Reward Night Free'))}$/", $value)
                        && strtotime($ratesText['d1'][$key]) !== false
                        && strtotime($ratesText['d2'][$key]) !== false
                    ) {
                        $date1 = date_create($ratesText['d1'][$key]);
                        $date2 = date_create($ratesText['d2'][$key]);
                        $interval = date_diff($date1, $date2);

                        if ($interval !== false) {
                            $freeNights += $interval->format('%a');
                        }
                    }
                }

                if ($freeNights !== null) {
                    $h->booked()->freeNights($freeNights);
                }

                $ratesText['curr'] = array_values(array_filter($ratesText['curr']));

                if (count(array_unique($ratesText['curr'])) == 1) {
                    $rates = [];

                    $ratesText['rate'] = array_values(array_filter($ratesText['rate']));

                    foreach ($ratesText['rate'] as $value) {
                        $value = PriceHelper::cost($value);
                        $rates[] = is_numeric($value) ? (float) $value : null;
                    }
                    $rates = array_values(array_unique(array_filter($rates)));

                    if (count($rates) == 1) {
                        if (!isset($r)) {
                            $r = $h->addRoom();
                        }
                        $r->setRate($rates[0] . ' ' . $ratesText['curr'][0]);
                    } elseif (count($rates) > 1) {
                        sort($rates);

                        if (!isset($r)) {
                            $r = $h->addRoom();
                        }
                        $r->setRate($rates[0] . '-' . $rates[count($rates) - 1] . ' ' . $ratesText['curr'][0]);
                    }
                }

                if (empty($h->getCheckInDate())) {
                    $date = strtotime(preg_replace("/^\s*([^\d\s]+)-(\d{1,2})-(\d{4})\s*$/", '$2 $1 $3',
                        $ratesText['d1'][0]));

                    if (!empty($date)) {
                        $h->booked()->checkIn($date);

                        if (!empty($h->getCheckInDate()) && !empty($timeIn)) {
                            $h->booked()->checkIn(strtotime($timeIn, $h->getCheckInDate()));
                        }
                    }
                }

                if (empty($h->getCheckOutDate())) {
                    $lastRate = count($ratesText[0]) - 1;

                    if (!empty($ratesText['d2'][$lastRate])) {
                        $date = strtotime(preg_replace("/^\s*([^\d\s]+)-(\d{1,2})-(\d{4})\s*$/", '$2 $1 $3', $ratesText['d2'][$lastRate]));

                        if (!empty($date)) {
                            $h->booked()->checkOut($date);

                            if (!empty($h->getCheckOutDate()) && !empty($timeOut)) {
                                $h->booked()->checkOut(strtotime($timeOut, $h->getCheckOutDate()));
                            }
                        }
                    } else {
                        $date = strtotime(preg_replace("/^\s*([^\d\s]+)-(\d{1,2})-(\d{4})\s*$/", '$2 $1 $3', $ratesText['d1'][$lastRate]));

                        if (!empty($date)) {
                            $h->booked()->checkOut(strtotime('+1 days', $date));

                            if (!empty($h->getCheckOutDate()) && !empty($timeOut)) {
                                $h->booked()->checkOut(strtotime($timeOut, $h->getCheckOutDate()));
                            }
                        }
                    }
                }
            } else {
                $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate Per Night'))}]/ancestor::table[1]", null, true, "/{$this->opt($this->t('Rate Per Night'))}\s+(.+[A-Z]{3})\s+{$this->opt($this->t('Total for Stay Per Room Rate'))}/");

                if (!empty($rate)) {
                    $r->setRate($rate);
                }
            }

            // p.cost
            if (preg_match("/{$this->opt($this->t('Total for Stay Per Room Rate'))}\s*:*\s*(\d[,.\'\d]+)[ ]*[A-Z]{3}\b/", $rateInformation, $m)) {
                $h->price()->cost(cost($m[1]));
            }

            // p.tax
            if (preg_match("#{$this->t('Taxes')}\s*+(\d[\d\.\,]+)[ ]*[A-Z]{3}\b#", $rateInformation, $m)) {
                $h->price()->tax(cost($m[1]));
            }

            // p.fee
            $fees = (array) $this->t('Resort Charge');

            foreach ($fees as $fee) {
                if (preg_match("#{$fee}\s*+(\d[\d\.\,]+)[ ]*[A-Z]{3}\b#", $rateInformation, $m)) {
                    $h->price()->fee($fee, cost($m[1]));
                }
            }

            // p.total
            // p.currencyCode
            // p.spentAwards
            if (preg_match("#\n[ ]*{$this->opt($this->t('Total for Stay'))}\s+(\d[\d,.]*)[ ]*([A-Z]{3})\b(?:[+\s]+(\d[\d,.]*[ ]*(?i)Points)\b)?#", $rateInformation, $m)) {
                $h->price()
                    ->total(cost($m[1]))
                    ->currency($m[2]);

                if (!empty($m[3])) {
                    $h->price()->spentAwards($m[3]);
                }
            } elseif (preg_match("#{$this->opt($this->t('Total for Stay'))}[\s\S]+?(\d[\d,.]*[ ]*(?i)Points)\b#", $rateInformation, $m)) {
                $h->price()->spentAwards($m[1]);
            }
        }

        if (empty($dateTimeNode) && empty($h->getCheckOutDate())) {
            $h->booked()->noCheckOut();
        }

        // cancellation
        // nonRefundable
        $cancellationTexts = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Rate Rules and Cancellation Policy'))}][1]/ancestor::tr[1]/descendant::text()[contains(.,'•')]", $itRoot);
        $cancellationTexts = array_map(function ($item) { return trim($item, '•,.!?; '); }, $cancellationTexts);
        $cancellation = implode('; ', $cancellationTexts);

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Rate Rules and Cancellation Policy'))}][1]/ancestor::tr[1]/descendant::text()[{$this->starts($this->t('Cancellations were required by '))}][1]");
        }

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);

            if (preg_match('/free to cancel or update your reservation by (?<time>\d{1,2}:\d{2}[ ]*[ap]m) local hotel time on (?<month>\w+)-(?<day>\d{1,2})-(?<year>\d{2,4})/i', $cancellation, $m)
                || preg_match('/Cancelations were required by (?<time>\d{1,2}:\d{2}[ ]*[ap]m) on (?<month>\w+)-(?<day>\d{1,2})-(?<year>\d{2,4}) local hotel time\./i', $cancellation, $m)
                || preg_match('/Cancellations were required by (?<time>\d{1,2}:\d{2}[ ]*[a\.p]*m\.?) on (?<month>\w+)-(?<day>\d{1,2})-(?<year>\d{2,4}) local hotel time\./i', $cancellation, $m)
                || preg_match('/If you wish to cancel\, please do by\s*(?<time>[\d\:]+\s*a?\.?p?\.?m\.)\s*on\s*(?<month>\w+)\-(?<day>\d+)\-(?<year>\d{4})\,\s*to avoid/i', $cancellation, $m)
            ) {
                $h->booked()->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
            } else {
                $h->booked()->parseNonRefundable('Your deposit is non-refundable');
            }
        }

        if (isset($maybeJunk) && $h->validate(false)) {
            // if all parsed ok and wrong hotel info (marked $maybeJunk) -> junk
            // FE: it-46506118.eml
            $email->removeItinerary($h);
            $email->setIsJunk(true);

            return true;
        }

        $this->parseStatement($email);

        return true;
    }

    private function getLink($containedText)
    {
        return "//*[self::a or self::img][" . $this->contains($containedText, '@href') . " or " . $this->contains($containedText, '@src') . "]/attribute::*[name() = 'src' or name()='href']";
    }

    private function parseStatement(Email $email)
    {
        $number = $this->http->FindSingleNode("(" . $this->getLink('mi_num=') . ")[1]", null, true, "/\Wmi_num=(\d{5,})(?:&|$)/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//a[" . $this->eq($this->t("unsubscribe")) . "]/@href", null, true,
                "/&x=[^%]+@[^%]+\.[^%]+%7c[^%]+%7c[^%]+%7c(\d{5,})$/");
        }

        if (!empty($number)) {
            $st = $email->add()->statement();

            $st
                ->setNumber($number)
                ->setLogin($number)
                ->setNoBalance(true)
            ;

            $name = implode(' ', [urldecode($this->http->FindSingleNode("(" . $this->getLink(['mi_FNAME', 'mi_fname']) . ")[1]", null, true, "/\Wmi_FNAME=(.*?)(?:&|$)/i")),
                urldecode($this->http->FindSingleNode("(" . $this->getLink(['mi_LNAME', 'mi_lname']) . ")[1]", null, true, "/\Wmi_LNAME=(.*?)(?:&|$)/i")), ]);

            if (!empty($name)) {
                $st->addProperty("Name", $name);
            }

            $status = $this->http->FindSingleNode("(" . $this->getLink('mi_tier') . ")[1]", null, true, "/\Wmi_tier=([A-Z]+)(?:&|$)/");

            switch ($status) {
                case 'D':
                    $st->addProperty('Status', 'Diamond');

                    break;

                case 'G':
                    $st->addProperty('Status', 'Gold');

                    break;

                case 'S':
                    $st->addProperty('Status', 'Silver');

                    break;

                case 'B':
                    $st->addProperty('Status', 'Member');

                    break;
            }
        }
    }

    private function parseUrl(Email $email, $url)
    {
        $this->http2 = clone $this->http;
        $this->http2->GetURL($url);

        $h = $email->add()->hotel();

        $confirmation = $this->http2->FindSingleNode("//text()[{$this->starts($this->t('Reservation Confirmation'))}]");

        if (preg_match("/({$this->starts($this->t('Reservation Confirmation'))})[\s#]+([A-Z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $checkInDate = $this->http2->FindSingleNode("//td[{$this->eq($this->t('Arrival:'))}]/following-sibling::td[normalize-space()][1]");
        $h->booked()->checkIn2($checkInDate);

        $checkOutDate = $this->http2->FindSingleNode("//td[{$this->eq($this->t('Departure:'))}]/following-sibling::td[normalize-space()][1]");
        $h->booked()->checkOut2($checkOutDate);

        // TODO: need finished this method
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
