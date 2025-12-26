<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-776338454.eml, mileageplus/statements/st-68949864.eml, mileageplus/statements/st-69072627.eml";

    private $detectFrom = [
        '@mileageplusshoppingnews.com', '@unitedmileageplus.com', '@united.com',
    ];

    private $detectSubject = [
        'Welcome to MileagePlus',
        'You have successfully enrolled in MileagePlus',
    ];

    private $lang = 'en';

    private static $dictionary = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $st = $email->add()->statement();

        $st->setMembership(true);

        $number = $this->http->FindSingleNode("//text()[" . $this->starts('Hi,') . "]/following::text()[normalize-space()][1][" . $this->starts('#') . "]", null, true,
            "/^\s*#(?:X{3,}|\*{3,})(\d{3,7})\s*$/");

        if (!empty($number)) {
            $st->setNumber($number)->masked();
            $st->setNoBalance(true);
        }

        $fullNumber = $this->http->FindSingleNode("//text()[" . $this->eq(['MileagePlus Number:', 'MileagePlus Number']) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*([A-Z]{0,3}\d{3,7})\s*$/");

        if (!empty($fullNumber)) {
            if (preg_match("/^([X*])\1{2,}/", $fullNumber)) {
                $st->setNumber($fullNumber)->masked();
            } else {
                $st->setNumber($fullNumber);
            }
            $st->setNoBalance(true);
        }

        $name = $this->http->FindSingleNode("//text()[" . $this->starts('Hi,') . "]", null, true,
            "/^\s*{$this->preg_implode('Hi, ')}\s*([[:alpha:] \-]+)\s*$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->starts('MileagePlus® enrollment confirmation for') . "]", null, true,
                "/^\s*{$this->preg_implode('MileagePlus® enrollment confirmation for')}\s*([[:alpha:] \-]+)\s*$/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach ($this->detectFrom as $from) {
            if (stripos($headers['from'], $from) !== false) {
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom === true && preg_match("/^\s*{$this->preg_implode($this->detectSubject)}/", $headers['subject'])) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) !== true) {
            return false;
        }

        if ($this->http->XPath->query("//a[contains(@href, '.united.com')] | //text()[contains(.,'united.com')]")->length > 0) {
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
