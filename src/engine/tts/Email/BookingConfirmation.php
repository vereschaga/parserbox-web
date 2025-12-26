<?php

namespace AwardWallet\Engine\tts\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "tts/it-605094820.eml, tts/it-605137888.eml, tts/it-612856657.eml, tts/it-622995122.eml";

    public $detectFrom = "mailer@eventpipe.com";
    public $detectSubject = [
        // en
        'Booking Confirmation R-',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Reservation Pipe ID:' => 'Reservation Pipe ID:',
            'Hotel Address:'       => 'Hotel Address:',
            // 'was successfully cancelled' => '',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]eventpipe\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['eventpipe.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['EventPipe. All rights'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Reservation Pipe ID:"]) && !empty($dict["Hotel Address:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Reservation Pipe ID:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Hotel Address:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Pipe ID:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(R-\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*(\D+?)\s*,\s*$/"))
        ;

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('was successfully cancelled'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Address'))}]/ancestor::*[.//text()[{$this->starts($this->t('Reservation Details'))}]][1]//text()[normalize-space()]"));
        // $this->logger->debug('$hotelInfo = '.print_r( $hotelInfo,true));
        // Hotel
        if (preg_match("/(?:^|\n)\s*{$this->opt($this->t('Hotel'))}\s*:\s*(?<name>.+?)\s+{$this->opt($this->t('Hotel Address'))}\s*:\s*(?<address>.+?)\s+{$this->opt($this->t('Check In'))}/s", $hotelInfo, $m)) {
            $h->hotel()
                ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                ->address(preg_replace("/\s+/", ' ', trim($m['address'])))
            ;
        }

        // Booked
        if (preg_match("/\n\s*{$this->opt($this->t('Check In'))}\s*:\s*(?<cin>.+?)\s+{$this->opt($this->t('Check Out'))}\s*:\s*(?<cout>.+?)\s+{$this->opt($this->t('Reservation Details'))}/s", $hotelInfo, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['cin']))
                ->checkOut($this->normalizeDate($m['cout']))
            ;
        }

        // Rooms
        $r = $h->addRoom();

        if (preg_match("/\n\s*{$this->opt($this->t('Reservation Details'))}\s*:\s*[^,]+night[^,]+,(?<type>[^,]+)(?:,|\s*$)/s", $hotelInfo, $m)) {
            $r->setType($m['type']);
        } elseif (preg_match("/\n\s*{$this->opt($this->t('Reservation Details'))}\s*:\s*(?<type>[^,]+)(?:\n\s*{$this->opt($this->t('Room Nights Held'))}|$)/", $hotelInfo, $m)) {
            $r->setType($m['type']);
        } else {
            $r->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Details'))}]/following::text()[normalize-space()][not(normalize-space() = ':')][1]/ancestor::ul[1]/descendant::text()[normalize-space()][not(ancestor::ul[2])]"));
            $rates = $this->http->FindNodes("//text()[{$this->eq($this->t('Reservation Details'))}]/following::text()[normalize-space()][not(normalize-space() = ':')][1]/ancestor::ul[1]//li[ancestor::ul[2]]",
                null, "/.+\(\w+\)\s*(\S.+?)\s*$/");

            if (!empty(array_filter($rates))) {
                $r->setRates($rates);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Cost'))}]/ancestor::*[not({$this->eq($this->t('Room Cost'))})][1]",
            null, true, "/^\s*{$this->opt($this->t('Room Cost'))}\s*:\s*(.+)\s*$/");

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']))
            ;
        }

        return true;
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Fri, 06/09/2023 03:00 PM EST
            '/^\s*[-[:alpha:]]+\s*,\s*(\d{1,2}\/\d{1,2}\/\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*(?:[A-Z]{3,5})?\s*$/ui',
        ];
        $out = [
            '$1, $2',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

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
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
