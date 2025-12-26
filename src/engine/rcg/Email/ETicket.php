<?php

namespace AwardWallet\Engine\rcg\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "rcg/it-11069587.eml, rcg/it-23765242.eml, rcg/it-24623768.eml, rcg/it-6601073.eml, rcg/it-6716320.eml, rcg/it-6722579.eml, rcg/it-751516598.eml";

    public $reFrom = "noreply@rcg.se";

    public $reSubject = [
        'E-ticket från RCG!',
        'Kvitto från RCG',
        'Bokningsbekräftelse från RCG',
    ];
    public $lang = '';
    public static $dictionary = [
        'sv' => [
            'Arrival'          => ['Ankomst', 'Ank', 'Arrival'],
            'From'             => ['From', 'Från'],
            'confNumber'       => ['Reservationsnummer', 'Incheckningskod'],
            'otaConfNumber'    => ['Bokningsnummer', 'RCGs bokningsnummer'],
            'passengers'       => ['Passagerare', 'Resenär'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicket' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".rcg.se/") or contains(@href,"www.rcg.se")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Rcg.se")]')->length === 0
            && $this->http->XPath->query('//img[normalize-space(@alt)="RCG onlinebokning" or contains(@src,"rcg.se/")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
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
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2;
        $cnt = $formats * count(self::$dictionary);

        return $cnt;
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

    private function parseEmail(): array
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")} and not(preceding::*[../self::tr and {$this->eq($this->t('Arrival'))}])]/following::text()[normalize-space(translate(.,':',''))][1]", null, true, '/^[A-Z\d]{5,9}$/');

        if ($confirmation) {
            $it['RecordLocator'] = $confirmation;
        } elseif (empty($confirmation) && $this->http->XPath->query("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Er e-ticket'))}]")->length === 0
        ) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]/following::text()[normalize-space(translate(.,':',''))][1]", null, true, '/^[-A-Z\d]{5,25}$/');
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Bokningsdatum'), "translate(.,':','')")}]/following::text()[normalize-space(translate(.,':',''))][1]", null, true, '/^.*\b\d{4}\b.*$/'));
        $it['TicketNumbers'] = [];

        foreach (array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'Biljettnummer')]/ancestor::*[1]/following-sibling::text()[normalize-space(.)]", null, "#^\s*(\d+.+)#")) as $str) {
            $it['TicketNumbers'] += array_map('trim', explode(",", $str));
        }

        $travellers = [];
        $passengersText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('passengers'), "translate(.,':','')")}]/ancestor::*[ descendant::text()[normalize-space(translate(.,':',''))][2] ][1]"));
        $passengersVal = preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('passengers'))}[: ]+([\s\S]+?)(?:\n\n|\n.*[:\d]|\s*$)/", $passengersText, $m) ? trim($m[1]) : '';
        $passengersRows = preg_split("/[ ]*\n[ ]*/", $passengersVal);

        foreach ($passengersRows as $pRow) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $pRow)) {
                $travellers[] = $pRow;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $it['Passengers'] = $travellers;
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Totalt inkl. skatt')]/following::text()[normalize-space(.)][1]"));

        if ($tot['Total'] !== '') {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1][contains(.,'Flight')]/following-sibling::tr";

        // two types order columns:
        // format 1:
        //             Avgång	    Ankomst	Flight	Från	Till
        //             Departure	Arrival	Flight	From	To
        // format 2:
        //             Avg	        Från	Ank	    Till	Flight	Flygbolag
        $format = 1;
        //check columns format
        if ($this->http->XPath->query("(//text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1][contains(.,'Flight')]/descendant::td[{$this->contains($this->t('From'))}])[1]/preceding-sibling::td[normalize-space()]")->length === 1) {
            $format = 2;
        }

        if ($format === 1) {
            $numDepDate = 1;
            $numArrDate = 2;
            $numFlight = 3;
            $numDepPoint = 4;
            $numArrPoint = 5;
        } else {
            $numDepDate = 1;
            $numArrDate = 3;
            $numFlight = 5;
            $numDepPoint = 2;
            $numArrPoint = 4;
        }
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][{$numDepDate}]", $root)));
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][{$numArrDate}]", $root)));

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][{$numFlight}]", $root);

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][{$numDepPoint}]", $root);

            if (preg_match("/(.+\S)\s*\(\s*([A-Z]{3})\s*(.*?)\s*\)/", $node, $m)) {
                if (!empty($m[3])) {
                    $seg['DepName'] = trim($m[3], " -.");
                } else {
                    $seg['DepName'] = $m[1];
                }
                $seg['DepCode'] = $m[2];
            } else {
                $seg['DepName'] = $node;
            }
            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][{$numArrPoint}]", $root);

            if (preg_match("/(.+\S)\s*\(\s*([A-Z]{3})\s*-?\s*(.*?)\s*\)/", $node, $m)) {
                if (!empty($m[3])) {
                    $seg['ArrName'] = trim($m[3], " -.");
                } else {
                    $seg['ArrName'] = $m[1];
                }
                $seg['ArrCode'] = $m[2];
            } else {
                $seg['ArrName'] = $node;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //25/12-16 17:20
            '#^(\d+)\/(\d+)\-(\d{2})\s+(\d+:\d+)$#',
        ];
        $out = [
            '20$3-$2-$1 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Arrival']) || empty($phrases['From'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[ *[{$this->eq($phrases['Arrival'])}] and *[{$this->eq($phrases['From'])}] ]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
