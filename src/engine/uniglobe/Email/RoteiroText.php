<?php

namespace AwardWallet\Engine\uniglobe\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class RoteiroText extends \TAccountChecker
{
    public $mailFiles = "uniglobe/it-10632854.eml, uniglobe/it-10758522.eml, uniglobe/it-4891058.eml, uniglobe/it-4891063.eml, uniglobe/it-48994590.eml, uniglobe/it-4961945.eml, uniglobe/it-4965614.eml, uniglobe/it-4965615.eml, uniglobe/it-4971481.eml, uniglobe/it-788023525.eml";

    public $lang = "pt";
    public static $dictionary = [
        "pt" => [
            'A Solicitação número' => ['O Pedido número', 'A Solicitação número', 'Confirmado para'],
            'PASSAGEM AÉREA'       => 'PASSAGEM AÉREA',
            'HOSPEDAGEM'           => 'HOSPEDAGEM',
            'LOCAÇÃO DE VEÍCULO'   => 'LOCAÇÃO DE VEÍCULO',
            // 'Descrição' => '',
            // 'Valores' => '',
            // 'Reserva' => '',
            // 'Total' => '',
            // 'Observação' => '',
            // 'Pagamento' => '',
            // 'SOLICITANTE' => '',

            // Flight
            // 'Partida' => '',
            // 'Chegada' => '',
            // 'Duração' => '',
            // 'Voo operado pela cia' => '',
            'Vôo' => ['Vôo', 'Nº Voo'],
            // 'Tarifa' => '',
            // 'Taxas' => '',
            // 'Localizador' => '',
            // 'Bilhete' => '',
            // 'Assento(s)' => '',

            // Hotel
            // 'Fone' => '',
            // 'Período' => '',
            // 'Limite Cancelamento' => '',

            // Rental
            // 'Retirada' => '',
            // 'Devolução' => '',
        ],
        "es" => [
            'A Solicitação número' => ['El pedido de numero', 'El Pedido número'],
            'PASSAGEM AÉREA'       => 'PASAJE AÉREO',
            // 'HOSPEDAGEM' => 'HOSPEDAGEM',
            // 'LOCAÇÃO DE VEÍCULO' => 'LOCAÇÃO DE VEÍCULO',
            'Descrição'   => 'Descripción',
            'Valores'     => 'Valores',
            'Reserva'     => 'Reserva',
            'Total'       => 'Total',
            'Observação'  => 'Observación',
            'Pagamento'   => 'Pago',
            'SOLICITANTE' => 'SOLICITANTE',

            // Flight
            'Partida'     => 'Salida',
            'Chegada'     => 'Llegada',
            'Duração'     => 'Duración',
            // 'Voo operado pela cia' => '',
            'Vôo'         => ['Vuelo', 'Nº Vuelo'],
            'Tarifa'      => 'Tarifa',
            'Taxas'       => 'Tasas',
            'Localizador' => 'Localizador',
            'Bilhete'     => 'Ticket',
            // 'Assento(s)' => '',

            // Hotel
            // 'Fone' => '',
            // 'Período' => '',
            // 'Limite Cancelamento' => '',

            // Rental
            // 'Retirada' => '',
            // 'Devolução' => '',
        ],
        "en" => [
            'A Solicitação número' => ['Request number'],
            'PASSAGEM AÉREA'       => 'AIR TICKET',
            'HOSPEDAGEM'           => 'HOTELS',
            // 'LOCAÇÃO DE VEÍCULO' => 'LOCAÇÃO DE VEÍCULO',
            'Descrição' => 'Description',
            'Valores'   => 'Prices',
            'Reserva'   => 'Reservation',
            'Total'     => 'Total',
            // 'Observação' => 'Observación',
            'Pagamento'   => 'Payment',
            'SOLICITANTE' => 'APPLICANT',

            // Flight
            'Partida'     => 'Departure',
            'Chegada'     => 'Arrival',
            'Duração'     => 'Duration',
            // 'Voo operado pela cia' => '',
            'Vôo'         => ['Flight Number'],
            'Tarifa'      => 'Fare',
            'Taxas'       => 'Taxes',
            'Localizador' => 'Locator',
            'Bilhete'     => 'Ticket',
            // 'Assento(s)' => '',

            // Hotel
            'Fone'                => 'Phone',
            'Período'             => 'Period',
            'Limite Cancelamento' => 'Cancellation threshold',

            // Rental
            // 'Retirada' => '',
            // 'Devolução' => '',
        ],
    ];

