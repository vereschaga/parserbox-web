<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

//!!!если разруливать с парсерами по вложению, то аккуратно. лучше суммы не дособрать, чем в мусор письмо отправить, т.к. коды сервисом не определятся
class LATAMAirlines extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-23687323.eml, lanpass/it-28668082.eml";

    public $reFrom = ["sales@bo.lan.com"];
    public $reBody = [
        'pt' => ['Chegada', 'Voo'],
        'es' => ['Llegada', 'Vuelo'],
    ];
    public $reSubject = [
        'Você resgatou sua passagem. Obrigada por escolher LATAM Airlines',
        'Su canje ha sido exitoso. Gracias por escoger LATAM Airlines',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [
        ],
        'es' => [
            'Número LATAM Fidelidade' => 'Número de pasajero frecuente',
            'Voo'                     => 'Vuelo',
            'Chegada'                 => 'Llegada',
            'Data'                    => 'Fecha',
            'Saida'                   => 'Ida',
            'Classe'                  => 'Cabina',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'mail.latam.com/pub/link_multiplus')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag || stripos($headers["subject"], 'LATAM Airlines') !== false) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Código de reserva'))}]/following::text()[normalize-space()!=''][1]"));
        $rootPax = $this->http->XPath->query("//text()[{$this->eq($this->t('Tipo'))}]/ancestor::*[{$this->contains($this->t('E-ticket'))}][1]");
        $accNums = [];

        foreach ($rootPax as $root) {
            $r->general()
                ->traveller($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Tipo'))}]/preceding::text()[normalize-space()!=''][1]",
                    $root));
            $r->issued()
                ->ticket($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('E-ticket'))}]/following::text()[normalize-space()!=''][1]",
                    $root), false);

            if (!empty($acc = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Número LATAM Fidelidade'))}]/following::text()[normalize-space()!=''][1]",
                $root, false, "/\d+/"))
            ) {
                $accNums[] = $acc;
            }
        }
        $accNums = array_unique($accNums);

        if (count($accNums) > 0) {
            $r->program()
                ->accounts($accNums, false);
        }

        $xpath = "//text()[{$this->eq($this->t('Voo'))}]/ancestor::*[{$this->contains($this->t('Chegada'))}][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Voo'))}]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^(\w{2})\s*(\d+)\b/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/{$this->opt($this->t('Operado por'))}\s*(.+?)\s*(?:para|$)/", $node, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }
            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Data'))}]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]",
                $root));
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Saida'))}]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^(\d+:\d+)\s*(.+)\s*\(([A-Z]{3})\)/", $node, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date))
                    ->name($m[2])
                    ->code($m[3]);
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Chegada'))}]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^(\d+:\d+)\s*(.+)\s*\(([A-Z]{3})\)/", $node, $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date))
                    ->name($m[2])
                    ->code($m[3]);
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Classe'))}]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^(.+?)\s*(?:\-([A-Z]{1,2}))$/", $node, $m)) {
                $s->extra()->cabin($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->extra()->bookingCode($m[2]);
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sexta-feira 07 dezembro 2018
            '#^([\-\w]+)\s+(\d+)\s+(\w+)\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $3 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
