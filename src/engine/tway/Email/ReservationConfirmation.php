<?php

namespace AwardWallet\Engine\tway\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "tway/it-365266882.eml, tway/it-367567388.eml, tway/it-367715807.eml, tway/it-730780643.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Reservation No.' => 'Reservation No.',
            't’way Air.'      => ['t’way Air.', 'T’way Air.'],
        ],
    ];

    private $detectFrom = "no_reply@twayair.com";
    private $detectSubject = [
        // en
        '[T’way Air] Booking No. :',
        '[T\'way Air] Reservation Confirmation Form (E-Ticket)',
        '[T\'way Air] Reservation Confirmation Form (E-Ticket)',
    ];
    private $detectBody = [
        'en' => [
            'Itinerary Information By Section',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]twayair\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false
            && stripos($headers["subject"], '[T’way Air]') === false) {
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
        $this->assignLang();
        // detect Provider
        if ($this->http->XPath->query("//a[{$this->contains('.twayair.com', '@href')}]")->length === 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('t’way Air.'))}]")->length === 0
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

        if (!$this->lang) {
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Reservation No."])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Reservation No.'])}]")->length > 0) {
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
            ->confirmation($this->http->FindSingleNode("//tr[not(.//tr)][*[1][{$this->eq($this->t('Reservation No.'))}]]/following::tr[not(.//tr)][1]/*[1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/"), $this->t('Reservation No.'))
            ->date($this->normalizeDate($this->http->FindSingleNode("//tr[not(.//tr)][*[2][{$this->eq($this->t('Reservation Date'))}]]/following::tr[not(.//tr)][1]/*[2]")));

        // Travellers and Tickets
        $passNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Information'))}]/following::tbody[normalize-space(.)][1]/tr[normalize-space(.)]");

        foreach ($passNodes as $passNode) {
            $traveller = $this->http->FindSingleNode("./descendant::td[normalize-space(.)][1]", $passNode, true, "/^(\D+\s*\/\s*\D\*+\D)\s*\|/") ??
                $this->http->FindSingleNode("./descendant::td[normalize-space(.)][2]", $passNode, true, "/^\D+\s*\/\s*\D\*+\D$/");
            $ticket = $this->http->FindSingleNode("./descendant::td[normalize-space(.)][4]", $passNode, true, "/^\d{10,}$/");

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $traveller);
            }
            $f->addTraveller($traveller, false);
        }

        // Segments
        $xpath = "//tr[*[1][{$this->eq($this->t('Segment'))}]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()][not({$this->contains($this->t('Departure Date'))})][not(starts-with(normalize-space(), '※'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][1]", $root));

            // Airline
            $node = $this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][2]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $routeRe = "/^\s*(?<code>[A-Z]{3})\s*\n\s*(?<name>.+?)\s*\n\s*(?<time>\d+:\d+(?: *[aApP][mM])?)(?<overnight>\s*[-+] ?\d)?\s*$/i";

            // Departure
            $departure = implode("\n", $this->http->FindNodes("*[3]/descendant::td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match($routeRe, $departure, $m)) {
                if (preg_match("/^\s*(.+?) T(\w+)\s*$/", $m['name'], $mt)) {
                    $s->departure()
                        ->terminal($mt[2]);
                    $m['name'] = $mt[1];
                }
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($date ? strtotime($m['time'], $date) : null)
                ;

                if (!empty($m['overnight']) && !empty($s->getDepDate())) {
                    $s->departure()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getDepDate()))
                    ;
                }
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant::td[normalize-space()][3]//text()[normalize-space()]", $root));

            if (preg_match($routeRe, $arrival, $m)) {
                if (preg_match("/^\s*(.+?) T(\w+)\s*$/", $m['name'], $mt)) {
                    $s->arrival()
                        ->terminal($mt[2]);
                    $m['name'] = $mt[1];
                }
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($date ? strtotime($m['time'], $date) : null)
                ;

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getArrDate()))
                    ;
                }
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("*[3]/descendant::td[normalize-space()][2]", $root, true, "/^\s*\d+\s*h\s*\d+\s*m\s*$/"))
                ->aircraft($this->http->FindSingleNode("*[4]", $root));

            $status = $this->http->FindSingleNode("./td[normalize-space()][last()]", $root, true, "/^\w+$/");

            if (!empty($status)) {
                $s->setStatus($status);
            }
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        $in = [
            // 2023-05-18(Thu)
            '/^\s*(\d{4})-(\d{2})-(\d{2})\s*\(\s*[[:alpha:]]+\s*\)\s*$/ui',
        ];
        $out = [
            '$3.$2.$1',
        ];

        $date = preg_replace($in, $out, $date);

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
}
