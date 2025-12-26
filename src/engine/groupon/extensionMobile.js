var plugin = {
	autologin: {
		url: "about:blank",

        getStartingUrl: function (params){
            switch (params.account.login2) {
                case 'USA':
                    return 'http://touch.groupon.com/mydata';
                case 'UK':
                    return 'https://m.groupon.co.uk/mydata';
                case 'Australia':
                    return 'https://www.groupon.com.au/mydata';
                default:
                    return 'http://touch.groupon.com/mydata';
            }
        },

        start: function (params) {
			if(this.isLoggedIn(params))
                if(this.isSameAccount(params))
                    api.complete();
                else
                    this.logout(params);
            else
                this.login(params);
        },

        isLoggedIn: function (params) {
            /*switch(params.account.login2){
                case 'USA':
                case 'UK':*/
                    if($('input[name = "email_address"]').length > 0){
                        return false;
                    }

                    if($('#sign-in').length > 0){
                        return false;
                    }

                    if($('#sign-out').length > 0){
                        return true;
                    }

                    if($('#profile').length > 0){
                        return true;
                    }
/*                break;
            }*/

            throw "can't determine login state";
        },

        login: function () {
            var form = $('form');
            var button = $('#sign-in');
            if(form.length == 1 && button.length == 1){
                form.find('input[name = "email_address"]').val(params.login);
                form.find('input[name = "password"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.login) !== 'undefined')
                && ($('div:contains("' + params.login + '")').length > 0);
        },

        checkLoginErrors: function () {
            var error = $('script:contains("Groupon.TouchData.error")');
            if(error.length == 1){
                var matches = /Groupon\.TouchData\.error\s*=\s*(['"])(.+)(['"])\s*;/i.exec(error.text());
                if(matches){
                    api.error(matches[2].trim());
                }else{
                    api.error('unknown error');
                }
            } else {
                api.complete();
            }
        },

        logout: function (){
            api.setNextStep('toLoginPage', function () {
                document.location.href = "/logout";
            });
        },

        toLoginPage: function (params){
            api.setNextStep('login', function (){
                document.location.href = plugin.autologin.getStartingUrl(params);
            });
        },

        finish: function () {
            api.complete();
        }
	}
};