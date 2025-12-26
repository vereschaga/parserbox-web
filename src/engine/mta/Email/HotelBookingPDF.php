<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelBookingPDF extends \TAccountChecker
{
    public $mailFiles = "mta/it-703164114.eml, mta/it-703677663.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $travellers = [];
    public $guests;
    public $year;
    public $kids;
    public $subjects = [
        'Infinity Holidays - Booking (#',
        'Infinity - ',
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mtatravel.com.au') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "MTA ") !== false
                && (strpos($text, 'Trip Summary') !== false)
                && (strpos($text, 'Trip Details') !== false)
                && (strpos($text, 'Check in:') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel.com.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            //$travellerText = $this->re("/Travellers\n\s*\d+.*\n+((?:.*\n*){1,10})\n+\s*(?:Trip Summary|\s+Please review)/u", $text);
            $travellerText = $this->re("/Travellers\n\s*\d+ ?(?:children|adults?|child|teen)(?: ?, ?\d+ ?[[:alpha:]]+)*\n(.+)\n+\s*(?:Trip Summary|\s+Please review)/su", $text);

            $travellerTable = $this->splitCols($travellerText);

            foreach ($travellerTable as $travellerColumn) {
                $travellers = array_filter(preg_split("/\n\n/", $travellerColumn));

                foreach ($travellers as $traveller) {
                    $pax = preg_replace("/(?:Mstr|Mrs|Mr|Ms|Miss)/", "", $this->re("/(.+)\s(?:Adult|Child|Kids|Inf|Teen)/su", $traveller));
                    $this->travellers[] = preg_replace("/[ ]{2,}/", " ", preg_replace("/\n/", " ", $pax));
                }
            }

            $tripDetails = $this->re("/Trip Details\n(.+)/s", $text);

            $this->guests = $this->re("/Travellers\n\s*(\d+)\s*adults?\,?/", $text);
            $this->kids = $this->re("/Travellers\n\s*\d+\s*adults?.*\,\s*(\d+)\s*child/", $text) ?? 0;
            $this->kids += $this->re("/Travellers\n\s*\d+\s*adults?.*\,\s*(\d+)\s*teen/", $text) ?? 0;

            //Remove NumberPage
            $tripDetails = preg_replace("/^\s+\d+\/\d+\n/m", "", $tripDetails);

            if (stripos($tripDetails, 'Check-in to') !== false) {
                $hotelsArray = $this->splitText($tripDetails, "/^\s*(\w+\,\s*\d+\s*\w+\s*\d{4}\n)/m", true);

                foreach ($hotelsArray as $hotelText) {
                    $this->HotelPDF($email, $hotelText);
                }
            } elseif (stripos($tripDetails, 'Stays') !== false) {
                $this->year = $this->re("/\n {0,5}Stay {2,}\w+\,\s*\d+\s*\w+\s*(\d{4}) {3,}/", $text);

                $hotelsArray = $this->splitText($tripDetails, "/^(Stays)/m", true);

                foreach ($hotelsArray as $hotelText) {
                    $this->HotelPDF($email, $hotelText);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelPDF(Email $email, string $textPDF)
    {
        if (stripos($textPDF, "Check in:") === false && (
            stripos($textPDF, "Experience an activity") !== false
            || stripos($textPDF, "Transfer to") !== false
        )) {
            return;
        }

        $h = $email->add()->hotel();

        $h->general()
            ->travellers(array_filter(array_unique($this->travellers)));

        $conf = preg_replace('/\s+/', '', $this->re("/Booking Reference:\s*([A-Z\d\-\_]{5,}(?:\n {20,}\d+)?)\n/u", $textPDF));

        if ($this->guests !== null) {
            $h->booked()
                ->guests($this->guests);
        }

        if ($this->kids !== null) {
            $h->booked()
                ->kids($this->kids);
        }

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()
                ->noConfirmation();
        }

        $year = $this->re("/^\w+\,\s*\d+\s*\w+\s*(\d{4})\n/", $textPDF) ?? $this->year;

        //remove \n in booking reference
        $textPDF = preg_replace("/(Booking Reference:\s*[A-Z\d]+)\n\s+([A-Z\d]{1,2})\n/", "$1$2", $textPDF);

        /*
               Hotel Laurin                                                        Booking Reference: 9003084666498
             Mon 14 Oct - Wed 16 Oct      •     Check in: 03:00pm - Check out: 10:00am •         2 nights

                             •      Lungomare Guglielmo Marconi 3, 16038, Santa Margherita Ligure

                      1 bedroom triple with terrace and sea view, queen & twin beds (2,0,0)
        */

        $xpath = "/\s+(?<hotelName>.+)\b\s+Booking Reference:.+"
            . "\n+\s*(?<arrDate>\w+\s*\d+\s*\w+)[\s\-]*(?<depDate>\w+\s*\d+\s*\w+)[\s•]+Check in:\s*(?<arrTime>[\d\:]+a?p?m)"
            . "[\s\-]+Check out:\s*(?<depTime>[\d\:]+a?p?m).+nights\s*\n+\s+[•]"
            . "\s+(?<address>.+(?:\n.+)?)\n+"
            . "\s+(?<roomDescription>.+\(\d+, ?\d+.*)/u";

        $xpath2 = "/Stays.*\n*\s*(?<hotelName>.+(?:\n.+)?)\n+\s*"
            . "(?<arrDate>\w+\s*\d+\s*\w+)[\s\-]*(?<depDate>\w+\s*\d+\s*\w+)[\s•]+Check in:\s*(?<arrTime>[\d\:]+a?p?m)"
            . "[\s\-]+Check out:\s*(?<depTime>[\d\:]+a?p?m).+nights\n+\s+[•]\s+"
            . "(?<address>.+)\n+"
            . "\s+(?<roomDescription>.+)/u";

        if (preg_match($xpath, $textPDF, $m) || preg_match($xpath2, $textPDF, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address(preg_replace('/\s+/', ' ', trim($m['address'])));

            $h->booked()
                ->checkIn($this->normalizeDate($m['arrDate'] . ' ' . $year . ', ' . $m['arrTime']))
                ->checkOut($this->normalizeDate($m['depDate'] . ' ' . $year . ', ' . $m['depTime']));

            $h->addRoom()->setDescription($m['roomDescription']);
        }

        if (preg_match("/\n\s*Cancellation Policy\n\s*(?<cancellation>.+)/u", $textPDF, $m)) {
            $h->general()
                ->cancellation($m['cancellation']);
            $this->detectDeadLine($h);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            //Fri, 27 Dec 2024, 00:30
            "/^\s*([[:alpha:]\-]+)\s+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui",
        ];
        $out = [
            "$1, $2 $3 $4, $5",
        ];
        $str = preg_replace($in, $out, $str);

        // if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
        //     if ($en = MonthTranslate::translate($m[2], $this->lang)) {
        //         $str = $m[1] . $en . $m[3];
        //     }
        // }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/If cancelled on or after\s+(?<date>\d+\s*\w+\s*\d{4})\,\s*a cancellation charge of/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date']));
        }
    }
}
