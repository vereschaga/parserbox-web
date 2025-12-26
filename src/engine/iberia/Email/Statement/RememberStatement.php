<?php

namespace AwardWallet\Engine\iberia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RememberStatement extends \TAccountChecker
{
    public $mailFiles = "iberia/statements/it-63156919.eml, iberia/statements/it-62243257.eml, iberia/statements/it-62539693.eml, iberia/statements/it-62541243.eml, iberia/statements/it-62541733.eml, iberia/statements/it-62541842.eml, iberia/statements/it-62541988.eml";
    private $lang = '';
    private $dateSendMail = '';
    private $reFrom = ['iberia.com'];
    private $reProvider = ['iberia.com'];
    private $reSubject = [
        ' just as you remember it',
        'Strengthen the protection of your information',
        'Lo bueno siempre llega: packs de viaje desde',
        ', wie Sie es kennen',
        ', comme dans vos souvenirs',
        // de
        ', Fliegen mit Rabatt verschenken',
    ];
    private $reBody = [
        'en' => [
            ['Balance at', 'INFORMATION ON DATA'],
            ['Balance at', 'INFORMACIÓN SOBRE PROTECCIÓN DE DATOS'],
            ['Balance at', 'INFORMATION ON DATA PRIVACY'],
        ],
        'fr' => [
            ['Solde au ', 'INFORMATION ON DATA'],
            ['Solde au', 'INFORMATIONS SUR LA'],
        ],
        'de' => [
            ['Stand am', 'INFORMATIONEN ZUM'],
            ['Ausgleichen auf', 'INFORMATIONEN ZUM'],
        ],
        'es' => [
            ['Saldo a', 'INFORMACIÓN SOBRE'],
            ['Saldo a', 'INFORMATION ON DATA'],
            ['Saldo a', 'INFORMACION SOBRE'],
            ['Saldo a', 'INFORMACIÓN SOBRE PROTECCIÓN DE DATOS'],
        ],
        'pt' => [
            ['Saldo a', 'INFORMAÇŐES SOBRE'],
            ['Saldo a', 'INFORMAÇÕES SOBRE'],
            ['Saldo a', 'INFORMAÇÃO SOBRE'],
            ['Saldo em', 'INFORMAÇÃO SOBRE'],
        ],
        'it' => [
            ['Saldo al', 'INFORMAZIONI SULLA'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
        'fr' => [
            'Balance at' => 'Solde au',
        ],
        'de' => [
            'Balance at' => ['Stand am', 'Ausgleichen auf'],
        ],
        'es' => [
            'Balance at' => ['Saldo a'],
        ],
        'pt' => [
            'Balance at' => ['Saldo a', 'Saldo em'],
        ],
        'it' => [
            'Balance at' => ['Saldo al'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $this->dateSendMail = $parser->getHeader('date');
        //$this->logger->debug($parser->getHeader('date'));
        $st = $email->add()->statement();

        $root = join("\n",
            $this->http->FindNodes("(//text()[{$this->contains($this->t('Balance at'))}])[1]/ancestor::table[{$this->contains($this->t('IB '))}][1]//text()"));
        $this->logger->debug($root);

        /*
        Neelu Modali
        IB 90862665
        309
        Balance at 04/17/2019

        Antonio López Sánchez
        IB 90009663
        0 Avios
        Saldo a 26/01/2019

        Chianglung Yen
        IB 77516888
        0
        Balance at
        */
        $preg1 = "/^\s*(?<name>[[:alpha:]\s\-]{4,})\s+(?<number>IB\s*\d+)\s+"
            . "(?<balance>-?[\d.,\s]+)(?:{$this->opt($this->t('Avios'))})?"
            . "\s+{$this->opt($this->t('Balance at'))}\s+(?<date>.+?\d{4})?/us";

        /*
        Pablo Beltran Gonzalez
        156.681 Avios
        IB 50920610
        Saldo a 06/06/2020

        Antonio López Sánchez
        719 Avios
        IB 69701498
        Saldo a 17/07/2019
         */
        $preg2 = "/^\s*(?<name>[[:alpha:]\s\-]{4,})\s+(?<balance>-?[\d.,\s]+)(?:{$this->opt($this->t('Avios'))})?"
            . "\s+(?<number>IB\s*\d+)\s+\s+{$this->opt($this->t('Balance at'))}(?<date>\s+.+?\d{4})?/us";

        /*
        Ronald Echandi Steinvorth
        IB 76650993
        Saldo a
         */
        $preg3 = "/^\s*(?<name>[[:alpha:]\s\-]{4,})\s+"
            . "(?<number>IB\s*\d+)\s+{$this->opt($this->t('Balance at'))}\s*$/us";

//         $this->logger->debug('$preg1 = '.print_r( $preg1,true));
//         $this->logger->debug('$preg2 = '.print_r( $preg2,true));
//         $this->logger->debug('$preg3 = '.print_r( $preg2,true));

        if (preg_match($preg1, $root, $m) || preg_match($preg2, $root, $m)) {
            $st->setLogin($m['number']);
            $st->setNumber($m['number']);

            if ($this->lang == 'en') {
                $st->setBalance(str_replace([',', '.'], ['', ''], $m['balance']));
            } elseif ($this->lang == 'es') {
                $st->setBalance(str_replace('.', '', $m['balance']));
            } else {
                $st->setBalance(trim($m['balance']));
            }
            $st->addProperty('Name', $m['name']);

            if (!empty($m['date'])) {
                $st->setBalanceDate(strtotime($this->normalizeDate($m['date']), false));
            }
        }

        if (preg_match($preg3, $root, $m)) {
            $st->setLogin($m['number']);
            $st->setNumber($m['number']);

            $st->setNoBalance(true);

            $st->addProperty('Name', $m['name']);
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

    private function normalizeDate($date)
    {
        $date = trim($date);

        switch ($this->lang) {
            case 'fr':
            case 'de':
            case 'es':
                // $this->logger->notice($this->ModifyDateFormat($date));
                return $this->ModifyDateFormat($date);

                break;
        }

        if (preg_match("/^(\d+).(\d+).(\d{4})$/", $date, $m)) {
            if (($m[2] > 12) || (strtotime(str_replace("/", ".", $date)) - strtotime($this->dateSendMail)) > 0) {
                $date = $m[2] . '.' . $m[1] . '.' . $m[3];
            } elseif ($m[2] <= 12) {
                $date = str_replace("/", ".", $date);
            }

            return $date;
        }

        return $date;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            foreach ($value as $val) {
                if ($this->http->XPath->query("//text()[{$this->contains($val[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($val[1])}]")->length > 0) {
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
