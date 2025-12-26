<?php

namespace AwardWallet\Engine\taj\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PointsUpdate extends \TAccountChecker
{
    public $mailFiles = "taj/statements/it-77103233.eml";
    public $subjects = [
        "/^\w+\'\d+\s*\|\s*Taj InnerCircle Points Update$/",
        "/^Taj InnerCircle Points Update:\s*\w+\'\d+$/",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tajhotels.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'his email was sent by The Indian Hotels Company Limited')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Taj InnerCircle Membership No:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TIC Points:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tajhotels.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taj InnerCircle Membership No:')]/preceding::text()[normalize-space()][1]");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taj InnerCircle Membership No:')]/following::text()[normalize-space()][1]");
        $st->setNumber($number);

        /*$status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TIC Tier:')]/following::text()[normalize-space()][1]");
        $st->addProperty('Status', $status);*/

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TIC Points:')]/following::text()[normalize-space()][1]");
        $st->setBalance($balance);

        $balanceDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'As of ')][not(contains(normalize-space(), 'last'))]", null, true, "/{$this->opt($this->t('As of'))}\s*(.+)/");
        $st->setBalanceDate(strtotime($balanceDate));

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
        return 0;
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
