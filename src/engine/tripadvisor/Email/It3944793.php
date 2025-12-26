<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3944793 extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/it-70458400.eml";

    public $subjects = [
        '/^Booked! Your reservation at\s*\D+is\s*/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thanks for booking on Tripadvisor' => ['Thanks for booking on Tripadvisor', 'Thanks for booking on TripAdvisor'],
            'guests'                            => ['guest', 'adult'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'TripAdvisor LLC. All rights reserved')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thanks for booking on Tripadvisor'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Important information'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripadvisor\.com/i', $from) > 0;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number:']/following::text()[normalize-space()][1]", null, true, "/^[-_A-Z\d]{5,}$/"))
            ->cancellation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation policy')]/following::text()[normalize-space()][1]"));

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation at')]", null, true, "/{$this->opt($this->t('Your reservation at'))}.+is\s*(\w+)/");

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation at')]", null, true, "/{$this->opt($this->t('Your reservation at'))}(.+)is\s*\w+/"))
            ->address($this->http->FindSingleNode("//img[contains(@src, 'media-cdn.tripadvisor.com')]/ancestor::tr[2]/descendant::a[1]/ancestor::table[2]/descendant::text()[normalize-space()][last()]/ancestor::span[1]"));

        $checkIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]", null, true, "/{$this->opt($this->t('Check in:'))}\s*(\d+\/\d+\/\d{4})/");
        $checkOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check out:')]", null, true, "/{$this->opt($this->t('Check out:'))}\s*(\d+\/\d+\/\d{4})/");

        $guestsValue = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Check in:')]/following::img[contains(@src,'/users.')][1]/ancestor::*[self::td or self::th][1]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('guests'))}/", $guestsValue, $m)) {
            // 2 guests    |    2 adults
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/", $guestsValue, $m)) {
            // 2 children
            $h->booked()->kids($m[1]);
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in:')]/following::text()[contains(normalize-space(), 'room')][1]", null, true, "/^(\d+)\s*{$this->opt($this->t('room'))}/"))
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $priceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Check in:')]/following::img[contains(@src,'/special-offer-45deg.')][1]/ancestor::*[self::td or self::th][1]");

        if (preg_match("/^(?<total>[\d\.]+)\s+(?<currency>[A-Z]{3})$/", $priceText, $m)) {
            $h->price()
                ->total($m['total'])
                ->currency($m['currency']);
        }

        $this->detectDeadLine($h, $h->getCancellation());

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellation): void
    {
        if (preg_match('/There is no charge for cancellations made before (?<time>[\d\:]+) \(property local time\) on (?<month>\w+)\s+(?<day>\d+)\,\s*(?<year>\d{4})\./i', $cancellation, $m)) {
            $h->booked()->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        } elseif (preg_match('/This (?i)stay can be cancell?ed with full refund until\s*(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/', $cancellation, $m)) {
            $h->booked()->deadline(strtotime($m[1]));
        } elseif (preg_match('/This (?i)reservation is non-refundable\./', $cancellation)) {
            $h->booked()->nonRefundable();
        }
    }
}
