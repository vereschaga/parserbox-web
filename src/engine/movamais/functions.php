<?php

class TAccountCheckerMovamais extends TAccountChecker
{
    private $auth;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->PostURL('https://api.movamais.com/movamais/api/v2.0/auth/login', [
            'email'    => $this->AccountFields['Login'],
            'password' => hash('sha256', $this->AccountFields['Pass']),
        ]);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->type)) {
            if (isset($response->errors[0]->errorCode)) {
                // User was not found or wrong password
                if (in_array($response->errors[0]->errorCode, ['USER_NOT_FOUND', 'WRONG_PASSWORD'])) {
                    throw new CheckException('User was not found or wrong password', ACCOUNT_INVALID_PASSWORD);
                }
                // E-mail já cadastrado via Facebook.
                if (in_array($response->errors[0]->errorCode, ['USER_HAS_NO_PASSWORD'])) {
                    throw new CheckException('Sorry, login via Facebook is not supported', ACCOUNT_PROVIDER_ERROR);
                }
            }

            if ('ERROR' == $response->type) {
                return false;
            }

            if ('SUCCESS' == $response->type && !empty($response->userId)) {
                $this->auth = $response;

                return true;
            }
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL('https://api.movamais.com/movamais/api/v1.0/user/?accessToken=' . $this->auth->token);
        $data = $this->http->JsonLog();

        if (!isset($data->type) || 'SUCCESS' != $data->type) {
            return;
        }

        $this->SetProperty('Name', beautifulName(trim($data->athlete->firstName . ' ' . $data->athlete->lastName)));

        $this->SetProperty('MemberSince', $data->athlete->registrationDate);

        // Pontos
        $this->SetBalance($data->statistics->pointsEarned);
        // atividades
        $this->SetProperty('Followers', $data->social->followerCount);
        // seguidores
        $this->SetProperty('Following', $data->social->followingCount);
        // seguindo
        $this->SetProperty('Activities', $data->statistics->activityCount);
        // medalhas
        $this->http->GetURL('https://api.movamais.com/movamais/api/v1.0/user/badges/?accessToken=' . $this->auth->token);
        $response = $this->http->JsonLog();

        if (isset($response->badges) && is_array($response->badges)) {
            $this->SetProperty('Medals', count($response->badges));
        }
    }
}
