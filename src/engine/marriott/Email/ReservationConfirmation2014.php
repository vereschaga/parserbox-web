<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation2014 extends \TAccountChecker
{
    public $mailFiles = "marriott/it-1.eml, marriott/it-11.eml, marriott/it-11953770.eml, marriott/it-12.eml, marriott/it-13.eml, marriott/it-14.eml, marriott/it-1472153.eml, marriott/it-1606347.eml, marriott/it-1608403.eml, marriott/it-1748463.eml, marriott/it-1827079.eml, marriott/it-1828090.eml, marriott/it-19.eml, marriott/it-20.eml, marriott/it-22.eml, marriott/it-2301899.eml, marriott/it-2301917.eml, marriott/it-2301920.eml, marriott/it-3490858.eml, marriott/it-36.eml, marriott/it-37.eml, marriott/it-3852739.eml, marriott/it-40.eml, marriott/it-46345266.eml, marriott/it-47651189.eml, marriott/it-48640777.eml, marriott/it-6.txt, marriott/it-7.eml, marriott/it-8.eml";
    public $reFrom = ["@marriott.com", "@pkghlrss.com"];
    public $reSubject = [
        //en
        "Reservation Confirmation",
        "Reservation Cancellation",
        "Your Reservation at the",
        "Reservation Cancellation for the ",
    ];
    public $reBody = [
        'Ritz',
        'Marriott', 'Renaissance Nashville Hotel',
        'groupcampaigns',
        '.passkey.com',
    ];
    public $reBody2 = [
        "en"=> ["Check-in:", "Arrival Date:"],
    ];

    public $subject;

    public static $dictionary = [
        "en" => [
            "Confirmation Number:" => [
                "Confirmation Number:",
                "Online Confirmation Number:",
                "Reservation Confirmation number:",
                "Marriott Confirmation Number:",
                "Marriott Confirmation Number",
                "Confirmation number:",
                "Reservation Acknowledgement Number:",
                "Hotel Confirmation Number:",
                "Hotel Confirmation number:",
                "Passkey Acknowledgement Number:",
                "Passkey Confirmation Number:",
                "Online Acknowledgement Number:",
            ],
            "Your Resort:"               => ["Your Resort:", "Your hotel:", "Your hotel:", "Hotel:"],
            "Hotel:"                     => ["Hotel:", 'Your Hotel'],
            "Canceling your Reservation" => ["Canceling your Reservation", "Canceling Your Reservation", 'Cancelling your Reservation'],
            "Modifying Your Reservation" => ["Modifying Your Reservation", "Modifying your Reservation"],
            "Room Policies:"             => ["Room Policies:", "Room Rate Policies:"],
            "guestNamePhrases"           => ["Reservation for ", "Reservation cancellation for "],
        ],
    ];

    public $lang = '';

    private static $providers = [
        "marriott"   => ["from" => "@marriott.com", "body" => "marriott.com"],
        "gcampaigns" => ["from" => "@pkghlrss.com", "body" => "passkey.com"],
    ];

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
        $fl = false;

        if (isset($headers["from"])) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers["from"], $reFrom) !== false) {
                    $fl = true;

                    break;
                }
            }
        }

        if ($fl) {
            foreach ($this->reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                $finded = true;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->reBody2 as $res) {
            foreach ($res as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang => $res) {
            foreach ($res as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseHtml($email);
        $email->setType('ReservationConfirmation2014' . ucfirst($this->lang));

        if (null !== ($prov = $this->getProvider($parser->getCleanFrom())) && ($prov !== 'marriott')) {
            $email->setProviderCode($prov);
        }

        return $email;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $r = $email->add()->hotel();

        // confirmation, travellers
        $confNo = array_values(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Confirmation Number:")) . "]",
            null, "#(.+:\s+\w+)#")));

        $confAny = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Confirmation Number:")) . "]/following::text()[normalize-space()][1]",
            null, "#^\s*([\dA-Z]{5,})\s*$#"));

        foreach ($confAny as $cn) {
            $confName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Confirmation Number:"))}][./following::text()[normalize-space()!=''][1][normalize-space()='{$cn}']]");

            if (empty($confName)) {
                $confName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Resort:'))}]/preceding::text()[{$this->eq($this->t("Confirmation Number:"))}][1][./following::text()[normalize-space()!=''][1][normalize-space()='{$cn}']]");
            }
            $confNo[] = $confName . ": " . $cn;
        }

        $guestName = $this->nextText($this->t("Guest name:"))
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("guestNamePhrases"))}]", null, true, "/{$this->opt($this->t("guestNamePhrases"))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/u")
        ;

        $addedConfNo = [];

        foreach ($confNo as $i => $cn) {
            if (preg_match("/^(.+?)\s*:\s(\w+)$/", $cn, $m) && !in_array($m[2], $addedConfNo)) {
                $r->general()
                    ->confirmation($m[2], $m[1], $i === 0);
                $addedConfNo[] = $m[2];
            }
        }
        $dateRes = strtotime($this->normalizeDate($this->nextText("Reservation confirmed:")));

        if ($this->http->XPath->query("//*[{$this->contains($this->t('Cancellation number:'))}]")->length === 0) {
            $r->general()->traveller($guestName);

            if (!empty($dateRes)) {
                $r->general()->date($dateRes);
            }
            $cancelled = false;
        } else {
            if (!empty($guestName)) {
                $r->general()->traveller($guestName);
            }

            if (!empty($dateRes)) {
                $r->general()->date($dateRes);
            }
            $cancelled = true;
        }

        // hotel
        $hotelName = $this->nextText($this->t("Your Resort:"));

        if (!empty($hotelName)) {
            $r->hotel()
                ->name($hotelName);
        }

        // address, phone, fax
        $address = null;

        if (!empty($r->getHotelName())) {
            $addressRows = $this->http->FindNodes("(//text()[{$this->eq($this->t('Hotel:'))}])[last()]/following::text()[normalize-space()!=''][1][{$this->eq($r->getHotelName())}]/ancestor::*[self::p or self::div or self::td or self::span][count(.//text()[normalize-space()!=''])>1][1]/descendant::text()[normalize-space()!='']");
            $addressRows = array_slice($addressRows, 1, 6);
            $addressRows = array_filter($addressRows, function ($item) {
                return !preg_match("/{$this->opt($this->t('Please Note'))}/i", $item);
            });

            if (preg_match("/^(.{3,}?)[,\s]*{$this->opt($this->t('Check-in Time'))}/", implode(', ', $addressRows),
                $m)) {
                $address = $m[1];
            } elseif ($this->http->XPath->query("(//text()[{$this->eq($this->t('Hotel:'))}])[last()]/following::text()[normalize-space()!=''][1][{$this->eq($r->getHotelName())}]/ancestor::*[self::p or self::div or self::td or self::span][count(.//text()[normalize-space()!=''])>1][1]/following::text()[normalize-space()!=''][1][{$this->starts($this->t('T:'))}]")->length > 0) {
                // FE: it-46345266.eml
                $address = implode(', ', $addressRows);
            }
        }

        if (empty($r->getHotelName())) {
            $hotelName = $this->re("/Reservation Confirmation\s*[#][A-Z\d]+\s*for\s*(.+)/u", $this->subject);

            if (empty($hotelName)) {
                $hotelName = $this->re("/Reservation (?:Update )?Confirmation for the\s*(.+)/u", $this->subject);
            }

            if (!empty($hotelName)) {
                $r->hotel()
                    ->name($hotelName);

                if (preg_match("/{$this->opt($hotelName)}[\|\s]+(?<address>.+)\s*\|\s*(?<phone>[\d\.]+)/", $this->http->FindSingleNode("//text()[{$this->starts($hotelName)}]"), $m)) {
                    $r->hotel()
                        ->address($m['address'])
                        ->phone($m['phone']);
                }
            }
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains("Phone:")}]", null, true, "/^(.{3,}?)\s+Phone:/")
                ?? $this->re("/^(.{3,}?)\s+Phone:/", implode(' ', $this->http->FindNodes("//text()[{$this->contains("Phone:")}]/../descendant::text()[normalize-space()]")))
                ?? $this->re("/^(.{3,}?)\s+Phone:/", implode(' ', $this->http->FindNodes("//text()[{$this->contains("Phone:")}]/../../descendant::text()[normalize-space()]")))
                ?? $this->re("/^(.{3,}?)\s+Phone:/", implode(' ', $this->http->FindNodes("//text()[{$this->contains("Phone:")}]/../../../descendant::text()[normalize-space()]")))
            ;
        }
        $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]'; // +377 (93) 15 48 52    |    713.680.2992

        if (!$address) {
            $r->hotel()->noAddress();
        } else {
            $r->hotel()->address($address);
            $r->hotel()
                ->phone($this->http->FindSingleNode("//text()[{$this->contains("Phone:")} or {$this->starts("T:")}]/ancestor::*[1]", null, true,
                    '/(?:Phone:|T:)\s+(' . $patterns['phone'] . ')/'), false, true)
                ->fax($this->http->FindSingleNode("//text()[{$this->contains("Fax:")}]/ancestor::*[1]", null, true,
                    '/Fax:\s+(' . $patterns['phone'] . ')/'), false, true);
        }

        // booked
        $r->booked()
            ->rooms($this->nextText($this->t("Number of rooms:")), false, true);

        $guests = $this->nextText($this->t("Guests per room:"));

        if (!empty($guests)) {
            $r->booked()
                ->guests($guests, false, $cancelled);
        }

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.    |    3pm

        // check in/out
        $checkInDate = strtotime($this->normalizeDate($this->nextText(["Check-in:", 'Arrival Date:'])));
        $checkInTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in Time'))}]", null, true,
            "/{$this->opt($this->t('Check-in Time'))}\s*:*\s*({$patterns['time']})$/");

        if (!empty($checkInDate) && $checkInTime) {
            $checkInDate = strtotime($checkInTime, $checkInDate);
        }

        $checkOutDate = strtotime($this->normalizeDate($this->nextText("Check-out:")));
        $checkOutTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-Out Time'))}]", null, true,
            "/{$this->opt($this->t('Check-Out Time'))}\s*:*\s*({$patterns['time']})$/");

        if (!empty($checkOutDate) && $checkOutTime) {
            $checkOutDate = strtotime($checkOutTime, $checkOutDate);
        }

        if ($cancelled) {
            if (empty($checkInDate)) {
                $r->booked()
                    ->noCheckIn();
            } else {
                $r->booked()
                    ->checkIn($checkInDate);
            }

            if (empty($checkOutDate)) {
                $r->booked()
                    ->noCheckOut();
            } else {
                $r->booked()
                    ->checkOut($checkOutDate);
            }
        } else {
            $r->booked()
                ->checkIn($checkInDate)
                ->checkOut($checkOutDate);
        }

        // cancellation
        $cancel = implode(' ',
            $this->http->FindNodes("//text()[{$this->eq($this->t('Canceling your Reservation'))}]/ancestor::*[{$this->contains($this->t('Modifying Your Reservation'))}][1]//text()[normalize-space(.)!='']"));
        $cancellationPolicy = $this->re("#{$this->opt($this->t('Canceling your Reservation'))}\s*(.+?)\s*{$this->opt($this->t('Modifying Your Reservation'))}#",
            $cancel);

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->nextNode($this->t('Canceling your Reservation'));
        }
        $cancellationPolicy = preg_replace(['/\s*By clicking on the link below\s*:/', '/·\s*/'], '', $cancellationPolicy);

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->nextNode($this->t('Room Policies:'), null, $this->t('days prior to arrival'));
        }

        if (!empty($cancellationPolicy)) {
            $r->general()->cancellation($cancellationPolicy);
        }

        $this->detectDeadLine($r);

        // rooms
        $room = $r->addRoom();
        $room
            ->setType($this->re("#(.*?)(?:,|$)#", $this->nextText("Room type:")), true)
            ->setDescription($this->re("#,\s+(.+)#", $this->nextText("Room type:")), false, true);

        $rate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost per night per room'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
            null, true, "#^\s*\d[\d\.\, ]+\s*$#");

        if ($rate === null) {
            $rateCurrency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost per night per room'))}]",
                null, true, "#{$this->opt($this->t('Cost per night per room'))}[ ]*\([ ]*([A-Z]{3})[ ]*\)#");
            $rateRows = $this->http->FindNodes("//*[{$this->starts($this->t('Date'))} and {$this->contains($this->t('Rate'))}]/following-sibling::text()[normalize-space()!='']");
            $rateText = implode("\n", $rateRows);

            if ($rateCurrency
                && preg_match_all("#\b{$this->opt($this->t('Confirmed'))}[ ]+(?<amount>\d[,.\'\d ]*)$#im", $rateText,
                    $rateMatches) // Jun 19, 2019 1 Confirmed 180.00
            ) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->amount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    $rate = number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateCurrency . ' / night';
                } else {
                    $rate = number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.',
                            '') . ' ' . $rateCurrency . ' / night';
                }
            }
        }

        if (!empty($rate)) {
            $room->setRate($rate);
        }

        $total = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Total for stay (for all rooms)") . "]/following::text()[normalize-space(.)][1]"));

        if (!empty($total)) {
            $r->price()
                ->total($total);
        }
        $tax = $this->amount($this->nextText("Estimated government taxes and fees"));

        if (!empty($tax)) {
            $r->price()
                ->tax($tax);
        }
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Cost per night per room')]",
            null, true, "#\(([A-Z]{3})\)#");

        if (!empty($currency)) {
            $r->price()
                ->currency($currency);
        }

        // cancelled
        $cancel = $this->http->FindSingleNode("(//*[contains(text(),'Cancellation number:')])[1]", null, true,
            "/Cancellation number:\s*\#*\s*(\d+)/");

        if ($cancel) {
            $r->general()
                ->cancellationNumber(trim($cancel))
                ->status('cancelled')
                ->cancelled();

            if (empty($confNo)) {
                $r->general()->noConfirmation();
            }
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#^You may cancel your reservation for no charge until (?<time>.+) hotel time on (?<date>.+? \d{4})#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])));

            return;
        }

        if (preg_match("#^You may cancel your reservation for no charge until (?<date>.+? \d{4}) \(#i",
                $cancellationText, $m)
            || preg_match("#^Changes to your reservation are not permitted. Please note that you may cancel your reservation for no charge until (?<date>.+? \d{4}).#i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date'])));

            return;
        }

        if (preg_match("#You must cancel by the end of the day on (?<date>\w+ \d+)\D* to avoid the charge and the cancel penalty#i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(EmailDateHelper::parseDateRelative($m['date'], $h->getCheckOutDate(), false));

            return;
        }

        if (preg_match("#^(?<prior>\d+) days? prior to arrival to avoid a \(\d+\) nights room#i",
                $cancellationText, $m)
            || preg_match("#^Guests must cancel (?<prior>\d+) days? prior to arrival to avoid penalty charge of #i",
                $cancellationText, $m)
            || preg_match("#^You may cancel your reservation for no charge until (?<prior>\d+) day(?:\[s\])? prior to arrival#i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['prior'] . " days", '00:00');

            return;
        }

        if (preg_match("#^Cancellations made within (?<hours>\d+) hours of arrival will forfeit#i",
                $cancellationText, $m)
            || preg_match("#^Reservations must be cancelled at least (?<hours>\d+) hours prior to arrival to avoid a #i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['hours'] . " hours");

            return;
        }

        if (preg_match("#^Cancellations made after (?<time>\d+:\d+\s*(?:[ap]m)?|\d+\s*[ap]m) day of arrival will forfeit #i",
                $cancellationText, $mm) > 0
        ) {
            $h->booked()
                ->deadlineRelative("0 days", $mm['time']);

            return;
        }

        if (preg_match("#^Cancellations made within (?<cnt>\w+) days? prior to arrival will forfeit the#i",
            $cancellationText, $m)
        ) {
            if (is_numeric($m['cnt'])) {
                $days = $m['cnt'];
            }

            switch ($m['cnt']) {
                case 'one':
                    $days = 1;

                    break;

                case 'two':
                    $days = 2;

                    break;

                case 'three':
                    $days = 3;

                    break;

                case 'four':
                    $days = 4;

                    break;

                case 'five':
                    $days = 5;

                    break;

                case 'six':
                    $days = 6;

                    break;

                case 'seven':
                    $days = 7;

                    break;

                case 'eight':
                    $days = 8;

                    break;

                case 'nine':
                    $days = 8;

                    break;
            }

            if (isset($days)) {
                $h->booked()
                    ->deadlineRelative($days . ' days', '00:00');
            }

            return;
        }
    }

    private function getProvider($from)
    {
        foreach (self::$providers as $provider => $prop) {
            if ((stripos($from, $prop['from']) !== false)
                || $this->http->XPath->query("//a[contains(@href,'{$prop['body']}')]")->length > 0
            ) {
                return $provider;
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+\((\d+:\d+ [AP]M)\)$#", //Thursday, November 27, 2014 (04:00 PM)
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+\((\d+:\d+:\d+\s+GMT)\)$#", //Tuesday, March 11, 2014 (01:52:00 GMT)
            "#^(\d+)-([^\d\s]+)-(\d{4})\s+\(Check-(?:in|out) time:\s+(\d+:\d+\s+[AP]M)\)$#", //23-Aug-2014 (Check-in time: 4:00 PM)
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Apr 6, 2017
            "#^(\d+)-([^\d\s]+)-(\d{4})$#", //25-Jul-2014

            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})\s+\(Check-(?:in|out) time:\s+(\d+:\d+\s+[AP]M)\)$#", //Jun 21, 2017 (Check-out time: 12:00 PM)
            "#^\s*[\w\-]+,\s+(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+(\s*[ap]m)?)\s*$#ui", // Saturday, July 13, 2013, 06:00 PM
            "#^\s*[\w\-]+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#ui", // Saturday, July 13, 2013
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$2 $1 $3",
            "$1 $2 $3",

            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (trim($s) == '') {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function nextNode($field, $root = null, $contains = null)
    {
        $rule = $this->eq($field);

        if (isset($contains)) {
            return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::*[normalize-space(.)!=''][{$this->contains($contains)}][1]",
                $root);
        } else {
            return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::*[normalize-space(.)!=''][1]",
                $root);
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
