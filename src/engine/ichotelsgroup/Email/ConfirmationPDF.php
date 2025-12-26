<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-1699851.eml"; // +1 bcdtravel(pdf)[en]

    public $reFrom = "@ihg.com";
    public $reSubject = [
        "en"=> "Hotel Confirmation for",
    ];
    public $reBody = ['InterContinental', 'Holiday Inn'];
    public $reBody2 = [
        "en"=> "Confirmation Number",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        //$this->logger->debug($text);
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Confirmation Number\s+(\d+)#", $text);

        // TripNumber
        // ConfirmationNumbers

        // HotelName
        if (strpos($text, "Property Name") !== false) {
            $it['HotelName'] = $this->re("#Property Name\s+([^\n]+)#", $text);
            // Address

            // match last column
            $lcpos = 0;
            $rows = explode("\n", $text);

            foreach ($rows as $row) {
                if (strpos($row, "Property Name")) {
                    $lcpos = strpos($row, "Property Name") + strlen("Property Name");

                    break;
                }
            }
            $lcrows = [];

            foreach ($rows as $row) {
                if (trim(substr($row, $lcpos))) {
                    $lcrows[] = trim(substr($row, $lcpos));
                }
            }

            if (isset($lcrows[1], $lcrows[2])) {
                $it['Address'] = $lcrows[1] . ', ' . $lcrows[2];
            }

            // Phone
            $it['Phone'] = $this->re("#Phone Number\s+([^\n]+)#", $text);
            // Fax
            $it['Fax'] = $this->re("#Fax Number\s+([^\n]+)#", $text);
        } elseif (preg_match("#(?:\s*\n){3,}((.*\n){1,4}\s*Telephone:.*)$#", $text, $info)) {
            $it['HotelName'] = $this->re("#^\s*(.+?)\s*\n\s*\d+\s+#", $info[0]);
            $it['Address'] = preg_replace("#\s*\n\s*#", ', ', $this->re("#.*?\s*\n\s*(\d+[\s\S]*?)\s*\n\s*Telephone:#", $info[0]));
            // Phone
            $it['Phone'] = trim($this->re("#Telephone:\s+([\d\(\)\s\+-]+)#", $info[0]));
            // Fax
            $it['Fax'] = trim($this->re("#Fax:\s+([\d\(\)\s\+-]+)#", $info[0]));
        } elseif (strpos($text, 'Thank you for choosing') !== false) {
            // Thank you for choosing the Holiday Inn Express Carpinteria. We look
            $it['HotelName'] = trim($this->re('/Thank you for choosing the (.+?)\.\s+We look/', $text));
            $lastWorld = end(explode(' ', $it['HotelName']));
            $it['Address'] = preg_replace('/\s{2,}/', ' ', $this->re("/reservations\@carpinteriaexpress\.com.+?{$lastWorld}\n(.+?)$/s", $text));
        } else {
            $rows = array_filter(explode("\n", $text));
            $htext = trim(end($rows));
            $htext = trim(prev($rows)) . "\n" . $htext;
            // echo $htext;
            // die();
            $it['HotelName'] = $this->re("#(.*?)\s+\d+\s+#", $htext);
            $it['Address'] = $this->re("#.*?\s+\d+\s+(.*?)\s+Telephone:#", $htext);
            // Phone
            $it['Phone'] = trim($this->re("#Telephone:\s+([\d\(\)\s\+-]+)#", $htext));
            // Fax
            $it['Fax'] = trim($this->re("#Fax:\s+([\d\(\)\s\+-]+)#", $htext));
        }

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Arrival\s{2,}(.*?)(?:\s{2,}|\n)#", $text) . ',' . $this->re("#Our Check-?in time is\s+(\d+:\d+[ap]m)#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Departure\s{2,}(.*?)(?:\s{2,}|\n)#", $text) . ',' . $this->re("#Check-?out time is\s+(\d+:\d+[ap]m)#", $text)));

        // GuestNames
        $it['GuestNames'] = array_filter([$this->re("#\nName\s{2,}(.*?)(?:\s{2,}|\n)#", $text)]);

        // Guests
        $it['Guests'] = $this->re("#No\. of Guests\s+(\d+)\s*/\s*\d+#", $text);

        // Kids
        $it['Kids'] = $this->re("#No\. of Guests\s+\d+\s*/\s*(\d+)#", $text);

        // Rooms
        $it['Rooms'] = $this->re("#No\. of Rooms\s+(\d+)#", $text);

        // Rate
        $it['Rate'] = $this->re("#Rate Plan\s{2,}(.*?)(?:\s{2,}|\n)#", $text);

        // RateType
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->re("#Room Description\s{2,}(.*?)(?:\s{2,}|\n)#", $text);

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*ihg_confirmation.*.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        $detected = false;

        foreach ($this->reBody as $re) {
            if (strpos($text, $re) !== false) {
                $detected = true;
            }
        }

        if (!$detected) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*ihg_confirmation.*.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'ConfirmationPDF' . ucfirst($this->lang),
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
        //		 $this->http->log($str);
        $in = [
            "#^\s*(\d+)-(\d+)-(\d{2}),(\d+:\d+[ap]m)\s*$#", //01-29-14,10:00pm
            "#^\s*(\d+)-(\d+)-(\d{2})[\s,]*$#", //01-29-14,
        ];
        $out = [
            "$2.$1.20$3, $4",
            "$2.$1.20$3",
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
