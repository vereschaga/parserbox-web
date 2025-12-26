<?php

namespace AwardWallet\Engine\mileageplus\Email;

class Forward extends \TAccountChecker
{
    protected $forward = []; /*
        '/[@.]united\b/',
        '/[@.]mileageplus\b/',
        '@mileageplusshoppingnews.com',
        '@unitedmileageplus.com',
        '@united.ipmsg.com',
        '@allianzassistance.com',
        '@cartera.com',
        '@carteracommerce.com',
        '/[@.]qemailserver\b/',
        '@magsformiles.delivery.net',
        '@chargerback.com',
        '@unitedcares.com',
        '@points-mail.com',
    ];*/

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && $this->checkAddress($headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return $this->checkAddress($from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => [],
            'emailType'  => 'forward',
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function checkAddress($from)
    {
        foreach ($this->forward as $check) {
            if (stripos($check, '/') === 0 && preg_match($check, $from) || stripos($from, $check) !== false) {
                return true;
            }
        }

        return false;
    }
}
