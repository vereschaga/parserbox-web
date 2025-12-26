<?php

namespace AwardWallet\Engine\hopper\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parser capitalcards/FlightDetails (in favor of hopper/YourReceiptAndItinerary)

class YourReceiptAndItinerary extends \TAccountChecker
{
    public $mailFiles = "hopper/it-10104588.eml, hopper/it-10104786.eml, hopper/it-10112956.eml, hopper/it-10116047.eml, hopper/it-74445116.eml, hopper/it-81460640.eml, hopper/it-113649163.eml, hopper/it-236778091.eml, hopper/it-385819186.eml, hopper/it-399800843.eml, hopper/it-400008453-es.eml, hopper/it-537098880-cancelled.eml, hopper/it-618219385.eml, hopper/it-618234981-fr.eml, hopper/it-618234990-fr-cancelled.eml";

    private $subject = [
        'Votre reçu Hopper et votre itinéraire', 'Votre réservation Hopper a changé', 'Votre réservation Hopper a été annulée', // fr
        'Tu recibo e itinerario', // es
        'receipt and itinerary', 'booking has changed', 'booking has been cancelled', 'booking has been canceled', 'Thanks for booking', // en
    ];

    private static $detectors = [
        'fr' => [
            'Consultez ci-dessous votre reçu contenant les détails du vol et les informations sur les passagers.',
            'Consultez votre reçu complet avec les détails du vol et des passagers ci-dessous.',
            'Attention ! Les détails de votre vol ont changé pour votre trajet',
            'a été annulé',
        ],
        'es' => [
            'Consulte su recibo completo con los detalles del vuelo y del pasajero a continuación.',
            'A continuación, consulta tu recibo completo con los detalles del vuelo y del pasajero.',
        ],
        'en' => [
            'Check out your full receipt with flight and passenger details below.',
            "Here's what to expect from now until boarding your flight.",
            'Here’s what to expect from now until boarding your flight.',
            'Heads up! Your flight details have changed for your',
            "You're on your way!", 'You’re on your way!',
            'Thanks for purchasing a Price Freeze',
            'has been cancelled', 'has been canceled',
        ],
    ];

