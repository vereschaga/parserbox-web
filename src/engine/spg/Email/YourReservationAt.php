<?php

namespace AwardWallet\Engine\spg\Email;

use AwardWallet\Engine\MonthTranslate;

class YourReservationAt extends \TAccountChecker
{
    public $mailFiles = "spg/it-2246444.eml";

    public $reSubject = [
        "en"=> "Your Reservation at",
    ];
    public $reBody = ['Starwood', 'Vistana Signature Experiences'];
    public $reBody2 = [
        "en"=> "Your Reservation",
    ];

    public static $dictionary = [
        "en" => [
            "Confirmation Number:" => ["Confirmation Number", "Confirmation Number:"],
            "Arrival:"             => ["Arrival:", "Arrival"],
            "Departure:"           => ["Departure:", "Departure"],
            "Phone:"               => ["Phone:", "Phone"],
            "Fax:"                 => ["Fax:", "Fax"],
            "Guest Name:"          => ["Guest Name:", "Guest Name", "Explorer Package Holder:"],
            "Villa Type:"          => ["Villa Type:", "Villa Type"],
            "Occupancy:"           => ["Occupancy:", "Occupancy"],
            "Confirmation Date:"   => ["Confirmation Date:", "Confirmation Date"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        ];

        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText($this->t("Confirmation Number:"));

        // HotelName
        $it['HotelName'] = $this->nextText($this->t("Your Reservation"));

        // Saturday, 11/03/2018 at 4PM, local time
        $patterns['dateTime'] = "/^(?<date>.{6,}){$this->opt($this->t(' at '))}(?<time>{$patterns['time']})/";

        // CheckInDate
        $dateTimeCheckIn = $this->nextText($this->t("Arrival:"));

        if (preg_match($patterns['dateTime'], $dateTimeCheckIn, $m)) {
            $dateCheckInNormal = $this->normalizeDate($m['date']);

            if ($dateCheckInNormal) {
                $it['CheckInDate'] = strtotime($dateCheckInNormal . ' ' . $m['time']);
            }
        }

        // CheckOutDate
        $dateTimeCheckOut = $this->nextText($this->t("Departure:"));

        if (preg_match($patterns['dateTime'], $dateTimeCheckOut, $m)) {
            $dateCheckOutNormal = $this->normalizeDate($m['date']);

            if ($dateCheckOutNormal) {
                $it['CheckOutDate'] = strtotime($dateCheckOutNormal . ' ' . $m['time']);
            }
        }

        // Address
        $it['Address'] = $this->http->FindSingleNode("(.//text()[normalize-space()='Your Reservation'])[1]/following::text()[normalize-space(.)][2]/ancestor::span[1][not({$this->contains($this->t("Phone:"))})]");

        if (empty($it['Address'])) {
            $it['Address'] = $this->nextText($this->t("Your Reservation"), null, 2);
        }

        // Phone
        $it['Phone'] = trim($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Phone:")) . "]", null, true, "#" . $this->opt($this->t("Phone:")) . "\s+([\d\(\)\s-]+)#"), ' -');

        // Fax
        $it['Fax'] = trim($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Fax:")) . "]", null, true, "#" . $this->opt($this->t("Fax:")) . "\s+([\d\(\)\s-]+)#"), ' -');

        // GuestNames
        $it['GuestNames'] = [$this->nextText($this->t("Guest Name:"))];

        // Guests
        $it['Guests'] = $this->re("/(\d+)\s+{$this->opt($this->t('Maximum'))}/", $this->nextText($this->t("Occupancy:")));

        // RoomType
        $it['RoomType'] = $this->nextText($this->t("Villa Type:"));

        // ReservationDate
        $confirmationDate = $this->nextText($this->t("Confirmation Date:"));

        if ($confirmationDate) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($confirmationDate));
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Starwood Vacation') !== false
            || stripos($from, '@starwood') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject'],$headers['from'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody[0]) === false && strpos($body, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})$/u', $string, $matches)) {
            // 11/14/2014    |    Friday, 05/29/2015
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
