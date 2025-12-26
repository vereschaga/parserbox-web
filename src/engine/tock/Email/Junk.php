<?php

namespace AwardWallet\Engine\tock\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Junk extends \TAccountChecker
{
    public $mailFiles = "tock/it-108038241.eml, tock/it-354827260.eml, tock/it-511186981.eml";

    private $detectFrom = "noreply@exploretock.com";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos(implode(" ", $parser->getFrom()), $this->detectFrom) === false) {
            return false;
        }

        $subject = $parser->getSubject();

        if (
            strpos($subject, 'Thank you for ordering from ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains(['Thank you for ordering!']) . "]")->length > 0
        ) {
            return true;
        }

        if ((strpos($subject, 'Thank you for visiting ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains('Thank you for visiting!') . "]")->length > 0)
            || (strpos($subject, 'Thank you for ordering from ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains(['Thank you for ordering!']) . "]")->length > 0)
        ) {
            if ($this->http->XPath->query("//text()[" . $this->contains('How was your experience?') . "]")->length > 0) {
                return true;
            }
            $date = strtotime(preg_replace('/ at /', ', ', $this->http->FindSingleNode("//text()[{$this->eq(['Thank you for visiting!', 'Thank you for ordering!'])}][not(ancestor::title)]/following::text()[normalize-space()][not(ancestor::style)][2]",
                null, true, "/^\s*(.+ at \d+:\d+.*) · .+/")));

            if (!empty($date) && strtotime($parser->getDate()) > strtotime('+ 10 hours', $date)) {
                return true;
            }
        }

        if (strpos($subject, 'In preparation for your visit at ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains(['Help us prepare for your visit', 'Help us prepare for your experience']) . "]")->length > 0
            && $this->http->XPath->query("//text()[" . $this->contains('Answer questions') . "]")->length > 0
        ) {
            return true;
        }

        if (strpos($subject, 'You\'ve been added to the waitlist') === 0
            && $this->http->XPath->query("//text()[" . $this->contains('You\'ll be notified if a reservation becomes available') . "]")->length > 0
        ) {
            return true;
        }

        if ((strpos($subject, 'Your pickup order for ') === 0 || strpos($subject, 'Your delivery order for ') === 0)
            && $this->http->XPath->query("//text()[" . $this->contains('Manage your order') . "]")->length > 0
        ) {
            return true;
        }

        if (strpos($subject, 'Private Event Request for ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains('Private event request sent') . "]")->length > 0
        ) {
            return true;
        }

        if (
            strpos($subject, 'New reservations on ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains(['Waitlist notification', 'You\'ve been added to the waitlist']) . "]")->length > 0
            && $this->http->XPath->query("//text()[" . $this->contains('this is not a reservation confirmation') . "]")->length > 0
        ) {
            return true;
        }

        if (
            strpos($subject, 'Your held reservation at ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains('Held seats expired') . "]")->length > 0
        ) {
            return true;
        }

        if (
            strpos($subject, 'Email purchase completed for') === 0
            && $this->http->XPath->query("//text()[" . $this->contains('has completed their book by email request for a reservation at') . "]")->length > 0
            && $this->http->XPath->query("//text()[" . $this->contains('On your statement, this charge will appear as') . "]")->length > 0
        ) {
            return true;
        }

        if (
            strpos($subject, 'Please accept a reservation for ') === 0
            && $this->http->XPath->query("//text()[" . $this->contains(', your reservation is not complete') . "]")->length > 0
        ) {
            return true;
        }

        if (
            preg_match('/\bTransfer\b/i', $subject)
            && $this->http->XPath->query("//text()[" . $this->contains(['Reservation transfer complete', 'To complete this transfer, please confirm your request', 'Please accept the transfer of a reservation', 'Reservation transfer cancelled']) . "]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
