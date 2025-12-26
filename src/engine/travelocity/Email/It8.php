<?php

namespace AwardWallet\Engine\travelocity\Email;

class It8 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*@travelocitycustomercare.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#FW:\s+Travelocity\s+travel#i";
    public $langSupported = "";
    public $typesCount = "";
    public $reFrom = "";
    public $reProvider = "";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "travelocity/it-10.eml, travelocity/it-11.eml, travelocity/it-2027105.eml, travelocity/it-8.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(normalize-space(text()), 'Flight summary') or contains(normalize-space(text()), 'Car rental summary')]/ancestor::table[2]");
                },

                "#Car\s+rental\s+summary#i" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#confirmation number:\s*([\d\w\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(clear("#;#", re("#\n\s*Location:\s*(.*?)\s*Hours of operation#ms"), ','));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        // Pick up: Fri 3/14/2014 1:00 pm
                        return strtotime(re("#\n\s*Pick\s*up:\s*(\w{3}\s*\d+/\d+/\d{4}\s*\d+:\d+\s*\w{2})#"));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(clear("#;#", re("#\n\s*Location:\s*(.*?)\s*Hours of operation#ms"), ','));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        // Drop off: Wed 3/19/2014 1:00 pm
                        return strtotime(re("#\s{2,}Drop\s*off\s*:\s*(\w{3}\s*\d+/\d+/\d{4}\s*\d+:\d+\s*\w{2})#"));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        $hoursRegex = '\d+/\d+/\d+\s*:\s*\d+:\d+\s*(?:am|pm)\s*-\s*\d+:\d+\s*(?:am|pm)';

                        if (preg_match("#\s*Hours\s+of\s+operation:\s*($hoursRegex)\s+($hoursRegex)#i", $node->nodeValue, $m)) {
                            return [
                                'PickupHours'  => $m[1],
                                'DropoffHours' => $m[2],
                            ];
                        } else {
                            return re("#\s*Hours of operation:\s*(.*?)\n#");
                        }
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#itinerary number:\s*[\w\d]+\s+[\w\d]+\s+([^\n]*?)\s*confirmation number\s*:#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        //Fri Mar-14-2014   Hertz Standard SUV:
                        return nice(re("#\w{3}\s*\w{3}\-\d+\-\d{4}\s*([^:]*?):#ms"));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Driver:\s*([^\n]*)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(node("//*[contains(text(), 'Driver:')]/ancestor::tr[1]/td[position()=last()]")),
                            cost(re("#\n\s*Total\s+amount\s+charged\s+([^\n]+)#", $this->text()))
                        );
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            currency(node("//*[contains(text(), 'Driver:')]/ancestor::tr[1]/td[position()=last()]")),
                            currency(re("#\n\s*Total\s+amount\s+charged\s+([^\n]+)#", $this->text()))
                        );
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Flight\s+taxes/fees\s+([^\n]+)#", $this->text()));
                    },
                ],

                "#Flight\s+summary#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $airline = re("#\n\s*([^\n]*?)\s+Flight:\s*\d+#");

                        return re("#\n\s*{$airline}\s+confirmation code:\s*([A-Z\d\-]+)#", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//text()[contains(.,'Traveler and cost summary')]/ancestor::tr[1]/following-sibling::tr[contains(.,'Add Frequent')]/td[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+amount\s+charged\s+([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Flight\s+taxes/fees\s+([^\n]+)#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//text()[contains(.,'Depart')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s*:\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("preceding-sibling::tr[contains(normalize-space(.), 'Traveling to')][1]/following-sibling::tr[1]");

                            $dep = $date . ',' . uberTime(1);
                            $arr = $date . ',' . uberTime(2);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node('td[5]', null, true, "#^(.*?)\s+Flight:\s*\d+#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = node("following-sibling::tr[string-length(normalize-space(.))>1][1]");

                            return [
                                'Cabin'    => re("#^(.*?)\s+Class#", $info),
                                'Aircraft' => re("#,\s*([^\n,]+)\s*,\s*\d+\%\s+on#", $info),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*Duration\s*:\s*(\d+hr\s*\d+mn)#"));
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },
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
