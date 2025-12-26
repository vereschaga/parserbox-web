<?php

namespace AwardWallet\Engine\rentacar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourStatement extends \TAccountChecker
{
    public $mailFiles = "rentacar/statements/it-62074098.eml, rentacar/statements/it-61117183.eml, rentacar/statements/it-61490698.eml, rentacar/statements/it-66096481.eml";
    private $lang = '';
    private $reFrom = ['@enterprise.com', '@email.enterprise.com'];
    private $reProvider = ['Enterprise'];
    private $reSubject = [
        ', please review your statement',
        'Your Enterprise Plus Membership',
    ];
    private $reBody = [
        'en' => [
            'Your Enterprise Plus Points and activity eStatement for',
            'In the meantime, please make note of your Enterprise Plus® number',
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Points balance'        => ['Points balance', 'Points Balance', 'POINTS BALANCE'],
            'Your account activity' => ['Your account activity', 'Your Account Activity', 'YOUR ACCOUNT ACTIVITY'],
            'As of'                 => ['As of', 'As Of', 'AS OF'],
        ],
    ];

    private $enDatesInverted = false;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $st = $email->add()->statement();
        $xpathTopLine = "//*[ (self::tr or self::td) and count(*[{$xpathNoEmpty}])=2 and *[{$xpathNoEmpty}][2][{$this->contains($this->t('Member #:'))}] ]/*[{$xpathNoEmpty}]";
        $name = $this->http->FindSingleNode($xpathTopLine . '[1]', null, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");

        if ($name) {
            $st->addProperty('Name', $name);

            if (($str = $this->http->FindSingleNode($xpathTopLine . '[2]'))) {
                $number = $this->http->FindPreg("/:\s*([\w\-]+)/", false, $str);

                $date = $this->http->FindSingleNode("//td[not(.//td) and {$this->starts($this->t('Your account activity'))}]", null, true, "/{$this->opt($this->t('As of'))}\s*(\d{1,2}\/\d{1,2}\/\d{4})$/");

                if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $date, $dateMatches)) {
                    foreach ($dateMatches[1] as $simpleDate) {
                        if ($simpleDate > 12) {
                            $this->enDatesInverted = true;

                            break;
                        }
                    }
                }

                $st->setNumber($number)
                    ->setLogin($number)
                    ->addProperty('TierLevel', $this->http->FindPreg("/(\w+)\s+{$this->opt($this->t('Member #:'))}/", false, $str))
                    ->setBalance($this->normalizeAmount($this->http->FindSingleNode("(//node()[{$this->eq($this->t('Points balance'))}]/following::node()[normalize-space()][1])[1]", null, true, "/^\d[,.\'\d\s]*$/")))
                    ->parseBalanceDate($this->normalizeDate($date));
            }
        } elseif (($number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Member Number:'))}]/ancestor::span[1]/following-sibling::*[last()]", null, true, '/^[-A-Z\d]{5,}$/'))) {
            // it-61490698.eml
            $st->setNumber($number)
                ->setLogin($number)
                ->setNoBalance(true);
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

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        // 09/13/2019
        $in[0] = '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';

        return preg_replace($in, $out, $text);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