    protected $mailDate;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }
        $emailSubjects = [
            // pt
            ['Solicitação', 'Emitida'],
            ['Solicitação', 'Cancelamento'],
            ['Solicitação', 'Emissão'],
            // es
            ['Solicitud', 'Emitido'],
            // en
            ['Request', 'approval'],
        ];

        foreach ($emailSubjects as $sub) {
            if (stripos($headers['subject'], $sub[0]) !== false
                && stripos($headers['subject'], $sub[1]) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (!$body) {
            $body = text($parser->getHTMLBody());
        }

        foreach (self::$dictionary as $dict) {
            if (empty($dict['A Solicitação número']) || $this->containsText($body, $dict['A Solicitação número']) !== true) {
                continue;
            }

            if (
                (!empty($dict['PASSAGEM AÉREA']) && $this->containsText($body, $dict['PASSAGEM AÉREA']) === true)
                || (!empty($dict['HOSPEDAGEM']) && $this->containsText($body, $dict['HOSPEDAGEM']) === true)
                || (!empty($dict['LOCAÇÃO DE VEÍCULO']) && $this->containsText($body, $dict['LOCAÇÃO DE VEÍCULO']) === true)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'uniglobeviajex.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->mailDate = strtotime('-1 month', strtotime($parser->getDate()));

        $its = $this->ParseEmail($parser);

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'RoteiroText' . ucfirst($this->lang),
        ];
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function ParseEmail($parser)
    {
        $its = [];

        $text = $parser->getPlainBody();

        $text = str_ireplace(["<br/>", "<br>"], ["\n", "\n"], $text);

        if (!$text) {
            $text = htmlspecialchars_decode(strip_tags(str_ireplace(["<br/>", "<br>"], ["\n", "\n"], $parser->getHTMLBody())));
        }
        $text = htmlspecialchars_decode($text);
        //$this->logger->debug('$text = '.print_r( $text,true));

        foreach (self::$dictionary as $lang => $dict) {
            if (
                (!empty($dict['PASSAGEM AÉREA']) && $this->containsText($text, $dict['PASSAGEM AÉREA']) === true)
                || (!empty($dict['HOSPEDAGEM']) && $this->containsText($text, $dict['HOSPEDAGEM']) === true)
                || (!empty($dict['LOCAÇÃO DE VEÍCULO']) && $this->containsText($text, $dict['LOCAÇÃO DE VEÍCULO']) === true)
            ) {
                $this->lang = $lang;
            }
        }

        // refs #8012
        // >>> SOLICITANTE
        // MARCIA VITORAZZO (mail@br.abb.com)
        if (preg_match("/>>>\s+{$this->opt($this->t('SOLICITANTE'))}\s*(.+?)\s*\(/u", $text, $p)) {
            $pax[] = $p[1];
        }

        //$re = "/((?:>>>)?.+?{$this->opt($this->t('Descrição'))}.+?{$this->opt($this->t('Reserva'))}\.+.+?{$this->opt($this->t('Pagamento'))}([:\w\-\/.,\s]+\s+{$this->opt($this->t('Observação'))}.+?\n{3,})?)/us";
        $re = "/((?:>>>)?.+?{$this->opt($this->t('Descrição'))}.+?{$this->opt($this->t('Pagamento'))}([:\w\-\/.,\s]+\s+{$this->opt($this->t('Observação'))}.+?\n{3,})?)/us";

        if (preg_match_all($re, $text, $trips)) {
            foreach ($trips[1] as $trip) {
                if (stripos($trip, "SERVIÇO") !== false) {
                    continue;
                }
                // if (stripos($trip, '>>> LOCAÇÃO DE VEÍCULO') !== false) {
                if ($this->containsText($trip, preg_replace('/(.+)/', '>>> $1', $this->t('LOCAÇÃO DE VEÍCULO'))) === true) {
                    $this->logger->notice('Car');
                    $year = date('Y', $this->mailDate);

                    $it = ['Kind' => 'L'];
                    $it['RenterName'] = $pax;

                    if (preg_match("/{$this->opt($this->t('Descrição'))}\.:?\s*{$this->opt($this->t('Retirada'))} (?<PickupDatetime>\d+\/.+?\d+:\d+) (?<PickupLocation>.+?) \/ {$this->opt($this->t('Devolução'))} (?<DropoffDatetime>\d+\/.+?\d+:\d+) (?<DropoffLocation>.+?)\s*{$this->opt($this->t('Período'))}.+?{$this->opt($this->t('Total'))}\s*:\s*(?<Currency>[A-Z]{3})\s*(?<TotalCharge>\d+\.\d+)\s*{$this->opt($this->t('Reserva'))}.+?:.+?(?<Number>[A-Z0-9\-]+)\s+{$this->opt($this->t('Pagamento'))}/s", $trip, $m)) {
                        foreach ($m as $key => $value) {
                            if (!is_numeric($key) && trim($value) !== '') {
                                $it[$key] = trim($value);
                            }
                        }
                        $it['PickupDatetime'] = $this->NormalizeDate($it['PickupDatetime'] . ' ' . $year);

                        if ($it['PickupDatetime'] < $this->mailDate) {
                            $it['PickupDatetime'] = strtotime("+1 year", $it['PickupDatetime']);
                            $year++;
                        }
                        $it['DropoffDatetime'] = $this->NormalizeDate($it['DropoffDatetime'] . ' ' . $year);
                    }

                    $its[] = $it;
                } elseif ($this->containsText($trip, $this->t('Período')) === true && $this->containsText($trip, $this->t('Vôo')) === false) { //Vôo
                    $year = date('Y', $this->mailDate);
                    $this->logger->notice('Hotel');
                    //Hotel#
                    $it = ['Kind' => 'R'];
                    $it['GuestNames'] = $pax;

                    $re = "/{$this->opt($this->t('Descrição'))}\.:\s*(?<hotelInfo>.+)"
                        . "\s*{$this->opt($this->t('Período'))}\.*:\s*(?<dates>.+?)"
                        . "\s*{$this->opt($this->t('Valores'))}\.*:(?<price>.+?)"
                        . "\s*{$this->opt($this->t('Reserva'))}\.*:\s*(?<ConfirmationNumber>[A-Z\d\-\/]+)\s+{$this->opt($this->t('Pagamento'))}/us";

                    $re2 = "/{$this->opt($this->t('Descrição'))}\.:\s*(?<hotelInfo>.+)"
                        . "\s*{$this->opt($this->t('Período'))}\.*:\s*(?<dates>.+?)\s+{$this->opt($this->t('Pagamento'))}/us";

                    if (preg_match($re, $trip, $m)
                        || preg_match($re2, $trip, $m)
                    ) {
                        if (isset($m['ConfirmationNumber'])) {
                            $it['ConfirmationNumber'] = $m['ConfirmationNumber'];
                        } else {
                            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
                        }

                        if (
                            preg_match("/(?<HotelName>.+)\s*\((?<RoomType>.*?)\)\s*(?<Address>.+)\s*\({$this->opt($this->t('Fone'))}\s*(?<Phone>[\d\-\+\)\( ]*)\)\s*(?<RoomDesc>.*)$/", $m['hotelInfo'], $mat)
                            || preg_match("/(?<HotelName>.+)\s*\((?<RoomType>.+?)\)\s*(?<Address>.+)\s* {2,}(?<RoomDesc>.+)$/", $m['hotelInfo'], $mat)
                            || preg_match("/(?<HotelName>.+) - (?<Address>.+)\s*\({$this->opt($this->t('Fone'))}\s*(?<Phone>[\d\-\+\)\( ]*)\)\s*(?<RoomDesc>.*)$/", $m['hotelInfo'], $mat)
                        ) {
                            $it['HotelName'] = trim($mat['HotelName']);
                            $it['Address'] = preg_replace('/\s+/', ' ', trim($mat['Address']));
                            $it['Phone'] = $mat['Phone'] ?? null;
                            $it['RoomType'] = trim($mat['RoomType'] ?? '');
                            $it['RoomTypeDescription'] = trim($mat['RoomDesc']);
                        }

                        if (preg_match("/^\s*(?<CheckInDate>\w+\/\w+?\s*\d+:\d+(?: ?[apAP][mM])?)\s*a\s*(?<CheckOutDate>\w+\/\w+?\s*\d+:\d+(?: ?[apAP][mM])?)(?:\s*-\s*(?<CancellationPolicy>{$this->opt($this->t('Limite Cancelamento'))}.+))?/u", $m['dates'], $mat)) {
                            $it['CancellationPolicy'] = $mat['CancellationPolicy'];

                            $it['CheckInDate'] = $this->NormalizeDate($mat['CheckInDate'] . ' ' . $year);

                            if ($it['CheckInDate'] < $this->mailDate) {
                                $it['CheckInDate'] = strtotime("+1 year", $it['CheckInDate']);
                                $year++;
                            }

                            $it['CheckOutDate'] = $this->NormalizeDate($mat['CheckOutDate'] . ' ' . $year);
                        }

                        if (isset($m['price']) && preg_match("/{$this->opt($this->t('Total'))}:?\s*(?<Currency>[A-Z]{3})\s*(?<Total>\d+[\.\,\d]*)\s*$/", $m['price'], $mat)) {
                            $it['Currency'] = $mat['Currency'];
                            $it['Total'] = PriceHelper::parse($mat['Total'], $mat['Currency']);
                        }
                    }
                    $its[] = $it;
                } else {
                    $this->logger->debug('Flight');
                    //Flight#
                    $it = ['Kind' => 'T', 'TripSegments' => []];

                    if (preg_match("#{$this->opt($this->t('Tarifa'))}.+?(?<BaseFare>\d+\.\d+).+{$this->opt($this->t('Taxas'))}.+?(?<Tax>\d+\.\d+)\s*{$this->opt($this->t('Total'))}\s*:?\s*(?<Currency>[A-Z]{3})\s*(?<TotalCharge>\d+\.\d+)#", $trip, $m)) {
                        foreach ($m as $key => $value) {
                            if (in_array($key, ['BaseFare', 'Tax', 'TotalCharge'])) {
                                $m[$key] = PriceHelper::parse($m[$key], $m['Currency']);
                            }

                            if (!is_numeric($key) && trim($value) != '') {
                                $it[$key] = $value;
                            }
                        }
                        // if (preg_match("/{$this->opt($this->t('Total'))}:?\s*(?<Currency>[A-Z]{3})\s*(?<Total>\d+[\.\,\d]*)\s*$/", $m['price'], $mat)) {
                        //     $it['Currency'] = $mat['Currency'];
                        //     $it['Total'] = PriceHelper::parse($mat['Total'], $mat['Currency']);
                    }

                    if (preg_match("#{$this->opt($this->t('Localizador'))}\s*:?\s*(?<RecordLocator>[\-A-Z\d]+)\s*\/.+?{$this->opt($this->t('Bilhete'))}\s*:?\s*(?<TicketNumber>[\-A-Z\d]+)#u", $trip, $m)) {
                        $it['RecordLocator'] = $m['RecordLocator'];
                        $it['TicketNumbers'][] = $m['TicketNumber'];
                    } elseif (preg_match("/{$this->opt($this->t('Localizador'))}\s*:?\s*(?<RecordLocator>[\-A-Z\d]+)\s*(?:\/|[\r\n]+)/u", $trip, $m)) {
                        $it['RecordLocator'] = $m['RecordLocator'];
                    }

                    if (isset($pax)) {
                        $it['Passengers'] = array_filter($pax);
                    }

                    if (preg_match_all("#{$this->opt($this->t('Descrição'))}\.:\s+(\([A-Z]{3}\)\s*.+?\s*/\s*\([A-Z]{3}\)\s*.+?\s*-\s*{$this->opt($this->t('Partida'))}\s*\w+/\w+?/\d+\s+\d+:\d+(?: ?[apAP][mM])?\s*-\s*{$this->opt($this->t('Chegada'))}\s*\w+/\w+/\d+\s+\d+:\d+(?: ?[apAP][mM])?\s*.+?\s*{$this->opt($this->t('Vôo'))}\s*\d+\s*\(.+?\))#u", $trip, $m)) {
                        foreach ($m[1] as $v) {
                            $seg = [];
                            //$this->logger->error($v);
                            preg_match("#\((?<DepCode>[A-Z]{3})\)\s*(?<DepName>.+?)\s*/\s*\((?<ArrCode>[A-Z]{3})\)\s*(?<ArrName>.+?)\s*-\s*{$this->opt($this->t('Partida'))}\s*(?<DepDate>\\w+/.+/\d+\s+\d+:\d+(?: ?[apAP][mM])?)\s*-\s*{$this->opt($this->t('Chegada'))}\s*(?<ArrDate>\w+/\w+/\d+\s+\d+:\d+(?: ?[apAP][mM])?)\s*-?\s*({$this->opt($this->t('Duração'))}\s+(?<Duration>.+?))?\s+(?<AirlineName>.+?)\s*{$this->opt($this->t('Vôo'))}\s*(?<FlightNumber>\d+)\s*\((?<Cabin>.+?)\)#u", $v, $vv);

                            if (preg_match("/^\s*(.+?)\s*\(\s*{$this->opt($this->t('Voo operado pela cia'))}\s+(.+?)\s*\)\s*$/", $vv['AirlineName'], $m)) {
                                $vv['AirlineName'] = $m[1];
                                $vv['Operator'] = $m[2];
                                $vv['Operator'] = preg_replace("/^\s*GOL\s*$/", 'Gol', $vv['Operator']);
                            }

                            foreach ($vv as $key => $value) {
                                if (!is_numeric($key) && trim($value) != '') {
                                    $seg[$key] = $value;
                                }
                            }
                            // "GOL" - use like  ICAO for Cargolaar, 'Gol' - airline Gol air
                            $seg['AirlineName'] = preg_replace("/^\s*GOL\s*$/", 'Gol', $seg['AirlineName']);
                            $seg['DepDate'] = $this->NormalizeDate($seg['DepDate']);
                            $seg['ArrDate'] = $this->NormalizeDate($seg['ArrDate']);
                            $it['TripSegments'][] = $seg;
                        }
                    }

                    if (preg_match("#{$this->opt($this->t('Localizador'))}\s*:?\s*.+?\s*/\s*{$this->opt($this->t('Assento(s)'))}:?\s*(?<Seats>[^/\n]+)#", $trip, $m)) {
                        $seats = explode("|", $m['Seats']);

                        if (!empty(array_filter($seats)) && count($seats) == count($it['TripSegments'])) {
                            foreach ($seats as $key => $value) {
                                if (preg_match("#\b(\d{1,3}[A-Z])\b#", $value, $mat)) {
                                    $it['TripSegments'][$key]['Seats'] = $mat[1];
                                }
                            }
                        }
                    }

                    $its[] = $it;
                }
            }
        }

        return $its;
    }

    private function NormalizeDate($d)
    {
        // $this->logger->debug('$d = '.print_r( $d,true));
        $in = [
            '#(\d+)\/(\w+)\/(\d+)#',
            '#^\s*(\w+)\/(\d+)\/(\d+)#',
            '#^\s*(\d+)\/(\w+)\s*(\d+\:\d+(?: ?[apAP][mM])?)\s*(\d{4})#',
            '#^\s*(\w+)\/(\d+)\s*(\d+\:\d+(?: ?[apAP][mM])?)\s*(\d{4})#',
        ];
        $out = [
            '$1 $2 $3',
            '$2 $1 $3',
            '$1 $2 $4 $3',
            '$2 $1 $4 $3',
        ];
        // $this->logger->debug('preg_replace($in, $out, $d) = '.print_r( preg_replace($in, $out, $d),true));
        $ret = $this->dateStringToEnglish(preg_replace($in, $out, $d));

        return strtotime($ret);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
