<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-469286974.eml, vivaaerobus/it-474745507-es.eml, vivaaerobus/it-476933423.eml, vivaaerobus/it-643804712.eml";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Reservation Code' => 'Reservation Code',
            'Passengers'       => 'Passengers',
            // 'Travel itinerary' => '',
            // 'Seat:' => '',
            // 'Terminal' => '',
            // 'Purchase summary' => '',
            // 'Booking Total' => '',
            //  'Base fare' => '',
            //  'Discount' => '',
            // 'Flight' => '',
        ],
        'es' => [
            'Reservation Code' => 'Código de reservación',
            'Passengers'       => 'Pasajeros',
            'Travel itinerary' => 'Duración',
            'Seat:'            => 'Asiento:',
            // 'Terminal' => '',
            'Purchase summary' => 'Resumen de compra',
            'Booking Total'    => 'Total de la reserva',
            'Base fare'        => 'Tarifa base',
            'Discount'         => 'Descuento',
            //'(Infant)' => '',
            'Flight' => 'Vuelo',
        ],
    ];

    private $detectFrom = "reservations@vivaaerobus.com";
    private $detectSubject = [
        // en
        'Booking confirmation | Viva Aerobus',
        // es
        'Confirmación de reservación | Viva Aerobus',
    ];
    private $detectBody = [
        'en' => [
            'Your reservation is ready',
        ],
        'es' => [
            'Tu reservación está lista', 'Tu reservación a Guadalajara está pendiente',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]vivaaerobus\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'Viva Aerobus') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.vivaaerobus.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Sent by VivaAerobus', 'Enviado por VivaAerobus', 'Sent by Viva', 'Enviado por Viva', 'Viva No-Reply'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        $assignLanguages = array_keys(self::$dictionary);

        foreach ($assignLanguages as $i => $lang) {
            if (!is_string($lang) || empty(self::$dictionary[$lang]['Reservation Code'])
                || $this->http->XPath->query("//*[{$this->contains(self::$dictionary[$lang]['Reservation Code'])}]")->length === 0
            ) {
                unset($assignLanguages[$i]);
            }
        }

        if (count($assignLanguages) > 1) {
            foreach ($assignLanguages as $i => $lang) {
                if (!is_string($lang) || empty(self::$dictionary[$lang]['Passengers'])
                    || $this->http->XPath->query("//tr/*[{$this->eq(self::$dictionary[$lang]['Passengers'])}]")->length === 0
                ) {
                    unset($assignLanguages[$i]);
                }
            }
        }

        if (count($assignLanguages) === 1) {
            $this->lang = array_shift($assignLanguages);

            return true;
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold") or contains(translate(@style," ",""),"font-weight:700"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        // General

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Code'))}]/following::text()[normalize-space()][1]", null, true, '/^\s*([A-Z\d]{5,7})\s*$/'));

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::tr[*[1][translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆') = '∆' or translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆') = '∆∆'] and *[2][contains(., '/')]]/*[2]/descendant::text()[normalize-space()][1][not(contains(normalize-space(), 'TBAADT'))]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        foreach ($travellers as $traveller) {
            $account = $this->http->FindSingleNode("//text()[{$this->starts($traveller)}]/following::text()[normalize-space()][2]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Doters:'))}\s*[#]\s*(\d+)/");

            if (!empty($account)) {
                $f->addAccountNumber($account, false, $traveller);
            }
        }

        $infants = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[{$this->eq($this->t('(Infant)'))}]/following::text()[normalize-space()][1]");

        if (count($infants) > 0) {
            $f->general()
                ->infants($infants);
        }

        // Seats
        $seats = [];
        $seatsText = "\n\n" . implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Seat:'))}]/ancestor::*[not({$this->starts($this->t('Seat:'))})][1]//text()[normalize-space()]"));
        $seatsParts = $this->split("/\n\s*([A-Z]{3} ?- ?[A-Z]{3}\s*\n)/", $seatsText);

        foreach ($seatsParts as $sp) {
            if (preg_match("/^\s*([A-Z]{3}) ?- ?([A-Z]{3})\s*\n(?:[\s\S]+\n)?{$this->opt($this->t('Seat:'))}\s*(\d{1,5}[A-Z])\b/u", $sp, $m)) {
                $seats[$m[1] . $m[2]][] = $m[3];
            }
        }

        $xpathTime = 'contains(translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆"),"∆:∆∆")';

        $xpath = "//tr[ *[1]/descendant::text()[{$xpathTime}] and *[2]/descendant::img and *[3]/descendant::text()[{$xpathTime}] ]";
        $segments = $this->http->XPath->query($xpath);
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['number']);
            }

            // Departure, Arrival
            $dateDep = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Travel itinerary'))}][1]/preceding::text()[normalize-space()][1]", $root));

            /*
                Terminal A
                Tijuana
                San Diego vía CBX
            */
            $patternTN = "/^{$this->opt($this->t('Terminal'))}[-–\s]+(?<terminal>[A-Z\d].*)\n(?<name>[\s\S]+)$/";

            $departureText = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            /*
                TIJ
                Tijuana
                San Diego vía CBX
                8:00 PM
            */

            if (preg_match($pattern = "/^\s*(?<code>[A-Z]{3})\s+(?<name>.{2,}(?:\n.+){0,2})\n+(?<time>{$patterns['time']})/i", $departureText, $m)) {
                $s->departure()->code($m['code']);

                if (preg_match($patternTN, $m['name'], $m2)) {
                    $s->departure()->terminal($m2['terminal']);
                    $m['name'] = $m2['name'];
                }
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']));

                if (!empty($dateDep)) {
                    $s->departure()->date(strtotime($m['time'], $dateDep));
                }
            }

            $arrivalText = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrivalText, $m)) {
                $s->arrival()->code($m['code']);

                if (preg_match($patternTN, $m['name'], $m2)) {
                    $s->arrival()->terminal($m2['terminal']);
                    $m['name'] = $m2['name'];
                }
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']));

                if ($dateDep) {
                    $s->arrival()->date(strtotime($m['time'], $dateDep));
                }
            }

            if ($s->getDepCode() && $s->getArrCode() && array_key_exists($s->getDepCode() . $s->getArrCode(), $seats)) {
                foreach ($seats[$s->getDepCode() . $s->getArrCode()] as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat:'))}]/following::text()[normalize-space()][1][{$this->contains($seat)}]/ancestor::table[2]/descendant::text()[string-length()>3][1][not(contains(normalize-space(), 'TBAADT'))]");

                    if (!empty($pax)) {
                        $s->addSeat($seat, true, true, $pax);
                    } else {
                        $s->addSeat($seat);
                    }
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//*[{$this->eq($this->t('Booking Total'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match("/^(?<money>.*?\d.*?)\s*[+]+\s*(?<points>\d[,.\'\d ]*\s*d(?:oters)?)$/i", $totalPrice, $m)) {
            // $3,028.33 MXN + 5,065 d
            $totalPrice = $m['money'];
            $f->price()->spentAwards($m['points']);

        /*
            Puntos Doters (Doters Points) - affiliate program for traveling companies (Vivo, ETN Turistar, Costa Line and other)
            https://www.doters.com/aliados
        */
        } elseif (preg_match("/^\d[,.\'\d ]*\s*d(?:oters)?$/i", $totalPrice, $m)) {
            // 5,065 d
            $f->price()->spentAwards($totalPrice);

            return;
        }

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)\s*(?<currencyCode>[A-Z]{3})?$/', $totalPrice, $matches)) {
            // $2,156.28 MXN    |    $9,984

            if (empty($matches['currencyCode'])) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            } else {
                $currencyCode = $matches['currencyCode'];
            }

            $f->price()->currency($currencyCode ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $costAmounts = $discountAmounts = $fees = [];
            $priceRows = $this->http->XPath->query("//tr[count(*[normalize-space()])=2][ preceding::text()[{$this->eq($this->t('Purchase summary'))}] and following::text()[{$this->eq($this->t('Booking Total'))}] ][not(.//*[normalize-space() and {$xpathBold}])]");

            foreach ($priceRows as $pRow) {
                $pName = $this->http->FindSingleNode("*[normalize-space()][1]", $pRow);
                $pValue = $this->http->FindSingleNode("*[normalize-space()][2]", $pRow, true, '/^[-–\s]*(?:' . preg_quote($matches['currency'], '/') . ')?\s*(\d[,.\'\d ]*)$/');

                if (!$pValue) {
                    continue;
                }

                $pAmount = PriceHelper::parse($pValue, $currencyCode);

                if (preg_match("/^{$this->opt($this->t('Base fare'))}$/i", $pName)) {
                    $costAmounts[] = $pAmount;
                } elseif (preg_match("/^{$this->opt($this->t('Discount'))}$/i", $pName)
                    || preg_match('/^[-–].+$/', $pValue)
                ) {
                    $discountAmounts[] = $pAmount;
                } else {
                    if (array_key_exists($pName, $fees)) {
                        $fees[$pName] += $pAmount;
                    } else {
                        $fees[$pName] = $pAmount;
                    }
                }
            }

            if (count($costAmounts) > 0) {
                $f->price()->cost(array_sum($costAmounts));
            }

            if (count($discountAmounts) > 0) {
                $f->price()->discount(array_sum($discountAmounts));
            }

            foreach ($fees as $name => $charge) {
                $f->price()->fee($name, $charge);
            }
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->t('Booking Total'))}]")->length > 0) {
            $this->logger->debug('Total price not found!');
            $f->price()->total(null);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        if (stripos($date, $this->t('Flight')) !== false) {
            $date = preg_replace("/{$this->opt($this->t('Flight'))}\s*\d+\:/", "", $date);
        }
        //$this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // Jueves, 17 ago 2023
            '/^\s*[[:alpha:]\-]+[\s,]\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
