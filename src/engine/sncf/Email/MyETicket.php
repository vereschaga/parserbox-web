<?php

namespace AwardWallet\Engine\sncf\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Strings;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyETicket extends \TAccountChecker
{
    use ProxyList;

    public $mailFiles = "sncf/it-111830175.eml, sncf/it-115762462.eml, sncf/it-117282772.eml, sncf/it-12326685.eml, sncf/it-163425582.eml, sncf/it-34115528.eml, sncf/it-34515654.eml, sncf/it-53974244.eml, sncf/it-61704375.eml, sncf/it-666553564.eml, sncf/it-668532407.eml, sncf/it-68959457.eml"; // +1 bcd screenshot

    public $date;
    public $junkReason = null;
    public $lang = "en";
    public $hardCodeCity = [
        'Dax' => 'Dax, France',
        'Eze' => 'Eze, France',
    ];
    public static $dictionary = [
        "fr" => [
            //For Junk Phrases
            "Cancelling my ticket"                      => "Annulation de mon billet",
            "Your ticket was successfully cancelled on" => "Votre billet a bien été annulé le",
            "Your cancelled ticket"                     => "Votre billet annulé",
            "Your summary"                              => "Votre récapitulatif",

            "garbageSegments" => ["Correspondance", "correspondance", "CORRESPONDANCE"],
            "Référence"       => ["Références", "Référence"],
            //            "Nom" => "",
            //            "Bonjour" => "",
            //            "Nom de famille" => "",
            "**h**" => ["**h**", "**H**"],
            "route" => ["Aller", "Retour", "Voyage"],
            //            "Aller" => "",
            //            "ALLER" => "",
            //            "Retour" => "",
            //            "RETOUR" => "",
            //            "Votre voyage" => "",
            //            "Voiture" => "",
            "Place"                  => ["Place", "Places", "place", "places"],
            "Prix initial du voyage" => ["Prix initial du voyage", "Prix du voyage initial"],
            //            "Order total" => "",
            "statusVariants"         => "annulé",
            "cancelledPhrases"       => [
                "Annulation de mon billet",
                "Votre billet a bien été annulé le dimanche",
                "Votre billet annulé",
            ],
            "ViewReservation" => ['Accéder à ma commande', 'Consulter mon voyage', 'Payer votre option'],
        ],
        "en" => [
            //            "garbageSegments" => "",
            "Référence" => ["Reference", "References"],
            //            "Nom" => "",
            "Bonjour"        => "Hello",
            "Nom de famille" => "Last name",
            //            "**h**" => "",
            "route"        => ["Outbound", "Return"],
            "Aller"        => "Outbound",
            "ALLER"        => "OUTBOUND",
            "Retour"       => "Return",
            "RETOUR"       => "RETURN",
            "Votre voyage" => "Your journey",
            //            "Voiture" => "",
            //            "Place" => "",
            "Prix initial du voyage" => "Initial price of the trip",
            "Order total"            => "Order total",
            "statusVariants"         => ["cancelled", "canceled"],
            "cancelledPhrases"       => [
                "Your ticket was successfully cancelled", "Your ticket was successfully canceled",
                "Your cancelled ticket", "Your canceled ticket",
            ],
            "ViewReservation" => "Access my order",
        ],
        "de" => [
            //            "garbageSegments" => "",
            "Référence" => ["Referenz", "Referenzen"],
            //            "Nom" => "",
            //"Bonjour"        => "",
            "Nom de famille" => "Familienname",
            //            "**h**" => "",
            "route"        => ["Hinreise", "Rückreise"],
            //"Aller"        => "Outbound",
            "ALLER"        => "HINFAHRT",
            //"Retour"       => "Return",
            "RETOUR"       => "RÜCKFAHRT",
            "Votre voyage" => "Ihre Reise",
            //            "Voiture" => "",
            //            "Place" => "",
            "Prix initial du voyage" => "Initial price of the trip",
            //            "Order total" => "",
            //"statusVariants"         => ["cancelled", "canceled"],
            "cancelledPhrases"       => [
                "Your ticket was successfully cancelled", "Your ticket was successfully canceled",
                "Your cancelled ticket", "Your canceled ticket",
            ],
            "ViewReservation" => "Zu meiner Bestellung",
        ],
        "es" => [
            //            "garbageSegments" => "",
            "Référence" => ["Referencia", "Referencias"],
            "Nom"       => "Nombre",
            //"Bonjour"        => "",
            //            "Nom de famille" => "Familienname",
            //            "**h**" => "",
            "route"        => ["Viaje", 'Tu viaje'],
            "Aller"        => "Ida",
            //            "ALLER"        => "HINFAHRT",
            //"Retour"       => "Return",
            //            "RETOUR"       => "RÜCKFAHRT",
            "Votre voyage" => "Tu viaje",
            //            "Voiture" => "",
            //            "Place" => "",
            //            "Prix initial du voyage" => "",
            "Order total" => "Total reserva",
            //"statusVariants"         => ["cancelled", "canceled"],
            //            "cancelledPhrases"       => [
            //                "Your ticket was successfully cancelled", "Your ticket was successfully canceled",
            //                "Your cancelled ticket", "Your canceled ticket",
            //            ],
            "ViewReservation" => ["Acceder a tu reserva", "Pagar tu opción"],
        ],
    ];

    private $detectSubject = [
        "fr" => "Votre voyage ",
        "Confirmation d'Annulation",
        "Confirmation d'échange",
        "en" => "Cancellation Confirmation",
        "de" => "Ihre Reise ",
        // es
        "Confirmación de la reserva",
        'Tu viaje ',
    ];

    private $detectCompany = ['oui.sncf', ".sncf-connect.com"];

    private $detectBody = [
        "de" => [
            "Mein E-Ticket zu meiner Information",
        ],
        "es" => [
            "Tus resúmenes de reserva",
            'Tu resumen de reserva',
            'Resumen de la opción',
        ],
        "fr" => [
            "Votre billet est nominatif et à usage strictement personnel",
            "Vos billets sont nominatifs et à usage strictement personnel",
            "Pour le retrait de votre commande, vous devez présenter la même carte de paiement",
            "Pour récupérer votre billet, avoir plus de détails sur votre réservation",
            "Besoin d’échanger ou annuler votre billet",
            "Annulation de mon billet",
            "Votre billet a bien été annulé le dimanche ",
            "Débit - Vente à distance sécurisée",
            "Payer votre option",
            "Votre sélection a bien été échangée le",
        ],
        "en" => [
            "Your ticket was successfully cancelled",
            "Your ticket will be sent to you by email",
            "Ticket to print for my reference",
            "My e-ticket for my reference",
            "To retrieve your ticket, get more details about your booking, add to it",
        ],
    ];

