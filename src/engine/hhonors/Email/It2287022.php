<?php

namespace AwardWallet\Engine\hhonors\Email;

// parsers with similar formats: It3291517

class It2287022 extends \TAccountCheckerExtended
{
    public $mailFiles = "hhonors/it-2287022.eml, hhonors/it-2287024.eml, hhonors/it-2295604.eml, hhonors/it-2306055.eml, hhonors/it-2312854.eml, hhonors/it-2312855.eml, hhonors/it-2314804.eml, hhonors/it-2315272.eml, hhonors/it-2315541.eml, hhonors/it-2353159.eml, hhonors/it-2371907.eml, hhonors/it-2388818.eml, hhonors/it-2408265.eml, hhonors/it-2454946.eml, hhonors/it-2519533.eml, hhonors/it-2562361.eml, hhonors/it-2582058.eml, hhonors/it-2584386.eml, hhonors/it-2587150.eml, hhonors/it-2596698.eml, hhonors/it-2596699.eml, hhonors/it-2673884.eml, hhonors/it-2784504.eml, hhonors/it-2789890.eml, hhonors/it-2791717.eml, hhonors/it-2794877.eml, hhonors/it-2800605.eml, hhonors/it-2818400.eml, hhonors/it-2858434.eml, hhonors/it-3119439.eml, hhonors/it-3123091.eml, hhonors/it-3123131.eml, hhonors/it-3182631.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@res.hilton') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Resorts Reservierung') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Honors') !== false && (
                strpos($parser->getHTMLBody(), 'YOUR STAY DATES') !== false
                || strpos($parser->getHTMLBody(), 'IHRE AUFENTHALTSDATEN:') !== false
        );
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@res.hilton') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:CANCEL+ATION|CONFIRMATION|BESTÄTIGUNG)\s*:\s*([A-Z\d\-]+)#");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\s+(?:Account|Mitgliedsnummer)\s*:\s*([\dA-Z\-]+)#")];
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*From:\s*\"(.*?)\s+by\s+Hilton\s+Cancel+ed\"#")) {
                            return [
                                'HotelName' => re(1),
                                'Address'   => re(1),
                            ];
                        }

                        if (re("#Ihre\s+(.*?)\s+Übernachtung#", $this->parser->getSubject())) {
                            return [
                                'HotelName' => re(1),
                                'Address'   => re(1),
                            ];
                        }

                        if (re("#Your Upcoming.* Stay at (.+)#", $this->parser->getSubject())) {
                            return [
                                'HotelName' => re(1),
                                'Address'   => re(1),
                            ];
                        }

                        if (!($d = $this->http->FindSingleNode(
                            "/descendant::td[(
								contains(@style, 'background-color:#') or 
								contains(@style, 'background-color: #') or 
								contains(@style, 'background:#') or 
								contains(@style, 'background: #') or 
								contains(@style, 'background:rgb') or 
								contains(@style, 'background-color:rgb') or
								contains(@style, 'background-color: rgb')
							) and not(
								contains(@style, 'background-color:#fff') or 
								contains(@style, 'background-color: #fff') or 
								contains(@style, 'background:#fff') or 
								contains(@style, 'background: #fff') or 
								contains(@style, 'background:rgb(255') or 
								contains(@style, 'background-color:rgb(255') or
								contains(@style, 'background-color: rgb(255')
							)][1]"
                        )) || !re("#^(.*?)\|#ms", trim($d, '| '))) {
                            $d = $this->http->FindSingleNode("(//img[contains(@src, 'http://echo.epsilon.com/webservices/echoengine/img_') and not(./ancestor::a)])[1]/ancestor::tr[1]/preceding-sibling::tr[1]");
                        }

                        if ($d) {
                            $d = trim($d, '| ');

                            if ($HotelName = re("#^(.*?)\|#ms", $d)) {
                                $d = str_replace($HotelName . '|', '', $d);
                                $Phone = re("#([0-9\s-+]+)$#ms", $d);
                                $Address = trim(str_replace(['Tel:', 'Tel.:'], '', str_replace($Phone, '', $d)), "\r\n |");

                                return [
                                    'HotelName' => $HotelName,
                                    'Address'   => $Address,
                                    'Phone'     => trim($Phone, " -\n\r"),
                                ];
                            }
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $in = re("#\n\s*(?:YOUR STAY DATES|IHRE AUFENTHALTSDATEN)\s*:\s*(\w{3}\s+\d+,\s*\d+)[\-\s]+(\w{3}\s+\d+,\s*\d+)#ix");

                        if (!$in) {
                            $in = re("#\n\s*(?:YOUR STAY DATES|IHRE AUFENTHALTSDATEN)\s*:\s*(\d+\s+\w{3}\s+\d+)[\-\s]+(\d+\s+\w{3}\s+\d+)#ix");
                        }

                        $out = nice(re(2));

                        return [
                            'CheckInDate'  => totime(en($in . ',' . re("#\n\s*Check[\s\-]+In.*?:\s*([^\n]+)#i"))),
                            'CheckOutDate' => totime(en($out . ',' . re("#\n\s*Check[\s\-]+Out.*?:\s*([^\n]+)#i"))),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*(?:Welcome|Willkommen)\s*,\s*([^\n]+)#")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Guests|Gäste)\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Rooms|Zimmer)\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Rate per night|Rate)\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(preg_replace("#[\s|]+#", " ", text(xpath("//text()[contains(., 'CANCELLATION POLICY') or contains(., 'STORNIERUNGSRICHTLINIEN') or contains(., 'stornierungsrichtlinien')]/ancestor::tr[1]/following::tr[1]//tr[contains(.,'•')]"))));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*(?:ROOM INFORMATION|ZIMMERINFORMATION)\s*:\s*([^\n]+)#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Taxes|Steuern)\s*:\s*([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*(?:Total for Stay|Gesamtrate für)\s*:\s*([^\n]+)#"), 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Number of Points per Stay\s*:\s*([^\n]+)#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#(?:^|\n)[>\s*]*From\s*:[^\n]*?(Confirmed)#i"),
                            re("#\n\s*ROOM INFORMATION\s*:\s*[^\n]+\s+.*?\s+(Confirmed)#i"),
                            re("#reservation has been (\w+)#ix"),
                            re("#\s+(Confirmed)[<\s]+homewood@res.hilton.com[>\s]+wrote:#i")
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        $this->logger->debug('$text = ' . print_r($text, true));

                        return orval(
                            re("#has been canceled#ix") ? true : false,
                            re("#\n\s*(?:CANCEL+ATION)\s*:\s*[A-Z\d\-]+#") ? true : false
                        );
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->setBody(preg_replace("#<br(?:\s+[^>]+|\s*)/?>#", "|", preg_replace("#\s+#", " ", preg_replace("#/\*.*?\*/#ms", "", $this->http->Response['body']))));
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
