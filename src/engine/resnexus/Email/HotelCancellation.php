<?php

namespace AwardWallet\Engine\resnexus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCancellation extends \TAccountChecker
{
    public $mailFiles = "resnexus/it-355122507.eml";
    public $subjects = [
        '/^(.+)\s+\-\s+Reservation Cancellation\:\s*[#]\d+$/',
    ];

    public $lang = 'en';

    public $subject;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public $detectBody = [
        "en" => [
            'we have cancelled your reservation',
            'Your reservation cancelation number:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'communications@resnexus.com') !== false) {
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
        $text = $parser->getPlainBody();

        if (stripos($parser->getSubject(), 'Reservation Cancellation') !== false) {
            foreach ($this->detectBody as $lang => $words) {
                foreach ($words as $word) {
                    if (stripos($text, $word) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/communications[@.]resnexus\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $subject = $parser->getSubject();
        $text = $parser->getPlainBody();

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/[#]\s*(\d{4,})/", $text));

        if (stripos($subject, 'Reservation Cancellation') !== false) {
            $h->general()
                ->cancelled();
        }

        $h->hotel()
            ->name($this->re("/^(.+)\s+\-/", $subject));

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
            '#^(\d+)\/(\d+)\/(\d{2})$#', //6/25/21
            "#^(\w+)\s*(\d+)\,\s*(\d{4})$#", //Jul 11, 2021
        ];
        $out = [
            '$2.$1.20$3',
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/if canceled within (\d+ hours) of booking/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }

        if (preg_match('/In the event of a cancellation\, please notify us (\d+ hours?) prior to arrival\, (\d+A?P?M)/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1], $m[2]);
        }

        if (preg_match('/Cancellations must be made by (\d+ a?p?m) the day PRIOR to your scheduled arrival/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 day', $m[1]);
        }

        if (preg_match('/You can cancel with (\d+ days?) notice without penalty/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1], '-1 hour');
        }
    }
}
