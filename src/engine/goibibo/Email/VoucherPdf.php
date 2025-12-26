<?php

namespace AwardWallet\Engine\goibibo\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-10830030.eml, goibibo/it-10879558.eml";

    public $reFrom = 'noreply@goibibo.com';
    public $reSubject = [
        'Voucher for Booking ID',
        'Voucher for PaymentID',
    ];

    public $reBody = 'ibibo Group';
    public $reBody2 = [
        'en' => ['PAYMENT RECEIPT', 'PAYMENT INVOICE'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [],
    ];
    protected $its;
    protected $emailDate;
    protected $bodyPDF = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        foreach ($pdfs as $pdf) {
            $this->bodyPDF = \PDF::convertToText($parser->getAttachmentBody($pdf));
            //			$this->AssignLang($this->bodyPDF);
            $its[] = $this->parseEmail();
        }

        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');
        $body = '';

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        if (isset($headers['subject']) && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
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

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/[^\d.,]+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail()
    {
        $text = $this->bodyPDF;
        $it = ['Kind' => 'T'];
        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        $pos = strpos($text, 'Ref:');

        if (!empty($pos)) {
            $info = substr($text, $pos, 200);
        } else {
            $info = substr($text, $pos, 1000);
        }

        if (preg_match("#Ref:\s*(\w+)#", $info, $m)) {
            $it['TripNumber'] = $m[1];
        }

        if (preg_match("#\s{3,}Date:\s*(.+)#", $info, $m)) {
            $this->emailDate = strtotime($this->normalizeDate(str_replace('.', '', $m[1])));
        }

        if (empty($this->emailDate)) {
            $this->logger->info("year not found");

            return null;
        }

        $posBegin = strpos($text, 'Ticket');

        for ($i = 1; $i < 10; $i++) {
            if ($text[$posBegin - $i] != ' ') {
                $posBegin = $posBegin - $i + 1;

                break;
            }
        }

        if (!empty($posBegin)) {
            $posEnd = strpos($text, 'Total Price:', $posBegin);
        }

        if (empty($posBegin) || empty($posBegin)) {
            $this->logger->info("data is not found");

            return [];
        }

        // TotalCharge
        // Currency
        $totalText = substr($text, $posEnd + strlen('Total Price:'), strpos($text, "\n", $posEnd) - $posEnd - strlen('Total Price:'));
        $it["TotalCharge"] = $this->normalizePrice($totalText);
        $it["Currency"] = $this->currency($totalText);

        $info = substr($text, $posBegin, $posEnd - $posBegin);

        if (preg_match_all('#^.{5,25}  ([A-Za-z\d]{2})(\d{1,5})[ ]+(\d+:\d+ .+)$#m', $info, $flights)) {
            foreach ($flights[0] as $value) {
                $info = preg_replace("#" . $value . "#", "", $info);
            }
            $info = preg_replace("#\n(Onward|Return)\n#", "", $info);
        }

        // Passengers
        $passengers = preg_split("#\n{2}#", $info);

        foreach ($passengers as $pass) {
            if (strpos($pass, '-') > 0) {
                $table = $this->SplitCols($pass);

                if (count($table) > 3 && !empty(trim($table[0])) && !empty(trim($table[1])) && strpos($table[2], '-') > 0) {
                    $it["Passengers"][] = preg_replace("#\s+#", ' ', trim($table[1]));
                    $segments = explode("/", $table[2]);

                    foreach ($segments as $value) {
                        $airports = explode("-", $value);

                        foreach ($airports as $key => $value) {
                            if (isset($airports[$key + 1])) {
                                $routes[] = ['dep' => trim($value), "arr" => trim($airports[$key + 1])];
                            }
                        }
                    }
                }
            }
        }

        if (!empty($it["Passengers"])) {
            $it["Passengers"] = array_unique($it["Passengers"]);
        }

        if (!empty($routes)) {
            $routes = array_values(array_map("unserialize", array_unique(array_map("serialize", $routes))));
        }

        if (!isset($flights[0]) || empty($routes) || count($flights[0]) != count($routes)) {
            $this->logger->info("flight number or routes is different or empty");

            return [];
        }

        foreach ($routes as $key => $flight) {
            $seg = [];
            // FlightNumber
            $seg['FlightNumber'] = $flights[2][$key];

            // AirlineName
            $seg['AirlineName'] = $flights[1][$key];

            // DepCode
            $seg['DepCode'] = $routes[$key]["dep"];

            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate($flights[3][$key]));

            if ($seg['DepDate'] < $this->emailDate) {
                $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
            }

            // ArrCode
            $seg['ArrCode'] = $routes[$key]["arr"];

            // ArrDate
            $seg['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->emailDate);
        $in = [
            //July 15, 2017, 8:49 a.m.
            '#^\s*(\w+)\s*(\d+)[, ]+(\d{4})[, ]+(\d+:\d+\s*[apm])\s*$#i',
            //05:35 03 Feb
            '#^\s*(\d+:\d+)\s+(\d+)\s+(\w+)\s*$#',
        ];
        $out = [
            '$2 $1 $3 $4',
            '$2 $3 ' . $year . ' $1',
        ];
        $date = preg_replace($in, $out, $date);
        //		if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
        //			$monthNameOriginal = $m[0];
        //			if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
        //				return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
        //			}
        //		}
        return $date;
    }

    private function AssignLang($body)
    {
        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBodies) {
            foreach ($reBodies as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        $ds = 5;

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function currency($s)
    {
        $sym = [
            'Rs'=> 'INR',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
