<?php

namespace AwardWallet\Engine\reurope\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YouOrder extends \TAccountChecker
{
    public $mailFiles = "reurope/it-108255176.eml, reurope/it-131137581.eml, reurope/it-182938555.eml, reurope/it-64779418.eml, reurope/it-65205841.eml, reurope/it-94246644.eml";
    private $lang = 'en';
    private $date;
    private $reFrom = ['@raileurope.co.uk'];
    private $reProvider = ['Rail Europe'];
    private $reSubject = [
        // en
        'Your Rail Europe order:',
        'Your Rail Europe cancellation:',
        // pt
        'Seu pedido da Rail Europe:',
        'Seu cancelamento da Rail Europe:',
        // es
        'Tu pedido en Rail Europe:',
    ];
    private $reBody = [
        'en' => [
            [
                'Your tickets and booking details are now available via your account in the Rail Europe',
                'Times and seats',
            ],
            [
                'mobile tickets for the following journeys.',
                'Times and seats',
            ],
        ],
        'pt' => [
            [
                'Seus tickets e detalhes da reserva já estão disponíveis na sua conta no aplicativo móvel Rail Europe',
                'Horários e assentos',
            ],
            [
                'confirmar que os seguintes tickets foram cancelados com sucesso',
                'Referência de pedido da Rail Europe',
            ],
        ],
        'es' => [
            [
                'Tus billetes y los detalles de tu reserva están incluidos en este correo y están también disponibles a través de la aplicación móvil de Rail Europe',
                'Hora y asientos',
            ],
        ],
    ];
    private static $dictionary = [
        'en' => [
            //            'Ticket reference' => '',
            //            'Times and seats' => '',
            //            'Seat' => '',
            //            'Coach' => '',
            //            'Interchange' => '',
            //            'Tickets' => '',
            //            'Passengers' => '',
            //            'Rail Europe order reference' => '',
            //            'You have been charged in' => '',

            // Cancelled
            //            'Journey' => '',
            //            'Description' => '',
            'following tickets were successfully cancelled' => ['following tickets were successfully cancelled', 'following ticket was successfully cancelled'],
        ],
        'pt' => [
            'Ticket reference'            => 'Código de retirada',
            'Times and seats'             => 'Horários e assentos',
            'Seat'                        => 'Assento',
            'Coach'                       => 'Vagão',
            'Interchange'                 => ['Transferência', 'Transferir'],
            'Tickets'                     => 'Tickets',
            'Passengers'                  => 'Passageiros',
            'Rail Europe order reference' => 'Referência de pedido da Rail Europe',
            'You have been charged in'    => 'Você foi cobrado em ',

            // Cancelled
            'Journey'                                       => 'Viagem',
            'Description'                                   => 'Descrição',
            'following tickets were successfully cancelled' => ['confirmar que os seguintes tickets foram cancelados com sucesso'],
        ],
        'es' => [
            //            'Ticket reference' => '',
            'Times and seats' => 'Hora y asientos',
            'Seat'            => 'Asiento',
            'Coach'           => 'Vagón',
            //            'Interchange' => '',
            'Tickets'                     => 'Billetes',
            'Passengers'                  => 'Pasajeros',
            'Rail Europe order reference' => 'Referencia del pedido en Rail Europe',
            'You have been charged in'    => 'Se ha realizado el cobro en',

            // Cancelled
            //            'Journey' => '',
            //            'Description' => '',
            //            'following tickets were successfully cancelled' => [''],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $t = $email->add()->train();

        $conf = $this->http->FindSingleNode("//*[{$this->starts($this->t('Ticket reference'))}]/following-sibling::*[1]", null, false, '/^[\w\-]{5,}$/');
        $condDesc = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket reference'))}]");

        if ($conf) {
            $t->general()->confirmation($conf, $condDesc);
        }

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Rail Europe order reference'))}]",
             null, false, '/:\s*([\w\-]{5,})\s*$/');
        $condDesc = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Rail Europe order reference'))}]", null, true,
            "/({$this->opt($this->t('Rail Europe order reference'))})/");

