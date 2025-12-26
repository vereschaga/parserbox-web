var plugin = {

    hosts: {'paradores.es': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://paradores.es/en/amigos-de-paradores';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);

                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);

            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");

        if ($('#friends-access:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($("#block-loginamigosblock a[href*='/user/logout']").length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('div#extracareCard span[ng-show *= "extraCareTied"]:eq(0)').text();//todo
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.ExtraCareNumber) != 'undefined')
                && (account.properties.CardNumber !== '')
                && number
                && (number === account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $("#block-loginamigosblock a[href*='/user/logout']").get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open login form
        const signIn = $('#friends-access');

        if (signIn.length) {
            signIn.get(0).click();
        }

        // wait login form
        let counter = 0;
        let login = setInterval(function () {
            const form = $('form#forms-login-amigos');
            browserAPI.log("waiting... " + counter);

            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('#edit-user').val(params.account.login);
                form.find('#edit-password').val(params.account.password);
                return provider.setNextStep('checkLoginErrors', function () {
                    //form.find('button#edit-submit-amigo').get(0).click();
                    form.submit();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            }

            if (counter > 10) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.alert-danger:visible p');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loadLoginProfile(params);
    },

    loadLoginProfile: function (params) {
        browserAPI.log("loadLoginProfile");
        provider.setNextStep('loginComplete', function () {
            document.location.href = 'https://paradores.es/en/mis-puntos';
        });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};