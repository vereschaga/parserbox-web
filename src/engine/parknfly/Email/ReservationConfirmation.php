<?php

namespace AwardWallet\Engine\parknfly\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "parknfly/it-201274535.eml, parknfly/it-85619404.eml";
    public $subjects = [
        '/Reservation Confirmation$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "PARK ‘N FLY"          => ["PARK ‘N FLY", "PARK 'N FLY", "Park 'N Fly Plus", "SeaTacPark.com"],
            "Your Reservation"     => ["Your Reservation", "You've successfully cancelled your reservation!", "Thank You For Choosing Us!"],
            'Payment Total'        => ['Payment Total', 'Total'],
            'Subtotal'             => ['Subtotal', 'Parking Fee'],
            'Taxes + Fees Details' => ['Taxes + Fees Details', 'Taxes & Fees'],
            'Cancelled'            => "You've successfully cancelled your reservation!",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@m-pnf.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('PARK ‘N FLY'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Reservation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Receipt'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]m\-pnf\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $e = $email->add()->parking();

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Billing Information']/following::text()[normalize-space()][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation number:']/following::text()[normalize-space()][1]"));

        $e->setLocation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Reservation'))}]/following::text()[normalize-space()='Facility:']/following::text()[normalize-space()][1]"))
            ->setAddress(implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Your Reservation'))}]/following::text()[normalize-space()='Facility:']/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'FLY'))]")));

        $e->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Arrival']/following::text()[normalize-space()][1]")))
            ->end($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Exit']/following::text()[normalize-space()][1]")));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Cancelled'))}]")->length > 0) {
            $e->general()
                ->cancelled();
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Total'))}]/following::text()[normalize-space()][1]");

        if (!empty($price)) {
            $e->price()
                ->total($this->re('/^\S([\d\.\,]+)/', $price))
                ->currency($this->normalizeCurrency($this->re('/^(\S)[\d\.\,]+/', $price)));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Subtotal'))}]/following::text()[normalize-space()][1]", null, true, "/^\S\s*([\d\.]+)$/");

        if (!empty($cost)) {
            $e->price()
                ->cost($cost);
        }

        $xpathFee = "//text()[{$this->eq($this->t('Taxes + Fees Details'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]/descendant::table/descendant::tr";
        $nodes = $this->http->XPath->query($xpathFee);

        if ($nodes->length > 0) {
            foreach ($nodes as $root) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1][not(contains(normalize-space(), '$'))]", $root);
                $feeSumm = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^\S\s*([\d\.\,]+)/");

                if (!empty($feeName) && !empty($feeSumm)) {
                    $e->price()
                        ->fee($feeName, $feeSumm);
                }
            }
        } else {
            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes + Fees Details'))}]/following::text()[normalize-space()][1]", null, true, "/^\S\s*([\d\.]+)$/");

            if (!empty($tax)) {
                $e->price()
                    ->tax($tax);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.', 'Rs'],
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#", // Sunday, Mar 28, 2021 at 08:30 AM
            "#^\w+\,\s*(\w+)\s*.+\/(\d+)\/(\d{4})\,.*\s*at\s*([\d\:]+\s*A?P?M)$#", // Thursday, May 5/12/2022, 2022 at 12:30 PM
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
}
