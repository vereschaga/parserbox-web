<?php

namespace AwardWallet\Engine\skyair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class PurchaseConfirmationJunk extends \TAccountChecker
{
    public $mailFiles = "skyair/it-166678187.eml";

    public $detectFrom = "@skyairline.c"; //skyairline.com or @skyairline.cl
    public $detectSubject = [
        // en
        " - Your SKY purchase confirmation", // 81UKGS - Your SKY purchase confirmation
    ];

    public static $dictionary = [
        "en" => [
            "Your transaction has been completed successfully, we attach the detail of your flight(s)" => "Your transaction has been completed successfully, we attach the detail of your flight(s)",
        ],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {

        if ($this->http->XPath->query("//a[contains(@href, '.skyairline.')]")->length < 2) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your transaction has been completed successfully, we attach the detail of your flight(s)'])
                && $this->http->XPath->query("//text()[".$this->eq($dict['Your transaction has been completed successfully, we attach the detail of your flight(s)'])."]")->length > 0
                && $this->http->XPath->query("//text()[".$this->eq($dict['Your transaction has been completed successfully, we attach the detail of your flight(s)'])."]/following::text()[contains(translate(.,'0123456789', '##########'),'#:##')]")->length == 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) === true && $this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
