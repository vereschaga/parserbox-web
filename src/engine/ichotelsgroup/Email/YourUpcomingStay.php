<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourUpcomingStay extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-80325594.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'headerVariants'  => ['Your upcoming stay', 'YOUR UPCOMING STAY'],
            'Front Desk:'     => ['Front Desk:', 'Hotel Front Desk:'],
            'Check-in Date:'  => ['Check-in Date:', 'Check In:'],
            'Check-out Date:' => ['Check-out Date:', 'Check Out:'],
        ],
    ];

    private $subjects = [
        'en' => ["it's time to check-in", 'please start your check-in now'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ihg.com/") or contains(@href,"www.ihg.com") or contains(@href,"click.mc.ihg.com")]')->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//*[{$this->contains($this->t('headerVariants'))}]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('Front Desk:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mc.ihg.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-\'’\/[:alpha:] ]*[[:alpha:]]', // Chang Kyung    |    Chao-cheng    |    Philip/iii
        ];

        $h->general()
            ->noConfirmation()
            ->traveller($this->re("/(?:^|[\]:][ ]*)({$patterns['travellerName']})\s*,\s*(?:it's time to check[-\s]*in|please start your check[-\s]*in now)/iu", $parser->getSubject()));

        $hotelText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('headerVariants'))}]/following::text()[normalize-space()][1]/ancestor::*[ descendant::text()[{$this->starts($this->t('Check-out Date:'))}] ][1]"));

        if (preg_match("/^\s*(?:{$this->opt($this->t('headerVariants'))}\n+)?[ ]*(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Front Desk:'))}/", $hotelText, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', $m['address']));
        }

        $h->hotel()->phone($this->re("/{$this->opt($this->t('Front Desk:'))}\s*({$patterns['phone']})[ ]*$/m", $hotelText));

        $h->booked()->checkIn2($this->re("/{$this->opt($this->t('Check-in Date:'))}\s*(.{3,}?)[ ]*$/m", $hotelText))->checkOut2($this->re("/{$this->opt($this->t('Check-out Date:'))}\s*(.{3,}?)[ ]*$/m", $hotelText));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
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
