<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PrepaidVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-15893453.eml, mta/it-16252054.eml, mta/it-20420284.eml, mta/it-33954955.eml, mta/it-39622253.eml, mta/it-781762418.eml";

    public $reFrom = ["mtatravel.com.au"];
    public $reBody = [
        'en'  => ['Prepaid Voucher', '5 Helpful Tips'],
        'en2' => ['Prepaid Voucher', 'Excite Holidays Booking ID'],
        'en3' => ['Prepaid Voucher', 'Booking Office Reference No:'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'segmentsReg'                => 'Prepaid Voucher\s+\-\s+',
            'Your local contact is'      => ['Your local contact is', 'Local Phone'],
            'Board'                      => ['Board', 'Board & Bed type'],
            'Excite Holidays Booking ID' => ['Excite Holidays Booking ID', 'ReadyRooms Booking ID'],
            'Pick Up Date And Time'      => ['Pick Up Date And Time', 'Pick Up Date and Time'],
            'To'                         => ['To', 'Drop Off'],
            'Vehicle type:'              => ['Vehicle type:', 'Vehicle Type:'],
        ],
    ];
    private $date;

    private $otaConfNo;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }

                    if (!$this->parseEmail($text, $email)) {
                        return null;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Excite Holidays') !== false || stripos($text, 'ReadyRooms') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 4; //Event | Transfer | Hotel | Ferry

        return $types * count(self::$dict);
    }

    private function parseEmail($textPDF, Email $email)
    {
        if (!empty($str = strstr($textPDF, $this->t('5 Helpful Tips'), true))) {
            $textPDF = $str;
        }

        $otaConf = $this->re("#{$this->opt($this->t('Excite Holidays Booking ID'))}[: ]+([\w\/\-]{5,})\s+#", $textPDF);

        if (!empty($otaConf)) {
            if (!empty($this->otaConfNo)) {
                if (!in_array($otaConf, $this->otaConfNo)) {
                    $email->ota()
                        ->confirmation($otaConf, $this->re("#\s+({$this->opt($this->t('Excite Holidays Booking ID'))})[: ]+[\w\/\-]{5,}#", $textPDF));
                    $this->otaConfNo[] = $otaConf;
                }
            } else {
                $email->ota()
                    ->confirmation($otaConf, $this->re("#\s+({$this->opt($this->t('Excite Holidays Booking ID'))})[: ]+[\w\/\-]{5,}#", $textPDF));
                $this->otaConfNo[] = $otaConf;
            }
        }

        $arr = $this->splitter("#({$this->t('segmentsReg')})#", "ControlStr\n" . $textPDF);

        foreach ($arr as $root) {
            $str = $this->re("#^Prepaid Voucher\s+\-\s+(.+?) *\n#", $root);

            switch ($str) {
                case 'Transfer':
                    $this->parseTransfer($root, $email);

                    break;

                case 'Activity':
                    $this->parseActivity($root, $email);

                    break;

                case 'Accommodation':
                    $this->parseHotel($root, $email);

                    break;

                case 'Ferry':
                    $this->parseFerry($root, $email);

                    break;

                default:
                    $this->logger->info("unknown type or reservation");

                    return false;

                    break;
            }
        }

        return true;
    }

    private function parseFerry($textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $r = $email->add()->ferry();

        $textTA = $this->re("#\n[ ]*{$this->t('LOCAL PORT AGENCY CONTACT')}\s+(.+?)\n\n\n#s", $textPDF);
        $r->program()
            ->phone($this->re("#{$this->t('Telephone Number')}[: ]+([\d\+\- \(\)]{5,})#", $textTA),
                $this->t('LOCAL PORT AGENCY CONTACT'));

        $r->general()
            ->confirmation($this->re("#Reservation Number[: ]+([\w\-]{5,})#", $textPDF))
            ->date(strtotime($this->re("#Date of Service[: ]+(.+)#", $textPDF)));

        $s = $r->addSegment();

        if (preg_match_all("#(.+?)[ ]*\|[ ]*{$this->opt($this->t('Age Group'))}: .+?[ ]*\|[ ]*{$this->opt($this->t('Seat Type'))}:[ ]*(.+?)[ ]*\|[ ]*{$this->opt($this->t('Ticket No'))}:[ ]*([\w ]+)#",
            $textPDF, $m)) {
            $r->general()
                ->travellers(array_map("trim", $m[1]));
            $r->setTicketNumbers(array_map("trim", $m[3]), false);
            $s->booked()->accommodations(array_map("trim", $m[2]));
        }

        $s->departure()
            ->name($this->re("#{$this->t('Embark Port')}[ ]*:[ ]+(.+?)(?:[ ]{2,}|\n)#", $textPDF))
            ->date(strtotime($this->re("#{$this->t('Local Departure Date and Time')}[ ]*:[ ]+(.+?)(?:[ ]{2,}|\n)#",
                $textPDF)));

        $s->arrival()
            ->name($this->re("#{$this->t('Disembark Port')}[ ]*:[ ]+(.+?)(?:[ ]{2,}|\n)#", $textPDF))
            ->date(strtotime($this->re("#{$this->t('Local Arrival Date and Time')}[ ]*:[ ]+(.+?)(?:[ ]{2,}|\n)#",
                $textPDF)));

        $s->extra()
            ->carrier($this->re("#{$this->t('Shipping Company')}[ ]*:[ ]+(.+?)(?:[ ]{2,}|\n)#", $textPDF))
            ->vessel($this->re("#{$this->t('Vessel')}[ ]*:[ ]+(.+?)(?:[ ]{2,}|\n)#", $textPDF));

        return true;
    }

    private function parseActivity($textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $t = $email->add()->event();
        $t->setEventType(EVENT_EVENT);
        $tourName = $this->re("#{$this->t('Booking Details')}: +(.+)#", $textPDF);
        $t->place()
            ->name($tourName);

        $address = $this->nice($this->re("#Service Point Address:\s+(.+)\s+Important Instructions#s", $textPDF));

        if (empty($address)) {
            $address = $this->nice($this->re("#Service Point:\s+(.+)\s+Important Instructions#s", $textPDF));
        }

        if (empty($address)) {
            $address = $this->nice($this->re("#confirm your activity\.\s+(.+?)\s+Traveller#s", $textPDF));
        }

        if (!empty($address)) {
            $t->place()
                ->address($address);
        }

        if (empty($time = $this->re("#Service Time: +.*?(\d+\.\d+(?:\s*h[ou]*rs)?)#", $textPDF))) {
            $time = $this->re("#Service Time: +(\d+(?::\d+)?[ap]m)\n#", $textPDF);
        }

        if (empty($time) && preg_match("#.+? \b(\d+:\d+)$#", $tourName, $m)) {
            $time = $m[1];
        }
        $t->booked()
            ->start(strtotime($this->re("#Service Date: +(.+)#", $textPDF) . ' ' . $this->normalizeTime($time)));

        if (preg_match("#Service Time: +[^\n]+\n(.+)\n *Service Point:#", $textPDF, $m)) {
            if (!empty($time = $this->normalizeTime(trim($m[1])))) {
                $t->booked()
                    ->end(strtotime($this->re("#Service Date: +(.+)#", $textPDF) . ' ' . $this->normalizeTime($time)));
            }
        } else {
            $t->booked()
                ->noEnd();
        }

        $t->general()
            ->confirmation($this->re("#Booking Office Reference No[: ]+([\w\-]{5,})#", $textPDF));

        if (preg_match("#{$this->opt($this->t('Traveller\'s Name(s)'))}: \(\d+\) *(.+)\s+{$this->opt($this->t('Booking Details'))}:#s",
            $textPDF, $m)) {
            $pax = array_map("trim", explode(',', $this->nice($m[1])));
            $t->general()
                ->travellers($pax);
        } else {
            $t->general()
                ->traveller($this->re("#Lead Customer[: ]+(.+)#", $textPDF));
        }
        $t->program()
            ->phone($this->re("#{$this->opt($this->t('Your local contact is'))}.+?(?:on|No).*?([\d\+\-\s\(\)]{5,})#i", $textPDF),
                $this->re("#{$this->opt($this->t('Your local contact is'))}\s+(.+)\s+on#", $textPDF));

        return true;
    }

    private function parseTransfer($textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('$textPDF = ' . print_r($textPDF, true));
        $timeDep = $this->re("#{$this->opt($this->t('Pick Up Date And Time'))}[: ]+(.+)#",
            $textPDF);

        //skip transfer to cruise - have no datetime depart
        if (!preg_match("#(\d+ \w+ \d{4} at\s+\d+:\d+)#", $timeDep, $m)
            && stripos($textPDF, 'Ship Departure Date:') !== false
        ) {
            return true;
        }

        $t = $email->add()->transfer();
        $t->general()
            ->confirmation($this->re("#Booking Office Reference No[: ]+([\w\-]{5,})#", $textPDF))
            ->traveller($this->re("#Lead Passenger[: ]+(.+)#", $textPDF));

        if (!empty($status = $this->re("#Your transfer is (confirmed)#", $textPDF))) {
            $t->general()
                ->status($status);
        }

        $s = $t->addSegment();
        $date = strtotime($this->re("#Date of Service: *(.+?\d{4})\b#m", $textPDF));

        $s->departure()
            ->name($this->nice($this->re("#{$this->opt($this->t('Pick Up'))} *: *(.+?)\n\s*(?:{$this->opt($this->t('To'))}|{$this->opt($this->t('Pick Up Date And Time'))}|[[:alpha:] \-]+):#s",
                $textPDF)));
        $s->arrival()
            ->name($this->nice($this->re("#^ *{$this->opt($this->t('To'))}[: ]+(.+?)\s*?(?:{$this->t('Flight Arrival Details and Time')}|{$this->t('Flight Departure Details and Time')}|\n\n)#sm",
                $textPDF)));

        if (preg_match("#(.+) (\d{1,2}:\d{2})#", $timeDep, $m)) {
            if (!preg_match('/\d{4}/', $m[1])) {
                $s->departure()->date(strtotime($m[2], $date));
            } else {
                $m[1] = preg_replace('/\s*at\s*/', ', ', $m[1]);
                $s->departure()->date(strtotime($m[1] . ', ' . $m[2]));
            }
            $s->arrival()->noDate();
        } else {
            $timeArFl = $this->re("#{$this->opt($this->t('Flight Arrival Details and Time'))}[: ]+(.*?at +\d+:\d+)#", $textPDF);

            if (preg_match("#(\d+ \w+ \d{4}) at\s+(\d+:\d+)#", $timeArFl, $m)
                && (preg_match("#\b[A-Z]{3}\b#", $s->getDepName()) || strpos($s->getDepName(), 'Airport') !== false)
            ) {
                $s->departure()->date(strtotime("+ 30 minutes", strtotime($m[1] . ', ' . $m[2])));
            }

            $time = $this->re("#{$this->opt($this->t('Flight Departure Details and Time'))}[: ]+(.+)#",
                $textPDF);

            if (preg_match("#(\d+ \w+ \d{4}) at\s+(\d+:\d+)#", $time, $m)
                || (preg_match("#\b[A-Z]{3}\b#", $s->getArrName()) || strpos($s->getArrName(), 'Airport') !== false)
            ) {
                $hours = '3';

                if (preg_match("#pick up time of (\d+) hours prior to your departure#", $textPDF, $m2)) {
                    $hours = $m2[1];
                }
                $s->departure()->date(strtotime("- " . $hours . " hours", strtotime($m[1] . ', ' . $m[2])));
            }

            if (!$s->getDepDate() && $s->getArrDate()) {
                $s->departure()->noDate();
            }

            if ($s->getDepDate() && !$s->getArrDate()) {
                $s->arrival()->noDate();
            }
        }

        if (preg_match("#\n\s*{$this->opt($this->t('Vehicle type:'))} *(.+)#", $textPDF, $m)) {
            $s->extra()
                ->type($m[1]);
        }

        return true;
    }

    private function parseHotel($textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->re("#Booking Office Reference No[: ]+([\w\-]{5,})#", $textPDF));

        if (!empty($phone = $this->re("#{$this->t('Local Phone No')}: +([\d\+\-\(\) ]{5,})#", $textPDF))) {
            $h->program()
                ->phone($phone, $this->t('Local Phone No'));
        }

        if (!empty($phone = $this->re("#{$this->t('please call Excite Holidays')} on +([\d\+\-\(\) ]{5,})#",
            $textPDF))
        ) {
            $h->program()
                ->phone($phone, 'Excite Holidays');
        }

        $hotelInfoBlock = $this->re("#{$this->opt($this->t('Booking Office Reference No'))}[^\n]*\n(.+)#s",
            strstr($textPDF, 'Booking Summary', true));

        $h->hotel()
            ->name($this->nice($this->re("#(.+)\s+{$this->t('Address')}:#s", $hotelInfoBlock)))
            ->address($this->nice($this->re("#{$this->t('Address')}:\s+(.+?)(?:{$this->t('Phone')}|$)#s",
                $hotelInfoBlock)))
            ->phone($this->re("#{$this->t('Phone')}:\s+([+(\d][-. \d)(]{5,}[\d)])#", $hotelInfoBlock), false, true);

        $bookingSummary = $this->re("#{$this->t('Booking Summary')}\s+(.+)\s+{$this->t('Booking Office Details')}#s",
            $textPDF);

        if (preg_match("#{$this->opt($this->t('Guest(s)'))}: +\((\d+)\)\s+(.+)\s+{$this->opt($this->t('Check In'))}#s",
            $bookingSummary, $m)) {
            if (preg_match_all('#Child\s(\d):#', $m[2], $k)) {
                if (max($k[1]) == count($k[1])) {
                    $h->booked()->kids(max($k[1]))
                               ->guests(strval($m[1] - max($k[1])));
                }
            } else {
                $h->booked()->guests($m[1]);
            }
            $pax = array_filter(array_map("trim", explode(",", $this->nice($m[2]))), function ($s) {
                return strpos($s, 'Child') === false;
            });

            if (!empty($pax)) {
                $h->general()
                    ->travellers($pax);
            }
        }
        $r = $h->addRoom();
        $r->setType($this->nice($this->re("#{$this->opt($this->t('Room type'))}:\s+(.+)\s+{$this->opt($this->t('Board'))}:#s",
            $bookingSummary)));

        $h->booked()
            ->checkIn(strtotime($this->re("#{$this->opt($this->t('Check In'))}: +(.+)#", $bookingSummary)))
            ->checkOut(strtotime($this->re("#{$this->opt($this->t('Check Out'))}: +(.+)#", $bookingSummary)));

        if (preg_match("#Check-in time starts at .+ Check-out time is .+\n(\d+:\d+[ ]*[ap]m)\n(\d+:\d+[ ]*[ap]m)\n#i",
                $textPDF, $m)
            && $h->getCheckInDate() && $h->getCheckOutDate()
        ) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        } elseif (preg_match("#Check-in time starts at (.+) Check-out time is (.+)\n#", $textPDF, $m)
            && $h->getCheckInDate() && $h->getCheckOutDate()
        ) {
            $h->booked()
                ->checkIn(strtotime($this->normalizeTime($m[1]), $h->getCheckInDate()))
                ->checkOut(strtotime($this->normalizeTime($m[2]), $h->getCheckOutDate()));
        }

        return true;
    }

    private function normalizeTime($strTime)
    {
        $in = [
            //20.15hours
            '#^(\d+)\.(\d+)\s*h[ou]*rs$#u',
            //9am
            '#^(\d+)\s*([ap]m)$#ui',
            //9:45am
            '#^(\d+:\d+)\s*([ap]m)$#ui',
            //noon
            '#^noon$#ui',
        ];
        $out = [
            '$1:$2',
            '$1:00 $2',
            '$1 $2',
            '12:00 PM',
        ];

        $str = preg_replace($in, $out, $strTime);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
