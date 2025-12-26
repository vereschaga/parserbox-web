<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class Forward extends \TAccountChecker
{
    protected $forward = []; /*
        '/[@.]southwest\b/',
        '@southwestvacations.com',
        '/[@.]customercommunications\b/',
        '/[@.]wnco\b/',
        'rapidrewards',
        '@rapidrewardsshopping.com',
        '@ipsosresearch.com',
        '@nettracer.aero',
        '@surveymonkeyuser.com',
        '@points-mail.com', // с этого адреса могут быть и другие провайдеры(в зависимости от текста до '@')
                            // но убирать этот адрес от сюда все равно не нужно
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
        return 0;
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
