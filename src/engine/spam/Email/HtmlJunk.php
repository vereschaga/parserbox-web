<?php

namespace AwardWallet\Engine\spam\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HtmlJunk extends \TAccountChecker
{
    public $mailFiles = "spam/it-12390380.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//h3")->length == 7
            && $this->http->XPath->query("//h3[normalize-space()='PREPARE TO APPLY']")->length == 1
            && $this->http->XPath->query("//h3[normalize-space()='GET READY TO GO']")->length == 1
            && $this->http->XPath->query("//h3[normalize-space()='DURING YOUR STUDY ABROAD YEAR']")->length == 1
            && $this->http->XPath->query("//h3[normalize-space()='SUMMER SCHOOLS']")->length == 1
            && $this->http->XPath->query("//h3[normalize-space()='RETURNING TO LEEDS']")->length == 1
            && $this->http->XPath->query("//h3[normalize-space()='STUDY ABROAD HANDBOOKS AND DOCUMENTS']")->length == 1
            && $this->http->XPath->query("//h3[normalize-space()='GRADUATE STUDY ABROAD OPPORTUNITIES']")->length == 1;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
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
        return count(self::$dictionary);
    }
}
