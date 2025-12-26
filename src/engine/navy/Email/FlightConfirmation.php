<?php

namespace AwardWallet\Engine\navy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "navy/it-706727490.eml, navy/it-769379943.eml";
    public $subjects = [
        'Navy Federal Rewards Flight Summary',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rewards.navyfederal.org') !== false) {
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
        return preg_match('/[@.]navyfederal\.org$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Navy Federal Rewards'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departing Flight'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Your total reservation cost'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // collect reservation confirmation
        $confirmationText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation number is'))}]/ancestor::td[normalize-space()][1]");

        if (preg_match("/^\s*.+?(?<status>{$this->opt($this->t('Booked'))})\s*\!\s*(?<desc>{$this->opt($this->t('Your reservation number is'))})\:\s*(?<number>\w+)\s*$/m", $confirmationText, $m)) {
            $f->general()
                ->confirmation($m['number'], $m['desc'])
                ->status($m['status']);
        }

        $airNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Duration'))}]/ancestor::table[1]/following-sibling::table[1]/descendant::td[{$this->contains($this->t('Flight'))}]");

        foreach ($airNodes as $root) {
            $s = $f->addSegment();

            // collect airline name and flight number
            $airInfo = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^{$this->opt($this->t('Flight'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s+(?<cabin>\w+)\s*$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
                $s->extra()
                    ->cabin($m['cabin']);
            }

            // collect departure info
            $depInfo = $this->http->FindSingleNode("./preceding::tr[1]/td[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<depPoint>.+?)\s*\((?<depCode>\w{3})\)\s+(?<depDate>.+)\s*$/mi", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depPoint'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate']));
            }

            // collect arrival info
            $arrInfo = $this->http->FindSingleNode("./preceding::tr[1]/td[normalize-space()][2]", $root);

            if (preg_match("/^\s*(?<arrPoint>.+?)\s*\((?<arrCode>\w{3})\)\s+(?<arrDate>.+)\s*$/mi", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrPoint'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate']));
            }

            // collect company and aircraft
            $aircraftInfo = $this->http->FindSingleNode("./following-sibling::td[normalize-space()][1]", $root);

            if (preg_match("/^.+?\-\s*(?<aircraft>.+?)\s*$/m", $aircraftInfo, $m)) {
                $s->setAircraft($m['aircraft']);
            }
        }

        // collect duration
        if (count($f->getSegments()) == 1) {
            $duration = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Departing Flight'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^{$this->opt($this->t('Flight Duration'))}\:\s+(\d+\s*\w+\s+\d+\s*\w+)\s+.+$/m");

            if (!empty($duration)) {
                $s->setDuration($duration);
            }
        }

        // collect travellers, accountNumbers and costs
        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Rewards applied'))}]/descendant::tr[normalize-space()][1]/td[normalize-space()][1]", null, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/m"));
        $currency = null;
        $cost = null;

        foreach ($travellers as $traveller) {
            $f->addTraveller($traveller, true);

            $accountNumberInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t($traveller))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

            if (preg_match("/^\s*(?<desc>.+?\s+[A-Z]{2})\s+\#(?<number>\d+)\s*$/m", $accountNumberInfo, $m)) {
                $f->addAccountNumber($m['number'], false, $traveller, $m['desc']);
            }

            $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t($traveller))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::td[normalize-space()][last()]");

            if (preg_match("/^\s*(?<currency>\D)\s*(?<cost>[\d\.\,\']+)\s*$/m", $priceInfo, $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $f->price()
                    ->currency($currency);
                $cost += PriceHelper::parse($m['cost'], $currency);
            }
        }

        if (!empty($currency) && $cost !== null) {
            $f->price()
                ->cost($cost);
        }

        // collect total
        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total cost after rewards applied'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*(?<total>[\d\.\,\']+)\s*$/m");

        if (!empty($currency) && $total !== null) {
            $f->price()
                ->total(PriceHelper::parse($total, $this->normalizeCurrency($currency)));
        }

        // collect spent awards (points)
        $spentAwards = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total rewards applied'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*([\d\.\,\']+\s+{$this->opt($this->t('Points'))})\s*$/mi");

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }

        // collect provider phone
        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('If you have questions or need assistance with planning your trip'))}]/ancestor::td[1]");

        if (preg_match("/^.+?(?<desc>{$this->opt($this->t('If you have questions or need assistance with planning your trip'))}).+?(?<phone>\d[\d\-]+\d)\s*\.\s*$/m", $phoneInfo, $m)) {
            $f->addProviderPhone($m['phone'], $m['desc']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFlight($email);
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})(?:\s*\,)?\s+(\d+(?:\s*\:\s*\d+)?\s*\w{2})$#u", // Aug 02, 2024 06:00 PM | Jan 19, 2025 , 11:00 AM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\D+)\s+\d{4}\,\s+\d+(?:\:\d+)?\s*\w{2}$#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }
}
