<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 6.1.0
*/namespace
Adminer;
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth_user.php';

if (!isset($_SESSION['admin'])){
    http_response_code(401);
    header('Location: .');
    exit;
}
if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];const
VERSION="6.1.0";error_reporting(24575);set_error_handler(function($Sc,$Uc){return!!preg_match('~^Undefined (array key|offset|index)~',$Uc);},E_WARNING|E_NOTICE);$xd=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($xd||ini_get("filter.default_flags")){foreach(array('_GET','_POST','_COOKIE','_SERVER')as$X){$dl=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($dl)$$X=$dl;}}$_COOKIE=array_filter($_COOKIE,'is_scalar');if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");function
connection($f=null){return($f?:Db::$instance);}function
adminer(){return
Adminer::$instance;}function
driver(){return
Driver::$instance;}function
connect(){$Lb=adminer()->credentials();$J=Driver::connect($Lb[0],$Lb[1],$Lb[2]);return(is_object($J)?$J:null);}function
idf_unescape($t){if(!preg_match('~^[`\'"[]~',$t))return$t;$rf=substr($t,-1);return
str_replace($rf.$rf,$rf,substr($t,1,-1));}function
q($Q){return
connection()->quote($Q);}function
idx($ya,$w,$i=null){return($ya&&array_key_exists($w,$ya)?$ya[$w]:$i);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
int_type(){return'(tiny|small|medium|big)?int(eger|\d)?';}function
number_type(){return'(^('.int_type().'|decimal|numeric|number|real|(binary_|half_|scaled_)?float\d?|(binary_)?double( precision)?|(small)?money)$)';}function
text_type(){return'char|text'.(JUSH=="sql"?'|enum|set':'');}function
is_searchable(array$k,array$X){if(!isset($k["privileges"]["where"]))return
false;$U=$k["type"];$dj=$X["val"];$Oa='binary$|bytea|raw|image|bfile|^vector$'.(JUSH=="mssql"?'|^timestamp$':'|^bit').(JUSH=="oracle"?'|^blob|^long|rowid':'');if(preg_match("~$Oa~",$U))return
false;if(preg_match(number_type(),$U)){$Lg='-?\d+(\.\d+)?';return(bool)preg_match('~^'.$Lg.(preg_match('~IN$~',$X["op"])?"( *, *$Lg)*":'').'$~',$dj);}if(preg_match('~^(small)?date|^timestamp~',$U))return(bool)preg_match('~^\d+-\d+-\d+~',$dj);if(preg_match('~^time~',$U))return(bool)preg_match('~^\d+:\d+~',$dj);if(preg_match('~^bool~',$U)||(JUSH=="mssql"&&$U=="bit"))return(bool)preg_match('~^(t|f|true|false|[01])$~i',$dj);return
true;}function
remove_slashes(array$zl,$xd=false){$J=array();foreach($zl
as$w=>$X)$J[stripslashes($w)]=(is_array($X)?remove_slashes($X,$xd):($xd?$X:stripslashes($X)));return$J;}function
bracket_escape($t,$Ha=false){static$Lk=array(':'=>':1',']'=>':2','['=>':3','"'=>':4','='=>':5');return
strtr($t,($Ha?array_flip($Lk):$Lk));}function
url_escape($Q){static$Lk=array();if(!$Lk){$Lk=array(' '=>'+');foreach(str_split("\"'<>#%&+=?".ini_get("arg_separator.input"))as$ab)$Lk[$ab]=sprintf('%%%02X',ord($ab));for($r=0;$r<256;$r++){if($r<32||$r>126)$Lk[chr($r)]=sprintf('%%%02X',$r);}}return
strtr((string)$Q,$Lk);}function
min_version($Bl,$Kf="",$f=null){$f=connection($f);$rj=$f->server_info;if($Kf&&preg_match('~([\d.]+)-MariaDB~',$rj,$A)){$rj=$A[1];$Bl=$Kf;}return$Bl&&version_compare($rj,$Bl)>=0;}function
charset(Db$e){return(min_version("5.5.3",0,$e)?"utf8mb4":"utf8");}function
ini_set($gh,$Y){return(function_exists('ini_set')?\ini_set($gh,$Y):false);}function
ini_bool($Le){$X=ini_get($Le);return(preg_match('~^(on|true|yes)$~i',$X)||(int)$X);}function
ini_bytes($Le){$X=ini_get($Le);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
max_input_vars($K,$th){$Nf=(int)ini_get("max_input_vars");return($Nf?(int)floor(($Nf-$th)/$K):0);}function
max_input_vars_error(){$Le="max_input_vars";return
sprintf('Maximum number of allowed fields exceeded. Please increase %s.',"<b>$Le = ".ini_get($Le)."</b>");}function
sid(){static$J;if($J===null)$J=(SID&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
set_password($Al,$N,$V,$F){$_SESSION["pwds"][$Al][$N][$V]=($_COOKIE["adminer_key"]&&is_string($F)?array(encrypt_string($F,$_COOKIE["adminer_key"])):$F);}function
get_password(){$J=get_session("pwds");if(is_array($J))$J=($_COOKIE["adminer_key"]?decrypt_string($J[0],$_COOKIE["adminer_key"]):false);return$J;}function
get_val($H,$k=0,$zb=null){$zb=connection($zb);$I=$zb->query($H);if(!is_object($I))return
false;$K=$I->fetch_row();return($K?$K[$k]:false);}function
get_vals($H,$c=0){$J=array();$I=connection()->query($H);if(is_object($I)){while($K=$I->fetch_row())$J[]=$K[$c];}return$J;}function
get_key_vals($H,$f=null,$uj=true){$f=connection($f);$J=array();$I=$f->query($H);if(is_object($I)){while($K=$I->fetch_row()){if($uj)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$f=null,$j="<p class='error'>"){$zb=connection($f);$J=array();$I=$zb->query($H);if(is_object($I)){while($K=$I->fetch_assoc())$J[]=$K;}elseif(!$I&&!$f&&$j&&(defined('Adminer\PAGE_HEADER')||$j=="-- "))echo$j.adminer()->error()."\n";return$J;}function
unique_array($K,array$v){foreach($v
as$u){if(preg_match("~^(PRIMARY|UNIQUE)$~",$u["type"])&&!$u["partial"]){$J=array();foreach($u["columns"]as$w){if(!isset($K[$w]))continue
2;$J[$w]=$K[$w];}return$J;}}}function
where_function($Nd,$c,array$k){if($Nd=="md5")return"MD5(".(is_blob($k)||JUSH!='sql'||preg_match("~^utf8~",$k["collation"])?$c:"CONVERT($c USING ".charset(connection()).")").")";return(in_array($Nd,driver()->functions)||in_array($Nd,driver()->grouping)?apply_sql_function($Nd,$c):$c);}function
where(array$Z,array$l=array()){$J=array();foreach((array)$Z["where"]as$w=>$X){$w=bracket_escape($w,true);$c=idf_escape($w);$k=idx($l,$w,array());$rd=$k["type"];$Ye=$k&&(is_blob($k)||preg_match('~binary~',$rd));$J[]=$c.($Ye&&!is_utf8($X)?" = ".driver()->quoteBinary($X):(JUSH=="sql"&&$rd=="json"?" = CAST(".q($X)." AS JSON)":(JUSH=="pgsql"&&preg_match('~^jsonb?$~',$k["full_type"])?"::jsonb = ".q($X)."::jsonb":(JUSH=="sql"&&is_numeric($X)&&preg_match('~\.~',$X)?" LIKE ".q($X):(JUSH=="mssql"&&strpos($rd,"datetime")===false?" LIKE ".q(preg_replace('~[_%[]~','[\0]',$X)):" = ".unconvert_field($k,q($X)))))));if(JUSH=="sql"&&preg_match('~char|text~',$rd)&&preg_match("~[^ -@]~",$X))$J[]="$c = ".q($X)." COLLATE ".charset(connection())."_bin";}foreach((array)$Z["null"]as$w)$J[]=idf_escape($w)." IS NULL";foreach((array)$Z["col"]as$r=>$mb){$X=idx($Z["val"],$r);$J[]=where_function(idx($Z["fun"],$r),idf_escape($mb),idx($l,$mb,array())).($X!==null?" = ".q($X):" IS NULL");}return
implode(" AND ",$J);}function
where_columns(array$l){$J=array();foreach((array)$_GET["null"]as$w)$J[$w]=true;foreach(array_keys((array)$_GET["where"])as$w)$J[bracket_escape($w,true)]=true;foreach((array)$_GET["col"]as$mb)$J[$mb]=true;return
array_intersect_key($J,$l);}function
where_check($X,array$l=array()){parse_str($X,$db);remove_slashes(array(&$db));return
where($db,$l);}function
where_link($r,$c,$Y,$dh="="){$ah=($Y!==null?$dh:"IS NULL");return"&where[$r][col]=".url_escape($c).($ah!=first(adminer()->operators())?"&where[$r][op]=".url_escape($ah):"")."&where[$r][val]=".url_escape($Y);}function
convert_fields(array$d,array$l,array$M=array()){$J="";foreach($d
as$w=>$X){if($M&&!in_array(idf_escape($w),$M))continue;$za=convert_field($l[$w]);if($za)$J
.=", $za AS ".idf_escape($w);}return$J;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),array(";"=>"%3B",","=>"%2C"));}function
cookie($B,$Y,$Af=2592000){header("Set-Cookie: $B=".rawurlencode($Y).($Af?"; expires=".gmdate("D, d M Y H:i:s",time()+$Af)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"").($B=="adminer_import"?"":"; HttpOnly")."; SameSite=lax",false);}function
get_url($ml,$Cb){$http_response_header=null;$Tc=array();set_error_handler(function($Sc,$j)use(&$Tc){$Tc[]=preg_replace('~^file_get_contents\([^)]*\):\s*~','',$j);return
true;});$J=file_get_contents($ml,false,$Cb);restore_error_handler();$je=(function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header);return
array($J,(preg_match('~^HTTP/[\d.]+ (\d+)~',idx($je,0,''),$A)?$A[1]:''),(array)$je,($J===false?implode("\n",$Tc):''),);}function
get_settings($Fb){parse_str($_COOKIE[$Fb],$vj);return$vj;}function
get_setting($w,$Fb="adminer_settings",$i=null){return
idx(get_settings($Fb),$w,$i);}function
save_settings(array$vj,$Fb="adminer_settings"){$Y=http_build_query($vj+get_settings($Fb));cookie($Fb,$Y);$_COOKIE[$Fb]=$Y;}function
restart_session(){if(!ini_bool("session.use_cookies")&&(!function_exists('session_status')||session_status()==PHP_SESSION_NONE))session_start();}function
stop_session($Bd=false){$pl=ini_bool("session.use_cookies");if(!$pl||$Bd){session_write_close();if($pl&&ini_set("session.use_cookies",'0')===false)session_start();}}function&get_session($w){return$_SESSION[$w][DRIVER][SERVER][$_GET["username"]];}function
set_session($w,$X){$_SESSION[$w][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($Al,$N,$V,$h=null){$ll=remove_from_uri(implode("|",array_keys(SqlDriver::$drivers))."|username|ext|".($h!==null?"db|":"").($Al=='mssql'||$Al=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$ll,$A);return"$A[1]?".(sid()?SID."&":"").($_GET["ext"]?"ext=".url_escape($_GET["ext"])."&":"").($Al!="server"||$N!=""?url_escape($Al)."=".url_escape($N)."&":"")."username=".url_escape($V).($h!=""?"&db=".url_escape($h):"").($A[2]?"&$A[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($_,$dg=null){if($dg!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($_!==null?$_:$_SERVER["REQUEST_URI"]))][]=$dg;}if($_!==null){if($_=="")$_=".";header("Location: $_");exit;}}function
query_redirect($H,$_,$dg,$wi=true,$bd=true,$md=false,$zk=""){if($bd){$Nj=microtime(true);$md=!connection()->query($H);$zk=format_time($Nj);}$Hj=($H?adminer()->messageQuery($H,$zk,$md):"");if($md){adminer()->error
.=adminer()->error().$Hj.script("messagesPrint();")."<br>";return
false;}if($wi)redirect($_,$dg.$Hj);return
true;}class
Queries{static$queries=array();static$start=0;}function
remember_query($H){if(!Queries::$start)Queries::$start=microtime(true);Queries::$queries[]=(driver()->delimiter!=';'?$H:(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";");}function
queries($H){remember_query($H);return
connection()->query($H);}function
apply_queries($H,array$T,$Vc='Adminer\table'){foreach($T
as$R){if(!queries("$H ".$Vc($R)))return
false;}return
true;}function
queries_redirect($_,$dg,$wi){$ri=implode("\n",Queries::$queries);$zk=format_time(Queries::$start);return
query_redirect($ri,$_,$dg,$wi,false,!$wi,$zk);}function
format_time($Nj){return
sprintf('%.3f s',max(0,microtime(true)-$Nj));}function
relative_uri($ll=''){return
preg_replace_callback('~^[^?]*~',function($A){return
str_replace(":","%3A",$A[0]);},preg_replace('~^[^?]*/([^?]*)~','\1',($ll?:$_SERVER["REQUEST_URI"])));}function
remove_from_uri($yh=""){return
substr(preg_replace("~(?<=[?&])($yh".(SID?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_files($B,$ac=false){$td=$_FILES[$B];if(!$td)return
null;foreach($td
as$w=>$X)$td[$w]=(array)$X;$J=array();foreach($td["error"]as$w=>$j){if($j)return$j;$m=$td["name"][$w];$Gk=$td["tmp_name"][$w];$Ab=file_get_contents($ac&&preg_match('~\.gz$~',$m)?"compress.zlib://$Gk":$Gk);if($ac){$Nj=substr($Ab,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$Nj))$Ab=iconv("utf-16","utf-8",$Ab);elseif($Nj=="\xEF\xBB\xBF")$Ab=substr($Ab,3);}$J[]=array($m,$Ab);}return$J;}function
get_file($w,$ac=false,$hc=""){$wd=get_files($w,$ac);if(!is_array($wd))return$wd;$J='';foreach($wd
as$td){$Ab=$td[1];$J
.=$Ab;if($hc)$J
.=(preg_match("($hc\\s*\$)",$Ab)?"":$hc)."\n\n";}return$J;}function
upload_error($j){$Vf=($j==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($j?'Unable to upload a file.'.($Vf?" ".sprintf('Maximum allowed file size is %sB.',$Vf):""):'File does not exist.');}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
utf8_length($X){return
strlen(preg_replace('~[\x80-\xBF]~','',$X));}function
format_number($X){preg_match('~^#+([^#0]+)(?:(#+)\1)?(#*0)$~u','#,##0',$A);$zj=strlen($A[3]);$J=number_format($X,0,".","");$J=preg_replace('~\B(?=(\d{'.(strlen($A[2])?:$zj).'})*\d{'.$zj.'}$)~',$A[1],$J);return
strtr($J,preg_split('~~u','0123456789',-1,PREG_SPLIT_NO_EMPTY));}function
format_status(array$S,$w){$X=idx($S,$w,'?');if(!is_numeric($X))return
h($X);if($X<0)return'?';$va=($w=="Rows"&&(JUSH=="sqlite"||$S["Engine"]==(JUSH=="pgsql"?"table":"InnoDB")));return($va?"~ ":"").format_number($X);}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($R,$nd=false){$J=table_status($R,$nd);return($J?reset($J):array("Name"=>$R));}function
column_foreign_keys($R){$J=array();foreach(adminer()->foreignKeys($R)as$n){foreach($n["source"]as$X)$J[$X][]=$n;}return$J;}function
fields_from_edit(){$J=array();foreach((array)$_POST["field_keys"]as$w=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$w];$_POST["fields"][$X]=$_POST["field_vals"][$w];}}foreach((array)$_POST["fields"]as$w=>$X){$B=bracket_escape($w,true);$J[$B]=array("field"=>$B,"full_type"=>"","type"=>"","privileges"=>array("insert"=>1,"update"=>1,"where"=>1,"order"=>1),"null"=>true,"auto_increment"=>($B==driver()->primary),);}return$J;}function
dump_headers($ve,$ug=false){$J=adminer()->dumpHeaders($ve,$ug);$vh=$_POST["output"];if($vh!="text"||$J=="tar"){$wb=($vh!="text"&&$vh!="file"&&preg_match('~^[0-9a-z]+$~',$vh)?".$vh":"");header("Content-Disposition: attachment; filename=".adminer()->dumpFilename($ve).".$J$wb");}session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$J;}function
dump_csv(array$K){$Tk=$_POST["format"]=="tsv";foreach($K
as$w=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($Tk?'\t':'[,;]|^$').'~',$X))$K[$w]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($Tk?"\t":";")),$K)."\r\n";}function
parse_csv($Ob,$mj){$J=array();preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$Ob,$Lf);foreach($Lf[0]as$K){preg_match_all("~((?>\"[^\"]*\")+|[^$mj]*)$mj~",$K.$mj,$Mf);$J[]=$Mf[1];}return$J;}function
csv_value($X){return(preg_match('~^".*"$~s',$X)?str_replace('""','"',substr($X,1,-1)):$X);}function
apply_sql_function($p,$c){return($p?($p=="unixepoch"?"DATETIME($c, '$p')":($p=="count distinct"?"COUNT(DISTINCT ":strtoupper("$p("))."$c)"):$c);}function
get_temp_dir(){return
ini_get("upload_tmp_dir")?:sys_get_temp_dir();}function
file_open_lock($m){if(is_link($m))return;$o=@fopen($m,"c+");if(!$o)return;@chmod($m,0660);if(!flock($o,LOCK_EX)){fclose($o);return;}return$o;}function
file_write_unlock($o,$Sb){rewind($o);fwrite($o,$Sb);ftruncate($o,strlen($Sb));file_unlock($o);}function
file_unlock($o){flock($o,LOCK_UN);fclose($o);}function
first(array$ya){return
reset($ya);}function
password_file($Ib){$m=get_temp_dir()."/adminer.key";if(!$Ib&&!file_exists($m))return'';$o=file_open_lock($m);if(!$o)return'';$J=stream_get_contents($o);if(!$J){$J=rand_string();file_write_unlock($o,$J);}else
file_unlock($o);return$J;}function
rand_string(){return(function_exists('random_bytes')?bin2hex(random_bytes(16)):md5(uniqid(strval(mt_rand()),true)));}function
select_value($X,$z,array$k,$xk){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$kf=array();foreach($X
as$W)$kf+=array_fill_keys(array_keys($W),null);foreach(array_keys($kf)as$if)$J
.="<th>".h($if);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($kf,$W)as$vl)$J
.="<td>".select_value($vl,$z,$k,$xk);}}else{foreach($X
as$if=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($if):"")."<td>".select_value($W,$z,$k,$xk);}return"<table>$J</table>";}if(!$z)$z=adminer()->selectLink($X,$k);if($z===null){if(is_mail($X))$z="mailto:$X";if(is_url($X))$z=$X;}$X=driver()->value($X,$k);$J=adminer()->editVal($X,$k);if($J!==null){if(!is_utf8($J))$J="\0";elseif($xk!=""&&is_shortable($k))$J=shorten_utf8($J,max(0,+$xk));else$J=h($J);}return
adminer()->selectVal($J,$z,$k,$X);}function
is_blob(array$k){return
preg_match('~blob|bytea|raw|file'.(JUSH=="mssql"?'|binary|image':'').'~',$k["type"])&&!in_array($k["type"],idx(driver()->structuredTypes(),'User types',array()));}function
is_mail($Jc){$Aa='[-a-z0-9!#$%&\'*+/=?^_`{|}~]';$yc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';$Ph="$Aa+(\\.$Aa+)*@($yc?\\.)+$yc";return
is_string($Jc)&&preg_match("(^$Ph(,\\s*$Ph)*\$)i",$Jc);}function
is_url($Q){$yc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';return
preg_match("~^((https?):)?//($yc?\\.)+$yc(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i",$Q);}function
is_ipv6($ha){$q='[\da-f]{1,4}';$Xe='\d{1,3}(\.\d{1,3}){3}';return(bool)preg_match("~^(($q:){7}$q|($q:){6}$Xe|(($q:)*$q)?::(($q:)*($q|$Xe))?)$~iD",$ha);}function
is_shortable(array$k){return!preg_match('~'.number_type().'|date|time|year~',$k["type"]);}function
url_host($re){return(strpos($re,":")!==false?"[$re]":$re);}function
server_parts(array$Jh){return
array("scheme"=>(string)$Jh["scheme"],"host"=>(string)$Jh["host"],"port"=>(string)$Jh["port"],"socket"=>(string)$Jh["socket"],"path"=>(string)$Jh["path"],);}function
parse_server($N){if($N=="")return
server_parts(array());if($N[0]==":"&&!is_ipv6($N)){$Ii=substr($N,1);if(preg_match('~^\d+$~D',$Ii))return
server_parts(array("port"=>$Ii));return(preg_match('~^/[-\w.:/]*$~D',$Ii)?server_parts(array("socket"=>$Ii)):null);}$bj="";if(preg_match('~^([-+.\w]+)://~',$N,$A)){$bj=strtolower($A[1]);$N=substr($N,strlen($A[0]));}if(preg_match('~^\[(.+)](:(\d+))?(/[-\w./]*)?$~D',$N,$A))return(is_ipv6($A[1])?server_parts(array("scheme"=>$bj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);if(is_ipv6($N))return
server_parts(array("scheme"=>$bj,"host"=>$N));if(preg_match('~^(/[-\w./]*)(:(\d+))?$~D',$N,$A))return
server_parts(array("scheme"=>$bj,"host"=>$A[1],"port"=>$A[3]));return(preg_match('~^([-\w.]*)(:(\d+))?(/[-\w./]*)?$~D',$N,$A)?server_parts(array("scheme"=>$bj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);}function
count_rows($R,array$Z,$Ze,array$q){$H=" FROM ".table($R).($Z?" WHERE ".implode(" AND ",$Z):"");return($Ze&&(JUSH=="sql"||count($q)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$q).")$H":"SELECT COUNT(*)".($Ze?" FROM (SELECT 1$H GROUP BY ".implode(", ",$q).") x":$H));}function
slow_query($H){$h=adminer()->database();$_k=adminer()->queryTimeout();$_j=driver()->slowQuery($H,$_k);$f=null;if(!$_j&&support("kill")){$f=connect();if($f&&($h==""||$f->select_db($h))){$lf=number(get_val(connection_id(),0,$f));echo
script("const timeout = setTimeout(() => { ajax('".js_escape(ME)."script=kill', function () {}, 'kill=$lf&token=".get_token()."'); }, 1000 * $_k);");}}ob_flush();flush();$J=@get_key_vals(($_j?:$H),$f,false);if($f){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$ui=rand(1,1e6);return($ui^$_SESSION["token"]).":$ui";}function
verify_token(){list($Hk,$ui)=explode(":",$_POST["token"]);return($ui^$_SESSION["token"])==$Hk&&in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"));}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Q,$nc=""){$ra=array_flip(str_split(compress_alphabet()));$x=strlen($Q);$xl=($x?13*($x-1)/2-$ra[$Q[0]]:0);$Oa="";$Ii=0;$Ji=0;for($r=1;$r<$x;$r+=2){$Ii=($Ii<<13)+$ra[$Q[$r]]*93+$ra[$Q[$r+1]];$Ji+=13;while($Ji>=8&&$xl>=8){$Ji-=8;$xl-=8;$Oa
.=chr($Ii>>$Ji);$Ii&=(1<<$Ji)-1;}}if($Oa=="")return"";if($nc!=""&&function_exists('inflate_init'))return
inflate_add(inflate_init(ZLIB_ENCODING_RAW,array('dictionary'=>$nc)),$Oa,ZLIB_FINISH);return($nc==""&&function_exists('gzinflate')?gzinflate($Oa):inflate($Oa,$nc));}function
inflate($Oa,$nc=""){$yf=array(3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258);$zf=array(0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0);$rc=array(1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577);$tc=array(0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13);$J=$nc;$G=0;do{$yd=inflate_bits($Oa,$G,1);$U=inflate_bits($Oa,$G,2);if(!$U){$G=($G+7)&~7;$x=inflate_bits($Oa,$G,16);$G+=16;$J
.=substr($Oa,$G>>3,$x);$G+=$x<<3;}else{if($U==1){$Ff=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$uc=array_fill(0,30,5);}else{$Ef=inflate_bits($Oa,$G,5)+257;$sc=inflate_bits($Oa,$G,5)+1;$D=array(16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15);$jg=array_fill(0,19,0);$ig=inflate_bits($Oa,$G,4)+4;for($r=0;$r<$ig;$r++)$jg[$D[$r]]=inflate_bits($Oa,$G,3);$kg=inflate_table($jg);$_f=array();while(count($_f)<$Ef+$sc){$Zj=inflate_symbol($Oa,$G,$kg);if($Zj==16)$_f=array_merge($_f,array_fill(0,inflate_bits($Oa,$G,2)+3,end($_f)));elseif($Zj==17)$_f=array_merge($_f,array_fill(0,inflate_bits($Oa,$G,3)+3,0));elseif($Zj==18)$_f=array_merge($_f,array_fill(0,inflate_bits($Oa,$G,7)+11,0));else$_f[]=$Zj;}$Ff=array_slice($_f,0,$Ef);$uc=array_slice($_f,$Ef);}$Gf=inflate_table($Ff);$wc=inflate_table($uc);while(($Zj=inflate_symbol($Oa,$G,$Gf))!=256){if($Zj<256)$J
.=chr($Zj);else{$x=$yf[$Zj-257]+inflate_bits($Oa,$G,$zf[$Zj-257]);$vc=inflate_symbol($Oa,$G,$wc);$Rg=strlen($J)-$rc[$vc]-inflate_bits($Oa,$G,$tc[$vc]);for($r=0;$r<$x;$r++)$J
.=$J[$Rg+$r];}}}}while(!$yd);return($nc==""?$J:substr($J,strlen($nc)));}function
inflate_bits($Oa,&$G,$Hb){$J=0;for($r=0;$r<$Hb;$r++){$J+=((ord($Oa[$G>>3])>>($G&7))&1)<<$r;$G++;}return$J;}function
inflate_table(array$_f){$R=array();$lb=0;for($Pa=1;$Pa<=max($_f);$Pa++){foreach($_f
as$Zj=>$x){if($x==$Pa){$R[$Pa][$lb]=$Zj;$lb++;}}$lb<<=1;}return$R;}function
inflate_symbol($Oa,&$G,array$R){$lb=0;$Pa=0;do{$lb=($lb<<1)+inflate_bits($Oa,$G,1);$Pa++;}while(!isset($R[$Pa][$lb]));return$R[$Pa][$lb];}function
script($Ej,$Kk="\n"){return"<script".nonce().">$Ej</script>$Kk";}function
script_src($ml,$dc=false){return"<script src='".h($ml)."'".nonce().($dc?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
on($Wc,$be,$wa=null){$xa=array();foreach(array_slice(func_get_args(),2)as$X)$xa[]=json_encode($X,256);return" data-on$Wc='".str_replace(array('&','<',"'"),array('&amp;','&lt;','&#039;'),"$be(".implode(", ",$xa).")")."'";}function
input_hidden($B,$Y=""){return"<input type='hidden' name='".h($B)."' value='".h($Y)."'>\n";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Q){return
str_replace(array('&','<','"',"'","\0"),array('&amp;','&lt;','&quot;','&#039;','&#0;'),$Q);}function
nl_br($Q){return
str_replace("\n","<br>",$Q);}function
checkbox($B,$Y,$fb,$nf="",$b="",$kb="",$pf=""){$J="<input type='checkbox' name='$B' value='".h($Y)."'".($fb?" checked":"").($nf==""&&$kb?" class='$kb'":"").($pf?" aria-labelledby='$pf'":"").$b.">";return($nf!=""?"<label".($kb?" class='$kb'":"").">$J".h($nf)."</label>":$J);}function
optionlist($C,$jj=null,$ql=false){$J="";foreach($C
as$if=>$W){$ih=array($if=>$W);if(is_array($W)){$J
.='<optgroup label="'.h($if).'">';$ih=$W;}foreach($ih
as$w=>$X)$J
.='<option'.($ql||is_string($w)?' value="'.h($w).'"':'').($jj!==null&&($ql||is_string($w)?(string)$w:$X)===$jj?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
html_select($B,array$C,$Y="",$b="",$pf=""){static$nf=0;$of="";if(!$pf&&substr($C[""],0,1)=="("){$nf++;$pf="label-$nf";$of="<option value='' id='$pf'>".h($C[""]);unset($C[""]);}return"<select name='".h($B)."'".($pf?" aria-labelledby='$pf'":"")."$b>".$of.optionlist($C,$Y)."</select>";}function
html_radios($B,array$C,$Y="",$mj=""){$J="";foreach($C
as$w=>$X)$J
.="<label><input type='radio' name='".h($B)."' value='".h($w)."'".($w==$Y?" checked":"").">".h($X)."</label>$mj";return$J;}function
confirm($dg=""){return
on('click','confirmClick',$dg?:'Are you sure?');}function
print_fieldset($s,$xf,$El=false){echo"<fieldset><legend>","<a href='#fieldset-$s' class='toggle'>$xf</a>","</legend>","<div id='fieldset-$s'".($El?"":" class='hidden'").">\n";}function
bold($Qa,$kb=""){return($Qa?" class='active $kb'":($kb?" class='$kb'":""));}function
js_escape($Q){return
str_replace("<","\\x3C",addcslashes($Q,"\r\n'\\"));}function
js_escape_re($Q){return
addcslashes(preg_quote($Q,"/"),"\r\n");}function
pagination_href($E){return
remove_from_uri("page|next").($E?"&page=$E".($_GET["next"]!=""?"&next=".url_escape($_GET["next"]):""):"");}function
pagination($E,$Pb){return" ".($E==$Pb?($E?"<b>".($E+1)."</b>":$E+1):'<a href="'.h(pagination_href($E)).'">'.($E+1)."</a>");}function
hidden_fields(array$ni,array$ze=array(),$ei=''){$J=false;foreach($ni
as$w=>$X){if(!in_array($w,$ze)){if(is_array($X))hidden_fields($X,array(),$w);else{$J=true;echo
input_hidden(($ei?$ei."[$w]":$w),$X);}}}return$J;}function
hidden_fields_get(){echo(sid()?input_hidden(session_name(),session_id()):''),($_GET["ext"]?input_hidden("ext",$_GET["ext"]):""),(isset($_GET[DRIVER])?input_hidden(DRIVER,SERVER):""),input_hidden("username",$_GET["username"]);}function
on_upload_progress(&$kl){$kl=(ini_bool("session.upload_progress.enabled")&&ini_get("session.upload_progress.name")?rand_string():"");return($kl?on('submit','uploadProgress',ME."upload=$kl",SESSION_NAME."=$kl"):"");}function
file_input($b,$Ii=""){$Pf="max_file_uploads";$Qf=ini_get($Pf);$Vf="upload_max_filesize";$Wf=ini_bytes($Vf);$bi=ini_bytes("post_max_size");if($bi&&$bi<$Wf){$Vf="post_max_size";$Wf=$bi;}$Xf=ini_get($Vf);return(ini_bool("file_uploads")?"<input type='file'$b".on('change','fileChange',(int)$Qf,sprintf('Increase %s.',"$Pf = $Qf"),$Wf,sprintf('Increase %s.',"$Vf = $Xf")).">$Ii":'File uploads are disabled.');}function
enum_input($U,$b,array$k,$Y,$Mc=""){preg_match_all("~'((?:[^']|'')*)'~",$k["length"],$Lf);$ei=($k["type"]=="enum"?"val-":"");$fb=(is_array($Y)?in_array("null",$Y):$Y===null);$J=($k["null"]&&$ei?"<label><input type='$U'$b value='null'".($fb?" checked":"")."><i>$Mc</i></label>":"");foreach($Lf[1]as$X){$X=stripcslashes(str_replace("''","'",$X));$fb=(is_array($Y)?in_array($ei.$X,$Y):$Y===$X);$J
.=" <label><input type='$U'$b value='".h($ei.$X)."'".($fb?' checked':'').'>'.h(adminer()->editVal($X,$k)).'</label>';}return$J;}function
input(array$k,$Y,$p,$Fa=false,$hl=false){$B=h(bracket_escape($k["field"]));echo"<td class='function'>";$Rc=driver()->enumLength($k);if($Rc){$k["type"]="enum";$k["length"]=$Rc;}$C=($k["type"]=="enum"||$k["type"]=="set");if(is_array($Y)&&!$p&&!$C)$p="json";$gf=($p=="json"||preg_match('~^jsonb?$~',$k["full_type"]));if($gf&&$Y!=''&&(JUSH!="pgsql"||$k["type"]!="json")&&(is_array($Y)||!$_POST["save"]))$Y=json_encode(is_array($Y)?$Y:json_decode($Y),128|64|256);$Hi=(JUSH=="mssql"&&$hl&&$k["auto_increment"]);if($Hi&&!$_POST["save"])$p=null;$Od=(isset($_GET["select"])||$Hi?array("orig"=>'original'):array())+adminer()->editFunctions($k);$b=" name='fields[$B]".($C?"[]":"")."'".($Fa?" autofocus":"");echo
driver()->unconvertFunction($k)." ";$R=$_GET["edit"]?:$_GET["select"];if($k["type"]=="enum")echo
h($Od[""])."<td>".adminer()->editInput($R,$k,$b,$Y);else{$de=(in_array($p,$Od)||isset($Od[$p]));$zd=0;foreach($Od
as$w=>$X){if($w===""||!$X)break;$zd++;}echo(count($Od)>1?"<select name='function[$B]'".on('change','functionChange').on_help_value('^SQL$').">".optionlist($Od,$p===null||$de?$p:"")."</select>":h(reset($Od)))."<td".($zd&&count($Od)>1?on('input','skipOriginal',$zd):"").">";$Ne=adminer()->editInput($R,$k,$b,$Y);if($Ne!="")echo$Ne;elseif(preg_match('~bool~',$k["type"]))echo"<input type='hidden'$b value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked":"")."$b value='1'>";elseif($k["type"]=="set")echo
enum_input("checkbox",$b,$k,(is_string($Y)?explode(",",$Y):$Y));elseif(is_blob($k)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$B'>";elseif($gf)echo"<textarea$b cols='50' rows='12' class='jush-json'>".h($Y).'</textarea>';elseif(($wk=preg_match('~text|lob|memo~i',$k["type"]))||preg_match("~\n~",$Y)){if($wk&&JUSH!="sqlite")$b
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$b
.=" cols='30' rows='$L'";}echo"<textarea$b>".h($Y).'</textarea>';}else{$Xk=driver()->types();$Vk=$Xk[$k["type"]];if(preg_match('~date|time|year~',$k["type"])){$Id=(preg_match('~time~',$k["type"])&&preg_match('~^\d+$~',$k["length"])?$k["length"]+1:0);$Yf=($Vk?$Vk+$Id:0);}elseif(!preg_match('~int|vector~',$k["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$k["length"],$A))$Yf=(preg_match("~binary~",$k["type"])?2:1)*$A[1]+($A[3]?1:0)+($A[2]&&!$k["unsigned"]?1:0);else$Yf=($Vk?$Vk+($k["unsigned"]?0:1):0);echo"<input".((!$de||$p==="")&&preg_match('~^'.int_type().'$~',$k["type"])&&!preg_match('~\[]~',$k["full_type"])?" type='number'":"")." value='".h($Y)."'".($Yf?" data-maxlength='$Yf'":"").(preg_match('~char|binary~',$k["type"])&&$Yf>20?" size='".($Yf>99?60:40)."'":"")."$b>";}echo
adminer()->editHint($R,$k,$Y),(count($Od)>1?script("fire(qs('select', qsl('td').previousSibling), 'change');",""):"");}}function
process_input(array$k){$t=bracket_escape($k["field"]);$p=idx($_POST["function"],$t);if($p=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?idf_escape($k["field"]):false);if($p=="NULL")return"NULL";if(is_blob($k)&&ini_bool("file_uploads")){$td=get_file("fields-$t");if(!is_string($td))return
false;return
driver()->quoteBinary($td);}$Y=idx($_POST["fields"],$t);if($Y===null)return
false;if($k["type"]=="enum"||driver()->enumLength($k)){$Y=idx($Y,0);if($Y=="orig"||!$Y)return
false;if($Y=="null")return"NULL";$Y=substr($Y,4);}if($k["auto_increment"]&&$Y=="")return
null;if($k["type"]=="set")$Y=implode(",",(array)$Y);if($p=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
adminer()->processInput($k,$Y,$p);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$lj="<ul>\n";foreach(table_status('',true)as$R=>$S){$B=adminer()->tableName($S);if(isset($S["Engine"])&&$B!=""&&(!$_POST["tables"]||in_array($R,$_POST["tables"]))){$I=connection()->query("SELECT".limit("1 FROM ".table($R)," WHERE ".implode(" AND ",adminer()->selectSearchProcess(fields($R),array(),$S)),1));if(!$I||$I->fetch_row()){$ji="<a href='".h(ME."select=".url_escape($R)."&where[0][op]=".url_escape($_GET["where"][0]["op"])."&where[0][val]=".url_escape($_GET["where"][0]["val"]))."'>$B</a>";echo"$lj<li>".($I?$ji:"<p class='error'>$ji: ".adminer()->error())."\n";$lj="";}}}echo($lj?"<p class='message'>".'No tables.':"</ul>")."\n";}function
on_help($wk,$yj=0){return
on('mouseover','helpMouseover',$wk,$yj).on('mouseout','helpMouseout');}function
on_help_value($Ci="",$Gi=""){return
on('mouseover','helpValueMouseover',$Ci,$Gi).on('mouseout','helpMouseout');}function
edit_form($R,array$l,$K,$hl,$j='',$H='',$zk=''){$fk=adminer()->tableName(table_status1($R,true));page_header(($hl?'Edit':'Insert'),$j,array("select"=>array($R,$fk)),$fk);adminer()->editRowPrint($R,$l,$K,$hl,$H,$zk);if($K===false){echo"<p class='error'>".'No rows.'."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$Hc=false;$Kl=($hl&&!isset($_GET["select"])?where_columns($l):array());$Db=(count($Kl)!=count($l));if(!$Db)$Kl=array();if(!$l)echo"<p class='error'>".'You have no privileges to update this table.'."\n";else{echo"<table class='layout nowrap'".on('keydown','editingKeydown').">\n";$Fa=!$_POST;foreach($l
as$B=>$k){echo"<tr".($Kl[$B]?on('change','whereChange'):"")."><th>".adminer()->fieldName($k);$i=idx($_GET["set"],bracket_escape($B));if($i===null){$i=$k["default"];if($k["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$i,$Ei))$i=$Ei[1];if(JUSH=="sql"&&preg_match('~binary~',$k["type"]))$i=bin2hex($i);}$Y=($K!==null?($k["type"]=="set"&&is_array($K[$B])?implode(",",$K[$B]):(is_bool($K[$B])?+$K[$B]:$K[$B])):(!$hl&&$k["auto_increment"]?"":(isset($_GET["select"])?false:$i)));if(!$_POST["save"]&&is_string($Y))$Y=adminer()->editVal($Y,$k);if(($hl&&!isset($k["privileges"]["update"]))||$k["generated"])echo"<td class='function'><td>".select_value($Y,'',$k,null);else{$Hc=true;$p=($_POST["save"]?idx($_POST["function"],bracket_escape($B),""):($hl&&preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$hl&&$Y==$k["default"]&&preg_match('~^[\w.]+\(~',$Y))$p="SQL";if(preg_match("~time~",$k["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$p="now";}if($k["type"]=="uuid"&&$Y=="uuid()"){$Y="";$p="uuid";}if($Fa!==false)$Fa=($k["auto_increment"]||$p=="now"||$p=="uuid"?null:true);input($k,$Y,$p,$Fa,$hl);if($Fa)$Fa=false;}}if(!fields($R)&&driver()->primary!="")echo"<tr>"."<th><input name='field_keys[]'".on('input','fieldChange').">"."<td class='function'>".html_select("field_funs[]",adminer()->editFunctions(array("null"=>isset($_GET["select"]))))."<td><input name='field_vals[]'>";echo"</table>\n";}echo"<p>\n";if($Hc){echo"<input type='submit' value='".'Save'."'>\n";if(!isset($_GET["select"])&&$Db){$oc=($Kl&&($j!=""||adminer()->error!="")?" disabled":"");echo"<input type='submit' name='insert' value='".($hl?'Save and continue editing':'Save and insert next')."' title='Ctrl+Shift+Enter'$oc".($hl?on('click','ajaxForm','Saving…'):"").">\n";}}echo($hl?"<input type='submit' name='delete' value='".'Delete'."'".confirm().">\n":"");if(isset($_GET["select"]))hidden_fields(array("check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]));echo
input_hidden("referer",(isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"])),input_hidden("save",1),input_token(),"</form>\n";}function
repeat_pattern($Ph,$x){return
str_repeat("$Ph{0,65535}",$x/65535)."$Ph{0,".($x%65535)."}";}function
shorten_utf8($Q,$x=80,$Vj=""){if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$x).")($)?)u",$Q,$A))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$x).")($)?)",$Q,$A);return(isset($A[2])?h($A[1]).$Vj:h(preg_replace('~\n[^\n]*\z~',"\n",$A[1]))."$Vj<i>…</i>");}function
icon($ue,$B,$te,$Bk,$b=""){return"<button ".($B?"type='submit' name='$B'":"draggable='true' tabindex='-1'")." title='".h($Bk)."' class='icon icon-$ue".($B?"":" jsonly")."'$b><span>$te</span></button>";}function
copy_icon(){$Gb='Copy';return"<a href='' class='jsonly icon-copy' title='$Gb'><span>$Gb</span></a>";}if(isset($_GET["file"])){if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){header("HTTP/1.1 304 Not Modified");exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression",'1');if($_GET["file"]=="default.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('&c(<]iDp;+<8]XG-X#ET@P~g+44jkGE
JJMWB[P=;i#!X$XZ(f>Cx5+d&ydL<""!*rBpff!fkm^bQBjtpU)4hnlgdu9u"]lNs!9kv`}pLnMu[)!?
/nW}!<x5Ey]-bY_G+1cjtywbHdyuU[S<dax0
Hp*-71^<Q=:@:nTh|W:o^Mww"/r*_nT8FCXI``P&A[5^Z%0OT*zx^Qb+nx0qMeS5DapbVg]7?)iJ*"[4}C}*JqDk!0#.uZ{4cX*U8U!(c3>W+"$E"oK-|>@iWX6l|1W=f$g36bV,e8dwLN)WK,R-6f"$IOZ^&_g;N%[.eN;seVrL3)^@fQ(N)+I_+gr(D**O1?;]IF:DMgNjlmV/-JRE},jU`0E2b2_Fpf9

)@uK@TEX-E0q&d=R):2^8UCGmhA|!EnaiTCO9E]<D0H?4_DJf^E8g[o_!1xI!9j?+-`Te!B-`bsPmV=<$,`bFq(52j+hR#Loh4Z}^`&?XpC=4^4pi:lSI}%9<`@NphbTIeYKquP`DgCzfIErRzu1$VT.5awB]A5%[)d{5>a=mnN&rWqBo=.X
Tw&_{V
%Sq9f#Zp&+ZNQ$++;qk~9wv.sKdb"*)BaZ-Rd8a*dAT@)}2F<vSWC)>4(fBxIN6/FUTxn7"S8sH._j
MZyJaF1No9&(oC9Mj$!&r8G6`DEb/r[V9k:,KeQN$Nwm)C1*6S8"llf@pFAAoG/4D@sAUMa$tH0Nxe=WAn8ClYb]V:PG5NQvPEQ9MY9a"sK8d$sbwSU9DlE^VUB#8K_[0Il&{[@9qK~em90k2Er
iHE.IGE5lo=3Fm@1=MM34!r=KC}^AB%"z=;3e,S7Dr[A__{9aL7@?3]wUkew{s
Kr!tOUQ-Od;Xa6m5Lry}k~Z=lWG79i_<MR<{""v1@`P$4PJ;aven@*_GdL;_o"lx_$c<8y9IWTU|Osq9JkImMylT@c$N(Ln|xF2ss%m473U{*{D&9j!_shhxM3N9+>!6ukH~>U
H6~=q:K!~4_*?a.4,ptom8{8q3gi"*zP4(s").ZttK/:m
,t"S~Q*$5DrPlMp-tKNCfZarEOEgXu9y0WwS(y~/"Un1KV"hvF`RGjMPEjEne@V+cmCuTMpXT
<t`2aO<&@?M[UKSJ+*(YwINSt.pPpSJ;Pn5PD=Gc@R=fm!.[e,wG]@IPL]wWa`gc/:~U:Wc$DJ3Gj+
2eUL"[c!@Q8p/,S~mqr"<?
0XMM:b3+dE#]9i$gNv-yec"LJ(+ph]UoG0`k!AB#F!a[~"@r{?A`C#3&71G"4N)hTgk;>?fkeTe)six>@!2!b$R[u0<Md[!hN7}_o1btnf93]BD9{VpB7;n2}HZpvTVo}"
"mq2=-TaS&Q9bUSx
m8v3jj]B%?BHH/CR?FCX_P>PM^]p}*zU98:7d>nF79Q=@92WSCV!/R^0!J2RHE/C<o_N.3^=^6>mK*`hrF`bmW7=kKgPpE@c[x}cOZ[.MuN]nA82wYRQ8aZFL[SRTJA,U_~([MmpHPx&0YNv.m|cO3FQP%+]W#l?:I7(8
Qt@Y,sn_!b6U.0s52(&_K
beRDYJFd8xxs
y7>Hsl2^V9ah^&4i7&"0LF2Jlf[4*eQJeztm
JIetUG(Umx<Dk.qq"liXSR7P4F:]|9:iolL8zc]6"%%C00zP5j&j:Fp,%VTWQDr$"&D-Q&Qpv*oEM1[cNQ_1h%h%fVP0zlH<t,;#H#%e.Fctm:?&09}8ejQ*#ymG%jTVfbjP6;CorQ=bgXl.5%mFrp[6o*F;W8G^}-l`bf]DL*(kY"xL(><J6DZ_Rlgkh]45v^dCUo56(_=9-sm*<t1iF
)4~N~y7m1DJ-;BB*/=;Yg4XxvvRVBmW#.dz#B8/X+<l42=Z2,5[02HjT3Di@]*OIO&j7M^_O)poc3*pnK_@!
ZXx#*C:2R*2aX>hFKR_Sf$CNf3PXwcyB#C.PPia{43H8rWms:/D]=
`P(1b)vuJ]-DX3OymFG/^jL=62O+EV]OZI#L3(RXt^6GGf2SmK"w3"oMIIpDk=
nhqe5h`l}R}m[E~rO(2Y(FP.Wp8rOdY&o>@&ChKb<[1,
P}
y9--6(;1qd/E^LVj`I?CT!xlqLeQzbctp%/D45[9
"b*;u
h?yA5i"gG,jX2uN?QRi]3~8m8R(p(vf8o-qldR!QpK-hY3<)lT_E?$&2M*";9z
j7M[dJ)y!BJxwJ8J~hxwN_2,:qA07U.1,+koMfT-GI%xZ+Q$Jn9x)jLKPYSz"RfZ/`?j&"6$Sb91?&EC|<cJhdb=#B=l`m%]H/^$,IRO~y}lpNeoGTY8>ms9r.i&[,1j=GllhJuQO#+J|^PA.xi+4o1hK7Zg0rGb@qM#[aE8y/4WT;H@o"g-,G:,mE0cR$z9kE_L7MR:C(+SqQ:=HZ|[y8}
:hIGZCd$t&:ipF5IEU0:/?]o.k@3-l&W~<d!SCn;ArSFfgBCZ+7HwO$2O8.L~]<>w_i#jz$N/K.EsFVmy=@*XvZ$|C[m)I*4u1x+IC[B*e8=Mv;6C=m&HjrVB8^8tl/%mG5AJqLEqV_.
raqA(w^!JQr~HA2RrIrZD!
dKb`Z$h%b^V-Tk!MQ
W^>#@-1ECw:Y]wCsC!)z(FVv]N$R2A>wzA]B=@5IvZlb@(S2QTwRYw`]}qM6q56IV"N+4h*rAmJa;amtJwpnA-:,lPYy]!Pq!Mrl1r{_ba0@qW]w=+[vZyf[Cq5oTK;
-q!xZ,of!b-XuG`));GLkyiuX^:uXjf)hOEwwBTBuoDBaB;pFpK5ZnJVU,^B-+X4[z%`bMc)LnsvWZp]<crLlyGNXw2f0s4R,6tJ}R;fGx?M9SS`Z9`z#DG<wQMA~c#nmiLtU(SB1]&F;jScPh/>KCqskFd7%;z6xBb
+yww]o6,iy@NZ-&%]Aro:UgqKy$Ggs"]P@I7;MI`y`)d,dEBuV;6mj~
r0R3t,?Ttu<BBh]#-Eax`c#8<#iH@hq*y]HS;pZGw4kIg+pHO$nq0X!UA(2*BY@Ko
U]|f1]S03<5(Gp58GG(j_yMN=V"2i7zuMax#/4g=*M/H|^z$Q.rL1dxgIvqFHSX:jnkhldH?T!v/aS^d<FlQa@;3nOpCKrTmdt(m3bGdSpQl?iVIaCp
,;B4T*yH<orQLC7svv_Y6<8>(i:s5m2$bL`[@XQBIJS7dojJ@&?86w8J5lx6+b}t6:7IV4TUtyPb-yyvli0t1$=NAp+z%q+bW5gg
w>4qL[w=>mxM3n_%NlV)@x)+m?94$
bsT+@?R1)Kqa;g]6k?#65eL212qW[
6NadoRd+C<D8-.rJga:M6$+8(NOuc2@Ffg.^)Yf{wc+4MAx)QF?<SwBii&08AiS]Y&RF7mR59Y/o;8bo(r7xlSXuFAl6Y+$>"dEOPhM>LRe#oxJg[][iQj&n<X)L%ru^.nci31X2TN7{6Fc$+}?qR(k*Z_p24n):;%s]@/?<SmHQy=%`DM"2v-1Ln|dk9WUd,U>h]b`$d&P/CPM;#iH~TnmpB2Dr@D$6
)3^7YLMcULxiqQBn2
i/mGe0ya:1{L?k}%!wtJl+3D:03naJzsG(zjZ0n7fg4G9`BM:Wyy.mdVLv
Ov`qxB)v:kz)[.4C73^EOH&
3?V<M{s{_hM?lV>_VI38B&$mPs.(ASw?[B.%Es
pl>sgD+n!aDgUnasypNOi8I[yc/Im+wnlPDTM?.RC?h.%Oo.E_<5qnlgG<z5IY<VPLDY;pRatkmP"G8MM/CVR<_/^[rwR]I"-q&R{=.r|`GE:po4vXi=HL7:Krmy9i/"is[er&L.|YD[;NTo3_jT{3Li6;IDNKXYvI)Kn$&CqRbMppf69?J,a]Ch$BhO_%^S1]GVJ"3iqK%?lH;?B/qD-2&tOOuG0Qasf7maik>++jPLp.k+1n&mOi[(ZS}
H5]<I9/!HL[u^$RW2I`1K"SD=I+.+vKi>^W
)0_6&^)hx.LX"V6n;S~Zh62V"7>X!Xn"s8,-G0dy|Mn>Kfh&9gU]wceog<M){aw3rFpa#u&kTh%XTN5LO`R,3t5"YxO,!c[yB`St$]^K..mU[]dvHp1JUwcP>P8BjJM$B%!NP+y%"!%_rhw<J][hJr3c/`T?F4vjd0X[c,-.KI_o1iGA%6^!aHi%I3P*-KikjbcAM#80I^PK0Tq=Jr-HH*x&(RIa"Bpk"^~vbVRrTHA[KhLz$a&_g(Zq)LA4vugp@qPdy7{r1GqBYu@som-
y)S$><*`e=yC}S;vuIY4WGlsgp#[o>x+%HEcM+"27k-oInllT&Ka]C{w5ldP<w]a$FbKcx1Kj`sVdbipVpUX&Hh9a$^*.
e9^6{)<@lw.C3Oh:DR)ZlfwMPY6!{1IT/Ri=G1=8{@5d"bv6|yl$M]^4>"k)3dxj_<w@e`qv_BoLj#.6;Rl:h^"Ku`0pwd2hguL+CjYTOc
a=npT;Q:g!R.C#R"');}elseif($_GET["file"]=="dark.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string(')OsbOb3V?!K0U*,j#-$TY2N&[`b!>wsTd_N`GuxPN9GOol*1@VDLlh_fdc430fu#lZ-r!f<.+=s=X(J2e>*"$r2geZo4@leYjQ1%,Ya^fK)KWrns9HN3Za[M&Ua[o)7sBH/u8kXg}4drw:$n$88?$
q.DLTGX#<D1t"V<MYp_Ma&R!lNy=^42%5+QTJ"M_zEIVt2b&@<iW5HXxa7"+HENrVp[-(?;l^q7O9Hb]:Sr
,WOw[;eXJ3/AYxWiY8v=afr;mm
2j7~=*!Bp~Z"dLH|e`)gkNjaXDNCg,tOd/Bee9aAhUna-ZLB;OF8<%r2e1x*xX$ZiG_Ot<kzJ%FMb$)(Q`hL2F*U3b$cI[XzX_yVm!=X`6&,RA>7e!9gn|F:S?FGgzw]+AWONX6E]$Hu$5^-Av"t[SRPD-dDP9jn"tZoFsSBWi!U
]MxVmGbSp6ix~D-FZ7DoJXY/zE9!l0/]_ZhqV=[.*yn"zS|U3V:p0%cK5pT+2_?0*<"/w-9$DgzF7#yWi<W,3"4>QoJftal+Tm>(PeM9JHTs;vxkWm9$<A7*iHsBl8Ig]>qQ38jy4P@0/ej$G,X[`Y>gf_|8q*^2Dnu#YI<#>h+;DK|$/DDimVm(m`WCVEYX1jS%84q"FCpAaU/4Yf
Q<ovd>ujL>jlSK$ADUHDsn1a>o@
;@5f]$+ZQNcbu-^=v>xaijt5[sMndunEa-5T28EWI"G!j1uhd)s:ch9c-:STXv8Dq82x=D]meVP[+d`LIY+k0"G?9H47
NBubq<z`![Z&|@7?P6j_[UcU{fnW0X^j_=5(,s<ii_zJS27M>X{xnK3M[W-rsA0k}H{mrK*vZ2&pNC@DA0;NWwLj&)j-eg5PfwA;O70]r,58hd_Eqn{Y@Ws+We9XpZFh)z(-@LIrbPy8da(hAcZV#?1X}E7dx7tw`28WL.XVqgdV!&yvq?3hO5.EHdr-kP>4[llRl9i0C+sj[+"u^v6Y#jXxd');}elseif($_GET["file"]=="functions.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('#c4]`nsZ32#tW"t=D[}-dt|D
t4.fB*UvVm*X5y`rIcq94l$pS];=:p"0Z-:)cc`G+XY!YDcCJS7Ye"8kvSK!rgB,
QGJBTN|9mkJ=oHNq=
u]0z"ZHVgjFqY]+!jcRjDHI9j.ysjATBA2+)D`tcn";#,vw[ec:F"59cxe:"MJsOExc#f]Jg3B4x(I*HPir:p%TWdy7<JoX
iM~/S"3o!mqy^s(l{673#
UR+s.b5"R*rVPQ)5R>!BUk]Meh`t?]]h)NXY_6dtZ`u<ni1
t#S`]*gu(E`2MmXu"JjrVD{.|Ews"l1_B>"X)U)FYUs-SK|fcE<.1w]=xN?+w:atRAu#>28:4Fx6t0nVm=*PMYYT5c:pQ0`UuR^"GtbTV
Sv4(hKlWQ)A^,D[.qZthMKtOz=(pI`auf/O1Jk%HeF%q2;XksE`/(bq7yT!`{.1@_;~Q7jS[7L2t="<22MYBka8;8t=v.Oovq[@d"hqnY/`L,M>^Mxaw?CcwvZESN346m,i`yy5`os@!KIs
LwX0>aHRquo@LfTs;:(QjF]$(c~F_5+yGP[WAhmMSqi#mY5=2y6[u7$+$G;5a3v.bZOGDv:m:HuWpq^:6yhmi?e&wvYRYq"=:9cRVLDk%LObrwOwi,`nj6crk7rUJH48n1gc0]3B2-l5|qN2!FVaqNLA$Sb-F)5B}:)hPrSo,E#]l:nJ:h]$0u9.Jtj]KU,.Z3lPi6iJ47DGK#ZU]O#`SW[OgIS9pM:
3*oro2*HBY`+ddG/M_juYpRw-Q6@chBx[$q:)#rJw!OCE[@#xk(.LZRqw`>nJ-"#8opCF`pa
2r2`a7cu9)ug0t%KI62Q06yM("TW<3sgmUjOL!=f-)x7T5A6.t"]LwxS@-Zvy6C]G]S!a5
iXSu,;tb]-CQ<y<l$(+Bvx%FpjdKq537f+!1^v4>6BaZI>zWN"RCYCy$
?(>9Kt@N(S8I?qTI4F2.2(Dou)eZ"YmpCZ(=[vNk3z:!tWTqB-UZ-x7z<7np_N:EUG=#,`DoAKA3RdcOfDmS!?m$
*hz5492s_]Y=:`{r418#G4@=u5Q?-
70qTmA.U~PmF"N0=2P3w_nu+(C]6el:LD+q,<@=U2_q/3&0-
;V8vYB]HUVKkuFNe1,c4MFm&AYT9uH8E+ia(6E:mlzZ9>n$t.Mcb<~qnp7$`SuT{_yrN5NmfpLyb(K&+o3SVsu#1Rh7O?h`0Yfoeo0s
f|#]X6RVTDd?ndC&kAsE*@waxjA(*.)=Vi=7ofQ-TtY3Jpw/tSOG>C67LyCrh}Ps/whiIsu&De(lN}IG5HK"OsQ,&e-HG~%_?q(.g8?#N70!A?cYW
o
XDC`S"-g9PBCP=8+
d>K)b4KSJ;4f_JHM^k%Q|o^yA1%#?GG>^x#lnL{c$s}jpy$sh_7sL6}Y&&ML+^NlAd+4,mW/G^NS}9Gp5EaHRU#8-V8KB0F(C-UyCZ?n]Q3Bia"FJJ)z(%FR)XSiI$U&<f.0JXa&oE9>dPR+dB0M@yiIV"
cLcFA0&*NCIl3K_3iPNuE>.Q<B#3H3eqx.%?
GGUL:<nOsDU1oH1XI+]riTK`G<:0/Z[,XiM3BOL7;^$W)`X$;)8jXcN27Q$v[3Cb}GaN>-<1W]o=%HX0k=@3*N+CSyw3?D/^>#AG>daACASsOj"?%wM!rLf!|k47}8$][5oTW2B16"e*
j![q(u?3gj._<w6=6&@vSNWGNd"sF`M(J2]Q#~0C"{S[k&s.%8D~8{J)Q_H697M6y.I_Z/2c^GrF%6*RH?2!XO]N0~8&C2Y(^-YTXOS=!>Pk2:<GrA.+MfW6#R<n5iF~,l&)3tRMxJsq4YRQFJ;Txe!6M?(XlCs.K`X*G8HWf|LC]3$CH|W(,]FdD4ZGS7vxvl2w]rx.2;-]A9Y}+,?<#<;
)?PiuI2"w4K@KmuC%m96tK!?pfRhNO5bH~<%rAyE*i<6L/<7Zt3tvphyP-gTn#`12@r]s$d5T/
6cjl0%JXj.10y,p8Zl3eR/aThJd^L6FM>1*:_K>X.rP8X7;bJ@<A*Fo%qFCr~GsOq@+9Z_zi)O[R-NB_~]mDX"3@PgQ%r(N9c
lXkDYS_p`!CE#!Fp4=|m=f>q#3(5F9Hu[$h5x11Qq?pI-0!5iLQcqExSu6/DQ7]8MD"Z<_5XQ[HderuwS)."B1"xw1anHU}y3!(DS7%Dn$iRYqO-$jZ"B"a84!j
t/
3;FH*<^iKvAEkS4F+er>g}?Gr~X0j*EN.?hs/J2UaAw[[cq4c)K`AG8mDy0ctk68<|c5.o*@LmlnblMo^_Oxof]FqC71!0LJ8(_H"H>^cYa:=W+{lHYg2L:pC:CbkVv="hAd88X1qC(qFJKI"C!jAP8T4>[<J(o`wnrLpo3NdC`-hlt<!S;+?dG<nzv|Nv9DF66Q`rR+P|4k.Vjs@8/3rc%
UG%]&".PTx9+KKX*/ZjT4L/)Y(0DThSUl_+]b_!%8}b/5/T-tGGO,"Vl24P
2"H(xb_H+=$!N3cN:2S%KFf7:3C)UR)f[uZ#[NDFE}Bzm*/=VjQ@nIO#3?Mjq7eou{p{H&2sEx)?3jNmtxT}#P6]q]@9DU>6gPc4Mg$t@E$^&/L2Zvr@"[d|4}vYe)A3XGlT.MC^1p2]8+ah2XgMXdo8IF3g*uj#kLL?Auy`jE[e5qw.T*xkFxqHB"B~;.q&6)o/b3?D3`RZe7manPmMF#iwilcG68p#Yoq>1!f%dLv2ERML3R.35>!X+65d;kNfZU,p$)<xY,D<.el#.7,dWqC!!dSK2^
|_5nP.T#)T:ZUxHpLU#eIXwV~=E!&o$iK$-/]++4o>.D+X9
?JV4#D&-loig*#Ao.*(>&nyeC2ZT14+`Gt@qD>{6_@}s,U^^G1MBGqOf->H9zUz,lg!d;*U]U`E(qRh0op#G:L`ZQ(&G^SXv=Y<]#CL9NI}#z"Z5AbdNcWw++Ktld"6kBL%U`hIh9vNvN!v+1r4S$IH`aSG+,0!MmN4T{tD6w&IMk:OZjR3F&VZ=}Z?Vk)MU+0c3GTEM-N5nhFfuV/;L6k>2@N#H},)F/aKk1Ll=%mv.u<Sj+PQ!KGZbJ!}cpfNuc<=kb<:T"e6d?S-s=g?9iu/cMWMq1LG;#Ul@<Mof]")YvT0Gb2,,s47OEo#73<s1T?pGAvrS{Wgy.Hz*(!bW]W@]>5"pdn^@bMY-:el$`SziYO#w[6!N<"6Ik%~O@<%P>w2?mBkWW6Mc}@7%G[_mX&bB|)cpOF3M[7`f@1j8k:.hJ$&iX9`c#asN`es^{9xu`K5JblD<X&pWu5<Q104/u9M=Y6uksmAh&5gJvgViq8<8w5dCJelSwiZ^0m9F/dXx,>K=h0Gs$Jf]2PY]7qxyR%h$`4DtwBD4C2WO;GhRyFA6.RAHq2^KxS$.FXKvH@IBV=5Pv1_*Fd/s+6Ms+T"P6pKE(.nQ>SU!&NB`"A;U6CJ-%gi4;Dl#!R,JaGJaEj(5+_u$9!BdKlaAA3@E]*^VYv}V0X=)="/G|Cl,NH)lKfVy=56yIeu+,irBa;u=XA&J&Ex2$mR%$sX_-VG2ke^-gOze46&g"t}6V$L9cFVx4Q?2RO
Bz5_Q>+65nwf5B%[o;>P,->h2eA/`wk6Gw9ZmIhi[uQw^leE;q$qA7Qfqu#~P0`9LEDu>AB;^0JJNzb"*e`W=[1y->UM.uAlIR;Q$46hCPg5Z*p?4f<+pFAyg5IsR`Nmk<KA?j3dLjL%JFZcaYXQMt/E3!QdL0La"7d,.$q(H&xuD!`4#XK"Rj4|-xx^nBezY0MJYs$pGuhfO_2ZZg;KAdWpk@XF8yb!9FKab[@DeX:1fh(p(CuNIfS`NnK(%1COud!^#[J}N"91Uo[e9?g@/iJn,-77.[!LQ|
B*nO}N_7HfFN-.
d|njNNP+`
]cqqeUauHk]/LN$De
kK;g_&W!iG6eu^F2h;YnZ/Xc*r)l7>_MSmLRp%ecl9"jYYiiq,ALRVm;S=!&^0cxXEV6$oPHAoDvuGAX2kbQrMAe5-LfJ_<sG$/Qs#U
"L*5P?]zX:m}/1$NtIi5kvWE2:FkHj>b-P,M3J1)`59}jvI8hHW69?@2kGPbU-r2J(fB`N5Ukk2tVI4W4ict"H4?4@6+@^tWV)XScBsu@;w!o73,J.q=m,eUwlAx2X*z,W@6y.=U)kkBb1YO(9a!Cs>*X*7fk[hW7Mk-"R-?::>M;.9b(;o
Y+]VQ|SY$ofnrB;%Kv<6fP.julJDeg1]<s..@~Fm*0&qpU6k+x6:NBE"]`)0kpbLyWdNL8"^S7^}_!w_)/+AR2TPC~C!G=Ea*O<XJe03@N0Ye]s8pO8S%zG90J)PhB>Wi*mw^yK+9^r6#y0X0k@4+EuoSXr4$($kdKOX-qb8RFv/m49gGTUGi#[FCG1q-{XVN^W:6BQAhI]Uy<<lxsqfj.]baSTFw>_7>*1W*=DJl!qu(__WOGN~b-
-_w+@(q2#Q;#);yJAe/Qo!"bd505"(RG;-XVBLM8sd%SV#5>$=EGV4gds/#bD_yq-
XDgOR[Z4k9`pZj^5:(a1kF}KnqxFFl_dA^Y@
L@40<"tulCj7YW65VWwODf%Z<Ku:kCF0;cE43LT{^MUi/[Ma<da/Y=#ybF<bGw.blal7^FNL^2#fZ/
l`:#5JuGEk>L2B^T
>9KnPh-Ojl1@vq<K-W"fUJA$;^IHH12Mn~`vdQjra6ZM(%s{ew2(
xEor.G^/fK3aC%G?>57
J4f5/9K>/4^e{dYD`<#.$c`yf/kgRDu/ac^9$ICj_GcEZ$.IZ1T?BioePDggn905A.|X5sW.Zh&t/MILlBW5KCj?
$7]2Ee`/*=$3).`_6vK|DyKeV<@+OZgp/+$$?PJSTvLPRipBYG0e0
ISMVEx5wfkjX++5w`^
P2,%wufX]sI]1_!w=vp"U9*r.RkVKgk2optJ6`7v[q4y%*5<e2ml&b[`We)PTg<Fnus28[-&^E!xQJeGh@h=s__]K_|Z!ddcnICLCB/D7lpm:Scy94C<jQo50$aW&n<-jFyZ?]_8hN:8}1ScW^*Assus>j0Z5]N;zOu<dnomhdz*xAq]Lg(cu/S^PlA?{XlkT$^](rY]
F
j#YSoQ0b/$Q{p<$~&g
Bc8!>TF(w&9x`w}?3
}nT(?,K28v0,4f,%rriDZsR!S??qx-m6mNS6e,OM)o9%RZi0e[na)-7T
tNVmBy^L,<Y"a+XB=bY6Hp-F@xbl#,F6K|ZX(@&W!lJQw^tcUD8H3C<HU~m^jQl:y}SeM[#RGmYqppCi,{q(NTG3EBiL&-`]k(&5Ky.MmBvrbf`9.T(tXoNM%nE=R`NeU3mwC!Fg?;_?YK)`AKoId9e~d.SMS;DrX
5oe!jrDQ<CvX:TBQ-9<oU&`H$"C~V+bWD4q5/(oS@D#I]eV9_O)$jd%(EO%e><B)I{_D;mo4K#p-glp2>,GQ-GNc_G@|L7o-]5M9Y>R8noaG%+Wk=O-$3Lw*[A5_Uw"}puTldME`Eti7!]w`)t:ck`8AE9c]b@=0SC_b#6kXVz=h
{%x%_<#($7{@kX<G&Q}9)DY3jaX5i
=y3BGohic&C+%Pw=+R2"^(o!Q4BNgiHg
"hm>j|D6e_w
e_ief,"rpC-o`rs>LYExooYO`jer?^@&etLheIm.rt7b(yo=7%`!.kx{^!8TDWUG]`+RI;!3!n(LLI
#W?YwXkYXI~ym6F3{8qCp`%^}]]^b#1D^R73+hj5~jAV-Qp98x}>zkZlJ1wbKnNc(`@4*hZj"PeU@J<&XG`1zc3HTg-s8(v5f;sIKEBt86N%?*fdYji$K!X#rhALBNNq!<(N@5u>5DS9^q[JrMQl(2gkEt8S#-`6d;ewj_f:1P8e)76y`%S%2X*xO1d[3@:d9<zstg/o>lKL(E;N;&5*D@2KkuwCz->-jVo<|0be#7lfG#e=9GVDl4Je^c-]]$,L"A1S%MVXd5b.8_R.%W%93m1"Wm!r&bSJK-UZmUTY)VjUz87I6s{ENVD!i&(,lU[Cy7W!PY6o`ao,v$[Lg914?Z=oJG$91s0_=uFeGx{Aq6^oAUS#M).i1kKj+(e$pKCxxE0d"K/iS?u;w$HB5$)Ox-zF-BZ+Sf|W6_idJ[C(a(F3}ne?Q"nH}KB`[11h#DZH`+nr7A38x:M/{WT9I,Em]9Zl*l^iWez$zw2n/5SHcm-OSl7jiUA,W*aD"GOm[^ihN%+0fB
NjN]@r4KJB@c5~4q%{20EPq;QqQ!J-QuVT6PZ|]3_/4nk?i/Hi^<5k;t(soQnG:
l-:zbQA=I*&w9UD3u(De3kq2PN!Xe-ZnC>FO`DLYTLbwSoFgO72U1pR_Qu2b:RYRLVNSZvn4w{jxOkMvB9]7>2EVhZCk`pdeC5j8Swg.R!+ae^_JKc0N5X_-e%qpt/qbYJ72:SRlnZLE:*gT1|]Qs],.;E/a?Z3z$y8hR+7gg_mG0?9|gNfv0PF8N.d4p})r[~U65[vnmTyi339<^_BQ
8-5K)nrrhc="E0+`$1UjjRaZsmaLSi8_#R&As3~Q#/v
~U&S]jJ*t(h){G:-@*OkMGu2zssk1FuH!Dd8RG+C#y0EZoT74imHp(ILbaZ^4K_Fg8J7T,vQ{P(?o3T<zFbr
aBJV>F>:4A
xxrPKGl>>&pc-p-T25*OZ2jWQ6rEHt>laK5DzPXpPp|F|2(e*YMZxC_8G</7j(SFCc@nqycAog{1w1XF+=+cH@P&L`q,>Zg71M+O9FyqKry<XWT=k,0LV8c`@UX.Q*C:<M8xd3%j:8FARsiow9oWig"I%KrH,wjnl^m20X1=I9mU=0nrVxmx1$6*[xq<Sh*AL2qR6H}cwp7k<k
O>yv?=x=)eaBT4R#gnfXmG%9[f"+`3KhPm$O@(<%`X%EH,fGjQIw.ZRU,sG^y+E{H-AG]Z3XIc.&CX5qe}?i6/?xU`4,ms[msE;+hIJlYyaM*]vt9|8@Bq@Wm|L7AjBS9u4m!i`Qfxx^b7xpC}=z%_vVq/Y!fRf!]x3|Fbv
L%4H
)"}SLFmT#d}ZOM{K<DuX~e1FwxzSw@cXH5:5Y+_[o+$G-#pVr
*<;NtQK,Soz+>1#O_3_+mWeiW#:Fdg",{ywL,TF.ru4ZLS^xEpY)NCA]2PX^+rV%~rYo@^P5Y!82}=X:><[3hR:(1=n`K$]ANd.=cHDiY,Ac*3w]uAR8a.>cO,}Kgkjh[-xY)p/o>^FvHXgpz6jn.0&(2xO+meX<=lrc2S11?ge.h!SaeWgFLQ_pJ7+tg3vB(/kGD
89!C08A
<vM*gP6dEerP=u}KF>n,gf}4!>]nDVMw(9xORt!Y{
!-."[8#5sRlnO`tHF5xU&EpYir,n|sK>MNspdi5>reb+Vfk>nYV5<:5j
14"$ZClj8KA9Z>U]8YQ{Q:
#dfb]cl.wx!r+@1#9GU1Oa]+QAt@$N1#~dC4b!]/irlf3RY?p=T-kL<>b,D8i`VZX?FD5g}kNl%Cf3MLx.DVpR:$7RQoxC9tM+,=QJ%blj)!a"Nr<H1wT#XK_;8gmDN0C>>ERnz+:<GI&(TXZYG#gs{y.
-8N0[+BDvsj:LJT[58*=,u3:!p5a~l-+RA>`3q_1JxW
p#%ktN?`NrE>v({&>8^9D1<LAXu6l@!d]3x6|:sLHl#DfC@KDX8ryO&DR
4p{%n1c3sG2#@B3Xg;u/4W1B.9="=1:%w#dan.7fBs~x&RGB.,sT9R(=3V<V2A=Mp3@sH^)Z"CA4U<ZHmJA@b9BC*RM)C!A-E#eLUU`u?0xZDsj^77L
I61L3@p(g#Ek;)gs#:qlVU!Mo-t[&8/_lbGC{bUhVWG1#IU-n7tjolM[#apFlSZ]tjtJIAViBnwvH)DxU%}&WgUioPAB1Z^wy`ObZX9u1E^uc9rv"9}4diCW;4ncbK4#{i|kZ""JibYE9Yb]:29B=4y^e:z+~Ge7vS8uM!!CB@5^%&iF?hGQKK6#4>}tAY@QkR!Z"uV6PbkS3yluX.(&rVfxVS(H_Y,_cUMe/FqKB/.u#uX
)i~D2[dJpRK_vgU^_s)]qGq6V9T8=D<g(5Mdar1<hVS0qKg-e;5]CjccS&2=/FdJLLE+R3`

NvCOFjU*EFh`[NYk%5wcT`i/
7bki(A,nmSQtQt1cTMan9Bmsa3lO4<iYE
9Qb,#d69+OJWNM-4PD2,@xgaz/j)I)RDj:wN2w]c^Ol-%_mhT(g78neS*Cmuv!O71#-"@5=hiZ.ct#81j`Fwmd@3V@)gpV}!#n_x2l2gldYYvjZ3C01dC$|aVTf%0Jhfn>uwE405xYa".p%r"V]+_Y!-z.S6b8Dv|/z4=1$iNsd-k+}"fP!Gth);87@;?#(11HcCTV|qv]aJB(M-Br5eU$Mk^KIOFcpp0AqGCPng0#0e2<kGlM(p`1Dr#wHdl(=.Fm;C<$i,aP5T%XC`j3@X"eK5R::HT]>CxnpOF;}9xtq^?%)^_C$D~H[P~DDt@BM<KA*KOKnjh>c_~>%O(p8*o+ZwzrU@.r#YOD]:}O?*2?k$,(11%NC$"Dp
[<)XGe%2m[=K0f55SizBx&l_C8ABb69D+6Gi,ka%jM!:O
d%L"13_j,_1+u<;
15nN|>E)ETIt?OBZK=/mV$<2FCd
-
%;xn2x
"EtA"te{t(1NXD#C$
XOJ@bHBqTIJ|#ph$1ZN,x<Z{iWInTW1pN1adpz[r"PSc49$v9`+2w-nSMTUZ;d5rG*C}8[EAgGKDPBLLJ;*YNp)fjP/!<FZ/3Zu[r6,HraEB&d1CV+(KCY]@RHdLtHt*5FO@[=04HbdYD&L
p`n++Exk%%$<TeMoQ~s;e2
}F>0{3(8UatX3k>BFl,_8^
fQW~K^i#"PVHv!0sgKE<ZM@zh}Oj^k31B?"0)5JlCw5vXxe6P!hiT<gbBWqy%(#%7^IW)o!9Mhbl8.F>)(//N-?3llWK#QNiF+8XUR%_wOt5.GN;&58(6:0g-%u/b8CjIyg{S;b8e%m%@Vj}YPbBMktkH#`EAyX|#-^&^JIn`Dd_>Va=/(K)dnEp*_Tx)Fxo+o^AWu[]
&WS3t&kUxz!M{_vPLY3P-1)k0cE.^stYGxmH0g?j+p^v~2HcU!OH{dhc!8A`q-?;I^|V;&Ipi)^,Ll$X{<kZcDRp*S@Z<K"xn2Q;>iz!z(Mv-!#jk3_f4)|5U0
IE4^UfQ,Dqp,W3#[[nnj#+1K!g(lv#IR_Z7`#ciAQLsY6+<YXT.+cT@]69tzUpKN;_?.5bQy1wM>LSK5PFXrXxkL3q-gsn+]$9=7JmI^BR;6+hlKaojKWJM~64-?RT4mu
ut^<4zdo3rq;1,HMZe!RmXGZCZGux=Fk9O@&!X3|.I^h4y`X=):zm}_Dbx+nC{x`j?@gWI9yMJsf!iwy&]yJB~/Bfj>C37%.o7R*S__S5*=EHvWO=O>H:no_3-Y93!Ydy~+@2&J)#kY>Op>[DP3<A;+KGQA0R5E0ZhIR[B*|#a8,ubdl]uX{?Ph<W.?+W*,J=UZ1f;e{]SRxn{t_iI@ttkub%Dmq%j,6*g
t(h?K=TC|n=/~[E2#L?0r<2!L3jYK8F)y2mB0vm6)i5wq,|I[exLC
1#pJ>6i]VF!wWDeT&OIC{%F=nUA&EsS6(3N*2YeG>=8^?Qh$~DWdI<{j5TaB6F~;`iQ
DI6-BkU3]x:`Qy{--!|e;xo/9$t:Id$Hz7=7B&32dqEB%J+@+"Fue^}t}cykCUHXUVQs,Cjv)FeSOf8[t?r,+T_hlTj=>[^.?q?%;gHv?+G#P&.%19-9>`DPs1^!k%E-
4Q9"d:P5PgqgkH@0">iu);)j#0>1-?q"<{@}T8GO@_sV<kl`wKE{p7fw[
CE$oi^2aUBf)bQXZHFhb(Z8bf"WeSIQ%nhj~6$)_MYO5[P_Us`/cenM`&NKcrX;zj3UbBQau&(L%5c17PZf^>C+1,An9`P(kEnS60=mQE{:(Paq<Q=4N.omzu`;Qs{g
e;`s&UXRgiQ"V)Y-I9*D[e*VF!=fIM/ZRQ3!m/lA+*0u>a<yi-
iM
N>s_=WXXbQwVLdH<7-YJ
DGF*OJm/l_?nbf0B"53OVOX%tq^:l._nQ]F
lmf@2J)%<hqb#<pJxGqy^=MbMAeN-EW
kajTDEHh[]
`"_c_,MpD`n/D0?(.%5gE5=fZoS
/=Uuybaq3hB5AV=`1LwS:Sl^f#9;,B(A2Z"#G>/QhD[x(?]~+%3Mxae;Ey]^(}4`BekDC6H3sE6$P~a_QW?y
*,<aT[q?]%jpZqtIeY{BP#rh^++12b>H8f[:9o$Ex_|I`Pa`g[rD3H03$<xuI+IG"CP_>5[b%s19k.o9rfG(@/:$Y/6"<r,j&FrN(L^Cq-GPn<30/N(x:%}dej<6[lBy^Xy0HHRB}u)/@MRhtp3B}UPpMqK#Fl?jlDkdHJZd<!k%|Rw7L`soK)Ta6Z@A~dkqL
qQACbZ*g4T{Lxb):8x77OVbGa0Scpj33~==Z-1*7y9sDd_^2pUZ:tM7hhdh5n:CL}fJQr&9/Tj(%EZ,M58dUhNO-u2X]b[TRE2hu5:h&=nHZ:LoVUxRh3]FJKW},zNV.PT?Jx+nh{F>KSh]s*;q"*b!JH97/OoR-M51&U3pRiV<(,/a
,/w<*G%?sv2;P$%`+IIVs9IB9B$j|O;[~hi8L%gB."
78HBVplHr1fUm/?")_u`IMn#ER68s+/_plm~(v3#E)`mPKtX;+V*u*k~;v
ie#G>=RL5-UKBYS]1^ry(C3MBAel@H"9g>.+$i>xWo$n<L^7t`yqj`kH7<H-5W.(W$EKDIm6}!m3]*Q=LZ_L<>GNLS5c%,njvMV0TI
fanRNHtE^;^02x;Oyw^TA>?&CRDj$|_%ST#t<uCeIM=f+1_g]RxBDxNA("7.ABOEsX@y.$l036_$hpvLp&_%yxxUp6rqgvt&wM`IQU`9lrxDcgPLnWokitnbGDEpH,R)BF</S$C^H`2Q53wCu"c*35mlq1upHBP{w-GK!6jXC$^>4rbT!N%.EX`dvE9KDTQ$$9T9YL-.8rS/t|oc?Y`J)%gP#bW)p
k
0td6u7b].3ta]7>r
G>|scQ@$-FB^>8sLb;3LEZBq/UD4m
F>f0i8MeR:vG?:rlH-tQ.J%.?RycIF=%RJZ)2TfX5?3Ap_*g~
ocushI|
7bz,!^Sqcl}w_Hz(q+9"!`NlA6Q`UBKqi&NANr>Fn!=EzfU,w>9%WGqEhadwh/>1Vbk^jW0A&>{nP+klPM]V[Y7%,b?<5vxByo1h0#a4QI
vM%x11_CD4_$#vi<h|-j@OZ]r*
)N&0y/Hbu2:3=L~tvnE4)xzp*
[TTm|>IH0rR`mYN%QTnrwh[3C
Gb*vUn!l2FKK4:rXS6]/~HE=>@Y8!"G:^r:bJl9LO7k$AH5ZsI,D`vmX)dqne>Nf2HG;1Y@/x^I6BxGdf7U@1=@KNl$2H)hi@JDvKxbJa.03[Wgi4FAwYe=&
=DKX@!jpVyyT;5T3an:/lq-G20?eVS$zxpAK;5eF),@#McMz?H0`+x^ly)"Rdq!;j7xDwI>UE5$r=0(Qs`q5YZN}q]"1
z!m/<Fxf8_0c`&n*X%3DX@NG8p,ub`f/>_XiNw4oy]
ngMLOnHD_E.nt74zFt#KBf$3-}FwEY(X=rLg[50gK_
4r!&J
g$Bz%D;,A5hDYTdI7aZV2M)t8v.v-iQMA+$bJ[MJ)2&VeF!HK.G({-4gZk7o*B/v;[kK#gE#&g.+w0GLs^eP+rq)%.}v</L6%a]AcTg/k"xfRo0)GHw)18O]T#sk0/v`H;3pJI`j55Cg.F0qHwB!hL1T(ZdXmX;fk5oexWD"1,(3W2U+H"j=i6Euw>i&IG0*OyeAy*6B)&fJ],
(gx
E5z"(WS-jJ0Jb:iiJR3uv;O]y)(`HM!x>Xa;n<+.MScs,6W?z(7^Ii7:A6yf!?umwUc6opAxPJ-$uCqwD0[&3=*gk`rNRM
sNZfYFI)QA>vB4%5<l5-zwiy4Y3J~87x#3y-sld=os03+Lnl=(7wMM2H":K.FX6)S?(U`khlSt+R03i:CWnQ^weM_GMg+41iNEkB=nUW6;5h83]__UM50d2oGC%"=1jl;L]?)/@B
pW+x.rpE1i3&P1[!*a8%1|*b(bt?C!c{eokbJ8;r`~<LUbx0UcaO-;iHSNLUITGM6t(mWlSQb/KK;Y-/d0F@18FN)7)<mF$zc3@$1#`$T7PDeCu#W,:1hCr8GE%yw)e%4shqf=BDyk%>>p::C$(B)qukvmYIF0vwh&NbQ]O?Kx
Ewu4]_%FX[`w$4hsUY%fLB)R3F51fw%T`5Mqo2vg/:iA
#Gg3Cgu{k.P
*mHcM}J)>)$
=;>CPIU5VcZD%|ASb]qj8@_E@}9O!r!2o&-j:V`ROIFm68n7iWo1duPL?CsX_@(Zc~]5B#H?qF
p;u7^bxtxS|ji*tOEig=4^ju"jxC;gr#4bv7"TuQC
3vHCUD4kX>QZOF:@ab:Y3-[C#Ft<jPUbQn+!!W:r"X6Z(xS_YN3%[qir$&4qr*/#nxl^P`bkNj(evcX>drtNd+`l7r{u+*rTS`OkU${%4Qe1{)qn^SXi;$I:#)MCE:>88w}kCKQVdUmKfvQ01b>mJF)acu=WIqC4RNDY="]00m">oL}a_cX7:Z=$5iYW6An
Ct!y)R]ma&&x$]s!:l[_9V#3Jd2HDF}0#&L@d
O?,lla:TcUMCh$z__.Dg,j92wO{=9Fcy5TsfYq.m27@gwu5<7W@uH6Vsm5=RBm}R/Q;#P3(!$!d-D"ZL^C),FfdWs/LZoL1sS=su=T($L!uZ^
KU3,&YSB[qf>WVqg:AbyW3Nj$pNjlgw8EVl&
$SxGO~j:v[MM`0ih@~EYV.Qk"(r<dBZcs+:wyxO&*uLF4eycz!ADD6Y")f%J,#OYE+2VMWd-4qgnhPAIqNu_+vcM0q-A=>^Zt2BSnT,w=hTI`bfy-+tuT:A_1!e~NV+bX
(#]<u$Olv}O1AZ!/kq9CgW/6)TiTIC@=euja2MNx#hAA/Js25hSTo-$i;Y<j3g/UPWbnUeTA$QGtg9B>*N/Wr1v,WBJs@P#Z;qF1wSgYW:4&..jgkovYg*JS_e5`-@v#,O%nOOioyH(15-Hh3nn$^nR/2C*]EWDr6kyb&-(5L!*@."1xt+faV]K}X/34J3lY_POw-P<qwSEy[l=@sFsu!Hi0/[`UxBS%vao>pLBLJfJ*DdG$]5Y2Dw?z&k3ut90:Tt3
DaTY!KZ%:;UtZ/m]Ilt-3dv:vV]]vbD;*nsmCT4L(fi}v:E8)J59DOemv4,_C]
YMgO)+?=w(
bIA60,0r8F%<NflRGWwMO:RTWYgupP%eMI"x]irZlWWrPsVQ3l2R,>yM@mp]
3gCs91L#S*cJU,Hk/
yOb7!]=Z>=3U!VV+)1AjS7S98/"<*("$kiF"@c09%NK-).S4D%Dbp+)E@w-
`FiliY+y`+5L~^]ecO7d+uS/OgOlL1B@f`{jMjSa6xl`4^X/$Z?vojO;qZt<|.b&%]Hf{"(w_2H#m,&a="VV5A?.Z_E&.UoS
swr5l",NBfp?V3Fspapl3j.;T`*K$emgdA,c&"vf!C:62{+`ELTt@kQja?*NfuG9t~@J<YIDu#YrA9"so
Bp-*yft_0pMdt[wD*blw(zuxC6`Lf[AVR:w)CP/Q/Sj2GIdyw?*c6r@8.~xlN8N=c`&UwyL-d}=cy9hA37G3kAI7*o;1osn:Y9/MN#[>;{-"$6/J
Mm#6Y"+=eLK`)1wV+0BW)%MEbj[m]-WgHm"VLVN7lFmwX"+O?o5wz[DL;j;n%spE*rC%r5<..M#JCb96MjZidd{BfpgHo!5MTp]qch`:db183w8d]4F%<4}y.rJi#95qLK{b=$:YWx>os=o%ZoG');}elseif($_GET["file"]=="jush.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('#hc]XHAs"H%tWN|U.+XC:&wAMKy$DBB;E%AtYhLA+imQ4AMgg./JRSnW/1.cRLJ<B*mAZP(Q1gkL[tG`6w;k3MBK|_>4u^FE>dAo5bgI3y9h[R&)L6qN=.WHKi:x"L}^7-|IEDZ7|UVqhf}vj[kKs4Cuxb}m,v[X,kXWY!u4Sa00OJ5OgXpvL9V[^HP:x3rw,d7wdea_HWMj*553va_7jr#]{_(E^919EMRc_uya:uvLv+E^FMJlCnKrq7kmAcJI/BnoIlKh~mOBoajr-
Q1I&^*zZW4^qkjxXSK:Wcyge2y.KthpN(tkyV6sJl!qf_4;"=q19vi
u8wjw@DW;,[vVe<RRr@Pjt==!5FF5~FB9q.7Purde5VF7Rs"5UFmpU7DDNj8%):,Kscm/_GdR-JnjF"oMil
eWLbHw"@S/`_*jPcoBZp
IyQGEG2e*ec:[3fIj8?S/]F"7xY[($zEj/GoM=((Xar-4e*q_c:*:ni>-3+*}H5iF90(BO.@jWAfqLLP(J5=uJ9<i96kzaCd`fYo
[*Iu(P
YpGIeLi7HMHX$lS"=y[E~[cy3L^Mi8+bLJ]F(VNr?1jJq[I&BH4MpiqPd37ivB>vRb^+)n4:9uPt4XRZ84."CWfcH=V<@2,J}e`hs^8)gtJ.*z(t-QYln[:L+CVf7W-vL=X48CqdoyM+~aM(rMfsfadG"XJr{]ZN-ICuf-(4#d5DR+lBWkN&tbtraUlhlkUvF4Ny^<46cFuYT
s#+%uRd:<I9@Mw+-=7XQyS[h;<~7X[hp#A>n{f2WqauN?Ed:0dj2o+qp2M7df)8<qe#V$56el"d5%^C@e
TS,5X%%^@sc/f&*L^%;tGV$U&IkX0uNTq[`g@(d)3Y1
zFuqZt2b-+SQ%["A>L{
z)|hYQNB^*0`UM8BQqt0Wn:jvn
n1b8WLUysWuj7.eSv=u*M/1*JZe_c"/kI8;B;<uT$!!9>X;Dh(s+vu)umYGIvK^)y~rM#oC
J1SM>XrKCY!iZ6C+PR0G:}EOse9$.[4[l:&aC
-
xrWUxR^FZ(Y;
ptBvlYi+FxC%vJG6%0e3&G^T(!%,OWpTdB-m._ekpYNRhwU0rg(rxRm[n?9aW0]oUq.lBszD?TaqOtn^OW/O2qUnROUc}6T-=($@q])!CmBByug8DMpG;<oOq.Ix+I->y/Ly(;vWUIH6nT_RpB*ay<cRR+>+Qt=WhxS/^5R&Xm>@$%dNy26("g
U@6EMJjkl[<YwVq2.~_0:>4q/"nC3rnsksGXS`?kHGyd?EkuiDd6P5`?
&BGNtv>Sn"NZe:;vh]VY/L^yz(O7.PEO>W<FW)#.:,ty@6c$;fRDrNaCX4.
q`-Pc!3L4eXXYf{R$mwH.:--fa;1ugIiC3B]l3OOvI%Y~,#[=WeyDbCAl:c+-s+k.-+l&jEJl0b]2F@]XuOg7t73`-+)ilCo1Is$$a;l
lHR,#c"+P1L
F#/FNbp@.207wP!T!~TIC.a2/lZw?veHalB~H44r[#XWcidemla:ek0uM5]2"v*Lk]Re"+>8i:N
vs^
mjQD>jZ4b`D>
4vx8~vmY#*wn78{X]GQ#lN1%!(w0&9eGmG~Y2UJs(QqbFs_LAd}gh:pD/o00x-&L`0`h(@?XG=Y*Fj2egAZW|R$UOU$3gY*eEevR7v7?ZL}[^rPvQ/R0`!u
jkFQ_glEt/mB{mpnL@M&DF"=&j]T!(Qxa7}!Q?tD5-bpkk_"55CwR&b%O=X-kj`Y,F3x~
&tU^~S!gxd=2=qehC:PSK%{
n2,Hx8.!D4)`(
"qf_cD}^(7,"x<1tUeC_f/,N`7^-=gXC5%gvQ?oLTR_+G1zD&7yr"UtKPgkBz#qH$b}jW6ngC6#S]J[VWlKm(f!Ct"+=rAsB<UOz%i^)mtMs1u8e.V=!8$RXjK2Qw08xCw>O?3>;ha$8AnRk<*PW+dgYuh]c12M.qjeeJf?[$q=ODhel$rYb.!qy+&VX~6,QmQJ7D
J#9_scTw|[`L1@ocd?R/+aQ6PhKbmrIK!UMTsOa@=tDK=_gVo$N;s>Z_Nr{9r]6]y`9
7!F#1.UDNx$Rc:XY4SB<k$Tq9Yv-/xQwXt}]6F9swO
d0?%$~J`rz`.2NmEwv&ac5%=D8H`wv@Nro
iV9rv`1wRg[_O&F^|8(S}%-+IBBN@JDmr(]L`i5vv(LmYr}C__lZf,d
i!.v4IzD}1|9&@MA3iT6Z<]<--<WS78QjHb7(ug+>qwwO[<_]NDIAu~!wwPwoh#1O<D]nM2Ygorjer-vT)$MusO`D@EZe"(!eCeU&_a->MN..
"/%_|U`qX[Eb*c`=3XxNc:z?Jx)!V*oahuQkx"r3^g>YG,(*"y@cP/B.dNaL
rSA5NQ2&p}mxsq8>URYB@3=`n2^6]8q}Tbn:e-<D1*4TF]E^qrE!b2obLE(_1Y.1.H]g6t9:9wU"]/4+fg]s>^:#q,,CwxVw_EC!W:d)/z*%h:/JilKa&%S[on*xeqN$ZWcyPEQQ"GQr)gwF`*/5y];/+]lrIXs6
Zx}xN3M8@Sd0Lz&%;lj]Ey$XfZ))Sl{SNgp:FYh?5G.xtVP!)r8l&bXoi=2ZV9O`Wt
z"ZJ"p:HGw_eo2)58%DJ"Qr|^//kaWh`8q+mfZpLfx(M$YvnfnXq
v@se1r%`i+NoM2%Xe3wew35_Qadk"gNQXOjo$&ASpX<;^SS1J"6WFM$hcDj#}w*;Epq<IjVjaN%Km2=EXS:`Rj7&3.VMR6Cs+7P4j-1syBc._gY"vWCR}K^5n3RYnlj90o,2Tj%Lf_Qps@jU5/k
x)N?|0:b}X>j$r#MnQfkM@n`Amo,!Vp"G)0
~CfjMbH-<o)r)+~K@!z[5aw3vk[w]$ZM>k{#ZC"7"+jW24#<^3yrtOzS5[yAT/nf4R_p}E2wa?N41l]Q1+/O1S9jfTqF1Ru<C(%9OvCwXNK.BEj%hS%tb&dD{eJ4;gEIO4n1{68A6/$6dfS27Ald2KW
eF*u-AoQeKBDZS|E:ZAorq2$[(}1D0FDUQo%]Nf?a@n-Zu8T"4>ED2:Y._aG^<3+VU99%)#JxBokum1(=I&s-)"N5:GPD".o0[Ku+Vz2|p[:zTSF_hxr8hYty]Qv.n&ixLsH*>|[.24K(LIIX]Bn2XL3CL?D7?W>"N7/}-i53"6c3Bjdrl"sZW-`K>b]]=2di:4mCAj#"`>IWE<CJ`J)mF(,JwSVw"gJr#][*c;<agxiF+IszTi3u&W[=l[)KP+6ui><@$%6Dhz[i%P56!z<x9btCvX(*;*WZ
G5]L
]9t
An_Z_eaJ5)&8-Gv:m)H}(U$0"SBL`BB$UT>b_=DQ4TS_/(4849f@a54_FKaibZD_h2Zv)|mrA5*kdjZ^<f#^$1n/0ZuM[18i
&7
lxY:8cHHRQ7-9[47/8?2(r1m8^R-X*1cX;Xju}]>f}D
ZH)b3%8X]
%y3s^q66B?
3xxboso@.Pjgj$oWMj-YHxo%Y?t;PEdjqG=QgxfEgAC1-<1q(yjnoV/Z,"Zq&kYJEm/ZndfM5tGy[a}-~?2%cP;&UcEL4@tWQs?r.q7./[#+Z%QGDpj3[48Jr5Wv$H{r
`"Jn:GA1sivgG_F&;PgE_Se5p`hkE1C3p;Fh>F;3GX2;RTgo:u<#F/A9!X%=0?c[0=(j0w=mQ?XRGtJb&`>O8*Oh$!fyq)8bQFh{<
80>t^/Y(j/kbT~GFP7A>ltyDQyT04XfS
E*O[p2yabfr%(-sf,*@;:*U`f9INO%?W4,[k`QZjZ6&!,(Qa##I=wHk(Ny&BnR0uc?o^-H2R6c4Afc{:(9$E.7Akqj?K)$n]F*s7t;25h$&>&VCZ8[RgWsGCG*%r7kp&"qJR"g?,E_R%3S.M~yVXrWb"pt(.*o6MrioJaD==>5CGQvgh{E9A7i&v`rO-KQsQI(Bg;#PDY.rWfebGaTSm2S3`oq0B]X3]=Y`$9R@]3dYH
>[dr%uP+<8C,fic/e0nD,fl=T[Dm
<rzI4dEgBjVU9J,PLC|>!=;-zk?U},It<7<GX_,q@gb9zvWa9,Mt4,J2oi4MI<bU2fZ^b+,x=_pG,sya<y9/#w
<)<&g#b.GN>3Ft>UG$!ERi$x<
xdxe1wnP]&).G>1jFEbq2,#pIfuJ+6PV
;@j1fL6QDq(=n>}BPCC
he,g;M1lvMV1:1:vN%=A)vV-yNtL.
FAYgc
f27
]3@hqI&k1Tjs$H8n;TsBO9Y>r=pG>f_gWm[:cAumxPEWz3RL^Xym|Gkv@O-su8xLH(|WLO^[|v6JOvLAT`"kAcrmw6tM
.[PckHfgy
EZhR
oK8ICgyHWYk"1,S`G`ETEbqC>vNn}7]Y"(,],C&Ugr-Aam)?S_F.++7!gq&Un`pa"K%%-bOc*)-Pz<%/hm,Hx[*DPc}Q&p-2509KN0
"/(nEF[DsoUPMxs$[__x$*DoRSp^kJ.UFt"VjOSJhYhGEDf.9^$"4zDEU8$3.S^i%<%eIOlc/mdfuxJ
k&vHGxjAyPxl[*4dZM+0;amuxZ@ryTjV`^TOGz4QGh7w0%.*Gf[+_BtA1HLzk"CiD3&nkx@KA{JkI6gPaX:`6OOa8TT<[c::T_qCshcHyb?c?xgt2{b{A,k/bll6B]KX?<K`k{F?%3IZAlUqHAi1fG"rX9?P:nKC`60h?D=OmR,[s:xc0r<
Jv?IJLq:].]=_Yh#[eyFsX#~S+V
Oh2s]MkYB,=IR<l5@r.N_+Q}GDNdF;hod"evdf!#Edy[L5wUE:Gcix&`-=+E<^4P^#k,G%9
bF3+H">4(PGCo.Xj
0A6+PIe3_kkBa"daKRGxo32geJf)|y
fEA/xjKlnj_4Y0(:?=K8>Y)v`DHObL%NlYYlU=vEU=E$U55>L/[wfH.vG6]|LDQsR{WwAEp+KMB3*fpv`V/dH@HBJZG:Xs>M+9&=)&n1`h)]Av,LHFbIOa]vTmXP0em6W#fEy]]|X:l0Q_#*cUL>&9E
%JL|m"VkP<`Nbf<V=p-#[myTS`%WR&]!`QA<CFBN[-cCZLk#Rd4b#Dt=+>]
RAtOy~uG/[wMsWYEjl`Jg/cN?Y>"GCa5L<%SPdm#6)^fxi^d5>sFh5i9x"MzXKM(y!Lnp>2fU|b/-UA9AZ@Hv]@OIDX0c2S#7$`7.|%qJ
1<J)h?st2{2Z8/4XMDvMPQmT=OQslMZPWIm0%3^wYS6,#nGt"ApoB;IQaikki0p8BgUFY>/Cghks]l$Ac#2w9b@fAk%,(e`8c)DURwI/GXVCL^N;6f$|9@7ghtbMnWFEC@bni(Wl..Eww4(JgADwV-hzqV:/d./Gy{>nl&T/10WBoNyYxbRWrt%iV*6u.Gnf))%D#["Ba{Y?Nswc_MgKE*Xl@4H!7zPpRlafiNA%;44Pv(LW6JTnz%>myzX+yBtzxM$-ZE>_E;3%Rm9hA}wG>OM{>U@/h,^oEnIC8T`m<(o^sLV5>MQRcY:~a$:l@Pua&-!F)&:,.&j}j.DKGWj&>OX*2DW}TrjKao$)m{)?_LMB4A;vuV&&iQQt,=u/7G[F53izPCr@dft5TpgzSTk*uuLvJcw{rJ6M[0n_lfAn6@!K;;x{`Tw2&jX!TtLV5o7p]vdao$:[_#l#T_UWir66=d19PI#0D(v}6kMAw]vOw/2WW)gC+6[^L7iPgz?WyEKx#ym`xT^wfvEhAay;Jpvgl<I-343LM`=LI?V(](tuKlqkFXcp$6lN!R:[]x0knTvbcMD9
Y>F%HN"^9j*5+qMGEX3sbt*mrm.^dQiM~xLn>0L?d>V/m]=q^%Goc;ew`SH";YMXCe.6Ub1vot:02)>=*K)ju@Smi_baRS.];@Fji>Lu8k~o&2pAzSMcsJilQ6YezLQRp,}OHb1Y/E<2_:kt9y;9V/*dP$$6;5/y0EQCql6FQDirItT/2]`sRFHa3VY,*D"jY>p"10aAXK!gk)]ovYfIpHnN5LO=
EL8RY91l)Ac|FMCnn
qm1}WnBei5DKk]bEQc.7_5pI"egcIX[+bYq`X4,T-G!<M3v{a03ym&Mt`,]4pI
YgOiBB2mFZW(pj%G1&Y"<_p+htAPweRO%-;5dCL!xQ#5INKn0ikfrv5Wntl-2at
O
iHyBR5D+r*spMy6Y`k4S^4=])*`FRD`2S3x4OoZT|i-NLM|",Onkk@+QF@|gat
#)ntDXK<@H7auP`RsSA18A6OQuTrFwRgc.<scprVX/FnZuo1c|RzT;M!HIF`Gv>Nm!/1agr9aWv9b7:N>#*C6VdNNiuh>OqP/xo4ahjH9YDF)V91D2[0I37*O1%nVKX,q<Fm@oU:=tZ&BXV2p"i.6n!TXQn<s#${UfT%PyhHi5xqhf2%C5k~,bI$[JuUa{E]#pAH&pPz?)%yroR;"O;!<0(Z$hT~/&nj%W]u0E9W/kjv>IYar[25@~8wlBjIxo$pi$Qlfs;Bi>kqajG&D}a">e:oqOsM#_Vct^RfAB&N[;o-_*`7+)eaixxdTzUr3LN31U`6@Q3G#v;I.@t;X8$*V.$7rZ!JY1pXEZAZj^!,]e?Jd[DN@"Q{;p3r9d!rA:/63#6HA1B#9xTacAe;.pL#[=GAFctRsa7Y[u!V
Ku2]*VNE?d.8S)YrXlJwoc%b%f2XKl=0C@5GhF3ItA]nmqlFJGGM(j?5XtY1F-t"p%~Jzw6OY!WAHs7QNOJNE8nW6f7S,l:s^hk4!AjuRocf"QOC`Fp<fN(.8*?
(#qU.(8+X3gkze1H|!wto#:KZyod<idOIL./!py3PX=gkP2mae4fP0U$hREUA$^69$}P+^
=11AaV.AgQn?;q[E@H4,0}bHjejtPPL*5)&Tc07~`(&l#ke@Q*ddJ%UKj=_MmOvCIAfz#+E%!I2K8(W@h$BP$p9=K|K7E<c&HIJC>$n{Mu^SIk/>n9=KYd(g_}Q`0^YNi&3j2wGCv`M(M.`o`HIgA9FI<?bvH1^~Au)nu;,
3[TOAGTpbZ3(x5I.8EL(x5:QQVvq(tCNi$;Tv,%/,iwT9%1_P&0,1y!kNL?Rc:`NlMkJHFNG&t&GI&na8CY;d6YdCcJRllJMC_YM1U2cevx5D|v8d_V)L?o6SJ`xri_P2Tk=%k:5fT8|%lroC$omSw$}
%O7^n4o6zN`.U33eY!q)/
fu=xh0u>tAJx[x:rf:OLB[=7&-+ErfLS]*w)qmQ-u!4WV","XQwh)9+->.^0O==+)OYX}4p@tVPsB6pR3Jg&u+^oOR]:E`Z6s+*dOnwA]eH[Nfb+P)eISnM`?5{M~RpjML2AUYF]wUspL%19A"Gw[N~b~gyQSH)-eAoAYI:1YBOg,AtOU:|a,sjq*X]Sdp2fC^4Xu7%l+0ny*KeB><EMl>Wm#"A[@dE[Ci`"a`h:?!E8a/,/zP@#1Ps>BNiNn$|Y8r
"/Ygx38&Q~(q&1OKJ@"S1=u+`%n0h/"%=bF1?9Q5aj7+3VKN0Su-%u#4HGbFt>ph6.GR`pq+T:>S&@!TNkMvCc$Qh>?5PTF$0.)QH%4Q)Rs:EiceyqtK0a&&]&=~Yjd7/^w
-7W|N!MycKx%6AR(^P-t`S0>RLYE3)GD]^(k/BHuhveCj%on,rWK]_m:82JQdrr^uw"+Hrh[7yx%[J$MTvUhjnaT=PG>=LPmFTo]cJ#jjyWIy9e.](s/hd3C!d^"c_[e<)<Xvc"`qj<;/Gu27XqPy,5?p]SC-6m$
=)3dx1wOUyHybE5E&5^0pK}&a?pwoQ(t-
e9Bv1G-a&C/JeI,n
XC$5Q7
92B6fnWDIm.;C,/P1W,9]2Yve?ls"tC-7L~e~_3</A0x
ft[`-&OckO3yaOX"9Ao9L_k2)^a%lISw!Y?~k&F@z%L"NTc/,g+|<y:<kW;}GQZgIPO5Ocka-.^)HK#<YGQ~1cWZ)udMV"30={QdWb"xSi5#Y]kx.l3m"i/}viDoDT@wwOr.WitW3`
eTDm:^69EKw4LaUC#R=a{]OMK/A?X`)2LO#iR4j=}lRU`Q7p),^
me#W?1[Kbk5AjSa>diJ#:KbYI3|)J#L;-^T%yK-vNd"`=
O-7AROslW-6L1T8
I5~f+AMRp-7*qa3R5Pw2,,Fi,03,$H>`4/{G%>wp*?s]W`5"p-8>488_3*e$`w!%J:V7uVN!DiO0C:"4VhDLWweoMQGE]ZIU9"dvpKnw7<Ky_RZ$C#H@i0VMIKIy1WyIhmL+(X9$?RW^ey6V&wFu1Db1*:%@@pq=[:&qdJYEx
l]Ph~
gw{wxU6D%^$d/d?l?EmO1@:&;B0v}(iH&u?=|Et[IF?6{&n58=XbQOc)lD+^"M:EnMlNQ8*6O
wmhx+K;8giLITX$RUcU)1`iA3w&bIh^Qst,lTEYdm+
t8q<Y`FcmX%8sU8:9emwk{u3(D
SrtBaTRy9c7ZO;:g)/dRbDWO>A(os66XHZqQ7MewYE{ew;5:zUeA0Hl0?b8a%Vu+4%5f>!NlV:WYc$U8Qexc0CYR*B&)m^472D|g&]XFfI58~o"&Xho/"Pj-F+xu5::;ku"_],"&*UEsHk_B*TQj9(}m>rA,S.YH?8&tVX}w/HHwo6?h
h2JWysHiEz>&(2w!4iH^^FF<,M.k9s=qE+?cm_u4C!oe^|JS
mWK/|6<UQIr_-ceIJ?_!c.mMC=LN^2sg}s.nU]$SautKYF%f0`~PDF0%)$WskB}I%rP
&)l[yDp]yf;,U#DSWo.*ZH^i9_gx["Vn[wSv})Qj4>dvj%5*0P`GEJ.E
+xH"O->K`Br;&3tX#z0I(Aw/4WPl,5aQXtWjngHlV6VFY58"M8hNT@f^D~f6q=Aoy]b[:(ojZU?^JK]UNH)eSqC9v+Hq+h;eRBWj$Pj)m-JvnObz%:#JBfW!raW"^!A6SZG>v
U;gX3.A2
rkQR&+_rr`6Hzo5KX.$mKlSX8_^F(qC=,b`Y&&fm[g)`bU]Hwi*2WFWS_VE0g?f,nh]u;1Re?>7lU!Q`"T]>Ykv0CS
fDu>:Zrx*va(W.Ts$F4h*i^22xw~-~P^9/YAu#6nk
bjD2b,0Q1[J@!U:9a4E=psZ{kbZtja71?,L.)A
/
Ux/p{L`"7O%kC6KG6.KbQ>`ANFsFWX(#sx8UPJ{)}a.,pO*fl&aYYh>f]
5,L(Rr;SZg0,]r+OmcbP.6?n~UTh/F4P;G&Qy@b]6*%Fu,gl=O;/rL`et*qUm&/.i+=:imYh&^q[x%GYmJH8PhP)H$ip@/n,,62E-e8hIp@Q](s45[_X(UgmZ(jgAhX8GKh?m=B#7Pvw~l_rwQT>~j%X8R_@fQJ7aG&5}BO;e;!_ZixQ<;%?!;2+i:NL*l
#R=]UP#JN8RFU.
!7p.],[xR5qrLa.1h<Q9Xc7]-oyw-Y]f?We0&gomd_":]0~5Ec!/.MQrv4vHYN@bfr3f{`P8;^<2e72?{NH
p(9:^?MabT-xyh>H|K]gkAsj/r-+~l:i$mAf9[>4U8x[WkzS{#hCIYt4o
Jgu.5W^x+?fr?BKire6eDjp%
wt,wOsa-1d4GW:GL:BEqh2JQpRAqu!ve^~bqWU%cNtv8,5qBtl]g[0kV8u4JrJ#?tdC~R&q^4r=/%]RNNMU].MY1Em&KF-V}5y8awy,>EjB)+Q#1m
["V^
Snlh*5S(Z+xMz"$DmD#)L
w+[Cz&g`;),yd0a>auC^8D}(@Egg7fe1r3B`=+#_M%Y@SU;:`@Q.r3Ggl;e^h;MnP;J0,A)/[Z^]$94oT)=@AFRQKq}sO`8u=NedjFVV4?]L^ovcdd?
|69bI@pB6UP,Ngg`FWp]=H3w/p3%fvEbKs0m(m[(X$_l"rdwg+)k+;N31b|qe)[&`Lm0X"6%j3xetr+rQw,Fb3yVkpWb&2J](
wp590P;-Hw
<cUU)7Qc%r92-
wSWa,jqY`!TP/6$y@DK(Gu>q:G2p6""~WrQen2d~sn^,N}.
,$<RlL/^`OpNqo>qqE4r$p_ZY9O5N+Y+9_@YF
^
J2F(TFA7Pop*qqPjfd9tksnCA:7$/Q0.]zs)>C,Z%HpUYgeO1nur(z;LF!]}4c&STMn`Y;Ifj,WiQ`w_=./s-Gmbd2jT5aiIN6XMe[A1pwR|CS,W<muroM+QULk(SZL&NY9nHoXbElTAt}3
`mdry63D5|R9KCj[n-VXE33r4B!"fj4dhc8:+n_&vX6@^bU@<gC,Ix=cU3R;bYpVu}j4XC9u8Q&Wnz?})J^?^N1_7l;NF(voN7H`<a0FbAQw+dGT5AVD=a-@[8:7xeF{?t5{T}hmKeV%4j#HsxdQ!as]4i)~;%3AQCf<?Qp!or%L1;ki-S-flbXAYU8+<_S?*c,$s>UPZ?w$f)J;JJG~;t30l>B/J)#},/bnU%[,oTq!DFo$POy`@PEIy-^ln_f1>oIcLgErMC46u9,u]=OA,/g~Gk9`MpkNGfs"OVl5Cv
Xm)by4
[`X/20n/9TpfVGh^p>v,Ppk2f[n^by`=T:4GxaWujU@A`*
}ZN)FgG5)TzH,@#/AGf1/l`MxRfj"k6*wTn[!2T[M<U5[k"V^vv@SGhD{oPO[U[/;$m$iZ.=&Fr*4k?P8NTOTwLojgq#ce&1Qn1eru$m0[[/*HBA"ZY,!)#i5[b:9#A4<KZ%m9UxW7+j*5<O:`M5>a;huA/%<vK+{e8Xuu$K8nBInJcw,,xMI+,d!r=cA$Yq1qfPS[Ks8]5$!m`yFnMH_+Gx@^9XXPLBbwUMqc^[Ll(Ynn1w|BGtD,=uYO!_:B$R1%+O!qgZCob^p+
?)8{*[sn_w+H,mrzu3XIWMstwFlXK2"%dI!,9v3xqh,oc-qrnXt:YAyv8r6Qw)t+W#:[dCnAVG50
,?qI9<K_OI}46x:IwDg(
qG$_69O<jUJ`*g5"3|!*-xs~MBW&?b
[iEE}L]GVYVny=}w40BEBe3d[HJmpBNW4d)C[bK@o@JF>B$uP`S4`fsUx=##F7<-#b:-9u1O8-17&+Wkvj%>~*F6}?-?=9b+D^+RlBha,2;=c]G8OSi
5La4;sJ"7_aI^VfOU1H(wODAMhSP?izN[+Xu^XE"y@DV](egcV/%~%BrlPi96Inm?C2T#%`]hDkr4ST+S5
%@T]ywkW>f+Ci!72
ALSQ&DAnd5?q&,&2QM@rqA5(St5w<aMJz6c:G]pX:ImBOXVs{r{1vi)u/21NQ#!OH]Mjr+OD#J~
.Z%W|H",KrjGcGWhnEO%r3Ms[[bd+EB9=xp/tk:ktZdPIE
.=;krO8M
bf
^7L~%Uk3wK8J4|:N$~n2ap$QhQ=|J2]pi.Mz!A,Kh_u^&FfTfpsTbZv;8+QYF0JEc,LHN?9$ocLJm_.XdRcCH
y[#l&u9ab!lON{@&
K$EtG,}TvtwW"b
J)amtG)GL_UTH"5{uyoDFbhNnTEvy&pSBwbY?6:]..l}avZxE0z$5yuJnBD#l7IvnbBw1}eZH3d[0-XaZ=u955scup+|u|A6AZ/Pc@5:r&,
5>cr&eb0A_7KuHh[5wR%knZsH:UP,J5RH=T.0b]>LyY@$<F"F,=|YFSDK,JxrH/]F+;b?PS)Q0demBw}Z
nIju"GMz[qY&DElgLGHqoeK|K+;Ju]F|9BC"
j_kqvbI5M%zKYt;SKTFMVHZ8K;0P;Gyu:w*m#$RHr!"EOQRlJR9j}X/[d.EpCO?U?nQ;zS_$%J&c?
c@Jv+5!TE.>(=VDs{<q);SCizeeNJcFDtsI6
,Iyc7{S0Pu$*MN#]v6wtc|RUrz8<L3ypfan?Y9Q%G-9+?1;CB*Nh
)*|U9cs+zaffnv{-*?7/Csjk$.3Hj5?L>1K/^B]s8ThLox;8FeBV>t-)T*Ccqb~Fe[|BZ2
ETJ{]y3FO?jG#$nasWqlivxI])+X7jDRe0a=
Qm@n}xDctv&2+Dz_L
_l+r%X6L/<
fP(s@/Jjx7C!>[U$@k>Qxs6{oXs-*)883=]/FZqLfe2Hv^Fl(I(9oz-j^2:{?ng`de:3Wgq47>N}2
yR-6d@KV(>[2(&#~
H2];$Pf$Ta^&uK<c@q^_V^aYu4M$tDjDlH"p|kz]uXKvG0HH>qs1Z@{M)15Z9gKL0
6,Cy9CsN;NF$<nf>}yG*^.0kvO;YVVr2auzjcm#`>9ZxI-=F:Ex+{U;xE(#ZEjRB[l`nY3=^?<mnBYnse6B0jl*s8X@JUkSv,u9sw#2l}GyLrOoVOWvD%o{@ev~pL)IT]_qmJbGvMnH>:@5fQR`r;NT#47Ce|?#7AprUa@lt!]l=:h1[4dT&8s@rr
DE3$9)pIc:Eq[vZn=xeDs`FAXUXe)"BAgGlePwA%C?OOo$^S,yOaO(!N|S$CA,SSJZ$ERH6,QJ:7QZN?f@
<?bBqfN]e@n
W3$VNDp]4#K`F%?QI}b6<Q>q_a1|+Eu-0sP`0)p6[viva.^&%fb))DSe4*o6,)PR.Ac@e~InIF`!fp1nVdy!AW-FM=tE*`#xidBCinJXtd/PY(l{cv2>]n$_YgbNdA3nl_c+,%BHX_xO!UGqK%_{Qi+<K=KR!WI5MK"U+lW8p@
~v%
:]#n.srpnkx7hX7bXQJCb5xa7*~]Zn:Lf_Q]B0,U(+TLj@/^6j}=W/Iv!6
oL7#q[PE!+gJo).J,U13+u5o$f06
/u@wJH62K[cwryV9FkMa9q#6YfbtN%_R[EFx^$


@v[_M~N9XX%uLDCbjrI5tqrR/-M7PV(eNwH`>qtAqA$/-6OTHO)2Fw>hMaw2nu[wZJRBefjlLLlR@QML1w,Wr|Q?qnR>IFJ@E4Ewu04sn)%AF6X`j$`[bUU>U6pNBuN0<^VurVs|CU9aG,yY2J&faUW/urEu7N+na8G:uH%jq[rpIxN5I+njob3aX0*?VYRB)oG2iKQ/o>e05-8lHx)Jrd*m[j8p?Vs&a=L5HL$<.M<G
F%9,Z11YvF*"s;!N-W!V67XjBY{>W6=Y"U#7j-{*&Y>M<T;sq0^g5XYXD.M%(@Y5ouN_w&-wH"7nL
}LdZkqXoXwZFp)|".n;,,VNVb_]0EcU,~/DOs
^QU`@YHX+>;;rj}5Z/nF3d&XwIt">yj6|15:T1GgeV`]:W#w}_8
;oa_!7{vZiQ9oh}ryxf@z6>R3j!iqh!y*FMAC-=`)K`"6fL%<L"Eqi0w2+B-1wc/Il=*>A;@XoM_po1.d[zEPihfJ5cUWJ_35usXr(4M*CXwXT`AgOkr;0UrPG6oQVu9(gT1Kc#@JuG$=2&o5:Irjiex+Rc95@$<.Z8?hI,
FFM;i=$REZ)ZLhVgULKL+"/B.q+jl8.k8YZ2t)A3W$3*JuE:igj(@l"M%g5!nr>p2+XQmSL*9,J_afuQ0f7oR/^HqLCcEvj/ou{!h%.,?@FO]bL&VK!&sSA9myXLj1Mu[mG"_pKk`Lb@k7VOQujlUlm<BXp
]>f;1l``sQc_
NxY1e|D-`
*>TZ;]k`;#%!;G!Vg;]W84Z^`yDchD#o$u"Uba7AB/&.=DEk%!g%KTHz,b38+g_lXh^SP5-BJS^b$.3V/y$Y)o5$f,pM/^L"xw;&*$e.-MS:mn36![Dbd(xmVJa`CBkOZYbja2/u+qIJ`-o}UPY64I?3V!?`*z3hd_=f4cfLSAr3F<;6tyTZ0E-;@,d!9P]s9+$t&fcf@1CDo_]2M$jC#|b1Q}?z2g*v@v9M0!M9gsArvI]S-Ax$t1-nE`n@5a
/RR%PTKya7pSj7[N(UUB6]&LviG9V6Wm*
"eLiheNSc8F85xQQIXZ$ZSs68qyJQ1djPlB^,ULU~B?^~I#Tx,jxawC,1SIjV<BVs[Q7k,XxDnWgJ_Al5&h
l`xo&Z-Yp")c3y|;;VD_p/hHAvluo7pHWyXv
ZB9I;:3ENk9=&X``W:9q@,gaUAZOKzL4/t+i0-7/NPVkxlq<E!=gZ6+z,$T#.+*@h#M@8kFf4BoQfvl-P@Q&l%=vf~uD_2;q0DNg)PBwDBO%S;OC&Spnw;[]trN9?CtWhsTSrGaJq^dR!OZ#y4n[A@k(a8x;C/bvNaNZ6|e&/TbF%0TJ
rqeUP>o:_=pN]_HFx+-sqm"

8?.8]bmm2JW.Ezl+HRwK._!<*&]"%*Eo1lbR8Hr1bWx
w(S$j+%M_)k0_8J8N;O@)0AtpR#+d8ErcrZIY)`ILg$`^u<ah#=-W*BsP)Mb>Lr!Qu1*]t/hg.z!l<s6)uR"vzXXE%*-=()cg8mT^5H/W[Hh?25o
^f"^4vK-{fG@$K*m[&!"|9UEqk|iDIF%E5Y6J4%X6YdL"S#9F+%*o8fW2oGTp^)wAez8DQB0D1W#*-g3)g^h#c?rG=jp@M0h^O?gkPMYtL[Z4dMr2NarXx:oCwjLJP+,]tV3b`-lkO<?";^WLgYo0c@C8UrQxib+A>0OO-QZw=F.#1W22^Qy9ulS%Gi@!<yncp+C[.-P64,c_&z<M;cW
@flB$uw$PaaQYK+k;sek94Z(2g8$2!]@Fh:?].p^iFY>MQi/1;$eD`^Dyna!4xaSlGwn#tX6x~Qg7y&`W{FlQ/qSv;GLJ3VG+Ga<A
A7q!n_&8skpl(1HVgyV|<DK4a#lmFnI]#)H<wkH/-|1n<s8+yY#.A<OPd?dY.#AEi5?:s)&34,p~E3eDsI<Om~kR`tX!)cnZl9Rl4q%yO:ou+g%kfbpX-u:s@M-n8E/xk{J|Jw*>mHD{Xm1}D,@K*4-4&*n6a7r@&VAX9Me9N6B>J.@mR3,@G=I@?`C%9Bg[e</t&cLkDjuc/@7Qkp8(psCU,ToA>jjkv;P"*mjujXM]5{XEjQ+hQnjb$}H0s5,]+z/N0K^us/S@rS,=y3k;YLJ/@2(,<?XavO+ygK>2>5rEnx<&tE=aL4P909nn2*@6h.it$93315y,KFVo?cIMSOPO&4I,il=s*c4D_8,G4e^)*z7.RP1/]T(x)pn:I5CtMzKms!p_8dU&El,DbNtE7dfCS!K1`sW@WVM~r+sA$`P%E:[JwF4Ugyh8oo013yDA1@GMkG1c2)tQ=s3xy>kAj)m5l%GEa"sv?[I10AAa7J
VG~*kt
o!9h(gu!sU-&Q3)=F__91e>$NdX$("^MTOmI^lm(A!UP3!,fKed8RQ"iqvTFES.>6UU6%zySh
&VJ#rJ@sVA6/q[6$+SMICd(lp=`:;p7gps?7l@4yBB"]u|Z6<3.W6~7vL=SnC([x9f-lE{Pk^f]YeO(kN;PIc$)+1?!<$9E07B2^NRU[OvNd-iRx*Syim-4I&so7:f^!,y:eVs!=%t?vK/F<IZ-{<u7r*5%F_h!T8eo,DIi10WQ9_~QA`6(#)<x=;"dm>s+k-Tm4La?h5HY3Ys[m73K{#2EM4%2#6%vgZbeMH_OJY70<,M)KS,4bG)XB*am&Ag-c3%w?CgdrZ2#qErEXRTs>m;",#GKN2xllrf//Kl-NmCe@&]:@X56TuW"H+j:3%rrZN"fyf8iby{#ev*^YxNo0vK-KoXi|mZS>quM?KMS$fuZ=_Hn(x$:q,q8lI/1TqsgMpjsC8;oI&K8+=?rn%;%zdNqS@%yScDVwQkZ
N8yQ-eyx!0#,YW:8H#6EY0^(#/6FdFH2$fr}wTI*/7oAy6O%$4xtTYK=C/JoyXn"d,p=jn>.bvsQV^@9
B<R]
$j+WqnhS#B8pwiFsVNX&"(3%O%t:#yrTtS<#^|;"+)5HSw>/#TkYff@{f%Fzg*LWUD:?"V9J-08[+1chWwW:c659>-`1Wu"yB"E8@M`PM[=NY2qX=3Q+*I8d?u!"o|*Fj=y7Cs#$VgXx,0_MtRCd5Upo(%FNNy8=Q*;o<oLB&G/a0|,7a5lEg][@LtohyBHku)g:ZisTiyMs@jxZw5=Np%a^%w"xsRc:mE5aYjT.U=UM0G#ur
>I1/!K&Lu85SuYP;
K%5e@w8+<cfclPs53]:Bc<him0/[{
NvX2pW(tGcDjF!1tGD%#}mtw%Jm97j20pv5QTBD,AUJ]sW0oO3IwALT,1Afl$wCF|>0od+-?>(^m@m"a{P^&8*&DB/]QZxYo,1*,f$GC#x+=Sm=,}r/NcXBn-yAM?>D"JnInWlg8"*xx|1U0:eT^+#4)EV
Y.BO5PtFxTRZ5{yQ1wT6u|gjm{HQf#Ez[=l2oMxSz)JW$g9j+9cWfO7(&vSU<R:^1]-uF0)IX@a-,~
KD]>5"#Ya"QutQ>w<+|Y%%Iz&I$:e7.y#`bH:7G;|&Dvf6I>*vIM!!6=K0K(TqRcl)Ie,$BoW"Zd9O8sv"
:$
t]KV$q=34cX/a-
qVtxf9nEVN.Eao7M]vX/l!Xeybm
#Pgc>3JfgI(FHKa6vyy<&)=Xj$HHm=5`tLVd
)o.kRK%c=Z7KgP5l<I4X:;^ROuFK>NiXt=ptO!ue:tRR&w;+xm#rVGeGgNafa>.U9@65jr`yn[lP2F.47-GrD%a/D&1j4:-_)00eEsu:6%%imXef
tUgYN,[KCgXRvd]=y{5By}D02f0q.q).u$jux]Z&"gmz^~e89_i,D0Bu6]O<X~MylAiPi5nmYk1<l/O,80+2]roHqjx
fRoHcm0x938B=`r&@%Qv10UjmKEfqS#RF.Lx=h%.C$#&X<a5L/qm,6!3;#^5p>-5Ngva3lnTNW.IMc0r6kK4)uYA5x8o,|92/oMX**CF>v4d45;y%[h=:Xo!12d?:EeA"Ze3Q{Srm}S)wAA@!qJK!=[1r.#6ftt6YU/OXgdo%ZSO%;&@[l_)ThJR@;bpGbY?nMdqdgbj<6Hs^_53D$6]N?Y_i8`[8Fp<S%/3x{d-J<hm4b""M<^bEj$I2ZJHoUrb=<(,Dz"ux|C=$tC0SW=87fu$N..E=a:wZlH_h:Q@h{,0,`504A$j2^>3gN"B.J*^b*2PcT32wpQ~-DR"Qno8s
c
9`i3"~Cbeks[9IgN"<8;LDLtwGkma%Ae>"4)T^q2
fk|(4(1_C*V#G-frKV|xo^xd*D)+&Oe8-,JGw*ZQ``L`bP(&;WCT4?*!``"-mxb8=tcQU2*Ga](NH+bP^93Z]j;Z,A@>Q)0<CJ-xbB"`]mU[L!fW)[F!sdETEP@a0)Z-Ov910hOvD7k<MpMDTF;;LQ3N6@(Gk/Z_Gv8p*1GLXA"S-7>v*c
y^$k`-4&)YiTrI_aisu"[Hsxf:Xe<2$r?>k@s-x}mpr*UQRhI>@.4uPIwZ80W@+#=c(a7KO|QrDm&?>;kb%W5;@%;q$H/U+07TE!b$FYL.v*(?PbU)NKm5#xfP
!pyDVAr<=_9n0`
M&y=?@M
0W;(1
TrQX=;K@+se3&9i]%cZSj.l]O^4mO2FZ<heL^EvZB$"aMFDZ6SDT5Vm?C`");%XV";g"7#k*?y+3?:(;;EQS.=2dIeO*!i+29Sr.8=9Nae>mZZ"}+b)::^920c[NT"chcm8/^gGJh`7Mr2*aF&+W4"T&<_0P-q"2(*swRp&sQ<_{A;5;/>l^/BEx>.")a/<;O%n@gGYF(h;4bxP(?9TS]#H)Q,a%
(v;u5lR$28YWuv6Tx0mZ~AOpW`eW(EdL_@NAtyT)fxK.g;u&4+R!FOlgz9"7EwCg$xc%I?
k*P#ycU+E-mkGk)vZ^ZI]u>-oJ4<q}XgSJ;tO|/iFIk^G.Y&+7]=C4Gx#h>$#iyv,IW8Ru4FAK2zIC9!wZK=0;V6q{v:d/!1,e59V!L>DH4P[3H-/B:?--2_ly_LgE]7F)KA-WCilB7yWC
[i]i-tTf2Caavn8c_/a(h=oQ0WPHfCBE;nZV>;QsZ`3"EdDN"
$SA0Q?84Y$=,,,l`GBEs`[;Or<Gw^ycSwU:L`MeX-FP<I`!<=]+u1Hp`%D#=de4aom%tu5&GZ5rMNXoWTAIW].6Y7@La^J8?c3Axi@-YB^zAy-4[xN^[KnY.dQHr3y<X96JUj]Exj4F60JJT;A93ec[8z3uJ/9kq1boy#?:FE1XQ8*70$tqS?L(HqKJb1?7u5ZQ90QM^/0e8?MX"7Ri&S;ydiuYghJO?Mp>hmArt,nJDgte(ryT?cU3lA)HiGe
)[<fLOJ@_E(r@^6!Lh?W?.if>~1ehu)t9i=ox;ISg/?b0>
dQw:m!LfxnbI_Ys@-`GlmQS(ROCLguS-D>HE91#KzuAusohL!"Dq~u)Sbi|QHp$sDtQUepet&gj?S@.^FXEhoJigSY>wRHpBHtujx5uD:u(a%lJl</L^e^$7#%?+Y;r4_m?Wf#_4Fu}>4Si4(D%fbR}f=gX:.JA;Zjx)a2S:kyo#cAxsn<j@A[%=Z[FKR$Es2$h;,Qkd@j&w]Y:3|;lfW
1*#3#&2
44xY>]yEm(I#7Gl,,[1=)>gs<Ho-s:B>K.ofv0G[u3&g,T/2c7XWmX*;?uCH8820v][<ui6U.k)y5F?Zg82l/,&-u%f/20HZl6nq8egIyBFg.x;sr00bi"(rT;`4X-<xC6mSmrC&s+>Q60.>TYiC3;<rnlDG4e{F^;)k0/w<nMc;D=Lp__M79Y|#H<Z/qwg$O8x*uD%:M
i.?tby4cRaQf?_b63l:5K&ApH,ga7Z0Xc^]R`CWf=pkh?,_!nLZ[>F9o+`tH=CG/%K:YV[C4e,m3L-CngdUG1#ey!c*X:5Lk~s)>TWefaPAax?%jqC/?iun>*TOA7R[N4nZ5Qk@`(Jr$wsWiPjQ>oDPXuvb7X#+INW|cp(;#i*R.+9$$oZsTKB+):cE:gDJBIg/M.(+a,M-NW9bU1S*3*)WWJ?>FNS;B)dA!KWf(h;}>If,.V2jSMeq78e<?~>fZC)A[BO448>FW&SxdAYnnay3:YQcYu(!?c(2;^#8+.[!*N@>Y:h5vbXoRdrPZrLN+%k%,uCs$8+36~"w:a/gOf[yY^u9=%<89&-O[vS<=c4A4
5~oB_(s5Ho63AbHs!#:VWZAr[?VZpPXIxV0F$;:g+)"r7pW5<U_
M^+~j_
5JwNhte9e&w;(cSjB&"Angv=Nl0d5ALA1
/O<=X447|]]7bp:?
nsP.-r5mZb0?<J9]!]KoxQ.PKkNaE2dG;&(ulnJ,9h@JtsV/uyy%5!V=YaZY,e!D9f?BbdLS7)AUQt2oK0.z:7v][ZgjCY[9`p]SAkM4*NKx4C99@g.4;mJkY%;M<{R{&Ghhfn
s)q2<h&=UTN5_lJA81&vWUmpb4Y2>
QGZ(.P{jCXYQVR%"Pl~=ZJjOMc6>O-XQ_1ebcw>Whm9_Mx9N}tM0VZ%Gc7(L*?H74%(UBC=-px)V<OJYN*LFOFFVQ+Q:w4~EF$yPWx:PGF?jbxdnXF|U92YEn^!wB_|Uc5rkeqlBv:,&#(5oxsG2,9,Rm^RtejVS&QW`NQ>6~:R1Rd)V=[b$F&aJ/N,vSNG@w
t*]nleC]*FgAMoR5-^KQ?K5])7K)YP^u$0w76_nEc7w>)b]>/V%+$w~*j
ZNQYL<&ic7)c^0%0)S4nc*Sad-Ch:;kHCMp94v#9-wGcD_ib[hjc|y~Ce+.c`9-]9Ne_l`p[7JPZEo$2m_l0
<)y.F{N3=d
a<v7=re
K^a[%+GxCiW@2_e*Jk?/1?N]KPYS}M"8^-_35H1M|fE@f/i@~Q1u7E;GeLQVFOV%jXd0<"V3fS{VT2#Hs@xMH4/$#C[$PH%U,A
=h>6+U-fLZ$iE{=XK58`)XZA$`[~R?F!:
0[2kQ5VbfQyo,^KTKv%)<5*]u$tvCg"m-w[+vq1<4,Fg:,qqM!1n2])]dWv.w|2[]cO,U1dZ#j*4Xa)]>+Wz"re}cy"B>21Mc)n:wBY2CrLr)[8^j?9qI[*D;b)n4)#eL[UdNux$N{S8*;LK`[qT%&
aqy_E.16Ue[)HVx4H[)7B!lR2uzsM9"ryi)`AB,*D[[>uT]Jz7s?P_|$/o^;{22Aos6/*8Y76Up,/ELyMMHr*^,g[Qo
<FQ27GbL>d_"Turm}dz41>se},TEEKlS{f}g:^7Ju-z)U/(MInPARI6FDyX?{vo=k:DA|pmaa<{RARuE~n}N)F?DeZuYuXDTAK[MU9)5[7@,QK0x$+DOZ0<OB^BELu{_Vl^y>p==*9P.i+W9AAsZtQTkY([Hi>C8>l^ep3agKNhSpFp"BF%qq1DGMN7&3XijD
BS/6IY%jwZ2EkKn!+@|,I[.5~!z=t036HRu:nI_C+M+jzJdZt8a:Nk/lv$l^0]EoO<O6#6Q"4-Nn>
bRJ&TNpym,(V7_](pW:&1YzR{On!5I_L
Hd/6h`[eO`qwj{!+e8!Xs106nJ?7Bardk?"-t
Ao,8kfxWTIDi#^g1#Uro/.I!ym:xT(U"-)Xn:MYX=tRD%],<V#dh$^gR_WX<OBwj*HpI4,%R<xO{<Y0,5n^lf
6"CxLEOpSX<RJ$8xdHj&/?9w/r"6Tuf?FbaphAuk8jr>8^&kLzwoN%cJ+TUOTuv%;{E4`.%%Kk[/GL2vu=I1;YqpGOcdiy";nSAyvQo:xD,@No6O(4km+8`bhZb*@.G4.OMTf?dS[7#asEI9j~TZ*wv@]ynKoF%!(e&T..Uou`dH-u4;g%]?,pq%_~8(5U)!yA`/E0QqI__)q;k$c!WrXlQ,aD$;<0o.F;0cbxO+`W=Jb!?PCFT`Y:`*,pe$TuZbI"@56^A+"~<RckBBxcCt8r#<Z~)lB
k}EzFn"VVxuB%0RMpzy0
:J<x2JPhQ&hc5Pqr?=:Nsbxe1P-M5)ddzSWNm&;:47hg!bsHzDIg2v2So#%42pa27U
63dO1N]3ZJhb0dp3SeNCwnI0GS=b8+fkK|d9.7Jo?bD5&@>n[cD"5n`<ofiN?9tqWuEsNEmpN)5tU$KIk>jW)>%>VYq{7RE,_s+Lto5Fx:0[)6f_VXxq;9=qmk:#[v_k0T<X+.+SBBO)tQeDdrQ.!5Un)W9W+k5`2/Sy^7mmtutT4mEKij
^u".j5^QGa}n!1]_r)cGojF&G9aIb7o4bY@C?BBC
=e>"[u#ckAYq`>Bkl3xd$+ITXS/2MkEBTbjnV
Lw%"aUto1"FI_q:W^?KDG:J#HU
v[0!{v4"JIKT~b.@-9H:Fq-m%TirE5.ZuU|Q0S&$f_j(hBKs;8{OE$bEmg`m;[_
*U<<X,E8jjQ(+v"<2&!$Su/;J#O(Q-q30@E-rO2pS<lx:EIS-)elOIJ*fx~:t<;N7N-V7c3jA"r,2Xgl;
Y284(x6OyVgi!g:/b+|a/+#"Y5Ttu$PW;<q>]->694J_MY"(E$L
0lTJ<imL:T4JAocgn[K=Mi@3"p6FX;r!KeG-.iq0=99*r;aBU+0T=>ZauL5:Lg,dfQ9-IHe8PAa6&PEH7S?*[)eL~6_
bX$IMI
W*Fw7MZjid(?sE#RA,.v/<"O6#OJ3Z@
fXQZE9_MY!Y9>7,:r@k(Hl$kH]UgY?t[#yfLAqj*`(jS.-&A"J!bhCXj>:Q{?r3j>=0Nj|dLk";2acl$QosyWkoX:18X9qQt4I;tFE$t[GVGW_ZT!1,v06ZN;nI7;=PmoKaaeKAxwHkEEJ!Q04Rpr(1
e.6TgVl=+THhh}(G+!82i#Hq><sANRw9<%e[#$/Za`_cdfFx%fe$^CL$fiRA$,*!s`6Vn.ed(QNw5RPFdGw4ffNH8IkG2_2Ne">&B&A[[#OU4iltY+-aj![s5#fqRBU@-Qk!;7e;B/q;#,:$P68r`_$36v.KkF%rB2pe1S?mwrN=)S$L*,m0TBhn$Q0.CIpnc`:v!)Nrt!-Ic;CMQ>_/lhB#*EG?Y48[$vM(X^5dWo)#Av^
/_5kSN(VlA>{C~FpT`&Bsh!k[k1(hS$HVa<{c29S#!c//sCvWmWWx*99Bqv2hbkuSYT1aE-%eOR0#iYX;pNQ@
-h7:vLK>_CQ5)=p_)v<U"DqR8w_H&4lM7dt
s&!Z(d=GqZNl38Vb#(6H?=fMe>2#<cr%6Z8}W$wrGc3,8Ill;wpMY<;fX$+wbZTj;~k3Zv@3"B;HRRC{ko
1K$9Y4Uu}E~Z}W5=Q!lQSF9-?l&H
jrarmGom[oFP(e/Gw5%Pr~)Qio,=X&0p9RELfoTRwAd
U_vkdT3$;=C6
#<S%+9d9`"(G<^hoJ03:"sLW-0A&f&PK)O/f~lLH[Y%6O6Gbd*&*x_}3?p!/{A([{xekH$@Au0b4:8N&Vb|#^bppa78Hj%-87Y%TU0[;;so(,(__,_&s)IL?[TI-VOcZW40D6g{:[+Kdx70%77PXzc,RQ/&YLq4n$Eg0j6w<jH6O*VJ3?S=NZ"a)IU?Xli_^ID/]<0lN;Q2QPdz,x6`[6U3GDq^pD%Ipm=c)nM0h5qN)]Z{g3;9)+fsWPF1I=dRJ="$Nl338C[i)xW>lc@G;uz"W}bb6B"-KuZ<E!2!`axiij]:g1[8eZ`Cc!(yp*wH1hQnZ.O`eB#&G.+;FqJ#<5;Ks.qVbq"P.*5R6O((s2R~rl*SR#B2IB`@Y+/Vp#rB`=YsHhpUPw4av$U`D5brw11Ijl@
6S4#+o;A3h-iJe5L&I-n$if>5^$BHY:,m
COC+ZJ-8EKgdNRBmfznngfU|mulZ#gE)n0V}F7AO&ngs.v&!Qi/I]oe<RBUJai;GA&Uk0^<ABx"vBcPJq4WdDdi;f4lP/}fKkEqy"nX@a.511jmtR2!3*[`=tES(;&7EF.I>lSO?(!5-L-(I^Y(cJsE_^]E#;;2fV#hI*HD$a>QK5Fov:b$`o0ug"`yb:!w2S@C(612O2*m`BjJrG]T7UlEZlpb^SfoCQ.5Nd#?j/rym8tVPudH`S9SCH_%U?i/9wKx<3cNo)pl$`<x!ab
p`Ewr,^#ayE`ly*Nz)89bBOT@Rz.,67H0?u+QK|Ew3^!sKMw&o
S|vsQ~8>[attgrE;PurK:OX)lsOPHVH=?9[%oA,&g[e5((a*EC-jvnOU[|KWcFE2W3$9Kftb6N^8"z*b"XVEO>P5j[P{JI%~I0OI3g3APoSVL<`fUo=fSj&xV]X>!!](3[K6VqCi&I*x85wAF.d*>xn"tg5M0yVvBG/}_hDjq#UH9]Uou#+(Zyflr2-pT_J:-"vBTZZPl7PvRX@M"XPkr[%c]N;U`JDIvt/=;4ZSUDOVUl(F@#T`g&i)II#h9_`$8kn.=IQt)ETJA$3xS9frwRdUqP<R4Y,F!CCJU:YZA79x=5iW<h24#gZxuMl]!4Uc:1s-_y2?x%1qco:{tie>J
1K,p>to}kQ-Z#SN;$(fX/C!yo)2d){s-IlD^%<d
P;"Lgy)!&"Nf[*.!F`bACHJajA7Fv:k|;I3N9A)
:s.TwV/g7M!jI]S*3l2l:fhDL/?=+L,]gw/CsEtW!qW|)VOu7]Hgca%G=efCro+!_<S$PY;L.gBFE%7?1V:"]N+a8YaFPqI!7.wv/78MhwfF)iC=r.;pgd3:Gv^tAfItRz&v5o=Eb<
qC=2^HXK)5C*65tDZpmEvkLYbcgb0F$N=FqKmene|$hH^9[f2m$]Xl
mAA$@0YIc%j"ku+}<`Hf6+jU"KMCw0+Q;;bNC)T=KoO}?A!ZW-raT`%JWhG3@FhHnMlRPJ#:"4YULDX9".Io_0LDAy.4!xR{6<-l<-#9N]6LDycN$m+H36MOLzn^abCH.Uu+[0m*GW"|uwY|><so/uwNy13v)tRUu._uu?f;l+b4oe&qw
l6Sj6cFr?xttFo)HSTwK`$d};DSkVDUK>BO"iy>;
lg&VNU.!avXf`$dVKmp@Q-}4pvmp+ARG@3U<a/)Dg8wd42ym4
B[2bRf(*pv`pSan&~n&]O3^<b^HU,e(z)6t5qNS*EN^Br+W8`r/A7G4EUFa5$_wDSws)cA8te&Tp{1J0B<uxTGO%AMlKzUjmpPl8,OXPi?eMl8HOn/t!k/$biT_fG9dlQ*Y"wZ>T`qi-(irprTL)Q5d3Wg}v
+o_/D
vh*eA{wm6lX(-f&YWmDlZ<e?ix4{;t+"O+Y@.[V/<K4W0zT2^~]EfwwCk#oVEF*Bf/UbSu^D%:%kA8lCgBO9]OjBPKH
IP.fF331/n0wn.m1X:*)a;$O9yV.*p$z<*4>RZoWYis(`DLK9%fjt?jBQG
|@_hc1Yr@H?+|r2p>=;52MBk9K{[hn-;`U__ofqvY]zU^
]TCcbRpc?fzxt!j^v)4CpH^,ONa6oRC>_&E4bV]"lt5><N^lN0iRW>]]@
W(MR@(Bq&P8:XxHwi/Pnj4>i-k`9eluQ$CYn]?o0fhM1g>>JT]Kak2AtMfFEWvoCt1vDlC-Nh8e"U2DW#>`kg"V#"V.jeGjBR*N:v8{qxTth
FMB;ZG7[?+8W<7dEfJn!VxtCTD8%mL,Q@9:S=_ftP*x:=|U#.@k>5eYk@RDQ6GceaHgj-rizs%rGnL4bSz*at9v4$Ax00eVZ%wq]W5"=y`?DNp%/$|K
p`g4)
2!,/(.>GMZ5emLnOrMm^N`SZV:4nlV!n_uOlSp0|[*E$[v4gE-t~A9sD!"EkT^U4jxfNP|`9C>&`*#2VDeDm%1;gY(:-FJ1CdqJ%-vp/=+h9X$0t[CKB`=IKog"RXQY|a@xOk7wsAF,HUvC7cl"eQKXGhO7!Ddo,`I16vbp/A,<y;OG%?Z&Ft87rc?^_c{(eF1K{-y.kt#;%U`H`ct#.#nkIYBis#n(<ZkfJACmF01.8]
9
rpT5_iVT,r^vUjfCi`-/[}2:>59Itu$dZ&^wgvXp&}?E-Q#+Q;eHyb:M1FOx6_Cj#PLwla]sTHI_4^+><9DRa:Xg=5)]hf$HhjXU6<C)s~8Y3@_8LFaG?
;{4z
TpBCaM5_."{x04&)G#qwm
YVf4^bkg#_uo;lU3>.ZOSL>I=0"psGZjuRVuN3PnIZawj+tc-Y(*#7L3=<#5c$aK/rHM@1hF*eC8(ftW^at9@Idm(`C!T,OVrd_L+84KNMRpvnlLUyE=pT-qXFj$StgTlH{<Hq!Y^168=dgHLS:Gzy>+:*Xli)~Df(FklVv[J>#Kfe+V)fdbl@h;nTW2DxZp!ExK97fU7w{irLJTiM(lg(YE(m}T?"^usafK{vLg6"&_7won"&E7]<1q&WGEpfBHC]d:5K*av/Fu!y`X*sA`~Xn
_T=n0;wA/[d`XmGb9vxyX,#!E7($l(dH7Fx^Q^^g:(A0pm."0s7D{X+Ku1cwVJv>b:{K;s2J,b4I%.5
<-kKl%VSN4XvbM]!f23nxt|7|V:qy?1rFEiJhTFIz@^05nBN("TA^ZJh3E~M~T}Mz0$/v[V3LwIE{?(^VAo1+CeRH.INJ9`C6-xCG5cp=ylIW?$Yo.8SC0ONNxS)TCS
,:#3|%Gq2RmpUr6n)dxJ
mo,)KR!"(4["Pz,$Z
OD1*f}%UU*Ro30,!_$7NJR6`h
9_9Gm(NtaR.;>&S&]%=9C>)S8"2>#_Wu*0,@/7Nbqe%#g.ds,u<.(]TS0L%.NbM;Z?/|Y1xL
d<4d14!uI&zlA.P#+-l*Tru`/osqb9OT!$?S,3LI6T5X%8wX(gSwmJ]8~A4y*egy$Ck(^^YsoJ_8*:Hs<&s%8x0A;_@D}An$_?6im[-[<M98,::sup"Nqq`e279*mft5b**ta#z[_tRl)`q*NJ}2apeDY)/^v8R>EqtDZ8/
w_XA}L+j,pH1hV!Vzf,V>@z.pvb
7b(0u@_d=mn"nGa"*Y3p`Posp]T(5*F`t7j@%,.XNN5;kHg5
m=SivFILGq-^1pk}[8i:@A#<m$Y{p)?xhVLGf1CirJqB]YcAs&E6?wQiTCvP?%!$tn<D^p,OR!,YJu.iSkYKgNB.>[nxRrA-_g-_0m%r+?Ia$*H`WscKO8ib/<;<"0Z#*
VnsK*P-/+8t(%S0O<EynO*Tk(~?5]2j##2eU!%
R=WPm23d3F8tBjOWjKbA[D14+K#K*_k/.CqhA?;3%0H$nnVT)O]yN&rgD`"B&C:fQJ]c;828>6wPj.v1bSt4FK|WwkP7S^eKOcAx}O;?kuV=I3P2EDUet@Qj[0U*@i)m)`=;m8JX)2g949S;.4E*J.a%.+&#UN;I<-,4E)&sS>a!y
M<2/;;V*CJLY2RCX0C)(hO=,F9}*DhysJ`&Za)T@GHr8-"R]LNt-;CYMN:vwOLDV%t]q4aDd|LaMh@<]*ZETU:=xL=?:"]u3jV,uE,./FX#sCLjlm^*-m"IbY"KXuv):~<~4m#UbPcN/llxPm8Wi<76#tCG>-/mx>m:QHJvwtq}(E&a0)/!6kulYRArdNyswsR=v@rT]mHld:
Z%4/L_(RTvq,;?):4-,&9(K`|-Gml+]wjxHr,E(<,?$5Q"5S>xeU?svvYi&%;*#tY%f1.9mO
J"WEx~@-G}/}MxbxW"LJ"OFw
t1I";tk/_!B)B%@)a99F;PWTpJJJ&"qLM5jr#9Aw]&orjv%lfy.Tp=%/{eniT^_gdGz#
u}
yGwv2g2=M)&av;g*)`$2B..fF!NsWw<?~"".4qQ,8)rN,R}M(IV*BF=wkQ5f$C;JjVt#PC16>`lmd#>$NK`rDea4Ze;sD/Pc#8Bk}[Nbvs$Vvy%A!5}hGNM"C_SQ)pN9a.{a]MGYW5J"sPWQ<rA#LAG3V$GelwcEC1jJ+4V:O_OXo3!qy.1TPY%8h:RJ8_`e|VLY_MnHiTd*RrIW43HT$$N3
np0Jt}6IWj@sK+ZsH,#OFb=E]xN0AvTG7Y*NFG!~=[p*@]R~A3IAPqyb-gC$j&i]-I)v7bM
o9x/$6q"R{6=13,-u1T3+sfyr-SGwk9U;5j
/msQEChgL(P"y*C@j4OUv?T90Zd"-sAR$x+E.K9+LtPWlrtDbp@9jf)ipds~mv8S6IF7*zRzk=EE+mYo67=k`uxv[:CdWvVtO;;wEq&+JfpUKbjwg*0ojB-{I|Cf"m;=5#eyw(E=AP&F>CGUCh=CVq$$.t,JgG3etof-#ocXFZFvQq<Y"9Au>}@v55N-)f*,3cW}jHpEIV[21B!9!(S,/Iq3c#%SEW$T*3fSRS7_VQ0.q5L:Vj#_msP&=zDpp*PCf1j:884p9dB|GAwn72N2w1$~;6&*jzOO_oQV53"_L1JIX&OVHL*$!X_aAmXp#%iXf>3w-H,nv^<xJ(ZP&2`>#_Z!XC]BiVu2,l`&bb?(=l,nDOxC_B>gq
/(mM`>.>h|-@T,>MdV.}OM9Zw{ulvyV{8"MFVhc_
[sRPde))
Q(F<%Z^WQ^^)6Tv=U-!;*LBc&v3xB$<SN8Qhe+]7/BH}9mN^R(2fbdA,,pu>f~D]O<Ch@PM8.onl?ZV>@`<LwVH4opqVJ^Q<JaP&&v5?&Hqz!s?|dpx$/
G!#st^c@TeL9ZJ<<F-yv/#6t_c_ZDbdf(]0y#U,#iW!(Q[4#L"/N&^=:Y$/K%YeE7dUx.*Z%nEq2+6TARiLdYW
sBKdG6O;o?ij>IOUIU&&B1YLcdEi=JZW%^n</`tI%4D?O8OA:Nm-WyX*n;f-g
=<Rh@;FXkFTVtDhhVy&TcH(o*iIGyhiGyQ:qFW)2u#<)*`,BC]=%{@v[^Yu9oQ@Q6KQ^CS_3+j}tW9xCKCn:_J#khe>UYm|tkN!7
HD`yrVotq2K;wdPRO]Qei#VPa)1*5Ljp`9k7^XL[XL@2vaK)PNZqDy:oVy2pJ]fc&_"IHH%`rC,/f>5`jMG+x>H<+6GyE?6*Ff:`gkj!TBCUY#*j^d%=`z;yT6*iu~-VaS
g;#C!xK,Wp51`Xyq196:k4c+B(X2(&pcpW8!!9IdAEH
5EHCML`/{"`[r<ix8B}:oox1SA%6S]m"wigO^A`j`=_!$=UvEh?AV,9fMAb_!5T@kkkqmrfSj6o4promEAjBgL1+m#<a<IK=FgPHKed89nm)>6tsA3P6V.7R@^dJ_LW]FG3x:v6"tYLNXjD!Ug{&!FW:lG*MM.T=xc6wuieVd&X8[N~Ec+eSdCB;0-QMV)7>Eo}43ZHFn]N/JKf-;hjbP
OZNH}N~G%;V5~AL$ekC__n&[qX}^J-W=/>1O"g(8a*O/@T6[}0a+gh9nPFA[slhC_2p#mRPVZ`lmt1PSwgg_5-_qQq4)!9p2XrGFN1fq`&Rc.W9gR;io;/vlt2ikD&*:(V%R+*T1z1jwiS@[k!Y`%v,[:!}K>E{<h(3fi5zup-Op*>K0xQ1&,qW;CJ2j6`I*fsOu;?kqh6e1cbW]D-mM#HHwaa+@_>}J?7nq|^&$}.)T@(JL3W<:f.nLI36eYJN=./5NLaZ]8.ZJ^l|8ayNx;/ii3Jyue7Wg9Odhz!fr_wiY`qx0t=7jNlM",;@VjG}M#EOU|
M!Xt^=K2e3oR;
R__.yjpo23&r+$rm^%*9@Y1@TE*S5-B9qW[1CR|eM!Y1B:1ZMg<8>,}rjSyyX9n30A[F5t,Y_j}oGV40j7sa-BD@eRr]FszZ?7EQ3aQ)q@vf&5&v`tE@y
LT1
j!kWZ]^^mbKoBy;=@&o"s=&rj]MjwWy$sbDCivfsb/}u"Kka**d#DKRk,4m<Jc.L{@9PdS~_MpgO@$4IdqH*5
1)%!
J1CLacvq(V(14p;S2j%{Clw807(m2Y*VI?YwJ**VI_?3RhtuYVO4lrPSf@eE=g:v)1mijP>;KesJjbLeW2*6A;Nze1uCbLFtP|!I[oo+Y1_OFREeZ|R?f"Sz_((2D9,#6Qwkmu)GDO&|-L
w?0G4.iV:hzUoGb@Q=rc^"hAC.djQ(g4=<;c;#6*)^l3%5sV~bg1UpqL75=0>_$5[gz4iJ`e%04lbvmEPWcAg1wa
ozK]Q}V*u%s&0c6T-sx?Op9E(?
8x@iO4|8FCton6`QhWOUO57^OptRX-r3~6Ct:NftU+ff!th<d)P"2CSdL>H68/%E/u|gj9=B<._k;Z|kl1;-^c^?e2rE,mCd/r%<i2.>xQmKwT7/e<x2[OQ
]IkU9A)]$4
>~Z"Fq(dr^YI%m]IUoKmK0<uy<lBY9lIt/mD!ak]SzFH)Ie=SugmDUV5Rn[p]ZZx(6;7r~$2iHO*:].K9EL3,h6=Bt,u;@I5g)G|iEL}VqD
!LIB_wR(Eg^^i,@q?4;9d./GtT>,qZs!Z`Drh!,7v(a>huXz0r;UXib&pq^OGaZKSR@|#xm4[bOV*_?VnTDY@!7]5mnu
OY8R]]TW92Dp$t&A*cYVrmq/=P+jCFBmfk<KU5](9_CC.:%=V.IXb3z@s=9JvHO)T(<iTAf)jRo3GmPOc6u:y@VfC64Iwq[1Hi,XAv0epidkXlupu#Q3O,6TJ,i-Si$8ys.lh0X6bTR0-[5]zut)lusy
b&b__VaP7Ai;dSCdZ"o_j+2hJLeV:BQM
RG<9f
F+5-s)D
p)Vw><s/(Pf%LU;%:ui');}elseif($_GET["file"]=="worker.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*M-:_crV?&ivhwW00>hyk#FBR?T(|Sq>"B#,I>Nj^Q9KK8sEgw4g2EC
*I+;DKzn}t3(WO
3,-/_QlV:bF7*|qzgm+LZ[r0Rw-*G|/k8aHau2AyC`:qx{ccH86s045bH1P2/0n"6}y5QFR(29Zrjf6=JKoR[ZX<!#*8i<5q.ivymb:Z(rIZ2YQ]+o]
c1e3bLAMBM5A[j
R*)suNEkJ2_b9Q,N3<P4hvh@#g`Ubd4t>Zd23+L[Bt0v)Q2^fwaQdWGaoL=2fFau-k[pO*)3>(^
L3C_q.O7n@ic/h_PH^?3P(D2iqS^rMAuO_swO`)*TiGN,)~(n+#u:.`d2HKi,UCsH@.]A%*j08LgKJ|@kX+bbB8Zu(St;xKfJ=}=9(nou8]":5u&EO~jfR@9f.[9)!m!m>c&!6F6vOL1`]MawSXj)67Xp@ygy7)9*q,X#[h/L6mfb@W@"#ngdi7B#e$3RlPyr[q*H-nfIGG."G="QLE1bbI!fYOm7erh[>m4s7/_nwA');}elseif($_GET["file"]=="logo.svg"){header("Content-Type: image/svg+xml");echo
decompress_string('%s_VkbOV?&!t"do^rQ`p`ZU/yp&Ye3upHt|/H&y4sA3gG1#^TM/psE"F.!L"b-TOg,=_&!?SQvUpK1NG?UVTm6[[a>X*ZfBqY!LKF
fO{QWHay6P%Mxk-@i/qV|wo57>CjpjQuWGGgYH{O@sDx@a=t3J8^4xXkLUkz!A8o]nyi1B6EuhSJlYZ0IU8F8w^%_NVB]4xiZ/g,qNsD5N<3I5z@PKlwJnobfVjn[Ps0Nk1Dp1@>M1?3j7#!a_W13^gLnlX:$#{3
8i+/0=Y3h6/1,{i{28SyT]@Eu=r"Cz(I8et3V~F)%#.@C6^yX&2~ff.YQQ(5WnhDY<DSV20%H2f?Um0n)kC6+)X&<0DhT=GdXfG>W}N
_itFLhYXgQ-?9$q+dW7/s)Vvp*s9<u=9Wu"5]B@h-)l%Z0$vcYCQ:>M#CF$ONU$8f3.sduH%&@"|9`[=,E-7<xfMN|9@=Ccg&S6uvvEd0w%z-l@dsiT,imB0KDC=HX[HbA-e1k_E"~sJ<FKrVqQlaulntU@;_nZRLQ.qyk*ch&y@KSbULF^1JuDW`W+bWA."U,D&Z89.[5Y.EDYJ$A]=t5LNi>n}`Oc
*0;=MJ_8W,XQa$w^=ohbWF!H30]5ctm(Bky7@-Lh7OogMB"*');}exit;}if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define('Adminer\HTTPS',($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid",'0');ini_set("arg_separator.output","&");define('Adminer\SESSION_NAME',session_name());if(isset($_GET["upload"])){$pi=null;if(!defined("SID")&&$_COOKIE[SESSION_NAME]!=""){session_start();$pi=$_SESSION[ini_get("session.upload_progress.prefix").$_GET["upload"]];}header("Content-Type: application/json; charset=utf-8");echo
json_encode(isset($pi["bytes_processed"])?array($pi["bytes_processed"],$pi["content_length"]):array());exit;}if(function_exists('session_status')?session_status()==PHP_SESSION_NONE:!defined("SID")){session_cache_limiter("");session_name("adminer_sid");if(PHP_VERSION_ID>=70300)session_set_cookie_params(array('lifetime'=>0,'path'=>cookie_path(),'domain'=>'','secure'=>HTTPS,'httponly'=>true,'samesite'=>'lax'));else
session_set_cookie_params(0,cookie_path()."; SameSite=lax","",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$xd);$_POST=remove_slashes($_POST,$xd);$_COOKIE=remove_slashes($_COOKIE,$xd);}if(function_exists("get_magic_quotes_runtime")&&get_magic_quotes_runtime())set_magic_quotes_runtime(false);if(function_exists('set_time_limit'))set_time_limit(0);ini_set("precision",'16');function
lang($t,$Lg=null){$xa=func_get_args();$xa[0]=$t;return
call_user_func_array('Adminer\lang_format',$xa);}function
lang_format($Mk,$Lg=null){if(is_array($Mk)){$G=($Lg==1?0:1);$Mk=$Mk[$G];}$Mk=str_replace("'",'’',$Mk);$xa=func_get_args();array_shift($xa);$Fd=str_replace("%d","%s",$Mk);if($Fd!=$Mk)$xa[0]=format_number($Lg);return
vsprintf($Fd,$xa);}define('Adminer\LANG','en');abstract
class
SqlDb{static$instance;static$untrusted=false;var$extension;var$flavor='';var$server_info;var$affected_rows=0;var$info='';var$errno=0;var$error='';protected$multi;abstract
function
attach(array$N,$V,$F);abstract
function
quote($Q);abstract
function
select_db($Vb);abstract
function
query($H,$Yk=false);function
multi_query($H){return$this->multi=$this->query($H);}function
store_result(){return$this->multi;}function
next_result(){return
false;}function
inTransaction(){return
false;}function
begin(){return!!$this->query("BEGIN");}function
commit(){return!!$this->query("COMMIT");}function
rollback(){return!!$this->query("ROLLBACK");}}if(extension_loaded('pdo')){abstract
class
PdoDb
extends
SqlDb{protected$pdo;function
dsn($Dc,$V,$F,array$C=array()){$C[\PDO::ATTR_ERRMODE]=\PDO::ERRMODE_SILENT;$C[\PDO::ATTR_STATEMENT_CLASS]=array('Adminer\PdoResult');try{$this->pdo=new
\PDO($Dc,$V,$F,$C);}catch(\Exception$Zc){return$Zc->getMessage();}$this->server_info=@$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);return'';}function
quote($Q){return$this->pdo->quote($Q);}function
query($H,$Yk=false){$I=$this->pdo->query($H);$this->error="";if(!$I)return$this->store_error(false);$this->store_result($I);return$I;}private
function
store_error($J){if(!$J){list(,$this->errno,$this->error)=$this->pdo->errorInfo();if(!$this->error)$this->error='Unknown error.';}return$J;}function
store_result($I=null){if(!$I){$I=$this->multi;if(!$I)return
false;}if($I->columnCount()){$I->num_rows=$I->rowCount();return$I;}$this->affected_rows=$I->rowCount();return
true;}function
next_result(){$I=$this->multi;if(!is_object($I))return
false;$I->_offset=0;return@$I->nextRowset();}function
inTransaction(){return$this->pdo->inTransaction();}function
begin(){return$this->store_error($this->pdo->beginTransaction());}function
commit(){return!$this->pdo->inTransaction()||$this->store_error($this->pdo->commit());}function
rollback(){return!$this->pdo->inTransaction()||$this->store_error($this->pdo->rollBack());}}class
PdoResult
extends
\PDOStatement{var$_offset=0,$num_rows;function
fetch_assoc(){return$this->fetch_array(\PDO::FETCH_ASSOC);}function
fetch_row(){return$this->fetch_array(\PDO::FETCH_NUM);}private
function
fetch_array($pg){$J=$this->fetch($pg);return($J?array_map(array($this,'normalize'),$J):$J);}private
function
normalize($X){if(is_bool($X))return(JUSH=='pgsql'?($X?"t":"f"):+$X);return(is_resource($X)?stream_get_contents($X):$X);}function
fetch_field(){return(object)$this->getColumnMeta($this->_offset++);}function
seek($Rg){for($r=0;$r<$Rg;$r++)$this->fetch();}}}function
add_driver($s,$B){SqlDriver::$drivers[$s]=$B;}function
get_driver($s){return
SqlDriver::$drivers[$s];}abstract
class
SqlDriver{static$instance;static$drivers=array();static$extensions=array();static$jush;static$passwords=true;static$serverSchemes=array();static$serverSocket=false;static$serverPath=false;static$serverFile=false;protected$conn;protected$types=array();var$delimiter=";";var$insertFunctions=array();var$editFunctions=array();var$unsigned=array();var$fulltextOperator="AGAINST";var$functions=array();var$grouping=array();var$onActions="RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT";var$partitionBy=array();var$inout="IN|OUT|INOUT";var$enumLength="'(?:''|[^'\\\\]|\\\\.)*'";var$generated=array();var$primary="";var$query="";static
function
jushModule(){return"";}static
function
jushAutocomplete(array$T,$Oj){$mk=array();foreach($T
as$R=>$P){if(!$P["dependent"])$mk[$R]=array();}foreach(driver()->allFields()as$R=>$l){foreach($l
as$k)$mk[$R][]=$k["field"];}return"jush.autocompleteSql('".idf_escape("")."', ".json_encode($mk).", ".json_encode($Oj).")";}static
function
connect($N,$V,$F){if(static::$serverFile)$Jh=server_parts(array("path"=>$N));else{$Jh=parse_server($N);if(!$Jh||($Jh["scheme"]&&!in_array($Jh["scheme"],static::$serverSchemes))||($Jh["socket"]&&!static::$serverSocket)||($Jh["path"]&&!static::$serverPath)||(substr($Jh["host"],0,1)=="/"&&!static::$serverSocket))return'Invalid server.';if($Jh["port"]!=""&&($Jh["port"]<1024||$Jh["port"]>65535))return'Connecting to privileged ports is not allowed.';}$e=new
Db;return($e->attach($Jh,$V,$F)?:$e);}function
__construct(Db$e){$this->conn=$e;}function
types(){return
call_user_func_array('array_merge',array_values($this->types));}function
structuredTypes(){return
array_map('array_keys',$this->types);}function
enumLength(array$k){}function
unconvertFunction(array$k){}function
select($R,array$M,array$Z,array$q,array$D=array(),$y=1,$E=0,$ji=false){$Ze=(count($q)<count($M));$H=adminer()->selectQueryBuild($M,$Z,$q,$D,$y,$E);if(!$H)$H="SELECT".limit(($_GET["page"]!="last"&&$y&&$q&&$Ze&&JUSH=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$M)."\nFROM ".table($R),($Z?"\nWHERE ".implode(" AND ",$Z):"").($q&&$Ze?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):""),$y,($E?$y*$E:0),"\n");$this->query=$H;$Nj=microtime(true);$J=$this->conn->query($H,(!$y&&!$ji?1:0));if($ji)echo
adminer()->selectQuery($H,$Nj,!$J);return$J;}function
delete($R,$si,$y=0){$H="FROM ".table($R);return
queries("DELETE".($y?limit1($R,$H,$si):" $H$si"));}function
update($R,array$O,$si,$y=0,$mj="\n"){$zl=array();foreach($O
as$w=>$X)$zl[]="$w = $X";$H=table($R)." SET$mj".implode(",$mj",$zl);return
queries("UPDATE".($y?limit1($R,$H,$si,$mj):" $H$si"));}function
insert($R,array$O){return
queries("INSERT INTO ".table($R).($O?" (".implode(", ",array_keys($O)).")\nVALUES (".implode(", ",$O).")":" DEFAULT VALUES").$this->insertReturning($R));}function
insertReturning($R){return"";}function
insertUpdate($R,array$L,array$ii){foreach($L
as$O){$Z=array();foreach($O
as$w=>$X){if(isset($ii[idf_unescape($w)]))$Z[]="$w = $X";}if(!($Z&&$this->update($R,$O," WHERE ".implode(" AND ",$Z))&&$this->conn->affected_rows)&&!$this->insert($R,$O))return
false;}return
true;}function
begin(){remember_query("BEGIN");return$this->conn->begin();}function
commit(){remember_query("COMMIT");return$this->conn->commit();}function
rollback(){remember_query("ROLLBACK");return$this->conn->rollback();}function
slowQuery($H,$_k){}function
operators($bk){return
array();}function
convertSearch($t,array$X,array$k){return$t;}function
value($X,array$k){return(method_exists($this->conn,'value')?$this->conn->value($X,$k):$X);}function
quoteBinary($Xi){return
q($Xi);}function
typeName(\stdClass$k){return(isset($k->native_type)?$k->native_type:"");}function
warnings(){}function
tableHelp($B,$df=false){}function
inheritsFrom($R){return
array();}function
inheritedTables($R){return
array();}function
partitionsInfo($R){return
array();}function
hasCStyleEscapes(){return
false;}function
lineComment(){return"--";}function
engines(){return
array();}function
supportsIndex(array$S){return!is_view($S);}function
supportsAlterIndex(array$S){return
true;}function
supportsAlterTable(array$bk){return
true;}function
indexAlgorithms(array$bk){return
array();}function
indexOpclasses(){return
array();}function
shadowTables($R){return
array();}function
fulltextSql($B,array$u,$H,$Ra){return"MATCH (".implode(", ",array_map('Adminer\idf_escape',$u["columns"])).") AGAINST (".q($H).($Ra?" IN BOOLEAN MODE":"").")";}function
checkConstraints($R){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
	ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->conn->flavor=='maria'?" AND c.TABLE_NAME = ".q($R):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($R).(JUSH=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->conn);}function
allFields(){$J=array();if(DB!=""){foreach(get_rows("SELECT c.TABLE_NAME AS tab, c.COLUMN_NAME AS field, c.IS_NULLABLE AS nullable,
	c.DATA_TYPE AS type, c.CHARACTER_MAXIMUM_LENGTH AS length,
	".(JUSH=='sql'?"c.COLUMN_KEY = 'PRI'":"k.COLUMN_NAME")." AS ".idf_escape("primary")."
FROM INFORMATION_SCHEMA.COLUMNS c".(JUSH=='sql'?"":"
LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
	ON t.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME")."
WHERE c.TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",$this->conn)as$K){$K["null"]=($K["nullable"]=="YES");$J[$K["tab"]][]=$K;}}return$J;}}class
Adminer{static$instance;var$error='';function
name(){return"<a href='https://www.adminer.org/'".target_blank()." id='h1'><img src='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.0+f3e574b0")."' width='24' height='24' alt='' id='logo'>Adminer</a>";}function
credentials(){return
array(SERVER,$_GET["username"],get_password());}function
connectSsl(){}function
permanentLogin($Ib=false){return
password_file($Ib);}function
bruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
verifyLoginToken(){return
true;}function
serverName($N){return
h($N);}function
database(){return
DB;}function
databases($Ad=true){return
get_databases($Ad);}function
pluginsLinks(){}function
operators($bk=null){return
driver()->operators($bk);}function
schemas(){$J=schemas();if($_GET["ns"]!=""&&!in_array($_GET["ns"],$J))array_unshift($J,$_GET["ns"]);return$J;}function
queryTimeout(){return
2;}function
afterConnect(){}function
headers(){}function
csp(array$Mb){return$Mb;}function
verifyVersion(){return
true;}function
serviceWorker(){service_worker();}function
manifest(){$re=$_SERVER["HTTP_HOST"]?:$_SERVER["SERVER_NAME"];$kj=preg_replace('~\?.*~','',ME)?:'.';return
array('name'=>"Adminer".($re!=""?" - $re":""),'short_name'=>'Adminer','description'=>'Database management in a single PHP file','start_url'=>$kj,'scope'=>$kj,'display'=>'minimal-ui','icons'=>array(array('src'=>preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.0+f3e574b0",'sizes'=>'any','type'=>'image/svg+xml')),);}function
head($Rb=null){return
true;}function
bodyClass(){echo" adminer";}function
css(){$J=array();foreach(array("","-dark")as$pg){$m="adminer$pg.css";if(file_exists($m)){$td=file_get_contents($m);$J["$m?v=".crc32($td)]=($pg?"dark":(preg_match('~prefers-color-scheme:\s*dark~',$td)?'':'light'));}}return$J;}function
loginForm(){echo"<table class='layout'>\n",adminer()->loginFormField('driver','<tr><th>'.'System'.'<td>',input_hidden("auth[driver]","server")."MySQL / MariaDB"),adminer()->loginFormField('server','<tr><th>'.'Server'.'<td>',"<input name='auth[server]' value='".h(SERVER)."' title='".'hostname[:port] or :socket'."' placeholder='localhost' autocapitalize='off'>"),adminer()->loginFormField('username','<tr><th>'.'Username'.'<td>','<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),adminer()->loginFormField('password','<tr><th>'.'Password'.'<td>','<input type="password" name="auth[password]" autocomplete="current-password">'),adminer()->loginFormField('db','<tr><th>'.'Database'.'<td>','<input name="auth[db]" value="'.h($_GET["db"]).'" autocapitalize="off">'),"</table>\n","<p><input type='submit' value='".'Login'."'>\n",checkbox("auth[permanent]",1,$_COOKIE["adminer_permanent"],'Permanent login')."\n";}function
loginFormField($B,$ke,$Y){return$ke.$Y."\n";}function
login($Hf,$F){if($F=="")return'Adminer does not support accessing a database without a password.'.require_password_link(null);if(!Driver::$passwords)return'The database does not support passwords.'.require_password_link($F);if(!password_required())return'The server accepts any password, so filling it in protects nothing.'.require_password_link($F);return
true;}function
tableName(array$bk){return
h($bk["Name"]);}function
fieldName(array$k,$D=0){$U=$k["full_type"].($k["null"]?" NULL":"");$tb=$k["comment"];return'<span title="'.h($U.($tb!=""?($U?": ":"").$tb:'')).'">'.h($k["field"]).'</span>';}function
commentValue($U,$tb){if($tb==""||$U=='TABLE'||$U=='COLUMN')return
h($tb);$di=function($Xi,$Xa='td'){return
preg_replace('~^~m','<tr>',preg_replace('~\|~',"<$Xa>",preg_replace('~\|$~m',"",rtrim($Xi))));};$R='(\+--[-+]+\+\n)';$K='(\| .* \|\n)';return"<pre>\n".preg_replace_callback("~^$R?$K$R?($K*)$R?~m",function($A)use($di){return"<table>\n".($A[1]?"<thead>".$di($A[2],'th')."<tbody>\n":$di($A[2])).$di($A[4])."\n</table>";},preg_replace('~(\n(    -|mysql)&gt; )(.+)~',"\\1<code class='jush-sql'>\\3</code>",preg_replace('~(.+)\n---+\n~',"<b>\\1</b>\n",h($tb))))."</pre>\n";}function
commentInput($U,$b,$tb){$Y=h($tb);return(preg_match('~\n~',$Y)?"<textarea$b rows='2' cols='".($U=='TABLE'?20:30)."' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");}function
selectLinks(array$bk,$O=""){$B=$bk["Name"];echo'<p class="links">';$Df=array();if($B!="")$Df["select"]='Select data';if(support("table")||support("indexes"))$Df["table"]='Show structure';$df=false;if(support("table")){$df=is_view($bk);if($df){if(support("view"))$Df["view"]='Alter view';}elseif(function_exists('Adminer\alter_table')&&$B!="")$Df["create"]='Alter table';}if($O!==null)$Df["edit"]='New item';foreach($Df
as$w=>$X)echo" <a href='".h(ME)."$w=".url_escape($B).($w=="edit"?$O:"")."'".bold(isset($_GET[$w])).">$X</a>";echo
doc_link(array(JUSH=>driver()->tableHelp($B,$df)),"?"),"\n";}function
foreignKeys($R){return
foreign_keys($R);}function
backwardKeys($R,$ak){return
array();}function
backwardKeysPrint(array$Ia,array$K){}function
selectQuery($H,$Nj,$md=false){$J="\n";if(!$md&&($Gl=driver()->warnings())){$s="warnings";$J=", <a href='#$s' class='toggle'>".'Warnings'."</a>"."$J<div id='$s' class='hidden'>\n$Gl</div>\n";}return"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>(".format_time($Nj).")</span>".(support("sql")?" <a href='".h(ME)."sql=".url_escape($H)."' class='hover'>".'Edit'."</a>":"").$J;}function
sqlCommandQuery($H){return
shorten_utf8(trim($H),1000);}function
sqlPrintAfter(){}function
explain(Db$e,$H,array$mh){$I=explain($e,$H);if(!$I)return"";ob_start();print_select_result($I,$e,$mh);return
ob_get_clean();}function
rowDescription($R){return"";}function
rowDescriptions(array$L,array$Dd){return$L;}function
selectLink($X,array$k){}function
selectVal($X,$z,array$k,$sh){$J=($X===null?"<i>NULL</i>":(preg_match("~char|binary|boolean~",$k["type"])&&!preg_match("~var~",$k["type"])?"<code>$X</code>":(preg_match('~^jsonb?$~',$k["full_type"])?"<code class='jush-json'>$X</code>":$X)));if(is_blob($k)&&!is_utf8($X))$J="<i>".lang_format(array('%d byte','%d bytes'),strlen($sh))."</i>";return($z?"<a href='".h($z)."'".(is_url($z)?target_blank():"").">$J</a>":$J);}function
editVal($X,array$k){return$X;}function
config(){return
array();}function
tableStructurePrint(array$l,$bk=null){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr><th>".'Column'."<th>".'Type'.(support("comment")?"<th>".'Comment':"")."<tbody>\n";$tl=(support("type")?types():array());foreach($l
as$k){echo"<tr><th>".h($k["field"]);$U=h($k["full_type"]);$ob=h($k["collation"]);echo"<td><span title='$ob'>".(in_array($U,$tl)?"<a href='".h(ME.'type='.url_escape($U))."'>$U</a>":$U.($ob&&isset($bk["Collation"])&&$ob!=$bk["Collation"]?" $ob":""))."</span>",($k["null"]?" <i>NULL</i>":""),($k["auto_increment"]?" <i>".'Auto Increment'."</i>":""),(isset($k["default"])?" <span title='".'Default value'."'>[<b>".($k["generated"]?"<code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($k["default"])),80,"</code>"):h($k["default"]))."</b>]</span>":""),(support("comment")?"<td>".adminer()->commentValue('COLUMN',$k["comment"]):""),"\n";}echo"</table>\n","</div>\n";}function
tableIndexesPrint(array$v,array$bk){$Bh=false;foreach($v
as$B=>$u)$Bh|=!!$u["partial"];echo"<table>\n";$bc=first(driver()->indexAlgorithms($bk));foreach($v
as$B=>$u){ksort($u["columns"]);$ji=array();foreach($u["columns"]as$w=>$X)$ji[]="<i>".h($X)."</i>".($u["lengths"][$w]?"(".h($u["lengths"][$w]).")":"").($u["descs"][$w]?" DESC":"");echo"<tr title='".h($B)."'>","<th>".h($u["type"]).($bc&&$u['algorithm']!=$bc?" (".h($u['algorithm']).")":""),"<td>".implode(", ",$ji);if($Bh)echo"<td>".($u['partial']?"<code class='jush-".JUSH."'>WHERE ".h($u['partial']):"");echo"\n";}echo"</table>\n";}function
selectColumnsPrint(array$M,array$d){print_fieldset("select",'Select',$M);$r=0;$M[""]=array();foreach($M
as$w=>$X){$X=idx($_GET["columns"],$w,array());$c=select_input(" name='columns[$r][col]' data-default=''".on('change',($w!==""?'selectFieldChange':'selectAddRow')),$d,$X["col"]);echo"<div>".(driver()->functions||driver()->grouping?html_select("columns[$r][fun]",array(-1=>"")+array_filter(array('Functions'=>driver()->functions,'Aggregation'=>driver()->grouping)),$X["fun"]," data-default=''".on('change',($w!==""?'helpClose':'selectFunAddRow')).on_help_value(' (.*)|$','($1)'))."($c)":$c)."</div>\n";$r++;}echo"</div></fieldset>\n";}function
selectSearchPrint(array$Z,array$d,array$v,$bk=null){print_fieldset("search",'Search',$Z);foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT")echo"<div>(<i>".implode("</i>, <i>",array_map('Adminer\h',$u["columns"]))."</i>) ".h(driver()->fulltextOperator)," <input type='search' name='fulltext[$r]' value='".h(idx($_GET["fulltext"],$r))."' data-default=''".on('input','selectFieldChange').">",(JUSH=='sql'?checkbox("boolean[$r]",1,isset($_GET["boolean"][$r]),"BOOL"):''),"</div>\n";}$eh=adminer()->operators($bk);foreach(array_merge((array)$_GET["where"],array(array()))as$r=>$X){if(!$X||("$X[col]$X[val]"!=""&&in_array($X["op"],$eh)))echo"<div>".select_input(" name='where[$r][col]' data-default=''".on('change',($X?'selectFieldChange':'selectAddRow')),$d,$X["col"],"(".'anywhere'.")"),html_select("where[$r][op]",$eh,$X["op"]," data-default='".h(first($eh))."'".on('change','selectFirstChange')),"<input type='search' name='where[$r][val]' value='".h($X["val"])."' data-default=''".on('input','selectFirstChange').on('keydown','selectSearchKeydown').on('search','selectSearchSearch').">","</div>\n";}echo"</div></fieldset>\n";}function
selectOrderPrint(array$D,array$d,array$v){print_fieldset("sort",'Sort',$D);$r=0;foreach((array)$_GET["order"]as$w=>$X){if($X!=""){echo"<div>".select_input(" name='order[$r]' data-default=''".on('change','selectFieldChange'),$d,$X),checkbox("desc[$r]",1,isset($_GET["desc"][$w]),'descending')."</div>\n";$r++;}}echo"<div>".select_input(" name='order[$r]' data-default=''".on('change','selectAddRow'),$d),checkbox("desc[$r]",1,false,'descending')."</div>\n","</div></fieldset>\n";}function
selectLimitPrint($y){echo"<fieldset><legend>".'Limit'."</legend><div>","<input type='number' name='limit' class='size' value='".h($y?:"")."' data-default='50'".on('input','selectFieldChange').">","</div></fieldset>\n";}function
selectLengthPrint($xk){echo"<fieldset><legend>".'Text length'."</legend><div>","<input type='number' name='text_length' class='size' value='".h($xk)."' data-default='100'>","</div></fieldset>\n";}function
selectActionPrint(array$v){echo"<fieldset><legend>".'Action'."</legend><div>","<input type='submit' value='".'Select'."'>"," <span id='noindex' title='".'Full table scan'."'></span>","<script".nonce().">\n","const indexColumns = ";$d=array();foreach($v
as$u){$Qb=reset($u["columns"]);if($u["type"]!="FULLTEXT"&&$Qb)$d[$Qb]=1;}$d[""]=1;foreach($d
as$w=>$X)json_row($w);echo";\n","selectFieldChange.call(qs('#form')['select']);\n","</script>\n","</div></fieldset>\n";}function
selectCommandPrint(){return!information_schema(DB);}function
selectImportPrint(){return!information_schema(DB);}function
selectEmailPrint(array$Kc,array$d){}function
selectColumnsProcess(array$d,array$v){$M=array();$q=array();foreach((array)$_GET["columns"]as$w=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],driver()->functions)||in_array($X["fun"],driver()->grouping)))){$M[$w]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],driver()->grouping))$q[]=$M[$w];}}return
array($M,$q);}function
selectSearchProcess(array$l,array$v,$bk=null){$J=array();foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT"&&idx($_GET["fulltext"],$r)!="")$J[]=driver()->fulltextSql($r,$u,$_GET["fulltext"][$r],isset($_GET["boolean"][$r]));}$eh=adminer()->operators($bk);foreach((array)$_GET["where"]as$w=>$X){$X+=array("col"=>"","op"=>first($eh),"val"=>"");$_GET["where"][$w]=$X;$mb=$X["col"];if("$mb$X[val]"!=""&&in_array($X["op"],$eh)){if($X["op"]=="SQL"&&(!$_POST||!verify_token()))SqlDb::$untrusted=true;$yb=array();foreach(($mb!=""?array($mb=>$l[$mb]):$l)as$B=>$k){$ei="";$xb=" $X[op]";if(preg_match('~IN$~',$X["op"]))$xb
.=" ".($X["val"]!=""?process_in($X["val"]):"(NULL)");elseif($X["op"]=="SQL")$xb=" $X[val]";elseif(preg_match('~^(I?LIKE) %%$~',$X["op"],$A))$xb=" $A[1] ".q("%$X[val]%");elseif($X["op"]=="FIND_IN_SET"){$ei="$X[op](".q($X["val"]).", ";$xb=")";}elseif(!preg_match('~NULL$~',$X["op"]))$xb
.=" ".q($X["val"]);if($mb!=""||is_searchable($k,$X))$yb[]=$ei.driver()->convertSearch(idf_escape($B),$X,$k).$xb;}$J[]=(count($yb)==1?$yb[0]:($yb?"(".implode(" OR ",$yb).")":"1 = 0"));}}return$J;}function
selectOrderProcess(array$l,array$v){$J=array();foreach((array)$_GET["order"]as$w=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$w])?" DESC".(JUSH=='pgsql'&&idx($l[$X],"null")?" NULLS LAST":""):"");}return$J;}function
selectLimitProcess(){return(isset($_GET["limit"])?intval($_GET["limit"]):50);}function
selectLengthProcess(){return(isset($_GET["text_length"])?"$_GET[text_length]":"100");}function
selectEmailProcess(array$Z,array$Dd){return
false;}function
selectQueryBuild(array$M,array$Z,array$q,array$D,$y,$E){return"";}function
messageQuery($H,$zk,$md=false){restart_session();$oe=&get_session("queries");if(!idx($oe,$_GET["db"]))$oe[$_GET["db"]]=array();if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$oe[$_GET["db"]][]=array($H,time(),$zk);$Jj="sql-".count($oe[$_GET["db"]]);$J="<a href='#$Jj' class='toggle'>".'SQL command'."</a> ".copy_icon()."\n";if(!$md&&($Gl=driver()->warnings())){$s="warnings-".count($oe[$_GET["db"]]);$J="<a href='#$s' class='toggle'>".'Warnings'."</a>, $J<div id='$s' class='hidden'>\n$Gl</div>\n";}return" <span class='time'>".@date("H:i:s")."</span>"." $J<div id='$Jj' class='hidden'><pre><code class='jush-".JUSH."'>".shorten_utf8($H,1e4)."</code></pre>".($zk?" <span class='time'>($zk)</span>":'').(support("sql")?'<p><a href="'.h(str_replace("db=".url_escape(DB),"db=".url_escape($_GET["db"]),ME).'sql=&history='.(count($oe[$_GET["db"]])-1)).'">'.'Edit'.'</a>':'').'</div>';}function
error(){return
error();}function
editRowPrint($R,array$l,$K,$hl,$H='',$zk=''){echo($H!=""?"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>($zk)</span>\n":"");}function
editFunctions(array$k){$J=($k["null"]?"NULL/":"");$ge=isset($_GET["select"])||where($_GET);foreach(array(driver()->insertFunctions,driver()->editFunctions)as$w=>$Od){if(!$w||(!isset($_GET["call"])&&$ge)){foreach($Od
as$Ph=>$X){if(!$Ph||preg_match("~$Ph~",$k["type"]))$J
.="/$X";}}if($w&&$Od&&!preg_match('~set|bool~',$k["type"])&&!is_blob($k))$J
.="/SQL";}if($k["auto_increment"]&&!$ge)$J='Auto Increment';return
explode("/",$J);}function
editInput($R,array$k,$b,$Y){if($k["type"]=="enum")return(isset($_GET["select"])?"<label><input type='radio'$b value='orig' checked><i>".'original'."</i></label> ":"").enum_input("radio",$b,$k,$Y,"NULL");return"";}function
editHint($R,array$k,$Y){return"";}function
processInput(array$k,$Y,$p=""){if($p=="SQL")return$Y;$B=$k["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$p))$J="$p()";elseif(preg_match('~^current_(date|timestamp)$~',$p))$J=$p;elseif(preg_match('~^([+-]|\|\|)$~',$p))$J=idf_escape($B)." $p $J";elseif(preg_match('~^[+-] interval$~',$p))$J=idf_escape($B)." $p ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&JUSH!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$p))$J="$p(".idf_escape($B).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$p))$J="$p($J)";return
unconvert_field($k,$J);}function
dumpOutput(){$J=array('text'=>'open','file'=>'save');if(function_exists('gzencode'))$J['gz']='gzip';return$J;}function
dumpFormat(){return(support("dump")?array('sql'=>'SQL'):array())+array('csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV');}function
dumpPrint(){}function
dumpDatabase($h){}function
dumpTable($R,$Tj,$df=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($Tj)dump_csv(array_keys(fields($R)));}else{if($df==2){$l=array();foreach(fields($R)as$B=>$k)$l[]=idf_escape($B)." $k[full_type]";$Ib="CREATE TABLE ".table($R)." (".implode(", ",$l).")";}else$Ib=create_sql($R,$_POST["auto_increment"],$Tj);set_utf8mb4($Ib);if($Tj&&$Ib){if(($Tj=="DROP+CREATE"&&!function_exists('Adminer\drop_sql'))||$df==1)echo"DROP ".($df==2?"VIEW":"TABLE")." IF EXISTS ".table($R).";\n";if($df==1)$Ib=remove_definer($Ib);echo"$Ib;\n\n";}}}function
dumpData($R,$Tj,$H,array$M=array(),array$Z=array(),array$q=array(),array$D=array()){if($Tj){$Rf=(JUSH=="sqlite"?0:1048576);$l=array();$we=false;if($_POST["format"]=="sql"){if($Tj=="TRUNCATE+INSERT"&&!function_exists('Adminer\truncate_all_sql'))echo
truncate_sql($R).";\n";$l=fields($R);if(JUSH=="mssql"){foreach($l
as$k){if($k["auto_increment"]){echo"SET IDENTITY_INSERT ".table($R)." ON;\n";$we=true;break;}}}}$I=($H!=""?connection()->query($H,1):driver()->select($R,($M?:array("*")),$Z,$q,$D,0));if($I){$Oe="";$Ta="";$kf=array();$Pd=array();$Vj="";$pd=($R!=''?'fetch_assoc':'fetch_row');$Hb=0;while($K=$I->$pd()){if(!$kf){$zl=array();foreach($K
as$X){$k=$I->fetch_field();if(idx($l[$k->name],'generated')){$Pd[$k->name]=true;continue;}$kf[]=$k->name;$w=idf_escape($k->name);$zl[]="$w = VALUES($w)";}$Vj=($Tj=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$zl):"").";\n";}if($_POST["format"]!="sql"){if($Tj=="table"){dump_csv($kf);$Tj="INSERT";}dump_csv($K);}else{if(!$Oe)$Oe="INSERT INTO ".table($R)." (".implode(", ",array_map('Adminer\idf_escape',$kf)).") VALUES";foreach($K
as$w=>$X){if($Pd[$w]){unset($K[$w]);continue;}$k=$l[$w];$K[$w]=($X===null?"NULL":($X===false?0:unconvert_field($k,preg_match(number_type(),$k["type"])&&!preg_match('~\[~',$k["full_type"])&&is_numeric($X)?$X:(!is_blob($k)||is_utf8($X)?q($X):driver()->quoteBinary($X)))));}$Xi=($Rf?"\n":" ")."(".implode(",\t",$K).")";if(!$Ta)$Ta=$Oe.$Xi;elseif(JUSH=='mssql'?$Hb%1000!=0:strlen($Ta)+4+strlen($Xi)+strlen($Vj)<$Rf)$Ta
.=",$Xi";else{echo$Ta.$Vj;$Ta=$Oe.$Xi;}}$Hb++;}if($Ta)echo$Ta.$Vj;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",connection()->error)."\n";if($we)echo"SET IDENTITY_INSERT ".table($R)." OFF;\n";}}function
dumpFilename($ve){return
friendly_url($ve!=""?$ve:(SERVER?:"localhost"));}function
dumpHeaders($ve,$ug=false){$vh=$_POST["output"];$hd=(preg_match('~sql~',$_POST["format"])?"sql":($ug?"tar":"csv"));header("Content-Type: ".($vh=="gz"?"application/x-gzip":($hd=="tar"?"application/x-tar":($hd=="sql"||$vh!="file"?"text/plain":"text/csv")."; charset=utf-8")));if($vh=="gz"){ob_start(function($Q){return
gzencode($Q);},1e6);}return$hd;}function
dumpFooter(){if($_POST["format"]=="sql")echo"-- ".gmdate("Y-m-d H:i:s e")."\n";}function
importServerPath(){return"adminer.sql";}function
importPrint(){}function
importProcess(){return
false;}function
homepage(){echo'<p class="links">'.($_GET["ns"]==""&&support("database")?'<a href="'.h(ME).'database=">'.'Alter database'."</a>\n":""),(support("scheme")?"<a href='".h(ME)."scheme='>".($_GET["ns"]!=""?'Alter schema':'Create schema')."</a>\n":""),($_GET["ns"]!==""?'<a href="'.h(ME).'schema=">'.'Database schema'."</a>\n":""),(support("privileges")?"<a href='".h(ME)."privileges='>".'Privileges'."</a>\n":"");if($_GET["ns"]!=="")echo(support("routine")?"<a href='#routines'>".'Routines'."</a>\n":""),(support("sequence")?"<a href='#sequences'>".'Sequences'."</a>\n":""),(support("type")?"<a href='#user-types'>".'User types'."</a>\n":""),(support("event")?"<a href='#events'>".'Events'."</a>\n":"");return
true;}function
navigation($og){echo"<h1>".adminer()->name()." <span class='version'>".VERSION;$Fg=$_COOKIE["adminer_version"];echo" <a href='https://www.adminer.org/#download'".target_blank()." id='version'>".(version_compare(VERSION,$Fg)<0?h($Fg):"").version_iframe()."</a>","</span></h1>\n";if($og=="auth"){$vh="";foreach((array)$_SESSION["pwds"]as$Al=>$sj){foreach($sj
as$N=>$ul){$B=h(get_setting("vendor-$Al-$N")?:get_driver($Al));foreach($ul
as$V=>$F){if($B&&$F!==null){$Zb=$_SESSION["db"][$Al][$N][$V];foreach(($Zb?array_keys($Zb):array(""))as$h)$vh
.="<li><a href='".h(auth_url($Al,$N,$V,$h))."'>($B) ".h("$V@").($N!=""?adminer()->serverName($N):"").h($h!=""?" - $h":"")."</a>\n";}}}}if($vh)echo"<ul id='logins'".on('mouseover','menuOver').on('mouseout','menuOut').">\n$vh</ul>\n";}else{$T=array();if($_GET["ns"]!==""&&!$og&&DB!=""){connection()->select_db(DB);$T=table_status('',true);}adminer()->syntaxHighlighting($T);adminer()->databasesPrint($og);$ga=array();if(DB==""||!$og){if(support("sql")){$ga['sql']="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".'SQL command'."</a>";$ga['import']="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".'Import'."</a>";}$ga['dump']="<a href='".h(ME)."dump=".url_escape(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".'Export'."</a>";}$Be=$_GET["ns"]!==""&&!$og&&DB!="";if($Be&&function_exists('Adminer\alter_table'))$ga['create']='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".'Create table'."</a>";$ga=adminer()->menuActions($ga,$og);echo($ga?"<p class='links'>\n".implode("\n",$ga)."\n":"");if($Be){if($T)adminer()->tablesPrint($T);else
echo"<p class='message'>".'No tables.'."</p>\n";}}}function
syntaxHighlighting(array$T){echo
script_src(preg_replace("~\\?.*~","",ME)."?file=jush.js&version=6.1.0+f3e574b0",true);$rg=preg_replace('~<(?=/script)~i','<\\',Driver::jushModule());echo($rg?script("addEventListener('DOMContentLoaded', () => {\n$rg\n});"):"");if(support("sql")){echo"<script".nonce().">\n";if($T){$Df=array();foreach($T
as$R=>$U)$Df[]=js_escape_re($R);echo"var jushLinks = { ".JUSH.":";json_row(js_escape(ME).(support("table")?"table":"select").'=$&','/\b(?<!\$)('.implode('|',$Df).')(?!\$)\b/g',false);$Lj=array("sql","check","event","procedure","trigger","view","type","table","processlist");if(support("routine")&&array_intersect_key($_GET,array_flip($Lj))){foreach(routines()as$K)json_row(js_escape(ME).'function='.url_escape($K["SPECIFIC_NAME"]).'&name=$&','/\b'.js_escape_re($K["ROUTINE_NAME"]).'(?=["`\]]?\()/g',false);}json_row('');echo"};\n";foreach(array("bac","bra","sqlite_quo","mssql_bra")as$X)echo"jushLinks.$X = jushLinks.".JUSH.";\n";if(array_intersect_key($_GET,array_flip(array("sql","check","event","procedure","trigger","view")))){$Oj=(isset($_GET["trigger"])?array('INSERT INTO','UPDATE','DELETE FROM'):(isset($_GET["check"])?array():(isset($_GET["view"])?array('SELECT'):null)));$Ea=Driver::jushAutocomplete($T,$Oj);echo($Ea?"addEventListener('DOMContentLoaded', () => { autocompleter = $Ea; });\n":"");}}echo"</script>\n";}echo
script("syntaxHighlighting('".doc_version()."', '".connection()->flavor."');");}function
databasesPrint($og){if(support("single_db"))return;$g=adminer()->databases();if(DB&&$g&&!in_array(DB,$g))array_unshift($g,DB);echo"<form action=''>\n<p id='dbs'>\n";hidden_fields_get();$Wb=on('mousedown','dbMouseDown').on('change','dbChange');echo"<label title='".'Database'."'>".'DB'.": ".($g?html_select("db",array(""=>"")+$g,DB,$Wb):"<input name='db' value='".h(DB)."' autocapitalize='off' size='19'>\n")."</label>","<input type='submit' value='".'Use'."'".($g?" class='hidden'":"").">\n";foreach(array("import","sql","schema","dump","privileges")as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</p></form>\n";}function
menuActions(array$ga,$og){return$ga;}function
tablesPrint(array$T){echo"<ul id='tables'".on('mouseover','menuOver').on('mouseout','menuOut').">";foreach($T
as$R=>$P){$R="$R";$B=adminer()->tableName($P);if($B!=""&&!$P["dependent"])echo'<li><a href="'.h(ME).'select='.url_escape($R).'"'.bold($_GET["select"]==$R||$_GET["edit"]==$R,"select hover")." title='".'Select data'."'>".'select'."</a> ",(support("table")||support("indexes")?'<a href="'.h(ME).'table='.url_escape($R).'"'.bold(in_array($R,array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"])),(is_view($P)?"view":"structure"))." title='".'Show structure'."'>$B</a>":"<span>$B</span>")."\n";}echo"</ul>\n";}function
showVariables(){return
show_variables();}function
showStatus(){return
show_status();}function
processList(){return
process_list();}function
killProcess($s){return
kill_process($s);}}class
Plugins{private
static$append=array('dumpFormat'=>true,'dumpOutput'=>true,'editRowPrint'=>true,'editFunctions'=>true,'config'=>true);var$plugins;var$drivers=array();var$driverFiles=array();var$error='';private$hooks=array();function
__construct($Wh){$_c=SqlDriver::$drivers;$me=" href='https://www.adminer.org/plugins/#use'".target_blank();if($Wh===null){$Wh=array();$Ma="adminer-plugins";if(is_dir($Ma)){foreach(glob("$Ma/*.php")as$m){$ud=SqlDriver::$drivers;$this->includeOnce($m);foreach(array_diff_key(SqlDriver::$drivers,$ud)as$s=>$B)$this->driverFiles[$s]=$m;}}if(file_exists("$Ma.php")){$De=$this->includeOnce("$Ma.php");if(is_array($De)){foreach($De
as$w=>$Th)$Wh[is_object($Th)?get_class($Th):$w]=$Th;}else$this->error
.=sprintf('%s must <a%s>return an array</a>.',"<b>$Ma.php</b>",$me)."<br>";}foreach(get_declared_classes()as$kb){if(!$Wh[$kb]&&(preg_match('~^Adminer\w~i',$kb)||is_subclass_of($kb,'Adminer\Plugin'))){$Ai=new
\ReflectionClass($kb);$_b=$Ai->getConstructor();if($_b&&$_b->getNumberOfRequiredParameters())$this->error
.=sprintf('<a%s>Configure</a> %s in %s.',$me,"<b>$kb</b>","<b>$Ma.php</b>")."<br>";else$Wh[$kb]=new$kb;}}}$Te=array_filter($Wh,function($Th){return!is_object($Th);});if($Te){$this->error
.=sprintf('Every plugin must <a%s>be an object</a>.',$me)."<br>";$Wh=array_diff_key($Wh,$Te);}$this->drivers=array_diff_key(SqlDriver::$drivers,$_c);$this->plugins=$Wh;$ia=new
Adminer;$Wh[]=$ia;$Ai=new
\ReflectionObject($ia);foreach($Ai->getMethods()as$lg){foreach($Wh
as$Th){$B=$lg->getName();if(method_exists($Th,$B))$this->hooks[$B][]=$Th;}}}function
includeOnce($m){return
include_once"./$m";}static
function
checksum($m){$td=str_replace("\r","",file_get_contents($m));$td=preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s','',$td);return
dechex(crc32($td));}function
checksums(){$vd=array_values($this->driverFiles);foreach($this->plugins
as$Th){$Ai=new
\ReflectionObject($Th);$vd[]=$Ai->getFileName();}$J=array();foreach($vd
as$m)$J[basename($m,'.php')]=self::checksum($m);return$J;}static
function
officialChecksums(){return
array('adminer.js'=>'a0599090','backward-keys'=>'ed1ef78f','before-unload'=>'2a613523','config'=>'722eb4af','dark-switcher'=>'3d490dea','database-hide'=>'e304a899','designs'=>'ed7e44e3','dump-alter'=>'896b579e','dump-bz2'=>'f0d0e336','dump-date'=>'adc7f1c7','dump-json'=>'767dd321','dump-xml'=>'4fc3cd60','dump-zip'=>'93817d96','edit-foreign'=>'72ad1562','edit-textarea'=>'a24c3cc','editor-setup'=>'a7dc3a37','editor-views'=>'5c12b185','enum-option'=>'1e24970e','file-upload'=>'10add0e8','foreign-system'=>'ebb4c654','frames'=>'b0e1d11a','highlight-codemirror'=>'c5716555','highlight-monaco'=>'edd1b0af','highlight-prism'=>'267948e5','import-csv'=>'d429c77','login-ip'=>'4d174fea','login-otp'=>'5b5a68af','login-passkey'=>'f69f2f06','login-password-less'=>'e150daac','login-reverse-proxy'=>'24558ea2','login-servers'=>'19c42e45','login-ssl'=>'6ed147bc','login-table'=>'811f8cef','menu-links'=>'c78461b3','remote-color'=>'ddeecc48','row-numbers'=>'eec8698c','select-email'=>'f84fbd2c','select-image'=>'f55c0231','slugify'=>'dec64713','sql-gemini'=>'c60ab309','sql-log'=>'8e435000','table-indexes-structure'=>'a90cc0c9','table-structure'=>'a8458e02','tables-filter'=>'ec2bcd6e','timeout'=>'97321caf','version-github'=>'627cadf9','version-noverify'=>'966937e9','clickhouse'=>'ed04ed31','elastic'=>'af0361c1','firebird'=>'99307ba8','igdb'=>'db772c05','imap'=>'385b5247','mongo'=>'f75dfcf','redis'=>'139ed221','simpledb'=>'d2226cc',);}function
__call($B,array$_h){$xa=array();foreach($_h
as$w=>$X)$xa[]=&$_h[$w];$J=null;foreach($this->hooks[$B]as$Th){$Y=call_user_func_array(array($Th,$B),$xa);if($Y!==null){if(!self::$append[$B])return$Y;$J=$Y+(array)$J;}}return$J;}}abstract
class
Plugin{protected$translations=array();function
description(){return$this->lang('');}function
screenshot(){return"";}protected
function
lang($t,$Lg=null){$xa=func_get_args();$xa[0]=idx($this->translations[LANG],$t)?:$t;return
call_user_func_array('Adminer\lang_format',$xa);}}class
Password{private$password_hash;private$password_matches=null;function
__construct($Lh){$this->password_hash=$Lh;}function
description(){return'Require a password verified by Adminer';}function
credentials(){$F=get_password();return
array(SERVER,$_GET["username"],($this->passwordMatches($F)&&!password_required()?"":$F));}function
login($Hf,$F){if($this->passwordMatches($F))return
true;}protected
function
passwordMatches($F){if($this->password_matches===null)$this->password_matches=(function_exists('password_verify')&&password_verify(strval($F),$this->password_hash));return$this->password_matches;}}Adminer::$instance=(function_exists('adminer_object')?adminer_object():(is_dir("adminer-plugins")||file_exists("adminer-plugins.php")?new
Plugins(null):new
Adminer));SqlDriver::$drivers=array("server"=>"MySQL / MariaDB")+SqlDriver::$drivers;if(!defined('Adminer\DRIVER')){define('Adminer\DRIVER',"server");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){class
Db
extends
\mysqli{static$instance;var$extension="MySQLi",$flavor='';function
__construct(){parent::init();}function
attach(array$N,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);$Xh=$N["port"];$Mc=("$N[host]$Xh$N[socket]"=="");$Mj=adminer()->connectSsl();$rl=($Mj&&($Mj['key']||$Mj['cert']||$Mj['ca']||isset($Mj['verify'])));if($rl)$this->ssl_set($Mj['key'],$Mj['cert'],$Mj['ca'],'','');$J=@$this->real_connect((!$Mc?$N["host"]:ini_get("mysqli.default_host")),(!$Mc||$V!=""?$V:ini_get("mysqli.default_user")),(!$Mc||$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,($Xh!=""?intval($Xh):ini_get("mysqli.default_port")),($Xh!=""?null:$N["socket"]),($rl?($Mj['verify']!==false?MYSQLI_CLIENT_SSL:64):0));$this->options(MYSQLI_OPT_LOCAL_INFILE,0);return($J?'':$this->error);}function
set_charset($bb){if(parent::set_charset($bb))return
true;parent::set_charset('utf8');return$this->query("SET NAMES $bb");}function
next_result(){return
self::more_results()&&parent::next_result();}function
quote($Q){return"'".$this->escape_string($Q)."'";}function
inTransaction(){return
false;}function
begin(){return$this->begin_transaction();}}}elseif(extension_loaded("mysql")&&!((ini_bool("sql.safe_mode")||ini_bool("mysql.allow_local_infile"))&&extension_loaded("pdo_mysql"))){class
Db
extends
SqlDb{private$link;function
attach(array$N,$V,$F){if(ini_bool("mysql.allow_local_infile"))return
sprintf('Disable %s or enable the %s or %s extension.',"'mysql.allow_local_infile'","MySQLi","PDO_MySQL");$Xh="$N[port]$N[socket]";$B=$N["host"].($Xh!=""?":$Xh":"");$this->link=@mysql_connect(($B!=""?$B:ini_get("mysql.default_host")),($B.$V!=""?$V:ini_get("mysql.default_user")),($B.$V.$F!=""?$F:ini_get("mysql.default_password")),true,131072);if(!$this->link)return
mysql_error();$this->server_info=mysql_get_server_info($this->link);return'';}function
set_charset($bb){return
mysql_set_charset($bb,$this->link)||mysql_set_charset('utf8',$this->link);}function
quote($Q){return"'".mysql_real_escape_string($Q,$this->link)."'";}function
select_db($Vb){return
mysql_select_db($Vb,$this->link);}function
query($H,$Yk=false){$I=@($Yk?mysql_unbuffered_query($H,$this->link):mysql_query($H,$this->link));$this->error="";if(!$I){$this->errno=mysql_errno($this->link);$this->error=mysql_error($this->link);return
false;}if($I===true){$this->affected_rows=mysql_affected_rows($this->link);$this->info=mysql_info($this->link);return
true;}return
new
Result($I);}}class
Result{var$num_rows;private$result;private$offset=0;function
__construct($I){$this->result=$I;$this->num_rows=mysql_num_rows($I);}function
fetch_assoc(){return
mysql_fetch_assoc($this->result);}function
fetch_row(){return
mysql_fetch_row($this->result);}function
fetch_field(){$J=mysql_fetch_field($this->result,$this->offset++);$J->orgtable=$J->table;$J->native_type=idx(array("string"=>"varchar","real"=>"double"),$J->type,$J->type);return$J;}}}elseif(extension_loaded("pdo_mysql")){class
Db
extends
PdoDb{var$extension="PDO_MySQL";function
attach(array$N,$V,$F){$C=array(\PDO::MYSQL_ATTR_LOCAL_INFILE=>false);if(isset($_GET["select"]))$C[\PDO::MYSQL_ATTR_MULTI_STATEMENTS]=false;$Mj=adminer()->connectSsl();if($Mj){if($Mj['key'])$C[\PDO::MYSQL_ATTR_SSL_KEY]=$Mj['key'];if($Mj['cert'])$C[\PDO::MYSQL_ATTR_SSL_CERT]=$Mj['cert'];if($Mj['ca'])$C[\PDO::MYSQL_ATTR_SSL_CA]=$Mj['ca'];if(isset($Mj['verify']))$C[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=$Mj['verify'];}$re=$N["host"];$Xh=$N["port"];$Aj=$N["socket"];return$this->dsn("mysql:charset=utf8".($re!=""?";host=$re":'').($Xh!=""?";port=$Xh":($Aj!=""?";unix_socket=$Aj":"")),$V,$F,$C);}function
set_charset($bb){return$this->query("SET NAMES $bb");}function
select_db($Vb){return$this->query("USE ".idf_escape($Vb));}function
query($H,$Yk=false){$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$Yk);return
parent::query($H,$Yk);}}}class
Driver
extends
SqlDriver{static$extensions=array("MySQLi","MySQL","PDO_MySQL");static$jush="sql";static$serverSocket=true;var$unsigned=array("unsigned","zerofill","unsigned zerofill");var$functions=array("char_length","date","from_unixtime","lower","round","floor","ceil","sec_to_time","time_to_sec","upper");var$grouping=array("avg","count","count distinct","group_concat","max","min","sum");var$partitionBy=array("HASH","LINEAR HASH","KEY","LINEAR KEY","RANGE","LIST");function
operators($bk){return
array("=","<",">","<=",">=","!=","LIKE","LIKE %%","REGEXP","IN","FIND_IN_SET","IS NULL","NOT LIKE","NOT REGEXP","NOT IN","IS NOT NULL","SQL");}static
function
connect($N,$V,$F){$e=parent::connect($N,$V,$F);if(is_string($e)){if(function_exists('iconv')&&!is_utf8($e)&&strlen($Xi=iconv("windows-1252","utf-8//IGNORE",$e))>strlen($e))$e=$Xi;return$e;}$e->set_charset(charset($e));$e->query("SET sql_quote_show_create = 1, autocommit = 1");$e->flavor=(preg_match('~MariaDB~',$e->server_info)?'maria':'mysql');add_driver(DRIVER,($e->flavor=='maria'?"MariaDB":"MySQL"));return$e;}function
__construct(Db$e){parent::__construct($e);$this->types=array('Numbers'=>array("tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21),'Date and time'=>array("date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4),'Strings'=>array("char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295),'Lists'=>array("enum"=>65535,"set"=>64),'Binary'=>array("bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295),'Geometry'=>array("geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0),);$this->insertFunctions=array("char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",);$this->editFunctions=array(number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",);if(min_version('5.7.8',10.2,$e))$this->types['Strings']["json"]=4294967295;if(min_version('',10.7,$e)){$this->types['Strings']["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if(min_version('',10.5,$e)){$this->types['Network']["inet6"]=39;if(min_version('','10.10',$e))$this->types['Network']["inet4"]=15;}if(min_version(9,11.7,$e))$this->types['Numbers']["vector"]=16383;if(min_version(5.7,10.2,$e))$this->generated=array("STORED","VIRTUAL");}function
unconvertFunction(array$k){return(preg_match("~binary~",$k["type"])?"<code class='jush-sql'>UNHEX</code>":($k["type"]=="bit"?doc_link(array('sql'=>'bit-value-literals.html'),"<code>b''</code>"):($k["type"]=="vector"?"<code class='jush-sql'>".($this->conn->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."</code>":(preg_match("~geom|point|linestring|polygon~",$k["type"])?"<code class='jush-sql'>GeomFromText</code>":""))));}function
insert($R,array$O){return($O?parent::insert($R,$O):queries("INSERT INTO ".table($R)." ()\nVALUES ()"));}function
insertUpdate($R,array$L,array$ii){$d=array_keys(reset($L));$ei="INSERT INTO ".table($R)." (".implode(", ",$d).") VALUES\n";$zl=array();foreach($d
as$w)$zl[$w]="$w = VALUES($w)";$Vj="\nON DUPLICATE KEY UPDATE ".implode(", ",$zl);$zl=array();$x=0;foreach($L
as$O){$Y="(".implode(", ",$O).")";if($zl&&(strlen($ei)+$x+strlen($Y)+strlen($Vj)>1e6)){if(!queries($ei.implode(",\n",$zl).$Vj))return
false;$zl=array();$x=0;}$zl[]=$Y;$x+=strlen($Y)+2;}return
queries($ei.implode(",\n",$zl).$Vj);}function
slowQuery($H,$_k){if(min_version('5.7.8','10.1.2')){if($this->conn->flavor=='maria')return"SET STATEMENT max_statement_time=$_k FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$A))return"$A[1] /*+ MAX_EXECUTION_TIME(".($_k*1000).") */ $A[2]";}}function
convertColumn($t,array$k){if(preg_match("~binary~",$k["type"]))return"HEX($t)";if($k["type"]=="bit")return"BIN($t + 0)";if($k["type"]=="vector")return($this->conn->flavor=='maria'?"VEC_ToText":"VECTOR_TO_STRING")."($t)";if(preg_match("~geom|point|linestring|polygon~",$k["type"]))return(min_version(8)?"ST_":"")."AsWKT($t)";return"";}function
convertSearch($t,array$X,array$k){return($this->convertColumn($t,$k)?:(preg_match('~'.text_type().'~',$k["type"])&&!preg_match("~^utf8~",$k["collation"])&&preg_match('~[\x80-\xFF]~',$X['val'])?"CONVERT($t USING ".charset($this->conn).")":$t));}function
typeName(\stdClass$k){$B=parent::typeName($k);if($B!=""){$Xk=array("TINY"=>"tinyint","SHORT"=>"smallint","LONG"=>"int","INT24"=>"mediumint","LONGLONG"=>"bigint","NEWDECIMAL"=>"decimal","VAR_STRING"=>"varchar","STRING"=>"char",);return
idx($Xk,$B,strtolower($B));}$Xk=array("decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",);$J=idx($Xk,$k->type,"");return($k->charsetnr==63?str_replace(array("text","varchar","char"),array("blob","varbinary","binary"),$J):$J);}function
quoteBinary($Xi){return"X".q(bin2hex($Xi));}function
warnings(){$I=$this->conn->query("SHOW WARNINGS");if($I&&$I->num_rows){ob_start();print_select_result($I);return
ob_get_clean();}}function
tableHelp($B,$df=false){$Jf=($this->conn->flavor=='maria');if(information_schema(DB))return
strtolower(str_replace("_","-",DB)."-".($Jf?"$B-table/":str_replace("_","-",$B)."-table.html"));if(DB=="sys")return($Jf?"sys-schema/":strtolower("sys-".str_replace("_","-",preg_replace('~^x\$~','',$B)).".html"));if(DB=="mysql")return($Jf?"mysql$B-table/":"system-schema.html");}function
partitionsInfo($R){$Jd="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($R);$I=$this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $Jd ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");$K=($I?$I->fetch_row():null);if(!$K)return
array();$J=array();list($J["partition_by"],$J["partition"],$J["partitions"])=$K;$Hh=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $Jd AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$J["partition_names"]=array_keys($Hh);$J["partition_values"]=array_values($Hh);return$J;}function
checkConstraints($R){$J=parent::checkConstraints($R);return($this->conn->flavor=='maria'?$J:array_map('stripslashes',$J));}function
hasCStyleEscapes(){static$Ua;if($Ua===null){$Kj=get_val("SHOW VARIABLES LIKE 'sql_mode'",1,$this->conn);$Ua=(strpos($Kj,'NO_BACKSLASH_ESCAPES')===false);}return$Ua;}function
lineComment(){return"#|-- ";}function
engines(){$J=array();foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$J[]=$K["Engine"];}return$J;}function
indexAlgorithms(array$bk){return(preg_match('~^(MEMORY|NDB)$~',$bk["Engine"])?array("HASH","BTREE"):array());}}function
idf_escape($t){return"`".str_replace("`","``",$t)."`";}function
table($t){return
idf_escape($t);}function
get_databases($Ad){$J=get_session("dbs");if($J===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$Nj=microtime(true);$J=($Ad?slow_query($H):get_vals($H));if(microtime(true)-$Nj>0.1){restart_session();set_session("dbs",$J);stop_session();}}return$J;}function
limit($H,$Z,$y,$Rg=0,$mj=" "){return" $H$Z".($y?$mj."LIMIT $y".($Rg?" OFFSET $Rg":""):"");}function
limit1($R,$H,$Z,$mj="\n"){return
limit($H,$Z,1,0,$mj);}function
db_collation($h,array$pb){$J=null;$Ib=get_val("SHOW CREATE DATABASE ".idf_escape($h),1);if(preg_match('~ COLLATE ([^ ]+)~',$Ib,$A))$J=$A[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$Ib,$A))$J=$pb[$A[1]][-1];return$J;}function
logged_user(){return
get_val("SELECT CURRENT_USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables(array$g){$J=array();foreach($g
as$h)$J[$h]=count(get_vals("SHOW TABLES IN ".idf_escape($h)));return$J;}function
table_status($B="",$nd=false){$J=array();$H="SELECT ENGINE AS Engine, TABLE_NAME AS Name, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($B!=""?"AND TABLE_NAME = ".q($B):"ORDER BY Name");$Zi=array();foreach(($nd?array():get_rows($H))as$K)$Zi[$K["Name"]]=$K;$hi=null;foreach(get_rows($nd?$H:"SHOW TABLE STATUS".($B!=""?" LIKE ".q(addcslashes($B,"%_\\")):""))as$K){$sh=idx($Zi,$K["Name"]);if($sh){if($K["Comment"]!==$sh["Comment"]&&$K["Comment"]!==$hi)$K["Error"]=$K["Comment"];$hi=$K["Comment"];$K["Comment"]=$sh["Comment"];$K["Engine"]=$sh["Engine"];}if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($B!="")$K["Name"]=$B;$J[$K["Name"]]=$K;}return$J;}function
is_view(array$S){return$S["Engine"]===null;}function
fk_support(array$S){return
preg_match('~InnoDB|IBMDB2I'.(min_version(5.6)?'|NDB':'').'~i',$S["Engine"]);}function
parse_type($Ld){preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$Ld,$A);return
array($A[1],$A[2],ltrim($A[3].$A[4]));}function
fields($R){$Jf=(connection()->flavor=='maria');$J=array();foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($R)." ORDER BY ORDINAL_POSITION")as$K){$k=$K["COLUMN_NAME"];$U=$K["COLUMN_TYPE"];$Qd=$K["GENERATION_EXPRESSION"];$kd=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$kd,$Pd);list($Wk,$x,$fl)=parse_type($U);$i=$K["COLUMN_DEFAULT"];if($i!=""){$cf=preg_match('~text|json~',$Wk);if(!$Jf&&$cf)$i=preg_replace("~^(_\w+)?('.*')$~",'\2',stripslashes($i));if($Jf||$cf){$i=($i=="NULL"?null:preg_replace_callback("~^'(.*)'$~",function($A){return
stripslashes(str_replace("''","'",$A[1]));},$i));}if(!$Jf&&preg_match('~binary~',$Wk)&&preg_match('~^0x(\w*)$~',$i,$A))$i=pack("H*",$A[1]);}$J[$k]=array("field"=>$k,"full_type"=>$U,"type"=>$Wk,"length"=>$x,"unsigned"=>$fl,"default"=>($Pd?($Jf?$Qd:stripslashes($Qd)):$i),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($kd=="auto_increment"),"on_update"=>(preg_match('~\bon update (\w+)~i',$kd,$A)?$A[1]:""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",","$K[PRIVILEGES],where,order")),"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($Pd[1]=="PERSISTENT"?"STORED":$Pd[1]),);}return$J;}function
indexes($R,$f=null){$J=array();foreach(get_rows("SHOW INDEX FROM ".table($R),$f)as$K){$B=$K["Key_name"];$J[$B]["type"]=($B=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$B]["columns"][]=$K["Column_name"];$J[$B]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$B]["descs"][]=null;$J[$B]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($R){static$Ph='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=array();$Jb=get_val("SHOW CREATE TABLE ".table($R),1);if($Jb){preg_match_all("~CONSTRAINT ($Ph) FOREIGN KEY ?\\(((?:$Ph,? ?)+)\\) REFERENCES ($Ph)(?:\\.($Ph))? \\(((?:$Ph,? ?)+)\\)(?: ON DELETE (".driver()->onActions."))?(?: ON UPDATE (".driver()->onActions."))?~",$Jb,$Lf,PREG_SET_ORDER);foreach($Lf
as$A){preg_match_all("~$Ph~",$A[2],$Ej);preg_match_all("~$Ph~",$A[5],$qk);$J[idf_unescape($A[1])]=array("db"=>idf_unescape($A[4]!=""?$A[3]:$A[4]),"table"=>idf_unescape($A[4]!=""?$A[4]:$A[3]),"source"=>array_map('Adminer\idf_unescape',$Ej[0]),"target"=>array_map('Adminer\idf_unescape',$qk[0]),"on_delete"=>($A[6]?:"RESTRICT"),"on_update"=>($A[7]?:"RESTRICT"),);}}return$J;}function
view($B){return
array("select"=>preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU','',get_val("SHOW CREATE VIEW ".table($B),1)));}function
collations(){$J=array();foreach(get_rows("SHOW COLLATION")as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$w=>$X)sort($J[$w]);return$J;}function
information_schema($h,$Zi=""){return($h=="information_schema")||(min_version(5.5)&&$h=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",connection()->error));}function
create_database($h,$ob){return
queries("CREATE DATABASE ".idf_escape($h).($ob?" COLLATE ".q($ob):""));}function
drop_databases(array$g){$J=apply_queries("DROP DATABASE",$g,'Adminer\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($B,$ob){$J=false;if(create_database($B,$ob)){$T=array();$Dl=array();foreach(tables_list()as$R=>$U){if($U=='VIEW')$Dl[]=$R;else$T[]=$R;}$J=(!$T&&!$Dl)||move_tables($T,$Dl,$B);drop_databases($J?array(DB):array());}return$J;}function
auto_increment(){$Da=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$u){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$u["columns"],true)){$Da="";break;}if($u["type"]=="PRIMARY")$Da=" UNIQUE";}}return" AUTO_INCREMENT$Da";}function
alter_table($R,$B,array$l,array$Cd,$tb,$Nc,$ob,$Ca,$Gh){$sa=array();foreach($l
as$k){if($k[1]){$i=$k[1][3];if(preg_match('~ GENERATED~',$i)){$k[1][3]=(connection()->flavor=='maria'?"":$k[1][2]);$k[1][2]=$i;}$sa[]=($R!=""?($k[0]!=""?"CHANGE ".idf_escape($k[0]):"ADD"):" ")." ".implode($k[1]).($R!=""?$k[2]:"");}else$sa[]="DROP ".idf_escape($k[0]);}$sa=array_merge($sa,$Cd);$P=($tb!==null?" COMMENT=".q($tb):"").($Nc?" ENGINE=".q($Nc):"").($ob?" COLLATE ".q($ob):"").($Ca!=""?" AUTO_INCREMENT=$Ca":"");if($Gh){$Hh=array();if($Gh["partition_by"]=='RANGE'||$Gh["partition_by"]=='LIST'){foreach($Gh["partition_names"]as$w=>$X){$Y=$Gh["partition_values"][$w];$Hh[]="\n  PARTITION ".idf_escape($X)." VALUES ".($Gh["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$P
.="\nPARTITION BY $Gh[partition_by]($Gh[partition])";if($Hh)$P
.=" (".implode(",",$Hh)."\n)";elseif($Gh["partitions"])$P
.=" PARTITIONS ".(+$Gh["partitions"]);}elseif($Gh===null)$P
.="\nREMOVE PARTITIONING";if($R=="")return
queries("CREATE TABLE ".table($B)." (\n".implode(",\n",$sa)."\n)$P");if($R!=$B)$sa[]="RENAME TO ".table($B);if($P)$sa[]=ltrim($P);return($sa?queries("ALTER TABLE ".table($R)."\n".implode(",\n",$sa)):true);}function
alter_indexes($R,$sa){$Za=array();foreach($sa
as$X)$Za[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return
queries("ALTER TABLE ".table($R).implode(",",$Za));}function
truncate_tables(array$T){return
apply_queries("TRUNCATE TABLE",$T);}function
drop_views(array$Dl){return
queries("DROP VIEW ".implode(", ",array_map('Adminer\table',$Dl)));}function
drop_tables(array$T){return
queries("DROP TABLE ".implode(", ",array_map('Adminer\table',$T)));}function
move_tables(array$T,array$Dl,$qk){$Fi=array();foreach($T
as$R)$Fi[]=table($R)." TO ".idf_escape($qk).".".table($R);if(!$Fi||queries("RENAME TABLE ".implode(", ",$Fi))){$gc=array();foreach($Dl
as$R)$gc[table($R)]=view($R);connection()->select_db($qk);$h=idf_escape(DB);foreach($gc
as$B=>$Cl){if(!queries("CREATE VIEW $B AS ".str_replace(" $h."," ",$Cl["select"]))||!queries("DROP VIEW $h.$B"))return
false;}return
true;}return
false;}function
copy_tables(array$T,array$Dl,$qk){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($T
as$R){$B=($qk==DB?table("copy_$R"):idf_escape($qk).".".table($R));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $B"))||!queries("CREATE TABLE $B LIKE ".table($R))||!queries("INSERT INTO $B SELECT * FROM ".table($R)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){$Ok=$K["Trigger"];list($Wc,$Ng)=trigger_event($K);if(!queries("CREATE TRIGGER ".($qk==DB?idf_escape("copy_$Ok"):idf_escape($qk).".".idf_escape($Ok))." $K[Timing] $Wc".($Ng!=""?" $Ng":"")." ON $B FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($Dl
as$R){$B=($qk==DB?table("copy_$R"):idf_escape($qk).".".table($R));$Cl=view($R);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $B"))||!queries("CREATE VIEW $B AS $Cl[select]"))return
false;}return
true;}function
trigger_event(array$K){$Yc=explode(",",$K["Event"]);$J=array();foreach(array("DELETE","INSERT","UPDATE")as$Wc){if(in_array($Wc,$Yc))$J[]=$Wc;}$J=implode(" OR ",$J);if(in_array("UPDATE",$Yc)&&min_version('','12.0.1')&&preg_match('~\s(?:BEFORE|AFTER)\s+(.+?)\s+ON\s~is',get_val("SHOW CREATE TRIGGER ".idf_escape($K["Trigger"]),2),$A)&&preg_match('~\bOF\s+(.+)~is',$A[1],$Ng))return
array("$J OF",$Ng[1]);return
array($J,"");}function
trigger($B,$R){if($B=="")return
array();$L=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($B));$J=reset($L);if($J)list($J["Event"],$J["Of"])=trigger_event($J);return($J?:array());}function
triggers($R){$J=array();foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){list($Wc)=trigger_event($K);$J[$K["Trigger"]]=array($K["Timing"],$Wc);}return$J;}function
trigger_options(){return
array("Timing"=>array("BEFORE","AFTER"),"Event"=>(min_version('','12.0.1')?array("INSERT","UPDATE","UPDATE OF","DELETE","INSERT OR UPDATE","INSERT OR UPDATE OF","DELETE OR INSERT","DELETE OR UPDATE","DELETE OR UPDATE OF","DELETE OR INSERT OR UPDATE","DELETE OR INSERT OR UPDATE OF",):array("INSERT","UPDATE","DELETE")),"Type"=>array("FOR EACH ROW"),);}function
routine($B,$U){$L=get_rows("SELECT PARAMETER_NAME, DTD_IDENTIFIER, PARAMETER_MODE, COLLATION_NAME
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($B)."
ORDER BY ORDINAL_POSITION");$l=array();foreach($L
as$K){$Ld=$K["DTD_IDENTIFIER"];list($Wk,$x,$fl)=parse_type($Ld);$l[]=array("field"=>$K["PARAMETER_NAME"],"type"=>$Wk,"length"=>$x,"unsigned"=>$fl,"null"=>true,"full_type"=>$Ld,"inout"=>($U=="FUNCTION"?"":$K["PARAMETER_MODE"]),"collation"=>$K["COLLATION_NAME"],);}$J=connection()->query("SELECT
	ROUTINE_COMMENT comment,
	ROUTINE_DEFINITION definition,
	LOWER(EXTERNAL_LANGUAGE) language,
	IF(DEFINER = CURRENT_USER(), '', DEFINER) definer,
	IF(IS_DETERMINISTIC = 'YES', 'DETERMINISTIC', 'NOT DETERMINISTIC') is_deterministic,
	SQL_DATA_ACCESS data_access,
	CONCAT('SQL SECURITY ', SECURITY_TYPE) security
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND ROUTINE_NAME = ".q($B))->fetch_assoc();if(!$J)return
array();$J['options']=array("DEFINER"=>$J['definer'],"DETERMINISTIC"=>$J['is_deterministic'],"SQL_DATA_ACCESS"=>$J['data_access'],"SQL_SECURITY"=>$J['security'],"COMMENT"=>$J['comment'],);if($l&&$l[0]['field']=='')$J['returns']=array_shift($l);$J['fields']=$l;return$J;}function
routines(){return
get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");}function
routine_languages(){return(min_version(9,99)?array("sql"=>"sql","javascript"=>"js"):array());}function
routine_options($Qi){return
array("DEFINER"=>array(),"DETERMINISTIC"=>array("NOT DETERMINISTIC","DETERMINISTIC"),"SQL_DATA_ACCESS"=>array("CONTAINS SQL","NO SQL","READS SQL DATA","MODIFIES SQL DATA"),"SQL_SECURITY"=>array("SQL SECURITY DEFINER","SQL SECURITY INVOKER"),"COMMENT"=>array(),);}function
routine_id($B,array$K){return
idf_escape($B);}function
last_id($I){return
get_val("SELECT LAST_INSERT_ID()");}function
explain(Db$e,$H){return$e->query("EXPLAIN ".(min_version(5.7)?"":"PARTITIONS ").$H);}function
found_rows(array$S,array$Z){return($Z||$S["Engine"]!="InnoDB"?null:$S["Rows"]);}function
create_sql($R,$Ca,$Tj){$J=get_val("SHOW CREATE TABLE ".table($R),1);if(!$Ca)$J=preg_replace('~(\n\)[^\n]*?) AUTO_INCREMENT=\d+~','\1',$J);return$J;}function
truncate_sql($R){return"TRUNCATE ".table($R);}function
use_sql($Vb,$Tj=""){$B=idf_escape($Vb);$J="";if(preg_match('~CREATE~',$Tj)&&($Ib=get_val("SHOW CREATE DATABASE $B",1))){set_utf8mb4($Ib);if($Tj=="DROP+CREATE")$J="DROP DATABASE IF EXISTS $B;\n";$J
.="$Ib;\n";}return$J."USE $B";}function
trigger_sql($R){$J="";foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")),null,"-- ")as$K){list($K["Event"],$K["Of"])=trigger_event($K);$J
.="\n".create_trigger(" ON ".table($K["Table"]),$K+array("Type"=>"FOR EACH ROW")).";\n";}return$J;}function
show_variables(){return
get_rows("SHOW VARIABLES");}function
show_status(){return
get_rows("SHOW STATUS");}function
process_list(){return
get_rows("SHOW FULL PROCESSLIST");}function
convert_field(array$k){return
driver()->convertColumn(idf_escape($k["field"]),$k);}function
unconvert_field(array$k,$J){if(preg_match("~binary~",$k["type"]))$J="UNHEX($J)";if($k["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($k["type"]=="vector")$J=(connection()->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geom|point|linestring|polygon~",$k["type"])){$ei=(min_version(8)?"ST_":"");$J=$ei."GeomFromText($J, $ei"."SRID($k[field]))";}return$J;}function
support($od){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(min_version(8)?'|descidx':'').(min_version('8.0.16','10.2.1')?'|check':'').(min_version(8,99)?'|fast_status':'').')$~',$od);}function
kill_process($s){return
queries("KILL ".number($s));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return
get_val("SELECT @@max_connections");}function
types($jd=false){return
array();}function
type_values($s){return"";}function
type_definition($s){return
array("kind"=>"","definition"=>"");}function
schemas(){return
array();}function
get_schema(){return"";}function
set_schema($Zi,$f=null){return
true;}}define('Adminer\JUSH',Driver::$jush);define('Adminer\SERVER',"".$_GET[DRIVER]);define('Adminer\DB',"$_GET[db]");define('Adminer\ME',preg_replace('~\?.*~','',relative_uri()).'?'.(sid()?SID.'&':'').($_GET["ext"]?"ext=".url_escape($_GET["ext"]).'&':'').(isset($_GET[DRIVER])?DRIVER."=".url_escape(SERVER).'&':'').(isset($_GET["username"])?"username=".url_escape($_GET["username"]).'&':'').(isset($_GET["db"])?'db='.url_escape(DB).'&'.(isset($_GET["ns"])?"ns=".url_escape($_GET["ns"])."&":""):''));if(isset($_GET["manifest"])){header("Content-Type: application/manifest+json; charset=utf-8");header("Cache-Control: no-cache");echo
json_encode(adminer()->manifest(),64|256);exit;}function
page_header($Bk,$j="",$Sa=array(),$Ck="",$Ig=false){if($Ig){header("HTTP/1.1 404 Not Found");$j=($j?:'Not found.');}page_headers();if(is_ajax()&&$j){page_messages($j);exit;}if(!ob_get_level())ob_start('ob_gzhandler',4096);$Dk=$Bk.($Ck!=""?": $Ck":"");$Ek=strip_tags($Dk.(SERVER!=""&&SERVER!="localhost"?h(" - ".SERVER):"")." - ".adminer()->name());echo'<!DOCTYPE html>
<html lang=\'en\' dir=\'ltr\' class=\'ltr nojs\'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>',$Ek,'</title>
<link rel="stylesheet" href="',h(preg_replace("~\\?.*~","",ME)."?file=default.css&version=6.1.0+f3e574b0"),'">
';$Nb=adminer()->css();if(is_int(key($Nb)))$Nb=array_fill_keys($Nb,'light');$ee=in_array('light',$Nb)||in_array('',$Nb);$ce=in_array('dark',$Nb)||in_array('',$Nb);$Rb=($ee?($ce?null:false):($ce?:null));$ag=" media='(prefers-color-scheme: dark)'";if($Rb!==false)echo"<link rel='stylesheet'".($Rb?"":$ag)." href='".h(preg_replace("~\\?.*~","",ME)."?file=dark.css&version=6.1.0+f3e574b0")."'>\n";echo"<meta name='color-scheme' content='".($Rb===null?"light dark":($Rb?"dark":"light"))."'>\n",script_src(preg_replace("~\\?.*~","",ME)."?file=functions.js&version=6.1.0+f3e574b0");if(adminer()->head($Rb))echo"<link rel='icon' href='data:image/gif;base64,"."R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n","<link rel='apple-touch-icon' href='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.0+f3e574b0")."'>\n";if(adminer()->manifest())echo"<link rel='manifest' href='".h(preg_replace('~\?.*~','',ME)."?manifest=")."' crossorigin='use-credentials'>\n";foreach($Nb
as$ml=>$pg){$b=($pg=='dark'&&!$Rb?$ag:($pg=='light'&&$ce?" media='(prefers-color-scheme: light)'":""));echo"<link rel='stylesheet'$b href='".h($ml)."'>\n";}echo"\n<body class='";adminer()->bodyClass();echo"'>\n",script((isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"onload = partial(verifyVersion, '".VERSION."');\n")."
const offlineMessage = '".js_escape('You are offline.')."';
const numberFormat = '".js_escape('#,##0')."';
const numberDigits = '".js_escape('0123456789')."';
const urlSeparators = '".js_escape(ini_get("arg_separator.input"))."';"),"<div id='help' class='jush-".JUSH." jsonly hidden'".on('mouseover','helpKeep').on('mouseout','helpMouseout')."></div>\n","<div id='content'>\n","<span id='menuopen' class='jsonly'".on('click','menuToggle')."><button title='".'Menu'."' class='icon icon-move' aria-expanded='false'></button></span>\n";if($Sa!==null){$z=substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1);echo'<p id="breadcrumb"><a href="'.h($z?:".").'">'.get_driver(DRIVER).'</a> » ';$z=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);$N=adminer()->serverName(SERVER);$N=($N!=""?$N:'Server');if($Sa===false)echo"$N\n";else{echo"<a href='".h($z.(DB!=""&&support("single_db")?"&db=":""))."' accesskey='1' title='Alt+Shift+1'>$N</a> » ";$fj="";if(is_string($Sa)){$fj=$Sa;$Sa=array();}if($_GET["ns"]!=""||(DB!=""&&is_array($Sa))){$Xb="$z&db=".url_escape(DB).(support("scheme")?"&ns=":"").(support("single_table")?"&select=":"");echo'<a href="'.h($Xb.($_GET["ns"]==""?$fj:"")).'">'.h(DB).'</a> » ';}if(is_array($Sa)){if($_GET["ns"]!="")echo'<a href="'.h(substr(ME,0,-1).$fj).'">'.h($_GET["ns"]).'</a> » ';foreach($Sa
as$w=>$X){$ic=(is_array($X)?$X[1]:h($X));if($ic!="")echo"<a href='".h(ME."$w=").url_escape(is_array($X)?$X[0]:$X)."'>$ic</a> » ";}}echo"$Bk\n";}}echo"<h2>$Dk</h2>\n","<div id='ajaxstatus' role='status' class='jsonly'></div>\n";restart_session();page_messages($j);adminer()->serviceWorker();$g=&get_session("dbs");if(DB!=""&&$g&&!in_array(DB,$g,true))$g=null;stop_session();define('Adminer\PAGE_HEADER',1);ob_flush();flush();if($Ig){page_footer($Ig===true?"":$Ig);exit;}}function
service_worker(){$Di=has_passwords();$lb=($Di?"navigator.serviceWorker.register('".js_escape(preg_replace('~\?.*~','',ME)."?file=worker.js&version=6.1.0+f3e574b0")."', {scope: location.pathname}).catch(() => {});":"navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));");echo
script("if (navigator.serviceWorker) {\n\t$lb\n}");}function
has_passwords(){foreach((array)$_SESSION["pwds"]as$sj){foreach($sj
as$ul){foreach($ul
as$F){if($F!==null)return
true;}}}return
false;}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-Frame-Options: deny");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");foreach(adminer()->csp(csp())as$Mb){$ie=array();foreach($Mb
as$w=>$X)$ie[]="$w $X";header("Content-Security-Policy: ".implode("; ",$ie));}adminer()->headers();}function
csp(){return
array(array("script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://www.adminer.org","frame-src"=>"https://www.adminer.org","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",),);}function
design_checksums(){$sl=array();foreach(array_keys(adminer()->css())as$ml)$sl[preg_replace('~\?.*~','',$ml)]=true;$J=array();foreach(array("adminer.css","adminer-dark.css")as$m){if($sl[$m]&&file_exists($m)){preg_match('~^/\* Adminer design ([-\w]+) \*/~',file_get_contents($m),$A);$J[$m]=array((string)$A[1],Plugins::checksum($m));}}return$J;}function
official_design_checksums(){return
array('adminer-border/adminer.css'=>'ec757f3e','adminer-dark/adminer-dark.css'=>'a26bcd7b','brade/adminer.css'=>'be4161f0','bueltge/adminer.css'=>'1a8f00b4','cpanel/adminer.css'=>'59ce604e','dracula/adminer-dark.css'=>'cfaf61dd','esterka/adminer.css'=>'1f805f36','flat/adminer.css'=>'49a61af9','galkaev/adminer-dark.css'=>'16c46f94','haeckel/adminer.css'=>'147a3565','hever/adminer.css'=>'ef0e1948','konya/adminer.css'=>'2b409696','lavender-light/adminer.css'=>'bf03f5d7','lucas-sandery/adminer.css'=>'6596353','mancave/adminer-dark.css'=>'e1ac813d','mvt/adminer.css'=>'ebd3afdc','nette/adminer.css'=>'5ab360e7','ng9/adminer.css'=>'488583cf','nicu/adminer.css'=>'216f097b','pappu687/adminer.css'=>'b58d128c','paranoiq/adminer.css'=>'64d27e5','pepa-linha/adminer.css'=>'baf25f0','pokorny/adminer.css'=>'ee9eea6d','price/adminer.css'=>'81be9a85','rmsoft/adminer.css'=>'6cd4a237','rmsoft_blue-dark/adminer.css'=>'32102a8','rmsoft_blue/adminer.css'=>'7d8d5b18','win98/adminer.css'=>'e82d63c3',);}function
version_iframe(){return(isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"<noscript><iframe sandbox src='https://www.adminer.org/version/?current=".VERSION."&amp;noscript=1'></iframe></noscript>");}function
get_nonce(){static$Hg;if(!$Hg)$Hg=base64_encode(rand_string());return$Hg;}function
page_messages($j){$ll=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$hg=idx($_SESSION["messages"],$ll);if($hg){echo"<div class='message'>".implode("</div>\n<div class='message'>",$hg)."</div>".script("messagesPrint();");unset($_SESSION["messages"][$ll]);}if($j)echo"<div class='error'>$j</div>\n";if(adminer()->error)echo"<div class='error'>".adminer()->error."</div>\n";}function
page_footer($og=""){echo"</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";adminer()->navigation($og);echo"</div>\n";if($og!="auth")echo'<form action="" method="post">
<p class="logout">
<span title="Username">',h($_GET["username"])."\n",'</span>
<input type=\'submit\' name=\'logout\' value=\'Logout\' id=\'logout\'>
',input_token(),'</form>
';echo"</div>\n\n",script("setupSubmitHighlight(document);");}function
int32($wg){while($wg>=2147483648)$wg-=4294967296;while($wg<=-2147483649)$wg+=4294967296;return(int)$wg;}function
long2str(array$W,$Fl){$Xi='';foreach($W
as$X)$Xi
.=pack('V',$X);if($Fl)return
substr($Xi,0,end($W));return$Xi;}function
str2long($Xi,$Fl){$W=array_values(unpack('V*',str_pad($Xi,4*ceil(strlen($Xi)/4),"\0")));if($Fl)$W[]=strlen($Xi);return$W;}function
xxtea_mx($Pl,$Ol,$Wj,$if){return
int32((($Pl>>5&0x7FFFFFF)^$Ol<<2)+(($Ol>>3&0x1FFFFFFF)^$Pl<<4))^int32(($Wj^$Ol)+($if^$Pl));}function
encrypt_string($Qj,$w){if($Qj=="")return"";$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($Qj,true);$wg=count($W)-1;$Pl=$W[$wg];$Ol=$W[0];$qi=floor(6+52/($wg+1));$Wj=0;while($qi-->0){$Wj=int32($Wj+0x9E3779B9);$Ec=$Wj>>2&3;for($wh=0;$wh<$wg;$wh++){$Ol=$W[$wh+1];$vg=xxtea_mx($Pl,$Ol,$Wj,$w[$wh&3^$Ec]);$Pl=int32($W[$wh]+$vg);$W[$wh]=$Pl;}$Ol=$W[0];$vg=xxtea_mx($Pl,$Ol,$Wj,$w[$wh&3^$Ec]);$Pl=int32($W[$wg]+$vg);$W[$wg]=$Pl;}return
long2str($W,false);}function
decrypt_string($Qj,$w){if($Qj=="")return"";if(!$w)return
false;$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($Qj,false);$wg=count($W)-1;$Pl=$W[$wg];$Ol=$W[0];$qi=floor(6+52/($wg+1));$Wj=int32($qi*0x9E3779B9);while($Wj){$Ec=$Wj>>2&3;for($wh=$wg;$wh>0;$wh--){$Pl=$W[$wh-1];$vg=xxtea_mx($Pl,$Ol,$Wj,$w[$wh&3^$Ec]);$Ol=int32($W[$wh]-$vg);$W[$wh]=$Ol;}$Pl=$W[$wg];$vg=xxtea_mx($Pl,$Ol,$Wj,$w[$wh&3^$Ec]);$Ol=int32($W[0]-$vg);$W[0]=$Ol;$Wj=int32($Wj-0x9E3779B9);}return
long2str($W,true);}$Rh=array();if($_COOKIE["adminer_permanent"]){foreach(explode(" ",$_COOKIE["adminer_permanent"])as$X){list($w)=explode(":",$X);$Rh[$w]=$X;}}function
add_invalid_login(){$Ka=get_temp_dir()."/adminer-invalid";foreach(glob("$Ka*")?:array($Ka)as$m){$o=file_open_lock($m);if($o)break;}if(!$o)$o=file_open_lock("$Ka-".rand_string());if(!$o)return;$Ve=json_decode(stream_get_contents($o),true);$zk=time();if($Ve){foreach($Ve
as$We=>$X){if($X[0]<$zk)unset($Ve[$We]);}}$Te=&$Ve[adminer()->bruteForceKey()];if(!$Te)$Te=array($zk+30*60,0);$Te[1]++;file_write_unlock($o,json_encode($Ve));}function
check_invalid_login(array&$Rh){$Ve=array();foreach(glob(get_temp_dir()."/adminer-invalid*")as$m){$o=file_open_lock($m);if($o){$Ve=json_decode(stream_get_contents($o),true);file_unlock($o);break;}}$w=adminer()->bruteForceKey();$Te=idx($Ve,$w,array());$Gg=($Te[1]>29?$Te[0]-time():0);if($Gg>0){$j=lang_format(array('Too many unsuccessful logins, try again in %d minute.','Too many unsuccessful logins, try again in %d minutes.'),ceil($Gg/60));if($_SERVER["HTTP_X_FORWARDED_FOR"]!=""&&$w==$_SERVER["REMOTE_ADDR"])$j
.='<br>'.sprintf('Use the %s <a%s>plugin</a> if Adminer runs behind a reverse proxy.','<b>login-reverse-proxy</b>'," href='https://www.adminer.org/plugins/?version=".VERSION."'".target_blank());auth_error($j,$Rh,false);}}function
password_required(){static$J;if($J===null){$J=(bool)get_session("password_required");if(!$J){$Lb=adminer()->credentials();$J=!is_object(Driver::connect($Lb[0],$Lb[1],""));if($J)set_session("password_required",true);}}return$J;}function
require_password_link($F){$sg="<a href='https://www.adminer.org/password/'".target_blank().">".'More options'."</a>";if(!function_exists('password_hash'))return" $sg";$Uh=($F!==null?$F:base64_encode(substr(pack("H*",rand_string()),0,12)));$he=password_hash($Uh,PASSWORD_DEFAULT);$m="adminer-plugins.php";$dd=file_exists("adminer-plugins.php");if($dd)$Re=($F!==null?sprintf('Add this line to %s to require the entered password:',"<b>$m</b>"):sprintf('Add this line to %s to require the password %s:',"<b>$m</b>","<b>$Uh</b>"));else{$m="<button name='password_less' value='".h($he)."' class='link'>$m</button>";$Re=($F!==null?sprintf('Save %s next to Adminer to require the entered password:',$m):sprintf('Save %s next to Adminer to require the password %s:',$m,"<b>$Uh</b>"));}$Bf="\t<a>new</a> Adminer\\Password(<span class='jush-apo'>'".h($he)."'</span>),";$J="<p>$Re
<pre><code class='jush'>".($dd?$Bf:"&lt;?php\n<a>return</a> <a>array</a>(\n$Bf\n);")."</code></pre>
<p>$sg
";return" <a href='#password-less' class='toggle'>".'Require a password.'."</a>
<div id='password-less' class='hidden'>".($dd?$J:"<form action='' method='post'>\n".$J.input_token()."</form>")."</div>";}if(preg_match('~^[-\w$./]+$~',$_POST["password_less"])&&verify_token()){header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=adminer-plugins.php");echo"<?php\nreturn array(\n\tnew Adminer\\Password('$_POST[password_less]'),\n);\n";exit;}$Ba=$_POST["auth"];if($Ba&&(!adminer()->verifyLoginToken()||verify_token())){session_regenerate_id();$Al=$Ba["driver"];$N=$Ba["server"];$V=$Ba["username"];$F=(string)$Ba["password"];$h=$Ba["db"];set_password($Al,$N,$V,$F);$_SESSION["db"][$Al][$N][$V][$h]=true;if($Ba["permanent"]){$w=implode("-",array_map('base64_encode',array($Al,$N,$V,$h)));$ki=adminer()->permanentLogin(true);$Rh[$w]="$w:".base64_encode($ki?encrypt_string($F,$ki):"");cookie("adminer_permanent",implode(" ",$Rh));}if(!array_diff(array_keys($_POST),array("auth","token"))||$Al!=DRIVER||$N!=SERVER||$V!==$_GET["username"]||$h!=DB)redirect(auth_url($Al,$N,$V,$h));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){foreach(array("pwds","db","dbs","queries")as$w)set_session($w,null);unset_permanent($Rh);redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1),'Logout successful.'.' '.'Thanks for using Adminer. Consider <a href="https://www.adminer.org/en/donation/">donating</a>.');}elseif($Rh&&!$_SESSION["pwds"]){session_regenerate_id();$ki=adminer()->permanentLogin();foreach($Rh
as$w=>$X){list(,$jb)=explode(":",$X);list($Al,$N,$V,$h)=array_map('base64_decode',explode("-",$w));set_password($Al,$N,$V,decrypt_string(base64_decode($jb),$ki));$_SESSION["db"][$Al][$N][$V][$h]=true;}}function
unset_permanent(array&$Rh){foreach($Rh
as$w=>$X){list($Al,$N,$V,$h)=array_map('base64_decode',explode("-",$w));if($Al==DRIVER&&$N==SERVER&&$V==$_GET["username"]&&$h==DB)unset($Rh[$w]);}cookie("adminer_permanent",implode(" ",$Rh));}function
auth_error($j,array&$Rh,$Ue=true){$tj=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$tj]||$_GET[$tj])&&!$_SESSION["token"])$j='Session expired. Please log in again.';elseif($Ue&&($F=get_password())!==null){restart_session();add_invalid_login();if($F===false)$j
.=($j?'<br>':'').sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.',target_blank(),'<code>permanentLogin()</code>');set_password(DRIVER,SERVER,$_GET["username"],null);unset_permanent($Rh);}}if(!$_COOKIE[$tj]&&$_GET[$tj]&&ini_bool("session.use_only_cookies"))$j='Session support must be enabled.';$_h=session_get_cookie_params();cookie("adminer_key",($_COOKIE["adminer_key"]?:rand_string()),$_h["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header('Login',$j,null);echo"<form action='' method='post'>\n","<div>";if(hidden_fields($_POST,array("auth","token")))echo"<p class='message'>".'The action will be performed after successful login with the same credentials.'."\n";echo
input_token(),"</div>\n";adminer()->loginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!class_exists('Adminer\Db')){unset($_SESSION["pwds"][DRIVER]);unset_permanent($Rh);page_header('No extension',sprintf('None of the supported PHP extensions (%s) are available.',implode(", ",Driver::$extensions)),false);page_footer("auth");exit;}$e='';if(isset($_GET["username"])&&is_string(get_password())){check_invalid_login($Rh);$Lb=adminer()->credentials();$e=Driver::connect($Lb[0],$Lb[1],$Lb[2]);if(is_object($e)){Db::$instance=$e;Driver::$instance=new
Driver($e);if($e->flavor)save_settings(array("vendor-".DRIVER."-".SERVER=>get_driver(DRIVER)));}}$Hf=null;if(!is_object($e)||($Hf=adminer()->login($_GET["username"],get_password()))!==true){$j=(is_string($e)?nl_br(h($e)):(is_string($Hf)?$Hf:'Invalid credentials.')).(preg_match('~^ | $~',get_password())?'<br>'.'There is a space in the entered password, which might be the cause.':'');auth_error($j,$Rh);}if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){page_header('Logout','Invalid CSRF token. Submit the form again.');page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($Ba&&$_POST["token"])$_POST["token"]=get_token();$j='';if($_POST){if(!verify_token()){header("HTTP/1.1 403 Forbidden");$j='Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.';}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){header("HTTP/1.1 413 Content Too Large");$j=sprintf('The POST data is too large. Reduce the data or increase the %s configuration directive.',"<b>post_max_size</b>");if(isset($_GET["sql"]))$j
.=' '.'You can upload a large SQL file via FTP and import it from the server.';}function
print_select_result($I,$f=null,array$mh=array(),&$y=0,&$Fc=false){$Df=array();$v=array();$d=array();$T=array();$ii=array();$Hc=array();$Xk=array();$J=array();$qg=$Fc;$Fc=false;for($r=0;(!$y||$r<$y)&&($K=$I->fetch_row());$r++){if(!$r){echo"<div class='scrollable'>\n","<table class='nowrap odds'".($qg?on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown'):"").">\n","<thead><tr>";for($ff=0;$ff<count($K);$ff++){$k=$I->fetch_field();$B=$k->name;$R=(isset($k->table)?$k->table:"");$lh=(isset($k->orgtable)?$k->orgtable:"");$kh=(isset($k->orgname)?$k->orgname:$B);$Wk=driver()->typeName($k);if($mh&&JUSH=="sql")$Df[$ff]=($B=="table"?"table=":($B=="possible_keys"?"indexes=":null));elseif($lh!=""){$oa=($R!=""?$R:$lh);if($R!="")$J[$R]=$lh;if(!isset($v[$oa])){if(!isset($ii[$lh])){$ii[$lh]=array();foreach(indexes($lh,$f)as$u){if($u["type"]=="PRIMARY"){$ii[$lh]=array_flip($u["columns"]);break;}}}$T[$oa]=$lh;$v[$oa]=$ii[$lh];$d[$oa]=$ii[$lh];}if(isset($d[$oa][$kh])){unset($d[$oa][$kh]);$v[$oa][$kh]=$ff;$Df[$ff]=$oa;}elseif($qg&&isset($k->orgname)&&$k->db==DB&&!is_blob(array("type"=>$Wk)))$Hc[$ff]=array($oa,$kh,preg_match('~text|json|lob~',$Wk));}$Xk[$ff]=$Wk;echo"<th title='".h(trim(($lh!=""?"$lh.$kh":($k->name!=$kh?$kh:""))." ".$Wk))."'>".h($B).($mh?doc_link(array('sql'=>"explain-output.html#explain_".strtolower($B),'mariadb'=>"explain/#the-columns-in-explain-select",)):"");}foreach($Hc
as$ff=>$Xa){if($d[$Xa[0]])unset($Hc[$ff]);}echo"<tbody>\n";}$xe=array();foreach($v
as$oa=>$u){if($u&&!$d[$oa]){$t="";foreach($u
as$mb=>$ff){if($K[$ff]===null){$t=null;break;}$t
.="&where[".url_escape(bracket_escape($mb))."]=".url_escape($K[$ff]);}$xe[$oa]=$t;}}echo"<tr>";foreach($K
as$w=>$X){$z="";if(isset($Df[$w])){if($mh&&JUSH=="sql"){$R=$K[array_search("table=",$Df)];$z=ME.$Df[$w].url_escape($mh[$R]!=""?$mh[$R]:$R);}elseif(idx($xe,$Df[$w])!==null)$z=ME."edit=".url_escape($T[$Df[$w]]).$xe[$Df[$w]];}$b="";$Xa=idx($Hc,$w);if($Xa&&idx($xe,$Xa[0])!==null&&is_utf8($X)){$Fc=true;$b=" data-name='".h("val[".bracket_escape($T[$Xa[0]])."][".bracket_escape(substr($xe[$Xa[0]],1))."][".bracket_escape($Xa[1])."]")."' data-text='".($Xa[2]?1:0)."'";}$X=select_value($X,$z,array('type'=>(preg_match('~binary~',$Xk[$w])?'blob':$Xk[$w])),null);echo"<td".(preg_match(number_type(),$Xk[$w])?" class='number'":"")."$b>$X";}}$y=$r;echo($r?"</table>\n</div>":"<p class='message'>".'No rows.')."\n";return$J;}function
textarea($B,$Y,$L=10,$qb=80,$hf=JUSH){echo"<textarea name='".h($B)."' rows='$L' cols='$qb' class='sqlarea jush-".h($hf)."' spellcheck='false' wrap='off'>";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($b,array$C,$Y="",$Sh=""){if($C&&$Y!=""&&!isset($C[$Y]))$C=array($Y=>$Y)+$C;$pk=($C?"select":"input");return"<$pk$b".($C?"><option value=''>$Sh".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$Sh'>");}function
json_row($w,$X=null,$Vc=true){static$zd=true;if($zd)echo"{";if($w!=""){echo($zd?"":",")."\n\t\"".addcslashes($w,"\r\n\t\"\\/").'": '.($X!==null?($Vc?'"'.addcslashes($X,"\r\n\"\\/").'"':$X):'null');$zd=false;}else{echo"\n}\n";$zd=true;}}function
flat_collations(){$pb=collations();return(is_array(reset($pb))?call_user_func_array('array_merge',array_values($pb)):$pb);}function
edit_type($w,array$k,array$pb,array$Ed=array(),array$ld=array()){$U=(string)$k["type"];echo"<td><select name='".h($w)."[type]' class='type' aria-labelledby='label-type'".on_help_value().">";if($U&&!array_key_exists($U,driver()->types())&&!isset($Ed[$U])&&!in_array($U,$ld))$ld[]=$U;$Rj=driver()->structuredTypes();if($Ed)$Rj['Foreign keys']=$Ed;echo
optionlist(array_merge($ld,$Rj),$U),"</select><td>","<input name='".h($w)."[length]' value='".h($k["length"])."' size='3'".(!$k["length"]&&preg_match('~var(char|binary)$~',$U)?" class='required'":"")." aria-labelledby='label-length'>","<td class='options'>",($pb?"<input list='collations' name='".h($w)."[collation]'".option_types($U,'('.text_type().')$')." value='".h($k["collation"])."' placeholder='(".'collation'.")'>":''),(driver()->unsigned?"<select name='".h($w)."[unsigned]'".option_types($U,'^$|'.number_type()).'><option>'.optionlist(driver()->unsigned,$k["unsigned"]).'</select>':''),(isset($k['on_update'])?"<select name='".h($w)."[on_update]'".option_types($U,'timestamp|datetime').'>'.optionlist(array(""=>"(".'ON UPDATE'.")","CURRENT_TIMESTAMP"),(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"CURRENT_TIMESTAMP":$k["on_update"])).'</select>':''),($Ed?"<select name='".h($w)."[on_delete]'".option_types($U,'`')."><option value=''>(".'ON DELETE'.")".optionlist(explode("|",driver()->onActions),$k["on_delete"])."</select> ":" ");}function
option_types($U,$Xk){return" data-types='".h($Xk)."'".(preg_match("~$Xk~",$U)?"":" class='hidden'");}function
process_length($x){if(JUSH=="mssql"&&preg_match('~^\s*\(?\s*max\s*\)?\s*$~i',$x))return"(max)";$Qc=driver()->enumLength;return(preg_match("~^\\s*\\(?\\s*$Qc(?:\\s*,\\s*$Qc)*+\\s*\\)?\\s*\$~",$x)&&preg_match_all("~$Qc~",$x,$Lf)?"(".implode(",",$Lf[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$x)));}function
process_in($X){$Qc=driver()->enumLength;if(preg_match("~^\\s*\\(?\\s*$Qc(?:\\s*,\\s*$Qc)*+\\s*\\)?\\s*\$~",$X)&&preg_match_all("~$Qc~",$X,$Lf))return"(".implode(", ",$Lf[0]).")";$J=array();foreach(explode(",",$X)as$ef)$J[]=q(trim($ef));return"(".implode(", ",$J).")";}function
process_type(array$k,$nb="COLLATE"){return" $k[type]".process_length($k["length"]).(preg_match(number_type(),$k["type"])&&in_array($k["unsigned"],driver()->unsigned)?" $k[unsigned]":"").(preg_match('~'.text_type().'~',$k["type"])&&$k["collation"]?" $nb ".(JUSH=="mssql"?$k["collation"]:q($k["collation"])):"");}function
process_field(array$k,array$Uk){if($k["on_update"])$k["on_update"]=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$k["on_update"]);return
array(idf_escape(trim($k["field"])),process_type($Uk),($k["null"]?" NULL":" NOT NULL"),default_value($k),(preg_match('~timestamp|datetime~',$k["type"])&&$k["on_update"]?" ON UPDATE $k[on_update]":""),(support("comment")&&$k["comment"]!=""?" COMMENT ".q($k["comment"]):""),($k["auto_increment"]?auto_increment():null),);}function
default_value(array$k){if($k["default"]===null)return"";$i=str_replace("\r","",$k["default"]);$Pd=$k["generated"];return(in_array($Pd,driver()->generated)?(JUSH=="mssql"?" AS ($i)".($Pd=="VIRTUAL"?"":" $Pd"):" GENERATED ALWAYS AS ($i) $Pd"):(preg_match('~^GENERATED ~i',$i)?" $i":" DEFAULT ".(preg_match('~char|binary|text|json|enum|set|String~',$k["type"])||preg_match('~^(?![a-z])~i',$i)?(JUSH=="sql"&&preg_match('~text|json~',$k["type"])?"(".q($i).")":q($i)):str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",(JUSH=="sqlite"?"($i)":$i)))));}function
edit_fields(array$l,array$pb,$U="TABLE",array$Ed=array()){$l=array_values($l);$cc=(($_POST?$_POST["defaults"]:get_setting("defaults"))?"":" class='hidden'");$ub=(($_POST?$_POST["comments"]:get_setting("comments"))?"":" class='hidden'");echo"<thead><tr>\n",($U=="PROCEDURE"?"<td>":""),"<th id='label-name'>".($U=="TABLE"?'Column name':'Parameter name'),"<th id='label-type'>".'Type'."<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>".script("qs('#enum-edit').onblur = editingLengthBlur;"),"<th id='label-length'>".'Length',"<th>".'Options';if($U=="TABLE")echo"<th id='label-null'>NULL\n","<th><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='".'Auto Increment'."'>AI</abbr>",doc_link(array('sql'=>"example-auto-increment.html",'mariadb'=>"auto_increment/",)),"<th id='label-default'$cc>".'Default value',(support("comment")?"<th id='label-comment'$ub>".'Comment':"");$sf=!support("move_col");echo"<td>".icon("plus","add[".($sf?count($l):0)."]","+",'Add next',($sf?on('click','editingAddLastRow'):"")),"<tbody".on('click','editingClick').on('input','editingInput').on('keydown','editingKeydown').">\n";foreach($l
as$r=>$k){$r++;$nh=$k[($_POST?"orig":"field")];$pc=(isset($_POST["add"][$r-1])||(isset($k["field"])&&!idx($_POST["drop_col"],$r)))&&(support("drop_col")||$nh=="");echo"<tr".($pc?"":" hidden").">\n",($U=="PROCEDURE"?"<td>".html_select("fields[$r][inout]",explode("|",driver()->inout),$k["inout"]):"")."<th>",(support("move_col")?icon("move","","↕",'Move')." ":"");if($pc)echo"<input name='fields[$r][field]' value='".h($k["field"])."' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'".(isset($_POST["add"][$r-1])?" autofocus":"").">";echo
input_hidden("fields[$r][orig]",$nh);edit_type("fields[$r]",$k,$pb,$Ed);if($U=="TABLE"){echo"<td><label class='block'>".checkbox("fields[$r][null]",1,$k["null"],"","","","label-null")."</label>","<td><label class='block'><input type='radio' name='auto_increment_col' value='$r'".($k["auto_increment"]?" checked":"")." aria-labelledby='label-ai'></label>","<td$cc>".(driver()->generated?html_select("fields[$r][generated]",array_merge(array("","DEFAULT"),driver()->generated),$k["generated"])." ":checkbox("fields[$r][generated]",1,$k["generated"],"","","","label-default"));$b=" name='fields[$r][default]' aria-labelledby='label-default'";$Y=h($k["default"]);echo(preg_match('~\n~',$k["default"])?"<textarea$b rows='2' cols='30' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");if(support("comment")){$b=" name='fields[$r][comment]' data-maxlength='".(min_version(5.5)?1024:255)."' aria-labelledby='label-comment'";echo"<td$ub>".adminer()->commentInput('COLUMN',$b,$k["comment"]);}}echo"<td>",(support("move_col")?icon("plus","add[$r]","+",'Add next')." ":""),($nh==""||support("drop_col")?icon("cross","drop_col[$r]","x",'Remove'):"");}}function
process_fields(array&$l){if($_POST["add"]){$l=array_values($l);array_splice($l,key($_POST["add"]),0,array(array()));}return$_POST["add"]||$_POST["drop_col"];}function
drop_create($Ac,$Ib,$Bc,$vk,$Cc,$_,$gg,$eg,$fg,$Vg,$Cg){if($_POST["drop"])query_redirect($Ac,$_,$gg);elseif($Vg=="")query_redirect($Ib,$_,$fg);elseif(support("transaction_ddl")){driver()->begin();queries_redirect($_,$eg,queries($Ac)&&queries($Ib)&&driver()->commit());driver()->rollback();}elseif($Vg!=$Cg){$Kb=queries($Ib);queries_redirect($_,$eg,$Kb&&queries($Ac));if($Kb&&$Bc)queries($Bc);}else
queries_redirect($_,$eg,queries($vk)&&queries($Cc)&&queries($Ac)&&queries($Ib));}function
create_trigger($Yg,array$K){$Ak=" $K[Timing] $K[Event]".(preg_match('~ OF~',$K["Event"])?" $K[Of]":"");return"CREATE TRIGGER ".idf_escape($K["Trigger"]).(JUSH=="mssql"?$Yg.$Ak:$Ak.$Yg).preg_replace('~[\s;]+$~',''," $K[Type]\n$K[Statement]").";";}function
q_dollar($Q){$hc='$$';while(strpos($Q.$hc,$hc)!=strlen($Q))$hc='$_'.substr($hc,1);return$hc.$Q.$hc;}function
routine_collate($ob){static$cb=array();if($ob&&!$cb){foreach(collations()as$bb=>$yl){foreach((array)$yl
as$X)$cb[$X]=$bb;}}return($cb[$ob]?"CHARACTER SET ".q($cb[$ob])." ":"")."COLLATE";}function
create_routine($Qi,array$K){$O=array();$l=$K["fields"];ksort($l);foreach($l
as$k){if($k["field"]!=""){$Me=(preg_match("~^(".driver()->inout.")\$~",$k["inout"])?$k["inout"]:"");$O[]="\n  ".(JUSH=="mssql"?"@$k[field]".process_type($k).($Me?" $Me":""):($Me?"$Me ":"").idf_escape($k["field"]).process_type($k,routine_collate($k["collation"])));}}$ec="";$C=array();foreach(routine_options($Qi)as$w=>$zl){$Y=idx($K["options"],$w,"");if($w=="DEFINER")$ec=($Y?" $w=".implode("@",array_map('Adminer\q',explode("@",$Y,2))):"");elseif(!$zl){if($Y!="")$C[]="$w ".q($Y);}elseif($Y!=reset($zl)&&in_array($Y,$zl))$C[]=$Y;}$qf=$K["language"];$fc=preg_replace('~[\s;]+$~','',$K["definition"]);$xc=(JUSH=="pgsql"||($qf&&$qf!="sql"));$zh=($O?implode(",",$O)."\n":"");return"CREATE$ec $Qi ".table(trim($K["name"])).(JUSH=="mssql"&&$Qi=="PROCEDURE"?rtrim($zh):" ($zh)").($Qi=="FUNCTION"?"\nRETURNS".process_type($K["returns"],routine_collate($K["returns"]["collation"])):"").($qf?" LANGUAGE $qf":"").($C?"\n".implode(" ",$C):"").($xc?" AS ".q_dollar("\n".trim($fc)."\n"):(JUSH=="mssql"?"\nAS":"")."\n$fc;");}function
remove_definer($H){$ec=implode("@",array_map('Adminer\idf_escape',explode("@",logged_user(),2)));return
preg_replace('(^([A-Z =]+) DEFINER='.preg_quote($ec).')','\1',$H);}function
format_foreign_key(array$n){$h=$n["db"];$Jg=$n["ns"];return" FOREIGN KEY (".implode(", ",array_map('Adminer\idf_escape',$n["source"])).") REFERENCES ".($h!=""&&$h!=$_GET["db"]?idf_escape($h).".":"").($Jg!=""&&$Jg!=$_GET["ns"]?idf_escape($Jg).".":"").idf_escape($n["table"])." (".implode(", ",array_map('Adminer\idf_escape',$n["target"])).")".(preg_match("~^(".driver()->onActions.")\$~",$n["on_delete"])?" ON DELETE $n[on_delete]":"").(preg_match("~^(".driver()->onActions.")\$~",$n["on_update"])?" ON UPDATE $n[on_update]":"").($n["deferrable"]?" $n[deferrable]":"");}function
tar_file($m,$Fk){$J=pack("a100a8a8a8a12a12",$m,644,0,0,decoct($Fk->size),decoct(time()));$hb=8*32;for($r=0;$r<strlen($J);$r++)$hb+=ord($J[$r]);$J
.=sprintf("%06o",$hb)."\0 ";echo$J,str_repeat("\0",512-strlen($J));$Fk->send();echo
str_repeat("\0",511-($Fk->size+511)%512);}function
doc_version(){$rj=connection()->server_info;if(JUSH=='oracle'){preg_match('~(?:.* |^)(\d+)\.\d+\.\d+\.\d+\.\d+~s',$rj,$A);return($A[1]>=18?$A[1]:"19");}$Ci=(JUSH=='sql'||connection()->flavor=='cockroach'?'~^\d+\.\d+~':'~^\d\.?\d~');$Bl=(preg_match($Ci,$rj,$A)?$A[0]:"");if(JUSH=='mssql')return($Bl>=15?"sql-server-ver$Bl":($Bl==12?"azuresqldb-current":"sql-server-2017"));return$Bl;}function
doc_link(array$Oh,$wk="<sup>?</sup>"){$Bl=doc_version();$nl=array('sql'=>"https://dev.mysql.com/doc/refman/$Bl/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(connection()->flavor=='cockroach'?"current":$Bl)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://docs.oracle.com/en/database/oracle/oracle-database/$Bl/",);if(connection()->flavor=='maria'){$nl['sql']="https://mariadb.com/kb/en/";$Oh['sql']=(isset($Oh['mariadb'])?$Oh['mariadb']:str_replace(".html","/",$Oh['sql']));}if(connection()->flavor=='cockroach'&&isset($Oh['cockroach'])){$nl['pgsql']="https://docs.cockroachlabs.com/docs/v$Bl/";$Oh['pgsql']=$Oh['cockroach'];}return($Oh[JUSH]?"<a href='".h($nl[JUSH].$Oh[JUSH].(JUSH=='mssql'?"?view=$Bl":""))."'".target_blank().">$wk</a>":"");}function
db_size($h){if(!connection()->select_db($h))return"?";$J=0;foreach(table_status()as$S)$J+=$S["Data_length"]+$S["Index_length"];return
format_number($J);}function
set_utf8mb4($Ib){static$O=false;if(!$O&&preg_match('~\butf8mb4~i',$Ib)){$O=true;echo"SET NAMES ".charset(connection()).";\n\n";}}if(DB==""&&isset($_GET["ns"]))redirect(remove_from_uri('ns'));if(!(DB!=""?connection()->select_db(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!="")page_header('Database'.": ".h(DB),adminer()->error(),true,"","db");else{if(!isset($_GET["db"])&&support("single_db")){$g=adminer()->databases();if($g)redirect(ME."db=".url_escape($g[0]));}if($_POST["db"]&&!$j)queries_redirect(substr(ME,0,-1),'Databases have been dropped.',drop_databases($_POST["db"]));page_header('Select database',$j,false);echo"<p class='links'>\n";foreach(array('database'=>'Create database','privileges'=>'Privileges','processlist'=>'Process list','variables'=>'Variables','status'=>'Status',)as$w=>$X){if(support($w))echo"<a href='".h(ME)."$w='>$X</a>\n";}echo"<p>".sprintf('%s version: %s through PHP extension %s',get_driver(DRIVER),"<b>".h(connection()->server_info)."</b>","<b>".connection()->extension."</b>")."\n","<p>".sprintf('Logged in as: %s',"<b>".h(logged_user())."</b>")."\n";$g=adminer()->databases();if($g){$bj=support("scheme");$pb=collations();echo"<form action='' method='post'>\n","<table class='checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n","<thead><tr>".(support("database")?"<td class='hover'>":"")."<th".(JUSH!='mssql'?" aria-sort='ascending'":"").">".'Database'.(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".'Refresh'."</a>":"")."<th>".'Collation'."<th>".'Tables'."<th>".'Size'." - <a href='".h(ME)."dbsize=1'".on('click','ajaxSetHtml',ME."script=connect").">".'Compute'."</a>"."<tbody>\n";$g=($_GET["dbsize"]?count_tables($g):array_flip($g));foreach($g
as$h=>$T){$Pi=h(preg_replace('~&db=[^&]*~','',ME))."db=".url_escape($h);$s=h("Db-".$h);echo"<tr>".(support("database")?"<td class='hover'>".checkbox("db[]",$h,in_array($h,(array)$_POST["db"]),"","","",$s):""),"<th><a href='$Pi' id='$s'>".h($h)."</a>";$ob=h(db_collation($h,$pb));echo"<td>".(support("database")?"<a href='$Pi".($bj?"&amp;ns=":"")."&amp;database=' title='".'Alter database'."'>$ob</a>":$ob),"<td align='right'><a href='$Pi&amp;schema=' id='tables-".h($h)."' title='".'Database schema'."'>".($_GET["dbsize"]?format_number($T):"?")."</a>","<td align='right' id='size-".h($h)."'>".($_GET["dbsize"]?db_size($h):"?"),"\n";}echo"</table>\n",(support("database")?"<div class='footer'><div>\n"."<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>\n"."<input type='hidden' name='all' value=''".on('click','countDbs').">\n"."<input type='submit' name='drop' value='".'Drop'."'".confirm().">\n"."</div></fieldset>\n"."</div></div>\n":""),input_token(),"</form>\n",script("tableCheck();");}$ia=adminer();$Wh=($ia
instanceof
Plugins?$ia->plugins:array());$_c=($ia
instanceof
Plugins?$ia->drivers:array());$mc=design_checksums();if($Wh||$_c||$mc){$ib=($ia
instanceof
Plugins?$ia->checksums():array());$Og=Plugins::officialChecksums();$il=function($ml){return" (<a href='$ml'".target_blank()." class='update'>".VERSION."</a>)";};$Vh=function($td)use($ib,$Og,$il){return($ib[$td]&&$Og[$td]&&$ib[$td]!==$Og[$td]?$il("https://www.adminer.org/plugins/?version=".VERSION):"");};echo"<div class='plugins'>\n","<h3>".'Loaded plugins'."</h3>\n<ul>\n";foreach($Wh
as$Th){$Ai=new
\ReflectionObject($Th);$jc=(method_exists($Th,'description')?$Th->description():"");if(!$jc){if(preg_match('~^/[\s*]+(.+)~',$Ai->getDocComment(),$A))$jc=$A[1];}$cj=(method_exists($Th,'screenshot')?$Th->screenshot():"");echo"<li><b>".get_class($Th)."</b>".h($jc?": $jc":"").($cj?" (<a href='".h($cj)."'".target_blank().">".'screenshot'."</a>)":"").$Vh(basename((string)$Ai->getFileName(),'.php'))."\n";}foreach($_c
as$s=>$B)echo"<li><b>".h($s)."</b>: ".h($B).$Vh(basename((string)$ia->driverFiles[$s],'.php'))."\n";if($mc){$Qg=official_design_checksums();foreach($mc
as$m=>$lc){list($B,$hb)=$lc;$Pg=$Qg["$B/$m"];echo"<li><b>".h($m)."</b>".h($B?": $B":"").($Pg&&$Pg!==$hb?$il("https://www.adminer.org/?version=".VERSION."#extras"):"")."\n";}}echo"</ul>\n";adminer()->pluginsLinks();echo"</div>\n";}}page_footer("db");exit;}adminer()->afterConnect();class
TmpFile{private$handler;var$size=0;function
__construct(){$this->handler=tmpfile();}function
write($Bb){$this->size+=strlen($Bb);fwrite($this->handler,$Bb);}function
send(){fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}if($_GET["select"]!=""&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$l=fields($a);header("Content-Type: application/octet-stream");$zl=array_merge((array)$_GET["where"],(array)$_GET["val"]);header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$zl)).".".friendly_url($_GET["field"]));$M=array(idf_escape($_GET["field"]));$I=driver()->select($a,$M,array(where($_GET,$l)),$M);$K=($I?$I->fetch_row():array());echo
driver()->value($K[0],$l[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$l=fields($a);if(!$l)$j=adminer()->error();$S=table_status1($a);$B=adminer()->tableName($S);$j=$j?:h($S["Error"]);page_header(($l&&is_view($S)?$S['Engine']=='materialized view'?'Materialized view':'View':'Table').": ".($B!=""?$B:h($a)),$j,array(),"",!$l);$Oi=array();foreach($l
as$w=>$k)$Oi+=$k["privileges"];adminer()->selectLinks($S,(isset($Oi["insert"])||!support("table")?"":null));$tb=$S["Comment"];if($tb!="")echo"<p class='nowrap'>".'Comment'.": ".adminer()->commentValue('TABLE',$tb)."\n";if($l)adminer()->tableStructurePrint($l,$S);function
tables_links(array$T){echo"<ul>\n";foreach($T
as$K){$z=preg_replace('~ns=[^&]*~',"ns=".url_escape($K["ns"]),ME);echo"<li><a href='".h($z."table=".url_escape($K["table"]))."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>";}echo"</ul>\n";}$Ke=driver()->inheritsFrom($a);if($Ke){echo"<h3>".'Inherits from'."</h3>\n";tables_links($Ke);}if(support("indexes")&&driver()->supportsIndex($S)){echo"<div>\n","<h3 id='indexes'>".'Indexes'."</h3>\n";$v=indexes($a);if($v)adminer()->tableIndexesPrint($v,$S);if(driver()->supportsAlterIndex($S))echo'<p class="links hover"><a href="'.h(ME).'indexes='.url_escape($a).'">'.'Alter indexes'."</a>\n";echo"</div>\n";}if(!is_view($S)&&driver()->supportsAlterTable($S)){if(fk_support($S)){echo"<div>\n","<h3 id='foreign-keys'>".'Foreign keys'."</h3>\n";$Ed=foreign_keys($a);if($Ed){echo"<table>\n","<thead><tr><th>".'Source'."<th>".'Target'."<th>".'ON DELETE'."<th>".'ON UPDATE'."<td class='hover'><tbody>\n";foreach($Ed
as$B=>$n){echo"<tr title='".h($B)."'>","<th><i>".implode("</i>, <i>",array_map('Adminer\h',$n["source"]))."</i>";$z=($n["db"]!=""?preg_replace('~db=[^&]*~',"db=".url_escape($n["db"]),ME):($n["ns"]!=""?preg_replace('~ns=[^&]*~',"ns=".url_escape($n["ns"]),ME):ME));echo"<td><a href='".h($z."table=".url_escape($n["table"]))."'>".($n["db"]!=""&&$n["db"]!=DB?"<b>".h($n["db"])."</b>.":"").($n["ns"]!=""&&$n["ns"]!=$_GET["ns"]?"<b>".h($n["ns"])."</b>.":"").h($n["table"])."</a>","(<i>".implode("</i>, <i>",array_map('Adminer\h',$n["target"]))."</i>)","<td>".h($n["on_delete"]),"<td>".h($n["on_update"]),'<td class="hover"><a href="'.h(ME.'foreign='.url_escape($a).'&name='.url_escape($B)).'">'.'Alter'.'</a>',"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'foreign='.url_escape($a).'">'.'Create foreign key'."</a>\n","</div>\n";}if(support("check")){echo"<div>\n","<h3 id='checks'>".'Checks'."</h3>\n";$eb=driver()->checkConstraints($a);if($eb){echo"<table>\n";foreach($eb
as$w=>$X)echo"<tr title='".h($w)."'>","<td><code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($X)),80,"</code>"),"<td class='hover'><a href='".h(ME.'check='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>","\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'check='.url_escape($a).'">'.'Create check'."</a>\n","</div>\n";}}if(support(is_view($S)?"view_trigger":"trigger")&&driver()->supportsAlterTable($S)){echo"<div>\n","<h3 id='triggers'>".'Triggers'."</h3>\n";$Rk=triggers($a);if($Rk){echo"<table>\n";foreach($Rk
as$w=>$X)echo"<tr valign='top'><td>".h($X[0])."<td>".h($X[1])."<th>".h($w)."<td class='hover'><a href='".h(ME.'trigger='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'trigger='.url_escape($a).'">'.'Create trigger'."</a>\n","</div>\n";}$wj=driver()->shadowTables($a);if($wj){echo"<h3 id='shadow-tables'>".'Shadow tables'."</h3>\n";tables_links($wj);}$Je=driver()->inheritedTables($a);if($Je){echo"<h3 id='partitions'>".'Inherited by'."</h3>\n";$Ch=driver()->partitionsInfo($a);if($Ch)echo"<p><code class='jush-".JUSH."'>BY ".h("$Ch[partition_by]($Ch[partition])")."</code>\n";tables_links($Je);}}elseif(isset($_GET["schema"])){page_header('Database schema',"",array(),h(DB.($_GET["ns"]?".$_GET[ns]":"")));function
schema_column($R,array$_i,array&$d){if(!isset($d[$R])){$d[$R]=0;foreach((array)idx($_i,$R)as$B=>$Bi){if($B!=$R)$d[$R]=max($d[$R],schema_column($B,$_i,$d)+1);}}return$d[$R];}function
type_class($U){foreach(array('char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',)as$w=>$X){if(preg_match("~$w|$X~",$U))return" class='$w'";}}$gk=array();$ik=array();$hk=array();$qd=array();$ca=($_GET["schema"]?:$_COOKIE["adminer_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$ca,$Lf,PREG_SET_ORDER);foreach($Lf
as$r=>$A){$gk[$A[1]]=array((float)$A[2],(float)$A[3]);$ik[]="\n\t'".js_escape($A[1])."': [ $A[2], $A[3] ]";}$Zi=array();$_i=array();$Ed=array();$qa=driver()->allFields();$ne=array();$jk=array();foreach(table_status('',true)as$R=>$S){if(!is_view($S)){if(adminer()->tableName($S)!=""&&!$S["dependent"])$jk[$R]=$S;else$ne[$R]=true;}}foreach($jk
as$R=>$S){$G=0;$Zi[$R]["fields"]=array();foreach($qa[$R]as$k){$G+=1.25;$qd[$R][$k["field"]]=$G;$Zi[$R]["fields"][$k["field"]]=$k;}foreach(adminer()->foreignKeys($R)as$X){if($X["db"]==""&&$X["ns"]==""&&!$ne[$X["table"]]){$Ed[$R][]=$X;$_i[$X["table"]][$R]=array();}}}$d=array();$Td=array();$Nl=array();$Yd=array();foreach(array_keys($Zi)as$B)schema_column($B,$_i,$d);arsort($d);foreach($d
as$B=>$c){$mg=null;foreach((array)idx($Ed,$B)as$X){if($X["table"]!=$B&&$Zi[$X["table"]])$mg=($mg===null?$d[$X["table"]]:min($mg,$d[$X["table"]]));}$d[$B]=max($c,(int)$mg-1);}foreach($Zi
as$B=>$R){$c=$d[$B];$Td[$c][]=$B;$yk=.75*strlen($B);foreach($R["fields"]as$k)$yk=max($yk,.65*strlen($k["field"]));$Nl[$c]=max(idx($Nl,$c,0),ceil($yk)+1);}foreach($Ed
as$B=>$yl){foreach($yl
as$X){$Xd=$d[$B]+(idx($d,$X["table"],$d[$B])>$d[$B]?1:0);$Yd[$Xd]=idx($Yd,$Xd,0)+1;}}ksort($Td);$le=0;$Ml=0;$rb=0;$gi=null;$ek=array();$lk=array();foreach($Td
as$c=>$T){if($gi!==null){$rb=round($rb+$Nl[$gi]+1.7+idx($Yd,$c,0)*.1,1);$D=array();foreach($T
as$B){$Wj=0;$Hb=0;$_g=array_keys((array)idx($_i,$B));foreach((array)idx($Ed,$B)as$X)$_g[]=$X["table"];foreach($_g
as$xg){if($Zi[$xg]&&$d[$xg]<$c){$Wj+=$Zi[$xg]["pos"][0];$Hb++;}}$D[$B]=($Hb?$Wj/$Hb:$le);}asort($D);$T=array_keys($D);}$Ik=0;foreach($T
as$B){$G=1.25*count($Zi[$B]["fields"]);$Zi[$B]["pos"]=($gk[$B]?:array($Ik,$rb));$ek[$B]=$Zi[$B]["pos"][1];$lk[$B]=$Nl[$c];$Ik+=2.5+$G;$le=max($le,$Zi[$B]["pos"][0]+2.5+$G);$Ml=max($Ml,round($Zi[$B]["pos"][1]+$Nl[$c],1));if(!$gk[$B])$hk[]="\n\t'".js_escape($B)."': [ ".$Zi[$B]["pos"][0].", ".$Zi[$B]["pos"][1]." ]";}$gi=$c;}$wf=array();$La=array();foreach($Ed
as$B=>$yl){foreach($yl
as$X){$rk=idx($ek,$X["table"],$ek[$B]);$Fj=$ek[$B]+$lk[$B];$Ni=($rk-1>$Fj);$uf=($Ni?$Fj+1:min($ek[$B],$rk)-1);$Ka=idx($La,(string)$uf,0);$La[(string)$uf]=$Ka+1;$uf=round($Ni?min($uf+$Ka*.1,$rk-1):$uf-$Ka*.1,1);while($wf[(string)$uf])$uf-=.0001;$Zi[$B]["references"][$X["table"]][(string)$uf]=array($X["source"],$X["target"]);$_i[$X["table"]][$B][(string)$uf]=$X["target"];$wf[(string)$uf]=true;}}echo'<div id="schema" style="height: ',$le,'em; width: ',$Ml,'em;">
<script',nonce(),'>
const tablePos = {',implode(",",$ik)."\n",'};
const tablePosDefault = {',implode(",",$hk)."\n",'};
const em = qs(\'#schema\').offsetHeight / ',$le,';
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, \'',js_escape(DB),'\');
</script>
';foreach($Zi
as$B=>$R){echo"<div class='table'".on('mousedown','schemaMousedown')." style='top: ".$R["pos"][0]."em; left: ".$R["pos"][1]."em; width: ".$lk[$B]."em;'>",'<a href="'.h(ME).'table='.url_escape($B).'"><b>'.h($B)."</b></a>";foreach($R["fields"]as$k){$X='<span'.type_class($k["type"]).' title="'.h($k["type"].($k["length"]?"($k[length])":"").($k["null"]?" NULL":'')).'">'.h($k["field"]).'</span>';echo"<br>".($k["primary"]?"<i>$X</i>":$X);}foreach((array)$R["references"]as$sk=>$Bi){foreach($Bi
as$uf=>$xi){$vf=$uf-$R["pos"][1];$Tj=($vf>0?"left: 100%; width: calc($vf"."em - 100%)":"left: $vf"."em");$Ml=($vf>0?"100%":(-$vf)."em");$r=0;foreach($xi[0]as$Ej)echo"\n<div class='references' title='".h($sk)."' id='refs$uf-".($r++)."' style='$Tj"."; top: ".$qd[$B][$Ej]."em; padding-top: .5em;'>"."<div style='border-top: 1px solid gray; width: $Ml;'></div></div>";}}foreach((array)$_i[$B]as$sk=>$Bi){foreach($Bi
as$uf=>$tk){$vf=$uf-$R["pos"][1];$r=0;foreach($tk
as$qk)echo"\n<div class='references arrow' title='".h($sk)."' id='refd$uf-".($r++)."' style='left: $vf"."em; top: ".$qd[$B][$qk]."em;'>"."<div style='height: .5em; border-bottom: 1px solid gray; width: ".(-$vf)."em;'></div>"."</div>";}}echo"\n</div>\n";}foreach($Zi
as$B=>$R){foreach((array)$R["references"]as$sk=>$Bi){if($Zi[$sk]){foreach($Bi
as$uf=>$xi){$ng=$le;$Tf=-10;foreach($xi[0]as$w=>$Ej){$Yh=$R["pos"][0]+$qd[$B][$Ej];$Zh=$Zi[$sk]["pos"][0]+$qd[$sk][$xi[1][$w]];$ng=min($ng,$Yh,$Zh);$Tf=max($Tf,$Yh,$Zh);}echo"<div class='references' id='refl$uf' style='left: $uf"."em; top: $ng"."em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: ".($Tf-$ng)."em;'></div></div>\n";}}}}echo'</div>
<p class="links"><a href="',h(ME."schema=".url_escape($ca)),'" id="schema-link">Permanent link</a>
';}elseif(isset($_GET["dump"])){$a=$_GET["dump"];if($_POST&&!$j){$i=array("auto_increment"=>'');foreach(array("type","routine","event","trigger")as$Yj){if(support($Yj))$i[$Yj."s"]='';}save_settings(array_intersect_key($_POST+$i,array_flip(array("output","format","db_style","schema_style","table_style","data_style"))+$i),"adminer_export");$pa=(DB==""||$_GET["ns"]==="");$T=array_flip((array)$_POST["tables"])+array_flip((array)$_POST["data"]);$hd=dump_headers((count($T)==1?key($T):DB),($pa||count($T)>1));$bf=preg_match('~sql~',$_POST["format"]);if($bf){echo"-- Adminer ".VERSION." ".get_driver(DRIVER)." ".str_replace("\n"," ",connection()->server_info)." dump\n\n";if(JUSH=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";connection()->query("SET time_zone = '+00:00'");connection()->query("SET sql_mode = ''");}}$Tj=$_POST["db_style"];$g=array(DB);if(DB==""){$g=$_POST["databases"];if(is_string($g))$g=explode("\n",rtrim(str_replace("\r","",$g),"\n"));}foreach((array)$g
as$h){adminer()->dumpDatabase($h);if(connection()->select_db($h)){if($bf&&$Tj)echo
use_sql($h,$Tj).";\n\n";foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?array(""):adminer()->schemas()))as$Zi){if($Zi!=""){if(DB==""&&information_schema(DB,$Zi))continue;set_schema($Zi);}if($bf&&$_POST["schema_style"]&&function_exists('Adminer\use_schema_sql'))echo
use_schema_sql($_GET["ns"],$_POST["schema_style"]).";\n\n";$Pj=($_POST["table_style"]||$_POST["data_style"]?table_status('',true):array());$gd=array();$Ub=array();foreach($Pj
as$B=>$S){if($pa||in_array($B,(array)$_POST["tables"]))$gd[$B]=$S;if($pa||in_array($B,(array)$_POST["data"]))$Ub[$B]=$S;}if($bf){if($_POST["table_style"]=="DROP+CREATE"&&function_exists('Adminer\drop_sql'))echo
drop_sql($gd);if($_POST["data_style"]=="TRUNCATE+INSERT"&&function_exists('Adminer\truncate_all_sql')){$Sk=array();foreach($Ub
as$B=>$S){if(!is_view($S)&&!($_POST["table_style"]=="DROP+CREATE"&&isset($gd[$B])))$Sk[]=$B;}echo
truncate_all_sql($Sk);}$uh="";if($_POST["types"]){foreach(types()as$s=>$U){$fc=type_definition($s);$Mg=($fc["kind"]=='d'?"DOMAIN":"TYPE");if($fc["definition"])$uh
.=($Tj!='DROP+CREATE'?"DROP $Mg IF EXISTS ".table($U).";;\n":"")."CREATE $Mg ".table($U)." $fc[definition];\n\n";else$uh
.="-- Could not export type $U\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$B=$K["ROUTINE_NAME"];$Qi=$K["ROUTINE_TYPE"];$Ib=create_routine($Qi,array("name"=>$B)+routine($K["SPECIFIC_NAME"],$Qi));set_utf8mb4($Ib);$uh
.=($Tj!='DROP+CREATE'?"DROP $Qi IF EXISTS ".table($B).";;\n":"")."$Ib;\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$Ib=remove_definer(get_val("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($Ib);$uh
.=($Tj!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$Ib;;\n\n";}}echo($uh&&JUSH=='sql'?"DELIMITER ;;\n\n$uh"."DELIMITER ;\n\n":$uh);}if($_POST["table_style"]||$_POST["data_style"]){$Dl=array();foreach($Pj
as$B=>$S){$R=array_key_exists($B,$gd);$Sb=array_key_exists($B,$Ub);if($R||$Sb){$Fk=null;if($hd=="tar"){$Fk=new
TmpFile;ob_start(array($Fk,'write'),1e5);}adminer()->dumpTable($B,($R?$_POST["table_style"]:""),(is_view($S)?2:0));if(is_view($S))$Dl[]=$B;elseif($Sb){$l=fields($B);$M=array("*");$Eb=convert_fields($l,$l);if($Eb)$M[]=substr($Eb,2);adminer()->dumpData($B,$_POST["data_style"],"",$M);}if($bf&&$_POST["triggers"]&&$R&&($Rk=trigger_sql($B)))echo"\nDELIMITER ;;\n$Rk\nDELIMITER ;\n";if($hd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$h/")."$B.csv",$Fk);}elseif($bf)echo"\n";}}if($bf&&$_POST["table_style"]&&function_exists('Adminer\foreign_keys_sql')){foreach($gd
as$B=>$S){if(!is_view($S))echo
foreign_keys_sql($B);}}if($bf){foreach($Dl
as$Cl)adminer()->dumpTable($Cl,$_POST["table_style"],1);}if($hd=="tar")echo
pack("x1024");}}}}adminer()->dumpFooter();exit;}page_header('Export',$j,($_GET["export"]!=""?array("table"=>$_GET["export"]):array()),h(DB));echo'
<form action="" method="post">
<table class="layout">
';$Yb=array('','USE','DROP+CREATE','CREATE');$aj=(JUSH=="mssql"?array('','DROP+CREATE','CREATE'):$Yb);$kk=array('','DROP+CREATE','CREATE');$Tb=array('','TRUNCATE+INSERT','INSERT');if(JUSH=="sql")$Tb[]='INSERT+UPDATE';$K=get_settings("adminer_export");if(!$K)$K=array("output"=>"text","format"=>"sql","db_style"=>(DB!=""?"":"CREATE"),"schema_style"=>"","table_style"=>"DROP+CREATE","data_style"=>"INSERT");echo"<tr><th>".'Output'."<td>".html_radios("output",adminer()->dumpOutput(),$K["output"])."\n","<tr><th>".'Format'."<td>".html_radios("format",adminer()->dumpFormat(),$K["format"])."\n",(JUSH=="sqlite"?"":"<tr><th>".'Database'."<td>".html_select('db_style',$Yb,$K["db_style"]).(support("type")?checkbox("types",1,$K["types"],'User types'):"").(support("routine")?checkbox("routines",1,$K["routines"],'Routines'):"").(support("event")?checkbox("events",1,$K["events"],'Events'):"")),(function_exists('Adminer\use_schema_sql')?"<tr><th>".'Schema'."<td>".html_select('schema_style',$aj,$K["schema_style"]):""),"<tr><th>".'Tables'."<td>".html_select('table_style',$kk,$K["table_style"]).checkbox("auto_increment",1,$K["auto_increment"],'Auto Increment').(support("trigger")?checkbox("triggers",1,$K["triggers"],'Triggers'):""),"<tr><th>".'Data'."<td>".html_select('data_style',$Tb,$K["data_style"]),'</table>
';adminer()->dumpPrint();echo'<p><input type=\'submit\' value=\'Export\'>
',input_token(),'
<table',on('click','dumpClick'),'>
';$fi=array();if($_GET["ns"]===""&&support("scheme")){echo"<thead><tr><th style='text-align: left;'>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly' title='".'All'."'".on('click','formCheck','^schemas\[').">".'Schema'."</label>","<tbody>\n";foreach(adminer()->schemas()as$Zi){if(!information_schema(DB,$Zi))echo"<tr><td>".checkbox("schemas[]",$Zi,true,$Zi,"","block")."\n";}}elseif(DB!=""){$fb=($a!=""?"":" checked");echo"<thead><tr>","<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$fb class='jsonly' title='".'All'."'".on('click','formCheck','^tables\[').">".'Table'."</label>","<th style='text-align: right;'><label class='block'>".'Data'."<input type='checkbox' id='check-data'$fb class='jsonly' title='".'All'."'".on('click','formCheck','^data\[')."></label>","<tbody>\n";$Dl="";$nk=tables_list();foreach($nk
as$B=>$U){$ei=preg_replace('~_.*~','',$B);$fb=($a==""||$a==(substr($a,-1)=="%"?"$ei%":$B));$ji="<tr><td>".checkbox("tables[]",$B,$fb,$B,"","block");if($U!==null&&!preg_match('~table~i',$U))$Dl
.="$ji\n";else
echo"$ji<td align='right'><label class='block'><span id='Rows-".h($B)."'></span>".checkbox("data[]",$B,$fb)."</label>\n";$fi[$ei]++;}echo$Dl;if($nk)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$g=adminer()->databases();echo"<thead><tr><th style='text-align: left;'>","<label class='block'>".($g?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly' title='".'All'."'".on('click','formCheck','^databases\[').">":"").'Database'."</label>","<tbody>\n";if($g){foreach($g
as$h){if(!information_schema($h)){$ei=preg_replace('~_.*~','',$h);echo"<tr><td>".checkbox("databases[]",$h,$a==""||$a=="$ei%",$h,"","block")."\n";$fi[$ei]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo'</table>
</form>
';$zd=true;foreach($fi
as$w=>$X){if($w!=""&&$X>1){echo($zd?"<p>":" ")."<a href='".h(ME)."dump=".url_escape("$w%")."'>".h($w)."</a>";$zd=false;}}}elseif(isset($_GET["privileges"])){page_header('Privileges');echo'<p class="links"><a href="'.h(ME).'user=">'.'Create user'."</a>";$I=connection()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$Rd=$I;if(!$I)$I=connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''><p>\n";hidden_fields_get();echo
input_hidden("db",DB),($Rd?"":input_hidden("grant")),"<table class='odds'>\n","<thead><tr><th>".'Username'."<th>".'Server'."<td class='hover'><tbody>\n";while($K=$I->fetch_assoc())echo'<tr><td>'.h($K["User"]),"<td>".h($K["Host"]),'<td class="hover"><a href="'.h(ME.'user='.url_escape($K["User"]).'&host='.url_escape($K["Host"])).'">'.'Edit'."</a>\n";if(!$Rd||DB!="")echo"<tr><td><input name='user' autocapitalize='off'>","<td><input name='host' value='localhost' autocapitalize='off'>","<td class='hover'><input type='submit' value='".'Edit'."'>\n";echo"</table>\n","</form>\n";}elseif(isset($_GET["sql"])){if(!$j&&$_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers("sql");if($_POST["format"]=="sql")echo"$_POST[query]\n";else{adminer()->dumpTable("","");adminer()->dumpData("","table",$_POST["query"]);adminer()->dumpFooter();}exit;}if(!$j&&$_POST["val"]){$la=0;$Uj=true;$Ya=array();$Wi=0;foreach($_POST["val"]as$L)$Wi+=count($L);$Na=$Wi>1&&driver()->begin();foreach($_POST["val"]as$ck=>$L){$R=bracket_escape($ck,true);$l=fields($R);$dk=indexes($R);foreach($L
as$t=>$K){parse_str(bracket_escape($t,true),$Z);$al=array();foreach($Z["where"]as$w=>$X)$al[bracket_escape($w,true)]=$X;if(!$l||$Z["null"]||array_diff_key($al,$l)||!unique_array($al,$dk)){$Uj=false;break
2;}$O=array();$M=array();foreach($K
as$jf=>$X){$w=bracket_escape($jf,true);$k=idx($l,$w);if(!$k){$Uj=false;break
3;}$O[idf_escape($w)]=(preg_match('~char|text~',$k["type"])||$X!=""?adminer()->processInput($k,$X):"NULL");$M[$jf]=$w;}$ti=where($Z,$l);if(!driver()->update($R,$O," WHERE $ti",0," ")){$Uj=false;break
2;}$la+=connection()->affected_rows;$d=array();foreach($M
as$w)$d[]=idf_escape($w);$jl=driver()->select($R,$d,array($ti),$d);$Dg=($jl?$jl->fetch_row():array());$ff=0;foreach($M
as$jf=>$w){$k=$l[$w];$Sj=array('type'=>(preg_match('~binary~',$k["type"])?'blob':$k["type"]));$Ya["val[$ck][$t][$jf]"]=select_value(idx($Dg,$ff++),"",$Sj,null);}}}if($Na&&$Uj)$Uj=driver()->commit();queries_redirect(null,lang_format(array('%d item has been affected.','%d items have been affected.'),$la),$Uj);if($Na&&!$Uj)driver()->rollback();page_headers();page_messages($j);foreach($Ya
as$B=>$X)echo"<div data-name='".h($B)."' hidden>$X</div>\n";exit;}restart_session();$pe=&get_session("queries");$oe=&$pe[DB];if(!$j&&$_POST["clear"]){$oe=array();redirect(remove_from_uri("history"));}stop_session();$ja=get_settings("adminer_import");if($_POST&&$ja)save_settings($ja,"adminer_import");page_header((isset($_GET["import"])?'Import':'SQL command'),$j);$Cf=driver()->lineComment();if(!$j&&$_POST&&!(isset($_GET["import"])&&adminer()->importProcess())){$hc=driver()->delimiter;$o=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$Ij=adminer()->importServerPath();$o=@fopen((file_exists($Ij)?$Ij:"compress.zlib://$Ij.gz"),"rb");$H=($o?fread($o,1e6):false);}else$H=get_file("sql_file",true,$hc);if(is_string($H)){if(($bg=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($bg,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$qi=$H.(preg_match("~$hc\\s*\$~",$H)?"":$hc);if(!$oe||first(end($oe))!=$qi){restart_session();$oe[]=array($qi,time());set_session("queries",$pe);stop_session();}}$Gj="(?:\\s|/\\*[\s\S]*?\\*/|(?:$Cf)[^\n]*\n?|--\r?\n)";$Rg=0;$Mc=true;$Gb=false;$f=connect();if($f&&DB!=""){$f->select_db(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$f);}$sb=0;$Tc=array();$Ah='[\'"'.(JUSH=="sql"?'`':(JUSH=="sqlite"?'`[':(JUSH=="mssql"?'[':''))).']|/\*|'.$Cf.'|$'.(JUSH=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$Jk=microtime(true);while($H!=""){if(!$Rg&&preg_match("~^$Gj*+DELIMITER\\s+(\\S+)~i",$H,$A)){$hc=preg_quote($A[1]);$H=substr($H,strlen($A[0]));}elseif(!$Rg&&JUSH=='pgsql'&&preg_match("~^($Gj*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$A)){$hc="\n\\\\\\.\r?\n";$Gb=true;$Rg=strlen($A[0]);}else{preg_match("($hc\\s*|$Ah)",$H,$A,PREG_OFFSET_CAPTURE,$Rg);list($Gd,$G)=$A[0];if(!$Gd&&$o&&!feof($o))$H
.=fread($o,1e5);else{if(!$Gd&&rtrim($H)=="")break;$Rg=$G+strlen($Gd);if($Gd&&!preg_match("(^$hc)",$Gd)){$Va=driver()->hasCStyleEscapes()||(JUSH=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$Ph=($Gd=='/*'?'\*/':($Gd=='['?']':(preg_match("~^(?:$Cf)~",$Gd)?"\n":preg_quote($Gd).($Va?'|\\\\.':''))));while(preg_match("($Ph|\$)s",$H,$A,PREG_OFFSET_CAPTURE,$Rg)){$Xi=$A[0][0];if(!$Xi&&$o&&!feof($o))$H
.=fread($o,1e5);else{$Rg=$A[0][1]+strlen($Xi);if(!$Xi||$Xi[0]!="\\")break;}}}else{$qi=substr($H,0,$G+($Gb?3:0));$H=substr($H,$Rg);$Rg=0;if($Gb){$hc=driver()->delimiter;$Gb=false;}$lb="<code class='jush-".JUSH."'>".adminer()->sqlCommandQuery($qi)."</code>";if(preg_match("~^$Gj*+\$~",$qi)&&!preg_match('~/\*M?!~',$qi)){echo($_POST["only_errors"]?"":"<pre>$lb</pre>\n");continue;}$Mc=false;$sb++;$ji="<pre id='sql-$sb'>$lb</pre>\n";if(JUSH=="sqlite"&&preg_match("~^$Gj*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$qi,$A)!==0){echo$ji,"<p class='error'>".sprintf('%s queries are not supported.',preg_match('~ATTACH~i',$A[1])?'ATTACH':'VACUUM INTO')."\n";$Tc[]=" <a href='#sql-$sb'>$sb</a>";if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$ji;ob_flush();flush();}$Nj=microtime(true);if(connection()->multi_query($qi)&&$f&&preg_match("~^$Gj*+USE\\b~i",$qi))$f->query($qi);do{$I=connection()->store_result();if(connection()->error){echo($_POST["only_errors"]?$ji:""),"<p class='error'>".'Error in query'.(connection()->errno?" (".connection()->errno.")":"").": ".adminer()->error()."\n";$Tc[]=" <a href='#sql-$sb'>$sb</a>";if($_POST["error_stops"])break
2;}else{$z=ME."sql=".url_escape(trim($qi));$zk=" <span class='time'>(".format_time($Nj).")</span>".(strlen($z)<1900?" <a href='".h($z)."'>".'Edit'."</a>":"");$la=connection()->affected_rows;$Gl=($_POST["only_errors"]?"":driver()->warnings());$Hl="warnings-$sb";if($Gl)$zk
.=", <a href='#$Hl' class='toggle'>".'Warnings'."</a>";$ed="";$fd="explain-$sb";if(is_object($I)){$y=$_POST["limit"];$Kg=$y;$Fc=!$_POST["only_errors"];if($Fc)echo"<form action='' method='post'>\n";$mh=print_select_result($I,$f,array(),$Kg,$Fc);if(!$_POST["only_errors"]){$Kg=max($I->num_rows,$Kg);echo"<p class='sql-footer'>".($Kg?($y&&$Kg>$y?sprintf('%d / ',$y):"").lang_format(array('%d row','%d rows'),$Kg):""),$zk;if($f&&preg_match("~^($Gj|\\()*+SELECT\\b~i",$qi)&&($ed=adminer()->explain($f,$qi,$mh))!="")echo", <a href='#$fd' class='toggle'>Explain</a>";if($Fc)echo", <input type='submit' name='save' value='".'Save'."' class='jsonly' disabled"." title='".'Ctrl+click on a value to modify it.'."'".on('click','sqlSave','Saving…').">";$s="export-$sb";echo", <a href='#$s' class='toggle'>".'Export'."</a><span id='$s' class='hidden'>: ".html_select("output",adminer()->dumpOutput(),$ja["output"])." ".html_select("format",adminer()->dumpFormat(),$ja["format"]).input_hidden("query",$qi)."<input type='submit' name='export' value='".'Export'."'".($y?"":on('click','sqlExport')).">".input_token()."</span>\n"."</form>\n";}}else{if(preg_match("~^$Gj*+(CREATE|DROP|ALTER)$Gj++(DATABASE|SCHEMA)\\b~i",$qi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"])echo"<p class='message' title='".h(connection()->info)."'>".lang_format(array('Query executed OK, %d row affected.','Query executed OK, %d rows affected.'),$la)."$zk\n";}echo($Gl?"<div id='$Hl' class='hidden'>\n$Gl</div>\n":""),($ed!=""?"<div id='$fd' class='hidden explain'>\n$ed</div>\n":"");}$Nj=microtime(true);}while(connection()->next_result());}}}}}if($Mc)echo"<p class='message'>".'No commands to execute.'."\n";else{$Ce=connection()->inTransaction();driver()->rollback();if($Ce)echo"<pre><code class='jush-".JUSH."'>ROLLBACK".(JUSH=="mssql"?" TRANSACTION":"")." -- Adminer</code></pre>\n";if($_POST["only_errors"])echo"<p class='message'>".lang_format(array('%d query executed OK.','%d queries executed OK.'),$sb-count($Tc))," <span class='time'>(".format_time($Jk).")</span>\n";elseif($Tc&&$sb>1)echo"<p class='error'>".'Error in query'.": ".implode("",$Tc)."\n";}}else
echo"<p class='error'>".upload_error($H)."\n";}echo'
<form action="" method="post" enctype="multipart/form-data" id="form"';$kl="";if(!isset($_GET["import"]))echo
on('submit','sqlSubmit',remove_from_uri("sql|limit|error_stops|only_errors|history"));else
echo
on_upload_progress($kl);echo'>
';$bd="<input type='submit' value='".'Execute'."' title='Ctrl+Enter'>";if(!isset($_GET["import"])){$qi=$_GET["sql"];if($_POST)$qi=$_POST["query"];elseif($_GET["history"]=="all")$qi=$oe;elseif($_GET["history"]!="")$qi=idx($oe[$_GET["history"]],0);echo"<p>";textarea("query",$qi,20);echo($_POST?"":script("qs('textarea').focus();")),"<p>";adminer()->sqlPrintAfter();echo"$bd\n",'Limit rows'.": <input type='number' name='limit' class='size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{$Zd=(extension_loaded("zlib")?"[.gz]":"");echo"<fieldset><legend>".'File upload'."</legend><div>",($kl?input_hidden(ini_get("session.upload_progress.name"),$kl):""),"SQL$Zd: ".file_input(" name='sql_file[]' multiple","\n$bd"),($kl?" <progress class='jsonly hidden' max='1' value='0'></progress>":""),"</div></fieldset>\n";$_e=adminer()->importServerPath();if($_e)echo"<fieldset><legend>".'From server'."</legend><div>",sprintf('Webserver file %s',"<code>".h($_e)."$Zd</code>")," <input type='submit' name='webfile' value='".'Run file'."'>","</div></fieldset>\n";adminer()->importPrint();echo"<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),'Stop on error')."\n",checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),'Show only errors')."\n",input_token();if(!isset($_GET["import"])&&$oe){print_fieldset("history",'History',$_GET["history"]!="");for($X=end($oe);$X;$X=prev($oe)){$w=key($oe);list($qi,$zk,$Ic)=$X;echo'<div><a href="'.h(ME."sql=&history=$w").'" class="hover">'.'Edit'."</a>"." <span class='time' title='".@date('Y-m-d',$zk)."'>".@date("H:i:s",$zk)."</span>"." <code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim(preg_replace("~^(?:$Cf).*~m",'',$qi))),80,"</code>").($Ic?" <span class='time'>($Ic)</span>":"")."</div>\n";}echo"<input type='submit' name='clear' value='".'Clear'."'>\n","<a href='".h(ME."sql=&history=all")."'>".'Edit all'."</a>\n","</div></fieldset>\n";}echo'</form>
';}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$l=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$l):""):where($_GET,$l));$hl=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($l
as$B=>$k){if((!$hl&&!isset($k["privileges"]["insert"]))||adminer()->fieldName($k)=="")unset($l[$B]);}if($_POST&&!$j&&!isset($_GET["select"])){$_=relative_uri((string)$_POST["referer"]);if($_POST["insert"])$_=($hl?null:relative_uri());elseif(!preg_match('~^.+&select=.+$~',$_))$_=ME."select=".url_escape($a);$v=indexes($a);$bl=unique_array($_GET["where"],$v);$ti="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($_,'Item has been deleted.',driver()->delete($a,$ti,$bl?0:1));else{$O=array();foreach($l
as$B=>$k){$X=process_input($k);if($X!==false&&$X!==null)$O[idf_escape($B)]=$X;}if($hl){if(!$O)redirect($_);queries_redirect($_,'Item has been updated.',driver()->update($a,$O,$ti,$bl?0:1));if(is_ajax()){page_headers();page_messages($j);exit;}}else{$I=driver()->insert($a,$O);$tf=($I?last_id($I):0);queries_redirect($_,sprintf('Item%s has been inserted.',($tf?" $tf":"")),$I);}}}$K=null;$H="";$zk="";if($Z){$M=array();$ij=array("*");foreach($l
as$B=>$k){if(isset($k["privileges"]["select"])){$za=($_POST["clone"]&&$k["auto_increment"]?"''":convert_field($k));$c=($za?"$za AS ":"").idf_escape($B);$M[]=$c;if($za)$ij[]=$c;}}$K=array();if(!support("table")){$M=array("*");$ij=$M;}if($M){$Nj=microtime(true);$I=driver()->select($a,$M,array($Z),$M,array(),(isset($_GET["select"])?2:1));$H=str_replace("SELECT ".implode(", ",$M),"SELECT ".implode(", ",$ij),driver()->query);$zk=format_time($Nj);if(!$I)$j=adminer()->error();else{$K=$I->fetch_assoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetch_assoc()))$K=null;}}if(!$l&&driver()->primary!=""){if(!$Z){$I=driver()->select($a,array("*"),array(),array("*"));$K=($I?$I->fetch_assoc():false);if(!$K)$K=array(driver()->primary=>"");}if($K){foreach($K
as$w=>$X){if(!$Z)$K[$w]=null;$l[$w]=array("field"=>$w,"null"=>($w!=driver()->primary),"auto_increment"=>($w==driver()->primary));}}}if($_POST["save"]){$ai=array();foreach((array)$_POST["fields"]as$w=>$X)$ai[bracket_escape($w,true)]=$X;$K=$ai+($K?$K:array());}edit_form($a,$l,$K,$hl,$j,$H,$zk);}elseif(isset($_GET["create"])){function
referencable_primary($kj){$J=array();foreach(table_status('',true)as$fk=>$R){if($fk!=$kj&&!$R["dependent"]&&fk_support($R)){foreach(fields($fk)as$k){if($k["primary"]){if($J[$fk]){unset($J[$fk]);break;}$J[$fk]=$k;}}}}return$J;}$a=$_GET["create"];$Eh=driver()->partitionBy;$Ih=($Eh&&$a!=""?driver()->partitionsInfo($a):array());$zi=referencable_primary($a);$Ed=array();foreach($zi
as$fk=>$k)$Ed[str_replace("`","``",$fk)."`".str_replace("`","``",$k["field"])]=$fk;$ph=array();$S=array();$Ig=false;if($a!=""){$ph=fields($a);$S=table_status1($a);$Ig=(count($S)<2);}$ta=($a==""||driver()->supportsAlterTable($S));$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!$j)save_settings(array("comments"=>$_POST["comments"],"defaults"=>$_POST["defaults"]));if($_POST&&!process_fields($K["fields"])&&!$j){if($_POST["drop"])queries_redirect(substr(ME,0,-1),'Table has been dropped.',drop_tables(array($a)));else{$l=array();$qa=array();$ol=false;$Cd=array();$oh=reset($ph);$na=" FIRST";foreach($K["fields"]as$k){$n=$Ed[$k["type"]];$Uk=($n!==null?$zi[$n]:$k);if($k["field"]!=""){if(!$k["generated"])$k["default"]=null;$oi=process_field($k,$Uk);$qa[]=array($k["orig"],$oi,$na);if(!$oh||$oi!==process_field($oh,$oh)){$l[]=array($k["orig"],$oi,$na);if($k["orig"]!=""||$na)$ol=true;}if($n!==null)$Cd[idf_escape($k["field"])]=($a!=""&&JUSH!="sqlite"?"ADD":" ").format_foreign_key(array('table'=>$Ed[$k["type"]],'source'=>array($k["field"]),'target'=>array($Uk["field"]),'on_delete'=>$k["on_delete"],));$na=" AFTER ".idf_escape($k["field"]);}elseif($k["orig"]!=""){$ol=true;$l[]=array($k["orig"]);}if($k["orig"]!=""){$oh=next($ph);if(!$oh)$na="";}}$Gh=array();if(in_array($K["partition_by"],$Eh)){foreach($K
as$w=>$X){if(preg_match('~^partition~',$w))$Gh[$w]=$X;}foreach($Gh["partition_names"]as$w=>$B){if($B==""){unset($Gh["partition_names"][$w]);unset($Gh["partition_values"][$w]);}}$Gh["partition_names"]=array_values($Gh["partition_names"]);$Gh["partition_values"]=array_values($Gh["partition_values"]);if($Gh==$Ih)$Gh=array();}elseif(preg_match("~partitioned~",$S["Create_options"]))$Gh=null;$dg='Table has been altered.';if($a==""){cookie("adminer_engine",$K["Engine"]);$dg='Table has been created.';}$B=trim($K["name"]);$_=ME.(support("table")?"table=":"select=").url_escape($B);$I=alter_table($a,$B,(JUSH=="sqlite"&&($ol||$Cd)?$qa:$l),$Cd,($K["Comment"]!=$S["Comment"]?$K["Comment"]:null),($K["Engine"]&&$K["Engine"]!=$S["Engine"]?$K["Engine"]:""),($K["Collation"]&&$K["Collation"]!=$S["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$Gh);if($I&&!Queries::$queries&&$a!=""&&!$l&&!$Cd)redirect($_);queries_redirect($_,$dg,$I);}}page_header(($a!=""?'Alter table':'Create table'),$j,array("table"=>$a),h($a),$Ig);if(!$_POST){$Xk=driver()->types();$K=array("Engine"=>$_COOKIE["adminer_engine"],"fields"=>array(array("field"=>"","type"=>(isset($Xk["int"])?"int":(isset($Xk["integer"])?"integer":"")),"on_update"=>"")),"partition_names"=>array(""),);if($a!=""){$K=$S;$K["name"]=$a;$K["fields"]=array();if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($ph
as$k){if($k["generated"])$k["default"]=ltrim($k["default"]);$k["generated"]=$k["generated"]?:(isset($k["default"])?"DEFAULT":"");$K["fields"][]=$k;}if($Eh){$K+=$Ih;$K["partition_names"][]="";$K["partition_values"][]="";}}}$pb=flat_collations();$Oc=driver()->engines();foreach($Oc
as$Nc){if(!strcasecmp($Nc,$K["Engine"])){$K["Engine"]=$Nc;break;}}$Of=max_input_vars(12,20);if($Of){$ne=(count($K["fields"])>$Of?"":" hidden");echo"<p".($ne?" id='max-fields' data-columns='$Of'":"")." class='error$ne'>".max_input_vars_error()."\n";}echo'
<form action="" method="post" id="form">
<p>
';if(support("columns")||$a==""){echo'Table name'.": <input name='name'".($a==""&&!$_POST?" autofocus":"")." data-maxlength='64' value='".h($K["name"])."' autocapitalize='off'>\n",(!$ta?h($S["Engine"])."\n":($Oc?html_select("Engine",array(""=>"(".'engine'.")")+$Oc,$K["Engine"],on('change','helpClose').on_help_value())."\n":""));if($pb)echo"<datalist id='collations'>".optionlist($pb)."</datalist>\n",(preg_match("~sqlite|mssql~",JUSH)?"":"<input list='collations' name='Collation' value='".h($K["Collation"])."' placeholder='(".'collation'.")'>\n");echo"<input type='submit' value='".'Save'."'>\n";}if(support("columns")&&$ta){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$pb,"TABLE",$Ed);echo"</table>\n",script("editFields();"),"</div>\n<p>\n",'Auto Increment'.": <input type='number' name='Auto_increment' class='size' value='".h($K["Auto_increment"])."'>\n",checkbox("defaults",1,($_POST?$_POST["defaults"]:get_setting("defaults")),'Default values',on('click','columnShowClick',6),"jsonly");$vb=($_POST?$_POST["comments"]:get_setting("comments"));if(support("comment")){echo
checkbox("comments",1,$vb,'Comment',on('click','editingCommentsClick',true),"jsonly").' ';$b=" name='Comment' data-maxlength='".(min_version(5.5)?2048:60)."'".($vb?"":" class='hidden'");echo
adminer()->commentInput('TABLE',$b,$K["Comment"]);}echo'<p>
<input type=\'submit\' value=\'Save\'>
';}echo'
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';if($Eh&&(JUSH=='sql'||$a=="")){$Fh=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset("partition",'Partition by',$K["partition_by"]);echo"<p>".html_select("partition_by",array_merge(array(""),$Eh),$K["partition_by"],on('change','partitionByChange').on_help_value('.','PARTITION BY $&'))."\n","(<input name='partition' value='".h($K["partition"])."'>)\n",'Partitions'.": <input type='number' name='partitions' class='size".($Fh||!$K["partition_by"]?" hidden":"")."' value='".h($K["partitions"])."'>\n","<table id='partition-table'".($Fh?"":" class='hidden'").">\n","<thead><tr><th>".'Partition name'."<th>".'Values'."<tbody>\n";foreach($K["partition_names"]as$w=>$X)echo'<tr>','<td><input name="partition_names[]" value="'.h($X).'" autocapitalize="off"'.($w==count($K["partition_names"])-1?on('input','partitionNameChange'):'').'>','<td><input name="partition_values[]" value="'.h(idx($K["partition_values"],$w)).'">';echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$He=array("PRIMARY","UNIQUE","INDEX");$S=table_status1($a,true);$Fe=driver()->indexAlgorithms($S);if(preg_match('~MyISAM|M?aria'.(min_version(5.6,'10.0.5')?'|InnoDB':'').'~i',$S["Engine"]))$He[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.(min_version(5.7,'10.2.2')?'|InnoDB':'').'~i',$S["Engine"]))$He[]="SPATIAL";if(min_version('',11.7)&&preg_match('~MyISAM|InnoDB~i',$S["Engine"]))$He[]="VECTOR";$v=indexes($a);$l=fields($a);$ii=array();if(JUSH=="mongo"){$ii=$v["_id_"];unset($He[0]);unset($v["_id_"]);}$K=$_POST;if($K)save_settings(array("index_options"=>$K["options"]));if($_POST&&!$j&&!$_POST["add"]&&!$_POST["drop_col"]){$sa=array();foreach($K["indexes"]as$u){$B=$u["name"];if(in_array($u["type"],$He)){$d=array();$_f=array();$kc=array();$ch=array();$Ge=(support("partial_indexes")?$u["partial"]:"");$Ee=(in_array($u["algorithm"],$Fe)?$u["algorithm"]:"");$O=array();ksort($u["columns"]);foreach($u["columns"]as$w=>$c){if($c!=""){$x=idx($u["lengths"],$w);$ic=idx($u["descs"],$w);$bh=idx($u["opclasses"],$w);$O[]=($l[$c]?idf_escape($c):$c).($x?"(".(+$x).")":"").($bh!=""?" ".idf_escape($bh):"").($ic?" DESC":"");$d[]=$c;$_f[]=($x?:null);$kc[]=$ic;$ch[]="$bh";}}$cd=$v[$B];if($cd){ksort($cd["columns"]);ksort($cd["lengths"]);ksort($cd["descs"]);if($u["type"]==$cd["type"]&&array_values($cd["columns"])===$d&&(!$cd["lengths"]||array_values($cd["lengths"])===$_f)&&array_values($cd["descs"])===$kc&&(!$cd["opclasses"]||array_values($cd["opclasses"])===$ch)&&$cd["partial"]==$Ge&&(!$Fe||$cd["algorithm"]==$Ee)){unset($v[$B]);continue;}}if($d)$sa[]=array($u["type"],$B,$O,$Ee,$Ge);}}foreach($v
as$B=>$cd)$sa[]=array($cd["type"],$B,"DROP");if(!$sa)redirect(ME."table=".url_escape($a));queries_redirect(ME."table=".url_escape($a),'Indexes have been altered.',alter_indexes($a,$sa));}page_header('Indexes',$j,array("table"=>$a),h($a));$sd=array_keys($l);if($_POST["add"]){foreach($K["indexes"]as$w=>$u){if($u["columns"][count($u["columns"])]!="")$K["indexes"][$w]["columns"][]="";}$u=end($K["indexes"]);if($u["type"]||array_filter($u["columns"],'strlen'))$K["indexes"][]=array("columns"=>array(1=>""));}if(!$K){foreach($v
as$w=>$u){$v[$w]["name"]=$w;$v[$w]["columns"][]="";}$v[]=array("columns"=>array(1=>""));$K["indexes"]=$v;}$_f=(JUSH=="sql"||JUSH=="mssql");$ch=driver()->indexOpclasses();$xj=($_POST?$_POST["options"]:get_setting("index_options"));echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap odds">
<thead><tr>
<th id="label-type">Index Type
';$ye=" class='idxopts".($xj?"":" hidden")."'";if($Fe)echo"<th id='label-algorithm'$ye>".'Algorithm'.doc_link(array('sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'storage-engine-index-types/',));echo'<th><input type="submit" hidden>','Columns'.($_f?"<span$ye> (".'length'.")</span>":"");if($_f||support("descidx"))echo
checkbox("options",1,$xj,'Options',on('click','indexOptionsShow'),"jsonly")."\n";echo'<th id="label-name">Name
';if(support("partial_indexes"))echo"<th id='label-condition'$ye>".'Condition';echo'<td><noscript>',icon("plus","add[0]","+",'Add next'),'</noscript>
<tbody>
';if($ii){echo"<tr><td>PRIMARY<td>";foreach($ii["columns"]as$w=>$c)echo
select_input(" disabled",array_combine($sd,$sd),$c),"<label><input disabled type='checkbox'>".'descending'."</label> ";echo"<td><td>\n";}$ff=1;foreach($K["indexes"]as$u){if(!$_POST["drop_col"]||$ff!=key($_POST["drop_col"])){echo"<tr><td>".html_select("indexes[$ff][type]",array(-1=>"")+$He,$u["type"],($ff==count($K["indexes"])?on('change','indexesAddRow'):""),"label-type");if($Fe)echo"<td$ye>".html_select("indexes[$ff][algorithm]",array_merge(array(""),$Fe),$u['algorithm'],"","label-algorithm");echo"<td>";ksort($u["columns"]);$r=1;foreach($u["columns"]as$w=>$c){echo"<span>".select_input(" name='indexes[$ff][columns][$r]' title='".'Column'."'".on('change','indexesChangeColumn',(JUSH=="sql"?"":$_GET["indexes"]."_")),($l&&($c==""||$l[$c])?array_combine($sd,$sd):array()),$c)," <span$ye>",($_f?"<input type='number' name='indexes[$ff][lengths][$r]' class='size' value='".h(idx($u["lengths"],$w))."' title='".'Length'."'>":"");if($ch){$bh=idx($u["opclasses"],$w);echo
html_select("indexes[$ff][opclasses][$r]",array(""=>"(".'operator class'.")")+array_combine($ch,$ch)+($bh!=""?array($bh=>$bh):array()),$bh),'';}echo(support("descidx")?checkbox("indexes[$ff][descs][$r]",1,idx($u["descs"],$w),'descending'):""),"<br>","</span></span>";$r++;}echo"<td><input name='indexes[$ff][name]' value='".h($u["name"])."' autocapitalize='off' aria-labelledby='label-name'>\n";if(support("partial_indexes"))echo"<td$ye><input name='indexes[$ff][partial]' value='".h($u["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>".icon("cross","drop_col[$ff]","x",'Remove',on('click','editingRemoveRow','indexes$1[type]'));}$ff++;}echo'</table>
</div>
<p>
<input type=\'submit\' value=\'Save\'>
',input_token(),'</form>
';}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!$j&&!$_POST["add"]){$B=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),'Database has been dropped.',drop_databases(array(DB)));}elseif($B!==DB){if(DB!=""){$_GET["db"]=$B;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".url_escape($B),'Database has been renamed.',rename_database($B,(string)$K["collation"]));}else{$g=explode("\n",str_replace("\r","",$B));$Uj=true;$rf="";foreach($g
as$h){if(count($g)==1||$h!=""){if(!create_database($h,(string)$K["collation"]))$Uj=false;$rf=$h;}}restart_session();set_session("dbs",null);queries_redirect(preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($rf),'Database has been created.',$Uj);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($B).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),'Database has been altered.');}}page_header(DB!=""?'Alter database':'Create database',$j,array(),h(DB));$pb=collations();$B=DB;if($_POST)$B=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,$pb);elseif(JUSH=="sql"){foreach(get_vals("SHOW GRANTS")as$Rd){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$Rd,$A)&&$A[1]){$B=stripcslashes(idf_unescape("`$A[2]`"));break;}}}echo'
<form action="" method="post">
<p>
',($_POST["add"]||strpos($B,"\n")?'<textarea autofocus name="name" rows="10" cols="40">'.h($B).'</textarea><br>':'<input name="name" autofocus value="'.h($B).'" data-maxlength="64" autocapitalize="off">')."\n",($pb?html_select("collation",array(""=>"(".'collation'.")")+$pb,$K["collation"]).doc_link(array('sql'=>"charset-charsets.html",'mariadb'=>"supported-character-sets-and-collations/",)):"")."\n",'<input type=\'submit\' value=\'Save\'>
';if(DB!="")echo"<input type='submit' name='drop' value='".'Drop'."'".confirm(sprintf('Drop %s?',DB)).">\n";elseif(!$_POST["add"]&&$_GET["db"]=="")echo
icon("plus","add[0]","+",'Add next')."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["call"])){$ba=($_GET["name"]?:$_GET["call"]);$Ui=(isset($_GET["callf"])?"FUNCTION":"PROCEDURE");$Qi=routine($_GET["call"],$Ui);page_header('Call'.": ".h($ba),$j,"#routines","",!$Qi);$Ae=array();$uh=array();foreach($Qi["fields"]as$r=>$k){if(substr($k["inout"],-3)=="OUT"&&JUSH=='sql')$uh[$r]="@".idf_escape($k["field"])." AS ".idf_escape($k["field"]);if(!$k["inout"]||preg_match('~^(IN|OUTPUT)~',$k["inout"]))$Ae[]=$r;}if(!$j&&$_POST){$Wa=array();foreach($Qi["fields"]as$w=>$k){$X="";if(in_array($w,$Ae)){$X=process_input($k);if($X===false)$X="''";if(isset($uh[$w]))connection()->query("SET @".idf_escape($k["field"])." = $X");}if(isset($uh[$w]))$Wa[]="@".idf_escape($k["field"]);elseif(in_array($w,$Ae))$Wa[]=$X;}$xa=implode(", ",$Wa);$H=(isset($_GET["callf"])||JUSH!="mssql"?(isset($_GET["callf"])?"SELECT ":"CALL ").(idx($Qi["returns"],"type")=="record"?"* FROM ":"").table($ba)."($xa)":"EXEC ".table($ba).($xa!=""?" $xa":""));$Nj=microtime(true);$I=connection()->multi_query($H);$la=connection()->affected_rows;echo
adminer()->selectQuery($H,$Nj,!$I);if(!$I)echo"<p class='error'>".adminer()->error()."\n";else{$f=connect();if($f)$f->select_db(DB);do{$I=connection()->store_result();if(is_object($I))print_select_result($I,$f);else
echo"<p class='message'>".lang_format(array('Routine has been called, %d row affected.','Routine has been called, %d rows affected.'),$la)." <span class='time'>".@date("H:i:s")."</span>\n";}while(connection()->next_result());if($uh)print_select_result(connection()->query("SELECT ".implode(", ",$uh)));}}echo'
<form action="" method="post">
';if($Ae){echo"<table class='layout'>\n";foreach($Ae
as$w){$k=$Qi["fields"][$w];$B=$k["field"];echo"<tr><th>".adminer()->fieldName($k);$Y=idx($_POST["fields"],$B);if($Y!=""){if($k["type"]=="set")$Y=implode(",",$Y);}input($k,$Y,idx($_POST["function"],$B,""));echo"\n";}echo"</table>\n";}echo'<p>
<input type=\'submit\' value=\'Call\'>
',input_token(),'</form>

',adminer()->commentValue($Ui,$Qi['comment']);}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$B=$_GET["name"];$K=$_POST;if($_POST&&!$j&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$qk=array();foreach($K["source"]as$w=>$X)$qk[$w]=$K["target"][$w];$K["target"]=$qk;}if(JUSH=="sqlite")$I=recreate_table($a,$a,array(),array(),array(" $B"=>($K["drop"]?"":" ".format_foreign_key($K))));else{$sa="ALTER TABLE ".table($a);$I=($B==""||queries("$sa DROP ".(JUSH=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($B)));if(!$K["drop"])$I=queries("$sa ADD".format_foreign_key($K));}queries_redirect(ME."table=".url_escape($a),($K["drop"]?'Foreign key has been dropped.':($B!=""?'Foreign key has been altered.':'Foreign key has been created.')),$I);if(!$K["drop"])$j='Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.';}$Ig=false;if(!$_POST&&$B!=""){$Ed=foreign_keys($a);$K=idx($Ed,$B,array());$Ig=!$K;}page_header(($B!=""?'Alter foreign key':'Create foreign key'),$j,array("table"=>$a),h($B!=""?$B:$a),$Ig);if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=array();else$K["source"][]="";}elseif($B!="")$K["source"][]="";else{$K["table"]=$a;$K["source"]=array("");}echo'
<form action="" method="post">
';$Ej=array_keys(fields($a));if($K["db"]!="")connection()->select_db($K["db"]);if($K["ns"]!=""){$qh=get_schema();set_schema($K["ns"]);}$yi=array_keys(array_filter(table_status('',true),function(array$S){return!$S["dependent"]&&fk_support($S);}));$qk=array_keys(fields(in_array($K["table"],$yi)?$K["table"]:reset($yi)));$b=on('change','foreignChange');echo"<p><label>".'Target table'.": ".html_select("table",$yi,$K["table"],$b)."</label>\n";if(JUSH!="sqlite"){$Zb=array();foreach(adminer()->databases()as$h){if(!information_schema($h))$Zb[]=$h;}echo"<label>".'DB'.": ".html_select("db",$Zb,$K["db"]!=""?$K["db"]:$_GET["db"],$b)."</label>";}echo
input_hidden("change-js"),'<noscript><p><input type=\'submit\' name=\'change\' value=\'Change\'></noscript>
<table>
<thead><tr><th id="label-source">Source<th id="label-target">Target<tbody>
';$ff=0;foreach($K["source"]as$w=>$X){echo"<tr>","<td>".html_select("source[".(+$w)."]",array(-1=>"")+$Ej,$X,($ff==count($K["source"])-1?on('change','foreignAddRow'):""),"label-source"),"<td>".html_select("target[".(+$w)."]",$qk,idx($K["target"],$w),"","label-target");$ff++;}echo'</table>
<p>
<label>ON DELETE: ',html_select("on_delete",array(-1=>"")+explode("|",driver()->onActions),$K["on_delete"]),'</label>
<label>ON UPDATE: ',html_select("on_update",array(-1=>"")+explode("|",driver()->onActions),$K["on_update"]),'</label>
',(support("deferrable")?html_select("deferrable",array('NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'),$K["deferrable"]).' ':''),doc_link(array('sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"foreign-keys/",)),'<p>
<input type=\'submit\' value=\'Save\'>
<noscript><p><input type=\'submit\' name=\'add\' value=\'Add column\'></noscript>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$rh="VIEW";if(JUSH=="pgsql"&&$a!=""){$P=table_status1($a);$rh=strtoupper($P["Engine"]);}if($_POST&&!$j){$B=trim($K["name"]);$za=" AS\n$K[select]";$_=ME."table=".url_escape($B);$dg='View has been altered.';$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$B&&JUSH!="sqlite"&&$U=="VIEW"&&$rh=="VIEW")query_redirect((JUSH=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($B).$za,$_,$dg);else{$uk="adminer_".uniqid();drop_create("DROP $rh ".table($a),"CREATE $U ".table($B).$za,"DROP $U ".table($B),"CREATE $U ".table($uk).$za,"DROP $U ".table($uk),($_POST["drop"]?substr(ME,0,-1):$_),'View has been dropped.',$dg,'View has been created.',$a,$B);}}$Ig=false;if(!$_POST&&$a!=""){$K=view($a);$Ig=!$K["select"];$K["name"]=$a;$K["materialized"]=($rh!="VIEW");if(!$j)$j=adminer()->error();}page_header(($a!=""?'Alter view':'Create view'),$j,array("table"=>$a),h($a),$Ig);echo'
<form action="" method="post">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',(support("materializedview")?" ".checkbox("materialized",1,$K["materialized"],'Materialized view'):""),'<p>';textarea("select",$K["select"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["event"])){$aa=$_GET["event"];$Se=array("YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND");$Pj=array("ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE");$K=$_POST;if($_POST&&!$j){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($aa),substr(ME,0,-1),'Event has been dropped.');elseif(in_array($K["INTERVAL_FIELD"],$Se)&&isset($Pj[$K["STATUS"]])){$Yi="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($aa!=""?'Event has been altered.':'Event has been created.'),queries(($aa!=""?"ALTER EVENT ".idf_escape($aa).$Yi.($aa!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$Yi)."\n".$Pj[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}$Ig=false;if(!$K&&$aa!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($aa));$Ig=!$L;$K=reset($L);}page_header(($aa!=""?'Alter event'.": ".h($aa):'Create event'),$j,"#events","",$Ig);echo'
<form action="" method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="',h($K["EVENT_NAME"]),'" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="',h("$K[EXECUTE_AT]$K[STARTS]"),'">
<tr><th title="datetime">End<td><input name="ENDS" value="',h($K["ENDS"]),'">
<tr><th>Every
<td><input type="number" name="INTERVAL_VALUE" value="',h($K["INTERVAL_VALUE"]),'" class="size"> ',html_select("INTERVAL_FIELD",$Se,$K["INTERVAL_FIELD"]),'<tr><th>Status<td>',html_select("STATUS",$Pj,$K["STATUS"]),'<tr><th>Comment<td><input name="EVENT_COMMENT" value="',h($K["EVENT_COMMENT"]),'" data-maxlength="64">
<tr><th><td>',checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",'On completion preserve'),'</table>
<p>';textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($aa!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$aa)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["procedure"])){$ba=($_GET["name"]?:$_GET["procedure"]);$Qi=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])&&!$j){foreach($K["fields"]as$w=>$k){if($k["field"]=="")unset($K["fields"][$w]);}$Wg=routine($_GET["procedure"],$Qi);$Ug=($Wg?routine_id($ba,$Wg):"");$Bg=routine_id($K["name"],$K);$Ib=create_routine($Qi,$K);$_=substr(ME,0,-1);$dg='Routine has been altered.';if(!$_POST["drop"]&&$Ug==$Bg&&connection()->flavor!="mysql")queries_redirect($_,$dg,queries(substr_replace($Ib,(JUSH=="mssql"?' OR ALTER':' OR REPLACE'),6,0)));else{$uk="adminer_".uniqid();drop_create("DROP $Qi $Ug",$Ib,"DROP $Qi $Bg",create_routine($Qi,array("name"=>$uk)+$K),"DROP $Qi ".routine_id($uk,$K),$_,'Routine has been dropped.',$dg,'Routine has been created.',$ba,$K["name"]);}}$Ig=false;if(!$_POST&&$ba!=""){$K=routine($_GET["procedure"],$Qi);$Ig=!$K;$K["name"]=$ba;}page_header(($ba!=""?(isset($_GET["function"])?'Alter function':'Alter procedure').": ".h($ba):(isset($_GET["function"])?'Create function':'Create procedure')),$j,"#routines","",$Ig);if(!$_POST&&$ba=="")$K["language"]="sql";$pb=(JUSH=="sql"?flat_collations():array());$Ri=routine_languages();echo($pb?"<datalist id='collations'>".optionlist($pb)."</datalist>":""),'
<form action="" method="post" id="form">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',($Ri?"<label>".'Language'.": ".html_select("language",array_keys($Ri),$K["language"],on('change','routineLanguage',$Ri))."</label>\n":""),'<input type=\'submit\' value=\'Save\'>
';$Si=strtolower($Qi);echo
doc_link(array('sql'=>"create-procedure.html",'mariadb'=>"create-$Si/",),"?"),'<div class="scrollable">
<table id="edit-fields" class="nowrap">
';edit_fields($K["fields"],$pb,$Qi);if(isset($_GET["function"])){echo"<tr><td>".'Return type';edit_type("returns",(array)$K["returns"],$pb,array(),(JUSH=="pgsql"?array("void","trigger"):array()));}echo'</table>
',script("editFields();"),'</div>
<p>';textarea("definition",$K["definition"],20,80,($Ri[$K["language"]]?:JUSH));echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($ba!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$ba)),'>
';$Ti=routine_options($Qi);if($Ti){$hh=false;foreach($Ti
as$w=>$zl){$i=($zl?reset($zl):"");$K["options"][$w]=idx($K["options"],$w,$i);if($K["options"][$w]!=$i)$hh=true;}print_fieldset("options",'Options',$hh);echo"<table class='layout'>\n";foreach($Ti
as$w=>$zl){$nf="label-option-$w";$Bk=str_replace("_"," ",$w);$M=array();foreach($zl
as$Y)$M[$Y]=(strpos($Y,"$Bk ")===0?substr($Y,strlen($Bk)+1):$Y);echo"<tr><th id='$nf'>$Bk<td>".($M?html_select("options[$w]",$M,$K["options"][$w],"",$nf):"<input name='options[$w]' value='".h($K["options"][$w])."' aria-labelledby='$nf' autocapitalize='off'>")."\n";}echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["check"])){$a=$_GET["check"];$B="$_GET[name]";$K=$_POST;if($K&&!$j){$_=ME."table=".url_escape($a);$gg='Check has been dropped.';$eg='Check has been altered.';$fg='Check has been created.';if(JUSH=="sqlite")queries_redirect($_,($K["drop"]?$gg:($B!=""?$eg:$fg)),recreate_table($a,$a,array(),array(),array(),"",array(),"$B",($K["drop"]?"":$K["clause"])));else{$sa="ALTER TABLE ".table($a);$db=" CHECK ($K[clause])";$uk="adminer_".uniqid();drop_create("$sa DROP CONSTRAINT ".idf_escape($B),"$sa ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"").$db,"$sa DROP CONSTRAINT ".idf_escape($K["name"]),"$sa ADD CONSTRAINT ".idf_escape($uk).$db,"$sa DROP CONSTRAINT ".idf_escape($uk),$_,$gg,$eg,$fg,$B,$K["name"]);}}$Ig=false;if(!$K){$gb=driver()->checkConstraints($a);$Ig=($B!=""&&!$gb[$B]);$K=array("name"=>$B,"clause"=>$gb[$B]);}page_header(($B!=""?'Alter check':'Create check'),$j,array("table"=>$a),h($B!=""?$B:$a),$Ig);echo'
<form action="" method="post">
<p>';if(JUSH!="sqlite")echo'Name'.': <input name="name" value="'.h($K["name"]).'" data-maxlength="64" autocapitalize="off"> ';echo
doc_link(array('sql'=>"create-table-check-constraints.html",'mariadb'=>"constraint/",),"?"),'<p>';textarea("clause",$K["clause"]);echo'<p><input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$B="$_GET[name]";$Qk=trigger_options();$K=trigger($B,$a);$Ig=($B!=""&&!$K);$K+=array("Trigger"=>$a."_bi");if($_POST){if(!$j&&in_array($_POST["Timing"],$Qk["Timing"])&&in_array($_POST["Event"],$Qk["Event"])&&in_array($_POST["Type"],$Qk["Type"])){$Yg=" ON ".table($a);$Ac="DROP TRIGGER ".idf_escape($B).(JUSH=="pgsql"?$Yg:"");$_=ME."table=".url_escape($a);if($_POST["drop"])query_redirect($Ac,$_,'Trigger has been dropped.');else{if($B!="")queries($Ac);queries_redirect($_,($B!=""?'Trigger has been altered.':'Trigger has been created.'),queries(create_trigger($Yg,$_POST)));if($B!="")queries(create_trigger($Yg,$K+array("Type"=>reset($Qk["Type"]))));}}$K=$_POST;}page_header(($B!=""?'Alter trigger':'Create trigger'),$j,array("table"=>$a),h($B!=""?$B:$a),$Ig);$Pk=on('change','triggerChange',"^".preg_quote($a,"/")."_[ba][iud]$",$a);echo'
<form action="" method="post" id="form">
<table class="layout">
<tr><th>Time
<td>',html_select("Timing",$Qk["Timing"],$K["Timing"],$Pk),'<tr><th>Event<td>',html_select("Event",$Qk["Event"],$K["Event"],$Pk),(in_array("UPDATE OF",$Qk["Event"])?" <input name='Of' value='".h($K["Of"])."' class='hidden'>":""),'<tr><th>Type<td>',html_select("Type",$Qk["Type"],$K["Type"]),'<tr><th>Name<td><input name="Trigger" value="',h($K["Trigger"]),'" data-maxlength="64" autocapitalize="off">
</table>
',script("fire(qs('#form')['Timing'], 'change');"),'<p>';textarea("Statement",$K["Statement"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["user"])){function
grant($Rd,array$mi,$d,$Yg){if(!$mi)return
true;if($mi==array("ALL PRIVILEGES","GRANT OPTION"))return($Rd=="GRANT"?queries("$Rd ALL PRIVILEGES$Yg WITH GRANT OPTION"):queries("$Rd ALL PRIVILEGES$Yg")&&queries("$Rd GRANT OPTION$Yg"));return
queries("$Rd ".preg_replace('~(GRANT OPTION)\([^)]*\)~','\1',implode("$d, ",$mi).$d).$Yg);}$da=$_GET["user"];$mi=array(""=>array("All privileges"=>""));foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$Cb)$mi[$Cb=="File access on server"?"Server Admin":$Cb][$K["Privilege"]]=$K["Comment"];}unset($mi["Server Admin"]["Usage"]);foreach($mi["Tables"]as$w=>$X)unset($mi["Databases"][$w]);$Ag=array();if($_POST){foreach($_POST["objects"]as$w=>$X)$Ag[$X]=(array)$Ag[$X]+idx($_POST["grants"],$w,array());}$Sd=array();$I=(isset($_GET["host"])?connection()->query("SHOW GRANTS FOR ".q($da)."@".q($_GET["host"])):null);$Ig=(isset($_GET["host"])&&!$I);if($I){while($K=$I->fetch_row()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$A)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$A[1],$Lf,PREG_SET_ORDER)){foreach($Lf
as$X){if($X[1]!="USAGE")$Sd["$A[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$Sd["$A[2]$X[2]"]["GRANT OPTION"]=true;}}}}if($_POST&&!$j){$Xg=(isset($_GET["host"])?q($da)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $Xg",ME."privileges=",'User has been dropped.');else{$Eg=q($_POST["user"])."@".q($_POST["host"]);$Kh=$_POST["pass"];$Kb=false;$I=true;if($Xg!=$Eg){$Kb=queries("CREATE USER $Eg IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($Kh));$I=$Kb;}elseif($Kh!="")$I=queries("SET PASSWORD FOR $Eg = ".(min_version(8,99)||$_POST["hashed"]?q($Kh):"PASSWORD(".q($Kh).")"));if($I){$Mi=array();foreach($Ag
as$Mg=>$Rd){if(isset($_GET["grant"]))$Rd=array_filter($Rd);$Rd=array_keys($Rd);if(isset($_GET["grant"]))$Mi=array_diff(array_keys(array_filter($Ag[$Mg],'strlen')),$Rd);elseif($Xg==$Eg){$Tg=array_keys((array)$Sd[$Mg]);$Mi=array_diff($Tg,$Rd);$Rd=array_diff($Rd,$Tg);unset($Sd[$Mg]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$Mg,$A)&&(!grant("REVOKE",$Mi,$A[2]," ON $A[1] FROM $Eg")||!grant("GRANT",$Rd,$A[2]," ON $A[1] TO $Eg"))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($Xg!=$Eg)queries("DROP USER $Xg");elseif(!isset($_GET["grant"])){foreach($Sd
as$Mg=>$Mi){if(preg_match('~^(.+)(\(.*\))?$~U',$Mg,$A))grant("REVOKE",array_keys($Mi),$A[2]," ON $A[1] FROM $Eg");}}}if($I&&!Queries::$queries)redirect(ME."privileges=");queries_redirect(ME."privileges=",(isset($_GET["host"])?'User has been altered.':'User has been created.'),$I);if($Kb)connection()->query("DROP USER $Eg");}}page_header((isset($_GET["host"])?'Username'.": ".h("$da@$_GET[host]"):'Create user'),$j,array("privileges"=>array('','Privileges')),"",$Ig);$K=$_POST;if($K)$Sd=$Ag;else{$K=$_GET+array("host"=>get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)"));$Sd[(DB==""||$Sd?"":idf_escape(addcslashes(DB,"%_\\"))).".*"]=array();}echo'<form action="" method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="',h($K["host"]),'" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="',h($K["user"]),'" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="',h($K["pass"]),'" autocomplete="new-password">
',($K["hashed"]?"":script("typePassword(qs('#pass'));")),(min_version(8,99)?"":checkbox("hashed",1,$K["hashed"],'Hashed',on('click','hashedClick'))),'</table>

',"<table class='odds'>\n","<thead><tr><th colspan='2'>".'Privileges'.doc_link(array('sql'=>"grant.html#priv_level"));$r=0;foreach($Sd
as$Mg=>$Rd){echo'<th>'.($Mg!="*.*"?"<input name='objects[$r]' value='".h($Mg)."' size='10' autocapitalize='off'>":input_hidden("objects[$r]","*.*")."*.*");$r++;}echo"<tbody>\n";foreach(array(""=>"","Server Admin"=>'Server',"Databases"=>'Database',"Tables"=>'Table',"Procedures"=>'Routine',)as$Cb=>$ic){foreach((array)$mi[$Cb]as$li=>$tb){echo"<tr><td".($ic?">$ic<td":" colspan='2'").' lang="en" title="'.h($tb).'">'.h($li);$r=0;foreach($Sd
as$Mg=>$Rd){$B="'grants[$r][".h(strtoupper($li))."]'";$Y=$Rd[strtoupper($li)];if($Cb=="Server Admin"&&$Mg!=(isset($Sd["*.*"])?"*.*":".*"))echo"<td>";elseif(isset($_GET["grant"]))echo"<td><select name=$B><option><option value='1'".($Y?" selected":"").">".'Grant'."<option value='0'".($Y=="0"?" selected":"").">".'Revoke'."</select>";else
echo"<td align='center'><label class='block'>","<input type='checkbox' name=$B value='1'".($Y?" checked":"").($li=="All privileges"?" id='grants-$r-all'":($li=="Grant option"?"":on('click','grantsClick',"grants-$r-all"))).">","</label>";$r++;}}}echo"</table>\n",'<p>
<input type=\'submit\' value=\'Save\'>
';if(isset($_GET["host"]))echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',"$da@$_GET[host]")),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST&&!$j){$mf=0;foreach((array)$_POST["kill"]as$X){if(adminer()->killProcess($X))$mf++;}queries_redirect(ME."processlist=",lang_format(array('%d process has been killed.','%d processes have been killed.'),$mf),$mf||!$_POST["kill"]);}}page_header('Process list',$j);echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds"',on('click','tableClick').on('dblclick','tableClick'),'>
';$r=-1;foreach(adminer()->processList()as$r=>$K){if(!$r){echo"<thead><tr lang='en'>".(support("kill")?"<td class='hover'>":"");foreach($K
as$w=>$X)echo"<th>$w".doc_link(array('sql'=>"show-processlist.html#processlist_".strtolower($w),));echo"<tbody>\n";}echo"<tr>".(support("kill")?"<td class='hover'>".checkbox("kill[]",$K[JUSH=="sql"?"Id":"pid"],0):"");foreach($K
as$w=>$X)echo"<td>".($X!=""&&((JUSH=="sql"&&$w=="Info"&&preg_match("~Query|Killed~",$K["Command"]))||(JUSH=="pgsql"&&$w=="query")||(JUSH=="oracle"&&$w=="sql_text"))?"<code class='jush-".JUSH."' data-full='".h($X)."'>".shorten_utf8($X,100,"</code>").' <a href="'.h(($K["db"]!=""?preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($K["db"])."&":ME)."sql=".url_escape($X)).'">'.'Clone'.'</a>'.' '.copy_icon():h($X));echo"\n";}echo'</table>
</div>
<p>
',script("copyCode(qsl('table'));");if(support("kill"))echo
format_number($r+1)."/".sprintf('%d in total',max_connections()),"<p><input type='submit' value='".'Kill'."'>\n";echo
input_token(),'</form>
',script("tableCheck();");}elseif($_GET["select"]!=""){$a=$_GET["select"];$S=table_status1($a);$v=indexes($a);$l=fields($a);$Ed=column_foreign_keys($a);$Sg=$S["Oid"];$Oi=array();$d=array();$ej=array();$jh=array();$xk=null;foreach($l
as$w=>$k){$B=adminer()->fieldName($k);$yg=html_entity_decode(strip_tags($B),ENT_QUOTES);if(isset($k["privileges"]["select"])&&$B!=""){$d[$w]=$yg;if(is_shortable($k))$xk=adminer()->selectLengthProcess();}if(isset($k["privileges"]["where"])&&$B!="")$ej[$w]=$yg;if(isset($k["privileges"]["order"])&&$B!="")$jh[$w]=$yg;$Oi+=$k["privileges"];}list($M,$q)=adminer()->selectColumnsProcess($d,$v);$M=array_unique($M);$q=array_unique($q);$Ze=count($q)<count($M);$Z=adminer()->selectSearchProcess($l,$v,$S);$D=adminer()->selectOrderProcess($l,$v);$y=adminer()->selectLimitProcess();if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$cl=>$K){$za=convert_field($l[key($K)]);$M=array($za?:idf_escape(key($K)));$Z[]=where_check(bracket_escape($cl,true),$l);$J=driver()->select($a,$M,$Z,$M);if($J)echo
first($J->fetch_row());}exit;}$ii=$el=array();foreach($v
as$u){if($u["type"]=="PRIMARY"){$ii=array_flip($u["columns"]);$el=($M?$ii:array());foreach($el
as$w=>$X){if(in_array(idf_escape($w),$M))unset($el[$w]);}break;}}if($Sg&&!$ii){$ii=$el=array($Sg=>0);$v[]=array("type"=>"PRIMARY","columns"=>array($Sg));}if($_POST&&!$j){$Jl=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$gb=array();foreach($_POST["check"]as$db)$gb[]=where_check($db,$l);$Jl[]="((".implode(") OR (",$gb)."))";}$Ll=$Jl;$Jl=($Jl?"\nWHERE ".implode(" AND ",$Jl):"");if($_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers($a);adminer()->dumpTable($a,"");$hj=($M?:array("*"));$Eb=convert_fields($d,$l,$M);if($Eb)$hj[]=substr($Eb,2);$H="";if(is_array($_POST["check"])&&!$ii){$Jd=implode(", ",$hj)."\nFROM ".table($a);$Vd=($q&&$Ze?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):"");$Zk=array();foreach($_POST["check"]as$X)$Zk[]="(SELECT".limit($Jd,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l).$Vd,1).")";$H=implode(" UNION ALL ",$Zk);}adminer()->dumpData($a,"table",$H,$hj,$Ll,($Ze?$q:array()),$D);adminer()->dumpFooter();exit;}if(!adminer()->selectEmailProcess($Z,$Ed)){if($_POST["save"]||$_POST["delete"]){$I=true;$la=0;$Na=false;$O=array();if(!$_POST["delete"]){foreach($l
as$B=>$X){$t=bracket_escape($B);if(isset($_POST["fields"][$t])||$_FILES["fields-$t"]){$X=process_input($l[$B]);if($X!==null&&($_POST["clone"]||$X!==false))$O[idf_escape($B)]=($X!==false?$X:idf_escape($B));}}}if($_POST["delete"]||$O){$H=($_POST["clone"]?"INTO ".table($a)." (".implode(", ",array_keys($O)).")\nSELECT ".implode(", ",$O)."\nFROM ".table($a):"");if($_POST["all"]||($ii&&is_array($_POST["check"]))||$Ze){$I=($_POST["delete"]?driver()->delete($a,$Jl):($_POST["clone"]?queries("INSERT $H$Jl".driver()->insertReturning($a)):driver()->update($a,$O,$Jl)));$la=connection()->affected_rows;if(is_object($I))$la+=$I->num_rows;}else{$Na=count((array)$_POST["check"])>1&&driver()->begin();foreach((array)$_POST["check"]as$X){$Il="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l);$I=($_POST["delete"]?driver()->delete($a,$Il,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$Il)):driver()->update($a,$O,$Il,1)));if(!$I)break;$la+=connection()->affected_rows;}if($Na&&$I&&!driver()->commit())$I=false;}}$dg=lang_format(array('%d item has been affected.','%d items have been affected.'),$la);if($_POST["clone"]&&$I&&$la==1){$tf=last_id($I);if($tf)$dg=sprintf('Item%s has been inserted.'," $tf");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page|next":""),$dg,$I);if($Na)driver()->rollback();if(!$_POST["delete"]){$ai=(array)$_POST["fields"];edit_form($a,array_intersect_key($l,$ai),$ai,!$_POST["clone"],$j);page_footer();exit;}}elseif(!$_POST["import"]){$I=true;$la=0;$Na=count((array)$_POST["val"])>1&&driver()->begin();foreach((array)$_POST["val"]as$cl=>$K){$O=array();foreach($K
as$w=>$X){$w=bracket_escape($w,true);$O[idf_escape($w)]=(preg_match('~char|text~',$l[$w]["type"])||$X!=""?adminer()->processInput($l[$w],$X):"NULL");}$I=driver()->update($a,$O," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check(bracket_escape($cl,true),$l),($Ze||$ii?0:1)," ");if(!$I)break;$la+=connection()->affected_rows;}if($Na)$I=$I&&driver()->commit();queries_redirect(remove_from_uri(),lang_format(array('%d item has been affected.','%d items have been affected.'),$la),$I);if($Na)driver()->rollback();}else{save_settings(array("format"=>$_POST["separator"]),"adminer_import");$td=get_file("csv_file",true);if(!is_string($td))$j=upload_error($td);elseif(!preg_match('~~u',$td))$j='File must be in UTF-8 encoding.';else{$qb=array_keys($l);$mj=($_POST["separator"]=="csv"?",":($_POST["separator"]=="tsv"?"\t":";"));$Ob=parse_csv($td,$mj);$la=count($Ob);driver()->begin();$L=array();foreach($Ob
as$w=>$zl){if(!$w&&!array_diff($zl,$qb)){$qb=$zl;$la--;}else{$O=array();foreach($zl
as$r=>$mb)$O[idf_escape($qb[$r])]=($mb==""&&$l[$qb[$r]]["null"]?"NULL":q(csv_value($mb)));$L[]=$O;}}$I=(!$L||driver()->insertUpdate($a,$L,$ii));if($I)driver()->commit();queries_redirect(remove_from_uri("page|next"),lang_format(array('%d row has been imported.','%d rows have been imported.'),$la),$I);driver()->rollback();}}}}$fk=adminer()->tableName($S);if(is_ajax()){page_headers();ob_start();}else
page_header('Select'.": $fk",$j,array(),"",(!$l&&support("table")));$O=null;if(isset($Oi["insert"])||!support("table")){$O="";foreach((array)$_GET["where"]as$X){$Y=$X["val"];if(is_array($Y))$Y=(count($Y)==1&&preg_match('~^val-(.*)~s',reset($Y),$A)?$A[1]:"");if($X["col"]!=""&&$Y!=""&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$Y)))))$O
.="&set[".url_escape(bracket_escape($X["col"]))."]=".url_escape($Y);}}adminer()->selectLinks($S,$O);if(!$d&&support("table"))echo"<p class='error'>".'Unable to select the table.'."\n";else{echo"<form action='' id='form'>\n","<div hidden>";hidden_fields_get();echo(DB!=""?input_hidden("db",DB).(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""):""),input_hidden("select",$a),"</div>\n";adminer()->selectColumnsPrint($M,$d);adminer()->selectSearchPrint($Z,$ej,$v,$S);adminer()->selectOrderPrint($D,$jh,$v);adminer()->selectLimitPrint($y);if($xk!==null)adminer()->selectLengthPrint($xk);adminer()->selectActionPrint($v);echo"</form>\n";foreach((array)$_GET["where"]as$X){if($X["op"]=="SQL"&&!in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"))){echo"<p class='error'>".'Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.'."\n";page_footer();exit;}}$E=$_GET["page"];$Hd=null;if($E=="last"){$Hd=get_val(count_rows($a,$Z,$Ze,$q));$E=floor(max(0,intval($Hd)-1)/$y);}$gj=$M;$Ud=$q;if(!$gj){$gj[]="*";$Eb=convert_fields($d,$l,$M);if($Eb)$gj[]=substr($Eb,2);}foreach($M
as$w=>$X){$k=$l[idf_unescape($X)];if($k&&($za=convert_field($k)))$gj[$w]="$za AS $X";}if(JUSH=="pgsql"||JUSH=="mssql"){foreach((array)$_GET["columns"]as$w=>$X){if(isset($gj[$w])&&$X["fun"])$gj[$w].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$Ze&&$el){foreach($el
as$w=>$X){$gj[]=idf_escape($w);if($Ud)$Ud[]=idf_escape($w);}}$I=driver()->select($a,$gj,$Z,$Ud,$D,$y,$E,true);if(!is_object($I))echo"<p class='error'>".(adminer()->error()?:'Unknown error.')."\n";else{if(JUSH=="mssql"&&$E)$I->seek($y*$E);$Lc=array();$L=array();while($K=$I->fetch_assoc()){if($E&&JUSH=="oracle")unset($K["RNUM"]);$L[]=$K;}$fe=($y&&(support("cursor")?$_GET["next"]!="":count($L)>=$y));if(is_ajax()&&$fe)header("X-Next-Page: ".pagination_href($E+1));if($_GET["modify"]&&$L){$Uf=max_input_vars(count($L[0])+1,20);echo($Uf&&count($L)>$Uf?"<p class='error'>".max_input_vars_error()."\n":"");}echo"<form action='' method='post' enctype='multipart/form-data'".on_upload_progress($kl).">\n";if($_GET["page"]!="last"&&$y&&$q&&$Ze&&JUSH=="sql")$Hd=get_val(" SELECT FOUND_ROWS()");if(!$L)echo"<p class='message'>".'No rows.'."\n";else{$Ja=adminer()->backwardKeys($a,$fk);$Li=array();reset($M);foreach($L[0]as$w=>$X){if(!isset($el[$w])){$X=idx($_GET["columns"],key($M))?:array();$Li[$w]=array("fun"=>$X["fun"],"col"=>($M?$X["col"]:$w));next($M);}}echo"<div class='scrollable'>","<table id='table' class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown').">\n","<thead><tr>".(!$q&&$M?"":"<td class='hover check'><input type='checkbox' id='all-page' class='jsonly' title='".'All rows on this page'."'".on('click','formCheck','^check').">");$zg=array();$vi=1;foreach($Li
as$w=>$X){$k=$l[$X["col"]];$B=($k?adminer()->fieldName($k,$vi):($X["fun"]?"*":h($w)));if($B!=""){$vi++;$zg[$w]=$B;$c=idf_escape($w);$se=remove_from_uri('(order|desc)[^=]*|page|next').'&order[0]='.url_escape($w);$ic="&desc[0]=1";$Bj=preg_replace('~ DESC( NULLS LAST)?$~','',$D[0]);$Dj=($Bj==$c||$Bj==$w);echo"<th id='th[".h(bracket_escape($w))."]'".($Dj?" aria-sort='".($Bj==$D[0]?"ascending":"descending")."'":"").">";$Nd=apply_sql_function(h($X["fun"]),$B);$Cj=isset($k["privileges"]["order"])||$X["fun"];echo($Cj?"<a href='".h($se.($Dj&&$Bj==$D[0]?$ic:''))."'>$Nd</a>":$Nd);$cg=($Cj?"<a href='".h($se.$ic)."' title='".'descending'."' class='text'> ↓</a>":'');if(!$X["fun"]&&isset($k["privileges"]["where"]))$cg
.="<a href='#fieldset-search' title='".'Search'."' class='text jsonly'".on('click','selectSearch',$w)."> =</a>";echo($cg?"<span class='column'>$cg</span>":"");}}$_f=array();if($_GET["modify"]){foreach($L
as$K){foreach($K
as$w=>$X)$_f[$w]=max($_f[$w],min(40,utf8_length($X)));}}echo($Ja?"<th>".'Relations':"")."<tbody>\n";if(is_ajax())ob_end_clean();foreach(adminer()->rowDescriptions($L,$Ed)as$wg=>$K){$bl=unique_array($L[$wg],$v);if(!$bl){$bl=array();foreach($L[$wg]as$w=>$X){if(!in_array(idx(idx($Li,$w,array()),"fun"),driver()->grouping))$bl[$w]=$X;}}$cl="";$r=0;foreach($bl
as$w=>$X){$Ki=idx($Li,$w,array());$Nd=idx($Ki,"fun","");$mb=($Nd?$Ki["col"]:$w);$k=(array)$l[$mb];$Ye=is_blob($k);if(!$Nd&&(JUSH=="sql"||JUSH=="pgsql")&&($Ye||preg_match('~'.text_type().'~',$k["type"]))&&strlen($X)>64){$Nd="md5";$X=md5($Ye?(string)driver()->value($X,$k):$X);}if($Nd){$cl
.="&fun[$r]=".url_escape($Nd)."&col[$r]=".url_escape($mb).($X!==null?"&val[$r]=".url_escape($X===false?"f":$X):"");$r++;}else$cl
.="&".($X!==null?"where[".url_escape(bracket_escape($mb))."]=".url_escape($X===false?"f":$X):"null[]=".url_escape($mb));}echo"<tr>".(!$q&&$M?"":"<td class='hover check'>".($Ze||information_schema(DB)?"":"<a href='".h(ME."edit=".url_escape($a).$cl)."' class='edit'>".'edit'."</a> ").checkbox("check[]",substr($cl,1),in_array(substr($cl,1),(array)$_POST["check"])));foreach($K
as$w=>$X){if(isset($zg[$w])){$Nd=$Li[$w]["fun"];$mb=$Li[$w]["col"];$k=(array)$l[$w];if($X!=""&&(!isset($Lc[$w])||$Lc[$w]!=""))$Lc[$w]=(is_mail($X)?$zg[$w]:"");$z="";if(is_blob($k)&&$X!="")$z=ME.'download='.url_escape($a).'&field='.url_escape($w).$cl;if(!$z&&$X!==null){foreach((array)$Ed[$w]as$n){if(count($Ed[$w])==1||end($n["source"])==$w){$z="";foreach($n["source"]as$r=>$Ej)$z
.=where_link($r,$n["target"][$r],$L[$wg][$Ej]);$z=($n["db"]!=""?preg_replace('~([?&]db=)[^&]+~','\1'.url_escape($n["db"]),ME):ME).'select='.url_escape($n["table"]).$z;if($n["ns"])$z=preg_replace('~([?&]ns=)[^&]+~','\1'.url_escape($n["ns"]),$z);if(count($n["source"])==1)break;}}}if($Nd=="count"&&$mb==""){$z=ME."select=".url_escape($a);$r=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$bl))$z
.=where_link($r++,$W["col"],$W["val"],$W["op"]);}foreach($bl
as$if=>$W){if(idx(idx($Li,$if,array()),"fun")){$z="";break;}$z
.=where_link($r++,$if,$W);}}$te=select_value($X,$z,$k,$xk);$t=bracket_escape($cl);$s=h("val[$t][".bracket_escape($w)."]");$ci=idx(idx($_POST["val"],$t),bracket_escape($w));$hl=idx($k["privileges"],"update");$Hc=!is_array($K[$w])&&!is_blob($k)&&is_utf8($X)&&$L[$wg][$w]==$X&&!$Nd&&!$k["generated"]&&$hl;$U=($Nd=="min"||$Nd=="max"?$l[$mb]["type"]:$k["type"]);$wk=preg_match('~text|json|lob~',$U);$af=preg_match(number_type(),$U)||preg_match('~^(avg|ceil|char_length|count|count distinct|floor|len|length|round|sum|time_to_sec)$~',$Nd);echo"<td id='$s'".($af&&($X===null||is_numeric(strip_tags($te))||$U=="money")?" class='number'":"");if(($_GET["modify"]&&$Hc&&$X!==null)||$ci!==null){$ae=h($ci!==null?$ci:$X);echo">".($wk?"<textarea name='$s' cols='30' rows='".(substr_count($X,"\n")+1)."'>$ae</textarea>":"<input name='$s' value='$ae' size='$_f[$w]'>");}else{$If=strpos($te,"<i>…</i>");echo($hl?" data-text='".($If?2:($wk?1:0))."'".($Hc?"":" data-warning='".'Use the edit link to modify this value.'."'"):"").">$te";}}}if($Ja)echo"<td>";adminer()->backwardKeysPrint($Ja,$L[$wg]);echo"</tr>\n";}if(is_ajax())exit;echo"</table>\n","</div>\n";}if(!is_ajax()){$ka=get_settings("adminer_import");if($L||$E||$fe){$ad=true;if($_GET["page"]!="last"){if(!$y||(count($L)<$y&&($L||!$E)))$Hd=($E?$E*$y:0)+count($L);elseif(JUSH!="sql"||!$Ze){$Hd=($Ze?false:found_rows($S,$Z));if(intval($Hd)<max(1e4,2*($E+1)*$y))$Hd=first(slow_query(count_rows($a,$Z,$Ze,$q)));elseif(JUSH=='sql'||JUSH=='pgsql')$ad=false;}}if(!support("cursor"))$fe=(($Hd===false?count($L)+1:$Hd-$E*$y)>$y);$xh=($y&&($fe||$E));if($xh)echo($fe?'<p><a href="'.h(pagination_href($E+1)).'" class="loadmore"'.on('click','selectLoadMore','Loading…').'>'.'Load more data'.'</a>':''),"\n";echo"<div class='footer'><div>\n";if($xh){$Sf=($Hd===false?$E+($L?(count($L)>=$y?2:1):0):floor(($Hd-1)/$y));echo"<fieldset><legend>".'Page'."</legend>";if(!support("cursor")){echo
pagination(0,$E).($E>5?" …":"");for($r=max(1,$E-4);$r<min($Sf,$E+5);$r++)echo
pagination($r,$E);if($Sf>0)echo($E+5<$Sf?" …":""),($ad&&$Hd!==false?pagination($Sf,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$Sf'>".'last'."</a>");}else
echo
pagination(0,$E).($E>1?" …":""),($E?pagination($E,$E):""),($fe?pagination($E+1,$E)." …":"");echo"</fieldset>\n";}echo"<fieldset>","<legend>".'Whole result'."</legend>";$qc=($ad?"":"~ ").$Hd;$nf=($Hd!==false?($ad?"":"~ ").lang_format(array('%d row','%d rows'),$Hd):"");echo
checkbox("all",1,0,$nf,on('click','countRows',$qc))."\n","</fieldset>\n";if(adminer()->selectCommandPrint())echo'<fieldset',($_GET["modify"]?'':" title='".'Ctrl+click on a value to modify it.'."'"),'>
<legend><a href=\'',h($_GET["modify"]?remove_from_uri("modify"):relative_uri()."&modify=1"),'\'>Modify</a></legend><div>
<input type=\'submit\' id=\'save\' value=\'Save\'',($_GET["modify"]?'':" class='jsonly' disabled"),'>
</div></fieldset>

<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type=\'submit\' name=\'edit\' value=\'Edit\'>
<input type=\'submit\' name=\'clone\' value=\'Clone\'>
<input type=\'submit\' name=\'delete\' value=\'Delete\'',confirm(),'>
</div></fieldset>
';$Fd=adminer()->dumpFormat();foreach((array)$_GET["columns"]as$c){if($c["fun"]){unset($Fd['sql']);break;}}if($Fd){print_fieldset("export",'Export'." <span id='selected2'></span>");$vh=adminer()->dumpOutput();echo($vh?html_select("output",$vh,$ka["output"])." ":""),html_select("format",$Fd,$ka["format"])," <input type='submit' name='export' value='".'Export'."'>\n","</div></fieldset>\n";}adminer()->selectEmailPrint(array_filter($Lc,'strlen'),$d);echo"</div></div>\n";}if(adminer()->selectImportPrint())echo"<p>","<a href='#import' class='toggle'>".'Import'."</a>","<span id='import'".($_POST["import"]?"":" class='hidden'").">: ",($kl?input_hidden(ini_get("session.upload_progress.name"),$kl):""),file_input(" name='csv_file'"," ".html_select("separator",array("csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"),$ka["format"])." <input type='submit' name='import' value='".'Import'."'>".($kl?" <progress class='jsonly hidden' max='1' value='0'></progress>":"")),"</span>";echo
input_token(),"</form>\n",(!$q&&$M?"":script("tableCheck();"));}}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$P=isset($_GET["status"]);page_header($P?'Status':'Variables');$_l=($P?adminer()->showStatus():adminer()->showVariables());if(!$_l)echo"<p class='message'>".'No rows.'."\n";else{echo"<table>\n";foreach($_l
as$K){echo"<tr>";$w=array_shift($K);echo"<th><code class='jush-".JUSH.($P?"status":"set")."'>".h($w)."</code>";foreach($K
as$X)echo"<td>".nl_br(h($X));}echo"</table>\n";}}elseif(isset($_GET["script"])){header("Content-Type: application/json; charset=utf-8");if($_GET["script"]=="db"){$Xj=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach(table_status()as$B=>$S){json_row("Comment-$B",h($S["Comment"]).($S["Error"]?" <span class='error'>".h($S["Error"])."</span>":""));if(!is_view($S)||preg_match('~materialized~i',$S["Engine"])){foreach(array("Engine","Collation")as$w)json_row("$w-$B",h($S[$w]));foreach(array_keys($Xj+array("Auto_increment"=>0,"Rows"=>0))as$w){if(array_key_exists($w,$S))json_row("$w-$B",format_status($S,$w));if($S[$w]!=""&&isset($Xj[$w]))$Xj[$w]+=($S["Engine"]!="InnoDB"||$w!="Data_free"?$S[$w]:0);}}}if(function_exists('Adminer\db_status'))$Xj=db_status();foreach($Xj
as$w=>$X)json_row("sum-$w",format_number($X));json_row("");}elseif($_GET["script"]=="kill"){if(!$j)connection()->query("KILL ".number($_POST["kill"]));}else{foreach(count_tables(adminer()->databases(false))as$h=>$X){json_row("tables-$h",format_number($X));json_row("size-$h",db_size($h));}json_row("");}exit;}else{if(!isset($_GET["select"])&&support("single_table")){$T=tables_list();if($T)redirect(ME.(support("table")?"table=":"select=").url_escape(key($T)));}$Zf=ME.(isset($_GET["select"])?"select=&":"");$ok=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($ok&&!$j&&!$_POST["search"]){$I=true;$dg="";if(JUSH=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"]);$dg='Tables have been truncated.';}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$dg='Tables have been moved.';}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$dg='Tables have been copied.';}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$dg='Tables have been dropped.';}elseif(JUSH=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("PRAGMA integrity_check(".q($R).")")as$K)$dg
.="<b>".h($R)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(JUSH=="mssql"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("DBCC CHECKTABLE (".q(table($R)).") WITH TABLERESULTS")as$K)$dg
.="<b>".h($R)."</b>: ".h($K["MessageText"])."<br>";}}elseif(JUSH!="sql"){$I=(JUSH=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),(array)$_POST["tables"]));$dg='Tables have been optimized.';}elseif(!$_POST["tables"])$dg='No tables.';elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('Adminer\idf_escape',$_POST["tables"])))){while($K=$I->fetch_assoc())$dg
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect(relative_uri(),$dg,$I);}page_header(($_GET["ns"]==""?'Database'.": ".h(DB):'Schema'.": ".h($_GET["ns"])),$j,true);if(adminer()->homepage()){if($_GET["ns"]!==""){$D=$_GET["order"];$Kd=($D||support("fast_status"));echo"<div>\n","<h3 id='tables-views'>".'Tables and views'."</h3>\n";$nk=($Kd?table_status():tables_list());if(!$nk)echo"<p class='message'>".'No tables.'."\n";else{echo"<form action='' method='post'>\n";if(support("table")){echo"<fieldset><legend>".'Search data in tables'." <span id='selected2'></span></legend><div>",html_select("op",adminer()->operators(),idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%"))," <input type='search' name='query' value='".h($_POST["query"])."'".on('keydown','submitKeydown','search').">"," <input type='submit' name='search' value='".'Search'."'>\n","</div></fieldset>\n";if(!$j&&$_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n",'<thead><tr>','<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="'.'All'.'"'.on('click','formCheck','^(tables|views)\[').'>','<th'.(!$D&&JUSH!='sqlite'?" aria-sort='ascending'":'').'><a href="'.h(substr($Zf,0,-1)).'">'.'Table'.'</a>';$d=array("Engine"=>array('Engine'.doc_link(array('sql'=>'storage-engines.html'))));if(collations())$d["Collation"]=array('Collation'.doc_link(array('sql'=>'charset-charsets.html','mariadb'=>'supported-character-sets-and-collations/')));if(function_exists('Adminer\alter_table'))$d["Data_length"]=array('Data Length'.doc_link(array('sql'=>'show-table-status.html',)),"create",'Alter table',);if(support("indexes"))$d["Index_length"]=array('Index Length'.doc_link(array('sql'=>'show-table-status.html',)),"indexes",'Alter indexes',);$d["Data_free"]=array('Data Free'.doc_link(array('sql'=>'show-table-status.html')),"edit",'New item');if(function_exists('Adminer\alter_table'))$d["Auto_increment"]=array('Auto Increment'.doc_link(array('sql'=>'example-auto-increment.html','mariadb'=>'auto_increment/')),"auto_increment=1&create",'Alter table',);$d["Rows"]=array('Rows'.doc_link(array('sql'=>'show-table-status.html',)),"select",'Select data',);if(support("comment"))$d["Comment"]=array('Comment'.doc_link(array('sql'=>'show-table-status.html',)),);$_a=array('Engine','Collation','Comment');foreach($d
as$w=>$c)echo"<th".($D==$w?" aria-sort='".(in_array($w,$_a)?"ascending":"descending")."'":"")."><a href='".h($Zf)."order=$w'>$c[0]</a>";echo"<tbody>\n";if($D){uasort($nk,function($fa,$Ga)use($D,$_a){$J=($fa[$D]<$Ga[$D]?-1:($fa[$D]>$Ga[$D]?1:0));return(in_array($D,$_a)?$J:-$J);});}$T=0;$Xj=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach($nk
as$B=>$P){$Cl=($Kd?is_view($P):$P!==null&&!preg_match('~table|sequence~i',$P));$P=($Kd?$P:array('Engine'=>$P));$s=h("Table-".$B);echo'<tr><td class="hover">'.checkbox(($Cl?"views[]":"tables[]"),$B,in_array("$B",$ok,true),"","","",$s),'<th>'.(support("table")||support("indexes")?"<a href='".h(ME)."table=".url_escape($B)."' title='".'Show structure'."' id='$s'>".h($B).'</a>':h($B));if($Cl&&!preg_match('~materialized~i',$P['Engine'])){$Bk='View';echo'<td colspan="'.(count($d)-(support("comment")?2:1)).'">'.(support("view")?"<a href='".h(ME)."view=".url_escape($B)."' title='".'Alter view'."'>$Bk</a>":$Bk),"<td align='right'><a href='".h(ME)."select=".url_escape($B)."' title='".'Select data'."'>?</a>";if(support("comment"))echo'<td>'.h($P['Comment']);}else{if($Kd){foreach(array_keys($Xj)as$w)$Xj[$w]+=($P["Engine"]!="InnoDB"||$w!="Data_free"?idx($P,$w):0);}foreach($d
as$w=>$c){$s=" id='$w-".h($B)."'";echo($c[1]?"<td align='right'><a href='".h(ME."$c[1]=").url_escape($B)."'$s title='$c[2]'>".format_status($P,$w)."</a>":"<td$s>".h(idx($P,$w,'?')).($w=="Comment"&&$P["Error"]?" <span class='error'>".h($P["Error"])."</span>":""));}$T++;}echo"\n";}echo"<tr><td class='hover'><th>".sprintf('%d in total',count($nk)),"<td>".h(JUSH=="sql"?get_val("SELECT @@default_storage_engine"):""),(collations()?"<td>".h(db_collation(DB,collations())):'');if($Kd&&function_exists('Adminer\db_status'))$Xj=db_status();foreach($Xj
as$w=>$Wj)echo($d[$w]?"<td align='right' id='sum-$w'>".($Kd?format_number($Wj):""):"");echo"\n","</table>\n",($Kd?'':script("ajaxSetHtml('".js_escape(ME)."script=db');")),"</div>\n";if(!information_schema(DB)){$wl="<input type='submit' value='".'Vacuum'."'".on_help("VACUUM")."> ";$fh="<input type='submit' name='optimize' value='".'Optimize'."'".on_help(JUSH=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE")."> ";$ji=(JUSH=="sqlite"?$wl."<input type='submit' name='check' value='".'Check'."'".on_help("PRAGMA integrity_check")."> ":(JUSH=="pgsql"?$wl.$fh:(JUSH=="mssql"?"<input type='submit' name='check' value='".'Check'."'".on_help("DBCC CHECKTABLE")."> ":(JUSH=="sql"?"<input type='submit' value='".'Analyze'."'".on_help("ANALYZE TABLE")."> ".$fh."<input type='submit' name='check' value='".'Check'."'".on_help("CHECK TABLE")."> "."<input type='submit' name='repair' value='".'Repair'."'".on_help("REPAIR TABLE")."> ":"")))).(function_exists('Adminer\truncate_tables')?"<input type='submit' name='truncate' value='".'Truncate'."'".confirm().on_help(JUSH=="sqlite"?"DELETE":"TRUNCATE".(JUSH=="pgsql"?"":" TABLE"))."> ":"").(function_exists('Adminer\drop_tables')?"<input type='submit' name='drop' value='".'Drop'."'".confirm().on_help("DROP TABLE").">":"");echo($ji?"<div class='footer'><div>\n<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>$ji\n</div></fieldset>\n":"");$g=(support("scheme")?adminer()->schemas():adminer()->databases());if(count($g)!=1&&function_exists('Adminer\move_tables')){echo"<fieldset><legend>".'Move to another database'." <span id='selected3'></span></legend><div>";$h=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($g?html_select("target",$g,$h):'<input name="target" value="'.h($h).'" autocapitalize="off">'),"</label> <input type='submit' name='move' value='".'Move'."'>",(support("copy")?" <input type='submit' name='copy' value='".'Copy'."'> ".checkbox("overwrite",1,$_POST["overwrite"],'overwrite'):""),"</div></fieldset>\n";}echo"<input type='hidden' name='all' value=''".on('click','countTables',$T).">\n",input_token(),"</div></div>\n";}echo"</form>\n",script("tableCheck();");}echo(function_exists('Adminer\alter_table')?"<p class='links hover'><a href='".h(ME)."create='>".'Create table'."</a>\n":''),(support("view")?"<a href='".h(ME)."view='>".'Create view'."</a>\n":""),"</div>\n";if(support("routine")){echo"<div>\n","<h3 id='routines'>".'Routines'."</h3>\n";$Vi=routines();if($Vi){echo"<table class='odds'>\n",'<thead><tr><th>'.'Name'.'<th>'.'Type'.'<th>'.'Return type'."<td class='hover'><tbody>\n";foreach($Vi
as$K){$B=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".url_escape($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').url_escape($K["SPECIFIC_NAME"]).$B).'" title="'.'Call'.'">'.h($K["ROUTINE_NAME"]).'</a>','<td>'.h($K["ROUTINE_TYPE"]),'<td>'.h($K["DTD_IDENTIFIER"]),'<td class="hover"><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').url_escape($K["SPECIFIC_NAME"]).$B).'">'.'Alter'."</a>";}echo"</table>\n";}echo'<p class="links hover">'.(support("procedure")?'<a href="'.h(ME).'procedure=">'.'Create procedure'.'</a>':'').'<a href="'.h(ME).'function=">'.'Create function'."</a>\n","</div>\n";}if(support("event")){echo"<div>\n","<h3 id='events'>".'Events'."</h3>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".'Name'."<th>".'Schedule'."<th>".'Start'."<th>".'End'."<td class='hover'><tbody>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?'At given time'."<td>".h($K["Execute at"]):'Every'." ".h($K["Interval value"])." ".h($K["Interval field"])."<td>".h($K["Starts"])),"<td>".h($K["Ends"]),'<td class="hover"><a href="'.h(ME).'event='.url_escape($K["Name"]).'">'.'Alter'.'</a>';echo"</table>\n";$Xc=get_val("SELECT @@event_scheduler");if($Xc&&$Xc!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($Xc)."\n";}echo'<p class="links hover"><a href="'.h(ME).'event=">'.'Create event'."</a>\n","</div>\n";}}}}page_footer();