<?php

namespace AwardWallet\Engine\skyscanner\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AllBookedFlight extends \TAccountChecker
{
    public $mailFiles = "skyscanner/it-52053076.eml, skyscanner/it-52216289.eml";

    public $reFrom = ["sender.skyscanner.net"];
    public $reBody = [
        'en' => ['Thanks for booking with', 'through Skyscanner'],
    ];
    public $reSubject = [
        'All booked! Here are your flight details',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your flight' => 'Your flight',
            'Travellers'  => ['Travellers', 'Travelers'],
            'notName'     => ['Adult', 'Child'],
            'noStop'      => ['Direct', 'Non-stop'],
        ],
    ];
    private $keywordProv = ['Skyscanner'];
    private $providersKeywords = [
        'ctrip'      => ['Trip.com'],
        'singaporeair' => ['Singapore Airlines'],
        'british'      => ['British Airways'],
        'vueling'      => ['Vueling Airlines'],
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['ctrip', 'singaporeair', 'british', 'vueling'];
    }

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
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[{$this->contains(['sender.skyscanner.net', 'www.skyscanner.net', 'content.skyscnr.com'], '@src')}] | //a[contains(@href,'sender.skyscanner.net')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->detectBody()) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Travellers'))}]/ancestor::tr[1]/following-sibling::tr/descendant::tr/td[2][./preceding-sibling::td[1][//img]][normalize-space()!=''][not({$this->eq($this->t('notName'))})]");

        // cabin from pay
        $fares = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Your fare'))}]/ancestor::tr[1]/following-sibling::tr/descendant::tr/td[1][normalize-space()!='']",
            null, "/\d+ x (.+)/")));

        if (count($fares) === 1) {
            $cabin = array_shift($fares);
        }

        // general
        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference number'))}]/following::text()[normalize-space()!=''][1]",
            null, false, '/^[\w\-]+$/');
        $descr = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Thanks for booking with'))}) and ({$this->contains($this->t('through Skyscanner'))})]",
            null, false,
            "/{$this->opt($this->t('Thanks for booking with'))}\s+(.+)\s+{$this->opt($this->t('through Skyscanner'))}/");
        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('All the best'))}]/preceding::text()[normalize-space()!=''][1]/ancestor::tr[1][.//img]",
            null, false, "/^[ \d\(\)\+\-]+$/");
        $provider = !empty($descr) ? $this->getRentalProviderByKeyword($descr) : null;

        if (preg_match("/^[A-Z\d]{5,6}$/", $confNo)) {
            $r->general()->confirmation($confNo);

            if (!empty($phone) && !empty($descr)) {
                $r->program()->phone($phone, $descr);
            }

            if (null !== $provider) {
                $r->setProviderCode($provider);
            } elseif (!empty($descr)) {
                $r->setProviderKeyword($descr);
            }
        } elseif (!empty($confNo)) {
            $r->general()->noConfirmation();
            $r->ota()->confirmation($confNo);

            if (!empty($phone) && !empty($descr)) {
                $r->ota()->phone($phone, $descr);
            }

            if (null !== $provider) {
                $r->ota()->code($provider);
            } elseif (!empty($descr)) {
                $r->ota()->keyword($descr);
            }
        } else {
            $r->general()->confirmation($confNo); //for broke
        }
        $r->general()->travellers($pax);

        // price
        $total = $this->http->FindSingleNode("//text()[{$this->eq('Total')}]/ancestor::td[1]/following-sibling::td[1]");
        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['total'])
            ->currency($total['currency']);

        // segments
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$ruleTime}]/ancestor::tr[1][./following-sibling::tr[normalize-space()!=''][last()][{$ruleTime}]]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $r->addSegment();

            if (isset($cabin)) {
                $s->extra()->cabin($cabin);
            }

            $date = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./preceding::tr[./td[1][.//img] and count(.//text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!=''][last()]",
                $root)));

            // airline
            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root))
                ->noNumber();

            // departure
            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root),
                    $date))
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root, false,
                    "/(.+) \([A-Z]{3}\)$/"))
                ->code($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root, false,
                    "/\(([A-Z]{3})\)$/"));

            // arrival
            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][last()]/descendant::text()[normalize-space()!=''][1]",
                    $root), $date))
                ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][last()]/descendant::text()[normalize-space()!=''][2]",
                    $root, false, "/(.+) \([A-Z]{3}\)$/"))
                ->code($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][last()]/descendant::text()[normalize-space()!=''][2]",
                    $root, false, "/\(([A-Z]{3})\)$/"));

            // duration
            $s->extra()
                ->duration($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[1]",
                    $root), true);

            // stops
            $noStop = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[last()]",
                $root);

            if (!in_array($noStop, (array) $this->t('noStop'))) {
                $this->logger->debug('new format. with stops');
                $s->extra()->seat(null); //for broke parsing
            } else {
                $s->extra()->stops(0);
            }
        }

        return true;
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Your flight'], $words['Travellers'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Your flight'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Travellers'])}]")->length > 0
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
        if ($this->lang === 'en') {
            return $date;
        }

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
        $node = str_replace(["€", "£", "₹", "฿"], ["EUR", "GBP", "INR", "THB"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['total' => $tot, 'currency' => $cur];
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

    private function contains($field, $src = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($src) {
            return 'contains(' . $src . ',"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->providersKeywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }
}
