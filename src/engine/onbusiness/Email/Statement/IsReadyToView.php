<?php

namespace AwardWallet\Engine\onbusiness\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class IsReadyToView extends \TAccountChecker
{
    public $mailFiles = "onbusiness/statements/it-121103033.eml";
    public $subjects = [
        ', your On Business statement is ready to view',
        'Let’s get back to business,',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'visit ba.com/onbusiness' => ['visit ba.com/onbusiness', 'Fly further with your On Business membership'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fly.ba.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('visit ba.com/onbusiness'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('My On Business account'))}]")->length > 0
                && $this->http->XPath->query("//img[contains(@src, 'britishairways.com')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fly\.ba\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('My On Business account'))}]/preceding::text()[{$this->starts($this->t('Dear'))}][1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('My On Business account'))}]/preceding::text()[{$this->starts($this->t('It’s time to fly, '))}][1]", null, true, "/{$this->opt($this->t('It’s time to fly, '))}\s*([[:alpha:]][[:alpha:]\s]*[[:alpha:]])\.\s*[A-Z]/u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim(str_replace(["Mrs", "Ms", "Mr", "REV"], '', $name), ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('MEMBERSHIP'))}]", null, true, "/^{$this->opt($this->t('MEMBERSHIP'))}\s*([A-Z\d]{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TIER LEVEL'))}]/preceding::text()[normalize-space()][1]");

        if (!empty($tier)) {
            $st->addProperty('Tier', $tier);
        }

        $pointsExpiring = $this->http->FindSingleNode("//text()[{$this->starts($this->t('POINTS EXPIRING END OF YEAR'))}]/preceding::text()[normalize-space()][1]");

        if ($pointsExpiring !== null) {
            $st->addProperty('PointsToExpire', $pointsExpiring);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ON BUSINESS POINTS'))}]/preceding::text()[normalize-space()][1]");
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
