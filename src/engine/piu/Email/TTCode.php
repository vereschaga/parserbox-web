<?php

namespace AwardWallet\Engine\piu\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TTCode extends \TAccountChecker
{
    public $mailFiles = "piu/it-33937632.eml";

    public $reFrom = "italotreno.it";
    public $reBody = [
        'it'  => ['Conferma acquisto biglietto', 'Contatto prenotazione'],
        'it2' => ['Conferma acquisto biglietto', 'Contatto della prenotazione'],
    ];
    public $reSubject = [
        'Codice Biglietto Italo Treno',
    ];
    public $lang = '';
    public static $dict = [
        'it' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $this->parseEmail($parser, $email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'www.italotreno.it')] | //a[contains(@href,'italotreno.it')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail($parser, Email $email)
    {
        $r = $email->add()->train();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('CODICE BIGLIETTO'))}]/following::text()[normalize-space(.)!=''][1]"))
            ->traveller($this->http->FindSingleNode("//tr[{$this->starts($this->t('Contatto prenotazione'))}][not(.//tr)][1]", null, true, "#{$this->opt($this->t('Contatto prenotazione'))}[\s:]+(.+)#"));

        $xpath = "//text()[{$this->eq($this->t('Data'))}]/ancestor::table[1][{$this->contains($this->t('Orari'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            // added ", Italy" for google, to help find correct address of stations
            $depName = $this->http->FindSingleNode("./following::text()[string-length(normalize-space(.))>2][1]", $root) . ', Italy';
            $arrName = $this->http->FindSingleNode("./following::text()[string-length(normalize-space(.))>2][2]", $root) . ', railway, Italy';
            $s->departure()->name($depName);
            $s->arrival()->name($arrName);

            $dateDep = $this->http->FindSingleNode("./descendant::tr[2]", $root);
            $timeDep = $this->http->FindSingleNode("./descendant::tr[4]", $root, true, "#^\s*(\d+:\d+.*?)\s*>\s*\d+:\d+.*?\s*$#");

            if (!empty($dateDep) && !empty($timeDep)) {
                if ($dateDep = $this->normalizeDate($dateDep)) {
                    $dateDep = EmailDateHelper::calculateDateRelative($dateDep, $this, $parser, EmailDateHelper::FORMAT_DOT_DATE_YEAR);
                    $s->departure()->date(strtotime($timeDep, $dateDep));
                }
            }
            $dateArr = $this->http->FindSingleNode("./descendant::tr[2]", $root);
            $timeArr = $this->http->FindSingleNode("./descendant::tr[4]", $root, true, "#^\s*\d+:\d+.*?\s*>\s*(\d+:\d+.*?)\s*$#");

            if (!empty($dateArr) && !empty($timeArr)) {
                if ($dateArr = $this->normalizeDate($dateArr)) {
                    $dateArr = EmailDateHelper::calculateDateRelative($dateArr, $this, $parser, EmailDateHelper::FORMAT_DOT_DATE_YEAR);
                    $s->arrival()->date(strtotime($timeArr, $dateArr));
                }
            }

            $xpathFragment1 = "ancestor::td[ following-sibling::td[normalize-space()] ][1]/following-sibling::td[normalize-space()]";

            $number = $this->http->FindSingleNode($xpathFragment1 . "/descendant::text()[{$this->eq($this->t('N.Treno'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space()][2]", $root);
            $cabin = $this->http->FindSingleNode($xpathFragment1 . "/descendant::text()[{$this->eq($this->t('Ambiente'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space()][3]", $root);
            $s->extra()
                ->number($number)
                ->cabin($cabin);

            $wagon = $this->http->FindSingleNode($xpathFragment1 . "/descendant::text()[{$this->eq($this->t('Carrozza'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space()][1]", $root);
            $seat = $this->http->FindSingleNode($xpathFragment1 . "/descendant::text()[{$this->eq($this->t('Posto'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space()][2]", $root);

            if (!empty($wagon) && !empty($seat)) {
                $s->extra()
                    ->car($wagon)
                    ->seat($seat);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 28/02
            '#^\s*(\d+)\/(\d+)\s*$#',
        ];
        $out = [
            '$1.$2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
