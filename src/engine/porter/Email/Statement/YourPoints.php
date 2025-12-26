<?php

namespace AwardWallet\Engine\porter\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourPoints extends \TAccountChecker
{
    public $mailFiles = "porter/it-76205695.eml, porter/it-76224676.eml, porter/it-76227338.eml, porter/it-76253453.eml";
    public $subjects = [
        '/^Your points expire in /',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['To next tier', 'Qualifying spend', 'bonus points'],
        'fr' => ['Dépenses minimales'],
    ];

    public static $dictionary = [
        "en" => [
            'To next tier'               => ['To next tier', 'Qualifying spend'],
            'You deserve to be rewarded' => ['You deserve to be rewarded', 'A final reminder to reward yourself'],
        ],
        "fr" => [
            //detectByBody, words from email
            'Points' => ['Points', 'points'],

            'Points balance:'            => 'Points VIPorter :',
            'To next tier'               => 'Dépenses minimales',
            'This email was sent to:'    => 'Ce courriel a été envoyé à',
            'You deserve to be rewarded' => 'Vous nous manquez beaucoup !',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.flyporter.com') !== false) {
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
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'VIPorter')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Points'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to:'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.flyporter\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $info = $this->http->FindNodes("//text()[{$this->contains($this->t('Points:'))}]/ancestor::table[1]/descendant::text()[normalize-space()][string-length()>3]");

        if (count($info) === 4) {
            $st->addProperty('Name', $info[0]);
            $st->setBalance($this->re("/{$this->opt($this->t('Points:'))}\s*(\d+)/", $info[2]));
        } else {
            $info2 = $this->http->FindNodes("//text()[{$this->contains($this->t('Points balance:'))}]/ancestor::table[1]/descendant::text()[normalize-space()][string-length()>3]");

            if (count($info2) === 4) {
                $st->addProperty('Name', $info2[0]);
                $st->setBalance(str_replace(' ', '', $this->re("/{$this->opt($this->t('Points balance:'))}\s*([\d\s]+)/", $info2[1])));
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('You deserve to be rewarded'))}]")->length > 0) {
            $st->setNoBalance(true);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to:'))}]/following::text()[contains(normalize-space(), '@')][1]");

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
