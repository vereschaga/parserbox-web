<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: when rewrite on object - see Api ota->

class It6098511 extends \TAccountChecker
{
    public $mailFiles = "tport/it-2162667.eml, tport/it-2163394.eml, tport/it-3110459.eml, tport/it-35627517.eml, tport/it-5424560.eml, tport/it-5537131.eml, tport/it-5567302.eml, tport/it-6732893.eml, tport/it-6787266.eml, tport/it-7480934.eml, tport/it-8889244.eml, tport/it-9938074.eml";

    public $reSubject = [
        'de' => ['Reiseplan anzeigen:'],
        'fr' => ['Afficher votre itinéraire'],
        'ru' => ['Просмотр маршрута поездки'],
        'it' => ['Visualizza il tuo itinerario', 'Visualizzare il proprio itinerario'],
        'pt' => ['Visualizar o seu itinerário', 'Exibir o seu itinerário:'],
        'es' => ['Ver tu itinerario'],
        'cs' => ['Zobrazit itinerář'],
        'sv' => ['Visa din resplan'],
        'en' => ['Itinerary – Detailed', 'View Your Itinerary:'],
    ];

    public $reBody2 = [
        "de"   => "Ihr Reservierungscode:",
        'de2'  => 'Reservierungsnummer:',
        "da"   => "Din reservationskode:",
        "da2"  => "Reservationsnummer:",
        "fr"   => "Votre numéro de réservation:",
        "fr2"  => "Numéro de réservation:",
        "ru"   => "Ваш код бронирования:",
        "it"   => "Codice di Prenotazione:",
        "it2"  => "Numero di prenotazione:",
        "pt"   => "O seu código de reserva:",
        "pt2"  => "Número da reserva:",
        'es'   => 'Puede acceder desde su Travelport',
        'es2'  => 'Número de confirmación:',
        'sv'   => 'Du kan öppna Travelport ViewTrip på din stationära dator, surfplatta och mobila enhet.',
        'sv2'  => 'Din resa har bokats.',
        'sv3'  => 'din resa är bokad.',
        'ja'   => 'お客様のご旅行が予約されました。ご旅行の詳細は',
        'ja2'  => 'Eメールメッセージ',
        'ja3'  => 'ありがとうございました。',
        'cs'   => 'Váš rezervační kód',
        'en'   => 'Your Reservation Code:',
        'en2'  => 'Confirmation Number:',
        'en3'  => 'Your travel agent has confirmed that your trip has been booked',
        'en4'  => 'Seamless trip itineraries at your fingertips',
    ];

