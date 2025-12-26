<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class SavedStatement extends \TAccountChecker
{
    // Southwest Airlines personal statement, saved from site section 'Snapshot' and sent by email to AW
    public $mailFiles = "rapidrewards/statements/st-2064880.eml, rapidrewards/statements/st-2064883.eml, rapidrewards/statements/st-2064885.eml, rapidrewards/statements/st-2064891.eml, rapidrewards/statements/st-2064892.eml, rapidrewards/statements/st-2144379.eml, rapidrewards/statements/st-2158255.eml";

    public function ParseStatement(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($pdfs = $parser->searchAttachmentByName('.*pdf')) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_SIMPLE);
        } else {
            $text = $this->toText($parser->getHTMLBody());
        }

        $name = $this->re('#My\s+Account\s*(?:My\s+Rapid\s+Rewards\s+Tier\s+Status|My\s+Rapid\s+Rewards\s+Rewards\s+Activity)?\s+(.*)\'s\s+Rapid\s+Rewards\s+Account#i', $text);

        if (!empty($name)) {
            $st->addProperty("Name", $name);
        }

        if (preg_match('#R\.\s*R\.\s*\#\s+(\d+)\s+Last\s+Activity:\s+(\d+/\d+/\d+)\s+([\d,]+)\s+Available\s+Pts#i', $text, $m)) {
            $st
                ->setLogin($m[1])
                ->setNumber($m[1])
            ;
            $last = strtotime($m[2]);

            if ($last > strtotime("01/01/2010")) {
                $st->addProperty("LastActivity", $m[2]);
                $st->addProperty("AccountExpirationDate", strtotime("+ 2 years", $last));
            }

            $st->setBalance(str_replace(',', '', $m[3]));
            $st->addProperty("Points", $m[3]);
        }

        $tierFlights = $this->re('#Qualifying[ \s]+flights[ \s]+flown:\s+(\d+)#i', $text);

        if (!empty($tierFlights)) {
            $st->addProperty("TierFlights", $tierFlights);
        }
        $tierPoints = $this->re('#Qualifying[ \s]+points[ \s]+earned:\s+(\d+)#i', $text);

        if (!empty($tierPoints)) {
            $st->addProperty("TierPoints", $tierPoints);
        }

        if (!empty(array_filter($st->getProperties()))) {
            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;
        }

        return $email;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseStatement($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($pdfs = $parser->searchAttachmentByName('.*pdf')) {
            $pdf = $parser->getAttachmentBody($pdfs[0]);
            $text = \PDF::convertToText($pdf, \PDF::MODE_SIMPLE);
        } else {
            $text = text($parser->getHTMLBody());
        }

        return stripos($text, 'Rapid Rewards Account') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#[^\w\d\t\r\n\* :;,./\(\)\[\]\{\}\-\\\$+=_<>&\#%^&!]#", ' ', $html);

        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        return $html;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
