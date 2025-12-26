<?php

namespace AwardWallet\Engine\kellogg\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EmailCodeVerification extends \TAccountChecker
{
    public $mailFiles = "kellogg/statements/it-108592445.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/\bkelloggs[.]com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@account.na.kelloggs.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'Email Code Verification') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Kellogg NA Co')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'and mention Keyword \"ACCESS CODE\" when reporting it to us')]")->length > 0;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/^Hi\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
            $st->setNoBalance(true);
        }

        $code = $this->http->FindSingleNode("//text()[normalize-space()='Please use the following code to access your account.']/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
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
}
