<?php

namespace AwardWallet\Engine\norwegiancruise\Email;

class It2938695 extends \TAccountCheckerExtended
{
	public $mailFiles = "norwegiancruise/it-2938695.eml";
    public $reBody = 'Cruise.com';
    public $reBody2 = "Sailing Information";
    public $reFrom = "@cruise.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation:')]/..", null, true, "#Confirmation\s*:\s*\#(\d+)#");

                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[text() = 'Passenger']/ancestor::tr[1]/following-sibling::tr/td[2]");

                // AccountNumbers
                // Cancelled
                // ShipName
                $it['ShipName'] = $this->http->FindSingleNode("//*[text() = 'Ship:']/..", null, true, "#Ship\s*:\s*(.+)#");

                // ShipCode
                // CruiseName
                $it['CruiseName'] = $this->http->FindSingleNode("//*[text() = 'Cruise Line:']/..", null, true, "#Cruise Line\s*:\s*(.+)#");

                // Deck
                // RoomNumber
                $it['RoomNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Cabin Number')]/following-sibling::*[1]");

                // RoomClass
                $it['RoomClass'] = $this->http->FindSingleNode("//*[contains(text(), 'Category')]/following-sibling::*[1]");

                // TotalCharge
                // Currency
                $it = array_merge($it, total($this->http->FindSingleNode("//td[contains(text(),'Total') and not(contains(text(), 'Payments'))]/following-sibling::td[1]")));

                // Status

                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

                // Ports
                $Ports = explode(',', $this->http->FindSingleNode("//*[contains(text(), 'ITINERARY INFORMATION')]/..", null, true, "#ITINERARY INFORMATION\s*:\s*(.+)#"));
                $Ports = array_map('trim', $Ports);

                // TripSegments

                foreach ($Ports as $k=>$Port) {
                    $itsegment = [];
                    // Port
                    $itsegment['Port'] = $Port;

                    if ($k == 0) {
                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("//b[text() = 'From:']/parent::td", null, true, "#From\s*:\s*(.+)#"));
                    }

                    if ($k == count($Ports) - 1) {
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("//b[text() = 'To:']/parent::td", null, true, "#To\s*:\s*(.+)#"));
                    }

                    $it['TripSegments'][] = $itsegment;
                }

                // Convert
                $this->converter = new \CruiseSegmentsConverter();
                $it['TripSegments'] = $this->converter->Convert($it['TripSegments']);

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }
}
