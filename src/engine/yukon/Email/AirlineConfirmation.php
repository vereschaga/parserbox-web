<?php

namespace AwardWallet\Engine\yukon\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirlineConfirmation extends \TAccountChecker
{
    public $mailFiles = "yukon/it-647037577.eml, yukon/it-670124094.eml";
    public $subjects = [
        "Air North, Yukon's Airline-Confirmation",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Departs' => ['Departs', 'DEPARTS'],
            'Stops'   => ['Stops', 'STOPS'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyairnorth.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Your Air North, Yukon\'s Airline Itinerary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your itinerary number is'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departs'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Stops'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyairnorth\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your itinerary number is')]/ancestor::*[1]", null, true, "/^{$this->opt($this->t('Your itinerary number is'))}\s*(\d+)\./"));

        $travellers = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='Departs']/ancestor::div[1]/descendant::div[not(contains(normalize-space(), 'Warning'))]/descendant::div[normalize-space()][1]")));

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Itinerary Total']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Total Fare']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\.\,]+)$/");

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $feeNodes = $this->http->XPath->query("//text()[normalize-space()='Itinerary Total']/ancestor::tr[1]/preceding-sibling::tr[not(contains(normalize-space(), 'Total Fare'))]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $feeRoot, true, "/^([\d\.\,]+)$/");

                if (!empty($feeName) && !empty($feeSumm)) {
                    $f->price()
                        ->fee($feeName, $feeSumm);
                }
            }
        }

        $link = $this->http->FindSingleNode("//a[contains(normalize-space(),'your itinerary') and normalize-space(@href)]/@href");
        $this->logger->debug('URL: ' . $link);

        if (!empty($link)) {
            $http2 = clone $this->http;
            $http2->GetURL($link);

            $xpath = "//text()[starts-with(normalize-space(),'DEPARTURE FLIGHT:') or starts-with(normalize-space(),'RETURN FLIGHT:')]/ancestor::table[1]/descendant::tr[starts-with(normalize-space(),'Flight Date')]/following-sibling::tr[not(contains(normalize-space(),'Cancelled'))]";
            $segments = $http2->XPath->query($xpath);

            if ($segments->length === 0) {
                $linkFiltered = preg_replace("/^.*?\/__(https?:\/\/.+)__.*$/i", '$1', $link);
                $this->logger->debug('URL(filtered): ' . $linkFiltered);
                $http2->GetURL($linkFiltered);
                $segments = $http2->XPath->query($xpath);
            }

            foreach ($segments as $root) {
                $segInfo = implode("\n", $http2->FindNodes("./descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<date>.+\s*\d{4})\n(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\n+(?<depTime>[\d:]+\s*A?P?M)\n+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\n+(?<arrTime>[\d:]+\s*A?P?M)\n*(?<aN>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fN>\d{1,5})\n/", $segInfo, $m)) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($m['aN'])
                        ->number($m['fN']);

                    $s->departure()
                        ->name($m['depName'])
                        ->code($m['depCode'])
                        ->date(strtotime($m['date'] . ', ' . $m['depTime']));

                    $s->arrival()
                        ->name($m['arrName'])
                        ->code($m['arrCode'])
                        ->date(strtotime($m['date'] . ', ' . $m['arrTime']));
                }

                $aircraft = $http2->FindSingleNode("self::tr[count(*)=6]/*[5]", $root, false);
                $status = $http2->FindSingleNode("self::tr[count(*)=6]/*[6]", $root, false);
                $s->extra()->aircraft($aircraft, false, true)->status($status, false, true);

                $seatsInfo = $http2->FindNodes("./following::text()[normalize-space()='Passenger Name'][1]/ancestor::tr[1]/following-sibling::tr", $root);

                foreach ($seatsInfo as $seatInfo) {
                    if (preg_match("/^\d+\s+(?<pax>\D+)\s+[A-Z]{3}[\d\.\,]+\s+(?<seat>\d+[A-Z])/", $seatInfo, $m)) {
                        $s->extra()
                            ->seat($m['seat'], false, false, $m['pax']);
                    }
                }
            }
        } else {
            $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), '› Flight')]/ancestor::h2[1]");

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $flightDay = '';
                $airlineName = '';
                $flightNumber = '';
                $depName = '';
                $depTime = '';
                $arrName = '';
                $arrTime = '';
                $aircraft = '';
                $seats = [];

                $flightInfo = $this->http->FindSingleNode(".", $root);

                if (preg_match("/^\w+\s*(?<flightDay>\w+\s*\w+,\s*\d{4})[\s›]*Flight\s*(?<aN>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fN>\d{1,5})[\s\-]*(?<depName>.+)\s+to\s+(?<arrName>.+)/", $flightInfo, $m)) {
                    $depName = $m['depName'];
                    $arrName = $m['arrName'];
                    $flightDay = $m['flightDay'];
                    $airlineName = $m['aN'];
                    $flightNumber = $m['fN'];
                }

                $flightTime = $this->http->FindSingleNode("./following::h4[1]", $root);

                if (preg_match("/^Departs\s*(?<depTime>[\d\:]+\s*A?P?M?)[\s›]*Arrives\s*(?<arrTime>[\d\:]+\s*A?P?M)[\s›]*Stops\s*\d+[\s›]*(?<aircraft>.+)?$/u", $flightTime, $m)) {
                    $depTime = $m['depTime'];
                    $arrTime = $m['arrTime'];

                    if (isset($m['aircraft']) && !empty($m['aircraft'])) {
                        $aircraft = $m['aircraft'];
                    }
                }

                foreach ($travellers as $traveller) {
                    $seat = $this->http->FindSingleNode("./following::text()[{$this->eq($traveller)}][1]/ancestor::div[2]/descendant::div[2]", $root, true, "/^Seat\s*(\d+[A-Z])$/iu");

                    if (!empty($seat)) {
                        $seats[] = $seat;
                    }
                }

                $stops = $this->http->FindSingleNode("./following::h4[1]", $root, true, "/Stops?\s(\d+)/");

                if (!empty($stops) && $stops == 1) {
                    $s->airline()
                        ->name($airlineName)
                        ->number($flightNumber);

                    if (!empty($aircraft)) {
                        $s->extra()
                            ->aircraft($aircraft);
                    }

                    $s->departure()
                        ->name($depName)
                        ->date(strtotime($flightDay . ', ' . $depTime))
                        ->noCode();

                    $s->arrival()
                        ->noCode()
                        ->noDate();

                    if (count($seats) > 0) {
                        $s->extra()
                            ->seats($seats);
                    }

                    $s = $f->addSegment();

                    $s->airline()
                        ->name($airlineName)
                        ->number($flightNumber);

                    if (!empty($aircraft)) {
                        $s->extra()
                            ->aircraft($aircraft);
                    }

                    $s->departure()
                        ->noDate()
                        ->noCode();

                    $s->arrival()
                        ->name($arrName)
                        ->date(strtotime($flightDay . ', ' . $arrTime))
                        ->noCode();

                    if (count($seats) > 0) {
                        $s->extra()
                            ->seats($seats);
                    }
                } elseif ($stops == 0) {
                    $s->airline()
                        ->name($airlineName)
                        ->number($flightNumber);

                    if (!empty($aircraft)) {
                        $s->extra()
                            ->aircraft($aircraft);
                    }

                    $s->departure()
                        ->name($depName)
                        ->date(strtotime($flightDay . ', ' . $depTime))
                        ->noCode();

                    $s->arrival()
                        ->name($arrName)
                        ->date(strtotime($flightDay . ', ' . $arrTime))
                        ->noCode();

                    $s->extra()
                        ->stops(0);

                    if (count($seats) > 0) {
                        $s->extra()
                            ->seats($seats);
                    }
                } else {
                    $f->removeSegment($s);
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
