<?php

namespace AwardWallet\Engine\flysmarter\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "flysmarter/it-785112666.eml, flysmarter/it-792091412.eml, support@flysmarter.";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Ordrenummer:'                => 'Ordrenummer:',
            'Buchungsnummer:'             => 'Buchungsnummer:',
            'Buchungsdatum:'              => 'Buchungsdatum:',
            'Reisender Vorname/Nachname:' => 'Reisender Vorname/Nachname:',
            'Abflug'                      => 'Abflug',
            'Ankunft'                     => 'Ankunft',
            'Flug'                        => 'Flug',
            'Davon Flugsteuern'           => 'Davon Flugsteuern',
            'Summe'                       => 'Summe',
            'Flugticket'                  => 'Flugticket',
            'Gesamtsumme inkl. Steuern:'  => 'Gesamtsumme inkl. Steuern:',
        ],
        'no' => [
            'Ordrenummer:'                => 'Ordrenummer:',
            'Buchungsnummer:'             => 'Bookingnummer:',
            'Buchungsdatum:'              => 'Bookingdato:',
            'Reisender Vorname/Nachname:' => 'Reisende Fornavn / Etternavn:',
            'Abflug'                      => 'Avr',
            'Ankunft'                     => 'Ank',
            'Flug'                        => 'Fly',
            'Davon Flugsteuern'           => 'Hvorav skat',
            'Summe'                       => 'Total',
            'Flugticket'                  => 'Flybillett',
            'Gesamtsumme inkl. Steuern:'  => 'Totalt inkl. skat:',
        ],
        'fi' => [
            'Ordrenummer:'                => 'Tilausnumero:',
            'Buchungsnummer:'             => 'Varausnumero:',
            'Buchungsdatum:'              => 'Varauspäivämäärä:',
            'Reisender Vorname/Nachname:' => 'Matkustajan(/-jien) etunimi/sukunimi:',
            'Abflug'                      => 'Lähtö',
            'Ankunft'                     => 'Saapuminen',
            'Flug'                        => 'Lento',
            'Davon Flugsteuern'           => 'Josta lentoveroa',
            'Summe'                       => 'Summa',
            'Flugticket'                  => 'Lennot',
            'Gesamtsumme inkl. Steuern:'  => 'Yhteensä veroineen:',
        ],
        'da' => [
            'Ordrenummer:'                => 'Ordrenummer:',
            'Buchungsnummer:'             => 'Bookingnummer:',
            'Buchungsdatum:'              => 'Reservationsdato:',
            'Reisender Vorname/Nachname:' => 'Navn/Efternavn:',
            'Abflug'                      => 'Afg',
            'Ankunft'                     => 'Ank',
            'Flug'                        => 'Fly',
            'Davon Flugsteuern'           => 'Skat',
            'Summe'                       => 'Total',
            'Flugticket'                  => 'Flybillet',
            'Gesamtsumme inkl. Steuern:'  => 'Total inkl. skat:',
        ],
    ];

    private $detectFrom = "provider.url";
    private $detectSubject = [
        // de
        'Buchungsbestätigung ',
        // no
        'Takk for din booking:',
        // fi
        'Tilausvahvistus Flysmarter',
        // da
        'Tak for din reservation af ordre',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flysmarter\.[a-z]{2,3}$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'FlySmarter') === false
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
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['flysmarter'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@flysmarter.', 'www.flysmarter.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Abflug']) && !empty($dict['Ankunft']) && !empty($dict['Flug'])
                && $this->http->XPath->query("//tr[*[1][{$this->eq($dict['Abflug'])}]][*[3][{$this->eq($dict['Ankunft'])}]][*[5][{$this->eq($dict['Flug'])}]]")->length > 0
            ) {
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

    public function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordrenummer:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Gesamtsumme inkl. Steuern:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $value = PriceHelper::parse($m['amount'], $currency);

            if (is_numeric($value)) {
                $value = (float) $value;
            } else {
                $value = null;
            }
            $email->price()
                ->total($value)
                ->currency($currency);

            $priceTableXpath = "//tr[*[3][{$this->eq($this->t('Davon Flugsteuern'))}]][*[4][{$this->eq($this->t('Summe'))}]]/"
                . "following-sibling::*";
            $taxes = $this->http->FindNodes($priceTableXpath . "[{$this->starts($this->t('Flugticket'))}]/*[3]", null, "/^\D*(\d[\d,. ]*?)\D*$/");
            $taxValue = 0.0;

            foreach ($taxes as $t) {
                $taxValue += (float) PriceHelper::parse($t, $currency);
            }

            if (!empty($taxValue)) {
                $email->price()
                    ->tax($taxValue);
            }
        }
        $discount = 0.0;
        $nodes = $this->http->XPath->query($priceTableXpath . "[not({$this->starts($this->t('Flugticket'))})]");

        foreach ($nodes as $pRoot) {
            $name = $this->http->FindSingleNode("*[1]", $pRoot);
            $value = $this->http->FindSingleNode("*[4]", $pRoot, true, "/^\D*?(\-?\d[\d,. ]*?)\D*$/");

            if (stripos($value, '-') === 0) {
                $discount = (float) PriceHelper::parse(trim($value, '-'), $currency);
            } else {
                $email->price()
                    ->fee($name, (float) PriceHelper::parse($value, $currency));
            }
        }

        if (!empty($discount)) {
            $email->price()
                ->discount($discount);
        }

        // Flights
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Buchungsnummer:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([\dA-Z]{5,})\s*$/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Buchungsdatum:'))}]/following::text()[normalize-space()][1]")))
            ->travellers(preg_replace('/\s*\/\s*/', ' ', preg_split('/\s*,\s*/',
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reisender Vorname/Nachname:'))}]/following::text()[normalize-space()][1]"))));

        // Segments
        $xpath = "//tr[*[1][{$this->eq($this->t('Abflug'))}]][*[3][{$this->eq($this->t('Ankunft'))}]][*[5][{$this->eq($this->t('Flug'))}]]/following-sibling::*[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("*[5]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,4})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("*[2]", $root, null, "/.+\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[2]", $root, null, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("*[1]", $root)))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("*[4]", $root, null, "/.+\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[4]", $root, null, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("*[3]", $root)))
            ;

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("*[6]", $root))
            ;
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Reisender Vorname/Nachname:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Reisender Vorname/Nachname:'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
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
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        // $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        $s = trim($s);

        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '€' => 'EUR',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
