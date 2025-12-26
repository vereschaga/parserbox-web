<?php

namespace AwardWallet\Engine\xola\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "xola/it-704975714.eml, xola/it-705285168.eml, xola/it-705388396.eml, xola/it-709290512.eml, xola/it-709571353.eml, xola/it-714414388.eml, xola/it-714708225.eml, xola/it-719906157.eml";
    public $detectSubject = [
        'Booking Confirmed:',
        'Your booking has been updated',
    ];

    public $lang = 'en';
    public $emailDate;

    public static $dictionary = [
        "en" => [
            'Your booking for' => 'Your booking for',
            'Customer Details' => 'Customer Details',
            'Adults'           => ['Adults', 'Guests', 'General Admission'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@xola.com') !== false) {
            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains(['@xola.com'])}]")->length === 0
            && $this->http->XPath->query("//a/@href[{$this->contains(['.xola.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your booking for']) && !empty($dict['Customer Details'])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Your booking for'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Customer Details'])}]")->length > 0
            ) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]xola\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking ID:'))}]", null, true,
                "/{$this->opt($this->t('Booking ID:'))}\s*(\w{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking ID:'))}]", null, true,
                    "/({$this->opt($this->t('Booking ID:'))})\s*\w{5,}\s*$/"), ':'));

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Code'))}]", null, true,
            "/{$this->opt($this->t('Confirmation Code'))}\s*([A-Z\d]{5,})\s*\|\s*{$this->opt($this->t('Booking ID:'))}\s*(\w{5,})\s*$/");

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf, $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Code'))}]", null, true,
                    "/({$this->opt($this->t('Confirmation Code'))})\s*[A-Z\d]{5,}\s*\|\s*{$this->opt($this->t('Booking ID:'))}\s*(\w{5,})\s*$/"));
        }

        // Events

        $xpath = "//text()[normalize-space() = 'Ticket For Admission']/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[normalize-space() = 'Ticket For Admission'])][last()]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = ".";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $e = $email->add()->event();

            $e->type()->event();

            $e->general()
                ->noConfirmation()
                ->status($this->http->FindSingleNode("//text()[{$this->starts($this->t('has been'))}]/ancestor::*[1]",
                    null, true,
                    "/{$this->opt($this->t('has been'))}\s*([[:alpha:]]+)\s*\.?\s*$/"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Customer Details'))}]/following::text()[normalize-space()][1]"),
                    true);

            if ($nodes->length > 1) {
                // Place
                $e->place()
                    ->name($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root));
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Info'))}]/following::text()[normalize-space()][string-length()>2][2]");
                $e->place()
                    ->address($address);

                $guests = $this->http->FindSingleNode("descendant::text()[normalize-space()][position() > 2][{$this->contains($this->t('Adults'))}][1]", $root,
                    true, "/(\d+)\s*{$this->opt($this->t('Adults'))}/");
                $kids = $this->http->FindSingleNode("descendant::text()[normalize-space()][position() > 2][{$this->contains($this->t('Children'))}][1]", $root,
                    true, "/(\d+)\s*{$this->opt($this->t('Children'))}/");

                if (!empty($guests)) {
                    $e->booked()
                        ->guests($guests);
                }

                if (!empty($kids)) {
                    $e->booked()
                        ->kids($kids);
                }

                $dateStr = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]", $root, true, "/.*\b\d{4}\b.*/");

                if (!empty($dateStr)) {
                    $e->booked()
                        ->start($this->normalizeDate($dateStr))
                        ->noEnd()
                    ;
                }
            } else {
                // Place
                $e->place()
                    ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking for'))}]/ancestor::*[1]",
                        null, true,
                        "/{$this->opt($this->t('Your booking for'))}\s+(.+?)\s*{$this->opt($this->t('has been'))}/"));
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Info'))}]/following::text()[normalize-space()][string-length()>2][2]");
                $e->place()
                    ->address($address);

                $gXpath = "//text()[{$this->eq($this->t('Customer Details'))}]/ancestor::*[{$this->starts($this->t('Customer Details'))}][not(.//text()[{$this->eq($this->t('Total'))}])][last()]";
                $guests = $this->http->FindSingleNode($gXpath . "//text()[{$this->contains($this->t('Adults'))}][1]",
                    null,
                    true, "/(\d+)\s*{$this->opt($this->t('Adults'))}/");
                $kids = $this->http->FindSingleNode($gXpath . "//text()[{$this->contains($this->t('Children'))}][1]",
                    null,
                    true, "/(\d+)\s*{$this->opt($this->t('Children'))}/");

                if (!empty($guests)) {
                    $e->booked()
                        ->guests($guests);
                }

                if (!empty($kids)) {
                    $e->booked()
                        ->kids($kids);
                }

                $cardDate = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Credit Card ('))}])[1]",
                    null,
                    true,
                    "/{$this->opt($this->t('Credit Card ('))}\s*(\w+[\s.,]+\w+[\s.,]+20\d{2})\s*\)\s*$/");

                if (!empty($cardDate)) {
                    $this->emailDate = strtotime($cardDate);
                }

                $dateStr = str_replace('•', "\n",
                    $this->http->FindSingleNode("//text()[{$this->eq($this->t('Customer Details'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]"));
                // Sun 13 Aug • Arrive By 9:40 AM • Starts At 10:00 AM • 1 hour
                // Mon 2 Sep • Arrive By 12:00 PM • Starts At 12:30 PM
                // Fri 29 Dec • 8:30 AM • 10 minutes
                // Fri 29 Dec • 11:30 AM
                // Mon 13 May
                $date = $this->re("/^(.+)/", $dateStr);
                $time = $this->re("/\n *Starts At *(\d+:\d+.*)/", $dateStr);

                if (empty($time)) {
                    $time = $this->re("/^.+\n *(\d+:\d+.*)/", $dateStr);
                }
                $duration = $this->re("/\n *(\d+ *(?:hours?|minutes?))\s*$/", $dateStr);

                if (!empty($date)) {
                    $e->booked()
                        ->start($this->normalizeDate($date . (!empty($time) ? ', ' . $time : '')));
                }

                if (!empty($e->getStartDate()) && !empty($duration)) {
                    $e->booked()
                        ->end(strtotime('+ ' . $duration, $e->getStartDate()));
                } else {
                    $e->booked()
                        ->noEnd();
                }
            }
        }

        // Price
        $total = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Customer Details'))}]/following::text()[{$this->eq($this->t('Total'))}][last()]/ancestor::tr[1]/descendant::td[last()]//text()[normalize-space()]"));
        $total = preg_replace("/(?:^|\n)(\d{1,3}(?:[,.]*\d{3)*)\n(\d{2})(\n\D*|\s*)$/", '$1.$2$3', $total);

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        } else {
            $email->price()
                ->total(null);
        }

        $taxesXpath = "//*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]]/following-sibling::*[normalize-space()][1][following-sibling::*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]]]/descendant::tr[not(.//tr)]";
        $fares = [];
        $isFares = true;
        $taxes = [];

        foreach ($this->http->XPath->query($taxesXpath) as $i => $tRoot) {
            $name = $this->http->FindSingleNode("*[1]", $tRoot);
            $value = $this->http->FindSingleNode("*[2]", $tRoot);
            $amount = PriceHelper::parse($this->re("/^\D*(\d[\d,. ]*?)\D*$/", $value));

            if ($isFares && stripos($name, ' × ') !== false) {
                $fares[] = $amount;

                continue;
            }

            if (preg_match('/^\s*-\s*\S/', $value)) {
                $isFares = false;

                continue;
            }

            if ($i === 0) {
                $fares[] = $amount;

                continue;
            }
            $taxes[$name] = $amount;

            if ($isFares) {
                $isFares = false;
            }
        }

        foreach ($taxes as $name => $value) {
            $email->price()
                ->fee($name, $value);
        }

        if (!empty($fares)) {
            $email->price()
                ->cost(array_sum($fares));
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailDate = strtotime($parser->getDate());

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $year = date('Y', $this->emailDate);

        if ($year < 2015) {
            $year = '';
        }

        $in = [
            // 28 July, 2024
            '/^\s*(\d+)\s+([[:alpha:]\-]+)\s*,\s*(\d{4})\s*$/ui',
            // 28 July, 2024 12:00 PM
            '/^\s*(\d+)\s+([[:alpha:]]+)\s*,\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
            // Sun 18 Sep, 4:40 PM
            '/^\s*([[:alpha:]\-]+)\s+(\d+)\s+([[:alpha:]]+)\s*\W\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
            // Sat. 7 Sep. , 2:00 p.m.
            '/^\s*([[:alpha:]\-]+)\.?\s+(\d+)\s+([[:alpha:]]+)\.?\s*\W\s*(\d{1,2}:\d{2}\s*[ap])\.m\.\s*$/ui',
            // Sun 18 Sep
            '/^\s*([[:alpha:]\-]+)\s+(\d+)\s+([[:alpha:]]+)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4',
            '$1, $2 $3 ' . $year . ', $4',
            '$1, $2 $3 ' . $year . ', $4m',
            '$1, $2 $3 ' . $year,
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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
        $sym = [
            'CA$'=> 'CAD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }
}
