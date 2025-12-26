<?php

namespace AwardWallet\Engine\national\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LoginOnly extends \TAccountChecker
{
    public $mailFiles = "national/statements/it-62617248.eml";
    public $from = '/[@.]nationalcar\.com$/';

    public $subjects = [
        '/^Your Emerald Club Username$/',
    ];

    public $provDetect = 'National Car Rental';

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("//a[starts-with(normalize-space(), 'SIGN IN NOW')]/ancestor::table[1]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Your username:')]/following::text()[string-length()>3][1]");
        $st->setLogin($login);

        $st->setNoBalance(true);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@nationalcar.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$this->provDetect}')]")->length > 0) {
            if (
                $this->http->XPath->query("//text()[contains(normalize-space(), 'Your Emerald Club Username')]")->count() > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Your username:')]")->count() > 0
                && $this->http->XPath->query("//a[starts-with(normalize-space(), 'SIGN IN NOW')]")->count() > 0
                && $this->http->XPath->query("//a[starts-with(normalize-space(), 'RESET MY PASSWORD')]")->count() > 0
            ) {
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
}
