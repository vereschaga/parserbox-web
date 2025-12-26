<?php

namespace AwardWallet\Engine\marriott\Email;

class It5080794 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reSubject = [
        "en"=> "The Ritz-Carlton",
    ];
    public $reBody = 'The Ritz-Carlton';
    public $reBody2 = [
        "en"=> "Thank you for staying at our hotel",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $text = implode("\n", $this->http->FindNodes("/descendant::text()[normalize-space()]"));

        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->getField("Confirmation:");

        // Hotel Name
        $it['HotelName'] = trim($this->getField("Warm greetings from"), ' !');

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->getField("Arrival:") . ',' . $this->getField("Check In Time:")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getField("Departure:") . ',' . $this->getField("Check Out Time:")));

        // Address
        $it['Address'] = str_replace("\n", ", ", $this->re("#" . $it['HotelName'] . "\n" .
        "((?:[^\n]+\n){2})" .
        "Hotel:\s+[\d-]+#", $text));

        // Phone
        $it['Phone'] = $this->getField("Hotel:");

        // Fax
        $it['Fax'] = $this->getField("Fax:");

        // GuestNames
        $it['GuestNames'] = array_filter([$this->getField("Name:")]);

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->getField("Cancellation Policy:");

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ritzcarlton.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
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

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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

    private function getField($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(.), '{$field}')])[1]/ancestor::p[1]", $root, true, "#{$field}\s+(.+)#");
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
            "#^(\d+)/(\d+)/(\d{4}),(\d+:\d+\s+[ap]m)$#",
        ];
        $out = [
            "$2.$1.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

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
