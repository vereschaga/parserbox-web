<?php

namespace AwardWallet\Engine\getty\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "getty/it-798962995.eml, getty/it-803021130.eml";

    public $subjects = [
        'Your Tickets for the Getty',
    ];

    public $pdfNamePattern = "tickets.pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [

        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'ticketing.getty.edu') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to welcoming you to the Getty soon'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Order Confirmation'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ticketing\.getty\.edu$/', $from) > 0;
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
            ->event();

        $e->general()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order Date'))}]", null, false, "/^Order\s*Date\s*\:\ s*(\d+\-\d+\-\d{4}\s*[\d\:]+\s*A?P?M?)$/")))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order Number'))}]", null, false, "/^Order\s*Number\s*\:\s*([\d\D\-]+)$/"));

        if (preg_match_all("/{$this->t('Customer Name')}\s*\:\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n/u", $pdfText, $m)){
            $e->setTravellers(array_unique($m[1]), true);
        }

        $placeName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TICKETS'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()][1][not(contains(normalize-space(), 'TICKET'))]");

        $e->place()
            ->name($placeName);

        $placeAddress = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('DETAILS'))}]/following::tr[1]/descendant::td[1]/descendant::text()[position() > 3]"));

        if (!empty($placeAddress)){
            $e->place()
                ->address($placeAddress);
        }

        $startTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DETAILS'))}]/following::tr[1]/descendant::td[1]/text()[2]", null, false, "/^\s*\w+\s*\,\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+\s*A?P?M?)$/");

        $e->booked()
            ->start(strtotime($startTime))
            ->noEnd();

        $guestsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('QUANTITY'))}]/following::td[2]", null, false, '/^(\d+)$/');

        if ($guestsCount !== null){
            $e->booked()
                ->guests($guestsCount);
        }

        $seatsInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Seat'))}]/following::tr[1]", null, '/^(\d+)$/');

        if ($seatsInfo !== null){
            $e->booked()
                ->seats($seatsInfo);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL'))}]/following::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/", $totalPrice, $m)){
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
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

}
