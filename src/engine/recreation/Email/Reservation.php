<?php

namespace AwardWallet\Engine\recreation\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "recreation/it-315421916.eml, recreation/it-318435439.eml, recreation/it-556530096.eml, recreation/it-563159879.eml, recreation/it-61439043.eml, recreation/it-61440896.eml, recreation/it-71762578.eml, recreation/it-72287973.eml";

    private $lang = '';
    private $reFrom = [
        '@recreation.gov',
    ];
    private $reProvider = ['Recreation.gov'];
    private $reSubject = [
        'Recreation.gov Reservation Confirmation',
        'Recreation.gov Reservation Reminder',
    ];
    private $detectLang = [
        'en' => [
            'Adventure awaits! This email confirms your reservation',
            'We hope the anticipation is building as you prepare for your trip',
            'We hope you enjoy your experience and bring home an amazing story.',
            'You recently updated the below reservation',
            'Thank you for your order',
            'You recently requested',
            'Your reservation is confirmed for',
            'Cancellation Details',
            'Reservation Information',
        ],
    ];

    private static $dictionary = [
        'en' => [
            'Entry:'          => ['Entry:', 'Entry Date', 'Entry Window'],
            'Exit:'           => ['Exit:', 'Exit Date'],
            'Start Time:'     => ['Start Time:', 'Tour Start:'],
            'End Time:'       => ['End Time:', 'Tour End:'],
            '# of Occupants:' => ['# of Occupants:', 'Group Size:'],
            "Check-In:"       => ["Check-In:", "Check In:"],
            "Check-Out:"      => ["Check-Out:", "Check Out:"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->obtainTravelAgency();

        if ($conf = $this->http->FindSingleNode("//strong[{$this->contains($this->t('Order Number:'), 'text()')}]/following-sibling::text()")) {
            $email->ota()->confirmation($conf, $this->t('Order Number:'));
        }

        if ($conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order #:'))}]", null, false,
            '/:\s*(.+)/')) {
            $email->ota()->confirmation($conf, $this->t('Order #:'));
        }

        if ($conf = $this->http->FindSingleNode("//strong[{$this->contains($this->t('Reservation ID:'), 'text()')}]/following-sibling::text()")) {
            $email->ota()->confirmation($conf, $this->t('Reservation ID:'));
        }

        if ($conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Number:'))}]/following::text()[normalize-space()][string-length() > 3][1]", null, true, "/^([\d\-]+)$/u")) {
            $email->ota()->confirmation($conf, $this->t('Reservation Number'));
        }

        if ($conf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'your upcoming reservation')]", null, true, "/{$this->opt($this->t('your upcoming reservation'))}\s*([\d\-]+)/u")) {
            $email->ota()->confirmation($conf);
        }

        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Start Time:")) . "]")) {
            $it = $email->add()->event();
        } elseif ($this->http->FindSingleNode("//text()[{$this->eq($this->t("Entry:"))}]")) {
            $it = $email->add()->event();
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Check-In:'))}]")->length == 0) {
            $it = $email->add()->event();
        } elseif ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Check-In:")) . "]")) {
            $it = $email->add()->hotel();
        } else {
            return false;
        }

        $it->general()
            ->noConfirmation();

        if ($traveller = $this->http->FindSingleNode("//strong[{$this->starts($this->t('Hi '), 'text()')}]", null, false, "/{$this->opt($this->t('Hi '))}\s*(.+?),/")) {
            $it->general()->traveller($traveller);
        }

        if ($date = $this->http->FindSingleNode("//strong[{$this->contains($this->t('Reservation Created:'), 'text()')}]/following-sibling::text()")) {
            $it->general()->date2(preg_replace(['/ at /', '/\([A-Z]+\)/'], [', ', ''], $date));
        }

        if ($guests = $this->http->FindSingleNode("//strong[{$this->contains($this->t('# of Occupants:'), 'text()')}]/following-sibling::text()", null, false, '/^\s*(\d+)\s*$/')) {
            $it->booked()->guests($guests);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Start Time:'))} or {$this->contains($this->t('Check-In:'))}]/ancestor::tr[1]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Entry:'))} or {$this->contains($this->t('Check-In:'))}]/ancestor::tr[1]");
        }

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Order Information')]/following::text()[starts-with(normalize-space(), 'Reservation Details')][1]/ancestor::tr[1]");
        }

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Cancellation Details')]/following::text()[starts-with(normalize-space(), 'Check In:')][1]/ancestor::tr[1]");
        }

        $this->logger->warning($nodes->length);

        foreach ($nodes as $n) {
            if ($td = $this->http->FindNodes('./td[1]//text()[normalize-space()]', $n)) {
                $td = join("\n", $td);
                /*
                    Wed, Jul 8, 2020
                    Check-In: 2:00 PM
                    Fri, Jul 10, 2020
                    Check-Out: 12:00 PM

                    August 22, 2020
                    Start Time: 5:00 AM
                    End Time: 11:00 PM
                 */

                $this->logger->debug($td);

                if ($this->http->XPath->query("//text()[{$this->eq($this->t('Cancellation Details'))}]")->length > 0) {
                    $it->general()
                        ->cancelled();
                }

                if (preg_match(
                    "/^(.+?)\s*\n\s*(?:{$this->opt($this->t('Start Time:'))}|{$this->opt($this->t('Check-In:'))})\s*(\d+:\d+(?:\s*[AP]M)?)\s*\n\s*"
                    . "(.+?)\s*\n\s*(?:{$this->opt($this->t('End Time:'))}|{$this->opt($this->t('Check-Out:'))})\s*(\d+:\d+(?:\s*[AP]M)?)/is", $td, $m)) {
                    if ($it->getType() == 'hotel') {
                        $it->booked()->checkIn2(str_replace("\n", "", "{$m[1]} {$m[2]}"));
                        $it->booked()->checkOut2(str_replace("\n", "", "{$m[3]} {$m[4]}"));
                    } else {
                        $it->booked()->start2("{$m[1]} {$m[2]}");
                        $it->booked()->end2("{$m[3]} {$m[4]}");
                    }
                } elseif (preg_match("/{$this->opt($this->t('Date:'))}\s*(.+)\s*{$this->opt($this->t('Start Time:'))}\s*(.+)\s*{$this->opt($this->t('End Time:'))}\s*([\d\:]+\s*A?P?M)/s", $td, $m)
                    || preg_match("/^(.+?)\s*\n\s*(?:{$this->opt($this->t('Start Time:'))}|{$this->opt($this->t('Check-In:'))})\s*(\d+:\d+(?:\s*[AP]M)?)\s*\n\s*"
                        . "(?:{$this->opt($this->t('End Time:'))}|{$this->opt($this->t('Check-Out:'))})\s*(\d+:\d+(?:\s*[AP]M)?)/is", $td, $m)) {
                    if ($it->getType() == 'hotel') {
                        $it->booked()->checkIn2("{$m[1]} {$m[2]}");
                        $it->booked()->checkOut2("{$m[1]} {$m[3]}");
                    } else {
                        $it->booked()->start2("{$m[1]} {$m[2]}");

                        if (trim($m[2]) === "1:00 AM" && trim($m[3]) === "12:59 AM") {
                            $it->booked()->noEnd();
                        } else {
                            $it->booked()->end2("{$m[1]} {$m[3]}");
                        }
                    }
                } elseif (preg_match("/(.+)\s*{$this->opt($this->t('Start Time:'))}\s*([\d\:]+\s*A?P?M)\s*{$this->opt($this->t('End Time:'))}\s*$/s", $td, $m)) {
                    if ($it->getType() == 'hotel') {
                        $it->booked()->checkIn2("{$m[1]} {$m[2]}");
                        $it->booked()->checkOut2("{$m[1]} {$m[3]}");
                    } else {
                        $it->booked()->start2("{$m[1]} {$m[2]}");
                        $it->booked()->noEnd();
                    }
                } elseif (preg_match("/^\s*{$this->opt($this->t('Check-In:'))}\s*(.*)\s+at\s+([\d\:]+\s*a?p?m)\s*\([A-Z]{3}\)\s*{$this->opt($this->t('Check-Out:'))}\s*(.*)\s+at\s+([\d\:]+\s*a?p?m)\s*\([A-Z]{3}\)$/si", $td, $m)) {
                    if ($it->getType() == 'hotel') {
                        $it->booked()->checkIn(strtotime($m[1] . ' ' . $m[2]));
                        $it->booked()->checkOut(strtotime($m[3] . ' ' . $m[4]));
                    } else {
                        $it->booked()->start2("{$m[1]} {$m[2]}");
                        $it->booked()->noEnd();
                    }
                } elseif (preg_match("/(\w+)\s+(\d+)\s+(\d+)\n{$this->opt($this->t('Entry:'))}\n\D+\n(\w+)\s+(\d+)\s+(\d+)\n{$this->opt($this->t('Exit:'))}\n\D+/is", $td, $m)
                    || preg_match("/(\w+)\s+(\d+)\s+(\d+)\n{$this->opt($this->t('Entry:'))}\n(\w+)\s+(\d+)\s+(\d+)\n{$this->opt($this->t('Exit:'))}/is", $td, $m)
                    || preg_match("/{$this->opt($this->t('Entry:'))}\s+(\w+)\s+(\d+)\s+(\d+)\n{$this->opt($this->t('Exit:'))}\s+(\w+)\s+(\d+)\s+(\d+)/i", $td, $m)
                    || preg_match("/(\w+)\s+(\d+)\s+(\d+)\n(\w+)\s+(\d+)\s+(\d+)/is", $td, $m)
                    || preg_match("/(\w+)\s+(\d+)\s+(\d+)\n.+\n(\w+)\s+(\d+)\s+(\d+)\n.+/is", $td, $m)
                ) {
                    if ($it->getType() == 'hotel') {
                    } else {
                        $it->booked()->start2("{$m[2]} {$m[1]} {$m[3]}");
                        $it->booked()->end2("{$m[5]} {$m[4]} {$m[6]}");
                    }
                } elseif (preg_match("/^\s*(?<inDate>\w+\s*\d+\,\s*\d{4})\s*(?<outDate>\w+\s*\d+\,\s*\d{4})\s*{$this->opt($this->t('Entry:'))}\s*(?<inTime>[\d\:]+\s*A?P?M)[\s\-]+(?<outTime>[\d\:]+\s*A?P?M)$/", $td, $m)) {
                    //it-61439043.eml
                    if ($it->getType() == 'hotel') {
                    } else {
                        $it->booked()->start(strtotime($m['inDate'] . ' ' . $m['inTime']));
                        $it->booked()->end(strtotime($m['outDate'] . ' ' . $m['outTime']));
                    }
                }
            }

            if ($td = $this->http->FindNodes('./td[3]//text()[normalize-space()]', $n)) {
                $td = join("\n", $td);

                if (preg_match("/^(.+?)\s*\n\s*(.+?)\s*\n\s*{$this->opt($this->t('Reservation Details'))}/s", $td, $m)
                    || preg_match("/^(.+?)\s*\n\s*(.+?)\s*\n.*{$this->opt($this->t('Quantity:'))}/s", $td, $m)
                ) {
                    if ($it->getType() == 'hotel') {
                        $name = preg_replace("#^(.+)\s*(?:\n[\s\S]+|$)#", '$1', $m[2]);

                        if (!preg_match("#camp\w*\s*$#i", $name)) {
                            $name .= ' Camping';
                        }
                        $it->hotel()
                            ->name($name)
                            ->address(str_replace("\n", ', ', $m[2]));

                        $it->addRoom()->setType($m[1]);
                    } else {
                        $it->place()->name($m[2]);
                        $it->place()->address(preg_replace("# (Ticket|Timed Entry).*#i", '', str_replace("\n", ' ', $m[2])));
                        $it->place()->type(Event::TYPE_EVENT);
                    }
                }

                if (preg_match("/^(.+)\n{$this->opt($this->t('Reservation Details'))}/u", trim($td), $m)) {
                    if ($it->getType() == 'hotel') {
                    } else {
                        $it->place()->name($m[1]);
                        $it->place()->address(preg_replace("# (Trails - Permit|Wilderness Permit - Permit|Permits - Permit).*#i", '', str_replace("\n", ' ', $m[1])));
                        $it->place()->type(Event::TYPE_EVENT);
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
