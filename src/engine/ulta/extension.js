var plugin = {
    //keepTabOpen: true,
    hosts: {'www.ulta.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.ulta.com/ulta/myaccount/login.jsp';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        provider.complete();
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
        if ($('#username:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.makeup-breadcrumb li:contains("My Account")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.trim($('li:contains("Member ID:") span').text());
        browserAPI.log("number: " + number);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Number) !== 'undefined'
            && account.properties.Number !== ''
            && number === account.properties.Number;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            if (provider.isMobile) {
                let logout = $('button:contains("Sign Out")');
                if (logout.length)
                    logout.get(0).click();
            } else {
                var menu = $('.DesktopHeader__NavigationBar__item--userName');
                if (menu.length) {
                    menu.get(0).click();
                    let logout = $('.SignInMenu__userOptions a:contains("Sign Out")');
                    if (logout.length)
                        logout.get(0).click();
                }
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let username = $('input[id *= "username"]:visible');

        if (username.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        //username.val(params.account.login);
        //$('#password').val(params.account.password);

        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.querySelector('input[id *= \"username\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
            "FindReact(document.querySelector('input[id *= \"password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
        );

        provider.setNextStep('checkLoginErrors', function () {
            $('.LoginForm__Submit button[type="submit"]').get(0).click();
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('.loginErrorMessage > span:last:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return
        }

        provider.complete();
    }

};