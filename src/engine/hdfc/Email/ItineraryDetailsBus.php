<?php

namespace AwardWallet\Engine\hdfc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryDetailsBus extends \TAccountChecker
{
    public $mailFiles = "hdfc/it-663178346.eml";

    private $detectFrom = '@smartbuyoffers.co';

    private $detectSubject = [
        'our Bus Booking with SmartBuy is Successful - Order Reference Number',
    ];

    private $detectCompany = 'HDFC Bank SmartBuy';

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Booking Details' => 'Booking Details',
            'Bus Details'     => 'Bus Details',
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
            if (!empty($dict['Booking Details']) && !empty($dict['Bus Details'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Booking Details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Bus Details'])}]")->length > 0
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
        $provider = strtolower(trim($this->http->FindSingleNode("//text()[contains(normalize-space(), 'bus transaction against your')]",
            null, true, "/bus transaction against your (.+?)\./")));
        $companies = [
            // key in lowercase
            'clear trip'         => 'cleartrip',
            'goibibo'            => 'goibibo',
            'yatra'              => 'yatra',
            'hdfc bank smartbuy' => 'hdfc',
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
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts("Order Reference Number") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#"))
        ;

        $b = $email->add()->bus();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Booking Id") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
            null, true, "/^\s*([\dA-Z]{5,})\s*$/");

        if (!empty($conf)) {
            $b->general()->confirmation($conf, 'Booking Id');
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts("PNR") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*([\dA-Z]{5,})\s*$/");

            if (!empty($conf)) {
                $b->general()->confirmation($conf, 'PNR');
            }
        }
        $this->logger->debug('$conf = ' . print_r($conf, true));

        $b->general()
            ->travellers(array_unique($this->http->FindNodes("//tr[*[1][{$this->eq('Passenger Name')}]]/following-sibling::tr/*[1]")), true);

        $b->general()->date(strtotime($this->http->FindSingleNode("//text()[" . $this->eq("Booking Date") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")));

        $xpath = "//text()[normalize-space() = 'Boarding Point']/ancestor::tr[.//text()[normalize-space() = 'Dropping Point']][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $b->addSegment();

            // Departure
            $name = $this->http->FindSingleNode("*[1]", $root, true, "/^\s*Boarding Point\s*(.+)/");
            $name = preg_replace("/\s*\([^()]*\)\s*$/", '', $name);
            $name = preg_replace('/^\s*(\S.+), \1.*/', '$1', $name);
            $name2 = $this->http->FindSingleNode("preceding-sibling::*[2]/*[1]", $root);

            if (!empty($name) && !empty($name2)) {
                $s->departure()
                    ->name($name . ', ' . $name2);
            }
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("preceding-sibling::*[1]/*[1]", $root)));

            // Arrival
            $name = $this->http->FindSingleNode("*[2]", $root, true, "/^\s*Dropping Point\s*(.+)/");
            $name = preg_replace("/\s*\([^()]*\)\s*$/", '', $name);
            $name = preg_replace('/^\s*(\S.+), \1.*/', '$1', $name);
            $name2 = $this->http->FindSingleNode("preceding-sibling::*[2]/*[2]", $root);

            if (!empty($name) && !empty($name2)) {
                $s->arrival()
                    ->name($name . ', ' . $name2);
            }
            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode("preceding-sibling::*[1]/*[2]", $root)));

            // Extra
            $col = count($this->http->FindNodes("following::tr[*[1][{$this->eq('Passenger Name')}]][1]/*[{$this->eq('Seat No.')}]/preceding-sibling::*", $root));

            if (!empty($col)) {
                $col++;
                $seats = array_filter($this->http->FindNodes("following::tr[*[1][{$this->eq('Passenger Name')}]][1]/following-sibling::tr/*[{$col}]",
                    $root, "/^\s*(\w+)\s*$/"));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }

        $priceXpath = "//text()[{$this->eq('Payment Details')}]/ancestor::*[following-sibling::*][1]/following-sibling::*";
        $pNodes = $this->http->XPath->query($priceXpath);
        $netpay = 0.0;
        $total = 0.0;
        $discount = 0.0;
        $totalPoint = [];

        foreach ($pNodes as $pRoot) {
            $name = $this->http->FindSingleNode("descendant::td[not(.//td)][1]", $pRoot);
            $value = $this->http->FindSingleNode("descendant::td[not(.//td)][2]", $pRoot);

            if (!empty($name) && $value === null) {
                break;
            }
            $this->logger->debug('$name = ' . print_r($name, true));

            if ($name == 'Total Base Fare') {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $b->price()
                        ->cost(PriceHelper::parse($m['amount'], $currency));
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
            } elseif (stripos($name, 'Net Pay') === 0) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $netpay += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif (stripos($name, 'Discount') === 0) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $discount += PriceHelper::parse($m['amount'], $currency);
                }
            } else {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $b->price()
                        ->fee($name, PriceHelper::parse($m['amount'], $currency));
                }
            }
        }

        if (!empty($total) || !empty($totalPoint)) {
            $b->price()
                ->total($total);
        } else {
            $b->price()
                ->total($netpay);
        }
        $b->price()
            ->currency($currency ?? null);

        if (!empty($totalPoint)) {
            $b->price()
                ->spentAwards(implode(' + ', $totalPoint));
        }

        if (!empty($discount)) {
            $b->price()
                ->discount(implode($discount));
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Mon 13 May 2024 at 18:30
            "/^\s*[[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4}) at (\d+:\d+(?: ?[AP]M)?)\s*$/iu",
            // 8:50, Mon 13 May 2024
            "/^\s*(\d+:\d+(?: ?[AP]M)?)\s*,\s*[[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $3 $4, $1",
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
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
