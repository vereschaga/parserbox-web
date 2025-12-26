<?php

namespace AwardWallet\Engine\harrah\Email;

class It2891386 extends \TAccountCheckerExtended
{
    public $mailFiles = "harrah/it-2891386.eml, harrah/it-2891387.eml, harrah/it-2891388.eml"; // +1 bcdtravel(html)[en]

    public $reBody = "Harrah";
    public $reBody2 = "Thank you for attending";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $patterns = [
                    'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
                    'phone' => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
                ];

                $it = [];
                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("(//*[contains(text(),'Confirmation Number')])[1]", null, true, "#Confirmation\s+Number\s*:\s+(\S+)#");

                $data = $this->http->FindNodes("//*[contains(text(),'Hotel Information')]/following-sibling::table[normalize-space(.)][1]/descendant::text()[string-length(normalize-space(.))>1]");

                if (count($data) !== 4) {
                    return;
                }

                if (!preg_match('/^' . $patterns['phone'] . '$/', $data[3])) {
                    return;
                }

                // Hotel Name
                $it['HotelName'] = $data[0];

                // CheckInDate
                $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->contains('Check-In Date:')}]", null, true, "#Check-In Date:\s*(.{6,})#");

                if ($dateCheckIn) {
                    $it['CheckInDate'] = strtotime($dateCheckIn);
                }
                $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains('Check-In time is')}]", null, true, '/Check-In time is\s+(' . $patterns['time'] . ')/');

                if (!empty($it['CheckInDate']) && $timeCheckIn) {
                    $it['CheckInDate'] = strtotime($timeCheckIn, $it['CheckInDate']);
                }

                // CheckOutDate
                $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->contains('Check-Out Date:')}]", null, true, "#Check-Out Date:\s*(.{6,})#");

                if ($dateCheckOut) {
                    $it['CheckOutDate'] = strtotime($dateCheckOut);
                }
                $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains('Check-Out time is')}]", null, true, '/Check-Out time is\s+(' . $patterns['time'] . ')/');

                if (!empty($it['CheckOutDate']) && $timeCheckOut) {
                    $it['CheckOutDate'] = strtotime($timeCheckOut, $it['CheckOutDate']);
                }

                // Address
                $it['Address'] = $data[1] . ' ' . $data[2];

                // Phone
                $it['Phone'] = $data[3];

                // GuestNames
                $it['GuestNames'] = [$this->http->FindSingleNode("//text()[{$this->contains('Guest Name:')}]", null, true, '/Guest Name:\s*(.{2,})/')];

                // Guests
                $it['Guests'] = $this->http->FindSingleNode("//text()[{$this->contains('Number of Adults:')}]", null, true, '/Number of Adults:\s*(\d+)/');

                // Kids
                $it['Kids'] = $this->http->FindSingleNode("//text()[{$this->contains('Number of Children:')}]", null, true, '/Number of Children:\s*(\d+)/');

                // Rate
                $rateTexts = $this->http->FindNodes("//*[contains(text(), 'Rate') and contains(text(), 'Guest(s)')]/../descendant::text()[normalize-space(.)]");
                $rateText = implode(' ', $rateTexts);

                if (preg_match('/Confirmed\s+(\d[,.\'\d]*)/', $rateText, $matches)) {
                    $it['Rate'] = $matches[1];
                }

                // CancellationPolicy
                $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains('Cancellation Policy:')}]", null, true, '/Cancellation Policy:\s*(.+)/s');

                if ($cancellationPolicy) {
                    $it['CancellationPolicy'] = $cancellationPolicy;
                }

                // Total
                $totalRate = $this->http->FindSingleNode("//text()[{$this->contains(['Room Rates', 'Room rates'])}]/preceding::text()[normalize-space(.)!='' and normalize-space(.)!='*'][1]", null, true, '/^(\d[,.\'\d]*)\s*\*?\s*$/');

                if ($totalRate) {
                    $it['Total'] = $totalRate;
                }

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        /* $this->http->SetEmailBody(preg_replace("#<br(?:\s+[^>]+|\s*)/?>#", "|", $parser->getHTMLBody())); */
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
