<?php

namespace AwardWallet\Engine\vy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BusBooking extends \TAccountChecker
{
    public $mailFiles = "vy/it-472530518.eml, vy/it-611976581.eml";
    public $subjects = [
        '/Booking confirmation/',
    ];

    public $lang = 'en';
    public $segments = [];
    public $travellers = [];
    public $ticket = [];
    public $seat = [];
    public $confNumbers;
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@vy.no') !== false)) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Vy Buss AS') !== false || stripos($text, 'Vy Travel AB') !== false)
             && stripos($text, 'Electronic receipt') !== false
             && stripos($text, 'Booking no.:') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vy\.no$/', $from) > 0;
    }

    public function ParseBusPDF(Email $email, $text)
    {
        $segArray = array_filter($this->split("/({$this->opt($this->t('Ticket'))}\s*{$this->opt($this->t('Electronic receipt'))})/", $text));
        $b = $email->add()->bus();

        foreach ($segArray as $segText) {
            $this->confNumbers[] = $this->re("/{$this->opt($this->t('Booking no.:'))}\s*(\d{5,})/", $segText);

            if (preg_match_all("/Name:\s*(\D+)\n/", $text, $m)) {
                $this->travellers = array_merge($this->travellers, $m[1]);
            }

            $year = $this->re("/{$this->opt($this->t('Ticket bought:'))}\s*\d+\.\d+\.(\d{4})\s*\d+\:/", $segText);

            if (preg_match("/(?<depName>.+)\s+\-\s+(?<arrName>.+)\n(?<date>\d+\.\s*\w+)\,\s*(?<depTime>[\d\:]+)\s*\-\s+(?<arrTime>[\d\:]+)(?: *\((?<overnight>\+\d)\))?/u", $segText, $m)) {
                $seg = $m['depName'] . $m['depTime'];

                $seat = $this->re("/Seat\:\s*([A-Z\d]+)\s+/", $segText);

                if (empty($this->segments) || in_array($seg, $this->segments) === false) {
                    $this->segments[] = $seg;

                    $s = $b->addSegment();

                    if (preg_match("/\s+(?<number>[A-Z\d]{2,5})\s+(?<type>.*)\n*\s+(?<ticket>\d{10,}\n)/", $segText, $match)) {
                        $s->setNumber($match['number']);

                        $s->departure()
                            ->name($m['depName'])
                            ->date(strtotime($m['date'] . ' ' . $year . ', ' . $m['depTime']));

                        $s->arrival()
                            ->name($m['arrName'])
                            ->date(strtotime($m['date'] . ' ' . $year . ', ' . $m['arrTime']));

                        if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                            $s->arrival()
                                ->date(strtotime($m['overnight'] . ' day', $s->getArrDate()));
                        }

                        $s->setBusType($match['type']);

                        $this->ticket[] = $match['ticket'];

                        if (!empty($seat)) {
                            $s->extra()
                                ->seat($seat);
                        }
                    }
                } else {
                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }
        }

        $b->general()
            ->travellers(array_unique(array_filter($this->travellers)));

        $b->setTicketNumbers(array_unique($this->ticket), false);

        foreach (array_unique(array_filter($this->confNumbers)) as $confNumber) {
            $b->general()
                ->confirmation($confNumber);
        }

        if (preg_match_all("/Total\s*([\d\.\,]+)\.\-/", $text, $m)) {
            $currency = 'NOK';
            $b->price()
                ->total(PriceHelper::parse(array_sum($m[1]), $currency))
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseBusPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
}
