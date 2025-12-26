<?php

namespace AwardWallet\Engine\hdfc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryDetails extends \TAccountChecker
{
    public $mailFiles = "hdfc/it-26824738.eml, hdfc/it-27251464.eml, hdfc/it-27463783.eml, hdfc/it-664770262.eml, hdfc/it-667098922.eml, hdfc/it-667881653.eml";

    private $detectFrom = '@smartbuyoffers.';

    private $detectSubject = [
        'Your Flight Booking with SmartBuy is', //en
        'Order Reference Number', //en
        'Payment Link from HDFC Bank Smartbuy Concierge Services', //en
    ];

    private $detectCompany = 'HDFC Bank SmartBuy';

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Order Details' => 'Order Details',
            'Passengers'    => 'Passengers',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach (self::$dict as $lang => $dict) {
            if (!empty($dict['Order Details']) && !empty($dict['Passengers'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Order Details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Passengers'])}]")->length > 0
            ) {
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
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['cleartrip', 'goibibo', 'yatra'];
    }

    private function parseEmail(Email $email)
    {
        // Provider
        $provider = strtolower(trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This ticket is booked through our partner')]", null, true, "#This ticket is booked through our partner (.+?)\.#")));

        if (empty($provider)) {
            $provider = strtolower(trim($this->http->FindSingleNode("//text()[contains(normalize-space(), 'booking through our booking partner')]",
                null, true, "/booking through our booking partner (.+?) is /")));
        }
        $companies = [
            // key in lowercase
            'clear trip' => 'cleartrip',
            'goibibo'    => 'goibibo',
            'yatra'      => 'yatra',
        ];

        if (!empty($provider)) {
            if (isset($companies[$provider])) {
                $email->setProviderCode($companies[$provider]);
            } else {
                $email->setProviderKeyword($provider);
            }
        }

        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts("Order ID") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#"))
        ;

        // User Email
        $userEmail = $this->http->FindSingleNode("//text()[" . $this->eq("Email") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1][contains(., '@')]");

        if (!empty($userEmail)) {
            $email->setUserEmail($userEmail);
        }

        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Partner Ref No") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true,
            "#^\s*(?:\d{10,}\#|\#)?([\dA-Z]{5,})\s*(/.*)?\s*$#");

        if (!empty($conf)) {
            $f->general()->confirmation($conf, 'Partner Ref No');
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Partner Trip ID") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true,
                "#^\s*(?:\d{10,}\#|\#)?([\dA-Z]{5,})\s*(/.*)?\s*$#");

            if (!empty($conf)) {
                $f->general()->confirmation($conf, 'Partner Trip ID');
            }
        }

        if (empty($conf)
            && (!empty($this->http->FindSingleNode("//text()[" . $this->starts(["Partner Ref No", "Partner Trip ID"]) . "]/following::text()[normalize-space() and not(normalize-space() = '/')][1][" . $this->starts(["Email", "Ticket Number"]) . "]"))
                || !empty($this->http->FindSingleNode("//text()[" . $this->eq(["Contact Details"]) . "]/following::text()[normalize-space()][1][" . $this->eq(["Email"]) . "]"))
            )
        ) {
            $f->general()->noConfirmation();
        }

        $passengerText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space()]"));

        if (preg_match_all("#.+ \d+:\s*(?:(?:Mr|Mrs|Mstr|Miss|Ms)\. )?(.+)#", $passengerText, $m)) {
            $f->general()
                ->travellers($m[1], true);
        }

        if (empty($f->getTravellers())) {
            $travellers = $this->http->FindNodes("//text()[normalize-space()='Passengers']/following::tr[not(.//tr)][normalize-space()][1][*[1][normalize-space() = 'Name']]/following::tr[not(.//tr)][1]/ancestor::*[1]/tr/*[1][not(normalize-space() = 'Name')]");
            $f->general()
                ->travellers(preg_replace("/^\s*(?:Mr|Mrs|Mstr|Miss|Ms)\.? /", '', $travellers), true);
        }

        $f->general()->date(strtotime($this->http->FindSingleNode("//text()[" . $this->eq("Order Date") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")));

        // Segments
        $xpath = "//text()[normalize-space()='Airline PNR']/ancestor::table[.//img][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//tr[*[1][.//text()[normalize-space()='Class']]][*[2][.//text()[normalize-space()='Quantity']]]/ancestor::table[.//img][1]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $conf = $this->http->FindSingleNode(".//text()[normalize-space() = 'Airline PNR']/ancestor::td[1]", $root, true, "#Airline PNR\s*([A-Z\d]{5,7})\b#");
            $class = $this->http->FindSingleNode(".//text()[normalize-space() = 'Class']/ancestor::td[1]", $root, true, "#Class\s*(.+)#");

            $airlines = $this->re("#^([\s\S]+\n)\s*Class\s*\n#", implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root)));

            $segmentCount = 1;
            preg_match_all("#.+\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) - (?<fn>\d{1,5})(?: *- *(?<fn2>\d{1,5}))?\s*\n[A-Z]{3}\n[A-Z]{3}#", $airlines, $flights);

            if (!empty($flights['fn2'][0])) {
                $segmentCount = 2;
                $s1 = $f->addSegment();
            }

            if (empty($flights)) {
                return $email;
            }

            // Airline
            $s->airline()
                ->name($flights['al'][0])
                ->number($flights['fn'][0])
            ;

            if ($segmentCount > 1) {
                $s1->airline()
                    ->name($flights['al'][0])
                    ->number($flights['fn2'][0])
                ;
            }

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);

                if ($segmentCount > 1) {
                    $s1->airline()
                        ->confirmation($conf);
                }
            }

            $via = '';
            $xpathRoute = "./following::table[1]/descendant::tr[1]";

            if ($segmentCount > 1) {
                $via = $this->http->FindSingleNode($xpathRoute . "/td[2]/descendant::text()[contains(.,'--Via')]",
                    $root, null, "/^\s*--Via (\S.+)\s*--\s*$/");
            }
            $regexp = "#(?<date>.+ \d+:\d+.*?)\s+(?<code>[A-Z]{3})\s+(?<name>.+?)\s*$#";
            // Departure
            $dep = implode(" ", $this->http->FindNodes($xpathRoute . "/td[1]//text()[normalize-space()]", $root));

            if (!empty($dep) && preg_match($regexp, $dep, $m)) {
                $s->departure()
                    // ->name(trim($m['name']))
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                ;

                if (preg_match("#^\s*(.*?)\bOperated By:\s*(?<operator>.*?)\s*$#", $m['name'], $mat)) {
                    $m['name'] = trim($mat[1]);
                    $s->airline()
                        ->operator($mat['operator']);
                }

                if (preg_match("#^\s*(.*?)\bTerminal\s*[:\-]+\s*(?<terminal>\w.*)\s*$#", $m['name'], $mat)) {
                    $m['name'] = trim($mat[1]);
                    $s->departure()
                        ->terminal($mat['terminal']);
                }

                if (!empty($m['name'])) {
                    $s->departure()
                        ->name(trim($m['name']));
                }
            }

            if ($segmentCount > 1) {
                $s1->departure()
                    ->noCode()
                    ->noDate()
                ;

                if (!empty($via)) {
                    $s1->departure()
                        ->name($via);
                }
            }
            // Arrival
            $arr = implode(" ", $this->http->FindNodes($xpathRoute . "/td[3]//text()[normalize-space()]", $root));

            if (!empty($arr) && $segmentCount === 1 && preg_match($regexp, $arr, $m)) {
                $s->arrival()
                    // ->name(trim($m['name']))
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                ;

                if (preg_match("#^\s*(.*?)\bTerminal\s*[:\-]+\s*(?<terminal>.*)\s*$#", $m['name'], $mat)) {
                    $m['name'] = trim($mat[1]);
                    $s->arrival()
                        ->terminal($mat['terminal'], true, true);
                }

                if (!empty($m['name'])) {
                    $s->arrival()
                        ->name(trim($m['name']));
                }
            }

            if (!empty($arr) && $segmentCount > 1 && preg_match($regexp, $arr, $m)) {
                $s->arrival()
                    ->noCode()
                    ->noDate()
                ;

                if (!empty($via)) {
                    $s->arrival()
                        ->name($via);
                }

                $s1->arrival()
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                ;

                if (preg_match("#^\s*(.*?)\bTerminal\s*[:\-]+\s*(?<terminal>.*)\s*$#", $m['name'], $mat)) {
                    $m['name'] = trim($mat[1]);
                    $s1->arrival()
                        ->terminal($mat['terminal'], true, true);
                }

                if (!empty($m['name'])) {
                    $s1->arrival()
                        ->name(trim($m['name']));
                }
            }

            $s->extra()
                ->cabin($class);

            if ($segmentCount > 1) {
                $s1->extra()
                    ->cabin($class);
            }
            $duration = $this->http->FindSingleNode("(" . $xpathRoute . "/td[2]/descendant::text()[normalize-space()])[1][not(contains(.,'stop') or contains(.,'Stop'))]", $root);

            if ($segmentCount === 1) {
                $s->extra()
                    ->duration($duration, true, true);
            } else {
                $durations = preg_split('/\s*\+\s*/', $duration);

                if (count($durations) === 2) {
                    $s->extra()
                        ->duration($durations[0]);
                    $s1->extra()
                        ->duration($durations[1]);
                }
            }

            // Stops don't show a real stop
            //			$s->extra()->stops();

            if (count($flights[0]) == 1) {
                continue;
            }

            for ($i = 1; $i <= count($flights[0]) - 1; $i++) {
                $s = $f->addSegment();

                // Airline
                $s->airline()
                    ->name($flights['al'][$i])
                    ->number($flights['fn'][$i])
                ;

                if (!empty($conf)) {
                    $s->airline()
                        ->confirmation($conf);
                }

                $xpathRoute = "./following::table[1]/following-sibling::table[1]/descendant::tr[1]";

                $regexp = "#(?<date>.+ \d+:\d+.*?)\s+(?<code>[A-Z]{3})\s+(?<name>.+?)(\s+Terminal\s*[:\-]+\s*(?<term>.*)|$)#";
                // Departure
                $dep = implode(" ", $this->http->FindNodes($xpathRoute . "/td[1]//text()[normalize-space()]", $root));

                if (!empty($dep) && preg_match($regexp, $dep, $m)) {
                    $s->departure()
                        ->name(trim($m['name']))
                        ->code($m['code'])
                        ->date(strtotime($m['date']))
                        ->terminal($m['term'] ?? null, true, true);
                }
                // Arrival
                $arr = implode(" ", $this->http->FindNodes($xpathRoute . "/td[3]//text()[normalize-space()]", $root));

                if (!empty($dep) && preg_match($regexp, $arr, $m)) {
                    $s->arrival()
                        ->name(trim($m['name']))
                        ->code($m['code'])
                        ->date(strtotime($m['date']))
                        ->terminal($m['term'] ?? null, true, true);
                }

                $s->extra()
                    ->cabin($class)
                    ->duration($this->http->FindSingleNode("(" . $xpathRoute . "/td[2]/descendant::text()[normalize-space()])[1][not(contains(.,'stop') or contains(.,'Stop'))]", $root), true, true)
                ;
            }
        }