    private static $dictionary = [
        'fr' => [
            "otaConfNo"      => ["Code de réservation Hopper", "Numéro de réservation Hopper"],
            // "otaConfNo2" => [""],
            "otaConfNoSubject" => ["Votre réservation Hopper a changé", "Votre réservation Hopper a été annulée"],
            "statusVariants"   => ["Confirmée", "Annulé"],
            "cancelledPhrases" => [" a été annulé."],
            // "junkPhrases" => [""],
            "feeNames"       => ["Garantie de connexion manquée™", "Garantie de connexion manquée"],
            "earnedPhrases"  => ["Super, vous avez gagné"],
            "direction"      => ["Vol aller", "Vol de retour"],
            // "Hopper" => [""],
            "Ticket #:" => ["Numéro de billet:"],
            // "Known Traveler #:" => [""],
            "Fare Details"   => ["Tarif détaillé"],
            "Base fare:"     => ["Tarif de base:", "Tarif de base :"],
            "Taxes & fees:"  => ["Taxes et frais:", "Taxes et frais :"],
            // "Total" => [""],
            // "Seat Selection" => [""],
            // "additionalBaggage" => "",
            // "Seat" => [""],
            "stop"           => ["escale"],
            "layover in"     => "escale à",
            "Operated by"    => ["Opéré par"],
            // "airlineConfNoLeft" => "",
            // "airlineConfNoRight" => "",
            "Payment Info"   => ["Informations de paiement"],
        ],
        'es' => [
            "otaConfNo"      => ["Código de reserva Hopper", "Código de la reserva Hopper", "Código de la reservación Hopper"],
            // "otaConfNo2" => [""],
            "otaConfNoSubject" => ["Tu recibo e itinerario de Hopper"],
            "statusVariants"   => ["Confirmado", "Confirmada"],
            // "cancelledPhrases" => [""],
            // "junkPhrases" => [""],
            "feeNames"       => ["Atención al cliente VIP", "Selección de asientos", "Seguro de viaje", "Equipaje adicional"],
            "earnedPhrases"  => ["¡Felicidades, obtuviste"],
            "direction"      => ["Vuelo de ida"],
            // "Hopper" => [""],
            "Ticket #:" => ["Número de boleto:"],
            // "Known Traveler #:" => [""],
            "Fare Details"   => ["Información sobre tarifas"],
            "Base fare:"     => ["Tarifa base:"],
            "Taxes & fees:"  => ["Impuestos y cargos:", "Impuestos y tarifas:"],
            // "Total" => [""],
            "Seat Selection"    => ["Selección de asientos"],
            "additionalBaggage" => "Equipaje adicional",
            "Seat"              => ["Asiento"],
            "stop"              => ["escala", "parada"],
            "layover in"        => "escala en",
            "Operated by"       => ["Operado por"],
            // "airlineConfNoLeft" => "",
            // "airlineConfNoRight" => "",
            "Payment Info"   => ["Información del pago"],
        ],
        'en' => [
            "otaConfNo"        => ["Hopper Booking Code"],
            "otaConfNo2"       => ["Hopper confirmation code:", "Hopper confirmation code :"],
            "otaConfNoSubject" => [
                "Your Hopper receipt and itinerary", "Your Hopper booking has changed",
                "Your Hopper booking has been cancelled", "Your Hopper booking has been canceled",
            ],
            "statusVariants"   => ["Confirmed", "Price Freeze", "Cancelled", "Canceled"],
            "cancelledPhrases" => [" has been cancelled.", " has been canceled."],
            "junkPhrases"      => ["Thanks for purchasing a Price Freeze"],
            "feeNames"         => [
                "Hopper commission", "Change For Any Reason", "Trip Insurance", "Hopper tip", "Hopper Tip",
                "VIP Support", "Seat Selection", "Refundable by Hopper", "Flight Delay Guarantee™",
            ],
            "earnedPhrases"    => ["Congrats, you earned"],
            "direction"        => ["Outbound", "Return"],
            //"Hopper" => ["Hopper", "Hopper Booking Code"]
            // "additionalBaggage" => "",
            "airlineConfNoLeft"  => ["Here's your", "Here’s your"],
            "airlineConfNoRight" => ["confirmation code:", "confirmation code :"],
        ],
    ];

    private $from = "@hopper.com";

    private $lang;

    private $http2; // for parsing emails with multiple body