    public static $dictionary = [
        "en" => [
            "Your Reservation Code:" => ["Your Reservation Code:", "Reservation Number:"],
            "NON STOP"               => ["NON STOP", "Non Stop"],
            "Confirmed:"             => ["Confirmed:", "Confirmation Number:"],
            //'View Your Itinerary:' => [""]
        ],
        "de" => [ // it-5567302.eml
            "Your Reservation Code:" => ["Ihr Reservierungscode:", 'Reservierungsnummer:'],
            "DEPART"                 => ["ABFLUG", 'ABFAHRT'],
            "ARRIVE"                 => "ANKUNFT",
            "NON STOP"               => "OHNE ZWISCHENSTOPP",
            "Confirmed:"             => "Bestätigungsnummer:",
            "to"                     => "nach",
            "CHECK-IN"               => "NOTTRANLATED",
            "CHECK-OUT"              => "NOTTRANLATED",
            "PICK-UP"                => "NOTTRANLATED",
            "DROP-OFF"               => "NOTTRANLATED",
        ],
        "da" => [
            "Your Reservation Code:" => ["Din reservationskode:", "Reservationsnummer:"],
            "DEPART"                 => "AFGANG",
            "ARRIVE"                 => "ANKOMST",
            "NON STOP"               => "NOTTRANLATED",
            "Confirmed:"             => "Bekræftelsesnummer:",
            "to"                     => "til",
            "CHECK-IN"               => "CHECK-IN",
            "CHECK-OUT"              => "CHECK-OUT",
            "PICK-UP"                => "OPSAMLING",
            "DROP-OFF"               => "AFSÆTNING",
        ],
        "fr" => [ // it-6732893.eml
            "Your Reservation Code:" => "Votre numéro de réservation:",
            "DEPART"                 => "DÉPART",
            "ARRIVE"                 => "ARRIVÉE",
            "NON STOP"               => "AUCUN ARRÊT",
            "Confirmed:"             => "Numéro de confirmation:",
            "to"                     => "à",
            //"CHECK-IN" => "CHECK-IN",
            //"CHECK-OUT" => "CHECK-OUT",
            //"PICK-UP" => "OPSAMLING",
            //"DROP-OFF" => "AFSÆTNING",
        ],
        "ru" => [ // it-6787266.eml
            "Your Reservation Code:" => "Ваш код бронирования:",
            "DEPART"                 => "ОТПРАВЛЕНИЕ",
            "ARRIVE"                 => "ПРИБЫТИЕ",
            "NON STOP"               => "БЕЗ ОСТАНОВОК",
            "Confirmed:"             => "Номер утверждения:",
            "to"                     => "в",
            //"CHECK-IN" => "CHECK-IN",
            //"CHECK-OUT" => "CHECK-OUT",
            //"PICK-UP" => "OPSAMLING",
            //"DROP-OFF" => "AFSÆTNING",
        ],
        "it" => [
            "Your Reservation Code:" => ["Il Suo Codice di Prenotazione:", "Il vostro Codice di Prenotazione:"],
            "DEPART"                 => "PARTENZA",
            "ARRIVE"                 => "ARRIVO",
            "NON STOP"               => "NOTTRANLATED",
            "Confirmed:"             => "Numero di conferma:",
            "to"                     => ["per", "a"],
            //			"CHECK-IN" => "",
            //			"CHECK-OUT" => "",
            //			"PICK-UP" => "",
            //			"DROP-OFF" => "",
        ],
        "pt" => [ // it-8889244.eml
            "Your Reservation Code:" => "O seu código de reserva:",
            "DEPART"                 => "PARTIDA",
            "ARRIVE"                 => "CHEGADA",
            "NON STOP"               => "SEM ESCALA",
            "Confirmed:"             => ["Número de confirmação:", "Número da confirmação:"],
            "to"                     => ["para", 'até'],
            //			"CHECK-IN" => "",
            //			"CHECK-OUT" => "",
            //			"PICK-UP" => "",
            //			"DROP-OFF" => "",
        ],
        "es" => [ // it-9938074.eml
            "Your Reservation Code:" => ["Su código de reserva:", "Tu código de reserva:", "Número de reserva:"],
            "DEPART"                 => ["PARTIDA", "SALIDA"],
            "ARRIVE"                 => "LLEGADA",
            "NON STOP"               => ["SIN PARADA", "Sin paradas"],
            "Confirmed:"             => "Número de confirmación:",
            "to"                     => "a",
            //			"CHECK-IN" => "NOTTRANLATED",
            //			"CHECK-OUT" => "NOTTRANLATED",
            //			"PICK-UP" => "NOTTRANLATED",
            //			"DROP-OFF" => "NOTTRANLATED",
        ],
        "sv" => [
            "Your Reservation Code:" => ["Ditt bokningsnummer:", "Bokningsnummer:"],
            "DEPART"                 => "AVRESA",
            "ARRIVE"                 => "ANKOMST",
            "NON STOP"               => "Inga uppehåll",
            "Confirmed:"             => "Bekräftelsenummer:",
            "to"                     => "till",
            //			"CHECK-IN" => "CHECK-IN",
            //			"CHECK-OUT" => "CHECK-OUT",
            //			"PICK-UP" => "OPSAMLING",
            //			"DROP-OFF" => "AFSÆTNING",
        ],
        "ja" => [ // it-35627517.eml
            "Your Reservation Code:" => ["ご予約コード:", "ご予約コード"],
            "DEPART"                 => "出発",
            "ARRIVE"                 => "到着",
            "NON STOP"               => "NOTTRANLATED",
            "Confirmed:"             => ["確認番号:", "確認番号"],
            "to"                     => "→",
            'View Your Itinerary:'   => "お客様の旅程を表示:",
            //			"CHECK-IN" => "CHECK-IN",
            //			"CHECK-OUT" => "CHECK-OUT",
            //			"PICK-UP" => "OPSAMLING",
            //			"DROP-OFF" => "AFSÆTNING",
        ],
        "cs" => [
            "Your Reservation Code:" => "Váš rezervační kód:",
            "DEPART"                 => "ODLET",
            "ARRIVE"                 => "PŘÍLET",
            "NON STOP"               => "PŘÍMÝ SPOJ",
            //            "Confirmed:" => "",
            "to" => "-",
            //			"CHECK-IN" => "CHECK-IN",
            //			"CHECK-OUT" => "CHECK-OUT",
            //			"PICK-UP" => "OPSAMLING",
            //			"DROP-OFF" => "AFSÆTNING",
        ],
    ];

