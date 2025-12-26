<?php

namespace AwardWallet\Engine\thalys\Email;

class YourBookingConfirmation extends \TAccountCheckerExtended
{
    use \DateTimeTools;

    public $mailFiles = "thalys/it-2.eml, thalys/it-2201984.eml, thalys/it-3394461.eml";

    public $reBody = [
        "de" => ["Thalys.com", "Hier noch einmal die Einzelheiten:"],
        "fr" => ["Thalys.com", "Nous vous rappelons ci-dessous le détail"],
        "nl" => ["Thalys", "Bedankt voor uw aankoop van Thalys tickets via NMBS Europe"],
        "en" => ["Thalys", "You are reminded of the details below:"],
    ];
    public $reFrom = "noreply@thalys.com";
    public $reSubject = [
        "de" => "Bestätigung Ihrer Bestellung",
        "fr" => "Confirmation de votre commande",
        "nl" => "Bevestiging van uw Thalys boeking",
        "en" => "Confirmation of your order",
    ];

    private $lang = "de";
    private $dictionary = [
        "de" => [
            "Hinfahrt" => ["Hinfahrt", "Zurück"],
        ],
        "fr" => [
            "Buchungsnummer"     => "Référence",
            "GESAMTBETRAG"       => "TOTAL",
            "Hinfahrt"           => "Aller",
            "Sehr geehrter Herr" => "Bonjour Monsieur",
        ],
        "nl" => [
            "Buchungsnummer" => "dossierreferentie",
            "GESAMTBETRAG"   => "Totaal",
            "Hinfahrt"       => "Uw reisgegevens",
        ],
        "en" => [
            "Buchungsnummer" => "Reference",
            "GESAMTBETRAG"   => "TOTAL",
            "Hinfahrt"       => ["Outward", "Return"],
            "Platz"          => "Seat",
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            'html' => function (&$itineraries) {
                $body = $this->http->Response["body"];

                foreach ($this->reBody as $lang => $rules) {
                    $result = true;

                    foreach ($rules as $rule) {
                        if (strpos($body, $rule) === false) {
                            $result = false;
                        }
                    }

                    if ($result == true) {
                        $this->lang = $lang;
                        $this->http->Log("Select lang by body: " . $this->lang);

                        break;
                    }
                }

                $this->http->Log("Lang: " . $this->lang);

                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., '" . $this->t("Buchungsnummer") . "')]/following::text()[1]", null, true, "#\w{6,7}#");
                // TripNumber
                // Passengers
                if (empty($it['Passengers'] = array_unique($this->http->FindNodes("//img[contains(@src, '/ico_hidden_details.png')]/ancestor::td[1]/following-sibling::td[1]/b")))) {
                    $it['Passengers'] = $this->http->FindPreg('/' . $this->t('Sehr geehrter Herr') . '([^<]*)/');
                }

                // AccountNumbers
                // Cancelled
                // TotalCharge
                $total = "//*[contains(text(),'" . $this->t("GESAMTBETRAG") . "')]";

                if ($this->http->XPath->query($total)->length === 1) {
                    $it['TotalCharge'] = cost($this->http->FindSingleNode($total));
                    $it['Currency'] = currency($this->http->FindSingleNode($total));
                } else {
                    $it['TotalCharge'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("GESAMTBETRAG") . "']/following::text()[2]"));
                    $it['Currency'] = currency($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("GESAMTBETRAG") . "']/following::text()[2]"));
                }
                // BaseFare
                // Currency

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                $xpath = "//text()[" . implode(" or ", array_map(function ($s) { return "normalize-space(.)='{$s}'"; }, (array) $this->t("Hinfahrt"))) . "]/ancestor::tr[1]/..";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }
                // $xpath = "//*[".implode(" or ", array_map(function($s){ return "normalize-space(text())='{$s}'"; }, (array)$this->t("Hinfahrt")))."]/following::tr[starts-with(normalize-space(.), 'Reis')]";
                $this->http->log($xpath);
                // $nodes = $this->http->XPath->query($xpath);
                foreach ($nodes as $root) {
                    $date = strtotime(str_replace("/", ".", $this->http->FindSingleNode("./tr[1]/td[7]", $root)));

                    $itsegment = [];
                    //					$itsegment['data'] = $this->http->FindSingleNode(".", $root);
                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[4]", $root);
                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/td[2]", $root);
                    // Type
                    //					$itsegment['Type'] = $this->http->FindSingleNode("./tr[1]/td[6]", $root);
                    if (empty($itsegment['ArrName']) && empty($itsegment['DepName']) && preg_match("#van\s+(\w+\S+).*\s+naar\s+(\w+\S+)\s+.+\s+(\d+)#i", $this->http->FindSingleNode("following-sibling::tr[2]/td/strong[1]", $root), $math)) {
                        $itsegment['DepName'] = $math[1];
                        $itsegment['ArrName'] = $math[2];
                        $itsegment['FlightNumber'] = $math[3];
                    }
                    // DepDate
                    $itsegment['DepDate'] = strtotime(str_replace("h", ":", $this->http->FindSingleNode("./tr[1]/td[3]", $root)), $date);
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(str_replace("h", ":", $this->http->FindSingleNode("./tr[2]/td[1]", $root)), $date);
                    $time = $this->http->FindNodes("following-sibling::tr[2]/td/span", $root);

                    if (empty($itsegment['ArrDate']) && empty($itsegment['DepDate']) && empty($date) && preg_match("#.+ (\d{1,2} \w{1,6} \d{4})#", $this->http->FindSingleNode(".", $root), $m)) {
                        $itsegment['DepDate'] = strtotime($this->dateStringToEnglish($m[1]) . ' ' . array_shift($time));
                        $itsegment['ArrDate'] = strtotime($this->dateStringToEnglish($m[1]) . ' ' . array_shift($time));
                    }
                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $itsegment['Seats'] = implode(', ', $this->http->FindNodes("./following::table[1]//text()[contains(., '" . $this->t('Platz') . "')]", $root, "#" . $this->t('Platz') . "\s+(\d+)#"));

                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[6]", $root);

                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode("./tr[1]/td[8]", $root, true, "#\d+h\d+#");

                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $lang => $rules) {
            $result = true;

            foreach ($rules as $rule) {
                if (stripos($body, $rule) === false) {
                    $result = false;
                }
            }

            if ($result == true) {
                $this->lang = $lang;
                // $this->http->Log("Select lang by body: ".$this->lang);
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $lang=>$rule) {
            if (stripos($headers["from"], $this->reFrom) !== false || stripos($headers["subject"], $rule) !== false) {
                $this->lang = $lang;
                // $this->http->Log("Select lang by headers: ".$this->lang);
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }
        $processor = $this->processors['html'];
        $processor($itineraries);

        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ["de", "fr", "nl", "en"];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    private function t($word)
    {
        if (!isset($this->dictionary[$this->lang]) || !isset($this->dictionary[$this->lang][$word])) {
            return $word;
        }

        return $this->dictionary[$this->lang][$word];
    }
}
