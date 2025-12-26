<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckInForYourFlight extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-696053091.eml, frontierairlines/it-699894200.eml, frontierairlines/it-700429499.eml, frontierairlines/it-760983389.eml";

    public $lang = '';
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectFrom = "flights@emails.flyfrontier.com";
    private $detectSubject = [
        // en
        "It's time to check in for your flight to",
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flyfrontier\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".flyfrontier.com/") or contains(@href,"emails.flyfrontier.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Frontier Airlines. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
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

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        return $this->http->XPath->query("//*[ count(*[normalize-space() or .//img])>1 and count(*[normalize-space() or .//img])<4 and *[1][not(.//img)] and *[2]/descendant::img and *[3][not(.//img)] and *[{$xpathTime}] ]");
    }

    private function parseEmailHtml(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your confirmation code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', check in now for your flight'))}]",
            null, true, "/^\s*(.+?)\s*{$this->opt($this->t(', check in now for your flight'))}/");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller, false);
        }

        // Segments

        foreach ($this->findSegments() as $root) {
            $s = $f->addSegment();
            $flightText = implode("\n", $this->http->FindNodes("following::text()[normalize-space()][position()<3]", $root));

            // Airline
            $flightNumber = null;
            $this->logger->debug($flightText);

            if (preg_match("/^[ ]*{$this->opt($this->t('Flight Number'))}[:\s]*(\d+)\s*(?:\||$)/im", $flightText, $m)
                || preg_match("/^[ ]*{$this->opt($this->t('Flight'))}\s+(\d+)\s*(?:\s+{$this->opt($this->t('is operated by'))}|\||$)/im", $flightText, $m)
            ) {
                // Flight Number: 1891    |    Flight 6610 is operated by Volaris Mexico as Flight 1710
                $flightNumber = $m[1];
            }
            $s->airline()->number($flightNumber);

            if ($flightNumber) {
                $s->airline()->name('Frontier Airlines');
            }

            if (preg_match("/{$this->opt($this->t('is operated by'))}\s+(?<name>\S.*?\S)\s+{$this->opt($this->t('as'))}\s+{$this->opt($this->t('Flight'))}\s+(?<number>\d+)\s*(?:\||$)/im", $flightText, $m)) {
                $s->airline()->carrierName($m['name'])->carrierNumber($m['number']);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][2]", $root, true, "/.+\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][2]", $root, true, "/(.+)\s*\([A-Z]{3}\)\s*$/"))
            ;

            $date = preg_match("/{$this->opt($this->t('Flight Date:'))}\s*(.+?)\s*(?:\||$)/im", $flightText, $m) ? $m[1] : null;

            if (empty($date)) {
                $date = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->starts($this->t('Trip Time:'))}][1]/preceding::text()[normalize-space()][1]", $root);
            }
            $date = strtotime($date);
            // $date = strtotime($this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->starts($this->t('Trip Time:'))}][1]/preceding::text()[normalize-space()][1]",
            $time = $this->http->FindSingleNode("*[1]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][2]", $root, true, "/.+\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][2]", $root, true, "/(.+)\s*\([A-Z]{3}\)\s*$/"))
            ;

            $time = $this->http->FindSingleNode("*[3]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            $duration = preg_match("/{$this->opt($this->t('Flight Time:'))}\s*(.+?)\s*(?:\||$)/im", $flightText, $m) ? $m[1] : null;

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 5][{$this->starts($this->t('Trip Time:'))}][1]",
                    $root, true, "/^\s*{$this->opt($this->t('Trip Time:'))}\s*(.+)\s*$/");
            }

            $s->extra()
                ->duration($duration);
        }
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
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
