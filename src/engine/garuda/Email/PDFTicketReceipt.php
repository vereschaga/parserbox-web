<?php

namespace AwardWallet\Engine\garuda\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class PDFTicketReceipt extends \TAccountChecker
{
    public $mailFiles = "garuda/it-1723089.eml, garuda/it-1723090.eml, garuda/it-46377985.eml, garuda/it-6137156.eml, garuda/it-6151948.eml, garuda/it-9094207.eml";
    private $date;
    /** @var \HttpBrowser */
    private $pdf;
    /** @var \HttpBrowser */
    private $pdfComplex;
    private $textPdf;
    private $PdfFileNamePattern = '(?:.*pdf|Your\s*Electronic\s*Ticket\s*Receipt.*)';

    private static $detectsProv = [
        'srilankan' => [
            'prov' => 'SRILANKAN.com',
            'text' => [
                'At check-in you must show the document used as a reference at the time of booking',
            ],
        ],
        'kestrelflyer' => [
            'prov' => 'airmauritius.com',
            'text' => [
                'Air Mauritius wishes you',
                'please contact Air Mauritius',
            ],
        ],
        'garuda' => [
            'prov' => 'WWW.GARUDA-INDONESIA.COM',
            'text' => [
                'PT GARUDA INDONESIA',
            ],
        ],
        'finnair' => [
            'prov' => 'WWW.FINNAIR.COM',
            'text' => [
                'Electronic Ticket Receipt',
            ],
        ],
        'tarom' => [
            'prov' => 'TAROM',
            'text' => [
                'Electronic Ticket Receipt',
            ],
        ],
        'sata' => [
            'prov' => 'SATA AZORES AIRLINES',
            'text' => [
                'Electronic Ticket Receipt',
            ],
        ],
        'mea' => [
            'prov' => 'MIDDLE EAST AIRLINES',
            'text' => [
                'Electronic Ticket Receipt',
            ],
        ],
    ];

