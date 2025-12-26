<?php

// + bcdtravel "ja"

namespace AwardWallet\Engine\spg\Email;

class ConfirmationHtml2017 extends \TAccountChecker
{
    public $mailFiles = "spg/it-1.eml, spg/it-12677797.eml, spg/it-1637511.eml, spg/it-1651635.eml, spg/it-1729233.eml, spg/it-1787311.eml, spg/it-1797864.eml, spg/it-1851042.eml, spg/it-1857551.eml, spg/it-1894649.eml, spg/it-1912962.eml, spg/it-1929139.eml, spg/it-1968679.eml, spg/it-1968680.eml, spg/it-2.eml, spg/it-2033150.eml, spg/it-2109159.eml, spg/it-2211083.eml, spg/it-2252341.eml, spg/it-2340683.eml, spg/it-2370985.eml, spg/it-2516902.eml, spg/it-2516960.eml, spg/it-2642059.eml, spg/it-2656071.eml, spg/it-2656347.eml, spg/it-2664987.eml, spg/it-2837396.eml, spg/it-2979244.eml, spg/it-3.eml, spg/it-8383483.eml, spg/it-9840562.eml";
    private $lang = 'en';
    private $emailSubject = '';
    private $subject = [
        'Your reservation has been confirmed',
        'Your reservation has been cancelled',
        'Sheraton Reservation #',
        'シェラトンご予約番号',
        'IHRE RESERVIERUNG ',
        'er staat u exploratie te wachten',
        'Confirmación de reserva',
        'Potete dormire sonni tranquilli. La prenotazione è confermata',
        'A sua reserva foi confirmada',
    ];
    private $body = [
        'en' => ['Confirmation:'],
        'de' => ['Bestätigung:', 'BESTÄTIGUNG:'],
        'fr' => ['allez bientôt arriver dans notre hôtel', 'Nous sommes impatients de vous accueillir'],
        'ja' => ['予約確認番号:'],
        'nl' => ['Bevestiging'],
        'es' => ['Confirmación:'],
        'it' => ['Conferma:'],
        'pt' => ['Confirmação:'],
    ];

