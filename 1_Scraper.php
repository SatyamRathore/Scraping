<?php

	define("PPUSERNAME","y1sin");
	define("PPPASSWORD","ufji9db3");
	define("PPPROXYURL","169.197.83.75:7336");

	require_once('./db.php');

	$db = db_connection();

	$HTMLFiles 			 = "HTMLFiles";
	$Category1Folder = "$HTMLFiles/Category1";
	
	$CookieFolder = "Cookie";
	
	if(!is_dir($HTMLFiles))											{ 	mkdir($HTMLFiles);										}
	if(!is_dir($Category1Folder))								{ 	mkdir($Category1Folder);							}
	if(!is_dir($CookieFolder))									{ 	mkdir($CookieFolder);									}

	$cookie_file = "$CookieFolder/Cookie.txt";

	if(isset($argv[1]) && $argv[1] == 'privateproxy'){
		$is_proxy = 'privateproxy';
		change_privateproxy();
	}

	$homepageurl = "https://www.allvetsupply.com/";
	
	$homepagefilename = "$Category1Folder/Category1.html";
	
	if(is_file($homepagefilename)){
		writeToLog("File Exists...");
		$homepagehtml = file_get_contents($homepagefilename);
	}else{
		$homepagehtml = home_page_download($homepageurl,$homepagefilename);
	}
	
	Category1Parse($homepagehtml);
	
	function home_page_download($homepageurl,$homepagefilename){
	
		global $db,$proxy,$is_proxy,$cookie_file,$ppProxyPassword,$Category1Folder;

		$ch = curl_init();
		
		curl_setopt($ch, 	CURLOPT_URL,$homepageurl);
		curl_setopt($ch,	CURLOPT_FOLLOWLOCATION,	1);
		curl_setopt($ch, 	CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, 	CURLOPT_SSL_VERIFYHOST, false);
		
		if($is_proxy == 'privateproxy'){
			curl_setopt($ch,  CURLOPT_PROXY, "$proxy");				
			curl_setopt($ch,  CURLOPT_PROXYUSERPWD, $ppProxyPassword);
		}
		
		curl_setopt($ch,	CURLOPT_RETURNTRANSFER,	1);
		curl_setopt($ch,	CURLOPT_BINARYTRANSFER,	1);			
		curl_setopt($ch,	CURLOPT_ENCODING,	'gzip');
		curl_setopt($ch, 	CURLOPT_CONNECTTIMEOUT, 20); 
		curl_setopt($ch, 	CURLOPT_TIMEOUT, 20);
		curl_setopt($ch,	CURLOPT_COOKIEJAR,"./{$cookie_file}");
		curl_setopt($ch,	CURLOPT_COOKIEFILE,"./{$cookie_file}");
		curl_setopt($ch,	CURLOPT_HEADER,0);
		curl_setopt($ch,	CURLOPT_HTTPHEADER, array(

			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: en-US,en;q=0.9',
			'Cache-Control: no-cache',
			'Pragma: no-cache',
			'Sec-Ch-Ua: "Google Chrome";v="113", "Chromium";v="113", "Not-A.Brand";v="24"',
			'Sec-Ch-Ua-Mobile: ?0',
			'Sec-Ch-Ua-Platform: "Windows"',
			'Sec-Fetch-Dest: document',
			'Sec-Fetch-Mode: navigate',
			'Sec-Fetch-Site: none',
			'Sec-Fetch-User: ?1',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36'
			
		));
		$result			=	curl_exec($ch);								
		$httpCode		=	curl_getinfo($ch,	CURLINFO_HTTP_CODE);
		
		writeToLog("Home Page http: $httpCode ");
		
		if($httpCode	==	200){
			
			if( (stripos($result,'/distil_r_captcha.html') !== false) || (stripos($result,'<div id="px-captcha">') !== false) || (stripos($result,'Access to this page has been denied') !== false) || (stripos($result,'http://auth.contentkeeper.com/login.html') !== false)||(stripos($result,'<title>Welcome To Zscaler Directory Authentication</title>') !== false)){
				writeToLog('Captch found ');
			
				if($is_proxy == 'privateproxy'){
					change_privateproxy();
				}
				
				return home_page_download($homepageurl,$homepagefilename);		
			}
			else{
				file_put_contents($homepagefilename,$result);			
				return $result;				
			}
			
		}else{

			if ($is_proxy == 'privateproxy'){
				change_privateproxy();
			}
			
			return home_page_download($homepageurl,$homepagefilename);			
		}
	}

	function Category1Parse($homepagehtml){
		
		global $db;
		
		preg_match('#<li><a href="index.html" title="Home">Home</a></li>(.*?)<div class="clear"></div>#is',$homepagehtml,$main);
		
		if(preg_match_all('#<a href="(.*?)" class="sub-menu" title="(.*?)">(.*?)<img src="(.*?)" class="mobile-menu-arrow" /></a>(.*?)<ul>(.*?)</ul>#is',$main[1],$details)){
			
			for($i=0; $i<count($details[1]); $i++){
				
				$Category1 = "";
				
				if(isset($details[2][$i]))		{		$Category1     =  trim($db->real_escape_string(html_entity_decode(strip_tags($details[2][$i]))));		}
				
				writeToLog("Category: $Category1...");
				
				preg_match_all('#<div class="block"><a href="(.*?)" title="(.*?)">(.*?)</a></div>#is',$details[6][$i],$cate2);
				
				for($j=0; $j<count($cate2[1]); $j++){
					
					$Category2URL  =  "";
					$Category2     =  "";
					
					if(isset($cate2[1][$j]))		{		$Category2URL  =   "https://www.allvetsupply.com/".trim($cate2[1][$j]);		}
					if(isset($cate2[3][$j]))		{		$Category2     =  trim($db->real_escape_string(html_entity_decode(strip_tags($cate2[3][$j]))));		}

					if($Category1 != 'Embryo Transfer'){
						
						$db->query("SET NAMES UTF8;");
				
						$Insert = "INSERT IGNORE INTO tbl_category1(Category1, Category2, Category2URL)VALUES('$Category1','$Category2','$Category2URL')";
						
						if(!$db->query($Insert)){
							writeToLog($db->error.PHP_EOL);
						}
						
						
					}else if($Category1 = 'Embryo Transfer'){
						
						$Category1 = "Embryo Transfer";
						$Category2 = "Embryo Transfer";
						$Category2URL = "https://www.allvetsupply.com/emtraneq.html";
						
						$db->query("SET NAMES UTF8;");
				
						$Insert = "INSERT IGNORE INTO tbl_category1(Category1, Category2, Category2URL)VALUES('$Category1','$Category2','$Category2URL')";
						
						if(!$db->query($Insert)){
							writeToLog($db->error.PHP_EOL);
						}
						
					}
				}
				
				writeToLog("Records Insert in tbl_category1".PHP_EOL);
				
			}

		}
		
		if(preg_match('#<a href="(.*?)" class="sub-menu" title="Vet Instruments">(.*?)<img src="(.*?)" class="mobile-menu-arrow" /></a>(.*?)<ul>(.*?)</ul>#is',$main[1],$details2)){
			
			$Category1 = '';
			
			if(isset($details2[2]))		{			$Category1  =  trim($db->real_escape_string(html_entity_decode(strip_tags($details2[2]))));		}
			
			writeToLog("Category1: $Category1...");
			
			preg_match_all('#<div class="block"><a href="(.*?)" title="(.*?)">(.*?)</a></div>#is',$details2[5],$cates2);

			for($k=0; $k<count($cates2[1]); $k++){
				
				$Category2URL  =  "";
				$Category2     =  "";
				
				if(isset($cates2[1][$k]))		{		$Category2URL  =   "https://www.allvetsupply.com/".trim($cates2[1][$k]);		}
				if(isset($cates2[3][$k]))		{		$Category2     =  trim($db->real_escape_string(html_entity_decode(strip_tags($cates2[3][$k]))));		}
				
				$db->query("SET NAMES UTF8;");
			
				$Insert3 = "INSERT IGNORE INTO tbl_category1(Category1, Category2, Category2URL)VALUES('$Category1','$Category2','$Category2URL')";
				
				if(!$db->query($Insert3)){
					writeToLog($db->error.PHP_EOL);
				}
				
			}
			
			writeToLog("$k Records Insert in tbl_category1".PHP_EOL);
			
		}
		
		if(preg_match('#<li><a href="(.*?)" class="sub-menu" title="Diagnostic Equipment">(.*?)</a></li>#is',$main[1],$detai)){

			$Category1 = '';
			
			if(isset($detai[2]))				{		$Category1     =  trim($db->real_escape_string(html_entity_decode(strip_tags($detai[2]))));		}
			if(isset($detai[1]))				{		$Category1URL  =   "https://www.allvetsupply.com/pregdet.html";																}
			
			writeToLog("Category: $Category1...");
			
			$Insert2 = "INSERT IGNORE INTO tbl_category1(Category1, Category2, Category2URL)VALUES('$Category1','$Category1','$Category1URL')";
			
			if(!$db->query($Insert2)){
				writeToLog($db->error.PHP_EOL);
			}else{
				writeToLog("Records Insert in tbl_category1");
			}
		}
		
		
	}

?>