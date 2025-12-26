<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ScheduleChng extends \TAccountChecker
{
    // +1 bcd - fr
    public $mailFiles = "volotea/it-41633483.eml, volotea/it-42078878.eml, volotea/it-42216928.eml, volotea/it-61226777.eml, volotea/it-94829826.eml";

    public $reFrom = ["@changes.volotea.com"];
    public $reBody = [
        'en' => ['We regret to inform you that for operational reasons', 'We regret to inform that due to operational reasons'],
        'es' => ['Sentimos comunicarle que por motivos operacionales'],
        'pt' => ['Lamentamos informá-lo(a) que por razões operacionais'],
        'fr' => ['de vous informer que pour des raisons'],
        'it' => ['Siamo spiacenti di informarla che'],
    ];
    public $reSubject = [
        'Schedule changes made on your booking',
        'Cambios de fecha en su reserva',
        'Changements d\'horaire de votre réservation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Dear '                                  => 'Dear ',
            'your booking under confirmation number' => ['your booking under confirmation number', 'your flight under confirmation number'],
        ],
        'es' => [
            'Dear '                                  => 'Estimado/a ',
            'your booking under confirmation number' => 'su reserva con número de confirmación',
            'modified',
        ],
        'pt' => [
            'Dear '                                  => 'Caro(a) ',
            'your booking under confirmation number' => 'sua reserva com o número de confirmação',
        ],
        'fr' => [
            'Dear '                                  => 'Cher(e) ',
            'your booking under confirmation number' => 'les horaires des vols de votre',
        ],
        'it' => [
            'Dear '                                  => 'Gentile ',
            'your booking under confirmation number' => 'sua prenotazione con il numero di conferma',
            'Number'                                 => 'Numero di volo',
        ],
    ];
    private $keywordProv = 'Volotea';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Volotea' or contains(@src,'.volotea.com')] | //a[contains(@href,'.volotea.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking under confirmation number'))}]/following::text()[normalize-space()!=''][1]"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, false,
                "#{$this->opt($this->t('Dear '))}\s*(.+?),#"), false);

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking under confirmation number'))}]/following::text()[normalize-space()!=''][2]",
            null, false, "#,?([\w\s]+)[?.]#u");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('we have been forced to reschedule'))}]",
                null, false, "#we have been forced to (\w+)#u");
        }

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        $time = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$time}]/ancestor::td/preceding-sibling::td[normalize-space()!=''][count(.//text()[{$time}])>=2]/following-sibling::td[normalize-space()!=''][1]//text()[{$time}]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $i => $root) {
            $s = $r->addSegment();

            $rowsDep = $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()!='']", $root);

            if (count($rowsDep) !== 3 && count($rowsDep) !== 4) {
                $this->logger->debug($i . ' segment: other format departure');

                return false;
            }
            $rowsArr = $this->http->FindNodes("./descendant::td[last()]/descendant::text()[normalize-space()!='']",
                $root);

            if (count($rowsArr) !== 3 && count($rowsArr) !== 4) {
                $this->logger->debug($i . ' segment: other format arrival');

                return false;
            }

            if (count($rowsArr) === 3) {
                $s->departure()
                    ->name($rowsDep[0])
                    ->date($this->normalizeDate($rowsDep[1] . ', ' . $rowsDep[2]));
                $s->arrival()
                    ->name($rowsArr[0])
                    ->date($this->normalizeDate($rowsArr[1] . ', ' . $rowsArr[2]));
                $node = $this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][1]", $root);

                if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)\s*.\s*([A-Z]{3})\s*.\s*([A-Z]{3})$#u", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                    $s->departure()->code($m[3]);
                    $s->arrival()->code($m[4]);
                }
            }

            if (count($rowsArr) === 4) {
                $s->departure()
                    ->name($rowsDep[1]);

                $s->arrival()
                    ->name($rowsArr[1]);

                $node = $this->http->FindSingleNode("./preceding::table[1]", $root);

                if (preg_match("/{$this->opt($this->t('Number'))}([\d\/]+)\s+.\s+([A-Z]{3})\s+.\s+([A-Z]{3})\s+.\s+([A-Z\d]{2})([\d]{2,4})$/u", $node, $m)
                ) {
                    $depDate = str_replace("/", ".", $m[1]) . ', ' . $rowsDep[3];

                    $s->departure()
                        ->code($m[2])
                        ->date(strtotime($depDate));

                    $arrDate = str_replace("/", ".", $m[1]) . ', ' . $rowsArr[3];
                    $s->arrival()
                        ->code($m[3])
                        ->date(strtotime($arrDate));

                    $s->airline()
                        ->name($m[4])
                        ->number($m[5]);
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //26/09/2019, 21:00
            '#^(\d+)\/(\d+)\/(\d{4}),\s*(\d+:\d+)$#',
        ];
        $out = [
            '$3-$2-$1, $4',
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['your booking under confirmation number'], $words['Dear '])) {
                if (($this->http->XPath->query("//*[{$this->contains($words['your booking under confirmation number'][0])}]")->length > 0
                        || $this->http->XPath->query("//*[{$this->contains($words['your booking under confirmation number'][1])}]")->length > 0)
                    && $this->http->XPath->query("//*[{$this->contains($words['Dear '])}]")->length > 0
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
