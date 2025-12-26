<?php

// bcdtravel

namespace AwardWallet\Engine\sncb\Email;

class ConfirmationBookingNl extends \TAccountChecker
{
    public $mailFiles = "";

    public $reBody = [
        'nl' => 'Prijs en reserveringsgegevens',
    ];
    public $reSubject = [
        '#.+?\s+boekingscode:\s+[A-Z\d]+,\s+vertrekdatum:\s+\d+\/\d+\/\d+#', //nl
    ];
    public $lang = '';
    public static $dict = [
        'nl' => [
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
        "fr" => [
            "janv"     => 0, "janvier" => 0,
            "févr"     => 1, "fevrier" => 1, "février" => 1,
            "mars"     => 2,
            "avril"    => 3, "avr" => 3,
            "mai"      => 4,
            "juin"     => 5,
            "juillet"  => 6, "juil" => 6,
            "août"     => 7, "aout" => 7,
            "sept"     => 8, "septembre" => 8,
            "oct"      => 9, "octobre" => 9,
            "novembre" => 10, "nov" => 10,
            "decembre" => 11, "décembre" => 11, "déc" => 11,
        ],
        "nl" => [
            "januari"   => 0, "jan" => 0,
            "februari"  => 1, "feb" => 1,
            "mrt"       => 2, "maart" => 2,
            "april"     => 3, "apr" => 3,
            "mei"       => 4,
            "juni"      => 5, "jun" => 5,
            "juli"      => 6, "jul" => 6,
            "augustus"  => 7, "aug" => 7,
            "september" => 8, "sep" => 8,
            "oktober"   => 9, "okt" => 9,
            "november"  => 10, "nov" => 10,
            "december"  => 11, "dec" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $lng => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lng;

                break;
            }
        }

        $its = $this->parseEmail();

        $node = str_replace("€", "EUR", $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t('Totaal') . "']/ancestor::tr[1]", null, true, "#" . $this->t('Totaal') . "\s+(.+)#"));

        if (preg_match("#([A-Z]{3})\s*([\d\,]+)#", $node, $m)) {
            if (count($its) === 1) {
                $its[0]['TotalCharge'] = str_replace(",", ".", $m[2]);
                $its[0]['Currency'] = $m[1];
            } else {
                $tot = [
                    'Amount'   => str_replace(",", ".", $m[2]),
                    'Currency' => $m[1],
                ];
            }
        }

        if (isset($tot)) {
            return [
                'parsedData' => [
                    'Itineraries' => $its,
                    'TotalCharge' => $tot,
                ],
                'emailType' => "InfoTrip",
            ];
        } else {
            return [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => "InfoTrip",
            ];
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'SNCB Europe') or contains(.,'NMBS Europe')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $re) {
                if (stripos($body, $re) !== false) {
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
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@b-rail.be") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2;
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
        $rl = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'(PNR)') or contains(normalize-space(.),'(DNR)')]/ancestor::*[1]/following-sibling::strong[position()<last()]", null, "#([A-Z\d]{5,6})#"));

        if (count($rl) == 0) {
            $rl = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'(PNR)') or contains(normalize-space(.),'(DNR)')]/ancestor::*[1]/following-sibling::strong", null, "#[A-Z\d]{5,6}#"));
        }

        if (count($rl) == 0) {
            $rl = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'(PNR)') or contains(normalize-space(.),'(DNR)')]/following::strong[1]", null, "#[A-Z\d]{5,6}#"));
        }

        $xpath = "//text()[contains(.,'" . $this->t('Uw reisgegevens') . "')]/following::table[1]//tr[not(.//tr) and (contains(.,'" . $this->t('Vertrek') . "') or contains(.,'" . $this->t('Terugreis') . "'))]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length < 1) {
            return null;
        }

        $trips = [];

        if (count($rl) != $nodes->length) {
            $r = array_shift($rl);

            foreach ($nodes as $item) {
                $trips[$r][] = $item;
            }
        } else {
            foreach ($nodes as $i => $item) {
                $trips[$rl[$i]][] = $item;
            }
        }

        $pax = array_filter($this->http->FindNodes("//text()[contains(.,'" . $this->t('Reizigers') . "')]/following::table[1]//tr[count(descendant::tr)=0 and contains(.,'•')]/td[2]//text()[normalize-space(.) and not(contains(.,':'))]"));

        $its = [];

        foreach ($trips as $rl => $value) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $pax;

            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            foreach ($value as $root) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                $date = $this->normalizeDate($this->http->FindSingleNode(".", $root, true, "#\d+\s+\S+\s+\d+#"));

                if ($this->http->XPath->query("./following-sibling::tr[descendant::table]", $root)->length == 0) {
                    //format #1
                    //just 'fr' for now? need correct for another lang's
                    $node = $this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(.),'De')][1]", $root);

                    if (preg_match("#De\s+(.+?)\s+à\s+(.+?)\s+avec\s+(.+?)\s+n°\s+(\d+)\s+Départ\s+à\s+(\d+:\d+)\s+et\s+arrivée\s+à\s+(\d+:\d+)#", $node, $m)) {
                        $seg['DepName'] = $m[1];
                        $seg['ArrName'] = $m[2];
                        $seg['Type'] = $m[3];
                        $seg['FlightNumber'] = $m[4];
                        $seg['DepDate'] = strtotime($m[5], $date);
                        $seg['ArrDate'] = strtotime($m[6], $date);
                    }
                    $seg['Cabin'] = $this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(.),'" . $this->t('met tarief') . "')][1]/descendant::text()[contains(normalize-space(.),'" . $this->t('met tarief') . "')][1]", $root, true, "#" . $this->t('met tarief') . "\s+(.+)#");
                    $node = $this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(.),'place')][1]", $root);

                    if (preg_match("#Voiture\s+(\d+),\s+place\s+(\d+)#", $node, $m)) {
                        $seg['Seats'] = $m[1] . '-' . $m[2];
                    }
                } else {
                    //format #2
                    $nodes = $this->http->FindNodes("./following-sibling::tr[descendant::table]//tr[count(descendant::tr)=0 and contains(.,':')]", $root);

                    if (count($nodes) == 2) {
                        if (preg_match("#(\d+:\d+)\s+(.+)#", $nodes[0], $m)) {
                            $seg['DepName'] = $m[2];
                            $seg['DepDate'] = strtotime($m[1], $date);
                        }

                        if (preg_match("#(\d+:\d+)\s+(.+)#", $nodes[1], $m)) {
                            $seg['ArrName'] = $m[2];
                            $seg['ArrDate'] = strtotime($m[1], $date);
                        }
                    }
                    $seg['Cabin'] = $this->http->FindSingleNode("./following::tr[count(descendant::tr)=0 and contains(.,'" . $this->t('met tarief') . "')][1]/descendant::text()[contains(.,'" . $this->t('met tarief') . "')][1]/following::text()[normalize-space(.)][1]", $root);
                }

                if (isset($seg['DepDate']) && $seg['DepDate'] > $seg['ArrDate']) {
                    $seg['ArrDate'] = strtotime('+1 day', $seg['ArrDate']);
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($str)
    {
        $in = [
            '#(\d+)\s+(\S+)?[\.,]?\s+(\d+)#',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

        return strtotime($str);
    }
}
