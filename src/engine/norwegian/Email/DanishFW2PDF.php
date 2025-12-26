<?php

namespace AwardWallet\Engine\norwegian\Email;

class DanishFW2PDF extends \TAccountChecker
{
    public $mailFiles = "norwegian/it-5589852.eml";
    public $reBody = "norwegian";
    public $reBody2 = [
        "da" => ["DIN BOOKINGREFERENCE ER", "Flyinfo"],
    ];
    public $reSubject = [
        'Rejsedokumenter',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'da' => [
        ],
    ];
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $text = '';

            foreach ($pdfs as $pdf) {
                //				if (($text .= \PDF::convertTotext($parser->getAttachmentBody($pdf))) !== null) {
                if (($text .= text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->dateYear = date('Y', strtotime($parser->getDate()));

        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->getParam($text, '#' . $this->t('DIN BOOKINGREFERENCE ER') . '\s*:\s*([A-Z\d]+)#');
        $this->result['Passengers'] = explode("\n", $this->getParam($text, '#' . $this->t('Passagerer') . '\s*\n(.*?)\n\s*' . $this->t('Yderligere') . '#'));
        $this->result['TripSegments'] = $this->parseSegments($this->findСutSection($text, $this->t('Flyinfo'), $this->t('Passagerer')));

        return [
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
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertTotext($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]norwegian\.dk/", $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseSegments($text)
    {
        $segment = [];
        $regStr = "(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)[-\s]+(?<dayFly>\d+)\s*(?<monthFly>\S+?)\s*(?<yearFly>\d+)";
        $regStr .= "\s+(?<timeDep>\d+:\d+)\s+(?<DepName>.+?)\n(?<Cabin>.+?)\n.+?\s*(?<timeArr>\d+:\d+)\s+(?<ArrName>.+?)\n";

        if (preg_match_all("#{$regStr}#", $text, $matches)) {
            foreach ($matches[0] as $i => $m) {
                $seg = [];
                $seg['AirlineName'] = $matches['AirlineName'][$i];
                $seg['FlightNumber'] = $matches['FlightNumber'][$i];
                $seg['DepName'] = $matches['DepName'][$i];
                $seg['ArrName'] = $matches['ArrName'][$i];

                $date = strtotime($matches['dayFly'][$i] . ' ' . $matches['monthFly'][$i] . ' ' . $matches['yearFly'][$i]);
                $seg['DepDate'] = strtotime($matches['timeDep'][$i], $date);
                $seg['ArrDate'] = strtotime($matches['timeArr'][$i], $date);
                $seg['Cabin'] = $matches['Cabin'][$i];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $segment[] = $seg;
            }
        }

        return $segment;
    }

    protected function getParam($subject, $pattern = null)
    {
        if (preg_match($pattern, $subject, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
