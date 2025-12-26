<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketsReady extends \TAccountChecker
{
    public $mailFiles = "ticketmaster/it-161510955.eml";
    public $detectSubjects = [
        'Ticket Order',
    ];

    public $lang = 'en';
    public $date;
    public static $dictionary = [
        "en" => [
            ", Your Tickets Are Ready" => [", Your Tickets Are Ready", ", Your Order Is Being Processed"],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@email.ticketmaster.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ( $this->http->XPath->query("//a[contains(@href, '.ticketmaster.com')]")->length < 3) {
            return false;
        }

        if ($this->http->XPath->query("//*[starts-with(normalize-space(), 'This email is NOT your ticket')]/ancestor::*/following-sibling::*[starts-with(normalize-space(), 'Order Status:') and contains(., 'Order #:')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.ticketmaster.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());


        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'Order #:']/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\-]{5,})$/"))
            ->traveller($this->http->FindSingleNode("//text()[".$this->contains($this->t(", Your Tickets Are Ready"))."]", null, true, "/^\s*(\D+?)\s*{$this->opt($this->t(", Your Tickets Are Ready"))}\s*$/"), false);

        $eventText = implode("\n", $this->http->FindNodes("//*[starts-with(normalize-space(), 'This email is NOT your ticket')]/ancestor::*[following-sibling::*[starts-with(normalize-space(), 'Order Status:') and contains(., 'Order #:')]]//text()[normalize-space()]"));
        if (preg_match("/^\s*(?<name>.+)\s*\n\s*(?<date>.+@.+)\s*\n\s*(?<address>.+)/", $eventText, $m)) {

            $e->place()
                ->name($m['name'])
                ->address($m['address'])
            ;
            $e->booked()
                ->start($this->normalizeDate($m['date']))
                ->noEnd()
            ;
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='You Paid:']/ancestor::tr[1]/descendant::td[last()]", null, true, "/^\D*\s*([\d\.,]+)\s*/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='You Paid:']/ancestor::tr[1]/descendant::td[last()]", null, true, "/^\s*([\D\s]+)\s*[\d\.,]+/");

        if (!empty($total) && !empty($currency)) {
            $e->price()
                ->total(PriceHelper::parse($total))
                ->currency($this->normalizeCurrency($currency));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);

        $in = [
            // Wed 07 April @  8:30am
            "/^\s*(\w+),\s*(\w+\s*\d{1,2})\s*[@]\s*([\d\:]+\s*[ap]m)\s*$/i",
        ];
        $out = [
            "$1, $2 {$year}, $3",
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
