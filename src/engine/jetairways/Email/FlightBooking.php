<?php

namespace AwardWallet\Engine\jetairways\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "jetairways/it-176766885.eml, jetairways/it-177112069.eml";

    private $detectSubject = [
        // en
        'Your flight booking',
        'Your Award Flight booking',
    ];

    private $detectBody = [
        'en' => [
            'eTicket Itinerary / Receipt',
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking ID:' => ['Booking ID:', 'Booking Reference Number:'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.intermiles.com') !== false || stripos($from, '@intermiles.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query("//a[contains(@href, '.intermiles.com')]")->length === 0) {
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
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->eq($this->t("Booking ID:"))."]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"));
        $earned = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total InterMiles earned"))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\d[\d,. ]*\s*InterMiles\s*$/");
        if (!empty($earned)) {
            $email->ota()
                ->earnedAwards($earned);
        }

        // FLIGHT
        $f = $email->add()->flight();

        // General
        $confs = array_unique($this->http->FindNodes("//text()[".$this->starts($this->t("Booking reference (PNR):"))."]",
                null, "/Booking reference \(PNR\):\s*([A-Z\d]{5,})\s*$/"));
        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }
        $travellers = array_unique($this->http->FindNodes("//tr[*[2][{$this->eq($this->t("Guest Name"))}]]/following::tr[normalize-space()][1]/*[2]",
            null, "/^\s*(?:(?:Mr|Miss|Mrs) )?(.+)/"));
        $f->general()
            ->travellers($travellers);

        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[".$this->starts($this->t("Ticket Number:"))."]",
            null, "/Ticket Number:\s*(\d{13})\b[\-\/\d]*\s*$/")));
        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }
        // Segments
        $xpath = "//text()[{$this->starts($this->t('Ticket Number:'))}]/following::text()[normalize-space()][2]/ancestor::tr[count(*) = 4 and *[1]//img]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);
        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes("./*[1]//text()[normalize-space()]", $root));
            if (preg_match("/^.+\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*-\s*(\d{1,5})\s*\n\s*(.+)\s*(?:\n|$)/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $s->extra()
                    ->cabin($m[3]);
            }
            $node = implode("\n", $this->http->FindNodes("./*[2]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(?<name1>.+)\s+(?<time>\d{1,2}:\d{2}(?:[apAP][mM])?)\s+(?<date>.+)\\s*\n\s*(?<code>[A-Z]{3})\s*\n\s*(?<name2>.+)/", $node, $m)) {
                if (preg_match("/(.+?)\s+Terminal ([\w ]+)\s*$/", $m['name2'], $mat)) {
                    $m['name2'] = $mat[1];
                    $s->departure()
                        ->terminal($mat[2]);
                }

                $s->departure()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->name($m['name1'] . ', ' . $m['name2'])
                ;
            }

            $node = implode("\n", $this->http->FindNodes("./*[3]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(\d+h\s*\d+m)\b/", $node, $m)) {
                $s->extra()
                    ->duration($m[1])
                ;
            }
            $node = implode("\n", $this->http->FindNodes("./*[4]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(?<name1>.+)\s+(?<time>\d{1,2}:\d{2}(?:[apAP][mM])?)\s+(?<date>.+)\\s*\n\s*(?<code>[A-Z]{3})\s*\n\s*(?<name2>.+)/", $node, $m)) {
                if (preg_match("/(.+?)\s+Terminal ([\w ]+)\s*$/", $m['name2'], $mat)) {
                    $m['name2'] = $mat[1];
                    $s->arrival()
                        ->terminal($mat[2]);
                }

                $s->arrival()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->name($m['name1'] . ', ' . $m['name2'])
                ;
            }

            $seats = array_filter($this->http->FindNodes("//tr[*[3][{$this->eq($this->t("Seat No."))}]][count(preceding::text()[{$this->starts($this->t('Ticket Number:'))}]) = ".($i+1)." and count(following::text()[{$this->starts($this->t('Ticket Number:'))}]) = ".($nodes->length-$i-1)."]/following::tr[normalize-space()][1]/*[3]",
                null, "/^\s*\d{1,3}[A-Z]\s*$/"));
            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }

        // Price
        $baseFare = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Base Fare"))}]/following::text()[normalize-space()][1]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $baseFare, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $baseFare, $m)
        ){
            $currency = $this->currency($m['currency']);
            $f->price()
                ->cost(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }
        foreach (['Payable Amount', 'Total Payable Amount', 'Total Amount'] as $totalTitle) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($totalTitle)}]/following::text()[normalize-space()][1]");
            if (!empty($total)) {
                break;
            }
        }
        if (preg_match("#(.+) \+ (\d+ InterMiles)\s*$#", $total, $m)){
            $f->price()
                ->spentAwards($m[2])
            ;
            $total = $m[1];
        } else {
            $spent = $this->http->FindSingleNode("//text()[{$this->eq(['Miles Used', 'InterMiles Redeemed'])}]/following::text()[normalize-space()][1]");
            if (!empty($spent)) {
                $f->price()
                    ->spentAwards($spent)
                ;
            }
        }
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ){
            $currency = $this->currency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }
        $total = $this->http->FindSingleNode("//text()[{$this->eq('Discount')}]/following::text()[normalize-space()][1]");
        if (preg_match("#^\s*-\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*-\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ){
            $currency = $this->currency($m['currency']);
            $f->price()
                ->discount(PriceHelper::parse($m['amount'], $currency))
            ;
        }
        $tax = $this->http->FindSingleNode("//text()[{$this->eq('Total Taxes (Inclusive of GST)')}]/following::text()[normalize-space()][1]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)
        ){
            $currency = $this->currency($m['currency']);
            $f->price()
                ->tax(PriceHelper::parse($m['amount'], $currency))
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
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 20-May-2022
//            '/^\s*(\d+)\s*-\s*(\w+)\s*-\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/iu',
        ];
        $out = [
//            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        return strtotime($date);
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) return $code;
        $sym = [
            '₹' => 'INR',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];
        foreach($sym as $f => $r)
            if ($s == $f) return $r;
        return null;
    }

}