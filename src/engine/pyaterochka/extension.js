var plugin = {
    //keepTabOpen: true,
    hosts: {'my.5ka.ru': true},

    getStartingUrl: function (params) {
        return 'https://my.5ka.ru/login';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
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
        if ($(':contains("Войти")').text().length <= 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($(':contains("По номеру карты")').text().length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('.summary__name').text();
        name = name.replace(/\s+/g, " ").trim().toLowerCase();
        return (typeof(account.properties) != 'undefined'
        && typeof(account.properties.Name) != 'undefined'
        && account.properties.Name != ""
        && account.properties.Name.toLowerCase() == name);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $("a[ng-show='authorized === true']").click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "LoginFormByCard"]:visible, form[name = "LoginFormByPhone"]:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var login = params.account.login.replace(/(?:\s+|-)/g, "");
            var schema;
            var name;
            var btn;
            var login2 = "";
            if (params.account.login2.length > 0 && (params.account.login2 === "card" || params.account.login2 === "phone")) {
                browserAPI.log("get type from login2");
                login2 = params.account.login2;
            } else if (login.length > 14)
                login2 = "card";
            else
                login2 = "phone";


            if (login2 === "card") {
                schema = "by-card";
                name = 'cardNo';
                btn = $('button[class="tabs__item"]:contains("По номеру карты")');
            }
            else {
                login = '+7' + login.slice(-10);
                schema = "by-phone";
                name = 'phone';
                btn = $('button[class="tabs__item"]:contains("По номеру телефона")');
            }
            if (btn.length > 0) {
                browserAPI.log("opening " + name + " form...");
                btn.click();
            }
            form = $('form[name = "LoginFormByCard"]:visible, form[name = "LoginFormByPhone"]:visible');

            setTimeout(function () {
                browserAPI.log("[schema]: " + schema);
                browserAPI.log("[login]: " + login);
                form.find('input[name = "' + name + '"]').val(login.match(/.{1,4}/g).join('-'));
                form.find('input[name = "' + name + '_password"]').val(params.account.password);

                provider.eval(
                    "var scope = angular.element(document.querySelector('form[name=\"LoginForm" + util.beautifulName(schema).replace("-", "") + "\"]')).scope();" +
                    "scope.signIn('" + login + "', '" + params.account.password + "', '" + schema + "');"
                );

                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            }, 1000);
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div[class="form__field-error"]:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}