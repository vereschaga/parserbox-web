<?php

namespace AwardWallet\Engine\gha\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "gha/it-139257640.eml, gha/it-140903021.eml";
    public $subjects = [
        'Your reservation confirmation at ',
    ];

    public $emailSubject;
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thank you very much for your interest in the' =>
                [
                    'Thank you very much for your interest in the',
                    'Thank you for your interest in staying with us at the',
                    'We are very much looking forward to welcome you at the',
                    'Welcome back to',
                    'Thank you very much for your interest in',
                    'We thank you for the interest you have shown in the',
                    'Further to your request, we thank you for the interest you have shown towards the',
                    'Thank you for choosing',
                    'We sincerely thank you for choosing',
                    'Further to your request, we thank you for the interest you have shown in',
                    'Warm greetings from',
                ],

            'CONFIRMATION NUMBER' => [
                'CONFIRMATION NUMBER',
                'CONFIRMATION NR',
            ],

            'ARRIVAL DATE' => ['ARRIVAL DATE', 'ARRIVAL'],

            'DEPARTURE DATE'           => ['DEPARTURE DATE', 'DEPARTURE'],
            'GUEST NAME'               => ['GUEST NAME', 'NAME'],
            'TERMS AND CONDITIONS'     => ['TERMS AND CONDITIONS', 'CANCELLATION POLICY'],
            'check-out time is before' => ['check-out time is before', 'check-out time is', 'your arrival and until'],
            'check-in time is from'    => ['check-in time is from', 'The room will be reserved from'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@kempinski.com') !== false || stripos($headers['subject'], 'Kempinski') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'KEMPINSKI DISCOVERY') or  contains(normalize-space(), 'kempinski')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('GUEST NAME'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('NUMBER OF GUESTS'))}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->starts($this->t('GUARANTEE'))} or {$this->starts($this->t('DEPOSIT INFORMATION'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]kempinski\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm    |    16.00 pm
            'phone' => '[+(\d][-+. \d)(\/]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $travellers = array_filter(preg_split("/(?:\s&\s|Mrs\.?\s|Mr\.?\s|Ms\.?\s)/", $this->http->FindSingleNode("//text()[{$this->eq($this->t('GUEST NAME'))}]/ancestor::tr[1]/descendant::td[string-length()>3][2]")));

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TERMS AND CONDITIONS'))}]/following::text()[string-length()>5][1]");

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}]/ancestor::tr[1]/descendant::td[string-length()>3][2]", null, true, "/^\s*([\dA-Z]{5,})\s*$/");

        if (empty($confirmation)) {
            $confirmation = $this->re("/Confirmation No\. #([A-Z\d]{5,})\s*$/", $this->emailSubject);
        }
        $h->general()
            ->confirmation($confirmation)
            ->travellers($travellers, true)
            ->cancellation($cancellation);

        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you very much for your interest in the'))}]", null, true, "/{$this->opt($this->t('Thank you very much for your interest in the'))}\s+(?:the +)?(\D+?)(?:\s*[,.;!?]|\s+and\s+|for)/");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Thank you very much for your interest in the'))}])[1]", null, true, "/{$this->opt($this->t('Thank you very much for your interest in the'))}\s+(?:the +)?(\D+?)(?:\s*[,.;!?]|\s+and\s+|for)/");
        }

        $hotelName = trim(preg_replace("/^(\D{10,}) in /", '$1', $hotelName));

        if (stripos($hotelName, '.') !== false) {
            $hotelName = trim($this->re("/(\D+)(?:\.| in )/", $hotelName));
        }

        if (!empty($hotelName)) {
            $h->hotel()->name($hotelName);
        }

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}]/following::text()[contains(normalize-space(), 'kempinski.com')][last()]/ancestor::table[1]/descendant::text()[normalize-space()]"));

        if (empty($hotelInfo)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('NUMBER OF GUESTS'))}]/following::text()[contains(normalize-space(), 'kempinski.com')][last()]/ancestor::table[1]/descendant::text()[normalize-space()]"));
        }

        if (!empty($hotelName) && stripos($hotelInfo, $hotelName) !== false) {
            if (preg_match("/^(?<name>.{2,})\n(?<address>.{3,})\n(?<phone>{$patterns['phone']})\n(?<email>reservation.+[@]kempinski\.com)/i", $hotelInfo, $m)
                || preg_match("/^(?<name>.{2,})\n(?<address>.{3,})\n[Tt]\s*[:]+\s*(?<phone>{$patterns['phone']})\s*[Ff]\s*[:]+\s*(?<fax>{$patterns['phone']})\n(?<email>.+[@]kempinski\.com)/i", $hotelInfo, $m)
                || preg_match("/^(?<name>.{2,})\s*-\s*(?<address>.+\n.+)\n(?<email>.+[@]kempinski\.com)\nTel\s*[:]+\s*(?<phone>{$patterns['phone']})$/i", $hotelInfo, $m)
                || preg_match("/^(?<name>.{2,})\n(?<address>.*\n.*)\n*t\:\s*(?<phone>{$patterns['phone']})\n(?<email>.+[@]kempinski\.com)\s*$/i", $hotelInfo, $m)
                || preg_match("/^{$hotelName}\s+(?<address>[\s\S]{3,80}?)\s+[TP]: (?<phone>{$patterns['phone']})\s+F: (?<fax>{$patterns['phone']})\n(?<email>.+[@]kempinski\.com)\s*$/iu", $hotelInfo, $m)
                || preg_match("/^(?<name>.{2,})\n(?<address>.*\n.*)\n*(?<phone>{$patterns['phone']})\n(?<email>\S+[@]kempinski\.com)\s*$/i", $hotelInfo, $m)
                || preg_match("/^(?<name>.{2,})\n(?<address>.*\n.*)\n(?<email>\S+[@]kempinski\.com)\n*Tel: *(?<phone>{$patterns['phone']})\s*$/i", $hotelInfo, $m)
            ) {
                $h->hotel()
                    ->address(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone($m['phone']);

                if (!empty($m['fax'])) {
                    $h->hotel()->fax($m['fax']);
                }
            }
        } elseif (!empty($hotelName) && stripos($hotelInfo, $hotelName) === false) {
            if (
                preg_match("/^(?<address>.{3,}){$this->opt($this->t('Tel :'))}\s*(?<phone>{$patterns['phone']})\s*{$this->opt($this->t('Fax :'))}\s*(?<fax>{$patterns['phone']})/is", $hotelInfo, $m)
                || preg_match("/^(?<address>\d+.+\n(.+\n)+)\s*T (?<phone>{$patterns['phone']}) · F (?<fax>{$patterns['phone']})\n(?<email>.+[@]kempinski\.com)\s*$/u", $hotelInfo, $m)
                || preg_match("/^(?<address>.+?)\s*(?:T:|Tel)\s*(?<phone>{$patterns['phone']})\s*\S\s*(?:F:|Fax)\s*(?<fax>{$patterns['phone']})\n*(?<email>.+[@]kempinski\.com)\s*$/u", $hotelInfo, $m)
            ) {
                $h->hotel()->address(preg_replace('/\s+/', ' ', $m['address']))->phone($m['phone'])->fax($m['fax']);

                if (!empty($m['fax'])) {
                    $h->hotel()->fax($m['fax']);
                }

                if (!empty($m['phone'])) {
                    $h->hotel()->phone($m['phone']);
                }
            }
        }

        if (!empty($hotelName) && empty($h->getAddress())
            && $this->http->XPath->query("//node()[{$this->starts($this->t('CONFIRMATION NUMBER'))} or {$this->starts($this->t('NUMBER OF GUESTS'))}]/following::text()[{$this->contains('@kempinski.com')}]" .
            "[preceding::text()[normalize-space()][position() < 10][{$this->starts($hotelName)}]]")->length == 0
        ) {
            $h->hotel()->noAddress();
        }

        $nightsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DURATION OF STAY'))}]/ancestor::tr[1]/descendant::td[string-length()>3][2]", null, true, "/\b(\d{1,3})\s*Nights/i");

        $h->booked()
           ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('ARRIVAL DATE'))}]/ancestor::tr[1]/descendant::td[string-length()>3][2]")))
           ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('DEPARTURE DATE'))}]/ancestor::tr[1]/descendant::td[string-length()>3][2]")))
           ->guests($this->http->FindSingleNode("//text()[normalize-space()='NUMBER OF GUESTS']/ancestor::tr[1]/descendant::td[string-length()>3][2]", null, true, "/(\d+)\s*Adult/"))
           ->rooms($this->http->FindSingleNode("//text()[normalize-space()='NUMBER OF ROOMS']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^\s*(\d+)\s*$/"), true, true)
        ;

        $kids = $this->http->FindSingleNode("//text()[normalize-space()='NUMBER OF GUESTS']/ancestor::tr[1]/descendant::td[string-length()>3][2]", null, true, "/(\d+)\s*Child/i");

        if (is_numeric($kids)) {
            $h->booked()
                ->kids($kids);
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='ROOM TYPE' or normalize-space()='ROOM' ]/ancestor::tr[1]/descendant::td[string-length()>3][2]");

        $roomRates = [];
        $roomRateVal = $this->htmlToText($this->http->FindHTMLByXpath("//text()[normalize-space()='ROOM RATE']/ancestor::tr[1]/descendant::td[string-length()>3][2]"));
        $roomRateRows = array_filter(preg_split('/[ ]*\n+[ ]*/', $roomRateVal));

        if (count($roomRateRows) === 1) {
            $roomRates = $roomRateRows;
        } else {
            foreach ($roomRateRows as $rrRow) {
                if (preg_match("/^From[ ]+.*\d.*[ ]+-[ ]+(\d[,.\'\d ]*?[ ]*[^\-\d)(]+|[^\-\d)(]+?[ ]*\d[,.\'\d ]*)$/i", $rrRow, $m)) {
                    // From Jun 09 - 535.00 EUR
                    $roomRates[] = $m[1];
                }
            }
        }

        if (count($roomRates) > 1 && $nightsCount !== null && count($roomRates) !== (int) $nightsCount) {
            $roomRates = [];
        }

        if (!empty($roomType) || count($roomRates) > 0) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (count($roomRates) === 1) {
                $roomRate = array_shift($roomRates);
                $room->setRate($roomRate);
            } elseif (count($roomRates) > 1) {
                $room->setRates($roomRates);
            }
        }

        $timeIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your room will be reserved for you from'))}]", null, true, "/{$this->opt($this->t('Your room will be reserved for you from'))}\s*([\d\:]+(?:\s*(?:noon|am|pm))?)/");

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-in time is from'))}]", null, true, "/{$this->opt($this->t('check-in time is from'))}\s*([\d\.:]+(?:\s*(?:noon|am|pm))?)/");
            $timeIn = str_replace('.', ':', $timeIn);
        }

        $this->logger->debug($timeIn);

        if (!empty($timeIn)) {
            $h->booked()
                ->checkIn(strtotime($timeIn, $h->getCheckInDate()));
        }

        $timeOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('on the day of arrival until'))}]", null, true, "/{$this->opt($this->t('on the day of arrival until '))}\s*([\d\:]+(?:\s*(?:noon|am|pm))?)/i");

        if (empty($timeOut)) {
            $timeOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-out time is before'))}]", null, true, "/{$this->opt($this->t('check-out time is before'))}\s*([\d\.:]+(?:\s*(?:noon|am|pm))?)/");
            $timeOut = str_replace('.', ':', $timeOut);
        }
        $timeOut = str_replace('12 noon', '12:00', $timeOut);

        if (!empty($timeOut)) {
            $h->booked()
                ->checkOut(strtotime($timeOut, $h->getCheckOutDate()));
        }

        if ($cancellation) {
            if (preg_match("/In (?i)case of no-show or cancell?ation after\s+(?<hour>{$patterns['time']})\s+hours? hotel time (?<prior>\d{1,3} days?) prior to the arrival date, the hotel will charge/", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative($m['prior'], $this->normalizeTime($m['hour']));
            } elseif (preg_match("#A cancellation free of charge is possible until (?<prior>\d+\s*days?) prior to your arrival#i", $cancellation, $m)
                || preg_match("#Cancellation or modification to your reservation can be made (?<prior>\d+\s*hours) prior to the date of your arrival#i", $cancellation, $m)
                || preg_match("#A cancellation free of charge is possible until (?<prior>\d+\s*days?) prior to arrival#", $cancellation, $m)
                || preg_match("#Free cancellation is possible until (?<prior>\d+\s*days?) prior to arrival#", $cancellation, $m)
                || preg_match("#Please be advised that should you wish to cancel or make changes to your reservation for any reason, please do so at least (?<prior>\d+\s*hours) prior to the scheduled arrival date#", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative($m['prior']);
            } elseif (preg_match("/If (?i)you fail to cancell? or amend your reservation after\s+(?<hour>{$patterns['time']})\s+\(?\s*local time\s*\)? on your scheduled arrival date, or fail to show up, the Hotel reserves the right to charge/", $cancellation, $m)
                || preg_match("/Should (?i)you require to alter, postpone or cancell? your reservation please contact us latest by\s+(?<hour>{$patterns['time']})\s+\(?\s*local time\s*\)? on the day of your arrival\s*\./", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative('0 days', $this->normalizeTime($m['hour']));
            } elseif (preg_match("/A cancellation free of charge is possible until\s+(?<hour>\d+\:\d+)hrs\s*\(local hotel time\)\s+(?<prior>\d+\s*days)\s+prior to arrival/", $cancellation, $m)) {
                $h->booked()->deadlineRelative($m['prior'], $this->normalizeTime($m['hour']));
            }
        }

        $account = $this->http->FindSingleNode("//text()[normalize-space()='KEMPINSKI DISCOVERY NO']/ancestor::tr[1]/descendant::td[string-length()>3][2]", null, true, "/^\s*(\d+)\,/");

        if (!empty($account)) {
            $h->program()
               ->account($account, false);
        }

        $priceText = $this->http->FindSingleNode("//text()[normalize-space(.)='TOTAL COST OF STAY']/ancestor::tr[1]/descendant::td[string-length()>3][2]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();
        $this->ParseHotel($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*[.][ ]*(\d)/', '$1:$2', $s); // 01.55 PM    ->    01:55 PM

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1]; // 21:51 PM    ->    21:51
        }
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25

        return $s;
    }
}
