var plugin = {

    // keepTabOpen: true,
    hosts: {'www.sclub.ru': true, 'sclub.ru': true},

    getStartingUrl: function (params) {
        return 'https://www.sclub.ru/';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if ($('label:contains("Вход"):visible').length > 0 || $('label:contains("Выход"):visible').length > 0) {
                clearInterval(start);
                plugin.start2(params);
            }
            if (counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
            }
            counter++;
        }, 500);
    },

    start2: function (params) {
        browserAPI.log("start2");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('label:contains("Выход"):visible').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        if ($('label:contains("Вход"):visible').length > 0) {
            $('label:contains("Вход")').get(0).click();
            browserAPI.log("not logged in");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        browserAPI.log("isSameAccount");
        var profile = $('a[href = "/my/profile"]');
        var name = '';
        if (profile.length > 0) {
            name = profile.attr('title');
        }
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start');
        var logout = $('label:contains("Выход")');
        if (logout.length > 0) {
            logout.get(0).click();
            plugin.start(params);
        }
    },

    login: function (params) {
        browserAPI.log("login");

        var form = $('form[action *= "/login"]');
        if (form.length > 0) {
            browserAPI.log("Submitting credentials");
            form.find('input[name = "login"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            // // angularjs
            // provider.eval('var scope = angular.element(document.getElementsByClassName("general-form general-form--center")).scope();' +
            //     'scope.$ctrl.loginModel.name = "' + params.account.login + '";' +
            //     'scope.$ctrl.loginModel.password = "' + params.account.password + '";' +
            //     ''
            // );
            util.sendEvent( form.find('input[name = "login"]').get(0), 'input' );
            util.sendEvent( form.find('input[name = "password"]').get(0), 'input' );

            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                if (form.find('button:not([disabled]):visible').length > 0) {
                    clearInterval(checkLoginErrors);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button').get(0).click();
                        setTimeout(function() {
                            plugin.checkLoginErrors(params);
                        }, 2000);
                    });
                }
                if (counter > 10) {
                    clearInterval(checkLoginErrors);
                    provider.setError('button form not found');
                }
                counter++;
            }, 1000);
        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.alert-new.alert-new--danger');
        if (errors.length > 0 && errors.text() != '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }
        // old
        //plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);

        if (document.location.href != 'https://www.sclub.ru/user#cards'){
            provider.setNextStep('parse', function () {
                document.location.href = 'https://www.sclub.ru/user#cards';
            });
        }
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("Retrieving balance");
        browserAPI.log('Current URL: ' + document.location.href);
        var data = {};
        // Balance - Баланс баллов
        var balance = $('span.personal__points');
        balance = util.findRegExp(balance.text(), /([\-\d\.\,\s]+)/i);
        if (balance.length > 0) {
            balance = balance.replace(/\s/ig, '');
            browserAPI.log("Balance: " + balance);
            data.Balance = util.trim(balance);
        } else
            browserAPI.log("Balance not found");
        // Номер вашей карты
        var number = $('div.card_status').attr('data-card');
        if (number.length > 0) {
            browserAPI.log("Номер вашей карты: " + util.trim(number));
            data.CardNumber = util.trim(number);
        } else
            browserAPI.log("CardNumber not found");
        // Name
        var name = $('span.userName').text();
        if (name.length > 0) {
            name = util.beautifulName(util.trim(name));
            browserAPI.log("Name: " + name);
            data.Name = util.trim(name);
        } else
            browserAPI.log("Name is not found");
        // Скидка
        var discount = $('strong:has(span.personal__rub)').text();
        if (discount.length > 0) {
            browserAPI.log("Скидка: " + util.trim(discount));
            data.Discount = util.trim(discount);
        } else
            browserAPI.log("Discount not found");
        // Статус карты
        var сardStatus = util.findRegExp($('script:contains("CardStatus"):contains("Balance"):eq(0)').text(), /"CardStatus":(\d),/i);
        if (сardStatus.length > 0) {
            сardStatus = (сardStatus == 1) ? 'Активна': 'Неактивна';
            browserAPI.log("Статус карты: " + сardStatus);
            data.CardStatus = сardStatus;
        } else
            browserAPI.log("CardStatus not found");

        // not tested
        // Personal actions ("Персональные акции")
        /*var subAccounts = [];
        if (typeof (data.CardNumber) != 'undefined')
        $.ajax({
            url: 'https://sclub.ru/api/user/personal-offers',
            async: false,
            success: function (rewards) {
                browserAPI.log("parse rewards");
                rewards = $(rewards);
                //console.log("---------------- data ----------------");
                console.log(rewards);
                //console.log("---------------- data ----------------");

                if (typeof (rewards) != 'undefined')
                for (var reward in rewards) {
                    if (!rewards.hasOwnProperty(reward))
                        continue;
                    var id = rewards[reward].id;
                    var displayName = rewards[reward].title;
                    var mechanicText = rewards[reward].mechanicText;
                    var status = rewards[reward].status;
                    if (mechanicText)
                        displayName = displayName + " (" + mechanicText + ")";
                    var subAccount = {
                        "Code"        : 'sclubOffer' + id,
                        "DisplayName" : displayName,
                        "Balance"     : null
                    };

                    if (status.toLowerCase() == 'active') {
                        var alias = rewards[reward].alias;
                        if (alias && typeof (data.CardNumber) != 'undefined') {
                            // detail link
                            var detailsLink = "https://sclub.ru/api/offers/"+ alias + "?ean=" + data.CardNumber;
                            browserAPI.log("Link -> " + detailsLink);
                            $.ajax({
                                url: detailsLink,
                                async: false,
                                success: function (data) {
                                    data = $(data);
                                    //console.log("---------------- data ----------------");
                                    console.log(data);
                                    //console.log("---------------- data ----------------");
                                    if (typeof (data.endsAt) != 'undefined') {
                                        browserAPI.log("Exp date: " + data.endsAt);
                                        var date = new Date(data.endsAt + ' UTC');
                                        var unixtime = date / 1000;
                                        if ( date != 'NaN' && !isNaN(unixtime) ) {
                                            browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime );
                                            subAccount.ExpirationDate = unixtime;
                                        }// if ( date != 'NaN' && !isNaN(unixtime) )
                                    }// if (typeof (data.endsAt) != 'undefined')
                                }// success: function (data)
                            });
                        }// if (isset($alias, $response->cards[0]->ean))

                        subAccounts.push(subAccount);
                    }// if (status.toLowerCase() == 'active')
                }// for (var reward in rewards)
            }// success: function (rewards)
        });

        params.data.properties.SubAccounts = subAccounts;
        params.data.properties.CombineSubAccounts = 'false';*/

        provider.saveProperties(data);
        provider.complete();
    }
}