    public $lang = "";
    public $traveller;
    public $pdf;
    public $date;
    public $otaConf = [];

    public function parseHtml(Email $email)
    {
        $tripNum = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Your Reservation Code:')) . "]/ancestor::td[1]", null, true, "/:\s*([A-Z\d]{5,})/");
        //##################
        //##   FLIGHTS   ###
        //##################
        $xpath = "//text()[" . $this->eq($this->t("DEPART")) . "]/ancestor::tr[" . $this->contains($this->t("ARRIVE")) . "][1]/..";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode("./preceding::table[1]//text()[" . $this->starts($this->t("Confirmed:")) . "]/ancestor::*[contains(.,':')][1]", $root, true, "#(?:" . implode("|", (array) $this->t("Confirmed:")) . ")\s*(\w+)#")) {
                $airs[$rl][] = $root;
            } else {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            if (!empty($rl)) {
                $f->general()
                    ->confirmation($rl);
            } else {
                $f->general()
                    ->noConfirmation();
            }

            if (!empty($this->traveller)) {
                $f->general()
                    ->traveller($this->traveller);
            }

            if (!empty($tripNum)) {
                $this->otaConf[] = $tripNum;
            }

            foreach ($roots as $root) {
                // SUN, OCT 11 - London (LHR) to Denver (DEN)
                // Ven. 01 Sept. - Sam. 02 Sept. - London (LHR) à Bangkok (BKK) - Confirmé
                // 2019年 04月 05日 (金曜日) - 2019年 04月 06日 (土曜日) - チューリッヒ (ZRH) → 東京 (NRT) - 予約済
                $sTitle = [];
                $sTitleValue = $this->http->FindSingleNode("preceding::table[2]/descendant::tr[normalize-space()][1]", $root);

                // for some emails like it-35627517.eml
                $date = $this->http->FindSingleNode("./preceding::table[2]/descendant::td[normalize-space(.)!=''][1]", $root, true, "/.*\d{4}.*/");

                if (preg_match("#^(\w+\,\s*\w+\s*\d+\,\s*\d{4})\s*\-\D+\([A-Z]{3}\)#", $date, $m)) {
                    $date = strtotime($this->normalizeDate($m[1]));
                    $arrDate = '';
                } elseif (preg_match("#(.*)\s+-\s+(.*)#", $date, $m)) {
                    $date = strtotime($this->normalizeDate($m[1]));
                    $arrDate = strtotime($this->normalizeDate($m[2]));
                } else {
                    $date = strtotime($this->normalizeDate($date));
                    $arrDate = '';
                }
                // for others
                if (empty($date)) {
                    $date = $this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "/.*\d{4}.*/");

                    if (preg_match("#(.*)\s+-\s+(.*)#", $date, $m)) {
                        $date = strtotime($this->normalizeDate($m[1]));
                        $arrDate = strtotime($this->normalizeDate($m[2]));
                    } else {
                        $date = strtotime($this->normalizeDate($date));
                        $arrDate = '';
                    }
                }

                $dateXpath = "./preceding::table[2]/";

                if (empty($date)) {
                    $date = $this->http->FindSingleNode("./preceding::table[1]/preceding::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                        $root, true, "/^\D*\d+\D*\d+\D*$/");

                    if (preg_match("#(.*)\s+-\s+(.*)#", $date, $m)) {
                        $date = strtotime($this->normalizeDate($m[1]));
                        $arrDate = strtotime($this->normalizeDate($m[2]));
                    } else {
                        $date = strtotime($this->normalizeDate($date));
                        $arrDate = '';
                    }

                    if (!empty($date)) {
                        $dateXpath = "./preceding::table[1]/preceding::tr[normalize-space()][1]/";
                    }
                }

                if (preg_match("/.+\([ ]*([A-Z]{3})[ ]*\)\s*{$this->opt($this->t("to"))}\s*.+?\([ ]*([A-Z]{3})[ ]*\)/", $sTitleValue, $m)) {
                    if ($m[2] == 'ZZF') { //always bad segment
                        continue;
                    }
                    $sTitle['depCode'] = $m[1];
                    $sTitle['arrCode'] = $m[2];
                }

                $s = $f->addSegment();

                if (count($this->http->FindNodes("./tr", $root)) == 1) {
                    // header & content in one cell (it-3110459.eml, it-35627517.eml, it-5537131.eml, it-5567302.eml, it-6732893.eml, it-6787266.eml, it-7480934.eml, it-8889244.eml, it-9938074.eml)
                    $this->logger->debug('Found flight segment: type 1');

                    $root = $this->http->XPath->query("./tr[1]", $root)->item(0);
                    // FlightNumber
                    // AirlineName
                    $airlineName = str_replace('.', '', $this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space(.)!=''][1]/ancestor::*[1]", $root, true, "#^(.*?)\s+\d+$#"));

                    if (empty($airlineName)) {
                        $s->airline()
                            ->noName();
                    } else {
                        $s->airline()
                            ->name($airlineName);
                    }

                    $s->airline()
                        ->number($this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space(.)!=''][1]/ancestor::*[1]", $root, true, "#\s*(\d+)#"));

                    // DepCode
                    $codeDep = $this->http->FindSingleNode('td[2]/descendant::text()[normalize-space()][last()]', $root, true, '/^[A-Z]{3}$/');

                    if (empty($codeDep) && !empty($sTitle['depCode'])) {
                        $codeDep = $sTitle['depCode'];
                    }
                    $s->departure()
                        ->code($codeDep);

                    // DepName
                    $depName = $this->http->FindSingleNode($dateXpath . "descendant::text()[normalize-space(.)][3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)\s+{$this->opt($this->t("to"))}\s+.*?\s+\([A-Z]{3}\)#u");

                    if (empty($depName)) {
                        $depName = $this->http->FindSingleNode($dateXpath . "descendant::text()[normalize-space(.)][3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#u");
                    }

                    if (empty($depName)) {
                        $depName = $this->http->FindSingleNode($dateXpath . "descendant::text()[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','11111111111111111111111111'),'(111)')][1]/preceding::text()[normalize-space()!=''][1]", $root);

                        if (preg_match("/^\s*\-\s*/", $depName, $m)) {
                            $depName = $this->http->FindSingleNode($dateXpath . "descendant::text()[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','11111111111111111111111111'),'(111)')][1]", $root);
                        }
                    }

                    $s->departure()
                        ->name($depName);

                    // DepDate
                    $depTime = implode(" ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()>1 and position()<last()]", $root));
                    $depTime = str_replace(["du.", "de."], ['PM', 'AM'], trim($depTime));
                    $s->departure()
                        ->date(strtotime($this->normalizeTime($depTime), $date));

                    // ArrCode
                    $codeArr = $this->http->FindSingleNode('td[4]/descendant::text()[normalize-space()][last()]', $root, true, '/^[A-Z]{3}$/');

                    if (empty($codeArr) && !empty($sTitle['arrCode'])) {
                        $codeArr = $sTitle['arrCode'];
                    }
                    $s->arrival()
                        ->code($codeArr);

                    // ArrName
                    $arrName = $this->http->FindSingleNode($dateXpath . "descendant::text()[normalize-space(.)][3]", $root, true, "#.*?\s+\([A-Z]{3}\)\s+{$this->opt($this->t("to"))}\s+(.*?)\s+\([A-Z]{3}\)#u");

                    if (empty($arrName)) {
                        $arrName = $this->http->FindSingleNode($dateXpath . "descendant::text()[normalize-space(.)][4]", $root, true, "#(?:" . $this->t("to") . ")?\s*(.*?)\s+\([A-Z]{3}\)#u");
                    }

                    if (empty($arrName)) {
                        $arrName = $this->http->FindSingleNode($dateXpath . "descendant::text()[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','11111111111111111111111111'),'(111)')][2]/preceding::text()[normalize-space()!=''][1]", $root);
                    }

                    $s->arrival()
                        ->name($arrName);

                    // ArrDate
                    $arrTime = implode(" ", $this->http->FindNodes("./td[4]/descendant::text()[normalize-space(.)][position()>1 and position()<last()]", $root));
                    $arrTime = str_replace(["du.", "de."], ['PM', 'AM'], $arrTime);

                    if (!empty($arrDate)) {
                        $s->arrival()
                            ->date(strtotime($this->normalizeTime($arrTime), $arrDate));
                    } else {
                        $s->arrival()
                            ->date(strtotime($this->normalizeTime($arrTime), $date));
                    }

                    // Duration
                    $duration = $this->http->FindSingleNode("td[3]/descendant::*/tr[3]", $root, true, '/^\d.+/');

                    if (!empty($duration)) {
                        $s->extra()
                            ->duration($duration);
                    }

                    // Stops
                    $stops = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root);

                    if (in_array($stops, (array) $this->t('NON STOP'))) {
                        $s->extra()
                            ->stops(0);
                    } elseif (preg_match('/^(\d{1,3})\b/', $stops, $matches)) {
                        $s->extra()
                            ->stops($matches[1]);
                    }
                } else {
                    // header separately (it-2162667.eml, it-2163394.eml)
                    $this->logger->debug('Found flight segment: type 2');

                    $root = $this->http->XPath->query("./tr[2]", $root)->item(0);
                    $s->airline()
                        ->name($this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(.*?)\s+\d+$#"))
                        ->number($this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#\s+(\d+)$#"));

                    // DepCode
                    $s->departure()
                        ->code($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^([A-Z]{3})$#"))
                        ->name($this->http->FindSingleNode($dateXpath . "descendant::text()[normalize-space(.)][3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)\s+" . $this->t("to") . "\s+.*?\s+\([A-Z]{3}\)#"))
                        ->date(strtotime(implode(" ", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)][position()<last()]", $root)), $date));

                    $s->arrival()
                        ->code($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^([A-Z]{3})$#"))
                        ->name($this->http->FindSingleNode($dateXpath . "descendant::text()[normalize-space(.)][3]", $root, true, "#.*?\s+\([A-Z]{3}\)\s+" . $this->t("to") . "\s+(.*?)\s+\([A-Z]{3}\)#"))
                        ->date(strtotime(implode(" ", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)][position()<last()]", $root)), $date));

                    // Duration
                    $duration = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root);

                    if (!empty($duration)) {
                        $s->extra()
                            ->duration($duration);
                    }

                    // Stops
                    $stops = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][2]", $root);

                    if (in_array($stops, (array) $this->t('NON STOP'))) {
                        $s->extra()
                            ->stops(0);
                    } elseif (preg_match('/^(\d{1,3})\b/', $stops, $matches)) {
                        $s->extra()
                            ->stops($matches[1]);
                    }
                }
            }
        }

        //#################
        //##   HOTELS   ###
        //#################

        $xpath = "//text()[" . $this->eq($this->t("CHECK-IN")) . "]/ancestor::tr[" . $this->contains($this->t("CHECK-OUT")) . "][1]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            if (!empty($this->traveller)) {
                $h->general()
                    ->traveller($this->traveller);
            }

            // ConfirmationNumber
            $confNumber = $this->http->FindSingleNode("./preceding::table[1]//text()[" . $this->starts($this->t("Confirmed:")) . "]", $root, true, "#(?:" . implode("|", (array) $this->t("Confirmed:")) . ")\s*(\w+)#");

            if (!empty($confNumber)) {
                $h->general()
                    ->confirmation($confNumber);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space(.)][1]", $root))
                ->noAddress();

            // CheckInDate
            $checkIn = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+-\s+#") . ', ' . implode(" ", $this->http->FindNodes("./td[2]/descendant::text()[string-length(normalize-space(.))>1][position()>1]", $root))));

            $checkOut = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\s+-\s+(.+)#") . ', ' . implode(" ", $this->http->FindNodes("./td[4]/descendant::text()[string-length(normalize-space(.))>1][position()>1]", $root))));

            if (empty($checkOut)) {
                $checkOut = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\s+-\s+(.+\d{4})\s*\D+\(#") . ', ' . implode(" ", $this->http->FindNodes("./td[4]/descendant::text()[string-length(normalize-space(.))>1][position()>1]", $root))));
            }

            $h->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut);

            $accounts = array_filter(array_unique($this->http->FindNodes("//text()[contains(normalize-space(), 'CLUB WYNDHAM TRAVEL')]", null, "/\s(\d+)\-/")));

            if (count($accounts) > 0) {
                $h->setAccountNumbers($accounts, false);
            }
        }

