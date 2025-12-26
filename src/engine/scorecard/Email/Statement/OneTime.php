<?php

namespace AwardWallet\Engine\scorecard\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTime extends \TAccountChecker
{
    public $mailFiles = "scorecard/statements/it-100179946.eml";
    public $subjects = [
        '/Your ScoreCard Rewards Code/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@scorecardrewards.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'ScoreCard Rewards')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your ScoreCard code is'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Authentication Code'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]scorecardrewards\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your ScoreCard code is'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setNoBalance(true);

        $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your ScoreCard code is'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Your ScoreCard code is'))}\s*(\d+)/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
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
