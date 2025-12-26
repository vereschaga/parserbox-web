<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class It2105717 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?ichotelsgroup#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#HOLIDAY INN EXP#i', 'us', ''],
        ['#Crowne Plaza San Diego#i', 'us', ''],
        ['#ihg_confirmation_logo#i', 'us', ''],
        ['#Confirmation\# \d+ Holiday Inn#i', 'us', ''],
    ];
    public $reFrom = [
        ['#ichotelsgroup#i', 'us', ''],
    ];
    public $reProvider = [
        ['#ichotelsgroup#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "6679";
    public $upDate = "28.04.2015, 11:45";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-11.eml, ichotelsgroup/it-2105717.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("application/pdf", "text")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Confirmation Number is|Su numero de Confirmacion es)\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Thank you for making your reservation at the|Agradecemos su preferencia por)\s+(.*?)[\.\;]?\s*(?:We\s+have|a continuacion encontrara)#ix");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $time = re("#(?:Check in time is|La hora de entrada será a las) (\d+:\d+\s*[APMapmhrs]+)#x");
                        $rate = '';
                        $type = '';

                        if (preg_match("#\n\s*(?:Room Type|Tipo De Habitación)\s+(\d+)\-(\d+)\-(\d+)\s+(\d+)\-(\d+)\-(\d+)\s+([\d.]+\s*[A-Z]{3})\s+([^\n]+)#", $text, $m)) {
                            $in = $m[3] . '-' . $m[1] . '-' . $m[2] . ', ' . $time;
                            $out = $m[6] . '-' . $m[4] . '-' . $m[5];
                            $rate = $m[7];
                            $type = $m[8];
                        } elseif (preg_match('/\n\s*(?:Room Type|Tipo De Habitación)\s*.*?\s+([\d\.,]+ [A-Z]{3})\s+\d{1,2}\s+([^\n]+)\s+(\d+)\-(\d+)\-(\d+)\s+(\d+)\-(\d+)\-(\d+)/ui', $text, $m)) {
                            $rate = $m[1];
                            $type = $m[2];
                            $in = $m[5] . '-' . $m[4] . '-' . $m[3] . ', ' . $time;
                            $out = $m[8] . '-' . $m[7] . '-' . $m[6];
                        }

                        $in = str_replace('hrs', '', $in);

                        return [
                            'CheckInDate'  => strtotime($in),
                            'CheckOutDate' => strtotime($out),
                            'Rate'         => $rate,
                            'RoomType'     => $type,
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#(?:Reservations Office|Reservaciones)\s*\n\s[^\n]+\s+(.*?)\s+(?:Telephone|Tel)\s*:#ims"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n?\s*(?:Telephone|Tel)\s*:\s*([\(\)\d\- +]+)#"));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\s+Fax\s*:\s*([\(\)\d\- +]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#(?:^|\n)\s*\d+\-[A-Z]{3}\-\d{4}\s*\n\s*([^\n]+)#")];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('( (?:
								If you wish to cancel |
								If you find it necessary to cancel
							) .+? [.]
						)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('We have reserved')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#(?:^|\n)\s*(\d+\-[A-Z]{3}\-\d{4})#"));
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                ((stripos($text, 'Reservations Office') !== false) || false !== stripos($text, 'Reservaciones'))
                && ((stripos($text, 'Thank you for making your reservation at the Holiday') !== false) || false !== stripos($text, 'Agradecemos su preferencia por Holiday'))
            ) {
                return true;
            }
        }

        return false;
    }
}
