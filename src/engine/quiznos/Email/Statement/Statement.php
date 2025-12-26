<?php

namespace AwardWallet\Engine\quiznos\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "quiznos/statements/it-87178106.eml, quiznos/statements/it-87295769.eml, quiznos/statements/it-87295771.eml, quiznos/statements/it-87297067.eml";
    public $subjects = [
        '/Your reward is about to expire\!/',
        '/Welcome to Quiznos Toasty Points/',
        '/Message from Quiznos/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Toasty Points Total'         => ['Toasty Points Total', 'Live Toasty. Eat Toasty', 'You earned'],
            'You are running out of time' => ['You are running out of time', 'Hello and thanks for joining'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@quiznos.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Quiznos')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Toasty Points Total'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('points'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]quiznos\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are running out of time'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\,$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('points available'))}]", null, true, "/^(\D+){$this->opt($this->t('has'))}/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are running out of time'))}]", null, true, "/{$this->opt($this->t('You are running out of time'))}\s*(\D+)\!/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Toasty Points Total:'))}]", null, true, "/{$this->opt($this->t('Toasty Points Total:'))}\s*([\d\.]+)/");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('points available'))}]", null, true, "/([\d\.]+)\s*{$this->opt($this->t('points available'))}/");
        }

        if ($balance !== null) {
            $st->setBalance($balance);
        } elseif ($balance == null && !empty($name)) {
            $st->setNoBalance(true);
        } elseif ($balance == null && empty($name) && $this->detectEmailByBody($parser) == true) { //it-87295769
            $st->setMembership(true);
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
