<?php

namespace AwardWallet\Engine\viarail\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "viarail/it-149465853.eml, viarail/it-1704905.eml, viarail/it-1704907.eml, viarail/it-4410284.eml, viarail/it-607656063.eml, viarail/it-6457608.eml";

    public $lang = 'en';

    public $subject;

    public static $dictionary = [
        'en' => [
            //            'BOOKING CONFIRMATION' => '',
            //            'VIA PRÉFÉRENCE:' => '',
            //            'TRAIN' => '',
            //            'From:' => '',
            //            'Departure:' => '',
            //            'To:' => '',
            //            'Arrival:' => '',
            //            'Class:' => '',
            //            'Car :' => '',
            //            'Seat :' => '',
            'FARE INFORMATION' => ['FARE INFORMATION', 'VIA PRÉFÉRENCE REWARD TICKET'],
            //            'FARE:' => '',
            //            'TOTAL:' => '',
            //            'TOTAL POINTS:' => '',
            'notReceipt' => [
                'Online train ticket purchase and booking',
                'booking your journey on the Canadian in our Prestige Sleeper class',
            ],
        ],
        'fr' => [
            'BOOKING CONFIRMATION' => 'CONFIRMATION DE RÉSERVATION',
            //            'VIA PRÉFÉRENCE:' => '',
            'TRAIN'      => 'TRAIN',
            'From:'      => 'De :',
            'Departure:' => 'Départ :',
            'To:'        => 'À :',
            'Arrival:'   => 'Arrivée :',
            'Class:'     => 'Classe :',
            //            'Car :' => '',
            //            'Seat :' => '',
            'FARE INFORMATION' => 'RENSEIGNEMENTS SUR LE TARIF',
            'FARE:'            => 'TARIF:',
            'TOTAL:'           => 'TOTAL:',
            //            'TOTAL POINTS:' => '',
            // 'notReceipt' => [''],
        ],
    ];

    private $detectFrom = '@viarail.ca';
    private $detectSubject = [
        // update detectEmailByHeaders if not contains 'VIA Rail'
        // en
        'VIA Rail Itinerary & Receipt',
        // fr
        'Itinéraire et reçu VIA Rail',
    ];

    private $detectBody = [
        'en' => ['Itinerary / receipt', 'ITINERARY / RECEIPT', 'ITINERARY #', 'Itinerary'],
        'fr' => ['ITINÉRAIRE ET REÇU', 'Itinéraire et reçu', 'ITINÉRAIRE'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        $this->subject = $parser->getSubject();

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        if (empty($headers['from']) || empty($headers['subject']))
//            return false;
//
//        if (stripos($headers['from'], $this->detectFrom) === false)
//            return false;

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".viarail.ca")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('TRAIN'))}]")->length > 0) {
            $this->parseTrainHtml($email);
        } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('BUS'))}]")->length > 0) {
            $this->parseBusHtml($email);
        }
    }

    private function parseBusHtml(Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $notReceipt = false;

        if (empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("FARE INFORMATION")) . " or  " . $this->contains($this->t("FARE:")) . " or " . $this->contains($this->t("TOTAL:")) . " or " . $this->contains($this->t("TOTAL POINTS:")) . "])[1]"))
            && $this->http->XPath->query("//*[{$this->contains($this->t('notReceipt'))}]")->length > 0
        ) {
            // мало примеров для резерваций без стоимости, возможно второе условие стоит потом пересмотреть
            $notReceipt = true;
        }

        $t = $email->add()->bus();

        // General
        $noteText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t("BOOKING CONFIRMATION"))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^({$this->opt($this->t("BOOKING CONFIRMATION"))})[:\s]+([A-Z\d]{5,})(?:\n|$)/mu", $noteText, $m)
            || preg_match("/({$this->opt($this->t('Booking Ref:'))})\s*([A-Z\d]{5,})\b/", $this->subject, $m)
        ) {
            $t->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $travellers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/ancestor::tr[1]/following-sibling::tr[1]//table[1]//td[1]", null, '/(.*?)\s*\(.*\)$/'));

        if (count($travellers) == 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Passenger :"))}]/following::ul[normalize-space()][1]/li[normalize-space()]", null, "/^[-–•*\s]*({$patterns['travellerName']})(?:\s*\(*|$)/u"));
        }

        if ($notReceipt == true && empty($travellers)) {
            $traveller = null;
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }

            if (!empty($traveller)) {
                $travellers = [$traveller];
            }
        }

        if (empty($travellers)
            && preg_match("/^{$this->opt($this->t("BOOKING CONFIRMATION"))}[:\s]+[A-Z\d]{5,}\n({$patterns['travellerName']})\s*(?:[,(]|\n|$)/u", $noteText, $m) > 0
        ) {
            $travellers = [$m[1]];
        }

        $t->general()
            ->travellers($travellers, true);

        // Program
        $accounts = $this->http->FindNodes("//text()[" . $this->contains($this->t("VIA PRÉFÉRENCE:")) . "]", null,
            "/" . $this->opt($this->t("VIA PRÉFÉRENCE:")) . "\s*([\d\*]{5,})\s*$/u");

        foreach ($accounts as $account) {
            $t->program()->account($account, (stripos($account, '*') !== false) ? true : false);
        }

        // Price
        $total = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("TOTAL:")) . "])[1]/ancestor::td[1]", null, true, "/:\s*(.+)/");
        $priceCurrency = null;

        if (preg_match('/^\s*(?<currency>[^\d\s]{1,5}?)\s*(?<amount>\d[\d., ]*?)\s*$/', $total, $matches)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?<currency>[^\d\s]{1,5}?)\s*$/', $total, $matches)
        ) {
            // $720.00
            $priceCurrency = $matches['currency'];

            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $t->price()
                ->currency($currency)
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $total2 = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("ADDITIONAL AMOUNT PAID")) . "]/following::text()[" . $this->eq($this->t("TOTAL:")) . "])[1]/ancestor::td[1]", null, true, "/:\s*(.+)/");

            if (!empty($priceCurrency)
                && (preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $total2, $matches)
                    || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $total2, $matches)
                )) {
                $t->price()
                    ->total($t->getPrice()->getTotal() + PriceHelper::parse($matches['amount'], $currencyCode));
                $t->price()
                    ->fee($this->http->FindSingleNode("//text()[" . $this->eq($this->t("ADDITIONAL AMOUNT PAID")) . "]"), PriceHelper::parse($matches['amount'], $currencyCode));
            } elseif (!empty($total2)) {
                $t->price()
                    ->total(null);
            }
        } /*elseif ($notReceipt !== true) {
            $t->price()
                ->total(null);
        }*/

        $cost = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("FARE:")) . "])[1]/ancestor::td[1]",
            null, true, "/:\s*(.+)/");

        if (!empty($priceCurrency) && preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $cost, $m)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $cost, $m)
        ) {
            $t->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
        }

        $taxValue = 0.0;
        $tax = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("G.S.T/H.S.T.:")) . "])[1]/ancestor::td[1]",
            null, true, "/:\s*(.+)/");

        if (!empty($priceCurrency) && preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $tax, $m)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $tax, $m)
        ) {
            $taxValue += PriceHelper::parse($m['amount'], $currencyCode);
        }
        $tax2 = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("P.S.T.:")) . "])[1]/ancestor::td[1]",
            null, true, "/:\s*(.+)/");

        if (!empty($priceCurrency) && preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $tax2, $m)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $tax2, $m)
        ) {
            $taxValue += PriceHelper::parse($m['amount'], $currencyCode);
        }

        if (!empty($taxValue)) {
            $t->price()->tax($taxValue);
        }

        $totalPoints = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("TOTAL POINTS:")) . "]/ancestor::td[1]", null, true, "/:\s*(\d[\d,. ]*)$/");

        if (!empty($totalPoints)) {
            $t->price()
                ->spentAwards($totalPoints);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("From:")) . "]/ancestor::tr[1][" . $this->contains($this->t("Departure:")) . "][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            // Departure
            $info = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("/" . $this->opt($this->t("From:")) . "\s+(?<name>.+)\n(?<date>.+)\n" . $this->opt($this->t("Departure:")) . "\s*(?<time>.+)/u", $info, $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])))
                    ->name($this->normalizeStation($m['name'] . ', Canada'))
                ;
            }

            // Arrival
            $info = implode("\n", $this->http->FindNodes("./following-sibling::tr[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/" . $this->opt($this->t("To:")) . "\s+(?<name>.+)\n(?<date>.+)\n" . $this->opt($this->t("Arrival:")) . "\s*(?<time>.+)/u", $info, $m)) {
                $s->arrival()
                    ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])))
                    ->name($this->normalizeStation($m['name'] . ', Canada'));
            }

            // Extra
            $info = implode("\n", $this->http->FindNodes("./preceding-sibling::tr[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^" . $this->opt($this->t("BUS")) . "\s*(?<number>[^\|\s\-]+?)\s*(?:\||-)/u", $info, $m)) {
                $s->extra()
                    ->number($m['number'])
                ;
            } elseif (preg_match("/^" . $this->opt($this->t("BUS")) . "\s*(.+?) - GO TRANSIT/u", $info, $m)) {
                $s->extra()
                    ->noNumber()
                ;
            }

            $s->extra()
                ->cabin($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2]//text()[" . $this->starts($this->t("Class:")) . "]/ancestor::td[1]", $root, true,
                    "/" . $this->opt($this->t("Class:")) . "\s*([\w ]+?)(?: - |$)/u"), true, true)
                ->seat($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2]//text()[" . $this->starts($this->t("Seat :")) . "]/ancestor::td[1]", $root, true,
                    "/" . $this->opt($this->t("Seat :")) . "\s*(\w+)\b/u"), true, true)
            ;
        }
    }

    private function parseTrainHtml(Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $notReceipt = false;

        if (empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("FARE INFORMATION")) . " or  " . $this->contains($this->t("FARE:")) . " or " . $this->contains($this->t("TOTAL:")) . " or " . $this->contains($this->t("TOTAL POINTS:")) . "])[1]"))
            && $this->http->XPath->query("//*[{$this->contains($this->t('notReceipt'))}]")->length > 0
        ) {
            // мало примеров для резерваций без стоимости, возможно второе условие стоит потом пересмотреть
            $notReceipt = true;
        }

        $t = $email->add()->train();

        // General
        $noteText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t("BOOKING CONFIRMATION"))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^({$this->opt($this->t("BOOKING CONFIRMATION"))})[:\s]+([A-Z\d]{5,})(?:\n|$)/mu", $noteText, $m)
            || preg_match("/({$this->opt($this->t('Booking Ref:'))})\s*([A-Z\d]{5,})\b/", $this->subject, $m)
        ) {
            $t->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $travellers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/ancestor::tr[1]/following-sibling::tr[1]//table[1]//td[1]", null, '/(.*?)\s*\(.*\)$/'));

        if (count($travellers) == 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Passenger :"))}]/following::ul[normalize-space()][1]/li[normalize-space()]", null, "/^[-–•*\s]*({$patterns['travellerName']})(?:\s*\(*|$)/u"));
        }

        if ($notReceipt == true && empty($travellers)) {
            $traveller = null;
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }

            if (!empty($traveller)) {
                $travellers = [$traveller];
            }
        }

        if (empty($travellers)
            && preg_match("/^{$this->opt($this->t("BOOKING CONFIRMATION"))}[:\s]+[A-Z\d]{5,}\n({$patterns['travellerName']})\s*(?:[,(]|\n|$)/u", $noteText, $m) > 0
        ) {
            $travellers = [$m[1]];
        }

        $t->general()
            ->travellers($travellers, true);

        // Program
        $accounts = $this->http->FindNodes("//text()[" . $this->contains($this->t("VIA PRÉFÉRENCE:")) . "]", null,
            "/" . $this->opt($this->t("VIA PRÉFÉRENCE:")) . "\s*([\d\*]{5,})\s*$/u");

        foreach ($accounts as $account) {
            $t->program()->account($account, (stripos($account, '*') !== false) ? true : false);
        }

        // Price
        $total = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("TOTAL:")) . "])[1]/ancestor::td[1]", null, true, "/:\s*(.+)/");
        $priceCurrency = null;

        if (preg_match('/^\s*(?<currency>[^\d\s]{1,5}?)\s*(?<amount>\d[\d., ]*?)\s*$/', $total, $matches)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?<currency>[^\d\s]{1,5}?)\s*$/', $total, $matches)
        ) {
            // $720.00
            $priceCurrency = $matches['currency'];

            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $t->price()
                ->currency($currency)
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $total2 = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("ADDITIONAL AMOUNT PAID")) . "]/following::text()[" . $this->eq($this->t("TOTAL:")) . "])[1]/ancestor::td[1]", null, true, "/:\s*(.+)/");

            if (!empty($priceCurrency)
                && (preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $total2, $matches)
                    || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $total2, $matches)
                )) {
                $t->price()
                    ->total($t->getPrice()->getTotal() + PriceHelper::parse($matches['amount'], $currencyCode));
                $t->price()
                    ->fee($this->http->FindSingleNode("//text()[" . $this->eq($this->t("ADDITIONAL AMOUNT PAID")) . "]"), PriceHelper::parse($matches['amount'], $currencyCode));
            } elseif (!empty($total2)) {
                $t->price()
                    ->total(null);
            }
        }

        $cost = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("FARE:")) . "])[1]/ancestor::td[1]",
            null, true, "/:\s*(.+)/");

        if (!empty($priceCurrency) && preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $cost, $m)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $cost, $m)
        ) {
            $t->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
        }

        $taxValue = 0.0;
        $tax = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("G.S.T/H.S.T.:")) . "])[1]/ancestor::td[1]",
            null, true, "/:\s*(.+)/");

        if (!empty($priceCurrency) && preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $tax, $m)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $tax, $m)
        ) {
            $taxValue += PriceHelper::parse($m['amount'], $currencyCode);
        }
        $tax2 = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("FARE INFORMATION")) . "]/following::text()[" . $this->eq($this->t("P.S.T.:")) . "])[1]/ancestor::td[1]",
            null, true, "/:\s*(.+)/");

        if (!empty($priceCurrency) && preg_match('/^\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*(?<amount>\d[\d., ]*?)\s*$/', $tax2, $m)
            || preg_match('/^\s*(?<amount>\d[\d., ]*?)\s*(?:' . preg_quote($priceCurrency, '/') . ')?\s*$/', $tax2, $m)
        ) {
            $taxValue += PriceHelper::parse($m['amount'], $currencyCode);
        }

        if (!empty($taxValue)) {
            $t->price()->tax($taxValue);
        }

        $totalPoints = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("TOTAL POINTS:")) . "]/ancestor::td[1]", null, true, "/:\s*(\d[\d,. ]*)$/");

        if (!empty($totalPoints)) {
            $t->price()
                ->spentAwards($totalPoints);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("From:")) . "]/ancestor::tr[1][" . $this->contains($this->t("Departure:")) . "][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            // Departure
            $info = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("/" . $this->opt($this->t("From:")) . "\s+(?<name>.+)\n(?<date>.+)\n" . $this->opt($this->t("Departure:")) . "\s*(?<time>.+)/u", $info, $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])))
                    ->name($this->normalizeStation($m['name'] . ', CA'))
                    ->geoTip('Canada')
                ;
            }

            // Arrival
            $info = implode("\n", $this->http->FindNodes("./following-sibling::tr[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/" . $this->opt($this->t("To:")) . "\s+(?<name>.+)\n(?<date>.+)\n" . $this->opt($this->t("Arrival:")) . "\s*(?<time>.+)/u", $info, $m)) {
                $s->arrival()
                    ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])))
                    ->name($this->normalizeStation($m['name'] . ', CA'))
                    ->geoTip('Canada')
                ;
            }

            // Extra
            $info = implode("\n", $this->http->FindNodes("./preceding-sibling::tr[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^" . $this->opt($this->t("TRAIN")) . "\s*(?<number>[^\|\s\-]+?)\s*(?:\||-)/u", $info, $m)) {
                $s->extra()
                    ->number($m['number'])
                ;
            } elseif (preg_match("/^" . $this->opt($this->t("TRAIN")) . "\s*(.+?) - GO TRANSIT/u", $info, $m)) {
                $s->extra()
                    ->noNumber()
                    ->service($m[1])
                ;
            }

            $s->extra()
                ->cabin($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2]//text()[" . $this->starts($this->t("Class:")) . "]/ancestor::td[1]", $root, true,
                    "/" . $this->opt($this->t("Class:")) . "\s*([\w ]+?)(?: - |$)/u"), true, true)
                ->car($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2]//text()[" . $this->starts($this->t("Car :")) . "]/ancestor::td[1]", $root, true,
                    "/" . $this->opt($this->t("Car :")) . "\s*(\w+)\b/u"), true, true)
                ->seat($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2]//text()[" . $this->starts($this->t("Seat :")) . "]/ancestor::td[1]", $root, true,
                    "/" . $this->opt($this->t("Seat :")) . "\s*(\w+)\b/u"), true, true)
            ;
        }
    }

    private function detectBody(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function normalizeTime(?string $s): string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1]; // 21:51 PM    ->    21:51
        }
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25

        return $s;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $in = [
            // Sun. Mar 7, 2021
            "/^\s*[-[:alpha:]]+[.\s]+([[:alpha:]]+)[.\s]+(\d{1,2})\s*,\s*(\d{4})\s*$/u",
            // Ven. 21 févr. 2020
            "/^\s*[-[:alpha:]]+[.\s]+(\d{1,2})[.\s]+([[:alpha:]]+)\s*\.?\s+(\d{4})\s*$/u",
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug($str);
        if (preg_match("/\d\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'CAD' => ['$'],
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

    private function normalizeStation(string $text): string
    {
        $text = str_replace(['É', 'é', 'Ô', 'ô'], ['E', 'e', 'O', 'o'], $text);

        if (empty($text)) {
            return '';
        }

        // https://www.viarail.ca/en/explore-our-destinations/find-a-train-station
        $stations = [
            'Ontario, London train station'        => ['London'],
            'Ontario, Kingston train station'      => ['Kingston'],
            'Ontario, Guelph train station'        => ['Guelph'],
            'Ontario, Fallowfield train station'   => ['Fallowfield'],
            'Ontario, Glencoe train station'       => ['Glencoe'],
            'Ontario, Windsor train station'       => ['Windsor'],
            'Ontario, Oakville train station'      => ['Oakville'],
            'Ontario, Oshawa train station'        => ['Oshawa'],
            'Ontario, Ottawa train station'        => ['Ottawa'],
            'Ontario, Aldershot train station'     => ['Aldershot'],
            'Quebec City'                          => ['Quebec'],
            'Quebec, Montreal train station'       => ['Montreal'],
            'Quebec, Dorval train station'         => ['Dorval'],
            'Quebec, Sainte-Foy train station'     => ['Sainte-Foy'],
            'New Brunswick, Moncton train station' => ['Moncton'],
            'Nova Scotia, Halifax train station'   => ['Halifax'],
        ];

        foreach ($stations as $name => $phrases) {
            foreach ($phrases as $phrase) {
                if (strcasecmp($phrase, $text) === 0) {
                    return $name;
                }
            }
        }

        return $text;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
