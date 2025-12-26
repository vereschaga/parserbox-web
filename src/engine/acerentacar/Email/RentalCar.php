<?php

namespace AwardWallet\Engine\acerentacar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCar extends \TAccountChecker
{
	public $mailFiles = "acerentacar/it-842643796.eml, acerentacar/it-853799544.eml";
    public $subjects = [
        'ACE Rent A Car - Booking Confirmation:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'discountName' => ['Percentage Discount']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'acerentacar.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains(($this->t('Thanks for booking with ACE Rent A Car.')))}]")->length === 0
            && $this->http->XPath->query("//a[contains(@href,'.acerentacar.com')]")->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Drop-off'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your vehicle has been reserved'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]acerentacar\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->RentalCar($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function RentalCar(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, false, "/^([A-Z]{3}\d{8})$/"));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/preceding::text()[normalize-space()][2]"))
            ->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/preceding::text()[normalize-space()][1]"));

        $carPicture = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/preceding::img[1]/@src", null, false, '/^https?:\/\/\S+$/');

        if ($carPicture !== null) {
            $r->car()
                ->image($carPicture);
        }

        $pickupInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space()][1]");

        //it-842643796.eml
        if (preg_match("/^(?<location>.+?\D)[ ]*(?<date>\d{1,2}\/\d{2}\/\d{4}|(?:[A-Z][a-z]+,[ ]*)?\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{4})[ ]*at[ ]*(?<time>\d{1,2}\:\d{2}[ ]*A?P?M?)$/", $pickupInfo, $p)){
            $r->pickup()
                ->location($pickupInfo = $p['location'])
                ->date(strtotime($p['date'] . ' ' . $p['time']));
        } else {
            $pickupDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space()][2]");

            if (preg_match("/^(?:[A-Z][a-z]+,[ ]*)?[ ]*(?<date>\d{1,2}\/\d{2}\/\d{4}|\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{4})[ ]*at[ ]*(?<time>\d{1,2}\:\d{2}[ ]*A?P?M?)$/", $pickupDate, $m)){
                $r->pickup()
                    ->date(strtotime($m['date'] . ' ' . $m['time']));
            }

            $r->pickup()
                ->location($pickupInfo);
        }
        //it-842643796.eml
        $dropoffInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop-off'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<location>.+?\D)[ ]*(?<date>\d{1,2}\/\d{2}\/\d{4}|(?:[A-Z][a-z]+,[ ]*)?\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{4})[ ]*at[ ]*(?<time>\d{1,2}\:\d{2}[ ]*A?P?M?)$/", $dropoffInfo, $d)){
            $r->dropoff()
                ->location($dropoffInfo = $d['location'])
                ->date(strtotime($d['date'] . ' ' . $d['time']));
        } else {
            $dropoffDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop-off'))}]/following::text()[normalize-space()][2]");

            if (preg_match("/^(?:[A-Z][a-z]+,[ ]*)?[ ]*(?<date>\d{1,2}\/\d{2}\/\d{4}|\d{1,2}[ ]*[[:alpha:]]+[ ]*\d{4})[ ]*at[ ]*(?<time>\d{1,2}\:\d{2}[ ]*A?P?M?)$/", $dropoffDate, $m)){
                $r->dropoff()
                    ->date(strtotime($m['date'] . ' ' . $m['time']));
            }

            $r->dropoff()
                ->location($dropoffInfo);
        }

        $hours = $this->http->FindNodes("//text()[{$this->eq($this->t('Location Hours'))}]/following::text()[normalize-space()][position() < 8][following::text()[{$this->eq($this->t('Total'))}]]");

        if (!empty($hours)){
            if ($pickupInfo == $dropoffInfo){
                $r->pickup()
                    ->openingHoursFullList($hours);

                $r->dropoff()
                    ->openingHoursFullList($hours);
            }
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m) ||
            preg_match("/^(?<price>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $r->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('Base Rate'))}]/following::td[1]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $m['currency']));

            $taxes = $this->http->XPath->query("//text()[{$this->eq($this->t('Base Rate'))}]/ancestor::tr[1]/following-sibling::tr");
            $discountArray = [];
            foreach ($taxes as $tax){
                $feeName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]",  $tax);
                $feePrice = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $tax, false, "/^\D{1,3}(\d[\d\.\,\']*)$/");

                if ($feeName !== null && $feePrice !== null){
                    if (preg_match("/^{$this->opt($this->t('discountName'))}$/", $feeName)){
                        $discountArray[] = PriceHelper::parse($feePrice, $m['currency']);
                    } else {
                        $r->price()
                            ->fee($feeName, PriceHelper::parse($feePrice, $m['currency']));
                    }
                }
            }

            if (!empty($discountArray)){
                $r->price()
                    ->discount(array_sum($discountArray));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}