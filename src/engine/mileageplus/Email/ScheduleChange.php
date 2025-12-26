<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-2168143.eml, mileageplus/it-56589415.eml, mileageplus/it-72888209.eml, mileageplus/it-83348827.eml";

    public static $dictionary = [
        "en" => [
            'Confirmation:' => ['Confirmation:', 'Confirmation number:'],
        ],
    ];

    public $lang = 'en';

    private $subjects = [
        'en' => [
            'Schedule Change Notification',
            'Schedule change for reservation',
            'The schedule for your reservation',
            'Travel Itinerary from',
            'The schedule for your reservation',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/^Fw\:/", $parser->getSubject())
            && $this->detectEmailFromProvider($parser->getHeaders()['from']) === false) {
            $email->setIsJunk(true);

            return true;
        }

        $this->ParseEmail($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'United Airlines') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".united.com/") or contains(@href,"www.united.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"United Airlines, Inc. All rights reserved") or contains(.,"mobile.united.com") or contains(.,"united.com/tripdetails") or contains(.,"@united.com") or contains(.,"united.com/cleanplus") or contains(., "United Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//*[contains(normalize-space(),"Flight Check-in Reminder")]')->length > 0
            || $this->http->XPath->query('//tr/*[normalize-space()="Flight"]/following-sibling::*[normalize-space()="Travel time"]')->length > 0
            || $this->http->XPath->query('//tr/*[normalize-space()="Flight"]/following-sibling::*[normalize-space()="Travel info"]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com/i', $from) > 0;
    }

    // United Airlines Schedule Change Notification for reservation XXXXXX
    // Check in now for your flight to XXX. Confirmation XXXXXXX
    protected function ParseEmail(PlancakeEmailParser $parser, Email $email): void
    {
        $f = $email->add()->flight();

        // anti-duplicate
        $flightRoots = $this->http->XPath->query("//text()[{$this->starts(['Confirmation number:'])}]/ancestor::tr[ descendant::tr/*[{$this->eq($this->t('Flight'))}]/following-sibling::*[{$this->eq($this->t('Travel info'))}] ][1]");
        $rootFlight = $flightRoots->length > 0 ? $flightRoots->item($flightRoots->length - 1) : null;

        if (preg_match("/Confirmation\s*(?-i)([A-Z\d]{5,})/i", $parser->getSubject(), $matches)
            || preg_match("/for(?: your)? reservation\s*(?-i)([A-Z\d]{5,})\b/i", $parser->getSubject(), $matches)
        ) {
            $confirmation = $matches[1];
        } else {
            $patterns['pnr'] = "/^{$this->opt($this->t('Confirmation:'))}\s*([A-Z\d]{5,})$/";
            $confirmation = $this->http->FindSingleNode("descendant::span[contains(@id,'lblPNR') or contains(@id,'spanPNR')]", null, true, '/^[A-Z\d]{5,}$/')
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]", $rootFlight, true, "/^\s*([A-Z\d]{5,7})\s*$/")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Confirmation:'))}]", $rootFlight, true, $patterns['pnr'])
                ?? $this->http->FindSingleNode("descendant::p[{$this->starts($this->t('Confirmation:'))}]", $rootFlight, true, $patterns['pnr'])
                ?? $this->http->FindSingleNode("descendant::text()[normalize-space()='Flight']/following::text()[{$this->starts($this->t('Confirmation:'))}][1]", null, true, $patterns['pnr'])
            ;
        }

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        }

        $flightRows = $this->http->XPath->query("//tr[descendant::*[contains(text(), 'Flight Time')]][count(td) = 8]");

        if ($flightRows->length === 0) {
            // it-83348827.eml
            $flightRows = $this->http->XPath->query("descendant::tr/*[normalize-space()][1][starts-with(normalize-space(),'Flight')]/ancestor::table[1]/descendant::tr[*[5] and normalize-space()][not(contains(normalize-space(),'Flight'))]", $rootFlight);
        }

        if ($flightRows->length === 0) {
            $flightRows = $this->http->XPath->query("//td/p[starts-with(normalize-space(), 'Flight')]/ancestor::table[1]/descendant::tr[normalize-space()][not(contains(normalize-space(), 'Flight'))]");
        }

        if ($flightRows->length === 0) {
            $flightRows = $this->http->XPath->query("//tr[starts-with(normalize-space(), 'Flight')]/ancestor::table[1]/descendant::tr[normalize-space()][not(contains(normalize-space(), 'Flight'))]");
        }

        for ($i = 0; $i < $flightRows->length; $i++) {
            $flight = $flightRows->item($i);
            $s = $f->addSegment();
            $number = $this->http->FindSingleNode("td[2]", $flight, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+$/');

            if (empty($number)) {
                $number = $this->http->FindSingleNode("td[1]", $flight, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+$/');
            }

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$/', $number, $m)) {
                $s->airline()
                    ->number($m[2])
                    ->name($m[1]);
                $operator = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $flight, true, "/Flight {$m[0]} is operated by (.+)/i");

                if ($operator) {
                    // it-83348827.eml
                    $s->airline()->operator($operator);
                }
            } else {
                $s->setFlightNumber($number);
            }

            // 8:50 a.m. Mon., Mar. 17, 2014 San Francisco, CA (SFO)
            $patterns['airport1'] = "/^(.*\.m\.)(.*\d,\s\d{4})\s*([A-Z].+)\(([A-Z]{3}).*\)$/";
            // 7:53 p.m. August 21 Denver (DEN)
            $patterns['airport2'] = "/^\s*([\d:]+\s*(?:p.m.|a.m.)\s*\w+\s+\d+)\s*(\D+)\s*\(([A-Z]{3})\)$/";

            if ($this->http->FindSingleNode("*[4]", $flight, true, '/^(.*\.m\..*\d\,\s\d{4}\s*[A-Z].+\([A-Z]{3}.*\))$/')) {
                $depInfo = implode(' ', $this->http->FindNodes("*[4]/descendant::text()[normalize-space()]", $flight));
            } elseif ($this->http->FindSingleNode("*[2]", $flight, true, '/^([\d:]+\s*(?:p.m.|a.m.).+?\([A-Z]{3}\))$/')) {
                $depInfo = implode(' ', $this->http->FindNodes("*[2]/descendant::text()[normalize-space()]", $flight));
            } else {
                $depInfo = '';
            }

            if (preg_match($patterns['airport1'], $depInfo, $matches)) {
                $s->departure()
                    ->code($matches[4])
                    ->name(trim($matches[3]))
                    ->date(strtotime($matches[2] . " " . $matches[1]));
            } elseif (preg_match($patterns['airport2'], $depInfo, $matches)) {
                $s->departure()
                    ->code($matches[3])
                    ->date(EmailDateHelper::calculateDateRelative($this->normalizeDate($matches[1]), $this, $parser, '%D% %Y%'));

                if (!empty(trim($matches[2]))) {
                    $s->departure()
                        ->name(trim($matches[2]));
                }
            }

            if ($this->http->FindSingleNode("*[5]", $flight, true, '/^(.*\.m\..*\d\,\s\d{4}\s*[A-Z].+\([A-Z]{3}.*\))$/')) {
                $arrInfo = implode(' ', $this->http->FindNodes("*[5]/descendant::text()[normalize-space()]", $flight));
            } elseif ($this->http->FindSingleNode("*[3]", $flight, true, '/^([\d\:]+\s*(?:p.m.|a.m.).+?[(][A-Z]{3}[)])$/')) {
                $arrInfo = implode(' ', $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $flight));
            } else {
                $arrInfo = '';
            }

            if (preg_match($patterns['airport1'], $arrInfo, $matches)) {
                $s->arrival()
                    ->code($matches[4])
                    ->name(trim($matches[3]))
                    ->date(strtotime($matches[2] . " " . $matches[1]));
            //$segment["ArrDateHuman"] = date("Y-m-d H:i", $segment["ArrDate"]);
            } elseif (preg_match($patterns['airport2'], $arrInfo, $matches)) {
                $s->arrival()
                    ->code($matches[3])
                    ->date(EmailDateHelper::calculateDateRelative($this->normalizeDate($matches[1]), $this, $parser, '%D% %Y%'));

                if (!empty(trim($matches[2]))) {
                    $s->arrival()
                        ->name(trim($matches[2]));
                }
                //$segment["ArrDateHuman"] = date("Y-m-d H:i", $segment["ArrDate"]);
            }

            if (preg_match("/(.*)Fare Class:([^(]+)\(([^)]+)\).*Meals:(.*)/i", $this->http->FindSingleNode("*[6]", $flight), $m)) {
                $s->extra()
                    ->aircraft($m[1])
                    ->cabin(trim($m[2]))
                    ->bookingCode($m[3])
                    ->meal(trim($m[4]));
            } elseif (preg_match('/^[A-Z]{1,2}$/', $this->http->FindSingleNode("*[4]", $flight), $m)) {
                // K    |    YN
                $s->extra()
                    ->bookingCode($m[0]);
            } elseif (preg_match('/^(.{3,}?)\s*\(\s*([A-Z]{1,2})\s*\)$/', $this->http->FindSingleNode("*[4]", $flight), $m)) {
                // United Economy (K)
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }

            $duration = $this->http->FindSingleNode("*[7]", $flight, true, "/Flight Time\:\D*(.*)/i");

            if (empty($duration)) {
                // 4 hr    |    4 hr 43 mn
                $duration = $this->http->FindSingleNode("*[5]", $flight, true, '/^\d[\d hrsmn]+$/i');
            }

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            if (empty($duration) && empty($s->getAircraft())) {
                // 2 hr 15 mn Boeing 757-300
                $duration = trim($this->http->FindSingleNode("*[5]", $flight, true, '/^(\d[\d hrsmn]+)/i'));
                $aircraft = $this->http->FindSingleNode("*[5]", $flight, true, '/^\d[\d hrsmn]+(.+)/i');

                if (!empty($duration) && !empty($aircraft)) {
                    $s->extra()
                        ->duration($duration)
                        ->aircraft($aircraft);
                }
            }
        }
        $passengers = [];
        $seats = [];
        $nodes = $this->http->XPath->query("//*[contains(text(), 'Traveler Information')]/following-sibling::div[1]/table");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $passengers[] = beautifulName($this->http->FindSingleNode("tbody/tr[1] | tr[1]", $node));
            $passSeats = array_map(function ($seat) {
                return preg_replace("/^[^:]+:\D+/", "", $seat);
            }, $this->http->FindNodes("tbody/tr[2]/td[2]/font/text() | tr[2]/td[2]/font/text()", $node));

            foreach ($passSeats as $j => $seat) {
                if (!isset($seats[$j])) {
                    $seats[$j] = [];
                }

                if ($seat != "") {
                    $seats[$j][] = $seat;
                }
            }
        }
        $pax = $passengers;

        if (empty($pax)) {
            $pax = $this->http->FindNodes("descendant::table[starts-with(normalize-space(),'Traveler')]/descendant::tr[not(contains(normalize-space(),'Traveler'))]", $rootFlight);
        }

        if (count($pax) > 0) {
            $f->general()->travellers($pax, true);
        }

        //  !!!!- No examples saved -!!!!!!
        /*foreach ($result["TripSegments"] as $i => $segment) {
            if (isset($seats[$i]) && count($seats[$i]) > 0)
                $result["TripSegments"][$i]["Seats"] = implode(",", $seats[$i]);
        }
        return [$result];*/
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            // 2:45 p.m. May 25 2020
            '/^([\d\:]+\s*(?:a|A|p|P)\.(?:M|m)\.)\s*([[:alpha:]]+)\s+(\d{1,2})\s+(\d{4})$/u',
            // 10:45 a.m. December 9
            '/^([\d\:]+\s*(?:a|A|p|P)\.(?:M|m)\.)\s*([[:alpha:]]+)\s+(\d{1,2})$/u',
        ];
        $out = [
            '$1 $3 $2 $4',
            '$1 $3 $2',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
