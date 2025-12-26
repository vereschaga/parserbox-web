<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It1879398 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*De\s*:[^\n]*?@lavoueuviagens[.]com[.]br#i";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#LÁVOUEU\s+VIAGENS#";
    public $rePDFRange = "/1";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#@lavoueuviagens[.]com[.]br#i";
    public $reProvider = "#[@.]lavoueuviagens[.]com[.]br#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "lavoueu/it-1879398.eml, lavoueu/it-1879399.eml, lavoueu/it-1879401.eml, lavoueu/it-1879402.eml, lavoueu/it-1879403.eml, lavoueu/it-2094808.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $conf = node("(//*[contains(text(), 'Nr. Confirmação')]/ancestor::p[1]/following-sibling::p[1])[1]");

                        return nice($conf);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("(//*[contains(text(), 'Para (To):')]/ancestor::p[1]/following-sibling::p[3])[1]");
                        $name = orval(
                            re_white('\( (.*?) \)', $name),
                            $name
                        );
                        $name = nice($name);

                        return [
                            'HotelName' => $name,
                            'Address'   => $name,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("(//*[contains(text(), 'Check-In')]/ancestor::p[1]/following-sibling::p[1])[1]");
                        $date = \DateTime::createFromFormat('d/m/Y', $date);
                        $date = $date ? $date->setTime(0, 0)->getTimestamp() : null;

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("(//*[contains(text(), 'Check-Out')]/ancestor::p[1]/following-sibling::p[1])[1]");
                        $date = \DateTime::createFromFormat('d/m/Y', $date);
                        $date = $date ? $date->setTime(0, 0)->getTimestamp() : null;

                        return $date;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $phone = node("(//*[contains(text(), 'Endereço (Address):')]/ancestor::p[1]/following-sibling::p[4])[1]");

                        return nice($phone);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $guest = node("(//*[contains(text(), 'Cliente (Client):')]/ancestor::p[1]/following-sibling::p[2])[1]");

                        return [nice($guest)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $guests = node("(//*[contains(text(), 'Hóspedes')]/ancestor::p[1]/following-sibling::p[1])[1]");

                        return re('#\d+#', $guests);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $rooms = node("(//*[contains(text(), 'Nr. Apts')]/ancestor::p[1]/following-sibling::p[1])[1]");

                        return $rooms;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type1 = node("(//*[contains(text(), 'Categoria')]/ancestor::p[1]/following-sibling::p[1])[1]");
                        $type2 = node("(//*[contains(text(), 'Categoria')]/ancestor::p[1]/following-sibling::p[3])[1]");

                        return nice("$type1, $type2");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("(//*[contains(text(), 'Cliente (Client):')]/ancestor::p[1]/following-sibling::p[3])[1]");
                        $date = re('#\d+/\d+/\d+#', $date);
                        $date = \DateTime::createFromFormat('d/m/Y', $date);
                        $date = $date ? $date->setTime(0, 0)->getTimestamp() : null;

                        return $date;
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
