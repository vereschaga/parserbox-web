<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationOfBooking extends \TAccountChecker
{
    public $mailFiles = "sixt/it-675103423.eml";
    public $subjects = [
        '/^Cancellation of your booking \d+$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sixt.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'with Sixt')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We hereby confirm the cancellation of your reservation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Sixt Team'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sixt\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->cancelled()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('We hereby confirm the cancellation of your reservation'))}]", null, true, "/^{$this->opt($this->t('We hereby confirm the cancellation of your reservation'))}\s*(\d+)\s/"))
            ->status('cancellation');

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
