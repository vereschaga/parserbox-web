<?php

namespace AwardWallet\Engine\worldpoints\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ticket extends \TAccountChecker
{
    public $mailFiles = "worldpoints/it-67241800.eml";
    private $lang = '';
    private $reFrom = ['@heathrowexpress.com'];
    private $reProvider = ['Heathrow Express'];
    private $reSubject = [
        'Your Booking Confirmation',
    ];
    private $reBody = [
        'en' => [
            ['HERE ARE YOUR BOOKING DETAILS', 'Travelling from:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $roots = $this->http->XPath->query("//text()[{$this->contains($this->t('Reference code:'))}]/ancestor::table[2]");

        foreach ($roots as $root) {
            $t = $email->add()->train();
            $conf = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Reference code:'))}]/following-sibling::strong[1]", $root);
            $t->general()->confirmation($conf);

            // £18.50
            $price = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Price:'))}]/following-sibling::strong[1]", $root);

            if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]+)/', $price, $matches)) {
                $t->price()
                    ->currency($this->normalizeCurrency($matches['currency']))
                    ->total($matches['amount']);
            }

            // Paddington > Heathrow
            $names = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Travelling from:'))}]/following-sibling::strong[1]", $root);

            if (preg_match("/^(.+?) > (.+?)$/", $names, $m)) {
                $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Travel Date:'))}]/following-sibling::strong[1]", $root);
                $s = $t->addSegment();
                $s->departure()->name($m[1])->date2($date);
                $s->arrival()->name($m[2])->noDate();
                $s->extra()->noNumber();
            }
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

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
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
