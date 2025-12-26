<?php

namespace AwardWallet\Engine\lufthansa\Email;

class CheckIn2 extends \TAccountCheckerExtended
{
    public $mailFiles = "lufthansa/it-1605765.eml, lufthansa/it-1615991.eml, lufthansa/it-4327066.eml, lufthansa/it-4418888.eml, lufthansa/it-6.eml, lufthansa/it-6770103.eml, lufthansa/it-7170708.eml, lufthansa/it-7180021.eml, lufthansa/it-7244636.eml, lufthansa/it-8121071.eml";

    public $reBody = "Lufthansa";
    public $reBody1 = [
        "Please check-in and select your preferred seat", //en
        "The following flights are now ready for check-in", //en
        "checken Sie bereits jetzt für Ihren Flug ein und wählen Sie Ihren bevorzugten Sitzplatz", //de
        "Enregistrez-vous dès maintenant sur le vol et choisissez votre siège à bord", //fr
        "Facture su vuelo con la mayor antelación para elegir su asiento preferido", //es
        "può già effettuare il check-in per il suo volo e scegliere il posto che desidera", //it
        "Зарегистрируйтесь на свой рейс и выберите место в салоне.", //ru
        "Realice el Check-in de su próximo vuelo con la mayor antelación para elegir su asiento preferido.", //es
    ];
    public $reBody7 = "Nous vous souhaitons un"; //fr agréable - agr&eacute;able
    public $reBody8 = "siguientes vuelos están listos para el Check-in"; //es

    public $reFrom = ["lufthansa.com", "lufthansa-group.com"];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "(//td[starts-with(normalize-space(.),'Passenger') or starts-with(normalize-space(.),'Passagier') or starts-with(normalize-space(.),'Passager') or starts-with(normalize-space(.),'Pasajero') or starts-with(normalize-space(.),'Passeggero') or starts-with(normalize-space(.),'Пассажир')]/following::td[normalize-space()!=''][1])[1]";

                        return [node($xpath)];
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        foreach ($this->reBody1 as $phrase) {
                            if ($this->http->XPath->query('//node()[contains(.,"' . $phrase . '")]')->length > 0) {
                                return CONFNO_UNKNOWN;
                            }
                        }

                        return null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//img[contains(./../@class,'flight_nr') or contains(@src,'logo_sm')]/ancestor::tr[contains(.,':')][1]";
                        $segments = $this->http->XPath->query($xpath);
                        $this->dateStr = node("./ancestor::tr[1]/preceding-sibling::tr[contains(.,'−')][2]/preceding-sibling::tr[2]", $segments->item(0));

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/([A-Z\d]{2})(\d+)/', node('./td[1]'), $m)) {
                                return ['AirlineName' => $m[1], 'FlightNumber' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 'take_off', 'Arr' => 'land'] as $key => $value) {
                                $time = node("./td[2]//img[contains(@src, '" . $value . ".gif')]/ancestor::td[1]/following-sibling::td[1]");
                                $res[$key . 'Date'] = strtotime($this->dateStr . ', ' . re('#\d+:\d+#', $time));

                                if (preg_match('#\+\d+#', $time, $m)) {
                                    $res[$key . 'Date'] = strtotime($m[0] . ' day', $res[$key . 'Date']);
                                }
                                $subj = node("./td[2]//img[contains(@src, '" . $value . ".gif')]/ancestor::td[1]/following-sibling::td[2]");

                                $regex = '#(?<' . $key . 'Name>.*)\s+\((?<' . $key . 'Code>\w{3})\)(\s*(?<Terminal>.*))?#';

                                if (preg_match($regex, $subj, $m)) {
                                    if (isset($m['Terminal'])) {
                                        $m['Terminal'] = trim(preg_replace('/^Terminal/', '', $m['Terminal']));
                                        $keyMap = ['Dep' => 'Departure', 'Arr' => 'Arrival'];
                                        $m[$keyMap[$key] . 'Terminal'] = $m['Terminal'];
                                    }
                                    copyArrayValues($res, $m, [$key . 'Name', $key . 'Code', $keyMap[$key] . 'Terminal']);
                                } elseif (preg_match('#\((?<' . $key . 'Code>\w{3})\)#', $subj, $m)) { // (ZAQ)
                                    copyArrayValues($res, $m, [$key . 'Code']);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re('#(\w+)\s+Class#', node(".//text()[contains(.,'Class')]")),
                                node('td[2]/descendant::tr[count(td)=1][1]/td[normalize-space(.)][1]', null, true, '#(\w+)\s+Class#'),
                                node('./ancestor::tr[1]/following-sibling::tr[2]', null, true, '#(\w+)\s+Class#'),
                                null
                            );
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom[0]) !== false || stripos($from, $this->reFrom[1]) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody1 as $value) {
            if (stripos(html_entity_decode($body), $value) !== false && stripos($body, $this->reBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        return stripos($headers['from'], $this->reFrom[0]) !== false || stripos($headers['from'], $this->reFrom[1]) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'de', 'fr', 'es', 'it', 'ru'];
    }
}
