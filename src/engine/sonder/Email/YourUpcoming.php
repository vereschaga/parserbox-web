<?php

namespace AwardWallet\Engine\sonder\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourUpcoming extends \TAccountChecker
{
    public $mailFiles = "sonder/it-48949911.eml, sonder/it-81058657.eml";

    public $lang = '';

    public static $dict = [
        'en' => [
        ],
    ];
    private $from = [
        'tripactions.com',
        'stasher.com',
    ];
    private $subject = [
        'Your upcoming stay in',
        'Information for your upcoming stay',
    ];
    private $body = [
        'en' => ['Thanks for completing your profile for your stay in', 'Your Sonder stay in'],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query("//text()[{$this->contains($this->from)}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }
        $text = $this->htmlToText(!empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody(),
            true);
        $text = substr($text, 0, 10000);
        $this->parseHotel($email, $text);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseHotel(Email $email, string $text)
    {
        $h = $email->add()->hotel();
        $h->general()->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your confirmation code is'))}]/following-sibling::*",
            null, false, '/\s*([A-Z\d\-]{10,16})$/'))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, false, '/\s+(.+?),$/'))
            ;
        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('profile for your stay in'))}]", null, false, "/{$this->t('profile for your stay in')}\s+(.+?)\./");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your Sonder stay in'))}]", null, false, "/{$this->t('Your Sonder stay in')}\s+(.+?)\s+is booked/u");
        }

        $address = $this->http->FindSingleNode("//img[{$this->contains($this->t('Neighborhood Map'), '@alt')}]/ancestor::tr[1]/following-sibling::tr[1]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='ADDRESS']/following::a[1]");
        }

        $h->hotel()
            ->name($name)
            ->address($address);

        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK IN'))}]/ancestor::td[1]", null, false, "/{$this->t('CHECK IN')}\s*(.+)/");
        $h->booked()->checkIn2($date);
        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK OUT'))}]/ancestor::td[1]", null, false, "/{$this->t('CHECK OUT')}\s*(.+)/");
        $h->booked()->checkOut2($date);

        $cancellationPolicy = $this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Cancellation policy'))}]]/following-sibling::tr[1]");
        $h->setCancellation($cancellationPolicy, false, true);
        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/if canceled within (\d+ hours) of booking/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->body as $lang => $phrase) {
            if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug($str);
        $in = [
            // Tue, 19 Nov 2019, 06: 40
            '/(\w+, \d+ \w+ \d{4}), (\d+):\s*(\d+)/s',
        ];
        $out = [
            "$1, $2:$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function htmlToText($string, $view = false)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        // Multiple spaces and newlines are replaced with a single space.
        $string = trim(preg_replace('/\s\s+/', ' ', $string));
        $text = preg_replace('/<[^>]+>/', "\n", $string);

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
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

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
