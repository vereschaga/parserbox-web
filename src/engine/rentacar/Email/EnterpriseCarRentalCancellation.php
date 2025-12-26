<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EnterpriseCarRentalCancellation extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-48457813.eml";

    public $reFrom = "partnerbookingkit.com";
    public $reBody = [
        'en' => ['This email is to confirm', 'has been cancelled'],
    ];
    public $reSubject = [
        'Enterprise Car Rental Cancellation:',
    ];
    private $keywordProv = "Enterprise Car Rental";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->rental();
        $text = $this->http->FindSingleNode("//text()[contains(normalize-space(),'This email is to confirm that your reservation number')]");
        $r->general()
            ->confirmation($this->http->FindPreg("/your reservation number\s+(\d+)/", false, $text))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear ')]", null, false,
                "/Dear (.+?),/"))
            ->status($this->http->FindPreg("/has been (\w+)/", false, $text))
            ->cancelled();
        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.partnerbookingkit.com')]")->length > 0
            && $this->http->XPath->query("//a")->length === 0
        ) {
            return $this->http->XPath->query("//*[contains(normalize-space(),'This email is to confirm that your reservation number')]")->length > 0 && $this->http->XPath->query("//*[contains(normalize-space(),'has been cancelled')]")->length > 0;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = false;

        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false) {
            $from = true;
        }

        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (($from || preg_match("/\{$this->keywordProv}\b/i", $reSubject) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
