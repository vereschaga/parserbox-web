<?php

namespace AwardWallet\Engine\travelbank\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "travelbank/it-764282952.eml";

    public $subjects = [
        'Rental Car',
        'is Reserved',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Total' => ['Estimated Total Due to Car Rental Agency'],
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
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Reservation Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Rental Car Agency:'))}]")->length > 0) {
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
        $this->RentalConfirmation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function RentalConfirmation(Email $email)
    {
        $r = $email->add()->rental();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'TravelBank Itinerary ID:')]", null, true, "/^\D+\:\s*([A-Z\d]+)$/"), 'TravelBank Itinerary ID');

        $r->setCompany($this->http->FindSingleNode("//text()[normalize-space()='Rental Car Agency:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(.*)$/"));

        $r->general()
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Date:')]", null, false, "/^Reservation Date\:\s*(\w+\s*\d+\,\s*\d{4})$/")));

        $reservationNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation Number:')]", null, true, "/\:\s*([A-Z\d]+)$/");

        if ($reservationNumber == null) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()
                ->confirmation($reservationNumber);
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/ancestor::tr[1]", null, true, "/\:\s*(\D{1,3}\s*[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Est. Rental')]/ancestor::tr[1]", null, true, "/\s\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $r->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Est. Taxes & Fees:')]/ancestor::tr[1]", null, true, "/\s*\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($tax !== null) {
                $r->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Traveler:']/ancestor::tr[1]/descendant::td[2]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");
        $r->addTraveller(($traveller), true);

        $carType = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Car Type:')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(.*)$/");

        if (!empty($carType)) {
            $r->car()
                ->type($carType);
        }

        $pickupInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Pick Up:']/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("/^(?<pickupTime>\d+\:\d+\s*(?:[A-a]|[P-p])[M-m])\s*\w+\,\s*(?<pickupDate>\w+\s*\d+\,\s*\d{4})\s*(?<pickupAddress>.*)$/", $pickupInfo, $m)) {
            $r->pickup()
                ->date(strtotime($m['pickupDate'] . $m['pickupTime']))
                ->location($m['pickupAddress']);
        }

        $dropInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Drop-off:']/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("/^(?<dropTime>\d+\:\d+\s*(?:[A-a]|[P-p])[M-m])\s*\w+\,\s*(?<dropDate>\w+\s*\d+\,\s*\d{4})\s*(?<dropAddress>.*)$/", $dropInfo, $m)) {
            $r->dropoff()
                ->date(strtotime($m['dropDate'] . $m['dropTime']))
                ->location($m['dropAddress']);
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
}
