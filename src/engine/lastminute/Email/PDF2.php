<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\lastminute\Email;

class PDF2 extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "lastminute/it-5026588.eml";

    /** @var \HttpBrowser */
    protected $pdf;
    protected $lang = '';

    protected $test = [];

    protected static $detectBody = [
        'it' => 'CODICE DI PRENOTAZIONE',
        'es' => ['CÓDIGO DE RESERVACIÓN', 'Nombre del pasajero:'],
    ];
    private $reDuration = '/(?<h>\d+)(?<H>\D+?)\s?:\s?(?<m>\d+)(?<M>\D+)/';
    private $year = '';
    private $dict = [
        'it' => [
            'Partenza' => ['Partenza', 'Partenza alle'],
            'Arrivo'   => ['Arrivo', 'Arrivo alle'],
        ],
        'es' => [
            'CODICE DI PRENOTAZIONE' => 'CÓDIGO DE RESERVACIÓN',
            'Partenza'               => 'Sale a la',
            'Arrivo'                 => 'Llega a la',
            'ARRIVO'                 => 'ARRIBO',
            'PARTENZA'               => 'PARTIDA',
            'ORGANIZZATO'            => 'PREPARADO',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBodyAndAcceptLang($parser);
        $its = (isset($this->pdf)) ? $this->parseEmail() : [];
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
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
        $this->year = $this->pdf->FindSingleNode('//div[@id = "page1-div"]/p[1]', null, true, '/\d+ \D+ (\d{4}) \d+ \D+ \d+/');

        if (empty($this->year)) {
            $year = $this->pdf->FindNodes('//div[@id = "page1-div"]/p[contains(., "DESTINO") or contains(., "VIAGGIO")]/preceding-sibling::p[position() = 1 or position() = 2]', null, '/\d{2} \D+ (\d{2,4})/');
            $this->year = (is_array($year)) ? array_shift($year) : $year;
        }

        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(., '" . $this->t('CODICE DI PRENOTAZIONE') . "')]/following-sibling::p[1]");
        $it['Passengers'] = $this->pdf->FindNodes("//p[contains(., '" . $this->t('ORGANIZZATO') . "')]/following-sibling::p[1]/descendant::text()");

        $it['AccountNumbers'][] = $this->pdf->FindSingleNode("//p[contains(normalize-space(.), 'Frequent Flyer')]/following-sibling::p[4]", null, true, '/([\d\s]+)\s+/');

        $it['TicketNumbers'][] = $this->pdf->FindSingleNode("//p[contains(normalize-space(.), 'biglietto')]/following-sibling::p[4]");

        $xpath = "//div[contains(@id, 'page') and contains(@id, 'div')]/p[contains(., '" . $this->t('PARTENZA') . "')]";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }

        foreach ($roots as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $depTime = $this->getNode('', $root, $this->t('Partenza'));
            $arrTime = $this->getNode('', $root, $this->t('Arrivo'));

            if (empty($depTime) || empty($arrTime)) {
                $depTime = $this->getNode(12, $root, $this->t('Partenza'));
                $arrTime = $this->getNode(15, $root, $this->t('Arrivo'));
            }

            $date = $this->getNode(1, $root); // departure date and arrival date (because arraival date doesn't exist)
            $depDate = $this->getDate($date, $depTime);
            $arrDate = $this->getDate($date, $arrTime);

            if (preg_match('/(\D{2}) (\d+)/', $this->getNode(3, $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match($this->reDuration, $this->getNode(4, $root), $m)) {
                $seg['Duration'] = $m['h'] . ' ' . $m['H'] . ' ' . $m['m'] . ' ' . $m['M'];
            }
            $seg['DepCode'] = $this->getNode(5, $root);
            $seg['DepName'] = $this->getNode(6, $root);
            $seg['ArrCode'] = $this->getNode(7, $root);
            $seg['ArrName'] = $this->getNode(8, $root);

            if (preg_match('/:(.+)/', $this->getNode(9, $root), $m)) {
                $seg['Aircraft'] = $m[1];
            }
            $seg['TraveledMiles'] = $this->getNode(10, $root);

            if (preg_match('/:terminal\s?([a-z\d]{1,2})/i', $this->getNode(14, $root), $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }

            if (preg_match('/:terminal\s?([a-z\d]{1,2})/i', $this->getNode(14, $root), $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            }

            if (($arrDt = $this->getNode(2, $root, $this->t('ARRIVO'))) !== null) { // if isset arrival date
                if (preg_match('/(\D{2}) (\d+)/', $this->getNode(5, $root), $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                if (preg_match($this->reDuration, $this->getNode(6, $root), $m)) {
                    $seg['Duration'] = $m['h'] . ' ' . $m['H'] . ' ' . $m['m'] . ' ' . $m['M'];
                }
                $seg['DepCode'] = $this->getNode(7, $root);
                $seg['DepName'] = $this->getNode(8, $root);
                $seg['ArrCode'] = $this->getNode(9, $root);
                $seg['ArrName'] = $this->getNode(10, $root);

                if (preg_match('/:(.+)/', $this->getNode(11, $root), $m)) {
                    $seg['Aircraft'] = $m[1];
                }
                $seg['TraveledMiles'] = $this->getNode(12, $root);

                if (preg_match('/:terminal\s?([a-z\d]{1,2})/i', $this->getNode(17, $root), $m)) {
                    $seg['DepartureTerminal'] = $m[1];
                }

                if (preg_match('/:terminal\s?([a-z\d]{1,2})/i', $this->getNode(21, $root), $m)) {
                    $seg['ArrivalTerminal'] = $m[1];
                }
                $depTime = $this->getNode(14, $root, $this->t('Partenza'));
                $arrTime = $this->getNode(18, $root, $this->t('Arrivo'));
                $depDate = $this->getDate($date, $depTime);
                $arrDate = $this->getDate($arrDt, $arrTime);
            } elseif (3 !== strlen($seg['DepCode'])) {
                $seg['DepCode'] = $this->getNode(7, $root);

                $seg['DepName'] = $this->getNode(8, $root);

                $seg['ArrCode'] = $this->getNode(9, $root);

                $seg['ArrName'] = $this->getNode(10, $root);

                if (preg_match('/:terminal\s?([a-z\d]{1,4})/i', $this->getNode(16, $root), $m)) {
                    $seg['DepartureTerminal'] = $m[1];
                }

                if (preg_match('/:terminal\s?([a-z\d]{1,4})/i', $this->getNode(19, $root), $m)) {
                    $seg['ArrivalTerminal'] = $m[1];
                }

                if (preg_match($this->reDuration, $this->getNode(4, $root), $m)) {
                    $seg['Duration'] = $m['h'] . ' ' . $m['H'] . ' ' . $m['m'] . ' ' . $m['M'];
                }

                if (preg_match('/Classe\s*:\s*(.+)\s*\/\s*([A-Z])/', $this->getNode(5, $root), $m)) {
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                }

                if (preg_match('/Stato\s*:\s*(.+)/', $this->getNode(6, $root), $m)) {
                    $it['Status'] = str_replace(' ', '', $m[1]);
                }
            }

            $seg['DepDate'] = $depDate;
            $seg['ArrDate'] = $arrDate;
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    protected function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    protected function detectBodyAndAcceptLang(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('((?:Prenotazione|Reserva|Reisspecificatie).+\.pdf)');

        if (0 < count($pdf)) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdf)), \PDF::MODE_COMPLEX)) !== null) {
                $this->pdf = clone $this->http;
                $html = str_replace('&#160;', ' ', $html);
                $this->pdf->SetBody($html);
            }
            $body = $this->pdf->Response['body'];

            foreach (self::$detectBody as $lang => $item) {
                if (is_array($item)) {
                    foreach ($item as $detect) {
                        if (stripos($body, $detect) !== false) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                }

                if (is_string($item) && stripos($body, $item) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getDate($date, $time)
    {
        if (preg_match('/.*?(\d{1,2}) (\D+)/', $date, $m)) {
            $month = $this->monthNameToEnglish($m[2], ['it', 'es', 'es1', 'es2']);
            $depDate = $m[1] . ' ' . $month . ' ' . $this->year . ' ' . $time;
        }

        return (!empty($depDate)) ? strtotime($depDate) : null;
    }

    private function getNode($pos = '', \DOMElement $root, $contains = null)
    {
        $res = '';

        if (!empty($pos) && $contains === null) {
            $res = $this->pdf->FindSingleNode('following-sibling::p[normalize-space() != ""][' . $pos . '][string-length()>2]', $root);
        } elseif (!empty($pos) && !empty($contains)) {
            if (!is_array($contains)) {
                $contains = [$contains];
            }
            $contains = implode(' or ', array_map(function ($el) { return "contains(normalize-space(.), '{$el}')"; }, $contains));
            $res = $this->pdf->FindSingleNode('following-sibling::p[normalize-space() != ""][(' . $contains . ') and position() = ' . $pos . '][string-length()>3]/following-sibling::p[1]', $root);
        } else {
            if (!is_array($contains)) {
                $contains = [$contains];
            }
            $contains = implode(' or ', array_map(function ($el) { return "contains(normalize-space(.), '{$el}')"; }, $contains));
            $res = $this->pdf->FindSingleNode('following-sibling::p[normalize-space() != ""][' . $contains . '][string-length()>3]/following-sibling::p[1]', $root);
        }

        return (!empty($res)) ? $res : null;
    }
}
