<?php

namespace AwardWallet\Engine\s7\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "s7/it-1.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match("#@s7#i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("#@s7#i", $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Thank you for choosing S7 Airlines') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//div[contains(text(),'Booking reference:')]/following-sibling::div[1]", null, false, '#(\S+)\s+#i');
        $itineraries['Status'] = $this->http->FindSingleNode("//div[contains(text(),'Booking reference:')]/following-sibling::div[1]", null, false, '#\S+\s+(\w+)#i');
        $itineraries['TotalCharge'] = $this->http->FindSingleNode("//div[contains(text(),'Booking reference:')]/following-sibling::div[4]", null, false, '#\d+\s*(\S*)\s+\S{3}#i');

        if ($itineraries['TotalCharge']) {
            $itineraries['TotalCharge'] = str_replace(",", "", $itineraries['TotalCharge']);
        }
        $itineraries['Passengers'] = $this->http->FindSingleNode("//div[contains(text(),'Passengers E-ticket')]/following-sibling::div[1]");
        $itineraries['BaseFare'] = $this->http->FindSingleNode("//div[contains(text(),'Passengers E-ticket')]/following-sibling::div[3]", null, false, '#(\S+)\s*\w{3}#i');

        if ($itineraries['BaseFare']) {
            $itineraries['BaseFare'] = str_replace(",", "", $itineraries['BaseFare']);
        }
        $itineraries['Tax'] = $this->http->FindSingleNode("//div[contains(text(),'Passengers E-ticket')]/following-sibling::div[3]", null, false, '#\S+\s*\w{3}\s*(\S+)#i');

        if ($itineraries['Tax']) {
            $itineraries['Tax'] = str_replace(",", "", $itineraries['Tax']);
        }
        $itineraries['Currency'] = $this->http->FindSingleNode("//div[contains(text(),'Passengers E-ticket')]/following-sibling::div[3]", null, false, '#\S+\s*(\w{3})#i');
        $nodes = $this->http->XPath->query("//div[contains(text(),'Flight Departure')]");
        $this->http->Log("Total nodes found " . $nodes->length);

        for ($i = 0; $i < $nodes->length; $i++) {
            $itineraries['TripSegments'][$i]['FlightNumber'] = $this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[1])[$i+1]");
            $itineraries['TripSegments'][$i]['Aircraft'] = $this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[3])[$i+1]");
            $itineraries['TripSegments'][$i]['DepName'] = $this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[5])[$i+1]");
            $itineraries['TripSegments'][$i]['DepDate'] = strtotime($this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[4])[$i+1]"));
            $itineraries['TripSegments'][$i]['ArrName'] = $this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[8])[$i+1]");
            $itineraries['TripSegments'][$i]['ArrDate'] = strtotime($this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[7])[$i+1]"));
            $itineraries['TripSegments'][$i]['Cabin'] = $this->http->FindSingleNode("(//div[contains(text(),'Flight Departure')]/following-sibling::div[9])[$i+1]");
            $itineraries['TripSegments'][$i]['DepCode'] = TRIP_CODE_UNKNOWN;
            $itineraries['TripSegments'][$i]['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }
}
