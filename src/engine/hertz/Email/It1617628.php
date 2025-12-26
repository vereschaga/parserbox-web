<?php

namespace AwardWallet\Engine\hertz\Email;

use PlancakeEmailParser;

// TODO: Intersects with Itinerary1, It3688101.
// TODO: if possible get rid of this parser
class It1617628 extends \TAccountCheckerExtended
{
    public $mailFiles = "";

    private $subjects = [
        'Reserva Hertz',
        'A minha reserva Hertz',
    ];

    private $detects = [
        'Thanks for Travelling at the Speed of Hertz',
        // TODO: It3688101 'Thanks for Traveling at the Speed of Hertz',
        'HERTZ CARS CANNOT CROSS BETWEEN ISLANDS, SEE IMPT INFO PAGES',
        'If you have questions about the acceptability of your form of payment, call Hertz',
        'you have successfully checked-in, and your booking confirmation number is below',
        'Please retain cancellation number',
        'the same credit card used when making your',
        'Hertz allows you to cancel prepaid and pay at location reservations',
    ];

    private $prov = 'hertz';

    private $date;
    private $text;
    private $pickUpAndReturn = [
        'Pickup and Return Location', 'Pickup Location & Return Location', 'Pick-up and Return Location', 'Endereço', 'Morada',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $this->text = $this->htmlToText(!empty($this->parser->getHTMLBody()) ? $this->parser->getHTMLBody() : $this->parser->getPlainBody(), true);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('/cancellation number\s+(\w+)/'),
                            re("#confirmation number is:\s*([\d\w\-]+)#i"),
                            re("#Confirmation Number:\s*([\d\w\-]+)#"),
                            re("#Hertz Reservation\s+([A-Z\d\-]+)#"),
                            re("#Seu número de confirmação é\s*:\s*([A-Z\d\-]+)#"),
                            re("#O seu número de Confirmação de Reserva é:\s*([A-Z\d\-]+)#"),
                            re("#Su número de confirmación es el siguiente:\s*([A-Z\d\-]+)#")
                        );
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        //						$text = text(xpath("//*[contains(text(), 'Pickup and Return Location') or contains(text(), 'Pickup Location & Return Location')]/ancestor-or-self::td[1]"));
                        $text = implode("\n", nodes("//*[contains(text(), 'Pickup and Return Location') or contains(text(), 'Pickup Location & Return Location')]/ancestor-or-self::td[1]/descendant::text()[normalize-space()]"));

                        if (!$text) {
                            $text = $this->text();
                        }

