<?php

namespace AwardWallet\Engine\maketrip\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class AmazonFlights extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-640788335.eml, maketrip/it-642256187.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Flight Booking' => 'Flight Booking',
        ],
    ];

    private $detectFrom = "no-reply-flights@amazon.com";
    private $detectSubject = [
        // en
        'Flight E-Ticket',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply-flights@amazon.com') !== false;
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
        if ($this->http->XPath->query("//a[{$this->contains(['amazon.in/'], '@href')}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Flight Booking']) && $this->http->XPath->query("//*[{$this->eq($dict['Flight Booking'])}]")->length > 0) {
                return true;
            }
        }

        return false;
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
            if (!empty($dict["Flight Booking"])) {
                if ($this->http->XPath->query("//*[{$this->eq($dict['Flight Booking'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('MakeMyTrip ID:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{7,})\s*$/"), 'MakeMyTrip ID')
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Amazon Order ID:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([\d]{3,}[\d\-]{6,})\s*$/"), 'Amazon Order ID');

        // Flight
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('duration'))}]/ancestor::table[1]/following-sibling::*[1]/descendant::tr[1]/ancestor::*[1]/*[count(*[normalize-space()]) > 1]/*[1]",
                null, "/^\s*([[:alpha:]](?:[ \-]?[[:alpha:]]){3,})\s*$/")))
        ;

        $xpath = "//text()[{$this->eq($this->t('duration'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // Airline
            $re = "/^\s*.+? (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])-(?<fn>\d{1,5})\s*\n\s*(?<cabin>.+)\s*\n\s*/";
            $flightText = implode("\n", $this->http->FindNodes("preceding::tr[1]/*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match($re, $flightText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->extra()
                    ->cabin($m['cabin']);
            }
            $pnr = $this->http->FindSingleNode("preceding::tr[1]//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space()][1]",
                $root, true, "/^\s*([A-Z\d]{5,7})\s*$/");

            if (!empty($pnr)) {
                $s->airline()
                    ->confirmation($pnr);
            }

            // Departure
            $re = "/^\s*(?<code>[A-Z]{3})\s*\n\s*(?<time>\d{1,2}:\d{2})\s*\n\s*(?<date>.+)\s*\n\s*(Terminal - (?<terminal>[^,]+)?,\s*)?(?<name>.+)\s*$/";
            $departText = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));

            if (preg_match($re, $departText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                    ->terminal($m['terminal'] ?? '', true);
            }
            // Arrival
            $re = "/^\s*(?<time>\d{1,2}:\d{2})\s*\n\s*(?<code>[A-Z]{3})\s*\n\s*(?<date>.+)\s*\n\s*(Terminal - (?<terminal>[^,]+)?,\s*)?(?<name>.+)\s*$/";
            $arrivalText = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match($re, $arrivalText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                    ->terminal($m['terminal'] ?? '', true);
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*(( *\d+ ?[hm])+)\s*$/"));
        }

        // Price
        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Order Total'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $f->price()
                ->total($this->amount($m['amount'], $currency))
                ->currency($currency)
            ;
        } else {
            $currency = null;
            $f->price()
                ->total(null);
        }

        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Base fare'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $f->price()
                ->cost($this->amount($m['amount'], $currency))
            ;
        }

        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Taxes & fees'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $f->price()
                ->tax($this->amount($m['amount'], $currency))
            ;
        }

        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Convenience fee'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $f->price()
                ->fee($this->http->FindSingleNode("//tr/*[1][{$this->eq($this->t('Convenience fee'))}]"), $this->amount($m['amount'], $currency))
            ;
        }

        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Discount applied'))}]]/*[2]");

        if (preg_match("#^\s*\-\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*\-\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $f->price()
                ->discount($this->amount($m['amount'], $currency))
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
        ];
        $out = [
        ];

        $date = preg_replace($in, $out, $date);

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

    private function amount($priceText = '', $currency)
    {
        $price = PriceHelper::parse($priceText, $currency);

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
            '₹' => 'INR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
