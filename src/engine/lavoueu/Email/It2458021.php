<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It2458021 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*De\s*:[^\n]*?[@.]lavoueuviagens[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]lavoueuviagens[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]lavoueuviagens[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "11.02.2015, 11:11";
    public $crDate = "11.02.2015, 10:16";
    public $xPath = "";
    public $mailFiles = "lavoueu/it-2458021.eml, lavoueu/it-2458058.eml, lavoueu/it-2458112.eml, lavoueu/it-2458114.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('text/rtf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('reserva nú mero      (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return reni('reserva nú mero \d+ no (.+?) [.]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Data de Chegada (.+)');
                        $date = ure('/(\d+-\d+-\d+)/', $info, 2);

                        return timestamp_from_format($date, 'd-m-y|');
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Data de Chegada (.+)');
                        $date = ure('/(\d+-\d+-\d+)/', $info, 1);

                        return timestamp_from_format($date, 'd-m-y|');
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return reni('
							Email: .+? \n
							(.+?)
							Tel:
						');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Tel: ([()+-\/\d\s]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: ([()+-\/\d\s]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $subj = $this->parser->getHeader('subject');

                        return [reni('ENC: ([a-z\s]+)', $subj)];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('- (Polí tica de Cancelamento: .+?) [.;] -');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('Agradecemos por escolher .+?[.]
							\w .+? \n
							(\w .+?) \n
							\d+ - \d+ - \d+
						');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Agradecemos por escolher .+?[.]
							(\w .+?) \n');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('prazer de confirmar sua reserva')) {
                            return 'confirmed';
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
        return ["es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
