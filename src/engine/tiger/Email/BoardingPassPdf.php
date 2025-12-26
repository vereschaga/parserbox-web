<?php

namespace AwardWallet\Engine\tiger\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "tiger/it-12232490.eml";

    public $reFrom = "tiger.com";
    public $reBody = [
        'en' => ['Booking reference', 'Boarding starts'],
    ];
    public $reSubject = [
        'Tigerair Australia boarding pass',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*boardingPass\.pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            if (count($pdfs) > 1) {
                $this->http->Log('It is necessary to improve parser for emails with several attachments');

                return null;
            }

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->AssignLang($text);
                    $its[] = $this->parseEmail($text);
                }
            }
        }

        $a = explode('\\', __CLASS__);

        if (!empty($bps = $this->getBoardingPass($its))) {
            $result = [
                'parsedData' => ['Itineraries' => $its, 'BoardingPass' => $bps],
                'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            ];
        } else {
            $result = [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            ];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if ($this->AssignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail($textPDF)
    {
        $table = $this->re("#\n\n\s*(^ *[^\n].+?){$this->opt($this->t('Please note:'))}#ms", $textPDF);
        $table = $this->splitCols($table, $this->colsPos($table, 10));

        if (count($table) !== 3) {
            $this->http->Log("other format");

            return null;
        }

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Booking reference'))}\s+([A-Z\d]{5,})#", $table[2]);
        $it['Passengers'][] = $this->nice($this->re("#^\s*(.+){$this->opt($this->t('Seat number'))}#s", $table[2]));

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $seg['Seats'][] = $this->re("#{$this->opt($this->t('Seat number'))}\s+(\d+[a-z])#i", $table[2]);

        $date = $this->normalizeDate($this->re("#{$this->opt($this->t('Departure Date'))}\s+(.+)#", $table[0]));

        if (preg_match("#^\s*(.+?)\s+([A-Z]{3})\s+(\d+:\d+(?:(?i)\s*[ap]m)?)#", $table[0], $m)) {
            $seg['DepName'] = $m[1];
            $seg['DepCode'] = $m[2];

            if ($date) {
                $seg['DepDate'] = strtotime($m[3], $date);
            }
        }

        if (preg_match("#^\s*(.+?)\s+([A-Z]{3})\s+(\d+:\d+(?:(?i)\s*[ap]m)?)#", $table[1], $m)) {
            $seg['ArrName'] = $m[1];
            $seg['ArrCode'] = $m[2];

            if ($date) {
                $seg['ArrDate'] = strtotime($m[3], $date);
            }
        }

        $node = $this->re("#{$this->opt($this->t('Flight number'))}\s+(.+)#i", $table[1]);

        if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }
        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s+(\w+)\s+(\d{4})$#',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function getBoardingPass($its)
    {
        $bps = [];

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for checking in for your Tigerair flight'))}]")->length > 0
            && !empty($url = $this->http->FindSingleNode("//text()[{$this->contains($this->t('The mobile boarding pass is available at'))}]/following::a[1]/@href"))
        ) {
            foreach ($its as $it) {
                if (count($it['TripSegments']) == 1) {
                    $seg = $it['TripSegments'][0];

                    if (isset($seg['FlightNumber'], $seg['DepCode'], $seg['DepDate'], $it['RecordLocator'], $it['Passengers'])) {
                        $bps[] = [
                            'FlightNumber'       => $seg['FlightNumber'],
                            'DepCode'            => $seg['DepCode'],
                            'DepDate'            => $seg['DepDate'],
                            'RecordLocator'      => $it['RecordLocator'],
                            'Passengers'         => $it['Passengers'],
                            'BoardingPassURL'    => $url,
                            'AttachmentFileName' => '',
                        ];
                    }
                }
            }
        }

        return $bps;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
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

    private function ColsPos($table, $correct = 5)
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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
