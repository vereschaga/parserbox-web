<?php

namespace AwardWallet\Engine\wagonlit\Email;

class ItineraryETicketPdf extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-12233630.eml";

    public $reFrom = ["carlsonwagonlit.com", "cwt.com"];
    public $reBody = [
        'en' => ['Your Travel Itinerary', 'Agency Reference Number'],
    ];
    public $lang = '';
    public $pdfNamePattern = "ITINERARY E-TICKET.*pdf";
    public static $dict = [
        'en' => [
            'wordsEndSegments' => [
                'Flight',
                'This receipt will be required at check-in, and must be presented to customs and immigration if requested.',
            ],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $this->http->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
                    $body = $this->http->Response['body'];
                    $this->assignLang($body);
                    $its = array_merge($its, $this->parseEmailPdf());
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if ((stripos($text, 'carlsonwagonlit.com') !== false) || (stripos($text, 'cwt.com') !== false)) {
                return $this->assignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(
        array $headers
    ) {
        return false;
    }

    public function detectEmailFromProvider(
        $from
    ) {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
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

    private function parseEmailPdf()
    {
        $its = [];
        $airs = [];
        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::p[1][following::p[normalize-space(.)!=''][1][not({$this->contains($this->t('Date'))})]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $nums = $this->countP($root, $this->t('wordsEndSegments'));
            $subXPath = "./following-sibling::p[normalize-space(.)!=''][position()<{$nums}]";
            $rl = $this->http->FindSingleNode("{$subXPath}/descendant::text()[{$this->starts($this->t('Confirmation Number For'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]",
                $root, true, "#([A-Z\d]{5,})#");
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = [];
            $it['TicketNumbers'] = [];

            foreach ($roots as $root) {
                $nums = $this->countP($root, $this->t('wordsEndSegments'));
                $subXPath = "./following-sibling::p[normalize-space(.)!=''][position()<{$nums}]";
                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::p[normalize-space(.)!=''][1]",
                    $root));
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];
                $node = $this->http->FindSingleNode("{$subXPath}[1]", $root);

                if (preg_match("#^([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $node = $this->http->FindSingleNode("{$subXPath}/descendant::text()[{$this->starts($this->t('Class'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]",
                    $root);

                if (preg_match("#([A-Z]{1,2})\s*\-\s*(.+)#", $node, $m)) {
                    $seg['BookingClass'] = $m[1];
                    $seg['Cabin'] = $m[2];
                }
                $node = $this->http->FindSingleNode("{$subXPath}/descendant::text()[{$this->starts($this->t('Class'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][2]",
                    $root);

                if (preg_match("#non[ \-]*stop#i", $node)) {
                    $seg['Stops'] = 0;
                }

                $node = implode("\n",
                    $this->http->FindNodes("{$subXPath}/descendant::text()[{$this->starts($this->t('Departs'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][position()<5]",
                        $root));

                if (preg_match("#(\d+:\d+)\s+(.+)\n([A-Z]{3})\s*(?:((?i).*Terminal.*)|{$this->opt($this->t('Arrives'))})#",
                    $node, $m)) {
                    $seg['DepDate'] = strtotime($m[1], $date);
                    $seg['DepName'] = $m[2];
                    $seg['DepCode'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', ' ', $m[4]));
                    }
                }
                $node = implode("\n",
                    $this->http->FindNodes("{$subXPath}/descendant::text()[{$this->starts($this->t('Arrives'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][position()<5]",
                        $root));

                if (preg_match("#(\d+:\d+)\s+(.+)\n([A-Z]{3})\s*(?:((?i).*Terminal.*)|.*)#", $node, $m)) {
                    $seg['ArrDate'] = strtotime($m[1], $date);

                    if (preg_match("#(.+)\s*\*\s*(.*?\s+\d{4})\s*$#", $m[2], $v)) {
                        $seg['ArrName'] = $v[1];
                    } else {
                        $seg['ArrName'] = $m[2];
                    }
                    $seg['ArrCode'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', ' ', $m[4]));
                    }
                }
                $node = implode("\n",
                    $this->http->FindNodes("{$subXPath}/descendant::text()[{$this->starts($this->t('Flying Time'))}]/ancestor::p[1]/preceding::p[normalize-space(.)!=''][1]/following::p[normalize-space(.)!=''][position()<3]",
                        $root));

                if (preg_match("#{$this->opt($this->t('Flying Time'))}\s+(.+)#", $node, $m)) {
                    $seg['Duration'] = $m[1];
                }

                $node = implode("\n",
                    $this->http->FindNodes("{$subXPath}/descendant::text()[{$this->starts($this->t('Equipment'))}]/ancestor::p[1]/preceding::p[normalize-space(.)!=''][1]/following::p[normalize-space(.)!=''][position()<3]",
                        $root));

                if (preg_match("#{$this->opt($this->t('Equipment'))}\s+(.+)#", $node, $m)) {
                    $seg['Aircraft'] = $m[1];
                }

                $node = implode("\n",
                    $this->http->FindNodes("{$subXPath}/descendant::text()[{$this->starts($this->t('Meal'))}]/ancestor::p[1]/preceding::p[normalize-space(.)!=''][1]/following::p[normalize-space(.)!=''][position()<3]",
                        $root));

                if (preg_match("#{$this->opt($this->t('Meal'))}\s+(.+)#", $node, $m)) {
                    $seg['Meal'] = $m[1];
                }

                $node = implode("\n",
                    $this->http->FindNodes("{$subXPath}/descendant::text()[normalize-space(.)!='']", $root));

                if (preg_match("#{$this->opt($this->t('Special Meals'))}\s*^(.+)#sm", $node, $m)) {
                    if (preg_match_all("# *([\w\- ]+\/[\w\- ]+?)\s*\n\s*(\d{5,})?\s*(?:\([\w\-]+\))?\s*(?:\n(\d+[a-zA-Z]))?(?:\s+|$)#",
                        $m[1], $v)) {
                        $it['Passengers'] = array_merge($it['Passengers'], $v[1]);

                        if (isset($v[2]) && !empty($v[2])) {
                            $it['TicketNumbers'] = array_merge($it['TicketNumbers'], $v[2]);
                        }

                        if (isset($v[3]) && !empty($v[3])) {
                            $seg['Seats'] = $v[3];
                        }
                    }
                }

                $it['TripSegments'][] = $seg;
            }
            $it['Passengers'] = array_values(array_unique(array_filter($it['Passengers'])));
            $it['TicketNumbers'] = array_values(array_unique(array_filter($it['TicketNumbers'])));

            $xp = "//text()[normalize-space(.)='Travellers']/ancestor::p[1]";
            $paxRoot = $this->http->XPath->query($xp);

            if ($paxRoot->length === 1) {
                $num = $this->countP($paxRoot->item(0), ['Flight']);
                $sub = "./following::p[normalize-space(.)!=''][position()<{$num}]";
                $node = implode("\n",
                    $this->http->FindNodes("{$sub}/descendant::text()[normalize-space(.)!='']", $paxRoot->item(0)));

                if (preg_match_all("# *([\w\- ]+\/[\w\- ]+?)\s*\(#", $node, $m)) {
                    if (empty($it['Passengers'])) {
                        $it['Passengers'] = $m[1];
                    }
                }

                if (stripos($node, $this->t('Frequent Flyer Numbers') !== false)) {
                    if (preg_match_all("# *[\w\- ]+\/[\w\- ]+?\s*\(.+?\)\s*([A-Z\d\-]{5,})#", $node, $m)) {
                        $it['AccountNumbers'] = $m[1];
                    }
                }
            }

            $its[] = $it;
        }

        if (count($its) === 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]"));

            if (!empty($tot['Total'])) {
                $its[0]['BaseFare'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]"));

            if (!empty($tot['Total'])) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        }

        return $its;
    }

    private function countP($root, $words)
    {
        if ($root === null) {
            return 0;
        }
        $start = $this->http->XPath->query("./preceding::p[normalize-space(.)!='']", $root)->length;
        $end = $this->http->XPath->query("./following::p[{$this->eq($words)}][1]/preceding::p[normalize-space(.)!='']",
            $root)->length;

        if ($end > $start) {
            return $end - $start + 1;
        }

        return 0;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+,\s*(\d+)\s+(\w+)\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
