<?php

namespace AwardWallet\Engine\upromise\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "upromise/it-100851176.eml, upromise/it-80049994.eml, upromise/it-80114145.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '@upromise.com';

    private $detectSubject = [
        'You have cashback pending for a recent purchase at',
        'Reset your password for your Upromise Account',
        'Your savings are on the way!',
        'Your savings transfer is complete!',
        'Verify Your Linked Account',
    ];

    private $detectBody = [
        'You are getting cash back rewards from Upromise',
        'We received your request to reset your password',
        'We have initiated a transfer of your Upromise rewards to your linked',
        'has been successfully transferred to help with college savings or other financial goals.',
        'add or change a Linked Redemption Account associated with your Upromise account',
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@upromise.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'member@mc.upromise.com') !== false) {
            return true;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains('upromise.com', '@href') . "]")->length < 3) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st
            ->setMembership(true)
            ->setNoBalance(true)
        ;

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