    public function parseHtml(Email $email, \HttpBrowser &$http2, string $subject): bool
    {
        if (!$this->detectBody($http2)) {
            return false;
        }

        if ($http2->XPath->query("descendant::*[{$this->contains($this->t("junkPhrases"))}]")->length > 0) {
            // it-113649163.eml
            return true;
        }

        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $r = $email->add()->flight();
        $r->general()->noConfirmation();
        $otaConfirmation = $otaConfirmationTitle = null;

        // Status
        $status = $http2->FindSingleNode("//div[contains(@style,'border-radius:') or contains(@style,'border-top-left-radius:')]", null, true, "/^{$this->opt($this->t("statusVariants"))}$/i");

        if (!empty($status)) {
            $r->general()->status($status);
        }

        if ($http2->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $r->general()->cancelled();
        }

        $rls = [];
        $nodes = $http2->XPath->query("//text()[{$this->eq($this->t('Hopper'))}]/ancestor::td[2]/table");

        if ($nodes->length > 0) {
            // it-10104588.eml
            foreach ($nodes as $root) {
                $company = $http2->FindSingleNode("descendant::text()[normalize-space()][1]", $root);
                $confirmation = $http2->FindSingleNode("descendant::text()[normalize-space()][2]", $root, true, "/^[-A-z\d ]{5,}$/");

                if (preg_match("/^{$this->opt($this->t('Hopper'))}$/i", $company, $m)) {
                    $otaConfirmation = $confirmation;
                    $otaConfirmationTitle = $m[0];
                } else {
                    $rls[$company] = $confirmation;
                }
            }
        } else {
            // it-236778091.eml
            $otaConfirmation = $http2->FindSingleNode("//text()[{$this->eq($this->t('otaConfNo'))}]/ancestor::tr[1]/descendant::td[1]", null, true, "/^[-A-z\d ]{5,}$/");
            $otaConfirmationTitle = $http2->FindSingleNode("//text()[{$this->eq($this->t('otaConfNo'))}]");

            if (!$otaConfirmation
                && preg_match("/({$this->opt($this->t("otaConfNo2"))})[:\s]*([-A-z\d ]{5,})(?:\s*[,;:!?]|$)/", $http2->FindSingleNode("//text()[{$this->contains($this->t("otaConfNo2"))}]"), $m)
            ) {
                $otaConfirmation = $m[2];
                $otaConfirmationTitle = rtrim($m[1], ': ');
            }

            $nodes = $http2->XPath->query("//text()[{$this->eq($this->t('otaConfNo'))}]/ancestor::td[2]/table/ancestor::tr[1]");

            foreach ($nodes as $root) {
                //it
                if (count($confCount = $http2->FindNodes("./following-sibling::tr/descendant::td[2]", $root)) > 1) {
                    $confCount = count($confCount);

                    for ($i = 1; $i <= $confCount; $i++) {
                        $airline = $http2->FindSingleNode("following-sibling::tr[{$i}]/descendant::text()[normalize-space()][2]", $root);
                        $confNo = $http2->FindSingleNode("following-sibling::tr[{$i}]/descendant::text()[normalize-space()][1]", $root);

                        if (array_key_exists($airline, $rls)) {
                            // it-399800843.eml
                            $rls[$airline] .= ',' . $confNo;
                        } else {
                            $rls[$airline] = $confNo;
                        }
                    }
                } else {
                    $rls[$http2->FindSingleNode("following::text()[normalize-space()][2]", $root)] = $http2->FindSingleNode("following::text()[normalize-space()][1]", $root);
                }
            }
        }

        if (count($rls) === 0 && $status) {
            // it-537098880-cancelled.eml
            $confNumbers = $http2->FindNodes("//*[ count(table[normalize-space()])=2 and table[normalize-space()][1][{$this->eq($status)}] ]/table[normalize-space()][2]/descendant::tr[not(.//tr) and count(*)=2]", null, "/^[-A-z\d ]{5,}$/");

            if (count($confNumbers) > 0 && !in_array(null, $confNumbers, true)) {
                $rls['_TOTAL_'] = implode(',', $confNumbers);
            }
        }

        if (!$otaConfirmation
            && preg_match("/{$this->opt($this->t("otaConfNoSubject"))}\s*[:]+\s*(?-i)([-A-Z\d]{5,15})$/iu", $subject, $m)
        ) {
            $otaConfirmation = $m[1];
        }

        $r->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $airs = [];

        $segments = $http2->XPath->query("//*[ *[not(self::p)][1]/descendant::text()[{$xpathTime}] and *[not(self::p)][3]/descendant::text()[{$xpathTime}] ]");

        foreach ($segments as $root) {
            $airline = $http2->FindSingleNode("ancestor::table[1]/preceding-sibling::table[1]", $root, true, "/(.*?)\s+-/")
                ?? $http2->FindSingleNode("preceding::td[1]", $root, true, "/(.*?)\s+-\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])\d+\s*$/")
                ?? $http2->FindSingleNode("ancestor::td[1]/following::tr[1]", $root, true, "/(.*?)\s+-/")
            ;

            if ($airline && array_key_exists($airline, $rls)) {
                $rl = $rls[$airline];
                $airs[$rl][] = $root;
            } elseif (array_key_exists('_TOTAL_', $rls)
                && count(preg_split('/(\s*,\s*)+/', $rls['_TOTAL_'])) === $segments->length
            ) {
                // it-537098880-cancelled.eml
                $rl = $rls['_TOTAL_'];
                $airs[$rl][] = $root;
            } else {
                $airs['unknown'][] = $root;
            }
        }

        // TripNumber
        if (isset($rls['Hopper'])) {
            $tripNumber = $rls['Hopper'];
        }

        if (!empty($tripNumber)) {
            $r->ota()->confirmation($tripNumber, 'Hopper');
        }

        // TicketNumbers
        $ticketNumbers = [];
        // Ticket #: 0143736464260, 0143732463262
        $ticketNoTexts = array_filter($http2->FindNodes("//text()[{$this->starts($this->t('Ticket #:'))}]", null, "/{$this->opt($this->t('Ticket #:'))}\s+(?:TicketNumber[ ]*\()?([^)]{5,}?)[\s)]*$/"));

        foreach ($ticketNoTexts as $ticketNoText) {
            $ticketNumbers = array_merge($ticketNumbers, preg_split('/\s*,\s*/', trim($ticketNoText, ', ')));
        }

        // AccountNumbers
        $accounts = [];
        // Known Traveler #: TT11XGY9R, DT51XGY9E
        $accountNoTexts = array_filter($http2->FindNodes("//text()[{$this->starts($this->t('Known Traveler #:'))}]", null, "/{$this->opt($this->t('Known Traveler #:'))}\s+([^)]{5,}?)[\s)]*$/"));

        foreach ($accountNoTexts as $accountNoText) {
            $accounts = array_merge($accounts, preg_split('/\s*,\s*/', trim($accountNoText, ', ')));
        }

        if (count($accounts)) {
            $r->program()->accounts(array_unique($accounts), false);
        }

        // Passengers
        $travellers = $http2->FindNodes("//text()[{$this->eq($this->t('Base fare:'))}]/ancestor::tr[1]/preceding-sibling::tr[1]", null, '/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\.?$/u');
        $travellers = preg_replace("/ child\s*$/", '', $travellers);

        if (!empty($travellers)) {
            $r->general()->travellers($travellers, true);
        }

        foreach (array_unique($ticketNumbers) as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/preceding::tr[not(contains(normalize-space(), ':'))][1]");

            if (!empty($pax) && in_array($pax, $travellers)) {
                $r->issued()->ticket($ticket, false, $pax);
            } else {
                $r->issued()->ticket($ticket, false);
            }
        }

        foreach ($airs as $rl => $roots) {
            $this->logger->debug($rl);

            // TODO: need sorting flight segments by date

            foreach ($roots as $root) {
                $s = $r->addSegment();

                $airlineConfNumbers = preg_split("/(\s*,\s*)+/", $rl);

                if (count($airlineConfNumbers) > 1) {
                    // it-399800843.eml
                    $airlineConfNum = array_shift($airlineConfNumbers);
                    $rl = implode(',', $airlineConfNumbers);
                } else {
                    $airlineConfNum = $rl;
                }

                if ($airlineConfNum !== 'unknown'
                    && $http2->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][string-length(\"{$airlineConfNum}\")>10 and {$this->contains($airlineConfNum)}] and *[normalize-space()][2][{$this->eq($this->t('otaConfNo'))}] ]")->length === 0
                ) {
                    //  bad confirmation number example: 23042916827206968845982
                    // good confirmation number example: HYNU4V
                    $s->airline()->confirmation($airlineConfNum);
                }

                $xpathDateRow = "preceding-sibling::*[normalize-space()][1][{$this->starts($this->t("direction"))}]" // it-10112956.eml
                    . " or descendant-or-self::*[*[normalize-space()][2][{$this->contains($this->t("stop"))} and not({$this->contains($this->t("layover in"))})] and count(*[normalize-space()])=2]" // it-81460640.eml
                ;
                $dateStr = $http2->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[not(descendant::*[{$this->starts($this->t("Operated by"))}])][{$xpathDateRow}][1]/descendant::*[not(.//tr) and normalize-space() and ../tr][1]", $root);
                $date = empty($dateStr) ? null : strtotime($this->normalizeDate($dateStr));

                // AirlineName
                // FlightNumber
                $flight = $http2->FindSingleNode("ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::*[../self::tr][1]", $root);

                if (!empty($flight) && preg_match('/(?:^(?<airlineFull>.{2,}?)\s+[-]+\s+|^|\s)(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z\d]{4})\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                    // American Airlines - AA2258
                    $airlineFull = empty($m['airlineFull']) ? null : $m['airlineFull'];
                    $s->airline()->name($m['airline'])->number($m['flightNumber']);
                } else {
                    $airlineFull = null;
                }

                if (empty($s->getConfirmation()) && $airlineFull) {
                    // it-385819186.eml
                    $sConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("airlineConfNoLeft"))} and {$this->contains($airlineFull)} and {$this->contains($this->t("airlineConfNoRight"))}]", null, true, "/{$this->opt($this->t("airlineConfNoRight"))}[:\s]*([A-Z\d]{5,8})$/");

                    if ($sConfirmation) {
                        $s->airline()->confirmation($sConfirmation);
                    }
                }

                // DepCode
                $depCode = $http2->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)!=''][2]",
                    $root);