        //###############
        //##   CARS   ###
        //###############

        $xpath = "//text()[" . $this->eq($this->t("PICK-UP")) . "]/ancestor::tr[" . $this->contains($this->t("DROP-OFF")) . "][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            if (!empty($this->traveller)) {
                $r->general()
                    ->traveller($this->traveller);
            }

            $r->general()
                ->confirmation($this->http->FindSingleNode("./preceding::table[1]//text()[" . $this->starts($this->t("Confirmed:")) . "]", $root, true, "#(?:" . implode("|", (array) $this->t("Confirmed:")) . ")\s*(\w+)#"));

            // PickupLocation
            if (!$pickUpLocation = $this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][3]", $root, true, "#(.*?)\s+" . $this->t("to") . "\s+#")) {
                $pickUpLocation = $this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][3]", $root);
            }

            $r->pickup()
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+-\s+#") . ', ' . implode(" ", $this->http->FindNodes("./td[2]/descendant::text()[string-length(normalize-space(.))>1][position()>1]", $root)))))
                ->location($pickUpLocation);

            // DropoffLocation
            if (!$dropoffLocation = $this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][3]", $root, true, "#\s+" . $this->t("to") . "\s+(.+)#")) {
                $dropoffLocation = $this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][3]", $root);
            }

            $r->dropoff()
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\s+-\s+(.+)#") . ', ' . implode(" ", $this->http->FindNodes("./td[4]/descendant::text()[string-length(normalize-space(.))>1][position()>1]", $root)))))
                ->location($dropoffLocation);

            $r->setCompany($this->http->FindSingleNode("./preceding::table[1]/descendant::text()[normalize-space(.)][1]", $root));
        }

        foreach (array_unique($this->otaConf) as $otaConf) {
            $email->ota()
                ->confirmation($otaConf);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]travelport\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (strpos($this->http->Response['body'], 'Travelport') === false) {
            return false;
        }

        if ($this->detectPdf($parser)) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//*[contains(normalize-space(),'{$re}')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (preg_match("/{$this->opt($this->t('View Your Itinerary:'))}\s*(\D+)\s*\-/", $parser->getSubject(), $m)) {
            $this->traveller = preg_replace("/(?:MRS\s*$|MS\s*$|MR\s*$)/", "", $m[1]);
        }

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[contains(normalize-space(),'{$re}')]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return [];
        }

        if ($this->detectPdf($parser)) {
            $this->logger->debug('go to parse by ItineraryPdf2017 or MyTripPdf');

            return [];
        }

        $this->parseHtml($email);

        //		$pdfs = $parser->searchAttachmentByName('Itinerary.*pdf');
