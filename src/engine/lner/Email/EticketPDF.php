<?php

namespace AwardWallet\Engine\lner\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers thetrainline/YourEticketsPdf (in favor of thetrainline/YourEticketsPdf)

class EticketPDF extends \TAccountChecker
{
    public $mailFiles = "lner/it-119721823.eml";
    private $subjects = [
        'Your Amended Journey',
    ];

    private $lang = '';

    private $detectLang = [
        'en' => ['Itinerary'],
    ];

    private $pdfPattern = '.+\.pdf';
    private $detectBody = [
        'en' => ['Information relating to compensation in the event'],
    ];

    private static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tripsy.app') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($textPdf);

            foreach ($this->detectBody as $lang => $words) {
                foreach ($words as $detectBody) {
                    if (stripos($textPdf, $detectBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]@tripsy\.app$/', $from) > 0;
    }

    public function ParseTrain(Email $email, $text)
    {
        $segments = array_filter(preg_split("/[A-Z\d]{11}\n+[=]/", $text));

        foreach ($segments as $segment) {
            $t = $email->add()->train();

            $traveller = str_replace(['Mrs', 'Mr', 'Ms'], '', $this->re("/{$this->opt($this->t('Passenger:'))}\n(\D+)\n+{$this->opt($this->t('Contact Information:'))}/u", $segment));

            if (!empty($traveller)) {
                $t->general()
                    ->traveller($traveller);
            }

            $t->general()
                ->noConfirmation()
                ->date(strtotime($this->re("/Purchased on\s*(.+)/", $segment)));

            $t->addTicketNumber($this->re("/{$this->opt($this->t('Ticket Number'))}\s*([A-Z\d]{10,})/", $segment), false);

            $date = '';

            if (preg_match("/\s*(?<date>\d+\s*\w+\s*\d{4})\s*(?<depCode>[A-Z]{3})[\s\-]+(?<arrCode>[A-Z]{3})\n+(?<depName>.+)[ ]{5,}(?<arrName>.+)/u", $segment, $m)) {
                $s = $t->addSegment();

                $date = $m['date'];

                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
            }

            if (isset($s) && (preg_match("/Itinerary[\s\-]+.+\n\s*(?<depName>.+)\s*\n\s*(?<depTime>\d+\:\d+)\s*(?<company>.+)\s+Coach\s*(?<cabin>.+)[, ]\s*Seat\s*(?<seat>.+)\n\s*(?<arrTime>\d+\:\d+)\n\s*(?<arrName>.+)\n+ *Ticket\s*Details\:/u", $segment, $m)
            || preg_match("/Itinerary[\s\-]+.+\n\s*(?<depName>.+)\s*\n\s*(?<depTime>\d+\:\d+)\s*(?<company>.+)\n\s*(?<arrTime>\d+\:\d+)\n\s*(?<arrName>.+)\n+ *Ticket\s*Details\:/u", $segment, $m))) {
                $s->departure()
                    ->name('United Kingdom, ' . $m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->name('United Kingdom, ' . $m['arrName'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));

                if (isset($m['seat'])) {
                    $s->addSeat($m['seat']);
                }

                if (isset($m['seat'])) {
                    $s->setCabin(trim($m['cabin'], ','));
                }

                $s->setServiceName($m['company']);

                $s->extra()
                    ->noNumber();
            }

            if (preg_match("/Itinerary[\s\-]+.*\s+(\d+\s*\w+)\:\s*\n\s*(?<depTime>\d+\:\d+)\s*(?<company>.+)\n+\s*From\s*(?<depName>.+)\n\s*To\s*(?<arrName>.+)\nCoach\s*(?<cabin>.+)[, ]\s*Seat\s*(?<seat>.+)\n\n\n/", $segment, $m)) {
                $s->departure()
                    ->name('United Kingdom, ' . $m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $arrTime = $this->http->FindSingleNode("//text()[{$this->eq($m['depName'])}]/ancestor::tr[1][{$this->starts($m['depTime'])}]/following::tr[1][{$this->contains($m['arrName'])}]/descendant::text()[normalize-space()][1]", null, true, "/^([\d\:]+)$/");
                $s->arrival()
                    ->name('United Kingdom, ' . $m['arrName'])
                    ->date(strtotime($date . ', ' . $arrTime));

                if (isset($m['seat'])) {
                    $s->addSeat($m['seat']);
                }

                if (isset($m['seat'])) {
                    $s->setCabin(trim($m['cabin'], ','));
                }

                $s->setServiceName($m['company']);

                $s->extra()
                    ->noNumber();
            }

            $price = $this->re("/Price\s*(\D+[\d\.\,]+)/", $segment);

            if (preg_match("/(\D+)([\d\.\,]+)/", $price, $m)) {
                $t->price()
                    ->currency($this->normalizeCurrency($m[1]))
                    ->total($m[2]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($textPdf);

            $this->ParseTrain($email, $textPdf);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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
}
