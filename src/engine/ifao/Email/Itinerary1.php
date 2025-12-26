<?php

namespace AwardWallet\Engine\ifao\Email;

// TODO: merge with parsers cytric/BookingConfirmation (in favor of cytric/BookingConfirmation)

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "ifao/it-1.eml, ifao/it-2.eml, ifao/it-3.eml, ifao/it-4.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && stripos($headers["from"], '@ifao') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("#@ifao#i", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), '@ifao.net') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindPreg('#Airline Reference:#')) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->It_1eml()],
                ],
            ];
        } else {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->It_2eml()],
                ],
            ];
        }
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function It_1eml()
    {
        $itineraries['Kind'] = 'T';
        $itineraries['Passengers'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/preceding-sibling::tr[1])[1]", null, true, '#(.*?)\(#');
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Booking Code:')]and not(.//table/.//table)])[last()]", null, true, '#Booking Code:\s*(\S+)\,\s*Booking#');

        $itineraries['ReservationDate'] = strtotime($this->http->FindSingleNode("(//table[.//span[contains(text(),'Booking Code:')]and not(.//table/.//table)])[last()]", null, true, '#Booking Date:\s*(\S+)#'));

        $nodes = $this->http->FindNodes("//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]");

        for ($i = 0; $i <= count($nodes) - 1; $i++) {
            $itineraries['TripSegments'][$i]['FlightNumber'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]])[$i+1]", null, true, '#.*?\s(\d+|\w+\d+|\d+\w+)#');
            $itineraries['TripSegments'][$i]['Cabin'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]])[$i+1]", null, true, '#.*?\s(?:\d+|\w+\d+|\d+\w+)\s*(.*)\,#');
            $itineraries['TripSegments'][$i]['AirlineName'] = trim($this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]])[$i+1]", null, true, '#^(\D+)#'));
            $Deptime[$i] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[1])[$i+1]", null, true, '#(\d+:\d+)#');
            $itineraries['TripSegments'][$i]['DepName'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[1])[$i+1]", null, true, '#\d+:\d+\s*(.*)#');
            $itineraries['TripSegments'][$i]['DepCode'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[1])[$i+1]", null, true, '#.*\((\w+)\)[^\)]*$#');
            $Arrtime[$i] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[2])[$i+1]", null, true, '#(\d+:\d+)#');
            $ArrName[$i] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[2])[$i+1]");
            $date[$i] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Itinerary')]and not(.//table/.//table)]/following-sibling::table[$i+1]/.//tr[ not(.//tr)])[4]", null, true, '#(\d+.*?\d+)#');
            $itineraries['TripSegments'][$i]['DepDate'] = strtotime($date[$i] . ' ' . $Deptime[$i]);

            if (preg_match('#(\d+:\d+)\s*(.*\d{4})?\,?\s*(.*)#', $ArrName[$i], $m)) {
                if ($m[2] != '') {
                    $itineraries['TripSegments'][$i]['ArrDate'] = strtotime($date[$i] . ' ' . $m[1] . '+ 1 day');
                } else {
                    $itineraries['TripSegments'][$i]['ArrDate'] = strtotime($date[$i] . ' ' . $m[1]);
                }
                $itineraries['TripSegments'][$i]['ArrName'] = $m[3];

                if (preg_match('#.*\((\w+)\)[^\)]*$#', $itineraries['TripSegments'][$i]['ArrName'], $m)) {
                    $itineraries['TripSegments'][$i]['ArrCode'] = $m[1];
                }
            }

            $itineraries['TripSegments'][$i]['Seats'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[4])[$i+1]", null, true, '#(?:Seats:|Seat Request:)\s*(.*)#');

            if (preg_match('#No seat reservation#', $itineraries['TripSegments'][$i]['Seats'])) {
                $itineraries['TripSegments'][$i]['Seats'] = '';
            }
            $itineraries['TripSegments'][$i]['Duration'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[5])[$i+1]", null, true, '#Flight Duration:\s*(.*?)\,#');
            $itineraries['TripSegments'][$i]['TraveledMiles'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Flight Duration:')]and not(.//table/.//table)]/.//tr[.//*[contains(text(),' Airline Reference:')]]/following-sibling::tr[5])[$i+1]", null, true, '#Miles:\s*(.*)#');
        }

        return $itineraries;
    }

    public function It_2eml()
    {
        $itineraries['Kind'] = 'R';
        $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Booking Code:')]and not(.//table/.//table)])[last()]", null, true, '#Booking Code:\s*(\S+)\,\s*Booking#');

        $itineraries['ReservationDate'] = strtotime($this->http->FindSingleNode("(//table[.//span[contains(text(),'Booking Code:')]and not(.//table/.//table)])[last()]", null, true, '#Booking Date:\s*(\S+)#'));

        $itineraries['GuestNames'] = $this->http->FindSingleNode("//table[.//span[contains(text(),'Hotel Reference:')]and not(.//table)]/.//tr[1]");
        $itineraries['HotelName'] = $this->http->FindSingleNode("//table[.//span[contains(text(),'Hotel Reference:')]and not(.//table)]/.//tr[2]");
        $itineraries['Address'] = $this->http->FindSingleNode("//table[.//span[contains(text(),'Hotel Reference:')]and not(.//table)]/.//tr[3]");
        $itineraries['Phone'] = $this->http->FindSingleNode("//table[.//span[contains(text(),'Hotel Reference:')]and not(.//table)]/.//tr[4]", null, true, '#Telephone:\s*(.*?)\,?\s*Telefax:#');
        $itineraries['Fax'] = $this->http->FindSingleNode("//table[.//span[contains(text(),'Hotel Reference:')]and not(.//table)]/.//tr[4]", null, true, '#Telefax:\s*(.*)#');
        $description = $this->http->FindSingleNode("(//table[.//span[contains(text(),'Hotel Reference:')]and not(.//table/.//table)]/following-sibling::table)[1]");

        if (preg_match("#Rate Amount:\s*(.*?)FROM\s*(.*?)\s*UNTIL\s*(.*?)\,.*Total:\s*(\w+)\s*(\d+\.\d+).*?Room Description:\s*(.*?)\,\s*Cancellation Policy?#", $description, $match)) {
            $itineraries['Rate'] = $match[1];
            $itineraries['CheckInDate'] = strtotime($match[2]);
            $itineraries['CheckOutDate'] = strtotime($match[3]);
            $itineraries['Currency'] = $match[4];
            $itineraries['Total'] = $match[5];
            $itineraries['RateType'] = $match[6];
        }

        return $itineraries;
    }
}
