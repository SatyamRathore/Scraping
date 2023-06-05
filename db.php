<?php

	// header('Content-type: text/plain; charset=utf-8');
	define('DB_HOST','localhost');
	define('DB_USER','root');
	define('DB_PASSWORD','');
	define('DB_NAME','upwork_michele_allvetsupply');

	global $db;
	
	
	if(! is_dir("./Logs")){
		mkdir("./Logs");
	}
	
	// code to wirte log globaly
	$log_file = fopen("./logs/".date('Y-m-d-H-i').".log", "w");
	function writeToLog($str){
		global $log_file;
		echo $str;
		fwrite($log_file, $str);		
	}
	

  function db_connection(){
		global $db;
		if(! check_db() ){
			$db=new mysqli(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
			if($db->connect_errno){
				echo $db->connect_errno . ':' . $db->connect_error;
				exit();
			}
		}
		return $db;
	}

	function check_db(){
		global $db;
		if(is_object($db) && is_a($db,'mysqli')){
			return true;
		}else{
			return false;
		}
	}


	function sanitize($value){
		global $db;
		$db=db_connection();
		$value=$db->real_escape_string($value);
		return $value;
	}

  function exec_query($query){
    global $db;
    if(! check_db()){
      $db=db_connection();
    }
    $db->query($query);
    if($db->errno){
      echo $db->errno . ':' . $db->error . '  ' . $query;
      exit();
    }
  }

  function close(){
    global $db;
    if(check_db()){
      $db->close();
    }
  }

  function get_row($query){
    global $db;
		$db=db_connection();
    $data=array();
    if($result=$db->query($query)){
      if($result->num_rows>0){
        $data=$result->fetch_row();
      }
      $result->close();
    }else{
      echo $db->errno  .  ':'  .  $db->error  .  '   '  .  $query;
      exit();
    }
    return $data;
  }


  function get_rows($query){
    global $db;
		$db=db_connection();
    $data=array();
    if($result=$db->query($query)){
      if($result->num_rows>0){
        while($row=$result->fetch_row()){
          $data[] = $row;
          unset($row);
        }
      }
      $result->close();
    }else{
      echo $db->errno  .  ':'  .  $db->error  .  '   '  .  $query;
      exit();
    }
    return $data;
  }

	function change_privateproxy(){
		
		global $username,$session,$password,$proxy,$is_proxy,$ppProxyPassword,$cookie_file;
		
		
		if(file_exists($cookie_file)){
			if(unlink($cookie_file)){
				echo("Cookie Successfully Deleted ");
			}			
		}
		else{
			echo("Cookie Not Exist ");
		}
		
		if($is_proxy == 'privateproxy'){
		
			$username = PPUSERNAME;
			$password = PPPASSWORD;
			$proxy  	= PPPROXYURL;
			$session 	= mt_rand();
			
			echo ("New Proxy: PrivateProxy:$session; ");
			
			$ppProxyPassword = "$username:$password";
		}
	}