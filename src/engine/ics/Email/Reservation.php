<?php

namespace AwardWallet\Engine\ics\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "ics/it-422860514.eml, ics/it-662824202.eml, ics/it-695470871.eml";
    public $subjects = [
        'REVISED ICS Reservation',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = '';

    public static $dictionary = [
        "en" => [
            // Html
            'For Car Type:'         => 'For Car Type:',
            'Passenger Information' => 'Passenger Information',
            'Confirmation'          => ['Confirmation', 'ICS Quote'],
            // 'Flight Number:' => '',

            // Pdf
            'POINT TO POINT RESERVATION' => ['POINT TO POINT RESERVATION', 'POINT-TO-POINT RESERVATION'],
            'Routing Information'        => 'Routing Information',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bookalimo.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(.,'BOOK-A-LIMO') or contains(.,'bookalimo.com')]")->length > 0
            && $this->assignLangHtml()
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $text = str_replace("­", " ", $text);

            if (stripos($text, 'www.bookalimo.com') !== false && $this->assignLangPdf($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bookalimo.com$/', $from) > 0;
    }

    public function ParseTransfer(Email $email): void
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $t = $email->add()->transfer();

        $t->general()->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]", null, true, "/^{$this->opt($this->t('Confirmation'))}\s*ICS-([\dQ]+)$/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Passenger Information'))}]/following::text()[normalize-space()='Name:']/following::text()[normalize-space()][1]");

        if ($traveller !== 'Unknown') {
            $t->general()
                ->traveller($traveller);
        }
        $s = $t->addSegment();

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date & Time Information')]/following::text()[normalize-space()='Date:']/following::text()[normalize-space()][1]");
        $time = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date & Time Information')]/following::text()[normalize-space()='Time:']/following::text()[normalize-space()][1]", null, true, "/^([\d\:]+\s*A?P?M?)\s*[*]*$/");

        $pickUpText = $this->htmlToText( $this->http->FindHTMLByXpath("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Pick Up'), "translate(.,':','')")}] ]/*[normalize-space()][2]") );
        $pickUpText = preg_replace("/^((?:.+\n+)+)[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-[\s\S]*$/", '$1', $pickUpText);

        $s->departure()
            ->name(preg_replace(['/(?:[ ]*\n+[ ]*)+/', '/(?:\s*,\s*)+/'], ', ', trim($pickUpText)))
            ->date(strtotime($date . ', ' . $time));

        $dropOffText = $this->htmlToText( $this->http->FindHTMLByXpath("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Drop Off'), "translate(.,':','')")}] ]/*[normalize-space()][2]") );
        $dropOffText = preg_replace("/^(.{3,}?)\s*{$this->opt($this->t('Flight Number:'))}.*$/s", '$1', $dropOffText);

        $s->arrival()
            ->name(preg_replace(['/(?:[ ]*\n+[ ]*)+/', '/(?:\s*,\s*)+/'], ', ', trim($dropOffText)))
            ->noDate();

        $s->extra()->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('For Car Type:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.{2,}?)[*\s]*$/"));

        if ($this->http->XPath->query("//text()[normalize-space()='Discount:']")->length > 0) {
            $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Due*:')]/following::text()[normalize-space()][1]");
        } else {
            $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Grand Total:')]/following::text()[normalize-space()][1]");
        }

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s*$/", $price, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }
    }

    public function ParseTransferPDF(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__ . '()');
        $t = $email->add()->transfer();

        $t->general()->confirmation($this->re("/^\s*{$this->opt($this->t('Confirmation'))}\s*ICS-?\s*([A-Z\d]+)\n/", $text));

        $traveller = $this->re("/^(?:.* {3,})?Name\:\s*(.+)\n\s*(?:.* {3,})?Phone\s*\:/m", $text);

        if ($traveller !== 'Unknown') {
            $t->general()
                ->traveller($traveller);
        }

        $s = $t->addSegment();

        if (preg_match("/^.* {3,}Date\:\s*(?<date>.+\d{4})(?: {3,}.*)?\n\s*.* {3,}Time\:\s*(?<time>[\d\:]+ *[AP]M)\*?(?: {3,}.*)?\n/mu", $text, $m)) {
            $s->departure()
                ->date(strtotime($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("/For Car Type[ ]*[:]+[ ]*.*\n+[ ]*Pick Up\s+(?<dep>(?:.+\n){2,}?)[ ]*Drop Off\s+(?<arr>(?:.+\n){2,}?)\s+(?:(?:Flight|Train) Number[ ]*:|Payment Information)/", $text, $m) // page 2
            || preg_match("/Routing Information .+\n+[ ]{0,5}Pick Up[ ]*(?<dep>(?:.+\n){2,}?)[ ]*Drop Off[ ]*(?<arr>.+\n(?:[ ]{8}.+\n+){2,}?)([ ]{20}|\n\n)/", $text, $m) // page 1
        ) {
            $m = preg_replace("/^(.{30,}?)[ ]{3}.*$/m", '$1', $m); // remove right column

            if (preg_match("/^([\s\S]+?)\n+[ ]{0,8}(Stop[\s\S]+?)\s*$/", $m['dep'], $m2)) {
                $m['dep'] = $m2[1];
                $stopsText = $m2[2];
            } else {
                $stopsText = null;
            }

            $m = preg_replace("/\n\s*(?:Flight|Train) Number[ ]*:[\S\s]*/", '', $m);
            $m['arr'] = preg_replace('/\n+[ ]*• .+$/s', '', $m['arr']);
            $m = preg_replace('/([ ]*\n+[ ]*)+/', ', ', array_map('trim', $m));

            if ($stopsText !== null && ($m['dep'] === $m['arr'] || strlen($stopsText) > 300)) {
                $this->logger->debug('Found itinerary with stops!');
                $email->removeItinerary($t);

                return;
            }

            $s->departure()->name($m['dep']);
            $s->arrival()->name($m['arr'])->noDate();
        }

        $s->extra()->model($this->re("/ For Car Type[ ]*[:]+[ ]*(.*?)[ *]*\n/", $text));

        if (stripos($text, 'Discount:') !== false) {
            $price = $this->re("/Total Due\*\:\s*([A-Z]{3}\s*[\d\.\,\']+)\n/", $text);
        } else {
            $price = $this->re("/Grand Total\:\s*([A-Z]{3}\s*[\d\.\,\']+)\n/", $text);
        }

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s*$/", $price, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLangHtml()) {
            $this->ParseTransfer($email);
        } else {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $text = str_replace("­", " ", $text);

                $this->assignLangPdf($text);
                $this->ParseTransferPDF($email, $text);
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function assignLangHtml(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['For Car Type:']) || empty($phrases['Passenger Information'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['For Car Type:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Passenger Information'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLangPdf(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['POINT TO POINT RESERVATION']) || empty($phrases['Routing Information'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['POINT TO POINT RESERVATION']) !== false
                && $this->strposArray($text, $phrases['Routing Information']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