    private $detectLang = [
        "es" => ["Viaje", 'Tu viaje'],
        "fr" => ["Votre voyage", "Votre billet", "Confirmation d'échange", "Accéder à ma commande", "Consulter mon voyage"],
        "de" => ["Hinreise", "Rückreise"],
        "en" => ["Outbound"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false
                    || $this->http->XPath->query("//*[contains(normalize-space(),\"{$dBody}\")]")->length > 0
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $email->setType('MyETicket' . ucfirst($this->lang));

        if ($this->http->XPath->query("//h1[{$this->contains($this->t('Cancelling my ticket'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your ticket was successfully cancelled on'))}]")->length > 0
            && $this->http->XPath->query("//h2[{$this->contains($this->t('Your cancelled ticket'))}]")->length > 0
            && $this->http->XPath->query("//h2[{$this->contains($this->t('Your summary'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->contains($this->t('ViewReservation'))}]")->length === 0
            && $this->http->XPath->query("//img[contains(@src, 'logo-oui')]")->length === 0
        ) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->date = strtotime($parser->getHeader('date'));

        $xpathTable = "(self::table or self::div)";

        // Confirmation
        $confirmations = preg_split("/\s*,\s*/", implode(', ', $this->http->FindNodes("//text()[{$this->eq($this->t("Référence"))}]/following::text()[normalize-space()][1]/ancestor::*[self::p or self::div or self::td][1]", null, '/^\s*(?:' . $this->preg_implode($this->t("Référence")) . ')?\s*([A-Z\d]{5,}(?:\s*,\s*[A-Z\d]{5,})*)\s*$/u')));

        if (count(array_filter($confirmations)) == 0) {
            $confirmations = $this->http->FindNodes("//text()[{$this->eq($this->t("Référence"))}]/following::text()[normalize-space()][1]/ancestor::*[self::p or self::div or self::td][1]", null, '/([A-Z\d]{5,})$/u');
        }

        if ($confirmations) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Référence"))}]", null, true, '/^(.+?)[\s:]*$/');
        }

        // Travellers
        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]'; // Mr. Hao-Li Huang
        $travellers = array_filter($this->http->FindNodes("//img[contains(@src,'ticket/avatar') or contains(@src,'ticket/ivtsAvatar')]/ancestor::tr[1]", null, "/^\s*({$patterns['travellerName']})\s*\(/u"));

        if (empty($travellers)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Nom"))}]/following::text()[normalize-space()][2]/ancestor::td[1]", null, true, "/^\s*({$patterns['travellerName']})\s*$/u");

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Nom"))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Bonjour"))}]", null, true, "/^{$this->preg_implode($this->t("Bonjour"))}\s+({$patterns['travellerName']})(?:\s*[,;?!]|$)/u");
            }

            if (!empty($traveller)) {
                $travellers = [$traveller];
            }
        }

        // Segments type 1 (full)
        $xpathPoint = "count(*[normalize-space()])=2 and *[1][{$this->eq($this->t("**h**"), "translate(normalize-space(),'0123456789','**********')")}] and *[2][not(descendant::text()[normalize-space()])] and *[3][normalize-space()]";
        $xpath = "//tr[{$xpathPoint}]";
        $points = $this->http->XPath->query($xpath);

