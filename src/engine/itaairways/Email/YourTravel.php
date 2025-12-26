<?php

namespace AwardWallet\Engine\itaairways\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourTravel extends \TAccountChecker
{
    public $mailFiles = "itaairways/it-136560478.eml, itaairways/it-136611143.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'      => ['RESERVATION NUMBER(PNR)', 'BOOKING CODE'],
            'OUTBOUND'        => ['OUTBOUND FLIGHT', 'OUTBOUND'],
            'INBOUND'         => ['RETURN FLIGHT', 'INBOUND'],
            'Flight Duration' => ['Flight Duration', 'Total duration'],
        ],
    ];

    private $detectors = [
        'en' => ['YOUR TRAVEL', 'TRAVEL DETAILS'],
    ];

    private $year = 0;

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match("/\b[A-Z\d]{5,}\s+(?i)ITA airways\b/", $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".itaspa.com/") or contains(@href,"www.itaspa.com") or contains(@href,"mybooking.itaspa.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourTravel' . ucfirst($this->lang));

        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';
        $xpathRow = '(self::tr or self::div or self::p)';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $totalPaid = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL PAID'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPaid, $matches)) {
            // EUR2,454.62
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $travellers = [];

        $segments = $this->http->XPath->query("//text()[ {$xpathTime} and following::text()[string-length(normalize-space())>2][1][{$xpathTime}] ]/ancestor::*[ preceding-sibling::*[normalize-space()] ][1]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = 0;
            $dateValue = $this->http->FindSingleNode("preceding::*[{$xpathRow}][{$this->starts($this->t('OUTBOUND'))} or {$this->starts($this->t('INBOUND'))}][1]", $root, true, "/(?:{$this->opt($this->t('OUTBOUND'))}|{$this->opt($this->t('INBOUND'))})\s*(.{3,})$/");

            if (preg_match("/^(?<wday>[-[:alpha:]]+)\s+(?<date>\d{1,2}\s+[[:alpha:]]+)$/u", $dateValue, $m)) {
                if ($m['wday'] === 'MAR') {
                    $m['wday'] = 'TUE';
                }
                $weekDateNumber = WeekTranslate::number1($m['wday']);
                $date = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $this->year, $weekDateNumber);
            }

            $airportsText = $this->htmlToText($this->http->FindHTMLByXpath("preceding::*[{$xpathRow}][string-length(normalize-space())>2][1]", null, $root));
            $airports = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $airportsText);

            if (count($airports) === 2) {
                if (preg_match("/(?:^|\()\s*([A-Z]{3})\s*\)?$/", $airports[0], $m)) {
                    $s->departure()->code($m[1]);
                } else {
                    $s->departure()->name($airports[0]);
                }

                if (preg_match("/(?:^|\()\s*([A-Z]{3})\s*\)?$/", $airports[1], $m)) {
                    $s->arrival()->code($m[1]);
                } else {
                    $s->arrival()->name($airports[1]);
                }
            }

            $timeDep = $timeArr = null;

            $timesText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

            if (preg_match("/(?<timeDep>{$patterns['time']})\s*(?<timeArr>{$patterns['time']})\s*{$this->opt($this->t('Flight Duration'))}[:\s]+(?<duration>\d[^:]+)$/", $timesText, $m)) {
                // 17:15 07:15 Flight Duration: 8hr 0min
                $timeDep = $m['timeDep'];
                $timeArr = $m['timeArr'];
                $s->extra()->duration($m['duration']);
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $seats = [];
            $belowRows = $this->http->XPath->query("following::*[{$xpathRow}][normalize-space()]", $root);

            foreach ($belowRows as $row) {
                $rowText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $row));

                if (preg_match("/^(?:Non[-\s]*stop|Direct)$/i", $rowText)) {
                    $s->extra()->stops(0);
                } elseif (preg_match("/^\s*(?<pName>{$patterns['travellerName']})\n+[ ]*(?:{$this->opt($this->t('Seat'))}|{$this->opt($this->t('Seats'))})[:\s]+(?<seats>\d[A-Z\d,\s]*[A-Z])\s*$/", $rowText, $m)) {
                    $travellers[] = $m['pName'];
                    $seats = array_merge($seats, preg_split('/\s*[,]+\s*/', $m['seats']));
                } elseif (preg_match("/^\s*(?:{$this->opt($this->t('Seat'))}|{$this->opt($this->t('Seats'))})[:\s]+(?<seats>\d[A-Z\d,\s]*[A-Z])\s*$/", $rowText, $m)) {
                    $seats = array_merge($seats, preg_split('/\s*[,]+\s*/', $m['seats']));
                } elseif (!preg_match("/{$this->opt($this->t('Seat'))}/", $rowText)) {
                    break;
                }
            }

            if (count($seats) > 0) {
                $s->extra()->seats(array_unique($seats));
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                $s->airline()->noName()->noNumber();
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['OUTBOUND'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['OUTBOUND'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
