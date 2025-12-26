<?php

namespace AwardWallet\Engine\opodo\Email;

class OpodoBConfirmation extends \TAccountChecker
{
    use \PriceTools;

    public $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        //		'de' => ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];
    public $mailFiles = "opodo/it-1902738.eml, opodo/it-5.eml, opodo/it-5089690.eml";
    public $reFrom = "opodo";
    public $Subj;
    public $reSubject = [
        "en" => "Opodo\s+flight\s+booking\s+confirmation",
    ];
    public $reBody = 'opodo';
    public $reBody2 = [
        "en" => "Booking reference",
    ];
    public $emaleDate;

    public $lang = "en";

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (preg_match("#{$re}#iu", $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->emaleDate = strtotime($parser->getDate());
        $this->Subj = $parser->getSubject();
        $this->http->FilterHTML = false;
        $itineraries = [];
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', html_entity_decode($this->http->Response["body"]));

        foreach ($this->reBody2 as $lang => $re) {
            if (mb_strpos($body, $re, 0, 'UTF-8') !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($itineraries, $totCharge);

        if (count($totCharge) > 0) {
            $result = [
                'emailType'  => 'OpodoBConfirmation',
                'parsedData' => [
                    'Itineraries' => $itineraries,
                    'TotalCharge' => $totCharge,
                ],
            ];
        } else {
            $result = [
                'emailType'  => 'OpodoBConfirmation',
                'parsedData' => [
                    'Itineraries' => $itineraries,
                ],
            ];
        }

        return $result;
    }

    public function parseHtml(&$itineraries, &$totCharge)
    {
        $pax = $this->http->FindNodes("//*[contains(text(),'" . $this->t('Name(s) of traveller(s)') . "')]/ancestor::tr[1]/following::tr[1]/td[1]/text()[normalize-space(.)]");
        $recLocs = array_unique(array_map("trim", $this->http->FindNodes("//*[contains(text(),'" . $this->t('Booking reference:') . "')]", null, "#\:\s+([A-Z\d]+)$#")));

        foreach ($recLocs as $recLoc) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $recLoc;
            $it['Passengers'] = $pax;
            $xpathFlight = "//*[contains(text(),'" . $this->t('Booking reference:') . "') and contains(text(),'{$recLoc}')]/ancestor::table[1]/following::table[1]//*[contains(text(),'" . $this->t('Inbound') . "') or contains(text(),'" . $this->t('Outbound') . "')]/ancestor::table[1]";
            $fligths = $this->http->XPath->query($xpathFlight);

            if ($fligths->length == 0) {
                $this->http->Log("segments root not found: {$xpathFlight}", LOG_LEVEL_NORMAL);
            }

            foreach ($fligths as $root) {
                $seg = [];
                $date = $this->http->FindSingleNode(".//tr[contains(.,'" . $this->t('Inbound') . "') or contains(.,'" . $this->t('Outbound') . "')]/td[2]", $root);

                $node = $this->http->FindSingleNode(".//tr[contains(.,'" . $this->t('Departing') . "')]/td[2]", $root);

                if (preg_match("#(?:(.*?Terminal\s*\w*),?)?\s*(.+?)\s*\(\s*([A-Z]{3})\s*\).?\s+(\w.+?)?\s*(\d+:\d+).*#", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $seg['DepartureTerminal'] = $m[1];
                    }
                    $seg['DepDate'] = strtotime($this->enDate($date . ' ' . $m[5]));
                    $seg['DepName'] = $m[4] . ' - ' . $m[2];
                    $seg['DepCode'] = $m[3];
                }
                $node = $this->http->FindSingleNode(".//tr[contains(.,'" . $this->t('Arriving') . "')]/td[2]", $root);

                if (preg_match("#(?:(.*?Terminal\s*\w*),?)?\s*(.+?)\s*\(\s*([A-Z]{3})\s*\).?\s+(\w.+?)?\s*(\d+:\d+).*#", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $seg['ArrivalTerminal'] = $m[1];
                    }
                    $seg['ArrDate'] = strtotime($this->enDate($date . ' ' . $m[5]));
                    $seg['ArrName'] = $m[4] . ' - ' . $m[2];
                    $seg['ArrCode'] = $m[3];
                }
                $node = $this->http->FindSingleNode(".//tr[descendant::img]/td[2]", $root);

                if (stripos($node, $this->t('Baggage')) !== false) {
                    $node = substr($node, 0, stripos($node, $this->t('Baggage')) - 1);
                }

                if (preg_match("#\(\s*([A-Z\d]{2})\s*(\d+)\s*\)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                if (preg_match("#" . $this->t('Aircraft type') . "\s*-\s*(\S+)#", $node, $m)) {
                    $seg['Aircraft'] = $m[1];
                }

                if (preg_match("#((?:" . $this->t('Economy|Bussines|First') . ").*)#i", $node, $m)) {
                    $seg['Cabin'] = $m[1];
                }
                $seg['Duration'] = $this->http->FindSingleNode(".//tr[contains(.,'" . $this->t('Duration') . "')]/td[2]", $root);

                $seg = array_filter($seg);
                $it['TripSegments'][] = $seg;
            }
            $it = array_filter($it);
            $itineraries[] = $it;
        }
        $total = $this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t('Total') . "']/ancestor::tr[1]/td[3]");

        if (count($recLocs) > 1) {
            $totCharge = $this->total($total, 'Amount');
        } else {
            $itineraries[0]['TotalCharge'] = $this->cost($total);
            $itineraries[0]['Currency'] = $this->currency($total);
        }
    }

    protected function enDate($nodeForDate)
    {
        $month = $this->monthNames['en'];
        $monthLang = $this->monthNames[$this->lang];
        preg_match("#(?<day>[\d]+)\s+(?<month>\S+)\s+(?<year>\d+)(?:\s+(?<time>\d+:\d+))?#", $nodeForDate, $chek);
        $res = $nodeForDate;

        for ($i = 0; $i < 12; $i++) {
            if (mb_strtolower(substr($monthLang[$i], 0, 3)) == mb_strtolower(substr(trim($chek['month']), 0, 3))) {
                if (isset($chek['time']) && !empty($chek['time'])) {
                    $res = $chek['day'] . ' ' . $month[$i] . ' ' . $chek['year'] . ' ' . $chek['time'];
                } else {
                    $res = $chek['day'] . ' ' . $month[$i] . ' ' . $chek['year'];
                }
            }
        }

        return $res;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
