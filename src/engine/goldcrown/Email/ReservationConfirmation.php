<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-3174114.eml";
    public $reFrom = "@bestwestern.no>";
    public $reSubject = [
        "no"=> "Best Western Norge : Din reservasjonsbekreftelse",
        "fr"=> "Best Western Hôtels Belgique : Confirmation de votre réservation",
    ];
    public $reBody = 'bestwestern';
    public $reBody2 = [
        "no"=> "Ditt reservasjonsnummer",
        "fr"=> "Votre numéro de réservation",
    ];

    public static $dictionary = [
        "no" => [],
        "fr" => [
            "Ditt reservasjonsnummer"   => "Votre numéro de réservation",
            "Bo fra"                    => "Séjour du",
            "Innsjekk"                  => "Arrivée",
            "Utsjekk"                   => "Départ",
            "voksne"                    => "Adulte",
            "Pris :"                    => "Tarif :",
            "Hele oppholdet"            => "Séjour total",
            "Rom d"                     => "Chambre d",
            "Kanselleringsbetingelser :"=> "Conditions d'annulation :",
        ],
    ];

    public $lang = "no";

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText($this->t("Ditt reservasjonsnummer"));

        // TripNumber
        // ConfirmationNumbers
        $it['ConfirmationNumbers'] = array_slice($this->http->FindNodes("//text()[" . $this->eq($this->t("Ditt reservasjonsnummer")) . "]/following::text()[normalize-space(.)][1]"), 1);

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, 'images.bestwestern.com')]/@alt");

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Bo fra")) . "]/ancestor::td[1]/b[1]") . ', ' .
        $this->re("#:\s*(.+)#", $this->nextText($this->t("Innsjekk")))));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Bo fra")) . "]/ancestor::td[1]/b[2]") . ', ' .
        $this->re("#:\s*(.+)#", $this->nextText($this->t("Utsjekk")))));

        // Address
        if (!$it['Address'] = $this->http->FindSingleNode("//img[contains(@src, 'images.bestwestern.com')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/text()[normalize-space(.)][1]")) {
            $it['Address'] = implode(" ", $this->http->FindNodes("//img[contains(@src, 'images.bestwestern.com')]/ancestor::div[1]/following-sibling::div[2]/descendant::text()[normalize-space(.)][position()<3]"));
        }

        // Phone
        if (!$it['Phone'] = $this->http->FindSingleNode("//img[contains(@src, 'images.bestwestern.com')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//a[@value][1]")) {
            $it['Phone'] = trim($this->nextText("Téléphone"), ' :');
        }

        // Guests
        $guests = array_sum($this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("Rom d") . "']/ancestor::td[1]", null, "#(\d+)\s+" . $this->t("voksne") . "#"));
        $it['Guests'] = !empty($guests) ? $guests : null;

        // Rooms
        $rooms = $this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("Rom d") . "']", null, "#\d+#");
        $it['Rooms'] = !empty($rooms) ? $rooms[count($rooms) - 1] : null;

        // RateType
        $rateType = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Pris :")) . "]/following::text()[normalize-space(.)][1]", null, "#(.*?),#"));
        $it['RateType'] = !empty($rateType) ? implode(". ", $rateType) : null;

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->nextText($this->t("Kanselleringsbetingelser :"));

        // RoomType
        $roomType = array_unique(array_map("trim", $this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("Rom d") . "']/ancestor::td[1]", null, "#([^,]*?),[^,]+$#")));
        $it['RoomType'] = !empty($roomType) ? implode(". ", $roomType) : null;

        // RoomTypeDescription
        $roomTypeDescription = array_map("trim", $this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("Rom d") . "']/ancestor::td[1]", null, "#:\s*(.*?),\s+[^,]*?,[^,]+$#"));
        $it['RoomTypeDescription'] = !empty($roomTypeDescription) ? $roomTypeDescription[count($roomTypeDescription) - 1] : null;

        // Total
        $it['Total'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hele oppholdet")) . "]", null, true, "#:\s*(.+)#"));

        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hele oppholdet")) . "]", null, true, "#:\s*(.+)#"));

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->reFrom) === false) {
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

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4}), (\d+:\d+)$#", //29/04/2016, 14:00
        ];
        $out = [
            "$1.$2.$3, $4",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
