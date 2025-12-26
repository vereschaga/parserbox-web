<?php

namespace AwardWallet\Engine\omega\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "omega/it-20456188.eml, omega/it-20870661.eml, omega/it-24463760.eml";

    public $reFrom = ["omegaflightstore.com"];
    public $reBody = [
        'en'  => ['Your Flight Details', 'Manage Your Booking'],
        'en2' => ['Your booking reference with us is', 'Passenger'],
    ];
    public $reSubject = [
        'Your OmegaFlightstore.com Booking Confirmation',
    ];
    public $lang = '';
    public $pdfNamePattern = "Itinerary.*pdf";
    public static $dict = [
        'sv' => [
            'rlInSubjReg' => '#Tidtabellsändring för din resa till.+?\-\s*([A-Z\d]{5,6})\b#',
            'Datum'       => ['Datum', 'Date'],
            'Till'        => ['Till', 'To'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language by body');
        }
        $type = 'Html';

        if (!$this->parseEmail($email)) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            if (isset($pdfs) && count($pdfs) > 0) {
                foreach ($pdfs as $pdf) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                        if ($this->assignLang($text)) {
                            $type = 'Pdf';
                            $this->parseEmailPdf($text, $email);
                        }
                    } else {
                        return null;
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'omegaflightstore.com')] | //img[contains(@src,'omegaflightstore.com')]")->length > 0) {
            if ($this->assignLang()) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Take off from') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $refTA = $this->re("#{$this->opt($this->t('Your booking reference with us is'))} +([\w\-]+)\n#", $textPDF);

        if (empty($refTA)) {
            $refTA = $this->re("#{$this->opt($this->t('Booking Reference'))}: *([\w\-]+)\n#", $textPDF);
        }

        if (empty($refTA)) {
            $this->logger->debug('other format pdf (booking ref)');

            return false;
        }

        $rls = [];

        if (preg_match_all("#{$this->opt($this->t('Airline Ref'))}: +(.+)#", $textPDF, $m)) {
            $nodes = array_unique($m[1]);

            foreach ($nodes as $node) {
                $arr = array_filter(array_map("trim", explode(',', $node)),
                    function ($s) {
                        return preg_match("#^[A-Z\d]{5,}$#", $s);
                    });
                $rls = array_unique(array_merge($rls, $arr));
            }
        }

        if (count($rls) === 0) {
            $this->logger->debug('other format pdf (airline refs)');

            return false;
        }

        if (preg_match_all("#{$this->opt($this->t('PNR Ref:'))} +([A-Z\d]+)#", $textPDF, $m)) {
            $nodes = array_unique($m[1]);

            if (count($nodes) !== 1) {
                $this->logger->debug('other format pdf (pnr)');

                return false;
            } else {
                $pnr = array_shift($nodes);
            }
        }

        //main parsing
        $email->ota()->confirmation($refTA, $this->t('Booking Reference'));

        $r = $email->add()->flight();
        //TODO: need more examples for the accurate allocation of recording locators. for now get the first one
        $r->general()
            ->confirmation($rls[0], $this->t('Airline Ref'));

        if (isset($pnr)) {
            $email->ota()
                ->confirmation($pnr, trim($this->t('PNR Ref:'), " :"), true);
        }

        $pax = array_map("trim",
            explode(',', $this->re("#{$this->opt($this->t('Passenger(s)'))}: +(.+)#", $textPDF)));
        $r->general()->travellers($pax);

        if (preg_match_all("#\s*\-\s*(\d{10,})#", $textPDF, $m)) {
            $tickets = array_unique($m[1]);
            $r->issued()->tickets($tickets, false);
        }

        $segments = $this->splitter("#\n(.*\n[^\n]+{$this->opt($this->t('Take off from'))})#m", "ControlStr\n" . $textPDF);

        $rlAirline = [];
        $numRl = 0;
        $date = null;

        foreach ($segments as $root) {
            $s = $r->addSegment();

            $dateStr = trim($this->re("#^(.*)\s+\d+:\d+\s+{$this->opt($this->t('Take off from'))}#s", $root));

            if (!empty($dateStr)) {
                $date = strtotime($dateStr);
            }

            $regDep = "#(?<time>\d+:\d+)\s+{$this->opt($this->t('Take off from'))} +(?<depName>.+)\s+" .
                "[\+\w]+\s+(?:(?s).+?) +(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+)#";

            if (preg_match($regDep, $root, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->noCode()
                    ->date(strtotime($m['time'], $date));
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);

                if (!isset($rlAirline[$m['airline']])) {
                    if (isset($rls[$numRl])) {
                        $rlAirline[$m['airline']] = $rls[$numRl];
                        $s->airline()->confirmation($rls[$numRl]);
                        $numRl++;
                    } else {
                        $this->logger->debug('wrong guessing about rls');

                        return false;
                    }
                } else {
                    $s->airline()->confirmation($rlAirline[$m['airline']]);
                }

                if (preg_match("#{$this->opt($this->t('Airline Ref'))}: +\b([A-Z\d]+)\b *\n#", $root,
                        $v) && $v[1] !== $rlAirline[$m['airline']]
                ) {
                    $this->logger->debug('wrong guessing about rls (' . $v[1] . '!==' . $rlAirline[$m['airline']] . ')');

                    return false;
                }
            }
            $regArr = "#\n(?<arrDate>.*)\n *(?<time>\d+:\d+)\s+{$this->opt($this->t('Land at'))} (?<arrName>.+)#";

            if (preg_match($regArr, $root, $m)) {
                $dateStr = trim($m['arrDate']);

                if (!empty($dateStr)) {
                    $date = strtotime($dateStr);
                }
                $s->arrival()
                    ->name($m['arrName'])
                    ->noCode()
                    ->date(strtotime($m['time'], $date));
            }

            if (preg_match("#{$this->opt($this->t('Departing terminal'))}\s+(?<term>.+)#i", $root, $m)) {
                $s->departure()->terminal($m['term']);
            }

            if (preg_match("#{$this->opt($this->t('Arriving terminal'))}\s+(?<term>.+)#i", $root, $m)) {
                $s->arrival()->terminal($m['term']);
            }

            if (preg_match("#{$this->opt($this->t('Class'))}:\s+(?<cabin>.+?)(?:\s{3,}|\n)#i", $root, $m)) {
                $s->extra()->cabin($m['cabin']);
            }
        }

        return true;
    }

    private function parseEmail(Email $email)
    {
        //first of all, we will check the format in more detail
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[normalize-space()][1][{$ruleTime}]/ancestor::tr[1][count(./descendant::td[normalize-space()!=''])=3]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('other format body (segments)');

            return false;
        }

        $refTA = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference with us is'))}]/following::text()[normalize-space()!=''][1]",
            null, false, "#^([\w\-]+)$#");

        if (empty($refTA)) {
            $refTA = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]", null,
                false, "#: *([\w\-]+)$#");
        }

        if (empty($refTA)) {
            $this->logger->debug('other format body (booking ref)');

            return false;
        }

        $rls = array_filter(array_map("trim", explode(',',
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Airline Booking Reference'))}]/following::text()[normalize-space()!=''][1]"))),
            function ($s) {
                return preg_match("#^[A-Z\d]{5,}$#", $s);
            });

        if (count($rls) === 0) {
            $this->logger->debug('other format body (airline refs)');

            return false;
        }

        //main parsing
        $email->ota()->confirmation($refTA, $this->t('Booking Reference'));

        $r = $email->add()->flight();
        //TODO: need more examples for the accurate allocation of recording locators. for now get the first one
        $r->general()
            ->confirmation($rls[0], $this->t('Airline Ref'));

        $pnr = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Main Booking Reference:'))}]/following::text()[normalize-space()!=''][1]",
            null, false, "#^([\w\-]+)$#");

        if (isset($pnr)) {
            $email->ota()
                ->confirmation($pnr, trim($this->t('Main Booking Reference:'), " :"), true);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Details'))}]/following::table[1]/descendant::tr[1]/*[self::th or self::td][2][{$this->eq($this->t('Name'))}]")->length > 0
        ) {
            $r->general()->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Details'))}]/following::table[1]/descendant::tr[position()>1]/*[self::th or self::td][2]"));
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost is'))}]/following::text()[normalize-space()!=''][1]"));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $tickets = array_filter(array_map("trim", explode(',',
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Number'))}]/following::text()[normalize-space()!=''][1]"))),
            function ($s) {
                return preg_match("#^[\d\-\/]{5,}$#", $s);
            });

        if (!empty($tickets)) {
            $r->issued()->tickets($tickets, false);
        }
        $rlAirline = [];
        $numRl = 0;
        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][last()]",
                $root);

            if (preg_match("#^(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);

                if (!isset($rlAirline[$m['airline']])) {
                    if (isset($rls[$numRl])) {
                        $rlAirline[$m['airline']] = $rls[$numRl];
                        $s->airline()->confirmation($rls[$numRl]);
                        $numRl++;
                    } else {
                        $this->logger->debug('wrong guessing about rls');

                        return false;
                    }
                } else {
                    $s->airline()->confirmation($rlAirline[$m['airline']]);
                }
            }
            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#^(.+)\s*\(([A-Z]{3})\)$#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][last()]",
                        $root)));
            }
            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][2][{$this->contains($this->t('Terminal'))}]",
                $root, false, "#^{$this->opt($this->t('Terminal'))}\s*(.+)#");

            if (!empty($node)) {
                $s->departure()->terminal($node);
            }

            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#^(.+)\s*\(([A-Z]{3})\)$#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()!=''][last()]",
                        $root)));
            }
            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()!=''][2][{$this->contains($this->t('Terminal'))}]",
                $root, false, "#^{$this->opt($this->t('Terminal'))}\s*(.+)#");

            if (!empty($node)) {
                $s->arrival()->terminal($node);
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::td[{$this->starts($this->t('Class'))}]",
                $root, false, "#{$this->opt($this->t('Class'))}[ :]+(.+)#");

            if (!empty($node)) {
                $s->extra()->cabin($node);
            }
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

    private function assignLang($body = null)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (isset($body) && is_string($body)) {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                } else {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                        && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                    ) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            }
        }

        return false;
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
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $tot = PriceHelper::cost($m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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
