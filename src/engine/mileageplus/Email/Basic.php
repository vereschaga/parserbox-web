<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class Basic extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-12896252.eml, mileageplus/it-12896265.eml, mileageplus/it-13198650.eml, mileageplus/it-6.eml, mileageplus/it-7.eml";
    public $lang = "en";
    private $date = null;

    public function ParseChangeNotification(\PlancakeEmailParser $parser)
    {
        try {
            $subject = $parser->getSubject();
        } catch (Exception $e) {
            $subject = $parser->getHeader("subject");
        }
        $result = ["Kind" => "T"];

        if (preg_match("/Confirmation\s*([A-Z\d]+)/ims", $subject, $matches)) {
            $result["RecordLocator"] = $matches[1];
        }

        if (preg_match("/for reservation\s*([A-Z\d]+)/ims", $subject, $matches)) {
            $result["RecordLocator"] = $matches[1];
        }
        $flightRows = $this->http->XPath->query("//tr[descendant::*[contains(text(), 'Flight Time')]][count(td) = 8]");
        $segments = [];

        for ($i = 0; $i < $flightRows->length; $i++) {
            $flight = $flightRows->item($i);
            $segment = [];
            $number = $this->http->FindSingleNode("td[2]", $flight);

            if (preg_match("/^([A-Z\d]{2})(\d+)$/", $number, $m)) {
                $segment["FlightNumber"] = $m[2];
                $segment["AirlineName"] = $m[1];
            } else {
                $segment["FLightNumber"] = $number;
            }
            $info = $this->http->FindSingleNode("td[4]", $flight);

            if (preg_match("/^(.*\.m\.)(.*\d\,\s\d{4})\s*([A-Z].+)\(([A-Z]{3}).*\)$/", $info, $matches)) {
                $segment["DepCode"] = $matches[4];
                $segment["DepName"] = trim($matches[3]);
                $segment["DepDate"] = strtotime($matches[2] . " " . $matches[1]);
                //$segment["DepDateHuman"] = date("Y-m-d H:i", $segment["DepDate"]);
            }
            $info = $this->http->FindSingleNode("td[5]", $flight);

            if (preg_match("/^(.*\.m\.)(.*\d\,\s\d{4})\s*([A-Z].+)\(([A-Z]{3}).*\)$/", $info, $matches)) {
                $segment["ArrCode"] = $matches[4];
                $segment["ArrName"] = trim($matches[3]);
                $segment["ArrDate"] = strtotime($matches[2] . " " . $matches[1]);
                //$segment["ArrDateHuman"] = date("Y-m-d H:i", $segment["ArrDate"]);
            }
            $info = $this->http->FindSingleNode("td[6]", $flight);

            if (preg_match("/(.*)Fare Class\:([^\(]+)\(([^\)]+)\).*Meals\:(.*)/", $info, $matches)) {
                $segment["Aircraft"] = $matches[1];
                $segment["Cabin"] = trim($matches[2]);
                $segment["BookingClass"] = $matches[3];
                $segment["Meal"] = trim($matches[4]);
            }
            $segment["Duration"] = $this->http->FindSingleNode("td[7]", $flight, true, "/Flight Time\:\D*(.*)/");
            $segments[] = $segment;
        }
        $passengers = $this->http->FindNodes("//*[contains(text(), 'Traveler Information')]/following-sibling::div[1]/table");

        foreach ($passengers as $i => $pass) {
            $passengers[$i] = beautifulName($pass);
        }
        $result["Passengers"] = implode(", ", $passengers);
        $result["TripSegments"] = $segments;

        return ["Itineraries" => [$result], "Properties" => []];
    }

    public function ParseCheckInReminder(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseChangeNotification($parser);

        if (isset($result["Itineraries"][0])) {
            if (empty($result["Itineraries"][0]["RecordLocator"])) {
                $result["Itineraries"][0]["RecordLocator"] = $this->http->FindSingleNode("//span[contains(@id, 'lblPNR') or contains(@id, 'spanPNR')]");
            }
            // passengers and seats
            $passengers = [];
            $seats = [];
            $nodes = $this->http->XPath->query("//*[contains(text(), 'Traveler Information')]/following-sibling::div[1]/table");

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $passengers[] = beautifulName($this->http->FindSingleNode("tbody/tr[1] | tr[1]", $node));
                $passSeats = array_map(function ($seat) {
                    return preg_replace("/^[^:]+:\D+/", "", $seat);
                }, $this->http->FindNodes("tbody/tr[2]/td[2]/font/text() | tr[2]/td[2]/font/text()", $node));

                foreach ($passSeats as $j => $seat) {
                    if (!isset($seats[$j])) {
                        $seats[$j] = [];
                    }

                    if ($seat != "") {
                        $seats[$j][] = $seat;
                    }
                }
            }
            $result["Itineraries"][0]["Passengers"] = implode(", ", $passengers);

            foreach ($result["Itineraries"][0]["TripSegments"] as $i => $segment) {
                if (isset($seats[$i]) && count($seats[$i]) > 0) {
                    $result["Itineraries"][0]["TripSegments"][$i]["Seats"] = implode(",", $seats[$i]);
                }
            }
        }

        return $result;
    }

    public function ParseAwardSummary()
    {
        $result = [];

        $lang = $this->getLang();
        $miniDict = [
            "Summary as of" => [
                'PRT' => 'Resumo a partir de',
                'ESP' => 'El resumen al',
                'CHN' => ' 的余额',
                'JPN' => '時点の明細書',
            ],
            "Summary as of (.+)" => [
                'PRT' => 'Resumo a partir de (.+)',
                'ESP' => 'El resumen al (.+)',
                'CHN' => '截至 (.+) 的余额',
                'JPN' => '(.+) 時点の明細書',
            ],
            "MileagePlus status" => [
                'PRT' => 'Status do MileagePlus',
                'ESP' => 'Nivel del socio',
                'CHN' => '前程万里 (MileagePlus) 会籍',
                'JPN' => '会員レベル',
            ],
            'MileagePlus status: (.+)' => [
                'PRT' => 'Status do MileagePlus: (.+)',
                'ESP' => 'Nivel del socio: (.+)',
                'CHN' => '前程万里 \(MileagePlus\) 会籍：(.+)',
                'JPN' => '会員レベル：(.+)',
            ],
            'Current award miles balance' => [
                'PRT' => 'Saldo atual de',
                'ESP' => 'Saldo actual millas',
                'CHN' => '当前的奖励里程结余',
                'JPN' => '現在の特典マイル残高',
            ],
            'Your award miles expire on' => [
                'PRT' => 'Suas milhas-pr',
                'ESP' => 'Sus millas premio expirar',
                'CHN' => '您的奖励里程的过期时间是',
                'JPN' => '特典マイルの期限',
            ],
            'qualifying miles' => [
                'PRT' => 'Milhas de qualifica',
                'ESP' => 'Millas que califican para',
                'CHN' => '合格里程',
                'JPN' => '資格対象マイル',
            ],
            'qualifying segments' => [
                'PRT' => 'Segmentos de qualifica',
                'ESP' => 'Segmentos que califican para',
                'CHN' => '合格航段',
                'JPN' => 'ア資格対象区間',
            ],
        ];

        $date = $this->http->FindSingleNode("//*[contains(text(), '" . $this->tr('Summary as of', $lang, $miniDict) . "')]", null, true, "/" . $this->tr('Summary as of (.+)', $lang, $miniDict) . "/ims");

        if ($time = strtotime($this->formatRegionDate($date, $lang))) {
            $result["BalanceDate"] = $time;
            //$result["BalanceDateHuman"] = date("m/d/Y", $time);
        }
        $number = $this->http->FindSingleNode("//*[contains(text(), 'MileagePlus #')]", null, true, "/#\s*X+(\d+)/");
        $result["PartialNumber"] = $result["PartialLogin"] = "$number\$";
        $status = $this->http->FindSingleNode("//*[contains(text(), '" . $this->tr('MileagePlus status', $lang, $miniDict) . "')]", null, true, "/" . $this->tr('MileagePlus status: (.+)', $lang, $miniDict) . "/ims");
        $result["MemberStatus"] = trim($status);
        $result["YearBegins"] = strtotime("1 JAN");

        if ($node = $this->findParentNode($this->tr('Current award miles balance', $lang, $miniDict), "tr", 10)) {
            $result["Balance"] = preg_replace("/[,.\s]/", "", $this->http->FindSingleNode("td[3]", $node));
        }

        if ($node = $this->findParentNode($this->tr('Your award miles expire on', $lang, $miniDict), "tr")) {
            $expDate = strtotime($this->formatRegionDate($this->http->FindSingleNode("td[3]", $node), $lang));

            if ($expDate > mktime(0, 0, 0, 1, 1, 1990)) {
                $result["AccountExpirationDate"] = $expDate;
                //$result["ExpDateHuman"] = date("m/d/Y", $expDate);
            }
        }

        if ($node = $this->findParentNode($this->tr('qualifying miles', $lang, $miniDict), "tr")) {
            $result["EliteMiles"] = $this->http->FindSingleNode("td[3]", $node);
        }

        if ($node = $this->findParentNode($this->tr('qualifying segments', $lang, $miniDict), "tr")) {
            $result["EliteSegments"] = $this->http->FindSingleNode("td[3]", $node);
        }

        return ["Properties" => $result];
    }

    public function ParseAccountSummaryCompact()
    {
        $result = [];
        $result["Login"] = $result["Number"] = $this->http->FindSingleNode("//*[contains(@id, 'OnePassNumber')]");

        if (empty($result["Number"]) && $node = $this->findParentNode("MileagePlus Number", "tr")) {
            $result["Login"] = $result["Number"] = $this->http->FindSingleNode("td[3]", $node);
        }

        if ($node = $this->findParentNode("MileagePlus Status", "tr")) {
            $status = $this->http->FindSingleNode("td[3]", $node);
            $result["MemberStatus"] = beautifulName(trim(preg_replace("/MileagePlus/ims", "", $status)));
            $result["YearBegins"] = strtotime("1 JAN");
        }

        if ($node = $this->findParentNode("Ending Balance as of", "tr")) {
            $date = $this->http->FindSingleNode("//*[contains(text(), 'Ending Balance as of')]", null, true, "/Ending Balance as of ([^:]+)/");
            $date = strtotime($date);

            if ($date > mktime(0, 0, 0, 1, 1, 1990)) {
                $result["BalanceDate"] = $date;
                //$result["BalanceDateHuman"] = date("m/d/Y", $date);
            }
            $result["Balance"] = preg_replace("/\,/", "", $this->http->FindSingleNode("td[2]", $node));
            $result["EliteMiles"] = $this->http->FindSingleNode("td[4]", $node);
            $result["EliteSegments"] = $this->http->FindSingleNode("td[5]", $node);
        }

        return ["Properties" => $result];
    }

    public function ParseMiniSummary()
    {
        $result = [];

        foreach (["Number" => "Number", "Balance" => "Balance", "MemberStatus" => "Status", "EliteMiles" => "Premier Miles", "EliteSegments" => "Premier Segments", "ExpDate" => "Expiration"] as $field => $search) {
            if ($node = $this->findParentNode($search, "th")) {
                $result[$field] = $this->http->FindSingleNode("following-sibling::td", $node);
            }
        }

        if (isset($result["ExpDate"])) {
            if (($expDate = strtotime($result["ExpDate"])) > mktime(0, 0, 0, 1, 1, 1990)) {
                $result["AccountExpirationDate"] = $expDate;
                //$result["ExpDateHuman"] = date("m/d/Y", $expDate);
            }
            unset($result["ExpDate"]);
        }

        if (isset($result["Balance"])) {
            $result["Balance"] = str_replace(",", "", $result["Balance"]);
        }
        $result["YearBegins"] = strtotime("1 JAN");

        return ["Properties" => $result];
    }

    public function ParseUseMilesStatement()
    {
        $result = [];
        $node = null;

        for ($i = 1; $i < 5; $i++) {
            if ($node = $this->findFirstNode("//*[contains(text()[$i], 'balance as of')]")) {
                break;
            }
        }

        if (!$node) {
            return null;
        }
        $date = preg_replace("/^.+balance as of/ims", "", CleanXMLValue($node->nodeValue));
        $date = strtotime($date);

        if ($date > mktime(0, 0, 0, 1, 1, 1990)) {
            $result["BalanceDate"] = $date;
            //$result["BalanceDateHuman"] = date("m/d/Y", $date);
        }
        $balance = $this->http->FindSingleNode("preceding-sibling::tr", $this->findParentNode($node, 'tr', 6), true, "/([\d\,]+)\s*miles/ims");

        if ($balance) {
            $result["Balance"] = preg_replace("/\,/", "", $balance);
        }

        return ["Properties" => $result];
    }

    public function ParsePlainStatement()
    {
        $result = [];
        $message = $this->http->FindSingleNode("//pre[1]");

        if (!$message) {
            $message = CleanXMLValue($this->http->Response["body"]);
        }
        $message = str_replace(["=A0", "=AE", ">"], "", $message);

        if (preg_match("/you have ([\d\,]+) award miles/ims", $message, $matches)) {
            $result["Balance"] = preg_replace("/\,/", "", $matches[1]);
        } elseif (preg_match("/Current award miles balance1?\D*([\d\,]+)\D/ims", $message, $matches)) {
            $result["Balance"] = preg_replace("/\,/", "", $matches[1]);
        } else {
            return null;
        }

        if (preg_match("/Summary as of ([\d\/]+)/ims", $message, $matches)) {
            $date = strtotime($matches[1]);

            if ($date > mktime(0, 0, 0, 1, 1, 1990)) {
                $result["BalanceDate"] = $date;
            }
        }
        $str = "current award miles balance";

        if (stripos($message, "Star alliance status") !== false) {
            $str = "Star alliance status";
        }

        if (preg_match("/mileageplus status:([^<]+)$str/ims", $message, $matches)) {
            $result["MemberStatus"] = trim($matches[1]);
            $result["YearBegins"] = strtotime("1 JAN");
        }

        if (preg_match("/Your award miles expire on\D*([\d\/]+)/ims", $message, $matches)) {
            $date = strtotime($matches[1]);

            if ($date > mktime(0, 0, 0, 1, 1, 1990)) {
                $result["AccountExpirationDate"] = $date;
            }
        }

        if (preg_match("/Premier qualifying miles2?\D*([\d\,]+)\D/ims", $message, $matches)) {
            $result["EliteMiles"] = $matches[1];
        }

        if (preg_match("/Premier qualifying segments2?\D*([\d\,]+)\D/ims", $message, $matches)) {
            $result["EliteSegments"] = $matches[1];
        }

        return ["Properties" => $result];
    }

    public function ParseBroken()
    {
        $result = [];
        //$body = str_ireplace(array("=A0"), array(""), $this->http->Response["body"]);
        $this->http->SetBody(quoted_printable_decode($this->http->Response["body"]));

        if ($balance = $this->http->FindPreg("/you have ([\d\.\,]+) award miles/")) {
            $result["Balance"] = preg_replace("/[\,\.\s]/", "", $balance);
        }

        if ($number = $this->http->FindPreg("/# XXXX(\d{4})/")) {
            $result["PartialLogin"] = $result["PartialNumber"] = $number . "$";
        }
        $result["EliteMiles"] = $this->http->FindPreg("/qualifying miles\d?\D+([\d\,\.]+)\D/ims");
        $result["EliteSegments"] = $this->http->FindPreg("/qualifying segments\d?\D+([\d\,\.]+)\D/ims");
        $result["MemberStatus"] = $this->http->FindPreg("/MileagePlus Status: (.+)/i");

        return ["Properties" => $result];
    }

    public function ParsePremierBSummary()
    {
        $result = [];
        $q = $this->http->XPath->query("//sup");

        for ($i = 0; $i < $q->length; $i++) {
            $q->item($i)->nodeValue = "";
        }
        $balance = $this->http->FindSingleNode("//div[text()[contains(., 'award miles')]][sup]/text()[1]");

        if (!isset($balance)) {
            $balances = $this->http->FindNodes("//div[descendant::text()[contains(., 'award miles')]][descendant::sup][not(descendant::div)]");

            foreach ($balances as $b) {
                if (preg_match("/^([\d\.\,]+) award miles/ims", $b, $m)) {
                    $balance = $m[1];

                    break;
                }
            }
        }

        if (isset($balance)) {
            $result["Balance"] = preg_replace("/[\,\.\s]/", "", $balance);
        }

        if ($number = $this->http->FindPreg("/X{4,5}\d{3,4}/")) {
            $result["PartialLogin"] = $result["PartialNumber"] = preg_replace("/^X+/", "", $number) . '$';

            if ($row = $this->findParentNode($number, 'tr', 10)) {
                $result["Name"] = $this->http->FindSingleNode("td[2]", $row);
            }
        }
        $nodes = $this->http->XPath->query("//div[descendant::text()[contains(., 'Premier')][contains(., 'qualifying')][contains(., 'miles')]][not(descendant::div)]");

        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            $value = $this->http->FindSingleNode("descendant::strong | descendant::b", $node, true, "/[\d\.\,]+/");

            if (!isset($value) && preg_match("/:\s*([\d\.\,]+)/", CleanXMLValue($node->nodeValue), $m)) {
                $value = $m[1];
            }

            if (isset($value)) {
                $result["EliteMiles"] = $value;
            }
        }
        $nodes = $this->http->XPath->query("//div[descendant::text()[contains(., 'Premier')][contains(., 'qualifying')][contains(., 'segments')]][not(descendant::div)]");

        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            $value = $this->http->FindSingleNode("descendant::strong | descendant::b", $node, true, "/[\d\.\,]+/");

            if (!isset($value) && preg_match("/:\s*([\d\.\,]+)/", CleanXMLValue($node->nodeValue), $m)) {
                $value = $m[1];
            }

            if (isset($value)) {
                $result["EliteSegments"] = $value;
            }
        }
        $nodes = $this->http->XPath->query("//div[descendant::text()[contains(., 'MileagePlus')][contains(., 'status')][contains(., ':')]][not(descendant::div)]");

        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            $value = $this->http->FindSingleNode("descendant::strong | descendant::b", $node, true, '/([^®]+)/');

            if (!isset($value) && preg_match("/:\s*([^®]+)/", CleanXMLValue($node->nodeValue), $m)) {
                $value = $m[1];
            }

            if (isset($value)) {
                $result["MemberStatus"] = $value;
            }
        }

        return ["Properties" => $result];
    }

    public function ParseFlightReservationSummary()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        // Itineraries
        $itineraries = [];
        $itineraryNodes = $xpath->query('//tr[contains(string(), "Flight Reservation")]/following-sibling::tr[contains(string(), "Confirmation Number:")]');

        foreach ($itineraryNodes as $itineraryNode) {
            $accountNumbers = [];
            $seatsByFlight = [];
            $itinerary = ['Kind' => 'T'];
            $itinerary['RecordLocator'] = preg_match('/Confirmation Number:\s*(\S+)/ims', CleanXMLValue($itineraryNode->nodeValue), $matches) ? $matches[1] : null;
            // Travelers
            $travelersNodes = $xpath->query('./..//td[contains(text(), "Travelers(s)")]/following-sibling::td[last()]/table', $itineraryNode);

            foreach ($travelersNodes as $travelerNode) {
                if (null !== ($passengerName = $http->FindSingleNode('.//tr[1]', $travelerNode, false))) {
                    $itinerary['Passengers'][] = $passengerName;
                }

                if (null !== ($accountNumber = $http->FindSingleNode('.//td[contains(string(), "Frequent Flyer")]/following-sibling::td[last()]', $travelerNode, false))) {
                    $accountNumbers[] = $accountNumber;
                }

                if (null !== ($passengerSeats = $http->FindSingleNode('.//td[contains(string(), "Seats:")]/following-sibling::td[last()]', $travelerNode, false))) {
                    $seats = array_map('trim', explode('|', $passengerSeats));

                    foreach ($seats as $i => $seat) {
                        $seatsByFlight[$i][] = $seat;
                    }
                }
            }

            if (!empty($accountNumbers)) {
                $itinerary['AccountNumbers'] = implode(', ', array_filter($accountNumbers));
            }

            //Segments
            $segmentsNodes = $xpath->query('./..//td[contains(string(), "Flight from:") and not(.//td)]/ancestor::table[1]', $itineraryNode);

            if ($segmentsNodes->length == 0) {
                continue;
            }

            foreach ($segmentsNodes as $i => $segmentNode) {
                $segment = [];

                if (preg_match('/(.+)\s*\((\S+)(\s*-\s*.+)?\)\s+to\s+(.+)\s+\((\S+)(\s*-\s*.+)?\)/ims', $http->FindSingleNode('.//td[contains(string(), "Flight from:") and not(.//td)]/following-sibling::td[last()]', $segmentNode), $matches)) {
                    $segment['DepName'] = $matches[1];
                    $segment['DepCode'] = $matches[2];
                    $segment['ArrName'] = $matches[4];
                    $segment['ArrCode'] = $matches[5];
                }
                $segment['DepDate'] = $this->normalizeDate($http->FindSingleNode('.//td[contains(string(), "Depart:") and not(.//td)]/following-sibling::td[last()]', $segmentNode));
                $segment['ArrDate'] = $this->normalizeDate($http->FindSingleNode('.//td[contains(string(), "Arrive:") and not(.//td)]/following-sibling::td[last()]', $segmentNode));
                $segment['FlightNumber'] = $http->FindSingleNode('.//td[contains(string(), "Flight Number:") and not(.//td)]/following-sibling::td[last()]', $segmentNode);

                if (strpos($this->http->Response['body'], 'has requested that United Airlines send you this itinerary') !== false) {
                    $segment['AirlineName'] = 'UA';
                }
                $segment['Aircraft'] = $http->FindSingleNode('.//td[contains(string(), "Aircraft:") and not(.//td)]/following-sibling::td[last()]', $segmentNode);

                if (preg_match('/^(.+?)(\s*\((\S+)\))?$/ims', $http->FindSingleNode('.//td[contains(string(), "Fare Class:") and not(.//td)]/following-sibling::td[last()]', $segmentNode), $matches)) {
                    $segment['Cabin'] = $matches[1];

                    if (!empty($matches[3])) {
                        $segment['BookingClass'] = $matches[3];
                    }
                }
                $segment['Meal'] = implode(', ', array_filter($http->FindNodes('.//td[contains(string(), "Meal:") and not(.//td)]/following-sibling::td[last()]//text()', $segmentNode), 'strlen'));

                if (isset($seatsByFlight[$i])) {
                    $segment['Seats'] = implode(', ', $seatsByFlight[$i]);
                }
                $itinerary['TripSegments'][] = $segment;
            }
            $itineraries[] = $itinerary;
        }

        return [
            'Itineraries' => $itineraries,
            'Properties'  => [],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getSubject(), 'Check in now for your') !== false) {
            // Ignore emails which are for emailCheckInNowForYourFlightChecker.php
            return [];
        }
        $body = $parser->getHTMLBody();
        $this->http->FilterHTML = false;
        $this->http->SetBody($body, true);
        $emailType = $this->getEmailType($parser);

        switch ($emailType) {
            case "Broken":
                $result = $this->ParseBroken();

                break;

            case "ChangeNotification":
                $result = $this->ParseChangeNotification($parser);

                break;

            case "CheckInReminder":
            case "ScheduleChangeNotification":
                $result = $this->ParseCheckInReminder($parser);

                break;

            case "PlainStatement":
                $result = $this->ParsePlainStatement();

                break;

            case "AwardSummary":
                $result = $this->ParseAwardSummary();

                break;

            case "AccountSummaryCompact":
                $result = $this->ParseAccountSummaryCompact();

                break;

            case "MiniSummary":
                $result = $this->ParseMiniSummary();

                break;

            case "UseMilesStatement":
                $result = $this->ParseUseMilesStatement();

                break;

            case "PremierBSummary":
                $result = $this->ParsePremierBSummary();

                break;

            case "FlightReservationSummary":
                $result = $this->ParseFlightReservationSummary();

                break;

            default:
                $result = [];
        }

        return [
            'parsedData' => $result,
            'emailType'  => $emailType,
        ];
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getHeader("subject");
        $lang = $this->getLang();
        $miniDict = [
            'My award summary' => [
                'PRT' => 'Resumo do',
                'ESP' => 'Mi resumen',
                'CHN' => '我的奖励里程余额',
                'JPN' => '特典マイ',
            ],
        ];

        if (stripos($this->http->Response["body"], '=A0') !== false && stripos($this->http->Response['body'], 'United Confirmation Number') === false) {
            return "Broken";
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Manage my reservation >')]")) {
            return "UnitedReservation";
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'Reservation Change Notification')]")) {
            return "ChangeNotification";
        }

        if ($this->http->FindPreg("/Flight Check-in Reminder for/ims")) {
            return "CheckInReminder";
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Schedule Change Notification for reservation')]")
            || $this->http->FindSingleNode("//*[contains(text(), 'Waitlist Update for Confirmation')]")
            || $this->http->FindSingleNode("//*[contains(text(), 'Upgrade Status Update for Confirmation')]")
            || $this->http->FindSingleNode("//*[contains(text(), 'Important Flight Information')]")
        ) {
            return "ScheduleChangeNotification";
        }

        if ($this->findParentNode("My Premier Summary", 'td', 5)) {
            return 'PremierBSummary';
        }

        if (($this->http->XPath->query("//*[contains(text(), '" . $this->tr('My award summary', $lang, $miniDict) . "')]")->length > 0)
            || ($this->http->XPath->query("//*[contains(text(), 'Current award miles')]")->length > 0)
        ) {
            return "AwardSummary";
        }
        //		if ($this->http->XPath->query("//pre")->length > 0 || ($this->http->XPath->query("//span | //table")->length == 0))
        //			return "PlainStatement";
        if (($this->http->XPath->query("//*[contains(text(), 'Account Summary')]")->length > 0 || $this->http->XPath->query("//*[contains(text(), 'Account summary')]")->length > 0)
            && $this->http->XPath->query("//*[contains(text(), 'Ending Balance as of')]")->length > 0
        ) {
            return "AccountSummaryCompact";
        }

        if ($this->http->XPath->query("//*[contains(text(), 'Our best ways to use miles')]")->length > 0) {
            return 'UseMilesStatement';
        }

        if ($this->http->FindSingleNode("//th[contains(text(), 'Mileage Balance')]")) {
            return "MiniSummary";
        }

        if ((strpos($subject, "Travel Itinerary sent from United Air Lines, Inc.") !== false || strpos($subject, "Travel Itinerary sent from United Airlines, Inc.") !== false)
            && $this->http->XPath->query('/*[.//text()[contains(., "Reservation Summary")]]')->length > 0) {
            return "FlightReservationSummary";
        }

        return 'Undefined';
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'MileagePlus Statement') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->getEmailType($parser) !== 'Undefined';
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "united.com") !== false || stripos($from, "mileageplus") !== false
        || stripos($from, "noreply@qemailserver.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ["en", "es", "pt", "zh", "ja"];
    }

    public static function getEmailTypesCount()
    {
        return 12;
    }

    protected function getLang()
    {
        if (preg_match('/Visualizar\s*no/ims', $this->http->Response["body"])) {
            return 'PRT';
        }

        if (preg_match('/Ver\s*en\s*/ims', $this->http->Response["body"])) {
            return 'ESP';
        }

        if (preg_match('/网\s*页\s*浏\s*览/ims', $this->http->Response["body"])) {
            return 'CHN';
        }

        if (preg_match('/ウ\s*ェ\s*ブ\s*ブ\s*ラ\s*ウ/ims', $this->http->Response["body"])) {
            return 'JPN';
        }

        return 'ENG';
    }

    protected function tr($key, $lang, $dict)
    {
        if (isset($dict[$key][$lang])) {
            return $dict[$key][$lang];
        } else {
            return $key;
        }
    }

    protected function formatRegionDate($dateStr, $lang)
    {
        if (in_array($lang, ['ESP', 'PRT'])) {
            // strtotime() behavior: if the separator is a slash (/), then the American m/d/y is assumed; whereas if the separator is a dash (-) or a dot (.), then the European d-m-y format is assumed.

            return str_replace('/', '.', $dateStr);
        }

        return $dateStr;
    }

    protected function findFirstNode($xpath, $parent = null)
    {
        $result = $this->http->XPath->query($xpath, $parent);

        if ($result->length == 0) {
            return null;
        } else {
            return $result->item(0);
        }
    }

    protected function findParentNode($start, $tag = 'td', $limit = 5)
    {
        if (!$start) {
            return null;
        }

        if (is_string($start)) {
            $str = explode(' ', $start);
            $and = [];

            foreach ($str as $s) {
                $and[] = "contains(text(), '$s')";
            }

            if (!($node = $this->findFirstNode("//*[" . implode(" and ", $and) . "]"))) {
                return null;
            }
        } elseif ($start instanceof \DOMNode) {
            $node = $start;
        } else {
            return null;
        }

        if (strtolower($node->nodeName) == strtolower($tag)) {
            return $node;
        }

        while ($limit > 0) {
            if (!($node = $this->findFirstNode("parent::*", $node))) {
                return null;
            }

            if (strtolower($node->nodeName) == strtolower($tag)) {
                return $node;
            }
            $limit--;
        }

        return null;
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^(\d+:\d+) ([ap])\.m\., [^\s\d]+\., ([^\s\d]+)\. (\d+), (\d{4})$#", //2:30 p.m., Wed., Aug. 24, 2016
        ];
        $out = [
            "$4 $3 $5, $1 $2m",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }
}
