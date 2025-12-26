<?php

namespace AwardWallet\Engine\hotelslash\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Junk extends \TAccountChecker
{
    public $mailFiles = "hotelslash/it-735134824.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Near:'   => 'Near:',
            'Guests:' => 'Guests:',
        ],
    ];

    private $detectFrom = "hotelslash.com";
    private $detectSubject = [
        // en
        'Discounted rates for your ',
    ];
    private $detectBody = [
        'en' => [
            'Below are the details of your current quote request.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hotelslash\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.hotelslash.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['©  HotelSlash', 'Your friends at HotelSlash'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        if ($this->detectEmailByHeaders($parser->getHeaders()) && $this->detectEmailByBody($parser)) {
            $email->setIsJunk(true, 'Not confirmed reservation');
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Near:"]) && !empty($dict["Guests:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Near:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Guests:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
