<?php

namespace AwardWallet\Engine\preservationhall\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "preservationhall/it-809646090.eml, preservationhall/it-819717354.eml, preservationhall/it-820058707.eml";
    public $subjects = [
        'Your Preservation Hall Ticket Purchase',
    ];

    public $pdfNamePattern = "ORDER\-.+\-Tickets.pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [

        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'preservationhall.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to having you as our guest(s) here at Preservation Hall.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Order Detail'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]preservationhall\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $pdfText = "";

        foreach ($pdfs as $pdf) {
            $pdfText .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $this->Event($email, $pdfText);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email, $pdfText)
    {
        $e = $email->add()->event();

        $e->type()
            ->show();

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order Detail'))}]/following::text()[normalize-space()][1]", null, false, "/^ORDER\-[\d\D]+$/"));

        $e->addTraveller(preg_replace("/^(?:Mrs.|Mr.|Ms.)/", "", $this->http->FindSingleNode("//text()[{$this->eq($this->t('Billing and Delivery Information'))}]/following::text()[normalize-space()][1]", null, false, "/^[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/")), true);

        $eventInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order Detail'))}]/following::table[1]/descendant::tr[normalize-space()][not({$this->contains($this->t('Details'))})][1]/descendant::td[2]");

        if (preg_match("/^\d+\s*x\s*\d+\-\d*\-\d{4}\s*(?<date>\w+\,\s*\w+\s*[\d\D]+\,\s*\d{4})\s*\-\s*(?<time>\d+\:\d+\s*[AP]?M?)\s*(?<name>{$this->t('Show')}\s*\-\s*.+)$/", $eventInfo, $m)){
            $e->place()
                ->name($m['name']);

            $e->booked()
                ->start(strtotime($m['date'] . ' ' . $m['time']))
                ->noEnd()
                ->guests(array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Order Detail'))}]/following::table[1]/descendant::tr[normalize-space()][count(td) >= 5]/descendant::td[2]", null, '/^(\d+)\s*x/')));
        }

        if (preg_match("/(.+{$this->t('Address')}\s*\:\s*.+\n*\s*.+\n*\s*.+)/u", $pdfText, $m)) {
            $e->place()
                ->address(preg_replace("/(?:{$this->t('Address')}:|\n)/",' ', $this->splitCols($m[0])[0]) . ', ' . $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order Detail'))}]/following::table[1]/descendant::tr[normalize-space()][not({$this->contains($this->t('Details'))})][1]/descendant::td[3]"));
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Total:'))}]/following::td[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,\']*)$/", $totalPrice, $m)
            || preg_match("/^(?<total>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $totalPrice, $m)){
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('Item Subtotal:'))}]/following::td[normalize-space()][1]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $m['currency']))
                ->currency($m['currency']);

            $fees = $this->http->XPath->query("//tr[preceding-sibling::tr[{$this->starts($this->t("Item Subtotal:"))}] and following-sibling::tr[{$this->starts($this->t("Order Total:"))}]]");

            foreach ($fees as $fee){
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $fee, false, "/^(.+)\:$/");
                $feeValue = $this->http->FindSingleNode("./descendant::td[2]", $fee, false,"/^\D{1,3}\s*(\d[\d\.\,\']*)$/");

                if ($feeName !== null && $feeValue !== null) {
                    $e->price()
                        ->fee($feeName, PriceHelper::parse($feeValue, $m['currency']));
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "normalize-space(.)=\"{$s}\"";
            }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            // 11-30-2024 12:22 AM
            "/^(\d+)\-(\d+)\-(\d{4})\s*([\d\:]+\s*A?P?M?)$/",
        ];
        $out = [
            "$2.$1.$3 $4",
        ];

        $date = preg_replace($in, $out, $str);

        return strtotime($date);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
