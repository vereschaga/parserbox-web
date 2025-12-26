<?php

namespace AwardWallet\Engine\trainpal\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "trainpal/it-828563085.eml, trainpal/it-821146219.eml, trainpal/it-831021174.eml, trainpal/it-822726422.eml";

    private $subjects = [
        'en' => ['Your booking confirmation']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // Html
            'otaConfNumber' => ['Booking ID'],
            'confNumber' => ['Booking reference'],
            'direction' => ['Outbound', 'Return'],

            // Pdf
            // 'Ticket Number' => '',
        ]
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trainpal\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.mytrainpal.com/', 'www.mytrainpal.com', 'www.facebook.com/thetrainpal'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['with TrainPal app', 'mytrainpal.com. All rights reserved'])}]")->length === 0
        ) {
            return false;
        }
        return $this->assignLang() && $this->findPoints($this)->length > 0;
    }

    public static function findPoints($obj): \DOMNodeList
    {
        // used in thetrainline/YourEticketsPdf
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';
        return $obj->http->XPath->query("//tr[ *[normalize-space()][1][{$xpathTime}] and *[5] ]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('YourBooking' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        ];

        $t = $email->add()->train();

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]/ancestor::tr[ descendant::text()[normalize-space()][2] ][1]");

        if (preg_match("/^({$this->opt($this->t('otaConfNumber'))})[:\s]+([-A-Z\d]{4,40})$/", $otaConfirmation, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{4,40}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $t->general()->confirmation($confirmation, $confirmationTitle);
        } elseif ($otaConfirmation && $this->http->XPath->query("//text()[{$this->starts($this->t('confNumber'))}]")->length === 0) {
            $t->general()->noConfirmation();
        }

        $points = $this->findPoints($this);
        $depPoint = true;

        foreach ($points as $root) {
            $dateVal = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant-or-self::*[normalize-space() and not(self::tr) and not(.//tr[normalize-space()])][1]", $root, true, "/^.{3,}\b\d{4}$/");

            if (preg_match("/^(?<wday>[-[:alpha:]]{3,30})\s+(?<day>\d{1,2})\s+(?<year>\d{4,})$/u", $dateVal ?? '', $m)) { // it-821146219.eml
                // Sat 7 2024
                $miscDates = implode("\n", array_filter($this->http->FindNodes("//text()[{$this->starts($m['wday'] . ', ' . $m['day'])} and {$this->contains($m['year'])}]")));

                if (preg_match("/^{$this->opt($m['wday'] . ', ' . $m['day'])}\s+[[:alpha:]]+\s+{$this->opt($m['year'])}$/imu", $miscDates, $m2)) {
                    // Sat, 7 Sep 2024
                    $dateVal = $m2[0];
                } else {
                    $dateVal = null;
                }
            }

            $date = strtotime($dateVal);

            $time = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");
            $nameStation = $this->http->FindSingleNode("*[5]/descendant::text()[normalize-space()][1]/ancestor::div[1][not(.//tr[normalize-space()])]", $root);
            $serviceName = $this->http->FindSingleNode("*[5]/descendant::text()[normalize-space()][1]/ancestor::div[1][not(.//tr[normalize-space()])]/following-sibling::div[normalize-space()][1][not(.//tr[normalize-space()])]", $root);

            if ($nameStation && !preg_match("/\bEurope$/i", $nameStation)) {
                $nameStation = implode(', ', array_filter([trim($nameStation, ', '), 'Europe']));
            }

            $seats = [];
            $seatsText = $this->htmlToText( $this->http->FindHTMLByXpath("descendant::tr[ count(*)=2 and *[1][normalize-space()=''] and *[2][{$this->contains($this->t('Coach'))} or {$this->contains($this->t('Seat'))}] ]/*[2]", null, $root) );

            if (preg_match_all("/^[ ]*((?:[, ]*(?:{$this->opt($this->t('Coach'))}|{$this->opt($this->t('Seat'))})[- ]+[A-Z\d]+)+)[ ]*$/m", $seatsText, $seatMatches)) {
                // Coach B, Seat 17F
                $seats = $seatMatches[1];
            }

            if ($depPoint) {
                $s = $t->addSegment();

                $s->departure()->date(strtotime($time, $date))->name($nameStation)->geoTip('Europe');
                $s->extra()->service($serviceName, false, true);

                $carValues = $seatValues = [];

                foreach ($seats as $seatRow) {
                    $car = $seat = null;

                    if (preg_match("/^{$this->opt($this->t('Coach'))}[- ]+([A-Z\d]+)[,\s]+{$this->opt($this->t('Seat'))}[- ]+([A-Z\d]+)$/i", $seatRow, $m)) {
                        // Coach B, Seat 17F
                        $car = $m[1];
                        $seat = $m[2];
                    } elseif (preg_match("/(?:^|[,\s]){$this->opt($this->t('Seat'))}[- ]+([A-Z\d]+)$/i", $seatRow, $m)) {
                        // Seat 17F
                        $seat = $m[1];
                    }

                    if ($car !== null || $seat !== null) {
                        $carValues[] = $car;
                        $seatValues[] = $seat;
                    }
                }

                if (count(array_unique($carValues)) === 1) {
                    $s->extra()->car($carValues[0])->seats($seatValues);
                } elseif (count($seats) > 0) {
                    $s->extra()->seats($seats);
                }

                $depPoint = false;
                continue;
            }

            /** @var \AwardWallet\Schema\Parser\Common\TrainSegment $s */
            $s->arrival()->date(strtotime($time, $date))->name($nameStation)->geoTip('Europe');

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                $s->extra()->noNumber();
            }

            $depPoint = true;
        }

        $xpathTotalPrice = "tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total amount'))}] ]";
        $totalPrice = $this->http->FindSingleNode("//{$xpathTotalPrice}/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // £43.10
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query("//tr[count(*[normalize-space()])=2 and preceding::*[{$this->eq($this->t('Payment information'))}] and following::{$xpathTotalPrice}]");

            $discountAmounts = [];

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $feeCharge = implode('', $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space() and not(ancestor::*[contains(translate(@style,' ',''),'text-decoration:line-through')])]", $feeRow));
                $feeCharge = preg_replace('/^(.*?)\s*\(.*$/', '$1', $feeCharge);

                if ( preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', ltrim($feeCharge, '- '), $m) ) {
                    $feeAmount = PriceHelper::parse($m['amount'], $currencyCode);

                    if (preg_match('/^\s*-.+/', $feeCharge)) {
                        $discountAmounts[] = $feeAmount;
                    } else {
                        $t->price()->fee($feeName, $feeAmount);
                    }
                }
            }

            if (count($discountAmounts) > 0) {
                $t->price()->discount(array_sum($discountAmounts));
            }
        }

        // Tickets (from PDF) (examples: it-828563085.eml)

        $tickets = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Ticket Number'))}[: ]+([-A-Z\d]{5,55})$/im", $textPdf, $m)
                && !in_array($m[1], $tickets)
            ) {
                $t->addTicketNumber($m[1], false);
                $tickets[] = $m[1];
            }
        }

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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['direction']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['direction'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
