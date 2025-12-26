<?php

namespace AwardWallet\Engine\speedway\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Member extends \TAccountChecker
{
    public $mailFiles = "speedway/it-75414029.eml, speedway/it-75563557.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = '@speedwayemail.com';

    private $detectSubject = [
        ' at Speedway!',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProv = false;

        if (stripos($parser->getCleanFrom(), $this->detectFrom) !== false) {
            $detectProv = true;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($parser->getSubject(), $dSubject) !== false) {
                $detectProv = true;

                break;
            }
        }

        if ($detectProv == false) {
            return false;
        }

        if ($this->http->XPath->query("//*[" . $this->contains('This promotional message was delivered to you as a Speedy Rewards member') . "]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st
            ->setMembership(true);

        $text = $this->http->FindSingleNode("//text()[" . $this->starts(['Points Balance:']) . "]/ancestor::td[1]");

        if (preg_match("/Points Balance: (\d[\d, ]*) as of (\d{2}-\d{2}-\d{4})\s*$/", $text, $m)) {
            $st
                ->setBalance(str_replace([' ', ','], '', $m[1]))
                ->setBalanceDate(strtotime($m[2]))
            ;
        }

        if (empty($text)) {
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
}
