<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It1907655 extends \TAccountCheckerExtended
{
    public $rePlain = "#mailto:corporativo@lavoueuviagens[.]com[.]br#i";
    public $rePlainRange = "1500";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#@lavoueuviagens[.]com[.]br#i";
    public $reProvider = "#[@.]lavoueuviagens[.]com[.]br#i";
    public $xPath = "";
    public $mailFiles = "lavoueu/it-1907655.eml, lavoueu/it-1907706.eml, lavoueu/it-1907773.eml";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $conf = node("(//*[contains(text(), 'Nr. Confirmação')]/following::p)[1]");

                        return nice($conf);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("(//*[contains(text(), 'Para (To):')]/following::p)[3]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("(//*[contains(text(), 'Check-In')]/following::p)[1]");
                        $date = \DateTime::createFromFormat('d/m/Y', $date);

                        if (!$date) {
                            return;
                        }

                        $date->setTime(0, 0);

                        return $date->getTimestamp();
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("(//*[contains(text(), 'Check-Out')]/following::p)[1]");
                        $date = \DateTime::createFromFormat('d/m/Y', $date);

                        if (!$date) {
                            return;
                        }

                        $date->setTime(0, 0);

                        return $date->getTimestamp();
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node("(//*[contains(text(), 'Endereço (Address):')]/following::p)[3]");
                        $city = node("(//*[contains(text(), 'Endereço (Address):')]/following::p)[5]");

                        return nice("$city, $addr");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = node("(//*[contains(text(), 'Cliente (Client):')]/following::p)[2]");

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'Hóspedes')]/following::p)[1]");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'Nr. Apts')]/following::p)[1]");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = node("(//*[contains(text(), 'Sr. Cliente,')])[1]");

                        if (preg_match('/,\s*(.+)/is', $cancel, $ms)) {
                            return nice($ms[1]);
                        }

                        return nice($cancel);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $cat = node("(//*[contains(text(), 'Categoria')]/following::p)[1]");
                        $type = node("(//*[contains(text(), 'Categoria')]/following::p)[3]");

                        return nice("$cat $type");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("(//*[contains(text(), 'Dt. Confirm.')]/following::p)[1]");
                        $date = \DateTime::createFromFormat('d/m/Y', $date);

                        if (!$date) {
                            return;
                        }

                        $date->setTime(0, 0);

                        return $date->getTimestamp();
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
}
