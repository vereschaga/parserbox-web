<?php

namespace AwardWallet\Engine\jetstar\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HolidaysBooking extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-473241054.eml, jetstar/it-473407176.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking reference' => 'Booking reference',
            'Holiday Total'     => 'Holiday Total',
        ],
    ];

    private $detectFrom = "booking@jetstarholidays.com";
    private $detectSubject = [
        // en
        'Jetstar Holidays Booking Confirmation: #',
    ];
    private $detectBody = [
        'en' => [
            "You're going on your holiday",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]jetstarholidays\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'Jetstar Holidays') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.jetstarholidays.com'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['Jetstar Airways Pty Ltd'])}]")->length === 0
        ) {
            return false;
        } else {
            return $this->assignLang();
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking reference"], $dict["Holiday Total"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking reference'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Holiday Total'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Booking reference'))}]/following-sibling::tr[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");
        $email->ota()
            ->confirmation($conf);

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flight Details'))}]")->length > 0) {
            $this->parseFlight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Hotel Details'))}]")->length > 0) {
            $this->parseHotel($email);
        }

        // Price
        $priceTable = $this->http->FindNodes("//tr[count(*) > 2 and td[normalize-space()][1][{$this->starts($this->t('Total ('))}]]/*[normalize-space()]");
        $this->logger->debug('$priceTable = ' . print_r($priceTable, true));

        if (preg_match("/\((\D+)\)\s*$/", $priceTable[0], $m)) {
            $currency = $this->currency($m[1]);
            $email->price()
                ->currency($currency);

            if (count($priceTable) === 5 && preg_match("/^\s*\d[\d,. ]*$/", $priceTable[1])) {
                $email->price()
                    ->spentAwards($priceTable[1]);
                unset($priceTable[1]);
                $priceTable = array_values($priceTable);
            }
            $this->logger->debug('$priceTable = ' . print_r($priceTable, true));

            if (count($priceTable) === 4) {
                $priceTable = preg_replace("/^\D{0,5}(\d[,. \d]*?)\D{0,5}/", '$1', $priceTable);
                $email->price()
                    ->total(PriceHelper::parse($priceTable[3], $currency))
                    ->cost(PriceHelper::parse($priceTable[1], $currency))
                    ->tax(PriceHelper::parse($priceTable[2], $currency))
                ;
            } else {
                $total = $this->http->FindSingleNode("//text()[normalize-space()='Payment']/preceding::text()[starts-with(normalize-space(), 'Total')][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

                if ($total !== null) {
                    $email->price()
                        ->total(PriceHelper::parse($total, $currency));
                }

                $tax = $this->http->FindSingleNode("//text()[normalize-space()='Payment']/preceding::text()[starts-with(normalize-space(), 'GST')][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

                if ($tax !== null) {
                    $email->price()
                        ->tax(PriceHelper::parse($tax, $currency));
                }

                $cost = $this->http->FindSingleNode("//text()[normalize-space()='Payment']/preceding::text()[starts-with(normalize-space(), 'Excl. GST')][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)$/");

                if ($cost !== null) {
                    $email->price()
                        ->cost(PriceHelper::parse($cost, $currency));
                }
            }
        } else {
            $email->price()
                ->total(null);
        }

        return true;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight Details'))}]/following::text()[{$this->eq($this->t('Flight reference'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");
        $f->general()
            ->confirmation($conf)
            ->travellers($this->http->FindNodes("//tr[td[{$this->eq($this->t('Name'))}] and td[{$this->eq($this->t('Age'))}]]/following-sibling::tr/td[1]",
                null, "/^\s*(?:(?:MR|MS|MRS|MISS|MSTR|DR)\s+)?(.+)/i"));

        $accounts = array_filter($this->http->FindNodes("//tr[td[(normalize-space(.)='Name')] and td[(normalize-space(.)='Age')]]/following-sibling::tr/td[3]", null, "/^(\d{5,})$/"));

        if (count($accounts) > 0) {
            foreach ($accounts as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]", null, true, "/^\s*(?:(?:MR|MS|MRS|MISS|MSTR|DR)\s+)?(.+)/i");

                if (!empty($pax)) {
                    $f->addAccountNumber($account, false, $pax);
                } else {
                    $f->addAccountNumber($account, false);
                }
            }
        }

        // $xpath = "//tr[td[normalize-space()='Departs'] and td[normalize-space()='Arrives']]/ancestor::tr[2]";
        $xpath = "//tr[td[{$this->eq($this->t('Departs'))}] and td[{$this->eq($this->t('Arrives'))}]]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("ancestor-or-self::tr[count(.//tr[not(.//tr)][normalize-space()]) = 1]/following-sibling::tr[2]/descendant::td[normalize-space()][1]", $root);
            $this->logger->debug('$flight = ' . print_r($flight, true));

            if (preg_match("/^s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\b/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            // Departure, Arrival
            $date = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root);

            $route = $this->http->FindSingleNode("preceding::text()[normalize-space()][2]", $root);

            if (preg_match("/^(.+){$this->opt($this->t(' to '))}(.+)$/", $route, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1]);
                $s->arrival()
                    ->noCode()
                    ->name($m[2]);
            }

            $this->logger->debug('$date = ' . print_r($date, true));

            if (!empty($date)) {
                $dTime = $this->http->FindSingleNode("ancestor-or-self::tr[count(.//tr[not(.//tr)][normalize-space()]) = 1]/following-sibling::tr[1]/descendant::tr[count(td[normalize-space()]) = 2]/td[normalize-space()][1]", $root);
                $this->logger->debug('$dTime = ' . print_r($dTime, true));
                $s->departure()
                    ->date($dTime ? strtotime($date . ', ' . $dTime) : null);
                $aTime = $this->http->FindSingleNode("ancestor-or-self::tr[count(.//tr[not(.//tr)][normalize-space()]) = 1]/following-sibling::tr[1]/descendant::tr[count(td[normalize-space()]) = 2]/td[normalize-space()][2]", $root);
                $s->arrival()
                    ->date($aTime ? strtotime($date . ', ' . $aTime) : null);
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("td[normalize-space()][2]", $root, true, '/^.*\d.*$/'));
        }
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//tr[td[{$this->eq($this->t('Name'))}] and td[{$this->eq($this->t('Age'))}]]/following-sibling::tr/td[1]",
                null, "/^\s*(?:(?:MR|MS|MRS|MISS|MSTR|DR)\s+)?(.+)/i"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[{$this->eq($this->t('Check-in'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()][1]/ancestor::td[1]"))
        ;

        $this->logger->debug('$date = ' . print_r($this->http->FindSingleNode("//tr[{$this->eq($this->t('Check-in'))}]/following-sibling::tr[normalize-space()][1]"), true));

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate(implode(' ', $this->http->FindNodes("//tr[{$this->eq($this->t('Check-in'))}]/following-sibling::tr[normalize-space()][1]//text()[normalize-space()]"))))
            ->checkOut($this->normalizeDate(implode(' ', $this->http->FindNodes("//tr[{$this->eq($this->t('Check-out'))}]/following-sibling::tr[normalize-space()][1]//text()[normalize-space()]"))))
        ;

        // Rooms
        // no examples for 2 or more rooms
        $types = $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[{$this->eq($this->t('Description'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]");
        $descrs = $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Details'))}]/following::tr[{$this->eq($this->t('Description'))}]/following-sibling::tr[normalize-space()][1]");

        if (count($types) == count($descrs)) {
            foreach ($types as $i => $type) {
                $h->addRoom()
                    ->setType($type)
                    ->setDescription($descrs[$i]);
            }
        } elseif (!empty($types)) {
            foreach ($types as $type) {
                $h->addRoom()
                    ->setType($type);
            }
        } elseif (!empty($descrs)) {
            foreach ($descrs as $descr) {
                $h->addRoom()
                    ->setDescription($descr);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // Mon 04 Sep, 2023 Before 10:00 AM
            '/^\s*[[:alpha:]\-]+\s+(\d{1,2})\s+([[:alpha:]]+)\s*[,\s]+\s*(\d{4})\s+(?:\D*\s+)?(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '$AUD' => 'AUD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
