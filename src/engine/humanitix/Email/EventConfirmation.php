<?php

namespace AwardWallet\Engine\humanitix\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventConfirmation extends \TAccountChecker
{
    public $mailFiles = "humanitix/it-752346244.eml, humanitix/it-754836470.eml, humanitix/it-755570499.eml, humanitix/it-762247142.eml, humanitix/it-763486541.eml, humanitix/it-774518768.eml";
    public $subjects = [
        'Order confirmation for',
        'Your tickets for',
    ];

    public $date = null;
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'here is your order confirmation for' => ['here is your order confirmation for', 'here is your ticket for', 'Here\'s your order confirmation for'],
            'Name'                                => ['Name', 'Buyer name'],
            'Total Sales tax'                     => ['Total Sales tax', 'Total GST', 'Sales tax included in total'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@humanitix.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]humanitix\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Humanitix'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('here is your order confirmation for'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Present this QR code'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('View your ticket below where you can'))}]")->length > 0)
            && ($this->http->XPath->query("//text()[{$this->eq($this->t('Event details'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->eq($this->t('Add to calendar'))}]")->length > 0)
        ) {
            return true;
        }

        return false;
    }

    public function ParseEventPDF(Email $email, $text = null)
    {
        $e = $email->add()->event();
        $e->setEventType(EVENT_EVENT);

        // collect reservation confirmation
        $orderDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order date'))}]/ancestor::div[normalize-space()][1]", null, true, "/^{$this->opt($this->t('Order date'))}\:\s*([\w\s]+)\s*$/m")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order summary'))}]/following::div[{$this->contains($this->t('Order date'))}][1]", null, true, "/^{$this->opt($this->t('Order date'))}\:\s*([\w\s]+)\s*$/m");

        if (!empty($orderDate)) {
            $e->general()
                ->date($this->normalizeDate($orderDate));
        }

        $numberText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order summary'))}]/following::div[{$this->contains($this->t('Order number'))}][1]")
            ?? $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Order number'))}])[1]/ancestor::p[normalize-space()][1]");

        if (preg_match("/^(?<desc>{$this->opt($this->t('Order number'))})\:\s*(?<number>\w{8})\s*$/m", $numberText, $m)) {
            $e->general()
                ->confirmation($m['number'], $m['desc']);
        }

        // collect event name
        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('here is your order confirmation for'))}]/ancestor::*[normalize-space()][1]/following-sibling::h2[normalize-space()][1]");

        if (!empty($name)) {
            $e->setName($this->removeEmoji($name));
        }

        // collect address
        $address = $this->http->FindSingleNode("(//img[contains(@src, 'ic_location.png')])[1]/following-sibling::*[normalize-space()][1]");

        if (!empty($address)) {
            $e->setAddress($address);
        }

        // collect startDate and EndDate
        $dateText = $this->http->FindSingleNode("(//img[contains(@src, 'ic_calendar.png')])[1]/following-sibling::*[normalize-space()][1]");
        $dates = preg_split("/\s+\-\s+/", $dateText);
        $startDate = $dates[0];
        $endDate = $dates[1];

        if (!empty($startDate)) {
            $startDate = $this->normalizeDate($startDate);
            $e->setStartDate($startDate);
        }

        if (preg_match("/^(?:(?<date>[\w\s]+?)\,)?\s*(?<time>\d+(?:\:\d+)?\s*\w{2}(?:\s+\d{4})?)\s+\w{3,4}.*$/m", $endDate, $m)) {
            if (!empty($m['date'])) {
                $endDate = $m['date'] . ', ' . $m['time'];
            } else {
                $endDate = date("d M Y", $startDate) . ', ' . $m['time'];
            }
            $endDate = $this->normalizeDate($endDate, $startDate);

            if ($endDate < $startDate) {
                $endDate = strtotime('+1 year', $endDate);
            }
            $e->setEndDate($endDate);
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order summary'))}]/following::div[{$this->contains($this->t('Name'))}][1]", null, true, "/^{$this->opt($this->t('Name'))}\:\s+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/m")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('here is your order confirmation for'))}]/ancestor::*[normalize-space()][1]", null, true, "/^{$this->opt($this->t('You\'re in'))}?\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[\,\.]/m");

        if (!empty($traveller)) {
            $e->addTraveller($traveller);
        }

        // collect total and currency from html
        if (empty($text)) {
            $totalText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total order price'))}]/ancestor::div[normalize-space()][1]");

            if (preg_match("/^{$this->opt($this->t('Total order price'))}\:\s+(?<currency>\D)\s*(?<total>[\d\.\,\']+)\s*$/m", $totalText, $m)) {
                $e->price()
                    ->total(PriceHelper::parse($m['total'], $this->normalizeCurrency($m['currency'])))
                    ->currency($this->normalizeCurrency($m['currency']));
            }

            return;
        }

        // first pdf format
        $currency = null;

        if ($this->re("/({$this->opt($this->t('Payment method'))})/s", $text) !== null
            && $this->re("/({$this->opt($this->t('Customer name'))})/s", $text) !== null
        ) {
            // collect total and currency from pdf
            if (preg_match("/{$this->opt($this->t('Total'))}\s+\D\s*(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})\s+/i", $text, $m)) {
                $e->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
                $currency = $m['currency'];
            }

            if (empty($currency)) {
                return;
            }

            // collect cost from pdf
            $cost = $this->re("/{$this->opt($this->t('Unit price'))}.+?\n.+?{$this->opt($this->t('Total'))}\s+(?!{$this->opt($this->t('GST'))})[A-Z]{3}\s+\D\s*([\d\.\,\']+)(?:\s|$)/si", $text);

            if ($cost !== null) {
                $e->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }
        }
        // second pdf format
        else {
            // collect total and currency from pdf
            if (preg_match("/{$this->opt($this->t('Total Amount Due'))}\s+(?<currency>\D)\s*(?<total>[\d\.\,\']+)\s*$/m", $text, $m)) {
                $total = $m['total'];
                $currency = $this->normalizeCurrency($m['currency']);
            }
            // specify currency
            $currency = $this->re("/{$this->opt($this->t('All dollar amounts are in'))}\s+([A-Z]{3})(?:\s|$)/s", $text) ?? $currency;

            if (empty($currency)) {
                return;
            }

            if ($total !== null) {
                $e->price()
                    ->total(PriceHelper::parse($total, $currency))
                    ->currency($currency);
            }

            // collect cost from pdf
            $cost = $this->re("/{$this->opt($this->t('Unit price'))}.+?\n.+?\D\s*([\d\.\,\']+)\s*?\n/si", $text);

            if ($cost !== null) {
                $e->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }
        }

        // collect fees from pdf for both formats
        if (preg_match("/^\s*(?<feeName>{$this->opt($this->t('Total Sales tax'))})\s+\D\s*(?<feeValue>[\d\.\,\']+)\s*$/m", $text, $m)) {
            $m['feeName'] = $m['feeName'] == 'Sales tax included in total' ? 'Sales tax' : $m['feeName'];
            $e->price()
                ->fee($m['feeName'], PriceHelper::parse($m['feeValue'], $currency));
        }

        $feesText = $this->re("/({$this->opt($this->t('Humanitix booking fee'))}.+?)\s+(?:{$this->opt($this->t('Total'))}\s+[A-Z]{3}|{$this->opt($this->t('Total Sales tax'))}\s+)/s", $text);
        $fees = array_filter(explode("\n", $feesText));

        foreach ($fees as $fee) {
            if (preg_match("/^\s*(?<feeName>.+?)\s{5,}.+\s+\D\s*(?<feeValue>[\d\.\,\']+)(?:\s|$)/um", $fee, $m)) {
                $e->price()
                    ->fee($m['feeName'], PriceHelper::parse($m['feeValue'], $currency));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        // if the address is missing (online event) or to be announced or see event for more details
        // example - it-752346244.eml
        if ($this->http->XPath->query("//img[contains(@src, 'location.png')]/following-sibling::*[normalize-space()][1]")->length == 0
            || $this->http->XPath->query("//img[contains(@src, 'location.png')]/following-sibling::*[{$this->eq($this->t('To be announced'))}]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, 'location.png')]/following-sibling::*[{$this->eq($this->t('See event for more details'))}]")->length > 0
        ) {
            $email->setIsJunk(true);

            return $email;
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseEventPDF($email, $text);
        }

        if (empty($pdfs)) {
            $this->ParseEventPDF($email);
        }

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str, $date = null)
    {
        $year = date("Y", $date ?? $this->date);

        $in = [
            "#^(\w+)\,\s+(\w+)\s+(\d+)\,\s+(\d+(?:\:\d+)?\s*\w{2})$#u", // Tue, Oct 15, 12:15pm | Fri, Oct 25, 9pm
            "#^(\w+)\,\s+(\d+)\s+(\w+)\,\s+(\d+(?:\:\d+)?\s*\w{2})$#u", // Wed, 27 Nov, 5:30pm | Fri, 25 Oct, 9pm
            "#^\w+\s+(\w+)\s+(\d+)(?:\w{2})?\s+(\d{4})\,\s+(\d+(?:\:\d+)?\s*\w{2})$#u", // Fri Oct 27th 2023, 7:00 pm
            "#^(\d+)(?:\w{2})?\s+(\w+)\,\s+(\d+(?:\:\d+)?\s*\w{2})$#u", //27 Nov, 5:30pm | 25 Oct, 9pm
            "#^(\w+)\s+(\d+)(?:\w{2})?\,\s+(\d+(?:\:\d+)?\s*\w{2})$#u", //Nov 27, 5:30pm | Oct 25, 9pm
            "#^(\w+)\s+(\d+)(?:\w{2})?\,\s+(\d+(?:\:\d+)?\s*\w{2})\s+(\d{4})$#u", // Feb 27, 5pm 2025
        ];
        $out = [
            "$1, $3 $2 $year, $4",
            "$1, $2 $3 $year, $4",
            "$2 $1 $3, $4",
            "$1 $2 $year, $3",
            "$2 $1 $year, $3",
            "$2 $1 $4, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\D+)(\s+\d{4})?\,\s+(\d+(?:\:\d+)?\s*\w{2})$#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("/^(?<week>\w{3})\,\s+(?<date>\d+\s+\w+\s+.+)/ui", $str, $m)) {
            $m['week'] = str_replace(' ', '', $m['week']);
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
            '$'         => '$',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }

    private function removeEmoji($string)
    {
        $emojiRegex = [
            '/[\x{1F100}-\x{1F1FF}]/u',   // Match Enclosed Alphanumeric Supplement
            '/[\x{1F300}-\x{1F5FF}]/u',   // Match Miscellaneous Symbols and Pictographs
            '/[\x{1F600}-\x{1F64F}]/u',   // Match Emoticons
            '/[\x{1F680}-\x{1F6FF}]/u',   // Match Transport And Map Symbols
            '/[\x{1F900}-\x{1F9FF}]/u',   // Match Supplemental Symbols and Pictographs
            '/[\x{2600}-\x{26FF}]/u',     // Match Miscellaneous Symbols
            '/[\x{2700}-\x{27BF}]/u',     // Match Dingbats
        ];

        foreach ($emojiRegex as $regex) {
            $string = preg_replace($regex, '', $string);
        }

        return preg_replace('/\s+/', ' ', $string);
    }
}
