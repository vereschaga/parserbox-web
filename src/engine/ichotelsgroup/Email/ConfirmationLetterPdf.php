<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationLetterPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@ihg.com";
    public $reSubject = [
        "en" => "transportation",
    ];
    public $reBody = ['@intercontisuzhou.com', '@intercontinentalfs.com', 'InterContinental'];
    public $reBody2 = [
        "en" => ["Reservation Information", "Reservation Confirmation"],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = null;

    public function parsePdf(&$itineraries)
    {
        $pdfs = $this->parser->searchAttachmentByName('Confirmation\s+Letter\s+for.*\.pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($this->parser->getAttachmentBody($pdf))) === null) {
                return;
            }
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#Confirmation Number(?:\(s\))?\s+\/\s+.*?(\d+)\s*(?:\n|Status)#", $text);

            // TripNumber
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->re("#We are looking forward to welcoming you at (.*?) and wish#", $text);

            if (empty($it['HotelName'])) {
                $it['HotelName'] = $this->re("#Thank you for your recent inquiry with regards of the accommodation at (.*?)\. We are#", $text);
            }

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Arrival Date\s+\/.*?:?\s+([^\d\s]+-\d+-\d{4}|\d+-\d+-\d{2})#", $text) . ', ' . $this->re("#ETA\s+/.*?:\s+(.+)#", $text)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Departure Date\s+\/.*?:?\s+([^\d\s]+-\d+-\d{4}|\d+-\d+-\d{2})#", $text) . ', ' . $this->re("#ETD\s+/.*?:\s+(.+)#", $text)));

            // Address
            $it['Address'] = $this->re("#Reservations Department\s+([^\n]+)#ms", $text);

            if (empty($it['Address'])) {
                $it['Address'] = $this->re("#\n\s*(\d+.+)$#", $text);
            }

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->re("#Tel\s+\/.*?:\s+([\+\d\s]+)\n#", $text);

            // Fax
            $it['Fax'] = $this->re("#Fax\s+\/.*?:\s+([\+\d\s]+)\n#", $text);

            // GuestNames
            $it['GuestNames'] = $this->re("#Guest Name\(s\)\s+\/.*?:?\s+([A-Za-z\s]+)\n#", $text);

            // Guests
            $it['Guests'] = $this->re("#Number of (?:Guest\(s\)|Adult\/Child)\s+\/.*?:?\s+(\d+)#", $text);

            // Kids
            // Rooms
            $it['Rooms'] = $this->re("#Number of Room\(s\)\s+\/.*?:?\s+(\d+)#", $text);

            // Rate
            $it['Rate'] = $this->re("#Daily Rate\s+\/.*?[:\)]\s+(.+?)(?:\n|\s*Arrival)#", $text);

            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->re("#(?:Room Type|Accommodation)\s+\/\s*(?:房间类型|.*?):?\s+(.+?)(?:\n|\s*Arrival)#", $text);

            // RoomTypeDescription
            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('Confirmation\s+Letter\s+for.*\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        $flag = false;

        foreach ($this->reBody as $reBody) {
            if (strpos($text, $reBody) !== false) {
                $flag = true;
            }
        }

        if (!$flag) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (is_array($re)) {
                foreach ($re as $r) {
                    if (strpos($text, $r) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (strpos($text, $re) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        if ($this->lang == null) {
            $this->detectEmailByBody($parser);
        }

        if ($this->lang == null) {
            return;
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
        $year = date("Y", $this->date);

        $in = [
            "#^([^\d\s]+)-(\d+)-(\d{4}),\s+TBA$#", //MAY-03-2017, TBA
            "#^([^\d\s]+)-(\d+)-(\d{4}),\s+(\d+:\d+)$#", //MAY-04-2017, 12:00
            "#^(\d+)-(\d+)-(\d{2}),.*$#", //05-30-17,
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",
            "20$3-$1-$2",
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
}
