<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\lastminute\Email;

/** not finished. This parser has heirs (ItineraryPDF) */
class PDF3 extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-1604222.eml, lastminute/it-5037063.eml";
    protected $lang = '';
    protected $dict = [
        'es' => [],
        //        'de' => [
        //            'PREPARADO PARA' => 'ERSTELLT FÜR',
        //            'AIRLINE RESERVATION CODE' => 'BUCHUNGSCODE DER FLUGGESELLSCHAFT',
        //            'CÓDIGO DE RESERVACIÓN' => 'RESERVIERUNGSCODE',
        //            'OTROS:' => 'SONSTIGE:',
        //            'PARTIDA:' => 'ABREISE:',
        //            'ARRIBO:' => 'ANKUNFT:',
        //            'Operado por:' => 'Betreiber-Fluggesellschaft:',
        //            'Duración:' => 'Dauer:',
        //        ],
    ];
    protected $pdfText;
    protected $it = [];
    protected $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    ];
    protected static $detectBody = [
        'es' => ['CÓDIGO DE RESERVACIÓN', 'Lastminute'],
        //        'de' => ['BUCHUNGSCODE DER FLUGGESELLSCHAFT', 'Lastminute'],
    ];
    private $year;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $this->detectBodyAndAcceptLang($parser);
        $its = (isset($this->pdfText)) ? $this->parseEmail() : [];

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBodyAndAcceptLang($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lastminute.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lastminute.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    protected function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $pdf = $this->pdfText;
        $passengersAndLocator = $this->cutText($pdf, $this->t('PREPARADO PARA'), $this->t('AIRLINE RESERVATION CODE'));

        if (preg_match_all('/(.+ (?:MR|MRS))/i', $passengersAndLocator, $m)) {
            $it['Passengers'] = $m[0];
        }

        if (preg_match('/' . $this->t('CÓDIGO DE RESERVACIÓN') . '\s*(\w+)/i', $passengersAndLocator, $m)) {
            $it['RecordLocator'] = $m[1];
        }
        $segmentsText = $this->cutText($pdf, $this->t('AIRLINE RESERVATION CODE'), $this->t('OTROS:'));
        $segments = explode($this->t('PARTIDA:'), $segmentsText);
        array_shift($segments); //because it's not valid segment

        $countSegments = count($segments);

        for ($i = 0; $i < $countSegments; $i++) {
            $this->getTripSegments($segments[$i]);
            $it['TripSegments'][] = $this->it;
        }

        return [$it];
    }

    /**
     * example: JUEVES 19 DIC Por favor verifique el horario de vuelo antes de la salida
     * IBERIA                                       BRU                          HEL                                  Avión:
     * AIRBUS INDUSTRIE A319
     * IB 7428                                      BRUSSELS, BELGIUM            HELSINKI VANTAA, FINLAND
     * JET
     * Operado por:                                 Sale a la(s):                   Llega a la(s):                    Millaje: 1023
     * FINNAIR                                      7:15pm                          10:50pm                           Escala(s): 0
     * Duración:                                    Terminal:                       Terminal:
     * 02horas :35minutos                           No disponible                   TERMINAL 2
     * Nombre del pasajero:         Asientos:        Clase:          Estado:         Recibo(s) de boleto(s) electrónico(s):            Comidas:
     * » VIAL/VICENTE MR            Sin asignar      Ecónomica       Confirmado      0754628959556/57.
     *
     * @param $textSegment
     *
     * @return array
     */
    protected function getTripSegments($textSegment)
    {
        $re = '/';
        $re .= '\w+ (?<Day>\d{2}) (?<Month>\w+)\s*(?:' . $this->t('ARRIBO:') . ' \w+ (?<ArrDay>\d{2}) (?<ArrMonth>\w+)|)[\s\D]*\b(?<DepCode>[A-Z]{3})\b\s+\b(?<ArrCode>[A-Z]{3})\b[\s\w\D]+';
        $re .= '\b(?<AirlineName>[A-Z]{2})\b (?<FlightNumber>\d{3,5})[\s\w\D]+';

        if (stripos($textSegment, $this->t('Operado por')) !== false) {
            $re .= $this->t('Operado por:') . '[\s\w\D]+(?<DepTime>\d{1,2}:\d{1,2}(?:pm|am))\s+(?<ArrTime>\d{1,2}:\d{1,2}(?:pm|am))[\s\D\w]+' . $this->t('Duración:') . '[\s\D\w]+(?<Duration>\d{2}\w+\s?:\d{2}\w+)';
        } else {
            $re .= $this->t('Duración:') . '.+\s+(?<Duration>\d{2}\w+\s?:\d{2}\w+)\s+(?<DepTime>\d{1,2}:\d{1,2}(?:am|pm))\s+(?<ArrTime>\d{1,2}:\d{1,2}(?:am|pm))';
        }
        $re .= '/';

        if (preg_match($re, $textSegment, $m)) {
            return $this->it = [
                'DepCode'      => $m['DepCode'],
                'ArrCode'      => $m['ArrCode'],
                'AirlineName'  => $m['AirlineName'],
                'FlightNumber' => $m['FlightNumber'],
                'Duration'     => (stripos($m['Duration'], ':')) ? str_replace(':', '', $m['Duration']) : $m['Duration'],
                'DepDate'      => strtotime($m['Day'] . ' ' . $this->monthNameToEnglish($m['Month'], 'es') . ' ' . $this->year . ' ' . $m['DepTime']),
                'ArrDate'      => (empty($m['ArrDay']) || empty($m['ArrMonth'])) ? strtotime($m['Day'] . ' ' . $this->monthNameToEnglish($m['Month'], 'es') . ' ' . $this->year . ' ' . $m['ArrTime']) :
                    strtotime($m['ArrDay'] . ' ' . $this->monthNameToEnglish($m['ArrMonth'], 'es') . ' ' . $this->year . ' ' . $m['ArrTime']),
            ];
        } else {
            return $this->it = [null];
        }
    }

    protected function cutText($text, $startMarker, $endMarker)
    {
        if (!empty($text) && !empty($startMarker) && !empty($endMarker)) {
            $str = stristr(stristr($text, $startMarker), $endMarker, true);

            return substr($str, strlen($startMarker));
        }

        return false;
    }

    protected function getPDFName()
    {
        return '(?:Reserva|Reisereservierung).*\.pdf';
    }

    protected function detectBodyAndAcceptLang(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->getPDFName());

        if (!empty($pdf)) {
            $this->pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (static::$detectBody as $lang => $detect) {
                if (is_array($detect) && count($detect) === 2) {
                    if (stripos($this->pdfText, $detect[0]) !== false && stripos($this->pdfText, $detect[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }

                if (is_string($detect) && stripos($this->pdfText, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function monthNameToEnglish($monthNameText, ...$lang)
    {
        $res = false;
        $monthNameOriginal = mb_strtolower($monthNameText, 'UTF-8');
        $list = $lang ? $lang : array_keys($this->monthNames);

        foreach ($list as $ln) {
            if (isset($this->monthNames[$ln])) {
                $i = 0;

                foreach ($this->monthNames[$ln] as $monthName) {
                    if (stripos($monthName, $monthNameOriginal) !== false) {
                        $res = $this->monthNames['en'][$i];
                    }
                    $i++;
                }
            }
        }

        return $res;
    }
}
