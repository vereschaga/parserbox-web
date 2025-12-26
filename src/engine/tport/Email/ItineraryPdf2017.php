<?php

namespace AwardWallet\Engine\tport\Email;

//TODO: don't add mta. until it provider is ignoreTraxo. and look at AwardWallet\Engine\mta\Email\ItineraryPdf2017Temp
use AwardWallet\Common\Parser\Util\PriceHelper;

class ItineraryPdf2017 extends \TAccountChecker
{
    public $mailFiles = "tport/it-14376843.eml, tport/it-42663131.eml, tport/it-813996693.eml, tport/it-8889237.eml";
    public static $detect = [ // use in parser tport/It6098511
        "en" => ["Conﬁrma on Number:", "Confirmation Number"],
        "it" => ["Numero di conferma"],
        "pt" => ["Número de confirmação"],
        "fr" => ["Numéro de réservation:"],
    ];
    public $pdfPattern = '(?:ElectronicTicket|.*E\s*ticket).*?\.pdf';
    public $pdfPatternAddition = '.*\.pdf';

    private $lang = '';
    private $code;
    private $arrCode;
    private static $headers = [//TODO: don't set mta - provider before it reset ignoreTraxo
        'flightcentre' => [
            'from' => ['@flightcentre.com.au'],
            'subj' => [
                'en' => 'Etickets',
                'Tickets -',
            ],
        ],
        'tport' => [//tport - last
            'from' => ['travelport.'],
            'subj' => [
                'en' => 'View Your Itinerary: ',
                'it' => 'Visualizza il tuo itinerario: ',
                'pt' => 'Visualizar o seu itinerário: ',
            ],
        ],
    ];
    private static $bodies = [
        'flightcentre' => [
            'en' => ['Flight Centre Midland Gate'],
        ],
        'bcd' => [
            'en' => ['BCD TRAVEL'],
        ],
        'tport' => [//tport - last
            'en' => ['To see the details of your trip', 'IMPORTANT INFORMATION FOR TRAVELERS'],
            'it' => ['Per visualizzare i dettagli del'],
            'pt' => ['Para ver as informações da sua', 'A sua viagem foi reservada'],
            'fr' => ['Renseignements sur l’agence', 'Informa ons sur les tarifs'],
        ],
    ];
    private $pdf2text;

