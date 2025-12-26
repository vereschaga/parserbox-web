<?php

namespace AwardWallet\Engine\pcpoints\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CurrentBalance extends \TAccountChecker
{
    public $mailFiles = "pcpoints/statements/it-85769722.eml, pcpoints/statements/it-85770675.eml, pcpoints/statements/it-85775800.eml, pcpoints/statements/it-85938889.eml, pcpoints/statements/it-86251016.eml";
    public $subjects = [
        '/Load your offers before you shop/',
        '/Free Tastes Good/',
        '/Points Days are on now! Are you ready to earn on these everyday/',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
        ],
        "fr" => [
            'Hey'                   => 'Bonjour',
            'Offer available until' => ["Offre en vigueur jusqu’au", "Valable au", "calculés jusqu’à 48 heures"],
            "You can redeem"        => "Vous pouvez échanger",

            "Your current points balance is"       => ["Votre solde de points", "est actuellement de"],
            "Your current PC"                      => "Votre solde de points PC",
            "You are receiving this email because" => "Vous recevez ce courriel parce que vous êtes un membre",
            "getting personalized"                 => "vos offres personnalisées",
        ],
    ];

    public $detectLang = [
        "en" => ["Your current"],
        "fr" => ["Votre solde"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.pcoptimum.ca') !== false) {
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
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your current points balance is'))}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->contains($this->t('You can redeem'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('Offer available until'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('Points balance and redemption'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('Your current PC'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.pcoptimum\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('getting personalized'))}]", null, true, "/^(\D+)\,\s*{$this->opt($this->t('getting personalized'))}/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]", null, true, "/^{$this->opt($this->t('Hey'))}(\D+)\,$/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your current points balance is'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\,[†]?\^?$/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are receiving this email because'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('You are receiving this email because'))}\s*(\S+[@]\S+\.\S+)/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are receiving this email because'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('You are receiving this email because'))}.+\s(\S+[@]\S+\.\S+)/");
        }

        if (!empty($login)) {
            $st->setLogin(trim($login, '.'))
                ->setNumber(trim($login, '.'));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your current PC'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\,\.\*\s]+)/");

        if (trim($balance) == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your current PC'))}]/following::text()[contains(normalize-space(), 'points')][1]/ancestor::tr[1]", null, true, "/([\d\.\,\s]+)\s*points\S*$/");
        }

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your current points balance is'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your current points balance is'))}.+?\s*\:?\s*([\d\.\,\s]+)(?:[*]+|\†|\^)/");
        }
        $st->setBalance(str_replace([',', '*', ' ', '†', '^'], '', $balance));

        $redemable = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You can redeem'))}]/ancestor::tr[1]/following::*[1]", null, true, "/([\d\.\,]+)\s*dollar/");

        if (empty($redemable)) {
            $redemable = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You can redeem'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('You can redeem'))}\s*\S\s*([\d\.\,]+)\s*\S/");
        }

        if (!empty($redemable)) {
            $st->addProperty('RedeemableValue', $redemable);
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
