<?php

namespace AwardWallet\Engine\hawaiian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EnterStatement extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-540414400.eml, hawaiian/statements/it-116370919.eml, hawaiian/statements/it-116491836.eml, hawaiian/statements/it-61935502.eml, hawaiian/statements/it-74470896.eml, hawaiian/statements/it-74600173.eml";
    private $lang = '';
    private $reFrom = ['.hawaiianairlines.com'];
    private $reProvider = ['Hawaiian Airlines'];
    private $reSubject = [
        'Enter our #',
    ];
    private $reBody = [
        'ja' => [
            'アカウント',
        ],
        'ko' => [
            '본 이메일은',
        ],
        'en' => [
            'This e-mail has been sent to you at ',
            'This email has been sent to you at',
        ],
    ];
    private static $dictionary = [
        'en' => [
            'confirmation code' => ['confirmation code', 'Confirmation Code'],
        ],
        'ko' => [
            ' Acct' => '계정',
        ],
        'ja' => [
            ' Acct' => 'アカウント',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t(' Acct'))}]/ancestor::td[1]");

        if (empty($text)) {
            $text = $this->http->FindSingleNode("//img[contains(@alt, 'Hawaiian Airlines')]/following::text()[{$this->contains($this->t(' Acct'))}][1]/ancestor::td[1]");
        }

        if (!empty($text)) {
            // Mxx Miler | Acct 326132238
            if (preg_match("/^\s*(?<name>.+?)\s*\|\s*{$this->opt($this->t(' Acct'))}\s+(?<number>[\w\-]+)/u", $text, $m)) {
                $st->setNoBalance(true);
                $st->addProperty('Name', $m['name']);
                $st->setNumber($m['number']);
                $st->setLogin($m['number']);
            }
        }

        if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(), 'to view your mileage statement')]"))) {
            $st->setMembership(true);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This e-mail has been sent to you at'))}]", null, true, "/{$this->opt($this->t('This e-mail has been sent to you at'))}\s*(\S+[@]\S+)/");

        if (!empty($login) && empty($st->getLogin())) {
            $st->setLogin(trim($login, '.'));
            $st->setNoBalance(true)
                ->setMembership(true);
        }

        if (empty($st->getNumber())) {
            $login = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Username')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[1]");

            if (!empty($login)) {
                $st->setLogin($login);
            }

            $number = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Username')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[last()]");

            if (!empty($number)) {
                $st->setNumber($number);
            }
        }

        $name = $this->http->FindSingleNode("//div[contains(normalize-space(), 'You may now use your new password to access your account')]/descendant::text()[starts-with(normalize-space(), 'Aloha')]", null, true, "/{$this->opt($this->t('Aloha'))}\s*(\D+)/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('confirmation code'))}]")->length > 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[normalize-space()='HawaiianMiles number']")->length > 0
            || $this->http->XPath->query("//text()[normalize-space()='Thanks for updating your account.']")->length > 0
            || $this->http->XPath->query("//img[contains(@alt, 'HawaiianMiles')]")->length > 0
            || $this->http->XPath->query("//text()[normalize-space()='Please complete your request.']/following::text()[normalize-space()][1][normalize-space()='Reset Password']")->length > 0
            || $this->http->XPath->query("//div[contains(normalize-space(), 'You may now use your new password to access your account')]")->length > 0
            || $this->http->XPath->query("//text()[normalize-space()='to view your mileage statement.']")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), '|') and {$this->contains($this->t(' Acct'))}]")->length > 0) {
            if ($this->assignLang()) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
