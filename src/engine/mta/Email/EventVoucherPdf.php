<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-512781092.eml";
    public $subjects = [
        'Booking Confirmation #',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@mtatravel.com') !== false)) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'MTA Travel -') !== false || stripos($text, 'TravelManagers') !== false)
                && stripos($text, 'Activity Name:') !== false
                && stripos($text, 'Attraction Dates:') !== false
                && stripos($text, 'Pax Details:') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel\.com.*$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseEvent($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email, string $text)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $e->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your Reference No:'))}\s*([A-Z\d\-]{6,})/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Customer Name:'))}\s*([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\n/", $text))
            ->date(strtotime($this->re("/{$this->opt($this->t('Booking Date:'))}\s+(.+)/", $text)));

        $name = $this->re("/{$this->opt($this->t('Activity Name:'))}\s*(.+)\,\s*{$this->opt($this->t('Pick up from'))}/", $text);
        $address = $this->re("/Pick-up available from hotels in\s*(.+)\.\s*Please confirm your pick-up/", $text);

        if (empty($name) && empty($address)) {
            if (preg_match("/{$this->opt($this->t('Attraction Details:'))}\s+(?<name>.+)\s+\|\s+(?<address>.+)\n/u", $text, $m)) {
                $name = $m['name'];
                $address = $m['address'];
            }
        }

        if (stripos($address, 'Activity Name:') !== false) {
            $address = $this->re("/Meeting point:\s*(.+)Meeting time:/", $text);
        }

        if (preg_match("/{$this->opt($this->t('Pax Details:'))}\s*{$this->opt($this->t('Adults:'))}\s*(?<adults>\d+)\,\s*{$this->opt($this->t('Children:'))}\s*(?<kids>\d+)/", $text, $m)) {
            $e->booked()
                ->guests($m['adults']);

            if (isset($m['kids']) && $m['kids'] !== null) {
                $e->booked()
                    ->kids($m['kids']);
            }
        }

        if (preg_match("/Meeting point instructions:\s+(.+)\n\s*End point\:/s", $text, $m)) {
            $e->setNotes(str_replace("\n", "", $m[1]));
        }

        if (!empty($name)) {
            $e->setName($name);
        }

        if (!empty($address)) {
            $e->setAddress($address);
        }

        $timeStart = $this->re("/Activity Name\:.*\s(\d+\:\d+)\s+\-/", $text);
        $dateStart = $this->re("/Attraction Dates.*From\s+(.+)\s+\|/", $text);
        $e->booked()
            ->start(strtotime($dateStart . ', ' . $timeStart));

        $duration = $this->re("/{$this->opt($this->t('Duration:'))}\s*(\d+\s*\w+)\n/", $text);

        if (empty($duration)
            && !empty($timeStart)
            && preg_match("/{$this->opt($this->t('Duration:'))}\s*(?<hours>\d+)\s*\w+\s+\w+\s*(?<min>\d+)\s*\w+\n/", $text, $m)) {
            $duration = (60 * intval($m['hours'])) + intval($m['min']) . ' min';
        }

        if (!empty($duration)) {
            $e->booked()
                ->end(strtotime($duration, $e->getStartDate()));
        } else {
            $e->booked()
                ->noEnd();
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total price:']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,]+)$/", $price, $m)) {
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $earnedPoints = $this->http->FindSingleNode("//text()[normalize-space()='You Earned:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (!empty($earnedPoints)) {
            $e->setEarnedAwards($earnedPoints);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})\s*at\s*([\d\:]+)$#u", //23-Oct-2023 at 11:15
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
