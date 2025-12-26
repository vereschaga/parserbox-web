<?php

namespace AwardWallet\Engine\travelbank\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdatedConfirmation extends \TAccountChecker
{
    public $mailFiles = "travelbank/it-741012550.eml, travelbank/it-751837663.eml, travelbank/it-753831564.eml, travelbank/it-762322252.eml";

    public $subjects = [
        'Reservation for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Total' => ['Total:'],
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
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Hotel Info'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Details'))}]")->length > 0) {
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
        $this->UpdatedConfirmation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function UpdatedConfirmation(Email $email)
    {
        $h = $email->add()->hotel();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'TravelBank Itinerary ID:')]//following-sibling::span[1]", null, true, "/^([A-Z\d]+)$/"), 'TravelBank Itinerary ID');

        $hotelConfirmation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hotel Confirmation Number:')]//following-sibling::span[1]", null, true, "/^([A-Z\d]+)$/");

        if ($hotelConfirmation == null) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($hotelConfirmation);
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]", null, true, "/^\D+\:\s*(\D{1,3}\s*[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Room Charge')]/ancestor::tr[1]", null, true, "/\s\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Tax Recovery Charges & Service Fees']/ancestor::tr[1]", null, true, "/\s*\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $feesNodes = $this->http->XPath->query("//text()[normalize-space()='Total Paid Today:']/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Total'))]");

            if ($feesNodes !== null) {
                foreach ($feesNodes as $root) {
                    $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                    $feeSum = $this->http->FindSingleNode("./descendant::td[3]", $root, true, '/^\D{1,3}([\d\.\,\']+)$/');

                    if ($feeName !== null && $feeSum !== null) {
                        $h->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                    }
                }
            }
        }

        $h->addRoom()
            ->setRate(str_replace(' avg', '', $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Room Charge')]", null, false, "/\D*\s*(\D{1,3}[\d\.\,\`]+(\s*\w*)\/\w*)\)/")))
            ->setType($this->http->FindSingleNode("//text()[normalize-space()='Room Type:']/ancestor::tr[1]/descendant::td[2]", null, false, "/^.+$/"));

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Guest:']/ancestor::tr[1]/descendant::td[2]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");
        $h->setTravellers(array_unique($travellers), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Hotel Info']/ancestor::tr[1]/following-sibling::tr[1]", null, false))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Hotel Info']/ancestor::tr[1]/following-sibling::tr[2]", null, false));

        $checkinInfo = $this->http->FindSingleNode("//text()[normalize-space()='Check-in:']/ancestor::tr[1]/descendant::td[2]");
        $this->logger->debug($checkinInfo);
        if (preg_match("/(?<checkinTime>\d+\:\d+\s*(?:[A-a]|[P-p])[M-m])?\s*\w+\,\s*(?<checkinDate>\w+\s*\d+\,\s*\d{4})$/", $checkinInfo, $m)) {
            if (!empty($m['checkinTime'])){
                $h->booked()
                    ->checkIn(strtotime($m['checkinTime'] . ' ' . $m['checkinDate']));
            } else {
                $h->booked()
                    ->checkIn(strtotime($m['checkinDate']));
            }
        }

        $checkoutInfo = $this->http->FindSingleNode("//text()[normalize-space()='Check-out:']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/(?<checkoutTime>\d+\:\d+\s*(?:[A-a]|[P-p])[M-m])?\s*\w+\,\s*(?<checkoutDate>\w+\s*\d+\,\s*\d{4})$/", $checkoutInfo, $m)) {
            if (!empty($m['checkoutTime'])){
                $h->booked()
                    ->checkOut(strtotime($m['checkoutTime'] . ' ' . $m['checkoutDate']));
            } else {
                $h->booked()
                    ->checkOut(strtotime($m['checkoutDate']));
            }
        }

        $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Details')]/ancestor::tr[1]/following-sibling::tr[3]");

        if ($cancellation !== null) {
            $h->general()
                ->cancellation($cancellation);
        } else {
            $h->general()
                ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancelation Policy:']/ancestor::tr[1]/descendant::td[2]"));
        }
        $this->detectDeadLine($h);
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^Free\s*cancelation\s*until\s*(\d+\s*\w+\s*\d{4}\s*[\d\:]+\s*A?P?M?)\s\(/", $cancellation, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        if (preg_match("/(\d+\s*days?)\s*before\s*arrival$/", $cancellation, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }

        if (preg_match("/^This rate is non\-refundable\./", $cancellation) || preg_match("/^Non\-refundable/", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
    }
}
