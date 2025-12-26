<?php

namespace AwardWallet\Engine\groupon\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Member extends \TAccountChecker
{
    public $mailFiles = "groupon/it-74031280.eml, groupon/it-74157228.eml";

    private $detectFrom = ['.groupon.com', '.grouponmail.'];
    private $detectBody = [
        'en' => [
            'because you recently made a Groupon purchase',
            'Recently added Wishlist Deals',
        ],
    ];
    private $lang = 'en';
    private static $dictionary = [
        'en' => [],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
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

        if ($this->http->XPath->query("//a[{$this->contains(['groupon.com', 'grouponmail.'], '@href')}]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setMembership(true);

        $useremail = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email confirmation was sent to'))}]", null, true,
            "/This email confirmation was sent to (\S+@\S+\.\S+) /");

        if (empty($useremail)) {
            $useremail = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are receiving this email because'))}]/ancestor::td[1]",
                null, true, "/You are receiving this email because (\S+@\S+\.\S+) /");
        }

        $st->setLogin($useremail);

        $st->setNoBalance(true);

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

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
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        $firstText = $this->http->FindSingleNode("(//img[contains(@src, 'logo')]/following::text()[normalize-space()][1])[1]");

        if (preg_match("/^(?:For [A-Z][a-z]+|[A-Z][a-z]+'s .+? Deals) \| [A-Z][a-z]+ \d{1,2}, 202\d$/", $firstText)) {
            $this->lang = 'en';

            return true;
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
