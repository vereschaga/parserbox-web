<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmedReservation extends \TAccountChecker
{
    public $mailFiles = "hotels/it-30395942.eml";

    public $reFrom = ["@hotels.com"];
    public $reBody = [
        'en' => ['Reservation Summary', 'Contact Information for the'],
    ];
    public $reSubject = [
        'Confirmed Hotels.com Reservation -',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'adults' => ['adults', 'adult'],
            'fees'   => ['EXTRA PERSON FEE', 'TAXES AND FEES'],
        ],
    ];
    private $keywordProv = 'Hotels.com';

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
        if ($this->http->XPath->query("//a[contains(@href,'hotels.com')]")->length > 0) {
            return $this->assignLang();
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        $mainConfNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION NUMBER'))}]", null,
            false, "/{$this->opt($this->t('RESERVATION NUMBER'))}\s*:\s*([\w\-]+)\s*$/");

        $r->general()
            ->status($this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION STATUS'))}]", null,
                false, "/{$this->opt($this->t('RESERVATION STATUS'))}\s*:\s*(.+)\s*$/"));
        $rootsType = $this->http->XPath->query("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[1][{$this->contains($this->t('Reserved for'))}]/ancestor::table[1]/descendant::tr[position()>1]");
        $rootsRate = $this->http->XPath->query("//text()[{$this->eq($this->t('Date'))}]/ancestor::tr[1][{$this->contains($this->t('Room 1'))}]/ancestor::table[1]/descendant::tr[position()>1]");

        foreach ($rootsType as $rootType) {
            $num = $this->http->FindSingleNode("./td[1]", $rootType);
            $col = $num + 1;
            $room = $r->addRoom();
            $room->setType($this->http->FindSingleNode("./td[2]", $rootType));
            $rates = [];

            foreach ($rootsRate as $rootRate) {
                $rate = $this->http->FindSingleNode("./td[{$col}]", $rootRate);
                $sum = $this->getTotalCurrency($rate);
                $curRate = $sum['Currency'];
                $rates[] = $sum['Total'];
            }

            if (!empty($rates) && isset($curRate)) {
                $rate = array_sum($rates) / count($rates);
                $room->setRate("Avg rate: " . $rate . ' ' . $curRate . ' per night');
            }
            $pax = $this->http->FindSingleNode("./td[3]", $rootType);
            $confNo = $this->http->FindSingleNode("./td[4]", $rootType);
            $descr = $this->t('Reserved for') . ' ' . $pax;
            $r->general()
                ->confirmation($confNo, $descr, $mainConfNo === $confNo);
            $r->general()
                ->traveller($pax);
        }
        $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('CHECK-IN DATE'))}]", null, false,
            "/{$this->opt($this->t('CHECK-IN DATE'))}\s*:\s*(.+)\s*$/");
        $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('CHECK-OUT DATE'))}]", null, false,
            "/{$this->opt($this->t('CHECK-OUT DATE'))}\s*:\s*(.+)\s*$/");
        $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('NUMBER OF GUESTS'))}]", null, false,
            "/{$this->opt($this->t('NUMBER OF GUESTS'))}\s*:\s*(.+)\s*$/");
        $r->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
            ->guests($this->re("/(\d+)\s*{$this->opt($this->t('adults'))}/", $guests))
            ->kids($this->re("/(\d+)\s*{$this->opt($this->t('child'))}/", $guests), false, true)
            ->rooms($rootsType->length);

        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('ROOM PRICE'))}]",
            null, false, "/{$this->opt($this->t('ROOM PRICE'))}\s*:\s*(.+)\s*$/"));
        $r->price()
            ->cost($sum['Total'])
            ->currency($sum['Currency']);
        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL PRICE'))}]",
            null, false, "/{$this->opt($this->t('TOTAL PRICE'))}\s*:\s*(.+)\s*$/"));
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);
        $fees = (array) $this->t('fees');

        foreach ($fees as $fee) {
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($fee)}]",
                null, false, "/{$this->opt($fee)}\s*:\s*(.+)\s*$/"));

            if (!empty($sum['Total'])) {
                $r->price()
                    ->fee($fee, $sum['Total']);
            }
        }

        $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'ADDRESS') and ./preceding::text()[normalize-space()!=''][1][starts-with(normalize-space(),'Hotel')]]",
            null, false, "/{$this->opt($this->t('ADDRESS'))}[:\s]+(.+)/");
        $city = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'ADDRESS') and ./preceding::text()[normalize-space()!=''][1][starts-with(normalize-space(),'Hotel')]]/following::text()[normalize-space()!=''][1][starts-with(normalize-space(),'CITY')]",
            null, false, "/{$this->opt($this->t('CITY'))}[:\s]+(.+)/");
        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'ADDRESS')]/preceding::text()[normalize-space()!=''][1][starts-with(normalize-space(),'Hotel')]",
                null, false, "/{$this->opt($this->t('Hotel'))}\s+(.+)/"))
            ->address($city . ', ' . $address);

        if (preg_match("/^(\d+)\s+(.+)/", $city, $m)) {
            $da = $r->hotel()->detailed();
            $da
                ->address($address)
                ->city($m[2])
                ->zip($m[1]);
        }

        $r->general()
            ->cancellation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy and Penalty'))}]/following::text()[normalize-space()!='' and not(contains(.,'--------------------------------'))][1]"));

        if (!empty($node = $r->getCancellation())) {
            $this->detectDeadLine($r, $node);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/we are required to pass on: Cancellations or changes made after (?<time>\d+:\d+\s*(?:[ap]m)?|\d+\s*[ap]m) \(.+\) on (?<date>\w+ \d+, \d{4})(?:, or no-shows,)? are subject to/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }

        $h->booked()
            ->parseNonRefundable("#^This rate is non-refundable.#");
    }

    private function normalizeDate($date)
    {
        $in = [
            //28/12/2018
            '#^(\d+)\/(\d+)\/(\d{4})$#u',
        ];
        $out = [
            '$3-$2-$1',
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
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
