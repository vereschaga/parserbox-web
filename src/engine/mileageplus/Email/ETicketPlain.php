<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPlain extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-10.eml, mileageplus/it-6305878.eml, mileageplus/it-6320191.eml, mileageplus/it-9.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!empty($this->http->Response["body"]) && ($this->http->XPath->query("//a | //img")->length > 0)) {
            return false;
        } //go to ETicket.php
        $body = implode("\n", $this->http->FindNodes("//text()[normalize-space(.)!='']"));

        if (empty($body) || !strpos($body, 'FLIGHT INFORMATION\n') === false) {
            $body = $parser->getPlainBody();
        }

        $its = $this->ParseEmail($parser->getSubject(), $body);

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "eTicketItineraryPlain",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match("/eTicket Itinerary and Receipt for Confirmation [A-Z\d]{6}$/", $headers['subject'])
            || isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (!empty($this->http->Response["body"]) && ($this->http->XPath->query("//a | //img")->length > 0)) {
            return false;
        } //go to ETicket.php
        $body = $parser->getPlainBody();

        return !empty($body) && strpos($body, 'FLIGHT INFORMATION') !== false && strpos($body, 'United') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\.com/", $from);
    }

    // subject eTicket Itinerary and Receipt for Confirmation ABC123
    // plaintext version (it-10)

    protected function ParseEmail($subject, $body)
    {
        $it = ["Kind" => "T", "Passengers" => [], "TripSegments" => []];

        if (preg_match("/eTicket Itinerary and Receipt for Confirmation ([A-Z\d]{6})/", $subject, $m)) {
            $it["RecordLocator"] = $m[1];
        } else {
            $start = stripos($body, 'Confirmation:');
            $end = stripos($body, 'Check-In');

            if ($start && $end && preg_match("/Confirmation: ([A-Z\d]{6})/", CleanXMLValue(substr($body, $start, $end - $start)), $m)) {
                $it["RecordLocator"] = $m[1];
            }
        }

        if (strpos($body, "\n>") !== false) {
            $body = preg_replace("#\n\s*>+#", "\n", $body);
        }
        $parts = $this->split("#(eTicket Number|FLIGHT INFORMATION|FARE INFORMATION)#", $body);

        if (count($parts) < 2) {
            return [];
        }

        $seats = [];

        foreach ($parts as $part) {
            if (strpos($part, 'eTicket Number') !== false) {
                if (preg_match_all("/^([A-Z]+)\/([A-Z]+)\s+(\d+)\s*([A-Z]{2}\-[A-Z\d]{5,})?\s([A-Z\d\-\/]+)$/m", $part,
                    $v, PREG_SET_ORDER)) {
                    foreach ($v as $m) {
                        $m[2] = preg_replace("/(MR|MS|MRS)$/", "", $m[2]);
                        $it["Passengers"][] = beautifulName($m[2] . " " . $m[1]);
                        $it["TicketNumbers"][] = $m[3];

                        if (isset($m[4]) && !empty($m[4])) {
                            $it["AccountNumbers"][] = $m[4];
                        }
                        $ss = explode("/", $m[5]);

                        foreach ($ss as $i => $s) {
                            $seats[$i][] = $s;
                        }
                    }
                } else {
                    // passengers and seats
                    $lines = array_filter(array_map("CleanXMLValue", explode("\n", $part)), "strlen");

                    foreach ($lines as $line) {
                        if (preg_match("/^([A-Z]+)\/([A-Z]+).+\s([A-Z\d\-\/]+)$/", $line, $m)) {
                            $m[2] = preg_replace("/(MR|MS|MRS)$/", "", $m[2]);
                            $it["Passengers"][] = beautifulName($m[2] . " " . $m[1]);
                            $ss = explode("/", $m[3]);

                            foreach ($ss as $i => $s) {
                                $seats[$i][] = $s;
                            }
                        } elseif (preg_match("/([A-Z]+)\/([A-Z]+)\s+(\d+)\s*([A-Z]{2}\-[A-Z\d]{5,})?\s([A-Z\d\-\/]+)/", $line, $m)) {
                            $m[2] = preg_replace("/(MR|MS|MRS)$/", "", $m[2]);
                            $it["Passengers"][] = beautifulName($m[2] . " " . $m[1]);
                            $it["TicketNumbers"][] = $m[3];

                            if (isset($m[4]) && !empty($m[4])) {
                                $it["AccountNumbers"][] = $m[4];
                            }
                            $ss = explode("/", $m[5]);

                            foreach ($ss as $i => $s) {
                                $seats[$i][] = $s;
                            }
                        }
                    }
                }
            }

            if (strpos($part, 'FLIGHT INFORMATION') !== false
                && strpos($part, 'Check-in') === false //bcd-email so broken. segment should be without "Check-in"
            ) {
                $segments = $this->split("#([^\d\s]+,\s+\d+[^\d\s]+\d{2}\s+\w{2}\d+\s+[A-Z]\s+)#ms", $part);

                foreach ($segments as $i=> $stext) {
                    $segment = [];

                    if (!preg_match("#(?<Date>[^\d\s]+,\s+\d+[^\d\s]+\d{2})\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+(?<BookingClass>[A-Z])\s+(?<DepName>.*?)" .
                    "\s+\((?<DepCode>[A-Z]{3})(?:\s+-\s+.*?)?\)\D+\*?(?<DepTime>\d+:\d+\s+[AP]M)\*?\s+(?<ArrName>.*?)\s+" .
                    "\((?<ArrCode>[A-Z]{3})(?:\s+-\s+.*?)?\)\D+\*?(?<ArrTime>\d+:\d+\s+[AP]M)\*?\s+(?:\(\d+[A-Z]+\)\s+)?(?<Aircraft>\S+)#ms", $stext, $m)) {
                        return [];
                    }

                    $keys = [
                        'AirlineName', 'FlightNumber', 'BookingClass',
                        'DepName', 'DepCode', 'ArrName', 'ArrCode', 'Aircraft',
                    ];

                    foreach ($keys as $key) {
                        $segment[$key] = trim(implode(" ", array_map("trim", explode("\n", $m[$key]))), " >");
                    }
                    $date = strtotime($this->normalizeDate($m['Date']));
                    $segment['DepDate'] = strtotime(implode(" ", array_map("trim", explode("\n", $m['DepTime']))), $date);
                    $segment['ArrDate'] = strtotime(implode(" ", array_map("trim", explode("\n", $m['ArrTime']))), $date);

                    if (isset($seats[$i])) {
                        $s = array_filter(array_map(function ($s) {
                            if (preg_match("#^\d+[A-Z]$#i", $s)) {
                                return $s;
                            }

                            return null;
                        }, $seats[$i]));

                        if (!empty($s)) {
                            $segment['Seats'] = $s;
                        }
                    }
                    $it["TripSegments"][] = $segment;
                }
            }

            if (strpos($part, 'FARE INFORMATION') !== false) {
                // fares
                if (preg_match("/The airfare you paid on this itinerary totals: ([\d\.\,]+) ([A-Z]{3})/", $part, $m)) {
                    $it["BaseFare"] = str_replace(",", "", $m[1]);
                    $it["Currency"] = $m[2];
                }

                if (preg_match("/The taxes, fees, and surcharges paid total: ([\d\.\,]+)/", $part, $m)) {
                    $it["Tax"] = str_replace(",", "", $m[1]);
                }

                if (preg_match("/eTicket Total:\s*([\d\.\,]+)/", $part, $m)) {
                    $it["TotalCharge"] = str_replace(",", "", $m[1]);
                }
            }
        }

        return [$it];
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\d\s]+,\s+(\d+)([^\d\s]+)(\d{2})$#", //Mon, 12DEC16
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], 'en')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
