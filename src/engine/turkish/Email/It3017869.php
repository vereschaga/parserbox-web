<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// pdf parsed in turkish/ElektronikBiletPdf
class It3017869 extends \TAccountChecker
{
    public $mailFiles = "turkish/it-210021327.eml, turkish/it-216106348.eml, turkish/it-3017869.eml, turkish/it-51387314.eml";

    public $detectSubject = [
        'THY - Elektronik Bilet Yolcu Seyahat Belgesi',
    ];
    public $detectBody = [
        'en' => ['ELECTRONIC TICKET PASSENGER ITINERARY'],
    ];

    private $dateRelative;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'eticketitinerary@thy.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $detectedFrom = $this->detectEmailFromProvider($headers['from']);

        if ($detectedFrom !== true && stripos($headers["subject"], "THY - ") === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (($detectedFrom === true || stripos($re, "THY - ") !== false)
            && stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains('turkishairlines.com', '@href')}] | //text()[contains(., 'THY Genel Müdürlüğü')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        if ($this->http->XPath->query("//tr[*[1][normalize-space() = 'Den/A'] and *[2][normalize-space() = 'Taşıyıcı'] ]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function parseEmail(Email $email): void
    {
        $this->dateRelative = $this->normalizeDate($this->getParam('Issue Date', "/^(?:.*\/)?\s*(.+)$/"));

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->getParam('Booking Ref', "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->getParam('Passenger Name', "/^\s*([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])(?:[ ]+(?:MISS|MSTR|MRS|MR|MS)[\d ]*)?(?:[ ]+(?:ADT|CHD)[\d ]*)?\s*$/u"))
        ;

        // Program
        $account = $this->getParam('Payment', "/-TK(\d{5,})\s*$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        // Issued
        $tickets = $this->getParam('Ticket Number', "/^\s*([\d\s]{10,})\s*$/");
        $f->issued()
            ->tickets(explode(' ', $tickets), false);

        // Price
        $miles = $this->getParam('Endorsmen / Restr', "/^(\d+)MIL\./");

        if (!empty($miles)) {
            $f->price()
                ->spentAwards($miles);
        }
        $currency = $this->getParam('/ Total', "/^\s*([A-Z]{3})\s*\d[\d,.]*A?$/");
        $total = $this->getParam('/ Total', "/^\s*[A-Z]{3}\s*(\d[\d,.]*)A?$/");

        if (!empty($total)) {
            $f->price()
                ->currency($currency)
                ->total(PriceHelper::parse($total, $currency))
            ;

            $taxes = explode(' ', $this->getParam('/ Tax'));

            foreach ($taxes as $tax) {
                if (preg_match("/^(\d[\d., ]*)([A-Z][A-Z\d]*)$/", $tax, $m)) {
                    $f->price()
                        ->fee($m[2], PriceHelper::parse($m[1], $currency));
                }
            }
        }
        $cost = $this->getParam('Base Fare');

        if (
               (!empty($currency) && preg_match("/^\s*({$currency}) *(\d[\d.,]*)\s*$/", $cost, $m))
            || (empty($currency) && preg_match("/^\s*([A-Z]{3}) *(\d[\d.,]*)\s*$/", $cost, $m))
        ) {
            $f->price()
                ->cost(PriceHelper::parse($m[2], $m[1]))
                ->currency($m[1])
            ;
        }
        // Segments
        $xpath = "*[1][normalize-space()='From/To'] and *[2][normalize-space()='Carrier']";
        $nodes = $this->http->XPath->query("//tr[{$xpath}]/ancestor::table[1]/descendant::tr[ preceding::tr[{$xpath}] and *[2][normalize-space()] and *[10] ]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("*[2]", $root, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])$/'))
                ->number($this->http->FindSingleNode("*[3]", $root, true, '/^\d+$/'))
            ;

            // Departure and Arrival Airport
            $route = $this->htmlToText($this->http->FindHTMLByXpath('*[1]', null, $root));

            if (preg_match("/^[*\s]*([\s\S]+?)\s*\/\s*([A-Z]{3})\s+([\s\S]+?)\s*\/\s*([A-Z]{3})\b/", $route, $m)) {
                /*
                    ISTANBUL/SAW
                    DENIZLI/DNZ

                    *ANADOLUJET*
                */
                $s->departure()
                    ->code($m[2])
                    ->name($m[1])
                ;
                $s->arrival()
                    ->code($m[4])
                    ->name($m[3])
                ;
            }

            // Departure and Arrival DateTime
            $depDate = $arrDate = null;
            $datesText = $this->htmlToText($this->http->FindHTMLByXpath('*[5]', null, $root));
            $dates = preg_split("/([ ]*\n+[ ]*)+/", $datesText);

            if (count($dates) == 2) {
                $depDate = $this->normalizeDate($dates[0]);
                $arrDate = $this->normalizeDate($dates[1]);
            } elseif (count($dates) == 1
                && (preg_match("/^\s*(?:\S+? *\/\s*)?(\w+)\s*$/", $dates[0], $m)
                    || preg_match("/^\s*(\d{2}-\d{2})\s*$/", $dates[0], $m)
                )) {
                $depDate = $arrDate = $this->normalizeDate($m[1]);
            }

            $depTime = $arrTime = null;
            $timesText = $this->htmlToText($this->http->FindHTMLByXpath('*[6]', null, $root));
            $times = preg_split("/([ ]*\n+[ ]*)+/", $timesText);

            if (count($times) == 2) {
                $depTime = $this->normalizeTime($times[0]);
                $arrTime = $this->normalizeTime($times[1]);
            } elseif (count($times) == 1 && preg_match("/^\d{1,2}[: ]*\d{2}$/", $times[0])) { // it-216106348.eml
                // 2140    |    21:40
                $depTime = $this->normalizeTime($times[0]);
                $arrDateText = $this->htmlToText($this->http->FindHTMLByXpath('*[7]', null, $root));

                if (preg_match("/^\s*(\d{2}-\d{2})\s*$/", $arrDateText, $m)) {
                    $arrDate = $this->normalizeDate($m[1]);
                }

                $arrTime = $this->htmlToText($this->http->FindHTMLByXpath('*[8]', null, $root));
                $arrTime = $this->normalizeTime($arrTime);
            } elseif (count($times) == 1 && $this->http->XPath->query("//tr[starts-with(normalize-space(), 'From/To') and contains(normalize-space(), 'Date Time')]")->length > 0) { // it-210021327.eml
                $timesText = $this->htmlToText($this->http->FindHTMLByXpath('*[5]', null, $root));
                $times = preg_split("/([ ]*\n+[ ]*)+/", $timesText);

                if (count($times) == 3) {
                    $depDate = $arrDate = $this->normalizeDate($times[0]);

                    $depTime = $this->normalizeTime($times[1]);
                    $arrTime = $this->normalizeTime($times[2]);
                } elseif (count($times) == 4) {
                    $depDate = $this->normalizeDate($times[0]);
                    $arrDate = $this->normalizeDate($times[2]);

                    $depTime = $this->normalizeTime($times[1]);
                    $arrTime = $this->normalizeTime($times[3]);
                }
            }

            if (!empty($depDate) && !empty($depTime)) {
                $s->departure()
                    ->date(strtotime($depTime, $depDate));
            }

            if (!empty($arrDate) && !empty($arrTime)) {
                $s->arrival()
                    ->date(strtotime($arrTime, $arrDate));
            }

            $terminals = $this->parseTerminals($this->htmlToText($this->http->FindHTMLByXpath('*[13]', null, $root)))
                ?? $this->parseTerminals($this->htmlToText($this->http->FindHTMLByXpath('*[last()]', null, $root)));

            if ($terminals !== null) {
                $s->departure()->terminal($terminals['dep'], false, true);
                $s->arrival()->terminal($terminals['arr'], false, true);
            }

            $clsText = $this->htmlToText($this->http->FindHTMLByXpath('*[4]', null, $root));

            if (preg_match("/^[A-Z]{1,2}$/", $clsText)) {
                $s->extra()->bookingCode($clsText);
            }
        }
    }

    private function parseTerminals(string $text): ?array
    {
        $rows = preg_split("/([ ]*\n+[ ]*)+/", $text);

        if (count($rows) === 2) {
            return [
                'dep' => $rows[0] == '0' ? null : $rows[0],
                'arr' => $rows[1] == '0' ? null : $rows[1],
            ];
        }

        return null;
    }

    private function getParam($title, $regexp = null): ?string
    {
        return $this->http->FindSingleNode("//*[self::td or self::th][not(.//td) and not(.//th)][" . $this->contains($title) . "][following-sibling::*[normalize-space()][1][normalize-space() = ':']]/following-sibling::*[normalize-space()][2]",
            null, true, $regexp);
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = ' . print_r($date, true));
        $in = [
            // 10NOV21
            '/^\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{2})\s*$/iu',
            // 13JAN; 13OCAK / 13JAN
            '/^\s*(?:\w+ \/ )?(\d{1,2})\s*([[:alpha:]]+)\s*$/ui',
            // 06-12
            '/^\s*(\d{2})\s*-\s*(\d{2})\s*$/',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2',
            '$1.$2',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^(\d{2})\.(\d{2})\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011));
        }
//        $this->logger->debug('$date 2 = ' . print_r($date, true));

//        if (preg_match("#\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $date = str_replace($m[1], $en, $date);
//            }
//        }
        if (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } elseif (!empty($this->dateRelative)) {
            $date = EmailDateHelper::parseDateRelative($date, $this->dateRelative);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeTime($time)
    {
//        $this->logger->debug('$time = ' . print_r($time, true));
        $in = [
            // 0612
            '#^\s*(\d{1,2})(\d{2})\s*$#iu',
        ];
        $out = [
            '$1:$2',
        ];
        $time = preg_replace($in, $out, $time);
//        $this->logger->debug('$time 2 = ' . print_r($time, true));

        return $time;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
