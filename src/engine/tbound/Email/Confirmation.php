<?php

namespace AwardWallet\Engine\tbound\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "tbound/it-15774175.eml, tbound/it-28840101.eml";

    public $reBody = [
        'en'  => ['Booking Details', 'Booking Confirmation'],
        'en2' => ['Booking Details', 'Refund Information'],
        'en3' => ['Booking Details', 'Payment Reminder'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Status' => ['Confirmed', 'Cancelled'],
            'nights' => ['nights', 'night'],
        ],
    ];
    private $pax = '';

    private $code;
    private static $bodies = [
        'mta' => [
            '//a[contains(@href,"mtatravel.com.au")]',
            'MTA Travel',
        ],
        'tbound' => [
            '//a[contains(@href,"booktravelbound.com")]',
            'Travel Bound',
        ],
    ];
    private $headers = [
        'mta' => [
            'from' => ["mtatravel.com.au", "travelcube.com.au"],
            'subj' => [
                '#Travel Bound, Booking ID [-\/\w]+ - Payment Reminder#i',
                '#Travel Bound, Booking ID [-\/\w]+ - Confirmation#i',
                '#TravelCube, Booking ID .+? - Refund Information#i',
            ],
        ],
        'tbound' => [
            'from' => ["gta-travel.com", "booktravelbound.com"],
            'subj' => [
                '#Travel Bound, Booking ID [-\/\w]+ - Payment Reminder#i',
                '#Travel Bound, Booking ID [-\/\w]+ - Confirmation#i',
                '#TravelCube, Booking ID .+? - Refund Information#i',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers['subject'])) {
                    $bySubj = true;

                    break;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->code = $this->getProvider($parser);

        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
            $this->logger->debug("[PROVIDER]: {$this->code}");
        }

        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Travel Bound' or @alt='TravelCube' or contains(@src,'gta-travel.com') or contains(@src,'travelcube.com')] | //a[contains(@href,'gta-travel.com') or contains(@href,'travelcube.com')] ")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$bodies);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; //transfer, hotel , tour
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $this->pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Lead Name:'))}]/following::text()[normalize-space(.)!=''][1]");
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*([\w\-\/]{5,})\s*$#"), trim($this->t('Booking ID:'), " :"));
        $xpath = "//text()[{$this->eq($this->t('Booking Details'))}]/ancestor::td[1][./following::td[1][{$this->eq($this->t('Status'))}]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (($node = $this->http->XPath->query("./ancestor::tr[1]/following::tr[1][contains(.,'Transfer')]",
                    $root))->length > 0
            ) {
                $this->parseTransfer($node->item(0), $email);

                continue;
            } elseif ($this->http->XPath->query("./descendant::text()[starts-with(normalize-space(.),'Room Type')]",
                    $root)->length > 0
            ) {
                $this->parseHotel($root, $email);

                continue;
            } elseif (($node = $this->http->XPath->query("./ancestor-or-self::td[1][contains(.,'Duration') and contains(.,'Pick up')]",
                    $root))->length > 0
            ) {
                $this->parseTour($node->item(0), $email);

                continue;
            } elseif (($node = $this->http->XPath->query("./ancestor::tr[1]/following::tr[1][contains(.,'Journey Type')]", $root))->length > 0) {
                $this->parseBus($node->item(0), $email);

                continue;
            } else {
                $this->logger->alert('unknown type of reservation');

                return false;
            }
        }

        return true;
    }

    private function parseBus(\DOMNode $root, Email $email)
    {
        $b = $email->add()->bus();
        $b->general()
        ->noConfirmation()
        ->traveller($this->pax)
        ->status($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('Status'))}]", $root));

        $sb = $b->addSegment();

        $date = $this->nextText('Date:', $root);
        $date = preg_replace('/(\w+) (\d{1,2}) (\d{2,4})/', '$2 $1 $3', $date);

        if (preg_match('/[A-Z\d]{2}\s*(\d+)/', $this->nextText('Flight No.:', $root), $m)) {
            $sb->extra()->number($m[1]);
        }
        $depTime = $this->nextText('Pick up Time:', $root);

        if ($date && $depTime) {
            $sb->departure()->date(strtotime($date . ', ' . $depTime));
            $sb->arrival()->noDate();
        }

        if ($dep = $this->nextText('Pick up From:', $root)) {
            $sb->departure()->address($dep);
        }

        if ($arr = $this->nextText('Drop off Point:', $root)) {
            $sb->arrival()->address($arr);
        }

        return true;
    }

    private function parseTransfer(\DOMNode $root, Email $email)
    {
        $t = $email->add()->transfer();
        $t->general()
            ->noConfirmation()
            ->traveller($this->pax)
            ->status($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('Status'))}]",
                $root));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./following::tr[normalize-space(.)!=''][1]/descendant::text()[{$this->eq($this->t('Cost:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root));

        if (!empty($tot['Total'])) {
            $t->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }
        $date = strtotime($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Date:')]/following::text()[normalize-space(.)!=''][1]",
            $root));
        $s = $t->addSegment();
        $s->extra()
            ->type($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Vehicle Type'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));

        $s->departure()
            ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick up Time'))}]/following::text()[normalize-space(.)!=''][1]",
                $root), $date))
            ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick up From'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
        $s->arrival()
            ->noDate()
            ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop off Point'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));

        return true;
    }

    private function parseHotel(\DOMNode $root, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation()
            ->traveller($this->pax)
            ->status($this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('Status'))}]",
                $root));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./following::tr[normalize-space(.)!=''][1]/descendant::text()[{$this->eq($this->t('Cost:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root));

        if (!empty($tot['Total'])) {
            $h->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root))
            ->noAddress();

        $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Date:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root);

        if (preg_match("#(.+) *\- *(\d+) *{$this->opt($this->t('nights'))}#", $node, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime("+ {$m[2]} days", strtotime($m[1])));
        }
        $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Room Type:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root);

        if (preg_match("#(\d+) *\w *(.+)#", $node, $m)) {
            $h->booked()
                ->rooms($m[1]);
            $r = $h->addRoom();
            $r->setType($m[2]);
        }

        // deadline
        $noCharges = $this->http->XPath->query("./ancestor::table[1]/descendant::text()[{$this->contains($this->t('If you cancel the booking prior to arrival the following charges will apply:'))}]/following::table[normalize-space(.)][1]/descendant::td[{$this->eq($this->t('No Charges'))}]", $root);

        if ($noCharges->length > 0) {
            $noChargesBefore = $this->http->XPath->query("./preceding-sibling::*", $noCharges->item($noCharges->length - 1));
            $noChargesPos = $noChargesBefore->length + 1;
            $deadline = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::*[last()]/*[{$noChargesPos}]", $noCharges->item($noCharges->length - 1));

            if (
                preg_match("/^(.{6,})\s+or earlier$/i", $deadline, $m) // Nov 13 2018 or earlier
            ) {
                $h->booked()->deadline2($m[1]);
            }
        }

        return true;
    }

    private function parseTour(\DOMNode $root, Email $email)
    {
        $e = $email->add()->event();
        $e->general()
            ->noConfirmation()
            ->traveller($this->pax)
            ->status($this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('Status'))}]",
                $root));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./following::tr[normalize-space(.)!=''][1]/descendant::text()[{$this->eq($this->t('Cost:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root));

        if (!empty($tot['Total'])) {
            $e->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }
        $date = strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Date:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root));
        $e->place()
            ->type(EVENT_EVENT)
            ->name($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root))
            ->address($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick up From:'))}]/following::text()[normalize-space(.)!=''][1]",
                $root));
        $e->booked()
            ->start(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick up Time:'))}]/following::text()[normalize-space(.)!=''][1]",
                $root), $date));
        $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Duration:'))}]/following::text()[normalize-space(.)!=''][1]",
            $root);

        if (preg_match("#^([\d\.]+) *{$this->opt($this->t('Day(s)'))}$#", $node, $m)) {
            $duration = (float) $m[1];
            $e->booked()
                ->end(strtotime("+ {$duration} days", $date));
        } elseif (preg_match("#^(?:(\d+) +{$this->opt($this->t('Hour(s)'))})? *(?:(\d+) +{$this->opt($this->t('Minute(s)'))})?$#",
            $node, $m)) {
            $date = $e->getStartDate();

            if (isset($m[1])) {
                $hours = $m[1];
            } else {
                $hours = 0;
            }

            if (isset($m[2])) {
                $minutes = $m[2];
            } else {
                $minutes = 0;
            }
            $e->booked()
                ->end(strtotime("+ {$hours} hours  {$minutes} minutes ", $date));
        }

        return true;
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
                    $this->lang = substr($lang, 0, 2);

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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
