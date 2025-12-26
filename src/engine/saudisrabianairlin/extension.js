var plugin = {

    hosts: {
        'alfursan.saudia.com': true
    },

    getStartingUrl: function (params) {
        return 'https://alfursan.saudia.com/en';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();

            // mobile design fix
            if (counter === 2 && $('button.navigation-menu-button:visible').length > 0) {
                $('button.navigation-menu-button').click();
            }

            if (isLoggedIn !== null && counter > 5) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    $('button[data-test="login-btn"]:visible').click();

                    setTimeout(function () {
                        plugin.login(params);
                    }, 3000)
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('button[data-test="login-btn"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=Logout]').text()) {//todo
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = util.trim($('div.youraccnt_welcome div.nav_font_bold').text());//todo
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        // provider.eval("signOut('Logout.jsp');");
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[id = "gigya-login-form"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "username"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors');
        // form.find('input[onclick="submitForm()"]').click();
        provider.eval("submitForm();");

        setTimeout(function () {
            plugin.checkLoginErrors(params);
        }, 10000)
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.gigya-error-msg:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }
};