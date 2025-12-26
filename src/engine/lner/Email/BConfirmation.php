<?php

namespace AwardWallet\Engine\lner\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BConfirmation extends \TAccountChecker
{
    public $mailFiles = "lner/it-10761434.eml, lner/it-10987981.eml, lner/it-115113637.eml, lner/it-168523356.eml, lner/it-1702150.eml, lner/it-1782469.eml, lner/it-1907135.eml, lner/it-2240390.eml, lner/it-2567675.eml, lner/it-2994056.eml, lner/it-3130587.eml, lner/it-73960242.eml, lner/it-8196284.eml, lner/it.eml";

    public static $detectProvider = [
        'cssc' => [
            'from' => 'crosscountry@trainsfares.co.uk',
            'body' => ['crosscountrytrains.', 'CrossCountry'],
        ],
        'lner' => [
            'from' => 'virgintrains@trainsfares.co.uk',
            'body' => ['virgintrains.'],
        ],
        'northern' => [
            'from' => 'northern@trainsfares.co.uk',
            'body' => ['northernrailway.co.uk'],
        ],
        'greang' => [
            'from' => 'greateranglia@trainsfares.co.uk',
            'body' => ['greateranglia.', 'Greater Anglia'],
        ],
        'hoggrob' => [
            'from' => 'eyrail.uk@trainsfares.co.uk',
            'body' => ['eyrail.uk@hrgworldwide.com'],
        ],
        'thetrainline' => [
            'from' => 'thetrainline.com',
            'body' => ['thetrainline', 'ScotRail'],
        ],
        'awc' => [
            'from' => 'avantiwestcoast@trainsfares.co.uk',
            'body' => ['.avantiwestcoast.co.uk', 'Avanti West Coast'],
        ],
    ];

    public $detectSubject = [
        'en' => 'Your Booking Confirmation',
        'Rail booking confirmation', //Rail booking confirmation 2436694264, Collection ref 4H3F5BWF
    ];

    public $detectBody = [
        'en' => ['Journey Information', 'Journey 1:'],
    ];

    public $emailSubject;
    public $lang = 'en';

    public static $dict = [
        'en' => [
            "Dear"           => ["Dear", "Hello"],
            "Transaction Id" => ["Transaction Id", "Transaction id", "Transaction Id:", "Transaction ID", "Booking Reference:"],
            //            "Collection reference:" => "",
            "Total amount paid:" => ["Total amount paid:", "Total amount:"],
            "notTrain"           => ["Tube (Unknown Service Provider)", "Foot (Unknown Service Provider)", 'Walk'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $this->http->Response['body'];

        foreach (self::$detectProvider as $code => $detects) {
            if (isset($detects['body'])
                    && $this->striposAll($text, $detects['body']) !== false
                    && $this->assignLang() == true
                    ) {
                $this->providerCode = $this->providerCode ?? $code;

                break;
            }
        }
        $this->emailSubject = $parser->getSubject();

        $this->parseHtml($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detects) {
                if (stripos($text, $detects['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        foreach (self::$detectProvider as $code => $detects) {
            if (isset($detects['body'])
                    && ($this->striposAll($text, $detects['body']) !== false || $this->http->XPath->query("//a[" . $this->contains($detects['body'], '@href') . "]")->length > 0)
                    && $this->assignLang() == true
                    ) {
                $this->providerCode = $this->providerCode ?? $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundCompany = false;

        if (stripos($headers["from"], '@trainsfares') !== false) {
            $foundCompany = true;

            foreach (self::$detectProvider as $code => $detects) {
                if (isset($detects['from']) && stripos($headers["from"], $detects['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@trainsfares.') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProvider));
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $t = $email->add()->train();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Transaction Id")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Transaction Id")) . "]", null, true, "#:\s*([A-Z\d]{5,})\s*[.]?\s*$#");
        }

        if (empty($conf) && !empty($this->emailSubject)) {
            if (preg_match("#(?:Your Booking Confirmation|Rail booking confirmation)\s*(\d{5,})\b#", $this->emailSubject, $m)) {
                $conf = $m[1];
            }
        }
        $t->general()->confirmation($conf);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Traveller:"))}]/following::text()[normalize-space()][1]", null, true, "/^\s*({$patterns['travellerName']})\s*$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Traveller:"))}]", null, true, "/:\s*({$patterns['travellerName']})\s*$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Dear"))}]", null, true, "/{$this->preg_implode($this->t("Dear"))}\s+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u")
        ;

        if (!empty($traveller) && !preg_match("/\bCustomer\b/i", $traveller)) {
            $t->general()->traveller($traveller);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total amount paid:")) . "]/following::text()[normalize-space(.)][1]");

        if ((preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $total, $m))
                && ($m['curr'] !== '?')
        ) {
            $t->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $xpath = "//*[(self::td or self::th) and " . $this->eq($this->t("Departs")) . "]/ancestor::tr[1][*[2][" . $this->eq($this->t("Arrives")) . "]]/following::tr[1]/ancestor::*[1]/tr[not(" . $this->starts($this->t("Departs")) . ") and count(*) > 1][*[3][not(" . $this->contains($this->t('notTrain')) . ")]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return;
        }
        $anchor = '';
        $tickets = [];

