<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourUpcomingTripToJunk extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-264629148.eml";
    public $subjects = [
        'Reminders about your upcoming trip to',
        'Quick reminders about your upcoming trip to',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your Itinerary' => ['Your Itinerary', 'Flight itinerary', 'Your itinerary:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getCleanFrom(), 'notifications@united.com') === false) {
            return false;
        }

        $detectedSubject = false;

        foreach ($this->subjects as $subject) {
            if (stripos($parser->getSubject(), $subject) !== false) {
                $detectedSubject = true;

                break;
            }
        }

        if ($detectedSubject === false) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->eq('My United')}]")->length === 0) {
            return false;
        }

        if (
            $this->http->XPath->query("//*[{$this->contains(["It's almost time for your trip", 'It’s almost time for your trip', "it’s almost time for your trip", 'Your trip is just around the corner'])}]")->length > 0
            && $this->http->XPath->query("//tr[count(*[normalize-space()])=2 and *[1][{$this->eq($this->t('Your Itinerary'))}] and *[normalize-space()][2][count(.//text()[normalize-space()])=6 and {$this->starts('Destination:')}]]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'notifications@united.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = implode("\n", $this->http->FindNodes("//tr[count(*[normalize-space()])=2 and *[1][{$this->eq($this->t('Your Itinerary'))}] and *[normalize-space()][2][count(.//text()[normalize-space()])=6 and {$this->starts('Destination:')}]]//text()[normalize-space()]"));
        $this->logger->debug('$text = ' . print_r($text, true));

        if (preg_match("/^\s*{$this->opt($this->t('Your Itinerary'))}\s+Destination:\s*.+\s+Reservation Number:\s*[A-Z\d]{5,7}\s*Departs:\s*.{5,20}\b\d{4}\s*$/i", $text)) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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
}