    private static $dict = [
        'en' => [
            //			'IMPORTANT INFORMATION FOR TRAVELERS' => "",
            //			'Passenger Name:' => "",
            //			'e‐Ticket Number:' => "",
            //			'Ticket Issue Date:' => "",
            //			'Flight' => "",
            //			'Confirmation Number:' => "",
            //			'Depart:' => "",
            //			'Arrive:' => "",
            //			'Class Of Service:' => "",
            //			'Fare Information' => "",
            //			'Form Of Payment:' => "",
            //			'Fare:' => "",
            //			'Taxes and Carrier‐imposed fees:' => "",
            //			'Total:' => "",
        ],
        'it' => [
            'IMPORTANT INFORMATION FOR TRAVELERS' => "INFORMAZIONI IMPORTANTI PER I VIAGGIATORI",
            'Passenger Name:'                     => 'Nome del passeggero:',
            'e‐Ticket Number:'                    => "Numero del biglietto elettronico:",
            'Ticket Issue Date:'                  => "Data di emissione del biglietto:",
            'Flight'                              => "Volo",
            'Confirmation Number:'                => "Numero di conferma:",
            'Depart:'                             => "Partenza:",
            'Arrive:'                             => "Arrivo:",
            'Class Of Service:'                   => "Classe di servizio:",
            'Fare Information'                    => "Informazioni sulla tariffa",
            'Form Of Payment:'                    => "Modalità di pagamento:",
            'Fare:'                               => "Tariffa:",
            'Taxes and Carrier‐imposed fees:'     => "Tasse e spese imposte dal vettore:",
            'Total:'                              => "Riquotazione:",
        ],
        'pt' => [
            'IMPORTANT INFORMATION FOR TRAVELERS' => "INFORMAÇÃO IMPORTANTE PARA VIAJANTES",
            'Passenger Name:'                     => 'Nome do passageiro:',
            'e‐Ticket Number:'                    => "Número de bilhete eletrónico:",
            'Ticket Issue Date:'                  => "Data de emissão do bilhete:",
            'Flight'                              => "Voo",
            'Confirmation Number:'                => "Número de confirmação:",
            'Depart:'                             => "Partida:",
            'Arrive:'                             => "Chegada:",
            'Class Of Service:'                   => "Classe de serviço:",
            //			'Fare Information' => "",
            //			'Form Of Payment:' => "",
            //			'Fare:' => "",
            //			'Taxes and Carrier‐imposed fees:' => "",
            //			'Total:' => "",
        ],
        'fr' => [
            'IMPORTANT INFORMATION FOR TRAVELERS' => "INFORMATIONS IMPORTANTES POUR LES VOYAGEURS",
            'Passenger Name:'                     => "Nom du passager:",
            'e‐Ticket Number:'                    => "Numéro de billet électronique:",
            'Ticket Issue Date:'                  => "Date d’émission du billet:",
            'Flight'                              => "Vol",
            'Confirmation Number:'                => "Confirmé",
            'Depart:'                             => "Départ:",
            'Arrive:'                             => "Arrivée:",
            'Class Of Service:'                   => "Classe de service:",
            //'Fare Information'                    => "",
            'Form Of Payment:'                    => "Mode de paiement:",
            //'Fare:'                               => "",
            'Taxes and Carrier‐imposed fees:'     => "Taxes et frais imposés par les transporteurs:",
            'Total:'                              => "Total:",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            if ($this->arrikey($headers['from'], $arr['from']) !== false) {
                $byFrom = true;
                $this->code = $code;
            }

            if ($this->arrikey($headers['subject'], $arr['subj']) !== false) {
                $bySubj = true;
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPatternAddition);
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ((null === ($code = $this->getProvByText($textPdf))) && (null === ($code = $this->getProvByText($parser->getHTMLBody())))) {
                $from = implode("", $parser->getFrom());

                foreach (self::$headers as $code => $arr) {
                    if ($this->arrikey($from, $arr['from']) !== false) {
                        $this->code = $code;

                        break;
                    }
                }

                if (empty($this->code)) {
                    return false;
                }
            }

            foreach (self::$detect as $lang => $detect) {
                foreach ($detect as $d) {
                    if (strpos($textPdf, $d) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!empty($pdf = $parser->searchAttachmentByName('(?:.+Itinerary.*?|Itinerary)\.pdf'))) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (mb_strlen($text) > 500) {
                $this->logger->debug('go to parse by MyTripPdf. because there more fields are parse');

                return false;
            }
        }

        $itineraries = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPatternAddition);
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToHtml($parser->getAttachmentBody($pdf));

            foreach (self::$detect as $lang => $detect) {
                foreach ($detect as $d) {
                    if (strpos($text, $d) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
            $text = $this->htmlToText($text);
            $this->pdf2text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->lang === 'en' && stripos($text, 'Conﬁrma on Number')) {
                $text = str_replace('Conﬁrma on Number', 'Confirmation Number', $text);
            }

            $its = [];

            if (!empty($text) && $this->lang) {
                $its[] = $this->parseEmail($text);
                $its = $this->groupBySegments($its);
            }
            $itineraries = array_merge($itineraries, $its);
        }

        $result = [
            'emailType'  => 'ItineraryPdf2017' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $itineraries],
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        } elseif (isset($text) && null !== ($code = $this->getProvByText($text))) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_unique(array_merge(array_keys(self::$headers), array_keys(self::$bodies)));
    }

    protected function getProvByText($text)
    {
        foreach (self::$bodies as $code=>$body) {
            switch ($code) {
                case 'tport':
                    if ($this->arrikey($text, $body) !== false) {//stripos($text, 'travelport') !== false &&    --set default
                        $this->code = $code;

                        return $code;
                    }

                    break;

                case 'flightcentre':
                    if (stripos($text, 'flightcentre') !== false && $this->arrikey($text, $body) !== false) {
                        $this->code = $code;

                        return $code;
                    }

                    break;

                case 'bcd':
                    if ($this->arrikey($text, $body) !== false) {
                        $this->code = $code;

                        return $code;
                    }

                    break;

                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * TODO: Beta!
     *
     * @version v1.2
     *
     * @param type $reservations
     *
     * @return array
     */
    protected function groupBySegments($reservations)
    {
        $newReservations = [];

        foreach ($reservations as $reservation) {
            $newSegments = [];

            foreach ($reservation['TripSegments'] as $segment) {
                if (empty($segment['RecordLocator']) && isset($reservation['TripNumber'])) {
                    // when there is no locator in the segment
                    $newSegments[$reservation['TripNumber']][] = $segment;
                } elseif (isset($segment['RecordLocator'])) {
                    $r = $segment['RecordLocator'];
                    unset($segment['RecordLocator']);
                    $newSegments[$r][] = $segment;
                }
            }

            foreach ($newSegments as $key => $segment) {
                $reservation['RecordLocator'] = $key;
                $reservation['TripSegments'] = $segment;

                if (!empty($reservation['Passengers'])) {
                    $reservation['Passengers'] = preg_replace("/\s(?:MS|MRS|MR)/", "", array_unique(array_filter($reservation['Passengers'])));
                }
                $newReservations[] = $reservation;
            }
        }

        return $newReservations;
    }

    protected function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'tport') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach (self::$bodies as $code=>$body) {
            switch ($code) {
                case 'tport':
                    if (stripos($parser->getHTMLBody(), 'travelport') !== false) {
                        return $code;
                    }

                    break;

                case 'flightcentre':
                    if (stripos($parser->getHTMLBody(), 'flightcentre') !== false) {
                        return $code;
                    }

                    break;

                default:
                    return null;
            }
        }

        return null;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * TODO: In php problems with "Type declarations", so i did so.
     * Are case sensitive. Example:
     * <pre>
     * var $reBody = ['en' => ['Reservation Modify'],];
     * var $reSubject = ['Reservation Modify']
     * </pre>.
     *
     * @param string $haystack
     *
     * @return int, string, false
     */
    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::parse($m['t'], $m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function parseEmail($text)
    {
        $patterns = [
            'time' => '\d{1,2}[:：.]\d{2}(?:[ ]*[AaPp]\.?[Mm]\.?)?',
        ];

        $result = ['Kind' => 'T'];

        $text = preg_replace("#\/tmp\/pdftohtml[\w_\.\-]+\.html#", '', $text);

        $payment = strstr($this->pdf2text, $this->t('Fare Information'));

        if (empty($payment)) {
            $payment = strstr($this->pdf2text, $this->t('Form Of Payment:'));
        }

        if (!empty($payment)) {
            if (preg_match("/\n[ ]*{$this->t('Fare:')}[ ]*(.+)/", $payment, $m)) {
                $sum = $this->getTotalCurrency($m[1]);
                $result['BaseFare'] = $sum['Total'];
                $result['Currency'] = $sum['Currency'];
            }

            if (preg_match("/\n[ ]*{$this->t('Taxes and Carrier‐imposed fees:')}(.+?)\n[ ]*{$this->t('Total:')}/su",
                $payment, $m)) {
                if (preg_match_all("/([A-Z]{3}) ([\d\.\,]+?) (\w+)$/m", $m[1], $v, PREG_SET_ORDER)) {
                    foreach ($v as $m) {
                        $result['Fees'][] = ["Name" => $m[3], "Charge" => PriceHelper::cost($m[2])];
                    }
                }
            }

            if (preg_match("/\n[ ]*{$this->t('Total:')}[ ]*(.+)/", $payment, $m)) {
                $sum = $this->getTotalCurrency($m[1]);
                $result['TotalCharge'] = $sum['Total'];
                $result['Currency'] = $sum['Currency'];
            }
        }

        foreach ($this->splitter('/(' . $this->t('Passenger Name:') . ')/', $text) as $traveler) {
            if (preg_match('/\n\s*([\w\s,.]+)(?:\n|[ ]{2,})\s*' . $this->t('e‐Ticket Number:') . '\s*(\d+[\- \d‐]+)\n/', $traveler, $matches)) {
                $result['Passengers'][] = preg_replace(['/.*MTA TRAVEL.*/s', '/\s+/'], ['', ' '], trim($matches[1]));
                $result['TicketNumbers'][] = str_replace(["‐", " "], ['-', ''], $matches[2]);
            } elseif (preg_match('/\n\s*([\w\s,.]+)(?:\n|[ ]{2,})\s*(\d+[\- \d‐]+)\n/', $traveler, $matches)) {
                $result['Passengers'][] = preg_replace(['/.*MTA TRAVEL.*/s', '/\s+/'], ['', ' '], trim($matches[1]));
                $result['TicketNumbers'][] = str_replace(["‐", " "], ['-', ''], $matches[2]);
            }

            if (preg_match("/Rewards Program:\s+([A-Z\d]{7,})/", $traveler, $m)) {
                $result['AccountNumbers'][] = $m[1];
            }
        }

        if (isset($result['TicketNumbers'])) {
            $result['TicketNumbers'] = array_filter($result['TicketNumbers']);
        }

        foreach ($this->splitter('/(' . $this->t('Flight') . ' [‐-].+?[‐-])/', $text) as $sText) {
            $sText = str_replace("État:\n", "", $sText);
            $sText = str_replace("Tarifs de base:\n", "", $sText);

            $i = [];

            $i += $this->matchSubpattern("/{$this->t('Flight')}\s*[‐-]\s*.*?\((?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\) [‐-] (?<FlightNumber>\d+)\s*\W?\s+(?<Date>\w.+?)(\s+[‐-]\s+.*)?{$this->t('Confirmation Number:')}\s*(?<RecordLocator>[A-Z\d]{5,7})\b/su", $sText);
            $i += $this->matchSubpattern("/{$this->t('Depart:')}(?<DepName>.+?)\((?<DepCode>[A-Z]{3})\)(?<DepName2>.*?)(?<DepDate>{$patterns['time']})/is", $sText);
            $arriveFields = $this->matchSubpattern("/{$this->t('Arrive:')}(?<ArrName>.+?)\((?<ArrCode>[A-Z]{3})\)(?<ArrName2>.*?)(?<ArrDate>{$patterns['time']})(?<newDate>\b[^\n]*)?/is", $sText);

            if (count($arriveFields) === 0) {
                // if ArrDate is empty
                $arriveFields = $this->matchSubpattern("/{$this->t('Arrive:')}(?<ArrName>.+?)\((?<ArrCode>[A-Z]{3})\)(?<ArrName2>.*?)\s+{$this->t('Class Of Service:')}/is", $sText);
                $i['ArrDate'] = MISSING_DATE;
            }
            $i += $arriveFields;

            unset($cabin);

            if (preg_match("#" . $this->t('Depart:') . "\s+" . $this->t('Arrive:') . "#", $sText)) {
                $i = array_merge($i, $this->matchSubpattern("/{$this->t('Depart:')}\s+{$this->t('Arrive:')}\s+{$this->t('Class Of Service:')}\s+(?<DepName>.+?)\((?<DepCode>[A-Z]{3})\)(?<DepName2>.*?)(?<DepDate>{$patterns['time']})\b/is", $sText));
                $i = array_merge($i, $this->matchSubpattern("/{$this->t('Arrive:')}.*?\d+[:.]\d+[^\n]*\s*\n\s+(?<ArrName>.+?)\((?<ArrCode>[A-Z]{3})\)(?<ArrName2>.*?)(?<ArrDate>{$patterns['time']})(?<newDate>\b[^\n]*)?/is", $sText));
                $cabin = $this->match('/' . $this->t('Class Of Service:') . '.*?\d+[:.]\d+[^\n]*.*?\d+[:.]\d+[^\n]*\s\n\s+(\w+)/sui', $sText);
            } else {
                $cabin = trim($this->match('/' . $this->t('Class Of Service:') . '\n{1,2}(.+)/ui', $sText));

                if (!empty($cabin) && strpos('  ', $cabin) !== false) {
                    unset($cabin);
                }
            }

            if (!empty($i['Date']) && !empty($i['DepDate']) && $i['DepDate'] !== MISSING_DATE) {
                $i['DepDate'] = strtotime($this->normalizeDate($i['Date'] . ' ' . $i['DepDate']));
            }

            if (!empty($i['Date']) && !empty($i['ArrDate']) && $i['ArrDate'] !== MISSING_DATE) {
                if (!empty($newDate = trim(preg_replace("#[,.]#", "", $i['newDate']))) && 4 < strlen($newDate)) {
                    $i['newDate'] = trim(preg_replace("#[,.]#", "", $i['newDate']));
                    $i['ArrDate'] = strtotime($this->normalizeDate($i['newDate'] . ' ' . $i['ArrDate']));
                } elseif (!empty($i['ArrDate']) && ($aDate = strtotime($this->normalizeDate($i['Date'] . ' ' . $i['ArrDate'])))) {
                    $i['ArrDate'] = $aDate;
                } else {
                    $i['ArrDate'] = MISSING_DATE;
                }
            }
            unset($i['Date']);
            unset($i['newDate']);

            if (preg_match("#Terminal\s*(.+?)\s*(?:\n|$|{$this->t('Arrive:')})#u", $i['DepName2'], $m)) {
                $i['DepartureTerminal'] = $m[1];
            }
            unset($i['DepName2']);

            if (preg_match("#Terminal\s*(.+?)(?:\s*{$this->t('Class Of Service:')}|\n|$)#u", $i['ArrName2'], $m)) {
                $i['ArrivalTerminal'] = $m[1];
            }
            unset($i['ArrName2']);

            if (!empty($cabin)) {
                $i['Cabin'] = $cabin;
                unset($cabin);
            } else {
                $i['Cabin'] = $this->match('/' . $this->t('Class Of Service:') . '\s*(\w+)/u', $sText);
            }

            if ($i['DepCode'] !== $i['ArrCode']) {
                $this->arrCode = $i['ArrCode'];
            } else {
                if (!empty($this->arrCode) && $i['DepCode'] !== $this->arrCode) {
                    $i['DepCode'] = $this->arrCode;
                }
            }

            $result['TripSegments'][] = $i;
        }

        if (isset($result['TripSegments'])) {
            $result['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $result['TripSegments'])));
        } else {
            $result['TripSegments'] = [];
        }

        return $result;
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     */
    private function matchSubpattern($pattern, $text): array
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
    }

    private function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }

        return $allMatches ? [] : null;
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
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
    private function strCut($input, $searchStart, $searchFinish = null)
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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+)$#",
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
            'needCheck' => "#^(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+)\.(\d+)$#",
            '/(\w+)\s+(\d{1,2}),\s+(\d{2,4})\s+.+\s+(\d+:\d+\s+[ap]m)/i',
        ];
        $out = [
            "$1 $2 $3 $4",
            "$1 $2 $3",
            "$1 $2 $3 $4:$5",
            '$2 $1 $3, $4',
        ];

        foreach ($in as $key => $re) {
            if ('needCheck' === $key && preg_match($re, $str, $m) && (24 < $m[4] || 60 < $m[5])) {
                $this->logger->debug("date format is incorrect: {$str}");

                return false;
            }
        }
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], "hu")) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
