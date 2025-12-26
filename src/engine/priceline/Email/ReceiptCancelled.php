<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptCancelled extends \TAccountChecker
{
    public $mailFiles = "priceline/it-653884152.eml, priceline/it-653917533.eml, priceline/it-653922466.eml";

    public $detectFrom = "info@travel.priceline.com";
    public $detectSubject = [
        // en
        'Your flight has been cancelled (Trip #:',
        'Your rental car has been cancelled (Trip #:',
        'Your hotel booking has been cancelled (Trip #:',
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Your receipt from Priceline'                        => 'Your receipt from Priceline',
            'This email confirms the cancellation of your trip.' => 'This email confirms the cancellation of your trip.',
            'Payment Summary'                                    => 'Payment Summary',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]priceline\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Priceline') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['priceline.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['priceline.com LLC'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your receipt from Priceline']) && !empty($dict['This email confirms the cancellation of your trip.']) && !empty($dict['Payment Summary'])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your receipt from Priceline'])}]/following::*[{$this->starts($dict['This email confirms the cancellation of your trip.'])}]/following::*[{$this->eq($dict['Payment Summary'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Priceline Trip Number:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d[\d\-]{5,})\s*$/u"), 'Priceline Trip Number');

        $cNodes = $this->http->XPath->query("//img/@src[contains(., '/cars.png')]/ancestor::div[2]");

        foreach ($cNodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->cancelled()
                ->status('Cancelled');

            $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("/\n\s*Confirmation #:\s*([\dA-Z\-]{5,})\s*(?:\n|$)/", $text, $m)) {
                $r->general()
                    ->confirmation($m[1]);
            }
            $date = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Purchase Date'))}]/following::text()[normalize-space()][1]"));

            if (!empty($date)) {
                $date = strtotime('- 2 month', $date);
            }

            if (!empty($date) && preg_match("/^.+\n\s*(.+?) - (.+?) • Pick-up: (.+?)\s*(?:\n|$)/", $text, $m)) {
                $r->pickup()
                    ->date(strtotime($this->normalizeTime($m[3]), EmailDateHelper::parseDateRelative($m[1], $date)));

                if (strcmp($m[1], $m[2]) !== 0) {
                    $r->dropoff()
                        ->date(EmailDateHelper::parseDateRelative($m[2], $date));
                }
            }
        }

        $hNodes = $this->http->XPath->query("//img/@src[contains(., '/stay_blue.png')]/ancestor::div[2]");

        foreach ($hNodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->cancelled()
                ->status('Cancelled');

            $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("/\n\s*Confirmation #:\s*([\dA-Z\-]{5,})\s*(?:\n|$)/", $text, $m)) {
                $h->general()
                    ->confirmation($m[1]);
            }

            if (preg_match_all("/^\s*Room \d+:\s*(.+)\s*$/m", $text, $m)) {
                $h->general()
                    ->travellers($m[1], true);
            }

            $date = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Purchase Date'))}]/following::text()[normalize-space()][1]"));

            if (!empty($date)) {
                $date = strtotime('- 1 month', $date);
            }

            if (preg_match("/^\s*(.+)\n/", $text, $m)) {
                $h->hotel()
                    ->name($m[1]);
            }

            if (!empty($date) && preg_match("/^.+\n\s*(.+?) – (.+?)\s*(?:\n|$)/", $text, $m)) {
                $h->booked()
                    ->checkIn(EmailDateHelper::parseDateRelative($m[1], $date))
                    ->checkOut(EmailDateHelper::parseDateRelative($m[2], $date));
            }
        }

        $fNodes = $this->http->XPath->query("//img/@src[contains(., '/flights_blue.png')]/ancestor::div[2]");

        if ($fNodes->length > 0) {
            $f = $email->add()->flight();

            $confs = array_unique(array_filter(preg_replace('/^\s*X{5,7}\s*$/', '',
                $this->http->FindNodes("//node()[{$this->eq($this->t('Airline Confirmation #:'))}]/following::text()[normalize-space()][1]"))));

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }

            $f->general()
                ->cancelled()
                ->status('Cancelled');
        }

        return true;
    }

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51

        return $string;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