    private static $dict = [
        'en' => [
            'Cancel Information' => ['and Cancellation Policy', 'Cancel Information', 'Cancellation Details'],
            'Check In'           => ['Check In', 'Arrival Date'],
            'Check Out'          => ['Check Out', 'Departure Date'],
            'ConfirmSubjectRe'   => "#(\d{5,} Confirmation|has been confirmed|Reservation Confirmation|is confirmed)#",
            'CancelSubjectRe'    => "#(Cancellation for|\d{5,} Cancellation)#",
        ],
        'de' => [
            'Confirmation:'      => ['Bestätigung:', 'BESTÄTIGUNG: '],
            'Phone:'             => 'Telefon:',
            'Check In'           => 'Ankunft',
            'Check Out'          => 'Abreise',
            'Guest Name'         => 'Name des Gasts',
            'Number of Adults'   => 'Anzahl Erwachsener',
            'Number of Children' => 'Anzahl Kinder',
            'Room Description'   => 'Zimmerbeschreibung',
            //'Estimated Total*:' => '',
            //'Room Rate' => '',
            'Cancel Information' => ['Informationen ändern und stornieren'],
            //			'Starpoints Used for Award' => '',
            'Number of Rooms'  => 'Anzahl der Zimmer',
            'ConfirmSubjectRe' => "#(wurde bestätigt)#",
            'CancelSubjectRe'  => "#(Stornierung)#",
        ],
        'fr' => [
            'Confirmation:'      => 'Confirmation',
            'Phone:'             => 'Téléphone',
            'Check In'           => 'Arrivée',
            'Check Out'          => 'Départ',
            'Guest Name'         => 'Nom du client',
            'Number of Adults'   => "Nombre de clients",
            'Number of Children' => "Nombre d'enfants",
            'Room Description'   => 'Description de la chambre',
            //'Estimated Total*:' => '',
            //'Room Rate' => '',
            'Cancel Information' => ['Règlement de garantie et conditions', 'Modification et annulation des informations'],
            //			'Starpoints Used for Award' => '',
            'Number of Rooms' => 'Nombre de chambres',
            //			'ConfirmSubjectRe' => "##",
            //			'CancelSubjectRe' => "##",
        ],
        'ja' => [
            'Confirmation:'      => '予約確認番号:',
            'Phone:'             => 'TEL:',
            'Check In'           => 'チェックイン',
            'Check Out'          => 'チェックアウト',
            'Guest Name'         => 'お客様のお名前',
            'Number of Adults'   => '人数（大人）',
            'Number of Children' => '人数(子ども)',
            'Room Description'   => '客室の詳細',
            'Estimated Total*:'  => '総額見積もり*:',
            'Room Rate'          => 'ご宿泊料金',
            'Cancel Information' => ['とキャンセルポリシー'],
            //			'Starpoints Used for Award' => '',
            //			'Number of Rooms' => '',
            //			'ConfirmSubjectRe' => "##",
            //			'CancelSubjectRe' => "##",
        ],
        'nl' => [
            'Confirmation:'      => 'Bevestiging',
            'Phone:'             => 'Telefoon:',
            'Check In'           => 'Inchecken',
            'Check Out'          => 'Uitchecken',
            'Guest Name'         => 'Naam van de gast',
            'Number of Adults'   => 'Aantal volwassenen',
            'Number of Children' => 'Aantal kinderen',
            'Room Description'   => 'Kamerbeschrijving',
            //			'Estimated Total*:' => '',
            //			'Room Rate' => '',
            'Cancel Information'        => ['annuleringsbeleid'],
            'Starpoints Used for Award' => 'Starpoints gebruikt voor award',
            'Number of Rooms'           => 'Aantal kamers',
            //			'ConfirmSubjectRe' => "##",
            //			'CancelSubjectRe' => "##",
        ],
        'es' => [
            'Confirmation:'      => 'Confirmación:',
            'Phone:'             => 'Teléfono:',
            'Check In'           => 'Llegada',
            'Check Out'          => 'Salida',
            'Guest Name'         => 'Nombre del Huésped',
            'Number of Adults'   => 'Número de Adultos',
            'Number of Children' => 'Número de Niños',
            'Room Description'   => 'Descripción de la Habitación',
            'Estimated Total*:'  => 'Total Estimativo*',
            'Room Rate'          => 'Tarifa de Habitación',
            'Cancel Information' => ['Políticas de garantía y cancelación'],
            //			'Starpoints Used for Award' => '',
            'Number of Rooms' => 'Número de Habitaciones',
            //			'ConfirmSubjectRe' => "##",
            //			'CancelSubjectRe' => "##",
        ],
        'it' => [
            'Confirmation:'      => 'Conferma:',
            'Phone:'             => 'Telefono:',
            'Check In'           => 'Check In',
            'Check Out'          => 'Check Out',
            'Guest Name'         => 'Nome del cliente',
            'Number of Adults'   => 'Numero di adulti',
            'Number of Children' => 'Numero di bambini',
            'Room Description'   => 'Descrizione della camera',
            // 'Estimated Total*:' => '',
            // 'Room Rate' => '',
            'Cancel Information' => ['Informazioni su modifiche e cancellazioni'],
            //			'Starpoints Used for Award' => '',
            'Number of Rooms' => 'Numero di camere',
            'SPG Number:'     => 'Numero SPG:',
            //			'ConfirmSubjectRe' => "##",
            //			'CancelSubjectRe' => "##",
        ],
        'pt' => [
            'Confirmation:'      => 'Confirmação:',
            'Phone:'             => 'Telefone:',
            'Check In'           => 'Check-in',
            'Check Out'          => 'Check-out',
            'Guest Name'         => 'Nome do Hóspede',
            'Number of Adults'   => 'Número de Adultos',
            'Number of Children' => 'Número de Crianças',
            'Room Description'   => 'Descrição do Quarto',
            'Estimated Total*:'  => 'Total Estimado',
            'Room Rate'          => 'Tarifa do Quarto',
            'Cancel Information' => ['Detalhes do cancelamento'],
            //			'Starpoints Used for Award' => '',
            'Number of Rooms' => 'Número de Quartos',
            'SPG Number:'     => 'Número SPG:',
            //			'ConfirmSubjectRe' => "##",
            //			'CancelSubjectRe' => "##",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], 'starwoodhotels') !== false && $this->arrikey($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'starwoodhotels') !== false && $this->arrikey($parser->getHTMLBody(), $this->body) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, ['starwoodhotels']) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $this->emailSubject = $parser->getSubject();

        if ($this->lang = $this->arrikey($parser->getHTMLBody(), $this->body)) {
            $its[] = $this->parseHotel();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TicketText2017' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return null;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * TODO: In php problems with "Type declarations", so i did so.
     * Are case sensitive. Example:
     * <pre>
     * var $reBody = ['en' => ['Reservation Modify'],];
     * var $reSubject = ['Reservation Modify']
     * </pre>.
     *
     * @param string $haystack
     *
     * @return int, string, false
     */
    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function parseHotel()
    {
        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $this->http->FindSingleNode("(//text()[{$this->xpathArray($this->t('Confirmation:'))}]/ancestor::tr[1])[1]", null, false, '/:\s*([\w-]+)/');

        $result += $this->parseHotelName();

        if (!$checkInDate = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check In'))}]/ancestor::td[1]/following-sibling::td[1])[1]")) {
            $checkInDate = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check In'))}]/following::text()[normalize-space(.)][1])[1]");
        }
        $result['CheckInDate'] = strtotime($this->normalizeDate($checkInDate));

        if (!$checkOutDate = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check Out'))}]/ancestor::td[1]/following-sibling::td[1])[1]")) {
            $checkOutDate = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check Out'))}]/following::text()[normalize-space(.)][1])[1]");
        }
        $result['CheckOutDate'] = strtotime($this->normalizeDate($checkOutDate));

        $result['GuestNames'] = array_unique($this->http->FindNodes("//text()[contains(., '{$this->t('Guest Name')}')]/following::text()[normalize-space(.)][1]"));
        $result['Guests'] = $this->http->FindSingleNode('(//text()[contains(., "' . $this->t('Number of Adults') . '")]/following::text()[normalize-space(.)][1])[1]', null, false, '/^\d+$/');
        $result['AccountNumbers'] = array_filter($this->http->FindNodes('//text()[normalize-space(.)="' . $this->t('SPG Number:') . '"]/following::text()[normalize-space(.)][1]'));
        $result['Kids'] = $this->http->FindSingleNode('(//text()[contains(., "' . $this->t('Number of Children') . '")]/following::text()[normalize-space(.)][1])[1]', null, false, '/^\d+$/');
        $result['Rooms'] = $this->http->FindSingleNode('(//text()[contains(., "' . $this->t('Number of Rooms') . '")]/following::text()[normalize-space(.)][1])[1]', null, false, '/^\d+$/');
        $result['RoomType'] = $this->http->FindSingleNode("(//text()[contains(., '{$this->t('Room Description')}')]/following-sibling::text())[1]");
        $RoomTypeDescription = implode(", ", $this->http->FindNodes("//text()[contains(., '{$this->t('Room Description')}')]/ancestor::table[1]//tr[contains(normalize-space(.),'•')]"));
        $pos = strpos($RoomTypeDescription, html_entity_decode("&#8226;"));

        if ($pos !== false) {
            $result['RoomTypeDescription'] = substr($RoomTypeDescription, $pos);
        }

        //		$result['Rate'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Room Rate')}')]/ancestor::td[1]/following-sibling::td[1]");
        $Rate = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Room Rate')}')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match("#^.*\d.*$#s", $Rate)) {
            $result['Rate'] = $Rate;
        } else {
            $result['Rate'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Room Rate')}')]/following::text()[normalize-space(.)][2]");
        }
        $total = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Estimated Total*:')}')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][last()]");

        if (!empty($total)) {
            $result['Total'] = (float) preg_replace('/[^\d.]+/', '', $total);
        }
        $total = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Estimated Total*:')}')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][last()-1]");

        if (!empty($total)) {
            $result['Currency'] = preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/', '/^£$/'], ['', 'EUR', 'USD', 'GBR'], $total);
        }
        $SpentAwards = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Starpoints Used for Award') . "')]/following::text()[normalize-space(.)][1]", null, true, "#(\d+)#");

        if (!empty($SpentAwards)) {
            $result['SpentAwards'] = $SpentAwards;
        }
        $result['CancellationPolicy'] = $this->http->FindSingleNode("(//text()[{$this->xpathArray($this->t('Cancel Information'))}]/ancestor::tr[1]/following-sibling::tr[1])[1]");

        $node = $this->http->XPath->query("//text()[{$this->xpathArray($this->t('Cancel Information'))}]/ancestor::tr[1]")->item(0);

        if (!$result['CancellationPolicy'] && preg_match("#" . $this->opt($this->t('Cancel Information')) . "\s+(.*?)(?:\n\n|$)#s", $this->nodeText($node), $m)) {
            $result['CancellationPolicy'] = trim($m[1]);
        }

        // Total
        $result['Total'] = $this->normalizePrice($this->http->FindSingleNode("//text()[contains(., '{$this->t('Estimated Total*:')}')]/following::div[1]/descendant::text()[normalize-space(.)][4]"));

        if ($result['Total'] == 0.0) {
            $result['Total'] = $this->normalizePrice($this->http->FindSingleNode("//text()[contains(., '{$this->t('Estimated Total*:')}')]/ancestor::tr[1]/td[last()]", null, true, "#([\d .,]+)#"));
        }

        // Currency
        $result['Currency'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Estimated Total*:')}')]/following::text()[normalize-space(.)][1]", null, true, "#([A-Z]{3})#");

        if (empty($result['Currency'])) {
            $result['Currency'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Estimated Total*:')}')]/following::text()[normalize-space(.)][2]", null, true, "#([A-Z]{3})#");
        }

        if (!empty($this->emailSubject) && strpos($this->t("ConfirmSubjectRe"), '#') !== false && preg_match($this->t("ConfirmSubjectRe"), $this->emailSubject)) {
            $result['Status'] = 'Confirmed';
        }

        if (!empty($this->emailSubject) && strpos($this->t("CancelSubjectRe"), '#') !== false && preg_match($this->t("CancelSubjectRe"), $this->emailSubject)) {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }

        if (!empty($this->http->FindSingleNode("(//text()[{$this->xpathArray($this->t('Confirmation:'))}]/ancestor::tr[1])[1]/following-sibling::tr[1]", null, false, '/^\s*[\w ]+:\s*([A-Z\d\-]{5,})\s*$/'))) {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }

        return $result;
    }

    private function nodeText($node)
    {
        if ($node !== null) {
            return strip_tags(str_replace("<br>", "\n", $node->ownerDocument->saveHTML($node)));
        }

        return null;
    }

    private function parseHotelName()
    {
        $result = [];
        $array = array_values(array_filter(array_map('trim', $this->http->FindNodes("//text()[contains(., '{$this->t('Phone:')}')]/ancestor::table[1]//text()"))));
        $pos = -1;

        foreach ($array as $key => $value) {
            if (stripos($value, $this->t('Phone:')) !== false) {
                $pos = $key;

                break;
            }
        }

        $slice = array_slice($array, 0, $pos);
        $result['HotelName'] = array_shift($slice);
        $result['Address'] = join(', ', $slice);

        $result += $this->matchSubpattern('/\w+\s*:(?<Phone>[+\d\s()-]+)(?:\w+\s*:(?<Fax>[+\d\s()-]+))?/', join("\n", array_slice($array, $pos)));

        return $result;
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     *
     * @return type
     */
    private function matchSubpattern($pattern, $text)
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function xpathArray($array, $str1 = 'normalize-space(.)', $method = 'contains', $operator = 'or')
    {
        $arr = [];

        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($array as $str2) {
            $arr[] = "{$method}({$str1}, '{$str2}')";
        }

        return join(" {$operator} ", $arr);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function normalizeDate($date)
    {
        //		$this->http->log($date);
        $in = [
            '#(\d{1,2})-(\w+)-(\d{4})\s*-\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?).*$#i',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = preg_replace($in, $out, $date);

        if (!empty(strtotime($str))) {
            return $str;
        }

        if (!empty(strtotime(preg_replace('#\s*[ap]m\s*$#i', '', $str)))) {
            return preg_replace('#\s*[ap]m\s*$#i', '', $str);
        }

        if (preg_match('#([[:alpha:]]+)#iu', $str, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str);
            }
        }

        return $str;
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
}
