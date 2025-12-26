<?php

namespace AwardWallet\Engine\groome\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelItineraryForAirport extends \TAccountChecker
{
    public $mailFiles = "groome/it-330835670.eml, groome/it-878020833.eml, groome/it-869952374.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'confNumber' => ['Reservation #'],
            'pickUp' => ['Pick Up Location', 'Pickup', 'Pickup Location'],
            'depot' => ['Depot Location', 'Depot'],
            'dropOff' => ['Drop Off Location', 'Drop Off'],
            'pickupTime' => ['Pick Up Time', 'Pickup Time'],
            'dropoffTime' => ['Estimated Drop Off Time', 'Drop Off Time'],
            // 'Flight Time' => ['Arrival Time', 'ArrivalTime', 'DepartureTime', 'Departure Time'],
            // 'Fare' => ['Fare', 'Total Fare'],
        ],
    ];

    private $detectSubject = [
        // en
        // Travel Itinerary for Monday, 3/21/2022 from MCO Airport
        // Travel Itinerary for Wednesday, 4/19/2023 to MCO Airport | TV791401
        'Travel Itinerary for',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[.@]groometrans\.com\s*$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (is_string($dSubject) && array_key_exists('subject', $headers) && stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query(
                "//*[{$this->contains(['Where to Find Your Groome Shuttle at', 'Groome Transportation, Inc'])}]"
                . " | //img[{$this->contains(['.groometransportation.com/', 'villages.groometransportation.com'], '@src')} or {$this->contains('Groome Transportation', '@alt')}]"
            )->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
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

    private function parseEmailHtml(Email $email): void
    {
        $xpathDigits = "contains(translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆')";

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'nameCode' => '/^\s*.*Airport.*\(\s*(?-i)([A-Z]{3})\s*\)/i', // Orlando International Airport (MCO)
        ];

        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->getField($this->t('confNumber')))
            ->traveller($this->getField($this->t('Passenger Name'), null, "/^{$patterns['travellerName']}$/u"));

        $note = $this->getField($this->t('Note'));

        if ($note) {
            $t->general()->notes($note);
        }

        $passengersCount = $this->getField($this->t('Passengers'), null, "/^\d{1,3}$/");
        $date = strtotime($this->getField($this->t('Travel Date'), null, "/^.{4,}\b\d{4}$/"));

        // Segments
        $s = $t->addSegment();
        $s->extra()->adults($passengersCount);

        $timeDep = $this->getField($this->t('pickupTime'), null, "/^(?:\D+ )?({$patterns['time']})/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('pickUp'), "translate(.,':','')")}]/following::text()[normalize-space()][position()<3][{$this->eq($this->t('Time'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^(?:\D+ )?({$patterns['time']})/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('pickUp'), "translate(.,':','')")}]/following::text()[normalize-space()][position()<5][{$this->eq($this->t('Time'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^(?:\D+ )?({$patterns['time']})/")
        ;
        $dateDep = strtotime($timeDep, $date);
        $s->departure()->date($dateDep);

        $locationDep = $this->getField($this->t('pickUp'));
        $addressDep = $this->getField($this->t('Pickup Address'))
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('pickUp'), "translate(.,':','')")}]/following::text()[normalize-space()][position()<3][{$this->eq($this->t('Address'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[:\s]*(.*[^:])$/")
        ;

        if (preg_match($patterns['nameCode'], $locationDep, $m)) {
            if (!in_array($m[1], TravelItineraryFor::$badAirportCodes)) {
                $s->departure()->code($m[1]);
            }

            $s->departure()->name($locationDep);
        } elseif (!$addressDep && preg_match("/\d{5}/", $locationDep)) {
            $s->departure()->address($locationDep);
        } else {
            $s->departure()->name($locationDep);
        }

        if ($addressDep) {
            $s->departure()->address($addressDep);
        }

        /* Depots (start) */

        $depotNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('depot'), "translate(.,':','')")}]");

        foreach ($depotNodes as $root) {
            $s->arrival()->noDate();

            $locationDepot = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root, true, "/^[:\s]*(.*[^:])$/");
            $addressDepot = $this->http->FindSingleNode("following::text()[normalize-space()][position()<3][{$this->eq($this->t('Depot Address'), "translate(.,':','')")} or {$this->eq($this->t('Address'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^[:\s]*(.*[^:])$/");

            if (preg_match($patterns['nameCode'], $locationDepot, $m)) {
                if (!in_array($m[1], TravelItineraryFor::$badAirportCodes)) {
                    $s->arrival()->code($m[1]);
                }

                $s->arrival()->name($locationDepot);
            } elseif (!$addressDepot && preg_match("/\d{5}/", $locationDepot)) {
                $s->arrival()->address($locationDepot);
            } else {
                $s->arrival()->name($locationDepot);
            }

            if ($addressDepot) {
                $s->arrival()->address($addressDepot);
            }

            $s = $t->addSegment();
            $s->extra()->adults($passengersCount);

            $timeDepot = $this->http->FindSingleNode("following::text()[normalize-space()][position()<3][{$this->eq($this->t('Depot Departure'), "translate(.,':','')")} or {$this->eq($this->t('Departure'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^(?:\D+ )?({$patterns['time']})/")
                ?? $this->http->FindSingleNode("following::text()[normalize-space()][position()<5][{$this->eq($this->t('Depot Departure'), "translate(.,':','')")} or {$this->eq($this->t('Departure'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^(?:\D+ )?({$patterns['time']})/")
            ;
            $dateDepot = strtotime($timeDepot, $date);
            $s->departure()->date($dateDepot);
            $dateDep = $dateDepot;

            if (preg_match($patterns['nameCode'], $locationDepot, $m)) {
                if (!in_array($m[1], TravelItineraryFor::$badAirportCodes)) {
                    $s->departure()->code($m[1]);
                }

                $s->departure()->name($locationDepot);
            } elseif (!$addressDepot && preg_match("/\d{5}/", $locationDepot)) {
                $s->departure()->address($locationDepot);
            } else {
                $s->departure()->name($locationDepot);
            }

            if ($addressDepot) {
                $s->departure()->address($addressDepot);
            }
        }

        /* Depots (end) */

        $timeArr = $this->getField($this->t('dropoffTime'), null, "/^(?:\D+ )?({$patterns['time']})/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('dropOff'), "translate(.,':','')")}]/following::text()[normalize-space()][position()<3][{$this->eq($this->t('Time'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^(?:\D+ )?({$patterns['time']})/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('dropOff'), "translate(.,':','')")}]/following::text()[normalize-space()][position()<5][{$this->eq($this->t('Time'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^(?:\D+ )?({$patterns['time']})/")
        ;
        $dateArr = strtotime($timeArr, $date);

        if ($dateDep && $dateArr && $dateDep > $dateArr) {
            $dateArr = strtotime('+1 days', $dateArr);
        }

        $s->arrival()->date($dateArr);
        
        $locationArr = $this->getField($this->t('dropOff'));
        $addressArr = $this->getField($this->t('Drop Off Address'))
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('dropOff'), "translate(.,':','')")}]/following::text()[normalize-space()][position()<3][{$this->eq($this->t('Address'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[:\s]*(.*[^:])$/")
        ;

        if (preg_match($patterns['nameCode'], $locationArr, $m)) {
            if (!in_array($m[1], TravelItineraryFor::$badAirportCodes)) {
                $s->arrival()->code($m[1]);
            }

            $s->arrival()->name($locationArr);
        } elseif (!$addressArr && preg_match("/\d{5}/", $locationArr)) {
            $s->arrival()->address($locationArr);
        } else {
            $s->arrival()->name($locationArr);
        }

        if ($addressArr) {
            $s->arrival()->address($addressArr);
        }

        // Price
        $xpathTotalPrice = "text()[{$this->eq($this->t('Amount Paid'), "translate(.,':','')")}]";

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Information'), "translate(.,':','')")}]/following::{$xpathTotalPrice}/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $33.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $discountAmounts = [];

            $feeNodes = $this->http->XPath->query("//text()[ preceding::text()[{$this->eq($this->t('Payment Information'), "translate(.,':','')")}] and following::{$xpathTotalPrice} ][{$xpathDigits}]");

            foreach ($feeNodes as $i => $feeRoot) {
                $feeName = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $feeRoot, true, "/^(.*[^:\s])\s*[:]+$/");
                $feeValue = $this->http->FindSingleNode(".", $feeRoot, true, "/^[:\s]*(.*[^:])$/");
                
                if ($feeName === null || $feeValue === null || !preg_match("/\d/", $feeValue)) {
                    continue;
                }

                $isDiscount = false;

                if (preg_match("/^\(\s*([^()\s].*?)\s*\)$/", $feeValue, $m)) {
                    $isDiscount = true;
                    $feeValue = $m[1];
                }

                $feeAmount = null;

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $feeValue, $m)) {
                    $feeAmount = PriceHelper::parse($m['amount'], $currencyCode);
                } else {
                    continue;
                }

                if ($i === 0 && !$isDiscount && preg_match("/^{$this->opt($this->t('Fare'))}$/i", $feeName)) {
                    $t->price()->cost($feeAmount);

                    continue;
                }

                if ($isDiscount || preg_match("/^.*\b{$this->opt($this->t('Discount'))}\b.*$/i", $feeName)) {
                    $discountAmounts[] = $feeAmount;

                    continue;
                }

                $t->price()->fee($feeName, $feeAmount);
            }

            if (count($discountAmounts) > 0) {
                $t->price()->discount(array_sum($discountAmounts));
            }
        }
    }

    private function getField($name, \DOMNode $root = null, string $re = "/^[:\s]*(.*[^:])$/"): ?string
    {
        return $this->http->FindSingleNode("descendant::text()[{$this->eq($name, "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, $re);
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['confNumber']) || empty($phrases['pickUp']) ) {
                continue;
            }
            if ($this->http->XPath->query("//text()[{$this->starts($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($phrases['pickUp'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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
}
