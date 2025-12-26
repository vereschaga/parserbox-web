var plugin = {
    hosts: {'www.sky.com.br': true, 'sky.com.br': true},

    getStartingUrl: function (params) {
        return 'https://www.sky.com.br/vivasky/meu-viva-sky/extrato-de-pontos';
    },
	getLogOutUrl: function (params) {
        return 'https://www.sky.com.br/institucional/Cadastro/logout.aspx';
    },


    start: function (params) {
        browserAPI.log("start");
		plugin.isLoggedIn(params);
    },
	
	isLoggedInAttempt:0,
	
    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
		
		if(plugin.isLoggedInAttempt<10){
			setTimeout(function(){
				plugin.isLoggedInAttempt++;
				plugin.isLoggedInHandler(params);
			},500);
		}else{
			browserAPI.log("Cant get accaunt state");
			provider.setError("Cant get accaunt state");
			throw "Cant get accaunt state";
		}
    },
	
	isLoggedInHandler: function (params) {
        browserAPI.log("isLoggedInHandler:"+plugin.isLoggedInAttempt);
		
		var signin = $("#ContentPlaceHolder1_btnLogar");
		var signout = $("#header_ibDeslogar");
		
		if(signin.length>0 || signout.length>0){
			
			
			if (signin.length > 0) {
				browserAPI.log("not LoggedIn");
				plugin.login(params);
			}else if(signout.length > 0){
				browserAPI.log("LoggedIn");
				plugin.isSameAccount(params);
			}else{
				browserAPI.log("Cant get accaunt state");
				provider.setError("Cant get accaunt state");
				throw "Cant get accaunt state";
			}
		}else{
			plugin.isLoggedIn(params);
		}
    },
	
	isSameAccountAttenpt:0,
	
    isSameAccount: function (params) {
		var account = params.account;
		browserAPI.log("isSameAccount");
		
		if(plugin.isSameAccountAttenpt<10){
			setTimeout(function(){
				plugin.isSameAccountAttenpt++;
				plugin.isSameAccountHandler(params);
			},500);
		}else{
			browserAPI.log("Cant get account information");
			provider.setError("Cant get account information");
			throw "Cant get account information";
		}
    },
	
	isSameAccountHandler: function(params) {
		var account = params.account;
		
		browserAPI.log("isSameAccountHandler:"+plugin.isSameAccountAttenpt);
		
		if($(".signin").length>0){
			var name = $(":contains('Olá, ')").children('strong').text().toLowerCase().replace(",", "");
			browserAPI.log("name: " + name);
			if((typeof(account.properties) != 'undefined')
				&& (typeof(account.properties.Name) != 'undefined')
				&& (account.properties.Name != '')
				&& (name == account.properties.Name.toLowerCase()))
					plugin.loginComplete(params);
			else
					plugin.logout();
		}else{
			 plugin.isSameAccount(params);
		}
	},
	
    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('logout2');
		document.location.href = plugin.getLogOutUrl();
    },
	
	logout2: function () {
        browserAPI.log("logout2");
        provider.setNextStep('gotoStart');
		$("#ContentPlaceHolder1_ImageButton_LogOff").click();
    },
	
	gotoStart: function () {
        browserAPI.log("gotoStart");
        provider.setNextStep('start');
		document.location.href = plugin.getStartingUrl();
    },
	
	loginAttempt:0,
	
    login: function (params) {
		browserAPI.log("login");
		
		if(plugin.loginAttempt<10){
			setTimeout(function(){
				plugin.loginAttempt++;
				plugin.loginHandler(params);
			},500);
		}else{
			browserAPI.log("Login form not found");
			provider.setError('Login form not found');
			throw 'Login form not found';
		}
    },
	
	loginHandler: function (params) {
        browserAPI.log("loginHandler:"+plugin.loginAttempt);
		var form = $('#form1');
		if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
			
			form.find('input[name = "ctl00$ContentPlaceHolder1$txtLogin"]').val(params.account.login);
			
			form.find('input[name = "ctl00$ContentPlaceHolder1$txtSenha"]').val(params.account.password);
			
			provider.setNextStep('checkLoginErrors');
			
			setTimeout(function(){
				$('#ContentPlaceHolder1_btnLogar').click();
			},500);
			
			setTimeout(function(){
				plugin.checkLoginErrors();
			},1000);
		} else {
			plugin.login(params)
		}
    },
	
	checkLoginErrorsAttempt:0,
	
    checkLoginErrors: function (params) {
		browserAPI.log("checkLoginErrors");
		if(plugin.checkLoginErrorsAttempt<10){
			setTimeout(function(){
				plugin.checkLoginErrorsAttempt++;
				plugin.checkLoginErrorsHandler(params);
			},500);
		}else{
			browserAPI.log("Cant get login result");
			provider.setError('Cant get login result');
			throw 'Cant get login result';
		}
    },
	
	checkLoginErrorsHandler: function (params) {
        browserAPI.log("checkLoginErrorsHandler:"+plugin.checkLoginErrorsAttempt);
		
		var errors = $(".error:visible");
		var signout = $("#header_ibDeslogar");
		
		if (signout.length > 0 || errors.length > 0) {
			if (errors.length > 0){
				var err = [];
				errors.each(function(k,v){
					err[err.length] = $(this).text();
				});
				browserAPI.log(err.join(' | '));
				provider.setError(err.join(' | '));
				throw err.join(' | ');
			}else{
				plugin.loginComplete(params);
			}
		} else {
			plugin.checkLoginErrors(params)
		}
    },
	
	loginComplete: function(params){
		browserAPI.log('This accaunt logged now');
		provider.complete();
	},
}