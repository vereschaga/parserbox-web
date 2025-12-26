<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class IsJunk extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-75890161.eml";
    public $subjects = [
        '/^Important: Basic Economy restrictions on your United flight$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@news.united.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'United Airlines')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Restrictions of a Basic Economy ticket'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for your recent purchase of a Basic Economy ticket'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\.united\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your itinerary*'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Restrictions of a Basic Economy ticket.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We want your feedback'))}]")->length > 0) {
            $email->setIsJunk(true);
        }
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
