<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UnitedReservation2021 extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-105967387.eml, mileageplus/it-106590374.eml, mileageplus/it-106904154.eml";
    public $subjects = [
        'united.com reservation for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@united.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'www.united.com')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'United') and contains(normalize-space(), 'Airlines, Inc.')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing United')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This is your confirmation email'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'Confirmation number']/ancestor::tr[1]", null, true, "/Confirmation number\s*([A-Z\d]+)/"), 'Confirmation number');

        $f->general()
            ->travellers(array_filter($this->http->FindNodes("//text()[normalize-space() = 'Travelers']/ancestor::tr[1]/following-sibling::tr/descendant::td[1]/descendant::text()[not(contains(normalize-space(), 'number')) and not(contains(normalize-space(), '*')) and not(contains(normalize-space(), 'Traveler'))]")), true);

        $accounts = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Travelers']/ancestor::tr[1]/following-sibling::tr/descendant::td[1]/descendant::text()[contains(normalize-space(), 'number')]/following::text()[contains(normalize-space(), '*')][1]"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, true);
        }

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), ':') and (contains(normalize-space(), 'AM') or contains(normalize-space(), 'PM'))]/ancestor::tr[1]/following::tr[normalize-space()][2]/descendant::text()[contains(normalize-space(), ' to ')]");

        if ($nodes->length > 0) {
            $this->parseSegment_1($nodes, $f);
        }

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'H, ')]/ancestor::table[1]/descendant::text()[normalize-space()][2][not(contains(normalize-space(), 'baggage')) and not(contains(normalize-space(), 'PM'))  and not(contains(normalize-space(), 'AM'))]/ancestor::table[1]/descendant::tr[1][contains(normalize-space(), ', ')]");

        if ($nodes->length > 0) {
            $this->parseSegment_2($nodes, $f);
        }

        $totalText = $this->http->FindSingleNode("//text()[normalize-space()='Purchase summary']/following::text()[starts-with(normalize-space(), 'Total')]");

        if (stripos($totalText, 'miles') !== false) {
            $f->price()
                ->spentAwards(str_replace(',', '', $this->re("/Total\s*([\d\,]+\s*.+)/", $totalText)));

            $totalText = $this->http->FindSingleNode("//text()[normalize-space()='Purchase summary']/following::text()[starts-with(normalize-space(), 'Total')]/following::text()[normalize-space()][1]");

            if (preg_match("/[+]\s*(\S)([\d\.]+)/", $totalText, $m)) {
                $f->price()
                    ->total($m[2])
                    ->currency($m[1]);
            }
        } else {
            if (preg_match("/Total\s*(\S)([\d\,\.]+)/u", $totalText, $m)) {
                $f->price()
                    ->total(str_replace(',', '', $m[2]))
                    ->currency($m[1]);
            }
        }

        $cost = $this->http->FindSingleNode("//text()[normalize-space()='Fare']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (stripos($cost, 'miles') !== false) {
            $f->price()
                ->cost('0');
        } else {
            $f->price()
                ->cost(str_replace(',', '', $this->re("/\s*\D([\d\.\,]+)/u", $cost)));
        }

        $fees = $this->http->XPath->query("//text()[normalize-space()='Fare']/ancestor::tr[1]/following-sibling::tr/descendant::td[2]/descendant::text()[contains(normalize-space(), '.')]");

        foreach ($fees as $root) {
            $feeSum = $this->http->FindSingleNode(".", $root, true, "/\D([\d\,\.]+)/");
            $feeName = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[1]", $root);
            $f->price()
                ->fee($feeName, str_replace(',', '', $feeSum));
        }
    }

    public function parseSegment_1(\DOMNodeList $nodes, Flight $f)
    {
        foreach ($nodes as $i => $root) {
            $airlineInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);

            if (!empty($airlineInfo)) {
                $s = $f->addSegment();

                if (preg_match("/^([A-Z\d]{2})\s*(\d{1,4})\s*\((.+)\)/u", $airlineInfo, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    $s->extra()
                        ->aircraft($m[3]);
                }

                $destinationText = $this->http->FindSingleNode(".", $root);

                if (preg_match("/.*\(([A-Z]{3})(?:\)|\s).*\(([A-Z]{3})(?:\)|\s)/", $destinationText, $m)) {
                    $s->departure()
                        ->code($m[1]);
                    $s->arrival()
                        ->code($m[2]);

                    $date = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[starts-with(normalize-space(), '{$m[1]}')]/preceding::text()[contains(normalize-space(), ', 20')][not(contains(normalize-space(), 'H,'))][1]", $root);

                    $timeInfo = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[normalize-space()][2]", $root);

                    if (preg_match("/(\d+\:\d+\s*A?P?M)\s*(\d+\:\d+\s*A?P?M)/u", $timeInfo, $m)) {
                        $s->departure()
                            ->date(strtotime($date . ' ' . $m[1]));

                        $s->arrival()
                            ->date(strtotime($date . ' ' . $m[2]));
                    }
                }

                $duration = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root, true, "/[A-Z]{3}(.+)[A-Z]{3}/");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $seatsText = '';
                $flightColumn = $this->http->FindNodes("//text()[normalize-space()='Travelers']/following::text()[normalize-space()='FLIGHT']/ancestor::tr[1]/following::tr[1]/descendant::td[2]/descendant::div");
                $seatColumn = $this->http->FindNodes("//text()[normalize-space()='Travelers']/following::text()[normalize-space()='FLIGHT']/ancestor::tr[1]/following::tr[1]/descendant::td[3]/descendant::div");

                if (count($flightColumn) == count($seatColumn)) {
                    foreach ($flightColumn as $key => $point) {
                        $seatsText = $seatsText . $point . " " . $seatColumn[$key] . "\n";
                    }
                }

                if (preg_match_all("/{$s->getDepCode()}[\s\-]+{$s->getArrCode()}\s*(\d{1,2}[A-Z])/u", $seatsText, $m)) {
                    $s->setSeats($m[1]);
                }
            }
        }
    }

    public function parseSegment_2(\DOMNodeList $nodes, Flight $f)
    {
        foreach ($nodes as $i => $root) {
            $airlineInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);

            if (!empty($airlineInfo)) {
                $s = $f->addSegment();

                if (preg_match("/^([A-Z\d]{2})\s*(\d{1,4})/u", $airlineInfo, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                $destinationText = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'H')][1]/ancestor::tr[1]/following::tr[1]", $root);

                if (preg_match("/.*\(([A-Z]{3})(?:\)|\s).*\(([A-Z]{3})(?:\)|\s)/", $destinationText, $m)) {
                    $s->departure()
                        ->code($m[1]);
                    $s->arrival()
                        ->code($m[2]);

                    $date = $this->http->FindSingleNode(".", $root);

                    $timeInfo = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'H')][1]/ancestor::tr[1]/preceding::tr[1]", $root);

                    if (preg_match("/(\d+\:\d+\s*[AP]?M)\s*(\d+\:\d+\s*[AP]?M)/ui", $timeInfo, $m)) {
                        $s->departure()
                            ->date(strtotime($date . ' ' . $m[1]));

                        $s->arrival()
                            ->date(strtotime($date . ' ' . $m[2]));
                    }
                }

                $duration = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'H')][1]/ancestor::tr[1]", $root, true, "/[A-Z]{3}(.+)[A-Z]{3}/");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $seatsText = '';
                $flightColumn = $this->http->FindNodes("//text()[normalize-space()='Travelers']/following::text()[normalize-space()='FLIGHT']/ancestor::tr[1]/following::tr[1]/descendant::td[2]/descendant::div");
                $seatColumn = $this->http->FindNodes("//text()[normalize-space()='Travelers']/following::text()[normalize-space()='FLIGHT']/ancestor::tr[1]/following::tr[1]/descendant::td[3]/descendant::div");

                if (count($flightColumn) == count($seatColumn)) {
                    foreach ($flightColumn as $key => $point) {
                        $seatsText = $seatsText . $point . " " . $seatColumn[$key] . "\n";
                    }
                }

                if (preg_match_all("/{$s->getDepCode()}[\s\-]+{$s->getArrCode()}\s*(\d{1,2}[A-Z])/u", $seatsText, $m)) {
                    $s->setSeats($m[1]);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
