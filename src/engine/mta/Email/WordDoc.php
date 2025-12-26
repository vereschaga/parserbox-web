<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class WordDoc extends \TAccountChecker
{
    public $mailFiles = "mta/it-16232248.eml";

    public $reFrom = ["mtatravel.com.au"];
    public $reBody = [
        'en' => ['Flight', 'Depart'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
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
        if ($this->http->XPath->query("//a[contains(@href,'www.mtatravel.com.au')] | //text()[contains(normalize-space(.),'plus MTA')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation();
        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->http->FindSingleNode("./descendant::tr[1]", $root));
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight'))}]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            if (preg_match("#\s+([A-Z][A-Z\d]|[\dA-Z][A-Z])\s*(\d+)\s*(?:\({$this->opt($this->t('Operated By'))}\s+(.+)\))?$#",
                $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (isset($m[3]) && !empty($m[3])) {
                    $s->airline()
                        ->operator($m[3]);
                }
            }

            $s->extra()
                ->status($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                    $root), true)
                ->aircraft($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Equipment'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->meal($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Services'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root), true)
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight Time'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->cabin($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[3]",
                    $root));
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->terminal($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}]/ancestor::td[1]/following-sibling::td[2]",
                    $root, false, "#{$this->opt('Terminal')}\s*(.*)#"), false, true)
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}]/ancestor::td[1]/following-sibling::td[3]",
                    $root), $date));
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrive'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->terminal($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrive'))}]/ancestor::td[1]/following-sibling::td[2]",
                    $root, false, "#{$this->opt('Terminal')}\s*(.*)#"), false, true)
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrive'))}]/ancestor::td[1]/following-sibling::td[3]",
                    $root), $date));
        }

        return true;
    }

    private function normalizeDate($strTime, $date)
    {
        $in = [
            //7:45 AM on Friday
            '#^(\d+:\d+\s+[ap]m)\s*on\s+(.+)\s*$#i',
        ];
        $out = [
            '$1',
        ];
        $outWeek = [
            '$2',
        ];
        $week = preg_replace($in, $outWeek, $strTime);
        //correct by weekday
        if ($week !== $strTime) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = strtotime(preg_replace($in, $out, $strTime), $date);

            for ($i = 0; $i <= 2; $i++) {
                $try = strtotime(sprintf('+%d days', $i), $str);

                if ((int) date('N', $try) === $weeknum) {
                    return $try;
                }

                if ($i === 0) {
                    continue;
                }
                $try = strtotime(sprintf('-%d days', $i), $date);

                if ((int) date('N', $try) === $weeknum) {
                    return $try;
                }
            }
        } else {
            $str = strtotime($strTime, $date);
        }

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
