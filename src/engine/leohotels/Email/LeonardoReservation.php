<?php

namespace AwardWallet\Engine\leohotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class LeonardoReservation extends \TAccountChecker
{
    public $mailFiles = "leohotels/it-13445291.eml";

    public $reFrom = "@leonardo-hotels.com";
    public $reBody = [
        'en' => ['Booking Information', 'Reservation Confirmation No.'],
    ];
    public $reSubject = [
        'Leonardo Reservation Confirmation',
        'Your booking confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
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
        if ($this->http->XPath->query("//a[contains(@href,'leonardo-hotels.com')]")->length > 0) {
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
        $r = $email->add()->hotel();

        if (empty($confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Confirmation No.'))}]",
            null, true, "#{$this->opt($this->t('Reservation Confirmation No.'))}[\s:]+([A-Z\d]{5,})#"))
        ) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Confirmation No.'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        }
        $r->general()
            ->confirmation($confNo, $this->t('Reservation Confirmation No.'));

        $root = $this->http->XPath->query("//text()[{$this->eq($this->t('Booking Information'))}]/ancestor::table[1]");

        if ($root->length === 1) {
            $r->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Arrival'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root->item(0))))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Departure'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root->item(0))))
                ->rooms($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Rooms reserved'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root->item(0)));
        }

        $root = $this->http->XPath->query("//text()[{$this->eq($this->t('Guest Information'))}]/ancestor::table[1]");

        if ($root->length === 1) {
            $r->general()
                ->traveller($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('First Name'))}]/ancestor::td[1]/following-sibling::td[1]",
                        $root->item(0)) . ' ' . $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Last Name'))}]/ancestor::td[1]/following-sibling::td[1]",
                        $root->item(0)));

            if (!empty($acc = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('AdvantageCLUB ID'))}]/ancestor::td[1]/following-sibling::td[1]",
                $root->item(0)))
            ) {
                $r->program()
                    ->account($acc, false);
            }
        }

        $roots = $this->http->XPath->query("//text()[{$this->starts($this->t('Room '))}]/ancestor::table[1]");

        foreach ($roots as $root) {
            $rm = $r->addRoom();
            $rm
                ->setType($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Room '))}]/following::text()[normalize-space(.)!=''][1]",
                    $root))
                ->setRateType($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Rate description'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root));
            $days = $this->http->XPath->query("./descendant::text()[contains(.,'Day')]/ancestor::tr[1]/following-sibling::tr",
                    $root)->length - 1;

            if ($days < 1) {
                $this->logger->debug('other format');

                return null;
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[contains(.,'Day')]/ancestor::tr[1]/following-sibling::tr[last()]/td[last()]",
                $root));

            if (!empty($tot['Total'])) {
                $rm->setRate('Avg: ' . $tot['Currency'] . ' ' . round($tot['Total'] / $days, 2) . ' per night');
            }
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Reservation Price'))}]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Confirmation No.'))}]/preceding::tr[string-length(normalize-space(.))>2][position()=2][not(.//img)]");

        $r->hotel()
            ->name($this->re("#^(.+?)\s*•#", $node))
            ->address(trim(preg_replace("#\s*•#u", ',', $this->re("#^.+?\s*•\s*(.+?)\s+[TF]:#", $node)), " ,"))
            ->phone(trim($this->re("#\s+T:\s*([\d \(\)\-\+]+)#", $node)))
            ->fax(trim($this->re("#\s+F:\s*([\d \(\)\-\+]+)#", $node)), true);

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
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

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
