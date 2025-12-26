<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RailETicket extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-153034261.eml, onbusiness/statements/it-64389900.eml";
    public $subjects = [
        'MakeMyTrip rail e-ticket for booking id',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'railservice@makemytrip.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Railservice@MakeMyTrip.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'MakeMyTrip ID:')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Fare Details:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger Details:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/railservice[@]makemytrip\.com$/', $from) > 0;
    }

    public function ParseRail(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Passenger Details:']/following::text()[normalize-space()='Name'][1]/ancestor::table[1]/descendant::tr/td[2][not(contains(normalize-space(), 'Name'))]"), true)
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='PNR NO.']/ancestor::td[1]", null, true, "/{$this->opt($this->t('PNR NO.'))}\s*([A-Z\d]+)$/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Booking Date:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Booking Date:'))}\s*(.+)/")));

        $xpath = "//text()[normalize-space()='PNR NO.']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $trainInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='No. & Name:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('No. & Name:'))}\s*(.+)/");

            if (preg_match("/^(\d+)[\s\-]+(.+)$/u", $trainInfo, $m)) {
                $s->setNumber($m[1]);
                $s->setServiceName($m[2]);
            }

            $depDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Boarding Date:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Boarding Date:'))}\s*(.+)/");
            $depTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Scheduled Departure Time:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Scheduled Departure Time:'))}\s*(.+)/");

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='From:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('From:'))}\s*(.+)/"))
                ->date(strtotime($depDate . ', ' . $depTime));

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='To:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('To:'))}\s*(.+)/"))
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()='Scheduled Arrival:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Scheduled Arrival:'))}\s*(.+)/")));

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::text()[normalize-space()='Class:']/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Class:'))}\s*(.+)/"));

            $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Fare']/ancestor::tr[1]/descendant::td[3]");

            if (preg_match("/^\s*(\D)\s*([\d\.]+)$/u", $price, $m)) {
                $t->price()
                    ->currency($this->normalizeCurrency($m[1]))
                    ->total(PriceHelper::parse($m[2], $this->normalizeCurrency($m[1])));

                $t->price()
                    ->cost($this->http->FindSingleNode("//text()[normalize-space()='Ticket fare**']/ancestor::tr[1]/descendant::td[3]", null, true, "/^\s*\D+([\d\.\,]+)/"));

                $xpathFees = "//text()[normalize-space()='Ticket fare**']/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Total Fare'))]";
                $nodeFees = $this->http->XPath->query($xpathFees);

                foreach ($nodeFees as $rootFee) {
                    $feeName = $this->http->FindSingleNode("./descendant::td[2]", $rootFee);
                    $feeSum = $this->http->FindSingleNode(".//descendant::td[3]", $rootFee, true, "/^\D+([\d\.\,]+)/");

                    if (!empty($feeName) && !empty($feeSum)) {
                        $t->price()
                            ->fee($feeName, $feeSum);
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[normalize-space()='MakeMyTrip ID:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('MakeMyTrip ID:'))}\s*([\dA-Z]+)$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->ParseRail($email);

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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs', '₹'],
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
