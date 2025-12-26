<?php

namespace AwardWallet\Engine\paisly\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "paisly/it-802655111.eml, paisly/it-802896197.eml";
    public $subjects = [
        'All set! Your activity confirmation.',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [

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
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Activity details'))}]")->length > 0) {
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
        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->type()->event();

        $e->obtainTravelAgency();

        $e->general()->noConfirmation();

        $e->ota()
            ->account($this->http->FindSingleNode("(//text()[{$this->eq($this->t('TrueBlue number'))}])[1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]", null, false, "/^([\dA-Z\-]+)$/"), false, null, 'TrueBlue number')
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Paisly confirmation number'))}])[1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]", null, false, "/^([\dA-Z\-]+)$/"), 'Paisly confirmation number');

        $reservationDate = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Purchase date'))}])[1]/following::tr[normalize-space()][1]", null, false, "/^(\d+\/\d+\/\d{4})$/");

        if ($reservationDate !== null) {
            $e->general()
                ->date(strtotime($reservationDate));
        }

        $e->place()
            ->name($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Activity details'))}])[1]/following::tr[normalize-space()][1]/descendant::text()[1]"))
            ->address(implode(" ", $this->http->FindNodes("(//text()[{$this->eq($this->t('Where to meet'))}])[1]/following::tr[normalize-space()][1]/descendant::text()[position() < last()]")));

        $startDate = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Activity details'))}])[1]/following::tr[normalize-space()][1]/descendant::text()[2]");

        if (preg_match("/^\w+\s*\,\s*(?<date>[\d\w]+\s*[\d\w]+\,\s*\d{4})\s*at\s*(?<time>[\d\:]+\s*A?P?M?)\s*\,\s*(?<duration>.+)$/" ,$startDate, $m)){
            $e->booked()
                ->start(strtotime($m['date'] . ' ' . $m['time']))
                ->end(strtotime($m['date'] . ' ' . $m['time']) + $this->normalizeDuration($m['duration']));
        }

        $e->booked()
            ->guests($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Activity details'))}])[1]/following::tr[normalize-space()][1]/descendant::text()[3]", null, false,'/^(\d+)/'));

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m) ||
            preg_match("/^(?<price>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $e->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Summary'))}]/following::td[2]/descendant::tr[1]/descendant::td[2]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $m['currency']));

            $taxes = $this->http->XPath->query("//text()[{$this->eq($this->t('Order Summary'))}]/following::td[2]/descendant::tr[not(contains(normalize-space(),'TrueBlue'))][not(contains(normalize-space(),'%'))][position() > 1]");

            foreach ($taxes as $tax){
                $taxName = $this->http->FindSingleNode("./descendant::text()[1]", $tax);
                $taxPrice = $this->http->FindSingleNode("./descendant::text()[2]", $tax, false, '/^\D{1,3}\s*(\d[\d\.\,\']*)$/');

                if ($taxName !== null && $taxPrice !== null){
                    $e->price()
                        ->fee($taxName, PriceHelper::parse($taxPrice, $m['currency']));
                }
            }

            $points = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Summary'))}]/following::td[2]/descendant::tr[{$this->contains($this->t("TrueBlue"))}]", null, false, "/^\D*(\d+)\s*(?:TrueBlue )?pts\b/");

            if ($points !== null) {
                $e->ota()
                    ->earnedAwards($points . ' TrueBlue points');
            }
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy'))}]/following::tr[normalize-space()][1]");

        if ($cancellation !== null){
            $e->general()
                ->cancellation($cancellation);
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

    private function normalizeDuration($str)
    {
        if (preg_match("/^(\d+)\s*minutes?$/", $str, $m)){
            return $m[1] * 60;
        }

        if (preg_match("/^(\d+)\s*hours?$/", $str, $m)){
            return $m[1] * 3600;
        }

        if (preg_match("/^(\d+)\s*hours?[\,\s]*(\d+)\s*minutes?$/", $str, $m)){
            return $m[1] * 3600 + $m[2] * 60;
        }
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
