<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3763180 extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/it-3763180.eml, tripadvisor/it-3763180.eml, tripadvisor/it-3944793.eml, tripadvisor/it-6932696.eml";

    public $subjects = [
        '/Booked! Your reservation at .{3,} is confirmed/i',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Total:' => ['Total:', 'Total Price:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.tripadvisor.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'TripAdvisor LLC. All rights reserved')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your trip and payment details'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.tripadvisor\.com$/', $from) > 0;
    }

    public function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi ')][not(contains(normalize-space(), 'weiweiweixy'))]", null, true, "/{$this->opt($this->t('Hi'))}\s+(\D+)$/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thanks for booking on TripAdvisor,')]", null, true, "/{$this->opt($this->t('Thanks for booking on TripAdvisor,'))}\s*(\D+)\.$/");
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation is')]/following::text()[normalize-space()='Confirmation Number:'][1]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{7,})/"))
            ->cancellation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Policy')]/following::text()[normalize-space()][1]"))
            ->traveller($traveller, true);

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation is')]", null, true, "/{$this->opt($this->t('Your reservation is'))}\s*(\D+)$/");

        if (!empty($status)) {
            $h->general()
                ->status(trim($status, '.'));
        }

        $hoteInfo = $this->http->FindSingleNode("//text()[normalize-space()='Your trip and payment details'][1]/following::text()[contains(normalize-space(), 'Reviews')][1]/following::text()[normalize-space()][1]/ancestor::table[1]");
        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Check in:']/preceding::img[contains(@src, 'phone.jpg')][1]/following::text()[normalize-space()][1]");

        if (!empty($phone)) {
            if (preg_match("/^(?<name>\D+)\s[\d\,]+\sReviews\s(?<address>.+){$phone}\s*[\d\-\s]+/u", $hoteInfo, $m)) {
                $h->hotel()
                    ->name($m['name'])
                    ->address($m['address'])
                    ->phone($phone);
            }
        }

        if (empty($phone)) {
            if (preg_match("/^(?<name>\D+)\s[\d\,]+\sReviews\s(?<address>.+)/u", $hoteInfo, $m)) {
                $h->hotel()
                    ->name($m['name'])
                    ->address($m['address']);
            }
        }

        $checkIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check in:'))}\s*(\d+\/\d+\/\d{4})/");
        $checkOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check out:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check out:'))}\s*(\d+\/\d+\/\d{4})/");

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]/following::text()[contains(normalize-space(), 'room')][1]", null, true, "/^(\d+)\s*{$this->opt($this->t('room'))}/"))
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]/following::text()[contains(normalize-space(), 'guests')][1]", null, true, "/^(\d+)\s*{$this->opt($this->t('guests'))}/"))
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<total>[\d\.]+)\s+(?<currency>[A-Z]{3})$/", $priceText, $m)) {
            $h->price()
                ->total($m['total'])
                ->currency($m['currency']);
        }

        $this->detectDeadLine($h, $h->getCancellation());
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+)\/(\d+)\/(\d{4})#u',
        ];

        $out = [
            '$2.$1.$3',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellation)
    {
        if (preg_match('/Cancellations before (?<date>\d+\/\d+\/\d{4}\,\s[\d\:]+\s*A?P?M)/i', $cancellation, $m)) {
            $h->booked()->deadline(strtotime($m['date']));
        }

        if (preg_match('/Can be cancelled by (\d+) day\(s\) before arrival/i', $cancellation, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' days');
        }

        if (preg_match('/Reservations must be canceled (\d+) hours prior to arrival date/i', $cancellation, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' hours');
        }

        if (preg_match('/Non-Refundable\./i', $cancellation, $m)) {
            $h->booked()->nonRefundable();
        }
    }
}
