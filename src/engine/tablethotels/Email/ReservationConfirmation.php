<?php

namespace AwardWallet\Engine\tablethotels\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@tablethotels\.com#i";
    public $reProvider = "#tablethotels\.com#i";
    public $rePlain = "#Welcome\s+to\s+Tablet!\s+We're\s+happy\s+to\s+have\s+you\s+as\s+a\s+customer#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "tablethotels/it-1747951.eml, tablethotels/it-1747953.eml, tablethotels/it-1811256.eml";
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
                        return re('#confirmation\s+number:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[normalize-space(.) = "Hotel"]/ancestor::td[1]//text()';
                        $subj = implode("\n", nodes($xpath));
                        $regex = '#Hotel\s+(.*)\s+((?s).*)\s+Tel:\s+(.*?)\s*(?:Fax:\s+(.*))?\n#';

                        if (preg_match($regex, $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => str_replace('.', ' ', $m[3]),
                                'Fax'       => (isset($m[4])) ? str_replace('.', ' ', $m[4]) : null,
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $subj = node('//td[contains(., "Guests & Dates") and not(.//td)]');
                        $regex = '#Guests\s+&\s+Dates\s+(.*)\s+(\w+\s+\d+)\s+-\s+(\w+\s+\d+),\s+(\d{4})#';

                        if (preg_match($regex, $subj, $m)) {
                            $CITimeStr = re('#Arrival\s+Time\s*:\s+(\d+:\d+\s*(?:am|pm)?)#i', $subj);
                            $res['GuestNames'] = [$m[1]];
                            $res['CheckInDate'] = strtotime($m[2] . ', ' . $m[4] . ', ' . $CITimeStr);
                            $res['CheckOutDate'] = strtotime($m[3] . ', ' . $m[4]);
                        }

                        return $res;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s+Adult#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+Policy\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Room\s+Type\s+(.*)\s+(.*)#i', $text, $m)) {
                            return [
                                'RoomType'            => $m[1],
                                'RoomTypeDescription' => ($m[2] != '.') ? preg_replace('#This\s+room\s+features\s+#i', '', $m[2]) : null,
                            ];
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Subtotal', +1);

                        return total($subj, 'Total');
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Once\s+you\'ve\s+completed\s+your\s+stay,\s+you\'ll\s+earn\s+(.*?),#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Reservation\s+confirmed#i', $this->parser->getHeader('Subject'))) {
                            return 'Confirmed';
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