        // Price
        $priceXpath = "//tr[{$this->eq('FARE DETAILS')} or {$this->eq('Payments')}][following-sibling::*[last()][{$this->starts('Paid by')} or {$this->starts('Netpay')}]]/following-sibling::*";
        $pNodes = $this->http->XPath->query($priceXpath);
        $cost = 0.0;
        $total = 0.0;
        $netpay = 0.0;
        $totalPoint = [];
        $discount = 0.0;

        foreach ($pNodes as $pRoot) {
            $name = $this->http->FindSingleNode("*[1]", $pRoot);
            $value = $this->http->FindSingleNode("*[2]", $pRoot);

            if ($name == 'Basefare') {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $cost += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif (stripos($name, 'Paid by points') === 0) {
                $totalPoint[] = $value;
            } elseif (stripos($name, 'Paid by') === 0) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $total += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif (stripos($name, 'Discount') === 0) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $discount += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif (stripos($name, 'Netpay') === 0) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $netpay += PriceHelper::parse($m['amount'], $currency);
                }
            } else {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $f->price()
                        ->fee($name, PriceHelper::parse($m['amount'], $currency));
                }
            }
        }

        if (!empty($total) || !empty($totalPoint)) {
            $f->price()
                ->total($total);
        } else {
            $f->price()
                ->total($netpay);
        }

        $f->price()
            ->cost($cost)
            ->currency($currency ?? null);

        if (!empty($totalPoint)) {
            $f->price()
                ->spentAwards(implode(' + ', $totalPoint));
        }

        if (!empty($discount)) {
            $f->price()
                ->discount($discount);
        }

        return $email;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeAmount(string $string)
    {
        $string = PriceHelper::cost($string);

        if (is_numeric($string)) {
            return (float) $string;
        }

        return null;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'INR' => ['Rs.', 'Rs'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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
