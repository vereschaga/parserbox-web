<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

// format similar like FlightItinerary.php

class TravelItinerary extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-2191503.eml, mileageplus/it-27986164.eml, mileageplus/it-28471532.eml, mileageplus/it-5748574.eml, mileageplus/it-5878378.eml, mileageplus/it-6352309.eml, mileageplus/it-7104554.eml, mileageplus/it-7298199.eml, mileageplus/it-7372488.eml"; // +1 bcdtravel

    public $reSubject = [
        'Itinerario de viajes enviado por United Airlines, Inc', // es
        'Itinéraire de voyage envoyé par United Airlines, Inc', // fr
        'Itinerário de viagem enviado pela United Airlines, Inc', // pt
        'Travel Itinerary sent from United Airlines, Inc', // en
    ];

    public $reBody = [
        'es' => ['Número de confirmación', 'Salida'],
        'fr' => ['Numéro de confirmation', 'Départ'],
        'pt' => ['Número de confirmação', 'Partida'], // N&uacute;mero de confirma&ccedil;&atilde;o
        'en' => ['Confirmation Number', 'Depart'],
    ];

    public $lang = '';

    public static $dict = [
        'es' => [
            'Depart:' => 'Salida:',
            'Arrive:' => 'Llegada:',
            'Flight:' => 'Vuelo:',
            'Day'     => 'Día',
            //			'Operated by' => '',
            'Flight Time:'         => 'Tiempo de vuelo:',
            'Fare Class:'          => 'Clase de tarifa:',
            'Travel Time:'         => 'Tiempo de viaje:',
            'Aircraft:'            => 'Aeronave:',
            'Meal:'                => 'Alimentos:',
            'Flight distance:'     => 'Distancia de vuelo:',
            'Distance:'            => 'Distancia de vuelo:',
            'Confirmation Number:' => 'Número de confirmación',
            'Seat Assignments'     => 'Asignación de asientos',
        ],
        'fr' => [
            'Depart:' => 'Départ :',
            'Arrive:' => ['Arrivée:', 'Arrivée :'],
            'Flight:' => 'Vol :',
            'Day'     => 'Jour',
            //			'Operated by' => '',
            'Flight Time:'     => 'Durée du vol :',
            'Fare Class:'      => 'Classe tarifaire :',
            'Travel Time:'     => 'Durée du voyage :',
            'Aircraft:'        => 'Appareil :',
            'Meal:'            => 'Repas :',
            'Flight distance:' => 'Distance de vol :',
            //			'Distance:' => '',
            'Confirmation Number:' => 'Numéro de confirmation :',
            'Seat Assignments'     => 'Allocations de sièges :',
        ],
        'pt' => [
            'Depart:'              => 'Partida:',
            'Arrive:'              => 'Chegada:',
            'Flight:'              => 'Voo:',
            'Day'                  => 'Dia',
            'Operated by'          => 'Operado por',
            'Flight Time:'         => 'Tempo de voo:',
            'Fare Class:'          => 'Classe de tarifa:',
            'Travel Time:'         => 'Tempo de voo:',
            'Aircraft:'            => 'Aeronave:',
            'Meal:'                => 'Refeição:',
            'Flight distance:'     => 'Distância do voo:',
            'Distance:'            => 'Distância:',
            'Confirmation Number:' => 'Número de confirmação:',
            'Seat Assignments'     => 'Designação de assentos',
        ],
        'en' => [],
    ];

    private $seats = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = preg_replace(["/\<script.+?\<\/script\>/s", "/\<style.+?\<\/style\>/s"], ['', ''], $this->http->Response['body']);
        $this->http->SetEmailBody($body);

        $this->assignLang();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TravelItinerary_' . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"@united.com") or contains(normalize-space(.),"United Airlines, Inc")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.united.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'unitedairlines@united.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/(?:[\.@]united\.com|United Airlines)/', $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    private function parseSegmentsFormat2($nodes)
    {
        $trips = [];

        foreach ($nodes as $root) {
            $seg = [];
            $text = implode("\n", $this->http->FindNodes('.//text()[normalize-space()!=""]', $root));
            $flights = $this->splitText('#(' . $this->opt($this->t('Depart:')) . ')#', $text);

            foreach ($flights as $flight) {
                $seg = [];

                if (preg_match("#" . $this->opt($this->t('Depart:')) . "\s*(\d+:\d+(?: [ap]\.m\.)?)\s+[\w]+\.*,\s(\w+)\.*\s*(\w+)\.*,\s*(\d{4})\s+([^(]+)\s+\((([A-Z]{3})(\s*-\s*([^)]+))?)\)#u", $flight, $m)) {
                    $seg['DepCode'] = $m[7];
                    $seg['DepDate'] = strtotime($this->dateStringToEnglish(str_replace(".", "", $m[3] . ' ' . $m[2] . ' ' . $m[4] . ' ' . $m[1])));
                    $seg['DepName'] = $m[5];

                    if (!empty($m[9])) {
                        $seg['DepName'] = $m[9] . ', ' . $seg['DepName'];
                    }
                }

                if (preg_match("#" . $this->opt($this->t('Arrive:')) . "\s*(\d+:\d+(?: [ap]\.m\.)?)\s*(?:(\+\s*\d+)\s*" . $this->t('Day') . ")?\s+[\w]+\.*,\s(\w+)\.*\s*(\w+)\.*,\s*(\d{4})\s+([^(]+)\s+\((([A-Z]{3})(\s*-\s*([^)]+))?)\)#u", $flight, $m)) {
                    $seg['ArrCode'] = $m[8];
                    $seg['ArrDate'] = strtotime($this->dateStringToEnglish(str_replace(".", "", $m[4] . ' ' . $m[3] . ' ' . $m[5] . ' ' . $m[1])));
                    //					if (!empty($m[2])) {
                    //						$seg['ArrDate'] = strtotime($m[2].' day', $seg['ArrDate']);
                    //					}
                    $seg['ArrName'] = trim($m[6]);

                    if (!empty($m[10])) {
                        $seg['ArrName'] = $m[10] . ', ' . $seg['ArrName'];
                    }
                }

                if (preg_match("#" . $this->t('Flight Time:') . "\s*(\d[\d\sa-z]*)#", $flight, $m)) {
                    $seg['Duration'] = trim($m[1]);
                }

                if (preg_match("#" . $this->t('Flight:') . "\s*([A-Z\d]{2})(\d{1,5})#", $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                if (empty($seg['AirlineName']) && empty($seg['FlightNumber'])) { //it-5878378
                    if (preg_match("#" . $this->opt($this->t('Arrive:')) . "\s*[^(]+\([A-Z]{3}[^)]*\)\s*([A-Z\d]{2})(\d{1,5})\s#", $flight, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    }
                }

                if (preg_match("#" . $this->t('Operated By') . "\s*(.+)\s*(" . $this->t('Aircraft:') . "|\n)#U", $flight, $m)) {
                    $seg['Operator'] = trim($m[1]);
                }

                if (preg_match("#" . $this->t('Aircraft:') . "\s*(.+)\s*\n#", $flight, $m)) {
                    $seg['Aircraft'] = $m[1];
                }

                if (preg_match("#" . $this->t('Fare Class:') . "\s*([^(\n]+)\s*(\(([A-Z]{1,2})\))?\n#", $flight, $m)) {
                    $seg['Cabin'] = trim($m[1]);

                    if (!empty($m[3])) {
                        $seg['BookingClass'] = $m[3];
                    }
                }

                if (preg_match("#" . $this->t('Meal:') . "\s*(.*)(?:\n|$)#", $flight, $m)) {
                    $seg['Meal'] = $m[1];
                }

                if (preg_match("#" . $this->t('Flight distance:') . "\s*(\d[\d\ a-z,]*)(\n|[A-Z])#", $flight, $m)) {
                    $seg['TraveledMiles'] = trim($m[1]);
                }

                if (isset($seg['DepCode']) && isset($seg['ArrCode']) && isset($this->seats[$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                    $seg['Seats'] = $this->seats[$seg['DepCode'] . '-' . $seg['ArrCode']];
                }
                $trips[] = $seg;
            }
        }

        return $trips;
    }

    private function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function parseEmail()
    {
        $its = [];
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Confirmation Number:') . "')]/following::text()[string-length(normalize-space(.))>3][1]", null, true, "#[A-Z\d]+#");

        $xpathFragment1 = '//tr[starts-with(normalize-space(.),"' . $this->t('Seat Assignments') . '") and not(.//tr)]';

        $passengerTexts = $this->http->FindNodes($xpathFragment1 . '/preceding-sibling::tr[normalize-space(.)][1]/td[1]');
        $passengerValues = array_values(array_filter($passengerTexts));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = $passengerValues;
        }

        $seatTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()');
        $seatValues = array_values(array_filter($seatTexts));

        if (!empty($seatValues[0])) {
            if (preg_match_all('/([A-Z]{3})\s*-\s*([A-Z]{3})\s*:\s*(\d{1,2}[A-Z])/', implode("\n", $seatValues), $seatMatches, PREG_SET_ORDER)) {
                $seats = [];

                foreach ($seatMatches as $seatMatch) {
                    $this->seats[$seatMatch[1] . '-' . $seatMatch[2]][] = $seatMatch[3];
                }
            }
        }

        $xpath = "//text()[{$this->contains($this->t('Depart:'))}]/ancestor::div[{$this->contains($this->t('Arrive:'))}][1]"; // it-5878378.eml, it-7104554.eml
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);
        $it['TripSegments'] = $this->parseSegmentsFormat2($nodes);

        $its[] = $it;

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $phrases) {
            if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $phrases[0] . '") and contains(normalize-space(.),"' . $phrases[1] . '")]')->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($string)
    {
        if (preg_match('/([^\d\s,.]{3,})[.\s]+(\d{1,2})[,\s]+(\d{4})$/', $string, $matches)) { // Sat., Apr. 28, 2012
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\s*([^\d\s,.]{3,})[,.\s]+(\d{4})$/', $string, $matches)) { // Mon., 1 May., 2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }
}
