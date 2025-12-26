<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-10240470.eml, saudisrabianairlin/it-10390952.eml, saudisrabianairlin/it-11613927.eml, saudisrabianairlin/it-1975123.eml, saudisrabianairlin/it-1975124.eml, saudisrabianairlin/it-5046561.eml, saudisrabianairlin/it-6606925.eml, saudisrabianairlin/it-6662443.eml, saudisrabianairlin/it-783894027.eml";

    public $reBody = [
        'ar' => 'وصول الرحلة',
        'en' => 'Flight Arrival',
        'de' => 'Ankunft um',
    ];
    public $reBodyPDF = [
        'en' => ['Boarding Pass', 'FROM'],
    ];

    public $lang = 'en';
    public $code = '';

    public static $dict = [
        'en' => [
            //Plain
            "#PassClass#" => "#[\n\s>]+(?<Pax>.+?)[:\s\-]+(?:Checked\s+In|Enregistré)(?:[\s-]+Ticket number:\s*(?<ETKT>[\d\-]+))?#iu",
            "#Flight#"    => "#[\n\s>]+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[\s\-]+(.+?)\s+\(\s*([A-Z]{3})\s*\)[\s\-]+(.+?)\s+\(\s*([A-Z]{3})\s*\)[\s\-]+(\d.+)\n((?:.+\n){3})#",
            "#ArrDate#"   => "#[\n\s>]+Flight Arrival:\s+(\d+:\d+)#",
        ],
        'ar' => [
            //Plain
            "#PassClass#" => "#[\n\s>]+(?<Pax>(?:M|D|C).+?): تمت إجراءات قبول الركاب[\s-]+(?<BClass>[A-Z]{1,2})[\s-]+رقم التذكرة : (?<ETKT>[A-Z\d]+)]#",
            "#Flight#"    => "#[\n\s>]+الرحلة :([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[\s\-]+(.+?)\s+\(\s*([A-Z]{3})\s*\)[\s\-]+(.+?)\s+\(\s*([A-Z]{3})\s*\)[\s\-]+(\d+.+)\n((?:.+\n){3})#",
            "#ArrDate#"   => "#وصول الرحلة : (\d+:\d+)#",
        ],
        'de' => [
            //Plain
            "#PassClass#" => "#[\n\s>]+(?<Pax>.+?)[:\s\-]+Eingecheckt(?:[\s-]+Flugscheinnummer:\s*(?<ETKT>[\d\-]+)])?#",
            "#Flight#"    => "#[\n\s>]+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[\s\-]+(.+?)\s+\(\s*([A-Z]{3})\s*\)[\s\-]+(.+?)\s+\(\s*([A-Z]{3})\s*\)[\s\-]+(\d.+)\n((?:.+\n){3})#",
            "#ArrDate#"   => "#[\n\s>]+Ankunft um:\s+(\d+:\d+)#",
        ],
    ];

    private static $headers = [
        'saudisrabianairlin' => [
            'from' => ['@saudia.com', '@saudiairlines.com'],
            'subj' => [
                'Check-in Confirmation',
                'Boarding Pass Confirmation',
            ],
        ],
        'egyptair' => [
            'from' => ['noreply@egyptair.com'],
            'subj' => [
                'Egyptair Boarding Pass Confirmation',
            ],
        ],
        'amadeus' => [
            'from' => ['noreply@amadeus.com'],
            'subj' => [
                'Boarding Pass Confirmation',
            ],
        ],
    ];

    private $bodies = [
        'saudisrabianairlin' => [
            'ar' => ['الرجاء العثور بطاقة صعود الطائرة المغلفة مع التفاصيل التالية'],
            'en' => ['Thank you for choosing Saudi Arabian Airlines Mobile Check-In'],
        ],
        'egyptair' => [
            'en' => ['using Egyptair online check-in', 'for choosing Egyptair Mobile Check-In'],
            'de' => ['Online-Check-in von Egyptair nutzen'],
        ],
        'amadeus' => [
            'en' => ['Thank you for using Royal Air Maroc online check-in service'],
        ],
    ];

    private $year = '';

    private $xpath = [
        'noDisplay' => 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]',
    ];

    private $patterns = [
        'date' => '(?:\b\d{1,2}\s+[[:alpha:]]+\s+\d{4}\b|\b[[:alpha:]]+\s*,\s*\d{1,2}\s+[[:alpha:]]+\b)', // 02 Jan 2016  |  Sat, 9 Nov
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    public function getProviderBody(): ?string
    {
        foreach ($this->bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                foreach ($search as $value) {
                    if (strpos($this->http->Response['body'], $value) !== false) {
                        $this->code = $code;

                        return $code;
                    }
                }
            }
        }

        return null;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $body = strip_tags(str_replace(['<br>', '<br/>'], ["\n", "\n"], $body));

        $this->year = $this->http->FindSingleNode("//text()[contains(translate(.,' ', ''),'©SaudiaAirlines')]", null, true, "/^(\d{4})\s*©/");
        $its = [];
        $typeParsing = "";
        $pdfs = $parser->searchAttachmentByName(".*(boardingPass|Boarding Pass).*pdf");

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignLang($textPdf, true)) {
                continue;
            }

            $its = array_merge($its, $this->parseEmailPDF($textPdf, $body));
            $typeParsing = "PDF";
        }

        if (count($its) === 0 && !empty($body)) {
            $its = $this->parseEmailPlain($body);
            $typeParsing = "Plain";
        }

        $result = [
            'emailType'  => 'BoardingPass' . $typeParsing . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];

        if (!empty($this->getProviderBody())) {
            $result['providerCode'] = $this->code;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (!empty($body) && $this->assignLang($body)) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf, true)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom) {
                $this->code = $code;
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $cnt = count(self::$dict);

        return $cnt * 2;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (
                ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang))
                || ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'fr'))
            ) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmailPDF($text, $body): array
    {
        $its = [];
        $itbody = $this->parseEmailPlain($body);
        $this->assignLang($body);
        $segments = $this->splitter("/^([ ]*Boarding Pass)$/m", $text);

        foreach ($segments as $stext) {
            $Passengers = $this->normalizeTraveller($this->re("/Boarding Pass\s+(.+)/", $stext));

            $seg = [];

            $posStart = strpos($stext, 'FROM');
            $posEnd = stripos($stext, 'Travel Information');

            $flight = substr($stext, $posStart, $posEnd - $posStart);

            $pos = $posStart - strrpos(substr($stext, 0, $posStart), "\n");
            $flight = str_pad('', $pos) . $flight;

            $pos = strpos($flight, 'TO');
            $flight = array_filter(explode("\n", $flight));
            $left = '';
            $right = '';

            foreach ($flight as $row) {
                $left .= trim(substr($row, 0, $pos)) . "\n";
                $right .= trim(substr($row, $pos)) . "\n";
            }

            $dateDep = $dateArr = $timeDep = $timeArr = null;

            $pattern = "(?<name>[\s\S]{2,}?)\n*(?<term>\n.*Terminal.*|\n[ ]*-)?\n+[ ]*(?<date>{$this->patterns['date']})\s+(?<time>{$this->patterns['time']})";
            $patternDateShort = "/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>\d{1,2}\s*[[:alpha:]]+|[[:alpha:]]+\s*\d{1,2})$/u"; // Sat, 9 Nov    |    Sat, Nov 9

            if (preg_match("/FROM\s+{$pattern}/u", $left, $m)) {
                $seg['DepName'] = trim(preg_replace("#\s+#", ' ', $m['name']));
                $terminalDep = empty($m['term']) ? null : trim(str_ireplace('terminal', '', $m['term']));

                if ($terminalDep && $terminalDep !== '-') {
                    $seg['DepartureTerminal'] = $terminalDep;
                }

                if (preg_match($patternDateShort, $m['date'], $m2) && $this->year) {
                    $weekDateNumber = WeekTranslate::number1($m2['wday']);
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($m2['date'] . ' ' . $this->year, $weekDateNumber);
                } elseif (preg_match("/^.{4,}\b\d{4}$/", $m['date'])) {
                    $dateDep = strtotime($m['date']);
                }

                $timeDep = $m['time'];
            }

            if ($dateDep && $timeDep) {
                $seg['DepDate'] = strtotime($timeDep, $dateDep);
            }

            if (preg_match("/TO\s+{$pattern}/u", $right, $m)) {
                $seg['ArrName'] = trim(preg_replace("#\s+#", ' ', $m['name']));
                $terminalArr = empty($m['term']) ? null : trim(str_ireplace('terminal', '', $m['term']));

                if ($terminalArr && $terminalArr !== '-') {
                    $seg['ArrivalTerminal'] = $terminalArr;
                }

                if (preg_match($patternDateShort, $m['date'], $m2) && $this->year) {
                    $weekDateNumber = WeekTranslate::number1($m2['wday']);
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($m2['date'] . ' ' . $this->year, $weekDateNumber);
                } elseif (preg_match("/^.{4,}\b\d{4}$/", $m['date'])) {
                    $dateArr = strtotime($m['date']);
                }

                $timeArr = $m['time'];
            }

            if ($dateArr && $timeArr) {
                $seg['ArrDate'] = strtotime($timeArr, $dateArr);
            }

            if (preg_match("/ GATE(?:\s+ZONE)?.*\n+[ ]*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)(?:[ ]+(?<seat>\d{1,3}[A-Z])(?: |\n|$)|[ ]{2}|\n|$)/", $stext, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m['seat'])) {
                    $seg['Seats'][] = $m['seat'];
                }
            }

            $posNextStep = stripos($stext, 'Next Steps');

            $info = substr($stext, $posEnd, $posNextStep - $posEnd);

            if (preg_match("/\n(.* )(?:BOOKING\s+REFERENCE|BOOKING\n)/", $info, $m)) {
                $info = explode("\n", $info);
                $pos = strlen($m[1]);
                $infoText = '';

                foreach ($info as $row) {
                    $infoText .= trim(substr($row, $pos)) . "\n";
                }

                $RecordLocator = $this->re("/BOOKING\s+REF(?:ERENCE)?[:\s]+([A-Z\d]{5,10})(?:\n+[ ]*TICKET|\s*$)/", $infoText);
                $TicketNumbers = $this->re("/TICKET[:\s]+ETKT\s+({$this->patterns['eTicket']})(?:\n|$)/", $infoText);
                $AccountNumbers = $this->re("#FREQUENT FLYER\s+([A-Z\d ]{5,})#", $infoText);
                $Cabin = $this->re("/CLASS OF TRAVEL\s+(.+)(?:\n+[ ]*BOOKING|\s*$)/", $infoText);

                if ($Cabin) {
                    $seg['Cabin'] = $Cabin;
                }
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return [];
            }

            if (!empty($itbody) && isset($itbody[0]['TripSegments'])) {
                foreach ($itbody[0]['TripSegments'] as $key => $value) {
                    if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                            && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                            && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                        $seg['DepCode'] = $value['DepCode'];
                        $seg['ArrCode'] = $value['ArrCode'];

                        break;
                    }
                }
            }

            // it-783894027.eml
            $xpathCodes = $timeDep && $timeArr ? "//tr[ not({$this->xpath['noDisplay']}) and *[1][contains(normalize-space(),'{$timeDep}')] and *[3][contains(normalize-space(),'{$timeArr}')] ]/preceding-sibling::tr[normalize-space()][1]" : '';

            if (empty($seg['DepCode']) && $xpathCodes) {
                $seg['DepCode'] = $this->http->FindSingleNode($xpathCodes . "/*[1]", null, true, "/^[A-Z]{3}$/");
            }

            if (empty($seg['ArrCode']) && $xpathCodes) {
                $seg['ArrCode'] = $this->http->FindSingleNode($xpathCodes . "/*[3]", null, true, "/^[A-Z]{3}$/");
            }

            if (empty($seg['DepCode'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (empty($seg['ArrCode'])) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumbers;
                    }

                    if (isset($AccountNumbers)) {
                        $its[$key]['AccountNumbers'][] = $AccountNumbers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            if (!empty($seg['Seats'])) {
                                $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            }
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }

                if (isset($AccountNumbers)) {
                    $it['AccountNumbers'][] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                if (isset($its[$key]['Passengers'])) {
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                }

                if (isset($its[$key]['TicketNumbers'])) {
                    $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                }

                if (isset($its[$key]['AccountNumbers'])) {
                    $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                }
            }
        }

        return $its;
    }

    private function parseEmailPlain($text): array
    {
        $this->assignLang($text);
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        if (preg_match($this->t('#PassClass#'), $text, $m)) {
            if (!empty($m['Pax'])) {
                $m['Pax'] = $this->normalizeTraveller($m['Pax']);
                $it['Passengers'][] = $m['Pax'];
            }

            if (!empty($m['BClass'])) {
                $seg['BookingClass'] = $m['BClass'];
            }

            if (!empty($m['ETKT'])) {
                $it['TicketNumbers'][] = $m['ETKT'];
            }
        }

        if (preg_match_all($this->t('#Flight#'), $text, $m)) {
            foreach ($m[0] as $key => $value) {
                $seg = [];
                $seg['AirlineName'] = $m[1][$key];
                $seg['FlightNumber'] = $m[2][$key];
                $seg['DepName'] = $m[3][$key];
                $seg['DepCode'] = $m[4][$key];
                $seg['ArrName'] = $m[5][$key];
                $seg['ArrCode'] = $m[6][$key];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[7][$key]));

                if (!empty($m[8][$key]) && preg_match($this->t('#ArrDate#'), $m[8][$key], $mat) && !empty($seg['DepDate'])) {
                    $seg['ArrDate'] = strtotime($mat[1], $seg['DepDate']);
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }

    private function normalizeDate($date)
    {
        //	    $this->logger->info("Date: {$date}");
        $in = [
            '#^\s*(\d+)\s+(\w+)\s+(\d{4})\s*\-\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#iu',
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*\-\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$3-$2-$1 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body, $ispdf = false): bool
    {
        if ($ispdf) {
            if (isset($this->reBodyPDF)) {
                foreach ($this->reBodyPDF as $lang => $reBody) {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        } elseif (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($re, $text): array
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
}
