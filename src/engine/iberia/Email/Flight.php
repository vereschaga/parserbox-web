<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "iberia/it-133860858-fr.eml, iberia/it-67874552.eml, iberia/it-68072433.eml, iberia/it-86179284.eml"; // +1 bcdtravel(html)[it]
    public $subjects = [
        '/^Flight change confirmation$/',
        '/^Confirmación de reserva$/',
        '/^Confirmação de reserva$/',
        '/^Buchungsbestätigung$/', // de
        '/^Confirmation de réservation$/', // fr
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Passenger information'],
        'es' => ['Información de pasajeros'],
        'pt' => ['Dados dos passageiros'],
        'de' => ['Passagierdaten'],
        'fr' => ['Informations sur les passagers'],
    ];

    public static $dictionary = [
        "en" => [// it-68072433.eml
            "statusVariants" => "confirmed",
            "Seats"          => ["Seats", "Seat"],
            "Price Total"    => ["Price Total", "Total Price"],
        ],
        "es" => [ // it-67874552.eml
            'Confirmation code:' => 'Código de confirmación:',
            'Passenger'          => 'Pasajero',
            'statusVariants'     => 'confirmada',
            'Departure'          => 'Salida',
            'Arrival'            => 'Llegada',
            'Cabin'              => 'Cabina',
            'Seats'              => 'Asiento',
            'Price'              => 'Precio',
            'Price Total'        => 'Precio Total',

            'Loyalty'               => 'Tarjeta',
            'Passenger information' => 'Información de pasajeros',
            'Your outbound trip'    => 'Su viaje de ida',
        ],
        "pt" => [ // it-86179284.eml
            'Confirmation code:' => 'Código de confirmação:',
            'Passenger'          => 'Passageiro',
            'statusVariants'     => 'confirmada',
            'Departure'          => 'Partida',
            'Arrival'            => 'Chegada',
            'Cabin'              => 'Cabine',
            'Seats'              => 'Assento',
            'Price'              => 'Preço',
            'Price Total'        => 'Preço total',

            'Loyalty'               => 'Cartão',
            'Passenger information' => 'Dados dos passageiros',
            'Your outbound trip'    => 'Sua viagem de ida',
        ],
        "de" => [
            'Confirmation code:' => 'Bestätigungscode:',
            'Passenger'          => 'Passagier',
            'statusVariants'     => 'bestätigt',
            'Departure'          => 'Abflug',
            'Arrival'            => 'Ankunft',
            'Cabin'              => 'Kabine',
            'Seats'              => 'Sitzplatz',
            'Price'              => 'Preis',
            'Price Total'        => 'Gesamtpreis',

            'Loyalty'               => 'Karte',
            'Passenger information' => 'Passagierdaten',
            'Your outbound trip'    => 'Ihr Hinflug',
        ],
        "fr" => [ // it-133860858-fr.eml
            'Confirmation code:' => 'Code de confirmation:',
            'Passenger'          => 'Passager',
            'statusVariants'     => 'confirmé',
            'Departure'          => 'Sortie',
            'Arrival'            => 'Arrivée',
            'Cabin'              => 'Cabine',
            'Seats'              => 'Siège',
            'Price'              => 'Prix',
            'Price Total'        => 'Prix total',

            'Loyalty'               => 'Carte',
            'Passenger information' => 'Informations sur les passagers',
            'Your outbound trip'    => 'Votre voyage aller',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'iberia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectLang() === true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(),'Iberia Líneas Aéreas') or contains(normalize-space(),'Iberia Lineas Aereas')] | //a[contains(@href,'.iberia.com/') or contains(@href,'comunicaciones.iberia.com')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Loyalty'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your outbound trip'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/iberia\.com$/', $from) > 0;
    }

    public function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation code:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation code:'))}\s*([A-Z\d]{5,})/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::table[2]/following-sibling::table/descendant::text()[contains(normalize-space(), '(')]", null, "/^(\D+)\s*\(/"), true);

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Confirmation code:'))}]/preceding::tr[not(.//tr) and normalize-space()][1]", null, true, "/^.*?\s*\b({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/");

        if ($status) {
            $f->general()->status($status);
        }

        $accounts = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::table[2]/following-sibling::table/descendant::td[normalize-space()][not(contains(normalize-space(), '(')) and not(contains(normalize-space(), '|'))]", null, "/^(\d+)$/"));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        $totalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code:'))}]/following::text()[{$this->eq($this->t('Price'))}][1]/ancestor::table[2]/following::text()[{$this->eq($this->t('Price Total'))}][1]/ancestor::tr[2]/descendant::tr[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalText, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+?)$/', $totalText, $matches)
        ) {
            // 1.684,45 €    |    3'430.00 CHF
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $xpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[2]";
        $node = $this->http->XPath->query($xpath);

        foreach ($node as $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^([A-Z\d]{2})(\d{2,4})$/", $airlineText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            /*
                16:25
                Terça-feira 31 Agosto 2021
                Madrid Adolfo Suarez-Barajas (MAD)
                Terminal 4S
            */
            $pattern = "/^\s*"
                . "(?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[ ]*\n+"
                . "[ ]*(?<date>.{6,}?)[ ]*\n+"
                . "[ ]*(?<name>.{3,}?)[ ]+\([ ]*(?<code>[A-Z]{3})[ ]*\)"
                . "(?:[ ]*\n+[ ]*(?i)Terminal(?:[ ]+(?<terminal>\w.*?))?)?"
                . "\s*$/";

            $departureText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Departure'))}][1]/ancestor::td[1]/following::td[1]", null, $root));

            if (preg_match($pattern, $departureText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date2($this->normalizeDate($m['date']) . ' ' . $m['time']);

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            $arrivalText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Arrival'))}][1]/ancestor::td[1]/following::td[1]", null, $root));

            if (preg_match($pattern, $arrivalText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date2($this->normalizeDate($m['date']) . ' ' . $m['time']);

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }

            $s->extra()->cabin($this->http->FindSingleNode("descendant::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Cabin'))}] ]/tr[normalize-space()][2]", $root), false, true);

            $seatText = $this->http->FindSingleNode("descendant::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Seats'))}] ]/tr[normalize-space()][2]", $root, true, "/^\d+[A-z][,\sA-z\d]*$/");

            if ($seatText) {
                $s->extra()->seats(preg_split('/\s*[,]+\s*/', $seatText));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectLang() == false) {
            $this->logger->debug('Can\'t determine a language!');

            return $email;
        }

        $this->parseFlight($email);

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

    private function detectLang(): bool
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str): string
    {
        $in = [
            // Wednesday 30 June 2021
            "/^.*\b(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})$/u",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeCurrency($string): ?string
    {
        $string = trim($string);

        if (preg_match("#^\s*([A-Z]{3})\s*$#", $string)) {
            return $string;
        }

        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.', 'Rs'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
