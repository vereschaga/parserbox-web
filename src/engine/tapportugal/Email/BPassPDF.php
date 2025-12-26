<?php

namespace AwardWallet\Engine\tapportugal\Email;

class BPassPDF extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-10253212.eml, tapportugal/it-4781420.eml, tapportugal/it-4783457.eml, tapportugal/it-5419547.eml, tapportugal/it-5446447.eml, tapportugal/it-5701841.eml";
    public $reSubject = [
        'en' => 'Your Boarding Pass Confirmation',
    ];
    public $reBody = [
        ['TRAVEL INFORMATION', 'flytap.com'],
        ['Boarding pass information', 'TRAVEL INFORMATION'],
        ['TRAVEL INFORMATION', 'A TAM deseja uma ótima'],
        'A TAP reserva-se o direito de alterar o seu lugar solicitado no check-in',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [],
    ];

    /** @var \HttpBrowser */
    protected $pdf;
    private $pdfText = '';
    private $segments = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->getPDFText($parser) === false) {
            $this->logger->info('PDF attachment not found or detect string not found');

            return false;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BPassPDF',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flytap.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["subject"]) && stripos($headers["subject"], 'flytap.com') !== false) {
            foreach ($this->reSubject as $re) {
                if (isset($headers["subject"]) && strpos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->getPDFText($parser);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];
        $text = $this->pdfText;
        $textForLoc = $this->pdfText;
        $recordLoc = $this->cutText('BOOKING REFERENCE', 'ETKT', $textForLoc);

        if (!empty($recordLoc) && preg_match('/((?:\d+[A-Z]+|[A-Z]+\d+)[\dA-Z]+)/', $recordLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match_all('#[[:alpha:]]{2,} / [[:alpha:]]{2,} (?:Mrs|Mr|Miss)#', $this->pdfText, $m)) {
            $it['Passengers'] = array_values(array_filter(array_unique($m[0])));
        }

        if (preg_match_all('#ETKT[^\S\n]+(\d{9,})\s+#', $this->pdfText, $m)) {
            $it['TicketNumbers'] = array_values(array_unique($m[1]));
        }

        foreach ($this->splitter('/\n([ ]*FLIGHT.+?FROM)/', $text) as $value) {
            $this->getSegmentInfo($value);
        }

        $it['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $this->segments)));

        return [$it];
    }

    private function getSegmentInfo($textSeg)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $re = '[Ss]eat.*?\s+(?<route>.*\s{2,}.*)\s+(?<AirlineName>[A-Z\d]{2})\s?(?<FlightNumber>\d{1,5})\s+';
        $re .= '(?<Seat>[A-Z\d]{2,4})\s+(?:|.*?)\s*(?:Terminal (?<DepTerm>\w{0,3}))?\s*(?:Terminal (?<ArrTerm>\w{1,3}))?';

        if (!empty($textSeg) && preg_match("/{$re}/i", $textSeg, $m)) {
            $route = array_values(array_filter(preg_split("#\s{2,}#", $m['route'])));

            if (count($route) == 2 && !strpos($route[0], "\n") && !strpos($route[1], "\n")) {
                $seg['DepName'] = trim($route[0]);
                $seg['ArrName'] = trim($route[1]);
            } elseif (count($route) == 1) {
                if (preg_match("#\s*(Lisbon Airport)\s+(.+)#", $route[0], $mat) || preg_match("#(.+)\s+(Lisbon Airport)#", $route[0], $mat)) {
                    $seg['DepName'] = $mat[1];
                    $seg['ArrName'] = $mat[2];
                }
            } elseif (count($route) > 2) {
                if (preg_match("#FROM.*[ ]+TO(?:[ ]+.*)?\n+(\s*(.+\n){1,3})([ ]+Terminal|\n{2,})#", $textSeg, $mat)) {
                    $textSegArr = explode("\n", $mat[1]);
                    $posD = strpos($textSeg, "FROM");
                    $posA = strpos($textSeg, "TO");
                    $seg['DepName'] = '';
                    $seg['ArrName'] = '';

                    foreach ($textSegArr as $value) {
                        $text1 = substr($value, $posD, $posA - $posD);
                        $text2 = substr($value, $posA);

                        if (!empty($text1)) {
                            $seg['DepName'] .= ' ' . trim($text1);
                        }

                        if (!empty($text2)) {
                            $seg['ArrName'] .= ' ' . trim($text2);
                        }
                    }
                }
            }
            $seg['AirlineName'] = $m['AirlineName'];
            $seg['FlightNumber'] = $m['FlightNumber'];
            $seg['Seats'][] = preg_match("#^\d{1,3}[A-Z]$#", $m['Seat']) ? $m['Seat'] : ''; // may be "INF",

            if (!empty($m['DepTerm']) && !empty($m['ArrTerm'])) {
                $seg['DepartureTerminal'] = $m['DepTerm'];
                $seg['ArrivalTerminal'] = $m['ArrTerm'];
            } elseif (!empty($m['DepTerm']) || !empty($m['ArrTerm'])) {
                $posD = strpos($textSeg, "FROM");
                $posA = strpos($textSeg, "TO");
                $textSegArr = explode("\n", $textSeg);

                foreach ($textSegArr as $value) {
                    $text1 = substr($value, $posD, $posA - $posD);
                    $text2 = substr($value, $posA);

                    if (!empty($text1) && strpos($text1, 'Terminal') !== false) {
                        $seg['DepartureTerminal'] = !empty($m['DepTerm']) ? $m['DepTerm'] : $m['ArrTerm'];

                        break;
                    }

                    if (!empty($text2) && strpos($text2, 'Terminal') !== false) {
                        $seg['ArrivalTerminal'] = !empty($m['DepTerm']) ? $m['DepTerm'] : $m['ArrTerm'];

                        break;
                    }
                }
            }
        }

        // it-4781420
        if (preg_match('/(\d+ [[:alpha:]]+ \d+)\s+(\d+ [[:alpha:]]+ \d+)\s+(?:BOARDING TIME\s+\d+:\d+)?.+?(\d+:\d+)\s+(\d+:\d+)/is', $textSeg, $m)) {
            $seg['DepDate'] = strtotime($m[1] . ', ' . $m[3], false);
            $seg['ArrDate'] = strtotime($m[2] . ', ' . $m[4], false);
        }

        if (!empty($textSeg) && (empty($seg['DepDate']) || empty($seg['ArrDate']))) {
            if (!empty($textSeg) && preg_match('/(\d+ [[:alpha:]]+ \d+).+?(\d+:\d+)/s', $textSeg, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ', ' . $m[2], false);
            }

            if (!empty($textSeg) && preg_match('/\d+:\d+.+?(\d+ [[:alpha:]]+ \d+).+?(\d+:\d+)/s', $textSeg, $m)) {
                $seg['ArrDate'] = strtotime($m[1] . ', ' . $m[2], false);
            }
        }

        if (isset($seg['FlightNumber']) && isset($seg['DepDate']) && isset($seg['ArrDate']) && $seg['ArrDate'] > $seg['DepDate']) {
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        } else {
            $this->logger->info('Parsing segment is failed');

            return false;
        }
        $finded = false;

        foreach ($this->segments as $key => $value) {
            if ($value['FlightNumber'] == $seg['FlightNumber'] && $value['AirlineName'] == $seg['AirlineName'] && $value['DepDate'] == $seg['DepDate']) {
                $this->segments[$key]['Seats'] = array_filter(array_merge($this->segments[$key]['Seats'], $seg['Seats']));
                $finded = true;
            }
        }

        if ($finded === false) {
            $this->segments[] = $seg;
        }

        return true;
    }

    private function cutText($start, $end, &$text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = stristr(stristr($text, $start), $end, true);
            $text = substr($text, stripos($text, $end) + strlen($end));

            return substr($cuttedText, strlen($start));
        }

        return false;
    }

    private function getPDFText(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//node()[contains(normalize-space(.),'Thank you for using Royal Air Maroc')]")->length > 0) {
            $this->logger->info('this mail from Royal Air Maroc. it parsed by airmaroc-BoardingPass.php');

            return false;
        }
        $pdfs = $parser->searchAttachmentByName('(?:boardingPass|CHECK IN ONLINE|CARTÃO (?:DE)?\s*EMBARQUE|TAP_Portugal_CheckIn|CARTÕES\s*(?:DE)? EMBARQUE|CARTAO|volt).*\.pdf');

        if (count($pdfs) < 1) {
            $this->logger->info('PDF attachment not found');

            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->reBody as $detectBody) {
            if (is_array($detectBody) && stripos($body, $detectBody[0]) !== false && stripos($body, $detectBody[1]) !== false) {
                $this->pdfText = $body;

                return true;
            } elseif (is_string($detectBody) && stripos($body, $detectBody) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
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
}
