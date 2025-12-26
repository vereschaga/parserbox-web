<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class EmailFlight extends \TAccountChecker
{
    public $mailFiles = "tripact/it-40584056.eml, tripact/it-41231188.eml, tripact/it-84114444.eml";

    public $reFrom = ["@tripactions.com"];
    public $reBody = [
        'en' => ['Flight to'],
    ];
    public $reSubject = [
        '#\S+\@\S+$#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Airline Confirmation Code:' => 'Airline Confirmation Code:',
            'Booking Status:'            => 'Booking Status:',
        ],
    ];
    private $keywordProv = 'TripActions';

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
        if ($this->http->XPath->query("//img[contains(@src,'.tripactions.com')]| //a[contains(@href,'.tripactions.com')]")->length > 0) {
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
                    && preg_match($reSubject, $headers["subject"]) > 0
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

        $r->ota()
            ->phone($phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('for live travel support from our travel team 24/7'))}]/preceding::text()[normalize-space()!=''][1]"),
                $this->t('for live travel support from our travel team 24/7'));
        $phones[] = str_replace(' ', '', $phone);
        $node = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('International support:'))}]/ancestor::div[1]/descendant::text()[normalize-space()!='']"));
        $node = $this->re("#{$this->t('International support:')}(.+)#s", $node);

        if (preg_match_all("#^(.+?):\n([\+\-\d\(\) ]+)\n#m", $node, $m, PREG_SET_ORDER)) {
            foreach ($m as $value) {
                $phone = str_replace(' ', '', $value[2]);

                if (!in_array($phone, $phones)) {
                    $r->ota()
                        ->phone($phone, $this->t('International support:') . $value[1]);
                    $phones[] = $phone;
                }
            }
        }
        $otaConfiramtion = $this->nextTd($this->t('Record Locator:'));

        if (empty($otaConfiramtion)) {
            $otaConfiramtion = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Record Locator:'))}]/following::text()[normalize-space()][1]");
        }

        $r->ota()->confirmation($otaConfiramtion);

        // Brussels Airlines=T3XSJD, Scandinavian Airlines=T3XSJD, Aer Lingus=28HUZJ
        $airlines = $this->nextTd($this->t('Airline Confirmation Code:'));

        if (empty($airlines)) {
            $airlines = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline Confirmation Code:'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match_all('/\=?([A-Z\d]{5,6})/u', $airlines, $m)) {
            foreach (array_unique($m[1]) as $airline) {
                $r->general()->confirmation($airline);
            }
        } else {
            $r->general()->confirmation($this->nextTd($this->t('Airline Confirmation Code:')));
        }

        $status = $this->nextTd($this->t('Booking Status:'));

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status:'))}]/following::text()[normalize-space()][1]");
        }

        $r->general()
            ->status($status);

        $travellers = $this->nextTds($this->t('Passenger Name:'));

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Name:'))}]/following::text()[normalize-space()][1]");
        }

        $r->general()
            ->travellers($travellers, true)
            ->status($status);

        if (in_array($status, (array) $this->t('Canceled'))) {
            $r->general()->cancelled();
        }

        //$this->logger->debug(var_export($this->nextTds($this->t('e-Ticket(s)')), true));
        $tickets = [];

        foreach ($this->nextTds($this->t('e-Ticket(s)')) as $item) {
            if ($item == 'Pending') {
                continue;
            }

            foreach (preg_split('/,\s*/', $item) as $ticket) {
                $tickets[] = $ticket;
            }
        }

        if (!empty($tickets)) {
            $r->issued()->tickets(array_unique($tickets), false);
        }

        $acc = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Name:'))}]/ancestor::tr[1]/following::tr[normalize-space()!=''][1][not({$this->starts($this->t('Flight Number:'))})]/td[2]");

        if (empty($acc)) {
            $acc = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Name:'))}]/following::text()[{$this->eq($this->t('MileagePlus'))}]/following::text()[normalize-space()][1]");
        }

        if (!empty($acc)) {
            $r->program()
                ->accounts($acc, false);
        }

        $xpath = "//text()[{$this->eq($this->t('Departs:'))}]/ancestor::table[{$this->contains($this->t('Flight'))}][1]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();

