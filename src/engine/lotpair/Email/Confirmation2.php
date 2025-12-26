<?php

namespace AwardWallet\Engine\lotpair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation2 extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-154606396.eml, lotpair/it-41029991.eml, lotpair/it-41214422.eml, lotpair/it-41220699.eml"; // +1 bcd, lang: pl

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Booking confirmation' => ['Booking confirmation'],
            'Seat selection'       => ['Seat selection'],
            'Adult'                => ['Adult', 'adult', 'ADULT'],
        ],
        'pl' => [
            'Booking confirmation' => ['Potwierdzenie rezerwacji'],
            'Seat selection'       => ['Wybrane miejsce:'],
            'Adult'                => ['Dorosły'],
            'Ticket number'        => ['Numer biletu:'],
            'Cabin class'          => ['Klasa:'],
            'Paid amount:'         => ['Zapłacono:'],
        ],
    ];

    private $detectors = [
        'en' => ['Check available ancillaries and order them now'],
        'pl' => ['Sprawdź dostępne usługi dodatkowe i zamów je już teraz!'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lot.pl') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return preg_match('/Confirmation for [[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]] | Booking number: [A-Z\d]{5,}/', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getBody());
        }

        if ($this->http->XPath->query('//a[contains(@href,".lot.com/") or contains(@href,"www.lot.com") or contains(@href,"book.lot.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getBody());
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($email);
        $email->setType('Confirmation2' . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking confirmation'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking confirmation'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathPRow = "tr[ not(.//tr) and descendant::text()[normalize-space()][2][{$this->contains($this->t('Adult'))}] ]";
        $passengerRows = $this->http->XPath->query("//{$xpathPRow} | //tr[not(.//tr) and (preceding-sibling::tr[{$xpathPRow}] or following-sibling::tr[{$xpathPRow}])]");

        foreach ($passengerRows as $pRow) {
            $pName = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $pRow, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
            $f->addTraveller($pName);
            $ticketNumber = $this->http->FindSingleNode('.', $pRow, true, "/{$this->opt($this->t('Ticket number'))}[:\s]+(\d{3}[- ]*\d{5,}[- ]*\d{1,2})\b/");
            if (!empty($ticketNumber) || !empty($this->http->FindSingleNode('.', $pRow, true, "/{$this->opt($this->t('Ticket number'))}[:\s]*(\w.*)$/"))) {
                $f->addTicketNumber($ticketNumber, false);
            }
        }

        $segments = $this->http->XPath->query("//tr[ *[1][string-length(descendant::text()[normalize-space()][1])=3] and *[3][string-length(descendant::text()[normalize-space()][1])=3] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $dateText = $this->http->FindSingleNode('ancestor::table[1]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/*[1]/descendant::tr[count(*)=2][1]', $segment);

            if ($dateText) {
                $date = $this->normalizeDate($dateText);
            }

            $flight = $this->http->FindSingleNode('preceding::tr[normalize-space()][1]', $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $airportDep = $this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][1]", $segment, true, '/^[A-Z]{3}$/');
            $s->departure()->code($airportDep);

            $timeDep = $this->http->FindSingleNode("*[1]/descendant::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]", $segment);

            if (isset($date) && !empty($date) && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            $airportArr = $this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][1]", $segment, true, '/^[A-Z]{3}$/');
            $s->arrival()->code($airportArr);

            //tr[ *[1][string-length(descendant::text()[normalize-space()][1])=3] and *[3][string-length(descendant::text()[normalize-space()][1])=3] ]//*[3]/descendant::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]
            $timeArr = $this->http->FindSingleNode("*[3]/descendant::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]", $segment);

            if (isset($date) && !empty($date) && $timeArr) {
                if (preg_match('/^(?<time>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)\s*\(\s*[+]\s*(?<overtime>\d{1,3})\s*d\s*\)/', $timeArr, $m)) {
                    // 12:30 (+1d)
                    $s->arrival()->date(strtotime($m['time'] . ' +' . $m['overtime'] . 'days', $date));
                } else {
                    $s->arrival()->date(strtotime($timeArr, $date));
                }
            }

            $xpathSDetails = "ancestor::table[1]/ancestor::td[ following-sibling::td[normalize-space()] ][1]/following-sibling::td[normalize-space()]";

            $seatsText = $this->http->FindSingleNode($xpathSDetails . "/descendant::td[not(.//td) and {$this->contains($this->t('Seat selection'))}]/following-sibling::td[normalize-space()]", $segment);

            if (preg_match_all('/\b(\d+[A-Z])\b/', $seatsText, $m)) {
                // 03A /KEVIN VAN DUN
                $s->extra()->seats($m[1]);
            }

            $cabin = $this->http->FindSingleNode($xpathSDetails . "/descendant::td[not(.//td) and {$this->contains($this->t('Cabin class'))}]/following-sibling::td[normalize-space()]", $segment);
            $s->extra()->cabin($cabin, false, true);
        }

        $payment = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Paid amount:'))}]", null, true, "/{$this->opt($this->t('Paid amount:'))}\s*(.+)$/");

        if (preg_match('/^(?<amount>\d[\s,.\'\d]*) ?(?<currency>[A-Z]{3})\b/', $payment, $m)) {
            // 814,80 USD
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Booking confirmation']) || empty($phrases['Seat selection'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking confirmation'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Seat selection'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function normalizeDate($str)
    {
        //$this->logger->critical($str);
        if (!is_string($str) || empty($str)) {
            return '';
        }
        $in = [
            // 05 listopada 2019
            '/^(\d+ \w+ \d{4})$/',
            // 04/07/19
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/',
        ];
        $out = [
            '$1',
            '$2/$1/$3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }
}
