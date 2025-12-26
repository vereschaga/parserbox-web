<?php

namespace AwardWallet\Engine\travelinc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class EitinPlain extends \TAccountChecker
{
    public $mailFiles = "travelinc/it-44519735.eml, travelinc/it-45642793.eml, travelinc/it-45904861.eml";

    public $reFrom = ["@travelinc.com"];
    public $reBody = [
        'en' => ['eitin.travelinc.com', 'SEGMENT |'],
    ];
    public $reSubject = [
        'FINAL ITINERARY',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'SEGMENT'               => 'SEGMENT',
            'Your Travel Itinerary' => 'Your Travel Itinerary',
            'Nightly Rate'          => ['Nightly Rate', 'Nightly Rates'],
        ],
    ];
    private $keywordProv = 'travelinc';
    private $recLoc;
    private $tripId;
    private $rentalKeywords = [
        'hertz' => [
            'Hertz',
        ],
        'national' => [
            'National',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email, $body);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (!empty($parser->getHTMLBody())) {
            return false;
        }
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $lang => $reBody) {
            if ($this->detectBody($body)) {
                return $this->assignLang($body);
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i",
                            $headers["subject"]) > 0)
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
        $types = 3; // flight | hotel | rental
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email, string $text)
    {
        $addInfo = $this->strstrArr($text, $this->t('Additional Information:'));

        if (!empty($addInfo)) {
            $this->recLoc = $this->re("#{$this->opt($this->t('Record Locator'))}:[ ]+([A-Z\d]+)#", $addInfo);
            $this->tripId = $this->re("#{$this->opt($this->t('Trip ID'))}:[ ]+([A-Z\d]+)#", $addInfo);
        }

        $segments = $this->splitter("#^(\s*::[ ]+.+?[ ]+{$this->t('SEGMENT')}[ ]+\|)#m", $text);
        $flights = $hotels = $cars = [];
        $result = true;

        foreach ($segments as $segment) {
            if (preg_match("#\s*::[ ]+(.+?)[ ]+{$this->t('SEGMENT')}[ ]+\|#", $segment, $m)) {
                if (in_array($m[1], (array) $this->t('AIR'))) {
                    $flights[] = $segment;
                } elseif (in_array($m[1], (array) $this->t('HOTEL'))) {
                    $hotels[] = $segment;
                } elseif (in_array($m[1], (array) $this->t('CAR'))) {
                    $cars[] = $segment;
                } else {
                    $this->logger->debug('unknown type reservation: ' . $m[1]);
                    $email->add()->flight(); // added empty reservation to broke result
                    $result = false;
                }
            }
        }

        if (!empty($flights)) {
            $this->parseFlights($email, $flights);
        }

        if (!empty($hotels)) {
            $this->parseHotels($email, $hotels);
        }

        if (!empty($cars)) {
            $this->parseCars($email, $cars);
        }

        return $result;
    }

    private function parseFlights(Email $email, array $flights)
    {
        $this->logger->notice(__METHOD__);
        $airs = [];

        foreach ($flights as $flight) {
            $rl = $this->re("#{$this->opt($this->t('Confirmation No'))}:[ ](\w+)\n#", $flight);
            $airs[$rl][] = $flight;
        }

        foreach ($airs as $rl => $segments) {
            $r = $email->add()->flight();

            if (!empty($this->recLoc)) {
                $r->ota()
                    ->confirmation($this->recLoc, ((array) $this->t('Record Locator'))[0]);
            }

            if (!empty($this->tripId)) {
                $r->ota()
                    ->confirmation($this->tripId, ((array) $this->t('Trip ID'))[0]);
            }

            $pax = $accounts = $tickets = [];
            $r->general()
                ->confirmation($rl, ((array) $this->t('Confirmation No'))[0]);

            foreach ($segments as $segment) {
                $s = $r->addSegment();

                if (preg_match("#{$this->t('SEGMENT')}[ ]+\|[ ]+(.+?)[ ]+::\s+{$this->opt($this->t('Airline'))}:[ ]+(.+?)[ ]+{$this->opt($this->t('Flight'))}[ ]+(\d+)#",
                    $segment, $m)) {
                    $date = $this->normalizeDate($m[1]);
                    $s->airline()
                        ->name($m[2])
                        ->number($m[3]);
                }

                if (preg_match("#{$this->opt($this->t('Takeoff'))}:[ ]+(\d+:\d+(?:\s*[ap]m)?)[ ]+(.+?)(?:[ ]+\|[ ]+{$this->t('Terminal')}:[ ](.+))?\n#i",
                    $segment, $m)) {
                    if (isset($date)) {
                        $s->departure()
                            ->date(strtotime($m[1], $date));
                    }
                    $s->departure()
                        ->name($m[2])
                        ->noCode();

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->departure()->terminal($m[3]);
                    }
                }

                if (preg_match("#{$this->opt($this->t('Landing'))}:[ ]+(\d+:\d+(?:\s*[ap]m)?)[ ]+(.+?)(?:[ ]+\|[ ]+{$this->t('Terminal')}:[ ](.+))?\n#i",
                    $segment, $m)) {
                    if (isset($date)) {
                        $s->arrival()
                            ->date(strtotime($m[1], $date));
                    }
                    $s->arrival()
                        ->name($m[2])
                        ->noCode();

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()->terminal($m[3]);
                    }
                }

                if (preg_match("#\n(.+?)[ ]*(?:\[[ ]*([A-Z]{1,2})[ ]*\])?[ ]*{$this->t('Class')}[ ]*\|[ ]*(.+?)[ ]*\|[ ]*(.+?)[ ]*\|[ ]*(\d[hm].*)#i",
                    $segment, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->aircraft($m[3])
                        ->meal($m[4])
                        ->duration($m[5]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->extra()->bookingCode($m[2]);
                    }
                } elseif (preg_match("#\n(.+?)[ ]*(?:\[[ ]*([A-Z]{1,2})[ ]*\])?[ ]*{$this->t('Class')}[ ]*\|[ ]*(.+)[ ]*\|[ ]*(\d[hm].*)#i",
                    $segment, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->meal($m[3])
                        ->duration($m[4]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->extra()->bookingCode($m[2]);
                    }
                }

                $pax[] = $this->re("#{$this->opt($this->t('Passenger'))}:[ ]*(.+?)[ ]*\|#", $segment);
                $tickets[] = $this->re("#{$this->opt($this->t('Ticket #'))}:[ ]*([\d\-]+)(?:[ ]+|$)#", $segment);
                $accounts[] = $this->re("#{$this->opt($this->t('FF#'))}:[ ]*(.+?)(?:[ ]+|$)#", $segment);
                $seat = $this->re("#{$this->opt($this->t('Seat'))}:[ ]*(\d+[A-z])(?:[ ]+|$)#", $segment);

                if (!empty($seat)) {
                    $s->extra()->seat($seat);
                }
            }
            $pax = array_unique($pax);

            if (!empty($pax)) {
                $r->general()
                    ->travellers($pax, true);
            }

            $tickets = array_unique($tickets);

            if (!empty($tickets)) {
                $r->issued()
                    ->tickets($tickets, false);
            }
            $accounts = array_unique($accounts);

            if (!empty($accounts)) {
                $r->program()
                    ->accounts($accounts, false);
            }
        }
    }

    private function parseHotels(Email $email, array $hotels)
    {
        $this->logger->notice(__METHOD__);

        foreach ($hotels as $segment) {
            $r = $email->add()->hotel();
            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('Confirmation No'))}:[ ](\w+)#", $segment),
                    ((array) $this->t('Confirmation No'))[0])
                ->traveller($this->re("#{$this->opt($this->t('Reservation name'))}:[ ]+(.+)#", $segment), true)
                ->cancellation($this->re("#{$this->opt($this->t('Cancel Policy'))}:[ ]+(.+)#", $segment));

            $acc = $this->re("#{$this->opt($this->t('Frequent Guest #'))}:[ ]+(.+)#", $segment);

            if (!empty($acc)) {
                $r->program()->account($acc, false);
            }

            if (preg_match("#{$this->t('SEGMENT')}[ ]+\|[ ]+(.+?)[ ]+::\s+{$this->opt($this->t('Name'))}:[ ]+(.+)#",
                $segment, $m)) {
                $date = $this->normalizeDate($m[1]);
                $r->hotel()->name($m[2]);
                $r->booked()
                    ->checkIn(strtotime($this->re("#{$this->opt($this->t('Check-in'))}:[ ]+(\d+:\d+.*)#", $segment),
                        $date));
            }
            $r->booked()
                ->checkOut($this->normalizeDate($this->re("#{$this->opt($this->t('Check-out'))}:[ ]+(.+)#", $segment)))
                ->rooms($this->re("#{$this->opt($this->t('Rooms'))}:[ ]+(\d+)[ ]+{$this->t('room')}#", $segment));
            $r->hotel()
                ->address($this->re("#{$this->opt($this->t('Address'))}:[ ]+(.+)#", $segment))
                ->phone($this->re("#{$this->opt($this->t('Phone'))}:[ ]+(.+)#", $segment))
                ->fax($this->re("#{$this->opt($this->t('Fax'))}:[ ]+(.+)#", $segment));

            $room = $r->addRoom();
            $desr = trim($this->re("#{$this->opt($this->t('Room Desc'))}:[ ]+(.+)#",
                    $segment) . ' ' . $this->re("#{$this->opt($this->t('Other Info'))}:[ ]+(.+)#", $segment));

            if (!empty($desr)) {
                $room->setDescription($desr);
            }
            $room->setRate($this->re("#{$this->opt($this->t('Nightly Rate'))}:[ ]+(.+)#", $segment));
            $total = $this->re("#{$this->opt($this->t('Total Rate'))}:[ ]+(.+)#", $segment);
            $total = $this->getTotalCurrency($total);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
            $this->detectDeadLine($r);
        }
    }

    private function parseCars(Email $email, array $cars)
    {
        $this->logger->notice(__METHOD__);

        foreach ($cars as $segment) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('Confirmation No'))}:[ ](\w+)#", $segment),
                    ((array) $this->t('Confirmation No'))[0])
                ->traveller($this->re("#{$this->opt($this->t('Driver'))}:[ ]+(.+)#", $segment), true);

            $acc = $this->re("#{$this->opt($this->t('Corp Discount No.'))}:[ ]+(.+)#", $segment);

            if (!empty($acc)) {
                $r->program()->account($acc, false);
            }

            if (preg_match("#{$this->t('SEGMENT')}[ ]+\|[ ]+(.+?)[ ]+::\s+{$this->opt($this->t('Name'))}:[ ]+(.+?)[ ]*\n#",
                $segment, $m)) {
                $date = $this->normalizeDate($m[1]);

                if (!empty($code = $this->getProviderByKeyword($m[2]))) {
                    $r->program()
                        ->code($code);
                } else {
                    $r->program()->keyword($m[2]);
                }
                $r->extra()->company($m[2]);

                $r->pickup()
                    ->date(strtotime($this->re("#{$this->opt($this->t('Pick-up'))}:[ ]+(\d+:\d+.*)#", $segment),
                        $date));
            }
            $r->dropoff()
                ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Drop-off'))}:[ ]+(.+)#", $segment)));

            $r->pickup()
                ->location($this->re("#{$this->opt($this->t('Pickup Location'))}:[ ]+(.+)#", $segment))
                ->phone($this->re("#{$this->opt($this->t('Phone'))}:[ ]+(.+)#", $segment));

            if (preg_match("#{$this->opt($this->t('Phone'))}:([\d\-\(\)\+\. \/]+)(\D+.+)#iu", $r->getPickUpLocation(),
                $m)) {
                //FE: it-45642793.eml
                $r->dropoff()->phone($r->getPickUpPhone());
                $r->pickup()->phone($m[1])->location($m[2]);
            }
            $drop = $this->re("#{$this->opt($this->t('DropOff Location'))}:[ ]+(.+)#u", $segment);

            if (!empty($drop)) {
                $r->dropoff()
                    ->location($drop);
            } elseif (!empty($this->re("#({$this->opt($this->t('DropOff Location'))}:\s+{$this->opt($this->t('Car Description'))})#",
                $segment))
            ) {
                $r->dropoff()->same();
            }

            $r->car()
                ->type($this->re("#{$this->opt($this->t('Car Description'))}:[ ]+(.+)#", $segment));
            $total = $this->re("#{$this->opt($this->t('Total Rate'))}:[ ]+(.+)#", $segment);
            $total = $this->getTotalCurrency($total);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            // 10/14/2019
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*$#',
            // 10/16/2019 12:00 PM
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#iu',
        ];
        $out = [
            '$3-$1-$2',
            '$3-$1-$2, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^(\d+) Day Cancellation Required/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days', '00:00');
        } else {
            $h->booked()
                ->parseNonRefundable("#^Advance Purchase Entire Stay Nonrefundable#i");
        }
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
                if ($this->stripos($body, $reBody[0]) && $this->stripos($body, $reBody[1])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['SEGMENT'], $words['Your Travel Itinerary'])) {
                if ($this->stripos($body, $words['SEGMENT'])
                    && $this->stripos($body, $words['Your Travel Itinerary'])
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
        $node = strtoupper($node);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }

    private function strstrArr(string $haystack, $needle, bool $before_needle = false): ?string
    {
        $needles = (array) $needle;

        foreach ($needles as $needle) {
            $str = strstr($haystack, $needle, $before_needle);

            if (!empty($str)) {
                return $str;
            }
        }

        return null;
    }

    private function getProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->rentalKeywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
