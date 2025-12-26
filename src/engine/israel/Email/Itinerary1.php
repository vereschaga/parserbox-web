<?php

namespace AwardWallet\Engine\israel\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "israel/it-2.eml, israel/it-3.eml, israel/it-4.eml, israel/it-4885837.eml, israel/it-4885848.eml, israel/it-4901121.eml, israel/it-6110478.eml";

    public $reSubject = ["EL AL E-TICKET", "אישור להזמנה", "שינויים בבקשה המיוחדת להזמה"];
    public $reBody = ["Thank you for choosing ELAL Israel", "EL AL E-Ticket", "תודה על שבחרת ב", "הבקשות המיוחדות הקשורות להזמנה "];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    return true;
                }
            }
        }

        return false;
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
        return stripos($from, "elal-ticketing.com") !== false || stripos($from, "elal.co.il") !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'EL AL E-Ticket') !== false) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->B_email($body)],
                ],
            ];
        } elseif (stripos($body, 'Confirmation for reservation ')) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->A_email()],
                ],
            ];
        } elseif (mb_stripos($body, 'אישור להזמנה') || mb_stripos($body, 'הבקשות המיוחדות הקשורות להזמנה ') || mb_stripos($body, 'קבלת כרטיס אלקטרוני')) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->C_email()],
                ],
            ];
        }

        return [];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en", "he"];
    }

    public function A_email()
    {
        $itineraries['Kind'] = 'T';

        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//td[contains(text(), 'Booking reservation number')]", null, true, '#Booking reservation number:\s*.+?([A-Z\d]+)#');

        if ($this->http->FindSingleNode("//tr/td[contains(text(),'Trip status')]/span") == 'Cancelled') {
            $itineraries['Cancelled'] = true;
            $itineraries['Status'] = 'Cancelled';
        } else {
            $itineraries['Status'] = $this->http->FindSingleNode("//tr/td[contains(text(),'Trip status')]", null, true, '#Trip status:\s*(\S+)#');
        }

        $itineraries['Passengers'] = $this->passengers()['Passengers'];
        $itineraries['TripSegments'] = $this->segments();

        return $itineraries;
    }

    public function passengers()
    {
        $itineraries['Passengers'] = '';
        $nodes = $this->http->XPath->query("//table[thead[tr[td[contains(text(),'Seat and Meal requests:')]]]]/tbody/tr[td]");
        $b = 0;
        $this->http->Log("Total nodes found " . $nodes->length);
        $Seat = $this->http->FindNodes("//table[thead[tr[td[contains(text(),'Seat and Meal requests:')]]]]/tbody/tr/td[2]");

        for ($i = 1; $i <= $nodes->length; $i++) {
            if ($this->http->FindSingleNode("//table[thead[tr[td[contains(text(),'Seat and Meal requests:')]]]]/tbody/tr[" . $i . "]/td[1]") != '') {
                $name[$b] = $this->http->FindSingleNode("//table[thead[tr[td[contains(text(),'Seat and Meal requests:')]]]]/tbody/tr[" . $i . "]/td[1]");

                $itineraries['TripSegments'][$b]['Seats'] = $Seat[$i - 1];

                if ($b == 0) {
                    $itineraries['Passengers'] = $name[$b];
                } else {
                    $itineraries['Passengers'] = $itineraries['Passengers'] . ',' . $name[$b];
                }

                $b++;
            }
        }

        return $itineraries;
    }

    public function segments()
    {
        $itineraries['TripSegments'] = [];
        $nodes = $this->http->XPath->query("//tr[td[contains(text(),'Airline:')]]");
        $this->http->Log("Total nodes found " . $nodes->length);

        $airline = $this->http->FindNodes("//tr[td[contains(text(),'Airline:')]]");
        $dateTime[0] = $this->http->FindNodes("//tr[td[span[contains(text(),'Departure:')]]]", null, '#Departure:\s*(\d\d:\d\d)\s*.*#');
        $dateTime[1] = $this->http->FindNodes("//tr[td[span[contains(text(),'Arrival:')]]]", null, '#Arrival:\s*?(\d\d:\d\d\s*?\+\d|\d\d:\d\d)\s*.*#');
        $date = $this->http->FindNodes("//tr[td[contains(text(),'Flight')]]", null, '#Flight\s*\d\s*(.*)#');
        $fareType = $this->http->FindNodes("//tr[td[contains(text(),'Fare type:')]]", null, '#Fare type:\s*(.*)#');
        $Meal = $this->http->FindNodes("//tr[td[contains(text(),'Meal:')]]", null, '#Meal:\s*(.*)#');
        $DepName = $this->http->FindNodes("//tr[td[span[contains(text(),'Departure:')]]]", null, '#Departure:\s*?(?:\d\d:\d\d\s*?\+\d\s*\w\w\w...|\d\d:\d\d)\s*(.*).*#');
        $ArrName = $this->http->FindNodes("//tr[td[span[contains(text(),'Arrival:')]]]", null, '#Arrival:\s*?(?:\d\d:\d\d\s*?\+\d\s*\w\w\w...|\d\d:\d\d)\s*(.*).*#');

        for ($i = 0; $i < $nodes->length; $i++) {
            if (isset($airline[$i])) {
                preg_match('#Airline:\s*.*?(\S\S\d\d\d\d|\d\d\d\d|\d\d\d\d\d\d)\s*Aircraft:\s*(.*)#', $airline[$i], $airline[$i]);

                if (isset($airline[$i])) {
                    $itineraries['TripSegments'][$i]['FlightNumber'] = arrayVal($airline[$i], 1);
                }
            }

            if (isset($fareType[$i])) {
                $itineraries['TripSegments'][$i]['Cabin'] = $fareType[$i];
            }

            if (isset($Meal[$i])) {
                $itineraries['TripSegments'][$i]['Meal'] = $Meal[$i];
            }
            $itineraries['TripSegments'][$i]['DepCode'] = TRIP_CODE_UNKNOWN;

            if (isset($DepName[$i])) {
                $itineraries['TripSegments'][$i]['DepName'] = $DepName[$i];
            } else {
                $itineraries['TripSegments'][$i]['DepName'] = '';
            }

            if (isset($ArrName[$i])) {
                $itineraries['TripSegments'][$i]['ArrName'] = $ArrName[$i];
            } else {
                $itineraries['TripSegments'][$i]['ArrName'] = '';
            }

            if ($this->UnixTime($date[$i], $dateTime[0][$i])) {
                $itineraries['TripSegments'][$i]['DepDate'] = $this->UnixTime($date[$i], $dateTime[0][$i]);
            } else {
                $itineraries['TripSegments'][$i]['DepDate'] = '';
            }

            if ($this->UnixTime($date[$i], $dateTime[1][$i])) {
                $itineraries['TripSegments'][$i]['ArrDate'] = $this->UnixTime($date[$i], $dateTime[1][$i]);
            } else {
                $itineraries['TripSegments'][$i]['ArrDate'] = $this->UnixTime($date[$i], $dateTime[1][$i]);
            }

            if (isset($airline[$i][2])) {
                $itineraries['TripSegments'][$i]['Aircraft'] = $airline[$i][2];
            }

            if (isset($this->passengers()['TripSegments'][$i]['Seats'])) {
                $itineraries['TripSegments'][$i]['Seats'] = $this->passengers()['TripSegments'][$i]['Seats'];
            }

            $itineraries['TripSegments'][$i]['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        return $itineraries['TripSegments'];
    }

    public function UnixTime($date, $dateTime)
    {
        $date = explode(',', $date);
        $azb[1] = trim($date[1]);
        $date[2] = trim($date[2]);
        $month = explode(' ', $azb[1]);

        if (preg_match('#[\+|\-]#', $dateTime)) {
            $UnixTime = strtotime(($month[1] . ' ' . $month[0] . ' ' . $date[2] . ' ' . $dateTime . ' day'));
        } else {
            $UnixTime = strtotime(($month[1] . ' ' . $month[0] . ' ' . $date[2] . ' ' . $dateTime));
        }

        return $UnixTime;
    }

    public function B_email($body)
    {
        $itineraries['Kind'] = 'T';

        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'Reservation Code')]]]]/following-sibling::tr[2]/td[2]");

        if ($this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[2]/td[8]") == 'Cancelled') {
            $itineraries['Cancelled'] = true;
        }
        $itineraries['Status'] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[2]/td[8]");
        $Charge = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'Total Amount:')]]]]/td[3]");

        if (isset($Charge)) {
            $charge = explode(' ', $Charge);
        }

        if (isset($charge[1])) {
            $itineraries['TotalCharge'] = $charge[1];
        }

        if (isset($charge[0])) {
            $itineraries['Currency'] = $charge[0];
        }

        $passangers = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'Passenger')]]]]/following-sibling::tr[2]/td[2]");

        if (preg_match('#(.*)\(#', $passangers, $match)) {
            $itineraries['Passengers'] = $match[1];
        }
        $itineraries['TripSegments'] = $this->B_segments($body);

        return $itineraries;
    }

    public function B_segments($body)
    {
        $nodes = $this->http->FindNodes("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[count(td)>7]");
        $c = 0;
        $i = 2;
        $segments = [];

        while (isset($nodes[$c])) {
            $From[$c] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[2]");

            if (preg_match('#(.*)\s*\((\w*)\)#', $From[$c], $match)) {
                $segments[$c]['DepCode'] = $match[2];
                $segments[$c]['DepName'] = $match[1];
            }

            $To = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[3]");

            if (preg_match('#(.*)\s*\((\w*)\)#', $To, $match)) {
                $segments[$c]['ArrCode'] = $match[2];
                $segments[$c]['ArrName'] = $match[1];
            }
            $time[$c] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[2]/td[7]");
            $year[$c] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'Reservation Code')]]]]/following-sibling::tr[2]/td[4]");

            if (preg_match('#\d*\w{3}(\d*)#', $year[$c], $match)) {
                $year[$c] = $match[1];
            }
            $day_month[$c] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[6]");

            if (preg_match('#(\d*)(\w{3})#', $day_month[$c], $match)) {
                $day = $match[1];
                $month = $match[2];
            } else {
                $day = $month = "";
            }

            $segments[$c]['DepDate'] = strtotime($day . ' ' . $month . ' 20' . $year[$c] . ' ' . $time[$c]);
            $segments[$c]['ArrDate'] = strtotime($day . ' ' . $month . ' 20' . $year[$c] . ' ' . $time[$c]);

            if ($this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[4]"))
                ;
            $segments[$c]['FlightNumber'] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[4]");

            if ($this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[5]"))
                ;
            $segments[$c]['Cabin'] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[2]/td[5]");
//            if ($this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[" . $i . "]/td[8]"));
//            $segments[$c]['Status'] = $this->http->FindSingleNode("//tr[td[div[span[contains(text(),'From')]]]]/following-sibling::tr[2]/td[8]");
            $i++;
            $c++;
        }

        return $segments;
    }

    public function C_email()
    {
        $itineraries['Kind'] = 'T';
        //$itineraries['RecordLocator'] = $this->http->FindSingleNode("//td[contains(text(),'מספר הזמנה:')]", null, true, '#מספר הזמנה:\s*(\S*)#');
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//td[contains(text(),'מספר הזמנה:')]", null, true, '#([A-Z\d]+)#');
        $itineraries['Passengers'] = $this->http->FindNodes("//tr[th[contains(text(),'מסמך')]]/td");
        $nodes = $this->http->XPath->query("//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]");
        $this->logger->info("Total nodes found " . $nodes->length);
        //             SEGMENTS
        for ($i = 0; $i <= $nodes->length - 1; $i++) {
            $one = false;

            if (($month[$i] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])])[$i+1]", null, false, '#(\d{1,2}\s+\S+?\s+\d{4})#'))) {
                $one = true;
            } else {
                $month[$i] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])])[$i+1]", null, false, '#\,\s*(.*?)\s*\d+#');
            }

            $date[$i] = explode(', ', $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])])[$i+1]", null, false, '#\d+\,\s*\d+#'));
            $Deptime[$i] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//*[contains(text(),'המראה')]and not(.//table)])[$i+1]", null, false, '#(\d+\:\d+)#');

            if ($one) {
                $itineraries['TripSegments'][$i]['DepDate'] = strtotime($this->dateHeToEn($month[$i] . ' ' . $Deptime[$i]));
            } else {
                $itineraries['TripSegments'][$i]['DepDate'] = strtotime($date[$i][0] . ' ' . $this->month($month[$i]) . ' ' . $date[$i][1] . ' ' . $Deptime[$i]);
            }

            $itineraries['TripSegments'][$i]['DepName'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//*[contains(text(),'המראה')]and not(.//table)])[$i+1]", null, false, '#\d+\:\d+\s*(.*)#');

            if (preg_match("#(.+),\s*(terminal.+)#i", $itineraries['TripSegments'][$i]['DepName'], $m)) {
                $itineraries['TripSegments'][$i]['DepName'] = $m[1];
                $itineraries['TripSegments'][$i]['DepartureTerminal'] = $m[2];
            }

            $itineraries['TripSegments'][$i]['DepCode'] = TRIP_CODE_UNKNOWN;

            if (!($Arrtime[$i] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//*[contains(text(),'נחיתה:')]and not(.//table)])[$i+1]", null, false, '#(\d+\:\d+)#'))) {
                $Arrtime[$i] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//*[contains(text(),'חזור:')]and not(.//table)])[$i+1]", null, false, '#(\d+\:\d+)#');
            }

            if ($one) {
                $itineraries['TripSegments'][$i]['ArrDate'] = strtotime($this->dateHeToEn($month[$i] . ' ' . $Arrtime[$i]));
            } else {
                $itineraries['TripSegments'][$i]['ArrDate'] = strtotime($date[$i][0] . ' ' . $this->month($month[$i]) . ' ' . $date[$i][1] . ' ' . $Arrtime[$i]);
            }
            $itineraries['TripSegments'][$i]['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (!($itineraries['TripSegments'][$i]['ArrName'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//*[contains(text(),'נחיתה:')]and not(.//table)])[$i+1]", null, false, '#\d+\:\d+\s*(.*)#'))) {
                $itineraries['TripSegments'][$i]['ArrName'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//*[contains(text(),'חזור:')]and not(.//table)])[$i+1]", null, false, '#\d+\:\d+\s*(.*)#');
            }

            if (preg_match("#(.+),\s*(terminal.+)#i", $itineraries['TripSegments'][$i]['ArrName'], $m)) {
                $itineraries['TripSegments'][$i]['ArrName'] = $m[1];
                $itineraries['TripSegments'][$i]['ArrivalTerminal'] = $m[2];
            }

            $itineraries['TripSegments'][$i]['FlightNumber'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//td[contains(text(),'חברת תעופה:')]and not(.//table/.//table)])[$i+1]", null, false, '#.+?\s+[A-Z\d]{2}\s*(\d+)#'); //'#חברת תעופה:\s*.*?\s*(\w+\d+|\d+\w+).*#');
            $itineraries['TripSegments'][$i]['AirlineName'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//td[contains(text(),'חברת תעופה:')]and not(.//table/.//table)])[$i+1]", null, false, '#.+?\s+([A-Z\d]{2})\s*\d+#');

            if (!($itineraries['TripSegments'][$i]['Aircraft'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//td[contains(text(),'חברת תעופה:')]and not(.//table/.//table)]/.//tr[.//td[contains(text(),'סוג')]])[$i+1]", null, false, '#סוג מטוס\s*(.*)#'))) {
                $itineraries['TripSegments'][$i]['Aircraft'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//td[contains(text(),'חברת תעופה:')]and not(.//table/.//table)]/.//tr[.//td[contains(text(),'מטוס:')]])[$i+1]", null, false, '#מטוס:\s+(.*)#');
            }
            $itineraries['TripSegments'][$i]['Cabin'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'הזמנתך נקלטה במער')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[.//td[contains(text(),'סוג מחלקה:')]and not(.//table/.//table)])[$i+1]", null, false, '#סוג מחלקה:\s*(.*)#');
            $itineraries['TripSegments'][$i]['Meal'] = '';
            $itineraries['TripSegments'][$i]['Seats'] = '';

            for ($c = 0; $c <= count($itineraries['Passengers']) - 1; $c++) {
                if ($itineraries['TripSegments'][$i]['Meal'] != '') {
                    $itineraries['TripSegments'][$i]['Meal'] = $itineraries['TripSegments'][$i]['Meal'] . ', ' . $this->http->FindSingleNode("(//table[.//*[contains(text(),'העדפת ארוחות:*')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[1]/following-sibling::tr[$c+1]/td[3])[1]");
                    $itineraries['TripSegments'][$i]['Seats'] = $itineraries['TripSegments'][$i]['Seats'] . ', ' . $this->http->FindSingleNode("(//table[.//*[contains(text(),'העדפת ארוחות:*')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[1]/following-sibling::tr[$c+1]/td[2])[1]");
                } else {
                    $itineraries['TripSegments'][$i]['Meal'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'העדפת ארוחות:*')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[1]/following-sibling::tr[$c+1]/td[3])[1]");
                    $itineraries['TripSegments'][$i]['Seats'] = $this->http->FindSingleNode("(//table[.//*[contains(text(),'העדפת ארוחות:*')]and not(.//table/.//table/.//table)and not(.//*[contains(text(),'מספר הזמנה:')])]/.//tr[1]/following-sibling::tr[$c+1]/td[2])[1]");
                }
            }
        }

        return $itineraries;
    }

    public function dateHeToEn($m)
    {
        $languages = [
            'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'he' => ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'],
        ];

        foreach ($languages['he'] as $i => $v) {
            $m1 = str_replace($v, $languages['en'][$i], $m);

            if ($m1 !== $m) {
                $m = $m1;

                break;
            }
            $m = $m1;
        }

        return $m;
    }

    public function month($month)
    {
        switch ($month) {
            case 'ינואר':
                return 'jan';

            case 'פברואר':
                return 'feb';

            case 'מרץ':
                return 'mar';

            case 'אפריל':
                return 'apr';

            case 'מאי':
                return 'may';

            case 'יוני':
                return 'jun';

            case 'יולי':
                return 'jul';

            case 'אוגוסט':
                return 'aug';

            case 'ספטמבר':
                return 'sep';

            case 'אוקטובר':
                return 'oct';

            case 'נובמבר':
                return 'nov';

            case 'דצמבר':
                return 'dec';
        }

        return $month;
    }
}
