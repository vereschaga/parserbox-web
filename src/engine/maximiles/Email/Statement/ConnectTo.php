<?php

namespace AwardWallet\Engine\maximiles\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConnectTo extends \TAccountChecker
{
    public $mailFiles = "maximiles/it-1.eml, maximiles/it-2.eml";
    public $subjects = [
        'A new device is trying to connect to your account',
        'Connexion inhabituelle sur votre compte',
    ];

    public $fromEmails = [
        '@maximiles.com',
        '@maximiles.co.uk',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => 'Your Maximiles login',
        'fr' => 'Votre identifiant',
    ];

    public static $dictionary = [
        "en" => [
            //'Hello' => [''],
            //'Your Maximiles login' => [''],
            //'your Maximiles account:' => [''],
        ],
        "fr" => [
            'Hello'                   => ['Bonjour'],
            'Your Maximiles login'    => ['Votre identifiant'],
            'your Maximiles account:' => ['compte Maximiles :'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->fromEmails as $fromEmail) {
            if (isset($headers['from']) && stripos($headers['from'], $fromEmail) !== false) {
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Maximiles'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('your Maximiles account:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Maximiles login'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->fromEmails as $fromEmail) {
            if (stripos($from, $fromEmail) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Maximiles login'))}]/preceding::text()[{$this->starts($this->t('Hello'))}][1]", null, true, "/^{$this->opt($this->t('Hello'))}\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Maximiles login'))}]", null, true, "/{$this->opt($this->t('Your Maximiles login'))}\s*(\S+[@]\S+)/");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $st->setNoBalance(true);

        $code = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your Maximiles account:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($code)) {
            $c = $email->add()->oneTimeCode();
            $c->setCode($code);
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

    public function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $detect) {
            if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
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
