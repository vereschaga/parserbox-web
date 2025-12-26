<?php

namespace AwardWallet\Engine\westjet\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "westjet/it-1.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"])
            && (stripos($headers['subject'], "Reservation Confirmation"));
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]westjet\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/([\.@]westjet\.com)/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = null;
        $itineraries['Kind'] = 'T';
        $switcher = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Your reservation code is')]/following-sibling::*[normalize-space()][1]");

        if (!empty($switcher)) {
            $itineraries['Kind'] = 'T';
            $segments = null;
            $itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Your reservation code is')]/following-sibling::*[normalize-space()][1]");
            $itineraries['Passengers'] = [$this->http->FindSingleNode("//td[contains(., 'Flight') and not(contains(., 'U.S.'))]/preceding-sibling::td")];
            $costTable = $this->http->XPath->query("//*[contains(normalize-space(.), 'Total') and contains(normalize-space(.), 'Charged')]/tr");

            foreach ($costTable as $rowTotal) {
                $cost = $this->http->FindSingleNode(".//td[contains(normalize-space(.), 'Total')]/following-sibling::td", $rowTotal);

                if (!empty($cost)) {
                    $itineraries['TotalCharge'] = preg_replace('/[A-Z]{3}\s?/', '', $cost);
                    $itineraries['Currency'] = preg_replace('/\s?[0-9.]+\s?/', '', $cost);
                }
            }
            $itineraries['TripSegments'] = $this->emailTypeOne();
        } else {
            $itineraries['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(normalize-space(.), 'Your reservation code is') and not(contains(normalize-space(.), 'Main contact'))]/td[2]");

            $itineraries['Passengers'] = [$this->http->FindSingleNode("//td[contains(., 'Flight') and not(contains(., 'U.S.'))]/preceding-sibling::td")];
            $cost = $this->http->FindSingleNode("//table//*[contains((.), 'Guest Type')]/../following-sibling::table[1]//td[contains(normalize-space(.), 'Total airfare')]");
            $cost = trim(str_replace('Total airfare:', '', $cost));

            if (!empty($cost)) {
                $itineraries['TotalCharge'] = preg_replace('/[A-Z]{3}\s?/', '', $cost);
                $itineraries['Currency'] = preg_replace('/\s?[0-9.]+\s?/', '', $cost);
            }
        }
        $itineraries['TripSegments'] = $this->emailTypeOne();

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }

    public function emailTypeOne()
    {
        $tripSegments = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Fare type')]");
        $seats = $this->http->XPath->query("//tr[contains(normalize-space(.), 'seat') and count(td) = 3]");
        $i = 0;
        $datePattern = "/[A-z]{3},\s?[0-9]{1,2}\s[A-z]{3}\s[0-9]{4},?\s?[0-9]{1,2}:[0-9]{1,2}\s?(A|P)M/";
        $segments = [];

        foreach ($tripSegments as $tripSegment) {
            $segment['FlightNumber'] = $this->http->FindSingleNode(".//td[1]", $tripSegment, true, "#^\s*[A-Z\d]{2}(\d+)#");
            $segment['AirlineName'] = $this->http->FindSingleNode(".//td[1]", $tripSegment, true, "#^\s*([A-Z\d]{2})\d+#");
            $depDate = $this->http->FindSingleNode(".//td[2]", $tripSegment);

            if (!empty($depDate)) {
                preg_match($datePattern, $depDate, $depDateRes);

                if (isset($depDateRes[0])) {
                    $segment['DepDate'] = strtotime(trim($depDateRes[0]));
                }
                $segment['DepName'] = trim(preg_replace($datePattern, '', $depDate));
            }

            $arrDate = $this->http->FindSingleNode(".//td[3]", $tripSegment);

            if (!empty($arrDate)) {
                preg_match($datePattern, $arrDate, $arrDateRes);

                if (isset($arrDateRes[0])) {
                    $segment['ArrDate'] = strtotime(trim($arrDateRes[0]));
                }
                $segment['ArrName'] = trim(preg_replace($datePattern, '', $arrDate));
            }

            if ($seats->length) {
                $seat = $this->http->FindSingleNode(".//td[2]", $seats->item($i));
                preg_match_all('/([A-Z]{3})|([0-9]{1,2}[A-Z]{1})/', $seat, $result);

                if (isset($result[0][0][0])) {
                    $segment['DepCode'] = $result[0][0];
                }

                if (isset($result[0][1])) {
                    $segment['ArrCode'] = $result[0][1];
                }

                if (isset($result[0][1])) {
                    $segment['Seats'] = $result[0][2];
                }
            } else {
                $codes = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Seat')]/following-sibling::td[1]");
                preg_match_all('/[A-Z]{3}/', $codes, $resultCodes);

                if (isset($resultCodes[0][0])) {
                    $segment['DepCode'] = $resultCodes[0][0];
                }

                if (isset($resultCodes[0][1])) {
                    $segment['ArrCode'] = $resultCodes[0][1];
                }
            }
            $segments[] = $segment;
            $i++;
        }

        return $segments;
    }
}
