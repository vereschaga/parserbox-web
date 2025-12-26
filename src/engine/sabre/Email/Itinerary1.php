<?php

namespace AwardWallet\Engine\sabre\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'M d g:ia';
    public $mailFiles = "sabre/it-1.eml, sabre/it-3.eml";
    public $dateEmail;

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"])
            && ((stripos($headers["from"], '@sabre.com') !== false || stripos($headers["from"], '@getthere.com') !== false)
                && isset($headers['subject']) && stripos($headers['subject'], 'Booking Confirmation') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (preg_match("#From:\s*[^\s]+@getthere\.com#", $body)) {
            return true;
        }

        if (preg_match("#@getthere\.com#", $body) && preg_match("#Thank you for making your travel reservations through our site#i", $body)) {
            return true;
        }

        return stripos($body, 'NON-COMPLIANT BOOKING - AUTHORIZATION REQUESTED') !== false || stripos($body, 'Thank You for using Carlson Wagonlit.') !== false
            || stripos($body, 'BUSINESS TRAVEL CENTER') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $plain = false;

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $plain = true;
        }
        $this->dateEmail = strtotime($parser->getDate());

        if (stripos($body, 'ctd.sabre@sabre.com') !== false) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => $this->ParseEmail_3($body),
                ],
            ];
        } else {
            $itineraries = [];
            $itineraries['Kind'] = 'T';
            $itineraries['RecordLocator'] = $this->http->FindPreg('/Reservation code:\s*([A-Z\d]{5,})/');
            $itineraries['Passengers'] = $this->http->FindSingleNode('//div[@id="itinerary-data"]/table[1]//tr[4]');

            $segments = [];

            $sendDate = date_parse_from_format('D, d M Y H:i', $parser->getHeader('date'));

            $tripInfoRows = $this->http->XPath->query('//div[@id="itinerary-data"]/table[1]/following-sibling::table');
            $tripRows = [];

            foreach ($tripInfoRows as $row) {
                $tripRows[] = $row;
            }
            $tripRows = array_slice($tripRows, 0, count($tripRows) - 2);

            foreach ($tripRows as $row) {
                $tripSegment = [];

                $to = $this->http->FindSingleNode("./tbody/tr[4]/td[2]", $row, true, '/To:\s*(.*)/');

                if (preg_match('/(?P<city>.+),\s*(?P<citycode>.*)\s*\((?P<airport>[^\)]*)/', $to, $matches)) {
                    $tripSegment['ArrName'] = $matches['city'];
                    $tripSegment['ArrCode'] = $matches['airport'];
                } else {
                    $to = $this->http->FindSingleNode("./tbody/tr[5]/td[2]", $row, true, '/To:\s*(.*)/');

                    if (preg_match('/(?P<city>.+),\s*(?P<citycode>.*)\s*\((?P<airport>[^\)]*)/', $to, $matches)) {
                        $tripSegment['ArrName'] = $matches['city'];
                        $tripSegment['ArrCode'] = $matches['airport'];
                    }
                }

                $segmentRows = $this->http->XPath->query('./tbody/tr', $row);
                $segmentRowsArray = [];

                foreach ($segmentRows as $segmentRow) {
                    $segmentRowsArray[] = $segmentRow;
                }

                $matches = [];
                $date = trim(preg_split('/, /', $segmentRowsArray[0]->textContent)[1]);
                array_shift($segmentRowsArray);

                foreach ($segmentRowsArray as $segmentRow) {
                    if (stripos($segmentRow->textContent, 'Flights:') !== false) {
                        if (preg_match('/Flights: (?P<airline>.+),\s*(?P<number>\w+\ \d+)/', $segmentRow->textContent, $matches)) {
                            $tripSegment['AirlineName'] = $matches['airline'];
                            $tripSegment['FlightNumber'] = $matches['number'];
                        }

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'From:') !== false) {
                        $from = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/From:\s*(.*)/');

                        if (preg_match('/(?P<city>.+),\s*(?P<citycode>.*)\s*\((?P<airport>[^\)]*)/', $from, $matches)) {
                            $tripSegment['DepName'] = $matches['city'];
                            $tripSegment['DepCode'] = $matches['airport'];
                        }
                        $depTime = $this->http->FindSingleNode("./td[3]", $segmentRow, true, '/Departs:\s*(.*)/');
                        $tripSegment['DepDate'] = $this->stringDateToUnixtime($date . ' ' . $depTime, $sendDate);

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'To:') !== false) {
                        $to = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/To:\s*(.*)/');

                        if (preg_match('/(?P<city>.+),\s*(?P<citycode>.*)\s*\((?P<airport>[^\)]*)/', $to, $matches)) {
                            $tripSegment['ArrName'] = $matches['city'];
                            $tripSegment['ArrCode'] = $matches['airport'];
                        }
                        $arrTime = $this->http->FindSingleNode("./td[3]", $segmentRow, true, '/Arrives:\s*(.*)/');
                        $tripSegment['ArrDate'] = $this->stringDateToUnixtime($date . ' ' . $arrTime, $sendDate);

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'Class:') !== false) {
                        $tripSegment['Cabin'] = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/Class:\s*(.*)/');

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'Seat(s):') !== false) {
                        $tripSegment['Seats'] = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/.*-\s*(.*)/');

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'Aircraft:') !== false) {
                        $tripSegment['Aircraft'] = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/Aircraft:\s*(.*)/');
                        $tripSegment['TraveledMiles'] = floatval($this->http->FindSingleNode("./td[3]", $segmentRow, true, '/.*:\s*(.*)/'));

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'Duration:') !== false) {
                        $tripSegment['Duration'] = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/Duration:\s*(.*)/');

                        continue;
                    }

                    if (stripos($segmentRow->textContent, 'Frequent Flyer') !== false) {
                        $tripSegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $segmentRow, true, '/.*:\s*(.*)/');

                        continue;
                    }
                }

                $segments[] = $tripSegment;
            }

            $itineraries['TripSegments'] = $segments;

            return [
                'emailType'  => 'reservations',
                'parsedData' => [
                    'Itineraries' => [$itineraries],
                ],
            ];
        }
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public function clr($s)
    {
        return str_replace(' ', '', $s);
    }

    public function parseEmailCar($body)
    {
        $it = ['Kind' => 'L'];

        $it['Number'] = preg_match("#Car Rental Confirmation \#\d+\s*([^\n\(\s]+)#", $body, $m) ? $m[1] : null;
        $it['AccountNumbers'] = preg_match("#Apollo Record Locator \#:\s*([^\n]+)#", $body, $m) ? $m[1] : null;

        if ($it['AccountNumbers'] == null) {
            unset($it['AccountNumbers']);
        }
        $it['RenterName'] = preg_match("#Name:\s*([^\n<]+)#", $body, $m) ? $this->clr($m[1]) : null;
        $it['RentalCompany'] = preg_match("#Vendor:\s*([^\n<]+)#", $body, $m) ? $m[1] : null;

        $it['PickupPhone'] = $it['DropoffPhone'] = preg_match("#\nTel\.:\s*([^\n]+)#", $body, $m) ? $m[1] : null;

        if (preg_match("#\nPick\-up:\s*(\w+,\s*\w+\s+\d+\s+\d+:\d+)\s+([^\n]+)#i", $body, $m)) {
            $it['PickupDatetime'] = strtotime($m[1]);
            $it['PickupLocation'] = $m[2];
        }

        if (!isset($it['PickupLocation'])) {
            preg_match("#Pick\-up:\s*(\w+,\s*\w+\s+\d+\s+\d+:\d+)\s+([^\n]+?)Tel\.:\s+([\d-]+)#i", $body, $m);
            $it['PickupDatetime'] = strtotime($m[1]);
            $it['PickupLocation'] = str_replace('Address:', ',', strip_tags($m[2]));
            $it['PickupPhone'] = $m[3];
        }

        if (preg_match("#\nDrop\-off:\s*(\w+,\s*\w+\s+\d+\s+\d+:\d+)\s+([^\n]+)#i", $body, $m)) {
            $it['DropoffDatetime'] = strtotime($m[1]);
            $it['DropoffLocation'] = $m[2];
        }

        if (!isset($it['DropoffLocation'])) {
            preg_match("#Drop\-off:\s*(\w+,\s*\w+\s+\d+\s+\d+:\d+)\s+([^\n]+?)Tel\.:\s+([\d-]+)#i", $body, $m);
            $it['DropoffDatetime'] = strtotime($m[1]);
            $it['DropoffLocation'] = str_replace('Address:', ',', strip_tags($m[2]));
            $it['DropoffPhone'] = $m[3];
        }

        $it['CarType'] = preg_match("#Car size:\s*([^\n<]+)#", $body, $m) ? $this->clr($m[1]) : null;

        if (preg_match("#Total Car Cost:\s*([\d.]+)\s+(\w{3})#", $body, $m)) {
            $it['TotalCharge'] = $m[1];
            $it['Currency'] = $m[2];
        }

        return [$it];
    }

    public function stringDateToUnixtime($date, $sendDate)
    {
        $date = date_parse_from_format($this::DATE_FORMAT, $date);

        if (($sendDate['month'] > $date['month'] + 5) || ($sendDate['year'] > date("Y") || abs($sendDate['month'] - $date['month']) > 5)) {
            $date['year'] = $sendDate['year'] + 1;
        } else {
            $date['year'] = $sendDate['year'];
        }

        return mktime($date['hour'], $date['minute'], 0, $date['month'], $date['day'], $date['year']);
    }

    public function ParseEmail_3($body)
    {
        $itineraries[0] = $this->ParseEmail_Trip_3($body);
        $itineraries[1] = $this->ParseEmail_Car_3($body);

        return $itineraries;
    }

    public function ParseEmail_Car_3($body)
    {
        $itineraries['Kind'] = "L";

        if (preg_match('#Car Rental Confirmation \#\d\s*(.*)\s*\(#', $body, $match)) {
            $itineraries['Number'] = $match[1];
        }

        if (preg_match('#Sent:.*?(\d\d\d\d)#', $body, $year)) {
            $body = $this->http->FindSingleNode("//body");
        }

        if (preg_match('#Car Leg.*Vendor:\s*(.*)\s*Type:#', $body, $match)) {
            $itineraries['RentalCompany'] = $match[1];
        }

        if (preg_match('#Car Leg.*Pickup City:\s*(.*)\s*Drop off City:#', $body, $match)) {
            $itineraries['PickupLocation'] = $match[1];
        }

        if (preg_match('#Car Leg.*Drop off City:\s*(.*)\s*Dates:#', $body, $match)) {
            $itineraries['DropoffLocation'] = $match[1];
        }

        if (preg_match('#Car Leg.*Drop off City:\s*(.*)\s*Dates:#', $body, $match)) {
            $itineraries['DropoffLocation'] = $match[1];
        }

        if (preg_match('#Car Leg.*Dates:\s*\w+,\s*(\w+)\s*(\d|\d\d)\s*(\d\d:\d\d)\s*\-\s*\w+,\s*(\w+)\s*(\d|\d\d)\s*(\d\d:\d\d)#', $body, $match)) {
            $itineraries['DropoffDatetime'] = strtotime("$match[2] $match[1] $year[1] $match[3]");
            $itineraries['PickupDatetime'] = strtotime("$match[5] $match[4] $year[1] $match[6]");
        }

        if (preg_match('#Car Leg.*Total Car Cost:\s*(\d*\.\d*)\s*#', $body, $match)) {
            $itineraries['TotalCharge'] = $match[1];
        }

        return $itineraries;
    }

    public function ParseEmail_Trip_3($body)
    {
        $itineraries['Kind'] = 'T';

        if (preg_match('#Airline\s*.*\#\w\s*?(.*)s*\((.*)\)#', $body, $match)) {
            $itineraries['RecordLocator'] = $match[1];
        }

        if (preg_match('#Name\(s\) of passengers \:(.*)#', $body, $match)) {
            $itineraries['Passengers'] = $match[1];
        }

        if (preg_match('#Base Airfare.*?(\d*\.\d*)#', $body, $match)) {
            $itineraries['BaseFare'] = $match[1];
        }

        if (preg_match('#Base Airfare.*?(\d*\.\d*)\s*(\w*)#', $body, $match)) {
            $itineraries['BaseFare'] = $match[1];
            $itineraries['Currency'] = $match[2];
        }

        if (preg_match('#Total Flight.*?(\d*\.\d*)#', $body, $match)) {
            $itineraries['TotalCharge'] = $match[1];
        }

        $itineraries['TripSegments'] = $this->ParseEmail_Trip_Segments_3();

        return $itineraries;
    }

    public function ParseEmail_Trip_Segments_3()
    {
        $body = $this->http->FindSingleNode("//body");
        $i = 1;
        preg_match('#Sent:.*?(\d\d\d\d)#', $body, $year);

        while (preg_match('#Air Leg ' . $i . '#', $body)) {
            $regex = '#Air Leg ' . $i . '.*Airline:\s*(.*)Flight:\s*?(\d+)\s*(.*)\->\s*(.*)\s*Class:\s*(.*)?\s*Departure\s+Date/Time:\s*\w+,\s*(\w*)\s+(\d+)\s+(\d+:\d+)\s.*Arrival\s*Date/Time:\s*\w+,\s*(\w+)\s+(\d+)\s+(\d+:\d+)#';

            if (preg_match($regex, $body, $match[$i])) {
                if (isset($match[$i][2])) {
                    $itineraries[$i]['FlightNumber'] = $match[$i][2];
                }

                if (isset($match[$i][1])) {
                    $itineraries[$i]['AirlineName'] = $match[$i][1];
                }

                if (isset($match[$i][3])) {
                    $itineraries[$i]['DepName'] = $match[$i][3];
                }

                if (isset($match[$i][4])) {
                    $itineraries[$i]['ArrName'] = $match[$i][4];
                }
                $itineraries[$i]['DepCode'] = TRIP_CODE_UNKNOWN;
                $itineraries[$i]['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (isset($match[$i][7], $match[$i][6], $year[1], $match[$i][8])) {
                    $itineraries[$i]['DepDate'] = strtotime($match[$i][7] . ' ' . $match[$i][6] . ' ' . $year[1] . ' ' . $match[$i][8]);
                }

                if (isset($match[$i][10], $match[$i][9], $year[1], $match[$i][11])) {
                    $itineraries[$i]['ArrDate'] = strtotime($match[$i][10] . ' ' . $match[$i][9] . ' ' . $year[1] . ' ' . $match[$i][11]);
                }

                if (isset($match[$i][5])) {
                    $itineraries[$i]['Cabin'] = $match[$i][5];
                }
            }
            $i++;
        }

        return nice($itineraries);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]virtuallythere\.com$/ims', $from) || (preg_match('#.*@sabre.com#', $from));
    }
}
