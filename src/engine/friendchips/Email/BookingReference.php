<?php

namespace AwardWallet\Engine\friendchips\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingReference extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-282188095.eml, friendchips/it-42812999.eml, friendchips/it-43427820.eml, friendchips/it-799832278.eml";

    public $reFrom = ["@customerservices.tui.co.uk"];
    public $reBody = [
        'en' => ['Thanks for booking with TUI', 'Thanks for booking your flights with TUI Airways', 'Thanks for booking with holidayhypermarket.co.uk.'],
    ];
    public $reSubject = [
        'TUI Booking Reference',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'ISSUE DATE:'         => 'ISSUE DATE:',
            'Guest'               => ['Guest', 'Passenger Name'],
            'paxEnd'              => ['Lead Passenger', 'For information on the hand and hold '],
            'notReseravtion'      => ['FINANCIAL PROTECTION', 'CONTACT DETAILS', 'TRAVEL BOOKING'],
        ],
    ];
    private $keywordProv = ['TUI', 'TUIfly'];

    private $patterns = [
        'confNumber' => '[-A-Z\d]{5,12}', // 10452321  |  M5GPQK
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        $totalPrice = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Total Price'))} and preceding-sibling::*[normalize-space()] and count(following-sibling::*[normalize-space()])=1]/following-sibling::*[normalize-space()]", null, true, '/^.*\d.*$/')
        ?? $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Total Price'))} and count(following-sibling::*[normalize-space()])=1]/following-sibling::*[normalize-space()]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // £2,506.14
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            // TODO: move here fields cost, fees and discount
        }

        $feesList = [];

        $feeRows = $this->http->XPath->query("//tr[*[normalize-space()][1][{$this->eq($this->t('Price Breakdown'))}] and *[normalize-space()][last()][{$this->eq($this->t('Amount'))}] and count(*[{$this->eq($this->t('Amount'))}])=1]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[ following-sibling::*[{$this->starts($this->t('Total Price'))}] ]/descendant-or-self::*[count(*[normalize-space()])>1]");

        foreach ($feeRows as $feeRow) {
            $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRow);
            $feeCharge = $this->http->FindSingleNode("*[normalize-space()][last()]", $feeRow, true, '/^.*\d.*$/');

            if ($feeName && $feeCharge !== null) {
                $feesList[] = [
                    'name' => $feeName,
                    'charge' => $feeCharge,
                ];
            }
        }

        if (count($feesList) === 0) {
            // it-42812999.eml, it-43427820.eml
            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Price Breakdown'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[ following-sibling::*[{$this->starts($this->t('Total Price'))}] ]/descendant-or-self::*[count(*[normalize-space()])>1]");

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRow);
                $feeCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRow, true, '/^.*\d.*$/');
    
                if ($feeName && $feeCharge !== null) {
                    $feesList[] = [
                        'name' => $feeName,
                        'charge' => $feeCharge,
                    ];
                }
            }
        }

        $costAmounts = $discountAmounts = [];
        $costs = true;

        foreach ($feesList as $feeItem) {
            if ($costs && preg_match('/.+ Price\s*$/', $feeItem['name'])) {
                $sum = $this->getTotalCurrency($feeItem['charge']);
                $costAmounts[] = $sum['Total'];

                continue;
            }
            $costs = false;

            if (preg_match("/^\s*\-.*\d+/", $feeItem['charge'])) {
                $sum = $this->getTotalCurrency($feeItem['charge']);
                $discountAmounts[] = $sum['Total'];
            } else {
                $email->price()->fee($feeItem['name'], $this->getTotalCurrency($feeItem['charge'])['Total']);
            }
        }

        if (count($costAmounts) > 0) {
            $email->price()->cost(array_sum($costAmounts));
        }

        if (count($discountAmounts) > 0) {
            $email->price()->discount(array_sum($discountAmounts));
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'tuiholidays') or contains(@src,'.thomson.co.uk') or contains(@src,'.tui.co.uk')] | //a[contains(@href,'.tui.co.uk')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
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
        $fromProv = false;

        if (array_key_exists('from', $headers) && $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) === true) {
            $fromProv = true;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#", $headers["subject"]) > 0)
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
        $types = 2; // flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email): void
    {
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REF:'))}]/following::text()[normalize-space()][1]");
        $confName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REF:'))}]", null, true, "/^\s*(.+?)[\s:]*$/");
        $email->ota()
            ->confirmation($conf, $confName);

        $reservationsText = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('YOUR '))}][not({$this->contains($this->t('notReseravtion'))})]",
            null, "#{$this->opt($this->t('YOUR '))}\s*(.*)#"));

        foreach ($reservationsText as $res) {
            if (in_array($res, (array) $this->t('FLIGHTS'))) {
                $this->parseFlight($email);
            } elseif (in_array($res, (array) $this->t('ACCOMMODATION'))) {
                $this->parseHotel($email);
            } else {
                $this->logger->debug('may have missed a type reservation: ' . $res);
                $email->add()->rental(); // for broke

                return;
            }
        }

        $dateReservation = strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('ISSUE DATE:'))}]/following::text()[normalize-space()!=''][1]"));
        $paxRoots = $this->http->XPath->query($xpath = "//text()[{$this->eq($this->t('Guest'))}]/ancestor::tr[{$this->contains($this->t('Age'))}][1]/following::tr[normalize-space()!='']");
        $travellers = [];
        $infants = [];

        foreach ($paxRoots as $paxRoot) {
            if ($this->http->XPath->query("./td[normalize-space()!='']", $paxRoot)->length < 2
                || $this->http->XPath->query("./td[normalize-space()!=''][{$this->contains($this->t('paxEnd'))}]",
                    $paxRoot)->length > 0
            ) {
                break;
            }

            if ($this->http->XPath->query("./td[normalize-space()!=''][2]//text()[{$this->eq($this->t('Infant'))}]", $paxRoot)->length > 0) {
                $infants[] = trim($this->http->FindSingleNode("./td[normalize-space()!=''][1]", $paxRoot), "*");
            } else {
                $travellers[] = trim($this->http->FindSingleNode("./td[normalize-space()!=''][1]", $paxRoot), "*");
            }
        }

        $infants = preg_replace("/^\s*(Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master) /", '', $infants);
        $travellers = preg_replace("/^\s*(Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master) /", '', $travellers);

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight') {
                $it->general()->travellers($travellers, true);

                if (count($infants) > 0) {
                    $it->general()->infants($infants, true);
                }
            } else {
                $it->general()->travellers(array_merge($travellers, $infants), true);
            }

            if (!empty($dateReservation)) {
                $it->general()
                    ->date($dateReservation);
            }
        }
    }

    private function parseFlight(Email $email): void
    {
        $xpath = "//text()[{$this->eq($this->t('Leaving'))}]/ancestor::tr[1][following::tr[{$this->contains($this->t('Arriving'))}][1]][count(./td)>4]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-flight]: " . $xpath);

        $r = $email->add()->flight();

        $r->general()
            ->noConfirmation();
        $seats = [];

        if ($nodes->length < 3 && $this->http->XPath->query("//text()[{$this->contains($this->t('Seat Number'))}]")->length > 0) {
            $seats[0] = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seat Number'))}]/ancestor::tr[1]/td[3]//text()[{$this->contains($this->t('Seat Number'))}]",
                null, "/{$this->opt($this->t('Seat Number'))}\s+(\d{1,3}[A-Z])\s*/"));
            $seats[1] = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seat Number'))}]/ancestor::tr[1]/td[4]//text()[{$this->contains($this->t('Seat Number'))}]",
                null, "/{$this->opt($this->t('Seat Number'))}\s+(\d{1,3}[A-Z])\s*/"));
        }

        foreach ($nodes as $i => $root) {
            $s = $r->addSegment();

            $s->setConfirmation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][3]/descendant::text()[normalize-space()]",
                $root, false, "#^([A-Z\d]+)$#"));

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]",
                    $root, false, "#{$this->opt($this->t('Leaving'))}\s*(.+)#")))
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root))
                ->terminal($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][3]", $root, false, "/^(?:Terminal[-:\s]*)*(.+?)(?:[:\s]*Terminal)*$/i"), false, true)
                ->code($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[contains(normalize-space(), '(')]",
                    $root, false, "#^\(([A-Z]{3})\)$#"));

            $airline = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Flight no'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1]",
                $root);
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Flight no'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][2]",
                $root, false, "#{$this->opt($this->t('Flight no'))}[:\s]*(.+)#");

            if (preg_match("#^([A-Z\d]{2,3}?)\s*(\d+)$#", $node, $m)) {
                // https://en.wikipedia.org/wiki/TUI_Airways
                if ($m[1] === 'TOM' || $airline === 'TUI Airways') {
                    $airline = 'BY';
                } elseif (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])$#", $m[1])) {
                    $airline = $m[1];
                }
                $s->airline()
                    ->name($airline)
                    ->number($m[2]);
            }

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]",
                    $root, false, "#{$this->opt($this->t('Arriving'))}\s*(.+)#")))
                ->name($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root))
                ->terminal($this->http->FindSingleNode("following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][3]", $root, false, "/^(?:Terminal[-:\s]*)*(.+?)(?:[:\s]*Terminal)*$/i"), false, true)
                ->code($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[contains(normalize-space(), '(')]",
                    $root, false, "#^\(([A-Z]{3})\)$#"));

            if (!empty($seats[$i])) {
                $s->extra()
                    ->seats($seats[$i]);
            }

            $aircraft = $this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][2]/descendant::text()[normalize-space()][1][not(contains(normalize-space(), 'Flight Extras'))]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }
        }
    }

    private function parseHotel(Email $email): void
    {
        // examples: it-43427820.eml, it-282188095.eml
        $xpath = "//text()[{$this->eq($this->t('Accommodation name'))}]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-hotel]: " . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            $confirmation = $this->http->FindSingleNode("self::td[{$this->eq($this->t('Accommodation name'))}]/preceding::tr[count(*[normalize-space()])=2][1][ *[normalize-space()][1][{$this->eq($this->t('Booking ref number'))}] ]/*[normalize-space()][2]", $root, true, "/^{$this->patterns['confNumber']}$/")
            ?? $this->http->FindSingleNode("self::td[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Booking ref number'))}] ]/following-sibling::td[normalize-space()]/descendant::text()[normalize-space()][1]", $root, true, "/^{$this->patterns['confNumber']}$/");

            $hotelName = $this->http->FindSingleNode("self::td[{$this->eq($this->t('Accommodation name'))}]/following-sibling::td[normalize-space()]", $root)
            ?? $this->http->FindSingleNode("self::td[ descendant::text()[normalize-space()][2][{$this->eq($this->t('Accommodation name'))}] ]/following-sibling::td[normalize-space()]/descendant::text()[normalize-space()][2]", $root);

            if ($this->http->XPath->query("./following::tr[normalize-space()!=''][position()<5]/td[{$this->starts($this->t('Destination'))}]/descendant::text()[normalize-space()!='']",
                    $root)->length === 2
            ) {
                $r->hotel()
                    ->address(implode(', ',
                        $this->http->FindNodes("./following::tr[normalize-space()!=''][position()<5]/td[{$this->starts($this->t('Destination'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']",
                            $root)));
            }

            if ($this->http->XPath->query("./following::tr[normalize-space()!=''][position()<6]/td[{$this->contains($this->t('Check-in'))}]/descendant::text()[normalize-space()!='']",
                    $root)->length === 3
            ) {
                $checkIn = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][position()<6]/td[{$this->contains($this->t('Check-in'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][2]",
                    $root);
                $checkOut = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][position()<6]/td[{$this->contains($this->t('Check-in'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][3]",
                    $root);
                $r->booked()
                    ->checkIn(strtotime($checkIn))
                    ->checkOut(strtotime($checkOut));
            }

            $roomsDescr = array_filter($this->http->FindNodes("following::tr[normalize-space()][position()<10]/td[{$this->starts($this->t('Room description'), "translate(.,'0123456789','')")}]", $root, "#^[^:]+[:]+\s*(.{2,})#"));
            
            foreach ($roomsDescr as $item) {
                $r->addRoom()->setDescription($item);
            }

            $r->general()->confirmation($confirmation);
            $r->hotel()->name($hotelName);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['ISSUE DATE:'], $words['Guest'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['ISSUE DATE:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Guest'])}]")->length > 0
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

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
