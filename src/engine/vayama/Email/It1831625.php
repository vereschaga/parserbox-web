<?php

namespace AwardWallet\Engine\vayama\Email;

class It1831625 extends \TAccountCheckerExtended
{
    public $mailFiles = "vayama/it-1831625.eml, vayama/it-1843368.eml, vayama/it-1935982.eml, vayama/it-1939692.eml, vayama/it-1961011.eml, vayama/it-3799273.eml, vayama/it-5107708.eml, vayama/it-5268018.eml, vayama/it-8704966.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject'])
                && (
                stripos($headers['subject'], 'Vayama.com E-Ticket Confirmation') !== false
                || stripos($headers['subject'], 'Vayama Reservation Reminder') !== false
                || stripos($headers['subject'], 'Vayama Ticket Confirmation/Receipt') !== false
                || stripos($headers['subject'], 'Vayama.com Booking Verification') !== false
                || stripos($headers['subject'], 'Vayama.com Booking Request Acknowledgement') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = str_replace('Â', '', $this->http->Response['body']); //replace bug like in it-8704966.eml

        return (stripos($text, 'Below is your E-ticket confirmation and receipt for your trip.') !== false
                || stripos($text, 'Below is your electronic ticket confirmation reminder for your trip.') !== false
                || stripos($text, 'Below is your electronic ticket confirmation and receipt for your trip.') !== false
                || stripos($text, 'IMPORTANT: This is NOT your E-ticket confirmation.') !== false
                || stripos($text, 'We are currently processing your reservation.') !== false)
                && stripos($text, 'vayama.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vayama.com') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    //replace bug like in it-8704966.eml
                    $this->http->SetBody(str_replace('Â', '', $this->http->Response['body']));
                    $text = text($this->http->Response['body']);

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cell('Confirmation Code(s)', 0, +1),
                            re('#Your\s*(?:Vayama\s+)?Trip\s*ID\s*:\s*([A-Z\d-]+)#i')
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'TRAVELER(S)') or contains(text(), 'TRAVELERS')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[1]");

                        if ($node) {
                            $names = splitter('#(\d+[.])#', $node);

                            return array_filter(array_map(function ($x) { return trim(re("#(?:\d+[.])?\s*([^\(]+)#", $x)); }, $names));
                        } else {
                            $node = text(xpath("//*[contains(text(), 'TRAVELERS FOR THIS BOOKING')]/ancestor::table[1]/following-sibling::table[contains(normalize-space(.),',')][1]"));
                            $names = preg_split("#\n#", $node);

                            return array_filter(array_map(function ($x) { return trim(re("#([^\(]+)#", $x)); }, $names));
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Taxes and agent-imposed Fees') or contains(text(), 'Taxes and Fees')]/ancestor::tr[1]/following-sibling::tr[1]//td[2]";

                        return total(node($xpath));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Taxes and agent-imposed Fees') or contains(text(), 'Taxes and Fees')]/preceding::td[1]";

                        return cost(node($xpath));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Taxes and agent-imposed Fees') or contains(text(), 'Taxes and Fees')]/following::td[1]";

                        return cost(node($xpath));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text())='Depart']/preceding::img[1]/ancestor::tr[1][contains(., 'Flight')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('td[2]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 3, 'Arr' => 4] as $key => $value) {
                                $info = node('./td[' . $value . ']');
                                $res[$key . 'Code'] = re('/[(](\w{3})[)]/', $info);

                                if (preg_match('#(\d+-\w+-\d+)\s+\(.*\)\s+(\d+):(\d+)([ap]m)#i', $info, $m)) {
                                    if ($m[2] == '00') {
                                        $m[2] = '12';
                                    }
                                    $res[$key . 'Date'] = strtotime($m[1] . ', ' . $m[2] . ':' . $m[3] . ' ' . $m[4]);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = node('following-sibling::*[1]');

                            return re("#Aircraft\s*[:]\s*(.+?)\s*$#is", $info);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node('following-sibling::*[1]');

                            return re("#Class\s*[:]\s*(.+?)\s*(?:Aircraft\s*[:]|$)#is", $info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $info = node('following-sibling::*[1]');

                            return re("#Flight\s*Time\s*[:]\s*(.+?)\s*Stops\s*[:]#is", $info);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $info = node('following-sibling::*[1]');
                            $n = re("#Stops\s*[:]\s*(\w+)\s*Class\s*[:]#is", $info);

                            if (re('/nonstop/i', $n)) {
                                return 0;
                            }
                            $n = re('/(\d+)/', $n);

                            if ($n) {
                                return intval($n);
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
