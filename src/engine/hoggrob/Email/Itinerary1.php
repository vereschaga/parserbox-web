<?php

namespace AwardWallet\Engine\hoggrob\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public const DATE_FORMAT = 'Hi F d Y';
    public $mailFiles = "hoggrob/it-2.eml";

    private $shared = [];

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && in_array($headers["from"], ["CORREX@TRXCORREX.COM", "HRG.YOUR.TRAVEL.ITINERARY.NO.REPLY.CH@HRGWORLDWIDE.COM", "CORREX.CH@HRGWORLDWIDE.COM"]))
            || stripos($headers['subject'], "E-TICKET ITINERARY RECEIPT FOR") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return in_array($from, ["CORREX@TRXCORREX.COM", "HRG.YOUR.TRAVEL.ITINERARY.NO.REPLY.CH@HRGWORLDWIDE.COM", "CORREX.CH@HRGWORLDWIDE.COM"]);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = null;
        $html = $this->http->XPath->query('//table');
        $this->year = orval(
            re('#\d{4}#i', $parser->getHeader('date')),
            re('#Date:.*?(\d{4})#i', $this->http->Response['body'])
        );

        if ($html->length) {
            $itineraries = $this->htmlItineraries();
        } else {
            $itineraries = $this->plainItineraries($parser->getPlainBody());
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => $itineraries,
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function plainItineraries($handle = null)
    {
        if (empty($handle)) {
            return null;
        }
        $flights = [];
        $tmp = [];
        $delimiter = '________________________________';
        $blocks = explode($delimiter, $handle);

        foreach ($blocks as $block) {
            $search = ['/(:\n\n+)/', '/\[(.*)\]|<\n?(.*)\n?>/'];
            $replace = [': ', ""];
            $block = preg_replace($search, $replace, $block);

            if (preg_match('/AIR\s-\s/i', $block, $result_flight)) {
                $flights[] = preg_split('/\n\n/i', $block);
            }
        }

        foreach ($flights as $key => $flight) {
            foreach ($flight as $flightkey => $value) {
                if (!empty($value)) {
                    if (preg_match("/(From|To|Duration|Meal|Status|Seats):\s/", $value, $matches)) {
                        $assockey = preg_replace("/:\s/", "", $matches[0]);

                        switch ($assockey) {
                            case 'From':
                            case 'To':
                                $arrDep = preg_split('/\n/i', trim(str_replace($matches[0], "", $value)));
                                $date = preg_replace('/\shrs,\s([A-Z]{1}[a-z]+),/', '', trim($arrDep[1]));
                                $date = $this->stringDateToUnixtime($this->normalizeDate(trim(str_replace(' hrs', '', $date)) . ' ' . $this->year));

                                if ($assockey == 'From') {
                                    $tmp[$key]['DepName'] = $arrDep[0];
                                    $tmp[$key]['DepCode'] = TRIP_CODE_UNKNOWN;
                                    $tmp[$key]['DepDate'] = $date;
                                } else {
                                    $tmp[$key]['ArrName'] = $arrDep[0];
                                    $tmp[$key]['ArrCode'] = TRIP_CODE_UNKNOWN;
                                    $tmp[$key]['ArrDate'] = $date;
                                }

                                break;

                            default:
                                $tmp[$key][$assockey] = trim(str_replace($matches[0], "", $value));

                                break;
                        }
                    } else {
                        preg_match("/Operated/mi", $value, $result);
                        preg_match("/AIR - /", $value, $air);

                        if (!isset($result[0]) && !isset($air[0])) {
                            preg_match("/^\s\s[a-z]+/i", $value, $status);
                            preg_match("/[A-Z]+[0-9]+/", $value, $flightNumber);

                            if (isset($status[0])) {
                                $tmp[$key]['Status'] = trim($value);
                            } elseif (isset($flightNumber[0])) {
                                $tmp[$key]['FlightNumber'] = trim($flightNumber[0]);
                            } else {
                                $airline = preg_split("/\s\d{4,}\s/", $value);

                                if (isset($airline[1])) {
                                    $tmp[$key]['AirlineName'] = $airline[0];
                                    $tmp[$key]['Cabin'] = $airline[1];
                                }
                            }
                        }
                    }
                }
            }
        }

        unset($flights);
        $flights = $tmp;
        $result['Kind'] = "T";
        preg_match('/\s[A-Z].+\/[A-Z]+/', $handle, $name);
        $result['Passengers'] = (isset($name[0])) ? [trim($name[0])] : '';
        preg_match_all('/(^)?([A-Z]{2}[0-9]{7,}+,?)/m', $handle, $accountNumbers);

        if (isset($accountNumbers[0])) {
            foreach ($accountNumbers[0] as $number) {
                $nTmp[] = rtrim($number, ',');
            }
            $result['AccountNumbers'] = implode(',', $nTmp);
        }
        preg_match('/TICKETING AMOUNT:\s[A-Z]{3}\d{0,10}.\d{2}/m', $handle, $charge);

        if (isset($charge[0])) {
            $result['TotalCharge'] = preg_replace('/TICKETING AMOUNT:\s[A-Z]{3}/', '', $charge[0]);
            $result['Currency'] = preg_replace('/\d{0,10}\.\d{2}/', '', str_replace('TICKETING AMOUNT: ', '', $charge[0]));
        }
        preg_match('/Information for Trip Locator:\s[A-Z0-9]+\n?/', $handle, $locator);

        if (isset($locator[0])) {
            $result['RecordLocator'] = trim(str_replace('Information for Trip Locator:', '', $locator[0]));
        }
        $result['TripSegments'] = $flights;

        return [$result];
    }

    public function htmlItineraries()
    {
        $passengersRows = $this->http->FindNodes("//span[contains(text(), 'Passengers')]/../../../..//following-sibling::tr/td[2]//span");
        $accountsRows = $this->http->FindNodes("//span[contains(text(), 'Passengers')]/../../../..//following-sibling::tr/td[4]//span");
        $info = $this->http->FindSingleNode('//html/body/div[1]/div[1]/div/div/p[11]/span[1]');
        $flights = [];
        $hotels = [];
        preg_match_all('/[A-Z]{3}[0-9]+\.[0-9]{2,}/i', $info, $match);

        if (isset($match[0][1])) {
            $this->shared['RecordLocator'] = str_replace('Information for Trip Locator: ', '', $this->http->FindSingleNode("//span[contains(text(), 'Information for Trip Locator:')]"));
            $this->shared['Passengers'] = join(',', $passengersRows);
            $this->shared['Kind'] = "T";

            $this->shared['AccountNumbers'] = join(', ', $accountsRows);
            $this->shared['TotalCharge'] = preg_replace('/[A-Z]/i', '', $match[0][1]);
            $this->shared['BaseFare'] = preg_replace('/[A-Z]/i', '', $match[0][0]);
            $this->shared['Currency'] = preg_replace('/[0-9]+\.[0-9]{2,}/i', '', $match[0][0]);
            $itinerarySegments = $this->http->XPath->query("//tbody[count(tr) >=7 and tr[contains(., 'AIR - ')] or tr[contains(., 'HOTEL - ')]]/tr");
            $rawIteneraries = $this->separateItenararies($itinerarySegments);
            $flights = $this->getFlights($rawIteneraries->flights);
            $hotels = $this->getHotels($rawIteneraries->hotels);
        }

        return [$flights, $hotels];
    }

    public function separateItenararies($rows)
    {
        $rawData = (object) [
            'flights' => [],
            'hotels'  => [],
        ];
        $switcher = 'flights';

        foreach ($rows as $row) {
            if ($this->http->FindSingleNode(".//td[contains(., 'AIR - ')]", $row)) {
                $switcher = 'flights';
            }

            if ($this->http->FindSingleNode(".//td[contains(., 'HOTEL - ')]", $row)) {
                $switcher = 'hotels';
            }

            switch ($switcher) {
                case 'flights':
                    $rawData->flights[] = $row;

                    break;

                case 'hotels':
                    $rawData->hotels[] = $row;

                    break;
            }
        }

        return $rawData;
    }

    public function getFlights($rows)
    {
        $flights = [];
        $iterator = 0;
        $flights['Kind'] = $this->shared['Kind'];
        $flights['RecordLocator'] = $this->shared['RecordLocator'];
        $flights['Passengers'] = $this->shared['Passengers'];
        $flights['AccountNumbers'] = $this->shared['AccountNumbers'];
        $flights['TotalCharge'] = $this->shared['TotalCharge'];
        $flights['BaseFare'] = $this->shared['BaseFare'];
        $flights['Currency'] = $this->shared['Currency'];
        $segments = [];

        foreach ($rows as $flight) {
            if ($this->http->FindSingleNode(".//td[contains(., 'AIR - ')]", $flight)) {
                $iterator++;
            }

            if ($this->http->FindSingleNode(".//span[contains(., 'YOUR FLIGHT NUMBER IS')]", $flight)) {
                preg_match('/\s(([A-Za-z]{2,3})|([A-Za-z]\d)|(\d[A-Za-z]))(\d{1,})([A-Za-z]?)/', $this->http->FindSingleNode(".//span[contains(., 'YOUR FLIGHT NUMBER IS')]", $flight), $matches);
                $segments[$iterator]['FlightNumber'] = (isset($matches[0])) ? trim($matches[0]) : '';
            }
            $flightBasics = $this->http->XPath->query("./td[2]", $flight);

            foreach ($flightBasics as $row) {
                if ($this->http->FindSingleNode(".//span[contains(text(), 'From')]", $row)) {
                    $date = '';
                    $departure = $this->http->FindSingleNode(".//span[contains(text(), 'From')]/../../../../td[3]//span", $row);
                    preg_match('/(\d{4})\shrs,\s([A-Z]{1}[a-z]+),\s([A-Z]{1}[a-z]+)\s[0-9]{2}/', $departure, $date);
                    $segments[$iterator]['DepName'] = trim(preg_replace('/(\d{4})\shrs,\s([A-Z]{1}[a-z]+),\s([A-Z]{1}[a-z]+)\s[0-9]{2}\s.*/', '', $departure));

                    if (isset($date[0])) {
                        $date = preg_replace('/\shrs,\s([A-Z]{1}[a-z]+),/', '', trim($date[0]));
                        $date = $this->stringDateToUnixtime($this->normalizeDate(trim(str_replace(' hrs', '', $date)) . ' ' . $this->year));
                    }
                    $segments[$iterator]['DepDate'] = $date;
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'To')]", $row)) {
                    $date = '';
                    $arrival = $this->http->FindSingleNode(".//span[contains(text(), 'To')]/../../../../td[3]//span", $row);
                    preg_match('/(\d{4})\shrs,\s([A-Z]{1}[a-z]+),\s([A-Z]{1}[a-z]+)\s[0-9]{2}/', $arrival, $date);
                    $segments[$iterator]['ArrName'] = trim(preg_replace('/(\d{4})\shrs,\s([A-Z]{1}[a-z]+),\s([A-Z]{1}[a-z]+)\s[0-9]{2}\s.*/', '', $arrival));

                    if (isset($date[0])) {
                        $date = preg_replace('/\shrs,\s([A-Z]{1}[a-z]+),/', '', trim($date[0]));
                        $date = $this->stringDateToUnixtime($this->normalizeDate($date . ' ' . $this->year));
                    }
                    $segments[$iterator]['ArrDate'] = $date;
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Seats')]", $row)) {
                    $segments[$iterator]['Seats'] = trim($this->http->FindSingleNode(".//span[contains(text(), 'Seats')]/../../../../td[3]//td[1]//span", $row));
                }
            }

            if ($this->http->FindSingleNode(".//span[contains(text(), 'Equipment')]", $flight)) {
                $segments[$iterator]['Aircraft'] = $this->http->FindSingleNode(".//span[contains(text(), 'Equipment:')]/../../..//following-sibling::td//span", $flight);
            }

            if ($this->http->FindSingleNode(".//span[contains(text(), 'Equipment')]", $flight)) {
                $segments[$iterator]['Aircraft'] = $this->http->FindSingleNode(".//span[contains(text(), 'Equipment:')]/../../..//following-sibling::td//span", $flight);
            }

            if ($this->http->FindSingleNode(".//span[contains(text(), 'Meals')]", $flight)) {
                $segments[$iterator]['Meal'] = $this->http->FindSingleNode(".//span[contains(text(), 'Meals')]/../../..//following-sibling::td//span", $flight);
            }

            if ($this->http->FindSingleNode(".//span[contains(text(), 'Duration')]", $flight)) {
                $segments[$iterator]['Duration'] = $this->http->FindSingleNode(".//span[contains(text(), 'Duration')]/../../..//following-sibling::td//span", $flight);
            }

            $segments[$iterator]['DepCode'] = TRIP_CODE_UNKNOWN;
            $segments[$iterator]['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        $flights['TripSegments'] = $segments;

        return $flights;
    }

    public function getHotels($rows)
    {
        $hotels = [];
        $hotels['Kind'] = 'R';

        foreach ($rows as $row) {
            if ($this->http->FindSingleNode(".//span/img/ancestor-or-self::td[1]/following-sibling::td//span", $row)) {
                $hotels['HotelName'] = $this->http->FindSingleNode(".//span/img/ancestor-or-self::td[1]/following-sibling::td//span", $row);
            }

            if ($this->http->FindSingleNode(".//span[contains(text(), 'HOTEL - ')]", $row)) {
                $date = $this->http->FindSingleNode(".//span[contains(text(), 'HOTEL - ')]", $row) . ' ' . $this->year;
                $date = preg_replace('/[A-Z]+?\s\-\s([A-Z]{1}[a-z]+?),/', '', trim($date));
                $hotels['CheckInDate'] = $this->stringDateToUnixtime($this->normalizeDate('0000 ' . $date));
            }

            $hotelAdditional = $this->http->XPath->query("./td[4]", $row);

            foreach ($hotelAdditional as $additionalRow) {
                if ($this->http->FindSingleNode(".//span[contains(text(), 'Chain')]", $additionalRow)) {
                    $hotels['2ChainName'] = trim($this->http->FindSingleNode(".//span[contains(text(), 'Chain')]/../../../../td[2]//span", $additionalRow));
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Rate')]", $additionalRow)) {
                    $hotels['Cost'] = trim($this->http->FindSingleNode(".//span[contains(text(), 'Rate')]/../../../../td[2]//span", $additionalRow));
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Status')]", $additionalRow)) {
                    $hotels['Status'] = trim($this->http->FindSingleNode(".//span[contains(text(), 'Status')]/../../../../td[2]//span", $additionalRow));
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Check')]", $additionalRow)) {
                    $date = trim($this->http->FindSingleNode(".//span[contains(text(), 'Check')]/../../../../td[2]//span", $additionalRow)) . ' ' . $this->year;
                    $date = preg_replace('/([A-Z]{1}[a-z]+),/', '', trim($date));
                    $hotels['CheckOutDate'] = $this->stringDateToUnixtime($this->normalizeDate('0000 ' . $date));
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Confirmation')]", $additionalRow)) {
                    $hotels['ConfirmationNumber'] = trim($this->http->FindSingleNode(".//span[contains(text(), 'Confirmation')]/../../../../td[2]//span", $additionalRow));
                }
            }
            $hotelBasic = $this->http->XPath->query("./td[2]", $row);

            foreach ($hotelBasic as $basicRow) {
                if ($this->http->FindSingleNode(".//span[contains(text(), 'Address')]", $basicRow)) {
                    $hotels['Address'] = $this->http->FindSingleNode(".//span[contains(text(), 'Address')]/../../../../td[3]//span", $basicRow);
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Telephone')]", $basicRow)) {
                    $hotels['Phone'] = $this->http->FindSingleNode(".//span[contains(text(), 'Telephone')]/../../../../td[3]//span", $basicRow);
                }

                if ($this->http->FindSingleNode(".//span[contains(text(), 'Fax')]", $basicRow)) {
                    $hotels['Fax'] = $this->http->FindSingleNode(".//span[contains(text(), 'Fax')]/../../../../td[3]//span", $basicRow);
                }
            }
        }

        return $hotels;
    }

    public function stringDateToUnixtime($date)
    {
        $date = date_parse_from_format($this::DATE_FORMAT, $date);

        return mktime($date['hour'], $date['minute'], 0, $date['month'], $date['day'], $date['year']);
    }

    public function normalizeDate($date)
    {
        $date = preg_replace('/(\(|\)|(P|A)M)/i', '', $date);
        $matches = [];

        if (preg_match('/(.+)\s+(\d+)\s+(.+)/', $date, $matches)) { // [1] -> time, [2] -> date, [3] -> month, [4] -> year
            array_shift($matches);
            $date = join(' ', $matches);
        }

        return $date;
    }
}
