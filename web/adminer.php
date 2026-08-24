<?php
/**
 * AdminNeo - Powerful database manager in a single PHP file
 * v5.7.0
 *
 * Compiled with
 * drivers:   mysql
 * languages: de, en, es, ru
 * themes:    default-red
 * config:    no
 *
 * @link https://www.adminneo.org/
 *
 * @author Peter Knut
 * @author Jakub Vrana (https://www.vrana.cz/)
 *
 * @copyright 2007-2025 Jakub Vrána
 * @copyright 2024-2025 Peter Knut
 *
 * @license Apache License, Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * @license GNU General Public License, version 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */namespace
AdminNeo;
require_once 'db.php';
require_once 'auth_user.php';

if (!isset($_SESSION['admin'])){
    http_response_code(401);
    header('Location: .');
    exit;
}

use
Exception;use
stdClass;use
PDO;use
PDOStatement;use
mysqli;use
mysqli_result;abstract
class
Plugin{protected$admin;protected$config;protected$settings;protected$locale;function
inject($ya,Config$Sb,Settings$O,Locale$qg){$this->admin=$ya;$this->config=$Sb;$this->settings=$O;$this->locale=$qg;}}abstract
class
Origin
extends
Plugin{private$errors=[];private
static$instance=null;static
function
create(array$Sb=[],array$Bi=[]){if(self::$instance)die("Admin instance already exists.\n");$ya=new
static();if(!$Sb&&file_exists("adminneo-config.php")){$Sb=include_once("adminneo-config.php");if(!is_array($Sb)){$Sb=[];$ig="href=https://github.com/adminneo-org/adminneo#configuration ".target_blank();$ya->addError(lang(0,"<b>adminneo-config.php</b>")." <a $ig>".lang(1)."</a>");}}$Sb=new
Config($Sb);$O=new
Settings($Sb);if(!$Bi&&file_exists("adminneo-plugins.php")){$Bi=include_once("adminneo-plugins.php");if(!is_array($Bi)){$Bi=[];$ig="href=https://github.com/adminneo-org/adminneo#plugins ".target_blank();$ya->addError(lang(0,"<b>adminneo-plugins.php</b>")." <a $ig>".lang(1)."</a>");}}self::$instance=$Bi?new
Pluginer($ya,$Bi):$ya;$ya->inject(self::$instance,$Sb,$O,Locale::get());foreach($Bi
as$Ai)$Ai->inject(self::$instance,$Sb,$O,Locale::get());return
self::$instance;}static
function
get(){if(!self::$instance)die("Admin instance not found. Create instance by Admin::create() method at first.\n");return
self::$instance;}protected
function
__construct(){}function
getConfig(){return$this->config;}function
getSettings(){return$this->settings;}abstract
function
getOperators();function
getLikeOperator(){return
Driver::get()->getLikeOperator();}function
getRegexpOperator(){return
null;}function
init(){}function
addError($i){$this->errors[]=$i;}function
getErrors(){return$this->errors;}abstract
function
getServiceTitle();function
getCredentials(){$N=$this->config->getServer(SERVER);return[$N?$N->getServer():SERVER,$_GET["username"],get_password()];}function
verifyDefaultPassword($F){$Ge=$this->config->getDefaultPasswordHash();if($Ge===null||$Ge==="")return
lang(2);elseif(!password_verify($F,$Ge))return
lang(3);return
true;}function
authenticate($V,$F){if($F==""){$Ge=$this->config->getDefaultPasswordHash();if($Ge===null)return
lang(4,target_blank());else
return$Ge==="";}return
true;}function
getPrivateKey($cc=false){return
get_private_key($cc);}function
getBruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
getServerName($N,$sj=true,$Hd=null){if($N==""){if(!$sj)return"";$N=Connection::exists()?Connection::get()->getDefaultServerName():"";if($N=="")return$Hd!==null?$Hd:lang(5);$ck=null;}else$ck=$this->config->getServer($N);return$ck?$ck->getName():preg_replace('~^https?://~',"",$N);}abstract
function
getDatabase();function
getDatabases($ae=true){$f=$this->filterListWithWildcards(get_databases($ae),$this->config->getHiddenDatabases(),false,Driver::get()->getSystemDatabases());if(DB!=""&&!in_array(DB,$f))array_unshift($f,DB);return$f;}function
getSchemas($lh=false){$Je=$this->config->getHiddenSchemas();if($lh&&!in_array("__system",$Je))$Je[]="__system";$Mj=$this->filterListWithWildcards(schemas(),$Je,false,Driver::get()->getSystemSchemas());if(isset($_GET["ns"])&&$_GET["ns"]!=""&&!in_array($_GET["ns"],$Mj))array_unshift($Mj,$_GET["ns"]);return$Mj;}function
getCollations(array$Ff=[]){$wm=$this->config->getVisibleCollations();$Ud=$wm?array_merge($wm,$Ff):[];return$this->filterListWithWildcards(collations(),$Ud,true);}private
function
filterListWithWildcards(array$nm,array$Ud,$Hf,array$Sk=[]){if(!$nm||!$Ud)return$nm;$r=array_search("__system",$Ud);if($r!==false){unset($Ud[$r]);$Ud=array_merge($Ud,$Sk);}array_walk($Ud,function(&$Y){$Y=str_replace('\\*',".*",preg_quote($Y,"~"));});$vi='~^('.implode("|",$Ud).')$~';return$this->filterListWithPattern($nm,$vi,$Hf);}private
function
filterListWithPattern(array$nm,$vi,$Hf){$I=[];foreach($nm
as$t=>$Y){if(is_array($Y)){if($Ik=$this->filterListWithPattern($Y,$vi,$Hf))$I[$t]=$Ik;}elseif(($Hf&&preg_match($vi,$Y))||(!$Hf&&!preg_match($vi,$Y)))$I[$t]=$Y;}return$I;}abstract
function
getQueryTimeout();function
sendHeaders(){}function
updateCspHeader(array&$gc){}function
printFavicons(){$Db=validate_color_variant($this->config->getColorVariant());echo"<link rel='icon' type='image/x-icon' href='",link_files("favicon-$Db.ico",[]),"' sizes='32x32'>\n","<link rel='icon' type='image/svg+xml' href='",link_files("favicon-$Db.svg",[]),"'>\n","<link rel='apple-touch-icon' href='",link_files("apple-touch-icon-$Db.png",[]),"'>\n";}abstract
function
printToHead();function
getCssUrls(){$cm=$this->config->getCssUrls();foreach(["adminneo.css","adminneo-light.css","adminneo-dark.css"]as$m){if(file_exists($m))$cm[]="$m?v=".filemtime($m);}return$cm;}function
isLightModeForced(){return$this->isColorSchemeForced(false);}function
isDarkModeForced(){return$this->isColorSchemeForced(true);}private
function
isColorSchemeForced($lc){$Rg=$lc?Settings::$ColorSchemeDark:Settings::$ColorSchemeLight;$Sg=$lc?Settings::$ColorSchemeLight:Settings::$ColorSchemeDark;$Qd=file_exists("adminneo-$Rg.css");$Rd=file_exists("adminneo-$Sg.css");if($Qd&&!$Rd)return
true;return$this->settings->getColorScheme()==$Rg&&!($Qd
xor$Rd);}function
getJsUrls(){$cm=$this->config->getJsUrls();$m="adminneo.js";if(file_exists($m))$cm[]="$m?v=".filemtime($m);return$cm;}abstract
function
printLoginForm();function
getLoginFormRow($Ld,$Pf,$j){if($Pf)return"<tr><th>$Pf</th><td>$j</td></tr>\n";else
return"$j\n";}function
printLogout(){echo"<div class='logout'>","<form action='' method='post'>\n",h($_GET["username"]),"<input type='submit' class='button' name='logout' value='",lang(6),"' id='logout'>",input_token(),"</form>","</div>\n";}function
getTableName(array$Wk){return
h($Wk["Name"]);}abstract
function
getFieldName(array$j,$D=0);function
formatComment($Kb){return
h($Kb);}abstract
function
printTableMenu(array$Wk,$mf);function
getForeignKeys($Q){return
foreign_keys($Q);}function
getBackwardKeys($Q,$Uk){if(!$this->settings->isRelationLinks())return[];$L=backward_keys($Q);$Jf=[];foreach($L
as$K){$q=$K["table_schema"].".".$K["table_name"];$Jf[$q]["schema"]=$K["table_schema"];$Jf[$q]["table"]=$K["table_name"];$Jf[$q]["constraints"][$K["constraint_name"]][$K["column_name"]]=$K["referenced_column_name"];}foreach($Jf
as$q=>$t){$A=$this->admin->getTableName(table_status1($t["table"],true));if($A!=""){$Pj=preg_quote($Uk);$Zj="(:|\\s*-)?\\s+";$Jf[$q]["name"]=(preg_match("(^$Pj$Zj(.+)|^(.+?)$Zj$Pj\$)iu",$A,$y)?$y[2].$y[3]:$A);}else
unset($Jf[$q]);}return$Jf;}function
printBackwardKeys(array$Ua,array$K){foreach($Ua
as$t){foreach($t["constraints"]as$Vb){$Dg=preg_replace('~&ns=[^&]+&~',"&ns=".urldecode($t["schema"])."&",ME);$w=$Dg.'select='.urlencode($t["table"]);$p=0;foreach($Vb
as$b=>$X){if(!isset($K[$X]))continue
2;$w
.=where_link($p++,$b,$K[$X]);}$A=preg_replace('(^'.preg_quote($_GET["select"]).(substr($_GET["select"],-1)=="s"?"?":"").'_)',"_",$t["name"]);$T=implode(", ",array_keys($Vb));echo"<a href='".h($w)."' title='".h($T)."'>".h($A)."</a>";$w=$Dg.'edit='.urlencode($t["table"]);foreach($Vb
as$b=>$X)$w
.="&preset".urlencode("[".bracket_escape($b)."]")."=".urlencode($K[$X]);echo"<a href='".h($w)."' title='".lang(7)."'>",icon_solo("add"),"</a> ";}}}abstract
function
formatSelectQuery($H,$Ak,$Gd=false);abstract
function
formatMessageQuery($H,$vl,$Gd=false);abstract
function
formatSqlCommandQuery($H);function
printAfterSqlCommand(){}abstract
function
getTableDescriptionFieldName($Q);abstract
function
fillForeignDescriptions(array$L,array$de);function
getFieldValueLink($X,$j){if(is_mail($X))return"mailto:$X";if(is_web_url($X))return$X;return
null;}abstract
function
formatSelectionValue($X,$w,$j,$Zh);abstract
function
formatFieldValue($Y,array$j);abstract
function
printTableStructure(array$k);abstract
function
printTablePartitions(array$li);abstract
function
printRelatedTables(array$S);abstract
function
printTableIndexes(array$s,array$Wk);abstract
function
printSelectionColumns(array$M,array$c);abstract
function
printSelectionSearch(array$Z,array$c,array$s);abstract
function
printSelectionOrder(array$D,array$c,array$s);abstract
function
printSelectionLimit($v);abstract
function
printSelectionLength($ql);abstract
function
printSelectionAction(array$s);function
isDataEditAllowed(){return!information_schema(DB);}abstract
function
processSelectionColumns(array$c,array$s);abstract
function
processSelectionSearch(array$k,array$s);abstract
function
processSelectionOrder(array$k,array$s);function
processSelectionLimit(){if(!isset($_GET["limit"]))return$this->settings->getRecordsPerPage();return$_GET["limit"]!=""?(int)$_GET["limit"]:0;}abstract
function
processSelectionLength();abstract
function
getFieldFunctions(array$j);abstract
function
getFieldInput($Q,array$j,$Ma,$Y,$o);function
getFieldInputHint($Q,array$j,$Y){return
support("comment")?$this->admin->formatComment($j["comment"]):"";}abstract
function
processFieldInput(array$j,$Y,$o="");function
detectJson($Md,&$Y,$Mi=null){if(is_array($Y)){$Yd=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|($this->config->isJsonValuesAutoFormat()?JSON_PRETTY_PRINT:0);$Y=json_encode($Y,$Yd);return
true;}$Yd=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|($Mi?JSON_PRETTY_PRINT:0);if(preg_match('~^jsonb?$~',$Md)){if($Y!=null&&$Mi!==null&&$this->config->isJsonValuesAutoFormat())$Y=json_encode(json_decode($Y),$Yd);return
true;}if(!$this->config->isJsonValuesDetection())return
false;if(is_string($Y)&&$Y!=""&&preg_match('~varchar|text|character varying|String|keyword~',$Md)&&($Y[0]=="{"||$Y[0]=="[")&&($Df=json_decode($Y))){if($Mi!==null&&$this->config->isJsonValuesAutoFormat())$Y=json_encode($Df,$Yd);return
true;}return
false;}function
getServerVariables(){return
show_variables();}function
getStatusVariables(){return
show_status();}abstract
function
getDumpOutputs();abstract
function
getDumpFormats();abstract
function
sendDumpHeaders($Ue,$Vg=false);function
dumpDatabase($oc){}abstract
function
dumpTable($Q,$Hk,$tm=0);abstract
function
dumpData($Q,$Hk,$H);abstract
function
getImportFilePath();abstract
function
printDatabaseMenu();function
printNavigation($Pg){$Wf=isset($_COOKIE["neo_version"])?$_COOKIE["neo_version"]:null;echo"<div class='header'>\n",$this->admin->getServiceTitle()."\n";if($Pg!="auth"){echo"<span class='version'>",h(preg_replace('~\\.0(-|$)~','$1',VERSION));if($this->config->isVersionVerificationEnabled()&&$Wf&&version_compare(VERSION,$Wf)<0)echo"<a id='version' class='version-badge' href='https://www.adminneo.org/download' ".target_blank()." title='".h($Wf)."'>",icon_solo("asterisk"),"</a>";echo"</span>\n";if($this->config->isVersionVerificationEnabled()&&!$Wf)echo
script("verifyVersion('".js_escape(ME)."', '".get_token()."');");}echo"</div>\n";}abstract
function
printDatabaseSwitcher($Pg);function
printTablesFilter(){echo"<div class='tables-filter jsonly'>"."<input id='tables-filter' type='search' class='input' autocomplete='off' placeholder='".lang(8)."'>".script("initTablesFilter(".json_encode($this->admin->getDatabase()).");")."</div>\n";}abstract
function
printTableList(array$S);function
getSettingsRows($ze){$O=[];if($ze==1){$C=get_language_options();if($C)$O["lang"]="<tr><th id='label-language'>".lang(9)."</th>"."<td>".html_select("lang",get_language_options(),Locale::get()->getLanguage(),"","label-language")."</td></tr>\n";$C=[""=>lang(10),Settings::$ColorSchemeLight=>lang(11),Settings::$ColorSchemeDark=>lang(12)];$O["colorScheme"]="<tr><th>".lang(13)."</th>"."<td>".html_radios("colorScheme",$C,($ra=$this->settings->getParameter("colorScheme"))!==null?$ra:"")."</td></tr>\n";}elseif($ze==2){$C=[""=>lang(14),true=>lang(15),false=>lang(16),];$h=$C[$this->config->isRelationLinks()];$C[""].=" ($h)";$O["relationLinks"]="<tr><th>".lang(17)."</th>"."<td>".html_radios("relationLinks",$C,($ra=$this->settings->getParameter("relationLinks"))!==null?$ra:"")."<span class='input-hint'>".lang(18)."</span>"."</td></tr>\n";$h=$this->config->getRecordsPerPage();$C=[""=>lang(14)." ($h)","20","30","50","70","100",];$O["recordsPerPage"]="<tr><th id='label-records'>".lang(19)."</th>"."<td>".html_select("recordsPerPage",$C,($ra=$this->settings->getParameter("recordsPerPage"))!==null?$ra:"","","label-records")."<span class='input-hint'>".lang(20)."</span>"."</td></tr>\n";$h=($ra=$this->config->getEnumAsSelectThreshold())!==null?$ra:lang(21);$C=[""=>lang(14)." ($h)",-1=>lang(21),0=>lang(22),3=>lang(23,3),5=>lang(23,5),10=>lang(23,10),20=>lang(23,20),];$O["enumAsSelectThreshold"]="<tr><th id='label-enum'>".lang(24)."</th>"."<td>".html_select("enumAsSelectThreshold",$C,($ra=$this->settings->getParameter("enumAsSelectThreshold"))!==null?$ra:"","","label-enum",true)."<span class='input-hint'>".lang(25)."</span>"."</td></tr>\n";}return$O;}abstract
function
getForeignColumnInfo(array$de,$b);}class
Pluginer{private
static$InternalMethods=["inject"=>true,"getConfig"=>true,];private
static$AppendMethods=["getErrors"=>true,"getFieldFunctions"=>true,"getDumpOutputs"=>true,"getDumpFormats"=>true,"getSettingsRows"=>true,];private$plugins;private$hooks=[];function
__construct(Origin$ya,array$Bi){$this->plugins=$Bi;foreach(get_class_methods('\AdminNeo\Origin')as$Ng){$this->hooks[$Ng]=[];if(!(isset(self::$InternalMethods[$Ng])?self::$InternalMethods[$Ng]:false)){foreach($Bi
as$Ai){if(method_exists($Ai,$Ng))$this->hooks[$Ng][]=$Ai;}}if(isset(self::$AppendMethods[$Ng])?self::$AppendMethods[$Ng]:false)array_unshift($this->hooks[$Ng],$ya);else$this->hooks[$Ng][]=$ya;}}function
getPlugins(){return$this->plugins;}function
__call($A,array$gi){$Ha=isset(self::$AppendMethods[$A])?self::$AppendMethods[$A]:false;$I=$Ha?[]:null;assert(isset($this->hooks[$A]),"Calling unknown plugin method: $A");foreach($this->hooks[$A]as$Ai){$Y=call_user_func_array([$Ai,$A],$gi);if($Y!==null){if($Ha)$I+=$Y;else
return$Y;}}return$I;}function
updateCspHeader(array&$gc){$this->__call(__FUNCTION__,[&$gc]);}function
detectJson($Md,&$Y,$Mi=null){return$this->__call(__FUNCTION__,[$Md,&$Y,$Mi]);}}class
Admin
extends
Origin{function
getOperators(){return
Driver::get()->getOperators();}function
getServiceTitle(){return"<a href='".h(HOME_URL)."'><svg role='img' class='logo' width='133' height='28'><desc>AdminNeo</desc><use href='".link_files("logo.svg",[])."#logo'/></svg></a>";}function
getDatabase(){return
DB;}function
getQueryTimeout(){return
2;}function
printToHead(){echo"<link rel='stylesheet' href='",link_files("jush.css",[]),"'>";if(!$this->admin->isLightModeForced())echo"<link rel='stylesheet' ".(!$this->admin->isDarkModeForced()?"media='(prefers-color-scheme: dark)' ":"")."href='",link_files("jush-dark.css",[]),"'>\n";echo
script_src(link_files("jush.js",[]),true);}function
printLoginForm(){$Sc=Drivers::getList();$dk=$this->config->getServerPairs($Sc);$N=SERVER?:$this->config->getDefaultServer();echo"<table class='box box-light'>\n";if($dk)echo$this->admin->getLoginFormRow('server',lang(5),"<select name='auth[server]'>".optionlist($dk,$N,true)."</select>");else{$Qc=DRIVER?:$this->config->getDefaultDriver($Sc);if(count($Sc)>1)echo$this->admin->getLoginFormRow('driver',lang(26),html_select("auth[driver]",$Sc,$Qc).script("initLoginDriver(qsl('select'));",""));else
echo$this->admin->getLoginFormRow('driver','',input_hidden("auth[driver]",$Qc));echo$this->admin->getLoginFormRow('server',lang(5),'<input class="input" name="auth[server]" value="'.h($N).'" title="'.lang(27).'" placeholder="localhost" autocapitalize="off">');}echo$this->admin->getLoginFormRow('username',lang(28),'<input class="input" name="auth[username]" id="username" value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),$this->admin->getLoginFormRow('password',lang(29),'<input type="password" class="input" name="auth[password]" autocomplete="current-password">');if(!$dk){$oc=isset($_GET["db"])?$_GET["db"]:$this->config->getDefaultDatabase();echo$this->admin->getLoginFormRow('db',lang(30),'<input class="input" name="auth[db]" value="'.h($oc).'" autocapitalize="off">');}echo"</table>\n","<p>","<input type='submit' class='button default' value='".lang(31)."'>",checkbox("auth[permanent]",1,$_COOKIE["neo_permanent"],lang(32)),"</p>\n";}function
getFieldName(array$j,$D=0){$U=$j["full_type"].($j["null"]?" NULL":"");$Kb=$j["comment"];$Zj=$U&&$Kb!=""?": ":"";return'<span title="'.h($U.$Zj.$Kb).'">'.h($j["field"]).'</span>';}function
printTableMenu(array$Wk,$mf){echo'<p class="links top-tabs">';$jg=[];$Vj=($this->settings->isSelectionPreferred()&&!$this->settings->isNavigationReversed())||(!$this->settings->isSelectionPreferred()&&$this->settings->isNavigationReversed());if($Vj)$jg["select"]=[lang(33),"data"];if(support("table")||support("indexes"))$jg["table"]=[lang(34),"structure"];if(!$Vj)$jg["select"]=[lang(33),"data"];$Q=$Wk["Name"];$_f=false;if(support("table")){$_f=is_view($Wk);if(!$_f){if($Q!="")$jg["create"]=[lang(35),"edit"];}elseif(support("view"))$jg["view"]=[lang(36),"edit"];}if($mf!==null)$jg["edit"]=[lang(7),"item-add"];$gi=$mf?"&".http_build_query($mf):"";foreach($jg
as$t=>$X)echo" <a href='",h(ME),"$t=",urlencode($Q),($t=="edit"?$gi:""),"'",bold(isset($_GET[$t])),">",icon($X[1]),"$X[0]</a>";echo
doc_link([DIALECT=>Driver::get()->tableHelp($Q,$_f)],icon("help").lang(37)),"\n";}function
formatSelectQuery($H,$Ak,$Gd=false){$Nk=support("sql");$_m=!$Gd?Driver::get()->warnings():null;if($Nk)$H
.=";";$Qk=DIALECT=="elastic"||DIALECT=="mongo"?"json":DIALECT;$J="<pre><code class='jush-$Qk'>".h(str_replace("\n"," ",$H))."</code></pre>\n";$J
.="<p class='links'>";if($Nk)$J
.="<a href='".h(ME)."sql=".urlencode($H)."'>".icon("edit").lang(38)."</a>";if($_m)$J
.="<a href='#warnings' class='toggle'>".lang(39).icon_chevron_down()."</a>";$J
.=" <span class='time'>(".format_time($Ak).")</span>";$J
.="</p>\n";if($_m){$J
.=script("initToggles(qsl('p'));");$J
.="<div id='warnings' class='warnings hidden'>\n$_m\n</div>\n";}return$J;}function
formatMessageQuery($H,$vl,$Gd=false){restart_session();$Le=&get_session("queries");if(!isset($Le[$_GET["db"]]))$Le[$_GET["db"]]=[];if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$Le[$_GET["db"]][]=[$H,time(),$vl];$Nk=support("sql");$_m=!$Gd?Driver::get()->warnings():null;$yk="sql-".count($Le[$_GET["db"]]);$Am="warnings-".count($Le[$_GET["db"]]);$J=" ";if($_m)$J
.="<a href='#$Am' class='toggle'>".lang(39).icon_chevron_down()."</a>, ";$Yi=support("sql")?lang(40):lang(41);$J
.="<a href='#$yk' class='toggle'>$Yi".icon_chevron_down()."</a>";$J
.=" <span class='time'>".@date("H:i:s")."</span>\n";if($_m)$J
.="<div id='$Am' class='warnings hidden'>\n$_m</div>\n";$J
.="<div id='$yk' class='hidden'>\n";$Qk=DIALECT=="elastic"||DIALECT=="mongo"?"json":DIALECT;$J
.="<pre><code class='jush-$Qk'>".truncate_utf8($H,1000)."</code></pre>\n";$J
.="<p class='links'>";if($Nk)$J
.="<a href='".h(str_replace("db=".urlencode(DB),"db=".urlencode($_GET["db"]),ME).'sql=&history='.(count($Le[$_GET["db"]])-1))."'>".icon("edit").lang(38)."</a>";if($vl)$J
.=" <span class='time'>($vl)</span>";$J
.="</p>\n";$J
.="</div>\n";return$J;}function
formatSqlCommandQuery($H){if(preg_match('~^DELIMITER\s~i',$H))return"";return
truncate_utf8($H,1000);}function
getTableDescriptionFieldName($Q){return"";}function
fillForeignDescriptions(array$L,array$de){return$L;}function
formatSelectionValue($X,$w,$j,$Zh){if($X===null)$pl="<i>NULL</i>";elseif(!$j)$pl=$X;elseif(preg_match("~char|binary|boolean~",$j["type"])&&!preg_match("~var~",$j["type"]))$pl="<code>$X</code>";elseif(is_blob($j)&&!is_utf8($X))$pl="<i>".lang(42,strlen($Zh))."</i>";elseif($this->admin->detectJson($j["full_type"],$Zh))$pl="<code class='jush-json'>$X</code>";else$pl=$X;if($w)$pl="<a href='".h($w)."'".(is_web_url($w)?target_blank():"").">$pl</a>";return$pl;}function
formatFieldValue($Y,array$j){return$Y;}function
printTableStructure(array$k){echo"<div class='scrollable'>\n","<table class='nowrap'>\n","<thead><tr>","<th>",lang(43),"</th>","<td>",lang(44),"</td>","<td>",lang(45),"</td>";if(support("comment"))echo"<td>",lang(46),"</td>";echo"</tr></thead>\n";$im=Driver::get()->getUserTypes();foreach($k
as$j){echo"<tr>","<th>",h($j["field"]),"</th>","<td>";$U=h($j["full_type"]);if(in_array($U,$im))echo"<a href='".h(ME.'type='.urlencode($U))."'>$U</a>";else
echo$U;if($j["null"])echo" <i>NULL</i>";if($j["auto_increment"])echo" <i>".lang(47)."</i>";$h=h($j["default"]);if(isset($j["default"]))echo" <span title='".lang(48)."'>[<b>",$j["generated"]?"<code class='jush-".DIALECT."'>$h</code>":$h,"</b>]</span>";echo"</td>","<td>",h($j["collation"]),"</td>";if(support("comment"))echo"<td>",$this->admin->formatComment($j["comment"]),"</td>";echo"\n";}echo"</table>\n","</div>\n";}function
printTablePartitions(array$li){$nk=isset($li["partition_names"]);echo"<p>","<code class='jush-".DIALECT."'>BY {$li["partition_by"]} ({$li["partition"]})</code>";if(!$nk&&isset($li["partitions"]))echo" ".lang(49).": ".h($li["partitions"]);echo"</p>";if($nk){echo"<table>\n","<thead><tr><th>".lang(50)."</th><td>".lang(51)."</td></tr></thead>\n";foreach($li["partition_names"]as$t=>$A){echo"<tr><th>";if(DIALECT=="pgsql")echo"<a href='",h(ME."table=".urlencode($A)),"'>";echo
h($A);if(DIALECT=="pgsql")echo"</a>";echo"</th><td>".h($li["partition_values"][$t])."\n";}echo"</table>\n";}}function
printRelatedTables(array$S){echo"<ul class='links'>\n";foreach($S
as$K){$w=preg_replace('~ns=[^&]*~',"ns=".urlencode($K["ns"]),ME);echo"<li><a href='",h($w."table=".urlencode($K["table"])),"'>",icon("structure");if($K["ns"]!=$_GET["ns"])echo"<b>".h($K["ns"])."</b>.";echo
h($K["table"]),"</a>";}echo"</ul>\n";}function
printTableIndexes(array$s,array$Wk){$tc=first(Driver::get()->getIndexAlgorithms($Wk));$ji=false;foreach($s
as$r){if(isset($r["partial"])?$r["partial"]:false){$ji=true;break;}}echo"<table>\n","<thead><tr>","<th>",lang(44),"</th>","<td>",lang(52)," (",lang(53),")</td>";if($ji)echo"<td>",lang(54),"</td>";echo"</tr></thead>\n";foreach($s
as$A=>$r){ksort($r["columns"]);$Oi=[];foreach($r["columns"]as$t=>$X)$Oi[]="<i>".h($X)."</i>".($r["lengths"][$t]?"(".h($r["lengths"][$t]).")":"").($r["descs"][$t]?" DESC":"");echo"<tr title='",h($A),"'>","<th>",h($r["type"]);if(isset($r['algorithm'])&&$r['algorithm']!=$tc)echo" (",h($r['algorithm']),")";echo"</th>","<td>",implode(", ",$Oi),"</td>";if($ji){echo"<td>";if($r['partial'])echo"<code class='jush-",DIALECT,"'>WHERE ",h($r['partial']),"</code>";echo"</td>";}echo"</tr>\n";}echo"</table>\n";}function
printSelectionColumns(array$M,array$c){print_fieldset_start("select",lang(55),"columns",(bool)$M,true);$M[""]=[];$p=0;foreach($M
as$t=>$X){$X=isset($_GET["columns"][$t])?$_GET["columns"][$t]:[];$b=select_input("name='columns[$p][col]'",$c,isset($X["col"])?$X["col"]:null,$t!==""?"selectFieldChange":"selectAddRow");echo"<div ",($t!=""?"":"class='no-sort'"),">",icon("handle","handle jsonly");if(Driver::get()->getFunctions()||Driver::get()->getGrouping())echo
html_select("columns[$p][fun]",[-1=>""]+array_filter([lang(56)=>Driver::get()->getFunctions(),lang(57)=>Driver::get()->getGrouping()]),isset($X["fun"])?$X["fun"]:null),help_script_command("value && value.replace(/ |\$/, '(') + ')'",true),script("qsl('select').onchange = (event) => { ".($t!==""?"":" qsl('select, input:not(.remove)', event.target.parentNode).onchange();")." };",""),"($b)";else
echo$b;echo" <button class='button light remove jsonly' title='",h(lang(58)),"'>",icon_solo("remove"),"</button>",script("qsl('#fieldset-select .remove').onclick = selectRemoveRow;",""),"</div>\n";$p++;}print_fieldset_end("select",true);}function
printSelectionSearch(array$Z,array$c,array$s){print_fieldset_start("search",lang(59),"search",(bool)$Z);foreach($s
as$p=>$r){if($r["type"]=="FULLTEXT"){echo"<div>(<i>".implode("</i>, <i>",array_map('AdminNeo\h',$r["columns"]))."</i>) AGAINST","<input type='text' class='input' name='fulltext[$p]' value='".h(isset($_GET["fulltext"][$p])?$_GET["fulltext"][$p]:null)."'>",script("qsl('input').oninput = selectFieldChange;","");if(DIALECT=='sql')echo
checkbox("boolean[$p]",1,isset($_GET["boolean"][$p]),"BOOL");echo"</div>\n";}}$mb="this.parentNode.firstChild.onchange();";foreach(array_merge((array)$_GET["where"],[[]])as$p=>$X){if(!$X||("$X[col]$X[val]"!=""&&in_array($X["op"],$this->getOperators())))echo"<div>",select_input(" name='where[$p][col]'",$c,$X["col"],($X?"selectFieldChange":"selectAddRow"),"(".lang(60).")"),html_select("where[$p][op]",$this->getOperators(),$X["op"],$mb),"<input type='text' class='input' name='where[$p][val]' value='".h($X["val"])."'>",script("mixin(qsl('input'), {oninput: function () { $mb }, onkeydown: selectSearchKeydown});","")," <button class='button light remove jsonly' title='".h(lang(58))."'>",icon_solo("remove"),"</button>",script('qsl("#fieldset-search .remove").onclick = selectRemoveRow;',""),"</div>\n";}print_fieldset_end("search");}function
printSelectionOrder(array$D,array$c,array$s){print_fieldset_start("sort",lang(61),"sort",(bool)$D,true);$_GET["order"][""]="";$p=0;foreach((array)$_GET["order"]as$t=>$X){if($t!=""&&$X=="")continue;echo"<div ",($t!=""?"":"class='no-sort'"),">",icon("handle","handle jsonly"),select_input("name='order[$p]'",$c,$X,$t!==""?"selectFieldChange":"selectAddRow")," ",checkbox("desc[$p]",1,isset($_GET["desc"][$t]),lang(62))," <button class='button light remove jsonly' title='",h(lang(58)),"'>",icon_solo("remove"),"</button>",script('qsl("#fieldset-sort .remove").onclick = selectRemoveRow;',""),"</div>\n";$p++;}print_fieldset_end("sort",true);}function
printSelectionLimit($v){echo"<fieldset><legend>".lang(63)."</legend><div class='fieldset-content'>","<input type='number' name='limit' class='input size' value='$v'>",script("qsl('input').oninput = selectFieldChange;",""),"</div></fieldset>\n";}function
printSelectionLength($ql){if($ql!==null)echo"<fieldset><legend>".lang(64)."</legend><div class='fieldset-content'>","<input type='number' name='text_length' class='input size' value='".h($ql)."'>","</div></fieldset>\n";}function
printSelectionAction(array$s){echo"<fieldset><legend>".lang(65)."</legend><div class='fieldset-content'>","<input type='submit' class='button' value='".lang(55)."'>"," <span id='noindex' title='".lang(66)."'></span>","<script".nonce().">\n";$c=new
stdClass();foreach($s
as$r){$ic=reset($r["columns"]);if($r["type"]!="FULLTEXT"&&$ic)$c->$ic=null;}echo"const indexColumns = ".json_encode($c,JSON_UNESCAPED_UNICODE).";\n","selectFieldChange.call(gid('form')['select']);\n","</script>\n","</div></fieldset>\n";}function
processSelectionColumns(array$c,array$s){$M=[];$xe=[];foreach((array)$_GET["columns"]as$t=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],Driver::get()->getFunctions())||in_array($X["fun"],Driver::get()->getGrouping())))){$M[$t]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],Driver::get()->getGrouping()))$xe[]=$M[$t];}}return[$M,$xe];}function
processSelectionSearch(array$k,array$s){$J=[];foreach($s
as$p=>$r){if($r["type"]=="FULLTEXT"&&isset($_GET["fulltext"])&&$_GET["fulltext"][$p]!="")$J[]="MATCH (".implode(", ",array_map('AdminNeo\idf_escape',$r["columns"])).") AGAINST (".q($_GET["fulltext"][$p]).(isset($_GET["boolean"][$p])?" IN BOOLEAN MODE":"").")";}foreach((array)$_GET["where"]as$Z){$_b=$Z["col"];$Eh=$Z["op"];$X=$Z["val"];if("$_b$X"!=""&&in_array($Eh,$this->getOperators())){$Rb=[];foreach(($_b!=""?[$_b=>$k[$_b]]:$k)as$A=>$j){$Ki="";$Qb=" $Eh";$uh=DIALECT=="pgsql"&&$Eh=="="&&$j["type"]=="oid";if($uh)$Qb
.=" ".$this->admin->processFieldInput($j,$X)."::regproc";elseif(preg_match('~IN$~',$Eh)){$Ze=process_length($X);$Qb
.=" ".($Ze!=""?$Ze:"(NULL)");}elseif($Eh=="SQL")$Qb=" $X";elseif(preg_match('~^(I?LIKE) %%$~',$Eh,$y))$Qb=" $y[1] ".$this->admin->processFieldInput($j,"%$X%");elseif($Eh=="FIND_IN_SET"){$Ki="$Eh(".q($X).", ";$Qb=")";}elseif(!preg_match('~NULL$~',$Eh))$Qb
.=" ".$this->admin->processFieldInput($j,$X);if($_b!=""||(isset($j["privileges"]["where"])&&(preg_match('~^[-\d.'.(preg_match('~IN$~',$Eh)?',':'').']+$~',$X)||!preg_match('~'.number_type().'|bit~',$j["type"]))&&(!preg_match("~[\x80-\xFF]~",$X)||preg_match('~char|text|enum|set~',$j["type"]))&&(!preg_match('~date|timestamp~',$j["type"])||preg_match('~^\d+-\d+-\d+~',$X))&&(!preg_match('~^elastic~',DRIVER)||$j["type"]!="boolean"||preg_match('~true|false~',$X))&&(!preg_match('~^elastic~',DRIVER)||strpos($Eh,"regexp")===false||preg_match('~text|keyword~',$j["type"])))){if($uh)$Rb[]=$Ki.idf_escape($A).$Qb;else$Rb[]=$Ki.Driver::get()->convertSearch(idf_escape($A),$Z,$j).$Qb;}}if(count($Rb)==1)$J[]=$Rb[0];elseif($Rb)$J[]="(".implode(" OR ",$Rb).")";else$J[]="1 = 0";}}return$J;}function
processSelectionOrder(array$k,array$s){$J=[];foreach((array)$_GET["order"]as$t=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$t])?" DESC":"");}return$J;}function
processSelectionLength(){return
isset($_GET["text_length"])?$_GET["text_length"]:"100";}function
getFieldFunctions(array$j){$J=($j["null"]?"NULL/":"");$Zl=isset($_GET["select"])||where($_GET);foreach([Driver::get()->getInsertFunctions(),Driver::get()->getEditFunctions()]as$t=>$pe){if(!$t||(!isset($_GET["call"])&&$Zl)){foreach($pe
as$vi=>$X){if(!$vi||preg_match("~$vi~",$j["type"]))$J
.="/$X";}}if($t&&$pe&&!preg_match('~enum|set|bool~',$j["type"])&&!is_blob($j))$J
.="/SQL";}if($j["auto_increment"]&&!$Zl)$J=lang(47);return
explode("/",$J);}function
getFieldInput($Q,array$j,$Ma,$Y,$o){return"";}function
processFieldInput(array$j,$Y,$o=""){if($o=="SQL")return$Y;if(isset($j["full_type"]))$this->admin->detectJson($j["full_type"],$Y,false);$A=$j["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$o))$J="$o()";elseif(preg_match('~^current_(date|timestamp)$~',$o))$J=$o;elseif(preg_match('~^([+-]|\|\|)$~',$o))$J=idf_escape($A)." $o $J";elseif(preg_match('~^[+-] interval$~',$o))$J=idf_escape($A)." $o ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&DIALECT!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$o))$J="$o(".idf_escape($A).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$o))$J="$o($J)";elseif($j["type"]=="boolean"&&DIALECT=="elastic")$J=$J=="0"?"false":"true";return
unconvert_field($j,$J);}function
getDumpOutputs(){$ci=['file'=>lang(67),'text'=>lang(68),];if(function_exists('gzencode'))$ci['gz']='gzip';return$ci;}function
getDumpFormats(){return(support("dump")?['sql'=>'SQL']:[])+['csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV'];}function
sendDumpHeaders($Ue,$Vg=false){$bi=$_POST["output"];$Cd=(str_contains($_POST["format"],"sql")?"sql":($Vg?"tar":"csv"));if($bi=="gz"){header("Content-Type: application/x-gzip");ob_start(function($Ek){return
gzencode($Ek);},1e6);}elseif($Cd=="tar")header("Content-Type: application/x-tar");elseif($Cd=="sql"||$bi=="text")header("Content-Type: text/plain; charset=utf-8");else
header("Content-Type: text/csv; charset=utf-8");return$Cd;}function
dumpTable($Q,$Hk,$tm=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($Hk)dump_csv(array_keys(fields($Q)));}else{if($tm==2){$k=[];foreach(fields($Q)as$A=>$j)$k[]=idf_escape($A)." $j[full_type]";$cc="CREATE TABLE ".table($Q)." (".implode(", ",$k).")";}else$cc=create_sql($Q,$_POST["auto_increment"],$Hk);set_utf8mb4($cc);if($Hk&&$cc){if($Hk=="DROP+CREATE"||$tm==1)echo"DROP ".($tm==2?"VIEW":"TABLE")." IF EXISTS ".table($Q).";\n";if($tm==1)$cc=remove_definer($cc);echo"$cc;\n\n";}}}function
dumpData($Q,$Hk,$H){if($Hk){$xg=(DIALECT=="sqlite"?0:1048576);$k=[];$Ve=false;if($_POST["format"]=="sql"){if($Hk=="TRUNCATE+INSERT")echo
truncate_sql($Q).";\n";$k=fields($Q);if(DIALECT=="mssql"){foreach($k
as$j){if($j["auto_increment"]){echo"SET IDENTITY_INSERT ".table($Q)." ON;\n";$Ve=true;break;}}}}$I=Connection::get()->query($H,1);if($I){$kf="";$eb="";$Jf=[];$re=[];$Kk="";$bc=0;while($K=($Q!=''?$I->fetchAssoc():$I->fetchRow())){if(!$Jf){$nm=[];foreach($K
as$X){$j=$I->fetchField();if(!empty($k[$j->name]['generated'])){$re[$j->name]=true;continue;}$Jf[]=$j->name;$t=idf_escape($j->name);$nm[]="$t = VALUES($t)";}$Kk=($Hk=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$nm):"").";\n";}if($_POST["format"]!="sql"){if($Hk=="table"){dump_csv($Jf);$Hk="INSERT";}dump_csv($K);}else{if(!$kf)$kf="INSERT INTO ".table($Q)." (".implode(", ",array_map('AdminNeo\idf_escape',$Jf)).") VALUES";foreach($K
as$t=>$X){if(isset($re[$t])){unset($K[$t]);continue;}$j=$k[$t];$K[$t]=($X===null?"NULL":($X===false?0:unconvert_field($j,preg_match(number_type(),$j["type"])&&!preg_match('~\[~',$j["full_type"])&&is_numeric($X)?$X:(!is_blob($j)||is_utf8($X)?q($X):Driver::get()->quoteBinary($X)))));}$Dj=($xg?"\n":" ")."(".implode(",\t",$K).")";if(!$eb)$eb=$kf.$Dj;elseif(DIALECT=="mssql"?$bc%1000!=0:strlen($eb)+4+strlen($Dj)+strlen($Kk)<$xg)$eb
.=",$Dj";else{echo$eb.$Kk;$eb=$kf.$Dj;}}$bc++;}if($eb)echo$eb.$Kk;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",Connection::get()->getError())."\n";if($Ve)echo"SET IDENTITY_INSERT ".table($Q)." OFF;\n";}}function
getImportFilePath(){return"adminneo.sql";}function
printDatabaseMenu(){echo"<p class='links top-links'>\n";$nh=isset($_GET["ns"])?$_GET["ns"]:null;if($nh==""&&support("database"))echo'<a href="',h(ME),'database=">',icon("edit"),lang(69),"</a>\n";if($nh!=""&&support("scheme"))echo"<a href='",h(ME),"scheme='>",icon("edit"),lang(70),"</a>\n";if($nh!=="")echo'<a href="',h(ME),'schema=">',icon("schema"),lang(71),"</a>\n";if(support("privileges"))echo"<a href='",h(ME),"privileges='>",icon("users"),lang(72),"</a>\n";echo"</p>\n";}function
printNavigation($Pg){parent::printNavigation($Pg);if($Pg=="auth"){$bi="";foreach((array)$_SESSION["pwds"]as$pm=>$hk){foreach($hk
as$N=>$jm){foreach($jm
as$V=>$F){if($F!==null){$rc=$_SESSION["db"][$pm][$N][$V];foreach(($rc?array_keys($rc):[""])as$g){$ek=$this->admin->getServerName($N,false);$T=h(get_driver_name($pm,$N)).($V!=""||$ek!=""?" - ":"").h($V).($V!=""&&$ek!=""?"@":"").h($ek).($g!=""?h(" - $g"):"");$bi
.="<li><a href='".h(auth_url($pm,$N,$V,$g))."' class='primary' title='$T'>$T</a></li>\n";}}}}}if($bi)echo"<nav id='logins'><menu>\n$bi</menu></nav>\n";}else{$this->admin->printDatabaseSwitcher($Pg);$va=[];if(DB==""||!$Pg){if(support("sql")){$va[]="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".icon("command").lang(40)."</a>";$va[]="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".icon("import").lang(73)."</a>";}$va[]="<a href='".h(ME)."dump=".urlencode(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".icon("export").lang(74)."</a>";}if(DB=="")$va[]='<a href="'.h(ME).'database="'.bold($_GET["database"]==="").">".icon("database-add").lang(75)."</a>\n";if(DB!=""&&$_GET["ns"]===""&&!$Pg)$va[]='<a href="'.h(ME).'scheme="'.bold($_GET["scheme"]==="").">".icon("database-add").lang(76)."</a>\n";if(DB!=""&&$_GET["ns"]!==""&&!$Pg)$va[]='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".icon("table-add").lang(77)."</a>\n";if($va)echo"<p class='links'>".implode("\n",$va)."</p>";$S=[];if($_GET["ns"]!==""&&!$Pg&&DB!=""){Connection::get()->selectDatabase(DB);$S=table_status('',true);}if($_GET["ns"]!==""&&!$Pg&&DB!=""){if($S){$this->admin->printTablesFilter();$this->admin->printTableList($S);}else
echo"<p class='message'>".lang(78)."</p>\n";}if(support("sql")||DIALECT=="elastic"||DIALECT=="mongo"){echo"<script".nonce().">\n";if(support("sql")&&$S){$jg=[];foreach($S
as$Q=>$U)$jg[]=js_escape_re($Q);$Vk=support("table")&&!$this->config->isSelectionPreferred()?"table":"select";echo"window.jushLinks = { ".DIALECT.": {\n",js_escape_key(ME.$Vk.'=$&'),': /\b(?<!\$)('.implode('|',$jg).')(?!\$)\b/g';if(support('routine')){foreach(routines()as$K)echo",\n",js_escape_key(ME.'function='.urlencode($K["SPECIFIC_NAME"]).'&name=$&'),': /\b'.js_escape_re($K["ROUTINE_NAME"]).'(?=["`\]]?\()/g';}echo"\n}};\n";foreach(["bac","bra","sqlite_quo","mssql_bra"]as$X)echo"jushLinks.$X = jushLinks.".DIALECT.";\n";}if(DIALECT!="elastic"&&DIALECT!="mongo"&&$this->getConfig()->isSqlAutocompletionEnabled()&&(isset($_GET["sql"])||isset($_GET["trigger"])||isset($_GET["check"]))){$fl=array_fill_keys(array_keys($S),[]);foreach(Driver::get()->getAllFields()as$Q=>$k){foreach($k
as$j)$fl[$Q][]=$j["field"];}echo"window.addEventListener('DOMContentLoaded', () => { autocompletion = jush.autocompleteSql('".idf_escape("")."', ".json_encode($fl)."); });\n";}echo"</script>\n";}echo
script("let autocompletion;\nwindow.addEventListener('DOMContentLoaded', () => { initSyntaxHighlighting('".js_escape(doc_version())."', '".js_escape(Connection::get()->getFlavor())."', autocompletion); });");}}function
printDatabaseSwitcher($Pg){$f=$this->admin->getDatabases();if(!$f&&DIALECT!="sqlite")return;echo"<div class='db-selector'><form action=''>";hidden_fields_get();echo"<div>";if($f)echo"<select id='database-select' name='db' title='",lang(30),"'>".optionlist([""=>"(".lang(79).")"]+$f,DB)."</select>".script("mixin(gid('database-select'), {onmousedown: dbMouseDown, onchange: dbChange});");else
echo"<input id='database-select' class='input' name='db' value='".h(DB)."' title='",lang(30),"' autocapitalize='off'>\n";echo"<input type='submit' value='".lang(80)."' class='button ".($f?"hidden":"")."'>\n","</div>";foreach(["import","sql","schema","dump","privileges"]as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</form></div>\n";}function
printTableList(array$S){$Xc=$this->settings->isNavigationDual()||$this->settings->isNavigationHover();$Fg=($Xc?"class='dual".($this->settings->isNavigationHover()?" hover":"")."'":($this->settings->isNavigationReversed()?"class='reversed'":""));echo"<nav id='tables'><menu $Fg>";foreach($S
as$Q=>$P){$Q="$Q";$A=$this->admin->getTableName($P);if($A==""||(isset($P["Partition"])?$P["Partition"]:false))continue;echo"<li>";$wa=in_array($Q,[$_GET["table"],$_GET["select"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"]]);$yb="primary".(is_view($P)?" view":"");$Ok=support("table")||support("indexes");$Sj=h(ME)."select=".urlencode($Q);$Xk=h(ME)."table=".urlencode($Q);if($this->settings->isSelectionPreferred()){if($this->settings->isNavigationReversed()&&$Ok)echo" <a href='$Xk' title='",lang(34),"' class='secondary'>",icon("structure"),"</a>";echo"<a href='$Sj'",bold($wa,$yb)," data-primary='true' title='$A'>$A</a>";if($Xc&&$Ok)echo" <a href='$Xk' title='",lang(34),"' class='secondary'>",icon_solo("structure"),"</a>";}else{if($this->settings->isNavigationReversed())echo" <a href='$Sj' title='",lang(33),"' class='secondary'>",icon("data"),"</a>";if($Ok)echo"<a href='$Xk'",bold($wa,$yb)," data-primary='true' title='$A'>$A</a>";else
echo"<span data-primary='true'",bold($wa,$yb),">$A</span>";if($Xc)echo" <a href='$Sj' title='",lang(33),"' class='secondary'>",icon_solo("data"),"</a>";}echo"</li>\n";}echo"</menu></nav>\n",script("initTablesList(".json_encode($this->admin->getDatabase()).");");}function
getSettingsRows($ze){$O=parent::getSettingsRows($ze);if($ze==1){$C=[""=>lang(14),Config::$NavigationSimple=>lang(81),Config::$NavigationDual=>lang(82),Config::$NavigationHover=>lang(83),Config::$NavigationReversed=>lang(84)];$h=$C[$this->config->getNavigationMode()];$C[""].=" ($h)";$O["navigationMode"]="<tr><th>".lang(85)."</th>"."<td>".html_radios("navigationMode",$C,($ra=$this->settings->getParameter("navigationMode"))!==null?$ra:"")."<span class='input-hint'>".lang(86)."</span>"."</td></tr>\n";$C=[""=>lang(14),0=>lang(34),1=>lang(33),];$h=$C[$this->config->isSelectionPreferred()?1:0];$C[""].=" ($h)";$O["preferSelection"]="<tr><th id='label-links'>".lang(87)."</th>"."<td>".html_select("preferSelection",$C,($ra=$this->settings->getParameter("preferSelection"))!==null?$ra:"","","label-links",true)."<span class='input-hint'>".lang(88)."</span>"."</td></tr>\n";}return$O;}function
getForeignColumnInfo(array$de,$b){return
null;}}class
TmpFile{private$handler;private$size;function
__construct(){$this->handler=tmpfile();}function
getSize(){return$this->size;}function
write($Xb){if(!$this->handler)return;$this->size+=strlen($Xb);fwrite($this->handler,$Xb);}function
send(){if(!$this->handler)return;fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}function
print_select_result(Result$I,$d=null,array$Th=[],$v=0){$jg=[];$s=[];$c=[];$ab=[];$Pl=[];$J=[];for($p=0;(!$v||$p<$v)&&($K=$I->fetchRow());$p++){if(!$p){echo"<div class='scrollable'>\n","<table class='nowrap'>\n","<thead><tr>";for($Cf=0;$Cf<count($K);$Cf++){$j=$I->fetchField();if(!$j){echo"<th></th>";continue;}$A=$j->name;$Sh=isset($j->orgtable)?$j->orgtable:"";$Rh=isset($j->orgname)?$j->orgname:$A;if(isset($j->table))$J[$j->table]=$Sh;if($Th&&DIALECT=="sql")$jg[$Cf]=($A=="table"?"table=":($A=="possible_keys"?"indexes=":null));elseif($Sh!=""){if(!isset($s[$Sh])){$s[$Sh]=[];foreach(indexes($Sh,$d)as$r){if($r["type"]=="PRIMARY"){$s[$Sh]=array_flip($r["columns"]);break;}}$c[$Sh]=$s[$Sh];}if(isset($c[$Sh][$Rh])){unset($c[$Sh][$Rh]);$s[$Sh][$Rh]=$Cf;$jg[$Cf]=$Sh;}}if($j->charsetnr==63)$ab[$Cf]=true;$Pl[$Cf]=$j->type;$T=trim(($Sh!=""?"$Sh.$Rh":($j->name!=$Rh?$Rh:""))." ".Driver::get()->getTypeName($j));echo"<th".($T!=""?" title='".h($T)."'":"").">".h($A).($Th?doc_link(['sql'=>"explain-output.html#explain_".strtolower($A),'mariadb'=>"reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain#columns-in-explain-...-select",]):"");}echo"</thead>\n";}echo"<tr>";foreach($K
as$t=>$X){$w="";if(isset($jg[$t])&&!$c[$jg[$t]]){if($Th&&DIALECT=="sql"){$Q=$K[array_search("table=",$jg)];$w=ME.$jg[$t].urlencode($Th[$Q]!=""?$Th[$Q]:$Q);}else{$w=ME."edit=".urlencode($jg[$t]);foreach($s[$jg[$t]]as$_b=>$Cf)$w
.="&where".urlencode("[".bracket_escape($_b)."]")."=".urlencode($K[$Cf]);}}$U=($ab[$t]?'blob':($Pl[$t]==254?'char':''));$j=['full_type'=>$U,'type'=>$U,];$X=select_value($X,$w,$j,null);$yb=$Pl[$t]<=9||$Pl[$t]==246?"class='number'":"";echo"<td $yb>$X</td>";}}if($p)echo"</table>\n</div>";else
echo"<p class='message'>".lang(89);echo"\n";return$J;}function
referencable_primary($Xj){$J=[];foreach(table_status('',true)as$Zk=>$Q){if($Zk!=$Xj&&fk_support($Q)){foreach(fields($Zk)as$j){if($j["primary"]){if($J[$Zk]){unset($J[$Zk]);break;}$J[$Zk]=$j;}}}}return$J;}function
textarea($A,$Y,$L=10,$Fb=80){echo"<textarea name='".h($A)."' rows='$L' cols='$Fb' class='sqlarea jush-".DIALECT."' spellcheck='false' wrap='off'>";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($Ma,$C,$Y="",$Ch="",$yi=""){$jl=($C?"select":"input");return"<$jl $Ma".($C?"><option value=''>$yi".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$yi'>").($Ch?script("qsl('$jl').onchange = $Ch;",""):"");}function
json_row($t,$X=null){static$Wd=true;if($Wd)echo"{";if($t!=""){echo($Wd?"":",")."\n\t\"".addcslashes($t,"\r\n\t\"\\/").'": '.($X!==null?'"'.addcslashes($X,"\r\n\t\"\\/").'"':'null');$Wd=false;}else{echo"\n}\n";$Wd=true;}}function
edit_type($t,$j,$Cb,$ee=[],$Fd=[]){$U=isset($j["type"])?$j["type"]:null;echo'<td><select name="',h($t),'[type]" class="type" aria-labelledby="label-type">';$Rc=Driver::get()->getTypes();if($U&&!isset($Rc[$U])&&!isset($ee[$U])&&!in_array($U,$Fd))$Fd[]=$U;$Gk=Driver::get()->getStructuredTypes();if($ee)$Gk[lang(90)]=$ee;echo
optionlist(array_merge($Fd,$Gk),$U),'</select><td><input name="',h($t),'[length]" value="',h(isset($j["length"])?$j["length"]:null),'" size="3"',(!(isset($j["length"])?$j["length"]:null)&&preg_match('~var(char|binary)$~',$U)?" class='input required'":" class='input'"),' aria-labelledby="label-length"><td class="options">',($Cb?"<select name='".h($t)."[collation]'".(preg_match('~(char|text|enum|set)$~',$U)?"":" class='hidden'").'><option value="">('.lang(91).')'.optionlist($Cb,isset($j["collation"])?$j["collation"]:null).'</select>':''),(Driver::get()->getUnsigned()?"<select name='".h($t)."[unsigned]'".(!$U||preg_match(number_type(),$U)?"":" class='hidden'").'><option>'.optionlist(Driver::get()->getUnsigned(),isset($j["unsigned"])?$j["unsigned"]:null).'</select>':''),(isset($j['on_update'])?"<select name='".h($t)."[on_update]'".(preg_match('~timestamp|datetime~',$U)?"":" class='hidden'").'>'.optionlist([""=>"(".lang(92).")","CURRENT_TIMESTAMP"],(preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?"CURRENT_TIMESTAMP":$j["on_update"])).'</select>':''),($ee?"<select name='".h($t)."[on_delete]'".(preg_match("~`~",$U)?"":" class='hidden'")."><option value=''>(".lang(93).")".optionlist(Driver::get()->getOnActions(),isset($j["on_delete"])?$j["on_delete"]:null)."</select> ":" ");}function
process_length($u){$nd=Driver::$EnumLengthPattern;return(preg_match("~^\\s*\\(?\\s*$nd(?:\\s*,\\s*$nd)*+\\s*\\)?\\s*\$~",$u)&&preg_match_all("~$nd~",$u,$z)?"(".implode(",",$z[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$u)));}function
process_type($j,$Ab="COLLATE"){return" $j[type]".process_length($j["length"]).(preg_match(number_type(),$j["type"])&&in_array($j["unsigned"],Driver::get()->getUnsigned())?" $j[unsigned]":"").(preg_match('~char|text|enum|set~',$j["type"])&&$j["collation"]?" $Ab ".(DIALECT=="mssql"?$j["collation"]:q($j["collation"])):"");}function
process_field($j,$Nl){if($j["on_update"])$j["on_update"]=preg_replace('~current_timestamp(\(\))?~i',"CURRENT_TIMESTAMP",$j["on_update"]);return[idf_escape(trim($j["field"])),process_type($Nl),($j["null"]?" NULL":" NOT NULL"),default_value($j),(preg_match('~timestamp|datetime~',$j["type"])&&$j["on_update"]?" ON UPDATE ".$j["on_update"]:""),(support("comment")&&$j["comment"]!=""?" COMMENT ".q($j["comment"]):""),($j["auto_increment"]?auto_increment():null),];}function
default_value($j){if($j["default"]===null)return"";$h=str_replace("\r","",$j["default"]);$qe=$j["generated"];if(in_array($qe,Driver::get()->getGenerated())){if(DIALECT=="mssql")return" AS ($h)".($qe=="VIRTUAL"?"":" $qe");else
return" GENERATED ALWAYS AS ($h) $qe";}if(stripos($h,"GENERATED ")===0)return" $h";if(preg_match('~char|binary|text|json|enum|set~',$j["type"])||preg_match('~^(?![a-z])~i',$h)){if(DIALECT=="sql"&&preg_match('~text|json~',$j["type"]))return" DEFAULT (".q($h).")";else
return" DEFAULT ".q($h);}else{$h=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$h);return" DEFAULT ".(DIALECT=="sqlite"?"($h)":$h);}}function
type_class($U){foreach(['char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',]as$yb=>$vi){if(preg_match("~$yb|$vi~",$U))return"class='$yb'";}return"";}function
edit_fields(array$k,array$Cb,$U="TABLE",$ee=[]){$k=array_values($k);$Nb=$_POST?$_POST["comments"]:Admin::get()->getSettings()->getParameter("commentsOpened");$Lb=$Nb?"":"class='hidden'";echo"<thead><tr>\n";if(support("move_col"))echo"<td class='jsonly'></td>";if($U=="PROCEDURE")echo"<td></td>";echo"<th id='label-name'>",($U=="TABLE"?lang(94):lang(95)),"</th>\n","<td id='label-type'>",lang(44),"<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>",script("gid('enum-edit').onblur = onFieldLengthBlur;"),"</td>\n","<td id='label-length'>",lang(96),"</td>\n","<td>",lang(97),"</td>\n";if($U=="TABLE")echo"<td id='label-null'>NULL</td>\n","<td><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='",lang(47),"'>AI</abbr>",doc_link(['sql'=>"example-auto-increment.html",'mariadb'=>"reference/data-types/auto_increment",]),"</td>\n","<td id='label-default'>",lang(48),"</td>\n",support("comment")?"<td id='label-comment' $Lb>".lang(46)."</td>\n":"";echo"<td>","<button name='add[",(support("move_col")?0:count($k)),"]' value='1' title='",h(lang(98)),"' class='button light'>",icon_solo("add"),"</button>",(support("move_col")?"":script("qsl('button').onclick = onAddLastFieldRowClick;")),script("row_count = ".count($k).";"),"</td>\n","</tr></thead>\n";$yb=support("move_col")?"class='sortable'":"";echo"<tbody $yb>\n";foreach($k
as$p=>$j){$p++;$Uh=$j[($_POST?"orig":"field")];$Hc=(isset($_POST["add"][$p-1])||(isset($j["field"])&&!(isset($_POST["drop_col"][$p])?$_POST["drop_col"][$p]:null)))&&(support("drop_col")||$Uh=="");echo"<tr",($Hc?"":" hidden"),">\n";if(support("move_col"))echo"<td class='handle jsonly'>",icon_solo("handle"),"</td>";if($U=="PROCEDURE")echo"<td>",html_select("fields[$p][inout]",Driver::get()->getInOut(),$j["inout"]),"</td>\n";echo"<th>";if($Hc)echo"<input class='input' name='fields[$p][field]' value='",h($j["field"]),"' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name' ".(isset($_POST["add"][$p-1])?"autofocus":"").">";echo
input_hidden("fields[$p][orig]",$Uh);edit_type("fields[$p]",$j,$Cb,$ee);echo"</th>\n";if($U=="TABLE"){echo"<td>",checkbox("fields[$p][null]",1,$j["null"],"","","block","label-null"),"</td>\n";$tb=$j["auto_increment"]?"checked":"";echo"<td><label class='block'><input type='radio' name='auto_increment_col' value='$p' $tb aria-labelledby='label-ai'></label></td>\n","<td class='default-value'>";if(Driver::get()->getGenerated())echo
html_select("fields[$p][generated]",array_merge(["","DEFAULT"],Driver::get()->getGenerated()),$j["generated"]);else
echo
checkbox("fields[$p][generated]",1,$j["generated"],"","","","label-default");$Ma="name='fields[$p][default]' aria-labelledby='label-default'";$Y=h($j["default"]);if(str_contains($Y,"\n")){if($Y[0]=="\n")$Y="\n$Y";echo"<textarea $Ma rows='3' cols='30' style='vertical-align: bottom;'>$Y</textarea>";}else
echo"<input class='input' $Ma value='$Y'>";echo"</td>\n";if(support("comment")){$wg=Connection::get()->isMinVersion("5.5")?1024:255;echo"<td $Lb>","<input class='input' name='fields[$p][comment]' value='",h($j["comment"]),"' data-maxlength='$wg' aria-labelledby='label-comment'>","</td>\n";}}echo"<td>";if(support("move_col"))echo"<button name='add[$p]' value='1' title='".h(lang(98))."' class='button light'>",icon_solo("add"),"</button>","<button name='up[$p]' value='1' title='".h(lang(99))."' class='button light hidden'>",icon_solo("arrow-up"),"</button>","<button name='down[$p]' value='1' title='".h(lang(100))."' class='button light hidden'>",icon_solo("arrow-down"),"</button>";if($Uh==""||support("drop_col"))echo"<button name='drop_col[$p]' value='1' title='".h(lang(58))."' class='button light'>",icon_solo("remove"),"</button>";echo"</td>\n</tr>\n";}echo"</tbody>";}function
process_fields(&$k){$sh=0;if($_POST["up"]){$Uf=0;foreach($k
as$t=>$j){if(key($_POST["up"])==$t){unset($k[$t]);array_splice($k,$Uf,0,[$j]);break;}if(isset($j["field"]))$Uf=$sh;$sh++;}}elseif($_POST["down"]){$je=false;foreach($k
as$t=>$j){if(isset($j["field"])&&$je){unset($k[key($_POST["down"])]);array_splice($k,$sh,0,[$je]);break;}if(key($_POST["down"])==$t)$je=$j;$sh++;}}elseif($_POST["add"]){$k=array_values($k);array_splice($k,key($_POST["add"]),0,[[]]);}elseif(!$_POST["drop_col"])return
false;return
true;}function
normalize_enum($y){$X=$y[0];return"'".str_replace("'","''",addcslashes(stripcslashes(str_replace($X[0].$X[0],$X[0],substr($X,1,-1))),'\\'))."'";}function
grant($ue,array$Ri,$c,$Ah,$hm){if(!$Ri)return
true;if($Ri==["ALL PRIVILEGES","GRANT OPTION"]){if($ue)return(bool)queries("GRANT ALL PRIVILEGES ON $Ah TO $hm WITH GRANT OPTION");else
return
queries("REVOKE ALL PRIVILEGES ON $Ah FROM $hm")&&queries("REVOKE GRANT OPTION ON $Ah FROM $hm");}if($Ri==["GRANT OPTION","PROXY"]){if($ue)return(bool)queries("GRANT PROXY ON $Ah TO $hm WITH GRANT OPTION");else
return(bool)queries("REVOKE PROXY ON $Ah FROM $hm");}return(bool)queries(($ue?"GRANT ":"REVOKE ").preg_replace('~(GRANT OPTION)\([^)]*\)~','$1',implode("$c, ",$Ri).$c)." ON $Ah ".($ue?"TO ":"FROM ").$hm);}function
drop_create($Tc,$cc,$Uc,$ol,$Vc,$x,$Ig,$Gg,$Hg,$zh,$ih){if($_POST["drop"])query_redirect($Tc,$x,$Ig);elseif($zh=="")query_redirect($cc,$x,$Hg);elseif($zh!=$ih){$fc=queries($cc);queries_redirect($x,$Gg,$fc&&queries($Tc));if($fc)queries($Uc);}else
queries_redirect($x,$Gg,queries($ol)&&queries($Vc)&&queries($Tc)&&queries($cc));}function
create_trigger($Ah,array$Il){$xl=" $Il[Timing] $Il[Event]".(preg_match('~ OF~',$Il["Event"])?" $Il[Of]":"");return"CREATE TRIGGER ".idf_escape($Il["Trigger"]).(DIALECT=="mssql"?$Ah.$xl:$xl.$Ah).rtrim(" $Il[Type]\n$Il[Statement]",";").";";}function
create_routine($_j,$K){$kk=[];$k=(array)$K["fields"];ksort($k);$af=implode("|",Driver::get()->getInOut());foreach($k
as$j){if($j["field"]!="")$kk[]=(preg_match("~^($af)\$~",$j["inout"])?"$j[inout] ":"").idf_escape($j["field"]).process_type($j,"CHARACTER SET");}$xc=rtrim($K["definition"],";");return"CREATE $_j ".idf_escape(trim($K["name"]))." (".implode(", ",$kk).")".($_j=="FUNCTION"?" RETURNS".process_type($K["returns"],"CHARACTER SET"):"").($K["language"]?" LANGUAGE $K[language]":"").(DIALECT=="pgsql"?" AS ".q($xc):"\n$xc;");}function
remove_definer($H){return
preg_replace('~^([A-Z =]+) DEFINER=`'.preg_replace('~@(.*)~','`@`(%|\1)',logged_user()).'`~','\1',$H);}function
format_foreign_key($n){$Bh=implode("|",Driver::get()->getOnActions());$g=$n["db"];$nh=$n["ns"];return" FOREIGN KEY (".implode(", ",array_map('AdminNeo\idf_escape',$n["source"])).") REFERENCES ".($g!=""&&$g!=$_GET["db"]?idf_escape($g).".":"").($nh!=""&&$nh!=$_GET["ns"]?idf_escape($nh).".":"").idf_escape($n["table"])." (".implode(", ",array_map('AdminNeo\idf_escape',$n["target"])).")".(preg_match("~^($Bh)\$~",$n["on_delete"])?" ON DELETE $n[on_delete]":"").(preg_match("~^($Bh)\$~",$n["on_update"])?" ON UPDATE $n[on_update]":"").(isset($n["deferrable"])?" $n[deferrable]":"");}function
tar_file($m,TmpFile$_l){$Ie=pack("a100a8a8a8a12a12",$m,644,0,0,decoct($_l->getSize()),decoct(time()));$vb=8*32;for($p=0;$p<strlen($Ie);$p++)$vb+=ord($Ie[$p]);$Ie
.=sprintf("%06o",$vb)."\0 ";echo$Ie,str_repeat("\0",512-strlen($Ie));$_l->send();echo
str_repeat("\0",511-($_l->getSize()+511)%512);}function
doc_link(array$ui,$pl="<sup>?</sup>"){if(!(isset($ui[DIALECT])?$ui[DIALECT]:null))return"";$qm=doc_version();$cm=['sql'=>"https://dev.mysql.com/doc/refman/$qm/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(Connection::get()->isCockroachDB()?"current":$qm)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://www.oracle.com/pls/topic/lookup?ctx=db".str_replace(".","",$qm)."&id=",'elastic'=>"https://www.elastic.co/guide/en/elasticsearch/reference/$qm/",];if(Connection::get()->isMariaDB()){$cm['sql']="https://mariadb.com/docs/server/";$ui['sql']=isset($ui['mariadb'])?$ui['mariadb']:str_replace(".html","",$ui['sql']);}return"<a href='".h($cm[DIALECT].$ui[DIALECT].(DIALECT=='mssql'?"?view=sql-server-ver$qm":""))."'".target_blank().">$pl</a>";}function
doc_version(){return
preg_replace('~^(\d\.?\d).*~s','\1',Connection::get()->getVersion());}function
db_size($g){if(!Connection::get()->selectDatabase($g))return"?";$J=0;foreach(table_status()as$R)$J+=$R["Data_length"]+$R["Index_length"];return
format_number($J);}function
set_utf8mb4($cc){static$kk=false;if(!$kk&&preg_match('~\butf8mb4~i',$cc)){$kk=true;echo"SET NAMES ".charset(Connection::get()).";\n\n";}}error_reporting(E_ALL&~E_DEPRECATED);set_error_handler(function($pd,$i){return(bool)preg_match('~^Undefined (array key|offset|index)~',$i);},E_WARNING|E_NOTICE);;$Td=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($Td||ini_get("filter.default_flags")){foreach(['_GET','_POST','_COOKIE','_SERVER']as$X){$Wl=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($Wl)$$X=$Wl;}}if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");class
Server{private$params;private$key;function
__construct(array$gi,$t=null){$this->params=$gi;$this->key=$t;}function
getKey(){return
isset($this->key)?$this->key:substr(md5($this->getDriver().$this->getServer()),0,8);}function
getDriver(){return$this->params["driver"];}function
getServer(){return
isset($this->params["server"])?$this->params["server"]:"";}function
getDatabase(){return
isset($this->params["database"])?$this->params["database"]:"";}function
getName(){return
isset($this->params["name"])?$this->params["name"]:(isset($this->params["server"])?$this->params["server"]:"");}function
getUsername(){return
isset($this->params["username"])?$this->params["username"]:"";}function
getPassword(){return
isset($this->params["password"])?$this->params["password"]:"";}function
hasCredentials(){return$this->getUsername()!=""||$this->getPassword()!="";}function
getConfigParams(){$gi=isset($this->params["config"])?$this->params["config"]:[];$te=["servers"];foreach($te
as$fi){if(isset($gi[$fi]))unset($gi[$fi]);}return$gi;}}class
Config{static$NavigationSimple="simple";static$NavigationDual="dual";static$NavigationHover="hover";static$NavigationReversed="reversed";private$params;private$servers=[];function
__construct(array$gi){$this->params=$gi;if(isset($this->params["servers"])){foreach($this->params["servers"]as$t=>$N){$ck=new
Server($N,is_string($t)?$t:null);$this->params["servers"][$t]=$ck;$this->servers[$ck->getKey()]=$ck;}}}function
getTheme(){return
isset($this->params["theme"])?$this->params["theme"]:"default";}function
getColorVariant(){return
isset($this->params["colorVariant"])?$this->params["colorVariant"]:"blue";}function
getCssUrls(){return$this->parseList(isset($this->params["cssUrls"])?$this->params["cssUrls"]:[]);}function
getJsUrls(){return$this->parseList(isset($this->params["jsUrls"])?$this->params["jsUrls"]:[]);}function
getNavigationMode(){return
isset($this->params["navigationMode"])?$this->params["navigationMode"]:self::$NavigationSimple;}function
isNavigationSimple(){return$this->getNavigationMode()==self::$NavigationSimple;}function
isNavigationDual(){return$this->getNavigationMode()==self::$NavigationDual;}function
isNavigationReversed(){return$this->getNavigationMode()==self::$NavigationReversed;}function
isSelectionPreferred(){return
isset($this->params["preferSelection"])?$this->params["preferSelection"]:false;}function
isJsonValuesDetection(){return
isset($this->params["jsonValuesDetection"])?$this->params["jsonValuesDetection"]:false;}function
isJsonValuesAutoFormat(){return
isset($this->params["jsonValuesAutoFormat"])?$this->params["jsonValuesAutoFormat"]:false;}function
isRelationLinks(){return
isset($this->params["relationLinks"])?$this->params["relationLinks"]:false;}function
getRecordsPerPage(){return(int)(isset($this->params["recordsPerPage"])?$this->params["recordsPerPage"]:50);}function
getEnumAsSelectThreshold(){if(array_key_exists("enumAsSelectThreshold",$this->params))return$this->params["enumAsSelectThreshold"]!==null?(int)$this->params["enumAsSelectThreshold"]:null;else
return
5;}function
isVersionVerificationEnabled(){return
isset($this->params["versionVerification"])?$this->params["versionVerification"]:true;}function
isSqlAutocompletionEnabled(){return
isset($this->params["sqlAutocompletion"])?$this->params["sqlAutocompletion"]:true;}function
getHiddenDatabases(){return$this->parseList(isset($this->params["hiddenDatabases"])?$this->params["hiddenDatabases"]:[]);}function
getHiddenSchemas(){return$this->parseList(isset($this->params["hiddenSchemas"])?$this->params["hiddenSchemas"]:[]);}function
getVisibleCollations(){return$this->parseList(isset($this->params["visibleCollations"])?$this->params["visibleCollations"]:[]);}function
getDefaultDriver(array$Sc){$Qc=isset($this->params["defaultDriver"])?$this->params["defaultDriver"]:null;return$Qc&&isset($Sc[$Qc])?$Qc:key($Sc);}function
getDefaultServer(){$N=isset($this->params["defaultServer"])?$this->params["defaultServer"]:null;if($N===null)return
null;$ck=isset($this->params["servers"][$N])?$this->params["servers"][$N]:null;if($ck)return$ck->getKey();return$N;}function
getDefaultDatabase(){return
isset($this->params["defaultDatabase"])?$this->params["defaultDatabase"]:null;}function
getDefaultPasswordHash(){return
isset($this->params["defaultPasswordHash"])?$this->params["defaultPasswordHash"]:null;}function
getSslKey(){return
isset($this->params["sslKey"])?$this->params["sslKey"]:null;}function
getSslCertificate(){return
isset($this->params["sslCertificate"])?$this->params["sslCertificate"]:null;}function
getSslCaCertificate(){return
isset($this->params["sslCaCertificate"])?$this->params["sslCaCertificate"]:null;}function
getSslTrustServerCertificate(){return
isset($this->params["sslTrustServerCertificate"])?$this->params["sslTrustServerCertificate"]:null;}function
getSslEncrypt(){return
isset($this->params["sslEncrypt"])?$this->params["sslEncrypt"]:null;}function
getSslMode(){return
isset($this->params["sslMode"])?$this->params["sslMode"]:null;}function
hasServers(){return
isset($this->params["servers"]);}function
getServerPairs(array$Sc){$qk=null;foreach($this->servers
as$N){if(!isset($Sc[$N->getDriver()]))continue;if(!$qk)$qk=$N->getDriver();elseif($N->getDriver()!=$qk){$qk=null;break;}}$dk=[];foreach($this->servers
as$t=>$N){if(!isset($Sc[$N->getDriver()]))continue;$bk=$N->getName();if($qk&&$bk)$dk[$t]=$bk;else$dk[$t]=$Sc[$N->getDriver()].($bk!=""?" - $bk":"");}return$dk;}function
getServer($ak){return
isset($this->servers[$ak])?$this->servers[$ak]:null;}function
applyServer($N){$N=$this->getServer($N);if(!$N)return;$this->params=array_merge($this->params,$N->getConfigParams());}private
function
parseList($lg){if(is_array($lg))return$lg;return
preg_split('~\s*,\s*~',(string)$lg);}}class
Settings{private
static$CookieName="neo_settings";static$ColorSchemeLight="light";static$ColorSchemeDark="dark";static$NavigationWidthMin=10;static$NavigationWidthMax=30;private$config;private$params=[];function
__construct(Config$Sb){$this->config=$Sb;if(isset($_COOKIE[self::$CookieName])){parse_str($_COOKIE[self::$CookieName],$this->params);$this->save();}if(isset($_COOKIE["neo_lang"])){$this->updateParameter("lang",$_COOKIE["neo_lang"]);unset($_COOKIE["neo_lang"]);cookie("neo_lang","",-3600);}}static
function
readParameter($t){parse_str(isset($_COOKIE[self::$CookieName])?$_COOKIE[self::$CookieName]:"",$gi);return
isset($gi[$t])?$gi[$t]:null;}function
getParameter($t,$h=null){return
isset($this->params[$t])?$this->params[$t]:$h;}function
updateParameter($t,$Y){$this->updateParameters([$t=>$Y]);}function
updateParameters(array$gi){$this->params=array_filter(array_merge($this->params,$gi),function($Y){return$Y!==null;});$this->save();}private
function
save(){cookie(self::$CookieName,http_build_query($this->params),7776000);}function
getColorScheme(){return$this->getParameter("colorScheme");}function
getNavigationMode(){return($ra=$this->getParameter("navigationMode"))!==null?$ra:$this->config->getNavigationMode();}function
isNavigationSimple(){return$this->getNavigationMode()==Config::$NavigationSimple;}function
isNavigationDual(){return$this->getNavigationMode()==Config::$NavigationDual;}function
isNavigationHover(){return$this->getNavigationMode()==Config::$NavigationHover;}function
isNavigationReversed(){return$this->getNavigationMode()==Config::$NavigationReversed;}function
getNavigationWidth(){$Fm=$this->getParameter("navigationWidth");if($Fm===null)return
null;return
min(max((float)$Fm,self::$NavigationWidthMin),self::$NavigationWidthMax);}function
isSelectionPreferred(){return($ra=$this->getParameter("preferSelection"))!==null?$ra:$this->config->isSelectionPreferred();}function
isRelationLinks(){return
isset($this->params["relationLinks"])?$this->params["relationLinks"]:$this->config->isRelationLinks();}function
getRecordsPerPage(){return($ra=$this->getParameter("recordsPerPage"))!==null?$ra:$this->config->getRecordsPerPage();}function
getEnumAsSelectThreshold(){$Y=$this->getParameter("enumAsSelectThreshold");if($Y<0)return
null;return$Y!==null?(int)$Y:$this->config->getEnumAsSelectThreshold();}}class
Hash{static
function
hkdf($u,$t,$ff="",$Ej=""){if(extension_loaded("hash")&&PHP_VERSION_ID>=70120)return
hash_hkdf("sha1",$t,$u,$ff,$Ej);if($Ej=="")$Ej=str_repeat("\0",20);$Si=self::hmacSha1($t,$Ej);$wh="";for($If="",$bb=1;!isset($wh[$u-1]);$bb++){$If=self::hmacSha1($If.$ff.chr($bb),$Si);$wh
.=$If;}return
substr($wh,0,$u);}static
function
hmacSha1($e,$t){if(!extension_loaded("hash"))return
hash_hmac("sha1",$e,$t,true);if(strlen($t)>64)$t=sha1($t,true);$t=str_pad($t,64,"\0");$uf=($t^str_repeat("\x36",64));$Fh=($t^str_repeat("\x5C",64));return
sha1($Fh.sha1($uf.$e,true),true);}}class
Random{static
function
strongKey(){return
strtr(rtrim(base64_encode(Random::bytes(32)),"="),"+/","-_");}static
function
bytes($u){if(PHP_VERSION_ID>=70000)return
random_bytes($u);$I=self::tryAlternatives($u);if($I!==false)return$I;$I=self::lastResortRandom($u);if($I!==false)return$I;throw
new
Exception("Error generating random bytes");}private
static
function
tryAlternatives($u){if(extension_loaded("libsodium"))return
\Sodium\randombytes_buf($u);$Vl=DIRECTORY_SEPARATOR==="/";if($Vl){$I=self::readDevUrandom($u);if($I!==false)return$I;}$fb=$Vl&&PHP_VERSION_ID>50609&&PHP_VERSION_ID<50613;if(extension_loaded("mcrypt")&&!$fb){$I=mcrypt_create_iv($u,MCRYPT_DEV_URANDOM);if($I!==false)return$I;}$gb=PHP_VERSION_ID<50444||(PHP_VERSION_ID>50500&&PHP_VERSION_ID<50528)||(PHP_VERSION_ID>50600&&PHP_VERSION_ID<50612);if(extension_loaded("openssl")&&!$gb){$I=openssl_random_pseudo_bytes($u,$Fk);if($Fk)return$I;}return
false;}private
static
function
readDevUrandom($u){static$l=null;if($l===null)$l=@fopen("/dev/urandom","rb");if(!$l)return
false;$pj=$u;$I="";do{$e=fread($l,$pj);if($e===false)return
false;$pj-=strlen($e);$I
.=$e;}while($pj>0);return$I;}private
static
function
readCapicom($u){$Hb=new
\COM("CAPICOM.Utilities.1");$pj=$u;$I="";do{$e=base64_decode((string)$Hb->GetRandom($u,0));$pj-=strlen($e);$I
.=$e;}while($pj>0);return$I;}private
static
function
lastResortRandom($u){static$t=null;static$Ej=null;if($t===null){$e=$_SERVER;$e[]=uniqid("",true);shuffle($e);$t=sha1(serialize($e),true);if(extension_loaded("openssl"))$Ej=openssl_random_pseudo_bytes(20);else{$Ej="";for($p=0;$p<20;$p++)$Ej
.=chr((mt_rand()^mt_rand())%256);}}else{if((ord($t)%2===0)===(ord($Ej)%2===0))$t=Hash::hmacSha1($t,$Ej);else$Ej=Hash::hmacSha1($Ej,$t);}return
Hash::hkdf($u,$t,"$u",$Ej);}}if(!function_exists("str_starts_with")){function
str_starts_with($He,$eh){return
strpos($He,$eh)===0;}}if(!function_exists("str_contains")){function
str_contains($He,$eh){return
strpos($He,$eh)!==false;}}if(!function_exists("password_verify")){function
password_verify($F,$Ge){return
false;}}if(!function_exists("ini_set")){function
ini_set($Lh,$Y){return
false;}}function
version(){return
VERSION;}function
idf_unescape($We){if(!preg_match('~^[`\'"[]~',$We))return$We;$Uf=substr($We,-1);return
str_replace($Uf.$Uf,$Uf,substr($We,1,-1));}function
q($Ek){return
Connection::get()->quote($Ek);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
number_type(){return'((?<!o)int(?!er)|numeric|real|float|double|decimal|money)';}function
remove_slashes(array$nm,$Td=false){$J=[];foreach($nm
as$t=>$X)$J[stripslashes($t)]=(is_array($X)?remove_slashes($X,$Td):($Td?$X:stripslashes($X)));return$J;}function
bracket_escape($We,$Ta=false){static$Fl=[':'=>':1',']'=>':2','['=>':3','"'=>':4'];return
strtr($We,($Ta?array_flip($Fl):$Fl));}function
min_version($qm,$tg=null,$d=null){if(!$d)$d=Connection::get();if($tg&&$d->isMariaDB())$qm=$tg;return$qm&&$d->isMinVersion($qm);}function
charset(Connection$d){return($d->isMinVersion("5.5.3")?"utf8mb4":"utf8");}function
link_files($A,array$Sd){switch($A){case'favicon-red.ico':$m='favicon-red-c2ebb34a8df5aba28e15d87728a151df__aff407a3.ico';break;case'favicon-red.svg':$m='favicon-red-a006e401273230fd6be80568c8361b57__aff407a3.svg';break;case'apple-touch-icon-red.png':$m='apple-touch-icon-red-507228751d2170d047e72142d2c02390__aff407a3.png';break;case'logo.svg':$m='logo-de272eb4bdca9c6fffd38c073270fb1a__9d7e398f.svg';break;case'jush.css':$m='jush-b3a93b18444da26820ff61746521dede__72e4fe51.css';break;case'jush-dark.css':$m='jush-dark-f8dac59c6ad1018686e52a0e0357e421__2ec7793c.css';break;case'jush.js':$m='jush-615bc0b9720a1de8edd2c6876a3495b6__aab91337.js';break;case'icons.svg':$m='icons-70163a2695280bf75edba563e7b5471b__2ec7793c.svg';break;case'default-red.css':$m='default-red-9c7de6d1d78ea798bfef943c92b6b611__0c4866a9.css';break;case'default-red-dark.css':$m='default-red-dark-aa471f32fb495651c17bba291cd8b147__7a7f64b1.css';break;case'main.js':$m='main-eaf2ce2c3d91edbef355936903e47e59__e62e765a.js';break;default:$m=null;break;}if(!$m)return
null;return
BASE_URL."?file=".urldecode($m);}function
ini_bool($Lh){$X=ini_get($Lh);return
preg_match('~^(on|true|yes)$~i',$X)||(int)$X;}function
ini_bytes($hf){$X=ini_get($hf);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
sid(){static$J;if($J===null)$J=(session_id()&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
save_driver_name($Qc,$N,$A){restart_session();$_SESSION["drivers"][$Qc][$N]=$A;stop_session();}function
get_driver_name($Qc,$N=null){return
isset($_SESSION["drivers"][$Qc][$N])?$_SESSION["drivers"][$Qc][$N]:Drivers::get($Qc);}function
save_login($Qc,$N,$V,$F,$g=""){$t=isset($_COOKIE["neo_key"])?$_COOKIE["neo_key"]:null;$_SESSION["pwds"][$Qc][$N][$V]=$t?[encrypt_string($F,$t)]:$F;$_SESSION["db"][$Qc][$N][$V][$g]=true;}function
delete_login($Qc,$N,$V){unset($_SESSION["pwds"][$Qc][$N][$V]);unset($_SESSION["db"][$Qc][$N][$V]);}function
get_password(){$F=get_session("pwds");if(is_array($F))return$_COOKIE["neo_key"]?decrypt_string($F[0],$_COOKIE["neo_key"]):false;return$F;}function
get_vals($H,$b=0){$J=[];$I=Connection::get()->query($H);if(is_object($I)){while($K=$I->fetchRow())$J[]=$K[$b];}return$J;}function
get_key_vals($H,$d=null,$lk=true){if(!$d)$d=Connection::get();$J=[];$I=$d->query($H);if(is_object($I)){while($K=$I->fetchRow()){if($lk)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$d=null,$i="<p class='error'>"){if(!$d)$d=Connection::get();$J=[];$I=$d->query($H);if(is_object($I)){while($K=$I->fetchAssoc())$J[]=$K;}elseif(!$I&&!is_object($d)&&$i&&(defined("AdminNeo\PAGE_HEADER")||$i=="-- "))echo$i.error()."\n";return$J;}function
unique_array(array$K,array$s){foreach($s
as$r){if(!preg_match("~PRIMARY|UNIQUE~",$r["type"])&&!$r["partial"])continue;$Sl=[];foreach($r["columns"]as$t){if(!isset($K[$t]))continue
2;$Sl[$t]=$K[$t];}return$Sl;}return
null;}function
escape_key($t){if(preg_match('(^([\w(]+)('.str_replace("_",".*",preg_quote(idf_escape("_"))).')([ \w)]+)$)',$t,$y))return$y[1].idf_escape(idf_unescape($y[2])).$y[3];return
idf_escape($t);}function
where($Z,$k=[]){$Rb=[];foreach((array)$Z["where"]as$t=>$X){$t=bracket_escape($t,true);$b=escape_key($t);$Od=isset($k[$t]["type"])?$k[$t]["type"]:null;$ne=isset($k[$t]["full_type"])?$k[$t]["full_type"]:null;if(DIALECT=="sql"&&$Od=="json")$Rb[]="$b = CAST(".q($X)." AS JSON)";elseif(DIALECT=="pgsql"&&preg_match('~^jsonb?$~',$ne))$Rb[]="$b::jsonb = ".q($X)."::jsonb";elseif(DIALECT=="sql"&&is_numeric($X)&&strpos($X,".")!==false)$Rb[]="$b LIKE ".q($X);elseif(DIALECT=="mssql"&&strpos($Od,"datetime")===false)$Rb[]="$b LIKE ".q(preg_replace('~[_%[]~','[\0]',$X));else$Rb[]="$b = ".(isset($k[$t])?unconvert_field($k[$t],q($X)):q($X));if(DIALECT=="sql"&&preg_match('~char|text~',$Od)&&preg_match("~[^ -@]~",$X))$Rb[]="$b = ".q($X)." COLLATE ".charset(Connection::get())."_bin";}foreach((array)$Z["null"]as$t)$Rb[]=escape_key($t)." IS NULL";return
implode(" AND ",$Rb);}function
where_check($X,$k=[]){parse_str($X,$qb);remove_slashes([&$qb]);return
where($qb,$k);}function
where_link($p,$b,$Y,$Ih="="){return"&where%5B$p%5D%5Bcol%5D=".urlencode($b)."&where%5B$p%5D%5Bop%5D=".urlencode(($Y!==null?$Ih:"IS NULL"))."&where%5B$p%5D%5Bval%5D=".urlencode($Y);}function
convert_fields(array$c,array$k,array$M=[]){$I="";foreach($c
as$t=>$X){if($M&&!in_array(idf_escape($t),$M))continue;$La=convert_field($k[$t]);if($La)$I
.=", $La AS ".idf_escape($t);}return$I;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),[";"=>"%3B",","=>"%2C"]);}function
cookie($A,$Y,$eg=2592000){header("Set-Cookie: $A=".rawurlencode($Y).($eg?"; expires=".gmdate("D, d M Y H:i:s",time()+$eg)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"")."; HttpOnly; SameSite=lax",false);}function
get_url($bm,$Yb){$J=@file_get_contents($bm,false,$Yb);if(function_exists('http_get_last_response_headers'))$http_response_header=($ra=http_get_last_response_headers())!==null?$ra:[];return[$J,isset($http_response_header)?$http_response_header:[]];}function
get_settings($ac="neo_settings"){parse_str(isset($_COOKIE[$ac])?$_COOKIE[$ac]:"",$O);return$O;}function
get_setting($t,$ac="neo_settings"){$O=get_settings($ac);return
isset($O[$t])?$O[$t]:null;}function
save_settings(array$O,$ac="neo_settings"){cookie($ac,http_build_query($O+get_settings($ac)));}function
restart_session(){if(!ini_bool("session.use_cookies")&&session_status()==PHP_SESSION_NONE)session_start();}function
stop_session($be=false){$fm=ini_bool("session.use_cookies");if(!$fm||$be){session_write_close();if($fm&&ini_set("session.use_cookies","0")===false)session_start();}}function&get_session($t){return$_SESSION[$t][DRIVER][SERVER][$_GET["username"]];}function
set_session($t,$X){$_SESSION[$t][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($pm,$N,$V,$g=null){$am=remove_from_uri(implode("|",array_keys(Drivers::getList()))."|username|ext|".($g!==null?"db|":"").($pm=='mssql'||$pm=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$am,$y);return"$y[1]?".(sid()?session_name()."=".urlencode(session_id())."&":"").urlencode($pm)."=".urlencode($N)."&".($_GET["ext"]?"ext=".urlencode($_GET["ext"])."&":"")."username=".urlencode($V).($g!=""?"&db=".urlencode($g):"").($y[2]?"&$y[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($x,$_=null){if($_!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($x!==null?$x:$_SERVER["REQUEST_URI"]))][]=$_;}if($x!==null){if($x=="")$x=".";header("Location: $x");exit;}}function
query_redirect($H,$x,$_,$gj=true,$wd=true,$Gd=false,$vl=""){if($wd){$Ak=microtime(true);$Gd=!Connection::get()->query($H);$vl=format_time($Ak);}$xk=$H?Admin::get()->formatMessageQuery($H,$vl,$Gd):"";if($Gd){Admin::get()->addError(error().$xk.script("initToggles();"));return
false;}if($gj)redirect($x,$_.$xk);return
true;}function
queries_redirect($x,$_,$gj){$Xi=implode("\n",Queries::$queries);$vl=format_time(Queries::$start);return
query_redirect($Xi,$x,$_,$gj,false,!$gj,$vl);}class
Queries{static$queries=[];static$start=0.0;}function
queries($H){if(!Queries::$start)Queries::$start=microtime(true);if(support("sql")){Queries::$queries[]=(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";";return
Connection::get()->query($H);}else{Queries::$queries[]=$H;return[];}}function
apply_queries($H,array$S,$rd='AdminNeo\table'){foreach($S
as$Q){if(!queries("$H ".$rd($Q)))return
false;}return
true;}function
format_time($Ak){return
lang(101,max(0,microtime(true)-$Ak));}function
relative_uri(){return
str_replace(":","%3a",preg_replace('~^[^?]*/([^?]*)~','\1',$_SERVER["REQUEST_URI"]));}function
remove_from_uri($fi=""){return
substr(preg_replace("~(?<=[?&])($fi".(sid()?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_file($t,$sc=false,$zc=""){$l=$_FILES[$t];if(!$l)return
null;foreach($l
as$t=>$X)$l[$t]=(array)$X;$J='';foreach($l["error"]as$t=>$i){if($i)return$i;$A=$l["name"][$t];$Al=$l["tmp_name"][$t];$Wb=file_get_contents($sc&&preg_match('~\.gz$~',$A)?"compress.zlib://$Al":$Al);if($sc){$Ak=substr($Wb,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$Ak))$Wb=iconv("utf-16","utf-8",$Wb);elseif($Ak=="\xEF\xBB\xBF")$Wb=substr($Wb,3);}if($zc){if(!preg_match("~$zc\\s*\$~",$Wb))$Wb
.=";";$Wb
.="\n\n";}$J
.=$Wb;}return$J;}function
upload_error($i){$_g=($i==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($i?lang(102).($_g?" ".lang(103,$_g):""):lang(104));}function
repeat_pattern($vi,$u){return
str_repeat("$vi{0,65535}",$u/65535)."$vi{0,".($u%65535)."}";}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
format_number($X){return
strtr(number_format($X,0,".",lang(105)),preg_split('~~u',lang(106),-1,PREG_SPLIT_NO_EMPTY));}function
format_rows(array$R){$L=$R["Rows"];$Ia=($L&&(DIALECT=="sqlite"||(isset($R["Engine"])?$R["Engine"]:"")==(DIALECT=="pgsql"?"table":"InnoDB")));return($Ia?"~ ":"").format_number($L);}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($Q,$Id=false){$J=table_status($Q,$Id);return($J?reset($J):["Name"=>$Q]);}function
column_foreign_keys($Q){$J=[];foreach(Admin::get()->getForeignKeys($Q)as$n){foreach($n["source"]as$X)$J[$X][]=$n;}return$J;}function
fields_from_edit(){$J=[];foreach((array)$_POST["field_keys"]as$t=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$t];$_POST["fields"][$X]=$_POST["field_vals"][$t];}}foreach((array)$_POST["fields"]as$t=>$X){$A=bracket_escape($t,true);$J[$A]=["field"=>$A,"full_type"=>"varchar","type"=>"varchar","privileges"=>["insert"=>1,"update"=>1,"where"=>1,"order"=>1],"null"=>true,"auto_increment"=>($t==Driver::get()->primary),];}return$J;}function
dump_headers($Ue,$Wg=false){$Ue=friendly_url($Ue).date("-Ymd-His");$Cd=Admin::get()->sendDumpHeaders($Ue,$Wg);$bi=$_POST["output"];if($bi!="text")header("Content-Disposition: attachment; filename=$Ue.$Cd".($bi!="file"&&preg_match('~^[0-9a-z]+$~',$bi)?".$bi":""));session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$Cd;}function
dump_table_order(array$ch,array$mj){$Nf=array_flip($ch);$Ph=[];$ym=[];$kc=false;$xm=function($A)use(&$xm,&$Ph,&$ym,&$kc,$Nf,$mj){if(isset($Ph[$A]))return;if(isset($ym[$A])){$kc=true;return;}$ym[$A]=true;foreach(isset($mj[$A])?$mj[$A]:[]as$kj){if(isset($Nf[$kj]))$xm($kj);}unset($ym[$A]);$Ph[$A]=true;};foreach($ch
as$A)$xm($A);return($kc?null:array_keys($Ph));}function
dump_csv($K){$Ml=$_POST["format"]=="tsv";foreach($K
as$t=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($Ml?'\t':'[,;]|^$').'~',$X))$K[$t]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($Ml?"\t":";")),$K)."\r\n";}function
apply_sql_function($o,$b){return($o?($o=="unixepoch"?"DATETIME($b, '$o')":($o=="count distinct"?"COUNT(DISTINCT ":strtoupper("$o("))."$b)"):$b);}function
get_temp_dir(){$ti=ini_get("upload_tmp_dir");if(!$ti)$ti=sys_get_temp_dir();return$ti;}function
open_file_with_lock($m){if(is_link($m))return
null;$l=@fopen($m,"c+");if(!$l)return
null;@chmod($m,0660);if(!flock($l,LOCK_EX)){fclose($l);return
null;}return$l;}function
write_and_unlock_file($l,$e){rewind($l);fwrite($l,$e);ftruncate($l,strlen($e));unlock_file($l);}function
unlock_file($l){flock($l,LOCK_UN);fclose($l);}function
first(array$Ka){return
reset($Ka);}function
get_private_key($cc){$m=get_temp_dir()."/adminneo.key";if(!$cc&&!file_exists($m))return
false;$l=open_file_with_lock($m);if(!$l)return
false;$t=stream_get_contents($l);if(!$t){$t=Random::strongKey();write_and_unlock_file($l,$t);}else
unlock_file($l);return$t;}function
get_random_string(){return
Random::strongKey();}function
select_value($X,$w,$j,$rl){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$Jf=[];foreach($X
as$W)$Jf+=array_fill_keys(array_keys($W),null);foreach(array_keys($Jf)as$Ef)$J
.="<th>".h($Ef);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($Jf,$W)as$km)$J
.="<td>".select_value($km,$w,$j,$rl);}}else{foreach($X
as$Ef=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($Ef):"")."<td>".select_value($W,$w,$j,$rl);}return"<table>$J</table>";}$Jj="";if($j&&$X!==null&&($rl===null||strlen($X)<=$rl)&&($nm=Driver::get()->explodeArrayValue($X,$j["full_type"],$Jj))){$Ij=$j;$Ij["type"]=$Ij["full_type"]=$Jj;$J=select_array_value($nm,$X,$w,$Ij,$rl);return
Driver::get()->implodeArrayValues($J,$j["full_type"]);}if(!$w)$w=Admin::get()->getFieldValueLink($X,$j);if($j)$X=Connection::get()->formatValue($X,$j);$J=$j?Admin::get()->formatFieldValue($X,$j):$X;if($J!==null){if(!is_utf8($J))$J="\0";elseif($rl!=""&&is_shortable($j))$J=truncate_utf8($J,max(0,+$rl));else$J=h($J);}return
Admin::get()->formatSelectionValue($J,$w,$j,$X);}function
select_array_value(array$nm,$X,$w,array$j,$rl){$I=[];foreach($nm
as$Y){if(is_array($Y))$I[]=select_array_value($Y,$X,$w,$j,$rl);else{$Of=preg_replace('~(where%5B\d+%5D%5Bval%5D=)'.preg_quote(urlencode($X),"~")."~",'${1}'.urlencode($Y),$w);$I[]=select_value($Y,$Of,$j,$rl);}}return$I;}function
is_blob(array$j){$Pl=Driver::get()->getStructuredTypes();$U=lang(107);return
preg_match('~blob|bytea|raw|file'.(DIALECT=="mssql"?'|binary|image':'').'~',$j["type"])&&!in_array($j["type"],isset($Pl[$U])?$Pl[$U]:[]);}function
is_mail($Y){return
is_string($Y)&&filter_var($Y,FILTER_VALIDATE_EMAIL);}function
is_web_url($Y){if(!is_string($Y)||!preg_match('~^(https?:)?//~i',$Y))return
false;$Ob=parse_url($Y);if(!$Ob)return
false;$bm=$Y;if(isset($Ob['path'])){$jd=array_map('urlencode',explode('/',$Ob['path']));$bm=str_replace($Ob['path'],implode('/',$jd),$bm);}if(isset($Ob['query'])){parse_str($Ob['query'],$gi);$bm=str_replace($Ob['query'],http_build_query($gi),$bm);}if(!isset($Ob['scheme']))$bm="https:$bm";return(bool)filter_var($bm,FILTER_VALIDATE_URL);}function
is_shortable($j){return$j&&!preg_match('~'.number_type().'|date|time|year~',$j["type"]);}function
host_port($N){return(preg_match('~^(:([^:].*)|(\[(.+)]|(([^:]+://)?[^:]+))(:(\d+))?)$~',$N,$y)?[(isset($y[4])?$y[4]:"").(isset($y[5])?$y[5]:""),$y[2].(isset($y[8])?$y[8]:"")]:[$N,'']);}function
count_rows($Q,$Z,$wf,$xe){$H=" FROM ".table($Q).($Z?" WHERE ".implode(" AND ",$Z):"");return($wf&&(DIALECT=="sql"||count($xe)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$xe).")$H":"SELECT COUNT(*)".($wf?" FROM (SELECT 1$H GROUP BY ".implode(", ",$xe).") x":$H));}function
slow_query($H){$g=Admin::get()->getDatabase();$wl=Admin::get()->getQueryTimeout();$sk=Driver::get()->slowQuery($H,$wl);$d=null;if(!$sk&&support("kill")){$d=connect();if($d&&($g==""||$d->selectDatabase($g))){$Lf=$d->getValue(connection_id());echo'<script',nonce(),'>
	const timeout = setTimeout(() => {
		ajax(\'',js_escape(ME),'script=kill\', function() {
		}, \'kill=',$Lf,'&token=',get_token(),'\');
	}, ',1000*$wl,');
</script>
';}}ob_flush();flush();$J=@get_key_vals(($sk?:$H),$d,false);if($d){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$cj=rand(1,1e6);return($cj^$_SESSION["token"]).":$cj";}function
verify_token(){return true;}function
script($uk,$El="\n"){return"<script".nonce().">$uk</script>$El";}function
script_src($bm,$wc=false){return"<script src='".h($bm)."'".nonce().($wc?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
input_hidden($A,$Y=""){return"<input type='hidden' name='".h($A)."' value='".h($Y)."'>";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Ek){if($Ek===null||$Ek==="")return"";return
str_replace(["&","<","\"","'","\0"],["&amp;","&lt;","&quot;","&#039;","&#0;"],$Ek);}function
truncate_utf8($Ek,$u=80){if($Ek=="")return"";if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$u).")($)?)u",$Ek,$y))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$u).")($)?)",$Ek,$y);return
h($y[1]).(isset($y[2])?"":"<i>…</i>");}function
icon_solo($q){return
icon($q,"solo");}function
icon_chevron_down(){return
icon("chevron-down","chevron");}function
icon_chevron_right(){return
icon("chevron-down","chevron-right");}function
icon($q,$yb=null){$q=h($q);return"<svg class='icon ic-$q $yb'><use href='".link_files("icons.svg",[])."#$q'/></svg>";}function
checkbox($A,$Y,$tb,$Pf="",$Dh="",$yb="",$Rf=""){$J="<input type='checkbox' name='$A' value='".h($Y)."'".($tb?" checked":"").($Rf?" aria-labelledby='$Rf'":"").">".($Dh?script("qsl('input').onclick = function () { $Dh };",""):"");return($Pf!=""||$yb?"<label".($yb?" class='$yb'":"").">$J".h($Pf)."</label>":$J);}function
optionlist($C,$Uj=null,$gm=false){$J="";foreach($C
as$Ef=>$W){$Nh=[$Ef=>$W];if(is_array($W)){$J
.='<optgroup label="'.h($Ef).'">';$Nh=$W;}foreach($Nh
as$t=>$X)$J
.='<option'.($gm||is_string($t)?' value="'.h($t).'"':'').($Uj!==null&&($gm||is_string($t)?(string)$t:$X)===$Uj?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
html_select($A,$C,$Y="",$Ch="",$Rf="",$gm=false){static$Pf=0;$Qf="";if(!$Rf&&substr(isset($C[""])?$C[""]:"",0,1)=="("){$Pf++;$Rf="label-$Pf";$Qf="<option value='' id='$Rf'>".h($C[""]);unset($C[""]);}return"<select name='".h($A)."'".($Rf?" aria-labelledby='$Rf'":"").">".$Qf.optionlist($C,$Y,$gm)."</select>".($Ch?script("qsl('select').onchange = function () { $Ch };",""):"");}function
html_radios($A,$C,$Y=""){$I="<span class='labels'>";foreach($C
as$t=>$X)$I
.="<label><input type='radio' name='".h($A)."' value='".h($t)."'".($t==$Y?" checked":"").">".h($X)."</label>";$I
.="</span>";return$I;}function
confirm($_="",$Wj="qsl('input')"){return
script("$Wj.onclick = () => confirm('".($_?js_escape($_):lang(108))."');","");}function
print_fieldset_start($q,$ag,$Te,$vm=false,$tk=false){echo"<fieldset id='fieldset-$q' class='closable ".(!$vm?" closed":"")."'>","<legend><a href='#'>$ag</a></legend>",icon($Te,"fieldset-icon jsonly"),"<div class='fieldset-content".($tk?" sortable":"")."'>";}function
print_fieldset_end($q,$tk=false){echo"</div>",script("initFieldset('$q');","");if($tk)echo
script("initSortable('#fieldset-$q .fieldset-content');","");echo"</fieldset>\n";}function
bold($cb,$yb=""){return($cb?" class='$yb active'":($yb?" class='$yb'":""));}function
js_escape($Ek){return
addcslashes($Ek,"\r\n'\\/");}function
js_escape_key($Ek){return'"'.addcslashes($Ek,"\r\n\t\"\\/").'"';}function
js_escape_re($Ek){return
addcslashes(preg_quote($Ek,"/"),"\r\n");}function
pagination($E,$hc){return"<li>".($E==$hc?"<strong>".($E+1)."</strong>":'<a href="'.h(remove_from_uri("page").($E?"&page=$E".($_GET["next"]?"&next=".urlencode($_GET["next"]):""):"")).'">'.($E+1)."</a>")."</li>";}function
print_hidden_fields(array$Ti,array$Xe=[],$Ki=""){$I=false;foreach($Ti
as$t=>$X){if(!in_array($t,$Xe)){if(is_array($X))print_hidden_fields($X,[],$t);else{$I=true;echo
input_hidden($Ki?$Ki."[$t]":$t,$X);}}}return$I;}function
hidden_fields_get(){if(sid())echo
input_hidden(session_name(),session_id());if(SERVER!==null)echo
input_hidden(DRIVER,SERVER);echo
input_hidden("username",$_GET["username"]);}function
enum_input($Ma,array$j,$Y,$hd=null,$sb=false){preg_match_all("~'((?:[^']|'')*)'~",$j["length"],$z);$nm=$z[1];$ul=Admin::get()->getSettings()->getEnumAsSelectThreshold();$M=!$sb&&$ul!==null&&count($nm)>$ul;$U=$sb?"checkbox":"radio";$xa=$M?"selected":"checked";$I=$M?"<select $Ma>":"<span class='labels'>";if($M&&$j["null"]&&$hd!==""){$tb=$Y===null?$xa:"";$I
.="<option value='__adminneo_empty__' disabled $tb></option>";}if($hd!==null){$tb=(is_array($Y)?in_array($hd,$Y):$Y===$hd)?$xa:"";if($M)$I
.="<option value='$hd' $tb>".lang(109)."</option>";else$I
.="<label><input type='$U' $Ma value='$hd' $tb><i>".lang(109)."</i></label>";}foreach($nm
as$X){if($hd===""&&$X==="")continue;$X=stripcslashes(str_replace("''","'",$X));$tb=is_array($Y)?in_array($X,$Y):$Y===$X;$tb=$tb?$xa:"";$ie=$X===""?("<i>".lang(109)."</i>"):h(Admin::get()->formatFieldValue($X,$j));if($M)$I
.="<option value='".h($X)."' $tb>$ie</option>";else$I
.=" <label><input type='$U' $Ma value='".h($X)."' $tb>$ie</label>";}$I
.=$M?"</select>":"</span>";return$I;}function
input($j,$Y,$o,$Qa=false){$A=h(bracket_escape($j["field"]));$Pl=Driver::get()->getTypes();$xf=isset($j["full_type"])&&Admin::get()->detectJson($j["full_type"],$Y,true);$rj=(DIALECT=="mssql"&&$j["auto_increment"]&&!$_POST["clone"]);if($rj&&!$_POST["save"])$o=null;if(in_array($j["type"],Driver::get()->getUserTypes())){$od=type_values($Pl[$j["type"]]);if($od){$j["type"]="enum";$j["length"]=$od;}}$Ma=" name='fields[$A]' ".($Qa?" autofocus":"");$pe=(isset($_GET["select"])||$rj?["orig"=>lang(110)]:[])+Admin::get()->getFieldFunctions($j);$Fe=(in_array($o,$pe)||isset($pe[$o]));echo"<td class='function'>",Driver::get()->getUnconvertFunction($j)." ";if(count($pe)>1){$Uj=$o===null||$Fe?$o:"";echo"<select name='function[$A]'>".optionlist($pe,$Uj)."</select>",help_script_command("value.replace(/^SQL\$/, '')",true),script("qsl('select').onchange = functionChange;","");}else
echo
h(reset($pe));echo"</td><td>";$if=Admin::get()->getFieldInput(isset($_GET["edit"])?$_GET["edit"]:null,$j,$Ma,$Y,$o);if($if!="")echo$if;elseif(preg_match('~bool~',$j["type"]))echo"<input type='hidden'$Ma value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked='checked'":"")."$Ma value='1'>";elseif($j["type"]=="enum")echo
enum_input($Ma,$j,$Y);elseif($j["type"]=="set"){preg_match_all("~'((?:[^']|'')*)'~",$j["length"],$z);echo"<span class='labels'>";foreach($z[1]as$X){$X=stripcslashes(str_replace("''","'",$X));$tb=$Y!==null&&in_array($X,explode(",",$Y),true);$tb=$tb?"checked":"";$ie=$X===""?("<i>".lang(109)."</i>"):h(Admin::get()->formatFieldValue($X,$j));echo" <label><input type='checkbox' name='fields[$A][]' value='".h($X)."' $tb>$ie</label>";}echo"</span>";}elseif(is_blob($j)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$A'>";elseif($xf)echo"<textarea $Ma cols='50' rows='12' class='jush-json'>".h($Y).'</textarea>';elseif(($pl=preg_match('~text|lob|memo|json~i',$j["type"]))||preg_match("~\n~",$Y)){if($pl&&DIALECT!="sqlite")$Ma
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$Ma
.=" cols='30' rows='$L'";}echo"<textarea $Ma>".h($Y).'</textarea>';}else{$Cg=!preg_match('~int~',$j["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$j["length"],$y)?((preg_match("~binary~",$j["type"])?2:1)*$y[1]+($y[3]?1:0)+($y[2]&&!$j["unsigned"]?1:0)):($Pl&&$Pl[$j["type"]]?$Pl[$j["type"]]+($j["unsigned"]?0:1):0);if(DIALECT=='sql'&&Connection::get()->isMinVersion("5.6")&&preg_match('~time~',$j["type"]))$Cg+=7;echo"<input class='input'".((!$Fe||$o==="")&&preg_match('~(?<!o)int(?!er)~',$j["type"])&&!preg_match('~\[\]~',$j["full_type"])?" type='number'":"").($o!="now"?" value='".h($Y)."'":" data-last-value='".h($Y)."'").($Cg?" data-maxlength='$Cg'":"").(preg_match('~char|binary~',$j["type"])&&$Cg>20?" size='44'":"")."$Ma>";}$Ke=Admin::get()->getFieldInputHint($_GET["edit"],$j,$Y);if($Ke!="")echo" <span class='input-hint'>$Ke</span>";if(count($pe)>1)echo
script("qs('select', qsl('td').previousSibling).onchange();","");$Xd=0;foreach($pe
as$t=>$X){if($t===""||!$X)break;$Xd++;}if(count($pe)>1)echo
script("qsl('td').oninput = partial(skipOriginal, $Xd);");}function
process_input($j){$We=bracket_escape($j["field"]);$o=isset($_POST["function"][$We])?$_POST["function"][$We]:"";if($o=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?idf_escape($j["field"]):false);if($o=="NULL")return
Driver::get()->getNull();if(is_blob($j)&&ini_bool("file_uploads")){$l=get_file("fields-$We");if(!is_string($l))return
false;return
Driver::get()->quoteBinary($l);}$Y=isset($_POST["fields"][$We])?$_POST["fields"][$We]:(isset($_FILES["fields"]["name"][$We])?$_FILES["fields"]["name"][$We]:null);if($Y===null)return
false;if($j["auto_increment"]&&$Y=="")return
null;if($j["type"]=="set")$Y=implode(",",(array)$Y);if($o=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
Admin::get()->processFieldInput($j,$Y,$o);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$wj=$qd=[];foreach(table_status("",true)as$Q=>$R){$Zk=Admin::get()->getTableName($R);if(!isset($R["Engine"])||$Zk==""||($_POST["tables"]&&!in_array($Q,$_POST["tables"])))continue;$I=Connection::get()->query("SELECT".limit("1 FROM ".table($Q)," WHERE ".implode(" AND ",Admin::get()->processSelectionSearch(fields($Q),[])),1));if($I&&!$I->fetchRow())continue;$w=h(ME."select=".urlencode($Q)."&where[0][op]=".urlencode($_GET["where"][0]["op"])."&where[0][val]=".urlencode($_GET["where"][0]["val"]));if($I)$wj[]="<li><a href='$w'>".icon("search")."$Zk</a></li>";else$qd[]="<div class='error'><a href='$w'>$Zk</a>: ".error()."</div>";}if($wj)echo"<ul class='links'>\n",implode("\n",$wj),"</ul>\n";if($qd)echo
implode("\n",$qd),"\n";if(!$wj&&!$qd)echo"<p class='message'>".lang(78)."</p>\n";}function
help_script($pl,$pk=false){return
script("initHelpFor(qsl('select, input'), '".h($pl)."', $pk);","");}function
help_script_command($Ib,$pk=false){return
script("initHelpFor(qsl('select, input'), (value) => { return $Ib; }, $pk);","");}function
edit_form($Q,$k,$K,$Zl){$Zk=Admin::get()->getTableName(table_status1($Q,true));$T=$Zl?lang(38):lang(111);page_header("$T: $Zk",["select"=>[$Q,$Zk],$T]);if($K===false){echo"<p class='error'>".lang(89)."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$dd=false;if(!$k)echo"<p class='error'>".lang(112)."\n";else{echo"<table class='box'>".script("qsl('table').onkeydown = onEditingKeydown;");$Qa=!$_POST;foreach($k
as$A=>$j){echo"<tr><th>".Admin::get()->getFieldName($j);$t=bracket_escape($A);$h=isset($_GET["preset"][$t])?$_GET["preset"][$t]:null;if($h===null){$h=$j["default"];if($j["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$h,$oj))$h=$oj[1];if(DIALECT=="sql"&&preg_match('~binary~',$j["type"]))$h=bin2hex($h);}$Y=($K!==null?($K[$A]!=""&&DIALECT=="sql"&&preg_match("~enum|set~",$j["type"])&&is_array($K[$A])?implode(",",$K[$A]):(is_bool($K[$A])?+$K[$A]:$K[$A])):(!$Zl&&$j["auto_increment"]?"":(isset($_GET["select"])?false:$h)));if(!$_POST["save"]&&is_string($Y))$Y=Admin::get()->formatFieldValue($Y,$j);if(($Zl&&!isset($j["privileges"]["update"]))||$j["generated"]){echo"<td class='function'></td><td>";if($Zl||!$j["generated"])echo
select_value($Y,'',$j,null);else
echo"<code class='jush-".DIALECT."'>",h($Y),"</code>";echo"</td>";}else{$dd=true;$o=($_POST["save"]?isset($_POST["function"][$t])?$_POST["function"][$t]:"":($Zl&&preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$Zl&&$Y==$j["default"]&&preg_match('~^[\w.]+\(~',$Y))$o="SQL";if(preg_match("~time~",$j["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$o="now";}if($j["type"]=="uuid"&&$Y=="uuid()"){$Y="";$o="uuid";}if($Qa!==false)$Qa=($j["auto_increment"]||$o=="now"||$o=="uuid"?null:true);input($j,$Y,$o,(bool)$Qa);if($Qa)$Qa=false;}echo"\n";}if(!support("table")&&!fields($Q))echo"<tr>"."<th><input class='input' name='field_keys[]'>".script("qsl('input').oninput = fieldChange;","")."<td class='function'>".html_select("field_funs[]",Admin::get()->getFieldFunctions(["null"=>isset($_GET["select"])]))."<td><input class='input' name='field_vals[]'>"."\n";echo"</table>\n",script("initToggles(gid('form'));");}echo"<p>";if($dd){echo"<input type='submit' class='button default' value='".lang(113)."'>\n";if(!isset($_GET["select"]))echo"<input type='submit' class='button' name='insert' value='".($Zl?lang(114):lang(115))."' title='Ctrl+Shift+Enter'>\n",($Zl?script("qsl('input').onclick = function () { return !ajaxForm(this.form, '".lang(116)."…', this); };"):"");}echo($Zl?"<input type='submit' class='button' name='delete' value='".lang(117)."'>".confirm()."\n":"");if(isset($_GET["select"]))print_hidden_fields(["check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]]);echo
input_hidden("referer",isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"]),input_hidden("save","1"),input_token(),"</form>\n";}function
file_upload_form_script($fe,$jf){$vg=ini_get("max_file_uploads");$_g=ini_get("upload_max_filesize");$Ag=ini_bytes("upload_max_filesize");return
script("initFilesUploadForm('".js_escape($fe)."', '".js_escape($jf)."', "."$vg, '".lang(118,$vg,"\'max_file_uploads\'")."', "."$Ag, '".lang(119,$_g,"\'upload_max_filesize\'")."')");}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Ek){$Fa=array_flip(str_split(compress_alphabet()));$u=strlen($Ek);$mm=($u?13*($u-1)/2-$Fa[$Ek[0]]:0);$Ya="";$uj=0;$vj=0;for($p=1;$p<$u;$p+=2){$uj=($uj<<13)+$Fa[$Ek[$p]]*93+$Fa[$Ek[$p+1]];$vj+=13;while($vj>=8&&$mm>=8){$vj-=8;$mm-=8;$Ya
.=chr($uj>>$vj);$uj&=(1<<$vj)-1;}}if($Ya=="")return"";return
function_exists('gzinflate')?gzinflate($Ya):inflate($Ya);}function
inflate($Ya){$bg=[3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258];$cg=[0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0];$Jc=[1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577];$Lc=[0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13];$J="";$G=0;do{$Vd=inflate_bits($Ya,$G,1);$U=inflate_bits($Ya,$G,2);if(!$U){$G=($G+7)&~7;$u=inflate_bits($Ya,$G,16);$G+=16;$J
.=substr($Ya,$G>>3,$u);$G+=$u<<3;}else{if($U==1){$ng=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$Mc=array_fill(0,30,5);}else{$mg=inflate_bits($Ya,$G,5)+257;$Kc=inflate_bits($Ya,$G,5)+1;$D=[16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15];$Lg=array_fill(0,19,0);$Kg=inflate_bits($Ya,$G,4)+4;for($p=0;$p<$Kg;$p++)$Lg[$D[$p]]=inflate_bits($Ya,$G,3);$Mg=inflate_table($Lg);$dg=[];while(count($dg)<$mg+$Kc){$Pk=inflate_symbol($Ya,$G,$Mg);if($Pk==16)$dg=array_merge($dg,array_fill(0,inflate_bits($Ya,$G,2)+3,end($dg)));elseif($Pk==17)$dg=array_merge($dg,array_fill(0,inflate_bits($Ya,$G,3)+3,0));elseif($Pk==18)$dg=array_merge($dg,array_fill(0,inflate_bits($Ya,$G,7)+11,0));else$dg[]=$Pk;}$ng=array_slice($dg,0,$mg);$Mc=array_slice($dg,$mg);}$og=inflate_table($ng);$Oc=inflate_table($Mc);while(($Pk=inflate_symbol($Ya,$G,$og))!=256){if($Pk<256)$J
.=chr($Pk);else{$u=$bg[$Pk-257]+inflate_bits($Ya,$G,$cg[$Pk-257]);$Nc=inflate_symbol($Ya,$G,$Oc);$sh=strlen($J)-$Jc[$Nc]-inflate_bits($Ya,$G,$Lc[$Nc]);for($p=0;$p<$u;$p++)$J
.=$J[$sh+$p];}}}}while(!$Vd);return$J;}function
inflate_bits($Ya,&$G,$bc){$J=0;for($p=0;$p<$bc;$p++){$J+=((ord($Ya[$G>>3])>>($G&7))&1)<<$p;$G++;}return$J;}function
inflate_table(array$dg){$Q=[];$zb=0;for($Za=1;$Za<=max($dg);$Za++){foreach($dg
as$Pk=>$u){if($u==$Za){$Q[$Za][$zb]=$Pk;$zb++;}}$zb<<=1;}return$Q;}function
inflate_symbol($Ya,&$G,array$Q){$zb=0;$Za=0;do{$zb=($zb<<1)+inflate_bits($Ya,$G,1);$Za++;}while(!isset($Q[$Za][$zb]));return$Q[$Za][$zb];}if(isset($_GET["file"]))load_compiled_file($_GET["file"]);function
load_compiled_file($m){if($m==""){http_response_code(404);exit;}if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){http_response_code(304);exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression","1");$Cd=pathinfo($m,PATHINFO_EXTENSION);switch($Cd){case"css":header("Content-Type: text/css; charset=utf-8");break;case"js":header("Content-Type: text/javascript; charset=utf-8");break;case"ico":header("Content-Type: image/x-icon");break;case"png":header("Content-Type: image/png");break;case"svg":header("Content-Type: image/svg+xml");break;}switch($m){case'favicon-red-c2ebb34a8df5aba28e15d87728a151df__aff407a3.ico':$e='AAABAAEAICAAAAEAIAC7AQAAFgAAAIlQTkcNChoKAAAADUlIRFIAAAAgAAAAIAgGAAAAc3p69AAAAYJJREFUeNrV1wHkGnEUwPFztCYBRAQY20AAAgQBQpghwEUFhM2G6A4QAJksoMi2AQTZBhgcAUgthAS2yTFo5f2/OHCcv3v3I+EDcO/reI+f9eOZdVN3E2CjAhcL+NjjX2gPHwu4qMA2EfAUHv5AEvoND1ltwEv8gqS0xQtNwFeIIV80AVeIIVdNgBilCNhCDNloAtoQQ9raNWzhBFE6wUl7iEpwsUoweAUXpTSH6H1MTAMdDPAhNEAHjZih77RbMMQTWEpZDCG6AOCAHooJBhfRw0G/hvHrNEEfXbwKddHHBBtTd2Bz6zvwFmLIG01ADjNISjPk0tyBFo6QhI5w0tyB17BCNqoYY40AEhFgjTGqsCPfShzwH3VYMfJ4HsrDilHHRbuGZ4xQgJVQASOctWt4ifzeKZooPDK0iSkCCKD7A98hMQLs8DO0iwyM+qYJcCCGOJqADD5DUvqEjPYO2JhAlD7CNvEyqmGZYPASNRh/G5bhYQ4ff0M+5vBQvrfH6W09AE8YAEN5XivhAAAAAElFTkSuQmCC';break;case'favicon-red-a006e401273230fd6be80568c8361b57__aff407a3.svg':$e='+<bAU6+V?$so%eoa6[DcEe<SKeo.[BnWu^_0
-+j@96@+X_5GA4^m3%R;yn_USCF5vXi6B6jvyvvy?qZYfND@5~KR9wPw1q,+w{:cwGa2aY)<GPqWy/nLYzy>c3Au_/MA7,dc
}`rf-`x<FH$&bI]FGspJPra.)yE]$w~aKaM]on_y4%i2=`y?0`vYw6}rUy&B3JD1F
/B
o8ro30<1Tp4r,.qWnFpndQQq#ek`C+.f19/9#q+uNg4bc6H.9(02NJtu){yYINu`Uzs:%?o1GH"Lgtxvhu>9uq8)/:th8&TH,OT*]5<Ydlap!w8k>zUUDvh@h+)F+>T8=R`(;8*p2Il^<$eSAzo6lU8L_P_Yh-i#lD(4lV7"52"_UdLUTuCV+Yf/[^)f(~i
.,!Hkau}dsr
i84
>s)_:!#hJQ9:ex&|].#nB#/g7`Ds1xz$U+"f!p-.*YXigq8vUBF!9lVoojveL
+8ox,X;hOaXS*cTW+ODnn.]r,!3BBNvdJ~,brQumcAQJa9E)x*rt!$x~u(xCb
69OF`xI}1->oD?M:yg2R';break;case'apple-touch-icon-red-507228751d2170d047e72142d2c02390__aff407a3.png':$e='iVBORw0KGgoAAAANSUhEUgAAALQAAAC0CAIAAACyr5FlAAALAUlEQVR42uzSgQAAAAACoP2ln2CDYig9QA7kQA7kQA7kQA7kQA7kQA6QAzmQAzmQAzmQAzmQAzlADuRADuRADuRADuRADuQAOZADOZADOcbeOUe58yxR/Gfbtm3btm3btm3btrW2jWRtZG3MvM9TP/72O5vMTLqTuqf+GHRn55y+26iuWx3zGG+v7v7ukeZnT6i/d9fANWtXnL0AxgW3POQVBeKLHILJvvbOT24O3rBh+elzzdEoRmGqCDliHNOjg52f315xzoK0+qyMKlSkemySQzCQ/WnVxcvQ0mEb1fmR2CKHwJru/Ow2WtcVowvhB2OBHILp8eGmp46mUV00fpCfFXIY32coZrjMj6ePsS1LyGEwGAI8YIYaX+4Qchg8A6UJPTX+hJHkkFXr/12bVJ67SNfX9/3dGh87eOa2b3n5TFWYiv93/cIfEnLEyIBSffnKqow1PhK8YaMZyDFU+qsqTEUHg4sJ5BAfKI7wOZIDjDWXVZw1fyTkwD822d8h5AD2eFd7f2Fm+7cf1r/4QMUt5xecdWDucTtnHbpF+j7rpey8ctKWS/yxwXxY0lZLcstDXlGAYhW3XkAVKlJ9vLvD9hI4vGk2J+QAvUmvR0IOrPPTW+KUHBO9oe6E72ueuC3/9H1p+9/XncsV46cKztiv5snbuxN/mOzrsV0FGyLOycGKtPn5kyMhB2NTfJGjvyg78MC1GQds9Pt6c9OWnhp/IvOAjQMPXjdQnOPKXisN5owcavY6wGZs2OTAxtsDsU+Owcri4KM3pe+1Dm0WFUvfe93gozcPVZXY4YKtdofkmOxtU9ej9fnlZ8zz3+Qo+cUhOfijsUwOpgKFZx9E82hihecdGl5HQiiGQ3K0vnHBZG+rug398sx/FR4s+tEhOZqfOzE2yTFQkqtooZsVnnvIQFm+PRsQquOUHK+fT2FralJNPpqePPI/yJH/tUNy1N+7W8yRw7LqX3qIxQXNoK3xeQ2vPmo7BrMHh+To/PRWnnR8fJN6MjXcW33Fqv/lAHVCjsA168QUOViG0HWrNtDcii48crK/13YAPBwOyRH6+el/9BCFP6iHI4F0Vbgv7T2H5MDbETvkmB4fyz5iGyNooSzn6O2nJ8ZdJEdf6jv/8KlfsMREd4N63v3tQ39/3pvwSjySo/L2iwyihbKquy93cVgZyP1SPa+7cwdrakJt9Dc8vD8Pu394LO6GFfxOxtFCWSj1V7cmpEPFP6vnWPv7V6tXU/2dVZcuj+sz7iaklbddaC45qu66zK2lLD6u/3pLX6LeDpf/0fbGhXG3lMXvaS45sg7Z3IETLExyVJ636ERnrSowWpsTd04wQ2mhVrYO3OdhkgOrvW1ra3JclTHLfS7kmNfJxlvY5MDa3r5UlYm1jTchB1v2kZAD68/6WBWLqS17IQfBPjgeIiFHxbkLK6FsLAX7CDlUmGD45MBqbt7Mmhg1P0xQyBFWgDHxwzMHGDc+dkjcBRgnbLKQueRI3HwxkSZ4iLwTdzOXHPmn7i2iJg8RfORGc8lBNKvIIT0E4TMqhsMsS9hoAQIZoy6khhmxLKSue/4+E8lR//LD4cmpGV/cHE0sK5ZTMFjT03kn72kWM9BG0CqSvMUPTA0PFl98tCnMKLns+KmRITfSPt0RbtqnO+Is7ZNl1T17D7IRrR0b68+DVM5dsSQO75lVssooRmF8oHGaMK4n/ffsw7fWkxnZR27bk5ngWarJAFvthGIQqkMoF90DxgW3POQVBWxJNUkX0v7dRxn7rq8PLTL237Djx0/5MElSqwVQcLR9+Q7zPnryKA4iaGjbvnrPmpqyNYSkmhxrb2545REE8r6GeB22VcNrj411tNgCI1T2o0119CWkWkDo7Il++sBNSM3Q9tW7o831tkAHcuSesOtwXfWsRVA9XV2/f8M/d+UdFxeceQC5N/7YcH7nVKAwVUjUQXV+pOv3byd6u+1ZYjhYkXfS7kIOb7fsmXtGnhWDacFoSwP+7P6CDBQDXb9+1f71+y0fvtzy0StccNuT9huvKECxyOcQsDN9z7XVlr1XEHJg+aftowRk+gOJXt5Je/DZQg6fgn1yjtlxrK3J1h50PNlHbccHCzl8jQRL2WGFUMovtsboTvoxebvl+FQhR3TCBNmEC6X8rCEtmH6qjxRy+IEZfNWdP37Ghq0dVfABHd9/rDz6Qg6NAowRS7Z+9qY1OWH7Dv5oyyevZey3gfoYIYeO0ecpO65YesWJze89T+42bzc4LIu1btO7z5VcfgITIPUBQg4zpAnJ2y5LYp2G1x8ngxvuClc2cfoLs0jmVHTBEcnbLuOBNCEaEN1K4haL4yApu+bU6nuvJBaEroXtXJxdg+UFo62NUyP/iKzkgluCVXGOUYBiFK665woqUh15gde6FR0hoqaETRf2VBcj5PAWtJ+50gS6LiGHh8g5didzySEbb96i6s5LzSVH9X1XCTk8BOdamEsO3HRCDm9dkOT0NJEZHN2CX0TI4YMicl7jsoEpLaSQw1vUPH6rWeSoffpOCRP0DyjWzWHGXRJD6jca33xKf8UbDlY7KpDo885fvkzdZVU9mZG662pENdtAyBFFUTUnummVtINo9eDDNyjxtJAjyhiqLuXIpqiPMnxA4TkHDwXKbN0goiYkLdX3XZ20zdL+04LtezqwkfroyZeFHE4iiqdHR4gHQzebsPGCXnOCXdyCM/dv/fyt6bFR5x8v5PAEzP852s1hYVhC7DH/0O5KZxk7sg7bMvDQ9TQ2nHCerQqyCjk8xN+jIvpyU2etOQt19mYlNn/wErtfTAvQnznU41OMs2qpwoCFJI4fQb5mzxLU4rMx22tIsE/abquPd7ZFHhKM5BW9NY7tvvx0+hg2xjAuuOUhrygQebgy6vvUnVeRYB//IsHS9lxruKbS1h5DwfK03deQSDA/8F8LBDW+6Ine7GS1dBJyeI7/3e0kpzETT1szEKXMjJXPi3IMqQQYM2FkoqCVFpJRTwKM/cbMEZrsZUQzoMaySOyRe/wuEn2uqTSBJE8sONVaxh+Md7Q2v/8iSQRFmmCGboVzOnF/KT+Vm1AetuSfAvdfk3nwZqJb0YIcYQjOkLqQB4xcXq4E6g1WFKGFJKVkGPInVi62thDdSvL2y2cfsQ2p38quPZ1+hZMM2IXpTvi+vyibpID0MfQHXHDb/cd3rZ++QQGKUZgqJFaguuhW9CUHqSPNlSZU3nGJkMNDkO/AXHIwUxZyeAg2vVCcmsiMpK2WnOgNCTm8BWH+JpKDLXsJ9vEcU0MDKkWfKUayIZ9CSiVMkOxbZpGDUDEJE/QPbGsZc2LoY7fYhsH8Y7xKrzpZf2aUXXeG7T8k+pz05wT36swM1BIEkgk5onZAE6GdejKDgY/Ps6ML0a0Q+Jm05RJauTTQadoKQo6oy5k0OSaSMxJGGoK2JhByqKQ/bV+8nbbHmtGiBXIHTv9T0UZCDh1Pvml84wkSF/tJC5xy5IMw43wgOQBwcqCPAA50aV7TgrEMLuK0tQWmkEOB3PhkQ3A9hweqquCjNyHttwXmkkNNRxAksvVFNvuUnVYKc39k55XJsV/3wv2oURxMLIQcZoK89xwnHnz05vKbzim+6ChO5yMOFMUikX/EFHLBLQ95RQGc3yyVjThP7i/t0oEMAAAAwCB/63t8xZAcyIEcyIEcIAdyIAdyIAdyIAdyIMcLciAHciAHciAHciAHciAHyIEcyIEcyIEcyIEcyIEcIAdyIAdyIAdyIAdyIAdyQHtHp5xFOjNVAAAAAElFTkSuQmCC';break;case'logo-de272eb4bdca9c6fffd38c073270fb1a__9d7e398f.svg':$e='(]^+JbP.FqjXYdorFxH%oTmn1#,Na[(-^<}T{`+Ahl-RItQoM;{4bK}l["$V3F6U&V6Ey@S8#w=t>3kaN[hLow+fWEUH+K<LoXqyEy6JupFy-JyK4S8q(7tl96;KLl/F|,Cz)p?p(B)[axu/4u77-)nvU
R?vPex0x2ynqlE!VMsqy.7^Mtiv[hKzB^oh,VovqjM1XCS0v]mXW-smT}3TK7IVEL2YtHsc^Dne,}uyaN:]l/HJnieEbYSTw;KD$c_8p_B2y&,]pd?W+OvtUWi,FjFuW3Gsr=[=,k5ZhU;]w50sP*<)SM
tcO5=+WoZrY8Iq)IW=_gPo=RG*5hngIJV?j"daOWXS`x~L$e])]A/t{9it,:r%.89Z!;1rZhBw]6K6fQlvHN$Hw,QuiFcFpKmc{y#sO=!8QV,<+O&P/25]6vLiFL^ILo%v=7LZHx2=IpuT_qcxR7puVAY]-[aZk-!Hsk3@pU2?.=/khk7TY+8^U^mMe^&3|d[5+h9;Y
kr~/LPx3%=u>(#a3Hf@EX)<u
hpxoYBBVp`W(PvmMW
B#sK.gGL@Vd{:",35}yAFD8*Arm#eht>.nM#/VX$c0nfYn>@aFR7y~^p#M;>Hr]/"5-YOhURoN?g"zr)rf03v&=U+I-CNf2fyI`@2rCNwy$T>{3b.C"<mw^pUpNV.:1gW1HboUDhY6rSWb#t&3^ZZCWe([&88L?Tb:rJC{:,[0cUZh4Z?E>_4(eVbK+W4cj3K
6JZ,1OCPNi-r:-0+h9c@$6(OPFO,>/K_<D>?aD4|c[qNng
#]abQba^dg.vgT
jO4.nVHH3Y??RBOkYeEql7Z%i$fv:!`8=ol
<6HDyKdV^.GOQE<w848Z0)$;-[WOZ($QN.)/E#@[UhS3g@bs8$w@iRav#q,^!">riV0ad4mzAx-tm;I$7+G<hFV$knOjWB`9D:,!6.B`@~D~lLM@<M0y2w8SF<2z*Q?8suZ!O(%O"i>PX9(r?[=%/{TBK"Y5o,?wUbppvc%SDB9:2sH.!E?uV/?
m,@iTyWH"kU~.Qf,)]TyKwNyoX6LeQ(^HfM@6j
4o+qU-cQZ:uU]TVg=la`BE{x<YgRQys@]DNHkxs-[I/xZDH(tx~I,OKPNZ/@fA]-^.jOn630BkZbx.P^-,m);cooD1IAp.,``B4+,etGxX"U8fa;-m84^sKe*v>@/HAeYMWEKTQ)eqhf~:)bj!p<2bBA{<+-LC46:QPR:9CjzQATX#[YXUysw]
N.c{F{GlQ+bj=,TT-!C{[nb4XXv@IXBg4/"YW.M7"&I]1:iT"%EKDl:j![3j6cJm@H6qxXW2/Z3Cbs2d^_Mps>DM!ccnZ<i*Bk_oLtHcB*IHFOrym<(YWVBvJs)l@)0Z
=r:E0<}*va7n8dz1"9z&IAIi9Vql_/_GmWkv_:7+J@p:0<f]@QLtEi=rp`*wKM:5vfI1|nK.ne&[~?Dw9$GKV(o;/%`Hmip$>""Ue?0@$iQ%0E@-8u^"L:b>FLzv@>2F,<8Oa+M=?1oWnKWe[PvjmLPP1h}>?=m6-g]sv1UozX%`5v(*-1kTxb9=scVhWiuXQq$+!BPCVI)xDF&Cnc4ACZZ;UYX0(]s_GY!vk8WEz/4F"DLf=_6%>e[r;9[xM
*??SKd):Aiccqb{<(e68*v9Xya1
}IiKS_We9OJP11tEgIuGCfq=227bEC06#b8:]191/`0PF4dN3NCRTej;PMj)t1HQ
Jk-U9uH!E]5fjhHQ[+SE@:i^g{tA^Al~K<U3Js9&fM#B=^50#vEFbxFZ5L?Y3#pI^GKK[GYdMVSZS-kM<^><@^4f#(*V&b>jq3*^KjD3*Rj:sZUT"F5[bbKNE?X;A{TeBBBDDh+O^.lXKwEfA")l6+[^TWA?4gsuw|<
F:E?URQb2aF,p$7S90=|txQTehv2K|GQ]/#8t!]{/N<29Gp"TPCb9HnMc}q@$*7z?v`WcA(@>t%Q%t2zFCg.^la~3eLCq_$QqZ>erybXCLsr`Q)1Xvng-<,eXT8Gismd[Kh5k)PClZRUu<<uVag@F,=B#6wrEv$LS(Zs^CXo2:d/o%A%n/ZC1%4vJi9[DJ7|ViE>Q+(A:M5wRMExVg">y+d/OirXu6Z@>[`*:xk1k:,a64QavY*$xgkb=eYrj?%BUFsiBT>VTy`OsXZ8T]!(4(9)TVb`f![p</Q,?n.6x;Vcy,ezD|@X0Xca@ad["tI%:wj?P}^e*sm]oP?U`&OhkEg+TWAAc5FL5H.DImYqS
4fIvQ%7XhX2^!kthV*<ddA1ed`@9m6@mZ7)ocp_a%uQl@q0U??@Nf?_0.+DqepA/LGctQ1X(#=m3EmLVkn?I+7r~foFQN=BUF~8$nF"4
{9LE>C+$c%w8vSvNB?}93S#K4kkm/+t;`RE%e
;(Yq`=YE$3,5|@/mXG|%z7YbGWWmFH+IC;f:U$J6vpyr)hV7nU62e3PYwL~yj.31;lgq)EK$.>bGaY5`n6mlwn3@/u`vQ:9lc4J6.&&ga%^j!0joCA$LU&#g7trww.(CN=l2:G1CfPS:KH=d>Hfpi7[$X`,7wJZoJvRF?YKmSVzNW0`Q0FdOqAIS
n9lrNOU01c?:p/5y16+Zkgo}`M)D6xm>7RiQ_b%p%ocllip!0).myrT"w[53iGRZBt8z<.d6p9("_WYy1v;v.xBx%3c3&hfawJgMtuxeflyK1.-:wQo"f_z&)W';break;case'jush-b3a93b18444da26820ff61746521dede__72e4fe51.css':$e='+UEmPb3V?!K0u25Dm[994[Zg@N#Q)YOC=2R_hE~4)=>cbdia55M)rQq_opI7=E.gy$2_wn3[@yoG6r~P5/:mrvY<e>#2+8qezLLv^&nr;/Kkr(>?R(rf#PZ<Kx
br^LS(>/*E-?WzeLSW_J
;*l
(asND-)j;m4/f-BIQ%S$]jg`lK"7X[Woi<6n<ErGn[ASke
cM6fo
Ky:?d|y4Z`/MKF8_iz@9f#<b1@MaLgh0efOIpYz&+xn<6xNY
d<~>ajCRq4s@jh
caxtV~2DNi9ioWoHqA9#OBh[!x5h*jN+q=`bmxSYd@yVW[J$)|db!4XItVe2/XU=San(wzD4EHl0a(LT*+#/I{HkOQ@+p7OU>7LCKJ)XgXJ0ht%5=cOF]A#h3y>xj
CPbQe)?*P`i3V~D/=qVG-dKwTh&h0H`Bh6D#U{g+4O4p2=9CtsQ/6U+vL<<[BwoX?2A6[c[V]D4-0UY0<f68Rw&}-5Kr^"[Lrv)&Bo_Q>]coooyj>sL9EEvT;B"HxR.k8B
^Q5llt~q7xBV~/n!91bSK$-ui1OQU0Jb$`Vf`/xBJPix,!jg:C6a0@xf^+|r7RpN*I/2:M%^huBD0`%<qSsC;K:6QF=r``duu$_:GGnAJ+yY4!,e.H+17juw;`Qv?UzH/[xK7OTM3Z[qLq^Z^+TawmRd!sSIPOxE!SvhF<|rj:/l,BOJ
mSuF$F"Zd+H*kq9$y!*@F1uY
f-gLsy-15W-N0hLvuJRq9Wpsq]/I!y*0G?:_rlbTt6D;G*GS~^a@HY-C!&62>2?z$:C?FDZ<faV50J@TPaw$ho!P$-okZoO1r^E-sl/oF>NEK8#EKBBKVZ_<mqh4swu)jYp;|+Kvi!"N+01_T-&=mH9s.pB9Qu!OD3m.Qc<m(T*j6SBmlJy+%v{79w|"Bn(V=Rj"ND,Ek(pjZKL^zAr1(P>e8nI&&y=E]uD6uOTPjvG[(AR]Kbr`]M|A(;|wf`C%Khwq200kz[t6)?ZIb&Trvi7%2NO?:O%Ht2"ee:3Fvl!s
VMlfy|MRtX';break;case'jush-dark-f8dac59c6ad1018686e52a0e0357e421__2ec7793c.css':$e=',Gjwm6?!R"-YJmoGR`r@~cEv;#i.*-_KUyr[0$CF,>/n=#+liP*01.(73:+G.C]Ek+^-h&|hnGDq1:ccpxU98SxFh5MU%c+]DCcezAcUOWmDiL$
)yZA,ICx<`.i#E%U;lo*kf6u&LQx+!%1t]iP#G9;zGT,4U2"ha>hB#am`y1YU6$z!l#C%';break;case'jush-615bc0b9720a1de8edd2c6876a3495b6__aab91337.js':$e='(hk]`!>p9CvwpHP(hq[*!NJF5FML(97K>/e-sd5Yd_qN;*HB8+(1wUgZ|7~w/mVWtDMgGM~htv^jmBm4eb;y*03o(V96q=%wGFJ+{#Pe*,NlHjy7BFQgK&`=9x
w}KS2<C8mz.p;8y@A]]KOHE=LG+ZJY69hi]OmLk*<i_y>A?DKpNY;Eh-vl?}:fK#yN/L7`JV"_gO#zW]A%bgx5C2iYwmPaMJuX*RsGB}TjvRN#`{,KUMi
bus4R/p(^I_~S@p#Wvq3(AP(py_~k|UY$"Y:=8^YN?&4x@x~MViEy:O(oAE*[
/b]C##(si>X2j<-;t|e++Ydp^P$&7@4#My:^SMObTpj$oO
]5_v>omWdd
W&>x5WND
<.qP/
.cxv8P@DKWL4pf5eG(E*ZwL>,cxPpMDW+CxX@`u:B*Sbo#T([)~.G
.
y2SX{A*Y8/8uHsuehCK>W7BWfyM&)($&l4;IZCY4v1M`"Syqu?KrH$x51f{:S`>/Rv9U"yTbR<3R@cHL3l!obgeXKa5w9ZBN#FH&_2S7~P=6MGsfaEaDHLW/5/:_*!#k8K2IDdF9e##HDk$e{V~qLqdZ}o<EXI=LR2Pw_J=322R&j!K+-+9XPj1BNq;y62xO8!~58w*]=,YI}<.A@!!AyD}IO0A"dE-2P-0)QMVuz,-v<1ZQ-5&?H6^.8HGF.0<@|nj=oK[^KwG44a-KS&{k4f+!&a>[Y"Z0w6/]
*}2]?%T>Z@,XS<8DjxbilrGIA{pIL.MHJOvZnzI0%~FT8cj/aO&5EL5TXi2_P4IkKS.ORpvNd(Ri
K?gF_.3sv8*r|_dTwC|+<OA6WqhD:k9.q6z)5r.dw:>9~-#
Jm<HsM>:]D4TBmv
[i><]8@
F_`]&TW?p2D^Ou[`EKqW0TsmX@*l)IBf$BOl5Y7,syLtMP+M[*LV1Zj<x%-]XyR2WoV;Q:1wmXRb<pgmQiJBW`VMah[pf[0K&K
Oo2EU^8EQUf.EyCw`-4f=.#JjC>b3dYB4[I:XlrI=Igp%PrS;c!/^B
T-Yn{N@`fjnHiT[)om&NC8`wywgf*bVLFha5}KXHE9]GBMYF)b2)A%|N{,Bi$y0M?q}I`6z,BUV6Gid6xn_SVV9$VeV;S+5mr+HGN]h`[CE$Za$fIe*SVR[c_;n]#x4.~kWawhx9:$O7TB?P=fA&>RhI*=0n6V8xoTUGN]+&ZN7Xy+49hi!X!nqq_F3XbxnTiX`A*O+)D>bjUXBi=yq`?q~
8a(q^^LQz]ho=jZigUF7benA#<>"H(7
yb/BVj>7$EfR!cYe3d2n5^{0b(,R|8bWdc6mX4-8-
+W&4PjB#0X{5E-yi?q%kNEv*76CTy6D%nfJE],&l$Vi9=aI?#Rsue;p,kiP6/St/cR`ga/*A3jCHNDrjiG7?McOVjkWqdDH[G+?wC,_8[/&3
Jd/G")SYCTnMI>&%-*6{Q:/swD9_mkD`K?FJ;}li?PH{G|?XL
"Y0IV2sn^uELH2i{!,BnQle`f2Zz-oN4A?3=ifbw]M^6ov410!^EaZ0Uc3N<_KjYe~kX(}KzUu",K=^gdP3F.OEFfFm#aiyZ5Q1%=-[c+^AtJ*E1MCIQdn53"w<G9w5w48CUhWR;f%axaV
K;=Tg0H6e]=aWBkUG*3m2t+tC3?TL8V;Z]UVS&C`lTH:)X<nBDy!jE<9L7!5wu0nyjyO=+4G>pSv(x
C!D),r0*o{wi$pEW39(!u(BgE(Aq2k24WL6GeZ!q6jTFltTCB

9Uqb,vj6!2Yc
28gE,nESwuO4-@_q%~krA4i)?=FralR"cl[96CCr%3yo+M_Kue:^u>7IfD"ClFD/8SGHV3P$"E#0L04"JqxS8@<Kc|t:p*fAV;!8:LXjJoQ{1[x3w>O;I0<JK"CBnReimy+!N]
_h]c-1*[(jee&Ouiy+NoKJ=n,&>Ii#9="+l<U#%MJ>V".qDGNw8iAy._@BA]>c42y$M@{H
>cS3=7H;%B2ixzHX"J+93g6r(A/BI#kBg_8(M`+N?g*L#o<#[q5=A@X])qmU*0$Q0}<Oe$N}u2Hg$mw
cc99O)1R"X?]/R4:mun&;*Gebs$j9=?E
d]Ta)INLAUmS77z)1j-(uPs0P"^>SQ;=Yy
bSoj2BVYla"VAMewH;4fLry#PY-^MEf?H*f+[xFp%CWUEZQD[|ZLnufDu(xeFzvkN.M0)Xetf8T0huA1?=xqxI@D61<JDMLurO!X(jW`Jw
@ZEwVD]dr_E/A-YNE!Re(W",[oy+2D,27YC_&5H4eokPfK>y+%%9G]}CRZaEak@glWE#Nh[:&k$L((Wy:KO<b
La=u3euZsjP:GgwajR*R&0gKiH"d3_SnW$jk7q9r{bjb"Xql>rOTFY>l4wDS:hI<|vBNRS
ks(LRG$)=qSMnUU^IzsE*meFc{TE4J.nx(/|N<?-^&wM;O2Tx-(5-4mk3bx.w>fyH<%>),3>pR,+f8&:GMySjb5>tl:5mz
Kp3oQ%|%|8@qqO:/<?G`&uqLjQ+5.F}7(A
&(P]AEGHoxRXX-uF
}4=h6,O;,&=0[w[wR!u&LSA]8oU>.N3Y/.$])`%.0TY73nNd57!nXCuS]+v[Rd|gqBu]WKte8p>kj%|D
yPNH4zU@4XjcoO?~@iPvfGqk$:t[UW:AHL9_dTrxFo1BGWgY:Q;GE8)-l}uv=LL0p9@iRgP(j+1c2[y6A(upby$B-Os}:P6!*~*N@^hmLaVm(RY~*H8MY:$sj%WgQipC
@N?FP
z$~@3.pb=Rhevrg@*Qek1FD_^sQ&R*d-I+T%Y?^"U%y->C]r-!QVM2|f6
>1rw!aR!.c8/;PQtA.&mpZ$6I`"*VG3J?NTr>#@a80RK(>,,|-aipDd88S-Qh
PU$uHvS&E,(8,e}+YU78`P|IJbiyMN~/pn8i<wY!Ej7Fi*A0&t%S;r0*mehTfv."^GfU;G+g_(9@x)98rVq)l:
:]6m$rGg1A3GUaZN:k.o)L:jn~3]m0Y"B.Ti
g"4B64fhv]oQj-SvJ%S5N&7k6P=&/&/Rqxv"G4r>7/#V/o5H$%EgIDX2w,`U_y.i|t}v<vCm!L"dNsa[t@BuS)"y@F{%74hxy_v;V#px{r)QVlB.H
(e<N*l5m`@=W>@P3bT_rkOl<+wI2*=_%ud?s^&F1c:#E6PaW!P^cJ/785HJ!!.uHA`hGjKkuzX@qHpM1/G:fFe#!Eq!aUh{<@I$2GtT%:H*9USeJQc[W]y3PEa*CnL$$qC4p;5>J]peFHH1@x8nr+3|4i_{Aw"T6{>@c*QyPFJ2brGiCQDBCEg
OmFJ1<mj_>HF="4}>Q]^Q(4uuL3y:rbF(%RUgcUwDYLmN38TH)_jI&Pm,B5h2HEE,g!xb.Po[L5Xmf]a-WslXjQQsHidDx99*e^fD>
yR$/UBo7)0je
yRBHUx1(9ADpOL]hr,*JyLR
0YZo57Ds`j:V:K9u/k)j-&uWyxtJR0lg">D"t.b!s[=uCT7ZK4ytA~(1[yOu#,S!nF1^hP<h8kJh3Y*njU&XNO`eCr+@A/3a,.A"6a)<QP<ZD6Zsw9+=`s`H.fpaVcFcuAPGa"2YCYa,F..Y4kV.dyf-.KZ{^fZv%:ZO!tB}+5Na$3C9OilPRABb``w%dR/
(3SYD?"O+XSdmSSn`w*,(xP%F"bmMat1/fs@)(QLs[];0(<f$=Q+_
+h`r"R=~8E%8HNJZw%+T5
IOM%
}$Tz(%ih:L/3Q#@<4)}%F`HQ!V=Wb8]%GKedt)ha*UX*`%;Pq7rJ_e2jd)x_Q6ipyQ~,[yfv;7n-bsS2*1JtE,citK$Ih<#I7GS
9XHP,Z/q*BKxbp)5_r.lPj[q[T09*V-0#YU-CGUUu@/w6]R3rvjpHEX1!4$D=C)!(d:T&L{7)(Q*sO~,NvI&EvvKXAURGRGZ]]bi:-r(m<~p=drrs%d9q71eV>@6v&jLP!ja]fZA#YkfE7Hn.@sa/>njq7N[~-k1N,8hQF=t;EBxH,Gv3]j/S:uC#;#V
/3^C@nFdXZyg+F+C):VWf}.GJCLq?Qf`j`mY9*L|B60Smb[!4JRg(N61A>6".]*!96>3$]QE0?14/??DH]w+Sy2x*0P;F9@&%_Qd0F
h69+31@AcFEh3@%v
?wGV]haRMZ<]*s,Hix#6`sC$KXpl*"p&uq*|J?<%w@M83Z_d).RCWr7RsX=UJP!!Y6ubkt?JEAirJ%vUuCjWy)jX!M)FBqy
X#(r]0C"gC2C`(O`nd%vGX.i-1A*k0M
NYMpS:)|;*T(Z7MLFh=5@qQCVF;f)&K}mRX)n!9.&a6r>P&a,E+D=z#ql1QIxU>SONn8?JpP
X*.5TQ{OHw+Job57;TCiS?dSt4/Nn>@j/^qTJ+W5b4O-mUmq5;#$".hDDk:$3.S^h&_&8D#A[U5NB5u6>omL54zr#yiLB0C;004NBm7^:7^U`chD_&
D[bR?GLMh1&t@Ws*CL]E550rA%`=:}*dO94O?lw$*nn%wfw$2d!@2{@@)_-9_$(@xvVu^.B}FhgeixDM@;/QW#@/B`1*IRD|=?L;<F/l&:0}
ii,l{S_Ya(j
pEkU~?l#jXXrAWLq*vV^DkErOP&%a0Dq"i"B,f/0k[eyF^9&fS+aYO(2w
gM<t-vdFdY
a;Ugj?mvT!2Ft"Ws*8m/F-Vn:bb=E#%qh^C9[3fVsAUPAtV28s0yU>h*.D?70JssUsO6>U9
Edp"v&.hZ!<qJ/;fE*XjX)ZrCZu#*xXy
E&`CfCRK_svBefG>Ti?1
TU`LK@
B8usx+(rYcM+5TbIQW;L]b%l;1)4.hq
{Rp);hYi/W>%2M$<GF5ZknOfd?ffqi=)CsC<QChwN?}i23m,Hsgu#1^$.IQ:.Uzc,_R^t).k}T-fqZP+
UsdN`P:oA%Q:4Qmm?=m2%fFN.oFx#!Zlc{>*,ZwYyfy6,Hg_H$t70=<g2}AP]#Vj#OnaG`$Md<%zWe%?K~-"A_pEGb+MK|VT2Hh0q#Mo"thB73PI4Q_;.1>eG6Wx])MKxI:a`UP`E=EbC%i08ZRun?4GJ~#n.wH-Qw6[rlMB0JSb6CaOjG%tYLUabdeto?-Q-bAoHK]~@"A8x#6kQwY~J]p_=}w{UV/C.Yf0
#F{;~NR-A7sV"O*_2A%n;u[S<mfq;8DSDo)l{)lLW-*s8:_)P5$G=J;T"FX@b+tv{wh9dr*pQN
d%47j3.[mQECK>K2ccNCM["y`W#jQ/M{#}NF87":0Xo/C8MLK2(BX3*4)U+QSSFO-qS"w>k^NohbLzUxiR.cc{mpz(q]ymv&c_8VGFk!;A&z.,li@+c(08d$0;],q+AS_zVd5#GEZ(6(b?q,jOd}<$(9.7<0yNV{N+3nNpd!r1>^Tx0SR66?:2Jl<Px*m@%Mu/+NP^qY6YqXLm<`Hny,o$n9_H*o33xEcT=Ei16EX+$Uv;,]sG:"uq-b`.tRK(PZ"=gO7(PSI@e9,80Kn1j9naLPu12<p.K/c1b41_k.W#w<eFnMAb=BmW_!=NL1FXm"HQjwm`
&^4p&78
yMdmLHQk<`|TvoA2KHq,sv[-h_n>f",5pImV7uoZt1[jM"D_,RC/mXqa>EN-lw-):nd7`fQTHBtufy[BPm.b*Du1hlK&lA
=Mr}o!UbWjN$Wdaw0fFtjXm`g,+Nr}J[j!=RHB,w#m(MFZ#eULGNooWp;8,cuy;3KbZy^qh;L>obq$/;Y@scsOM.)~R3F$TiluPw(NF(GO7{odc0=<sMxo!LEhQ"cAAVaXo!n^6Y/c#KF,<RDC+;oe6n$Ho7w-K8pBZ=lsufm8GgO=BY^dUp59J0rmS]F]
1ANM+/{6r@)[$
u7WA1vE$^I<
$,*t7QDPeHJw=%-Lc8MTWYD"lA3!{f7MbwYV@!vP(&R!Kfc:Y3700jJV!Fn+A2"iO8foN-&Z01w&Qb>>&tT^q"Z8
K,oX[)o7E&a:GKMjtXNirug3dTFAiH
P5{``Q%^u#]ZSW7HVG[KutRHF:HfT,^XS2EEZ*h=c&7tWjA`T5XeHaNb"]n<Q;!lD2>*@jM2jZlC9C,FVS,#>8R`1W5ubr}s;qE$1^#`:T5q7H@],@P!KZNEmTl9?vqQ1>ySA`Hu`O*0{$zNwww00joUb!bmwuwH%tlL#6FdNA5jPJ#^Y1-CYID@i_wEY3j<7Q`L<BUgT3SuK<1h*A|iyHm390,aI]"]y*3!*^^F~SF5Qjhx9aQn^["O3Uq;b-VU;kxL/IYVH_;WAPoN^lv;DJd"y0tOW=w%o
o)(eL];q+HJwcCHpLQZs`asRVpUK(@g
yJL`Ad,tm8HAD_y$NP*xe2lfatBB+"G[(JD3H2wAJ
hJR`P]Hd,
x1U[2=!NJAf%wWQyu"p[#"qb)"r?Ow,3~o
p0%v1t._%19QQ1Yrf%=C-wL5`1*zOlG|?1*J5,d7W"_=t/cp`Lu^iZvO*X_N!C(Iv&%T2.dm8(%f/@"W#8bt:Dy*5%a|c2/G5rM^EWy6St*XJe>/Pfb*&w@ctdB;cU+UnC<S4Nv~#fscby2]]H("1db9WOF
P}q^o^WKU;Z:kqn<l;Fz/[.9C]%#yM.81UA|Y>OUNw0
-?#3vY7JNPY6/iy#;jrjE"bMO~!>`q$z$1"B(&IKiuex-M^lw1rEe-?+p[KfmZ.2EA@Q[tZ3sj*-M$g0o6Wd<_@+mR%kDVL`Ukn!@$qxhX.*hz^<@a9_5=N)72id/xr)pVuHLED702.!l|yAr=C[P#)<4o%bk?3Bw{ulZs:}.HyLGv60Wy6NkvK3</%I!uc
<JvWUHB*qF:^cTc@H%@DM=Z@h`D~?p_kT@eZ$;o2N
eoy"I,*}@c0#,,p?Yj/qHq2Q2a45!yNHBO1&pT!,XeqX8IG7P(/y"~fI1bbSMbbYq
;tDRda)f@G)S@=ENMl>Mw+&f
PM%4shY8W?d[-COn1+b7n)evGA|J&ILkko!!RkrwuFz4`"~95^oB349^aQtp{l2si?cY)av@H0xW`24NNhct{U#%A2!brRS*$?XUum<L5b9_J"e=ZU+9/%PG1=fb9)z@HU
,?.=T#eR:5Fi2rq$I,fB?/d
g:7vQsgG4<Hy)2+!C3wh=53"-UjNv8aUD[N%Z%t{Gl8|Yd*LLRxqhg,z]ZU*SG]-O$J!JF/`q_*B^iY00b(fnBo4OaZ6EmTLOHSOb5Uj?}Dvl2"B=.a5g#w,:22CAZJoL/eSmO]x]!"?
8P;iNkD2j-42JG-=-DuagB{_[vNM#Z.VKeMA/#w.cI)$u$x(PPV/Y"YS82s&=h`!FNrSVv;$,3,gQd}J;.2"OC_Y44.07q;R@iWW]tqdpG](37zsBb}>>ok^]b5O<wi_xNL-:KKyM&*#zA+u1#%XdI-
$B2c7riwLd4ymy0G
A$)yPK(".K3$#v7DoEN~o_ur^]m@,%=)*!1`)n63$cNZ.TFIX=)nOVHz<s."!ypaO>;whLtSy7*QjY
ta*qQHny.ti6GVYw6_gW~j"m}.Gf
j^A@KI_kw*4+p{bnSot1bhD4&aeKr[2HVx(Rjd.w96Vtm.g8bl=>llK&<Q*fDCPpkoEvOp5P$zCH7rJdpg;D/MKiRj>,F)cb0168>0k,Ms7(SqG:4ts{W_d5oRYP,d.T2Bv8hMJjjGPWp|qQoPRCc&kTx?+WlEV0lM74Y0y=BIk6C4jF/q0W>^=F%Dv&]Tq:g<jvEOF?*<0.Q]bzcZ
?uQ,A0~QBw1`D[z0g,q(NP}fDT?F/T%fNW<-Bto8k$N9F;D!n<BJGNCeuS)Vf$jG">pQP^/W-CLN.L5gm%qGBeNa8XTFJPIuKg8:Mh13T&5:eQJ=DU?Wz+&+1eG.#Vlx18q[2^E
512&_6Z=}S,kGs2dWNJ*Ls)BK1]__:yW]S7#H::rhC5g1,,8Avz,e[(;}i?"Z*RxeWIG3_4@U$n/-bf!TnQF1@Z8nD|o_P~#~eST0Fd1mqHS.4WOS4xvQ..x|aH6=$d&?BTI1EG]|#`2*Ke?&f:0gFvu{2XF@b"pao4&^eJSiu{--a/wuxfm7Lg@pg0_uTQI(VEwK7z;mI6O0Vm2AC4b$0,bN-(G&NoQ!FcU)KP8_<bM0K1-y4.K7W>>^cpO]YRP|hF=BbwUP>+c6jM?H/H4#m7?Nh,_1"Ay}YU2W7qi+k<1p7WF=(m)-O_hzV^"N/e>K
qPGktD*k)B}HVPU"O83m#,,swy2uy-Jqec3<pBTm{0s>l1YATny/>B4J!&8By5m]pv>uyfz4c]iRI6..a0KGKF{X@Ce<QJ+7o1[yHglIYZdpXqHkp]:$htj:EB.#R3lBQp
6[Z>?=HX&Pk%*g!;,09~Znv+9Q/KJjj@_WB!4B$yV&*V1[H@[2EI(@B>,Qh}w^.>OqxvF8!#$!0fLX
cow[YEqQRG
sE.cfF@SF]v1H0s4co+;Z|p@+w&Myqn$[RA/J-!tF|Z/)w
/I]ddjWDT_Z/=%s]J^I_91rLDCPm9NZJT08xxuFhd]Ve=Qc^vla3s8th4dy21J5i(GTN6F1m`oQc_&CrXO**3u*?wZixQFwq+o<
9.;JDw&V
#HLce3+Ec}H#)j5*ZJq=c{XjaLkpd1)9D3j@T}1Z.BO/R>b
IyCE7<3^:n$X8i>!o<u"C`x|N1h%2Qg?X8U|g8NG%kuvoz@}^kQZ.~C2!?vQ&k]2`$cJ;M_%l[&F0NCQtzk^2>ngiXO>S7Q@$wRha"k3hmq,F4+.W>e`_4Sq1(NhhM;c
0?}j`+Dg!aM(PA-x8AvEv(!
&Dr%gk&lWMVw2_$KV)5fBjH#>j`:7M5"u-H91hWt)
~:O]gIS=qe)S
Ugdtb.Az._u;wY?a;*M/T?U]_buJSpfH#*v"OmnmOTHj<`RQOLQZXwR94#Oo[?h{>Y"fEPKEEy>pA|S??H3Hhq;C[.JHSO.%`X?D^b3T163X`r3Fp%?N4=a:
fZuraa)+&@S2kLK4ylH2^xvlyIcRVEfTAvc!?U@p*SFy]Ob>ai+Dk^cs>Alc;B+C{W}^{osZ{Gg_rxaHl5aO@Td:MULF.aR>#t
L]u7;V&he{XJ9.Cq(@hu:zN<6(Nv?{gRpx4$fJ6FqH4K=-@ejCE8bS6JSYm!(+UPE/!104K,n[fe]+Yx9T`EQWb7h
D{t^W8ZoE/]7S@2g!iV2]8:N"sk]ClpqP,:?#nBR.N+Gey-+5UL2+dH1a|0~L:Kc#gOkTgT_+]m?T::|;^0;
/nnGBb9#O4J`C"n=dq8dh*OR1U<iMu_])Fo-yl^SYV_u4%e<2>dkeG@2uitgTS^C?&Ujp5%nd:.Uj
S`8[y,79hDD,{m<o5e[]pG+(vdq[7ItY[8Y6AbS
d:n
qWf&yq+Zn17))N#5_wiFh`gMH:~@L
q+oGcvD=#s2gwc4f3rzv|<ZG&h"g$$A_g>eALk:Aa-ZhZw~
]HqY>j,Nqh-ks-W1uKDNP_gGn4FW>Fu:JF4h0Lwq.Adu7kW`LbqA+&"OWu/+rfIv21Sk`kVCv.x1G/gu2obRlpza]dYM]P?mgsXbag?qcy6PYk`76_RvJIt-QXiH`M.eAIw/qGvJE>KInfg<JW*u*C">SB(cIgo-.dPrag:GBFw5>mOj/b!aSs&]e%DDpIGh&]>ZgS6ZXf-
?Tv%&Z)+6<G)%f._c9{BVIji-r3%<fS0Imc1CTwA+;vOQm_;n+/iGb(J0ff8[;_t-%lB;qtrz1CUrK,_mf)?84kp=_|V2p3tTP.L@mMy!"Nx^94_McQEa;A%>V+-+%_UV1FQbo^jyt.kJqz3Pg/^^ApAB?(g;P?&<9zu1WH#aC>N+^iDpHx:)(LY,G0QcG"*@M$EV:xs$wKESt~,G9GmfT5)o]kEFc:4Y)|1r(}9"lds+d$IQuDtOEz/~Wz,!xGnEDO2pqw/5$*"N9hRl[VPU<:n."G>.[J5IiFZ_AR)5xH^D<406qjH=8[-:-=ID:j.I*)y.&Ob!a@V`C3U(elC9"6.{$Uuw#N:Xp.gn]{^dRqe<D-Lt[qX$v+qK./R$%j%%;HSGvdC7-nl?kdC+)XiO!/UCW#+Ls7^;X*Df%<-!W*Oy?dakk^Yc+(yjL"db;>,(adkpdA4c=O/<^pV!/aOXNa:K6
]N6{$)"24k/v1IT^"]rF^D]u%x<:j}qi.F2:Z2CqeD?M8PJB%$%3QGYrL(^N!v#?3xxe!Q7$8cdC%F$!Og/g4`RWEC_k;fPb.fk}3D.*o_McQ{xd#pbz67Bb4FFJe6,(aN)})K
BB{Lh&H]u]`$F-?;6,H!g4C@-a;&D8~bO)f:]`2fud4v@ti^3[T-?!+:O6pP~vt%el[b_4uc%9G$eT2D]^BSJ,N)~a1e:a1u$xnJmFIdX37gK)?9e?-6-QBv8"vt^YcDYg3Cre|F:K.q>cRE?e^&Yv2#2C)9Iu2X{i<_!LgsqM9I@w$(n7
MBk[vOt7PDm~=5yvc@b],AMYs3Mxt4-jkwlgfiHmtldFwpQnqRtS^!Amfkp{URZ@w:rqF^&`R)GoDl<m[m=/wUKwJp+ZMm(Xy2MYrJ*YslvX`XL|i;O{MbyayrJ6=hqo$_;qg=t]$<!LaMQltVu(w0K/R#mjc.b1QZ_t-K4J,7gxS|myeH>WgB2,d^kdM2;y5rH`;=v
DIM$&N?X29([Ws3PG!q}%Apcm|y&EEZ^l!RyepT_GnOoOnyR->s2KLN[4oY=Y22"JUHGb]@}bcl_R?
qVwi)PE9;Sl_(m8Zj%%+ZG]^KjR3jErgaOuYhAofbRn7kGzu{UqYPUgY)+H6&fP[DA27bc|P>v"V6e(W9Bmi[6>c}YGb[d@oDvK.?o`b]`B3/:nZxkw`%pE:iYAKu&XrDz"rRWRi)BZ<j[V2</Fw(yXwuoGH+ne5{ug#5k(O(A*a:E*B7neGgN~Hl^99$KAXosqNMtnn%5(*T(]K4)sUbK/YFE6GTF1V(:oWzD>W4V$Rc?:ma-7DR5$knFQJMeTn+LT2r/@%es`L2GkEC#pS=n3?)?c#TT;rT9&+,d9g9<n>zO"l2d$jD`vg~c`K+!z.wxzP"-(G1JysmL/-.kA3v/Vkoc=MyYU^[6UtV"gdr%:Nf3*>$SK[BkIe!@&`pw@nuLXtTVL$da,MU9DmXawqrl?bm6_Q%kqui[aXFMny!WN,_n-!er131R{]TV_K4&}yBw+,Vv?n@#e#;2Pv-GP8S4:,my8z)K/n.w7bmu}m:6[29c@P[K4nbK@nCXAc|ms)eH|h[7?Q2[H
8^BbzX.KKtA-aEu[818YB"Z=CDe>/b/hkju[]vyZ>/xm=b4eT#ytutWWk@FtAcZS!cXxih3`+`fndffuyt5jmkdk9OC_sl5ERmq`bFg:h<XV
[i7cHf/bPDTtK4n}N7k55~YxizP"9v"nx_K,qw7zMfs[%@W3m=*a)ykS46FSB/68)2`]4#oDQ!a=/bkGjAbS)Y@~ehP>ZA=pX1To^txt^X3RSrU|"U6]L=ym.`Si6|ZY_ts,3E78.`@Q#X3!=h0mOK.oO.yBo#l^K93rbKq@7JJED?,hF^/8g/OX7*
cb]xXo?)U1oM3s{8O_=xZ95nuKOa-,w^b)1>Sp1PyD(kRX$z%^QetxZ6]<"v$vm_:!$m`X(RiLWw?yrY$)"3^4<]]XYz%/dpfS!APl.vu/^_1[%8ll&NL7Y_wnMxc;Gtn8P
ldlyblM_psPJON?!F]e;I2~D8m<Yqo#h{kIK//esYao@:*Vu?#=*H@bF_d,dpY^b1bI!bLXwX:"xbG,c_K52-4@VEC4Gq/`.*;X@{MZ*rY-bzI>_0z&f30*Y5wPcDg7xjI@TSdav_>njAFVc@?L;<2*:K0o@t2&1
PWHK=hL.]e%V:Y(iJYD%9Btf!/g20Lh4N.xFNP0C"
!]bUK?q?h`gpxaxMz!-}Qq!FYxKr3%51LJOHO`M
tACTAGNP<i.][S[[xD,fe
Sjb]_pw7u-tCm?,8MzZk[Yw8CV-e;ykgheLA;-T[Xm)sEEh2up=Aff>meV<GYv)z%EMC3wmrP%E6g%0X_fhKD8s!gLtE334X3LD!@A<SCeY/k(WC4w6E^<nal5({rfV32M#y#{aombN0HWjj]D"n)r+lWrHtBi.u,^yfn+:?_SF5j:a4tY>|-E_cfpF+GTya"wSLL8FV9bTrX(yTDU"p:MdBUtAd#lRUF,_B$[dl7>A3%93ds*ihAT<R]e!XT[<W
@qGIlbZbIcS=)R1""7t/HY2KJxSb_s
/)
J)xeGBh`{&Rf^$#6EoN<FbQEb8E&Ln-w|,e[:Zui2mB
v"/8BwWN7A*AU,g4r"p7Di+kRB%AUYdFa@AuRi$/ovoj2r/y?8uw*ysL`=tFKGTYXU$E%@MD0X7o|cBlF"<K.4![fk<":*k+=/1Jp@16g.^ypM"E,YGK>@i:ZT58o0=F[5rhSt_,=q|c_10nb5{dZ(%Q{t=,oC=)LJ@Ai/~L)puseL/<w(AYI8g`dtY$
2)$./3!O;.tIN0:<wrhj
~6%cl8Art8Q=TdkxS=<shCf63waa_L?]]/,Y%3&gIk6afCXfFmR]b:&[OIz<Zl>t&?!>eS^7Pu$4REJ
#aLP!4Q-(#$A,+Vhha~5IOd[K/b.KBua<ww<3mT:I4yj-D]E#Wrr+#r$0%n<B<qC+(c#K[Q8_2!.A0;k"pYX[+LSz?xNi>7AxL#CCq,MFk3f+D(TE-
i"2_^F>e/G9g]6J8,v]3yI9y1H"Py!.Mx
!v(R7*7APr,[UUALDoEg!2uI8KfnH:=iWg-{WZdce[
:$Q"?x.%i3636XOiby)-?)F5c9d+^>?e>0`_B?LPuu>!ryPw~7qtSdYhvIsK]cyojUF-hVm+hcyOHBU8.vlMwr4x#J:cDduyk"j8&*l%^Q57(I&Z>X%U,r|D{$ij]Ekf+gI,fEVeTpJ!>f^hZwg
:N8cmg7rLT5"4teEvezt:OQE+#{@XKPhvAk.NH{*-trP}Y
QAO{7.[%-6`|Z!"G6mTRSSG<q{
8Hkjv31)U_lZ!<F(fc!n{,p3`s_=%^(D+K*`%$!C_!??[W$mB"
LU.j,23M[JRwU/]4VD->pcL23SV2PS74:nAA:);s)/`<>jvfXp^l+C8E95>A0X69f]dE^eQ
8;-bwx<x,`Q%pQjWDKBbVgM5QNvi-
:`VDKu,-/G.uh/&q
h<~w._W(H
EvgZo*FTE=V>o]~>&06P(,j=m-,Q
3Y$mKq%+s}U{NzUCj>;!?dH:&jwC(tS*,W*Iv$?~N/ySN!1H(Cqm3wo.#(S,IZrh2rgE!=aU<0x(o/"(tz@Bu3cAYOy5dwNu,b5{Gx0]]drx8F+%X
8;TLyBtr"`+7/dfa*hT9;D;.=2ti+w.yKFfYS^D<MEwm$;!DDlIKv]`,U}U!Q+3wLj")wh^L8>Y*_>T9Wlv5bYnZ?P$GNb<PK,@/<Zwk%.`g2ZNkWH[F:UjxZ>YX`{KZA9;vvYb-2!,AY*:
jumhsg?<knNx
[_N_hUtj"i>M=f3"zPf($Th&v#],?sF6/j<Kz%k1mkD@/AL%zNpgz<JuR^/(:)`EFw)sxo~siJMU=Dpg@bc[;o<ho6d0"wbL5J,m^n>JQ;=&#ccw:7.#{K5ysssn
KNw0,~Bao<M+]$#XA+NJQ_s89tQ5_RAto[YH<YQmFj[w?`![8sM#??mR(/l
aV_"lCA*;1?sgs!CneIHSO^_?Q=tj/Q0r</]ge:`-_TtCwfx3$T=INm?r25xhb?
&!<mvGd`48[B?Ty:7ed"F
59mZ3xy1_8AV
fe@*!h=%Hw9%gRcqc)0L=ix;9O"Avc)]]WkM-PY&KTwA/>}Mghq>`!)y
G+q@yKe49Ex>K4qwQs5gB4]o/fY7a5yI#XZ(i.t;y%7`P0/sf1>lfkkWrWDr%6Ut7GfrG2$8HR+(L?0vV:LV&"L/R}6,m~;PRos3x&a)^)4+kL%2nRtWHKa=!g_|SLpjO*k4m%65K)]y.4w<c`[l9`Z?;aG@W>s__FQ;ZHa+lwd?S[[5[=G]7@PNuNZ+vTiAm>0O?}5YpS_-Vp>zF?)WrIb?5kRLpM%^>IPF"(>69*]ARHx_i6?9MZEclZF%J.
>5{jpbHbbAq;([AHI&{^H*0VMv[BILGlIB@`"4@Z_+SbfU]KxUi`Gwg]U_o#h[uCGQYn%3JTPO!;
[h2MKZ"}Og`8XaXayGCkKE^+g^YEec?.Ks`Nsi.VBJKGB^FX8
pP14G8A4j[?Q3`Tt`P])Sb.3"k*1nvalaN"FJ&wDt1D/f~qD)2!>+mSlccVo:oxeuFTEAp.*oGY$Qslv;@Fd,MB#k7wP@aEH3Ib#^d]Vo03Fndmv>+]41GGBX!j#TdQsa<5H"ic)!#`W.]Ox"t?Q`-o%
Rke*HF7<*mr$[ldwDw
0uF?6$1Hc-tK)GE]4u$KeS5RXEl"5SE/Sg`>9]Nf=ZGUszsQ3h3?h!H*M_P,a^hHCK(mWHy`aJKf$@H$!^aNUF3&Nid5R@;7xP*BLY;eHy%l`KQy.$Eo5"lYxLVYw-2Uk1*DIzu1X4`^pFLZBMmj87db>;RCB|El)Dn#N)izWf6d^U8{^E5j
LHJvq9ER(j?B[:Kj8K4k1K8B*$aK8_HM`2*7FoZsH*v]h5;LY@~1Z>j,EPDYFu0/3FTA8x43`Wm2q,X9iSVg:rwSNu(Z9tw=TxSEi%5ih9(<Nmj,v50x[i]an;C-H9}"3uF`*`ou8Gig4MNFw^Pwe[mCn]uShhjL+x^m6p_"[%[A1>.UdPF=$UE
Kz!Ff(6uw1iLrGZxX]=A8iE^6*g.a
a@5gpAJJhK>V1OtP,+b7b;5dp#N62Hf_6OVYgq/4%
)C^yac[Ul?k.c9T()Cc2W-jQ+EkDsaq&l<sdH8Am^7k8n#j$V?boJ^=6!<R"ugI&g?.08By5/*^`p;TkkXYgj6
&*"@=<dRg-Q<[j9X44Xa]s(pY(X.$QsIQ&b,a?b]emm!juwdwXNu05/aZnS@$!_Bm.&r:A>hMCk/VAo62tKt5Kx^9%/EE3SnNeBbwBV}n&?=]sIa?iIH!m/po:TwVvbf6y+}l%
C;IhY$;#_fmk<e@8swI%qWqhZt^3%eOw$5ogWtR=FYM=Hm(tbT
3*$w?5w@V}9jL>g*%NQ7fC-UlDNHQz2[n]1[a"50<uyl6X<)rR1$=YJn$oo)+,EgdOt(^IOpOMtd1P#02Y=ChP./!O#>5=Mo[8k<dq?lawxhLS&^wru;C=*;#nCr@)Y>HP7#h2nAb:ccs>><YVURNkl1K,dCxAo0.csTj+7[6<SCy}<wdyc$dQ1bc{K4G;q!aR_R?>WA>-gzfdYM!JG+A}dc6v_P&}[p01jjqV+<cfl/Q%a7fuBcRfd?h&UJ)JL=*I<Sw57`r8$XK3^dO%GyxX6Foa0()IL*Dj`R&&G
rb/@h=U%7a,.eA/Ym-^<=@
OAcCG,P[ll`
&FOb%+(i/u|r7H01k"l`myJnqXY8O8qZkyfG]L9nCpKi~,A!wNDL[Ddz"Efy(Z9!pK#2+)C*(TBNm;z5RUYLA)"QnMM1U6C(qg/Stmo)Wx1#t]tBxeFz"-0p["[3q1=(rHp%;#_/8"|pPTJ-J6y%Iobt+kj>JK=*]k&yrmiy~w^twu6l;qloTl|RlKjSE$Sp-cwR|fL7C_b%C=i
>fMMLtQ)cSi8Z5vSE2eo0p<95gkQgL&3:ZG<7tM;[Dg2cyS*YKT3s!^`VJkvwyyRZyssrn?n.sC;oJvg"$]kxLUt3OF@Rtm`@->,3iI8;>28?y_Ki?w6UV[9IXbVLfIoap9wFn*v>:yl^pxg+I)y%-p4WPbj&Rz,6X:i}ABGxTb%
8IeIo&!PSehY$[eMX*NXhMC5L!<Hw!"Clc^*#Rd:<QG?!(K,s?-S#ku[)5K3%7@;Y?7XIZ8TYR012cyxBUz%hVo+2.c6:-DLeY
s9&VcxgX#jq`tRs/TK$9LNxp==a/cNQK=d$s1Ejc}L."}kS
2,K!JBHoZEPY[gLF*s!xDRS-2VH499(yp*RmjLC[kz(gwu
gq/hlbrF:y^oXBw5&7->wB9xqRc=K"2#iA(Lyg+68WvFbfT==Wn6>[17(wI$!l7YY20i#l,
x!#!1b8{>^5;C&G7:(ka4J5K)O+sMz+I]E=b2;-!7xY7kHOjq
.:uccLc
Xz_-5ReD;%$_R{f5tJ%qlGBGyg0B67YMco.B!jmc;
T~#3-tkXZr5VhHlMIyK,qW/{@t_#*_"!Gw[7N^m@PE$;R_b+q+<B=zyWSG0A$M.(O-ZF^6[_$L<=F<9-53%W9B1@DImKKMIidB&6B]-,?)$`8tYZKvpgf|r::F0@d~*fO^HKK_V%`JU.TxCcE}:@tk`AE;3S
"+Z"Lk=iGI):<p.u`%Gs]gv)}SXamcg4f7d9frEZIEh<L:1$`86"$V>MZ,(H:Eg6~r|GN8gjz741t4VEUWQ?^clwkd}nMlly3bHZ>M5:?KtvzdPKFPXE&edm7^u5o8@K+,q%46a6bw[wJo;$~+=W.t|=kfKy1:GYST9:(hU=M8TGu3%1[%ZtG>|"E%Oyu"rY<&!PC-
$ItX@<
yXL88eAPI(V"|R^4s,|L_-.y&kKCgVR<_tYrVl:q{*yN1>Bj#xKSubq"^IzK3:KBn$U0uF?DW$&2~F>ggUQd;DJ<Af5#*!Rpe<Oy
g"V~u+OmOG"k+(HJ0dP}4@`b)3<6#(,L=iox(y=[l?
<xiel*&4p4Rg"!-eDN-Jm
5[c;1(Y_"W{4AcxF@Rn,7Extw=kPytf-#._Ua?
0m;OT_8VTw[<r_Vz"NOr1X7re[j%1;kaI-Bpl
Ali]pb:MY6L=(?^U]u%-:RB%x_qcofYru_S+tO@(`-$tH
>K1t!<OEB^Mnf#Txjmb*NQt#w?Dh3B9@3-cN[(11h}RhUA9qje),-?6IZoSWP_^^<Ohfe(%^2O3p2t)S,%,!.M;OS:;aBtT.-Q<V/OO;({&(+ruX59;9+!mU3~CjB)Ca&<bsY+1WBoA1/1m/F0ho:-[b2y>X$?R`u)$pve!C=8n=2n=<JJs5,$Ct3}?}9T$D++N5XO!Rf]HdecI
!X2"=u*=B-*WD0!a;Hj[2[:j,4ZZY
0.cyo{W(q%eSD9!}lEiZ<mwSr~=ms5@%*.e;Yy=W#E+B*gmj"vX&8yszOkD%RuC{:;(*Rm$viO
EexWKSB-i(_WO={T)1hc+;MX&I2sB8CyDYLizW7Yu<YC%aY@}tgPge"hMcPtLdxE"xQ_XXA/tI_wM5%,k$oP~N
CVt]Ubw7(x]:
-%ry<$0.WmkGo)v.Z_wG3"aK^4<t@T{OY&+ES_&$t6JYTL3yghhio,[RsPDA`0KnKT^[PP.A/*S#eOD$yl<(F@Ky[dP&jKU$Fjar?U@klWKg]JsU/4I>7Nuc@XhM.D"_)V6?>eVGa0J$t3=c*>xI4+UxgYKroY%;lCvcFSyI
r]S``h%hYw$~&H7$K@6_eQg"qwh/?6#%,MSWrQg-UF`sx`lHWzMTtRgs
5peK[6
U0KVO4_q+0WCp}s#VC/:3yWTh`.(gO`xV1Jg
ObY4N[#f5Je"R%
q]vl.:kMk,%P_[#tdn+m4#L,G^3<qB,b55AZI]gWMo:f3j^|%~P>60j-#S^|a39+Ym.z4DD)DD+@al&/;"^^qcf|>pD>r&1aF%b3UDx$pv-xAj2$D}7NA}.WnB)Tc$UF/sp6)b7tY
[I[n6+1eEG;j7w)#Tku1@|^*RkG:1Gj;9wp}v[]Or>3LT@hZ[pklP^g~ax>J#o/3S?`UcA_=uySM_A+aZdWkhJ38RngMMa!$?O,"B.+7<$>Ja.X_ja)X25UNjo;.n5o`Thw],zO10n&v7xb~2HXR&>ov.Shj()w
ic%vWp#H&4VA&x!;`w%LJ#kQ#yGQ)$Y(
qruX4iIQP^s;_WUbi?e"v"srrY"Fk]x
a8A.6I4J=etYv,c<0fjJz""%zfC+[no0K+v]8,LO}5]VNWePK/jS];a]t^Bu{x~oBSDVXd1%OJzgEOw0Y//^we29=;<d
P^K0._&wiV+b%D]=et7Wb`gZ?G4McI(ICWPhIx1;"n/=Z=U3dqX*TAd4LYY~yim-8dI1YA0G!W&>="w?%)/y6/^e3((Nsw.k#Mh[
MW|#xV<F-/h5f//&ALsf{H!js/>=UKa-]q@(xLsV?V8#*`nYXi3T4wVMYnlWoFvlTX.s2+k$-of=Fm_O;S>Ab?_!lD/_?MGSDYY
MjcZUu<D+V33&e)8"lWjc*`iUT#Se=Gd<4X)i2bB
[H+eplu60K?[@5e-k:
6:jHT=ctc>jS<=kQxN4nZ5Ak
`(Jr&=sWiP0b>oE[Wvv$7X#+INX_cp(;#i@OD-9$$oZCQBB+)#c5/fDsB1Q-M.T/a,M-NW9bU-R<2s)SVW?3F<Su<9a]8<H{8;a|/Zor90OGQyIrKD4Spq%Rj$Yt@
ua&%$l;/7Bm*b]aYq}x9:~sn3anAqc&u3&,[WB?K/j1G!^59V}1BFhV2rFhTNe?]y("@o~"I?(3<rC+I;PLCDe42ON_;gaV|?:$R(Tjf6_+Gvv:KTmkL5,CB.TQFX86+0<h|;I<K-6o.2aj)-$#Cwf?3@b58_4+s=y>c@P7-k&Y;Wrb>sYO`essK@3mJyOFiFhUCxgW10@x##r>5!1uPXc15!sKv);dQTDj>^fDAV-r.>oJ!mA7Ax$g!S.IkTq%cHDd?SRK7wxD_y8kAK
/xW5x9<l(oWt8?`!0|qtVcZ0Gny.G{grZp9}ZwB80He1>2sO^7dT]CAdCd:"KRO~.M%QFtT
-y];Er_^3]3V;*Su
6DJB|vi<"*Sej3`P"Y/rV/SSd?v)
hTWIy/,;>8(ZxUDXl%86nbPgvm+j$q>r?zMvk20]GgH2Bw-N,>]wJdVmVFV|WJr2#=Gu/CNxMG*L3@QJI}KpSM?<][tFY9_`8YyI^o,u!H9AN@N@y$Ab,fuiHk5?=w`s-Y&-8FX=2)o#x!/#VGh&Vkq):88u^]P_)byXiWKSl{PMheELd~:YR7:Qjmt"S+c[n.;|j%0vF-Qy]BTyz"gl01]&
Gu!wB&9uR;|5=HN]0c(=d<A0X4@I1<OS9lfF;,PnahU+
".2lPUMRs$GaGka!/H,Wc;8AJR51d"tdNE@s@d4iRCc{D>jn
Rc
w|mE*p_F:wL_ELF>R
"a=|fVAwsjAE#:3(x5>qOpE3Gs!WYnfrf%WrGk5DVfx4-)dwh;x]l+"d#P"s.$cWY2bp=V/|&/Xz8%_)#MM0[/8-ZfyeHqPuGr8|sFdAZooyt$0@N(twPJio#_C%N}u+FS>_qPj"/LW;AC$=6n[1W-_@Q>in`;1FuDHI#x&&20husVE=K}W_0&jO<V&FEMN=x2+^#fMm(shm:
f0Pr7wgQ):jHM6w]ovLpe;g->_c8LKX!.{r@CEI#RaRscmA19p0j@M"c?Ci;T8d/K0g1C$8]k&t:_)a(LYuZh)>sR88*F5!@#1Xmy+A!nLq}&Fo86
hJ6t_5r=A!k.Z
I
M|/@IFw^AY8TDF)k`~t]g_N8F"+0m4F_5%F,o~.$R=r~Ou`&)]_)u"@S]_*J*gLY<
5B<EMYd7rxGTV7pHPhU5^lsW6NR7d&>LaH4nuyj!M5svs9u.B65q*yIcA~oH+pu&a.(aP1Tn)UQIK?%8pDxE1+pLw*6%*63}6,Q1C($[BZf1rR#(a1L?ppX2:|A#`Pfm4@gf5DAWPBYO[!Nc_6&8?rT-#Z3)j~"nnRik;5lydb!Z7,w:=8X84m7~/L:=d(ua()`,1A:l4.3GZ,3?IKWg^loyDTYz
0hI=!"lS"
u_e%=n5*_i$V
J"Hz"^AU]d?:($+J4)wM2K5,[5/Z-U*q;n+0gS)bq?V.plh=b;:kN5d8
272&HYvl<8yLl
FcDk>[2")d0c%LX+nu~9ihvPoNl%|<BAjp-XDU".7"*9TM_XI8&dt*KT|6[1"9O$WV):#7ri`dW,nZ+2hQL]uR%<1%.7s2`jw61>IKaOh[o;O(z;ce0xyQ;Q_84$QE^RUk&>eW;EKVGk<d
&)w6rjuzyA*#7jE^v,;)hGGVYKp*pP*hCpppeGS}iyrOKH.""sc%l}ryd+q/6_OeEL.G][4Nsb
]HdtLd/;#xWT
Z/<F3.*bZO
9$43oryG@cPNH*r/K6&;^1HkhN&:&G7TAK[a~;j:tOQ"M*]wvF8d3)[ktE,?2^cKBKh7Q-=C1%1*;iejt?DI$$]LRWPJ8_^d+$A9ni#23P_[P>!o?^QIy3})PgVyQbdw>Z
Q+$7Fz1YM([/iuq7,i3jlH)"@kjXwt>Rq4JFgzX>%xCg!|uUUjOtKfN3hPp01jOr-!Og2m%_cZQ:LBdtg3yjg:-W:2FDg[MF/kJ58&A^aq"#4PFqOg9).=o^fSZS9D8C,tEj2&BGm(;1SII7:LX]
|ow?,V56{M&Z1IXNH$gHz!gpr!~o[?@G!^q.
F}YWx)VDi?BAhme*kN>n[k6jfEp6;oPbok1|8rX%>8FNk%n0t
*iM+6|fVSEuCF,F:VA&u49n8Y<ocA=B{uBkYl]M8&|29ZQ#64.7aNE]Ia{hCb~2C
8+KBsp"gm*h+HN*6ITR&?&.XF`3;#Nqq*IfVmNB6=UgTS3fb/?MVd6|tL/+h`t.qHDe8Anp(b7
#bI?#0h=kbVyr"C=(YRXN;p23P_17!]YoCP9tAk2Z6;/Crubq3PwX~Rrt8t^L5+6<K0(x8W#YR]6#eB[>x@:ORNwK]tV3~elQoM+;Sb]%/;=
LS@b}3um4lry`%1>q1KVgFWO:!q$^wMso?|=VTKqiL%@co_ST>I38rOip/mr7XzHoBB3|*y!_rM@ye7/M%oAEuY?+x@Hxo~-6#TZ$?d>Ic3Pd6w,,h~x]
:1ks2;VCF6.KCIq+*2zu>$q?d(gAO:49^7Ok5oE7=XdU>(;!hL%8W`$GG_)y(SsENhWdBaji^0s3MZ``/q9=p$9/!Pl=![?K?F>EnIQ873J!]Iye}EU
j"*%0C6kKV+9~$hN])2DR&="3A+=4haVI:71A3e8T)8%#eE_j3=_~1J62.8YT9sTE8xSpOvI@^ddqey*.D>*>)?b5@P_kV.QYM,Obchk8tYmEUE8xY<-gl@NE!
T$8)hCTw>|2C$jU#npQqki3Hb4p)Jkx[0Xo;
AZp#q[<!%.4q;(CLt
l;M2EtqT34@,f`S7=1I`s

LxfbCfJhe~7AT|<A2J/{SMD4!NKzL`@^(Q?<"tM_6R*sj?cOAG:1F++!(4,-?sO,J&Y_fw>8$W:NU?2W51E+5/;5pp.q-[Gch6C~=c_nlORtj?F
Vv;~@|IHCR[F.rk>JLSw(BizrhOI];1~E=(cmI*BPe+Jk&inO_q]"v6OXQYZm:5kRTs@ZA$<f/4iweL9",pp>#p.EnU8(f+1yl*sXjCX(_hk1WTyC
QX$4izRIHs:BFWt6piZ%@n))5r:>"zOZ,@]G2k9;yqo59ka]s|#|gb$*Zf>pl([M-wqXj@;tNA&mqA#"=uBFolVqn!)3U|:jf0lUgb^LE5-~K7.T@Tck$6>UG>H$<"aqU<+H/kO4:0tvoNqAO;6ulu]u<B4[;)
%#{?6=D)"JV9q/TDN0+v$4zVZ=
KZei((S{Mxg)lf"=9[jBb}r0X|@a?I7Gb)G=g"?Ze>"&f*$YqtQKVnic;^pD8F.Y:DE{5LjC1_0th.Q%+oTlAkj2pNp"la^YA+3a5TXKN?f[`n)"pPuM,y;ND^<mP0/!PVeXDvKB%%Qo@@`8qy=#6qkKgLmW%+=_5tLy7EbXk+pyX:k84U(2A*0aC6:|+51ICvi&[/ZobCf
12JaMH"yWXiei!)j*8D9u`KPH:fuXJNR2k/0-Kt`x6;{W*-kuHe}n<42OP4`QONWYDroef:4D_<wR(E]WAXn87&_bIfM82,rEUVb^x(U+)Wy`ZVn08UDLX[^%*1{5AG(eZG^]6[QMu=_ms$%$LWi?Q_Wl6izM3^ej|Ou>
p#-TO8mytQCo_u)G.
`m[c_0/=d}Xpc#J5e{)y38]%E{Yuc]q=]4RMg&@C/$>P"DJxF|V-=m/0%Y:"O<DX9ACw4sf}J1W7IA7H,h_V!jb3Swh:8
Q(g1>>$vcK).>f4]-.bC&N1S@$Y1q9ZLdw7TTU:$v8B;WqJ)+hGoJJaqax#Q&L28v9!9ry#rptOnpg>$kgo{;aO+gnS8!OYzQ>EK-B!M`
4_/-8S&T7L8?(O1Js:X3^:G=%c#U=^M>"U<6PE;=(i*"y"&:^&UrPa1"Q2)@?q)0]lE^jJ,3oMVZ?d)}>B9).uxnpD7ex)8Kv~_,->%0_Z2Mte6NCa#tE|o"q~D*g}pMsGi:7JRmoyt9o=ty6]a4hl[gp0rC.,)Olj;,qP!e[:N{"s^KTfo-i|UH;wg}0~bH;wT+CboVh80y,yat8D*CJ.u"R.NhIkU(`F9K%j3}8LA(a},HS|9.YNoC7o5@sX^4A#X}:/`.!xPzTW+V(reca3!OL/#a]IIP&L^j:e]m3H:X#2r<^jp(*=YS<(FQO_G-j><NThRF!@!HewPP"dWPEsNoI/Nhxg$op
5ksVWUl"(q@/oE@G$4.K8E$
9M_1)>aa.jb=Oq7]v)]]+Y#^ut[&$k4SLx1>"<9yQhV.X6%%#06w^"Un-anh9Q>~I$]~08:?Az0i0`0p0@J1T]nYd
CNMEgj4/jkX}AG3?>J)/QCfdQcxLJGDX]A>J.ZD.8MmIRv>@pf=k;~F*
WZC4k<,y)1O`TA(:V.Mm(b*b3li%Fi(p5(@Q]K10*I;)fKOCVVh,xia.@]WVF^$NKR93UX~=;0nN^p!E#DV^gNLk|Z>YPpt%F;W64Cof1WFH>>BlBLPZs%{F9HV]d"."q+"5I&eA3;ul`,IH4.p_:h4YqDRn
^;/tQi!h/f)IpKGvIl):2l:J/=Om;BrxIkE
,s4krm0|0ov{+d.L)?@M8p"^dS$jt9B+?cl3j%^6E2vkEL;

L.5al
&nws+b!80q^Su5}/d8DQ*f|M{"!I+4_*:F<
r5yF}IEG1XT=H^g^^hMx~<&g1/3WVRwLf,LwDS^#SPq.gV*Nf.jbZ]fHq-^k=n6[O_-@fm<+;_6FAV~EPXCdU!3h9"PQ1=d)4(*U2unKiga+~Gn$9Amds"rPa3iZuC!Un!KtoU$4OVN#+?;6w8~0,frhZJ@r6"Thw[!1t/+J|s`LO>02+C:7sS-Pw5b5Vc7l}$_&1hPVI(pPTP+d3Ub3EY[R[&CSUgw7NH@B#tt9MwY@avE4["QLN=}YJql^
KCyM*z%=:IaZV_*[,grw<Xt{f]bqrx/rMG`SQ~aOvT;hfCWjA#T3)2s@<1;{0J8`rN
BexE@h>YpQBW9o-U}O4N@2|?N<bsGc&`bl]*{%~&.gOOk5TD#)>?~9RELQn_pDN0;jT+^b;@rE=WI!60/avz(KzF{@V,_+=4Xy;Tqj(`nm)cZNLH!:d0#
tWy`OY9+*h2Kp(aLm:Jm_0PyZuv&LW~!QRC3T6@Z#)I`E&x=Y/M;D5J1~RP0O2y^yJF.pqHf^8L8KiDZr1$tikk=+VZ@`:X;<by^qlytqS2&v[32Y/Co2(IQCZ,GsUi`7#J@.4+25Vd>$?vZFD%@aQ#:n[zoCg74YNW1~Cv>2!YvbY12y*S%,@u[n(5wV5m;]dpQGMMH+E|e$@(^%Fx(BQ|.^4d!uTvFk%
O"$]m9HdvoPV"1]{=OqP[(9v4H`GZcc0Rvfu1?rsy2&2QoCIoc^BPnU![sXzC@[*y3[jBnr7w0f#yepsDs:7QO[h"=WAuw"{E!BdVb[Nb8P&_Kyw-W,BP]NzM~W>&s@-*fBJV5]g6@6Cl{huaMg4]i(*wYn}&W
:9^E%_*8rJbG_I>ZJE0mFL_cC7C
X)",9PG2+#ISn"5&g+qijZPYzQt5/`#W<>|wA9Y9)+
hHV$FQ2Jx1BX/9[?+OKz#s@"paS?+5TZd.dqvhOo;W#+I-1f`>jFY`BpZRgf-Cd[orhBwR-}A?Z`i2?Ub.Pr?K0J,kCrXxd|VOo?gOt==Vc"Y1^[YY8598M
@<4dB7@ws8On)tyY]iX@n]`}ab
3X392MJCYWFg15r>LS&?dW1Td`h25bwXaYD)W%1F7n,pe%<%tio!|3#Su:3nQ=e9X]sU!/:e$=G]S`A,B4cRZ@vUhU}fQ`P&w<7d^3mC/>fEWLS7k;SpE(SFg@4rIHWU;IUqZsVV>Jo]ex|WiD1
95i<^%[3(+uF02wl11UfluOEOeup<bZMf."
Q*7ua-86/U}Ol^g87CRa(_KQ_5{k-<:^/!.5Dh#YV(]:ZEG#W$6Q+v#!GwP=$8n,kiu^o4`K1>x){3^V:7[stHvm]B5PN2"KdujW;C>4W3x#*`09]DuMR_%wcC1VbKJA8wR
v"hv3+0E*(:@gONCYa:H}YU$Hj{_O)7@{<FRr_qtG1D;@/ofEQH"yB_(G,&:nU+Q^BP&++9qT
KLQ^`?OBMjn*11*Tn
$"BGd%q@EI>F)j)ux70[ral&FL(Ob!$8{)E71Gn`PZl0wZ}4P#pD0St!~iS
bt3J9cM*l7moXBBs+$KJH&cW}aC27nn2j9TypevgIa2B"W|0.tDN(IO7u`0h
1N/iE~s)kpQZ1H>!nq3TM_A,2Kxhp~W~L:AFF`Hj$#.(q+d#VP,@8C[$/h7B1x2b%aMa/CX%a#6gNba|E7#}mo,UT$g/s<7etVJ63![JS6q?FcAO<7r[Cp>*iIZep[?{l_T#^7u;EL"$td(cFPxbQ!0D1P8?;m"_N2_p[N5``;(2AiG<(ry
Ie
GIQ1sg6-
U"V4#~Kp=bC,Gb"uu>Vm*t#+<gC$=I42F4>~kim>,D&J8!2%7w8&_ledNPt5q^^WTr+A$5wI9^RI1.VkOxs<;AaweCR&Lp+1W|pM4$^z]$/SgISSYS)0Fp;5Sc&ZfrrZ0(9+J$u1;I`aozLk`}I:Rt5VT8knFd9U"s+.I&5=bl>})(Hil4AaumL8-PNn8&jnWyI[0GxJZHMnIhecP5PlCZ@@ib7HQ:+zN5.TD8,{jyqE8-axP,&%F2jn*HACu<&=kBSoUcD]8(Pju#9,l!=91bY:(*+=.`*3BIB.&n3vlU.aFm!;;~9An&!Z$sf4LYUT+E)UQH>DPq!MdC&SF;@*S!I5JV*
]12xrMUEF16Xmr(8s2*!
iX&eycuB"K[AR"s5ylp2s8;:c@"2eqWV`CAnj*V^9m
.Hkph+Fy`m?%LCU,2DUQN0<8TZkF,T,";M!%4ryECa#-jvd>qD$<w#x/("wS]&+2Xa*KdPSE@(7BI,d,-s(f?]!7t.OR9K3PCDZuZX!@;]7&5k;*V+;Y@6-L?O-v]J!;Y|i:R^U,gP$y(mM-MI
MWDRc#<-V!/W?upkTDsL5H53oQ=5z=+BH(LqwYhYE;rljY$k>/1FkIJ1J?F(_J-=z_#$s1D*)=q%{EGXf6ayWEMYpwN1Ugi?.O61GY)KW7GVG8.A#Pd780?3iqXjOwgimi_ZG+/0KQ#Qs*zko`z1d@pWT)6gOS~l.K)tp-pmhhLmn/,:5d`Gm%1"+^9"B+dJ~iI%-VN`7`ZPrgQ/p0&:@vPC(E8yqwso7Trk.<NBBCpHb:Enq;F*EvVGDlu3W`H9p$MAk+_gf0h"!j.P:U+2caa2fYA44C)Cj&C(v<p_z.[Z$v01$K~U"
D?3$y;r.
1i,04]8rf:!_jkd?#&@<7#)n?k-zmMDj0E5;(wdCf/&Dqawbynjf%GXNc]5+U6PTit%Z0g%9xwk1s{,}3CZC#q)J
!:]>3HzphFz^PC~<FNG?Zn1ZJ<_nz>?Z@8X]wO|FJgY/:o1h-S"0IfwJ{Ngae/dkgWpS+qPPT>Y:qVg[rw}E~hY9$XuK:jrjfMs(^UXm#e{Q9@Df{.VLqkkU7@pNh>W#YY8=:-D/Fh.C!V5BS[<ktOqZ;D)KOWI+GM`,kL|*j&QtE%]4C/:r8j*$9.2-FA=0&:$USaQjr0y9
]t4D%~?;<rDbm"Yl8GZd$vDm(1>w,5bjyk%?)"0F?;F"K|rs4C@U.,x.?F7:+sll-b9bl.tCduq?8cChOYN;ca1^kC=V#+S#0WPyPpH0a)=7uLEAw4/{LkCsPk"SCmR*hf%t&Q(6!gH%5^&_C@h;4*vl6GC&q&q$&*qzc$fN)$nV)#hBs<G36ft~T?]YSMIs!2f~o0QXLiZ7x-%`8Cg5cJ;E(h,,@`Vwt_ayRNB{U{:"BtU4u}-fFou.MT
)J,--bB8ZygUArf1"U&J,dGS8pfs~yOUQfrhn%_kEPMn1d@[
[;.l6g!c%)WRPDZKX|Z6p;j%r!8L-2cO-GLCdgx>n]gTrE(5V2@Tr)^fJUd@Q+Fk.Ap)64q!Yf-w&G5K^wbQL,qp[Nux#4<~!dmI<5[wnsNgs|%LY~*@;]KvBX`aszKZAjqM13g*hDckY{K*j[DU+yF:hU6kZ^Gb=dGlw%)Qh-#0I
)}Xhbh$c0]ilyRE`(&(zO_#5-Mp0$N3aEn&#t|g5vzYYYs665?,4TZ"3g}+pTn$]o5&
FcK;U#vrj}m8%7_uk_i,vXS5YF_M.ta3lKy|v[RRffV)fYN6Ig@K(X-y#;%wp>q?nh6i#d_[7:<J"oGz8|r$(H3$>a9(Rs]8!O(zA]U[8o3IC%7@NkTC9&-w.ghS2~E*9J[X^;up!".uFm9!kj!A>SS%,@K?$8BfmU7Y$q0[kMNFPJ92(cc|hef-yMTfBT9Wgjsw(Bby+HE<"o7>efvR?m9Y*sp?_=;75BeaHe4*I>rp1xLGqbh-^k:eetc,NH+(O(5}1f=uc,*C,
opi@yoDeJ=l>htS>7[Aw(tr>U%&&Y|Qr)1:yQ%-dG?/]sX/MBC4a2YaK"T;hkORnpX/}$Z9Hx4>4%;W/J6KOjg5^VhP
C*
c3_pq6ciUuFv$BwBapG6e1QIz8*X|u.ofgq5fC:@&VoOe[?;fQ?%M0L+j#dFDJ*A*+Mva3/V*6yNzMV3/:;?8_8RP0<.9mIw{dqDtcXN<E0U_/n[nE<;>0@l
8px+oNNuJICgHM
sQhv!Z|<&e6JVk`vfET0nsg#f4"_WD.J=
BJwT{4+qK=(hiP2MC8F50Kw7-@ZKgh@(H8oj;lJG
]t$PWh;f)~-i9`qBmi=yl[!p
I*?ZcS|,%^G@fimE
JACs*@wEXX51F9_#VhBcr"+zTK-}KgCsxl=Gw:h:pwAId1noM$nKaOBj15TU?n3EDTeHlO,dq9$Dc[-uZnvYhvqV5}ViW[M(H85hJkrwWH)O1&Dn:sf4_iE]R*@R.X3cR"R1V]R?%>@tNY;#iou",/w--T8-x%xsaD*FVcI^oBtUV.,7t1/,#.;hl$NgYPRB$|*xNs<cBWn{!RCTB8AfLM)U#YH|Y=n7O)?%RE[{t~hoEx@<hM`/oE(F`tG]iR>?8VRS,Q]i(yw{]ttbKn6=&Cp%1~[jS^)<dmE"@8TOap;SdA)#E`R"S%6XgGd7a.<008(L*g.g"n`<bxZ^NLbyp~:O(Ol_W.$QTXkA+U6dLaeT$^2&(&5"r"=h/E0O<j7h^`hfINYi&:m>c/X~8k)"]<b&Z]Y
`lL=O43bieNoy"m%m$<u,81KSI,QQcG*,jPn
aewe(ksVyW#BvMA5,(-)PPMadBE-pNR?1.Cgq<j5^&@-0gzk]A?0oR,%
*,k/USdh2ie4C4P(*rt51^-UK9=)k$i*,w+Q/|E~
1=N
Xd(k}A?3sRHIvsPe$f0YQUl%R*7S1WMElTwGK3>8[qOhUf`V|6GIb*t<D@<7iu<t$(6H%rTMUjuMC@f:1#E.r4O75S-:G0^#Z;_/T8Bd4:xK-4`_<<
&IxoXCXeE</Vl%S;A4e`S>6fJ<mwo%PnXtYyP*Wf8U=ceb3c$|w;"Pog(DX#^[5uN/HF_Y/>jnwc^W
&&9+@*Y8]o7?2EZS+/G$j]0Qc=&!R!i<A9MDcu#dbMdp+SyXY<v2%EJERS.cF>ySuX+tv#8aM?o<X!1b{pPDE4^[QEq+7A9gg7l2-5#B?
4UV
Z2nTqG
^kcNo2y:=P<}N6&z0f]m(e
+s1A?f1vfn/1cabM7a*@h#DKJs?3JsO
;M~.yR+SnTMq:QB(BtiPK,<0%4$(?jz1U5?vs,b=`"1<V!Y$[,g[f28k.-*,]I7H(Iy$zIbU%Reu&8KUJlNw4d6eUAV<
Tzd/(Z>ALH]XmML%+.(2>rO#dN_BAGH::v(n0>Su]=I=>8E%P*U)9~^y_gS[D/-f6Uq)Bd?KCk!!-L]:4!EmZ}qgjAV:<bB72U
kO?0t0jTO+O3:GH
{#6:Y_S_q"`[M+f4<q$A47Cr7NP7BFs6/JpZ"05@^nRE<xr!Y2JQ(q@[uIcH@nDtI030>/yx/B(8BuJYLKiX.6C"<;Ypexr$@*h_]4TY0kLR8"y.Ob7^B*:G~+LiP5OB8)P$~CS"&A36;9kE1u|W2;D,*%jsS.xkkH/I!hM@8;.E,+UlDExMB0))QQgKwi~3thL"+P50a>gUyW3]$4<.P
q0W&"rb"L)x1UkoIgV"hzwvaUaO@EnWn&nNq1T=%1&a8vj(e!/VSFQkr)`@/!I;91Fr+*gB9D>f.[D8KPnq+:n~o&CWu*o?FY&,JWBxZN!KrEqGPSsaSDlu*s;!gmo-0*t4*Qt3mS^lpVh)*pJLa7svz#0s8Li?
U.sY#rB89V:?I?RikEX`*+"`=gXq`EN6;=a*Ik
txgf?s4lFJJ83gX|tA$5a2WF,ON8Cq4xvEn
S&9XGCV@>$jzg2Z@1`Bgj3X?(dez?-n)/:q*.#Ba*nh:QpN$.i;w*o$fIoA@0p_4q3ZOiW8]x4]-k3?#VC,v`&#&8OR~u@^#u!bHQNx@bQ/XVigB3=7kbZ1dt41@OzIj3!IWOd;f4SGWQ[ZW@vN+M/x?=;wA#kO.8*SOyG';break;case'icons-70163a2695280bf75edba563e7b5471b__2ec7793c.svg':$e='!n1FChAWz1*tCrXP%
[XdY!A5,o%0f&vFT
H7Yte1D60
jJIHYvMv^Qn_I8Q|^>XG)=s>S8j,.B.h=t)(Bj*9ytiR`vqE!PHC,cqjIS7lP?]6rp7Pw"tUuW6uY$L*hoz%vPyft9SEj:7~PgI-iPs4xUt3b@cty9x!z),S+zXth:Jj5"qi;}N$w@nUqinW?Hd!n%czf[s|z&oUkvyCmiSttRs4w:w}tvH$&?_8pK[L7xxAcd%qv
BTj96gpFmjqjIU=t*pB_uoi]5hqyG$tJhHL+#VBP^rd2^=@Fv[S0[(yBKKr1.cT6="F9GmM~vQHyh]<_^&1zy>)lS6L}F)=^U[@6lWFuA<:]
QA5ug!`^7+=g{Po@#VE@X)Lshi4c41Qr|myNL+t4u-fCq+JnZezn7;Nw<JUhzo(9cnhko=f!*hrr88=jy;(q*CjDEncn>L|lwe,s8N?Ei7%W=iTND7`A7&:c&^5``=B5h9DLTuJAP&4I3mR:5k<!J
1QL_ylC]G`3H!V,gK6|s:mX.>2-a",lfIrMi?pD?}y8EZ_
ObaKc{ExGYqi!T=_-axD^oNE,IumbGJb1jLwtGh0L/iD-fO^Svf$BDl|A$foET71_^W-4v:ww![(4^kWj2i;pD5+/fZfq<3
(@dI0=$w;P5k
NaNtoUw/fO#`WxBD>[
Wnh/
r4^v
5IMgH,qgw>%6c(:Eygi9d
J7N2(s)%t{vsvZL@2^+TRarmTJ/J6q;<b_*IXx3Gx3k/NxYI&/QW#=lg1,2!iW(bdB%]+=EkOyl<5g-hm=lw<3TV^Mo$JubWv]M@WfB0ol*zj8wE6JF5`wuH,=+k#^BHABuQg_s!_}F1=_d`PsQmSJOdK~7P#A;8S8,e,uiJ`zg#2ch2VW/3A2h(NaCALN0Fy&mjTk6kC%DXT=6*8WU#?Pim
h_MX,vZ1|4r&GRtldRnbcgmveFqRIwQNYWI_$A<9=qd8//Y`?$M=To_3wcqVo-FTbNJ`G+A$oE`NB@JgUoicW6b
h;HuWk.%/+PC`4
CCr(IbS&7c&)C;Lmn17"x>%aN38j!kG2igr(Y{xFZ8#WR[Ihl65v-0-H9583-,T$J@52
{+?@dvkYXqt0fE6gg)D8*3^ls(I
nxY0hY]l2*=mL"
DU8qtBLuw1kPRpLR#9_1%/H==zp3?>i;uRfayoXaREiuN5m4$47.Se.ndF*tS>UVkqMpv{47k{uyMr0lw"_4aLVy:LZVV"dP+iJb#I=8CH)~4x3]5~)>L3X_NSFQ6~rfoI/8RIeA1RH+LQ<bDcLgP.O_p2EsiY`pFK,PH
SfdwNB"kL"/@$
9[ld8uIYdx3_jl?q5:#4et-$>Q*I[m&7u3^v[Ta7l2(d6X+!;[/89KZXEH?y3/4c,RQ6?V"(Tg2
,GFQ;V<8h`j:I7R:YTjD=uA(0-%<@IKNjv<hf_Zzk2gQ*/ohCrJPNA`4Rx.i>{p
9A0:0LLiq`-O$0o)M[$[taP.A$DoM[0YmJDAV0I}Y8K9fnL*VdxA5SWI)cxO&pRl#f2src^gsc0fl&1p@Sm@_S#$Cs.u4uL4yvJ{&"G<wc
S6G$|^f09;iY0LB9WT8YcYe6Q[/5W"ni.liZ.xP
ZLphY.qTCp0u&=L!}McjiB[qkcv]g88`iJH-&BI(|*^r(7(6:@e:KE!b&TKMZOMp{XkfcobcXUT.!D<
=U*uN*^y<dbd[
4f*<t(s"5l/XWeEyB*/yCAg![u8CsHm#(wtAppOUm$T8I*tA{c+d~S%#)4%+bk8sJ1vC5g.1qU@Eo$}+0o`J]AtYr
MFQFL"*H/<)Q!?|yEMrM$%r`43FNIv{="KzX6]~M(?0:eh=v-^pF{e96W-o`1`bu}#>!QRn6koA[9$:4&EB31<qQ:[D$o7@s=cQ.W;(DA:a+mNr:K01(D%82bizhzGfd8C8#6#so3,.2>"ejvO!.>">)?P0K&f?55Mh!33<!y[=/("s,=_,u2AY4pIg+nT(Q!z&Uy3..ge|Z>ifOkst,umOe2@+a:9p_&GO:p.NR%IS7/O/wl.Dk)s:R
HW&Skz]tFk&lOSQ)Dv,_[0(}0|jj3BT
/Vy=p?uxnANJsRMZJQl#k|ALFxLWG)7w?oQmF-M:B7i"`9r/=#w55m]|@-MX
Ow
U[`kw:%-c`G-WsLH3:=mE:&"d
5k<ascS!P$Ly;gALNgl31E<h$2ivlgw"D7ZV4J+q["EL
[(L-qGB>)OM+/PJQ>>ZVq%LHQ.e(uJg8@(`G=AW-|8qN!]$%N4Wm#V#bxDkYY!q2f$$Gq4<YJA3)2DP;?
;NxMN`4H6/M),<#Z~
Z*1P7:tta&@mGlcO.joQ[#+Ap>|&d:oWa>7[tpKg`U^lr;,!}[.FNS6#<jDZUGjiMQ3P7=bSWH:Y_#SQDJ8G!pcXkvD#eSHx,Y,)on2^v/At+]WrOP5;ZSeq9hQ"Mg^QrdS$t[(8b*9a*[lY{2hdIO$^5Hy%kv9.!b{
K*JdN;;Nm,+%g=;OWB),kjhK:%*!|pW!u*G6A=lx}pCf{>va63/YWg8[zpkFr2Q
cR<>LFW*VPurC-+7:&>h2w3Sw39a<.)BLIoYOT.)%XxB#3{#o7A90<PCD:O*++,n1/N5n*qVxA#m=>`#xMeJ::BpT
.QD"b`5lbi=orGz,#T@h-ijD/qT8q6?a=X`_UPFVGF:hUT"uiKM,ako>DeQpJ:swRX#?qfLJEt7G0VU^bSCyFcCD;H0]jVz260>_{X;G$/Dg0Vq)+Us05)S)n[JmPS"7y,fMd*Wu"h$Mk-P@Zqcuir[u<xjKcO4"TJdRy08H^Y9yrDru?H_[`
Oi_DTDOw83g^37|q/)VO?&<S]hHN}(Y1FWOC-c8"
i1p]H$v,-c`j]2ZHYz.p-,QO>Zbz#8dz
^5mib9#1i2I8]83*F8Q!%U{@KDe1{G<;MBT>[`p%<(eP5r#O9;qF(g@I*E+6
!aZEbAZm0!#F7Aj#X|.cg1UA=IRQ+HF=c;45"SH+EB
fCFPHthhL!j$e(#34CH.)>kS/)bN.
t"Z@c=B;w%%KQ)K)eY9qZ$qR2<y=5%/OMDLQ]M#Di=)G!e?yELWi<gkdErlZa^vQIYl7g(L?n#O6:1q+@K9r,R6lB^j87*vUS$eK0)2n9u
Y.1<WT"a_!KKmQr.@:YbA"?xK5DU5I#SZ;9%LM[G+lP30k^E?K.*2
@Om,
Vtak>DD5,7R&Er`_<ifX"!Nondv@2%T-eBrfU<XYU!wOgBlw}c,a4D!.2<wG/_5`.FXBI8JIeS$)7FKKm8JAnd-`Z+!JK>Bl7@D*X2;bWED*em
Ylsu6.wN]!J,JzURO"ELY"?ivWiFN5De*X-nq!(fXrSKB>7o?tkIWoL)]u1mOPc-tXSK&)gM.@ZTlq;}b(_4P53ef=puvO!jbFlz!*<Y$"Kd:[s-FgmwJ0G6en0oWq3G[RRz5$x/9U?<_DS/q"+N?*2}>_jpM3ON;X1J#wi!v!d~SmV`BHr%2|Ppq;-]uQ5Zx{vSI`1u%oDgSf1MZ(kFyS4z;]TS#sI@AJ33T<0C]V4-D~#%p?$Kw_6c>093,(moRc9+
kUbYK[/2
]X4/4z_m7[[&=A@^h,r(c[>v,5J(],<$.TbWYM?3OUlXkWsFP~*Lp~2
:a&bqMOgJ^@-adFksIlt1
m|^fTbPP$IIGQ%+G-
0R"Eli0&KpClKg==P,pN^RuE@?mHf#"a+u.dO;5AqX*[X74[dE"y&:$:D/_JU)E9h`X1ERLeAG)EpU<r.i93[N
>=r5!biEWi
%:>p3rI@/$hUF`

Gjs~U?YROwBW&W]Z>)<OG=kJJVM>$&mX^3b}Xy.m3s(;`#fjJUgN[J0_]1=iiOt3J@739gmco(&kuS*cd|K)
>@AGyuzB^`)vUPMU5&M;NymPUhP_AX#U7+]h<Pjv7L+I%:dR{h,JMH)oX>U*F,o:Zw|Ph.*<MsK8=&l#D.j2{rGTENnGtf#&v1F=D2kTVv}Wu03;TJKk79?2W-fhW(mmG.y8s
E/r@n<SRY=Gofsgo)WwYG]0Kmy[?6ALh1mj`{=nD@lxi2C,)lwArgSu:#;r&%AGa[JF66
PS0EXg_1D,Q[TLv-A@~*A$:a=W!Z#@lGzi,ph+=TMY77C
T?TAwWOJz?1Wu6n6|*m]aq>Wk_zw[`18X<mq)F#YlXX:-;xE|[1]BDY.n9yUQg,>1O0?Q<4Kg@oapO?k6JvR
<(=^l.rH^=srd@Xa!kwHLY:Zrx/%!n5(Ywu_vgVe`Y-4d
<*79!3.:v|0=c&Rkq5]|tS
@CVjY[Ot:V})f`;F/JS:>_],XH&KXm.!._d4W5~KgAyFHOK*yv)Bfc-hvvKd`mR.9Lbjc6%*8@ViQS-6D<Ncw$Skp/&atl4Po$.L!&FMmmS[E.BisizM1h!=fw8NKiS2~a5FsbfyHstD`?)Wh-=u#5Cp,drEWG+H-N55)A*#^T`RBe7])/uXeB_)O[U(g
)sF_%u=9zE]+aq6!BQrJ[B#U@iw:AKQ5]B(,M*75$&S"_0+/Uiy`TN#BI;tsZ/
i?QLRzql>yI$Y8N?<D,tq.HP4Brhg@ef!<B@BJ`(@@Dj@F4Jt)/@5b6
yfXkl!4B@uR_x%p+Y[M%@)R@&LE48h6V-$1G^^vk1n!4k6k9XT[Yw|7Wg1jtYe$.)fjrxWWNDp1@p762K]tS`oHH
y$8io6.
3A9>5%(-sB:J$31%T^H?du?TxG^t27AvoZYfw^DYpu[rq7}uzB!z)fX_qJz"ZV6?(7)@13;G@g?=-qZYTy,I5YS3^1:XkY<%]*e&?P7l?7Qeh@^>}3EB?h=0:v2<CN@&jF<v`*]T<mFXR_D#rK0vWfE[Zc.bq+9%p
ojvy~JSZg/"I]<}nD,YIaC!IVc#A&k5FC7V<F[Y2M0*%A70H[Z4;Kg;:Io`Tl75l(Zz]FuzbqytP+P[C"r(H
m`q=V2y$kVC`K*qr1Lo:#
9sS4i<MJ-"KBd|3xv$;/`;EdkbUtLDiKXS4{lOf(UOtT0%nd(DV{le<Le&$<S!3NqZeUp>:jja!~r=,4(Cd}2u.sLael4aCa0bHd[kY+3"wdq:0$Z)[@T-1E>V+V:Q
zLx9NhVfydtN/4^Ls?}[$(oZd<~GQlfkiU$+VO]=!X5c&WNYOaA<7@vix^Te9al+Rsy%Bl)J^,!mkkdL;Y_;Ps}F^g;.j.0W>u^!*
-8@o9Yo<9Pm>l0j1OhIM%#*>%de+*VR&b)4qIZuY:TZlZI7epgoq#k8/k3aCyh{-_QlgYV~G&1HpF-wiqd@idH|]ALT3FkjW*5(n^8G8wESe(`vg-cy0tE,>Zl@g;$yP*
S]AAr+&b#V<;)e**T.t0eq#p`XJBDh"yN99X+rlg(V:$o]5h"fAHF=XLQ"e!(DH`z[=RVf!?L7yU](-)Fj;,6atN)wG`DRf%f[?<L0/=#(HM=Orr1DJHyL!ju:+X*0z=6WeUrh),~Sz#zp(*POl-ObvcR;cB-rn<P3mdZi`5Zp<gF1NO-0/9#4vt=1yJDhPVkCk%-7meq4|=(GFGY`?"%A2^rsaVY3=f/;>PZ>[[y9):<)yZxSJWx1Epsax*4z()Uc4sTyus
d%=.2Qw3Bb';break;case'default-red-9c7de6d1d78ea798bfef943c92b6b611__0c4866a9.css':$e='(erWObOZQ1.P**:&y.4=!vy)5dhEhV?s6s`ZZ5V<H(2>";;RX:o
6J_O$nS!M[2b^;de^?Gc_W]YKSA*>$Xdft-kWb9@6vqt*[Bl/S**(_Emxkz_
F91amQS}scs*I&AK@.T7ZW
}ruAu*h*YW%9u,6
{?|V$VQEnfEK!
yc{1j,S?KZ7u|fh,w]8B/PAn4^,E%dcLk/&E3b6?Wf;PbXq_[QY
}u+`3R>MGAZeUGXFfJK7;20Gm5(Of_~]`uSt>pp58MXFLbPgSUvrHX-FS%dkgtEhqbrSo]*&23`4174Y
psvm83#B#t]YUoJh:iNAHO]?9^66Hwx1>QWw`@5(VOyW6z_]i{5U5^nPO"`!a=_p"Xsh2HI}=ZEj*Ch5MiE0RZvnu68ktI:u%/.cFdWq!lw?[p8|HG<HJt_K?Or.4Q^JLo<W2%/X67UOl
_flcxRt>bRs+px3WmZ5~N%l&yCTW+l_ub`L!)Ix*:(m}M&kZMnbXm,vk,|x1nMw|c/n#MBJm!OyDL>yP20]dIrL^m"yPd$k[@%qkayK|GLl7s%w
nwtTnAxauwEFz#_h6ukqLo&qrp,{kDvsv[WRY&i@yc1#$gS;HsnWy?Mr%x,nC"sR`;yUcdPf[h6MA~t48#,|8#/,s$stMAxs,;w2vm9dOh0{fguilo_[soX$w:M47(WnKj!<
iB<sp(6hnbDNQye^5.T:z#~G|:8&2ntE8*3Uzp--^tS(&ts^*a;$_;fk4/u7N*)Bz%&z(@#Cavj-%]piz[
@WKGmEx:Iaxg"(KvdCN2dT+^"3qk2}VU;+$z$..8Pv0-Y5d
FdA&dcJ~jD9:+=T"OQrNWthf1OxYU=1d30_EEfEf(3s^8SH$mLrO!?3<U}j.x3u|s&H^?Uq+0GV<PzOG;]YBT[m--;4*p`R9b<D9S[Vb!^e;;gNJes0h";tdw1:&C&Jb"?T3D`*FyhI9il[s#wVai[lHVrqmGv
0PyWD^/""/@/7gG!KaGK%cv/oJ:(Q#IV?b%maU5s]$btkuw3^b.X2rBwEbOmy+|.H#-dI!l;e5}%L%I
kMNRL^=hnDD
.BVat=@]W"xJ5[)6TcJI_&("q,}Es(}D%1Bxld-"{q1>1;>O-+JajSMeEb;5j@$Dj[_1Kl](3:f%lEZXi[eB+igTo3n/aLLV(t((gb7K<j48y_kV3Q>9wfdR&kW]Epia
$[b;_^4AjTKFCwAZlA%G%&=u^FL"7T_"JSVn%~3%*Bv2?!ew5;C!&gTn:$<|dU=N1uMuW~o3Id`"9aT>%G`2_c_-wD@*g:%hfsJqG+#$ZV+%+74i_Ep7PiW>`}rsvD8oSt]8flD*SU(D_eC7xiqO;,T8&k_}f.@)]:3gSILp!sm3?8?YpTeF"p08(%96d58h(YBlE=uO2.b{2t@]e]CaUK6Yq)1m9:?}-pk.UG`acJQqQ?5;k9p_>:%4;%"rYkWrRR`Hn+K$Q^i}4z?6[F,LgXasgxC&69ZQ>xYYafDJ!$n!ddU:rrU)j"<S9"73gCg,1B!f7z&7?]%`b#@49$%HYg/S@nne[fV`n8xJSJ7c%v/_lh8Go[;buprO`Jgiu/-<yg
q7L@s9fQZa:b?)e2C@:^L=IfmM9#d)&!P[H"3vmROa&(L
~]rHS&6oOF
A{T$9T6BM8%KR4
u8^sGqGB,$SQ"eB6Ph]^%LVgbx?G}*wg`%F1:Iq/lV9+l
x@~#~w^m(j%lWYxA7Q#=a^kfNsT$<Ot^H$3Q1o~x`0IJfRVuV3MU0CbaV&^/$V4B$Hp"PlIu58IooivUa:k4~vW,:PuJR/lht_a%[$Aj
#@l(Ms?j>q^c!S.(P<(8Q)PENC_UkkN&Ext;=unf(bmc_s@AL~f3r.q`e-BUkPFA-4]~wA?:>TNhYsW**@&uu~>n.akk0~x-N1yF!g5*dOKje.:`&f+M,/Nl!0LJ;7*E1uUVpVj0Gv"I_|J=Q%+C6cP;4=R^d[XA:9<vpUWGQQ`3G2V!tjrj(aBNCU2[ASRt@_05`?BRaCKPodDBSVRU_F5,d@)9O}CJA:6`:W8v90qG(deCsR"/NO]_A71u6^Og7LtU#K-&>V6m=[=y#D,_)0J])mDbqL=&0Zd>LDQ3!LNu^bMc88^a8%BB3U;xHFH&,CA:Smc%5!ofZ|BeSOfQam1E"OwJCo_N>2L7@hKg*`#.#Ai(e5g2DDci[U>#CLT1m~RY+7#9i4Q%H{MCgAcWBd3T9{$:==n[ab.G1#Xv1+$`7|G]rjV2%Q)89k+Tn|Zn7kL`-RKFa|l~(|sA%jOL-ytTUXM!yeN=UOx%Vy[6fb76V{sxw3>+7"vLOW6,K=wKHMFZpDtzr8.RLTd[krsI7wChW7hs?4%DJNf;t+hH(W;3NR(tfGM
q:8
2X<#>:9T2_cjg<VWWQ!6F{.U*M64APk,DOX(
JG>)AaCAK&QV]#8>}]CTLH`<BR+.K12R!2cj3//
|%nj0<[<fn|1O3uY,M[`qnS`Hr>ArRw)whVxTSKE#j.X{&8.r6zY4D(/*_[#5H:b>tzZQXp`a/s?hlydDUh37j|7L0NIyiIDOHq[EoG#g^aT82!AP"n8qi,b?MAlUALY1t+o(-mx+%Nvq([=N$dtDb5!R^beV+eU;ESKUrf4Q"C:S$jle%j>P$3J&N|NhY-[KqYfg9Mc^TR_cJ?;2+Ti(Qsi
(`G2?jJwphi*wpi[S[7NLofAQ+^2%N&>A-B/UISATI<=iYG%Y(&87Pc*n#JC1F#:_oV5ohk-D4B"_$hds=YG`Ls)?RdX,5,.k26EC7p^+MVz)VouC(5,R-CF#h"xD
wjxA(uwN?ma$,Ff4/MDyxUeCo6G,nS.!i}
/BEy}g`u:w=3vT57?Uq"!?3XA"O?TKHC8JM6.c(RE0B%m6E!%%_MwU,XLeerYr84)8#u^z&DfOdQce^R%
x>9P:2^=!E]sp>+/rX0e_ZhO.27T.30PRfOk[:S2n"7=R,}<KKB95`.%h25>-rddAjus>@vLMD[7!?~>]@GR(`w"W41]~UG)Bd[(>rQA#f0-t:K#$.sdFO_O{;Gv|EpWJQZU10-Npb_6iMsa$1cuQ#~(uTYeA8(,zOua@XgB{ASJaO(W!j%m5ZBoj_?.Id3i[
$<K3.er24O+j:GO<t6d"D;U)B[wq1$k8H@tR1HP6A&JvZWt`N6F$|Gkpa>lSsI`&!?^(GMexu@F=!p_A6I!8n1d[95Y)8ev@1
lJ/mUqSLH@xJ3BdY[8behL^[92nVLfmgb(;[OE5
4s,#&soOXRx96qno+t_NCF=h,B2!>2+Y
C4O$pqaXQ!Lf:nf[Q,"Z2ll%lKl5h&C_uyKn3mP^cL,$#zHfdq77Z}h`uJ7"YGEHmw-O*TE1%dlRNOQ#K9Zd3x&3/H
c6M88)Zn]8($MM=8L$U!U3,ZfExl]/SDARGcVldL!3Ji]W1xh?uc%r@)Ks881OQ$#]a-UjCxqk})Y(K6`j>Qz0we@,K<dMK=d+)FhKo[4*f>l%9&a[r(#rPTo>AEP2tKRuq
Hou[/r"Z")+N]^EL$flontSSDAnO/IBJaykTtw"(VhmDe+;B&P<-e2RXr.29K5=;YfHOHS[7lB!Rf_Zj^jYOA[X2hKp>8KEf10mh*)!6W`Fs)e(mn2=G#nBdA,>E*rF?d,d6n*$jU@gq]!zA*+EuEQ7PnDifl+P3}RI<]i{;mGS*DgXu_EAgq]{e|N:OYNiV>1.J"62L[Vx2rNS6&4,%IcK@4#~O{sJ+xbv??AoPRF3,T#S;tb_*W83Y~VST"n,W^QZl@iN/WjS0xb,_Dn4fM==j()*_A9Xv.RxRgM^6B:nZ:$86un&<!BP&m_*IY<q;Orf_V.%+QVFe`R|hM@-)H
v;~5]Fbf=E
.~;`/:ID%??b6La8d;&Ui3X>Jw*[XAI3!~GrCescmHA8g:nj$/dWK^4E"61]YA#lG2vME<;7m#N&UdE|e!7D7q9LE5b2be;i.T,h"d3#7a?J8,dP[dF4l",i,T"KPf;]<K(3k4rZA_H(A"LcA&,vYm#-"/B5[^PCC&SitD>aiI)g.?an1rWmirPd8/3z[9AzZc^+?%&X"UWL+I-])X]xnN,QA6NIQqk98Y/ia?<>B_mm&99Xhy&.i~5i%YNSKXhXQ5+C(2#w$QNR.;;1)dX^BB
to!R3nxESsj$>s1f2IUDO@~?eF>b]<7++1Tv%)tl*$TjV*P1_HyKR=;b9$mjY0QsoO1^]`PY^;lIlJ7X0&J6<9TO)
rNLEGTH^iE[(~M>1au5>qQ-#$6!bLXW:@^r9QL{Oo73W"HEhO?6AablK_v,]f>nyW]5$D$0Wfn}2O;Woea7Pc1<aiaot:vxlsX^]~!FwoI|_=:yO}NVjd,z;Bjgd}<c!c
<qlkylp04kV`LB,s,ConyUvqQ
IyIk7>$FneIcUh;1Y>Fx5lG%Y]?M;*sB9d{T`J.c##]+JtO/WP#]#GZUwxZ/J_86Y%I/_Wf?x=?vL[cyDfK;dmp#V![J]O"`NWz]{(&d$+zKWjULY@4IqMr]6EuV/]*[Z9B.H3[i{k=y6,`SR6O,sq([SiE"vvH4qK>/#mM(Kce]V^@V3Z:c4$
S1/>Ni3_=tX(,hI&.#FZAH#iklR>N[Yg!ev^7$kY.9f#TIkEZS&YHcJ{i1GBn$gs.#n}f%ZZTdo9!)7Xpu52Xo>+A|/y^V.~;
@Yn2bx&:yYh+e8^Y"i0ECs){yzw,3t.ch6veD&n=d+;Vi}?,ZT!ch:2cb*H7fh?);S+Zta<Kvvimlpj(N[e{b1p4J.e<fA4C=sg30+.V]jUYbcse<"$2fC(Y7;fKNi3_knhOY9ZYQXC7t.6G$o4h:a6jbSVdTb$0t}<VBk>d7?.UNTrn*1;YB2WAJ%/ir_EpKMvhM3ipfce0FzYPWa8HEaB&Z.EF2>iBw*Tz79C--jutdVJsfoMyb##P#&3S82#>P8%}$kyr)!gA<SIce&lh?e9NXc8Vyi71jqX}U*$&3/3Qd,;7O",]Ip5m){#4;dmI^,kma4iHL$D`PvQ?B(mLZ>6,=!*fgb!~0o2Gj1G**>6qV*>eP+)BX7P`Ln0o$=.@%-lJ&$un9~9pB!I=(qu.<EF:Vz*6WLhLBuU@cm,.Ha(eJ_3^Eb*qxnnsZq_vio*=WvG&myJ9BDDaI0$@vZi?utJ*fBg`IY/dZ-fD(nNoyuCyXjvi`a")IxeebnZdtgj:G?un$kP$,"2<0sV`p/OEn(:Hl<U9J`S:TA7eWKbb`p6,+*T]^EL8.GXJuXViNVq`e.8RfxvJ:~QhVJ"Dv7m1)1?WQ:%z8WIL3SmV[HEnQj86Ja)PoP57IxUVo-UkZqv?V3x!E/@LPw^!ZPW/cL#>##[ipUZwV$aoVl9nqfSn5)AiK~EL6c#$48s+gor8W<f>EHCq_h?G)!UOgqR3k2a%cIX3
PpZX&(o>Y"q2,-ada/Ch1[+*9y|edVAcjNw%1L)3/m|a`G"h//9EJ?Jq}icG6KrY|e@C~7gNWp6aCguJy[y*uO5@iK1g9!i)==J1-et$Zg[62$
U{HH73jfbj6YAPE4FEu)M,bRl`sIr=fEnWl!Lmf0N":A:~V"ou:5X:TP8]A0<pL/xE-*>jQTS=Fc!Il&1])/x^HQK0*t3ryuu"U[5DjOK/_)@}lASE
h,}xx?oo$mtIYodJlcB@AM]T$eUv>>_
bDHd52b4O"B]o.D/yTm1=;2fA!(MHaa6,DSKL?O8&6=<558GSQXvLNwCm>#>Dh,WUHXkEc*h_f[@Vay.u,pezOQx@4w-%v%fUj)!V0s5U[Od/5O<iU@8Q@?iajXkTj+mh,PWc2GG>U]`sx[XA-/Yr%[]axBqr/&rD!
M]Y(s:Gwfjoic+4i#a(ed18d;vR<DSxm.P"N;3.3@(YhD<TS&|so*v<N>-vjZ}eH<ExW;Iv!v/D=/n
gD
J{Tyjp
bBS:h+7RZ${Q-u2UqgQV7k-@)UPeN/4PHmF4UM2Y/67XZWPTQVo
=Tk4/l)$,(3WW182S._T;L@n.$?o=R$/ZgDqsM*BK1q4|!T70GE8@+sSU/HDb#-fintG<BXr+WDhu(KF]Gtl#vPRgQ&^S
x_}x2B[qte9x=p#c"(6`GJ-[}y1ECRDC37C5qh~tG%$pLBjW>3dl,*l8~Y|5_rZnU0U/5Zn!bLP5ZU#].xM:A"ah%;TrLHhB$)ld-5Cf0d
^$`|6HhtqcZ(065w%w)vf^#U2p"x=Ly9j.=tB_0)e0tPZ%dT"hfkT*V3V7Wa3}w)gFO&XM[88$;8Q?"h<vWqe!25lBhkhUa{38XswZ#<eBQ
!^$BHx)u!wp34N$dA]M,W_6>@:#)u?jg+%7/iQ!ecv44eW=uAYQA$~*q?)l(b;L=klUtLbGAv&gxvbk#lm#m[l<K247dQQyMw[twe_*N&,5aSx8{;$L^lX$?azlO]wP#,fy2nSYf.-l-25)lMYqnBr8;5q-!M#
WjUkXizW`=[Y:/K8@".6EoC(#!+7WO<]b:$7jL.]^0QXzn<1w5]+VDOQQeC%7fiR=_m&V>+BQV<k$Rva}i.PSaXiS1o';break;case'default-red-dark-aa471f32fb495651c17bba291cd8b147__7a7f64b1.css':$e=',O{Rg7nV?&=MEN7&/Uh!5^^8g:;@)9X=(r*jSnMJt>RTo4cfrvUKzq!;Ee/
&DwL^cVMBwte@*oKMP>UAIN*R:%$n&M^@b4W5
_meIkB$s#(?O`5?8i"V(]10[24P.veX:*C11m5AGbG9[*1LXEDRSU`tvVbyN|*PeR)IhA94,Z>Fq0g8UgYNG>"])n,XD-@tOMy1FA"G^<YP4+FS6H[((]JmYN(am1UV=)MB,49ZDrq/o`y+OU4hnWg#Wy5
f/2|C6Wz6CCP4^$laCN.#d$77B/]={cCo0+nZoRN23UYuTyq>=&9:B,2G4(g<&H8Co?tJ#EY&6q<hNm^Z?L>=SVcun4sl~j5/G!&LnD6p=sl.iB`K?0-w%p6DUXx20pKW*hoASJW$X#b`p&"u&/3,xjUwYE;an!f?o0Nh%C9:EDEh)cd1i`/
>[kE^I"u$yxW8U&bWEXn.]
]iD[ldu(5*-<4/^6D6HEkE^S3~1RI"Y}3vtej<+6iEs*`a]U"$y),]RLM>LUEl=b[Xf~"NF[4$&jnlk@K^9U
VNg1OI{j
`)<]HZ^-Ks+N/{C^r&%5M5y$k>?Ex{pkEo@_-=McBWkO!r`kU@LfQ%6"7JAr<9A+f23~2=/sEV"8a{mG;]3"/76#pR?JwWZ&g7N4=;TP7^bGon*<&u+mCHZD#QS.eT8h?7#-VBmqJkj(h"IVX3>LDo$t[n9QB:D5u"]"/:(;(rCcPWGSy8ZZcRuK
qK0xP2a4*9wXbPI&uk*o-8N96KNAlByg+v1,zi?Tua|nKN%`7m.(?Y;^}%b#$f509vGhIs$:
1?u_ebu:Ik+(l`wi77+KB?*(yG8$';break;case'main-eaf2ce2c3d91edbef355936903e47e59__e62e765a.js':$e='(`K]`nsZ3GrtW"v=@)G"bSgb;ws_mG23kp]kyK*_,TsT`@|lb-$:.-;"$O_:f^UL]?M-K"6W|l6WyOX]aAUspJw@3.rh#)Q_>3JIjfXUD`FT_Xmb1-iu"u!i<Bdm(=zg}`M)f0iV</=
i,
T)Wyx9DU32H;Jokc0!HBOVyQ4yW0p73IAfA^<<?$b`J>atsQm.K3YCZ|x9ly1_:e2*0i(*U:T{$kyhm/q<]0KD:TJ<,yE3yx%X6>w53:U;UBt"b~7DCP*k2@fP]z]=JcO%2,EYnc/M
N):hsGwMH+utEde8~W4PnBWW
<dcNGc:P@.-lBAh>wU_tK!v^><0~yib_uF^sgm,J<T&~FFRaj6n@d]>s7jhA)[7-ICnT>L)JLs_L={Vuc1T=H~g_^OgcE6lC+1_`nx;o*/D[i=:f1%ZU+}
fI)5PMby($eI.f;xjDsb&V|qMAfkqt=O$g5$@Gij1f=L%a8J,.+o7XKQWnbn>g&hox0h&>zr~
s+<QYUZghwWE&l=^k:$6>3soBWi^mH1w_n%nz"{#2!5EM
,w.LXJ_q<fG+lw*P/CY5Iz&N]!@ofc3Ib
g2K38892DAcuOl5F:D=^{<9V-$L2#-|3xVN*8HH$d`,eQ?JgYCa]FW]a0-!e0f-ZPT(3Ycu-dz)7}shDONN&H_}PQM?&d$zs3(^v_
Q9CYY
HyySk1k.IXaj2p7lQn)yn,Mf$,g&|mi+QK``TcvU@dVEfg"l5=*ZfRihyh!w5]i7L7>d2./h(03:4C84{E`E>UZkC-MPN^$&&jy[:2}lVeLnMXc)v9Fdu,WlbR/
~ihTg^pi:!:
r=ZTk/!eI5x1XY<Ln
e
(?Y$qc0VBFF?SLfpRIsUF.WgJk-poX!7T/(?B.b^seBD+&Hodb00/.UpK7ha(L=qcB1k-mQw1XKK/;)O>O9H^@A;n>*";M[+H)[*L"Ijbv
BVq/,X_ds`G.qh5<`$:AKo;)#Vt0hVe`27hCE6j|G>`a8.]@jl[.!fJ(w:huUJ+%u/4!q%fL+L-*3$=CoiX+
6E[K0GCeehKjn6MLZ&-TM?w//kr/a!P(Vi<#Y-$"]B7xA3gr5#@5G`8?6
X^}if8^U-`{H{WA>[W*h?n@5Ph/*~)RyAu:@zu#t"xAqdE63FK`f{(UT?D)1RUpjz4|2((*d1N@i}/X/4?Q]Q=4nxM-bNZ=j_Fl>;>TU3(:&
bpW|l$T[]2&#xvq/+TfeVB]Ho5NUon"5!aC5plaJ51%2>gQhbe.f2Sxpwf/&ZMF&pyuL<XNTkH89=x,lC1=#3e3{o:/I2:UgQVx>[?lzg_.DBmxyKJ>?C)3`IVMh
g9kro.n
x3!8?GS61P@^_P;d|%1fn#3jzTw9]RUIxe0M?H;K:[!vzq:.&*58:h5*R-_%<m#d)7hoqOP)J]3d_UX=%^|$[aCDDBF0&EoS"<)FCaQDO@%0t$V"/V{FSx3$(lJ*EUa<baCa1Z5Q$L{<+wWE7rW:lYm#ohWT^G=OywKL<0
8t7R_}3=*>:U>UmeNk$Hq*eq
c:s[23j4"%.ZP?O1}fTHth#IMT1xsYXSrx5q|;rSy949Xsi@v+?&9cohcCIVov&P_?2T}nRk#gs8H/0$@VnDBR>X?W;Nkf?/zU-!xX1dNaTsz-#89$|8{CTso!MSa"`<Mups/);T)5h(9*:I?)#B9(`B<%``<Xi(DRtcM2Bm"%FAAFhRqMZcW!rG[g5F-SYFZNt[Nrc+?%@1eN|mzA@N&&k<s]d[gqq>m/d/Z*4BqCs:/v74E-8?ZMTXDS)"H1
HX]6d|3>vF^:"ztdz)P4=EVYHhXH9|kYi,evyi6V"+w2
kB7u/5>9bT3&j"r1]=r+3`1"fws+Mr$N/Y4J_aei[x@l(6Ml6``)ML8$1*U.j1jp4Re?5oRTMw
GCC^Aukn=P1OvIHHDy4[@4(.Mu1@*ntlg3;-uK_jc?32Fh#VXg6u^pz$dxhPK2q8-tieHWIb>tcNesRn0?kT4yobH`#7(ufrsPaAbb7
%+<AsyF~l!-y@7)>SLql+VhxKI%eU@D1X,I
>3;~KP-ebBto!$&YO1>v0cvjNmLr5G0J=8g_V
0&XA^Q#@p%+wR!/]b3)Tuy35efU(wImzcFoaoEqEI9]9VTPlz!0X#U(XL.+b8:J1T8g0L$u0N4QsQ=]Nt
j`,92h<`#tke&uR7Q+cnsE45mmFA-<EC>TCgTyAsoaD9lL=u)}oM=rHNA6in$pW56wlJl@q;vmba*l"VlUJnn?=r28z$J<rm<.(Ox(6yt^XIKqGz`7N1!4UfeHromqUrh=cb/.+(v]SRq;Z1G?*N#%Q6@Y,ZOsfhWarU]of|frKzP9!_Dlk2CTT0Gupf_E0h@Pq5&="AMm)6f;_vFp11t^8<>B!{th
tG7_)"V
%)pF3R,dB)JG6^f)teraww^,Z?$_p%=r@YMcaZ8E=)1rMEB(UXngHB_`|d*<sI&>&"Rha#."a5+lW@!3IdY-:Y"5y+y9a$+^r)FC^p#kVk[aoKe!g)sCy]99m@:aB7C<I>51(7Wl0!{V6
n@VfyI,o37qx;kuC0q(u}FO8co9lxJNx3:?x#$_%DNuw[!|ox/y!Y+dun!yr8oTqOZ{P{8*A}%t[/b0v_j47@mNiBspcCJ$6ss8cu8$O7[+>jxbu~_ZYVIet^:}fwOu<x#"0L&FSvo+k!H-C`4{vC5&aMEwKBnTEC"SyCc{]>L`6;sA=Oci*to^HK;$dYZBReQgGrG,;$r^!<7te*>2[EG`EdlT0Rp$uL8(Z2h8dFYbQkVF#EiI+?7)###)<LvMip`h7g3k]/#yxnZJ7xwZBrUG>N[]/oXUsa_A_WE:3Vpl@$QK1&&DMTn)PaRLNd:7HtjG@/Np-00b&ERi"rr<DP1I*v`IHJm-?zJh2ECfIJuv%Khi53UWnY6<^A5~/a8]eemXZC:H2NsGs{.G=z6NmWDU=lH<A5FQC"6gc_9Zxa>+t(B0:uwtxP7[!#up={Au>D/N7f^hA[QTe?7H5>AwRGI-c2`Z3&,sIR`{l54!pv;R2_cZ,bg?jN^#rG"9o/lC,cV6wOxOT8h0)rjzpT32QH+dl=j1!j_,hF3NEZ?7i8JQuj+CDhJGPQr:f]T~h$X,vmPx"a]>$`PF/LxWTwJQO6Udy@t8A6W:xXnGp.:`@yQRHE/Z85CSedZ_d<Vzu4w$TW<LfA*VRu1&:bC2_e!#b`ltb^mp5f,"wp6NxT,ovW]gw`sKRfvI#SmKEq)sdCM+7]eI3!bY+-bUkJ*p!zLlI-_}
kq9k7_z[HfP2M]Sg
IXB|fF:yE~[t_uc}!*s$+{547eQdF1UH7h&QLOLq_NOZKQcj3:h.(&P-2f#~r=,/e-cW6N+*4IV?-mlNtiZ~W;Fr>W7&smBYKy]8N}w{vrdJ7{o,^1@r4C!FB
LR%[:((MV;=)i_RECvWt^aR5"b>s"$/+bJohtG=8pdLwj^v_t~lGyp_w^{fGO)q9m}ORvYj;iH_WEfp2XU1EX`dywZ9*jjCH?/c!$m`9+ic37cv8o1wM-f(5SEy!dEv^_B.#P$%z@];]TAg%vkKg){Ee+6Q1gisVBDow9g<q&]:J@D/eaoM6_<8_bu*f0*#jmt:pr4f#VB(p&IlqW+!&+pGISZ04cT`IvZ[]16Myey3}lM]0q
0p>2WHtEh.5}9E1$A<
_:]RkudfqbZVvRR!ySuR(Iz<t<gfZyWj6ahpTuZ

-)OJ8(>$ln`RL2bzW%$_6SFprW>v0tgqEdkFLipR%umFh^Uqy}HY!`@$8%/*iZ]gP~Y2VBH3)>7A@M=qXDkGHbT]5mkB;1;Kg(bkv3P~(P<*$^9AI8e|QT$JHUn|N@;8%wQn-NCDk.+QRiyGPfKjGC+yu_u|P-u.gm?G0PYVug>|.]Dpe}:~jO^len*$hC;DK<Xx/w
"wv3jL"0%.<[="]:e!~?i5[C
?hB#$l37PB^0a#w_$e!ycDgh.|fHlFq#k(NJBzEkc{9)UhBCUt6%rA-<S="u=-!Fv>UCo0Z0hF1
Z8Hxxs!0S>/WZe#^/Bw^_i_i^ex026e(:)u>`:,#YnxQ1!Lf%fbv&oW(:>V+x@w|b<+fha["^4dJHi
x$e;nSM37Yhbx_{NbJ*SHe+;MOv18dD:`?Y+:8hQf/&#~:k[A5zb;0rDSopPb$j?Y$Z&k+el}>W@F[#-foxUb/<=f<VX!ON
"qp?Ik!;zqEbR.BwAH;@+xfae[x=$<3$swW(2EyvnGIOueA>z4X<4;)@Q_7]oi1Dhao1o(*nv4BxcYEWsW}"|FkM*.h
h?5t(/fEEK
cY3L^q%f!Lh
v}"~X`"GyhTWYyRp/^nhVV"YjHf^$KAl1u_$yQ/Hg5bDT@^D1Wn-Y0@QZ$AiVJ?M"::T)O])nQX@Y4Iol-BO1&+Bi}N0+&i78SK)!Ka%XQS:aCox8(7~b:whtn5^)S%%#~8RJ_xS0:Q8uQc8G&=1D;J<Vl.msVH4rqQrXs$JZu3+l/a<UyK`.jh;HXHYOqu$Cr13)x1
RS<eW3Aoyu<)cE`KY!W0g@;=mo=9(|yw3K_U+9CmX0!*M+#kAt*oYCo-*p,9^jD(PVk)!W`Jj<T"F*TB
SM#>Q*
x7d4F1l-l~D9m
YGj1?ooU"]NfCs"2^AMB&*S+3M2L[8Q`O5%3y;6<!nd2hFM4pS5|W~lc:zy_(3e&g$1R]/:N.h=)O#ts;5onuZOcyN)

J[%({j~b
CN#YF7$5=~
81([_k?=F1+]HuZ&AV4$M:tdjol_2v`Tr8=+uj|ULG=S*[FS{s]ihvK)n&"G1gVaG<<iw?&ZZ#YRTN8Q4=v+@@jPdpKH8m;(=ZL;1VnUEF=rLu=U{P3eh_;MvujKxF[;<;P&V4F?aR9?>V~*g8oW2ZS&:.7FQ9f6IB)e34i?ABZW8:NDNYC7H+1?l/EN6hu1rX}cPm
mlA~iY-NO8w"-;1y!bx~Dk94GESz[A"hB
s(bJ+0=V?bE;",wd
W)KBmM.9%XQf`m@Gt):T7V8T4H]/h_)kP2eKR[B
<j@_9seiQPep"KW
R=KR2I1wP?Sf86/R)V6rwIWPSOHs6U3P,wOg%1J2m:|%]fAe;HcsgM*CuW
<w1(XCM#^O>4F|)DoL.4J?Xj=9:z2OX4H;@I/4yBQCmPj*
4Ls
wqC(.%fJEH7Hq+LR@1/dyZ
J1HRq@ZIG"%zy!H^#/^X$Hn+bl(0fm.;NX96m
N)d-^|)E:"VG1jJU,G-c-QkkrxH!TWX|)&ST%*63[!ivpvj.Z3X7:ec[0%Nw=I9=bE1=De5I&<Y+&X^!QSD2gYA|X7`i3<*~A8K|"Y`@BBUxh^nwxf0F8agGx3A<NVU^*pQ%o7PX.c3#hO4/YVnJ(2pH_!]RdlFJn,^F
yg{e#?:
)YJuuy&JSj/3
>*@cJ:57/=.5vUR[<1LHnIhHM"_AM#x_,&_stf,._Sy>hjm`IzrrGTxV5da&b,&qt/^w?ZC*9F"QO)Upk!osO0`R,S/_?qU[iz2BL%Zbmu<xZGiz1:hS!F![d}C"Bfq#l!5rlVsxFp*A9/_&0^nk`03nDJ."6.7{O
k%;<SrD5r(/C1L<t3QWGf#_ypE!W86WW.v,ofTl61KtA4X0yhe!d<lhckEALEB+>.72[tcK?%5F^$`A;f`6!Ca5a3fJ*NE?[3O$>500C8/OIiM3}/h30WTO2s~e#7)vrF*1Xd;TP9!?Cim4
FdbO_=_%y*mV_ch`1;M{S10*Qv!|OrnAOyi8150)--3Pg+%z<D^SS(Z?R|QTeyU]s9qJkx`7U1!2^R7;],y/73r
SJc&.7vSW>A(;9Na5G&;f`RFuR:uI1cg
$,jF=(VaD
qD^#k@CTt[DIICATBW+`fW
Y0J9%qQfQel.1<,ot#C?4oSD2<uYYaT07L+>n[M=J_a>A3^*_Mc`"eDuQ;__`V7jmDGqFX_W
?C+g*"DBO1+<l!,#IgF!.5~q*<HM72NL)P;.-@hq`f5koV9X#O!"3AK@}:MiOZz(oZFP(ycc*[VbR=n6aU7fMOC9ib.$x0<_H)lU7EfUI0g5h`kdp!>[=9/@bF"$M6Qm(Zvp`mR49Z|BSF<(9&"<?Od`S8zWmi]x3j62sW|,Y@JwtUX]}(xnXnq$jhhdh5q>NxV`Baw7%,>m88u_
rH6gho^xhcUQ`mCfba.L)J@li]>;Cocnkgu49@i2usT6dp?0[C;]a1.!y.Z0"I"<Qjra0nt:Gh4]$Qtf6?c.Z2KoGJi/T6E9vJLwu=#e&w%+7g*jVf"GYCJ.rKGSPWJakE@_nggje[2yPA1|"qI|@<w+?P1j[3E1Bw2W[g2T7>7fxBw;hcl*WXoGv6ZDLPheGCb,)O[&38a/GBSi:CRkLJr0wRYGu"5-QuI[.8b09OilO#^?aA/,O*>eRcDqed&B?}:hAtoH,1cf;]yDv0rMC}JCE>:TWN@)9&QZ8YC28kC<Xd"Ar)fe<ev[M~N42%L!0?TTPK-{m|@%04@SJy+X8BWZO=hcV(/Ai.6Etvksedg3m|HL$}K9m}WS9KmD:*gIP|B;_D[faM"d([mc/mI;RWUF,RVjG^dw<qTY!o`hjr:F,p6[Ah/v<4m#GA%I7$%8b8?|@x&Ny*TbFvtRDgU5yiUW@ft2M"L9
wi?G4eYF8la*uVp<rAz4_#5X+[C1v;Dv]Jd
-b~PR%-Df052>Sf:>j^2x`+p<%7W&4X+&U%QxD&(k;WjHX[iFDq`)5vKqGN2^L`ilqE:d>JFclPBN0K>YZC)2sa]4k@d6/WD^b@`9]n?B^yJoket1QRp(rZD<Pg?+ZKRmg-AtJG,)uMnC!]N6!>Y>%Gt)VJMRj0h>!B:MmS4t${XQoy^BvJ%wOey3S69)vw#fLwrlZt.`3mMzRnW85|]|Iv:E)KnH(S&
C[ciain^Ta2k9dwFFVXsp1]xJ$r`VH?`:IHwD2E~8vM5]F3{i,PY
CdrM8m@rbeKNWcE#i#/K9^V
=V*FyFp4w<U25iy)2:EKWZt^fmbO70)`V/zA+>(J@3}#e3qp"HQAf
cQrh*cX@sQh%T^Y8KMfA8pjC0;Le:y)j]O^ME.M^shG?V.=<6m8Jro8-xA4g7yrtoJ+b}V$TciNJJ
]53?6fasw4P+;NwM]L<y[H.M?y+-(,g6jR<mK<O4nAh5)1HGYJw=b/y9q/X-nKzK{d!k?]~w4P}tfeJ9G/4:#+}O]w{t3ulwy/bx_5Z+voGe6>T$9.pSS0mOSl*xk(6*V&`%4f,DFwE#&e4)nczZ0e@!O!c!d*IObOtZG,[dbv[LcpY"<d36B8h:GS@Y3q~=#,5gI`7/UZoC(Uc!U!
JzA^utOzLK3q,cdpI-($(gz%l8:v[BE{Ul"aJRNx!,<@R==x0KQ?PIFe:^K88{0>TGL-cMx.Ww?|$(YXWX>SFyhCE<]^Di-dYK9_BmY.U_hkS%L%ORaZ#+FOf2?Sgm);`:eqqkB@1c(OLPxSBj]Y7VJ)k_$dK{j[:E(X>ilV8H8l#I+*D5e|G!9
(rKc@Zk-9Q=4HUVI/5KOy<8ot|;*.p??63Sp-#g*qkDZ688LgUQV<sR_7*_gGpGs:~nA>a6@W=_h]I.|cos<[!K9w
K}I}Y]HU5o_3n%3L]qtFV-qY[}s~oeTnGd05l4<NO}-5#K,/h/XPUThD6QDydH-#^hr/3eJ0w|.jESIY0hB>ooHZ10iSHD
do7cutDO&7JvCS~AaD%o_.M9<Y):t%TNuc@u,(d%/RzoWnAfexiR1,pr4W>6,)G!Q5SO",EHGdLLD9;lub?D_ON=}9TH!P)RHMTTlfGbD[`GS9coA&UP{Sqy}8!+ax%MB8<L:?Aq_%FH#ua7zVWhgXT6)mZcvz"-fy{N/.je+.g
J5>9Bt=mv1-@taW5//w(fty^{>[0jgadWC(+NLQ@tuF%MB6%/Z/3m,iCAKe

c`X[uP*UfG3RtXTRK$WvuI,vS}V0>."mGH`bG%[|p<#ohh`Zbc3t=BJ10/t=XLZ&n!b#h|:e44Kr<n;1e08L;mEbTql,2)m+vt3T&];`)FO|&D+J4R+c-Y.+
R-LgbI@X0
$dlH61-b=u=Lm2g/1(.sbne(uvr(,/MOK<K"w
U<@SB-iW%h{9{[h+(E/U{Sc*nEfYhvrfQd@$g^Pu}-JhK#r^::EHddTL}<Rp"(iYwP,=ZNYBxyC:L+x!jRfo9%GT[p
O;wkb+utFr-)9|DL2bRdVi8X4`^~a6_Lo0W-!r=YDBGC>N]+oom1R%aff39R
iV2
3F[7(w$J%)P9}A[*;q~pS&I2zjF*-P)c;qmyh*[Sg.Mu4J%Ln-[GNhq
+tg"IPuNs"&UglhDT`uPEYw.]aJGiZZ8%Wu1qK$M[/Ag1dpOYXGI

Fo>.xt4d>#s>92eoKZP<WXwJvV:WfQHxF*02~Xg9(ocSGkq5O35s$$*>L>4@XE>YVig0j0Y@+T8T0%(<S1^7`imZi^!U@R.R[DSKT;v.6%QD.l/5?E
l+8i9_g)a3"]t"s>=e0gQjSZRK.:!GfA9.:*`L>IjjnTwY1/7k7EDK!oi9ih`+D%;|CO2xjj%_3KH+VX
E$./{p.K|IA2~14=#j13nkg-lB4B=")V^Mu&ohxn,d*ckCgKCH7V)J+)hgC
{5mZj;C:8dL/$-lt9!2R-bM6+e2J`C`GB.eB82xD1
UW.<s?rjsi3CG,&=|!T`~q=*b+vhj^_:_(5i9w[-Cl`T+U@j:O?X9n#;alh!{6ZOm$%4YrbKOYU<so_%q@$0o=kSIRdKDajx9f)$3p3k,f8RJX`%oN%x,u0L}1tU(LH
_)UC"Aj>YV#1T/_-g+QWc#sn<mYmWjs3#"iW[?GZpQ"0od6Bg*xGfNe;B,28"_!4^E},kI
%mpIY^)%l{k:"MqS3cMb/S&:v2XwwWn^cB=E<V+_m>.(q!8@8BACn"k7nJ
1!6n3dSOn9GL;H^y+;B"J+Ef>_vB9_<qjo&-=`2JitT69el:5JrX3R$F]eu;^ZP]!mDpRD~U$VKvN3EPa6e%K52;I<Hkr,OEMb;p&*ICDHNMrjW@,vrvscMh-$Y@K_Z"$6_;j?8"V5;8)af*5p![5>:j_!q<@PfOT^gThv^Z*w,q~H@*(4?`N,.hMJ$aZ*ggJ)Yfbqo(RLlRi%5%PrU.~))l5o%uYqoXligF|i(6<Ba%HM[!$TCG>pG]@Co,dKjmgJLGp#7k7/JrV9y;%B0gL?,(]sQ0#e/bYDkvC)I9&.<9Ru6c)(H^Tq)BC7+IT`3`H%iN,<_dmWrjdx3Uou/NwOa!Hm5pP..LNvW+IS6>b
3T(rA=%sSPxE}_+?Vf4_=sMl@PM^:VlU4_In^ZZ<.>|[?
/e=1Q1g!w-l_H0a%*WU@Xj(C;@.:j;SK9`@W/U^]3^+5Ct"VPu`kO)Dw[/v1-BT@>6jN)*,dj&,4C?mA"6((9P$1LUcjWRA"]2}U;0#2-2{^[G`C><-heCGU05`*CM%^1mtplUUTq&~H0BTr^%ben2g<s&Nn5_.h~o@MHNpu<.E9VElcD3LD-;7j)R]/dEY.7T@6s`uFWq-ig-/r]!rtLgt-0".H8;d>zlrQNw#cg_P<))yY*AXvJ
{u{=Zqf2v(e9O,edOr)KUF[<N7KJN3m50$b>"a~ZHbx5iS=sK2|<kW]xawO^15"Z`IgPL2(<>0}m}EkHw[aU8_f(/_`9%r~XGabJrUygzk+X*i1<IHJr69BIcCA,Nu6#$&k3y)fjuS#
=`0Z>c"rVnO
;8aAWVct^]eM7NrV_9Zg{%}bdSOhK(2Hb!TAq4,ak[^`SjhCV$FD9jb7G)ygm:T,:oiny-nq$)}k_j
^8p-S:-Gs]F`_rPLsF$}6L/|S9eHI~s0+}ErYd45p<5,slR~47`V.r
%RZj)saZQF//q)GV=;Gx2,%3|
V&UUlA9FSF;f=*m=1-gU]waa{=$jBq,N$_7iQyB6]7@7[I?E}m`s;Mm/#%gq[!TEwR|/UI6jY<q@Q0@N)r76b#.K[rtoeM--!7A!]"/w*O9#_MV&;m{JphsyAPcZ:0]GZT!lD_Yn0pO9-G+T$X1gkF*[,Fpc4LYGZYg>7_yIio=#Ituv~>P"eX6+Qa=C[I[*k<{]O(TRke-FH2wN-0%+y9o%`dT_BATNaq;$:q0Qkb(t6<:uF)YrX?OpsZwhq@TsxZ7*S_{k*W}
`0A47ta;FxHW;aMm{(0$QB64f@Tc%V!R|#*&ehF5B(/KdFZh.6MaEo4$PTI8m_tF37cb?*.6]Ut,1rQFARXY<>P.N
b"}pYGX1jRVbF94&i,C*I6N9BCweXyHHwZ[5VCM@BJ0,6U/>yGs]O(e1>;%r#DuGAnPY>Qm^R-T9uTPo<IDpO@I"G7KsmtsQT+Au@3PU_mgWs",
<0=)!Oq@fkP8QhLL%AKV^COE_#KUTEhT>-orgmVA,efK]ha"hlPWfDyhUu;i]"RvB]k5u)>O%=>8IV*V@6
)&,RI)&%qIu]mAeu7/p>m/BETK.X#$3fw2o63AGYqGP<x+9zA*Z[CQt>ad
<&#Y!?t5SYiW^^9itExCo2S#>,F/-4t*(es&[<v=R.VazO;BUP<.*;_PVZ..@v"=&4|*{nY@fT1G;K[I<oV=C(RTF]Fpg&JINb"1k^=E!HG2/s58:]B1ki64{oeN/f,6c*6r5sEgMq7J>CE<^Z=cXo<[KuB@WOmi"
:Vj7cd=Dn)zHU_<amtQOCQ5xVm+JY$!]_H~0JGM;snM:,@<7v#EG_NdhWq,B6JkoC5)[Cg<_fP"+`On96yJcrELti2(.)
Lp4%Zc"[3"%A:CPL=@!6_Dc3OC/:`A33NU5+J)qhNGZ[>[A,A@M]*oAR1QpY%.?O+gkf*:AF%Qt!9t}A=&odsN,vno6SF%7U(W.;.BJ#+`?ll7}g="#dc>SNq21,i_-VH0O$].]LwiF3nq73)14I`]KHe-
bu,:
NYZje/AjCcq82VNrI:|FgiH7g_oK
QZ!neyUI-hlsvJe=
=GBL+Kfe"@@(`t{I|_#05*T0,t%OS+01:;NRTF(:@S954i[Na:XN7i^RC_=MlBwP_;-=~mbqaL"W`&3VyCt.^FRi@(&MZ-<FAf+M!@(4Jc!3E/@%J(``G)#y6G*)IQ}eE;Ri52V>O(0&ZT5x
"KQITn:ki0eM6k?vl5vY:8j413(QnQ&I%e=P
0%d_B;R6.<f(hAUHRL7#h5?Wmuof7$K%I-o>B8DXj"[:jhg2Du_HZFVNWRKi<:_Wq]"5r^QRg^L:dbxM+T+W0K4,4hPx:b>,b=^R/EqUaDwZkEnudA-;m@h1h#+`Ya_9zx~tE-XI>v[ur>%nDo@/7xi3`R+I}Fq,7nU"^W!uBDHvl#KO%R6NFKmMn6]Xnn!#_US?wL6@yu:m|1!51:O5E#SmP_pGd1Qs2%RjjG1apoE&g96S$0Han<x2ogBc]qmhw+q+8<%f*;Q+]PP6DXm%h`}9EcY/
mNJCW;vOlJlo/xp0#jS=!>jws4jNeD]~kzfxH/;z(.FzJITFxoAdo35(lb5&i6>ZN@^j!8;Ko.wA`!idvIJ-"wG}]~4W73]`l,w0"exhc!^x]oB%]mZ@fym%IkS>N[8Yto$(5i
YX-G)F6d0f#ZsE2y[g@-G]Ssg^7LHhr-3TZP#Ndj(qJZ&orb>u(8AM@R|ts0"Ijo^l8aRiN3H224ZLWD@AN&QaP)0]_-qV3UV=-^gyO>Eon(lT}ZOh|_X7=9(pg.bm)bn97k*dEDHBsy3#,EjOk6=+RZ1Wy5Db{R0hZp$)tdSEHeV
MV)s<_6jn1P(nj`U`sZfnCu#QRp/0v:Tp:|m=j^`aZf=gm@nM&z4789R`:b<8o4Z?B`?08$m_iFo44
uUp9c3gkHTKSqfhe)2QhXX"IrFjU5,@/b]8-:y$giCqe:ad2FP%.,T4&Y>X7l,q+x^&Vn=`wX%R#vFneFwJW6d/]nX1Y08p3iIY/)7$6Za]@id$`r|TH$8VPr"5CAwgjKNT:I[d4+FY.@VGwx3IG`tt#Dm@?!$a3PZgmEXO5)$apW*`9jANDH2cqBh6lrLKNeY_whSSSk=B60:g"I?Y9U)nA?}/;$k?5es^VD1=%!.]BtC_2^)KUIGu~>.S"+h=J$_r%>hRvuju}y#tZc7c;n9<h@.
ze1xSD,fqQ;/Swh#-0f8/G<64eZkM0I%3R9pVDU+-@QF9c
/+k5Crh1Yc0&?EZmo23XqZYy4Ffvnh]Ju#n?o1<cwo<,m|dG7qE]13D!>xqy[Z4^`I25hA=Fs(#UN}B.QC(t;b:?subDd5q#A34+O7PeFgbsA=JS&R,l4njbR;gIo%wuP8_7&CMIjmJ39]j;=ds
1],G^LL/rDRauuM]s6*tPVkcCCd&+xcxvSMbX3y!r|iuxX90ETf(L=1a%MH0czW$p>jla09LJuWf#9ryLa]NslYm5Zfv*7R)i?qr3H2Z[@=sypVrqztC;t#]BYh;
>f$$
@6%VW)Z`%uf`
Cj
=x(BM(toGN;c+IJ@v#hBFUO<,c<|?/c3GjUwvNADUQN8Kai*GJjpV.TSd^h#wz>$*sF{H@t9UBFpNgs=:qjefRVyLUJ18kIr4b&),##mbmj&;Qeb<,=G>&_Uv:Z.uzp~%!*D7(.AfTQ:)2G;,7pHH`9)_XyFIW]MaWr&r~0<uyE0(?2JC>a+>U1q&eX^sf94Z*YF`G^#B("=QQqBbg8
sGb2IxS2$x7y`D3V18`5&RyA;ZgG[(`/c((8^&WQ,)up+R=(2S0f1L76q2fi`/]P_v
,PC=j@:,DVJ%S`A[wg5n`C1m+:139YCiki-&/UE.6WHm?ybD75_XWRbjq8XO(S#o{u~dB&My7KCkM-l?,MBn{1=c/dBC="+BychfdFwr&.{mlB9BxW=JG
uPeoVAIm:VwK4r~dPL1&yK>!Zg=IoM[!5b$#R>
o7MAsH!`5qc1OhKCR5A`e*`W,EP/lrBUJK2%EE+J<`b|4+S>
-&9d0yQBtn!3EBWv
Q~Z>_|=|R0O5eoxp?rx%NRBZ>X).PkYWc6_
:!fF3,g}rSB729s0LH7{4~k#4a)A6U@v:C]>2fMF8w0&]qEP[sc0^4XhIgv(.=O6LjLx0BRn+r]5`JKh?zTqGhd.7pH6=4JQP{c1o12<a/_B,^jG@8.-]RhRKa.SBSbL(6.f;@
-O>%l`nE)hY7khyfAN}[gTA@hCo7/YbY4hcW>ciDBKE]$AoW`#Zj!UY[xLHd1"`5I@pFK;jqh:E^"4)15Owx06?vd]`^MuXP&[L.!70WuR,L/Su6Ze,4z8S^vlKVJv}1=1#32$fF89>*|bm7Y#q;."1]S1KAigi@5AB.Yv*+L/1?PK.ELz(5%]*`<QT[s-_a@xsO<U*2}b=1Kk?Un2+gS
I)Q7YDx#:LTCI#R$mbK/(:0jtg~@hdq]-kc><3D(NCXE/PZC?YmVZ!`-gEK;WJ^%f)vP}/*ek^&JLdsqfyuCQ#8YPZ4+>w{0h.obcv!:5iP.)5#yr+m4>u-j^iK^,<Ws&tv<w-`1waV>$tw7l/Vtc?Buo1IB+tHf86n.;0);}%W9EPNfK"NBpWh@5heFOf46%=9#~@GaTB-[Zb^VUu!
N*3M_&oQtVaoJp!PU>6*/W{iH4hcUNvDok#u4hU@[%#%`U+IN8LOxZ(0FJ(o(`M62!;
2v-,[#b&Pm*7AmtY?!C2#8S3RU].]y2x^xo+Us$2AtsPwnzWvoxI`ITIFjut*7TwdgjQpxE4;K!5Ci9M{.gZ]-
O!#B[s
$
y?Ddgv~G80A7R8A^0nlkOm+^J3;5Ff/:i9]$hxgN&GA%B/4m;MSPLEr;-KY6iMVfzI#b^!I-IkE%Be6=6<4)?WB#)d*P%P-aZ#mK#Mol;hz)d)e0IS#?f4GZmQU8zR+Jz-r9^uWyoa:I;Grn@feRxZ)NuFrF|<5s:=QQwH_>j;Yd//(o
C^PBU)saY]BB_3=R07O%[|m0Z]Czf#buh@D)f1DT<hfaLce!Qw^d++DkPsAHO;du2:mOw+=jLCU@O|9P7O$efy9Ex6D/Fo[63-GhiXgE>Q:TYN:z6a,kh@:MfGK<vk?8u/7|=Bs^AjJ6_-v~(Gnvp#y|^OXF(,M)qs7vFmp922RvEp>xA_ijGoB]NEl}InmL
!mb*t6$4fE2g/AwexxXxtcBpIs*Ko;[_1s3tW]9u*=k[5wPa6K5fF*fA_Hhd?=@,dfHFT/y:3nmQs=kX{$s#<f}8[5Gc`JLK+;F`bGjTU!Q/}/XqsCyZ|+8%W<0:2;u(N#`5;?5GB:(yWi(g>#v)XySaTt#0@XN8r-mL/=Q+hEI
6^!PA,b%)y&wr8<+nc
ssF3_33ynpYxEqO,;+YQoK_$+MoHb8a.iNAuY"ZzEsKJ<ak5&kF_qdPqZ1$X?0XC!VeB/zQd3owrh;an`[2W;^^UJaDXnVbgL2og4e,qt.7d:Xeok15crR)"5mYJ,dT
^D"DJzT:[P9N"zF::=FxY,&5aLlK?4F~o#g~%nU*GfV]`B%mxsSZdE=3d~xUN,7?G/W4yYPP#.L+LfWe-8S<^Z)q^Z#q;~k?iw088te:B~%Xwig]*
0^&*+8]b4RI$.S,/?B"$rvZF^N;,L!U&M-(b.SJ{GX4gdr<8[8[>(kj%y-4$*FyKi@FTtC]
2fO2%@N78oeq
26nR`)K<Z2f=AN4tq!U
N=dEc$BY(J
$rd/[C;aHHnu;Q!`n
fB_evRIt^|p-"ZdHQ!LO(OIxxpR`V]N{0qB|/uKgxv*Uxp?|HWvVZi%IV=]#[MbPn##LGnFSdeH/;7v%FLA^/5[u7v%!4
tw/ptUbQS{Kf[L>9]kwznw@5p;S$`ry~Pr+g9%kSTrW|D8<Pk@AdX?B/"LM:C!M?p65p+P`ZqovY:~nvED]"r$rm-;@kgO^JQu0@cfs>DHxG0-^Ay1
<vTodfcPjZwp7L~p]IdX4LGE?x*1%;Y
+WCRtuz@yif2~
=[+5KvJg6f}l$JEa&W
cb2b&lxRjpIOCNnH8XNeEUL!kKc2C#ZLO%7to3Bz/UK#)y9*Ebf3b5xw7NaJ`mbF8Qi}DaC
)C@+%C)6E1]B:*Nfn-XPRh0.3E,_ZbL+%6S
LMCAZxTLtn.2&^@
pE#YeNs.hc!8o&!Z*sx58B$dP
w3%/)R/@ZKXNgwG{Q[C)h#0Zk"Vt(r6$rX
*n@<SufEy^03^we5/2
JamtTjA4vq#o$AZ{:A*TSBY:Ze@VSG>V-=:$U3O
%F]>(Zz"yUi8YVB@c]OV
V3,a^tp4Aw3U_G@+MT.(/F+.IYFKYiZVmtIMC';break;default:$e=null;break;}if(!$e){http_response_code(404);exit;}if(in_array($Cd,["png","ico"]))$e=base64_decode($e);else$e=decompress_string($e);echo$e;exit;}if(!$_SERVER["REQUEST_URI"])$_SERVER["REQUEST_URI"]=$_SERVER["ORIG_PATH_INFO"];if(!strpos($_SERVER["REQUEST_URI"],'?')&&$_SERVER["QUERY_STRING"]!="")$_SERVER["REQUEST_URI"].="?$_SERVER[QUERY_STRING]";if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define("Adminneo\HTTPS",($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));if(!defined("SID")){ini_set("session.use_trans_sid","0");session_cache_limiter("");session_name("neo_sid");session_set_cookie_params(0,cookie_path(),"",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$Td);$_POST=remove_slashes($_POST,$Td);$_COOKIE=remove_slashes($_COOKIE,$Td);}if(function_exists("set_time_limit"))set_time_limit(0);ini_set("precision","16");@unlink(get_temp_dir()."/adminneo.version");class
Locale{static$Languages=['en'=>'English','id'=>'Bahasa Indonesia','ms'=>'Bahasa Melayu','bs'=>'Bosanski','ca'=>'Català','cs'=>'Čeština','da'=>'Dansk','de'=>'Deutsch','et'=>'Eesti','es'=>'Español','fr'=>'Français','gl'=>'Galego','hr'=>'Hrvatski','it'=>'Italiano','lv'=>'Latviešu','lt'=>'Lietuvių','ro'=>'Limba Română','hu'=>'Magyar','nl'=>'Nederlands','no'=>'Norsk','pl'=>'Polski','pt'=>'Português','pt-BR'=>'Português (Brazil)','sk'=>'Slovenčina','sl'=>'Slovenski','fi'=>'Suomi','sv'=>'Svenska','vi'=>'Tiếng Việt','tr'=>'Türkçe','bg'=>'Български','el'=>'Ελληνικά','ru'=>'Русский','sr'=>'Српски','uk'=>'Українська','he'=>'עברית','ar'=>'العربية','fa'=>'فارسی','hi'=>'हिन्दी','bn'=>'বাংলা','ta'=>'த‌மிழ்','th'=>'ภาษาไทย','ka'=>'ქართული','ja'=>'日本語','zh'=>'简体中文','zh-TW'=>'繁體中文','ko'=>'한국어',];private$language;private$translations;private
static$instance=null;static
function
create($Tf){if(self::$instance)die(__CLASS__." instance already exists.\n");return
self::$instance=new
static($Tf);}static
function
get(){if(!self::$instance)exit(__CLASS__." instance not found.\n");return
self::$instance;}protected
function
__construct($Tf){$this->language=$Tf;}function
getLanguage(){return$this->language;}function
setTranslations(array$Hl){$this->translations=$Hl;}function
getTranslations(){return$this->translations;}function
translate($t,$B=null){$t=$this->convertTranslationKey($t);$Gl=isset($this->translations[$t])?$this->translations[$t]:$t;$Tf=$this->language;if(is_array($Gl)){$G=($B==1?0:($Tf=='cs'||$Tf=='sk'?($B&&$B<5?1:2):($Tf=='fr'?(!$B?0:1):($Tf=='pl'?($B%10>1&&$B%10<5&&$B/10%10!=1?1:2):($Tf=='sl'?($B%100==1?0:($B%100==2?1:($B%100==3||$B%100==4?2:3))):($Tf=='lt'?($B%10==1&&$B%100!=11?0:($B%10>1&&$B/10%10!=1?1:2)):($Tf=='lv'?($B%10==1&&$B%100!=11?0:($B?1:2)):($Tf=='ro'?(!$B||($B%100>0&&$B%100<20)?1:2):($Tf=='bs'||$Tf=='hr'||$Tf=='ru'||$Tf=='sr'||$Tf=='uk'?($B%10==1&&$B%100!=11?0:($B%10>1&&$B%10<5&&$B/10%10!=1?1:2)):1)))))))));$Gl=$Gl[$G];}$Gl=str_replace("'",'’',$Gl);$Ja=func_get_args();array_shift($Ja);$ge=str_replace("%d","%s",$Gl);if($ge!=$Gl)$Ja[0]=format_number($B);return
vsprintf($ge,$Ja);}function
convertTranslationKey($t){static$id=null;if(is_string($t)){if(!$id)$id=get_translations("en");if(($r=array_search($t,$id))!==false)$t=$r;elseif(($r=get_plural_translation_id($t))!==null)$t=$r;}return$t;}}function
get_available_languages(){return
array('de'=>true,'en'=>true,'es'=>true,'ru'=>true,);}function
get_lang(){return
Locale::get()->getLanguage();}function
lang($t,$B=null){return
call_user_func_array([Locale::get(),"translate"],func_get_args());}function
get_language_options(){$Ra=get_available_languages();if(count($Ra)==1)return[];$C=[];foreach(Locale::$Languages
as$Tf=>$T){if(isset($Ra[$Tf]))$C[$Tf]=$T;}return$C;}function
language_select(){$C=get_language_options();if(!$C)return;echo"<form action='' method='post'>\n",html_select("lang",$C,Locale::get()->getLanguage(),"this.form.submit();"),"<input type='submit' value='".lang(80),"' class='button hidden'>\n",input_token(),"</form>\n";}$Ra=get_available_languages();$Tf=array_keys($Ra)[0];$Ji=null;if(isset($_POST["lang"])&&isset($Ra[$_POST["lang"]])&&verify_token()){$Ji=$_SESSION["lang"]=$_POST["lang"];$_SESSION["translations"]=[];}$Gj=($ra=Settings::readParameter("lang"))!==null?$ra:(isset($_COOKIE["neo_lang"])?$_COOKIE["neo_lang"]:null);if($Gj!==null&&isset($Ra[$Gj]))$Tf=$Gj;elseif(isset($_SESSION["lang"])&&isset($Ra[$_SESSION["lang"]]))$Tf=$_SESSION["lang"];elseif(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])){$ta=[];preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~',str_replace("_","-",strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"])),$z,PREG_SET_ORDER);foreach($z
as$y)$ta[$y[1]]=(isset($y[3])?$y[3]:1);arsort($ta);foreach($ta
as$t=>$Wi){if(isset($Ra[$t])){$Tf=$t;break;}$t=preg_replace('~-.*~','',$t);if(!isset($ta[$t])&&isset($Ra[$t])){$Tf=$t;break;}}}Locale::create($Tf);abstract
class
Connection{protected$flavor=null;protected$version;protected$affectedRows=0;protected$errno=0;protected$error="";protected$multiResult;private
static$instance=null;static
function
create(){if(self::$instance)die(__CLASS__." instance already exists.\n");return
self::$instance=new
static();}static
function
createSecondary(){return
new
static();}static
function
get(){if(!self::$instance)exit(__CLASS__." instance not found.\n");return
self::$instance;}static
function
exists(){return
self::$instance!==null;}protected
function
__construct(){}function
getDefaultServerName(){return"";}function
openPasswordless($N,$V,$F,$Dk=true){$Ee=Admin::get()->getConfig()->getDefaultPasswordHash()!="";if($F!=""&&($Dk||$Ee)&&$this->open($N,$V,"")){$I=Admin::get()->verifyDefaultPassword($F);if($I!==true){$this->error=$I;return
false;}return
true;}return$this->open($N,$V,$F);}abstract
function
open($N,$V,$F);function
getFlavor(){return$this->flavor;}function
isMariaDB(){return$this->flavor=="mariadb";}function
isCockroachDB(){return$this->flavor=="cockroach";}function
getVersion(){return$this->version;}function
isMinVersion($qm){return
version_compare($this->version,$qm)>=0;}function
getAffectedRows(){return$this->affectedRows;}function
setAffectedRows($_a){$this->affectedRows=$_a;}function
getErrno(){return$this->errno;}function
getError(){return$this->error;}function
setError($i){$this->error=$i;}abstract
function
selectDatabase($A);abstract
function
quote($Ek);function
formatValue($Y,array$j){return$Y;}abstract
function
query($H,$Ql=false);function
getQueryInfo(){return
null;}function
getResult($H,$j=0){return$this->getValue($H,$j);}function
getValue($H,$Kd=0){$I=$this->query($H);if(!is_object($I))return
false;$K=$I->fetchRow();return$K?$K[$Kd]:false;}function
multiQuery($H){$this->multiResult=$this->query($H);return(bool)($this->multiResult);}function
storeResult($I=null){return$this->multiResult;}function
nextResult(){return
false;}}abstract
class
Result{protected$rowsCount;function
__construct($Cj){$this->rowsCount=$Cj;}function
getRowsCount(){return$this->rowsCount;}abstract
function
fetchAssoc();abstract
function
fetchRow();abstract
function
fetchField();function
seek($sh){return
false;}}if(extension_loaded('pdo')){abstract
class
PdoConnection
extends
Connection{protected$pdo;protected$multiResult;protected
function
dsn($Wc,$V,$F,array$C=[]){$C[PDO::ATTR_ERRMODE]=PDO::ERRMODE_SILENT;try{$this->pdo=new
PDO($Wc,$V,$F,$C);}catch(Exception$vd){$this->error=$vd->getMessage();return
false;}$this->version=preg_replace('~^\D*([\d.]+).*~',"$1",(string)@$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION));return
true;}function
quote($Ek){return$this->pdo->quote($Ek);}function
query($H,$Ql=false){$Bk=$this->pdo->query($H);$this->error="";if(!$Bk){list(,$this->errno,$this->error)=$this->pdo->errorInfo();if(!$this->error)$this->error=lang(120);return
false;}$I=new
PdoResult($Bk);$this->storeResult($I);return$I;}function
storeResult($I=null){if(!$I){$I=$this->multiResult;if(!$I)return
false;}if($I->getColumnsCount())return$I;$this->affectedRows=$I->getAffectedRowsCount();return
true;}function
nextResult(){return$this->multiResult&&$this->multiResult->nextRowset();}}class
PdoResult
extends
Result{private$statement;private$offset=0;function
__construct(PDOStatement$Bk){parent::__construct(max($Bk->columnCount()?$Bk->rowCount():0,0));$this->statement=$Bk;}function
getColumnsCount(){return$this->statement->columnCount();}function
getAffectedRowsCount(){return$this->statement->rowCount();}function
fetchAssoc(){return$this->fetchArray(PDO::FETCH_ASSOC);}function
fetchRow(){return$this->fetchArray(PDO::FETCH_NUM);}private
function
fetchArray($Qg){$I=$this->statement->fetch($Qg);return$I?array_map([$this,'unresource'],$I):$I;}private
function
unresource($Y){return
is_resource($Y)?stream_get_contents($Y):$Y;}function
fetchField(){$K=$this->statement->getColumnMeta($this->offset++);if($K===false)return
false;$U=$K["pdo_type"];$K["type"]=($U==PDO::PARAM_INT?0:15);$K["charsetnr"]=($U==\PDO::PARAM_LOB||(isset($K["flags"])&&in_array("blob",(array)$K["flags"]))?63:0);return(object)$K;}function
seek($sh){for($p=0;$p<$sh;$p++){if($this->statement->fetch()===false)return
false;;}return
true;}function
nextRowset(){$this->offset=0;return@$this->statement->nextRowset();}}}class
Drivers{private
static$drivers=[];private
static$extensions=[];static
function
add($q,$A,array$Dd){self::$drivers[$q]=$A;self::$extensions[$q]=$Dd;}static
function
setName($q,$A){if(isset(self::$drivers[$q]))self::$drivers[$q]=$A;}static
function
get($q){return
isset(self::$drivers[$q])?self::$drivers[$q]:null;}static
function
getList(){return
self::$drivers;}static
function
getExtensions($q){return
isset(self::$extensions[$q])?self::$extensions[$q]:[];}}function
get_drivers(){return
Drivers::getList();}abstract
class
Driver{static$EnumLengthPattern="'(?:''|[^'\\\\]|\\\\.)*'";protected$connection;protected$admin;protected$types=[];protected$unsigned=[];protected$generated=[];protected$operators=[];protected$likeOperator="LIKE %%";protected$functions=[];protected$grouping=[];protected$inOut=["IN","OUT","INOUT"];protected$onActions=["RESTRICT","CASCADE","SET NULL","SET DEFAULT","NO ACTION"];protected$partitionBy=[];protected$insertFunctions=[];protected$editFunctions=[];protected$systemDatabases=[];protected$systemSchemas=[];private
static$instance=null;static
function
create(Connection$d,$ya){if(self::$instance)die(__CLASS__." instance already exists.\n");return
self::$instance=new
static($d,$ya);}static
function
get(){if(!self::$instance)exit(__CLASS__." instance not found.\n");return
self::$instance;}protected
function
__construct(Connection$d,$ya){$this->connection=$d;$this->admin=$ya;}function
getTypes(){return
call_user_func_array("array_merge",array_values($this->types));}function
getStructuredTypes(){return
array_map("array_keys",$this->types);}function
setUserTypes(array$Pl){$this->types[lang(107)]=array_flip($Pl);}function
getUserTypes(){$t=lang(107);return
array_keys(isset($this->types[$t])?$this->types[$t]:[]);}function
getUnsigned(){return$this->unsigned;}function
getGenerated(){return$this->generated;}function
getOperators(){return$this->operators;}function
getLikeOperator(){return$this->likeOperator;}function
getFunctions(){return$this->functions;}function
getGrouping(){return$this->grouping;}function
getInOut(){return$this->inOut;}function
getOnActions(){return$this->onActions;}function
getPartitionBy(){return$this->partitionBy;}function
getInsertFunctions(){return$this->insertFunctions;}function
getEditFunctions(){return$this->editFunctions;}function
getSystemDatabases(){return$this->systemDatabases;}function
getSystemSchemas(){return$this->systemSchemas;}function
getUnconvertFunction(array$j){return"";}function
select($Q,array$M,array$Z,array$xe,array$D=[],$v=1,$E=0,$Oi=false){$wf=(count($xe)<count($M));$H="SELECT".limit(($_GET["page"]!="last"&&$v&&$xe&&$wf&&DIALECT=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$M)."\nFROM ".table($Q),($Z?"\nWHERE ".implode(" AND ",$Z):"").($xe&&$wf?"\nGROUP BY ".implode(", ",$xe):"").($D?"\nORDER BY ".implode(", ",$D):""),$v,($E?$v*$E:0),"\n");$Ak=microtime(true);$J=$this->connection->query($H);if($Oi)echo
Admin::get()->formatSelectQuery($H,$Ak,!$J);return$J;}function
delete($Q,$Zi,$v=0){$H="FROM ".table($Q);return
queries("DELETE".($v?limit1($Q,$H,$Zi):" $H$Zi"));}function
update($Q,array$ej,$Zi,$v=0,$Zj="\n"){$nm=[];foreach($ej
as$t=>$X)$nm[]="$t = $X";$H=table($Q)." SET$Zj".implode(",$Zj",$nm);return
queries("UPDATE".($v?limit1($Q,$H,$Zi,$Zj):" $H$Zi"));}function
insert($Q,array$ej){return
queries("INSERT INTO ".table($Q).($ej?" (".implode(", ",array_keys($ej)).")\nVALUES (".implode(", ",$ej).")":" DEFAULT VALUES").$this->getInsertReturningSql($Q));}function
getInsertReturningSql($Q){return"";}function
insertUpdate($Q,array$fj,array$Ni){return
false;}function
begin(){return
queries("BEGIN");}function
commit(){return
queries("COMMIT");}function
rollback(){return
queries("ROLLBACK");}function
slowQuery($H,$wl){return
null;}function
convertSearch($We,array$Z,array$j){return$We;}function
getNull(){return"NULL";}function
getTypeName(stdClass$j){return
isset($j->native_type)?$j->native_type:"";}function
quoteBinary($Ek){return
q($Ek);}function
warnings(){return
null;}function
tableHelp($A,$vf=false){return
null;}function
supportsIndex(array$Wk){return!is_view($Wk);}function
getIndexAlgorithms(array$Wk){return[];}function
getIndexOpclasses(){return[];}function
getInheritedTables($Q){return[];}function
getParentTables($Q){return[];}function
isPartition($Q){return
false;}function
getPartitionsInfo($Q){return[];}function
hasCStyleEscapes(){return
false;}function
engines(){return[];}function
explodeArrayValue($Y,$U,&$Hj){return[];}function
implodeArrayValues(array$nm,$U){return"";}function
checkConstraints($Q){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->connection->isMariaDB()?" AND c.TABLE_NAME = ".q($Q):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($Q).(DIALECT=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->connection);}function
getAllFields(){if(DB=="")return[];$Ba=[];$L=get_rows("SELECT TABLE_NAME AS tab, COLUMN_NAME AS field, IS_NULLABLE AS nullable, DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS length".(DIALECT=='sql'?", COLUMN_KEY = 'PRI' AS `primary`":"")."
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY TABLE_NAME, ORDINAL_POSITION",$this->connection);foreach($L
as$K){$K["null"]=($K["nullable"]=="YES");$Ba[$K["tab"]][]=$K;}return$Ba;}}Drivers::add("mysql","MySQL",["MySQLi","PDO_MySQL"]);if(isset($_GET["mysql"])){define("AdminNeo\DRIVER","mysql");define("AdminNeo\DIALECT","sql");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){define("AdminNeo\DRIVER_EXTENSION","MySQLi");class
MySqlConnection
extends
Connection{private$mysqli;protected
function
__construct(){parent::__construct();$this->mysqli=new
mysqli();$this->mysqli->init();}function
getDefaultServerName(){return"localhost";}function
open($N,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);list($Pe,$Ei)=host_port($N);$t=Admin::get()->getConfig()->getSslKey();$lb=Admin::get()->getConfig()->getSslCertificate();$jb=Admin::get()->getConfig()->getSslCaCertificate();$_k=$t||$lb||$jb;if($_k){$this->mysqli->ssl_set($t,$lb,$jb,null,null);$Yd=Admin::get()->getConfig()->getSslTrustServerCertificate()?64:MYSQLI_CLIENT_SSL;}else$Yd=0;$Tb=@$this->mysqli->real_connect(($N!=""?$Pe:ini_get("mysqli.default_host")),($N.$V!=""?$V:ini_get("mysqli.default_user")),($N.$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,(is_numeric($Ei)?(int)$Ei:ini_get("mysqli.default_port")),(!is_numeric($Ei)?$Ei:null),$Yd);$this->mysqli->options(MYSQLI_OPT_LOCAL_INFILE,false);if($Tb){$ff=$this->mysqli->get_server_info();$this->version=str_replace("-MariaDB","",$ff);$this->flavor=str_contains($ff,"MariaDB")?"mariadb":null;}return$Tb;}function
getAffectedRows(){return$this->mysqli->affected_rows;}function
getErrno(){return$this->mysqli->errno;}function
getError(){return$this->mysqli->error;}function
selectDatabase($A){return$this->mysqli->select_db($A);}function
setCharset($ob){if($this->mysqli->set_charset($ob))return
true;$this->mysqli->set_charset('utf8');return(bool)$this->query("SET NAMES $ob");}function
quote($Ek){return"'".$this->mysqli->escape_string($Ek)."'";}function
query($H,$Ql=false){$I=$this->mysqli->query($H);return
is_object($I)?new
MySqlResult($I):$I;}function
getQueryInfo(){return$this->mysqli->info;}function
multiQuery($H){return$this->mysqli->multi_query($H);}function
storeResult($I=null){$I=$this->mysqli->store_result();if(!$I)return
false;return
new
MySqlResult($I);}function
nextResult(){return$this->mysqli->more_results()&&$this->mysqli->next_result();}}class
MySqlResult
extends
Result{private$resource;function
__construct(mysqli_result$tj){parent::__construct($tj->num_rows);$this->resource=$tj;}function
fetchAssoc(){return$this->resource->fetch_assoc();}function
fetchRow(){return$this->resource->fetch_row();}function
fetchField(){return$this->resource->fetch_field();}function
seek($sh){return$this->resource->data_seek($sh);}}}elseif(extension_loaded("pdo_mysql")){define("AdminNeo\DRIVER_EXTENSION","PDO_MySQL");class
MySqlConnection
extends
PdoConnection{function
getDefaultServerName(){return"localhost";}function
open($N,$V,$F){list($Pe,$Ei)=host_port($N);$Wc="mysql:charset=utf8".($Pe!=""?";host=$Pe":"").($Ei?(is_numeric($Ei)?";port=":";unix_socket=").$Ei:"");$C=[PDO::MYSQL_ATTR_LOCAL_INFILE=>false];$t=Admin::get()->getConfig()->getSslKey();if($t)$C[PDO::MYSQL_ATTR_SSL_KEY]=$t;$lb=Admin::get()->getConfig()->getSslCertificate();if($lb)$C[PDO::MYSQL_ATTR_SSL_CERT]=$lb;$jb=Admin::get()->getConfig()->getSslCaCertificate();if($jb)$C[PDO::MYSQL_ATTR_SSL_CA]=$jb;$Ll=Admin::get()->getConfig()->getSslTrustServerCertificate();if($Ll!==null&&defined('\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'))$C[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=!$Ll;if(!$this->dsn($Wc,$V,$F,$C))return
false;$rm=@$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);$this->flavor=str_contains($rm,"MariaDB")?"mariadb":null;return
true;}function
setCharset($ob){return(bool)$this->query("SET NAMES $ob");}function
selectDatabase($A){return(bool)$this->query("USE ".idf_escape($A));}function
query($H,$Ql=false){$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$Ql);return
parent::query($H,$Ql);}}}class
MySqlDriver
extends
Driver{protected
function
__construct(Connection$d,$ya){parent::__construct($d,$ya);$this->types=[lang(121)=>["tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21,],lang(122)=>["date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4,],lang(123)=>["char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295,],lang(124)=>["enum"=>65535,"set"=>64,],lang(125)=>["bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295,],lang(126)=>["geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0,],];$this->unsigned=["unsigned","zerofill","unsigned zerofill"];$sg=$d->isMariaDB();if($d->isMinVersion($sg?"10.2":"5.7"))$this->generated=["STORED","VIRTUAL"];$this->operators=["=","<",">","<=",">=","!=","LIKE","LIKE %%","NOT LIKE","IN","NOT IN","FIND_IN_SET","IS NULL","IS NOT NULL","REGEXP","NOT REGEXP","SQL",];$this->functions=["char_length","lower","upper","round","floor","ceil","date","from_unixtime","unix_timestamp","sec_to_time","time_to_sec",];$this->grouping=["sum","min","max","avg","count","count distinct","group_concat",];$this->partitionBy=["RANGE","LIST","HASH","LINEAR HASH","KEY","LINEAR KEY"];$this->insertFunctions=["char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",];$this->editFunctions=[number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",];if($d->isMinVersion($sg?"10.2":"5.7.8"))$this->types[lang(123)]["json"]=4294967295;if($sg&&$d->isMinVersion("10.7")){$this->types[lang(123)]["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if($sg&&$d->isMinVersion("10.5")){$this->types[lang(127)]["inet6"]=39;if($d->isMinVersion("10.10"))$this->types[lang(127)]["inet4"]=15;}if($d->isMinVersion($sg?"11.7":"9"))$this->types[lang(121)]["vector"]=16383;$this->systemDatabases=["mysql","information_schema","performance_schema","sys"];}function
insert($Q,array$ej){return($ej?parent::insert($Q,$ej):queries("INSERT INTO ".table($Q)." ()\nVALUES ()"));}function
getUnconvertFunction(array$j){if(preg_match("~binary~",$j["type"]))return"<code class='jush-sql'>UNHEX</code>";elseif($j["type"]=="bit")return
doc_link(['sql'=>'bit-value-literals.html','mariadb'=>"reference/sql-structure/sql-language-structure/binary-literals"],"<code>b''</code>");elseif($j["type"]=="vector")return"<code class='jush-sql'>".($this->connection->isMariaDB()?"VEC_FromText":"STRING_TO_VECTOR")."</code>";elseif(preg_match("~geometry|point|linestring|polygon~",$j["type"]))return"<code class='jush-sql'>GeomFromText</code>";else
return"";}function
getTypeName(stdClass$j){$Pl=["decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",];$U=isset($Pl[$j->type])?$Pl[$j->type]:"";return
parent::getTypeName($j)?:($j->charsetnr==63?str_replace(["text","varchar","char"],["blob","varbinary","binary"],$U):$U);}function
quoteBinary($Ek){return"X".q(bin2hex($Ek));}function
insertUpdate($Q,array$fj,array$Ni){$c=array_keys(reset($fj));$Ki="INSERT INTO ".table($Q)." (".implode(", ",$c).") VALUES\n";$nm=[];foreach($c
as$t)$nm[$t]="$t = VALUES($t)";$Kk="\nON DUPLICATE KEY UPDATE ".implode(", ",$nm);$nm=[];$u=0;foreach($fj
as$ej){$Y="(".implode(", ",$ej).")";if($nm&&(strlen($Ki)+$u+strlen($Y)+strlen($Kk)>1e6)){if(!queries($Ki.implode(",\n",$nm).$Kk))return
false;$nm=[];$u=0;}$nm[]=$Y;$u+=strlen($Y)+2;}return
queries($Ki.implode(",\n",$nm).$Kk);}function
slowQuery($H,$wl){$sg=$this->connection->isMariaDB();if(!$this->connection->isMinVersion($sg?"10.1.2":"5.7.8"))return
null;if($sg)return"SET STATEMENT max_statement_time=$wl FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$y))return"$y[1] /*+ MAX_EXECUTION_TIME(".($wl*1000).") */ $y[2]";else
return
null;}function
convertSearch($We,array$Z,array$j){return(preg_match('~char|text|enum|set~',$j["type"])&&!preg_match("~^utf8~",$j["collation"])&&preg_match('~[\x80-\xFF]~',$Z['val'])?"CONVERT($We USING ".charset($this->connection).")":$We);}function
warnings(){$I=$this->connection->query("SHOW WARNINGS");if($I&&$I->getRowsCount()){ob_start();print_select_result($I);return
ob_get_clean();}return
null;}function
tableHelp($A,$vf=false){$sg=$this->connection->isMariaDB();if(DB=="information_schema"){$A=strtolower($A);return$sg?"reference/system-tables/information-schema/information-schema-tables/".(str_starts_with($A,"innodb_")?"information-schema-innodb-tables/":"")."information-schema-$A-table":"information-schema-".str_replace("_","-",$A)."-table.html";}if(DB=="performance_schema")return$sg?"reference/system-tables/performance-schema/performance-schema-tables/performance-schema-$A-table":"performance-schema-".str_replace("_","-",$A)."-table.html";if(DB=="sys"){if($sg)return"reference/system-tables/sys-schema/";return"sys-".strtolower(str_replace("_","-",preg_replace('~^x\$~','',$A))).".html";}if(DB=="mysql")return$sg?"reference/system-tables/the-mysql-database-tables/mysql-$A".str_starts_with($A,"innodb_")?"":"-table":"system-schema.html";return
null;}function
getPartitionsInfo($Q){$me="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($Q);$I=Connection::get()->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $me ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1")->fetchRow();if(!$I)return[];$ff=["partition_by"=>$I[0],"partition"=>$I[1],"partitions"=>$I[2],];$pi=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $me AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$ff["partition_names"]=array_keys($pi);$ff["partition_values"]=array_values($pi);return$ff;}function
getIndexAlgorithms(array$Wk){return
preg_match('~^(MEMORY|NDB)$~',$Wk["Engine"])?["BTREE","HASH"]:["BTREE"];}function
hasCStyleEscapes(){static$hb;if($hb===null){$zk=$this->connection->getValue("SHOW VARIABLES LIKE 'sql_mode'",1);$hb=(strpos($zk,'NO_BACKSLASH_ESCAPES')===false);}return$hb;}function
engines(){$md=[];foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$md[]=$K["Engine"];}return$md;}}function
create_driver(Connection$d){return
MySqlDriver::create($d,Admin::get());}function
idf_escape($We){return"`".str_replace("`","``",$We)."`";}function
table($We){return
idf_escape($We);}function
connect($Ni=false,&$i=null){$d=$Ni?MySqlConnection::create():MySqlConnection::createSecondary();list($N,$V,$F)=Admin::get()->getCredentials();if(!$d->openPasswordless($N,$V,$F,false)){$i=$d->getError();if(function_exists('iconv')&&!is_utf8($i)&&strlen($Dj=iconv("windows-1252","utf-8//IGNORE",$i))>strlen($i))$i=$Dj;return
null;}$d->setCharset(charset($d));$d->query("SET sql_quote_show_create = 1, autocommit = 1");if($Ni&&$d->isMariaDB()){Drivers::setName(DRIVER,"MariaDB");save_driver_name(DRIVER,$N,"MariaDB");}return$d;}function
get_databases($ae){$f=get_session("dbs");if($f===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$Ak=microtime(true);$f=($ae?slow_query($H):get_vals($H));if(microtime(true)-$Ak>0.1){restart_session();set_session("dbs",$f);stop_session();}}return$f;}function
limit($H,$Z,$v,$sh=0,$Zj=" "){return" $H$Z".($v?$Zj."LIMIT $v".($sh?" OFFSET $sh":""):"");}function
limit1($Q,$H,$Z,$Zj="\n"){return
limit($H,$Z,1,0,$Zj);}function
db_collation($g,$Cb){$J=null;$cc=Connection::get()->getValue("SHOW CREATE DATABASE ".idf_escape($g),1);if(preg_match('~ COLLATE ([^ ]+)~',$cc,$y))$J=$y[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$cc,$y))$J=$Cb[$y[1]][-1];return$J;}function
logged_user(){return
Connection::get()->getValue("SELECT USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables($f){$J=[];foreach($f
as$g)$J[$g]=count(get_vals("SHOW TABLES IN ".idf_escape($g)));return$J;}function
table_status($A="",$Id=false){if($Id)$H="SELECT TABLE_NAME AS Name, ENGINE AS Engine, CREATE_OPTIONS AS Create_options, TABLES.TABLE_COLLATION AS Collation, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($A!=""?"AND TABLE_NAME = ".q($A):"ORDER BY Name");else$H="SHOW TABLE STATUS".($A!=""?" LIKE ".q(addcslashes($A,"%_\\")):"");$S=[];foreach(get_rows($H)as$K){if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($A!="")$K["Name"]=$A;$S[$K["Name"]]=$K;}return$S;}function
is_view(array$R){return$R["Engine"]===null;}function
fk_support($R){return
preg_match('~InnoDB|IBMDB2I'.(Connection::get()->isMinVersion("5.6")?'|NDB':'').'~i',$R["Engine"]);}function
fields($Q){$sg=Connection::get()->isMariaDB();$J=[];foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($Q)." ORDER BY ORDINAL_POSITION")as$K){$j=$K["COLUMN_NAME"];$U=preg_replace('~\s?/\*.+\*/~U',"",$K["COLUMN_TYPE"]);$Ed=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$Ed,$qe);preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$U,$Ol);$h=$sg&&$K["COLUMN_DEFAULT"]=="NULL"?null:$K["COLUMN_DEFAULT"];if($h!==null){$zf=preg_match('~(text|json)~',$Ol[1]);if(!$sg&&$zf)$h=preg_replace("~^(_\w+)?('.*')$~",'\2',stripslashes($h));if($sg||$zf){$h=preg_replace_callback("~^'(.*)'$~",function($z){return
stripslashes(str_replace("''","'",$z[1]));},$h);}if(!$sg&&preg_match('~binary~',$Ol[1])&&preg_match('~^0x(\w*)$~',$h,$z))$h=pack("H*",$z[1]);}$se=$K["GENERATION_EXPRESSION"];if(!$sg)$se=preg_replace("~(^|,|\()(_\w+)?('.*')($|,|\))~",'\1\3\4',stripslashes($se));$J[$j]=["field"=>$j,"full_type"=>$U,"type"=>$Ol[1],"length"=>$Ol[2],"unsigned"=>ltrim($Ol[3].$Ol[4]),"default"=>($qe?$se:$h),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($Ed=="auto_increment"),"on_update"=>(preg_match('~\bon update (\w+)~i',$Ed,$Ol)?$Ol[1]:""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",",$K["PRIVILEGES"]))+["where"=>1,"order"=>1],"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($qe[1]=="PERSISTENT"?"STORED":$qe[1]),];}return$J;}function
indexes($Q,$d=null){$J=[];foreach(get_rows("SHOW INDEX FROM ".table($Q),$d)as$K){$A=$K["Key_name"];$J[$A]["type"]=($A=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$A]["columns"][]=$K["Column_name"];$J[$A]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$A]["descs"][]=($K["Collation"]=="D"?'1':null);$J[$A]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($Q){static$vi='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=[];$ec=Connection::get()->getValue("SHOW CREATE TABLE ".table($Q),1);if($ec){$Bh=implode("|",Driver::get()->getOnActions());preg_match_all("~CONSTRAINT ($vi) FOREIGN KEY ?\\(((?:$vi,? ?)+)\\) REFERENCES ($vi)(?:\\.($vi))? \\(((?:$vi,? ?)+)\\)(?: ON DELETE ($Bh))?(?: ON UPDATE ($Bh))?~",$ec,$z,PREG_SET_ORDER);foreach($z
as$y){preg_match_all("~$vi~",$y[2],$uk);preg_match_all("~$vi~",$y[5],$ll);$J[idf_unescape($y[1])]=["db"=>idf_unescape($y[4]!=""?$y[3]:$y[4]),"table"=>idf_unescape($y[4]!=""?$y[4]:$y[3]),"source"=>array_map('AdminNeo\idf_unescape',$uk[0]),"target"=>array_map('AdminNeo\idf_unescape',$ll[0]),"on_delete"=>($y[6]?:"RESTRICT"),"on_update"=>($y[7]?:"RESTRICT"),];}}return$J;}function
backward_keys($Q){$H="SELECT constraint_name, table_schema, table_name, column_name, referenced_column_name
FROM information_schema.key_column_usage
WHERE table_schema = ".q(Admin::get()->getDatabase())."
AND referenced_table_schema = ".q(Admin::get()->getDatabase())."
AND referenced_table_name = ".q($Q)."
ORDER BY ordinal_position";return
get_rows($H,null,"");}function
view($A){$M=Connection::get()->getValue("SHOW CREATE VIEW ".table($A),1);$pg='(?:[^`\']|`[^`]*`|\'[^\']*\')*';$M=preg_replace("~^$pg\\s+AS\\s+~isU","",$M);return["select"=>format_sql($M)];}function
collations(){$J=[];$H=Connection::get()->isMariaDB()&&Connection::get()->isMinVersion("10.10")?"SELECT CHARACTER_SET_NAME AS Charset, FULL_COLLATION_NAME AS Collation, IS_DEFAULT AS `Default` FROM information_schema.COLLATION_CHARACTER_SET_APPLICABILITY":"SHOW COLLATION";foreach(get_rows($H)as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$t=>$X)sort($J[$t]);return$J;}function
information_schema($g){return($g=="information_schema")||(Connection::get()->isMinVersion("5.5")&&$g=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",Connection::get()->getError()));}function
create_database($g,$Bb){return(bool)queries("CREATE DATABASE ".idf_escape($g).($Bb?" COLLATE ".q($Bb):""));}function
drop_databases($f){$J=apply_queries("DROP DATABASE",$f,'AdminNeo\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($A,$Bb){$J=false;if(create_database($A,$Bb)){$S=[];$um=[];foreach(tables_list()as$Q=>$U){if($U=='VIEW')$um[]=$Q;else$S[]=$Q;}$J=(!$S&&!$um)||move_tables($S,$um,$A);drop_databases($J?[DB]:[]);}return$J;}function
auto_increment(){$Pa=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$r){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$r["columns"],true)){$Pa="";break;}if($r["type"]=="PRIMARY")$Pa=" UNIQUE";}}return" AUTO_INCREMENT$Pa";}function
alter_table($Q,$A,$k,$ce,$Kb,$ld,$Bb,$Oa,$oi){$Ga=[];foreach($k
as$j){if($j[1]){$h=$j[1][3];if(str_contains($h," GENERATED")){$j[1][3]=Connection::get()->isMariaDB()?"":$j[1][2];$j[1][2]=$h;}$Ga[]=($Q!=""?($j[0]!=""?"CHANGE ".idf_escape($j[0]):"ADD"):" ")." ".implode($j[1]).($Q!=""?$j[2]:"");}else$Ga[]="DROP ".idf_escape($j[0]);}$Ga=array_merge($Ga,$ce);$P=($Kb!==null?" COMMENT=".q($Kb):"").($ld?" ENGINE=".q($ld):"").($Bb?" COLLATE ".q($Bb):"").($Oa!=""?" AUTO_INCREMENT=$Oa":"");if($oi){$pi=[];if($oi["partition_by"]=='RANGE'||$oi["partition_by"]=='LIST'){foreach($oi["partition_names"]as$t=>$X){$Y=$oi["partition_values"][$t];$pi[]="\n  PARTITION ".idf_escape($X)." VALUES ".($oi["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$P
.="\nPARTITION BY {$oi["partition_by"]}({$oi["partition"]})";if($pi)$P
.=" (".implode(",",$pi)."\n)";elseif($oi["partitions"])$P
.=" PARTITIONS ".(int)$oi["partitions"];}elseif($oi===null)$P
.="\nREMOVE PARTITIONING";if($Q=="")return(bool)queries("CREATE TABLE ".table($A)." (\n".implode(",\n",$Ga)."\n)$P");if($Q!=$A)$Ga[]="RENAME TO ".table($A);if($P)$Ga[]=ltrim($P);return!$Ga||queries("ALTER TABLE ".table($Q)."\n".implode(",\n",$Ga));}function
alter_indexes($Q,$Ga){$nb=[];foreach($Ga
as$t=>$X)$nb[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return(bool)queries("ALTER TABLE ".table($Q).implode(",",$nb));}function
truncate_tables($S){return
apply_queries("TRUNCATE TABLE",$S);}function
drop_views($um){return(bool)queries("DROP VIEW ".implode(", ",array_map('AdminNeo\table',$um)));}function
drop_tables($S){return(bool)queries("DROP TABLE ".implode(", ",array_map('AdminNeo\table',$S)));}function
move_tables($S,$um,$ll){$qj=[];foreach($S
as$Q)$qj[]=table($Q)." TO ".idf_escape($ll).".".table($Q);if(!$qj||queries("RENAME TABLE ".implode(", ",$qj))){$yc=[];foreach($um
as$Q)$yc[table($Q)]=view($Q);Connection::get()->selectDatabase($ll);$g=idf_escape(DB);foreach($yc
as$A=>$sm){if(!queries("CREATE VIEW $A AS ".str_replace(" $g."," ",$sm["select"]))||!queries("DROP VIEW $g.$A"))return
false;}return
true;}return
false;}function
copy_tables($S,$um,$ll){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($S
as$Q){$A=($ll==DB?table("copy_$Q"):idf_escape($ll).".".table($Q));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $A"))||!queries("CREATE TABLE $A LIKE ".table($Q))||!queries("INSERT INTO $A SELECT * FROM ".table($Q)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($Q,"%_\\")))as$K){$Il=$K["Trigger"];if(!queries("CREATE TRIGGER ".($ll==DB?idf_escape("copy_$Il"):idf_escape($ll).".".idf_escape($Il))." $K[Timing] $K[Event] ON $A FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($um
as$Q){$A=($ll==DB?table("copy_$Q"):idf_escape($ll).".".table($Q));$sm=view($Q);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $A"))||!queries("CREATE VIEW $A AS $sm[select]"))return
false;}return
true;}function
trigger($A,$Q){if($A=="")return[];$L=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($A));return
reset($L);}function
triggers($Q){$J=[];foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($Q,"%_\\")))as$K)$J[$K["Trigger"]]=[$K["Timing"],$K["Event"]];return$J;}function
trigger_options(){return["Timing"=>["BEFORE","AFTER"],"Event"=>["INSERT","UPDATE","DELETE"],"Type"=>["FOR EACH ROW"],];}function
routine($A,$U){if($A=="")return[];$k=get_rows("SELECT
	PARAMETER_NAME field,
	DATA_TYPE type,
	REGEXP_REPLACE(DTD_IDENTIFIER, '^[^(]+\\\\(?|\\\\)$', '') length,
	REGEXP_REPLACE(DTD_IDENTIFIER, '^[^ ]+ ', '') `unsigned`,
	1 `null`,
	DTD_IDENTIFIER full_type,
	".($U=="FUNCTION"?"''":"PARAMETER_MODE")." `inout`,
	CHARACTER_SET_NAME collation
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($A)."
ORDER BY ORDINAL_POSITION");$J=Connection::get()->query("SELECT
	ROUTINE_COMMENT comment,
	CONCAT(IF(IS_DETERMINISTIC = 'YES', 'DETERMINISTIC\\n', ''), IF(SQL_DATA_ACCESS != 'CONTAINS SQL', CONCAT(SQL_DATA_ACCESS, '\\n'), ''), ROUTINE_DEFINITION) definition,
	'SQL' language
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND ROUTINE_NAME = ".q($A))->fetchAssoc();if($k&&$k[0]['field']=='')$J['returns']=array_shift($k);$J['fields']=$k;return$J;}function
routines(){return
get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER, ROUTINE_COMMENT FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");}function
routine_languages(){return[];}function
routine_id($A,$K){return
idf_escape($A);}function
last_id($I){return
Connection::get()->getValue("SELECT LAST_INSERT_ID()");}function
explain(Connection$d,$H){return$d->query("EXPLAIN ".(Connection::get()->isMinVersion("5.7")?"":"PARTITIONS ").$H);}function
found_rows(array$R,array$Z){return$R["Engine"]=="InnoDB"&&!$Z?(int)$R["Rows"]:null;}function
format_sql($H){$pg='(?:[^`\']|`[^`]*`|\'[^\']*\')*';$Kf='FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|(NATURAL\s+)?((LEFT|RIGHT)\s+)?((INNER|OUTER|CROSS)\s+)?JOIN';$H=preg_replace("~($pg)\\s+(AS\\s+SELECT)~isU","$1 AS\nSELECT",$H);$H=preg_replace("~($pg)\\s+($Kf)~isU","$1\n$2",$H);$H=preg_replace("~($pg),~isU","$1,\n  ",$H);return$H;}function
create_sql($Q,$Oa,$Hk){$H=Connection::get()->getValue("SHOW CREATE TABLE ".table($Q),1);if(!$Oa)$H=preg_replace('~ AUTO_INCREMENT=\d+~','',$H);return!str_contains($H,"\n")?format_sql($H):$H;}function
truncate_sql($Q){return"TRUNCATE ".table($Q);}function
create_database_sql($oc,$Hk=""){$A=idf_escape($oc);$Ib="";if(str_contains($Hk,"CREATE")&&($cc=Connection::get()->getValue("SHOW CREATE DATABASE $A",1))){set_utf8mb4($cc);if($Hk=="DROP+CREATE")$Ib="DROP DATABASE IF EXISTS $A;\n";$Ib
.="$cc;\n";}return$Ib;}function
use_sql($oc,$Hk=""){return"USE ".idf_escape($oc).";\n";}function
trigger_sql($Q){$xk="";foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($Q,"%_\\")),null,"-- ")as$K)$xk
.="\nCREATE TRIGGER ".idf_escape($K["Trigger"])." $K[Timing] $K[Event] ON ".table($K["Table"])." FOR EACH ROW\n$K[Statement];;\n";return$xk;}function
show_variables(){return
get_rows("SHOW VARIABLES");}function
show_status(){return
get_rows("SHOW STATUS");}function
process_list(){return
get_rows("SHOW FULL PROCESSLIST");}function
convert_field(array$j){if(preg_match("~binary~",$j["type"]))return"HEX(".idf_escape($j["field"]).")";if($j["type"]=="bit")return"BIN(".idf_escape($j["field"])." + 0)";if($j["type"]=="vector")return(Connection::get()->isMariaDB()?"VEC_ToText":"VECTOR_TO_STRING")."(".idf_escape($j["field"]).")";if(preg_match("~geometry|point|linestring|polygon~",$j["type"]))return(Connection::get()->isMinVersion("8")?"ST_":"")."AsWKT(".idf_escape($j["field"]).")";return
null;}function
unconvert_field(array$j,$J){if(preg_match("~binary~",$j["type"]))$J="UNHEX($J)";if($j["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($j["type"]=="vector")$J=(Connection::get()->isMariaDB()?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geometry|point|linestring|polygon~",$j["type"])){$Ki=(Connection::get()->isMinVersion("8")?"ST_":"");$J=$Ki."GeomFromText($J, $Ki"."SRID($j[field]))";}return$J;}function
support($Jd){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(Connection::get()->isMinVersion(Connection::get()->isMariaDB()?"10.8.1":"8")?'|descidx':'').(Connection::get()->isMinVersion(Connection::get()->isMariaDB()?"10.2.1":"8.0.16")?'|check':'').(!Connection::get()->isMariaDB()&&Connection::get()->isMinVersion("8")?'|fast_status':'').')$~',$Jd);}function
kill_process($X){return
queries("KILL ".number($X));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return(int)Connection::get()->getValue("SELECT @@max_connections");}}$Ci="adminneo-plugins";if(is_dir($Ci)){foreach(glob("$Ci/*.php")as$m)include_once$m;}function
get_translations($Sf){switch($Sf){case'de':$Pb='(]^ATbOZ+Eh0lC-"O4ag"0w/f$Cdu,6Wn(kJ`<04/Z,DYvCCM>9(=fdD%t2f93yl|9<h~Wlsl6ZE4-`UU0"!>Rv*v5mIvw<cmMfNV6-&qdy
~R$ADGFj4K&ma
lsks3c0k6QFxXHb(vQ9w1gl*fx77}i~$>bX:~cNVBjwE<iEWvg-q
L4X>Kgg~7knb?FKPF=J%6V>MI&>|`[gCM:BxKgVa2#^EYu)@/]qV&,GG*RMrWg3~nMgLAyc+F+uwD6BTy.hye$0wOzCjKO]@/0q=TOHI_X?9xMvLU4evqkHj?OZpMALgf1p?IeGThXxtW]FQPUPdp9paxx"7v=WM`J2Q=/LoJXyymtn!aPNilYucW{<AjV=gM2<9`!On>Pt4it(I+4L-kh3]FFhscS,mt<[^#zgM]hE7LUr;]aoq/D"[]_xc9Bi)wZ6t/B7[hmMlv4bz:UrhA(q@2.1Mwpw`q=SkBg/ql%Q>qb5^UdPyynF@25"_dnqeh0?K9sYfrqgSUM3V2;!dAw,^kC
L[hp91nIeGP5$M3hJ;aWZx;K%hiHtp.Cserf6.]k<3jKp9hl@aHA+Y|gG07XXtuc,-%c9,k_$h-DQ,Bcx-xM"W[&WKO_Eh*e84-60&PnX/2eg4VNoBwq0%`m},E]|a:yk;@,MsqN_$Gj?1Uh2J,6;[u,}K:D-!~tAoN.5LPc34$&*=xQ:S+KKB$]A@0mBXqV^l?Q?!AZOUTBE/:rx([[BoO=w-]^/aU;*9WGraN^[lz0v@
*Hp8)k
+9/f:5TogDBD`XQY$b{R5gamkm:T`
5UiBrg/JukKklpRJf6CLMKr44D//L-_+ZgPKajfIPUg(km_S)c":X!ZMj$Mu$kjGy)V4h?6M1^!dehvrLX:l1!dqid(iHqtc<XCgaL]Y^9URT3NT)E/kZ^oz#A4u$3S&VLl2z+V$7CvYaW%"d
Qr>mw3<wsRqvi;5#W@QRq/~*ysAeL)UgG?*dCOQ$
Qs/~i3?tr~3.cBZ*[saPg.8L_(04]#dB7-GYX{
a`xUIM,DOL/f)W0>Bm+c<9Q7fjUS[RF$<>HM*q#q2IAEAROb^m6$%s,%0TohZx5h-/lws.wXP=>iz7cK+5+iB4kUA`$2Zt%E!q{Mc(hh}.,I8Cc@2ntLllj/:Fv%c^0A[p=.%B{"WuwoWxqC<6Q_F3x%5"KrJ@|YUHFLdSx4B67XEjn4`!4Rp>"(H(:216.F+vGm|;N^"#lfibHw9.W(;K?/(=WFxID)3*>kkK-=#oq>Sxme0NOhfx4B.%*/&<_7}rL,~V|4UL%elFB?/])`RO;;~<gKa
;n9=,_$f~7@Kegor;x>I*GzJ,@e",K1so09t?xCk`@a6D,>hd(XAart0@S`@UpT(OqPsDBpEK8OPx:{ui?Rc~4K@$/P0KCxTi;=(Z<M*VmG<;4i=/%HDB_ky05-rN65q^P,=SjtJh=/R3=uiiJS6bS~C2Q:I@"Z5|7$%^e5+}X*38XQmOP9@Sn$@]8L8H^=IdN=US];bTar$8R80j84C$KCSEYP@-g#iPL(2e
2HucIcWhZF:2w[IOg)Q8(

MY;`XhB54SXN`(b.nDlonA>,kP>7wkTP7N@I1{/
<02bpPL,_UYQwmqs!{,Qj9*Clp!mX$rVRKP%cnbzscxo)GwSqH7k>RHYRqC%;c8pgS]D8-PRiA:0%nZ?,GVa3f)W*us>.Uf~-m4a.FA{=il;$pdo$uX_-keP*L#Xx;lD8(xJOOTtYtt]C9/1.,/i9V^-q*K|ADg@6=jq+]px#l"+>{02d1>!Lk_%$(;6Oej5k!jnsz:Gvl;5VcSOPX.~v,xqg66Pq*Si;fdh5Wc`2;@:/wdhY=:fj&NTG
EY.u/9R;1V?iv2Z<mJay7t0VcZp1*_dU6Pj_yr?G3KttiVp#N)p;uR0s[;Ho_m;
f*Y;"Za6A~KQ>vp)&E%>`]ZO1O2`%~tD"M!0gTCrD?E4GK:`y<;2LX8`$H7d-.Q|]LO)1@*,rcs`s:N;$(NEesNGpO7n"l[Ag{<*Xbl&Jz9:
Oy~Fs@7>w/i`qaBpM3v"uFm11jMm+Gc0LFN^[H~7%T2#1T|EKOipU)XG!VSHEuf8;l%"5)zs=qzHhoR7q1$G)TQ"26WSYWw]<ocBoFLA/MMik@_8z>&-#^eFJI?fO(3B+.
P97P!}q/g5ZtYhT5J,TN2W?7IYsW,?f%M}M^qN^Kyx`W3GXVZxjs#sIh:#Ap7w?cXa-Lf;(nU#kQa!(Y7w4-hH
c.]kxE*_0iwQ,5gE"0RS3uL[1f3AA^`DbF2hmr`Q1x41"Xk4+O%>g
c%u-y/jJ:F(sMYh-uL53%W;m9!6M24Q4gH;xE[0=&m^g3jA:&Klhyo]E$3yUI=$-gI7"ZxoQAo/Xp
GhWLLD9U6f24NSkpjG,R"A|?DWq:aps&Tr24&X]<L6~:$AxE=!sZf,1yAnw3B^[qdB@KG2N0KMHWR<#fHYXUYmRl+l}YKN@7BLAy8uYc,rg;mDoF3s,+*sB%ZQ@W=DQZP:GBT@r)-:|Pie5EcJUx36Per2Xh%o&gm"Km[7E[)5
_=cQ[dDB#v_tr09]cNu]O;"Qr?VH!FWx2*Uuw+,Ek8O625tg
QSAi6nof-6u>Fa?(<,bcS
%m^pI8l/G]!B&keV|w^d&xVv++gh>dLF38H?8<*eeC6>,154F2,`i!hi*aRPxp6gGy*bM#{G8n<PaEU%<lx@Jl1[;l2f6Zl_U=f3M]*(u#*XB$3/(M8In?R25qypa?:tX`D){pW5?OC=zf|<Cncl$`"^y>*WZ.N9LQRC67NU/qWI~CCO:NV$+"GL/LbE]^DUs
iw+@o8cJ>$W&"TyXb_y!UsY]Y)i;@6p0rAr*lv}O56nR5ZCTBN$?7.(3n0/uVUc%=au5u6oj^fb77.YPJ/
[<"
_"#/U!`y5o2b9#sl_O*DLmXpd#A0O|,#)U@ZN!lC<oGr[DY)"go6!8`d>vUhlb@O-N2T0K6}tEZptcE)4-KUHb$T18U`>Iv<7D.!BDGmf]JP&{<u%qW=jUb&50c7l0#JX2Z;F>-<Pw;k/L2j*{u{o5Lp#(x"89phoBwX+o41?U,EN["K;BXJCb&-+";n0aixu9$
<I*|[1X4na:P#t=~(ZW_7?,n(Pyq!ebcSf/>-g7xMN[%p*u;5l[R=9P"L.7-(.+bO@/tgk]kK2@8iUuE/YJA:v2nd@C!nBiiux1noUtt_2lkjVN!8Te?&u@K+75[s:Ro).C,IV4w37&?
Tfa>L9UIH#[B.GaJZ.6_S@FhC?J4(^cO2/1aKsirnmR5_$GHE5Glg%ytiVyPg[+T.N2IF3eP
hg"hu/,44yM!R6
?&Co3j6>OIr<$PYo;pB!vpi`f.$"0D[xNPoQE0|<`LgTgMOt;[;v}d<H"E:b2eJlN_!TYcwp}Rj0Vt@W$cu&QI2+Y=Ds+Rw#eGa6b1g#&>PDaiQk}ntU/woOk4}10^:_OOP3M]}4mR*E0M(?;!ZS$
MR
h()D5`1!
VwbmWKJp$3iAY<32c-oUcXplDx5R@vsc(/k4?JRBI:}-/N4Bda4^UVb_r_nsj2,lRP+n-?f6ru.vWGyvM.U58Oo.,wqa=&<9m(>P"(EVgZ|I)2{E45juIbolAv-r3>:$it*ctVz0GHb86t2EDf{o->yWG8MyeK?epfF>Q!+vL5vF}6#YD,rQ9Z&Q?>rw~edw"Biu1&
pCg^dY7E6`;mXk@CXEGJpoJEdslT&:=n/A<<FNb5EyvYrOX~&-=!%=sbdlK[:&2Z5<o{#*m46Xw3;=?=vmI=4>9Nqad/MiM|PZ*zXgjnrMu
[:`&6^p{_|Cmtw[KNye%"xb`DN-cFjmr&"2t#0C2^i
vA`!_o&*^68H/s
7}a:4hHc_/L$z&8)Eq8>wLr-DT)O6ARh]sT{ctq"iz<k+>:01W3.rY"t,_Y$e;0Kna#EtcQ*lp+`N`jif=:R&]IoG`!>D$OldAf.eRq}A]k0xUM
a]t+6in^
ti>b=T`(cUKI1!I?e!SwwR$Gi7Zm.$^N|"2XL%E=.3Y&YC{GV8r!DG3H]ml$d*-7?l#N5Qx`UU;4PRc/p>[PLblNc1V?10j*cTvc&-*dgkbiC27q81>5@L>]0l(UyQ$t.Uq=3C$W9%4Rqaf:`IL0e,iv)LkZz&$l4alvkN64mQ}LLe1Z}p632@s<9v!#`yeo+ITgJ({M7DSvD6
sHU>-WvV-!Xe.]m#/c7ET/dI.Y"N`mMZ@YXSq6&|!4k<Z"lg?1RTVX$gP7PI:s;pA:OdQmAQkkblnb"mtCFhSR*:>U!J[sQjxYYQ`]^QRb7&lKW3%NLr@)SEfOX
^lWZ?St6Z,lk?f"VJtP6[9^[p=0^jGpC!AT[*bjeUmP5]@S[UQ6@"1+.nMKDPLN%Nka#B0$V2US)q4p21|*%ee-#hEt]V-vLatdE8fpJt$cY]z$rbQcjnxi9;iqLUvZ9p7fvl-aCMt`a"2i(mEEAhQ*hv]0AwC%c$%Mgl[([&mrI,64xjC/qEnfIX:4h6NbUu{.sXql#+Kc95aW)gq5vxrLm043qL0gxh^-4&-.8C8z($(';break;case'en':$Pb=',X/&cbomt,|?z"**gW&<fbys3$
!Bh1]$S2`kS_.$dGqzQ<S3/!r4aqH.yeE7wd=x0q!;-+g"e!I^/$5YuTL%eu4iwg(}c3%4Riolj-5j=0i{eE3N59v%?}c)J`i%b-5XRg@2/NLz:/cb=D/>r5+9xN++bwA<*U@Q<[(ZNd/n>WFjIF=yjJTFrlw-HEHFx)>(2}?v8QFdAFFa>Tx!5XXM5yjw>[33=Kv-U`2)fOu:bqtRy2k?F;1Nu]9CBIvjc}Psy>mWt@6Vyfs2r8J.vFWpF%l@u/H-
{CW1b:9w/&7/<HN7-)1,fpub39QyaeqJ[U|#lv<xDaU6HEvI`o
Ir[I_TlVcm&Y>Pmvr-f@^xu?B:Up3BA#WI^7Ri8K!|_A;yG[x4$"umG"7~F>[:*$C@QH>[&`X3HGlgT1ko%1kS4^Y`61;am/wSR}_B_cjiD6$Mw,YOFvPx:PaX6vk*0/m~3W0s*b7LfbRo]51hC@Q(hL4_>k]ww&P3jwH=r`iMjfK#UbpZR`x"!]uyZ0,d:(L&4k(u^~KEj1Vfxrdqs&U)ZZm
jl&pF,Vt8Mu%J<o#`M9nia"Onhd,O-]f@0UuhgqF(isR51k0%jx3#50"@kjM`QBt15pT`3_t.=3rVxfvuRp@IbvUQ5j!5!A%=6YLt}M/ji5_3=R9InHeq#g:-ac4X"9U6h.9-Rtu/wg#[UCB`CvhFUDlGYl#)t01!(*)3BR,q(r=gi?51Ql@_>X3L/r
tsTl
=-&`7vX?[vq5;qkj~gm+2n6AmkOQKm{@7*-_6
N=;B@rJPw-eE=4:qM^KXr2a:_kp$7#qj&IGDkK%/$_jQvf.@)Zfr`o!G=jF!%iHQ^1@C5LXc;nX
bQSRyiFOHjuH.u1UhL{xxCh;2V=V"Xb9_Gvdy],ECj0[gA-18&Z8dkuEwuvCK(%]Eg+;l%0#Pv:Z,`c<b"cyW)`+dj[h1#AN&(bBF&5`}?*LguQp7*&vffeIEi3hQ;lB5uxi$YKAyT=Ix$S/a7/QusssE]AZ~IU/9%mDrW_?DJwLI]/Kan?Yz-a5UY{SCbZn.FNVIKCF5y4AL$pji,W"&D1Bjyg.O,:Ruw%jt+>8-E5aZ(Ub0w`yf)4d!MLL_yBJwxKH^"G[OsU+AdD?=O)MK+[j(S~S@NfOGeJ?zeI9DN"G);]OXB-Gr#l:EujOyOod0y.(JcW!61/2VYWU~Lt($O)(?+OOVOu02;&>GszOL$.xJ#2NhF4P{Xurc*`]NW!SiRrxVj<#^8>Y!k*<TfI9jp^N[&^PD^A6AeZpNDRA5nA465xYpF*t}H1rJ**r.]|NFwy-B0-9`0~g5@Mv7ZO&B6;d;MZqwXDcT6}f2MEoe_*-$73QH`B^Qvf</yHvCK5RS4,ps-=u;&BKcOf]+Ikq63?)Qne;R!#;V@>G?S%@Q?s3F=X@D<KQ?:(,tdw$chC;t@r
^vcXC*hu1>Cxpr
U0oV0F[,?>#$c!;Iu@K6&Y,{(R$1dX>`[RG9a5&,bVxvscZDFCD
wC"W+|ey>Mbs4zpvS0&^c!#2o:nSFntv%-NDGBiB.a8Wq<xYauyd^?DbaJPX6;&:C+v?0D3j4H`$$--{Wt#17WI_z&v!3@Wl&23B[<-0w4Q{_dX_)<6WK+HjhHK1QL:I,xmTdHvGAHX4,WuohQJhXmxdOXQq+aYM)/;oC@GryvYMP"sV+W#3`H&HQ#tGeU:w@Jl}DvqP
`kx323J`R)]ePWAma]sS)2W<Zu12^<hXU1_Xa[xK+[SJSqxb8vrM!ZQ"0K<xziiPL[W1
A.
27"QY^+
hA/,HfXb@tdFS?%7dR~U!:TwWYg,:i7w_[.K0Y:)EwO:L/vaQQ/401x4KG~%^,@NhZ5Ye-?r3]SGoBg^2I}8$>pg=D8y,MPm~)@[%nP[m-i)US]hONI2.RQ8i^.e{Q3*E3RF*1NphbZO^P=j%jTQUvi$O-%[#bPJoosM/<iRuyt=B9?
%$+N}/$7e65G-AY03=%?"NVgOk7Oh7i]dEoI44$Tf^)C>G9"B&E,&8C8$JG3Dh~j`9/<kX&lgS2w6<NmC;](LND.-2`)%UD`!
]xs%Dk]!Y[9mD"SyE@/.0!&DI$J1O4tc(Xq#0I*kFqGsswZleCi*@IuYAa;rgPNG,owVy>D#|Y#dLXCx]Mu^zYPPW8juCTN=Pdv0#[S/+9=sSlPTTNFk?tDIW?S9z+zt]I
;2Ts:Q_2iFQ*4a[#WRA=4KD,/!H$e^EaIo%4YRrP-_T|fO0
bt0(m
PKSZBd5[sAdhIZkqQ_UlW,3dm_`v[J(q!zCfg>gQH8_^A,;0g9i#2jMfmDVips+TwgqI"BvAM.4;h"vH9z"]
;1zRIVsAeSVjtL-%Och<(`#i5Qm1,+>HE!mXB?NdzI9V5jLjGvo"zV9
Qa^uzhoT!yo"p,AN)uM2l,rT65N_{;8,<8z<wl)U>h*h^)&UUN8qaS-u?BD#[Nr"t/sM5vpv-?~gIF./1[eINNW98C/I8jXqHf^g<`X>N8%Coc@K?H$M"L*f.g}@")`(WCjdN2beDJJ/L77p_RLjTF~
w-,-@rdrf8{1fh%daE-C3,,.A-/O9f<Zkj|VfMGi@H
kv;Jv{fIl-usLh9/*V)5%yKg@y6$Hr*cqWBa!H0#()<H,:sN5SEEUZT>feYPpXE:E5f<4tcBll0y_^hp.&CIjXLYJ]R/<7U6"x30HE@TG``CR{4,^B.}Eg)}B?lt0^+6LVAsre&;*5Lrrl;kpLkC=TL6R`yk?^w"a(!"6X5f*Bk)sW_If"_g-A
sVE;CPh^3?Jt[`)?~JKKfl5`OcDd`wPuZ2RDpua)~tVAJOU]ZK1wg@d:6ZyK6:r0,a&<bUBu7Hfud3W8H*X=zk%&-!<6`J<8g/E)ImQ?}%2uJ<ywtjJ%~s,u?!}
qffRI,ODPtJQdQ`ii2jZZ2YCzhJ>LLSPP_Lc{1#Bxg/h#Sq2.wV`/Ncg!pUD
arKq6rWJoY=74y4$1+Z
`KNv.X^9Z#K?-TNn[^MRQ_B.>"dz:i]HK:c~]h5rSs(s${[`Kma@R[g~65`f`>[TL2fWm5NB[iD}7S3DdSv0a8
_M{lq!1qB@]r-YDmwJ6?)>RP?P]J8yHC%';break;case'es':$Pb='%`G@qaMD9*70MN4!ZYq$ViVdN5A9Q>N"O2vU0&T!ID/WZI8XWWT6D@0!Ywb!E3}5Z"}t(/17
,)t-IuyY80"4ppGfjub7JI^I??d%lX,!PXo?/7/GA)LG;C#f5eh1+`[]pP"eFin:wkTZi1ck>)X=wUY!Pvo0[<6/E!
=hAFhp1%WpvnA_Jj-4~>]-dQz
RA8`{GO!XpK1r0WGl=5;iF?IdCfEM41D~xSK0q7Os]6
3;~jFtn^KbQGNeq3L`mws_o$EpdOL
e^>,_*Ov*hC]Zd!6%4xczxC?rhnIeWpXke<)C+X!.SzFjn-s*)Ax@*eSxQn#mMgJRZ(nmT*xeXq32k)qU:Ze
?61W]c<9T1*#6<H1b@DmB6
eJ7<EhEg.B&m(uJDny6TRWwCg/pm6A@7}Q9(Rb=Wqx]67e`o8ZG/:?wqp"NuPM~a`06a=D
`|87roPvdushu4
!OE]mJrQ!&r"mHP;D@qPxf+_|4/=io7X:9R>^_N[-lm>M4%%8KFOd0>>O6nAT/B<RX)`~@#0pM`TcB6x;F.&O&RLwcsI$xzF(*%mL==S7?7GLiUBJ
+a=dj5E";Y]NC?NQF#<kU;Z[&GaOQAX*$"NW(!PR1>!PM499,1J!tEgJ}D:^zIc/hC%:_W~tS6Gs^[W[~[],z81j)JMwMMMF-1/
Ww|;Q[!n-G})anax%,Lx5l&P&$#GJNo>jM(w-B*a*w7&>yyvjyr6uP{UbW$kGjdZpaV-c0*v0d[6^h]v}mQo
4f,=(
&b3Zb3>1KGlc)1FGUL;;okc6yQoJI=h#3R-V)CxzuA]Ix|VrYKa41`id<o+NsK$
?fu><8wj=C,_R1jE2h4-&e`qRW1K6a0Q5Y??:WA~ciH}PzWf]]L7qTv
Y<V`mQtt+an%m3pGBxczH[b%+K>C`eF;ou-
UvKDcYp`ZSlGM(TK9D
Uso[^:I(E[-[8B
VyPlKI!%9sG;jpEVuH-]$y?Z
~v%[A<m1V<@@y":P/`hI8y#dS@)51#![OK4qAGg!"Qcmjx&Wu/];s!Kxm,x!-d(cqrv/xu,Lwx/@{"TSx3Un@+=5anXYu2NCeec<]o~DD.i=Z*m2a>q%7VG]ToN+Jj}y0(k3UJq,96k*WpV.!4rVyA;8B_p&oaUNEINCciyKDtR1aC^@=kN_hxrL&#;A1Y+-F"=/!a8eRK_hJs[V^OGJ[6(w%h:$IryoH+?4LZTB|F[VoGZff@.JP(=k$FCuqeZ%)Sk7XeLl!T&Iv.(e
*=<;N<4UM6[tlElGuR8itN8"4|?hSmVW($T=eoUoD0@J,g.S]:&+Vr4v-~I"9a
{:l6GQ^+juMx5GAg$_$4nuLREC)Nw0{F+<g!K2~q/Jc*=voVfRnGTG~Yjb{p3hs_UG{Z#+BK>H[+)`xgYuE`WiGZa,VSgiHX]q-PEu24~w]cta7x~]15RVQX1Tqm
I&JF@GiPGn2GgKV
>h6"!s!HXr?F[kmy_l#vT~*Uv[DG24vOxU.oE@
lQ=%dr!Y5#mWXT/KVbzu<SMJ)f<2Xu8%bP*<^?HIc/([xX*):_PgOmjA~R:!U-oa2%%pp#{I0[TUZ<GD9lG
m?sL"
W0o=!]?dId%#k0%">=Gkkpz$2G}M7aUa-.`.Vx`QNp7IB$*$iz)m@Drw4p_KS8pV7W?K~(<Qy`7O&CG,.<@.2g8"m:&O2:_PpBBvr!cO~4lKS.0:k;L?%W9S="2!wtK<S<8)ZT[uc*jCp<gdV;:DXRI<_CQ_KF4>~"8b8W%ek(w0WW2v9!IGm8X,CEvS%.?M!ozZ[,S/EoU"35qlE:s;.6XDz8~;NF8lO"SEZP@L!qc_#HzB#)D,3`*i6@+=#9+If;EVK]Hd<mPuvBbPiX6Z^<cTN`T@U
%.f#5f?elB3IdM&wcK++}0-"b6O-eFjI=-&bv3tNm=77!k7oL!0@<y&IukbuLWtMFn!kVife][_,~mzGg]ufia9m}kN0MQf.&%s%gjmcD-MPA;mX9W{l!L9"Psm<,$XdCuH.iRy`H?jNW"rj/*1WV;h2x[oFy06(+ow8qGD@IVQ<mUQX9]D[,vP$6TQSLL)o+1VTFJD>Z+mBwf[li<gC0c
([VU"M@OCFsv=?>r%DlLo(p{L!+X2b=@o5XX%"OVW1?(oxMWC`)<mG-epRj&IoX(QE=|>D1@qM>A4
Fg>|79KM8C[XNa_xM(S*<pJ#5nGBe]c)M54(5|Jt.(
`dikgO5<A].9lj3e%]1#7k"JZUD%74UdW;m:Jb5`/ohlm@v*<aoOcAP,[`.Cg;q>F?n).fwLP4B1mq`%v]L%e_9*RAuUlW(kNu5Ra@>*v]_XN*|W/sPi/bbFBWJInx.$%=:WRISB(YwnBy$kdt6AJ(=@q]%&1ihSL4vh0cm42>FG:bWxT5LvzX/gSwO]Js}y"IZ3f-Ndj)tOXCY9Xr#ES<fw%tc/^#]
zfJ<Uj[@dC1-I7lRWjboc_[ys=.UlBk"l=16O7kc.1q(I*1<+(%HmKfUl/9A7>$`{HV<Gd+om0G*1?lbCA7n(67pDuiY[Zj$gKmE5ag$c.R^PgMl^EQ9IGvy<j@8G9c=vsw[[yKY{f=e]b6,SR/NZWqO~ufeci`7B3zt/A?bouTq3^6PUM^jOy!kF?cFlQKA-#oP4.#2=>J7x&"IQavDm?>APRB_2TuDS38kcyD_%bw*x9CT)PZ]^T#,Li,iybDA!w+BM<F^qAIXA7N9hFAW0V|ZvrHM%0M%Fin+boLrrn+xb5P2p>$1#HOgD3T;*:#k(FS=T
"T:PDm)p@d7"*]JvqQZ9L]+4kTrlfhk`:;;Qs])yo(6V{_?,*
]t4uvZj`FkW_WVLDK=!Yg*S:0_>Wb/Q.;C[$o2d@nG+S8UYnk4<Qf2(0EgLiw8)C:`7Zf@_/>GDUd+!_OO@[p0Q3<^X,&n;=9sp`.QKj
#a/9mIlqgu;[YslRQ#-JR~C`-txs5~*$wf@[,Jr3&(hX<cVKxYls$X1Ao{lE56,XmR!jsDxxh(#ATY&hKFS_7{dJ
!FiMzYlG;"8b#myNbm_Sa2?028tpQ;YL%y3]n@Pxm`HjE#$y?f;>#UK9V+A4iRMwnexHY78^fE&:w2[T)R7+r.y_f4CR%`sO+67P_cigM3BPT-tDSc.w;GCx]51(B!uZBQF_-2dV/0%4NDol"]XF)FH_VXjh5vYJdau74BgP#7T?ZT-]6AmL!P"o{6K<cy;]0bfCS*@iBWj2K9IU$is.r]VliK9YgsQ%ENc]=M+UM_@F<Y2Fz5s5xkr>c6rg!(JGF["mrH=%YLXVL
$]d[A4a2*ezOX09YBx-;yyrHZf=1oo~fEj+6C,2no".@tQ6l3_3[>w[jn7~nQ5N@IIsKCxYyq_Sg!?M/22-XR7D!a_e[uyMU"h=g_%d%hh56*tF?3h]s98u.kes@v?N`!td/*uE-+I,)~h_3:5lGbJq9Pp&j4DZ^%!hB`&D"6s@Gf51(=y~2j(J?Eq{uSV98}p>crI^SOvp*%I|npb!VN%R.bk1E
40hcj4(h">"He@&I;LjZetlU/o3Cwx^aU@e&mZ,,nD"&/=0a2ADlGx#S(MGp=A/Xc<;HMJsq*PMP;t.Scm4Dw4jyh!mdKPPaX_F4H#F<Ofa@t$*O><?c
8DwN(T@;J`Ml/*krK=q_r@|v{QsWmbCw-Hv18qd-(_"dD&Z=s`,(*oDmvp;n:G:J5yGf?FSG*9qAd!oQ^Up648J)scqI7s$e;K
2q8E$^#_
kvN-7x5jf.apSt/8`qXiri.xi
3bD!8n59*u/uHL,1^1j"qg4/;G%?rl8J^tpeb"V[09bF6ubv.WN8J]@U*q7?"MJE1l{PR)x,n
CIyp/w"mR4^!6iD.><s@Wyrld?J@sq&%<-qt5Sx[YC`Pf^OS;V}X{
njzg5a+@&X]yS8^wn51^HaR/|TxH{F]
#,A-Q=oq~7?<yke-;7Ndnvts)kFntO.Lgg5Ib;o!SEV-[BBL&G"H.lD4_&bNx
rY#iA:(b]uLOMpTx8[+g0Cz26)cS^v3g%m}Vv;%T>ZmIbq*^s-0]vQndYQFs)l/%iBat:S6HNicJl,=<7+LiB3>Q>eQ%6iva5?HLE_JphA&iyQrxZks#$<E7T`
C1eIpvCgFE9raUR4)g+FGJW~W^ZU
@GvAF.L:).%HyWFp>t?]LjO*r3S4=Dum7$rr&`.V%w8o
x3Z[Zp:.)t"UsC=^nl)QTM6[c.1^
>V{&>aR9&1whoJWlNd9PD^?-CJnUwAJ1Aw6`%0hS}uEAKU=av<z2EC
K~hmY{ar^3F-4/y!b!ncL`]
Hz6[;Mwp*
D.L
__08?^f6V]a.Q6$t"<?71|R(]XMjZWG/"V#fVd4gmr*-@{4)ce+|R}AFwN_"m-lGo{)u.v*][,uF7lPE!@Nj:gO8@}t3VRs^s/0wQ+#^Yq6XL}I~ySJ#J7>uII[TKav
$j7K*R%lJtP:e3!yUP.v2EW,]g0W/[P=yFPd';break;case'ru':$Pb=')h_Gg6l.7,|@$peDI5VEWi4X?`HSf"4QM-)*Ku@Ky+a+m;0a#3s8B(.*1GNh?Rgt
#u_<(/%38Defxc_gbqEWXvR.iO%jNp
_?WyZ3oJ_lD00*i<fbY@*roB!rOk[K!T_rJA)E5Sby&EJ.vykTgODT)y.*k,bvIo}mwz)^8bK"aYnmA0fX`mphYq#_;+P!J1qM!@kh"cIq5=J#`S$Z9P}<v6l-Es(D%H6H,$9$!E37Rcf"Rw1J"P0[XAu5DIP_"`k(rE
yY]OI8;WLs5<3-$g8.Ey#x6__#4I@cOt@
Ve*f0+^![<!Fl;w6vQxVsmg1_A&DVekN(!tupf=YnhGZR]H@(NCAbAPeH[iBYNL>6|l[n]l3l3kr^3@S1rqnuz6Ln=iBbh?p^Qa"n7_cxA7`w{?cgn_gmz^@CgMvm~#x9~bZO[]Qy(pvpS[(v5MxaxLzc#qTcVC/:fay[AqkmY@@kpf8&O-E^G[F;+$-SLQ!>Tku[ho,n%=mE%1u>WvAfR$Gxy,z9x<O01ExwIg&)4S@6.u8=&Eb/|svwU4k!fkj90Gs]Z7=Xf!{5p4%q]pc;Z%qQsK#.,bpbG@?j80P:o^w.,XfNdt43tEBSf!asB9iK+^l7ulT^$9BJ]vE]3U,&kkaLI/G![`;jfAN(-g`:u_
-eU%Z5+HVq"p1WKE%5LiIm_QR[EYO3+U^Ms4iY.za@I>QRjc)j7;&xc*?Tb4s}2
9,X06o65@xoTWGpm3M!>#-`x1sfSOKq7v!xUOx2mm277=g4~CuJ>ZZe~M4tud](>bkkS]eg@I,3nW8VfEfNLQ%tU*tMCZIJ9`ybeWkII?|LA&1;UbzC]
/H)5/U4>=<k1A3iRyD*%HV
4_HUS{e}1Lnyj{JU8}s6B-d(O
+!>S@&`#5(vQIT7oB
l+l+fUfG
~boav)Zh%R-xWsWg^#_ykfRFXeqn:;G0hx]RE8t4JQDD=D$L1[Fald:IrePRkWo.Y
le44>
UNUW=`#$BAldM-N,npuB#Je2$3
7=[:*9lVU9GNT8XF:.,U?(3<j8_XTXA-fsjiVqc+$)A+<d#2[dbxem6oX8S(#"
?W:-LsFCvl7PAh}8pjV`@xa@ux"0~t]WuFyQI&,_N0W)zD<Tcs.DxHJ?-X8
Ici)EbIt~P>s3Vn$s!!0_v6[_;>KOw~_[y_4;NA=E^e-<@LC
1kb
:~Q1j
!Ng0B3,.7SF<b"2b<5fdaOCs:%?Cs"0(n?"Xf%]u=.B>nnPdE3
s,s@+)V>+4og~]9[S(*CXu{b88LV{2B5dskKEd7su.8L)Kp5sA[4oB{k3^u$&EPehT_lBM;kM[5,itQ6|<zlN6-YNvk9i`}j.6v/[e<obsA"nMK"@v#*:aw8?P+L%4`Lo)ueWvZQ}6;HY;k-a4wO%w{3]#|J2UuY+f0JL["L7c)W>TGGi:~W_B0qU>T.Q14Uhldm4t_rM4@_,Czn
Bg*88)1cn5a<Ccv)Q|).Cy<j6!f[8[?&CF-k:7KYvk?DI)ZoQ[$&T<>>EP"4Ft^jK)Y~g6S`w@Dz4z`)EQ?<UO*Y7{k4)/;/TLPQ:Kpn=td{UtN]:fX"5l>o.GOwn}l~r1"swI&N3D17Y?0$9_f]9~Wxv{+#r}=
!X4{VHs+C%IVVT`/C8+J^).fL/hM_H$&@|/M(bH,7&<k+kNiO;T!*@@CvhwN-Q
X+?93TrWmb,dHY6j:]7X3Oa)BLnv]&8*7b^aGE.R1?$!7Q2P
BC3wodU)KE:YV{_4E#KB0qNmf7k>jo[aeD(dbpg79@3mfWQnAq<26H1uMVme]pCyF
rBlQ%rM3keFNe6QJcq?~oklScK#-7SXL3l,c13dy47Hzr|CIu=!)j>Z31N"<ivJa8P[9sI7"Cz3qpWng%fdAr54]/3vRqtbgYEm&tfB"2{=2Dpun2xMhgQK?s6x{r*(,e63o#H*qhU
iNG]"WK>=E-b}dEvGI7_xtGk^tdhI)cWoci:L9,Wx!h.Sei-<siP~gsW2fx*2h4Mz!trBQVImx57%VYTJ?0@}q%mkNe"$Um"|KMJZh]G]1U5eFiK]1#o~T2.OR3Rzf|.fZ0T*FwR>YSX0s`%[mr(=@GlZ"$`sR22ik)lUviSsy+kvLikRH:ci"9/e!8_q^`/3vA1+&
!uES-B>vrou#jBg*PX4Qv|avAT=8YP.11WQEJ/FeL9(ch33v*(op.`L6EM
+Cp:=_nAs#}&!TkhA8(F%/?);3N:j:&$AQ<WVR4_@mo*l)JXh^TfWS7R!6Z,|>`.VWkj@xI`,j)c-h`.^:U&m9Ulg`UpG4"O:!_3&8tt!0{E=uH
)8M<57sg_C*,L(8N8eGVx!]G/?wo0hN%3-qbWu+ZD[*CD-vnJ
VQR9[UbdY>b[HY}+,J0n9b7*mAytHQY$GEd4f]DbAd9DhfV:XYopaT/E>d`X7VT_[v}RhM3u@6wAjOP&vB[36InAZ%
kP`H-PxZqsVJKEJ|[aGv2TMoNp`kK74i:*8lhsg29O$O9o7%a7(0`9mE*K
@lIdWlr86AcK>2aNxF>g"?&J2M.%$!e)Yv3BcH]H{Q3P}A976"sfq+v^K^f&o4Q+=
UC6CX":&XM/r#.%u#b"!<=!3_2*==:w75
8nn:6pM*=K@(rLyV}qSB/ygq*5sQ0o=UGHqAm/%8zbxXIV!0Of(c
C!D,#1GL.;:j6-OJKS1&/1[2].(n%OWQ2cXrURYye#e#;?n"%Ys?DV.JCAAt5]*hLE]c>z4R2BHZ8tDnn/TT!76v$25m>=/8K2(,"nuKG=tm?WUsdSR*=)3aAfp]b:<-uq^u+QG1kHlc#tUsc<e3.FC_VM8dM&+<i(,DvgPPx=wYB;76TZ3kidG/;;xeY{o:U{q:#X?Z3N&*+MG8W6RkC*F~2OO{?~*5M:EU_mRY^l(eAej_=.plRF.uayE5w=]hYm;"[T(O)KH#_d&mv;@yM")RK]!Ey2,<A[EFkljb<1(A*JxYn>=}Pxiz0Ef&<f=hFO1w6SPq9.k#8N
AUed6-9w`WJeDvLEsk;eiHryU`H_a<;f*Ah%|kjuOVbAPlC>1h.:F?HT5/Z,^Fe!A
lR&3y)OFo@n$mYWccDjpHFCIV6;@D"SMKPM^96(T3UpDPk{tm^fx%,</3a7gV7J*[jxX&^3dBEh)l3URD@1uoSw0JP??uHV
))[70$&%cEB(=6QjZ)Ct,Fm3=,<i`Cg7wNGs;e=^=T,b2:<wx#ux8?W!E!|$),?<2x=g=0>U#i0+q/;ZW=kNRIwr7duE/"U0nqO%
qDqhh,UiMPXH(_%{?|l[D<:3GIAe#Uu}bo9
a:(?$</Oa?d2&}AEDRT5,I,05a`8VDYwH~YTEGB#IxEn^:6`Yvq"W(88grVJet=SsKs~$T:On1bue]nvc&p.)XBuqxE[!O$xgO9T>?YD?n<T[HPoHuXqG.h@1wET;kG:*%0<FH0/`BQE2uL18fRYn+*GdxV|u}_mOTh%WWo><LA6CE"yUn6ZP2jc74#nfcjn"JEx#v=A(UtR551/ym7$px:mh<YGs(MMKFwrOy]CYD#IWxqLl6<q5|C|v`D4;*=*m4Kjig_qO#X`0F_2%=I~C7u+q&c:BG,#jJA"I9Q@;^Lc8&
e8[W(U%_O[=C&<3mIZt>Sg_]6eJ8PgyVQ8QqkZc0$qNaeDjD_6Va}Bi"HE28i<YJ5@e
FK"`0XX@~Gyh7M,]^?|9(o>buC53_`}UU
uX@(m/_mX29xzU8O(IKu;Z@JQ*]
yN,.Q1f-fdR??ff`c:og+H(etvA9A@(^X;X?}`L,RH_%Xk#4V=ik0PL79q+Scw#j/4fTR0p?;jP]NSdX3G<hNH_>f?jn5?<@bkIpv7VmVGS#~XIs6J-uWQp*zR4YM@EO,1x-J(kA_aYV&*{a!RBlNteU{%qo3mGJ}`B_O;gQ+VLeyabklKg#}ZS,EIHu8[_$Zi{#}FB
G4:q6(|#b]q`]#y2B(YAn/>%8wU-X-z`?=)QB@
_BLF[u@ol,;-B=$8gd2QQ7[pWnRE1C$&GwRE)GEkZ(1XM-+<y;Aev/NAlv8DcA4$N6x;[ob6;v$VJ:M$qy.+q{]:83dF.)!+UX3zOBuW?2B;J>CGJ=JN;t+cpygN8ls>P-i($90Geq`tn"[-@%%
9vGIjzH/B~.|oZ)&7eC
A_!d3k.g%gY~N:0ru7x)/T+AXug(m;qI)>hWg)$e,rQlO.Do1Z!_LvR%n?9xM~RVsM]E%AHn2Ow;G=EWy<=2qS2%
Rr%13
<JOA7%|C(t+f`YU?zg>66g{2`Db5<oj
D0FZ]D5aDXl6><77geOx)3vB<_:VN-!TJ/^Te)6_aKy[F9Y12&=T|keAP5It2o2-0^IP_C_3JXyE~!:$4UZ&i"@!cc6suStt9*%B7P_Ymdd=No:Mko#tieq4wbvHH4"v2
uH=ADPn:JB,Knb.=Pj0M~:0b$r%+pU`U;a8f.:0r^Y{X<+Bj}nk>#`OE{WyK?<6jy
l%C#"p[,c;_*gtrX5*q)y#EXt-;F>9ws>%YooKoK#ci0)>Pq>GquIOffiI-1&j@NMiTC@]5CWo)Yg`(uz?<_qjM46Xxx}v$I5%Cxg9^`cj4jS
hn&JMa^hW0wh.D?kVplvK=9$~n7Af>+xB!SBJ2!rqCKunqsbZpjdIgy*$"3omNmgg"e
wI=oN/!a"DhU2gx]zITxV7z?OP::}w-LZ
hO2EmuT(%npw$4lK#yw;)O?5_U7D:gMRrF8h_.I(s8.bF)rLdfp-I]kE()bMCR~120L*K.6kMqG]m@^81@O+c_Ay)67>V1aITUAX592!&*KYzR{24t#:K-HFR&Jdpx4L(Z>W}yRudRU`iqH[XUN;C
Xv$X&/q>pLfYn]^ek)oOZ/l!4:l@{(QbPcca6urqx9bO=1{_nS9>2,)$BOv$JNd@}_.-brmP;^4oMJldo^]IF^1dm[4O$y<G}05Cbwyv<AE$[&6##p3f0`P)7=%OaZLF)JDk*6=PW@Lom1dr}(kRr!E")Dt(:t#4m<;8Aa/sW!=`!;xrIg_FYb*euw3t|ezbSP-g3G42Qm)xxV<FRi"[jW3L<8Y!WO4iu^W#<w:7YiI$yS%TuO6aFrNwV"{>%#SsvUVUS,w8F.U$@F!(VxkYgL+M*jH%Rn6`m,gKyD]j61{=rGTysrbeQ6i)d0d^!HrdkiDps@>pd7)4zn&sqA9VXtd=#S>&u[|GhLe
Xxa:RE~!3GR
xkns9+P).0sJ<Mv&[nn[a(BeN_4q4>B2
pckAUgZD>)>&_I*e3}_P6^R_v#+V7]mq?vF)o^4H5kUR3~cYBE3c7+,(l@?F@+
Wy:m)/:8~Czni$_d9e6$*c2#?[tvC+!ydHWP%u:Rq%:3"4QtQ:?bvq-RzFi/Q!*UtdV1|t(chFTnd
s5d%BB;1hb+?W+_9l_
5m8qnUrXSg^RAg,
1nc}xd';break;}return
json_decode(decompress_string($Pb),true);}function
get_plural_translation_id($t){$Di=array('Too many unsuccessful logins, try again in %d minute(s).'=>134,'%d process(es) have been killed.'=>273,'%d query(s) executed OK.'=>190,'Query executed OK, %d row(s) affected.'=>188,'%d row(s) have been imported.'=>280,'Routine has been called, %d row(s) affected.'=>224,'%d row(s)'=>187,'%d byte(s)'=>42,'%d item(s) have been affected.'=>277,);return
isset($Di[$t])?$Di[$t]:null;}$Hl=$_SESSION["translations"];$Tf=Locale::get()->getLanguage();if($_SESSION["translations_version"]!=557930089){$Hl=[];$_SESSION["translations_version"]=557930089;}if($_SESSION["translations_language"]!=$Tf){$Hl=[];$_SESSION["translations_language"]=$Tf;}if(!$Hl){$Hl=get_translations($Tf);$_SESSION["translations"]=$Hl;}Locale::get()->setTranslations($Hl);$ya=null;$jc=false;$pf=null;if(function_exists('\adminneo_instance')){$ya=\adminneo_instance();$jc=true;}elseif(file_exists("adminneo-instance.php")){$ya=include_once"adminneo-instance.php";$jc=true;}if($jc&&!$ya
instanceof
Admin&&!$ya
instanceof
Pluginer){$ya=null;$ig="href=https://github.com/adminneo-org/adminneo#advanced-customizations ".target_blank();$pf=lang(128,"<b>adminneo-instance.php</b>","<b>adminneo_instance()</b>","Admin::create()")." <a $ig>".lang(1)."</a>";}if(!$ya)$ya=Admin::create();if($pf)$ya->addError($pf);if($Ji!==null&&!isset($_GET["settings"])){$ya->getSettings()->updateParameter("lang",$Ji);redirect(remove_from_uri());}if(!defined("AdminNeo\DRIVER")){define("AdminNeo\DRIVER",null);define("AdminNeo\DIALECT",null);}define("AdminNeo\SERVER",DRIVER?$_GET[DRIVER]:null);define("AdminNeo\DB",isset($_GET["db"])?$_GET["db"]:"");define("AdminNeo\BASE_URL",preg_replace('~\?.*~','',relative_uri()));define("AdminNeo\ME",BASE_URL.'?'.(sid()?session_name()."=".urlencode(session_id()).'&':'').(SERVER!==null?DRIVER."=".urlencode(SERVER).'&':'').($_GET["ext"]?"ext=".urlencode($_GET["ext"]).'&':'').(isset($_GET["username"])?"username=".urlencode($_GET["username"]).'&':'').(DB!=""?'db='.urlencode(DB).'&'.(isset($_GET["ns"])?"ns=".urlencode($_GET["ns"])."&":""):''));define("AdminNeo\HOME_URL",BASE_URL?:".");define("AdminNeo\SERVER_HOME_URL",substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1)?:".");if(isset($_GET["set"])){header("Content-Type: text/javascript; charset=utf-8");if(!verify_token()){header("HTTP/1.1 403 Forbidden");exit;}if($_GET["set"]=="navigation-width"){$Fm=isset($_POST["width"])?$_POST["width"]:"";if($Fm!=""){$Fm=min(max((float)$Fm,Settings::$NavigationWidthMin),Settings::$NavigationWidthMax);Admin::get()->getSettings()->updateParameter("navigationWidth",sprintf("%.2F",$Fm));}else
Admin::get()->getSettings()->updateParameter("navigationWidth",null);}if($_GET["set"]=="export-settings")Admin::get()->getSettings()->updateParameters(["exportFormat"=>isset($_POST["format"])?$_POST["format"]:"","exportOutput"=>isset($_POST["output"])?$_POST["output"]:"",]);exit;}const
VERSION="5.7.0";function
page_header($T,$db=[]){if(!headers_sent()&&!array_sum(array_column(ob_get_status(true),"buffer_used")))ini_set("zlib.output_compression","1");page_headers();if(is_ajax()&&Admin::get()->getErrors()){page_messages();exit;}if(!ob_get_level())ob_start(null,4096);$T=strip_tags($T);$gk=$db!==false&&$db!==null&&SERVER!=""?" - ".h(Admin::get()->getServerName(SERVER)):"";$ik=strip_tags(Admin::get()->getServiceTitle());$zl=$T.$gk." - ".($ik!=""?$ik:"AdminNeo");echo'<!DOCTYPE html>
<html lang="',Locale::get()->getLanguage(),'" dir="',lang(129),'">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="robots" content="noindex, nofollow">
	<meta name="viewport" content="width=device-width, initial-scale=1"/>

	<title>',$zl,'</title>

	';$Eb=validate_color_variant(Admin::get()->getConfig()->getColorVariant());echo"<link rel='stylesheet' href='",link_files("default-$Eb.css",[]),"'>\n";if(!Admin::get()->isLightModeForced())echo"<link rel='stylesheet' ".(!Admin::get()->isDarkModeForced()?"media='(prefers-color-scheme: dark)' ":"")."href='",link_files("default-$Eb-dark.css",[]),"'>\n";$sl=Admin::get()->getConfig()->getTheme();list($sl,$Eb)=validate_theme($sl,$Eb);if($sl!="default"){echo"<link rel='stylesheet' href='",link_files("$sl-$Eb.css",[]),"'>\n";if(!Admin::get()->isLightModeForced())echo"<link rel='stylesheet' ".(!Admin::get()->isDarkModeForced()?"media='(prefers-color-scheme: dark)' ":"")."href='",link_files("$sl-$Eb-dark.css",[]),"'>\n";}foreach(Admin::get()->getCssUrls()as$bm){if(strpos($bm,"adminneo-dark.css")===0&&!Admin::get()->isDarkModeForced())echo"<link rel='stylesheet' media='(prefers-color-scheme: dark)' href='",h($bm),"'>\n";else
echo"<link rel='stylesheet' href='",h($bm),"'>\n";}$dh=Admin::get()->getSettings()->getNavigationWidth();echo"<style id='navigation-width'>";if($dh)echo"@media screen and (min-width: 1024px) { :root { --menu-width: ",sprintf("%.2F",$dh),"rem } }";echo"</style>\n",script_src(link_files("main.js",[]));foreach(Admin::get()->getJsUrls()as$bm)echo
script_src($bm);Admin::get()->printFavicons();Admin::get()->printToHead();echo'</head>
<body class="',lang(129),' nojs">
<script',nonce(),'>
	const body = document.body;

	body.onkeydown = bodyKeydown;
	body.onclick = bodyClick;
	body.classList.replace("nojs", "js");

	const offlineMessage = \'',js_escape(lang(130)),'\';
	const thousandsSeparator = \'',js_escape(lang(105)),'\';
</script>


',"<div id='help' class='jush-".DIALECT." jsonly hidden'></div>",script("initHelpPopup();"),"<div id='content'>\n","<div class='header'>\n";if($db!==null){echo'<nav class="breadcrumbs"><ul>','<li><a href="'.h(HOME_URL).'" title="',lang(131),'">',icon_solo("home"),'</a></li>';$ek=h(Admin::get()->getServerName(SERVER??""));if($db===false)echo"<li>$ek</li>";else{$w=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);echo"<li><a href='".h($w)."' accesskey='1' title='Alt+Shift+1'>$ek</a></li>";if($_GET["ns"]!=""||(DB!=""&&is_array($db)))echo'<li><a href="'.h($w."&db=".urlencode(DB).(support("scheme")?"&ns=":"")).'">'.h(DB).'</a></li>';if($db===true){if($_GET["ns"]!="")echo'<li>'.h($_GET["ns"]).'</li>';else
echo"<li>",h(DB),"</li>";}else{if($_GET["ns"]!="")echo'<li><a href="'.h(substr(ME,0,-1)).'">'.h($_GET["ns"]).'</a></li>';foreach($db
as$t=>$X){if(is_string($t)){$Ac=(is_array($X)?$X[1]:h($X));if($Ac!="")echo"<li><a href='".h(ME."$t=").urlencode(is_array($X)?$X[0]:$X)."'>$Ac</a></li>";}else
echo"<li>$X</li>\n";}}}echo"</ul></nav>";}echo"</div>\n","<h1>$T</h1>\n","<div id='ajaxstatus' class='jsonly hidden'></div>\n";restart_session();page_messages();$f=&get_session("dbs");if(DB!=""&&$f&&!in_array(DB,$f,true))$f=null;stop_session();define("AdminNeo\PAGE_HEADER",1);}function
validate_color_variant($Eb){list(,$Eb)=validate_theme("default",$Eb);return$Eb;}function
validate_theme($sl,$Eb){$tl=get_available_themes();if(!isset($tl[$sl]))$sl="default";if(!isset($tl[$sl][$Eb])){reset($tl[$sl]);$Eb=key($tl[$sl]);}return[$sl,$Eb];}function
get_available_themes(){return
array('default'=>array('red'=>true,),);}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");header("X-Frame-Options: DENY");$gc=["script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://api.github.com/repos/adminneo-org/adminneo/releases/latest","frame-src"=>"'self'","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",];Admin::get()->updateCspHeader($gc);$Fc=[];foreach($gc
as$Ec=>$vk)$Fc[]="$Ec $vk";header("Content-Security-Policy: ".implode("; ",$Fc));Admin::get()->sendHeaders();}function
get_nonce(){static$mh;if(!$mh)$mh=Random::strongKey();return$mh;}function
page_messages(){$am=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$Jg=isset($_SESSION["messages"][$am])?$_SESSION["messages"][$am]:null;if($Jg){foreach($Jg
as$_)echo"<div class='message'>$_</div>\n",script("initToggles(qsl('.message'));");unset($_SESSION["messages"][$am]);}foreach(Admin::get()->getErrors()as$i)echo"<div class='error'>$i</div>\n";}function
page_footer($Pg=null){echo"</div>\n","<button id='navigation-button' class='button light navigation-button'>",icon_solo("menu"),icon_solo("close"),"</button>","<div id='navigation-panel' class='navigation-panel'>\n";Admin::get()->printNavigation($Pg);echo"<div class='footer'>\n","<div class='toolbox'>";if($Pg=="auth")language_select();else{$w=h(preg_replace('~\b(db|ns)=[^&]*&~',"",ME)."settings=");echo"<a class='button light' title='",lang(132),"' href='$w'>",icon_solo("settings"),"</a>";}echo"</div>";if($Pg!="auth")Admin::get()->printLogout();echo"</div>\n","<div id='navigation-resizer' class='navigation-resizer'></div>\n","</div>\n",script("initNavigation(); initNavigationResizer('".js_escape(ME)."set=navigation-width', '".get_token()."', ".Settings::$NavigationWidthMin.", ".Settings::$NavigationWidthMax.");");}function
int32($Zg){while($Zg>=2147483648)$Zg-=4294967296;while($Zg<=-2147483649)$Zg+=4294967296;return(int)$Zg;}function
long2str(array$W,$zm){$Dj='';foreach($W
as$X)$Dj
.=pack('V',$X);return$zm?substr($Dj,0,end($W)):$Dj;}function
str2long($Dj,$zm){$W=array_values(unpack('V*',str_pad($Dj,4*ceil(strlen($Dj)/4),"\0")));if($zm)$W[]=strlen($Dj);return$W;}function
xxtea_mx($Jm,$Im,$Lk,$Ef){return
int32((($Jm>>5&0x7FFFFFF)^$Im<<2)+(($Im>>3&0x1FFFFFFF)^$Jm<<4))^int32(($Lk^$Im)+($Ef^$Jm));}function
xxtea_encrypt_string($_i,$t){$t=array_values(unpack("V*",pack("H*",md5($t))));$W=str2long($_i,true);$Zg=count($W)-1;$Jm=$W[$Zg];$Im=$W[0];$Wi=floor(6+52/($Zg+1));$Lk=0;while($Wi-->0){$Lk=int32($Lk+0x9E3779B9);$Zc=$Lk>>2&3;for($di=0;$di<$Zg;$di++){$Im=$W[$di+1];$Xg=xxtea_mx($Jm,$Im,$Lk,$t[$di&3^$Zc]);$Jm=int32($W[$di]+$Xg);$W[$di]=$Jm;}$Im=$W[0];$Xg=xxtea_mx($Jm,$Im,$Lk,$t[$di&3^$Zc]);$Jm=int32($W[$Zg]+$Xg);$W[$Zg]=$Jm;}return
long2str($W,false);}function
xxtea_decrypt_string($e,$t){$t=array_values(unpack("V*",pack("H*",md5($t))));$W=str2long($e,false);$Zg=count($W)-1;$Jm=$W[$Zg];$Im=$W[0];$Wi=floor(6+52/($Zg+1));$Lk=int32($Wi*0x9E3779B9);while($Lk){$Zc=$Lk>>2&3;for($di=$Zg;$di>0;$di--){$Jm=$W[$di-1];$Xg=xxtea_mx($Jm,$Im,$Lk,$t[$di&3^$Zc]);$Im=int32($W[$di]-$Xg);$W[$di]=$Im;}$Jm=$W[$Zg];$Xg=xxtea_mx($Jm,$Im,$Lk,$t[$di&3^$Zc]);$Im=int32($W[0]-$Xg);$W[0]=$Im;$Lk=int32($Lk-0x9E3779B9);}return
long2str($W,true);}const
ENCRYPTION_GCM='aes-256-gcm';const
ENCRYPTION_CBC='aes-256-cbc';const
ENCRYPTION_TAG_LENGTH=16;const
ENCRYPTION_HMAC_LENGTH=64;function
generate_iv($u){if(function_exists('random_bytes')){try{return
random_bytes($u);}catch(Exception$Zc){}}return
openssl_random_pseudo_bytes($u);}function
hash_key($t){return
substr(hash('sha512',$t,true),0,32);}function
aes_encrypt_string($_i,$t){$Ng=PHP_VERSION_ID>=70100&&in_array(ENCRYPTION_GCM,openssl_get_cipher_methods())?ENCRYPTION_GCM:ENCRYPTION_CBC;$t=hash_key($t);$Af=generate_iv(openssl_cipher_iv_length($Ng)?:16);if($Ng==ENCRYPTION_GCM)$xb=openssl_encrypt($_i,$Ng,$t,OPENSSL_RAW_DATA,$Af,$jl,"",ENCRYPTION_TAG_LENGTH);else{$xb=openssl_encrypt($_i,$Ng,$t,OPENSSL_RAW_DATA,$Af);$jl=hash_hmac("sha512",$Af.$xb,$t,true);}if($xb===false)return
false;return$Af.$jl.$xb;}function
aes_decrypt_string($e,$t){$Ng=PHP_VERSION_ID>=70100&&in_array(ENCRYPTION_GCM,openssl_get_cipher_methods())?ENCRYPTION_GCM:ENCRYPTION_CBC;$Bf=openssl_cipher_iv_length($Ng)?:16;$kl=$Ng==ENCRYPTION_GCM?ENCRYPTION_TAG_LENGTH:ENCRYPTION_HMAC_LENGTH;if(strlen($e)<$Bf+$kl)return
false;$t=hash_key($t);$Af=substr($e,0,$Bf);$jl=substr($e,$Bf,$kl);$xb=substr($e,$Bf+$kl);if($Af===false||$jl===false||$xb===false)return
false;if($Ng==ENCRYPTION_GCM)return
openssl_decrypt($xb,$Ng,$t,OPENSSL_RAW_DATA,$Af,$jl);else{$Ne=hash_hmac('sha512',$Af.$xb,$t,true);if(!hash_equals($jl,$Ne))return
false;return
openssl_decrypt($xb,$Ng,$t,OPENSSL_RAW_DATA,$Af);}}function
encrypt_string($_i,$t){if($_i=="")return"";if(extension_loaded('openssl'))return
aes_encrypt_string($_i,$t);else
return
xxtea_encrypt_string($_i,$t);}function
decrypt_string($e,$t){if($e=="")return"";if(extension_loaded('openssl'))return
aes_decrypt_string($e,$t);else
return
xxtea_decrypt_string($e,$t);}$xi=[];if($_COOKIE["neo_permanent"]){foreach(explode(" ",$_COOKIE["neo_permanent"])as$X){list($t)=explode(":",$X);$xi[$t]=$X;}}function
validate_server_input(array&$xi){$N=preg_replace('~:/[-\w.][-\w.:/]*$~D',"",SERVER);if($N=="")return;if(!preg_match('~^[^:]+://~',$N))$N="https://$N";$ri=parse_url($N);if(!$ri)auth_error($xi);if(isset($ri['user'])||isset($ri['pass'])||isset($ri['query'])||isset($ri['fragment']))auth_error($xi);if(isset($ri['scheme'])&&!preg_match('~^(https?)$~i',$ri['scheme']))auth_error($xi);$Qe=$ri['host'].(isset($ri['path'])?$ri['path']:'');if(!is_server_host_valid($Qe))auth_error($xi);if(isset($ri['port'])&&($ri['port']<1024||$ri['port']>65535))auth_error($xi,lang(133));}if(!function_exists('AdminNeo\is_server_host_valid')){function
is_server_host_valid($Qe){return
strpos($Qe,'/')===false;}}function
build_http_url($N,$V,$F,$vc,$uc=null){if(!preg_match('~^(https?://)?([^:]*)(:\d+)?$~',rtrim($N,'/'),$z))return
null;return($z[1]?:"http://").($V!==""||$F!==""?urlencode($V).":".urlencode($F)."@":"").($z[2]!==""?$z[2]:$vc).(isset($z[3])?$z[3]:($uc?":$uc":""));}function
add_invalid_login(){$Xa=get_temp_dir()."/adminneo-invalid";$l=null;foreach(glob("$Xa*")?:[$Xa]as$m){$l=open_file_with_lock($m);if($l)break;}if(!$l){$l=open_file_with_lock("$Xa-".Random::strongKey());if(!$l)return;}$sf=json_decode(stream_get_contents($l),true);$vl=time();if($sf){foreach($sf
as$tf=>$X){if($X[0]<$vl)unset($sf[$tf]);}}$rf=&$sf[Admin::get()->getBruteForceKey()];if(!$rf)$rf=[$vl+30*60,0];$rf[1]++;write_and_unlock_file($l,json_encode($sf));}function
check_invalid_login(array&$xi){$Xa=get_temp_dir()."/adminneo-invalid";$sf=[];foreach(glob("$Xa*")as$m){$l=open_file_with_lock($m);if($l){$sf=json_decode(stream_get_contents($l),true);unlock_file($l);break;}}$rf=($sf?$sf[Admin::get()->getBruteForceKey()]:[]);$kh=($rf&&$rf[1]>29?$rf[0]-time():0);if($kh>0)auth_error($xi,lang(134,ceil($kh/60)));}function
connect_to_db(array&$xi){if(Admin::get()->getConfig()->hasServers()&&!Admin::get()->getConfig()->getServer(SERVER))auth_error($xi);$d=connect(true,$i);if(!$d)connection_error(nl2br(h($i)),$xi);return$d;}function
authenticate(array&$xi){$I=Admin::get()->authenticate($_GET["username"],get_password());if($I!==true)connection_error($I,$xi);}function
connection_error($i,array&$xi){$i=$i?:lang(3);if(preg_match('~^ +| +$~',get_password()))$i
.="<br>".lang(135);auth_error($xi,$i);}Admin::get()->init();$Na=isset($_POST["auth"])?$_POST["auth"]:null;if($Na){session_regenerate_id();$N=isset($Na["server"])?$Na["server"]:"";$fk=Admin::get()->getConfig()->getServer($N);$Qc=$fk?$fk->getDriver():(isset($Na["driver"])?$Na["driver"]:"");$N=$fk?$N:trim($N);$V=isset($Na["username"])?$Na["username"]:"";$F=isset($Na["password"])?$Na["password"]:"";if($fk&&$fk->hasCredentials()&&$V==""&&$F==""){$V=$fk->getUsername();$F=$fk->getPassword();}$g=$fk?$fk->getDatabase():(isset($Na["db"])?$Na["db"]:"");save_login($Qc,$N,$V,$F,$g);if($Na["permanent"]){$t=implode("-",array_map("base64_encode",[$Qc,$N,$V,$g]));$Pi=Admin::get()->getPrivateKey(true);$kd=$Pi?encrypt_string($F,$Pi):false;$xi[$t]="$t:".base64_encode($kd?:"");cookie("neo_permanent",implode(" ",$xi));}if(count($_POST)==1||DRIVER!=$Qc||SERVER!=$N||$_GET["username"]!==$V||DB!=$g)redirect(auth_url($Qc,$N,$V,$g));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){foreach(["pwds","db","dbs","queries"]as$t)set_session($t,null);unset_permanent($xi);redirect(SERVER_HOME_URL,lang(136));}elseif($xi&&!$_SESSION["pwds"]){session_regenerate_id();$Pi=Admin::get()->getPrivateKey();foreach($xi
as$t=>$X){list(,$wb)=explode(":",$X);list($Qc,$N,$V,$g)=array_map("base64_decode",explode("-",$t));$F=$Pi?decrypt_string(base64_decode($wb),$Pi):false;save_login($Qc,$N,$V,$F,$g);}}function
unset_permanent(array&$xi){foreach($xi
as$t=>$X){list($Qc,$N,$V,$g)=array_map("base64_decode",explode("-",$t));if($Qc==DRIVER&&$N==SERVER&&$V==$_GET["username"]&&$g==DB)unset($xi[$t]);}cookie("neo_permanent",implode(" ",$xi));}function
auth_error(array&$xi,$i=null){$jk=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$jk]||$_GET[$jk])&&!$_SESSION["token"])$i=lang(137);else{restart_session();add_invalid_login();$F=get_password();if($F!==null){if($F===false)$i=lang(138);delete_login(DRIVER,SERVER,$_GET["username"]);}unset_permanent($xi);}}if(!$_COOKIE[$jk]&&$_GET[$jk]&&ini_bool("session.use_only_cookies"))$i=lang(139);if(!$i)$i=lang(3);Admin::get()->addError($i);print_login_page();}function
print_login_page(){$gi=session_get_cookie_params();cookie("neo_key",($_COOKIE["neo_key"]?:Random::strongKey()),$gi["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header(lang(31),null);echo"<form action='' method='post'>\n","<div>";if(print_hidden_fields($_POST,["auth"]))echo"<p class='message'>".lang(140)."\n";echo"</div>\n";Admin::get()->printLoginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!DRIVER)print_login_page();if(isset($_GET["username"])&&!defined('AdminNeo\DRIVER_EXTENSION')){Admin::get()->addError(lang(141,implode(", ",Drivers::getExtensions(DRIVER))));unset($_SESSION["pwds"][DRIVER]);unset_permanent($xi);page_header(lang(142),false);page_footer("auth");exit;}if(!isset($_GET["username"])||get_password()===null)print_login_page();validate_server_input($xi);check_invalid_login($xi);Admin::get()->getConfig()->applyServer(SERVER);$d=connect_to_db($xi);authenticate($xi);create_driver($d);if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){Admin::get()->addError(lang(143));page_header(lang(6));page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($Na&&$_POST["token"])$_POST["token"]=get_token();if($_POST){if(!verify_token()){$hf="max_input_vars";$Bg=ini_get($hf);if(extension_loaded("suhosin")){foreach(["suhosin.request.max_vars","suhosin.post.max_vars"]as$t){$X=ini_get($t);if($X&&(!$Bg||$X<$Bg)){$hf=$t;$Bg=$X;}}}if(!$_POST["token"]&&$Bg)Admin::get()->addError(lang(144,"'$hf'"));else
Admin::get()->addError(lang(143).' '.lang(145));$_POST=[];}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){$i=lang(146,"'post_max_size'");if(isset($_GET["sql"]))$i
.=' '.lang(147);Admin::get()->addError($i);}if(isset($_GET["settings"])){$O=Admin::get()->getSettings();$mk=array_merge(Admin::get()->getSettingsRows(1),Admin::get()->getSettingsRows(2),Admin::get()->getSettingsRows(3));if($_POST){$gi=[];foreach($mk
as$t=>$K){if(isset($_POST[$t])){$dm=$_POST[$t]===""||(is_array($_POST[$t])&&in_array("",$_POST[$t]));$gi[$t]=(!$dm?$_POST[$t]:null);}}$O->updateParameters($gi);redirect(remove_from_uri());}$T=lang(132);page_header($T,[$T]);echo"<form id='settings' action='' method='post'>\n","<table class='box'>\n";foreach($mk
as$K)echo$K;echo"</table>\n","<p>","<input type='submit' value='".lang(113),"' class='button default hidden'>",input_token(),"</p>\n","</form>\n",script("initSettingsForm();");page_footer();exit;}if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];if(!(DB!=""?Connection::get()->selectDatabase(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!=""){Admin::get()->addError(lang(148));header("HTTP/1.1 404 Not Found");page_header(lang(30).": ".h(DB),true);}else{if($_POST["db"])queries_redirect(substr(ME,0,-1),lang(149),drop_databases($_POST["db"]));$T=h(Drivers::get(DRIVER).": ".Admin::get()->getServerName(SERVER));page_header($T,false);$jg=['privileges'=>[lang(72),"users"],'processlist'=>[lang(150),"list"],'variables'=>[lang(151),"variable"],'status'=>[lang(152),"status"],];$kg="";foreach($jg
as$t=>$X){if(support($t))$kg
.="<a href='".h(ME)."$t='>".icon($X[1])."$X[0]</a>";}if($kg)echo"<p class='links top-links'>$kg</p>\n";echo"<p>".lang(153,Drivers::get(DRIVER),"<b>".h(Connection::get()->getVersion())."</b>","<b>".DRIVER_EXTENSION."</b>")."\n","<p>".lang(154,"<b>".h(logged_user())."</b>")."\n";$f=Admin::get()->getDatabases();if($f){$Nj=support("scheme");$Da=collations();echo"<form action='' method='post'>\n","<div class='table-footer-parent'>\n","<div class='scrollable'>\n","<table class='checkable'>\n","<thead><tr>".(support("database")?"<td>":"")."<th>".lang(30).(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".lang(155)."</a>":"")."<td>".lang(45)."<td>".lang(156)."<td>".lang(157)." - <a href='".h(ME)."dbsize=1'>".lang(158)."</a>".script("qsl('a').onclick = partial(ajaxSetHtml, '".js_escape(ME)."script=connect');","")."</thead>\n","<tbody>\n";$f=($_GET["dbsize"]?count_tables($f):array_flip($f));foreach($f
as$g=>$S){$zj=h(ME)."db=".urlencode($g);$q=h("Db-".$g);echo"<tr>".(support("database")?"<td class='actions'>".checkbox("db[]",$g,in_array($g,(array)$_POST["db"]),"","","",$q):""),"<th><a href='$zj' id='$q'>".h($g)."</a>";$Bb=h(db_collation($g,$Da));echo"<td>".(support("database")?"<a href='$zj".($Nj?"&amp;ns=":"")."&amp;database=' title='".lang(69)."'>$Bb</a>":$Bb),"<td align='right'><a href='$zj&amp;schema=' id='tables-".h($g)."' title='".lang(71)."'>".($_GET["dbsize"]?$S:"?")."</a>","<td align='right' id='size-".h($g)."'>".($_GET["dbsize"]?db_size($g):"?"),"\n";}echo"</tbody>\n",script("mixin(qsl('tbody'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});"),"</table>\n","</div>\n";if(support("database"))echo"<div class='table-footer'><div class='field-sets'>\n","<fieldset><legend>",lang(159)," <span id='selected'></span></legend><div class='fieldset-content'>\n",input_hidden("all"),script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^db/)); };"),"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(),"\n","</div></fieldset>\n","</div></div>\n",script("initTableFooter()");echo"</div>\n",input_token(),"</form>\n",script("tableCheck();");}}echo'<p class="links"><a href="'.h(ME).'database=">'.icon("database-add").lang(75)."</a>\n";page_footer("db");exit;}if(isset($_GET["select"])&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$k=fields($a);header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$_GET["where"])).".".friendly_url($_GET["field"]));$M=[idf_escape($_GET["field"])];$I=Driver::get()->select($a,$M,[where($_GET,$k)],$M);$K=($I?$I->fetchRow():[]);echo
Connection::get()->formatValue($K[0],$k[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$k=fields($a);if(!$k)Admin::get()->addError(error()?:lang(78));$R=table_status1($a,true);$A=Admin::get()->getTableName($R);$yj=[];foreach($k
as$t=>$j)$yj+=$j["privileges"];$T=$k&&is_view($R)?$R['Engine']=='materialized view'?lang(161):lang(162):lang(8);$Zk=$A!=""?$A:h($a);page_header("$T: $Zk",[$Zk]);$nf=null;if(isset($yj["insert"])||!support("table"))$nf=[];Admin::get()->printTableMenu($R,$nf);$ff=[];if(!preg_match("~sqlite|mssql|pgsql~",DIALECT)&&isset($R["Engine"]))$ff[]=lang(163).": ".h($R["Engine"]);if(isset($R["Collation"]))$ff[]=lang(45).": ".h($R["Collation"]);if($ff)echo"<p>",implode(", ",$ff),"</p>";if($k)Admin::get()->printTableStructure($k);$Kb=$R["Comment"];if($Kb!="")echo"<p class='keep-lines'>",lang(46),": ",Admin::get()->formatComment($Kb),"</p>\n";if(!is_view($R))$bd='<p class="links"><a href="'.h(ME).'create='.urlencode($a).'">'.icon("edit").lang(35)."</a>\n";elseif(support("view"))$bd='<p class="links"><a href="'.h(ME).'view='.urlencode($a).'">'.icon("edit").lang(36)."</a>\n";else$bd="";if($ff||$k||$Kb!="")echo$bd;$hi=Driver::get()->getParentTables($a);if($hi){echo"<h2>".lang(164)."</h2>\n";Admin::get()->printRelatedTables($hi);}if(Driver::get()->getPartitionBy()&&str_contains(isset($R["Create_options"])?$R["Create_options"]:"","partitioned")){$qi=Driver::get()->getPartitionsInfo($a);if($qi){echo"<h2 id='partitions'>".lang(49)."</h2>\n";Admin::get()->printTablePartitions($qi);if(DIALECT!="pgsql")echo$bd;}}$gf=Driver::get()->getInheritedTables($a);if($gf){echo"<h2 id='inherited-by'>".lang(165)."</h2>\n";Admin::get()->printRelatedTables($gf);}if(support("indexes")&&Driver::get()->supportsIndex($R)){echo"<h2 id='indexes'>".lang(166)."</h2>\n";$s=indexes($a);if($s)Admin::get()->printTableIndexes($s,$R);echo'<p class="links"><a href="'.h(ME).'indexes='.urlencode($a).'">'.icon("edit").lang(167)."</a>\n";}if(!is_view($R)){if(fk_support($R)){echo"<h2 id='foreign-keys'>".lang(90)."</h2>\n";$ee=foreign_keys($a);if($ee){echo"<table>\n","<thead><tr><th>".lang(168)."<td>".lang(169)."<td>".lang(93)."<td>".lang(92)."<td></thead>\n";foreach($ee
as$A=>$n)echo"<tr title='".h($A)."'>","<th><i>".implode("</i>, <i>",array_map('AdminNeo\h',$n["source"]))."</i>","<td><a href='".h($n["db"]!=""?preg_replace('~db=[^&]*~',"db=".urlencode($n["db"]),ME):($n["ns"]!=""?preg_replace('~ns=[^&]*~',"ns=".urlencode($n["ns"]),ME):ME))."table=".urlencode($n["table"])."'>".($n["db"]!=""&&$n["db"]!=DB?"<b>".h($n["db"])."</b>.":"").($n["ns"]!=""&&$n["ns"]!=$_GET["ns"]?"<b>".h($n["ns"])."</b>.":"").h($n["table"])."</a>","(<i>".implode("</i>, <i>",array_map('AdminNeo\h',$n["target"]))."</i>)","<td>".h($n["on_delete"]),"<td>".h($n["on_update"]),'<td><a href="'.h(ME.'foreign='.urlencode($a).'&name='.urlencode($A)).'">'.lang(170).'</a>',"\n";echo"</table>\n";}echo'<p class="links"><a href="'.h(ME).'foreign='.urlencode($a).'">'.icon("add").lang(171)."</a>\n";}if(support("check")){echo"<h2 id='checks'>".lang(172)."</h2>\n";$rb=Driver::get()->checkConstraints($a);if($rb){echo"<table cellspacing='0'>\n";foreach($rb
as$t=>$X)echo"<tr title='".h($t)."'>","<td><code class='jush-".DIALECT."'>".h($X),"<td><a href='".h(ME.'check='.urlencode($a).'&name='.urlencode($t))."'>".lang(170)."</a>","\n";echo"</table>\n";}echo'<p class="links"><a href="'.h(ME).'check='.urlencode($a).'">'.icon("add").lang(173)."</a>\n";}}if(support(is_view($R)?"view_trigger":"trigger")){echo"<h2 id='triggers'>".lang(174)."</h2>\n";$Kl=triggers($a);if($Kl){echo"<table>\n";foreach($Kl
as$t=>$X)echo"<tr><td>".h($X[0])."<td>".h($X[1])."<th>".h($t)."<td><a href='".h(ME.'trigger='.urlencode($a).'&name='.urlencode($t))."'>".lang(170)."</a>\n";echo"</table>\n";}echo'<p class="links"><a href="'.h(ME).'trigger='.urlencode($a).'">'.icon("add").lang(175)."</a>\n";}}elseif(isset($_GET["schema"])){$yl=h(": ".DB.($_GET["ns"]?".$_GET[ns]":""));page_header(lang(71).$yl,[lang(71)]);$bl=[];$cl=[];$Nd=[];$pa=($_GET["schema"]?:$_COOKIE["neo_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$pa,$z,PREG_SET_ORDER);foreach($z
as$p=>$y){$bl[$y[1]]=[(float)$y[2],(float)$y[3]];$cl[]="\n\t'".js_escape($y[1])."': [ $y[2], $y[3] ]";}$Cl=0;$Wa=-1;$Lj=[];$lj=[];$Zf=[];$Ea=Driver::get()->getAllFields();foreach(table_status('',true)as$Q=>$R){if(is_view($R))continue;$G=0;$Lj[$Q]["fields"]=[];foreach(isset($Ea[$Q])?$Ea[$Q]:[]as$j){$G+=1.25;$Nd[$Q][$j["field"]]=$G;$Lj[$Q]["fields"][$j["field"]]=$j;}$Lj[$Q]["pos"]=(isset($bl[$Q])?$bl[$Q]:[$Cl,0]);foreach(Admin::get()->getForeignKeys($Q)as$X){if(!$X["db"]){$Xf=$Wa;if((isset($bl[$Q][1])?$bl[$Q][1]:0)||(isset($bl[$X["table"]][1])?$bl[$X["table"]][1]:0))$Xf=min(floatval(isset($bl[$Q][1])?$bl[$Q][1]:0),floatval(isset($bl[$X["table"]][1])?$bl[$X["table"]][1]:0))-1;else$Wa-=.1;while($Zf[(string)$Xf])$Xf-=.0001;$Lj[$Q]["references"][$X["table"]][(string)$Xf]=[$X["source"],$X["target"]];$lj[$X["table"]][$Q][(string)$Xf]=$X["target"];$Zf[(string)$Xf]=true;}}$Cl=max($Cl,$Lj[$Q]["pos"][0]+2.5+$G);}echo"<div id='schema' style='height: {$Cl}em;'>\n","<script",nonce(),">\n","gid('schema').onselectstart = () => false;\n","const tablePos = {",implode(",",$cl),"\n};\n","const em = gid('schema').offsetHeight / $Cl;\n","document.onmousemove = schemaMousemove;\n","document.onmouseup = partialArg(schemaMouseup, '",js_escape(DB),"');\n","</script>\n";foreach($Lj
as$A=>$Q){echo"<div class='table' style='top: ".$Q["pos"][0]."em; left: ".$Q["pos"][1]."em;'>",'<a href="'.h(ME).'table='.urlencode($A).'"><b>'.h($A)."</b></a>",script("qsl('div').onmousedown = schemaMousedown;");foreach($Q["fields"]as$j){$X='<span '.type_class($j["type"]).' title="'.h($j["type"].($j["length"]?"($j[length])":"").($j["null"]?" NULL":'')).'">'.h($j["field"]).'</span>';echo"<br>".($j["primary"]?"<i>$X</i>":$X);}foreach((array)$Q["references"]as$ml=>$nj){foreach($nj
as$Xf=>$hj){$Yf=$Xf-(isset($bl[$A][1])?$bl[$A][1]:0);$p=0;foreach($hj[0]as$uk){echo"\n<div class='references' title='",h($ml),"' id='refs$Xf-$p' style='left: {$Yf}em; top: ",$Nd[$A][$uk],"em; padding-top: .5em;'>","<div style='border-top: 1px solid Gray; width: ".(-$Yf)."em;'></div>","</div>";$p++;}}}foreach((array)$lj[$A]as$ml=>$nj){foreach($nj
as$Xf=>$c){$Yf=$Xf-(isset($bl[$A][1])?$bl[$A][1]:0);$p=0;foreach($c
as$ll){echo"\n<div class='references' title='",h($ml),"' id='refd$Xf-$p' style='left: {$Yf}em; top: ".$Nd[$A][$ll]."em; height: 1.25em;'>","<svg style='width: 1em; height: 1em; float: right;' viewBox='0 0 22 22' fill='currentColor'><path d='M11,19l10,-8l-10,-8l0,16Z'/></svg>","<div style='height: .5em; border-bottom: 1px solid Gray; width: ".(-$Yf)."em;'></div>","</div>";$p++;}}}echo"\n</div>\n";}foreach($Lj
as$A=>$Q){foreach((array)$Q["references"]as$ml=>$nj){if($Lj[$ml]){foreach($nj
as$Xf=>$hj){$Og=$Cl;$zg=-10;foreach($hj[0]as$t=>$uk){$Fi=$Q["pos"][0]+$Nd[$A][$uk];$Gi=$Lj[$ml]["pos"][0]+$Nd[$ml][$hj[1][$t]];$Og=min($Og,$Fi,$Gi);$zg=max($zg,$Fi,$Gi);}echo"<div class='references' id='refl$Xf' style='left: $Xf"."em; top: $Og"."em; padding: .5em 0;'><div style='border-right: 1px solid Gray; margin-top: 1px; height: ".($zg-$Og)."em;'></div></div>\n";}}}}echo"</div>\n","<p class='links'>","<a href='",(ME."schema=".urlencode($pa)),"' id='schema-link'>",lang(176),"</a>","</p>\n";}elseif(isset($_GET["dump"])){$a=$_GET["dump"];$O=Admin::get()->getSettings();if($_POST){$O->updateParameters(["dumpFormat"=>$_POST["format"],"dumpDbStyle"=>$_POST["db_style"],"dumpTypes"=>isset($_POST["types"])?$_POST["types"]:(support("type")?"":null),"dumpRoutines"=>isset($_POST["routines"])?$_POST["routines"]:(support("routine")?"":null),"dumpEvents"=>isset($_POST["events"])?$_POST["events"]:(support("event")?"":null),"dumpTableStyle"=>$_POST["table_style"],"dumpAutoIncrement"=>isset($_POST["auto_increment"])?$_POST["auto_increment"]:"","dumpTriggers"=>isset($_POST["triggers"])?$_POST["triggers"]:(support("trigger")?"":null),"dumpDataStyle"=>$_POST["data_style"],"dumpOutput"=>$_POST["output"],]);if(DB!="")$f=[DB];else{$f=isset($_POST["databases"])?$_POST["databases"]:[];if(is_string($f))$f=explode("\n",rtrim(str_replace("\r","",$f),"\n"));}$Mj=isset($_POST["schemas"])?$_POST["schemas"]:[];$S=array_flip(isset($_POST["tables"])?$_POST["tables"]:[])+array_flip(isset($_POST["data"])?$_POST["data"]:[]);if(count($S)==1)$Ue=key($S);elseif(count($Mj)==1)$Ue=$Mj[0];elseif(count($f)==1)$Ue=$f[0];else$Ue=Admin::get()->getServerName(SERVER,true,"server");$Bd=dump_headers($Ue,DB==""||$_GET["ns"]===""||count($S)>1);$yf=preg_match('~sql~',$_POST["format"]);$mc=$yf&&$_POST["data_style"]&&!$_POST["table_style"]&&DIALECT!="sql";if($yf){echo"-- AdminNeo ".VERSION." ".Drivers::get(DRIVER)." ".Connection::get()->getVersion()." dump\n\n";if(DIALECT=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";Connection::get()->query("SET time_zone = '+00:00'");Connection::get()->query("SET sql_mode = ''");}}$Hk=$_POST["db_style"];foreach($f
as$g){Admin::get()->dumpDatabase($g);if(Connection::get()->selectDatabase($g)){if($yf){if($Hk)echo
create_database_sql($g,$Hk),use_sql($g,$Hk)."\n";$ai="";if($_POST["types"]){foreach(types()as$q=>$U){$od=type_values($q);if($od)$ai
.=($Hk!='DROP+CREATE'?"DROP TYPE IF EXISTS ".idf_escape($U).";;\n":"")."CREATE TYPE ".idf_escape($U)." AS ENUM ($od);\n\n";else$ai
.="-- Could not export type $U\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$A=$K["ROUTINE_NAME"];$_j=$K["ROUTINE_TYPE"];$cc=create_routine($_j,["name"=>$A]+routine($K["SPECIFIC_NAME"],$_j));set_utf8mb4($cc);$ai
.=($Hk!='DROP+CREATE'?"DROP $_j IF EXISTS ".idf_escape($A).";;\n":"")."$cc;\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$cc=remove_definer(Connection::get()->getValue("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($cc);$ai
.=($Hk!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$cc;;\n\n";}}echo($ai&&DIALECT=='sql'?"DELIMITER ;;\n\n$ai"."DELIMITER ;\n\n":$ai);}if($_POST["table_style"]||$_POST["data_style"]){foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?[""]:Admin::get()->getSchemas(true)))as$Lj){if($Lj!="")set_schema($Lj);$hl=table_status('',true);$al=array_keys($hl);$Gc=false;if($mc&&$al){$mj=[];foreach($al
as$A){if(!is_view($hl[$A])&&(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["data"]))){foreach(foreign_keys($A)as$n)$mj[$A][]=$n["table"];}}$Qh=dump_table_order($al,$mj);if($Qh)$al=$Qh;else$Gc=function_exists('AdminNeo\foreign_key_checks_sql');}if($Gc)echo
foreign_key_checks_sql(false)."\n";$um=[];foreach($al
as$A){$R=$hl[$A];$Q=(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["tables"]));$e=(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["data"]));if($Q||$e){$_l=null;if($Bd=="tar"){$_l=new
TmpFile();ob_start([$_l,'write'],1e5);}$dc=($Q?$_POST["table_style"]:"");Admin::get()->dumpTable($A,$dc,(is_view($R)?2:0));if(is_view($R)&&$Bd!="tar")$um[]=$A;elseif($e){$k=fields($A);Admin::get()->dumpData($A,$_POST["data_style"],"SELECT *".convert_fields($k,$k)." FROM ".table($A));if($yf&&!$dc&&$_POST["auto_increment"]&&function_exists('AdminNeo\restart_sequences_sql'))echo"\n".restart_sequences_sql($A);}if($yf&&$_POST["triggers"]&&$Q&&($Kl=trigger_sql($A)))echo"\nDELIMITER ;;\n$Kl\nDELIMITER ;\n";if($Bd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$g/")."$A.csv",$_l);}elseif($yf)echo"\n";}}if($Gc)echo
foreign_key_checks_sql(true)."\n";if($_POST["table_style"]&&function_exists('AdminNeo\foreign_keys_sql')){foreach($hl
as$A=>$R){$Q=(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["tables"]));if($Q&&!is_view($R))echo
foreign_keys_sql($A);}}foreach($um
as$sm)Admin::get()->dumpTable($sm,$_POST["table_style"],1);if($Bd=="tar")echo
pack("x512");}}}}if($yf)echo"-- ".gmdate("Y-m-d H:i:s e")."\n";exit;}$A=DB!=""?h(DB):h(Admin::get()->getServerName(SERVER));page_header(lang(74).": $A",($_GET["export"]!=""?["table"=>$_GET["export"]]:[lang(74)]));echo"<form action='' method='post'>\n","<table class='box'>\n";$qc=['','USE','DROP+CREATE','CREATE'];$el=['','DROP+CREATE','CREATE'];$nc=['','TRUNCATE+INSERT','INSERT'];if(DIALECT=="sql")$nc[]='INSERT+UPDATE';echo"<tr><th>",lang(177),"</th><td>",html_radios("format",Admin::get()->getDumpFormats(),$O->getParameter("dumpFormat","sql")),"</td></tr>\n";if(DIALECT!="sqlite"){echo"<tr><th id='label-db'>",lang(30),"</th>","<td>",html_select('db_style',$qc,$O->getParameter("dumpDbStyle",DB==""?"CREATE":""),"","label-db"),"<span class='labels'>";if(support("routine"))echo
checkbox("routines",1,$O->getParameter("dumpRoutines",$_GET["dump"]==""?"1":""),lang(178));if(support("event"))echo
checkbox("events",1,$O->getParameter("dumpEvents",$_GET["dump"]==""?"1":""),lang(179));echo"</span></td></tr>";}echo"<tr><th id='label-tables'>",lang(156),"</th><td>",html_select('table_style',$el,$O->getParameter("dumpTableStyle","DROP+CREATE"),"","label-tables")," <span class='labels'>",checkbox("auto_increment",1,$O->getParameter("dumpAutoIncrement"),lang(47));if(support("trigger"))echo
checkbox("triggers",1,$O->getParameter("dumpTriggers","1"),lang(174));echo"</span></td></tr>","<tr><th id='label-data'>",lang(180),"</th><td>",html_select("data_style",$nc,$O->getParameter("dumpDataStyle","INSERT"),"","label-data"),"</td></tr>","<tr><th>",lang(181),"</th><td>",html_radios("output",Admin::get()->getDumpOutputs(),$O->getParameter("dumpOutput","file")),"</td></tr>\n","</table>\n","<p>","<input type='submit' class='button default' value='",lang(74),"'>",input_token(),"</p>\n","<table>\n",script("qsl('table').onclick = dumpClick;");$Li=[];if(DB!=""&&$_GET["ns"]===""){echo"<thead><tr><th>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly'>".lang(182)."</label>".script("gid('check-schemas').onclick = partial(formCheck, /^schemas\\[/);",""),"</thead>\n";foreach(Admin::get()->getSchemas()as$Lj)echo"<tr><td>".checkbox("schemas[]",$Lj,true,$Lj,"","block")."\n";}elseif(DB!=""){$tb=($a!=""?"":" checked");echo"<thead><tr>","<th><label class='block'><input type='checkbox' id='check-tables'$tb class='jsonly'>".lang(8)."</label>".script("gid('check-tables').onclick = partial(formCheck, /^tables\\[/);",""),"<th class='right'><label class='block'>".lang(180)."<input type='checkbox' id='check-data'$tb class='jsonly'></label>".script("gid('check-data').onclick = partial(formCheck, /^data\\[/);",""),"</thead>\n";$um="";$gl=tables_list();foreach($gl
as$A=>$U){$Ki=preg_replace('~_.*~','',$A);$tb=($a==""||$a==(substr($a,-1)=="%"?"$Ki%":$A));$Oi="<tr><td>".checkbox("tables[]",$A,$tb,$A,"","block");if($U!==null&&!preg_match('~table~i',$U))$um
.="$Oi\n";else
echo"$Oi<td class='right'><label class='block'><span id='Rows-".h($A)."'></span>".checkbox("data[]",$A,$tb)."</label>\n";$Li[$Ki]++;}echo$um;if($gl)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$f=Admin::get()->getDatabases();echo"<thead><tr><th>","<label class='block'>".($f?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly'>".script("gid('check-databases').onclick = partial(formCheck, /^databases\\[/);",""):"").lang(30)."</label>","</thead>\n";if($f){foreach($f
as$g){if(!information_schema($g)){$Ki=preg_replace('~_.*~','',$g);echo"<tr><td>".checkbox("databases[]",$g,$a==""||$a=="$Ki%",$g,"","block")."\n";$Li[$Ki]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo"</table>\n","</form>\n";$jg=[];foreach($Li
as$t=>$X){if($t!=""&&$X>1)$jg[]="<a href='".h(ME)."dump=".urlencode("$t%")."'>".icon("check").h($t)."*</a>";}if($jg)echo"<p class='links'>",implode("",$jg),"</p>\n";}elseif(isset($_GET["privileges"])){$yl=DB!=""?h(": ".DB):"";page_header(lang(72).$yl,[lang(72)]);echo'<p class="links top-links"><a href="',h(ME),'user=">',icon("user-add"),lang(183),"</a></p>\n";$I=Connection::get()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$ue=$I;if(!$I)$I=Connection::get()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''>\n";hidden_fields_get();echo
input_hidden("db",DB);if(!$ue)echo
input_hidden("grant");echo"\n","<div class='scrollable'>\n","<table class='checkable'>\n","<thead><tr><th>".lang(28)."<th>".lang(5)."<th></thead>\n";while($K=$I->fetchAssoc())echo'<tr><td>'.h($K["User"])."<td>".h($K["Host"]).'<td><a href="'.h(ME.'user='.urlencode($K["User"]).'&host='.urlencode($K["Host"])).'">'.lang(38)."</a>\n";if(!$ue||DB!="")echo"<tr><td><input class='input' name='user' autocapitalize='off'><td><input class='input' name='host' value='localhost' autocapitalize='off'><td><input type='submit' class='button' value='".lang(38)."'>\n";echo"</table>\n","</div>\n","</form>\n";}elseif(isset($_GET["sql"])){$O=Admin::get()->getSettings();if($_POST["export"]){$O->updateParameters(["exportFormat"=>$_POST["format"],"exportOutput"=>$_POST["output"],]);dump_headers("sql");Admin::get()->dumpTable("","");Admin::get()->dumpData("","table",$_POST["query"]);exit;}restart_session();$Me=&get_session("queries");$Le=&$Me[DB];if($_POST["clear"]){$Le=[];redirect(remove_from_uri("history"));}stop_session();$T=isset($_GET["import"])?lang(73):lang(40);page_header($T,[$T]);$gg="--".(DIALECT=="sql"?" ":"");if($_POST){$le=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$Ye=Admin::get()->getImportFilePath();if($Ye){if(file_exists($Ye))$le=fopen($Ye,"rb");elseif(file_exists("$Ye.gz"))$le=fopen("compress.zlib://$Ye.gz","rb");}$H=$le?fread($le,1e6):false;}else$H=get_file("sql_file",true,";");if(is_string($H)){if(($Eg=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($Eg,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$Wi=$H.(preg_match("~;[ \t\r\n]*\$~",$H)?"":";");if(!$Le||first(end($Le))!=$Wi){restart_session();$Le[]=[$Wi,time()];set_session("queries",$Me);stop_session();}}$wk="(?:\\s|/\\*[\s\S]*?\\*/|(?:#|$gg)[^\n]*\n?|--\r?\n)";$zc=";";$_c=1;$sh=0;$hd=true;$Ub=connect();if($Ub&&DB!=""){$Ub->selectDatabase(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$Ub);}$Jb=0;$qd=[];$ii='[\'"'.(DIALECT=="sql"?'`#':(DIALECT=="sqlite"?'`[':(DIALECT=="mssql"?'[':''))).']|/\*|'.$gg.'|$'.(DIALECT=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$Dl=microtime(true);$Yc=Admin::get()->getDumpFormats();unset($Yc["sql"]);while($H!=""){if(!$sh&&preg_match("~^$wk*+DELIMITER\\s+(\\S+)~i",$H,$y)){$zc=preg_quote($y[1]);$_c=strlen($y[1]);$he=Admin::get()->formatSqlCommandQuery(trim($y[0]));if($he!="")echo"<pre><code class='jush-".DIALECT."'>$he</code></pre>\n";$H=substr($H,strlen($y[0]));}elseif(!$sh&&DIALECT=="pgsql"&&preg_match("~^($wk*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$y)){$zc="\n\\\\\\.\r?\n";$_c=3;$sh=strlen($y[0]);}else{preg_match("($zc\\s*|$ii)",$H,$y,PREG_OFFSET_CAPTURE,$sh);list($je,$G)=$y[0];if(!$je&&$le&&!feof($le))$H
.=fread($le,1e5);else{if(!$je&&rtrim($H)=="")break;$sh=$G+strlen($je);if($je&&!preg_match("(^$zc)",$je)){$ib=Driver::get()->hasCStyleEscapes()||(DIALECT=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$vi='(';if($je=='/*')$vi
.='\*/';elseif($je=='[')$vi
.=']';elseif(preg_match("~^$gg|^#~",$je))$vi
.="\n";else$vi
.=preg_quote($je).($ib?"|\\\\.":"");$vi
.='|$)s';while(preg_match($vi,$H,$y,PREG_OFFSET_CAPTURE,$sh)){$Dj=$y[0][0];if(!$Dj&&$le&&!feof($le))$H
.=fread($le,1e5);else{$sh=$y[0][1]+strlen($Dj);if(!isset($Dj[0])||$Dj[0]!="\\")break;}}}else{$hd=false;$Wi=substr($H,0,$G+$_c);$Jb++;$Oi="<pre id='sql-$Jb'><code class='jush-".DIALECT."'>".Admin::get()->formatSqlCommandQuery(trim($Wi))."</code></pre>\n";if(DIALECT=="sqlite"&&preg_match("~^$wk*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$Wi,$y)!==0){echo$Oi,"<p class='error'>".lang(184,preg_match('~ATTACH~i',$y[1])?'ATTACH':'VACUUM INTO')."\n";$qd[]=" <a href='#sql-$Jb'>$Jb</a>";if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$Oi;ob_flush();flush();}$Ak=microtime(true);if(Connection::get()->multiQuery($Wi)&&is_object($Ub)&&preg_match("~^$wk*+USE\\b~i",$Wi))$Ub->query($Wi);do{$I=Connection::get()->storeResult();if(Connection::get()->getError()){echo($_POST["only_errors"]?$Oi:""),"<p class='error'>",lang(185),(!empty(Connection::get()->getErrno())?" (".Connection::get()->getErrno().")":""),": ",error()."</p>\n";$qd[]=" <a href='#sql-$Jb'>$Jb</a>";if($_POST["error_stops"])break
2;}else{$vl=" <span class='time'>(".format_time($Ak).")</span>";$cd=(strlen($Wi)<1000?" <a href='".h(ME)."sql=".urlencode(trim($Wi))."'>".icon("edit").lang(38)."</a>":"");$aj=Connection::get()->getQueryInfo();$za=Connection::get()->getAffectedRows();$_m=($_POST["only_errors"]?null:Driver::get()->warnings());$Bm="warnings-$Jb";$Cm=$_m?"<a href='#$Bm' class='toggle'>".lang(39).icon_chevron_down()."</a>":null;$yd=$Th=null;$zd="explain-$Jb";$_d=false;$Ad="export-$Jb";$v=0;if(is_object($I)){if(!$_POST["only_errors"])echo"<div class='table-result'>\n";$v=(int)$_POST["limit"];$Th=print_select_result($I,$Ub,[],$v);if(!$_POST["only_errors"]){echo"<p class='links'>";$ph=$I->getRowsCount();echo($ph?($v&&$ph>$v?lang(186,$v):"").lang(187,$ph):""),$vl,$cd,$Cm;if($Ub&&preg_match("~^($wk|\\()*+SELECT\\b~i",$Wi)&&($yd=explain($Ub,$Wi)))echo"<a href='#$zd' class='toggle'>Explain".icon_chevron_down()."</a>";$_d=true;echo"<a href='#$Ad' class='toggle'>".lang(74).icon_chevron_down()."</a>","</p>\n";}}else{if(preg_match("~^$wk*+(CREATE|DROP|ALTER)$wk++(DATABASE|SCHEMA)\\b~i",$Wi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"]){echo"<p class='message' title='".h($aj)."'>",lang(188,$za),"$vl $cd";if($Cm)echo", $Cm";echo"</p>\n";}}if(!$_POST["only_errors"])echo
script("initToggles(qsl('p'));");if($_m)echo"<div id='$Bm' class='hidden'>\n$_m</div>\n";if($yd){echo"<div id='$zd' class='hidden explain'>\n";print_select_result($yd,$Ub,$Th);echo"</div>\n";}if($_d){echo"<form id='$Ad' action='' method='post' class='hidden'><p>\n",html_select("format",$Yc,$O->getParameter("exportFormat")),html_select("output",Admin::get()->getDumpOutputs(),$O->getParameter("exportOutput"))." ",input_hidden("query",$Wi),input_token()," <input type='submit' class='button' name='export' value='".lang(74)."'>";if(!$v)echo
script("qsl('input').onclick = partial(sqlExport, '".js_escape(ME)."set=export-settings');","");echo"</p></form>\n";}if(is_object($I)&&!$_POST["only_errors"])echo"</div>\n";}$Ak=microtime(true);}while(Connection::get()->nextResult());}$H=substr($H,$sh);$sh=0;}}}}if($hd)echo"<p class='message'>".lang(189)."\n";elseif($_POST["only_errors"]){$vh=$Jb-count($qd);echo"<p class='".($vh?"message":"error")."'>".lang(190,$Jb-count($qd))," <span class='time'>(".format_time($Dl).")</span>\n";}elseif($qd&&$Jb>1)echo"<p class='error'>".lang(185).": ".implode("",$qd)."\n";}else
echo"<p class='error'>".upload_error($H)."\n";}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";if(!isset($_GET["import"])){$Wi=$_GET["sql"];if($_POST)$Wi=$_POST["query"];elseif($_GET["history"]=="all")$Wi=$Le;elseif($_GET["history"]!="")$Wi=$Le[$_GET["history"]][0];echo"<p>";textarea("query",$Wi,20);echo
script(($_POST?"":"qs('textarea').focus();\n")."gid('form').onsubmit = partial(sqlSubmit, gid('form'), '".js_escape(remove_from_uri("sql|limit|error_stops|only_errors|history"))."');"),"</p>","<p><input type='submit' class='button default' value='".lang(191)."' title='Ctrl+Enter'>",lang(192).": <input type='number' name='limit' class='input size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{echo"<div class='field-sets'>\n","<fieldset><legend>".lang(193)."</legend><div class='fieldset-content'>";$Be=(extension_loaded("zlib")?"[.gz]":"");if(ini_bool("file_uploads"))echo"SQL$Be (&lt; ".ini_get("upload_max_filesize")."B): <input type='file' name='sql_file[]' multiple>","<input type='submit' class='button default' value='".lang(191)."'>",file_upload_form_script("form","sql_file[]");else
echo
lang(194);echo"</div></fieldset>\n";$Ye=Admin::get()->getImportFilePath();if($Ye)echo"<fieldset><legend>".lang(195)."</legend><div class='fieldset-content'>",lang(196,"<code>".h($Ye)."$Be</code>"),' <input type="submit" class="button default" name="webfile" value="'.lang(197).'">',"</div></fieldset>\n";echo"</div>\n","<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),lang(198)),checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),lang(199)),input_token(),"</p>\n";if(!isset($_GET["import"]))Admin::get()->printAfterSqlCommand();if(!isset($_GET["import"])&&$Le){echo"<div class='field-sets'>\n";print_fieldset_start("history",lang(200),"history",$_GET["history"]!="");for($X=end($Le);$X;$X=prev($Le)){$t=key($Le);list($Wi,$vl,$gd)=$X;echo" <pre><code class='jush-".DIALECT."'>",truncate_utf8(ltrim(str_replace("\n"," ",str_replace("\r","",preg_replace("~^(#|$gg).*~m",'',$Wi))))),"</code></pre>",'<p class="links">',"<a href='".h(ME."sql=&history=$t")."'>".icon("edit").lang(38)."</a>"," <span class='time' title='".@date('Y-m-d',$vl)."'>".@date("H:i:s",$vl).($gd?" ($gd)":"")."</span>","</p>";}echo"<p><input type='submit' class='button' name='clear' value='".lang(201)."'>\n","<a href='",h(ME."sql=&history=all")."' class='button light'>",icon("edit"),lang(202),"</a></p>\n";print_fieldset_end("history");echo"</div>\n";}echo"</form>\n";}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$k=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$k):""):where($_GET,$k));$Zl=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($k
as$A=>$j){if((!$Zl&&!isset($j["privileges"]["insert"]))||Admin::get()->getFieldName($j)=="")unset($k[$A]);}if($_POST&&!isset($_GET["select"])){$x=$_POST["referer"];if($_POST["insert"])$x=($Zl?null:$_SERVER["REQUEST_URI"]);elseif(!preg_match('~^.+&select=.+$~',$x))$x=ME."select=".urlencode($a);$s=indexes($a);$Tl=unique_array(isset($_GET["where"])?$_GET["where"]:[],$s);$bj="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($x,lang(203),(bool)Driver::get()->delete($a,$bj,$Tl?0:1));else{$kk=[];foreach($k
as$A=>$j){$X=process_input($j);if($X!==false&&$X!==null)$kk[idf_escape($A)]=$X;}if($Zl){if(!$kk)redirect($x);queries_redirect($x,lang(204),(bool)Driver::get()->update($a,$kk,$bj,$Tl?0:1));if(is_ajax()){page_headers();page_messages();exit;}}else{$I=Driver::get()->insert($a,$kk);$Vf=($I?last_id($I):0);queries_redirect($x,lang(205,($Vf?" $Vf":"")),(bool)$I);}}}$K=null;if($Z){$M=[];foreach($k
as$A=>$j){if(isset($j["privileges"]["select"])){$La=($_POST["clone"]&&$j["auto_increment"]?"''":convert_field($j));$M[]=($La?"$La AS ":"").idf_escape($A);}}$K=[];if(!support("table"))$M=["*"];if($M){$I=Driver::get()->select($a,$M,[$Z],$M,[],(isset($_GET["select"])?2:1));if(!$I)Admin::get()->addError(error());else{$K=$I->fetchAssoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetchAssoc()))$K=null;}}if(!support("table")&&!$k){if(!$Z){$I=Driver::get()->select($a,["*"],[],["*"]);$K=($I?$I->fetchAssoc():false);if(!$K)$K=[Driver::get()->primary=>""];}if($K){foreach($K
as$t=>$X){if(!$Z)$K[$t]=null;$k[$t]=["field"=>$t,"null"=>($t!=Driver::get()->primary),"auto_increment"=>($t==Driver::get()->primary)];}}}if(isset($_POST["save"])?$_POST["save"]:false){$Hi=[];foreach((isset($_POST["fields"])?$_POST["fields"]:[])as$t=>$X)$Hi[bracket_escape($t,true)]=$X;$K=$Hi+($K?:[]);}if($_POST["edit"]){$ed=array_filter($k,function($j){return!(isset($j["generated"])?$j["generated"]:null);});}else$ed=$k;edit_form($a,$ed,$K,$Zl);}elseif(isset($_GET["create"])){$a=$_GET["create"];$mi=Driver::get()->getPartitionBy();$qi=$mi?Driver::get()->getPartitionsInfo($a):[];$jj=referencable_primary($a);$ee=[];foreach($jj
as$Zk=>$j)$ee[str_replace("`","``",$Zk)."`".str_replace("`","``",$j["field"])]=$Zk;$Wh=[];$R=[];if($a!=""){$Wh=fields($a);$R=table_status1($a);if(count($R)<2)Admin::get()->addError(lang(78));}$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!Admin::get()->getErrors())Admin::get()->getSettings()->updateParameter("commentsOpened",isset($_POST["comments"])?$_POST["comments"]:null);if($_POST&&!process_fields($K["fields"])&&!Admin::get()->getErrors()){if($_POST["drop"])queries_redirect(substr(ME,0,-1),lang(206),drop_tables([$a]));else{$k=[];$Ea=[];$em=false;$ce=[];$Vh=reset($Wh);$Aa=" FIRST";foreach($K["fields"]as$t=>$j){$n=$ee[$j["type"]];$Nl=($n!==null?$jj[$n]:$j);if($j["field"]!=""){if(!$j["generated"])$j["default"]=null;$Ui=process_field($j,$Nl);$Ea[]=[$j["orig"],$Ui,$Aa];if(!$Vh||$Ui!==process_field($Vh,$Vh)){$k[]=[$j["orig"],$Ui,$Aa];if($j["orig"]!=""||$Aa)$em=true;}if($n!==null)$ce[idf_escape($j["field"])]=($a!=""&&DIALECT!="sqlite"?"ADD":" ").format_foreign_key(['table'=>$ee[$j["type"]],'source'=>[$j["field"]],'target'=>[$Nl["field"]],'on_delete'=>$j["on_delete"],]);$Aa=" AFTER ".idf_escape($j["field"]);}elseif($j["orig"]!=""){$em=true;$k[]=[$j["orig"]];}if($j["orig"]!=""){$Vh=next($Wh);if(!$Vh)$Aa="";}}$oi=[];if(in_array($K["partition_by"],$mi)){foreach($K
as$t=>$X){if(preg_match('~^partition~',$t))$oi[$t]=$X;}foreach($oi["partition_names"]as$t=>$A){if($A===""){unset($oi["partition_names"][$t]);unset($oi["partition_values"][$t]);}}$oi["partition_names"]=array_values($oi["partition_names"]);$oi["partition_values"]=array_values($oi["partition_values"]);if($oi==$qi)$oi=[];}elseif(str_contains(isset($R["Create_options"])?$R["Create_options"]:"","partitioned"))$oi=null;$_=lang(207);if($a==""){cookie("neo_engine",isset($K["Engine"])?$K["Engine"]:"");$_=lang(208);}$A=trim($K["name"]);queries_redirect(ME.(support("table")?"table=":"select=").urlencode($A),$_,alter_table($a,$A,(DIALECT=="sqlite"&&($em||$ce)?$Ea:$k),$ce,($K["Comment"]!=$R["Comment"]?$K["Comment"]:null),($K["Engine"]&&$K["Engine"]!=$R["Engine"]?$K["Engine"]:""),($K["Collation"]&&$K["Collation"]!=$R["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$oi));}}if($a!="")page_header(lang(35).": ".h($a),["table"=>$a,lang(35)]);else
page_header(lang(77),[lang(77)]);if(!$_POST){$Pl=Driver::get()->getTypes();$K=["Engine"=>$_COOKIE["neo_engine"],"fields"=>[["field"=>"","type"=>(isset($Pl["int"])?"int":(isset($Pl["integer"])?"integer":"")),"on_update"=>""]],"partition_names"=>[""],];if($a!=""){$K=$R;$K["name"]=$a;$K["fields"]=[];if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($Wh
as$j){$j["generated"]=$j["generated"]?:(isset($j["default"])?"DEFAULT":"");$K["fields"][]=$j;}if($mi){$K+=$qi;$K["partition_names"][]="";$K["partition_values"][]="";}}}$Gf=[];if($K["Collation"])$Gf[$K["Collation"]]=true;foreach($K["fields"]as$j){if($j["collation"])$Gf[$j["collation"]]=true;}$Cb=Admin::get()->getCollations(array_keys($Gf));$md=Driver::get()->engines();foreach($md
as$ld){if(!strcasecmp($ld,$K["Engine"])){$K["Engine"]=$ld;break;}}echo"<form action='' method='post' id='form'>\n";if(support("columns")||$a==""){echo"<p>",lang(209),": ","<input class='input' name='name' data-maxlength='64' value='",h($K["name"]),"' autocapitalize='off'",(($a==""&&!$_POST)?" autofocus":""),">";if($md)echo" ",html_select("Engine",[""=>"(".lang(210).")"]+$md,$K["Engine"]),help_script_command("value",true);if($Cb&&!preg_match("~sqlite|mssql~",DIALECT))echo" ",html_select("Collation",[""=>"(".lang(91).")"]+$Cb,$K["Collation"]);echo" <input type='submit' class='button default' value='",lang(113),"'>","</p>";}if(support("columns")&&($a==""||!Driver::get()->isPartition($a))){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$Cb,"TABLE",$ee);echo"</table>\n",script("initFieldsEditing(gid('edit-fields'));");if(support("move_col"))echo
script("initSortable('#edit-fields tbody');");echo"</div>\n","<p>",lang(47),": ","<input type='number' class='input size' name='Auto_increment' size='6' value='",h($K["Auto_increment"]),"'>";$Nb=$_POST?$_POST["comments"]:Admin::get()->getSettings()->getParameter("commentsOpened");$Lb=$Nb?"":"hidden";if(support("comment")){echo
checkbox("comments",1,$Nb,lang(46),"editingCommentsClick(this, ".(support("move_col")?7:6).");","jsonly")," ";if(preg_match('~\n~',$K["Comment"]))echo"<textarea name='Comment' rows='2' cols='20'",($Lb?" class='$Lb'":""),">",h($K["Comment"]),"</textarea>";else
echo"<input name='Comment' value='",h($K["Comment"]),"' data-maxlength='",(Connection::get()->isMinVersion("5.5")?2048:60),"' class='input $Lb'>";}echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(113),"'>";}elseif($a!="")echo"<p>";if($a!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(lang(211,$a)),"</p>\n";if($mi&&(DIALECT=="sql"||$a=="")){echo"<div class='field-sets'>\n";$ni=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset_start("partition",lang(212),"split",(bool)$K["partition_by"]);echo"<p>",html_select("partition_by",array_merge([""],$mi),$K["partition_by"]),help_script_command("value.replace(/./, 'PARTITION BY \$&')",true),script("qsl('select').onchange = partitionByChange;"),"(<input class='input' name='partition' value='",h($K["partition"]),"'>) ",lang(49),": ","<input type='number' name='partitions' class='input size ",($ni||!$K["partition_by"]?"hidden":""),"' value='",h($K["partitions"]),"'>","</p>\n","<table id='partition-table'",($ni?"":" class='hidden'"),">\n","<thead><tr><th>",lang(213),"</th><th>",lang(51),"</th></tr></thead>\n";foreach($K["partition_names"]as$t=>$X){echo"<tr>","<td><input class='input' name='partition_names[]' value='",h($X),"' autocapitalize='off'>";if($t==count($K["partition_names"])-1)echo
script("qsl('input').oninput = partitionNameChange;");echo"</td>","<td><input class='input' name='partition_values[]' value='",h(isset($K["partition_values"][$t])?$K["partition_values"][$t]:""),"'></td>","</tr>\n";}echo"</table>\n","</p>\n";print_fieldset_end("partition");echo"</div>\n";}echo
input_token(),"</form>\n";}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$ef=["PRIMARY","UNIQUE","INDEX"];$R=table_status1($a,true);$cf=Driver::get()->getIndexAlgorithms($R);$d=Connection::get();$sg=$d->isMariaDB();if(preg_match('~MyISAM|M?aria'.($d->isMinVersion($sg?"10.0.5":"5.6")?'|InnoDB':'').'~i',$R["Engine"]))$ef[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.($d->isMinVersion($sg?"10.2.2":"5.7")?'|InnoDB':'').'~i',$R["Engine"]))$ef[]="SPATIAL";if($sg&&$d->isMinVersion("11.7")&&preg_match('~MyISAM|InnoDB~i',$R["Engine"]))$ef[]="VECTOR";$s=indexes($a);$k=fields($a);$Ni=[];if(DIALECT=="mongo"){$Ni=$s["_id_"];unset($ef[0]);unset($s["_id_"]);}$K=$_POST;if($K){$O=Admin::get()->getSettings();if($O->getParameter("indexOptions")!==null)$O->updateParameter("indexOptions",null);}if($_POST&&!$_POST["add"]&&!$_POST["drop_col"]){$Ga=[];foreach($K["indexes"]as$r){$A=$r["name"];if(in_array($r["type"],$ef)){$c=[];$dg=[];$Cc=[];$Hh=[];$bf=$cf?(in_array($r["algorithm"],$cf)?$r["algorithm"]:first($cf)):"";$df=(support("partial_indexes")?$r["partial"]:"");$kk=[];ksort($r["columns"]);foreach($r["columns"]as$t=>$b){if($b!=""){$u=isset($r["lengths"][$t])?$r["lengths"][$t]:null;$Ac=isset($r["descs"][$t])?$r["descs"][$t]:null;$Gh=isset($r["opclasses"][$t])?$r["opclasses"][$t]:null;$kk[]=($k[$b]?idf_escape($b):$b).($u?"(".(+$u).")":"").($Gh!=""?" ".idf_escape($Gh):"").($Ac?" DESC":"");$c[]=$b;$dg[]=($u?:null);$Cc[]=$Ac;$Hh[]="$Gh";}}$xd=$s[$A];if($xd){ksort($xd["columns"]);ksort($xd["lengths"]);ksort($xd["descs"]);if($r["type"]==$xd["type"]&&array_values($xd["columns"])===$c&&(!$xd["lengths"]||array_values($xd["lengths"])===$dg)&&array_values($xd["descs"])===$Cc&&(!$xd["opclasses"]||array_values($xd["opclasses"])===$Hh)&&(!$cf||$xd["algorithm"]===$bf)&&$xd["partial"]==$df){unset($s[$A]);continue;}}if($c)$Ga[]=[$r["type"],$A,$kk,$bf,$df];}}foreach($s
as$A=>$xd)$Ga[]=[$xd["type"],$A,"DROP"];if(!$Ga)redirect(ME."table=".urlencode($a));queries_redirect(ME."table=".urlencode($a),lang(214),alter_indexes($a,$Ga));}page_header(lang(167),["table"=>$a,lang(167)],h($a));$Pd=array_keys($k);if($_POST["add"]){foreach($K["indexes"]as$t=>$r){if($r["columns"][count($r["columns"])]!="")$K["indexes"][$t]["columns"][]="";}$r=end($K["indexes"]);if($r["type"]||array_filter($r["columns"],'strlen'))$K["indexes"][]=["columns"=>[1=>""]];}if(!$K){foreach($s
as$t=>$r){$s[$t]["name"]=$t;$s[$t]["columns"][]="";}$s[]=["columns"=>[1=>""]];$K["indexes"]=$s;}$dg=(DIALECT=="sql"||DIALECT=="mssql");$Hh=Driver::get()->getIndexOpclasses();if($_POST)$ok=$_POST["options"];else{$ok=false;foreach($s
as$r){if(array_filter(isset($r["lengths"])?$r["lengths"]:[])||array_filter(isset($r["descs"])?$r["descs"]:[])||array_filter(isset($r["opclasses"])?$r["opclasses"]:[])||(isset($r["partial"])?$r["partial"]:"")!=""){$ok=true;break;}}}echo"<form action='' method='post'>\n","<div class='scrollable'>\n","<table class='nowrap'>\n","<thead><tr>","<th id='label-type'>",lang(215),"</th>";$Mh="class='idxopts".($ok?"":" hidden")."'";if(count($cf)>1)echo"<th id='label-method' $Mh>",lang(216),doc_link(['sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'ha-and-performance/optimization-and-tuning/optimization-and-indexes/storage-engine-index-types',]),"</th>";echo"<th><input type='submit' hidden>",lang(52).($dg?"<span $Mh> (".lang(53).")</span>":"");if($dg||support("descidx"))echo
checkbox("options",1,$ok,lang(97),"indexOptionsShow(this.checked)","jsonly")."\n";echo"</th>","<th id='label-name'>",lang(217),"</th>";if(support("partial_indexes"))echo"<th id='label-condition' $Mh>",lang(54),"</th>";echo"<th>","<button name='add[0]' value='1' title='",lang(98),"' class='button light hidden'>",icon_solo("add"),"</button>","</th>","</tr></thead>\n";if($Ni){echo"<tr><td>PRIMARY<td>";foreach($Ni["columns"]as$b)echo
select_input(" disabled",$Pd,$b),"<label><input type='checkbox' disabled>".lang(62)."</label> ";echo"<td><td>\n";}$Cf=1;foreach($K["indexes"]as$r){if(!$_POST["drop_col"]||$Cf!=key($_POST["drop_col"])){echo"<tr><td>",html_select("indexes[$Cf][type]",[-1=>""]+$ef,$r["type"],($Cf==count($K["indexes"])?"indexesAddRow.call(this);":""),"label-type"),"</td>";if(count($cf)>1)echo"<td $Mh>",html_select("indexes[$Cf][algorithm]",array_merge([""],$cf),$r['algorithm'],"label-method"),"</td>";echo"<td>";ksort($r["columns"]);$p=1;foreach($r["columns"]as$t=>$b){echo"<span>".select_input(" name='indexes[$Cf][columns][$p]' title='".lang(43)."'",($k&&($b==""||$k[$b])?array_combine($Pd,$Pd):[]),$b,"partial(".($p==count($r["columns"])?"indexesAddColumn":"indexesChangeColumn").", '".js_escape(DIALECT=="sql"?"":$_GET["indexes"]."_")."')"),"<span $Mh>";if($dg)echo"<input type='number' name='indexes[$Cf][lengths][$p]' class='input size' value='".(h(isset($r["lengths"][$t])?$r["lengths"][$t]:"")),"' title='".lang(96),"'>";if($Hh){$Gh=isset($r["opclasses"][$t])?$r["opclasses"][$t]:"";echo
html_select("indexes[$Cf][opclasses][$p]",[""=>"(".lang(218).")"]+array_combine($Hh,$Hh)+($Gh!=""?[$Gh=>$Gh]:[]),$Gh),'';}if(support("descidx"))echo
checkbox("indexes[$Cf][descs][$p]",1,isset($r["descs"][$t])?$r["descs"][$t]:false,lang(62));echo"<br></span></span>";$p++;}echo"</td>","<td><input name='indexes[$Cf][name]' value='",h($r["name"]),"' class='input' autocapitalize='off' aria-labelledby='label-name'></td>\n";if(support("partial_indexes"))echo"<td $Mh><input name='indexes[$Cf][partial]' value='".h($r["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>","<button name='drop_col[$Cf]' value='1' title='",h(lang(58)),"' class='button light'>",icon_solo("remove"),"</button>",script("qsl('button').onclick = onRemoveIndexRowClick;"),"</td>\n";}$Cf++;}echo"</table>\n","</div>\n","<p>","<input type='submit' class='button default' value='",lang(113),"'>",input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!isset($_POST["add_x"])){$A=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),lang(219),drop_databases([DB]));}elseif(DB!==$A){if(DB!=""){$_GET["db"]=$A;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".urlencode($A),lang(220),rename_database($A,$K["collation"]));}else{$f=explode("\n",str_replace("\r","",$A));$Jk=true;$Uf="";foreach($f
as$g){if(count($f)==1||$g!=""){if(!create_database($g,$K["collation"]))$Jk=false;$Uf=$g;}}restart_session();set_session("dbs",null);queries_redirect(ME."db=".urlencode($Uf),lang(221),$Jk);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($A).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),lang(222));}}if(DB!="")page_header(lang(69).": ".h(DB),[lang(69)]);else
page_header(lang(75),[lang(75)]);$A=DB;if($_POST)$A=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,collations());elseif(DIALECT=="sql"){foreach(get_vals("SHOW GRANTS")as$ue){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$ue,$y)&&$y[1]){$A=stripcslashes(idf_unescape("`$y[2]`"));break;}}}$Cb=Admin::get()->getCollations($K["collation"]?[$K["collation"]]:[]);echo"<form action='' method='post'>\n","<p>";if($_POST["add_x"]||strpos($A,"\n"))echo"<textarea id='name' name='name' rows='10' cols='40'>",h($A),"</textarea><br>\n";else
echo"<input class='input' name='name' id='name' value='",h($A),"' data-maxlength='64' autocapitalize='off' autofocus>\n";if($Cb)echo
html_select("collation",[""=>"(".lang(91).")"]+$Cb,$K["collation"]),doc_link(['sql'=>"charset-charsets.html",'mariadb'=>"reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations",]),"\n";echo"<input type='submit' class='button default' value='",lang(113),"'>\n";if(DB!="")echo"<input type='submit' class='button' name='drop' value='".lang(160)."'>".confirm(lang(211,DB))."\n";elseif(!$_POST["add_x"]&&$_GET["db"]=="")echo"<button name='add_x' value='1' title='",h(lang(98)),"' class='button light'>",icon_solo("add"),"</button>\n";echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["call"])){$oa=$_GET["name"]?:$_GET["call"];page_header(lang(223).": ".h($oa),[lang(223)]);$_j=routine($_GET["call"],(isset($_GET["callf"])?"FUNCTION":"PROCEDURE"));$Ze=[];$ai=[];foreach($_j["fields"]as$p=>$j){if(substr($j["inout"],-3)=="OUT"&&DIALECT=='sql')$ai[$p]="@".idf_escape($j["field"])." AS ".idf_escape($j["field"]);if(!$j["inout"]||substr($j["inout"],0,2)=="IN")$Ze[]=$p;}if($_POST){$kb=[];foreach($_j["fields"]as$t=>$j){$X="";if(in_array($t,$Ze)){$X=process_input($j);if($X===false)$X="''";if(isset($ai[$t]))Connection::get()->query("SET @".idf_escape($j["field"])." = $X");}if(isset($ai[$t]))$kb[]="@".idf_escape($j["field"]);elseif(in_array($t,$Ze))$kb[]=$X;}$H=(isset($_GET["callf"])?"SELECT ":"CALL ").($_j["returns"]&&$_j["returns"]["type"]=="record"?"* FROM ":"").table($oa)."(".implode(", ",$kb).")";$Ak=microtime(true);$I=Connection::get()->multiQuery($H);$za=Connection::get()->getAffectedRows();echo
Admin::get()->formatSelectQuery($H,$Ak,!$I);if(!$I)echo"<p class='error'>".error()."\n";else{$Ub=connect();if($Ub)$Ub->selectDatabase(DB);do{$I=Connection::get()->storeResult();if(is_object($I))print_select_result($I,$Ub);else
echo"<p class='message'>".lang(224,$za)." <span class='time'>".@date("H:i:s")."</span>\n";}while(Connection::get()->nextResult());if($ai)print_select_result(Connection::get()->query("SELECT ".implode(", ",$ai)));}}echo"<form action='' method='post'>\n";if($Ze){echo"<table class='box'>\n";foreach($Ze
as$t){$j=$_j["fields"][$t];$A=$j["field"];echo"<tr><th>".Admin::get()->getFieldName($j);$Y=isset($_POST["fields"][$A])?$_POST["fields"][$A]:"";if($Y!=""){if($j["type"]=="set")$Y=implode(",",$Y);}input($j,$Y,(string)(isset($_POST["function"][$A])?$_POST["function"][$A]:""));echo"\n";}echo"</table>\n";}echo"<p>\n","<input type='submit' class='button' value='",lang(223),"'>\n",input_token(),"</p>\n","</form>\n";$Kb=$_j["comment"];if($Kb!==null&&$Kb!==""){$Kb=h(trim($_j["comment"],"\n"));if(preg_match('~^ +~',$Kb,$z)){preg_match_all("~^($z[0]|$)~m",$Kb,$hg);if(count($hg[0])==substr_count($Kb,"\n"))$Kb=preg_replace("~^($z[0])~m","",$Kb);}$Kb=preg_replace('~(^|[^\n]\n)(Description|Parameters|Example)\n~',"$1\n<strong>$2</strong>\n",$Kb);echo"<pre class='comment'>$Kb</pre>\n";}}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$A=$_GET["name"];$K=$_POST;if($_POST&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$ll=[];foreach($K["source"]as$t=>$X)$ll[$t]=$K["target"][$t];$K["target"]=$ll;}if(DIALECT=="sqlite")$I=recreate_table($a,$a,[],[],[" $A"=>($K["drop"]?"":" ".format_foreign_key($K))]);else{$Ga="ALTER TABLE ".table($a);$I=($A==""||queries("$Ga DROP ".(DIALECT=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($A)));if(!$K["drop"])$I=queries("$Ga ADD".format_foreign_key($K));}queries_redirect(ME."table=".urlencode($a),($K["drop"]?lang(225):($A!=""?lang(226):lang(227))),(bool)$I);if(!$K["drop"])Admin::get()->addError(lang(228));}page_header(lang(229).": ".h($a),["table"=>$a,lang(229)]);if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=[];else$K["source"][]="";}elseif($A!=""){$ee=foreign_keys($a);$K=$ee[$A];$K["source"][]="";}else{$K["table"]=$a;$K["source"]=[""];}echo"<form action='' method='post'>\n";$uk=array_keys(fields($a));if($K["db"]!="")Connection::get()->selectDatabase($K["db"]);if($K["ns"]!=""){$Xh=get_schema();set_schema($K["ns"]);}$ij=array_keys(array_filter(table_status('',true),'AdminNeo\fk_support'));$ll=array_keys(fields(in_array($K["table"],$ij)?$K["table"]:reset($ij)));$Ch="this.form['change-js'].value = '1'; this.form.submit();";echo"<p>","<span id='label-table'>",lang(230),":</span> ",html_select("table",$ij,$K["table"],$Ch,"label-table");if(DIALECT!="sqlite"){$rc=[];foreach(Admin::get()->getDatabases()as$g){if(!information_schema($g))$rc[]=$g;}echo"<span id='label-db'>",lang(231),":</span> ",html_select("db",$rc,$K["db"]!=""?$K["db"]:$_GET["db"],$Ch,"label-db");}echo
input_hidden("change-js"),"<noscript><input type='submit' class='button' name='change' value='",lang(232),"'></noscript>","</p>\n","<table>","<thead><tr><th id='label-source'>",lang(168),"<th id='label-target'>",lang(169),"</thead>\n";$Cf=0;foreach($K["source"]as$t=>$X){echo"<tr>","<td>".html_select("source[".(+$t)."]",[-1=>""]+$uk,$X,($Cf==count($K["source"])-1?"foreignAddRow.call(this);":""),"label-source"),"<td>".html_select("target[".(+$t)."]",$ll,isset($K["target"][$t])?$K["target"][$t]:null,"","label-target");$Cf++;}echo"</table>\n","<noscript><p><input type='submit' class='button' name='add' value='",lang(233),"'></p></noscript>","<p>\n","<span id='label-delete'>".lang(93),":</span> ",html_select("on_delete",[-1=>""]+Driver::get()->getOnActions(),$K["on_delete"],"","label-delete"),"<span id='label-update'>".lang(92),":</span> ",html_select("on_update",[-1=>""]+Driver::get()->getOnActions(),$K["on_update"],"","label-update");if(DRIVER=='pgsql')echo
html_select("deferrable",['NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'],$K["deferrable"]);echo
doc_link(['sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"architecture/server-constraints/foreign-key-constraints",]),"</p>\n<p>","<input type='submit' class='button default' value='",lang(113),"'>";if($A!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(lang(211,$A));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$Yh="VIEW";if(DIALECT=="pgsql"&&$a!=""){$P=table_status1($a);$Yh=strtoupper($P["Engine"]);}if($_POST){$A=trim($K["name"]);$La=" AS\n$K[select]";$x=ME."table=".urlencode($A);$_=lang(234);$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$A&&DIALECT!="sqlite"&&$U=="VIEW"&&$Yh=="VIEW")query_redirect((DIALECT=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($A).$La,$x,$_);else{$nl=$A."_adminneo_".uniqid();drop_create("DROP $Yh ".table($a),"CREATE $U ".table($A).$La,"DROP $U ".table($A),"CREATE $U ".table($nl).$La,"DROP $U ".table($nl),($_POST["drop"]?substr(ME,0,-1):$x),lang(235),$_,lang(236),$a,$A);}}if(!$_POST&&$a!=""){$K=view($a);$K["name"]=$a;$K["materialized"]=($Yh!="VIEW");if($i=error())Admin::get()->addError($i);}if($a!="")page_header(lang(36).": ".h($a),["table"=>$a,lang(36)]);else
page_header(lang(237),[lang(237)]);echo"<form action='' method='post'>\n","<p>",lang(217),":","<input class='input' name='name' value='",h($K["name"]),"' data-maxlength='64' autocapitalize='off'>\n";if(support("materializedview"))echo
checkbox("materialized",1,$K["materialized"],lang(161));echo"</p>\n<p>";textarea("select",$K["select"]);echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(113),"'>\n";if($a!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>\n",confirm(lang(211,$a));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["event"])){$ea=$_GET["event"];$qf=["YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND"];$Ck=["ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE"];$K=$_POST;if($_POST){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($ea),substr(ME,0,-1),lang(238));elseif(in_array($K["INTERVAL_FIELD"],$qf)&&isset($Ck[$K["STATUS"]])){$Kj="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($ea!=""?lang(239):lang(240)),(bool)queries(($ea!=""?"ALTER EVENT ".idf_escape($ea).$Kj.($ea!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$Kj)."\n".$Ck[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}if($ea!="")page_header(lang(241).": ".h($ea),[lang(241)]);else
page_header(lang(242),[lang(242)]);if(!$K&&$ea!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($ea));$K=reset($L);}echo"<form action='' method='post'>\n","<table class='box box-light'>\n","<tr><th>",lang(217),"</th><td>","<input class='input' name='EVENT_NAME' value='",h($K["EVENT_NAME"]),"' data-maxlength='64' autocapitalize='off'>","</td></tr>\n","<tr><th title='datetime'>",lang(243),"</th><td>","<input class='input' name='STARTS' value='",h("$K[EXECUTE_AT]$K[STARTS]"),"'>","</td></tr>\n","<tr><th title='datetime'>",lang(244),"</th><td>","<input class='input' name='ENDS' value='",h($K["ENDS"]),"'>","</td></tr>\n","<tr><th>",lang(245),"</th><td>","<input type='number' name='INTERVAL_VALUE' value='",h($K["INTERVAL_VALUE"]),"' class='input size'> ",html_select("INTERVAL_FIELD",$qf,$K["INTERVAL_FIELD"]),"</td></tr>\n","<tr><th>",lang(152),"</th><td>",html_select("STATUS",$Ck,$K["STATUS"]),"</td></tr>\n","<tr><th>",lang(46),"</th><td>","<input class='input' name='EVENT_COMMENT' value='",h($K["EVENT_COMMENT"]),"' data-maxlength='64'>","</td></tr>\n","<tr><th></th><td>",checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",lang(246)),"</td></tr>\n","</table>\n","<p>";textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo"</p>\n","<p>","<input type='submit' class='button default' value='",lang(113),"'>";if($ea!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(lang(211,$ea));echo"</p>\n",input_token(),"</form>\n";}elseif(isset($_GET["procedure"])){$oa=($_GET["name"]?:$_GET["procedure"]);$_j=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])){foreach($K["fields"]as$t=>$j){if($j["field"]=="")unset($K["fields"][$t]);}$yh=routine_id($oa,routine($_GET["procedure"],$_j));$hh=routine_id($K["name"],$K);$cc=create_routine($_j,$K);$x=substr(ME,0,-1);$_=lang(247);if(!$_POST["drop"]&&$yh==$hh&&(DIALECT!="sql"||Connection::get()->isMariaDB()))query_redirect(substr_replace($cc,' OR REPLACE',6,0),$x,$_);else{$nl="$K[name]_adminer_".uniqid();drop_create("DROP $_j $yh",$cc,"DROP $_j $hh",create_routine($_j,["name"=>$nl]+$K),"DROP $_j ".routine_id($nl,$K),$x,lang(248),$_,lang(249),$oa,$K["name"]);}}if($oa!=""){$T=isset($_GET["function"])?lang(250):lang(251);page_header($T.": ".h($oa),[$T]);}else{$T=isset($_GET["function"])?lang(252):lang(253);page_header($T,[$T]);}if(!$_POST){if($oa=="")$K["language"]="sql";else{$K=routine($_GET["procedure"],$_j);$K["name"]=$oa;}}$pb=get_vals("SHOW CHARACTER SET");sort($pb);$Aj=routine_languages();echo"<form action='' method='post' id='form'>\n","<p>",lang(217),": ","<input class='input' name='name' value='",h($K["name"]),"' data-maxlength='64' autocapitalize='off'>";if($Aj)echo"<span id='label-language'>",lang(9),":</span> ",html_select("language",$Aj,$K["language"],"","label-language");echo"<input type='submit' class='button default' value='",lang(113),"'>","</p>\n","<div class='scrollable'>\n","<table class='nowrap' id='edit-fields'>\n";edit_fields($K["fields"],$pb,$_j);if(isset($_GET["function"])){echo"<tbody><tr>";if(support("move_col"))echo"<th></th>";echo"<th>",lang(254),"</th>";edit_type("returns",(array)$K["returns"],$pb,[],(DIALECT=="pgsql"?["void","trigger"]:[]));echo"<td></td>","</tr></tbody>\n";}echo"</table>\n",script("initFieldsEditing(gid('edit-fields'));");if(support("move_col"))echo
script("initSortable('#edit-fields tbody');");echo"</div>\n","<p>";textarea("definition",$K["definition"],20);echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(113),"'>";if($oa!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(lang(211,$oa));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["check"])){$a=$_GET["check"];$A=$_GET["name"];$K=$_POST;if($K){if(DIALECT=="sqlite")$Jk=recreate_table($a,$a,[],[],[],"",[],"$A",($K["drop"]?"":$K["clause"]));else{$Jk=($A==""||queries("ALTER TABLE ".table($a)." DROP CONSTRAINT ".idf_escape($A)));if(!$K["drop"])$Jk=(bool)queries("ALTER TABLE ".table($a)." ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"")." CHECK ($K[clause])");}queries_redirect(ME."table=".urlencode($a),($K["drop"]?lang(255):($A!=""?lang(256):lang(257))),$Jk);}page_header(($A!=""?lang(258).": ".h($A):lang(173)),["table"=>$a]);if(!$K){$ub=Driver::get()->checkConstraints($a);$K=["name"=>$A,"clause"=>$ub[$A]];}echo"<form action='' method='post'>\n","<p>";if(DIALECT!="sqlite")echo
lang(217).': <input name="name" value="'.h($K["name"]).'" class="input" data-maxlength="64" autocapitalize="off"> ';echo
doc_link(['sql'=>"create-table-check-constraints.html",'mariadb'=>"reference/sql-statements/data-definition/constraint",],"?"),"</p>\n<p>";textarea("clause",$K["clause"]);echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(113),"'>";if($A!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(lang(211,$A));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$A=isset($_GET["name"])?$_GET["name"]:"";$Jl=trigger_options();$K=trigger($A,$a)+["Trigger"=>$a."_bi"];if($_POST){if(in_array($_POST["Timing"],$Jl["Timing"])&&in_array($_POST["Event"],$Jl["Event"])&&in_array($_POST["Type"],$Jl["Type"])){$Ah=" ON ".table($a);$Tc="DROP TRIGGER ".idf_escape($A).(DIALECT=="pgsql"?$Ah:"");$x=ME."table=".urlencode($a);if($_POST["drop"])query_redirect($Tc,$x,lang(259));else{if($A!="")queries($Tc);queries_redirect($x,($A!=""?lang(260):lang(261)),(bool)queries(create_trigger($Ah,$_POST)));if($A!="")queries(create_trigger($Ah,$K+["Type"=>reset($Jl["Type"])]));}}$K=$_POST;}if($A!="")page_header(lang(262).": ".h($A),["table"=>$a,h($A)]);else
page_header(lang(263),["table"=>$a,lang(263)]);echo"<form action='' method='post' id='form'>\n","<table class='box box-light'>\n","<tr><th id='label-time'>",lang(264),"</th><td>",html_select("Timing",$Jl["Timing"],$K["Timing"],"triggerChange(/^".js_escape_re($a)."_[ba][iud]$/, '".js_escape($a)."', this.form);","label-time"),"</td></tr>\n","<tr><th id='label-event'>",lang(265),"</th><td>",html_select("Event",$Jl["Event"],$K["Event"],"this.form['Timing'].onchange();","label-event");if(in_array("UPDATE OF",$Jl["Event"]))echo" <input name='Of' value='".h($K["Of"])."' class='input hidden'>";echo"</td></tr>\n","<tr><th id='label-type'>",lang(44),"</th><td>",html_select("Type",$Jl["Type"],$K["Type"],"","label-type"),"</td></tr>\n","</table>\n","<p>",lang(217),"<input class='input' name='Trigger' value='",h($K["Trigger"]),"' data-maxlength='64' autocapitalize='off'>","</p>\n",script("gid('form')['Timing'].onchange();"),"<p>";textarea("Statement",$K["Statement"]);echo"</p>\n","<p>","<input type='submit' class='button default' value='",lang(113),"'>";if($A!="")echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>",confirm(lang(211,$A));echo"</p>\n",input_token(),"</form>\n";}elseif(isset($_GET["user"])){$qa=$_GET["user"];$Ri=[""=>["All privileges"=>""]];foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$Yb)$Ri[$Yb=="File access on server"?"Server Admin":$Yb][$K["Privilege"]]=$K["Comment"];}unset($Ri["Server Admin"]["Usage"]);foreach($Ri["Tables"]as$t=>$X)unset($Ri["Databases"][$t]);$gh=[];if($_POST){foreach($_POST["objects"]as$t=>$X)$gh[$X]=(array)$gh[$X]+(array)$_POST["grants"][$t];}$we=[];if(isset($_GET["host"])&&($I=Connection::get()->query("SHOW GRANTS FOR ".q($qa)."@".q($_GET["host"])))){while($K=$I->fetchRow()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$y)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$y[1],$z,PREG_SET_ORDER)){foreach($z
as$X){if($X[1]!="USAGE")$we["$y[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$we["$y[2]$X[2]"]["GRANT OPTION"]=true;}}}}$zi=!Connection::get()->isMariaDB()&&Connection::get()->isMinVersion("8");if($_POST){$_h=(isset($_GET["host"])?q($qa)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $_h",ME."privileges=",lang(266));else{$jh=q($_POST["user"])."@".q($_POST["host"]);$si=$_POST["pass"];$fc=false;$I=true;if($_h!=$jh){$fc=(bool)queries("CREATE USER $jh IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($si));$I=$fc;}elseif($si!="")$I=(bool)queries("SET PASSWORD FOR $jh = ".($zi||$_POST["hashed"]?q($si):"PASSWORD(".q($si).")"));if($I){$xj=[];foreach($gh
as$rh=>$ue){if(isset($_GET["grant"]))$ue=array_filter($ue);$ue=array_keys($ue);if(isset($_GET["grant"]))$xj=array_diff(array_keys(array_filter($gh[$rh],'strlen')),$ue);elseif($_h==$jh){$xh=array_keys((array)$we[$rh]);$xj=array_diff($xh,$ue);$ue=array_diff($ue,$xh);unset($we[$rh]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$rh,$y)&&(!grant(false,$xj,$y[2],$y[1],$jh)||!grant(true,$ue,$y[2],$y[1],$jh))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($_h!=$jh)queries("DROP USER $_h");elseif(!isset($_GET["grant"])){foreach($we
as$rh=>$xj){if(preg_match('~^(.+)(\(.*\))?$~U',$rh,$y))grant(false,array_keys($xj),$y[2],$y[1],$jh);}}}queries_redirect(ME."privileges=",(isset($_GET["host"])?lang(267):lang(268)),$I);if($fc)Connection::get()->query("DROP USER $jh");}}$T=isset($_GET["host"])?lang(28).": ".h("$qa@$_GET[host]"):lang(183);$yl=isset($_GET["host"])?h($qa):lang(183);page_header($T,["privileges"=>['',lang(72)],$yl]);if($_POST){$K=$_POST;$we=$gh;}else{$K=$_GET+["host"=>Connection::get()->getValue("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")];if($we)$we[".*"]=[];elseif(DB!="")$we[idf_escape(addcslashes(DB,"%_\\")).".*"]=[];else$we["*.* "]=[];}echo"<form action='' method='post'>\n","<table class='box box-light'>\n","<tr><th>",lang(5),"</th>","<td><input class='input' name='host' data-maxlength='60' value='",h($K["host"]),"' autocapitalize='off'></td>\n","<tr><th>",lang(28),"</th>","<td><input class='input' name='user' data-maxlength='80' value='",h($K["user"]),"' autocapitalize='off'></td>\n",'<tr><th>',lang(29),"</th>","<td><input class='input' name='pass' id='pass' value='",h($K["pass"]),"' autocomplete='new-password'>";if(!$zi)echo
checkbox("hashed",1,$K["hashed"],lang(269),"typePassword(this.form['pass'], this.checked);");echo"</td>\n";if(!$K["hashed"])echo
script("typePassword(gid('pass'));");echo"</table>\n","<div class='scrollable'><table class='checkable'>\n","<thead><tr><th colspan='2'>".lang(72).doc_link(['sql'=>"grant.html#priv_level","mariadb"=>"reference/sql-statements/account-management-sql-statements/grant#privilege-levels"])."</th>";$p=0;foreach($we
as$rh=>$ue){echo"<th>";if($rh=="*.*")echo"*.*",input_hidden("objects[$p]","*.*");else
echo"<input class='input' name='objects[$p]' value='".h(trim($rh))."' size='10' autocapitalize='off'>";echo"</th>";$p++;}echo"</tr></thead>\n";foreach([""=>"","Server Admin"=>lang(5),"Databases"=>lang(30),"Tables"=>lang(8),"Procedures"=>lang(270),]as$Yb=>$Ac){foreach((array)$Ri[$Yb]as$Qi=>$Kb){echo"<tr>";if($Ac)echo"<td>$Ac</td>";echo"<td".(!$Ac?" colspan='2'":"").' lang="en" title="'.h($Kb).'">'.h($Qi)."</td>";$p=0;foreach($we
as$rh=>$ue){$A="'grants[$p][".h(strtoupper($Qi))."]'";$Y=$ue[strtoupper($Qi)];$Vi=strpos($rh,"@")!==false;$fh=$rh==".*";$Ca=$Qi=="All privileges";$ve=$Qi=="Grant option";if($rh=="*.*"&&$Qi=="Proxy")echo"<td></td>";elseif($Vi&&$Qi!="Proxy"&&!$ve)echo"<td></td>";elseif($Yb=="Server Admin"&&$rh!=(isset($we["*.*"])?"*.*":".*")&&!(($Vi||$fh)&&$Qi=="Proxy"))echo"<td></td>";elseif(isset($_GET["grant"]))echo"<td><select name=$A>"."<option></option>"."<option value='1'".($Y?" selected":"").">".lang(271)."</option>"."<option value='0'".($Y=="0"?" selected":"").">".lang(272)."</option>"."</select></td>";else{echo"<td class='center'><label class='block'>","<input type='checkbox' name=$A value='1'".($Y?" checked":"").($Ca?" id='grants-$p-all'":(!$ve?" class='grants-$p'":"")).">";if($Ca)echo
script("qsl('input').onclick = function () { if (this.checked) formUncheckAll('.grants-$p'); };");elseif(!$ve)echo
script("qsl('input').onclick = function () { if (this.checked) formUncheck('grants-$p-all'); };");echo"</label>";}$p++;}echo"</tr>";}}echo"</table></div>\n","<p>","<input type='submit' class='button default' value='",lang(113),"'>\n";if(isset($_GET["host"]))echo"<input type='submit' class='button' name='drop' value='",lang(160),"'>\n",confirm(lang(211,"$qa@$_GET[host]"));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST){$Mf=0;foreach((array)$_POST["kill"]as$X){if(kill_process($X))$Mf++;}queries_redirect(ME."processlist=",lang(273,$Mf),$Mf||!$_POST["kill"]);}}page_header(lang(150),[lang(150)]);echo"<form action='' method='post'>\n","<div class='scrollable'>\n","<table class='nowrap checkable'>\n";$p=-1;foreach(process_list()as$p=>$K){if(!$p){echo"<thead><tr lang='en'>".(support("kill")?"<th>":"");foreach($K
as$t=>$X)echo"<th>$t".doc_link(['sql'=>"show-processlist.html#processlist_".strtolower($t),'mariadb'=>"reference/sql-statements/administrative-sql-statements/show/show-processlist",]);echo"</thead>\n","<tbody>\n";}echo"<tr>".(support("kill")?"<td>".checkbox("kill[]",$K[DIALECT=="sql"?"Id":"pid"],0):"");foreach($K
as$t=>$X)echo"<td>".($X!=""&&((DIALECT=="sql"&&$t=="Info"&&preg_match("~Query|Killed~",$K["Command"]))||(DIALECT=="pgsql"&&$t=="query")||(DIALECT=="oracle"&&$t=="sql_text"))?"<code class='jush-".DIALECT."'>".truncate_utf8($X,100).'</code> <a href="'.h(ME.($K["db"]!=""?"db=".urlencode($K["db"])."&":"")."sql=".urlencode($X)).'">'.icon("edit").lang(274).'</a>':h($X));echo"\n";}if($p>=0)echo"</tbody>\n",script("mixin(qsl('tbody'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");echo"</table>\n","</div>\n","<p>";if(support("kill"))echo($p+1)."/".lang(275,max_connections()),"<p><input type='submit' class='button' value='".lang(276)."'>\n";echo
input_token(),"</p>\n","</form>\n",script("tableCheck();");}elseif(isset($_GET["select"])){$a=$_GET["select"];$R=table_status1($a);$s=indexes($a);$k=fields($a);$ee=column_foreign_keys($a);$th=$R["Oid"];$yj=[];$c=[];$Qj=[];$Oh=[];$rl=null;foreach($k
as$t=>$j){$A=Admin::get()->getFieldName($j);$bh=html_entity_decode(strip_tags($A),ENT_QUOTES);if(isset($j["privileges"]["select"])&&$A!=""){$c[$t]=$bh;if(is_shortable($j))$rl=Admin::get()->processSelectionLength();}if(isset($j["privileges"]["where"])&&$A!="")$Qj[$t]=$bh;if(isset($j["privileges"]["order"])&&$A!="")$Oh[$t]=$bh;$yj+=$j["privileges"];}list($M,$xe)=Admin::get()->processSelectionColumns($c,$s);$M=array_unique($M);$xe=array_unique($xe);$wf=count($xe)<count($M);$Z=Admin::get()->processSelectionSearch($k,$s);$D=Admin::get()->processSelectionOrder($k,$s);$v=Admin::get()->processSelectionLimit();if($_GET["modify"]&&!Admin::get()->isDataEditAllowed())redirect(ME."select=".urlencode($a));if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$Ul=>$K){$La=convert_field($k[key($K)]);$M=[$La?:idf_escape(key($K))];$Z[]=where_check($Ul,$k);$J=Driver::get()->select($a,$M,$Z,$M);if($J)echo
first($J->fetchRow());}exit;}$Ni=$Xl=[];foreach($s
as$r){if($r["type"]=="PRIMARY"){$Ni=array_flip($r["columns"]);$Xl=($M?$Ni:[]);foreach($Xl
as$t=>$X){if(in_array(idf_escape($t),$M))unset($Xl[$t]);}break;}}if($th&&!$Ni){$Ni=$Xl=[$th=>0];$s[]=["type"=>"PRIMARY","columns"=>[$th]];}$O=Admin::get()->getSettings();if($_POST){$Em=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$ub=[];foreach($_POST["check"]as$qb)$ub[]=where_check($qb,$k);$Em[]="((".implode(") OR (",$ub)."))";}$Em=($Em?"\nWHERE ".implode(" AND ",$Em):"");if($_POST["export"]){$O->updateParameters(["exportFormat"=>$_POST["format"],"exportOutput"=>$_POST["output"],]);dump_headers($a);Admin::get()->dumpTable($a,"");$me=($M?implode(", ",$M):"*").convert_fields($c,$k,$M)."\nFROM ".table($a);$_e=($xe&&$wf?"\nGROUP BY ".implode(", ",$xe):"").($D?"\nORDER BY ".implode(", ",$D):"");if(!is_array($_POST["check"])||$Ni)$H="SELECT $me$Em$_e";else{$Rl=[];foreach($_POST["check"]as$X)$Rl[]="(SELECT".limit($me,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$k).$_e,1).")";$H=implode(" UNION ALL ",$Rl);}Admin::get()->dumpData($a,"table",$H);exit;}if($_POST["save"]||$_POST["delete"]){$I=true;$za=0;$kk=[];if(!$_POST["delete"]){$Yj=array_keys($_POST["fields"]+$_POST["function"]);foreach($Yj
as$A){$X=process_input($k[$A]);if($X!==null&&($_POST["clone"]||$X!==false))$kk[idf_escape($A)]=($X!==false?$X:idf_escape($A));}}if($_POST["delete"]||$kk){if($_POST["clone"])$H="INTO ".table($a)." (".implode(", ",array_keys($kk)).")\nSELECT ".implode(", ",$kk)."\nFROM ".table($a);if($_POST["all"]||($Ni&&is_array($_POST["check"]))||$wf){$I=($_POST["delete"]?Driver::get()->delete($a,$Em):($_POST["clone"]?queries("INSERT $H$Em".Driver::get()->getInsertReturningSql($a)):Driver::get()->update($a,$kk,$Em)));$za=Connection::get()->getAffectedRows();if(is_object($I))$za+=$I->getRowsCount();}else{foreach((array)$_POST["check"]as$X){$Dm="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$k);$I=($_POST["delete"]?Driver::get()->delete($a,$Dm,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$Dm)):Driver::get()->update($a,$kk,$Dm,1)));if(!$I)break;$za+=Connection::get()->getAffectedRows();}}}$_=lang(277,$za);if($_POST["clone"]&&$I&&$za==1){$Vf=last_id($I);if($Vf)$_=lang(205," $Vf");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page":""),$_,(bool)$I);if(!$_POST["delete"]){$ed=array_filter($k,function($j){return!(isset($j["generated"])?$j["generated"]:null);});edit_form($a,$ed,(array)$_POST["fields"],!$_POST["clone"]);page_footer();exit;}}elseif(!$_POST["import"]){if(!$_POST["val"])Admin::get()->addError(lang(278));else{$Jk=true;$za=0;foreach($_POST["val"]as$Ul=>$K){$kk=[];foreach($K
as$t=>$X){$t=bracket_escape($t,true);$kk[idf_escape($t)]=(preg_match('~char|text~',$k[$t]["type"])||$X!=""?Admin::get()->processFieldInput($k[$t],$X):"NULL");}$Jk=(bool)Driver::get()->update($a,$kk," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($Ul,$k),($wf||$Ni?0:1)," ");if(!$Jk)break;$za+=Connection::get()->getAffectedRows();}queries_redirect(remove_from_uri(),lang(277,$za),$Jk);}}elseif(!is_string($l=get_file("csv_file",true)))Admin::get()->addError(upload_error($l));elseif(!preg_match('~~u',$l))Admin::get()->addError(lang(279));else{$O->updateParameter("exportFormat",$_POST["import_format"]);$Fb=array_keys($k);preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$l,$z);$za=count($z[0]);Driver::get()->begin();$Zj=($_POST["import_format"]=="csv;"?";":($_POST["import_format"]=="tsv"?"\t":","));$L=[];foreach($z[0]as$t=>$X){preg_match_all("~((?>\"[^\"]*\")+|[^$Zj]*)$Zj~",$X.$Zj,$ug);if(!$t&&!array_diff($ug[1],$Fb)){$Fb=$ug[1];$za--;}else{$kk=[];foreach($ug[1]as$p=>$_b)$kk[idf_escape($Fb[$p])]=($_b==""&&$k[$Fb[$p]]["null"]?"NULL":q(preg_match('~^".*"$~s',$_b)?str_replace('""','"',substr($_b,1,-1)):$_b));$L[]=$kk;}}$Jk=!$L||Driver::get()->insertUpdate($a,$L,$Ni);if($Jk)Driver::get()->commit();queries_redirect(remove_from_uri("page"),lang(280,$za),$Jk);Driver::get()->rollback();}}$Zk=Admin::get()->getTableName($R);if(is_ajax()){page_headers();ob_start();}else
page_header(lang(55).": $Zk",[$Zk]);$nf=null;if(isset($yj["insert"])||!support("table")){$nf=[];foreach((array)$_GET["where"]as$X){if(isset($ee[$X["col"]])&&count($ee[$X["col"]])==1&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$X["val"])))))$nf["preset"."[".bracket_escape($X["col"])."]"]=$X["val"];}}Admin::get()->printTableMenu($R,$nf);if(!$c&&support("table"))echo"<p class='error'>".lang(281).($k?".":": ".error())."\n";else{echo"<form id='form' action=''>\n","<div hidden>";hidden_fields_get();if(DB!=""){echo
input_hidden("db",DB);if(isset($_GET["ns"]))echo
input_hidden("ns",$_GET["ns"]);}echo
input_hidden("select",$a),'<input type="submit" class="button" value="'.h(lang(55)).'">',"</div>\n","<div class='field-sets'>\n";Admin::get()->printSelectionColumns($M,$c);Admin::get()->printSelectionSearch($Z,$Qj,$s);Admin::get()->printSelectionOrder($D,$Oh,$s);Admin::get()->printSelectionLimit($v);Admin::get()->printSelectionLength($rl);Admin::get()->printSelectionAction($s);echo"</div>\n</form>\n";$E=isset($_GET["page"])?$_GET["page"]:null;if($E=="last"){$ke=Connection::get()->getValue(count_rows($a,$Z,$wf,$xe));$E=(int)floor(max(0,intval($ke)-1)/$v);}else{$ke=false;$E=(int)$E;}$Rj=$M;$ye=$xe;if(!$Rj){$Rj[]="*";$Zb=convert_fields($c,$k,$M);if($Zb)$Rj[]=substr($Zb,2);}foreach($M
as$t=>$X){$j=$k[idf_unescape($X)];if($j&&($La=convert_field($j)))$Rj[$t]="$La AS $X";}if(DIALECT=="pgsql"||DIALECT=="mssql"){foreach((array)$_GET["columns"]as$t=>$X){if(isset($Rj[$t])&&$X["fun"])$Rj[$t].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$wf&&$Xl){foreach($Xl
as$t=>$X){$Rj[]=idf_escape($t);if($ye)$ye[]=idf_escape($t);}}$I=Driver::get()->select($a,$Rj,$Z,$ye,$D,$v,$E,true);if(!$I)echo"<p class='error'>".error()."\n";else{if(DIALECT=="mssql"&&$E)$I->seek($v*$E);echo"<form id='selection_form' action='' method='post' enctype='multipart/form-data'>\n","<div class='table-footer-parent'>\n";$L=[];while($K=$I->fetchAssoc()){if($E&&DIALECT=="oracle")unset($K["RNUM"]);$L[]=$K;}if($_GET["page"]!="last"&&$v&&$xe&&$wf&&DIALECT=="sql")$ke=Connection::get()->getValue(" SELECT FOUND_ROWS()");$fd=false;if(!$L)echo"<p class='message'>".lang(89)."\n";else{$Va=Admin::get()->getBackwardKeys($a,$Zk);echo"<div class='scrollable'>\n","<table id='table' class='nowrap checkable'>\n","<thead><tr>";if($xe||!$M){echo"<th class='actions'><input type='checkbox' id='all-page' class='jsonly'>".script("gid('all-page').onclick = partial(formCheck, /check/);","");if(Admin::get()->isDataEditAllowed())echo" <a href='",h($_GET["modify"]?remove_from_uri("modify"):$_SERVER["REQUEST_URI"]."&modify=1")."' title='",lang(282),"'>",icon_solo("edit-all"),"</a>";}$ch=[];$pe=[];reset($M);$dj=1;foreach($L[0]as$t=>$X){if(!isset($Xl[$t])){$Tj=key($M);$X=isset($_GET["columns"][$Tj])?$_GET["columns"][$Tj]:[];$j=$k[$M?($X?$X["col"]:current($M)):$t];$A=($j?Admin::get()->getFieldName($j,$dj):(isset($X["fun"])?"*":h($t)));if($A!=""){$dj++;$ch[$t]=$A;$b=idf_escape($t);$Re=remove_from_uri('(order|desc)[^=]*|page').'&order%5B0%5D='.urlencode($t);$Ac="&desc%5B0%5D=1";echo"<th id='th[".h(bracket_escape($t))."]'>";$oe=apply_sql_function(isset($X["fun"])?$X["fun"]:null,$A);$tk=isset($j["privileges"]["order"])||(isset($X["fun"])?$X["fun"]:null);if($tk)echo'<a href="',h($Re.($D[0]==$b||$D[0]==$t?$Ac:'')),'">',"$oe</a>";else
echo$oe;echo"<span class='column'>";if($tk)echo"<a href='".h($Re.$Ac)."' title='".lang(62)."' class='button light'>",icon_solo("arrow-down"),"</a>";if(!isset($X["fun"])&&isset($j["privileges"]["where"]))echo'<a href="#fieldset-search" title="'.lang(59).'" class="button light jsonly">',icon_solo("search"),'</a>',script("qsl('a').onclick = partial(selectSearch, '".js_escape($t)."');");echo"</span>";}$pe[$t]=isset($X["fun"])?$X["fun"]:null;next($M);}}$dg=[];if($_GET["modify"]){foreach($L
as$K){foreach($K
as$t=>$X)$dg[$t]=max($dg[$t],min(40,strlen(utf8_decode($X))));}}if($Va)echo"<th>".lang(17)."</th>";echo"</thead>\n","<tbody>\n";if(is_ajax())ob_end_clean();foreach(Admin::get()->fillForeignDescriptions($L,$ee)as$Zg=>$K){$Tl=unique_array($L[$Zg],$s);if(!$Tl){$Tl=[];reset($M);foreach($L[$Zg]as$t=>$X){if(!preg_match('~^(COUNT|AVG|GROUP_CONCAT|MAX|MIN|SUM)\(~',current($M)))$Tl[$t]=$X;next($M);}}$Ul="";foreach($Tl
as$t=>$X){$j=isset($k[$t])?$k[$t]:null;if((DIALECT=="sql"||DIALECT=="pgsql")&&$j&&preg_match('~char|text|enum|set~',$j["type"])&&strlen($X)>64){$t=(strpos($t,'(')?$t:idf_escape($t));$t="MD5(".(DIALECT!='sql'||preg_match("~^utf8~",isset($j["collation"])?$j["collation"]:"")?$t:"CONVERT($t USING ".charset(Connection::get()).")").")";$X=md5($X);}$Ul
.="&".($X!==null?urlencode("where[".bracket_escape($t)."]")."=".urlencode($X===false?"f":$X):"null%5B%5D=".urlencode($t));}echo"<tr>";if($xe||!$M){echo"<td class='actions'>",checkbox("check[]",substr($Ul,1),in_array(substr($Ul,1),(array)$_POST["check"]));if(!$wf&&Admin::get()->isDataEditAllowed())echo" <a href='",h(ME."edit=".urlencode($a).$Ul),"' class='edit' title='",lang(38),"'>",icon_solo("edit"),"</a>";}reset($M);foreach($K
as$t=>$X){if(isset($ch[$t])){$b=current($M);$j=isset($k[$t])?$k[$t]:null;$w="";if($j&&is_blob($j)&&$X!="")$w=ME.'download='.urlencode($a).'&field='.urlencode($t).$Ul;if(!$w&&$X!==null){foreach((array)$ee[$t]as$n){if(count($ee[$t])==1||end($n["source"])==$t){$w="";foreach($n["source"]as$p=>$uk)$w
.=where_link($p,$n["target"][$p],$L[$Zg][$uk]);$w=($n["db"]!=""?preg_replace('~([?&]db=)[^&]+~','\1'.urlencode($n["db"]),ME):ME).'select='.urlencode($n["table"]).$w;if($n["ns"])$w=preg_replace('~([?&]ns=)[^&]+~','\1'.urlencode($n["ns"]),$w);if(count($n["source"])==1)break;}}}if($b=="COUNT(*)"){$w=ME."select=".urlencode($a);$p=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$Tl))$w
.=where_link($p++,$W["col"],$W["val"],$W["op"]);}foreach($Tl
as$Ef=>$W)$w
.=where_link($p++,$Ef,$W);}$oh=$X===null;$Se=select_value($X,$w,$j,$rl);$sd=bracket_escape($t);$q=h("val[$Ul][$sd]");$Ii=isset($_POST["val"][$Ul][$sd])?$_POST["val"][$Ul][$sd]:null;$Zl=isset($j["privileges"]["update"])?$j["privileges"]["update"]:false;$dd=!is_array($K[$t])&&is_utf8($Se)&&$L[$Zg][$t]==$K[$t]&&!$pe[$t]&&!(isset($j["generated"])?$j["generated"]:false);$U=($b&&preg_match('~^(AVG|MIN|MAX)\((.+)\)~',$b,$z)?$k[idf_unescape($z[2])]["type"]:(isset($j["type"])?$j["type"]:null));$Tg=$U=="money"||($b&&preg_match('~^SUM\((.+)\)~',$b,$z)&&$k[idf_unescape($z[1])]["type"])=="money";$pl=$U&&preg_match('~text|json|lob~',$U);$qh=($U&&preg_match(number_type(),$U))||($b&&preg_match('~^(CHAR_LENGTH|ROUND|FLOOR|CEIL|UNIX_TIMESTAMP|TIME_TO_SEC|COUNT|SUM)\(~',$b));$yb=$qh&&($oh||is_numeric(strip_tags($Se))||$Tg)?"class='number'":"";echo"<td id='$q' $yb";if(($_GET["modify"]&&$dd&&!$oh)||$Ii!==null){$fd=true;$Ce=h($Ii!==null?$Ii:$K[$t]);echo" data-editing='true'>".($pl?"<textarea name='$q' cols='30' rows='".(substr_count($K[$t],"\n")+1)."'>$Ce</textarea>":"<input class='input' name='$q' value='$Ce' size='$dg[$t]'>");}else{$rg=strpos($Se,"<i>…</i>");if($Zl)echo" data-text='".($rg?2:($pl?1:0))."'".($dd?"":" data-warning='".h(lang(283))."'");echo">$Se";}}next($M);}if($Va){echo"<td>";Admin::get()->printBackwardKeys($Va,$L[$Zg]);echo"</td>";}echo"</tr>\n";}if(is_ajax())exit;echo"</tbody>\n",script("mixin(qs('#table tbody'), {onclick: partialArg(tableClick, false, ".(Admin::get()->isDataEditAllowed()?"true":"false")."), ondblclick: partialArg(tableClick, true), onkeydown: onEditingKeydown});"),"</table>\n",script("initToggles(gid('table'));"),"</div>\n";}if(!is_ajax()){if($L||$E){$ud=true;if($_GET["page"]!="last"){if(!$v||(count($L)<$v&&($L||!$E)))$ke=($E?$E*$v:0)+count($L);elseif(DIALECT!="sql"||!$wf){$ke=($wf?false:found_rows($R,$Z));if($ke<max(1e4,2*($E+1)*$v))$ke=first(slow_query(count_rows($a,$Z,$wf,$xe)));elseif(DIALECT=='sql'||DIALECT=='pgsql')$ud=false;}}$ei=($v!==null&&($ke===false||$ke>$v||$E));if($ei){if(($ke===false?count($L)+1:$ke-$E*$v)>$v)echo'<p class="links">','<a href="',h(remove_from_uri("page")."&page=".($E+1)),'" class="loadmore">',icon("expand"),lang(284),'</a>',script("qsl('a').onclick = partial(loadNextPage, $v, '".lang(285)."…');","");echo"\n";}echo"<div class='table-footer'><div class='field-sets'>\n";if($ei){$yg=($ke===false?$E+(count($L)>=$v?2:1):(int)floor(($ke-1)/$v));$Pc="<li>…</li>";echo"<fieldset>";if(DIALECT!="simpledb"){echo"<legend><a href='".h(remove_from_uri("page"))."'>".lang(286)."</a></legend>",script("qsl('a').onclick = function () { pageClick(this.href, +prompt('".lang(286)."', '".($E+1)."')); return false; };"),"<div id='fieldset-pagination' class='fieldset-content'><ul class='pagination'>",pagination(0,$E);if($E>5)echo$Pc;for($p=max(1,$E-4);$p<min($yg,$E+5);$p++)echo
pagination($p,$E);if($yg>0){if($E+5<$yg)echo$Pc;echo($ud&&$ke!==false?pagination($yg,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$yg'>".lang(287)."</a>");}echo"</ul></div>";}else{echo"<legend>".lang(286)."</legend>","<div id='fieldset-pagination'><ul class='pagination'>",pagination(0,$E);if($E>1)echo$Pc;if($E)echo
pagination($E,$E);if($yg>$E){echo
pagination($E+1,$E);if($yg>$E+1)echo$Pc;}echo"</ul></div>";}echo"</fieldset>\n";}echo"<fieldset>","<legend>".lang(288)."</legend><div class='fieldset-content'>";$Ic=($ud?"":"~ ").$ke;echo
checkbox("all",1,0,($ke!==false?($ud?"":"~ ").lang(187,$ke):""),"const checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$Ic' : checked); selectCount('selected2', this.checked || !checked ? '$Ic' : checked);")."\n","</div></fieldset>\n";if(Admin::get()->isDataEditAllowed()){echo"<fieldset",($_GET["modify"]?'':' class="jsonly"'),">","<legend>",lang(282),"</legend>";$Fj=($_GET["modify"]?"":" data-inline-edit='1'".($fd?"":" disabled"));echo"<div class='fieldset-content'",($_GET["modify"]?"":" title='".lang(278)."'"),">","<input type='submit' class='button' id='modify-save' value='",lang(113),"'",$Fj,">","</div>","</fieldset>\n","<fieldset>","<legend>",lang(159)," <span id='selected'></span></legend>","<div class='fieldset-content'>","<input type='submit' class='button' name='edit' value='",lang(38),"'> ","<input type='submit' class='button' name='clone' value='",lang(274),"'> ","<input type='submit' class='button' name='delete' value='",lang(117),"'>",confirm(),"</div>","</fieldset>\n";}$ge=Admin::get()->getDumpFormats();foreach((array)$_GET["columns"]as$b){if($b["fun"]){unset($ge['sql']);break;}}if($ge){print_fieldset_start("export",lang(74)." <span id='selected2'></span>","export");echo
html_select("format",$ge,$O->getParameter("exportFormat"));$bi=Admin::get()->getDumpOutputs();echo($bi?" ".html_select("output",$bi,$O->getParameter("exportOutput")):"")," <input type='submit' class='button' name='export' value='".lang(74)."'>\n";print_fieldset_end("export");}echo"</div></div>\n",script("initTableFooter()");}echo"</div>\n";if(Admin::get()->isDataEditAllowed()){echo"<p>","<a href='#import'>",icon("import"),lang(73),"</a>",script("qsl('a').onclick = partial(toggle, 'import');",""),"</p>","<p id='import'",($_POST["import"]?"":" class='hidden'"),">";if(ini_bool("file_uploads"))echo"<input type='file' name='csv_file'> ",html_select("import_format",["csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"],$O->getParameter("exportFormat"))," <input type='submit' class='button default' name='import' value='".lang(73)."'>",file_upload_form_script("selection_form","csv_file");else
echo
lang(194);echo"</p>";}echo
input_token(),"</form>\n",(!$xe&&$M?"":script("tableCheck();"));}else
echo"</div>\n";}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$P=isset($_GET["status"]);$T=$P?lang(152):lang(151);page_header($T,[$T]);$om=($P?Admin::get()->getStatusVariables():Admin::get()->getServerVariables());if(!$om)echo"<p class='message'>",lang(89),"</p>\n";else{echo"<div class='scrollable'><table>\n";foreach($om
as$K){echo"<tr>";$t=array_shift($K);echo"<th><code class='jush-".DIALECT.($P?"status":"set")."'>".h($t)."</code></th>";foreach($K
as$X)echo"<td>",nl2br(h($X)),"</td>";echo"</tr>\n";}echo"</table></div>\n";}}elseif(isset($_GET["script"])){header("Content-Type: text/javascript; charset=utf-8");if($_GET["script"]=="db"){$Mk=["Data_length"=>0,"Index_length"=>0,"Data_free"=>0];$e=[];$pc=null;foreach(table_status()as$A=>$R){$e["Comment-$A"]=h($R["Comment"]);if(!is_view($R)||preg_match('~materialized~i',$R["Engine"])){$e["Engine-$A"]=h($R["Engine"]);$Bb=isset($R["Collation"])?$R["Collation"]:"";if($Bb==""){if($pc===null)$pc=db_collation(DB,collations())??"";$Bb=$pc;}$e["Collation-$A"]=h($Bb);foreach($Mk+["Auto_increment"=>0,"Rows"=>0]as$t=>$X){if($R[$t]!=""){$X=format_number($R[$t]);if($X>=0)$e["$t-$A"]=($t=="Rows"?format_rows($R):$X);if(isset($Mk[$t]))$Mk[$t]+=($R["Engine"]!="InnoDB"||$t!="Data_free"?$R[$t]:0);}elseif(array_key_exists($t,$R))$e["$t-$A"]="?";}}}if(function_exists('AdminNeo\db_status'))$Mk=db_status();foreach($Mk
as$t=>$X)$e["sum-$t"]=format_number($X);echo
json_encode($e,JSON_UNESCAPED_UNICODE);}elseif($_GET["script"]=="kill")Connection::get()->query("KILL ".number($_POST["kill"]));else{$e=[];foreach(count_tables(Admin::get()->getDatabases())as$g=>$X){$e["tables-$g"]=$X;$e["size-$g"]=db_size($g);}echo
json_encode($e,JSON_UNESCAPED_UNICODE);}exit;}else{$il=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($il&&!$_POST["search"]){$I=true;$_="";if(DIALECT=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]||$_POST["truncate_cascade"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"],(bool)$_POST["truncate_cascade"]);$_=lang(289);}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$_=lang(290);}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$_=lang(291);}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$_=lang(292);}elseif(DIALECT=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$Q){foreach(get_rows("PRAGMA integrity_check(".q($Q).")")as$K)$_
.="<b>".h($Q)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(DIALECT!="sql"){$I=(DIALECT=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),$_POST["tables"]));$_=lang(293);}elseif(!$_POST["tables"])$_=lang(78);elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('AdminNeo\idf_escape',$_POST["tables"])))){while($K=$I->fetchAssoc())$_
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect($_SERVER["REQUEST_URI"],$_,(bool)$I);}if($_GET["ns"]=="")page_header(lang(30).": ".h(DB),true);else
page_header(lang(182).": ".h($_GET["ns"]),true);Admin::get()->printDatabaseMenu();if($_GET["ns"]===""){echo"<h2 id='schemas'>".lang(294)."</h2>\n";$Mj=Admin::get()->getSchemas();if(!$Mj)echo"<p class='message'>".lang(295)."\n";else{echo"<div class='scrollable'>\n","<table class='nowrap'>\n",'<thead><tr class="wrap"><th>',lang(182),"</th></tr></thead>";foreach($Mj
as$A)echo"<tr><th><a href='",h(ME),"ns=".urlencode($A),"' title='",lang(296),"'>".h($A)."</a></th></tr>";echo'</table></div>';}echo'<p class="links"><a href="'.h(ME).'scheme=">'.icon("database-add").lang(76)."</a>\n";}else{echo"<h2 id='tables-views'>".lang(297)."</h2>\n";$dl=['sql'=>'show-table-status.html','mariadb'=>'reference/sql-statements/administrative-sql-statements/show/show-table-status'];$pc=db_collation(DB,collations());$c=["Engine"=>["label"=>lang(163),"doc"=>doc_link(['sql'=>'storage-engines.html','mariadb'=>'server-usage/storage-engines']),],];if($pc!="")$c["Collation"]=["label"=>lang(45),"doc"=>doc_link(['sql'=>'charset-charsets.html','mariadb'=>'reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations']),];$c+=["Data_length"=>["label"=>lang(298),"doc"=>doc_link($dl+['pgsql'=>'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT','oracle'=>'REFRN20286']),"link"=>"create","title"=>lang(35),],"Index_length"=>["label"=>lang(299),"doc"=>doc_link($dl+['pgsql'=>'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT']),"link"=>"indexes","title"=>lang(167),],"Data_free"=>["label"=>lang(300),"doc"=>doc_link($dl),"link"=>"edit","title"=>lang(7),],"Auto_increment"=>["label"=>lang(47),"doc"=>doc_link(['sql'=>'example-auto-increment.html','mariadb'=>'reference/data-types/auto_increment']),"link"=>"auto_increment=1&create","title"=>lang(35),],"Rows"=>["label"=>lang(301),"doc"=>doc_link($dl+['pgsql'=>'catalog-pg-class.html#CATALOG-PG-CLASS','oracle'=>'REFRN20286']),"link"=>"select","title"=>lang(33),],];if(support("comment"))$c["Comment"]=["label"=>lang(46),"doc"=>doc_link($dl+['pgsql'=>'functions-info.html#FUNCTIONS-INFO-COMMENT-TABLE']),];$D=(is_string($_GET["order"])?$_GET["order"]:"");$Bc=null;if(preg_match('~^(.+)-(asc|desc)$~',$D,$y)){$D=$y[1];$Bc=($y[2]=="desc");}if($D!="__table"&&!isset($c[$D]))$D="";if($Bc===null)$Bc=isset($c[$D]["link"]);$Gm=($D!=""&&$D!="__table")||support("fast_status");$gl=($Gm?table_status():tables_list());if(!$gl)echo"<p class='message'>".lang(78)."\n";else{echo"<form action='' method='post'>\n","<div class='table-footer-parent'>\n";if(support("table")){echo"<div class='field-sets'>\n","<fieldset><legend>".lang(302)." <span id='selected2'></span></legend><div class='fieldset-content'>",html_select("op",Admin::get()->getOperators(),isset($_POST["op"])?$_POST["op"]:Driver::get()->getLikeOperator()),"<input type='search' class='input' name='query' value='".h($_POST["query"])."'>",script("qsl('input').onkeydown = partialArg(bodyKeydown, 'search');","")," <input type='submit' class='button' name='search' value='".lang(59)."'>\n","</div></fieldset>\n","</div>\n";if($_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable'>\n",'<thead><tr class="wrap">','<td class="actions"><input id="check-all" type="checkbox" class="input jsonly">'.script("gid('check-all').onclick = partial(formCheck, /^(tables|views)\[/);","");$ah=($D==""||$D=="__table");$Yk=($ah&&!$Bc?ME."order=__table-desc":substr(ME,0,-1));echo'<th><a href="'.h($Yk).'">'.lang(8).'</a>';foreach($c
as$t=>$b){$Dc=($t===$D?!$Bc:isset($b["link"]));echo'<td><a href="'.h(ME)."order=$t-".($Dc?"desc":"asc").'">'.$b["label"].'</a>'.$b["doc"];}echo"</thead>\n","<tbody>\n";if($D=="__table"){if($Bc)$gl=array_reverse($gl,true);}elseif($D){uasort($gl,function($sa,$Sa)use($D,$Bc){$Hm=isset($sa[$D])?$sa[$D]:null;$Im=isset($Sa[$D])?$Sa[$D]:null;$I=($Hm<$Im?-1:($Hm>$Im?1:0));return($Bc?-$I:$I);});}$Mk=["Data_length"=>0,"Index_length"=>0,"Data_free"=>0];$S=0;foreach($gl
as$A=>$P){$sm=($Gm?is_view($P):$P!==null&&!preg_match('~table|sequence~i',$P));$ld=($Gm?(isset($P["Engine"])?$P["Engine"]:""):$P);$q=h("Table-".$A);echo'<tr><td class="actions">'.checkbox(($sm?"views[]":"tables[]"),$A,in_array("$A",$il,true),"","","",$q);if(!Admin::get()->getSettings()->isSelectionPreferred()&&(support("table")||support("indexes")))$ua="table";else$ua="select";echo"<th><a href='",h(ME),"$ua=",urlencode($A),"' id='$q'>",h($A),"</a></th>";if($sm&&!preg_match('~materialized~i',$ld)){$T=lang(162);$Gb=count($c)-(support("comment")?2:1);echo'<td colspan="'.$Gb.'">'.(support("view")?"<a href='".h(ME)."view=".urlencode($A)."' title='".lang(36)."'>$T</a>":$T),'<td align="right"><a href="'.h(ME)."select=".urlencode($A).'" title="'.lang(33).'">?</a>';}else{foreach($c
as$t=>$b){if($t=="Comment")continue;$q=" id='$t-".h($A)."'";$w=isset($b["link"])?$b["link"]:"";if(!$w){$X="";if($Gm){$X=isset($P[$t])?$P[$t]:"";if($t=="Collation"&&$X=="")$X=$pc;}echo"<td$q>".h($X);continue;}$X="?";if($Gm){$B=isset($P[$t])?$P[$t]:"";if(is_numeric($B)&&$B>=0){$X=($t=="Rows"?format_rows($P):format_number($B));if(isset($Mk[$t])&&($ld!="InnoDB"||$t!="Data_free"))$Mk[$t]+=$B;}}echo"<td align='right'>".(support("table")||$t=="Rows"||(support("indexes")&&$t!="Data_length")?"<a href='".h(ME."$w=").urlencode($A)."'$q title='".$b["title"]."'>".h($X)."</a>":"<span$q>".h($X)."</span>");}$S++;}echo(support("comment")?"<td id='Comment-".h($A)."'>".($Gm?h(isset($P["Comment"])?$P["Comment"]:""):""):""),"\n";}echo"</tbody>\n",script("mixin(qsl('tbody'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});"),"<tfoot><tr>","<td><th>".lang(275,count($gl)),"<td>".h(DIALECT=="sql"?Connection::get()->getValue("SELECT @@default_storage_engine"):""),($pc!=""?"<td>".h($pc):"");if($Gm&&function_exists('AdminNeo\db_status'))$Mk=db_status();foreach($Mk
as$t=>$Lk)echo"<td align='right' id='sum-$t'>".($Gm?format_number($Lk):"");echo"<td></td><td></td>";if(support("comment"))echo"<td></td>";echo"</tr></tfoot>\n","</table>\n","</div>\n",($Gm?"":script("ajaxSetHtml('".js_escape(ME)."script=db');"));if(Admin::get()->isDataEditAllowed()){echo"<div class='table-footer'><div class='field-sets'>\n";$lm="<input type='submit' class='button' value='".lang(303)."'> ".help_script("VACUUM");$Kh="<input type='submit' class='button' name='optimize' value='".lang(304)."'> ".help_script(DIALECT=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE");echo"<fieldset><legend>".lang(159)." <span id='selected'></span></legend><div class='fieldset-content'>".(DIALECT=="sqlite"?$lm."<input type='submit' class='button' name='check' value='".lang(305)."'> ".help_script("PRAGMA integrity_check"):(DIALECT=="pgsql"?$lm.$Kh:(DIALECT=="sql"?"<input type='submit' class='button' value='".lang(306)."'> ".help_script("ANALYZE TABLE").$Kh."<input type='submit' class='button' name='check' value='".lang(305)."'> ".help_script("CHECK TABLE")."<input type='submit' class='button' name='repair' value='".lang(307)."'> ".help_script("REPAIR TABLE"):"")))."<input type='submit' class='button' name='truncate' value='".lang(308)."'> ".help_script(DIALECT=="sqlite"?"DELETE":("TRUNCATE".(DIALECT=="pgsql"?"":" TABLE"))).confirm().(DIALECT=="pgsql"?"<input type='submit' class='button' name='truncate_cascade' value='".lang(309)."'> ".help_script("TRUNCATE CASCADE").confirm():"")."<input type='submit' class='button' name='drop' value='".lang(160)."'>".help_script("DROP TABLE").confirm()."\n";$f=(support("scheme")?Admin::get()->getSchemas():Admin::get()->getDatabases());echo"</div></fieldset>\n";$Oj="";if(count($f)!=1&&DIALECT!="sqlite"){echo"<fieldset><legend>".lang(310)." <span id='selected3'></span></legend><div>";$g=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($f?html_select("target",$f,$g,"","label-move"):'<input class="input" name="target" value="'.h($g).'" autocapitalize="off">')," <input type='submit' class='button' name='move' value='".lang(311)."'>",(support("copy")?" <input type='submit' class='button' name='copy' value='".lang(312)."'> ".checkbox("overwrite",1,$_POST["overwrite"],lang(313)):""),"</div></fieldset>\n";$Oj=" selectCount('selected3', formChecked(this, /^(tables|views)\[/));";}echo
input_hidden("all"),script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^(tables|views)\[/));".(support("table")?" selectCount('selected2', formChecked(this, /^tables\[/) || $S);":"")."$Oj }"),input_token(),"</div></div>\n",script("initTableFooter()");}echo"</div>\n","</form>\n",script("tableCheck();");}echo'<p class="links"><a href="',h(ME),'create=">',icon("table-add"),lang(77),"</a>\n";if(support("view"))echo'<a href="',h(ME),'view=">',icon("view-add"),lang(237),"</a>\n";if(support("routine")){echo"<h2 id='routines'>".lang(178)."</h2>\n";$Bj=routines();if($Bj){$Mb=$Bj[0]["ROUTINE_COMMENT"]!==null;echo"<table>\n",'<thead><tr>','<th>',lang(217),'</th><td>',lang(44),'</td><td>',lang(254),"</td>";if($Mb)echo"<td>",lang(46),"</td>";echo"<td></td>","</tr></thead>\n";foreach($Bj
as$K){$A=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".urlencode($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="',h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').urlencode($K["SPECIFIC_NAME"]).$A),'">',h($K["ROUTINE_NAME"]),'</a></th>','<td>',h($K["ROUTINE_TYPE"]),'</td>','<td>',h($K["DTD_IDENTIFIER"]),'</td>';if($Mb)echo'<td>',truncate_utf8(preg_replace('~\s{2,}~'," ",trim($K["ROUTINE_COMMENT"])),50),'</td>';echo'<td><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').urlencode($K["SPECIFIC_NAME"]).$A).'">'.lang(170)."</a></td>";}echo"</table>\n";}echo'<p class="links">';if(support("procedure"))echo'<a href="',h(ME),'procedure=">',icon("function-add"),lang(253),"</a>";echo'<a href="',h(ME),'function=">',icon("function-add"),lang(252),"</a>\n","</p>\n";}if(support("event")){echo"<h2 id='events'>".lang(179)."</h2>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".lang(217)."<td>".lang(314)."<td>".lang(243)."<td>".lang(244)."<td></thead>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?lang(315)."<td>".h($K["Execute at"]):lang(245)." ".h($K["Interval value"])." ".h($K["Interval field"])."<td>".h($K["Starts"])),"<td>".h($K["Ends"]),'<td><a href="'.h(ME).'event='.urlencode($K["Name"]).'">'.lang(170).'</a>';echo"</table>\n";$td=Connection::get()->getValue("SELECT @@event_scheduler");if($td&&$td!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($td)."\n";}echo'<p class="links"><a href="',h(ME),'event=">',icon("event-add"),lang(242),"</a></p>\n";}}}page_footer();