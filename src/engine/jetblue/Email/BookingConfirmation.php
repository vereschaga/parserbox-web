<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-508669018.eml, jetblue/it-509702064.eml, jetblue/it-516779913.eml, jetblue/it-516982086.eml, jetblue/it-630529088.eml, jetblue/it-630647344.eml, jetblue/it-631326124.eml, jetblue/it-749383237.eml";

    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            'Your JetBlue confirmation code is' => ['Your JetBlue confirmation code is', 'Confirmation #'],
            // 'Flight' => '',
            // 'Sold as' => '',
            // 'TrueBlue Number:' => '',
            'Flight #' => ['Flight #', 'Flight '],
            // 'Ticket number:' => ',
            // 'Fare:' => '',
            // 'Seat:' => '',
            // 'Payment Details' => '',
            // 'Purchase Date:' => '',
            // 'Taxes & fees' => '',
            // 'Total' => '',
        ],
        'fr' => [
            'Your JetBlue confirmation code is' => 'Votre code de confirmation JetBlue est',
            'Flight'                            => 'Vol',
            // 'Sold as' => '',
            'TrueBlue Number:'                  => 'TrueBlue Number:',
            'Flight #'                          => 'Vols #',
            'Ticket number:'                    => 'Numéro de billet:',
            'Fare:'                             => 'Tarif:',
            'Seat:'                             => 'Siège:',
            'Payment Details'                   => 'Détails de paiement',
            'Purchase Date:'                    => 'Date d\'achat:',
            'Taxes & fees'                      => 'Impôts et taxes',
            'Total'                             => 'Total',
        ],
        'es' => [
            'Your JetBlue confirmation code is' => 'Tu código de confirmación de JetBlue es',
            'Flight'                            => 'Vuelo',
            // 'Sold as' => '',
            'TrueBlue Number:'                  => 'TrueBlue Number:',
            'Flight #'                          => 'Vuelo #',
            'Ticket number:'                    => 'Número de boleto:',
            'Fare:'                             => 'Tarifa:',
            'Seat:'                             => 'Asiento:',
            'Payment Details'                   => 'Detalles de pago',
            'Fecha de compra:'                  => 'Date d\'achat:',
            'Taxes & fees'                      => 'Impuestos y cargos',
            'Total'                             => 'Total:',
        ],
    ];

    private $detectFrom = "jetblueairways@email.jetblue.com";
    private $detectSubject = [
        // en
        'JetBlue booking confirmation for',
        'Check in for your flight to ',
        // fr
        'Confirmation de reservation pour',
    ];
    private $detectBody = [
        'en' => [
            'Your Flight Itinerary',
        ],
        'fr' => [
            'Votre itinéraire de vol',
            'Tu código de confirmación',
        ],
        'es' => [
            'Tu código de confirmación',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]jetblue\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'JetBlue ') === false
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
            $this->http->XPath->query("//a[{$this->contains(['jetblue'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['JetBlue Airways Corporation'])}]")->length === 0
        ) {
            return false;
        }

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

        $this->date = strtotime($parser->getDate());
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Flight #"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Flight #'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Your JetBlue confirmation code is'))}])[1]",
                null, true, "/{$this->opt($this->t('Your JetBlue confirmation code is'))}\s*([A-Z\d]{5,7})\s*$/"))
        ;
        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Flight #'))}]/ancestor::tr[not({$this->starts($this->t('Flight #'))})][1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]",
            null, '/^\s*([A-Z \-\']+)$/'));

        if (empty($travellers)) {
            $travelerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Traveler Name'))}]/ancestor::tr[1]/ancestor::*[1]/*");

            $segment = '';
            $tickets = [];
            $seatsFromTravellers = [];

            foreach ($travelerRows as $row) {
                if (preg_match("/^\s*{$this->opt($this->t('Traveler Name'))}/", $row->nodeValue, $m)) {
                    continue;
                }

                if (preg_match("/^\s*([A-Z]{3})\s*\W\s*([A-Z]{3})\s*:\s*$/", $row->nodeValue, $m)) {
                    $segment = $m[1] . $m[2];

                    continue;
                }

                $values = $this->http->FindNodes("*", $row);
                $travellers[] = $values[0] ?? '';

                if (preg_match("/^\d{13}$/", $values[1] ?? '')) {
                    $tickets[] = $values[1];
                }

                if (preg_match("/^\d{1,3}[A-Z]$/", $values[2] ?? '')) {
                    $seatsFromTravellers[$segment][] = $values[2];
                }
            }
            $travellers = array_unique($travellers);
            $tickets = array_unique($tickets);
        }
        $f->general()
            ->travellers($travellers);

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('TrueBlue Number:'))}]/following::text()[normalize-space()][1]",
            null, '/^\s*(\d{5,})\s*$/')));

        if (!empty($accounts)) {
            foreach ($accounts as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::table[3]/descendant::text()[normalize-space()][1]", null, true, "/({$this->opt($travellers)})/");

                if (!empty($pax)) {
                    $f->addAccountNumber($account, false, $pax);
                } else {
                    $f->addAccountNumber($account, false);
                }
            }
        }
        // Issued
        if (empty($tickets)) {
            $tickets = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket number:'))}]/following::text()[normalize-space()][1]",
                null, '/^\s*(\d{5,})\s*$/')));
        }

        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::table[3]/descendant::text()[normalize-space()][1]", null, true, "/({$this->opt($travellers)})/");

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $emailDate = $this->date;

        $this->date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Purchase Date:'))}]",
            null, true, "/{$this->opt($this->t('Purchase Date:'))}\s*(.+)\s*$/"), false);

        if (empty($this->date)) {
            $year = $this->http->FindSingleNode("//text()[{$this->contains('JetBlue Airways Corporation')}]",
                null, true, "/©(20\d{2})\s*JetBlue Airways Corporation/");

            if (!empty($year)) {
                $this->date = strtotime('01.01.' . $year);
            }
        }

        if (empty($this->date)) {
            // from headed Forwarded message
            // Date: Wed, Sep 13, 2023 at 8:11 PM
            $this->date = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight #'))}]/preceding::text()[{$this->starts('Date: ')}][1]",
                null, true, "/^\s*Date:\s*(\w+[,.\s]+\w+[,.\s]+\w+[,.\s]+\d{4})(?:(?: at |, )\d{1,2}:\d{2}\s*[ap]m)?\s*$/i"));
        }

        if (empty($this->date)) {
            $this->date = $emailDate;
        }

        // Segments
        $xpath = "//text()[normalize-space() = '-']/ancestor::tr[3][.//text()[{$this->starts($this->t('Flight'))}]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $sText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            $s = $f->addSegment();

            //$mainRe = "/^\s*(?<dCode>[A-Z]{3})\s+(?<aCode>[A-Z]{3})\s+{$this->opt($this->t('Flight'))}(?<flight>[\S\s]+)(?:\n{$this->opt($this->t('Operated by'))}\s*(?<operator>.+))?\n(?<dep>.+\n\d+ ?[:h] ?\d[\s\S]*)\n-\n(?<arr>[\s\S]+)$/";
            $mainRe = "/^\s*(?<dCode>[A-Z]{3})\n*\s*(?<aCode>[A-Z]{3})\n*\s*(?:(?<duration>\d+\s*(?:h|m).*))?\n*{$this->opt($this->t('Flight'))}(?<flight>[\S\s]+)(?:\n{$this->opt($this->t('Operated by'))}\s*(?<operator>.+))?\n(?<dep>.+\n\d+ ?[:h] ?\d[\s\S]*)\n-\n(?<arr>[\s\S]+)$/";

            if (preg_match($mainRe, $sText, $mat)) {
                if (!empty($mat['duration'])) {
                    $s->extra()
                        ->duration($mat['duration']);
                }
                // Airline
                $sold = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::td[1]", $root);

                if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('Sold as'))} (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) (?<fn>\d{1,5})\s*$/", $sold, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                } elseif (preg_match("/.*(\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+)?\b(?<fn>\d{1,5})\s*(?:{$this->opt($this->t('Operated by'))}|$)/", $mat['flight'], $m)) {
                    $s->airline()
                        ->name('jetBlue')
                        ->number($m['fn']);
                }

                $re = "/^\s*(?<date>.+)\n(?<time>\d{1,2} ?[:h] ?\d{2}(?:\s*[ap]m)?)(?:\s*{$this->opt($this->t('Terminal'))}:\s*(?<terminal>.+))?\s*/i";
                $re2 = "/^\s*(?<time>\d{1,2} ?[:h] ?\d{2}(?:\s*[ap]m)?)(?:\s*{$this->opt($this->t('Terminal'))}:\s*(?<terminal>.+))?\s*/i";

                // Departure
                $s->departure()
                    ->code($mat['dCode']);

                if (preg_match($re, $mat['dep'], $m)) {
                    $date = $this->normalizeDate($m['date']);

                    if (!empty($date)) {
                        $s->departure()
                            ->date(strtotime($this->normalizeTime($m['time']), $date))
                            ->strict()
                        ;
                    }

                    if (!empty($m['terminal'])) {
                        $s->departure()
                            ->terminal($m['terminal']);
                    }
                }

                // Arrival
                $s->arrival()
                    ->code($mat['aCode']);

                if (preg_match($re, $mat['arr'], $m) || preg_match($re2, $mat['arr'], $m)) {
                    if (!empty($m['date'])) {
                        $date = $this->normalizeDate($m['date']);
                        $s->arrival()
                            ->strict();
                    } elseif (!empty($s->getDepDate())) {
                        $date = $s->getDepDate();
                    }

                    if (!empty($date)) {
                        $s->arrival()
                            ->date(strtotime($this->normalizeTime($m['time']), $date))
                        ;
                    }

                    if (!empty($m['terminal'])) {
                        $s->arrival()
                            ->terminal($m['terminal']);
                    }
                }

                // Extra
                $number = preg_replace('/^0*/', '', trim($s->getFlightNumber()));
                $flightNumber = array_merge(
                    preg_replace('/^(.+)/', '$1 ' . $s->getFlightNumber(), (array) $this->t('Flight #')),
                    preg_replace('/^(.+)/', '$1 ' . $number, (array) $this->t('Flight #'))
                );
                $fares = array_unique(array_filter($this->http->FindNodes("//node()[{$this->eq($flightNumber)}]/ancestor::*[1]"
                    . "//text()[{$this->eq($this->t('Fare:'))}]/ancestor::td[1]", null, "/^\s*{$this->opt($this->t('Fare:'))}\s*([[:alpha:]]+(?: [[:alpha:]]+)?)\s*$/")));
                // in jetblue fare the same as cabin
                if (count($fares) === 1) {
                    $s->extra()
                        ->cabin($fares[0]);
                }

                $seats = array_filter($this->http->FindNodes("//node()[{$this->eq($flightNumber)}]/ancestor::*[1]"
                    . "//text()[{$this->eq($this->t('Seat:'))}]/following::text()[normalize-space()][1]", null, "/^\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($mat['dCode']) && !empty($mat['aCode']) && isset($seatsFromTravellers[$mat['dCode'] . $mat['aCode']])) {
                    $seats = array_unique($seatsFromTravellers[$mat['dCode'] . $mat['aCode']]);
                }

                if (!empty($seats)) {
                    foreach ($seats as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::table[3]/descendant::text()[normalize-space()][1]", null, true, "/({$this->opt($travellers)})/");

                        if (!empty($pax)) {
                            $s->extra()
                                ->seat($seat, false, false, $pax);
                        } else {
                            $s->extra()
                                ->seat($seat);
                        }
                    }
                }
            }

            $segments = $f->getSegments();

            foreach ($segments as $seg) {
                if ($seg->getId() !== $s->getId()) {
                    if ($s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepCode() == $seg->getDepCode()
                        && $s->getArrCode() == $seg->getArrCode()
                    ) {
                        $f->removeSegment($seg);

                        break;
                    }
                }
            }
        }

        // Price
        $total = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Payment Details'))}]/following::td[{$this->eq($this->t('Total'))}]/following-sibling::td[normalize-space()][1]/text()[normalize-space()]"));

        if (preg_match("/^\s*([\s\S]+\n)?(\d[.,\d]*\s*Points)\s*$/", $total, $m)) {
            $f->price()
                ->spentAwards($m[2]);
            $total = $m[1];
        }

        $currency = null;

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
            || preg_match("#^\s*[^\d\s]{1,3}\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $f->price()
                ->total($this->amount($m['amount'], $currency))
                ->currency($this->currency($m['currency']))
            ;
        }

        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Details'))}]/following::td[{$this->eq($this->t('Taxes & fees'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)
            || preg_match("#^\s*[^\d\s]{1,3}\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $tax, $m)
        ) {
            $f->price()
                ->tax($this->amount($m['amount'], $currency))
            ;
        }

        return true;
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

    private function normalizeDate($date, $relative = true)
    {
        if ($relative && empty($this->date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            // Sat, Oct 07
            '/^([[:alpha:]]+)\,\s*([[:alpha:]]+)\s*(\d+)\s*$/u',
            // jeudi 28 septembre
            '/^([[:alpha:]]+)\s+(\d+)\s+([[:alpha:]]+)\s*$/u',
            //  Feb 20, 2024
            '/^\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*$/u',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
            '$2 $1 $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('$date 2 = '.print_r( $date,true));
        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            if ($weeknum === null) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price, $currency)
    {
        $price = PriceHelper::parse($price, $currency);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeTime($str): string
    {
        $in = [
            // 10h20
            "/^\s*(\d{1,2})\s*h\s*(\d{2})\D*$/",
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }
}
