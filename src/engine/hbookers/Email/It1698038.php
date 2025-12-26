<?php

namespace AwardWallet\Engine\hbookers\Email;

class It1698038 extends \TAccountCheckerExtended
{
    public $reFrom = "#hostelbookers#i";
    public $reProvider = "#hostelbookers#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hostelbookers#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#hostelbookers[.]com#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "hbookers/it-1698038.eml, hbookers/it-1783941.eml";
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
                        return re("#Reference\s*number\s*([A-Z\-\d]+)#i", cell(["Reference number"], 0, 0));
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return text(xpath("//*[contains(text(), 'Website:')]/ancestor::tr[1]/preceding-sibling::tr[3]//*[@size='+1']"));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#check-in:\s*(\d+/\d+/\d+)#i", cell(["Check-in"], 0, 0));
                        $time = re("#Check-in / Check-out:\s*(\d+:\d+)#i", cell("Check-in / Check-out:", 0, 0));
                        $datetime = sprintf('%s, %s', $date, $time);
                        $datetime = \DateTime::createFromFormat('d/m/Y, H:i', $datetime);

                        return $datetime ? $datetime->getTimestamp() : null;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#check-out:\s*(\d+/\d+/\d+)#i", cell(["Check-out"], 0, 0));
                        $time = re("#Check-in / Check-out:\s*(\d+:\d+)\s*/\s*(\d+:\d+)#i", cell("Check-in / Check-out:", 0, 0), 2);
                        $datetime = sprintf('%s, %s', $date, $time);
                        $datetime = \DateTime::createFromFormat('d/m/Y, H:i', $datetime);

                        return $datetime ? $datetime->getTimestamp() : null;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Website:')]/ancestor::tr[1]/preceding-sibling::tr[3]//*[@size='-1']";
                        $text = text(xpath($xpath));

                        return preg_replace('/\s+/', ' ', $text);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#telephone:\s*(.*)#i', cell('Telephone:', 0, 0));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#name:\s*(.*)#i', cell('Name:', 0, 0));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#people:\s*(.*)#i', cell('People:', 0, 0));
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = re('#(\d+[.]\d+)\s*[a-z]{3}#ims', cell('Cost per person', 0, +1));

                        return sprintf('%s / night', $rate);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/\s*(.*)[(](.*)[)]/i', cell('Room details', 0, +1), $ms)) {
                            return [
                                'RoomType'            => $ms[1],
                                'RoomTypeDescription' => $ms[2],
                            ];
                        }

                        return '';
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $re = '#(\d+[.]\d+)\s*([a-z]{3})#i';

                        return [
                            'Total'    => re($re, cell('Total', 0, +2), 1),
                            'Currency' => re($re, cell('Total', 0, +2), 2),
                        ];
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
