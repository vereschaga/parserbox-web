<?php

namespace AwardWallet\Engine\sncf\Email;

class TicketBookingConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#noreply@uk\.voyages-sncf\.com#i";
    public $reProvider = "#uk\.voyages-sncf\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+with\s+Voyages-sncf\.com\s+This\s+is\s+confirmation\s+of\s+your\s+booking#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Voyages-sncf\.com:\s+Your\s+Ticket\s+Booking\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "sncf/it-1780714.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#reference\s+number:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[contains(., "Passenger details")]/ancestor::tr/following-sibling::tr[contains(., "Adult Tickets")]//tr[not(.//tr)][2]';

                        return nodes($xpath);
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[(contains(normalize-space(.), "st Leg:") or contains(normalize-space(.), "nd Leg:") or contains(normalize-space(.), "rd Leg:")) and not(.//tr)]/following-sibling::tr[1]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep', 'Arr'] as $value) {
                                $regex = '#';
                                $regex .= $value . ':\s+';
                                $regex .= '(?P<Name>(?s).*?)\s+';
                                $regex .= '(?P<Time>\d+:\d+)\s+';
                                $regex .= '(?P<Day>\d+)/(?P<Month>\d+)/(?P<Year>\d+)';
                                $regex .= '#';

                                if (preg_match($regex, $node->nodeValue, $m)) {
                                    $res[$value . 'Name'] = nice($m['Name']);
                                    $dateStr = $m['Day'] . '.' . $m['Month'];
                                    $dateStr .= '.' . ((strlen($m['Year']) == 2) ? '20' . $m['Year'] : $m['Year']);
                                    $dateStr .= ', ' . $m['Time'];
                                    $res[$value . 'Date'] = strtotime($dateStr);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:\s+(.*)#i');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Coach/Seat:\s+(\d+/\d+)#i');
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
