<?php

namespace AwardWallet\Engine\ninja\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "ninja/it-180975401.eml, ninja/it-182506377.eml, ninja/it-182849239.eml, ninja/it-207025984.eml, ninja/it-229824257.eml";
    public $subjects = [
        'Your payment confirmation |',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $subject;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rail.ninja') !== false) {
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

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (strpos($text, 'Rail.Ninja') !== false
                    && strpos($text, 'THIS IS NOT A TICKET') !== false
                    && strpos($text, 'Ticket order RN') !== false
                ) {
                    return true;
                }
            }
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Rail.Ninja')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Ticket information:')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Order details')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Your payment details:')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rail\.ninja$/', $from) > 0;
    }

    public function ParseTrainPDF(Email $email, $text): void
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/Ticket order\s*(RN[\d\-]+)/", $text))
            ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Issue date:'))}\s*(\w+\s*\d+\,\s*\d{4})/", $text)))
            ->traveller($this->re("/{$this->opt($this->t('Account:'))}\s*([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])[ ]{10,}/", $text));

        $currency = $this->re("/{$this->opt($this->t('Total:'))}\s*[\d\.\,]+\s*([A-Z]{3})/", $text);
        $cost = $this->re("/{$this->opt($this->t('Subtotal:'))}\s*([\d\.\,]+)/", $text);
        $tax = $this->re("/{$this->opt($this->t('Tax:'))}\s*([\d\.\,]+)/", $text);
        $total = $this->re("/{$this->opt($this->t('Total:'))}\s*([\d\.\,]+)/", $text);
        $t->price()
            ->cost(PriceHelper::parse($cost, $currency))
            ->tax(PriceHelper::parse($tax, $currency))
            ->total(PriceHelper::parse($total, $currency))
            ->currency($currency);

        $segmentText = $this->re("/\n+(^\s*Train\s*[#].+)\n+\s*Subtotal/msu", $text);
        $segments = $this->splitText($segmentText, "/(\s*Train\s*[#].+)/u", true);

        // it-207025984.eml
        if (count($segments) === 0
            && stripos($text, 'Train') == false
            && stripos($text, 'Payment for ticket modification') !== false
            && $this->http->XPath->query("//text()[normalize-space()='Ticket information:']/following::text()[starts-with(normalize-space(), 'Train #')]")->length > 0) {
            $email->removeItinerary($t);
            $this->ParseTrainHTML($email);
        }

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            if (preg_match("#\s*Train\s*\#(?<trainNumber>[A-Z\d]+)\s*(?<depName>.+)\s*\-\-(?<arrName>.+)\.\s*{$this->opt($this->t('Departure date/time:'))}\s*(?<depDate>.+)\s*{$this->opt($this->t('Arrival date/time:'))}(?<arrDate>.+)\.\s*{$this->opt($this->t('Ticket class:'))}\s*(?<cabin>[A-z\s\d]+)(?:\((?<bookingCode>[A-Z])\))?\.*\s*{$this->opt($this->t('Seat'))}?#su", $segment, $m)) {
                $s->setNumber($m['trainNumber']);

                $s->departure()
                    ->name(str_replace("\n", " ", $m['depName']))
                    ->date($this->normalizeDate(str_replace("\n", " ", preg_replace("/\s+/", " ", $m['depDate']))));

                $s->arrival()
                    ->name(str_replace("\n", " ", preg_replace("/\s+/", " ", $m['arrName'])))
                    ->date($this->normalizeDate(str_replace("\n", " ", preg_replace("/\s+/", " ", $m['arrDate']))));

                $s->extra()
                    ->cabin($m['cabin']);

                if (isset($m['bookingCode'])) {
                    $s->extra()
                        ->bookingCode($m['bookingCode']);
                }
            }
        }
    }

    public function ParseTrainHTML(Email $email): void
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/^.+(RN[\d\-]+)/", $this->subject))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Account:']/ancestor::tr[1]/descendant::td[2]"));

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Transaction amount:']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $priceText, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $segmentText = $this->http->FindSingleNode("//text()[normalize-space()='Ticket information:']/following::text()[normalize-space()][1]/ancestor::td[1]");
        $segments = $this->splitText($segmentText, "/(Train\s*[#])/u", true);

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            if (preg_match("/Train\s*#\s*(?<serviceName>[A-Z]*?)?\s*(?<trainNumber>\d+)\s*(?<depName>.{2,}?)\s*--\s*(?<arrName>.{2,}?)\s*\.\s*{$this->opt($this->t('Departure date/time:'))}/i", $segment, $m)) {
                if (!empty($m['serviceName'])) {
                    $s->extra()->service($m['serviceName']);
                }

                $s->setNumber($m['trainNumber']);
                $s->departure()->name($m['depName']);
                $s->arrival()->name($m['arrName']);
            }

            if (preg_match("/{$this->opt($this->t('Departure date/time:'))}\s*(?<depDate>.+?)[.\s]*{$this->opt($this->t('Arrival date/time:'))}/i", $segment, $m)) {
                $s->departure()->date($this->normalizeDate($m['depDate']));
            }

            if (preg_match("/{$this->opt($this->t('Arrival date/time:'))}\s*(?<arrDate>.+?)[.\s]*{$this->opt($this->t('Ticket class:'))}/i", $segment, $m)) {
                $s->arrival()->date($this->normalizeDate($m['arrDate']));
            }

            if (preg_match("/{$this->opt($this->t('Ticket class:'))}\s*(?<cabin>[-A-z\s\d)(á]+?)\s*(?:\(\s*(?<bookingCode>[A-Z])\s*\))?\+?\.\s*{$this->opt($this->t('Seat'))}/iu", $segment, $m)) {
                $s->extra()->cabin($m['cabin']);

                if (!empty($m['bookingCode'])) {
                    $s->extra()->bookingCode($m['bookingCode']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $this->subject = $parser->getSubject();

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (preg_match("/Train\s+\#\d+\|\d+\s+/", $text)) {
                    $email->setIsJunk(true);
                } elseif (!preg_match("/Train\s+\#\d+/", $text) && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Train #')]")->length > 0) {
                    //it-229824257.eml
                    $this->ParseTrainHTML($email);
                } else {
                    $this->ParseTrainPDF($email, $text);
                }
            }
        } else {
            $segmentText = $this->http->FindSingleNode("//text()[normalize-space()='Ticket information:']/following::text()[normalize-space()][1]/ancestor::td[1]");

            if (preg_match("/Train\s+\#\d+\|\d+\s+/", $segmentText)) {
                $email->setIsJunk(true);
            } else {
                $this->ParseTrainHTML($email);
            }
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

    private function re($re, $str, $c = 1): ?string
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // Wed, 10 Aug 2022, 08:00.
            "/^\w+\,\s*(\d+)\s*(\w+)\s*(\d{4})\,\s*([\d\:]+)\.?\s*$/su",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
