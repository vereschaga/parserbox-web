<?php

namespace AwardWallet\Engine\tamair\Email;

class ETicketReceiptText extends \TAccountChecker
{
    public $mailFiles = "tamair/it-5511022.eml";
    public $reSubject = [
        "Your Electronic Ticket Receipt",
    ];
    public $reFrom = [
        "nao-responda@tam.com.br",
    ];
    public $reProvider = "latam";
    public $date;
    public $lang;
    public $reBody = [
        "pt" => ["Comprando a tarifa", "Tarifa Aerea:"],
    ];
    public static $dictionary = [
        'pt' => [
            'RecordLocator'   => 'CÓDIGO\s+DA\s+RESERVA',
            'ReservationDate' => 'Data\s+de\s+emissão',
            'Passengers'      => 'NOME', //etc. full it if need
        ],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "pt" => [
            "jan"      => 0, "janeiro" => 0,
            "fev"      => 1, "fevereiro" => 1,
            "março"    => 2, "mar" => 2,
            "abr"      => 3, "abril" => 3,
            "maio"     => 4, "mai" => 4,
            "jun"      => 5, "junho" => 5,
            "julho"    => 6, "jul" => 6,
            "ago"      => 7, "agosto" => 7,
            "setembro" => 8, "set" => 8,
            "out"      => 9, "outubro" => 9,
            "novembro" => 10, "non" => 10,
            "dez"      => 11, "dezembro" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : text($parser->getHTMLBody());
        $this->AssignLang($text);
        $this->result['Kind'] = 'T';

        if (preg_match('/' . $this->t('RecordLocator') . '\s*:\s+([A-Z\d]+)/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/' . $this->t('ReservationDate') . '\s*:\s+(\d+)\s*(\S+?)\s*(\d+)\s*/', $text, $matches)) {
            $this->result['ReservationDate'] = strtotime($this->normalizeDate($matches[1] . " " . $matches[2] . " " . $matches[3]));

            if ($this->result['ReservationDate']) {
                $this->date = $this->result['ReservationDate'];
            }
        }

        if (preg_match('/' . $this->t('Passengers') . '\s*:\s+(.+?)\n/', $text, $matches)) {
            $this->result['Passengers'][] = $matches[1];
        }

        if (preg_match('/NÚMERO\s+DO\s+E-TICKET\s*:\s+([A-Z\d\s]+?)\n/', $text, $matches)) {
            $this->result['TicketNumbers'][] = $matches[1];
        }

        if (preg_match('/Total\s*:\s+([A-Z]{3})\s+(\d[\,\d\s]*?\d*?)\n/', $text, $matches)) {
            $this->result['TotalCharge'] = str_replace(" ", "", str_replace(",", ".", $matches[2]));
            $this->result['Currency'] = $matches[1];
        }
        $subtext = $this->findСutSection($text, "Comprando a tarifa", "Tarifa Aerea:");
        $this->parseSegments($subtext);

        return [
            'emailType'  => 'ETicketReceiptText',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : text($parser->getHTMLBody());

        if (stripos($text, $this->reProvider) === false) {
            return false;
        }

        foreach ($this->reBody as $re) {
            if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    protected function parseSegments($text)
    {
        $segments = preg_split('/DE/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $text) {
            if (strpos($text, 'Data:') !== false) {
                $this->result['TripSegments'][] = $this->parseSement($text);
            }
        }
    }

    protected function parseSement($text)
    {
        $segment = [];

        if (preg_match("/Data\s*:\s+(\d+\s*\S+)/", $text, $matches)) {
            $date = strtotime($this->normalizeDate($matches[1]));
        }

        if (preg_match("/Vôo\s*:\s+([A-Z\d]{2})\s*(\d+)/", $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        if (preg_match("/Operado\s+por\s+(.+?)\n/", $text, $matches)) {
            $segment['Operator'] = $matches[1];
        }

        if (preg_match("/Saída\s*:\s+(\d+:\d+)\s+(.+?)\s+([A-Z]{3})\n/", $text, $matches)) {
            $segment['DepDate'] = strtotime($matches[1], $date);

            if ($segment['DepDate'] < $this->date) {
                $segment['DepDate'] = strtotime("+1 year", $segment['ArrDate']);
            }
            $segment['DepName'] = $matches[2];
            $segment['DepCode'] = $matches[3];
        }

        if (preg_match("/Chegada\s*:\s+(\d+:\d+)\s+(.+?)\s+([A-Z]{3})\n/", $text, $matches)) {
            $segment['ArrDate'] = strtotime($matches[1], $date);

            if ($segment['ArrDate'] < $this->date) {
                $segment['ArrDate'] = strtotime("+1 year", $segment['ArrDate']);
            }
            $segment['ArrName'] = $matches[2];
            $segment['ArrCode'] = $matches[3];
        }

        if (preg_match("/Classe\s*:\s+(.+?)(?:\s+\(\s*([A-Z]{1,2})\s*\))?\n/", $text, $matches)) {
            $segment['Cabin'] = $matches[1];

            if (isset($matches[2]) && !empty($matches[2])) {
                $segment['BookingClass'] = $matches[2];
            }
        }

        if (preg_match("/Aeronave\s*:\s+(.+?)\n/", $text, $matches)) {
            $segment['Aircraft'] = $matches[1];
        }

        return $segment;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+(\S+?)\s+(\d+)$#",
            "#^(\d+)\s*(\S+?)$#",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $year",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function AssignLang($body)
    {
        foreach ($this->reBody as $lang => $re) {
            if (stripos($body, $re[0]) !== false && stripos($body, $re[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