//        if (count($pdfs) > 0) {
        //			$body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        //			$nbsp = chr(194) . chr(160);
        //			$body = str_replace([$nbsp, '&#160;'], [' ', ' '], $body);
        //			$result = [];
        //			$this->pdf = clone $this->http;
        //			$this->pdf->SetEmailBody($body);
        //			$Pdfresult = $this->ParsePdf();
//
        //			foreach ($itineraries as $key => $itinerary) {
        //				if ($itinerary['RecordLocator'] == $Pdfresult['RecordLocator'] && $itinerary['Kind'] == "T") {
        //					$itineraries[$key]["Passengers"] = $Pdfresult["Passengers"];
        //					foreach ($itinerary['TripSegments'] as $i => $segment) {
        //						foreach ($Pdfresult['TripSegments'] as $j => $segmentPdf) {
        //							if ($segment['FlightNumber'] == $segmentPdf['FlightNumber'] && $segment['DepCode'] == $segmentPdf['DepCode'] && $segment['DepDate'] == $segmentPdf['DepDate']){
        //								$itineraries[$key]['TripSegments'][$i] = array_merge($itineraries[$key]['TripSegments'][$i],$Pdfresult['TripSegments'][$j]);
        //							}
        //						}
        //					}
        //				}
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) + 1;
    }

    protected function ParsePdf()
    {
        $segments = $this->splitText("#([A-Z]{3}\s*\d+,\s*\d{4}\s*-\s*[^(]+\([A-Z]{3}\)\s+to\s+[^(]+\([A-Z]{3}\))#", $this->pdf->Response['body']);
        $results = [];

        if (preg_match("#Your Reservation Code:\s*([\w]{5,6})\s+#", $this->pdf->Response['body'], $m)) {
            $results['RecordLocator'] = $m[1];
        }

        foreach ($segments as $key => $segmentText) {
            $result = [];
            unset($depDate);
            unset($arrDate);

            if (preg_match("#([A-Z]{3}\s*\d+,\s*\d{4})(?:\s*-\s*[A-Z]{3},\s*([A-Z]{3}\s*\d+,\s*\d{4}))?\s*-\s*([^(]+)\(([A-Z]{3})\)\s+to\s+([^(]+)\(([A-Z]{3})\)#", $segmentText, $m)) {
                $depDate = $this->normalizeDate($m[1]);

                if ($m[6] == 'ZZF') { //always bad segment
                    continue;
                }

                if (!empty($m[2])) {
                    $arrDate = $this->normalizeDate($m[2]);
                }
                // DepName
                $result['DepName'] = trim($m[3]);
                // DepCode
                $result['DepCode'] = $m[4];
                // ArrName
                $result['ArrName'] = trim($m[5]);
                // ArrCode
                $result['ArrCode'] = $m[6];
            }

            if (preg_match("#ARRIVE\s+[^(]+\(([A-Z][A-Z\d]|[A-Z\d][A-Z])\)\s+(\d{1,5})\s+\D*\s*(\d{1,2}:\d{2})\s+[\w\s]+\D*\s*(\d{1,2}:\d{2})\s+([AP]M)\s+([AP]M)\s+[A-Z]{3}\s+[A-Z]{3}\s+([\w ]+)#", $segmentText, $m)) {
                // AirlineName
                $result['AirlineName'] = $m[1];
                // FlightNumber
                $result['FlightNumber'] = $m[2];
                // DepDate
                // ArrDate
                if (!empty($depDate)) {
                    $result['DepDate'] = strtotime($depDate . ' ' . $m[3] . $m[5]);

                    if (!empty($arrDate)) {
                        $result['ArrDate'] = strtotime($arrDate . ' ' . $m[4] . $m[6]);
                    } else {
                        $result['ArrDate'] = strtotime($depDate . ' ' . $m[4] . $m[6]);
                    }
                }
                // Duration
                $result['Duration'] = $m[7];
            }

            if (preg_match("#Name\s+Special Services\s+((?:([A-Z,[:space:]]+)[[:space:]]*.{0,5}\n)+)#", $segmentText, $m)) {
                $passengers = explode("\n", $m[1]);

                foreach ($passengers as $str) {
                    $results['Passengers'][] = trim(preg_replace("#[^A-Z\s]#", "", $str));
                }
            }

            if (preg_match("#Class Of Service:\s*(.*)#", $segmentText, $m)) {
                $result['Cabin'] = $m[1];
            }

            if (preg_match("#AIRPORT INFO\s+([^(]+)\(\w+\)(.*\n)+(?:\s*Terminal\s*(\w+))?\s+to\s+([^(]+)\(\w+\)(.*\n)+(?:\s*Terminal\s*(\w+))?\s+FLIGHT INFO#U", $segmentText, $m)) {
                $result['DepName'] = trim($m[1]) . ', ' . trim($m[2]);

                if (!empty($m[3])) {
                    $result['DepartureTerminal'] = $m[3];
                }
                $result['ArrName'] = trim($m[4]) . ', ' . trim($m[5]);

                if (!empty($m[6])) {
                    $result['ArrivalTerminal'] = $m[6];
                }
            }
            $results['TripSegments'][] = $result;
        }
        $results['Passengers'] = array_diff($results['Passengers'], [null, '']);
        $results['Passengers'] = array_unique($results['Passengers']);

        return $results;
    }

    private function detectPdf(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('(ElectronicTicket|Itinerary).*?\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            foreach (\AwardWallet\Engine\tport\Email\ItineraryPdf2017::$detect as $phrase) {
                foreach ($phrase as $ph) {
                    if (strpos($textPdf, $ph) !== false) {
                        return true; // go to ItineraryPdf2017
                    }
                }
            }

            foreach (\AwardWallet\Engine\tport\Email\MyTripPdf::$langDetectors as $phrases) {
                foreach ($phrases as $phrase) {
                    if (strpos($textPdf, $phrase) !== false) {
                        return true; // go to MyTripPdf
                    }
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //$this->logger->info("DATE: {$str}");
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+?)\.?\s+(\d+)\s+-\s+[^\d\s]+,\s+[^\d\s]+\s+\d+$#", // SUN, OCT 11 - SUN, OCT 11
            "#^[^\d\s]+,\s+([^\d\s]+?)\.?\s+(\d+)$#", // SUN, OCT 11
            "#^[^\d\s]+,\s+([^\d\s]+?)\.?\s+(\d+),\s+(\d+:\d+)$#", // SUN, OCT 11, 12:00
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+?)\.?$#", // Mon 23 Jan
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+?)\.?\s+-\s+[^\d\s]+\s+\d+\s+[^\d\s]+$#", // Mon 23 Jan - Tue 24 Jan
            "#^[^\d\s]+,\s+([^\d\s]+?)\.?\s+(\d+),\s+(\d+:\d+\s+[AP]M)$#", // Tue, Mar 07, 8:00 AM
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+?)\.?,\s+(\d+:\d+)$#", // Lø 01 Apr, 15:00
            '/^\w+\s+(\d{1,2})\s+(\d{1,2})$/u', // ST 30 3
            '/^\w+\s+(\d{1,2})\s+(\d{1,2})\s+-\s+\w+\s+\d{1,2}\s+\d{1,2}$/u', // ST 30 3 - ŠT 31 3
            '/(\d{2,4})\w+\s+(\d{1,2})\w+\s+(\d{1,2})\w+\s+.+/u',
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s\.]+?)[. ]+(\d{4})\s*$#", // Mar 12 Juin 2018
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s\.]+?)[. ]+(\d{4}),\s+(\d+:\d+)\s*$#", // Qua 11 Jul 2018, 15:00
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})$#", //Wed, Oct 05, 2022
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $year",
            "$2 $1 $year, $3",
            "$1 $2 $year",
            "$1 $2 $year",
            "$2 $1 $year, $3",
            "$1 $2 $year, $3",
            "$2/$1/$year",
            "$2/$1/$year",
            '$3.$2.$1',
            '$1 $2 $3',
            '$1 $2 $3, $4',
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->info("DATE: {$str}");

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }

            if (empty($en)) {
                if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], "hu")) {
                    $str = str_replace($m[1], $en, $str);
                }
            }
        }

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match("/^(?<hrs>\d+)\:(?<min>\d+)\s*(?<typeTime>A?P?M)$/", $time, $m)) {
            if ($m[1] > 12) {
                $time = $m['hrs'] . ':' . $m['min'];
            }
        }

        return $time;
    }
}
