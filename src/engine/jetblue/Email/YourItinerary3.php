<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItinerary3 extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-726946509.eml, jetblue/it-727185609.eml, jetblue/it-728386399.eml";
    public $subjects = [
        'Your JetBlue Vacations itinerary',
    ];

    public $lang = 'en';
    public $hotelsList = [];

    public static $dictionary = [
        "en" => [
            'Manage your booking' => ['Manage your booking', 'View my booking'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.jetblue.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'JetBlue Vacations')]")->length > 0

            && (($this->http->XPath->query("//text()[{$this->contains($this->t('Your flights'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight confirmation code'))}]")->length > 0)

                || ($this->http->XPath->query("//text()[{$this->contains($this->t('Your hotel'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel confirmation number'))}]")->length > 0))

            && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage your booking'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.jetblue\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[normalize-space()='JetBlue Vacations booking number']/ancestor::td[1]", null, true, "/{$this->opt($this->t('JetBlue Vacations booking number'))}\s*(\d+)/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf, 'JetBlue Vacations booking number');
        }

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Details about your upcoming vacay')]/following::text()[normalize-space()='Package total'][1]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,\']+)$/", $total, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Details about your upcoming vacay')]/following::text()[normalize-space()='JetBlue Vacations Package'][1]/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)/");

            if (!empty($cost)) {
                $email->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Details about your upcoming vacay')]/following::text()[normalize-space()='Taxes & fees'][1]/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)/");

            if (!empty($tax)) {
                $email->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your flights'))}]")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your hotel'))}]")->length > 0) {
            $this->ParseHotel($email);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your car rental'))}]")->length > 0) {
            $this->ParseRental($email);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Airport transfer voucher'))}]")->length > 0) {
            $this->ParseTransfer($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[normalize-space()='JetBlue Vacations booking number']/preceding::text()[normalize-space()='Flight confirmation code']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Flight confirmation code'))}\s*([A-Z\d]{6})/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Details about your upcoming vacay')]/following::text()[normalize-space()='JetBlue Vacations booking number'][1]/preceding::text()[normalize-space()='Flight confirmation code']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Flight confirmation code'))}\s*([A-Z\d]{6})/");
        }
        $f->general()
           ->confirmation($conf);

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Your flights']/following::text()[normalize-space()='Flight confirmation code']/following::table[1]/descendant::tr");

        $travellers = [];
        $accounts = [];

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $text = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depCode>[A-Z]{3})\s*\-\s*(?<arrCode>[A-Z]{3})\n\w+\,\s*(?<flightDate>\w+\s*\d+\,\s*\d{4})\n(?<depTime>[\d\:]+\s*A?P?M)\s*\-\s*(?<arrTime>[\d\:]+\s*A?P?M)\n(?<aName>.+)\s+(?<fNumber>\d{1,4})/u", $text, $m)
            || preg_match("/^.*\((?<depCode>[A-Z]{3})\)\s*\-\s*.*\((?<arrCode>[A-Z]{3})\)\n\w+\,\s*(?<flightDate>\w+\s*\d+\,\s*\d{4})\n(?<depTime>[\d\:]+\s*A?P?M)\s*\-\s*(?<arrTime>[\d\:]+\s*A?P?M)\n(.*\s)?(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/u", $text, $m)) {
                $s->airline()
                   ->name($m['aName'])
                   ->number($m['fNumber']);

                $s->departure()
                   ->code($m['depCode'])
                   ->date(strtotime($m['flightDate'] . ', ' . $m['depTime']));

                $s->arrival()
                   ->code($m['arrCode'])
                   ->date(strtotime($m['flightDate'] . ', ' . $m['arrTime']));
            }

            if (preg_match_all("/(?<pax>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n(?:Frequent Flier:\s*\D+(?<account>\d+)\n)?(?<seat>\d+[A-Z])/", $text, $m)
            || preg_match_all("/^(?<pax>[[:alpha:]][.\/\'’[:alpha:] ]*[[:alpha:]])(?:\n|$)(?:Frequent Flier:\s*\D+(?<account>\d+))?/m", $text, $m)) {
                $travellers = array_merge($m['pax'], $travellers);
                $accounts = array_merge($m['account'], $accounts);

                foreach (array_filter($m['seat']) as $seat) {
                    $pax = $this->re("/([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n(?:Frequent Flier:\s*\D+\d+\n)?\s*$seat/", $text);

                    if (!empty($pax)) {
                        $s->addSeat($seat, true, true, $pax);
                    } else {
                        $s->addSeat($seat);
                    }
                }
            }
        }

        $f->setTravellers(array_filter(array_unique($travellers)));

        foreach (array_filter(array_unique($accounts)) as $account) {
            if (preg_match("/(?<pax>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\nFrequent Flier:\s*(?<programm>\D+)$account(?:\n|$)/", $text, $m)) {
                $f->addAccountNumber($account, false, $m['pax'], $m['programm']);
            } else {
                $f->addAccountNumber($account, false);
            }
        }
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Hotel confirmation number']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->cancellation($this->http->FindSingleNode("./following::text()[normalize-space()='Hotel cancellation policy'][1]/following::text()[normalize-space()][1]", $root))
                ->confirmation(str_replace(['|', ' '], '', $this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Hotel confirmation number'))}\s*([A-z\d\|\-\s]{4,})$/")));

            $hotelInfo = implode("\n", $this->http->FindNodes("./following::tr[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<hotelName>.+)\n(?<address>.+)\n*(?<phone>[+]?[\d\-\(\)\s]+.*)?$/", $hotelInfo, $m)) {
                $h->general()
                    ->travellers($this->http->FindNodes("./following::text()[normalize-space()='Reservation info'][1]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()]", $root));

                $h->hotel()
                    ->name($m['hotelName'])
                    ->address($m['address']);

                if (isset($m['phone'])) {
                    $h->hotel()
                        ->phone($m['phone']);
                }
            }

            //$roomNodes = $this->http->XPath->query("./following::tr[1]/ancestor::tr[1]/descendant::text()[starts-with(normalize-space(), 'Room')]", $root);
            $roomNodes = $this->http->XPath->query("./following::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), 'night')]/preceding::text()[normalize-space()][1]", $root);

            foreach ($roomNodes as $roomRoot) {
                $room = $h->addRoom();
                $room->setDescription($this->http->FindSingleNode(".", $roomRoot));

                $inDate = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check-in')][1]/following::text()[normalize-space()][1]", $roomRoot);
                $h->booked()
                    ->checkIn($this->normalizeDate($inDate));

                $outDate = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check-out')][1]/following::text()[normalize-space()][1]", $roomRoot);
                $h->booked()
                    ->checkOut($this->normalizeDate($outDate));

                $adult = $this->http->FindSingleNode("./following::text()[normalize-space()][2][contains(normalize-space(), 'adult')]", $roomRoot, true, "/^(\d+)\s+/");

                if (!empty($adult)) {
                    $h->booked()
                        ->guests($adult);
                }

                $child = $this->http->FindSingleNode("./following::text()[normalize-space()][3][contains(normalize-space(), 'child')]", $roomRoot, true, "/^(\d+)\s+/");

                if ($child !== null) {
                    $h->booked()
                        ->kids($child);
                }
            }
            $this->detectDeadLine($h);

            $chekHotel = $h->getHotelName() . $h->getCheckInDate();

            if (!in_array($chekHotel, $this->hotelsList)) {
                $this->hotelsList[] = $chekHotel;
            } else {
                $email->removeItinerary($h);
            }
        }
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Car rental confirmation number']/ancestor::td[1]/descendant::text()[normalize-space()][2]", null, true, "/^([A-Z\d]+)$/"));

        $r->car()
            ->model($this->http->FindSingleNode("//text()[normalize-space()='Car rental confirmation number']/ancestor::td[1]/descendant::text()[normalize-space()][3][contains(normalize-space(), 'or similar')]"))
            ->type($this->http->FindSingleNode("//text()[normalize-space()='Car rental confirmation number']/ancestor::td[1]/descendant::text()[normalize-space()][3][contains(normalize-space(), 'or similar')]/following::text()[normalize-space()][1]"));

        $pickUpText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Your car rental']/following::text()[normalize-space()='Booking details']/following::text()[normalize-space()][1][contains(normalize-space(), 'Pick-up location')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/Pick-up location\n(?<date>.+)\n(?<location>(?:.+\n){1,5})(?<phone>[+][\(\)\-\d]+)/", $pickUpText, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['date']))
                ->location(str_replace("\n", " ", $m['location']))
                ->phone($m['phone']);
        }

        $dropOffText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Your car rental']/following::text()[normalize-space()='Booking details']/ancestor::tr[1]/following::tr[2][contains(normalize-space(), 'Drop-off location')]/descendant::text()[normalize-space()]"));

        if (preg_match("/Drop-off location\n(?<date>.+)\n(?<location>(?:.+\n){1,5})(?<phone>[+][\(\)\-\d]+)/", $dropOffText, $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m['date']))
                ->location(str_replace("\n", " ", $m['location']))
                ->phone($m['phone']);
        }
    }

    public function ParseTransfer(Email $email)
    {
        $r = $email->add()->transfer();

        $r->general()
            ->noConfirmation();

        $r->general()
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Airport transfer voucher']/following::text()[normalize-space()='Booking details']/ancestor::tr[1]/following::tr[3]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'passengers'))]"));

        $pointsText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Details about your upcoming vacay')]/following::text()[normalize-space()='Airport transfer voucher'][1]/following::text()[normalize-space()][not(contains(normalize-space(), 'voucher'))][1]");

        $airportName = '';
        $airportCode = '';
        $hotelName = '';
        $hotels = [];
        $flights = [];

        if (preg_match("/between\s+(?<airportName>.+)\s*\((?<airportCode>[A-Z]{3})\)\s+and\s+all.+hotel/", $pointsText, $m)
         || preg_match("/Free Airport Transfer.*\s+\((?<airportCode>[A-Z]{3})\)\s*\-\s*(?<airportName>\w+\s*\w*\s*\w*)\,?\s*\-?\s*(?<hotelLocation>.+hotel)/i", $pointsText, $m)) {
            $airportName = trim(str_replace('Airport', '', $m['airportName']));
            $airportCode = $m['airportCode'];
            $itineraries = $email->getItineraries();

            foreach ($itineraries as $itinerary) {
                if ($itinerary->getType() === 'hotel') {
                    $hotels[] = $itinerary;
                }

                if ($itinerary->getType() === 'flight') {
                    $flights[] = $itinerary;
                }
            }

            foreach ($hotels as $hotel) {
                if (stripos($hotel->getAddress(), $airportName) !== false) {
                    $hotelName = $hotel->getHotelName() . ', ' . $hotel->getAddress();
                } elseif (isset($m['hotelLocation'])) {
                    $hotelName = $m['hotelLocation'];
                }
            }
        }

        foreach ($flights as $flight) {
            foreach ($flight->getSegments() as $segment) {
                if ($segment->getArrCode() === $airportCode) {
                    $s = $r->addSegment();

                    $s->departure()
                        ->code($airportCode)
                        ->name($airportName)
                        ->date($segment->getArrDate());

                    $s->arrival()
                        ->name($hotelName)
                        ->noDate();
                }
            }
        }

        foreach ($flights as $flight) {
            foreach ($flight->getSegments() as $segment) {
                if ($segment->getDepCode() === $airportCode) {
                    $s = $r->addSegment();

                    $s->departure()
                        ->noDate()
                        ->name($hotelName);

                    $s->arrival()
                        ->code($airportCode)
                        ->name($airportName)
                        ->date(strtotime('-3 hours', $segment->getDepDate()));

                    if (empty($s->getDepName()) || empty($s->getArrName())) {
                        $email->removeItinerary($r);
                    }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*\-\s*([\d\:]+\s*A?P?M?)$#u", //Fri, Nov 22, 2024 - 04:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Hotel cancellations made on or after\s*([\d\/]+)\s*will be subject to a fee of/", $cancellationText, $m)
        || preg_match("/Changes and cancellations made on or after\s*([\d\/]+)\s*/", $cancellationText, $m)) {
            $h->setDeadline(strtotime($m[1]));
        }
    }
}
