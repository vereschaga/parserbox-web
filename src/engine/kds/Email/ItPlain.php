<?php

// bcdtravel
//TODO: looks like similar format with It5636106

namespace AwardWallet\Engine\kds\Email;

class ItPlain extends \TAccountChecker
{
    public $mailFiles = "kds/it-12590604.eml";

    public $reFrom = "wave.support@kds.com";
    public $reSubject = [
        "Votre trajet vers",
        "Demande de voyage ",
    ];
    public $reBody = 'kds';
    public $reBody2 = [
        "fr" => ["Votre trajet vers", "Restitution du véhicule", "Ce message vous a été envoyé automatiquement"],
    ];

    public static $dictionary = [
        "fr" => [],
    ];

    public $lang = "fr";

    protected $result = [];
    protected $pax = [];
    protected $total = [];
    private $type;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        $this->lang = $this->detect($body, $this->reBody2);

        $itineraries = $this->parseEmail($body);

        if (count($this->total) == 2) {
            return [
                'parsedData' => ['Itineraries' => $itineraries, 'TotalCharge' => $this->total],
            ];
        }

        return [
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //return strpos($headers["from"], $this->reFrom) !== false;
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        if (stripos($body, $this->reBody) !== false && $this->detect($body, $this->reBody2)) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; //flights + hotels + cars
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseEmail($plainText)
    {
        $hotelText = $this->findCutSection($plainText, $this->t('Hôtel'), [$this->t('Prix estimé')]);

        if (!empty($hotelText)) {
            $this->logger->debug('go to parse by It5636106.php');

            return null;
//            $this->parseItsHotel($hotelText);
//            $this->type = '1';
        }

        $this->parsePassengers($this->findCutSection($plainText, $this->t('Voyageur'), [$this->t('Transport'), $this->t('Location de voiture')]));

        if (empty($this->pax['Passengers']) && preg_match("#Voyageur[:\s\-]+(.+)#", $plainText, $m)) {
            $this->pax['Passengers'] = [$m[1]];
        }

        $this->parseTotal($this->findCutSection($plainText, 'Prix estimé', 'Livraison des documents de voyage'));

        $this->result = [];
        $flyText = $this->findCutSection($plainText, $this->t('Transport'), [$this->t('Location de voiture'), $this->t('Hôtel'), $this->t('Prix estimé')]);

        if (!empty($flyText)) {
            $this->parseItsFly($flyText);
            $this->type = '1';
        }

        if (empty($this->type)) {
            $this->type = '2';
            $arr = $this->splitter("#\n( *Prix\s*:\s*)#", $this->findCutSection($plainText, null, [$this->t('Prix estimé'), $this->t('Prix Estimé')]));

            if (count($arr) === 0) {
                $this->logger->debug('other format');

                return false;
            }

            foreach ($arr as $root) {
                if (strpos($root, 'Nom du loueur') !== false) {
                    $this->parseItsCar($root);
                } elseif (mb_strpos($root, 'Nom de l\'Hôtel') !== false) {
                    $this->parseItsHotel($root);
                } else {
                    $this->logger->debug('unknown type of segment');

                    return false;
                }
            }
        }

        return $this->result;
    }

    protected function parseItsFly($plainText)
    {
        $its = [];
        $airs = $this->recordLocator($this->findCutSection($plainText, $this->t('Numéro de confirmation'), $this->t('Billet à émettre avant le')));

        $text = $this->findCutSection($plainText, $this->t('Billet à émettre avant le'), $this->t('Forme de paiement'));
        $segmentsSplitter = "Segment.+?(Billet électronique)";

        foreach (preg_split('/' . $segmentsSplitter . '/', $text, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true && strlen($value) > 50) {
                if (preg_match("#\n\s*Vol\s*:\s*(.+?)\s*[A-Z\d]{2}\d+#", $value, $vol) && isset($airs[trim($vol[1])])) {
                    $rl = $airs[$vol[1]];
                    $num = -1;

                    if (count($its) > 0) {
                        foreach ($its as $i => $it) {
                            if ($rl == $it['RecordLocator']) {
                                $num = $i;

                                break;
                            }
                        }
                    }

                    if ($num < 0) {
                        $itNew['Kind'] = 'T';
                        $itNew['RecordLocator'] = $rl;

                        if (isset($this->pax) && count($this->pax) == 2) {
                            $itNew['TicketNumbers'] = $this->pax['TicketNumbers'];
                            $itNew['Passengers'] = $this->pax['Passengers'];
                        }

                        $itNew['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));

                        if (isset($itNew['TripSegments'][0]['Status'])) {
                            $itNew['Status'] = $itNew['TripSegments'][0]['Status'];
                            unset($itNew['TripSegments'][0]['Status']);
                        }
                        $its[] = $itNew;
                    } else {
                        $cnt = count($its[$num]['TripSegments']);
                        $its[$num]['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));

                        if (isset($its[$num]['TripSegments'][$cnt]['Status'])) {
                            $its[$num]['Status'] = $its[$num]['TripSegments'][$cnt]['Status'];
                            unset($its[$num]['TripSegments'][$cnt]['Status']);
                        }
                    }
                }
            }
        }

        if (count($its) == 1 && preg_match("#\n\s*Prix\s*:\s*(\d[\d\,]+)\s*([A-Z]{3})#", $plainText, $m)) {
            $its[0]['TotalCharge'] = str_replace(",", ".", $m[1]);
            $its[0]['Currency'] = $m[2];
        }

        foreach ($its as $it) {
            $this->result[] = $it;
        }
    }

    /*
    Prix          : 663,41 EUR
    Nom de l\'Hôtel     : Randers
    Chaine de l\'Hôtel  : Hotel Service
    Arrivée            : 14/10/2018
    Départ             : 19/10/2018
    Chambre            : single - standardChambre standard: La chambre standard est équipée de douche/WC ou de baignoire/WC. - Tarif HRS
    Adresse            : Torvegade 11 - 8900 Randers
    Numéro de confirmation : 116967637 15704
        Conditions d\'annulation (Informations disponibles uniquement dans cette langue)
        Informations Tarifaires
        * De 2018-10-14 à 2018-10-19 (Tarif HRS):
        * 1099.00 DKK perNight
        * De 2018-10-14 à 2018-10-19 (Remise pour entreprises):
        * -109.90 DKK perNight
        Taxes
        * vat Inclus 25 % ()860.09 DKK ()
        * serviceTax Inclus 15 % ()645.07 DKK ()
        Services inclus
    * Utilisation d\'un terminal d\'accès Internet dans l\'hôtel
    * Accès réseau local sans fil dans la chambre
    * Petit déjeuner inclus
        Durée minimale et maximale
        * Séjour minimum0 nuit(s)
        Politique d\'annulation
        * Date limite d\'annulation sans frais : 2018-10-14 à 18:00 (heure locale de
        * l\'hôtel).
     */
    protected function parseItsHotel($text)
    {
        $it = [];
        $it['Kind'] = 'R';

        if (preg_match("#\n\s*Prix\s*:\s*(\d[\d.,]+)\s*([A-Z]{3})#", $text, $m)) {
            $it['Total'] = str_replace(",", ".", $m[1]);
            $it['Currency'] = $m[2];
        }

        if (!empty($this->pax['Passengers'])) {
            $it['GuestNames'] = $this->pax['Passengers'];
        }

        if (preg_match("#\n\s*(?:Nom de l\'Hôtel|Nom de l hôtel)\s*:\s*(.+?)\n#", $text, $m)) {
            $it['HotelName'] = $m[1];
        }

        if (preg_match("#\n\s*Adresse\s*:\s*(.+?)\n#", $text, $m)) {
            $it['Address'] = $m[1];
        }

        if (preg_match("#\n\s*Téléphone\s*:\s*(.+?)\n#", $text, $m)) {
            $it['Phone'] = $m[1];
        }

        if (preg_match("#\n\s*Fax\s*:\s*(.+?)\n#", $text, $m)) {
            $it['Fax'] = $m[1];
        }

        if (preg_match("#Numéro de confirmation\s*:\s*([\w\- ]+)#", $text, $m)) {
            $it['ConfirmationNumber'] = str_replace(' ', '-', $m[1]);
        } else {
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        }

        if (preg_match("#\n\s*Arrivée\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($m[1]));
        }

        if (preg_match("#\n\s*Départ\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CheckOutDate'] = strtotime($this->normalizeDate($m[1]));
        }

        if (preg_match("#\n\s*Chambre\s*:\s*(.+?)\n#", $text, $m)) {
            $node = $m[1];

            if (preg_match("#(.+?)\s*:\s+(.+)#", $node, $m)) {
                $it['RoomType'] = $m[1];
                $it['RoomTypeDescription'] = $m[2];
            } else {
                $it['RoomTypeDescription'] = $node;
            }
        }

        if (preg_match("#\bPolitique d\'annulation[:\s]*(.+?)(?:\n\n|$)#su", $text, $m)) {
            $it['CancellationPolicy'] = $m[1];
        }

        $this->result[] = $it;
    }

    /*
      Prix					  : 32.04 EUR
      Nom du loueur           : Europcar
      Prise du véhicule       : Europcar La Defense Delivery La Defense Centre Paris La Defense
      Restitution du véhicule : Europcar Paris Gare De Lyon (Outside) 193 Rue De Bercy Paris
      Date de prise           : 06/03/2017 14:00
      Date de retour          : 07/03/2017 08:00
      Classe                  : Economique
      Type                    : 4 Portes
      Transmission            : Manuel
      Air Cond                : Avec A/C
     */
    protected function parseItsCar($text)
    {
        $it = [];
        $it['Kind'] = 'L';

        if (preg_match("#^\s*Prix\s*:\s*(\d[\d.,]+)\s*([A-Z]{3})#m", $text, $m)) {
            $it['TotalCharge'] = str_replace(",", ".", $m[1]);
            $it['Currency'] = $m[2];
        }

        if (!empty($this->pax['Passengers'])) {
            $it['RenterName'] = $this->pax['Passengers'][0];
        }

        if (preg_match("#\n\s*(?:\(service non conforme à la politique de voyage\)\s*)?Nom du loueur\s*:\s*(.+?)\n#", $text, $m)) {
            $it['RentalCompany'] = $m[1];
        }

        if (preg_match("#\n\s*(?:Prise du véhicule|Adresse)\s*:\s*(.+?)\n#", $text, $m)) {
            $it['PickupLocation'] = $m[1];
        }

        if (preg_match("#\n\s*(?:Restitution du véhicule|Adresse)\s*:\s*(.+?)\n#", $text, $m)) {
            $it['DropoffLocation'] = $m[1];
        }

        if (preg_match("#Numéro de confirmation\s*:\s*([A-Z\d]+)#", $text, $m)) {
            $it['Number'] = $m[1];
        }

        if (preg_match("#\n\s*Date de prise\s*:\s*(.+?)\n#", $text, $m)) {
            $it['PickupDatetime'] = strtotime($this->normalizeDate($m[1]));
        }

        if (preg_match("#\n\s*Date de retour\s*:\s*(.+?)\n#", $text, $m)) {
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($m[1]));
        }

        $it['CarType'] = [];

        if (preg_match("#\n\s*Type\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CarType'][] = $m[1];
        }

        if (preg_match("#Classe\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CarType'][] = $m[1];
        }

        if (preg_match("#\n\s*Transmission\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CarType'][] = $m[1];
        }

        if (preg_match("#\n\s*Air Cond\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CarType'][] = $m[1];
        }

        if (preg_match("#\n\s*Autre équipement\s*:\s*(.+?)\n#", $text, $m)) {
            $it['CarType'][] = $m[1];
        }

        $it['CarType'] = join(', ', $it['CarType']);
        $this->result[] = $it;
    }

    protected function recordLocator($recordLocator)
    {
        $airs = [];

        if (preg_match_all("#\s*:?\s*(.+?)\s*:\s*([A-Z\d]+)#", $recordLocator, $m)) {
            if (is_array($m[1])) {
                foreach ($m[1] as $i => $v) {
                    $airs[$v] = $m[2][$i];
                }
            }// else $airs[$m[1]] => $m[2];
        }

        return $airs;
    }

    protected function parseTotal($total)
    {
        if (preg_match('#\nTotal\s*:\s*(\d[\d\,]+)\s*([A-Z]{3})#', $total, $m)) {
            $this->total['Amount'] = str_replace(",", ".", $m[1]);
            $this->total['Currency'] = $m[2];
        }
    }

    protected function parsePassengers($plainText)
    {
        if (preg_match_all("#\n([^\s]+.+)\n#u", $plainText, $m)) {
            if (is_array($m[1])) {
                $this->pax['Passengers'] = array_unique($m[1]);
            } else {
                $this->pax['Passengers'] = [$m[1]];
            }
        }
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match('#\n\s*Vol\s*:\s*.+?\s*([A-Z\d]{2})(\d+)#u', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        if (preg_match("#" . $this->t('Départ') . "\s*:\s*(.+)\s+(\d+\/\d+\/\d+\s+\d+:\d+)\s*(?:\((.+)\))?#u", $value, $m)) {
            $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            $segment['DepName'] = trim($m[1]);

            if (isset($m[3]) && !empty($m[3])) {
                $segment['DepartureTerminal'] = trim($m[3]);
            }
            $segment['DepDate'] = strtotime($this->normalizeDate($m[2]));
        }

        if (preg_match("#" . $this->t('Arrivée') . "\s*:\s*(.+)\s+(\d+\/\d+\/\d+\s+\d+:\d+)\s*(?:\((.+)\))?#u", $value, $m)) {
            $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $segment['ArrName'] = trim($m[1]);

            if (isset($m[3]) && !empty($m[3])) {
                $segment['ArrivalTerminal'] = trim($m[3]);
            }
            $segment['ArrDate'] = strtotime($this->normalizeDate($m[2]));
        }

        if (preg_match("#" . $this->t('Classe') . "\s*:\s*(.+)#u", $value, $m)) {
            $segment['Cabin'] = $m[1];
        }

        return $segment;
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+\S{3}\s+\d{4})\s+(\d+:\d+)$#",
            "#^\S+\s+(\d+\s+\S+\s+\d{4})\s+(\d+:\d+)$#",
            "#^(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+)$#",
            "#^(\d+)\/(\d+)\/(\d+)$#",
        ];
        $out = [
            "$1, $2",
            "$1, $2",
            "$1.$2.$3, $4",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish(mb_strtolower($str));
        }

        return $str;
    }

    private function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $lang;
                }
            }
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
