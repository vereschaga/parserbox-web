<?php

namespace AwardWallet\Engine\tripair\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "tripair/it-6213293.eml";

    public $reFrom = 'tripair';
    public $reBody = [
        'pt' => ['Número da reserva', 'Data de emissão'],
    ];
    public $reSubject = [
        'Tripair.com - Flight Booking Information',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'pt' => [
            'TripNum'    => 'Reservaconfirmada',
            'RecLoc'     => 'Númerodareserva',
            'ReservDate' => 'Datadeemissão',
            'Adult'      => 'ADULTO',
            'Duration'   => ['D', 'URAÇÃ', 'O', ':'],
            'Flight'     => ['C', 'OMPA', 'NHIA', 'A', 'ÉREA', ':'],
            'Aircraft'   => ['A', 'VIÃO'],
            'Cabin'      => ['T', 'ARIFA', 'P', 'RETEN', 'DIDA'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $NBSP = chr(194) . chr(160);
        $html = str_replace($NBSP, ' ', html_entity_decode($html));
        $this->pdf->SetBody($html);

        $body = text($this->pdf->Response['body']);
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'PDF_' . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf[0])));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[starts-with(translate(.,' ',''),'" . $this->t('RecLoc') . "')]/following::p[1]", null, true, '#([A-Z\d]+)#');
        $it['TripNumber'] = $this->pdf->FindSingleNode("//p[starts-with(translate(.,' ',''),'" . $this->t('TripNum') . "')]", null, true, '#-\s*([A-Z\d]+)#');
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("//p[contains(translate(.,' ',''),'" . $this->t('ReservDate') . "')]/following::p[1]")));
        $it['Passengers'] = $this->pdf->FindNodes("//p[contains(.,'" . $this->t('Adult') . "')]//text()[not(contains(.,'" . $this->t('Adult') . "'))]");

        $rule = $this->getRule('Duration');
        $xpath = "//p[{$rule[0]}][1]/following::p[{$rule[1]}]";
        //p[normalize-space(.)='D' and normalize-space(./following::p[1])='URAÇÃ' and normalize-space(./following::p[2])='O'and normalize-space(./following::p[3])=':']/following::p[4]
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./preceding::p[contains(.,':') and string-length(.)>1][2]/preceding::p[1]", $root)));

            $node = $this->pdf->FindSingleNode("./preceding::p[contains(.,':') and string-length(.)>1][2]", $root) . ' ' . $this->pdf->FindSingleNode("./preceding::p[contains(.,':') and string-length(.)>1][2]/following::p[1][not(contains(.,':'))]", $root);

            if (preg_match("#(\d+:+\d+)\s+(.+?)\s+\(([A-Z]{3})\)\s*(?:,?\s*(.*terminal.*)|.*)$#i", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['DepName'] = $m[2];
                $seg['DepCode'] = $m[3];

                if (isset($m[4]) && !empty($m[4])) {
                    $seg['DepartureTerminal'] = $m[4];
                }
            }
            $node = $this->pdf->FindSingleNode("./preceding::p[contains(.,':') and string-length(.)>1][1]", $root) . ' ' . $this->pdf->FindSingleNode("./preceding::p[contains(.,':') and string-length(.)>1][1]/following::p[1][not(contains(.,':'))]", $root);

            if (preg_match("#(\d+:+\d+)\s+(.+?)\s+\(([A-Z]{3})\)\s*(?:,?\s*(.*terminal.*)|.*)$#i", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = $m[3];

                if (isset($m[4]) && !empty($m[4])) {
                    $seg['ArrivalTerminal'] = $m[4];
                }
            }
            $seg['Duration'] = $this->pdf->FindSingleNode(".", $root);
            $rule = $this->getRule('Flight');
            $node = implode(" ", $this->pdf->FindNodes("./following::p[{$rule[0]}][1]/following::p[{$rule[1]}]//text()", $root));

            if (preg_match("#.+\(([A-Z\d]{2})\s*(\d+)\)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $rule = $this->getRule('Aircraft');
            $seg['Aircraft'] = implode(" ", $this->pdf->FindNodes("./following::p[{$rule[0]}][1]/following::p[{$rule[1]}]//text()", $root));
            $rule = $this->getRule('Cabin');
            $seg['Cabin'] = implode(" ", $this->pdf->FindNodes("./following::p[{$rule[0]}][1]/following::p[{$rule[1]}]//text()", $root));

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getRule($field)
    {
        $w = $this->t($field);

        if (!is_array($w)) {
            $w = [$w];
        }

        $rule = '1';

        foreach ($w as $i => $v) {
            switch ($i) {
                case 0:
                    $rule = "normalize-space(.)='{$v}'";

                    break;

                default:
                    $rule .= " and normalize-space(./following::p[{$i}])='{$v}'";

                    break;
            }
        }

        return [$rule, count($w)];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+\s+\w+\s+\d+)$#u',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(mb_strtolower(preg_replace($in, $out, $date)));

        return $str;
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
}
