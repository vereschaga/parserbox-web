<?php

namespace AwardWallet\Engine\paisly\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCar extends \TAccountChecker
{
	public $mailFiles = "paisly/it-805289231.eml, paisly/it-808799434.eml";
    public $subjects = [
        'All set! Your car rental confirmation.',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [

        ],
    ];

    private $detectRentalProviders = [
        'perfectdrive' => [
            'Budget',
        ],
        'avis' => [
            'Avis',
        ],
        
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'paisly.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Thanks for booking with Paisly.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Order confirmation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Drop-off location'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Reservation details'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]paisly\.com$/', $from) > 0;
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

        $r->obtainTravelAgency();

        $r->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Confirmation number'))}])[1]/following::tr[normalize-space()][1]/descendant::td[1]/descendant::text()[1]", null, false, "/^([\dA-Z\-]+)$/"));

        $reservationDate = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Booking date'))}])[1]/following::tr[normalize-space()][1]/descendant::td[1]", null, false, "/^(\d+\/\d+\/\d{4})$/");

        if ($reservationDate !== null) {
            $r->general()
                ->date(strtotime($reservationDate));
        }

        $r->car()
            ->image($this->http->FindSingleNode("(//text()[normalize-space() = 'Your vehicle'])[1]/preceding::img[1]/@src", null, '/^https?:\/\/\S+$/'))
            ->type($this->http->FindSingleNode("(//text()[normalize-space() = 'Your vehicle'])[1]/following::text()[1]"))
            ->model($this->http->FindSingleNode("(//text()[normalize-space() = 'Your vehicle'])[1]/following::text()[2]"));

        $pickupDate = $this->http->FindSingleNode("//tr[td[1][{$this->eq($this->t('Pick-up time'))}]][td[2][{$this->eq($this->t('Drop-off time'))}]]/following-sibling::tr[normalize-space()][1]/td[1]");

        if (preg_match("/^\w+\s*\,\s*(?<date>[\d\w]+\s*[\d\w]+\,\s*\d{4})\s*at\s*(?<time>[\d\:]+\s*A?P?M?)$/", $pickupDate, $m)){
            $r->pickup()
                ->date(strtotime($m['date'] . ' ' . $m['time']));
        }

        $provider = $this->getRentalProviderByKeyword($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Customer ID'))}])[1]", null, false, "/^(.+){$this->t('Customer ID')}$/"));

        if (!empty($provider)) {
            $r->setProviderCode($provider);
        }

        $r->pickup()
            ->phone($this->http->FindSingleNode("//tr[td[1][{$this->eq($this->t('Pick-up location'))}]][td[2][{$this->eq($this->t('Drop-off location'))}]]/following-sibling::tr[normalize-space()][1]/td[1]/descendant::text()[last()]", null, false, '/^[\-\+\(\)\d\s]+$/'))
            ->location(preg_replace('/\,?\s*undefined\,?/', '', implode(" ", $this->http->FindNodes("//tr[td[1][{$this->eq($this->t('Pick-up location'))}]][td[2][{$this->eq($this->t('Drop-off location'))}]]/following-sibling::tr[normalize-space()][1]/td[1]/descendant::text()[position() < last()]"))));

        $dropoffDate = $this->http->FindSingleNode("//tr[td[1][{$this->eq($this->t('Pick-up time'))}]][td[2][{$this->eq($this->t('Drop-off time'))}]]/following-sibling::tr[normalize-space()][1]/td[2]");

        if (preg_match("/^\w+\s*\,\s*(?<date>[\d\w]+\s*[\d\w]+\,\s*\d{4})\s*at\s*(?<time>[\d\:]+\s*A?P?M?)$/", $dropoffDate, $m)){
            $r->dropoff()
                ->date(strtotime($m['date'] . ' ' . $m['time']));
        }

        $r->dropoff()
            ->phone($this->http->FindSingleNode("//tr[td[1][{$this->eq($this->t('Pick-up location'))}]][td[2][{$this->eq($this->t('Drop-off location'))}]]/following-sibling::tr[normalize-space()][1]/td[2]/descendant::text()[last()]", null, false, '/^[\-\+\(\)\d\s]+$/'))
            ->location(preg_replace('/\,?\s*undefined\,?/', '', implode(" ", $this->http->FindNodes("//tr[td[1][{$this->eq($this->t('Pick-up location'))}]][td[2][{$this->eq($this->t('Drop-off location'))}]]/following-sibling::tr[normalize-space()][1]/td[2]/descendant::text()[position() < last()]"))));

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m) ||
            preg_match("/^(?<price>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $r->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[{$this->contains($this->t('base rate'))}]/descendant::text()[2]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $m['currency']));

            $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[{$this->starts($this->t('Taxes & fees'))}]/descendant::text()[2]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/");

            if ($taxes !== null){
                $r->price()
                    ->fee("Taxes & fees", PriceHelper::parse($taxes, $m['currency']));
            }

            $equipment = $this->http->XPath->query("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[preceding-sibling::tr[{$this->contains($this->t('base rate'))}] and following-sibling::tr[{$this->contains($this->t('Taxes & fees'))}]]");

            foreach ($equipment as $item){
                $equipmentName = $this->http->FindSingleNode("./descendant::text()[1]", $item);
                $equipmentPrice = $this->http->FindSingleNode("./descendant::text()[2]", $item, false, '/^\D{1,3}\s*(\d[\d\.\,\']*)$/');

                if ($equipmentName !== null && $equipmentPrice !== null){
                    $r->addEquipment($equipmentName, PriceHelper::parse($equipmentPrice, $m['currency']));

                    $r->price()
                        ->fee($equipmentName, PriceHelper::parse($equipmentPrice, $m['currency']));
                }
            }

            $points = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[{$this->contains($this->t("TrueBlue"))}]", null, false, "/^\D*(\d+)\s*(?:TrueBlue )?pts\b/");

            if ($points !== null) {
                $r->ota()
                    ->earnedAwards($points . ' TrueBlue points');
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

    private function getRentalProviderByKeyword($keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->detectRentalProviders as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }
        return null;
    }
}
