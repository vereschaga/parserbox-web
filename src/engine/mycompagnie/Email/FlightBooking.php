<?php

namespace AwardWallet\Engine\mycompagnie\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "emails2parse/it-722231745.eml, emails2parse/it-722254947.eml, emails2parse/it-726596576.eml, emails2parse/it-726615113.eml, emails2parse/it-727569666.eml, emails2parse/it-730384678.eml, emails2parse/it-730914634.eml, emails2parse/it-730944587.eml, emails2parse/it-731269753.eml, emails2parse/it-731583023.eml, emails2parse/it-732365662.eml, emails2parse/it-732863893.eml, emails2parse/it-733036908.eml, emails2parse/it-735077620.eml, emails2parse/it-735086365.eml, emails2parse/it-737255468.eml, emails2parse/it-738308907.eml, emails2parse/it-738655536.eml, mycompagnie/it-722231745.eml, mycompagnie/it-726615113.eml, mycompagnie/it-730944587.eml, mycompagnie/it-732863893.eml, mycompagnie/it-737255468.eml";
    public $subjects = [
        // en
        'La Compagnie – Your booking confirmation on',
        'La Compagnie - Your Booking confirmation',
        // it
        'La Compagnie – Conferma prenotazione',
    ];

    public $reBody = [
        'en' => ['YOUR TRIP', 'PASSENGERS'],
        'it' => ['IL TUO VIAGGIO', 'PASSEGGERI'],
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Booking number:' => ['Booking number:', 'Booking Number:'],
            'Outbound'        => ['Outbound', 'OUTBOUND'],
            'Return'          => ['Return', 'RETURN'],
        ],
        'it' => [
            'Thank you for choosing La Compagnie' => 'Grazie per averci scelto La Compagnie',
            'YOUR TRIP'                           => 'IL TUO VIAGGIO',
            'PASSENGERS'                          => 'PASSEGGERI',
            'MANAGE YOUR BOOKING'                 => 'GESTIRE LA TUA PRENOTAZIONE',
            'Booking number'                      => 'Numero di prenotazione',
            'Booking number:'                     => 'Numero di prenotazione:',
            'Status:'                             => 'Stato',
            'Ticket'                              => 'Biglietto',
            'DEPARTURE'                           => 'PARTENZA',
            'ARRIVAL'                             => 'ARRIVO',
            'FLIGHT:'                             => 'VOLO:',
            'CLASS:'                              => 'CLASSE:',
            'MEAL:'                               => 'PASTO:',
            'Fares'                               => 'Base tariffaria',
            'Taxes'                               => 'Imposte',
            'TOTAL PAID'                          => 'TOTALE PAGATO',
            'Outbound'                            => 'Andata',
            'Return'                              => 'Ritorno',
            'Terminal'                            => 'Terminale',
            'SEAT'                                => 'POSTO',
        ],
    ];

    public function detectEmailByHeaders(array $headers): bool
    {
        if (isset($headers['from']) && stripos($headers['from'], '@lacompagnie.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser): bool
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing La Compagnie'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('YOUR TRIP'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('PASSENGERS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('MANAGE YOUR BOOKING'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        return preg_match('/[@.]lacompagnie\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        // collect confirmation reservation
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking number:'))}]/following::text()[normalize-space()][string-length()>5][1]", null, false, "/^[A-Z\d]{6}$/"), $this->t('Booking number'))
            ->status($this->http->FindSingleNode("//text()[{$this->eq($this->t('Status:'))}]//following::text()[normalize-space()][string-length()>5][1]", null, false, "/^\w+$/"));

        // collect price
        $costText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fares'))}]/ancestor::tr[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Fares'))}\s*(.+)$/");

        if (!empty($costText)) {
            $cost = $this->getPriceAndCurrency($costText);
            $f->price()->cost(PriceHelper::parse($cost['value'], $cost['currency']));
        }

        $taxText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/ancestor::tr[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Taxes'))}\s*(.+)$/");

        if (!empty($taxText)) {
            $tax = $this->getPriceAndCurrency($taxText);
            $f->price()->tax(PriceHelper::parse($tax['value'], $tax['currency']));
        }

        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL PAID'))}]/ancestor::tr[normalize-space()][1]", null, false, "/^{$this->opt($this->t('TOTAL PAID'))}\s*(.+)$/");

        if (!empty($totalText)) {
            $total = $this->getPriceAndCurrency($totalText);
            $f->price()
                ->total(PriceHelper::parse($total['value'], $total['currency']))
                ->currency($total['currency']);
        }

        // collect travellers
        $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Ticket'))}]/ancestor::table[normalize-space()][1]/descendant::th[normalize-space()][1]", null, "/^[\w\.]+\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");

        if (!empty($travellers)) {
            $f->setTravellers($travellers, true);
        }

        // collect ticketNumbers and accountNumbers
        $ticketsTexts = $this->http->FindNodes("//text()[{$this->contains($this->t('Ticket'))}]/ancestor::table[normalize-space()][1]", null, "/^(.+\#\d+).*$/");

        foreach ($ticketsTexts as $ticketsText) {
            if (preg_match("/^[\w\.]+\s*(?<passName>.+?)\s*(?<accountNumber>[A-Z]{2}\d+)?\s*{$this->opt($this->t('Ticket'))}\s*\#\s*(?<ticketNumber1>\d+)[\#\/\s]*(?<ticketNumber2>\d+)?$/", $ticketsText, $m)) {
                if (!empty($m['ticketNumber1'])) {
                    $f->addTicketNumber($m['ticketNumber1'], false, $m['passName']);
                }

                if (!empty($m['ticketNumber2'])) {
                    $f->addTicketNumber($m['ticketNumber2'], false, $m['passName']);
                }

                if (!empty($m['accountNumber'])) {
                    $f->addAccountNumber($m['accountNumber'], false, $m['passName']);
                }
            }
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[normalize-space()][1]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('FLIGHT:'))}][1]/ancestor::td[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('FLIGHT:'))}\s*(.+)$/");

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})(?:$|\D)/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $servicesInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CLASS:'))}][1]/ancestor::td[normalize-space()][1]", $root);

            if (preg_match("/^{$this->opt($this->t('CLASS:'))}\s*(?<cabin>.+?)\s*{$this->opt($this->t('MEAL:'))}\s*(?<meal>.+)$/", $servicesInfo, $m)) {
                if (!empty($m['cabin'])) {
                    $s->setCabin($m['cabin']);
                }

                if (!empty($m['cabin'])) {
                    $s->addMeal($m['meal']);
                }
            }

            $depDay = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]", $root, true, "/^\D+?((?:\s+[\w\,]+){2}\s*\d{4})$/");

            $depInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::td[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('DEPARTURE'))}\s*(.+)$/");

            if (preg_match("/^(?<depTime>\d+\:\d+\s*[\w.]{2,4})\s*(?<depName>.+?)\s*(?<depCode>[A-Z]{3})\s*(?:{$this->opt($this->t('Terminal'))}\s*(?<depTerminal>\S+))?$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($depDay . ', ' . $m['depTime']));

                if (!empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL'))}]/ancestor::td[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('ARRIVAL'))}\s*(.+)$/");

            if (preg_match("/^(?<arrTime>\d+\:\d+\s*[\w.]{2,4})\s*(?:\((?<dayShift>.+)\))?\s*(?<arrName>.+?)\s*(?<arrCode>[A-Z]{3})\s*(?:{$this->opt($this->t('Terminal'))}\s*(?<arrTerminal>\S+))?$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);

                if (!empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }

                if (empty($m['dayShift'])) {
                    $s->setArrDate($this->normalizeDate($depDay . ', ' . $m['arrTime']));
                } else {
                    $s->setArrDate(strtotime($m['dayShift'], $this->normalizeDate($depDay . ', ' . $m['arrTime'])));
                }
            }

            // collect seats
            foreach ($f->getTravellers() as $traveller) {
                $flightCodes = $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/following::th[{$this->contains($traveller[0])}]/following::text()[{$this->eq($s->getAirlineName() . $s->getFlightNumber())}][1]/ancestor::tr[normalize-space()][1]/td[normalize-space()]");
                $tdNumber = array_search($s->getAirlineName() . $s->getFlightNumber(), $flightCodes) + 1;
                $seat = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PASSENGERS'))}]/following::th[{$this->contains($traveller[0])}]/following::text()[{$this->eq($this->t('SEAT'))}][1]/ancestor::tr[normalize-space()][1]/td[normalize-space()][{$tdNumber}]", null, true, "/^\s*(\d+[A-Z])\s*$/")
                    ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('PASSENGERS'))}]/following::th[{$this->contains($traveller[0])}]/following::text()[{$this->eq($s->getAirlineName() . ' ' . $s->getFlightNumber())}][1]/ancestor::tr[normalize-space()][1]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d+[A-Z])\s*$/");

                if (!empty($seat)) {
                    $s->addSeat($seat, false, false, $traveller[0]);
                }
            }
        }
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
        foreach ($this->reBody as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($words[1])}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function getPriceAndCurrency(string $priceText)
    {
        if (preg_match("/^(?<currency>\D+?)\s*(?<price>[\d\.\,\']+)$/", $priceText, $m)
            || preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D+?)$/", $priceText, $m)) {
            return ['value' => $m['price'], 'currency' => $this->normalizeCurrency($m['currency'])];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})\s*\,\s*(\d+\:\d+\s*[\w.]{2,4})\s*$#u", //14 Gennaio 2025, 00:00 A.M.
            "#^\s*(\w+)\s*(?:de\s+)?(\w+)th\,(?:\s+de)?\s*(\d{4})\s*\,\s*(\d+\:\d+\s*[\w.]{2,4})\s*$#u", //October 11th, 2024, 00:00 A.M.
        ];
        $out = [
            "$1 $2 $3 $4",
            "$2 $1 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
