<?php

namespace AwardWallet\Engine\lanai\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingNumberPDF extends \TAccountChecker
{
    public $mailFiles = "lanai/it-127305644.eml, lanai/it-127308854.eml";
    public $subjects = [
        '/Lanai Air - Booking Number/',
    ];

    public $subject;

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $depDate = '';
    public $arrDate = '';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@lanaiair.com') !== false) {
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

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (strpos($text, 'Lanai Air') !== false && strpos($text, 'DEPARTING PASSENGER NAMES') !== false && strpos($text, 'BOOKING NUMBER:') !== false
                ) {
                    return true;
                }
            }
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Welcome on board Lana’i Air')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Booking number')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Departing'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Arriving'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]lanaiair.com$/', $from) > 0;
    }

    public function ParseFlightHTML(Email $email)
    {
        $f = $email->add()->flight();

        $operator = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'operated by')]", null, true, "/{$this->opt($this->t('operated by'))}\s*(.+)/");
        $airlineName = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Welcome on board')]", null, true, "/{$this->opt($this->t('Welcome on board'))}\s*(.+)\,/");

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking number')]", null, true, "/{$this->opt($this->t('Booking number'))}\s*(\d{6})/"));

        $traveller = $this->re("/\s*\-\s*(\D+)\s*\)$/", $this->subject);

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller, true);
        }

        $xpath = "//text()[starts-with(normalize-space(), 'Departing Flight')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($airlineName)
                ->number($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Departing Flight'))}\s*(\d+)\:/"));

            if (!empty($operator)) {
                $s->airline()
                    ->operator(trim($operator, '.'));
            }

            $depText = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Departing')]", $root);

            if (preg_match("/\((?<depCode>[A-Z]{3})\)\s*at\s*(?<time>[\d\:]+\s*A?P?M?)\s*\w+\s*(?<date>\d+\s*\w+\s*\d{4})\.$/", $depText, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['date'] . ' ' . $m['time']));
            }

            $arrText = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Arriving')]", $root);

            if (preg_match("/\((?<arrCode>[A-Z]{3})\)\s*at\s*(?<time>[\d\:]+\s*A?P?M?)\s*\w+\s*(?<date>\d+\s*\w+\s*\d{4})\.$/", $arrText, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['date'] . ' ' . $m['time']));
            }
        }
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $this->logger->debug($text);
        $confNumber = $this->re("/BOOKING NUMBER:\s*(\d+)\n/", $text);

        if (!empty($confNumber)) {
            $f = $email->add()->flight();

            $operator = $this->re("/Operated by (.+)/", $text);
            $airlineName = $this->re("/All transportation of passengers and baggage for\s*(.+)\s*operated/", $text);

            $f->general()
                ->confirmation($confNumber);

            if (preg_match_all("/(?:DEPARTING PASSENGER NAMES|RETURNING PASSENGER NAMES)\s*\(\d+\)\n(\s*[•]\D+\n)\n*\s*CHECK IN BY/", $text, $m)) {
                $paxText = implode(',', str_replace("\n", ",", $m[1]));
                $pax = array_filter(array_unique(explode(',', preg_replace("/\s*[•]\s*/", "", $paxText))));

                $f->general()
                    ->travellers(array_unique($pax), true);
            }

            $preg = "/(DETAILS\n\s*[\d\:]+\s*A?P?M?\s*\D+[ ]{4,}[\-\>]+\s*\D+\s*FLIGHT:\s*\d+\n+\s*.+A?P?M[ ]{4,}.+A?P?M[ ]{4,}CLASS:\s*.+\n)/u";

            if (preg_match_all($preg, $text, $m)) {
                foreach ($m[1] as $seg) {
                    if (preg_match("/DETAILS\n\s*[\d\:]+\s*A?P?M?\s*(?<depName>\D+)[ ]{4,}[\-\>]+\s*(?<arrName>\D+)\s*FLIGHT:\s*(?<flightNumber>\d+)\n+\s*(?<depDate>.+A?P?M)[ ]{4,}(?<arrDate>.+A?P?M)[ ]{4,}CLASS:\s*(?<cabin>.+)\n/u", $seg, $match)) {
                        $s = $f->addSegment();

                        $s->airline()
                            ->name($airlineName)
                            ->number($match['flightNumber'])
                            ->operator(trim($operator, '.'));

                        $s->departure()
                            ->name($match['depName'])
                            ->date(strtotime($match['depDate']))
                            ->noCode();

                        $s->arrival()
                            ->name($match['arrName'])
                            ->date(strtotime($match['arrDate']))
                            ->noCode();

                        $s->extra()
                            ->cabin($match['cabin']);
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->ParseFlightPDF($email, $text);
            }
        } else {
            $this->ParseFlightHTML($email);
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
}
