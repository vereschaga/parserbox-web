<?php

namespace AwardWallet\Engine\lastminute\Email;

class It2634103 extends \TAccountCheckerExtended
{
    public $mailFiles = "lastminute/it-2634103.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Buchungsnummer: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->http->FindNodes('//td[normalize-space(.)="Reisende"]/following-sibling::td[string-length(normalize-space(.))>2][1]//text()[string-length(normalize-space(.))>2]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[text()='Gesamtpreis']/following::b[1]");

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Ihre Registrierung war erfolgreich')) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//td[contains(.,'Flugnummer:')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = rew('Flugnummer : (\w+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return reni('Flugnummer : \w+ (.+?) \bAn\b');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w+) $');
                        },
                    ],
                ],
            ],
        ];
    }

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'buchung@lastminute.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lastminute.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//td[contains(.,"Ihr") and contains(.,"lastminute.com") and contains(.,"Team") and not(.//td)]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"//www.lastminute.de") and contains(.,"//www.lastminute.de")]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"mailto:buchung@lastminute.com")]')->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
