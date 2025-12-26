<?php

namespace AwardWallet\Engine\nonni\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "nonni/it-14875793.eml, nonni/it-15411913.eml";

    public $reFrom = "@iperbooking.com";

    public $reBody = [
        'it' => 'RIEPILOGO DATI DELLA PRENOTAZIONE',
    ];
    public $reSubject = [
        ' - Prenotazione Modificata num.',
        ' - Conferma Prenotazione num.',
    ];
    public $lang = '';
    public static $dict = [
        'it' => [],
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
        if ($this->http->XPath->query("//a[contains(@href,'www.nonnihotels.com')]")->length > 0) {
            return $this->assignLang();
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

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Nome:")) . "]/following-sibling::td[1]")
                . ' ' . $this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Cognome:")) . "]/following-sibling::td[1]"), true
            );
        $confs = array_filter($this->http->FindNodes("//text()[normalize-space() = 'SOGGIORNO HOTEL:']/ancestor::tr[1]/following-sibling::tr//text()[normalize-space()][1][contains(., '(cod.')]", null, "#cod\.\s*(\d{4,})\b#"));

        foreach ($confs as $conf) {
            $h->general()->confirmation($conf);
        }
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[contains(., 'Tel.')]/ancestor::td[1][preceding-sibling::td[1]//img]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<name>.+)\n(?<addr>.+)\n\s*Tel\.\s*(?<tel>.+)\s*\n\s*Fax\s*(?<fax>.+)#", $hotelInfo, $m)) {
            $h->hotel()
                ->name(trim($m['name'], ' *'))
                ->address($m['addr'])
                ->phone($m['tel'])
                ->fax($m['fax']);
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[normalize-space() = 'Arrivo:']/following::text()[normalize-space()][1])[1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[normalize-space() = 'Partenza:']/following::text()[normalize-space()][1])[1]")))
            ->rooms(count($this->http->FindNodes("//text()[normalize-space() = 'Arrivo:']/ancestor::td[1][contains(., 'Partenza:')]")))
            ->guests(array_sum($this->http->FindNodes("//text()[normalize-space() = 'Prezzo giornaliero']/ancestor::table[1]//td[contains(.,'Adult') and ./following-sibling::td[2]]", null, "#(\d+)\s*Adult#")))
            ->kids(array_sum($this->http->FindNodes("//text()[normalize-space() = 'Prezzo giornaliero']/ancestor::table[1]//td[contains(., 'bambin') and ./following-sibling::td[2]]", null, "#(\d+)\s*bambin#")))
            ->cancellation($this->http->FindSingleNode("(//text()[normalize-space() = 'Politiche di cancellazione']/ancestor::td[1])[1]", null, true, "#Politiche di cancellazione\s*(.+)#"), true, true)
        ;

        $types = $this->http->FindNodes("//text()[normalize-space() = 'SOGGIORNO HOTEL:']/ancestor::tr[1]/following-sibling::tr[contains(., '(cod.')]//text()[normalize-space()][starts-with(., 'Camera:')]", null, "#:\s*(.+)#");

        foreach ($types as $type) {
            $rm = $h->addRoom();
            $rm->setType($type);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space() = 'Importo Prenotazione:']/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $in = [
            //16/11/2016
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = preg_replace($in, $out, $date);
//        $str = $this->dateStringToEnglish($str);
        return strtotime($str);
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
