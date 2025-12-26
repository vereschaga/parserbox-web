<?php

namespace AwardWallet\Engine\iberia\Email;

class ConfReservationPlain extends \TAccountChecker
{
    public $mailFiles = "iberia/it-6019785.eml";

    public $reBody = [
        'pt' => ['A sua viagem', 'Código de confirmação'],
        'es' => ['Tu viaje', 'Código de confirmación'],
    ];
    public $reSubject = [
        'pt' => ['Confirmação de reserva'],
        'es' => ['Confirmación de reserva'],
    ];
    public $lang = 'pt';
    public static $dict = [
        'pt' => [
            'Confirmation code:' => 'Código de confirmação:',
            'Depart'             => 'Saída',
            'Operated by'        => 'Voo operado por',
            'segment'            => '([A-Z\d]+\s*\d+\s+Saída(?:.+?\n){5})',
        ],
        'es' => [
            'Confirmation code:' => 'Código de confirmación:',
            'Depart'             => 'Salida',
            'Operated by'        => 'Vuelo operado por',
            'segment'            => '([A-Z\d]+\s*\d+\s+Salida(?:.+?\n){5})',
            'Travel document'    => 'Viajar documentado',
        ],
    ];

    protected $result = [];

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
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = text($this->http->Response['body']);

        if (!$this->AssignLang($body)) {
            return null;
        }

        $itineraries = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => [$itineraries]],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = text($this->http->Response['body']);

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject) && isset($headers['subject'])) {
            foreach ($this->reSubject as $lang => $reSubject) {
                if (stripos($headers['subject'], $reSubject[0]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "iberia") !== false;
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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function parseEmail($plainText)
    {
        $this->result['Kind'] = 'T';
        $this->recordLocator($this->findСutSection($plainText, $this->t('Confirmation code:'), $this->t('Depart')));

        $this->parseSegments($this->findСutSection($plainText, $this->t('Confirmation code:'), $this->t('Travel document')));

        return $this->result;
    }

    protected function recordLocator($recordLocator)
    {
        if (preg_match('#^\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            $this->result['RecordLocator'] = $m[1];
        }
    }

    protected function parseSegments($plainText)
    {
        $segmentsSplitter = $this->t('segment');

        if (preg_match_all('/' . $segmentsSplitter . '/', $plainText, $value)) {
            foreach ($value[1] as $v) {
                $this->result['TripSegments'][] = $this->iterationSegments(html_entity_decode($v));
            }
        }
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match('#^\s*([A-Z\d]{2})\s*(\d+)#u', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        if (preg_match('#Operado\s+por\s+(.+?)\s+(\d+:\d+)h?,\s+\w+\s+(\d+.+?\d+)\s+(.+?)\s+\(([A-Z]{3})\)\s*(?:(.*?Terminal.+?))?\s+(\d+:\d+)h?,\s+\w+\s+(\d+.+?\d+)\s+(.+?)\s+\(([A-Z]{3})\)\s*(?:(.*?Terminal.+?))?\s+(.*?)\s*(?:Duración|$)#u', $value, $m)) {
            $segment['Operator'] = $m[1];
            $segment['DepCode'] = $m[5];
            $segment['DepName'] = $m[4];

            if (isset($m[6]) && !empty($m[6])) {
                $segment['DepartureTerminal'] = $m[6];
            }

            $segment['ArrCode'] = $m[10];
            $segment['ArrName'] = $m[9];

            if (isset($m[11]) && !empty($m[11])) {
                $segment['ArrivalTerminal'] = $m[11];
            }
            $segment['DepDate'] = strtotime($this->normalizeDate($m[3] . ' ' . $m[2]));
            $segment['ArrDate'] = strtotime($this->normalizeDate($m[8] . ' ' . $m[7]));
            $segment['Cabin'] = $m[12];
        }

        return $segment;
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
                if (mb_stripos($body, $reBody[0]) !== false && mb_stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+\w+\s+\d{4})\s+(\d+:\d+)$#u",
            "#^(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})\s+(\d+:\d+)$#u",
        ];
        $out = [
            "$1, $2",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }
}
