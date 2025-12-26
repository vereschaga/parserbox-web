<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Shopping extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-52110725.eml, mileageplus/statements/st-68006739.eml, mileageplus/statements/st-69098794.eml, mileageplus/statements/st-69139967.eml, mileageplus/statements/st-69156175.eml";

    private $detectFrom = ['@mileageplusshoppingnews.com'];

    private $lang = 'en';

    private static $dictionary = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $st = $email->add()->statement();

        $xpath = "//tr[td[" . $this->starts("Hi,") . "] and td[" . $this->starts("Total miles earned") . "]]";

        if (!empty($this->http->FindSingleNode($xpath))) {
            $info = implode(" ",
                $this->http->FindNodes($xpath . "/td[" . $this->starts($this->t('Hi,')) . "]//text()[normalize-space()]"));

            if (preg_match("/^\s*{$this->preg_implode('Hi, ')}\s*(?<name>[[:alpha:] \-]+)\s*#(?:X{3,}|\*{3,})(?<number>\d{3,7})\s*$/",
                $info, $m)) {
                $st->setNumber($m['number'])->masked();
                $st->addProperty('Name', $m['name']);
            }

            $balance = $this->http->FindSingleNode($xpath . "/td[" . $this->starts('Total miles earned') . "]", null,
                true,
                "/{$this->preg_implode("Total miles earned")}\s*(\d[\d,]*)\*$/");
            // "Это просто заработанные на шопинге мили, не отражают реальный баланс united."
//            $st->setBalance(str_replace(',', '', $balance));
            $st->setNoBalance(true);

        /*
                    $date = $this->http->FindSingleNode("//text()[" . $this->starts('The mileage rates are valid as of') . "]",
                        null, true,
                        "/{$this->preg_implode("The mileage rates are valid as of")}\s*(\d[\d\/]{6,}.*?)\./");
                    if (!empty($date)) {
                        //10/26/2020 12:00:00 AM; 10/26/20
                        $st->setBalanceDate($this->normalizeDate($date));
                    }
        */
        } elseif (!empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Hi,')) . "]/ancestor::td[1][.//text()[" . $this->starts('#') . "]]"))) {
            $info = implode(" ",
                $this->http->FindNodes("//text()[" . $this->starts($this->t('Hi,')) . "]/ancestor::td[1][.//text()[" . $this->starts('#') . "]]//text()[normalize-space()]"));

            if (preg_match("/^\s*{$this->preg_implode('Hi, ')}\s*(?<name>[[:alpha:] \-]+)\s*#(?:X{3,}|\*{3,})(?<number>\d{3,7})\s*$/",
                $info, $m)) {
                $st->setNumber($m['number'])->masked();
                $st->addProperty('Name', $m['name']);
                $st->setNoBalance(true);
            }
        } elseif (!empty($info = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Welcome')) . " and contains(., '| #')]"))) {
            if (preg_match("/^\s*{$this->preg_implode('Welcome')}\s*(?<name>[[:alpha:] \-]+)\s*\|\s*#(?:X{3,}|\*{3,})(?<number>\d{3,7})\s*$/",
                $info, $m)) {
                $st->setNumber($m['number'])->masked();
                $st->addProperty('Name', $m['name']);
                $st->setNoBalance(true);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        if ($this->http->XPath->query("//img[@alt = 'MileagePlus Shopping | United Airlines']")->length > 0
            && (
                $this->http->XPath->query("//tr[td[starts-with(normalize-space(), 'Hi,')] and td[starts-with(normalize-space(), 'Total')]]")->length > 0
                || $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Hi,')]/following::text()[normalize-space()][1][starts-with(normalize-space(), '#')]")->length > 0
                || $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Welcome') and contains(., '| #')]")->length > 0
            )
        ) {
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
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
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

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "/^\s*(\d{1,2}\/\d{1,2}\/)(\d{2})\s*$/", //10/23/20
        ];
        $out = [
            '${1}20$2',
        ];
        $str = preg_replace($in, $out, $str);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }
        return strtotime($str);
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
