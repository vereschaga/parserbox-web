<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HasBeenRescheduled extends \TAccountCheckerExtended
{
    public $mailFiles = "ticketmaster/it-91699092.eml";

    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = ["ticketmaster.com", "livenation.com"];
    private $detectSubject = [
        ' Has Been Rescheduled',
    ];

    private $detectCompany = [
        'Ticketmaster', 'Live Nation', 'livenation.com',
    ];

    private $detectBody = [
        'en' => ['Your Event Has Been Rescheduled'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $foundCompany = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $foundCompany = true;
            }
        }

        if ($foundCompany == false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])) {
            return false;
        }
        $findFrom = false;

        foreach ($this->detectFrom as $dFrom) {
            if (stripos($headers['from'], $dFrom) !== false) {
                $findFrom = true;
            }
        }

        if ($findFrom == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEvent(Email $email)
    {
        $ev = $email->add()->event();

        // General
        $ev->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi ')]", null, true, "/^\s*Hi ([[:alpha:] \-\']+),\s*$/"), false)
        ;

        // Place
        $ev->place()
            ->name($this->http->FindSingleNode("//text()[normalize-space() = 'Your New Event Date Is:']/preceding::text()[normalize-space()][1]/ancestor::tr[position()<3][td[1][.//img and normalize-space()='']]/td[normalize-space()][1]/descendant::td[not(.//td) and normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//td[not(.//td) and normalize-space() = 'Your New Event Date Is:']/following::td[not(.//td) and normalize-space()][2]"))
            ->type(Event::TYPE_SHOW)
        ;

        // Booked
        $ev->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//td[not(.//td) and normalize-space() = 'Your New Event Date Is:']/following::td[not(.//td) and normalize-space()][1]")))
            ->noEnd()
        ;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('In ' . $str);
        $in = [
            "/^\s*([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})\s*@\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i", //August 25, 2021 @ 7:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('Out ' . $str);
        $this->logger->debug(strtotime($str));

        return strtotime($str);
    }
}
