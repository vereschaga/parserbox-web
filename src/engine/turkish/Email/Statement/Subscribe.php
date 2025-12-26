<?php

namespace AwardWallet\Engine\turkish\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Subscribe extends \TAccountChecker
{
    public $mailFiles = "turkish/statements/it-72792442.eml, turkish/statements/it-72795540.eml, turkish/statements/it-72852532.eml";

    private $detectFrom = 'milesandsmiles@milesandsmiles.turkishairlines.com';

    private $lang = '';
    private static $dictionary = [
        'en' => [
            'Click here to unsubscribe on Miles&Smiles membership account' => 'Click here to unsubscribe on Miles&Smiles membership account',
            "Don't want to receive this type of email"                     => "Don't want to receive this type of email",
        ],
        'tr' => [
            'Click here to unsubscribe on Miles&Smiles membership account' => 'Miles&Smiles üyelik hesabınızdaki iletişim tercihlerinden iptal etmek için tıklayın',
            "Don't want to receive this type of email"                     => ["E-posta adresinize bültenlerimizin gönderilmesini istemiyorsanız lütfen tıklayınız", "E-posta adresinize bültenlerimizin gönderilmesini istemiyorsanız tıklayın"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Click here to unsubscribe on Miles&Smiles membership account'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Click here to unsubscribe on Miles&Smiles membership account'])}][1]")->length > 0
            ) {
                $st = $email->add()->statement();

                $st->setMembership(true);

                break;
            }

            if (!empty($dict["Don't want to receive this type of email"])
                && $this->http->XPath->query("//*[{$this->contains($dict["Don't want to receive this type of email"])}][1]")->length > 0
            ) {
                $email->setIsJunk(true);

                break;
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Click here to unsubscribe on Miles&Smiles membership account'])
                    && $this->http->XPath->query("//*[{$this->contains($dict['Click here to unsubscribe on Miles&Smiles membership account'])}][1]")->length > 0
            ) {
                return true;
            }

            if (!empty($dict['Don\'t want to receive this type of email'])
                    && $this->http->XPath->query("//*[{$this->contains($dict['Don\'t want to receive this type of email'])}][1]")->length > 0
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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
}
