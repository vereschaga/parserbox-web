<?php

namespace AwardWallet\Engine\hotelclub\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $rePlain = "#HotelClub\s+record\s+locator#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#confirmation@hotelclub\.com#i";
    public $reProvider = "#hotelclub\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "hotelclub/it-1730629.eml, hotelclub/it-1908852.eml, hotelclub/it-2030092.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $q = white('Hotel confirmation (?:.+?) : ([\w\-]+)');

                        if (!preg_match_all("/$q/", $text, $ms)) {
                            return;
                        }
                        $confs = $ms[1];

                        $res = [];
                        $res['ConfirmationNumber'] = $confs[0];

                        if (sizeof($confs) > 1) {
                            $res['ConfirmationNumbers'] = implode(',', $confs);
                        }

                        return $res;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(.*)\s+hotel\s+details#'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Check-in:\s+\w+,\s*(\d+\s+\w+\s+\d+)\s+\|\s+Check-out:\s+\w+,\s*(\d+\s+\w+\s+\d+)#';

                        if (preg_match($regex, $text, $m1)) {
                            $ciDateStr = $m1[1];
                            $coDateStr = $m1[2];

                            if (preg_match('#Hotel\s+check-in/check-out:\s+(\d{2})(\d{2})\s+(\d{2})(\d{2})#', $text, $m2)) {
                                $ciDateStr .= ', ' . $m2[1] . ':' . $m2[2];
                                $coDateStr .= ', ' . $m2[3] . ':' . $m2[4];
                            }

                            return [
                                'CheckInDate'  => strtotime($ciDateStr),
                                'CheckOutDate' => strtotime($coDateStr),
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+Phone:\s+(.*?)\s+(?:\|\s+Fax:\s+(.*))?#', $text, $m)) {
                            return [
                                'Address' => nice($m[1]),
                                'Phone'   => $m[2],
                                'Fax'     => $m[3] ?? null,
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Hotel reservations under:\s+(.*)#')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Guest\(s\):?\s+(\d+)#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\(s\):?\s+(\d+)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $xpath = "(//text()[contains(., 'Cancellation:')]/ancestor::*[2])[1]/following-sibling::*/*";

                        return nice(implode("\n", (array_filter(nodes($xpath)))));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Room description: (.+?)
							Special requests:
						');

                        if (preg_match_all("/$q/", $text, $ms)) {
                            $types = $ms[1];

                            return implode('|', $types);
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell(['Amount due at booking', 'Total trip cost'], +1);

                        if ($subj) {
                            return [
                                'Total'    => cost($subj),
                                'Currency' => currency($subj),
                            ];
                        }
                    },
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
