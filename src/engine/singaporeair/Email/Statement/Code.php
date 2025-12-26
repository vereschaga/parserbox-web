<?php

namespace AwardWallet\Engine\singaporeair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-243603940.eml, singaporeair/statements/it-1.eml";
    public $subjects = [
        'KrisFlyer Account Activation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@singaporeair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('KrisFlyer Membership Services'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('ACTIVATE YOUR KRISFLYER MEMBERSHIP ACCOUNT NOW'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for signing up for a KrisFlyer account'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('to activate your membership'))}]")->length > 0;
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('KrisFlyer registration'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('To activate your account'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for signing up with KrisFlyer'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]singaporeair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $membership = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Tier Status:')]/following::text()[starts-with(normalize-space(), 'Membership No:')][1]", null, true, "/{$this->opt($this->t('Membership No:'))}\s*(\d{6,})$/");

        if (!empty($membership)) {
            $st->setNumber($membership);
        }

        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Tier Status:')]", null, true, "/{$this->opt($this->t('Tier Status:'))}\s*(.+)/");

        if (!empty($status)) {
            $st->addProperty('CurrentTier', $status);
        }

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Tier Status:')]/following::text()[starts-with(normalize-space(), 'Dear')][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)\s*\,$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear ')]", null, true, "/{$this->opt($this->t('Dear '))}\s*(\D+)\,/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', str_replace(['Mrs ', 'Ms ', 'Mr '], '', $name));
        }

        $st->setNoBalance(true);

        $c = $email->add()->oneTimeCode();
        $c->setCodeAttr("/^https\:\/\/www\.singaporeair\.com\/kfNewUserEnroll\.form\?[A-z\=\d\&\/\+]+$/u", 5000);
        $code = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Tier Status:')]/following::a[normalize-space()='click here'][1]/@href");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'To activate your account')]/following::a[normalize-space()='here'][1]/@href");
        }
        $c->setCode($code);

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