                        if (0 < $this->http->XPath->query("//td[({$this->contains(['Pickup Location', 'Pick Up Location', 'Loja de Retirada:', 'Estação de levantamento'])}) and not(.//td)]/ancestor::tr[1]/following-sibling::tr[{$this->contains(['Address', 'Endereço', 'Morada'])}]")->length) {
                            // it-1912655.eml
                            $phonePickup = $this->re("/([+(\d][-. \d)(]{5,}[\d)])/", $this->getNode(['Phone Number', 'Tel: :', 'Número de Telefone: :']));
                            $phoneDropoff = $this->re("/([+(\d][-. \d)(]{5,}[\d)])/", $this->getNode(['Phone Number', 'Tel: :', 'Número de Telefone: :'], null, 2));
                            $faxPickup = $this->getNode(['Fax Number', 'Número de fax::', 'Número de fax: :']);
                            $faxDropoff = $this->getNode(['Fax Number', 'Número de fax::', 'Número de fax: :'], null, 2);

                            return [
                                'PickupLocation'  => $this->getNode(['Pick Up Location', 'Pickup Location', 'Loja de Retirada:', 'Estação de levantamento']) . ', ' . $this->getNode(['Address', 'Endereço', 'Morada']),
                                'PickupHours'     => $this->getNode(['Hours of Operation', 'Horário comercial:', 'Horário:']),
                                'PickupPhone'     => $phonePickup,
                                'PickupFax'       => (empty($faxPickup) ? null : $faxPickup),
                                'DropoffLocation' => $this->getNode(['Return Location', 'Loja de Devolução:', 'Cidade de devolução']) . ', ' . $this->getNode(['Address', 'Endereço', 'Morada'], null, 2),
                                'DropoffHours'    => $this->getNode(['Hours of Operation', 'Horário comercial:', 'Horário:'], null, 2),
                                'DropoffPhone'    => $phoneDropoff,
                                'DropoffFax'      => (empty($faxDropoff) ? null : $faxDropoff),
                            ];
                        } else {
                            // it-1876765.eml, it-1879580.eml

                            $patterns['address'] = $this->opt(['Endereço', 'Address', 'Location'], '/');
                            $patterns['locEnd'] = $this->opt(['Phone Number', 'Hours of Operation', 'Horário comercial', 'Location Type', 'Tipo de loja'], '/');

                            if (preg_match("/^([\s\S]+)\s+\n[ ]*({$this->opt('Return Location', '/')}[.: ]*\n[\s\S]+)$/u", $text, $m)) {
                                $locations = [$m[1], $m[2]];
                            } else {
                                $locations = [$text];
                            }

                            $locPickup = nice(clear("/\n[ ]*{$patterns['address']}/",
                                re("/(?:^|\n)\s*(?:Pickup|Pick\-up|Local)(?: Location)?(?: and | & | de )(?:Return Location|retirada e devolução)[.:\s]+([\s\S]+?)\s+{$patterns['locEnd']}/iu", $locations[0])
                                ?? re("/(?:^|\n)\s*Pickup Location[.:\s]+([\s\S]+?)\s+{$patterns['locEnd']}/iu", $locations[0])
                            ), ',');
                            $phonePickup = re("/\n[ ]*(?:Tel|Phone Number)[:\s]*(.+)/", $locations[0]);
                            $faxPickup = re("/\n[ ]*(?:Número de fax|Fax Number)[:\s]*(.+)/", $locations[0]);
                            $hoursPickup = re("/\n[ ]*(?:Horário comercial|Hours of Operation)[:\s]*(.+)/", $locations[0]);

                            if (empty($locations[1])) {
                                // the same in this email
                                $locDropoff = $locPickup;
                                $phoneDropoff = $phonePickup;
                                $faxDropoff = $faxPickup;
                                $hoursDropoff = $hoursPickup;
                            } else {
                                $locDropoff = nice(clear("/\n[ ]*{$patterns['address']}/", re("/^\s*Return Location[.:\s]+([\s\S]+?)\s+{$patterns['locEnd']}/iu", $locations[1])), ',');
                                $phoneDropoff = re("/\n[ ]*(?:Tel|Phone Number)[:\s]*(.+)/", $locations[1]);
                                $faxDropoff = re("/\n[ ]*(?:Número de fax|Fax Number)[:\s]*(.+)/", $locations[1]);
                                $hoursDropoff = re("/\n[ ]*(?:Horário comercial|Hours of Operation)[:\s]*(.+)/", $locations[1]);
                            }

                            return [
                                'PickupLocation'  => $locPickup,
                                'PickupPhone'     => $phonePickup,
                                'PickupFax'       => $faxPickup,
                                'PickupHours'     => $hoursPickup,
                                'DropoffLocation' => $locDropoff,
                                'DropoffPhone'    => $phoneDropoff,
                                'DropoffFax'      => $faxDropoff,
                                'DropoffHours'    => $hoursDropoff,
                            ];
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (false !== stripos($text, 'We\'ve cancelled your reservation')) {
                            return [
                                'Status'    => 'cancelled',
                                'Cancelled' => true,
                            ];
                        }

                        return false;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $pickupDateTime = ['Retirada', 'Levantamento', 'Pick Up time', 'Pick-Up Time', 'Pick-up Time', 'Pickup Time', 'Pick-up time'];

                        if (in_array(trim(node("//text()[{$this->starts($pickupDateTime)}]/ancestor::td[1]")), $pickupDateTime)) {
                            $date = node("//text()[{$this->starts($pickupDateTime)}]/ancestor::td[1]/following::td[normalize-space(.)!=''][2]");
                        } elseif (($dtre = implode('|', $pickupDateTime)) && ($dt = node("//text()[{$this->starts($pickupDateTime)}]", null, true, "/(?:{$dtre})\s*:\s*(\w+,\s+\w+\s+\d{1,2},\s+\d{2,4}\s+at\s+\d{1,2}:\d{2}\s*[ap]m)/iu"))) {
                            $date = $dt;
                        } elseif (!empty($dt = $this->re("/{$this->opt($pickupDateTime)}\s*\n(\w+\,\s*\d+\s*\w+\,\s+\d{4}\s+at\s*[\d\:]+)\s*\n/", $this->text))) {
                            $date = $dt;
                        } else {
                            $date = node("//text()[{$this->starts($pickupDateTime)}]/following::text()[normalize-space(.)][1]");
                        }

                        return strtotime(en(uberDate($date) . ', ' . uberTime($date)), $this->date);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dropoffDateTime = ['Devolução', 'Devolução', 'Return time', 'Return Time', 'Return time'];

                        if (in_array(trim(node("//text()[{$this->starts($dropoffDateTime)}]/ancestor::td[1]")), $dropoffDateTime)) {
                            $date = node("//text()[{$this->starts($dropoffDateTime)}]/ancestor::td[1]/following::td[normalize-space(.)!=''][2]");
                        } elseif (($dtre = implode('|', $dropoffDateTime)) && ($dt = node("//text()[{$this->starts($dropoffDateTime)}]", null, true, "/(?:{$dtre})\s*:\s*(\w+,\s+\w+\s+\d{1,2},\s+\d{2,4}\s+at\s+\d{1,2}:\d{2}\s*[ap]m)/iu"))) {
                            $date = $dt;
                        } elseif (!empty($dt = $this->re("/{$this->opt($dropoffDateTime)}\s*\n(\w+\,\s*\d+\s*\w+\,\s+\d{4}\s+at\s*[\d\:]+)\s*\n/", $this->text))) {
                            $date = $dt;
                        } else {
                            $date = node("//text()[{$this->starts($dropoffDateTime)}]/following::text()[normalize-space(.)][1]");
                        }

                        return strtotime(en(uberDate($date) . ', ' . uberTime($date)), $this->date);
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RentalCompany' => trim(orval(
                                re("#Thanks for Travel+ing at the Speed of\s+([^,\n]+)\s*,\s*([^\n]+)#i"),
                                re("#The (Hertz) Corporation#")
                            )),
                            'RenterName' => re(2),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $raw = re("#\n\s*(?:Your\s+Vehicle|Veículo)[:\s]+(.+?)\n\s*?\n#s");

                        if (re("#(.+?)\n\s*(.+?\s+(?:or\s+similar|ou\s+similar))#is", $raw)) {
                            $data['CarType'] = nice(re(1));
                            $data['CarModel'] = nice(re(2));
                        } elseif (re("#(.+?\s+(?:or\s+similar|ou\s+similar))\s*\n\s*(.+)#i", $raw)) {
                            $data['CarType'] = nice(re(2));
                            $data['CarModel'] = nice(re(1));
                        } else {
                            $raw = re("#(\n\s*(?:Your\s+Vehicle|Veículo)(?:[^\n]*\n*){10})#");
                            $data['CarType'] = nice(re("#\s*(?:Your\s+Vehicle|Veículo)[:\s]+(.+?)\s*(?:\(\w+\)|Group\s.+?\-|\n{2,})\s+(.+?(?:\s+\w*\s*similar|\n))#is", $raw));
                            $data['CarModel'] = clear("#^\s*(? >\([^\)]*\)|Group\s.+?\-|\n{2,})\s*#i", nice(re(2)));

                            if (empty($data['CarModel'])) {
                                $data['CarModel'] = $this->re('/([\(\)A-Z\d]+ [ a-z\d]+)\s+or similar/is', $raw);
                            }

                            if (!$data['CarType']) {
                                $data['CarType'] = nice(re("#\n\s*(?:Your\s+Vehicle|Veículo)[:\s]+([^\n]+)\n([^\n]+)#is", $raw));
                                $data['CarModel'] = trim(re(2));
                            }

                            if (empty($data['CarModel'])) {
                                $data['CarModel'] = trim($this->http->FindSingleNode("(//img[contains(@src, 'images.hertz.com/vehicles')]/ancestor::tr[1]/following-sibling::tr[3]//text()[normalize-space(.)])[1]"));
                            }
                        }

                        return $data;
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("//img[contains(@src, 'vehicles/')]/@src", null, false, "/^https?:\/\/\S+$/"),
                            node("//*[contains(normalize-space(text()), 'Your Vehicle')]/ancestor::table[1]/following::table[1]//img[1]/@src", null, false, "/^https?:\/\/\S+$/")
                        );
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $str = re("#\n\s*Thanks\s+for\s+Travel*ing\s+at\s+the\s+speed\s+of\s+Hertz.?,?\s*([^\n]+)#i");

                        if (empty($str)) {
                            $str = re("#Thank\s*you\s*for\s*choosing\s*Hertz\,\s*([^\n]+)#");
                        }

                        if (empty($str)) {
                            $str = re("#\n\s*([^\n]+)\s+Sua\s+reserva\s+foi\s+modificada\s+com\s+sucesso\s+e\s+seu#");
                        }

                        if (empty($str)) {
                            $str = trim(re('/(.+),\s+We\'ve cancelled your reservation/i', $text));
                        }

                        if (empty($str)) {
                            $str = trim(re('/Obrigada\s*por\s*viajar\s*a\s*velocidade\s*da\s*Hertz\,\s*(.+)/i', $text));
                        }

                        return $str;
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Rate Code|Código da tarifa|Optional Information)[:\s]*([\d\w\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Total Estimated Charge',
                            'Total Estimated* Charge',
                            'Total Approximate Charge',
                            'Valor a ser pago no balcão',
                        ];

                        return total(orval(
                            cell($variants, +1),
                            re("#\n\s*Total Estimated Charge\s+([^\n]+)#"),
                            re("#\n\s*Total[\s\*]+([\d.]+\s*[A-Z]{3})#")
                        ));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Tax and other charges|Taxes|Total Sales Tax|TAX)\s+([\d.,]+)#"));
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*(?:Tipo de loja|Service\s+Type)[:\s]+([^\n]+?)(?:\s\s\s+|\n)#"));
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Discounts.*?\n\s*\d+\s*(?:Week|days) at\s+([^\n]+)\s+([\d.,]+\s*[A-Z]{3})\b#msi", $text, 2),
                            re(1)
                        );
                    },

                    "Discounts" => function ($text = '', $node = null, $it = null) {
                        $r = filter(preg_split("#\s*\n\s*#", re("#Your Vehicle.*?\n\s*Discounts:\s*(.*?)\s+Included in the rates#ms")));
                        $array = [];

                        foreach ($r as $d) {
                            $items = preg_split('#\s*:\s*:\s*#', $d);

                            if (count($items) == 2) {
                                [$name, $value] = $items;
                                $array[] = ['Code' => $name, 'Name' => $value];
                            } else {
                                break;
                            }
                        }

                        return $array;
                    },

                    "Fees" => function ($text = '', $node = null, $it = null) {
                        $nodes = $this->http->XPath->query("//div[normalize-space(.)='Fees']/following-sibling::table[1]/descendant::tr");
                        $fees = [];

                        foreach ($nodes as $node) {
                            $fees[] = [
                                'Name'   => $this->http->FindSingleNode('td[1]', $node),
                                'Charge' => $this->http->FindSingleNode('td[2]', $node, true, '/([\d\.]+)/'),
                            ];
                        }

                        return $fees;
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "pt"];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject'])) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (false !== stripos($headers['subject'], $subject)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]hertz[.]com/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        if ($this->http->XPath->query("//img[contains(@alt,'Hertz')]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->pickUpAndReturn)}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function getNode($field, $re = null, int $td = 1)
    {
        $field = (array) $field;

        return $this->http->FindSingleNode("(//text()[{$this->starts($field)}]/ancestor::*[ count(descendant::text()[string-length(normalize-space())>1])>1 ][1])[{$td}]", null, true, $re ? $re : "/{$this->opt($field, '/')}[\s:]*(.*)/");
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field, $delimiter = '#')
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function htmlToText($string, $view = false)
    {
        $text = preg_replace('/<[^>]+>/', "\n", html_entity_decode($string));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }
}
