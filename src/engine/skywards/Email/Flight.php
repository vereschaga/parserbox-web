<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "skywards/it-18308847.eml, skywards/it-229850573.eml, skywards/it-70263429.eml, skywards/it-71112893.eml, skywards/it-637194458-short.eml";

    private $subjects = [
        'en' => ['Your boarding pass is ready', 'Check in online for your flight to'],
        'it' => ['La carta di imbarco è pronta'],
    ];

    private $langDetectors = [
        'en' => ["re looking forward to welcoming you on board"],
        'it' => ["il check-in è stato effettuato"],
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
            //            'Your booking reference:' => '',
            //            'Hello' => '',
            //            'Departure' => '',
            //            'Arrival' => '',
            'Passengers' => ['Passengers', 'Passenger List'],
            // 'with infant' => '',
            //            'Membership no.' => '',
            //            'Seat no.' => '',
        ],
        'it' => [
            'Your booking reference:' => 'Codice di prenotazione:',
            'Hello'                   => 'Salve',
            'Departure'               => 'Partenza',
            'Arrival'                 => 'Arrivo',
            'Passengers'              => 'Passeggeri',
            // 'with infant' => '',
            'Membership no.'          => 'N. socio',
            'Seat no.'                => 'N. posto',
        ],
    ];

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();
        $this->parseEmail($email);
        $this->parseStatement($email);

        $ns = explode('\\', __CLASS__);
        $class = end($ns);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"The Emirates team") or contains(normalize-space(.),"The Emirates Group. All Rights Reserved") or contains(.,"@emirates.email")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emirates\.(?:com|email)/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): void
    {
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';

        $patterns = [
            'date'          => '\b\d{1,2}\s+[[:alpha:]]+\s+(?:\d{2}|\d{4})\b', // Sun 3 Sep 23
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference:'))}]/following-sibling::text()[normalize-space(.)][1]"));

        if ($accs = $this->http->FindNodes("//tr[{$this->contains($this->t('Membership no.'))} and not(.//tr)]/following-sibling::tr[normalize-space(.)][1]", null, '/([A-Z\d]+)/')) {
            foreach ($accs as $acc) {
                $f->addAccountNumber($acc, false);
            }
        } elseif ($acc = $this->http->FindSingleNode("//img[contains(@src, 'Account-Icon')]/ancestor::td[1]/preceding-sibling::td[1]/text()[normalize-space(.)][last()]")) {
            $f->addAccountNumber($acc, false);
        }

        $travellers = $this->http->FindNodes("//tr[{$this->eq($this->t('Passengers'))}]/following::*[ count(table[normalize-space()])=2 and table[normalize-space()][2]/descendant::*[not(.//tr) and ../self::tr][descendant::img or normalize-space()][1][descendant::img and normalize-space()=''] ]/table[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u");
        $areNamesFull = true;

        if (in_array(null, $travellers, true)) {
            $travellers = [];
            $areNamesFull = null;
        }

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//img[contains(@src,'images/FO-Blue-80x126') or contains(@src,'images/male') or contains(@src,'images/generic') or contains(@src,'images/girl_generic') or contains(@src,'images/female_generic')]/ancestor::table[2]/preceding-sibling::table[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/i");
            $areNamesFull = true;
        }

        if (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}] | //text()[{$this->eq($this->t('Hello'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        if (preg_match_all("/{$this->opt($this->t('with infant'))}\s+(.+)/", implode("\n", $travellers), $m)) {
            $f->general()->infants($m[1], $areNamesFull);
        }
        $travellers = preg_replace('/^\s*(?:Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master)\s+/', '', $travellers);
        $f->general()->travellers(preg_replace("/\s*{$this->opt($this->t('with infant'))}\s+.+$/", '', $travellers), $areNamesFull);

        $xpath = "//table[{$this->contains($this->t('Departure'))} and {$this->contains($this->t('Arrival'))} and descendant::table[1][not(.//table)]]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");
        }

        $seats = [];
        $seatsRoots = $this->http->XPath->query("//tr[{$this->starts($this->t('Seat no.'))} and not(.//tr)]/following-sibling::tr[normalize-space(.)!='']");

        foreach ($seatsRoots as $sroot) {
            $pseats = $this->http->FindNodes("./descendant::text()[normalize-space()]", $sroot);

            if (count($pseats) === $roots->length) {
                foreach ($pseats as $i => $s) {
                    $seats[$i][] = $s;
                }
            } else {
                $seats = [];

                break;
            }
        }

        foreach ($roots as $segKey => $root) {
            $s = $f->addSegment();

            $nodes = $this->http->XPath->query('descendant::tr[1]/td[position() = 1 or position() = 3]', $root);

            foreach ($nodes as $node) {
                $segInfo = implode("\n", $this->http->FindNodes("descendant::tr[normalize-space(.)]", $node));
                $re = "/({$this->opt($this->t('Departure'))}|{$this->opt($this->t('Arrival'))})\s+\w+,\s+(\d{1,2} \w+ \d{2,4})\s+(\d{1,2}:\d{2})\s+(.+)\s+\(([A-Z]{3})\)/iu";

                if (preg_match($re, $segInfo, $m)) {
//                    if ('Departure' === $m[1]) {
                    if (in_array($m[1], (array) $this->t('Departure'))) {
                        $s->departure()
                            ->date(strtotime($m[2] . ', ' . $m[3]))
                            ->name($m[4])
                            ->code($m[5]);
//                    } elseif ('Arrival' === $m[1]) {
                    } elseif (in_array($m[1], (array) $this->t('Arrival'))) {
                        $s->arrival()
                            ->date(strtotime($m[2] . ', ' . $m[3]))
                            ->name($m[4])
                            ->code($m[5]);
                    }
                }
            }

            if (isset($seats[$segKey])) {
                $s->extra()
                    ->seats(array_filter($seats[$segKey]));
            }

            if (preg_match('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*[-–—]+\s*(\d+)/', $this->http->FindSingleNode('descendant::tr[1]/*[2]/descendant::tr[2]', $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $val = $this->http->FindSingleNode('descendant::tr[1]/td[2]/descendant::tr[last()]', $root);
            // 14 hr 45 min / Non-stop
            // 03 hr 10 min / Boeing 777-300ER
            if (preg_match('#^(.+?)/\s*(.+)#', $val, $m)) {
                $s->extra()->duration($m[1]);

                if (is_numeric($m[2])) {
                    $s->extra()->stops($m[2]);
                } elseif (strtolower($m[2]) == 'non-stop') {
                    $s->extra()->stops(0);
                } elseif (preg_match('/^(.{10,30})$/', $m[2], $m)) {
                    $s->extra()->aircraft($m[1]);
                }
            }
            $val = $this->http->FindSingleNode('(ancestor::tr[1]/following-sibling::tr[normalize-space()][1]//text()[normalize-space()]/ancestor::td[1])[2]',
                $root);

            if (is_numeric($val)) {
                $s->extra()->stops($val);
            } elseif (strtolower($val) == 'non-stop') {
                $s->extra()->stops(0);
            } elseif (preg_match('/^(.{10,30})$/', $val, $m)) {
                $s->extra()->aircraft($m[0]);
            }

            $cabin = $this->http->FindSingleNode('(ancestor::tr[1]/following-sibling::tr[normalize-space()][1]//text()[normalize-space()]/ancestor::td[1])[1]',
                $root, false, '/^(.{5,})$/');
            $cabin = preg_replace("/^\s*(.+?)\s*\/.*/", '$1', $cabin);

            if ($cabin) {
                $s->extra()->cabin($cabin);
            }
        }

        if (count($f->getSegments()) > 0) {
            return;
        }

        /*
            it-637194458-short.eml (boarding pass)
        */

        $segments = $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathAirportCode}] and *[2][normalize-space()='']/descendant::img and *[3][{$xpathAirportCode}] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $xpathPreContainer = "ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()]/descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->starts($this->t('Departure date'))}] ][1]";

            $flight = $this->http->FindSingleNode($xpathPreContainer . "/*[normalize-space()][1]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $dateDep = strtotime($this->http->FindSingleNode($xpathPreContainer . "/*[normalize-space()][2]", $root, true, "/^{$this->opt($this->t('Departure date'))}\D*?({$patterns['date']})$/u"));

            if ($dateDep) {
                $s->departure()->day($dateDep)->noDate();
                $s->arrival()->noDate();
            }

            $codeDep = $this->http->FindSingleNode('*[1]', $root);
            $codeArr = $this->http->FindSingleNode('*[3]', $root);

            $s->departure()->code($codeDep);
            $s->arrival()->code($codeArr);
        }
    }

    private function parseStatement(Email $email): void
    {
        $blockXpath = "(//img[@title='Emirates']/ancestor::a[@alias='Emirates Header Logo' and contains(@href, 'emirates.com')])[1]/ancestor::*[not(normalize-space())]/following-sibling::*[normalize-space()][1]";

        if (!empty($this->http->FindSingleNode($blockXpath))) {
            $info = $this->http->FindNodes($blockXpath . "/descendant::text()[normalize-space()]");

            if (count($info) === 2 && preg_match("/^\s*[[:alpha:]][[:alpha:] \-]+\s*$/", $info[0])
            && preg_match("/^\s*[A-Z]{0,2}0*(\d{5,})\s*$/", $info[1], $m1)) {
                $st = $email->add()->statement();

                $st->setNumber($m1[1]);
                $st->setLogin($m1[1]);

                $st->setNoBalance(true);

                $st->addProperty('Name', $info[0]);
            }
        }
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
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

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
