<?php

namespace AwardWallet\Engine\xanterra\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "xanterra/it-77744182.eml, xanterra/it-81879307.eml";
    public $subjects = [
        '/Your Yellowstone Reservation Confirmation\. Do Not Reply\.$/',
        '/Yellowstone Reservation Information$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'non-refundable' => ['non-refundable', 'was canceled'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@xanterra.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Operated by Xanterra')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Information'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('FACILITY'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]xanterra\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (count($this->http->FindNodes("//tr[starts-with(normalize-space(), 'ARRIVE')]/descendant::td")) !== 13) {
            $this->logger->error('BAD FORMAT!');
        }

        $xpathPart = "//tr[starts-with(normalize-space(), 'ARRIVE')]/following-sibling::tr[contains(normalize-space(), ':00')][not(contains(normalize-space(), 'unit') or contains(normalize-space(), 'COMMENTS')or contains(normalize-space(), 'CHECK'))]";
        $nodes = $this->http->XPath->query($xpathPart);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'CANCELLATION OF LODGING')]");

            if (empty($cancellation)) {
                $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t('non-refundable'))}]");

                if (preg_match("/(?:\.com)\.\s*(.+{$this->opt($this->t('non-refundable'))}.+)/u", $info, $m)
                    || preg_match("/(?:\.com)?\.\s*(.+{$this->opt($this->t('non-refundable'))}.+)/u", $info, $m)
                ) {
                    $cancellation = $m[1];
                }
            }
            $h->general()
                ->traveller($this->http->FindSingleNode("//text()[normalize-space()='IF SENDING US A DEPOSIT CHECK,PLEASE MAKE PAYABLE TO AND MAIL TO:']/following::text()[normalize-space()][1]"), true)
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation #')]", null, true, "/{$this->opt($this->t('Reservation #'))}\s*([A-Z\d]+)\s/"), 'Reservation #')
                ->cancellation($cancellation);

            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Hotel Information')]/following::text()[normalize-space()][1]/ancestor::small[1]/descendant::text()"));

            if (preg_match("/^Hotel Information\n+.+\nOperated by Xanterra Parks & Resorts\S\n(?<address>.+)\nReservations:\s*(?<phone>.+)/u", $hotelInfo, $m)) {
                $h->hotel()
                    ->address($m['address'])
                    ->phone($m['phone']);
            }

            $h->setHotelName($this->http->FindSingleNode("./descendant::td[6]", $root));

            $dateIn = $this->http->FindSingleNode("./descendant::td[1]", $root);
            $timeIn = $this->http->FindSingleNode("./descendant::td[5]", $root, true, "/^(\d+\:\d+)\:\d+\s*\//u");

            $dateOut = $this->http->FindSingleNode("./descendant::td[2]", $root);
            $timeOut = $this->http->FindSingleNode("./descendant::td[5]", $root, true, "/\/\s*(\d+\:\d+)\:\d+/u");

            $h->booked()
                ->checkIn($this->normalizeDate($dateIn . ', ' . $timeIn))
                ->checkOut($this->normalizeDate($dateOut . ', ' . $timeOut));

            $h->booked()
                ->guests($this->http->FindSingleNode("./descendant::td[9]", $root, true, "/^(\d+)\s*\//"))
                ->kids($this->http->FindSingleNode("./descendant::td[9]", $root, true, "/\/\s*(\d+)/"))
                ->rooms($this->http->FindSingleNode("./descendant::td[7]", $root, true, "/^(\d+)$/"));

            $roomDescription = $this->http->FindSingleNode("./descendant::td[8]", $root);

            if (!empty($roomDescription)) {
                $room = $h->addRoom();
                $room->setDescription($roomDescription);
            }

            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for your deposit of')]", null, true, "/{$this->opt($this->t('Thank you for your deposit of'))}\s*(\S)\d/");

            if (empty($currency)) {
                $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'If your reservation was canceled within 30 days of arrival, a')]", null, true, "/{$this->opt($this->t('If your reservation was canceled within 30 days of arrival, a'))}\s*(\S)\d/");
            }

            $h->price()
                ->total(cost($this->http->FindSingleNode("./descendant::td[13]", $root)))
                ->currency($currency)
                ->cost($this->http->FindSingleNode("./descendant::td[12]", $root))
                ->fee('TAX 1', '0' . $this->http->FindSingleNode("./descendant::td[10]", $root))
                ->fee('TAX 2', '0' . $this->http->FindSingleNode("./descendant::td[11]", $root));

            $this->detectDeadLine($h, $timeIn);
        }
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

    private function normalizeDate($str)
    {
        $str = str_replace('.', '', $str);
        //$this->logger->warning('dateIN: '.$str);
        $in = [
            // 07/15/21, 1:00 pm
            "#^(\d+)\/(\d+)\/(\d+)\,\s*([\d\:]+\s*a?p?m)$#",
        ];
        $out = [
            "$2.$1.20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->warning('dateOUT: '.$str);
        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $timeIn)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Deposits are refundable if reservations are cancelled at least (\d+) days prior to the designated check\-in time./i",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1] . 'days');
        }
    }
}
