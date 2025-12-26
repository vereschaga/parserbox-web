<?php

namespace AwardWallet\Engine\travelbank\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "travelbank/it-738705948.eml, travelbank/it-739779851.eml, travelbank/it-753559881.eml, travelbank/it-762390663.eml";

    public $subjects = [
        'Flight for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Direct' => ['Direct', 'Connecting'],
        ],
    ];

    public function detectEmailByHeaders(array $headers): bool
    {
        if (isset($headers['from']) && stripos($headers['from'], '@travelbank.com') !== false) {
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
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'TravelBank Itinerary ID:')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Flight Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Know Before You Go'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        return preg_match('/[@.]travelbank\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'TravelBank Itinerary ID:')]", null, true, "/^\D+\:\s*([A-Z\d]{6})$/"), 'TravelBank Itinerary ID');

        $f->general()
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Date:')]", null, false, "/^Booking Date\:\s*(\w+\s*\d+\,\s*\d{4})$/")));

        $confirmationNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation Number:')]", null, true, "/^\D+\:\s*([A-Z\d]{6})$/");
        if ($confirmationNumber == null) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confirmationNumber);
        }

        $priceInfo = $this->http->FindSingleNode("//text()[normalize-space()='Total Paid:']/ancestor::tr[1]", null, true, "/Total\s*Paid\:\s*(\D{1,3}\s*[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Base Fare:']/ancestor::tr[1]", null, true, "/Base\s*Fare\:\s*\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & Fees:']/ancestor::tr[1]", null, true, "/Taxes\s*\&\s*Fees\:\s*\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($tax !== null) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Traveler(s)']/ancestor::table[1]/following-sibling::table[1]/descendant::tr[not(contains(normalize-space(), 'ending in'))]/descendant::td[1]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");
        $f->setTravellers(array_unique($travellers), true);

        $tickets = $this->http->FindNodes("//text()[normalize-space()='Traveler(s)']/ancestor::table[1]/following-sibling::table[1]/descendant::tr[not(contains(normalize-space(), 'ending in'))]/descendant::td[2]", null, "/^\s*\d+$/");

        foreach ($tickets as $ticket) {
            if (!empty($ticket)){
                $traveller = $this->http->FindNodes("//text()[normalize-space()='Traveler(s)']/ancestor::table[1]/following-sibling::table[1]/descendant::tr[{$this->contains(($this->t($ticket)))}]/descendant::td[1]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/")[0];
                $f->addTicketNumber($ticket, false, $traveller);
            }
        }

        $segmentNodes = $this->http->XPath->query("//text()[normalize-space()='Airline:']/ancestor::table[1]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Airline:']/ancestor::td[1]/following-sibling::td[1]", $root, true, '/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})$/');

            if (empty($airInfo)) {
                $airInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Flight:']/ancestor::td[1]/following-sibling::td[1]", $root, true, '/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})$/');
            }

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $cabinInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Cabin Class:']/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^\w+$/');

            if (!empty($cabinInfo)) {
                $s->extra()
                    ->cabin($cabinInfo);
            }

            $milesInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Miles:']/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^\d*\.*\,*\d*\D*\s*\/\s*\d*\.*\,*\d*\D*$/');

            if (!empty($milesInfo)) {
                $s->extra()
                    ->miles($milesInfo);
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[normalize-space()='Duration:']/ancestor::td[1]/following-sibling::td[1]", $root, true, "/^((?:\d+\s*[hH]*\w+\,?\s*)?(?:\d+\s*[mM]*\w+)?)$/"));

            $depInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Departure:']/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("/^(?<depTime>\d+\:\d+\s*(?:A|P)M)\s*\w+\,\s*(?<depDate>\w+\s*\d+\,\s*\d{4})\s*\((?<depCode>[A-Z]{3})\)$/", $depInfo, $m)) {
                $s->departure()
                    ->date(strtotime($m['depDate'] . $m['depTime']))
                    ->code($m['depCode']);
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrival:']/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("/^(?<arrTime>\d+\:\d+\s*(?:A|P)M)\s*\w+\,\s*(?<arrDate>\w+\s*\d+\,\s*\d{4})\s*\((?<arrCode>[A-Z]{3})\)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->date(strtotime($m['arrDate'] . $m['arrTime']))
                    ->code($m['arrCode']);
            }

            $seatInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Seats:']/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^(\d+\-?[A-Z])$/');

            if (!empty($seatInfo)) {
                $s->extra()
                    ->seat(str_replace('-', '', $seatInfo));
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