                if (empty($depCode)) {
                    $depCode = $http2->FindSingleNode("./ancestor::td[2]/following::td[2]/descendant::span[1]",
                        $root);
                }

                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                }

                $depName = $http2->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)!=''][1]", $root);

                if (empty($depName)) {
                    $depName = $http2->FindSingleNode("./ancestor::td[2]/following::td[2]", $root, true, "/(.+)[\s]?[A-Z]{3}/");
                }

                if (!empty($depName)) {
                    $s->departure()
                        ->name($depName);
                }
                // DepartureTerminal
                // DepDate
                if (!empty($date)) {
                    $depDate = strtotime($this->normalizeTime($http2->FindSingleNode("table[1]/descendant::text()[normalize-space()][3]", $root)), $date);

                    if (empty($depDate)) {
                        $depDate = strtotime($this->normalizeTime($http2->FindSingleNode("ancestor::td[2]/following::td[2]", $root, true, "/(\d{1,2}:\d{1,2}\s[A-z]{2})/")), $date);
                    }

                    if (!empty($depDate)) {
                        $s->departure()
                            ->date($depDate);
                    }
                }

                // ArrCode
                $arrCode = $http2->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)!=''][2]", $root);

                if (empty($arrCode)) {
                    $arrCode = $http2->FindSingleNode("./ancestor::td[2]/following::td[5]/descendant::span[1]", $root);
                }

                if (!empty($arrCode)) {
                    $s->arrival()
                        ->code($arrCode);
                }

                // ArrName
                $arrName = $http2->FindSingleNode("table[3]/descendant::text()[normalize-space()][1]", $root);

                if (empty($arrName)) {
                    $arrName = $http2->FindSingleNode("ancestor::td[2]/following::td[5]", $root, true, "/(.+)[\s]?[A-Z]{3}/");
                }

                if (!empty($arrName)) {
                    $s->arrival()
                        ->name($arrName);
                }
                // ArrDate
                if (!empty($date)) {
                    $arrDate = strtotime($this->normalizeTime($http2->FindSingleNode("table[3]/descendant::text()[normalize-space()][3]", $root)), $date);

                    if (empty($arrDate)) {
                        $arrDate = strtotime($this->normalizeTime($http2->FindSingleNode("ancestor::td[2]/following::td[5]", $root, true, "/(\d{1,2}:\d{1,2}\s[A-z]{2})/")), $date);
                    }

                    if (!empty($arrDate)) {
                        $s->arrival()
                            ->date($arrDate);
                    }
                }

                // Operator
                $operator = $http2->FindSingleNode("ancestor::table[1]/following-sibling::table[1]/descendant::text()[normalize-space()][position()<3][{$this->contains($this->t('Operated by'))}]", $root, true, "/{$this->opt($this->t('Operated by'))}[ ]+(.{2,}?)[.\s]*$/");
                $s->airline()->operator($operator, false, true);

                // Duration
                $duration = $http2->FindSingleNode("./table[2]", $root);

                if (empty($duration)) {
                    $duration = $http2->FindSingleNode("./ancestor::td[2]/following::td[3]", $root);
                }

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                // it-74445116.eml

                foreach ($r->getTravellers() as $traveller) {
                    if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber()) && !empty($traveller[0])) {
                        $seat = $http2->FindSingleNode("//text()[{$this->eq($this->t('Payment Info'))}]/following::text()[{$this->eq($this->t('Seat Selection'))}]/following::text()[{$this->contains($s->getAirlineName() . ' ' . $s->getFlightNumber())}][1]/following::text()[{$this->contains($traveller[0])} and {$this->contains($this->t("Seat"))}][1]", null, true, "/{$this->opt($this->t("Seat"))}\s*[:]+\s*(.+)$/");

                        if (empty($seat)) {
                            $travellerPart = $this->re("/^(\w+)/", $traveller[0]);
                            $seat = $http2->FindSingleNode("//text()[{$this->eq($this->t('Payment Info'))}]/following::text()[{$this->eq($this->t('Seat Selection'))}]/following::text()[{$this->contains($s->getAirlineName() . ' ' . $s->getFlightNumber())}][1]/following::text()[{$this->contains($travellerPart)} and {$this->contains($this->t("Seat"))}][1]", null, true, "/{$this->opt($this->t("Seat"))}\s*[:]+\s*(.+)$/");
                        }

                        if (!empty($seat)) {
                            $s->extra()
                                ->seat($seat, true, true, $traveller[0]);
                        }
                    }
                }
            }
        }

        // US$ 119.20    |    ₹15,205.91
        $rePrice = '/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u';
        // 183,01 €
        $rePrice2 = '/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u';

        $xpathPriceHeader = "tr[{$this->eq($this->t("Fare Details"))}]";
        $xpathTotal = "tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] and not(preceding::tr[{$this->eq($this->t("Seat Selection"))} or {$this->eq($this->t("additionalBaggage"))}]) ]";

        // Currency
        // TotalCharge
        $total = $http2->FindSingleNode("//{$xpathPriceHeader}/following::{$xpathTotal}/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if ($total !== null && (preg_match($rePrice, $total, $m) || preg_match($rePrice2, $total, $m))) {
            $currency = $this->normalizeCurrency($m['currency']);
            $currency2 = $currency === 'USD' ? preg_quote('$', '/') : null;
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));

            // BaseFare
            $baseFare = $http2->FindSingleNode("//text()[{$this->eq($this->t('Base fare:'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $matches)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?$/u', $baseFare, $matches)
            ) {
                $r->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            // Tax
            $taxes = $http2->FindSingleNode("//text()[{$this->eq($this->t('Taxes & fees:'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $matches)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?$/u', $taxes, $matches)
            ) {
                $r->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            // Fees
            $feeRows = $http2->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $http2->FindSingleNode('*[normalize-space()][2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $matches)
                    || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?$/u', $feeCharge, $matches)
                ) {
                    $feeName = $http2->FindSingleNode('*[normalize-space()][1]', $feeRow);
                    $r->price()->fee($feeName, PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }

            // Discount
            $discounts = [];
            $discountValues = array_filter($http2->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][starts-with(normalize-space(),'-')] and preceding::{$xpathPriceHeader} and following::{$xpathTotal} ]/*[normalize-space()][2]", null, '/^[-]+\s*(.*\d.*)$/'));

            foreach ($discountValues as $discount) {
                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $discount, $matches)
                    || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . $currency2 . ')?$/u', $discount, $matches)
                ) {
                    $discounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
                }
            }

            if (count($discounts) > 0) {
                $r->price()->discount(array_sum($discounts));
            }
        }

        $earned = $http2->FindSingleNode("//text()[{$this->eq($this->t('earnedPhrases'))}]/following::text()[normalize-space()][1]", null, true, '/^(?:\d[,.‘\'\d ]*?[ ]*[^\-\d)(]+|[^\-\d)(]+?[ ]*\d[,.‘\'\d ]*)$/u');

        if ($earned !== null) {
            // 1,85 €    |    US$ 4,97
            $r->ota()->earnedAwards($earned);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], 'reçu Hopper') === false // fr
            && stripos($headers['subject'], 'de Hopper') === false // es
            && stripos($headers['subject'], 'Your Hopper') === false // en
            && stripos($headers['subject'], 'with Hopper') === false // en
        ) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.hopper.com") or contains(@href,".hopper.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for using Hopper") or contains(normalize-space(),"Hopper. All rights reserved") or contains(.,"@hopper.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody($this->http) && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // $this->date = strtotime($parser->getHeader('date'));

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $junkStatuses = [];

        $bodyDocuments = $this->http->XPath->query("//node()[{$this->eq($this->t("statusVariants"))}]/ancestor::*[ descendant::text()[starts-with(normalize-space(),'Copyright') and contains(.,'Hopper')] ][1]");

        if ($bodyDocuments->length > 1) {
            // it-618219385.eml
            $this->http2 = clone $this->http;

            foreach ($bodyDocuments as $documentRoot) {
                $this->http2->SetEmailBody($this->http->FindHTMLByXpath('.', null, $documentRoot));

                $subjectTitles = ['Subject:', 'Asunto:'];
                $forwardedSubject = $this->http->FindSingleNode("ancestor-or-self::blockquote[1]/descendant::text()[{$this->eq($subjectTitles)}]/following::text()[normalize-space()][1]", $documentRoot)
                    ?? $this->http->FindSingleNode("ancestor-or-self::blockquote[1]/descendant::text()[{$this->starts($subjectTitles)}]", $documentRoot, true, "/^{$this->opt($subjectTitles)}[: ]*(.{2,})$/");

                $junkStatuses[] = $this->parseHtml($email, $this->http2, $forwardedSubject ?? $parser->getSubject());
            }
        } else {
            $junkStatuses[] = $this->parseHtml($email, $this->http, $parser->getSubject());
        }

        if (count(array_unique($junkStatuses)) === 1 && $junkStatuses[0]) {
            $email->setIsJunk(true);
        }

        $email->setType('YourReceiptAndItinerary' . ucfirst($this->lang));

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function detectBody(\HttpBrowser &$http2): bool
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($http2->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (!is_string($lang) || empty($words['statusVariants'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($words['statusVariants'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //		$year = date("Y", $this->date);
        $in = [
            // August 30, 2017
            "/^([[:alpha:]]+)[.\s]+(\d{1,2})\s*,\s*(\d{4})$/u",
            // 25 juillet 2023    |    7 de junio de 2023
            "/^(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]+)[.\s]+(?:de\s+)?(\d{4})$/iu",
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/(\d)[ ]*[Hh][ ]*(\d)/', // 01 h 55    ->    01:55
            '/(\d)[ ]*([AaPp])(?:\.[ ]*)?([Mm])\.?$/', // 2:00 p. m.    ->    2:00 PM
        ], [
            '$1:$2',
            '$1 $2$3',
        ], $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            // do not add unused currency!
            'NZD' => ['NZ$'],
            'CAD' => ['CA$'],
            'MXN' => ['MX$'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
