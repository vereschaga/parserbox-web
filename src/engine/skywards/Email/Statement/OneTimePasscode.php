<?php

namespace AwardWallet\Engine\skywards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimePasscode extends \TAccountChecker
{
    public $mailFiles = "skywards/statements/it-107440395.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'do-not-reply@accounts.emirates.email') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'Your one-time passcode') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emirates.email\b/i', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'Your-Emirates-Skywards-password')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your one-time passcode')]")->length > 0;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/^Dear\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setNoBalance(true);

        $code = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your passcode is :')]/ancestor::tr[1]", null, true, "/Your passcode is\s*\:\s*(\d{5,})/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }
}
