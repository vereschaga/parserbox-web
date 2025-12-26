<?php

namespace AwardWallet\Engine\hdfc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryDetailsHotel2 extends \TAccountChecker
{
    public $mailFiles = "hdfc/it-861196374.eml, hdfc/it-864847172.eml, hdfc/it-875940298.eml, hdfc/it-878203105.eml";

    private $detectFrom = '@smartbuyoffers.co';

    private $detectSubject = [
        'Your Hotel Booking with SmartBuy is Successful - Order Reference Number',
    ];

    private $detectCompany = 'HDFC Bank SmartBuy';

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'R360 Booking ID:'                    => ['R360 Booking ID:', 'R360 Booking ID :'],
            'Booking Details'                     => 'Booking Details',
            'Hotel Name:'                         => 'Hotel Name:',
            'booking through our booking partner' => ['booking through our booking partner', 'booked through our partner'],
            'notConfirmedStatuses'                => ['Booking Failed', 'Booking Under Process'],
            'Booking Cancelled'                   => ['Booking Cancelled', 'Your hotel booking has been cancelled successfully'],
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
            if (!empty($dict['Booking Details']) && !empty($dict['Hotel Name:'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Booking Details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Hotel Name:'])}]")->length > 0
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
        $providers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('booking through our booking partner'))}]",
            null, "/{$this->opt($this->t('booking through our booking partner'))} (.+?)(?:\.\s*| is | has )/")));
        $provider = strtolower(trim($providers[0] ?? ''));

        $companies = [
            // key in lowercase
            'clear trip' => 'cleartrip',
            'cleartrip'  => 'cleartrip',
            'goibibo'    => 'goibibo',
            'yatra'      => 'yatra',
        ];

        if (!empty($provider)) {
            if (isset($companies[$provider])) {
                $email->setProviderCode($companies[$provider]);
            } else {
                $email->setProviderKeyword($provider);
                $email->setProviderKeyword($provider);
            }
        }

        // Travel Agency
        $confPrimary = $this->http->FindSingleNode("//text()[" . $this->eq("Partner Ref ID :") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z\-]{7,})\s*$#");

        if (empty($confPrimary) && $this->http->XPath->query("//text()[" . $this->eq($this->t("R360 Booking ID:")) . "]/preceding::text()[normalize-space()][1][{$this->eq($this->t('notConfirmedStatuses'))}]")->length > 0) {
            $email->setIsJunk(true, 'not confirmed reservation');

            return true;
        }
        $email->ota()
            ->confirmation($confPrimary,
                trim($this->http->FindSingleNode("//text()[" . $this->eq("Partner Ref ID :") . "]"), ': '), true)
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("R360 Booking ID:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z\-]{7,})\s*$#"),
                trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("R360 Booking ID:")) . "]"), ': '));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//td[{$this->eq('Guest Name:')}]/following-sibling::td[normalize-space()][1]"), true)
            ->date(strtotime($this->http->FindSingleNode("//text()[" . $this->eq("Booked on:") . "]/following::text()[normalize-space()][1]")))
            ->cancellation($this->http->FindSingleNode("//tr[{$this->eq('Cancellation Policy')}]/following-sibling::tr[normalize-space()][1]"), true, true)
        ;

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Booking Cancelled'))}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//td[" . $this->eq("Hotel Name:") . "]/following-sibling::td[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//tr[*[1][" . $this->eq("Hotel Name:") . "]]/following-sibling::tr[1][count(*[normalize-space()]) = 1]",
                null, true, "/^\s*(?:Address:\s*)?(.+)/"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//td[" . $this->eq("Check-In:") . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*(.+?)\s*(?:\(.+\))?\s*$/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//td[" . $this->eq("Check-Out:") . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*(.+?)\s*(?:\(.+\))?\s*$/")))
            ->rooms($this->http->FindSingleNode("//td[" . $this->eq("No of Rooms:") . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*(\d+)\s*$/"), true, true)
        ;
        $adults = array_filter($this->http->FindNodes("//td[{$this->eq('No of Guest:')}]/following-sibling::td[normalize-space()][1]",
            null, "/\b(\d+) ?\(?Adult/i"));
        $kids = array_filter($this->http->FindNodes("//td[{$this->eq('No of Guest:')}]/following-sibling::td[normalize-space()][1]",
            null, "/\b(\d+) ?\(?Child/i"), function ($v) {return $v === null ? false : true; });
        $h->booked()
            ->guests(count($adults) > 0 ? array_sum($adults) : null, $h->getCancelled(), $h->getCancelled())
            ->kids(count($kids) > 0 ? array_sum($kids) : null, $h->getCancelled(), $h->getCancelled());

        $types = $this->http->FindNodes("//td[{$this->starts('Room Type:')}]/following-sibling::td[normalize-space()][1]");

        foreach ($types as $type) {
            $h->addRoom()->setType($type);
        }

        $priceXpath = "//text()[{$this->eq('Base Fare')}]/ancestor::*[.//text()[{$this->eq('Fare Summary')}]][1]//tr[not(.//tr)]";
        $pNodes = $this->http->XPath->query($priceXpath);

        if ($h->getCancelled() === true) {
            $pNodes = [];
        }
        $totalPaid = 0.0;
        $total = 0.0;
        $discount = 0.0;
        $totalPoint = [];

        $isFee = true;

        foreach ($pNodes as $pRoot) {
            $name = $this->http->FindSingleNode("*[1]", $pRoot);
            $value = $this->http->FindSingleNode("*[2]", $pRoot, true, "/.*\d+.*/");

            if (empty($value)) {
                continue;
            }

            if ($name == 'Base Fare') {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $h->price()
                        ->cost(PriceHelper::parse($m['amount'], $currency));
                }
            } elseif (stripos($name, 'Paid by points') === 0) {
                if (preg_replace("/\D+/", '', $value) !== "0") {
                    $totalPoint[] = $value;
                }
                $isFee = false;
            } elseif (stripos($name, 'Total Fare') === 0) {
                $isFee = false;
            } elseif (stripos($name, 'Paid by') === 0) {
                $isFee = false;

                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $totalPaid += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif ($name === 'Total') {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $total += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif (stripos($name, 'Discount') !== false) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $discount += PriceHelper::parse($m['amount'], $currency);
                }
            } elseif ($isFee === true) {
                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                ) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $h->price()
                        ->fee($name, PriceHelper::parse($m['amount'], $currency));
                }
            }
        }

        if (!empty($totalPaid) || !empty($totalPoint)) {
            $h->price()
                ->total($totalPaid)
                ->currency($currency ?? null);
        } elseif ($h->getCancelled() !== true) {
            $h->price()
                ->total($total)
                ->currency($currency ?? null);
        }

        if (!empty($totalPoint)) {
            $h->price()
                ->spentAwards(implode(' + ', $totalPoint));
        }

        if (!empty($discount)) {
            $h->price()
                ->discount($discount);
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^\s*\W*\s*Free Cancellation \(100% refund\) if you cancel this booking before (\S+ \d{1,2}:\d{2}):\d{2} \(destination time\)\./u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }
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
