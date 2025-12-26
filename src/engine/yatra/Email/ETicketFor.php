<?php

namespace AwardWallet\Engine\yatra\Email;

class ETicketFor extends \TAccountChecker
{
    public $mailFiles = "yatra/it-5017296.eml, yatra/it-6890891.eml, yatra/it-9900734.eml";

    public $reFrom = "yatra.com";

    public $reBody = [
        'en' => [
            'Your flight booking is on hold',
            'Flight e-Ticket',
            'Congratulations! Your flight booking is confirmed',
            'Your refund request has been processed',
        ],
    ];

    public $reSubject = [
        'Your Yatra e-Ticket for booking',
        'Confirmation Email',
    ];

    public $lang = '';

    public static $dict = [
        'en' => [
            'statusVariants' => ['confirmed', 'on hold'],
            'Total Amount'   => ['Total Amount', 'You Paid', 'You paid'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $parser->searchAttachmentByName("ETicket.*html");
            $htmls = $parser->searchAttachmentByName("ETicket.*html");

            if (isset($htmls) && count($htmls) > 0) {
                $html = $parser->getAttachmentBody(array_shift($htmls));
                $this->http->SetEmailBody($html);
            }
        }
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        if (count($its) === 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Total Flight Price')]/ancestor::td[1]/following-sibling::td"));

            if ($tot['Total'] !== null) {
                $its[0]['BaseFare'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Amount'))}]/ancestor::td[1]/following-sibling::td"));

            if ($tot['Total'] !== null) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        } else {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Amount'))}]/ancestor::td[1]/following-sibling::td"));

            if ($tot['Total'] !== null) {
                return [
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                    'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
                ];
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $parser->searchAttachmentByName("ETicket.*html");
            $htmls = $parser->searchAttachmentByName("ETicket.*html");

            if (isset($htmls) && count($htmls) > 0) {
                $html = $parser->getAttachmentBody(array_shift($htmls));
                $this->http->SetEmailBody($html);
            }
        }

        if ($this->http->XPath->query("//a[contains(@href,'yatra.com')]")->length > 0) {
            $body = $this->http->Response['body'];

            return $this->assignLang($body);
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

    private function parseEmail(): array
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $its = [];

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]");

        if (!$status) {
            $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your flight booking is'))}]", null, true, "/{$this->opt($this->t('Your flight booking is'))}\s+({$this->opt($this->t('statusVariants'))})(?:[,.;?!]|$)/i");
        }

        $tripNum = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference Number')]/following::text()[normalize-space(.)!=''][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");

        $earn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You will earn'))} and {$this->contains($this->t('for this booking'))}]", null, true, "/{$this->opt($this->t('You will earn'))}\s+(\d+ eCash)\s+{$this->opt($this->t('for this booking'))}/i");

        $resDate = strtotime($this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Booking Date') or starts-with(normalize-space(.),'Payment Due Date')]/following::text()[normalize-space(.)!=''][1])[1]"));

        $xpath = "//text()[starts-with(normalize-space(.), 'Airline PNR')]/ancestor::table[1]/ancestor::tr[1]";
        //		if( $this->http->XPath->query("descendant::img[contains(@src, 'img.yatra.com/content/air-pay-book-service')]", $this->http->XPath->query($xpath)->item(0))->length === 0 ){
        //			$xpath2 = "//text()[starts-with(normalize-space(.),'Airline PNR')]/ancestor::table[1]/ancestor::tr[1]/preceding::tr[descendant::img[contains(@src, 'img.yatra.com/content/air-pay-book-service/css/images/I5.gif')]][2]";
        //			$nodes = $this->http->XPath->query($xpath2);
        //		}
        //		if( $this->http->XPath->query($xpath)->length === 0 )
        //			$xpath = "//text()[starts-with(normalize-space(.), 'PNR')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return [];
        }
        $airs = [];

        foreach ($nodes as $root) {
            $node = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.), 'Airline PNR') or starts-with(normalize-space(.), 'PNR')]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^\s*([A-Z\d]+)\s*$#");

            if ($node) {
                $airs[$node][] = $root;
            } else {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;

            if ($status) {
                $it['Status'] = $status;
            }

            $it['TripNumber'] = $tripNum;

            if ($earn && count($airs) === 1) {
                $it['EarnedAwards'] = $earn;
            }

            $it['ReservationDate'] = $resDate;

            $tickets = [];
            $pax = [];

            foreach ($nodes as $root) {
                $seg = [];

                if ($node = $this->http->FindSingleNode("./following-sibling::tr[1][contains(.,'operated by')]", $root, true, "#operated by\s+(.+)#i")) {
                    $seg['Operator'] = $node;
                }
                $flight = implode(' ', $this->http->FindNodes("descendant::table[1]/descendant::text()[normalize-space()]", $root));

                if (preg_match('/(?:^|\s)([A-Z][A-Z\d]|[A-Z\d][A-Z]).*?(\d+)$/', $flight, $m)) {
                    // LH-Lufthansa761    |    Singapore Airlines SQ - 401
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::*[name() = 'table' or name() = 'span'][1]/following-sibling::table[1]//td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']", $root));

                if (empty($node) && isset($seg['AirlineName'])) {
                    $node = implode("\n", $this->http->FindNodes("./descendant::*[(name() = 'table' or name() = 'span') and not(contains(.,'{$seg['AirlineName']}'))][1]//td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']", $root));
                }

                if (preg_match("#(.+)\n\S*?\s*(\d+.+?\d{4})\s+[\|,]*\s+(\d+:\d+)\s+(.+)#s", $node, $m)) {
                    $m[4] = preg_replace("/Carry On Hand Baggage.+/", "", $m[4]);
                    $seg['DepName'] = preg_replace('/\s+/', ' ', $m[1] . '-' . $m[4]);
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[2] . ' ' . $m[3]));
                }

                $reTerm = '/^(.{3,}?)\s*,\s*T-([-A-Z\d]+)/i'; // Indira Gandhi Airport, T-2

                if (!empty($seg['DepName']) && preg_match($reTerm, $seg['DepName'], $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepartureTerminal'] = $m[2];
                }
                $node = implode("\n", $this->http->FindNodes("./descendant::*[name() = 'table' or name() = 'span'][1]/following-sibling::table[1]//td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='']", $root));

                if (empty($node) && isset($seg['AirlineName'])) {
                    $node = implode("\n", $this->http->FindNodes("./descendant::*[(name() = 'table' or name() = 'span') and not(contains(.,'{$seg['AirlineName']}'))][1]//td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='']", $root));
                }

                if (preg_match("#(.+)\n\S*?\s*(\d+.+?\d{4})\s+[\|,]*\s+(\d+:\d+)\s+(.+)#s", $node, $m)) {
                    $seg['ArrName'] = preg_replace('/\s+/', ' ', $m[1] . '-' . $m[4]);
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[2] . ' ' . $m[3]));
                }

                if (!empty($seg['ArrName']) && preg_match($reTerm, $seg['ArrName'], $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrivalTerminal'] = $m[2];
                }
                //$node = implode("\n", $this->http->FindNodes("./descendant::*[name() = 'table' or name() = 'span'][1]/following-sibling::table[2]//text()[normalize-space(.)!='']", $root));
                $node = implode("\n", $this->http->FindNodes("./descendant::text()[starts-with(normalize-space(.), 'Airline PNR')]/ancestor::td[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#Duration[:\s]+(\d+:\d+)\s+(.+)#", $node, $m) || preg_match("#Duration[:\s]+(\d+.+)(?:\n(.*)|$)#", $node, $m)) {
                    $seg['Duration'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['Cabin'] = $m[2];
                    }
                }

                if (!isset($seg['Cabin'])) {
                    $node = implode("\n", $this->http->FindNodes("./following::tr[1]//text()[normalize-space(.)!='' and not(contains(.,'---'))]", $root));

                    if (preg_match("#(.+)\s+\|#", $node, $m)) {
                        $seg['Cabin'] = $m[1];
                    }
                }

                $xpathRow = '(self::tr or self::table)';
                $xpathPassengerDetails = "ancestor-or-self::*[ {$xpathRow} and following-sibling::*[{$xpathRow} and {$this->starts($this->t('Passenger Details'))}] ][1]/following-sibling::*[{$xpathRow} and {$this->starts($this->t('Passenger Details'))}]";

                $pNames = array_filter($this->http->FindNodes($xpathPassengerDetails . "/descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Name'))}] ]/*[2]", $root, "/^({$patterns['travellerName']})\s*(?:\(|$)/u"));

                if (count($pNames) === 0) {
                    // it-6890891.eml
                    $pNames = array_filter($this->http->FindNodes($xpathPassengerDetails . "/descendant::tr[ *[1][{$this->eq($this->t('Name'))}] and *[5][{$this->starts($this->t('Seat No'))}] ]/following-sibling::tr/*[1]", $root, "/^({$patterns['travellerName']})\s*(?:\(|$)/u"));
                }

                if (count($pNames)) {
                    $pax = array_merge($pax, $pNames);
                }

                $seats = array_filter($this->http->FindNodes($xpathPassengerDetails . "/descendant::tr[ *[1][{$this->eq($this->t('Name'))}] and *[5][{$this->starts($this->t('Seat No'))}] ]/following-sibling::tr/*[5]", $root, '/^\d+[A-Z]$/'));

                if (count($seats)) {
                    $seg['Seats'] = array_unique($seats);
                }

                $pTickets = array_filter($this->http->FindNodes($xpathPassengerDetails . "/descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Ticket No.'))}] ]/*[2]", $root, '/^[-A-Z\d]{5,}$/'));

                if (count($pTickets) === 0) {
                    // it-6890891.eml
                    $pTickets = array_filter($this->http->FindNodes($xpathPassengerDetails . "/descendant::tr[ *[1][{$this->eq($this->t('Name'))}] and *[6][{$this->starts($this->t('Ticket No'))}] ]/following-sibling::tr/*[6]", $root, '/^[-A-Z\d]{5,}$/'));
                }

                if (count($pTickets)) {
                    $tickets = array_merge($tickets, $pTickets);
                }

                if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                $it['TripSegments'][] = $seg;
            }

            if (count($pax)) {
                $it['Passengers'] = array_unique($pax);
            }

            if (count($tickets)) {
                $it['TicketNumbers'] = array_unique($tickets);
            }

            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\s+(\w+),?\s+(\d{4})\s+(\d+:\d+)\s*$#',
        ];
        $out = [
            '$1 $2 $3 $4',
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

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("Rs.", "INR", $node);
        $tot = null;
        $cur = null;

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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
}
