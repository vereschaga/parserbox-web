<?php

namespace AwardWallet\Engine\wizz\Email;

class FlightReminder extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "wizz/it-10378450.eml, wizz/it-10450182.eml, wizz/it-13883898.eml, wizz/it-4249133.eml, wizz/it-4252499.eml, wizz/it-4551569.eml, wizz/it-4552948.eml";

    public static $dictionary = [
        "de" => [
            'Ihr Fluginformationen' => ['Ihr Fluginformationen', 'Ihre Flugdaten und -zeiten:'],
            'Ihr Bestätigungscode:' => ['Ihr Flugbestätigungscode', 'Ihr Bestätigungscode:', 'Ihr Flugbestätigungscode:'],
        ],
        "it" => [
            "Ihr Bestätigungscode:"=> "Codice di conferma del volo:",
            "Ihr Fluginformationen"=> "Dettagli del volo",
        ],
        "es" => [
            "Ihr Bestätigungscode:" => ["Código de confirmación de su vuelo:", 'Código de confirmación del vuelo:'],
            "Ihr Fluginformationen" => ["Ofertas de vuelos", 'Datos del vuelo'],
        ],
        "en" => [
            "Ihr Bestätigungscode:"=> "Your flight confirmation code:",
            "Ihr Fluginformationen"=> "Your flight details",
        ],
        "uk" => [
            "Ihr Bestätigungscode:"=> "Ваш код підтвердження замовлення рейсу:",
            "Ihr Fluginformationen"=> "Дата та година вашого рейсу:",
        ],
        "nl" => [
            "Ihr Bestätigungscode:"=> "Uw vluchtbevestigingscode:",
            "Ihr Fluginformationen"=> "Uw vluchtdata en -tijden:",
        ],
        "pt" => [
            "Ihr Bestätigungscode:"=> "O código de confirmação do seu voo:",
            "Ihr Fluginformationen"=> "As datas e as horas do seu voo:",
        ],
    ];

    private $reFrom = "notifications@notifications.wizzair.com";
    private $reSubject = [
        "de"=> "Erinnerung an Ihren Wizz Air-Flug",
        "it"=> "Promemoria per il volo Wizz Air",
        "es"=> "Recordatorio de vuelos de Wizz Air",
        "en"=> "Wizz Air Flight Reminder",
        "pt"=> "Wizz Air Flight Reminder",
    ];
    private $reBody = 'Wizz Air';
    private $reBody2 = [
        "de" => ["Ihr Fluginformationen", 'vielen Dank, dass Sie sich für Wizz Air entschieden haben'],
        "it" => "Dettagli del volo",
        "es" => ["Ofertas de vuelos", 'Datos del vuelo'],
        "en" => "Your flight details",
        'uk' => 'Дякуємо за замовлення в компанії Wizz Air! Наближається час відправлення вашого рейсу',
        'nl' => 'Hartelijk dank voor uw boeking bij Wizz Air',
        'pt' => 'Obrigado por reservar com a Wizz Air',
    ];

    private $lang = "de";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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
            if (is_string($re) && strpos($body, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"
        $body = $this->http->Response["body"];

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
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

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $re = is_array($this->t("Ihr Bestätigungscode:")) ? implode('|', $this->t("Ihr Bestätigungscode:")) : $this->t("Ihr Bestätigungscode:");
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Ihr Bestätigungscode:")) . "]", null, true, "#(?:" . str_replace(':', '\:', $re) . ")\s+(\w+)#");

        // TripNumber
        // Passengers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Ihr Fluginformationen")) . "]/following::table[1]//tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(.*?)\s+\(\s*\d{2}\d{2}\s*\)#");

            if (preg_match("#(.+?) - ([\-]*Terminal.*)#i", $itsegment['DepName'], $m)) {
                $itsegment['DepName'] = trim($m[1]);
                $itsegment['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[2]));
            }

            // DepCode
            if (!empty($itsegment['DepName'])) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $this->http->FindSingleNode("./td[2]", $root, true, "#\(\s*(\d{2}\d{2})\s*\)#")), $date);

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(.*?)\s+\(\s*\d{2}\d{2}\s*\)#");

            if (preg_match("#(.+?) - ([\-]*Terminal.*)#i", $itsegment['ArrName'], $m)) {
                $itsegment['ArrName'] = trim($m[1]);
                $itsegment['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m[2]));
            }

            // ArrCode
            if (!empty($itsegment['ArrName'])) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $this->http->FindSingleNode("./td[3]", $root, true, "#\(\s*(\d{2}\d{2})\s*\)#")), $date);

            // AirlineName
            if ($this->http->XPath->query("//a[contains(@href, 'wizzair.com')]")->length > 3) {
                $itsegment['AirlineName'] = "W6";
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
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
            "#^(\d{4})/(\d+)/(\d+)$#",
        ];
        $out = [
            "$2/$3/$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
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
}
