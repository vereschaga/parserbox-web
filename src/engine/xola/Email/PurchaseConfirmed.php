<?php

namespace AwardWallet\Engine\xola\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PurchaseConfirmed extends \TAccountChecker
{
    public $mailFiles = "xola/it-705035247.eml, xola/it-708221142.eml, xola/it-713714770.eml";
    public $detectSubject = [
        'Purchase Confirmed:',
        'Your purchase has been updated',
        'Reminder - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your purchase for'     => ['Your purchase for', 'Just a friendly reminder!'],
            'Purchase Confirmation' => 'Purchase Confirmation',
            'Adults'                => ['Adults', 'Guests', 'Adult', 'Guest'],
            'Purchase canceled for' => 'Purchase canceled for',
            'Purchase ID:'          => 'Purchase ID:',
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
        if ($this->http->XPath->query("//text()[{$this->contains(['@xola.com', 'Xola, Inc.'])}]")->length === 0
            && $this->http->XPath->query("//a/@href[{$this->contains(['.xola.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your purchase for']) && !empty($dict['Purchase Confirmation'])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Your purchase for'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Purchase Confirmation'])}]")->length > 0
            ) {
                return true;
            }

            if (!empty($dict['Your purchase for']) && !empty($dict['Purchase Confirmation'])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Purchase canceled for'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Purchase ID:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]xola\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $e = $email->add()->event();

        $e->type()->event();

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Purchase ID:'))}]", null, true,
                "/{$this->opt($this->t('Purchase ID:'))}\s*(\w{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Purchase ID:'))}]", null, true,
                    "/({$this->opt($this->t('Purchase ID:'))})\s*\w{5,}\s*$/"), ':'))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Information'))}]/following::text()[normalize-space()][1]"), true);
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('has been'))}]/ancestor::*[{$this->starts($this->t('Your purchase for'))}][1]", null, true,
                "/{$this->opt($this->t('has been'))}\s*([[:alpha:]]+)\s*$/");

        if (!empty($status)) {
            $e->general()
                ->status($status);
        }

        $conf = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation Code'))}])[1]", null, true,
            "/{$this->opt($this->t('Confirmation Code'))}\s*:?\s*([A-Z\d]{5,})\s*(?:\||$)/");

        if (!empty($conf)) {
            $e->general()
                ->confirmation($conf, $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation Code'))}])[1]", null, true,
                    "/({$this->opt($this->t('Confirmation Code'))})/"));
        }

        // Place
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your purchase for'))}]/ancestor::*[1]", null, true,
            "/{$this->opt($this->t('Your purchase for'))}\s+(.+?)\s*{$this->opt($this->t('has been'))}/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Manage this purchase'))}]/preceding::text()[normalize-space()][1]/ancestor::table[count(.//text()[normalize-space()]) > 2][1]//h2");
        }
        $e->place()
            ->name($name);

        $address = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Meeting Location'))}]/following-sibling::*[normalize-space()][1]");
        $e->place()
            ->address($address);

        $eventName = $e->getName();

        if (empty($eventName)) {
            $eventName = 'false()';
        }
        $gXpath = "//text()[{$this->eq($eventName)}]/ancestor::*[not({$this->eq($eventName)})][1]";
        $guests = $this->http->FindSingleNode($gXpath . "//text()[{$this->contains($this->t('Adults'))}][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Adults'))}/");
        $kids = $this->http->FindSingleNode($gXpath . "//text()[{$this->contains($this->t('Children'))}][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Children'))}/");

        if (!empty($guests)) {
            $e->booked()
                ->guests($guests);
        }

        if (!empty($kids)) {
            $e->booked()
                ->kids($kids);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->eq($eventName)}]/following::text()[normalize-space()][1]");
        $startTime = $this->http->FindSingleNode("//text()[{$this->eq($eventName)}]/following::text()[normalize-space()][2]",
            null, true, "/^\s*\d{1,2}:\d{2}.*/");
        $endTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Information'))}]/preceding::text()[normalize-space()][position() < 7][{$this->starts($this->t('Ends'))}]",
            null, true, "/{$this->opt($this->t('Ends'))}\s*(\d{1,2}:\d{2}.*)/");

        if (!empty($date)) {
            $e->booked()
                ->start($this->normalizeDate($date . (!empty($startTime) ? ', ' . $startTime : '')))
            ;
        }

        if (!empty($date) && !empty($startTime) && !empty($endTime)) {
            $enddate = $this->normalizeDate($date . (!empty($endTime) ? ', ' . $endTime : ''));

            if ($enddate < $e->getStartDate() && strtotime('+ 1 day ', $enddate) > $e->getStartDate()) {
                $enddate = strtotime('+ 1 day ', $enddate);
            }
            $e->booked()
                ->end($enddate);
        } else {
            $e->booked()
                ->noEnd();
        }

        // Price
        $total = $this->http->FindSingleNode("//*[{$this->eq($this->t('Payment Summary'))}]/following-sibling::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]][last()]/descendant::text()[normalize-space()][2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $e->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($this->currency($m['currency']))
            ;
        } else {
            $e->price()
                ->total(null);
        }

        $taxesXpath = "//*[{$this->eq($this->t('Payment Summary'))}][following-sibling::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]]]/ancestor::*[1]/*[count(.//text()[normalize-space()]) > 1][following-sibling::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]]]/descendant::tr[not(.//tr)]";
        $fares = [];
        $isFares = true;
        $taxes = [];
        $totalCount = 0;

        foreach ($this->http->XPath->query($taxesXpath) as $i => $tRoot) {
            $name = $this->http->FindSingleNode("*[1]", $tRoot);
            $value = $this->http->FindSingleNode("*[normalize-space()][2]", $tRoot);
            $amount = PriceHelper::parse($this->re("/^\D*(\d[\d,. ]*?)\D*$/", $value));

            if (preg_match("/^\s*{$this->opt($this->t('Total'))}\s*$/", $name)) {
                $totalCount++;

                if ($totalCount == 2) {
                    break;
                }
                $isFares = false;

                continue;
            }

            if ($isFares && preg_match('/\s+x\s*\d+\)\s*$/', $name)) {
                $fares[] = $amount;

                continue;
            } elseif ($isFares && $i > 0) {
                $isFares = false;
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
        }

        foreach ($taxes as $name => $value) {
            $e->price()
                ->fee($name, $value);
        }

        if (!empty($fares)) {
            $e->price()
                ->cost(array_sum($fares));
        }

        return true;
    }

    public function ParseEmailCanceled(Email $email)
    {
        $e = $email->add()->event();

        $e->type()->event();

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Purchase ID:'))}]", null, true,
                "/{$this->opt($this->t('Purchase ID:'))}\s*(\w{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Purchase ID:'))}]", null, true,
                    "/({$this->opt($this->t('Purchase ID:'))})\s*\w{5,}\s*$/"), ':'))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Information'))}]/following::text()[normalize-space()][1]"), true)
            ->status('Canceled')
            ->cancelled()
        ;

        $conf = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation Code'))}])[1]", null, true,
            "/{$this->opt($this->t('Confirmation Code'))}\s*:?\s*([A-Z\d]{5,})\s*(?:\||$)/");

        if (!empty($conf)) {
            $e->general()
                ->confirmation($conf, $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation Code'))}])[1]", null, true,
                    "/({$this->opt($this->t('Confirmation Code'))})/"));
        }

        // Place
        $e->place()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Purchase canceled for'))}]/ancestor::*[1]", null, true,
                "/{$this->opt($this->t('Purchase canceled for'))}\s+(.+?)\s*\.\s*/"))
        ;

        $eventName = $e->getName();

        if (empty($eventName)) {
            $eventName = 'false()';
        }
        $gXpath = "//text()[{$this->eq($eventName)}]/ancestor::*[not({$this->eq($eventName)})][1]";
        $guests = $this->http->FindSingleNode($gXpath . "//text()[{$this->contains($this->t('Adults'))}][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Adults'))}/");
        $kids = $this->http->FindSingleNode($gXpath . "//text()[{$this->contains($this->t('Children'))}][1]", null, true, "/(\d+)\s*{$this->opt($this->t('Children'))}/");

        if (!empty($guests)) {
            $e->booked()
                ->guests($guests);
        }

        if (!empty($kids)) {
            $e->booked()
                ->kids($kids);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->eq($eventName)}]/following::text()[normalize-space()][1]");
        $startTime = $this->http->FindSingleNode("//text()[{$this->eq($eventName)}]/following::text()[normalize-space()][2]",
            null, true, "/^\s*\d{1,2}:\d{2}.*/");
        $endTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Information'))}]/preceding::text()[normalize-space()][position() < 7][{$this->starts($this->t('Ends'))}]",
            null, true, "/{$this->opt($this->t('Ends'))}\s*(\d{1,2}:\d{2}.*)/");

        if (!empty($date)) {
            $e->booked()
                ->start($this->normalizeDate($date . (!empty($startTime) ? ', ' . $startTime : '')))
            ;
        }

        if (!empty($date) && !empty($startTime) && !empty($endTime)) {
            $enddate = $this->normalizeDate($date . (!empty($endTime) ? ', ' . $endTime : ''));

            if ($enddate < $e->getStartDate() && strtotime('+ 1 day ', $enddate) > $e->getStartDate()) {
                $enddate = strtotime('+ 1 day ', $enddate);
            }
            $e->booked()
                ->end($enddate);
        } else {
            $e->booked()
                ->noEnd();
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Purchase canceled for'))}]")->length > 0) {
            $this->ParseEmailCanceled($email);
        } else {
            $this->ParseEmail($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // Sun 18 Sep • 4:40 PM
            '/^\s*[[:alpha:]\-]+\s+(\d+)\s+([[:alpha:]]+)\s*[,\s]\s*(\d{4})\s*\W\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3 , $4',
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
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'CA$' => 'CAD',
            '$'   => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
