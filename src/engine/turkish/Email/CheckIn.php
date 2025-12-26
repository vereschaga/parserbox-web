<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// maybe it's better rewrite, change logic
class CheckIn extends \TAccountChecker
{
    public $mailFiles = "turkish/it-10626529.eml, turkish/it-114087040.eml, turkish/it-12053084.eml, turkish/it-141726705-pt.eml, turkish/it-148676665.eml, turkish/it-19157696.eml, turkish/it-26916523.eml, turkish/it-28314245.eml, turkish/it-29140191.eml, turkish/it-34993798.eml, turkish/it-35140947.eml, turkish/it-35168888.eml, turkish/it-35192651.eml, turkish/it-35193200.eml, turkish/it-35195804.eml, turkish/it-39084295.eml, turkish/it-39114350.eml, turkish/it-5717432.eml, turkish/it-6123316.eml, turkish/it-8680852.eml";

    public $lang = "en";
    private $reFrom = "@thy.com";
    private $reSubject = [
        "en" => "Turkish Airlines - Online Ticket - Information Message",
        "es" => "Turkish Airlines - Billete en línea - Mensaje de información",
        "pt" => "Turkish Airlines - Bilhete online - mensagem informativa",
        "it" => "Turkish Airlines - biglietto on line. Messaggio informativo",
        "de" => "Turkish Airlines - Online Ticket - Informationsanzeige",
        "fr" => "Turkish Airlines - Billet en ligne - Message d'information",
        "tr" => "Turkish Airlines - Online Bilet - Bilgi Mesaji",
        "ru" => "Turkish Airlines - Электронный билет - Информационное сообщение",
        "zh" => "Turkish Airlines - 网上机票-基本信息",
    ];
    private $reBody = 'Turkish Airlines';
    private $reBody2 = [
        "en"  => "OUTBOUND TRIP",
        "en2" => "OUTBOUND FLIGHT",
        "fr"  => "TRAJET ALLER",
        "es"  => "VIAJE DE IDA",
        "pt"  => "VIAGEM DE IDA",
        "de"  => "HINREISE",
        "it"  => "VIAGGIO DI ANDATA",
        "tr"  => "GİDİŞ",
        "ru"  => "РЕЙС ВЫЛЕТА",
        "zh"  => "去程",
    ];

