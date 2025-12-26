<?php

namespace AwardWallet\Engine\sj\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "sj/it-222687296.eml, sj/it-36961591.eml, sj/it-465276088.eml, sj/it-469300880.eml, sj/it-76891290.eml, sj/it-76932673.eml";

    public $reFrom = ["no-reply@info.sj.se"];
    public $reBody = [
        'en'   => ['Your reciept', 'Your trip'],
        'en2'  => ['Your receipt', 'Your trip'],
        'en3'  => ['Your receipt', 'Cancelled trip'],
        'sv'   => ['Ditt kvitto', 'Din resa'],
        'sv2'  => ['Ditt kvitto', 'Din resa är avbokad'],
    ];
    public $reSubject = [
        '#Booking number: [A-Z\d]{5,}$#',
        '#Bokningsnummer: [A-Z\d]{5,}$#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Travel time'    => 'Travel time',
            'Booking number' => 'Booking number',
            'Your trip'      => ['Your trip', 'Cancelled trip'],
            'Your reciept'   => ['Your reciept', 'Your receipt'],
            'point'          => 'point', // to check(no examples)
            //            'Refundable' => '',
            'Plats'                  => ['Seats', 'seat', 'Plats', 'plat'],
            'Your trip is cancelled' => 'Your trip is cancelled',
            'Your trip is'           => 'Your trip is',
            'Your booking has been'  => 'Your booking has been',
        ],
        'sv' => [// bcd
            'Travel time'                  => 'Restid',
            'Booking number'               => 'Bokningsnummer',
            'Your trip'                    => ['Din resa', 'Avbokad resa'],
            'Corpororate identity number:' => 'Orgnr:',
            'Your reciept'                 => 'Ditt kvitto',
            'Refundable'                   => 'Återbetalas',
            'point'                        => 'poäng',
            'Completion'                   => 'Komplettering',
            'Date:'                        => 'Datum:',
            'Amount due'                   => 'Att betala',
            'incl. booking fee'            => 'varav bokningsavg.',
            'Your trip is cancelled'       => 'Din resa är avbokad',
            'Your trip is'                 => 'Din resa är',
            'Trip'                         => 'Din resa',
            'Train'                        => ['Tåg', 'Tunnelbana', 'Stadstrafik'],
            'Plats'                        => ['Plats', 'plats', 'Kupé', 'kupé', 'Sittplats'],

            'Bus'    => ['Buss'],
            'Refund' => 'Återköp',
            //'Your booking has been' => '',
        ],
    ];
    public $travellers;
    private $keywordProv = 'SJ';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.sj.se')] | //a[contains(@href,'.sj.se')] | //text()[contains(.,'.sj.se')]")->length > 0
            && $this->detectBody($this->http->Response['body'])
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function ParserBus(Email $email, \DOMNode $root, $flagSearchClass, $i)
    {
        $b = $email->add()->bus();

        $accounts = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Corpororate identity number:'))}]",
            null, "#:\s+(.+)#")));

        if (empty($accounts)) {
            $accounts = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Corpororate identity number:'))}]/following::text()[normalize-space()][1]",
                null, "#^\s*(\w+)\s*$#"));
        }

        $b->program()
            ->accounts($accounts, false);

        $b->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/following::text()[normalize-space()!=''][1]"),
                $this->t('Booking number'))
            ->travellers($this->travellers, true);

        $dateText = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Your reciept'))}]/following::text()[normalize-space()!=''][position() < 5]"));

        if (preg_match("#{$this->opt($this->t('Date:'))}\s+(.+?\d+:\d+(?:[ap]m\b)?)#i", $dateText, $m)) {
            $date = $this->normalizeDate($m[1]);

            if (!empty($date)) {
                $b->general()
                    ->date($date);
            }
        }

        $s = $b->addSegment();

        $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[starts-with(translate(normalize-space(), '0123456789', '##########'), '##:##')][1]/preceding-sibling::tr[2]", $root));
        $time = $this->http->FindSingleNode("./preceding::*[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
            $root);

        if (!empty($date) && !empty($time)) {
            $s->departure()->date(strtotime($time, $date));
        }

        $time = $this->http->FindSingleNode("./following::*[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
            $root);

        if (!empty($date) && !empty($time)) {
            $s->arrival()->date(strtotime($time, $date));
        }

        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);
        $route = explode(' - ', $node);

        if (count($route) !== 2) {
            $this->logger->debug("incorrect format route");

            return false;
        }
        $s->departure()->name($route[0]);
        $s->arrival()->name($route[1]);

        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][3]", $root);

        if (preg_match("#,\s+(.+),\s+{$this->opt($this->t('Bus'))}\s+(.+)#", $node, $m)) {
            $s->extra()
                ->type($m[1])
                ->number($m[2]);
        }
        $s->extra()
            ->duration($this->http->FindSingleNode(
                "./descendant::text()[{$this->starts($this->t('Travel time'))}]",
                $root,
                false,
                "#{$this->opt($this->t('Travel time'))}\s+(.+)#"
            ));

        return true;
    }

    public function ParserTrain(Email $email, \DOMNode $root, $flagSearchClass, $i)
    {
        $r = $email->add()->train();

        $accounts = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Corpororate identity number:'))}]",
            null, "#:\s+(.+)#")));

        if (empty($accounts)) {
            $accounts = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Corpororate identity number:'))}]/following::text()[normalize-space()][1]",
                null, "#^\s*(\w+)\s*$#"));
        }

        $r->program()
            ->accounts($accounts, false);

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/following::text()[normalize-space()!=''][1]"),
                $this->t('Booking number'))
            ->travellers($this->travellers, true);

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking has been'))}]", null, true, "/{$this->opt($this->t('Your booking has been'))}\s*(.+)/");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        $dateText = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Your reciept'))}]/following::text()[normalize-space()!=''][position() < 5]"));

        if (preg_match("#{$this->opt($this->t('Date:'))}\s+(.+?\d+:\d+(?:[ap]m\b)?)#i", $dateText, $m)) {
            $date = $this->normalizeDate($m[1]);

            if (!empty($date)) {
                $r->general()
                    ->date($date);
            }
        }

        $s = $r->addSegment();

        $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[starts-with(translate(normalize-space(), '0123456789', '##########'), '##:##')][1]/preceding-sibling::tr[2]", $root));
        $time = $this->http->FindSingleNode("./preceding::*[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
            $root);

        if (!empty($date) && !empty($time)) {
            $s->departure()->date(strtotime($time, $date));
        }

        $time = $this->http->FindSingleNode("./following::*[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
            $root);

        if (!empty($date) && !empty($time)) {
            $s->arrival()->date(strtotime($time, $date));
        }

        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);
        $route = explode(' - ', $node);

        if (count($route) !== 2) {
            $this->logger->debug("incorrect format route");

            return false;
        }
        $s->departure()->name('Sweden, ' . $route[0]);
        $s->arrival()->name('Sweden, ' . $route[1]);

        $s->extra()->service($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root));

        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][3]", $root);

        if (preg_match("#,\s+(.+),\s+{$this->opt($this->t('Train'))}\s+(.+)#", $node, $m)) {
            $s->extra()
                ->type($m[1]);

            if (empty($m[2])) {
                $s->extra()
                    ->noNumber();
            } else {
                $s->extra()
                    ->number($m[2]);
            }
        }
        $s->extra()
            ->duration($this->http->FindSingleNode(
                "./descendant::text()[{$this->starts($this->t('Travel time'))}]",
                $root,
                false,
                "#{$this->opt($this->t('Travel time'))}\s+(.+)#"
            ));

        $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Plats'))}]", $root);

        if (preg_match("#{$this->opt($this->t('Plats'))} ([\d\s,]+?) (?:i vagn|in carriage) (.+)#i", $node, $m)) {
            $s->extra()
                ->car($m[2])
                ->seats(array_map("trim", explode(",", $m[1])));

            if ($flagSearchClass) {
                $pos1 = $i * count($this->travellers) + 1;
                $pos2 = ($i + 1) * count($this->travellers);
                $class = implode("|",
                    array_filter(array_unique($this->http->FindNodes("(//text()[{$this->eq($this->t('Trip'))}])[position()<={$pos2} and position()>={$pos1}]/ancestor::tr[1]/following-sibling::tr[1][{$this->contains($this->t('seat'))}]",
                        null, "#(.+)\s+{$this->opt($this->t('seat'))}#"))));

                if (!empty($class)) {
                    $s->extra()->cabin($class);
                }
            }
        }

        return true;
    }

    private function parseCancelledEmail(Email $email)
    {
        $this->logger->debug(__METHOD__);

        $t = $email->add()->train();
        $t->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/following::text()[normalize-space()!=''][1]"),
                    $this->t('Booking number'))
                ->cancelled()
                ->travellers($this->travellers)
                ->status($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your trip is'))}]", null, true, "/{$this->opt($this->t('Your trip is'))}\s*(.+)/"));
    }

    private function parseEmail(Email $email)
    {
        $totalsText = $this->http->FindNodes("//text()[{$this->starts($this->t('Amount due'))}]/ancestor::tr[1]");
        $validTotal = true;

        foreach ($totalsText as $totalText) {
            if (preg_match("/\s+(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\.\, ]*)$/", $totalText, $m)) {
                $currency[] = $m['currency'];
                $t = $this->amount($m['amount']);

                if (is_null($t)) {
                    $validTotal = false;

                    break;
                }
                $totals[] = $t;
            } elseif (preg_match("/\s+{$this->opt($this->t('point'))}\s*(?<amount>\d[\d ]*)$/", $totalText, $m)) {
                $totalsPoints[] = str_replace(' ', '', $m['amount']);
            } else {
                $validTotal = false;
            }
        }

        /*$totalsRefundText = $this->http->FindNodes("//text()[{$this->eq($this->t('Refundable'))}]/ancestor::tr[1]");
        foreach ($totalsRefundText as $totalRefundText) {
            if (preg_match("/{$this->opt($this->t('Refundable'))}\s*(?<amount>\d[\d\.\, ]*)$/u", $totalRefundText, $m)) {
                $t = $this->amount($m['amount']);

                if (is_null($t)) {
                    $validTotal = false;

                    break;
                }
                $totalsRefund[] = $t;
            } else {
                $validTotal = false;
            }
        }*/

        if ($validTotal && !empty($totals) && count(array_unique($currency)) == 1) {
            /*if (empty($totalsRefund)) {
                $fee = $this->getTotalCurrency(strtoupper($this->http->FindSingleNode("//text()[{$this->starts($this->t('incl. booking fee'))}]/ancestor::tr[1]",
                    null, false, "#{$this->opt($this->t('incl. booking fee'))}\s+(.+)#")));

                if (!empty($fee['Total'])) {
                    $email->price()
                        ->fee($this->t('incl. booking fee'), $fee['Total']);
                }
            }

            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your trip is cancelled'))}]")->length === 0) {
                $email->price()
                    ->total(array_sum($totals) - (!empty($totalsRefund) ? array_sum($totalsRefund) : 0))
                    ->currency($currency[0]);

                if (!empty($totalsPoints)) {
                    $email->price()
                        ->spentAwards(array_sum($totalsPoints));
                }
            } else {
                $email->price()
                    ->total(array_sum($totals))
                    ->currency($currency[0])
                ;
            }*/

            /*$fee = $this->getTotalCurrency(strtoupper($this->http->FindSingleNode("//text()[{$this->starts($this->t('incl. booking fee'))}]/ancestor::tr[1]",
                null, false, "#{$this->opt($this->t('incl. booking fee'))}\s+(.+)#")));*/

            $fee = $this->getTotalCurrency(strtoupper($this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount due'))}]/ancestor::tr[1]/following::text()[normalize-space()][1][{$this->starts($this->t('incl. booking fee'))}]",
                null, false, "#{$this->opt($this->t('incl. booking fee'))}\s+(.+)#")));

            if (!empty($fee['Total'])) {
                $email->price()
                    ->fee($this->t('incl. booking fee'), $fee['Total']);
            }

            $email->price()
                ->total(array_sum($totals))
                ->currency($currency[0]);
        } elseif ($validTotal && !empty($totalsPoints)) {
            $email->price()
                ->spentAwards(array_sum($totalsPoints));
        }

        $this->travellers = array_filter(
            $this->http->FindNodes(
                "//text()[{$this->eq($this->t('Your trip'))}]/following::text()[normalize-space()!=''][1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::text()[normalize-space()!='']",
                null,
                "#(.+?)(?:,|$)#"
            )
        );

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your trip is cancelled'))}]")->length === 0) {
            $xpath = "//text()[{$this->starts($this->t('Travel time'))}]/ancestor::table[1]";
            /*$this->logger->debug($xpath);
            $this->logger->debug("./descendant::text()[{$this->contains($this->t('Train'))}]");
            $this->logger->debug("./descendant::text()[{$this->contains($this->t('Bus'))}]");*/
            $nodes = $this->http->XPath->query($xpath);

            $trips = $this->http->FindNodes("(//text()[{$this->eq($this->t('Trip'))}])/ancestor::tr[1]");
            $flagSearchClass = (count($trips) === $nodes->length * count($this->travellers));

            foreach ($nodes as $i => $root) {
                if ($this->http->XPath->query("./descendant::text()[{$this->contains($this->t('Bus'))}]", $root)->length > 0) {
                    $this->ParserBus($email, $root, $flagSearchClass, $i);
                } elseif ($this->http->XPath->query("./descendant::text()[{$this->contains($this->t('Train'))}]", $root)->length > 0
                || $this->http->XPath->query("./ancestor::table[1]/descendant::img[contains(@src, 'train')]", $root)->length > 0) {
                    $this->ParserTrain($email, $root, $flagSearchClass, $i);
                }
            }
        } else {
            $this->parseCancelledEmail($email);
        }

        return true;
    }

    private function normalizeDate($str)
    {
        $in = [
            //Sunday 2 Jun 2019 kl 19:07
            '#^\s*(\w+)\s+(\d+)\s+(\w+)\s+(\d{4})\s+kl\s+(\d+:\d+)\s*$#u',
            //2019-05-02, 19:34
            '#^\s*(\d{4}\-\d+\-\d+),\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$2 $3 $4, $5',
            '$1, $2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Travel time'], $words['Booking number'])) {
                if (($this->http->XPath->query("//*[{$this->contains($words['Travel time'])}]")->length > 0
                    || $this->http->XPath->query("//*[{$this->contains($words['Your trip is cancelled'])}]")->length > 0)
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking number'])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function amount($node)
    {
        return PriceHelper::cost($node);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
