<?php

namespace AwardWallet\Engine\omnihotels\Email;

class It5359562 extends \TAccountChecker
{
    public $mailFiles = "omnihotels/it-5359562.eml, omnihotels/it-5359583.eml, omnihotels/it-5359652.eml, omnihotels/it-5359796.eml, omnihotels/it-5359857.eml, omnihotels/it-6133468.eml";

    public $reFrom = "@omnihotels.com";
    public $reSubject = [
        "Omni Hotels Guest Folio",
        "Omni Hotels Guest Receipt",
    ];
    public $reBody = 'omnihotels.com';
    public $reBody2 = [
        "en"=> ["Folio for ", "Receipt for "],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_array($re)) {
                foreach ($re as $r) {
                    if (strpos($body, $r) !== false) {
                        return true;
                    }
                }
            } elseif (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        $body = $this->http->Response["body"];
        $this->lang = "";

        foreach ($this->reBody2 as $lang=>$re) {
            if (!empty($this->lang)) {
                break;
            }

            if (is_array($re)) {
                foreach ($re as $r) {
                    if (strpos($body, $r) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            } elseif (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(&$itineraries): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Confirmation ')]", null, true, "#Confirmation \#(\d+)$#");

        if (empty($it['ConfirmationNumber'])) {
            $it['ConfirmationNumber'] = $this->nextText('Confirmation #');
        }

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Phone:')]/ancestor::td[1]/descendant::text()[normalize-space()][1][ ancestor::*[{$xpathBold}] ]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->nextText('Arrival:'));

        if (empty($it['CheckInDate'])) {
            $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Arrival:')][1]", null, true, '/Arrival:[ ]+(.+)/'));
        }

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->nextText('Departure:'));

        if (empty($it['CheckOutDate'])) {
            $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Departure:')][1]", null, true, '/Departure:[ ]+(.+)/'));
        }

        // Address
        $it['Address'] = implode(', ', $this->http->FindNodes("//text()[starts-with(normalize-space(),'Phone:')]/ancestor::td[1]/descendant::text()[normalize-space()][1]/following::text()[ normalize-space() and following::text()[starts-with(normalize-space(),'Phone:')] ]"));

        // DetailedAddress

        // Phone
        $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Phone:')]", null, true, "/Phone:[:\s]*([+(\d][-. \d)(]{5,}[\d)])$/");

        if (!$phone) {
            $phone = $this->nextText('Phone:');
        }
        $it['Phone'] = $phone;

        // Fax
        // GuestNames
        $it['GuestNames'] = array_filter([$this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Folio for ') or starts-with(normalize-space(.), 'Receipt for ')]", null, true, "#(?:Folio for|Receipt for) (.+)#")]);

        // Guests
        // Kids
        // Rooms
        // Rate
        // RateType

        // CancellationPolicy
        // RoomType
        // RoomTypeDescription
        $it['RoomTypeDescription'] = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Room No:'] ]")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Room No:')]", null, true, "/^Room No:\s*\d+$/i")
        ;

        // Cost
        // Taxes
        // Total
        // Currency
        $amounts = $currencies = [];
        $deductionsRows = $this->http->XPath->query("//tr[ *[normalize-space()][1][starts-with(normalize-space(),'Date')] and *[normalize-space()][last()][starts-with(normalize-space(),'Amount')] ]/following-sibling::tr[ *[normalize-space()][2] and *[normalize-space()][last()][contains(normalize-space(),'-')] ]");

        foreach ($deductionsRows as $dRow) {
            if (preg_match('/^-\s*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)?$/', $this->http->FindSingleNode("*[normalize-space()][last()]", $dRow), $m)) {
                // -954.85 USD
                $amounts[] = $this->normalizeAmount($m['amount']);

                if (!empty($m['currency'])) {
                    $currencies[] = $m['currency'];
                }
            }
        }
        $currencies = array_values(array_unique($currencies));

        if (count($currencies) === 1) {
            $it['Total'] = array_sum($amounts);
            $it['Currency'] = $currencies[0];
        }

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries

        // Fees
        // $xpath = "//text()[normalize-space(.)='Amount']/ancestor::tr[1]/following-sibling::tr[./td[2]]";
        // $nodes = $this->http->XPath->query($xpath);
        // $fees = [];
        // foreach($nodes as $root){
        // if(($Name = $this->http->FindSingleNode("./td[2]", $root)) && ($Charge = $this->normalizeAmount($this->re("#^([\d,.]+)\s+[A-Z]{3}$#", $this->http->FindSingleNode("./td[last()]", $root)))))
        // $fees[] = [
        // 'Name' => $Name,
        // 'Charge' => $Charge,
        // ];
        // }
        // if(!empty($fees))
        // $it["Fees"] = $fees;

        $itineraries[] = $it;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
