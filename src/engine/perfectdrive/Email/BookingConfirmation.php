<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Your reservation is confirmed for.*?Budget Rent a Car#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reservations@budget\.com\.au#i";
    public $reProvider = "#budget\.com\.au#i";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1919231.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+Number\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'PICKUP', 'Dropoff' => 'RETURN'] as $key => $value) {
                            $subj = cell($value, 0, 0, '//text()');
                            $regex = '#';
                            $regex .= $value . '\s+';
                            $regex .= '(.*)\s+';
                            $regex .= '((?s).*)\s*';
                            $regex .= '\n\s*([\+\d\(][\+\d\(\)\s*\-]{8,})\s*\n*';
                            $regex .= '#i';

                            if (preg_match($regex, $subj, $matches)) {
                                $res[$key . 'Datetime'] = strtotime($matches[1]);
                                $res[$key . 'Location'] = nice($matches[2], ',');
                                $res[$key . 'Phone'] = trim($matches[3]);
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//td[.//img[contains(@src, "man_icon.gif")] and not(.//td)]/following-sibling::td[1]');

                        if (preg_match('#(.*)\s+\((.*)\)\s+Driver:\s+(.*?)\s+-#i', $subj, $matches)) {
                            return [
                                'CarModel'   => $matches[1],
                                'CarType'    => $matches[2],
                                'RenterName' => $matches[3],
                            ];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Estimated\s+Total\s+(.*)#'));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+reservation\s+is\s+(confirmed)\s+for#i');
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
