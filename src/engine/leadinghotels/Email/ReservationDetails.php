<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-15832587.eml, leadinghotels/it-297436154.eml, leadinghotels/it-44705530.eml, leadinghotels/it-561808767.eml";

    private $subjects = [
        'en' => ['Your Reservation Confirmation', 'Updated Reservation'],
    ];

    private $detects = [
        'YOUR RESERVATION IS CONFIRMED AT',
        'YOUR RESERVATION AT',
        'HAS BEEN CANCELLED',
    ];

    private $prov = 'The Leading Hotels of the World';

    private $nodes = [];

    private $mapCodes = [
        'trplace' => [
            'TRAVELPLACE',
        ],
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'LHW') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov) && $this->http->XPath->query("//a[contains(@href,'lhw.com')] | //img[contains(@src,'lhw.com')]") === 0) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$detect}')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]+lhw\.com/i', $from) > 0;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): Email
    {
//        MAIN INFORMATION
        $xpath = "//table[contains(normalize-space(.),'RESERVATION DETAILS')]/following-sibling::table[normalize-space(.)][1]/descendant::tr[count(td)=2][1]";
        $names = array_values($this->arrUniq($this->http->FindNodes("{$xpath}/td[1]/descendant::text()[normalize-space(.)]")));
        $values = array_values($this->arrUniq($this->http->FindNodes("{$xpath}/td[2]/descendant::text()[normalize-space(.)]")));

        foreach ($names as $i => $name) {
            if (isset($values[$i])) {
                $this->nodes[$name] = $values[$i];
            }
        }

        $h = $email->add()->hotel();

        $account = $this->getNode('LEADERS CLUB ID');

        if (!empty($account)) {
            $h->setAccountNumbers([$account], false);
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'YOUR RESERVATION IS CONFIRMED')]/following-sibling::text()[normalize-space(.)][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'YOUR RESERVATION AT')]", null, true, "/{$this->opt('YOUR RESERVATION AT')}\s*(.+)\s+{$this->opt('HAS BEEN CANCELLED')}/");

            if (!empty($hotelName)) {
                $h->general()
                    ->cancelled()
                    ->cancellationNumber($this->getNode('CANCELLATION NUMBER'))
                    ->noConfirmation();
            }
        }

        $h->hotel()
            ->name($hotelName);

        if ($phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'YOUR RESERVATION IS CONFIRMED')]/following::a[contains(@href, 'tel:')][1]", null, true, '/([\d\(\) \-]+)/')) {
            $h->hotel()
                ->phone($phone);
        }

        $address = implode(', ', array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.), 'YOUR RESERVATION IS CONFIRMED')]/following::a[contains(@href, 'tel:')][1]/ancestor::p[1]/span[normalize-space(.)]/text()[not(.//a)]")));

        if (empty($address)) {
            $address = implode(', ', $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'YOUR RESERVATION IS CONFIRMED')]/following::a[contains(@href, 'tel:')][1]/ancestor::div[1]/descendant::*[normalize-space()]"));
            $address = trim(str_replace([$phone, '+', 'CALL', ','], '', $address));
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'YOUR RESERVATION AT')]/following::text()[normalize-space()][1]/ancestor::div[1]");
        }

        if (strlen($address) > 2000) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'YOUR RESERVATION IS CONFIRMED')]/following::div[1]");
        }

        $address = preg_replace('/^[ ]*,[ ]*$/', '', $address);

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } elseif (!empty($h->getHotelName())) {
            $h->hotel()->noAddress();
        }

        if (empty($h->getCancellationNumber())) {
            $h->general()
                ->confirmation($this->getNode('CONF. NUMBER'));
        }

        $h->general()
            ->traveller($this->getNode('GUEST NAME'));

        $additionalGuest = $this->getNode('ADDITIONAL GUEST(S)');

        if ($additionalGuest) {
            $h->addTraveller(preg_match('/^[[:alpha:]][-.\'\"[:alpha:] ]*[[:alpha:]]$/u', $additionalGuest) ? $additionalGuest : null);
        }

        //it-297436154.eml
        $guests = $this->http->FindSingleNode("//text()[normalize-space()='NUMBER OF ADULTS']/following::text()[normalize-space()][1]");

        if (stripos($guests, 'NUMBER OF CHILDREN') !== false) {
            $text = implode("\n", $this->http->FindNodes("//text()[normalize-space()='NUMBER OF ADULTS']/ancestor::tr[1]/following::table[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^(?<adults>\d)\n^(?<kids>\d)\n^(?<room>\d+)\n/m", $text, $m)) {
                $h->booked()
                    ->guests($m['adults'])
                    ->kids($m['kids'])
                    ->rooms($m['room']);
            }
        } else {
            $h->booked()
                ->guests($this->getNode('NUMBER OF ADULTS'));

            $h->booked()
                ->kids($this->getNode('NUMBER OF CHILDREN'));

            $h->booked()
                ->rooms($this->getNode('NUMBER OF ROOMS'));
        }

        $r = $h->addRoom();

        $roomDesc = $this->getTrNode('ACCOMMODATIONS');

        if (empty($roomDesc)) {
            $roomDesc = $this->http->FindSingleNode("//p[normalize-space()='ACCOMMODATIONS']/following-sibling::p[normalize-space()][1]");
        }

        $rateType = $this->getTrNode('RATE DESCRIPTION');

        if (strlen($rateType) < 2000) {
            $r->setRateType(preg_replace("/(https\:.+)/", "", $rateType));
        }

        $r->setDescription($roomDesc);

        $totalRate = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL RATE']/following::text()[normalize-space()][1]");

        if (stripos($totalRate, 'TOTAL COST') !== false) {
            if ($this->http->XPath->query("//text()[normalize-space()='TOTAL RATE']/ancestor::tr[1][contains(normalize-space(), 'TAXES')]")->length == 0) {
                $priceText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='TOTAL RATE']/ancestor::tr[1]/following::table[1]/descendant::table[2]/descendant::text()[normalize-space()]"));

                if (preg_match("/^(?<rate>[\d\.\,]+)\n^(?<cost>[\d\.\,]+)\n^(?<total>[\d\.\,]+)\n^(?<currency>[A-Z]{3})$/m", $priceText, $m)) {
                    $h->price()
                        ->currency($m['currency'])
                        ->total(PriceHelper::parse($m['total'], $m['currency']))
                        ->cost(PriceHelper::parse($m['cost'], $m['currency']));

                    $r->setRate($m['rate']);
                }
            }
        } else {
            $h->price()
                ->total(str_replace([','], [''], $this->getNode('TOTAL COST')));

            $cost = str_replace([','], [''], $this->getNode('TOTAL RATE'));

            if ($cost !== '') {
                $h->price()->cost($cost);
            }

            $tax = str_replace([','], [''], $this->getNode('TAXES'));

            if ($tax !== '') {
                $h->price()->tax($tax);
            }

            if (preg_match('/[A-Z]{3}/', end($values))) {
                $h->price()
                    ->currency(end($values));
            }

            $r->setRate($this->getNode('AVG. DAILY ROOM RATE'));
        }

        $h->general()
            ->cancellation(implode(" ", $this->http->FindNodes("//table[normalize-space(.)='CANCELLATION POLICY']/following-sibling::table[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']")));

        $h->setProviderCode('leadinghotels');

