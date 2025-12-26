<?php

namespace AwardWallet\Engine\suntrans\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingCancellation extends \TAccountChecker
{
    public $mailFiles = "suntrans/it-138668917.eml";
    public $subjects = [
        //en
        '/Booking SUNTR[\_A-Z\d\s\-]+Cancellation confirmation$/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@suntransfers.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Suntransfers.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('your booking SUNTR_'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]suntransfers\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('We are so very sorry that you are having to cancel your transfer with us'))}]")->length > 0) {
            $t = $email->add()->transfer();

            $t->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('your booking'))}]", null, true, "/{$this->opt($this->t('your booking'))}\s*(SUNTR[\_\d[A-Z]+)\s*/"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\D+)\,/"), true)
                ->cancelled()
                ->status($this->http->FindSingleNode("//text()[{$this->starts($this->t('your booking'))}]", null, true, "/{$this->opt($this->t('has been'))}\s*(\w+)\./"));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
