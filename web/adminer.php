<?php
/**
 * AdminNeo - Powerful database manager in a single PHP file
 * v5.6.0
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
inject($xa,Config$Qb,Settings$P,Locale$lg){$this->admin=$xa;$this->config=$Qb;$this->settings=$P;$this->locale=$lg;}}abstract
class
Origin
extends
Plugin{private$errors=[];private
static$instance=null;static
function
create(array$Qb=[],array$vi=[]){if(self::$instance)die("Admin instance already exists.\n");$xa=new
static();if(!$Qb&&file_exists("adminneo-config.php")){$Qb=include_once("adminneo-config.php");if(!is_array($Qb)){$Qb=[];$dg="href=https://github.com/adminneo-org/adminneo#configuration ".target_blank();$xa->addError(lang(0,"<b>adminneo-config.php</b>")." <a $dg>".lang(1)."</a>");}}$Qb=new
Config($Qb);$P=new
Settings($Qb);if(!$vi&&file_exists("adminneo-plugins.php")){$vi=include_once("adminneo-plugins.php");if(!is_array($vi)){$vi=[];$dg="href=https://github.com/adminneo-org/adminneo#plugins ".target_blank();$xa->addError(lang(0,"<b>adminneo-plugins.php</b>")." <a $dg>".lang(1)."</a>");}}self::$instance=$vi?new
Pluginer($xa,$vi):$xa;$xa->inject(self::$instance,$Qb,$P,Locale::get());foreach($vi
as$ui)$ui->inject(self::$instance,$Qb,$P,Locale::get());return
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
verifyDefaultPassword($F){$De=$this->config->getDefaultPasswordHash();if($De===null||$De==="")return
lang(2);elseif(!password_verify($F,$De))return
lang(3);return
true;}function
authenticate($V,$F){if($F==""){$De=$this->config->getDefaultPasswordHash();if($De===null)return
lang(4,target_blank());else
return$De==="";}return
true;}function
getPrivateKey($ac=false){return
get_private_key($ac);}function
getBruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
getServerName($N,$kj=true,$Ed=null){if($N==""){if(!$kj)return"";$N=Connection::exists()?Connection::get()->getDefaultServerName():"";if($N=="")return$Ed!==null?$Ed:lang(5);$Uj=null;}else$Uj=$this->config->getServer($N);return$Uj?$Uj->getName():preg_replace('~^https?://~',"",$N);}abstract
function
getDatabase();function
getDatabases($Xd=true){return$this->filterListWithWildcards(get_databases($Xd),$this->config->getHiddenDatabases(),false,Driver::get()->getSystemDatabases());}function
getSchemas($gh=false){$Ge=$this->config->getHiddenSchemas();if($gh&&!in_array("__system",$Ge))$Ge[]="__system";return$this->filterListWithWildcards(schemas(),$Ge,false,Driver::get()->getSystemSchemas());}function
getCollations(array$Af=[]){$om=$this->config->getVisibleCollations();$Rd=$om?array_merge($om,$Af):[];return$this->filterListWithWildcards(collations(),$Rd,true);}private
function
filterListWithWildcards(array$fm,array$Rd,$Cf,array$Kk=[]){if(!$fm||!$Rd)return$fm;$r=array_search("__system",$Rd);if($r!==false){unset($Rd[$r]);$Rd=array_merge($Rd,$Kk);}array_walk($Rd,function(&$Y){$Y=str_replace('\\*',".*",preg_quote($Y,"~"));});$oi='~^('.implode("|",$Rd).')$~';return$this->filterListWithPattern($fm,$oi,$Cf);}private
function
filterListWithPattern(array$fm,$oi,$Cf){$I=[];foreach($fm
as$t=>$Y){if(is_array($Y)){if($Ak=$this->filterListWithPattern($Y,$oi,$Cf))$I[$t]=$Ak;}elseif(($Cf&&preg_match($oi,$Y))||(!$Cf&&!preg_match($oi,$Y)))$I[$t]=$Y;}return$I;}abstract
function
getQueryTimeout();function
sendHeaders(){}function
updateCspHeader(array&$ec){}function
printFavicons(){$Bb=validate_color_variant($this->config->getColorVariant());echo"<link rel='icon' type='image/x-icon' href='",link_files("favicon-$Bb.ico",[]),"' sizes='32x32'>\n","<link rel='icon' type='image/svg+xml' href='",link_files("favicon-$Bb.svg",[]),"'>\n","<link rel='apple-touch-icon' href='",link_files("apple-touch-icon-$Bb.png",[]),"'>\n";}abstract
function
printToHead();function
getCssUrls(){$Ul=$this->config->getCssUrls();foreach(["adminneo.css","adminneo-light.css","adminneo-dark.css"]as$m){if(file_exists($m))$Ul[]="$m?v=".filemtime($m);}return$Ul;}function
isLightModeForced(){return$this->isColorSchemeForced(false);}function
isDarkModeForced(){return$this->isColorSchemeForced(true);}private
function
isColorSchemeForced($jc){$Mg=$jc?Settings::$ColorSchemeDark:Settings::$ColorSchemeLight;$Ng=$jc?Settings::$ColorSchemeLight:Settings::$ColorSchemeDark;$Nd=file_exists("adminneo-$Mg.css");$Od=file_exists("adminneo-$Ng.css");if($Nd&&!$Od)return
true;return$this->settings->getColorScheme()==$Mg&&!($Nd
xor$Od);}function
getJsUrls(){$Ul=$this->config->getJsUrls();$m="adminneo.js";if(file_exists($m))$Ul[]="$m?v=".filemtime($m);return$Ul;}abstract
function
printLoginForm();function
getLoginFormRow($Id,$Kf,$j){if($Kf)return"<tr><th>$Kf</th><td>$j</td></tr>\n";else
return"$j\n";}function
printLogout(){echo"<div class='logout'>","<form action='' method='post'>\n",h($_GET["username"]),"<input type='submit' class='button' name='logout' value='",lang(6),"' id='logout'>",input_token(),"</form>","</div>\n";}function
getTableName(array$Ok){return
h($Ok["Name"]);}abstract
function
getFieldName(array$j,$D=0);function
formatComment($Ib){return
h($Ib);}abstract
function
printTableMenu(array$Ok,$O="");function
getForeignKeys($Q){return
foreign_keys($Q);}function
getBackwardKeys($Q,$Mk){if(!$this->settings->isRelationLinks())return[];$L=backward_keys($Q);$Ef=[];foreach($L
as$K){$q=$K["table_schema"].".".$K["table_name"];$Ef[$q]["schema"]=$K["table_schema"];$Ef[$q]["table"]=$K["table_name"];$Ef[$q]["constraints"][$K["constraint_name"]][$K["column_name"]]=$K["referenced_column_name"];}foreach($Ef
as$q=>$t){$A=$this->admin->getTableName(table_status1($t["table"],true));if($A!=""){$Hj=preg_quote($Mk);$Rj="(:|\\s*-)?\\s+";$Ef[$q]["name"]=(preg_match("(^$Hj$Rj(.+)|^(.+?)$Rj$Hj\$)iu",$A,$y)?$y[2].$y[3]:$A);}else
unset($Ef[$q]);}return$Ef;}function
printBackwardKeys(array$Sa,array$K){foreach($Sa
as$t){foreach($t["constraints"]as$Tb){$zg=preg_replace('~&ns=[^&]+&~',"&ns=".urldecode($t["schema"])."&",ME);$w=$zg.'select='.urlencode($t["table"]);$p=0;foreach($Tb
as$b=>$X){if(!isset($K[$X]))continue
2;$w
.=where_link($p++,$b,$K[$X]);}$A=preg_replace('(^'.preg_quote($_GET["select"]).(substr($_GET["select"],-1)=="s"?"?":"").'_)',"_",$t["name"]);$T=implode(", ",array_keys($Tb));echo"<a href='".h($w)."' title='".h($T)."'>".h($A)."</a>";$w=$zg.'edit='.urlencode($t["table"]);foreach($Tb
as$b=>$X)$w
.="&set".urlencode("[".bracket_escape($b)."]")."=".urlencode($K[$X]);echo"<a href='".h($w)."' title='".lang(7)."'>",icon_solo("add"),"</a> ";}}}abstract
function
formatSelectQuery($H,$sk,$Dd=false);abstract
function
formatMessageQuery($H,$nl,$Dd=false);abstract
function
formatSqlCommandQuery($H);function
printAfterSqlCommand(){}abstract
function
getTableDescriptionFieldName($Q);abstract
function
fillForeignDescriptions(array$L,array$ae);function
getFieldValueLink($X,$j){if(is_mail($X))return"mailto:$X";if(is_web_url($X))return$X;return
null;}abstract
function
formatSelectionValue($X,$w,$j,$Sh);abstract
function
formatFieldValue($Y,array$j);abstract
function
printTableStructure(array$k);abstract
function
printTablePartitions(array$ei);abstract
function
printRelatedTables(array$S);abstract
function
printTableIndexes(array$s,array$Ok);abstract
function
printSelectionColumns(array$M,array$c);abstract
function
printSelectionSearch(array$Z,array$c,array$s);abstract
function
printSelectionOrder(array$D,array$c,array$s);abstract
function
printSelectionLimit($v);abstract
function
printSelectionLength($il);abstract
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
getFieldInput($Q,array$j,$Ka,$Y,$o);function
getFieldInputHint($Q,array$j,$Y){return
support("comment")?$this->admin->formatComment($j["comment"]):"";}abstract
function
processFieldInput(array$j,$Y,$o="");function
detectJson($Jd,&$Y,$Ei=null){if(is_array($Y)){$Vd=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|($this->config->isJsonValuesAutoFormat()?JSON_PRETTY_PRINT:0);$Y=json_encode($Y,$Vd);return
true;}$Vd=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|($Ei?JSON_PRETTY_PRINT:0);if(preg_match('~^jsonb?$~',$Jd)){if($Y!=null&&$Ei!==null&&$this->config->isJsonValuesAutoFormat())$Y=json_encode(json_decode($Y),$Vd);return
true;}if(!$this->config->isJsonValuesDetection())return
false;if(is_string($Y)&&$Y!=""&&preg_match('~varchar|text|character varying|String|keyword~',$Jd)&&($Y[0]=="{"||$Y[0]=="[")&&($zf=json_decode($Y))){if($Ei!==null&&$this->config->isJsonValuesAutoFormat())$Y=json_encode($zf,$Vd);return
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
sendDumpHeaders($Re,$Qg=false);function
dumpDatabase($mc){}abstract
function
dumpTable($Q,$_k,$lm=0);abstract
function
dumpData($Q,$_k,$H);abstract
function
getImportFilePath();abstract
function
printDatabaseMenu();function
printNavigation($Kg){$Rf=isset($_COOKIE["neo_version"])?$_COOKIE["neo_version"]:null;echo"<div class='header'>\n",$this->admin->getServiceTitle()."\n";if($Kg!="auth"){echo"<span class='version'>",h(preg_replace('~\\.0(-|$)~','$1',VERSION));if($this->config->isVersionVerificationEnabled()&&$Rf&&version_compare(VERSION,$Rf)<0)echo"<a id='version' class='version-badge' href='https://www.adminneo.org/download' ".target_blank()." title='".h($Rf)."'>",icon_solo("asterisk"),"</a>";echo"</span>\n";if($this->config->isVersionVerificationEnabled()&&!$Rf)echo
script("verifyVersion('".js_escape(ME)."', '".get_token()."');");}echo"</div>\n";}abstract
function
printDatabaseSwitcher($Kg);function
printTablesFilter(){echo"<div class='tables-filter jsonly'>"."<input id='tables-filter' type='search' class='input' autocomplete='off' placeholder='".lang(8)."'>".script("initTablesFilter(".json_encode($this->admin->getDatabase()).");")."</div>\n";}abstract
function
printTableList(array$S);function
getSettingsRows($we){$P=[];if($we==1){$C=get_language_options();if($C)$P["lang"]="<tr><th id='label-language'>".lang(9)."</th>"."<td>".html_select("lang",get_language_options(),Locale::get()->getLanguage(),"","label-language")."</td></tr>\n";$C=[""=>lang(10),Settings::$ColorSchemeLight=>lang(11),Settings::$ColorSchemeDark=>lang(12)];$P["colorScheme"]="<tr><th>".lang(13)."</th>"."<td>".html_radios("colorScheme",$C,($qa=$this->settings->getParameter("colorScheme"))!==null?$qa:"")."</td></tr>\n";}elseif($we==2){$C=[""=>lang(14),true=>lang(15),false=>lang(16),];$h=$C[$this->config->isRelationLinks()];$C[""].=" ($h)";$P["relationLinks"]="<tr><th>".lang(17)."</th>"."<td>".html_radios("relationLinks",$C,($qa=$this->settings->getParameter("relationLinks"))!==null?$qa:"")."<span class='input-hint'>".lang(18)."</span>"."</td></tr>\n";$h=$this->config->getRecordsPerPage();$C=[""=>lang(14)." ($h)","20","30","50","70","100",];$P["recordsPerPage"]="<tr><th id='label-records'>".lang(19)."</th>"."<td>".html_select("recordsPerPage",$C,($qa=$this->settings->getParameter("recordsPerPage"))!==null?$qa:"","","label-records")."<span class='input-hint'>".lang(20)."</span>"."</td></tr>\n";$h=($qa=$this->config->getEnumAsSelectThreshold())!==null?$qa:lang(21);$C=[""=>lang(14)." ($h)",-1=>lang(21),0=>lang(22),3=>lang(23,3),5=>lang(23,5),10=>lang(23,10),20=>lang(23,20),];$P["enumAsSelectThreshold"]="<tr><th id='label-enum'>".lang(24)."</th>"."<td>".html_select("enumAsSelectThreshold",$C,($qa=$this->settings->getParameter("enumAsSelectThreshold"))!==null?$qa:"","","label-enum",true)."<span class='input-hint'>".lang(25)."</span>"."</td></tr>\n";}return$P;}abstract
function
getForeignColumnInfo(array$ae,$b);}class
Pluginer{private
static$InternalMethods=["inject"=>true,"getConfig"=>true,];private
static$AppendMethods=["getErrors"=>true,"getFieldFunctions"=>true,"getDumpOutputs"=>true,"getDumpFormats"=>true,"getSettingsRows"=>true,];private$plugins;private$hooks=[];function
__construct(Origin$xa,array$vi){$this->plugins=$vi;foreach(get_class_methods('\AdminNeo\Origin')as$Ig){$this->hooks[$Ig]=[];if(!(isset(self::$InternalMethods[$Ig])?self::$InternalMethods[$Ig]:false)){foreach($vi
as$ui){if(method_exists($ui,$Ig))$this->hooks[$Ig][]=$ui;}}if(isset(self::$AppendMethods[$Ig])?self::$AppendMethods[$Ig]:false)array_unshift($this->hooks[$Ig],$xa);else$this->hooks[$Ig][]=$xa;}}function
getPlugins(){return$this->plugins;}function
__call($A,array$Zh){$Ga=isset(self::$AppendMethods[$A])?self::$AppendMethods[$A]:false;$I=$Ga?[]:null;assert(isset($this->hooks[$A]),"Calling unknown plugin method: $A");foreach($this->hooks[$A]as$ui){$Y=call_user_func_array([$ui,$A],$Zh);if($Y!==null){if($Ga)$I+=$Y;else
return$Y;}}return$I;}function
updateCspHeader(array&$ec){$this->__call(__FUNCTION__,[&$ec]);}function
detectJson($Jd,&$Y,$Ei=null){return$this->__call(__FUNCTION__,[$Jd,&$Y,$Ei]);}}class
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
printLoginForm(){$Qc=Drivers::getList();$Vj=$this->config->getServerPairs($Qc);$N=SERVER?:$this->config->getDefaultServer();echo"<table class='box box-light'>\n";if($Vj)echo$this->admin->getLoginFormRow('server',lang(5),"<select name='auth[server]'>".optionlist($Vj,$N,true)."</select>");else{$Oc=DRIVER?:$this->config->getDefaultDriver($Qc);if(count($Qc)>1)echo$this->admin->getLoginFormRow('driver',lang(26),html_select("auth[driver]",$Qc,$Oc).script("initLoginDriver(qsl('select'));",""));else
echo$this->admin->getLoginFormRow('driver','',input_hidden("auth[driver]",$Oc));echo$this->admin->getLoginFormRow('server',lang(5),'<input class="input" name="auth[server]" value="'.h($N).'" title="'.lang(27).'" placeholder="localhost" autocapitalize="off">');}echo$this->admin->getLoginFormRow('username',lang(28),'<input class="input" name="auth[username]" id="username" value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),$this->admin->getLoginFormRow('password',lang(29),'<input type="password" class="input" name="auth[password]" autocomplete="current-password">');if(!$Vj){$mc=isset($_GET["db"])?$_GET["db"]:$this->config->getDefaultDatabase();echo$this->admin->getLoginFormRow('db',lang(30),'<input class="input" name="auth[db]" value="'.h($mc).'" autocapitalize="off">');}echo"</table>\n","<p>","<input type='submit' class='button default' value='".lang(31)."'>",checkbox("auth[permanent]",1,$_COOKIE["neo_permanent"],lang(32)),"</p>\n";}function
getFieldName(array$j,$D=0){$U=$j["full_type"].($j["null"]?" NULL":"");$Ib=$j["comment"];$Rj=$U&&$Ib!=""?": ":"";return'<span title="'.h($U.$Rj.$Ib).'">'.h($j["field"]).'</span>';}function
printTableMenu(array$Ok,$O=""){echo'<p class="links top-tabs">';$eg=[];$Nj=($this->settings->isSelectionPreferred()&&!$this->settings->isNavigationReversed())||(!$this->settings->isSelectionPreferred()&&$this->settings->isNavigationReversed());if($Nj)$eg["select"]=[lang(33),"data"];if(support("table")||support("indexes"))$eg["table"]=[lang(34),"structure"];if(!$Nj)$eg["select"]=[lang(33),"data"];$Q=$Ok["Name"];$vf=false;if(support("table")){$vf=is_view($Ok);if(!$vf){if($Q!="")$eg["create"]=[lang(35),"edit"];}elseif(support("view"))$eg["view"]=[lang(36),"edit"];}if($O!==null)$eg["edit"]=[lang(7),"item-add"];foreach($eg
as$t=>$X)echo" <a href='",h(ME),"$t=",urlencode($Q),($t=="edit"?$O:""),"'",bold(isset($_GET[$t])),">",icon($X[1]),"$X[0]</a>";echo
doc_link([DIALECT=>Driver::get()->tableHelp($Q,$vf)],icon("help").lang(37)),"\n";}function
formatSelectQuery($H,$sk,$Dd=false){$Fk=support("sql");$sm=!$Dd?Driver::get()->warnings():null;if($Fk)$H
.=";";$Ik=DIALECT=="elastic"||DIALECT=="mongo"?"json":DIALECT;$J="<pre><code class='jush-$Ik'>".h(str_replace("\n"," ",$H))."</code></pre>\n";$J
.="<p class='links'>";if($Fk)$J
.="<a href='".h(ME)."sql=".urlencode($H)."'>".icon("edit").lang(38)."</a>";if($sm)$J
.="<a href='#warnings' class='toggle'>".lang(39).icon_chevron_down()."</a>";$J
.=" <span class='time'>(".format_time($sk).")</span>";$J
.="</p>\n";if($sm){$J
.=script("initToggles(qsl('p'));");$J
.="<div id='warnings' class='warnings hidden'>\n$sm\n</div>\n";}return$J;}function
formatMessageQuery($H,$nl,$Dd=false){restart_session();$Ie=&get_session("queries");if(!isset($Ie[$_GET["db"]]))$Ie[$_GET["db"]]=[];if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$Ie[$_GET["db"]][]=[$H,time(),$nl];$Fk=support("sql");$sm=!$Dd?Driver::get()->warnings():null;$pk="sql-".count($Ie[$_GET["db"]]);$tm="warnings-".count($Ie[$_GET["db"]]);$J=" ";if($sm)$J
.="<a href='#$tm' class='toggle'>".lang(39).icon_chevron_down()."</a>, ";$Qi=support("sql")?lang(40):lang(41);$J
.="<a href='#$pk' class='toggle'>$Qi".icon_chevron_down()."</a>";$J
.=" <span class='time'>".@date("H:i:s")."</span>\n";if($sm)$J
.="<div id='$tm' class='warnings hidden'>\n$sm</div>\n";$J
.="<div id='$pk' class='hidden'>\n";$Ik=DIALECT=="elastic"||DIALECT=="mongo"?"json":DIALECT;$J
.="<pre><code class='jush-$Ik'>".truncate_utf8($H,1000)."</code></pre>\n";$J
.="<p class='links'>";if($Fk)$J
.="<a href='".h(str_replace("db=".urlencode(DB),"db=".urlencode($_GET["db"]),ME).'sql=&history='.(count($Ie[$_GET["db"]])-1))."'>".icon("edit").lang(38)."</a>";if($nl)$J
.=" <span class='time'>($nl)</span>";$J
.="</p>\n";$J
.="</div>\n";return$J;}function
formatSqlCommandQuery($H){if(preg_match('~^DELIMITER\s~i',$H))return"";return
truncate_utf8($H,1000);}function
getTableDescriptionFieldName($Q){return"";}function
fillForeignDescriptions(array$L,array$ae){return$L;}function
formatSelectionValue($X,$w,$j,$Sh){if($X===null)$hl="<i>NULL</i>";elseif(!$j)$hl=$X;elseif(preg_match("~char|binary|boolean~",$j["type"])&&!preg_match("~var~",$j["type"]))$hl="<code>$X</code>";elseif(is_blob($j)&&!is_utf8($X))$hl="<i>".lang(42,strlen($Sh))."</i>";elseif($this->admin->detectJson($j["full_type"],$Sh))$hl="<code class='jush-json'>$X</code>";else$hl=$X;if($w)$hl="<a href='".h($w)."'".(is_web_url($w)?target_blank():"").">$hl</a>";return$hl;}function
formatFieldValue($Y,array$j){return$Y;}function
printTableStructure(array$k){echo"<div class='scrollable'>\n","<table class='nowrap'>\n","<thead><tr>","<th>",lang(43),"</th>","<td>",lang(44),"</td>","<td>",lang(45),"</td>";if(support("comment"))echo"<td>",lang(46),"</td>";echo"</tr></thead>\n";$am=Driver::get()->getUserTypes();foreach($k
as$j){echo"<tr>","<th>",h($j["field"]),"</th>","<td>";$U=h($j["full_type"]);if(in_array($U,$am))echo"<a href='".h(ME.'type='.urlencode($U))."'>$U</a>";else
echo$U;if($j["null"])echo" <i>NULL</i>";if($j["auto_increment"])echo" <i>".lang(47)."</i>";$h=h($j["default"]);if(isset($j["default"]))echo" <span title='".lang(48)."'>[<b>",$j["generated"]?"<code class='jush-".DIALECT."'>$h</code>":$h,"</b>]</span>";echo"</td>","<td>",h($j["collation"]),"</td>";if(support("comment"))echo"<td>",$this->admin->formatComment($j["comment"]),"</td>";echo"\n";}echo"</table>\n","</div>\n";}function
printTablePartitions(array$ei){$ek=isset($ei["partition_names"]);echo"<p>","<code class='jush-".DIALECT."'>BY {$ei["partition_by"]} ({$ei["partition"]})</code>";if(!$ek&&isset($ei["partitions"]))echo" ".lang(49).": ".h($ei["partitions"]);echo"</p>";if($ek){echo"<table>\n","<thead><tr><th>".lang(50)."</th><td>".lang(51)."</td></tr></thead>\n";foreach($ei["partition_names"]as$t=>$A){echo"<tr><th>";if(DIALECT=="pgsql")echo"<a href='",h(ME."table=".urlencode($A)),"'>";echo
h($A);if(DIALECT=="pgsql")echo"</a>";echo"</th><td>".h($ei["partition_values"][$t])."\n";}echo"</table>\n";}}function
printRelatedTables(array$S){echo"<ul class='links'>\n";foreach($S
as$K){$w=preg_replace('~ns=[^&]*~',"ns=".urlencode($K["ns"]),ME);echo"<li><a href='",h($w."table=".urlencode($K["table"])),"'>",icon("structure");if($K["ns"]!=$_GET["ns"])echo"<b>".h($K["ns"])."</b>.";echo
h($K["table"]),"</a>";}echo"</ul>\n";}function
printTableIndexes(array$s,array$Ok){$rc=first(Driver::get()->getIndexAlgorithms($Ok));$ci=false;foreach($s
as$r){if(isset($r["partial"])?$r["partial"]:false){$ci=true;break;}}echo"<table>\n","<thead><tr>","<th>",lang(44),"</th>","<td>",lang(52)," (",lang(53),")</td>";if($ci)echo"<td>",lang(54),"</td>";echo"</tr></thead>\n";foreach($s
as$A=>$r){ksort($r["columns"]);$Gi=[];foreach($r["columns"]as$t=>$X)$Gi[]="<i>".h($X)."</i>".($r["lengths"][$t]?"(".$r["lengths"][$t].")":"").($r["descs"][$t]?" DESC":"");echo"<tr title='",h($A),"'>","<th>",$r["type"];if(isset($r['algorithm'])&&$r['algorithm']!=$rc)echo" ({$r["algorithm"]})";echo"</th>","<td>",implode(", ",$Gi),"</td>";if($ci){echo"<td>";if($r['partial'])echo"<code class='jush-",DIALECT,"'>WHERE ",h($r['partial']),"</code>";echo"</td>";}echo"</tr>\n";}echo"</table>\n";}function
printSelectionColumns(array$M,array$c){print_fieldset_start("select",lang(55),"columns",(bool)$M,true);$M[""]=[];$p=0;foreach($M
as$t=>$X){$X=isset($_GET["columns"][$t])?$_GET["columns"][$t]:[];$b=select_input("name='columns[$p][col]'",$c,isset($X["col"])?$X["col"]:null,$t!==""?"selectFieldChange":"selectAddRow");echo"<div ",($t!=""?"":"class='no-sort'"),">",icon("handle","handle jsonly");if(Driver::get()->getFunctions()||Driver::get()->getGrouping())echo
html_select("columns[$p][fun]",[-1=>""]+array_filter([lang(56)=>Driver::get()->getFunctions(),lang(57)=>Driver::get()->getGrouping()]),isset($X["fun"])?$X["fun"]:null),help_script_command("value && value.replace(/ |\$/, '(') + ')'",true),script("qsl('select').onchange = (event) => { ".($t!==""?"":" qsl('select, input:not(.remove)', event.target.parentNode).onchange();")." };",""),"($b)";else
echo$b;echo" <button class='button light remove jsonly' title='",h(lang(58)),"'>",icon_solo("remove"),"</button>",script("qsl('#fieldset-select .remove').onclick = selectRemoveRow;",""),"</div>\n";$p++;}print_fieldset_end("select",true);}function
printSelectionSearch(array$Z,array$c,array$s){print_fieldset_start("search",lang(59),"search",(bool)$Z);foreach($s
as$p=>$r){if($r["type"]=="FULLTEXT"){echo"<div>(<i>".implode("</i>, <i>",array_map('AdminNeo\h',$r["columns"]))."</i>) AGAINST","<input type='text' class='input' name='fulltext[$p]' value='".h(isset($_GET["fulltext"][$p])?$_GET["fulltext"][$p]:null)."'>",script("qsl('input').oninput = selectFieldChange;","");if(DIALECT=='sql')echo
checkbox("boolean[$p]",1,isset($_GET["boolean"][$p]),"BOOL");echo"</div>\n";}}$kb="this.parentNode.firstChild.onchange();";foreach(array_merge((array)$_GET["where"],[[]])as$p=>$X){if(!$X||("$X[col]$X[val]"!=""&&in_array($X["op"],$this->getOperators())))echo"<div>",select_input(" name='where[$p][col]'",$c,$X["col"],($X?"selectFieldChange":"selectAddRow"),"(".lang(60).")"),html_select("where[$p][op]",$this->getOperators(),$X["op"],$kb),"<input type='text' class='input' name='where[$p][val]' value='".h($X["val"])."'>",script("mixin(qsl('input'), {oninput: function () { $kb }, onkeydown: selectSearchKeydown});","")," <button class='button light remove jsonly' title='".h(lang(58))."'>",icon_solo("remove"),"</button>",script('qsl("#fieldset-search .remove").onclick = selectRemoveRow;',""),"</div>\n";}print_fieldset_end("search");}function
printSelectionOrder(array$D,array$c,array$s){print_fieldset_start("sort",lang(61),"sort",(bool)$D,true);$_GET["order"][""]="";$p=0;foreach((array)$_GET["order"]as$t=>$X){if($t!=""&&$X=="")continue;echo"<div ",($t!=""?"":"class='no-sort'"),">",icon("handle","handle jsonly"),select_input("name='order[$p]'",$c,$X,$t!==""?"selectFieldChange":"selectAddRow")," ",checkbox("desc[$p]",1,isset($_GET["desc"][$t]),lang(62))," <button class='button light remove jsonly' title='",h(lang(58)),"'>",icon_solo("remove"),"</button>",script('qsl("#fieldset-sort .remove").onclick = selectRemoveRow;',""),"</div>\n";$p++;}print_fieldset_end("sort",true);}function
printSelectionLimit($v){echo"<fieldset><legend>".lang(63)."</legend><div class='fieldset-content'>","<input type='number' name='limit' class='input size' value='$v'>",script("qsl('input').oninput = selectFieldChange;",""),"</div></fieldset>\n";}function
printSelectionLength($il){if($il!==null)echo"<fieldset><legend>".lang(64)."</legend><div class='fieldset-content'>","<input type='number' name='text_length' class='input size' value='".h($il)."'>","</div></fieldset>\n";}function
printSelectionAction(array$s){echo"<fieldset><legend>".lang(65)."</legend><div class='fieldset-content'>","<input type='submit' class='button' value='".lang(55)."'>"," <span id='noindex' title='".lang(66)."'></span>","<script".nonce().">\n";$c=new
stdClass();foreach($s
as$r){$gc=reset($r["columns"]);if($r["type"]!="FULLTEXT"&&$gc)$c->$gc=null;}echo"const indexColumns = ".json_encode($c,JSON_UNESCAPED_UNICODE).";\n","selectFieldChange.call(gid('form')['select']);\n","</script>\n","</div></fieldset>\n";}function
processSelectionColumns(array$c,array$s){$M=[];$ue=[];foreach((array)$_GET["columns"]as$t=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],Driver::get()->getFunctions())||in_array($X["fun"],Driver::get()->getGrouping())))){$M[$t]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],Driver::get()->getGrouping()))$ue[]=$M[$t];}}return[$M,$ue];}function
processSelectionSearch(array$k,array$s){$J=[];foreach($s
as$p=>$r){if($r["type"]=="FULLTEXT"&&isset($_GET["fulltext"])&&$_GET["fulltext"][$p]!="")$J[]="MATCH (".implode(", ",array_map('AdminNeo\idf_escape',$r["columns"])).") AGAINST (".q($_GET["fulltext"][$p]).(isset($_GET["boolean"][$p])?" IN BOOLEAN MODE":"").")";}foreach((array)$_GET["where"]as$Z){$yb=$Z["col"];$_h=$Z["op"];$X=$Z["val"];if("$yb$X"!=""&&in_array($_h,$this->getOperators())){$Pb=[];foreach(($yb!=""?[$yb=>$k[$yb]]:$k)as$A=>$j){$Ci="";$Ob=" $_h";$ph=DIALECT=="pgsql"&&$_h=="="&&$j["type"]=="oid";if($ph)$Ob
.=" ".$this->admin->processFieldInput($j,$X)."::regproc";elseif(preg_match('~IN$~',$_h)){$We=process_length($X);$Ob
.=" ".($We!=""?$We:"(NULL)");}elseif($_h=="SQL")$Ob=" $X";elseif(preg_match('~^(I?LIKE) %%$~',$_h,$y))$Ob=" $y[1] ".$this->admin->processFieldInput($j,"%$X%");elseif($_h=="FIND_IN_SET"){$Ci="$_h(".q($X).", ";$Ob=")";}elseif(!preg_match('~NULL$~',$_h))$Ob
.=" ".$this->admin->processFieldInput($j,$X);if($yb!=""||(isset($j["privileges"]["where"])&&(preg_match('~^[-\d.'.(preg_match('~IN$~',$_h)?',':'').']+$~',$X)||!preg_match('~'.number_type().'|bit~',$j["type"]))&&(!preg_match("~[\x80-\xFF]~",$X)||preg_match('~char|text|enum|set~',$j["type"]))&&(!preg_match('~date|timestamp~',$j["type"])||preg_match('~^\d+-\d+-\d+~',$X))&&(!preg_match('~^elastic~',DRIVER)||$j["type"]!="boolean"||preg_match('~true|false~',$X))&&(!preg_match('~^elastic~',DRIVER)||strpos($_h,"regexp")===false||preg_match('~text|keyword~',$j["type"])))){if($ph)$Pb[]=$Ci.idf_escape($A).$Ob;else$Pb[]=$Ci.Driver::get()->convertSearch(idf_escape($A),$Z,$j).$Ob;}}if(count($Pb)==1)$J[]=$Pb[0];elseif($Pb)$J[]="(".implode(" OR ",$Pb).")";else$J[]="1 = 0";}}return$J;}function
processSelectionOrder(array$k,array$s){$J=[];foreach((array)$_GET["order"]as$t=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$t])?" DESC":"");}return$J;}function
processSelectionLength(){return
isset($_GET["text_length"])?$_GET["text_length"]:"100";}function
getFieldFunctions(array$j){$J=($j["null"]?"NULL/":"");$Rl=isset($_GET["select"])||where($_GET);foreach([Driver::get()->getInsertFunctions(),Driver::get()->getEditFunctions()]as$t=>$me){if(!$t||(!isset($_GET["call"])&&$Rl)){foreach($me
as$oi=>$X){if(!$oi||preg_match("~$oi~",$j["type"]))$J
.="/$X";}}if($t&&$me&&!preg_match('~enum|set|bool~',$j["type"])&&!is_blob($j))$J
.="/SQL";}if($j["auto_increment"]&&!$Rl)$J=lang(47);return
explode("/",$J);}function
getFieldInput($Q,array$j,$Ka,$Y,$o){return"";}function
processFieldInput(array$j,$Y,$o=""){if($o=="SQL")return$Y;if(isset($j["full_type"]))$this->admin->detectJson($j["full_type"],$Y,false);$A=$j["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$o))$J="$o()";elseif(preg_match('~^current_(date|timestamp)$~',$o))$J=$o;elseif(preg_match('~^([+-]|\|\|)$~',$o))$J=idf_escape($A)." $o $J";elseif(preg_match('~^[+-] interval$~',$o))$J=idf_escape($A)." $o ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&DIALECT!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$o))$J="$o(".idf_escape($A).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$o))$J="$o($J)";elseif($j["type"]=="boolean"&&DIALECT=="elastic")$J=$J=="0"?"false":"true";return
unconvert_field($j,$J);}function
getDumpOutputs(){$Vh=['file'=>lang(67),'text'=>lang(68),];if(function_exists('gzencode'))$Vh['gz']='gzip';return$Vh;}function
getDumpFormats(){return(support("dump")?['sql'=>'SQL']:[])+['csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV'];}function
sendDumpHeaders($Re,$Qg=false){$Uh=$_POST["output"];$_d=(str_contains($_POST["format"],"sql")?"sql":($Qg?"tar":"csv"));if($Uh=="gz"){header("Content-Type: application/x-gzip");ob_start(function($xk){return
gzencode($xk);},1e6);}elseif($_d=="tar")header("Content-Type: application/x-tar");elseif($_d=="sql"||$Uh=="text")header("Content-Type: text/plain; charset=utf-8");else
header("Content-Type: text/csv; charset=utf-8");return$_d;}function
dumpTable($Q,$_k,$lm=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($_k)dump_csv(array_keys(fields($Q)));}else{if($lm==2){$k=[];foreach(fields($Q)as$A=>$j)$k[]=idf_escape($A)." $j[full_type]";$ac="CREATE TABLE ".table($Q)." (".implode(", ",$k).")";}else$ac=create_sql($Q,$_POST["auto_increment"],$_k);set_utf8mb4($ac);if($_k&&$ac){if($_k=="DROP+CREATE"||$lm==1)echo"DROP ".($lm==2?"VIEW":"TABLE")." IF EXISTS ".table($Q).";\n";if($lm==1)$ac=remove_definer($ac);echo"$ac;\n\n";}}}function
dumpData($Q,$_k,$H){if($_k){$sg=(DIALECT=="sqlite"?0:1048576);$k=[];$Se=false;if($_POST["format"]=="sql"){if($_k=="TRUNCATE+INSERT")echo
truncate_sql($Q).";\n";$k=fields($Q);if(DIALECT=="mssql"){foreach($k
as$j){if($j["auto_increment"]){echo"SET IDENTITY_INSERT ".table($Q)." ON;\n";$Se=true;break;}}}}$I=Connection::get()->query($H,1);if($I){$hf="";$cb="";$Ef=[];$oe=[];$Ck="";$Zb=0;while($K=($Q!=''?$I->fetchAssoc():$I->fetchRow())){if(!$Ef){$fm=[];foreach($K
as$X){$j=$I->fetchField();if(!empty($k[$j->name]['generated'])){$oe[$j->name]=true;continue;}$Ef[]=$j->name;$t=idf_escape($j->name);$fm[]="$t = VALUES($t)";}$Ck=($_k=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$fm):"").";\n";}if($_POST["format"]!="sql"){if($_k=="table"){dump_csv($Ef);$_k="INSERT";}dump_csv($K);}else{if(!$hf)$hf="INSERT INTO ".table($Q)." (".implode(", ",array_map('AdminNeo\idf_escape',$Ef)).") VALUES";foreach($K
as$t=>$X){if(isset($oe[$t])){unset($K[$t]);continue;}$j=$k[$t];$K[$t]=($X===null?"NULL":($X===false?0:unconvert_field($j,preg_match(number_type(),$j["type"])&&!preg_match('~\[~',$j["full_type"])&&is_numeric($X)?$X:(!is_blob($j)||is_utf8($X)?q($X):Driver::get()->quoteBinary($X)))));}$wj=($sg?"\n":" ")."(".implode(",\t",$K).")";if(!$cb)$cb=$hf.$wj;elseif(DIALECT=="mssql"?$Zb%1000!=0:strlen($cb)+4+strlen($wj)+strlen($Ck)<$sg)$cb
.=",$wj";else{echo$cb.$Ck;$cb=$hf.$wj;}}$Zb++;}if($cb)echo$cb.$Ck;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",Connection::get()->getError())."\n";if($Se)echo"SET IDENTITY_INSERT ".table($Q)." OFF;\n";}}function
getImportFilePath(){return"adminneo.sql";}function
printDatabaseMenu(){echo"<p class='links top-links'>\n";$ih=isset($_GET["ns"])?$_GET["ns"]:null;if($ih==""&&support("database"))echo'<a href="',h(ME),'database=">',icon("edit"),lang(69),"</a>\n";if($ih!=""&&support("scheme"))echo"<a href='",h(ME),"scheme='>",icon("edit"),lang(70),"</a>\n";if($ih!=="")echo'<a href="',h(ME),'schema=">',icon("schema"),lang(71),"</a>\n";if(support("privileges"))echo"<a href='",h(ME),"privileges='>",icon("users"),lang(72),"</a>\n";echo"</p>\n";}function
printNavigation($Kg){parent::printNavigation($Kg);if($Kg=="auth"){$Uh="";foreach((array)$_SESSION["pwds"]as$hm=>$Zj){foreach($Zj
as$N=>$bm){foreach($bm
as$V=>$F){if($F!==null){$pc=$_SESSION["db"][$hm][$N][$V];foreach(($pc?array_keys($pc):[""])as$g){$Wj=$this->admin->getServerName($N,false);$T=h(get_driver_name($hm,$N)).($V!=""||$Wj!=""?" - ":"").h($V).($V!=""&&$Wj!=""?"@":"").h($Wj).($g!=""?h(" - $g"):"");$Uh
.="<li><a href='".h(auth_url($hm,$N,$V,$g))."' class='primary' title='$T'>$T</a></li>\n";}}}}}if($Uh)echo"<nav id='logins'><menu>\n$Uh</menu></nav>\n";}else{$this->admin->printDatabaseSwitcher($Kg);$ua=[];if(DB==""||!$Kg){if(support("sql")){$ua[]="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".icon("command").lang(40)."</a>";$ua[]="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".icon("import").lang(73)."</a>";}$ua[]="<a href='".h(ME)."dump=".urlencode(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".icon("export").lang(74)."</a>";}if(DB=="")$ua[]='<a href="'.h(ME).'database="'.bold($_GET["database"]==="").">".icon("database-add").lang(75)."</a>\n";if(DB!=""&&$_GET["ns"]===""&&!$Kg)$ua[]='<a href="'.h(ME).'scheme="'.bold($_GET["scheme"]==="").">".icon("database-add").lang(76)."</a>\n";if(DB!=""&&$_GET["ns"]!==""&&!$Kg)$ua[]='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".icon("table-add").lang(77)."</a>\n";if($ua)echo"<p class='links'>".implode("\n",$ua)."</p>";$S=[];if($_GET["ns"]!==""&&!$Kg&&DB!=""){Connection::get()->selectDatabase(DB);$S=table_status('',true);}if($_GET["ns"]!==""&&!$Kg&&DB!=""){if($S){$this->admin->printTablesFilter();$this->admin->printTableList($S);}else
echo"<p class='message'>".lang(78)."</p>\n";}if(support("sql")||DIALECT=="elastic"||DIALECT=="mongo"){echo"<script".nonce().">\n";if(support("sql")&&$S){$eg=[];foreach($S
as$Q=>$U)$eg[]=preg_quote($Q,'/');$Nk=support("table")&&!$this->config->isSelectionPreferred()?"table":"select";echo"window.jushLinks = { ".DIALECT.": {\n",js_escape_key(ME.$Nk.'=$&'),': /\b('.implode('|',$eg).')\b/g';if(support('routine')){foreach(routines()as$K)echo",\n",js_escape_key(ME.'function='.urlencode($K["SPECIFIC_NAME"]).'&name=$&'),': /\b'.preg_quote($K["ROUTINE_NAME"],'/').'(?=["`]?\()/g';}echo"\n}};\n";foreach(["bac","bra","sqlite_quo","mssql_bra"]as$X)echo"jushLinks.$X = jushLinks.".DIALECT.";\n";}if(DIALECT!="elastic"&&DIALECT!="mongo"&&$this->getConfig()->isSqlAutocompletionEnabled()&&(isset($_GET["sql"])||isset($_GET["trigger"])||isset($_GET["check"]))){$Xk=array_fill_keys(array_keys($S),[]);foreach(Driver::get()->getAllFields()as$Q=>$k){foreach($k
as$j)$Xk[$Q][]=$j["field"];}echo"window.addEventListener('DOMContentLoaded', () => { autocompletion = jush.autocompleteSql('".idf_escape("")."', ".json_encode($Xk)."); });\n";}echo"</script>\n";}echo
script("let autocompletion;\nwindow.addEventListener('DOMContentLoaded', () => { initSyntaxHighlighting('".js_escape(doc_version())."', '".js_escape(Connection::get()->getFlavor())."', autocompletion); });");}}function
printDatabaseSwitcher($Kg){$f=$this->admin->getDatabases();if(!$f&&DIALECT!="sqlite")return;echo"<div class='db-selector'><form action=''>";hidden_fields_get();echo"<div>";if($f)echo"<select id='database-select' name='db' title='",lang(30),"'>".optionlist([""=>"(".lang(79).")"]+$f,DB)."</select>".script("mixin(gid('database-select'), {onmousedown: dbMouseDown, onchange: dbChange});");else
echo"<input id='database-select' class='input' name='db' value='".h(DB)."' title='",lang(30),"' autocapitalize='off'>\n";echo"<input type='submit' value='".lang(80)."' class='button ".($f?"hidden":"")."'>\n","</div>";foreach(["import","sql","schema","dump","privileges"]as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</form></div>\n";}function
printTableList(array$S){$Ag=($this->settings->isNavigationDual()?"class='dual'":($this->settings->isNavigationReversed()?"class='reversed'":""));echo"<nav id='tables'><menu $Ag>";foreach($S
as$Q=>$uk){$Q="$Q";$A=$this->admin->getTableName($uk);if($A==""||(isset($uk["Partition"])?$uk["Partition"]:false))continue;echo"<li>";$va=in_array($Q,[$_GET["table"],$_GET["select"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"]]);$wb="primary".(is_view($uk)?" view":"");$Gk=support("table")||support("indexes");$Kj=h(ME)."select=".urlencode($Q);$Pk=h(ME)."table=".urlencode($Q);if($this->settings->isSelectionPreferred()){if($this->settings->isNavigationReversed()&&$Gk)echo" <a href='$Pk' title='",lang(34),"' class='secondary'>",icon("structure"),"</a>";echo"<a href='$Kj'",bold($va,$wb)," data-primary='true' title='$A'>$A</a>";if($this->settings->isNavigationDual()&&$Gk)echo" <a href='$Pk' title='",lang(34),"' class='secondary'>",icon_solo("structure"),"</a>";}else{if($this->settings->isNavigationReversed())echo" <a href='$Kj' title='",lang(33),"' class='secondary'>",icon("data"),"</a>";if($Gk)echo"<a href='$Pk'",bold($va,$wb)," data-primary='true' title='$A'>$A</a>";else
echo"<span data-primary='true'",bold($va,$wb),">$A</span>";if($this->settings->isNavigationDual())echo" <a href='$Kj' title='",lang(33),"' class='secondary'>",icon_solo("data"),"</a>";}echo"</li>\n";}echo"</menu></nav>\n",script("initTablesList(".json_encode($this->admin->getDatabase()).");");}function
getSettingsRows($we){$P=parent::getSettingsRows($we);if($we==1){$C=[""=>lang(14),Config::$NavigationSimple=>lang(81),Config::$NavigationDual=>lang(82),Config::$NavigationReversed=>lang(83)];$h=$C[$this->config->getNavigationMode()];$C[""].=" ($h)";$P["navigationMode"]="<tr><th>".lang(84)."</th>"."<td>".html_radios("navigationMode",$C,($qa=$this->settings->getParameter("navigationMode"))!==null?$qa:"")."<span class='input-hint'>".lang(85)."</span>"."</td></tr>\n";$C=[""=>lang(14),0=>lang(34),1=>lang(33),];$h=$C[$this->config->isSelectionPreferred()?1:0];$C[""].=" ($h)";$P["preferSelection"]="<tr><th id='label-links'>".lang(86)."</th>"."<td>".html_select("preferSelection",$C,($qa=$this->settings->getParameter("preferSelection"))!==null?$qa:"","","label-links",true)."<span class='input-hint'>".lang(87)."</span>"."</td></tr>\n";}return$P;}function
getForeignColumnInfo(array$ae,$b){return
null;}}class
TmpFile{private$handler;private$size;function
__construct(){$this->handler=tmpfile();}function
getSize(){return$this->size;}function
write($Vb){if(!$this->handler)return;$this->size+=strlen($Vb);fwrite($this->handler,$Vb);}function
send(){if(!$this->handler)return;fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}function
print_select_result(Result$I,$d=null,array$Mh=[],$v=0){$eg=[];$s=[];$c=[];$Ya=[];$Hl=[];$J=[];for($p=0;(!$v||$p<$v)&&($K=$I->fetchRow());$p++){if(!$p){echo"<div class='scrollable'>\n","<table class='nowrap'>\n","<thead><tr>";for($yf=0;$yf<count($K);$yf++){$j=$I->fetchField();if(!$j){echo"<th></th>";continue;}$A=$j->name;$Lh=isset($j->orgtable)?$j->orgtable:"";$Kh=isset($j->orgname)?$j->orgname:$A;if(isset($j->table))$J[$j->table]=$Lh;if($Mh&&DIALECT=="sql")$eg[$yf]=($A=="table"?"table=":($A=="possible_keys"?"indexes=":null));elseif($Lh!=""){if(!isset($s[$Lh])){$s[$Lh]=[];foreach(indexes($Lh,$d)as$r){if($r["type"]=="PRIMARY"){$s[$Lh]=array_flip($r["columns"]);break;}}$c[$Lh]=$s[$Lh];}if(isset($c[$Lh][$Kh])){unset($c[$Lh][$Kh]);$s[$Lh][$Kh]=$yf;$eg[$yf]=$Lh;}}if($j->charsetnr==63)$Ya[$yf]=true;$Hl[$yf]=$j->type;echo"<th".($Lh!=""||$j->name!=$Kh?" title='".h(($Lh!=""?"$Lh.":"").$Kh)."'":"").">".h($A).($Mh?doc_link(['sql'=>"explain-output.html#explain_".strtolower($A),'mariadb'=>"reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain#columns-in-explain-...-select",]):"");}echo"</thead>\n";}echo"<tr>";foreach($K
as$t=>$X){$w="";if(isset($eg[$t])&&!$c[$eg[$t]]){if($Mh&&DIALECT=="sql"){$Q=$K[array_search("table=",$eg)];$w=ME.$eg[$t].urlencode($Mh[$Q]!=""?$Mh[$Q]:$Q);}else{$w=ME."edit=".urlencode($eg[$t]);foreach($s[$eg[$t]]as$yb=>$yf)$w
.="&where".urlencode("[".bracket_escape($yb)."]")."=".urlencode($K[$yf]);}}$U=($Ya[$t]?'blob':($Hl[$t]==254?'char':''));$j=['full_type'=>$U,'type'=>$U,];$X=select_value($X,$w,$j,null);$wb=$Hl[$t]<=9||$Hl[$t]==246?"class='number'":"";echo"<td $wb>$X</td>";}}if($p)echo"</table>\n</div>";else
echo"<p class='message'>".lang(88);echo"\n";return$J;}function
referencable_primary($Pj){$J=[];foreach(table_status('',true)as$Rk=>$Q){if($Rk!=$Pj&&fk_support($Q)){foreach(fields($Rk)as$j){if($j["primary"]){if($J[$Rk]){unset($J[$Rk]);break;}$J[$Rk]=$j;}}}}return$J;}function
textarea($A,$Y,$L=10,$Db=80){echo"<textarea name='".h($A)."' rows='$L' cols='$Db' class='sqlarea jush-".DIALECT."' spellcheck='false' wrap='off'>";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($Ka,$C,$Y="",$yh="",$ri=""){$bl=($C?"select":"input");return"<$bl $Ka".($C?"><option value=''>$ri".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$ri'>").($yh?script("qsl('$bl').onchange = $yh;",""):"");}function
json_row($t,$X=null){static$Td=true;if($Td)echo"{";if($t!=""){echo($Td?"":",")."\n\t\"".addcslashes($t,"\r\n\t\"\\/").'": '.($X!==null?'"'.addcslashes($X,"\r\n\t\"\\/").'"':'null');$Td=false;}else{echo"\n}\n";$Td=true;}}function
edit_type($t,$j,$Ab,$be=[],$Cd=[]){$U=isset($j["type"])?$j["type"]:null;echo'<td><select name="',h($t),'[type]" class="type" aria-labelledby="label-type">';$Pc=Driver::get()->getTypes();if($U&&!isset($Pc[$U])&&!isset($be[$U])&&!in_array($U,$Cd))$Cd[]=$U;$zk=Driver::get()->getStructuredTypes();if($be)$zk[lang(89)]=$be;echo
optionlist(array_merge($Cd,$zk),$U),'</select><td><input name="',h($t),'[length]" value="',h(isset($j["length"])?$j["length"]:null),'" size="3"',(!(isset($j["length"])?$j["length"]:null)&&preg_match('~var(char|binary)$~',$U)?" class='input required'":" class='input'"),' aria-labelledby="label-length"><td class="options">',($Ab?"<select name='".h($t)."[collation]'".(preg_match('~(char|text|enum|set)$~',$U)?"":" class='hidden'").'><option value="">('.lang(90).')'.optionlist($Ab,isset($j["collation"])?$j["collation"]:null).'</select>':''),(Driver::get()->getUnsigned()?"<select name='".h($t)."[unsigned]'".(!$U||preg_match(number_type(),$U)?"":" class='hidden'").'><option>'.optionlist(Driver::get()->getUnsigned(),isset($j["unsigned"])?$j["unsigned"]:null).'</select>':''),(isset($j['on_update'])?"<select name='".h($t)."[on_update]'".(preg_match('~timestamp|datetime~',$U)?"":" class='hidden'").'>'.optionlist([""=>"(".lang(91).")","CURRENT_TIMESTAMP"],(preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?"CURRENT_TIMESTAMP":$j["on_update"])).'</select>':''),($be?"<select name='".h($t)."[on_delete]'".(preg_match("~`~",$U)?"":" class='hidden'")."><option value=''>(".lang(92).")".optionlist(Driver::get()->getOnActions(),isset($j["on_delete"])?$j["on_delete"]:null)."</select> ":" ");}function
process_length($u){$kd=Driver::$EnumLengthPattern;return(preg_match("~^\\s*\\(?\\s*$kd(?:\\s*,\\s*$kd)*+\\s*\\)?\\s*\$~",$u)&&preg_match_all("~$kd~",$u,$z)?"(".implode(",",$z[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$u)));}function
process_type($j,$zb="COLLATE"){return" $j[type]".process_length($j["length"]).(preg_match(number_type(),$j["type"])&&in_array($j["unsigned"],Driver::get()->getUnsigned())?" $j[unsigned]":"").(preg_match('~char|text|enum|set~',$j["type"])&&$j["collation"]?" $zb ".(DIALECT=="mssql"?$j["collation"]:q($j["collation"])):"");}function
process_field($j,$Fl){if($j["on_update"])$j["on_update"]=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$j["on_update"]);return[idf_escape(trim($j["field"])),process_type($Fl),($j["null"]?" NULL":" NOT NULL"),default_value($j),(preg_match('~timestamp|datetime~',$j["type"])&&$j["on_update"]?" ON UPDATE ".$j["on_update"]:""),(support("comment")&&$j["comment"]!=""?" COMMENT ".q($j["comment"]):""),($j["auto_increment"]?auto_increment():null),];}function
default_value($j){if($j["default"]===null)return"";$h=str_replace("\r","",$j["default"]);$ne=$j["generated"];if(in_array($ne,Driver::get()->getGenerated())){if(DIALECT=="mssql")return" AS ($h)".($ne=="VIRTUAL"?"":" $ne");else
return" GENERATED ALWAYS AS ($h) $ne";}if(stripos($h,"GENERATED ")===0)return" $h";if(preg_match('~char|binary|text|json|enum|set~',$j["type"])||preg_match('~^(?![a-z])~i',$h)){if(DIALECT=="sql"&&preg_match('~text|json~',$j["type"]))return" DEFAULT (".q($h).")";else
return" DEFAULT ".q($h);}else{$h=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$h);return" DEFAULT ".(DIALECT=="sqlite"?"($h)":$h);}}function
type_class($U){foreach(['char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',]as$wb=>$oi){if(preg_match("~$wb|$oi~",$U))return"class='$wb'";}return"";}function
edit_fields(array$k,array$Ab,$U="TABLE",$be=[]){$k=array_values($k);$Lb=$_POST?$_POST["comments"]:Admin::get()->getSettings()->getParameter("commentsOpened");$Jb=$Lb?"":"class='hidden'";echo"<thead><tr>\n";if(support("move_col"))echo"<td class='jsonly'></td>";if($U=="PROCEDURE")echo"<td></td>";echo"<th id='label-name'>",($U=="TABLE"?lang(93):lang(94)),"</th>\n","<td id='label-type'>",lang(44),"<textarea id='enum-edit' rows='4' cols='12' wrap='off' style='display: none;'></textarea>",script("gid('enum-edit').onblur = onFieldLengthBlur;"),"</td>\n","<td id='label-length'>",lang(95),"</td>\n","<td>",lang(96),"</td>\n";if($U=="TABLE")echo"<td id='label-null'>NULL</td>\n","<td><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='",lang(47),"'>AI</abbr>",doc_link(['sql'=>"example-auto-increment.html",'mariadb'=>"reference/data-types/auto_increment",]),"</td>\n","<td id='label-default'>",lang(48),"</td>\n",support("comment")?"<td id='label-comment' $Jb>".lang(46)."</td>\n":"";echo"<td>","<button name='add[",(support("move_col")?0:count($k)),"]' value='1' title='",h(lang(97)),"' class='button light'>",icon_solo("add"),"</button>",script("row_count = ".count($k).";"),"</td>\n","</tr></thead>\n";$wb=support("move_col")?"class='sortable'":"";echo"<tbody $wb>\n";foreach($k
as$p=>$j){$p++;$Nh=$j[($_POST?"orig":"field")];$Fc=(isset($_POST["add"][$p-1])||(isset($j["field"])&&!(isset($_POST["drop_col"][$p])?$_POST["drop_col"][$p]:null)))&&(support("drop_col")||$Nh=="");$_k=$Fc?"":"style='display: none;'";echo"<tr $_k>\n";if(support("move_col"))echo"<td class='handle jsonly'>",icon_solo("handle"),"</td>";if($U=="PROCEDURE")echo"<td>",html_select("fields[$p][inout]",Driver::get()->getInOut(),$j["inout"]),"</td>\n";echo"<th>";if($Fc)echo"<input class='input' name='fields[$p][field]' value='",h($j["field"]),"' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name' ".(isset($_POST["add"][$p-1])?"autofocus":"").">";echo
input_hidden("fields[$p][orig]",$Nh);edit_type("fields[$p]",$j,$Ab,$be);echo"</th>\n";if($U=="TABLE"){echo"<td>",checkbox("fields[$p][null]",1,$j["null"],"","","block","label-null"),"</td>\n";$rb=$j["auto_increment"]?"checked":"";echo"<td><label class='block'><input type='radio' name='auto_increment_col' value='$p' $rb aria-labelledby='label-ai'></label></td>\n","<td class='default-value'>";if(Driver::get()->getGenerated())echo
html_select("fields[$p][generated]",array_merge(["","DEFAULT"],Driver::get()->getGenerated()),$j["generated"]);else
echo
checkbox("fields[$p][generated]",1,$j["generated"],"","","","label-default");$Ka="name='fields[$p][default]' aria-labelledby='label-default'";$Y=h($j["default"]);if(str_contains($Y,"\n")){if($Y[0]=="\n")$Y="\n$Y";echo"<textarea $Ka rows='3' cols='30' style='vertical-align: bottom;'>$Y</textarea>";}else
echo"<input class='input' $Ka value='$Y'>";echo"</td>\n";if(support("comment")){$rg=Connection::get()->isMinVersion("5.5")?1024:255;echo"<td $Jb>","<input class='input' name='fields[$p][comment]' value='",h($j["comment"]),"' data-maxlength='$rg' aria-labelledby='label-comment'>","</td>\n";}}echo"<td>";if(support("move_col"))echo"<button name='add[$p]' value='1' title='".h(lang(97))."' class='button light'>",icon_solo("add"),"</button>","<button name='up[$p]' value='1' title='".h(lang(98))."' class='button light hidden'>",icon_solo("arrow-up"),"</button>","<button name='down[$p]' value='1' title='".h(lang(99))."' class='button light hidden'>",icon_solo("arrow-down"),"</button>";if($Nh==""||support("drop_col"))echo"<button name='drop_col[$p]' value='1' title='".h(lang(58))."' class='button light'>",icon_solo("remove"),"</button>";echo"</td>\n</tr>\n";}echo"</tbody>";}function
process_fields(&$k){$nh=0;if($_POST["up"]){$Pf=0;foreach($k
as$t=>$j){if(key($_POST["up"])==$t){unset($k[$t]);array_splice($k,$Pf,0,[$j]);break;}if(isset($j["field"]))$Pf=$nh;$nh++;}}elseif($_POST["down"]){$ge=false;foreach($k
as$t=>$j){if(isset($j["field"])&&$ge){unset($k[key($_POST["down"])]);array_splice($k,$nh,0,[$ge]);break;}if(key($_POST["down"])==$t)$ge=$j;$nh++;}}elseif($_POST["add"]){$k=array_values($k);array_splice($k,key($_POST["add"]),0,[[]]);}elseif(!$_POST["drop_col"])return
false;return
true;}function
normalize_enum($y){$X=$y[0];return"'".str_replace("'","''",addcslashes(stripcslashes(str_replace($X[0].$X[0],$X[0],substr($X,1,-1))),'\\'))."'";}function
grant($re,array$Ji,$c,$wh,$Zl){if(!$Ji)return
true;if($Ji==["ALL PRIVILEGES","GRANT OPTION"]){if($re)return(bool)queries("GRANT ALL PRIVILEGES ON $wh TO $Zl WITH GRANT OPTION");else
return
queries("REVOKE ALL PRIVILEGES ON $wh FROM $Zl")&&queries("REVOKE GRANT OPTION ON $wh FROM $Zl");}if($Ji==["GRANT OPTION","PROXY"]){if($re)return(bool)queries("GRANT PROXY ON $wh TO $Zl WITH GRANT OPTION");else
return(bool)queries("REVOKE PROXY ON $wh FROM $Zl");}return(bool)queries(($re?"GRANT ":"REVOKE ").preg_replace('~(GRANT OPTION)\([^)]*\)~','$1',implode("$c, ",$Ji).$c)." ON $wh ".($re?"TO ":"FROM ").$Zl);}function
drop_create($Rc,$ac,$Sc,$gl,$Tc,$x,$Dg,$Bg,$Cg,$uh,$dh){if($_POST["drop"])query_redirect($Rc,$x,$Dg);elseif($uh=="")query_redirect($ac,$x,$Cg);elseif($uh!=$dh){$dc=queries($ac);queries_redirect($x,$Bg,$dc&&queries($Rc));if($dc)queries($Sc);}else
queries_redirect($x,$Bg,queries($gl)&&queries($Tc)&&queries($Rc)&&queries($ac));}function
create_trigger($wh,array$Al){$pl=" $Al[Timing] $Al[Event]".(preg_match('~ OF~',$Al["Event"])?" $Al[Of]":"");return"CREATE TRIGGER ".idf_escape($Al["Trigger"]).(DIALECT=="mssql"?$wh.$pl:$pl.$wh).rtrim(" $Al[Type]\n$Al[Statement]",";").";";}function
create_routine($sj,$K){$O=[];$k=(array)$K["fields"];ksort($k);$Xe=implode("|",Driver::get()->getInOut());foreach($k
as$j){if($j["field"]!="")$O[]=(preg_match("~^($Xe)\$~",$j["inout"])?"$j[inout] ":"").idf_escape($j["field"]).process_type($j,"CHARACTER SET");}$vc=rtrim($K["definition"],";");return"CREATE $sj ".idf_escape(trim($K["name"]))." (".implode(", ",$O).")".($sj=="FUNCTION"?" RETURNS".process_type($K["returns"],"CHARACTER SET"):"").($K["language"]?" LANGUAGE $K[language]":"").(DIALECT=="pgsql"?" AS ".q($vc):"\n$vc;");}function
remove_definer($H){return
preg_replace('~^([A-Z =]+) DEFINER=`'.preg_replace('~@(.*)~','`@`(%|\1)',logged_user()).'`~','\1',$H);}function
format_foreign_key($n){$xh=implode("|",Driver::get()->getOnActions());$g=$n["db"];$ih=$n["ns"];return" FOREIGN KEY (".implode(", ",array_map('AdminNeo\idf_escape',$n["source"])).") REFERENCES ".($g!=""&&$g!=$_GET["db"]?idf_escape($g).".":"").($ih!=""&&$ih!=$_GET["ns"]?idf_escape($ih).".":"").idf_escape($n["table"])." (".implode(", ",array_map('AdminNeo\idf_escape',$n["target"])).")".(preg_match("~^($xh)\$~",$n["on_delete"])?" ON DELETE $n[on_delete]":"").(preg_match("~^($xh)\$~",$n["on_update"])?" ON UPDATE $n[on_update]":"").(isset($n["deferrable"])?" $n[deferrable]":"");}function
tar_file($m,TmpFile$sl){$Fe=pack("a100a8a8a8a12a12",$m,644,0,0,decoct($sl->getSize()),decoct(time()));$tb=8*32;for($p=0;$p<strlen($Fe);$p++)$tb+=ord($Fe[$p]);$Fe
.=sprintf("%06o",$tb)."\0 ";echo$Fe,str_repeat("\0",512-strlen($Fe));$sl->send();echo
str_repeat("\0",511-($sl->getSize()+511)%512);}function
doc_link(array$ni,$hl="<sup>?</sup>"){if(!(isset($ni[DIALECT])?$ni[DIALECT]:null))return"";$im=doc_version();$Ul=['sql'=>"https://dev.mysql.com/doc/refman/$im/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(Connection::get()->isCockroachDB()?"current":$im)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://www.oracle.com/pls/topic/lookup?ctx=db".str_replace(".","",$im)."&id=",'elastic'=>"https://www.elastic.co/guide/en/elasticsearch/reference/$im/",];if(Connection::get()->isMariaDB()){$Ul['sql']="https://mariadb.com/docs/server/";$ni['sql']=isset($ni['mariadb'])?$ni['mariadb']:str_replace(".html","",$ni['sql']);}return"<a href='".h($Ul[DIALECT].$ni[DIALECT].(DIALECT=='mssql'?"?view=sql-server-ver$im":""))."'".target_blank().">$hl</a>";}function
doc_version(){return
preg_replace('~^(\d\.?\d).*~s','\1',Connection::get()->getVersion());}function
db_size($g){if(!Connection::get()->selectDatabase($g))return"?";$J=0;foreach(table_status()as$R)$J+=$R["Data_length"]+$R["Index_length"];return
format_number($J);}function
set_utf8mb4($ac){static$O=false;if(!$O&&preg_match('~\butf8mb4~i',$ac)){$O=true;echo"SET NAMES ".charset(Connection::get()).";\n\n";}}error_reporting(E_ALL&~E_DEPRECATED);set_error_handler(function($md,$i){return(bool)preg_match('~^Undefined (array key|offset|index)~',$i);},E_WARNING|E_NOTICE);;$Qd=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($Qd||ini_get("filter.default_flags")){foreach(['_GET','_POST','_COOKIE','_SERVER']as$X){$Ol=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($Ol)$$X=$Ol;}}if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");class
Server{private$params;private$key;function
__construct(array$Zh,$t=null){$this->params=$Zh;$this->key=$t;}function
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
getConfigParams(){$Zh=isset($this->params["config"])?$this->params["config"]:[];$qe=["servers"];foreach($qe
as$Yh){if(isset($Zh[$Yh]))unset($Zh[$Yh]);}return$Zh;}}class
Config{static$NavigationSimple="simple";static$NavigationDual="dual";static$NavigationReversed="reversed";private$params;private$servers=[];function
__construct(array$Zh){$this->params=$Zh;if(isset($this->params["servers"])){foreach($this->params["servers"]as$t=>$N){$Uj=new
Server($N,is_string($t)?$t:null);$this->params["servers"][$t]=$Uj;$this->servers[$Uj->getKey()]=$Uj;}}}function
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
getDefaultDriver(array$Qc){$Oc=isset($this->params["defaultDriver"])?$this->params["defaultDriver"]:null;return$Oc&&isset($Qc[$Oc])?$Oc:key($Qc);}function
getDefaultServer(){$N=isset($this->params["defaultServer"])?$this->params["defaultServer"]:null;if($N===null)return
null;$Uj=isset($this->params["servers"][$N])?$this->params["servers"][$N]:null;if($Uj)return$Uj->getKey();return$N;}function
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
getServerPairs(array$Qc){$hk=null;foreach($this->servers
as$N){if(!isset($Qc[$N->getDriver()]))continue;if(!$hk)$hk=$N->getDriver();elseif($N->getDriver()!=$hk){$hk=null;break;}}$Vj=[];foreach($this->servers
as$t=>$N){if(!isset($Qc[$N->getDriver()]))continue;$Tj=$N->getName();if($hk&&$Tj)$Vj[$t]=$Tj;else$Vj[$t]=$Qc[$N->getDriver()].($Tj!=""?" - $Tj":"");}return$Vj;}function
getServer($Sj){return
isset($this->servers[$Sj])?$this->servers[$Sj]:null;}function
applyServer($N){$N=$this->getServer($N);if(!$N)return;$this->params=array_merge($this->params,$N->getConfigParams());}private
function
parseList($gg){if(is_array($gg))return$gg;return
preg_split('~\s*,\s*~',(string)$gg);}}class
Settings{private
static$CookieName="neo_settings";static$ColorSchemeLight="light";static$ColorSchemeDark="dark";static$NavigationWidthMin=10;static$NavigationWidthMax=30;private$config;private$params=[];function
__construct(Config$Qb){$this->config=$Qb;if(isset($_COOKIE[self::$CookieName])){parse_str($_COOKIE[self::$CookieName],$this->params);$this->save();}if(isset($_COOKIE["neo_lang"])){$this->updateParameter("lang",$_COOKIE["neo_lang"]);unset($_COOKIE["neo_lang"]);cookie("neo_lang","",-3600);}}static
function
readParameter($t){parse_str(isset($_COOKIE[self::$CookieName])?$_COOKIE[self::$CookieName]:"",$Zh);return
isset($Zh[$t])?$Zh[$t]:null;}function
getParameter($t,$h=null){return
isset($this->params[$t])?$this->params[$t]:$h;}function
updateParameter($t,$Y){$this->updateParameters([$t=>$Y]);}function
updateParameters(array$Zh){$this->params=array_filter(array_merge($this->params,$Zh),function($Y){return$Y!==null;});$this->save();}private
function
save(){cookie(self::$CookieName,http_build_query($this->params),7776000);}function
getColorScheme(){return$this->getParameter("colorScheme");}function
getNavigationMode(){return($qa=$this->getParameter("navigationMode"))!==null?$qa:$this->config->getNavigationMode();}function
isNavigationSimple(){return$this->getNavigationMode()==Config::$NavigationSimple;}function
isNavigationDual(){return$this->getNavigationMode()==Config::$NavigationDual;}function
isNavigationReversed(){return$this->getNavigationMode()==Config::$NavigationReversed;}function
getNavigationWidth(){$ym=$this->getParameter("navigationWidth");if($ym===null)return
null;return
min(max((float)$ym,self::$NavigationWidthMin),self::$NavigationWidthMax);}function
isSelectionPreferred(){return($qa=$this->getParameter("preferSelection"))!==null?$qa:$this->config->isSelectionPreferred();}function
isRelationLinks(){return
isset($this->params["relationLinks"])?$this->params["relationLinks"]:$this->config->isRelationLinks();}function
getRecordsPerPage(){return($qa=$this->getParameter("recordsPerPage"))!==null?$qa:$this->config->getRecordsPerPage();}function
getEnumAsSelectThreshold(){$Y=$this->getParameter("enumAsSelectThreshold");if($Y<0)return
null;return$Y!==null?(int)$Y:$this->config->getEnumAsSelectThreshold();}}class
Hash{static
function
hkdf($u,$t,$cf="",$xj=""){if(extension_loaded("hash")&&PHP_VERSION_ID>=70120)return
hash_hkdf("sha1",$t,$u,$cf,$xj);if($xj=="")$xj=str_repeat("\0",20);$Ki=self::hmacSha1($t,$xj);$rh="";for($Df="",$Za=1;!isset($rh[$u-1]);$Za++){$Df=self::hmacSha1($Df.$cf.chr($Za),$Ki);$rh
.=$Df;}return
substr($rh,0,$u);}static
function
hmacSha1($e,$t){if(!extension_loaded("hash"))return
hash_hmac("sha1",$e,$t,true);if(strlen($t)>64)$t=sha1($t,true);$t=str_pad($t,64,"\0");$pf=($t^str_repeat("\x36",64));$Ah=($t^str_repeat("\x5C",64));return
sha1($Ah.sha1($pf.$e,true),true);}}class
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
\Sodium\randombytes_buf($u);$Nl=DIRECTORY_SEPARATOR==="/";if($Nl){$I=self::readDevUrandom($u);if($I!==false)return$I;}$db=$Nl&&PHP_VERSION_ID>50609&&PHP_VERSION_ID<50613;if(extension_loaded("mcrypt")&&!$db){$I=mcrypt_create_iv($u,MCRYPT_DEV_URANDOM);if($I!==false)return$I;}$eb=PHP_VERSION_ID<50444||(PHP_VERSION_ID>50500&&PHP_VERSION_ID<50528)||(PHP_VERSION_ID>50600&&PHP_VERSION_ID<50612);if(extension_loaded("openssl")&&!$eb){$I=openssl_random_pseudo_bytes($u,$yk);if($yk)return$I;}return
false;}private
static
function
readDevUrandom($u){static$l=null;if($l===null)$l=@fopen("/dev/urandom","rb");if(!$l)return
false;$hj=$u;$I="";do{$e=fread($l,$hj);if($e===false)return
false;$hj-=strlen($e);$I
.=$e;}while($hj>0);return$I;}private
static
function
readCapicom($u){$Fb=new
\COM("CAPICOM.Utilities.1");$hj=$u;$I="";do{$e=base64_decode((string)$Fb->GetRandom($u,0));$hj-=strlen($e);$I
.=$e;}while($hj>0);return$I;}private
static
function
lastResortRandom($u){static$t=null;static$xj=null;if($t===null){$e=$_SERVER;$e[]=uniqid("",true);shuffle($e);$t=sha1(serialize($e),true);if(extension_loaded("openssl"))$xj=openssl_random_pseudo_bytes(20);else{$xj="";for($p=0;$p<20;$p++)$xj
.=chr((mt_rand()^mt_rand())%256);}}else{if((ord($t)%2===0)===(ord($xj)%2===0))$t=Hash::hmacSha1($t,$xj);else$xj=Hash::hmacSha1($xj,$t);}return
Hash::hkdf($u,$t,"$u",$xj);}}if(!function_exists("str_starts_with")){function
str_starts_with($Ee,$Zg){return
strpos($Ee,$Zg)===0;}}if(!function_exists("str_contains")){function
str_contains($Ee,$Zg){return
strpos($Ee,$Zg)!==false;}}if(!function_exists("password_verify")){function
password_verify($F,$De){return
false;}}if(!function_exists("ini_set")){function
ini_set($Eh,$Y){return
false;}}function
version(){return
VERSION;}function
idf_unescape($Te){if(!preg_match('~^[`\'"[]~',$Te))return$Te;$Pf=substr($Te,-1);return
str_replace($Pf.$Pf,$Pf,substr($Te,1,-1));}function
q($xk){return
Connection::get()->quote($xk);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
number_type(){return'((?<!o)int(?!er)|numeric|real|float|double|decimal|money)';}function
remove_slashes(array$fm,$Qd=false){$J=[];foreach($fm
as$t=>$X)$J[stripslashes($t)]=(is_array($X)?remove_slashes($X,$Qd):($Qd?$X:stripslashes($X)));return$J;}function
bracket_escape($Te,$Ra=false){static$yl=[':'=>':1',']'=>':2','['=>':3','"'=>':4'];return
strtr($Te,($Ra?array_flip($yl):$yl));}function
min_version($im,$og=null,$d=null){if(!$d)$d=Connection::get();if($og&&$d->isMariaDB())$im=$og;return$im&&$d->isMinVersion($im);}function
charset(Connection$d){return($d->isMinVersion("5.5.3")?"utf8mb4":"utf8");}function
link_files($A,array$Pd){switch($A){case'favicon-red.ico':$m='favicon-red-c2ebb34a8df5aba28e15d87728a151df__aff407a3.ico';break;case'favicon-red.svg':$m='favicon-red-a006e401273230fd6be80568c8361b57__aff407a3.svg';break;case'apple-touch-icon-red.png':$m='apple-touch-icon-red-507228751d2170d047e72142d2c02390__aff407a3.png';break;case'logo.svg':$m='logo-de272eb4bdca9c6fffd38c073270fb1a__9d7e398f.svg';break;case'jush.css':$m='jush-b3a93b18444da26820ff61746521dede__72e4fe51.css';break;case'jush-dark.css':$m='jush-dark-f8dac59c6ad1018686e52a0e0357e421__2ec7793c.css';break;case'jush.js':$m='jush-615bc0b9720a1de8edd2c6876a3495b6__aab91337.js';break;case'icons.svg':$m='icons-70163a2695280bf75edba563e7b5471b__2ec7793c.svg';break;case'default-red.css':$m='default-red-9c7de6d1d78ea798bfef943c92b6b611__cfb00ea1.css';break;case'default-red-dark.css':$m='default-red-dark-aa471f32fb495651c17bba291cd8b147__7a7f64b1.css';break;case'main.js':$m='main-eaf2ce2c3d91edbef355936903e47e59__45ca58f9.js';break;default:$m=null;break;}if(!$m)return
null;return
BASE_URL."?file=".urldecode($m);}function
ini_bool($Eh){$X=ini_get($Eh);return
preg_match('~^(on|true|yes)$~i',$X)||(int)$X;}function
ini_bytes($ef){$X=ini_get($ef);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
sid(){static$J;if($J===null)$J=(session_id()&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
save_driver_name($Oc,$N,$A){restart_session();$_SESSION["drivers"][$Oc][$N]=$A;stop_session();}function
get_driver_name($Oc,$N=null){return
isset($_SESSION["drivers"][$Oc][$N])?$_SESSION["drivers"][$Oc][$N]:Drivers::get($Oc);}function
save_login($Oc,$N,$V,$F,$g=""){$t=isset($_COOKIE["neo_key"])?$_COOKIE["neo_key"]:null;$_SESSION["pwds"][$Oc][$N][$V]=$t?[encrypt_string($F,$t)]:$F;$_SESSION["db"][$Oc][$N][$V][$g]=true;}function
delete_login($Oc,$N,$V){unset($_SESSION["pwds"][$Oc][$N][$V]);unset($_SESSION["db"][$Oc][$N][$V]);}function
get_password(){$F=get_session("pwds");if(is_array($F))return$_COOKIE["neo_key"]?decrypt_string($F[0],$_COOKIE["neo_key"]):false;return$F;}function
get_vals($H,$b=0){$J=[];$I=Connection::get()->query($H);if(is_object($I)){while($K=$I->fetchRow())$J[]=$K[$b];}return$J;}function
get_key_vals($H,$d=null,$ck=true){if(!$d)$d=Connection::get();$J=[];$I=$d->query($H);if(is_object($I)){while($K=$I->fetchRow()){if($ck)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$d=null,$i="<p class='error'>"){if(!$d)$d=Connection::get();$J=[];$I=$d->query($H);if(is_object($I)){while($K=$I->fetchAssoc())$J[]=$K;}elseif(!$I&&!is_object($d)&&$i&&(defined("AdminNeo\PAGE_HEADER")||$i=="-- "))echo$i.error()."\n";return$J;}function
unique_array(array$K,array$s){foreach($s
as$r){if(!preg_match("~PRIMARY|UNIQUE~",$r["type"])&&!$r["partial"])continue;$Kl=[];foreach($r["columns"]as$t){if(!isset($K[$t]))continue
2;$Kl[$t]=$K[$t];}return$Kl;}return
null;}function
escape_key($t){if(preg_match('(^([\w(]+)('.str_replace("_",".*",preg_quote(idf_escape("_"))).')([ \w)]+)$)',$t,$y))return$y[1].idf_escape(idf_unescape($y[2])).$y[3];return
idf_escape($t);}function
where($Z,$k=[]){$Pb=[];foreach((array)$Z["where"]as$t=>$X){$t=bracket_escape($t,true);$b=escape_key($t);$Ld=isset($k[$t]["type"])?$k[$t]["type"]:null;$ke=isset($k[$t]["full_type"])?$k[$t]["full_type"]:null;if(DIALECT=="sql"&&$Ld=="json")$Pb[]="$b = CAST(".q($X)." AS JSON)";elseif(DIALECT=="pgsql"&&preg_match('~^jsonb?$~',$ke))$Pb[]="$b::jsonb = ".q($X)."::jsonb";elseif(DIALECT=="sql"&&is_numeric($X)&&strpos($X,".")!==false)$Pb[]="$b LIKE ".q($X);elseif(DIALECT=="mssql"&&strpos($Ld,"datetime")===false)$Pb[]="$b LIKE ".q(preg_replace('~[_%[]~','[\0]',$X));else$Pb[]="$b = ".(isset($k[$t])?unconvert_field($k[$t],q($X)):q($X));if(DIALECT=="sql"&&preg_match('~char|text~',$Ld)&&preg_match("~[^ -@]~",$X))$Pb[]="$b = ".q($X)." COLLATE ".charset(Connection::get())."_bin";}foreach((array)$Z["null"]as$t)$Pb[]=escape_key($t)." IS NULL";return
implode(" AND ",$Pb);}function
where_check($X,$k=[]){parse_str($X,$ob);remove_slashes([&$ob]);return
where($ob,$k);}function
where_link($p,$b,$Y,$Bh="="){return"&where%5B$p%5D%5Bcol%5D=".urlencode($b)."&where%5B$p%5D%5Bop%5D=".urlencode(($Y!==null?$Bh:"IS NULL"))."&where%5B$p%5D%5Bval%5D=".urlencode($Y);}function
convert_fields(array$c,array$k,array$M=[]){$I="";foreach($c
as$t=>$X){if($M&&!in_array(idf_escape($t),$M))continue;$Ja=convert_field($k[$t]);if($Ja)$I
.=", $Ja AS ".idf_escape($t);}return$I;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),[";"=>"%3B",","=>"%2C"]);}function
cookie($A,$Y,$Zf=2592000){header("Set-Cookie: $A=".rawurlencode($Y).($Zf?"; expires=".gmdate("D, d M Y H:i:s",time()+$Zf)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"")."; HttpOnly; SameSite=lax",false);}function
get_url($Tl,$Wb){$J=@file_get_contents($Tl,false,$Wb);if(function_exists('http_get_last_response_headers'))$http_response_header=($qa=http_get_last_response_headers())!==null?$qa:[];return[$J,isset($http_response_header)?$http_response_header:[]];}function
get_settings($Yb="neo_settings"){parse_str(isset($_COOKIE[$Yb])?$_COOKIE[$Yb]:"",$P);return$P;}function
get_setting($t,$Yb="neo_settings"){$P=get_settings($Yb);return
isset($P[$t])?$P[$t]:null;}function
save_settings(array$P,$Yb="neo_settings"){cookie($Yb,http_build_query($P+get_settings($Yb)));}function
restart_session(){if(!ini_bool("session.use_cookies")&&session_status()==PHP_SESSION_NONE)session_start();}function
stop_session($Yd=false){$Xl=ini_bool("session.use_cookies");if(!$Xl||$Yd){session_write_close();if($Xl&&ini_set("session.use_cookies","0")===false)session_start();}}function&get_session($t){return$_SESSION[$t][DRIVER][SERVER][$_GET["username"]];}function
set_session($t,$X){$_SESSION[$t][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($hm,$N,$V,$g=null){$Sl=remove_from_uri(implode("|",array_keys(Drivers::getList()))."|username|ext|".($g!==null?"db|":"").($hm=='mssql'||$hm=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$Sl,$y);return"$y[1]?".(sid()?session_name()."=".urlencode(session_id())."&":"").urlencode($hm)."=".urlencode($N)."&".($_GET["ext"]?"ext=".urlencode($_GET["ext"])."&":"")."username=".urlencode($V).($g!=""?"&db=".urlencode($g):"").($y[2]?"&$y[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($x,$_=null){if($_!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($x!==null?$x:$_SERVER["REQUEST_URI"]))][]=$_;}if($x!==null){if($x=="")$x=".";header("Location: $x");exit;}}function
query_redirect($H,$x,$_,$Yi=true,$td=true,$Dd=false,$nl=""){if($td){$sk=microtime(true);$Dd=!Connection::get()->query($H);$nl=format_time($sk);}$ok=$H?Admin::get()->formatMessageQuery($H,$nl,$Dd):"";if($Dd){Admin::get()->addError(error().$ok.script("initToggles();"));return
false;}if($Yi)redirect($x,$_.$ok);return
true;}function
queries_redirect($x,$_,$Yi){$Pi=implode("\n",Queries::$queries);$nl=format_time(Queries::$start);return
query_redirect($Pi,$x,$_,$Yi,false,!$Yi,$nl);}class
Queries{static$queries=[];static$start=0.0;}function
queries($H){if(!Queries::$start)Queries::$start=microtime(true);if(support("sql")){Queries::$queries[]=(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";";return
Connection::get()->query($H);}else{Queries::$queries[]=$H;return[];}}function
apply_queries($H,array$S,$od='AdminNeo\table'){foreach($S
as$Q){if(!queries("$H ".$od($Q)))return
false;}return
true;}function
format_time($sk){return
lang(100,max(0,microtime(true)-$sk));}function
relative_uri(){return
str_replace(":","%3a",preg_replace('~^[^?]*/([^?]*)~','\1',$_SERVER["REQUEST_URI"]));}function
remove_from_uri($Yh=""){return
substr(preg_replace("~(?<=[?&])($Yh".(sid()?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_file($t,$qc=false,$xc=""){$l=$_FILES[$t];if(!$l)return
null;foreach($l
as$t=>$X)$l[$t]=(array)$X;$J='';foreach($l["error"]as$t=>$i){if($i)return$i;$A=$l["name"][$t];$tl=$l["tmp_name"][$t];$Ub=file_get_contents($qc&&preg_match('~\.gz$~',$A)?"compress.zlib://$tl":$tl);if($qc){$sk=substr($Ub,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$sk))$Ub=iconv("utf-16","utf-8",$Ub);elseif($sk=="\xEF\xBB\xBF")$Ub=substr($Ub,3);}if($xc){if(!preg_match("~$xc\\s*\$~",$Ub))$Ub
.=";";$Ub
.="\n\n";}$J
.=$Ub;}return$J;}function
upload_error($i){$vg=($i==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($i?lang(101).($vg?" ".lang(102,$vg):""):lang(103));}function
repeat_pattern($oi,$u){return
str_repeat("$oi{0,65535}",$u/65535)."$oi{0,".($u%65535)."}";}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
format_number($X){return
strtr(number_format($X,0,".",lang(104)),preg_split('~~u',lang(105),-1,PREG_SPLIT_NO_EMPTY));}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($Q,$Fd=false){$J=table_status($Q,$Fd);return($J?reset($J):["Name"=>$Q]);}function
column_foreign_keys($Q){$J=[];foreach(Admin::get()->getForeignKeys($Q)as$n){foreach($n["source"]as$X)$J[$X][]=$n;}return$J;}function
fields_from_edit(){$J=[];foreach((array)$_POST["field_keys"]as$t=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$t];$_POST["fields"][$X]=$_POST["field_vals"][$t];}}foreach((array)$_POST["fields"]as$t=>$X){$A=bracket_escape($t,true);$J[$A]=["field"=>$A,"full_type"=>"varchar","type"=>"varchar","privileges"=>["insert"=>1,"update"=>1,"where"=>1,"order"=>1],"null"=>true,"auto_increment"=>($t==Driver::get()->primary),];}return$J;}function
dump_headers($Re,$Rg=false){$Re=friendly_url($Re).date("-Ymd-His");$_d=Admin::get()->sendDumpHeaders($Re,$Rg);$Uh=$_POST["output"];if($Uh!="text")header("Content-Disposition: attachment; filename=$Re.$_d".($Uh!="file"&&preg_match('~^[0-9a-z]+$~',$Uh)?".$Uh":""));session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$_d;}function
dump_table_order(array$Xg,array$ej){$If=array_flip($Xg);$Ih=[];$qm=[];$ic=false;$pm=function($A)use(&$pm,&$Ih,&$qm,&$ic,$If,$ej){if(isset($Ih[$A]))return;if(isset($qm[$A])){$ic=true;return;}$qm[$A]=true;foreach(isset($ej[$A])?$ej[$A]:[]as$cj){if(isset($If[$cj]))$pm($cj);}unset($qm[$A]);$Ih[$A]=true;};foreach($Xg
as$A)$pm($A);return($ic?null:array_keys($Ih));}function
dump_csv($K){$El=$_POST["format"]=="tsv";foreach($K
as$t=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($El?'\t':'[,;]|^$').'~',$X))$K[$t]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($El?"\t":";")),$K)."\r\n";}function
apply_sql_function($o,$b){return($o?($o=="unixepoch"?"DATETIME($b, '$o')":($o=="count distinct"?"COUNT(DISTINCT ":strtoupper("$o("))."$b)"):$b);}function
get_temp_dir(){$mi=ini_get("upload_tmp_dir");if(!$mi)$mi=sys_get_temp_dir();return$mi;}function
open_file_with_lock($m){if(is_link($m))return
null;$l=@fopen($m,"c+");if(!$l)return
null;@chmod($m,0660);if(!flock($l,LOCK_EX)){fclose($l);return
null;}return$l;}function
write_and_unlock_file($l,$e){rewind($l);fwrite($l,$e);ftruncate($l,strlen($e));unlock_file($l);}function
unlock_file($l){flock($l,LOCK_UN);fclose($l);}function
first(array$Ia){return
reset($Ia);}function
get_private_key($ac){$m=get_temp_dir()."/adminneo.key";if(!$ac&&!file_exists($m))return
false;$l=open_file_with_lock($m);if(!$l)return
false;$t=stream_get_contents($l);if(!$t){$t=Random::strongKey();write_and_unlock_file($l,$t);}else
unlock_file($l);return$t;}function
get_random_string(){return
Random::strongKey();}function
select_value($X,$w,$j,$jl){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$Ef=[];foreach($X
as$W)$Ef+=array_fill_keys(array_keys($W),null);foreach(array_keys($Ef)as$_f)$J
.="<th>".h($_f);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($Ef,$W)as$cm)$J
.="<td>".select_value($cm,$w,$j,$jl);}}else{foreach($X
as$_f=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($_f):"")."<td>".select_value($W,$w,$j,$jl);}return"<table>$J</table>";}$Bj="";if($j&&$X!==null&&($jl===null||strlen($X)<=$jl)&&($fm=Driver::get()->explodeArrayValue($X,$j["full_type"],$Bj))){$Aj=$j;$Aj["type"]=$Aj["full_type"]=$Bj;$J=select_array_value($fm,$X,$w,$Aj,$jl);return
Driver::get()->implodeArrayValues($J,$j["full_type"]);}if(!$w)$w=Admin::get()->getFieldValueLink($X,$j);if($j)$X=Connection::get()->formatValue($X,$j);$J=$j?Admin::get()->formatFieldValue($X,$j):$X;if($J!==null){if(!is_utf8($J))$J="\0";elseif($jl!=""&&is_shortable($j))$J=truncate_utf8($J,max(0,+$jl));else$J=h($J);}return
Admin::get()->formatSelectionValue($J,$w,$j,$X);}function
select_array_value(array$fm,$X,$w,array$j,$jl){$I=[];foreach($fm
as$Y){if(is_array($Y))$I[]=select_array_value($Y,$X,$w,$j,$jl);else{$Jf=preg_replace('~(where%5B\d+%5D%5Bval%5D=)'.preg_quote(urlencode($X),"~")."~",'${1}'.urlencode($Y),$w);$I[]=select_value($Y,$Jf,$j,$jl);}}return$I;}function
is_blob(array$j){$Hl=Driver::get()->getStructuredTypes();$U=lang(106);return
preg_match('~blob|bytea|raw|file~',$j["type"])&&!in_array($j["type"],isset($Hl[$U])?$Hl[$U]:[]);}function
is_mail($Y){return
is_string($Y)&&filter_var($Y,FILTER_VALIDATE_EMAIL);}function
is_web_url($Y){if(!is_string($Y)||!preg_match('~^(https?:)?//~i',$Y))return
false;$Mb=parse_url($Y);if(!$Mb)return
false;$Tl=$Y;if(isset($Mb['path'])){$gd=array_map('urlencode',explode('/',$Mb['path']));$Tl=str_replace($Mb['path'],implode('/',$gd),$Tl);}if(isset($Mb['query'])){parse_str($Mb['query'],$Zh);$Tl=str_replace($Mb['query'],http_build_query($Zh),$Tl);}if(!isset($Mb['scheme']))$Tl="https:$Tl";return(bool)filter_var($Tl,FILTER_VALIDATE_URL);}function
is_shortable($j){return$j&&!preg_match('~'.number_type().'|date|time|year~',$j["type"]);}function
host_port($N){return(preg_match('~^(:([^:].*)|(\[(.+)]|(([^:]+://)?[^:]+))(:(\d+))?)$~',$N,$y)?[(isset($y[4])?$y[4]:"").(isset($y[5])?$y[5]:""),$y[2].(isset($y[8])?$y[8]:"")]:[$N,'']);}function
count_rows($Q,$Z,$rf,$ue){$H=" FROM ".table($Q).($Z?" WHERE ".implode(" AND ",$Z):"");return($rf&&(DIALECT=="sql"||count($ue)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$ue).")$H":"SELECT COUNT(*)".($rf?" FROM (SELECT 1$H GROUP BY ".implode(", ",$ue).") x":$H));}function
slow_query($H){$g=Admin::get()->getDatabase();$ol=Admin::get()->getQueryTimeout();$jk=Driver::get()->slowQuery($H,$ol);$d=null;if(!$jk&&support("kill")){$d=connect();if($d&&($g==""||$d->selectDatabase($g))){$Gf=$d->getValue(connection_id());echo'<script',nonce(),'>
	const timeout = setTimeout(() => {
		ajax(\'',js_escape(ME),'script=kill\', function() {
		}, \'kill=',$Gf,'&token=',get_token(),'\');
	}, ',1000*$ol,');
</script>
';}}ob_flush();flush();$J=@get_key_vals(($jk?:$H),$d,false);if($d){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$Ui=rand(1,1e6);return($Ui^$_SESSION["token"]).":$Ui";}function
verify_token(){return true;}function
script($lk,$xl="\n"){return"<script".nonce().">$lk</script>$xl";}function
script_src($Tl,$uc=false){return"<script src='".h($Tl)."'".nonce().($uc?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
input_hidden($A,$Y=""){return"<input type='hidden' name='".h($A)."' value='".h($Y)."'>";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($xk){if($xk===null||$xk==="")return"";return
str_replace(["&","<","\"","'","\0"],["&amp;","&lt;","&quot;","&#039;","&#0;"],$xk);}function
truncate_utf8($xk,$u=80){if($xk=="")return"";if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$u).")($)?)u",$xk,$y))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$u).")($)?)",$xk,$y);return
h($y[1]).(isset($y[2])?"":"<i>…</i>");}function
icon_solo($q){return
icon($q,"solo");}function
icon_chevron_down(){return
icon("chevron-down","chevron");}function
icon_chevron_right(){return
icon("chevron-down","chevron-right");}function
icon($q,$wb=null){$q=h($q);return"<svg class='icon ic-$q $wb'><use href='".link_files("icons.svg",[])."#$q'/></svg>";}function
checkbox($A,$Y,$rb,$Kf="",$zh="",$wb="",$Mf=""){$J="<input type='checkbox' name='$A' value='".h($Y)."'".($rb?" checked":"").($Mf?" aria-labelledby='$Mf'":"").">".($zh?script("qsl('input').onclick = function () { $zh };",""):"");return($Kf!=""||$wb?"<label".($wb?" class='$wb'":"").">$J".h($Kf)."</label>":$J);}function
optionlist($C,$Mj=null,$Yl=false){$J="";foreach($C
as$_f=>$W){$Gh=[$_f=>$W];if(is_array($W)){$J
.='<optgroup label="'.h($_f).'">';$Gh=$W;}foreach($Gh
as$t=>$X)$J
.='<option'.($Yl||is_string($t)?' value="'.h($t).'"':'').($Mj!==null&&($Yl||is_string($t)?(string)$t:$X)===$Mj?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
html_select($A,$C,$Y="",$yh="",$Mf="",$Yl=false){static$Kf=0;$Lf="";if(!$Mf&&substr(isset($C[""])?$C[""]:"",0,1)=="("){$Kf++;$Mf="label-$Kf";$Lf="<option value='' id='$Mf'>".h($C[""]);unset($C[""]);}return"<select name='".h($A)."'".($Mf?" aria-labelledby='$Mf'":"").">".$Lf.optionlist($C,$Y,$Yl)."</select>".($yh?script("qsl('select').onchange = function () { $yh };",""):"");}function
html_radios($A,$C,$Y=""){$I="<span class='labels'>";foreach($C
as$t=>$X)$I
.="<label><input type='radio' name='".h($A)."' value='".h($t)."'".($t==$Y?" checked":"").">".h($X)."</label>";$I
.="</span>";return$I;}function
confirm($_="",$Oj="qsl('input')"){return
script("$Oj.onclick = () => confirm('".($_?js_escape($_):lang(107))."');","");}function
print_fieldset_start($q,$Vf,$Qe,$nm=false,$kk=false){echo"<fieldset id='fieldset-$q' class='closable ".(!$nm?" closed":"")."'>","<legend><a href='#'>$Vf</a></legend>",icon($Qe,"fieldset-icon jsonly"),"<div class='fieldset-content".($kk?" sortable":"")."'>";}function
print_fieldset_end($q,$kk=false){echo"</div>",script("initFieldset('$q');","");if($kk)echo
script("initSortable('#fieldset-$q .fieldset-content');","");echo"</fieldset>\n";}function
bold($ab,$wb=""){return($ab?" class='$wb active'":($wb?" class='$wb'":""));}function
js_escape($xk){return
addcslashes($xk,"\r\n'\\/");}function
js_escape_key($xk){return'"'.addcslashes($xk,"\r\n\t\"\\/").'"';}function
pagination($E,$fc){return"<li>".($E==$fc?"<strong>".($E+1)."</strong>":'<a href="'.h(remove_from_uri("page").($E?"&page=$E".($_GET["next"]?"&next=".urlencode($_GET["next"]):""):"")).'">'.($E+1)."</a>")."</li>";}function
print_hidden_fields(array$Li,array$Ue=[],$Ci=""){$I=false;foreach($Li
as$t=>$X){if(!in_array($t,$Ue)){if(is_array($X))print_hidden_fields($X,[],$t);else{$I=true;echo
input_hidden($Ci?$Ci."[$t]":$t,$X);}}}return$I;}function
hidden_fields_get(){if(sid())echo
input_hidden(session_name(),session_id());if(SERVER!==null)echo
input_hidden(DRIVER,SERVER);echo
input_hidden("username",$_GET["username"]);}function
enum_input($Ka,array$j,$Y,$ed=null,$qb=false){preg_match_all("~'((?:[^']|'')*)'~",$j["length"],$z);$fm=$z[1];$ml=Admin::get()->getSettings()->getEnumAsSelectThreshold();$M=!$qb&&$ml!==null&&count($fm)>$ml;$U=$qb?"checkbox":"radio";$wa=$M?"selected":"checked";$I=$M?"<select $Ka>":"<span class='labels'>";if($M&&$j["null"]&&$ed!==""){$rb=$Y===null?$wa:"";$I
.="<option value='__adminneo_empty__' disabled $rb></option>";}if($ed!==null){$rb=(is_array($Y)?in_array($ed,$Y):$Y===$ed)?$wa:"";if($M)$I
.="<option value='$ed' $rb>".lang(108)."</option>";else$I
.="<label><input type='$U' $Ka value='$ed' $rb><i>".lang(108)."</i></label>";}foreach($fm
as$X){if($ed===""&&$X==="")continue;$X=stripcslashes(str_replace("''","'",$X));$rb=is_array($Y)?in_array($X,$Y):$Y===$X;$rb=$rb?$wa:"";$fe=$X===""?("<i>".lang(108)."</i>"):h(Admin::get()->formatFieldValue($X,$j));if($M)$I
.="<option value='".h($X)."' $rb>$fe</option>";else$I
.=" <label><input type='$U' $Ka value='".h($X)."' $rb>$fe</label>";}$I
.=$M?"</select>":"</span>";return$I;}function
input($j,$Y,$o,$Oa=false){$A=h(bracket_escape($j["field"]));$Hl=Driver::get()->getTypes();$sf=isset($j["full_type"])&&Admin::get()->detectJson($j["full_type"],$Y,true);$jj=(DIALECT=="mssql"&&$j["auto_increment"]&&!$_POST["clone"]);if($jj&&!$_POST["save"])$o=null;if(in_array($j["type"],Driver::get()->getUserTypes())){$ld=type_values($Hl[$j["type"]]);if($ld){$j["type"]="enum";$j["length"]=$ld;}}$Ka=" name='fields[$A]' ".($Oa?" autofocus":"");$me=(isset($_GET["select"])||$jj?["orig"=>lang(109)]:[])+Admin::get()->getFieldFunctions($j);$Ce=(in_array($o,$me)||isset($me[$o]));echo"<td class='function'>",Driver::get()->getUnconvertFunction($j)." ";if(count($me)>1){$Mj=$o===null||$Ce?$o:"";echo"<select name='function[$A]'>".optionlist($me,$Mj)."</select>",help_script_command("value.replace(/^SQL\$/, '')",true),script("qsl('select').onchange = functionChange;","");}else
echo
h(reset($me));echo"</td><td>";$ff=Admin::get()->getFieldInput(isset($_GET["edit"])?$_GET["edit"]:null,$j,$Ka,$Y,$o);if($ff!="")echo$ff;elseif(preg_match('~bool~',$j["type"]))echo"<input type='hidden'$Ka value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked='checked'":"")."$Ka value='1'>";elseif($j["type"]=="enum")echo
enum_input($Ka,$j,$Y);elseif($j["type"]=="set"){preg_match_all("~'((?:[^']|'')*)'~",$j["length"],$z);echo"<span class='labels'>";foreach($z[1]as$X){$X=stripcslashes(str_replace("''","'",$X));$rb=$Y!==null&&in_array($X,explode(",",$Y),true);$rb=$rb?"checked":"";$fe=$X===""?("<i>".lang(108)."</i>"):h(Admin::get()->formatFieldValue($X,$j));echo" <label><input type='checkbox' name='fields[$A][]' value='".h($X)."' $rb>$fe</label>";}echo"</span>";}elseif(is_blob($j)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$A'>";elseif($sf)echo"<textarea $Ka cols='50' rows='12' class='jush-json'>".h($Y).'</textarea>';elseif(($hl=preg_match('~text|lob|memo|json~i',$j["type"]))||preg_match("~\n~",$Y)){if($hl&&DIALECT!="sqlite")$Ka
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$Ka
.=" cols='30' rows='$L'";}echo"<textarea $Ka>".h($Y).'</textarea>';}else{$yg=!preg_match('~int~',$j["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$j["length"],$y)?((preg_match("~binary~",$j["type"])?2:1)*$y[1]+($y[3]?1:0)+($y[2]&&!$j["unsigned"]?1:0)):($Hl&&$Hl[$j["type"]]?$Hl[$j["type"]]+($j["unsigned"]?0:1):0);if(DIALECT=='sql'&&Connection::get()->isMinVersion("5.6")&&preg_match('~time~',$j["type"]))$yg+=7;echo"<input class='input'".((!$Ce||$o==="")&&preg_match('~(?<!o)int(?!er)~',$j["type"])&&!preg_match('~\[\]~',$j["full_type"])?" type='number'":"").($o!="now"?" value='".h($Y)."'":" data-last-value='".h($Y)."'").($yg?" data-maxlength='$yg'":"").(preg_match('~char|binary~',$j["type"])&&$yg>20?" size='44'":"")."$Ka>";}$He=Admin::get()->getFieldInputHint($_GET["edit"],$j,$Y);if($He!="")echo" <span class='input-hint'>$He</span>";$Ud=0;foreach($me
as$t=>$X){if($t===""||!$X)break;$Ud++;}if(count($me)>1)echo
script("qsl('td').oninput = partial(skipOriginal, $Ud);");}function
process_input($j){$Te=bracket_escape($j["field"]);$o=isset($_POST["function"][$Te])?$_POST["function"][$Te]:"";if($o=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?idf_escape($j["field"]):false);if($o=="NULL")return
Driver::get()->getNull();if(is_blob($j)&&ini_bool("file_uploads")){$l=get_file("fields-$Te");if(!is_string($l))return
false;return
Driver::get()->quoteBinary($l);}$Y=isset($_POST["fields"][$Te])?$_POST["fields"][$Te]:(isset($_FILES["fields"]["name"][$Te])?$_FILES["fields"]["name"][$Te]:null);if($Y===null)return
false;if($j["auto_increment"]&&$Y=="")return
null;if($j["type"]=="set")$Y=implode(",",(array)$Y);if($o=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
Admin::get()->processFieldInput($j,$Y,$o);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$oj=$nd=[];foreach(table_status("",true)as$Q=>$R){$Rk=Admin::get()->getTableName($R);if(!isset($R["Engine"])||$Rk==""||($_POST["tables"]&&!in_array($Q,$_POST["tables"])))continue;$I=Connection::get()->query("SELECT".limit("1 FROM ".table($Q)," WHERE ".implode(" AND ",Admin::get()->processSelectionSearch(fields($Q),[])),1));if($I&&!$I->fetchRow())continue;$w=h(ME."select=".urlencode($Q)."&where[0][op]=".urlencode($_GET["where"][0]["op"])."&where[0][val]=".urlencode($_GET["where"][0]["val"]));if($I)$oj[]="<li><a href='$w'>".icon("search")."$Rk</a></li>";else$nd[]="<div class='error'><a href='$w'>$Rk</a>: ".error()."</div>";}if($oj)echo"<ul class='links'>\n",implode("\n",$oj),"</ul>\n";if($nd)echo
implode("\n",$nd),"\n";if(!$oj&&!$nd)echo"<p class='message'>".lang(78)."</p>\n";}function
help_script($hl,$gk=false){return
script("initHelpFor(qsl('select, input'), '".h($hl)."', $gk);","");}function
help_script_command($Gb,$gk=false){return
script("initHelpFor(qsl('select, input'), (value) => { return $Gb; }, $gk);","");}function
edit_form($Q,$k,$K,$Rl){$Rk=Admin::get()->getTableName(table_status1($Q,true));$T=$Rl?lang(38):lang(110);page_header("$T: $Rk",["select"=>[$Q,$Rk],$T]);if($K===false){echo"<p class='error'>".lang(88)."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$ad=false;if(!$k)echo"<p class='error'>".lang(111)."\n";else{echo"<table class='box'>".script("qsl('table').onkeydown = onEditingKeydown;");$Oa=!$_POST;foreach($k
as$A=>$j){echo"<tr><th>".Admin::get()->getFieldName($j);$t=bracket_escape($A);$h=isset($_GET["set"][$t])?$_GET["set"][$t]:null;if($h===null){$h=$j["default"];if($j["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$h,$gj))$h=$gj[1];if(DIALECT=="sql"&&preg_match('~binary~',$j["type"]))$h=bin2hex($h);}$Y=($K!==null?($K[$A]!=""&&DIALECT=="sql"&&preg_match("~enum|set~",$j["type"])&&is_array($K[$A])?implode(",",$K[$A]):(is_bool($K[$A])?+$K[$A]:$K[$A])):(!$Rl&&$j["auto_increment"]?"":(isset($_GET["select"])?false:$h)));if(!$_POST["save"]&&is_string($Y))$Y=Admin::get()->formatFieldValue($Y,$j);if(($Rl&&!isset($j["privileges"]["update"]))||$j["generated"]){echo"<td class='function'></td><td>";if($Rl||!$j["generated"])echo
select_value($Y,'',$j,null);else
echo"<code class='jush-".DIALECT."'>",h($Y),"</code>";echo"</td>";}else{$ad=true;$o=($_POST["save"]?isset($_POST["function"][$A])?$_POST["function"][$A]:"":($Rl&&preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$Rl&&$Y==$j["default"]&&preg_match('~^[\w.]+\(~',$Y))$o="SQL";if(preg_match("~time~",$j["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$o="now";}if($j["type"]=="uuid"&&$Y=="uuid()"){$Y="";$o="uuid";}if($Oa!==false)$Oa=($j["auto_increment"]||$o=="now"||$o=="uuid"?null:true);input($j,$Y,$o,(bool)$Oa);if($Oa)$Oa=false;}echo"\n";}if(!support("table")&&!fields($Q))echo"<tr>"."<th><input class='input' name='field_keys[]'>".script("qsl('input').oninput = fieldChange;","")."<td class='function'>".html_select("field_funs[]",Admin::get()->getFieldFunctions(["null"=>isset($_GET["select"])]))."<td><input class='input' name='field_vals[]'>"."\n";echo"</table>\n",script("initToggles(gid('form'));");}echo"<p>";if($ad){echo"<input type='submit' class='button default' value='".lang(112)."'>\n";if(!isset($_GET["select"]))echo"<input type='submit' class='button' name='insert' value='".($Rl?lang(113):lang(114))."' title='Ctrl+Shift+Enter'>\n",($Rl?script("qsl('input').onclick = function () { return !ajaxForm(this.form, '".lang(115)."…', this); };"):"");}echo($Rl?"<input type='submit' class='button' name='delete' value='".lang(116)."'>".confirm()."\n":"");if(isset($_GET["select"]))print_hidden_fields(["check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]]);echo
input_hidden("referer",isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"]),input_hidden("save","1"),input_token(),"</form>\n";}function
file_upload_form_script($ce,$gf){$qg=ini_get("max_file_uploads");$vg=ini_get("upload_max_filesize");$wg=ini_bytes("upload_max_filesize");return
script("initFilesUploadForm('".js_escape($ce)."', '".js_escape($gf)."', "."$qg, '".lang(117,$qg,"\'max_file_uploads\'")."', "."$wg, '".lang(118,$vg,"\'upload_max_filesize\'")."')");}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($xk){$Ea=array_flip(str_split(compress_alphabet()));$u=strlen($xk);$em=($u?13*($u-1)/2-$Ea[$xk[0]]:0);$Wa="";$mj=0;$nj=0;for($p=1;$p<$u;$p+=2){$mj=($mj<<13)+$Ea[$xk[$p]]*93+$Ea[$xk[$p+1]];$nj+=13;while($nj>=8&&$em>=8){$nj-=8;$em-=8;$Wa
.=chr($mj>>$nj);$mj&=(1<<$nj)-1;}}if($Wa=="")return"";return
function_exists('gzinflate')?gzinflate($Wa):inflate($Wa);}function
inflate($Wa){$Wf=[3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258];$Xf=[0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0];$Hc=[1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577];$Jc=[0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13];$J="";$G=0;do{$Sd=inflate_bits($Wa,$G,1);$U=inflate_bits($Wa,$G,2);if(!$U){$G=($G+7)&~7;$u=inflate_bits($Wa,$G,16);$G+=16;$J
.=substr($Wa,$G>>3,$u);$G+=$u<<3;}else{if($U==1){$ig=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$Kc=array_fill(0,30,5);}else{$hg=inflate_bits($Wa,$G,5)+257;$Ic=inflate_bits($Wa,$G,5)+1;$D=[16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15];$Gg=array_fill(0,19,0);$Fg=inflate_bits($Wa,$G,4)+4;for($p=0;$p<$Fg;$p++)$Gg[$D[$p]]=inflate_bits($Wa,$G,3);$Hg=inflate_table($Gg);$Yf=[];while(count($Yf)<$hg+$Ic){$Hk=inflate_symbol($Wa,$G,$Hg);if($Hk==16)$Yf=array_merge($Yf,array_fill(0,inflate_bits($Wa,$G,2)+3,end($Yf)));elseif($Hk==17)$Yf=array_merge($Yf,array_fill(0,inflate_bits($Wa,$G,3)+3,0));elseif($Hk==18)$Yf=array_merge($Yf,array_fill(0,inflate_bits($Wa,$G,7)+11,0));else$Yf[]=$Hk;}$ig=array_slice($Yf,0,$hg);$Kc=array_slice($Yf,$hg);}$jg=inflate_table($ig);$Mc=inflate_table($Kc);while(($Hk=inflate_symbol($Wa,$G,$jg))!=256){if($Hk<256)$J
.=chr($Hk);else{$u=$Wf[$Hk-257]+inflate_bits($Wa,$G,$Xf[$Hk-257]);$Lc=inflate_symbol($Wa,$G,$Mc);$nh=strlen($J)-$Hc[$Lc]-inflate_bits($Wa,$G,$Jc[$Lc]);for($p=0;$p<$u;$p++)$J
.=$J[$nh+$p];}}}}while(!$Sd);return$J;}function
inflate_bits($Wa,&$G,$Zb){$J=0;for($p=0;$p<$Zb;$p++){$J+=((ord($Wa[$G>>3])>>($G&7))&1)<<$p;$G++;}return$J;}function
inflate_table(array$Yf){$Q=[];$xb=0;for($Xa=1;$Xa<=max($Yf);$Xa++){foreach($Yf
as$Hk=>$u){if($u==$Xa){$Q[$Xa][$xb]=$Hk;$xb++;}}$xb<<=1;}return$Q;}function
inflate_symbol($Wa,&$G,array$Q){$xb=0;$Xa=0;do{$xb=($xb<<1)+inflate_bits($Wa,$G,1);$Xa++;}while(!isset($Q[$Xa][$xb]));return$Q[$Xa][$xb];}if(isset($_GET["file"]))load_compiled_file($_GET["file"]);function
load_compiled_file($m){if($m==""){http_response_code(404);exit;}if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){http_response_code(304);exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression","1");$_d=pathinfo($m,PATHINFO_EXTENSION);switch($_d){case"css":header("Content-Type: text/css; charset=utf-8");break;case"js":header("Content-Type: text/javascript; charset=utf-8");break;case"ico":header("Content-Type: image/x-icon");break;case"png":header("Content-Type: image/png");break;case"svg":header("Content-Type: image/svg+xml");break;}switch($m){case'favicon-red-c2ebb34a8df5aba28e15d87728a151df__aff407a3.ico':$e='AAABAAEAICAAAAEAIAC7AQAAFgAAAIlQTkcNChoKAAAADUlIRFIAAAAgAAAAIAgGAAAAc3p69AAAAYJJREFUeNrV1wHkGnEUwPFztCYBRAQY20AAAgQBQpghwEUFhM2G6A4QAJksoMi2AQTZBhgcAUgthAS2yTFo5f2/OHCcv3v3I+EDcO/reI+f9eOZdVN3E2CjAhcL+NjjX2gPHwu4qMA2EfAUHv5AEvoND1ltwEv8gqS0xQtNwFeIIV80AVeIIVdNgBilCNhCDNloAtoQQ9raNWzhBFE6wUl7iEpwsUoweAUXpTSH6H1MTAMdDPAhNEAHjZih77RbMMQTWEpZDCG6AOCAHooJBhfRw0G/hvHrNEEfXbwKddHHBBtTd2Bz6zvwFmLIG01ADjNISjPk0tyBFo6QhI5w0tyB17BCNqoYY40AEhFgjTGqsCPfShzwH3VYMfJ4HsrDilHHRbuGZ4xQgJVQASOctWt4ifzeKZooPDK0iSkCCKD7A98hMQLs8DO0iwyM+qYJcCCGOJqADD5DUvqEjPYO2JhAlD7CNvEyqmGZYPASNRh/G5bhYQ4ff0M+5vBQvrfH6W09AE8YAEN5XivhAAAAAElFTkSuQmCC';break;case'favicon-red-a006e401273230fd6be80568c8361b57__aff407a3.svg':$e='+<bAU6+V?$so%eoa6[DcEe<SKeo.[BnWu^_0
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
d%=.2Qw3Bb';break;case'default-red-9c7de6d1d78ea798bfef943c92b6b611__cfb00ea1.css':$e='&erWG6KZS1.Oe@H#WA[QPB,hP./GgmWDCog8Ul^<=Uxqoj!2vm%U?P@X(i!rqm#y~GXsKy9(&7`$LbEK)Ys)yC1RCVp3;B<`nk+b?X>]FJ]4sICHxD~6CmHVChb
Us+W>=zehc)xx&"9Yu9wv9r>1H.4D5gF?Jj-/K+
dY&Zu^55(0Rk},Gl_nX)Echl.W8@nGiyE/s`90i=]vPG8`;E;R30aa7<b@aIv3LTDEEJnPV!Xp)qJdvq|mKA)^=i31Y<^kzJqpBDM5iar@mXU=_S+?GH1hlN6kHbf8JTe?V!)yZXS7xqwx==<=Lr(U3yo+Ix:ZVP9Tr.9B,W4[D.PeiNDFiT@=_LoZ=;`@rR(p"SE$Ea$b1]!%aMxw_8{w(5gFU`#=!Itw6T{N/7L3N8%&m6|O}.PrGhy1P01gH:+`iM&(fFGM/@xso?,CwRq/xsML~H!]~UV;XG}hByeM^w;jxMbiAtQtu_rsw:IyRIivrSRsPpbMxpdn-q+L921ELz&H27|l5qjXaklS8m6ycz(T/s043@3tQu2Ktw6K6yFf]fkz#h/MAo%=FKr78u)16!L6evk!N_vMjToz!eGL-u{M|yUY"w_7(v^`sw;5Jyff%wqv|y-2Mnye/MvxjMwuzK{MBa=eIyfcTuz(o!OBaS8qJc$q]n7X%v|My5RJ4rl,W?Kd#^l=mJprF-vsPsA?8$hn-<Z.I:%mIP]5qo#.B)KiJ[D&}?SoTxDG&E9!wZ:x3MTj3wkkvpEkuY?D6
Nhq.3YR*Er{s@tZ"(o|NVN0N~/k$+iSK*C2OX9P#XVL3y5
d.j.C{=8uT5EGu-=RjMhe/J9R{E,X$f$kG1d>1b.qd2=$fHyP)/,C<tPFoTtlVOo_h_G?$dzV$1m20bW(hy}Cu8n9vFB]r8f;IiCYD
d@h8Xhf!=Cw_)V6S}a?Ir(s/3I!)J-#`
*kCQ(ofDPF.
+$DI+p53#V
0y%6k"V,F+9U{6_DUXw!mG^!+l7@_Wi5@96E/ZZ/(*ua!dud,K<,JHf?L.#4xSN/TS+V3U)/m.FDjEcCR8+!uut7j?f7N_dr:ISU"`3/Go)@NH*J(sd%MiYpLW^M@!SRV*gjx%s#i@xC9DZH4ftmQ?wr:M6`5UUX<wD0MPmX|8J51)x,YM(:K#&XUUh7dmKb6v>]
jecvq?pyv3H-){*FL)mQ`pE"/Z;wsSy+hHk?<lJ,ldl7`$T%%B_;_:r>v0hKAxr4`N&Nt]A$q;Sx/$ZL8DFWRD<DBig]LO_bX~;[$%&Z<=0Bu2$:IiA23X^hZ?=ROa==u~Lq)0N*P-U<Rtm3r$,~u1Dg
>&$*[E*l^c7tb*FwE$;%&_WlI7`"%$U(!N:AJ&zq"j1PuQ
8R@g#7e-oo5M3XPs6Kc5#rgz?4X7<ip
qM4HWt]64A<DS|XqT$tkQl`%_K3JUsa!YS=y7=u$=A#i:L)0SW%K;.)/oCyC)Fqst0_@p"s
PW4>T$e-O|9
GA(~<(FF*vKRVmf>b.HihV/"8em`l$T63/5S
M%",**Dm;%9PAlQc%e9Oz0iM4="R0m_VeejpNknsi8|XUZX
cHMvTJD[f1O,f4BMPb/uzmeh5[>67bB<j7[/1Xb8Q[3ynk#!%[2$3]e4{4{`#m43$6lO$,.exhziwHlNR5&cIl:qfc)qinX%k9c:R5y>OGg=bhdi8rB2(Y^q~<QY8p>@4Rba
C>Zjr/$G#^`X:8dYOta4,6`S+qg)Wu?gX~;!,UQP@gI%/K`
.rN5u`<B+&Myrwp{mC:!EH"G*1iy3&<L
N*jh$KW8!9:K)%#qO`:#lp
R.:8jkA~nSn_i.mCnHVBO-^b%O-.fxI7nvDc3%8,]ky8BzDYo]8TRL(4Ii(6fG#bPXjnd596KWtXAbhZo7<D"[p>,7j#hUFcB+
vjd:l`kihRoU#H]Z7#-:"($%V%.x=pt;Boai?/WpewBt=[yEQ[c5Pd6]thqb|N@>JgcU2cQ[:*c$V4(5J:`.Z6/v!Osda+=fBbuePXR#om<(:`]E1-OCX27AC!-CJh~qdIO]fj^1o+ec%eFiG-n/qqzlY-;`_%UOp*(0)2i=qqYNp%]SerbE/R3o[c_sh(u-^bVI#?R.Fu&L|O33#&K/?_{%U#hRSea"9N"Q<5`S#$hby8gN+;
o8V$755A`9TZwBWtXvU$O(2Co!e[c.",ddOSPqG#oH>Rx(Px3m%it/=xq^4VBq[U!~C,_.l_:
UH,P[ik(5>n*JB%lPh&edM"NY|W
`6;2&::)g!E"dSeuUUOb-[%_Nhj_l$f>"B&)%SL(JCfw0`o`u<DiU:qHrbtVwABb[jNhH3)nu.j-7=Mn).YJB[k]UH?I55%$i9q[/`*($XHZ[KcW0xccLCo;vc%`Vrk"cK^LTqkC/,i]Np11TuXXT{OiAt5e!0
h<o2+<9b9#C9^jNh(Mrgk=`u]3qVb8ud}?;O&T4sZ1yXAr,0g$j&vr*Q,-sB:=my8AQ.rdUOF;O<erHh0d<.^&0EW
~A;m<MnQk6n`h
D=A,9hN3W"q*S6rf0ZK_=5]t%O7&iUr+e4?cqk~$p:d*rj768%s^`uG)>1ALcd}OpJu/B-!#%2SuEhEr`*5E"$1x[x?GFFcNacX7;ZUr+Qnx5_nI_y`vV>f7:FgOWIGdLY3iJ8NY5L`(MlM%ffwrdS[<$E!4%*{glU0F-$nv#2}jC<u00u^o+<b-8ec0<Z~@sa%B[o{k+1/!&BhufvaX:ORC[&w;sY}nCWc-DK]&Xo*3!Pyg:Ju<9<)8A;*5xaN`@hyO~*+B-!:%W%v!(9qT}"
>0F7DAK~1}-ZQZmnywCEV}q3H4W{WbW9B?T&W/%uh*r(Eh6$lIOHg#ZGk&`{atwL`cH?[=Luy/
oBDy~;|u:a;5<@9_wU/"&;WO.O[H1OdKF]Nep]Clw+%3OGH@2A`6Z3{.rbqQhhyU:U}N%moyHR#pr1(r4q6?*?/*FZ4abc
GKQW8yFse08,.o,)(2n.KO@tXEF9)%)`y*woWN!SI+Zck/=OG3_*p"*7+3pUQo0pU3Qi:`6@g>&nHjaVH&Y51okj#0uU5-d;#c-AuC[Np>,3.d=qmcLDR;j:]U@Tv_Ytmi.%.=Xjjh0+C/$QRRk==nq#2XXiH`Adw1fh2p3m?mtu8W$L8
K~$m@^ES/k?XL5g@=m=KkXCe#P6uL!XJQL8U_ueK@DK3+"m
$<Fr:>q7ATj#>CCao;*9>/)5>-$tjO<!*NmVAC.]u9]fV"=o1dL:2=:Hlm3P1sn])5ax0d7iuzHHHPVq:>YJu4(z#K>PK!wQ^*^I[RTF$u,~,aN1:(BCyv20EP5_7/`s+
R**N!Td]*FJsUk(V$Lf8(+u9>LSDBumM3d_hXV3iKU1IU9)$G_b.kkE,d;SeX?o-i?M0Rj3i6vMd2`O]T>O*P$B{*NH<$-VL]{2`TuSw2Zu|yXOsqd"?n
$,+mpN7["]p?$TmK+)E*u3<YC(VpR(RB%Q0TvaIgo`k*,J#wug?V:Tu=T.=2;.7r:e;O#!mB?:>s1RNC;"dNIw/$waNU"s+yfY%WN<U?VgqgA`5MN!NET,nAJVw#TmtO
U@TW}c6Pd5zeDN>4V@4SDh{c/!U&=OD4.fu22Vm(@8C3{4`o"pB^YDeq(*}n"lEtM5Ur8+c(0G">lfNH_*OkBGJ7,*00}m?H5)Uq^
aT~XF+I1@[;^ji6p0_`<1pa,A/w]WEjl"C6
/%euu(6>UW`mvCQiStAj?$bQwD(&|*j%%N~i
doU<T;IAZw%rH[hRc
8+ie;uE#-@c9NqS>ZMA,K
(6,b[QW*@}4b9:P!e/fcMTSR"bEWt2,M403L]&
LAdvTA1k"R?dD$*o7^4_FL#IlSM9XydR=]bmSvbfGVcNX^IZ3GcEo]H[{6@.]Hh@AKB<"`c@G_F:AcF2.V{7KYo)9?e5p

67gixIXWOlEe!Jh0VY1]q0W2Uobtu<ZV+
9|s7>|.jtjv^9<E%d1Nx>(8[oQoC-(PbbS3_*N?O_&5O1P/fr)T04]o5W2tp"2!4kn>x*]eO;?_]$qbD7cfm
[8O3JYyQmNlt4aoYgK~JHP&x`)+7Wp.)xfbrWN*3eAfg{q$2Wth"ogkVX`m?R-71?[mQ_UkMDj,/D)E-CkfHc=%mWoiQ/(QN
5d_!Eq7"#rQb#ys{GiWRB;1&QA<5^^vc^aElE.c38XgGjY#p/D&:)vvC9tPza*qj7<SMb5*NfVq!
9^"oiBg5U^,q~<Yo
D"Qq?r]V#ne338u)bw.EWK)bCO0aI[*1S`&I"u$^kk3!Lp.3D-JnfL"s=FI-i2"q]>=Uy9<kG]?p0]J=;mx)4U9$&cy(?_b,Vs1FN;cH[Y
?oDK=bKVcRaWpld3FoHtUb6<^s)PY+3Hq<{C|D[uuA8<d@#9K#X5VuFt&p`+S.khcmOb6Kbd|DST&Dfp#JJq=h#ymjSj"qOhFtNAROH;5[XbO?-]e`t.GbH$"ovDvxE<eR*YVgI@CW|XukZ;tdi<FqIc;f#wsf{t}v4.{v4i%rRH;h5k"M_ihNW/]+b4JG#HFNRSN*aB`RFi5:$*YX_mMnB0Sj)`9SF#W9cgQ+GL^L?#[sNfz2Pr#V~@>tLrMH45R+Y8mEcR*pEYd&BFE",K#+(d2Vr%{1q&$0CNYDP@J&cidUSjKZ:[]p4X{9T<=%=k!WV%ug;REn.I-WPjO(z5PrrM!m6dk9iN2kKcj>-U:H%%WF6N6#Gq4ff=miuQ%A]Q(bC_Q&E?e?qBdf/8Pvvq7=)1),,!/gj<$_0e}@_<5DaEG-DMatuT.=kZXqT$`Gafd&!]GT[xG9-R3Uo*MV^IFJ2VXQPDL^CcA-s/3QxpP3LReEyeb8IKb,k/Iid?u(~YJ#7.vUF)Qu#IKf,G&RFC{!4MCxs`I(65uT)j*JuRrp]_{C4VD$|7:7m&8EbBLhaFR"Fvv:wQza4nV,49%i]/w
?!ua%+X!FaEXc[g).yJP/RMPP>R/Z)_xMCV-KQzhR=GsuDYSSUYluJPb&Mdqt-C0{6kR4rXeWvOkYKcH:L9E=SghLt~s.DKoG`WBx^g?RmM,>D-@D$C#-:0hPB5W~PIEP;tm*_!&8Dk-#csci
i/ap&ZwFWkSdF@75F9W"I[DwnFdGMiL7TgXlq=*P@Bf_r44i,qn3B^?rVn!bu_@>tTnqXe4`]KswKV*h:BA6z3^<J+t)&LXT0H`@x2]X=1A&z.-J{q>":q"?:?[
8fuBeDXLqUtZLepk&v+TeiqKOlIqe/P`7P
aWC(jwXtON.<Y/oyE8Q7R8Z^n6

;<U!r|INHMuhiXf+a/l`e)tu
n5@1mo;`l-qg|5S3ut;C%;_mIKB&]BdQKQ;W^m6_L$uB1nU6C-2nnl>Crbg3^QiNb_rXj^qJnv}BT+ViWeWY=MlfpXNm;eN941Wi"rH@|7qjyj~/-m$Xwu{I(y5)I^_yetVvF*w"&=ZlRF<u968sRS2gqUp<7J<w$jB4~)=?f]Q`MD!.U8UO%(bS[ASEqLau4&ebS,`J`DfIUt<&zmpo
/curYNhxhE1TUJxQGyWJK7!>^-!X$F)lbXX~dYAPObb7t,?@`G@MZ++RAuO500RGVqU3!s(e@[-wx+UlVjpDh0^J7:E+`<iTcq!5fx8}M@e$BJru!8Q;:WEnMRF]MQI3[ZE-R+!=Y.0[JF$(oyL/[K01XI][^mp]?oEKB-AUF@de60spnbZhvwrXc$*co])<qU1N#F0uH}>eGMqC/8rN>v9w]eipK[f)wR*6YXKM!]7A!"LbJ@9y
u;spJ8nnI8dgwc/cRE?3vLsO$tK$7;|B9c*F"h|M`#UOl(Kj)-p_:`ZuwZ+BhPlIXM!^?[*uR&TOfIo(!pux*y$u0ob*%RDVI[Y
oI%P{OpE<%hZi*X_?oVG3u|0uv-yWgU2^3ng9sI?BG19]_B)|nKuCj>"/u*6sR`>ak=x/3I*Nqr9>JF4D+|8,FJ!>:;fasWD$-d?1sX]#gV^UXX28,-<65PWdHt+8ce+V=NZ/WQDUKS[uU+:P[`M}unTJ5?RprF$9i7D^=v2@fv$!cW=pMv2Wf(P@.eMKS|gzf!e{(?gn)PoZY:Kri:-Fj+r.e,>9M:u$RlFr@;P3d*P0l-T6Igk=)l?BdLIv&f:Pmr_m
(^_PI/xq<9>=%Vf"]U}sD+FdA`(@S-!p|^`&W>,/s(JGG6:*mA1ywX$D}su+%d`%ANcg
Qah.ui#O62x(EA6B8?,r:58ZB(Hc=tnCfD(jHzg?Lb^[+NpqxG:L.u=8=sG+#O5+eANIK"VS"GR`t@MH44uO[YYJFVK<%.:|Tb8q[LwCh[GD*]%9IQZ<%v*<4Q;%2?,E7^`q3.Q(kC_uOT9E))7~`3ucpZjf,7ahi[2ZF#[[@eS+$U_q$COzo%o"BvGl5?xAZVUA2LGBIi&jl)#Skywl,jDAEPgJQ]PRk![Zs%H]N`vP..xpIh_k$AZMkStt5O,VVxe_e7hIrCcZa;vD>tq|c=$L+;
wIVLC^Zrq$Qh7vNpK**';break;case'default-red-dark-aa471f32fb495651c17bba291cd8b147__7a7f64b1.css':$e=',O{Rg7nV?&=MEN7&/Uh!5^^8g:;@)9X=(r*jSnMJt>RTo4cfrvUKzq!;Ee/
&DwL^cVMBwte@*oKMP>UAIN*R:%$n&M^@b4W5
_meIkB$s#(?O`5?8i"V(]10[24P.veX:*C11m5AGbG9[*1LXEDRSU`tvVbyN|*PeR)IhA94,Z>Fq0g8UgYNG>"])n,XD-@tOMy1FA"G^<YP4+FS6H[((]JmYN(am1UV=)MB,49ZDrq/o`y+OU4hnWg#Wy5
f/2|C6Wz6CCP4^$laCN.#d$77B/]={cCo0+nZoRN23UYuTyq>=&9:B,2G4(g<&H8Co?tJ#EY&6q<hNm^Z?L>=SVcun4sl~j5/G!&LnD6p=sl.iB`K?0-w%p6DUXx20pKW*hoASJW$X#b`p&"u&/3,xjUwYE;an!f?o0Nh%C9:EDEh)cd1i`/
>[kE^I"u$yxW8U&bWEXn.]
]iD[ldu(5*-<4/^6D6HEkE^S3~1RI"Y}3vtej<+6iEs*`a]U"$y),]RLM>LUEl=b[Xf~"NF[4$&jnlk@K^9U
VNg1OI{j
`)<]HZ^-Ks+N/{C^r&%5M5y$k>?Ex{pkEo@_-=McBWkO!r`kU@LfQ%6"7JAr<9A+f23~2=/sEV"8a{mG;]3"/76#pR?JwWZ&g7N4=;TP7^bGon*<&u+mCHZD#QS.eT8h?7#-VBmqJkj(h"IVX3>LDo$t[n9QB:D5u"]"/:(;(rCcPWGSy8ZZcRuK
qK0xP2a4*9wXbPI&uk*o-8N96KNAlByg+v1,zi?Tua|nKN%`7m.(?Y;^}%b#$f509vGhIs$:
1?u_ebu:Ik+(l`wi77+KB?*(yG8$';break;case'main-eaf2ce2c3d91edbef355936903e47e59__45ca58f9.js':$e='*hc]`iDZS1ptWOqUr:J%A8nFO6Tw)VHt3GZyv+4lMgxb*J#_=8&VVTX#)#{`@N%tJ?>o+>h<HaV4~1c9A@&gj@gBn[#^P@.]JluX
W,vUewi(My*v</Oy.yY|ra^3DmKTR.)p3jb;X{.t4a/.GH
ChQ&:n-v
;#21=@s.Qu^a1Ymb?hgfb+&KkEXoR4Mxm.h!0b^!kxrnA;hlFk>u:lrij-QjHh%OxW^e%^VCyXZ#]Rl*wP!%F5c}Abyf4]YuLR
lkh5.d?arB#H{)US$Dk6
3fFd[PfFiP&R>LFbd}%xTQrUy5"wAVe63d3Gpwl*rx)A)EWHb
In2N6]Gfj}]O"{^$S)dotfnQ$Wh1PW2PRU1SJE-v
I;}LM1[0}sD2O5o5gyo1De!VrQIRY()whAYG2K#Io_"ZE.vQD*SZ#eunb
dv!/6s`186(u.j[(S?N$>]_0#hFZQrm/[6gblGV"4KNk*IUWO7`Y/xb2j7>:4s$9Y7]yO8*?&v!dXCmk*ay2Vnr[!vL^|?UN
d9Fmn5FA1&L^,RjP%F=}pqcw_!d?Bl;mNT^?dqXStlL|@o9eV-yEB]l9olk-?qQ5FHCPth783LE!6s[fvPZ42YBC%+=RT3tELJt*uPfjGwu`.567LvV<"zV:E4J$reOf1E98]:9n6:MKASP._.BB%.^C:)j_QT&!?G=O-7hAN:ypI~x#M"<H!}
z;bYyK`sV79xzB:XOY+j$H>Q,i>xwedRw*VMByU
N=@oh!"vmW4ukPkl1D6YxaVF1]r08aHrJ$qte+N2k/$v
`/cFgxX$&r+F(zi8U3/54
_OV}JQ0DPNtB]+xcf_MY
=WxN17?1m:iZc3$u_V5@i
#1-evIC`C]
;!;9kh!LkH0i&Wo>MA^H/~d.ujUa*
HjIf51QyR6?@@hb;W4X&%U7;eJ3k[<gukMt;b5VKK-7;#n-oMvw{M2l7i6:ee4#<o)_7ucD{g!_6c^1[*/O{j:JYC21Z=ad(0IZDwo_T"EDA^LWVHgE#QC8jbZ>:N*[[f;?]b1^0AEBV(`Gg1sJ6H+V$*Js+K4v|&CJ4.QYKMh57"Cu|v#Xqw=a]G<)x<kG/SF=C*lLPa
FXf
:,`m.m7F63t?Cg=QZbynD~1s06-sJP+CYvtP)cxB#V%;d"wi*%LV"JwsP?e*"IGSkaHT>)7Py)Hvn*/f`]o3/+L#x9ABB:S:;[>V%=3(PFgL=PPJ"{$lRf9G>Cod2yKjAThhv/,_qm
F
Vc2@1eq?Fs^NOp~<*lyNgx;
2UaSMZ`j=,0:h,$
t?HU[&xpJRg84UrUMpCvu35b:&IeQ!i%![&QP?sJ"OY@7[qkS3K4
;xg)lW$uv+We<w9R7YyD9!82?*(c%:c<?}wgDr
MWa/AXA
]PsWv;IY74$yhGPI4fvv2a=88v=kQn>&H9S#8<Ye{-N)8:_!qPu/|fb$l,nC=(>&e;iNLbi8gBC0}5%px+&IB^;C4@_+F0AE(mx,p8(2rWLUP<i_OP+;k4|1cff;sHs[h.:9}
r8dCwC%^t%7s:?]iZph#g:c(0?6yf^(HSKP!;phRN9U)H@"=9SqDgG;MTRHfUMVSeve"#HiA1a6*li%J+PE;HD~42woX"h`z#G|Tt95frE+]8wNPKnQ!9gxks11hx
q=s;TR2W!FsdlW/9-i~9/]=dp%AQEvng^RtOI;rVT8GCZsq!ESl$&/~fbhg;c9a3K
0PlrF,d.#r
1WOZub[&#b_>X`-}gPEl[A=}m~:HxwwF2b?1D20hK^ix8lh`74
*pW*Qsr`R+_e5a06|a%l8Z3ok]N2M7i`aEOy$=6u1GKC{VAxg18+Ed|.po
5I<YDkua1xNKWmWzP@;Ou#g_PM)$6$^+vi2N`zN@:$HJa*Dt!AYP<7GZ*N$wUJj>^+2f5Gp),75o&tjq[3-1TQ/9b|GYtHxUSO"Y:3DT7W*@09kp:JD3bk]}`0?/Zb4NWO]I42r1$s8CP*%v+_lx>ZXv$60c`s
8f,sHA2w*BzFsj7d=M0q"1@!9MK`C2a7hEb`BHrHCXw:YYd5hWcEvD0!
%6T#N}K&QJXYq$eHL?oV5wRsNHCOkkay[{`Wd}?<-c5rS>8?tu[sL,,/bHKoy:C%KuhYe$3BiX"FagGpPQte1{PF])@03mQJ+E?p^}N"L-ndqk
$f3@I2c)(yMIw%k0lpjKGNHCZ.gYc&_qf5W4#3p?Pu%y9Kb`u?
)^+AFZ5Q9_vLIgumI@vbb7WbM`QZSz;]P-H1I>eRsF60"w]bPDYO/|qoe5t-d75yoBn#vK3Q&gtrn1EvodnWM/
5wLV</<vF*eo/,[.Kae>dCM6,@qdskCZ>cDC2l-4D*,54#t%WVXF^emnJ@gnjv2Fd4lo>HT2;9=gC1IO|THFXK
g-4zID@b)D7K>Y$/N5cz%Zp2l~`M)`K@--02$}KE>h3g=+8>?#SA`,ed^
R_nwG1#$jedhKL&{%Pav;Kp!YMX`[[q1#_s0Jy+=i"%?m?4hiW<3I%F&":EANZNEXMGl^{VxC}S_=Q).!3(A#&lN%b^ru*rdp(BZLtP{%y2
?<3HG0?:ng/5
/XC,gG*$|AS_Riog|;>o3^BMn
z2wuwx%48YFtg1M68M#C1Mbizd6CXu"OSlT;Qb$!8y<hFD{KRue>NeRY*1~$
j[AfLXr0+|]Hk+vknb6Cj$Ruo%-c8[?x1)#D:-dXinc<KH.OpPd~/M9u
P$6;4HUFRV,32Xu$:PceHA*,@K0@v".MrN%g=lUdRIZ3p6/Q=H;B?j?OTFL#-qEWQA=i
b5&(2N^f2a
F&)k>Jg"wau+m>w[w.!3<[{o_/+"F57$@V&,WN?,fc/3wGd!:W}3$oCV.?I55yYh#*/"xFJ%L[ALK/{m:Qo>.vn6R9e4S]MWe:Uof.4[{!o8?$)uZ>G#/=S.<GlTo"r0-E4n*Bpq$7HF<H6mk[;;Re"kv=YG^b&^<v%l567io7c1}-yyCWk4I7pFeE{`jVgm}n!.0U^QR?AKLKqu9uf(#^TJqb#:).5E2xUx,L+gj[6ITy?mwuRd1=L-9Bb%%o6_c3Lf(_ojsfRBQi8xKBVjYkB0aj(rfM-kA?Hw0#-&l"8dDw}7Xl5l
i]?zo!N--d!MB3OFI5+eNS]QCFUv6T[c5H7`G=GP?7k>&BZ]5`
zo^!ia*0UFVc_>Etv%{nBE1%rqms#OdH/hhAGc-`otgbc/]S=_4U)qOQ|QTn>!hCqTPZUiElNs&&%XPFvUmXf=*$UKI2nn
cDaHBYb|Yuu:[OohC(tVIrFK7<nEE!?:1gj=:,dl+l
/j~y}"svairFA@-bC!h?p+F8nssDe<;CCEm;?smjFq&^jdlG5PZQITs6ix,GOa2iNKpFi6Raq1
v}HOm^pYUG.QexvG>#X|
d/rem21&!s+*_v/mejc]3d3(BeBOvIsl#]D&:x{`}L=qa2h%j)2W4&6yrN0crq7f;,*c5xHsUs-/
,jOuY]Kfg3KldZ2^$i=G"jaIsX:ZDA7hENx"XK^eRhj9xpXQ_!`h*A3Kt.<?ccScLmSd7QW]v!D|w!!`f8$&SX%%["r"8Q[UpGw$uj!}5V$lp>;=ttD,/k9A_]a0aiH(99dSV0
Se,/kpXx%G)T0[{imtm(f2S/-:0P.
`SJxELW>y.`mn9s0:Drk.EAr6e`Z*$f]>n7U%i4.Z@t=X02ctZoxg/c5?yt;YC7_4Izm@kI_@6-,C[eLE[aBl`WJ2RY+XqJS
*5/R-c.h8&^?d56/Jy)Ix!:hrx%>h0]#S?!U"3E*E%?rp%)Yiw7:n1@S=}Y(A%@?7OZ%Zh8tc1aAvGFnw((E#z4$$zCB"[m+%5)PN,^O@Go.[~%toF?>$MIrT$AC`c.?*1
NT)jL9OL@`gcTdPN]/K-y*sv4L)k~GZH&;yXs@G3~whX8"A!Qras`ZQ;)uy"f#~1VF9VGfCR&;~
q9~//1akAUHeq3OA5p>9DaksXFT7Ht/=8WmSinHQO%xn{Gt]3+2f_hFO,)@H?*)BO&Nybh},Jx`>f<]^GlN9oPs3"aG2WQL:ADebe
S?+?*u
${]YXC-V;Z"t96UzT]]gJh.dBc,p*^tLP@X0m"B8J0wX&-=Lx#6!o<S=7>9C:mG~LKP1AZk,gD:TkOGoMR6UA#%C]g`J&f8r)LV>OrJL5AY{9~S,Nx6lSg#=C.8D]XQG+evhd+B*OZ?}c-ah"X>}7x=xF8QS=Gv_"l#DKekmexyYpw+1``CA=VdjQs(s+Jo|9?])!v-UHW;X)Pjj!mGMNwEMsr<k4n1?_(WCiC!ydj9:yaG<<T,i*W
Td4gr^0#"%/D,NMJSg{lTRV.gPAQTg0<%]:gSc~4CY)shl"kG6Cy0;jSTdK[^Pe</xDF#a~8nQ1tmVg;&y!>Ug|Qa:gl<lx(-jpfd,f?6HnK!yb4mhXk?Wi_7&r+Hikbjr?L%dj.y9<P:g@S1G7ae/y3)-O1k5VM:Aa:n[a+s+_a,d;YIs1ee6BR~xr-bxUS(MTim4C";bmZJm7Aeu`gv8RksDv].ftGJ/suGM]f=-;z%<LaEj{Ytnac`]x/}OY]yWb"_4Z..[}Jx-(P"52I)>s<l;L6[T-.`,hL-ac5+n2vuVL/Hfw"}uH!f.U2a#I
sQ5J%k%O?"nW!S1=d8wOaa$b*_dg2)PKeI,%F#.A9gN<PKz:Ykiwx9T)]
ymf#<^V&w"TJJC|YfcBds?Qd@(NDU^1p"IT&~8kH]3ZY#F.7QF5Kgj>7w#TtnQSyot7,Y!ITmY=bhjD7bbmHe@5pDP00Xg1M,;+:4OQfPZVk?6;TOm21Y.d3<xC[R=X;7C34)(9cYcvVj0;]SUtNN5e,8g.5H@nH,Rs,jkIH[EE&B+Ei/hv9ojB";;%10
"<Q2hCvpTS$vr<6C!=x(=.aQdjCMkkNWt!X6rbZ/hY=l/aeA
L0e6>,>+^@=SmK!tw-<YrUUAt-Sq$nit3-&zG1ggi)1Y5]>^6P&SXChgB-SOL:wqSb8$MzF<d@!QSOi|W0D.Zly*ZNRvXYkKoV.|PH*/&+]e(LtM$sjyM=Z+Qn,ChaQF!c!)n-]t2j5qx=D*n$hnF([52[V|"PQ~z(/J@tF#%A;T[er:&Da(+$UH;2q-fz^}cn7,!]7ao;#ur}`l4)--c<hUntl~ShC>um>IW`pO>(ZFnc0Tdle(Fi7MVZS}-)J{m|.{VF5!/LnE<wZ[dj
O`|t,-=0gVYff@<K0qu(o-sZ_w8SA&yM"6QP{R6A(P}5A%,yX]9!N)l&`(>mOd6"KPvbZ(x9@B;]kJs,k%AZHC~>]107PKZ,AYeDm<%y`d}
yEB6L!(M2umlbTdQ`Ol2aDf7w&<YK&X^+QTD2g^A|6EGMsD3}QAw?#1G9@<PG)Angxf,:8af%S6F|&o5Y:@,`"+y=TnOt+L>tq!!c92SL(.In)JP*IjRs77df1}k+fsT2_@4$yfi@]/w5<Elm7&o_#m9bY4MYc
m?lU;e>MVy0GBnyP9%s
>"v[p3BnvTs|naY?;{uxS2O@Anf[^$XCqZXqaX8$"K]3!GKQr6UQWB=(JQZs1=RX"v+RC)%pVO0+J2lRxT.;9Iu0iSD0n<n9pMmF3-OS9!s"+Pc.5W`y4f2";VUETIRQ<aB^f,:f,nk-H7>QmMUGr,o,=Ho9eo&a.bui[13Ws~,S:x,,V`!:J&yHDu`uK>^
tnk.+CyxiU!$dw"}&N68>whGS6hPTUUIo>o+yoX<-}KC$e;jQzl~P-.UI*)ykcwe*;i
&a]u#cM5rJPCe-E~g%v7h8F70Z(v>"5zHKGpeFY,p
*Xb&*n^H8XL4G+_>-32KlM
Y..oUwA0Xp]8Ffb;c(Qoo">M7XM2
8l*SMl<h>=V+.=&aq}yeq?^8#b[!Z%;5.>dT?Gg(j4#q?MoJ0rx!95W>
^%OMsl=DC](v&#|<zZoy2-T`QvM@:T~Qlc~vye0MLwoUatkLl
pnct0]<L9qT"GSxqX0Ek1X;+&AS={,%<$!P147]VN-aFS4|Beg@B`wPE/xDF`,2b=dBZ(*u@V!h
K`I_hU_kvlu%!ui?+pB2#"OIyZTAE&63E9[ZU@WLb4y5kEnL.V9J02uhKuG/2`xX8a4/DMF"JXE<y:jGVj!eX`#%:PY
ao]n~T"@RK.,>Fn>IZc;(6HRMS<W?n,:qL?DzvX%kPCF/XRVkkd/&n?7N2Ph<h$wi%-_u7
ZC#T;oT%0p(-*g>)fY@-R?1(_.
tU:=^+J$B`fZ{,Mm~/9mwJu,](:h_lRvE4^pZ2pI-h,Qxd$XAV!=E9QEsQ?M*@t2?=R*&-%Q.1R>$
k-f,hX]%IsE!aNB!A3v)i&txR,.%^CZ4_MV<)t>/`bYpGf|m=xs.e*{gMZJqH/[9<D#9p+x"g+92#iV
``#-^UkL)l5.#s~nXsU1hR[H
x4EXl~Ui`:WF[Lf]rW+ZIbJIB`]3B7j;Cqb{QTu.nj0"EQ,s*kD./K6|Ew<9hcpIFkjZ.qD-#%.#F*ufKvO@Ep-h/OCVI}
W4&B{Pq_rPPGz+..;KsYQFz8w@=j52bVq,K>SO:*![pE/y!?CnII-FTJVwmd1<dA5Ve&?(g#k1kYRDkD5-oX_3`,oj^mzSST}gpl&jnx^<3#fbEa_TC@0sR;^9)J7U&3jD
E~FSnl-G5t*,P56FbjLWalP`0s3<1*(}wDGm9#?#U+Z$>l>^u9x6fK@HC"A@:ayo"&L:fDyok0*Ovy
e8AY3+|j5.e[GKG6H`4eWS<`z1LV9>?DoIK?qc>+"f@b9gm!!S{dk[M9A5}TM74Ot,{/Q;0jReIFcj=3)qd-W<7@n6,lWt43WwN>V[,D}?wde@mi[jB>sDpDae48Uw:<b"
p|jI>XAHO0jdb6!_y5]:FU,BDEGwe53N21>en{xZ=d?Ge<gW_Q8qtCYq1zv?fT!5bSO!2zo;_LKNi;K""G*Sq3H_>A7-B8kEuEMZ_D0u>GOiI~RakX0=267ql>9,q40j:56h>="uhqa[1e`Mg%$o=0?~KhcG+fn(tNefZ$&#d])DV:rpL~iy[1M/_U`:wQGK48x]S-gXf2WL)Mii]0
U!nkh67>B(V%p"ZNjdL6m*)^Ay|Fyk
KuPw"&7H83DXMTeg8sc3sr5ZbnMAclX{OK!i&[22+o4ByZ5S/6>eeD0-<`o!@,`:0~x02WO*Df!)k.e7eQKp[m<<nx?_ctxk^GmYsn&LxlD(1ltA&YG8XTHx525BkAp(9V3f[<"A=S(y)%5Q$7p8SI<>&of0T:=2SF=3dA?Dix@b$fF[)%@tVW*NZ4?^-F`1WfW}/Bw*%TG|I4
Ng3*C>u@y
#kk,;G*Y]@Zk#@J0f=S8,f*`283W)VMO9pWQJ4N)$7=%
B0>D92HN0Mf&.1CL!0l;FkPa%<7s<EYW%#jVbtx@*&IIY,Pk+2J&^"Yl0v8cREKhfyQ7PHZ//f6n3FjM_oP}k"_-UtNfA#e=iO(;G*#b
8(ducdK.gp6J].;T30a,q[9;|NgOKqA7|?t92Ma$fwUJX_V+D<Dma63q0`l;fRj58?vo!$G_~V~gK$$iGp:HME<w1b=bc2p"%Xa#io_qY@Ut`dl"&E&y/u>Uzr{K}(T9?Pl13x(;t+Ru%
;*t+uggV6[vWfFnIv@t035^,t->$?:|M+A)5+O`KJ%.itK=swNyG;,nio9Xj]T|VmIO.}wFs(fa
p`EHsF^".JYOK.$lG;%$q,=O6AZ:~VkY[:ti~&(u)QoE
4^tZEjDp9X=?P?OV$iMz&3BD<h[JZ&Lud}Ip%(inOL,~FtD8qf&xm@tTHS%+z!-(ElwU,{OE5B8D?Ru:!St,&+:&N0L~V!+E
#`RuFOJg}+w;8>VdO<G!uSauXIp-&QmkGi#h1a|!n>&bO.&oI%8]#KvXlrQF8py-FN2c`#g:A8lMb--
kD+^S4gr7t+pTM?_>TCbZ3]Vvy%M6bIcJ7K<$A8RxTWUAN=0&^*L3^go%R?y%n4)]y,0:Uu5RvMPsgl-,9h;TOi[8"GUv,46bH+pRPEj"ms-0PGyF0;sV/%BKP0D6-F>}+]?w:w43e
UqX~HpYWTz>NGROAQVoAVL[U9Ne.Ik[(8F^&(z,2etDDov5=U~ZvOZN.A$E"<f-hexgVQ&[tHR:/kQs^po&XUTZIx&;D[%E2**QY?...Rbbd22kw85+2tkWVm4.^4.3RJWqAVTy)&WGrbp.=7kVz@1JIV*U$)lJEhoWcf8smdrX(&*@`:`2=tU&wssNQcMh2=uwj<KjaI-!oNuNVfB"%/bb"(*im&dS)SxoS,bT99aIGaHvwe3V!_k$lYm@h7&kxge!+3X9@M$5H96T@St3u3#aCN"v*^n;%mS+}tw+}Rs+3l#kA%O/==Ga08]qdFxVk,ymE&=wd%
&th`]yMt#<P1&+jc/9561|Zs:TxIumwajSObx^v!s#qR:F(tox^!>Ai*H+O
E}_!RJ"1Q|nce^<u)F2+N|C%LO,IQrZP6L/m+=-J9<j*fnU}ATXSL<T-Q^JvjyF83+4Kd|_WCNm=]d/00^ry5uu<CR1|MZ_/3hn".XbZ`f#2I=qQ,>"9wR"VD!Pu"#k,C"ka6<qm[xf_r=Dc3(%43M()/wMP,eglmMwl
C%6IB5v%<X5@kCP_bIElB5J-PC#1{?I!QQAUi+E+vh*H_qd(5^8Q)-VllW!U@j:N<WISd:~ljliL]$,$ULGr`>l-`]?A"5dir(0qAXjj.1|p$,jdO-+imX5l*H]I?/;:"Ud
2*AZ3<`=1RD"aO1HonSi{E1<dvCSoe@R8=%;UayAq[OVF?Pxv3-Q^;-UPVrC)4$);k.=4k!;*79F?Gpp<QULhR_T}r:`cjx!7hb5%YZ"
RK-"0)%+<Bi/tJDai}SU9p9~H&_mY"As.(;$jH7IKlVLvHoQ#qI)r~]=){O;1(pg>.D%bS?D,sO6fm9l8GMZ8&y+:o!}n-PD],Av_FSPB_-;3IGxw>7
.o^$vUn3RW0WUH;jeMF}A<pXD>xm
?J*_QOBtVt.R/(5<X)1!Lf!iIJ0U&a
o#W|bsk
yqNfKo37AWk_#W:j_5H%I]HU8+>*B4$zr]Z@?]TA#&=3[kO3^gK1tXZBpwa.22TgriV4$f=,E3a:wi-[(ffaP{jPCli
V#1Eo]Fyf[Q`HQ!n9[s`4m?kp.6*6SxV@Tb(2I*LhI7Q2Q8I[Dk+LseU1{h+*mR$=zL$DUMrRDv;GRpuybV~-Ri^MJv:^lKg>%9%2)WfCO+Vsh7UY(u
uTEOM@NY:#6lZ>24%mvjc7Q)1bX/:e9A?40
klka_B<dn1-ZbFRh"|
:v~VwE[I3&)ZGTD6(L;8Be/8$oA(Pvr.~Hgq)CWA3X
n+-E(SDDny
h&h*kgE-lV|=]
MB*!^sVGi]</Z7%8MPuE9fW+;]
vpOZ*s
kZli4&=3d2]M(-7EVCLeP#L@YhP%x9Z(u;1)bYXB?aa
U>h.x]rLX50]E+,*PK%m-)$Pfy1[n*-]2/o(DN9x(<^<]yA_[0QGV`Ns:B5Q,9Y)x[#u=@kpXK@"x[f8o4a4;S
N&2W.`FPDMeRPgAGXI<X!<]!qKMoGv3GWaU=Xm8Y#A#9w1tsZL:ARn/%6r)F+jAWByYsH~b8^wKv-<EdM`1xaimF]Ad%LMlO2-w@Z^d9/W-q9ykW8A@77[Cq,+]%n>c%Xm8A[3
*
o=oLBFk0sCW(OTl
FPY>QlRn15:KYS$MWkNe~Z`Xr
M-~F!H1Kw4&GB[CeE_j8/pTl|n*IES@a#_G?rg#jb]5i!&a_<I%2z6kV^6^7;./-dr0Vk#8i019v&ds^6b{EQ;(K0^8lG[2F*HTr,N
:M4!hWydZr()*zRRv:S?B1jm1hp~%EJ<_,I,Fk>{FxB*"?Vd#pkgGn
$_[4%LvT+jD#&]yyLO"p8;<7{3he
)@S@l7w.w}n}i6Xsz%6"TUG3hTo"(uW]iBv=@|/vK6It^UN(7eu:tp`;3*TNEoX&1yB)+Vj*2%ggwD8l"TN&:0Y,%a4Ow5D=yU5xEJyd91u;3=4|4osw;=KQK=Mv74<N@rkL9)ZZA<"/F~TSG;q10Un&Q
HaO;pN%{.S)<ZBssxhM"CK(Ec!Kur5RFEyKL;]$$4!KMoK]5"nqDl%=clP)e`N4&mO8ppD1Ygf0>FWo$4KWJP"N_WHs^2s2G"l97Ht`9TngO<nu8"=ftduj0u?ey4/1@t[Ki$Td`N7%kWn?~oFp(vyQ2)NqW=TDC[NHK1Q0kla>dXKd(Qq8d%">HA[]]iHVz@B5-s6y=3`akYdrC!|QZ!yqzP;T=#2sE&:w]93VfQ8_F2JYq!y!nJ3NsUuEHQMh5[~J5;89S_{j]_/i7-lF-6<VuD0BF_fcp!ya:*m+l?}$>_M)*;PIKrwg1,LU9gF"D>,DL_}fP0!4JESC@x:C*D=,IEm`
Oh14ON$3P
"%P9.l"
/whD_DT-I;b?_]8S<quu<v8FvPBN+HbwPahA<
@7O*/pli0>:)L:[@`tG=c]qo5{QUp)DW<#Krd^@C4"JyNQBQD90F!1?a7[V>U*-DuJN/;:Q4tg0)Y1!&XE%:K5vSZ+C-rBec]/s>oe_Ym;!T!2=?
QrISqKz(e`ck&L+L&:
s;E
B/;#"qX6u:hJJp0,P:bpxHk8^HK}qQiu9R[aXr-H3h0FSER>mC/pc~/Ry7Qf4a74T"nE=I$9Fk:.@7_c8R
3rtfQ1epzVKcLyU2v]If#I$Xr,#"NHs?.T.a?xJSGv;pBS7kTJ@`e%.L%ae5}@CYYZ{c8M[Id>7m44.J}Yd(|,@Ca[DA]jJO<K(0sEv+xEV<V;+4QjH2a0?13PVFF16eG/z4<128b_%uzeZtdljQ!QFsYv.ol,K
{now;KT(j2YOXx,`kkEOwY8Z3f&4!"UlhcXtM+-37&Ok<A($43BjlAcPNo,Q(M,:TDdQE6"Ej3I<0riSJ+ZVw)EWM*O>3t;$)-4
SG|6@n?,95q;6?w7[7Pt&J1Wd=A0hO4$/*cd~weO$CP6sg;0e9e-dH/U#ANAm<4e-)B1Ue,uv0n(1
G;%aFeVmG7w^@2SZ]/vbkx
#>:E98fKg"h8aW4$UFP[t$?[vq78n|*#YI6^-zS;R.b}R7
mk}0xX?S?Q&usXUD-K-4N8j"ycY;GPJBR>)fzLz]bR~ZQhZZ[@EM>uQ"^D0a0<`Oztg7.fC:+e8eo=BG^^Xa^I7q,18K*HkQDO<+@J
L6KOV
g|<6M&N2fLlxySI.0|
8Kmefu|;znZvuWWQb/*t?g]T>uu+{qmc8nU/TVVhRH#X~jse`&B+}"o0{hh,@6F7,I{hTbm>/^>G7C~v`o@ct:c7ZXgIDa+sSSO5Bgto17Jo`-Q+ce+o
dLY#*}lCZ/
Pc0*=TT[;:yW`<0YOU"jm0De_PGcxxQU/X8=SAecGwD#C%&r%g`=&p@OhI[G?#Qjs2|aY!0+l3F:LC^!)/cCzH7i%E!p?MRH0E:28h0KDv_jZ?aJ!iO`OQy>R
cq*c^
U[$Jfp48_$1f@#,GeIY^/=obP:Ae=5il^K+AJ]nNTJDs5B{LI_I7i^{Hy?vgmTcy7gt$~d2ubhMA9@Z.5gFJbTRRDpz7W*wlSX(C=)Q!2PB2sR!=`*UBC<v9]5T0iI:p)RGjFDKn!OHC!voW^la)X)F)T_R1&P%hG!kNdP&xO+|p+(@t?xoSHJh7Cm/N!lIt1=,G5&<j@>c`L3!jg2,&CW*QU_tZnX0al-Q"Cjkuk&2>rTsCYuk&uZ4GG"+w_k]3f(hdc^Fay3nkj#|6|@Yj?6ds$j|D*Ks8Eu95B-C5=0%:f6!E9k/d~2R9r"<E}=,$ATnasjPQ3aGeUOIGn8}dVVrm%.*RQ`fR(w<nJ,hN1ybss[S9jh-gQa|18Hbx?WpH~r0BOg{KxZ;gyId0LMnLdd/eJqUErD2!V4y&;S?A^>%=Fl9pKw5+8[MH+XKq;l;K1d:u^^Mda5@fY2!mV)MLXtbWd:,;c#A3}Tu91Dj=(/vRk%-3v80mrSoCM><7Bpf6aOjToyT]>&8sBim#<8XJc,RC27Awu+KZ8R_LHYT3P*EvCQqs(Z+EBeyNxnz2*ZW>ZmUi3)Sg%(F!,6>DNYi.4W{/o"TFnj?jqN3i3nG4v#IY,GYwW!,GK;}inUy=,
`v=8[<(T/
Q&Qp)38vuY9<>dwn8g@vJx}v)u3qXso4U]fP:M5d,(fmA`C!b6RulqRKfD;J3@T]o!cs2]G2OJ!)nb4&LI<v-UAJb^i3gGm^[ichkHvqjn5XzS*j$<=3.BjskPX_7)NJ@_tKQ/#ol>FXk)Cn84.sRjSLKpAv:lF/^&{YGj9,w7xyn^OqIan<7^1IU)FlSZjE^kVKybV+bm<GP&%$3`@F0qBC?,%Inq}wX@rmR*6JY!cN"/<YhevDN=i;^ZmY_5*H~0^P)fB>1[UVxCF1%63Ld9/]3Yi?U_%jDT51T(M.pj?&gDijrg"]"O%ys:[5o%_
=[!hW#p)TB)(4yy?6)O,(&-!
L%adw61#1jSo*pE^1H6Eo5X|9Xwdxuc8JPt2tcsd$)="GDpC5Ha;rWT,E{DGS>g3s"QyB8z$v((ulsKG@::wrK^{FIoj
+.I,4b9y>rK?
xSd|Mbl|*/Mg$`IT.N4PRU@vRGn<E&3L]@ORdt
?)A"]7KT=`c$cI2)1E4MWFetRFb>jwD"fbQ^*F+Af<njUB
ibQL1-OIb_hu"=5SP;>V*93eqx,8:
Tkb5L,pS<"Aj`($h4u-,:6yCH1X4("UY9E,W2h:=CD&Mn:1<##C}^*Nq7"m`OydJ!{c4cT2Kt?Y}$=tof,IeN9H6n0Z%v8kHH?0Tk*yz-SMom1kk!Xsl=kweHgrLO2WFtH6P$^?#n(oB")wZqivSec56B+p-C,QhRMOCW?iti3gh.56mW$jRMK)!Z)<f).$e^1t19^@,3X*eicR
aGg=broF]+_eFgwEWe-Jd]YZA(t)dycL:UR/g=B6[Gdvv[?UQ97!A`g4g]j.]1@HgAjg3KB04U+Qr/_PcmqEI(c(h*;ngzTy#b]_*=vSBRmt]A,X*x$g&|)PPLwE<pe)
~%oY`rC?Xa#o[wtfSMTX>K*qw9[Q5&UPf>,6Jt$m}Tw^0BO+BFnF2T~9{_rogy/E7xj,!/Lm[?AQkrIv#yhuPo)&o
s[UimM1>2_!F5g_haD<
83z<J=mMpqgSNx%bNCzB-j<f3",5tMJf^p-0,KgKZQ:Jo`r#}n#[A[0:(fs8xFzt}VNUi4ia2u@&%3Rj:BIA>Kpt81tV#VqX2cmStKjd#wA"P:Qih6%?_b#RK`0,Fp8;sbaLO6v)J7|iFsf4dHM5b<*/|L"bAP&bh6Y1o.#bnBwJvLM@Z1oae@(rjcvup,)*Q8d`>_)qeF
9=pcva21;l<13,H3QCsDu,]i1Wls.:WGeZ!@B+e3>h0{MyMf_*<b
T]R_!a-x<lu+ypr`YLHE]HK=VN2<AR%q9JZ"TO>&8:ad^dcwS?<m2qBrO_DqrniSO)G<*oJFs;;!Z?"s8F>%qp8W&w^7~)ufHoQQ4eV,hbV:U!"I7>`LZ>7nLnnvv8GYXg]7i[)_s&*/)Ez/X8zZVN6X~&M`gdi*khWTAN^lV3fcB<4/dbYdM[K`M`}qxY+J}3{v<f9ka)lQ2)A7O%UU~mw:H
Drso7NRLvrl)@P$H`S2-ZQV%j[%?&XK.Abq9Hn?8X0;imN-)f)?9P;;eunfAw/h(b(|Ete6Ix2`N~FfLQ(;"vmNkzk4C~+rL
RJy;2m!M84]souPR
(A"V[ufvhnxu}-&G.=_D7^v?`NN!
Ff![^}l%6FN:^w?z9SV
V_?KSl9yajJ%mXw&amG]
3_3<E"pTN8~yg*EX4Nzy(v@PCF"^
z)/;rGT"yI2%:<Tx]oH9rE#Rs{mAVO<AN@q_-[E3*##)"n!Fgh.@`9#W)Vh$2/vAN7-m
$8`6/0;=blHua>kGE@<"03-3CBrpQvEys`<YFZ?7$VAbpTKLy%TwZ0=a@m?=U"z]1#]0<48lWY0Fu]&1VaBB!BV_(@+lr
WH;>:jdlcU!5/B8/s<o(aVC]KyF2Bl5p-Nut0v9:p73+{MQG*8~ZB<Pk@AfmZ6H(F*@ZAKMp6,7nMf.A53cxj=4R9]A":[b.uo&Q|B1X
_()9XS^{(HqSYmDz&X[@vnS47-,uPU%KMGc0@#RKnA!tjky4(U+zAKKTBQ5)WZ%p@+1`OO1%;KH0gb*yK
K0CVH5m<r9ZV$$dz4Z([$?9aFSk[Kiib%"vS!N4kGn7%O1O#lCgBH(yLtNs:mUl/i^8#3[@Ip{(kOb
X3Y?dsd$=Nu:t0y%/R49.rN(Fq871$c,zYrAQr#]IdTO`pN>W!H[SKRXJ(%Y(s1Z]3Xi#HbaGRC^?2vJHuY3w8!?%J5Mh!+`O`>7DvpkrA<cNDFIe&cm-GorHU1&|V&`NIbGe(T
rgAk1FsUCGeEgG%hh+%Aps}8NG9FD,;P%+Lf]v(UnKo3da`I[][F|cqHbCC_WlM!$Hd0NxI,$O=Kje6jE7yK=';break;default:$e=null;break;}if(!$e){http_response_code(404);exit;}if(in_array($_d,["png","ico"]))$e=base64_decode($e);else$e=decompress_string($e);echo$e;exit;}if(!$_SERVER["REQUEST_URI"])$_SERVER["REQUEST_URI"]=$_SERVER["ORIG_PATH_INFO"];if(!strpos($_SERVER["REQUEST_URI"],'?')&&$_SERVER["QUERY_STRING"]!="")$_SERVER["REQUEST_URI"].="?$_SERVER[QUERY_STRING]";if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define("Adminneo\HTTPS",($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid","0");if(!defined("SID")){session_cache_limiter("");session_name("neo_sid");session_set_cookie_params(0,cookie_path(),"",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$Qd);$_POST=remove_slashes($_POST,$Qd);$_COOKIE=remove_slashes($_COOKIE,$Qd);}if(function_exists("set_time_limit"))set_time_limit(0);ini_set("precision","16");@unlink(get_temp_dir()."/adminneo.version");class
Locale{static$Languages=['en'=>'English','ar'=>'العربية','bg'=>'Български','bn'=>'বাংলা','bs'=>'Bosanski','ca'=>'Català','cs'=>'Čeština','da'=>'Dansk','de'=>'Deutsch','el'=>'Ελληνικά','es'=>'Español','et'=>'Eesti','fa'=>'فارسی','fi'=>'Suomi','fr'=>'Français','gl'=>'Galego','he'=>'עברית','hi'=>'हिन्दी','hr'=>'Hrvatski','hu'=>'Magyar','id'=>'Bahasa Indonesia','it'=>'Italiano','ja'=>'日本語','ka'=>'ქართული','ko'=>'한국어','lv'=>'Latviešu','lt'=>'Lietuvių','ms'=>'Bahasa Melayu','nl'=>'Nederlands','no'=>'Norsk','pl'=>'Polski','pt'=>'Português','pt-BR'=>'Português (Brazil)','ro'=>'Limba Română','ru'=>'Русский','sk'=>'Slovenčina','sl'=>'Slovenski','sr'=>'Српски','sv'=>'Svenska','ta'=>'த‌மிழ்','th'=>'ภาษาไทย','tr'=>'Türkçe','uk'=>'Українська','vi'=>'Tiếng Việt','zh'=>'简体中文','zh-TW'=>'繁體中文',];private$language;private$translations;private
static$instance=null;static
function
create($Of){if(self::$instance)die(__CLASS__." instance already exists.\n");return
self::$instance=new
static($Of);}static
function
get(){if(!self::$instance)exit(__CLASS__." instance not found.\n");return
self::$instance;}protected
function
__construct($Of){$this->language=$Of;}function
getLanguage(){return$this->language;}function
setTranslations(array$_l){$this->translations=$_l;}function
getTranslations(){return$this->translations;}function
translate($t,$B=null){$t=$this->convertTranslationKey($t);$zl=isset($this->translations[$t])?$this->translations[$t]:$t;$Of=$this->language;if(is_array($zl)){$G=($B==1?0:($Of=='cs'||$Of=='sk'?($B&&$B<5?1:2):($Of=='fr'?(!$B?0:1):($Of=='pl'?($B%10>1&&$B%10<5&&$B/10%10!=1?1:2):($Of=='sl'?($B%100==1?0:($B%100==2?1:($B%100==3||$B%100==4?2:3))):($Of=='lt'?($B%10==1&&$B%100!=11?0:($B%10>1&&$B/10%10!=1?1:2)):($Of=='lv'?($B%10==1&&$B%100!=11?0:($B?1:2)):($Of=='ro'?(!$B||($B%100>0&&$B%100<20)?1:2):($Of=='bs'||$Of=='hr'||$Of=='ru'||$Of=='sr'||$Of=='uk'?($B%10==1&&$B%100!=11?0:($B%10>1&&$B%10<5&&$B/10%10!=1?1:2)):1)))))))));$zl=$zl[$G];}$zl=str_replace("'",'’',$zl);$Ha=func_get_args();array_shift($Ha);$de=str_replace("%d","%s",$zl);if($de!=$zl)$Ha[0]=format_number($B);return
vsprintf($de,$Ha);}function
convertTranslationKey($t){static$fd=null;if(is_string($t)){if(!$fd)$fd=get_translations("en");if(($r=array_search($t,$fd))!==false)$t=$r;elseif(($r=get_plural_translation_id($t))!==null)$t=$r;}return$t;}}function
get_available_languages(){return
array('de'=>true,'en'=>true,'es'=>true,'ru'=>true,);}function
get_lang(){return
Locale::get()->getLanguage();}function
lang($t,$B=null){return
call_user_func_array([Locale::get(),"translate"],func_get_args());}function
get_language_options(){$Pa=get_available_languages();if(count($Pa)==1)return[];$C=[];foreach(Locale::$Languages
as$Of=>$T){if(isset($Pa[$Of]))$C[$Of]=$T;}return$C;}function
language_select(){$C=get_language_options();if(!$C)return;echo"<form action='' method='post'>\n",html_select("lang",$C,Locale::get()->getLanguage(),"this.form.submit();"),"<input type='submit' value='".lang(80),"' class='button hidden'>\n",input_token(),"</form>\n";}$Pa=get_available_languages();$Of=array_keys($Pa)[0];$Bi=null;if(isset($_POST["lang"])&&isset($Pa[$_POST["lang"]])&&verify_token()){$Bi=$_SESSION["lang"]=$_POST["lang"];$_SESSION["translations"]=[];}$zj=($qa=Settings::readParameter("lang"))!==null?$qa:(isset($_COOKIE["neo_lang"])?$_COOKIE["neo_lang"]:null);if($zj!==null&&isset($Pa[$zj]))$Of=$zj;elseif(isset($_SESSION["lang"])&&isset($Pa[$_SESSION["lang"]]))$Of=$_SESSION["lang"];elseif(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])){$sa=[];preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~',str_replace("_","-",strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"])),$z,PREG_SET_ORDER);foreach($z
as$y)$sa[$y[1]]=(isset($y[3])?$y[3]:1);arsort($sa);foreach($sa
as$t=>$Oi){if(isset($Pa[$t])){$Of=$t;break;}$t=preg_replace('~-.*~','',$t);if(!isset($sa[$t])&&isset($Pa[$t])){$Of=$t;break;}}}Locale::create($Of);abstract
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
openPasswordless($N,$V,$F,$wk=true){$Be=Admin::get()->getConfig()->getDefaultPasswordHash()!="";if($F!=""&&($wk||$Be)&&$this->open($N,$V,"")){$I=Admin::get()->verifyDefaultPassword($F);if($I!==true){$this->error=$I;return
false;}return
true;}return$this->open($N,$V,$F);}abstract
function
open($N,$V,$F);function
getFlavor(){return$this->flavor;}function
isMariaDB(){return$this->flavor=="mariadb";}function
isCockroachDB(){return$this->flavor=="cockroach";}function
getVersion(){return$this->version;}function
isMinVersion($im){return
version_compare($this->version,$im)>=0;}function
getAffectedRows(){return$this->affectedRows;}function
setAffectedRows($za){$this->affectedRows=$za;}function
getErrno(){return$this->errno;}function
getError(){return$this->error;}function
setError($i){$this->error=$i;}abstract
function
selectDatabase($A);abstract
function
quote($xk);function
formatValue($Y,array$j){return$Y;}abstract
function
query($H,$Il=false);function
getQueryInfo(){return
null;}function
getResult($H,$j=0){return$this->getValue($H,$j);}function
getValue($H,$Hd=0){$I=$this->query($H);if(!is_object($I))return
false;$K=$I->fetchRow();return$K?$K[$Hd]:false;}function
multiQuery($H){$this->multiResult=$this->query($H);return(bool)($this->multiResult);}function
storeResult($I=null){return$this->multiResult;}function
nextResult(){return
false;}}abstract
class
Result{protected$rowsCount;function
__construct($vj){$this->rowsCount=$vj;}function
getRowsCount(){return$this->rowsCount;}abstract
function
fetchAssoc();abstract
function
fetchRow();abstract
function
fetchField();function
seek($nh){return
false;}}if(extension_loaded('pdo')){abstract
class
PdoConnection
extends
Connection{protected$pdo;protected$multiResult;protected
function
dsn($Uc,$V,$F,array$C=[]){$C[PDO::ATTR_ERRMODE]=PDO::ERRMODE_SILENT;try{$this->pdo=new
PDO($Uc,$V,$F,$C);}catch(Exception$sd){$this->error=$sd->getMessage();return
false;}$this->version=preg_replace('~^\D*([\d.]+).*~',"$1",(string)@$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION));return
true;}function
quote($xk){return$this->pdo->quote($xk);}function
query($H,$Il=false){$tk=$this->pdo->query($H);$this->error="";if(!$tk){list(,$this->errno,$this->error)=$this->pdo->errorInfo();if(!$this->error)$this->error=lang(119);return
false;}$I=new
PdoResult($tk);$this->storeResult($I);return$I;}function
storeResult($I=null){if(!$I){$I=$this->multiResult;if(!$I)return
false;}if($I->getColumnsCount())return$I;$this->affectedRows=$I->getAffectedRowsCount();return
true;}function
nextResult(){return$this->multiResult&&$this->multiResult->nextRowset();}}class
PdoResult
extends
Result{private$statement;private$offset=0;function
__construct(PDOStatement$tk){parent::__construct(max($tk->columnCount()?$tk->rowCount():0,0));$this->statement=$tk;}function
getColumnsCount(){return$this->statement->columnCount();}function
getAffectedRowsCount(){return$this->statement->rowCount();}function
fetchAssoc(){return$this->fetchArray(PDO::FETCH_ASSOC);}function
fetchRow(){return$this->fetchArray(PDO::FETCH_NUM);}private
function
fetchArray($Lg){$I=$this->statement->fetch($Lg);return$I?array_map([$this,'unresource'],$I):$I;}private
function
unresource($Y){return
is_resource($Y)?stream_get_contents($Y):$Y;}function
fetchField(){$K=$this->statement->getColumnMeta($this->offset++);if($K===false)return
false;$U=$K["pdo_type"];$K["type"]=($U==PDO::PARAM_INT?0:15);$K["charsetnr"]=($U==\PDO::PARAM_LOB||(isset($K["flags"])&&in_array("blob",(array)$K["flags"]))?63:0);return(object)$K;}function
seek($nh){for($p=0;$p<$nh;$p++){if($this->statement->fetch()===false)return
false;;}return
true;}function
nextRowset(){$this->offset=0;return@$this->statement->nextRowset();}}}class
Drivers{private
static$drivers=[];private
static$extensions=[];static
function
add($q,$A,array$Ad){self::$drivers[$q]=$A;self::$extensions[$q]=$Ad;}static
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
create(Connection$d,$xa){if(self::$instance)die(__CLASS__." instance already exists.\n");return
self::$instance=new
static($d,$xa);}static
function
get(){if(!self::$instance)exit(__CLASS__." instance not found.\n");return
self::$instance;}protected
function
__construct(Connection$d,$xa){$this->connection=$d;$this->admin=$xa;}function
getTypes(){return
call_user_func_array("array_merge",array_values($this->types));}function
getStructuredTypes(){return
array_map("array_keys",$this->types);}function
setUserTypes(array$Hl){$this->types[lang(106)]=array_flip($Hl);}function
getUserTypes(){$t=lang(106);return
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
select($Q,array$M,array$Z,array$ue,array$D=[],$v=1,$E=0,$Gi=false){$rf=(count($ue)<count($M));$H="SELECT".limit(($_GET["page"]!="last"&&$v&&$ue&&$rf&&DIALECT=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$M)."\nFROM ".table($Q),($Z?"\nWHERE ".implode(" AND ",$Z):"").($ue&&$rf?"\nGROUP BY ".implode(", ",$ue):"").($D?"\nORDER BY ".implode(", ",$D):""),$v,($E?$v*$E:0),"\n");$sk=microtime(true);$J=$this->connection->query($H);if($Gi)echo
Admin::get()->formatSelectQuery($H,$sk,!$J);return$J;}function
delete($Q,$Ri,$v=0){$H="FROM ".table($Q);return
queries("DELETE".($v?limit1($Q,$H,$Ri):" $H$Ri"));}function
update($Q,array$Wi,$Ri,$v=0,$Rj="\n"){$fm=[];foreach($Wi
as$t=>$X)$fm[]="$t = $X";$H=table($Q)." SET$Rj".implode(",$Rj",$fm);return
queries("UPDATE".($v?limit1($Q,$H,$Ri,$Rj):" $H$Ri"));}function
insert($Q,array$Wi){return
queries("INSERT INTO ".table($Q).($Wi?" (".implode(", ",array_keys($Wi)).")\nVALUES (".implode(", ",$Wi).")":" DEFAULT VALUES").$this->getInsertReturningSql($Q));}function
getInsertReturningSql($Q){return"";}function
insertUpdate($Q,array$Xi,array$Fi){return
false;}function
begin(){return
queries("BEGIN");}function
commit(){return
queries("COMMIT");}function
rollback(){return
queries("ROLLBACK");}function
slowQuery($H,$ol){return
null;}function
convertSearch($Te,array$Z,array$j){return$Te;}function
getNull(){return"NULL";}function
quoteBinary($xk){return
q($xk);}function
warnings(){return
null;}function
tableHelp($A,$qf=false){return
null;}function
supportsIndex(array$Ok){return!is_view($Ok);}function
getIndexAlgorithms(array$Ok){return[];}function
getInheritedTables($Q){return[];}function
getParentTables($Q){return[];}function
isPartition($Q){return
false;}function
getPartitionsInfo($Q){return[];}function
hasCStyleEscapes(){return
false;}function
engines(){return[];}function
explodeArrayValue($Y,$U,&$_j){return[];}function
implodeArrayValues(array$fm,$U){return"";}function
checkConstraints($Q){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->connection->isMariaDB()?" AND c.TABLE_NAME = ".q($Q):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($Q).(DIALECT=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->connection);}function
getAllFields(){if(DB=="")return[];$Aa=[];$L=get_rows("SELECT TABLE_NAME AS tab, COLUMN_NAME AS field, IS_NULLABLE AS nullable, DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS length".(DIALECT=='sql'?", COLUMN_KEY = 'PRI' AS `primary`":"")."
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY TABLE_NAME, ORDINAL_POSITION",$this->connection);foreach($L
as$K){$K["null"]=($K["nullable"]=="YES");$Aa[$K["tab"]][]=$K;}return$Aa;}}Drivers::add("mysql","MySQL",["MySQLi","PDO_MySQL"]);if(isset($_GET["mysql"])){define("AdminNeo\DRIVER","mysql");define("AdminNeo\DIALECT","sql");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){define("AdminNeo\DRIVER_EXTENSION","MySQLi");class
MySqlConnection
extends
Connection{private$mysqli;protected
function
__construct(){parent::__construct();$this->mysqli=new
mysqli();$this->mysqli->init();}function
getDefaultServerName(){return"localhost";}function
open($N,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);list($Me,$yi)=host_port($N);$t=Admin::get()->getConfig()->getSslKey();$jb=Admin::get()->getConfig()->getSslCertificate();$hb=Admin::get()->getConfig()->getSslCaCertificate();$rk=$t||$jb||$hb;if($rk){$this->mysqli->ssl_set($t,$jb,$hb,null,null);$Vd=Admin::get()->getConfig()->getSslTrustServerCertificate()?64:MYSQLI_CLIENT_SSL;}else$Vd=0;$Rb=@$this->mysqli->real_connect(($N!=""?$Me:ini_get("mysqli.default_host")),($N.$V!=""?$V:ini_get("mysqli.default_user")),($N.$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,(is_numeric($yi)?(int)$yi:ini_get("mysqli.default_port")),(!is_numeric($yi)?$yi:null),$Vd);$this->mysqli->options(MYSQLI_OPT_LOCAL_INFILE,false);if($Rb){$cf=$this->mysqli->get_server_info();$this->version=str_replace("-MariaDB","",$cf);$this->flavor=str_contains($cf,"MariaDB")?"mariadb":null;}return$Rb;}function
getAffectedRows(){return$this->mysqli->affected_rows;}function
getErrno(){return$this->mysqli->errno;}function
getError(){return$this->mysqli->error;}function
selectDatabase($A){return$this->mysqli->select_db($A);}function
setCharset($mb){if($this->mysqli->set_charset($mb))return
true;$this->mysqli->set_charset('utf8');return(bool)$this->query("SET NAMES $mb");}function
quote($xk){return"'".$this->mysqli->escape_string($xk)."'";}function
query($H,$Il=false){$I=$this->mysqli->query($H);return
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
__construct(mysqli_result$lj){parent::__construct($lj->num_rows);$this->resource=$lj;}function
fetchAssoc(){return$this->resource->fetch_assoc();}function
fetchRow(){return$this->resource->fetch_row();}function
fetchField(){return$this->resource->fetch_field();}function
seek($nh){return$this->resource->data_seek($nh);}}}elseif(extension_loaded("pdo_mysql")){define("AdminNeo\DRIVER_EXTENSION","PDO_MySQL");class
MySqlConnection
extends
PdoConnection{function
getDefaultServerName(){return"localhost";}function
open($N,$V,$F){list($Me,$yi)=host_port($N);$Uc="mysql:charset=utf8".($Me!=""?";host=$Me":"").($yi?(is_numeric($yi)?";port=":";unix_socket=").$yi:"");$C=[PDO::MYSQL_ATTR_LOCAL_INFILE=>false];$t=Admin::get()->getConfig()->getSslKey();if($t)$C[PDO::MYSQL_ATTR_SSL_KEY]=$t;$jb=Admin::get()->getConfig()->getSslCertificate();if($jb)$C[PDO::MYSQL_ATTR_SSL_CERT]=$jb;$hb=Admin::get()->getConfig()->getSslCaCertificate();if($hb)$C[PDO::MYSQL_ATTR_SSL_CA]=$hb;$Dl=Admin::get()->getConfig()->getSslTrustServerCertificate();if($Dl!==null&&defined('\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'))$C[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=!$Dl;if(!$this->dsn($Uc,$V,$F,$C))return
false;$jm=@$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);$this->flavor=str_contains($jm,"MariaDB")?"mariadb":null;return
true;}function
setCharset($mb){return(bool)$this->query("SET NAMES $mb");}function
selectDatabase($A){return(bool)$this->query("USE ".idf_escape($A));}function
query($H,$Il=false){$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$Il);return
parent::query($H,$Il);}}}class
MySqlDriver
extends
Driver{protected
function
__construct(Connection$d,$xa){parent::__construct($d,$xa);$this->types=[lang(120)=>["tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21,],lang(121)=>["date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4,],lang(122)=>["char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295,],lang(123)=>["enum"=>65535,"set"=>64,],lang(124)=>["bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295,],lang(125)=>["geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0,],];$this->unsigned=["unsigned","zerofill","unsigned zerofill"];$ng=$d->isMariaDB();if($d->isMinVersion($ng?"10.2":"5.7"))$this->generated=["STORED","VIRTUAL"];$this->operators=["=","<",">","<=",">=","!=","LIKE","LIKE %%","NOT LIKE","IN","NOT IN","FIND_IN_SET","IS NULL","IS NOT NULL","REGEXP","NOT REGEXP","SQL",];$this->functions=["char_length","lower","upper","round","floor","ceil","date","from_unixtime","unix_timestamp","sec_to_time","time_to_sec",];$this->grouping=["sum","min","max","avg","count","count distinct","group_concat",];$this->partitionBy=["RANGE","LIST","HASH","LINEAR HASH","KEY","LINEAR KEY"];$this->insertFunctions=["char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",];$this->editFunctions=[number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",];if($d->isMinVersion($ng?"10.2":"5.7.8"))$this->types[lang(122)]["json"]=4294967295;if($ng&&$d->isMinVersion("10.7")){$this->types[lang(122)]["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if($ng&&$d->isMinVersion("10.5")){$this->types[lang(126)]["inet6"]=39;if($d->isMinVersion("10.10"))$this->types[lang(126)]["inet4"]=15;}if($d->isMinVersion($ng?"11.7":"9"))$this->types[lang(120)]["vector"]=16383;$this->systemDatabases=["mysql","information_schema","performance_schema","sys"];}function
insert($Q,array$Wi){return($Wi?parent::insert($Q,$Wi):queries("INSERT INTO ".table($Q)." ()\nVALUES ()"));}function
getUnconvertFunction(array$j){if(preg_match("~binary~",$j["type"]))return"<code class='jush-sql'>UNHEX</code>";elseif($j["type"]=="bit")return
doc_link(['sql'=>'bit-value-literals.html','mariadb'=>"reference/sql-structure/sql-language-structure/binary-literals"],"<code>b''</code>");elseif($j["type"]=="vector")return"<code class='jush-sql'>".($this->connection->isMariaDB()?"VEC_FromText":"STRING_TO_VECTOR")."</code>";elseif(preg_match("~geometry|point|linestring|polygon~",$j["type"]))return"<code class='jush-sql'>GeomFromText</code>";else
return"";}function
quoteBinary($xk){return"X".q(bin2hex($xk));}function
insertUpdate($Q,array$Xi,array$Fi){$c=array_keys(reset($Xi));$Ci="INSERT INTO ".table($Q)." (".implode(", ",$c).") VALUES\n";$fm=[];foreach($c
as$t)$fm[$t]="$t = VALUES($t)";$Ck="\nON DUPLICATE KEY UPDATE ".implode(", ",$fm);$fm=[];$u=0;foreach($Xi
as$Wi){$Y="(".implode(", ",$Wi).")";if($fm&&(strlen($Ci)+$u+strlen($Y)+strlen($Ck)>1e6)){if(!queries($Ci.implode(",\n",$fm).$Ck))return
false;$fm=[];$u=0;}$fm[]=$Y;$u+=strlen($Y)+2;}return
queries($Ci.implode(",\n",$fm).$Ck);}function
slowQuery($H,$ol){$ng=$this->connection->isMariaDB();if(!$this->connection->isMinVersion($ng?"10.1.2":"5.7.8"))return
null;if($ng)return"SET STATEMENT max_statement_time=$ol FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$y))return"$y[1] /*+ MAX_EXECUTION_TIME(".($ol*1000).") */ $y[2]";else
return
null;}function
convertSearch($Te,array$Z,array$j){return(preg_match('~char|text|enum|set~',$j["type"])&&!preg_match("~^utf8~",$j["collation"])&&preg_match('~[\x80-\xFF]~',$Z['val'])?"CONVERT($Te USING ".charset($this->connection).")":$Te);}function
warnings(){$I=$this->connection->query("SHOW WARNINGS");if($I&&$I->getRowsCount()){ob_start();print_select_result($I);return
ob_get_clean();}return
null;}function
tableHelp($A,$qf=false){$ng=$this->connection->isMariaDB();if(DB=="information_schema"){$A=strtolower($A);return$ng?"reference/system-tables/information-schema/information-schema-tables/".(str_starts_with($A,"innodb_")?"information-schema-innodb-tables/":"")."information-schema-$A-table":"information-schema-".str_replace("_","-",$A)."-table.html";}if(DB=="performance_schema")return$ng?"reference/system-tables/performance-schema/performance-schema-tables/performance-schema-$A-table":"performance-schema-".str_replace("_","-",$A)."-table.html";if(DB=="mysql")return$ng?"reference/system-tables/the-mysql-database-tables/mysql-$A".str_starts_with($A,"innodb_")?"":"-table":"system-schema.html";return
null;}function
getPartitionsInfo($Q){$je="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($Q);$I=Connection::get()->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $je ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1")->fetchRow();if(!$I)return[];$cf=["partition_by"=>$I[0],"partition"=>$I[1],"partitions"=>$I[2],];$ii=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $je AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$cf["partition_names"]=array_keys($ii);$cf["partition_values"]=array_values($ii);return$cf;}function
getIndexAlgorithms(array$Ok){return
preg_match('~^(MEMORY|NDB)$~',$Ok["Engine"])?["BTREE","HASH"]:["BTREE"];}function
hasCStyleEscapes(){static$fb;if($fb===null){$qk=$this->connection->getValue("SHOW VARIABLES LIKE 'sql_mode'",1);$fb=(strpos($qk,'NO_BACKSLASH_ESCAPES')===false);}return$fb;}function
engines(){$jd=[];foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$jd[]=$K["Engine"];}return$jd;}}function
create_driver(Connection$d){return
MySqlDriver::create($d,Admin::get());}function
idf_escape($Te){return"`".str_replace("`","``",$Te)."`";}function
table($Te){return
idf_escape($Te);}function
connect($Fi=false,&$i=null){$d=$Fi?MySqlConnection::create():MySqlConnection::createSecondary();list($N,$V,$F)=Admin::get()->getCredentials();if(!$d->openPasswordless($N,$V,$F,false)){$i=$d->getError();if(function_exists('iconv')&&!is_utf8($i)&&strlen($wj=iconv("windows-1250","utf-8",$i))>strlen($i))$i=$wj;return
null;}$d->setCharset(charset($d));$d->query("SET sql_quote_show_create = 1, autocommit = 1");if($Fi&&$d->isMariaDB()){Drivers::setName(DRIVER,"MariaDB");save_driver_name(DRIVER,$N,"MariaDB");}return$d;}function
get_databases($Xd){$f=get_session("dbs");if($f===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$f=($Xd?slow_query($H):get_vals($H));restart_session();set_session("dbs",$f);stop_session();}return$f;}function
limit($H,$Z,$v,$nh=0,$Rj=" "){return" $H$Z".($v?$Rj."LIMIT $v".($nh?" OFFSET $nh":""):"");}function
limit1($Q,$H,$Z,$Rj="\n"){return
limit($H,$Z,1,0,$Rj);}function
db_collation($g,$Ab){$J=null;$ac=Connection::get()->getValue("SHOW CREATE DATABASE ".idf_escape($g),1);if(preg_match('~ COLLATE ([^ ]+)~',$ac,$y))$J=$y[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$ac,$y))$J=$Ab[$y[1]][-1];return$J;}function
logged_user(){return
Connection::get()->getValue("SELECT USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables($f){$J=[];foreach($f
as$g)$J[$g]=count(get_vals("SHOW TABLES IN ".idf_escape($g)));return$J;}function
table_status($A="",$Fd=false){if($Fd)$H="SELECT TABLE_NAME AS Name, ENGINE AS Engine, CREATE_OPTIONS AS Create_options, TABLES.TABLE_COLLATION AS Collation, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($A!=""?"AND TABLE_NAME = ".q($A):"ORDER BY Name");else$H="SHOW TABLE STATUS".($A!=""?" LIKE ".q(addcslashes($A,"%_\\")):"");$S=[];foreach(get_rows($H)as$K){if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($A!="")$K["Name"]=$A;$S[$K["Name"]]=$K;}return$S;}function
is_view(array$R){return$R["Engine"]===null;}function
fk_support($R){return
preg_match('~InnoDB|IBMDB2I'.(Connection::get()->isMinVersion("5.6")?'|NDB':'').'~i',$R["Engine"]);}function
fields($Q){$ng=Connection::get()->isMariaDB();$J=[];foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($Q)." ORDER BY ORDINAL_POSITION")as$K){$j=$K["COLUMN_NAME"];$U=preg_replace('~\s?/\*.+\*/~U',"",$K["COLUMN_TYPE"]);$Bd=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$Bd,$ne);preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$U,$Gl);$h=$ng&&$K["COLUMN_DEFAULT"]=="NULL"?null:$K["COLUMN_DEFAULT"];if($h!==null){$uf=preg_match('~(text|json)~',$Gl[1]);if(!$ng&&$uf)$h=preg_replace("~^(_\w+)?('.*')$~",'\2',stripslashes($h));if($ng||$uf){$h=preg_replace_callback("~^'(.*)'$~",function($z){return
stripslashes(str_replace("''","'",$z[1]));},$h);}if(!$ng&&preg_match('~binary~',$Gl[1])&&preg_match('~^0x(\w*)$~',$h,$z))$h=pack("H*",$z[1]);}$pe=$K["GENERATION_EXPRESSION"];if(!$ng)$pe=preg_replace("~(^|,|\()(_\w+)?('.*')($|,|\))~",'\1\3\4',stripslashes($pe));$J[$j]=["field"=>$j,"full_type"=>$U,"type"=>$Gl[1],"length"=>$Gl[2],"unsigned"=>ltrim($Gl[3].$Gl[4]),"default"=>($ne?$pe:$h),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($Bd=="auto_increment"),"on_update"=>(preg_match('~\bon update (\w+)~i',$Bd,$Gl)?$Gl[1]:""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",",$K["PRIVILEGES"]))+["where"=>1,"order"=>1],"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($ne[1]=="PERSISTENT"?"STORED":$ne[1]),];}return$J;}function
indexes($Q,$d=null){$J=[];foreach(get_rows("SHOW INDEX FROM ".table($Q),$d)as$K){$A=$K["Key_name"];$J[$A]["type"]=($A=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$A]["columns"][]=$K["Column_name"];$J[$A]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$A]["descs"][]=null;$J[$A]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($Q){static$oi='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=[];$cc=Connection::get()->getValue("SHOW CREATE TABLE ".table($Q),1);if($cc){$xh=implode("|",Driver::get()->getOnActions());preg_match_all("~CONSTRAINT ($oi) FOREIGN KEY ?\\(((?:$oi,? ?)+)\\) REFERENCES ($oi)(?:\\.($oi))? \\(((?:$oi,? ?)+)\\)(?: ON DELETE ($xh))?(?: ON UPDATE ($xh))?~",$cc,$z,PREG_SET_ORDER);foreach($z
as$y){preg_match_all("~$oi~",$y[2],$lk);preg_match_all("~$oi~",$y[5],$dl);$J[idf_unescape($y[1])]=["db"=>idf_unescape($y[4]!=""?$y[3]:$y[4]),"table"=>idf_unescape($y[4]!=""?$y[4]:$y[3]),"source"=>array_map('AdminNeo\idf_unescape',$lk[0]),"target"=>array_map('AdminNeo\idf_unescape',$dl[0]),"on_delete"=>($y[6]?:"RESTRICT"),"on_update"=>($y[7]?:"RESTRICT"),];}}return$J;}function
backward_keys($Q){$H="SELECT constraint_name, table_schema, table_name, column_name, referenced_column_name
FROM information_schema.key_column_usage
WHERE table_schema = ".q(DB)."
AND referenced_table_schema = ".q(DB)."
AND referenced_table_name = ".q($Q)."
ORDER BY ordinal_position";return
get_rows($H,null,"");}function
view($A){$M=Connection::get()->getValue("SHOW CREATE VIEW ".table($A),1);$kg='(?:[^`\']|`[^`]*`|\'[^\']*\')*';$M=preg_replace("~^$kg\\s+AS\\s+~isU","",$M);return["select"=>format_sql($M)];}function
collations(){$J=[];$H=Connection::get()->isMariaDB()&&Connection::get()->isMinVersion("10.10")?"SELECT CHARACTER_SET_NAME AS Charset, FULL_COLLATION_NAME AS Collation, IS_DEFAULT AS `Default` FROM information_schema.COLLATION_CHARACTER_SET_APPLICABILITY":"SHOW COLLATION";foreach(get_rows($H)as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$t=>$X)sort($J[$t]);return$J;}function
information_schema($g){return($g=="information_schema")||(Connection::get()->isMinVersion("5.5")&&$g=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",Connection::get()->getError()));}function
create_database($g,$_b){return(bool)queries("CREATE DATABASE ".idf_escape($g).($_b?" COLLATE ".q($_b):""));}function
drop_databases($f){$J=apply_queries("DROP DATABASE",$f,'AdminNeo\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($A,$_b){$J=false;if(create_database($A,$_b)){$S=[];$mm=[];foreach(tables_list()as$Q=>$U){if($U=='VIEW')$mm[]=$Q;else$S[]=$Q;}$J=(!$S&&!$mm)||move_tables($S,$mm,$A);drop_databases($J?[DB]:[]);}return$J;}function
auto_increment(){$Na=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$r){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$r["columns"],true)){$Na="";break;}if($r["type"]=="PRIMARY")$Na=" UNIQUE";}}return" AUTO_INCREMENT$Na";}function
alter_table($Q,$A,$k,$Zd,$Ib,$id,$_b,$Ma,$hi){$Fa=[];foreach($k
as$j){if($j[1]){$h=$j[1][3];if(str_contains($h," GENERATED")){$j[1][3]=Connection::get()->isMariaDB()?"":$j[1][2];$j[1][2]=$h;}$Fa[]=($Q!=""?($j[0]!=""?"CHANGE ".idf_escape($j[0]):"ADD"):" ")." ".implode($j[1]).($Q!=""?$j[2]:"");}else$Fa[]="DROP ".idf_escape($j[0]);}$Fa=array_merge($Fa,$Zd);$uk=($Ib!==null?" COMMENT=".q($Ib):"").($id?" ENGINE=".q($id):"").($_b?" COLLATE ".q($_b):"").($Ma!=""?" AUTO_INCREMENT=$Ma":"");if($hi){$ii=[];if($hi["partition_by"]=='RANGE'||$hi["partition_by"]=='LIST'){foreach($hi["partition_names"]as$t=>$X){$Y=$hi["partition_values"][$t];$ii[]="\n  PARTITION ".idf_escape($X)." VALUES ".($hi["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$uk
.="\nPARTITION BY {$hi["partition_by"]}({$hi["partition"]})";if($ii)$uk
.=" (".implode(",",$ii)."\n)";elseif($hi["partitions"])$uk
.=" PARTITIONS ".(int)$hi["partitions"];}elseif($hi===null)$uk
.="\nREMOVE PARTITIONING";if($Q=="")return(bool)queries("CREATE TABLE ".table($A)." (\n".implode(",\n",$Fa)."\n)$uk");if($Q!=$A)$Fa[]="RENAME TO ".table($A);if($uk)$Fa[]=ltrim($uk);return!$Fa||queries("ALTER TABLE ".table($Q)."\n".implode(",\n",$Fa));}function
alter_indexes($Q,$Fa){$lb=[];foreach($Fa
as$t=>$X)$lb[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return(bool)queries("ALTER TABLE ".table($Q).implode(",",$lb));}function
truncate_tables($S){return
apply_queries("TRUNCATE TABLE",$S);}function
drop_views($mm){return(bool)queries("DROP VIEW ".implode(", ",array_map('AdminNeo\table',$mm)));}function
drop_tables($S){return(bool)queries("DROP TABLE ".implode(", ",array_map('AdminNeo\table',$S)));}function
move_tables($S,$mm,$dl){$ij=[];foreach($S
as$Q)$ij[]=table($Q)." TO ".idf_escape($dl).".".table($Q);if(!$ij||queries("RENAME TABLE ".implode(", ",$ij))){$wc=[];foreach($mm
as$Q)$wc[table($Q)]=view($Q);Connection::get()->selectDatabase($dl);$g=idf_escape(DB);foreach($wc
as$A=>$km){if(!queries("CREATE VIEW $A AS ".str_replace(" $g."," ",$km["select"]))||!queries("DROP VIEW $g.$A"))return
false;}return
true;}return
false;}function
copy_tables($S,$mm,$dl){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($S
as$Q){$A=($dl==DB?table("copy_$Q"):idf_escape($dl).".".table($Q));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $A"))||!queries("CREATE TABLE $A LIKE ".table($Q))||!queries("INSERT INTO $A SELECT * FROM ".table($Q)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($Q,"%_\\")))as$K){$Al=$K["Trigger"];if(!queries("CREATE TRIGGER ".($dl==DB?idf_escape("copy_$Al"):idf_escape($dl).".".idf_escape($Al))." $K[Timing] $K[Event] ON $A FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($mm
as$Q){$A=($dl==DB?table("copy_$Q"):idf_escape($dl).".".table($Q));$km=view($Q);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $A"))||!queries("CREATE VIEW $A AS $km[select]"))return
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
format_sql($H){$kg='(?:[^`\']|`[^`]*`|\'[^\']*\')*';$Ff='FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|(NATURAL\s+)?((LEFT|RIGHT)\s+)?((INNER|OUTER|CROSS)\s+)?JOIN';$H=preg_replace("~($kg)\\s+(AS\\s+SELECT)~isU","$1 AS\nSELECT",$H);$H=preg_replace("~($kg)\\s+($Ff)~isU","$1\n$2",$H);$H=preg_replace("~($kg),~isU","$1,\n  ",$H);return$H;}function
create_sql($Q,$Ma,$_k){$H=Connection::get()->getValue("SHOW CREATE TABLE ".table($Q),1);if(!$Ma)$H=preg_replace('~ AUTO_INCREMENT=\d+~','',$H);return!str_contains($H,"\n")?format_sql($H):$H;}function
truncate_sql($Q){return"TRUNCATE ".table($Q);}function
create_database_sql($mc,$_k=""){$A=idf_escape($mc);$Gb="";if(str_contains($_k,"CREATE")&&($ac=Connection::get()->getValue("SHOW CREATE DATABASE $A",1))){set_utf8mb4($ac);if($_k=="DROP+CREATE")$Gb="DROP DATABASE IF EXISTS $A;\n";$Gb
.="$ac;\n";}return$Gb;}function
use_sql($mc,$_k=""){return"USE ".idf_escape($mc).";\n";}function
trigger_sql($Q){$ok="";foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($Q,"%_\\")),null,"-- ")as$K)$ok
.="\nCREATE TRIGGER ".idf_escape($K["Trigger"])." $K[Timing] $K[Event] ON ".table($K["Table"])." FOR EACH ROW\n$K[Statement];;\n";return$ok;}function
show_variables(){return
get_rows("SHOW VARIABLES");}function
show_status(){return
get_rows("SHOW STATUS");}function
process_list(){return
get_rows("SHOW FULL PROCESSLIST");}function
convert_field(array$j){if(preg_match("~binary~",$j["type"]))return"HEX(".idf_escape($j["field"]).")";if($j["type"]=="bit")return"BIN(".idf_escape($j["field"])." + 0)";if($j["type"]=="vector")return(Connection::get()->isMariaDB()?"VEC_ToText":"VECTOR_TO_STRING")."(".idf_escape($j["field"]).")";if(preg_match("~geometry|point|linestring|polygon~",$j["type"]))return(Connection::get()->isMinVersion("8")?"ST_":"")."AsWKT(".idf_escape($j["field"]).")";return
null;}function
unconvert_field(array$j,$J){if(preg_match("~binary~",$j["type"]))$J="UNHEX($J)";if($j["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($j["type"]=="vector")$J=(Connection::get()->isMariaDB()?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geometry|point|linestring|polygon~",$j["type"])){$Ci=(Connection::get()->isMinVersion("8")?"ST_":"");$J=$Ci."GeomFromText($J, $Ci"."SRID($j[field]))";}return$J;}function
support($Gd){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(Connection::get()->isMinVersion("8")?'|descidx':'').(Connection::get()->isMinVersion(Connection::get()->isMariaDB()?"10.2.1":"8.0.16")?'|check':'').')$~',$Gd);}function
kill_process($X){return
queries("KILL ".number($X));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return(int)Connection::get()->getValue("SELECT @@max_connections");}}$wi="adminneo-plugins";if(is_dir($wi)){foreach(glob("$wi/*.php")as$m)include_once$m;}function
get_translations($Nf){switch($Nf){case'de':$Nb='-]^@qbP.!/f0mN&*Cks=qI6T1_z,)<sB[?]@P*HIt"isQ,T5~!$?*UOl[K8*;AvQBw]WOug0sn0;3r0;/dEg%CP+XbXy5b9mmx<x+O=De*@ZeL5TLfJn1!mc_cakJybUX=,aJT?fOoeQ
6;GbUI0!vj**bQZ/;xSnR?7CDbvGW=Vm`#gkkD5o_Nt1MXAsW;w78vxy5+UDpK;KHS/Rs~FiEU>zcKLf0H>zyZWqGzTVHEbiQ*l!.a6AuEU:]I<yUZMmk86s<$c1U0$yg[a3l]UTgNn`Yd?4sWj3_xX3t)
e?tUdx
yFMrS"iPZY2oIA:Z
>MBt8]jTVxvN]mYE5vHd!W__8jU7
>Y5N/&&5$k^*%"5B
O[egkU+FU0Z^+,)&JTVrBacg2[d57z$y.]=XhB].8`qVqW1[+`TQ_c7#8W*w@PcX1u1KI<^KqmUS(ru@l=.rhA(q@2.1MwpZiq=Sk7f-+fSQ>*-5^UcPuymi;nL-|dlqem_B,9gYfragSUM3^2+!cm{,^l&Yc[hp11n%yGP4}D8i-;cb[uPK%hiJ:qYCfeq$0.Mk<3jKoek5;ORA+CzgGg<XXtu5aSVL9!ElEkY^ji@B{S~cPReNJs?mI.}C[+!c."sHM3XCs)n;B]yp0NOG~,n4|bWw1-^^8v{8@Z7r4UrJX5h7+_=RP]/_+Q1w5wr_lawnZP0#v0!hHpYcJ={
{Rq3L[hCN4GuC1!>8m@^7V&y9%^jdv1
Z!p5!Ni1n9WGr^alBn#)LGI+cHz0z?F-Wp2,H^u31IcH:h1A+]vptCQG
g%<Bg(2JGuBX`KKOe
`O!.nQ&z=811x`;L)uIFT1/$+q4W#rK,3h27($AaX}u,LjhDq}#tRT,%B-R.IFuob.[j4<<Ew_SuItLAN%p{uX$DExu*H$*Bg+`~r`llz&s7wUVf[=/|$m$PZ#G;0R>ASg
`b-/X&g;|1"cY"3#Rf-J4^oI+uSW}.qG`g`"d08!]U?Od:OcLe.(sm1$(G,R:q09xKf9`PLmr@W2inrNKw~&Kjln;l#sB,)5(H1^8+9@%Gy*~:o^*p[=MG).bVY]>%rKsoYK6m!q8nfTuC6)+57lPeWu"QwS4QN6yd?WzR|W3Zc-:4Jw6%7=
^}DaVC#puRJy&*Js=yg|8-n#[,dN>kvYT{XkdHsYnUMqJ"/AJ~"|?/7S
+OCWBU7et&d@7b~!#.DoHF(.pQ2:OO:qb:FmV084Q"2v9JIBaTaH
t@!!F^c.:sZ%TZVWnbbY`^#XIepEW7r,Wz2!$!dx_Pn$J9YW<i;fjhh@CslY0jr3rl%[sv9ZW_Ae42W~LNQf2a^%JQU1I`qD3+a,3+@umaADW*[^F/EO#jp=K3Bk!Y-$jX>I]QBy)t,WM(s84EwdG#j<=,Dig
k37fO@n2oL6Iv?KTi@kV5gPtcF_sMP:rb7IthE3N($ecj@sGghk+MI%bP<5R^?g<wpALL.X"ctaS[qW3(D,LEV=y.t@M&1KA&6%KVAd,w`&@kL!bgFDJvX!zI&`2uL&Y15`LdZW+VZ$=a|2PF#2+:N3@[)
(Qsn@nBx[c(GL.j,r
IUX;HFhE2W-&.N^.&D{oj@9D/Y88BSMuGZVhh;28I%66FIfjdvYn5:?DE[&_HkQP!fQc]C(GA*lOJZ".9p-
H9G2cY(=pZ;V^R"%`vdQODH,{*^b:7q=ik$%g8P*AW|-o@C4<;3B0m;
Tdr$v@crRfp>N.+#{9W=@s.w~m2krI=:>v7fN5nN]!S.!BCSW-oFDs=O3,hE<`*v@`9*C?PxVTB1x.D
kaUveMIp^VKHqg-
h0)w[`Wi<Ei10dr^^B;Z#(Sm9S.gOW=!m`x^IoyQZ1Jt;y#*bv2h`&@#
H++rM?JNiR#08"Y#uc!6*6f#i:3C
)f%/kLd#Sw|sk;q>sMGm&)v!lN6aAu"^dcI$iGm_i1$6TsHgUX:Wr7c!b&b,i[rBo12NHIqpq:X
<4PNrP&nGAE5$$4-$u>h`m,F
`@72tD(ZeO1)cD*!DsxcStY=)M=QIZxtV-h:1";8-aY=&U`EkGv5)sFK+%od40#aF)<~VXokkq0y;Os@^L#=1Q>aQKQ50U9]fn)~
6;f0e%nYuvurB$>]PDz4-Dh%kdIoO#~JSt_r_6thMA`suc5Ct/RV^?[=xq$Tzj11C_T$T>#Bad&j0H9yuH#Rh>c
:@*Z::Q4,A|wTxI5,9z)wD}3T9aYpHUc4syU$T=BfV8w,6l4~5Mo[]yeUIcKU
u*<bOA%2^ZRE/v:AsaHl5Sv@t"Nw|r
.0CXxme{GI%Wmf<9x.!CcI_CQuhq2Gvu/B]Wp{4p;;L8wE0Xo@i<u3c+]R1Y.FTh<A"r$$MXU-y%xM7EjuD+]Gm)[BZda8_
A*NBrc@XN}`)6+-$wv`:r<-_"b"~
v]-/1
w7tYe>jrvA!UVtMYFO4fj=j"y6+rB(ZV*cphPv^T6Et.ySQj8uWyVk7``m1(?nq4r5M5Z(,E%iJ"vRkc)_`Kj2vEhPD=KxZv
J,R{-O4yav:")8`,AlK63XM;wsBgV(%#$_nBPyAt/-,x&gY=s)-xIkgQ@&B*KR@*saY5eIwHnxb2ps*/q-cL##"huo/SL=_8Mw9Qpf<-kxO[nh$[x[ybRuluts-M@_;G*
ay,B/!#O]fDy
qJo
xP*J7:#O,*p<bKVmO1@FMs]+t6.&GlI#rKWEXKi.P@!9)(!/sbY8FD{cL8|H_V9:QJB>T7I)CZ#1++E-}9n2tcg8c/|.U2}t6fp>Xh7v&%T&cfy)mMwT^Eoc{U|!Z"1/~NIFSsvqaiF`Um<l+BU8J!xfB#=^O2BM08,AY;7pO`N/YZUhh$,FJ
Hfv-ffbqVUJk]UWRdU:<X6f3(#+BLfuf4$4+p/[e}3hZiI0oRdk.R_VW9/Tf"&?bxL)TyRod#@##%X:PhA5]vi9.iEfUR2l&2C1?z_1u;QW@^aD0vHVCU3TtIc)imF7Qdn
<0O8F^C%-WEQ,#x!85sk6Y]#2lKe@pw!TQ-P:NC22>%p+gSGG1-0?=KLI!#Lsz8$sX")QsNQ[vd_Q=KaCU
4Ah$S"dO4m.^J59GjR2`pevqm*K3q-/sfv`*&,h.w94RD7|vW)S=FM+IKwG8hQ_QzWhy&2#[
Nhvc7bJoB)HJF^6*tf+b"y@Qna][7wHQS)nUc>Ed>8.Guh)T=/:tEv7d?x3YxNPjFEm;6ROZ]bTy8ln9<.#3qa<cCQOV>,-{UMD=U$.XnlTf
oHrJk:lhCU<.X_FK""k08n<rjl6Y>ss/JWEoVm("0d4qmBaB35AwhVe:,$AOiLB?pB3vp2d[)3LNQ@-;riD=%,7Ny:F-u0G<D9i"_r5uQlEMShu4Ytv<mu2c;Vyh3"sapV|rS!LCJe{Mj#w&BN"$Im.V.K/%$PU[Vf-:6e#4rGs,[kN/v(@qlZBHJ;5L{S-b#vejhVy;NVJJo$Rew(ZcWFG)%x8cS"zj!V@3rC["(_^_]g0m!P=CC+`"hz((uVQxA.(;.N-rhXknz]rVgo"3Ze<1D*LRpyW&q"7I/5Fh/A0d%p,i?,0>Exn6jD441>nZpTMF}.NQ_W03MQ~T^s$N`#AiJ"9DW7fs/P,%~Z^9R`iL,N;YM<L@zMQJm792f`G`zLvD1dfmII2Qb[BB2_:>)qf!lg%%in7qt,S3pJH^nWcN]v?ug:9$@1V5O^m<pdB*:NZ&^rbh7VDY~q_>#7w/f_jYb^^Rq]oWk!WK9@|N4v7fD/v$Px233%wS$:7Scxpyz$=J:MFPUInQR8?>2Ixxc>TS,"`**yW_qB%rQXI8F82nS[=>l-:@@gb:+":!(sX/`K~l}[
uK1:rm8PMTW{.Eo1.v35lX@v6R"9Na+bZ9#a,7LEioY(cQPA>!&Z+gO__xLmt%[[xC->O"##RBug&?W`-`+F^Fi,s`/S8j8WPrdM!c9JfNmTLmqzyugMV>;kdb5jAQpv=C5XDP.-aaVnCWsA!6nqAIN|N&ho#=!2WD_DC|GgNcLY^W+oJ|NE$$h#
HwDlZGgGZ&<*6;.*DoFrC)3LV=H+/aSZIMWT;*Axaq%@:u_)[A_bQ?!ri9oOWx>gd/_4W&*),ejVm@(wUgl*#4ReTV`7R:DmY=i.Lln,*pq1_o,ewz%i=rIi!87!LRAiNBpDTXH3lEcyAEVt%5eJ
S@
Q;k!vGf#=O?&b!W]E
8$KErw3"Q3m9wK[dUWeK&lYw11g13`T(a$9_Zl2]Gqds
,WIH;qpGNYZ%%fqU53myK<9$G4HA+=K|af4"qxvhmi-z`
5jL/1EjQJT4T>PiAhT/,`bL6-Ut|PHkS_B$vKdwijeUlQXR.nTkS"^Bhj:c_)w<UE=I<nFI%$$K:u>o+C#OEXpZbC}LB+^wGnpqrU<i^Oxc:o$*BAv^Dh.:i20r?Uv_l=b
AA
[U:_o=kKn?s`vbPq)%D+n%$&j+0*_%xe:9M@O_@"wpJw`w"0kz`7_8jsdCVd8pQo#i=RaH"WFDf8$1fN-*bC#]z!uBd(';break;case'en':$Nb='$X/,mbsmT,|]u":3Gv3<^GPF25pN_j8J"iEe~q0I%dgin>I)T;zg3aqIQy%G}wd=xr|$8p=m1=3D/3.a^x#6~o~AGv0%oy^#Yf9oJr+Wx[TrF8a,,W`L#
BB
6ZERM!Wo:Db[(fcJ0onTPhV(J+R^M8&UBT]a]@]=/>Q@8C>x
A`J*]
#FF%2JG6To"kvB$]L*O
|Y}vI1b`E
?K^+ki=S4FdYZV}53L!h&V)pCHrmja<BSt)`2P9xUCc1qm)N"#HM]y?K1GiLOv,k07KL<An0#l@uxB5?oguA.Vyj#_-Q
bJ`TB|LwrWeTY~f82Dt|^_iV$Ok#fOb9;gA.._V{K6PHfCm)HR7T#0(O__+7eVf~w$QbhIF$CFck/<%g9Ur70]?<x&(?yN]7[7qP<Z!)n;(FnO){xbix47D)Z/>[p6G>Z@F3UdORps+|45E_/KuZ&i,G?)U
HoOVnT9y
Jd{_/[a)Z4HLkXnVilV<`NE;@(e1IZ9A8G~,kID>
GoXfwm_7/=s$iSS,Y$.6aatLkSI{a6${_BQHmR`}0+VO@!;*<14{Z0XhXI4G"EjWGVBW>[&]!m!i7N^`%4L*
|
EG"mQqBw~F:
N)_6e991%V]^0WvHJ-h[e2eLW%iB~#Y32yV:"?buh(IuUw2rD.TP#Zcsh<<e[fPHssnN5G_A*!Fh$tK-qoz,Y%zXn8Z9`5~#@jyDnZ@8iv8p.n+.tl"s{<aL<a|1(@FWfZ%"89z[
=P;k+?J/1S#;W_b,e)ja">x~ObAb?T^aS/_J6yV@xFuq767Jqzcb5L%o8pjtZ&n[vQs{!9vXja=3]M1==_c+qubXI(Ecd:siW~=.yP,tlCYYp.^%7Y-)oXH+q)ZmPoQEtKz$LMmA4Zhc+Sdgg#&]2MFeUhRMF][#p$!gePR_::i=`
CGm<&?Z_o;=[WJgg0tJ5-&l|N
E{7T&
Iw^_=FuZ-K,7!%_u2D`?*Q@#AZW_L.EGyPvY7hA|cIP_>>[="`gLK(N{c3x04x[zLwNRO1;7Z)?VuecykS5N^Ru&+L,=CN8{,<Fe`L&/aA]fqC2HebI+&Ytetl2N@2?}&o>YwTm"&lYWTXBOQB=yz)tQ2An}sld!xrMryIaF"tT9,V&p"82#^v=9LV
GE}d]8`A[qns)r8!X@;$dIO-AkgxX9(!wx~p8-H_|,Ie`0@OB>WRdQ./"BW)#-;9IfLY*"IJc%qJ*G"Y/gAb[YBC6*cT9q+L<<z/o[J]7-s20`lN:>r8sb3Vr*EKSLr!W
F!h)PD4wRsG=JCh]*vo5Gx<dz58i*cDJsdf5e(d"RI?7K>K4"9yAtYt>_"eq1_n/Hw?%/OHyM#=#hiTL-"v%YqQD+=IFPxnN%.|Zw5hYL>2f^">e,3z1Bn{-)Ky:@?_aNB`P,Z|Z"$s,3Sj1#(t`pR(2U4;ob<4r"X]w3,2t,H>B?_[7gM!LtUz0$XhrbFYJC7F@~479[K:S$*a
3xJ<qQ_8+G%9l^ivO^3Jy=x?rLL`4P|eRL"@y?v:eh&Y,scrjPm`|>d4-p@HY0l<`:1hRJ<<c)VkzW@<GP>rf1b_6Iy7qm%O`oBTzm>-&9XCs&7KUB[p3a:[6<r.Jg-O2Xj1(t;l
l%_>gos3aWbH;Ej>vFcLI0xT8A(JmIo02Jsh6#OpjqCR(V@s*b:o2dee43ODjkkN<m9MJ"h`AB4:/#Bc?/+3B0D"B{e1
E4dI?^C]@Rb$NYewkNWaK&AUGG@*m!tG{o0
t)tB[W~I)3cYD
OKh$h+zl?2FDmes<;L0WKei;nU&gw`3-*(%jh4L!
R!@GZ-VS5PiU,0N+J-o[5dOFw+.K1m&AfX6@->dD"+UhE.QR[TdPNN?qAlo##(;&Lwrua
&N-5u-VNt7;LKx39gjCZV(d8ONGU3,3U9"Q07Q<+_*Cg/o+%b<*=7z5yoJ0MZF@0`.XASVS-s^G?c*],T!@OIZ$VjMnvu%1p$HcRG@$~c^wwj|=&.{3WD:,%1Q4a
%`IOW1T:s3_tN&$eK!Qh%_]!E")?lU9Cy*l@~D$rFrNIf5uE`lB(tXH$9*wP
.*b1Yd([pE<~SUNkZ0fwsDtBZc%[?F.Q5<K@a8r&uc*uRL
_4oMjf4Q2*Zd[cz"wcl#o5~,W.:h~_m0H=F+pJYsJvh9yZj,m:mi|%DB9#yYuRJXMS0_SC!1(o,k(]fgRU{nT>-n_e_.lHC*Pfe7?>]jU<hX3+6`n(s2:Cn_t5w#Yilv-!o;OD8vF<y)%Gme:g#^C+mJ`CE5lr|9o;uhYVussAL>dQMQ#^vpbpl5-@n]YZ[p`EQ*InwsebwuNRPML5aN07inhetuFX2T$8Bx.gCZ,p;m?Vwe?W{qxIJV
toO}Eutc:Q>lg:XmJR4c=V.q7D+xy8vcrs,~7w&1,2T4iu(uu5Xt(<dDe-G{1fgF1X_=iq<FfNH7N,x6!kUSJTK-Rv>c+TUoA61te4*O9;db3t^vH^%U-`n`loF!+Sxn*AoTgcbM9$=bXs%cdAG;f0g393#}#K<@I&^zp3`xTiW;A(2WEPP_i3
6;`s&W]`l*Poda8dj5y$%6&$Q@VY`>}pX4!#YHHyZ^3srEJ7xNZ4&Ro2.b8TMXUYMw2NEI{cYyzO)p"b#B@e^RxGZAXZfG*"@fCiNDTBaE6B_`b`ruGP-j`I?qV6eI4cW9(B20/B@tlRHo!O:gFOZsyl$_|q:X?2WS(?FG|RO;nR^mNSnm,=/8~$2"1>g^5oq:$oWr+>%A%0O0*v;+#8>`2Gy,LCx/V4hxz1W_79=R]pj("]lvT_nbz6yS12E-#@3_jEhv_]g7LjPA,V#!EG
?P4,HWT#99nopK#z%z!T2v(UXg-`eK^HGdni<U-a<e@iV#4YQN*&rq()$sd$AN1l`65xQN1vX](/p~0jD;ZV2{-/!)0~1C
nngg=[
s7r)r<73nj6YaI0q/IK!1h"[/wCCC40"j"Buf{du*Dwb%HBbAh-NbJ_W#MD(Svp+BcY>@P@lYtyp0ixSuUF
Fj6byI/RqMDv#(>hm1^CUJg}"pK.?%w
7>SS;Fqd,7t<"x1#<8qiL:v|J,FyN7I5<SegGm';break;case'es':$Nb='-`G@iaMp=,{0L82!ZYq!0W@"~H`QcZz"|Cn%51[25:94Q36XW59J|:xI87e-yX[%Po<[E#~7sfXXAH5Ha#N374TB<bY=*V)]]A.qUC3,CTi@
^|vvY=<G8.86U2qkL!$98I)hc@iQYgA:V*hO5Vcz;M$hXGa@(nFsDtsv
E0|@F)a]nQtGQ>b)=uO$|b6E(Tdw/tf4AsLP7UvE@dZH
1"Fp%r)kDzyunc[VQ/?dEQ=%p0
N0Ws3lxlx`j@,/`3;=37**[$2Q{2pLb^G[.M01nz"LN&+AlvJX;SPqhxcyvlcO7CP
W^-SDsq3/^+L*pic!Q$yQ:H@+S:m;x|^NV_rtBrYOCs]@)C?s0Q%#W,VmWFC{f(qIbXUD1BgCarKJRnhEnsMjv/.[l
Nh5!ya;l##O^1Ew+6|SS:vK-x!krUAqW$iE]]2G}U1
+ayA9NFHEI;]$51kUqK?ZuQgLC=HWS`UFaX7+Ix1KauZ<S=!m7Vm+gu/2?AdN>J]@@~Ay02(V2|Vi(yae.)vQq9/VK?_#J]E)n*^lSYie?0b[w!?7RcR+GZFTwe<qssnxu@;e7w!bdx$E&bPV]_9?,>]u>c1ws9-enI,(Om/EN"1,8H5D[w*cESZUMfIm5j)^`^HH"fL1i6y:U9k#WKD16^w5!-)nK|Nmq-Xtd2hkhz=x2(rJo:JGXCY8oW
-I_+-2IEG(>X!rA[OpfnuX
(:yRx[y+IU-aA=/x?Cf`Om`!YA(CL)aV5|R[ne6,YZ1{XQD(%@upOn
=GMuNQt?*O&dlBYlhS:E~6U<5Xb=sDqxqVTms@vii<*W9q}^4Rfu*:d0nhDj
7K*(&}k[*_VI,M&%`qUA2N5Ih8uWee9<^%l#5oeN;Okr7,uwxC@GhE<cLnRtG4WRfA52cz=bcGWO>8_]v<xJ!mg{_:tO5`0.J
yYlh:g
Us_a6<O(M[/ZY!*<Me[6b$RFl`ar-7xvk-=-9[MUVxiqAHM0kWAw7!B<7?2po*kX[Acc[&5S1y0p~M}#=Utqvvwmshn=CBiROmwLPK#"!xK@m:Y*Hv]j@2J&BQ(aEt*l@;Gv3(eK"PlTi4MQ$hh8kYe"6Df[d#uD.v%RFG
oHrRX)VA`Ts|kpO!#mlI`d76
TSP0}LW)W$VtPX@=QVeO7H-IM&(pXct6=h|hD][f~:K5D,;Mf(P<&>*mOjCtr.k.AS{KJO2q!CA-":.Jnm.xqRZQ!Ff@JjH`n.~SBZKM3*<M?6j]dD9/Nmot$j*.$,.]{*LyEgE7i@(XmB%)NwfnFZ
@2(xBN_/`^sMZyF;U]&4j
N-)SUKdTSo(j1X4d9L/9Q=_AD"+TH6ti@WHe^]sh`{+]ChFkYj8=O8?WIS>o6;39&t`PaAau:qLYtmUesM4~opamu~9osT$`pkBYfjv]w{MCU.S~fgvXb|S{[LR/wj]luswu?_I>#|5As
mpB{LGODr.DDx)4SNCM{%l15^pwYOY#nv.l^d%mtcl,2BL){J@V24d73L"]tLs6snRVHKQw?#o#c3Ldfn-AzF2ow_SUN#.CNNc:xFwi~o
Wo0pw+FNYWvt`!A9gC$WN=Z]Sh:$+BuSR/E-l8:k?(J?!uV6pUt;AR?@!XuzySJL].iI;IaaJ-g+uu*J9RLTj<l(-Iae84_^Op"nri=gr.THs)(NO:tlX^+R@6T8(3k@/^"##I#N6[j"T:9`q!wB*kCP:=Cpj=@`+"uc4#]7Q8U=81P![>^BN>;OvuG<I6"V.X-m$rSC.>F`=w@a:R(Qtn"*Lzs7ZMZZM2.x;g;^;3mrHuANO]L-MlCYYqch2<%ms16)D@5~]>jvfR<)K)9
fRV]t`6jL:e$3;%ei:Cs-q;PC-uWWh7?E1w)heh+OL#Jey9c,Te?40D`l6qr+DSkXnG)ReM/9BbUvtB;C-7CM|Kr+<3Xo2>_!PG|`w?zD5m
E8FfJ:dSTh#y#crKneSj.0Zn9:1d<YJKlm?}eOJzu3,><TZ3f$
[j+2R!m2tK6
d]F!@]hTM%&b0YlCKlFWjZ$DtMrFqwpqH"E;9a:_mtXvqg6630>&n1ipGke=h]8Irphp.lEFos:7y;,Q^N,$OXCs7tMt_Z0$wiPQ,YOGEGLd<7V$Ge44)1<KM+.L,T(oWdlr{DylFTNB(DzWl>#oi99^8D@Bx7p
0?Rfz%qbGPEglCBSeOvltA/?1^xekrb9)Z[;I0zBwBKGI$6ik0,GW[%29%!Wv2`s"*mkzQqO4wE:OHasn;3CIYk0P
a2>9";N@1yZ^9>bHi
3N_Oy4.bzwl,oT-rr8;UP(p>*0>i;h}xHt^Z33Wjcxl-act$JwybNeLW^wz]Bn2v4.`aY?}*AYG,nMB)sMc&I=[<7cF`[Eq!om~[e<_7XV:/x+,CK=s/FONbC,XEl3p-UYUn>g?1Fi
[A"`ey+P(ULH-!5b7LRWjboS[Wz&H3#g3^.4<.Jl<5MUVH(H!(G0(%aAjQ24<`]CY|,:qraNSZ^^jp2A]^h=MSvFXeRTkWc
SUBuo.QLg<pLB[/NLzBrC~y$#Ra~hVUMsO$JQJoSK,IK?0iO2P[t[Yx4@a.gr6iJ3I0SNsPs^*p{gqYbf#d8sdAH?WD".hduBs7G_Oso$~;sv3&ke(*Os(:COmZuI,X=NsGu]iVZ#Xc;1FU=7I6nkUK!j+R"uavM$}lPk$x>gd1Zp7G
u_q%*(iIssqIa6_JIwaE/V8R3?Nu?`cp@0G
B[nCpcbm-QuKv9W2@>Fb#M`R4)eUm$>rS-MphIF_4=(a5Bc3C+J}lkllR(l=&V_

HVPrTt!eE8o?eM)K/XYf?YQ8J(DP:0s!xEU9V83O._yVjX^=J;+7,?$-(YaQ=_ch]kmwBFr]hBF8y$
s)eM<KJvV>N~;93+/p<_FQ1VAGUl6`CgXH^qHN2r[
l:/UN+j,Pup>5@,(qf3SSK<h>QlA9W[*BT/BbhdBsPZCkf2{G3W^PXGLeYLCcwQ;Nu(P6M+_DRPi^vQVn
nR*K@^9L2#J}Y6_U.Y*A;m=[4X/rcqn;?OxEX5)pTAodiL,Y
EYv%|
pQ9o$G~Fn)5n>8Vn.t`mF*:8_>A5%l*bi+HMIZ/N26
L]S^VDeA%3_?nZnpL9N
_VmgWeNW1&m{io6)DpleaecT.p<hgxAv?xZFlr6uEC@"o6G)HAtHj*Y|Fs4?#@e#I@,4C}n`M>k_j5#GkQ)eVzZ#>6rA:oD5,QJPIh#<_4/^=p4=BV;flsO^>{pr#cRRq*;$SR-g8YAGwpvDa7WG#AG4?dUmmK){XpC{e?9TiZ7-bG9K:9n^k2#EKw4Z7/p:#A/yQv):x"[>@Vyz5~S.+#GgI/,qazI}1e=vV+Rd:`q"iS8hCPFu!8=F#7XGj=J^O!
Im7vQ:d)8eGWElWG6
pdU2&Ea@9H,cp$-8OL"%~Jewo_7q[@;[vr):i+apT@e(n4QC.hWB-c!crI^SGo
)F:p#rm+dSi5_700.Q4$[Rrx%[""97%t+}MOQM64.49P#<Vt#ppt>_$HsTJZSM*>J#M0KPQ2IsZ:v@GlxM&(_%&Wy`heQVA=EfLlpq`EymwYGMbO-xXGGgs`;-9
XTuFRLVe+:08Csd~08.GAStNi/JteW@]<Rct9HA7oxlqC|<OCD
T^?u6rgB:+;;1xb+_f|0oK`?Ya~p<$nXRbV?+-wMTX<Pi;MPYIVp%#:cFo0m/TKofBH"{juN*=l%qZ8tsNq=*-a]LVYgH9+Mc6H$#n>R[%iWpa!Sb8~3Ra@xv)`.,Y}=)9<q_fHogv.C<GV,LP>R*k>sv0h5;]g<A`
sltG]Km3vXQ?StSux]GwRpb.Pa9xDOn}g.;_GJk{BF$sXr::VVMXvi#Yo`.]a7AdNDk-g5[Y;wXTK^r~
_s@pD@9omEH=I!u"MAg&
Z;rLI=ay"Ep`0{A}b/?4w962#~u^=D!S/01h6-7#z"w&DO(wV}vqj;25BToF8bVMol$b^p(QEbu%Ur%j:m6(DRM`<T.C8F>gZ{v-lnnnk"etr*efl9Pc9u!<r~w>LTjg8c?t(JLncllIjDh0,i$Mn!]+AbR@,j8FO_K|^&bc)aDqtg!`Xj+r%f,[3/)FQW8<5:AF`b$T,k?i#EZ!bL-CM}/h+&
fxai4;_PM.saNVi?m-EL%1V[VhktmMPpb4
*rN/u$L;f?6(Bwm0Gn6Jmau/s=hw*2UaH.GmT8LH:LNmOS>qTSCG^,.YjUj|fz>B-2_MAUwz4VGP`7H?[:+SBjg5Q}mLh](:,Wn!k2(HNbZK_}BzwZPH(Mr`69s/7y,pu.8&[Au*G[Iq*0"jker[40
F1u^-WBtu=IUN2D^CtH3N[J(_!:SztoUreXRto&-B21D&D(9
-`K?Ti7^e,
,F/XLQM/"br>`fT63DNe6GRt5C;n9Ng]IXOX
A4FLH[D-tK=](|.o6]xfd(';break;case'ru':$Nb=')h_Gg6l.7,|@$peDI5Nt5Vy3lFi-E"G(p80!tpVIo5E6>T?E3EgNb.:^D0EW8AFC=.vAb!l"d*C_/t5r-tSn?/eNy5:"yim
ckD[ahRc>I7%d)dl-^3kWb9@%L.v=]QZ#65t)VXp[MiuJ_aMuH{dWfvyZRJSF?ju!t2C#4I(pQ.izy2(1iUA&0uKb.V$duw97X}+xq&,j6@fiYtfU8Kk-[0,O*[J3_$_j6J(
NEu_,hnY":LX629!Alsx+easlT7hQ>3mypk_5
ZO7PWa*V#BY,4!#.XdVSX
0OdxGA&A`2)&l,l!LJx_w3m4vYL?OY@`$CAp+5hEtguD/lHE4pkra5%05IB1yCbe)|dEctmr]_t#g~s2uwiE^IV7mYrM`QFi0~t?UP]-xBanxpA*qjl.uX7/cz+!q):R2L:]C[5SJISo78bKwg
3qpy0m]ocK$0#K^E_AAgH1~
5+cW$U
1cuLe0<8G*uc[d#7fgf:F%EX>#uxra
=3R):F>x6p<.5yQ!>3M.UU-`#xug&)4S@+-u88WEb0/s~wU4k!e4e90Gs]Z7?Xf"L5p2_q]o@;Z%qQsa%.,bpbG@?j80P:o^vZ/XfXbt43tEBN7!apY9iJ~aH,RG;5"YTbEwr
p01doJM+w%BR5]Z`+)rei=jk_G1g"[/
&e)EGihQ;t*6!XfLs
&Z$I4[GE93TxSkUNR]sAvqG3wB7hBpF
y<^yW"TR<VWi-BJ?CGo2chgu;Vj#{d
AN)Y99diu_FTy/e*VJs<X_19WD2|5m(<E^M4tud](>bkkS]eg@I,3nW8VFEfN<Q%tU5uMCZIHqbAbe+gHf?|LC$vUL@lf_>$h^H=FHZXW7*^D0X"f2(/31ZYoL"kS9lk^=
Rs+UMlBb5OQO
+#>S@&`#a4v]L>6MX??tqRyvx5_fbo5r*7s3_txYvBg^#_Xf+P3zCut2Zg#sN"QPYO+6#c_3_!7+j&X"qx5Be`RkWo.Y
lfW4>
UNUW=`#$BAldM-N,npuB#Je2$3
7=/6*9lVU9GNT8XF:.,U?(35St@kgC1vDbo_h]n]#%1e[G#;jukgCv7JgkPeNTktRFSjvfT$s0>`qUZ0F4A2MBZby%UTK=h>JWhP#dLO)
%|Ya<%Rfb;4s0V=-k9nx%"B=wT90<k;%#ZPW*#b.>o.^boL~laMq+.N3[e@c^^];^iTX1mT=/14PTwI>*6Bjvrx$Cv&2#*I4t4%mTFk7_Th+6JA,HyxVpBa"7{ND*p2*PhhniFv+9|IB;r],fsPyy!!"S_/XLZ%UxYP/2UmW#bXf7J<~?h,7k[JG[sdgSkq=:mxg7]0
AD!Ew9W0/^G8,giZvDYxAOr*XP#@D@^jx+"H225JL"R2B^-0goc!bF6%%}F/7PD#N|n78uRsPKwS,g$/#[^1."?YKM=(?oj<M=g6vBJ%9Y:{l
gLk.CU3F_
WK,LvDE`/+FXmUmbYfPt
F+2k|1r22aVj#?^DcZ@Wok`J:nI(HOQjkiSkFa"FX=hdSg19kPJ=T[C0u=AgTAqB#7pvg.4FvklhH3P!3q1M.H}O[.jF<J$+!Z|+b!,Hbo5T+q]0ZZjY:o!bMnEp6Mdwu$1e|)%;o"a3Pi|xIx{3KgNJ^D_o;mQs_rB2levJkVo-EJ?v+9l$J1rjJn7fV-S3~2(b~"@kS30(_!W^fd?pq,fk}=yii&ZJ/pcYGn?<bd77IB#xA]zROdxXrRp$GZ<2}NH2:,11.U,n,6LJr!g"mg1.vL&y@=y@y^bwWOY_D$NS+wNu#Zb.8DdiD0g>XQg;gT*G!-%i.9}![T*cgI"lU<(D~.5A_kCjaipooqmI/Zg7],:woE~,Hd30$U(.UtX@CO)^i3jir-(fp0kQjdMB{[u+sC;?*PC1jZFqE,lI<&_32vmMijlC]Akf4/6,1,]<2$6bCJ*oL#_rgaCQeKS)=sAFgZMnla+#+yuxNSEE9]JE!AASrQ=RNb2
4$,t|f[I?Uf)/<x=rh|#$VRXm#_6O`nk/T5Gz;57-f_:Yd
cPoN8t]76{k6Y<1dT;0Y+L-IJ6??7E%HN8FFTyQi5t
V3&O]+IJkDdDI%opuo3dAK+d[OK[zm,UbV
v"qGxnT-?}RV-<1#<wY_YqH.y!1+%m(UEA-:?YW&pnj:[OQ"8CtJR!bV_C,0l3qDlS0Y3`9CRfps]p9a*ot|Wu@rZPV`ocrj%#cNR)f;C5G-.`.n3MOQ=WhG(k->,DDI]Ldl"n48yP
gIqiGe}M<&l^w(>hFF5IjA^^in:S&,D.;UvZPsXrnu8+">./0HsWwA>itl
*B#g&Jgf5o(j^_h3:+TDmdl^<bygyCd2o-+Dbs^#QR>{guyI*L.n4B5{-)7R
gCK6PaXbVr7UU
9PHj8O[BSwc0Hx?(Igr(>&@?%rVYp=VH_+`&f*kLD23a^2JJ^ItV6a!CDde*%,a&e^g*]6JV)csBJ+[!BQ/;%4Z@I3k8Y9,a"mCO*!T[DsVNy>g4,BP-Y0!UzLL.(2yW=5@vDQ.1#S5/*tb<=V`0]^)!FCH"dN~BtZ~1pVLEc:{)TYa=X)+#^W0;n"~(WT/Q`4Y(72SRl[!S.0PyhR%#]45?}Z!eaWqjUF
QM#ERz(B5$4~m<aryUf,o%&9UTer!1TqqS?At`7EG"FJ@R3I6i
}Iy*VwN=&DV
0$Ase24eyZaU41^-R#-2,1/DCZwwUY=0")PG?.QI;_P-C1=GrN|,v._;a-mlDSx9vHz975AjGpc/iI:s9CH*[7@pSF^(/EB+1C|J>_-K#IJABkL1qyeEr`"cdbOu+6Lq{?6^~9AqZ]%2U"}:`]6x/]S@#9U(i/`D=HPCSw_kfQ(mK4Mweb`$)=c:5O<111Z#^DMted^,M)c#Q?0.$wL/OCLb]v|
q<jxEF2)/GWAsc/3J8L6;cS]Bh#@ADxg[I
t{?vquStw&JMw<r}4z!o<Ju:O~fKc-ymYw$Oe|!H>0O{bD<0O^fAvl#4T}A/9:!.=x;&v?Sc[y/#^X"GcHqEtvM,V@.E^]Whcx]n1F#&oWU};jJ-Lq.-UgE-Uh3SVu!|eV<,fuW6N`
o0h&O#o+5)b"cEwHKS}a}6m@SiN;sv*,r0DGIt.DVpp;)47LdDM7
VPsk4Ih~F
Hs$Z_}3#rB.;1C0o=`L61zfuT1k3WuYg9hiNYSN,IqRwM(T/&T$S[CXNJ|2Us=R,7Qap^.05U*1D_4"n[i["#sNwInd9:09,
j.bA@h`0KJ%9YdB?2Yi()7IP%t_;@"nrq3.#9@mM?Jv4KX7:[)1e!+?LCDVQ*]Bukk/^R:W^(4gCo_s4(o!)X;tsqRKA$Y`.:$Ak`Q_ia@I8jZ/?yIs5H9mfb>]2.(&#ilm8z-m78Yfs9;j!
rP;9U^it2s89NdO/@>yg%!V#PQ"
$=Geos<!NZ"</4BHD(u>.J@/Ms>=kq!_YF
$B5OdTxSjy%YSf
4!#HBL!T`X^oV_$=i<bJ(tjOoytJ*U<duC)&,W=*#_YG)P79h;cmnq&qv6Ji`M-22tOKiAm}JfCO%e168I1/wWqe3.%JBkAde
k0ur@.YUua%8_1ezJq%k&5GLB&Bzd4
:^8RCFGH4rf#P?:,zQc81[BpnGFcW)w3Qx<]eT]Hg]+oFjE3K-hdcKVShq.*TJ>sK;_[l,g;)C)lYDpoT/|]$.kGMUpW[%S<b,0HA7$DDqs3/I1N@RdDw0]@!*XrC0hgb2>^<Y>sG6N[~OxfFa1m%PqqGTIC&7S4$F}]#DUBcE7k-x|>R#YSi;*KBZOFvM=$^XT)q>nt^a[xv$FD@+77G)jA$#WUsU/L
%}G}R*F
R(G:>$!MAS_jF]?|gh_/k4)-q--^;J];")[aeSoIPr3AjDJ`J21$
>"B+KQh<`t8ln"8Fx)pZA+r42%"vP03kgf#i5(5ti/nRCh7LCEL`L<0^xF9G^/"QV(%0mK*FoEsplFO+!Z<&&0UrrS>9z`}6H-)F9C;g,.?s~8!`}=|>YLQPHxVRCu`SUcbknSw,&Pv1qk4j8^UW(e:%#&kWE2ai
Kbiiii2<u=@!)Dc2yW%?7"DTbc4Do*L=do(:v84YpE8.Ec>J-d(hW4uPuNS(CypjYKMHdhfY=ND?-8+hxHu7&VsM4L?!h8&DhJ/J)<EE5UJCkcg8bR:dgwnE9[p@CE6zK]L*k8kuf{+S/ywo@#bAR$n}G:lw@_(me9cznO_0B2vs6[THox>jhz=">N
rc6J<o6lf!6IS]vL4ru2:NWXM
@LVcUZSNuKKo>k>WB%#x}iZaUf3J|d.(4]I9O,&B$33Qlsz+":ra+^t;X?:1+q)ajNJ3+H",OI!goJnD4&UM5l6R!#9AQx"wp%!fL
!a-2RC<z(g`1VBzul.f6<xx=EiaCz+bgL&6)^I@,2>GUZDWS2_rm94L*"lkg>mLgv#S)LD|hv;H`,2FMN7QBXteWhNer(45"7pXt!LRU5?/kshFI>9/SNl(dSnFhZ28M@d..{1_K+m&QNcHQFWXnPqC[I;14c6SO7^^OTUa]5=vtPUKF(3WW4v=G+Debt:&lq@&!<.>fJ1]Yuc-ccGT3)5U4%y>E,Ko!8_mr`^bEGQ[641|?4pzYVG%MSLAl6KxEUI9w<1(pvN.9]#n"g)iq|YA&!ErR++}U*x~rKZihS]-b$1CO`l]LfUBn@aTXY*^H/S-j5R%i3i|=qT3ep(1(=I:;cFg%!N1Aq0y*|p]%d?5IUO.Xn.A%kgm$(;;4F5qmQJbM&>zfO]E6&S+>~%oWx<SM^YV&{"4Z=]~-q,syI8.?A*sOz4Jz#f;1_xYIhX%kYgfW/aj6Xk&,Pkq9X)f=CT(vRKQ"zSgZ{-?U+$!xyW!x_oPynE<U@lG+9ZAEy:MXX,s?./`)uIA1f[Jy=fuk+U~-Zk_/|6PwSgR]EAkh10Z@jyHi<l9rm*17i98jup1#iF.Bw8Jhi>/hc@(
z)CC(`zy1$iw#/?SH
g%.?}>a/]Btn>/E&=&c4,WWZ!ZcU)?!@r&{6u("iQ-(L9tt[l
#xB={q*]n"cA#vP$9
-tf#mF(nDLF1-
4.:x"U1eNuQmeoU
GYXyKUV`<+T8Tv+xQ>}8TyJwe4=M]r8a<y[BkSHRP1y[u1}>U?lw-rbuC1(Fy4)E.ek;*@0Lxml&V6~OCp.0/$8p*k5V2<>$bEm6Fm*n2Z9_mEr7bYtCYFHhmAhKi6P_&=vx?s#m!3Il4:qMAJ])%gmK;ZRh@FTo7oF;IHb*hqX?$n:n%1@]Gi#P[r`E<(2]N8=Ag#n!@`glOQ>;ANk;@m]`Rv|LHnb=UW.@(i45M5=D@56F=DxHL3>6I"q7C&[qTS<M8c~
#mCD!moWJNN5mvr7UAq-1Tv)hk
fFS9136t.-0+mchuE#1"<aPGjGz&&.';break;}return
json_decode(decompress_string($Nb),true);}function
get_plural_translation_id($t){$xi=array('Too many unsuccessful logins, try again in %d minute(s).'=>133,'%d process(es) have been killed.'=>271,'%d query(s) executed OK.'=>189,'Query executed OK, %d row(s) affected.'=>187,'%d row(s) have been imported.'=>278,'Routine has been called, %d row(s) affected.'=>222,'%d row(s)'=>186,'%d byte(s)'=>42,'%d item(s) have been affected.'=>275,);return
isset($xi[$t])?$xi[$t]:null;}$_l=$_SESSION["translations"];$Of=Locale::get()->getLanguage();if($_SESSION["translations_version"]!=2500100307){$_l=[];$_SESSION["translations_version"]=2500100307;}if($_SESSION["translations_language"]!=$Of){$_l=[];$_SESSION["translations_language"]=$Of;}if(!$_l){$_l=get_translations($Of);$_SESSION["translations"]=$_l;}Locale::get()->setTranslations($_l);$xa=null;$hc=false;$kf=null;if(function_exists('\adminneo_instance')){$xa=\adminneo_instance();$hc=true;}elseif(file_exists("adminneo-instance.php")){$xa=include_once"adminneo-instance.php";$hc=true;}if($hc&&!$xa
instanceof
Admin&&!$xa
instanceof
Pluginer){$xa=null;$dg="href=https://github.com/adminneo-org/adminneo#advanced-customizations ".target_blank();$kf=lang(127,"<b>adminneo-instance.php</b>","<b>adminneo_instance()</b>","Admin::create()")." <a $dg>".lang(1)."</a>";}if(!$xa)$xa=Admin::create();if($kf)$xa->addError($kf);if($Bi!==null&&!isset($_GET["settings"])){$xa->getSettings()->updateParameter("lang",$Bi);redirect(remove_from_uri());}if(!defined("AdminNeo\DRIVER")){define("AdminNeo\DRIVER",null);define("AdminNeo\DIALECT",null);}define("AdminNeo\SERVER",DRIVER?$_GET[DRIVER]:null);define("AdminNeo\DB",isset($_GET["db"])?$_GET["db"]:"");define("AdminNeo\BASE_URL",preg_replace('~\?.*~','',relative_uri()));define("AdminNeo\ME",BASE_URL.'?'.(sid()?session_name()."=".urlencode(session_id()).'&':'').(SERVER!==null?DRIVER."=".urlencode(SERVER).'&':'').($_GET["ext"]?"ext=".urlencode($_GET["ext"]).'&':'').(isset($_GET["username"])?"username=".urlencode($_GET["username"]).'&':'').(DB!=""?'db='.urlencode(DB).'&'.(isset($_GET["ns"])?"ns=".urlencode($_GET["ns"])."&":""):''));define("AdminNeo\HOME_URL",BASE_URL?:".");define("AdminNeo\SERVER_HOME_URL",substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1)?:".");if(isset($_GET["set"])){header("Content-Type: text/javascript; charset=utf-8");if(!verify_token()){header("HTTP/1.1 403 Forbidden");exit;}if($_GET["set"]=="navigation-width")save_navigation_width(isset($_POST["width"])?$_POST["width"]:"");exit;}function
save_navigation_width($ym){if($ym==""){Admin::get()->getSettings()->updateParameter("navigationWidth",null);return;}$ym=min(max((float)$ym,Settings::$NavigationWidthMin),Settings::$NavigationWidthMax);Admin::get()->getSettings()->updateParameter("navigationWidth",sprintf("%.2F",$ym));}const
VERSION="5.6.0";function
page_header($T,$bb=[]){ini_set("zlib.output_compression","1");page_headers();if(is_ajax()&&Admin::get()->getErrors()){page_messages();exit;}if(!ob_get_level())ob_start(null,4096);$T=strip_tags($T);$Yj=$bb!==false&&$bb!==null&&SERVER!=""?" - ".h(Admin::get()->getServerName(SERVER)):"";$ak=strip_tags(Admin::get()->getServiceTitle());$rl=$T.$Yj." - ".($ak!=""?$ak:"AdminNeo");echo'<!DOCTYPE html>
<html lang="',Locale::get()->getLanguage(),'" dir="',lang(128),'">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="robots" content="noindex, nofollow">
	<meta name="viewport" content="width=device-width, initial-scale=1"/>

	<title>',$rl,'</title>

	';$Cb=validate_color_variant(Admin::get()->getConfig()->getColorVariant());echo"<link rel='stylesheet' href='",link_files("default-$Cb.css",[]),"'>\n";if(!Admin::get()->isLightModeForced())echo"<link rel='stylesheet' ".(!Admin::get()->isDarkModeForced()?"media='(prefers-color-scheme: dark)' ":"")."href='",link_files("default-$Cb-dark.css",[]),"'>\n";$kl=Admin::get()->getConfig()->getTheme();list($kl,$Cb)=validate_theme($kl,$Cb);if($kl!="default"){echo"<link rel='stylesheet' href='",link_files("$kl-$Cb.css",[]),"'>\n";if(!Admin::get()->isLightModeForced())echo"<link rel='stylesheet' ".(!Admin::get()->isDarkModeForced()?"media='(prefers-color-scheme: dark)' ":"")."href='",link_files("$kl-$Cb-dark.css",[]),"'>\n";}foreach(Admin::get()->getCssUrls()as$Tl){if(strpos($Tl,"adminneo-dark.css")===0&&!Admin::get()->isDarkModeForced())echo"<link rel='stylesheet' media='(prefers-color-scheme: dark)' href='",h($Tl),"'>\n";else
echo"<link rel='stylesheet' href='",h($Tl),"'>\n";}$Yg=Admin::get()->getSettings()->getNavigationWidth();echo"<style id='navigation-width'>";if($Yg)echo"@media screen and (min-width: 1024px) { :root { --menu-width: ",sprintf("%.2F",$Yg),"rem } }";echo"</style>\n",script_src(link_files("main.js",[]));foreach(Admin::get()->getJsUrls()as$Tl)echo
script_src($Tl);Admin::get()->printFavicons();Admin::get()->printToHead();echo'</head>
<body class="',lang(128),' nojs">
<script',nonce(),'>
	const body = document.body;

	body.onkeydown = bodyKeydown;
	body.onclick = bodyClick;
	body.classList.replace("nojs", "js");

	const offlineMessage = \'',js_escape(lang(129)),'\';
	const thousandsSeparator = \'',js_escape(lang(104)),'\';
</script>


',"<div id='help' class='jush-".DIALECT." jsonly hidden'></div>",script("initHelpPopup();"),"<div id='content'>\n","<div class='header'>\n";if($bb!==null){echo'<nav class="breadcrumbs"><ul>','<li><a href="'.h(HOME_URL).'" title="',lang(130),'">',icon_solo("home"),'</a></li>';$Wj=h(Admin::get()->getServerName(SERVER??""));if($bb===false)echo"<li>$Wj</li>";else{$w=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);echo"<li><a href='".h($w)."' accesskey='1' title='Alt+Shift+1'>$Wj</a></li>";if($_GET["ns"]!=""||(DB!=""&&is_array($bb)))echo'<li><a href="'.h($w."&db=".urlencode(DB).(support("scheme")?"&ns=":"")).'">'.h(DB).'</a></li>';if($bb===true){if($_GET["ns"]!="")echo'<li>'.h($_GET["ns"]).'</li>';else
echo"<li>",h(DB),"</li>";}else{if($_GET["ns"]!="")echo'<li><a href="'.h(substr(ME,0,-1)).'">'.h($_GET["ns"]).'</a></li>';foreach($bb
as$t=>$X){if(is_string($t)){$zc=(is_array($X)?$X[1]:h($X));if($zc!="")echo"<li><a href='".h(ME."$t=").urlencode(is_array($X)?$X[0]:$X)."'>$zc</a></li>";}else
echo"<li>$X</li>\n";}}}echo"</ul></nav>";}echo"</div>\n","<h1>$T</h1>\n","<div id='ajaxstatus' class='jsonly hidden'></div>\n";restart_session();page_messages();$f=&get_session("dbs");if(DB!=""&&$f&&!in_array(DB,$f,true))$f=null;stop_session();define("AdminNeo\PAGE_HEADER",1);}function
validate_color_variant($Cb){list(,$Cb)=validate_theme("default",$Cb);return$Cb;}function
validate_theme($kl,$Cb){$ll=get_available_themes();if(!isset($ll[$kl]))$kl="default";if(!isset($ll[$kl][$Cb])){reset($ll[$kl]);$Cb=key($ll[$kl]);}return[$kl,$Cb];}function
get_available_themes(){return
array('default'=>array('red'=>true,),);}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");header("X-Frame-Options: DENY");$ec=["script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://api.github.com/repos/adminneo-org/adminneo/releases/latest","frame-src"=>"'self'","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",];Admin::get()->updateCspHeader($ec);$Dc=[];foreach($ec
as$Cc=>$mk)$Dc[]="$Cc $mk";header("Content-Security-Policy: ".implode("; ",$Dc));Admin::get()->sendHeaders();}function
get_nonce(){static$hh;if(!$hh)$hh=Random::strongKey();return$hh;}function
page_messages(){$Sl=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$Eg=isset($_SESSION["messages"][$Sl])?$_SESSION["messages"][$Sl]:null;if($Eg){foreach($Eg
as$_)echo"<div class='message'>$_</div>\n",script("initToggles(qsl('.message'));");unset($_SESSION["messages"][$Sl]);}foreach(Admin::get()->getErrors()as$i)echo"<div class='error'>$i</div>\n";}function
page_footer($Kg=null){echo"</div>\n","<button id='navigation-button' class='button light navigation-button'>",icon_solo("menu"),icon_solo("close"),"</button>","<div id='navigation-panel' class='navigation-panel'>\n";Admin::get()->printNavigation($Kg);echo"<div class='footer'>\n","<div class='toolbox'>";if($Kg=="auth")language_select();else{$w=h(preg_replace('~\b(db|ns)=[^&]*&~',"",ME)."settings=");echo"<a class='button light' title='",lang(131),"' href='$w'>",icon_solo("settings"),"</a>";}echo"</div>";if($Kg!="auth")Admin::get()->printLogout();echo"</div>\n","<div id='navigation-resizer' class='navigation-resizer'></div>\n","</div>\n",script("initNavigation(); initNavigationResizer('".js_escape(ME)."set=navigation-width', '".get_token()."', ".Settings::$NavigationWidthMin.", ".Settings::$NavigationWidthMax.");");}function
int32($Ug){while($Ug>=2147483648)$Ug-=4294967296;while($Ug<=-2147483649)$Ug+=4294967296;return(int)$Ug;}function
long2str(array$W,$rm){$wj='';foreach($W
as$X)$wj
.=pack('V',$X);return$rm?substr($wj,0,end($W)):$wj;}function
str2long($wj,$rm){$W=array_values(unpack('V*',str_pad($wj,4*ceil(strlen($wj)/4),"\0")));if($rm)$W[]=strlen($wj);return$W;}function
xxtea_mx($Bm,$Am,$Dk,$_f){return
int32((($Bm>>5&0x7FFFFFF)^$Am<<2)+(($Am>>3&0x1FFFFFFF)^$Bm<<4))^int32(($Dk^$Am)+($_f^$Bm));}function
xxtea_encrypt_string($ti,$t){$t=array_values(unpack("V*",pack("H*",md5($t))));$W=str2long($ti,true);$Ug=count($W)-1;$Bm=$W[$Ug];$Am=$W[0];$Oi=floor(6+52/($Ug+1));$Dk=0;while($Oi-->0){$Dk=int32($Dk+0x9E3779B9);$Wc=$Dk>>2&3;for($Wh=0;$Wh<$Ug;$Wh++){$Am=$W[$Wh+1];$Sg=xxtea_mx($Bm,$Am,$Dk,$t[$Wh&3^$Wc]);$Bm=int32($W[$Wh]+$Sg);$W[$Wh]=$Bm;}$Am=$W[0];$Sg=xxtea_mx($Bm,$Am,$Dk,$t[$Wh&3^$Wc]);$Bm=int32($W[$Ug]+$Sg);$W[$Ug]=$Bm;}return
long2str($W,false);}function
xxtea_decrypt_string($e,$t){$t=array_values(unpack("V*",pack("H*",md5($t))));$W=str2long($e,false);$Ug=count($W)-1;$Bm=$W[$Ug];$Am=$W[0];$Oi=floor(6+52/($Ug+1));$Dk=int32($Oi*0x9E3779B9);while($Dk){$Wc=$Dk>>2&3;for($Wh=$Ug;$Wh>0;$Wh--){$Bm=$W[$Wh-1];$Sg=xxtea_mx($Bm,$Am,$Dk,$t[$Wh&3^$Wc]);$Am=int32($W[$Wh]-$Sg);$W[$Wh]=$Am;}$Bm=$W[$Ug];$Sg=xxtea_mx($Bm,$Am,$Dk,$t[$Wh&3^$Wc]);$Am=int32($W[0]-$Sg);$W[0]=$Am;$Dk=int32($Dk-0x9E3779B9);}return
long2str($W,true);}const
ENCRYPTION_GCM='aes-256-gcm';const
ENCRYPTION_CBC='aes-256-cbc';const
ENCRYPTION_TAG_LENGTH=16;const
ENCRYPTION_HMAC_LENGTH=64;function
generate_iv($u){if(function_exists('random_bytes')){try{return
random_bytes($u);}catch(Exception$Wc){}}return
openssl_random_pseudo_bytes($u);}function
hash_key($t){return
substr(hash('sha512',$t,true),0,32);}function
aes_encrypt_string($ti,$t){$Ig=PHP_VERSION_ID>=70100&&in_array(ENCRYPTION_GCM,openssl_get_cipher_methods())?ENCRYPTION_GCM:ENCRYPTION_CBC;$t=hash_key($t);$wf=generate_iv(openssl_cipher_iv_length($Ig)?:16);if($Ig==ENCRYPTION_GCM)$vb=openssl_encrypt($ti,$Ig,$t,OPENSSL_RAW_DATA,$wf,$bl,"",ENCRYPTION_TAG_LENGTH);else{$vb=openssl_encrypt($ti,$Ig,$t,OPENSSL_RAW_DATA,$wf);$bl=hash_hmac("sha512",$wf.$vb,$t,true);}if($vb===false)return
false;return$wf.$bl.$vb;}function
aes_decrypt_string($e,$t){$Ig=PHP_VERSION_ID>=70100&&in_array(ENCRYPTION_GCM,openssl_get_cipher_methods())?ENCRYPTION_GCM:ENCRYPTION_CBC;$xf=openssl_cipher_iv_length($Ig)?:16;$cl=$Ig==ENCRYPTION_GCM?ENCRYPTION_TAG_LENGTH:ENCRYPTION_HMAC_LENGTH;if(strlen($e)<$xf+$cl)return
false;$t=hash_key($t);$wf=substr($e,0,$xf);$bl=substr($e,$xf,$cl);$vb=substr($e,$xf+$cl);if($wf===false||$bl===false||$vb===false)return
false;if($Ig==ENCRYPTION_GCM)return
openssl_decrypt($vb,$Ig,$t,OPENSSL_RAW_DATA,$wf,$bl);else{$Ke=hash_hmac('sha512',$wf.$vb,$t,true);if(!hash_equals($bl,$Ke))return
false;return
openssl_decrypt($vb,$Ig,$t,OPENSSL_RAW_DATA,$wf);}}function
encrypt_string($ti,$t){if($ti=="")return"";if(extension_loaded('openssl'))return
aes_encrypt_string($ti,$t);else
return
xxtea_encrypt_string($ti,$t);}function
decrypt_string($e,$t){if($e=="")return"";if(extension_loaded('openssl'))return
aes_decrypt_string($e,$t);else
return
xxtea_decrypt_string($e,$t);}$qi=[];if($_COOKIE["neo_permanent"]){foreach(explode(" ",$_COOKIE["neo_permanent"])as$X){list($t)=explode(":",$X);$qi[$t]=$X;}}function
validate_server_input(array&$qi){$N=preg_replace('~:/[-\w.][-\w.:/]*$~D',"",SERVER);if($N=="")return;if(!preg_match('~^[^:]+://~',$N))$N="https://$N";$ki=parse_url($N);if(!$ki)auth_error($qi);if(isset($ki['user'])||isset($ki['pass'])||isset($ki['query'])||isset($ki['fragment']))auth_error($qi);if(isset($ki['scheme'])&&!preg_match('~^(https?)$~i',$ki['scheme']))auth_error($qi);$Ne=$ki['host'].(isset($ki['path'])?$ki['path']:'');if(!is_server_host_valid($Ne))auth_error($qi);if(isset($ki['port'])&&($ki['port']<1024||$ki['port']>65535))auth_error($qi,lang(132));}if(!function_exists('AdminNeo\is_server_host_valid')){function
is_server_host_valid($Ne){return
strpos($Ne,'/')===false;}}function
build_http_url($N,$V,$F,$tc,$sc=null){if(!preg_match('~^(https?://)?([^:]*)(:\d+)?$~',rtrim($N,'/'),$z))return
null;return($z[1]?:"http://").($V!==""||$F!==""?urlencode($V).":".urlencode($F)."@":"").($z[2]!==""?$z[2]:$tc).(isset($z[3])?$z[3]:($sc?":$sc":""));}function
add_invalid_login(){$Va=get_temp_dir()."/adminneo-invalid";$l=null;foreach(glob("$Va*")?:[$Va]as$m){$l=open_file_with_lock($m);if($l)break;}if(!$l){$l=open_file_with_lock("$Va-".Random::strongKey());if(!$l)return;}$nf=json_decode(stream_get_contents($l),true);$nl=time();if($nf){foreach($nf
as$of=>$X){if($X[0]<$nl)unset($nf[$of]);}}$mf=&$nf[Admin::get()->getBruteForceKey()];if(!$mf)$mf=[$nl+30*60,0];$mf[1]++;write_and_unlock_file($l,json_encode($nf));}function
check_invalid_login(array&$qi){$Va=get_temp_dir()."/adminneo-invalid";$nf=[];foreach(glob("$Va*")as$m){$l=open_file_with_lock($m);if($l){$nf=json_decode(stream_get_contents($l),true);unlock_file($l);break;}}$mf=($nf?$nf[Admin::get()->getBruteForceKey()]:[]);$fh=($mf&&$mf[1]>29?$mf[0]-time():0);if($fh>0)auth_error($qi,lang(133,ceil($fh/60)));}function
connect_to_db(array&$qi){if(Admin::get()->getConfig()->hasServers()&&!Admin::get()->getConfig()->getServer(SERVER))auth_error($qi);$d=connect(true,$i);if(!$d)connection_error(nl2br(h($i)),$qi);return$d;}function
authenticate(array&$qi){$I=Admin::get()->authenticate($_GET["username"],get_password());if($I!==true)connection_error($I,$qi);}function
connection_error($i,array&$qi){$i=$i?:lang(3);if(preg_match('~^ +| +$~',get_password()))$i
.="<br>".lang(134);auth_error($qi,$i);}Admin::get()->init();$La=isset($_POST["auth"])?$_POST["auth"]:null;if($La){session_regenerate_id();$N=isset($La["server"])?$La["server"]:"";$Xj=Admin::get()->getConfig()->getServer($N);$Oc=$Xj?$Xj->getDriver():(isset($La["driver"])?$La["driver"]:"");$N=$Xj?$N:trim($N);$V=isset($La["username"])?$La["username"]:"";$F=isset($La["password"])?$La["password"]:"";if($Xj&&$Xj->hasCredentials()&&$V==""&&$F==""){$V=$Xj->getUsername();$F=$Xj->getPassword();}$g=$Xj?$Xj->getDatabase():(isset($La["db"])?$La["db"]:"");save_login($Oc,$N,$V,$F,$g);if($La["permanent"]){$t=implode("-",array_map("base64_encode",[$Oc,$N,$V,$g]));$Hi=Admin::get()->getPrivateKey(true);$hd=$Hi?encrypt_string($F,$Hi):false;$qi[$t]="$t:".base64_encode($hd?:"");cookie("neo_permanent",implode(" ",$qi));}if(count($_POST)==1||DRIVER!=$Oc||SERVER!=$N||$_GET["username"]!==$V||DB!=$g)redirect(auth_url($Oc,$N,$V,$g));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){foreach(["pwds","db","dbs","queries"]as$t)set_session($t,null);unset_permanent($qi);redirect(SERVER_HOME_URL,lang(135));}elseif($qi&&!$_SESSION["pwds"]){session_regenerate_id();$Hi=Admin::get()->getPrivateKey();foreach($qi
as$t=>$X){list(,$ub)=explode(":",$X);list($Oc,$N,$V,$g)=array_map("base64_decode",explode("-",$t));$F=$Hi?decrypt_string(base64_decode($ub),$Hi):false;save_login($Oc,$N,$V,$F,$g);}}function
unset_permanent(array&$qi){foreach($qi
as$t=>$X){list($Oc,$N,$V,$g)=array_map("base64_decode",explode("-",$t));if($Oc==DRIVER&&$N==SERVER&&$V==$_GET["username"]&&$g==DB)unset($qi[$t]);}cookie("neo_permanent",implode(" ",$qi));}function
auth_error(array&$qi,$i=null){$bk=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$bk]||$_GET[$bk])&&!$_SESSION["token"])$i=lang(136);else{restart_session();add_invalid_login();$F=get_password();if($F!==null){if($F===false)$i=lang(137);delete_login(DRIVER,SERVER,$_GET["username"]);}unset_permanent($qi);}}if(!$_COOKIE[$bk]&&$_GET[$bk]&&ini_bool("session.use_only_cookies"))$i=lang(138);if(!$i)$i=lang(3);Admin::get()->addError($i);print_login_page();}function
print_login_page(){$Zh=session_get_cookie_params();cookie("neo_key",($_COOKIE["neo_key"]?:Random::strongKey()),$Zh["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header(lang(31),null);echo"<form action='' method='post'>\n","<div>";if(print_hidden_fields($_POST,["auth"]))echo"<p class='message'>".lang(139)."\n";echo"</div>\n";Admin::get()->printLoginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!DRIVER)print_login_page();if(isset($_GET["username"])&&!defined('AdminNeo\DRIVER_EXTENSION')){Admin::get()->addError(lang(140,implode(", ",Drivers::getExtensions(DRIVER))));unset($_SESSION["pwds"][DRIVER]);unset_permanent($qi);page_header(lang(141),false);page_footer("auth");exit;}if(!isset($_GET["username"])||get_password()===null)print_login_page();validate_server_input($qi);check_invalid_login($qi);Admin::get()->getConfig()->applyServer(SERVER);$d=connect_to_db($qi);authenticate($qi);create_driver($d);if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){Admin::get()->addError(lang(142));page_header(lang(6));page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($La&&$_POST["token"])$_POST["token"]=get_token();if($_POST){if(!verify_token()){$ef="max_input_vars";$xg=ini_get($ef);if(extension_loaded("suhosin")){foreach(["suhosin.request.max_vars","suhosin.post.max_vars"]as$t){$X=ini_get($t);if($X&&(!$xg||$X<$xg)){$ef=$t;$xg=$X;}}}if(!$_POST["token"]&&$xg)Admin::get()->addError(lang(143,"'$ef'"));else
Admin::get()->addError(lang(142).' '.lang(144));$_POST=[];}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){$i=lang(145,"'post_max_size'");if(isset($_GET["sql"]))$i
.=' '.lang(146);Admin::get()->addError($i);}if(isset($_GET["settings"])){$P=Admin::get()->getSettings();$dk=array_merge(Admin::get()->getSettingsRows(1),Admin::get()->getSettingsRows(2),Admin::get()->getSettingsRows(3));if($_POST){$Zh=[];foreach($dk
as$t=>$K){if(isset($_POST[$t])){$Vl=$_POST[$t]===""||(is_array($_POST[$t])&&in_array("",$_POST[$t]));$Zh[$t]=(!$Vl?$_POST[$t]:null);}}$P->updateParameters($Zh);redirect(remove_from_uri());}$T=lang(131);page_header($T,[$T]);echo"<form id='settings' action='' method='post'>\n","<table class='box'>\n";foreach($dk
as$K)echo$K;echo"</table>\n","<p>","<input type='submit' value='".lang(112),"' class='button default hidden'>",input_token(),"</p>\n","</form>\n",script("initSettingsForm();");page_footer();exit;}if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];if(!(DB!=""?Connection::get()->selectDatabase(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!=""){Admin::get()->addError(lang(147));header("HTTP/1.1 404 Not Found");page_header(lang(30).": ".h(DB),true);}else{if($_POST["db"])queries_redirect(substr(ME,0,-1),lang(148),drop_databases($_POST["db"]));$T=h(Drivers::get(DRIVER).": ".Admin::get()->getServerName(SERVER));page_header($T,false);$eg=['privileges'=>[lang(72),"users"],'processlist'=>[lang(149),"list"],'variables'=>[lang(150),"variable"],'status'=>[lang(151),"status"],];$fg="";foreach($eg
as$t=>$X){if(support($t))$fg
.="<a href='".h(ME)."$t='>".icon($X[1])."$X[0]</a>";}if($fg)echo"<p class='links top-links'>$fg</p>\n";echo"<p>".lang(152,Drivers::get(DRIVER),"<b>".h(Connection::get()->getVersion())."</b>","<b>".DRIVER_EXTENSION."</b>")."\n","<p>".lang(153,"<b>".h(logged_user())."</b>")."\n";$f=Admin::get()->getDatabases();if($f){$Fj=support("scheme");$Ca=collations();echo"<form action='' method='post'>\n","<div class='table-footer-parent'>\n","<div class='scrollable'>\n","<table class='checkable'>\n","<thead><tr>".(support("database")?"<td>":"")."<th>".lang(30).(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".lang(154)."</a>":"")."<td>".lang(45)."<td>".lang(155)."<td>".lang(156)." - <a href='".h(ME)."dbsize=1'>".lang(157)."</a>".script("qsl('a').onclick = partial(ajaxSetHtml, '".js_escape(ME)."script=connect');","")."</thead>\n","<tbody>\n";$f=($_GET["dbsize"]?count_tables($f):array_flip($f));foreach($f
as$g=>$S){$rj=h(ME)."db=".urlencode($g);$q=h("Db-".$g);echo"<tr>".(support("database")?"<td class='actions'>".checkbox("db[]",$g,in_array($g,(array)$_POST["db"]),"","","",$q):""),"<th><a href='$rj' id='$q'>".h($g)."</a>";$_b=h(db_collation($g,$Ca));echo"<td>".(support("database")?"<a href='$rj".($Fj?"&amp;ns=":"")."&amp;database=' title='".lang(69)."'>$_b</a>":$_b),"<td align='right'><a href='$rj&amp;schema=' id='tables-".h($g)."' title='".lang(71)."'>".($_GET["dbsize"]?$S:"?")."</a>","<td align='right' id='size-".h($g)."'>".($_GET["dbsize"]?db_size($g):"?"),"\n";}echo"</tbody>\n",script("mixin(qsl('tbody'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});"),"</table>\n","</div>\n";if(support("database"))echo"<div class='table-footer'><div class='field-sets'>\n","<fieldset><legend>",lang(158)," <span id='selected'></span></legend><div class='fieldset-content'>\n",input_hidden("all"),script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^db/)); };"),"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(),"\n","</div></fieldset>\n","</div></div>\n",script("initTableFooter()");echo"</div>\n",input_token(),"</form>\n",script("tableCheck();");}}echo'<p class="links"><a href="'.h(ME).'database=">'.icon("database-add").lang(75)."</a>\n";page_footer("db");exit;}if(isset($_GET["select"])&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$k=fields($a);header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$_GET["where"])).".".friendly_url($_GET["field"]));$M=[idf_escape($_GET["field"])];$I=Driver::get()->select($a,$M,[where($_GET,$k)],$M);$K=($I?$I->fetchRow():[]);echo
Connection::get()->formatValue($K[0],$k[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$k=fields($a);if(!$k)Admin::get()->addError(error()?:lang(78));$R=table_status1($a,true);$A=Admin::get()->getTableName($R);$qj=[];foreach($k
as$t=>$j)$qj+=$j["privileges"];$T=$k&&is_view($R)?$R['Engine']=='materialized view'?lang(160):lang(161):lang(8);$Rk=$A!=""?$A:h($a);page_header("$T: $Rk",[$Rk]);$O=null;if(isset($qj["insert"])||!support("table"))$O="";Admin::get()->printTableMenu($R,$O);$cf=[];if(!preg_match("~sqlite|mssql|pgsql~",DIALECT)&&isset($R["Engine"]))$cf[]=lang(162).": ".h($R["Engine"]);if(isset($R["Collation"]))$cf[]=lang(45).": ".h($R["Collation"]);if($cf)echo"<p>",implode(", ",$cf),"</p>";if($k)Admin::get()->printTableStructure($k);$Ib=$R["Comment"];if($Ib!="")echo"<p class='keep-lines'>",lang(46),": ",Admin::get()->formatComment($Ib),"</p>\n";if(!is_view($R))$Yc='<p class="links"><a href="'.h(ME).'create='.urlencode($a).'">'.icon("edit").lang(35)."</a>\n";elseif(support("view"))$Yc='<p class="links"><a href="'.h(ME).'view='.urlencode($a).'">'.icon("edit").lang(36)."</a>\n";else$Yc="";if($cf||$k||$Ib!="")echo$Yc;$ai=Driver::get()->getParentTables($a);if($ai){echo"<h2>".lang(163)."</h2>\n";Admin::get()->printRelatedTables($ai);}if(Driver::get()->getPartitionBy()&&str_contains(isset($R["Create_options"])?$R["Create_options"]:"","partitioned")){$ji=Driver::get()->getPartitionsInfo($a);if($ji){echo"<h2 id='partitions'>".lang(49)."</h2>\n";Admin::get()->printTablePartitions($ji);if(DIALECT!="pgsql")echo$Yc;}}$df=Driver::get()->getInheritedTables($a);if($df){echo"<h2 id='inherited-by'>".lang(164)."</h2>\n";Admin::get()->printRelatedTables($df);}if(support("indexes")&&Driver::get()->supportsIndex($R)){echo"<h2 id='indexes'>".lang(165)."</h2>\n";$s=indexes($a);if($s)Admin::get()->printTableIndexes($s,$R);echo'<p class="links"><a href="'.h(ME).'indexes='.urlencode($a).'">'.icon("edit").lang(166)."</a>\n";}if(!is_view($R)){if(fk_support($R)){echo"<h2 id='foreign-keys'>".lang(89)."</h2>\n";$be=foreign_keys($a);if($be){echo"<table>\n","<thead><tr><th>".lang(167)."<td>".lang(168)."<td>".lang(92)."<td>".lang(91)."<td></thead>\n";foreach($be
as$A=>$n)echo"<tr title='".h($A)."'>","<th><i>".implode("</i>, <i>",array_map('AdminNeo\h',$n["source"]))."</i>","<td><a href='".h($n["db"]!=""?preg_replace('~db=[^&]*~',"db=".urlencode($n["db"]),ME):($n["ns"]!=""?preg_replace('~ns=[^&]*~',"ns=".urlencode($n["ns"]),ME):ME))."table=".urlencode($n["table"])."'>".($n["db"]!=""&&$n["db"]!=DB?"<b>".h($n["db"])."</b>.":"").($n["ns"]!=""&&$n["ns"]!=$_GET["ns"]?"<b>".h($n["ns"])."</b>.":"").h($n["table"])."</a>","(<i>".implode("</i>, <i>",array_map('AdminNeo\h',$n["target"]))."</i>)","<td>".h($n["on_delete"]),"<td>".h($n["on_update"]),'<td><a href="'.h(ME.'foreign='.urlencode($a).'&name='.urlencode($A)).'">'.lang(169).'</a>',"\n";echo"</table>\n";}echo'<p class="links"><a href="'.h(ME).'foreign='.urlencode($a).'">'.icon("add").lang(170)."</a>\n";}if(support("check")){echo"<h2 id='checks'>".lang(171)."</h2>\n";$pb=Driver::get()->checkConstraints($a);if($pb){echo"<table cellspacing='0'>\n";foreach($pb
as$t=>$X)echo"<tr title='".h($t)."'>","<td><code class='jush-".DIALECT."'>".h($X),"<td><a href='".h(ME.'check='.urlencode($a).'&name='.urlencode($t))."'>".lang(169)."</a>","\n";echo"</table>\n";}echo'<p class="links"><a href="'.h(ME).'check='.urlencode($a).'">'.icon("add").lang(172)."</a>\n";}}if(support(is_view($R)?"view_trigger":"trigger")){echo"<h2 id='triggers'>".lang(173)."</h2>\n";$Cl=triggers($a);if($Cl){echo"<table>\n";foreach($Cl
as$t=>$X)echo"<tr><td>".h($X[0])."<td>".h($X[1])."<th>".h($t)."<td><a href='".h(ME.'trigger='.urlencode($a).'&name='.urlencode($t))."'>".lang(169)."</a>\n";echo"</table>\n";}echo'<p class="links"><a href="'.h(ME).'trigger='.urlencode($a).'">'.icon("add").lang(174)."</a>\n";}}elseif(isset($_GET["schema"])){$ql=h(": ".DB.($_GET["ns"]?".$_GET[ns]":""));page_header(lang(71).$ql,[lang(71)]);$Tk=[];$Uk=[];$Kd=[];$oa=($_GET["schema"]?:$_COOKIE["neo_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$oa,$z,PREG_SET_ORDER);foreach($z
as$p=>$y){$Tk[$y[1]]=[(float)$y[2],(float)$y[3]];$Uk[]="\n\t'".js_escape($y[1])."': [ $y[2], $y[3] ]";}$vl=0;$Ua=-1;$Dj=[];$dj=[];$Uf=[];$Da=Driver::get()->getAllFields();foreach(table_status('',true)as$Q=>$R){if(is_view($R))continue;$G=0;$Dj[$Q]["fields"]=[];foreach(isset($Da[$Q])?$Da[$Q]:[]as$j){$G+=1.25;$Kd[$Q][$j["field"]]=$G;$Dj[$Q]["fields"][$j["field"]]=$j;}$Dj[$Q]["pos"]=(isset($Tk[$Q])?$Tk[$Q]:[$vl,0]);foreach(Admin::get()->getForeignKeys($Q)as$X){if(!$X["db"]){$Sf=$Ua;if((isset($Tk[$Q][1])?$Tk[$Q][1]:0)||(isset($Tk[$X["table"]][1])?$Tk[$X["table"]][1]:0))$Sf=min(floatval(isset($Tk[$Q][1])?$Tk[$Q][1]:0),floatval(isset($Tk[$X["table"]][1])?$Tk[$X["table"]][1]:0))-1;else$Ua-=.1;while($Uf[(string)$Sf])$Sf-=.0001;$Dj[$Q]["references"][$X["table"]][(string)$Sf]=[$X["source"],$X["target"]];$dj[$X["table"]][$Q][(string)$Sf]=$X["target"];$Uf[(string)$Sf]=true;}}$vl=max($vl,$Dj[$Q]["pos"][0]+2.5+$G);}echo"<div id='schema' style='height: {$vl}em;'>\n","<script",nonce(),">\n","gid('schema').onselectstart = () => false;\n","const tablePos = {",implode(",",$Uk),"\n};\n","const em = gid('schema').offsetHeight / $vl;\n","document.onmousemove = schemaMousemove;\n","document.onmouseup = partialArg(schemaMouseup, '",js_escape(DB),"');\n","</script>\n";foreach($Dj
as$A=>$Q){echo"<div class='table' style='top: ".$Q["pos"][0]."em; left: ".$Q["pos"][1]."em;'>",'<a href="'.h(ME).'table='.urlencode($A).'"><b>'.h($A)."</b></a>",script("qsl('div').onmousedown = schemaMousedown;");foreach($Q["fields"]as$j){$X='<span '.type_class($j["type"]).' title="'.h($j["type"].($j["length"]?"($j[length])":"").($j["null"]?" NULL":'')).'">'.h($j["field"]).'</span>';echo"<br>".($j["primary"]?"<i>$X</i>":$X);}foreach((array)$Q["references"]as$el=>$fj){foreach($fj
as$Sf=>$Zi){$Tf=$Sf-(isset($Tk[$A][1])?$Tk[$A][1]:0);$p=0;foreach($Zi[0]as$lk){echo"\n<div class='references' title='",h($el),"' id='refs$Sf-$p' style='left: {$Tf}em; top: ",$Kd[$A][$lk],"em; padding-top: .5em;'>","<div style='border-top: 1px solid Gray; width: ".(-$Tf)."em;'></div>","</div>";$p++;}}}foreach((array)$dj[$A]as$el=>$fj){foreach($fj
as$Sf=>$c){$Tf=$Sf-(isset($Tk[$A][1])?$Tk[$A][1]:0);$p=0;foreach($c
as$dl){echo"\n<div class='references' title='",h($el),"' id='refd$Sf-$p' style='left: {$Tf}em; top: ".$Kd[$A][$dl]."em; height: 1.25em;'>","<svg style='width: 1em; height: 1em; float: right;' viewBox='0 0 22 22' fill='currentColor'><path d='M11,19l10,-8l-10,-8l0,16Z'/></svg>","<div style='height: .5em; border-bottom: 1px solid Gray; width: ".(-$Tf)."em;'></div>","</div>";$p++;}}}echo"\n</div>\n";}foreach($Dj
as$A=>$Q){foreach((array)$Q["references"]as$el=>$fj){if($Dj[$el]){foreach($fj
as$Sf=>$Zi){$Jg=$vl;$ug=-10;foreach($Zi[0]as$t=>$lk){$zi=$Q["pos"][0]+$Kd[$A][$lk];$_i=$Dj[$el]["pos"][0]+$Kd[$el][$Zi[1][$t]];$Jg=min($Jg,$zi,$_i);$ug=max($ug,$zi,$_i);}echo"<div class='references' id='refl$Sf' style='left: $Sf"."em; top: $Jg"."em; padding: .5em 0;'><div style='border-right: 1px solid Gray; margin-top: 1px; height: ".($ug-$Jg)."em;'></div></div>\n";}}}}echo"</div>\n","<p class='links'>","<a href='",(ME."schema=".urlencode($oa)),"' id='schema-link'>",lang(175),"</a>","</p>\n";}elseif(isset($_GET["dump"])){$a=$_GET["dump"];$P=Admin::get()->getSettings();if($_POST){$P->updateParameters(["dumpFormat"=>$_POST["format"],"dumpDbStyle"=>$_POST["db_style"],"dumpTypes"=>isset($_POST["types"])?$_POST["types"]:(support("type")?"":null),"dumpRoutines"=>isset($_POST["routines"])?$_POST["routines"]:(support("routine")?"":null),"dumpEvents"=>isset($_POST["events"])?$_POST["events"]:(support("event")?"":null),"dumpTableStyle"=>$_POST["table_style"],"dumpAutoIncrement"=>isset($_POST["auto_increment"])?$_POST["auto_increment"]:"","dumpTriggers"=>isset($_POST["triggers"])?$_POST["triggers"]:(support("trigger")?"":null),"dumpDataStyle"=>$_POST["data_style"],"dumpOutput"=>$_POST["output"],]);if(DB!="")$f=[DB];else{$f=isset($_POST["databases"])?$_POST["databases"]:[];if(is_string($f))$f=explode("\n",rtrim(str_replace("\r","",$f),"\n"));}$Ej=isset($_POST["schemas"])?$_POST["schemas"]:[];$S=array_flip(isset($_POST["tables"])?$_POST["tables"]:[])+array_flip(isset($_POST["data"])?$_POST["data"]:[]);if(count($S)==1)$Re=key($S);elseif(count($Ej)==1)$Re=$Ej[0];elseif(count($f)==1)$Re=$f[0];else$Re=Admin::get()->getServerName(SERVER,true,"server");$zd=dump_headers($Re,DB==""||$_GET["ns"]===""||count($S)>1);$tf=preg_match('~sql~',$_POST["format"]);$kc=$tf&&$_POST["data_style"]&&!$_POST["table_style"]&&DIALECT!="sql";if($tf){echo"-- AdminNeo ".VERSION." ".Drivers::get(DRIVER)." ".Connection::get()->getVersion()." dump\n\n";if(DIALECT=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";Connection::get()->query("SET time_zone = '+00:00'");Connection::get()->query("SET sql_mode = ''");}}$_k=$_POST["db_style"];foreach($f
as$g){Admin::get()->dumpDatabase($g);if(Connection::get()->selectDatabase($g)){if($tf){if($_k)echo
create_database_sql($g,$_k),use_sql($g,$_k)."\n";$Th="";if($_POST["types"]){foreach(types()as$q=>$U){$ld=type_values($q);if($ld)$Th
.=($_k!='DROP+CREATE'?"DROP TYPE IF EXISTS ".idf_escape($U).";;\n":"")."CREATE TYPE ".idf_escape($U)." AS ENUM ($ld);\n\n";else$Th
.="-- Could not export type $U\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$A=$K["ROUTINE_NAME"];$sj=$K["ROUTINE_TYPE"];$ac=create_routine($sj,["name"=>$A]+routine($K["SPECIFIC_NAME"],$sj));set_utf8mb4($ac);$Th
.=($_k!='DROP+CREATE'?"DROP $sj IF EXISTS ".idf_escape($A).";;\n":"")."$ac;\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$ac=remove_definer(Connection::get()->getValue("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($ac);$Th
.=($_k!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$ac;;\n\n";}}echo($Th&&DIALECT=='sql'?"DELIMITER ;;\n\n$Th"."DELIMITER ;\n\n":$Th);}if($_POST["table_style"]||$_POST["data_style"]){foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?[""]:Admin::get()->getSchemas(true)))as$Dj){if($Dj!="")set_schema($Dj);$Zk=table_status('',true);$Sk=array_keys($Zk);$Ec=false;if($kc&&$Sk){$ej=[];foreach($Sk
as$A){if(!is_view($Zk[$A])&&(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["data"]))){foreach(foreign_keys($A)as$n)$ej[$A][]=$n["table"];}}$Jh=dump_table_order($Sk,$ej);if($Jh)$Sk=$Jh;else$Ec=function_exists('AdminNeo\foreign_key_checks_sql');}if($Ec)echo
foreign_key_checks_sql(false)."\n";$mm=[];foreach($Sk
as$A){$R=$Zk[$A];$Q=(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["tables"]));$e=(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["data"]));if($Q||$e){$sl=null;if($zd=="tar"){$sl=new
TmpFile();ob_start([$sl,'write'],1e5);}$bc=($Q?$_POST["table_style"]:"");Admin::get()->dumpTable($A,$bc,(is_view($R)?2:0));if(is_view($R)&&$zd!="tar")$mm[]=$A;elseif($e){$k=fields($A);Admin::get()->dumpData($A,$_POST["data_style"],"SELECT *".convert_fields($k,$k)." FROM ".table($A));if($tf&&!$bc&&$_POST["auto_increment"]&&function_exists('AdminNeo\restart_sequences_sql'))echo"\n".restart_sequences_sql($A);}if($tf&&$_POST["triggers"]&&$Q&&($Cl=trigger_sql($A)))echo"\nDELIMITER ;;\n$Cl\nDELIMITER ;\n";if($zd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$g/")."$A.csv",$sl);}elseif($tf)echo"\n";}}if($Ec)echo
foreign_key_checks_sql(true)."\n";if($_POST["table_style"]&&function_exists('AdminNeo\foreign_keys_sql')){foreach($Zk
as$A=>$R){$Q=(DB==""||$_GET["ns"]===""||in_array($A,(array)$_POST["tables"]));if($Q&&!is_view($R))echo
foreign_keys_sql($A);}}foreach($mm
as$km)Admin::get()->dumpTable($km,$_POST["table_style"],1);if($zd=="tar")echo
pack("x512");}}}}if($tf)echo"-- ".gmdate("Y-m-d H:i:s e")."\n";exit;}$A=DB!=""?h(DB):h(Admin::get()->getServerName(SERVER));page_header(lang(74).": $A",($_GET["export"]!=""?["table"=>$_GET["export"]]:[lang(74)]));echo"<form action='' method='post'>\n","<table class='box'>\n";$oc=['','USE','DROP+CREATE','CREATE'];$Wk=['','DROP+CREATE','CREATE'];$lc=['','TRUNCATE+INSERT','INSERT'];if(DIALECT=="sql")$lc[]='INSERT+UPDATE';echo"<tr><th>",lang(176),"</th><td>",html_radios("format",Admin::get()->getDumpFormats(),$P->getParameter("dumpFormat","sql")),"</td></tr>\n";if(DIALECT!="sqlite"){echo"<tr><th id='label-db'>",lang(30),"</th>","<td>",html_select('db_style',$oc,$P->getParameter("dumpDbStyle",DB==""?"CREATE":""),"","label-db"),"<span class='labels'>";if(support("routine"))echo
checkbox("routines",1,$P->getParameter("dumpRoutines",$_GET["dump"]==""?"1":""),lang(177));if(support("event"))echo
checkbox("events",1,$P->getParameter("dumpEvents",$_GET["dump"]==""?"1":""),lang(178));echo"</span></td></tr>";}echo"<tr><th id='label-tables'>",lang(155),"</th><td>",html_select('table_style',$Wk,$P->getParameter("dumpTableStyle","DROP+CREATE"),"","label-tables")," <span class='labels'>",checkbox("auto_increment",1,$P->getParameter("dumpAutoIncrement"),lang(47));if(support("trigger"))echo
checkbox("triggers",1,$P->getParameter("dumpTriggers","1"),lang(173));echo"</span></td></tr>","<tr><th id='label-data'>",lang(179),"</th><td>",html_select("data_style",$lc,$P->getParameter("dumpDataStyle","INSERT"),"","label-data"),"</td></tr>","<tr><th>",lang(180),"</th><td>",html_radios("output",Admin::get()->getDumpOutputs(),$P->getParameter("dumpOutput","file")),"</td></tr>\n","</table>\n","<p>","<input type='submit' class='button default' value='",lang(74),"'>",input_token(),"</p>\n","<table>\n",script("qsl('table').onclick = dumpClick;");$Di=[];if(DB!=""&&$_GET["ns"]===""){echo"<thead><tr><th>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly'>".lang(181)."</label>".script("gid('check-schemas').onclick = partial(formCheck, /^schemas\\[/);",""),"</thead>\n";foreach(Admin::get()->getSchemas()as$Dj)echo"<tr><td>".checkbox("schemas[]",$Dj,true,$Dj,"","block")."\n";}elseif(DB!=""){$rb=($a!=""?"":" checked");echo"<thead><tr>","<th><label class='block'><input type='checkbox' id='check-tables'$rb class='jsonly'>".lang(8)."</label>".script("gid('check-tables').onclick = partial(formCheck, /^tables\\[/);",""),"<th class='right'><label class='block'>".lang(179)."<input type='checkbox' id='check-data'$rb class='jsonly'></label>".script("gid('check-data').onclick = partial(formCheck, /^data\\[/);",""),"</thead>\n";$mm="";$Yk=tables_list();foreach($Yk
as$A=>$U){$Ci=preg_replace('~_.*~','',$A);$rb=($a==""||$a==(substr($a,-1)=="%"?"$Ci%":$A));$Gi="<tr><td>".checkbox("tables[]",$A,$rb,$A,"","block");if($U!==null&&!preg_match('~table~i',$U))$mm
.="$Gi\n";else
echo"$Gi<td class='right'><label class='block'><span id='Rows-".h($A)."'></span>".checkbox("data[]",$A,$rb)."</label>\n";$Di[$Ci]++;}echo$mm;if($Yk)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$f=Admin::get()->getDatabases();echo"<thead><tr><th>","<label class='block'>".($f?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly'>".script("gid('check-databases').onclick = partial(formCheck, /^databases\\[/);",""):"").lang(30)."</label>","</thead>\n";if($f){foreach($f
as$g){if(!information_schema($g)){$Ci=preg_replace('~_.*~','',$g);echo"<tr><td>".checkbox("databases[]",$g,$a==""||$a=="$Ci%",$g,"","block")."\n";$Di[$Ci]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo"</table>\n","</form>\n";$eg=[];foreach($Di
as$t=>$X){if($t!=""&&$X>1)$eg[]="<a href='".h(ME)."dump=".urlencode("$t%")."'>".icon("check").h($t)."*</a>";}if($eg)echo"<p class='links'>",implode("",$eg),"</p>\n";}elseif(isset($_GET["privileges"])){$ql=DB!=""?h(": ".DB):"";page_header(lang(72).$ql,[lang(72)]);echo'<p class="links top-links"><a href="',h(ME),'user=">',icon("user-add"),lang(182),"</a></p>\n";$I=Connection::get()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$re=$I;if(!$I)$I=Connection::get()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''>\n";hidden_fields_get();echo
input_hidden("db",DB);if(!$re)echo
input_hidden("grant");echo"\n","<div class='scrollable'>\n","<table class='checkable'>\n","<thead><tr><th>".lang(28)."<th>".lang(5)."<th></thead>\n";while($K=$I->fetchAssoc())echo'<tr><td>'.h($K["User"])."<td>".h($K["Host"]).'<td><a href="'.h(ME.'user='.urlencode($K["User"]).'&host='.urlencode($K["Host"])).'">'.lang(38)."</a>\n";if(!$re||DB!="")echo"<tr><td><input class='input' name='user' autocapitalize='off'><td><input class='input' name='host' value='localhost' autocapitalize='off'><td><input type='submit' class='button' value='".lang(38)."'>\n";echo"</table>\n","</div>\n","</form>\n";}elseif(isset($_GET["sql"])){$P=Admin::get()->getSettings();if($_POST["export"]){$P->updateParameters(["exportFormat"=>$_POST["format"],"exportOutput"=>$_POST["output"],]);dump_headers("sql");Admin::get()->dumpTable("","");Admin::get()->dumpData("","table",$_POST["query"]);exit;}restart_session();$Je=&get_session("queries");$Ie=&$Je[DB];if($_POST["clear"]){$Ie=[];redirect(remove_from_uri("history"));}stop_session();$T=isset($_GET["import"])?lang(73):lang(40);page_header($T,[$T]);$bg="--".(DIALECT=="sql"?" ":"");if($_POST){$ie=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$Ve=Admin::get()->getImportFilePath();if($Ve){if(file_exists($Ve))$ie=fopen($Ve,"rb");elseif(file_exists("$Ve.gz"))$ie=fopen("compress.zlib://$Ve.gz","rb");}$H=$ie?fread($ie,1e6):false;}else$H=get_file("sql_file",true,";");if(is_string($H)){if(($_g=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($_g,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$Oi=$H.(preg_match("~;[ \t\r\n]*\$~",$H)?"":";");if(!$Ie||first(end($Ie))!=$Oi){restart_session();$Ie[]=[$Oi,time()];set_session("queries",$Je);stop_session();}}$nk="(?:\\s|/\\*[\s\S]*?\\*/|(?:#|$bg)[^\n]*\n?|--\r?\n)";$xc=";";$yc=1;$nh=0;$ed=true;$Sb=connect();if($Sb&&DB!=""){$Sb->selectDatabase(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$Sb);}$Hb=0;$nd=[];$bi='[\'"'.(DIALECT=="sql"?'`#':(DIALECT=="sqlite"?'`[':(DIALECT=="mssql"?'[':''))).']|/\*|'.$bg.'|$'.(DIALECT=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$wl=microtime(true);$Vc=Admin::get()->getDumpFormats();unset($Vc["sql"]);while($H!=""){if(!$nh&&preg_match("~^$nk*+DELIMITER\\s+(\\S+)~i",$H,$y)){$xc=preg_quote($y[1]);$yc=strlen($y[1]);$ee=Admin::get()->formatSqlCommandQuery(trim($y[0]));if($ee!="")echo"<pre><code class='jush-".DIALECT."'>$ee</code></pre>\n";$H=substr($H,strlen($y[0]));}elseif(!$nh&&DIALECT=="pgsql"&&preg_match("~^($nk*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$y)){$xc="\n\\\\\\.\r?\n";$yc=3;$nh=strlen($y[0]);}else{preg_match("($xc\\s*|$bi)",$H,$y,PREG_OFFSET_CAPTURE,$nh);list($ge,$G)=$y[0];if(!$ge&&$ie&&!feof($ie))$H
.=fread($ie,1e5);else{if(!$ge&&rtrim($H)=="")break;$nh=$G+strlen($ge);if($ge&&!preg_match("(^$xc)",$ge)){$gb=Driver::get()->hasCStyleEscapes()||(DIALECT=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$oi='(';if($ge=='/*')$oi
.='\*/';elseif($ge=='[')$oi
.=']';elseif(preg_match("~^$bg|^#~",$ge))$oi
.="\n";else$oi
.=preg_quote($ge).($gb?"|\\\\.":"");$oi
.='|$)s';while(preg_match($oi,$H,$y,PREG_OFFSET_CAPTURE,$nh)){$wj=$y[0][0];if(!$wj&&$ie&&!feof($ie))$H
.=fread($ie,1e5);else{$nh=$y[0][1]+strlen($wj);if(!isset($wj[0])||$wj[0]!="\\")break;}}}else{$ed=false;$Oi=substr($H,0,$G+$yc);$Hb++;$Gi="<pre id='sql-$Hb'><code class='jush-".DIALECT."'>".Admin::get()->formatSqlCommandQuery(trim($Oi))."</code></pre>\n";if(DIALECT=="sqlite"&&preg_match("~^$nk*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$Oi,$y)!==0){echo$Gi,"<p class='error'>".lang(183,preg_match('~ATTACH~i',$y[1])?'ATTACH':'VACUUM INTO')."\n";$nd[]=" <a href='#sql-$Hb'>$Hb</a>";if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$Gi;ob_flush();flush();}$sk=microtime(true);if(Connection::get()->multiQuery($Oi)&&is_object($Sb)&&preg_match("~^$nk*+USE\\b~i",$Oi))$Sb->query($Oi);do{$I=Connection::get()->storeResult();if(Connection::get()->getError()){echo($_POST["only_errors"]?$Gi:""),"<p class='error'>",lang(184),(!empty(Connection::get()->getErrno())?" (".Connection::get()->getErrno().")":""),": ",error()."</p>\n";$nd[]=" <a href='#sql-$Hb'>$Hb</a>";if($_POST["error_stops"])break
2;}else{$nl=" <span class='time'>(".format_time($sk).")</span>";$Zc=(strlen($Oi)<1000?" <a href='".h(ME)."sql=".urlencode(trim($Oi))."'>".icon("edit").lang(38)."</a>":"");$Si=Connection::get()->getQueryInfo();$ya=Connection::get()->getAffectedRows();$sm=($_POST["only_errors"]?null:Driver::get()->warnings());$um="warnings-$Hb";$vm=$sm?"<a href='#$um' class='toggle'>".lang(39).icon_chevron_down()."</a>":null;$vd=$Mh=null;$wd="explain-$Hb";$xd=false;$yd="export-$Hb";if(is_object($I)){if(!$_POST["only_errors"])echo"<div class='table-result'>\n";$v=(int)$_POST["limit"];$Mh=print_select_result($I,$Sb,[],$v);if(!$_POST["only_errors"]){echo"<p class='links'>";$kh=$I->getRowsCount();echo($kh?($v&&$kh>$v?lang(185,$v):"").lang(186,$kh):""),$nl,$Zc,$vm;if($Sb&&preg_match("~^($nk|\\()*+SELECT\\b~i",$Oi)&&($vd=explain($Sb,$Oi)))echo"<a href='#$wd' class='toggle'>Explain".icon_chevron_down()."</a>";$xd=true;echo"<a href='#$yd' class='toggle'>".lang(74).icon_chevron_down()."</a>","</p>\n";}}else{if(preg_match("~^$nk*+(CREATE|DROP|ALTER)$nk++(DATABASE|SCHEMA)\\b~i",$Oi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"]){echo"<p class='message' title='".h($Si)."'>",lang(187,$ya),"$nl $Zc";if($vm)echo", $vm";echo"</p>\n";}}if(!$_POST["only_errors"])echo
script("initToggles(qsl('p'));");if($sm)echo"<div id='$um' class='hidden'>\n$sm</div>\n";if($vd){echo"<div id='$wd' class='hidden explain'>\n";print_select_result($vd,$Sb,$Mh);echo"</div>\n";}if($xd)echo"<form id='$yd' action='' method='post' class='hidden'><p>\n",html_select("format",$Vc,$P->getParameter("exportFormat")),html_select("output",Admin::get()->getDumpOutputs(),$P->getParameter("exportOutput"))." ",input_hidden("query",$Oi),input_token()," <input type='submit' class='button' name='export' value='".lang(74)."'>","</p></form>\n";if(is_object($I)&&!$_POST["only_errors"])echo"</div>\n";}$sk=microtime(true);}while(Connection::get()->nextResult());}$H=substr($H,$nh);$nh=0;}}}}if($ed)echo"<p class='message'>".lang(188)."\n";elseif($_POST["only_errors"]){$qh=$Hb-count($nd);echo"<p class='".($qh?"message":"error")."'>".lang(189,$Hb-count($nd))," <span class='time'>(".format_time($wl).")</span>\n";}elseif($nd&&$Hb>1)echo"<p class='error'>".lang(184).": ".implode("",$nd)."\n";}else
echo"<p class='error'>".upload_error($H)."\n";}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";if(!isset($_GET["import"])){$Oi=$_GET["sql"];if($_POST)$Oi=$_POST["query"];elseif($_GET["history"]=="all")$Oi=$Ie;elseif($_GET["history"]!="")$Oi=$Ie[$_GET["history"]][0];echo"<p>";textarea("query",$Oi,20);echo
script(($_POST?"":"qs('textarea').focus();\n")."gid('form').onsubmit = partial(sqlSubmit, gid('form'), '".js_escape(remove_from_uri("sql|limit|error_stops|only_errors|history"))."');"),"</p>","<p><input type='submit' class='button default' value='".lang(190)."' title='Ctrl+Enter'>",lang(191).": <input type='number' name='limit' class='input size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{echo"<div class='field-sets'>\n","<fieldset><legend>".lang(192)."</legend><div class='fieldset-content'>";$ze=(extension_loaded("zlib")?"[.gz]":"");if(ini_bool("file_uploads"))echo"SQL$ze (&lt; ".ini_get("upload_max_filesize")."B): <input type='file' name='sql_file[]' multiple>","<input type='submit' class='button default' value='".lang(190)."'>",file_upload_form_script("form","sql_file[]");else
echo
lang(193);echo"</div></fieldset>\n";$Ve=Admin::get()->getImportFilePath();if($Ve)echo"<fieldset><legend>".lang(194)."</legend><div class='fieldset-content'>",lang(195,"<code>".h($Ve)."$ze</code>"),' <input type="submit" class="button default" name="webfile" value="'.lang(196).'">',"</div></fieldset>\n";echo"</div>\n","<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),lang(197)),checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),lang(198)),input_token(),"</p>\n";if(!isset($_GET["import"]))Admin::get()->printAfterSqlCommand();if(!isset($_GET["import"])&&$Ie){echo"<div class='field-sets'>\n";print_fieldset_start("history",lang(199),"history",$_GET["history"]!="");for($X=end($Ie);$X;$X=prev($Ie)){$t=key($Ie);list($Oi,$nl,$dd)=$X;echo" <pre><code class='jush-".DIALECT."'>",truncate_utf8(ltrim(str_replace("\n"," ",str_replace("\r","",preg_replace("~^(#|$bg).*~m",'',$Oi))))),"</code></pre>",'<p class="links">',"<a href='".h(ME."sql=&history=$t")."'>".icon("edit").lang(38)."</a>"," <span class='time' title='".@date('Y-m-d',$nl)."'>".@date("H:i:s",$nl).($dd?" ($dd)":"")."</span>","</p>";}echo"<p><input type='submit' class='button' name='clear' value='".lang(200)."'>\n","<a href='",h(ME."sql=&history=all")."' class='button light'>",icon("edit"),lang(201),"</a></p>\n";print_fieldset_end("history");echo"</div>\n";}echo"</form>\n";}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$k=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$k):""):where($_GET,$k));$Rl=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($k
as$A=>$j){if((!$Rl&&!isset($j["privileges"]["insert"]))||Admin::get()->getFieldName($j)=="")unset($k[$A]);}if($_POST&&!isset($_GET["select"])){$x=$_POST["referer"];if($_POST["insert"])$x=($Rl?null:$_SERVER["REQUEST_URI"]);elseif(!preg_match('~^.+&select=.+$~',$x))$x=ME."select=".urlencode($a);$s=indexes($a);$Ll=unique_array(isset($_GET["where"])?$_GET["where"]:[],$s);$Ti="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($x,lang(202),(bool)Driver::get()->delete($a,$Ti,$Ll?0:1));else{$O=[];foreach($k
as$A=>$j){$X=process_input($j);if($X!==false&&$X!==null)$O[idf_escape($A)]=$X;}if($Rl){if(!$O)redirect($x);queries_redirect($x,lang(203),(bool)Driver::get()->update($a,$O,$Ti,$Ll?0:1));if(is_ajax()){page_headers();page_messages();exit;}}else{$I=Driver::get()->insert($a,$O);$Qf=($I?last_id($I):0);queries_redirect($x,lang(204,($Qf?" $Qf":"")),(bool)$I);}}}$K=null;if($Z){$M=[];foreach($k
as$A=>$j){if(isset($j["privileges"]["select"])){$Ja=($_POST["clone"]&&$j["auto_increment"]?"''":convert_field($j));$M[]=($Ja?"$Ja AS ":"").idf_escape($A);}}$K=[];if(!support("table"))$M=["*"];if($M){$I=Driver::get()->select($a,$M,[$Z],$M,[],(isset($_GET["select"])?2:1));if(!$I)Admin::get()->addError(error());else{$K=$I->fetchAssoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetchAssoc()))$K=null;}}if(!support("table")&&!$k){if(!$Z){$I=Driver::get()->select($a,["*"],[],["*"]);$K=($I?$I->fetchAssoc():false);if(!$K)$K=[Driver::get()->primary=>""];}if($K){foreach($K
as$t=>$X){if(!$Z)$K[$t]=null;$k[$t]=["field"=>$t,"null"=>($t!=Driver::get()->primary),"auto_increment"=>($t==Driver::get()->primary)];}}}if(isset($_POST["save"])?$_POST["save"]:false)$K=(isset($_POST["fields"])?$_POST["fields"]:[])+($K?:[]);if($_POST["edit"]){$bd=array_filter($k,function($j){return!(isset($j["generated"])?$j["generated"]:null);});}else$bd=$k;edit_form($a,$bd,$K,$Rl);}elseif(isset($_GET["create"])){$a=$_GET["create"];$fi=Driver::get()->getPartitionBy();$ji=$fi?Driver::get()->getPartitionsInfo($a):[];$bj=referencable_primary($a);$be=[];foreach($bj
as$Rk=>$j)$be[str_replace("`","``",$Rk)."`".str_replace("`","``",$j["field"])]=$Rk;$Ph=[];$R=[];if($a!=""){$Ph=fields($a);$R=table_status1($a);if(count($R)<2)Admin::get()->addError(lang(78));}$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!Admin::get()->getErrors())Admin::get()->getSettings()->updateParameter("commentsOpened",isset($_POST["comments"])?$_POST["comments"]:null);if($_POST&&!process_fields($K["fields"])&&!Admin::get()->getErrors()){if($_POST["drop"])queries_redirect(substr(ME,0,-1),lang(205),drop_tables([$a]));else{$k=[];$Da=[];$Wl=false;$Zd=[];$Oh=reset($Ph);$_a=" FIRST";foreach($K["fields"]as$t=>$j){$n=$be[$j["type"]];$Fl=($n!==null?$bj[$n]:$j);if($j["field"]!=""){if(!$j["generated"])$j["default"]=null;$Mi=process_field($j,$Fl);$Da[]=[$j["orig"],$Mi,$_a];if(!$Oh||$Mi!==process_field($Oh,$Oh)){$k[]=[$j["orig"],$Mi,$_a];if($j["orig"]!=""||$_a)$Wl=true;}if($n!==null)$Zd[idf_escape($j["field"])]=($a!=""&&DIALECT!="sqlite"?"ADD":" ").format_foreign_key(['table'=>$be[$j["type"]],'source'=>[$j["field"]],'target'=>[$Fl["field"]],'on_delete'=>$j["on_delete"],]);$_a=" AFTER ".idf_escape($j["field"]);}elseif($j["orig"]!=""){$Wl=true;$k[]=[$j["orig"]];}if($j["orig"]!=""){$Oh=next($Ph);if(!$Oh)$_a="";}}$hi=[];if(in_array($K["partition_by"],$fi)){foreach($K
as$t=>$X){if(preg_match('~^partition~',$t))$hi[$t]=$X;}foreach($hi["partition_names"]as$t=>$A){if($A===""){unset($hi["partition_names"][$t]);unset($hi["partition_values"][$t]);}}$hi["partition_names"]=array_values($hi["partition_names"]);$hi["partition_values"]=array_values($hi["partition_values"]);if($hi==$ji)$hi=[];}elseif(str_contains(isset($R["Create_options"])?$R["Create_options"]:"","partitioned"))$hi=null;$_=lang(206);if($a==""){cookie("neo_engine",isset($K["Engine"])?$K["Engine"]:"");$_=lang(207);}$A=trim($K["name"]);queries_redirect(ME.(support("table")?"table=":"select=").urlencode($A),$_,alter_table($a,$A,(DIALECT=="sqlite"&&($Wl||$Zd)?$Da:$k),$Zd,($K["Comment"]!=$R["Comment"]?$K["Comment"]:null),($K["Engine"]&&$K["Engine"]!=$R["Engine"]?$K["Engine"]:""),($K["Collation"]&&$K["Collation"]!=$R["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$hi));}}if($a!="")page_header(lang(35).": ".h($a),["table"=>$a,lang(35)]);else
page_header(lang(77),[lang(77)]);if(!$_POST){$Hl=Driver::get()->getTypes();$K=["Engine"=>$_COOKIE["neo_engine"],"fields"=>[["field"=>"","type"=>(isset($Hl["int"])?"int":(isset($Hl["integer"])?"integer":"")),"on_update"=>""]],"partition_names"=>[""],];if($a!=""){$K=$R;$K["name"]=$a;$K["fields"]=[];if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($Ph
as$j){$j["generated"]=$j["generated"]?:(isset($j["default"])?"DEFAULT":"");$K["fields"][]=$j;}if($fi){$K+=$ji;$K["partition_names"][]="";$K["partition_values"][]="";}}}$Bf=[];if($K["Collation"])$Bf[$K["Collation"]]=true;foreach($K["fields"]as$j){if($j["collation"])$Bf[$j["collation"]]=true;}$Ab=Admin::get()->getCollations(array_keys($Bf));$jd=Driver::get()->engines();foreach($jd
as$id){if(!strcasecmp($id,$K["Engine"])){$K["Engine"]=$id;break;}}echo"<form action='' method='post' id='form'>\n";if(support("columns")||$a==""){echo"<p>",lang(208),": ","<input class='input' name='name' data-maxlength='64' value='",h($K["name"]),"' autocapitalize='off'",(($a==""&&!$_POST)?" autofocus":""),">";if($jd)echo" ",html_select("Engine",[""=>"(".lang(209).")"]+$jd,$K["Engine"]),help_script_command("value",true);if($Ab&&!preg_match("~sqlite|mssql~",DIALECT))echo" ",html_select("Collation",[""=>"(".lang(90).")"]+$Ab,$K["Collation"]);echo" <input type='submit' class='button default' value='",lang(112),"'>","</p>";}if(support("columns")&&($a==""||!Driver::get()->isPartition($a))){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$Ab,"TABLE",$be);echo"</table>\n",script("initFieldsEditing(gid('edit-fields'));");if(support("move_col"))echo
script("initSortable('#edit-fields tbody');");echo"</div>\n","<p>",lang(47),": ","<input type='number' class='input size' name='Auto_increment' size='6' value='",h($K["Auto_increment"]),"'>";$Lb=$_POST?$_POST["comments"]:Admin::get()->getSettings()->getParameter("commentsOpened");$Jb=$Lb?"":"hidden";if(support("comment")){echo
checkbox("comments",1,$Lb,lang(46),"editingCommentsClick(this, ".(support("move_col")?7:6).");","jsonly")," ";if(preg_match('~\n~',$K["Comment"]))echo"<textarea name='Comment' rows='2' cols='20'",($Jb?" class='$Jb'":""),">",h($K["Comment"]),"</textarea>";else
echo"<input name='Comment' value='",h($K["Comment"]),"' data-maxlength='",(Connection::get()->isMinVersion("5.5")?2048:60),"' class='input $Jb'>";}echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(112),"'>";}elseif($a!="")echo"<p>";if($a!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(lang(210,$a)),"</p>\n";if($fi&&(DIALECT=="sql"||$a=="")){echo"<div class='field-sets'>\n";$gi=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset_start("partition",lang(211),"split",(bool)$K["partition_by"]);echo"<p>",html_select("partition_by",array_merge([""],$fi),$K["partition_by"]),help_script_command("value.replace(/./, 'PARTITION BY \$&')",true),script("qsl('select').onchange = partitionByChange;"),"(<input class='input' name='partition' value='",h($K["partition"]),"'>) ",lang(49),": ","<input type='number' name='partitions' class='input size ",($gi||!$K["partition_by"]?"hidden":""),"' value='",h($K["partitions"]),"'>","</p>\n","<table id='partition-table'",($gi?"":" class='hidden'"),">\n","<thead><tr><th>",lang(212),"</th><th>",lang(51),"</th></tr></thead>\n";foreach($K["partition_names"]as$t=>$X){echo"<tr>","<td><input class='input' name='partition_names[]' value='",h($X),"' autocapitalize='off'>";if($t==count($K["partition_names"])-1)echo
script("qsl('input').oninput = partitionNameChange;");echo"</td>","<td><input class='input' name='partition_values[]' value='",h(isset($K["partition_values"][$t])?$K["partition_values"][$t]:""),"'></td>","</tr>\n";}echo"</table>\n","</p>\n";print_fieldset_end("partition");echo"</div>\n";}echo
input_token(),"</form>\n";}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$bf=["PRIMARY","UNIQUE","INDEX"];$R=table_status1($a,true);$Ze=Driver::get()->getIndexAlgorithms($R);$d=Connection::get();$ng=$d->isMariaDB();if(preg_match('~MyISAM|M?aria'.($d->isMinVersion($ng?"10.0.5":"5.6")?'|InnoDB':'').'~i',$R["Engine"]))$bf[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.($d->isMinVersion($ng?"10.2.2":"5.7")?'|InnoDB':'').'~i',$R["Engine"]))$bf[]="SPATIAL";if($ng&&$d->isMinVersion("11.7")&&preg_match('~MyISAM|InnoDB~i',$R["Engine"]))$bf[]="VECTOR";$s=indexes($a);$k=fields($a);$Fi=[];if(DIALECT=="mongo"){$Fi=$s["_id_"];unset($bf[0]);unset($s["_id_"]);}$K=$_POST;if($K)Admin::get()->getSettings()->updateParameter("indexOptions",isset($K["options"])?$K["options"]:null);if($_POST&&!$_POST["add"]&&!$_POST["drop_col"]){$Fa=[];foreach($K["indexes"]as$r){$A=$r["name"];if(in_array($r["type"],$bf)){$c=[];$Yf=[];$Ac=[];$Ye=$Ze?(in_array($r["algorithm"],$Ze)?$r["algorithm"]:first($Ze)):"";$af=(support("partial_indexes")?$r["partial"]:"");$O=[];ksort($r["columns"]);foreach($r["columns"]as$t=>$b){if($b!=""){$u=isset($r["lengths"][$t])?$r["lengths"][$t]:null;$zc=isset($r["descs"][$t])?$r["descs"][$t]:null;$O[]=($k[$b]?idf_escape($b):$b).($u?"(".(+$u).")":"").($zc?" DESC":"");$c[]=$b;$Yf[]=($u?:null);$Ac[]=$zc;}}$ud=$s[$A];if($ud){ksort($ud["columns"]);ksort($ud["lengths"]);ksort($ud["descs"]);if($r["type"]==$ud["type"]&&array_values($ud["columns"])===$c&&(!$ud["lengths"]||array_values($ud["lengths"])===$Yf)&&array_values($ud["descs"])===$Ac&&(!$Ze||$ud["algorithm"]===$Ye)&&$ud["partial"]==$af){unset($s[$A]);continue;}}if($c)$Fa[]=[$r["type"],$A,$O,$Ye,$af];}}foreach($s
as$A=>$ud)$Fa[]=[$ud["type"],$A,"DROP"];if(!$Fa)redirect(ME."table=".urlencode($a));queries_redirect(ME."table=".urlencode($a),lang(213),alter_indexes($a,$Fa));}page_header(lang(166),["table"=>$a,lang(166)],h($a));$Md=array_keys($k);if($_POST["add"]){foreach($K["indexes"]as$t=>$r){if($r["columns"][count($r["columns"])]!="")$K["indexes"][$t]["columns"][]="";}$r=end($K["indexes"]);if($r["type"]||array_filter($r["columns"],'strlen'))$K["indexes"][]=["columns"=>[1=>""]];}if(!$K){foreach($s
as$t=>$r){$s[$t]["name"]=$t;$s[$t]["columns"][]="";}$s[]=["columns"=>[1=>""]];$K["indexes"]=$s;}$Yf=(DIALECT=="sql"||DIALECT=="mssql");$fk=$_POST?$_POST["options"]:Admin::get()->getSettings()->getParameter("indexOptions");echo"<form action='' method='post'>\n","<div class='scrollable'>\n","<table class='nowrap'>\n","<thead><tr>","<th id='label-type'>",lang(214),"</th>";$Fh="class='idxopts".($fk?"":" hidden")."'";if(count($Ze)>1)echo"<th id='label-method' $Fh>",lang(215),doc_link(['sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'ha-and-performance/optimization-and-tuning/optimization-and-indexes/storage-engine-index-types',]),"</th>";echo"<th><input type='submit' class='button invisible'>",lang(52).($Yf?"<span $Fh> (".lang(53).")</span>":"");if($Yf||support("descidx"))echo
checkbox("options",1,$fk,lang(96),"indexOptionsShow(this.checked)","jsonly")."\n";echo"</th>","<th id='label-name'>",lang(216),"</th>";if(support("partial_indexes"))echo"<th id='label-condition' $Fh>",lang(54),"</th>";echo"<th>","<button name='add[0]' value='1' title='",lang(97),"' class='button light hidden'>",icon_solo("add"),"</button>","</th>","</tr></thead>\n";if($Fi){echo"<tr><td>PRIMARY<td>";foreach($Fi["columns"]as$b)echo
select_input(" disabled",$Md,$b),"<label><input type='checkbox' disabled>".lang(62)."</label> ";echo"<td><td>\n";}$yf=1;foreach($K["indexes"]as$r){if(!$_POST["drop_col"]||$yf!=key($_POST["drop_col"])){echo"<tr><td>",html_select("indexes[$yf][type]",[-1=>""]+$bf,$r["type"],($yf==count($K["indexes"])?"indexesAddRow.call(this);":""),"label-type"),"</td>";if(count($Ze)>1)echo"<td $Fh>",html_select("indexes[$yf][algorithm]",array_merge([""],$Ze),$r['algorithm'],"label-method"),"</td>";echo"<td>";ksort($r["columns"]);$p=1;foreach($r["columns"]as$t=>$b){echo"<span>".select_input(" name='indexes[$yf][columns][$p]' title='".lang(43)."'",($k&&($b==""||$k[$b])?array_combine($Md,$Md):[]),$b,"partial(".($p==count($r["columns"])?"indexesAddColumn":"indexesChangeColumn").", '".js_escape(DIALECT=="sql"?"":$_GET["indexes"]."_")."')"),"<span $Fh>";if($Yf)echo"<input type='number' name='indexes[$yf][lengths][$p]' class='input size' value='".(h(isset($r["lengths"][$t])?$r["lengths"][$t]:"")),"' title='".lang(95),"'>";if(support("descidx"))echo
checkbox("indexes[$yf][descs][$p]",1,isset($r["descs"][$t])?$r["descs"][$t]:false,lang(62));echo"</span> </span>";$p++;}echo"</td>","<td><input name='indexes[$yf][name]' value='",h($r["name"]),"' class='input' autocapitalize='off' aria-labelledby='label-name'></td>\n";if(support("partial_indexes"))echo"<td $Fh><input name='indexes[$yf][partial]' value='".h($r["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>","<button name='drop_col[$yf]' value='1' title='",h(lang(58)),"' class='button light'>",icon_solo("remove"),"</button>",script("qsl('button').onclick = onRemoveIndexRowClick;"),"</td>\n";}$yf++;}echo"</table>\n","</div>\n","<p>","<input type='submit' class='button default' value='",lang(112),"'>",input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!isset($_POST["add_x"])){$A=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),lang(217),drop_databases([DB]));}elseif(DB!==$A){if(DB!=""){$_GET["db"]=$A;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".urlencode($A),lang(218),rename_database($A,$K["collation"]));}else{$f=explode("\n",str_replace("\r","",$A));$Bk=true;$Pf="";foreach($f
as$g){if(count($f)==1||$g!=""){if(!create_database($g,$K["collation"]))$Bk=false;$Pf=$g;}}restart_session();set_session("dbs",null);queries_redirect(ME."db=".urlencode($Pf),lang(219),$Bk);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($A).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),lang(220));}}if(DB!="")page_header(lang(69).": ".h(DB),[lang(69)]);else
page_header(lang(75),[lang(75)]);$A=DB;if($_POST)$A=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,collations());elseif(DIALECT=="sql"){foreach(get_vals("SHOW GRANTS")as$re){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$re,$y)&&$y[1]){$A=stripcslashes(idf_unescape("`$y[2]`"));break;}}}$Ab=Admin::get()->getCollations($K["collation"]?[$K["collation"]]:[]);echo"<form action='' method='post'>\n","<p>";if($_POST["add_x"]||strpos($A,"\n"))echo"<textarea id='name' name='name' rows='10' cols='40'>",h($A),"</textarea><br>\n";else
echo"<input class='input' name='name' id='name' value='",h($A),"' data-maxlength='64' autocapitalize='off' autofocus>\n";if($Ab)echo
html_select("collation",[""=>"(".lang(90).")"]+$Ab,$K["collation"]),doc_link(['sql'=>"charset-charsets.html",'mariadb'=>"reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations",]),"\n";echo"<input type='submit' class='button default' value='",lang(112),"'>\n";if(DB!="")echo"<input type='submit' class='button' name='drop' value='".lang(159)."'>".confirm(lang(210,DB))."\n";elseif(!$_POST["add_x"]&&$_GET["db"]=="")echo"<button name='add_x' value='1' title='",h(lang(97)),"' class='button light'>",icon_solo("add"),"</button>\n";echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["call"])){$na=$_GET["name"]?:$_GET["call"];page_header(lang(221).": ".h($na),[lang(221)]);$sj=routine($_GET["call"],(isset($_GET["callf"])?"FUNCTION":"PROCEDURE"));$We=[];$Th=[];foreach($sj["fields"]as$p=>$j){if(substr($j["inout"],-3)=="OUT"&&DIALECT=='sql')$Th[$p]="@".idf_escape($j["field"])." AS ".idf_escape($j["field"]);if(!$j["inout"]||substr($j["inout"],0,2)=="IN")$We[]=$p;}if($_POST){$ib=[];foreach($sj["fields"]as$t=>$j){$X="";if(in_array($t,$We)){$X=process_input($j);if($X===false)$X="''";if(isset($Th[$t]))Connection::get()->query("SET @".idf_escape($j["field"])." = $X");}if(isset($Th[$t]))$ib[]="@".idf_escape($j["field"]);elseif(in_array($t,$We))$ib[]=$X;}$H=(isset($_GET["callf"])?"SELECT ":"CALL ").($sj["returns"]&&$sj["returns"]["type"]=="record"?"* FROM ":"").table($na)."(".implode(", ",$ib).")";$sk=microtime(true);$I=Connection::get()->multiQuery($H);$ya=Connection::get()->getAffectedRows();echo
Admin::get()->formatSelectQuery($H,$sk,!$I);if(!$I)echo"<p class='error'>".error()."\n";else{$Sb=connect();if($Sb)$Sb->selectDatabase(DB);do{$I=Connection::get()->storeResult();if(is_object($I))print_select_result($I,$Sb);else
echo"<p class='message'>".lang(222,$ya)." <span class='time'>".@date("H:i:s")."</span>\n";}while(Connection::get()->nextResult());if($Th)print_select_result(Connection::get()->query("SELECT ".implode(", ",$Th)));}}echo"<form action='' method='post'>\n";if($We){echo"<table class='box'>\n";foreach($We
as$t){$j=$sj["fields"][$t];$A=$j["field"];echo"<tr><th>".Admin::get()->getFieldName($j);$Y=isset($_POST["fields"][$A])?$_POST["fields"][$A]:"";if($Y!=""){if($j["type"]=="set")$Y=implode(",",$Y);}input($j,$Y,(string)(isset($_POST["function"][$A])?$_POST["function"][$A]:""));echo"\n";}echo"</table>\n";}echo"<p>\n","<input type='submit' class='button' value='",lang(221),"'>\n",input_token(),"</p>\n","</form>\n";$Ib=$sj["comment"];if($Ib!==null&&$Ib!==""){$Ib=h(trim($sj["comment"],"\n"));if(preg_match('~^ +~',$Ib,$z)){preg_match_all("~^($z[0]|$)~m",$Ib,$cg);if(count($cg[0])==substr_count($Ib,"\n"))$Ib=preg_replace("~^($z[0])~m","",$Ib);}$Ib=preg_replace('~(^|[^\n]\n)(Description|Parameters|Example)\n~',"$1\n<strong>$2</strong>\n",$Ib);echo"<pre class='comment'>$Ib</pre>\n";}}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$A=$_GET["name"];$K=$_POST;if($_POST&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$dl=[];foreach($K["source"]as$t=>$X)$dl[$t]=$K["target"][$t];$K["target"]=$dl;}if(DIALECT=="sqlite")$I=recreate_table($a,$a,[],[],[" $A"=>($K["drop"]?"":" ".format_foreign_key($K))]);else{$Fa="ALTER TABLE ".table($a);$I=($A==""||queries("$Fa DROP ".(DIALECT=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($A)));if(!$K["drop"])$I=queries("$Fa ADD".format_foreign_key($K));}queries_redirect(ME."table=".urlencode($a),($K["drop"]?lang(223):($A!=""?lang(224):lang(225))),(bool)$I);if(!$K["drop"])Admin::get()->addError(lang(226));}page_header(lang(227).": ".h($a),["table"=>$a,lang(227)]);if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=[];else$K["source"][]="";}elseif($A!=""){$be=foreign_keys($a);$K=$be[$A];$K["source"][]="";}else{$K["table"]=$a;$K["source"]=[""];}echo"<form action='' method='post'>\n";$lk=array_keys(fields($a));if($K["db"]!="")Connection::get()->selectDatabase($K["db"]);if($K["ns"]!=""){$Qh=get_schema();set_schema($K["ns"]);}$aj=array_keys(array_filter(table_status('',true),'AdminNeo\fk_support'));$dl=array_keys(fields(in_array($K["table"],$aj)?$K["table"]:reset($aj)));$yh="this.form['change-js'].value = '1'; this.form.submit();";echo"<p>","<span id='label-table'>",lang(228),":</span> ",html_select("table",$aj,$K["table"],$yh,"label-table");if(DIALECT!="sqlite"){$pc=[];foreach(Admin::get()->getDatabases()as$g){if(!information_schema($g))$pc[]=$g;}echo"<span id='label-db'>",lang(229),":</span> ",html_select("db",$pc,$K["db"]!=""?$K["db"]:$_GET["db"],$yh,"label-db");}echo
input_hidden("change-js"),"<noscript><input type='submit' class='button' name='change' value='",lang(230),"'></noscript>","</p>\n","<table>","<thead><tr><th id='label-source'>",lang(167),"<th id='label-target'>",lang(168),"</thead>\n";$yf=0;foreach($K["source"]as$t=>$X){echo"<tr>","<td>".html_select("source[".(+$t)."]",[-1=>""]+$lk,$X,($yf==count($K["source"])-1?"foreignAddRow.call(this);":""),"label-source"),"<td>".html_select("target[".(+$t)."]",$dl,isset($K["target"][$t])?$K["target"][$t]:null,"","label-target");$yf++;}echo"</table>\n","<noscript><p><input type='submit' class='button' name='add' value='",lang(231),"'></p></noscript>","<p>\n","<span id='label-delete'>".lang(92),":</span> ",html_select("on_delete",[-1=>""]+Driver::get()->getOnActions(),$K["on_delete"],"","label-delete"),"<span id='label-update'>".lang(91),":</span> ",html_select("on_update",[-1=>""]+Driver::get()->getOnActions(),$K["on_update"],"","label-update");if(DRIVER=='pgsql')echo
html_select("deferrable",['NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'],$K["deferrable"]);echo
doc_link(['sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"architecture/server-constraints/foreign-key-constraints",]),"</p>\n<p>","<input type='submit' class='button default' value='",lang(112),"'>";if($A!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(lang(210,$A));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$Rh="VIEW";if(DIALECT=="pgsql"&&$a!=""){$uk=table_status1($a);$Rh=strtoupper($uk["Engine"]);}if($_POST){$A=trim($K["name"]);$Ja=" AS\n$K[select]";$x=ME."table=".urlencode($A);$_=lang(232);$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$A&&DIALECT!="sqlite"&&$U=="VIEW"&&$Rh=="VIEW")query_redirect((DIALECT=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($A).$Ja,$x,$_);else{$fl=$A."_adminneo_".uniqid();drop_create("DROP $Rh ".table($a),"CREATE $U ".table($A).$Ja,"DROP $U ".table($A),"CREATE $U ".table($fl).$Ja,"DROP $U ".table($fl),($_POST["drop"]?substr(ME,0,-1):$x),lang(233),$_,lang(234),$a,$A);}}if(!$_POST&&$a!=""){$K=view($a);$K["name"]=$a;$K["materialized"]=($Rh!="VIEW");if($i=error())Admin::get()->addError($i);}if($a!="")page_header(lang(36).": ".h($a),["table"=>$a,lang(36)]);else
page_header(lang(235),[lang(235)]);echo"<form action='' method='post'>\n","<p>",lang(216),":","<input class='input' name='name' value='",h($K["name"]),"' data-maxlength='64' autocapitalize='off'>\n";if(support("materializedview"))echo
checkbox("materialized",1,$K["materialized"],lang(160));echo"</p>\n<p>";textarea("select",$K["select"]);echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(112),"'>\n";if($a!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>\n",confirm(lang(210,$a));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["event"])){$ea=$_GET["event"];$lf=["YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND"];$vk=["ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE"];$K=$_POST;if($_POST){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($ea),substr(ME,0,-1),lang(236));elseif(in_array($K["INTERVAL_FIELD"],$lf)&&isset($vk[$K["STATUS"]])){$Cj="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($ea!=""?lang(237):lang(238)),(bool)queries(($ea!=""?"ALTER EVENT ".idf_escape($ea).$Cj.($ea!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$Cj)."\n".$vk[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}if($ea!="")page_header(lang(239).": ".h($ea),[lang(239)]);else
page_header(lang(240),[lang(240)]);if(!$K&&$ea!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($ea));$K=reset($L);}echo"<form action='' method='post'>\n","<table class='box box-light'>\n","<tr><th>",lang(216),"</th><td>","<input class='input' name='EVENT_NAME' value='",h($K["EVENT_NAME"]),"' data-maxlength='64' autocapitalize='off'>","</td></tr>\n","<tr><th title='datetime'>",lang(241),"</th><td>","<input class='input' name='STARTS' value='",h("$K[EXECUTE_AT]$K[STARTS]"),"'>","</td></tr>\n","<tr><th title='datetime'>",lang(242),"</th><td>","<input class='input' name='ENDS' value='",h($K["ENDS"]),"'>","</td></tr>\n","<tr><th>",lang(243),"</th><td>","<input type='number' name='INTERVAL_VALUE' value='",h($K["INTERVAL_VALUE"]),"' class='input size'> ",html_select("INTERVAL_FIELD",$lf,$K["INTERVAL_FIELD"]),"</td></tr>\n","<tr><th>",lang(151),"</th><td>",html_select("STATUS",$vk,$K["STATUS"]),"</td></tr>\n","<tr><th>",lang(46),"</th><td>","<input class='input' name='EVENT_COMMENT' value='",h($K["EVENT_COMMENT"]),"' data-maxlength='64'>","</td></tr>\n","<tr><th></th><td>",checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",lang(244)),"</td></tr>\n","</table>\n","<p>";textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo"</p>\n","<p>","<input type='submit' class='button default' value='",lang(112),"'>";if($ea!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(lang(210,$ea));echo"</p>\n",input_token(),"</form>\n";}elseif(isset($_GET["procedure"])){$na=($_GET["name"]?:$_GET["procedure"]);$sj=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])){foreach($K["fields"]as$t=>$j){if($j["field"]=="")unset($K["fields"][$t]);}$th=routine_id($na,routine($_GET["procedure"],$sj));$ch=routine_id($K["name"],$K);$ac=create_routine($sj,$K);$x=substr(ME,0,-1);$_=lang(245);if(!$_POST["drop"]&&$th==$ch&&(DIALECT!="sql"||Connection::get()->isMariaDB()))query_redirect(substr_replace($ac,' OR REPLACE',6,0),$x,$_);else{$fl="$K[name]_adminer_".uniqid();drop_create("DROP $sj $th",$ac,"DROP $sj $ch",create_routine($sj,["name"=>$fl]+$K),"DROP $sj ".routine_id($fl,$K),$x,lang(246),$_,lang(247),$na,$K["name"]);}}if($na!=""){$T=isset($_GET["function"])?lang(248):lang(249);page_header($T.": ".h($na),[$T]);}else{$T=isset($_GET["function"])?lang(250):lang(251);page_header($T,[$T]);}if(!$_POST){if($na=="")$K["language"]="sql";else{$K=routine($_GET["procedure"],$sj);$K["name"]=$na;}}$nb=get_vals("SHOW CHARACTER SET");sort($nb);$tj=routine_languages();echo"<form action='' method='post' id='form'>\n","<p>",lang(216),": ","<input class='input' name='name' value='",h($K["name"]),"' data-maxlength='64' autocapitalize='off'>";if($tj)echo"<span id='label-language'>",lang(9),":</span> ",html_select("language",$tj,$K["language"],"","label-language");echo"<input type='submit' class='button default' value='",lang(112),"'>","</p>\n","<div class='scrollable'>\n","<table class='nowrap' id='edit-fields'>\n";edit_fields($K["fields"],$nb,$sj);if(isset($_GET["function"])){echo"<tbody><tr>";if(support("move_col"))echo"<th></th>";echo"<th>",lang(252),"</th>";edit_type("returns",(array)$K["returns"],$nb,[],(DIALECT=="pgsql"?["void","trigger"]:[]));echo"<td></td>","</tr></tbody>\n";}echo"</table>\n",script("initFieldsEditing(gid('edit-fields'));");if(support("move_col"))echo
script("initSortable('#edit-fields tbody');");echo"</div>\n","<p>";textarea("definition",$K["definition"],20);echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(112),"'>";if($na!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(lang(210,$na));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["check"])){$a=$_GET["check"];$A=$_GET["name"];$K=$_POST;if($K){if(DIALECT=="sqlite")$Bk=recreate_table($a,$a,[],[],[],"",[],"$A",($K["drop"]?"":$K["clause"]));else{$Bk=($A==""||queries("ALTER TABLE ".table($a)." DROP CONSTRAINT ".idf_escape($A)));if(!$K["drop"])$Bk=(bool)queries("ALTER TABLE ".table($a)." ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"")." CHECK ($K[clause])");}queries_redirect(ME."table=".urlencode($a),($K["drop"]?lang(253):($A!=""?lang(254):lang(255))),$Bk);}page_header(($A!=""?lang(256).": ".h($A):lang(172)),["table"=>$a]);if(!$K){$sb=Driver::get()->checkConstraints($a);$K=["name"=>$A,"clause"=>$sb[$A]];}echo"<form action='' method='post'>\n","<p>";if(DIALECT!="sqlite")echo
lang(216).': <input name="name" value="'.h($K["name"]).'" class="input" data-maxlength="64" autocapitalize="off"> ';echo
doc_link(['sql'=>"create-table-check-constraints.html",'mariadb'=>"reference/sql-statements/data-definition/constraint",],"?"),"</p>\n<p>";textarea("clause",$K["clause"]);echo"</p>\n<p>","<input type='submit' class='button default' value='",lang(112),"'>";if($A!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(lang(210,$A));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$A=isset($_GET["name"])?$_GET["name"]:"";$Bl=trigger_options();$K=trigger($A,$a)+["Trigger"=>$a."_bi"];if($_POST){if(in_array($_POST["Timing"],$Bl["Timing"])&&in_array($_POST["Event"],$Bl["Event"])&&in_array($_POST["Type"],$Bl["Type"])){$wh=" ON ".table($a);$Rc="DROP TRIGGER ".idf_escape($A).(DIALECT=="pgsql"?$wh:"");$x=ME."table=".urlencode($a);if($_POST["drop"])query_redirect($Rc,$x,lang(257));else{if($A!="")queries($Rc);queries_redirect($x,($A!=""?lang(258):lang(259)),(bool)queries(create_trigger($wh,$_POST)));if($A!="")queries(create_trigger($wh,$K+["Type"=>reset($Bl["Type"])]));}}$K=$_POST;}if($A!="")page_header(lang(260).": ".h($A),["table"=>$a,h($A)]);else
page_header(lang(261),["table"=>$a,lang(261)]);echo"<form action='' method='post' id='form'>\n","<table class='box box-light'>\n","<tr><th id='label-time'>",lang(262),"</th><td>",html_select("Timing",$Bl["Timing"],$K["Timing"],"triggerChange(/^".preg_quote($a,"/")."_[ba][iud]$/, '".js_escape($a)."', this.form);","label-time"),"</td></tr>\n","<tr><th id='label-event'>",lang(263),"</th><td>",html_select("Event",$Bl["Event"],$K["Event"],"this.form['Timing'].onchange();","label-event");if(in_array("UPDATE OF",$Bl["Event"]))echo" <input name='Of' value='".h($K["Of"])."' class='input hidden'>";echo"</td></tr>\n","<tr><th id='label-type'>",lang(44),"</th><td>",html_select("Type",$Bl["Type"],$K["Type"],"","label-type"),"</td></tr>\n","</table>\n","<p>",lang(216),"<input class='input' name='Trigger' value='",h($K["Trigger"]),"' data-maxlength='64' autocapitalize='off'>","</p>\n",script("gid('form')['Timing'].onchange();"),"<p>";textarea("Statement",$K["Statement"]);echo"</p>\n","<p>","<input type='submit' class='button default' value='",lang(112),"'>";if($A!="")echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>",confirm(lang(210,$A));echo"</p>\n",input_token(),"</form>\n";}elseif(isset($_GET["user"])){$pa=$_GET["user"];$Ji=[""=>["All privileges"=>""]];foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$Wb)$Ji[$Wb=="File access on server"?"Server Admin":$Wb][$K["Privilege"]]=$K["Comment"];}unset($Ji["Server Admin"]["Usage"]);foreach($Ji["Tables"]as$t=>$X)unset($Ji["Databases"][$t]);$bh=[];if($_POST){foreach($_POST["objects"]as$t=>$X)$bh[$X]=(array)$bh[$X]+(array)$_POST["grants"][$t];}$te=[];if(isset($_GET["host"])&&($I=Connection::get()->query("SHOW GRANTS FOR ".q($pa)."@".q($_GET["host"])))){while($K=$I->fetchRow()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$y)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$y[1],$z,PREG_SET_ORDER)){foreach($z
as$X){if($X[1]!="USAGE")$te["$y[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$te["$y[2]$X[2]"]["GRANT OPTION"]=true;}}}}$si=!Connection::get()->isMariaDB()&&Connection::get()->isMinVersion("8");if($_POST){$vh=(isset($_GET["host"])?q($pa)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $vh",ME."privileges=",lang(264));else{$eh=q($_POST["user"])."@".q($_POST["host"]);$li=$_POST["pass"];$dc=false;$I=true;if($vh!=$eh){$dc=(bool)queries("CREATE USER $eh IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($li));$I=$dc;}elseif($li!="")$I=(bool)queries("SET PASSWORD FOR $eh = ".($si||$_POST["hashed"]?q($li):"PASSWORD(".q($li).")"));if($I){$pj=[];foreach($bh
as$mh=>$re){if(isset($_GET["grant"]))$re=array_filter($re);$re=array_keys($re);if(isset($_GET["grant"]))$pj=array_diff(array_keys(array_filter($bh[$mh],'strlen')),$re);elseif($vh==$eh){$sh=array_keys((array)$te[$mh]);$pj=array_diff($sh,$re);$re=array_diff($re,$sh);unset($te[$mh]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$mh,$y)&&(!grant(false,$pj,$y[2],$y[1],$eh)||!grant(true,$re,$y[2],$y[1],$eh))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($vh!=$eh)queries("DROP USER $vh");elseif(!isset($_GET["grant"])){foreach($te
as$mh=>$pj){if(preg_match('~^(.+)(\(.*\))?$~U',$mh,$y))grant(false,array_keys($pj),$y[2],$y[1],$eh);}}}queries_redirect(ME."privileges=",(isset($_GET["host"])?lang(265):lang(266)),$I);if($dc)Connection::get()->query("DROP USER $eh");}}$T=isset($_GET["host"])?lang(28).": ".h("$pa@$_GET[host]"):lang(182);$ql=isset($_GET["host"])?h($pa):lang(182);page_header($T,["privileges"=>['',lang(72)],$ql]);if($_POST){$K=$_POST;$te=$bh;}else{$K=$_GET+["host"=>Connection::get()->getValue("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")];if($te)$te[".*"]=[];elseif(DB!="")$te[idf_escape(addcslashes(DB,"%_\\")).".*"]=[];else$te["*.* "]=[];}echo"<form action='' method='post'>\n","<table class='box box-light'>\n","<tr><th>",lang(5),"</th>","<td><input class='input' name='host' data-maxlength='60' value='",h($K["host"]),"' autocapitalize='off'></td>\n","<tr><th>",lang(28),"</th>","<td><input class='input' name='user' data-maxlength='80' value='",h($K["user"]),"' autocapitalize='off'></td>\n",'<tr><th>',lang(29),"</th>","<td><input class='input' name='pass' id='pass' value='",h($K["pass"]),"' autocomplete='new-password'>";if(!$si)echo
checkbox("hashed",1,$K["hashed"],lang(267),"typePassword(this.form['pass'], this.checked);");echo"</td>\n";if(!$K["hashed"])echo
script("typePassword(gid('pass'));");echo"</table>\n","<div class='scrollable'><table class='checkable'>\n","<thead><tr><th colspan='2'>".lang(72).doc_link(['sql'=>"grant.html#priv_level","mariadb"=>"reference/sql-statements/account-management-sql-statements/grant#privilege-levels"])."</th>";$p=0;foreach($te
as$mh=>$re){echo"<th>";if($mh=="*.*")echo"*.*",input_hidden("objects[$p]","*.*");else
echo"<input class='input' name='objects[$p]' value='".h(trim($mh))."' size='10' autocapitalize='off'>";echo"</th>";$p++;}echo"</tr></thead>\n";foreach([""=>"","Server Admin"=>lang(5),"Databases"=>lang(30),"Tables"=>lang(8),"Procedures"=>lang(268),]as$Wb=>$zc){foreach((array)$Ji[$Wb]as$Ii=>$Ib){echo"<tr>";if($zc)echo"<td>$zc</td>";echo"<td".(!$zc?" colspan='2'":"").' lang="en" title="'.h($Ib).'">'.h($Ii)."</td>";$p=0;foreach($te
as$mh=>$re){$A="'grants[$p][".h(strtoupper($Ii))."]'";$Y=$re[strtoupper($Ii)];$Ni=strpos($mh,"@")!==false;$ah=$mh==".*";$Ba=$Ii=="All privileges";$se=$Ii=="Grant option";if($mh=="*.*"&&$Ii=="Proxy")echo"<td></td>";elseif($Ni&&$Ii!="Proxy"&&!$se)echo"<td></td>";elseif($Wb=="Server Admin"&&$mh!=(isset($te["*.*"])?"*.*":".*")&&!(($Ni||$ah)&&$Ii=="Proxy"))echo"<td></td>";elseif(isset($_GET["grant"]))echo"<td><select name=$A>"."<option></option>"."<option value='1'".($Y?" selected":"").">".lang(269)."</option>"."<option value='0'".($Y=="0"?" selected":"").">".lang(270)."</option>"."</select></td>";else{echo"<td class='center'><label class='block'>","<input type='checkbox' name=$A value='1'".($Y?" checked":"").($Ba?" id='grants-$p-all'":(!$se?" class='grants-$p'":"")).">";if($Ba)echo
script("qsl('input').onclick = function () { if (this.checked) formUncheckAll('.grants-$p'); };");elseif(!$se)echo
script("qsl('input').onclick = function () { if (this.checked) formUncheck('grants-$p-all'); };");echo"</label>";}$p++;}echo"</tr>";}}echo"</table></div>\n","<p>","<input type='submit' class='button default' value='",lang(112),"'>\n";if(isset($_GET["host"]))echo"<input type='submit' class='button' name='drop' value='",lang(159),"'>\n",confirm(lang(210,"$pa@$_GET[host]"));echo
input_token(),"</p>\n","</form>\n";}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST){$Hf=0;foreach((array)$_POST["kill"]as$X){if(kill_process($X))$Hf++;}queries_redirect(ME."processlist=",lang(271,$Hf),$Hf||!$_POST["kill"]);}}page_header(lang(149),[lang(149)]);echo"<form action='' method='post'>\n","<div class='scrollable'>\n","<table class='nowrap checkable'>\n";$p=-1;foreach(process_list()as$p=>$K){if(!$p){echo"<thead><tr lang='en'>".(support("kill")?"<th>":"");foreach($K
as$t=>$X)echo"<th>$t".doc_link(['sql'=>"show-processlist.html#processlist_".strtolower($t),'mariadb'=>"reference/sql-statements/administrative-sql-statements/show/show-processlist",]);echo"</thead>\n","<tbody>\n";}echo"<tr>".(support("kill")?"<td>".checkbox("kill[]",$K[DIALECT=="sql"?"Id":"pid"],0):"");foreach($K
as$t=>$X)echo"<td>".($X!=""&&((DIALECT=="sql"&&$t=="Info"&&preg_match("~Query|Killed~",$K["Command"]))||(DIALECT=="pgsql"&&$t=="query")||(DIALECT=="oracle"&&$t=="sql_text"))?"<code class='jush-".DIALECT."'>".truncate_utf8($X,100).'</code> <a href="'.h(ME.($K["db"]!=""?"db=".urlencode($K["db"])."&":"")."sql=".urlencode($X)).'">'.icon("edit").lang(272).'</a>':h($X));echo"\n";}if($p>=0)echo"</tbody>\n",script("mixin(qsl('tbody'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");echo"</table>\n","</div>\n","<p>";if(support("kill"))echo($p+1)."/".lang(273,max_connections()),"<p><input type='submit' class='button' value='".lang(274)."'>\n";echo
input_token(),"</p>\n","</form>\n",script("tableCheck();");}elseif(isset($_GET["select"])){$a=$_GET["select"];$R=table_status1($a);$s=indexes($a);$k=fields($a);$be=column_foreign_keys($a);$oh=$R["Oid"];$qj=[];$c=[];$Ij=[];$Hh=[];$jl=null;foreach($k
as$t=>$j){$A=Admin::get()->getFieldName($j);$Wg=html_entity_decode(strip_tags($A),ENT_QUOTES);if(isset($j["privileges"]["select"])&&$A!=""){$c[$t]=$Wg;if(is_shortable($j))$jl=Admin::get()->processSelectionLength();}if(isset($j["privileges"]["where"])&&$A!="")$Ij[$t]=$Wg;if(isset($j["privileges"]["order"])&&$A!="")$Hh[$t]=$Wg;$qj+=$j["privileges"];}list($M,$ue)=Admin::get()->processSelectionColumns($c,$s);$M=array_unique($M);$ue=array_unique($ue);$rf=count($ue)<count($M);$Z=Admin::get()->processSelectionSearch($k,$s);$D=Admin::get()->processSelectionOrder($k,$s);$v=Admin::get()->processSelectionLimit();if($_GET["modify"]&&!Admin::get()->isDataEditAllowed())redirect(ME."select=".urlencode($a));if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$Ml=>$K){$Ja=convert_field($k[key($K)]);$M=[$Ja?:idf_escape(key($K))];$Z[]=where_check($Ml,$k);$J=Driver::get()->select($a,$M,$Z,$M);if($J)echo
first($J->fetchRow());}exit;}$Fi=$Pl=[];foreach($s
as$r){if($r["type"]=="PRIMARY"){$Fi=array_flip($r["columns"]);$Pl=($M?$Fi:[]);foreach($Pl
as$t=>$X){if(in_array(idf_escape($t),$M))unset($Pl[$t]);}break;}}if($oh&&!$Fi){$Fi=$Pl=[$oh=>0];$s[]=["type"=>"PRIMARY","columns"=>[$oh]];}$P=Admin::get()->getSettings();if($_POST){$xm=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$sb=[];foreach($_POST["check"]as$ob)$sb[]=where_check($ob,$k);$xm[]="((".implode(") OR (",$sb)."))";}$xm=($xm?"\nWHERE ".implode(" AND ",$xm):"");if($_POST["export"]){$P->updateParameters(["exportFormat"=>$_POST["format"],"exportOutput"=>$_POST["output"],]);dump_headers($a);Admin::get()->dumpTable($a,"");$je=($M?implode(", ",$M):"*").convert_fields($c,$k,$M)."\nFROM ".table($a);$xe=($ue&&$rf?"\nGROUP BY ".implode(", ",$ue):"").($D?"\nORDER BY ".implode(", ",$D):"");if(!is_array($_POST["check"])||$Fi)$H="SELECT $je$xm$xe";else{$Jl=[];foreach($_POST["check"]as$X)$Jl[]="(SELECT".limit($je,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$k).$xe,1).")";$H=implode(" UNION ALL ",$Jl);}Admin::get()->dumpData($a,"table",$H);exit;}if($_POST["save"]||$_POST["delete"]){$I=true;$ya=0;$O=[];if(!$_POST["delete"]){$Qj=array_keys($_POST["fields"]+$_POST["function"]);foreach($Qj
as$A){$X=process_input($k[$A]);if($X!==null&&($_POST["clone"]||$X!==false))$O[idf_escape($A)]=($X!==false?$X:idf_escape($A));}}if($_POST["delete"]||$O){if($_POST["clone"])$H="INTO ".table($a)." (".implode(", ",array_keys($O)).")\nSELECT ".implode(", ",$O)."\nFROM ".table($a);if($_POST["all"]||($Fi&&is_array($_POST["check"]))||$rf){$I=($_POST["delete"]?Driver::get()->delete($a,$xm):($_POST["clone"]?queries("INSERT $H$xm".Driver::get()->getInsertReturningSql($a)):Driver::get()->update($a,$O,$xm)));$ya=Connection::get()->getAffectedRows();if(is_object($I))$ya+=$I->getRowsCount();}else{foreach((array)$_POST["check"]as$X){$wm="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$k);$I=($_POST["delete"]?Driver::get()->delete($a,$wm,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$wm)):Driver::get()->update($a,$O,$wm,1)));if(!$I)break;$ya+=Connection::get()->getAffectedRows();}}}$_=lang(275,$ya);if($_POST["clone"]&&$I&&$ya==1){$Qf=last_id($I);if($Qf)$_=lang(204," $Qf");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page":""),$_,(bool)$I);if(!$_POST["delete"]){$bd=array_filter($k,function($j){return!(isset($j["generated"])?$j["generated"]:null);});edit_form($a,$bd,(array)$_POST["fields"],!$_POST["clone"]);page_footer();exit;}}elseif(!$_POST["import"]){if(!$_POST["val"])Admin::get()->addError(lang(276));else{$Bk=true;$ya=0;foreach($_POST["val"]as$Ml=>$K){$O=[];foreach($K
as$t=>$X){$t=bracket_escape($t,true);$O[idf_escape($t)]=(preg_match('~char|text~',$k[$t]["type"])||$X!=""?Admin::get()->processFieldInput($k[$t],$X):"NULL");}$Bk=(bool)Driver::get()->update($a,$O," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($Ml,$k),($rf||$Fi?0:1)," ");if(!$Bk)break;$ya+=Connection::get()->getAffectedRows();}queries_redirect(remove_from_uri(),lang(275,$ya),$Bk);}}elseif(!is_string($l=get_file("csv_file",true)))Admin::get()->addError(upload_error($l));elseif(!preg_match('~~u',$l))Admin::get()->addError(lang(277));else{$P->updateParameter("exportFormat",$_POST["import_format"]);$Db=array_keys($k);preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$l,$z);$ya=count($z[0]);Driver::get()->begin();$Rj=($_POST["import_format"]=="csv;"?";":($_POST["import_format"]=="tsv"?"\t":","));$L=[];foreach($z[0]as$t=>$X){preg_match_all("~((?>\"[^\"]*\")+|[^$Rj]*)$Rj~",$X.$Rj,$pg);if(!$t&&!array_diff($pg[1],$Db)){$Db=$pg[1];$ya--;}else{$O=[];foreach($pg[1]as$p=>$yb)$O[idf_escape($Db[$p])]=($yb==""&&$k[$Db[$p]]["null"]?"NULL":q(preg_match('~^".*"$~s',$yb)?str_replace('""','"',substr($yb,1,-1)):$yb));$L[]=$O;}}$Bk=!$L||Driver::get()->insertUpdate($a,$L,$Fi);if($Bk)Driver::get()->commit();queries_redirect(remove_from_uri("page"),lang(278,$ya),$Bk);Driver::get()->rollback();}}$Rk=Admin::get()->getTableName($R);if(is_ajax()){page_headers();ob_start();}else
page_header(lang(55).": $Rk",[$Rk]);$O=null;if(isset($qj["insert"])||!support("table")){$Zh=[];foreach((array)$_GET["where"]as$X){if(isset($be[$X["col"]])&&count($be[$X["col"]])==1&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$X["val"])))))$Zh["set"."[".bracket_escape($X["col"])."]"]=$X["val"];}$O=$Zh?"&".http_build_query($Zh):"";}Admin::get()->printTableMenu($R,$O);if(!$c&&support("table"))echo"<p class='error'>".lang(279).($k?".":": ".error())."\n";else{echo"<form id='form' action=''>\n","<div style='display: none;'>";hidden_fields_get();if(DB!=""){echo
input_hidden("db",DB);if(isset($_GET["ns"]))echo
input_hidden("ns",$_GET["ns"]);}echo
input_hidden("select",$a),'<input type="submit" class="button" value="'.h(lang(55)).'">',"</div>\n","<div class='field-sets'>\n";Admin::get()->printSelectionColumns($M,$c);Admin::get()->printSelectionSearch($Z,$Ij,$s);Admin::get()->printSelectionOrder($D,$Hh,$s);Admin::get()->printSelectionLimit($v);Admin::get()->printSelectionLength($jl);Admin::get()->printSelectionAction($s);echo"</div>\n</form>\n";$E=isset($_GET["page"])?$_GET["page"]:null;if($E=="last"){$he=Connection::get()->getValue(count_rows($a,$Z,$rf,$ue));$E=(int)floor(max(0,intval($he)-1)/$v);}else{$he=false;$E=(int)$E;}$Jj=$M;$ve=$ue;if(!$Jj){$Jj[]="*";$Xb=convert_fields($c,$k,$M);if($Xb)$Jj[]=substr($Xb,2);}foreach($M
as$t=>$X){$j=$k[idf_unescape($X)];if($j&&($Ja=convert_field($j)))$Jj[$t]="$Ja AS $X";}if(!$rf&&$Pl){foreach($Pl
as$t=>$X){$Jj[]=idf_escape($t);if($ve)$ve[]=idf_escape($t);}}$I=Driver::get()->select($a,$Jj,$Z,$ve,$D,$v,$E,true);if(!$I)echo"<p class='error'>".error()."\n";else{if(DIALECT=="mssql"&&$E)$I->seek($v*$E);echo"<form id='selection_form' action='' method='post' enctype='multipart/form-data'>\n","<div class='table-footer-parent'>\n";$L=[];while($K=$I->fetchAssoc()){if($E&&DIALECT=="oracle")unset($K["RNUM"]);$L[]=$K;}if($_GET["page"]!="last"&&$v&&$ue&&$rf&&DIALECT=="sql")$he=Connection::get()->getValue(" SELECT FOUND_ROWS()");$cd=false;if(!$L)echo"<p class='message'>".lang(88)."\n";else{$Ta=Admin::get()->getBackwardKeys($a,$Rk);echo"<div class='scrollable'>\n","<table id='table' class='nowrap checkable'>\n","<thead><tr>";if($ue||!$M){echo"<th class='actions'><input type='checkbox' id='all-page' class='jsonly'>".script("gid('all-page').onclick = partial(formCheck, /check/);","");if(Admin::get()->isDataEditAllowed())echo" <a href='",h($_GET["modify"]?remove_from_uri("modify"):$_SERVER["REQUEST_URI"]."&modify=1")."' title='",lang(280),"'>",icon_solo("edit-all"),"</a>";}$Xg=[];$me=[];reset($M);$Vi=1;foreach($L[0]as$t=>$X){if(!isset($Pl[$t])){$Lj=key($M);$X=isset($_GET["columns"][$Lj])?$_GET["columns"][$Lj]:[];$j=$k[$M?($X?$X["col"]:current($M)):$t];$A=($j?Admin::get()->getFieldName($j,$Vi):(isset($X["fun"])?"*":h($t)));if($A!=""){$Vi++;$Xg[$t]=$A;$b=idf_escape($t);$Oe=remove_from_uri('(order|desc)[^=]*|page').'&order%5B0%5D='.urlencode($t);$zc="&desc%5B0%5D=1";echo"<th id='th[".h(bracket_escape($t))."]'>".script("mixin(qsl('th'), {onmouseover: partial(columnMouse), onmouseout: partial(columnMouse, ' hidden')});","");$le=apply_sql_function(isset($X["fun"])?$X["fun"]:null,$A);$kk=isset($j["privileges"]["order"])||(isset($X["fun"])?$X["fun"]:null);if($kk)echo'<a href="',h($Oe.($D[0]==$b||$D[0]==$t?$zc:'')),'">',"$le</a>";else
echo$le;echo"<span class='column hidden'>";if($kk)echo"<a href='".h($Oe.$zc)."' title='".lang(62)."' class='button light'>",icon_solo("arrow-down"),"</a>";if(!isset($X["fun"])&&isset($j["privileges"]["where"]))echo'<a href="#fieldset-search" title="'.lang(59).'" class="button light jsonly">',icon_solo("search"),'</a>',script("qsl('a').onclick = partial(selectSearch, '".js_escape($t)."');");echo"</span>";}$me[$t]=isset($X["fun"])?$X["fun"]:null;next($M);}}$Yf=[];if($_GET["modify"]){foreach($L
as$K){foreach($K
as$t=>$X)$Yf[$t]=max($Yf[$t],min(40,strlen(utf8_decode($X))));}}if($Ta)echo"<th>".lang(17)."</th>";echo"</thead>\n","<tbody>\n";if(is_ajax())ob_end_clean();foreach(Admin::get()->fillForeignDescriptions($L,$be)as$Ug=>$K){$Ll=unique_array($L[$Ug],$s);if(!$Ll){$Ll=[];reset($M);foreach($L[$Ug]as$t=>$X){if(!preg_match('~^(COUNT|AVG|GROUP_CONCAT|MAX|MIN|SUM)\(~',current($M)))$Ll[$t]=$X;next($M);}}$Ml="";foreach($Ll
as$t=>$X){$j=isset($k[$t])?$k[$t]:null;if((DIALECT=="sql"||DIALECT=="pgsql")&&$j&&preg_match('~char|text|enum|set~',$j["type"])&&strlen($X)>64){$t=(strpos($t,'(')?$t:idf_escape($t));$t="MD5(".(DIALECT!='sql'||preg_match("~^utf8~",isset($j["collation"])?$j["collation"]:"")?$t:"CONVERT($t USING ".charset(Connection::get()).")").")";$X=md5($X);}$Ml
.="&".($X!==null?urlencode("where[".bracket_escape($t)."]")."=".urlencode($X===false?"f":$X):"null%5B%5D=".urlencode($t));}echo"<tr>";if($ue||!$M){echo"<td class='actions'>",checkbox("check[]",substr($Ml,1),in_array(substr($Ml,1),(array)$_POST["check"]));if(!$rf&&Admin::get()->isDataEditAllowed())echo" <a href='",h(ME."edit=".urlencode($a).$Ml),"' class='edit' title='",lang(38),"'>",icon_solo("edit"),"</a>";}reset($M);foreach($K
as$t=>$X){if(isset($Xg[$t])){$b=current($M);$j=isset($k[$t])?$k[$t]:null;$w="";if($j&&is_blob($j)&&$X!="")$w=ME.'download='.urlencode($a).'&field='.urlencode($t).$Ml;if(!$w&&$X!==null){foreach((array)$be[$t]as$n){if(count($be[$t])==1||end($n["source"])==$t){$w="";foreach($n["source"]as$p=>$lk)$w
.=where_link($p,$n["target"][$p],$L[$Ug][$lk]);$w=($n["db"]!=""?preg_replace('~([?&]db=)[^&]+~','\1'.urlencode($n["db"]),ME):ME).'select='.urlencode($n["table"]).$w;if($n["ns"])$w=preg_replace('~([?&]ns=)[^&]+~','\1'.urlencode($n["ns"]),$w);if(count($n["source"])==1)break;}}}if($b=="COUNT(*)"){$w=ME."select=".urlencode($a);$p=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$Ll))$w
.=where_link($p++,$W["col"],$W["val"],$W["op"]);}foreach($Ll
as$_f=>$W)$w
.=where_link($p++,$_f,$W);}$jh=$X===null;$Pe=select_value($X,$w,$j,$jl);$pd=bracket_escape($t);$q=h("val[$Ml][$pd]");$Ai=isset($_POST["val"][$Ml][$pd])?$_POST["val"][$Ml][$pd]:null;$Rl=isset($j["privileges"]["update"])?$j["privileges"]["update"]:false;$ad=!is_array($K[$t])&&is_utf8($Pe)&&$L[$Ug][$t]==$K[$t]&&!$me[$t]&&!(isset($j["generated"])?$j["generated"]:false);$U=($b&&preg_match('~^(AVG|MIN|MAX)\((.+)\)~',$b,$z)?$k[idf_unescape($z[2])]["type"]:(isset($j["type"])?$j["type"]:null));$Og=$U=="money"||($b&&preg_match('~^SUM\((.+)\)~',$b,$z)&&$k[idf_unescape($z[1])]["type"])=="money";$hl=$U&&preg_match('~text|json|lob~',$U);$lh=($U&&preg_match(number_type(),$U))||($b&&preg_match('~^(CHAR_LENGTH|ROUND|FLOOR|CEIL|UNIX_TIMESTAMP|TIME_TO_SEC|COUNT|SUM)\(~',$b));$wb=$lh&&($jh||is_numeric(strip_tags($Pe))||$Og)?"class='number'":"";echo"<td id='$q' $wb";if(($_GET["modify"]&&$ad&&!$jh)||$Ai!==null){$cd=true;$_e=h($Ai!==null?$Ai:$K[$t]);echo" data-editing='true'>".($hl?"<textarea name='$q' cols='30' rows='".(substr_count($K[$t],"\n")+1)."'>$_e</textarea>":"<input class='input' name='$q' value='$_e' size='$Yf[$t]'>");}else{$mg=strpos($Pe,"<i>…</i>");if($Rl)echo" data-text='".($mg?2:($hl?1:0))."'".($ad?"":" data-warning='".h(lang(281))."'");echo">$Pe";}}next($M);}if($Ta){echo"<td>";Admin::get()->printBackwardKeys($Ta,$L[$Ug]);echo"</td>";}echo"</tr>\n";}if(is_ajax())exit;echo"</tbody>\n",script("mixin(qs('#table tbody'), {onclick: partialArg(tableClick, false, ".(Admin::get()->isDataEditAllowed()?"true":"false")."), ondblclick: partialArg(tableClick, true), onkeydown: onEditingKeydown});"),"</table>\n",script("initToggles(gid('table'));"),"</div>\n";}if(!is_ajax()){if($L||$E){$rd=true;if($_GET["page"]!="last"){if(!$v||(count($L)<$v&&($L||!$E)))$he=($E?$E*$v:0)+count($L);elseif(DIALECT!="sql"||!$rf){$he=($rf?false:found_rows($R,$Z));if($he<max(1e4,2*($E+1)*$v))$he=first(slow_query(count_rows($a,$Z,$rf,$ue)));elseif(DIALECT=='sql'||DIALECT=='pgsql')$rd=false;}}$Xh=($v!==null&&($he===false||$he>$v||$E));if($Xh){if(($he===false?count($L)+1:$he-$E*$v)>$v)echo'<p class="links">','<a href="',h(remove_from_uri("page")."&page=".($E+1)),'" class="loadmore">',icon("expand"),lang(282),'</a>',script("qsl('a').onclick = partial(loadNextPage, $v, '".lang(283)."…');","");echo"\n";}echo"<div class='table-footer'><div class='field-sets'>\n";if($Xh){$tg=($he===false?$E+(count($L)>=$v?2:1):(int)floor(($he-1)/$v));$Nc="<li>…</li>";echo"<fieldset>";if(DIALECT!="simpledb"){echo"<legend><a href='".h(remove_from_uri("page"))."'>".lang(284)."</a></legend>",script("qsl('a').onclick = function () { pageClick(this.href, +prompt('".lang(284)."', '".($E+1)."')); return false; };"),"<div id='fieldset-pagination' class='fieldset-content'><ul class='pagination'>",pagination(0,$E);if($E>5)echo$Nc;for($p=max(1,$E-4);$p<min($tg,$E+5);$p++)echo
pagination($p,$E);if($tg>0){if($E+5<$tg)echo$Nc;echo($rd&&$he!==false?pagination($tg,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$tg'>".lang(285)."</a>");}echo"</ul></div>";}else{echo"<legend>".lang(284)."</legend>","<div id='fieldset-pagination'><ul class='pagination'>",pagination(0,$E);if($E>1)echo$Nc;if($E)echo
pagination($E,$E);if($tg>$E){echo
pagination($E+1,$E);if($tg>$E+1)echo$Nc;}echo"</ul></div>";}echo"</fieldset>\n";}echo"<fieldset>","<legend>".lang(286)."</legend><div class='fieldset-content'>";$Gc=($rd?"":"~ ").$he;echo
checkbox("all",1,0,($he!==false?($rd?"":"~ ").lang(186,$he):""),"const checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$Gc' : checked); selectCount('selected2', this.checked || !checked ? '$Gc' : checked);")."\n","</div></fieldset>\n";if(Admin::get()->isDataEditAllowed()){echo"<fieldset",($_GET["modify"]?'':' class="jsonly"'),">","<legend>",lang(280),"</legend>";$yj=($_GET["modify"]?"":" data-inline-edit='1'".($cd?"":" disabled"));echo"<div class='fieldset-content'",($_GET["modify"]?"":" title='".lang(276)."'"),">","<input type='submit' class='button' id='modify-save' value='",lang(112),"'",$yj,">","</div>","</fieldset>\n","<fieldset>","<legend>",lang(158)," <span id='selected'></span></legend>","<div class='fieldset-content'>","<input type='submit' class='button' name='edit' value='",lang(38),"'> ","<input type='submit' class='button' name='clone' value='",lang(272),"'> ","<input type='submit' class='button' name='delete' value='",lang(116),"'>",confirm(),"</div>","</fieldset>\n";}$de=Admin::get()->getDumpFormats();foreach((array)$_GET["columns"]as$b){if($b["fun"]){unset($de['sql']);break;}}if($de){print_fieldset_start("export",lang(74)." <span id='selected2'></span>","export");echo
html_select("format",$de,$P->getParameter("exportFormat"));$Uh=Admin::get()->getDumpOutputs();echo($Uh?" ".html_select("output",$Uh,$P->getParameter("exportOutput")):"")," <input type='submit' class='button' name='export' value='".lang(74)."'>\n";print_fieldset_end("export");}echo"</div></div>\n",script("initTableFooter()");}echo"</div>\n";if(Admin::get()->isDataEditAllowed()){echo"<p>","<a href='#import'>",icon("import"),lang(73),"</a>",script("qsl('a').onclick = partial(toggle, 'import');",""),"</p>","<p id='import'",($_POST["import"]?"":" class='hidden'"),">";if(ini_bool("file_uploads"))echo"<input type='file' name='csv_file'> ",html_select("import_format",["csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"],$P->getParameter("exportFormat"))," <input type='submit' class='button default' name='import' value='".lang(73)."'>",file_upload_form_script("selection_form","csv_file");else
echo
lang(193);echo"</p>";}echo
input_token(),"</form>\n",(!$ue&&$M?"":script("tableCheck();"));}else
echo"</div>\n";}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$uk=isset($_GET["status"]);$T=$uk?lang(151):lang(150);page_header($T,[$T]);$gm=($uk?Admin::get()->getStatusVariables():Admin::get()->getServerVariables());if(!$gm)echo"<p class='message'>",lang(88),"</p>\n";else{echo"<div class='scrollable'><table>\n";foreach($gm
as$K){echo"<tr>";$t=array_shift($K);echo"<th><code class='jush-".DIALECT.($uk?"status":"set")."'>".h($t)."</code></th>";foreach($K
as$X)echo"<td>",nl2br(h($X)),"</td>";echo"</tr>\n";}echo"</table></div>\n";}}elseif(isset($_GET["script"])){header("Content-Type: text/javascript; charset=utf-8");if($_GET["script"]=="db"){$Ek=["Data_length"=>0,"Index_length"=>0,"Data_free"=>0];$e=[];$nc=null;foreach(table_status()as$A=>$R){$e["Comment-$A"]=h($R["Comment"]);if(!is_view($R)||preg_match('~materialized~i',$R["Engine"])){$e["Engine-$A"]=h($R["Engine"]);$_b=isset($R["Collation"])?$R["Collation"]:"";if($_b==""){if($nc===null)$nc=db_collation(DB,collations())??"";$_b=$nc;}$e["Collation-$A"]=h($_b);foreach($Ek+["Auto_increment"=>0,"Rows"=>0]as$t=>$X){if($R[$t]!=""){$X=format_number($R[$t]);if($X>=0)$e["$t-$A"]=($t=="Rows"&&$X&&$R["Engine"]==(DIALECT=="pgsql"?"table":"InnoDB")?"~ $X":$X);if(isset($Ek[$t]))$Ek[$t]+=($R["Engine"]!="InnoDB"||$t!="Data_free"?$R[$t]:0);}elseif(array_key_exists($t,$R))$e["$t-$A"]="?";}}}foreach($Ek
as$t=>$X)$e["sum-$t"]=format_number($X);echo
json_encode($e,JSON_UNESCAPED_UNICODE);}elseif($_GET["script"]=="kill")Connection::get()->query("KILL ".number($_POST["kill"]));else{$e=[];foreach(count_tables(Admin::get()->getDatabases())as$g=>$X){$e["tables-$g"]=$X;$e["size-$g"]=db_size($g);}echo
json_encode($e,JSON_UNESCAPED_UNICODE);}exit;}else{$al=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($al&&!$_POST["search"]){$I=true;$_="";if(DIALECT=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"]);$_=lang(287);}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$_=lang(288);}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$_=lang(289);}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$_=lang(290);}elseif(DIALECT=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$Q){foreach(get_rows("PRAGMA integrity_check(".q($Q).")")as$K)$_
.="<b>".h($Q)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(DIALECT!="sql"){$I=(DIALECT=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),$_POST["tables"]));$_=lang(291);}elseif(!$_POST["tables"])$_=lang(78);elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('AdminNeo\idf_escape',$_POST["tables"])))){while($K=$I->fetchAssoc())$_
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect($_SERVER["REQUEST_URI"],$_,(bool)$I);}if($_GET["ns"]=="")page_header(lang(30).": ".h(DB),true);else
page_header(lang(181).": ".h($_GET["ns"]),true);Admin::get()->printDatabaseMenu();if($_GET["ns"]===""){echo"<h2 id='schemas'>".lang(292)."</h2>\n";$Ej=Admin::get()->getSchemas();if(!$Ej)echo"<p class='message'>".lang(293)."\n";else{echo"<div class='scrollable'>\n","<table class='nowrap'>\n",'<thead><tr class="wrap"><th>',lang(181),"</th></tr></thead>";foreach($Ej
as$A)echo"<tr><th><a href='",h(ME),"ns=".urlencode($A),"' title='",lang(294),"'>".h($A)."</a></th></tr>";echo'</table></div>';}echo'<p class="links"><a href="'.h(ME).'scheme=">'.icon("database-add").lang(76)."</a>\n";}else{echo"<h2 id='tables-views'>".lang(295)."</h2>\n";$Vk=['sql'=>'show-table-status.html','mariadb'=>'reference/sql-statements/administrative-sql-statements/show/show-table-status'];$nc=db_collation(DB,collations());$c=["Engine"=>["label"=>lang(162),"doc"=>doc_link(['sql'=>'storage-engines.html','mariadb'=>'server-usage/storage-engines']),],];if($nc!="")$c["Collation"]=["label"=>lang(45),"doc"=>doc_link(['sql'=>'charset-charsets.html','mariadb'=>'reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations']),];$c+=["Data_length"=>["label"=>lang(296),"doc"=>doc_link($Vk+['pgsql'=>'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT','oracle'=>'REFRN20286']),"link"=>"create","title"=>lang(35),],"Index_length"=>["label"=>lang(297),"doc"=>doc_link($Vk+['pgsql'=>'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT']),"link"=>"indexes","title"=>lang(166),],"Data_free"=>["label"=>lang(298),"doc"=>doc_link($Vk),"link"=>"edit","title"=>lang(7),],"Auto_increment"=>["label"=>lang(47),"doc"=>doc_link(['sql'=>'example-auto-increment.html','mariadb'=>'reference/data-types/auto_increment']),"link"=>"auto_increment=1&create","title"=>lang(35),],"Rows"=>["label"=>lang(299),"doc"=>doc_link($Vk+['pgsql'=>'catalog-pg-class.html#CATALOG-PG-CLASS','oracle'=>'REFRN20286']),"link"=>"select","title"=>lang(33),],];if(support("comment"))$c["Comment"]=["label"=>lang(46),"doc"=>doc_link($Vk+['pgsql'=>'functions-info.html#FUNCTIONS-INFO-COMMENT-TABLE']),];$D=(is_string($_GET["order"])?$_GET["order"]:"");$_c=null;if(preg_match('~^(.+)-(asc|desc)$~',$D,$y)){$D=$y[1];$_c=($y[2]=="desc");}if($D!="__table"&&!isset($c[$D]))$D="";if($_c===null)$_c=isset($c[$D]["link"]);$zm=($D!=""&&$D!="__table");$Yk=($zm?table_status():tables_list());if(!$Yk)echo"<p class='message'>".lang(78)."\n";else{echo"<form action='' method='post'>\n","<div class='table-footer-parent'>\n";if(support("table")){echo"<div class='field-sets'>\n","<fieldset><legend>".lang(300)." <span id='selected2'></span></legend><div class='fieldset-content'>",html_select("op",Admin::get()->getOperators(),isset($_POST["op"])?$_POST["op"]:Driver::get()->getLikeOperator()),"<input type='search' class='input' name='query' value='".h($_POST["query"])."'>",script("qsl('input').onkeydown = partialArg(bodyKeydown, 'search');","")," <input type='submit' class='button' name='search' value='".lang(59)."'>\n","</div></fieldset>\n","</div>\n";if($_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable'>\n",'<thead><tr class="wrap">','<td class="actions"><input id="check-all" type="checkbox" class="input jsonly">'.script("gid('check-all').onclick = partial(formCheck, /^(tables|views)\[/);","");$Vg=($D==""||$D=="__table");$Qk=($Vg&&!$_c?ME."order=__table-desc":substr(ME,0,-1));echo'<th><a href="'.h($Qk).'">'.lang(8).'</a>';foreach($c
as$t=>$b){$Bc=($t===$D?!$_c:isset($b["link"]));echo'<td><a href="'.h(ME)."order=$t-".($Bc?"desc":"asc").'">'.$b["label"].'</a>'.$b["doc"];}echo"</thead>\n","<tbody>\n";if($D=="__table"){if($_c)$Yk=array_reverse($Yk,true);}elseif($D){uasort($Yk,function($ra,$Qa)use($D,$_c){$_m=isset($ra[$D])?$ra[$D]:null;$Am=isset($Qa[$D])?$Qa[$D]:null;$I=($_m<$Am?-1:($_m>$Am?1:0));return($_c?-$I:$I);});}$Ek=["Data_length"=>0,"Index_length"=>0,"Data_free"=>0];$S=0;foreach($Yk
as$A=>$uk){$km=($zm?is_view($uk):$uk!==null&&!preg_match('~table|sequence~i',$uk));$id=($zm?(isset($uk["Engine"])?$uk["Engine"]:""):$uk);$q=h("Table-".$A);echo'<tr><td class="actions">'.checkbox(($km?"views[]":"tables[]"),$A,in_array("$A",$al,true),"","","",$q);if(!Admin::get()->getSettings()->isSelectionPreferred()&&(support("table")||support("indexes")))$ta="table";else$ta="select";echo"<th><a href='",h(ME),"$ta=",urlencode($A),"' id='$q'>",h($A),"</a></th>";if($km&&!preg_match('~materialized~i',$id)){$T=lang(161);$Eb=count($c)-(support("comment")?2:1);echo'<td colspan="'.$Eb.'">'.(support("view")?"<a href='".h(ME)."view=".urlencode($A)."' title='".lang(36)."'>$T</a>":$T),'<td align="right"><a href="'.h(ME)."select=".urlencode($A).'" title="'.lang(33).'">?</a>';}else{foreach($c
as$t=>$b){if($t=="Comment")continue;$q=" id='$t-".h($A)."'";$w=isset($b["link"])?$b["link"]:"";if(!$w){$X="";if($zm){$X=isset($uk[$t])?$uk[$t]:"";if($t=="Collation"&&$X=="")$X=$nc;}echo"<td$q>".h($X);continue;}$X="?";if($zm){$B=isset($uk[$t])?$uk[$t]:"";if(is_numeric($B)&&$B>=0){$X=($t=="Rows"&&$B&&$id==(DIALECT=="pgsql"?"table":"InnoDB")?"~ ":"").format_number($B);if(isset($Ek[$t])&&($id!="InnoDB"||$t!="Data_free"))$Ek[$t]+=$B;}}echo"<td align='right'>".(support("table")||$t=="Rows"||(support("indexes")&&$t!="Data_length")?"<a href='".h(ME."$w=").urlencode($A)."'$q title='".$b["title"]."'>$X</a>":"<span$q>$X</span>");}$S++;}echo(support("comment")?"<td id='Comment-".h($A)."'>".($zm?h(isset($uk["Comment"])?$uk["Comment"]:""):""):""),"\n";}echo"</tbody>\n",script("mixin(qsl('tbody'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});"),"<tfoot><tr>","<td><th>".lang(273,count($Yk)),"<td>".h(DIALECT=="sql"?Connection::get()->getValue("SELECT @@default_storage_engine"):""),($nc!=""?"<td>".h($nc):"");foreach($Ek
as$t=>$Dk)echo"<td align='right' id='sum-$t'>".($zm?format_number($Dk):"");echo"<td></td><td></td>";if(support("comment"))echo"<td></td>";echo"</tr></tfoot>\n","</table>\n","</div>\n",($zm?"":script("ajaxSetHtml('".js_escape(ME)."script=db');"));if(Admin::get()->isDataEditAllowed()){echo"<div class='table-footer'><div class='field-sets'>\n";$dm="<input type='submit' class='button' value='".lang(301)."'> ".help_script("VACUUM");$Dh="<input type='submit' class='button' name='optimize' value='".lang(302)."'> ".help_script(DIALECT=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE");echo"<fieldset><legend>".lang(158)." <span id='selected'></span></legend><div class='fieldset-content'>".(DIALECT=="sqlite"?$dm."<input type='submit' class='button' name='check' value='".lang(303)."'> ".help_script("PRAGMA integrity_check"):(DIALECT=="pgsql"?$dm.$Dh:(DIALECT=="sql"?"<input type='submit' class='button' value='".lang(304)."'> ".help_script("ANALYZE TABLE").$Dh."<input type='submit' class='button' name='check' value='".lang(303)."'> ".help_script("CHECK TABLE")."<input type='submit' class='button' name='repair' value='".lang(305)."'> ".help_script("REPAIR TABLE"):"")))."<input type='submit' class='button' name='truncate' value='".lang(306)."'> ".help_script(DIALECT=="sqlite"?"DELETE":("TRUNCATE".(DIALECT=="pgsql"?"":" TABLE"))).confirm()."<input type='submit' class='button' name='drop' value='".lang(159)."'>".help_script("DROP TABLE").confirm()."\n";$f=(support("scheme")?Admin::get()->getSchemas():Admin::get()->getDatabases());echo"</div></fieldset>\n";$Gj="";if(count($f)!=1&&DIALECT!="sqlite"){echo"<fieldset><legend>".lang(307)." <span id='selected3'></span></legend><div>";$g=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($f?html_select("target",$f,$g,"","label-move"):'<input class="input" name="target" value="'.h($g).'" autocapitalize="off">')," <input type='submit' class='button' name='move' value='".lang(308)."'>",(support("copy")?" <input type='submit' class='button' name='copy' value='".lang(309)."'> ".checkbox("overwrite",1,$_POST["overwrite"],lang(310)):""),"</div></fieldset>\n";$Gj=" selectCount('selected3', formChecked(this, /^(tables|views)\[/));";}echo
input_hidden("all"),script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^(tables|views)\[/));".(support("table")?" selectCount('selected2', formChecked(this, /^tables\[/) || $S);":"")."$Gj }"),input_token(),"</div></div>\n",script("initTableFooter()");}echo"</div>\n","</form>\n",script("tableCheck();");}echo'<p class="links"><a href="',h(ME),'create=">',icon("table-add"),lang(77),"</a>\n";if(support("view"))echo'<a href="',h(ME),'view=">',icon("view-add"),lang(235),"</a>\n";if(support("routine")){echo"<h2 id='routines'>".lang(177)."</h2>\n";$uj=routines();if($uj){$Kb=$uj[0]["ROUTINE_COMMENT"]!==null;echo"<table>\n",'<thead><tr>','<th>',lang(216),'</th><td>',lang(44),'</td><td>',lang(252),"</td>";if($Kb)echo"<td>",lang(46),"</td>";echo"<td></td>","</tr></thead>\n";foreach($uj
as$K){$A=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".urlencode($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="',h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').urlencode($K["SPECIFIC_NAME"]).$A),'">',h($K["ROUTINE_NAME"]),'</a></th>','<td>',h($K["ROUTINE_TYPE"]),'</td>','<td>',h($K["DTD_IDENTIFIER"]),'</td>';if($Kb)echo'<td>',truncate_utf8(preg_replace('~\s{2,}~'," ",trim($K["ROUTINE_COMMENT"])),50),'</td>';echo'<td><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').urlencode($K["SPECIFIC_NAME"]).$A).'">'.lang(169)."</a></td>";}echo"</table>\n";}echo'<p class="links">';if(support("procedure"))echo'<a href="',h(ME),'procedure=">',icon("function-add"),lang(251),"</a>";echo'<a href="',h(ME),'function=">',icon("function-add"),lang(250),"</a>\n","</p>\n";}if(support("event")){echo"<h2 id='events'>".lang(178)."</h2>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".lang(216)."<td>".lang(311)."<td>".lang(241)."<td>".lang(242)."<td></thead>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?lang(312)."<td>".$K["Execute at"]:lang(243)." ".$K["Interval value"]." ".$K["Interval field"]."<td>$K[Starts]"),"<td>$K[Ends]",'<td><a href="'.h(ME).'event='.urlencode($K["Name"]).'">'.lang(169).'</a>';echo"</table>\n";$qd=Connection::get()->getValue("SELECT @@event_scheduler");if($qd&&$qd!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($qd)."\n";}echo'<p class="links"><a href="',h(ME),'event=">',icon("event-add"),lang(240),"</a></p>\n";}}}page_footer();