<?php

namespace AwardWallet\Engine\triprewards\Email;

class ConfirmationPDF extends \TAccountCheckerExtended
{
    public $reFrom = "#(reservations@hotellascolinas\.com|stay@hojoanaheim\.com|reservations@wyndham\.com)#i";
    public $reProvider = "#(hotellascolinas|hojoanaheim|wyndham)\.com#i";
    public $rePlain = "";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#\b(Wyndham|Howard Johnson)\s+.*\s+Confirmation|Confirmation\s*\#\s*\d+.*?\s+Wyndham\s*Affiliate#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1733618.eml, triprewards/it-2968772.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->plainText = $this->getDocument('application/pdf', 'text');
                    $text = $this->setDocument('application/pdf', 'simpletable');

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+No\.\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Hotel\s+Name:\s+(.*)#i'),
                            re('#Thank you for choosing the\s+(.*?)\s+for your upcoming visit#')
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(orval(
                            str_replace('-', '/', re('#Arrival\s+Date:\s+([\d\-]+)#i')),
                            re('#Arrival\s+Date:\s+(\w+)\s+(\d+)\w+\s+(\d+)#i', $text, 2) . ' ' . re(1) . ' ' . re(3)
                        ));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(orval(
                            str_replace('-', '/', re('#Departure\s+Date:\s+([\d\-]+)#i')),
                            re('#Departure\s+Date:\s+(\w+)\s+(\d+)\w+\s+(\d+)#i', $text, 2) . ' ' . re(1) . ' ' . re(3)
                        ));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nice(re('#Address:\s+(.*)\s+Adult#si'), ','),
                            nice(re("#\n\s*{$it['HotelName']}\s*\n\s*(.*?)\s*\n\s*Tel\s*:\s*(.*?)\s*\|\s*Fax\s*:\s*(.*?)\s*\|#", $text, 1))
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Phone\s+No\.\s+(.*)#', $this->plainText),
                            re("#\n\s*{$it['HotelName']}\s*\n\s*(.*?)\s*\n\s*Tel\s*:\s*(.*?)\s*\|\s*Fax\s*:\s*(.*?)\s*\|#", $text, 2)
                        );
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Fax\s+No\.\s+(.*)#', $this->plainText),
                            re("#\n\s*{$it['HotelName']}\s*\n\s*(.*?)\s*\n\s*Tel\s*:\s*(.*?)\s*\|\s*Fax\s*:\s*(.*?)\s*\|#", $text, 3)
                        );
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $regex = '#We\s+are\s+pleased\s+to\s+confirm\s+the\s+following\s+(?:guest\s+|)reservation\s+for:\s+(.*)#';

                        return [re($regex)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Adults/Children\s*:\s*(\d+)\s*/\s*(\d+)#msi', $text, $m) || preg_match('#(\d+)\s*/\s*(\d+)\s+Adults/Children\s*:\s+No\.#msi', $text, $m)) {
                            return [
                                'Guests' => $m[1],
                                'Kids'   => $m[2],
                            ];
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#No\.\s+of\s+Rooms:\s+(\d+)#i');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Arrival\s+Room\s+Rate.*:\s+(.*)#');

                        if ($subj) {
                            return cost($subj);
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        // echo $text;
                        // die();
                        $subj = re('#Cancellation\s+Information/Policy\s*:\s*(.*?)\s+(?:Deposit|Experience)#msi');

                        if ($subj) {
                            return nice(preg_replace('#\.+#', '.', nice($subj, '.')));
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Description:\s+(.*)#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+cost.*:\s+(.*)#');

                        if ($subj) {
                            return cost($subj);
                        }
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(orval(
                            re('#Rates\s+are\s+quoted\s+in\s+(.*)#i'),
                            re('#information\s+in\s+([A-Z]{3})\s*:#i')
                        ));
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
