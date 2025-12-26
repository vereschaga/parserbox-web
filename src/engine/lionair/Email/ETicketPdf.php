<?php

namespace AwardWallet\Engine\lionair\Email;

// TODO: move one format (it-11118606.eml, it-33986345.eml, it-9099930.eml) from this parser to parser batikair/BookingConfirmation

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "lionair/it-11118606.eml, lionair/it-18859850.eml, lionair/it-18963446.eml, lionair/it-19073508.eml, lionair/it-9069591.eml, lionair/it-9099930.eml, lionair/it-9988702.eml, lionair/it-33986345.eml, lionair/it-69731973.eml, lionair/it-671384372.eml, lionair/it-671616366.eml";

    public static $dictionary = [
        'th' => [
            'headerStart'       => 'เอกสารกำหนดการเดินทาง',
            'headerEnd'         => 'รายละเอียดการเดินทาง',
            'confNumber'        => 'หมายเลขอ้างอิงการจอง',
            'Issued Date'       => 'ันที่ออกเอกสาร',
            'Passenger Details' => 'รายละเอียดผูโ้ ดยสาร',
            'passengersStart'   => 'รายละเอียดผูโ้ ดยสาร',
            'passengersEnd'     => 'รายละเอียดการเดินทาง',
            'itineraryStart'    => 'รายละเอียดการเดินทาง',
            'itineraryEnd'      => 'ข้อกำหนดอัตราค่าบัตรโดยสาร',
            'route'             => ['เที่ยวบินขาไป', 'เที ่ ย วบิ น ขาไป'],
            'Flight'            => ['เที่ยวบิน', 'วบิน'],
            'Class'             => 'ชั้น',
            'Status'            => 'สถานะ',
            'fareStart'         => 'สรุปการจอง',
            'fareEnd'           => ['บริการบนเที่ยวบิน', 'บริการบนเทีย'],
            'Total Paid'        => 'ยอดรวมที่ชำระ',
            'Total Taxes'       => 'ภาษีรวม',
        ],
        'en' => [
            'headerStart'     => ['Booking Details', 'eTicket Itinerary'],
            'headerEnd'       => 'Itinerary Details',
            'confNumber'      => ['Booking reference no.', 'Booking Reference (PNR)', 'Place Of Issue'],
            'passengersStart' => 'Passenger Details',
            'passengersEnd'   => 'Itinerary Details',
            'itineraryStart'  => 'Itinerary Details',
            'itineraryEnd'    => ['Fare Details', 'Fare Rules', 'Ancillary Item'],
            'route'           => ['Departure Flight', 'Return Flight'],
            'fareStart'       => ['Fare Details', 'Booking Summary'],
            'fareEnd'         => ['Fare Rules', 'In-Flight Service'],
            'Total Paid'      => ['Total Paid', 'Total Payment', 'Total Amount', 'TOTAL'],
        ],
    ];

    private $detects = [
        'th' => [ // it-69731973.eml
            'นี่คือเอกสารกำหนดการเดินทางที่ออกโดยสายการบินไทยไลอ้อนแอร์ ในการเช็คอิน คุณต้องแสดงเอกสารนีพ',
        ],
        'en' => [
            'This is an eTicket itinerary. To enter the airport and for check-in',
            'This is an eTicket itinerary. To check-in, you must present this document along with an official government-issued photo identification such as a passport or',
            'This is an eTicket itinerary issued by Thai Lion air',
        ],
    ];

    private $patterns = [
        'date'          => '\b\d{1,2} [[:alpha:]]{3,25} \d{4}\b', // 04 Aug 2017
        'time'          => '\d{1,2}[.:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    12.55
        'travellerName' => '[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang    |    Witten/Catherine Mrs
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    private $lang = 'en';

    private $pdfText = '';

    private $enDatesInverted = false;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (false === $this->getPDFText($parser)) {
            return false;
        }

        return [
            'emailType'  => 'ETicketPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) < 1) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        if (stripos($body, 'Lion Air') === false && stripos($body, 'ไลอ้อนแอร์') === false
            && stripos($body, '@lionairthai.com') === false
        ) {
            return false;
        }

        foreach ($this->detects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lionairthai.com') !== false || stripos($from, 'lionair.co.id') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Lion Air - Booking Confirmation ID') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $text = $this->pdfText;

        if (preg_match_all('/\b(\d{1,2})\/\d{1,2}\/\d{4}\b/', $text, $dateMatches)) {
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->enDatesInverted = true;
                }
            }
        }

        $firstBookingDetails = $this->re("/{$this->opt($this->t('headerStart'))}([\s\S]+?){$this->opt($this->t('headerEnd'))}/", $text);

        $it['RecordLocator'] = $this->re("/{$this->opt($this->t('confNumber'))}[: ]+([A-Z\d]{5,7})(?:_[A-Z\d]|[ ]{2}|$)/im", $firstBookingDetails);

        $it['TripNumber'] = $this->re("/{$this->opt($this->t('confNumber'))}[: ]+[A-Z\d]{5,7}_([A-Z\d]{5,})(?:[ ]{2}|$)/im", $firstBookingDetails);

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('Issued Date'))}\s*:\s*(.+)/i", $firstBookingDetails)));

        $travellers = $tickets = $accounts = [];
        $textPass = $this->findCutSectionAll($text, $this->t('passengersStart'), $this->t('passengersEnd'));
        preg_match_all("/^\s*\d+\.[ ]+(?<ticket>{$this->patterns['eTicket']})[ ]{3,}(?<pass>{$this->patterns['travellerName']})$/imu", $textPass, $m); // 2.  9902145648081  Dieguez/Hernan Mr
        preg_match_all("/^\s*\d+\.[ ]+(?<pass>{$this->patterns['travellerName']})?[ ]{3,}(?<ticket>{$this->patterns['eTicket']})(?:$|[ ]{4,}\S)/imu", $textPass, $m2); // 2. Landreville/Jessica Miss 9902157849531
        $m2['pass'] = array_values(array_filter($m2['pass']));

        if (empty($m[0]) && empty($m2[0])) {
            preg_match_all("/^\s*(?<pass>{$this->patterns['travellerName']})?[ ]{3,}(?<ticket>{$this->patterns['eTicket']})[ ]{3,}\w+[ ]*$/imu", $textPass, $m); // Miss.AGATHE HOUSSIN   9902157815466   Adult
        }

        if (empty($m[0]) && empty($m2[0])) {
            preg_match_all("/^\s*(?:\d+\.[ ]+)?(?<pass>{$this->patterns['travellerName']})?(?:[ ]{3,}(?<account>[A-Z][A-Z\d]{5,}))?[ ]{3,}(?<ticket>{$this->patterns['eTicket']})[ ]*$/imu", $textPass, $m); // Bakhtiiar/Bernara Miss   990217582527 , Deguine/Maxime Mr ID14DR16157   9902133894923
        }

        if (!empty($m['pass'])) {
            $travellers = array_merge($travellers, $m['pass']);
        }

        if (!empty($m2['pass'])) {
            $travellers = array_merge($travellers, $m2['pass']);
        }

        if (!empty($m['ticket'])) {
            $tickets = array_merge($tickets, $m['ticket']);
        }

        if (!empty($m2['ticket'])) {
            $tickets = array_merge($tickets, $m2['ticket']);
        }

        if (!empty($m['account'])) {
            $accounts = array_filter($m['account']);
        }

        preg_match_all('/Loyalty No:[ ]+([A-Z\d]{6,})\b/im', $textPass, $m);

        if (!empty($m[1])) {
            // it-18963446.eml
            $accounts = $m[1];
        }

        if (count($travellers) > 0) {
            $it['Passengers'] = array_values(array_unique(array_map(function ($item) {
                $namePrefixes = $this->opt(['Miss', 'Mrs', 'Mr', 'Ms']);

                if (preg_match("/^{$namePrefixes}\.\s*([[:alpha:]].*[[:alpha:]])$/iu", $item, $m) // it-18859850.eml
                    || preg_match("/^([[:alpha:]].*[[:alpha:]])\s+{$namePrefixes}$/iu", $item, $m) // it-18963446.eml
                ) {
                    return $m[1];
                }

                return $item;
            }, $travellers)));
        }

        if (count($tickets) > 0) {
            $it['TicketNumbers'] = array_values(array_unique($tickets));
        }

        if (count($accounts) > 0) {
            $it['AccountNumbers'] = array_values(array_unique($accounts));
        }

        $itineraryDetails = $this->findCutSection($text, $this->t('itineraryStart'), $this->t('itineraryEnd'));

        $segments = [];
        $segments = $this->split("/({$this->opt($this->t('route'))}|\n[ ]{0,4}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) \d+[ ]{2,}\S.{7})/", $itineraryDetails, -1, PREG_SPLIT_NO_EMPTY);

        $tmpSegs = [];

        foreach ($segments as $segment) {
            preg_match_all("/(.+\s+{$this->opt($this->t('Flight'))}\s+[\S\s]+?\s+{$this->opt($this->t('Class'))}\s+.+\([A-Z]\))/", $segment, $m);

            if (!empty($m[1])) {
                array_walk($m[1], function ($el) use (&$tmpSegs) {
                    $tmpSegs[] = $el;
                });
            }
        }

        if (count($tmpSegs) > count($segments)) {
            unset($segments);
            $segments = $tmpSegs;
        }

        $textSeats = $this->findCutSection($text, 'In-Flight Service', 'Important Notes');
        $seats = [];

        if (preg_match_all('/Seat Number\s*:\s*([A-Z\d]{1,3})/', $textSeats, $m) && count($m[1]) > 0 && count($segments) > 0) {
            $size = count($m[1]) / count($segments);
            $seats = array_chunk($m[1], $size);
        }

        $re = "/(?<dep>.+)[ ]{2,}(?<arr>.+)\s+{$this->opt($this->t('Flight'))}\s+(?<aname>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<fnum>\d+)\s+depart\s+arrive\s+(?<ddate>\d+\/\d+\/\d+) (?<dtime>{$this->patterns['time']})\s+(?<adate>\d+\/\d+\/\d+) (?<atime>{$this->patterns['time']})\s+{$this->opt($this->t('Status'))}\s+(?<status>\w+)\s+{$this->opt($this->t('Class'))}\s+(?<cabin>\w+)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)/i";

        foreach ($segments as $segment) {
            $segment = preg_replace("/{$this->opt($this->t('route'))}/", '', $segment);

            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            if (preg_match_all($re, $segment, $m)) {
                // examples: it-11118606.eml, it-33986345.eml, it-9099930.eml
                $this->logger->debug('Parse segments type: 1');

                foreach ($m[0] as $key => $m_value) {
                    $seg = [];

                    $seg['AirlineName'] = $m['aname'][$key];
                    $seg['FlightNumber'] = $m['fnum'][$key];

                    if (isset($m['dcode'][$key])) {
                        $seg['DepCode'] = $m['dcode'][$key];
                    } else {
                        $seg['DepName'] = trim($m['dep'][$key]);
                    }

                    if (isset($m['acode'][$key])) {
                        $seg['ArrCode'] = $m['acode'][$key];
                    } else {
                        $seg['ArrName'] = trim($m['arr'][$key]);
                    }

                    if (!empty($m['term'][$key])) {
                        $seg['DepartureTerminal'] = trim($m['term'][$key], ' *');
                    }

                    if (!empty($m['depterm'][$key])) {
                        $seg['DepartureTerminal'] = trim($m['depterm'][$key], ' *');
                    }

                    if (!empty($m['arrterm'][$key])) {
                        $seg['ArrivalTerminal'] = trim($m['arrterm'][$key], ' *');
                    }

                    if (isset($seg['DepName']) && isset($seg['ArrName'])) {
                        foreach ([
                            'Dep' => $seg['DepName'][$key],
                            'Arr' => $seg['ArrName'][$key],
                        ] as $prefix => $value) {
                            if (preg_match('/^\b[A-Z]{3}\b$/', $value)) {
                                $seg[$prefix . 'Code'] = $value;
                                unset($seg[$prefix . 'Name']);
                            }
                        }
                    }

                    $seg['DepDate'] = strtotime($this->normalizeDate($m['ddate'][$key] . ', ' . $m['dtime'][$key]));
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m['adate'][$key] . ', ' . $m['atime'][$key]));

                    if (isset($m['oper'][$key]) && stripos($m['oper'][$key], 'lion') === false) {
                        $seg['Operator'] = $m['oper'][$key];
                    }

                    if (!empty($m['cabin'][$key])) {
                        $seg['Cabin'] = trim($m['cabin'][$key]);
                    }
                    $seg['BookingClass'] = $m['class'][$key];

                    if (array_key_exists('stops', $m) && array_key_exists($key, $m['stops']) && $m['stops'][$key] !== '') {
                        $seg['Stops'] = trim($m['stops'][$key]);
                    }

                    if (isset($seg['FlightNumber']) && isset($seg['DepName']) && !isset($seg['DepCode'])) {
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    }

                    if (isset($seg['FlightNumber']) && isset($seg['ArrName']) && !isset($seg['ArrCode'])) {
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    if (count($seats) > 0) {
                        $seg['Seats'] = array_shift($seats);
                    }

                    $it['TripSegments'][] = $seg;
                }
            } else {
                // examples: it-18963446.eml, it-19073508.eml, it-9069591.eml, it-9988702.eml, it-671384372.eml, it-671616366.eml
                $this->logger->debug('Parse segment type: 2');

                $seg = [];

                if (preg_match("/^([\s\S]+?)\n+[* ]*(?:[A-Z]{3} ?- ?[A-Z]{3} )?Operated by[ ]+(\S.*?\S)(?:[ ]{2}|\n|$)/i", $segment, $m)) {
                    // *DPS-KUL OPERATED BY BATIK AIR (it-19073508.eml)
                    $segment = $m[1];
                    $seg['Operator'] = $m[2];
                }

                if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)[ ]+[^\d\s]/", $segment, $m)) {
                    $seg['AirlineName'] = $m['name'];
                    $seg['FlightNumber'] = $m['number'];
                }

                $tablePos = [0];

                if (preg_match("/((.{4,} ){$this->patterns['date']}[ ]+){$this->patterns['date']}/u", $segment, $matches)) {
                    $tablePos[1] = mb_strlen($matches[2]);
                    $tablePos[2] = mb_strlen($matches[1]);
                } elseif (preg_match_all("/^(.{4,} ){$this->patterns['date']}/mu", $segment, $dateMatches) && count($dateMatches[0]) === 2) {
                    $dateBeforePos = array_map('mb_strlen', $dateMatches[2]);
                    sort($dateBeforePos);
                    $tablePos[1] = $dateBeforePos[0];
                    $tablePos[2] = $dateBeforePos[1];
                }

                if (preg_match("/^\n*([ ]{0,4}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+[ ]+[^\d\s].{20,65}?\S[ ]+)(?:\d{1,3}[ ]{2,})?(?:[[:alpha:]]{3,15} - [A-Z]{1,2}|[A-Z]{1,2})(?:[ ]{2}|\n)/", $segment, $matches)
                    || preg_match("/^\n*.{15,70}\n+(.{50,}?[ ]{2})(?:\d{1,3}[ ]{2,})?(?:[[:alpha:]]{3,15} - [A-Z]{1,2}|[A-Z]{1,2})(?:[ ]{2}|\n)/", $segment, $matches) // it-671616366.eml
                ) {
                    $tablePos[3] = mb_strlen($matches[1]);
                }

                $table = $this->splitCols($segment, $tablePos);

                if (count($table) === 4) {
                    $table = array_map(function ($item) { return trim($item, ")\n"); }, $table);

                    $nameDep = $this->re("/^\s*(.+?)\n+[ ]*{$this->patterns['date']}/su", $table[1]);

                    if (preg_match($patternName = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})[\s)]*$/s", $nameDep, $m)) {
                        // Denpasar (bali) (DPS)
                        $seg['DepName'] = preg_replace('/\s+/', ' ', $m['name']);
                        $seg['DepCode'] = $m['code'];
                    } elseif ($nameDep) {
                        $seg['DepName'] = $nameDep;
                    }

                    $dateDep = strtotime($this->normalizeDate($this->re("/^[ ]*({$this->patterns['date']})/mu", $table[1])));
                    $timeDep = $this->re("/^[ ]*({$this->patterns['time']})/m", $table[1]);

                    if ($dateDep && $timeDep) {
                        $seg['DepDate'] = strtotime($timeDep, $dateDep);
                    }

                    if (preg_match($patternTerminal = "/\n[ ]*Terminal ([A-z\d][-A-z\d\s]*?)\s*$/i", $table[1], $m)) {
                        // Terminal Halim P K
                        $seg['DepartureTerminal'] = preg_replace('/\s+/', ' ', $m[1]);
                    } elseif (preg_match("/^.*\bCHECK-IN WITH .{2,}? TERMINAL ([A-z\d][-A-z\d ]*?)(?:[ ]{2}|$)/m", $segment, $m)) {
                        // CGK-SIN CHECK-IN WITH LION AIR CGK-KEBERANGKATAN TERMINAL 2D (it-9988702.eml)
                        $seg['DepartureTerminal'] = $m[1];
                    }

                    $nameArr = $this->re("/^\s*(.+?)\n+[ ]*{$this->patterns['date']}/su", $table[2]);

                    if (preg_match($patternName, $nameArr, $m)) {
                        $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['name']);
                        $seg['ArrCode'] = $m['code'];
                    } elseif ($nameArr) {
                        $seg['ArrName'] = $nameArr;
                    }

                    $dateArr = strtotime($this->normalizeDate($this->re("/^[ ]*({$this->patterns['date']})/mu", $table[2])));
                    $timeArr = $this->re("/^[ ]*({$this->patterns['time']})/m", $table[2]);

                    if ($dateArr && $timeArr) {
                        $seg['ArrDate'] = strtotime($timeArr, $dateArr);
                    }

                    if (preg_match($patternTerminal, $table[2], $m)) {
                        $seg['ArrivalTerminal'] = preg_replace('/\s+/', ' ', $m[1]);
                    }

                    if (preg_match("/^(?<stops>\d{1,3})(?:[ ]{2}|\n)/", $table[3], $m)) {
                        $seg['Stops'] = $m['stops'];
                    }

                    if (preg_match("/^(?<stops>\d{1,3}[ ]{2,})?(?:(?<cabin>[[:alpha:]]{3,15}) - )?(?<bookingCode>[A-Z]{1,2})(?:[ ]{2}|\n)/", $table[3], $m)) {
                        // Economy - Y    |    Y
                        if (!empty($m['cabin'])) {
                            $seg['Cabin'] = $m['cabin'];
                        }
                        $seg['BookingClass'] = $m['bookingCode'];
                    }
                }

                $it['TripSegments'][] = $seg;
            }
        }

        if (empty($it['TripSegments'][0]['FlightNumber']) && preg_match("/{$this->opt($this->t('Class'))}[ ]*:/iu", $text)) {
            // examples: it-18859850.eml, it-69731973.eml
            $it['TripSegments'] = $this->parseSegmentsType3($itineraryDetails);
        }

        if (preg_match_all("/{$this->opt($this->t('fareStart'))}([\s\S]+?){$this->opt($this->t('fareEnd'))}/", $text, $fareMatches)) {
            $fareDetails = implode("\n", $fareMatches[1]);
        } else {
            $fareDetails = null;
        }

        if (preg_match_all('/(?:^|\n)\s*(?:Published Fare:|Flight(?:, Insurance|, Baggages)?[ ]*:*)[ ]+\w+[ ]+(\d[,.\'\d]*)\s*(\n|$)/u', $fareDetails, $m)) {
            $it['BaseFare'] = null;

            foreach ($m[1] as $value) {
                if ($it['BaseFare'] === null) {
                    $it['BaseFare'] = $this->normalizeAmount($value);
                } else {
                    $it['BaseFare'] += $this->normalizeAmount($value);
                }
            }
        }

        $it['Currency'] = $this->re("/{$this->opt($this->t('Total Paid'))}[:\s]+([A-Z]{3})\s+\d[,.\'\d]*/", $fareDetails);

        if (preg_match_all("/{$this->opt($this->t('Total Paid'))}[:\s]+\w+\s+(\d[,.\'\d]*)/", $fareDetails, $m)) {
            $it['TotalCharge'] = null;

            foreach ($m[1] as $value) {
                if ($it['TotalCharge'] === null) {
                    $it['TotalCharge'] = $this->normalizeAmount($value);
                } else {
                    $it['TotalCharge'] += $this->normalizeAmount($value);
                }
            }
        }

        if (preg_match_all("/{$this->opt($this->t('Total Taxes'))}[ ]*:*\s+\w+\s+(\d[,.\'\d]*)/", $fareDetails, $m)) {
            $it['Tax'] = null;

            foreach ($m[1] as $value) {
                if ($it['Tax'] === null) {
                    $it['Tax'] = $this->normalizeAmount($value);
                } else {
                    $it['Tax'] += $this->normalizeAmount($value);
                }
            }
        }

        return [$it];
    }

    private function parseSegmentsType3($text): array
    {
        $res = [];
        $re = "/{$this->opt($this->t('Flight'))}[ ]*:\s*(?<aname>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fnum>\d+)\s+{$this->opt($this->t('Class'))}[ ]*:\s*(?<cabin>.+?)\((?<bcode>[A-Z]{1,2})\).+\n\s*(?<dcode>[A-Z]{3})[ ]{3,}(?<acode>[A-Z]{3})\s*\n\s*(?<dname>.+?)[ ]{3,}(?<arrname>.+)\s*\n\s*(?<ddate>\S.*?\d{4}.+?)[ ]{3,}(?<adate>\S.*?\d{4}.+)/iu";
        preg_match_all($re, $text, $m);

        if (!empty($m) && count($m['aname']) > 0) {
            foreach ($m['aname'] as $i => $value) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if (!isset($m['aname'][$i])) {
                    return [];
                }
                $seg['AirlineName'] = $m['aname'][$i];
                $seg['FlightNumber'] = $m['fnum'][$i];
                $seg['DepName'] = $m['dname'][$i];
                $seg['ArrName'] = $m['arrname'][$i];
                $seg['DepCode'] = $m['dcode'][$i];
                $seg['ArrCode'] = $m['acode'][$i];
                $seg['DepDate'] = strtotime(str_replace('/', '.', $m['ddate'][$i]));
                $seg['ArrDate'] = strtotime(str_replace('/', '.', $m['adate'][$i]));
                $seg['Cabin'] = trim($m['cabin'][$i]);
                $seg['BookingClass'] = $m['bcode'][$i];
                $res[] = $seg;
            }
            $this->logger->debug('Parse segments type: 3');
        }

        return $res;
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            "/^(\d+)\/(\d+)\/(\d{2,4}, {$this->patterns['time']})$/", // 30/09/2016 11:35    |    09/30/2016 11:35
            '/^[^\d\s]+,\s*(\d+)\s+([^\d\s]+),\s*(\d{4})$/', //Sunday, 29 Apr, 2018
            "/^(\d+)\s+([^\d\s]+),\s*(\d{4})\s*,\s+({$this->patterns['time']})$/", //17 Jul 2018 12.55
        ];
        $out[0] = $this->enDatesInverted ? '$1.$2.$3' : '$2.$1.$3';
        $out[1] = '$1 $2 $3';
        $out[2] = '$1 $2 $3 $4:$5';

        return preg_replace($in, $out, $str);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function re($re, $text, $index = 1)
    {
        if ($index !== 1 && !empty($index)) {
            if (preg_match($re, $text, $m) && isset($m[$index])) {
                return $m[$index];
            }
        } elseif (preg_match($re, $text, $m) && isset($m[1])) {
            return $m[1];
        }

        return null;
    }

    private function getPDFText(\PlancakeEmailParser $parser): bool
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) < 1) {
            $this->logger->info('PDF attachment not found');

            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->detects as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    $this->pdfText = $body;
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function findCutSection($input, $searchStart, $searchFinish = null)
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

    private function findCutSectionAll($input, $searchStart, $searchFinish = null)
    {
        $result = $this->findCutSection($input, $searchStart, $searchFinish);

        if (!empty($result)) {
            $textNext = $input;
            $pos = strpos($textNext, $searchStart);
            $textNext = substr($textNext, $pos + 100);
            $i = 0;

            while ($pos > 0 && $i < 10) {
                $pos = strpos($textNext, $searchStart);

                if ($pos !== false) {
                    $result .= "\n" . $this->findCutSection($textNext, $searchStart, $searchFinish);
                    $textNext = substr($textNext, $pos + 100);
                } else {
                    break;
                }
                $i++;
            }
        }

        return $result;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