//            if ($this->http->XPath->query("./preceding::text()[normalize-space()!=''][2][{$this->eq($this->t('Duration:'))}]", $root)->length === 0) {
//                $this->logger->alert('other format');
//                return false;
//            }

            // date flight
            if ($d = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ',')][1]", $root))) {
                $date = $d;
            }

            // duration
            $s->extra()->duration($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), 'm')][1]", $root));

            $ar = $this->http->FindSingleNode('descendant::text()[starts-with(normalize-space(.),"Flight")]/ancestor::*[1]/preceding-sibling::span', $root);

            // airline
            // cabin
            // seats
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight'))}]/ancestor::*[1]",
                $root, false, "#{$this->t('Flight')}\s*(.+)#");

            if (preg_match("#^(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d+)\W+(?<cabin>.+)#", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $s->extra()->cabin($m['cabin']);
                $flight = $m['al'] . $m['fn'];
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Flight Number:'))}]//ancestor::tr[1][./td[translate(.,' ','')='{$flight}']]/following-sibling::tr[position()<4][{$this->starts($this->t('Seat:'))}]/td[2]",
                    null, "#^(\d+[A-z])\b#"));

                if (count($seats) == 0) {
                    $seats = array_filter($this->http->FindNodes("//text()[normalize-space(.)=\"Flight Number:\"]/following::text()[contains(normalize-space(), '{$s->getFlightNumber()}')]/following::text()[{$this->starts($this->t('Seat:'))}][1]/following::text()[normalize-space()][1]/ancestor::span[1]/descendant::text()[normalize-space()]",
                        null, "#^(\d+[A-z])\b#"));
                }

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // departure
            $depInfo = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Departs:'))}]/ancestor::tr[1]/descendant::text()[string-length(normalize-space())>2][position()>1]",
                $root);

            if (count($depInfo) !== 3) { //it-84114444.eml
                $depInfo = explode(",", $this->re("/{$this->opt($this->t('Departs:'))}\,(.+)\,{$this->opt($this->t('Arrives:'))}/", implode(",", $depInfo)));
            }

            if (count($depInfo) !== 3) {
                $this->logger->debug("other format departs");

                return false;
            }
            $s->departure()
                ->date(strtotime($depInfo[0], $date))
                ->name($depInfo[1])
                ->code($depInfo[2]);

            // arrival
            $arrInfo = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Arrives:'))}]/ancestor::tr[1]/descendant::text()[string-length(normalize-space())>2][position()>1]",
                $root);

            if (count($arrInfo) !== 3) { //it-84114444.eml
                $arrInfo = explode(",", $this->re("/{$this->opt($this->t('Arrives:'))}\,(.+)/", implode(",", $arrInfo)));
            }

            if (count($arrInfo) !== 3) {
                $this->logger->debug("other format arrives");

                return false;
            }
            $s->arrival()
                ->date(strtotime($arrInfo[0], $date))
                ->name($arrInfo[1])
                ->code($arrInfo[2]);
        }

        $total = $this->nextTd($this->t('Total Price:'));

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][1]");
        }

        $cost = $this->nextTd($this->t('Ticket Price:'));

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket Price:'))}]/following::text()[normalize-space()][1]");
        }

        $currency = $this->http->FindSingleNode("//text()[normalize-space(.)='Ticket Price:']/ancestor::tr[1]/descendant::td[last()]", null, true, "/([A-Z]{3})/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()][2]");
        }

        $tax = $this->nextTd($this->t('Taxes:'));

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes:'))}]/following::text()[normalize-space()][1]");
        }

        $r->price()
            ->total(cost($total))
            ->cost(cost($cost))
            ->tax(cost($tax))
            ->currency($currency);

        return true;
    }

    private function nextTd($field, $root = null, $onlyFirst = false): ?string
    {
        if ($onlyFirst) {
            return $this->http->FindSingleNode("(//text()[{$this->eq($field)}])[1]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
        } else {
            return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
        }
    }

    private function nextTds($field, $root = null): array
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
            $root);
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wed, Jun 19, 2019
            '#^(\w+),\s+(\w+)\s+(\d+),\s+(\d{4})$#u',
        ];
        $out = [
            '$3 $2 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations or changes made after (\d{1,2}:\d{2}\s*[ap]m) \(.+?\) on (\d{1,2} \w+ \d{2,4}|\w+ \d{1,2}, \d{4}) or no-shows are subject to a (?:property|hotel) fee equal to/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->dateStringToEnglish(str_replace(".", '', $m[2]) . ', ' . str_replace(".",
                        ':', $m[1]))));
        }

        $h->booked()
            ->parseNonRefundable("#^You will be charged \d+\% of the total price if you cancel your booking.$#");
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
            if (isset($words['Airline Confirmation Code:'], $words['Booking Status:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Airline Confirmation Code:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking Status:'])}]")->length > 0
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

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
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
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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
