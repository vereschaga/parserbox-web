<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "ticketmaster/it-235725916.eml, ticketmaster/it-788501690.eml, ticketmaster/it-83635413.eml";
    public $subjects = [
        '/(?:You’re in! Your|You\'ve accepted)/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Ticket Transfer Summary' => 'Ticket Transfer Summary',
            'Order #'                 => ['Order #', ' / Ref:'],
            'Your Order'              => ['Your Order', 'Your Tickets'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.ticketmaster.com.au') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Ticketmaster')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Order'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Payment Summary'))} or {$this->eq($this->t('Ticket Transfer Summary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.ticketmaster\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $confs = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Order #'))}]", null, "/{$this->opt($this->t('Order #'))}\s*([A-Z\d\/\-]+)/u"));

        foreach ($confs as $conf) {
            $e->general()
                ->confirmation($conf);
        }

        $traveller = $this->http->FindSingleNode('//text()[contains(normalize-space(), "You\'re In")]', null, true, "/^(\D+)\,\s*{$this->opt($this->t("You're In"))}/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', a summary of your purchase.')]", null, true, "/^(.+)\s*{$this->opt($this->t(', a summary of your purchase'))}/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
                null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*(.+?),/");
        }
        $e->general()
            ->traveller($traveller, false);

        $e->setName($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order #'))} or {$this->eq($this->t('Ticket Transfer Summary'))}]/following::text()[normalize-space()][1]"));

        $e->setAddress($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order #'))} or {$this->eq($this->t('Ticket Transfer Summary'))}]/following::text()[normalize-space()][2]"));

        $e->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order #'))} or {$this->eq($this->t('Ticket Transfer Summary'))}]/following::text()[normalize-space()][3]")))
            ->noEnd()
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Order'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)[x]/"));

        $seats = $this->http->FindNodes("//text()[normalize-space()='SEAT']/ancestor::tr[2]/descendant::tr[normalize-space()]");
        $seatsText = implode(' ', $seats);

        if (preg_match_all("/(\w+\s*\w+\s+ROW\s*\w+\s*SEAT\s*\d+)/", $seatsText, $m)) {
            $e->setSeats($m[1]);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[last()]", null, true, "/\D*\s*([\d\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[last()]", null, true, "/^([\D\s]+)[\d\.]+/");

        if (!empty($total) && !empty($currency)) {
            $e->price()
                ->total(cost($total))
                ->currency($this->normalizeCurrency($currency));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+\s*\w+\s*\d{4})\s*[@\-]\s*([\d\:]+(?:\s*[ap]m)?)$#", // Wed 07 April 2021 @  8:30am
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
            'NZD' => ['NZ $'],
            'MXN' => ['MX $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
