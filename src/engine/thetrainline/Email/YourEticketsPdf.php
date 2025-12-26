<?php

namespace AwardWallet\Engine\thetrainline\Email;

//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Bus;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers lner/EticketPDF (in favor of thetrainline/YourEticketsPdf)

class YourEticketsPdf extends \TAccountChecker
{
    public $mailFiles = "thetrainline/it-33375418.eml, thetrainline/it-33763848.eml, thetrainline/it-34014908.eml, thetrainline/it-35225033.eml, thetrainline/it-35363431.eml, thetrainline/it-115707025.eml";

    public static $detectProvider = [
        'cssc' => [
            'from' => 'crosscountry@trainsfares.co.uk',
            'body' => ['crosscountrytrains.co.uk/e‐ticket', 'crosscountrytrains.co.uk/e-ticket'],
        ],
        'lner' => [
            'from' => 'virgintrains@trainsfares.co.uk',
            'body' => ['virgintrains.co.uk/tickets', 'www.lner.co.uk'],
        ],
        'trainpal' => [
            'from' => ['tp-accounts-noreply@trip.com', '@trainpal.com'],
            'body' => [' mytrainpal.com', 'trainpal@trip.com'],
        ],
        'thetrainline' => [
            'from' => 'thetrainline.com',
            'body' => ['thetrainline.com/eticket', ' trainline.com'],
        ],
        'reurope' => [
            'from' => '@raileurope.com',
            'body' => ['.raileurope.com', '@raileurope.com'],
        ],
        'greang' => [
            'from' => 'greateranglia@trainsfares.co.uk',
            'body' => ['greateranglia.'],
        ],
        'awc' => [
            'from' => 'avantiwestcoast@trainsfares.co.uk',
            'body' => ['avantiwestcoast.co.uk/e-ticket'],
        ],
        [
            'from' => 'eastmidlands@trainsfares.co.uk',
            'body' => ['eastmidlandsrailway.co.uk/e-ticket'],
        ],
        [
            'from' => '.lnr@trainsfares.co.uk',
            'body' => ['londonnorthwesternrailway.co.uk/e-ticket'],
        ],
        [
            'from' => 'auto-confirm.wmr@trainsfares.co.uk>',
            'body' => ['westmidlandsrailway.co.uk/e-ticket'],
        ],
    ];

    public $detectSubject = [
        'en' => 'Your e-tickets to',
    ];

    public $detectBody = [
        'en' => ['Ticket Details:'],
    ];

    public $lang = 'en';
    private $providerCode = '';

    public $pdfNamePattern = ".+\.pdf";
    public static $dict = [
        'en' => [],
    ];

