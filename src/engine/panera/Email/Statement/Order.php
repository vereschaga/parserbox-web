<?php

namespace AwardWallet\Engine\panera\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Order extends \TAccountChecker
{
    public $mailFiles = "panera/statements/it-65695570.eml";
    private $lang = '';
    private $reFrom = ['panerabread.com'];
    private $reProvider = ['MyPanera'];
    private $reSubject = [
        'Your Panera Order -',
    ];
    private $reBody = [
        'en' => [
            ['Thanks for your order!', 'MyPanera Number:'],
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

        $card = $this->http->FindSingleNode("//text()[{$this->contains($this->t('MyPanera Number:'))}]/ancestor::b[1]/following-sibling::text()[normalize-space()][1]",
            null, true, "/^\d+$/");

        if (isset($card)) {
            $st->setNumber($card);
            $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Available Rewards:'))}]/ancestor::b[1]/following-sibling::text()[normalize-space()][1]",
                null, true, self::BALANCE_REGEXP);

            if (isset($balance)) {
                $st->setBalance($balance);
            } else {
                $st->setNoBalance(true);
            }
            $st->setMembership(true);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
