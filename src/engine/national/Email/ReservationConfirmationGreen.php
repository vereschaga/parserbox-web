<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmationGreen extends \TAccountChecker
{
    public $mailFiles = "national/it-1619692.eml, national/it-1895764.eml, national/it-1903465.eml, national/it-1907880.eml, national/it-2304344.eml, national/it-3119813.eml, national/it-3144429.eml, national/it-3161114.eml, national/it-6734259.eml";
    public $reFrom = "@nationalcar.com";
    public $reSubject = [
        "en" => "National Car Rental Reservation Confirmation",
        "fr" => "Confirmation de la réservation National Car Rental",
        "pt" => "Confirmação de Reserva National Car Rental",
    ];
    public $reBody = 'nationalcar.com';
    public $reBody2 = [
        "en" => ["Reservation Information", 'National Car Rental'],
        "fr" => "Informations sur la réservation",
        "pt" => "Informações sobre a reserva",
    ];

    public static $dictionary = [
        "en" => [
            "Car Class:"=> ["Car Class:", "Your Vehicle:"],
        ],
        "fr" => [
            "Confirmation Number"              => "Numéro de confirmation",
            "Pickup Information"               => "Informations sur le retrait",
            "Dropoff Information"              => "Informations sur le dépôt",
            "Date & Time:"                     => "Date et heure :",
            "Location:"                        => "Agence :",
            "Address:"                         => "Adresse :",
            "Phone:"                           => "Téléphone:",
            "Hours:"                           => "Horaires :",
            "Car Class:"                       => "Type de véhicule :",
            "Name:"                            => "Nom :",
            "Total Estimate"                   => "Estimation du total",
            "Your reservation has been cancel" => "NOTTRANSLATED",
        ],
        "pt" => [
            "Confirmation Number"              => "Seu número de confirmação é:",
            "Pickup Information"               => "Informações de retirada",
            "Dropoff Information"              => "Informações do devolução",
            "Date & Time:"                     => "Data e horário:",
            "Location:"                        => "Localização:",
            "Address:"                         => "Endereço:",
            "Phone:"                           => "Telefone",
            "Hours:"                           => "Horário:",
            "Car Class:"                       => "Classe do automóvel:",
            "Name:"                            => "Nome:",
            "Total Estimate"                   => "Total estimado",
            "Your reservation has been cancel" => "NOTTRANSLATED",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->nextText([$this->t("Confirmation Number"), 'Your confirmation number is:']);
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq([$this->t("Pickup Information"), 'Collection information']) . "]/following::table[1]//td[" . $this->eq([$this->t("Date & Time:"), 'Date & time:']) . "]/following-sibling::td[1]")));

        // PickupLocation
        $it['PickupLocation'] = $this->http->FindSingleNode("//text()[" . $this->eq([$this->t("Pickup Information"), 'Collection information']) . "]/following::table[1]//td[" . $this->eq($this->t("Location:")) . "]/following-sibling::td[1]");

        if ($address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pickup Information")) . "]/following::table[1]//td[" . $this->eq($this->t("Address:")) . "]/following-sibling::td[1]")) {
            $it['PickupLocation'] = $address;
        }

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq([$this->t("Dropoff Information"), 'Drop-off information']) . "]/following::table[1]//td[" . $this->eq([$this->t("Date & Time:"), 'Date & time:']) . "]/following-sibling::td[1]")));

        // DropoffLocation
        $it['DropoffLocation'] = $this->http->FindSingleNode("//text()[" . $this->eq([$this->t("Dropoff Information"), 'Drop-off information']) . "]/following::table[1]//td[" . $this->eq($this->t("Location:")) . "]/following-sibling::td[1]");

        if ($address = $this->http->FindSingleNode("//text()[" . $this->eq([$this->t("Dropoff Information"), 'Drop-off information']) . "]/following::table[1]//td[" . $this->eq([$this->t("Address:"), 'Location:']) . "]/following-sibling::td[1]")) {
            $it['DropoffLocation'] = $address;
        }

        if (!$it['DropoffLocation'] && !$this->http->FindSingleNode("//text()[" . $this->eq($this->t("Dropoff Information")) . "]")) {
            $it['DropoffDatetime'] = MISSING_DATE;
            $it['DropoffLocation'] = $it['PickupLocation'];
        }

        // PickupPhone
        $it['PickupPhone'] = $this->http->FindSingleNode("//text()[" . $this->eq([$this->t("Pickup Information"), 'Collection information']) . "]/following::table[1]//td[" . $this->eq($this->t("Phone:")) . "]/following-sibling::td[1]");

        // PickupFax
        // PickupHours
        if ($node = $this->http->XPath->query("//text()[" . $this->eq($this->unionFields([$this->t("Pickup Information"), 'Collection information'])) . "]/following::table[1]//td[" . $this->eq($this->t("Hours:")) . "]/following-sibling::td[1]")->item(0)) {
            $it['PickupHours'] = trim(preg_replace("#<[^>]+>#", "", preg_replace("#<br[^>]*/?>#", "\n", $node->ownerDocument->saveHTML($node))));
        }

        // DropoffPhone
        // DropoffHours
        if ($node = $this->http->XPath->query("//text()[" . $this->eq($this->t("Dropoff Information")) . "]/following::table[1]//td[" . $this->eq($this->t("Hours:")) . "]/following-sibling::td[1]")->item(0)) {
            $it['DropoffHours'] = trim(preg_replace("#<[^>]+>#", "", preg_replace("#<br[^>]*/?>#", "\n", $node->ownerDocument->saveHTML($node))));
        }

        // DropoffFax
        // RentalCompany
        // CarType
        $it['CarType'] = $this->http->FindSingleNode("//td[" . $this->eq($this->unionFields([$this->t("Car Class:"), 'Car class:'])) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]");

        // CarModel
        $it['CarModel'] = $this->http->FindSingleNode("//td[" . $this->eq($this->unionFields([$this->t("Car Class:"), 'Car class:'])) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]");

        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->nextText($this->t("Name:"));

        // PromoCode
        // TotalCharge
        $it["TotalCharge"] = $this->amount($this->nextText([$this->t("Total Estimate"), 'Total estimate']));

        // Currency
        $it["Currency"] = $this->currency($this->nextText([$this->t("Total Estimate"), 'Total estimate']));

        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        if (stripos($this->http->Response['body'], $this->t('Your reservation has been cancel')) !== false) {
            $it["Status"] = 'cancelled';
            $it["Cancelled"] = true;
        }

        // ServiceLevel

        // PricedEquips
        // Discount
        // Discounts
        // Fees
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
            $re = (array) $re;

            foreach ($re as $r) {
                if (stripos($body, $r) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            $re = (array) $re;

            foreach ($re as $r) {
                if (stripos($this->http->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($itineraries);
        $classPart = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($classPart) . ucfirst($this->lang),
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
        // $this->http->log($word);
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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+@\s+(\d+:\d+\s+[AP]M)$#", //Wednesday, April 23, 2014 @ 9:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
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
        $code = $this->re('/\s*([A-Z]{3})\s*/', $s);

        if (!empty($code)) {
            return $code;
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

        if (count($field) === 0) {
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

    private function unionFields($field)
    {
        $result = [];

        foreach ($field as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $value);
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }
}
