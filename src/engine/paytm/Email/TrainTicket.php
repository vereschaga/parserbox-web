<?php

namespace AwardWallet\Engine\paytm\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainTicket extends \TAccountChecker
{
    public $mailFiles = "paytm/it-68748721.eml, paytm/it-68748722.eml";
    public $subjects = [
        '/^Paytm Trains - Cancellation Confirmation$/',
        '/^Paytm- Your Train Ticket \:/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Boarding Time & Station' => ['Boarding Time & Station', 'Boarding Station & Time'],
            'Arrival Time & Station'  => ['Arrival Time & Station', 'Arrival Station & Time'],
            'Total Fare'              => ['Total Fare', 'Total Amount paid for cancelled Traveller(s)'],
            'Ticket Fare'             => ['Ticket Fare', 'Refundable Amount'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@paytm.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Paytm Booking ID')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TRAINS'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Train Name & No'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Boarding Time & Station'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]paytm\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $t = $email->add()->train();

        $travellers = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Name, Gender & Age']/ancestor::tr[1]/following::tr[starts-with(normalize-space(), '#')]/descendant::text()[normalize-space()][2]", null, "/^(\D+)\,\s*[A-Z]{1}\,/"));

        if (count($travellers) == 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[normalize-space()='Traveller Name']/ancestor::tr[1]/following-sibling::tr/td[1]"));
        }

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'PNR No']/ancestor::tr[1]/following::tr[1]/descendant::td[1]"))
            ->travellers($travellers);

        if (!empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We have confirmed the cancellation of below travellers')]"))) {
            $t->general()
                ->cancelled()
                ->status('canceled');
        }

        $s = $t->addSegment();

        $s->setNumber($this->http->FindSingleNode("//text()[normalize-space() = 'Train Name & No.']/following::text()[normalize-space()][1]", null, true, "/^\D+\/?\-?\s*(\d+)/"));

        $depText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Boarding Time & Station'))}]/ancestor::tr[1]/descendant::td[1]");
        $this->logger->warning($depText);

        if (preg_match("/{$this->opt($this->t('Boarding Time & Station'))}\s+(?<depTime>[\d\:]+)\s+hrs\s+(?<depDate>\d+\s+\w+\s+\d{4})\s+(?<depName>.+)\s*$/", $depText, $m)
            || preg_match("/{$this->opt($this->t('Boarding Time & Station'))}\s+(?<depName>.+)\s+(?<depDate>\d+\s+\w+\s+\d{4})\,?\s+(?<depTime>[\d\:]+)\s+hrs\s*$/", $depText, $m)) {
            $s->departure()
                ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                ->name($m['depName']);
        }

        $arrText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Boarding Time & Station'))}]/ancestor::tr[1]/descendant::td[last()]");
        $this->logger->warning($arrText);

        if (preg_match("/{$this->opt($this->t('Arrival Time & Station'))}\s+(?<arrTime>[\d\:]+)\s+hrs\s+(?<arrDate>\d+\s+\w+\s+\d{4})\s+(?<arrName>.+)\s*$/", $arrText, $m)
            || preg_match("/{$this->opt($this->t('Arrival Time & Station'))}\s+(?<arrName>.+\))\s*(?<arrDate>\d+\s+\w+\s+\d{4})\,?\s*(?<arrTime>[\d\:]+)\s+hrs\s*$/u", $arrText, $m)) {
            $s->arrival()
                ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                ->name($m['arrName']);
        }

        $seats = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Name, Gender & Age']/ancestor::tr[1]/following::tr[starts-with(normalize-space(), '#')]/descendant::td[5]", null, "/\/\s*(\d+)/"));

        if (count($seats) > 0) {
            $s->extra()
                ->seats($seats);
        }

        $duration = $this->http->FindSingleNode("//text()[normalize-space() = 'Boarding Time & Station']/ancestor::tr[1]/descendant::td[2]", null, true, "/{$this->opt($this->t('Duration'))}\s*(.+)/");

        if (!empty($duration)) {
            $s->extra()
                ->duration($duration);
        }

        $cabin = $this->http->FindSingleNode("//text()[normalize-space() = 'Class/Quota']/following::text()[normalize-space()][1]", null, true, "/^(.+)\s*\//");

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Fare'))}]/following::text()[normalize-space()][1]", null, true, "/^(\S{3})\s*[\d\.]+$/");
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Fare'))}]/following::text()[normalize-space()][1]", null, true, "/^\S{3}\s*([\d\.]+)$/");

        if (!empty($currency) && !empty($total)) {
            $t->price()
                ->total($total)
                ->currency($this->normalizeCurrency($currency));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket Fare'))}]/following::text()[normalize-space()][1]", null, true, "/^\S{3}\s*([\d\.]+)$/");

        if (!empty($cost)) {
            $t->price()
                ->cost($cost);
        }

        $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Total Fare'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), '(-)')]");

        if ($feeNodes->count() == 0) {
            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket Fare'))}]/ancestor::tr[1]/following-sibling::tr");
        }

        foreach ($feeNodes as $root) {
            $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
            $this->logger->warning($feeName);
            $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^(?:\(\-\)\s*)?\S{3}\s*([\d\.]+)$/");

            if (!is_null($feeSum) && !empty($feeName)) {
                $t->price()
                    ->fee($feeName, $feeSum);
            }
        }

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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US Dollar'],
            'INR' => ['Rs.'],
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }
}
