<?php

namespace AwardWallet\Engine\tamair\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "tamair/it-7776973.eml, tamair/it-7915259.eml, tamair/it-9872832.eml, tamair/it-9872852.eml";

    public $reFrom = "checkintam@tam.com.br";
    public $reBody = [
        'pt' => ['O Check-in de sua viagem', 'Voo'],
        'en' => ['Check-in for your flight', 'Flight'],
    ];
    public $reBodyPDF = [
        'pt' => ['CARTÃO DE EMBARQUE', 'HORA LOCAL'],
        'en' => ['BOARDING PASS', 'LOCAL TIME'],
    ];
    public $reSubject = [
        'Seus documentos do Check-in',
        'Your Check-in documents',
    ];
    public $lang = '';
    public $subject;
    public $pdf;
    public $pdfNamePattern = ".*pdf|CARTO_DE_EMBARQUE.*|BOARDING_PASS.*";
    public static $dict = [
        'pt' => [
            "BOARDING PASS"  => "CARTÃO DE EMBARQUE",
            "PASSENGER NAME" => "NOME DO PASSAGEIRO",
            "BOOKING CODE"   => "CÓDIGO DE RESERVA",
            "TICKET NUMBER"  => "NÚMERO DO BILHETE",
            "flightReg"      => "AEROPORTO\s+EMBARQUE",
            "OPERATED"       => "OPERADO",
            "LOCAL\s+TIME"   => "HORA\s+LOCAL",
            //HTML
            "Flight" => "Voo",
            "From"   => "De",
            "To"     => "Para",
        ],
        'en' => [
            "flightReg" => ["AIRPORT\s+GATE", "AIRPORT\s+DOOR"],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $type = "";
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));

            if ($this->pdf->XPath->query("//text()[{$this->eq($this->t('BOARDING PASS'))}]")->length > 0) {
                $body = text($this->pdf->Response['body']);
                $this->AssignLang($body, true);
                $its = $this->parseEmailPDF($body);
                $type = "Pdf";
            }
        }

        if (count($its) === 0) {
            $body = $this->http->Response['body'];
            $this->AssignLang($body);

            $its = $this->parseEmail();
            $type = "Html";
        }
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . $type . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'tam.com')] | //img[contains(@src,'lan.com')]")->length > 0) {
            $body = $this->http->Response['body'];

            return $this->AssignLang($body);
        }
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text, true);
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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])) {
                            $new = "";

                            if (isset($tsJ['Seats'])) {
                                $new .= "," . $tsJ['Seats'];
                            }

                            if (isset($tsI['Seats'])) {
                                $new .= "," . $tsI['Seats'];
                            }
                            $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                            $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                            $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                $its[$j]['TicketNumbers'] = array_merge($its[$j]['TicketNumbers'], $its[$i]['TicketNumbers']);
                $its[$j]['TicketNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['TicketNumbers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmailPDF($textPDF)
    {
        $its = [];
        $nodes = $this->splitter("#({$this->opt($this->t('PASSENGER NAME'))})\s+#", $textPDF);

        foreach ($nodes as $node) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->re("#{$this->opt($this->t('BOOKING CODE'))}\s*([A-Z\d]+)#", $node);
            $it['Passengers'][] = $this->re("#{$this->opt($this->t('PASSENGER NAME'))}\s*(.+)#", $node);
            $it['TicketNumbers'][] = $this->re("#{$this->opt($this->t('TICKET NUMBER'))}\s*([\d]+)#", $node);

            $seg = [];

            if (preg_match("#{$this->opt($this->t('flightReg'))}\s+[^\n]+\s+([^\n]+)\s+([A-Z\d]{2})\s*(\d+)\s+.*?\s*(\d+\/?[A-Z])#", $node, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];
                $seg['Seats'] = $m[4];
            }

            if (preg_match("#\n\s*([A-Z]{3})\n\s*([A-Z]{3})\n.+?(\d+:\d+)\n\s*(\d+:\d+)#s", $node, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $depTime = $m[3];
                $arrTime = $m[4];
            }

            if (preg_match("#{$this->opt($this->t('OPERATED'))}\s+\d+:\d+\s+\d+:\d+\n([^\n]*TERMINAL[^\n]*)\n(.+)\n.+?\d{4}\n.+?\d{4}\n(.*?[A-Z\d]{2}\d+)#", $node, $m)) {
                $seg['DepartureTerminal'] = trim(str_replace("TERMINAL", '', $m[1]));

                if (preg_match("#(.+?)\s*([A-Z\d]{2}\d+)?$#", $m[2] . ' ' . $m[3], $v)) {
                    $seg['Operator'] = $v[1];
                }
            } elseif (preg_match("#{$this->opt($this->t('OPERATED'))}\s+\d+:\d+\s+\d+:\d+\n([^\n]*TERMINAL[^\n]*)\n(.+)#", $node, $m)) {
                $seg['DepartureTerminal'] = trim(str_replace("TERMINAL", '', $m[1]));

                if (preg_match("#(.+?)\s*([A-Z\d]{2}\d+)?$#", $m[2], $v)) {
                    $seg['Operator'] = $v[1];
                }
            } elseif (preg_match("#([^\n]*TERMINAL[^\n]*)\s*\d+\/?[A-Z]\s+{$this->opt($this->t('OPERATED'))}\s+\d+:\d+\s+\d+:\d+\s+([^\n]+?)\n.+?\d{4}\n.+?\d{4}\n(.*?[A-Z\d]{2}\d+)#", $node, $m)) {
                $seg['DepartureTerminal'] = trim(str_replace("TERMINAL", '', $m[1]));

                if (preg_match("#(.+?)\s*([A-Z\d]{2}\d+)?$#", $m[2] . ' ' . $m[3], $v)) {
                    $seg['Operator'] = $v[1];
                }
            }

            if (preg_match("#{$this->opt($this->t('LOCAL\s+TIME'))}\n\s*(\d+\s+\w+\s+\d+)\n\s*([^\n]*TERMINAL.+?)\n\s*(\d+\s+\w+\s+\d+)#s", $node, $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));

                if (isset($depTime)) {
                    $seg['DepDate'] = strtotime($depTime, $seg['DepDate']);
                }
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[3]));

                if (isset($arrTime)) {
                    $seg['ArrDate'] = strtotime($arrTime, $seg['ArrDate']);
                }

                if (preg_match("#([^\n]*TERMINAL[^\n]*)\n([^\n]*TERMINAL[^\n]*)#", $m[2], $v)) {
                    $seg['DepartureTerminal'] = trim(str_replace("TERMINAL", '', $v[1]));
                    $seg['ArrivalTerminal'] = trim(str_replace("TERMINAL", '', $v[2]));
                } elseif (isset($seg['DepartureTerminal']) && trim($seg['DepartureTerminal']) != trim($m[2])) {
                    $seg['ArrivalTerminal'] = trim(str_replace("TERMINAL", '', $m[2]));
                }
            }

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }
        $its = $this->mergeItineraries($its);

        return $its;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#\(([A-Z\d]+)\)#", $this->subject);
        $it['Passengers'] = array_values(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Flight'))}]/ancestor::table[{$this->contains($this->t('From'))}][1]/descendant::tr[1]/following-sibling::tr[2]//td[count(descendant::td)=0 and not(preceding-sibling::td)]")));
        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::table[{$this->contains($this->t('From'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $node = implode("-", $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('From'))}]/following::text()[normalize-space(.)!=''][position()<3]", $root));

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            }
            $node = implode("-", $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][position()<3]", $root));

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }
            $seg['DepDate'] = strtotime($this->normalizeDate(implode(" ", $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('From'))}]/following::text()[normalize-space(.)!=''][position()=3 or position()=4]", $root))));
            $seg['ArrDate'] = strtotime($this->normalizeDate(implode(" ", $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][position()=3 or position()=4]", $root))));
            $seg['Seats'] = $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[2]//td[count(descendant::td)=0 and preceding-sibling::td]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
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

    private function AssignLang($body, $pdf = false)
    {
        if ($pdf) {
            $reBodyM = $this->reBodyPDF;
        } else {
            $reBodyM = $this->reBody;
        }

        foreach ($reBodyM as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

        return '(?:' . implode("|", $field) . ')';
    }
}
