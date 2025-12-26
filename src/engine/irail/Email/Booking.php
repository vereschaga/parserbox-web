<?php

namespace AwardWallet\Engine\irail\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "irail/it-36781224.eml, irail/it-37270676.eml, irail/it-663531420.eml";

    public $reFrom = ["irishrail.ie"];
    public $reBody = [
        'en'  => ['Payment information', 'This is not a ticke'],
        'en2' => ['Click here to view, amend or cancel your booking', 'This is not a ticket'],
    ];
    public $reSubject = [
        '#Thank you for booking with Iarnród Éireann$#u',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Full Name'                => 'Full Name',
            'booking reference number' => ['booking reference number', 'Booking Number'],
            'Your trip'                => ['Your trip', 'Outward', 'Return'],
        ],
    ];
    private $keywordProv = ['Irish Rail', 'Iarnród Éireann'];

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
        if ($this->http->XPath->query("//img[contains(@src,'irishrail.ie')] | //a[contains(@href,'irishrail.ie')] | //text()[contains(.,'irishrail.ie')]")->length > 0
            && $this->detectBody($this->http->Response['body'])
        ) {
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
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || (preg_match("#{$this->opt($this->keywordProv)}#", $headers["subject"]) > 0)
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
        $r = $email->add()->train();

        $service = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Thank you for booking with'))}])[1]",
            null, false, "#{$this->opt($this->t('Thank you for booking with'))}\s+(.+)#u");

        $ticket = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Collection No:'))}]",
            null, false, "#:\s+(.+)#");

        if (!empty($ticket)) {
            $r->setTicketNumbers([
                $ticket,
            ], false);
        }

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference number'))}]",
            null, false, "#{$this->opt($this->t('booking reference number'))}[ ]+(\d{6,})#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference number'))}]/following::text()[normalize-space()][1]",
                null, false, "#^(\d{6,})#");
        }

        $r->general()
            ->confirmation($conf,
               'booking reference number');

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Full Name'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!='']/td[normalize-space()!=''][1]");
        $this->logger->debug(var_export($travellers, true));

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Dear '))}]", null, "/{$this->opt($this->t('Dear '))}(.+)/");
        }

        $travellers = array_filter(preg_replace("/^(Grp\s\D+\s\d+)$/", "", $travellers));

        if (count($travellers) > 0) {
            $r->general()
                ->travellers(preg_replace("/^(?:MRS|MS|MR|Grp)/", "", $travellers), true);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('booking has been cancelled'))}]")->length > 0) {
            $r->general()
                ->cancelled();

            return true;
        }

        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total paid'))}]/ancestor::tr[1]"));

        if (!empty($total['Total']) && !empty($total['Currency'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $cost = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Amount'))}]/ancestor::tr[1]/td[normalize-space()!=''][2]"));

        if (!empty($cost['Total'])) {
            $r->price()
                ->cost($cost['Total']);
        }

        $discount = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount applied'))}]/ancestor::tr[1]/td[normalize-space()!=''][2]"));

        if (!empty($discount['Total'])) {
            $r->price()
                ->discount($discount['Total']);
        }

        $seats = [];
        $countColumn = $this->http->XPath->query("//text()[{$this->eq($this->t('Full Name'))}]/ancestor::tr[1]/td[normalize-space()!=''][position() > 2]")->length;

        for ($i = 1; $i <= $countColumn; $i++) {
            $columnSeats = $this->http->FindNodes("//text()[{$this->eq($this->t('Full Name'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!='']/td[normalize-space()!=''][" . (2 + $i) . "]");

            if (isset($columnSeatsCount) && $columnSeatsCount !== count($columnSeats)) {
                $seats = [];

                break;
            } else {
                $columnSeatsCount = count($columnSeats);
            }

            foreach ($columnSeats as $rs) {
                $segmentsSeats = explode(', ', $rs);

                if (isset($segmentsSeatsCount[$i]) && $segmentsSeatsCount[$i] !== count($segmentsSeats)) {
                    $seats = [];

                    break 2;
                } else {
                    $segmentsSeatsCount[$i] = count($segmentsSeats);
                }

                foreach ($segmentsSeats as $j => $v) {
                    $seats[$i - 1][$j][] = $v;
                }
            }
        }
//        $this->logger->debug('$seats = '.print_r( $seats,true));

        $xpathTrip = "//text()[{$this->eq($this->t('Your trip'))}]/ancestor::table[{$this->contains($this->t('Dep'))}][1]";
        $this->logger->debug("[XPATH-Trip]: " . $xpathTrip);
        $nodes = $this->http->XPath->query($xpathTrip);

        if ($nodes->length !== count($seats)) {
            $seats = [];
        }

        foreach ($nodes as $i => $rootTrip) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Your trip'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][last()]",
                $rootTrip));
            $xpath = "./descendant::text()[{$this->contains($this->t('Dep'))}]";
            $this->logger->debug("[XPATH]: " . $xpath);

            $segments = $this->http->XPath->query($xpath, $rootTrip);

            if (isset($seats[$i]) && count($seats[$i]) !== $segments->length) {
                $seats = [];
            }

            foreach ($segments as $j => $root) {
                $s = $r->addSegment();

                // Departure
                $name = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]", $root);
                $time = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root);
                $s->departure()
                    ->name(!empty($name) ? $name . ', Ireland' : null)
                    ->date((!empty($date) && !empty($time)) ? strtotime($time, $date) : null);

                // Arrival
                $arrXpath = "./ancestor::tr[1]/following::tr[normalize-space()!=''][1][{$this->contains($this->t('Arr'))}]/descendant::text()[{$this->eq($this->t('Arr'))}]";
                $name = $this->http->FindSingleNode("{$arrXpath}/following::text()[normalize-space()!=''][1]", $root);
                $time = $this->http->FindSingleNode("{$arrXpath}/preceding::text()[normalize-space()!=''][1]", $root);
                $s->arrival()
                    ->name(!empty($name) ? $name . ', Ireland' : null)
                    ->date((!empty($date) && !empty($time)) ? strtotime($time, $date) : null);

                $s->extra()
                    ->noNumber();

                $nodesCabin = $this->http->FindNodes("./following::text()[normalize-space()!=''][1]/ancestor::td[1]/descendant::text()[normalize-space()!='']",
                    $root);

                if (count($nodesCabin) == 2) {
                    $cabin = $nodesCabin[1];
                    $legend = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Legend'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/descendant::text()[normalize-space()='{$cabin}']/following::text()[normalize-space()!=''][1]");
                    $s->extra()
                        ->cabin($legend);
                }
