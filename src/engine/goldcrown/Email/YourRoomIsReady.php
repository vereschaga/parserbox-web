<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourRoomIsReady extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-34188239.eml";
    public $reFrom = "@bestwestern.complete-booking.com";
    public $reSubject = [
        "en" => ["Your room is ready", "Complete your booking"],
    ];
    public $reBody2 = [
        "en" => ["Your Room Selection", "Don’t forget to complete your booking!"],
    ];

    public static $dictionary = [
        "en" => [
            'Finish Booking' => ['Finish Booking', 'Book Now', 'Complete booking'],
        ],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $lang => $res) {
            foreach ($res as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (!preg_match("/{$this->opt($this->t('Finish Booking'))}/u", $body)) {
            return false;
        }

        if (strpos($body, 'bestwestern') === false && strpos($body, 'Best Western') === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $words) {
            foreach ($words as $word) {
                if (strpos($body, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang => $res) {
            foreach ($res as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

        //$this->parseHtml($email);
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

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

    private function parseHtml(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Your Room Selection'))}]/ancestor::table[1]");

        if ($nodes->length == 1) {
            $root = $nodes->item(0);
        } else {
            return false;
        }

        $r = $email->add()->hotel();

        $r->general()
            ->noConfirmation();

        if ($this->http->XPath->query("//a[{$this->eq($this->t('Finish Booking'))}]")->length > 0) {
            $r->general()->status('not finished');
        }

        $r->hotel()
            ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root))
            ->address($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][3]", $root));

        $r->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Check-in'))}]",
                $root, false, "#:\s*(.+)#")))
            ->checkOut(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Check-out'))}]",
                $root, false, "#:\s*(.+)#")))
            ->guests($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Guest'))}]", $root,
                false, "#(\d+) {$this->opt($this->t('Guest'))}#"));
        $room = $r->addRoom();
        $room->setType($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Guest'))}]/preceding::text()[normalize-space()!=''][1]",
            $root));

        return true;
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

        return '(?:' . implode("|", $field) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
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
}
