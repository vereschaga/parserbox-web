<?php

namespace AwardWallet\Engine\norwegian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CashPoints extends \TAccountChecker
{
    public $mailFiles = "norwegian/it-70097719.eml, norwegian/statements/it-66500047.eml, norwegian/statements/it-66533255.eml, norwegian/statements/it-70104901.eml, norwegian/statements/it-70331581.eml, norwegian/statements/it-70422451.eml, norwegian/statements/it-76971152.eml, norwegian/statements/it-77261388.eml, norwegian/statements/it-77270150.eml";
    private $lang = 'en';
    private $reFrom = ['.norwegianreward.com', '@norwegianreward.com'];
    private $reProvider = ['Norwegian', 'CashPoints'];
    private $reSubject = [
    ];
    private $reBody = [
        'es' => [
            ['Número de Reward', 'vuelos registrados'],
        ],
        'sv' => [
            ['Detta mailet skickades till', 'flygningar'],
            ['Ditt saldo', 'Flygningar'],
        ],
        'no' => [
            ['Denne e-posten ble sendt til', 'flyvninger'],
        ],
        'en' => [
            ['You are receiving this email at', 'Reward Number:'],
            ['This email was sent to', 'Reward Number:'],
            ['Current balance', 'registered flights'],
            ['Your total balance', 'Active Rewards'],
            ['Expiring this year', 'Your total balance'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'loginDetect'        => ['You are receiving this email', 'This email was sent to'],
            'Reward number:'     => ['Reward number:', 'Reward Number:'],
            'registered flights' => ['registered flights', 'flights registered'],
            'Current balance:'   => ['Current balance:', 'Your total balance:'],
            'Hi'                 => ['Hi', 'Dear'],
        ],
        'es' => [
            'Hi'                 => '¡Hola',
            'Reward number:'     => 'Número de Reward:',
            'loginDetect'        => 'Recibís este email en',
            'flights'            => 'vuelos',
            'registered flights' => 'vuelos registrados',
            'You have'           => 'Tenés',
        ],
        'sv' => [
            'Hi'                 => 'Hej',
            'Reward number:'     => 'Reward-nummer:',
            'loginDetect'        => ['Detta mailet skickades till', 'Det här mailet skickades till', 'Du får det här mailet till'],
            'flights'            => ['flygningar', 'Flygningar'],
            'Current balance:'   => 'Ditt saldo:',
            'registered flights' => 'Flygningar senaste',
        ],
        'no' => [
            'Hi'             => 'Hei',
            'Reward number:' => 'Reward-nummer:',
            'loginDetect'    => ['Denne e-posten ble sendt til', 'Du mottar denne e-posten på'],
            'flights'        => 'flyvninger',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $pattern = "/{$this->opt($this->t('loginDetect'))}(?:.+\s|\s)([_a-z0-9\-.]+@[_a-z0-9\-.]+)/i";
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('loginDetect'))}]/ancestor::*[1]",
            null, true, $pattern);

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('loginDetect'))}]/following::text()[normalize-space()][1]",
                null, true, "/^(\S+[@]\S+\.\S+)$/u");
        }

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reward number:'))}]/following::*[normalize-space()][1]",
            null, true, "/^\d+$/");

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CashPoints'))}]/preceding-sibling::a[normalize-space()][1]",
            null, true, self::BALANCE_REGEXP);

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You have'))}]", null, true, "/(\d+)\s*{$this->opt($this->t('CashPoints'))}/");
        }

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Current balance:'))}]/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*{$this->opt($this->t('CashPoints'))}/");
        }

        $flights = $this->http->FindSingleNode("//text()[{$this->eq($this->t('flights'))}]/preceding-sibling::a[normalize-space()][1]",
            null, true, self::BALANCE_REGEXP);

        if ($flights == null) {
            $flights = $this->http->FindSingleNode("//text()[{$this->contains($this->t('registered flights'))}]", null, true, "/(\d+)\s*{$this->opt($this->t('registered flights'))}/");
        }

        if ($flights == null) {
            $flights = $this->http->FindSingleNode("//text()[{$this->contains($this->t('registered flights'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        }

        $expire = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Expire '))}]", null, true, "/{$this->opt($this->t('Expire '))}(\d+\.\d+\.\d{4})/"));
        $expireBalance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Expiring this year:'))}]/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*{$this->opt($this->t('CashPoints'))}/");

        if (isset($login, $balance)) {
            $st->setLogin(trim($login, '.'));

            if (isset($number)) {
                $st->setNumber($number);
            }
            $st->setBalance($balance);
            $st->setMembership(true);

            if (preg_match("/{$this->opt($this->t('Hi'))},?\s+([[:alpha:]\s.\-]{3,})!?/", $parser->getHeader('subject'), $m)) {
                $st->addProperty('Name', $m[1]);
            } elseif ($name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/{$this->opt($this->t('Hi'))}([[:alpha:]\s.\-]{3,})\,/")) {
                $st->addProperty('Name', $name);
            }

            if (isset($flights)) {
                $st->addProperty('Flights', $flights);
            }

            if (!empty($expire)) {
                $st->setExpirationDate($expire);
            }

            if (!empty($expireBalance)) {
                $st->addProperty('ExpiringBalance', $expireBalance);
            }
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

        foreach ($this->reSubject as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
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

    private function normalizeDate($date)
    {
        $in = [
            //18 jul, 2019
            '#^(\d+)\.(\d+)\.(\d{4})$#u',
        ];
        $out = [
            '$2.$1.$3',
        ];
        $str = strtotime(preg_replace($in, $out, $date));

        return $str;
    }
}