    // Hard-code train(bus) stations list from PDF-text. Only first line!
    private $stations = [
        'LONDON ST PANCRAS INTERNATIONAL',
        'HEATHROW CENTRAL BUS STATION',
        'BIRMINGHAM INTERNATIONAL',
        'READING OR READING WEST',
        'READING OR READING',
        'BIRMINGHAM MOOR STREET',
        'LICHFIELD TRENT VALLEY',
        'BIRMINGHAM NEW STREET',
        'BRISTOL TEMPLE MEADS',
        'BIRMINGHAM STATIONS',
        'GILLINGHAM (DORSET)',
        'SOUTHAMPTON CENTRAL',
        'STRATFORD UPON AVON',
        'LONDON BLACKFRIARS',
        'LONDON KINGS CROSS',
        'MANCHESTER (CENTRAL',
        'MANCHESTER AIRPORT',
        'MARKET HARBOROUGH',
        'NEWARK NORTH GATE',
        'LONDON MARYLEBONE',
        'LONDON TERMINALS',
        'POULTON LE FYLDE',
        'SALFORD CRESCENT',
        'EALING BROADWAY',
        'GATWICK AIRPORT',
        'EXETER CENTRAL',
        'LEAMINGTON SPA',
        'LICHFIELD CITY',
        'LONDON BRIDGE',
        'LONDON EUSTON',
        'CRADLEY HEATH',
        'DROITWICH SPA',
        'HUDDERSFIELD',
        'BROAD GREEN',
        'LEVENSHULME',
        'BLACKWATER',
        'HUNTINGDON',
        'MAIDENHEAD',
        'SHEPPERTON',
        'STOCKPORT',
        'NEWCASTLE',
        'HARROGATE',
        'BARNETBY',
        'BRIGHTON',
        'HOLYHEAD',
        'SOLIHULL',
        'ST NEOTS',
        'CHESTER',
        'COOKHAM',
        'NEWQUAY',
        'MARLOW',
        'DERBY',
        'LEEDS',
        'YORK',
        'PAR',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $providerByFrom = '';

        foreach (self::$detectProvider as $code => $detects) {
            if (array_key_exists('from', $detects)
                && $this->striposAll($parser->getCleanFrom() ?? '', $detects['from']) !== false
            ) {
                $providerByFrom = $code;
                
                break;
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$this->assignLang($text)) {
                continue;
            }

            $this->providerCode = '';

            foreach (self::$detectProvider as $code => $detects) {
                if (array_key_exists('body', $detects)
                    && $this->striposAll($text, $detects['body']) !== false
                ) {
                    $this->providerCode = $code;
                    
                    break;
                }
            }

            if (!$this->providerCode && $providerByFrom) {
                $this->providerCode = $providerByFrom;
            }
            
            $this->parseTicket($email, $text);
        }

        if (count($email->getItineraries()) > 0
            && \AwardWallet\Engine\trainpal\Email\YourBooking::findPoints($this)->length > 1
        ) {
            $this->logger->notice('Go to parser trainpal/YourBooking');
            $email->add()->train(); // for 100% fail
        }

        if (!empty($this->providerCode) && is_string($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $providerByFrom = '';

        foreach (self::$detectProvider as $code => $detects) {
            if (array_key_exists('from', $detects)
                && $this->striposAll($parser->getCleanFrom() ?? '', $detects['from']) !== false
            ) {
                $providerByFrom = $code;
                
                break;
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$this->assignLang($text)) {
                continue;
            }

            if ($providerByFrom) {
                return true;
            }

            foreach (self::$detectProvider as $detects) {
                if (array_key_exists('body', $detects)
                    && $this->striposAll($text, $detects['body']) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundCompany = false;

        if (array_key_exists('from', $headers) && stripos($headers['from'], '@trainsfares') !== false) {
            $foundCompany = true;

            foreach (self::$detectProvider as $code => $detects) {
                if (array_key_exists('from', $detects) && $this->striposAll($headers['from'], $detects['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'thetrainline.com') !== false;
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
        return array_filter(array_keys(self::$detectProvider), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
        // return array_keys(self::$detectProvider);
    }

    private function parseTicket(Email $email, string $text): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        ];

        // General
        if (preg_match("#(?:Please quote your |Your )(order ID|Transaction ID):\s*([\d ]{5,})\s*(?:\n|$)#", $text,
            $m)) {
            $m[2] = preg_replace("#\s+#", '', $m[2]);
            $confirmation = [$m[2], $m[1]];
        }

        if (empty($confirmation) && in_array($this->providerCode, ['lner'])) {
            $conf = $this->http->FindSingleNode("//text()[normalize-space() = 'Booking Reference:']/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/");

            if (!empty($conf)) {
                $confirmation = [$conf, 'Booking Reference'];
            }
        }

        $reservationDate = $this->normalizeDate($this->re("#Purchased on[: ]+(.+)\n#u", $text));

        // Ticket Numbers
        if (preg_match("#\n\s*Ticket Number[: ]*([\dA-Z]{5,})\s*\n#", $text, $m)) {
            $ticket = $m[1];
        }

        // Price
        $total = $this->re("#\n\s*Price[ ]*(.+)#", $text);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $total, $m)) {
            if (!empty($email->getPrice()) && !empty($email->getPrice()->getTotal())) {
                $email->price()
                    ->total($email->getPrice()->getTotal() + $this->amount($m['amount']));
            } else {
                $email->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        }

        $codeDep = $codeArr = null;

        if (preg_match("#\n\s*(?<dCode>[A-Z]{3})[ ]+\W? +(?<aCode>[A-Z]{3})\s*\n\s*(?:TICKET|FARE) TYPE#", $text, $m)) {
            $codeDep = $m['dCode'];
            $codeArr = $m['aCode'];
        }

        if (preg_match("#\n[\s\W]*(\d{1,2}[- ][^\d\W]{3,5}[- ]\d{4})[ ]{1,}(?:\w+: *)?[A-Z]{3}[ ]*[\‐\-][ ]*[A-Z]{3}\s*\n(?:.*\n+)*?\s*(?:TICKET|FARE) TYPE#u",
            $text, $m)) {
            $date = $this->normalizeDate($m[1]);
        } elseif (preg_match("#\n[\s\W]*(?<date1>\d{1,2}-)[ ]{2,}(?:\w+: *)?[A-Z]{3}[ ]*[\‐\-][ ]*[A-Z]{3}\s*\n\s*(?<date2>[^\d\W]{3,5}-\d{4})(?:.*\n+)*?\s*(?:TICKET|FARE) TYPE#u",
            $text, $m)) {
//            =              28-                             EDB-KGX
//                           Mar-2022
//            EDINBURGH                            LONDON KINGS CROSS
            $date = $this->normalizeDate($m[1] . $m[2]);
        } else {
            $date = false;
        }

        if (stripos($text, 'www.nationalrail.co.uk') !== false
            || stripos($text, 'crosscountrytrains.co.uk') !== false
            || stripos($text, 'londonnorthwesternrailway.co.uk') !== false
            || stripos($text, 'virgintrains.co.uk') !== false
            || stripos($text, 'mytrainpal.com') !== false
            || stripos($text, 'avantiwestcoast.co.uk') !== false
            || stripos($text, ' trainline.com') !== false
            || stripos($text, 'thetrainline.com') !== false
            || stripos($text, 'reurope.com') !== false
        ) {
            $region = ', UK';
            $codeDep = $codeArr = null;
        } else {
            $region = '';
        }

        $segmentTexts = '';

        /*
            ???
        */
        $regexp = "/\n\s*"
            . "(?:Mandatory Reserva(?: |ti|\W)ons?|I(?: |ti|\W)nerary [-‐]) [^:]*:\s*\n"
            . "\s*(?<firstName>.+)(?<segments>(?:\n\s*{$patterns['time']}\s*\n\s*[\s\S]*?\n\s*{$patterns['time']}\s*\n\s*.+)+)"
            . "/u";

        /*
            ???
        */
        $regexp2 = "/\b"
            . "(?:Mandatory Reserva(?: |ti|\W)ons?|I(?: |ti|\W)nerary [-‐]|I\Wnerary - Op\Wonal Reserva\Wons) \d{1,2} [[:alpha:]]+\s*\n"
            . "\s*(?<firstName>.+)(?<segments>(?:\n\s*{$patterns['time']}\s*\n\s*[\s\S]*?\n\s*{$patterns['time']}\s*\n\s*.+)+)"
            . "/u";

        /*
            ???
        */
        $regexp3 = "/" // it-35225033.eml
            . "Mandatory Reserva(?: |ti|\W)ons .*(?:\n.*)?:"
            . "\s*(?<segments>(?:\n\s*{$patterns['time']}\s+[\s\S]+?\n\s*\n[\s\S]*?\n\s*{$patterns['time']}\s+[\s\S]+?)+)\n\s*\n"
            . "/u";

        /*
            11:03 CrossCountry
            From Manchester Piccadilly
            To Stoke-on-Trent
            Coach C,Seat 65
        */
        $regexp4 = "/"
            . "Itinerary [-‐] (?:Mandatory|Optional) Reservations\s+\d{1,2}\s+[[:alpha:]]+\s*:\s*\n"
            . "(?<segments>(?:{$patterns['time']}.*\n{1,2}[ ]*From .+\n{1,2}[ ]*To .+\n.*\n+)+)\n"
            . "/u";

        if (preg_match($regexp, $text, $m) || preg_match($regexp2, $text, $m)) {
            $segmentTexts = $m['segments'];
            $depName = $m['firstName'];
            $sregexp = "/\s*\n\s*(?<dTime>{$patterns['time']})\s*\n\s*(?<info>[\s\S]*?)\n\s*(?<aTime>{$patterns['time']})\s*\n\s*(?<aName>.+)/";
        } elseif (preg_match($regexp3, $text, $m)) {
            // no examples for 2 or more segments
            $segmentTexts = $m['segments'];
            $sregexp = "/\s*\n\s*(?<dTime>{$patterns['time']})\s+(?<dName>[\s\S]+?)\n\s*\n(?<info>[\s\S]*?)\n\s*(?<aTime>{$patterns['time']})\s+(?<aName>[\s\S]+?)\s*(?=\n\s*{$patterns['time']}|\s*$)/";
        } elseif (preg_match($regexp4, $text, $m)) {
            $segmentTexts = $m['segments'];
            $sregexp = "/\b(?<dTime>{$patterns['time']})[ ]*(?<info>.*)\n{1,2}[ ]*From[ ]+(?<dName>[\s\S]{2,}?)\n{1,2}[ ]*To[ ]+(?<aName>.{2,})\n[ ]*(?<seat>.*)/";
        }
        
        if (!empty($segmentTexts) && preg_match_all($sregexp, $segmentTexts, $m, PREG_SET_ORDER)) {
            foreach ($m as $i => $segParams) {
                // Segments

                $typeBus = preg_match("/^\s*Bus\b/i", array_key_exists('info', $segParams) ? $segParams['info'] : '') > 0 ? true : false;

                if ($typeBus) {
                    $fountIt = false;

                    foreach ($email->getItineraries() as $value) {
                        if ($value->getType() == 'bus') {
                            /** @var Bus $t */
                            $t = $value;
                            $fountIt = true;

                            break;
                        }
                    }

                    if ($fountIt == false) {
                        $t = $email->add()->bus();
                    }
                } else {
                    $fountIt = false;

                    foreach ($email->getItineraries() as $value) {
                        if ($value->getType() == 'train') {
                            /** @var Train $t */
                            $t = $value;
                            $fountIt = true;

                            break;
                        }
                    }

                    if ($fountIt == false) {
                        $t = $email->add()->train();
                    }
                }

                if (!empty($confirmation) && !in_array($confirmation[0], array_column($t->getConfirmationNumbers(), 0))) {
                    $t->general()->confirmation($confirmation[0], $confirmation[1]);
                }

                if (empty($t->getReservationDate())) {
                    $t->general()->date($reservationDate);
                }

                if (!in_array($ticket, array_column($t->getTicketNumbers(), 0))) {
                    $t->addTicketNumber($ticket, false);
                }

                $s = $t->addSegment();

                $s->extra()->noNumber();

                $params['date'] = $date;
                $params['region'] = $region;
                $params['match'] = $segParams;
                $params['match']['dName'] = !empty($params['match']['dName']) ? $params['match']['dName'] : $depName;
                $params['match']['dName'] = !empty($params['match']['dName']) ? $params['match']['dName'] : $depName;
                $depName = $segParams['aName'];
                $this->parseSegment($s, $params);

                if (!empty($codeDep) && $i == 0) {
                    $s->departure()->code($codeDep);
                }

                if (!empty($codeArr) && count($m) == ($i + 1)) {
                    $s->arrival()->code($codeArr);
                }

                $segments = $t->getSegments();

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize(array_diff_key($segment->toArray(),
                                ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            if (!empty($s->getSeats())) {
                                $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                    $s->getSeats())));
                            }
                            $t->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }

        if (empty($segmentTexts) && !preg_match("/\b{$patterns['time']}(?:\b|\D|$)/", $text)) {
            $fountIt = false;

            foreach ($email->getItineraries() as $value) {
                if ($value->getType() == 'train') {
                    /** @var Train $t */
                    $t = $value;
                    $fountIt = true;

                    break;
                }
            }

            if ($fountIt == false) {
                $t = $email->add()->train();
            }

            if (!in_array($confirmation[0], array_column($t->getConfirmationNumbers(), 0))) {
                $t->general()->confirmation($confirmation[0], $confirmation[1]);
            }

            if (empty($t->getReservationDate())) {
                $t->general()->date($reservationDate);
            }

            if (!in_array($ticket, array_column($t->getTicketNumbers(), 0))) {
                $t->addTicketNumber($ticket, false);
            }

            /*
                            07 Mar 2019    Out: STP ‐ MHR
                LONDON ST PANCRAS INTERNATIONAL
                                        MARKET HARBOROUGH
                STP                                   MHR
                TICKET TYPE                         ROUTE
             */
            $patterns['stations'] = '/'
                . '\n[=]?[ ]*(?<date>\d{1,2}[ ]+[[:alpha:]]{3,}[ ]+\d{2,4})\b.*\s*\n(?<stations>.*(\n.*){0,4}?)\s*\n[ ]*(?<depCode>[A-Z]{3})[ ]{2,}(?<arrCode>[A-Z]{3})\n\s*(?:TICKET|FARE) TYPE[ ]{2,}ROUTE'
                . '/';

            if (preg_match($patterns['stations'], $text, $m)) {
                $date = strtotime($m['date']);
                $patterns['stationName'] = '[[:upper:] ,.)(‐\-\\/]{3,}';
                $patterns['allStations'] = $this->opt($this->stations);

                $point1Name = $point2Name = null;
                $tablePos = [0];

                if (preg_match("/^({$patterns['stationName']}[ ]{2,}){$patterns['stationName']}(?:\n|$)/", $m['stations'], $matches)
                    || preg_match("/^{$patterns['stationName']}\n+(.{3,}[ ]{2,}){$patterns['stationName']}(?:\n|$)/", $m['stations'], $matches)
                ) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $table = $this->splitCols($m['stations'], $tablePos);

                if (count($table) !== 2) {
                    if (preg_match("/^({$patterns['allStations']}[ ]+){$patterns['allStations']}(?:\n|$)/", $m['stations'], $matches)) {
                        $tablePos[] = mb_strlen($matches[1]);
                    }
                    $table = $this->splitCols($m['stations'], $tablePos);
                }

                if (count($table) === 2) {
                    $point1Name = trim($table[0]);
                    $point2Name = trim($table[1]);
                }

                // $point1Code = $m['depCode'];
                // $point2Code = $m['arrCode'];

                $s = $t->addSegment();

                $s->extra()->noNumber();

                $s->departure()
                    // ->code($point1Code)
                    ->name(preg_replace('/\s+/', ' ', $point1Name) . $region)
                ;
                $s->arrival()
                    // ->code($point2Code)
                    ->name(preg_replace('/\s+/', ' ', $point2Name) . $region)
                ;

                if (preg_match("/^(.+ )DEPART(?: |$)/m", $text, $matches)
                    && preg_match("/\n.+ DEPART(?: .*)\n.{" . (mb_strlen($matches[1]) - 3) . "} +(\d{1,2}:\d{1,2})\b/m", $text, $m)) {
                    $s->departure()
                        ->date(!empty($date) ? strtotime($m[1], $date) : false)
                    ;
                    $s->arrival()
                        ->noDate();
                } else {
                    $s->departure()
                        ->noDate();
                    $s->arrival()
                        ->noDate();
                }

                if (preg_match("/^(.+ )COACH +SEAT *$/m", $text, $matches)
                    && preg_match("/\n.+ COACH +SEAT(?: .*)\n.{" . (mb_strlen($matches[1]) - 3) . "} +([A-Z\d]+)[ ]{2,}(\d[A-Z\d]*)/m", $text, $m)) {
                    $s->extra()
                        ->car($m[1])
                        ->seat($m[2]);
                }

                $segments = $t->getSegments();

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize(array_diff_key($segment->toArray(),
                                ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            if (!empty($s->getSeats())) {
                                $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                    $s->getSeats())));
                            }
                            $t->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }

        if (in_array($this->providerCode, ['reurope', 'lner'])) {
            foreach ($email->getItineraries() as $value) {
                if ($value->getType() == 'train' && empty(array_column($value->getConfirmationNumbers(), 0))) {
                    $value->general()->noConfirmation();
                }
            }
        }
    }

    private function parseSegment($s, array $params): void
    {
        $m = $params['match'];

        $s->departure()
            ->name(preg_replace('/\s+/', ' ', $m['dName']) . $params['region'])
            ->date(!empty($params['date']) ? strtotime($m['dTime'], $params['date']) : false)
            ->geoTip('Europe');

        $s->arrival()
            ->name(preg_replace('/\s+/', ' ', $m['aName']) . $params['region'])
            ->geoTip('Europe');

        if (array_key_exists('aTime', $m)) {
            $s->arrival()->date(!empty($params['date']) ? strtotime($m['aTime'], $params['date']) : false);
        } else {
            $s->arrival()->noDate();
        }

        if (preg_match("/Coach ([A-Z\d]{1,3})[,\s]+Seat ([A-Z\d]{1,4})\b/", empty($m['seat']) ? $m['info'] : $m['seat'], $mat)) {
            $s->extra()
                ->car($mat[1])
                ->seat($mat[2]);
        }
    }

    private function striposAll(string $text, $needle): bool
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

    private function assignLang(?string $body): bool
    {
        if (empty($body)) {
            $body = $this->http->Response['body'];
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*$#u', //10 Feb 2019
        ];
        $out = [
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)){
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$date = str_replace($m[1], $en, $date);
        //		}
        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = trim($price);

        if (preg_match("#^([\d,. ]+)[.,](\d{2})$#", $price, $m)) {
            $price = str_replace([' ', ',', '.'], '', $m[1]) . '.' . $m[2];
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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
}
