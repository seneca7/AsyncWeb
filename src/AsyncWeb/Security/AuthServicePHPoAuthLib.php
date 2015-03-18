<?php

namespace AsyncWeb\Security;
use \AsyncWeb\Security\AuthService;


class AuthServicePHPoAuthLib implements AuthService{
	public function SERVICE_ID(){ return "\\AsyncWeb\\Security\\AuthServicePHPoAuthLib";}
	public static $TABLE_ASSOC = "users_superconnection";
	
	protected $services = array();
	protected $info = array();
	public function registerService($name,\OAuth\OAuth2\Service\AbstractService $service,$info){
		$this->services[$name] = $service;
		$this->info[$name] = $info;
	}

	public function check(Array $data=array()){
		if($data){ return $this->checkData($data);}
		return $this->checkAuth();
	}
	public function loginForm(){
		$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
		$currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
		foreach($this->services as $service){
			$class= get_class($service);
			$class= str_replace("OAuth\\OAuth2\\Service\\","",$class);
			//$url = $currentUri->getRelativeUri() . '?go='.$class;
			
			$url = \AsyncWeb\System\Path::make(array("go"=>$class));
			
			$ret.="<a href='$url'>Login with ".$class."!</a>";
		}
		
		\AsyncWeb\Storage\Session::set("oauth_path",\AsyncWeb\Frontend\URLParser::getCurrent());
		return $ret;
		
	}
	protected static $DB_TABLE_USERS = "outer_user_access";
	protected function checkAuth(){
		\AsyncWeb\Frontend\URLParser::parse();
		if (!empty($_GET['code'])) {
			
			$provider = \AsyncWeb\Storage\Session::get("oauth_provider");
			if(!isset($this->services[$provider])){
				throw new \AsyncWeb\Exceptions\SecurityException("oAuth provider is not registered!");
			}
			
	
			// This was a callback request from google, get the token
			$service = $this->services[$provider];
			$service->requestAccessToken($_GET['code']);
			// Send a request with it
			$result = json_decode($service->request($this->info[$provider]), true);
			$result["id3"] = $result["id"];
			
			$email = $result["email"];
			if(!$email) $email = $result["id"];
			
			$id2 = md5(substr($provider."-".md5($email),0,32));
			unset($result["id"]);
			
			\AsyncWeb\DB\DB::u(AuthServicePHPoAuthLib::$DB_TABLE_USERS,$id2,$result);
			Auth::auth(array("userid"=>$id2),$this);

			if($path =  \AsyncWeb\Storage\Session::get("oauth_path")){
				\AsyncWeb\HTTP\Header::s("location",$path);
			}

			return true;
		}
		
		foreach($this->services as $name=>$service){
			if($_REQUEST["go"] == $name){
				\AsyncWeb\Storage\Session::set("oauth_provider",$name);
				$url = $service->getAuthorizationUri();
				header('Location: ' . $url);
				exit;
			}
		}
		return false;
	}
	
	protected function checkData($data){
		$row = \AsyncWeb\DB\DB::qbr(AuthServicePHPoAuthLib::$DB_TABLE_USERS,array("where"=>array("id2"=>$data["userid"]),"cols"=>array("id2")));
		if($row){
			return true;
		}
		throw new \AsyncWeb\Exceptions\SecurityException("User is not allowed to be logged as another user! 0x8310591");
	}
	
	
	
}