        foreach ($nodes as $i => $root) {
            $sType = 'train';

            if ($this->http->FindSingleNode("*[3]", $root, true, "/^\s*{$this->preg_implode($this->t("Bus "))}\s*\(\s*/i")) {
                if (!isset($b)) {
                    $b = $email->add()->bus();
                    $b = $b->fromArray(array_diff_key($t->toArray(), ['segments' => '']));
                }
                $s = $b->addSegment();
                $sType = 'bus';
            } else {
                $s = $t->addSegment();
            }

            $date = strtotime($this->http->FindSingleNode("preceding::*[" . $this->starts($this->t("Travel on"), 'text()') . " or " . $this->contains($this->t("Travel on")) . "][1]", $root, true, "#" . $this->preg_implode($this->t('Travel on')) . "\s+(.+?)(\s+" . $this->preg_implode($this->t('returning on')) . "|$)#"));

            // hard code
            $dep = $this->http->FindSingleNode("*[1]", $root, true, "#^\s*\d+:\d+\s*-\s*(.+)#");
            $arr = $this->http->FindSingleNode("*[2]", $root, true, "#^\s*\d+:\d+\s*-\s*(.+)#");

            // Departure
            $s->departure()
                ->name((!empty($dep)) ? $dep . ', United Kingdom' : '')
                ->date((!empty($date)) ? strtotime($this->http->FindSingleNode("*[1]", $root, true, "#^\s*(\d+:\d+)\s*-\s*.+#"), $date) : false)
            ;

            // Arrival
            $s->arrival()
                ->name((!empty($arr)) ? $arr . ', United Kingdom' : '')
                ->date((!empty($date)) ? strtotime($this->http->FindSingleNode("*[2]", $root, true, "#^\s*(\d+:\d+)\s*-\s*.+#"), $date) : false)
            ;

            // Extra
            $company = $this->http->FindSingleNode("*[3]", $root, true, "/^{$this->preg_implode($this->t("Train"))}\s*\(\s*(.{2,}?)\s*\)$/i");

            if ($sType == 'train') {
                $s->extra()
                    ->type($company)
                    ->noNumber();
            }

            $seats = trim(implode(" ", $this->http->FindNodes("*[4][" . $this->contains($this->t("Seat:")) . "]//text()[normalize-space()]", $root)));

            if (!empty($seats) && preg_match_all("#" . $this->preg_implode($this->t("Coach:")) . "\s*([A-Z\d]{1,4})\s+" . $this->preg_implode($this->t("Seat:")) . "\s*([A-Z\d]{1,4})\b#", $seats, $coachMatches)) {
                if (count(array_unique($coachMatches[1])) == 1) {
                    $s->extra()
                        ->car($coachMatches[1][0])
                        ->seats($coachMatches[2])
                    ;
                }
            }

            // for metro/tube/underground
            if ($s->getDepDate() === $s->getArrDate()
                && (strcasecmp($s->getTrainType(), 'Unknown Service Provider') === 0
                    || stripos($s->getDepName(), 'London Underground') === 0
                    || stripos($s->getArrName(), 'London Underground') === 0
                )
            ) {
                // it-115113637.eml
                $t->removeSegment($s);

                continue;
            }

            $ticket = $this->http->FindSingleNode("preceding::text()[{$this->eq($this->t("Collection reference:"))}][1]/following::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z\d]{5,})\s*$/");

            if ($ticket) {
                $tickets[] = $ticket;
            }

            if ($sType == 'bus') {
                $allSegments = $b->getSegments();
            } else {
                $allSegments = $t->getSegments();
            }

            foreach ($allSegments as $seg) {
                if ($s->getId() === $seg->getId()) {
                    continue;
                }

                if ($s->getDepName() == $seg->getDepName()
                    && $s->getArrName() == $seg->getArrName()
                    && $s->getDepDate() == $seg->getDepDate()
                ) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }

                    if (stripos($s->getId(), 'train') === 0) {
                        $t->removeSegment($s);
                    } else {
                        $b->removeSegment($s);
                    }
                }
            }
        }

        if (count($tickets) > 0) {
            $t->setTicketNumbers(array_unique($tickets), false);
        }

        foreach ($t->getSegments() as $i => $segment) {
            if (!empty($anchor) && false === stripos($segment->getDepName(), 'London')) {
                $suppleDepName = $t->getSegments()[$i]->getDepName() . $anchor;
                $segment->setDepName($suppleDepName);
            }

            if (!empty($anchor) && false === stripos($segment->getArrName(), 'London')) {
                $suppleArrName = $segment->getArrName() . $anchor;
                $segment->setArrName($suppleArrName);
            }
        }

        if (isset($b) && count($b->getSegments()) > 0 && count($t->getSegments()) === 0) {
            $email->removeItinerary($t);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function striposAll(string $text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function assignLang($body = ''): bool
    {
        if (empty($body)) {
            $body = $this->http->Response['body'];
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false || $this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($price)
    {
        $price = trim($price);

        if (preg_match("#^([\d,. ]+)[.,](\d{2})$#", $price, $m)) {
            $price = str_replace([' ', ',', '.'], '', $m[1]) . '.' . $m[2];
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'ВЈ' => 'GBP',
            'Â£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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
}
