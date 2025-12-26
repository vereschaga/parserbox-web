<?php

namespace AwardWallet\Engine\hotwire\Email;

class It1935413 extends \TAccountCheckerExtended
{
    public $rePlain = "#marcas\s+comerciales\s+de\s+Hotwire#i";
    public $rePlainRange = "-500";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $reFrom = "#@hotwire[.]com#i";
    public $reProvider = "#[@.]hotwire[.]com#i";
    public $caseReference = "6934";
    public $xPath = "";
    public $mailFiles = "hotwire/it-1935413.eml";
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
                        $q = whiten('Confirmación del hotel: ([\w-]+)');

                        return re("#$q#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'a clientes de Hotwire')]/ancestor::*[1]/div[2]");
                        $addr = between($name, 'Teléfono :');

                        return [
                            'HotelName' => nice($name),
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dates = node("//*[contains(text(), 'Fechas')]/following::tr[1]/td[1]");

                        if (!preg_match('/(.+?)\s*-\s*(.+)/', $dates, $ms)) {
                            return;
                        }
                        $date1 = nice($ms[1]);
                        $date2 = nice($ms[2]);

                        return [
                            'CheckInDate'  => strtotime($date1),
                            'CheckOutDate' => strtotime($date2),
                        ];
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return between('Teléfono :', 'Confirmación del hotel:');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), 'Fechas')]/following::li[1]");
                        $q = whiten('(.+?) debe estar presente');
                        $name = re("/$q/i", $info);

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $n = node("//*[contains(text(), 'Fechas')]/following::tr[1]/td[2]");

                        return re("#(\d+)#", $n);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        $n = node("//*[contains(text(), 'Fechas')]/following::tr[1]/td[3]");

                        return re("#(\d+)#", $n);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $n = node("//*[contains(text(), 'Fechas')]/following::tr[1]/td[4]");

                        return re("#(\d+)#", $n);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = node("//*[contains(text(), '/noche') or contains(text(), '/ noche')]");

                        return nice($rate);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between(
                            'Política de cancelación del hotel',
                            'Garantía de precios bajos de Hotwire'
                        );
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Subtotal', +1);

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Tax recovery charges & fees:', +1);

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Precio total del viaje', +1);

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Esta es la confirmación del viaje que reservaste\.');

                        if (re("#$q#i")) {
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
}