    private static $dictionary = [
        "en" => [
            "Check-in complete. Have a good trip." => [
                "Check-in complete. Have a good trip.",
                "Your seat selection has been completed.",
            ],
            // "Reservation Code" => "",
            "passengersByBaggage" => "Passengers",
            "OUTBOUND TRIP"       => ["INBOUND TRIP", "OUTBOUND TRIP", 'OUTBOUND FLIGHT'],
            "Seat:"               => ["Seat:", "Standard seat:"],
        ],
        "fr" => [
            "Check-in complete. Have a good trip." => [
                "Enregistrement terminé. Nous vous souhaitons un agréable voyage !",
                "Votre sélection de siège est terminée.",
                "Your seat selection has been completed.",
            ],
            // "Reservation Code" => "",
            "PASSENGER:"          => "PASSAGER :",
            "passengersByBaggage" => "Passagers",
            "OUTBOUND TRIP"       => ["TRAJET ALLER", "TRAJET RETOUR"],
            "Seat:"               => ["Siège :", "Siège standard:"],
            " on "                => " le ",
        ],
        "es" => [
            "Check-in complete. Have a good trip." => [
                "Check-in finalizado. Le deseamos un buen vuelo.",
                "Your seat selection has been completed.",
                "Su selección de asiento ha sido completada.",
                "Se ha completado la selección de asiento.",
            ],
            // "Reservation Code" => "",
            "PASSENGER:"    => "PASAJERO:",
            // "passengersByBaggage" => "",
            "OUTBOUND TRIP" => ["VIAJE DE IDA", "VIAJE DE VUELTA"],
            "Seat:"         => "Asiento:",
            " on "          => [" el ", " en "],
        ],
        "pt" => [
            "Check-in complete. Have a good trip." => [
                "Sua seleção de assentos foi concluída.",
            ],
            "Reservation Code"    => "Código de reserva",
            "PASSENGER:"          => "PASSAGEIRO:",
            "passengersByBaggage" => "Passageiros",
            "OUTBOUND TRIP"       => ["VIAGEM DE IDA"],
            "Seat:"               => "Lugar:",
            " on "                => [" na ", " em "],
        ],
        "de" => [
            "Check-in complete. Have a good trip." => [
                "Your seat selection has been completed.",
                "Ihre Sitzplatzauswahl ist abgeschlossen.",
                "Ihre Sitzplatzauswahl wurde abgeschlossen.",
            ],
            "Reservation Code"    => "Reservierungscode",
            "PASSENGER:"          => "PASSAGIER:",
            "passengersByBaggage" => "Passagiere",
            "OUTBOUND TRIP"       => ["HINREISE", "RÜCKREISE"],
            "Seat:"               => "Sitzplatz:",
            " on "                => " am ",
        ],
        "tr" => [
            "Check-in complete. Have a good trip." => [
                "Koltuk seçiminiz tamamlandı. Ancak!",
            ],
            "Reservation Code"    => "Rezervasyon Kodu",
            "PASSENGER:"          => "YOLCU:",
            "passengersByBaggage" => "Yolcu",
            "OUTBOUND TRIP"       => ["GİDİŞ", "DÖNÜŞ"],
            "Seat:"               => "Koltuk:",
            " on "                => ", ",
        ],
        "it" => [
            "Check-in complete. Have a good trip." => [
                "Your seat selection has been completed.",
                "La tua selezione del posto è stata completata.",
                "Check-in completato. Buon viaggio.",
            ],
            "Reservation Code"    => "Codice di prenotazione",
            "PASSENGER:"          => "PASSEGGERO:",
            "passengersByBaggage" => "Passeggeri",
            "OUTBOUND TRIP"       => ["VIAGGIO DI ANDATA", "VIAGGIO DI RITORNO"],
            "Seat:"               => "Posto a sedere:",
            " on "                => [" il giorno ", " in data "],
        ],
        "ru" => [
            "Check-in complete. Have a good trip." => [
                "Выбор места подтвержден.",
            ],
            "Reservation Code" => "Код бронирования",
            "PASSENGER:"       => "ПАССАЖИР:",
            // "passengersByBaggage" => "",
            "OUTBOUND TRIP" => ["РЕЙС ВЫЛЕТА"],
            "Seat:"         => ["Место:", "Стандартное место:"],
            " on "          => ": ",
        ],
        "zh" => [
            "Check-in complete. Have a good trip." => [
                "请不要忘记创建登机牌！",
            ],
            "Reservation Code"    => "预订代码",
            "PASSENGER:"          => "乘客：",
            "passengersByBaggage" => "乘客",
            "OUTBOUND TRIP"       => ["去程"],
            "Seat:"               => ["座位："],
            " on "                => " 上的 ",
        ],
    ];
    private $date = null;
    private $justFirstBP;

    /** @var \PlancakeEmailParser */
    private $parser;

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parser = $parser;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($email);

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
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $f = $email->add()->flight();

