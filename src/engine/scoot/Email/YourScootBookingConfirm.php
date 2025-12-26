<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourScootBookingConfirm extends \TAccountChecker
{
    public $mailFiles = "scoot/it-10023309.eml, scoot/it-10025360.eml, scoot/it-10684205.eml, scoot/it-176424468.eml, scoot/it-263233093.eml, scoot/it-33513014.eml, scoot/it-35462221.eml, scoot/it-5598243.eml, scoot/it-8032824.eml";

    public $langDetectors = [
        'zh' => ['酷航预订编号', '訂位代號'],
        'ja' => ['予約番号'],
        'en' => ['Booking Ref'],
        'th' => ['รหัสอ้างอิงการสำรองที่นั่ง'],
    ];

    public $reSubject = [
        'zh' => '您的酷航预订确认',
        '飛往 台北 的航班已經開放線上報到 Check-in 了!',
        'ja' => 'スクート予約確認',
        'en' => 'Your Scoot booking confirmation',
        'th' => 'หมายเลขอ้างอิงการสำรองที่นั่ง Scoot',
    ];

    public $lang = '';

    public static $dict = [
        'zh' => [
            'Booking Ref' => '酷航预订编号',
            'Flight'      => '航班',
            'Departure'   => '出发',
            'Arrival'     => '抵达',
            //            'Your payment details' => '',
            //            'Total' => '',
        ],
        'ja' => [
            'Booking Ref'          => '予約番号',
            'Flight'               => 'フライト',
            'Departure'            => '出発',
            'Arrival'              => '到着',
            'Your payment details' => 'お支払内容',
            'Total'                => '合計',
        ],
        'en' => [
            //            'Booking Ref' => '',
            'Flight'                => ['Flight', 'FLIGHT', 'Flight No.'],
            'Departure'             => ['Departure', 'DEPARTURE'],
            'Arrival'               => ['Arrival', 'ARRIVAL'],
            'Your Flight Details -' => ['Your Flight Details -', 'Your flight details -'],
            //            'Your payment details' => '',
            //            'Total' => '',
        ],
        'th' => [
            'Booking Ref'           => 'รหัสอ้างอิงการสำรองที่นั่ง Scoot:',
            'Flight'                => ['เที่ยวบิน<'],
            'Departure'             => ['ขาออก'],
            'Arrival'               => ['ขาเข้า'],
            //            'Your Flight Details -' => ['Your Flight Details -', 'Your flight details -'],
            'Your payment details' => 'รายละเอียดการชําระเงินของคุณ',
            'Total'                => 'รวม',
        ],
    ];

    private $type = '';

    private $patterns = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseEmail($email);

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, $this->t('Your payment details')) === false) {
                continue;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Total'))}\s+(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/m", $textPdf, $m)) {
                $email->price()
                    ->currency($m['currency'])
                    ->total($this->normalizeAmount($m['amount']));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $this->type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // if pdf - go to parse by YourBookingConfirmationPdf.php
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }
            $textPdf = str_replace(chr(194) . chr(160), ' ', $textPdf);

            if (\AwardWallet\Engine\scoot\Email\YourBookingConfirmationPdf::detectProvider($textPdf)
                && $this->detectForeignPdf($textPdf) && !preg_match("/[A-Z\d]{2}\s*\d{2,4}.+\-.*[ ]{5,}Depart.*\([A-Z]{3}\)\s+[\d\:]+\n/", $textPdf)
            ) {
                return false;
            }
        }

        if ($this->http->XPath->query('//node()[contains(.,"@flyscoot.com") or contains(.,"www.flyscoot.com") or contains(normalize-space(.),"Thanks for choosing Scoot") or contains(normalize-space(.),"The Scoot Team") or contains(normalize-space(.),"miles with Scoot")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//flyscoot.com") or contains(@href,".flyscoot.com/")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        // let  detectEmailByBody check
//        if (stripos($headers['from'], 'your-itinerary@flyscoot.com') !== false) {
//            foreach ($this->reSubject as $reSubject) {
//                if (stripos($headers['subject'], $reSubject) !== false) {
//                    return true;
//                }
//            }
//        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'The Scoot Team') !== false
            || stripos($from, '@flyscoot.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            if ($this->http->XPath->query('//node()[' . $this->contains($phrases) . ']')->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        /*
            Changi Airport (SIN)
            07 Mar 2019 9:10 AM
        */
        $this->patterns['airportDateTime'] = '/'
            . '\s*(?<name>.*?)\s*\(\s*(?<code>[A-Z]{3})\s*\)'
            . '\s+(?<dateTime>.{6,}\s*\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)'
            . '/';

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking Ref'))}])[1]/following::text()[string-length(normalize-space(.))>3 and not(contains(.,'gif'))][1]", null, true, "/^([A-Z\d]{5,7})$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking Ref'))}])[1]", null, true, "/{$this->opt($this->t('Booking Ref:'))}\s*([A-Z\d]{5,7})$/");
        }
        $f->general()
            ->confirmation($confirmation);

        // don't collet "Hi ..." - it can be an agent
        //$it['Passengers'][] = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Hi']/following::text()[normalize-space()][1]");

        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[{$this->contains($this->t('Departure'))}][1]/following::tr[contains(normalize-space(), ':')][.//table]//tr[ td[3] ]/td[1]//tr[string-length(normalize-space())>1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            // it-10023309.eml
            $this->type = '1';
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseSegments1($f, $segments);
        } elseif (($segments = $this->http->XPath->query($xpath = "//tr[ *[1]/descendant::text()[{$this->contains($this->t('Departure'))}] and *[2]/descendant::text()[{$this->contains($this->t('Arrival'))}] ]/following-sibling::*[count(*[normalize-space()])=2]"))->length === 1) {
            // it-33513014.eml
            $this->type = '2';
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseSegments2($f, $segments);
        } elseif (($segments = $this->http->XPath->query($xpath = "//text()[normalize-space()='Booking Ref:']/ancestor::tr[1]/following::text()[starts-with(normalize-space(), 'DEPARTURE')]/ancestor::tr[contains(normalize-space(), ':')][1]"))->length > 0) {
            // it-33513014.eml
            $this->type = '3';
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseSegments3($f, $segments);
        } elseif (($segments = $this->http->XPath->query($xpath = "//text()[normalize-space()='Flight Status']/following::text()[normalize-space()='Operating']/ancestor::tr[1]"))->length > 0) {
            // it-263233012.eml
            $this->type = '4';
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseSegments4($f, $segments);
        }
    }

    private function parseSegments1(\AwardWallet\Schema\Parser\Common\Flight $f, $segments)
    {
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $n = count($this->http->FindNodes("./preceding-sibling::tr", $root)) + 1;
            $root = $this->http->XPath->query("./ancestor::tr[1]", $root)->item(0);

            if (empty($this->http->FindSingleNode("./td[1]//tr[$n]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})\b#"))) {
                $f->removeSegment($s);

                break;
            }

            $s->airline()
                ->number($this->http->FindSingleNode("./td[1]//tr[{$n}]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$#"))
                ->name($this->http->FindSingleNode("./td[1]//tr[{$n}]/descendant::text()[normalize-space(.)][1]", $root, true, "#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+$#"));

            $operator = $this->http->FindSingleNode("./td[1]//tr[{$n}]/descendant::text()[normalize-space(.)][2]", $root);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $departureHtml = $this->http->FindHTMLByXpath("td[3]//tr[{$n}]", null, $root);
            $departureText = $this->htmlToText($departureHtml);

            if (preg_match($this->patterns['airportDateTime'], $departureText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($this->normalizeDate($m['dateTime'])));
            }

            $arrivalHtml = $this->http->FindHTMLByXpath("td[5]//tr[{$n}]", null, $root);
            $arrivalText = $this->htmlToText($arrivalHtml);

            if (preg_match($this->patterns['airportDateTime'], $arrivalText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($this->normalizeDate($m['dateTime'])));
            }
        }
    }

    private function parseSegments2(\AwardWallet\Schema\Parser\Common\Flight $f, $segments)
    {
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Flight Details -'))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Your Flight Details -'))}\s*(.+)$/");

            if (preg_match('/\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            $departureHtml = $this->http->FindHTMLByXpath('*[1]', null, $root);
            $departureText = $this->htmlToText($departureHtml);

            if (preg_match($this->patterns['airportDateTime'], $departureText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['dateTime']));
            }

            $arrivalHtml = $this->http->FindHTMLByXpath('*[2]', null, $root);
            $arrivalText = $this->htmlToText($arrivalHtml);

            if (preg_match($this->patterns['airportDateTime'], $arrivalText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['dateTime']));
            }
        }
    }

    private function parseSegments3(\AwardWallet\Schema\Parser\Common\Flight $f, $segments)
    {
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Flight Details -'))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Your Flight Details -'))}\s*(.+)$/");

            if (preg_match('/\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            $departureHtml = $this->http->FindHTMLByXpath('.', null, $root);
            $departureText = $this->htmlToText($departureHtml);

            if (preg_match($this->patterns['airportDateTime'], $departureText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['dateTime']));
            }

            $arrivalHtml = $this->http->FindHTMLByXpath("./following::text()[starts-with(normalize-space(), 'ARRIVAL')]/ancestor::tr[contains(normalize-space(), ':')][1]", null, $root);
            $arrivalText = $this->htmlToText($arrivalHtml);

            if (preg_match($this->patterns['airportDateTime'], $arrivalText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['dateTime']));
            }
        }
    }

    private function parseSegments4(\AwardWallet\Schema\Parser\Common\Flight $f, $segments)
    {
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^(.+)$/");

            if (preg_match('/\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            $departureHtml = $this->http->FindHTMLByXpath('./descendant::td[3]', null, $root);
            $departureText = $this->htmlToText($departureHtml);

            if (preg_match($this->patterns['airportDateTime'], $departureText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date(strtotime($m['dateTime']));
            }

            $arrivalHtml = $this->http->FindHTMLByXpath("./descendant::td[4]", null, $root);
            $arrivalText = $this->htmlToText($arrivalHtml);

            if (preg_match($this->patterns['airportDateTime'], $arrivalText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date(strtotime($m['dateTime']));
            }
        }
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $in = [
            "#^(\d{4})年(\w+月)(\d{1,2})日\s+(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$#u", // 2019年六月19日  8:15AM
            "#^(\d{4})年-(\d{1,2})月-(\d{1,2})日\s+(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$#", // 2019年-4月-27日 2:50PM
            "#^(\d+\s+[^\d\s]+\s+\d{4})\s+(\d+:\d+)[ap]m$#", // 06 Jan 2018 15:55pm
            "#^(\d+\s+[^\d\s]+\s+\d{4})\s+(\d+:\d+) [AP]M$#", // 01 Dec 2016 00:55 AM
        ];
        $out = [
            "$3 $2 $1, $4",
            "$2/$3/$1, $4",
            "$1, $2",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+?)\.?\s+\d{4}#", $str, $m)) {
            if (!$en = MonthTranslate::translate($m[1], $this->lang)) {
                $en = MonthTranslate::translate($m[1], 'es');
            }

            if ($en) {
                $str = str_replace($m[1], $en, $str);
            }
        }
//        $this->logger->debug($str);
        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectForeignPdf($text): bool
    {
        foreach (\AwardWallet\Engine\scoot\Email\YourBookingConfirmationPdf::$langDetectors as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }
}
