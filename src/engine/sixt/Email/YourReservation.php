<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "sixt/it-96637298.eml";
    public $subjects = [
        '/Your reservation with Sixt is confirmed/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'reservation is' => ['reservation is', 'reservation has'],
            'Booking period' => ['Booking period', 'Daily rate', 'Rental period']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@lyftmail.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sixt support') or contains(normalize-space(), 'sixt.com') or contains(normalize-space(), 'Lyft Rentals')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Pickup location'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reminders'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]lyftmail\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation']/following::text()[normalize-space()][1]", null, true, "/[#]\s*([A-Z\d]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thanks')]", null, true, "/{$this->opt($this->t('Thanks'))}\s*(\w+)\,/"), false);

        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'reservation is')]", null, true, "/{$this->opt($this->t('reservation is'))}\s*(\w+)\./");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        $r->car()
            ->image($this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation is'))}]/following::img[contains(@alt, 'car')][1]/@src"))
            ->type($this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation is'))}]/following::img[contains(@alt, 'car')][1]/following::text()[normalize-space()][1]"));

        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Dates']/following::text()[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Pickup location']/following::text()[normalize-space()][1]"));

        $r->dropoff()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Dates']/following::text()[normalize-space()][2]")))
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Return location']/following::text()[normalize-space()][1]"));

        $r->price()
            ->total($this->http->FindSingleNode("//text()[normalize-space()='Estimated Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D([\d\.\,]+)/"))
            ->currency($this->http->FindSingleNode("//text()[normalize-space()='Estimated Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\D)\d+/"));

        $r->price()
            ->cost($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking period'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D([\d\.\,]+)/"));

        $xpath = "//text()[{$this->starts($this->t('Booking period'))}]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Estimated Total') or contains(normalize-space(), 'FREE') or contains(normalize-space(), 'discount') or contains(normalize-space(), 'Discount'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $feeName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);
            $feeSumm = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root, true, "/^\D+([\d\.\,]+)/");

            if (!empty($feeSumm) && !empty($feeName)) {
                $r->price()
                    ->fee($feeName, $feeSumm);
            }
        }

        $discount = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Booking period'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'Discount') or contains(normalize-space(), 'discount')]/descendant::td[2]", null, "/^\-\D([\d\.\,]+)/"));
        if (count($discount) > 0)
            $r->price()
                ->discount(array_sum($discount));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Lyft Rentals')]")->length > 0)
            $email->setProviderCode('lyft');

        $this->ParseCar($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['sixt', 'lyft'];
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

    private function normalizeDate($str)
    {
        $year = isset($this->date) ? date("Y", $this->date) : date("Y");
        $in = [
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*([\d\:]+\s*A?P?M)$#", //Thu Dec 19 08:25 PM
        ];
        $out = [
            "$2 $1 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
