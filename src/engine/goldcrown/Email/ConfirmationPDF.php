<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-60374702.eml, goldcrown/it-6647421.eml";
    public static $dict = [
        'en' => [
            'Conf' => ["Conf", "Confirmation"],
        ],
    ];

    private $reFrom = "bestwestern.";
    private $reBody = [
        'en'  => 'Avg Daily Rate',
        'en2' => 'Each Best Western',
    ];
    private $reSubject = [
        '#Best Western.*? Confirmation [A-Z\d]+#',
        '#A\smessage\sfrom\D+\-\sGuest\sConfirmation#',
    ];
    private $lang = '';
    //	public $pdfNamePattern = "Confirm.*pdf";
    private $pdfNamePattern = ".*\.pdf";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->AssignLang($text);
                    $its[] = $this->parseEmail($email, $text);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'www.bestwestern.com') !== false || stripos($text, 'Best Western') !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $dateReservation = $this->re("#Date\/Time\s*Booked\s+([\d\/\s\:]+\s*A?P?M)\n#", $text);

        if (!empty($dateReservation)) {
            $h->general()
                ->date(strtotime($dateReservation));
        }

        $h->general()
            ->confirmation($this->re("#{$this->opt($this->t('Conf'))}\s+\#\s+([A-Z\d]+)#", $text));

        $phone = $this->re("#Phone\:\s*([\d\-\(\)]+)\s*Web#", $text);

        if (empty($phone)) {
            $phone = $this->re("#^(?:.+[ ]{3,})?\s*([\(\)\d\- ]{5,})#", $text);
        } // order may be : phone, hotel name or hotel name, phone
        $h->hotel()
            ->phone($phone);

        $fax = $this->re("#Fax\:\s*([\d\-\(\)]+)#", $text);

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        if (preg_match("#^((?:.*\n){2,8}?)(?:[ ]*[\d\/]{6,}|.* Room |Phone|\n\n\n)#", $text, $m)) {
            $addr = preg_replace("#^([\d\(\)\-\+ ]{5,}\n)#", '', $m[1]);
            $addr = preg_replace("#^([ ]{0,20}.*)[ ]{10,}\s.*#m", '$1', $addr);

            if (preg_match("#^(?<name>(?:.+\n){1,2})\n(?<addr>[\s\S]+)#", $addr, $mat)
                || preg_match("#^(?<name>.+)\n(?<addr>[\s\S]+)#", $addr, $mat)) {
                $h->hotel()
                    ->name(preg_replace("#\s*\n\s*#", ' ', trim($mat['name'])))
                    ->address(preg_replace("#\s*\n\s*#", ', ', trim($mat['addr'])));
            }
        }

        $account = trim($this->re("#Loyalty Club:[ ]+(\d{5,})\s+#", $text));

        if (!empty($account)) {
            $h->ota()->account($account, false);
        }

        $travellers = trim($this->re("#Registered To:.*\n(?:[ ]{30,}.*\n|\s*\n){0,5}([ ]{0,10}.+?)([ ]{3,}|\n)#", $text));

        if (empty($travellers)) {
            $travellers = trim($this->re("#Guest\s+Name\s*(\D+)\s*Arrival\sDate#", $text));
        }
        $h->general()
            ->traveller($travellers);

        $guests = $this->re("#Guests\s+(\d+)\s*\/\s*\d+#", $text);
        $kids = $this->re("#Guests\s+\d+\s*\/\s*(\d+)#", $text);

        if (empty($guests)) {
            if (preg_match("/Adults\/Children\s\D+(\d)\s*\/(\d)\s*\/(\d)\s*/", $text, $m)) {
                $guests = $m[1];
                $kids = $m[2] + $m[3];
            }
        }

        $h->booked()
            ->guests($guests)
            ->kids($kids);

        $room = $h->addRoom();
        $dx = strlen($this->re("#(.+[ ]{3,})Room Type #", $text));
        $roomType = $this->re("#Room Type ((?:.*\n){1,5}).*[ ]{3,}Guests[ ]+\d#", $text);

        if (empty($roomType)) {
            if (!empty($dx) && !empty($roomType)) {
                $roomType = preg_replace("#^.{" . ($dx - 5) . "}.*[ ]{5,}(.+)#m", '$1', $roomType);
                $roomType = preg_replace("#\s*\n\s*#", ', ', trim($roomType));
            }
        }

        if (empty($roomType)) {
            $roomType = $this->re("#Room\sType\s([\D\d]+)\s*Late\sArrival#", $text);
            $roomType = preg_replace("#\s*\n\s*#", ', ', trim($roomType));
        }

        if (!empty($roomType)) {
            $room->setType($roomType);
        }

        $rate = $this->re("#Avg Daily Rate:[ ]+(.+)#", $text);

        if (!empty($rate)) {
            $room->setRate($rate);
        }

        //it-60374702
        if (!empty($dateA = $this->re("#Arrival\s*Date\s*([\d\/]+)#", $text))) {
            $dateArr = $this->normalizeDate($dateA);
        }

        if (!empty($dateD = $this->re("#Departure\s*Date\s*([\d\/]+)#", $text))) {
            $dateDep = $this->normalizeDate($dateD);
        }

        if (empty($dateArr) && empty($dateDep)) {
            $dateArr = $this->re("#Arrival\s+(.+)#", $text);
            $dateDep = $this->re("#Departure\s+(.+)#", $text);

            if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateArr, $m)) {
                $dateArr = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateArr));
            }

            if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateDep, $m)) {
                $dateDep = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateDep));
            }

            if ($this->identifyDateFormat($dateArr, $dateDep) === 1) {
                $dateArr = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$2.$1.$3", $dateArr);
                $dateDep = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$2.$1.$3", $dateDep);
            } else {
                $dateArr = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$1.$2.$3", $dateArr);
                $dateDep = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$1.$2.$3", $dateDep);
            }
        }

        $h->booked()
            ->checkIn(strtotime($dateArr))
            ->checkOut(strtotime($dateDep));

        $cancellation = str_replace("\n", " ", $this->re("/(Reservations much be cancelled.+on file)/s", $text));

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);

            $this->detectDeadLine($h, $cancellation);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s);
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d).(\d+).(\d{4})$#', // 7/5/2020
        ];
        $out = [
            '$2.$1.$3',
        ];
        $date = preg_replace($in, $out, $date);

        return $date;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("#Reservations much be cancelled by (\da?p?m), (\w+) days? prior arrival#i",
            $cancellationText, $m)) {
            if ($m[2] == 'one') {
                $days = 1;
            }

            if ($m[2] == 'two') {
                $days = 2;
            }
            $h->booked()
                ->deadlineRelative("{$days} days", $m[1]);
        }
    }
}
