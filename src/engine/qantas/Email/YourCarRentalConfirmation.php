<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourCarRentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "qantas/it-1797263.eml, qantas/it-38143342.eml";

    public $reFrom = ["qantas.com"];
    public $reBody = [
        'en' => ['car', 'booking'],
    ];
    public $reSubject = [
        '#Your Qantas car rental confirmation with .+? \- reference#',
        '#Your car rental confirmation [A-Z\d]{5,} with#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Pick-up'                    => 'Pick-up',
            'Driver'                     => 'Driver',
            'Confirmation Number:'       => ['Confirmation Number:', 'confirmation number:'],
            'Total Estimated Price'      => ['Total Estimated Price', 'Total estimated price'],
            'Car Type'                   => ['Car Type', 'Vehicle Type+'],
            'Total Qantas Points Amount' => ['Total Qantas Points Amount', 'Total Qantas Points amount'],
        ],
    ];
    private $keywordProv = 'Qantas';
    private $rentalProviders = [
        'jumbo'        => ['Jumbo Car'],
        'rentacar'     => ['Enterprise'],
        'dollar'       => ['Dollar'],
        'hertz'        => ['Hertz'],
        'avis'         => ['Avis'],
        'sixt'         => ['Sixt'],
        'alamo'        => ['Alamo'],
        'perfectdrive' => ['Budget'],
        'payless'      => ['Payless'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Qantas' or contains(@src,'.qantas.com')] | //a[contains(@href,'.qantas.com')]")->length > 0
            && $this->detectBody($this->http->Response['body'])
        ) {
            return $this->assignLang();
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

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
        $r = $email->add()->rental();

        // spentAwards
        $spent = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Qantas Points Amount'))}]/following::text()[normalize-space(.)!=''][1]");

        if (!empty($spent)) {
            $r->price()
                ->spentAwards($spent);
        }

        // TotalCharge
        $sum = $this->getTotalCurrency($this->nextTdContains($this->t('Total Estimated Price')));

        if (!empty($sum['Total']) && !empty($sum['Currency'])) {
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        // Qantas Frequent Flyer
        if (!empty($ff = $this->nextTdContains($this->t('Qantas Frequent Flyer number')))) {
            $r->ota()
                ->account($ff, false);
        }

        // status, company, provider
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for making your'))}]");

        if (empty($text)) {
            $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your car rental booking with'))}]");

            if (preg_match("#Your car rental booking with (.+) is now (confirmed)#i", $text, $m)) {
                $company = $m[1];
                $r->general()->status($m[2]);
            }
        } else {
            if (preg_match("#Thank\s+you\s+for\s+making\s+your\s+(.*)\s+rental\s+car#i", $text, $m)) {
                $company = $m[1];
            }
        }

        if (isset($company)) {
            $r->extra()->company($company);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if (false !== stripos($company, $detect)) {
                        $r->program()->code($code);
                        $flagCode = true;

                        break 2;
                    }
                }
            }

            if (!isset($flagCode)) {
                $r->program()->keyword($company);
            }
        }

        // accountNumber
        if (!empty($an = $this->nextTdContains($this->t('Wizard number')))) {
            $r->program()
                ->account($an, false);
        }

        // general info
        $r->general()
            ->confirmation(trim(str_replace(" ", '', $this->nextTdContains($this->t('Confirmation Number:')))))
            ->traveller($this->nextTdContains($this->t('Driver:')), true);

        // pickup, dropoff
        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Drop-off'] as $key => $value) {
            $subj = $this->nextTdContains($value);
            $regex = "#^" .
                "(\d+\s+\w+\s+\d+|\d+\/\d+\/\d+ 00:00:00)\s+" . //   31/05/2019 00:00:00 6:30AM
                "(\d+:\d+\s*(?:am|pm))" .
                "(?:\s+(.*?))?" .
                "(?:\s+{$this->opt($this->t('Tel:'))}\s+(.*))?" .
                "$#i";

            if (preg_match($regex, $subj, $m)) {
                if (preg_match("#^(\d+)\/(\d+)\/(\d+) 00:00:00$#", $m[1], $v)) {
                    $m[1] = $v[3] . '-' . $v[2] . '-' . $v[1];
                }

                if ($key == 'Pickup') {
                    $point = $r->pickup();
                } else {
                    $point = $r->dropoff();
                }
                $point
                    ->date(strtotime($m[1] . ', ' . $m[2]));

                if (isset($m[3]) && !empty($m[3])) {
                    $point
                        ->location($m[3]);
                }

                if (isset($m[4]) && !empty($m[4])) {
                    $point
                        ->phone($m[4]);
                }
            }
        }

        if (!$r->getDropOffLocation() && $r->getPickUpLocation()) {
            $r->dropoff()->same();
        }

        // car
        $r->car()
            ->model($this->nextTdContains($this->t('Car Type')));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Pick-up'], $words['Driver'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Pick-up'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Driver'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function nextTdContains($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->contains($field)}]/ancestor-or-self::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][1]");
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("AUD $", "AUD", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("₹", "INR", $node);

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
}
