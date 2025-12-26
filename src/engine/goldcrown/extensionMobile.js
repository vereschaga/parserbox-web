var plugin = {
    autologin : {
        url : 'https://www.bestwestern.com/en_US/rewards/member-dashboard.html',

        start : function() {
            browserAPI.log('start');
            setTimeout(function() {
                if (plugin.autologin.isLoggedIn())
                    if (plugin.autologin.isSameAccount())
                        plugin.autologin.finish();
                    else
                        plugin.autologin.logout();
                else
                    plugin.autologin.login();
            }, 2000);
        },

        login : function() {
            browserAPI.log('login');
            $('a.loginLink', '#bw-login-button').trigger('click');
            $('.guestLogin').removeClass('hidden modalSmHidden');

            var $form = $('#guest-login-form');
            if ($form.length) {
                $('input[name="guest-user-id-1"]', $form).val(params.login);
                $('input#guest-password-1', $form).val(params.pass);
                var $recaptchaFrame = $('iframe[src*="/recaptcha/"]');
                if ($recaptchaFrame.length) {
                    provider.reCaptchaMessage();
                    provider.setNextStep('checkLoginErrors', function() {
                        browserAPI.log("captcha entered by user");
                        //$form.submit();
                        var isError = setInterval(function() {
                            var $error = $('div.errorInfo .defaultMessage:visible');
                            if ($error.length && '' != util.trim($error.text())) {
                                api.error(util.trim($error.text()));
                                clearInterval(isError);
                            }
                        }, 1000);
                    });
                } else {
                    provider.setNextStep('checkLoginErrors', function() {
                        $form.submit();
                    });
                }
                return;
            }
            provider.setError(util.errorMessages.loginFormNotFound);
        },

        isSameAccount : function() {
            browserAPI.log('isSameAccount');
            return (typeof(params.properties) != 'undefined' &&
            typeof(params.properties.Number) != 'undefined' &&
            params.properties.Number != '' &&
            $('p:contains("Account# ' + params.properties.Number + '")').length);
        },

        isLoggedIn : function() {
            browserAPI.log('isLoggedIn');
            if ($('form#rewards-login-form, #bw-login-button a.loginLink:visible').length) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('a:contains("Logout"), #bw-login-button a.accountNavLink:visible,.username-editprofilelink').length) {
                browserAPI.log('logged in');
                return true;
            }

            provider.setError(util.errorMessages.unknownLoginState);
            return false;
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            var $error = $('div.errorInfo .defaultMessage:visible');
            if ($error.length && '' != util.trim($error.text())) {
                api.error(util.trim($error.text()));
            } else
                this.finish();
        },

        logout : function() {
            browserAPI.log('logout');
            api.setNextStep('loadLoginForm', function() {
                $('button.logoutButton').click();
            });
        },

        loadLoginForm : function() {
            browserAPI.log('loadLoginForm');
            api.setNextStep('login', function() {
                document.location.href = plugin.autologin.url;
            });
        },

        toDetailsPage : function(nextStep) {
            api.setNextStep(nextStep, function() {
                document.location.href = $('a[href*="homeProfileWithPagination"]').attr('href');
            });
        },

        finish : function() {
            browserAPI.log('finish');
            api.complete();
        }

    }
};
