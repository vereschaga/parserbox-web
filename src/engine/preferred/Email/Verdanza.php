<?php

namespace AwardWallet\Engine\preferred\Email;

class Verdanza extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@verdanzahotel\.com#i";
    public $reProvider = "#verdanzahotel\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+.+\s+(?:Verdanza|Grand\s+Cypress)#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "preferred/it-1729275.eml";
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
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Thank\s+you\s+for\s+choosing\s+the|Thank\s+you\s+for\s+confirming\s+your\s+reservation\s+at\s+the)\s+(.*)(?:\s+for\s+your|!! )#');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check\s+In:\s+(.*)#') . ' ' . re('#(?:Checkin\s+Time|Check\s+In\s+Time):\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check\s+Out:\s+(.*)#') . ' ' . str_replace('12 Noon', '12 PM', re('#(?:Checkout\s+Time|Check\s+Out\s+Time):\s+(.*)#')));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Hotel Description') or contains(., 'Helpful Information')]/ancestor::td[1]//text()";
                        $subj = implode("\n", nodes($xpath));
                        $regex = '#\s*.+\s+(?:Rating.*\s*|.+)\n\s*((?s).*)\s+Phone:\s+(.*)\s+Fax:\s+(.*)#';

                        if (preg_match($regex, $subj, $m)) {
                            return [
                                'Address' => nice($m[1], ','),
                                'Phone'   => $m[2],
                                'Fax'     => $m[3],
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Guest Info')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//text()";

                        return [array_values(array_filter(nodes($xpath)))[0]];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Adults:\s+(\d+)#');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Children:\s+(\d+)#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Rooms:\s+(\d+)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Type:\s+(.*)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.) = 'Cancellation:']/following::text()[1]";

                        return node($xpath);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Charge')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]";

                        return cost(node($xpath));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Tax', +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Total Charge', +1);

                        if ($subj) {
                            return [
                                'Total'    => cost($subj),
                                'Currency' => currency($subj),
                            ];
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Book\s+Date:\s+(.*)#'));
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'reservations@verdanzahotel.com') !== false || strpos($from, 'reservations@villasgrandcypress.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ((strpos($body, 'Verdanza') !== false || strpos($body, 'Grand Cypress') !== false) && strpos($body, 'Thank you for') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], 'reservations@verdanzahotel.com') !== false || strpos($headers["from"], 'reservations@villasgrandcypress.com') !== false;
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
