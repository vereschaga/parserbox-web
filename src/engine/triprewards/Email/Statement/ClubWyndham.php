<?php

namespace AwardWallet\Engine\triprewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ClubWyndham extends \TAccountChecker
{
    public $mailFiles = "triprewards/statements/it-75892131.eml, triprewards/statements/it-76002663.eml";
    public $subjects = [
        '/^Club Wyndham/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Owner Number:' => ['Owner Number:', 'owner number:'],
            'Hi'            => ['Hi', 'Dear'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wyn.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Club Wyndham')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Owner Number:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hi'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wyn\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Owner Number:'))}]", null, true, "/{$this->opt($this->t('Owner Number:'))}\s*(\d+)/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setNoBalance(true);
        }

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Tier:')]", null, true, "/{$this->opt($this->t('Your Tier:'))}\s+(\D+)/");

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\D+)/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Sent to'))}]", null, true, "/{$this->opt($this->t('Sent to'))}\s*(\S+[@]\S+\.\S+)/");

        if (!empty($login)) {
            $st->setLogin($login);
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