        if (!$conf) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rail Europe order reference'))}]/following::text()[normalize-space()][1]",
                 null, false, '/^[\w\-]{5,}$/');
            $condDesc = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rail Europe order reference'))}]");
        }

        if ($conf) {
            $t->general()->confirmation($conf, $condDesc);
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("following tickets were successfully cancelled")) . "])[1]"))) {
            $t->general()
                ->cancelled()
                ->status("Cancelled")
            ;
        }

        $travellers = $this->http->FindNodes("//h4[{$this->starts($this->t('Passengers'))}]/following-sibling::ul/li",
            null, '/^\s*(.+?)\s*\(/');

        if (!$travellers) {
            $travellers = $this->http->FindNodes("//h4[{$this->starts($this->t('Passengers'))}]/following-sibling::p",
                null, '/^o*\s*(.+?)\s*\(/');
        }
        $travellers = preg_replace("/^\s*(?:Mrs|Mr|Dr|Miss|Mstr|Ms|Sr\.|Sra\.|Srta\.)\s+/", '', $travellers);

        if ($t->getCancelled() === true) {
            if (!$travellers) {
                $travellers = array_filter($this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Journey'))}] and *[2][{$this->eq($this->t('Description'))}]]/following-sibling::tr/*[2]",
                    null, '/^\s*([[:alpha:] \-]+?)\s*$/'));
            }

            if ($travellers) {
                $t->general()->travellers(array_unique($travellers));
            }
        } else {
            if ($travellers) {
                $t->general()->travellers(array_unique($travellers));
            }
        }

        $year = $this->http->FindSingleNode("(//h4[{$this->starts($this->t('Passengers'))}]/preceding::text()[normalize-space()][1])[1]",
            null, false, '/\d{4}$/');

        if (empty($year)) {
            $year = $this->http->FindSingleNode("(//h4[{$this->starts($this->t('Passengers'))}][preceding::text()[normalize-space()][1][not(contains(., '20'))]]/preceding::text()[normalize-space()][2])[1]",
                null, false, '/\d{4}$/');
        }
        //$this->logger->debug("Year: {$year}");

        /**
         * Italo #8996
         * 18:57
         * Venice S. Lucia
         * Mon 31 Aug
         * Seat 77, Coach 6
         * Seat 79, Coach 6
         * 20:10
         * Verona Porta Nuova.
         */
        $xpath = "//h2[{$this->eq($this->t('Times and seats'))}]/following::text()[following::h2[{$this->eq($this->t('Tickets'))}]]";
        $text = "\n" . join("\n",
            $this->http->FindNodes($xpath));

        $nodes = $this->splitter('/(\w+[^\n\#]+?#[\dA-Z]*\d+\s*\d+:\d+.+)/', $text);

        if (count($nodes) == 0) {
            $nodes = $this->splitter('/(\w+\s*\w*\s*[^\n\#]+?#?[\dA-Z]*\d+\s*\d+:\d+.+)/', $text);
        }

        foreach ($nodes as $node) {
            $s = $t->addSegment();

            if (preg_match('/(\w+[^\n\#]+?)#([\dA-Z]*\d+)\s*(\d+:\d+)\s*(.+)\s*\n\s*(\w+\s*\d{1,2}\s*\w+)\s*\n/', $node, $m)
                || preg_match('/(\w+[^\n\#]+?)#([\dA-Z]*\d+)\s*(\d+:\d+)\s*(.+)\s*\n\s*/', $node, $m)
                || preg_match('/(\w+\s*\w*\s*[^\n\#]+?)\n([\dA-Z]*\d+)\s*(\d+:\d+)\s*(.+)\s*\n\s*(\w+\s*\d{1,2}\s*\w+)\s*\n/', $node, $m)
                || preg_match('/(\w+\s*\w*\s*[^\n\#]+?)\n([\dA-Z]*\d+)\s*(\d+:\d+)\s*(.+)\s*\n\s*/', $node, $m)) {
                if (isset($m[5])) {
                    $this->date = "$m[5] $year";
                }
                $s->extra()->service($m[1]);
                $s->extra()->number($m[2]);
                $s->departure()->date($this->normalizeDate("{$this->date}, {$m[3]}"));
                $s->departure()->name(trim($m[4]) . ', Europe')->geoTip('Europe');
            }

            if (isset($this->date) && (preg_match("/\d+:\d+.+?(\d+:\d+)\s*(.+)\s+{$this->opt($this->t('Interchange'))}/s", $node, $m) || preg_match('/\d+:\d+.+?(\d+:\d+)\s*(.+)\n\n\n\n/s', $node, $m) || preg_match('/\d+:\d+.+?(\d+:\d+)\s*(.+)$/s', $node, $m))) {
                $m[2] = preg_replace("/\s*\n\s*(\d+ (?:horas|minutos))(\s*,\s*\d+ (?:horas|minutos))?\s*$/", '', $m[2]);
                $s->arrival()->date($this->normalizeDate("{$this->date}, {$m[1]}"));
                $s->arrival()->name(trim($m[2]) . ', Europe')->geoTip('Europe');
            }

            if (preg_match_all("/{$this->opt($this->t('Seat'))} (\w+)/", $node, $m)) {
                $s->extra()->seats(array_unique($m[1]));
            }

            if (preg_match_all("/{$this->opt($this->t('Coach'))} (\w+)/", $node, $m)) {
                $coach = array_unique($m[1]);

                if (count($coach) == 1) {
                    $s->extra()->car($coach[0]);
                }
            }
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You have been charged in'))}]", null, false, '/\(([A-Z]{3})\)/');
        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You have been charged in'))}]/preceding::em[1]");

        if (preg_match("/^\s*(?<currency>\D+?)\s*(?<amount>\d[,.'\d ]*)/m", $price, $m)
            || preg_match("/^\s*(?<amount>\d[,.'\d ]*)\s*(?<currency>\D+?)\s*/m", $price, $m)
        ) {
            $t->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($currency);
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Rail Europe') === false
        ) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //Miércoles, 19 de mayo de 2021
            // Qui 18 Ago 2022, 11:20`
            "/^\s*([[:alpha:]\-]+)\s*(\d+)\s*([[:alpha:]]+)\s*(\d{4})[,\s]+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui",
        ];
        $out = [
            "$1, $2 $3 $4, $5",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+), (?<time>\d+:\d+.*)$#", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);

            if (!empty($date)) {
                $date = strtotime($m['time'], $date);
            }
        } elseif (preg_match("#\d{4}#", $date, $m)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