//        OTA
        $h->obtainTravelAgency();

        foreach ($this->mapCodes as $provCode => $keyWords) {
            foreach ($keyWords as $keyWord) {
                if (!empty($this->getTrNode('NAME')) && false !== stripos($keyWord, $this->getTrNode('NAME'))) {
                    $h->ota()->code($provCode);

                    break 2;
                }
            }
        }
//        if (!empty($confOta = $this->getTrNode('AGENCY ID NUMBER'))) {
//            // it's not a confirmation number
//            $ota->confirmation($confOta);
//        }

        $dates = $this->http->FindSingleNode("//td[starts-with(normalize-space(.), 'Check-in') and contains(normalize-space(.), 'Check-out') and not(.//td)]");

        if (preg_match('/Check-in\s+(?<inDate>\d{1,2} \w+ \d{2,4})\s*After\s+(?<inTime>\d{1,2}:\d{2}\s*[AP]M)?\s*Check-out\s+(?<outDate>\d{1,2} \w+ \d{2,4})\s*Before\s*(?<outTime>\d{1,2}:\d{2}\s*[AP]M)?/iu', $dates, $m)) {
            $checkInDate = strtotime($m['inDate']);
            $checkOutDate = strtotime($m['outDate']);

            if (!empty($m['inTime']) && !empty($m['outTime'])) {
                $h->booked()
                    ->checkIn(strtotime($m['inTime'], $checkInDate))
                    ->checkOut(strtotime($m['outTime'], $checkOutDate));
            } else {
                $h->booked()
                    ->checkIn($checkInDate)
                    ->checkOut($checkOutDate);
            }
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (
               preg_match("#To avoid a cancellation penalty, please cancel by (.+?) local hotel time\.#ui", $cancellationText, $m) //en
        ) {
            $h->booked()->deadline(strtotime($m[1]));

            return true;
        }

        if (
               preg_match("#Cancellations are not possible without incurring a charge\.#ui", $cancellationText)
        ) {
            $h->booked()->nonRefundable();

            return true;
        }
        // if no info in Cancellation, check rate description
        // FE: it-44705530
        if (preg_match("#^Cancellations must be received prior to the local hotel date and time stated above#", $cancellationText)
            && count($h->getRooms()) > 0 && !empty($cancellationText = $h->getRooms()[0]->getRateType())) {
            if (preg_match("#^Prepaid Rate Non\-Refundable#ui", $cancellationText)
                || preg_match("#daily rate Non\-refundable full prepayment charged#ui", $cancellationText)
            ) {
                $h->booked()->nonRefundable();

                return true;
            }
        }

        return false;
    }

    private function getTrNode(string $str): ?string
    {
        return $this->http->FindSingleNode("//tr[normalize-space(.)='{$str}']/following-sibling::tr[1]");
    }

    private function getNode(string $key): ?string
    {
        if (isset($this->nodes[$key])) {
            return $this->nodes[$key];
        }

        return null;
    }

    private function arrUniq(array $array): array
    {
        $res = [];

        foreach ($array as $value) {
            if (preg_match('/^[ ]*[\d\.,]+/', $value)) {
                $res[] = $value;
            } elseif (!in_array($value, $res)) {
                $res[] = $value;
            }
        }

        return $res;
    }

    private function opt($field, bool $replaceSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($replaceSpaces) {
            return $replaceSpaces ? preg_replace('/[ ]+/', '\s+', preg_quote($s, '/')) : preg_quote($s, '/');
        }, $field)) . ')';
    }
}
