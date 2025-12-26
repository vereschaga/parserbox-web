<?php

namespace AwardWallet\Engine\mypoints\Transfer;

class Register extends \TAccountCheckerMypoints
{
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $inputFieldsMap = [
        'Email'      => 'email',
        'Password'   => ['password1', 'password2'],
        'FirstName'  => 'name.first',
        'BirthMonth' => 'birthDateMonth',
        'BirthDay'   => 'birthDateDay',
        'BirthYear'  => 'birthDateYear',
        'PostalCode' => 'zipcode',
        'Gender'     => 'genderCode',
    ];

    protected $fields;

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $this->fields = $fields;
        $this->http->removeCookies();
        $this->http->GetURL("https://www.mypoints.com/emp/u/index.vm");

        if (!$this->http->ParseForm("regForm")) {
            return false;
        }

        $this->fields['BirthDay'] = ($this->fields['BirthDay'] + 0) < 10 ? '0' . $this->fields['BirthDay'] : $this->fields['BirthDay'];
        $this->fields['BirthMonth'] = ($this->fields['BirthMonth'] + 0) < 10 ? '0' . $this->fields['BirthMonth'] : $this->fields['BirthMonth'];

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $this->fields[$awKey]);
            }
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        $errors = $this->http->FindNodes("//div[contains(@class,'alert-danger')]//p[contains(@class,'alert-description')]");

        if (!empty($errors)) {
            throw new \UserInputError(implode(";\n", $errors));
        } // Is it always user input error?

        if ($successMessage = $this->http->FindSingleNode("//div[contains(@class,'alert-info')]//p[contains(@class,'alert-description')]")) {
            $this->ErrorMessage = 'Your account is not active until you verify your email address via the email we sent to your inbox after you signed up. Unverified accounts are closed after 30 days. If you did not receive the verification email, please sign in to your account and click to re-send it.';
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email ',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (must be at least 6 characters)',
                'Required' => true,
            ],
            'FirstName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date (2-number)',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date (2-number)',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date (4-number)',
                'Required' => true,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Zipcode (5-number)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => false,
                'Options'  => self::$genders,
            ],
        ];
    }
}
