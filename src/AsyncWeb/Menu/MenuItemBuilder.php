<?php
namespace AsyncWeb\Menu;

class MenuItemBuilder{
	public static function get(){//get($id2,$text,$path,$visible="1",$langs=array(),$run="",$fa=""){
		$id2="";$text="";$path="";$visible="1";$langs=array();$run="";$fa="";$submenu = array();$type = "category";
		$args = func_get_args();
		$i = 0;
		while(($arg = array_shift($args)) !== null){$i++;
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\ID")){
				$id2 = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Text")){
				$text = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Path")){
				$path = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Visibility")){
				$visible = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Langs")){
				if(is_array($arg->get())){
					$langs = $arg->get();
				}
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Execute")){
				$run = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\FA")){
				$fa = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Submenu")){
				$submenu = $arg->get();
			}else
			if(is_a($arg,"\\AsyncWeb\\Menu\\Builder\\Type")){
				$type = $arg->get();
			}else{
				switch($i){
					case "1": 
						$id2 = $arg;
					break;
					case "2": 
						$text = $arg;
					break;
					case "3": 
						$path = $arg;
					break;
					case "4": 
						$visible = $arg;
					break;
					case "5": 
						$langs = $arg;
					break;
					case "6": 
						$run = $arg;
					break;
					case "7": 
						$fa = $arg;
					break;
				}
			}
			
		}
		
		if(!$langs){
			foreach(MainMenu::getLangs() as $k=>$v){
				$langs[$k] = $path;
			}
		}
		return array("id2"=>$id2,"path"=>$path,"title"=>$text,"text"=>$text,"type"=>$type,"visible"=>$visible,"class"=>"","logintype"=>"all","group"=>null,"run"=>$run,"style"=>"standard","id"=>"","langs"=>$langs,"fa"=>$fa,"submenu"=>$submenu);
	}
}
