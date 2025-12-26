<?php

namespace AwardWallet\Engine\gamestop\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Points extends \TAccountChecker
{
    public $mailFiles = "gamestop/statements/it-66577047.eml";
    private $lang = '';
    private $reFrom = ['.gamestop.com'];
    private $reProvider = ['loyal GameStop customer'];
    private $reSubject = [
        'Additional PS5 pre-orders will be available at GameStop tomorrow',
    ];
    private $reBody = [
        'en' => [
            [
                'This message was sent to you because you have elected to receive email communications from GameStop.',
                'Membership:',
            ],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello,'))}]",
            null, true, "/^{$this->opt($this->t('Hello,'))}\s+([[:upper:]][[:alpha:]\s.\-]{2,})/");

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Membership:'))}]/following-sibling::span[normalize-space()][1]",
            null, true, '/[\w\s]{4,}/');

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points:'))}]/following-sibling::span[normalize-space()][1]",
            null, true, self::BALANCE_REGEXP);

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We sent this email to'))}]/following-sibling::a",
            null, true, '/[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+/');

        if (!empty($name) && !empty($status) && !empty($login)) {
            $st->addProperty('Name', $name);
            $st->addProperty('Membership', $status);
            $st->setLogin($login);
            $st->setBalance(str_replace(',', '', $balance));
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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
}
