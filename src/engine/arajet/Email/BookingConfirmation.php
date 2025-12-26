<?php

namespace AwardWallet\Engine\arajet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "arajet/it-732690565.eml, arajet/it-732924850.eml, arajet/it-736612009.eml, arajet/it-739175304.eml, arajet/it-748443774.eml";
    public $subjects = [
        'Arajet OTA Booking Confirmation',
        'Confirmación de tu reserva en Arajet',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Thank you for choosing Arajet'],
        'es' => ['¡Gracias por elegir Arajet'],
        'fr' => ['Merci d\'avoir choisi Arajet!'],
    ];

    public static $dictionary = [
        "en" => [
            'Reservation modified:' => ['Reservation modified:', 'Reservation Modified on:'],
        ],
        "es" => [
            'Thank you for choosing Arajet' => '¡Gracias por elegir Arajet!',
            'Booking created:'              => 'Reserva creada:',
            'Reservation modified:'         => 'Reserva modificada:',
            'Journey '                      => ['Journey ', 'Trayecto '],
            'Passengers'                    => 'Pasajeros',
            'Booking Reference:'            => 'Confirmación de Reserva:',
            //'Layover:' => ['', ''],
            'Duration:'           => ['Duration:', 'Duración:'],
            'Flight #:'           => ['Flight #:', 'Vuelo #:'],
            'Connection'          => 'Escala',
            'Payment information' => 'Información de pago',
            'Total Paid'          => 'Total Pagado',
            'Tickets'             => 'Tarifa',
        ],
        "fr" => [
            'Thank you for choosing Arajet' => 'Merci d\'avoir choisi Arajet!',
            'Booking created:'              => 'Réservation créée:',
            //'Reservation modified:'         => 'Reserva modificada:',
            'Journey '                      => ['Trajet '],
            'Passengers'                    => 'Passagers',
            'Booking Reference:'            => 'Code de référence:',
            //'Layover:' => ['', ''],
            'Duration:'           => ['Durée:'],
            'Flight #:'           => ['Vol #:'],
            'Connection'          => 'Connexion',
            'Payment information' => 'Informations de paiement',
            'Total Paid'          => 'Total',
            'Tickets'             => 'Ticket',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@travel.arajet.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing Arajet'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking created:'))} or {$this->contains($this->t('Reservation modified:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Journey '))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]travel\.arajet\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following::table[1]/descendant::tr[normalize-space()]/descendant::text()[normalize-space()]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}]", null, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]{6})$/"));

        $dateReservation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking created:'))}]", null, true, "/{$this->opt($this->t('Booking created:'))}\s*(.+)\s+UTC/");

        if (!empty($dateReservation)) {
            $f->general()
                ->date($this->normalizeDate($dateReservation));
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Booking created:'))} or {$this->starts($this->t('Reservation modified:'))}]/ancestor::table[1]/following::table[1]/following-sibling::table[{$this->contains($this->t('Layover:'))} or {$this->contains($this->t('Duration:'))}]/descendant::text()[{$this->starts($this->t('Layover:'))} or {$this->starts($this->t('Duration:'))}]/ancestor::tr[1][not({$this->contains($this->t('Connection'))})]");

        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./descendant::img[contains(@src, 'Avion')]", $root)->length > 0) {
                $s = $f->addSegment();

                $fNumber = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('Flight #:'))}\s*(\d{1,4})$/");

                if (!empty($fNumber)) {
                    $s->airline()
                        ->name('DM')
                        ->number($fNumber);
                }

                $depText = implode("\n", $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<depCode>[A-Z]{3})\n(?<depName>(?:.+\n){1,2})(?<depDate>\w+\s*\d+\,\s*\d{4})\,\n(?<depTime>\d+\:\d+\s*A?\.?P?\.?\s*M\.?)\n/iu", $depText, $m)) {
                    $s->departure()
                        ->code($m['depCode'])
                        ->name(str_replace("\n", " ", $m['depName']))
                        ->date($this->normalizeDate($m['depDate'] . ' ' . $m['depTime']));
                }

                $arrText = implode("\n", $this->http->FindNodes("./descendant::td[5]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<arrCode>[A-Z]{3})\n(?<arrName>(?:.+\n){1,2})(?<arrDate>\w+\s*\d+\,\s*\d{4})\,\n(?<arrTime>\d+\:\d+\s*A?\.?P?\.?\s*M\.?)\n/iu", $arrText, $m)) {
                    $s->arrival()
                        ->code($m['arrCode'])
                        ->name(str_replace("\n", " ", $m['arrName']))
                        ->date($this->normalizeDate($m['arrDate'] . ' ' . $m['arrTime']));
                }

                $duration = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()]", $root, true, "/{$this->opt($this->t('Duration:'))}\s*(\d+.+)/");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }
            }
        }
    }

    public function ParseBus(Email $email)
    {
        $b = $email->add()->bus();

        $b->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following::table[1]/descendant::tr[normalize-space()]/descendant::text()[normalize-space()]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}]", null, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]{6})$/"));

        $dateReservation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking created:'))}]", null, true, "/{$this->opt($this->t('Booking created:'))}\s*(.+)\s+UTC/");

        if (!empty($dateReservation)) {
            $b->general()
                ->date($this->normalizeDate($dateReservation));
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Booking created:'))} or {$this->starts($this->t('Reservation modified:'))}]/ancestor::table[1]/following::table[1]/following-sibling::table[{$this->contains($this->t('Layover:'))} or {$this->contains($this->t('Duration:'))}]/descendant::text()[{$this->starts($this->t('Layover:'))} or {$this->starts($this->t('Duration:'))}]/ancestor::tr[1][not({$this->contains($this->t('Connection'))})]");

        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./descendant::img[contains(@src, 'Bus')]", $root)->length > 0) {
                $s = $b->addSegment();

                $bNumber = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('Flight #:'))}\s*(\d{1,4})$/");

                if (!empty($fNumber)) {
                    $s->setNumber($bNumber);
                }

                $depText = implode("\n", $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<depCode>[A-Z]{3})\n(?<depName>(?:.+\n){1,2})(?<depDate>\w+\s*\d+\,\s*\d{4})\,\n(?<depTime>\d+\:\d+\s*A?\.?P?\.?\s*M\.?)\n/iu", $depText, $m)) {
                    $s->departure()
                        ->code($m['depCode'])
                        ->name(str_replace("\n", " ", $m['depName']))
                        ->date($this->normalizeDate($m['depDate'] . ' ' . $m['depTime']));
                }

                $arrText = implode("\n", $this->http->FindNodes("./descendant::td[5]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<arrCode>[A-Z]{3})\n(?<arrName>(?:.+\n){1,2})(?<arrDate>\w+\s*\d+\,\s*\d{4})\,\n(?<arrTime>\d+\:\d+\s*A?\.?P?\.?\s*M\.?)\n/iu", $arrText, $m)) {
                    $s->arrival()
                        ->code($m['arrCode'])
                        ->name(str_replace("\n", " ", $m['arrName']))
                        ->date($this->normalizeDate($m['arrDate'] . ' ' . $m['arrTime']));
                }

                $duration = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()]", $root, true, "/{$this->opt($this->t('Duration:'))}\s*(\d+.+)/");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment information'))}]/ancestor::tr[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Total Paid'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Paid'))}\s+(.+)/");

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,\']+)$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Payment information'))}]/ancestor::tr[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Total Paid'))}]/ancestor::tr[1]/preceding-sibling::tr/descendant::td[1]/descendant::p[string-length()>2]");

            foreach ($feeNodes as $key => $feeRoot) {
                $key++;
                $feeName = $this->http->FindSingleNode(".", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][$key]", $feeRoot, true, "/^\D+([\d\.\,\']+)$/");

                if (stripos($feeName, $this->t('Tickets')) !== false) {
                    $email->price()
                        ->cost(PriceHelper::parse($feeSumm, $currency));

                    continue;
                }

                if (!empty($feeName) && $feeSumm !== null) {
                    $email->price()
                        ->fee($feeName, PriceHelper::parse($feeSumm, $currency));
                }
            }
        }

        if ($this->http->XPath->query("//img[contains(@src, 'Avion')]")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//img[contains(@src, 'Bus')]")->length > 0) {
            $this->ParseBus($email);
        }

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})\s*\-?\s*(\d+\:\d+)\s*(A?\.?P?\.?)\s*(M\.?)$#ui", //August 2, 2024 - 02:59 AM
        ];
        $out = [
            "$2 $1 $3, $4 $5$6",
        ];
        $str = str_replace('.', '', preg_replace($in, $out, $str));
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar', 'US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
