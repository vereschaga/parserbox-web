<?php

namespace AwardWallet\Engine\dohop\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "dohop/it-745468680.eml, dohop/it-745551861.eml";
    public $subjects = [
        'with Dohop',
    ];

    public $lang = 'en';
    public $prevDate = 0;

    public static $dictionary = [
        'en' => [
            'Your bookings'    => ['Your bookings', 'Your tickets'],
            'Connection'       => ['Connection', 'Connect'],
            'Reservation code' => ['Reservation code', 'Booking reference'],
        ],
    ];

    public function detectEmailByHeaders(array $headers): bool
    {
        if (isset($headers['from']) && stripos($headers['from'], '@dohop.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser): bool
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Dohop'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Trip details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Your bookings'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Summary'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        return preg_match('/[@.]dohop\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        //$this->assignLang();
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        // collect reservation confirmations
        $email->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Dohop Reservation Code'))}]//ancestor::td[normalize-space()][1]//following::tr[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{8})\s*$/"), 'Dohop Reservation Code');
        $f->setNoConfirmationNumber(true);

        // collect travellers
        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Summary'))}]/ancestor::table[normalize-space()][1]/ancestor::tr[normalize-space()][1]/following-sibling::tr[normalize-space()]/descendant::tr[normalize-space()][1]", null, "/^\s*\d\.\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/"));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::table[normalize-space()][1]/descendant::tr[normalize-space()]", null, "/^\s*\d\.\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/"));
        }

        if (!empty($travellers)) {
            $f->setTravellers($travellers, true);
        }

        // collect segments
        $depDay = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Depart'))}]", null, true, "/^\s*{$this->opt($this->t('Depart'))}\s*\-\s*(\d+\s*[a-z]+\s*\d{4})\s*$/i");
        $depSegment = $this->http->XPath->query("//text()[{$this->contains($this->t('Depart'))}]/following::table[1]/descendant::table[1]")[0];
        $arrSegment = $this->http->XPath->query("//text()[{$this->contains($this->t('Depart'))}]/following::table[1]/descendant::table[last()]")[0];

        foreach ([$depSegment, $arrSegment] as $segment) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/td[normalize-space()][2]", $segment);

            if (preg_match("/^.+?\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $pointInfo = $this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/td[normalize-space()][1]", $segment);

            if (preg_match("/^(?<depCode>[A-Z]{3})\s*(?<depName>\D+?)\s*\-\s*(?<arrCode>[A-Z]{3})\s*(?<arrName>\D+?)\s*$/", $pointInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
            }

            $timeInfo = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/td[normalize-space()][1]", $segment);

            if (preg_match("/^\s*(?<depTime>\d+\:\d+)\s*\-\s*(?<arrTime>\d+\:\d+)\s*$/", $timeInfo, $m)) {
                $depDate = strtotime($depDay . ', ' . $m['depTime']);

                if ($depDate < $this->prevDate) {
                    $depDate = strtotime('+1 day', $depDate);
                }
                $this->prevDate = $depDate;
                $s->departure()
                    ->date($depDate);

                $arrDate = strtotime($depDay . ', ' . $m['arrTime']);

                if ($arrDate < $this->prevDate) {
                    $arrDate = strtotime('+1 day', $arrDate);
                }
                $this->prevDate = $arrDate;
                $s->arrival()
                    ->date($arrDate);
            }

            $duration = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/td[normalize-space()][3]", $segment, true, "/^\s*((:?\d+h)?\s*(?:\d+m)?)\s*$/i");
            $s->setDuration($duration);

            $itinerary = $s->getDepName() . ' — ' . $s->getArrName();
            $reservationCode = $this->http->FindSingleNode("//text()[{$this->eq([$itinerary])}]/ancestor::tr[normalize-space()][1]/following-sibling::tr[{$this->contains($this->t('Reservation code'))}]", null, true, "/^\s*{$this->opt($this->t('Reservation code'))}\s*([A-Z\d]{6,8})\s*$/");

            if (!empty($reservationCode)) {
                $s->setConfirmation($reservationCode);
            }
        }

        // collect fees
        $feesInfo = $this->http->XPath->query("//text()[{$this->contains($this->t('Summary'))}]/ancestor::table[1]/ancestor::tr[1]/following-sibling::tr[last()]/descendant::tr[normalize-space()][not(descendant::tr)][position()>1]");

        foreach ($feesInfo as $feeInfo) {
            $feeName = $this->http->FindSingleNode("./td[normalize-space()][1]", $feeInfo);
            $feePrice = $this->http->FindSingleNode("./td[normalize-space()][2]", $feeInfo);

            if (preg_match("/^\s*(?<currency>\D+)\s*(?<price>[\d\.\,\']+)\s*$/", $feePrice, $m)) {
                $f->price()
                    ->fee($feeName, PriceHelper::parse($m['price'], $m['currency']));
            }
        }

        // collect total
        $totalInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/following::td[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>\D+?)\s*(?<price>[\d\.\,\']+)\s*$/", $totalInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
