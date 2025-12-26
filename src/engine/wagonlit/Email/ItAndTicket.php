<?php

namespace AwardWallet\Engine\wagonlit\Email;

class ItAndTicket extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-4846080.eml";
    public $reBody = [
        'We are pleased to enclose your Itinerary and e-Ticket for your reference',
        'This e-mail and any attachments may contain confidential',
    ];
    public $reSubject = [
        'Your itinerary & e-Ticket',
    ];

    public $pdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $mhts = $parser->searchAttachmentByName('.*\.mht');
        $html = '';

        if (isset($mhts) && count($mhts) > 0) {
            foreach ($mhts as $mht) {
                $html = $parser->getAttachmentBody($mht);
            }
        } else {
            return null;
        }

        $this->http->SetBody($html);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => [$its]],
            'emailType'  => "ItAndTicket",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, $this->reBody[0]) !== false && stripos($body, $this->reBody[1]) !== false;
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
        return stripos($from, "carlsonwagonlit.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//td[@class='itinerary' and contains(.,'Reservation Code:')]/following-sibling::td[1]");
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//span[@class='itinerary' and contains(.,'Issued Date')]/../following-sibling::td[1]"));
        $it['AccountNumbers'] = $this->http->FindSingleNode("//span[@class='itinerary2']/strong[.='Frequent Flyer']/ancestor::tr[1]/following-sibling::tr[1]//td[@class='itinerary2']");
        $it['Passengers'] = $this->http->FindNodes("//td[@class='itinerary' and contains(.,'Traveler:')]/following-sibling::td[1]");

        foreach ($this->http->XPath->query("//span[@class='itinerary2']/strong[contains(.,'Ticket No :')]/ancestor-or-self::table[1]") as $ts) {
            $seg = [];

            if (($tmp = $this->http->FindSingleNode(".//strong[contains(.,'Ticket No :')]/ancestor::td[1]/preceding-sibling::td[1]", $ts))) {
                //Sat, 29 Oct 2016 Singapore Airlines, SQ915
                if (preg_match('#\w{3},\s+\d{1,2}\s+\w{3}\s+\d{4}\s+.+?,\s+(?<AirlineName>[A-Z\d]{2})(?<FlightNumber>\d+)#', $tmp, $m)) {
                    $seg['AirlineName'] = $m['AirlineName'];
                    $seg['FlightNumber'] = $m['FlightNumber'];
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            foreach (['Departure', 'Arrival'] as $d) {
                foreach (['Terminal', 'Date', 'Time'] as $t) {
                    if (($tmp = $this->http->FindSingleNode(".//div[contains(.,'{$d} {$t}')]/ancestor::td[1]/following-sibling::td[1]", $ts))) {
                        if ($t === 'Time') {
                            $seg[substr($d, 0, 3) . 'Date'] = strtotime($seg[$d . 'Date'] . " " . $tmp);
                            unset($seg[$d . 'Date']);
                        } else {
                            $seg[$d . $t] = $tmp;
                        }
                    }
                }
            }

            foreach (['From' => 'DepName', 'To' => 'ArrName', 'Status' => 'Status', 'Journey Time' => 'Duration',
                'Aircraft Type' => 'Aircraft', 'Meal Request' => 'Meal', 'Seat Request' => 'Seats', 'Booking Class' => 'BookingClass', ] as $d => $k) {
                if (($tmp = $this->http->FindSingleNode(".//div[contains(.,'{$d} :')]/ancestor::td[1]/following-sibling::td[1]", $ts))) {
                    $seg[$k] = $tmp;
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
