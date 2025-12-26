<?php

namespace AwardWallet\Engine\hertz\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AccountUpdated extends \TAccountChecker
{
    public $mailFiles = "hertz/statements/it-73026169.eml, hertz/statements/it-73236596.eml, hertz/statements/it-73735062.eml";

    public $detectBody = [
        'en' => [
            'You\'ve successfully linked your CLEAR and Hertz Gold Plus Rewards® accounts',
            'Your Password was modified on ',
            'a request to reset your Gold Plus Rewards password',
        ],
        'es' => [
            'Su contraseña fue modificada domingo, ',
        ],
    ];

    public $lang = '';
    public static $dictionary = [
        "en" => [
            'Hello ' => ['Hello ', 'Dear '],
        ],
        "es" => [
            'Hello ' => 'Hola ',
        ],
    ];

    private $detectFrom = ['hertz@emails.hertz.com', 'noreply@emails.hertz.com'];
    private $detectSubject = [
        'Your Profile Information Has Changed',
        // en
        'Finishing Resetting your Hertz.com Password',
        'Finish Resetting your Hertz.com Password',
        'You\'ve linked your accounts',
        'Our updated Gold Terms and Conditions',
        'Member Number Retrieval',
        // es
        'Finalización del restablecimiento de la contraseña de Hertz.com',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setMembership(true);

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $info = $this->http->FindSingleNode("(//tr[not(normalize-space())][//img[@alt = 'hertz']/ancestor::a[@title = 'Hertz']]/following-sibling::tr[normalize-space()][1]/descendant::td[1][contains(., '#')])[1]");

        if (!empty($info) && preg_match("/^\s*([[:alpha:]]+(?: [[:alpha:]]+)+)\s*\#\s*(\d{5,})\s*$/u", $info, $m)) {
            $number = $m[2];
            $name = $m[1];
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Member Number is'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/");
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member Number'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]", null,
                false, "/^{$this->opt($this->t('Hello '))}\s*((?:[[:alpha:]]+\s){2,5})[,:]$/u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if (!empty($number)) {
            $st->setNumber($number);
            $st->setLogin($number);
        }

        $st->setNoBalance(true);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false
                    && (stripos($headers['subject'], 'hertz.com') !== false || $this->striposAll($headers['from'], $this->detectFrom) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"click.emails.hertz.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                return true;
            }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //10/21/2020 12:00:00 AM
            '#^(\d+)\/(\d+)\/(\d{4})\s+(\d+\:\d+)\:\d+\s*(A?P?M)$#u',
            //10/21/2020
            '#^(\d+)\/(\d+)\/(\d{4})$#u',
        ];
        $out = [
            '$2.$1.$3, $4',
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
