<?php

namespace AwardWallet\Engine\thaiair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-2.eml, thaiair/it-3.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]thaiairways\.com/ims", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/([\.@]thaiairways\.com)/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));

        if (preg_match("#FLIGHT\s+CL\s+DATE\s+DEP\s+FARE\s+BASIS\s+NVB\s+NVA#ms", $parser->getHtmlBody())) {
            return $this->ParsePlanEmailTicket($parser);
        } elseif (preg_match("#ARR\s+FLT\s+\s#ms", $parser->getHtmlBody())) {
            return $this->ParsePlainVoucherEmail($parser);
        } elseif (preg_match("#\w+\s+\d+\s+(\w{2}\d{2}\w{3})#", $parser->getPlainBody())) {
            return $this->ParseAttachedHtmlEmail($parser);
        } elseif ($this->http->FindSingleNode("//th[contains(., 'Confirmation for reservation')]")) {
            // Parser toggled off as it is covered by 'emailConfirmationForReservationChecker.php'
            return null;
        //			return $this->ParsePlanEmailReservation($parser);
        } else {
            return $this->ParsePlanEmailTicket($parser);
        }
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public function ParsePlanEmailTicket(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $itineraries = [];
        $itineraries['Kind'] = 'T';

        if (preg_match('/<pre>(.*)<\\/pre>/is', $body, $matches)) {
            $ticket = $matches[1];

            $data = explode("\n", $ticket);
            $Year = date('Y', $this->date);
            $TicketDate = 0;

            foreach ($data as $m=>$row) {
                // AUTOMATE TKT                         DATE :11JUL13
                // ISSUING AIRLINE                         : THAI AIRWAYS INTL
                if (preg_match('/AUTOMATE TKT\s+DATE\s*:\s*(?P<date>\S+)/', $row, $matches)) {
                    $TicketDate = strtotime($matches['date']);
                    $Year = date('Y', $TicketDate);
                }

                //  *BOOKING REFERENCE FOR KIOSK/INTERNET CHECK-IN:             S6ST9A
                if (preg_match('/BOOKING REFERENCE FOR KIOSK\\/INTERNET CHECK-IN:\s+(?P<ref>\S+)/', $row, $matches)) {
                    $itineraries['RecordLocator'] = $matches['ref'];
                }
                //  TOTAL             :  THB   18980
                elseif (preg_match('/TOTAL\s+:\s*(?P<currency>\S+)\s+(?P<charge>[\d\.]+)/', $row, $matches)) {
                    $itineraries['Currency'] = $matches['currency'];
                    $itineraries['TotalCharge'] = $matches['charge'];
                }
                // BKKHPTG                              NAME :RIDLEY/KRISTIANALEX MR
                elseif (preg_match('/NAME :\s*(?P<names>.+)/', $row, $matches)) {
                    $itineraries['Passengers'] = explode(',', beautifulName(str_replace('/', ' ', trim($matches['names']))));

                    foreach ($itineraries['Passengers'] as $k=> $name) {
                        if (strtolower(substr($name, -2)) == 'mr') {
                            $itineraries['Passengers'][$k] = 'Mr. ' . trim(substr($name, 0, -2));

                            continue;
                        }

                        if (strtolower(substr($name, -3)) == 'mrs') {
                            $itineraries['Passengers'][$k] = 'Mrs. ' . trim(substr($name, 0, -3));

                            continue;
                        }
                    }
                }
            }
            $is_it = 0;
            $is_empty_str = 0;
            $segments = [];

            foreach ($data as $m=>$row) {
                // FROM / TO        FLIGHT CL DATE  DEP   FARE BASIS    NVB   NVA      BAG ST
                if (preg_match('/FROM \\/ TO        FLIGHT CL DATE  DEP   FARE BASIS    NVB   NVA      BAG ST/', $row, $matches)) {
                    $is_it = 1;
                    $is_empty_str = 0;
                }
                // {empty string}*2
                elseif (preg_match('/^\s+$/', $row, $matches)) {
                    $is_empty_str++;

                    if ($is_empty_str == 2) {
                        $is_it = 0;
                    }
                }
                // BANGKOK SUVARNAB TG0916 X  25AUG 1250   XBP00TG5EEP        31OCT    20K OK
                // 0         1         2         3         4         5         6         7         8
                // 012345678901234567890123456789012345678901234567890123456789012345678901234567890
                elseif (strlen(trim($row)) > 16 && $is_it) {
                    $is_empty_str = 0;
                    $row = trim($row);
                    $segment = [];
                    $segment['FlightNumber'] = trim(substr($row, 17, 6));
                    $segment['DepCode'] = TRIP_CODE_UNKNOWN;
                    $segment['DepName'] = trim(substr($row, 0, 16));
                    $segment['DepDate'] = strtotime(trim(substr($row, 27, 5)) . $Year . ' ' . substr($row, 33, 2) . ':' . substr($row, 35, 2), $this->date);
                }
                // LONDON   HEATHRO
                elseif ($is_it) {
                    $is_empty_str = 0;
                    $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $segment['ArrName'] = trim($row);
                    // todo no information in letter
                    $segment['ArrDate'] = $segment['DepDate'];
                    $segments[] = $segment;
                }
            }
            // fix years
            foreach ($segments as $m=>$segment) {
                if ($m > 0 && $segment['DepDate'] < $segments[$m - 1]['DepDate']) {
                    $segments[$m]['DepDate'] = mktime(0, 0, 0,
                        date('m', $segment['DepDate']),
                        date('d', $segment['DepDate']),
                        date('Y', $segment['DepDate']) + 1);
                } elseif ($m == 0 && $TicketDate > 0 && $segment['DepDate'] < $TicketDate) {
                    $segments[$m]['DepDate'] = mktime(0, 0, 0,
                        date('m', $segment['DepDate']),
                        date('d', $segment['DepDate']),
                        date('Y', $segment['DepDate']) + 1);
                }
            }

            $itineraries['TripSegments'] = $segments;
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function ParsePlanEmailReservation(\PlancakeEmailParser $parser)
    {
        $itineraries = null;
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//td[contains(., 'Reservation Code')]/span");

        if (!$itineraries['RecordLocator']) {
            $trip = $this->http->FindSingleNode("//*[contains(text(), 'Your trip reservation')]/ancestor::table[1]/following-sibling::*[1]");
            $itineraries['RecordLocator'] = preg_match("#reservation number\s*:\s*([A-Z\d\-]+)#", $trip, $m) ? $m[1] : null;
        }

        $itineraries['Status'] = $this->http->FindSingleNode("//td[contains(., 'Reservation status')]/span");
        //$itineraries['Passengers'] = array($this->http->FindSingleNode("//td[contains(text(),'Frequent flyer' )]/ancestor-or-self::table[2]//tr[2]/td/span"));
        $itineraries['Passengers'] = $this->http->FindNodes("//td[contains(text(),'Frequent flyer' )]/ancestor::tr[2]/preceding-sibling::tr[2]");

        $itineraries['AccountNumbers'] = $this->http->FindSingleNode("//td[contains(text(),'Frequent flyer' )]/following-sibling::td");
        $payment = $this->http->XPath->query("//tr[contains(., 'total for all travellers') and count(td) > 2]");

        $itineraries['TripSegments'] = $this->getSegments($this->http->XPath->query("//table//*[contains(., 'Flight') and count(tr) > 4 and not(contains(., 'Flight payment'))  and not(contains(., 'Flight Notes'))]"));

        foreach ($payment as $cell) {
            $currency = explode(' ', str_replace(['(', ')'], ['', ''], $this->http->FindSingleNode(".//*[contains(text(), 'total for all travellers')]/ancestor::tr[1]/td[1]", $cell)));

            foreach ($currency as $val) {
                preg_match('/\d+(.\d+)?.\d+/', $val, $resultMoney);

                if (isset($resultMoney[0])) {
                    $itineraries['TotalCharge'] = str_replace(',', '', $resultMoney[0]);
                }
                preg_match('/[A-Z]{3}/', $val, $resultCurrency);

                if (isset($resultCurrency[0])) {
                    $itineraries['Currency'] = str_replace(',', '', $resultCurrency[0]);
                }
            }
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }

    public function getSegments($rows)
    {
        $segments = [];

        foreach ($rows as $row) {
            $count = count($this->http->FindNodes(".//span[contains(text(), 'Departure')]/../following-sibling::td[2]", $row));

            $arrFlight = $this->http->FindNodes(".//td[contains(text(), 'Airline')]/following-sibling::td[1]", $row);
            $arrArrName = $this->http->FindNodes(".//span[contains(text(), 'Arrival')]/../following-sibling::td[2]", $row);
            $arrDepName = $this->http->FindNodes(".//span[contains(text(), 'Departure')]/../following-sibling::td[2]", $row);
            $arrDate = $this->http->FindNodes(".//td[contains(text(), 'Flight')]/following-sibling::td[1]", $row);
            $arrTime1 = $this->http->FindNodes(".//span[contains(text(), 'Departure')]/../following-sibling::td[1]", $row);
            $arrTime2 = $this->http->FindNodes(".//span[contains(text(), 'Arrival')]/../following-sibling::td[1]", $row);
            $arrAircraft = $this->http->FindNodes(".//td[contains(text(), 'Aircraft')]/following-sibling::td[1]", $row);

            for ($i = 0; $i < $count; $i++) {
                $dataGarbage = [];

                if (preg_match('/^(.*?)\s+([A-Z]+[0-9]+)$/', $arrFlight[$i], $flightNumber)) {
                    $dataGarbage['FlightNumber'] = $flightNumber[2];
                    $dataGarbage['AirlineName'] = $flightNumber[1];
                }

                $dataGarbage['DepName'] = $arrDepName[$i];

                $date = $arrDate[$i];
                $depTime = $arrTime1[$i];
                $dataGarbage['DepDate'] = strtotime($date . ' ' . $depTime, $this->date);

                $dataGarbage['ArrName'] = $arrArrName[$i];
                $arrTime = $arrTime2[$i];

                $dataGarbage['ArrDate'] = strtotime($date . ' ' . $arrTime);
                $dataGarbage['Aircraft'] = $arrAircraft[$i];

                $dataGarbage['DepCode'] = TRIP_CODE_UNKNOWN;
                $dataGarbage['ArrCode'] = TRIP_CODE_UNKNOWN;

                $segments[] = $dataGarbage;
            }
        }

        return $segments;
    }

    private function ParsePlainVoucherEmail($parser)
    {
        $text = $parser->getHtmlBody();

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match("#\nNAME\s+([^\n]+)#", $text, $m)) {
            $it['Passengers'] = $m[1];
        }

        if (preg_match("#BOOKING REF\s*\-*\s*\w+/([^\s]+)#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $it['TripSegments'] = [];

        //ARR FLT       TG  518 B MO 01OCT DXBBKK HK   2055 0640
        //DEP FLT       TG  475 B TU 02OCT BKKSYD HK   1920 0720
        preg_replace_callback("#(?:ARR|DEP)\s+FLT\s+(\w+\s+\d+)\s+\w+\s+\w+\s+(\d{1,2}\w{3})\s+(\w{3})(\w{3})\s+\w+\s+(\d{2})(\d{2})\s+(\d{2})(\d{2})#ms", function ($m) use (&$it) {
            $seg = [];

            $seg['FlightNumber'] = preg_replace("#\s+#", ' ', $m[1]);

            $seg['DepDate'] = strtotime($m[2] . ', ' . $m[5] . ':' . $m[6], $this->date);
            $seg['DepName'] = $m[3];
            $seg['DepCode'] = $m[3];

            $seg['ArrDate'] = strtotime($m[2] . ', ' . $m[7] . ':' . $m[8], $this->date);
            $seg['ArrName'] = $m[4];
            $seg['ArrCode'] = $m[4];

            $it['TripSegments'][] = $seg;
        }, $text);

        /*
        IN-OUT DATE   02OCT12-02OCT12

        HOTEL         NOVOTEL SUVARNABHUMI-STPC BKD/TEL.662-131-1111
        ROOM          1SGLB TOTAL 1ROOM SUPERIOR
        SERVICE INCL  STPC: ACCOMMODATION ONLY FOR DAY USE FOR LAYOVER PASSENGER
                            ON TG EXPENSE.
                      01JAN12-31DEC12 TRSFED BY HTL SHUTTLE BUS :2ND FLR/GATE 4. EVERY
                      15MINS
                      */

        $ho = ['Kind' => 'R'];
        $ho['ConfirmationNumber'] = $it['RecordLocator'];

        if (preg_match("#\nIN\-OUT DATE\s+(\d{2}\w{3}\d{2})\s*\-\s*(\d{2}\w{3}\d{2})#ms", $text, $m)) {
            $ho['CheckInDate'] = strtotime($m[1]);
            $ho['CheckOutDate'] = strtotime($m[2]);
        }

        if (preg_match("#\nHOTEL\s+([^\n]+)#", $text, $m)) {
            $ho['HotelName'] = $ho['Address'] = trim($m[1]);

            if (preg_match("#^(.*?)/TEL.(.*?)$#", $ho['HotelName'], $m)) {
                $ho['Address'] = $ho['HotelName'] = $m[1];
                $ho['Phone'] = $m[2];
            }

            if (preg_match("#^(.*?)\s*\-\s*(.*?)$#", $ho['HotelName'], $m)) {
                $ho['HotelName'] = $m[1];
                $ho['Address'] = $m[2];
            }
        }

        if (preg_match("#\nROOM\s+([^\n]+)#", $text, $m)) {
            $ho['RoomTypeDescription'] = trim($m[1]);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it, $ho],
            ],
        ];
    }

    private function ParseAttachedHtmlEmail($parser)
    {
        $text = $parser->getPlainBody();

        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $re = "(\w{2}\s+\d{2,})\s+\w{2}(\d{1,2}\w{3})\s+(.*?)\s+(\d{2})(\d{2})\s*/\s*(.*?)\s+(\d{2})(\d{2})";

        if (preg_match("#\n(\w+/\w+)\s+$re#", $text, $m)) {
            $it['Passengers'] = $m[1];
        }

        $it['TripSegments'] = [];

        //NZ  850    FR21OCT Melbourne       1840 /  Wellington       0010 +1
        preg_replace_callback("#$re#ms", function ($m) use (&$it) {
            $seg = [];

            $seg['FlightNumber'] = preg_replace("#\s+#", ' ', $m[1]);

            $seg['DepDate'] = strtotime($m[2] . ', ' . $m[4] . ':' . $m[5], $this->date);
            $seg['DepName'] = $m[3];
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $seg['ArrDate'] = strtotime($m[2] . ', ' . $m[7] . ':' . $m[8], $this->date);
            $seg['ArrName'] = $m[6];
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $seg;
        }, $text);

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }
}
