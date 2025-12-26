<?php

namespace AwardWallet\Engine\lotpair\Email;

class YourEMDreceipt extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-5598096.eml, lotpair/it-5604598.eml, lotpair/it-5604744.eml";

    public $reBody = [
        'en' => ['Document Number', 'LOT POLISH AIRLINES'],
    ];
    public $reSubject = [
        'Your EMD receipt',
    ];
    public $lang = '';
    public $pdf;
    public $date;
    public static $dict = [
        'en' => [
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
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName("Your\s*EMD\s*Receipt.*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $html = str_replace($NBSP, ' ', html_entity_decode($html));
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "YourEMDreceipt",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('Your\s*EMD\s*Receipt.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@lot.com") !== false;
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('Booking Reference') . "')]/ancestor::p[1]", null, true, "#:\s+([A-Z\d]+)#");

        $it['Passengers'][] = $this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('Document Number') . "')]/ancestor::p[1]/following::p[2]");
        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('Fare') . "')]/ancestor::p[1]/following::p[1]"));
        $it['TotalCharge'] = $tot['Total'];
        $it['Currency'] = $tot['Currency'];

        $i = 1;
        $numstr = 1;
        $fly = '1';
        $flight = [];

        while (substr(trim($this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('CPN') . "')]/ancestor::p[1]/following::p[{$i}]")), 0, 3) != $this->t('ICW') && $i < 100) {
            if (trim($this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('CPN') . "')]/ancestor::p[1]/following::p[{$i}]")) == "{$numstr}") {
                if ($i > 1) {
                    $flight[] = $fly;
                }
                $fly = "{$numstr}";
                $numstr++;
            } else {
                $fly .= ' ' . trim($this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('CPN') . "')]/ancestor::p[1]/following::p[{$i}]"));
            }
            $i++;
        }

        if ($fly != '1') {
            $flight[] = $fly;
        }

        foreach ($flight as $str) {
            $seg = [];
            //1 Pre Reserved SeatAssignment JFK WAW 29Nov ,22:30 30Nov ,12:45 LO 027 L OK
            //1 Prepaid BagUpto23Kg And158Cm JFK WAW 30Jun ,22:30 1 Pc LO 027 M OK
            if (preg_match("#.+?([A-Z]{3})\s+([A-Z]{3})\s+(\d+\s*\S+\s*,?\s*\d+:\d+)\s+(\d+\s*\S+\s*,?\s*\d+:\d+)?\s*.*?\s*([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1,2})#", $str, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));

                if (isset($m[4]) && !empty(trim($m[4]))) {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[4]));
                } else {
                    $seg['ArrDate'] = MISSING_DATE;
                }
                $seg['AirlineName'] = $m[5];
                $seg['FlightNumber'] = $m[6];
                $seg['BookingClass'] = $m[7];

                if ($seg['DepDate'] < $this->date) {
                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);

                    if ($seg['ArrDate'] != MISSING_DATE) {
                        $seg['ArrDate'] = strtotime("+1 year", $seg['ArrDate']);
                    }
                }
            }
//       		elseif (preg_match("##",$str,$m)){
//       		}
            //echo $str."<br><br>";
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);

        $in = [
            '#(\d+)\s*(\S+?)\s*,?\s*(\d+:\d+)#',
        ];
        $out = [
            '$1 $2 ' . $year . ' $3',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
