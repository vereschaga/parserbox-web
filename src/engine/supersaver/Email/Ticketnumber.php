<?php

namespace AwardWallet\Engine\supersaver\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ticketnumber extends \TAccountChecker
{
    public $mailFiles = "supersaver/it-3696580.eml, supersaver/it-3910482.eml, supersaver/it-3910530.eml, supersaver/it-61163710.eml, supersaver/it-6656544.eml, supersaver/it-8429123.eml, supersaver/it-8434204.eml, supersaver/it-715012017-gotogate.eml";

    public static $detectFrom = [
        'gotogate' => [
            'gotogate.',
        ],
        'flybillet' => [
            'flybillet.',
        ],
        'trip' => [
            '@mytrip.',
            '@Mytrip.',
            '@info.Mytrip.com',
            '@trip.',
            '@avion.',
            '@airtickets24.',
            'pamediakopes.gr',
        ],

        'fnt' => [
            'flightnetwork.',
        ],
        'supersaver' => [
            'supersaver.',
            'travelpartner.',
            'travelstart.',
            'seat24.',
            'etraveli.com',
            'flygresor.',
            'charter.',
            'flygvaruhuset.',
            'goleif.',
            'budjet.',
            'travelfinder.',
            'travelfinder.',
        ],
    ];

    public static $dict = [
        'nl' => [
            //			'Order number' => '',
            //			'Passagiers' => '',
            //			'e-Ticket' => '',
            //			'Van' => '',
            //			'Datum' => '',
        ],
        'fr' => [
            'Order number' => ['Order number', 'Order number:', 'Numéro de commande:', 'Vols de la commande'],
            'Passagiers'   => ['Voyageurs', 'Nom'],
            'e-Ticket'     => 'e-Ticket',
            'Van'          => 'De',
            'Datum'        => 'Date',
        ],
        'no' => [
            'Order number' => ['Order number', 'Ordrenummer', 'Ordrenummer:'],
            'Passagiers'   => 'Passasjerer',
            'e-Ticket'     => 'e-Ticket',
            'Van'          => 'Fra',
            'Datum'        => 'Dato',
        ],
        'en' => [
            'Order number' => ['Order number', 'Order number:', 'Flights for Order'],
            'Passagiers'   => ['Passengers', 'Name'],
            'e-Ticket'     => 'e-Ticket',
            'Van'          => 'From',
            'Datum'        => 'Date',
        ],
        'sv' => [
            'Order number' => ['Ordernummer:', 'Flyg för bokning', 'Order number', 'Order number:'],
            'Passagiers'   => ['Passagerare', 'Namn'],
            'e-Ticket'     => 'e-Ticket',
            'Van'          => 'Från',
            'Datum'        => 'Datum',
        ],
        'zh' => [
            'Order number' => '订单编号',
            'Passagiers'   => '出行人数',
            'e-Ticket'     => '电子机票',
            'Van'          => '出发城市',
            'Datum'        => '日期',
        ],
        'fi' => [
            'Order number' => 'Order number',
            'Passagiers'   => 'Matkustajat',
            'e-Ticket'     => 'e-Ticket',
            'Van'          => 'Lähtö',
            'Datum'        => 'Päivämäärä',
        ],
        'de' => [
            'Order number' => ['Order number:', 'Bestellnummer:', 'Flüge für Buchung'],
            'Passagiers'   => ['Reisender', 'Name'],
            'e-Ticket'     => 'e-Ticket',
            'Van'          => 'Ab',
            'Datum'        => ['Date', 'Datum'],
        ],
        'da' => [
            'Order number' => ['Order number:', 'Ordrenummer:', 'Flyrejser for ordre'],
            'Passagiers'   => ['Passagerer', 'navn'],
            'e-Ticket'     => ['e-Ticket', 'e-Ticket number(s)'],
            'Van'          => 'Fra',
            'Datum'        => 'Dato',
        ],
        'es' => [
            'Order number' => ['Order number:', 'Order number', 'Número de reserva:', 'Vuelos del pedido'],
            'Passagiers'   => ['Pasajeros', 'Nombre'],
            'e-Ticket'     => ['e-Ticket'],
            'Van'          => 'De',
            'Datum'        => 'Fecha',
        ],
        'ru' => [
            'Order number' => 'Номер заказа:',
            'Passagiers'   => 'Пассажир(ы)',
            'e-Ticket'     => 'e-Ticket number(s)',
            'Van'          => 'Откуда',
            'Datum'        => 'Дата',
        ],
        'pl' => [
            'Order number' => ['Numer zamówienia:', 'Order number'],
            'Passagiers'   => 'Pasażerowie',
            'e-Ticket'     => 'e-Ticket number(s)',
            'Van'          => 'Od',
            'Datum'        => 'Data',
        ],
        'it' => [
            'Order number' => ['Numero ordine:', 'Order number'],
            'Passagiers'   => 'Passeggero',
            'e-Ticket'     => 'e-Ticket number(s)',
            'Van'          => 'Da',
            'Datum'        => 'Data',
        ],
        'bg' => [
            //            'Order number' => '',
            'Passagiers' => 'Пътникци',
            //            'e-Ticket' => '',
            'Van'   => 'От',
            'Datum' => 'Datum',
        ],
        'vi' => [
            //            'Order number' => '',
            'Passagiers' => 'Hành khách',
            //            'e-Ticket number' => '',
            'Van'   => 'Từ',
            'Datum' => 'Ngày',
        ],
        'hu' => [
            'Order number' => 'Foglalási hivatkozás:',
            'Passagiers'   => 'UTASOK',
            //            'e-Ticket' => '',
            'Van'   => ['Indulási hely', 'Innen'],
            'Datum' => ['Indulás', 'Dátum'],
        ],
        'ja' => [
            'Order number' => '申し込み番号:',
            //            'Passagiers' => '',
            //            'e-Ticket' => '',
            'Van'   => ['出発地'],
            'Datum' => ['日付'],
        ],
    ];