//                $fareXpath = "./ancestor::tr[1]/following::tr[normalize-space()!=''][1]/following-sibling::tr[normalize-space()!=''][1][{$this->starts($this->t('Fare Family'))}]";
//                $fare = $this->http->FindSingleNode("{$fareXpath}/descendant::text()[{$this->starts($this->t('Fare Family'))}]/following::text()[normalize-space()!=''][1]", $root);
                $s->extra()
                    ->service($service);

                if (!empty($seats[$i][$j])) {
                    $sSeats = array_filter(preg_replace("/^\s*(No Assigned Seat|NA)\s*$/i", '', $seats[$i][$j]));

                    if (!empty($sSeats)) {
                        $s->extra()->seats($sSeats);
                    }
                } else {
                    if ($i === 0) {
                        $seats = $this->http->FindNodes("//text()[normalize-space(.)='Outward Seat']/ancestor::table[1]/descendant::tr/td[3]/descendant::text()[not(contains(normalize-space(), 'Outward Seat'))]", null, "/^([A-Z]\d+)/");

                        if (count($seats) > 0) {
                            $s->setSeats($seats);
                        }
                    }

                    if ($i === 1) {
                        $seats = $this->http->FindNodes("//text()[normalize-space(.)='Return Seat']/ancestor::table[1]/descendant::tr/td[4]/descendant::text()[not(contains(normalize-space(), 'Return Seat'))]", null, "/^([A-Z]\d+)/");

                        if (count($seats) > 0) {
                            $s->setSeats($seats);
                        }
                    }
                }
            }
        }

        return true;
    }

    private function normalizeDate($str)
    {
        $in = [
            //Fri 24 May, 2019
            '#^\s*(\w+)\s+(\d+)\s+(\w+),\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$2 $3 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false
                || (stripos($body, "Our records show that your booking has been cancelled") !== false)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Full Name'], $words['booking reference number'])) {
                if (($this->http->XPath->query("//*[{$this->contains($words['Full Name'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['booking reference number'])}]")->length > 0)
                    || $this->http->XPath->query("//text()[{$this->contains('booking has been cancelled')}]")->length > 0
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
