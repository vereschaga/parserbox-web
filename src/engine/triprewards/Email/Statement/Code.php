<?php

namespace AwardWallet\Engine\triprewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-654399650.eml, triprewards/it-843401306.eml, triprewards/it-846778811.eml, triprewards/it-846792444.eml, triprewards/statements/it-637095634.eml";
    public $subjects = [
        'Your Wyndham Verification Code',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['Verification Code'],
        "fr" => ['Code de vérification'],
        "de" => ['Verifizierungscode'],
        "pt" => ['Código de Verificação'],
        "es" => ['Código de verificación'],
    ];

    public static $dictionary = [
        "en" => [
        ],
        "fr" => [
            'Wyndham Verification Code'              => 'Code de vérification Wyndham',
            'Rewards account:'                       => 'Wyndham Rewards :',
            'Action required to verify your account' => 'Une action est requise pour vérifier votre compte.',
        ],
        "de" => [
            'Wyndham Verification Code'              => 'Verifizierungscode für Wyndham',
            'Rewards account:'                       => 'Wyndham Rewards-Konto angefordert haben:',
            'Action required to verify your account' => 'Zur Verifizierung Ihres Kontos ist Ihre Mitwirkung erforderlich.',
        ],
        "pt" => [
            'Wyndham Verification Code'              => 'Código de Verificação Wyndham',
            'Rewards account:'                       => 'Wyndham Rewards:',
            'Action required to verify your account' => 'Ação necessária para verificar sua conta.',
        ],
        "es" => [
            'Wyndham Verification Code'              => 'Código de verificación de Wyndham',
            'Rewards account:'                       => 'Wyndham Rewards:',
            'Action required to verify your account' => 'Acción necesaria para verificar tu cuenta.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wyndhamhotels.com') !== false) {
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
        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Wyndham Verification Code'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t("Rewards account:"))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Action required to verify your account'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wyndhamhotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $code = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rewards account:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{6})$/");

        if (!empty($code)) {
            $oc = $email->add()->oneTimeCode();
            $oc->setCode($code);
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang=> $detects) {
            foreach ($detects as $detect) {
                if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
