<?php

namespace AwardWallet\Engine\rzd\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class BonusBalance extends \TAccountChecker
{
    public $mailFiles = "rzd/statements/it-130428855.eml";

    public $lang = 'ru';
    public static $dictionary = [
        'ru' => [
//            'Confirmation' => 'Confirmation',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply@rzd-bonus.ru') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if ($this->detectEmailFromProvider(implode('', $parser->getFrom())) !== true) {
            return false;
        }

        if ($this->http->XPath->query("//tr[not(.//tr)][starts-with(normalize-space(), 'Номер счета:')][count(preceding::img) = 2 and following::text()[normalize-space()][1][normalize-space() = 'О программе']]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Номер счета:')]", null, true,
            "/:\s*(\d{5,})\s*$/");
        $st
            ->setNumber($number)
            ->setLogin($number);

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Баланс баллов:')]", null, true,
            "/:\s*(\d+)\s*$/");
        $st->setBalance($balance);


        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmailHtml(Email $email)
    {
            $st = $email->add()->statement();
        //$st->setNumber("");
    
        return true;
    }


    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Phrase1"], $dict["Phrase2"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Phrase1'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Phrase2'])}]")->length > 0
                ) {
                    $this->lang = $lang;
                    return true;
                }
            }
        }
        return false;
    }



    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }



    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }


    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || empty($this->date)) {
            return null;
        }

        $in = [
//            // Sun, Apr 09
//            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
//            // Tue Jul 03, 2018 at 1 :43 PM
//            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
//            '$1, $3 $2 ' . $year,
//            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));


        $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }


}