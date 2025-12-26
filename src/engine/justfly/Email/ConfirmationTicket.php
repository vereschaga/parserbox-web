<?php

namespace AwardWallet\Engine\justfly\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationTicket extends \TAccountChecker
{
    public $mailFiles = "justfly/it-10104558.eml, justfly/it-10113444.eml, justfly/it-10115585.eml, justfly/it-10116116.eml, justfly/it-10746582.eml, justfly/it-11652076.eml, justfly/it-11898056.eml, justfly/it-11930198.eml, justfly/it-12338933.eml, justfly/it-12366297.eml, justfly/it-3520006.eml, justfly/it-38886157.eml, justfly/it-3897144.eml, justfly/it-628475610.eml, justfly/it-8762250.eml, justfly/it-8807323.eml, justfly/it-8821976.eml, justfly/it-9769110.eml, justfly/it-9863406.eml";

    public $reBody = [
        'en' => [
            'Justfly Booking:',
            'Justfly Booking Number:',
            'With JustFly, you\'re in control of your booking',
            'JustFly wishes you a safe and enjoyable trip!',
            'Here is the information your requested about your travel itinerary',
            'Important - Your Itinerary Has Changed!',
            'Manage my Booking',
            'Thank you for booking with us',
            'shared their travel itinerary with you',
            'Pack your bags',
        ],
        'fr' => [
            'Merci d\'avoir',
            'Faites vos valises',
        ],
    ];
    public $reSubject = [
        'Your Electronic Ticket',
        'Your trip to',
        'Itinerary Update',
        'Your trip confirmation and receipt',
        'Your Itinerary Details',
        'travel itinerary with you',
        'Thanks for booking with FlightHub',
        'Confirmation and receipt for your new itinerary',
        'Notification de réservation',
        'Votre billet électronique',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Booking Number:' => [
                'Numéro de réservation FlightHub:',
                'Numéro de confirmation FlightHub:',
                'Justfly Booking:',
                'JustFly Booking:',
                'FlightHub Booking:',
                'Justfly Booking Number:',
                'JustFly Booking Number:',
                'FlightHub Booking Number:',
            ],
            'Flight '               => 'Flight ',
            'YOUR ITINERARY'        => ['YOUR ITINERARY', 'Your Itinerary', 'ITINERARY'],
            'Passenger Information' => ['Passenger Information', 'PASSENGER INFORMATION'],
            'Airline Confirmation'  => ['Airline Confirmation', 'Airline confirmation'],
            // PRICE 1 (PRICE SUMMARY ...  Total Price (per person))
            // 'Total Price (per person)' => '',
            // 'All prices are in' => '',
            // 'Base Fare' => '',
            // 'Taxes & Fees' => '',
            // 'Grand Total:' => '',
            // PRICE 2 (BILLING INFORMATION ...  Total Price (per person))
            'BILLING INFORMATION' => ['Billing Information', 'BILLING INFORMATION'],
            // 'Airfare:' => '',
            // 'Taxes & Fees:' => '',
            // PRICE 2 + PRICE 3 (RECEIPTS     Date  Receipt # ... Total:)
            'Total:'                => ['Total:', 'TOTAL:', 'Total Charged:'],
        ],
        'fr' => [
            'Passenger Information' => ['Voyageurs', 'VOYAGEURS'],
            'YOUR ITINERARY'        => ['VOTRE ITINÉRAIRE', 'Votre Itinéraire', 'ITINÉRAIRE'],
            'Direct'                => ['Départ', 'Retour'],
            'Airline Confirmation'  => 'Confirmation de la compagnie aérienne',
            'Booking Number:'       => [
                'Numéro de réservation FlightHub:',
                'Numéro de réservation FlightHub :',
                'Numéro de confirmation FlightHub:',
                'Numéro de confirmation FlightHub :',
                'Numéro de réservation Justfly:',
                'Numéro de confirmation Justfly:',
                'Numéro de réservation JustFly:',
                'Numéro de confirmation JustFly:',
            ],
            'SELECTED SEATS'      => 'SIÈGES SÉLECTIONNÉS',
            'Confirmation Number' => 'Numéro de confirmation',
            // 'RECEIPTS'            => 'REÇUS',
            // PRICE 1 (PRICE SUMMARY ...  Total Price (per person))
            // 'Total Price (per person)' => '',
            // 'All prices are in' => '',
            // 'Base Fare' => '',
            // 'Taxes & Fees' => '',
            // 'Grand Total:' => '',
            // PRICE 2 (BILLING INFORMATION ...  Total Price (per person))
            // 'BILLING INFORMATION' => ['Billing Information', 'BILLING INFORMATION'],
            // 'Airfare:' => '',
            // 'Taxes & Fees:' => '',
            // PRICE 2 + PRICE 3 (RECEIPTS     Date  Receipt # ... Total:)
            'Total:'              => ['Total:', 'Total facturé:'],

            // email
            'Total Trip Time' => ['Durée totale du voyage', 'sur les bagages'], //end segment
            'Flight '         => 'Vol ',
            'TRAVELERS'       => 'VOYAGEURS',
            'Name'            => 'Nom',
            'Economy'         => 'Economie',

            // Url
            'Duration:'       => 'Durée:',
            'Booking Status:' => 'Statut de réservation:',
            'Booked:'         => 'Réservé le',
            'Traveler'        => 'Voyageur',
            //			'Frequent Flyer' => '',
            'E-Ticket' => 'E-Ticket',
            //			'Aircraft' => '',
            'Operated by' => 'Opéré par',
            'Return'      => 'Retour',
        ],
    ];
    private $date;
    private $code;
    private static $providers = [
        'justfly' => [
            'from'      => ['noreply@justfly.com'],
            'bodyXPath' => [
                '//img[contains(@src,\'.justfly.com/images/\')] | //a[contains(@href,\'.justfly.com/\')]',
            ],
            'keyword' => 'Justfly',
            'partUrl' => [
                'en' => 'www.justfly.com/service/booking/detail/',
            ],
        ],
        'flighthub' => [
            'from'      => ['noreply@flighthub.com'],
            'bodyXPath' => [
                '//img[contains(@src,\'.flighthub.com/images/\')] | //a[contains(@href,\'.flighthub.com/\')]',
            ],
            'keyword' => 'FlightHub',
            'partUrl' => [
                'en' => 'www.flighthub.com/service/booking/detail/',
                'fr' => 'www.flighthub.com/fr/service/booking/detail/',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        $bySubj = false;

        foreach ($this->reSubject as $subj) {
            if (stripos($headers['subject'], $subj) !== false) {
                $bySubj = true;
            }
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            if (($byFrom || stripos($headers['subject'], $arr['keyword']) !== false) && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->code = $this->getProvider($parser);

        if (!$this->date = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Transaction Date:')])[1]",
            null, false, '#:\s*(.+)#')
        ) {
            $this->date = $parser->getDate();
        }

        $type = '';

        if ($this->assignLang()) {
            $this->parseEmail($email);
        } else {
            $this->logger->debug('can\'t determine a language (body)');
        }

        if (count($email->getItineraries()) === 0) {
            if (null === ($urls = $this->assignLangUrl())) {
                $this->logger->debug('can\'t determine a language(url)');

                return $email;
            }
            $type = 'Url';
            [$url, $partUrl] = $urls;
            $this->parseEmailByUrl($email, $url, $partUrl);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        if (isset($this->code)) {
            $email->setProviderCode($this->code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ((null !== $this->getProviderByBody()) && $this->detectBody()) {
            if ($this->assignLang()) {
                return true;
            }

            if (null !== $this->assignLangUrl()) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // html | link
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['bodyXPath'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ($this->http->XPath->query($search)->length > 0) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $xpathPsngs = "//tr[({$this->contains($this->t('Name'))}) and ({$this->contains($this->t('E-ticket'))}) and not(.//tr)]/following-sibling::tr[normalize-space(.)]";

        // travellers
        $pax = array_filter($this->http->FindNodes("//tr[td[{$this->contains($this->t('Passenger Information'))}]]//tr[count(td)=4][position()>1]/td[1]"));

        if (empty($pax)) {
            $pax = array_filter($this->http->FindNodes($xpathPsngs . '/td[1]'));
        }
        // ota CcnfNo
        $otaConfNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Number:'))}]/following::text()[normalize-space(.)!=''][1]");
        $otaConfNoDesc = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Number:'))}]");

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') and translate(translate(substring(normalize-space(.),string-length(normalize-space(.))-1),'APM','apm'),'apm','ddd')='dd'";
        $xpath = "//text()[$ruleTime]/ancestor::tr[count(./descendant::text()[{$this->starts($this->t('Flight '))}])=1][1]";
        $rows = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        if ($rows->length === 0) {
            $this->logger->debug('can\'t find segments in body');

            return true;
        }
        $airs = [];

        foreach ($rows as $row) {
            $rl = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('Airline Confirmation'))}])[1]/following::text()[normalize-space(.)!=''][1]",
                $row);

            if (!$rl) {
                $airline = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $row);
                $rl = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '{$airline}') and ({$this->contains($this->t('Confirmation Number'))})]/following::text()[normalize-space()!=''][1]");
            }

            if (!$rl) {
                $rl = CONFNO_UNKNOWN;
            }
            $airs[$rl][] = $row;
        }

        foreach ($airs as $rl => $roots) {
            $r = $email->add()->flight();

            if (!empty($otaConfNo)) {
                $r->ota()
                    ->confirmation($otaConfNo, trim($otaConfNoDesc, ":"));
            }

            if ($rl === CONFNO_UNKNOWN) {
                $r->general()->noConfirmation();
            } else {
                if (stripos($rl, ',') !== false) {
                    $confs = array_filter(explode(',', $rl));

                    foreach ($confs as $conf) {
                        $r->general()->confirmation($conf);
                    }
                } else {
                    $r->general()->confirmation($rl);
                }
            }

            if (count($pax) > 0) {
                $r->general()->travellers($pax, true);
            }

            $tickets = [];

            foreach ($roots as $root) {
                $s = $r->addSegment();

                $flight = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2][{$this->starts($this->t('Flight '))}]",
                    $root, false, "#{$this->opt($this->t('Flight '))}\s*(\d+)$#");
                $airline = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);
                // search tickets
                $tickets = array_unique(array_merge($tickets,
                    array_filter($this->http->FindNodes("//tr[td[{$this->contains($this->t('Passenger Information'))}]]//tr[count(td)=4][position()>1]/td[2][{$this->starts($airline)}]",
                        null, '/:\s*([\d-]+)/'))));

                if (empty($tickets)) {
                    $eTicketText = implode(' ', $this->http->FindNodes("{$xpathPsngs}/td[2]/descendant::text()[normalize-space()]"));

                    if (preg_match_all("#{$this->opt($airline)}\s*:\s*(\d{3}[- ]*\d{5,}[- ]*\d{1,2}|[A-Z\d]{6})\b#", $eTicketText, $m)) {
                        $tickets = array_unique(array_merge($tickets, $m[1]));
                    }
                }

                // airline - flight
                $s->airline()
                    ->name($airline)
                    ->number($flight);

                $terminal = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]/ancestor::td[1]/descendant::text()[{$this->contains($this->t('Terminal'))}]",
                    $root);

                if (!empty($terminal)) {
                    $s->departure()->terminal(preg_replace("#^{$this->opt($this->t('Terminal'))}\s+(.+)#", '$1', $terminal));
                }

                // departure
                // arrival
                // seats
                $node = implode("\n",
                    $this->http->FindNodes("descendant::td[not(.//td) and contains(.,')') and contains(.,'(')]/ancestor::tr[1]/descendant::text()[normalize-space()!='']",
                        $root));

                $regExp = "#(?<depDate>.+\n.+\d{1,2})\s*(?<depName>[^\n]+)\s*\(\s*(?<depCode>[A-Z]{3})\s*\)\s+(?<arrDate>.+\n.+\d{1,2})\s*(?<arrName>[^\n]+?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)#";
                $regExp2 = "#(?<depDate>.+)\n(?<depName>[^\n]+)\s*\(\s*(?<depCode>[A-Z]{3})\s*\)\s+(?<arrDate>.+)\n(?<arrName>[^\n]+?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)#s";

                if (preg_match($regExp, $node, $m) || preg_match($regExp2, $node, $m)) {
                    $s->departure()
                        ->name($m['depName'])
                        ->code($m['depCode'])
                        ->date($this->normalizeDate($m['depDate']));
                    $s->arrival()
                        ->name($m['arrName'])
                        ->code($m['arrCode'])
                        ->date($this->normalizeDate($m['arrDate']));
                    // search seats
                    $route1 = $m['depCode'] . '-' . $m['arrCode'];
                    $route2 = $m['depCode'] . 'to' . $m['arrCode'];
                    $ruleRoute = "translate(normalize-space(),' ','')='{$route1}' or translate(normalize-space(),' ','')='{$route2}'";

                    if (!empty($this->http->FindSingleNode("(//*[self::th or self::td][{$ruleRoute}])[1]"))) {
                        $cnt = $this->http->XPath->query("(//*[self::th or self::td][{$ruleRoute}])[1]/preceding-sibling::*")->length + 1;
                        $seats = array_filter($this->http->FindNodes("//*[self::th or self::td][{$ruleRoute}]/ancestor::tr[1]/following-sibling::tr/*[self::th or self::td][{$cnt}]",
                            null, "#^\d+[A-z]$#"));

                        if (!empty($seats)) {
                            $s->extra()->seats($seats);
                        }
                    }
                }

                // operator
                $operator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Operated by'))}]",
                    $root, false, "#{$this->opt($this->t('Operated by'))}\s+(.+)#");

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }

                // aircraft
                // cabin
                $aircraft = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Aircraft:'))}]",
                    $root, false, "#{$this->opt($this->t('Aircraft:'))}\s+(.+?)\s*(?:–|$)#");

                if (!empty($aircraft)) {
                    $s->extra()->aircraft($aircraft);

                    $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Aircraft:'))}]/ancestor::td[1]",
                        $root, false, "#{$aircraft}[\s\-–]+(.+)#");

                    if (!empty($cabin)) {
                        $s->extra()->cabin($cabin);
                    }
                }

                if (!$s->getCabin()) {
                    $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Economy'))}]",
                        $root);

                    if (!empty($node)) {
                        $s->extra()->cabin($node);
                    }
                }
            }

            if (!empty($tickets)) {
                $r->issued()->tickets($tickets, false);
            }
        }

        // Price
        if ($this->http->FindSingleNode("(//node()[{$this->eq($this->t('Total Price (per person)'))}])[1]")) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('All prices are in'))}]",
                null, true, "/{$this->opt($this->t('All prices are in'))}\s*([A-Z]{3})\b/");
            $headers = $this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Base Fare'))}]]/preceding-sibling::tr/*",
                null, "/^\s*(\d+)/");
            $fares = $this->http->FindNodes("//td[{$this->eq($this->t('Base Fare'))}]/ancestor::tr[1]/*",
                null, "/^\D*(\d[\W\d]*?)\D*$/");
            $taxes = $this->http->FindNodes("//td[{$this->eq($this->t('Taxes & Fees'))}]/ancestor::tr[1]/*",
                null, "/^\D*(\d[\W\d]*?)\D*$/");

            $costValue = 0.0;
            $taxValue = 0.0;

            foreach ($fares as $i => $fare) {
                $costValue += PriceHelper::parse($headers[$i] * $fare, $currency);
                $taxValue += PriceHelper::parse($headers[$i] * $taxes[$i], $currency);
            }

            if (!empty($costValue)) {
                $email->price()
                    ->cost($costValue);
            }

            if (!empty($taxValue)) {
                $email->price()
                    ->tax($taxValue);
            }
            $fNodes = $this->http->XPath->query("//tr[not(.//tr)][preceding::*[{$this->eq($this->t('Total Price (per person)'))}]][following::*[{$this->eq($this->t('Grand Total:'))}]][count(*[normalize-space()]) = 2]");

            foreach ($fNodes as $fRoot) {
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $fRoot);
                $value = PriceHelper::parse($this->http->FindSingleNode("*[normalize-space()][2]", $fRoot, true, "/^\D*(\d[\W\d]*?)\D*$/"), $currency);

                $email->price()
                    ->fee($name, $value);
            }

            $email->price()
                ->currency($currency)
                ->total(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->eq($this->t('Grand Total:'))}]/following-sibling::td",
                    null, true, "/^\D*(\d[\W\d]*?)\D*$/"), $currency));
        } elseif ($this->http->FindSingleNode("(//node()[{$this->eq($this->t('BILLING INFORMATION'))}])[1]")) {
            $currency = $this->http->FindSingleNode("//td[{$this->starts($this->t('Total:'))}]/ancestor::tr[1]",
                null, true, "/[\W\d]([A-Z]{3})(?:[\W\d]|$)/");

            $fare = $this->http->FindSingleNode("//td[{$this->eq($this->t('Airfare:'))}]/following-sibling::td",
                null, true, "/^\D*(\d[\W\d]*?)\D*$/");
            $email->price()
                ->cost(PriceHelper::parse($fare, $currency));

            $tax = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Taxes & Fees:'))}]]/following-sibling::td",
                null, true, "/^\D*(\d[\W\d]*?)\D*$/");
            $email->price()
                ->tax(PriceHelper::parse($tax, $currency));

            $total = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total:'))}]]/following-sibling::td",
                null, true, "/^\D*(\d[\W\d]*?)\D*$/");
            $email->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency)
            ;
        } else {
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::td[1]", null, false, '/.*\d.*/');

            if (preg_match('/(?:^|[\D\S])(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})\b/', $node, $m) // $1,365.30 USD
                || preg_match('/\b(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d]*)$/', $node, $m) // USD 340.50
                || preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $node, $m) // $340.50
            ) {
                $email->price()
                    ->total($this->normalizeAmount($m['amount']))
                    ->currency($this->normalizeCurrency($m['currency']));
            }
        }

        return true;
    }

    private function parseEmailByUrl(Email $email, string $url, string $partUrl)
    {
        if (!isset($this->code)) {
            $this->logger->debug('something went wrong');

            return false;
        }

        $tn = $this->re("#{$this->opt($partUrl)}(.+)#", $url);

        if (strpos($tn, '?') !== false) {
            $this->http->GetURL("https://{$partUrl}{$tn}&nr=1");
        } else {
            $this->http->GetURL("https://{$partUrl}{$tn}?nr=1");
        }

        $airs = [];
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Duration:'))}]/ancestor::div[@class='segment-wrap'][1]");

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("preceding::text()[{$this->contains($this->t('Airline Confirmation'))}][1]/following::text()[normalize-space(.)!=''][1]",
                $root);
            $airs[$rl][] = $root;
        }

        // get booking info
        $otaConfNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Number:'))}]/following::text()[normalize-space(.)!=''][1]");
        $otaConfNoDesc = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Number:'))}]");
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Status:'))}]/following::text()[normalize-space(.)!=''][1]");
        $resDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booked:'))}]",
            null, true, "#{$this->opt($this->t('Booked:'))}\s*(.+)#"));

        foreach ($airs as $rl => $nodes) {
            $r = $email->add()->flight();
            $r->ota()->confirmation($otaConfNo, trim($otaConfNoDesc, ":"));

            // general info
            $r->general()
                ->confirmation($rl)
                ->date($resDate)
                ->status($status)
                ->travellers($this->http->FindNodes("(//text()[{$this->eq($this->t('Traveler'))}])[1]/ancestor::tr[1]/following-sibling::tr/td[1]"));
//                ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Traveler'))}]/following::label[1]"));

            if ($status == 'Cancelled') {
                $r->general()->cancelled();
            }

            // accounts
            $accounts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer'))}]/following::text()[normalize-space(.)!=''][1]"),
                function ($v) {
                    if (empty($v) || trim($v) == 'None') {
                        return false;
                    }

                    return true;
                });

            if (!empty($accounts)) {
                $r->program()
                    ->account($accounts, false);
            }

            // tickets
            $direct = $this->http->FindSingleNode("//text()[normalize-space(.)='{$rl}']/ancestor::div[{$this->starts($this->t('Direct'))}][1]",
                null, true, "#^\s*(\w+)#u");

            if ($direct == $this->t('Return')) {
                $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('E-Ticket'))}]/ancestor::tr[{$this->contains($this->t('Traveler'))}]/following-sibling::tr/td[4]",
                    null, "#\b([\d\-]{7,})\b#"));
            } else {
                $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('E-Ticket'))}]/ancestor::tr[{$this->contains($this->t('Traveler'))}]/following-sibling::tr/td[3]",
                    null, "#\b([\d\-]{7,})\b#"));
            }

            if (empty($tickets) && (count($nodes) > 0)) {
                $airline = $this->http->FindSingleNode("descendant::div[@class='segment-airline-right']/text()[normalize-space()!=''][1]",
                    $nodes[0]);

                $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('E-Ticket'))}]/ancestor::tr[{$this->contains($this->t('Traveler'))}]/following-sibling::tr/td[2][{$this->contains($airline)}]",
                    null, "#\b([\d\-]{7,})\b#"));
            }

            if (empty($tickets)) {
                $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('E-Ticket'))}]/ancestor::tr[{$this->contains($this->t('Traveler'))}]/following-sibling::tr/td[2]",
                    null, "#\b([\d\-]{7,})\b#"));
            }

            if (!empty($tickets)) {
                $r->issued()
                    ->tickets(array_unique($tickets), false);
            }

            foreach ($nodes as $root) {
                $s = $r->addSegment();

                // airlineName, flightNumber
                $node = implode("\n",
                    $this->http->FindNodes("descendant::div[@class='segment-airline-right']/text()", $root));

                if (preg_match("#(.+)\s+{$this->opt($this->t("Flight "))}\s*(\d+)#", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }
                // cabin
                $s->extra()->cabin($this->http->FindSingleNode("descendant::div[@class='segment-airline-right']/span",
                    $root), true);
                // departure info
                $node = $this->http->FindSingleNode("descendant::div[@class='segment-wrap-middle']/ul[1]", $root);

                if (preg_match("#(\d+:\d+.+?\d{4})\s+(.+)\s+\(([A-Z]{3})\)\s*(?:{$this->opt($this->t('Terminal'))}\s+(\w+))?#",
                    $node, $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($m[1]))
                        ->name($m[2])
                        ->code($m[3]);

                    if (isset($m[4])) {
                        $s->departure()->terminal($m[4]);
                    }
                }
                // arrival info
                $node = $this->http->FindSingleNode("descendant::div[@class='segment-wrap-middle']/ul[2]", $root);

                if (preg_match("#(\d+:\d+.+?\d{4})\s+(.+)\s+\(([A-Z]{3})\)\s*(?:{$this->opt($this->t('Terminal'))}\s+(\w+))?#",
                    $node, $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($m[1]))
                        ->name($m[2])
                        ->code($m[3]);

                    if (isset($m[4])) {
                        $s->arrival()->terminal($m[4]);
                    }
                }

                $s->extra()
                    ->duration($this->http->FindSingleNode("descendant::div[@class='segment-wrap-right']", $root, true,
                        "#" . $this->opt($this->t("Duration:")) . "\s*([\d\shm]+)\b#i"))
                    ->aircraft($this->http->FindSingleNode("descendant::div[@class='segment-airline-right']//text()[" . $this->starts($this->t("Aircraft")) . "]",
                        $root, true, "#" . $this->opt($this->t("Aircraft")) . "\s*:\s*(.+)#"), false, true);

                $operator = $this->http->FindSingleNode("descendant::div[@class='segment-wrap-middle']//text()[" . $this->starts($this->t("Operated by")) . "]",
                    $root, true, "#:\s*(.+)#");

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }

                if ($s->getArrCode() && $s->getDepCode()) {
                    $pos = $this->http->XPath->query("//*[self::td or self::th][starts-with(normalize-space(.),'{$s->getDepCode()}') and contains(normalize-space(.),'{$s->getArrCode()}')]/preceding-sibling::*[self::td or self::th]")->length;

                    if ($pos > 1) {
                        $pos++;
                        $seats = array_filter($this->http->FindNodes("//*[self::td or self::th][starts-with(normalize-space(.),'{$s->getDepCode()}') and contains(normalize-space(.),'{$s->getArrCode()}')]/ancestor::tr[1]/following-sibling::tr/td[{$pos}]",
                            null, "#(\d+\w)#"));

                        if (!empty($seats)) {
                            $s->extra()->seats($seats);
                        }
                    }
                }
            }
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total:'))}]/following::text()[1]"));

        if (!empty($tot['Total'])) {
            if (count($email->getItineraries()) == 1) {
                $email->getItineraries()[0]->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            } else {
                $email->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = $this->re("#\b(\d{4})\b#", $this->date);
        $in = [
            /* 10:25pm
               Sun. Aug 25*/
            '#^(\d+:\d+\s*[ap]m)\s+(\w+)\.\s+(\w+)\s+(\d+)$#u',
            // 2:40pm	 Fri Dec 20 , 2019
            '#^(\d+:\d+\s*[ap]m)\s+(\w+)\s+(\w+)\s+(\d+)\s*,\s*(\d{4})$#u',
            // 7:55am	 Sam 27 Jan , 2018
            '#^(\d+:\d+\s*[ap]m)\s+(\w+)\s+(\d+)\s+(\w+)\s*,\s*(\d{4})$#u',
            // June 3, 2019
            '#(\w+)\s+(\d+),\s+(\d{4})#',
            //13 Janvier 2018
            '#^(\d+)\s+(\w+)\s+(\d{4})$#u',
        ];
        $out = [
            '$4 $3 ' . $year . ', $1',
            '$4 $3 $5, $1',
            '$3 $4 $5, $1',
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $outWeek = [
            '$2',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));

            if (empty($weeknum) && $this->lang == 'fr') {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, 'en'));
            }
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight '], $words['YOUR ITINERARY'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight '])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['YOUR ITINERARY'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangUrl()
    {
        $codes = array_keys(self::$providers);

        foreach ($codes as $code) {
            $partsUrl = self::$providers[$code]['partUrl'];

            foreach ($partsUrl as $lang => $part) {
                $url = $this->http->FindSingleNode("(//a[contains(@href,'{$part}')])[1]/@href");

                if (!empty($url)) {
                    $partUrl = $part;
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (!isset($url, $partUrl)) {
            return null;
        }

        return [$url, $partUrl];
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $tot = PriceHelper::cost($m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
