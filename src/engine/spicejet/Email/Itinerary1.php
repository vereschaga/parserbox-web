<?php

namespace AwardWallet\Engine\spicejet\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "";
    public $reBody = 'SpiceJet';
    public $reBody2 = "your e-ticket is attached";
    public $reFrom = "Itinerary@spicejet.com";
    public $reSubject = "SpiceJet Booking PNR";

    protected $doc = null;

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];
                $text = $this->text($this->http->Response['body']);
                $doc = text($this->doc->Response['body']);

                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = re("#Your\s+confirmation\s+number\s+\(PNR\)\s+is\s+(\S+)#", $text);
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[contains(text(),'PASSENGERS(S)')]/ancestor::tr[1]/following-sibling::*/td/span[2]");
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = str_replace(",", "", re("#Total\s+Price\s+([\d\.,]+)#ms", $doc));
                // BaseFare
                // Currency
                $it['Currency'] = re("#Payment\s+Information\s+.*?\(([A-Z]+)\)]#", $doc);
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[contains(text(), 'Flight No.')]/ancestor::tr[1]/following-sibling::tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#^(.*?)(\s*/|$)#");

                    // DepDate
                    $itsegment['DepDate'] = strtotime(
                        re("#\w+\s+(\d+)\s+(\w+),\s+(\d+)#", $this->http->FindSingleNode("./td[1]", $root)) . ' ' . re(2) . ' ' . re(3) . ', ' .
                        $this->http->FindSingleNode("./td[5]", $root)
                    );

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root, true, "#^(.*?)(\s*/|$)#");

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(
                        re("#\w+\s+(\d+)\s+(\w+),\s+(\d+)#", $this->http->FindSingleNode("./td[1]", $root)) . ' ' . re(2) . ' ' . re(3) . ', ' .
                        $this->http->FindSingleNode("./td[6]", $root)
                    );

                    if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                        $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(\D+)#");

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = text($parser->getHTMLBody());

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false || strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $this->doc = clone $this->http;
        $itineraries = [];

        // this attachments have bad headers content-type(name*0=). Alternative method search pdf/htm
        $pos = false;
        $type = "";

        foreach ($parser->getAttachments() as $i=>$att) {
            if (strpos($att['headers']['content-type'], 'application/pdf') !== false) {
                $pos = $i;
                $type = 'pdf';

                break;
            } elseif (strpos($att['headers']['content-type'], '.htm') !== false) {
                $pos = $i;
                $type = 'htm';

                break;
            }
        }

        if ($pos !== false) {
            switch ($type) {
                case 'pdf':
                    if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pos), \PDF::MODE_SIMPLE)) !== null) {
                        $this->doc->SetEmailBody($html);
                    } else {
                        return null;
                    }

                break;

                case 'htm':
                    $this->doc->SetEmailBody($parser->getAttachmentBody($pos));

                break;
            }
        } else {
            return null;
        }

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
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

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
