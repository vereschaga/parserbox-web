<?php

namespace AwardWallet\Engine\turkish\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Information extends \TAccountChecker
{
    public $mailFiles = "turkish/statements/it-66726250.eml, turkish/statements/it-66886274.eml, turkish/statements/it-66926170.eml";
    private $lang = '';
    private $reFrom = ['@mail.turkishairlines.com', '.turkishairlines.com', '@turkishairlines.com'];
    private $reProvider = ['Miles&Smiles'];
    private $reSubject = [
        'Welcome to Miles&Smiles!',
        'Miles&Smiles Transaction Information',
        'Miles&Smiles password changed',
        'card status information',
        'Miles&Smiles Status Information',
    ];
    private $reBody = [
        'en' => [
            [
                'Welcome to Turkish Airlines Miles&Smiles Loyalty Program.',
                'You can earn Miles from your flights with Turkish Airlines',
            ],
            ['Your Current Mileage balance is shown below.', 'Total Miles:'],
            ['Your Miles&Smiles membership account pin code has been successfully changed', 'Please do not share'],
            ['In order to benefit from Miles&Smiles world your', 'membership keeps continue!'],
            ['For detailed information, you can contact our call centre', 'Since you have reached a total of'],
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

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear'))}]",
            null, true, "/^{$this->opt($this->t('Dear'))}\s+([[:alpha:]\s.\-]{2,})/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear'))}]/ancestor::*[1]/following-sibling::*[normalize-space()][1]",
            null, true, "/^[A-Z]{2}\d{5,}$/");

        if ($number) {
            $st->setLogin($number);
            $st->setNumber($number);
        }
        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Miles:'))}]",
            null, true, "/{$this->opt($this->t('Total Miles:'))}\s*([\d.,\s]+)/");

        if (isset($balance)) {
            $st->setBalance($balance);
        } else {
            $st->setNoBalance(true);
        }

        $status = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('membership keeps continue!'))}])[1]",
            null, true, "/(Classic|Classic Plus|Elite|Elite Plus) {$this->opt($this->t('membership keeps continue!'))}/");

        if (isset($status)) {
            $st->addProperty('Status', $status);
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
