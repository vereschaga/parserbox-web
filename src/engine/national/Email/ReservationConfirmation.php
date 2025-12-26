<?php

namespace AwardWallet\Engine\national\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "national/it-1791447.eml, national/it-2575165.eml, national/it-2820351.eml";

    public $reFrom = "reservations@nationalcar.com";
    public $reSubject = [
        "conf"=> "National Reservation Confirmation",
        "canc"=> "National Reservation Cancellation",
    ];
    public $reBody = 'National Car Rental';
    public $reBody2 = [
        "en" => "Your Vehicle",
        'es' => 'Gracias por hacer su reservación con National Car Rental',
    ];

    public static $dict = [
        'en' => [],
        'es' => [
            'Confirmation'                      => 'Número de confirmación #',
            'Your\s+confirmation\s+number\s+is' => 'Su\s+número\s+de\s+confirmación\s+es', // re
            'Pickup'                            => 'Recogida',
            'Return'                            => 'Devolución',
            //            'Address' => '',
            'Phone'        => 'Teléfono',
            'Hours'        => 'Horario',
            'Your Vehicle' => 'Su vehículo',
            'or\s+similar' => 'o\s+similar', // re
            //            'Emerald\s+Club\s+Number' => '', // re
            //            'Your reservation has been' => '',
            //            'canceled',
            'Driver\s+Name'   => 'Nombre\s+del\s+controlador', // re
            'Estimated Total' => 'Precio total estimado',
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $body = $this->http->Response['body'];

                    foreach ($this->reBody2 as $lang => $re) {
                        if (strpos($body, $re) !== false) {
                            $this->lang = $lang;
                        }
                    }
                    $this->logger->info("LANG: {$this->lang}");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        $node = re("#{$this->t('Your\s+confirmation\s+number\s+is')}\s*:\s+([\w\-]+)#iu");

                        if ($node == null) {
                            $node = re("#{$this->t('Confirmation')}\s*[\#]\s*([\w-]+)#i");
                        }

                        return $node;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $xpath = "//td[contains(., '{$this->t('Pickup')}') and contains(., '{$this->t('Return')}') and not(.//td)]//text()";

                        if (count(nodes($xpath)) === 0) {
                            $xpath = "//text()[normalize-space(.)='{$this->t('Pickup')}']/ancestor::table[1][contains(., '{$this->t('Return')}')]//text()";
                        }
                        $subj = implode("\n", nodes($xpath));

                        $regex = '#';

                        $regex .= "{$this->t('Pickup')}\s+(?P<PickupLocation>.*?)\s+(?:\w+,\s+)?";
                        $regex .= "(?P<PickupDatetime>\w+\s+\d+,\s+\d+\s+\d+:\d+\s+(?:am|pm))\s+";
                        //$regex .= "Address:\s+(?P<PickupAddress>.*)\s+";
                        $regex .= "(?:{$this->t('Address')}:\s+)*(?P<PickupAddress>.*)\s+";
                        $regex .= "{$this->t('Phone')}\s*:\s+?(?:ext\:\s*{$this->t('MAIN')}\s+)*?(?P<PickupPhone>.*)\s+?(?:ext\:\s*{$this->t('MAIN')}\s+)*?";
                        $regex .= "{$this->t('Hours')}\s*:\s+(?P<PickupHours>.*)\s+";
                        $regex .= "{$this->t('Return')}\s+(?P<DropoffLocation>.*?)\s+(?:\w+,\s+)?";
                        $regex .= "(?P<DropoffDatetime>\w+\s+\d+,\s+\d+\s+\d+:\d+\s+(?:am|pm))";
                        $regex2 = $regex;
                        $regex .= "#siu";
                        $regex2 .= "\s+(?:{$this->t('Address')}:\s+)*(?P<DropoffAddress>.*)\s+{$this->t('Phone')}\s*:\s+?(?:ext\:\s*{$this->t('MAIN')}\s+)*?";
                        $regex2 .= "(?P<DropoffPhone>.*)\s+?(?:ext\:\s*{$this->t('MAIN')}\s+)*?{$this->t('Hours')}\s*:\s+(?P<DropoffHours>.*)\s+";
                        $regex2 .= "#siu";

                        if (preg_match($regex, $subj, $m)) {
                            $res['PickupDatetime'] = $this->normalizeDate($m['PickupDatetime']);
                            // $res['DropoffDatetime'] = strtotime(preg_replace("/\s+/u", " ", $m['DropoffDatetime']));
                            $res['DropoffDatetime'] = strtotime(preg_replace("/^\s*(\w{3})\w*\b/", "\\1", $m['DropoffDatetime']));
                            $res['PickupHours'] = nice(trim(preg_replace("#\s*\n\s*#", "\n", $m['PickupHours'])), '; ');
                            $keys = ['PickupLocation', 'PickupPhone', 'DropoffLocation'];
                            copyArrayValues($res, $m, $keys);
                            $res['PickupPhone'] = re("#([\d\(\) \-\+]+)#", $res['PickupPhone']);
                            $res = nice($res);
                            $res['PickupLocation'] .= ', ' . nice($m['PickupAddress'], ',');

                            if (preg_match($regex2, $subj, $m)) {
                                $res['DropoffLocation'] .= ', ' . nice($m['DropoffAddress'], ',');
                                $res['DropoffHours'] = nice(trim(preg_replace("#\s*\n\s*#", "\n", $m['DropoffHours'])), '; ');
                                $res['DropoffPhone'] = re("#([\d\(\) \-\+]+)#", $m['DropoffPhone']);
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $res = [
                            'CarType'     => null,
                            'CarModel'    => null,
                            'CarImageUrl' => null,
                        ];
                        $xpath = "//tr[contains(., '{$this->t('Your Vehicle')}') and not(.//tr)]/ancestor::table[1]/tbody//tr[count(./td) = 2]";
                        $subj = $this->http->XPath->query($xpath);

                        if ($subj->length == 1) {
                            $carInfoNode = $subj->item(0);
                            $subj = implode("\n", nodes('./td[1]//text()', $carInfoNode));

                            if (preg_match("#\s*\n\s*(.*)\s+(.*(?:\s+{$this->t('or\s+similar')})?)#i", $subj, $m)) {
                                $res['CarType'] = $m[1];
                                $res['CarModel'] = nice($m[2]);
                            }
                            $res['CarImageUrl'] = node('./td[2]//img/@src', $carInfoNode, true, "#^https?:\/\/\S+$#");
                        } else {
                            $xpath = "//text()[contains(., '{$this->t('Your Vehicle')}')]/following::table[1]/descendant::tr[count(./td) = 2]";
                            $subj = $this->http->XPath->query($xpath);

                            if ($subj->length == 1) {
                                $carInfoNode = $subj->item(0);
                                $subj = implode("\n", nodes('./td[2]//text()', $carInfoNode));

                                if (preg_match("#(.*)\s*\n\s*(.*\s+.*(?:\s+{$this->t('or\s+similar')})?)#i", $subj, $m)) {
                                    $res['CarType'] = $m[1];
                                    $res['CarModel'] = nice($m[2]);
                                }
                                //$res['CarImageUrl'] = node('./td[2]//img/@src', $carInfoNode);
                            }
                        }

                        return $res;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return niceName(re("#\n\s*{$this->t('Driver\s+Name')}\s+([^\n]+)#"));
                    /*$node = re('#Driver\s+Name\s+(.*)#i', node('//div[contains(., "Driver Name") and not(.//div)]'));
                    if($node == null){
                        $node = re('#Driver\s+Name\s+(.*)#i', node('//*[contains(., "Driver Name")]/ancestor-or-self::p'));
                    }
                    return $node;*/
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell($this->t('Estimated Total'), +1));
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+{$this->t('Emerald\s+Club\s+Number')}\s+(\d+)#i");
                    //return re('#Number\s+(.*)#i', node('//div[contains(., "Emerald Club Number") and not(.//div)]'));
                    },
                    "Status" => function ($text = '', $node = null, $it = null) {
                        $status = re("#{$this->t('Your reservation has been')}\s+(\w+)#i");

                        if ($status == $this->t('canceled')) {
                            return [
                                'Status'   => $status,
                                'Cancelled'=> true,
                            ];
                        }

                        return $status;
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d{1,2}),\s+(\d{2,4})\s+(\d{1,2}:\d{2}\s*[AP]M)$#iu",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{2,4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
