<?php

namespace AwardWallet\Engine\jrg\Email;

use AwardWallet\Schema\Parser\Email\Email;

class NotificationJunk extends \TAccountChecker
{
    public $mailFiles = "jrg/it-502414345.eml, jrg/it-767525295.eml";

    public $detectFrom = "@japanrailpass-reservation.net";
    public $detectSubject = [
        // en
        '[JAPAN RAIL PASS Reservation]Purchase Complete Notification',
        '[JAPAN RAIL PASS Reservation]User Information Registration',
        '[JAPAN RAIL PASS Reservation]購票成功通知',
    ];

    public $detectBody = [
        'en' => [
            ['Credit card payment for the following is complete.', 'Valid For:'],
            ['Please access  the following URL to complete your registration.', 'Note that temporary registration via the URL is valid for 24 hours'],
        ],
        'zh' => [
            ['信用卡付款已完成，詳情如下。', '使用期限:'],
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Boarding Date' => 'Boarding Date', // from parser Notification
        ],
        'zh' => [
            'Boarding Date' => '乘車日',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]japanrailpass-reservation\.net$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'JAPAN RAIL PASS') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();

        if ($this->containsText($text, '(C) JAPAN RAILWAYS GROUP') === false) {
            return $email;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (count($dBody) == 2
                    && $this->containsText($text, $dBody[0]) === true
                    && $this->containsText($text, $dBody[1]) === true
                    && !empty(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['Boarding Date'])
                    && $this->containsText($text, self::$dictionary[$lang]['Boarding Date']) === false
                    && $this->containsText($text, '⇒') === false
                ) {
                    $this->lang = $lang;
                    $email->setIsJunk(true);

                    break;
                }
            }
        }

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
        return count(self::$dictionary);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
