<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers velocity/ETicket (in favor of velocity/ETicket)

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "golair/it-144854598.eml, golair/it-145451744.eml, golair/it-152436114.eml, golair/it-268417762.eml";
    public $subjects = [
        'Compra de passagem confirmada com sucesso',
        'Danke für Ihre Buchung bei Etihad Airways mit der Nummer',
        ': Ticket Purchased Successfully',
        // pt
        'confirmada com sucesso',
        // es
        'comprada con éxito',
    ];

    public $lang = '';
    public $provCode;
    public $subject;
    public $date;
    public $pax = [];
    public $account = [];
    public $detectLang = [
        'pt' => ['Obrigada por escolher a GOL', 'Sua compra foi realizada com sucesso'],
        'es' => ['Tu compra se ha realizado con éxito para'],
        'de' => ['Danke für Ihre Buchung bei Etihad Airways'],
        'en' => ['Your purchase has been successful', 'Thanks for choosing GOL.', 'Here\'s your itinerary:', 'Itinerary Confirmation'],
    ];

    public static $providers = [
        'golair' => [
            'from' => '@voegol.com.br',

            'body' => [
                '.voegol.com.br',
                // pt
                'Obrigada por escolher a GOL',
                // en
                'Thanks for choosing GOL.',
            ],
        ],
        'etihad' => [
            'from' => '@etihad.ae',

            'body' => [
                '.etihad.com',
                // de
                'Danke für Ihre Buchung bei Etihad Airways',
            ],
        ],

        'batikair' => [
            'from' => '@batikair.com',

            'body' => [
                // '.etihad.com',
                // en
                // 'Thanks for choosing Lion Air Group',
            ],
        ],
        'skyair' => [
            'from' => '@skyairline.com',

            'body' => [
                '.skyairline.com',
                // en
                // 'Thanks for choosing Lion Air Group',
            ],
        ],
    ];

    public static $dictionary = [
        'en' => [
            'Reservation code'  => ['Your Etihad Airways reference is:', 'Reservation code', 'YOUR RESERVATION CODE IS'],
            'Seats:'            => ['Seats:', 'Seat(s):'],
            'Your ticket(s):'   => ['Your ticket(s):', 'Your ticket(s) is/are:', 'Your ticket(s) is/are : No Tiket Anda :'],
        ],
        'pt' => [
            'Reservation code'  => ['Código da reserva', 'SEU CÓDIGO DE RESERVA É'],
            'Aircraft:'         => 'Aeronave:',
            'Seats:'            => 'Assento:',
            'Your ticket(s):'   => ['Teu(s) bilhete(s):', 'Your ticket(s) is/are:'],
            'Cabin:'            => 'Cabine:',
            'Class:'            => 'Classe:',
        ],

        'de' => [
            'Reservation code'              => 'Ihre Etihad Airways Referenz ist:',
            'Aircraft:'                     => 'Flugzeug:',
            'Seats:'                        => 'Sitzplatz:',
            'Your ticket(s):'               => 'Ihr(e) Ticket(s):',
            'Cabin:'                        => 'Kabine:',
            'Meal:'                         => 'Mahlzeit:',
            'Programa Viajante Frequente:'  => 'Vielflieger:',
        ],
        'es' => [
            'Reservation code'              => 'TU CÓDIGO DE RESERVA ES',
            'Aircraft:'                     => 'Avión:',
            'Seats:'                        => 'Asiento:',
            'Your ticket(s):'               => 'Tus tickets son:',
            'Cabin:'                        => 'Cabina:',
            // 'Meal:'                         => 'Mahlzeit:',
            // 'Programa Viajante Frequente:'  => 'Vielflieger:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $key => $provider) {
            if (isset($headers['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        $this->provCode = $key;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'icon-air') or contains(@id, '-segment-icon')]")->length == 0
            && $this->http->XPath->query("//img[contains(@src, 'arrow-right') or contains(@id, 'arrow-right')]")->length == 0
        ) {
            return false;
        }

        if (empty($this->provCode)) {
            foreach (self::$providers as $key => $provider) {
                if (isset($provider['body'])) {
                    if ($this->http->XPath->query("//text()[{$this->contains($provider['body'])}]")->length > 0
                        || $this->http->XPath->query("//a[{$this->contains($provider['body'], '@href')}]")->length > 0
                    ) {
                        $this->provCode = $key;

                        break;
                    }
                }
            }
        }

        if ($this->provCode) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $key => $provider) {
            if ($key === 'golair' && preg_match("/{$this->opt($this->t($provider['from']))}$/u", $from)) {
                return true;
            }
        }

        return false;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation code'))}]/following::text()[normalize-space()][not(normalize-space() = ':')][1]", null, true, "/^([A-Z\d]{5,7})$/");

        if (empty($confirmation)) {
            $confirmation = $this->re("/\:\s*([A-Z]{5,7})/", $this->subject);
        }
        $f->general()
            ->confirmation($confirmation);

        $nodes = $this->http->XPath->query("//img[contains(@src, 'icon-air') or contains(@id, '-segment-icon')]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightInfo = $this->http->FindSingleNode("./descendant::tr[1]", $root);

            if (preg_match("/^([A-Z]{3})\s+.+\s+\-\s+([A-Z]{3})\s+/u", $flightInfo, $m)) {
                $s->departure()
                    ->code($m[1]);

                $s->arrival()
                    ->code($m[2]);
            }

            $duration = $this->http->FindSingleNode("./descendant::tr[string-length()>5][2]", $root, true, "/^\s*[^⋅]+[⋅]\s*([^⋅]+?)\s*[⋅]/u");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $aircraft = $this->http->FindSingleNode("./following::table[1]/descendant::text()[{$this->starts($this->t('Aircraft:'))}]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Aircraft:'))}\s*(.+)/");

            if (stripos($aircraft, $this->t('Meal:')) == false) {
                $s->extra()
                    ->aircraft($aircraft);
            } else {
                $s->extra()
                    ->aircraft($this->re("/(.+){$this->t('Meal:')}/", $aircraft));
            }

            $cabin = $this->http->FindSingleNode("./following::table[1]/descendant::text()[{$this->starts($this->t('Cabin:'))}]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Cabin:'))}\s*(\w+)/u");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $meal = $this->http->FindSingleNode("./following::table[1]/descendant::text()[{$this->starts($this->t('Meal:'))}]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Meal:'))}\s*(\w+)/u");

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $bookingCode = $this->http->FindSingleNode("./following::table[1]/descendant::text()[{$this->starts($this->t('Classe:'))}]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Classe:'))}\s*(\w+)/u");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $mainDate = $this->http->FindSingleNode("./descendant::tr[string-length()>5][2]/descendant::text()[normalize-space()][1]", $root, true, "/^(\w+\,\s*\w+\s*\w+)/u");
            $year = date('Y', $this->normalizeDate($mainDate));

            $flightNumber = $this->http->FindSingleNode("./following::table[1]/descendant::text()[string-length()>5][1]/ancestor::tr[1]", $root, true, "/\,\s*([A-Z\d]{2}\s*\d{1,4})\s*/u");

            if (preg_match("/([A-Z\d]{2})\s*(\d{1,4})/", $flightNumber, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $date = $this->http->FindSingleNode("./following::table[1]/descendant::img[contains(@src, 'arrow-right') or contains(@id, 'arrow-right')]/following::text()[contains(normalize-space(), ':')][1]/ancestor::td[1]", $root);

            if (!empty($date)) {
                $date = preg_replace("/\s+\([+]\d+\)/", "", $date);
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $year));
            }

            $depTerminal = $this->http->FindSingleNode("./following::table[1]/descendant::img[contains(@src, 'arrow-right') or contains(@id, 'arrow-right')]/following::text()[contains(normalize-space(), ':')][2]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root, true, "/{$this->opt($this->t('TERMINAL'))}\s*(\S+)/");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $date = $this->http->FindSingleNode("./following::table[1]/descendant::img[contains(@src, 'arrow-right') or contains(@id, 'arrow-right')]/following::text()[contains(normalize-space(), ':')][2]/ancestor::td[1]", $root);

            if (!empty($date)) {
                $date = preg_replace("/\s+\([+]\d+\)/", "", $date);
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $year));
            }

            $arrTerminal = $this->http->FindSingleNode("./following::table[1]/descendant::img[contains(@src, 'arrow-right') or contains(@id, 'arrow-right')]/following::text()[contains(normalize-space(), ':')][2]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root, true, "/{$this->opt($this->t('TERMINAL'))}\s*(\S+)/");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $seats = $this->http->XPath->query("./following::table[1]/descendant::text()[{$this->eq($this->t('Seats:'))}]/following::text()[normalize-space()][1]", $root);

            if (count($seats) == 0) {
                $seats = $this->http->XPath->query("./following::table[1]/descendant::text()[{$this->contains($this->t('Seats:'))}]", $root, "/^{$this->opt($this->t('Seats:'))}\s*(\d+[A-Z])$/");
            }

            if ($seats->length > 0) {
                foreach ($seats as $sRoot) {
                    $seat = $this->http->FindSingleNode(".", $sRoot, true, "/^\s*(?:{$this->opt($this->t('Seats:'))})?\s*(\d+[A-Z])\b/");
                    $traveller = preg_replace("/^(?:MRS|MR|MISS|MS)/iu", "", $this->http->FindSingleNode("ancestor::td[1]", $sRoot, true, "/^\s*(\D+?)\s*{$this->opt($this->t('Seats:'))}/su"));

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat, false, false, $traveller ? ucwords(strtolower(trim($traveller))) : null);
                    }
                }
            }

            $acc = $this->http->FindSingleNode("./following::table[1]/descendant::text()[{$this->contains($this->t('Programa Viajante Frequente:'))}]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]+)/");

            if (!empty($acc)) {
                $this->account[] = $acc;
            }

            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Your ticket(s):'))}]/ancestor::tr[1]/descendant::a/preceding::text()[normalize-space()][1]",
                null, "/^\s*(\D+?)\s*:\s*$/");

            if (empty($travellers)) {
                $travellers = $this->http->FindNodes("./following::table[1]/descendant::text()[{$this->contains($this->t('Seats:'))}]/ancestor::td[1]",
                    $root, "/^(.+)\s*{$this->opt($this->t('Seats:'))}/su");
            }

            if (count($this->pax) > 0) {
                $this->pax = array_merge($this->pax, $travellers);
            } else {
                $this->pax = $travellers;
            }
        }

        $this->pax = array_map('ucwords', array_map('strtolower', $this->pax));
        $f->general()
            ->travellers(preg_replace("/^(?:MRS|MR|MISS|MS)/iu", "", array_unique(array_filter($this->pax))), true);

        $f->program()
            ->accounts(array_unique(array_filter($this->account)), false);

        $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Your ticket(s):'))}]/ancestor::tr[1]/descendant::a", null, "/^(\d+)$/");
        $ticketsText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Your ticket(s):'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (count($tickets) > 0) {
            foreach ($tickets as $i => $ticket) {
                $traveller = preg_replace("/^(?:MRS|MR|MISS|MS)/iu", "", $this->re("/(?:^|\n) *([^\d\n]+?)\s*:\s*\n[\d,\s]*\b{$ticket}\b/", $ticketsText));
                $f->issued()
                    ->ticket($ticket, false, $traveller ? ucwords(strtolower(trim($traveller))) : null);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->subject = $parser->getSubject();

        $this->assignLang();

        $this->ParseFlight($email);

        if (!empty($this->provCode)) {
            $email->setProviderCode($this->provCode);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    protected function assignLang()
    {
        foreach ($this->detectLang as $lang => $phrases) {
            if ($this->http->XPath->query("//text()[{$this->contains($phrases)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", \"{$s}\")";
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            // 21:15, 30 Mar, 2022
            "#^\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\,\s*(\d+\s*\w+)\,\s*(\d{4})$#ui",
            // 21:15, Mar 30, 2022
            "#^\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\,\s*(\w+)\s*(\d+)\,\s*(\d{4})$#ui",
            // Qua, 30 Mar
            "#^(\w+\,\s*\d+\s*\w+)$#u",
            // Fri, Feb 3
            "#^(\w+),\s*(\w+)\s*(\d+)\s*$#u",
        ];
        $out = [
            "$2 $3, $1",
            "$3 $2 $4, $1",
            "$1 $year",
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
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
