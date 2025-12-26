<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-209340475.eml, frontierairlines/it-27144023.eml, frontierairlines/it-27219510.eml, frontierairlines/it-69877933.eml, frontierairlines/it-74288262.eml, frontierairlines/it-95006645.eml, frontierairlines/it-305710136.eml, frontierairlines/it-305271478.eml, frontierairlines/it-760317423.eml, frontierairlines/it-751909118.eml"; // +1 bcdtravel(html)[en]
    private $subjects = [
        'en' => ['Important information for your upcoming trip', "It's time to check in for your flight to"],
    ];

    private $lang = '';
    private static $dict = [
        'en' => [
            'confNumber' => [
                'Your trip confirmation number is',
                'Your trip confirmation code is',
                'YOUR TRIP CONFIRMATION CODE IS',
                'Your confirmation code',
            ],
            'Flight'  => ['Flight', 'Flights'],
            'Nonstop' => ['Nonstop', 'Non-stop', 'Non stop'],
        ],
    ];

    private $year = '';

    private $xpath = [
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Frontier Airlines') !== false
            || preg_match('/[.@]flyfrontier\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".flyfrontier.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for flying Frontier") or contains(normalize-space(),"activity with Frontier Airlines") or contains(normalize-space(),"Frontier Airlines. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
        $this->year = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Frontier Airlines")]', null, true, "/^©\s*(\d{4})\s*Frontier Airlines/i");
        $this->parseEmail($email);
        $email->setType('YourTrip' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function findSegments(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ count(*[normalize-space() or .//img])>1 and count(*[normalize-space() or .//img])<4 and *[1][not(.//img)] and *[2]/descendant::img and *[3][not(.//img)] and */descendant::text()[normalize-space()][1][{$this->xpath['airportCode']}] ]");
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        //###########
        //# FLIGHT ##
        //###########

        $f = $email->add()->flight();
        $confirmation = null;

        foreach ((array) $this->t('confNumber') as $phrase) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($phrase, "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

            if ($confirmation) {
                $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($phrase, "translate(.,':','')")}]", null, true, '/^(.+?)(?:\s+IS)?[\s:：]*$/iu');
                $f->general()->confirmation($confirmation, $confirmationTitle);

                break;
            }
        }

        $passengers = [];

        $xpathFragmentTime = 'descendant::text()[string-length(normalize-space(.))=3][1]/following::text()[normalize-space(.)][1]';
        $xpathFragmentPassenger = "./following::table[not(.//tr/*[3]) and normalize-space(.)][1][ ./descendant::text()[{$this->eq($this->t('Seat Assignment:'))}] ]/descendant::td[not(.//td) and normalize-space(.)]";

        $segments = $this->findSegments();
        $key = 1;

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            $s->airline()->noName();

            // flightNumber
            $flightNumber = $this->http->FindSingleNode("./preceding::tr[not(./*[3]) and normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]", $segment, true, "/{$this->opt($this->t('Flight'))}\s+(.+)/");

            if (preg_match('/^(\d+)\s*\D+\s*(\d+)$/', $flightNumber, $m) && !empty($m[$key])) {
                $s->airline()->number($m[$key]);

                $key = $key + 1;

                if ($key > 2) {
                    $key = 1;
                }
            } elseif (preg_match('/^\d+$/', $flightNumber)) {
                $s->airline()->number($flightNumber);
            }

            $date = null;
            $dateVal = $this->http->FindSingleNode("preceding::tr[not(*[3]) and normalize-space()][1]/descendant::text()[normalize-space()][2]", $segment, true, "/^.*\d.*$/");

            if (preg_match("/^(?<dayAndMonth>.{3,}?)[-,.\s]+(?<year>\d{4})$/", $dateVal, $m)) {
                // Sep. 29, 2018

                if (intval($m['year']) > 1975) {
                    $date = strtotime($dateVal);
                } else {
                    // it-760317423.eml
                    $dateRelative = empty($this->year) ? 0 : strtotime($this->year . '-01-01');
                    $date = $dateRelative ? EmailDateHelper::parseDateRelative($m['dayAndMonth'], $dateRelative, true, '%D%, %Y%') : null;
                }
            }

            // depCode
            $depCode = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)][1]", $segment, true, '/^[A-Z]{3}$/');
            $s->departure()->code($depCode);

            // depDate
            $timeDep = $this->http->FindSingleNode('./*[1]/' . $xpathFragmentTime, $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            // arrCode
            $arrCode = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)][1]", $segment, true, '/^[A-Z]{3}$/');
            $s->arrival()->code($arrCode);

            // arrDate
            $timeArr = $this->http->FindSingleNode('./*[3]/' . $xpathFragmentTime, $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            // seats
            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = array_filter($this->http->FindNodes("following::table[not(.//tr/*[3]) and normalize-space()][1]/descendant::text()[{$this->eq($this->t('Seat Assignment:'))}]/ancestor::td[1]/descendant::text()[{$this->starts($s->getDepCode())} and {$this->contains($this->t(' to '))} and {$this->contains($s->getArrCode())}]/following::text()[normalize-space()][1]", $segment, '/^\d{1,5}[A-Z]$/'));

                if (count($seats) === 0) {
                    $seats = array_filter($this->http->FindNodes("following::table[not(.//tr/*[3]) and normalize-space()][1]/descendant::text()[{$this->eq($this->t('Seat Assignment:'))}]/ancestor::td[1]/descendant::text()[{$this->starts($s->getDepCode())} and {$this->contains($this->t(' to '))} and {$this->contains($s->getArrCode())}]", $segment, '/:\s*(\d{1,5}[A-Z])$/'));
                }

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            if ($segments->length === 1) {
                $totalTime = $this->http->FindSingleNode("./following::tr[normalize-space(.)][1]/descendant::text()[{$this->eq($this->t('Total Time:'))}]/following::text()[normalize-space(.)][1]", $segment);
                $totalTimeParts = preg_split('/\s*\|\s*/', $totalTime);

                // duration
                if (!empty($totalTimeParts[0]) && preg_match('/^\d[hrs min\d]+$/i', $totalTimeParts[0])) {
                    // 1 hrs 59 min
                    $s->extra()->duration($totalTimeParts[0]);
                }

                // stops
                if (!empty($totalTimeParts[1]) && preg_match("/^{$this->opt($this->t('Nonstop'))}$/i", $totalTimeParts[1])) {
                    $s->extra()->stops(0);
                }

                // seat
                if (empty($s->getSeats())) {
                    $seats = array_filter($this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::*[(self::tr or self::p) and not(.//tr or .//p) and normalize-space()][1][{$this->eq($this->t('Your Seats Have Been Assigned'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", null, "/^(\d+[A-Z])(?:\s*\||$)/"));

                    if (count($seats) > 0) {
                        $s->extra()->seats($seats);
                    } else {
                        $seat = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::*[(self::tr or self::p) and not(.//tr or .//p) and normalize-space()][1][{$this->eq($this->t('Your Seat Has Been Assigned'))}] ]/*[normalize-space()][2]/descendant::p[normalize-space()][1]", null, true, "/^\d+[A-Z]$/");
                        $s->extra()->seat($seat, false, true);
                    }
                }
            }

            // travellers
            $passengerNames = array_filter($this->http->FindNodes($xpathFragmentPassenger . '[contains(@class,"mbl-pax-text")]', $segment, "/^{$patterns['travellerName']}$/u"));

            if (count($passengerNames) === 0) {
                $passengerNames = array_filter($this->http->FindNodes($xpathFragmentPassenger . "[ ancestor::tr[1]/following-sibling::tr[{$this->starts($this->t('Seat Assignment:'))}] ]", $segment, "/^{$patterns['travellerName']}$/u"));
            }

            if (count($passengerNames)) {
                $passengers = array_merge($passengers, $passengerNames);
            }
        }

        // travellers
        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }

        if (!$confirmation && $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length === 0 && $segments->length > 0) {
            // it-751909118.eml
            $f->general()->noConfirmation();
        }

        //########
        //# CAR ##
        //########

        $cars = $this->http->XPath->query("//tr[preceding-sibling::tr[{$this->starts($this->t('Location:'))}] and {$this->starts($this->t('Pick-up Time:'))} and {$this->contains($this->t('Drop-off Time:'))}]");

        foreach ($cars as $carRoot) {
            // it-95006645.eml

            $car = $email->add()->rental();

            $company = $this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('Confirmation Number:'))}]/preceding-sibling::tr[normalize-space()]", $carRoot);

            if (($code = $this->normalizeProvider($company))) {
                $car->program()->code($code);
            } else {
                $car->extra()->company($company);
            }

            $confirmation = $this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('Confirmation Number:'))}]", $carRoot);

            if (preg_match("/^({$this->opt($this->t('Confirmation Number:'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
                $car->general()->confirmation($m[2], rtrim($m[1], ' :'));
            }

            $vehicle = $this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('Vehicle:'))}]", $carRoot, true, "/^{$this->opt($this->t('Vehicle:'))}\s*(.+)$/");
            $car->car()->type($vehicle);

            $location = $this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('Location:'))}]", $carRoot, true, "/^{$this->opt($this->t('Location:'))}\s*(.{3,})$/");
            $car->pickup()->location($location);
            $car->dropoff()->same();

            if (preg_match("/^{$this->opt($this->t('Pick-up Time:'))}\s*(.{6,}?)[\s\|]*{$this->opt($this->t('Drop-off Time:'))}\s*(.{6,})$/", $this->http->FindSingleNode('.', $carRoot), $m)) {
                // Pick-up Time: 05-31-2021 10:30 AM | Drop-off Time: 05-31-2021 09:30 PM
                $car->pickup()->date2($this->normalizeDate($m[1]));
                $car->dropoff()->date2($this->normalizeDate($m[2]));
            }
        }
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis' => ['Avis Car Rental'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 05-31-2021 09:30 PM
            '/^(\d{1,2})-(\d{1,2})-(\d{4})\s+(\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/',
        ];
        $out = [
            '$3-$1-$2 $4',
        ];

        return preg_replace($in, $out, $text);
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }
}
