<?php

namespace AwardWallet\Engine\hhonors\Email;

class It5958405 extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-5850777.eml, hhonors/it-5854949.eml, hhonors/it-5958405.eml, hhonors/it-6091743.eml, hhonors/it-6601937.eml, hhonors/it-58980096.eml, hhonors/it-59571616.eml, hhonors/it-123419898-de.eml, hhonors/it-123629088-ja.eml, hhonors/it-124346049-pl.eml";

    public $reFrom = "@Hilton.com";
    public $reSubject = [
        "fr" => "Your receipt from",
        "en" => "Receipt A for guest", "We hope you enjoyed your stay at",
    ];
    public $reBody = ['hilton', 'DOUBLETREE', '@adinahotels.com', 'Adina Apartment Hotel', 'www.hamptoninn.com', 'www.embassysuites.com', 'visit Honors.com', 'www.homewoodsuites.com'];
    public $reBody2 = [
        "fr" => ["Date d'Arrivée", 'Date d\'arrivée', 'Arrivée', 'Date d\'arrivee'],
        "de" => ["ANKUNFTSDATUM",],
        "ja" => ["到着日",],
        "pl" => ["Data Przyjazdu",],
        "en" => ["Arrival Date",],
        "es" => ["FECHA DE SALIDA",],
    ];

    public static $dictionary = [
        "fr" => [ // ?
             'Confirmation Number' => 'Numero de confirmation',
            'Tel'            => ['Tel', 'TELEPHONE', 'Téléphone'],
             'FAX' => ['TELECOPIE', 'FAX'],
            'Arrival Date'   => ["Date d'Arrivée", 'Arrivée', 'Date d\'arrivee'],
            'Departure Date' => ['Date de départ', "Départ", "Date de depart:"],
            'Adult/child'    => ['Adulte/Enfant', 'Adultes/Enfants'],
            'Room Rate'      => ['Tarif de la chambre', 'Tarif Chambre'],
            'Room No'        => ['Numéro de chambre', 'Chambre'],
        ],
        "de" => [ // it-123419898-de.eml
            // 'Confirmation Number' => '',
            'Tel'            => ['Tel', 'TELEFON'],
            'FAX'            => ['FAX', 'TELEFAX'],
            'Arrival Date'   => 'ANKUNFTSDATUM',
            'Departure Date' => 'ABREISEDATUM',
            'Adult/child'    => 'Adult/Child',
            'Room Rate'      => 'ZIMMERPREIS',
            'Room No'        => 'ZIMMER NUMMER',
        ],
        "ja" => [ // it-123629088-ja.eml
            'Confirmation Number' => '予約番号',
            'Tel'                 => '電話',
            'FAX'                 => 'ファックス',
            'Arrival Date'        => '到着日',
            'Departure Date'      => '出発日',
            'Adult/child'         => '人数',
            'Room Rate'           => '部屋料金',
            'Room No'             => '部屋番号',
        ],
        "pl" => [ // it-124346049-pl.eml
            'Confirmation Number' => 'Potwierdzenie #',
            'Tel'                 => 'Telefon',
            // 'FAX'            => '',
            'Arrival Date'   => 'Data Przyjazdu',
            'Departure Date' => 'Data Wyjazdu',
            'Adult/child'    => 'Liczba Doroslych i Dzieci',
            'Room Rate'      => 'Stawka za Pokój',
            'Room No'        => 'Nr Pokoju',
        ],
        "es" => [
            'Confirmation Number' => ['CONFIRMACION:', 'Confirmation Number:'],
            'Tel'                 => ['TELEPHONE', 'TELEFONO'],
             'FAX'            => 'FAX',
            'Arrival Date'   => ['FECHA DE LLEGADA:', 'FECHA DE LLEGAD'],
            'Departure Date' => ['FECHA DE SALIDA:', 'FECHA DE SALIDA'],
            'Adult/child'    => ['# ADULTOS/NINOS:', 'ADULTO/NINO'],
            'Room Rate'      => ['POR HABITACION:', 'TARIFA'],
            'Room No'        => ['HABITACION:', '# DE HAB'],
        ],
        "en" => [
            'Confirmation Number' => ['Confirmation Number', 'Your Reference'],
            'Tel'                 => ['Tel', 'TELEPHONE', 'Phone'],
            'Adult/child'         => ['Adult/child', 'Adults/Children'],
            'Room No'             => ['Room No', 'Room Number'],
        ],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries, string $subject): void
    {
        $text = $this->pdf->Response["body"];
        // echo $text;
        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        if (!$it['ConfirmationNumber'] = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Confirmation Number'))}[ ]*:\n+([-A-Z\d]{5,})(?:[ ]{2}|$)/imu", $text)) {
            if (!$it['ConfirmationNumber'] = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Confirmation Number'))}(?:[ ]*:[ ]+|[ ]{1,5})([-A-Z\d]{5,})(?:[ ]{2}|$)/imu", $text)) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }
        }

        // HotelName
        // Address
        foreach ([
            "/(?<name>[\s\S]{2,}?[,\/-]\n[ ]*.{2,}[^\/-])\n(?<address>(?:.+\n+){2,4}).*\b{$this->opt($this->t('Tel'))}[:\s]+/iu",
            "/(?<name>[\s\S]{2,}?[^\/-])\n(?<address>(?:.+\n+){2,4}).*\b{$this->opt($this->t('Tel'))}[:\s]+/iu",
        ] as $pattern) {
            // it-5854949.eml
            if (!preg_match($pattern, $text, $m)) {
                continue;
            }

            $hotelName_temp = preg_replace('/\s+/', ' ', preg_replace('/(\S[,\/-])\n+[ ]*(\S)/', '$1$2', $m['name']));
            $hotelName_tempRE = preg_replace('/\s*([^\w\s]+)\s*/u', ' $1 ', $hotelName_temp);

            if (preg_match_all("/" . str_replace(' ', '\s*', preg_quote($hotelName_tempRE, '/')) . "/", $text, $matches) && count($matches[0]) > 1
                || stripos($subject, $hotelName_temp) !== false
            ) {
                $it['HotelName'] = trim($hotelName_temp, ', ');
                $it['Address'] = preg_replace('/[, ]*\n+[, ]*/', ', ', trim($m['address']));

                break;
            } else {
                $nameFromSubject = $this->re("/We hope you enjoyed your stay at the(.+) - come again soon!/", $subject);
                if (preg_match("/^.*{$nameFromSubject}$/i", $hotelName_temp)) {
                    $it['HotelName'] = trim($hotelName_temp, ', ');
                    $it['Address'] = preg_replace('/[, ]*\n+[, ]*/', ', ', trim($m['address']));
                }

            }
        }

        if (empty($it['HotelName'])) {
            // it-58980096.eml
            $hotelName_temp = $this->re('/([\s\S]{2,}?[^,\/-])\n/', $text);

            if ($hotelName_temp) {
                $hotelName_temp = preg_replace('/\s+/', ' ', $hotelName_temp);
            }

            if (stripos($text, $hotelName_temp) !== strripos($text, $hotelName_temp)) {
                $it['HotelName'] = $hotelName_temp;
            }

            if (!empty($it['HotelName'])
                && preg_match("/" . preg_quote($it['HotelName'], '/') . ".*\n(?<address>(?:.+\n){1,3}).*\b{$this->opt($this->t('Tel'))}[:\s]+/iu", $text, $m)
            ) {
                $m['address'] = preg_replace('/^[ ]*ABN \d.+$\n*/m', '', trim($m['address'])); // ABN 36 062 326 176
                $it['Address'] = preg_replace('/[ ]*\n+[ ]*/', ', ', $m['address']);
            }
        }

        //try to check format
        $date = $this->re("#{$this->opt($this->t('Arrival Date'))}[:\s]+(.+)#iu", $text);
        $date2 = $this->re("#{$this->opt($this->t('Departure Date'))}[:\s]+(.+)#iu", $text);

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})(?:\s+\d+:\d+.*)?$#", $date, $m)) {
            $date = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})#", "$1 " . "20$2", $date));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})(?:\s+\d+:\d+.*)?$#", $date2, $m)) {
            $date2 = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})#", "$1 " . "20$2", $date2));
        }

        if ($this->identifyDateFormat($date, $date2) === 1) {
            $date = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)#", "$2.$1.$3", $date);
            $date2 = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)#", "$2.$1.$3", $date2);
        } else {
            $date = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)#", "$1.$2.$3", $date);
            $date2 = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)#", "$1.$2.$3", $date2);
        }
        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($date));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($date2));

        $patterns['phone'] = '[+(\d][-+. \d)(]{5,}[\d)]';

        // Phone
        $it['Phone'] = $this->re("/\b{$this->opt($this->t('Tel'))}[:\s]+({$patterns['phone']})/iu", $text);

        // Fax
        $it['Fax'] = $this->re("/\b{$this->opt($this->t('FAX'))}[:\s]+({$patterns['phone']})/iu", $text);

        // GuestNames
        $guestName = $this->re("#\n\s*(.+?)\s*\n\s*{$this->opt($this->t('Room No'))}#", $text);

        if (preg_match("/^[[:alpha:]][-,.\'’[:alpha:] ]*[[:alpha:]]$/u", $guestName)) {
            $it['GuestNames'][] = $guestName;
        }

        // Guests
        $it['Guests'] = $this->re("/{$this->opt($this->t('Adult/child'))}[:\s]+(\d{1,3})[ ]*\/[ ]*\d{1,3}$/im", $text);

        // Kids
        $it['Kids'] = $this->re("/{$this->opt($this->t('Adult/child'))}[:\s]+\d{1,3}[ ]*\/[ ]*(\d{1,3})$/im", $text);

        // Rate
        $it['Rate'] = $this->re("#{$this->opt($this->t('Room Rate'))}[:\s]+([\d\,\.]+.+)#i", $text);

        // RoomTypeDescription
        $roomNo = $this->re("#\n[ ]*({$this->opt($this->t('Room No'))}[:\s]+.*\d.*)\n#i", $text);

        if ($roomNo !== null) {
            $it['RoomTypeDescription'] = preg_replace('/\s+/', ' ', $roomNo);
        }

        // AccountNumbers
        $accountNumber = $this->re("/(?:Hhonors|Honors|HH|Hilton Honors)[\s#]+(.*\d.*?)\s/i", $text);

        if ($accountNumber) {
            $it['AccountNumbers'] = [$accountNumber];
        }

        // Total
        if (preg_match_all("/^[ ]*{$this->opt($this->t('Total'))}[ ]*[:]+\s*(?<amount>\d[,.\'\d ]*)$/m", $text, $m)
            && count($m['amount']) === 1
        ) {
            $it['Total'] = $this->normalizeAmount($m['amount'][0]);
        }

        // Currency
        if (preg_match_all("/^[ ]*{$this->opt($this->t('Total due'))}[ ]*[:]+\s*(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/m", $text, $m)
            && count($m['currency']) === 1
        ) {
            $it['Currency'] = $m['currency'][0];
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        $pdfs = $parser->searchAttachmentByName('(?:FOLIODETE_\d+|Adina.*Receipt.*)\.pdf');

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }
        $text = substr($text, 0, 4000);

        foreach ($this->reBody as $reBody) {
            if (stripos($text, $reBody) !== false && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->sortedPdf($parser)) {
            return null;
        }

        $this->assignLang($this->pdf->Response['body']);

        $itineraries = [];
        $this->parsePdf($itineraries, $parser->getSubject());

        $result = [
            'emailType'  => 'It5958405' . ucfirst($this->lang),
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

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $res) {
            foreach ($res as $re) {
                if (strpos($text, $re) !== false) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+:\d+\s+[AP]M)$#",
            "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+:\d+)$#",
        ];
        $out = [
            "$2.$1.$3, $4",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function sortedPdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $res = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as &$c) {
                ksort($c);
                $r = "";

                foreach ($c as $t) {
                    $r .= $t . "\n";
                }
                $c = $r;
            }

            ksort($grid);

            foreach ($grid as $r) {
                $res .= $r;
            }
        }
        $this->pdf->setBody($res);

        return true;
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
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

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                if (!empty($diff)) {
                    $min = min($diff);

                    return array_flip($diff)[$min];
                }
            }
        }

        return -1;
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
}
