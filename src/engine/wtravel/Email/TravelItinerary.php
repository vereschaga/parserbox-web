<?php

namespace AwardWallet\Engine\wtravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItinerary extends \TAccountChecker
{
    public $mailFiles = "wtravel/it-609262318.eml, wtravel/it-80360839.eml, wtravel/it-80823822.eml, wtravel/it-81643721.eml";
    public $subjects = [
        'Travel Itinerary for:',
    ];

    public $date;
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Ticket No.' => ['Ticket No.', 'Conf. No.'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@foxworldtravel.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Fox World Travel')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Total Invoiced'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket No.'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]foxworldtravel\.com$/', $from) > 0;
    }

    public function FlightParse(Email $email, $nodes)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();
        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Total Invoiced')]/ancestor::tr[1]/preceding-sibling::tr[contains(normalize-space(), '/')]/descendant::td[1]");
        $f->general()
            ->travellers(array_unique(array_filter($travellers)), true);

        $accounts = array_unique(array_filter(
            $this->http->FindNodes("//td[{$this->eq($this->t('Frequent Flier'))}]/following-sibling::td[1]", null, "/^\s*([A-Z]{2}\s*[A-Z\d]+)\s*$/")));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        $tickets = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket No.')]", null, "/{$this->opt($this->t('Ticket No.'))}\s*(\d+)$/u");

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        foreach ($nodes as $root) {
            $date = $this->http->FindSingleNode("./preceding::tr[1]/td[1]", $root);
            $this->logger->debug('$date = ' . print_r($date, true));

            $s = $f->addSegment();

            $airInfo = $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root);

            if (count($airInfo) === 4) {
                $s->airline()
                    ->name($this->re("/^([A-Z]+)/", $airInfo[0]))
                    ->number($this->re("/^[A-Z]+\s*(\d{2,4})/", $airInfo[0]));

                if ($operator = $this->http->FindSingleNode("./descendant::td[4]/descendant::text()[normalize-space()='Flight Operated By']/following::text()[normalize-space()][1]", $root)) {
                    $s->airline()
                        ->operator($operator);
                }
                $conf = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Confirmation')]/following::text()[normalize-space()][1]", $root);
                $s->airline()
                    ->confirmation($conf);

                $s->extra()
                    ->duration($airInfo[1])
                    ->miles($airInfo[2])
                    ->aircraft($airInfo[3]);

                if ($seat = $this->http->FindSingleNode("./following::text()[normalize-space()='Seat'][1]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[1]", $root)) {
                    $s->extra()
                        ->seat($seat);
                }
            }

            $depInfo = $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root);

            if (count($depInfo) >= 3) {
                $s->departure()
                    ->code($depInfo[0])
                    ->name($depInfo[1])
                    ->date($this->normalizeDate($date . ' ' . $depInfo[2]));

                if (isset($depInfo[3])) {
                    $s->departure()
                        ->terminal($this->re("/Terminal\:(.+)/", $depInfo[3]));
                }
            }

            $arrInfo = $this->http->FindNodes("./descendant::td[3]/descendant::text()[normalize-space()]", $root);

            if (count($arrInfo) >= 3) {
                $s->arrival()
                    ->code($arrInfo[0])
                    ->name($arrInfo[1])
                    ->date($this->normalizeDate($date . ' ' . $arrInfo[2]));

                if (preg_match("/Terminal\:(.+)/", $arrInfo[3], $m)) {
                    $s->arrival()
                        ->terminal($m[1]);
                }
            }

            if (empty($s->getDepDate()) && empty($s->getArrDate()) && empty($s->getDepCode()) && empty($s->getArrCode())) {
                // $f->removeSegment($s);
            }
        }

        return true;
    }

    public function HotelParse(Email $email, $nodes)
    {
        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $hotelInfo = implode(' ', $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(.+)\s+Phone\:\s+(.+)\sFax\:\s+(.+)\s+Conf. No.\s([A-Z\d]+)$/", $hotelInfo, $m)) {
                $h->hotel()
                    ->name($this->http->FindSingleNode("./preceding::tr[1]/descendant::td[2]", $root))
                    ->address($m[1])
                    ->phone($m[2])
                    ->fax($m[3]);

                $h->general()
                    ->confirmation($m[4])
                    ->cancellation($this->http->FindSingleNode("./following::tr[1]", $root, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/u"));
            }

            $h->booked()
                ->rooms($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^(\d+)\s*Rooms/"))
                ->guests($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/(\d+)\s*Adults/"));

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()='Check In']/following::text()[normalize-space()][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()='Check Out']/following::text()[normalize-space()][1]", $root)));

            $rate = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()='Nightly Rate']/following::text()[normalize-space()][1]", $root);

            if (!empty($rate)) {
                $room = $h->addRoom();
                $room->setRate($rate);
            }

            $account = $this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(), 'Frequent Guest:')]", $root, true, "/{$this->opt($this->t('Frequent Guest:'))}\s*(.+)/");

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }
        }
    }

    public function RentalParse(Email $email, $nodes)
    {
        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->setCompany($this->http->FindSingleNode("./preceding-sibling::tr[2]/descendant::td[2]", $root));

            $r->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/{$this->opt($this->t('Conf. No.'))}\s*(.+)/"));

            $r->car()
                ->image($this->http->FindSingleNode("./descendant::td[2]/descendant::img/@src[1]", $root))
                ->type($this->http->FindSingleNode("./preceding::tr[1]/descendant::td[2]/text()[not(contains(normalize-space(), 'miles'))]", $root));

            $pickUpInfo = implode(" ", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Pick Up\s*(.+[\d\:]+a?p?m)\s*(.+)\sPhone\:\s*([\d\-]+)/", $pickUpInfo, $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m[1]))
                    ->location($m[2])
                    ->phone($m[3]);
            }

            $dropOffInfo = implode(" ", $this->http->FindNodes("./descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Drop Off\s*(.+[\d\:]+a?p?m)\s*(.+)\sPhone\:\s*([\d\-]+)/", $dropOffInfo, $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m[1]))
                    ->location($m[2])
                    ->phone($m[3]);
            }

            $account = $this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(), 'Frequent Renter:')]", $root, true, "/{$this->opt($this->t('Frequent Renter:'))}\s*(.+)/");

            if (!empty($account)) {
                $r->program()
                    ->account($account, true);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // $this->date = strtotime($parser->getDate());

        $date = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Invoiced'))}]", null, true, "/ - (.+)/"));

        if (!empty($date)) {
            $this->date = $date;
        }

        $email->obtainTravelAgency();

        $flightNode = $this->http->XPath->query("//img[contains(@src, 'plane-arrow.gif')]/ancestor::tr[1]");

        if ($flightNode->length == 0) {
            $flightNode = $this->http->XPath->query("//text()[normalize-space()='Seat' or normalize-space()='Flight Operated By' or normalize-space()='Anytime']/ancestor::tr[1]/preceding::tr[1]");
        }

        if ($flightNode->length > 0) {
            $this->FlightParse($email, $flightNode);
        }

        $hotelNode = $this->http->XPath->query("//text()[normalize-space()='Check In']/ancestor::tr[1]");

        if ($hotelNode->length > 0) {
            $this->hotelParse($email, $hotelNode);
        }

        $rentalNode = $this->http->XPath->query("//text()[normalize-space()='Pick Up']/ancestor::tr[1]");

        if ($rentalNode->length > 0) {
            $this->RentalParse($email, $rentalNode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date = ' . print_r($date, true));
        $year = date('Y', $this->date);
        $in = [
            // Sunday, January 7th 8:55am
            '#^\s*([[:alpha:]]+)\s*[\s,]\s*([[:alpha:]]+)\s*(\d+)[[:alpha:]]{2}\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
            // Mon, Feb 24, 2020 3:00pm
            '#^\s*[[:alpha:]]+\s*[\s,]\s*([[:alpha:]]+)\s*(\d+)\s*[\s,]\s*(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
            // Mon, Feb 24, 2020
            '#^\s*[[:alpha:]]+\s*[\s,]\s*([[:alpha:]]+)\s*(\d+)\s*[\s,]\s*(\d{4})\s*$#ui',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
            '$2 $1 $3, $4',
            '$2 $1 $3',
        ];
        $date = preg_replace($in, $out, $date);
        $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>[[:alpha:]\-]+), (?<date>\d+ [[:alpha:]]+ .+)/u", $date, $m)) {
            if ($year > 2000) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

                return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
            }
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            return strtotime($date);
        }

        return null;
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
