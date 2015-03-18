<?php
namespace AsyncWeb\Menu;
use AsyncWeb\Cache\Cache;
use AsyncWeb\Storage\Session;
use AsyncWeb\Objects\Group;
use AsyncWeb\Article\CategoryArticle;
use AsyncWeb\System\Language;
use AsyncWeb\System\Path;
use AsyncWeb\Menu\MenuBuilder;

class MainMenu{
	public static $USE_MAIN_MENU = false;
	public static $PAGE = "main";// filtruj system pre ktory sa zobrazuje menu
	public static $editingmenu = false;
	public static $editingart = false;
	public static $showsep = true;
	public static $showsepfrom = 3;
	private static $menu = array();
	private static $leftMenu = array();
	private static $navigator = array();
	private static $builders = array();
	public static function registerBuilder($object,$priority=1){
		if(MainMenu::$built) throw new Exception("MainMenu was already build");
		if(is_a($object,'\AsyncWeb\Menu\MenuBuilder')){
			while(isset(MainMenu::$builders[$priority])){
				$priority++;
			}
			MainMenu::$builders[$priority] = $object;
		}
	}
	private static $built = false;
	private static $current = null;
	public static function build($rebuild=false){
		if(MainMenu::$built) return true;
		
		
		$edit = Session::get("MENU_editingmenu");
		if($edit !==null){
			MainMenu::$editingmenu = $edit;
		}
		$editart = Session::get("MENU_editingart");
		if($editart !==null){
			MainMenu::$editingart = $editart;
		}
		
		
		if(Group::is_in_group("MenuEditor")){
			
			if(isset($_REQUEST["seteditmenu"])){
				MainMenu::$editingmenu = $_REQUEST["seteditmenu"] || $_REQUEST["seteditmenu"];
				Session::set("MENU_editingmenu",MainMenu::$editingmenu);
				Cache::invalidate("menu");
				\AsyncWeb\HTTP\Header::s("reload",array("seteditmenu"=>""));exit;

			}
			
			if(!MainMenu::$editingmenu){
				MainMenu::$addLeftMenuItems["seteditmenu"] = array("path"=>"?seteditmenu=1","text"=>Language::get("Správa štruktúry"));
			}else{
				MainMenu::$addLeftMenuItems["setexportallmenu"] = array("path"=>"?exportallmenu=1","text"=>Language::get("Export celé menu"));
				//MainMenu::$addLeftMenuItems["setexportsubmenu"] = array("path"=>"?exportsubmenu=1","text"=>"Export submenu");
				MainMenu::$addLeftMenuItems["seteditmenu"] = array("path"=>"?seteditmenu=0","text"=>Language::get("Koniec správy štruktúry"));
			}
		}
		if(Group::is_in_group("HTMLEditor") || Group::is_in_group("PHPEditor")){
			if(isset($_REQUEST["seteditart"])){
				MainMenu::$editingart = $_REQUEST["seteditart"] || $_REQUEST["seteditart"];
				Session::set("MENU_editingart",MainMenu::$editingart);
				Cache::invalidate("menu");
				
				\AsyncWeb\HTTP\Header::s("reload",array("seteditart"=>""));exit;
				
			}
			
			if(MainMenu::$editingart){
				MainMenu::$addLeftMenuItems["seteditart"] = array("path"=>"?seteditart=0","text"=>Language::get("Koniec správy textov"));
			}else{
				MainMenu::$addLeftMenuItems["seteditart"] = array("path"=>"?seteditart=1","text"=>Language::get("Správa textov"));
			}
		}
		
		//if(class_exists("Timer1")){echo "asjfkf: \n";}
		
		$k1 = "MM_menu_l:".Language::getLang()."_u:".\AsyncWeb\Security\Auth::userId()."_p:".MainMenu::$PAGE."_e:".MainMenu::$editingmenu."-".MainMenu::$editingart;
		$k2 = "MM_leftMenu_l:".Language::getLang()."_u:".\AsyncWeb\Security\Auth::userId()."_p:".MainMenu::$PAGE."_e:".MainMenu::$editingmenu."-".MainMenu::$editingart;
		$k3 = "MM_navigator_l:".Language::getLang()."_u:".\AsyncWeb\Security\Auth::userId()."_p:".MainMenu::$PAGE."_e:".MainMenu::$editingmenu."-".MainMenu::$editingart;
		
		if($menu = Cache::get($k1,"menu")){
			if(is_array($menu)){
				MainMenu::$menu = $menu;
			}
		}
		if($menu = Cache::get($k2,"menu")){
			if(is_array($menu)){
				MainMenu::$leftMenu = $menu;
			}
		}
		if($menu = Cache::get($k3,"menu")){
			if(is_array($menu)){
				MainMenu::$navigator = $menu;
			}
		}

		if(!count(MainMenu::$menu) || $rebuild){
			ksort(MainMenu::$builders);
			foreach(MainMenu::$builders as $builder){
				//$builder->check();
				//if(class_exists("Timer1")){echo "asjfkf1: \n";}
				MainMenu::$menu = $builder->makeTopMenu(MainMenu::$menu);
				//var_dump(MainMenu::$menu);exit;
				//if(class_exists("Timer1")){echo "asjfkf2: \n";}
				MainMenu::$leftMenu = $builder->makeLeftMenu(MainMenu::$leftMenu);
				//if(class_exists("Timer1")){echo "asjfkf3: \n";}
				MainMenu::$navigator = $builder->makeNavigator(MainMenu::$navigator);
				//if(class_exists("Timer1")){echo "asjfkf4: \n";}
//				if(!MainMenu::$current) MainMenu::$current = $builder->getCurrent();
			}
			if(count(MainMenu::$menu)){
				Cache::set($k1,"menu",MainMenu::$menu);
				Cache::set($k2,"menu",MainMenu::$leftMenu);
				Cache::set($k3,"menu",MainMenu::$navigator);
				CategoryArticle::cacheArticleBuffer();
			}			
		}else{
			CategoryArticle::loadArticleBuffer();
			ksort(MainMenu::$builders);
			
		}		
		
		if(Group::is_in_group("MenuEditor")){
			if(MainMenu::$editingmenu){
				if(isset($_REQUEST["exportallmenu"]) || isset($_REQUEST["exportsubmenu"])){
					$export = "";
					foreach(MainMenu::$builders as $builder){
						if(isset($_REQUEST["exportallmenu"]) && $_REQUEST["exportallmenu"]){
							if(method_exists($builder,"export")){
								$export .= $builder->export();
							}
						}
						if(isset($_REQUEST["exportsubmenu"]) && $_REQUEST["exportsubmenu"]){
							if(method_exists($builder,"export")){
								$export .= $builder->export();
							}
						}
					}
					$out = "<menuitems>".$export."</menuitems>\n";
	\AsyncWeb\HTTP\Header::send("Cache-Control: public");
	\AsyncWeb\HTTP\Header::send("Content-Description: File Transfer");
	\AsyncWeb\HTTP\Header::send("Content-Disposition: attachment; filename=menu.xml");
	\AsyncWeb\HTTP\Header::send("Content-Type: text/xml");
	\AsyncWeb\HTTP\Header::send("Content-Transfer-Encoding: binary");
	\AsyncWeb\HTTP\Header::send("Content-length: ".strlen($out));/**/
					echo $out;
					exit;
				}
			}
		}
		
		
		MainMenu::$built = true;
		return true;
	}
	public static $showLangBarTexts = true;
	public static function makeLangBar(){
		MainMenu::build();
		$cur = MainMenu::getCurrent();
		
		$ret = '';
		foreach(Language::$SUPPORTED_LANGUAGES as $lang=>$arr){
			$path = MainMenu::makeLangPath($cur,$lang);
			$pathImg = $arr["img"];
			
			if(isset($clang) && $clang) $path = "http://".$clang["domain"].$clang["path"];
			if($ret) $ret.= '&nbsp;';
			if($pathImg && \AsyncWeb\IO\File::exists($pathImg)){
				$ret.='	 <a href="'.$path.'">';
				$ret.='<img src="'.$pathImg.'" class="flag" width="18" height="12" alt="'.Language::get("LB_$lang").'" title="'.Language::get("L__LB_$lang").'" />';
				$ret.='</a>';
			}
			if(MainMenu::$showLangBarTexts){
				$ret.='<a href="'.$path.'" class="current_lang">'.Language::get("L__LB_$lang").'</a>';
			}
		}
		return '    <div class="lang_menu">'.$ret.'</div>';
	}
	private static function makeLangPath(&$cur,$lang=false){
		$path = $cur["langs"][$lang];

		if($lang && $lang != Language::getLang()){
			$protocol = "http";
			if($_SERVER["SERVER_PORT"] == 443){
				$protocol = "https";
			}
			while(substr(Language::$SUPPORTED_LANGUAGES[$lang]["domain"],-1,1) == "/") Language::$SUPPORTED_LANGUAGES[$lang]["domain"] = substr(Language::$SUPPORTED_LANGUAGES[$lang]["domain"],0,-1);			
			$path = $protocol."://".Language::$SUPPORTED_LANGUAGES[$lang]["domain"].$path;
		}
		return $path;
	}
	public static function clearLangBar(){
		Language::$SUPPORTED_LANGUAGES = array();
	}
	private static function parseBrowserLang(){
		$langsStr=@$_SERVER['HTTP_ACCEPT_LANGUAGE'];
		$langs=explode(',',$langsStr);
		$langa=array();
		foreach ($langs as $lang){
			@ereg('([a-z]{1,2})(-([a-z0-9]+))?(;q=([0-9\.]+))?',$lang,$found);
			$code=htmlentities($found[1],ENT_QUOTES);
			$coef=sprintf('%3.1f',$found[5]?$found[5]:'1');
			$key=$coef.'-'.$code;
			$langa[$key]=array('code'=>$code,'coef'=>$coef);
		}
		//krsort($langa);
		return $langa;
	}
	public static function getDefaultLang(){
		
		foreach(Language::$SUPPORTED_LANGUAGES as $lang=>$arr){
			if($arr["domain"] == MainMenu::getDomain()){
				return $lang;
			}
		}
		$langs = MainMenu::parseBrowserLang();
		//var_dump($langs);exit;
		foreach($langs as $arr){
			foreach(Language::$SUPPORTED_LANGUAGES as $lang=>$d){
				if($arr["code"] == $lang) return $lang;
				if($arr["code"] == "cs") return "sk";
			}
		}
		foreach(Language::$SUPPORTED_LANGUAGES as $lang=>$d){
			return $lang;
		}
		return "en";
	}
	public static function registerLangBarLang($lang,$path,$img){
		Language::$SUPPORTED_LANGUAGES[$lang] = array("domain"=>$path,"img"=>$img);
	}
	public static function checkDomain(){
		foreach(Language::$SUPPORTED_LANGUAGES as $lang=>$arr){
			if($arr["domain"] == MainMenu::getDomain()){
				return true;
			}
		}
		$deflang = MainMenu::getDefaultLang();
		if(isset(Language::$SUPPORTED_LANGUAGES[$deflang])){
			$defaultDomain = Language::$SUPPORTED_LANGUAGES[$deflang]["domain"];
		}else{
			$defaultDomainarr = array_pop(Language::$SUPPORTED_LANGUAGES);;
			$defaultDomain = $defaultDomainarr["domain"];
		}
		
		if($defaultDomain){
			header("Location: http://".$defaultDomain);
			exit;
		}
		return false;
	}
	public static function getLangs(){
		return Language::$SUPPORTED_LANGUAGES;
	}
	public static function showMenuItem(&$row,$recursive=true,$class="menuitem",$subclass="submenu",$showsubmenu=false,$showeditor=true,$type="main"){
		$ret = '';
		
		if(!@$row["visible"]) return $ret;
		$ret.='<li class="'.$class.' '.@$row["class"].'">';
		if(!$row["type"]) $row["type"] = "category";
		if($row["type"] == "category" && !$row["text"]){$row["text"] = "?";}
		if($type == "left" || $type == "nav") $row["type"] = "category";
		switch($row["type"]){
			case "image": $ret.= '<a href="'.$row["path"].'"><img src="'.$row["img"].'" width="'.$row["imgwidth"].'" height="'.$row["imgheight"].'" alt="'.$row["imgalt"].'" title="'.($row["text"]).'" /></a>';break;
			case "text":$ret.= '<span class="menutext">'.($row["text"]).'</span>';break;
			case "category":$ret.= '<a href="'.$row["path"].'"><span class="menutext">'.($row["text"]).'</span></a>';break;
			case "src":$ret.= '<a href="'.$row["path"].'"><span class="menutext">'.($row["text"]).'</span></a>';break;
		}

		$sub = "";
		$i = 0;
		
		if($recursive){
			if(isset($row["submenu"]) && is_array($row["submenu"])){
				ksort($row["submenu"]);
				foreach($row["submenu"] as $k=>$row1){$i++;
					if($k=="submenu") continue;
					$sub.=MainMenu::showMenuItem($row1,$recursive,$class,$subclass,$showsubmenu,$showeditor,$type);
				}
				if($sub){
					$style = ' style="display:none"';
					if($showsubmenu) $style = '';
					$ret.='<ul class="'.$subclass.'"'.$style.'>'.$sub.'</ul>';
				}
			}
		}
		$ret.='</li>';
		
		return $ret;
	}
	public static function showNavigatorItem(&$row){
		return MainMenu::showLeftMenuItem($row,false,"nav","",true,false,"nav");
	}
	public static function showLeftMenuItem(&$row,$recursive=true,$class="menuitem",$subclass="submenu"){
		return MainMenu::showMenuItem($row,$recursive,$class,$subclass,true,false,"left");
	}
	public static function checkAuth(&$row){
		if(!$row) return false;
		if(!Group::is_in_group("MenuEditor")){
			switch($row["logintype"]){
				case "logged": 
					if(!\AsyncWeb\Security\Auth::userId()){return false;}
				break;
				case "notlogged": 
					if(\AsyncWeb\Security\Auth::userId()) {return -1;}
				break;
				case "all": 
				break;
			}
			if(@$row["group"]){
				if(!Group::isInGroupId($row["group"])) return false;
			}
		}
		return true;
	}
	public static function showTopMenu(){
		$key = "TopMenuMain_l:".Language::getLang()."_u:".\AsyncWeb\Security\Auth::userId()."_p:".MainMenu::$PAGE;
		
		if($menu = Cache::get($key,"menu")){
				return $menu;
		}
	
		MainMenu::build();
		if(!MainMenu::$menu) return false;
		$ret= '<div id="menu1"><ul id="menu" class="nav navbar-nav">';
		$sub = "";
		$i = 0;
		
		ksort(MainMenu::$menu);
		foreach(MainMenu::$menu as $k=>$row){$i++;
			if($k=="submenu") continue;
			$t = MainMenu::showMenuItem($row);
			if($t){
				if(MainMenu::$showsep && $i>MainMenu::$showsepfrom) $sub.='<li>|</li>';
				$sub.=$t;
			}
			$lrow = $row;
		}
		$ret.= $sub.'</ul></div><div class="menu1_clear"></div>';
		
		
		$x=Cache::set($key,"menu",$ret);

		return $ret;
	}
	public static function installDefaultValues(){
			$path = "main";$id2 = md5(MainMenu::$PAGE."-".$path);Language::set($k="L__$path",$path);$path=$k;
			Language::set($kt=md5(uniqid()),"Main page");
			Language::set($kd=md5(uniqid()),"");
			Language::set($kk=md5(uniqid()),"");
			Language::set($kv=md5(uniqid()),"1");
			Language::set($ktext=md5(uniqid()),"Main page");
			\AsyncWeb\DB\DB::u("menu",$id2,array("page"=>MainMenu::$PAGE,"path"=>$path,"parent"=>null,"order"=>"1000","text"=>$ktext,"domain"=>MainMenu::getDomain(),"visible"=>$kv,"style"=>"standard","type"=>"category","logintype"=>"all","title"=>$kt,"description"=>$kd,"keywords"=>$kk));
			Cache::invalidate("menu");
			MainMenu::$built = false;
	}
	public static function clearMenu(&$menu,$id2,$isParent=false){
		if($menu)
		foreach ($menu as &$item){
			$cur = false;
			if(!isset($item["submenu"])) continue;
			if($isParent){
				unset($item["submenu"]);
			}else{
				if($item["id2"] != $id2 && !MainMenu::findMenuItemById($item["submenu"],$id2)){
					unset($item["submenu"]);
				}
				if($item["id2"] == $id2){
					$cur = true;
				}
				MainMenu::clearMenu($item["submenu"],$id2,$cur);
			}
		}
	}
	private static $addLeftMenuItems = array();
	public static function registerLeftMenuItem($item){
		MainMenu::$addLeftMenuItems[] = $item;
	}
	public static function showLeftMenu(){
		$cur = MainMenu::getCurrent();
		/*
		if(!$cur){
			// 404
			$uri = $_SERVER["REQUEST_URI"];
			if(($pos = strpos($uri,"?"))!==false){
				$uri = substr($uri,0,$pos);
			}
			if($uri=="/"){
				//var_dump(MainMenu::$menu);exit;
				MainMenu::installDefaultValues();
				
			}
			return;
		}
		/**/
		$key = "LeftMenuMain_l:".Language::getLang()."_c:".$cur["id2"]."_u:".\AsyncWeb\Security\Auth::userId()."_p:".MainMenu::$PAGE;
		if($menu = Cache::get($key,"menu")){
				return $menu;
		}

		MainMenu::clearMenu(MainMenu::$leftMenu,$cur["id2"]);
		
		
		
		
		$ret = '<ul id="menu2">';
		
		ksort(MainMenu::$leftMenu);
		foreach(MainMenu::$leftMenu as $row){
			$ret.= MainMenu::showLeftMenuItem($row);
			
		}
		
		
		if(Group::is_in_group("HTMLEditor") && (MainMenu::$editingmenu || MainMenu::$editingart)){
			$ret.='<li><a href="'.Path::make(array("insert_data_articles_html"=>"1","newhtmlarticle"=>"1")).'">'.Language::get("Nový HTML článok").'</a></li>';
		}
		if(Group::is_in_group("PHPEditor") && (MainMenu::$editingmenu || MainMenu::$editingart)){
			$ret.='<li><a href="'.Path::make(array("insert_data_articles_php"=>"1","newphparticle"=>"1")).'">'.Language::get("Nový PHP článok").'</a></li>';
		}
		
		if(MainMenu::$editingmenu){
			if(Group::is_in_group("MenuEditor")){
				$ret.='<li><a href="'.Path::make(array("dbmenu4_edit___UPDATE1"=>"1","dbmenu4_edit___ID"=>$cur["id"],"editmenu"=>"1")).'">'.Language::get("Edituj menu").'</a></li>';
				$ret.='<li><a onclick="confirm(\''.Language::get("Naozaj chcete zrušiť menu?").'\')?ret=true:ret=false;return ret;" href="'.Path::make(array("dbmenu4_edit___DELETE"=>"1","dbmenu4_edit___ID"=>$cur["id"],"deletemenu"=>"1")).'">'.Language::get("Zruš menu").'</a></li>';
			}
		}
		
		foreach(MainMenu::$addLeftMenuItems as $item){
			$ret.='<li><a href="'.$item["path"].'">'.$item["text"].'</a></li>';
		}
		
		$ret .='</ul>';
		
		Cache::set($key,"menu",$ret);
		
		return $ret;
	}
	public static function showNavigator(){
		$ret = '<ul id="menuNav">';
		$nav = "";
		
		foreach(MainMenu::$navigator as $row){
			$item = MainMenu::showNavigatorItem($row);
			if($item){
				if($nav){$nav .= '<li>&gt;</li>';}
				$nav .= $item;
			}
		}
		$ret .= $nav;
		$ret.= '</ul>';
		return $ret;
	}
	public static $CATEGORY_TAG_NAME = "cat";
	public static function getCurrent($dbg=false){
	
		$dbgi = 0;
		//$dbg= true;
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		MainMenu::build();
		
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		
		$url = \AsyncWeb\Frontend\URLParser::parse();
		if(isset($url["tmpl"][MainMenu::$CATEGORY_TAG_NAME])){
			$path = $url["tmpl"][MainMenu::$CATEGORY_TAG_NAME];
		}else{
			$path = "main";
		}
//		$path = urldecode($_SERVER["REQUEST_URI"]);
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		//var_dump(MainMenu::$menu);
		//var_dump($path);
		$ret= MainMenu::findMenuItem(MainMenu::$menu,$path);
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		if(!$ret){
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
			$pos = strpos($path,'?');
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
			if($pos){
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
				$path = substr($path,0,$pos);
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
				$ret= MainMenu::findMenuItem(MainMenu::$menu,$path);
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
			}
		}
		
		if($path == "main" && !$ret){
			return "0";
		}
		
		if(!$ret && substr($path,-1) != "/"){
			$path.="/";
			$ret= MainMenu::findMenuItem(MainMenu::$menu,$path); 
		}
		
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:gettitle:".($dbgi++).":\n";}
		if(isset($ret["title"])){
			$title = Language::get($ret["title"],array(),false,$dbg);
		}else{
			$title = "";
		}
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:settitletoglobal:".($dbgi++).":\n";}
		if(isset($ret["title"])) $GLOBALS["TMPL_title"] = $title;
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		if(isset($ret["description"])) $GLOBALS["TMPL_description"] = Language::get($ret["description"]);
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		if(isset($ret["keywords"])) $GLOBALS["TMPL_keywords"] = Language::get($ret["keywords"]);
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		MainMenu::$current = $ret;
		if($dbg){echo Timer1::show()."MainMenu:getCurrent:".($dbgi++).":\n";}
		
		if($ret){
			if(!MainMenu::$builderschecked){MainMenu::$builderschecked = true;
				foreach(MainMenu::$builders as $builder){
					$builder->check();
				}
			}
		}
		return $ret;
	}
	private static $builderschecked = false;
	public static function find($findMe){
		return MainMenu::findMenuItem(MainMenu::$menu,$findMe);
	}
	private static function findMenuItem(&$menu,$findMe){
		if($menu)
		foreach($menu as $k=>$v){
			if(isset($v["path"]) && $v["path"] == $findMe) return $v;
			if(isset($v["id3"]) && $v["id3"] == $findMe) return $v;
			if(isset($v["submenu"])){
				if($ret = MainMenu::findMenuItem($v["submenu"],$findMe)){
					return $ret;
				}
			}
		}
	}
	private static function findMenuItemById(&$menu,$findMe){
		foreach($menu as $k=>$v){
			if(isset($v["id2"]) && $v["id2"] == $findMe) return $v;
			if(isset($v["submenu"])){
				if($ret = MainMenu::findMenuItemById($v["submenu"],$findMe)){
					return $ret;
				}
			}
		}
	}
	public static function getCurrentId(){
		$cur = MainMenu::getCurrent();
		if($cur){
			return $cur["id2"];
		}
		return false;
	}
	public static function getDomain(){
		return $_SERVER["HTTP_HOST"];
	}
	public static function generateSubmenuArticle($menu){
		$menu = $menu["id2"];
		
		$cur = MainMenu::getCurrent();
		$ret = '';
		
		if(isset($cur["submenu"])){
			foreach($cur["submenu"] as $row){
				$ret.=MainMenu::showLeftMenuItem($row,false,"art_menuitem");
			}
		}
		
		if(!$ret) return false;
		return '<h1>'.Language::get($cur["text"]).'</h1><ul class="art_menu">'.$ret.'</ul>';
	}
}

