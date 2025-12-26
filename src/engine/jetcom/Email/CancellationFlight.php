<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationFlight extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-153204579.eml";
    public $subjects = [
        'Important Information Regarding Your Jet2.com Flight',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'the cancellation of your flight booking' => ['the cancellation of your flight booking', 'cancellation of your flight booking'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jet2.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Jet2.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Important Information Regarding Your'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('the cancellation of your flight booking'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jet2\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference:')]", null, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([\dA-Z]+)/u"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/u"));

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('the cancellation of your flight booking'))}]")->length > 0) {
            $f->general()
                ->cancelled();
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
