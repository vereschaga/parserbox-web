<?php

namespace AwardWallet\Engine\ryanair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: fix different ryanair/it-5589573.eml with parser `It4027271`

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-5589573.eml, ryanair/it-5810009.eml, ryanair/it-65727034.eml"; // +2 bcdtravel(pdf)[it]

    public $reFrom = "@ryanair.com";
    public $reSubject = [
        "it" => "Itinerario di Viaggio Ryanair",
        "en" => "Ryanair",
    ];
    public $reBody = 'ryanair';
    public $reBody2 = [
        "it"  => ["CARTA", "IMBARCO"],
        "it2" => ["CA RTA D", "IMBA RCO"],
        "en"  => ["Boarding", "pass"],
        "en2" => ["Boarding", "Pass"],
        "no"  => ["BORD", "KARTE"],
        "ro"  => ["Îmbarcare", "Decolare"],
        "de"  => ["Seq", "Referenz"],
    ];

    public static $dictionary = [
        "it" => [
            "segments"                    => "#(CA ?RTA\s*.?D[ ]?.?IMBA ?RCO)#",
            "Imbarco\s+Posto\s+Posteriore"=> "Imbarco\s+Pos ?to(?:\s*\*)?\s+[^\d\s]+",
        ],
        "en" => [
            "segments"                    => "#(Boarding\s*pass|BOARDING\s*PASS|Boarding\s*Pass)#",
            "Imbarco\s+Posto\s+Posteriore"=> "Boarding\s+Seat\s*\*?\s+(?:Front|Back)[^\n]*",
            "Riferimento"                 => "Booking\s*ref",
            "Partenze"                    => "Departs",
        ],
        "no" => [
            "segments"                    => "#(BORDKARTE)#",
            "Imbarco\s+Posto\s+Posteriore"=> "Boarding\s+Seat\*?\s+(?:Vordere|Hintere)",
            "Riferimento"                 => "Referenz",
            "Partenze"                    => "Abflugzeit",
        ],
        // bcdtravel
        "ro" => [
            "segments"                    => "#(PRIORITY\s+REGULAR)#",
            "Imbarco\s+Posto\s+Posteriore"=> "Îmbarcare\s+Loc\*?\s+Ușadinfață",
            "Riferimento"                 => "Bookingref",
            "Partenze"                    => "Decolare",
        ],
        "de" => [
            "segments"                     => "#(BORDKA RTE|NON-PRIORITYQ)#",
            "Imbarco\s+Posto\s+Posteriore" => "Boarding\s+Seat\*?\s+(?:Vordere|Hintere)[^\n]*",
            "Riferimento"                  => "Referenz",
            "Partenze"                     => "Abflugzeit",
        ],
    ];

    public $lang = "";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                } else {
                    return null;
                }
            }

            foreach ($this->reBody2 as $lang => $re) {
                if ((strpos($text, $re[0]) !== false) && (strpos($text, $re[1]) !== false)) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }

            if (empty($this->lang)) {
                $this->logger->debug("can't detected lang");

                return null;
            }

            $this->parseEmail($email, $text);
            $class = explode('\\', __CLASS__);
            $email->setType(end($class) . ucfirst($this->lang));

            return $email;
        } else {
            return null;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*\.pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody2 as $lang => $reBody) {
                if ((stripos($text, $reBody[0]) !== false) && (stripos($text, $reBody[1]) !== false)) {
                    return true;
                }
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function parseEmail(Email $email, $plainText)
    {
        //$this->logger->error($plainText);
        $patterns = [
            'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        ];

        $m = $this->split($this->t('segments'), $plainText);

        $patterns['segment'] = '/'
            . '([A-Z]{3})\s*-\s*([A-Z]{3})\s*\|\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(.+?)\s*' . $this->t('Imbarco\s+Posto\s+Posteriore') . '\s+(.+?)\s+'
            . $this->t('Riferimento') . '\s+.+?\s+([A-Z\d]+).+?' . $this->t('Partenze') . '\s+(.+?)\s+(' . $patterns['time'] . ')\s+(' . $patterns['time'] . ')'
            . '/s';

        $patterns['segment2'] = "/"
            . "(?<depCode>[A-Z]{3})\s*-\s*(?<arrCode>[A-Z]{3})\s*\|\s*(?<aName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fNumber>\d+)\s+(?<traveller>.+?)\s*{$this->t('Imbarco\s+Posto\s+Posteriore')}"
            . "\s+(?<seat>.+?)\s+{$this->t('Riferimento')}\s+.+?\s+(?<confNumber>[A-Z\d]+).+?{$this->t('Partenze')}\s+(?<depDate>.+?)\s*\-*\s+(?<depTime>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?)\s+(?<arrDate>\d+\.\s*\w+\s+\d{4})\s*\-\s*(?<arrTime>\d+(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?)/su";

        $this->logger->debug($patterns['segment']);

        foreach ($m as $text) {
            $this->logger->debug($text);
            $this->logger->error('-------------------------------------------');

            if (preg_match($patterns['segment2'], $text, $v)) {
                $f = $email->add()->flight();

                $f->general()
                    ->traveller($v['traveller'], true)
                    ->confirmation($v['confNumber']);

                $s = $f->addSegment();

                $s->departure()
                    ->code($v['depCode'])
                    ->date(strtotime($this->normalizeDate($v['depDate'] . ' ' . $v['depTime'])));

                $s->arrival()
                    ->code($v['arrCode'])
                    ->date(strtotime($this->normalizeDate($v['arrDate'] . ' ' . $v['arrTime'])));

                $s->airline()
                    ->name($v['aName'])
                    ->number($v['fNumber']);

                $s->extra()
                    ->seat($v['seat'], true, true, $v['traveller']);
            } elseif (preg_match($patterns['segment'], $text, $v)) {
                $f = $email->add()->flight();

                $f->general()
                    ->traveller($v[5], true)
                    ->confirmation($v[7]);

                $s = $f->addSegment();

                $s->departure()
                    ->code($v[1])
                    ->date(strtotime($this->normalizeDate($v[8] . ' ' . $v[10])));

                $s->arrival()
                    ->code($v[2])
                    ->noDate();

                $s->airline()
                    ->name($v[3])
                    ->number($v[4]);

                $s->extra()
                    ->seat($v[6]);
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

    private function normalizeDate($str)
    {
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)\.\s*(\S{3})\s*(\d{4})\D+([\d\:]+)$#s",
            "#^(\d+)\s*\.?\s+(\S{3}\s+\d{4})$#",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                $condition1 = $segment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $uniqueSegment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'];
                $condition2 = $segment['DepCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['DepCode'] !== TRIP_CODE_UNKNOWN && $segment['DepCode'] === $uniqueSegment['DepCode']
                    && $segment['ArrCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['ArrCode'] !== TRIP_CODE_UNKNOWN && $segment['ArrCode'] === $uniqueSegment['ArrCode'];
                $condition3 = $segment['DepDate'] !== MISSING_DATE && $uniqueSegment['DepDate'] !== MISSING_DATE && $segment['DepDate'] === $uniqueSegment['DepDate'];

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($segment['Seats']) && !is_array($segment['Seats'])) {
                        $segment['Seats'] = (array) $segment['Seats'];
                    }

                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats']) && !is_array($uniqueSegments[$key]['Seats'])) {
                            $uniqueSegments[$key]['Seats'] = (array) $uniqueSegments[$key]['Seats'];
                        }

                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        //		$this->http->log('$r = '.print_r( $r,true));
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
