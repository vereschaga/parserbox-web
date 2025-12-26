<?php

namespace AwardWallet\Engine\hdfc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryDetailsHotel extends \TAccountChecker
{
    public $mailFiles = "hdfc/it-663988331.eml, hdfc/it-666569495.eml";

    private $detectFrom = '@smartbuyoffers.co';

    private $detectSubject = [
        'Your Hotel Booking with SmartBuy is Successful - Order Reference Number',
    ];

    private $detectCompany = 'HDFC Bank SmartBuy';

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Order Details' => 'Order Details',
            'Guests'        => 'Guests',
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
            if (!empty($dict['Order Details']) && !empty($dict['Guests'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Order Details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Guests'])}]")->length > 0
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
        $provider = strtolower(trim($this->http->FindSingleNode("//text()[contains(normalize-space(), 'booking through our booking partner')]",
            null, true, "/booking through our booking partner (.+?) is /")));
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

        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Partner Ref No") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*(?:\d{10,}#)?([\dA-Z]{5,})\s*$/");

        if (!empty($conf)) {
            $h->general()->confirmation($conf, 'Partner Ref No');
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Partner Trip ID") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*(?:\d{10,}#)?([\dA-Z]{5,})\s*$/");

            if (!empty($conf)) {
                $h->general()->confirmation($conf, 'Partner Trip ID');
            }
        }

        if (empty($conf)
            && (!empty($this->http->FindSingleNode("//text()[" . $this->starts(["Partner Ref No", "Partner Trip ID"]) . "]/following::text()[normalize-space() and not(normalize-space() = '/')][1][" . $this->starts(["Email", "Ticket Number"]) . "]"))
                || !empty($this->http->FindSingleNode("//text()[" . $this->eq(["Contact Details"]) . "]/following::text()[normalize-space()][1][" . $this->eq(["Email"]) . "]"))
            )
        ) {
            $h->general()->noConfirmation();
        }

        $passengerText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Guests']/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space()]"));

        if (preg_match_all("#.+ \d+:\s*(?:(?:Mr|Mrs|Mstr|Miss|Ms)\. )?(.+)#", $passengerText, $m)) {
            $h->general()
                ->travellers(array_unique($m[1]), false);
        }
        $h->general()->date(strtotime($this->http->FindSingleNode("//text()[" . $this->eq("Order Date") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")));

        $xpath = "//text()[{$this->eq('Contact Details')}]/following::img[1]/ancestor::tr[1][*[1][not(normalize-space())] and count(*[1]//img) = 1 and count(*[2]//img) = 1]";

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode($xpath . "/*[2]/descendant::tr[not(.//tr)][1]"))
            ->address($this->http->FindSingleNode($xpath . "/*[2]/descendant::tr[not(.//tr)][2]"));

        $dateStr = implode("\n", $this->http->FindNodes($xpath . "/*[2]/descendant::tr[not(.//tr)][3]//text()[normalize-space()]"));

        if (preg_match("/^\s*(\d.+)\n\s*(\d.+)\s*$/", $dateStr, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]))
            ;
        }
        $roomsCount = $this->http->FindSingleNode("//td[{$this->eq('No. of Rooms :')}]/following-sibling::*[1]");
        $h->booked()
            ->rooms($roomsCount);

        $roomsTitle = [];

        if ($roomsCount > 0) {
            for ($i = 1; $i <= $roomsCount; $i++) {
                $roomsTitle[] = 'room' . $i;
            }
        }

        if (!empty($roomsTitle)) {
            $types = $this->http->FindNodes("//td[{$this->eq($roomsTitle)}]/following-sibling::*[1][preceding::text()[{$this->eq('Guests')}]][following::text()[{$this->eq('No. of Rooms :')}]]");

            foreach ($types as $type) {
                $h->addRoom()->setType($type);
            }

            $h->booked()
                ->guests(array_sum($this->http->FindNodes("//*[{$this->starts('No of Guests')}]/following::td[{$this->eq($roomsTitle)}]/following-sibling::*[1]",
                    null, "/Adult *: *(\d+)/i")))
                ->kids(array_sum($this->http->FindNodes("//*[{$this->starts('No of Guests')}]/following::td[{$this->eq($roomsTitle)}]/following-sibling::*[1]",
                    null, "/Child *: *(\d+)/i")));
        }

        $priceXpath = "//tr[{$this->eq('Payments')}]/following-sibling::*";
        $pNodes = $this->http->XPath->query($priceXpath);
        $netpay = 0.0;
        $total = 0.0;
        $discount = 0.0;
        $totalPoint = [];

        foreach ($pNodes as $pRoot) {
            $name = $this->http->FindSingleNode("*[1]", $pRoot);
            $value = $this->http->FindSingleNode("*[2]", $pRoot);

            if ($name == 'Basefare') {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $h->price()
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
            } elseif (stripos($name, 'Netpay') === 0) {
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
                    $h->price()
                        ->fee($name, PriceHelper::parse($m['amount'], $currency));
                }
            }
        }

        if (!empty($total) || !empty($totalPoint)) {
            $h->price()
                ->total($total);
        } else {
            $h->price()
                ->total($netpay);
        }
        $h->price()
            ->currency($currency ?? null);

        if (!empty($totalPoint)) {
            $h->price()
                ->spentAwards(implode(' + ', $totalPoint));
        }

        if (!empty($discount)) {
            $h->price()
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
