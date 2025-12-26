<?php

namespace AwardWallet\Engine\spg\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmationPlainText extends \TAccountCheckerExtended
{
    public $mailFiles = "spg/it-1772647.eml, spg/it-1772648.eml, spg/it-1854942.eml, spg/it-1855811.eml, spg/it-2098620.eml";

    public $subjects = [
        'Four Points Knoxville Reservation Confirmation',
        'aloft Philadelphia Airport Reservation Confirmation',
        'Your reservation confirmation',
    ];

    public $from = ['@marriott.com', ''];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@starwoodhotels.com') !== false) {
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
        if (
            stripos($parser->getBodyStr(), 'This electronic message transmission contains information from the Company') !== false
            && stripos($parser->getBodyStr(), 'that may be proprietary, confidential and/or privileged') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]starwoodhotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getBodyStr();
        $text = str_replace('=', '', $text);

        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->re("#(?:Reservation|Confirmation)\s+Number\s+[-\#]\s+([\w\-]+)#", $text))
            ->traveller($this->re("#Guest\s+Name\s*(?:-|:\*+)\s*(.*)#i", $text), true);

        $hotelName = $this->re("#Thank\s+you\s+for\s+choosing\s+the\s+(.*?)(?:,|for)#iu", $text);

        if (empty($hotelName)) {
            $hotelName = $this->re("/We hope in the future if your travels bring you back to our area you will\s+remember the\s+([^.\n]+)/", $text);
        }
        $h->hotel()
            ->name($hotelName)
            ->noAddress();

        if (preg_match('#Yours\s+sincerely,\s*' . $h->getHotelName() . '\s+Hotel\s+((?s).*)\s+([\d\-]+)\n\n#i', $text, $m)) {
            $h->hotel()
                ->address($m[1])
                ->phone($m[2]);
        } else {
            $h->hotel()
                ->noAddress();
        }

        $dateStrIn = $this->re('#Arrival\s+Date\s*(?:-|:\*+\s+\w+,)\s*(.*)#', $text);
        $timeStrIn = $this->re('#check-in\s+time\s+is\s+after\s+(\d+:\d+(?:am|pm))#is', $text);

        $dateStrOut = $this->re('#Departure\s+Date\s*(?:-|:\*+\s+\w+,)\s*(.*)#', $text);
        $timeStrOut = preg_replace('/\s+/', '', $this->re('#check-out\s+time\s+is\s+by\s+(\d+:\d+(?:am|p\s*m))#is', $text));

        $h->booked()
            ->checkIn($this->normalizeDate($dateStrIn . ', ' . $timeStrIn))
            ->checkOut($this->normalizeDate($dateStrOut . ', ' . $timeStrOut));

        $rooms = $this->re('#Number\s+of\s+Rooms\s+-\s+(\d+)#', $text);

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        if (preg_match("#Adults/Children\s*(?:-|:\*+)\s*(\d+)\s+Adult\(s\)\s*/\s*(\d+)\s+Child#iu", $text, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        $cancellation = $this->re('#or\s*cancellation\s*as\s*follows\:\s+\-*\s*(.+)\n\n#siu', $text);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $roomType = $this->re("#Room\s+Type\s+-\s+(.*)#", $text);
        $roomDescription = $this->re("#\n\s*Accomodations\s*:[*\s]*([^\n]+)#", $text);
        $rate = $this->re("#(?:Room\s+Rate|Rate\s+Per\s+Night)\s*(?:-|:\*+)\s*([\d\,\.]+)#", $text);

        if (!empty($roomType) || !empty($roomDescription) || !empty($rate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }

        if (preg_match("#we\s+are\s+pleased\s+to\s+confirm\s+your\s+room\s+reservation#", $text)) {
            $h->general()
                ->status('Confirmed');
        }

        if (preg_match("#Cancellation\s+of\s+Reservation#", $text)) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        if (stripos($text, 'marriott.com') !== false) {
            $email->setProviderCode('marriott');
        }

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

    public static function getEmailProviders()
    {
        return ['spg', 'marriott'];
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})\s*\,\s*$#s", //08-OCT-2021
            "#^(\d+)\-(\w+)\-(\d{4})\s*\,\s*([\d\:]+A?P?\s*M)$#s", //11-JAN-2014, 3:00PM
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