    private $detectSubject = [
        "nl" => 'Hier is uw ticketnummer',
        "fr" => 'Voici votre numéro de billet',
        "no" => 'Her kommer billettnummeret ditt',
        'Din reise ',
        "en" => 'Here’s your ticket number',
        'Your flight ',
        'here’s all information regarding your trip',
        "sv" => 'Här kommer din bokningsinformation',
        "zh" => '这是您的机票编号',
        "fi" => 'Tässä on lipunnumerosi',
        "da" => 'Din rejse',
        "es" => 'Este es tu número de billete',
        'Tu vuelo ',
        "ru" => 'Ваш рейс ',
        "pl" => 'Informacja na temat Twojej rezerwacji',
        "it" => 'Il tuo volo ',
        'bg' => 'Вашият полет',
        'vi' => 'Chuyến bay của quý vị',
        'de' => 'Ihr Flug ',
        'hiermit erhalten Sie alle Informationen zu Ihrer Reise',
        'ja' => 'お客様のフライト',
    ];

    private $detectBody = [
        'nl' => [
            ['Het moment voor uw geweldige reis is bijna aangebroken', 'Van'],
            ['Hier zijn enkele handige tips voor uw reis', 'Van'],
        ],
        'fr' => [
            ['La date de votre magnifique voyage approche à grands pas', 'De'],
            ['Réservation de vol', 'De'],
            ['Vols de la commande', 'De'],
        ],
        'no' => [
            ['Så deilig at du skal ut og reise snart, har du alt du trenger før turen', 'Fra'],
            ['Flybokning', 'Ordrenummer'],
        ],
        'sv' => [
            ['Nu är det snart dags för din resa. I det här mejlet hittar', 'Från'],
            ['Supersavertravel är inte ansvarig för slutsålda flygbiljetter eller felaktiga priser', 'Från'],
            ['Flyg bokning', 'Från'],
            ['Flyg för bokning', 'Från'],
        ],
        'zh' => [
            ['恭喜您即将踏上一段精彩的旅程', '出发城市'],
        ],
        'fi' => [
            ['Varaamasi matka lähestyy', 'Lähtö'],
        ],
        'de' => [
            ['Meine Buchungen', 'Nach'],
            ['Flugbuchung', 'Bestellnummer'],
            ['Flugbuchung', 'Flüge für Buchung'],
        ],
        'da' => [
            ['Flybooking', 'Fra'],
            ['Flyrejser for ordre', 'Fra'],
        ],
        'en' => [
            ["It's great that you are going on an amazing trip", 'From'],
            ['Flight booking', 'From'],
            ['Flights for Order', 'From'],
        ],
        'es' => [
            ['Se acerca la fecha de tu fantástico viaje', 'De'],
            ['Reserva de vuelo', 'Order number'],
            ['Reserva de vuelo', 'Número de reserva:'],
            ['Vuelos del pedido', 'Datos de pasajeros del pedido'],
        ],
        'ru' => [
            ['В благодарность за ваше бронирование мы дарим вам отличные скидки на проживание в отелях.', 'Откуда'],
        ],
        'pl' => [
            ['Rezerwacja lotu', 'Numery referencyjne odprawy'],
            ['Rezerwacja lotu', 'Order number'],
            ['Rezerwacja lotu', 'Numer zamówienia'],
        ],
        'it' => [
            ['Prenotazione volo', 'Numero ordine:'],
            ['Prenotazione volo', 'Order number'],
        ],
        'bg' => [
            ['Резервация на полет', 'Order number'],
        ],
        'vi'  => [
            ['Đặt vé máy bay', 'Order number'],
        ],
        'hu'  => [
            ['Retúr', 'Foglalási hivatkozás'],
            ['Repülőjegy foglalása', 'Order number'],
        ],
        'ja'  => [
            ['フライト予約', '予約'],
        ],
    ];
    private $lang = '';

    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider();
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
            $email->ota()->code($this->providerCode);
        }

        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Order number')) . "]/following::text()[normalize-space(.)!=''][1]");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Order number')) . "]",
                null, true, "/{$this->opt($this->t('Order number'))}\s+([A-Z\d\-]{5,})[\s\W]*$/");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Hier zijn enkele handige tips voor uw reis')) . "]", null, false, '/\s+([A-Z\d\-]{5,})\b/');
        }

        $email->ota()
            ->confirmation($conf, 'Order number');

        $this->flight($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectFrom as $emailFroms) {
            $emailFromsName = array_map(function ($v) {return trim($v, '.@'); }, $emailFroms);

            if ($this->http->XPath->query("//img[" . $this->contains($emailFromsName, '@src') . "] | //a[" . $this->contains($emailFroms, '@href') . "]")->length > 0) {
                if ($this->assignLang()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach (self::$detectFrom as $code => $emailFroms) {
            foreach ($emailFroms as $emailFrom) {
                if (stripos($headers["from"], $emailFrom) !== false) {
                    $find = true;
                    $this->providerCode = $code;

                    break 2;
                }
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectFrom as $code => $emailFroms) {
            foreach ($emailFroms as $code => $emailFrom) {
                if (stripos($from, $emailFrom) !== false) {
                    return true;
                }
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectFrom);
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

    private function flight(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();
        // General
        $rl = $this->http->FindSingleNode("((//text()[" . $this->eq($this->t('Passagiers')) . "]/ancestor::table[" . $this->contains($this->t('e-Ticket')) . "][1]/following-sibling::table//table)[3]//text()[normalize-space(.)!=''])[1]", null, true, "#(?:[A-Z]{2}[\/])?([A-Z\d]{5,7}\b)#");

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Passagiers')) . "]/ancestor::tr[" . $this->contains($this->t('e-Ticket')) . "][1]/following-sibling::tr[count(./td) = 3 and normalize-space()]/td[3]//td[not(.//td)])[last()]", null, true, "#(?:[A-Z]{2}[\/])?([A-Z\d]{5,7}\b)#");
        }

        if (empty($rl) && empty($this->http->FindSingleNode("//*[" . $this->starts($this->t('Passagiers')) . " or " . $this->starts($this->t('e-Ticket')) . "]"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($rl);
        }

        $travellers = $tickets = [];

        // it-715012017-gotogate.eml
        $travellerRows = $this->http->XPath->query("//tr[ *[normalize-space()][2] and *[normalize-space()][1][{$this->eq($this->t('Passagiers'))}] ]/following-sibling::tr[normalize-space()]");

        if ($travellerRows->length === 0) {
            // it-3696580.eml
            $travellerRows = $this->http->XPath->query("//tr[ *[normalize-space()][2] and *[normalize-space()][1][{$this->eq($this->t('Passagiers'))}] ]/ancestor::table[ following-sibling::table[normalize-space()] ][1]/following-sibling::table[normalize-space()]//tr[*[3] and normalize-space()]");
        }

        foreach ($travellerRows as $tRow) {
            $passengerName = $this->http->FindSingleNode("*[1]", $tRow, true, "/^{$patterns['travellerName']}$/u");

            if ($passengerName && !in_array($passengerName, $travellers)) {
                $f->general()->traveller($passengerName, true);
                $travellers[] = $passengerName;
            }

            $ticket = $this->http->FindSingleNode("*[3]/descendant::*[normalize-space() and not(.//tr[normalize-space()]) and not(self::tr)][1]", $tRow, true, "/^{$patterns['eTicket']}$/u");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t('Van')) . "]/ancestor::table[" . $this->contains($this->t('Datum')) . "][1]/following-sibling::table[contains(translate(.,'0123456789','dddddddddd'),'dd:dd')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[" . $this->eq($this->t('Van')) . "]/ancestor::tr[" . $this->contains($this->t('Datum')) . "][1]/following-sibling::tr[contains(translate(.,'0123456789','dddddddddd'),'dd:dd')]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $node = $this->http->FindNodes(".//text()[normalize-space(.)!='']", $root);

            if (count($node) === 4 || count($node) === 5) {
                // Airline
                $s->airline()
                    ->noName()
                    ->noNumber();

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($node[0])
                ;
                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($node[1])
                ;

                $date = strtotime($this->normalizeDate($node[2]));

                if ($date && preg_match("#^\s*(\d+:\d+)\s*\-\s*(\d+:\d+)\s*$#", $node[3], $m)) {
                    $s->departure()
                        ->date(strtotime($m[1], $date));

                    $s->arrival()
                        ->date(strtotime($m[2], $date));

                    if (!empty($s->getArrDate()) && !empty($node[4]) && preg_match('/^[\+\-]\s*\d{1,2}$/', trim($node[4]))) {
                        $s->arrival()
                            ->date(strtotime($node[4] . ' day', $s->getArrDate()));
                    }
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $dBodies) {
            foreach ($dBodies as $dBody) {
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $dBody[0] . '")]')->length > 0
                    && $this->http->XPath->query('//*[contains(normalize-space(.),"' . $dBody[1] . '")]')->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(): ?string
    {
        foreach (self::$detectFrom as $code => $emailFroms) {
            $emailFromsName = array_map(function ($v) {return trim($v, '.@'); }, $emailFroms);

            if ($this->http->XPath->query("(//text()[" . $this->eq($this->t('Order number')) . "])[1]/preceding::img[" . $this->contains($emailFromsName, '@src') . "] | //a[" . $this->contains(str_replace('@', '.', $emailFroms), '@href') . "]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($emailFromsName)}]")->length > 0) {
                return $code;
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sun 28 May 2017
            '#.+?(\d+)\s+(\w+)[.,]?\s+(\d{4})\s*$#',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
