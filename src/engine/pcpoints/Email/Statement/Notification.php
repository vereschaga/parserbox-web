<?php

namespace AwardWallet\Engine\pcpoints\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "pcpoints/statements/it-85764431.eml, pcpoints/statements/it-86246091.eml";
    public $subjects = [
        '/Your password was successfully updated/',
        '/Your PC Optimum account profile was updated/',
        '/Le profil de votre compte PC Optimum a été mis à jour/',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Hi '        => ['Dear', 'Hi '],
            'detectBody' => [
                'Your password was successfully changed',
                'Your account profile was recently changed',
                'Congratulations, you\'ve successfully registered',
            ],
        ],
        "fr" => [
            'Hi '        => 'Bonjour',
            'detectBody' => [
                'Le profil de votre compte a récemment été modifié',
            ],
        ],
    ];

    public $detectLang = [
        "en" => ["Hi"],
        "fr" => ["Bonjour"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.pcoptimum.ca') !== false) {
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
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'PC Optimum')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.pcoptimum\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, true, "/^{$this->opt($this->t('Hi '))}(\D+)\,$/");
        $st->addProperty('Name', trim($name, ','));

        $st->setNoBalance(true);

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

    private function assignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                foreach ($reBody as $word) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
