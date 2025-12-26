<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-668351636.eml, leadinghotels/it-668771480.eml";
    public $subjects = [
        'Your Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your reservation is confirmed at' => [
                'Your reservation is confirmed at',
                'Your reservation at',
            ],

            'cancelledPhrases' => ['has been cancelled'],

            'The Leading Hotels of the World' => ['The Leading Hotels of the World', 'THE LEADING HOTELS OF THE WORLD'],

            'To avoid a cancellation penalty, please cancel by' => [
                'To avoid a cancellation penalty, please cancel by',
                'Cancellations are not possible without',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.lhw.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($subject, $headers['subject']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('The Leading Hotels of the World'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Rate Description'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.lhw\.com$/', $from) > 0;
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

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guest Name')]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Conf. Number')]/ancestor::tr[1]/descendant::td[normalize-space()][2]"))
            ->traveller($traveller)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->starts($this->t('To avoid a cancellation penalty, please cancel by'))}]"));

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Adults')]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+)$/"))
            ->kids($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Children')]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+)$/"))
            ->rooms($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Rooms')]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+)$/"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Cost']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*([\d\.\,\']+[A-Z]{3})\s*$/");

        if (preg_match("/^(?<total>[\d\,\.\']+)\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taxes')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\.\,\']+)$/");

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $roomDescription = $this->http->FindSingleNode("//text()[normalize-space()='Room Details']/following::text()[normalize-space()][1]");

        if (!empty($roomDescription)) {
            $h->addRoom()->setDescription($roomDescription);
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation is confirmed at'))}]/following::text()[normalize-space()][not({$this->contains($this->t('cancelledPhrases'))})][3]", null, true, "/^([+]*[\d\s]+)$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call us at'))}]", null, true, "/^{$this->opt($this->t('Call us at'))}\s*([\d\s\(\)\-]+)\./");
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation is confirmed at'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation is confirmed at'))}]/following::text()[normalize-space()][not({$this->contains($this->t('cancelledPhrases'))})][2]"))
            ->phone($phone);

        $inDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in: After')]/following::text()[normalize-space()][1]");
        $inTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in: After')]", null, true, "/^{$this->opt($this->t('Check-in: After'))}\s*([\d\:]+\s*A?P?M)/");

        if (!empty($inTime)) {
            $inDate = $inDate . ', ' . $inTime;
        }

        $outDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out: Before')]/following::text()[normalize-space()][1]");
        $outTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out: Before')]", null, true, "/^{$this->opt($this->t('Check-out: Before'))}\s*([\d\:]+\s*A?P?M)/u");

        if (!empty($outTime)) {
            $outDate = $outDate . ', ' . $outTime;
        }

        $h->booked()
            ->checkIn(strtotime($inDate))
            ->checkOut(strtotime($outDate));

        $account = $this->http->FindSingleNode("//text()[normalize-space()='Leaders Club #']/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");

        if (!empty($account)) {
            $h->addAccountNumber($account, false, $traveller);
        }

        $this->detectDeadLine($h);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $h->general()
                ->cancelled();
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

        if (preg_match("/To avoid a cancellation penalty\, please cancel by\s*(\d+.*\d{4}\s*[\d\:]+\s*A?P?M?)\s*local hotel time\./", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        if (preg_match("/Cancellations are not possible without incurring a charge/", $cancellationText)) {
            $h->booked()->
                nonRefundable();
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