//        if ($points->length % 2 !== 0) {
//            $this->logger->debug('Incorrect count segment points!');
//            return $email;
//        }
        $segmentsTrain = [];
        $segmentsBus = [];

        foreach ($points as $key => $point) {
            $segmentType = 'train'; // default
            $followRows = $this->http->XPath->query('following-sibling::tr[string-length(normalize-space())>2]', $point);

            if ($followRows->length < 2) {
                continue;
            }

            foreach ($followRows as $row) {
                if (!empty($points[$key + 1]) && $points[$key + 1] === $row) {
                    break;
                }

                if ($this->http->XPath->query("descendant::img[contains(@src,'travel-detail/bus.')]", $row)->length > 0
                    || $this->http->XPath->query("descendant::text()[contains(.,'OUIBUS')]", $row)->length > 0
                ) {
                    $segmentType = 'bus';

                    break;
                } elseif ($this->http->XPath->query("descendant::text()[{$this->contains($this->t('garbageSegments'))}]", $row)->length > 0) {
                    $segmentType = 'garbage';

                    break;
                }
            }

            switch ($segmentType) {
                case 'train':
                    $segmentsTrain[] = $point;

                    break;

                case 'bus':
                    $segmentsBus[] = $point;

                    break;
            }
            $this->logger->debug('Found segment: ' . $segmentType);
        }

        if (count($segmentsTrain)) {
            $t = $this->parseTrain($email, $points, $segmentsTrain);

            if ($confirmations) {
                foreach ($confirmations as $conf) {
                    if (!empty($conf)) {
                        $t->general()->confirmation($conf, $confirmationTitle);
                    }
                }
            }

            if (!empty($travellers)) {
                $t->general()->travellers($travellers);
            }
        }

        if (count($segmentsBus)) {
            $b = $this->parseBus($email, $points, $segmentsBus);

            if ($confirmations) {
                foreach ($confirmations as $conf) {
                    $b->general()->confirmation($conf, $confirmationTitle);
                }
            }

            if (!empty($travellers)) {
                $b->general()->travellers($travellers);
            }
        }
        //Segments type 4 (route in header)
        if ($points->length == 0) {
            $xpath = "//text()[" . $this->starts($this->t("**h**"), "translate(normalize-space(),'0123456789','**********')") . "]/ancestor::*[not(" . $this->starts($this->t("**h**"), "translate(normalize-space(),'0123456789','**********')") . ")][1][preceding::text()[normalize-space()][1][" . $this->eq($this->t("route")) . "]]";
            $points = $this->http->XPath->query($xpath);

            if ($points->length == 1 || $points->length == 2) {
                $t = $this->parseTrainSegments4($email, $points, $segmentsTrain);

                if ($confirmations) {
                    foreach ($confirmations as $conf) {
                        if (!empty($conf)) {
                            $t->general()->confirmation($conf, $confirmationTitle);
                        }
                    }
                }
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Nom"))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

                if (!empty($traveller)) {
                    $t->general()->traveller($traveller, false);
                }
            }
        }

        $order = 1;
        $queryResult = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Référence"))}]/ancestor::table[ preceding-sibling::table[normalize-space()] ][position() = 1 or position()=2][1]/preceding-sibling::table[string-length()>2][1]");

        if (empty($queryResult)) {
            $order = 2;
        }

        // Segments type 2 (short)
        if ($points->length === 0
            && ($dates = $this->http->XPath->query("//text()[{$this->eq($this->t("Référence"))}]/ancestor::table[ preceding-sibling::table[normalize-space()] ][position() = 1 or position()=2]/preceding-sibling::table[string-length()>2][$order][{$this->starts($this->t("route"))} or contains(., ' vers ') or contains(., ' nouveau ') or contains(., ' billet ')]"))->length > 0
            || ($dates = $this->http->XPath->query("//text()[{$this->eq($this->t("Référence"))}]/ancestor::table[ preceding-sibling::table[normalize-space()] ][position() = 1 or position()=2]/preceding-sibling::table[string-length()>2][$order-1][{$this->starts($this->t("route"))} or contains(., ' vers ') or contains(., ' nouveau ') or contains(., ' billet ')]"))->length > 0
        ) {
            $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order total'))}]/ancestor::tr[1]/descendant::td[2]");

            if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
                $currency = $this->normalizeCurrency($m['currency']);

                $email->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);
            }
            // it-53974244.eml, it-163425582.eml
            $this->logger->debug('Found segments type 2 (short)');

            if ($this->parseShortSegmentsLink($email) !== true) {
                foreach ($dates as $root) {
                    $this->parseShortSegments($email, $root, $confirmations);

                    $confirmationCells = array_unique($this->http->FindNodes("following-sibling::table[normalize-space()][1]/descendant::text()[{$this->eq($this->t("Référence"))}]/ancestor::*[../self::tr][1]",
                        $root));

                    $confs = [];

                    foreach ($confirmationCells as $confirmationCell) {
                        if (preg_match("/^({$this->preg_implode($this->t("Référence"))})[:\s]*([A-Z\d]{5,})$/u",
                            $confirmationCell, $m)) {
                            $confs[] = ['name' => $m[1], 'value' => $m[2]];
                        }
                    }

                    foreach ($email->getItineraries() as $i => $it) {
                        if (!empty($confs) && empty($it->getConfirmationNumbers())) {
                            foreach ($confs as $conf) {
                                $email->getItineraries()[$i]->general()->confirmation($conf['value'], $conf['name']);
                            }
                        }

                        if (!empty($travellers) && empty($it->getTravellers())) {
                            $email->getItineraries()[$i]->general()->travellers($travellers);
                        }
                    }
                }
            } else {
                $confirmationCells = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t("Référence"))}]/ancestor::*[../self::tr][1]"));

                $confs = [];

                foreach ($confirmationCells as $confirmationCell) {
                    if (preg_match("/^({$this->preg_implode($this->t("Référence"))})[:\s]*([A-Z\d]{5,})$/u",
                        $confirmationCell, $m)) {
                        $confs[] = ['name' => $m[1], 'value' => $m[2]];
                    }
                }

                foreach ($email->getItineraries() as $i => $it) {
                    if (!empty($confs) && empty($it->getConfirmationNumbers())) {
                        foreach ($confs as $conf) {
                            $email->getItineraries()[$i]->general()->confirmation($conf['value'], $conf['name']);
                        }
                    }

                    if (!empty($travellers) && empty($it->getTravellers())) {
                        $email->getItineraries()[$i]->general()->travellers($travellers);
                    }
                }
            }
        }

        // Segments type 3 (cancelled)
        if ($points->length === 0 && $dates->length !== 1
            && ($roots = $this->http->XPath->query("//tr[ *[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Aller"))}] and *[3]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Référence"))}] ]"))->length === 1
        ) {
            // it-61704375.eml
            $this->logger->debug('Found segments type 3 (cancelled)');
//            $this->logger->debug('$xpath = '.print_r( "//tr[ *[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t("route"))}] and *[3]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Référence"))}] ]",true));
            $t = $this->parseCancelledSegments($email, $roots->item(0));

            if ($confirmations) {
                foreach ($confirmations as $conf) {
                    if (!empty($conf)) {
                        $t->general()->confirmation($conf, $confirmationTitle);
                    }
                }
            }
            $lastName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Nom de famille"))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

            if (!empty($travellers)) {
                if ($lastName && count($travellers) === 1) {
                    $travellers = [array_shift($travellers) . ' ' . $lastName];
                }
                $t->general()->travellers($travellers);
            } elseif ($lastName) {
                $t->general()->traveller($lastName);
            }

            $totalPrice = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t("Prix initial du voyage"))}] ]/*[2]");

            if (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+?)$/', $totalPrice, $matches)
                || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $matches)
            ) {
                // 72,00 €    |    €24.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        if ($this->junkReason !== null) {
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'train' || $it->getType() === 'bus') {
//                    $email->removeItinerary($it);
                }
            }
            // $email->setIsJunk(true, $this->junkReason);

            return $email;
        }

        // Price

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Référence"))}]/ancestor::*[ {$xpathTable} and preceding-sibling::*[{$xpathTable} and normalize-space() and .//img] ][1]/preceding-sibling::*[{$xpathTable} and normalize-space() and .//img][1]/descendant::td[not(.//td) and normalize-space()][last()]", null, true,
            "/.*\d+[\.\,]+\d+.*/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Prix"))}]/following::td[normalize-space()][1]", null, true,
                "/.*\d.*/");
        }

        if (preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $matches)
            || preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $matches)
        ) {
            // 102,00 €    |    € 102,00
            $currency = $this->currency($matches['curr']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'oui.sncf') !== false
            || stripos($from, '@connect.sncf') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains($this->detectCompany, '@href')}] | //*[{$this->contains($this->detectCompany)}] | //img[contains(@src, '/logo-sncf-connect.png')]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false
                    || $this->http->XPath->query("//*[contains(normalize-space(),\"{$dBody}\")]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseTrain(Email $email, $allPoints, $segments): \AwardWallet\Schema\Parser\Common\Train
    {
        $this->logger->debug('ParseTrain');

        $t = $email->add()->train();

        // Segment
        foreach ($segments as $root) {
            $s = $t->addSegment();

            $date = 0;
            $dateTexts = $this->http->FindNodes("(ancestor::td[1]/preceding::text()[{$this->eq($this->t("route"))}])[last()]/ancestor::*[self::tr or self::div][1]/descendant::text()[normalize-space()]", $root);

            if (preg_match("#{$this->preg_implode($this->t("route"))}\s*([\s\S]{3,})#", implode(' ', $dateTexts), $m)) {
                $date = $this->normalizeDate($m[1]);
            }

            $sText = implode("\n", $this->http->FindNodes('*[normalize-space()]', $root));
            $followRows = $this->http->XPath->query('following-sibling::tr[string-length(normalize-space())>2]', $root);

            foreach ($followRows as $row) {
                foreach ($allPoints as $point) {
                    if ($point === $row) {
                        $sText .= "\n" . implode("\n", $this->http->FindNodes('*[normalize-space()]', $row));

                        break 2;
                    }
                }
                $sText .= "\n" . implode("\n", $this->http->FindNodes('*[normalize-space()]', $row));
            }

            /*
                11H41
                Lille Flandres
                1h03
                TGV INOUI 7248 1èreclasse
                Voiture 3Places 23, 24, 26
                12H44
                Paris Nord
            */
            $regexp = "#"
                . "(?<dTime>\d{2}H\d{2})\n"
                . "(?<dName>.+)\n"
                . "(?<duration>\d{1,2}[ ]*h(?:[ ]*\d{2})?|\d{1,3}[ ]*min)\n" // 1h12    |    17 min    |    1h
                . "(?<info>[\s\S]+?)"
                . "(?<aTime>\d{2}H\d{2})\n"
                . "(?<aName>.+)"
                . "#i";

            if (preg_match($regexp, $sText, $m)) {
                // Departure
                $s->departure()
                    ->name($m['dName'])->geoTip('Europe');

                if (!empty($date) && ($time = $this->normalizeTime($m['dTime']))) {
                    $s->departure()->date(strtotime($time, $date));
                }

                // Arrival
                $s->arrival()
                    ->name($m['aName'])->geoTip('Europe');

                if (!empty($date) && ($time = $this->normalizeTime($m['aTime']))) {
                    $s->arrival()->date(strtotime($time, $date));
                }

                // Extra
                $s->extra()
                    ->duration($m['duration'])
                ;

                if (preg_match("#^\s*(?<name>.+?)\s+(?<number>\d{1,6})[\s+\|]+(?<class>\d\w{0,5})\s*class#u", $m['info'], $mat)) {
                    $s->extra()
                        ->service($mat['name'])
                        ->number($mat['number'])
                        ->cabin($mat['class'])
                    ;
                }

                if (preg_match("#\s+" . $this->preg_implode($this->t("Voiture")) . "\s*(?<car>\d+)\s*" . $this->preg_implode($this->t("Place")) . "\s*(?<seat>[\dA-Z]{1,5}([ ]?,[ ]?[\dA-Z]{1,5})*)(?:\s+|$)#", $m['info'], $mat)) {
                    $s->extra()
                        ->car($mat['car'])
                        ->seats(array_map('trim', array_filter(explode(",", $mat['seat']))))
                    ;
                }
            }
        }

        return $t;
    }

    private function parseBus(Email $email, $allPoints, $segments): \AwardWallet\Schema\Parser\Common\Bus
    {
        $b = $email->add()->bus();

        // Segment
        foreach ($segments as $root) {
            $s = $b->addSegment();

            $date = 0;
            $dateTexts = $this->http->FindNodes("(ancestor::td[1]/preceding::text()[{$this->eq($this->t("route"))}])[last()]/ancestor::*[self::tr or self::div][1]/descendant::text()[normalize-space()]", $root);

            if (preg_match("#{$this->preg_implode($this->t("route"))}\s*([\s\S]{3,})#", implode(' ', $dateTexts), $m)) {
                $date = $this->normalizeDate($m[1]);
            }

            $sText = implode("\n", $this->http->FindNodes('*[normalize-space()]', $root));
            $followRows = $this->http->XPath->query('following-sibling::tr[string-length(normalize-space())>2]', $root);

            foreach ($followRows as $i => $row) {
                foreach ($allPoints as $point) {
                    if ($point === $row) {
                        $sText .= "\n" . implode("\n", $this->http->FindNodes('*[normalize-space()]', $row));

                        if (!empty($followRows[$i + 1])) {
                            $sText .= "\n" . implode("\n", $this->http->FindNodes('*[normalize-space()]', $followRows[$i + 1]));
                        }

                        break 2;
                    }
                }
                $sText .= "\n" . implode("\n", $this->http->FindNodes('*[normalize-space()]', $row));
            }

            /*
                10h30
                Aéroport De Genève
                2h15
                Route de l'aéroport 21, 1215 Le Grand-Saconnex, Suisse
                OUIBUS
                12h45
                Gare De Grenoble
                11 Place de la Gare, 38000 Grenoble, France
            */
            $regexp = "#"
                . "(?<dTime>\d{2}h\d{2})\n"
                . "(?<dName>.+)\n"
                . "(?<duration>\d{1,2}[ ]*h(?:[ ]*\d{2})?|\d{1,3}[ ]*min)\n" // 1h12    |    17 min    |    1h
                . "(?<dAddr>.+)"
                . "[\s\S]+?"
                . "(?<aTime>\d{2}h\d{2})\n"
                . "(?<aName>.+)\n"
                . "(?<aAddr>.+)"
                . "#i";

            if (preg_match($regexp, $sText, $m)) {
                // Departure
                $s->departure()
                    ->name($m['dName'])->geoTip('Europe')
                    ->address($m['dAddr'])
                ;

                if (!empty($date) && ($time = $this->normalizeTime($m['dTime']))) {
                    $s->departure()->date(strtotime($time, $date));
                }

                // Arrival
                $s->arrival()
                    ->name($m['aName'])->geoTip('Europe')
                    ->address($m['aAddr'])
                ;

                if (!empty($date) && ($time = $this->normalizeTime($m['aTime']))) {
                    $s->arrival()->date(strtotime($time, $date));
                }

                // Extra
                $s->extra()
                    ->duration($m['duration'])
                ;
            }
        }

        return $b;
    }

    private function parseShortSegments(Email $email, $root)
    {
        $t = $email->add()->train();

        $xpathRoute = "preceding::tr[normalize-space()][1]/descendant::img";
        $point1 = $this->http->FindSingleNode($xpathRoute . "/preceding::node()[normalize-space()][1]", $root, true, "/^(?:{$this->preg_implode($this->t("Votre voyage"))}\s*)?(.{3,})$/");
        $point2 = $this->http->FindSingleNode($xpathRoute . "/following::node()[normalize-space()][1]", $root);

        // Aller : mercredi 12 février à 05h31    |    Outbound: Thursday, 24 February 2022 at 12:53
        $patterns['dateTime'] = "/^\s*{$this->preg_implode($this->t("route"))}?[:\s]+(?<date>.{3,}?)\s+(?:à|at)\s+(?<time>\d{1,2}[:h]+\d{2})$/";

        $xpathExtra = "//img[contains(@src,'ticket/avatar') or {$this->contains($this->t("Voyageur"), '@alt')}]/ancestor::tr[1]/following::tr[normalize-space()][1]";

        $dateDepart = implode(' ', $this->http->FindNodes("descendant::td[not(.//td) and {$this->starts($this->t("Aller"))}]/descendant::text()[normalize-space()]", $root));
        $dateReturn = implode(' ', $this->http->FindNodes("descendant::td[not(.//td) and {$this->starts($this->t("Retour"))}]/descendant::text()[normalize-space()]", $root));

        if (preg_match($patterns['dateTime'], $dateDepart, $m)) {
            $this->junkReason = 'Missing arrival date.';
            $s1 = $t->addSegment();

            $s1->departure()
                ->name($point1)->geoTip('Europe')
                ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])));

            $s1->arrival()->name($point2)->geoTip('Europe')
                ->noDate();

            $departInfo = implode("\n", $this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->eq($this->t("ALLER"))}]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match_all("/^{$this->preg_implode($this->t("ALLER"))}$\s+(?<name>.+?)\s+(?<number>\d+)$/m", $departInfo, $matches)) {
                if (count(array_unique($matches['name'])) === 1) {
                    $s1->extra()->service($matches['name'][0]);
                }

                if (count(array_unique($matches['number'])) === 1) {
                    $s1->extra()->number($matches['number'][0]);
                }
            }
        }

        if (preg_match($patterns['dateTime'], $dateReturn, $m)) {
            $s2 = $t->addSegment();

            $s2->departure()
                ->name($point2)->geoTip('Europe')
                ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])));

            $s2->arrival()
                ->name($point1)->geoTip('Europe')
                ->noDate();

            $returnInfo = implode("\n", $this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->eq($this->t("RETOUR"))}]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match_all("/^{$this->preg_implode($this->t("RETOUR"))}$\s+(?<name>.+?)\s+(?<number>\d+)$/m", $returnInfo, $matches)) {
                if (count(array_unique($matches['name'])) === 1) {
                    $s2->extra()->service($matches['name'][0]);
                }

                if (count(array_unique($matches['number'])) === 1) {
                    $s2->extra()->number($matches['number'][0]);
                }
            }
        }

        return $email;
    }

    private function parseShortSegmentsLink(Email $email)
    {
        $t = $email->add()->train();

        $data = null;
        $link = $this->http->FindSingleNode("//a[{$this->eq($this->t('ViewReservation'))}]/@href");

        if (stripos($link, '://urldefense.proofpoint.com/v2/url?') !== false
        || stripos($link, '//linkprotect.cudasvc.com/url?') !== false) {
            $link = str_replace('https://urldefense.proofpoint.com/v2/url?u=', '', $link);
            $link = str_replace('https://linkprotect.cudasvc.com/url?a=', '', $link);
            $link = str_replace(['_'], ['/'], $link);
            $link = preg_replace('/-([\dA-Z]{2})/', '%$1', $link);
            $link = urldecode($link);
            $link = preg_replace('/(TRIP_IMPORT).+/', '$1', $link);
        }

        if (stripos($link, '.safelinks.protection.outlook.com/?url=') !== false
            && stripos($link, 'https://') !== false) {
            $link = str_replace(["%3D", "%26"], ["=", "&"], $link);
        }

        if (stripos($link, 'sncf') === false) {
            $http = clone $this->http;
            $http->setMaxRedirects(0);
            $http->GetURL($link);

            if (!empty($http->Response['headers']) && stripos($http->Response['headers']['location'], 'sncf') !== false) {
                $link = $http->Response['headers']['location'];
            }
        }

        if (stripos($link, 'mimecast.com') !== false) {
            $http = clone $this->http;
            $http->setMaxRedirects(1);
            $http->GetURL($link);

            if (!empty($http->Response['headers']) && stripos($http->Response['headers']['location'], 'sncf') !== false) {
                $link = $http->Response['headers']['location'];
            }
        }

        // https://www.google.com/url?q=https://www.sncf-connect.com/app/redirect?pnrRef%3DJT2GWJ%26name%3DProvost%26rfrr%3DVscMailConf_Travel_AftersaleWeb%26redirection_type%3DTRIP_IMPORT&source=gmail-imap&ust=1688657352000000&usg=AOvVaw3xkvSnoz7Xn8-xbVXCqfwd
        if (stripos($link, 'www.google.com') !== false) {
            $link = $this->re("/google\.com\\/url\?q=(.+)&source=gmail-imap.*/u", $link);
            $link = urldecode($link);
        }

        $this->logger->debug('$link = ' . print_r($link, true));

        if (preg_match("#\?*pnrRef=(?<conf>[\w-]+)&name=(?<name>[^&]+?)(?:&|$)#", $link, $m)) {
            // $m['name'] = str_replace(' ', '+', urldecode($m['name']));
//            oldlink $data['status'] == 'SUCCESS'
//            $link = "https://www.oui.sncf/vsa/api/v2/orders/fr_FR/{$m['name']}/{$m['conf']}?source=vsa&withAftersaleEligibility=true";
            $link = "https://www.sncf-connect.com/bff/api/v1/redirections?pnrRef={$m['conf']}&name={$m['name']}&rfrr=VscMailConf_Travel_AftersaleWeb&redirection_type=TRIP_IMPORT";

            $http = clone $this->http;
            // $http->SetProxy($this->proxyDOP());

            $http->RetryCount = 0;
            $headers = [
                'Host'            => 'www.sncf-connect.com',
                // 'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36,',
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'gzip',
                'x-bff-key'       => 'ah1MPO-izehIHD-QZZ9y88n-kku876',
                'Referer'         => 'https://www.sncf-connect.com/app/redirect?pnrRef=' . $m['conf'] . '&name=' . $m['name'] . '&rfrr=VscMailConf_Travel_AftersaleWeb&redirection_type=TRIP_IMPORT',
            ];
            $http->setMaxRedirects(5);
            $http->GetURL($link, $headers);

            $data = $http->JsonLog(null, 0, true);
            $http->Log("Result 1: " . Strings::cutInMiddle($http->Response['body'], 1000), LOG_LEVEL_ERROR);

            if (!is_array($data)) {
                $http->SetProxy($this->proxyDOP());
                $http->GetURL($link, $headers);
                $data = $http->JsonLog(null, 0, true);
                $http->Log("Result 2: " . Strings::cutInMiddle($http->Response['body'], 1000), LOG_LEVEL_ERROR);
            }

            if (isset($data['url']) && empty($data['order']['trainFolders'])) {
                $http->SetProxy($this->proxyUK());
                $http->GetURL($link, $headers);
                $data = $http->JsonLog(null, 0, true);
                $http->Log("Result 2: " . Strings::cutInMiddle($http->Response['body'], 1000), LOG_LEVEL_ERROR);
            }
        }
        // $this->logger->debug('$http = '.print_r( $http->Response['body'],true));
        // $this->logger->debug('$data = '.print_r( $data,true));

        if (!empty($data) && !empty($data['status']) && $data['status'] == 'SUCCESS') {
            $segments = $data['order']['trainFolders'][0]['travels'];

            foreach ($segments as $segment) {
                if (isset($segment['segments'][0])) {
                    $parts = $segment['segments'];

                    foreach ($parts as $part) {
                        $s = $t->addSegment();
                        $s->departure()
                            ->name($part['origin']['stationName'])->geoTip('Europe')
                            ->date(strtotime($part['departureDate']))
                            ->address('Europe, ' . $part['origin']['cityName'] . ', ' . $part['origin']['address']['streetAddress']);

                        $s->arrival()
                            ->name($part['destination']['stationName'])->geoTip('Europe')
                            ->date(strtotime($part['arrivalDate']))
                            ->address('Europe, ' . $part['destination']['cityName'] . ', ' . $part['destination']['address']['streetAddress']);

                        $s->setServiceName($part['transport']['label'], true, true);

                        $cabin = $part['comfortClass'];

                        if (!empty($cabin)) {
                            $s->extra()
                                ->cabin($cabin);
                        }

                        $duration = $part['duration'];

                        if (!empty($duration)) {
                            $s->extra()
                                ->duration(trim($part['duration'], '-'));
                        }

                        $s->extra()
                            ->number($part['transport']['number']);

                        if (count($segments[0]['segments'][0]['placements']) > 0) {
                            $paxs = $data['order']['trainFolders'][0]['passengers'];

                            foreach ($paxs as $pax) {
                                $paxId = $pax['passengerId'];

                                if (isset($part['placements']["{$paxId}"])) {
                                    $seat = $part['placements']["{$paxId}"]['seatNumber'];

                                    if (!empty($seat)) {
                                        $s->addSeat($seat);
                                    }

                                    $carNumber = $part['placements']["{$paxId}"]['coachNumber'];

                                    if (!empty($carNumber)) {
                                        $s->setCarNumber($carNumber);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $s = $t->addSegment();
                    $s->departure()
                        ->name($segment['origin']['stationName'])->geoTip('Europe')
                        ->date(strtotime($segment['departureDate']))
                        ->address('Europe, ' . $segment['origin']['cityName'] . ', ' . $segment['origin']['address']['streetAddress']);

                    $s->arrival()
                        ->name($segment['destination']['stationName'])->geoTip('Europe')
                        ->date(strtotime($segment['arrivalDate']))
                        ->address('Europe, ' . $segment['destination']['cityName'] . ', ' . $segment['destination']['address']['streetAddress']);

                    $s->extra()
                        ->noNumber();
                }

                $urlTicket = $data['order']['trainFolders'][0]['ticketingInfos']['pdfUrlRecovery'];

                $ticketArray = [];

                if (!empty($urlTicket)) {
                    if (!isset($http)) {
                        $http = clone $this->http;
                        // $http->SetProxy($this->proxyReCaptchaIt7(), false);
                        $http->RetryCount = 0;
                    }
                    $fullUrl = 'https://www.oui.sncf' . $urlTicket;
                    $http->GetURL($fullUrl);
                    $ticketInfoArray = $http->JsonLog(null, 1, true);
                    $http->Log(Strings::cutInMiddle($http->Response['body'], 1000), LOG_LEVEL_ERROR);
                    $tickets = $ticketInfoArray['tickets'];

                    foreach ($tickets as $ticket) {
                        if (count($ticket['tcnList']) > 0) {
                            $ticketArray[] = implode(',', $ticket['tcnList']);
                        }

                        if (isset($ticket['pdfUrl']) && !empty($ticket['pdfUrl'])) {
                            $s->extra()
                                ->link($ticket['pdfUrl']);
                        }
                        //$this->logger->error($ticket['pdfUrl']); - link PDF file
                    }
                }
            }

            if (count($ticketArray) > 0) {
                $t->setTicketNumbers(array_unique(explode(',', (implode(',', $ticketArray)))), false);
            }
        } elseif (!empty($data) && !empty($data['redirection']) && !empty($data['redirection']['tripDetailsRedirection'])) {
            $this->lang = 'fr';
            $travellers = [];
            $confirmations = [];

            //NB!!!! if count arrow > 1 in head segments = 2 segmnets (Paris Gare De Lyon -> <- Lyon Part Dieu)
            $segNumbers = [];
            $tripsCount = count($data['redirection']['tripDetailsRedirection']['consultation']['trips']);

            for ($i = 0; $i < $tripsCount; $i++) {
                $segNumbers[] = 'trips' . '-' . $i;
            }
            $tripsCount = count($data['redirection']['tripDetailsRedirection']['consultation']['passedTrips']);

            for ($i = 0; $i < $tripsCount; $i++) {
                $segNumbers[] = 'passedTrips' . '-' . $i;
            }

            for ($i = 0; $i < count($segNumbers); $i++) {
                $j = explode('-', $segNumbers[$i]);
                $trips = array_intersect_key($data['redirection']['tripDetailsRedirection']['consultation'][$j[0]][$j[1]]['trip']['tripDetails'] ?? [], ['outwardJourney' => '', 'inwardJourney' => '']);

                foreach ($trips as $trip) {
                    $segments = $trip['timeline']['steps'];
                    $this->date = strtotime("-1 day", strtotime($trip['departureDate']));
                    $ticketsSegment = [];

                    foreach ($trip['ticketing'] as $tt) {
                        $ticketsSegment[$tt['origin'] . '-' . $tt['destination']]['date'] = $tt['departureDateHourLabel'];
                        $ticketsSegment[$tt['origin'] . '-' . $tt['destination']]['pdfs'][] = [$tt['pdf'], $tt['travelerIdentity']['fullName']];
                        $ticketsSegment[$tt['origin'] . '-' . $tt['destination']]['travellers'][] = $tt['travelerIdentity']['fullName'];
                        $ticketsSegment[$tt['origin'] . '-' . $tt['destination']]['references'][] = $tt['reference'];

                        $travellers[] = $tt['travelerIdentity']['fullName'];
                        $confirmations[] = $tt['reference'];
                    }

                    if (empty($trip['ticketing']) && !empty($trip['travelersIdentity'])) {
                        $travellers = array_merge($travellers, array_column($trip['travelersIdentity'], 'fullName'));
                    }
                    $confirmations = array_merge($confirmations, $trip['references']);

                    foreach ($segments as $segment) {
                        if (!isset($segment['train'])) {
                            continue;
                        }
                        $seg = $segment['train'];

                        $s = $t->addSegment();

                        $dep = $this->searchCity($seg['departure']['stationLabel']);
                        $arr = $this->searchCity($seg['arrival']['stationLabel']);

                        $s->departure()
                            ->name($dep)->geoTip('Europe');

                        $s->arrival()
                            ->name($arr)->geoTip('Europe');

                        $date = null;

                        if (isset($ticketsSegment[$dep . '-' . $arr])) {
                            $date = $this->re("/^(.+) \d{1,2}:\d{2}\s*$/", $ticketsSegment[$dep . '-' . $arr]['date']);
                        }

                        if (empty($date)) {
                            $date = date("j F Y", strtotime($trip['departureDate']));
                        }

                        $s->departure()
                            ->date($this->normalizeDate($date . ' ' . ($seg['departure']['timeImpactedByDisruption'] ?? $seg['departure']['timeLabel'])));
                        $s->arrival()
                            ->date($this->normalizeDate($date . ' ' . ($seg['arrival']['timeImpactedByDisruption'] ?? $seg['arrival']['timeLabel'])));

                        $s->extra()
                            ->duration($seg['duration']['label'])
                            ->cabin($seg['comfortClass']['label'])
                            ->service($seg['transporter']['description'], true, true)
                            ->car($this->re("/^[[:alpha:]]+\s+([A-Z\d]+)$/u", $seg['seatAssignments'][0]['coachNumber'] ?? ''), true, true)
                        ;

                        if (preg_match("/^\D*(\d+)\s*$/", $seg['transporter']['number'], $m)) {
                            $s->extra()
                                ->number($m[1]);
                        } else {
                            $s->extra()
                                ->noNumber();
                        }

                        if (!empty($seg['seatAssignments'][0]['seats'])) {
                            $seats = [];

                            foreach ($seg['seatAssignments'][0]['seats'] as $si => $sr) {
                                $seats[] = $this->re("/^[[:alpha:]]+\s+([A-Z\d]+)$/u", $sr['number']);
                            }
                            $s->extra()
                                ->seats($seats);
                        }

                        $ticketsArray = [];

                        if (!empty($ticketsSegment[$dep . '-' . $arr]) && !empty(array_filter(array_column($ticketsSegment[$dep . '-' . $arr]['pdfs'], 0)))) {
                            foreach ($ticketsSegment[$dep . '-' . $arr]['pdfs'] as $p) {
                                if (!in_array($p[0], $ticketsArray)) {
                                    $ticketsArray[] = $p[0];
                                    $s->extra()
                                        ->link($p[0], $p[1]);
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($travellers)) {
                $t->general()
                    ->travellers(array_unique($travellers));
            }

            if (!empty($confirmations)) {
                foreach (array_unique($confirmations) as $conf) {
                    $t->general()
                        ->confirmation($conf);
                }
            }
        }

        if ($t->getSegments() > 0) {
            return true;
        }

        return false;
    }

    private function parseCancelledSegments(Email $email, $root): \AwardWallet\Schema\Parser\Common\Train
    {
        $t = $email->add()->train();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $t->general()->cancelled();
        }

        $statuses = array_unique(array_filter($this->http->FindNodes("//h2[{$this->eq($this->t('cancelledPhrases'))}]", null, "/\b{$this->preg_implode($this->t('statusVariants'))}\b/i")));

        if (count($statuses) === 1) {
            $t->general()->status(array_values($statuses)[0]);
        }

        $xpathRoute = "preceding::tr[normalize-space()][1]/descendant::img";
        $point1 = $this->http->FindSingleNode($xpathRoute . "/preceding::node()[normalize-space()][1]", $root, true, "/^(?:{$this->preg_implode($this->t("Votre voyage"))}\s*)?(.{3,})$/");
        $point2 = $this->http->FindSingleNode($xpathRoute . "/following::node()[normalize-space()][1]", $root);

        $s = $t->addSegment();

        $s->departure()->name($point1)->geoTip('Europe');
        $s->arrival()->name($point2)->geoTip('Europe');

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $dates = implode(' ', $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^{$this->preg_implode($this->t("route"))}\s+(?<date>.{6,}?)\s+(?<timeDep>{$patterns['time']})\s+-\s+(?<timeArr>{$patterns['time']})$/",
            $dates, $m)) {
            // Outbound Monday 06 Jul 16:53 - 19:51
            $dateDep = $this->normalizeDate($m['date']);
            $s->departure()->date(strtotime($m['timeDep'], $dateDep));
            $s->arrival()->date(strtotime($m['timeArr'], $dateDep));

            $s->extra()->noNumber();
        }

        $dates = implode(' ', $this->http->FindNodes("*[2]/descendant::text()[normalize-space()]", $root));

        if (!empty($dates) && preg_match("/^{$this->preg_implode($this->t("route"))}\s+(?<date>.{6,}?)\s+(?<timeDep>{$patterns['time']})\s+-\s+(?<timeArr>{$patterns['time']})$/",
            $dates, $m)) {
            $s = $t->addSegment();

            $s->departure()->name($point2)->geoTip('Europe');
            $s->arrival()->name($point1)->geoTip('Europe');
            // Outbound Monday 06 Jul 16:53 - 19:51
            $dateDep = $this->normalizeDate($m['date']);
            $s->departure()->date(strtotime($m['timeDep'], $dateDep));
            $s->arrival()->date(strtotime($m['timeArr'], $dateDep));

            $s->extra()->noNumber();
        }

        return $t;
    }

    private function parseTrainSegments4(Email $email, $segments): \AwardWallet\Schema\Parser\Common\Train
    {
        $t = $email->add()->train();

        // Segment
        foreach ($segments as $i => $root) {
            $s = $t->addSegment();

            $routes = $this->http->FindNodes("./preceding::text()[normalize-space()][1][" . $this->eq($this->t("route")) . "]/ancestor::*[not(" . $this->starts($this->t("route")) . ")][1]/descendant::td[normalize-space()][1]//text()[normalize-space()]", $root);

            if (count($routes) !== 2) {
                $routes = [];
            }
            $sText = implode("\n", $this->http->FindNodes('*[normalize-space()]', $root));
            /*
                Jeudi
                10 SEP
                18h35 - 21h42
                TGV INOUI 6127 1ere classe
                Voiture 3 Place 31
            */
            $regexp = "#^\s*"
                . "(?<date>.+\n.+)\n"
                . "(?<dTime>\d{2}H\d{2}) - (?<aTime>\d{2}H\d{2})\n"
                . "(?<info>[\s\S]+)"
                . "#i";

            if (preg_match($regexp, $sText, $m)) {
                $date = $this->normalizeDate($m['date']);

                // Departure
                if ($i === 0) {
                    $s->departure()
                        ->name($routes[0] ?? null)->geoTip('Europe');
                } elseif ($i === 1) {
                    $s->departure()
                        ->name($routes[1] ?? null)->geoTip('Europe');
                }

                if (!empty($date) && ($time = $this->normalizeTime($m['dTime']))) {
                    $s->departure()->date(strtotime($time, $date));
                }

                // Arrival
                if ($i === 0) {
                    $s->arrival()
                        ->name($routes[1] ?? null)->geoTip('Europe');
                } elseif ($i === 1) {
                    $s->arrival()
                        ->name($routes[0] ?? null)->geoTip('Europe');
                }

                if (!empty($date) && ($time = $this->normalizeTime($m['aTime']))) {
                    $s->arrival()->date(strtotime($time, $date));
                }

                // Extra
                if (preg_match("#^\s*(?<name>.+?)\s+(?<number>\d{1,6})\s+(?<class>\d\w{0,5})\s*class#u", $m['info'], $mat)) {
                    $s->extra()
                        ->service($mat['name'])
                        ->number($mat['number'])
                        ->cabin($mat['class'])
                    ;
                } elseif (preg_match("#^\s*(?<name>.+?)\s+(?<number>\d{1,6})\s+#u", $m['info'], $mat)) {
                    $s->extra()
                        ->service($mat['name'])
                        ->number($mat['number'])
                    ;
                }

                if (preg_match("#\s+" . $this->preg_implode($this->t("Voiture")) . "\s*(?<car>\d+)\s*" . $this->preg_implode($this->t("Place")) . "\s*(?<seat>[\dA-Z]{1,5}([ ]?,[ ]?[\dA-Z]{1,5})*)(?:\s+|$)#", $m['info'], $mat)) {
                    $s->extra()
                        ->car($mat['car'])
                        ->seats(array_map('trim', array_filter(explode(",", $mat['seat']))))
                    ;
                }
            }
        }

        return $t;
    }

    private function searchCity($point)
    {
        foreach ($this->hardCodeCity as $city => $newCityName) {
            if ($city === $point) {
                return $newCityName;
            }
        }

        return $point;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = ' . print_r($date, true));
        $year = date('Y', $this->date);
        $in = [
            // samedi 31 octobre 2020
            '#^\s*(?:Le\s+)?[[:alpha:]]{2,}[\s,]*(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})\s*$#iu',
            // jeudi 15 août; Jeudi 10 sept.
            '#^\s*(?:Le\s+)?([[:alpha:]]{2,})[.,]?\s*(\d{1,2})\s+([[:alpha:]]{3,})[.]?\s*$#iu',
            // Le mardi 02 avril à 19h35
            '#^\s*(?:Le\s+)?([[:alpha:]]{2,})[,.]?\s*(\d{1,2})\s+([[:alpha:]]{3,})\s+à\s+\d{1,2}h\d{2}$#iu',
            // Mar. 21 juin 08:26; Ven. 1 juil. 18:17
            '#^\s*([[:alpha:]]{2,})[,.]?\s*(\d{1,2})\s+([[:alpha:]]{3,})[.]?\s+(\d{1,2}:\d{2})\s*$#iu',
        ];
        $out = [
            '$1 $2 $3',
            '$1, $2 $3 ' . $year,
            '$1, $2 $3 ' . $year,
            '$1, $2 $3 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

//        $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeTime(string $time): string
    {
        $in = [
            '#^\s*(\d{1,2})[h](\d{2})\s*$#ui', //12h25
        ];
        $out = [
            '$1:$2',
        ];
        $time = preg_replace($in, $out, $time);

        return $time;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function currency($s): ?string
    {
        if (preg_match('/^\s*([A-Z]{3})\s*$/', $s, $m)) {
            return $m[1];
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
