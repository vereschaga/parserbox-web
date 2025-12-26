<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "navan/it-423491576.eml, navan/it-431854160.eml, navan/it-432251933.eml, navan/it-433333494.eml, navan/it-434751362.eml, navan/it-656046894.eml";
    public $subjects = [
        '/Your.*\s[A-Z]{3}\-[A-Z]{3}\s*flight is confirmed$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Flights details'  => ['Flights details', 'Booking details', 'View itinerary'],
            'Your flight is'   => ['Your flight is', 'Your flight was'],
            'E-ticket number'  => ['E-ticket number', 'ETickets:'],
            'Traveler details' => ['Traveler details', 'Traveler'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@navan.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Navan booking ID')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('E-ticket number'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('being ticketed'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight is'))}]/following::text()[normalize-space()][1][contains(normalize-space(), 'canceled')]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('unless you cancel it within the next 24 hours'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flights details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]navan\.com$/', $from) > 0;
    }

    public function ParseFlightHTML(Email $email): void
    {
        $patterns = [
            'date'          => '.+\b\d{4}', // Tue, Jun 27, 2023
            'dateShort'     => '\b(?:[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)\b', // May 02    |    02 May
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your flight is'))}]/following::text()[normalize-space()][1][contains(normalize-space(), 'canceled')]")->length > 0) {
            $f->general()
                ->cancelled();
        }

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Navan booking ID')]/following::text()[normalize-space()][1]"), 'Navan booking ID');

        $confArray = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='Confirmation:']/ancestor::tr[1]/following-sibling::tr")));

        if (count($confArray) == 0) {
            $confArray = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='Airline booking confirmation']/ancestor::td[1]", null, "/{$this->opt($this->t('Airline booking confirmation'))}\s*([A-Z\d]{6})\s*/")));
        }

        $confs = [];

        foreach ($confArray as $conf) {
            if (stripos($conf, 'Navan booking ID:') !== false) {
                break;
            }

            if (preg_match("/^([A-Z\d]{6})$/", $conf, $m) || preg_match("/^.+\s([A-Z\d]{6})$/", $conf, $m)) {
                if (!in_array($m[1], $confs)) {
                    $confs[] = $m[1];
                    $f->general()
                        ->confirmation($m[1]);
                }
            }
        }

        if ($this->http->XPath->query("//td[contains(normalize-space(), 'This booking will be') and contains(normalize-space(), 'approved') and contains(normalize-space(), 'unless you cancel it within the next 24 hours')]")->length > 0) {
            $f->general()
                ->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your flight is')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your flight is'))}\s*(.+)/");

        if (!empty($status)) {
            $f->setStatus($status);
        }

        $xpathInitials = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆"';

        $areNamesFull = true;
        $travellers = array_filter($this->http->FindNodes("//table[ descendant::*[../self::tr and normalize-space()][1][{$this->starts($this->t('E-ticket number'))}] ]/preceding-sibling::*[normalize-space()][1][self::div and count(descendant::text()[normalize-space()])=1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) === 0) {
            $areNamesFull = true;
            $travellers = array_filter($this->http->FindNodes("//tr[{$this->eq($this->t('Traveler details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathInitials}] ]/*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));
        }

        if (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), $areNamesFull);
        }

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('E-ticket number'))}]/ancestor::td[1]", null, "/{$this->opt($this->t('E-ticket number'))}[:\s]*({$patterns['eTicket']})$/"));

        if (count($tickets) == 0) {
            $tickets = array_filter([$this->http->FindSingleNode("//text()[{$this->starts($this->t('E-ticket number'))}]/ancestor::tr[1]/following::tr[1]", null, true, "/^{$patterns['eTicket']}$/")]);
        }

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Frequent flyer number')]/ancestor::td[1]", null, "/{$this->opt($this->t('Frequent flyer number'))}\s*([\dA-Z]{4}[•]*[A-Z\d]{4})\s*/")));

        if (count($accounts) == 0) {
            $accounts = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='Traveler details']/ancestor::table[1]/descendant::img/following::text()[normalize-space()][2]", null, "/\s([•\d]+)\s*$/")));
        }

        if ($accounts) {
            $f->setAccountNumbers($accounts, true);
        }

        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::text()[normalize-space()][last()]", null, true, "/^([A-Z]{3})$/");
        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::text()[normalize-space()][2]", null, true, "/^\D*([\d\.\,]+)/");

        if (!empty($currency) && !empty($total)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/ancestor::tr[1]/descendant::text()[normalize-space()][2]", null, true, "/^\D*([\d\.\,]+)/");

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes and Fees']/ancestor::tr[1]/descendant::text()[normalize-space()][2]", null, true, "/^\D*([\d\.\,]+)/");

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Duration:') or (contains(normalize-space(), 'layover in'))]/ancestor::tr[1]/following::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightInfo = implode(' ', $this->http->FindNodes("descendant::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^.+\s(?<airlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<flightNumber>\d+)[,\s]+(?<cabin>\D+)\s+\((?:(?<bookingCode>[A-Z]{1,2})|\D+)\)/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);

                $s->extra()
                    ->cabin($m['cabin']);

                if (!empty($m['bookingCode'])) {
                    $s->extra()
                        ->bookingCode($m['bookingCode']);
                }

                if ($this->http->XPath->query("//text()[contains(normalize-space(), 'layover in')]")->length == 0) {
                    $s->extra()
                        ->duration($this->http->FindSingleNode("./preceding::tr[normalize-space()][1]/descendant::td[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Duration:'))}\s*(.+)/"));
                }
            }
            $flightData = implode("\n", $this->http->FindNodes("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^\d+\:\d+/", $flightData)) {
                $flightData = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));
            }

            $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'©') and contains(normalize-space(),'Navan Inc')]", null, true, "/©\s*(\d{4})\s*Navan Inc/");
            $dateRelative = $year ? strtotime($year . '-01-01') : 0;

            if (preg_match("/(?<depDate>{$patterns['date']})\n(?<arrDate>{$patterns['date']})\n(?<depTime>{$patterns['time']})\n(?<arrTime>{$patterns['time']})\n(?:.*\n)?(?<depCode>[A-Z]{3})\n(?:.+\n){1,5}(?<arrCode>[A-Z]{3})(?:\n|$)/", $flightData, $m) // it-431854160.eml
                || preg_match("/(?<depTime>{$patterns['time']})\n(?<depDate>{$patterns['dateShort']})\n(?<arrDate>{$patterns['dateShort']})\n(?<arrTime>{$patterns['time']})\n(?<depCode>[A-Z]{3})\n(?<arrCode>[A-Z]{3})(?:\n|$)/", $flightData, $m) // it-656046894.eml
            ) {
                if (preg_match("/.+\b\d{4}$/", $m['depDate'])) {
                    $dateDep = strtotime($m['depDate']);
                } elseif (preg_match("/^{$patterns['dateShort']}$/", $m['depDate']) && $dateRelative) {
                    $dateDep = EmailDateHelper::parseDateRelative($this->normalizeDate($m['depDate']), $dateRelative, true, '%D% %Y%');
                } else {
                    $dateDep = null;
                }

                if (preg_match("/.+\b\d{4}$/", $m['arrDate'])) {
                    $dateArr = strtotime($m['arrDate']);
                } elseif (preg_match("/^{$patterns['dateShort']}$/", $m['arrDate']) && $dateRelative) {
                    $dateArr = EmailDateHelper::parseDateRelative($this->normalizeDate($m['arrDate']), $dateRelative, true, '%D% %Y%');
                } else {
                    $dateArr = null;
                }

                $s->departure()->date(strtotime($m['depTime'], $dateDep))->code($m['depCode']);
                $s->arrival()->date(strtotime($m['arrTime'], $dateArr))->code($m['arrCode']);
            }

            $depTerminal = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Terminal')][1]/ancestor::tr[1]/descendant::td[1]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

            if ($depTerminal !== null) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Terminal')][1]/ancestor::tr[1]/descendant::td[2]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

            if ($arrTerminal !== null) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $seats = array_filter($this->http->FindNodes("descendant::text()[normalize-space()='Seat']/ancestor::tr[1]/descendant::text()[normalize-space()][not(normalize-space()='Seat')]", $root, "/^\d+[A-Z]$/"));

            if (count($seats) === 0) {
                $seats = array_filter($this->http->FindNodes("descendant::*[ tr[normalize-space()][1][normalize-space()='Seat'] ]/tr[normalize-space()]/descendant::*[count(node()[normalize-space()])=2][1]/node()[normalize-space()][1]", $root, "/^\d+[A-Z]$/"));
            }

            if (count($seats) === 0 && !empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Traveler details'))}]/following::text()[normalize-space()='Seats:']/ancestor::table[1]/descendant::tr/td[2]/descendant::text()[normalize-space()][2][{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]", null, "/^\d+[A-Z]$/"));
            }

            if (count($seats) > 0) {
                $s->extra()->seats($seats);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlightHTML($email);

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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // May 02
            '/^([[:alpha:]]+)\s+(\d{1,2})$/u',
        ];
        $out = [
            '$2 $1',
        ];

        return preg_replace($in, $out, $text);
    }
}
