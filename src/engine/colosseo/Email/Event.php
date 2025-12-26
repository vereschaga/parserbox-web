<?php

namespace AwardWallet\Engine\colosseo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "colosseo/it-750816157.eml";
    public $subjects = [
        'Parco archeologico del Colosseo booking confirmation',
    ];
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ticketcolosseo.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ticketcolosseo\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->logger->debug($text);

            if ((strpos($text, 'Show up at the entrance of the Colosseum') !== false || strpos($text, 'del Colosseo') !== false || strpos($text, 'COLOSSEO') !== false)
                && (strpos($text, 'BIGLIETTO ELETTRONICO') !== false)
                && (strpos($text, 'effettuata il giorno') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParseEventPDF(Email $email, $text)
    {
        $texts = array_filter(preg_split("/^(\s*BIGLIETTO ELETTRONICO)/mu", $text));

        foreach ($texts as $segText) {
            if (preg_match("/^\n\s+(?<conf>[A-Z\d]{12,})\n\s+(?<dateStart>\d+\/\d+\/\d{4})[\s\-]+(?<timeStart>\d+\:\d+)\n\s+(?<name1>.+\n*.*)\s+\((?<total1>[\.\,\d]+)\s*(?<currency>\D{1,3})\).*\n*(?:\s*(?<name2>.+)\((?<total2>[\d\.\,]+).*\n)?\s+(?<pax>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n\n*/u", $segText, $m)) {
                $e = $email->add()->event();
                $e->setEventType(EVENT_SHOW);

                $e->general()
                    ->confirmation($m['conf'])
                    ->traveller($m['pax']);

                $e->setStartDate(strtotime(str_replace('/', '.', $m['dateStart']) . ', ' . $m['timeStart']))
                    ->setNoEndDate(true);

                $name = !empty($m['name2']) ? $m['name1'] . ' ' . $m['name2'] : $m['name1'];

                if (!empty($name)) {
                    $e->setName($name);
                    $e->setAddress('Piazza del Colosseo, 1, 00184 Roma RM, Italy');
                }

                $currency = $this->normalizeCurrency($m['currency']);
                $total = array_sum([$m['total1'], $m['total2']]);

                if (!empty($currency) && $total !== null) {
                    $e->price()
                        ->total(PriceHelper::parse($total, $currency))
                        ->currency($currency);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseEventPDF($email, $text);
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
