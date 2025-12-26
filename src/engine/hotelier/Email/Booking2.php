<?php

namespace AwardWallet\Engine\hotelier\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking2 extends \TAccountChecker
{
    public $mailFiles = "hotelier/it-630444815.eml";
    public $subjects = [
        'Online Booking For',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Room: ' => ['Room: ', 'Room ', 'Bed: '],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@apac.littlehotelier.com') !== false) {
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
        if ($this->http->XPath->query("//img[contains(@src, 'littlehotelier.com')]")->length > 0
            || $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Booking Reference Number:')]/following::text()[normalize-space()][1][starts-with(normalize-space(), 'LH')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Address and contact'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Accommodation'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]apac\.littlehotelier\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference Number:')]/following::text()[normalize-space()][1]"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Guest Details']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), '@') or contains(normalize-space(), 'Guest Details'))]"))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy']/following::p[normalize-space()][1]"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Booked on']/following::text()[normalize-space()][1]")));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you, your reservation at ')]", null, true, "/{$this->opt($this->t('Thank you, your reservation at'))}\s*(.+)\s+{$this->opt($this->t('has been'))}/"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Address and contact']/following::text()[normalize-space()][string-length()>2][1]"))
            ->phone($this->http->FindSingleNode("//text()[normalize-space()='Address and contact']/following::text()[normalize-space()][string-length()>2][2]"));

        $inDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/ancestor::tr[1]/following::tr[1]");
        $inDate = preg_replace("/\s*from\s*/iu", ", ", $inDate);
        $outDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/ancestor::tr[1]/following::tr[1]");

        $inTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in is available from')]", null, true, "/{$this->opt($this->t('Check-in is available from'))}\s*(\d+\:\d+)\s+/");
        $outTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out is from')]", null, true, "/{$this->opt($this->t('Check-out is from'))}\s*(\d+\:\d+)\s+/");

        if (!empty($inTime) && !empty($outTime)) {
            $h->booked()
                ->checkIn(strtotime($inDate . ', ' . $inTime))
                ->checkOut(strtotime($outDate . ', ' . $outTime));
        } else {
            $h->booked()
                ->checkIn(strtotime($inDate))
                ->checkOut(strtotime($outDate));
        }

        $guests = $this->http->FindNodes("//text()[{$this->starts($this->t('Room: '))}]/following::text()[normalize-space()][1][contains(normalize-space(), 'Adult')]", null, "/(\d+)\s*{$this->opt($this->t('Adult'))}/");
        $h->booked()
            ->guests(array_sum(array_filter($guests)));

        $kids = $this->http->FindNodes("//text()[{$this->starts($this->t('Room: '))}]/following::text()[normalize-space()][1][contains(normalize-space(), 'Children')]", null, "/(\d+)\s*{$this->opt($this->t('Children'))}/");
        $infants = $this->http->FindNodes("//text()[{$this->starts($this->t('Room: '))}]/following::text()[normalize-space()][1][contains(normalize-space(), 'Infants')]", null, "/(\d+)\s*{$this->opt($this->t('Infants'))}/");

        if (!empty($infants)) {
            $kids = array_merge($infants, $kids);
        }
        $h->booked()
            ->kids(array_sum(array_filter($kids)));

        $roomNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Room: '))}][not(contains(normalize-space(), 'Tax'))]");

        if (!empty($roomNodes->length)) {
            $h->booked()
                ->rooms($roomNodes->length);

            foreach ($roomNodes as $roomRoot) {
                $room = $h->addRoom();

                $type = $this->http->FindSingleNode(".", $roomRoot, true, "/\:\s*(.+)/");

                if (empty($type)) {
                    $type = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $roomRoot, true, "/\:\s*(.+)/");
                }
                $room->setType($type);

                $rate = implode(",", $this->http->FindNodes("./following::text()[starts-with(normalize-space(), 'Date')][1]/ancestor::tr[1]/following-sibling::tr", $roomRoot));
                $room->setRate($rate);
            }
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Booking Summary']/following::text()[starts-with(normalize-space(), 'Total:')][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D*([\d\.\,]+)$/");

        if ($price !== null) {
            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Prices are in')]", null, true, "/{$this->opt($this->t('Prices are in'))}\s*([A-Z]{3})/");
            $h->price()
                ->total(PriceHelper::parse($price, $currency))
                ->currency($currency);
            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Booking Summary']/following::text()[normalize-space()='Room:']/ancestor::tr[1]/td[2]", null, true, "/\D*([\d\.\,]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $discount = $this->http->FindSingleNode("//text()[normalize-space()='Booking Summary']/following::text()[normalize-space()='Discount Total:']/ancestor::tr[1]/td[2]", null, true, "/\D*([\d\.\,]+)/");

            if (!empty($discount)) {
                $h->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }

            $feeNodes = $this->http->XPath->query("//text()[normalize-space()='Booking Summary']/following::text()[normalize-space()='Room:']/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Discount') or contains(normalize-space(), 'Total:'))]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./descendant::td[2]", $feeRoot, true, "/\D*([\d\.\,]+)/");

                if (!empty($feeName) && !empty($feeSumm)) {
                    $h->price()
                        ->fee($feeName, PriceHelper::parse($feeSumm, $currency));
                }
            }
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellation policy \((\d+\s*days?) prior to arrival\) to received full deposit refund/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }
}
