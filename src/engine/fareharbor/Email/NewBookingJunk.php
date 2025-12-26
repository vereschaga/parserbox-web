<?php

namespace AwardWallet\Engine\fareharbor\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewBookingJunk extends \TAccountChecker
{
    public $subjects = [
        '/^New booking:/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fareharbor.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'FareHarbor')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'View on FareHarbor')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Booking #')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Booking note:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Affiliate:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Voucher:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Created by:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Created at:')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fareharbor\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
