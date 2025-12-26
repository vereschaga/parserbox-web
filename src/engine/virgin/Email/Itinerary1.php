<?php

namespace AwardWallet\Engine\virgin\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $reFrom = "#Virgin\.eticket\.UK@fly\.virgin\.com#i";
    public $reSubject = "#Virgin Atlantic Airways#i";
    public $reProvider = "#fly\.virgin\.com#i";
    public $reText = null;
    public $reHtml = null; //"#virginamerica\.com([\s]|>)+wrote#i";
    public $mailFiles = "";

    public function __construct()
    {
        parent::__construct();

        // @Define processors
        $this->processors = [
            // @Parse "virgin/it-1.eml"
            "#Virgin Atlantic Airways customer#i" => function (&$it) {
                $body = $this->http->Response['body']; // full html
                // @Handlers

                $it['Kind'] = 'T';

                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Reference:')]/following-sibling::td[1]");
                $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Issue Date:')]/following-sibling::td[1]"));

                $it['Passengers'] = $this->http->FindSingleNode("//*[contains(text(), 'Passenger:')]/following-sibling::td[1]");

                $it['BaseFare'] = $this->http->FindSingleNode("//*[contains(text(),'Fare')]/following-sibling::td[2]");
                $it['Tax'] = 0;

                foreach (explode(" ", trim(preg_replace("#[^\d.]+#", ' ', $this->http->FindSingleNode("//*[contains(text(),'Taxes')]/following-sibling::td[1]")))) as $plus) {
                    $it['Tax'] += $plus;
                }

                $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(),'Total')]/following-sibling::td[2]");
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(),'Total')]/following-sibling::td[1]");

                $it['TripSegments'] = [];

                $year = date('Y', $it['ReservationDate']);
                $rows = $this->http->XPath->query("//*[contains(text(), 'FLIGHT DETAILS')]/ancestor::table[1]/following-sibling::table[contains(., 'ARRIVE')]//tr");

                for ($i = 1; $i < $rows->length; $i++) {
                    $row = $rows->item($i);
                    $seg = [];

                    $seg['FlightNumber'] = $this->http->FindSingleNode("td[1]", $row);

                    $seg['DepDate'] = strtotime($this->http->FindSingleNode("td[2]", $row) . " $year, " . $this->http->FindSingleNode("td[4]", $row));
                    $seg['DepCode'] = $seg['DepName'] = $this->http->FindSingleNode("td[3]", $row);

                    $seg['ArrDate'] = strtotime($this->http->FindSingleNode("td[2]", $row) . " $year, " . $this->http->FindSingleNode("td[6]", $row));
                    $seg['ArrCode'] = $seg['ArrName'] = $this->http->FindSingleNode("td[5]", $row);

                    $seg['Cabin'] = $this->http->FindSingleNode("td[7]", $row);
                    $lineCode = $this->http->FindSingleNode("td[9]", $row);
                    $seg['AirlineName'] = $this->http->FindSingleNode("//td[contains(text(), '=')]/preceding-sibling::td[contains(text(), '" . $lineCode . "')]/following-sibling::td[2]");

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return ((isset($this->reFrom) && $this->reFrom) ? preg_match($this->reFrom, $headers["from"]) : false)
                || ((isset($this->reSubject) && $this->reSubject) ? preg_match($this->reSubject, $headers["subject"]) : false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $parser->getPlainBody()) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $parser->getHtmlBody())) {
                $processor($itineraries);
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }
}
