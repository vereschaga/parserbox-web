<?php

namespace AwardWallet\Engine\irail\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "irail/it-33584369.eml";

    public $reFrom = ["irishrail.ie"];
    public $reBody = [
        'en' => ['Ticket Collection', 'This is not a ticket'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure' => 'Departure',
            'Arrival'   => 'Arrival',
        ],
    ];
    private $keywordProv = 'Irish Rail';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($this->http->Response['body'], $this->keywordProv) !== false) {
            if ($this->detectBody()) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//text()[normalize-space()='Departure']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('can\'t find segments');

            return false;
        }
        $dates = array_filter(array_map("strtotime",
            $this->http->FindNodes($xpath . '/preceding::tr[normalize-space()][1]/td[normalize-space()]')));
        $headers = $this->http->FindNodes($xpath . '/preceding::tr[normalize-space()][2]/td[normalize-space()]');

        if (count($dates) == $nodes->length) {
            $this->logger->debug('something wrong with detect dates of trip');

            return false;
        }

        $otaConf = str_replace(' ', '-',
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Collection No:'))}]", null, false,
                "#{$this->opt($this->t('Ticket Collection No:'))} ([\d ]+)$#"));
        $email->ota()
            ->confirmation($otaConf, trim($this->t('Ticket Collection No:'), ":"));
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/ancestor::tr[1][({$this->contains($this->t('Email'))}) and ({$this->contains($this->t('Hours'))})]/td[1]");

        if (preg_match_all("#([\d\+\-\)\( ]{5,15})#", $node, $m)) {
            $phones = array_filter(array_unique(array_map(function ($s) {
                return trim(str_replace(' ', '', $s));
            }, $m[1])));

            foreach ($phones as $phone) {
                $email->ota()->phone($phone);
            }
        }

        $r = $email->add()->train();
        $confNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference Number is'))}]",
            null, false, "#{$this->opt($this->t('Booking Reference Number is'))} (\d{5,})#");
        $r->general()
            ->confirmation($confNo)
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('First and Last Name'))}]/ancestor::tr[{$this->contains($this->t('Price'))}][1]/following-sibling::tr[count(./td)>2]/td[1]"));

        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total amount (with discount)'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

        if (!empty($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
        $discount = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount applied'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

        if (!empty($discount['Total'])) {
            $r->price()
                ->discount($discount['Total']);
        }

        foreach ($nodes as $i => $root) {
            $s = $r->addSegment();
            $s->extra()->noNumber();
            $name = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][1]", $root);
            $s->departure()
                ->name(!empty($name)? $name.', Ireland' : null)
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][last()]/td[normalize-space()!=''][1]",
                    $root), $dates[$i]));

            $name = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][last()]", $root);
            $s->arrival()
                ->name(!empty($name)? $name.', Ireland' : null)
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][last()]/td[normalize-space()!=''][last()]",
                    $root), $dates[$i]));

            if (isset($headers[$i])) {
                $cnt = $this->http->XPath->query("//text()[{$this->eq($this->t('First and Last Name'))}]/ancestor::tr[{$this->contains($this->t('Price'))}][1]/td[contains(normalize-space(),'{$headers[$i]}')]/preceding-sibling::td")->length + 1;

                if ($cnt > 1) {
                    $s->extra()
                        ->seats($this->http->FindNodes("//text()[{$this->eq($this->t('First and Last Name'))}]/ancestor::tr[{$this->contains($this->t('Price'))}][1]/following-sibling::tr[count(./td)>2]/td[{$cnt}]"));
                }
            }
        }

        return true;
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Departure"], $words["Arrival"])) {
                $needles = [(array) $words["Departure"], (array) $words["Arrival"]];

                if ($this->http->XPath->query("//*[{$this->contains($needles[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($needles[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
