<?php

namespace AwardWallet\Engine\wizz\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class DiscountClub extends \TAccountChecker
{
    public $mailFiles = "wizz/statements/it-65931918.eml, wizz/statements/it-65991832.eml, wizz/statements/it-66237174.eml, wizz/statements/it-66263906.eml, wizz/statements/it-92582435.eml";
    public $subjects = [
        '/$WIZZ Discount Club$/',
    ];

    public $detectLang = [
        'en' => ['Your subscription to', 'Renew your', 'Click on the link'],
        'it' => 'Benvenuto al',
        'ru' => 'Продлите свое членство',
        'pl' => 'Odnów swoje członkostwo',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Your subscription to WIZZ Discount Club has ended' => [
                'Your subscription to WIZZ Discount Club has ended',
                'Renew your WIZZ Discount Club membership',
                'You have requested password reset for your account at',
            ],

            'As a Wizz Discount Club member you saved' => [
                'As a Wizz Discount Club member you saved',
                'We hope that you have enjoyed all the great benefits of being a Club Member',
                'We have noticed that you still have not renewed your membership',
                'Click on the link below to get you in the door',
            ],
        ],
        "it" => [
            'Dear'                                              => 'Ciao',
            'Your subscription to WIZZ Discount Club has ended' => 'Benvenuto al WIZZ Discount Club',
            'As a Wizz Discount Club member you saved'          => 'Accedi sempre su wizzair.com per prenotare alle tariffe',
        ],

        "ru" => [
            'Dear'                                              => 'Уважаемый(-ая)',
            'Your subscription to WIZZ Discount Club has ended' => 'Продлите свое членство WIZZ Discount Club',
            'As a Wizz Discount Club member you saved'          => 'Мы заметили, что Вы еще не продлили свое членство',
            'Wizz Air newsletters at'                           => 'Wizz Air на адрес',
        ],
        "pl" => [
            'Dear'                                              => 'Witaj',
            'Your subscription to WIZZ Discount Club has ended' => 'Odnów swoje członkostwo w WIZZ Discount Club',
            'As a Wizz Discount Club member you saved'          => 'Zauważyliśmy, że Twoje członkostwo nie zostało jeszcze odnowione',
            'Wizz Air newsletters at'                           => 'Wizz Air pod adresem',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wizzair.com') !== false) {
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
        if ($this->detectLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Wizz Air')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your subscription to WIZZ Discount Club has ended'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('As a Wizz Discount Club member you saved'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wizzair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ',!'));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Wizz Air newsletters at'))}]", null, true, "/{$this->opt($this->t('Wizz Air newsletters at'))}\s*(\S+[@]\S+\.\S+)\./");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $st->setNoBalance(true);

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

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $word) {
                    if (stripos($body, $word) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
