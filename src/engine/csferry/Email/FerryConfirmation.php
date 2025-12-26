<?php

namespace AwardWallet\Engine\csferry\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FerryConfirmation extends \TAccountChecker
{
    public $mailFiles = "csferry/it-759806008.eml, csferry/it-770735981.eml";
    public $subjects = [
        'Reservation Confirmed',
        'Reservation Changes Confirmed',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'TRAVELLER' => ['ADULT', 'CHILD', 'INFANT'],
            'CHILD'     => ['CHILD', 'INFANT'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@longislandferry.com') !== false) {
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
        return preg_match('/[@.]longislandferry\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Cross Sound Ferry Services'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->contains($this->t('CLICK HERE TO PRINT'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Schedule'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('30 Minute Rule'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseFerry(Email $email)
    {
        $f = $email->add()->ferry();

        // collect booking confirmation
        $confirmationInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your Reservation Number is'))}]/ancestor::td[normalize-space()][1]");

        if (preg_match("/^.+?(?<desc>{$this->opt($this->t('Reservation Number'))}).+?\:\s*(?<number>\d+)\s*$/mi", $confirmationInfo, $m)) {
            $f->general()
                ->confirmation($m['number'], $m['desc']);
        }

        // collect segments info
        $segmentsInfo = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Surcharge'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]", null, "/^\s*[A-Z]{2}\-[A-Z]{2}.+?(?:AM|PM)\s*$/mi"));

        foreach ($segmentsInfo as $segmentInfo) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<depNameCode>[A-Z]{2})\-(?<arrNameCode>[A-Z]{2})\s+[[:alpha:]]+\s+(?<depDate>\d+\/\d+\/\d{4}\s+\d+\:\d+\s*(?:AM|PM))\s*$/mi", $segmentInfo, $m)) {
                $s->departure()->date(strtotime($m['depDate']));
                $s->arrival()->noDate();

                // collect departure and arrival info
                if ($m['depNameCode'] == 'OP' && $m['arrNameCode'] == 'NL') {
                    $s->departure()
                        ->name('Orient Point')
                        ->code('NY');

                    $s->arrival()
                        ->name('New London')
                        ->code('CT');
                }

                if ($m['depNameCode'] == 'NL' && $m['arrNameCode'] == 'OP') {
                    $s->departure()
                        ->name('New London')
                        ->code('CT');

                    $s->arrival()
                        ->name('Orient Point')
                        ->code('NY');
                }

                // collect info from tickets (vehicles info and/or travellers count)
                $ticketsInfo = $this->http->FindNodes("//text()[{$this->eq($this->t($segmentInfo))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]");

                $kidsCount = null;
                $adultsCount = null;

                foreach ($ticketsInfo as $ticketInfo) {
                    // if ticket for vehicle and driver
                    if (preg_match("/^\s*(?<vehicleType>[\w\s]+?)\s+{$this->opt($this->t('and DRIVER'))}\s*\/\s*(?<model>[\w\s]+\-[\w\s]+)(?:\s*\((?<length>.+?)\))?\s*[$].+?$/mi", $ticketInfo, $m)) {
                        $v = $s->addVehicle();
                        $v->setType($m['vehicleType']);
                        $v->setModel($m['model']);

                        if (!empty($m['length'])) {
                            $v->setLength($m['length']);
                        }

                        ++$adultsCount;

                        continue;
                    }

                    // if ticket for usual passenger (adult, child or infant)
                    if (preg_match("/^\s*(?<travellerType>{$this->opt($this->t('TRAVELLER'))})\s*[$].+?$/mi", $ticketInfo, $m)) {
                        if (preg_match("/^{$this->opt($this->t('CHILD'))}$/i", $m['travellerType'])) {
                            ++$kidsCount;
                        } else {
                            ++$adultsCount;
                        }

                        continue;
                    }

                    break;
                }

                if ($adultsCount !== null) {
                    $s->setAdults($adultsCount);
                }

                if ($kidsCount !== null) {
                    $s->setKids($kidsCount);
                }
            }
        }

        // collect total
        $totalInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total amount for your above reservation'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/\s*(?<currencySign>\D)\s*(?<total>[\d\.\,\']+)\s*/", $totalInfo, $m)) {
            $currency = $this->normalizeCurrency($m['currencySign']);

            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        // collect discount
        $discountInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total amount for your above reservation'))}]/preceding::tr[{$this->contains($this->t('Discount'))}][1]");
        $discount = $this->re("/\s*{$this->opt($this->t('Discount'))}\s*\:\s*\-?\s*\D\s*([\d\.\,\']+)\s*/", $discountInfo);

        if (!empty($discount) && !empty($currency)) {
            $f->price()
                ->discount(PriceHelper::parse($discount, $currency));
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact Info'))}]/following::tr[not(descendant::table)][normalize-space()][1]", null, true, "/^{$this->opt($this->t('Name'))}\:\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/mi");

        if (!empty($traveller)) {
            $f->addTraveller($traveller, true);
        }

        // collect provider phones
        $providerPhonesText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Please call the Cross Sound office at'))}]");

        if (preg_match("/^.+?(?<phone1>\+?\s*\d[\d\-\s\(\)]+\d)\s+or\s+(?<phone2>\+?\s*\d[\d\-\s\(\)]+\d)\s+(?<desc>for.+?)\..*$/", $providerPhonesText, $m)) {
            $f->addProviderPhone($m['phone1'], $m['desc']);
            $f->addProviderPhone($m['phone2'], $m['desc']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFerry($email);
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

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
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
