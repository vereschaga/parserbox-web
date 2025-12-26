<?php

namespace AwardWallet\Engine\greyhound\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ticket extends \TAccountChecker
{
    public $mailFiles = "greyhound/it-109807198.eml, greyhound/it-234771832.eml, greyhound/it-240108643.eml, greyhound/it-65911828.eml";
    private $lang = '';
    private $date = '';
    private $reFrom = ['greyhound.com'];
    private $reProvider = ['Greyhound'];
    private $reSubject = [
        'Greyhound Ticket Purchase Confirmation and Itinerary',
    ];
    private $reBody = [
        'en' => [
            ['Your booking confirmation number is', 'YOUR TRIP DETAILS'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParseTicket(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $t = $email->add()->bus();

        $t->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking confirmation number is:'))}]/following-sibling::*[normalize-space()][1]",
                null, false, '/^([\w\-]{5,})/'),
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking confirmation number is:'))}]")
        );
        $travellers = $this->http->FindNodes("//strong[{$this->starts($this->t('Passengers:'))}]/following-sibling::text()");

        foreach ($travellers as $traveller) {
            $t->general()->traveller($this->http->FindPreg('/^\s*([[:alpha:]\s]{2,})/', false, $traveller));
        }

        $t->program()->phone(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('or call'))}]/following-sibling::span[1]", null, false, '/^[+\d\-\s]{5,}/')
        );

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL ('))}]", null, false, '/\(([A-Z]{3})\)/');
        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL ('))}]/ancestor::*[1]/following-sibling::*[1]");

        if (preg_match("/^\s*(?<currency>.+?)\s*(?<amount>\d[,.'\d]*)/m", $price, $m)) {
            $t->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($currency ?? $this->normalizeCurrency($m['currency']));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('taxes & fees)'))}]");

        if (preg_match("/\(.+?\s*(?<cost>\d[,.'\d]*) plus .+?\s*(?<tax>\d[,.'\d]*) taxes/m", $price, $m)) {
            $t->price()
                ->cost($this->normalizeAmount($m['cost']))
                ->tax($this->normalizeAmount($m['tax']));
        }

        $nodes = $this->http->XPath->query("//text()[contains(.,'Leaving From:')]/ancestor::tr[1]");
        $this->logger->debug("Found nodes: {$nodes->length}");

        foreach ($nodes as $node) {
            $left = trim(join("\n", $this->http->FindNodes(".//text()", $node)));
            $s = $t->addSegment();
            /*
            April 17 2020
            Leaving From: Boston @ 7:00AM
            Carrier: GREYHOUND LINES, INC.
            Schedule Number: GLI2505
             */
            if (preg_match('/^(?<date>.+?\d{4})\s+Leaving From:\s*(?<name>.+?) @ (?<time>\d+:\d+(?:\s*[AP]M)?)\s*Carrier:\s*(.+?)\s+Schedule Number:\s*(?<number>[\w\-]{3,})/',
                $left, $m)) {
                $s->departure()->date2("{$m['date']} {$m['time']}");
                $s->extra()->number($m['number']);
                $s->departure()->name($m['name']);

                $info = join("\n",
                    $this->http->FindNodes("(./ancestor::tr[1]/following-sibling::tr[1]//tr)[1]//text()", $node));

                if (preg_match("/([[:upper:]\s]{5,})\*?\s+(.{10,60})/s", $info, $mi)) {
                    $s->departure()->name($mi[1]);
                    $s->departure()->address(str_replace("\n", ' ', $mi[2]));
                }
            }

            $right = trim(join("\n", $this->http->FindNodes("./following-sibling::tr[1]//text()", $node)));

            if (preg_match('/^(?<date>.+?\d{4})\s+Going To:\s*(?<name>.+?) @ (?<time>\d+:\d+(?:\s*[AP]M)?)\s*Carrier:\s*(.+?)\s+Schedule Number:\s*(?<number>[\w\-]{3,})/',
                $right, $m)) {
                $s->arrival()->date2("{$m['date']} {$m['time']}");
                $s->extra()->number($m['number']);
                $s->arrival()->name($m['name']);

                $info = join("\n",
                    $this->http->FindNodes("(./ancestor::tr[1]/following-sibling::tr[1]//tr)[2]//text()", $node));

                if (preg_match("/([[:upper:]\s]{5,})\*?\s+(.{10,60})/s", $info, $mi)) {
                    $s->arrival()->name($mi[1]);
                    $s->arrival()->address(str_replace("\n", ' ', $mi[2]));
                }
            }
        }
    }

    public function ParseTicket2(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $t = $email->add()->bus();

        $t->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking confirmation number is'))}]", null, false, "/{$this->opt($this->t('Your booking confirmation number is'))}\:?\s([\w\-]{5,})/"),
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking confirmation number is'))}]", null, true, "/({$this->opt($this->t('Your booking confirmation number is'))})/")
        );

        $travellers = $this->http->FindNodes("//text()[(starts-with(normalize-space(.),'Passengers:'))]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()]");

        foreach ($travellers as $traveller) {
            $t->general()->traveller($this->http->FindPreg('/^\s*([[:alpha:]\s]{2,})/', false, $traveller), true);
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total ('))}]", null, false, '/\(([A-Z]{3})\)/');
        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total ('))}]/ancestor::*[1]/following::*[1]");

        if (preg_match("/^\s*(?<currency>.+?)\s*(?<amount>\d[,.'\d]*)/m", $price, $m)) {
            $t->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($currency ?? $this->normalizeCurrency($m['currency']));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('taxes & fees)'))}]");

        if (preg_match("/\(.+?\s*(?<cost>\d[,.'\d]*) plus .+?\s*(?<tax>\d[,.'\d]*) taxes/m", $price, $m)) {
            $t->price()
                ->cost($this->normalizeAmount($m['cost']))
                ->tax($this->normalizeAmount($m['tax']));
        } else {
            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Subtotal:'))}\s*\D([\d\.]+)/u");

            if (!empty($cost)) {
                $t->price()
                    ->cost($cost);
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes and Fees:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Taxes and Fees:'))}\s*\D([\d\.]+)/u");

            if (!empty($tax)) {
                $t->price()
                    ->tax($tax);
            }
        }

        $nodes = $this->http->XPath->query("//text()[contains(.,'Boarding / Leaving from:')]/ancestor::tr[1]");
        $this->logger->debug("Found nodes: {$nodes->length}");

        foreach ($nodes as $node) {
            $left = trim(join("\n", $this->http->FindNodes("./following::tr[3]/descendant::text()[normalize-space()]", $node)));
            $s = $t->addSegment();
            /*
                Aug 01, 2021
                4:15 PM
                700 Atlantic Ave
                Boston, MA 02111
                (617) 526-1801
                Carrier: GREYHOUND LINES, INC.
                Schedule Number: 2589
             */
            if (preg_match('/^(?:Operated by\s*(\D+)\n)?(?<date>\w+\s*\d+\,\s*\d{4})\n(?<time>\d+:\d+(?:\s*[AP]M)?)\n(?<address>.+)\s*Carrier:\s*(.+?)\s+Schedule Number:\s*(?<number>[\w\-]{3,})$/uims', $left, $m)) {
                $s->departure()->date2("{$m['date']} {$m['time']}");
                $s->extra()->number($m['number']);
                $s->departure()->name($this->http->FindSingleNode("./following::tr[1]", $node));
                $s->departure()->address(str_replace("\n", " ", $m['address']));
            }

            $right = trim(join("\n", $this->http->FindNodes("./following::text()[normalize-space()='Going to:'][1]/following::tr[3]/descendant::text()[normalize-space()]", $node)));

            if (preg_match('/^(?:Operated by\s*(\D+)\n)?(?<date>\w+\s*\d+\,\s*\d{4})\n(?<time>\d+:\d+(?:\s*[AP]M)?)\n(?<address>.+)\s*Carrier:\s*(.+?)\s+Schedule Number:\s*(?<number>[\w\-]{3,})$/uims',
                $right, $m)) {
                $s->arrival()->date2("{$m['date']} {$m['time']}");
                $s->extra()
                    ->number($m['number']);
                $s->arrival()->name($this->http->FindSingleNode("./following::text()[normalize-space()='Going to:'][1]/following::tr[1]", $node));
                $s->arrival()->address(str_replace("\n", ' ', $m['address']));
            }
        }
    }

    public function ParseTicket3(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $t = $email->add()->bus();

        $t->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking confirmation number is'))}]", null, false, "/{$this->opt($this->t('Your booking confirmation number is'))}\:?\s([\w\-]{5,})/"),
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking confirmation number is'))}]", null, true, "/({$this->opt($this->t('Your booking confirmation number is'))})/")
        );

        $travellers = $this->http->FindNodes("//text()[(starts-with(normalize-space(.),'Passengers:'))]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()]");

        foreach ($travellers as $traveller) {
            $t->general()->traveller($this->http->FindPreg('/^\s*([[:alpha:]\s]{2,})/', false, $traveller), true);
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total ('))}]", null, false, '/\(([A-Z]{3})\)/');
        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total ('))}]/ancestor::*[1]/following::*[1]");

        if (preg_match("/^\s*(?<currency>.+?)\s*(?<amount>\d[,.'\d]*)/m", $price, $m)) {
            $t->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($currency ?? $this->normalizeCurrency($m['currency']));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('taxes & fees)'))}]");

        if (preg_match("/\(.+?\s*(?<cost>\d[,.'\d]*) plus .+?\s*(?<tax>\d[,.'\d]*) taxes/m", $price, $m)) {
            $t->price()
                ->cost($this->normalizeAmount($m['cost']))
                ->tax($this->normalizeAmount($m['tax']));
        } else {
            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Subtotal:'))}\s*\D([\d\.]+)/u");

            if (!empty($cost)) {
                $t->price()
                    ->cost($cost);
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes and Fees:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Taxes and Fees:'))}\s*\D([\d\.]+)/u");

            if (!empty($tax)) {
                $t->price()
                    ->tax($tax);
            }
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Transfer at:' or normalize-space()='Boarding / Leaving from:' or normalize-space()='Going to:']");

        foreach ($nodes as $root) {
            $typeSegment = $this->http->FindSingleNode(".", $root);

            if ($typeSegment == 'Boarding / Leaving from:') {
                $s = $t->addSegment();
                $left = implode("\n", $this->http->FindNodes("./following::tr[3]/descendant::text()[normalize-space()]", $root));
                /*
                        Aug 01, 2021
                        4:15 PM
                        700 Atlantic Ave
                        Boston, MA 02111
                        (617) 526-1801
                        Carrier: GREYHOUND LINES, INC.
                        Schedule Number: 2589
                     */
                if (preg_match('/^(?:Operated by\s*(\D+)\n)?(?<date>\w+\s*\d+\,\s*\d{4})\n(?<time>\d+:\d+(?:\s*[AP]M)?)\n(?<address>.+)\s*Carrier:\s*(.+?)\s+Schedule Number:\s*(?<number>[\w\-]{3,})$/uims', $left, $m)) {
                    $this->date = strtotime($m['date']);
                    $s->departure()->date2("{$m['date']} {$m['time']}");
                    $s->extra()->number($m['number']);
                    $s->departure()->name($this->http->FindSingleNode("./following::tr[1]", $root));
                    $s->departure()->address(str_replace("\n", " ", $m['address']));
                }
            } elseif ($typeSegment == 'Going to:') {
                $right = trim(join("\n", $this->http->FindNodes("./following::tr[3]/descendant::text()[normalize-space()]", $root)));

                if (preg_match('/^(?:Operated by\s*(\D+)\n)?(?<date>\w+\s*\d+\,\s*\d{4})\n(?<time>\d+:\d+(?:\s*[AP]M)?)\n(?<address>.+)\s*Carrier:\s*(.+?)\s+Schedule Number:\s*(?<number>[\w\-]{3,})$/uims',
                    $right, $m)) {
                    $s->arrival()->date2("{$m['date']} {$m['time']}");
                    $s->extra()
                        ->number($m['number']);
                    $s->arrival()->name($this->http->FindSingleNode("./following::tr[1]", $root));
                    $s->arrival()->address(str_replace("\n", ' ', $m['address']));
                }
            } elseif ($typeSegment == 'Transfer at:') {
                $arrTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()='Arrive']/following::text()[normalize-space()][1]", $root);

                $s->arrival()
                    ->name($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), 'to Schedule')]/preceding::text()[normalize-space()][1]", $root))
                    ->date(strtotime($arrTime, $this->date));

                if ($s->getArrDate() < $s->getDepDate()) {
                    $s->arrival()
                        ->date(strtotime('+1 day', $s->getArrDate()));
                    $this->date = $s->getArrDate();
                }

                $s = $t->addSegment();

                $s->setNumber($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()='to Schedule']/following::text()[normalize-space()][1]", $root));

                $depTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()='Depart']/following::text()[normalize-space()][1]", $root);
                $s->departure()
                    ->date(strtotime($depTime, $this->date))
                    ->name($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), 'to Schedule')]/preceding::text()[normalize-space()][1]", $root));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");

        if ($this->http->XPath->query("//text()[contains(.,'Leaving From:')]/ancestor::tr[1]")->length > 0) {
            $this->ParseTicket($email);
        } elseif ($this->http->XPath->query("//text()[normalize-space()='Transfer at:']")->length > 0) {
            $this->ParseTicket3($email);
        } elseif ($this->http->XPath->query("//text()[contains(.,'Boarding / Leaving from:')]/ancestor::tr[1]/following-sibling::tr[1]")->length > 0) {
            $this->ParseTicket2($email);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    private function normalizeDate($str)
    {
        $in = [
            // 28/08/2020 18:50
            '#^(\d+)/(\d+)/(\d{4}) (\d+:\d+)$#',
        ];
        $out = [
            "$2/$1/$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
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
        $s = preg_replace('/[,.\'](\d{3})/', '$1',
            $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
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
}
