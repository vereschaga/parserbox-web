<?php

namespace AwardWallet\Engine\virtuoso\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class SabreBooking extends \TAccountChecker
{
    public $mailFiles = "virtuoso/it-15772655.eml, virtuoso/it-15775993.eml, virtuoso/it-38895158.eml";

    public $reFrom = ["@eglobalfares.com", "eglobal@virtuoso.com"];
    public $reBody = [
        'en' => ['Virtuoso Sabre Booking Successful', 'Flight Segments'],
    ];
    public $reSubject = [
        'Virtuoso Sabre (Sabre Booking)',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Operated By' => ['Operated By', 'OPERATED BY'],
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='virtuoso Heading' or contains(@src,'virtuoso')]")->length > 0) {
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

    private function nextFields($field, $root = null)
    {
        return $this->http->FindNodes("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
            $root);
    }

    private function nextField($field, $root = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
            $root);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->re("#^([A-Z\d]{5,})#", $this->nextField($this->t('PNR'))), $this->t('PNR'))
            ->travellers($this->nextFields($this->t('Passenger #')));

        $passivePNR = $this->re("#^([A-Z\d]{5,})#", $this->nextField($this->t('Passive PNR')));

        if ($passivePNR) {
            $passivePNRTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Passive PNR'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->addConfirmationNumber($passivePNR, $passivePNRTitle);
        }

        $tot = $this->getTotalCurrency($this->nextField($this->t('Fare')));

        if (!empty($tot['Total'])) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $xpathSegHeaders = "//text()[{$this->eq($this->t('Destination'))}]/ancestor::tr[1][{$this->contains($this->t('Departure'))}]";

        // it-38895158.eml
        $shift = $this->http->XPath->query($xpathSegHeaders . "/*[5][{$this->contains($this->t('Arr.Date'))}]")->length > 0 ? 2 : 0;

        $segments = $this->http->XPath->query($xpathSegHeaders . '/following-sibling::tr[ *[7] ]');

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $departure = $this->http->FindSingleNode("td[normalize-space()][1]", $root);

            if (preg_match("#^(.+?)\s*\(\s*([A-Z]{3})\s*\)#", $departure, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }
            $arrival = $this->http->FindSingleNode("td[normalize-space()][2]", $root);

            if (preg_match("#^(.+?)\s*\(\s*([A-Z]{3})\s*\)#", $arrival, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode('td[normalize-space()][3]', $root)));

            if (!$dateDep) {
                $this->date = $dateDep;
            }
            $timeDep = $this->normalizeDate($this->http->FindSingleNode('td[normalize-space()][4]', $root));

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($shift) {
                $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode('td[normalize-space()][5]', $root)));

                if (!$dateArr) {
                    $this->date = $dateArr;
                }
                $timeArr = $this->normalizeDate($this->http->FindSingleNode('td[normalize-space()][6]', $root));

                if ($dateArr && $timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $dateArr));
                }
            }

            $class = $this->http->FindSingleNode("td[normalize-space()][5+{$shift}]", $root, true, "#^[A-Z]{1,2}$#");
            $s->extra()->bookingCode($class, false, true);

            $node = $this->http->FindSingleNode("td[normalize-space()][6+{$shift}]", $root);

            if (preg_match("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $confNo = $this->http->FindSingleNode("td[normalize-space()][7+{$shift}]", $root, false, "#^\s*([A-Z\d]{5,})\s*$#");

            if (!empty($confNo)) {
                $s->airline()->confirmation($confNo);
            }

            $operator = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $root, true, "#^{$this->opt($this->t('Operated By'))}\s*(.+)#");
            $s->airline()->operator($operator, false, true);

            if (!empty($an = $s->getAirlineName())
                && !empty($fn = $s->getFlightNumber())
                && !empty($node =
                    $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Arriving'))}]",
                        null, false, "#{$this->opt($this->t('Arriving'))}:\s+(.+)#"))
            ) {
                //parse added info - not main format of email; example: 15775993.eml
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($node)));
                $node = $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Class'))}]",
                    null, false, "#{$this->opt($this->t('Class'))}:\s+(.+)#");

                if (preg_match("#([A-Z]{1,2})\s*\-\s*(.+)#", $node, $m)) {
                    $s->extra()
                        ->bookingCode($m[1])
                        ->cabin($m[2]);
                }

                if (!empty($node = $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Aircraft Type'))}]/following-sibling::td[1]"))) {
                    $s->extra()->aircraft($node);
                }

                if (!empty($node = $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Flying Time'))}]/following-sibling::td[1]"))) {
                    $s->extra()->duration($node);
                }

                if (!empty($node = $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Operated By'))}]/following-sibling::td[1]"))) {
                    $s->airline()->operator($node);
                }

                if (!empty($node = $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Airline Reference'))}]/following-sibling::td[1]",
                    null, false, "#^\s*([A-Z\d]{5,})\s*$#"))
                ) {
                    $s->airline()->confirmation($node);
                }

                if (strlen($node = $this->http->FindSingleNode("//td[{$this->starts($this->t('Flight Number'))} and contains(.,'{$an}{$fn}')]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Stops'))}]/following-sibling::td[1]",
                    null, false, "#^\s*(\d+)\s*$#"))) {
                    $s->extra()->stops($node);
                }
            } elseif ($shift === 0) {
                $s->arrival()->noDate();
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //12/19/2014
            '#^(\d+)\/(\d+)\/(\d{4})$#',
            //0745
            '#^(\d{2})(\d{2})$#',
            //Sun 27 Sep 12:50PM
            '#^\w+\s+(\d+)\s+(\w+)\s+(\d+:\d+(\s*[ap]m)?)$#i',
        ];
        $out = [
            '$3-$1-$2',
            '$1:$2',
            '$1 $2 ' . $year . ' $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
