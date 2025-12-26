var plugin = {
    clearCache:true,
    flightStatus: {
        url: 'http://cyb.koreanair.com/KalApp/mobileGate?mode=flightStatus_en&accessType=mweb&lang=eng',
        match: /^(?:KE)?\d+/i,

        start: function () {
            var searchRadio = $('[name=radio3]').eq(1);
            var flightInput = $('#flight');
            if (searchRadio.length == 1 && flightInput.length == 1) {
                searchRadio.click();
                flightInput.val(params.flightNumber.replace(/KE/gi, ''));

                var dateInput = $('#ShowFlight select[name="orgDate"]');
                var depDateElem = dateInput.find('option[value*="' + $.format.date(api.getDepDate(), 'yyyyMMdd') + '"]');
                if (depDateElem.length == 1) {
                    dateInput.val(depDateElem.val());
                    api.setNextStep('finish', function () {
                        sendSubmit();
                    });
                } else {
                    api.errorDate();
                }
            }
        },

        finish: function () {
            //api.error('Error text');
            if ($('.fl_name').length == 1)
                api.complete();
            else {
                api.error($('#ct div:not([class])').text().trim().replace(/[\n\t]|\s{2,}/i, ''));
            }
        }
    },

    autologin: {

        cashbackLink : '', // Dynamically filled by extension controller
        startFromCashback : function(params) {
            browserAPI.log('startFromCashback');
            provider.setNextStep('start', function () {
                document.location.href = plugin.autologin.getStartingUrl(params);
            });
        },

        getStartingUrl: function(params) {
            // return "https://www.koreanair.com/mobile/global/";
            return "https://www.koreanair.com/mobile/global/en/profile/profile.html#dashboard";
        },

        start: function (params) {
            browserAPI.log('start');
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params.account))
                            plugin.autologin.finish(params);
                        else
                            plugin.autologin.logout();
                    }
                    else
                        plugin.autologin.login(params)
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        isSameAccount: function (params) {
            browserAPI.log('isSameAccount');
            var number = $('.skypassNo').eq(0).text().replace(/\s+/g, '');
            browserAPI.log("number: " + number);
            return ((typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.AccountNumber) !== 'undefined')
                && ('' !== params.properties.AccountNumber.trim())
                && number
                && number == params.properties.AccountNumber);
        },

        login: function (params) {
            browserAPI.log('login');
            var modal = koreanair.mobileLoginModal().always(function () {
                var form = $('form#loginForm');
                if (form.length > 0) {
                    if (params.login2 == 'sky') {
                        form.find('label[for="login-skypass"]').click();
                    }
                    form.find('#usernameInput').val(params.login);
                    form.find('#passwordInput').val(params.pass);
                    api.setNextStep('checkLoginErrors', function () {
                        $('#modalLoginButton').addClass('disabled');

                        koreanair.Login.callLoginService(
                            $('#loginForm input[name=how-to-login]:checked').val(),
                            $('#loginForm #usernameInput').val(),
                            $('#loginForm #passwordInput').val())
                            .done(function() {
                                $('#closeBtnType3').click();
                                plugin.autologin.finish();
                            }).
                            fail(function() {
                                $('#errorWrapper').html('<h4><a class="errorMessage" href="#">' + Granite.I18n.get('unauthorized-login-label') + '</a></h4>');
                                $('#errorWrapper a').focus();
                                $('#errorWrapper a').on('click', function (evt) {
                                    evt.preventDefault();
                                    evt.stopPropagation();
                                    $('#usernameInput').focus();
                                });
                                $('#modalLoginButton').removeClass('disabled');
                                plugin.autologin.checkLoginErrors(params);
                            });
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
            });
            modal.resolve();
        },

        checkLoginErrors: function (params) {
            browserAPI.log("checkLoginErrors");
            var errors = $('#errorWrapper a:visible');
            if (errors.length > 0) {
                provider.setError(errors.text());
            }
            else
                plugin.autologin.finish(params);
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('.logout').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('.btnlogin').length > 0) {
                browserAPI.log("LoggedIn");
                return false;
            }
            if ($('.notlogin').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            return null;
        },

        logout: function (params) {
            browserAPI.log('logout');
            api.setNextStep('toLoginPage', function () {
                koreanair.Login.logoutUser();
            });
        },

        toLoginPage: function (params) {
            browserAPI.log('toLoginPage');
            api.setNextStep('login', function () {
                document.location.href = plugin.autologin.getStartingUrl(params);
            })
        },

        finish: function () {
            browserAPI.log('finish');
            api.complete();
        }
    }
};