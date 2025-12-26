<?php

namespace AwardWallet\Engine\tesco\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    public static $titles = [
        'Mr'   => 'Mr',
        'Mrs'  => 'Mrs',
        'Miss' => 'Miss',
        'Ms'   => 'Ms',
    ];

    public static $inputFieldsMap = [
        'Title'              => 'reg-title',
        'FirstName'          => 'register-firstname',
        'LastName'           => 'register-lastname',
        'PhoneLocalNumber'   => 'register-phone',
        'Email'              => 'register-email',
        'PostalCode'         => '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.postalCode',
        'DoYouHaveAClubcard' => 'register-no-clubcard',
        'ClubcardNumber'     => 'register-clubcard',
        'Password'           => ['register-password', 'register-password-confirm'],
        'AddressLine1'       => '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.primaryStreet',
        'AddressLine2'       => '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.buildingNameNumber',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $this->http->removeCookies();

        $this->AccountFields['BrowserState'] = null;

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyUK());
        } else {
            $this->http->SetProxy('localhost:8000');
        } // This provider should be tested via proxy even locally
    }

    public function registerAccount(array $fields)
    {
        $status = false;

        try {
            $status = $this->registerInternal($fields);
        } catch (\CheckException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->http->log($e->getMessage(), LOG_LEVEL_ERROR);

            return false;
        }

        return $status;
    }

    public function registerInternal(array $fields)
    {
        $this->http->GetURL('https://secure.tesco.com/direct/my/register.page'); //?_DARGS=/blocks/common/flyoutMyAccount.jsp_A&_DAV=true

        if (!$this->http->ParseForm('ir-register1')) {
            throw new \EngineError('Error parse registration form');
        }

        $this->checkValues($fields);

        if (!$fields['DoYouHaveAClubcard']) {
            $fields['DoYouHaveAClubcard'] = $fields['DoYouHaveAClubcard'] === true ? 'false' : 'true';
        }

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        $addValues = [
            //            '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.postalCode' => 'LN101AH',
            //            '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.city' => 'Aberdeen',
            //            '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.primaryStreet' => 'My new street',
            //            '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.buildingNameNumber' => '123',
            //            '/com/tesco/ecom/util/PCAAddressVO.pCAAddress.organisationName' => 'Aberdeen City Council',
            //            'register-password-confirm' => 'Q1w2e3r4t5_',
            //            'register-password' => 'Q1w2e3r4t5_',
            //            'register-phone' => '0123456789',
            ///////////////
            'register-no-clubcard' => 'true',
            'register-button'      => 'Continue',
        ];

        foreach ($addValues as $row => $value) {
            $this->http->SetInputValue($row, $value);
        }

        if (!$this->http->PostForm()) {
            throw new \EngineError('Error post registration form');
        }

        if (!$response = json_decode($this->http->Response['body'], true)) {
            throw new \EngineError('Error parse result json');
        }

        if (isset($response['redirection']) and $response['redirection'] == '/direct/?register') {
            $this->ErrorMessage = 'Success! Your Login is ' . $fields['Email'];
            $this->http->log($this->ErrorMessage);

            return true;
        }

        if (isset($response['dg-ir-page-error'])) {
            throw new \UserInputError(CleanXMLValue(strip_tags($response['dg-ir-page-error'])));
        } // Is it always user input error?

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Title ',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'PhoneLocalNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (UK phone numbers only, must begin with 01, 02 or 07 and contain 10 or 11 digits including the dialling code)',
                'Required' => true,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postcode',
                'Required' => true,
            ],
            'DoYouHaveAClubcard' =>
            [
                'Type'     => 'boolean',
                'Caption'  => 'Do you have a Clubcard?',
                'Required' => true,
            ],
            'ClubcardNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Your Clubcard number',
                'Required' => false,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (must be a minimum of 8 characters and contain a number, an uppercase character, a lowcase character and a special character)',
                'Required' => true,
            ],
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Street',
                    'Required' => true,
                ],
            'AddressLine2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Building number / name',
                    'Required' => true,
                ],
        ];
    }

    protected function checkValues(&$fields)
    {
        if ($fields['DoYouHaveAClubcard']) {
            if (!isset($fields['ClubcardNumber']) or trim($fields['ClubcardNumber']) === '') {
                throw new \UserInputError('ClubcardNumber error value');
            }

            if (!preg_match('/^[\d]{16,18}$/i', trim($fields['ClubcardNumber']))) {
                throw new \UserInputError('ClubcardNumber invalid value');
            }
        } else {
            if (isset($fields['ClubcardNumber'])) {
                unset($fields['ClubcardNumber']);
            }
        }

        if (!preg_match('/^0[1|2|7]{1}[\d]{8,9}$/i', $fields['PhoneLocalNumber'])) {
            throw new \UserInputError('Phone invalid value. Whole "Phone Dialing Code"+"Phone Area Code"+"Phone Local Number" must begin with 01, 02 or 07 and contain 10 or 11 digits including the dialling code');
        }

        if (!preg_match('/^([A-PR-UWYZ0-9][A-HK-Y0-9][AEHMNPRTVXY0-9]?[ABEHMNPRVWXY0-9]? {1,2}[0-9][ABD-HJLN-UW-Z]{2}|GIR 0AA)$/', $fields['PostalCode'])) {
            throw new \UserInputError('PostalCode invalid value.');
        }
    }
}
