<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-48035261.eml";

    public $lang = '';

    public static $dict = [
        'en' => [
            'Confirmation' => ['Confirmation', 'Confirmation Number'],
        ],
    ];

    public $text;
    private $from = [
        '.hertz.com',
    ];
    private $subject = [
        'HERTZ RESERVATION ',
    ];
    private $body = [
        'en' => ['Thank you for placing your reservation with Hertz, we hope you enjoy your rental experience with us. Please see below for your reservation details.'],
    ];

    /*private function parseRental(Email $email, string $text)
    {
        $r = $email->add()->rental();
        $r->general()->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation'))}]/following::b)[1]"));
        $r->general()->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null,
            false, "/{$this->t('Dear ')}(.+?),/"));

        // Pickup
        $location = $this->http->FindSingleNode("(//b[{$this->starts($this->t('LOCATION:'))}])[1]//following-sibling::*[1]");
        $location .= ', ' . $this->http->FindSingleNode("(//b[{$this->starts($this->t('ADDRESS:'))}])[1]/following-sibling::*[1]");
        $r->pickup()->location($location);
        $this->logger->debug("(//b[{$this->starts($this->t('ADDRESS:'))}])[1]/following-sibling::*[1]");

        // Dropoff
        $location = $this->http->FindSingleNode("(//b[{$this->starts($this->t('LOCATION:'))}])[2]/following-sibling::*[1]");
        $location .= ', ' . $this->http->FindSingleNode("(//b[{$this->starts($this->t('ADDRESS:'))}])[2]/following-sibling::*[1]");
        $r->dropoff()->location($location);

        $r->pickup()->phone($this->http->FindSingleNode("(//b[{$this->starts($this->t('PHONE:'))}])[1]/following-sibling::*[1]"));
        $r->pickup()->hours($this->http->FindSingleNode("(//b[{$this->starts($this->t('HOURS:'))}])[1]/following-sibling::*[1]"));

        $r->dropoff()->phone($this->http->FindSingleNode("(//b[{$this->starts($this->t('PHONE:'))}])[2]/following-sibling::*[1]"));
        $r->dropoff()->hours($this->http->FindSingleNode("(//b[{$this->starts($this->t('HOURS:'))}])[2]/following-sibling::*[1]"));

        // Date
        $date = $this->http->FindSingleNode("//b[{$this->starts($this->t('PICK-UP DATE/TIME:'))}]/following-sibling::*[1]");
        $r->pickup()->date2($date);
        $date = $this->http->FindSingleNode("//b[{$this->starts($this->t('DROP-OFF DATE/TIME:'))}]/following-sibling::*[1]");
        $r->dropoff()->date2($date);

        // Type Car
        $r->car()->type($this->http->FindSingleNode("//b[{$this->starts($this->t('VEHICLE TYPE:'))}]/following-sibling::*[1]"));

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('APPROXIMATE RENTAL CHARGE:'))}]/following-sibling::b");
        if (preg_match('/([\d.,\s]+)\s*([A-Z]{3})/', $price, $m)) {
            $r->price()->total($m[1]);
            $r->price()->currency($m[2]);
        }
    }*/

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
            && $this->http->XPath->query('//text()[contains(normalize-space(),"Hertz")]')->length === 0
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

        $this->parseRental($email, $text);
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

    private function parseRental(Email $email, string $text)
    {
        $r = $email->add()->rental();
        $confirmation = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation'))}]/following::b)[1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]", null, true, "/[A-Z\d]{11}$/");
        }
        $r->general()
            ->confirmation($confirmation);
        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null,
            false, "/{$this->t('Dear ')}(.+?),/"));

        $items = $this->splitter('/(Pick-up Information|Drop-off Information)/', $text);

        if (count($items) !== 2) {
            $items = $this->splitter('/(Pick-up|Drop-off)/', $text);
        }

        if (count($items) !== 2) {
            $this->logger->alert('Check bug items!');
        }

        foreach ($items as $item) {
            $item = preg_replace('/\n[\s\n]+/', "\n", $item);
            //$this->logger->debug($item);
            // ADDRESS:
            // 600 RENTAL BLVD
            // LOCATION:
            // NEW ORLEANS INT'L AP
            // PICK-UP
            // DATE/TIME:
            // WED 06 NOV 2019 02:00 PM
            // PHONE:
            // 9013455680
            // HOURS:
            // MO-SU 0600-2400 7 DAYS

            if (preg_match("#{$this->t('ADDRESS:')}\s*(.+?)\s+{$this->t('LOCATION:')}\s*([^\n]+?)\n+.*?"
                . "{$this->t('DATE/TIME:')}\s*(.+?)\s*\n+\s*"
                . "{$this->t('PHONE:')}\s*([^\n]{7,17})\s*\n+\s*(?:{$this->t('HOURS:')}\s*([^\n]{4,100})\s*\n)?#s", $item, $m)) {
                //preg_match('//s', $item, $phone);
                $location = "{$m[1]}, {$m[2]}";

                if (empty($r->getPickUpLocation())) {
                    $r->pickup()->location($location);
                    $r->pickup()->date2($m[3]);
                    $r->pickup()->phone($m[4]);
                    $r->pickup()->openingHours($m[5]);
                } else {
                    $r->dropoff()->location($location);
                    $r->dropoff()->date2($m[3]);
                    $r->dropoff()->phone($m[4]);
                    $r->dropoff()->openingHours($m[5]);
                }
            }
        }

        // Type Car
        $type = $this->http->FindSingleNode("//b[{$this->starts($this->t('VEHICLE TYPE:'))}]/following-sibling::text()");

        if (empty($type)) {
            $type = $this->http->FindSingleNode("//b[{$this->starts($this->t('VEHICLE TYPE:'))}]/following-sibling::*[1]");
        }

        if (empty($type)) {
            if (preg_match("/VEHICLE[ ]TYPE:\s*\n(.+)\n/u", $text, $m)) {
                $type = $m[1];
            }
        }
        $r->car()->type($type);

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('APPROXIMATE RENTAL CHARGE:'))}]/following-sibling::b");

        if (preg_match('/([\d.,\s]+)\s*([A-Z]{3})/', $price, $m)) {
            $r->price()->total($m[1]);
            $r->price()->currency($m[2]);
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

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function htmlToText($string, $view = false)
    {
        $text = preg_replace('/<[^>]+>/', "\n", html_entity_decode($string));

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

    private function contains($field, $node = ''): string
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
