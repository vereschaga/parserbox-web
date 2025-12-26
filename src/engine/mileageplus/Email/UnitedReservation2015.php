<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class UnitedReservation2015 extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-16003905.eml, mileageplus/it-3131278.eml, mileageplus/it-3132662.eml, mileageplus/it-3279118.eml, mileageplus/it-3322156.eml, mileageplus/it-43178871.eml, mileageplus/it-4533850.eml, mileageplus/it-4535264.eml, mileageplus/it-4588195.eml, mileageplus/it-4601003.eml, mileageplus/it-5762645.eml, mileageplus/it-65466270.eml, mileageplus/it-66396827.eml, mileageplus/it-72505224.eml";

    public $reSubject = [
        "en"  => "Your United reservation for",
        "en2" => "united.com reservation for",
        "en3" => "Your flight cancellation is complete",
        "en4" => "Your flight cancellation is confirmed",
        "pt"  => "Sua reserva da United para",
        "fr"  => "réservation united.com pour",
        "de"  => "united.com-Reservierung für",
        "de2" => "Ihre United-Reservierung für",
        "es"  => "Su reservación de United para",
        "zh"  => "您預訂前往",
        "zh2" => "您的美联航预订航班",
    ];

    public $reBody2 = [
        "pt"=> "Resumo da viagem",
        "ja"=> "旅程表",
        "fr"=> "Récapitulatif du voyage",
        "es"=> "Resumen de viaje",
        "de"=> "Reise-Zusammenfassung",
        "zh"=> ["旅程摘要", "旅行摘要"],
        // en-detects should be in the end
        "en"=> [
            "Trip summary", "Flight itinerary for", "Thank you for choosing United",
            "was cancelled and we have received your request", "was canceled and we have received your request",
            ", was canceled. We have received your request for refund",
        ],
    ];

    public static $dictionary = [
        "en" => [
            "Operated by"     => ["Operated by", "Operated By"],
            "ACCOUNTS"        => ["Frequent flyer:"],
            "Email address:"  => ["Email address:", "Home phone:"],
            "statusVariants"  => ["cancelled", "canceled", "cancellation is confirmed", "cancelation is confirmed"],
            "cancelledPhrases"=> [
                ", was cancelled and we have received your request for a refund",
                ", was canceled and we have received your request for a refund",
                "Your flight cancellation is confirmed", "Your flight cancelation is confirmed",
                ", was canceled. We have received your request for refund",
            ],
        ],
        "pt" => [
            "Confirmation number:"=> "Número de confirmação:",
            "Travelers"           => "Passageiros",
            "Email address:"      => "Endereço eletrônico:",
            "Total"               => "Total",
            "Taxes and fees"      => "Impostos e taxas",
            "Operated by"         => "Operado por",
            "Distance"            => "de distância",
            "Duration:"           => "Duração:",
            "to"                  => "para",
            // "ACCOUNTS" => "",
            //			"statusVariants"=>[""],
            //			"cancelledPhrases"=>[""],
        ],
        "ja" => [
            "Confirmation number:"=> "ご予約番号：",
            "Travelers"           => "名様",
            "Email address:"      => "メールアドレス：",
            "Total"               => "合計",
            "Taxes and fees"      => "税金および手数料",
            "Operated by"         => ["運航航空会社：", "運航"],
            "Distance"            => "飛行距離",
            "Duration:"           => ["飛行時間：", "期間："],
            "to"                  => "－",
            "ACCOUNTS"            => "フリークエントフライヤー：",
            //			"statusVariants"=>[""],
            //			"cancelledPhrases"=>[""],
        ],
        "fr" => [
            "Confirmation number:"=> "Numéro de confirmation :",
            "Travelers"           => "Voyageurs",
            "Email address:"      => "Adresse électronique :",
            "Total"               => "Total",
            "Taxes and fees"      => "Taxes et frais",
            "Operated by"         => ["Exploité par", "Opéré par"],
            "Distance"            => "NOTTRANSLATED",
            "Duration:"           => "Durée :",
            "to"                  => "à destination de",
            "ACCOUNTS"            => "Voyageur fréquent :",
            //			"statusVariants"=>[""],
            //			"cancelledPhrases"=>[""],
        ],
        "es" => [
            "Confirmation number:"=> "Número de confirmación:",
            "Travelers"           => "Pasajeros",
            "Email address:"      => "Dirección de correo electrónico:",
            "Total"               => "Total",
            "Taxes and fees"      => "Impuestos y cargos",
            "Operated by"         => "Operados por",
            "Distance"            => "NOTTRANSLATED",
            "Duration:"           => "Duración:",
            "to"                  => "hacia",
            "ACCOUNTS"            => "Viajero frecuente:",
            //			"statusVariants"=>[""],
            //			"cancelledPhrases"=>[""],
        ],
        "de" => [
            "Confirmation number:"=> "Bestätigungsnummer:",
            "Travelers"           => "Reisende",
            "Email address:"      => "E-Mail-Adresse:",
            "Total"               => "Gesamtsumme",
            "Taxes and fees"      => "Steuern und Gebühren",
            "Operated by"         => "Durchgeführt von",
            "Distance"            => "NOTTRANSLATED",
            "Duration:"           => "Dauer:",
            "to"                  => "nach",
            "ACCOUNTS"            => "Vielflieger:",
            "plane"               => "Flugzeug",
            //			"statusVariants"=>[""],
            //			"cancelledPhrases"=>[""],
        ],
        "zh" => [
            "Confirmation number:"=> "確認號碼：",
            "Travelers"           => "旅客",
            "Email address:"      => "電郵地址：",
            "Total"               => "總計",
            "Taxes and fees"      => "稅項與手續費",
            "Operated by"         => ["營運者"],
            "Distance"            => "NOTTRANSLATED",
            "Duration:"           => ["期間：", "时长："],
            "to"                  => "至",
            "ACCOUNTS"            => "飛行常客:",
            //            "plane"               => "",
            //			"statusVariants"=>[""],
            //			"cancelledPhrases"=>[""],
        ],
    ];

    public $lang = "en";

    private $date;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@united.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['from'], 'notifications@united.com') === false
            && strpos($headers['subject'], 'United ') === false
            && stripos($headers['subject'], 'united.com ') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".united.com/") or contains(@href,"www.united.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"United Airlines, Inc.") or contains(.,"@united.com") or contains(.,"@news.united.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        $this->assignLang();

        $this->parseHtml($email, $parser->getSubject());

        $email->setType('UnitedReservation2015' . ucfirst($this->lang));

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

    private function parseHtml(Email $email, string $subject): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
            'railStation'   => '/(.*?)\s+\([A-Z]{3}\s*-\s*Rail\s*Station/i', // Philadelphia, PA, US (ZFV - Rail Station)
            'airport'       => '/(.*?)\s+\(([A-Z]{3})/', // Houston, TX, US (IAH - Intercontinental)
        ];

        $f = $email->add()->flight();

        // RecordLocator
        $confirmation = $this->nextText($this->t("Confirmation number:"));

        if (empty($confirmation) && 0 === $this->http->XPath->query("//a[contains(normalize-space(.), 'Manage reservation')]")->length) {
            $f->general()
                ->noConfirmation();
        } elseif (empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*(.+)/"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confirmation);
        }

        // Passengers
        $passengersXpath = "//text()[{$this->eq($this->t("Travelers"))}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[{$this->contains($this->t("Email address:"))} or .//td[1][not(.//tr)]/following-sibling::td[1]/table]";
        $travellers = array_filter($this->http->FindNodes($passengersXpath . "/descendant::text()[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) === 0) {
            // ???
            $passengersXpath = "//table[{$this->eq($this->t("Travelers"))}]/following-sibling::table//tr[{$this->contains($this->t("Email address:"))} and not({$this->starts($this->t("Email address:"))})]";
            $travellers = array_filter($this->http->FindNodes($passengersXpath . "/descendant::text()[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));
        }

        if (count($travellers) === 0) {
            // it-72505224.eml
            $travellersText = implode(', ', $this->http->FindNodes("//h2[{$this->eq($this->t("Travelers"))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", null, "/^[[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]]$/u"));

            if ($travellersText) {
                $travellers = preg_split('/[,\s]*,[,\s]*/', $travellersText);
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        // AccountNumbers
        $accountRows = $this->http->XPath->query("//text()[{$this->eq($this->t("ACCOUNTS"))}]/ancestor::tr[1]");

        foreach ($accountRows as $accRow) {
            $travellerName = $this->http->FindSingleNode("ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][last()]/descendant::*[count(*[normalize-space()])=2][1]/*[normalize-space()][1]", $accRow, true, "/^{$patterns['travellerName']}$/u");
            $account = $this->http->FindSingleNode(".", $accRow, true, "/^{$this->opt($this->t("ACCOUNTS"))}[:：]*([-A-Z\d* ]{5,})$/u");

            if ($account) {
                $f->program()->account($account, strpos($account, '*') !== false, $travellerName);
            }
        }

        $total = $this->nextText($this->t("Total"));
        $total2 = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t("Total"))}])[1]/following::text()[normalize-space(.)][2][starts-with(normalize-space(), '+')]",
            null, true, '/^\s*\+\s*(.+)$/');
        // SpentAwards
        if (stripos($total, 'miles') !== false) {
            $f->price()
                ->spentAwards($total);
            $total = $total2;
        } elseif (stripos($total2, 'miles') !== false) {
            $f->price()
                ->spentAwards($total2);
        }

        // TotalCharge
        // Tax
        if (preg_match('/^\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $matches)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/', $total, $matches)
        ) {
            // ₩399,900    |    + $319.42    |    202,73 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $tax = $this->nextText($this->t("Taxes and fees"));

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $tax, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $tax, $m)
            ) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $xpath = "//img[contains(@src, 'icons/plane.png') or @alt='plane' or @alt='Image removed by sender. " . $this->t("plane") . "' or @alt='mage removed by sender. " . $this->t("plane") . "']/ancestor::tr[1][count(./td)=4]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[contains(translate(.,'0123456789','dddddddddd'), 'd:dd')]//ancestor::tr[1][count(./td[normalize-space()!='']) = 4 or count(./td[normalize-space()!='']) = 3]";
            $segments = $this->http->XPath->query($xpath);
        }

        // $this->logger->debug($xpath);
        $flightsRoute = [];

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]", $root));

            if ($dateinfo = $this->http->FindSingleNode("./following-sibling::tr[2]//text()[" . $this->starts("Depart ") . "]", $root, true, "#:\s*(.+)#")) {
                $date = $this->normalizeDate($dateinfo);
            }

            if (empty($date) && $dateinfo = $this->http->FindSingleNode("./preceding-sibling::tr[1]/preceding::text()[normalize-space(.)][1]", $root, true, "#.*\b\d{4}\b.*#")) {
                $date = $this->normalizeDate($dateinfo);
            }

            if (empty($date) && $dateinfo = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[normalize-space()][1]", $root, true, "#.*\b\d{4}\b.*#")) {
                $date = $this->normalizeDate($dateinfo);
            }

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode("./preceding-sibling::tr[string-length(normalize-space()) > 1][1]/td[1]/descendant::td[normalize-space()][1]", $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $s->airline()
                        ->name($matches['airline'])
                        ->number($matches['flightNumber']);
                }
            }

            $xpathFragment1 = '/descendant::*[ ./following-sibling::*[normalize-space(.)] ][1]/following-sibling::*[normalize-space(.)][1]';

            // DepName
            // DepCode
            $airportDep = $this->http->FindSingleNode('./td[1]' . $xpathFragment1, $root);
            //it-65466270
            if (preg_match("/^[\d\:]+\s*a?p?m$/", $airportDep)) {
                $xpathFragment1 = '/descendant::*[ ./following-sibling::*[normalize-space(.)] ][1]/following-sibling::*[normalize-space(.)][2]';
                $airportDep = $this->http->FindSingleNode('./td[1]' . $xpathFragment1, $root);
            }

            if (preg_match($patterns['railStation'], $airportDep, $matches)) {
                $s->departure()
                    ->name($matches[1])
                    ->noCode();
            } elseif (preg_match($patterns['airport'], $airportDep, $matches)) {
                $s->departure()
                    ->name($matches[1])
                    ->code($matches[2]);
            }

            // DepDate
            $time = $this->normalizeTime($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root));

            if (preg_match("#((\d+):\d+)\s*[apm]#i", $time, $m) && $m[2] > 12) {
                $time = $m[1];
            }

            if (is_numeric($date)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            // ArrName
            // ArrCode
            $airportArr = $this->http->FindSingleNode('./td[3]' . $xpathFragment1, $root);

            if (empty($airportArr)) {
                $airportArr = $this->http->FindSingleNode('./td[normalize-space()][2]' . $xpathFragment1, $root);
            }

            if (preg_match($patterns['railStation'], $airportArr, $matches)) {
                $s->arrival()
                    ->name($matches[1])
                    ->noCode();
            } elseif (preg_match($patterns['airport'], $airportArr, $matches)) {
                $s->arrival()
                    ->name($matches[1])
                    ->code($matches[2]);
            }

            // ArrDate
            $time = $this->normalizeTime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root));

            if (empty($time)) {
                $time = $this->http->FindSingleNode('./td[normalize-space()][2]/descendant::text()[normalize-space(.)][1]', $root);
            }

            if (preg_match("#((\d+):\d+)\s*[apm]#i", $time, $m) && $m[2] > 12) {
                $time = $m[1];
            }

            if (is_numeric($date)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $flightsRoute[] = $s->getDepCode() . ' ' . $this->t('to') . ' ' . $s->getArrCode();
            }

            // Operator
            $operator = $this->http->FindSingleNode("preceding-sibling::tr[1]//text()[{$this->starts($this->t('Operated by'))}]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.{2,}?)(?:\s+DBA\b|$)/i");
            $s->airline()->operator($operator, false, true);

            // TraveledMiles
            $miles = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Distance")) . "]", $root, true, "#([\d\.\,]+)#");

            if (!empty($miles)) {
                $s->extra()
                    ->miles($miles);
            }

            // Cabin
            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $codesVariants = [];

                foreach ((array) $this->t('to') as $phrase) {
                    $codesVariants[] = $s->getDepCode() . ' ' . $phrase . ' ' . $s->getArrCode();
                    $codesVariants[] = $s->getDepCode() . $phrase . $s->getArrCode();
                }

                $cabinValues = $this->http->FindNodes($passengersXpath . "//text()[{$this->eq($codesVariants)}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][2]");

                if (count(array_unique($cabinValues)) !== 1) {
                    $cabin = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration:"))}]/following::text()[normalize-space()][1]", $root, true, "/^(.+?)\s*\(\s*[A-Z]{1,2}\s*\)/");
                } else {
                    $cabin = $cabinValues[0];
                }

                $s->extra()->cabin($cabin, false, true);

                // Seats
                $xpathTraveller = "ancestor::table[1]/ancestor::tr[ *[normalize-space()][2] ][1]/*[normalize-space()][1]";
                $xpathSeat = "following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]";

                $seatRows = $this->http->XPath->query("//*[{$this->eq($this->t("Travelers"))}]/following-sibling::*//tr["
                    . "({$this->contains($this->t("Email address:"))} or {$this->contains($codesVariants)})"
                    . " and not({$this->starts($this->t("Email address:"))} or {$this->starts($codesVariants)})]"
                    . "/descendant::text()[{$this->eq($codesVariants)}]/ancestor::td[1]"
                );

                foreach ($seatRows as $seatRow) {
                    $travellerName = $this->http->FindSingleNode($xpathTraveller, $seatRow, true, "/^{$patterns['travellerName']}$/u");
                    $seat = $this->http->FindSingleNode($xpathSeat, $seatRow, true, "/^\d+[A-z]$/");

                    if ($seat) {
                        $s->extra()->seat($seat, false, true, $travellerName);
                    }
                }

                if (empty($s->getSeats())) {
                    // ???
                    $seatRows = $this->http->XPath->query($passengersXpath . "//text()[{$this->eq($codesVariants)}]/ancestor::td[1]");

                    foreach ($seatRows as $seatRow) {
                        $travellerName = $this->http->FindSingleNode($xpathTraveller, $seatRow, true, "/^{$patterns['travellerName']}$/u");
                        $seat = $this->http->FindSingleNode($xpathSeat, $seatRow, true, "/^\d+\w$/");

                        if ($seat) {
                            $s->extra()->seat($seat, false, true, $travellerName);
                        }
                    }
                }
            }

            // BookingClass
            $s->extra()->bookingCode($this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration:"))}]/following::text()[normalize-space()][1]", $root, true, "/\(\s*([A-Z]{1,2})\s*\)/"));

            // Duration
            $s->extra()->duration($this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration:"))}]", $root, true, "/{$this->opt($this->t("Duration:"))}[:\s]*(\d.+)/u"));
        }

        if (empty($f->getTravellers())) {
            $travellers = $this->http->FindNodes("//*[" . $this->eq($this->t("Travelers")) . "]/following-sibling::*//tr[(" . $this->contains($this->t("Email address:")) . " or " . $this->contains($flightsRoute) . ") and not(" . $this->starts($this->t("Email address:")) . " or " . $this->starts($flightsRoute) . ")]/descendant::text()[normalize-space()][1]/ancestor::td[1]");

            if (!empty($travellers)) {
                $f->general()->travellers($travellers);
            }
        }

        $statusText = null;

        if (($statusText = $this->http->FindSingleNode("//text()[{$this->contains($this->t("cancelledPhrases"))}]"))
            || ($statusText = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Your reservation')][1]/following-sibling::text()[contains(normalize-space(),'was cancelled') or contains(normalize-space(),'was canceled')]"))
        ) {
            // it-43178871.eml
            $f->general()->cancelled();
        } elseif (preg_match("/{$this->opt($this->t("cancelledPhrases"))}/", $subject)
            && $this->http->XPath->query("//node()[{$this->eq($this->t("Confirmation number:"))}] | //a[contains(@href,'.united.com/') and contains(@href,'utm_source=FFC_Cancel')]")->length > 0
        ) {
            // it-72505224.eml
            $f->general()->cancelled();
            $statusText = $subject;
        }

        if (preg_match("/\b({$this->opt($this->t("statusVariants"))})\b/", $statusText, $m)) {
            $f->general()->status($m[1]);
        }
    }

    private function assignLang(): bool
    {
        if (!isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function normalizeTime(?string $str): string
    {
        $in = [
            "#^(\d+:\d+) ([ap])\.m\.$#", //8:24 p.m.
            "#^(\d+:\d+ [ap]m)#", //16:15 pm
        ];
        $out = [
            "$1 $2m",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^([^\s\d]+),\s+([^\s\d\,\.]+)\s+(\d+)$#", //mer, 31 mai
            "#^([^\s\d]+),\s+(\d+)\s+([^\s\d\,\.]+)$#", //mer, mai 31
            "#^[^\s\d\,\.]+\.?, ([^\s\d\,\.]+)\.? (\d+), (\d{4})$#", //Sun, Oct 11, 2015
            "#^[^\s\d\,\.]+\.?, (\d+) ([^\s\d\,\.]+)\.?, (\d{4})$#", //sex, 26 fev, 2016
            "#^[^\s\d\,\.]+, (\d{4}) (\d+)月 (\d+)$#", //日, 2016 3月 13
            "#^[^\s\d\,\.]+,\s*(\d{4}) ([^\s\d\,\.]+) (\d{1,2})$#", //Wed, 2018 Jul 04
            "#^\s*[^\s\d\,\.]+,\s*(\d+)\s+(\d+)月[\s,]+(\d{4})\s*$#u", //周二, 29 11月, 2022
        ];
        $out = [
            "$3 $2 $year",
            "$2 $3 $year",
            "$2 $1 $3",
            "$1 $2 $3",
            "$3.$2.$1",
            "$3 $2 $1",
            "$1.$2.$3",
        ];
        $outWeek = [
            '$1',
            '$1',
            '',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $str))) {
            try {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));

                if (empty($weeknum)) {
                    $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, 'en'));
                }
                $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));
                $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));
        }

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang))) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs'],
            'TWD' => ['TW $'],
            'EUR' => ['€'],
            'BRL' => ['R$'],
            'HKD' => ['HK $'],
            'GBP' => ['£'],
            'JPY' => ['円'],
            'KRW' => ['₩'],
            'CAD' => ['CA $'],
            'ARS' => ['AR $'],
            'USD' => ['US $'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function nextText($field, $root = null): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
}