        // RecordLocator
        $confNumber = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in complete. Have a good trip.")) . "]/ancestor::td[1]/following-sibling::td[1]", null, true, "/([A-Z\d]{6,})/");

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("(//a[contains(@href, 'http://www.turkishairlines.com') and contains(@href, 'flights/manage-booking/index.html')])[1]/@href", null, true, "#\?pnr=([A-Z\d]{5,7})\W#");
        }

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("(//a[contains(@href, 'http://www.turkishairlines.com') and contains(@href, 'flights/manage-booking/index.html')])[1]/@href", null, true, "#\?pnr=([A-Z\d]{5,7})\W#");
        }

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Code'))}]/ancestor::table[1]", null, true, "#^([A-Z\d]{5,7})\s*{$this->opt($this->t('Reservation Code'))}#su");
        }

        $f->general()
            ->confirmation($confNumber);

        // Passengers
        $pax = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("PASSENGER:")) . "]/ancestor::tr[1]/following-sibling::tr[not(" . $this->contains($this->t("PASSENGER:")) . ")]/td[2]/descendant::text()[normalize-space(.)][1]", null, "/^(\D+)$/")));

        if (count($pax) === 0) {
            $pax = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("PASSENGER:")) . "]/ancestor::tr[1]/following-sibling::tr[not(" . $this->contains($this->t("PASSENGER:")) . ")]/td[1]", null, "/^\s*[A-Z]{2}\s*(\D+)\s+\(/")));
        }

        if (count($pax) == 0) {
            $pax = $this->http->FindNodes("//tr[ *[normalize-space()][1][{$this->eq($this->t("passengersByBaggage"))}] and *[normalize-space()][2] ]/following-sibling::tr/*[normalize-space()][1]/descendant::tr/*[not(.//tr) and normalize-space()][2]", null, "/^(?:titlelookup[.\s]*)?({$patterns['travellerName']})(?:\s*\(|$)/u");
        }

        $f->general()
            ->travellers(preg_replace("/^(?:Mr|Mrs|Mr|Ms|Mr|Ms|先生|Bay|Г-жа|Г-н|Herr)[.\s]+(.{2,})$/iu", '$1', $pax));

        $imgPath = "img[ancestor::td[1][count(.//text()[contains(translate(normalize-space(),'0123456789', 'dddddddddd'),'dd:dd')])=2]]";
        $this->logger->debug("[imgPath]: " . $imgPath);

        $xpathSegments = "//text()[" . $this->starts($this->t("Seat:")) . "]/preceding::text()[normalize-space(.)][1]/ancestor::*[" . $this->contains($this->t("PASSENGER:")) . "][1]/ancestor-or-self::*[local-name() = 'table' or local-name() = 'div'][1]";
        $segments = $this->http->XPath->query($xpathSegments);

        if ($segments->length === 0) {
            $xpathSegments = "//text()[" . $this->starts($this->t("Check-in status")) . "]/preceding::text()[normalize-space(.)][1]/ancestor::*[" . $this->contains($this->t("PASSENGER:")) . "][1]/ancestor-or-self::*[local-name() = 'table' or local-name() = 'div'][1]";
            $segments = $this->http->XPath->query($xpathSegments);
        }
        $this->logger->debug("[XPATH]: " . $xpathSegments);

        $segNumber = 0;

        foreach ($segments as $root) {
            $flightsNumbers = $this->http->FindNodes("(.//text()[" . $this->starts($this->t("Seat:")) . "])[1]/ancestor::td[1]/descendant::text()[" . $this->starts($this->t("Seat:")) . "]/preceding::text()[normalize-space(.)][1]",
                $root, "#^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$#");

            if (empty($flightsNumbers)) {
                $flightsNodes = $this->http->FindNodes("(./descendant::tr/descendant::td[count(./div)=2])[1]/following-sibling::td[1]/*[not(self::br)]",
                    $root);

                if (count($flightsNodes) % 2 !== 0) {
                    $this->logger->debug('other format');

                    return $email;
                }
                $flightsNumbers = [];

                foreach ($flightsNodes as $key => $value) {
                    if ($key % 2 == 0) {
                        $flightsNumbers[] = $value;
                    }
                }
            }

            foreach ($flightsNumbers as $key => $flight) {
                if ($segments->length === $this->http->XPath->query("//text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "]")->length) {
                    $position = '1';

                    if ($segments->length === 1) {
                        $positionNorm = "1"; // it-29140191.eml;
                    } else {
                        $positionNorm = "last()-{$key}"; // it-35140947.eml
                    }
                } elseif ($segments->length === 1) {
                    $position = "last()-{$key}";
                    $positionNorm = $key + 1;

                    if (count($flightsNumbers) > $this->http->XPath->query("//text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "]")->length) { // it-39114350.eml
                        if ($key + 1 > $this->http->XPath->query("//text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "]")->length) {
                            // skip flights
                            $this->justFirstBP = true;

                            continue;
                        }
                        $setEmptyArrival = true; // for junk
                    }
                } elseif ($segments->length > 1 && count(array_unique($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Seat:'))}][1]/preceding::text()[normalize-space()][1]", $root))) === 1) {
                    //114087040
                    $position = "last()-{$key}";
                    $positionNorm = $key + 1;
                    $this->justFirstBP = true;

                    if ($segNumber > 0) {
                        continue;
                    }

                    $segNumber++;
                } else {
                    $this->logger->debug('other format - other logic');

                    return $email;
                }
                $date = $this->normalizeDate($this->re("#" . $this->opt($this->t(" on ")) . "(.+)#",
                    $this->http->FindSingleNode("(./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][{$position}])[1]/following::text()[normalize-space(.)][1]",
                        $root)));

                if (empty($date) && $this->lang == 'ru') {
                    $date = $this->normalizeDate($this->re("#.+-.+ (\d+\s+\w+\s+\d{4})\s*г\.\s*$#u",
                        $this->http->FindSingleNode("(./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][{$position}])[1]/following::text()[normalize-space(.)][1]",
                            $root)));
                }

                if (empty($date) && $this->lang == 'ru') {
                    // Москва - Стамбул четверг 02 января
                    $date = $this->normalizeDate($this->re("#.+-.+ ([[:alpha:]]+\s+\d{1,2}+\s+[[:alpha:]]+)\s*$#u",
                        $this->http->FindSingleNode("(./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][{$position}])[1]/following::text()[normalize-space(.)][1]",
                            $root)));
                }

                if ($this->lang == 'zh') {
                    // 星期日 12 一月 上的 巴塞罗那 至 利雅得
                    $date = $this->normalizeDate($this->re("#^(.+)" . $this->opt($this->t(" on ")) . ".+ 至 .+#u",
                        $this->http->FindSingleNode("(./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][{$position}])[1]/following::text()[normalize-space(.)][1]", $root)));
                }

                if (empty($flight)) {
                    $seats = [];
                    $s = $f->addSegment();

                    if ($this->http->XPath->query("//a[contains(@href,'https://www.turkishairlines.com/') and contains(@href,'/flights/manage-booking?')]")->length > 0) {
                        $s->airline()
                            ->name('TK');
                    } else {
                        $s->airline()
                            ->noName();
                    }
                    $s->airline()
                        ->noNumber();
                    // Seats
                    $paxNodes = $this->http->XPath->query("(./descendant::tr/descendant::td[count(./div)=2])", $root);

                    foreach ($paxNodes as $rootPax) {
                        $nodes = $this->http->FindNodes("./following-sibling::td[1]/*[not(self::br)][normalize-space()!='']",
                            $rootPax);
                        $seatsPax = [];

                        if (preg_match("#:\s*(\d+[A-z])$#", $nodes[0]) || preg_match("#^(\d+[A-z])$#", $nodes[0])) {
                            foreach ($nodes as $k => $value) {
                                $seatsPax[] = $this->re("#\b(\d+[A-z])$#", $value);
                            }
                        } else {
                            foreach ($nodes as $k => $value) {
                                if ($k % 2 !== 0) {
                                    $seatsPax[] = $value;
                                }
                            }
                        }

                        if (isset($seatsPax[$key]) && preg_match("#^\d+[A-Z]$#", $seatsPax[$key])) {
                            $s->extra()
                                ->seat($seatsPax[$key]);
                        }
                    }
                } elseif (preg_match("#^\s*([A-Z\d]{2})(\d+)\s*$#", $flight, $m)) {
                    $seats = [];
                    $s = $f->addSegment();
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    $root2 = $root;

                    if (count(array_unique($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Seat:'))}][1]/preceding::text()[normalize-space()][1]", $root))) === 1) {
                        $root2 = null;
                    }
                    // Seats
                    if (empty($seats = array_filter($this->http->FindNodes(".//text()[contains(.,'" . $s->getArrName() . $s->getFlightNumber() . "')]/following::text()[normalize-space(.)!=''][1][{$this->starts($this->t("Seat:"))}]/following::text()[normalize-space(.)!=''][1]",
                        $root2, "#^\d+[A-Z]$#")))
                    ) {
                        if (empty($seats = array_filter($this->http->FindNodes(".//text()[contains(.,'" . $s->getArrName() . $s->getFlightNumber() . "')]/following::text()[normalize-space(.)!=''][1][{$this->starts($this->t("Seat:"))}]",
                            $root2, "#:\s*(\d+[A-Z])$#")))
                        ) {
                            $seats = array_filter($this->http->FindNodes(".//text()[contains(.,'" . $s->getArrName() . $s->getFlightNumber() . "')]/following::text()[normalize-space(.)!=''][1]",
                                $root2, "#^\s*(\d+[A-Z])$#"));
                        }
                    }

                    if (count($seats) > 0) {
                        $s->setSeats(array_unique($seats));
                    }
                }

                if ($position == '1') {
                    if ($key == 0) {
                        $s->departure()
                            ->code($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][1]",
                                $root, true, "#\(([A-Z]{3})\)#"))
                            ->name($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][1]",
                                $root, true, "#(.*?) \([A-Z]{3}\)#"))
                            ->date($this->normalizeDate($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][2]",
                                $root), $date));
                        $arDate = $s->getDepDate();
                    } else {// it-40050955.eml
                        $depName = $this->http->FindSingleNode("//text()[normalize-space()='Passengers']/ancestor::tr[1]/descendant::text()[{$this->starts(' to ' . $s->getArrName())}][1]", null, true, "/(.+){$this->opt($this->t('to'))}/");

                        if (!empty($depName)) {
                            $s->departure()
                                ->name($depName);
                        }

                        $s->departure()
                            ->noCode()
                            ->noDate();
                    }

                    if ($key == count($flightsNumbers) - 1) {
                        $s->arrival()
                            ->code($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][4]",
                                $root, true, "#\(([A-Z]{3})\)#"))
                            ->name($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][4]",
                                $root, true, "#(.*?) \([A-Z]{3}\)#"))
                            ->date($this->normalizeDate($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][3]",
                                $root), $arDate));

                        $depName = $this->http->FindSingleNode("//text()[normalize-space()='Passengers']/ancestor::tr[1]/descendant::text()[{$this->contains(' to ' . $s->getArrName())}]", null, true, "/(.+){$this->opt($this->t('to'))}/");

                        if (!empty($depName)) {
                            $s->departure()
                                ->name($depName);
                        }
                    } else { //it-40050955.eml
                        $arrivalName = $this->http->FindSingleNode("//text()[normalize-space()='Passengers']/ancestor::tr[1]/descendant::text()[{$this->starts($s->getDepName() . ' to')}][1]", null, true, "/{$this->opt($this->t('to'))}\s*(.+)/");

                        if (!empty($arrivalName)) {
                            $s->arrival()
                                ->name($arrivalName);
                        }

                        $s->arrival()
                            ->noCode()
                            ->noDate();
                    }
                } else {
                    $s->departure()
                        ->code($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][1]",
                            $root, true, "#\(([A-Z]{3})\)#"))
                        ->name($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][1]",
                            $root, true, "#(.*?) \([A-Z]{3}\)#"))
                        ->date($this->normalizeDate($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][2]",
                            $root), $date));

                    $arDate = $s->getDepDate();

                    $arDate = $arDate ?? $date;

                    $s->arrival()
                        ->code($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][4]",
                            $root, true, "#\(([A-Z]{3})\)#"))
                        ->name($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][4]",
                            $root, true, "#(.*?) \([A-Z]{3}\)#"))
                        ->date($this->normalizeDate($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[{$positionNorm}]/descendant::text()[normalize-space(.)][3]",
                            $root), $arDate));
                }

                if (isset($setEmptyArrival)) {
                    $s->arrival()
                        ->noCode();
                }
                // Cabin
                $s->setCabin($this->http->FindSingleNode("./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][{$position}]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^(?:cabintypelookup.)?(.+)#"));
            }
        }

        if ($segments->length === 1
            && count($f->getSegments()) === 1
            && $this->http->XPath->query("//text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "]")->length === 2
        ) {
            $root = $segments->item(0);
            $date = $this->normalizeDate($this->re("#" . $this->t(" on ") . "(.+)#",
                $this->http->FindSingleNode("(./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][1])[1]/following::text()[normalize-space(.)][1]",
                    $root)));

            if ($this->http->XPath->query("//a[contains(@href,'https://www.turkishairlines.com/') and contains(@href,'/flights/manage-booking?')]")->length > 0) {
                $s->airline()
                    ->name('TK');
            } else {
                $s->airline()
                    ->noName();
            }
            $s->airline()
                ->noNumber();

            $s->departure()
                ->code($this->http->FindSingleNode($q = "(./preceding::" . $imgPath . "/ancestor::td[1])[last()]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[last()]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "#(.*?) \([A-Z]{3}\)#"))
                ->date($this->normalizeDate($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[last()]/descendant::text()[normalize-space(.)][2]",
                    $root), $date));

            $arDate = $s->getDepDate();

            $arDate = $arDate ?? $date;
            $s->arrival()
                ->code($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[last()]/descendant::text()[normalize-space(.)][4]",
                    $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[last()]/descendant::text()[normalize-space(.)][4]",
                    $root, true, "#(.*?) \([A-Z]{3}\)#"))
                ->date($this->normalizeDate($this->http->FindSingleNode("(./preceding::" . $imgPath . "/ancestor::td[1])[last()]/descendant::text()[normalize-space(.)][3]",
                    $root), $arDate));

            // Cabin
            $s->setCabin($this->http->FindSingleNode("./preceding::text()[" . $this->eq($this->t("OUTBOUND TRIP")) . "][1]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^(?:cabintypelookup.)?(.+)#"), true, true);
        }

        $this->BoardingPass($f, $email);
    }

    private function BoardingPass(Flight $f, Email $email)
    {
        $confNumber = $f->getConfirmationNumbers()[0][0];

        if (!empty($confNumber)) {
            $travellers = $f->getTravellers();

            foreach ($travellers as $traveller) {
                $segments = $f->getSegments();

                foreach ($segments as $segment) {
                    $depCode = $segment->getDepCode();
                    $flightNumber = $segment->getFlightNumber();

                    if (!empty($depCode) && !empty($flightNumber)) {
                        $bp = $email->add()->bpass();
                        $bp->setRecordLocator($confNumber);
                        $bp->setAttachmentName($this->http->FindSingleNode('//text()[normalize-space(.)="' . $confNumber . '"]/ancestor::a[1]/@href'));
                        $bp->setTraveller($traveller[0]);
                        $bp->setFlightNumber(trim(($segment->getAirlineName() ?? '') . ' ' . $flightNumber));
                        $bp->setDepCode($depCode);
                        $bp->setDepDate($segment->getDepDate());
                    }
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        $in = [
            // 14 Ocak 2025 Salı
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s+(\d{4})\s+[[:alpha:]]+\s*$#u",
            "#^(?<week>[^\s\d]+) (\d+) ([^\s\d]+)$#", //Saturday 08 October
        ];
        $out = [
            "$1 $2 $3",
            "$2 $3 %Y%",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^\s*[[:alpha:]]+[\s,]+(\d+\s+([[:alpha:]]+)\s+\d{4})\s*$#u", $str, $m)) {
            $str = $m[1];
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y',
                    EmailDateHelper::calculateDateRelative(str_replace('%Y%', '', $str), $this, $this->parser)), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
