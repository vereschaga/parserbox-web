<?php

namespace AwardWallet\Engine\reurope\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ShoppingBasket extends \TAccountChecker
{
    public $mailFiles = "reurope/it-21784402.eml, reurope/it-43348736.eml, reurope/it-50687022.eml";

    public $reFrom = ["@railplus.com.au"];
    public $reBody = [
        'en' => [
            'Shopping Basket',
            ['train', 'Train'],
        ],
    ];
    public $reSubject = [
        'www.railplus.com.au Booking Number',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'train'      => ['train', 'Train'],
            'skipStatus' => ['NOT_APPLICABLE'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

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
        if ($this->http->XPath->query("
                //img[contains(@src,'railplus.com.au') or contains(@src,'railplus.co.nz')] | 
                //a[contains(@href,'railplus.com.au') or contains(@href,'railplus.co.nz')]")->length > 0) {
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
        $xpathFees = "//text()[{$this->eq($this->t('Basket Total'))}]/ancestor::tr[1]/preceding-sibling::tr[count(./descendant::text()[normalize-space()])=2]";
        $nodes = $this->http->XPath->query($xpathFees);

        foreach ($nodes as $rootFee) {
            $fee = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $rootFee);
            $tot = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $rootFee);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->fee($fee, $tot['Total']);
        }
        $tot = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Basket Total'))}]/ancestor::td[1]/following-sibling::td[1]");
        $tot = $this->getTotalCurrency($tot);
        $email->price()
            ->total($tot['Total'])
            ->currency($tot['Currency']);

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Number is'))}]",
                null, false, "#{$this->opt($this->t('Booking Number is'))} *([A-Z\d]{5,})#"), 'Booking Number');

        $xpath = "//text()[{$this->eq($this->t('Fare Type:'))}]/ancestor::table[{$this->contains($this->t('train'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        $checkNodes = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][1]", $nodes[0]);

        if ($checkNodes == "Fare Type:") {
            $xpath = "//text()[{$this->eq($this->t('Fare Type:'))}]/ancestor::table[{$this->contains($this->t('train'))}][2]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug("[XPATH] - roots found: {$nodes->length}\n" . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->train();

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('e-Ticket PNR'))}]/following::text()[normalize-space()!=''][1]",
                $root, false, "#^([A-Z\d]{5,})$#");

            if (empty($node) && $this->http->XPath->query("./descendant::text()[{$this->contains($this->t('e-Ticket PNR'))}]",
                    $root)->length == 0
            ) {
                $r->general()->noConfirmation();
            } else {
                $r->general()->confirmation($node, 'e-Ticket PNR');
            }

            $tot = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td[1]", $root);
            $tot = $this->getTotalCurrency($tot);
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            $travellerRows = $this->http->XPath->query("following-sibling::*[normalize-space()]", $root);

            foreach ($travellerRows as $tRow) {
                if ($this->http->XPath->query("descendant::*[{$this->contains($this->t('Date of birth:'))}]", $tRow)->length === 0) {
                    break;
                }
                $tRowText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $tRow));

                if (preg_match_all("#\b([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\s*\(#u", $tRowText, $m)) {
                    $pax = array_map(function ($s) {
                        return trim(preg_replace("#\s+#", ' ', $s));
                    }, $m[1]);
                    $r->general()->travellers($pax);
                }
            }

            $s = $r->addSegment();
            $train = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][1]", $root);

            if (preg_match("#^(.+) *\/ *{$this->opt($this->t('train'))}[ \#]+([A-Z]{0,2}\d+)?$#", $train, $m)) {
                // 2nd class / train #8981    |    1st class / Train #   |   2nd class / Train #GW430900
                $s->extra()->cabin($m[1]);

                if (!empty($m[2])) {
                    $s->extra()->number($m[2]);
                } else {
                    $s->extra()->noNumber();
                }
            } elseif (preg_match("#^{$this->opt($this->t('train'))}[ \#]+(\d+)$#", $train, $m)) {
                // Train #2159
                $s->extra()->number($m[1]);
            }

            $dep = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][1]/ancestor::tr[1]/td[2]/descendant::text()[normalize-space()!=''][1]",
                $root);
            $depTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][1]/ancestor::tr[1]/td[2]/descendant::text()[normalize-space()!=''][2]",
                $root);
            $arr = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][1]/ancestor::tr[1]/td[4]/descendant::text()[normalize-space()!=''][1]",
                $root);
            $arrTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][1]/ancestor::tr[1]/td[4]/descendant::text()[normalize-space()!=''][2]",
                $root);
            $s->departure()
                ->name($dep)
                ->date($this->normalizeDate($depTime));
            $s->arrival()
                ->name($arr)
                ->date($this->normalizeDate($arrTime));
            $status = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][ancestor::tr[not(contains(normalize-space(.), 'This product is pending'))]][2]/ancestor::tr[1]/td[5]/descendant::div[preceding-sibling::div][1]", $root);

            $s->extra()
                ->car($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Coach:'))}]/following::text()[normalize-space()!=''][1]",
                    $root), false, true)
                ->seats(explode(' ',
                    $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat:'))}]/following::text()[normalize-space()!=''][1]",
                        $root)));
            $statusSkip = (array) $this->t('skipStatus');

            if ($status !== null && !in_array($status, $statusSkip)) {
                $s->extra()->status($status);
            }

            $node1 = ucfirst($this->http->FindSingleNode("./descendant::img[1]/@src", $root, false,
                "#\/([^\/]+)\.png$#"));
            $node2 = ucfirst(preg_replace("#\s*{$this->opt($this->t('Image removed by sender'))}[.!\s]*#i", '',
                $this->http->FindSingleNode("descendant::img[1]/@alt", $root)));

            if ($node1 == $node2) {
                $type = $node1;
            } else {
                $type = trim($node1 . '-' . $node2, " -");
            }

            if (!empty($type)) {
                $s->extra()->type($type);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //13:59 / Tue, 11 Sep 2018
            '#^(\d+:\d+) *\/ *[\w\-]+, (\d+)\s+(\w+)\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $3 $4, $1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
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
