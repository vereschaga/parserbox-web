<?php

namespace AwardWallet\Engine\recreation\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "recreation/it-551532408.eml, recreation/it-867836108.eml";
    public $subjects = [
        'Recreation.gov Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Modify Ticket'                   => ['Modify Ticket'],
            'This location needs you to know' => ['This location needs you to know'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@recreation.gov') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, '.recreation.gov')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Modify Ticket']) && !empty($dict['This location needs you to know'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Modify Ticket'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['This location needs you to know'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]recreation\.gov$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $event = $email->add()->event();

        $event->type()
            ->event();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Adventure awaits! Your reservation'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([\d\-]{5,})\s*$/");

        $event->general()
            ->confirmation($confirmation)
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]", null, true,
                "/^\s*{$this->opt($this->t('Hello '))}\s*([[:alpha:]\- ]+?),\s*$/"), false);

        // Place
        $event->place()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Details'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Details'))}]/following::text()[normalize-space()][2]"))
        ;

        $datesText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Start Time:'))}]/ancestor::td[not({$this->starts($this->t('Start Time:'))})][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^\s*(?<date>.*?20\d{2}.*?)(?<arrDate>\n\s*.*?20\d{2}.*?)?\s*\n\s*{$this->opt($this->t('Start Time:'))}\s*(?<startTime>\d+:\d+(?:\s*[AP]M)?)\s*\n\s*"
            . "{$this->opt($this->t('End Time:'))}\s*(?<endTime>\d+:\d+(?:\s*[AP]M)?)?\s+{$this->opt($this->t('Tickets:'))}/", $datesText, $m)
        ) {
            $m['endTime'] = $m['endTime'] ?? null;
            $event->booked()
                ->start(strtotime($m['date'] . ', ' . $m['startTime']));

            if ($m['startTime'] == $m['endTime'] || empty($m['endTime']) || ($m['startTime'] === "1:00 AM" && $m['endTime'] === "12:59 AM")) {
                $event->booked()
                    ->noEnd();
            } else {
                $event->booked()
                    ->end(strtotime(!empty($m['arrDate']) ? $m['arrDate'] : $m['date'] . ', ' . $m['endTime']));
            }
        }

        if (preg_match("/{$this->opt($this->t('Tickets:'))}\s*{$this->opt($this->t('Adult: *(\d+)\s*$'))}/", $datesText, $m)
        ) {
            $event->booked()
                ->guests(strtotime($m['date'] . ', ' . $m['startTime']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
