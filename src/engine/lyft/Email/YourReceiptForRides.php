<?php

namespace AwardWallet\Engine\lyft\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReceiptForRides extends \TAccountChecker
{
    public $mailFiles = "lyft/it-173701577.eml";

    public $detectSubject = [
        'Your receipt for rides'
    ];
    public $lang = 'en';

    public static $dict = [
        'en' => [
            'Dropoff' => ['Dropoff', 'Drop-off'],
        ],
    ];

    public $detectBody = [
        'en' => [
            'One day of rides in a single charge',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmail($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'lyftmail.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lyftmail.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function parseEmail(PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->transfer();

        $r->general()
            ->noConfirmation();

        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t("You were charged"))} and {$this->contains($this->t("for all of the rides"))}]",
            null, true, "/{$this->opt($this->t("You were charged"))}(.+){$this->opt($this->t("for all of the rides"))}/");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ){
            $c = $this->currency($m['currency']);
            $r->price()
                ->total($this->amount($m['amount'], $c))
                ->currency($c)
            ;
        }

        $xpath = "//text()[" . $this->eq($this->t("Pickup")) . "]/ancestor::*[count(.//text()[" . $this->eq($this->t("Pickup")) . "]) = 1 and following-sibling::*[normalize-space()][1][" . $this->starts($this->t("Drop-off")) . "] and preceding::text()[normalize-space()][{$this->eq($this->t("Ride fare"))}]]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $r->addSegment();

            $date = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][3]", $root, true, '/(.+\b\d{4}\b.*?)\s+\d{1,2}:\d/');

            // Departure
            $pickUp = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
//            $this->logger->debug('$pickUp = '.print_r( $pickUp,true));
            $re = "/^\s*" . $this->opt($this->t("Pickup")) . "\s+(?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+(?<address>[\s\S]+)$/i";
            if (preg_match($re, $pickUp, $m)) {
                $s->departure()
                    ->date((!empty($date))? strtotime($date.','.$m['time']) : null)
                    ->address($m['address']);
            }

            // Arrival
            $dropoff = implode("\n", $this->http->FindNodes("following-sibling::*[normalize-space()][1]//text()[normalize-space()]", $root));
//            $this->logger->debug('$dropoff = '.print_r( $dropoff,true));
            $re = "/^\s*" . $this->opt($this->t("Drop-off")) . "\s+(?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+(?<address>[\s\S]+)$/i";
            if (preg_match($re, $dropoff, $m)) {
                $s->arrival()
                    ->date((!empty($date))? strtotime($date.','.$m['time']) : null)
                    ->address($m['address']);
            }
        }
    }

    private function amount($amount, $currency)
    {
        return PriceHelper::parse($amount, $currency);
    }

    private function currency($s)
    {
        $s = trim($s);
        if (preg_match("/^([A-Z]{3})$/", $s)) {
            return $s;
        }

        $sym = [
            '$'   => 'USD',
            '€'   => 'EUR',
            '£'   => 'GBP',
            'US$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s === $f) {
                return $r;
            }
        }

        return $s;
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

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function convertSegments($points, Transfer $r): bool
    {
        $result = true;

        foreach ($points as $point) {
            if ($next = next($points)) {
                $s = $r->addSegment();

                if (isset($point["Name"])) {
                    $s->departure()->name($this->normalizeLocation($point["Name"]));
                } else {
                    $result = false;
                }

                if (isset($point["Date"])) {
                    if ($point["Date"] === MISSING_DATE) {
                        $s->departure()->noDate();
                    } else {
                        $s->departure()->date($point["Date"]);
                    }
                }

                if (isset($next["Name"])) {
                    $s->arrival()->name($this->normalizeLocation($next["Name"]));
                } else {
                    $result = false;
                }

                if (isset($next["Date"])) {
                    if ($next["Date"] === MISSING_DATE) {
                        $s->arrival()->noDate();
                    } else {
                        $s->arrival()->date($next["Date"]);
                    }
                }
            }
        }

        return $result;
    }

    private function normalizeLocation(?string $s): ?string
    {
        if (empty($s) || !isset($this->region)) {
            return $s;
        }
        $s = preg_replace('/[, ]*,[, ]*/', ', ', $s);
        $s = preg_match("/.{2,},\s*USA?$/", $s) ? $s : $s . $this->region;

        return $s;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
