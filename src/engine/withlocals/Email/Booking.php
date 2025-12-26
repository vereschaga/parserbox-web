<?php

namespace AwardWallet\Engine\withlocals\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "withlocals/it-679835398.eml, withlocals/it-680738104.eml, withlocals/it-683195770.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking number' => ['Booking number', 'Booking reference', 'Booking ID'],
            'Guests'         => ['Guests', 'Number of guests'],
            'Amount'         => ['Amount', 'Total price'],
            'Date & time'    => ['Date & time', 'Trip date & time'],
        ],
    ];

    private $detectFrom = "@withlocals.com";
    private $detectSubject = [
        // en
        'Your Withlocals booking is confirmed!',
        'Booking confirmation from Viator',
    ];
    private $detectBody = [
        'en' => [
            'Booking Overview', 'Booking details', 'Payout details',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]withlocals\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Withlocals') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.withlocals.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Withlocals BV - '])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseEmailHtml(Email $email)
    {
        // Vendor
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Amount'))}]/following::text()[normalize-space()][position() < 10][{$this->eq($this->t('You will receive'))}]")->length > 0) {
            $email->setSentToVendor(true);
        }
        $event = $email->add()->event();

        $event->type()->event();

        // General
        $event->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([\da-z]{5,})(?:-[a-z\d\-]+)?\s*$/"));

        if ($email->getSentToVendor() !== true) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
                null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*(\D+?)[\W\s]*,\s*$/");

            if (!empty($traveller)) {
                $event->general()
                    ->traveller($traveller, false);
            }
        }
        // Place
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Overview'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Experience'))}]/following::text()[normalize-space()][1]");
        }
        $event->place()
            ->name($name);
        $meetingPoint = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Meeting Point'))}]/following::text()[normalize-space()][1]");
        $addressFromUrl = $this->getAddress($event->getName(), $meetingPoint);

        if (!empty($addressFromUrl['address'])) {
            $event->place()
                    ->address($addressFromUrl['address']);

            $event->general()
                    ->notes($addressFromUrl['meetingPoint']);
        } else {
            $event->place()
                ->address($meetingPoint ?? $addressFromUrl['meetingPoint'] ?? null);
        }

        // Booked
        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/following::text()[normalize-space()][1]",
        null, true, "/^\s*(.*\d{4}.*)\s*$/");
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Time'))}]/following::text()[normalize-space()][1]",
        null, true, "/^\s*(\d{1,2}:\d{2}.*)\s*$/");

        if (empty($date) && empty($time)) {
            $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date & time'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(.*\d{4}.*)(?: at |\s*T\s*\d{1,5}:)/");
            $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date & time'))}]/following::text()[normalize-space()][1]",
                null, true, "/(?: at |T\s*)(\d{1,2}:\d{2}.*)\s*$/");
        }

        if (!empty($date) && !empty($time)) {
            $event->booked()
             ->start(strtotime($date . ', ' . $time));
        }

        $duration = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Duration'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d+.*)\s*$/");

        if (!empty($duration) && !empty($event->getStartDate())) {
            if (preg_match('/^\s*(\d+\.\d) hours?/', $duration, $m)) {
                // 2.5 hours -> 150 minute
                $duration = (int) ((float) $m[1] * 60.0) . ' minute';
            }
            $event->booked()
                ->end(strtotime('+ ' . $duration, $event->getStartDate()));
        } elseif (!empty($event->getStartDate())) {
            $event->booked()
                ->noEnd();
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d+)\s*$/");

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Guests'))}]")->length > 0) {
            $event->booked()
                ->guests($guests);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Amount'))}]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $currency = $this->currency($m['currency']);
            $event->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        return true;
    }

    private function getAddress($eventName, $meetingPoint = null)
    {
        $addressResult = ['address' => '', 'meetingPoint' => ''];

        if (empty($eventName)) {
            return false;
        }
        $http2 = clone $this->http;
        $url = 'https://www.google.com/search?q=withlocals+' . trim(urlencode($eventName));

        $http2->GetURL($url);

        $tourCode = $http2->FindSingleNode("//text()[{$this->starts($eventName)}]/ancestor::h3[1]/ancestor::a/@href[contains(., 'withlocals.com')][1]",
            null, true, "/\.withlocals\.com\/experience\/.+-([a-z\d]{5,})\//");

        if (empty($tourCode)) {
            // if only one
            $tourCode = $http2->FindSingleNode("//h3/ancestor::a/@href[contains(., 'withlocals.com')]",
                null, true, "/\.withlocals\.com\/experience\/.+-([a-z\d]{5,})\//");
        }

        if (empty($tourCode)) {
            return $addressResult;
        }

        $url2 = "https://api.withlocals.com/api/v3/experience/{$tourCode}?lang=en";
        $http2->setDefaultHeader('Origin', 'https://www.withlocals.com');
        $http2->setDefaultHeader('Referer', 'https://www.withlocals.com/');
        $http2->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $http2->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0');
        $http2->setDefaultHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8');
        $http2->GetURL($url2);

        $data = json_decode($http2->Response['body'], true);

        if (!is_array($data)) {
            return $addressResult;
        }
        $addressResult['meetingPoint'] = $meetingPoint;

        if (!empty($data['attributes']) && !empty($data['attributes']['exactSpot'])) {
            $addressResult['address'] = $data['attributes']['exactSpot'];

            if (!empty($data['attributes']['area']) && !preg_match("/(^|,\s*)" . preg_quote($data['attributes']['area']) . "(?:\s*,|$)/", $addressResult['address'])) {
                $addressResult['address'] .= ', ' . $data['attributes']['area'];
            }

            if (!empty($data['address']) && !empty($data['address']['country']) && !preg_match("/(^|,\s*)" . preg_quote($data['address']['country']) . "(?:\s*,|$)/", $addressResult['address'])) {
                $addressResult['address'] .= ', ' . $data['address']['country'];
            }
        } elseif (!empty($data['address']) && !empty($data['address']['formatted_address'])) {
            $addressResult['address'] = $data['address']['formatted_address'];
        }

        if (empty($addressResult['meetingPoint']) && !empty($data['attributes']) && !empty($data['attributes']['meetingPoint'])) {
            $addressResult['meetingPoint'] = $data['attributes']['meetingPoint'];
        }

        return $addressResult;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'    => 'EUR',
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
}