    // Standard Methods

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProv);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                (stripos($headers['from'], 'ticket@garuda-indonesia.com') !== false)
                || (stripos($headers['from'], '@finnair.') !== false)
                || (stripos($headers['from'], '@airmauritius.com') !== false)
            );
    }

    public function detectEmailFromProvider($from)
    {
        return (stripos($from, '@garuda-indonesia.com') !== false)
            || (stripos($from, '@finnair.') !== false)
            || (stripos($from, '@airmauritius.com') !== false)
            || (stripos($from, '@tarom.ro') !== false)
        ;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->PdfFileNamePattern);

        foreach ($pdfs as $pdf) {
            if ($this->getProviderCode($pdf, $parser)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName($this->PdfFileNamePattern) as $pdf) {
            $this->textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->pdfComplex = clone $this->http;
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $NBSP = chr(194) . chr(160);
            $htmlPdf = str_replace($NBSP, ' ', html_entity_decode($htmlPdf));
            $this->pdfComplex->SetEmailBody($htmlPdf);
            $htmlPdf = pdfHtmlHtmlTable($htmlPdf);
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $htmlPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);

            $this->date = null;
            $dates = array_values(array_filter($this->pdfComplex->FindNodes("//text()[normalize-space() = 'Issuing Airline and date']/following::text()[normalize-space()][position() < 5]",
                null, "/^\s*:.*?\D(\d{1,2}[[:alpha:]]+\d{2})\s*$/")));

            if (count($dates) == 1) {
                $date = strtotime(preg_replace("/^(\d{1,2})([[:alpha:]]+)(\d{2})\s*$/", '$1 $2 $3', $dates[0]));

                if (!empty($date)) {
                    $this->date = $date;
                }
            }

            if ($it = $this->ParsePdf()) {
                $res = [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'PDFTicketReceipt',
                ];

                if ($prov = $this->getProviderCode($pdf, $parser)) {
                    $res['providerCode'] = $prov;
                }

                return $res;
            }
        }

        return null;
    }

    private function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function ParsePdf()
    {
        $textBody = text($this->pdf->Response['body']);
        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/Booking\s+Reference\s*:\s+([A-Z\d]{5,7})/i', $textBody, $matches)) {
            $it['RecordLocator'] = $matches[1];
        } else {
            return null;
        }
        $it['TripSegments'] = [];

        $segments = $this->pdf->XPath->query('//tr[./td[contains(normalize-space(.),"Operated by")] and not(.//tr)]/preceding-sibling::tr[not(contains(.,"Terminal")) and contains(.,":")][1]');
        $segmentsComplex = $this->pdfComplex->XPath->query("//text()[contains(translate(normalize-space(),'0123456789','dddddddddd'),'dddd')]/ancestor::p[1][./following-sibling::p[1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]]");

        if ($segmentsComplex->length > 0 && $segments->length !== $segmentsComplex->length) {
            $this->logger->debug("other format - detect segments");

            return false;
        }

        if ($segmentsComplex->length == 0) {
            // for parse emails with date without years
            $segmentsComplex = $segments;
        }

        $accounts = [];

        foreach ($segments as $i=>$segment) {
            $segmentComplex = $segmentsComplex->item($i);
            $seg = [];
            $seg['DepName'] = preg_replace("#\s+#", ' ', implode("\n", $this->pdf->FindNodes('./td[string-length(normalize-space(.))>2][1]//text()[normalize-space()]', $segment)));
            $seg['ArrName'] = preg_replace("#\s+#", ' ', implode("\n", $this->pdf->FindNodes('./td[string-length(normalize-space(.))>2][2]//text()[normalize-space()]', $segment)));

            if (preg_match('/^([A-Z\d]{2})(\d+)\b/', $seg['ArrName'])) {
                // not easy algorithm but looks like worker
                unset($seg['DepName']);
                unset($seg['ArrName']);
                // FE: it-46377985.eml
                for ($k = 1; $k <= 4; $k++) {
                    if (isset($seg['DepName'])) {
                        break;
                    }
                    $node = $this->pdfComplex->FindSingleNode("./preceding-sibling::p[$k]", $segmentComplex);

                    if ($k == 1 && preg_match("#^[A-Z]{1,2}$#", $node)) {
                        $seg['BookingClass'] = $node;
                    } elseif ($k == 1 && preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)\s+([A-Z]{1,2})$#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                        $seg['BookingClass'] = $m[3];
                    } elseif ($k == 2 && preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)$#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    } elseif ($k == 2) {
                        $seg['ArrName'] = $node;
                    } elseif ($k == 3 && !isset($seg['ArrName'])) {
                        $seg['ArrName'] = $node;
                    } else {
                        $seg['DepName'] = $node;
                    }
                }
                $date = strtotime(str_replace("-", ' ', $this->pdfComplex->FindSingleNode(".", $segmentComplex)));
                $depTime = $this->pdfComplex->FindSingleNode("./following-sibling::p[1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]",
                    $segmentComplex);

                if (!empty($depTime)) {
                    $seg['DepDate'] = strtotime($depTime, $date);
                }

                $arrTime = $this->pdfComplex->FindSingleNode("./following-sibling::p[2][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]",
                    $segmentComplex);

                if (!empty($arrTime)) {
                    $seg['ArrDate'] = strtotime($arrTime, $date);
                } else {
                    $seg['ArrDate'] = MISSING_DATE;
                }
            } else {
                if (preg_match('/^([A-Z\d]{2})(\d+)$/',
                    $this->pdf->FindSingleNode('./td[string-length(normalize-space(.))>2][3]', $segment), $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                    $seg['BookingClass'] = $this->pdf->FindSingleNode('./td[string-length(normalize-space(.))>2][3]/following-sibling::td[normalize-space()!=""][1]',
                        $segment, false, "#^[A-Z]{1,2}$#");
                } elseif (preg_match('/^([A-Z\d]{2})(\d+)\s+([A-Z]{1,2})$/',
                    $this->pdf->FindSingleNode('./td[string-length(normalize-space(.))>2][3]', $segment), $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                    $seg['BookingClass'] = $matches[3];
                } elseif (preg_match('/\s([A-Z\d]{2})(\d+)$/',
                    $this->pdf->FindSingleNode('./td[string-length(normalize-space(.))>2][2]', $segment), $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                    $seg['ArrName'] = str_replace(" " . $seg['AirlineName'] . $seg['FlightNumber'], "",
                        $seg['ArrName']);
                    $seg['BookingClass'] = $this->pdf->FindSingleNode('./td[string-length(normalize-space(.))>2][2]/following-sibling::td[normalize-space()!=""][1]',
                        $segment, false, "#^[A-Z]{1,2}$#");
                } elseif (preg_match('/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s*(?<cabin>\w+)$/',
                    $this->pdf->FindSingleNode('./td[string-length(normalize-space(.))>2][3]', $segment), $matches)) {
                    $seg['AirlineName'] = $matches['aName'];
                    $seg['FlightNumber'] = $matches['fNumber'];
                    $seg['Cabin'] = $matches['cabin'];
                }

                $headSegment = $this->re("/Itinerary\n*(\s+From\s+To\s+Flight.+)\n/", $this->textPdf);
                $segmentText = $this->re("/\n+(\s+.+" . $seg['AirlineName'] . $seg['FlightNumber'] . ".+\n(?:.+\n){1,10}\s+Operated by.+)/", $this->textPdf);

                if (!empty($headSegment) && !empty($segmentText)) {
                    $segmentTextWithHead = $headSegment . "\n" . $segmentText;
                    $segmentTable = $this->splitCols($segmentTextWithHead);

                    $depTerminal = $this->re("/Terminal\s+(\S+)/", $segmentTable[0]);

                    if (!empty($depTerminal)) {
                        $seg['DepartureTerminal'] = $depTerminal;
                    }

                    $arrTerminal = $this->re("/Terminal\s+(\S+)/", $segmentTable[1]);

                    if (!empty($arrTerminal)) {
                        $seg['ArrivalTerminal'] = $arrTerminal;
                    }

                    if (empty($seg['BookingClass']) && preg_match("//", $segmentTable[2], $m)) {
                        $bookingCode = $this->re("/Flight\n+\w+\s*\d*\n+\(([A-Z]{1,2})\)/", $segmentTable[2]);

                        if (!empty($bookingCode)) {
                            $seg['BookingClass'] = $bookingCode;
                        }
                    }
                }

                if (preg_match("/\d+PC\s+(?<seat>\d+[A-Z])$/", $segment->nodeValue, $m)) {
                    $seg['Seats'] = [$m['seat']];
                }

                if (preg_match('/(?<date>\d{1,2}[^\d]{3})\s*(?<timedep>\d{1,2}:\d{2})\s*(?<timearr>\d{1,2}:\d{2})/',
                    $segment->nodeValue, $matches)) {
                    $date = $this->normalizeDate($matches['date']);
                    $seg['DepDate'] = strtotime($matches['timedep'], $date);
                    $seg['ArrDate'] = strtotime($matches['timearr'], $date);

                    if ($overnight = $this->pdf->FindSingleNode('(./following-sibling::tr[./td[normalize-space(.)="Operated by"]]/following-sibling::tr[string-length(normalize-space(.))>1])[1]',
                        $segment, true, '/Arrival\s+Day\s*[+]\s*(\d{1,2})/i')
                    ) {
                        $seg['ArrDate'] = strtotime('+' . $overnight . ' days', $seg['ArrDate']);
                    }
                } else {
                    // FE: it-46377985.eml
                    $node = $this->pdf->FindSingleNode("./td[string-length(normalize-space(.))>2][4][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dddd')]",
                        $segment);

                    if (!empty($node)) {
                        $date = strtotime(str_replace("-", ' ', $node));
                        $depTime = $this->pdf->FindSingleNode("./td[string-length(normalize-space(.))>2][5][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]",
                            $segment);

                        if (!empty($depTime)) {
                            $seg['DepDate'] = strtotime($depTime, $date);
                        }

                        $arrTime = $this->pdf->FindSingleNode("./td[string-length(normalize-space(.))>2][6][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')]",
                            $segment);

                        if (!empty($arrTime)) {
                            $seg['ArrDate'] = strtotime($arrTime, $date);
                        } else {
                            $seg['ArrDate'] = MISSING_DATE;
                        }
                    }
                }
            }

            if (isset($seg['AirlineName'],$seg['FlightNumber']) && preg_match("#\n([^\n]+ {$seg['AirlineName']}{$seg['FlightNumber']} .+?Marketed by[^\n]+)#s", $this->textPdf, $matches)) {
                $table = $this->splitCols($matches[1], $this->colsPos(strstr($matches[1], "\n", true)));

                if (count($table) > 2) {
                    if (preg_match("#Terminal (\w+)#", $table[0], $matches)) {
                        $seg['DepartureTerminal'] = $matches[1];
                    }

                    if (preg_match("#Terminal (\w+)#", $table[1], $matches)) {
                        $seg['ArrivalTerminal'] = $matches[1];
                    }
                }
            }

            $seg['Operator'] = $this->pdf->FindSingleNode('(./following-sibling::tr/td[./preceding-sibling::td[normalize-space(.)="Operated by"] and ./following-sibling::td[normalize-space(.)="Marketed by"] and string-length(normalize-space(.))>2])[1]', $segment);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        if (preg_match_all("/Frequent flyer number\s+(\d{10,})/", $this->textPdf, $m)) {
            $it['AccountNumbers'] = array_unique($m[1]);
        }

        $it['Passengers'] = [];

        if (preg_match('/Name\s*:\s+(.*)/', $textBody, $matches)) {
            $it['Passengers'][] = preg_replace("/(?:\s+\(ADT\)|\s+Mrs|\s+Mr)/", "", $matches[1]);
        }
        $it['TicketNumbers'] = [];

        if (preg_match('/Ticket\s+number(?:\s+Tour Code)?\s*:\s+([-\d\s]+?)\s*\n/ms', $textBody, $matches)) {
            $it['TicketNumbers'][] = $matches[1];
        }

        if (preg_match('/Total\s+Amount\s*:\s+([^\d]{3})\s+([.\d]+)/', $textBody, $matches)) {
            $it['Currency'] = trim($matches[1]);
            $it['TotalCharge'] = $this->priceNormalize($matches[2]);

            if (preg_match('/Fare\s*:\s+' . $it['Currency'] . '\s+([.\d]+)/', $textBody, $matches)) {
                $it['BaseFare'] = $matches[1];
            } elseif (preg_match("#Fare Equivalent\s+:\s(?<cur>[A-Z]{3})\s*(?<sum>\d[\d\.,]+)#", $this->textPdf,
                $m)) {
                $it['BaseFare'] = $m['sum'];
            }
        }

        if (!empty($node = $this->pdfComplex->FindSingleNode("//text()[normalize-space()='Taxes']/ancestor::p[1]/following-sibling::p[string-length(normalize-space())>1][1]",
            null, false, "#^[A-Z]{3}\s*\d[\d\.,]+\s*\w+$#"))
        ) {
            for ($i = 1; $i < 20; $i++) {
                $node = $this->pdfComplex->FindSingleNode("//text()[normalize-space()='Taxes']/ancestor::p[1]/following-sibling::p[string-length(normalize-space())>1][{$i}]");

                if (preg_match("#^Total\b#i", $node) > 0) {
                    break;
                }

                if (preg_match("#^(?<cur>[A-Z]{3})\s*(?<sum>\d[\d\.,]+)\s*(?<name>\w+)$#", $node, $m)) {
                    if (isset($it['Currency']) && $it['Currency'] !== $m['cur']) {
                        continue;
                    } elseif (!isset($it['Currency'])) {
                        $it['Currency'] = $m['cur'];
                    }
                    $it['Fees'][] = ['Name' => $m['name'], 'Charge' => $this->priceNormalize($m['sum'])];
                }
            }
        } else {
            // FE: it-46377985.eml
            if (preg_match("#Fare\s*:\s*[^\n]+\n[ ]*Taxes\s*:\s*(.+)\n[ ]*Total\s+Amount#s", $this->textPdf, $m) || preg_match("#Fare Equivalent\s*:\s*[^\n]+\n[ ]*[A-z]+?\s\/\sTaxes\s*:\s*(.+)\n[ ]*[A-z\s]+\s\/\sTotal\s+Amount#s", $this->textPdf, $m)) {
                if (preg_match_all("#\b(?<cur>[A-Z]{3})\s*(?<sum>\d[\d\.,]+)\s*(?<name>\w+)\b#", $m[1], $v, PREG_SET_ORDER)) {
                    foreach ($v as $m) {
                        if (isset($it['Currency']) && $it['Currency'] !== $m['cur']) {
                            continue;
                        } elseif (!isset($it['Currency'])) {
                            $it['Currency'] = $m['cur'];
                        }
                        $it['Fees'][] = ['Name' => $m['name'], 'Charge' => $this->priceNormalize($m['sum'])];
                    }
                }
            }
        }

        return $it;
    }

    private function getProviderCode($pdf, \PlancakeEmailParser $parser)
    {
        $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
        $text = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $text);

        foreach (self::$detectsProv as $prov => $detects) {
            foreach ($detects['text'] as $detect) {
                if (false !== stripos($text, $detect) && false !== stripos($text, $detects['prov'])) {
                    return $prov;
                }
            }
        }

        return false;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date IN = '.print_r( $str,true));
        // $this->logger->debug('$this->date = '.print_r( $this->date,true));
        $year = date("Y", $this->date);
        $in = [
            // 13Feb
            '#^\s*(\d+)([[:alpha:]]+)\s*$#',
        ];
        // %year% - for date without year and without week
        $out = [
            '$1 $2 %year%',
        ];

        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$date Replace = '.print_r( $str,true));

        if (!empty($this->date) && strpos($str, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $str, $m)) {
            $str = EmailDateHelper::parseDateRelative($m['date'], $this->date);

            if (!empty($str) && !empty($m['time'])) {
                return strtotime($m['time'], $str);
            }

            return $str;
        } else {
            return null;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
