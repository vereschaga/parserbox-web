<?php

namespace AwardWallet\Engine\rcg\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ETicketModify extends \TAccountChecker
{
    public $mailFiles = "rcg/it-22593054.eml, rcg/it-22887640.eml, rcg/it-24529600.eml, rcg/it-31672924.eml, rcg/it-806118350.eml";

    public $reFrom = ["noreply@rcg.se"];
    public $reBody = [
        'sv' => [
            'Tack för din bokning till',
            'har gjort en tidtabellsändring som kräver åtgärd för att resan ska kunna genomföras',
            'har genomfört ändringar i flygtidtabellen för din resa',
        ],
        'no' => [
            'Takk for din bestilling til',
        ],
    ];
    public $reSubject = [
        // sv
        'Tidtabellsändring för din resa till',
        'Elektroniska biljetter från RCG för resan till',
        // no
        'Elektroniske billetter fra RCG for turen til',
    ];
    public $lang = '';
    public static $dict = [
        'sv' => [
            'rlInSubjReg' => '#Tidtabellsändring för din resa till.+?\-\s*([A-Z\d]{5,6})\b#',
            'Datum'       => ['Datum', 'Date'],
            'Till'        => ['Till', 'To'],
        ],
        'no' => [
            // 'Tidtabellsändring' => '',
            // 'rlInSubjReg' => '#Tidtabellsändring för din resa till.+?\-\s*([A-Z\d]{5,6})\b#',
            'Datum'                        => ['Date'],
            'Till'                         => 'To',
            'RCGs bokningsnummer'          => 'Travelis reservasjonsnummer',
            'Flygbolagets Incheckningskod' => 'Flyselskapets innsjekkingskode',
            'Bokningsdatum:'               => 'Booking Dato:',
            // 'E-biljetter:' => '',
            'Hej '     => 'Hei ',
            'Resenär:' => 'traveler:',
        ],
    ];
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->subject = $parser->getSubject();

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'rcg')]/@href | //img[contains(@src,'rcg')] | //text()[contains(., 'online@rcg.se')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $node = $this->http->FindSingleNode("//*[{$this->contains($this->t('RCGs bokningsnummer'))}]/ancestor-or-self::li[1]",
            null, false, "#:\s*(\d{5,})#s");

        if (!empty($node)) {
            $ta = $email->ota();
            $ta
                ->confirmation($node, $this->t('RCGs bokningsnummer'))
                ->code('rcg');
        }

        $r = $email->add()->flight();

        $nodes = implode("\n", explode(',', $this->http->FindSingleNode("//*[{$this->contains($this->t('Flygbolagets Incheckningskod'))}]/ancestor-or-self::li[1]",
            null, false, "#:\s+(.+)#")));

        if (preg_match_all("#^\s*(?<name>.+?)\s*\((?<conf>[A-Z\d]{5,6})\)#m", $nodes, $m, PREG_SET_ORDER)
            || preg_match_all("#^\s*(?<conf>[A-Z\d]{5,6})\s*\((?<name>.+)\)#m", $nodes, $m, PREG_SET_ORDER)
        ) {
            foreach ($m as $v) {
                $rl[trim($v['name'])] = $v['conf'];
            }
        }

        if (!isset($rl) && $this->http->XPath->query("//td[{$this->eq($this->t('Tidtabellsändring'))}]")->length > 0
            && preg_match($this->t('rlInSubjReg'), $this->subject, $m)
        ) {
            $r->general()
                ->confirmation($m[1]);
        } else {
            $r->general()->noConfirmation();
        }

        $node = $this->http->FindSingleNode("//*[{$this->contains($this->t('Bokningsdatum:'))}]/ancestor-or-self::li[1]",
            null, false, "#:\s*(.+)#s");

        if (!empty($node)) {
            $r->general()->date(strtotime($node));
        }

        // travellers
        // ticketNumbers
        $node = $this->http->FindSingleNode("//*[{$this->contains($this->t('E-biljetter:'))}]/ancestor-or-self::li[1]",
            null, false, "#:\s*(.+)#s");

        if (preg_match_all("#(.+?)\s+\((\d[\d\-\/]+)\)#", $node, $m)) {
            $r->general()->travellers($m[1]);
            $r->issued()->tickets($m[2], false);
        } else {
            $travellers = $this->http->FindNodes("//*[{$this->contains($this->t('Resenär:'))}]/ancestor-or-self::li[1]//text()[not({$this->eq(preg_replace('/\s*:\s*$/', '', $this->t('Resenär:')))})and not({$this->eq($this->t('Resenär:'))}) and not(normalize-space()=':')]");

            if (!empty($travellers)) {
                $r->general()
                    ->travellers($travellers, true);
            }
        }

        if (count($r->getTravellers()) === 0) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hej '))}]", null, true,
                "/^{$this->opt($this->t('Hej '))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[,!]|$)/mu");
            $r->general()->traveller($traveller);
        }

        $xpath = "//text()[{$this->eq($this->t('Datum'))}]/ancestor::tr[1][{$this->contains($this->t('Till'))}]/following-sibling::tr[count(./*[normalize-space()!=''])>3]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $key => $root) {
            $parted = $key !== 0 && $this->http->XPath->query('./*', $root)->length === 4; // it-31672924.eml

            if ($parted) {
                $prevSegment = $r->getSegments()[$key - 1];
            }

            $s = $r->addSegment();

            if (!$parted) {
                $date = strtotime($this->http->FindSingleNode("./td[1]", $root));
            }

            if ($parted) {
                $s->airline()
                    ->name($prevSegment->getAirlineName())
                    ->number($prevSegment->getFlightNumber());
            } elseif (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#", $this->http->FindSingleNode('./*[2]', $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if ($parted) {
                $s->airline()->operator($prevSegment->getOperatedBy(), false, true);
            } else {
                $operator = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)!=''][2]", $root, false, "#{$this->opt($this->t('flygs av'))}\s+(.+)#");
                $s->airline()->operator($operator, false, true);
            }

            $airline = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)!=''][1]", $root);

            if (isset($rl) && isset($rl[$airline])) {
                $s->airline()->confirmation($rl[$airline]);
            }

            $posDep = $parted ? '1' : '3';

            $depName = $this->http->FindSingleNode("./*[$posDep]/descendant::text()[normalize-space()!=''][2]", $root);

            if (empty($depName)) {
                $depName = $this->http->FindSingleNode("./*[$posDep]/descendant::text()[normalize-space()!=''][1]", $root);
            }
            $terminalDep = $this->http->FindSingleNode("./*[$posDep]/descendant::text()[normalize-space()!=''][3]", $root, true, "/.*Terminal.*/i");
            $s->departure()
                ->noCode()
                ->name($depName)
                ->date(strtotime($this->http->FindSingleNode('./*[' . ($parted ? '3' : '6') . ']/descendant::text()[normalize-space()][1]', $root), $date))
                ->terminal($terminalDep ? preg_replace("/^Terminal\s*(.+)/i", '$1', $terminalDep) : null,
                    false, true);

            $posArr = $parted ? '2' : '4';

            $arrName = $this->http->FindSingleNode("./*[$posArr]/descendant::text()[normalize-space()!=''][2]", $root);

            if (empty($arrName)) {
                $arrName = $this->http->FindSingleNode("./*[$posArr]/descendant::text()[normalize-space()!=''][1]", $root);
            }
            $terminalArr = $this->http->FindSingleNode("./*[$posArr]/descendant::text()[normalize-space()!=''][3]", $root, true, "/.*Terminal.*/i");
            $s->arrival()
                ->noCode()
                ->name($arrName)
                ->date(strtotime($this->http->FindSingleNode('./*[' . ($parted ? '4' : '7') . ']/descendant::text()[normalize-space()][1]', $root), $date))
                ->terminal($terminalArr ? preg_replace("/^Terminal\s*(.+)/i", '$1', $terminalArr) : null,
                    false, true);

            if ($this->http->XPath->query("./preceding-sibling::tr[last()]/*[self::td or self::th][position()>4 and position()=last()][{$this->starts($this->t('Status'))}]",
                    $root)->length > 0
            ) {
                $s->extra()->status($parted ? $prevSegment->getStatus() : $this->http->FindSingleNode("./*[position()>4 and position()=last()]", $root));
            }

            $s->extra()
                ->aircraft($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space()][2]", $root), true, true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                $re = (array) $reBody;

                foreach ($re as $r) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$r}')]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
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
