<?php

//function CheckBalance2MEXICANAGO( &$http, $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties ){
//	$http->getCookieManager()->reset();
//	$http->GetURL("http://www.mexicanago.com/en");
//	if(!$http->ParseForm()) {
//		if ($http->FindPreg("/Conscientes de que Mexicana dispone de las fortalezas que requiere para salir adelante/ims")) {
//			$nErrorCode = ACCOUNT_PROVIDER_ERROR;
//			$sErrorMessage = 'Mexicana suspended all operations at noon CDT on August 28, 2010';
//			return;
//		}
//		return false;
//	}
//
//	$http->Form["email"] = $sLogin;
//	$http->Form["password"] = $sPassword;
//	if($IsRedirect)
//		return array(
//			"RequestMethod" => "POST",
//			"URL" => $http->FormURL,
//			"PostValues" => $http->Form
//		);
//	$http->PostForm();
//	$error = $http->FindSingleNode("//h5[@class='error' and contains(text(), 'start session.')]");
//	if(isset($error)){
//		$nErrorCode = ACCOUNT_INVALID_PASSWORD;
//		$sErrorMessage = $error;
//		return;
//	}
//	$error = $http->FindSingleNode("//h5[@class='error']/following::p[1]");
//	if(isset($error)){
//		$nErrorCode = ACCOUNT_INVALID_PASSWORD;
//		$sErrorMessage = $error;
//		return;
//	}
//	$text = $http->FindSingleNode("//span[contains(text(), 'GO Points')]/strong");
//	if(isset($text)){
//		$nBalance = $text;
//		$nErrorCode = ACCOUNT_CHECKED;
//	}
//	$text = $http->FindSingleNode("//span[contains(text(), 'GO Number')]/strong");
//	if(isset($text))
//		$arProperties["Number"] = $text;
//	$http->GetURL("http://www.mexicanago.com/tucuentago");
//	$nodes = $http->XPath->query("//td[contains(text(), 'Name(s)')]/following::td[1]");
//	if($nodes->length > 0)
//		$arProperties["Name"] = CleanXMLValue($nodes->item(0)->nodeValue);
//	$text = $http->FindSingleNode("//td[contains(text(), 'Last Name(s)')]/following::td[1]");
//	if(isset($text))
//		$arProperties["LastName"] = $text;
//	$text = $http->FindSingleNode("//td[contains(text(), 'Inscription Date')]/following::td[1]");
//	if(isset($text))
//		$arProperties["InscriptionDate"] = $text;
//	$text = $http->FindSingleNode("//th[contains(text(), 'GO Level')]/following::th[1]");
//	if(isset($text))
//		$arProperties["Level"] = $text;
//}
