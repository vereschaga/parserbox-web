<?php

namespace AwardWallet\Engine\hbooker\Email;

class ReservationCancellation extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#STORNIERUNG\s+der\s+Reservierung.*bei\s+hotelbooker.org#i";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#hbooker#i";
    public $reProvider = "#hbooker#i";
    public $xPath = "";
    public $mailFiles = "hbooker/it-1887151.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');
                    $text = preg_replace('#\n\s*>#i', "\n", $text);
                    $text = str_replace(' ', ' ', $text);

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Buchungsnummer:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotelname:\s+(.*)#i');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn'  => 'Anreise',
                            'CheckOut' => 'Abreise',
                        ];

                        foreach ($arr as $key => $value) {
                            $res[$key . 'Date'] = strtotime(str_replace('/', '.', re('#' . $value . ':\s+(.*)#i')));
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re('#Straße:\s+(.*)#i') . ', ' . re('#PLZ/Ort:\s+(.*)#i');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Telefon:\s+(.*)#i');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re('#Fax:\s+(.*)#i');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Personen:\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Zimmerbeschreibung:\s+(.*)#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Preis:\s+(.*)#i'), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(STORNIERUNG)\s+der\s+Reservierung.*bei\s+hotelbooker.org#', $text, $m)) {
                            return [
                                'Status'    => $m[1],
                                'Cancelled' => true,
                            ];
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Buchungsdatum:\s+(.*)#'));
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
        return ["de"];
    }
}
