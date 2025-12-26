<?php

namespace AwardWallet\Engine\nokair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "nokair/it-1843110.eml, nokair/it-2973292.eml, nokair/it-3031258.eml, nokair/it-4548745.eml, nokair/it-4560992.eml, nokair/it-4645212.eml, nokair/it-6056499.eml";

    public $reBody = [
        'en' => ['Booking Reference', 'Flight No.'],
    ];
    public $reSubject = [
        'en' => ['Booking Confirmation', 'Schedule Change'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Booking' . ucfirst($this->lang));

        $f = $this->parseEmail($email);

        $tot = ['Total' => '', 'Currency' => ''];
        $pdfs = $parser->searchAttachmentByName('receipt\.pdf');

        if (count($pdfs) === 1) {
            if (!empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0])))) {
                $tot = $this->getTotalCurrency($this->parsePrice($textPdf));
            }
        }

        if ($tot['Total'] === '' && $tot['Currency'] === '') {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Payment'))}]/ancestor::td[1]/following-sibling::td[1]"));
        }

        if ($tot['Total'] !== '') {
            $f->price()->total($tot['Total'])->currency($tot['Currency'], true);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".nokair.com/") or contains(@href,"www.nokair.com")] | //*[contains(normalize-space(),"Nok Airlines Public Company Limited. All Rights Reserved") or contains(normalize-space(),"Your Nok Air Team") or contains(.,"www.nokair.com")]')->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Nok Air') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nokair.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish(?string $date): ?string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public static function parsePrice(?string $text): ?string
    {
        $amount = self::re("/(?:\n|\/)[ ]*Total[ ]+Amount[ ]+received[: ]+(.*\d.*)/i", $text);
        $currency = self::re("/\n[ ]{20,}(?:.+\/[ ]*)([A-Z]{3})(?:\n+.+){0,15}?\n(?:.+\/)?[ ]*(?i)Total[ ]+Amount[ ]+received[: ]+/", $text);

        return implode(' ', array_filter([$amount, $currency]));
    }

    private function parseEmail(Email $email): ?Flight
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("((//text()[{$this->eq($this->t('Booking Reference:'))}]/ancestor::tr[1]/following-sibling::tr[1])[1]//text()[normalize-space(.)!=''])[last()]",
                null, false, "#^([\w-]+)$#"),
                trim($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Booking Reference:'))}])[1]"), " :"),
                true)
            ->confirmation($this->http->FindSingleNode("((//text()[{$this->eq($this->t('Booking Reference:'))}]/ancestor::tr[1]/following-sibling::tr[1])[1]//text()[normalize-space(.)!=''])[last()-1][not(contains(.,' '))] | //text()[{$this->starts($this->t('YOUR BOOKING NUMBER'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^([\w-]+)$#"),
                'Booking Number')
            ->travellers($this->http->FindNodes("(//text()[{$this->contains($this->t('Name of Passenger(s)'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1])[1]//text()[normalize-space(.)!='']"))
            ->date(strtotime($this->http->FindSingleNode("((//text()[{$this->eq($this->t('Booking Reference:'))}]/ancestor::tr[1]/following-sibling::tr[1])[1]//text()[normalize-space(.)!=''])[1]")));

        if (empty($status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation has been'))}]",
            null, false, "/{$this->opt($this->t('reservation has been'))}\s+(\w+)/"))
        ) {
            $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nok Air flight is schedule changed'))}]",
                null, false, "#(schedule changed)#");
        }

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $xpath = "//tr[not(.//tr) and ({$this->contains($this->t('Date'))}) and ({$this->contains($this->t('Flight No.'))}) and ({$this->contains($this->t('Departing'))})]/following-sibling::tr[count(./td) > 3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (empty($this->http->FindSingleNode("td[2]", $root, false, "#^(\w+?\s*\d+)#"))) {
                continue;
            } //exclude coach, catamaran etc no flights

            $s = $f->addSegment();

            $node = $this->http->FindSingleNode("td[2]", $root);

            if (preg_match("#^([A-Z\d]{2})\s*(\d+)\s*(.*)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3])) {
                    $s->extra()->cabin($m[3]);
                }
            }
            $date = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("td[1]/descendant::text()[normalize-space(.)!=''][last()]",
                $root)));
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("td[3]/descendant::text()[normalize-space(.)!=''][1]", $root))
                ->date(strtotime($this->correctTimeString($this->http->FindSingleNode("td[3]/descendant::text()[normalize-space(.)!=''][2][contains(.,':')]",
                    $root, false, "#(\d+:\d+(?:\s*[ap]m)?)#i")), $date));
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("td[4]/descendant::text()[normalize-space(.)!=''][1]", $root))
                ->date(strtotime($this->correctTimeString($this->http->FindSingleNode("td[4]/descendant::text()[normalize-space(.)!=''][2][contains(.,':')]",
                    $root, false, "#(\d+:\d+(?:\s*[ap]m)?)#i")), $date));
        }

        return $f;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function correctTimeString(?string $time): ?string
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }
}
