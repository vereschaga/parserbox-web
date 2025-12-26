<?php

namespace AwardWallet\Engine\nhhotels\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "nhhotels/it-130462760.eml, nhhotels/it-153535490.eml, nhhotels/it-2413126.eml, nhhotels/it-2428854.eml, nhhotels/it-2734092.eml, nhhotels/it-2852926.eml, nhhotels/it-48521905.eml, nhhotels/it-56611499.eml, nhhotels/it-62758633.eml, nhhotels/it-65035339.eml";

    public $reFrom = ["nh-hoteles.", "nh-hotels."];
    public $reBody = [
        'de' => [
            'Falls Sie Änderungen an Ihrer Reservierung vornehmen möchten, so können Sie dies im',
            'Ihre Reservierung bei der NH Hotel Group wurde storniert.',
            'Wir freuen uns, Ihre Buchung bestätigen zu können:',
        ],
        'nl' => [
            'We bevestigen uw boeking met veel genoegen:',
            'Met veel genoegen bevestigen wij uw boeking:',
        ],
        'es' => [
            'Le confirmamos la cancelación de su reserva.',
            'Tu reserva con NH Hotel Group ha sido cancelada',
            'Tu reserva con NH Hotel Group ha sidocancelada',
            'Condiciones y descripción de tarifa',
        ],
        'en' => [
            're pleased to confirm your booking:',
            'Your reservation with NH Hotel Group has been cancelled',
            'Your reservation with NH Hotel Group has beencancelled',
        ],
        'pt' => [
            'Temos o prazer de confirmar a sua reserva:',
        ],
        'it' => [
            'Siamo lieti di confermare la prenotazione:',
            'Le confermiamo la cancellazione della Sua prenotazione',
        ],
    ];
    public $reSubject = [
        'de' => 'Reservierungsbestätigung',
        'Stornierungsbestätigung',
        'es' => 'CANCELACIÓN ',
        'Reserva cancelada',
        'en' => 'Your booking NH Hotels',
        'Reservation cancelled',
        'Collection Royal Smartsuites',
        'pt' => 'CONFIRMAÇÃO Avani Avenida Liberdade',
        // it
        'CONFERMA NH',
        ' CANCELLAZIONE NH',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'pt' => [
            'Booking Number'       => 'Número de reserva',
            'Hotel Info'           => 'Informações sobre o hotel',
            'Telephone.'           => 'Telefone.',
            'Arrival date:'        => 'Data da chegada:',
            'Check out date:'      => ['Data do check-out:', 'Data da partida:'],
            'Services included:'   => 'Serviços incluídos:',
            'adults'               => 'adultos',
            'kids'                 => 'criança',
            'Name:'                => 'Nome:',
            'dear'                 => 'Prezado sr./sra.',
            'Total rate:'          => 'Tarifa total',
            'City tax:'            => 'IVA total',
            'Total accommodation:' => 'Resumo de valores',
            // Cancelled
            //'status'     => '',
            //'cancelled'  => '',
            //'cancelled2' => '',
            'cancellation' => 'Garantia:',
        ],
        'de' => [
            'Booking Number'     => 'Reservierungsnummer',
            'Hotel Info'         => 'Hotelinformationen',
            'Telephone.'         => 'Telefon.',
            'Arrival date:'      => 'Anreisedatum:',
            'Check out date:'    => 'Abreisedatum:',
            'Services included:' => 'Inklusive folgender Serviceleistungen:',
            'adults'             => 'Erwachsene',
            'Name:'              => 'Name:',
            'dear'               => ['Sehr geehrte Frau, sehr geehrter Herr', 'Sehr geehrte/r Frau/Herr', 'Sehr geehrte/r'],
            'Total rate:'        => 'Rate gesamt:',
            'City tax:'          => 'MwSt. insgesamt:',
            // Cancelled
            'status'     => 'storniert',
            'cancelled'  => 'Buchungsstornierung',
            'cancelled2' => 'Ihre Reservierung bei der NH Hotel Group wurde storniert.',
        ],
        'nl' => [
            'Booking Number'     => ['Boekingsnummer', 'Reserveringsnummer'],
            'Hotel Info'         => 'Hotelinformatie',
            'Telephone.'         => 'Telefoon.',
            'Arrival date:'      => 'Aankomstdatum:',
            'Check out date:'    => 'Uitcheckdatum:',
            'Services included:' => 'Services inbegrepen:',
            'adults'             => 'volwassenen',
            'kids'               => 'Junior',
            'Name:'              => 'Naam:',
            'dear'               => 'Geachte heer, mevrouw',
            'Total rate:'        => 'Totaalbedrag:',
            'City tax:'          => 'Toeristenbelasting:',
            // Cancelled
            //            'status' => '',
            //            'cancelled' => '',
            //            'cancelled2' => '',
        ],
        'es' => [
            'Booking Number'     => ['Número de Reserva', 'Nº de reserva'],
            'Hotel Info'         => 'Info Hotel',
            'Telephone.'         => 'Teléfono.',
            'Arrival date:'      => 'Fecha de llegada',
            'Check out date:'    => 'Fecha de Salida',
            'Services included:' => 'Servicios incluidos:',
            'adults'             => ['adultos', 'adulto'],
            'Name:'              => 'Nombre',
            'dear'               => 'Estimado/a',
            'Total rate:'        => ['Total rate:', 'Tarifa total'],
            // Cancelled
            'Total accommodation:' => ['Total accommodation:', 'Resumen del precio'],
            'City tax:'            => ['City tax:', 'IVA total'],
            'status'               => 'Cancelación',
            'cancelled'            => ['Comprobante de cancelación', 'CANCELACIÓN DE RESERVA'],
            'cancellation'         => 'Garantía:',
            'cancelled2'           => [
                'Le confirmamos la cancelación de su reserva.',
                'Tu reserva con NH Hotel Group ha sido cancelada',
                'Tu reserva con NH Hotel Group ha sidocancelada',
            ],
        ],
        'it' => [
            'Booking Number'     => ['Numero di prenotazione'],
            'Hotel Info'         => 'Info hotel',
            'Telephone.'         => 'Telefono.',
            'Arrival date:'      => 'Data di arrivo:',
            'Check out date:'    => 'Data di check-out:',
            'Services included:' => 'Servizi compresi:',
            'adults'             => ['adulto'],
            'Name:'              => 'Nome:',
            'dear'               => 'Gent. sig./sig.ra',
            'Total rate:'        => ['Totale tariffa'],
            // Cancelled
            'Total accommodation:' => ['Riepilogo prezzi'],
            'City tax:'            => ['IVA totale1'],
            'status'               => 'cancellazione',
            'cancelled'            => ['Conferma di cancellazione'],
            'cancellation'         => 'Garanzia:',
            'cancelled2'           => [
                'Le confermiamo la cancellazione della Sua prenotazione',
            ],
        ],
        'en' => [
            'Booking Number' => ['Booking Number', 'Reservation Number'],
            'dear'           => ['Dear Sir/Madam', 'Dear Mr/Mrs'],
            'Total rate:'    => ['Rate type', 'Total rate:'],
            'City tax:'      => ['City tax:', 'Taxes'],
            'kids'           => ['kids', 'child'],
            // Cancelled
            'status'     => 'cancelled',
            'cancelled'  => 'Booking Cancellation',
            'cancelled2' => [
                'Your reservation with NH Hotel Group has been cancelled',
                'Your reservation with NH Hotel Group has beencancelled',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".nh-hotels.com/") or contains(@href,"www.nh-hotels.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@nh-hotels.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'NH Hotel Group') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseHotel(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold") or contains(translate(@style," ",""),"font-weight:strong"))';

        $h = $email->add()->hotel();

        $firstParagraph = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Check out date:'))}]/preceding::text()[{$this->eq($this->t('Booking Number'))}]/ancestor::*[ ../self::tr and descendant::text()[normalize-space()][2] ][1]"));

        // $this->logger->debug($firstParagraph);

        if (preg_match("/^[ ]*({$this->opt($this->t('Booking Number'))})[: ]+([-A-z\d]{5,})[ ]*$/m", $firstParagraph, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $hotelName = $this->http->FindSingleNode("//img[{$this->contains(['/star.png', 'nh-hotels.com/multimedia/images/nh', 'img.nh-hotels.net/nh'], '@src')}]/preceding::text()[normalize-space(.)][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel Info'))}]/ancestor::table[1]/preceding::text()[string-length()>5][1]");
        }

        $hotel = $this->http->FindSingleNode("//a[{$this->contains($this->t('Hotel Info'))}]/ancestor::td[1]");
        $address = preg_replace('/\s{2,}/', ', ', trim(str_replace('·', '  ',
            $this->http->FindPreg("/^(.+?){$this->opt($this->t('Telephone.'))}/", false, $hotel)))
        );
        $phone = $this->http->FindPreg("/{$this->opt($this->t('Telephone.'))}\s+([+\d\s\(\)\-]+)/", false, $hotel);

        if ($hotelName && $address && $phone) {
            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone);
        }

        $dateCheckIn = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Arrival date:'))}]/following::*[normalize-space()][1]"));

        if (count($dateCheckIn) == 1) {
            $dateCheckIn = $dateCheckIn[0];
        }

        if ($dateCheckIn) {
            $h->booked()->checkIn($this->normalizeDate($dateCheckIn));
        }
        $dateCheckOut = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Check out date:'))}]/following::*[normalize-space()][1]"));

        if (count($dateCheckOut) == 1) {
            $dateCheckOut = $dateCheckOut[0];
        }

        if ($dateCheckOut) {
            $h->booked()->checkOut($this->normalizeDate($dateCheckOut));
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('cancellation'))}]/following::text()[normalize-space()][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);

            $this->detectDeadLine($h, $cancellation);
        }

        $rooms = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Services included:'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][position()<3][count(descendant::*[{$xpathBold}])=1]/descendant::*[{$xpathBold}]", null, "/^\D+$/"));

        if (count($rooms) > 0) {
            foreach ($rooms as $room) {
                $r = $h->addRoom();
                $r->setType($room);
            }
        }

        // · 1 Queen bed · 1 adults · Room only · Non smoker · FLEXIBLE RATE · Services included:
        $booked = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Services included:'))}]/ancestor::td[1]"));

        if (preg_match_all("/(?:^|\s)(\d{1,3}) {$this->opt($this->t('adults'))}/u", $booked, $adultMatches)) {
            $h->booked()->guests(array_sum($adultMatches[1]));
        }

        if (preg_match_all("/(?:^|\s)(\d{1,3}) {$this->opt($this->t('kids'))}/u", $booked, $kidMatches)) {
            $h->booked()->kids(array_sum($kidMatches[1]));
        }

        $patterns['name'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('dear'))}]", null, "/^{$this->opt($this->t('dear'))}[,\s]+({$patterns['name']})(?:\s*[,;:!?]|$)/u"), function ($item) {
            return !empty($item) && !preg_match("/^(?:CUSTOMADE TRAVEL)$/i", $item);
        });

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $h->general()->traveller($traveller);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total rate:'))}]", null, true, "/{$this->opt($this->t('Total rate:'))}[:\s]*(.*?\d.*?)(?:[ ]*\(|$)/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total rate:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.*?\d.*?)(?:[ ]*\(|$)/") // it-2852926.eml
        ;

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 566,98USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('City tax:'))}]/following::*[normalize-space()][1]");

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $price, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total accommodation:'))}]/following::*[normalize-space()][1]");

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $price, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelled'))}]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('cancelled2'))}]")->length > 0
        ) {
            $h->general()->status($this->t('status'));
            $h->general()->cancelled();
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
        foreach ($this->reBody as $lang => $items) {
            foreach ($items as $value) {
                if ($this->http->XPath->query("//*[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 26/05/2015
            '#^(\d+)/(\d+)/(\d{4})$#u',
        ];
        $out = [
            '$2/$1/$3',
        ];
        $outWeek = [
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weekNum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weekNum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Esta reserva se puede modificar o cancelar sin gastos hasta las (\d+)h del día de llegada#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1] . ':00');
        }

        if (preg_match("#Esta reserva no admite modificaciones ni cancelación sin gastos#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("#A reserva pode ser cancelada ou alterada sem gastos até (\d+) horas antes da entrada no hotel#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        }
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
