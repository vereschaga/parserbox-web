<?php

namespace AwardWallet\Engine\resnexus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "resnexus/it-157308822.eml, resnexus/it-159012254.eml, resnexus/it-886413714.eml, resnexus/it-93697345.eml, resnexus/it-94752992.eml, resnexus/it-94923537.eml, resnexus/it-96006326.eml, resnexus/it-96115257.eml";
    public $subjects = [
        '/^.+\s\-\sConfirmation\:\s*\#\d{5,}/',
    ];

    public $lang = 'en';

    public $subject;

    public static $dictionary = [
        "en" => [
            'Confirmation is'                 => ['Confirmation is', 'Your Confirmation is', 'Your confirmation number is'],
            'HotelNameStart'                  => ['staying with us at the', 'you stay with us at ', 'Welcome to'],
            'HotelNameEnd'                    => ['. Please', '!'],
            'Adults'                          => ['Adult', 'Guests'],
            'Children'                        => ['Children', 'Child'],
            'cancellationStart'               => ['Cancellation policy for remainder of 2021 season', 'Cancellation Policy', 'CANCELLATION POLICY for Non-Holiday Reservations', '30-DAY CANCELLATION POLICY', 'FEES and CANCELLATIONS', 'CANCELLATION POLICY', 'You can cancel'],
            'cancellationEnd'                 => ['Cancellation Policy for 2022 and beyond', 'HOLIDAYS', 'If you have any concerns', 'CHECK IN & CHECK OUT', 'Deposit/Guarantee Policy:', 'Holiday and peak', 'Parking Policy', 'OUR RURAL INTERNETWE', 'without penalty'],
            'CANCELLATION POLICY'             => ['CANCELLATION POLICY', 'Cancellation Policy'],
            'Things To Know Before Your Stay' => [
                'Things To Know Before Your Stay',
                'We look forward to your visit and want you to know',
            ],
            'Depart:'                        => ['Depart:', 'Check-Out::'],
            'RESERVATION INFORMATION'        => ['RESERVATION INFORMATION', 'RESERVATION/PURCHASE INFORMATION'],
            'Thank You For Your Reservation' => ['Thank You For Your Reservation', 'Thank you again for choosing to stay with us'],
            'Check in:'                      => ['Check in:', 'Check-In'],
            'Check-out:'                     => ['Check-out:', 'Check out:', 'Check-Out Time:', 'Check-Out'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@resnexus.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (
            !empty($this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 1 and " . $this->starts('RESERVATION INFORMATION (#') . "]/following-sibling::tr[count(*[normalize-space()]) = 2 and *[2][" . $this->contains('Estimated Arrival') . "]]/following-sibling::tr[" . $this->contains(['Adult', 'Guest']) . "]"))
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]resnexus\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmaltion = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation is'))}]", null, true, "/\#*\s*(\d{4,})/");

        if (empty($confirmaltion)) {
            $confirmaltion = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}])[1]", null, true, "/\(\#(\d{4,})/");
        }

        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'RESERVATION INFORMATION')]", null, "/\-\s*(\D+)/u");

        if (count($travellers) > 0) {
            $h->general()
                ->travellers($travellers, true);
        } else {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(\w+)\s*\,$/");

            if (!empty($traveller)) {
                $h->general()
                    ->traveller($traveller, false);
            }
        }
        $h->general()
            ->confirmation($confirmaltion);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CANCELLATION POLICY'))}]/ancestor::tr[1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CANCELLATION POLICY'))}]/ancestor::*[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'FEES and CANCELLATIONS')]/ancestor::tr[1]");
        }

        if (preg_match("#{$this->opt($this->t('cancellationStart'))}(.+){$this->opt($this->t('cancellationEnd'))}#s", $cancellation, $m)) {
            $h->general()
                ->cancellation(trim($m[1], '.:*'));
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'You can cancel')]");

            if (!empty($cancellation) && strlen($cancellation) < 2000) {
                $h->general()
                    ->cancellation(trim($cancellation, '*'));
            }
        }

        $hotelName = $this->re("/(?:Fwd\:\s*|^)(.+)\s*\-\s*(?:Confirmation|\#)/", $this->subject);

        if (empty($hotelName)) {
            $hotelName = $this->re("/Reservation Confirmation: #\d+ for (.+)/", $this->subject);
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('HotelNameStart'))}]", null, true, "/{$this->opt($this->t('HotelNameStart'))}\s*(\D+)\s*{$this->opt($this->t('HotelNameEnd'))}/");
        }

        if (empty($hotelName)) {
            $hotelInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank You For Selecting')]/ancestor::*[1]");

            if (preg_match("/^{$this->opt($this->t('Thank You For Selecting'))}\s*(?<hotelName>.+)\s*{$this->opt($this->t('For Your Lodging Experience While Visiting'))}/", $hotelInfo, $m)) {
                $hotelName = $m['hotelName'];
            }
        }

        if (!empty($hotelName)) {
            $h->hotel()
                ->name(str_replace('FW: ', '', $hotelName));
        }

        $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('in the'))} and {$this->contains($this->t($h->getHotelName()))}]");

        if (preg_match("/{$this->opt($this->t('in the'))}(.+)\.\s+/", $address, $m)) {
            $address = $m[1];
        }

        if (preg_match("/{$this->opt($this->t('is located on'))}(.+)\.\s+/", $address, $m)) {
            $address = $m[1];
        }

        if (empty($address)) {
            $hotelInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank You For Selecting')]/ancestor::*[1]");

            if (preg_match("/{$this->opt($this->t('For Your Lodging Experience While Visiting'))}\s*(?<address>.+)$/", $hotelInfo, $m)) {
                $address = $m['address'];
            }
        }

        if (strlen($address) > 100) {
            $address = '';
        }

        if (empty($address) && !empty($hotelName)) {
            $addressText = $this->http->FindSingleNode('//text()[normalize-space()="' . $hotelName . '"]/ancestor::*[1]');

            if (preg_match("/{$hotelName}\s*(?<phone>[\d\-]+)\s*www\.\S+\s*(?<address>.+)/", $addressText, $m)) {
                $h->hotel()
                    ->phone($m['phone'])
                    ->address($m['address']);
            }
        }

        if (empty($h->getAddress()) && !empty($hotelName)) {
            $addressText = implode(" ", $this->http->FindNodes("//text()[{$this->contains(trim($hotelName))}]/ancestor::table[1]/descendant::text()[normalize-space()]"));

            if (empty($addressText) || stripos($addressText, 'RESERVATION INFORMATION') !== false) {
                $addressText = $this->http->FindSingleNode("//text()[{$this->contains(trim($hotelName) . ' |')}]")
                ?? $this->http->FindSingleNode("//text()[{$this->contains(trim($hotelName) . ' LLC |')}]");
            }

            if (strlen($addressText) > 200) {
                $addressText = '';
            }

            if (preg_match("/{$hotelName}\s*(?<address>.+)\s(?<phone>[\d\-]+)/", $addressText, $m)
            || preg_match("/{$hotelName}\s*\|\s+(?<address>.+)\s\|\s+(?<phone>[\d\-]+)/", $addressText, $m)) {
                $h->hotel()
                    ->phone($m['phone'])
                    ->address(str_replace('LLC | ', '', trim($m['address'], '|')));
            }
        }

        if (empty($h->getAddress()) && !empty($hotelName)) {
            $addressText = implode("\n", $this->http->FindNodes("//text()[{$this->starts(trim($hotelName))}]/ancestor::table[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^(?<address>.+)\n\s*(?<phone>[\d\-\s\(\)ext]+)/", $addressText, $m)) {
                $h->hotel()
                    ->phone($m['phone'])
                    ->address($m['address']);
            }
        }

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        $datesText = $this->http->FindSingleNode("//text()[normalize-space()='Thank You For Your Reservation']/following::text()[normalize-space()][1]");
        $guestTextFromRate = [];

        if (preg_match("/^\w+\,?\s*([\d\/]+)\s*\-\s*\w+\,?\s*([\d\/]+)$/", $datesText, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        } elseif (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}]/following::text()[{$this->starts($this->t('Depart:'))}][1]"))) {
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}]/following::text()[{$this->starts($this->t('Depart:'))}][1]/ancestor::tr[1]/descendant::text()[{$this->contains($this->t('Adults'))}][1]", null, true, "/^\s*\w+\,\s*(\w+\s*\d+\,\s*\d{4})/")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}]/following::text()[{$this->starts($this->t('Depart:'))}][1]", null, true, "/\s*\w+\,\s*(\w+\s*\d+\,\s*\d{4})/")));
        } else {
            $nodes = $this->http->FindNodes("//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}]/ancestor::tr[1]/following::tr[normalize-space()][2]//text()[normalize-space()]");

            if (!empty($nodes) && !preg_match("/^.*\d{4}.*$/", $nodes[0])) {
                $type = array_shift($nodes);
                $h->addRoom()
                    ->setType($type);

                if (!empty($nodes) && preg_match("/^\s*(\d[\d,. ]* *[^\d\s]{1,5}|[^\d\s]{1,5} *\d[\d,. ]*)\s*$/", $nodes[0])) {
                    array_shift($nodes);
                }

                foreach ($nodes as $i => $node) {
                    if (preg_match("/^([\w\s,.]+\b\d{4})\b\s*—(.+)/", $node, $m)) {
                        if ($i == 0) {
                            $guestTextFromRate[] = $m[2];
                            $h->booked()
                                ->checkIn($this->normalizeDate($m[1]));
                        } else {
                            $date = $this->normalizeDate($m[1]);

                            if (!empty($date)) {
                                $date = strtotime("+1 day", $date);
                            }
                            $h->booked()
                                ->checkOut($date);
                        }
                    } else {
                        break;
                    }
                }
            }
        }

        $timeText = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-IN / CHECK-OUT']/following::text()[normalize-space()][1]");

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate()) && preg_match("/Check-In time is\s*([\d\:]+\s*A?P?M).*Check-Out time is\s*([\d\:]+\s*[AP]M)/su", $timeText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        }

        if (empty($timeText)) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Estimated Arrival'))}]", null, true, "/{$this->opt($this->t('Estimated Arrival'))}[\s\-]+([\d\:]+\s*A?P?M)/iu");

            if (empty($checkIn)) {
                $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in:'))}]", null, true, "/{$this->opt($this->t('Check in:'))}[\s\-]+([\d\:]+\s*A?P?M)/iu");
            }

            if (empty($checkIn)) {
                $checkIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in:'))}][not({$this->contains($this->t('Tent'))})]", null, true, "/{$this->opt($this->t('Check in:'))}[\s\-]+([\d\:]+\s*A?P?M)/iu");
            }

            if (!empty($h->getCheckInDate()) && !empty($checkIn)) {
                $h->booked()
                    ->checkIn(strtotime($checkIn, $h->getCheckInDate()));
            }

            $checkOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-out:'))}\s*([\d\:]+\s*[AP]M)/i");

            if (empty($checkOut)) {
                $checkOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out:'))}][not({$this->contains($this->t('Tent'))})]", null, true, "/{$this->opt($this->t('Check-out:'))}[\s\-]+([\d\:]+\s*A?P?M)/iu");
            }

            if (!empty($h->getCheckOutDate()) && !empty($checkOut)) {
                $h->booked()
                    ->checkOut(strtotime($checkOut, $h->getCheckOutDate()));
            }
        }

        $roomCount = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Room ')]");

        if (count($roomCount) > 0) {
            $h->booked()
                ->rooms(count($roomCount));
        }

        $guestTexts = $this->http->FindNodes("//text()[{$this->starts($this->t('Depart:'))}]/preceding::b[1]/ancestor::tr[1]/following::text()[{$this->contains($this->t('Adults'))}][1]");

        if (empty($guestTexts) && !empty($guestTextFromRate)) {
            $guestTexts = $guestTextFromRate;
        }

        foreach ($guestTexts as $guestText) {
            if (preg_match("/(?<adult>\d+)\s*{$this->opt($this->t('Adults'))}\,\s*(?<kids>\d+)\s*{$this->opt($this->t('Children'))}/", $guestText, $m)
                || preg_match("/(?<adult>\d+)\s*{$this->opt($this->t('Adults'))}/", $guestText, $m)) {
                $h->booked()
                    ->guests($m['adult'] + $h->getGuestCount());

                if (isset($m['kids'])) {
                    $h->booked()
                        ->kids($m['kids'] + $h->getKidsCount());
                }
            }
        }

        $timeIn = $this->http->FindSingleNode("//text()[" . $this->contains(['Check-in time:', 'Check-in is any time after', 'Check-In time is', 'Check-in:', 'Check-in time is after']) . "]",
            null, true, "/" . $this->opt(['Check-in time:', 'Check-in is any time after', 'Check-In time is', 'Check-in:', 'Check-in time is after']) . "\s*(\d{1,2}:\d{2}\s*[AP]\.?M)/i");
        $timeOut = $this->http->FindSingleNode("//text()[" . $this->contains(['Check-out time:', 'check-out is at', 'Check-Out time is', 'check-out time is']) . "]",
            null, true, "/" . $this->opt(['Check-out time:', 'check-out is at', 'Check-Out time is', 'check-out time is']) . "\s*(\d{1,2}:\d{2}\s*[AP]\.?M)/i");

        if (!empty($timeIn) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime(str_replace('.', '', $timeIn), $h->getCheckInDate()));
        }

        if (!empty($timeOut) && !empty($h->getCheckOutDate())) {
            $h->booked()
                ->checkOut(strtotime(str_replace('.', '', $timeOut), $h->getCheckOutDate()));
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::tr[1]");

        if (empty($priceText) || stripos($priceText, 'Tax') === false) {
            $priceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total: ')]/ancestor::table[1]");
        }

        $priceArray = explode(':', $priceText);
        $price = array_pop($priceArray);
        $currency = $this->re("/^(\D{1,3})\s*\d/", $price);

        if (!empty($currency) && preg_match_all("/([\d\.\,]+)/", $price, $m)) {
            if (count($m[1]) === count($priceArray)) {
                foreach ($m[1] as $k => $v) {
                    if ($priceArray[$k] == 'Sub Total') {
                        $h->price()
                            ->currency($currency)
                            ->cost(PriceHelper::parse($v, $currency));
                    } elseif ($priceArray[$k] == 'Total') {
                        $h->price()
                            ->currency($currency)
                            ->total(PriceHelper::parse($v, $currency));
                    } elseif ($priceArray[$k] == 'Tax Total') {
                        $h->price()
                            ->currency($currency)
                            ->tax(PriceHelper::parse($v, $currency));
                    } else {
                        $h->price()
                            ->fee($priceArray[$k], PriceHelper::parse($v, $currency));
                    }
                }
            } elseif (preg_match("/^{$this->opt($this->t('Total:'))}\s*\D(?<total>\d+\.\,?\d+)\s*{$this->opt($this->t('Tax:'))}\s*\D(?<tax>\d+\,?\.\d++)\s*{$this->opt($this->t('Sub Total:'))}\s*(?<currency>\D)(?<cost>[\d\.\,]+)\s*$/", $priceText, $m)) {
                $h->price()
                        ->currency($m['currency'])
                        ->cost(PriceHelper::parse($m['cost'], $m['currency']))
                        ->tax(PriceHelper::parse($m['tax'], $m['currency']))
                        ->total(PriceHelper::parse($m['total'], $m['currency']));

                if (isset($m['fee'])) {
                    $h->price()
                            ->fee('Tax', $m['fee']);
                }
            }
        }

        $roomTypeNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Depart:'))}]/preceding::b[1]/ancestor::tr[1]");

        foreach ($roomTypeNodes as $roomTypeRoot) {
            $room = $h->addRoom();

            $roomType = $this->http->FindSingleNode("./descendant::td[1]", $roomTypeRoot);

            if (!empty($roomType)) {
                $room->setType(preg_replace("/\S\d+$/", "", $roomType));
            }

            $rates = preg_replace("/—\s+.+\—\s+/u", "- ", $this->http->FindNodes("./ancestor::table[1]/ancestor::td[1]/descendant::span[not(contains(normalize-space(), 'Depart:'))]", $roomTypeRoot));

            if (count($rates) > 0) {
                $room->setRate(implode("; ", $rates));
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\/(\d+)\/(\d{2})$#', //6/25/21
            "#^(\w+)\s*(\d+)\,\s*(\d{4})$#", //Jul 11, 2021
        ];
        $out = [
            '$2.$1.20$3',
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/if canceled within (\d+ hours) of booking/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }

        if (preg_match('/In the event of a cancellation\, please notify us (\d+ hours?) prior to arrival\, (\d+A?P?M)/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1], $m[2]);
        }

        if (preg_match('/Cancellations must be made by (\d+ a?p?m) the day PRIOR to your scheduled arrival/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 day', $m[1]);
        }

        if (preg_match('/You can cancel with (\d+ days?) notice without penalty/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1], '-1 hour');
        }
    }
}
