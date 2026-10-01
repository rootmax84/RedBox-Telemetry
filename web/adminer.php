<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 6.1.1
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
VERSION="6.1.1";error_reporting(24575);set_error_handler(function($Wc,$Yc){return!!preg_match('~^Undefined (array key|offset|index)~',$Yc);},E_WARNING|E_NOTICE);$Ad=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($Ad||ini_get("filter.default_flags")){foreach(array('_GET','_POST','_COOKIE','_SERVER')as$X){$wl=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($wl)$$X=$wl;}}$_COOKIE=array_filter($_COOKIE,'is_scalar');if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");function
connection($f=null){return($f?:Db::$instance);}function
adminer(){return
Adminer::$instance;}function
driver(){return
Driver::$instance;}function
connect(){$Nb=adminer()->credentials();$J=Driver::connect($Nb[0],$Nb[1],$Nb[2]);return(is_object($J)?$J:null);}function
idf_unescape($t){if(!preg_match('~^[`\'"[]~',$t))return$t;$yf=substr($t,-1);return
str_replace($yf.$yf,$yf,substr($t,1,-1));}function
q($Q){return
connection()->quote($Q);}function
idx($ya,$w,$i=null){return($ya&&array_key_exists($w,$ya)?$ya[$w]:$i);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
int_type(){return'(tiny|small|medium|big)?int(eger|\d)?';}function
number_type(){return'(^('.int_type().'|decimal|numeric|number|real|(binary_|half_|scaled_)?float\d?|(binary_)?double( precision)?|(small)?money)$)';}function
text_type(){return'char|text'.(JUSH=="sql"?'|enum|set':'');}function
is_user_type($U){return
in_array($U,idx(driver()->structuredTypes(),'User types',array()));}function
full_type_sql(array$k){$U=$k["type"];return(is_user_type($U)?idf_escape($U).substr($k["full_type"],strlen($U)):$k["full_type"]);}function
is_searchable(array$k,array$X){if(!isset($k["privileges"]["where"]))return
false;if(preg_match('~NULL$~',$X["op"]))return
true;$U=$k["type"];$sj=$X["val"];$Oa='binary$|bytea|raw|image|bfile|^vector$'.(JUSH=="mssql"?'|^timestamp$':'|^bit').(JUSH=="oracle"?'|^blob|^long|rowid':'');if(preg_match("~$Oa~",$U))return
false;if(preg_match(number_type(),$U)){$Wg='-?\d+(\.\d+)?';return(bool)preg_match('~^'.$Wg.(preg_match('~IN$~',$X["op"])?"( *, *$Wg)*":'').'$~',$sj);}if(preg_match('~^(small)?date|^timestamp~',$U))return(bool)preg_match('~^\d+-\d+-\d+~',$sj);if(preg_match('~^time~',$U))return(bool)preg_match('~^\d+:\d+~',$sj);if(preg_match('~^bool~',$U)||(JUSH=="mssql"&&$U=="bit"))return(bool)preg_match('~^(t|f|true|false|[01])$~i',$sj);return
true;}function
remove_slashes(array$Rl,$Ad=false){$J=array();foreach($Rl
as$w=>$X)$J[stripslashes($w)]=(is_array($X)?remove_slashes($X,$Ad):($Ad?$X:stripslashes($X)));return$J;}function
bracket_escape($t,$Ha=false){static$el=array(':'=>':1',']'=>':2','['=>':3','"'=>':4','='=>':5');return
strtr($t,($Ha?array_flip($el):$el));}function
url_escape($Q){static$el=array();if(!$el){$el=array(' '=>'+');foreach(str_split("\"'<>#%&+=?".ini_get("arg_separator.input"))as$ab)$el[$ab]=sprintf('%%%02X',ord($ab));for($r=0;$r<256;$r++){if($r<32||$r>126)$el[chr($r)]=sprintf('%%%02X',$r);}}return
strtr((string)$Q,$el);}function
min_version($Ul,$Sf="",$f=null){$f=connection($f);$Gj=$f->server_info;if($Sf&&preg_match('~([\d.]+)-MariaDB~',$Gj,$A)){$Gj=$A[1];$Ul=$Sf;}return$Ul&&version_compare($Gj,$Ul)>=0;}function
charset(Db$e){return(min_version("5.5.3",0,$e)?"utf8mb4":"utf8");}function
ini_set($sh,$Y){return(function_exists('ini_set')?\ini_set($sh,$Y):false);}function
ini_bool($Se){$X=ini_get($Se);return(preg_match('~^(on|true|yes)$~i',$X)||(int)$X);}function
ini_bytes($Se){$X=ini_get($Se);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
max_input_vars($K,$Eh){$Vf=(int)ini_get("max_input_vars");return($Vf?(int)floor(($Vf-$Eh)/$K):0);}function
max_input_vars_error(){$Se="max_input_vars";return
sprintf('Maximum number of allowed fields exceeded. Please increase %s.',"<b>$Se = ".ini_get($Se)."</b>");}function
sid(){static$J;if($J===null)$J=(SID&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
set_password($Tl,$O,$V,$F){$_SESSION["pwds"][$Tl][$O][$V]=($_COOKIE["adminer_key"]&&is_string($F)?array(encrypt_string($F,$_COOKIE["adminer_key"])):$F);}function
get_password(){$J=get_session("pwds");if(is_array($J))$J=($_COOKIE["adminer_key"]?decrypt_string($J[0],$_COOKIE["adminer_key"]):false);return$J;}function
get_val($H,$k=0,$_b=null){$_b=connection($_b);$I=$_b->query($H);if(!is_object($I))return
false;$K=$I->fetch_row();return($K?$K[$k]:false);}function
get_vals($H,$c=0){$J=array();$I=connection()->query($H);if(is_object($I)){while($K=$I->fetch_row())$J[]=$K[$c];}return$J;}function
get_key_vals($H,$f=null,$Jj=true){$f=connection($f);$J=array();$I=$f->query($H);if(is_object($I)){while($K=$I->fetch_row()){if($Jj)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$f=null,$j="<p class='error'>"){$_b=connection($f);$J=array();$I=$_b->query($H);if(is_object($I)){while($K=$I->fetch_assoc())$J[]=$K;}elseif(!$I&&!$f&&$j&&(defined('Adminer\PAGE_HEADER')||$j=="-- "))echo$j.adminer()->error()."\n";return$J;}function
unique_array($K,array$v){foreach($v
as$u){if(preg_match("~^(PRIMARY|UNIQUE)$~",$u["type"])&&!$u["partial"]){$J=array();foreach($u["columns"]as$w){if(!isset($K[$w]))continue
2;$J[$w]=$K[$w];}return$J;}}}function
where_function($Sd,$c,array$k){if($Sd=="md5")return
driver()->md5($c,$k)?:$c;return(in_array($Sd,driver()->functions)||in_array($Sd,driver()->grouping)?apply_sql_function($Sd,$c):$c);}function
where(array$Z,array$l=array()){$J=array();foreach((array)$Z["where"]as$w=>$X){$w=bracket_escape($w,true);$c=idf_escape($w);$k=idx($l,$w,array());$vd=$k["type"];$ff=$k&&(is_blob($k)||preg_match('~binary~',$vd));$J[]=$c.($ff&&!is_utf8($X)?" = ".driver()->quoteBinary($X):(JUSH=="sql"&&$vd=="json"?" = CAST(".q($X)." AS JSON)":(JUSH=="pgsql"&&preg_match('~^jsonb?$~',$k["full_type"])?"::jsonb = ".q($X)."::jsonb":(JUSH=="sql"&&is_numeric($X)&&preg_match('~\.~',$X)?" LIKE ".q($X):(JUSH=="mssql"&&strpos($vd,"datetime")===false?" LIKE ".q(preg_replace('~[_%[]~','[\0]',$X)):" = ".unconvert_field($k,q($X)))))));if(JUSH=="sql"&&preg_match('~char|text~',$vd)&&preg_match("~[^ -@]~",$X))$J[]="$c = ".q($X)." COLLATE ".charset(connection())."_bin";}foreach((array)$Z["null"]as$w)$J[]=idf_escape($w)." IS NULL";foreach((array)$Z["col"]as$r=>$nb){$X=idx($Z["val"],$r);$J[]=where_function(idx($Z["fun"],$r),idf_escape($nb),idx($l,$nb,array())).($X!==null?" = ".q($X):" IS NULL");}return
implode(" AND ",$J);}function
where_columns(array$l){$J=array();foreach((array)$_GET["null"]as$w)$J[$w]=true;foreach(array_keys((array)$_GET["where"])as$w)$J[bracket_escape($w,true)]=true;foreach((array)$_GET["col"]as$nb)$J[$nb]=true;return
array_intersect_key($J,$l);}function
where_check($X,array$l=array()){parse_str($X,$db);remove_slashes(array(&$db));return
where($db,$l);}function
where_link($r,$c,$Y,$ph="="){$mh=($Y!==null?$ph:"IS NULL");return"&where[$r][col]=".url_escape($c).($mh!=first(adminer()->operators())?"&where[$r][op]=".url_escape($mh):"")."&where[$r][val]=".url_escape($Y);}function
convert_fields(array$d,array$l,array$N=array()){$J="";foreach($d
as$w=>$X){if($N&&!in_array(idf_escape($w),$N))continue;$za=convert_field($l[$w]);if($za)$J
.=", $za AS ".idf_escape($w);}return$J;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),array(";"=>"%3B",","=>"%2C"));}function
cookie($B,$Y,$Hf=2592000){header("Set-Cookie: $B=".rawurlencode($Y).($Hf?"; expires=".gmdate("D, d M Y H:i:s",time()+$Hf)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"").($B=="adminer_import"?"":"; HttpOnly")."; SameSite=lax",false);}function
get_url($El,$Eb){$http_response_header=null;$Xc=array();set_error_handler(function($Wc,$j)use(&$Xc){$Xc[]=preg_replace('~^file_get_contents\([^)]*\):\s*~','',$j);return
true;});$J=file_get_contents($El,false,$Eb);restore_error_handler();$oe=(function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header);return
array($J,(preg_match('~^HTTP/[\d.]+ (\d+)~',idx($oe,0,''),$A)?$A[1]:''),(array)$oe,($J===false?implode("\n",$Xc):''),);}function
json_decode_exact($nf){$nf=preg_replace('~"(\\\\u0001(?:[^"\\\\]|\\\\.)*+")|"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)~','"\\\\u0001$1',$nf);return
json_decode(preg_replace('~"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)|-?\d[-+.\deE]*+~','"\\\\u0001$0"',$nf));}function
json_scalar($X){return(is_string($X)&&substr($X,0,1)=="\1"?substr($X,1):$X);}function
json_encode_exact($X,$Dd=0){return
preg_replace('~"\\\\u0001(-?\d[^"\\\\]*)"|(")\\\\u0001(\\\\u0001(?:[^"\\\\]|\\\\.)*+")|"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)~','$1$2$3',json_encode($X,$Dd));}function
get_settings($Hb){parse_str($_COOKIE[$Hb],$Kj);return$Kj;}function
get_setting($w,$Hb="adminer_settings",$i=null){return
idx(get_settings($Hb),$w,$i);}function
save_settings(array$Kj,$Hb="adminer_settings"){$Y=http_build_query($Kj+get_settings($Hb));cookie($Hb,$Y);$_COOKIE[$Hb]=$Y;}function
restart_session(){if(!ini_bool("session.use_cookies")&&(!function_exists('session_status')||session_status()==PHP_SESSION_NONE))session_start();}function
stop_session($Gd=false){$Hl=ini_bool("session.use_cookies");if(!$Hl||$Gd){session_write_close();if($Hl&&ini_set("session.use_cookies",'0')===false)session_start();}}function&get_session($w){return$_SESSION[$w][DRIVER][SERVER][$_GET["username"]];}function
set_session($w,$X){$_SESSION[$w][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($Tl,$O,$V,$h=null){$Dl=remove_from_uri(implode("|",array_keys(SqlDriver::$drivers))."|username|ext|".($h!==null?"db|":"").($Tl=='mssql'||$Tl=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$Dl,$A);return"$A[1]?".(sid()?SID."&":"").($_GET["ext"]?"ext=".url_escape($_GET["ext"])."&":"").($Tl!="server"||$O!=""?url_escape($Tl)."=".url_escape($O)."&":"")."username=".url_escape($V).($h!=""?"&db=".url_escape($h):"").($A[2]?"&$A[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($_,$lg=null){if($lg!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($_!==null?$_:$_SERVER["REQUEST_URI"]))][]=$lg;}if($_!==null){if($_=="")$_=".";header("Location: $_");exit;}}function
query_redirect($H,$_,$lg,$Ji=true,$fd=true,$qd=false,$Rk=""){if($fd){$dk=microtime(true);$qd=!connection()->query($H);$Rk=format_time($dk);}$Xj=($H?adminer()->messageQuery($H,$Rk,$qd):"");if($qd){adminer()->error
.=adminer()->error().$Xj.script("messagesPrint();")."<br>";return
false;}if($Ji)redirect($_,$lg.$Xj);return
true;}class
Queries{static$queries=array();static$start=0;}function
remember_query($H){if(!Queries::$start)Queries::$start=microtime(true);Queries::$queries[]=(driver()->delimiter!=';'?$H:(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";");}function
queries($H){remember_query($H);return
connection()->query($H);}function
apply_queries($H,array$T,$Zc='Adminer\table'){foreach($T
as$R){if(!queries("$H ".$Zc($R)))return
false;}return
true;}function
queries_redirect($_,$lg,$Ji){$Ei=implode("\n",Queries::$queries);$Rk=format_time(Queries::$start);return
query_redirect($Ei,$_,$lg,$Ji,false,!$Ji,$Rk);}function
format_time($dk){return
sprintf('%.3f s',max(0,microtime(true)-$dk));}function
relative_uri($Dl=''){return
preg_replace_callback('~^[^?]*~',function($A){return
str_replace(":","%3A",$A[0]);},preg_replace('~^[^?]*/([^?]*)~','\1',($Dl?:$_SERVER["REQUEST_URI"])));}function
remove_from_uri($Jh=""){return
substr(preg_replace("~(?<=[?&])($Jh".(SID?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_files($B,$cc=false){$xd=$_FILES[$B];if(!$xd)return
null;foreach($xd
as$w=>$X)$xd[$w]=(array)$X;$J=array();foreach($xd["error"]as$w=>$j){if($j)return$j;$m=$xd["name"][$w];$Zk=$xd["tmp_name"][$w];$Cb=file_get_contents($cc&&preg_match('~\.gz$~',$m)?"compress.zlib://$Zk":$Zk);if($cc){$dk=substr($Cb,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$dk))$Cb=iconv("utf-16","utf-8",$Cb);elseif($dk=="\xEF\xBB\xBF")$Cb=substr($Cb,3);}$J[]=array($m,$Cb);}return$J;}function
get_file($w,$cc=false,$jc=""){$_d=get_files($w,$cc);if(!is_array($_d))return$_d;$J='';foreach($_d
as$xd){$Cb=$xd[1];$J
.=$Cb;if($jc)$J
.=(preg_match("($jc\\s*\$)",$Cb)?"":$jc)."\n\n";}return$J;}function
upload_error($j){$dg=($j==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($j?'Unable to upload a file.'.($dg?" ".sprintf('Maximum allowed file size is %sB.',$dg):""):'File does not exist.');}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
utf8_length($X){return
strlen(preg_replace('~[\x80-\xBF]~','',$X));}function
format_number($X){preg_match('~^#+([^#0]+)(?:(#+)\1)?(#*0)$~u','#,##0',$A);$Oj=strlen($A[3]);$J=number_format($X,0,".","");$J=preg_replace('~\B(?=(\d{'.(strlen($A[2])?:$Oj).'})*\d{'.$Oj.'}$)~',$A[1],$J);return
strtr($J,preg_split('~~u','0123456789',-1,PREG_SPLIT_NO_EMPTY));}function
format_status(array$S,$w){$X=idx($S,$w,'?');if(!is_numeric($X))return
h($X);if($X<0)return'?';$va=($w=="Rows"&&(JUSH=="sqlite"||$S["Engine"]==(JUSH=="pgsql"?"table":"InnoDB")));return($va?"~ ":"").format_number($X);}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($R,$rd=false){$J=table_status($R,$rd);return($J?reset($J):array("Name"=>$R));}function
column_foreign_keys($R){$J=array();foreach(adminer()->foreignKeys($R)as$n){foreach($n["source"]as$X)$J[$X][]=$n;}return$J;}function
fields_from_edit(){$J=array();foreach((array)$_POST["field_keys"]as$w=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$w];$_POST["fields"][$X]=$_POST["field_vals"][$w];}}foreach((array)$_POST["fields"]as$w=>$X){$B=bracket_escape($w,true);$J[$B]=array("field"=>$B,"full_type"=>"","type"=>"","privileges"=>array("insert"=>1,"update"=>1,"where"=>1,"order"=>1),"null"=>true,"auto_increment"=>($B==driver()->primary),);}return$J;}function
dump_headers($Be,$Bg=false){$J=adminer()->dumpHeaders($Be,$Bg);$Gh=$_POST["output"];if($Gh!="text"||$J=="tar"){$xb=($Gh!="text"&&$Gh!="file"&&preg_match('~^[0-9a-z]+$~',$Gh)?".$Gh":"");header("Content-Disposition: attachment; filename=".adminer()->dumpFilename($Be).".$J$xb");}session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$J;}function
dump_csv(array$K){$ml=$_POST["format"]=="tsv";foreach($K
as$w=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($ml?'\t':'[,;]|^$').'~',$X))$K[$w]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($ml?"\t":";")),$K)."\r\n";}function
parse_csv($Qb,$Aj){$J=array();preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$Qb,$Tf);foreach($Tf[0]as$K){preg_match_all("~((?>\"[^\"]*\")+|[^$Aj]*)$Aj~",$K.$Aj,$Uf);$J[]=$Uf[1];}return$J;}function
csv_value($X){return(preg_match('~^".*"$~s',$X)?str_replace('""','"',substr($X,1,-1)):$X);}function
apply_sql_function($p,$c){return($p?($p=="unixepoch"?"DATETIME($c, '$p')":($p=="count distinct"?"COUNT(DISTINCT ":strtoupper("$p("))."$c)"):$c);}function
get_temp_dir(){return
ini_get("upload_tmp_dir")?:sys_get_temp_dir();}function
file_open_lock($m){if(is_link($m))return;$o=@fopen($m,"c+");if(!$o)return;@chmod($m,0660);if(!flock($o,LOCK_EX)){fclose($o);return;}return$o;}function
file_write_unlock($o,$Ub){rewind($o);fwrite($o,$Ub);ftruncate($o,strlen($Ub));file_unlock($o);}function
file_unlock($o){flock($o,LOCK_UN);fclose($o);}function
first(array$ya){return
reset($ya);}function
password_file($Kb){$m=get_temp_dir()."/adminer.key";if(!$Kb&&!file_exists($m))return'';$o=file_open_lock($m);if(!$o)return'';$J=stream_get_contents($o);if(!$J){$J=rand_string();file_write_unlock($o,$J);}else
file_unlock($o);return$J;}function
rand_string(){return(function_exists('random_bytes')?bin2hex(random_bytes(16)):md5(uniqid(strval(mt_rand()),true)));}function
select_value($X,$z,array$k,$Pk,array$ci=array()){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$rf=array();foreach($X
as$W)$rf+=array_fill_keys(array_keys($W),null);foreach(array_keys($rf)as$pf)$J
.="<th>".h($pf);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($rf,$W)as$Nl)$J
.="<td>".select_value($Nl,$z,$k,$Pk,$ci);}}else{foreach($X
as$pf=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($pf):"")."<td>".select_value($W,$z,$k,$Pk,$ci);}return"<table>$J</table>";}if(!$z)$z=adminer()->selectLink($X,$k);if($z===null){if(is_mail($X))$z="mailto:$X";if(is_url($X))$z=$X;}$X=driver()->value($X,$k);$J=adminer()->editVal($X,$k);if($J!==null){if(!is_utf8($J))$J="\0";elseif($Pk!=""&&is_shortable($k))$J=shorten_utf8($J,max(0,+$Pk),"",$ci);else$J=highlight_matches($J,$ci);}return
adminer()->selectVal($J,$z,$k,$X);}function
is_blob(array$k){return
preg_match('~blob|bytea|raw|file'.(JUSH=="mssql"?'|binary|image':'').'~',$k["type"])&&!in_array($k["type"],idx(driver()->structuredTypes(),'User types',array()));}function
is_identity_always(array$k){return$k["auto_increment"]&&(JUSH=="mssql"||$k["default"]=="GENERATED ALWAYS AS IDENTITY");}function
is_mail($Mc){$Aa='[-a-z0-9!#$%&\'*+/=?^_`{|}~]';$Ac='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';$bi="$Aa+(\\.$Aa+)*@($Ac?\\.)+$Ac";return
is_string($Mc)&&preg_match("(^$bi(,\\s*$bi)*\$)i",$Mc);}function
is_url($Q){$Ac='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';return
preg_match("~^((https?):)?//($Ac?\\.)+$Ac(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i",$Q);}function
is_ipv6($ha){$q='[\da-f]{1,4}';$ef='\d{1,3}(\.\d{1,3}){3}';return(bool)preg_match("~^(($q:){7}$q|($q:){6}$ef|(($q:)*$q)?::(($q:)*($q|$ef))?)$~iD",$ha);}function
is_shortable(array$k){return!preg_match('~'.number_type().'|date|time|year~',$k["type"]);}function
url_host($ye){return(strpos($ye,":")!==false?"[$ye]":$ye);}function
server_parts(array$Vh){return
array("scheme"=>(string)$Vh["scheme"],"host"=>(string)$Vh["host"],"port"=>(string)$Vh["port"],"socket"=>(string)$Vh["socket"],"path"=>(string)$Vh["path"],);}function
parse_server($O){if($O=="")return
server_parts(array());if($O[0]==":"&&!is_ipv6($O)){$Wi=substr($O,1);if(preg_match('~^\d+$~D',$Wi))return
server_parts(array("port"=>$Wi));return(preg_match('~^/[-\w.:/]*$~D',$Wi)?server_parts(array("socket"=>$Wi)):null);}$qj="";if(preg_match('~^([-+.\w]+)://~',$O,$A)){$qj=strtolower($A[1]);$O=substr($O,strlen($A[0]));}if(preg_match('~^\[(.+)](:(\d+))?(/[-\w./]*)?$~D',$O,$A))return(is_ipv6($A[1])?server_parts(array("scheme"=>$qj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);if(is_ipv6($O))return
server_parts(array("scheme"=>$qj,"host"=>$O));if(preg_match('~^(/[-\w./]*)(:(\d+))?$~D',$O,$A))return
server_parts(array("scheme"=>$qj,"host"=>$A[1],"port"=>$A[3]));return(preg_match('~^([-\w.]*)(:(\d+))?(/[-\w./]*)?$~D',$O,$A)?server_parts(array("scheme"=>$qj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);}function
count_rows($R,array$Z,$gf,array$q){$H=" FROM ".table($R).($Z?" WHERE ".implode(" AND ",$Z):"");return($gf&&(JUSH=="sql"||count($q)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$q).")$H":"SELECT COUNT(*)".($gf?" FROM (SELECT 1$H GROUP BY ".implode(", ",$q).") x":$H));}function
slow_query($H){$h=adminer()->database();$Sk=adminer()->queryTimeout();$Pj=driver()->slowQuery($H,$Sk);$f=null;if(!$Pj&&support("kill")){$f=connect();if($f&&($h==""||$f->select_db($h))){$sf=number(get_val(connection_id(),0,$f));echo
script("const timeout = setTimeout(() => { ajax('".js_escape(ME)."script=kill', function () {}, 'kill=$sf&token=".get_token()."'); }, 1000 * $Sk);");}}ob_flush();flush();$J=@get_key_vals(($Pj?:$H),$f,false);if($f){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$Hi=rand(1,1e6);return($Hi^$_SESSION["token"]).":$Hi";}function
verify_token(){return true;}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Q,$pc=""){$ra=array_flip(str_split(compress_alphabet()));$x=strlen($Q);$Pl=($x?13*($x-1)/2-$ra[$Q[0]]:0);$Oa="";$Wi=0;$Xi=0;for($r=1;$r<$x;$r+=2){$Wi=($Wi<<13)+$ra[$Q[$r]]*93+$ra[$Q[$r+1]];$Xi+=13;while($Xi>=8&&$Pl>=8){$Xi-=8;$Pl-=8;$Oa
.=chr($Wi>>$Xi);$Wi&=(1<<$Xi)-1;}}if($Oa=="")return"";if($pc!=""&&function_exists('inflate_init'))return
inflate_add(inflate_init(ZLIB_ENCODING_RAW,array('dictionary'=>$pc)),$Oa,ZLIB_FINISH);return($pc==""&&function_exists('gzinflate')?gzinflate($Oa):inflate($Oa,$pc));}function
inflate($Oa,$pc=""){$Ef=array(3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258);$Ff=array(0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0);$tc=array(1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577);$vc=array(0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13);$J=$pc;$G=0;do{$Bd=inflate_bits($Oa,$G,1);$U=inflate_bits($Oa,$G,2);if(!$U){$G=($G+7)&~7;$x=inflate_bits($Oa,$G,16);$G+=16;$J
.=substr($Oa,$G>>3,$x);$G+=$x<<3;}else{if($U==1){$Nf=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$wc=array_fill(0,30,5);}else{$Mf=inflate_bits($Oa,$G,5)+257;$uc=inflate_bits($Oa,$G,5)+1;$D=array(16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15);$rg=array_fill(0,19,0);$qg=inflate_bits($Oa,$G,4)+4;for($r=0;$r<$qg;$r++)$rg[$D[$r]]=inflate_bits($Oa,$G,3);$sg=inflate_table($rg);$Gf=array();while(count($Gf)<$Mf+$uc){$rk=inflate_symbol($Oa,$G,$sg);if($rk==16)$Gf=array_merge($Gf,array_fill(0,inflate_bits($Oa,$G,2)+3,end($Gf)));elseif($rk==17)$Gf=array_merge($Gf,array_fill(0,inflate_bits($Oa,$G,3)+3,0));elseif($rk==18)$Gf=array_merge($Gf,array_fill(0,inflate_bits($Oa,$G,7)+11,0));else$Gf[]=$rk;}$Nf=array_slice($Gf,0,$Mf);$wc=array_slice($Gf,$Mf);}$Of=inflate_table($Nf);$yc=inflate_table($wc);while(($rk=inflate_symbol($Oa,$G,$Of))!=256){if($rk<256)$J
.=chr($rk);else{$x=$Ef[$rk-257]+inflate_bits($Oa,$G,$Ff[$rk-257]);$xc=inflate_symbol($Oa,$G,$yc);$dh=strlen($J)-$tc[$xc]-inflate_bits($Oa,$G,$vc[$xc]);for($r=0;$r<$x;$r++)$J
.=$J[$dh+$r];}}}}while(!$Bd);return($pc==""?$J:substr($J,strlen($pc)));}function
inflate_bits($Oa,&$G,$Jb){$J=0;for($r=0;$r<$Jb;$r++){$J+=((ord($Oa[$G>>3])>>($G&7))&1)<<$r;$G++;}return$J;}function
inflate_table(array$Gf){$R=array();$mb=0;for($Pa=1;$Pa<=max($Gf);$Pa++){foreach($Gf
as$rk=>$x){if($x==$Pa){$R[$Pa][$mb]=$rk;$mb++;}}$mb<<=1;}return$R;}function
inflate_symbol($Oa,&$G,array$R){$mb=0;$Pa=0;do{$mb=($mb<<1)+inflate_bits($Oa,$G,1);$Pa++;}while(!isset($R[$Pa][$mb]));return$R[$Pa][$mb];}function
script($Uj,$dl="\n"){return"<script".nonce().">$Uj</script>$dl";}function
script_src($El,$fc=false){return"<script src='".h($El)."'".nonce().($fc?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
on($ad,$ge,$wa=null){$xa=array();foreach(array_slice(func_get_args(),2)as$X)$xa[]=json_encode($X,256);return" data-on$ad='".str_replace(array('&','<',"'"),array('&amp;','&lt;','&#039;'),"$ge(".implode(", ",$xa).")")."'";}function
input_hidden($B,$Y=""){return"<input type='hidden' name='".h($B)."' value='".h($Y)."'>\n";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Q){return
str_replace(array('&','<','"',"'","\0"),array('&amp;','&lt;','&quot;','&#039;','&#0;'),$Q);}function
nl_br($Q){return
str_replace("\n","<br>",$Q);}function
checkbox($B,$Y,$fb,$uf="",$b="",$lb="",$wf=""){$J="<input type='checkbox' name='$B' value='".h($Y)."'".($fb?" checked":"").($uf==""&&$lb?" class='$lb'":"").($wf?" aria-labelledby='$wf'":"").$b.">";return($uf!=""?"<label".($lb?" class='$lb'":"").">$J".h($uf)."</label>":$J);}function
optionlist($C,$yj=null,$Il=false){$J="";foreach($C
as$pf=>$W){$uh=array($pf=>$W);if(is_array($W)){$J
.='<optgroup label="'.h($pf).'">';$uh=$W;}foreach($uh
as$w=>$X)$J
.='<option'.($Il||is_string($w)?' value="'.h($w).'"':'').($yj!==null&&($Il||is_string($w)?(string)$w:$X)===$yj?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
group_system(array$Jg,$pj=false){$J=array();$sk=array();foreach($Jg
as$B){if($pj?driver()->isSystem(DB,$B):driver()->isSystem($B))$sk[]=$B;else$J[]=$B;}if($sk)$J[sprintf('System%s','')]=$sk;return$J;}function
html_select($B,array$C,$Y="",$b="",$wf=""){static$uf=0;$vf="";if(!$wf&&substr($C[""],0,1)=="("){$uf++;$wf="label-$uf";$vf="<option value='' id='$wf'>".h($C[""]);unset($C[""]);}return"<select name='".h($B)."'".($wf?" aria-labelledby='$wf'":"")."$b>".$vf.optionlist($C,$Y)."</select>";}function
html_radios($B,array$C,$Y="",$Aj=""){$J="";foreach($C
as$w=>$X)$J
.="<label><input type='radio' name='".h($B)."' value='".h($w)."'".($w==$Y?" checked":"").">".h($X)."</label>$Aj";return$J;}function
confirm($lg=""){return
on('click','confirmClick',$lg?:'Are you sure?');}function
print_fieldset($s,$Df,$Xl=false){echo"<fieldset><legend>","<a href='#fieldset-$s' class='toggle'>$Df</a>","</legend>","<div id='fieldset-$s'".($Xl?"":" class='hidden'").">\n";}function
bold($Qa,$lb=""){return($Qa?" class='active $lb'":($lb?" class='$lb'":""));}function
js_escape($Q){return
str_replace("<","\\x3C",addcslashes($Q,"\r\n'\\"));}function
js_escape_re($Q){return
addcslashes(preg_quote($Q,"/"),"\r\n");}function
pagination_href($E){return
remove_from_uri("page|next").($E?"&page=$E".($_GET["next"]!=""?"&next=".url_escape($_GET["next"]):""):"");}function
pagination($E,$Rb){return" ".($E==$Rb?($E?"<b>".($E+1)."</b>":$E+1):'<a href="'.h(pagination_href($E)).'">'.($E+1)."</a>");}function
hidden_fields(array$Ai,array$Fe=array(),$si=''){$J=false;foreach($Ai
as$w=>$X){if(!in_array($w,$Fe)){if(is_array($X))hidden_fields($X,array(),$w);else{$J=true;echo
input_hidden(($si?$si."[$w]":$w),$X);}}}return$J;}function
hidden_fields_get(){echo(sid()?input_hidden(session_name(),session_id()):''),($_GET["ext"]?input_hidden("ext",$_GET["ext"]):""),(isset($_GET[DRIVER])?input_hidden(DRIVER,SERVER):""),input_hidden("username",$_GET["username"]);}function
on_upload_progress(&$Cl){$Cl=(ini_bool("session.upload_progress.enabled")&&ini_get("session.upload_progress.name")?rand_string():"");return($Cl?on('submit','uploadProgress',ME."upload=$Cl",SESSION_NAME."=$Cl"):"");}function
file_input($b,$Wi=""){$Xf="max_file_uploads";$Yf=ini_get($Xf);$dg="upload_max_filesize";$eg=ini_bytes($dg);$oi=ini_bytes("post_max_size");if($oi&&$oi<$eg){$dg="post_max_size";$eg=$oi;}$fg=ini_get($dg);return(ini_bool("file_uploads")?"<input type='file'$b".on('change','fileChange',(int)$Yf,sprintf('Increase %s.',"$Xf = $Yf"),$eg,sprintf('Increase %s.',"$dg = $fg")).">$Wi":'File uploads are disabled.');}function
enum_input($U,$b,array$k,$Y,$Pc=""){preg_match_all("~".driver()->enumLength."~",$k["length"],$Tf);$si=($k["type"]=="enum"?"val-":"");$fb=(is_array($Y)?in_array("null",$Y):$Y===null);$J=($k["null"]&&$si?"<label><input type='$U'$b value='null'".($fb?" checked":"")."><i>$Pc</i></label>":"");foreach($Tf[0]as$X){$X=stripcslashes(idf_unescape($X));$fb=(is_array($Y)?in_array($si.$X,$Y):$Y===$X);$J
.=" <label><input type='$U'$b value='".h($si.$X)."'".($fb?' checked':'').'>'.h(adminer()->editVal($X,$k)).'</label>';}return$J;}function
input(array$k,$Y,$p,$Fa=false,$_l=false){$B=h(bracket_escape($k["field"]));echo"<td class='function'>";$Vc=driver()->enumLength($k);if($Vc){$k["type"]="enum";$k["length"]=$Vc;}$C=($k["type"]=="enum"||$k["type"]=="set");if(is_array($Y)&&!$p&&!$C)$p="json";$nf=($p=="json"||preg_match('~^jsonb?$~',$k["full_type"]));if($nf&&$Y!=''&&(JUSH!="pgsql"||$k["type"]!="json")&&(is_array($Y)||!$_POST["save"]))$Y=(is_array($Y)?json_encode($Y,128|64|256):json_encode_exact(json_decode_exact($Y),128|64|256));$Vi=($_l&&is_identity_always($k));if($Vi&&!$_POST["save"])$p=null;$Td=(isset($_GET["select"])||$Vi?array("orig"=>'original'):array())+adminer()->editFunctions($k);$b=" name='fields[$B]".($C?"[]":"")."'".($Fa?" autofocus":"");echo
driver()->unconvertFunction($k)." ";$R=$_GET["edit"]?:$_GET["select"];if($k["type"]=="enum")echo
h($Td[""])."<td>".adminer()->editInput($R,$k,$b,$Y);else{$ie=(in_array($p,$Td)||isset($Td[$p]));$Cd=0;foreach($Td
as$w=>$X){if($w===""||!$X)break;$Cd++;}echo(count($Td)>1?"<select name='function[$B]'".on('change','functionChange').on_help_value('^SQL$').">".optionlist($Td,$p===null||$ie?$p:"")."</select>":h(reset($Td)))."<td".($Cd&&count($Td)>1?on('input','skipOriginal',$Cd):"").">";$Ue=adminer()->editInput($R,$k,$b,$Y);if($Ue!="")echo$Ue;elseif(preg_match('~bool~',$k["type"]))echo"<input type='hidden'$b value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked":"")."$b value='1'>";elseif($k["type"]=="set")echo
enum_input("checkbox",$b,$k,(is_string($Y)?explode(",",$Y):$Y));elseif(is_blob($k)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$B'>";elseif($nf)echo"<textarea$b cols='50' rows='12' class='jush-json'>".h($Y).'</textarea>';elseif(($Ok=preg_match('~text|lob|memo~i',$k["type"]))||preg_match("~\n~",$Y)){if($Ok&&JUSH!="sqlite")$b
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$b
.=" cols='30' rows='$L'";}echo"<textarea$b>".h($Y).'</textarea>';}else{$ql=driver()->types();$ol=$ql[$k["type"]];$ya=preg_match('~\[]~',$k["full_type"]);if($ya)$gg=0;elseif(preg_match('~date|time|year~',$k["type"])){$ri=($k["length"]==""&&JUSH=="pgsql"?6:$k["length"]);$Nd=(preg_match('~time~',$k["type"])&&preg_match('~^[1-9]\d*$~',$ri)?$ri+1:0);$gg=($ol?$ol+$Nd:0);}elseif(!preg_match('~int|vector~',$k["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$k["length"],$A))$gg=(preg_match("~binary~",$k["type"])?2:1)*$A[1]+($A[3]?1:0)+($A[2]&&!$k["unsigned"]?1:0);else$gg=($ol?$ol+($k["unsigned"]?0:1):0);echo"<input".((!$ie||$p==="")&&preg_match('~^'.int_type().'$~',$k["type"])&&!$ya?" type='number'":"")." value='".h($Y)."'".($gg?" data-maxlength='$gg'":"").(preg_match('~char|binary~',$k["type"])&&$gg>20?" size='".($gg>99?60:40)."'":"")."$b>";}echo
adminer()->editHint($R,$k,$Y),(count($Td)>1?script("fire(qs('select', qsl('td').previousSibling), 'change');",""):"");}}function
process_input(array$k){$t=bracket_escape($k["field"]);$p=idx($_POST["function"],$t);if($p=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?idf_escape($k["field"]):false);if($p=="NULL")return"NULL";if(is_blob($k)&&ini_bool("file_uploads")){$xd=get_file("fields-$t");if(!is_string($xd))return
false;return
driver()->quoteBinary($xd);}$Y=idx($_POST["fields"],$t);if($Y===null)return
false;if($k["type"]=="enum"||driver()->enumLength($k)){$Y=idx($Y,0);if($Y=="orig"||!$Y)return
false;if($Y=="null")return"NULL";$Y=substr($Y,4);}if($k["auto_increment"]&&$Y=="")return
null;if($k["type"]=="set")$Y=implode(",",(array)$Y);if($p=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
adminer()->processInput($k,$Y,$p);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$_j="<ul>\n";foreach(table_status('',true)as$R=>$S){$B=adminer()->tableName($S);if(isset($S["Engine"])&&$B!=""&&(!$_POST["tables"]||in_array($R,$_POST["tables"]))){$I=connection()->query("SELECT".limit("1 FROM ".table($R)," WHERE ".implode(" AND ",adminer()->selectSearchProcess(fields($R),array(),$S)),1));if(!$I||$I->fetch_row()){$xi="<a href='".h(ME."select=".url_escape($R)."&where[0][op]=".url_escape($_GET["where"][0]["op"])."&where[0][val]=".url_escape($_GET["where"][0]["val"]))."'>$B</a>";echo"$_j<li>".($I?$xi:"<p class='error'>$xi: ".adminer()->error())."\n";$_j="";}}}echo($_j?"<p class='message'>".'No tables.':"</ul>")."\n";}function
on_help($Ok,$Nj=0){return
on('mouseover','helpMouseover',$Ok,$Nj).on('mouseout','helpMouseout');}function
on_help_value($Qi="",$Ui=""){return
on('mouseover','helpValueMouseover',$Qi,$Ui).on('mouseout','helpMouseout');}function
edit_form($R,array$l,$K,$_l,$j='',$H='',$Rk=''){$yk=adminer()->tableName(table_status1($R,true));page_header(($_l?'Edit':'Insert'),$j,array("select"=>array($R,$yk)),$yk);adminer()->editRowPrint($R,$l,$K,$_l,$H,$Rk);if($K===false){echo"<p class='error'>".'No rows.'."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$Kc=false;$dm=($_l&&!isset($_GET["select"])?where_columns($l):array());$Fb=(count($dm)!=count($l));if(!$Fb)$dm=array();if(!$l)echo"<p class='error'>".'You have no privileges to update this table.'."\n";else{echo"<table class='layout nowrap'".on('keydown','editingKeydown').">\n";$Fa=!$_POST;foreach($l
as$B=>$k){echo"<tr".($dm[$B]?on('change','whereChange'):"")."><th>".adminer()->fieldName($k);$i=idx($_GET["set"],bracket_escape($B));if($i===null){$i=$k["default"];if($k["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$i,$Si))$i=$Si[1];if(JUSH=="sql"&&preg_match('~binary~',$k["type"]))$i=bin2hex($i);}$Y=($K!==null?($k["type"]=="set"&&is_array($K[$B])?implode(",",$K[$B]):(is_bool($K[$B])?+$K[$B]:$K[$B])):(!$_l&&$k["auto_increment"]?"":(isset($_GET["select"])?false:$i)));if(!$_POST["save"]&&is_string($Y))$Y=adminer()->editVal($Y,$k);if(($_l&&!isset($k["privileges"]["update"]))||$k["generated"])echo"<td class='function'><td>".select_value($Y,'',$k,null);else{$Kc=true;$p=($_POST["save"]?idx($_POST["function"],bracket_escape($B),""):($_l&&preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$_l&&$Y==$k["default"]&&preg_match('~^[\w.]+\(~',$Y))$p="SQL";if(preg_match("~time~",$k["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$p="now";}if($k["type"]=="uuid"&&$Y=="uuid()"){$Y="";$p="uuid";}if($Fa!==false)$Fa=($k["auto_increment"]||$p=="now"||$p=="uuid"?null:true);input($k,$Y,$p,$Fa,$_l);if($Fa)$Fa=false;}}if(!fields($R)&&driver()->primary!="")echo"<tr>"."<th><input name='field_keys[]'".on('input','fieldChange').">"."<td class='function'>".html_select("field_funs[]",adminer()->editFunctions(array("null"=>isset($_GET["select"]))))."<td><input name='field_vals[]'>";echo"</table>\n";}echo"<p>\n";if($Kc){echo"<input type='submit' value='".'Save'."'>\n";if(!isset($_GET["select"])&&$Fb){$qc=($dm&&($j!=""||adminer()->error!="")?" disabled":"");echo"<input type='submit' name='insert' value='".($_l?'Save and continue editing':'Save and insert next')."' title='Ctrl+Shift+Enter'$qc".($_l?on('click','ajaxForm','Saving…'):"").">\n";}}echo($_l?"<input type='submit' name='delete' value='".'Delete'."'".confirm().">\n":"");if(isset($_GET["select"]))hidden_fields(array("check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]));echo
input_hidden("referer",(isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"])),input_hidden("save",1),input_token(),"</form>\n";}function
repeat_pattern($bi,$x){return
str_repeat("$bi{0,65535}",$x/65535)."$bi{0,".($x%65535)."}";}function
shorten_utf8($Q,$x=80,$nk="",array$ci=array()){if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$x).")($)?)u",$Q,$A))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$x).")($)?)",$Q,$A);$x=strlen(isset($A[2])?$A[1]:preg_replace('~\n[^\n]*\z~',"\n",$A[1]));return
highlight_matches($Q,$ci,$x).$nk.(isset($A[2])?"":"<i>…</i>");}function
highlight_matches($Q,array$ci,$x=null){if($x===null)$x=strlen($Q);$J="";$G=0;if($ci&&@preg_match_all("((?|".implode("|",$ci)."))su",$Q,$Tf,PREG_OFFSET_CAPTURE)){foreach($Tf[0]as$A){list($Ok,$dk)=$A;if($Ok!=""&&$dk<$x){$Qc=min($dk+strlen($Ok),$x);$J
.=h(substr($Q,$G,$dk-$G))."<mark>".h(substr($Q,$dk,$Qc-$dk))."</mark>";$G=$Qc;}}}return$J.h(substr($Q,$G,$x-$G));}function
icon($Ae,$B,$_e,$Uk,$b=""){return"<button ".($B?"type='submit' name='$B'":"draggable='true' tabindex='-1'")." title='".h($Uk)."' class='icon icon-$Ae".($B?"":" jsonly")."'$b><span>$_e</span></button>";}function
copy_icon(){$Ib='Copy';return"<a href='' class='jsonly icon-copy' title='$Ib'><span>$Ib</span></a>";}if(isset($_GET["file"])){if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){header("HTTP/1.1 304 Not Modified");exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression",'1');if($_GET["file"]=="default.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('+c(<]iDp;+<8]XG-X#ETBP{IAOo`HAD0Z,$t2FfTr3g#Vd(TVf>Cx5+d&ycL<,9"B6]b"oPK(+THBssK@e0=xnaBRf;$]A0]jsW_*Ibe$(2;yd5/}P:0_3xBmwvnkq<ydKh3e?rW3UW8c6iorbru~.;FOKSUq)z0u4OH&MRf7i:
N[U_7;F1p9i(5s29CE=`-:Ze`.kn@9RF4QL-+YuW|6!U&>e-zoVW6?4.A8t`/B@/ELQUIrIU%7Kvs3S?k#p2;1H
X/y@s?AR7M+0t;gg~I=j9:,1HW;ic+?$`BB#$=qSuRwY!<DX3Ny>D#NN*xh]"#vZCW/M>Y@mG3*h%?kSmN=OT;f?cTPrvH,vnUVam2HYmY(?/@
0?NF?2Ol^+5I
U%GE]1O0$>ys`u;eY*<H,E)jU7$
/G"I;uWG$9)5gsRW{:{e~U~.8AE.5W!
iWp-~OBKj1jL_EwRB?Y&i9o<;$`HciPwu)}$:S%2WeZN_EB+TMeR~sZ$~6Tlb.PFzj2c5D6$IrS,VAG6-$YN,Jsp.hB-"5(?g%)]_85S;K}Q)!]Ug5W7nWvawCpy>%pke1%;C>,8*br=&cDBB+3bJLku@UA6Pj:[LNyDz#!]HdCU2Vp/Tlj+yr(,J,*Wh<pP&Q5qxHb/^oeg5%v+WD+:w2T0+%DHK8Z6Oq>bbmt"W9"Gv0Dk;uesBj-9`dThaOFtw)#)nPV3&XyFGk01&HN4/Q{y|&Q`8uS0E,^"<CZDxnmK?l<Ff_g^SD_u$Z@<+<tC&b^k)8;L,&z5t.6)K0^"X#G_Rn:/T#T(cmAla^WGNtZS4XlovO
"JS=mYRH05NTD)D$P<[|2YAPQ@>B#ogdeOCH)M?8)+*D+`r2kS@J7:27wU2&LL!0(7rXo)F2]gv#2me/LOGGPS`9P@Wp!c9C)IEitF`CKjug?LA"_w?5f5ERLbV%"Sekh?;mD9kN
Kgs*3=$EYJJoJm;SuFwOv5@2rMMTVhHs
L]vkS}w&YkWCkI/+1n,j:]OE&&-ng%CfmYK`SO(N(v3A:cVgy
Z)dKHyOq`7+i0G]M(r,[9,?ZYq
]/"%_Hv07O6xyQ^+0#-*3$SpP!ci1={S}nLm3E:8k`7A`ng!$yH-j]G/|y}d8Vhy6XZ5FNRiRhJ:f23:43;VJp2REp"2/PU]Gt#F?,).3Oi]/oy2tM5RMl*RzvJsW2/:.fEIoUE.
4l]"?S9(cOr~IfBV51Su7^`]oq&3b5HUl~B4/Sb^C7,cE.H-tQV*^R#rEmw+$qICNudLR<En:sNot2

KY)]Xs7@-;-#x`3qs]OY=[_lc=L3=|G6qr8vNfc@]Vm(F,&^2w_="t$w3x[VZM*ybGUsmQORjL@FP4&?
5C3C%d%,g6fMf@kAN>(<M)9lHg<(aowp0%0gvB
>uekZH[Qhb3m3EZu69I0Z3Sk!}C,Cs`CJNb|ho&F+_Fv1V*9@fd39
&)f27Wk->IL*%gW1AqC,hV&i=^gG(P&
jb8I5PFUY-4Y_YjN@_kBFm$)`,?gA<HJm&6&N`7b69`OSgA1e]O:Jd%MEx>s2|
d%"mb?yp
C1+?Ml&im[ZB*N!c%e=]R
6B%O;a,NRqW|U}tM;5Z6t7o/?c>a9WsG1Tk!?#]gn|vgx{qBT_505u#2(M@LqfO(RlG=I)aR6fMs]
nyL/y6bx-,d|UacKJhi=PhxG2`Qhl5=kK?AT_AUf&(NI!:9i)TXDh`9<_N>d$}d?99epf"Lte7lxQz/KD0&
ZA8jpQrjlcQ`!?dHd(6IFPRu1E.&@RfvL"p*6zsdlUE%#5/[>7_)D3jrqi%&e%U492k}9j(NS]G2KOF?I*ib%:`c)lY)b}l`asY{0YvMx=TkbBq:&ne?mKPtlwO9<i6[J=Jvib,r+Fh4>y%C9%Yf)_/A$Z=)PHmQN[3@8SPov"@_3L7^5<?Dc?#_LK9z`l1?RA7mfD#{AcNbJjBY6}GCA*S
jaGHPp.*:(DO&{E97kVgEB_Mj
eyxuy@#C.PNGx>5*Frh_a_R?jDEH
0SJj74wk2(zJX$+G~tIe<)cUp$,i.@m:h$uqJkHp`rw5Oi^B#;af$OYt3F5ljH4E)n
D.E35kH:ddpC<:o)_ZTHo`JxY;Qo/~4cD]Kka#&Jg*
y8e"5(G+TB3VllVi"P,;M4*yU*:INGrm[E8&;fe79XPN-#HZv5M?*PHeu.j&qH`E)#ewBDs*XVW1sO4U^:"lWmS1I>hX!E/xZ:hSZq%;>_)s>QJx!o{4A%]JSThrG%Aq<$N00!#oQX&8YX@`nJxO-^f-2`Z#9H&@%7^<DA?mq`x3NL0"#LEPiR>]RVtp;MmL/xD4|W(@Q5=oF=5mMmOh/Ed]Sn#e4w!Xa3S0b5Zs2b4[JPU>Y-Q_NdQDebkvBS_`_2,Kmh/dlNO,=7^iJKsDWC+Y}eCrN]v7WqiU|@~@STU"M@~Z[ZQJfm!ZJ=,hW_1eaXa:~*LCm$prSpmT^XnJF)]#[MQtoKXlVgoIg#irnY4IhC=FVmq=3*Yv8]G>Mm=I"2/G")dC3B*e8=5rlIaC%)KY<2qP_N!X`(xkecZi(l?<@TCJAua%LjXkPpuSBqc%1"{/VvHRN]7dh00ywYgc4M/2.9$N-_EFZ=#GUk@,3jn5zb@70neZ0+4dd?x*0kYA~rA!AKSR.)6S~whrt^m2NhrAvS`$=p)^aw:ss1zm[bmvTR?tU->o$OHx",{x
vU@iBfpiWCxF%}yCyf`rpPj5N%[zq+ww-"f!bm,qDw*~l4Q$y|vWbth!KtKd!<p&2<BU,[sQmx7pn}fhC^bKwW4>csrq@JyewRu|l7Ko>ggj>ayQr+wB9$qURwa"&%IN+g5arv+VyAd"lDSyM|Pi5Y*5K<uvJ778nz<2xCBq&]*7p+X);jN9`i
o`,]_p5MuX3RtD*CcKdl&)hNr;rJ(d}KjWtTq]NRh(Xpx$ql^bW(3*]%@M)BntuCI]vqpX|v[,gS`tfFX)bclBkp~:F0/Ja^>h1q+MZ-U_2J4WqqJM;oN-/r/E{Qh9U1c@v[7r.C_i_Tje
0J`0("b_3TT`&!Lb&)u:F|(8mo,ScbAqi}[zj#5U
qY)>5Sz68#-"=vXaWY*E-<xKi+M0fX&e_Fbu
=+7.L*v{tw=j#l^DKec|yzcIm<)55UCRw`=/jRj^H,.qZ;Or,k1@?K$_(+XuOf%3qnQbR!3|l0",RrV!Quq[g.46^QGSk~KSyRE20k2B<-5b/TC4A_H,iUtJuYn%uR,cv9E@z)vGMXa
U7Y2)a(JKS0}aSk]F6_NGG[gkas.8:b|UpDNo7)#1:#^u(_HCcA[=y$0/DoQ*-u-Y(kgPrvr]SA.64*juL2`E:o"&N
&,C8%TeT~=V^yWF8S.B@gi_qm<d1?S%?e_=p!.T[#Bw5ZN+cEZIcA.cq*akE?G
:i+IU0S
U^_[@PL}3e`rc&=dEd1i
]Vb1Iot<#!x+zvS0U:]
Otkx6Tg
{N]Sq-dY*#Tq]m>cOeiMi<NR)uU1n>uWeM*X(]|raF9XW
Jk~L;&knB$^)Y91*G
*QyvX*Fy8cX";/[vR-M$^`qM>FPjg8vVhe8E
<@m+-r<%+Mnz^_hSjahRL@hlq&&P(j:BN~O5?x,=/)siN?uPspLjm?2uWg?;QeUeu;Ribp+C$IU0Uuc/m2BD<~juKOZbKB>_fBjkwO`#:]yJnD>.S0E/^Qhj9Zv<_:WwIvaK=N.)@)bOq;)Q87@L*l%Yk<s^`g1Cat#ti>&u6x&d+11d
/U#+W!`B:ZvZ&fc_/`sZceqo}[)20D-y$oVu9m_uA]j;-U7bB<|?7P|@2nE2@V?K98_5-g+ILk?^8*sC(bm*TB$7WbTag
9Q|r`/x[gbit95y)ed+c[J^bxHu4Bt13vMc@ORH,*amJeT
3qE<,,dHeMwr3;dgO>U(4bF.oRf}L*Ig:K0;UhO]&n>,++r:ue_nFS)#f9u6p-?syidcp)g?JNdnUG>eY+Z;Q
ic*@g5_.(zZ+YP)7WTcSVV64E0hX_i$-S*f>RIkT6ld.Z.><DZX71s.
WoV<&#()`85SxZ)5=HnlbK"*8mBsyhtbT&MSD&=h;.d+S7EUaT<uXX<57JdOk<fN,pSbM2v
)Gt5CBx_X;Xmx[w@arJubk5w^{]:
g*qcDVgqw;JdkVn3F.8$@p,:"FTh[*DX9m$V*:+c!axkZ)oj,UU@4RHdkI_C-nv@Bbb"2Il%Ec`V/]F!T7fe:-a?F&-iS-6v-`v1N"_kxs%h/QW`a,XTZX,EAi}J]q`kTc2LhWS
KS/b&hmAg*8t%vv$ER(=+^RuQ+RY1t{08ll%O;+4{X7>#p:nsa+_mEK"WOgW;oA/x=!OiUz/D[9&Ty.4oid/0)gkT19r~W^qH<o6j<O!J)P3
8@`.:n<:I^>H1d_z]a..A=+~7R`By];s-$4h19^+&_Wk7|H|u@b)l)m`d>1.U~vpYshDNjbBe4QTR`F$k[dSsdQOF5APIhwwcKCo@.rtDG^NYTsUbFiJyvxd1h&s]W+:x^L%tX');}elseif($_GET["file"]=="dark.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('$Osc2b7V>fj0U7tCw8TNfbT`5e<0!4x49EeL1n%e,E<_WZ?>@wWMzpUD:UubAB^u7,;ZJ.!U!ODBJ$p,I?B8.F[0L@ZP|U.08;>OMB~NvqOKctbeXn{?PG0IyC%OS?)r=jqOH2M?hb}k!R/`..+tcti.<c<RYfU_-K2A
vCxFn<6{L^r&8pu@R.[?Y;Q%DJhl3UE#:f_^Gy.{7^;|fNj6ajaX/-y3:kA
^e)ZU{TubDn#S5p^*>QuANxT;&o~2R.180pfCDp{Af#.9i9,-(*Y&"#eJu7R(uH>BnL38{cb_!.Xt`+GTj["w]mNugo|W%7muWs%-r2M[*vyw`Oi6fN"fuoOa;Cx<jivJ:1;k/yuSo;e7`7Ib+sTqL`5Z!<_Msdu^T1o?NxnfVqUcZ(31mO1mW&?l3hyqkSs<KDz)JOYNX.qcbw.a-hF9!u8),&16yAnEfk#`",*pieSR"^
:QnVS6*:q5["XTfNZdqS`4.wq&WqR$ff;}))8p/))?(NoZ>?`%8E(L)Qbr/mvjYq3odVYIZmt];gErx>%}pw8jHUEMo^U6FzrG6!>r%.Fl,i8GQ7.)96y=aANQAV
I&mM@=8CtkDDN#eQ><"PT&0&"
MqJq.V[OT-W:;AFl}kzYka3jtFJR2
a(@dy4=2dZ`=+P>(`R1E_t2_SsXn^QTobmely7V_<d>rQWl@v,kyf0vur:xH._r2kS"fa0O7^l]f4?(
>1U6U+VK-57o7x8oQWpsfb%dt4*EQPSa_B
R5pEdml@-Xb&>I"-^*scQ1=E/ctCOk5vsYn%D90P*?o@rb?
W?IHCMCI&bJI9sK8]T5bt76}rq_*)G[9K2;FAd)taZI^BtuBX+sK60V2H]NeE|*vCruwDpOK^kn6m6f6&1s^Ucs*]9vP^6+%wutWM/66l4Fj)WO0?_+RGg2|jW.*v?W?ZXyua"T+&(XWj;>M!:kHFApzq<`|]GHW(vkK32!q%[A`AHO1`}O*ZI?@w}[)');}elseif($_GET["file"]=="functions.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('+]bcjnsZ323o!;f/.e+fx8n;[f71r0pc]giW`oV!D5mSIO;.F)#>?N&/uKRvUtUkWix^XgjX;sS9{(3
eB-
eJD[z_=IA3FpiUH:=MZ3k@qANy
,pQ[T_3Z)Wvk_gT[Jd2khOG[lbtSJi%Xc~x,$3rQ`e7zs`9hczx~5/)NGI&}cu=mR0U@T!doS{^#,i$18TPKy|Ut2;;<LDrZ*rx?t3j]uV0wBhgRMVF+5rB"o&w`p]@K$P3@jPh+.?b}q$QhMV-w*DRoP)N{L2JTta*c.*>bWJuFy>FjBaYa^O=]J40HjoD4_^T?rw%#V3SaEJ,@haT<%p+hZz%2WiK*x/Ujaht!%qA^VCZ%24?IYc7SxrR/#P0:US@|Fn%2c_mX-0TL`9<sF_kip3+A]LVm4i_B)GKtx"@.D*hh<7$~]-CYZwFt@Oa4x]vbQ|`u+7g+B6IsXQ3H9^K]$9EnvPx3cO;lKlZbjaFHFIy,ncrDLW[Tn<w@a^MaXrb^LUyM7x^;r12)nqu8iDuIM(&I)|_X"Ta}5J+vE8Uf0-m`=4yNV{@ctgiirE-<
?TDx$]G!:iP](g0Z*9Tox-iN*VRy5Z>W|@*CdPh,qgPeFeMj{gObW[)Bvq#<xTo_E9Ma8.QeBgQwGwbq:@%v*h-K?(EG6roY+s6+fSd4qJe8ihH3!oX$R%<=8sjBnS#^E#;AmlT"iBvkm.HK$!dmmiw(<TJiS*Fu)T`tfj)T
@N%}vxH6(]h%)|`r.SEbR&c(LU&vpH"+5`VMV)-ZbIT#<fD?n>8n2J%va!Mv$m.^hRe%-X-j8i^ltxz"+|(/%&CpSH2U#0L{*B3d4
_yY.,rLR)Vu[*cHPfLnsT%2)YL*l"Qrl)l]e?}*q</a6L:)^n}&4kh
$76?e_vQYFJn_$lIesca`%Ewhj%@RVQhWxqp+.jV5;r-HtRBw:=<Sk)v"OJJtc%O%sO:
ZZ&+Q1[:U&fw2;M5@lX{[^,8rZPTbc9_#(EBnTtG;ck;)R4J&Zs.JeIACX?%N(wf_FTvYw0/xQqtaLx0CKViZm]I1+UWX/z$S9P&84YP;E=~j^9RosV!7zfSE#83?W&67RHN5rXJPDCS2Gyv;d!O@S#*/Uo2_|#Mu=br..K!3_K;71(Uy~vxanxZS)w%e;THdO(#U)!#!GJB^&t[rKBocXn!w<n)YgNp!K#5tt=Q,r#DG:?&t8?)!?"%DU_7IMM7ZkymqVXhSO*|CkdoTA3Uncu[!&/UNEkv$d-~Q*Y<41A./WpZv1ekD>a6K,o&o{FA<,hX="<85cJg1"q`ACg$xfbTN^G(18VLq|V^0zEub|d9kf63V>g9m>$"Zen)a{$+a[j><8U7*GC%x99n>"T[6}?,bk*,q-nZ9yH=^Sqtfqs+*[fgu)b>FMLo[`a<HAX%w/b<L.xjMB]jfV,Ar6qJ-IvMed8h$K;tjyg2&/*Iq[?l-Gu<2MghB>KoD{5rWJrFV]bxI9H:=zbb7Td4o7a.Ga2ZLG`$+KgEw@fGI~tV,k-Nd=YwM{)h61ry(c(L/g5$-cWtXw=Q4,CNs`:|aRsFGM+*-V_`%rI/YL@:?n(>E
/n2exjX%P[Dq]yI,HU[R3mauTku
0AA+R`a:Co6f388ML#DT)"c(m-FoQw:%p0
PG&r"LiSMarC.JWx4eaW//o9%OUE[0S6z&C/p4(Bg2oXd8!##.{#w]{VW@hQ7fU7o:tbCrEEJbz:6(cZrU0Xda`4xVx!+Sr@c2&GQ]F):Yb?,oH$K=2j2W%07YqKDDoSheV3Kw.3@aW+w*B#4Z36q<!qp1PyQkpGS9Q<-V(^VFyG;w.H_s5LVHIY&/6]::OcSEM!@>EdFKirB$BjXBihOE=7tX"fY"#JZ?@Rym;[F@lmHfkJOU*h/!MXOdxLq`d<FUof^^.We>/tP!
oZuZPZ8^WdOIo#ty^2XU`|0/.trk[&21Vcp<:iH=NZ0ja;jtf&gI8o]iT]vsA^G)4V5//@E%tfMjh6j$+8wQs:Pc&6-_w^U&]In#`fvwt6#4:&L0:}G^TL=USoe%m{FH;,kcYR@`?q:)8pQ7H?c^Ua;GAk9K%p"fbcHuJ?<=6AT2(R*xu?bcvJKK1j@|g0`v(s$PNT=Z#L,%Q_C/a)6W+41|JQs:,1-J=t%2n.5cAt6l1AiKxX5xDG-iTXMpZBT=_X:=C4WdkI&-`(.GobZFC9!yC%C%/Q(V*k-!uQ?f5>Nt#36)EAcL2%Sn0e]E^+Gd]E4%Ci@ycI9C7j"@kRwz%J5W;$ioW;Co2:4g`/lcCnj@1G*py6%(m?I
>n6Y!F]B-&^2@,;/Y(Rnu%
]vb+wc+CKNq3w+w0D(]GoV^?fNN?,[+?;,"XGP<!b!&aS!S>`Ef:XW`PJXr>)1K&i;)lNuL!uR7/(<lxsGy137GOLb]Fnb{Q0;?J!6cGsEYr"Y?Ub0,cG9p8(2YP]j]KZ&QBDC/"2WjK>"/S&o8b2Fb6wZ]L)+<w7iPj#)yu#L"[sr`cp/__ynn$Cp^DHqvP(DXD^N?;~EeOS.aI-,bRWxnj54PhTJ)tt)hTKpj85%TlGg/JD"Br_)C3*Niig;Pp+XAIn(ilG0HITlWiG-!Giqi#F0nRZt&"Cm:`qUNeim?P-gE#PI/
a"/;T^g$lh(6(/gH;^mRQ/GJh
?ypWph-5Q:YV0biLHp}BJA[;=f#6io7b2@)3<Q8:h[ha$a3u%YhD2pVtC+)Sxldu`/!Dco{+*5mmGR^S[<=QShX]M[{NRMC$p3YYLT:T7)%uAc/"dRz"d%df`,&kOKUrG"u%=Q8G4_+YgWmeiBeyG<RMbn@!=7A.:b<i.=k.4WVYNE&4C"l.8GqVU"^Y]!G=d7hP]C69ADn
Wvsg{r$C!a"K(/onhFLaJ=$R.Omru/!cNR{Ooj)>;Cw:](`AVamlBKN@J8hW@15F-MR>V<FN2r^I9#6t}@=#@acn%tdDD"J
jpU3@l{VIqpQo0V`%op*XE&Lk,D0D

xn"@?(c%ua3"y(U2U=!R[OD9?CRH.z?t>VQgh~gFpv#DLi
rg]W*rmj~c0w(QWr]HbdnZmi(6!ICUX5JR~5cMVF[m#Qtrp5BW#D6_#CyQe+;$$JaCk<"0#RWu?mJ2elz9{U%lCxJ6F"_DDRtG:Jnx}[i,fyXsGHmF8UVDO`@S)o`gCQMbROQmJl=C>a~.lq1gJm;O(U/!3L68.#0p4fp$@"cnV^$=!/?+HnGA0M)H.ssyic]=86rn.GSyW_`->;8v_w?.WJt(.2xDx2HN0/6qdh8!!0QQ91=ViyJ_?dJFNHhpmd4;.;N9)-UNWq-UQl6<Agta%>%$:*|)0g
%9%$*g"<sb^<Sa*I[2Fc"tXBV5[7c}Jtdvl_p/B"aE]QY"dl
6esCs`m[Z6EiX^@*`hK.7i;YFeI3Ps^i`Cv+#^y1?#BJqHMJ,_I9%M/X0ZZ]U-*j*+sP6AqxD-HO":lZ6DPIGKK[VVuPV1qv4xdg1uS.=.qCkD0t[ZU#CnhjYc!/I,)g=Qzi#kX1&Ya<QbA[QFb47o_`YTHY-`*r*ysBg4vu.m*#&6rCX
08-%~P<J+!tt"JZ&|RjU$y*!Q8)&Tnmg1S$m@0=wV@C#UY9Fh,5Tr4*m3[H=k2(Bhn*<QaHQGSggLQ[!]B*<Ikc"6j_AnX:Qd27cP@*6.F.Dhhae3
4P[)cu5IL
w5LV^NLS$.
ZL%GKv6M4QId20GC5y!09*o|VB]kwj
Jy`;0-Z7[yzB>CJ*dYIL2!rN/9Lh+r7@bl|E{F4o3m[E99r*/d"Q~2lwO9w/|W*%|,,oJ%=V{Lk!j]ZxhOqvHq_tavh^lS/6aD3h$%Jg`hb5b$in*5XOd4LBVSnOTxU]mCzFoC"-_ELTHLT)kXWL,R-;bQC,4#mT0sCNd0-Q8b33F%*1<=3>[aPI#Y4C"uW&gNI08vYLz_~[.$W$*nNW03v:usSeB1y+TG
-3[TiIP}a5#v98
4fx`{q8JC4n+H,50rd%HR&<&.YNiT#McvCx:An}wM?zy3R4Wf@2B_l
1K-sEyR0RKw,eEPM,cP/l:xRF"c6,5k7R:]iHi%oWKkr:6:PjxJ:h?!!f&1#n0PBRErrk#i,Um5KlNtp[v47v|
}N
.g3]x9K_G_balSiTHSNLq=u"D^q=TAPwKhA|3;*z$<@6y6A{U_f+bqYO(mb*-qB7Wg+Bl:Re7Il0"R-?:+Ny;neN%Roruy>G9}l
#HpLLx.R+u[0p="vLXx9Co)n$IUO1R-xqei2o17.6q3R"N?L]`k.fAb,ychZL("
Q1_8_%xB?)#*&NZ"C~--G=D~*W8Lw<"DbGj}Q2lg[oW=U)g+A[0WNDZ-X?^~p2^.R_T_jCX
JL
C`MPV3|j>6^!x"e!mOv?<2;
3n*RrA*66X-73frAk<`5@.{-UJs8lX6n+rzX]=_6}YPL=LIpQpGd&3)04Vq9GkDF,+E_WpFO"_DSn59WL#A2#
8#*Q[9lfRgy)gc!5,4~TVG;.{@@Ic96^PxdNN5R._`v(ZoN_V@]m3D%?~_@dmjPWPY;EYp,3tZG1lrO(oms>=>koRuv=sL@)3P_I$lCjwE+3KVwwCGPQn9`uz??K];C:37X)$d!RXp25ZfHM;=^"l<b/BK()%Gah-l8d;:X"rj4n4;
"Z6+4bL0c.288W%,W}fl-?e?0]JmAy-WCiT&m8F_He1|!Ln>v|et>>l7[0j+q5ew=)[Ur:g+G^[BC4b&;==x`x
N4&K99M=l)]e{d9?7<c"p^1yvkVCE3KTwV2oW^}F@Ux52M&5lUo
lo5D.OTi4h]/m/On7v@.zh&rhN$rBcp3%h@[s<C9F[9`3,C:-#Z`_LxJY.WKkV<*9U,g0/3$$?0:)Tv3]QEoOoU/A.&D"MV
-0IfkO!+,akP2`
G{#4tcXao><,UIw=qI*l8!tw,s3`q+D#%TxVm1?haUucyv0aO(+zry@++ZVa9|ZKRby$U}?zP
FcKtb?[K3{g+>fk)%Rl.oO;nbhM8]FH{v<CPqz@),e/*Br+?s,f0S41U`q_)g!-u$l.RvhLI>PME!t`xSs>k50UL8zJ`Jq1ys~##^;Csq:Xn,u>{Ab1n=/B
PG[+v?UWeqD}SwE{V)-v=G0d3{$Dv+Ma"X@i!Tfcf,L_0EKmF5KjX+khI:$b.%0?v&joC2;&8xKau8$,d>M`UCLc/w#!_`Z7Yh)}5R((iQX3By^J,
cxa<<o"/j`VL.X
>!.#vw=(LXH%1"wS2riY5KbNv
C5`,O<q<myrro98;u!E4Z_!UlE{IaM6L~j?RYXq(s?8]K++e;="4?vM7*0/P{0-!"$6kkek*wZ5-VcM^Zlp
e?)9O]=[G8u_"NL8Q5,6J0e5mutP)YX%%V`s)t$^teGh)-zrr1"d[UrEV$Amm;LO5aQ&w+>0pf+
%Z_Hq9a..%8F)?kab5AN[v3QvG$Zgda/SUk#[dfFo0o`/^_?YXcI"9jt8eQ9o7AdFisX.x;p>+-%y!wy
2hAt_#3uo
h2AdSC:Cf19DqI`v6LMXQ}3>1#l;VmVR0w>Y&BQR5o6XwdX<G&rt9jZs#6_R>,g?y3&}pJ>")++%Ev8|!1(,,[Uk)EOK=LjDN|
c@~9-eayBO]hH9U(LpCZ>[@s~AL2_Ck^|aMRPbn]Hg[wSSHF5ZRy+=jP<u}e8.uf+K/(w.B
uFvkHSuKMPTU^m09cl6)/w~&9YfwrxsRcVv)@(]i"#i*{V_,#&ZDsG?#ynuiyODAY4-u,EaV)dHjqifId;BZq9}>!O.=/OtGIA*54=pO;%B$-9eE<gm:{V98btXGl
IR(mdPD%Kls;l:/YQS{s5[uh_y)6JQoSRncC;8|rh:(ZfCFsy6%fG<O0R7!P
hlGw`;@EA`/
kzGQ4,vjx<>#nbco2*+W;t8,igkvTZR,va1EciB$+aP[-MhyA=BHrRdOZ3/.YT[3TW)NEpNhg`Nu8vivOBqv[a;_Ac7d9QG<1y55P*$y>%Sy7VYI
H#Ws7i@*(7fvj-6:4!RLClZdT`+nFMg7y3,^YnJuJSFtvXgXe%vW$@!n8/[BEq[b>(0$g89d,88adF8bJeTeo"Z,~j-$GrMQ~u&J_PsNT<C$*bsB6JHCRmC7N?bj
Qb*C`%;G.5Kgo*-[[i1dll)bACWQCAS>0fYiYm*jk8W%!o2-j>^3AAL(.(bf<+h"[IC]6K0R)fcXLu2paX6@jfw,f&XPi`"LiuV23vb@_0gK[9RQ!<.Si=@^V9.?"vI0QTm!=ogcjX$@g,AIQv-[?oQKoNWjc}>-NWdYjLZHt*a[[8Nx,xs)09]YBad8-e*3D,eU)DN%%WBUZM#B6xujBxx?T_AA&:/{jbJlr2m
F?B@vB_LZ(_f_nem+js./A:?l(&N)27K0e*K4?W{Tn2}-(:T%WHD4R+X^/
x:8ozp1K4EygaNxf9Xnc@C(A1^_tlN3%Q0]QAovhfB9=^DY<"Ii![
(?9j"(-CjV(.*5)8c[c3
eZMsQ{+3XNC3.clwgl]B2R;Wcb-2;AoTBk>lc#s!e!9hFe
%#%0VLJldV"$:Y-J&o-gl>=nM0a8/R~izD~0f^10db2GOvuc4A9n~4[E_]_l@
oP(/Q^4VP_J<oZQr4liC:]?c
Wk
J`F#b5?Y=Q
(^m"WrcbHX!lUI?QZ6KJBc"p3[C45UazRp:GH]9ho"%<C>mnsebJh=GKJ*fHvkT<n`HBw8fl`3Ya9OTC>T6034P<0OJW
UK&FYF-=cj2^IIbi6=yP43|C;xvKjg>Ig*@3N]MF}!ECY$XAvqJFFVNin(&/E<NFh_7IjdU^QX+BeJ#9mP6f(RpU)
SXJeJ+09yhFm/7d*X@+OqnzbwBnqkBw$$_>,&!8K?eIcU?0Eq@e_A(b1EDv)Wi,lRr?aYGJ^wusZ!";5b9NqJ9D;g"9b(=~MQO%*:irDV1
C:-Q_N_>9$2&-m"U1B7$0e.5pb+b9`hEE;_0MS52IU`q).p)=`
t,I2D)My3gn*J^k=5QLIp"J(&i5=~P9!;
ML>$}H}2Rw(rlcI@,^?Q^tuyyvms%Vsqk>FQKS$(OxxHbIk/v*oHVcQT_gPf/6BH_<.9TU2j<@MY58r(`#FkB;o:9^5de3~*Pa7E-+%/&P`6in6ILyJ_@=I!
XpL&w@:]j6pR%[]M%0?gJKdoYgJOyq^Ctxx$%AA/#=4,$D?Q;lPqZYl)9F[rFkatDrTxBN6p]g,U)OKRf_bY#(N|iC>ig&YW6DK2_t#g!{$<8dAC8tn-j$mjfCA}[I2F9gB6Hy:~$&F1`=UkpA^oAb5@Hn6n=<0Z#!H6;Bb57^v6Mz6W#I=A7#Zra_@=gLwvd5mA=Ie:T1sIAHhV*"k|a!_[_{H8i$i~hMI,XSq%QQuk*W`i9h"(?(+R:nr=)%QhftNOV#`>Eol?J
G;,d2%Tg!X<z8~%|o`Ke9uQOt[Ty_eYKf>&s]A@V4=M$(8P]dNdd?!^m)0gg]`a`UrakR<.Q!L#Si]I4%#HWxssw,6_dr1^,APm8oi#JB#?Xc!CCH<;B;:S%9}x,&jbTR][ox+eWYqh;"v,0m8JMVC9pZ>bwn<
$L6q!/l;H7H"6c:K$vzZ8bOv0L~,3G?JF&;r
d+ksBA
`"RNav(;4Z{]_3s/
g.5E^&qBh{%8+9D[vAH5yq5d^AB~sV-LffFw*WVnHplmqGgp%]:1tJTUo+dJh_*]qzr:Ktb$20^:)b2Oo9NwSP[<N]).X_J1ld<$ym
G<Q=~Wm?IkF2liZp2Zw%H8,NO*W#~J|&)0&4Er{^Mr>=Cggl-)/dIZY%tI."9pT<fH<$RjA*Ek5c+amBKEBB6)IE2)|yHu:GWCvA:K2!Vl`=p,W^Aw8soaiRWKYC~%vtY9Q/]k`
57/]kq
["<=Y$c!*ex%>+NW_4EIP#HKJXex_~Du::gqX-s^mjJjq#tOr~0Ss9:28q&+_vGH#:VM>treh"lLjbsAXBC<laL2M~9}dqD_cKi)[{PYqnBgj@-?g3SL09Jz_9AEQ>19:@/[>B,lQvmTc!I=0kK*FgWHk9;mX[;hq;)NOQj{XJ<AIh2O2#v9R#>LBtYk#WS.&TQ{p$#Zi,E61%?Oj`
dgV$*Z"gyvkb|go*~8v]e-IRoQaLX+j)Mo%7gi6iEst6]bQt.l+KKsUU^Fv6h_cZf!1.*LqCdI$
v@JE-"aWQ5@N
b#6*oj$Xhs&Clp#5$=`5SaMi&yt*l,f|IZxs""iW6|)NI_g8khqZ4yB=l`!_d=mUa$M&P#d!+rvP_ip,P`dtme985-5e00Ai;l,2C5w@(.CAuKqo3hEc8hJ(%[-9Be.TtzNa5O9aUQw(n|6o-/N!>@sk!6vB
.M!/jS+;=)HcqxX,V*dY2DXM7:L/la*"^_Jv{^Gi}8Z;q0pK>^GRXtN]iV0>,K-9CXe"+/=M(CUB&.QNi/H7eMp`$Xj6o2^2#-?!{)?]*kFb$ihDD_lgQ7-3cui4f*+*&A}wIgjmL96p#30G[1AUE^6HDEnvY<j6;O3t5(X/qX-U+:0wK3EfqbgE?(ULLVQ<9*]jrlf&$nT3mfO+7Z9v`7GF
!{oPAJE2
Fdbf_29];ZW<08D=EJs^j:wWj&+*
3EJp?{^83(se5GYRl[_ed-vJHM+R?1DC
/*F1-rR"|^ritP_d(cawR;+`{G;^HBO[-Q|Ld0|:?BV3!5EI),an7%rJR
%9W4m!WCJ%UWRE)rlRIlbaHa*0rx05A5(V77O#5JqEwY*7}Wf,D]<L3GQm1e!df<6Y1c_
SG<2wm!kuF9FQ&d=N0?6m)WS6ZTic,4ECxerL0C%Uj9&p#L^:m:l`m~GefYA9H^mo6.LNCL7{<_(?_Qx<+PR~JVPS2Sb.1-"7,dlq9Y<
ua/+[I=ku)pN5EKo=1=Oi&j[2$xHk&(a<_?}=[qr"`s("8d79F0H>6>_N:Q53u0`kvDb=WDJ+ErSZ_5G[EK+r6&.[y5{-]oQl`*+[!_[1#)b?(4R4Zkw(Lkq+~<k#D$XWOM8gmk<d+9uOxZ}c::+b5#|u-y@^y3]0%1C"7N"WmvMAkZqOq`7T+wNsKtWqne~s<#eDN%6?myE"Ff[y($d;{q8JkS9V5"Q`ZN5gD,a"pV`J-)!HxuSb-t^,|:[&}3`W0mit*Dz]$L!^!QBV=0#yCes&V`%EELo<YedQt+h#QF#8]>l6pgr&X]ON+?Y)l-Vo-qT`7)y5MX3@rHyk~Bsrg@yD_<Z
3Y5G(!L*[A}Z$g?Z317X+ET8ih#W8EYwQrqreK/T^3gp"d;8D!ly2Q>"vs9.SiSfYd5/"v=lH0NS-k}]2C<5VT[B+[`7YqvcBlpXgNQ<Q
|4Qg/m0<VN0<"7eOmO?&soo_zm1j-W5;$U^UJk|40R]FQ=al+R_;T2pHc@;P;toD+u).o$liO3:oq%hF)scW,8gGt_C(U464>VN)
_mB=wcY|rK,(0-!-gkT$^&^
n)@OY*f;O47-[P[4#n!a*6!8*d#tvFm(DE9pA,9R.oRS!<3U9%m-!_w-AF<,u_B=1k`|6cn(s>_^Spjc&.r{YcAW`A:(m!j!J~C.dyt=``(z-%p*7/]QeVb`HNK[J2]b-x0.[{OXP`fXJT/BxXDl#=11Jn4m$u0FB@]<_>S}Ly+Pr}lcIA)/,0.%R(JnO&0foKXvv,F#]~cz"0o(]8>ss]u{97p+)]YjlCPHxYz$itOOp
9:*hsj8OAsa70T7l(S>Px1_h=+u]3hMo9ubQ?jA!m7Rg_>Bg25CKo|)xx-tqN(GcHICh=T)j"$<@sMr~=e7J@@_{J%H`3E6NZK_`vW_&A4r2Ku2W:o`
jNvf3-D{UuPSNa6p:%NyHH9%)-1gk_3blX8&*.&ZMl;}w?t?^Apy9NhDB;03${r81f9|".S:to
0@efXc@UdvO9frU<N<JWsVrItX[Wo15tvQm9>)8+~tmVdPwR-EPK>rD&uSiexas8Fx!w8/-aYmE[[,9X((K6[23d{AcWp6CCj<{S%*s%!0_o53)GY;&KPf4Lhm03a#R6sZsRU/P;bJYDHD>YO(cIPd$k["WYEuKS)j0J":%<4,[*iIZ"QNAok376s[^c%%%EY2L=)>eRmgK6bPZys#L"]M:OH@aros[*{,5_MFj^/3&J<XV[H+6
lhs4JD4]$oS4)L|T+1WhvP4,S>ADFH0r/g?hah^4nK9;0[9LDix=b$q40-##|3(c!;a1H!&1|-<G-xEd
641I/R.5i9X5:%Bt8Q*~A;Nk-FN{F<jUW5T#!VM56Z)Ob3BQ@>l&[z64!.KtT;`C7psi/A&g[8kw2&4I
Gtpc*.D5Hate[^3)3"o$]tzSg]`]v:s2e9Ze^3u1#Fv^kT<&kwb2bSY;3#YU{&+@AgqqZ(Qw-%I0CgWKXMv%>>0+VXlD3b[cCO%VCR$Lda^=D"?QB/m(vqO$ww*ZCa{;ut@]R7sPeWOfQDP;xf`$M%g^trgT6/`U<;xeISB?MIF,SU#yTto=lAy6J
T5`cy1IFar6R@DIe&4yu%vb!ZK5*<a7k2E|d=T
>^#M)@n?q
0XGM9]yJ7t$=dLt!(3H-HKTf_XTzLP2TS!PZhuT
p>WhQ{k9b6uC#OY~Jz5=ui#U$KL@mU6vxaLA+oG9RtfZ`Bb[Ox<t]sAz:aF7T]<umXj!dP04Qkge+]`ak]*]AUw,WNOOPiItP19Xpk-x0yi5LR]AbFMride|:M)}@&FG^Q#IS>L)*7Hpb|]O&0fBx[b$*adz)bcP&}imPSiLjgN
qS=X+^eCIcKkyUw:s4v|mYBpl}bn35NpFBh@&%p)S%x`<@v>D%ckjOrC%Q#`6`u{y<Xgv7>;Vl6|u_%"LMKjE<QA=jxiMul:Z5%v/y9lQsM{1)I"!TUMOakBX9Ejny13$@R#r|mW6#hz3_JU^vefL7WF
if#p%ynvaW>k[PWhLg_JOYm`9lfxD6`w!iiZ4tsn"<3I{GixaBF</R}DAHl8#`4PnJ|sw^}qse@M=4WA`uqkr%|=Q=;GonIc9")OfWDnZ)M
R2(%5N0Jnd=(%D]cBmX1:+!1L]Y0K2yr33mDxgG!Qf3F{sZ&,0&eCg?tGNucS6DSE3VQAV,`"9e#p/kIz<=
KCR[put6=eGG5tc%oxlHN@!0p
9YZbNZsLT@73O7x:5l[.H%?=yf+V7XDou5-N+ufhs*]-9ti)f:PBh6=BfM5B4>D`&CnfA&6({7X`AXTt4`}e|;)/()M]>Ya+~0Oikmun?.2S}OR_
TuMd$%=tO#E([Jg&rr@7!]t(y:qTWb:!JJmdAW0=Ni/1nln,[,FSgp*yRDUy^%&hG
e|y>24%K&*#*qW?1po#6.*F0K;ga*yH0^]+(8-!IJ!Wx=LtTw2-(%uY+y912>0fA1inE-5+YOfQ;;JRWtt,nt!("K2Zi[iz(KyAIG,Xe1SrHExWvVxs61&]
[740]:+ucfOTlBa<T0sNN["5sPWG-#yTI,^~x0DmKdb{F@a-@Mn%[SpP:+lZ1e,3<
y0!(^t]0
kIB7pa0-803@K,QE.DR0^ff"zrN2xgF_GcPm4uN_d@Y0U!L%r_v&$7NK!d5(e76*O!Fu"`+G%Kk*koM6:x;R!A"w"
iF$l?L8M*ffwZW/+D?MiSGRgPc`1DpBvY1Xrv^RijgdZ|v*X8!r94g;gB=T2PeaOgPrjk48N$SCsJeuO$MEnJ--&.XIEqy?Ld0C^[#Z/_Q?uPvEB{R}ur"ikR&7#.`QpbI*`)Rr%!%uDX@nGXe3wh4r50^fR0x#MxjdJR,zF9A{&A&XAFn>WzNOX|"Tg#X^A*Od({nijg
>]s=x,:_,)aoD>uG7Is.k]=LnS9c0^Lo87wNMsn]C;La~f?<p0(B-3[[rHS5N$l9jpOVndNb3Sr[kEQge=n#f+w1*Ls3$Uxtpqa78v</l7H5=Ff,qXGm?#io[4yWi>o[OP115)$@rFHKy(vL.TR<RS!UGGMYQAnYkBnjz<5cLsvq0QA35tzWH0V$u]4.&YbcNyO/,vhyK]"n}TULs_[_YlCIjH#g~VHHEVHK@6.2l%Z6$ioT)l0SXG>@^7iC<<Kch=rr7revyUyLYy
6ndml50sy8w@F=P706-vg*vX-?4VeO4#+p3xV3oPR{h?K^q1L!Jxcw:_nj.6Ez7I?Wl`F+eD[O:ISMYG?owcP3t7WjowPZV]8?o"u=&53zGA^7]0j0[Hq^v*;>cg,BtDIVy*_,:
8kq)$/:MS;sQD#%WHtwfo^s++wc]")2Z2+o+yB_-r&k*hPgtO0bf@]cM2vkMb)=#(_qu=_kgl8/TsfF3QnMU
}g1ID,}3B-g88D/f]s.YYr~s2^ihOo8E%M=H^-kGq8mT$;7N+"843lHUXC|CPY["6UtK7aP+"j[I2w$x-Tm"}XB3[tNp;I*l?4p#1k7O}-"e
V0y#?Kqwb*c;ISid-vo>Ujs"Mt%DsP%]]/y8d]5c&-_05$u]AHUsxV;AWkIw*LDW9FUr8L_aaOb"4>I1#amfo!X!k+f0Tj02e9r_<CpH@:[5sdB?(efo@C-CT|r^NbuS:RsK`oNs,gA9aydD%CXEih>3P`RwkLT_#
9"qi[WdBlNb1Gm9lhxhzIXKr5Md4
L:_y@,ReKBq5HNaMt4I*vK|h$_<9xrDjzYUsv)kcE,dX|)LqDAVmcc2A7W>j!QzDr-,Am^AebNTF<bvD{Y(hqTwXo]_hqprwn:#=rp|L>yVa;h[EM=Bn}tMf
m~eCL?nvHc?Cy[p-$aWOY/vmU6/IYbi8suCN9nq$4qdL9:6N/U3OU33bC$`i*YUy&0)ujztp&J&aRT>8PY;u,+)P;p&_h-vTKPGZo(e0&l+:LG?Yx4/3?;=yN-4#26?VDLnqke&J,Nl(KR.h6Yf^@9]"s&Fxsj^|N>t[HSD:8^P+<P:3l#dYb#QAN)-GA<D2W=?M
=puG1x,g|1&r8;.9~ue5u"@^
5nF5>nZ
ehc%NIGlZ9&9l#W6Ah["nLy)Q|W7&!xERr!:*Q_?PX3R80H$;|1ESQ<:]0!a5GEz"~0=d])(CqWzd_Yu9%tK!=kFrnFlR_Twq+tW^1EMw|0SgHg4Pkv&^Y#IeC*b1F"!lQTW=v!TJR4~7pR,V7U2Rs1cKM2@7Hu~Yq.qZ#$qD]rZq4;f`!V$0AjS[BIF?9K-"7v8j#GjDK-:hz+,&$yYd{DiLJXg+!Es^w)l0l8SN)J-F[TTq*//XxOvRO,o+Cy|H1]eu:<,&#a:P}OI.&x,Bd@C+YudeY]p
]Eu1_+fQp2Q1Od[WIM:KmCd8:$vlZ-W[2;e36o/(dgt"B5f5lp+@wt)&eq&&vjp,<]WRiV*s
BFt)*&yo>e3w
Ot9!rS;K;BEUuL}jdN:/-k=7#J^Gj:C4
49v4g;T>MUW(mWW[xyj7r8(Xv/=`.]Yu:PI<?"eA+6Vpwt#$OvXRVoC[<dwfYGto5CM$%Gj0_E<2:y3gl"2}Ye.*GPRG$fd#CTj>6FD8eTMWv!jshCX$=!V}b*v*lkTi-BThMGV^O/]&LDLP#Buq;I]aBYZ>5maDK]*,X7,$*a+9p,^Ae2rrN@1O**U2;K%>_EgJ!MYb:CW9-9mOIlG~6LvpvN]^K5D9,8m+L>Esg~[9qwbx%#gzNA*EYwL[$Ja|v.SE9G<a1Ag-GmZT`4#u/*#Ge,]LE<I--kvq<LUKFOeh!;$2a7CI*2Cfq3hQh4%ycfUwM!r3*W`^)mNm&Bx9PQr[_A-OS"DN3oE[8jR^1Q(=rO!:.6^&[J!p.3pC"QBWZy"4X6S#1HOp54e4Rlc^!}R<sR)WY"+?0_k]KPSbl@7VTDg7mD:4J_r*g
KF59)lxe%>DL%lNuCxE}`M2CjZ)165WITmN!w}A+*|J_2>+E1=USO29O$f6!o8eLoPs:*Wc?/+T`;F8NgR,mev[=2v&)5<T,1|J{p
nJbrKOK[nG/AkuC[sv7*%8-MlYC^JCO#dJQ<D1%RP8MkdWuze!]HlU83o7EN/%dB=)"zkb,5H.2uU>#q+]y69)3h+$hl.rx4BRU~ty"|(@xP47surH%P8GE68XgZ^a?*g1E@-6QJJjN.mtw6rW1gN"UU@B[$Esr|#JCrsci[
LACqACE/rbzN-HLUf8?Im"ML@n4^#H0%2tZ#,i#vQk`S{qnH/2Q:xAvd1*}pN]`?Tgo*`PZKgZNVmLAf}n?XS>TRzL7Bl4VV:7(2"T0qnq`->i?2"n5d!AGn9u"9&;7+h7~yw!Q');}elseif($_GET["file"]=="jush.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*hc]XHAs"H%tWN|U.+XKS*L4tuu!IbbTh(Ad/X43~_(![`z(J./JRSnW/1.cRLJ<B*mAZP(Q1gkL[tG`6w;k3MBK|_>4u^RE=dAo5bgI3y9RYR&(j,Yi`T@a:f_yF,Kn:g/<@WE^Lslxs2vz#J1kdxY4bbUs.,P`QW,D4"V`mA>D
:@K0_fPCh%ph0J%$vib?wBqUx&Uhu!+[4q8T5#&,cHvuO,`r-b/Pl4w}tWxG_(iArz)uiGngxC7,FzQRw9
wFoVEW"FKA|u`^9K~4P1_CkDD**fm?7XSItye_Y=H+jMwi;wXthBELyw3YAe?0!-?o,Dcfsx0aFz!/Hr#
;wd2vQ?@th.QY=P>.Ve#y<ZN{OW0.6HVGBcPV7,]ps:nOTBEIDk.!bm24&(`edecr`;#WAhq=4znFp(OW/*arf,(-v%
1Q
M5m`(Tp8>:7:@DT/[Z1r:9##6~ne:30}Ujtf2<!#L!QZ-jVsB]&&>f1j5>(WJK59]G[t>,[tB4Em6t3[KW3GF"=,s,r/y#^vlK#$y=JIYuNqZu6MH%n}/9Xtat+7KAKX]mM"X9yndeQGqZ;%ReFo,5w|DB+&v6:hq[e+03s
U|6KtO/<2]I&tnLW/hZe<Q^cC%SQh6>J+YT0u0AKt0px3sQx,sa4p6]}^)MIf|IY$?7+UQJC*m!Rqa:b1|v4BR_h]
xN4qmp)kMCJ~i=_Y>^a??q_1v[,#q|Fg!%y4WlR]R
n1EhJRD@>LL2ge3;C;
*.s/I.wY%YlYd5Y
@Gi?1qmx3^OK2`SBw;F&%Em)fU!Gu.+lx<vEf<3C_)3,EQ5D7bp2R.4
^kOW5;*LQih
j^0pkilfeoCS3y5;9a&;GiN5bW(4+R#D{-#
:m>uv7!Xjw~9``HkD^@rlet>eE:6IW@A*V7@:yWA3b}<ggNpF]*G!adp!28jtmk!Jygw;e*cq(}>onV[Q#V?kylVHtnZl-6x{=:G^IDslEd^@m@7p^)_Cq2K%K
Zl0_Un=tPrP4Eyp0;3GM,6*iY9E;IV9=G7Wbn6RV"DGFK&xq=56?YMS,Ui-#q05-
aZ?*}i5s;s-kJ2Fsl3c6A0Fsu>#RAEhL)2FN$UMsBY+&Z^6bA$,<mR+%yWfvt_>!d9f53L:
c)`]c!_AV;R194x22]qiahkHNhgFB*9>-V?yC8CtwKgWmrab)ervmjn+L3,u_)|"nv@0FTx9<_/=-rmAV>`oIN#HD!POv.Kx[I)=VU}RSJCW]M9<Q4G#Ab3LTW8!15=F7,KaVr*FxI%+2au^&0_"tBJ.#U20VJhxs[@`sWTu%h:E{>da!Gcun].EOH.1[m2-9X(jaOC_REp>v]_qR@|Y5NL(a
yW&`qj>c,[hu`c[e3d6n?^{%a(,!+Ba+`yDjoJ/d3
*V~4Tm+*%[V$2.o%)pre~r^@93ZU<+C!4:FDx,a=gW"9(5J=}S&uc:zu(WH?e$Y65G!%96hk#TEL0rpOCGcsZvrgWXY7.i`>_GRi]nDO7<*F|t<=O$!-Se86l)%Ui9-Kx(Lv4ta%<aOgIt
jrV4_K
~Yp1/]1w9#3>q2:ASogfi##Lt<[`slbbS0}Q8C2laGGH`j.,oGVT.;-hVX)+Ryz*o;D[=)t3qw"iAhB*6L[K=0H"r:KY@:<.9HGD5"(ry?6@qlcFalN)C@68W*x"EF{,kCF?=Bdd<w
N6e1QU[ci=qK+y]Wp5RC1MXJ1aCT[/^aq<7x=J]L3@GQ
5[C

@f=^_b58J}K6XH8iW&(8vVv)ebMZ=R#y4Rl[f}r7w1d,f07e90"sTy:z41qp>>MibIb`G;.:^Y^k<*w~T_kV.sX"0cR0U#!sZBg5sh<teH]ElmG%^Dd=(TL]IUGD8UoWSH:p_>lF"y,.m=iMQCjpZ&cJ4wS*&[U|1F[k"0i=%gjxoD0O6~;f
NdY*t<WE!5Gp0)?,+eWuO4Gju)y:GyFye1aE^7JnjsH:4y8rg1@?XyBXJ<ymIu%W|RHVa@7C}5o!.
G1TxD^P
x=Rm]%~HHXRG?av>+6?Psg1dlMHjp@0!*w^<`7"W6_q7`7,U5hQw;^#4-9HmyEhp#<kbV]CX`[?f{K`d5WUh
*lB4*_UHA)FkCBHi[Z<hy/%l-r-liaG;+N$TrzH,5b3R(l"is$dgNcHlEm2n1M"dO)9z3&A,`m7glRGYhQs5hG&1O0sjCp])
VBq]EjQol%6-{3Wed"49g.NGbK?"3khI3<Ut]6YmC;pH-]`PNi%Twbt
t6Ue$idUr5pR/CjFa7utI4M2FO/Gyv|0yd`v=hdFUYJj4V|hF#>g<ic9bohq*1k_z3F^mr/2Y(jW0?x]cZ-v5E`
Rf;/1"TOl!UZ&W"/DuT+":O1TYC`kbO4Yq4vhKSyk!39J]M={NN<]m0UQvn.GW/Fw$hrRcJxquT7J/=21mJ=mf#A8,/9Up{;10/-xw6A+mgcj]X$y7kBFj`:~J]$!^%qZu}#f=#:)b<T.UP3.T8kg1z.?Ce>9k[L%pI?Y,42._EX!c8]Clg^w<Xo)TxTmpo3mo37#)RfqHvRNCXM~?`nP>ddIN8e}PzKymE<=U91i;P$N)/m[eEn7K:/j=tJB+z#CI[j
[DR(A*QmWq-~nx4X_#VS4:uzCb`[GgadkT+9vc&U.K<{UBxiH
)I4c8ZXKYsZ;"=84$&+JG{:?/+LPS}h@7!qECuS]+xZOd|grBUR`*q9DuslMIhD
yPYM3WU@4[j+YUBf@I[{fWEg$8u>kY=CH,9cdTF|Fq1B1Wm+:AFHE8T~gPw(=QL17^KbRgOydy10HmwpAPulby$?3#s=/W-k+A*JWR>_G2WP(R
f*H8-Y:Q*dRXJ
js+[}NCFP]-*O@3Dtb<RCevrk@:RHk10J_]sq&N*dYT+X$v)dls%Y"QC]tC8$W02|s~[{1vl&/|,RbU[GS:s~.*mpZ$1Wmv194X5}oZJpY^*L3L=%jWo*kqgwCep
E6&P&C=E=H]?5H>6:/Shpap*eWP^X@<.4ZdP4uUj!MeZ/:rzm;5[mRGM*&tq,TxuJPN/p8]HD3kgHV0epa(>J3VtT&.<X2mIr>
9j^GFN0Tz"|8^gYXtvf
*avD_Yg!*Y]vL#Rq9@]Lj_4Kla*D55D.~V/o7^&7$Iz-#3f,SA[f:Hl]%7e8mGe:Q(hXj*2UqLZ9=N,Pj<cB,+0#ZR-]p[&Pb,jklY00Ic>yeKR@L"n0TR7qq0;+tn6*OkZ;sle=)lKJ1a/X5Oyd?keZ8:FN7]5g~G2
*`3Rq/o>BO&^W!|M21BLRhgnTl{%odpOd,qj13zF7MRi*t+-dL>hgv!lNNrvwNwV9a*fkI~hJ6EIqH"9%K`$$&%,dg}ln/2m&>~;I6TRF>:diSYC|?V<G9l>JkIQ8"pLEk&E981.CYSGNX^LNUIw
2$n3Ach+24]9..Ot/|&;7ATOCqW]pl=v)sE~pFMLvtKXrM/6APEr`gXi-exobsM%va<;FHH;sn#pl*!,0DbcUhC5@k>8mgvK4FijMn:i*e,+4%sVxvuA!8@@@$8cm8A)AH9)x^JgO]A^olC4q4<v2O5Zh3sg!qE,b>SInTFqDYZBUjccstv16"m-"H@L2OZ@e&9R4jtp:&6WS]e4G7x11J[V19S"N;[y6[GE&()D&K]Db@`TM`uVCq*C<*=)F`M1hhWQFu[c0]01VTWhT:RGll26Rf`BC.c!!ag@VXg[VPc*Dm#0)"XKdWP}QWAgq3Xd7jAO`$yPz!dqGA(~y,Q?!K4*vROV?Qnoo&D[`[s!")f&[&1O)L3]2H-l11,Dtp_:ObBi(j%KQ`10U:Bd@Fjm8^C4@>n(7!o*$b3w)ymB[XtIBXJ$l%w.k.9fctN}v^
ZB>O0fH)IDpsVPEQrb)pE03sEP44KDlH*ET)hSbD2s3-=_^M"m$Hs11eY8J/r#D,~X+X%Ru+hN.iOm*fb3Ri3o{k{"?:!>aPEWwu:Y$aI;[=uQ1G7mu3~(LZC@cC@`N6Q;~Bxuue6WDc{QC5ckM&s^p3#$jE$:k%ccm%G54wiUss+KdsfuafW+G@w=wAR7s#1]Ycr.qec1s>Hk%H5??eb_jdwJ}HGC^OG`nDU.5xkW4^]V*xW[LBhQS@]IoH_4u[jshG(_XpK;gn8M!.{ykg~NPEUvtVdIl.}x$m#j*
Ldv9f-1r_pVO??/qEn<*U[isVV=7~/:]HG]Sjl<mWs%p!X8&[LPC{H4[9_5n,mz]>kx/bCgZLRpuS]SU9,2?.vPgG`%;Gx0TkbA]f0pZpna;tLFxzW:4*xxW$$[M%cCthuB73.gp;4db0X/t.ufDs:*
GVQLnSX+CcF0x,==pEjn,
s&cN]s*0{s8[8
Q^SB2_XbAn97?[JK9ayjV@|Pq*a$a@)jN
}(Mux8[sDw|vbuUNz!;@F4o$>sZI%W9W)aCsF:QZw
Mkzii48log~1O,2;^y|(p@gKV(#oN2*9&m,@Z8nZKA#ec`d2QJAFm]-n/E!yLPe2E<(.K0S+8&f@l]VUZBt.opGPd2#DP2#@"La2$E=:1#t*jG^1;(VOb.u,kDrdg)?z).EZ
eV7T<}9h@j
]g@mRP[:C%TE$1*IBH#GkI7fY1kc[<-g6X2WKZt80]cq{GGZHqR`!SS=Pf872mo/+&2Sz4J4z<OW4`zwPxQA(>t1[emw&rE8nufBKLLp9J#pWAH[3/JR7I.AXUNbU&"*|Jo)t-3odFB?Qr`l=`ZBAAiw/?!X
GQbDr7]N@3@AJ]U:iPjrlFG,,,32*tDK@a_qbVns*Oa4CZ.N>(V-]fogG^Ro[sf[DD,WDqndJ/5wH"E}I(#uYqFn=BJB[8kL0x@QasI)H"S{6{F@o?Qu
@A:+@Y}3_l.,G"~5gsNyQHyuKI#w.vteNF`x*whi<_TN7%Rkq*3?|Ubc3FiA]$)@eYnV``3]WEDk72SL?a?hODp;Q`/a($1,s5gcReYiwcgI@m9FBHJkvBNh4nY7W]_53@Z.e6%GM0Xwg6ynk?d"SAv/V6vUVZn4}sbuWtIc9k-%W)~cuFh&8Ed-aN"loUhOYp~aCR`8A.fr^ou(_A#Rg
|UN@YoN>C[]]iY)?/`L5%D3xI*{sdRAtWl1v*fTq|HFSimUJPtraHU[9tsgl0IS%KV7m#W(Y8y|d64ZG:xji9x"M:X+GTu[RI#9lTl)^#8vm=TsVzf-FGKok3+^w/)"4K,3"(Jd"0Jys>]2^V"+8/vj;MJImHz"/;C[r(Ch;A1Uea?Iu5)<N*$gU(IXi1`IB;M*DtI.kOQlVIW.9bw->buseX+GorY
hz&DR*x4=e2xfO`9Jqs76=tbNo$JKxV@qcM5ugd?lFRWbXE>@Ts{7-Tx
_6=,5cSPwd
JSu#[k`kYE1$5}9"y(w6bAdm)t!!4G%.Rr26(Z"P"C#>w2#HolE4_mjxc%tRkC7t$v+23DSBa;j:D9r>yqJcfkq$[ln#7wq^x*v0]N9<.Xn0F.++Yma|>zc6xtk[Zql>JqkC9GXgG3[be5*I7nZyk4CMTq,DWfHUix)8X~.#&R?Z[s.&t]lidp`O5WH=6V[RZpIwG@YX0m#oyEr|S!p"LWO*2F:]t*w[WDe-P*0up6%zc{i"JN+LHr!@F-+rc=Agye<wn7aNxb$*2FZElkHEyo,x6EfVpz2j<mBiy8c|S(GNa">uUWjU*z?*19[F"MZ2t46+c7v;v_yq4Qm#j+*cflJqi@mN>44NM^xHw6$YcElLCaW{siMz)`tQ2itw4oyT=JH|a)Z?I$KhEWrl^?$VwK*;f/]z1NXRyJcM#>]{>V0GKy^Uj*4hPJGFsm7.]EX#A(_hrtHZL@ss0L)b;n0PR4qn!mog<HKJX7-
oKUYe>6RH"5,nj02?@8zw5pKG$t#@pn/fVj>]HFC
;KSrcz&VM0YfjBymJs=Y$ZEbZ:IK;u6lG=o6FVDOIw1B^3|SuoE%isSx
L5_l2xG,JC_Iv9w;){kcvlUxAQ<=SJ2{G`
=!XUEsi_nDu0sHz=ba~6kd/c;[q_iZa=
$DQdB}c$^Zw/v~PS<t]dq^5|rRM8dR(,g/Iv8Ept5KIV@w_qm=&X!k$
c]LJn~8fRSc=W)BCtvkAoFq$
J,1T>QMFCU[$="?@uR+w=h8oloS!]WeY:QbeM+$8Ut)G;pNFWhzwK,{m}v9j&aQco+aRtOf_YnYk9r]lDVPUV&A4:3C+]W"WJx&gSH>877yONdjry0Cf)]TDp@B#wH;-mba]A**KgmPbIUoS
=/DaZhW;D6H>>htRx5/Um;06wC2Op>.Vno+ZA3WR?/4i3!1yb+s{7!t->/)":,B`%^"Cww
<jnU"HimwryCf34%jYX4Mj[
,,Tdd&^h:t(I]`Kc.f}
8kkiBG/I"/TXLI8S;t2JQ.Sgv:pk#pVEZMJqh*#^^FnSH5QT^wnB@3n$=1SU~9N0L#|J(q3N<ZW4X*m#E8f(rtJ#k@-#aYo(}Cc05itHxV/c(-L]4F4ylZJD.x"u{ZdqbueX9vwB/1girj;Ku6D"H[6LaZ7)oe30?aM1;GWfA2w`$Mc.f["hIo,;v]YU`<DNR>E%)bZxR"r[)"Ux6(rEmab*uk{v7#;ugUGHa@]UTZ%(LhS=z9O?ogD=F^0Uo[U>"DbHC2n%5XT
D+A+2L^6ItN0N#FF
xqroq3*o2SS`e{b3`^cP^>2"u,/Z`[;Zk[ALA(A~?w`ul{A.+BXmJ+iLlH;rg%87O%n>7>CI#FUuL@YvoIC,ii[BI)CZ`RbLIDh_l"xywG_&-s*Hm;TeY!Q+OjFT"].je
$FhP`SHmWk#Nbgd5BIM|2W3xCE,SQ>aj<Fq]IMYX`{
+I/Qa8d.*pvNdR`YQa%v^rMdB@NpYKeAV-OUF1,NxOw5)m06%@IS@fKf"@1K:`,N]d=lNS|LgS&IM+%JdTdXXi$@]">hI9F9~/jTcln&!8U*Hi>t/<K5/1i
e
ema^T?P[<P&WSFM)._EJjSw.q>zw~oo/=c7Aw=O[bK7JkQ]&)<gF?w;[!`a&$e%M[(o:>jDp8(c4C"hM~i+rVH5*%0(u#y`Ce&36Wr:yWdLZC6@F)f$!h#q#}9*=Si.`rUo@]x9[,=W"Zd[^+s0FQwkSVkD]6gh+RS+G8(xfBq@6fbW?d:U^95i_mywr1Ovt%LEI7&P"9P(]?DMd6!(k
nF9iY(;d@B0uWOHmPs
YI:h<p.8MW6Gu!E;d)NvP`SMPEA9u@61{NpI9NvLer4O<t}Y^D="<5r!iY!FD]F=ZdiTAP@3{Y[gz%JU!$kav2*>W5U*&SeI0,RF*_F6IuuAsxnMB)Nmdm1+|;D2~qPgNy13i$gKHy+4M?TAR0B6ndlNC-l>72HnI#aq89199`<(PTImUVsIUH<K}^Zoaw~F:tMp_f~c|0S^TedP(qJKeqL+J>SDDWZ?k&p/mwGMYvD3uEnu2CfSdYHg7"K&DY(DQb4DJ*|-!aaR<C%H7f0Ch)"@?o5X(&+5|PpHFP`^.O7P[=2`^*NoK;YB0e+*;B=DrdwqoV_`<mzFHsKk^7dj3&?HTk)]7j)DX272l[W$8Y7("Lk7c5I.8^</l#z:z5^?$a#*$Kp6swHymy0DrA$/CQn(2._3$:#4zoH"Aq&x[^[m@n+=*)d1`=jJO$aO;E)FhX=4kOvI-nG#$!{1DMX
~hl&Ahr+4jA^:0W$VHny.^g6Gl
Xx_gX1kELky5MPC`8U!6u-alX@g`v4V{<[W7sQ%{PI$R"KM7i7kkYw2<LTkc4Up:M<hwx.H]g4,d8K_|>P5tOiAp%$xgy=hMh*I=?au{+C]buX)Jn0?D:aqul9H"d8sKp7c"6T&I(E>HBbKOy)fq`:Td6=&94.Q<"1sC]ZkxlvNNw~QvG!V<`?iHSb=98+@q
tEtHu5wPadKK<
:1fG~^i-g:zaJ
"j_y}3z9$L47O6lWs]XbVqOm$I1tK$I%@];8;B(Bp$W8e$FBk6p27Nq1wD~Z;)B5=#j-QH%94hH*7p0#S=|sKQ^lX`!tij7aPo!C|Ds#^hcBBPhuo6GNRcG*YIz@tLqRb`[GKCZ#y@<AH=>CmKC4;_-=M_p#!R/#vD_u^IgYX<j;92#ogOais?e!n-&d`(Rn)koyokcrVOjH"?DD)"ox(:lqSpZH_6ZF~OysrahY;<KJ4_3M+g+r9LvE4?)HVo0*2`qF1ZRTq$:RDDuNvh,,CqWi91Vd,ZNK[Mt<h/jHRIyn%r9%2=6&?,|[e!ggds8arqnemBBP42mfw>R&`dox8pOYejHjvRZB?F+O>oex2j&REE@?S2=87U08G,r_Nw:Y]
R_p3_riqZqMI`Q]aj!p"AC"[x&/B~3*JEh2=(b-d99lYMtI[1xXb"Xy;#sS>b:s"papw>Y,vE#r"fs#1NHkrq,u%S"-l$o.r?eM[B5UkBb/wwFclg;QfSx[5ene)&7mR}DpmKh+El9yEawg`v,G!V=j;pw;l!o=sjj$<^:308HVO9*eTw(ej>_65w;0VYhvebFn<29S
`khf/8J?NauiOUKA64HBlhgb?!55e%y.3rM1W>8rBUPK.]N7&9t;4B%2$4nx,rpFh2^7zwy;,7!-g^=(q36H|L(@:
EF3Ty6w,1Zg
OUP^VWM
X6;vYc?yVvYchDpqp4[d!hj7*QiYC/EEilh%`j#7GhhJN`%,4d1g,B,1ZPXR</7w^?OMgIbCyjxAXi=p"LA;PF<9;Cixt,C(([8cbfhb}$W1HP2h0>%I~G;RRRr*6nky67oW+h2IPPR>~s"E4hV7:,^$9S
48u&`c[b"/%Vn5s[G`)|a/$.Ty+QNk;tA)KMhZyu4])":Y)WjM$imMS!.E56HL98W2XmK^x}to]4C.:d/Jr8A
<[-O5M=?SC)xhB
sbwDVO79~C<0&mV=T:V&41Z8yRCq)i_
AsFHa*+YbsRZ[h5@,uQtiZ8`u^paQ]lB0qI@EuW]$bgws`I@|
Qf
3L?>(2C2O;o6OV^/b!63S#AWsOcvu.N`5
2%9KjT5tiec189Z/$Kn@]`wUGo3L
(`+RkC!C9N8pf<%mhV$=Q;+(nrC/,1GqadNH:C[!j6RB@E;3zxXg_Gu%M-jiyw4:^ru=/dM]G_~ecf_wajRWT
-CTLRXf_Dc,0a82koy,y]cM2Zd6;T&d5-jBjpFC4NvwFgHH"!j`U1t4.#kzeE+iaNd^J*@m@XvBZGPKB!35bEZ=c(3)a:^o:V1,(KKlDmj3h_4Egue|D%,,pIxCTj9h^Ir(.-IQ;>.Z9/&MxFlp?
JCF4U+t+$mVaTN-CLfOnY|PZQ!@Y
@cSixdwqJ`6@0WjV,o7lm*nQ3=4H.Q<tf=tBibDsa_%^!+"D<Tmrw=yEG1]Z2h-
;%.?]6#2wQ6!";1_jQ+S6>YNW
Rk8/nF;0s!wH1;{B9vzv;ehFlW[QQQrADBve
yd9t&Y2Z>kU(^Yp.^8=wtuFT==WrnOGMd7#zK>gRTSGQ<Ga"5DX_0}85?I%-ZD;gGBg+yQq45O6nDu1z)0@2L#a4M?4IO`hfG-kF=(]m-OFXdpeUG
?Nke?v43v|]Njtbu[4:AV0ZWJIua7P#ZFjW[Fk4Jx.Q?j7WJAo-Y7.I&,fqq^vXA_@1cma)-
^CbA}
"@Jbb5j!W6xaMK1#jsvr:h|(mHt8^#kNFi-^3WTXx!.X%nl7xjXb&4obl;fTBY
yob%Sz4yh[um&T>T/$A9_oa&
`m~=D;dqB](4W--u5A2//7Es"L2o
/D=d_b0Lq|OB=wA@Qa?
08^JsX"GY4puW4!fZlLJ;O^j4v)l?~+c_AXrl.ev="-KG--I`!KbCla.$cjF&(4kX=_-m0j;APfV
{?RfhfT^MMr3eS<@7`+7>Z!bh5|L]>$
?(U`i9koD,KhBADCe@skmZJ;U^HCaJ9R`Y&JP
S"cXc9bYmxnb<ZFP@4(E](sRblyn)i_[m_m4J+|Q78&v
Dq:#11E;QDZQI-on75GD!4Yg1z%!
}?u3cn`rr0~WBj>j!P>FiA]"PPp6}0oFMOd-;%s4X"|#)G7R`(qT*Zm$<TjG%Ho)Nd,2S7sLFaut"fGr+L);#Hs,&V!**Bk-pbuU=P2YfE%l%W>[Id2=8Alb.:qAhP$R72A452bra8;M/X!/[<91og]]<"wi=^u/KohVYmz&x;dqwTMI7SEeRF"XSM>R>3B6B[=p&ah8#r[`
5qrrm!6TI:6q#E/>:&7`dUN
E}_u3y7hK)=t?MY^G3nV.HTC5r4Fxe>Hlw</o>%8Vrd&%[S*w>.w!H$.F81rXV2R1`
J0sV)lo#u1SLB=S
]f9J#KVbR<ib:oY8|au^;3Opfo)C^KRCm*/$J%hs5&90X3hkC4.K?>F<SJQ"$!rsF:Ipc-*Y^8)+sRDJ^gliqmY4ubbb:4>q.V]G0!!cL$d,Wmi%X>Uu0jV4WHITuvJa<5r7jbe`Vn^dt6StRt?^/i)mJp6YWYV8ay31N@14KL7g&czil_X]=,.+e55fMTv.EGlA,-5aWjk-]mq!()8mMaWg,a;uQ2zJwy5uU
ZrRAUuQ+-CPxW9#vJ.CpxJhP/F^!D:YK/.g:9ef`3f>D`&"[Q(D_],_"_M>E67HKF09#2"2?)+#9d;#"Q]^08fpqw7bs{,V"#py_tn,^`MuO=6,]?,_#90Y"
*bn#xs)#wge%Ehj8.No23Osx#5*F+H!a6mh@*/"5*$ogfsc=+?f[b}fiHC+{u6OEohE,o;z%8;,L1zMyxavwn^o3%z2m,,Md3+c
tr7
N&$&!FDFG/W0yUR*IrcxUO@:ydmJFS
t,:=jii&3H#m(WgFwc[vO.OI)XR%ry=n]nq:[ei92EhtP2*eB[m,A$tm(lv,gtwL]PTsVtC)VC$x&I1Xs94,zL!THncm{;D][p"e=(@n8bWj.w;_m#)wT:Pd/c`BEh}<o+i`7RLAf0EBy/!yP)B/>29bW;C*qU~NUOZ@SXN_/XfsVGlo.*Q*`a2#=&PksqE&HEB;YZ9^Ku9$M*}TK"qkm-Q_bslYHBzsD>|`A_m?N=])&7k$y:{JxmN
MJ3dVmNdx.q[m=90v0g,Mf9A%2>>Ji;>7*E6#P^/@@o7bc|es0Rx`Ub_9
&j07Np&Onp7E;HR`}xDndtF]3Ko
yGba,O6L3y:$1.ga<"|wnha6AIf9;kXlG7*N
MAtzd6+Tj
u2z!/nLO4[i66Y"<!tvtt"t6nMm0vr+<Y}WrpIlw,A]3vO7IMmoVa}FH6RJltF1h1=t=CchJm)j.U@LaIn*El*
b)Ls1.*i9DqM]L*M5D]wcLTZxg%%&
lPjwV!9n2;;Oa(Wt
>DSqj}Y=JV^O-9iAH1y>KM[stUOPCXg}"XXaiNwBS*.:Q1UrUXeJa`v.0Kc4_#6@
N*b+^k/s6(sE`=>36UV
eG^qn^%vomaMo$bl{akR-`g9:jAtG@QfetX70<V
_;J/JX~&
3r&dIW=$xFU8b<*l*)CAz%d&YbFX7>xSJYk"6@yv:PiK$[>Wv}l_y~KVifx_f+8Qqbcavyy7K4xasxOFy`n`xAqpEZUwjknndZE&U%l/@M?PP`p^AE]]D[pfQ?BVVBS*Lw&Wd"LLttltI~<]m:>NQoZh,=yzQR%xgHkNjQpKR+td7no`c6u9z%bJqfSJ*^vJJOx?hk,-Od9,X$M9SLm<6[!1F5T
!H($:bytc48?YWJA#h
V%W
l#mw?yej*MtyMA]3^3ZvN2[vX>LfAh`]!/DIHJ3q;/)/ZPSqCN+L(tFCe>a(!^l=brafUKZs<^>1/RQG7E#C1i6nMH=S{pY!8
3hRyd<4*cg7Ue2vh`Z+>NCQQaC~-:w?a&c@&Mt"Kt=E?C6{a9+1%>IN^yh^Fbqnz#5>OiQo,l6OiasE"?^u_4BVb]Of]6#lU2@UE.lH4EESHQrSu%M>S%jF]Vc8]D:7uR:W/V7D,3bhfU?;hN<]2<[ZGsu9cK.;V&M6c:;JG=0:!{4PZs^L1Na0y`iebgHi0c2_6,J
M"x^X)Er<z?j.d*P3-G]ilHQENqEC~(rH%@T1.&<9i(0&51B4@o+CI=nn-y*N.y;Lk&Vw?L[y9HT2-4R4:*jRt=z3gn1B_!!dFY>J1WG)-]wjoX[grXfHQosPzRxdc7Al(Pl=bCQ(19Co:Yq6Lt(5g!jm^ypcr#`[w>r3%aq]A`b#CY$9S_JHR3rG^e:)40C.Pw-v/ttdFi
3Wa5vlyEr
6Z6YbY$v_%.c+^h6gY>CU4<&=?$OUb"<;NVW.#cBp?cd)DT#/M6ba{kkcE,#4{dR@Q#xm>=|eBXSpo+uJw,bIuD8.zeGu-YjDIFGHg5/y9LnnCy9i-`KDjcnMC-E()s7a;"iY)W&WCABLHZFo3fo*y1Wc|tWgare1VTFemr)87tc"O$>XIPi`$$PmM5xEla_K%<JGEeB=]N[,_>
Zotv/H3w[F[zW#jnfQ^,.n$Ec|C^FfX>^&=l$h<>[0lYuWF@cAl{yi/T#{XdX}O6i[XB0CbpU^S5*g$8sts03`PH$T&mWt^lxoX#PiZ5EC$8L}XzkD(AyusD)N")Z)&3:sGNcA&5+6,G+dhB,a:[Tub
*/<)l*Y-l.X.1
s8ODn>Kmv]sHd7=Y[Wjym(8w6bdz2_MU7Hf%Ee3"(!#!2q<@eiNYjiIwGIs$t*=Z9Z+3ddh7x(L"JTV:/(O_qRz$96XCqW#DkXcA<3c}3:"b]#sLq~c=H
qDhcL&6j(1rBUL`asnKE*)&@S[op
XELKu2k!6c7g,GA#!&ieYsj9~kV7DtWF"&($*8&iDhm4
3"@{O`.PmhrpkM`*Ku4s5r(*40P{x[<lj*
~T@]pM0B
VGGk;;dfrPNrd=Tw#7e=*nsVu3+_pXm.1(Xp5Q4Gqbls._ejE[_vI5!2Lxf](4xsbq3A?zp(9P-hQ.%}EmI50t%Pp^T4/nDC.L@)xtlUL[p)ntZ%HUaH%^HFDIBI`##nXN*:[WWd/bHWG.4"/nF)[ay@Cltj1@E3&%ob0KnHQ7Udr[tZ8Tt5UmWP0G7a8#Tj%(Z4cadrHnHrV=N<`=IG&zm4vgPu(?35X_qpyiGi9u^>.A>>(Mjo0`_V8WR$"xTSypx#7Q!@j-i99;J:i#rST#e$NUXOX~P*,K?|vLBny!?%q-_xe0ni^^dE)p%7a~j0XyuE#o:a_)&zH4Q$Z0e/pkKn_K$W(&HaMTNs[1SC3@6
#G=;O_IT%(t<:|`*9}=oKPsw@HZfI~*-wU=v9A(FR+L;;}8KGqeEPvK
.[,|lU_E04KT_w31)Ug4e!hJ>hc!n[,tIbGkBT]E.)K)`E$#D~Q-?[W$W8%FLU.f,23=[I<5k9ZKVD-?O`x63QUOf]4J:nLB8cgw*,c%HIyPXpii,f8U>b>AFZ68f]dE`+%h88-cf-<47B>#fy[!fu4X_KxI>ur(99SB2^uk,[B>-Rdo,C0dBNK*_OW6
%vgYLVJNu>9>o]~=c06P(VX-;-lQT3Z%0@p%+s}U{NzU;j>;!JdH:<Rfn(ti4+x,ov$>&d<o=9!kiRXQ^%
Iz(-iBb3u>v2E%/Y@G)kDhHWd3He]VKWA}isFdCLodR#U+0CV&e$e{-TGQ93-2vZP!H^%h+7/dh>5IT9=*;>>TyX68#wKFDsI|#>]swm<+!DDLIcv
^ZPPUj$|3wM-")xO-98=Y+3;NgX/k4c|nZ,*ctY92#7H6MZ^=`!an+X(<RhWh"50
G8
>pLOaN1a)Mw^X9XnR2S^^OF+yGJ3FQr|dQ?>n0@ce5Ltq
cA.(NN:f$pKu"F8`)tvfn)rRKZ000JkT@-AL%rQYgZ<Nvs^/(:(}/vyvsDenmKL!0dgbTWK@JWd75(ts"}pQFcjEHCJcnb/)2$z)a#%_)pXocAa&K~pkL(o68_wZlj&2?>?
!R/}b:j!Dq]=9,L,V@?CpKTL114MeMT;^B*"5.v(oOBS![J#UsmlU)+2P(yhmxCC]3>d4@`!I?A)UW_^8]P:ps!U_;&8.Yn;rc3>S4F|cuO#ANO;C%&V
EM1ySHI57+PyFa
v.q]^E?fqE3RE(MK0j.nh|nD*{As1lHnT@Ql]-j$JCle?1Jni/7~Y-9~_En[a/]8hJ7/=ppxv&/G^MK17dZyh4`QLhJJV4P/nISa)umF-vcLA?w`+5NP,]yb+I?YA94H<ax=jZ9;,GNyn]m=YfBWO(Lg$MQ3tUpueLv=B)Bw5(P|ry`7oOK<SDn]Ez8e:Q]A=$rUn!@fuK[YeFb]HJgPnMFk>G+?l!AI^)Cx1[]NJ^&3pMao1cAB$Uc}xxpgM0OE4g;en@&uahpX/eZk+)#m0ZtO1w0sa?>JikY]V@U+2bp/3:^3w1)?,um]3;U_n+6+RBJ7[H
p5!T>qt+
9:
|RqZMc0BSn_v`@+GJUC01:?
qZz^$ZAhg&w
x]POrrT*BYy5*k1Ks-6Umq}xblGd6r8]:ERLVyw0#W#1)CxFUtzDlnc][c2Q;$X/,V7+0T&*[PLO2kqJr48$|=Yq>EMU@tR<VBqa:mJtd11v`L6c#tG^q#Gvr)R2?1/SL`zVo:wu{uf_BBs-!tw,=gyVq;@Fax/n/eiaJlmPI3I[m7O8}Hdc~`"w8Ds#pC3/5DUF}A"lelMN>fVPSgSqz71SNU{0/BMqDi"Y4.KPSU?NqiKalWiX_])`3*MZ0glk+u-aEwat(Hp>yRsY+oGRv8m%5=W;u/.tFjDoB^A%fZfR(rw2*KEBGT|1rKadSu5W4=gtjGak>Yb=n0".A#IOD%!Upvf2fvN)GuF)z0g,XeWte]B-H^(%d%GFU
8"8qwpY.)GW/atOc<PxOp#AcJ)9Xtj}].Q4")x33@g{Mg6Xv1l:P3$W;A7ign-xsI2g,g6[FU5m?)LuM}:YyYruZ$,>g2XNm6d7`0`>vS6/oqLj&mA9)=YlRQ]6`Ef1&ZAQ/}N4;
@hMRQ
$cP.N56Y19-N8q&33pHpM[nZ!P8}["0
N;)s"igOj45Jfaa".cwf
6N#x@vx>u*;tYITXmLO++DZu:"f*NU9)HD^?MH~
.lWBYesOc!?oET`yt;sU1IRR)#p%ErfkK"@3*5r4$:b#2xFeLiO_OY;%OS_Vs9`8:j#bBm1OQb.nOBom;AL)$-liof}V|YG8rL`,T,M74-$r1mmG>n}3[N([!/Mu!nR&K:
_6(}7@V^xop=fHHyRQbTVhL
!jOx8$qj$Y^y&@9eNEl=4XDy!%1QLq.z>9x|8zTZ*CyG?
v|G;Hj83]59z43"6_I_B"$vfth+D5GDG&+0%eS^6JapzQ.gXm|[|C0;cs,TaIj*
dv0KQ&lIn9KM*|!1+A&|j$Ah?M18UOihKR2LgY]u0_r.HuS/R.%aKuGYDjDq"_Tqt[Fu((F<QI7w<Tf2(dAc&NNONDF#ZXau>aehtq7[+98F"SPcu])Y?<8)D06EG>*&=-d(=W(lQ@4qEUYM3nK^B]$+[zL&`9QJa1o*2nFT:u7k9Fq7BH?iC$Y,$
Dx,{?y7vF/:KMT2-bp<x(qpG#SMK7$X@Jv*4"j5Z%M98dU2:/2qOq^HTcf".rR6-EUo2N1MD5"-z=QE(x}h>kATxV<=u*9PzwD7$OJqKjlMCW.2XY]e&f&dgy(LCtV:oaEMfd=#Xyk.^ms$pTlMwH:4YwADgUy234>[U/"FZuc_b8DfF_8ubw]=fyI$AEO/QOJ&23&6voV3FbPTVr[TmJj$kDaR?8wep8#kddQna_C:Fqi3*;YSUo->#E,6Xq$UwDthsRArW[b&Bk%5x>:GN!F(rf|y?>^8s#Gio73"vwMhv`,t:,SNMvhDe:f-8a}[|dowyk,@lN.eQooe#WP,H6A;+gQ#4tEkhJl/uqUmCMemdy~_9E2L_sAX`)vZ!7~3sx{t?gi"7LCEZJrXOY8.kX-ZV%aj%aAOce+O7O0aMt:$|e>x*h?te>1:iaC#@CVc=/ji?LO6cgau<0;y0P,/ZuutWDe,z5F$w;kvb:&,t=g5JQh<[q?Uc])CP$
7SKVxu2RY$$S=TUXE.+XUu_HfR&X8~4jw7i6I/OlOT!pR-eQqM6Nb2gOdGw?b2v662$GA.HJq6^Cn`6aC6O#K.K4w:w?NUz$*"Q2^~E_8?s`Y>F/l0=(G*d%99!&lrs32`6nIKMimB8SBH046_b@,Qw0BAKzi_q66T_1,ggD.N#-TU%rN;*bUCYV`w[
^ewJ@HdxK=R=xjL+y%Ebx`NEz#)bk#tCL[GnPf!2_Xe0Lj4vj!B4fW#>.D%ZeznatQ$$aXdnPsPcI38<xtC3Q|
yAI.~wV`+q{qq*Iwjh:7t4_yL&MR2HE
@r~z#E;MyJhydt,)f,16,9SOCs2yAw/dfZUL*+2)n!"xa1z
&5DMo+o2Q1jd:]dJ)<7.3isIYLryWwQDWp[I=e|5TyeCr4WSKky7Q+J.3Z8^vmr[Df"NqxaaFtW-HXN!8Pq5H#2T*dHk+Wrt$*{2q5{%A"hJ#a7trQ~E<o2NVX$qR`N0~d%$)d%a^%p;^8O9lmgMCy]@O#vw"s=_48F6RlJ/pgpN>z)W~UaK;6lS3i-0oC]C}tY%_FmqtyhyI7<bi22&I=~-&T;x5&fp%g{F@!#"]xbf58l"8#R8lUc5ZC3&kP?PkryX}TEThy3s1^fxdRIwtYCHulx%hO,,=q&[si>=ddhdFdk,-3u*CU;&b%2IN%b2T"_b&;mt
ks^[X{_38w.MJp]!6^`t8/GMIl0=LGd0HUWL`!Q8w7p^"exVPxe3YcVj#U)FX{fUsYEy2BuM2Ut;Z;R6rtsg%"U*o3G?u[9vkEwA52N(S<v^Wa
}=%d<(`HG;>e$hN&VbMK}$bf<;-d}cJ*>#(lJo)!j-"KH(&%#izuNH|,"TY]}3>iE":Ro8OdXh=!_INEt$i$--t%JiVeYMvrkh<Ce2)wDxEtM(2LT$?wDCNb+NR_G"0[mm<c~-ca378U<)249C^Vnvt4?`2$v1A]u"2qsuHEZi?q7I!Sm99(/$xf0h3Nn."9q1`N])xpTpW)<e^hTRd7DU"yYdxB.4p$#gb<rjr-)-jrs!r,6Zv%o_Bb{7*7TlxRnB1.Tty>2OVHb$nmG@U;zSiL-f6uG5V7{.4_QXD4^e^k0d8;~4TjRoP6Ku#&{nb#les7?Nmnqh7XE@Lb+#DLLsTnZKCGBj_yts[<|QU#Z0
p^JB6%t?USB[dSFI[e@jZ7wNdZ?AQBwU#z,%>Rg"3K`f/{+AX(Ao97Z|Nz#;%TiL_V,I<T`bbX1OdSb1:zs6A4o:4!rc4_nOZO*RBVB"crfH2EH>V#[AJke!eo]!bsS&I,9LN.&ZIKg)sc8tVg8@n,/Ee#iG+<,K#c6_uG)r3k(|s3^kP/ZNJ{%8.FXUG!0t)A0<oSYcDZ%G,IK^:
fr)8YN?#&7ZMWj6X=sKfN)],Tr.K9njk;Z,rmW-`eS_e)o,&w9<B_%GB"igx[0OkjQ8("=)xH0"4phlBtF.K(^&=,i_?VN(wV>MReY]x<Kk<F.2eArL9/zg:gN_S#alql,FOz!hU23--]uKJ;u&fjT&6:1BVc2a{MRU".q7{XhwduQ_o#X#}Na9$lr.cXhI7E
]dSTrcq39rnpBKu$P87=QjovjI:Q662rx6I]=$s3Hg>MVA4mO,cC!(Vmj/Y@[Me$tv$ZyRS;gLV3VR2@C;3[NJ9U7qU,eIvL%KH7f.3k*yAM
>_iC}>YUg[ZYqSb(8^:lcs04j$p7S^f.)Gr=C98UPqspwEgH7tfe^s.M|1,QYPr9XhKWg5*35@)<`9wDXA:lYo77C9Vd~:(Ys@dugN==M8z
q`ynlNrt"v=Lhc0TFAqH4,y>K<h?L&/0DI%B`v_TI<CCfa|?{!z,k^/*oEUz%ux>`Fz(kZR!S]1)yGtb?<9BrZ%2u?qO:eM#nJ`k0pJCXt{7&c;_u[ZTIy=B2/:l(hz>@D2PB$fh>E?$8
Tb~B2Mf
g1UOr4=-BvG,BHaK*$k^9a)s(j(Zk>0>A]iQe@Bn;"/Fze$9"4(bq
fn#?>5W_g?yy<w:"HyjDccx?K`>35OmG/2VP)*&XOs^^)OPUeRD7o(#W6DIj
Qt]%:A2zT~czmx3|?;>C
e"x,Ae)u;a6Ay/|)s1YvhD9#ZYEnwpb%3?5UMR/1|MTy%2`nWd4_<L{ZMI@p"KT6AbXFAaUAR_OUAXA
,$dMrmfd~[rycAd)HbqHrSC
*cP]d]s`{FJs7FqXdP9:9%f<a`w0l8:]`bB)&]7&R*UM@Yyj1v/u&)Bjm4/NXR2I7KwN]XjLN3_V~q}#HrH,]>,K
"n)^pC-#2]MJ/ohUTKu5FfE!:u900U&f:wpV3v%5P%4uXX>XEN-
w$VGS}.B5eSiZV)3j~5WpYg<VHX/i%:=/"K]3gY.)L?-/Sq^gzrTMZ`4dvY.G0S!2{#rV"U)>I+e3Yox5|28C3yBqMU-,KPoIx#nWASqy6XKPpLxP.]ge^)(09=t2a1Fv,G74yoq.p/70UU"/H7u.a[YormM,T=~O*[>(xLs#6Z4&CamZ;kIT4wVMXnl+^FvlTX.s2+e$1rP=Fm_>)=Bmff52mD/_HsySD[wa|jbZau<+A5/_*(R8"injc*`iUT#Se=Gim4X(FLpB
fI+es%u50KBDDAe-k:
V;H^[
xFI
j;0Zv:^o,Fx+hL1m)6J#Lw2:g7,
<_?<hxN,kN95ji$BwV_Nx&2*lY5OPjN;6c[P}Xd.E_:25p[7VVW5qcZ8<Ytra:T*DQo<d
bc&:=!(k)$e<pR,E#-Kp+(<+i:fIJ,[oY]%
H>.Qd>`j/W1F.9jgBd4iztUy]D?Os=z%DQp%*.nNaS=jO#O10HaoiLBByfG4/A378&NGuSO^{#-<S,Q",ODTwe)eQ>Qa^/R/M"MSk?-f_[uA3WD+nic5Rv^/vX.s~aN:S.<;I]|e6<>I9=5y@)4$QDFRV"JByeu/;wvch&~rBkobPdIKcYv#)Zgi<Fr:$1vp~Pes-C;Z@^?)&d`
2b0,}kb!Au2FyXeP.-r5mZb0?<J9]!]KoxQ.PKkNaE2dG;&)XlnJ,9h@JtsV/uyy%5!V=YaZY,e!D9f?BbdLS7)AUQt2oK0.z:7v][ZgjCY[9`p]SAkM4*NKx4C99@g.4;mJkY%;M<{R{&Ghhfn
s)q2<h&=UTOKE2n`N?hh+1T;IL`Ax?#m5.;!pY:c@#R)}9$_vY5hU&7LBZz8O?A>bK`tS6m4EJCvI#ll+?N9ymGx2p`
n6L*t0ZoY8?J57~$k"h5`j|jk2x5#N>G}hr*
!(vG&<jm`mwBc)ky0(C3k$n)o*FUGAIe]DtRbJ&B*"/,ebleB6P;+TBkoD[fB%)/FS3SK}RrBIdkHK:U&j+Cr=N3r{"$uz?a3<XMP]l>e{S2e%H8Bm3Ys&@8GE1t!6o{?o:UEU=CMqZ159]%1}4#w;_Z9
"y"lXqY?L1M5I)<+I54pnSfw[sCUj}</yrANNS+t8IxbX9cEG&y>yF)wIxx=W68Z&jXA`?4k]
)uz#kxSnUMGys0xH%tOV>vLQGxL|;jZ#2ulwV8QRxiT^Z0$y4Z1|?]7-Qzl8&?=LN&Jet$7|gb@>ik:mQq`OGRn"9O4iaW(2bb+@$q$S%U^r&bz)uKh4G_%mw/Pt<e7b4jYH]35/L`tlN*e.C!XuN>1bMnP}d_&F=u[PA}NQ(>3.yHt+cyV^yyigSd4/"ioe#y1I=<G"enA)
DdZRWiYyp+OC92O8sy*2p@T=t:Y*b"vL(2GMe+f]=5I*]QT,d,Bp4>3L)PEeQ:@eKp01WZ>Zu0Lf:_MDz08Em6!v41n-;IHvO")_3ndD&SO0=l#-&J1Pj=4PmQr+OGrs%G1o85IsS5w_-j)B7J#akHi=P/@3@r2B|YSG/-|S*s=T}Hwi_U"O}koBVv[OPy@*Q`}69F212DiY4at?rw9$DJegoI:+!kB^9)_x%
sqn$#0I-Imhl.Q[59V;wLI%G^/
_Sx9Aj5?dq$TI.4"qybadd3BpC:G!~PAR&)ugCK@$ujrx-13xgvJL/ifF>?6!UEA!5c6R9mf%jIsuzT*JS-lE{kp,bk@0`=y^6W<OkV($^fb5dAE:>(HfA=c%>:%8u.iER$P8HvL(YLvJkm7y{Wn+,-&hL:@j:_F-+jRg[R:g:gcHneFQR"UQ]ZM3J6.%55q]Ihd-LJnD_5qDAixec$YFhq4>z:*GejAu,rvnKV^XHf6EHT|F]/q@kW#BET{3#rw1u/("dZRhy%uNeBs;btZ[dw"9MVR"A"Du|t>uBit)"5E-1%#6c?bI&R8K_D<Rv!r()xVKR"-$tD^=ctLYT!q,>Al){yM+,%cmX+fOi>1IK+oEt:shQO_+Vk?UFg=2N8>HdYY??&5Q`.x0M"b,%XR4!=x<nD1WcI.>Y&~2At/gtiVw)B&c*XSdb>i3B`2e^R/2bE$Q>U.SG9*9R[bqkR"$.w,Eb]R"1V>t^(=WeS[`<fI`R
EdkcX"?-nv
(|&K3M/Oq3-P;U2sXc=}DEuW&>hu5Lj<(}H#_U"$0oI+NqfOlJBm8x"eS6:yn1;#8|^}`7)%E8#@j#g*v*S0"bFBd6#;XV
kgy4Cn:x_Y<+[!i4"0K9(KI6y6x&=)KM6o*Yg^*j%u`o0c|/=$H6X>7Fesb2XPb^V6!X3p,O#iU)~m}UBI"Z1AKi)nqTxO(Uouw-~g}.0C<V_H0/r"N/YfJJ;uwg|g!(Z8<St;V&P38<V8wuT87^2(fiQos"&l#]A(m&6S4PC+sSz!F#AMVY#P~5RF#.WM$TBKQvjA&3kYC7:w+YE*mU(%;,{Rb#,5mQ>&HYaB$[N1"+Z&5j&d^L#HS)~&-?^=5X|u.+<R[19,[Ptat%AJ_:s[p=vJed8CBxXKu;^,ap^j
fvSXRy.kn8.[odQm,=xjkIoC(i=1/MZQ.75R7
YL^,K_e*Ky*M3F4tUbmpUC3Q4/":MY.Z*
*;6sF:T%9^eBqn$qPHb)XQTW=
ckkQ
66|242<1k^0qGJFtqmMjx2-(Q(@#/h]f2?U1/Hl
5R`Q$oz"{_1M9ZnpFq<qX?>O3;oYhy/q3qvTs&vt8tXvX.xWW%~rdEuOw^b(uLE=
B|!w%PpOc}i{T~17J;0&tD.W/+[_7/uwj}FUE6xH/"=?_kDm
6&r9G,[qLaHAl82;
Xmr!DgN@9D;[g]/0>ZY:Z`M
e8K=isEc9N[:F=&fWq1@GLh->Ar[e^QcNr(2Qx@i;[vX,`u`JM5ft>Zra2^K01OMrcr*2:FBenfz-H@h<}Gr*c(Pw|>YR&JzweQ!US9gNR!.olD|R9qwOTa]E)%6UP(g^i<>:U;;Do&>
*==O-QQGX"HaDDB1S"R0EE68,m9:&^69WNz%h?zL5A/N4*k&
<Re[$!8-C^c<#m<]W%I<d)<p^V9-yVT%(93$F&kFAp%5Q_
|){UwT`()Ti+lrj]Y*:>$[3)ApsT-W}(EFzf?oy1&d-k1
z0,N_"BnJ$Hm3Qr"HN#+goLDQ"Vj<vSVL[SQLb_#/vkX<>
Dq<90,WD0DvW+Q7B3`wciYoFJ*:6kt]TKjaBGR[8a~(!s}38T+[d]0#7Z-pU/08Fbhvbwx,Oj{!r!"@mF-kacv#"0epqT|E"&wOo.gJ[G[3LE/)$#J5^IKTQ?
Nc!;C!bWj|>xvT$n@xoQ-xg<6=O-%@62Zch@^kC
2eU5]=:8jA%4+=[b)Qm+-9mfDV5h5w36pYMt4a9%&&6>HursVFK:]Adc<l!mH1c5"G_r9fig)O;a0ngl#Z8pqTQ"kdu"dtKD)2,NB!CiD+)H@d>X]OEFa938T77y[9J`pb/:RR2s)[#PwE.W
[,A+.%-0ISFM{R49P2gw5SjwN/r(ZW3RbMOJrKz,P:MOa;@_DYNWs-T+v8/)R>k4
x!@jW-XLVuq1h>,}R_j#_<2+-fAD&>
Xi4NBf&fAuBQd4M8ub#dqmp"D`vX-Qv%QUP^:%sXIX"[;r"mm-L*ry]2b/Hf!,[/I..Tr${Q5V1[x2=Hkkm81sR!|8l>
!)6nj2$9by.
&w>&L)U|$9`6-5$1TySZECbC1qTJJ%RK5wdz=nYE.4:pGD&^fRK{pC(fWY-aj-r:93[2_j#yXTtP&V_FP%FcCo#KZd%t^aWY]|AZJ[C7.G5jwhikv8t2YJNHbEbTn]:O?E-H(7*G
X>oP0t.ZQ(*s:#Cv0eGw}s/5TuEmEMO@uE:jOpEU.[K/o*nfR0*Po8gt{06;+RRQn`bL8Sk:k=e5o+bE*#-0XWJf?4fyNY:r
)8(#K7m{,MtdFY+o.HdS<@*pyZ4
G3Z42R5ACHpUx2dV5ln8`L`0STgBh,"xC)_l%b4ce(ACB{A`66KOQ[blxN6P-LBGiKJuVSrX:s6eHZ*1u,p"a!Oc?,t/XSL8iYq5e<D?!t=*iti,NWWx)B)}ai%K8snKBU%u*~k~<8VNbOXlF:?_AX,m8=]Z1a"y>^`U.Y>]39/U_j(-T8^f+z#52_[MAk1F`^YgFV(5?!<E5>eibvhrRG;;,91`Y2/4hr]fvpO~">&L(>"YVQp])NVL3!(Iw&;4;uuU#o;?1-d2R.2mm{_iGDVzVVmE#gVYb7+~8-ToUxqMf%LR3%"^9+4l7>_3F1/aRV&vxFHbdnEI=<Yq`
Crt31MU[sL%0f0TTaqZ+h?Uzu"PMLi
/"A+]yTN-o$F%cc32G1EUE*[(+L]0!,&cT?uU1LxJ*)T:6aj%qQK<Fh.g7}fx(VBO1pT4T5huCN;g#_,aBm$5i`-~6:Uo,hJ~JF)l7gDCd;X{kw,tH^Nz/|G*_B(-#QfY1jQjO7S<sVP9-g=5,
bhI;ya_&qr?7XGVj1I(U7(0(_mbHHdsu<m0`).+`#9li@I8jUxGL#Mya!bU,t;@41Gue8>277B:X)S8/#*"(jZtf^o6*2st-oWGx2b$6:3SuI72=Ut>PQQI9Y`?|ur8^kK[9i63[GINYD/fp7a&R*:;]ARXEqQ9t_`&&ZlUhsIAuTM
"jTXI#.J/w`n*#N_Et,#Dy[TD?)mAty>*gs-)ivy0-N)JF7)lhD7[eeQ3v/3J2c_S-m;iuH*
ay<z88E~)h$pxY%Ij&Y|jD;u[=I3V^Xf5@b~0@:B.>df<4_Lv$Fy$~0H@|%B$$88?.nPABYcIP%#y6)g$%cm.~@9;/7C5IH,9zOG%ZmmW7OOo:iYY?*W9b8i+]$*9#X3&|]EdN5ASno.@i"t"PS]).ZV1Y@!s9H,x,qgcXW<r9P7/x8|
1.WnhZifgNi1{j4[;q2;,Vp!:rfO6pGakefB4,?oo(gC|tpBw+atO%Y(x_*MZf0]Eo
YbA.fvK2*h!@bKj(0rfN$uGrq0Ak!:Lm;HVHIGh(#._%DP/Zp}V_`~lO0/a}w!"FW{/dAl4LaK*@$x7wAk#A,<ixJk3zm4k:ngB(5&C(ap6vIJpd#M2V.0.0GQ@.mmG`sgZB>!BRF"Fz!%-;5TOns"d<_Yx[Rp-:B8/k;Omx:E0P$jg7`SgE$!Rub"1DE3GTGJAL"<o.=j6`H2N,Ka8?cwGv)NPoqQVmT,[)N@8;Vu3mt:NhRg^xck,M[PX5toUb5SABsx`s#qKKCS
sJ_%RMInQ,@Q}krwZ*`K`D.h1n.u9/LwN1"@GX&kW/WKk4HS-$WxkAC-M]N;(La;%F<8Pr#%-kKDbh<:s$pITDA.D:qtNWiUHACD-ujsj`c*J4vU;3DYPo/5NoQ?R=j`LOs*qu]efc1S=s]
l3f9|
B?8j+K[K{HANp->$0yS*.$AT&bUA5XTlfS,=M<GTZ7Y`OSk+j;wKpjfhAu
A;+DuNTs+|7<tZNv$o/
Y`BtXN$r-+1%fu$v2AsO_72tHx$Se
H1e]-bQ@<=vF.{2|&xWy
h7(q[
=wV3+N`w:5D&yfT%
-}gr,iPtZ(JZ)Uk6$-8>020/K{>tkJeFDZVK
$u?Elvwft=jP.1fCf?53o"e_L!hU{f7Ca]$1Ao@pN+3fv-k:yA6A8`67]^#E&hrO2-C_u#jT<W@&?Z74#lF
lt*$En}if/bIi>N3PUsmHdrm:badogU7]>`MBlnv]&}
^V%hIET).bCAmtODX0cLs2QLEY6u^.)"h1I#klRbq99CGmEqC)%<U&hP1hfb18UYE?TW7YpAzpN0}lTD[eZ]_Z%I0_QBP79I@X1]i`T_$_%e4Ln`A?NVTIm.&+PB4_Y2-mNsquYF@4,@Kh$lY$l.X(T@Aeg[A]O3W"=!&[AAqn45#(4U~ijq0[D>d+L@6L|g7OoVMT1PuL75/BQ6xOL)Y4:2LB`^p=X3fu(Y~))9l%iJ.:2
1fAU~<rK0.h>TC<w)m-6jJ+[!Ypf/s1M8u3J-%I)`!,5fN}P"^L9o-mT@"Gnqi}3}?DAU$[ZLX8MXvi`6l6Ji51Y2^6Dw3)3*DaFm-Sj{h0gD=}GChYe;c6ndc!dhE0-
[)H12rFX-I*`H>",hO%q+&(9SkPp>&@kR%/{B$d>y@TB6>ED;Y3,BCDq>j*]bm.iK@J]c7vB`h4itNYMKK[(#=b)0<IEgzVEF(wlfwf$40Un@0k}
S*kdnLS&+H&bfHAk#>>B1g,R:WSswY8M"/c"t05I"
Jfp#_9I1W_H4n3M;r@V
:j7C|ER4427E+]BZmT+C&;0@|#?U"oK7x8]-z]{66GKZ%dT&y!j[8u2!Gly%hwkh4%"@_XInGDxp
1K&oUm;.AQc;t03AVr4dT$7/E!:CWaWKDW<"xqio]5)Sna<8P@b_wF#31dHlKO1RQ8O$BU3EDRM6YJIoNCacE>:{$}I#U1B/QUb;])L=oJD_hK@(tzKgK.oS0
M99YRX3M=MoM>rxanh8SWT";SV3P_W^KC@?4JgCS9W5$owtmP<i{yc<%yQw,W=cDYrG}lz=*N@18p*X5gr3c:we.jpiho!o:L^JF/hI94bf+epOe`Msc
+H_nP-92KHia!Uf3qAtiCi.^lm^M>%Dq&-q`r1qbAXM/3h-avEQ.7o:3StQt%_C(=C*
oc^ae8aXw+>5HjUX%j9+W
$FIjt=LoZyB7mKfL#76Ze0WwUvx_9S,G.`E4QtMy96"*"ma&7Yg!-m7ne0*Ws.P:53K2ed/Qv+)w2m0g>Gy?oU>1to(rW?=t4eZO#;`2h+x(Fsrh<M7(*9HN#uGLw!Npc0MT^m`1l+ppSTX?ib+Hb"F`+!`V|2pmW^Pt8Bsj=!$F<=gy)0[!S]S)wRA*F03%SPn!w@d88A7hv7_yH1F(v;3+y.Q%[vh7|ex=}R%0:;~do6Qo?j#L)U{sJVN5.!{,%D9;~%(K~"K%A`{Q-T=g$$#p:;[EbxniJM(%KTj%,Tx$EHfH@Y8B/9+B^T10$Bn=2R!%1/V79#U%D^"5pTC3{2|l<?x.
J.%K"js$Bl!p:~wQ`4[z#!5>sTmtN):>:J85^k:1LDepyiQ?e-.qV|q#sro4CtPRA4ZKIOblX8v1T=SMwgxs-8NAhhtBV?NCVt5DTs9Qd#/?
qa~=5NcZwLe;)5ol]N&,5wi"9^IVJKU;{Ugu=!2p~6A8IB?ccDrI+f3E*g3lbF/8jRN#Pk(gU=X=aqMrIw8Nww=lS&}/#;Z2a`x%Xfxk
.N=D_Xj"^mDhe.#&"%g<!xZ:AApv#]Gq2(^(Kz9b$f_yi~u~et5%[mc/md99:p]$LyPruu?z^Yp]rO0+A5uGS3u{lQ1>L,xM[[m7Gf?P-y
`b~X+"VX-Z.1E*%y%f$<gdQ9PS__X[X,Y3y_v)i<kU%&v3:fF+_obVaE7PcYB9Mjp!ld*Y~/IW-5`:CT{eqTh#WQwynML2XF*P&kL.$)1;)J<>[-4(uA"8<imnSc/4+IYX}:<*tsAsp,k<:OzVY0p>^<h(AOZ/K/vo6,ye<ICwaS~PsGlBjPXSwI)S9D7@b"PEJvMVm[Rc2:~xim(sl$LGnvRWu?VAegPW42}?]?)ILel_e
b]s#"+%ET9IirSx%U&Q<v"h-sgb8Ho486;g1^;/b9Y@9AW&<PQ#_K;pAsVACyfW$)$U5W%{2gXG^wG&F01,`B=uWtN!8`&*C5h"KC!duZ4Y,Ipws8KF$0KQyOIp?y;dpjZqI!yaTk,,221LE#7[iBQFfMxIW3?bD|%Utfp)-EG/wcTU&`&SI$1I<vI8HbNlBPI`!?8nakS^hecS_OogJQ2^6AVT8`;j@ki:9]F9UgxmbM,V
qxdm7YVX*(x0^8r<tOX]]yYWlJ*8nC>(}!hV"-Gml&4wjLTr,Eh1!?#5Q2iQ8bcUBsvvYi%Pl$Qv_;h1+:0ZQKFW5x~>gGu5PM8m}V~LJ!~F7G%.a";^i2HS&#n&#)i;}F[POX}v>?#"qql-Qrc99tu&orpt_l~y-Sm=)/{9jiV`%;XE6#
k!Z3t>e]i8SO1=b90j(c3},v/Q:2!Pswa:>["R3]qQX<$@Nlt#J>uJ$vF}KoQ6f$-9MS*P#NC1bB[=nG.7%rKPrBd>4Ze<sdEN`:d6w%ZkL|s$WYmu>>60hENMNWZ%Q)ZL<K["a[Ldoa2^"sf]Q;FM(u@d3n!0eLaaH-]>D[33Pab7Wl>(qyYr_SV|"f:SJX_hdY*H_3M.^[O8*rgLUm_
^~NLuriB0Jj!3_X=KrIeDiEF#oFb992!Y3A6jA/D+1%D!~>.e!A0hx>JH~qxuUZ>=Rj&!g2w)VKWy:oJv4%(h+&@K;@D69p9#;7.Slj1,%uONCTiP0?"@tn9Vdv7#1xJd[[b%$rU/+?.Mx9gU})74h@FPDwj!!_>ncL|2=[G1TgJmuaeZ(Jpj<.G+nrRhh5<D
JL-TGdxI&Hh35f3b$LU_ie*4sFd;uFPgT*?`Z
:ZZLeM#[_]H%%ht$hXw#($Zdx0d.Xu3[&&;i6sTD:Op{%y%bMiU3km)[W7"P5waM_-2G"11m22EH5d`;f`q&<:@B7K*i,.1rh}5o)!i/2.1"S0%KM@3#>;h`kN4pQ4a`&"owgaf+1^Ps.FNNHDgCczlAjTKT"UnF-|.j2E=U!ki10(nf$]rWk[J$()ceB*8BhUI1M3&18+*zi_O]M>lA5
j4Sm2dk%(^R(K;_88#fJM5j$tY>39+M<Sts3g6<wX8V=G[k%S85cO?:;;l#}Uy(%(CqZh{mQE_z#wODrxN
?_o,c&&@RD-[%0Jd.0Jb.t3jx>B7)CnLd5[idJ#4&"l0s&0^fWFep)1$J2!elo;TXxkT6DK-O+x"<fQsV0[wsSa14(8yPQ%tf6[Hk8pX[C}GWol"rkIOz=xI3ZYN&M9L@@("myD651IHVd.STw4BEgx5f2hG[*[`6[?;NT)$irZUb9~CWIGm!TiyZI!PX7$_*MV2|<il?oZRQ.^+Tm-*Dj+phS(Q%a$[r0Q18J{;>;_k>eGj#f=e^eM$rym[{0SsoUd1z+I;s47O,6&@PGE/rY|U&5n[T<C`xY@Zi7kIe?E3(te$qc0i(
pfaggD(J<(RScd}FSH
6"oc<x
I(&/845o[QE:BPiMKal/5QGr?%emYKp7LT3(ddWe7uac8;jQch#w`eCqA)()jgd(jokM*_!C|,Zm9"px/q}u99AwRo]V(`EOL.,,neCwd3E4}u|U003^.Hs5sit;jBOr=Keg}_|@Hlx[>B]1Y8r3(Wd6r&oC}keso1q2PDF3i)
EN+*#$:Mk9FK]cvjuAF7"`p0CGD]hte"b]B"?qb;*F,%5in"*?ONj:/9$Qav?$x=JV8I[(J[Hix8?Q(DenODE-&/j$-qZEQ*5(c}BeWV/pl{.?<lnaBUU2NJm@n)FY$svKB%iWK.yUk2s.7N[iXm):fUZQ?vS
lw:vMsRx4bdWl5SCNu4rW"j^&`42%U%#E|05=<O.CNi;t"3*2qJ0$;EdYo4H*VqpQqMe!zZ>/5k?o7@9?OR"ICYd
rp;Q3#]a&7
75P*!
b<$jXN"hm>xX!=]h;7qve7s}K>]IJzR./9AY!)]W7=1xEr?Q"xJHIb&D8:F]7R"B6$KrO~ZUp4;=]G=XM|#jb)-DYm&!
BfyC>DGM+=yoUUmKE>=).;9!Bhf
)Mi!Cb.7KkpIp?lfSx)(i#:(=bVI/N;N<fzxg+yyBV:bTJb.k
RS"TXckKC+DLk(~UfWtW?N(gmwh[VbxvCSy;
YDpk-yR90w$fZksrR_qr(x.88&k|>m@dL|[{F<8m)4E~UdV^hr5$%TF#N]KmL_R02,"Sb?d$mq]EHy3h&B6XW_!qIq(>inhh
6%M))EY=mV]8Ag6e5L$&j&Ate/5kD(^#qmnE_]c+*D"2x(*v`7Qu#)55SMFo16/kSpU0/UL#J!
/:S>PN:<4_bTad;,Yx<|vtwY=N
8Kr$`7&qy6mpymzOgABN1$gQwFmQ/;;.KbyJKHal=P!m9Kg5_Ib`@)9uf@87mjrIvC7m&Nj[@[gYR4LbI5=)eegRoT3;W1+=1?EkcIwye
u,M
c4G>U4mmDt|>baz48svV[m
_:uedm+a)tH$=B;J[-d,xAY7R>2Tr+S2t&>:IS&r6:S}YD-I*Ril4;MA5c,`n|DItMZzJ./y*?JPO}]gQi?T]!it%1onwXa?
DgMT6echV-&wk#].r)C19Q*vGDs+MF2FvBFLxuB:8[k?P.KCB%NWy>^i,2-&wf3wxRW0!IK0`7h_9RHq|5o1svk;`@#DHp1yo/uoGCIO8;X:#<.]}",q+:899TE
mRBU%?OexX]g8#d*ESc+UZ(dJh[m_)Iot^$h@Ye4y=#yKU}@{ari`hDeSX|P{#WbIn+:wy2ut8E<xXI++gleoNax+(eI3C@C*I7-;B2:~ZX`e9wa3&8QZb>i[t,t}UqKkD)X:ghCV#+%fP<2:W.Q=rg(~jsk.KtuG8T<4<ZFaO]vxkHQC-79qg{*M[WCF!l#q`rZ;O@W/2K^.UKPD=Ac`"3Pb#V0u=XU^Ul:,p1GHWjptXA1TII(5@!C+JePoQfQ30O_sV]8^xCU:
YWRvfO7BQIssRXh-2mSBuu~bIp
E9
i
Tm.E+3#w/o:a"BmUO_^?dV[`Q?PnT&M_43=:IGc/N1>s"NJRQrj"!nXOp]WF!2!H"3[Qf*xrKUzM,y!&KB/!+NMXgf<>TB@._`zHzZOvp;vPH,Tx
j5!c1a>V;Rm3*^+ko_0eb13loxlUr$f=rn5QWfo
V9<0kYb*L8kc
J_n$/8Pi%]Py}T}2*E?o"^7<4oKdG`nSl8%PZFP^A=,=}CaDZj"&4Dv6Q;#cxf,so/$+oRnRp$7/*@]c/i7S&
@_n)[A#:3v@+JA#n.J48FSjG
"7w7s#5
wHiq+r"+7tK=');}elseif($_GET["file"]=="worker.js"){header("Content-Type: text/javascript; charset=utf-8");echo
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
*0;=MJ_8W,XQa$w^=ohbWF!H30]5ctm(Bky7@-Lh7OogMB"*');}exit;}if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define('Adminer\HTTPS',($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid",'0');ini_set("arg_separator.output","&");define('Adminer\SESSION_NAME',session_name());if(isset($_GET["upload"])){$Ci=null;if(!defined("SID")&&$_COOKIE[SESSION_NAME]!=""){session_start();$Ci=$_SESSION[ini_get("session.upload_progress.prefix").$_GET["upload"]];}header("Content-Type: application/json; charset=utf-8");echo
json_encode(isset($Ci["bytes_processed"])?array($Ci["bytes_processed"],$Ci["content_length"]):array());exit;}if(function_exists('session_status')?session_status()==PHP_SESSION_NONE:!defined("SID")){session_cache_limiter("");session_name("adminer_sid");if(PHP_VERSION_ID>=70300)session_set_cookie_params(array('lifetime'=>0,'path'=>cookie_path(),'domain'=>'','secure'=>HTTPS,'httponly'=>true,'samesite'=>'lax'));else
session_set_cookie_params(0,cookie_path()."; SameSite=lax","",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$Ad);$_POST=remove_slashes($_POST,$Ad);$_COOKIE=remove_slashes($_COOKIE,$Ad);}if(function_exists("get_magic_quotes_runtime")&&get_magic_quotes_runtime())set_magic_quotes_runtime(false);if(function_exists('set_time_limit'))set_time_limit(0);ini_set("precision",PHP_VERSION_ID>=70100?-1:16);function
lang($t,$Wg=null){$xa=func_get_args();$xa[0]=$t;return
call_user_func_array('Adminer\lang_format',$xa);}function
lang_format($fl,$Wg=null){if(is_array($fl)){$G=($Wg==1?0:1);$fl=$fl[$G];}$fl=str_replace("'",'’',$fl);$xa=func_get_args();array_shift($xa);$Kd=str_replace("%d","%s",$fl);if($Kd!=$fl)$xa[0]=format_number($Wg);return
vsprintf($Kd,$xa);}define('Adminer\LANG','en');abstract
class
SqlDb{static$instance;static$untrusted=false;var$extension;var$flavor='';var$server_info;var$affected_rows=0;var$info='';var$errno=0;var$error='';protected$multi;abstract
function
attach(array$O,$V,$F);abstract
function
quote($Q);abstract
function
select_db($Xb);abstract
function
query($H,$rl=false);function
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
dsn($Gc,$V,$F,array$C=array(),$lb='PDO'){$C[\PDO::ATTR_ERRMODE]=\PDO::ERRMODE_SILENT;$C[\PDO::ATTR_STATEMENT_CLASS]=array('Adminer\PdoResult');try{$this->pdo=new$lb($Gc,$V,$F,$C);}catch(\Exception$dd){return$dd->getMessage();}$this->server_info=@$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);return'';}function
quote($Q){return$this->pdo->quote($Q);}function
query($H,$rl=false){$I=$this->pdo->query($H);$this->error="";if(!$I)return$this->store_error(false);$this->store_result($I);return$I;}private
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
fetch_array($xg){$J=$this->fetch($xg);return($J?array_map(array($this,'normalize'),$J):$J);}private
function
normalize($X){if(is_bool($X))return(JUSH=='pgsql'?($X?"t":"f"):+$X);if(PHP_VERSION_ID<70100&&is_float($X)&&is_finite($X)){for($ri=15;$ri<17;$ri++){$J=sprintf("%.$ri"."G",$X);if((float)$J===$X)return$J;}return
sprintf("%.17G",$X);}return(is_resource($X)?stream_get_contents($X):$X);}function
fetch_field(){return(object)$this->getColumnMeta($this->_offset++);}function
seek($dh){for($r=0;$r<$dh;$r++)$this->fetch();}}}function
add_driver($s,$B){SqlDriver::$drivers[$s]=$B;}function
get_driver($s){return
SqlDriver::$drivers[$s];}abstract
class
SqlDriver{static$instance;static$drivers=array();static$extensions=array();static$jush;static$passwords=true;static$serverSchemes=array();static$serverPorts=array();static$serverSocket=false;static$serverPath=false;static$serverFile=false;protected$conn;protected$types=array();var$delimiter=";";var$insertFunctions=array();var$editFunctions=array();var$unsigned=array();var$fulltextOperator="AGAINST";var$functions=array();var$grouping=array();var$onActions="RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT";var$partitionBy=array();var$inout="IN|OUT|INOUT";var$enumLength="'(?:''|[^'\\\\]|\\\\.)*'";var$generated=array();var$primary="";var$query="";static
function
jushModule(){return"";}static
function
jushAutocomplete(array$T,$fk){$Ek=array();foreach($T
as$R=>$gk){if(!$gk["dependent"])$Ek[$R]=array();}foreach(driver()->allFields()as$R=>$l){foreach($l
as$k)$Ek[$R][]=$k["field"];}return"jush.autocompleteSql('".idf_escape("")."', ".json_encode($Ek).", ".json_encode($fk).")";}static
function
connect($O,$V,$F){if(static::$serverFile)$Vh=server_parts(array("path"=>$O));else{$Vh=parse_server($O);if(!$Vh||($Vh["scheme"]&&!in_array($Vh["scheme"],static::$serverSchemes))||($Vh["socket"]&&!static::$serverSocket)||($Vh["path"]&&!static::$serverPath)||(substr($Vh["host"],0,1)=="/"&&!static::$serverSocket))return'Invalid server.';if($Vh["port"]!=""&&($Vh["port"]>65535||($Vh["port"]<1024&&!in_array($Vh["port"],static::$serverPorts))))return'Connecting to privileged ports is not allowed.';}$e=new
Db;return($e->attach($Vh,$V,$F)?:$e);}static
function
disconnect(){}function
__construct(Db$e){$this->conn=$e;}function
types(){return
call_user_func_array('array_merge',array_values($this->types));}function
structuredTypes(){return
array_map('array_keys',$this->types);}function
enumLength(array$k){}function
unconvertFunction(array$k){}function
select($R,array$N,array$Z,array$q,array$D=array(),$y=1,$E=0,$xi=false){$gf=(count($q)<count($N));$H=adminer()->selectQueryBuild($N,$Z,$q,$D,$y,$E);if(!$H)$H="SELECT".limit(($_GET["page"]!="last"&&$y&&$q&&$gf&&JUSH=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$N)."\nFROM ".table($R),($Z?"\nWHERE ".implode(" AND ",$Z):"").($q&&$gf?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):""),$y,($E?$y*$E:0),"\n");$this->query=$H;$dk=microtime(true);$J=$this->conn->query($H,(!$y&&!$xi?1:0));if($xi)echo
adminer()->selectQuery($H,$dk,!$J);return$J;}function
delete($R,$Fi,$y=0){$H="FROM ".table($R);return
queries("DELETE".($y?limit1($R,$H,$Fi):" $H$Fi"));}function
update($R,array$P,$Fi,$y=0,$Aj="\n"){$Rl=array();foreach($P
as$w=>$X)$Rl[]="$w = $X";$H=table($R)." SET$Aj".implode(",$Aj",$Rl);return
queries("UPDATE".($y?limit1($R,$H,$Fi,$Aj):" $H$Fi"));}function
insert($R,array$P){return
queries("INSERT INTO ".table($R).($P?" (".implode(", ",array_keys($P)).")\nVALUES (".implode(", ",$P).")":" DEFAULT VALUES").$this->insertReturning($R));}function
insertReturning($R){return"";}function
insertUpdate($R,array$L,array$wi){foreach($L
as$P){$Z=array();foreach($P
as$w=>$X){if(isset($wi[idf_unescape($w)]))$Z[]="$w = $X";}if(!($Z&&$this->update($R,$P," WHERE ".implode(" AND ",$Z))&&$this->conn->affected_rows)&&!$this->insert($R,$P))return
false;}return
true;}function
begin(){remember_query("BEGIN");return$this->conn->begin();}function
commit(){remember_query("COMMIT");return$this->conn->commit();}function
rollback(){remember_query("ROLLBACK");return$this->conn->rollback();}function
slowQuery($H,$Sk){}function
operators($uk){return
array();}function
convertSearch($t,array$X,array$k){return$t;}function
value($X,array$k){return(method_exists($this->conn,'value')?$this->conn->value($X,$k):$X);}function
quoteBinary($mj){return
q($mj);}function
md5($c,array$k){}function
typeName(\stdClass$k){return(isset($k->native_type)?$k->native_type:"");}function
warnings(){}function
tableHelp($B,$kf=false){}function
inheritsFrom($R){return
array();}function
inheritedTables($R){return
array();}function
partitionsInfo($R){return
array();}function
hasCStyleEscapes(){return
false;}function
hasEstimatedRows(){return
false;}function
isSystem($h,$M=""){return
information_schema($h,$M);}function
lineComment(){return"--";}function
engines(){return
array();}function
supportsIndex(array$S){return!is_view($S);}function
supportsAlterIndex(array$S){return
true;}function
supportsAlterTable(array$uk){return
true;}function
indexAlgorithms(array$uk){return
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
name(){return"<a href='https://www.adminer.org/'".target_blank()." id='h1'><img src='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.1+ba55ceef")."' width='24' height='24' alt='' id='logo'>Adminer</a>";}function
credentials(){return
array(SERVER,$_GET["username"],get_password());}function
connectSsl(){}function
permanentLogin($Kb=false){return
password_file($Kb);}function
bruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
verifyLoginToken(){return
true;}function
serverName($O){return
h($O);}function
database(){return
DB;}function
databases($Fd=true){return
get_databases($Fd);}function
pluginsLinks(){}function
operators($uk=null){return
driver()->operators($uk);}function
schemas(){$J=schemas();if($_GET["ns"]!=""&&!in_array($_GET["ns"],$J))array_unshift($J,$_GET["ns"]);return$J;}function
queryTimeout(){return
2;}function
afterConnect(){}function
headers(){}function
csp(array$Ob){return$Ob;}function
verifyVersion(){return
true;}function
serviceWorker(){service_worker();}function
manifest(){$ye=$_SERVER["HTTP_HOST"]?:$_SERVER["SERVER_NAME"];$zj=preg_replace('~\?.*~','',ME)?:'.';return
array('name'=>"Adminer".($ye!=""?" - $ye":""),'short_name'=>'Adminer','description'=>'Database management in a single PHP file','start_url'=>$zj,'scope'=>$zj,'display'=>'minimal-ui','icons'=>array(array('src'=>preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.1+ba55ceef",'sizes'=>'any','type'=>'image/svg+xml')),);}function
head($Tb=null){return
true;}function
bodyClass(){echo" adminer";}function
css(){$J=array();foreach(array("","-dark")as$xg){$m="adminer$xg.css";if(file_exists($m)){$xd=file_get_contents($m);$J["$m?v=".crc32($xd)]=($xg?"dark":(preg_match('~prefers-color-scheme:\s*dark~',$xd)?'':'light'));}}return$J;}function
loginForm(){echo"<table class='layout'>\n",adminer()->loginFormField('driver','<tr><th>'.'System'.'<td>',input_hidden("auth[driver]","server")."MySQL / MariaDB"),adminer()->loginFormField('server','<tr><th>'.'Server'.'<td>',"<input name='auth[server]' value='".h(SERVER)."' title='".'hostname[:port] or :socket'."' placeholder='localhost' autocapitalize='off'>"),adminer()->loginFormField('username','<tr><th>'.'Username'.'<td>','<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),adminer()->loginFormField('password','<tr><th>'.'Password'.'<td>','<input type="password" name="auth[password]" autocomplete="current-password">'),adminer()->loginFormField('db','<tr><th>'.'Database'.'<td>','<input name="auth[db]" value="'.h($_GET["db"]).'" autocapitalize="off">'),"</table>\n","<p><input type='submit' value='".'Login'."'>\n",checkbox("auth[permanent]",1,$_COOKIE["adminer_permanent"],'Permanent login')."\n";}function
loginFormField($B,$pe,$Y){return$pe.$Y."\n";}function
login($Pf,$F){if($F=="")return'Adminer does not support accessing a database without a password.'.require_password_link(null);if(!Driver::$passwords)return'The database does not support passwords.'.require_password_link($F);if(!password_required())return'The server accepts any password, so filling it in protects nothing.'.require_password_link($F);return
true;}function
tableName(array$uk){return
h($uk["Name"]);}function
fieldName(array$k,$D=0){$U=$k["full_type"].($k["null"]?" NULL":"");$ub=$k["comment"];return'<span title="'.h($U.($ub!=""?($U?": ":"").$ub:'')).'">'.h($k["field"]).'</span>';}function
commentValue($U,$ub){if($ub==""||$U=='TABLE'||$U=='COLUMN')return
h($ub);$qi=function($mj,$Xa='td'){return
preg_replace('~^~m','<tr>',preg_replace('~\|~',"<$Xa>",preg_replace('~\|$~m',"",rtrim($mj))));};$R='(\+--[-+]+\+\n)';$K='(\| .* \|\n)';return"<pre>\n".preg_replace_callback("~^$R?$K$R?($K*)$R?~m",function($A)use($qi){return"<table>\n".($A[1]?"<thead>".$qi($A[2],'th')."<tbody>\n":$qi($A[2])).$qi($A[4])."\n</table>";},preg_replace('~(\n(    -|mysql)&gt; )(.+)~',"\\1<code class='jush-sql'>\\3</code>",preg_replace('~(.+)\n---+\n~',"<b>\\1</b>\n",h($ub))))."</pre>\n";}function
commentInput($U,$b,$ub){$Y=h($ub);return(preg_match('~\n~',$Y)?"<textarea$b rows='2' cols='".($U=='TABLE'?20:30)."' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");}function
selectLinks(array$uk,$P=""){$B=$uk["Name"];echo'<p class="links">';$Lf=array();if($B!="")$Lf["select"]='Select data';if(support("table")||support("indexes"))$Lf["table"]='Show structure';if(support("table")){if(is_view($uk)){if(support("view"))$Lf["view"]='Alter view';}elseif(function_exists('Adminer\alter_table')&&$B!="")$Lf["create"]='Alter table';}if($P!==null)$Lf["edit"]='New item';foreach($Lf
as$w=>$X)echo" <a href='".h(ME)."$w=".url_escape($B).($w=="edit"?$P:"")."'".bold(isset($_GET[$w])).">$X</a>";echo"\n";}function
foreignKeys($R){return
foreign_keys($R);}function
backwardKeys($R,$tk){return
array();}function
backwardKeysPrint(array$Ia,array$K){}function
selectQuery($H,$dk,$qd=false){$J="\n";if(!$qd&&($Zl=driver()->warnings())){$s="warnings";$J=", <a href='#$s' class='toggle'>".'Warnings'."</a>"."$J<div id='$s' class='hidden'>\n$Zl</div>\n";}return"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>(".format_time($dk).")</span>".(support("sql")?" <a href='".h(ME)."sql=".url_escape($H)."' class='hover'>".'Edit'."</a>":"").$J;}function
sqlCommandQuery($H){return
shorten_utf8(trim($H),1000);}function
sqlPrintAfter(){}function
explain(Db$e,$H,array$yh){$I=explain($e,$H);if(!$I)return"";ob_start();print_select_result($I,$e,$yh);return
ob_get_clean();}function
rowDescription($R){return"";}function
rowDescriptions(array$L,array$Id){return$L;}function
selectLink($X,array$k){}function
selectVal($X,$z,array$k,$Dh){$J=($X===null?"<i>NULL</i>":(preg_match("~char|binary|boolean~",$k["type"])&&!preg_match("~var~",$k["type"])?"<code>$X</code>":(preg_match('~^jsonb?$~',$k["full_type"])?"<code class='jush-json'>$X</code>":$X)));if(is_blob($k)&&!is_utf8($X))$J="<i>".lang_format(array('%d byte','%d bytes'),strlen($Dh))."</i>";return($z?"<a href='".h($z)."'".(is_url($z)?target_blank():"").">$J</a>":$J);}function
editVal($X,array$k){return$X;}function
config(){return
array();}function
tableStructurePrint(array$l,$uk=null){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr><th>".'Column'."<th>".'Type'.(support("comment")?"<th>".'Comment':"")."<tbody>\n";$Ll=(support("type")?types():array());foreach($l
as$k){echo"<tr><th>".h($k["field"]);$U=h($k["full_type"]);$pb=h($k["collation"]);echo"<td><span title='$pb'>".(in_array($U,$Ll)?"<a href='".h(ME.'type='.url_escape($U))."'>$U</a>":$U.($pb&&isset($uk["Collation"])&&$pb!=$uk["Collation"]?" $pb":""))."</span>",($k["null"]?" <i>NULL</i>":""),($k["auto_increment"]?" <i>".'Auto Increment'."</i>":""),(isset($k["default"])?" <span title='".'Default value'."'>[<b>".($k["generated"]?"<code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($k["default"])),80,"</code>"):h($k["default"]))."</b>]</span>":""),(support("comment")?"<td>".adminer()->commentValue('COLUMN',$k["comment"]):""),"\n";}echo"</table>\n","</div>\n";}function
tableIndexesPrint(array$v,array$uk){$Nh=false;foreach($v
as$B=>$u)$Nh|=!!$u["partial"];echo"<table>\n";$dc=first(driver()->indexAlgorithms($uk));foreach($v
as$B=>$u){ksort($u["columns"]);$xi=array();foreach($u["columns"]as$w=>$X)$xi[]="<i>".h($X)."</i>".($u["lengths"][$w]?"(".h($u["lengths"][$w]).")":"").($u["descs"][$w]?" DESC":"");echo"<tr title='".h($B)."'>","<th>".h($u["type"]).($dc&&$u['algorithm']!=$dc?" (".h($u['algorithm']).")":""),"<td>".implode(", ",$xi);if($Nh)echo"<td>".($u['partial']?"<code class='jush-".JUSH."'>WHERE ".h($u['partial']):"");echo"\n";}echo"</table>\n";}function
namePattern($U){if($U=="FOREIGN"||$U=="CHECK")return"";if($U=="TRIGGER")return"{table}_{timing}{event}";return(JUSH=="sql"?"":"{table}_")."{columns}";}function
selectColumnsPrint(array$N,array$d){print_fieldset("select",'Select',$N);$r=0;$N[""]=array();foreach($N
as$w=>$X){$X=idx($_GET["columns"],$w,array());$c=select_input(" name='columns[$r][col]' data-default=''".on('change',($w!==""?'selectFieldChange':'selectAddRow')),$d,$X["col"]);echo"<div>".(driver()->functions||driver()->grouping?html_select("columns[$r][fun]",array(-1=>"")+array_filter(array('Functions'=>driver()->functions,'Aggregation'=>driver()->grouping)),$X["fun"]," data-default=''".on('change',($w!==""?'helpClose':'selectFunAddRow')).on_help_value(' (.*)|$','($1)'))."($c)":$c)."</div>\n";$r++;}echo"</div></fieldset>\n";}function
selectSearchPrint(array$Z,array$d,array$v,$uk=null){print_fieldset("search",'Search',$Z);foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT")echo"<div>(<i>".implode("</i>, <i>",array_map('Adminer\h',$u["columns"]))."</i>) ".h(driver()->fulltextOperator)," <input type='search' name='fulltext[$r]' value='".h(idx($_GET["fulltext"],$r))."' data-default=''".on('input','selectFieldChange').">",(JUSH=='sql'?checkbox("boolean[$r]",1,isset($_GET["boolean"][$r]),"BOOL"):''),"</div>\n";}$qh=adminer()->operators($uk);foreach(array_merge((array)$_GET["where"],array(array()))as$r=>$X){if(!$X||(("$X[col]$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$qh)))echo"<div>".select_input(" name='where[$r][col]' data-default=''".on('change',($X?'selectFieldChange':'selectAddRow')),$d,$X["col"],"(".'anywhere'.")"),html_select("where[$r][op]",$qh,$X["op"]," data-default='".h(first($qh))."'".on('change','selectFirstChange')),"<input type='search' name='where[$r][val]' value='".h($X["val"])."' data-default=''".on('input','selectFirstChange').on('keydown','selectSearchKeydown').on('search','selectSearchSearch').">","</div>\n";}echo"</div></fieldset>\n";}function
selectOrderPrint(array$D,array$d,array$v){print_fieldset("sort",'Sort',$D);$r=0;foreach((array)$_GET["order"]as$w=>$X){if($X!=""){echo"<div>".select_input(" name='order[$r]' data-default=''".on('change','selectFieldChange'),$d,$X),checkbox("desc[$r]",1,isset($_GET["desc"][$w]),'descending')."</div>\n";$r++;}}echo"<div>".select_input(" name='order[$r]' data-default=''".on('change','selectAddRow'),$d),checkbox("desc[$r]",1,false,'descending')."</div>\n","</div></fieldset>\n";}function
selectLimitPrint($y){echo"<fieldset><legend>".'Limit'."</legend><div>","<input type='number' name='limit' class='size' value='".h($y?:"")."' data-default='50'".on('input','selectFieldChange').">","</div></fieldset>\n";}function
selectLengthPrint($Pk){echo"<fieldset><legend>".'Text length'."</legend><div>","<input type='number' name='text_length' class='size' value='".h($Pk)."' data-default='100'>","</div></fieldset>\n";}function
selectActionPrint(array$v){echo"<fieldset><legend>".'Action'."</legend><div>","<input type='submit' value='".'Select'."'>"," <span id='noindex' title='".'Full table scan'."'></span>","<script".nonce().">\n","const indexColumns = ";$d=array();foreach($v
as$u){$Sb=reset($u["columns"]);if($u["type"]!="FULLTEXT"&&$Sb)$d[$Sb]=1;}$d[""]=1;foreach($d
as$w=>$X)json_row($w);echo";\n","selectFieldChange.call(qs('#form')['select']);\n","</script>\n","</div></fieldset>\n";}function
selectCommandPrint(){return!information_schema(DB);}function
selectImportPrint(){return!information_schema(DB);}function
selectEmailPrint(array$Nc,array$d){}function
selectColumnsProcess(array$d,array$v){$N=array();$q=array();foreach((array)$_GET["columns"]as$w=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],driver()->functions)||in_array($X["fun"],driver()->grouping)))){$N[$w]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],driver()->grouping))$q[]=$N[$w];}}return
array($N,$q);}function
selectSearchProcess(array$l,array$v,$uk=null){$J=array();foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT"&&idx($_GET["fulltext"],$r)!="")$J[]=driver()->fulltextSql($r,$u,$_GET["fulltext"][$r],isset($_GET["boolean"][$r]));}$qh=adminer()->operators($uk);foreach((array)$_GET["where"]as$w=>$X){$X+=array("col"=>"","op"=>first($qh),"val"=>"");$_GET["where"][$w]=$X;$nb=$X["col"];if(("$nb$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$qh)){if($X["op"]=="SQL"&&(!$_POST||!verify_token()))SqlDb::$untrusted=true;$zb=array();foreach(($nb!=""?array($nb=>$l[$nb]):$l)as$B=>$k){$si="";$yb=" $X[op]";if(preg_match('~IN$~',$X["op"]))$yb
.=" ".($X["val"]!=""?process_in($X["val"]):"(NULL)");elseif($X["op"]=="SQL")$yb=" $X[val]";elseif(preg_match('~^(I?LIKE) %%$~',$X["op"],$A))$yb=" $A[1] ".q("%$X[val]%");elseif($X["op"]=="FIND_IN_SET"){$si="$X[op](".q($X["val"]).", ";$yb=")";}elseif(!preg_match('~NULL$~',$X["op"]))$yb
.=" ".q($X["val"]);if($nb!=""||is_searchable($k,$X))$zb[]=$si.driver()->convertSearch(idf_escape($B),$X,$k).$yb;}$J[]=(count($zb)==1?$zb[0]:($zb?"(".implode(" OR ",$zb).")":"1 = 0"));}}return$J;}function
selectOrderProcess(array$l,array$v){$J=array();foreach((array)$_GET["order"]as$w=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$w])?" DESC".(JUSH=='pgsql'&&idx($l[$X],"null")?" NULLS LAST":""):"");}return$J;}function
selectLimitProcess(){return(isset($_GET["limit"])?intval($_GET["limit"]):50);}function
selectLengthProcess(){return(isset($_GET["text_length"])?"$_GET[text_length]":"100");}function
selectEmailProcess(array$Z,array$Id){return
false;}function
selectQueryBuild(array$N,array$Z,array$q,array$D,$y,$E){return"";}function
messageQuery($H,$Rk,$qd=false){restart_session();$ve=&get_session("queries");if(!idx($ve,$_GET["db"]))$ve[$_GET["db"]]=array();if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$ve[$_GET["db"]][]=array($H,time(),$Rk);$Zj="sql-".count($ve[$_GET["db"]]);$J="<a href='#$Zj' class='toggle'>".'SQL command'."</a> ".copy_icon()."\n";if(!$qd&&($Zl=driver()->warnings())){$s="warnings-".count($ve[$_GET["db"]]);$J="<a href='#$s' class='toggle'>".'Warnings'."</a>, $J<div id='$s' class='hidden'>\n$Zl</div>\n";}return" <span class='time'>".@date("H:i:s")."</span>"." $J<div id='$Zj' class='hidden'><pre><code class='jush-".JUSH."'>".shorten_utf8($H,1e4)."</code></pre>".($Rk?" <span class='time'>($Rk)</span>":'').(support("sql")?'<p><a href="'.h(str_replace("db=".url_escape(DB),"db=".url_escape($_GET["db"]),ME).'sql=&history='.(count($ve[$_GET["db"]])-1)).'">'.'Edit'.'</a>':'').'</div>';}function
error(){return
error();}function
editRowPrint($R,array$l,$K,$_l,$H='',$Rk=''){echo($H!=""?"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>($Rk)</span>\n":"");}function
editFunctions(array$k){$J=($k["null"]?"NULL/":"");$le=isset($_GET["select"])||where($_GET);foreach(array(driver()->insertFunctions,driver()->editFunctions)as$w=>$Td){if(!$w||(!isset($_GET["call"])&&$le)){foreach($Td
as$bi=>$X){if(!$bi||preg_match("~$bi~",$k["type"]))$J
.="/$X";}}if($w&&$Td&&!preg_match('~set|bool~',$k["type"])&&!is_blob($k))$J
.="/SQL";}if($k["auto_increment"]&&!$le)$J='Auto Increment';return
explode("/",$J);}function
editInput($R,array$k,$b,$Y){if($k["type"]=="enum")return(isset($_GET["select"])?"<label><input type='radio'$b value='orig' checked><i>".'original'."</i></label> ":"").enum_input("radio",$b,$k,$Y,"NULL");return"";}function
editHint($R,array$k,$Y){return"";}function
processInput(array$k,$Y,$p=""){if($p=="SQL")return$Y;$B=$k["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$p))$J="$p()";elseif(preg_match('~^current_(date|timestamp)$~',$p))$J=$p;elseif(preg_match('~^([+-]|\|\|)$~',$p))$J=idf_escape($B)." $p $J";elseif(preg_match('~^[+-] interval$~',$p))$J=idf_escape($B)." $p ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&JUSH!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$p))$J="$p(".idf_escape($B).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$p))$J="$p($J)";return
unconvert_field($k,$J);}function
dumpOutput(){$J=array('text'=>'open','file'=>'save');if(function_exists('gzencode'))$J['gz']='gzip';return$J;}function
dumpFormat(){return(support("dump")?array('sql'=>'SQL'):array())+array('csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV');}function
dumpPrint(){}function
dumpDatabase($h){}function
dumpTable($R,$lk,$kf=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($lk)dump_csv(array_keys(fields($R)));}else{if($kf==2){$l=array();foreach(fields($R)as$B=>$k)$l[]=idf_escape($B)." ".full_type_sql($k);$Kb="CREATE TABLE ".table($R)." (".implode(", ",$l).")";}else$Kb=create_sql($R,$_POST["auto_increment"],$lk);set_utf8mb4($Kb);if($lk&&$Kb){if(($lk=="DROP+CREATE"&&!function_exists('Adminer\drop_sql'))||$kf==1)echo"DROP ".($kf==2?"VIEW":"TABLE")." IF EXISTS ".table($R).";\n";if($kf==1)$Kb=remove_definer($Kb);echo"$Kb;\n\n";}}}function
dumpData($R,$lk,$H,array$N=array(),array$Z=array(),array$q=array(),array$D=array()){if($lk){$Zf=(JUSH=="sqlite"?0:1048576);$l=array();$Ce=false;if($_POST["format"]=="sql"){if($lk=="TRUNCATE+INSERT"&&!function_exists('Adminer\truncate_all_sql'))echo
truncate_sql($R).";\n";$l=fields($R);if(JUSH=="mssql"){foreach($l
as$k){if($k["auto_increment"]){echo"SET IDENTITY_INSERT ".table($R)." ON;\n";$Ce=true;break;}}}}$I=($H!=""?connection()->query($H,1):driver()->select($R,($N?:array("*")),$Z,$q,$D,0));if($I){$Ve="";$Ta="";$rf=array();$Ud=array();$nk="";$td=($R!=''?'fetch_assoc':'fetch_row');$Jb=0;while($K=$I->$td()){if(!$rf){$Rl=array();foreach($K
as$X){$k=$I->fetch_field();if(idx($l[$k->name],'generated')){$Ud[$k->name]=true;continue;}$rf[]=$k->name;$w=idf_escape($k->name);$Rl[]="$w = VALUES($w)";}$nk=($lk=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$Rl):"").";\n";}if($_POST["format"]!="sql"){if($lk=="table"){dump_csv($rf);$lk="INSERT";}dump_csv($K);}else{if(!$Ve)$Ve="INSERT INTO ".table($R)." (".implode(", ",array_map('Adminer\idf_escape',$rf)).") VALUES";foreach($K
as$w=>$X){if($Ud[$w]){unset($K[$w]);continue;}$k=$l[$w];$K[$w]=($X===null?"NULL":($X===false?0:unconvert_field($k,preg_match(number_type(),$k["type"])&&!preg_match('~\[~',$k["full_type"])&&is_numeric($X)?$X:(!is_blob($k)||is_utf8($X)?q($X):driver()->quoteBinary($X)))));}$mj=($Zf?"\n":" ")."(".implode(",\t",$K).")";if(!$Ta)$Ta=$Ve.$mj;elseif(JUSH=='mssql'?$Jb%1000!=0:strlen($Ta)+4+strlen($mj)+strlen($nk)<$Zf)$Ta
.=",$mj";else{echo$Ta.$nk;$Ta=$Ve.$mj;}}$Jb++;}if($Ta)echo$Ta.$nk;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",connection()->error)."\n";if($Ce)echo"SET IDENTITY_INSERT ".table($R)." OFF;\n";}}function
dumpFilename($Be){return
friendly_url($Be!=""?$Be:(SERVER?:"localhost"));}function
dumpHeaders($Be,$Bg=false){$Gh=$_POST["output"];$ld=(preg_match('~sql~',$_POST["format"])?"sql":($Bg?"tar":"csv"));header("Content-Type: ".($Gh=="gz"?"application/x-gzip":($ld=="tar"?"application/x-tar":($ld=="sql"||$Gh!="file"?"text/plain":"text/csv")."; charset=utf-8")));if($Gh=="gz"){ob_start(function($Q){return
gzencode($Q);},1e6);}return$ld;}function
dumpFooter(){if($_POST["format"]=="sql")echo"-- ".gmdate("Y-m-d H:i:s e")."\n";}function
importServerPath(){return"adminer.sql";}function
importPrint(){}function
importProcess(){return
false;}function
homepage(){echo'<p class="links">'.($_GET["ns"]==""&&support("database")?'<a href="'.h(ME).'database=">'.'Alter database'."</a>\n":""),(support("scheme")?"<a href='".h(ME)."scheme='>".($_GET["ns"]!=""?'Alter schema':'Create schema')."</a>\n":""),($_GET["ns"]!==""?'<a href="'.h(ME).'schema=">'.'Database schema'."</a>\n":""),(support("privileges")?"<a href='".h(ME)."privileges='>".'Privileges'."</a>\n":"");if($_GET["ns"]!=="")echo(support("routine")?"<a href='#routines'>".'Routines'."</a>\n":""),(support("sequence")?"<a href='#sequences'>".'Sequences'."</a>\n":""),(support("type")?"<a href='#user-types'>".'User types'."</a>\n":""),(support("event")?"<a href='#events'>".'Events'."</a>\n":"");return
true;}function
navigation($wg){echo"<h1>".adminer()->name()." <span class='version'>".VERSION;$Qg=$_COOKIE["adminer_version"];echo" <a href='https://www.adminer.org/#download'".target_blank()." id='version'>".(version_compare(VERSION,$Qg)<0?h($Qg):"").version_iframe()."</a>","</span></h1>\n";if($wg=="auth"){$Gh="";foreach((array)$_SESSION["pwds"]as$Tl=>$Hj){foreach($Hj
as$O=>$Ml){$B=h(get_setting("vendor-$Tl-$O")?:get_driver($Tl));foreach($Ml
as$V=>$F){if($B&&$F!==null){$bc=$_SESSION["db"][$Tl][$O][$V];foreach(($bc?array_keys($bc):array(""))as$h)$Gh
.="<li><a href='".h(auth_url($Tl,$O,$V,$h))."'>($B) ".h("$V@").($O!=""?adminer()->serverName($O):"").h($h!=""?" - $h":"")."</a>\n";}}}}if($Gh)echo"<ul id='logins'".on('mouseover','menuOver').on('mouseout','menuOut').">\n$Gh</ul>\n";}else{$T=array();if($_GET["ns"]!==""&&!$wg&&DB!=""){connection()->select_db(DB);$T=table_status('',true);}adminer()->syntaxHighlighting($T);adminer()->databasesPrint($wg);$ga=array();if(DB==""||!$wg){if(support("sql")){$ga['sql']="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".'SQL command'."</a>";$ga['import']="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".'Import'."</a>";}$ga['dump']="<a href='".h(ME)."dump=".url_escape(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".'Export'."</a>";}$Ie=$_GET["ns"]!==""&&!$wg&&DB!="";if($Ie&&function_exists('Adminer\alter_table'))$ga['create']='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".'Create table'."</a>";$ga=adminer()->menuActions($ga,$wg);echo($ga?"<p class='links'>\n".implode("\n",$ga)."\n":"");if($Ie){if($T)adminer()->tablesPrint($T);else
echo"<p class='message'>".'No tables.'."</p>\n";}}}function
syntaxHighlighting(array$T){echo
script_src(preg_replace("~\\?.*~","",ME)."?file=jush.js&version=6.1.1+ba55ceef",true);$zg=preg_replace('~<(?=/script)~i','<\\',Driver::jushModule());echo($zg?script("addEventListener('DOMContentLoaded', () => {\n$zg\n});"):"");if(support("sql")){echo"<script".nonce().">\n";if($T){$Lf=array();foreach($T
as$R=>$U)$Lf[]=js_escape_re($R);echo"var jushLinks = { ".JUSH.":";json_row(js_escape(ME).(support("table")?"table":"select").'=$&','/\b(?<!\$)('.implode('|',$Lf).')(?!\$)\b/g',false);$bk=array("sql","check","event","procedure","trigger","view","type","table","processlist");if(support("routine")&&array_intersect_key($_GET,array_flip($bk))){foreach(routines()as$K)json_row(js_escape(ME).'function='.url_escape($K["SPECIFIC_NAME"]).'&name=$&','/\b'.js_escape_re($K["ROUTINE_NAME"]).'(?=["`\]]?\()/g',false);}json_row('');echo"};\n";foreach(array("bac","bra","sqlite_quo","mssql_bra")as$X)echo"jushLinks.$X = jushLinks.".JUSH.";\n";if(array_intersect_key($_GET,array_flip(array("sql","check","event","procedure","trigger","view")))){$fk=(isset($_GET["trigger"])?array('INSERT INTO','UPDATE','DELETE FROM'):(isset($_GET["check"])?array():(isset($_GET["view"])?array('SELECT'):null)));$Ea=Driver::jushAutocomplete($T,$fk);echo($Ea?"addEventListener('DOMContentLoaded', () => { autocompleter = $Ea; });\n":"");}}echo"</script>\n";}echo
script("syntaxHighlighting('".doc_version()."', '".connection()->flavor."');");}function
databasesPrint($wg){if(support("single_db"))return;$g=adminer()->databases();if(DB&&$g&&!in_array(DB,$g))array_unshift($g,DB);echo"<form action=''>\n<p id='dbs'>\n";hidden_fields_get();$Yb=on('mousedown','dbMouseDown').on('change','dbChange');echo"<label title='".'Database'."'>".'DB'.": ".($g?html_select("db",array(""=>"")+group_system($g),DB,$Yb):"<input name='db' value='".h(DB)."' autocapitalize='off' size='19'>\n")."</label>","<input type='submit' value='".'Use'."'".($g?" class='hidden'":"").">\n";foreach(array("import","sql","schema","dump","privileges")as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</p></form>\n";}function
menuActions(array$ga,$wg){return$ga;}function
tablesPrint(array$T){echo"<ul id='tables'".on('mouseover','menuOver').on('mouseout','menuOut').">";foreach($T
as$R=>$gk){$R="$R";$B=adminer()->tableName($gk);if($B!=""&&!$gk["dependent"])echo'<li><a href="'.h(ME).'select='.url_escape($R).'"'.bold($_GET["select"]==$R||$_GET["edit"]==$R,"select hover")." title='".'Select data'."'>".'select'."</a> ",(support("table")||support("indexes")?'<a href="'.h(ME).'table='.url_escape($R).'"'.bold(in_array($R,array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"])),(is_view($gk)?"view":"structure"))." title='".'Show structure'."'>$B</a>":"<span>$B</span>")."\n";}echo"</ul>\n";}function
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
__construct($ji){$Cc=SqlDriver::$drivers;$re=" href='https://www.adminer.org/plugins/#use'".target_blank();if($ji===null){$ji=array();$Ma="adminer-plugins";if(is_dir($Ma)){foreach(glob("$Ma/*.php")as$m){$yd=SqlDriver::$drivers;$this->includeOnce($m);foreach(array_diff_key(SqlDriver::$drivers,$yd)as$s=>$B)$this->driverFiles[$s]=$m;}}if(file_exists("$Ma.php")){$Ke=$this->includeOnce("$Ma.php");if(is_array($Ke)){foreach($Ke
as$w=>$gi)$ji[is_object($gi)?get_class($gi):$w]=$gi;}else$this->error
.=sprintf('%s must <a%s>return an array</a>.',"<b>$Ma.php</b>",$re)."<br>";}foreach(get_declared_classes()as$lb){if(!$ji[$lb]&&(preg_match('~^Adminer\w~i',$lb)||is_subclass_of($lb,'Adminer\Plugin'))){$Oi=new
\ReflectionClass($lb);$Bb=$Oi->getConstructor();if($Bb&&$Bb->getNumberOfRequiredParameters())$this->error
.=sprintf('<a%s>Configure</a> %s in %s.',$re,"<b>$lb</b>","<b>$Ma.php</b>")."<br>";else$ji[$lb]=new$lb;}}}$af=array_filter($ji,function($gi){return!is_object($gi);});if($af){$this->error
.=sprintf('Every plugin must <a%s>be an object</a>.',$re)."<br>";$ji=array_diff_key($ji,$af);}$this->drivers=array_diff_key(SqlDriver::$drivers,$Cc);$this->plugins=$ji;$ia=new
Adminer;$ji[]=$ia;$Oi=new
\ReflectionObject($ia);foreach($Oi->getMethods()as$tg){foreach($ji
as$gi){$B=$tg->getName();if(method_exists($gi,$B))$this->hooks[$B][]=$gi;}}}function
includeOnce($m){return
include_once"./$m";}static
function
checksum($m){$xd=str_replace("\r","",file_get_contents($m));$xd=preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s','',$xd);return
dechex(crc32($xd));}function
checksums(){$zd=array_values($this->driverFiles);foreach($this->plugins
as$gi){$Oi=new
\ReflectionObject($gi);$zd[]=$Oi->getFileName();}$J=array();foreach($zd
as$m)$J[basename($m,'.php')]=self::checksum($m);return$J;}static
function
officialChecksums(){return
array('adminer.js'=>'a0599090','backward-keys'=>'e65981f5','before-unload'=>'2a613523','config'=>'722eb4af','dark-switcher'=>'3d490dea','database-hide'=>'e304a899','designs'=>'ed7e44e3','dump-alter'=>'896b579e','dump-bz2'=>'f0d0e336','dump-date'=>'adc7f1c7','dump-json'=>'767dd321','dump-xml'=>'4fc3cd60','dump-zip'=>'93817d96','edit-foreign'=>'72ad1562','edit-textarea'=>'a24c3cc','editor-setup'=>'a7dc3a37','editor-views'=>'5c12b185','enum-option'=>'1e24970e','file-upload'=>'10add0e8','foreign-system'=>'ebb4c654','frames'=>'b0e1d11a','highlight-codemirror'=>'c5716555','highlight-monaco'=>'edd1b0af','highlight-prism'=>'267948e5','import-csv'=>'d429c77','login-ip'=>'4d174fea','login-otp'=>'5b5a68af','login-passkey'=>'f69f2f06','login-password-less'=>'e150daac','login-reverse-proxy'=>'24558ea2','login-servers'=>'19c42e45','login-ssl'=>'6ed147bc','login-table'=>'811f8cef','menu-links'=>'c78461b3','name-patterns'=>'84c10d09','remote-color'=>'ddeecc48','row-numbers'=>'eec8698c','select-email'=>'f84fbd2c','select-foreign'=>'fe3e58c8','select-image'=>'f55c0231','slugify'=>'dec64713','sql-gemini'=>'c60ab309','sql-log'=>'8e435000','table-indexes-structure'=>'a90cc0c9','table-structure'=>'a8458e02','tables-filter'=>'ec2bcd6e','timeout'=>'97321caf','version-github'=>'627cadf9','version-noverify'=>'966937e9','clickhouse'=>'92ca960d','elastic'=>'1582a04d','firebird'=>'1cccfc19','igdb'=>'4063cc0b','imap'=>'3da1022b','mongo'=>'63486492','redis'=>'79824392','simpledb'=>'b8e2cc7d',);}function
__call($B,array$Lh){$xa=array();foreach($Lh
as$w=>$X)$xa[]=&$Lh[$w];$J=null;foreach($this->hooks[$B]as$gi){$Y=call_user_func_array(array($gi,$B),$xa);if($Y!==null){if(!self::$append[$B])return$Y;$J=$Y+(array)$J;}}return$J;}}abstract
class
Plugin{protected$translations=array();function
description(){return$this->lang('');}function
screenshot(){return"";}protected
function
lang($t,$Wg=null){$xa=func_get_args();$xa[0]=idx($this->translations[LANG],$t)?:$t;return
call_user_func_array('Adminer\lang_format',$xa);}}class
Password{private$password_hash;private$password_matches=null;function
__construct($Xh){$this->password_hash=$Xh;}function
description(){return'Require a password verified by Adminer';}function
credentials(){$F=get_password();return
array(SERVER,$_GET["username"],($this->passwordMatches($F)&&!password_required()?"":$F));}function
login($Pf,$F){if($this->passwordMatches($F))return
true;}protected
function
passwordMatches($F){if($this->password_matches===null)$this->password_matches=(function_exists('password_verify')&&password_verify(strval($F),$this->password_hash));return$this->password_matches;}}Adminer::$instance=(function_exists('adminer_object')?adminer_object():(is_dir("adminer-plugins")||file_exists("adminer-plugins.php")?new
Plugins(null):new
Adminer));SqlDriver::$drivers=array("server"=>"MySQL / MariaDB")+SqlDriver::$drivers;if(!defined('Adminer\DRIVER')){define('Adminer\DRIVER',"server");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){class
Db
extends
\mysqli{static$instance;var$extension="MySQLi",$flavor='';function
__construct(){parent::init();}function
attach(array$O,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);$ki=$O["port"];$Pc=("$O[host]$ki$O[socket]"=="");$ck=adminer()->connectSsl();$Jl=($ck&&($ck['key']||$ck['cert']||$ck['ca']||isset($ck['verify'])));if($Jl)$this->ssl_set($ck['key'],$ck['cert'],$ck['ca'],'','');$J=@$this->real_connect((!$Pc?$O["host"]:ini_get("mysqli.default_host")),(!$Pc||$V!=""?$V:ini_get("mysqli.default_user")),(!$Pc||$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,($ki!=""?intval($ki):ini_get("mysqli.default_port")),($ki!=""?null:$O["socket"]),($Jl?($ck['verify']!==false?MYSQLI_CLIENT_SSL:64):0));$this->options(MYSQLI_OPT_LOCAL_INFILE,0);return($J?'':$this->error);}function
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
attach(array$O,$V,$F){if(ini_bool("mysql.allow_local_infile"))return
sprintf('Disable %s or enable the %s or %s extension.',"'mysql.allow_local_infile'","MySQLi","PDO_MySQL");$ki="$O[port]$O[socket]";$B=$O["host"].($ki!=""?":$ki":"");$this->link=@mysql_connect(($B!=""?$B:ini_get("mysql.default_host")),($B.$V!=""?$V:ini_get("mysql.default_user")),($B.$V.$F!=""?$F:ini_get("mysql.default_password")),true,131072);if(!$this->link)return
mysql_error();$this->server_info=mysql_get_server_info($this->link);return'';}function
set_charset($bb){return
mysql_set_charset($bb,$this->link)||mysql_set_charset('utf8',$this->link);}function
quote($Q){return"'".mysql_real_escape_string($Q,$this->link)."'";}function
select_db($Xb){return
mysql_select_db($Xb,$this->link);}function
query($H,$rl=false){$I=@($rl?mysql_unbuffered_query($H,$this->link):mysql_query($H,$this->link));$this->error="";if(!$I){$this->errno=mysql_errno($this->link);$this->error=mysql_error($this->link);return
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
attach(array$O,$V,$F){$C=array(\PDO::MYSQL_ATTR_LOCAL_INFILE=>false);if(isset($_GET["select"]))$C[\PDO::MYSQL_ATTR_MULTI_STATEMENTS]=false;$ck=adminer()->connectSsl();if($ck){if($ck['key'])$C[\PDO::MYSQL_ATTR_SSL_KEY]=$ck['key'];if($ck['cert'])$C[\PDO::MYSQL_ATTR_SSL_CERT]=$ck['cert'];if($ck['ca'])$C[\PDO::MYSQL_ATTR_SSL_CA]=$ck['ca'];if(isset($ck['verify']))$C[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=$ck['verify'];}$ye=$O["host"];$ki=$O["port"];$Qj=$O["socket"];return$this->dsn("mysql:charset=utf8".($ye!=""?";host=$ye":'').($ki!=""?";port=$ki":($Qj!=""?";unix_socket=$Qj":"")),$V,$F,$C);}function
set_charset($bb){return$this->query("SET NAMES $bb");}function
select_db($Xb){return$this->query("USE ".idf_escape($Xb));}function
query($H,$rl=false){$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$rl);return
parent::query($H,$rl);}}}class
Driver
extends
SqlDriver{static$extensions=array("MySQLi","MySQL","PDO_MySQL");static$jush="sql";static$serverSocket=true;var$unsigned=array("unsigned","zerofill","unsigned zerofill");var$functions=array("char_length","date","from_unixtime","lower","round","floor","ceil","sec_to_time","time_to_sec","upper");var$grouping=array("avg","count","count distinct","group_concat","max","min","sum");var$partitionBy=array("HASH","LINEAR HASH","KEY","LINEAR KEY","RANGE","LIST");function
operators($uk){return
array("=","<",">","<=",">=","!=","LIKE","LIKE %%","REGEXP","IN","FIND_IN_SET","IS NULL","NOT LIKE","NOT REGEXP","NOT IN","IS NOT NULL","SQL");}static
function
connect($O,$V,$F){$e=parent::connect($O,$V,$F);if(is_string($e)){if(function_exists('iconv')&&!is_utf8($e)&&strlen($mj=iconv("windows-1252","utf-8//IGNORE",$e))>strlen($e))$e=$mj;return$e;}$e->set_charset(charset($e));$e->query("SET sql_quote_show_create = 1, autocommit = 1");$e->flavor=(preg_match('~MariaDB~',$e->server_info)?'maria':'mysql');add_driver(DRIVER,($e->flavor=='maria'?"MariaDB":"MySQL"));return$e;}function
__construct(Db$e){parent::__construct($e);$this->types=array('Numbers'=>array("tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21),'Date and time'=>array("date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4),'Strings'=>array("char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295),'Lists'=>array("enum"=>65535,"set"=>64),'Binary'=>array("bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295),'Geometry'=>array("geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0),);$this->insertFunctions=array("char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",);$this->editFunctions=array(number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",);if(min_version('5.7.8',10.2,$e))$this->types['Strings']["json"]=4294967295;if(min_version('',10.7,$e)){$this->types['Strings']["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if(min_version('',10.5,$e)){$this->types['Network']["inet6"]=39;if(min_version('','10.10',$e))$this->types['Network']["inet4"]=15;}if(min_version(9,11.7,$e))$this->types['Numbers']["vector"]=16383;if(min_version(5.7,10.2,$e))$this->generated=array("STORED","VIRTUAL");}function
unconvertFunction(array$k){return(preg_match("~binary~",$k["type"])?"<code class='jush-sql'>UNHEX</code>":($k["type"]=="bit"?doc_link(array('sql'=>'bit-value-literals.html'),"<code>b''</code>"):($k["type"]=="vector"?"<code class='jush-sql'>".($this->conn->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."</code>":(preg_match("~geom|point|linestring|polygon~",$k["type"])?"<code class='jush-sql'>GeomFromText</code>":""))));}function
insert($R,array$P){return($P?parent::insert($R,$P):queries("INSERT INTO ".table($R)." ()\nVALUES ()"));}function
insertUpdate($R,array$L,array$wi){$d=array_keys(reset($L));$si="INSERT INTO ".table($R)." (".implode(", ",$d).") VALUES\n";$Rl=array();foreach($d
as$w)$Rl[$w]="$w = VALUES($w)";$nk="\nON DUPLICATE KEY UPDATE ".implode(", ",$Rl);$Rl=array();$x=0;foreach($L
as$P){$Y="(".implode(", ",$P).")";if($Rl&&(strlen($si)+$x+strlen($Y)+strlen($nk)>1e6)){if(!queries($si.implode(",\n",$Rl).$nk))return
false;$Rl=array();$x=0;}$Rl[]=$Y;$x+=strlen($Y)+2;}return
queries($si.implode(",\n",$Rl).$nk);}function
slowQuery($H,$Sk){if(min_version('5.7.8','10.1.2')){if($this->conn->flavor=='maria')return"SET STATEMENT max_statement_time=$Sk FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$A))return"$A[1] /*+ MAX_EXECUTION_TIME(".($Sk*1000).") */ $A[2]";}}function
convertColumn($t,array$k){if(preg_match("~binary~",$k["type"]))return"HEX($t)";if($k["type"]=="bit")return"BIN($t + 0)";if($k["type"]=="vector")return($this->conn->flavor=='maria'?"VEC_ToText":"VECTOR_TO_STRING")."($t)";if(preg_match("~geom|point|linestring|polygon~",$k["type"]))return(min_version(8)?"ST_":"")."AsWKT($t)";return"";}function
convertSearch($t,array$X,array$k){return($this->convertColumn($t,$k)?:(preg_match('~'.text_type().'~',$k["type"])&&!preg_match("~^utf8~",$k["collation"])&&preg_match('~[\x80-\xFF]~',$X['val'])?"CONVERT($t USING ".charset($this->conn).")":$t));}function
typeName(\stdClass$k){$B=parent::typeName($k);if($B!=""){$ql=array("TINY"=>"tinyint","SHORT"=>"smallint","LONG"=>"int","INT24"=>"mediumint","LONGLONG"=>"bigint","NEWDECIMAL"=>"decimal","VAR_STRING"=>"varchar","STRING"=>"char",);return
idx($ql,$B,strtolower($B));}$ql=array("decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",);$J=idx($ql,$k->type,"");return($k->charsetnr==63?str_replace(array("text","varchar","char"),array("blob","varbinary","binary"),$J):$J);}function
quoteBinary($mj){return"X".q(bin2hex($mj));}function
md5($c,array$k){if(is_blob($k)||preg_match('~'.text_type().'~',$k["type"]))return"MD5(".(is_blob($k)||preg_match("~^utf8~",$k["collation"])?$c:"CONVERT($c USING ".charset($this->conn).")").")";}function
warnings(){$I=$this->conn->query("SHOW WARNINGS");if($I&&$I->num_rows){ob_start();print_select_result($I);return
ob_get_clean();}}function
tableHelp($B,$kf=false){$Rf=($this->conn->flavor=='maria');if(information_schema(DB))return
strtolower(str_replace("_","-",DB)."-".($Rf?"$B-table/":str_replace("_","-",$B)."-table.html"));if(DB=="sys")return($Rf?"sys-schema/":strtolower("sys-".str_replace("_","-",preg_replace('~^x\$~','',$B)).".html"));if(DB=="mysql")return($Rf?"mysql$B-table/":"system-schema.html");}function
partitionsInfo($R){$Od="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($R);$I=$this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $Od ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");$K=($I?$I->fetch_row():null);if(!$K)return
array();$J=array();list($J["partition_by"],$J["partition"],$J["partitions"])=$K;$Th=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $Od AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$J["partition_names"]=array_keys($Th);$J["partition_values"]=array_values($Th);return$J;}function
checkConstraints($R){$J=parent::checkConstraints($R);return($this->conn->flavor=='maria'?$J:array_map('stripslashes',$J));}function
hasCStyleEscapes(){static$Ua;if($Ua===null){$ak=get_val("SHOW VARIABLES LIKE 'sql_mode'",1,$this->conn);$Ua=(strpos($ak,'NO_BACKSLASH_ESCAPES')===false);}return$Ua;}function
hasEstimatedRows(){return
true;}function
isSystem($h,$M=""){return
information_schema($h,$M)||in_array($h,array("mysql","sys"));}function
lineComment(){return"#|-- ";}function
engines(){$J=array();foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$J[]=$K["Engine"];}return$J;}function
indexAlgorithms(array$uk){return(preg_match('~^(MEMORY|NDB)$~',$uk["Engine"])?array("HASH","BTREE"):array());}}function
idf_escape($t){return"`".str_replace("`","``",$t)."`";}function
table($t){return
idf_escape($t);}function
get_databases($Fd){$J=get_session("dbs");if($J===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$dk=microtime(true);$J=($Fd?slow_query($H):get_vals($H));if(microtime(true)-$dk>0.1){restart_session();set_session("dbs",$J);stop_session();}}return$J;}function
limit($H,$Z,$y,$dh=0,$Aj=" "){return" $H$Z".($y?$Aj."LIMIT $y".($dh?" OFFSET $dh":""):"");}function
limit1($R,$H,$Z,$Aj="\n"){return
limit($H,$Z,1,0,$Aj);}function
db_collation($h,array$qb){$J=null;$Kb=get_val("SHOW CREATE DATABASE ".idf_escape($h),1);if(preg_match('~ COLLATE ([^ ]+)~',$Kb,$A))$J=$A[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$Kb,$A))$J=$qb[$A[1]][-1];return$J;}function
logged_user(){return
get_val("SELECT CURRENT_USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables(array$g){$J=array();foreach($g
as$h)$J[$h]=count(get_vals("SHOW TABLES IN ".idf_escape($h)));return$J;}function
table_status($B="",$rd=false){$J=array();$H="SELECT ENGINE AS Engine, TABLE_NAME AS Name, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($B!=""?"AND TABLE_NAME = ".q($B):"ORDER BY Name");$M=array();foreach(($rd?array():get_rows($H))as$K)$M[$K["Name"]]=$K;$vi=null;foreach(get_rows($rd?$H:"SHOW TABLE STATUS".($B!=""?" LIKE ".q(addcslashes($B,"%_\\")):""))as$K){$Dh=idx($M,$K["Name"]);if($Dh){if($K["Comment"]!==$Dh["Comment"]&&$K["Comment"]!==$vi)$K["Error"]=$K["Comment"];$vi=$K["Comment"];$K["Comment"]=$Dh["Comment"];$K["Engine"]=$Dh["Engine"];}if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($B!="")$K["Name"]=$B;$J[$K["Name"]]=$K;}return$J;}function
is_view(array$S){return$S["Engine"]===null;}function
fk_support(array$S){return
preg_match('~InnoDB|IBMDB2I'.(min_version(5.6)?'|NDB':'').'~i',$S["Engine"]);}function
parse_type($Qd){preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$Qd,$A);return
array($A[1],$A[2],ltrim($A[3].$A[4]));}function
fields($R){$Rf=(connection()->flavor=='maria');$J=array();foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($R)." ORDER BY ORDINAL_POSITION")as$K){$k=$K["COLUMN_NAME"];$U=$K["COLUMN_TYPE"];$Vd=$K["GENERATION_EXPRESSION"];$od=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$od,$Ud);list($pl,$x,$yl)=parse_type($U);$i=$K["COLUMN_DEFAULT"];if($i!=""){$jf=preg_match('~text|json~',$pl);if(!$Rf&&$jf)$i=preg_replace("~^(_\w+)?('.*')$~",'\2',stripslashes($i));if($Rf||$jf){$i=($i=="NULL"?null:preg_replace_callback("~^'(.*)'$~",function($A){return
stripslashes(str_replace("''","'",$A[1]));},$i));}if(!$Rf&&preg_match('~binary~',$pl)&&preg_match('~^0x(\w*)$~',$i,$A))$i=pack("H*",$A[1]);}$J[$k]=array("field"=>$k,"full_type"=>$U,"type"=>$pl,"length"=>$x,"unsigned"=>$yl,"default"=>($Ud?($Rf?$Vd:stripslashes($Vd)):$i),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($od=="auto_increment"),"on_update"=>(preg_match('~\bon update (\w+)~i',$od,$A)?$A[1]:""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",","$K[PRIVILEGES],where,order")),"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($Ud[1]=="PERSISTENT"?"STORED":$Ud[1]),);}return$J;}function
indexes($R,$f=null){$J=array();foreach(get_rows("SHOW INDEX FROM ".table($R),$f)as$K){$B=$K["Key_name"];$J[$B]["type"]=($B=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$B]["columns"][]=$K["Column_name"];$J[$B]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$B]["descs"][]=null;$J[$B]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($R){static$bi='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=array();$Lb=get_val("SHOW CREATE TABLE ".table($R),1);if($Lb){preg_match_all("~CONSTRAINT ($bi) FOREIGN KEY ?\\(((?:$bi,? ?)+)\\) REFERENCES ($bi)(?:\\.($bi))? \\(((?:$bi,? ?)+)\\)(?: ON DELETE (".driver()->onActions."))?(?: ON UPDATE (".driver()->onActions."))?~",$Lb,$Tf,PREG_SET_ORDER);foreach($Tf
as$A){preg_match_all("~$bi~",$A[2],$Uj);preg_match_all("~$bi~",$A[5],$Ik);$J[idf_unescape($A[1])]=array("db"=>idf_unescape($A[4]!=""?$A[3]:$A[4]),"table"=>idf_unescape($A[4]!=""?$A[4]:$A[3]),"source"=>array_map('Adminer\idf_unescape',$Uj[0]),"target"=>array_map('Adminer\idf_unescape',$Ik[0]),"on_delete"=>($A[6]?:"RESTRICT"),"on_update"=>($A[7]?:"RESTRICT"),);}}return$J;}function
view($B){return
array("select"=>preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU','',get_val("SHOW CREATE VIEW ".table($B),1)));}function
collations(){$J=array();foreach(get_rows("SHOW COLLATION")as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$w=>$X)sort($J[$w]);return$J;}function
information_schema($h,$M=""){return($h=="information_schema")||(min_version(5.5)&&$h=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",connection()->error));}function
create_database($h,$pb){return
queries("CREATE DATABASE ".idf_escape($h).($pb?" COLLATE ".q($pb):""));}function
drop_databases(array$g){$J=apply_queries("DROP DATABASE",$g,'Adminer\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($B,$pb){$J=false;if(create_database($B,$pb)){$T=array();$Wl=array();foreach(tables_list()as$R=>$U){if($U=='VIEW')$Wl[]=$R;else$T[]=$R;}$J=(!$T&&!$Wl)||move_tables($T,$Wl,$B);drop_databases($J?array(DB):array());}return$J;}function
auto_increment(){$Da=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$u){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$u["columns"],true)){$Da="";break;}if($u["type"]=="PRIMARY")$Da=" UNIQUE";}}return" AUTO_INCREMENT$Da";}function
alter_table($R,$B,array$l,array$Hd,$ub,$Rc,$pb,$Ca,$Sh){$sa=array();foreach($l
as$k){if($k[1]){$i=$k[1][3];if(preg_match('~ GENERATED~',$i)){$k[1][3]=(connection()->flavor=='maria'?"":$k[1][2]);$k[1][2]=$i;}$sa[]=($R!=""?($k[0]!=""?"CHANGE ".idf_escape($k[0]):"ADD"):" ")." ".implode($k[1]).($R!=""?$k[2]:"");}else$sa[]="DROP ".idf_escape($k[0]);}$sa=array_merge($sa,$Hd);$gk=($ub!==null?" COMMENT=".q($ub):"").($Rc?" ENGINE=".q($Rc):"").($pb?" COLLATE ".q($pb):"").($Ca!=""?" AUTO_INCREMENT=$Ca":"");if($Sh){$Th=array();if($Sh["partition_by"]=='RANGE'||$Sh["partition_by"]=='LIST'){foreach($Sh["partition_names"]as$w=>$X){$Y=$Sh["partition_values"][$w];$Th[]="\n  PARTITION ".idf_escape($X)." VALUES ".($Sh["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$gk
.="\nPARTITION BY $Sh[partition_by]($Sh[partition])";if($Th)$gk
.=" (".implode(",",$Th)."\n)";elseif($Sh["partitions"])$gk
.=" PARTITIONS ".(+$Sh["partitions"]);}elseif($Sh===null)$gk
.="\nREMOVE PARTITIONING";if($R=="")return
queries("CREATE TABLE ".table($B)." (\n".implode(",\n",$sa)."\n)$gk");if($R!=$B)$sa[]="RENAME TO ".table($B);if($gk)$sa[]=ltrim($gk);return($sa?queries("ALTER TABLE ".table($R)."\n".implode(",\n",$sa)):true);}function
alter_indexes($R,$sa){$Za=array();foreach($sa
as$X)$Za[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return
queries("ALTER TABLE ".table($R).implode(",",$Za));}function
truncate_tables(array$T){return
apply_queries("TRUNCATE TABLE",$T);}function
drop_views(array$Wl){return
queries("DROP VIEW ".implode(", ",array_map('Adminer\table',$Wl)));}function
drop_tables(array$T){return
queries("DROP TABLE ".implode(", ",array_map('Adminer\table',$T)));}function
move_tables(array$T,array$Wl,$Ik){$Ti=array();foreach($T
as$R)$Ti[]=table($R)." TO ".idf_escape($Ik).".".table($R);if(!$Ti||queries("RENAME TABLE ".implode(", ",$Ti))){$ic=array();foreach($Wl
as$R)$ic[table($R)]=view($R);connection()->select_db($Ik);$h=idf_escape(DB);foreach($ic
as$B=>$Vl){if(!queries("CREATE VIEW $B AS ".str_replace(" $h."," ",$Vl["select"]))||!queries("DROP VIEW $h.$B"))return
false;}return
true;}return
false;}function
copy_tables(array$T,array$Wl,$Ik){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($T
as$R){$B=($Ik==DB?table("copy_$R"):idf_escape($Ik).".".table($R));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $B"))||!queries("CREATE TABLE $B LIKE ".table($R))||!queries("INSERT INTO $B SELECT * FROM ".table($R)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){$hl=$K["Trigger"];list($ad,$Yg)=trigger_event($K);if(!queries("CREATE TRIGGER ".($Ik==DB?idf_escape("copy_$hl"):idf_escape($Ik).".".idf_escape($hl))." $K[Timing] $ad".($Yg!=""?" $Yg":"")." ON $B FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($Wl
as$R){$B=($Ik==DB?table("copy_$R"):idf_escape($Ik).".".table($R));$Vl=view($R);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $B"))||!queries("CREATE VIEW $B AS $Vl[select]"))return
false;}return
true;}function
trigger_event(array$K){$cd=explode(",",$K["Event"]);$J=array();foreach(array("DELETE","INSERT","UPDATE")as$ad){if(in_array($ad,$cd))$J[]=$ad;}$J=implode(" OR ",$J);if(in_array("UPDATE",$cd)&&min_version('','12.0.1')&&preg_match('~\s(?:BEFORE|AFTER)\s+(.+?)\s+ON\s~is',get_val("SHOW CREATE TRIGGER ".idf_escape($K["Trigger"]),2),$A)&&preg_match('~\bOF\s+(.+)~is',$A[1],$Yg))return
array("$J OF",$Yg[1]);return
array($J,"");}function
trigger($B,$R){if($B=="")return
array();$L=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($B));$J=reset($L);if($J)list($J["Event"],$J["Of"])=trigger_event($J);return($J?:array());}function
triggers($R){$J=array();foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){list($ad)=trigger_event($K);$J[$K["Trigger"]]=array($K["Timing"],$ad);}return$J;}function
trigger_options(){return
array("Timing"=>array("BEFORE","AFTER"),"Event"=>(min_version('','12.0.1')?array("INSERT","UPDATE","UPDATE OF","DELETE","INSERT OR UPDATE","INSERT OR UPDATE OF","DELETE OR INSERT","DELETE OR UPDATE","DELETE OR UPDATE OF","DELETE OR INSERT OR UPDATE","DELETE OR INSERT OR UPDATE OF",):array("INSERT","UPDATE","DELETE")),"Type"=>array("FOR EACH ROW"),);}function
routine($B,$U){$L=get_rows("SELECT PARAMETER_NAME, DTD_IDENTIFIER, PARAMETER_MODE, COLLATION_NAME
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($B)."
ORDER BY ORDINAL_POSITION");$l=array();foreach($L
as$K){$Qd=$K["DTD_IDENTIFIER"];list($pl,$x,$yl)=parse_type($Qd);$l[]=array("field"=>$K["PARAMETER_NAME"],"type"=>$pl,"length"=>$x,"unsigned"=>$yl,"null"=>true,"full_type"=>$Qd,"inout"=>($U=="FUNCTION"?"":$K["PARAMETER_MODE"]),"collation"=>$K["COLLATION_NAME"],);}$J=connection()->query("SELECT
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
routine_options($ej){return
array("DEFINER"=>array(),"DETERMINISTIC"=>array("NOT DETERMINISTIC","DETERMINISTIC"),"SQL_DATA_ACCESS"=>array("CONTAINS SQL","NO SQL","READS SQL DATA","MODIFIES SQL DATA"),"SQL_SECURITY"=>array("SQL SECURITY DEFINER","SQL SECURITY INVOKER"),"COMMENT"=>array(),);}function
routine_id($B,array$K){return
idf_escape($B);}function
last_id($I){return
get_val("SELECT LAST_INSERT_ID()");}function
explain(Db$e,$H){return$e->query("EXPLAIN ".(min_version(5.7)?"":"PARTITIONS ").$H);}function
found_rows(array$S,array$Z){return($Z||$S["Engine"]!="InnoDB"?null:$S["Rows"]);}function
create_sql($R,$Ca,$lk){$J=get_val("SHOW CREATE TABLE ".table($R),1);if(!$Ca)$J=preg_replace('~(\n\)[^\n]*?) AUTO_INCREMENT=\d+~','\1',$J);return$J;}function
truncate_sql($R){return"TRUNCATE ".table($R);}function
use_sql($Xb,$lk=""){$B=idf_escape($Xb);$J="";if(preg_match('~CREATE~',$lk)&&($Kb=get_val("SHOW CREATE DATABASE $B",1))){set_utf8mb4($Kb);if($lk=="DROP+CREATE")$J="DROP DATABASE IF EXISTS $B;\n";$J
.="$Kb;\n";}return$J."USE $B";}function
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
unconvert_field(array$k,$J){if(preg_match("~binary~",$k["type"]))$J="UNHEX($J)";if($k["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($k["type"]=="vector")$J=(connection()->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geom|point|linestring|polygon~",$k["type"])){$si=(min_version(8)?"ST_":"");$J=$si."GeomFromText($J, $si"."SRID($k[field]))";}return$J;}function
support($sd){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(min_version(8)?'|descidx':'').(min_version('8.0.16','10.2.1')?'|check':'').(min_version(8,99)?'|fast_status':'').')$~',$sd);}function
kill_process($s){return
queries("KILL ".number($s));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return
get_val("SELECT @@max_connections");}function
types($nd=false){return
array();}function
type_values($s){return"";}function
type_definition($s){return
array("kind"=>"","definition"=>"");}function
schemas(){return
array();}function
get_schema(){return"";}function
set_schema($M,$f=null){return
true;}}define('Adminer\JUSH',Driver::$jush);define('Adminer\SERVER',"".$_GET[DRIVER]);define('Adminer\DB',"$_GET[db]");define('Adminer\ME',preg_replace('~\?.*~','',relative_uri()).'?'.(sid()?SID.'&':'').($_GET["ext"]?"ext=".url_escape($_GET["ext"]).'&':'').(isset($_GET[DRIVER])?DRIVER."=".url_escape(SERVER).'&':'').(isset($_GET["username"])?"username=".url_escape($_GET["username"]).'&':'').(isset($_GET["db"])?'db='.url_escape(DB).'&'.(isset($_GET["ns"])?"ns=".url_escape($_GET["ns"])."&":""):''));if(isset($_GET["manifest"])){header("Content-Type: application/manifest+json; charset=utf-8");header("Cache-Control: no-cache");echo
json_encode(adminer()->manifest(),64|256);exit;}function
page_header($Uk,$j="",$Sa=array(),$Vk="",$Tg=false,$zc=""){if($Tg){header("HTTP/1.1 404 Not Found");$j=($j?:'Not found.');}page_headers();if(is_ajax()&&$j){page_messages($j);exit;}if(!ob_get_level())ob_start('ob_gzhandler',4096);$Wk=$Uk.($Vk!=""?": $Vk":"");$Xk=strip_tags($Wk.(SERVER!=""&&SERVER!="localhost"?h(" - ".SERVER):"")." - ".adminer()->name());echo'<!DOCTYPE html>
<html lang=\'en\' dir=\'ltr\' class=\'ltr nojs\'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>',$Xk,'</title>
<link rel="stylesheet" href="',h(preg_replace("~\\?.*~","",ME)."?file=default.css&version=6.1.1+ba55ceef"),'">
';$Pb=adminer()->css();if(is_int(key($Pb)))$Pb=array_fill_keys($Pb,'light');$je=in_array('light',$Pb)||in_array('',$Pb);$he=in_array('dark',$Pb)||in_array('',$Pb);$Tb=($je?($he?null:false):($he?:null));$ig=" media='(prefers-color-scheme: dark)'";if($Tb!==false)echo"<link rel='stylesheet'".($Tb?"":$ig)." href='".h(preg_replace("~\\?.*~","",ME)."?file=dark.css&version=6.1.1+ba55ceef")."'>\n";echo"<meta name='color-scheme' content='".($Tb===null?"light dark":($Tb?"dark":"light"))."'>\n",script_src(preg_replace("~\\?.*~","",ME)."?file=functions.js&version=6.1.1+ba55ceef");if(adminer()->head($Tb))echo"<link rel='icon' href='data:image/gif;base64,"."R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n","<link rel='apple-touch-icon' href='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.1+ba55ceef")."'>\n";if(adminer()->manifest())echo"<link rel='manifest' href='".h(preg_replace('~\?.*~','',ME)."?manifest=")."' crossorigin='use-credentials'>\n";foreach($Pb
as$El=>$xg){$b=($xg=='dark'&&!$Tb?$ig:($xg=='light'&&$he?" media='(prefers-color-scheme: light)'":""));echo"<link rel='stylesheet'$b href='".h($El)."'>\n";}echo"\n<body class='";adminer()->bodyClass();echo"'>\n",script((isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"onload = partial(verifyVersion, '".VERSION."');\n")."
const offlineMessage = '".js_escape('You are offline.')."';
const numberFormat = '".js_escape('#,##0')."';
const numberDigits = '".js_escape('0123456789')."';
const urlSeparators = '".js_escape(ini_get("arg_separator.input"))."';"),"<div id='help' class='jush-".JUSH." jsonly hidden'".on('mouseover','helpKeep').on('mouseout','helpMouseout')."></div>\n","<div id='content'>\n","<span id='menuopen' class='jsonly'".on('click','menuToggle')."><button title='".'Menu'."' class='icon icon-move' aria-expanded='false'></button></span>\n";if($Sa!==null){$z=substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1);echo'<p id="breadcrumb"><a href="'.h($z?:".").'">'.get_driver(DRIVER).'</a> » ';$z=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);$O=adminer()->serverName(SERVER);$O=($O!=""?$O:'Server');if($Sa===false)echo"$O\n";else{echo"<a href='".h($z.(DB!=""&&support("single_db")?"&db=":""))."' accesskey='1' title='Alt+Shift+1'>$O</a> » ";$uj="";if(is_string($Sa)){$uj=$Sa;$Sa=array();}if($_GET["ns"]!=""||(DB!=""&&is_array($Sa))){$Zb="$z&db=".url_escape(DB).(support("scheme")?"&ns=":"").(support("single_table")?"&select=":"");echo'<a href="'.h($Zb.($_GET["ns"]==""?$uj:"")).'">'.h(DB).'</a> » ';}if(is_array($Sa)){if($_GET["ns"]!="")echo'<a href="'.h(substr(ME,0,-1).$uj).'">'.h($_GET["ns"]).'</a> » ';foreach($Sa
as$w=>$X){$kc=(is_array($X)?$X[1]:h($X));if($kc!="")echo"<a href='".h(ME."$w=").url_escape(is_array($X)?$X[0]:$X)."'>$kc</a> » ";}}echo"$Uk\n";}}echo"<h2>$Wk$zc</h2>\n","<div id='ajaxstatus' role='status' class='jsonly'></div>\n";restart_session();page_messages($j);adminer()->serviceWorker();$g=&get_session("dbs");if(DB!=""&&$g&&!in_array(DB,$g,true))$g=null;stop_session();define('Adminer\PAGE_HEADER',1);ob_flush();flush();if($Tg){page_footer($Tg===true?"":$Tg);exit;}}function
service_worker(){$Ri=has_passwords();$mb=($Ri?"navigator.serviceWorker.register('".js_escape(preg_replace('~\?.*~','',ME)."?file=worker.js&version=6.1.1+ba55ceef")."', {scope: location.pathname}).catch(() => {});":"navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));");echo
script("if (navigator.serviceWorker) {\n\t$mb\n}");}function
has_passwords(){foreach((array)$_SESSION["pwds"]as$Hj){foreach($Hj
as$Ml){foreach($Ml
as$F){if($F!==null)return
true;}}}return
false;}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-Frame-Options: deny");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");foreach(adminer()->csp(csp())as$Ob){$ne=array();foreach($Ob
as$w=>$X)$ne[]="$w $X";header("Content-Security-Policy: ".implode("; ",$ne));}adminer()->headers();}function
csp(){return
array(array("script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://www.adminer.org","frame-src"=>"https://www.adminer.org","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",),);}function
design_checksums(){$Kl=array();foreach(array_keys(adminer()->css())as$El)$Kl[preg_replace('~\?.*~','',$El)]=true;$J=array();foreach(array("adminer.css","adminer-dark.css")as$m){if($Kl[$m]&&file_exists($m)){preg_match('~^/\* Adminer design ([-\w]+) \*/~',file_get_contents($m),$A);$J[$m]=array((string)$A[1],Plugins::checksum($m));}}return$J;}function
official_design_checksums(){return
array('adminer-border/adminer.css'=>'ec757f3e','adminer-dark/adminer-dark.css'=>'a26bcd7b','brade/adminer.css'=>'be4161f0','bueltge/adminer.css'=>'1a8f00b4','cpanel/adminer.css'=>'59ce604e','dracula/adminer-dark.css'=>'cfaf61dd','esterka/adminer.css'=>'1f805f36','flat/adminer.css'=>'49a61af9','galkaev/adminer-dark.css'=>'16c46f94','haeckel/adminer.css'=>'147a3565','hever/adminer.css'=>'ef0e1948','konya/adminer.css'=>'2b409696','lavender-light/adminer.css'=>'bf03f5d7','lucas-sandery/adminer.css'=>'6596353','mancave/adminer-dark.css'=>'e1ac813d','mvt/adminer.css'=>'ebd3afdc','nette/adminer.css'=>'5ab360e7','ng9/adminer.css'=>'488583cf','nicu/adminer.css'=>'216f097b','pappu687/adminer.css'=>'b58d128c','paranoiq/adminer.css'=>'64d27e5','pepa-linha/adminer.css'=>'baf25f0','pokorny/adminer.css'=>'ee9eea6d','price/adminer.css'=>'81be9a85','rmsoft/adminer.css'=>'6cd4a237','rmsoft_blue-dark/adminer.css'=>'32102a8','rmsoft_blue/adminer.css'=>'7d8d5b18','win98/adminer.css'=>'e82d63c3',);}function
version_iframe(){return(isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"<noscript><iframe sandbox src='https://www.adminer.org/version/?current=".VERSION."&amp;noscript=1'></iframe></noscript>");}function
get_nonce(){static$Sg;if(!$Sg)$Sg=base64_encode(rand_string());return$Sg;}function
page_messages($j){$Dl=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$pg=idx($_SESSION["messages"],$Dl);if($pg){echo"<div class='message'>".implode("</div>\n<div class='message'>",$pg)."</div>".script("messagesPrint();");unset($_SESSION["messages"][$Dl]);}if($j)echo"<div class='error'>$j</div>\n";if(adminer()->error)echo"<div class='error'>".adminer()->error."</div>\n";}function
page_footer($wg=""){echo"</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";adminer()->navigation($wg);echo"</div>\n";if($wg!="auth")echo'<form action="" method="post">
<p class="logout">
<span title="Username">',h($_GET["username"])."\n",'</span>
<input type=\'submit\' name=\'logout\' value=\'Logout\' id=\'logout\'>
',input_token(),'</form>
';echo"</div>\n\n",script("setupSubmitHighlight(document);");}function
int32($Dg){while($Dg>=2147483648)$Dg-=4294967296;while($Dg<=-2147483649)$Dg+=4294967296;return(int)$Dg;}function
long2str(array$W,$Yl){$mj='';foreach($W
as$X)$mj
.=pack('V',$X);if($Yl)return
substr($mj,0,end($W));return$mj;}function
str2long($mj,$Yl){$W=array_values(unpack('V*',str_pad($mj,4*ceil(strlen($mj)/4),"\0")));if($Yl)$W[]=strlen($mj);return$W;}function
xxtea_mx($im,$hm,$ok,$pf){return
int32((($im>>5&0x7FFFFFF)^$hm<<2)+(($hm>>3&0x1FFFFFFF)^$im<<4))^int32(($ok^$hm)+($pf^$im));}function
encrypt_string($ik,$w){if($ik=="")return"";$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($ik,true);$Dg=count($W)-1;$im=$W[$Dg];$hm=$W[0];$Di=floor(6+52/($Dg+1));$ok=0;while($Di-->0){$ok=int32($ok+0x9E3779B9);$Hc=$ok>>2&3;for($Hh=0;$Hh<$Dg;$Hh++){$hm=$W[$Hh+1];$Cg=xxtea_mx($im,$hm,$ok,$w[$Hh&3^$Hc]);$im=int32($W[$Hh]+$Cg);$W[$Hh]=$im;}$hm=$W[0];$Cg=xxtea_mx($im,$hm,$ok,$w[$Hh&3^$Hc]);$im=int32($W[$Dg]+$Cg);$W[$Dg]=$im;}return
long2str($W,false);}function
decrypt_string($ik,$w){if($ik=="")return"";if(!$w)return
false;$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($ik,false);$Dg=count($W)-1;$im=$W[$Dg];$hm=$W[0];$Di=floor(6+52/($Dg+1));$ok=int32($Di*0x9E3779B9);while($ok){$Hc=$ok>>2&3;for($Hh=$Dg;$Hh>0;$Hh--){$im=$W[$Hh-1];$Cg=xxtea_mx($im,$hm,$ok,$w[$Hh&3^$Hc]);$hm=int32($W[$Hh]-$Cg);$W[$Hh]=$hm;}$im=$W[$Dg];$Cg=xxtea_mx($im,$hm,$ok,$w[$Hh&3^$Hc]);$hm=int32($W[0]-$Cg);$W[0]=$hm;$ok=int32($ok-0x9E3779B9);}return
long2str($W,true);}$ei=array();if($_COOKIE["adminer_permanent"]){foreach(explode(" ",$_COOKIE["adminer_permanent"])as$X){list($w)=explode(":",$X);$ei[$w]=$X;}}function
add_invalid_login(){$Ka=get_temp_dir()."/adminer-invalid";foreach(glob("$Ka*")?:array($Ka)as$m){$o=file_open_lock($m);if($o)break;}if(!$o)$o=file_open_lock("$Ka-".rand_string());if(!$o)return;$cf=json_decode(stream_get_contents($o),true);$Rk=time();if($cf){foreach($cf
as$df=>$X){if($X[0]<$Rk)unset($cf[$df]);}}$af=&$cf[adminer()->bruteForceKey()];if(!$af)$af=array($Rk+30*60,0);$af[1]++;file_write_unlock($o,json_encode($cf));}function
check_invalid_login(array&$ei){$cf=array();foreach(glob(get_temp_dir()."/adminer-invalid*")as$m){$o=file_open_lock($m);if($o){$cf=json_decode(stream_get_contents($o),true);file_unlock($o);break;}}$w=adminer()->bruteForceKey();$af=idx($cf,$w,array());$Rg=($af[1]>29?$af[0]-time():0);if($Rg>0){$j=lang_format(array('Too many unsuccessful logins, try again in %d minute.','Too many unsuccessful logins, try again in %d minutes.'),ceil($Rg/60));if($_SERVER["HTTP_X_FORWARDED_FOR"]!=""&&$w==$_SERVER["REMOTE_ADDR"])$j
.='<br>'.sprintf('Use the %s <a%s>plugin</a> if Adminer runs behind a reverse proxy.','<b>login-reverse-proxy</b>'," href='https://www.adminer.org/plugins/?version=".VERSION."'".target_blank());auth_error($j,$ei,false);}}function
password_required(){static$J;if($J===null){$J=(bool)get_session("password_required");if(!$J){$Nb=adminer()->credentials();$J=!is_object(Driver::connect($Nb[0],$Nb[1],""));if($J)set_session("password_required",true);}}return$J;}function
require_password_link($F){$_g="<a href='https://www.adminer.org/password/'".target_blank().">".'More options'."</a>";if(!function_exists('password_hash'))return" $_g";$hi=($F!==null?$F:base64_encode(substr(pack("H*",rand_string()),0,12)));$me=password_hash($hi,PASSWORD_DEFAULT);$m="adminer-plugins.php";$hd=file_exists("adminer-plugins.php");if($hd)$Ye=($F!==null?sprintf('Add this line to %s to require the entered password:',"<b>$m</b>"):sprintf('Add this line to %s to require the password %s:',"<b>$m</b>","<b>$hi</b>"));else{$m="<button name='password_less' value='".h($me)."' class='link'>$m</button>";$Ye=($F!==null?sprintf('Save %s next to Adminer to require the entered password:',$m):sprintf('Save %s next to Adminer to require the password %s:',$m,"<b>$hi</b>"));}$Jf="\t<a>new</a> Adminer\\Password(<span class='jush-apo'>'".h($me)."'</span>),";$J="<p>$Ye
<pre><code class='jush'>".($hd?$Jf:"&lt;?php\n<a>return</a> <a>array</a>(\n$Jf\n);")."</code></pre>
<p>$_g
";return" <a href='#password-less' class='toggle'>".'Require a password.'."</a>
<div id='password-less' class='hidden'>".($hd?$J:"<form action='' method='post'>\n".$J.input_token()."</form>")."</div>";}if(preg_match('~^[-\w$./]+$~',$_POST["password_less"])&&verify_token()){header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=adminer-plugins.php");echo"<?php\nreturn array(\n\tnew Adminer\\Password('$_POST[password_less]'),\n);\n";exit;}$Ba=$_POST["auth"];if($Ba&&(!adminer()->verifyLoginToken()||verify_token())){session_regenerate_id();$Tl=$Ba["driver"];$O=$Ba["server"];$V=$Ba["username"];$F=(string)$Ba["password"];$h=$Ba["db"];set_password($Tl,$O,$V,$F);$_SESSION["db"][$Tl][$O][$V][$h]=true;if($Ba["permanent"]){$w=implode("-",array_map('base64_encode',array($Tl,$O,$V,$h)));$yi=adminer()->permanentLogin(true);$ei[$w]="$w:".base64_encode($yi?encrypt_string($F,$yi):"");cookie("adminer_permanent",implode(" ",$ei));}if(!array_diff(array_keys($_POST),array("auth","token"))||$Tl!=DRIVER||$O!=SERVER||$V!==$_GET["username"]||$h!=DB)redirect(auth_url($Tl,$O,$V,$h));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){Driver::disconnect();foreach(array("pwds","db","dbs","queries")as$w)set_session($w,null);unset_permanent($ei);redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1),'Logout successful.'.' '.'Thanks for using Adminer. Consider <a href="https://www.adminer.org/en/donation/">donating</a>.');}elseif($ei&&!$_SESSION["pwds"]){session_regenerate_id();$yi=adminer()->permanentLogin();foreach($ei
as$w=>$X){list(,$kb)=explode(":",$X);list($Tl,$O,$V,$h)=array_map('base64_decode',explode("-",$w));set_password($Tl,$O,$V,decrypt_string(base64_decode($kb),$yi));$_SESSION["db"][$Tl][$O][$V][$h]=true;}}function
unset_permanent(array&$ei){foreach($ei
as$w=>$X){list($Tl,$O,$V,$h)=array_map('base64_decode',explode("-",$w));if($Tl==DRIVER&&$O==SERVER&&$V==$_GET["username"]&&$h==DB)unset($ei[$w]);}cookie("adminer_permanent",implode(" ",$ei));}function
auth_error($j,array&$ei,$bf=true){$Ij=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$Ij]||$_GET[$Ij])&&!$_SESSION["token"])$j='Session expired. Please log in again.';elseif($bf&&($F=get_password())!==null){restart_session();add_invalid_login();if($F===false)$j
.=($j?'<br>':'').sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.',target_blank(),'<code>permanentLogin()</code>');set_password(DRIVER,SERVER,$_GET["username"],null);unset_permanent($ei);}}if(!$_COOKIE[$Ij]&&$_GET[$Ij]&&ini_bool("session.use_only_cookies"))$j='Session support must be enabled.';$Lh=session_get_cookie_params();cookie("adminer_key",($_COOKIE["adminer_key"]?:rand_string()),$Lh["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header('Login',$j,null);echo"<form action='' method='post'>\n","<div>";if(hidden_fields($_POST,array("auth","token")))echo"<p class='message'>".'The action will be performed after successful login with the same credentials.'."\n";echo
input_token(),"</div>\n";adminer()->loginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!class_exists('Adminer\Db')){unset($_SESSION["pwds"][DRIVER]);unset_permanent($ei);page_header('No extension',sprintf('None of the supported PHP extensions (%s) are available.',implode(", ",Driver::$extensions)),false);page_footer("auth");exit;}$e='';if(isset($_GET["username"])&&is_string(get_password())){check_invalid_login($ei);$Nb=adminer()->credentials();$e=Driver::connect($Nb[0],$Nb[1],$Nb[2]);if(is_object($e)){Db::$instance=$e;Driver::$instance=new
Driver($e);if($e->flavor)save_settings(array("vendor-".DRIVER."-".SERVER=>get_driver(DRIVER)));}}$Pf=null;if(!is_object($e)||($Pf=adminer()->login($_GET["username"],get_password()))!==true){$j=(is_string($e)?nl_br(h($e)):(is_string($Pf)?$Pf:'Invalid credentials.')).(preg_match('~^ | $~',get_password())?'<br>'.'There is a space in the entered password, which might be the cause.':'');auth_error($j,$ei);}if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){page_header('Logout','Invalid CSRF token. Submit the form again.');page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($Ba&&$_POST["token"])$_POST["token"]=get_token();$j='';if($_POST){if(!verify_token()){header("HTTP/1.1 403 Forbidden");$j='Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.';}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){header("HTTP/1.1 413 Content Too Large");$j=sprintf('The POST data is too large. Reduce the data or increase the %s configuration directive.',"<b>post_max_size</b>");if(isset($_GET["sql"]))$j
.=' '.'You can upload a large SQL file via FTP and import it from the server.';}function
print_select_result($I,$f=null,array$yh=array(),&$y=0,&$Ic=false){$Lf=array();$v=array();$d=array();$T=array();$wi=array();$Kc=array();$ql=array();$J=array();$yg=$Ic;$Ic=false;for($r=0;(!$y||$r<$y)&&($K=$I->fetch_row());$r++){if(!$r){echo"<div class='scrollable'>\n","<table class='nowrap odds'".($yg?on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown'):"").">\n","<thead><tr>";for($mf=0;$mf<count($K);$mf++){$k=$I->fetch_field();$B=$k->name;$R=(isset($k->table)?$k->table:"");$xh=(isset($k->orgtable)?$k->orgtable:"");$wh=(isset($k->orgname)?$k->orgname:$B);$pl=driver()->typeName($k);if($yh&&JUSH=="sql")$Lf[$mf]=($B=="table"?"table=":($B=="possible_keys"?"indexes=":null));elseif($xh!=""){$oa=($R!=""?$R:$xh);if($R!="")$J[$R]=$xh;if(!isset($v[$oa])){if(!isset($wi[$xh])){$wi[$xh]=array();foreach(indexes($xh,$f)as$u){if($u["type"]=="PRIMARY"){$wi[$xh]=array_flip($u["columns"]);break;}}}$T[$oa]=$xh;$v[$oa]=$wi[$xh];$d[$oa]=$wi[$xh];}if(isset($d[$oa][$wh])){unset($d[$oa][$wh]);$v[$oa][$wh]=$mf;$Lf[$mf]=$oa;}elseif($yg&&isset($k->orgname)&&$k->db==DB&&!is_blob(array("type"=>$pl)))$Kc[$mf]=array($oa,$wh,preg_match('~text|json|lob~',$pl));}$ql[$mf]=$pl;echo"<th title='".h(trim(($xh!=""?"$xh.$wh":($k->name!=$wh?$wh:""))." ".$pl))."'>".h($B).($yh?doc_link(array('sql'=>"explain-output.html#explain_".strtolower($B),'mariadb'=>"explain/#the-columns-in-explain-select",)):"");}foreach($Kc
as$mf=>$Xa){if($d[$Xa[0]])unset($Kc[$mf]);}echo"<tbody>\n";}$De=array();foreach($v
as$oa=>$u){if($u&&!$d[$oa]){$t="";foreach($u
as$nb=>$mf){if($K[$mf]===null){$t=null;break;}$t
.="&where[".url_escape(bracket_escape($nb))."]=".url_escape($K[$mf]);}$De[$oa]=$t;}}echo"<tr>";foreach($K
as$w=>$X){$z="";if(isset($Lf[$w])){if($yh&&JUSH=="sql"){$R=$K[array_search("table=",$Lf)];$z=ME.$Lf[$w].url_escape($yh[$R]!=""?$yh[$R]:$R);}elseif(idx($De,$Lf[$w])!==null)$z=ME."edit=".url_escape($T[$Lf[$w]]).$De[$Lf[$w]];}$b="";$Xa=idx($Kc,$w);if($Xa&&idx($De,$Xa[0])!==null&&is_utf8($X)){$Ic=true;$b=" data-name='".h("val[".bracket_escape($T[$Xa[0]])."][".bracket_escape(substr($De[$Xa[0]],1))."][".bracket_escape($Xa[1])."]")."' data-text='".($Xa[2]?1:0)."'";}$X=select_value($X,$z,array('type'=>(preg_match('~binary~',$ql[$w])?'blob':$ql[$w])),null);echo"<td".(preg_match(number_type(),$ql[$w])?" class='number'":"")."$b>$X";}}$y=$r;echo($r?"</table>\n</div>":"<p class='message'>".'No rows.')."\n";return$J;}function
textarea($B,$Y,$L=10,$rb=80,$of=JUSH){echo"<textarea name='".h($B)."' rows='$L' cols='$rb' class='sqlarea jush-".h($of)."' spellcheck='false' wrap='off'>";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($b,array$C,$Y="",$fi=""){if($C&&$Y!=""&&!isset($C[$Y]))$C=array($Y=>$Y)+$C;$Hk=($C?"select":"input");return"<$Hk$b".($C?"><option value=''>$fi".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$fi'>");}function
json_row($w,$X=null,$Zc=true){static$Cd=true;if($Cd)echo"{";if($w!=""){echo($Cd?"":",")."\n\t\"".addcslashes($w,"\r\n\t\"\\/").'": '.($X!==null?($Zc?'"'.addcslashes($X,"\r\n\"\\/").'"':$X):'null');$Cd=false;}else{echo"\n}\n";$Cd=true;}}function
flat_collations(){$qb=collations();return(is_array(reset($qb))?call_user_func_array('array_merge',array_values($qb)):$qb);}function
edit_type($w,array$k,array$qb,array$Jd=array(),array$pd=array()){$U=(string)$k["type"];echo"<td><select name='".h($w)."[type]' class='type' aria-labelledby='label-type'".on_help_value().">";if($U&&!array_key_exists($U,driver()->types())&&!isset($Jd[$U])&&!in_array($U,$pd))$pd[]=$U;$jk=driver()->structuredTypes();if($Jd)$jk['Foreign keys']=$Jd;echo
optionlist(array_merge($pd,$jk),$U),"</select><td>","<input name='".h($w)."[length]' value='".h($k["length"])."' size='3'".(!$k["length"]&&preg_match('~var(char|binary)$~',$U)?" class='required'":"")." aria-labelledby='label-length'>","<td class='options'>",($qb?"<input list='collations' name='".h($w)."[collation]'".option_types($U,'('.text_type().')$')." value='".h($k["collation"])."' placeholder='(".'collation'.")'>":''),(driver()->unsigned?"<select name='".h($w)."[unsigned]'".option_types($U,'^$|'.number_type()).'><option>'.optionlist(driver()->unsigned,$k["unsigned"]).'</select>':''),(isset($k['on_update'])?"<select name='".h($w)."[on_update]'".option_types($U,'timestamp|datetime').'>'.optionlist(array(""=>"(".'ON UPDATE'.")","CURRENT_TIMESTAMP"),(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"CURRENT_TIMESTAMP":$k["on_update"])).'</select>':''),($Jd?"<select name='".h($w)."[on_delete]'".option_types($U,'`')."><option value=''>(".'ON DELETE'.")".optionlist(explode("|",driver()->onActions),$k["on_delete"])."</select> ":" ");}function
option_types($U,$ql){return" data-types='".h($ql)."'".(preg_match("~$ql~",$U)?"":" class='hidden'");}function
process_length($x){if(JUSH=="mssql"&&preg_match('~^\s*\(?\s*max\s*\)?\s*$~i',$x))return"(max)";$Uc=driver()->enumLength;return(preg_match("~^\\s*\\(?\\s*$Uc(?:\\s*,\\s*$Uc)*+\\s*\\)?\\s*\$~",$x)&&preg_match_all("~$Uc~",$x,$Tf)?"(".implode(",",$Tf[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$x)));}function
process_in($X){$Uc=driver()->enumLength;if(preg_match("~^\\s*\\(?\\s*$Uc(?:\\s*,\\s*$Uc)*+\\s*\\)?\\s*\$~",$X)&&preg_match_all("~$Uc~",$X,$Tf))return"(".implode(", ",$Tf[0]).")";$J=array();foreach(explode(",",$X)as$lf)$J[]=q(trim($lf));return"(".implode(", ",$J).")";}function
process_type(array$k,$ob="COLLATE"){return" ".(is_user_type($k["type"])?idf_escape($k["type"]):$k["type"]).process_length($k["length"]).(preg_match(number_type(),$k["type"])&&in_array($k["unsigned"],driver()->unsigned)?" $k[unsigned]":"").(preg_match('~'.text_type().'~',$k["type"])&&$k["collation"]?" $ob ".(JUSH=="mssql"?$k["collation"]:q($k["collation"])):"");}function
process_field(array$k,array$nl){if($k["on_update"])$k["on_update"]=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$k["on_update"]);return
array(idf_escape(trim($k["field"])),process_type($nl),($k["null"]?" NULL":" NOT NULL"),default_value($k),(preg_match('~timestamp|datetime~',$k["type"])&&$k["on_update"]?" ON UPDATE $k[on_update]":""),(support("comment")&&$k["comment"]!=""?" COMMENT ".q($k["comment"]):""),($k["auto_increment"]?auto_increment():null),);}function
default_value(array$k){if($k["default"]===null)return"";$i=str_replace("\r","",$k["default"]);$Ud=$k["generated"];$Q=!preg_match('~]$~',$k["length"])&&(preg_match('~char|binary|text|json|enum|set|String~',$k["type"])||driver()->enumLength($k));return(in_array($Ud,driver()->generated)?(JUSH=="mssql"?" AS ($i)".($Ud=="VIRTUAL"?"":" $Ud"):" GENERATED ALWAYS AS ($i) $Ud"):(preg_match('~^GENERATED ~i',$i)?" $i":" DEFAULT ".($Q||preg_match('~^(?![a-z])~i',$i)?(JUSH=="sql"&&preg_match('~text|json~',$k["type"])?"(".q($i).")":q($i)):str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",(JUSH=="sqlite"?"($i)":$i)))));}function
edit_fields(array$l,array$qb,$U="TABLE",array$Jd=array()){$l=array_values($l);$ec=(($_POST?$_POST["defaults"]:get_setting("defaults"))?"":" class='hidden'");$vb=(($_POST?$_POST["comments"]:get_setting("comments"))?"":" class='hidden'");echo"<thead><tr>\n",($U=="PROCEDURE"?"<td>":""),"<th id='label-name'>".($U=="TABLE"?'Column name':'Parameter name'),"<th id='label-type'>".'Type'."<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>".script("qs('#enum-edit').onblur = editingLengthBlur;"),"<th id='label-length'>".'Length',"<th>".'Options';if($U=="TABLE")echo"<th id='label-null'>NULL\n","<th><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='".'Auto Increment'."'>AI</abbr>",doc_link(array('sql'=>"example-auto-increment.html",'mariadb'=>"auto_increment/",)),"<th id='label-default'$ec>".'Default value',(support("comment")?"<th id='label-comment'$vb>".'Comment':"");$zf=!support("move_col");echo"<td>".icon("plus","add[".($zf?count($l):0)."]","+",'Add next',($zf?on('click','editingAddLastRow'):"")),"<tbody".on('click','editingClick').on('input','editingInput').on('keydown','editingKeydown').">\n";foreach($l
as$r=>$k){$r++;$zh=$k[($_POST?"orig":"field")];$rc=(isset($_POST["add"][$r-1])||(isset($k["field"])&&!idx($_POST["drop_col"],$r)))&&(support("drop_col")||$zh=="");echo"<tr".($rc?"":" hidden").">\n",($U=="PROCEDURE"?"<td>".html_select("fields[$r][inout]",explode("|",driver()->inout),$k["inout"]):"")."<th>",(support("move_col")?icon("move","","↕",'Move')." ":"");if($rc)echo"<input name='fields[$r][field]' value='".h($k["field"])."' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'".(isset($_POST["add"][$r-1])?" autofocus":"").">";echo
input_hidden("fields[$r][orig]",$zh);edit_type("fields[$r]",$k,$qb,$Jd);if($U=="TABLE"){echo"<td><label class='block'>".checkbox("fields[$r][null]",1,$k["null"],"","","","label-null")."</label>","<td><label class='block'><input type='radio' name='auto_increment_col' value='$r'".($k["auto_increment"]?" checked":"")." aria-labelledby='label-ai'></label>","<td$ec>".(driver()->generated?html_select("fields[$r][generated]",array_merge(array("","DEFAULT"),driver()->generated),$k["generated"])." ":checkbox("fields[$r][generated]",1,$k["generated"],"","","","label-default"));$b=" name='fields[$r][default]' aria-labelledby='label-default'";$Y=h($k["default"]);echo(preg_match('~\n~',$k["default"])?"<textarea$b rows='2' cols='30' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");if(support("comment")){$b=" name='fields[$r][comment]' data-maxlength='".(min_version(5.5)?1024:255)."' aria-labelledby='label-comment'";echo"<td$vb>".adminer()->commentInput('COLUMN',$b,$k["comment"]);}}echo"<td>",(support("move_col")?icon("plus","add[$r]","+",'Add next')." ":""),($zh==""||support("drop_col")?icon("cross","drop_col[$r]","x",'Remove'):"");}}function
process_fields(array&$l){if($_POST["add"]){$l=array_values($l);array_splice($l,key($_POST["add"]),0,array(array()));}return$_POST["add"]||$_POST["drop_col"];}function
drop_create($Dc,$Kb,$Ec,$Nk,$Fc,$_,$og,$mg,$ng,$hh,$Ng){if($_POST["drop"])query_redirect($Dc,$_,$og);elseif($hh=="")query_redirect($Kb,$_,$ng);elseif(support("transaction_ddl")){driver()->begin();queries_redirect($_,$mg,queries($Dc)&&queries($Kb)&&driver()->commit());driver()->rollback();}elseif($hh!=$Ng){$Mb=queries($Kb);queries_redirect($_,$mg,$Mb&&queries($Dc));if($Mb&&$Ec)queries($Ec);}else
queries_redirect($_,$mg,queries($Nk)&&queries($Fc)&&queries($Dc)&&queries($Kb));}function
create_trigger($kh,array$K){$Tk=" $K[Timing] $K[Event]".(preg_match('~ OF~',$K["Event"])?" $K[Of]":"");return"CREATE TRIGGER ".idf_escape($K["Trigger"]).(JUSH=="mssql"?$kh.$Tk:$Tk.$kh).preg_replace('~[\s;]+$~',''," $K[Type]\n$K[Statement]").";";}function
q_dollar($Q){$jc='$$';while(strpos($Q.$jc,$jc)!=strlen($Q))$jc='$_'.substr($jc,1);return$jc.$Q.$jc;}function
routine_collate($pb){static$cb=array();if($pb&&!$cb){foreach(collations()as$bb=>$Ql){foreach((array)$Ql
as$X)$cb[$X]=$bb;}}return($cb[$pb]?"CHARACTER SET ".q($cb[$pb])." ":"")."COLLATE";}function
create_routine($ej,array$K){$P=array();$l=$K["fields"];ksort($l);foreach($l
as$k){if($k["field"]!=""){$Te=(preg_match("~^(".driver()->inout.")\$~",$k["inout"])?$k["inout"]:"");$P[]="\n  ".(JUSH=="mssql"?"@$k[field]".process_type($k).($Te?" $Te":""):($Te?"$Te ":"").idf_escape($k["field"]).process_type($k,routine_collate($k["collation"])));}}$gc="";$C=array();foreach(routine_options($ej)as$w=>$Rl){$Y=idx($K["options"],$w,"");if($w=="DEFINER")$gc=($Y?" $w=".implode("@",array_map('Adminer\q',explode("@",$Y,2))):"");elseif(!$Rl){if($Y!="")$C[]="$w ".q($Y);}elseif($Y!=reset($Rl)&&in_array($Y,$Rl))$C[]=$Y;}$xf=$K["language"];$hc=preg_replace('~[\s;]+$~','',$K["definition"]);$_c=(JUSH=="pgsql"||($xf&&$xf!="sql"));$Kh=($P?implode(",",$P)."\n":"");return"CREATE$gc $ej ".table(trim($K["name"])).(JUSH=="mssql"&&$ej=="PROCEDURE"?rtrim($Kh):" ($Kh)").($ej=="FUNCTION"?"\nRETURNS".process_type($K["returns"],routine_collate($K["returns"]["collation"])):"").($xf?" LANGUAGE $xf":"").($C?"\n".implode(" ",$C):"").($_c?" AS ".q_dollar("\n".trim($hc)."\n"):(JUSH=="mssql"?"\nAS":"")."\n$hc;");}function
remove_definer($H){$gc=implode("@",array_map('Adminer\idf_escape',explode("@",logged_user(),2)));return
preg_replace('(^([A-Z =]+) DEFINER='.preg_quote($gc).')','\1',$H);}function
object_name($U,$R,array$d){return
str_replace(array("{table}","{columns}"),array($R,implode("_",$d)),adminer()->namePattern($U));}function
format_foreign_key(array$n,$B=""){$h=$n["db"];$Ug=$n["ns"];return($B!=""?" CONSTRAINT ".idf_escape($B):"")." FOREIGN KEY (".implode(", ",array_map('Adminer\idf_escape',$n["source"])).") REFERENCES ".($h!=""&&$h!=$_GET["db"]?idf_escape($h).".":"").($Ug!=""&&$Ug!=$_GET["ns"]?idf_escape($Ug).".":"").idf_escape($n["table"])." (".implode(", ",array_map('Adminer\idf_escape',$n["target"])).")".(preg_match("~^(".driver()->onActions.")\$~",$n["on_delete"])?" ON DELETE $n[on_delete]":"").(preg_match("~^(".driver()->onActions.")\$~",$n["on_update"])?" ON UPDATE $n[on_update]":"").($n["deferrable"]?" $n[deferrable]":"");}function
tar_file($m,$Yk){$J=pack("a100a8a8a8a12a12",$m,644,0,0,decoct($Yk->size),decoct(time()));$hb=8*32;for($r=0;$r<strlen($J);$r++)$hb+=ord($J[$r]);$J
.=sprintf("%06o",$hb)."\0 ";echo$J,str_repeat("\0",512-strlen($J));$Yk->send();echo
str_repeat("\0",511-($Yk->size+511)%512);}function
doc_version(){$Gj=connection()->server_info;if(JUSH=='oracle'){preg_match('~(?:.* |^)(\d+)\.\d+\.\d+\.\d+\.\d+~s',$Gj,$A);return($A[1]>=18?$A[1]:"19");}$Qi=(JUSH=='sql'||connection()->flavor=='cockroach'?'~^\d+\.\d+~':'~^\d\.?\d~');$Ul=(preg_match($Qi,$Gj,$A)?$A[0]:"");if(JUSH=='mssql')return($Ul>=15?"sql-server-ver$Ul":($Ul==12?"azuresqldb-current":"sql-server-2017"));return$Ul;}function
doc_link(array$ai,$Ok="📖"){$Ul=doc_version();$Fl=array('sql'=>"https://dev.mysql.com/doc/refman/$Ul/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(connection()->flavor=='cockroach'?"current":$Ul)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://docs.oracle.com/en/database/oracle/oracle-database/$Ul/",);if(connection()->flavor=='maria'){$Fl['sql']="https://mariadb.com/kb/en/";$ai['sql']=($ai['mariadb']?:str_replace(".html","/",$ai['sql']));}if(connection()->flavor=='cockroach'&&$ai['cockroach']){$Fl['pgsql']="https://docs.cockroachlabs.com/docs/v$Ul/";$ai['pgsql']=$ai['cockroach'];}return($ai[JUSH]?" <a href='".h($Fl[JUSH].$ai[JUSH].(JUSH=='mssql'?"?view=$Ul":""))."'".target_blank()." class='doc' title='".'Documentation'."'>$Ok</a>":"");}function
db_size($h){if(!connection()->select_db($h))return"?";$J=0;foreach(table_status()as$S)$J+=$S["Data_length"]+$S["Index_length"];return
format_number($J);}function
set_utf8mb4($Kb){static$P=false;if(!$P&&preg_match('~\butf8mb4~i',$Kb)){$P=true;echo"SET NAMES ".charset(connection()).";\n\n";}}if(DB==""&&isset($_GET["ns"]))redirect(remove_from_uri('ns'));if(!(DB!=""?connection()->select_db(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!="")page_header('Database'.": ".h(DB),adminer()->error(),true,"","db");else{if(!isset($_GET["db"])&&support("single_db")){$g=adminer()->databases();if($g)redirect(ME."db=".url_escape($g[0]));}if($_POST["db"]&&!$j)queries_redirect(substr(ME,0,-1),'Databases have been dropped.',drop_databases($_POST["db"]));page_header('Select database',$j,false);echo"<p class='links'>\n";foreach(array('database'=>'Create database','privileges'=>'Privileges','processlist'=>'Process list','variables'=>'Variables','status'=>'Status',)as$w=>$X){if(support($w))echo"<a href='".h(ME)."$w='>$X</a>\n";}echo"<p>".sprintf('%s version: %s through PHP extension %s',get_driver(DRIVER),"<b>".h(connection()->server_info)."</b>","<b>".connection()->extension."</b>")."\n","<p>".sprintf('Logged in as: %s',"<b>".h(logged_user())."</b>")."\n";$g=adminer()->databases();if($g){$qj=support("scheme");$qb=collations();echo"<form action='' method='post'>\n","<table class='checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n","<thead><tr>".(support("database")?"<td class='hover'>":"")."<th".(JUSH!='mssql'?" aria-sort='ascending'":"").">".'Database'.(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".'Refresh'."</a>":"")."<th>".'Collation'."<th>".'Tables'."<th>".'Size'." - <a href='".h(ME)."dbsize=1'".on('click','ajaxSetHtml',ME."script=connect").">".'Compute'."</a>"."<tbody>\n";$g=($_GET["dbsize"]?count_tables($g):array_flip($g));foreach($g
as$h=>$T){$dj=h(preg_replace('~&db=[^&]*~','',ME))."db=".url_escape($h);$s=h("Db-".$h);echo"<tr>".(support("database")?"<td class='hover'>".checkbox("db[]",$h,in_array($h,(array)$_POST["db"]),"","","",$s):""),"<th><a href='$dj' id='$s'>".h($h)."</a>";$pb=h(db_collation($h,$qb));echo"<td>".(support("database")?"<a href='$dj".($qj?"&amp;ns=":"")."&amp;database=' title='".'Alter database'."'>$pb</a>":$pb),"<td align='right'><a href='$dj&amp;schema=' id='tables-".h($h)."' title='".'Database schema'."'>".($_GET["dbsize"]?format_number($T):"?")."</a>","<td align='right' id='size-".h($h)."'>".($_GET["dbsize"]?db_size($h):"?"),"\n";}echo"</table>\n",(support("database")?"<div class='footer'><div>\n"."<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>\n"."<input type='hidden' name='all' value=''".on('click','countDbs').">\n"."<input type='submit' name='drop' value='".'Drop'."'".confirm().">\n"."</div></fieldset>\n"."</div></div>\n":""),input_token(),"</form>\n",script("tableCheck();");}$ia=adminer();$ji=($ia
instanceof
Plugins?$ia->plugins:array());$Cc=($ia
instanceof
Plugins?$ia->drivers:array());$oc=design_checksums();if($ji||$Cc||$oc){$ib=($ia
instanceof
Plugins?$ia->checksums():array());$ah=Plugins::officialChecksums();$Al=function($El){return" (<a href='$El'".target_blank()." class='update'>".VERSION."</a>)";};$ii=function($xd)use($ib,$ah,$Al){return($ib[$xd]&&$ah[$xd]&&$ib[$xd]!==$ah[$xd]?$Al("https://www.adminer.org/plugins/?version=".VERSION):"");};echo"<div class='plugins'>\n","<h3>".'Loaded plugins'."</h3>\n<ul>\n";foreach($ji
as$gi){$Oi=new
\ReflectionObject($gi);$lc=(method_exists($gi,'description')?$gi->description():"");if(!$lc){if(preg_match('~^/[\s*]+(.+)~',$Oi->getDocComment(),$A))$lc=$A[1];}$rj=(method_exists($gi,'screenshot')?$gi->screenshot():"");echo"<li><b>".get_class($gi)."</b>".h($lc?": $lc":"").($rj?" (<a href='".h($rj)."'".target_blank().">".'screenshot'."</a>)":"").$ii(basename((string)$Oi->getFileName(),'.php'))."\n";}foreach($Cc
as$s=>$B)echo"<li><b>".h($s)."</b>: ".h($B).$ii(basename((string)$ia->driverFiles[$s],'.php'))."\n";if($oc){$ch=official_design_checksums();foreach($oc
as$m=>$nc){list($B,$hb)=$nc;$bh=$ch["$B/$m"];echo"<li><b>".h($m)."</b>".h($B?": $B":"").($bh&&$bh!==$hb?$Al("https://www.adminer.org/?version=".VERSION."#extras"):"")."\n";}}echo"</ul>\n";adminer()->pluginsLinks();echo"</div>\n";}}page_footer("db");exit;}adminer()->afterConnect();class
TmpFile{private$handler;var$size=0;function
__construct(){$this->handler=tmpfile();}function
write($Db){$this->size+=strlen($Db);fwrite($this->handler,$Db);}function
send(){fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}if($_GET["select"]!=""&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$l=fields($a);header("Content-Type: application/octet-stream");$Rl=array_merge((array)$_GET["where"],(array)$_GET["val"]);header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$Rl)).".".friendly_url($_GET["field"]));$N=array(idf_escape($_GET["field"]));$I=driver()->select($a,$N,array(where($_GET,$l)),$N);$K=($I?$I->fetch_row():array());echo
driver()->value($K[0],$l[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$l=fields($a);if(!$l)$j=adminer()->error();$S=table_status1($a);$B=adminer()->tableName($S);$j=$j?:h($S["Error"]);page_header(($l&&is_view($S)?$S['Engine']=='materialized view'?'Materialized view':'View':'Table').": ".($B!=""?$B:h($a)),$j,array(),"",!$l,($l?doc_link(array(JUSH=>driver()->tableHelp($a,is_view($S)))):""));$cj=array();foreach($l
as$w=>$k)$cj+=$k["privileges"];adminer()->selectLinks($S,(isset($cj["insert"])||!support("table")?"":null));$ub=$S["Comment"];if($ub!="")echo"<p class='nowrap'>".'Comment'.": ".adminer()->commentValue('TABLE',$ub)."\n";if($l)adminer()->tableStructurePrint($l,$S);function
tables_links(array$T){echo"<ul>\n";foreach($T
as$K){$z=preg_replace('~ns=[^&]*~',"ns=".url_escape($K["ns"]),ME);echo"<li><a href='".h($z."table=".url_escape($K["table"]))."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>";}echo"</ul>\n";}$Re=driver()->inheritsFrom($a);if($Re){echo"<h3>".'Inherits from'."</h3>\n";tables_links($Re);}if(support("indexes")&&driver()->supportsIndex($S)){echo"<div>\n","<h3 id='indexes'>".'Indexes'."</h3>\n";$v=indexes($a);if($v)adminer()->tableIndexesPrint($v,$S);if(driver()->supportsAlterIndex($S))echo'<p class="links hover"><a href="'.h(ME).'indexes='.url_escape($a).'">'.'Alter indexes'."</a>\n";echo"</div>\n";}if(!is_view($S)&&driver()->supportsAlterTable($S)){if(fk_support($S)){echo"<div>\n","<h3 id='foreign-keys'>".'Foreign keys'."</h3>\n";$Jd=foreign_keys($a);if($Jd){echo"<table>\n","<thead><tr><th>".'Source'."<th>".'Target'."<th>".'ON DELETE'."<th>".'ON UPDATE'."<td class='hover'><tbody>\n";foreach($Jd
as$B=>$n){echo"<tr title='".h($B)."'>","<th><i>".implode("</i>, <i>",array_map('Adminer\h',$n["source"]))."</i>";$z=($n["db"]!=""?preg_replace('~db=[^&]*~',"db=".url_escape($n["db"]),ME):($n["ns"]!=""?preg_replace('~ns=[^&]*~',"ns=".url_escape($n["ns"]),ME):ME));echo"<td><a href='".h($z."table=".url_escape($n["table"]))."'>".($n["db"]!=""&&$n["db"]!=DB?"<b>".h($n["db"])."</b>.":"").($n["ns"]!=""&&$n["ns"]!=$_GET["ns"]?"<b>".h($n["ns"])."</b>.":"").h($n["table"])."</a>","(<i>".implode("</i>, <i>",array_map('Adminer\h',$n["target"]))."</i>)","<td>".h($n["on_delete"]),"<td>".h($n["on_update"]),'<td class="hover"><a href="'.h(ME.'foreign='.url_escape($a).'&name='.url_escape($B)).'">'.'Alter'.'</a>',"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'foreign='.url_escape($a).'">'.'Create foreign key'."</a>\n","</div>\n";}if(support("check")){echo"<div>\n","<h3 id='checks'>".'Checks'."</h3>\n";$eb=driver()->checkConstraints($a);if($eb){echo"<table>\n";foreach($eb
as$w=>$X)echo"<tr title='".h($w)."'>","<td><code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($X)),80,"</code>"),"<td class='hover'><a href='".h(ME.'check='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>","\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'check='.url_escape($a).'">'.'Create check'."</a>\n","</div>\n";}}if(support(is_view($S)?"view_trigger":"trigger")&&driver()->supportsAlterTable($S)){echo"<div>\n","<h3 id='triggers'>".'Triggers'."</h3>\n";$kl=triggers($a);if($kl){echo"<table>\n";foreach($kl
as$w=>$X){echo"<tr valign='top'><td>".h($X[0])."<td>".h($X[1])."<th>".h($w)."<td class='hover'><a href='".h(ME.'trigger='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>";$ej=$X[2];if($ej){$gj=preg_replace('~ns=[^&]*~',"ns=".url_escape($ej["ns"]),ME).'function='.url_escape($ej["function"]).'&name='.url_escape($ej["name"]);echo", <a href='".h($gj)."' title='".h($ej["name"])."'>".'Alter function'."</a>";}echo"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'trigger='.url_escape($a).'">'.'Create trigger'."</a>\n","</div>\n";}$Lj=driver()->shadowTables($a);if($Lj){echo"<h3 id='shadow-tables'>".'Shadow tables'."</h3>\n";tables_links($Lj);}$Qe=driver()->inheritedTables($a);if($Qe){echo"<h3 id='partitions'>".'Inherited by'."</h3>\n";$Oh=driver()->partitionsInfo($a);if($Oh)echo"<p><code class='jush-".JUSH."'>BY ".h("$Oh[partition_by]($Oh[partition])")."</code>\n";tables_links($Qe);}}elseif(isset($_GET["schema"])){page_header('Database schema',"",array(),h(DB.($_GET["ns"]?".$_GET[ns]":"")));function
schema_column($R,array$Ni,array&$d){if(!isset($d[$R])){$d[$R]=0;foreach((array)idx($Ni,$R)as$B=>$Pi){if($B!=$R)$d[$R]=max($d[$R],schema_column($B,$Ni,$d)+1);}}return$d[$R];}function
type_class($U){foreach(array('char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',)as$w=>$X){if(preg_match("~$w|$X~",$U))return" class='$w'";}}$zk=array();$Ak=array();$_k=array();$ud=array();$ca=($_GET["schema"]?:$_COOKIE["adminer_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$ca,$Tf,PREG_SET_ORDER);foreach($Tf
as$r=>$A){$zk[$A[1]]=array((float)$A[2],(float)$A[3]);$Ak[]="\n\t'".js_escape($A[1])."': [ $A[2], $A[3] ]";}$M=array();$Ni=array();$Jd=array();$qa=driver()->allFields();$se=array();$Bk=array();foreach(table_status('',true)as$R=>$S){if(!is_view($S)){if(adminer()->tableName($S)!=""&&!$S["dependent"])$Bk[$R]=$S;else$se[$R]=true;}}foreach($Bk
as$R=>$S){$G=0;$M[$R]["fields"]=array();foreach($qa[$R]as$k){$G+=1.25;$ud[$R][$k["field"]]=$G;$M[$R]["fields"][$k["field"]]=$k;}foreach(adminer()->foreignKeys($R)as$X){if($X["db"]==""&&$X["ns"]==""&&!$se[$X["table"]]){$Jd[$R][]=$X;$Ni[$X["table"]][$R]=array();}}}$d=array();$Yd=array();$gm=array();$de=array();foreach(array_keys($M)as$B)schema_column($B,$Ni,$d);arsort($d);foreach($d
as$B=>$c){$ug=null;foreach((array)idx($Jd,$B)as$X){if($X["table"]!=$B&&$M[$X["table"]])$ug=($ug===null?$d[$X["table"]]:min($ug,$d[$X["table"]]));}$d[$B]=max($c,(int)$ug-1);}foreach($M
as$B=>$R){$c=$d[$B];$Yd[$c][]=$B;$Qk=.75*strlen($B);foreach($R["fields"]as$k)$Qk=max($Qk,.65*strlen($k["field"]));$gm[$c]=max(idx($gm,$c,0),ceil($Qk)+1);}foreach($Jd
as$B=>$Ql){foreach($Ql
as$X){$ce=$d[$B]+(idx($d,$X["table"],$d[$B])>$d[$B]?1:0);$de[$ce]=idx($de,$ce,0)+1;}}ksort($Yd);$qe=0;$fm=0;$sb=0;$ui=null;$xk=array();$Dk=array();foreach($Yd
as$c=>$T){if($ui!==null){$sb=round($sb+$gm[$ui]+1.7+idx($de,$c,0)*.1,1);$D=array();foreach($T
as$B){$ok=0;$Jb=0;$Kg=array_keys((array)idx($Ni,$B));foreach((array)idx($Jd,$B)as$X)$Kg[]=$X["table"];foreach($Kg
as$Eg){if($M[$Eg]&&$d[$Eg]<$c){$ok+=$M[$Eg]["pos"][0];$Jb++;}}$D[$B]=($Jb?$ok/$Jb:$qe);}asort($D);$T=array_keys($D);}$bl=0;foreach($T
as$B){$G=1.25*count($M[$B]["fields"]);$M[$B]["pos"]=($zk[$B]?:array($bl,$sb));$xk[$B]=$M[$B]["pos"][1];$Dk[$B]=$gm[$c];$bl+=2.5+$G;$qe=max($qe,$M[$B]["pos"][0]+2.5+$G);$fm=max($fm,round($M[$B]["pos"][1]+$gm[$c],1));if(!$zk[$B])$_k[]="\n\t'".js_escape($B)."': [ ".$M[$B]["pos"][0].", ".$M[$B]["pos"][1]." ]";}$ui=$c;}$Cf=array();$La=array();foreach($Jd
as$B=>$Ql){foreach($Ql
as$X){$Jk=idx($xk,$X["table"],$xk[$B]);$Vj=$xk[$B]+$Dk[$B];$bj=($Jk-1>$Vj);$Af=($bj?$Vj+1:min($xk[$B],$Jk)-1);$Ka=idx($La,(string)$Af,0);$La[(string)$Af]=$Ka+1;$Af=round($bj?min($Af+$Ka*.1,$Jk-1):$Af-$Ka*.1,1);while($Cf[(string)$Af])$Af-=.0001;$M[$B]["references"][$X["table"]][(string)$Af]=array($X["source"],$X["target"]);$Ni[$X["table"]][$B][(string)$Af]=$X["target"];$Cf[(string)$Af]=true;}}echo'<div id="schema" style="height: ',$qe,'em; width: ',$fm,'em;">
<script',nonce(),'>
const tablePos = {',implode(",",$Ak)."\n",'};
const tablePosDefault = {',implode(",",$_k)."\n",'};
const em = qs(\'#schema\').offsetHeight / ',$qe,';
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, \'',js_escape(DB),'\');
</script>
';foreach($M
as$B=>$R){echo"<div class='table'".on('mousedown','schemaMousedown')." style='top: ".$R["pos"][0]."em; left: ".$R["pos"][1]."em; width: ".$Dk[$B]."em;'>",'<a href="'.h(ME).'table='.url_escape($B).'"><b>'.h($B)."</b></a>";foreach($R["fields"]as$k){$X='<span'.type_class($k["type"]).' title="'.h($k["type"].($k["length"]?"($k[length])":"").($k["null"]?" NULL":'')).'">'.h($k["field"]).'</span>';echo"<br>".($k["primary"]?"<i>$X</i>":$X);}foreach((array)$R["references"]as$Kk=>$Pi){foreach($Pi
as$Af=>$Ki){$Bf=$Af-$R["pos"][1];$lk=($Bf>0?"left: 100%; width: calc($Bf"."em - 100%)":"left: $Bf"."em");$fm=($Bf>0?"100%":(-$Bf)."em");$r=0;foreach($Ki[0]as$Uj)echo"\n<div class='references' title='".h($Kk)."' id='refs$Af-".($r++)."' style='$lk"."; top: ".$ud[$B][$Uj]."em; padding-top: .5em;'>"."<div style='border-top: 1px solid gray; width: $fm;'></div></div>";}}foreach((array)$Ni[$B]as$Kk=>$Pi){foreach($Pi
as$Af=>$Lk){$Bf=$Af-$R["pos"][1];$r=0;foreach($Lk
as$Ik)echo"\n<div class='references arrow' title='".h($Kk)."' id='refd$Af-".($r++)."' style='left: $Bf"."em; top: ".$ud[$B][$Ik]."em;'>"."<div style='height: .5em; border-bottom: 1px solid gray; width: ".(-$Bf)."em;'></div>"."</div>";}}echo"\n</div>\n";}foreach($M
as$B=>$R){foreach((array)$R["references"]as$Kk=>$Pi){if($M[$Kk]){foreach($Pi
as$Af=>$Ki){$vg=$qe;$bg=-10;foreach($Ki[0]as$w=>$Uj){$li=$R["pos"][0]+$ud[$B][$Uj];$mi=$M[$Kk]["pos"][0]+$ud[$Kk][$Ki[1][$w]];$vg=min($vg,$li,$mi);$bg=max($bg,$li,$mi);}echo"<div class='references' id='refl$Af' style='left: $Af"."em; top: $vg"."em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: ".($bg-$vg)."em;'></div></div>\n";}}}}echo'</div>
<p class="links"><a href="',h(ME."schema=".url_escape($ca)),'" id="schema-link">Permanent link</a>
';}elseif(isset($_GET["dump"])){$a=$_GET["dump"];if($_POST&&!$j){$i=array("auto_increment"=>'');foreach(array("type","routine","event","trigger")as$qk){if(support($qk))$i[$qk."s"]='';}save_settings(array_intersect_key($_POST+$i,array_flip(array("output","format","db_style","schema_style","table_style","data_style"))+$i),"adminer_export");$pa=(DB==""||$_GET["ns"]==="");$T=array_flip((array)$_POST["tables"])+array_flip((array)$_POST["data"]);$ld=dump_headers((count($T)==1?key($T):DB),($pa||count($T)>1));$if=preg_match('~sql~',$_POST["format"]);if($if){echo"-- Adminer ".VERSION." ".get_driver(DRIVER)." ".str_replace("\n"," ",connection()->server_info)." dump\n\n";if(JUSH=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";connection()->query("SET time_zone = '+00:00'");connection()->query("SET sql_mode = ''");}}$lk=$_POST["db_style"];$g=array(DB);if(DB==""){$g=$_POST["databases"];if(is_string($g))$g=explode("\n",rtrim(str_replace("\r","",$g),"\n"));}foreach((array)$g
as$h){adminer()->dumpDatabase($h);if(connection()->select_db($h)){if($if&&$lk)echo
use_sql($h,$lk).";\n\n";foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?array(""):adminer()->schemas()))as$M){if($M!=""){if(DB==""&&information_schema(DB,$M))continue;set_schema($M);}if($if&&$_POST["schema_style"]&&function_exists('Adminer\use_schema_sql'))echo
use_schema_sql($_GET["ns"],$_POST["schema_style"]).";\n\n";$hk=($_POST["table_style"]||$_POST["data_style"]?table_status('',true):array());$kd=array();$Wb=array();foreach($hk
as$B=>$S){if($pa||in_array($B,(array)$_POST["tables"]))$kd[$B]=$S;if($pa||in_array($B,(array)$_POST["data"]))$Wb[$B]=$S;}if($if){if($_POST["table_style"]=="DROP+CREATE"&&function_exists('Adminer\drop_sql'))echo
drop_sql($kd);if($_POST["data_style"]=="TRUNCATE+INSERT"&&function_exists('Adminer\truncate_all_sql')){$ll=array();foreach($Wb
as$B=>$S){if(!is_view($S)&&!($_POST["table_style"]=="DROP+CREATE"&&isset($kd[$B])))$ll[]=$B;}echo
truncate_all_sql($ll);}$Fh="";if($_POST["types"]){foreach(types()as$s=>$U){$hc=type_definition($s);$Xg=($hc["kind"]=='d'?"DOMAIN":"TYPE");if($hc["definition"])$Fh
.=($lk!='DROP+CREATE'?"DROP $Xg IF EXISTS ".table($U).";;\n":"")."CREATE $Xg ".table($U)." $hc[definition];\n\n";else$Fh
.="-- Could not export type $U\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$B=$K["ROUTINE_NAME"];$ej=$K["ROUTINE_TYPE"];$Kb=create_routine($ej,array("name"=>$B)+routine($K["SPECIFIC_NAME"],$ej));set_utf8mb4($Kb);$Fh
.=($lk!='DROP+CREATE'?"DROP $ej IF EXISTS ".table($B).";;\n":"")."$Kb;\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$Kb=remove_definer(get_val("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($Kb);$Fh
.=($lk!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$Kb;;\n\n";}}echo($Fh&&JUSH=='sql'?"DELIMITER ;;\n\n$Fh"."DELIMITER ;\n\n":$Fh);}if($_POST["table_style"]||$_POST["data_style"]){$Wl=array();foreach($hk
as$B=>$S){$R=array_key_exists($B,$kd);$Ub=array_key_exists($B,$Wb);if($R||$Ub){$Yk=null;if($ld=="tar"){$Yk=new
TmpFile;ob_start(array($Yk,'write'),1e5);}adminer()->dumpTable($B,($R?$_POST["table_style"]:""),(is_view($S)?2:0));if(is_view($S))$Wl[]=$B;elseif($Ub){$l=fields($B);$N=array("*");$Gb=convert_fields($l,$l);if($Gb)$N[]=substr($Gb,2);adminer()->dumpData($B,$_POST["data_style"],"",$N);}if($if&&$_POST["triggers"]&&$R&&($kl=trigger_sql($B)))echo"\nDELIMITER ;;\n$kl\nDELIMITER ;\n";if($ld=="tar"){ob_end_flush();tar_file((DB!=""?"":"$h/")."$B.csv",$Yk);}elseif($if)echo"\n";}}if($if&&$_POST["table_style"]&&function_exists('Adminer\foreign_keys_sql')){foreach($kd
as$B=>$S){if(!is_view($S))echo
foreign_keys_sql($B);}}if($if){foreach($Wl
as$Vl)adminer()->dumpTable($Vl,$_POST["table_style"],1);}if($ld=="tar")echo
pack("x1024");}}}}adminer()->dumpFooter();exit;}page_header('Export',$j,($_GET["export"]!=""?array("table"=>$_GET["export"]):array()),h(DB));echo'
<form action="" method="post">
<table class="layout">
';$ac=array('','USE','DROP+CREATE','CREATE');$oj=(JUSH=="mssql"?array('','DROP+CREATE','CREATE'):$ac);$Ck=array('','DROP+CREATE','CREATE');$Vb=array('','TRUNCATE+INSERT','INSERT');if(JUSH=="sql")$Vb[]='INSERT+UPDATE';$K=get_settings("adminer_export");if(!$K)$K=array("output"=>"text","format"=>"sql","db_style"=>(DB!=""?"":"CREATE"),"schema_style"=>"","table_style"=>"DROP+CREATE","data_style"=>"INSERT");echo"<tr><th>".'Output'."<td>".html_radios("output",adminer()->dumpOutput(),$K["output"])."\n","<tr><th>".'Format'."<td>".html_radios("format",adminer()->dumpFormat(),$K["format"])."\n",(JUSH=="sqlite"?"":"<tr><th>".'Database'."<td>".html_select('db_style',$ac,$K["db_style"]).(support("type")?checkbox("types",1,$K["types"],'User types'):"").(support("routine")?checkbox("routines",1,$K["routines"],'Routines'):"").(support("event")?checkbox("events",1,$K["events"],'Events'):"")),(function_exists('Adminer\use_schema_sql')?"<tr><th>".'Schema'."<td>".html_select('schema_style',$oj,$K["schema_style"]):""),"<tr><th>".'Tables'."<td>".html_select('table_style',$Ck,$K["table_style"]).checkbox("auto_increment",1,$K["auto_increment"],'Auto Increment').(support("trigger")?checkbox("triggers",1,$K["triggers"],'Triggers'):""),"<tr><th>".'Data'."<td>".html_select('data_style',$Vb,$K["data_style"]),'</table>
';adminer()->dumpPrint();echo'<p><input type=\'submit\' value=\'Export\'>
',input_token(),'
<table',on('click','dumpClick'),'>
';$ti=array();if($_GET["ns"]===""&&support("scheme")){echo"<thead><tr><th style='text-align: left;'>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly' title='".'All'."'".on('click','formCheck','^schemas\[').">".'Schema'."</label>","<tbody>\n";foreach(adminer()->schemas()as$M){if(!information_schema(DB,$M))echo"<tr><td>".checkbox("schemas[]",$M,true,$M,"","block")."\n";}}elseif(DB!=""){$fb=($a!=""?"":" checked");echo"<thead><tr>","<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$fb class='jsonly' title='".'All'."'".on('click','formCheck','^tables\[').">".'Table'."</label>","<th style='text-align: right;'><label class='block'>".'Data'."<input type='checkbox' id='check-data'$fb class='jsonly' title='".'All'."'".on('click','formCheck','^data\[')."></label>","<tbody>\n";$Wl="";$Fk=tables_list();foreach($Fk
as$B=>$U){$si=preg_replace('~_.*~','',$B);$fb=($a==""||$a==(substr($a,-1)=="%"?"$si%":$B));$xi="<tr><td>".checkbox("tables[]",$B,$fb,$B,"","block");if($U!==null&&!preg_match('~table~i',$U))$Wl
.="$xi\n";else
echo"$xi<td align='right'><label class='block'><span id='Rows-".h($B)."'></span>".checkbox("data[]",$B,$fb)."</label>\n";$ti[$si]++;}echo$Wl;if($Fk)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$g=adminer()->databases();echo"<thead><tr><th style='text-align: left;'>","<label class='block'>".($g?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly' title='".'All'."'".on('click','formCheck','^databases\[').">":"").'Database'."</label>","<tbody>\n";if($g){foreach($g
as$h){if(!information_schema($h)){$si=preg_replace('~_.*~','',$h);echo"<tr><td>".checkbox("databases[]",$h,$a==""||$a=="$si%",$h,"","block")."\n";$ti[$si]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo'</table>
</form>
';$Cd=true;foreach($ti
as$w=>$X){if($w!=""&&$X>1){echo($Cd?"<p>":" ")."<a href='".h(ME)."dump=".url_escape("$w%")."'>".h($w)."</a>";$Cd=false;}}}elseif(isset($_GET["privileges"])){page_header('Privileges');echo'<p class="links"><a href="'.h(ME).'user=">'.'Create user'."</a>";$I=connection()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$Wd=$I;if(!$I)$I=connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''><p>\n";hidden_fields_get();echo
input_hidden("db",DB),($Wd?"":input_hidden("grant")),"<table class='odds'>\n","<thead><tr><th>".'Username'."<th>".'Server'."<td class='hover'><tbody>\n";while($K=$I->fetch_assoc())echo'<tr><td>'.h($K["User"]),"<td>".h($K["Host"]),'<td class="hover"><a href="'.h(ME.'user='.url_escape($K["User"]).'&host='.url_escape($K["Host"])).'">'.'Edit'."</a>\n";if(!$Wd||DB!="")echo"<tr><td><input name='user' autocapitalize='off'>","<td><input name='host' value='localhost' autocapitalize='off'>","<td class='hover'><input type='submit' value='".'Edit'."'>\n";echo"</table>\n","</form>\n";}elseif(isset($_GET["sql"])){if(!$j&&$_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers("sql");if($_POST["format"]=="sql")echo"$_POST[query]\n";else{adminer()->dumpTable("","");adminer()->dumpData("","table",$_POST["query"]);adminer()->dumpFooter();}exit;}if(!$j&&$_POST["val"]){$la=0;$mk=true;$Ya=array();$lj=0;foreach($_POST["val"]as$L)$lj+=count($L);$Na=$lj>1&&driver()->begin();foreach($_POST["val"]as$vk=>$L){$R=bracket_escape($vk,true);$l=fields($R);$wk=indexes($R);foreach($L
as$t=>$K){parse_str(bracket_escape($t,true),$Z);$tl=array();foreach($Z["where"]as$w=>$X)$tl[bracket_escape($w,true)]=$X;if(!$l||$Z["null"]||array_diff_key($tl,$l)||!unique_array($tl,$wk)){$mk=false;break
2;}$P=array();$N=array();foreach($K
as$qf=>$X){$w=bracket_escape($qf,true);$k=idx($l,$w);if(!$k){$mk=false;break
3;}$P[idf_escape($w)]=(preg_match('~char|text~',$k["type"])||$X!=""?adminer()->processInput($k,$X):"NULL");$N[$qf]=$w;}$Gi=where($Z,$l);if(!driver()->update($R,$P," WHERE $Gi",0," ")){$mk=false;break
2;}$la+=connection()->affected_rows;$d=array();foreach($N
as$w)$d[]=idf_escape($w);$Bl=driver()->select($R,$d,array($Gi),$d);$Og=($Bl?$Bl->fetch_row():array());$mf=0;foreach($N
as$qf=>$w){$k=$l[$w];$kk=array('type'=>(preg_match('~binary~',$k["type"])?'blob':$k["type"]));$Ya["val[$vk][$t][$qf]"]=select_value(idx($Og,$mf++),"",$kk,null);}}}if($Na&&$mk)$mk=driver()->commit();queries_redirect(null,lang_format(array('%d item has been affected.','%d items have been affected.'),$la),$mk);if($Na&&!$mk)driver()->rollback();page_headers();page_messages($j);foreach($Ya
as$B=>$X)echo"<div data-name='".h($B)."' hidden>$X</div>\n";exit;}restart_session();$we=&get_session("queries");$ve=&$we[DB];if(!$j&&$_POST["clear"]){$ve=array();redirect(remove_from_uri("history"));}stop_session();$ja=get_settings("adminer_import");if($_POST&&$ja)save_settings($ja,"adminer_import");page_header((isset($_GET["import"])?'Import':'SQL command'),$j);$Kf=driver()->lineComment();if(!$j&&$_POST&&!(isset($_GET["import"])&&adminer()->importProcess())){$jc=driver()->delimiter;$o=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$Yj=adminer()->importServerPath();$o=@fopen((file_exists($Yj)?$Yj:"compress.zlib://$Yj.gz"),"rb");$H=($o?fread($o,1e6):false);}else$H=get_file("sql_file",true,$jc);if(is_string($H)){if(($jg=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($jg,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$Di=$H.(preg_match("~$jc\\s*\$~",$H)?"":$jc);if(!$ve||first(end($ve))!=$Di){restart_session();$ve[]=array($Di,time());set_session("queries",$we);stop_session();}}$Wj="(?:\\s|\xEF\xBB\xBF|/\\*[\s\S]*?\\*/|(?:$Kf)[^\n]*\n?|--\r?\n)";$dh=0;$Pc=true;$Ib=false;$f=connect();if($f&&DB!=""){$f->select_db(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$f);}$tb=0;$Xc=array();$Mh='[\'"'.(JUSH=="sql"?'`':(JUSH=="sqlite"?'`[':(JUSH=="mssql"?'[':''))).']|/\*|'.$Kf.'|$'.(JUSH=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$cl=microtime(true);while($H!=""){if(!$dh&&preg_match("~^$Wj*+DELIMITER\\s+(\\S+)~i",$H,$A)){$jc=preg_quote($A[1]);$H=substr($H,strlen($A[0]));}elseif(!$dh&&JUSH=='pgsql'&&preg_match("~^($Wj*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$A)){$jc="\n\\\\\\.\r?\n";$Ib=true;$dh=strlen($A[0]);}else{preg_match("($jc\\s*|$Mh)",$H,$A,PREG_OFFSET_CAPTURE,$dh);list($Ld,$G)=$A[0];if(!$Ld&&$o&&!feof($o))$H
.=fread($o,1e5);else{if(!$Ld&&rtrim($H)=="")break;$dh=$G+strlen($Ld);if($Ld&&!preg_match("(^$jc)",$Ld)){$Va=driver()->hasCStyleEscapes()||(JUSH=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$bi=($Ld=='/*'?'\*/':($Ld=='['?']':(preg_match("~^(?:$Kf)~",$Ld)?"\n":preg_quote($Ld).($Va?'|\\\\.':''))));while(preg_match("($bi|\$)s",$H,$A,PREG_OFFSET_CAPTURE,$dh)){$mj=$A[0][0];if(!$mj&&$o&&!feof($o))$H
.=fread($o,1e5);else{$dh=$A[0][1]+strlen($mj);if(!$mj||$mj[0]!="\\")break;}}}else{$Di=substr($H,0,$G+($Ib?3:0));$H=substr($H,$dh);$dh=0;if($Ib){$jc=driver()->delimiter;$Ib=false;}$mb="<code class='jush-".JUSH."'>".adminer()->sqlCommandQuery($Di)."</code>";if(preg_match("~^$Wj*+\$~",$Di)&&!preg_match('~/\*M?!~',$Di)){echo($_POST["only_errors"]?"":"<pre>$mb</pre>\n");continue;}$Pc=false;$tb++;$xi="<pre id='sql-$tb'>$mb</pre>\n";if(JUSH=="sqlite"&&preg_match("~^$Wj*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$Di,$A)!==0){echo$xi,"<p class='error'>".sprintf('%s queries are not supported.',preg_match('~ATTACH~i',$A[1])?'ATTACH':'VACUUM INTO')."\n";$Xc[]=" <a href='#sql-$tb'>$tb</a>";if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$xi;ob_flush();flush();}$dk=microtime(true);if(connection()->multi_query($Di)&&$f&&preg_match("~^$Wj*+USE\\b~i",$Di))$f->query($Di);do{$I=connection()->store_result();if(connection()->error){echo($_POST["only_errors"]?$xi:""),"<p class='error'>".'Error in query'.(connection()->errno?" (".connection()->errno.")":"").": ".adminer()->error()."\n";$Xc[]=" <a href='#sql-$tb'>$tb</a>";if($_POST["error_stops"])break
2;}else{$z=ME."sql=".url_escape(trim($Di));$Rk=" <span class='time'>(".format_time($dk).")</span>".(strlen($z)<1900?" <a href='".h($z)."'>".'Edit'."</a>":"");$la=connection()->affected_rows;$Zl=($_POST["only_errors"]?"":driver()->warnings());$am="warnings-$tb";if($Zl)$Rk
.=", <a href='#$am' class='toggle'>".'Warnings'."</a>";$id="";$jd="explain-$tb";if(is_object($I)){$y=$_POST["limit"];$Vg=$y;$Ic=!$_POST["only_errors"];if($Ic)echo"<form action='' method='post'>\n";$yh=print_select_result($I,$f,array(),$Vg,$Ic);if(!$_POST["only_errors"]){$Vg=max($I->num_rows,$Vg);echo"<p class='sql-footer'>".($Vg?($y&&$Vg>$y?sprintf('%d / ',$y):"").lang_format(array('%d row','%d rows'),$Vg):""),$Rk;if($f&&preg_match("~^($Wj|\\()*+SELECT\\b~i",$Di)&&($id=adminer()->explain($f,$Di,$yh))!="")echo", <a href='#$jd' class='toggle'>Explain</a>";if($Ic)echo", <input type='submit' name='save' value='".'Save'."' class='jsonly' disabled"." title='".'Ctrl+click on a value to modify it.'."'".on('click','sqlSave','Saving…').">";$s="export-$tb";echo", <a href='#$s' class='toggle'>".'Export'."</a><span id='$s' class='hidden'>: ".html_select("output",adminer()->dumpOutput(),$ja["output"])." ".html_select("format",adminer()->dumpFormat(),$ja["format"]).input_hidden("query",$Di)."<input type='submit' name='export' value='".'Export'."'".($y?"":on('click','sqlExport')).">".input_token()."</span>\n"."</form>\n";}}else{if(preg_match("~^$Wj*+(CREATE|DROP|ALTER)$Wj++(DATABASE|SCHEMA)\\b~i",$Di)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"])echo"<p class='message' title='".h(connection()->info)."'>".lang_format(array('Query executed OK, %d row affected.','Query executed OK, %d rows affected.'),$la)."$Rk\n";}echo($Zl?"<div id='$am' class='hidden'>\n$Zl</div>\n":""),($id!=""?"<div id='$jd' class='hidden explain'>\n$id</div>\n":"");}$dk=microtime(true);}while(connection()->next_result());}}}}}if($Pc)echo"<p class='message'>".'No commands to execute.'."\n";else{$Je=connection()->inTransaction();driver()->rollback();if($Je)echo"<pre><code class='jush-".JUSH."'>ROLLBACK".(JUSH=="mssql"?" TRANSACTION":"")." -- Adminer</code></pre>\n";if($_POST["only_errors"])echo"<p class='message'>".lang_format(array('%d query executed OK.','%d queries executed OK.'),$tb-count($Xc))," <span class='time'>(".format_time($cl).")</span>\n";elseif($Xc&&$tb>1)echo"<p class='error'>".'Error in query'.": ".implode("",$Xc)."\n";}}else
echo"<p class='error'>".upload_error($H)."\n";}echo'
<form action="" method="post" enctype="multipart/form-data" id="form"';$Cl="";if(!isset($_GET["import"]))echo
on('submit','sqlSubmit',remove_from_uri("sql|limit|error_stops|only_errors|history"));else
echo
on_upload_progress($Cl);echo'>
';$fd="<input type='submit' value='".'Execute'."' title='Ctrl+Enter'>";if(!isset($_GET["import"])){$Di=$_GET["sql"];if($_POST)$Di=$_POST["query"];elseif($_GET["history"]=="all")$Di=$ve;elseif($_GET["history"]!="")$Di=idx($ve[$_GET["history"]],0);echo"<p>";textarea("query",$Di,20);echo($_POST?"":script("qs('textarea').focus();")),"<p>";adminer()->sqlPrintAfter();echo"$fd\n",'Limit rows'.": <input type='number' name='limit' class='size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{$ee=(extension_loaded("zlib")?"[.gz]":"");echo"<fieldset><legend>".'File upload'."</legend><div>",($Cl?input_hidden(ini_get("session.upload_progress.name"),$Cl):""),"SQL$ee: ".file_input(" name='sql_file[]' multiple","\n$fd"),($Cl?" <progress class='jsonly hidden' max='1' value='0'></progress>":""),"</div></fieldset>\n";$Ge=adminer()->importServerPath();if($Ge)echo"<fieldset><legend>".'From server'."</legend><div>",sprintf('Webserver file %s',"<code>".h($Ge)."$ee</code>")," <input type='submit' name='webfile' value='".'Run file'."'>","</div></fieldset>\n";adminer()->importPrint();echo"<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),'Stop on error')."\n",checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),'Show only errors')."\n",input_token();if(!isset($_GET["import"])&&$ve){print_fieldset("history",'History',$_GET["history"]!="");for($X=end($ve);$X;$X=prev($ve)){$w=key($ve);list($Di,$Rk,$Lc)=$X;echo'<div><a href="'.h(ME."sql=&history=$w").'" class="hover">'.'Edit'."</a>"." <span class='time' title='".@date('Y-m-d',$Rk)."'>".@date("H:i:s",$Rk)."</span>"." <code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim(preg_replace("~^(?:$Kf).*~m",'',$Di))),80,"</code>").($Lc?" <span class='time'>($Lc)</span>":"")."</div>\n";}echo"<input type='submit' name='clear' value='".'Clear'."'>\n","<a href='".h(ME."sql=&history=all")."'>".'Edit all'."</a>\n","</div></fieldset>\n";}echo'</form>
';}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$l=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$l):""):where($_GET,$l));$_l=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($l
as$B=>$k){if((!$_l&&!isset($k["privileges"]["insert"]))||adminer()->fieldName($k)=="")unset($l[$B]);}if($_POST&&!$j&&!isset($_GET["select"])){$_=relative_uri((string)$_POST["referer"]);if($_POST["insert"])$_=($_l?null:relative_uri());elseif(!preg_match('~^.+&select=.+$~',$_))$_=ME."select=".url_escape($a);$v=indexes($a);$ul=unique_array($_GET["where"],$v);$Gi="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($_,'Item has been deleted.',driver()->delete($a,$Gi,$ul?0:1));else{$P=array();foreach($l
as$B=>$k){$X=process_input($k);if($X!==false&&$X!==null)$P[idf_escape($B)]=$X;}if($_l){if(!$P)redirect($_);queries_redirect($_,'Item has been updated.',driver()->update($a,$P,$Gi,$ul?0:1));if(is_ajax()){page_headers();page_messages($j);exit;}}else{$I=driver()->insert($a,$P);$_f=($I?last_id($I):0);queries_redirect($_,sprintf('Item%s has been inserted.',($_f?" $_f":"")),$I);}}}$K=null;$H="";$Rk="";if($Z){$N=array();$xj=array("*");foreach($l
as$B=>$k){if(isset($k["privileges"]["select"])){$za=($_POST["clone"]&&$k["auto_increment"]?"''":convert_field($k));$c=($za?"$za AS ":"").idf_escape($B);$N[]=$c;if($za)$xj[]=$c;}}$K=array();if(!support("table")){$N=array("*");$xj=$N;}if($N){$dk=microtime(true);$I=driver()->select($a,$N,array($Z),$N,array(),(isset($_GET["select"])?2:1));$H=str_replace("SELECT ".implode(", ",$N),"SELECT ".implode(", ",$xj),driver()->query);$Rk=format_time($dk);if(!$I)$j=adminer()->error();else{$K=$I->fetch_assoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetch_assoc()))$K=null;}}if(!$l&&driver()->primary!=""){if(!$Z){$I=driver()->select($a,array("*"),array(),array("*"));$K=($I?$I->fetch_assoc():false);if(!$K)$K=array(driver()->primary=>"");}if($K){foreach($K
as$w=>$X){if(!$Z)$K[$w]=null;$l[$w]=array("field"=>$w,"null"=>($w!=driver()->primary),"auto_increment"=>($w==driver()->primary));}}}if($_POST["save"]){$ni=array();foreach((array)$_POST["fields"]as$w=>$X)$ni[bracket_escape($w,true)]=$X;$K=$ni+($K?$K:array());}edit_form($a,$l,$K,$_l,$j,$H,$Rk);}elseif(isset($_GET["create"])){function
referencable_primary($zj){$J=array();foreach(table_status('',true)as$yk=>$R){if($yk!=$zj&&!$R["dependent"]&&fk_support($R)){foreach(fields($yk)as$k){if($k["primary"]){if($J[$yk]){unset($J[$yk]);break;}$J[$yk]=$k;}}}}return$J;}$a=$_GET["create"];$Qh=driver()->partitionBy;$Uh=($Qh&&$a!=""?driver()->partitionsInfo($a):array());$Mi=referencable_primary($a);$Jd=array();foreach($Mi
as$yk=>$k)$Jd[str_replace("`","``",$yk)."`".str_replace("`","``",$k["field"])]=$yk;$Ah=array();$S=array();$Tg=false;if($a!=""){$Ah=fields($a);$S=table_status1($a);$Tg=(count($S)<2);}$ta=($a==""||driver()->supportsAlterTable($S));$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!$j)save_settings(array("comments"=>$_POST["comments"],"defaults"=>$_POST["defaults"]));if($_POST&&!process_fields($K["fields"])&&!$j){if($_POST["drop"])queries_redirect(substr(ME,0,-1),'Table has been dropped.',drop_tables(array($a)));else{$l=array();$qa=array();$Gl=false;$Hd=array();$_h=reset($Ah);$na=" FIRST";foreach($K["fields"]as$k){$n=$Jd[$k["type"]];$nl=($n!==null?$Mi[$n]:$k);if($k["field"]!=""){if(!$k["generated"])$k["default"]=null;$Bi=process_field($k,$nl);$qa[]=array($k["orig"],$Bi,$na);if(!$_h||$Bi!==process_field($_h,$_h)){$l[]=array($k["orig"],$Bi,$na);if($k["orig"]!=""||$na)$Gl=true;}if($n!==null)$Hd[idf_escape($k["field"])]=($a!=""&&JUSH!="sqlite"?"ADD":" ").format_foreign_key(array('table'=>$Jd[$k["type"]],'source'=>array($k["field"]),'target'=>array($nl["field"]),'on_delete'=>$k["on_delete"],),object_name("FOREIGN",trim($K["name"]),array($k["field"])));$na=" AFTER ".idf_escape($k["field"]);}elseif($k["orig"]!=""){$Gl=true;$l[]=array($k["orig"]);}if($k["orig"]!=""){$_h=next($Ah);if(!$_h)$na="";}}$Sh=array();if(in_array($K["partition_by"],$Qh)){foreach($K
as$w=>$X){if(preg_match('~^partition~',$w))$Sh[$w]=$X;}foreach($Sh["partition_names"]as$w=>$B){if($B==""){unset($Sh["partition_names"][$w]);unset($Sh["partition_values"][$w]);}}$Sh["partition_names"]=array_values($Sh["partition_names"]);$Sh["partition_values"]=array_values($Sh["partition_values"]);if($Sh==$Uh)$Sh=array();}elseif(preg_match("~partitioned~",$S["Create_options"]))$Sh=null;$lg='Table has been altered.';if($a==""){cookie("adminer_engine",$K["Engine"]);$lg='Table has been created.';}$B=trim($K["name"]);$_=ME.(support("table")?"table=":"select=").url_escape($B);$I=alter_table($a,$B,(JUSH=="sqlite"&&($Gl||$Hd)?$qa:$l),$Hd,($K["Comment"]!=$S["Comment"]?$K["Comment"]:null),($K["Engine"]&&$K["Engine"]!=$S["Engine"]?$K["Engine"]:""),($K["Collation"]&&$K["Collation"]!=$S["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$Sh);if($I&&!Queries::$queries&&$a!=""&&!$l&&!$Hd)redirect($_);queries_redirect($_,$lg,$I);}}$ek=($a!=""?"alter":"create");page_header(($a!=""?'Alter table':'Create table'),$j,array("table"=>$a),h($a),$Tg,doc_link(array('sql'=>"$ek-table.html",'mariadb'=>($a!=""?"$ek-table":""),)));if(!$_POST){$ql=driver()->types();$K=array("Engine"=>$_COOKIE["adminer_engine"],"fields"=>array(array("field"=>"","type"=>(isset($ql["int"])?"int":(isset($ql["integer"])?"integer":"")),"on_update"=>"")),"partition_names"=>array(""),);if($a!=""){$K=$S;$K["name"]=$a;$K["fields"]=array();if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($Ah
as$k){if($k["generated"])$k["default"]=ltrim($k["default"]);$k["generated"]=$k["generated"]?:(isset($k["default"])?"DEFAULT":"");$K["fields"][]=$k;}if($Qh){$K+=$Uh;$K["partition_names"][]="";$K["partition_values"][]="";}}}$qb=flat_collations();$Sc=driver()->engines();foreach($Sc
as$Rc){if(!strcasecmp($Rc,$K["Engine"])){$K["Engine"]=$Rc;break;}}$Wf=max_input_vars(12,20);if($Wf){$se=(count($K["fields"])>$Wf?"":" hidden");echo"<p".($se?" id='max-fields' data-columns='$Wf'":"")." class='error$se'>".max_input_vars_error()."\n";}echo'
<form action="" method="post" id="form">
<p>
';if(support("columns")||$a==""){echo'Table name'.": <input name='name'".($a==""&&!$_POST?" autofocus":"")." data-maxlength='64' value='".h($K["name"])."' autocapitalize='off'>\n",(!$ta?h($S["Engine"])."\n":($Sc?html_select("Engine",array(""=>"(".'engine'.")")+$Sc,$K["Engine"],on('change','helpClose').on_help_value())."\n":""));if($qb)echo"<datalist id='collations'>".optionlist($qb)."</datalist>\n",(preg_match("~sqlite|mssql~",JUSH)?"":"<input list='collations' name='Collation' value='".h($K["Collation"])."' placeholder='(".'collation'.")'>\n");echo"<input type='submit' value='".'Save'."'>\n";}if(support("columns")&&$ta){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$qb,"TABLE",$Jd);echo"</table>\n",script("editFields();"),"</div>\n<p>\n",'Auto Increment'.": <input type='number' name='Auto_increment' class='size' value='".h($K["Auto_increment"])."'>\n",checkbox("defaults",1,($_POST?$_POST["defaults"]:get_setting("defaults")),'Default values',on('click','columnShowClick',6),"jsonly");$wb=($_POST?$_POST["comments"]:get_setting("comments"));if(support("comment")){echo
checkbox("comments",1,$wb,'Comment',on('click','editingCommentsClick',true),"jsonly").' ';$b=" name='Comment' data-maxlength='".(min_version(5.5)?2048:60)."'".($wb?"":" class='hidden'");echo
adminer()->commentInput('TABLE',$b,$K["Comment"]);}echo'<p>
<input type=\'submit\' value=\'Save\'>
';}echo'
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';if($Qh&&(JUSH=='sql'||$a=="")){$Rh=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset("partition",'Partition by',$K["partition_by"]);echo"<p>".html_select("partition_by",array_merge(array(""),$Qh),$K["partition_by"],on('change','partitionByChange').on_help_value('.','PARTITION BY $&'))."\n","(<input name='partition' value='".h($K["partition"])."'>)\n",'Partitions'.": <input type='number' name='partitions' class='size".($Rh||!$K["partition_by"]?" hidden":"")."' value='".h($K["partitions"])."'>\n","<table id='partition-table'".($Rh?"":" class='hidden'").">\n","<thead><tr><th>".'Partition name'."<th>".'Values'."<tbody>\n";foreach($K["partition_names"]as$w=>$X)echo'<tr>','<td><input name="partition_names[]" value="'.h($X).'" autocapitalize="off"'.($w==count($K["partition_names"])-1?on('input','partitionNameChange'):'').'>','<td><input name="partition_values[]" value="'.h(idx($K["partition_values"],$w)).'">';echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$Oe=array("PRIMARY","UNIQUE","INDEX");$S=table_status1($a,true);$Me=driver()->indexAlgorithms($S);if(preg_match('~MyISAM|M?aria'.(min_version(5.6,'10.0.5')?'|InnoDB':'').'~i',$S["Engine"]))$Oe[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.(min_version(5.7,'10.2.2')?'|InnoDB':'').'~i',$S["Engine"]))$Oe[]="SPATIAL";if(min_version('',11.7)&&preg_match('~MyISAM|InnoDB~i',$S["Engine"]))$Oe[]="VECTOR";$v=indexes($a);$l=fields($a);$wi=array();if(JUSH=="mongo"){$wi=$v["_id_"];unset($Oe[0]);unset($v["_id_"]);}$K=$_POST;if($K)save_settings(array("index_options"=>$K["options"]));if($_POST&&!$j&&!$_POST["add"]&&!$_POST["drop_col"]){$sa=array();foreach($K["indexes"]as$u){$B=$u["name"];if(in_array($u["type"],$Oe)){$d=array();$Gf=array();$mc=array();$oh=array();$Ne=(support("partial_indexes")?$u["partial"]:"");$Le=(in_array($u["algorithm"],$Me)?$u["algorithm"]:"");$P=array();ksort($u["columns"]);foreach($u["columns"]as$w=>$c){if($c!=""){$x=idx($u["lengths"],$w);$kc=idx($u["descs"],$w);$nh=idx($u["opclasses"],$w);$P[]=($l[$c]?idf_escape($c):$c).($x?"(".(+$x).")":"").($nh!=""?" ".idf_escape($nh):"").($kc?" DESC":"");$d[]=$c;$Gf[]=($x?:null);$mc[]=$kc;$oh[]="$nh";}}$gd=$v[$B];if($gd){ksort($gd["columns"]);ksort($gd["lengths"]);ksort($gd["descs"]);if($u["type"]==$gd["type"]&&array_values($gd["columns"])===$d&&(!$gd["lengths"]||array_values($gd["lengths"])===$Gf)&&array_values($gd["descs"])===$mc&&(!$gd["opclasses"]||array_values($gd["opclasses"])===$oh)&&$gd["partial"]==$Ne&&(!$Me||$gd["algorithm"]==$Le)){unset($v[$B]);continue;}}if($d)$sa[]=array($u["type"],$B,$P,$Le,$Ne);}}foreach($v
as$B=>$gd)$sa[]=array($gd["type"],$B,"DROP");if(!$sa)redirect(ME."table=".url_escape($a));queries_redirect(ME."table=".url_escape($a),'Indexes have been altered.',alter_indexes($a,$sa));}page_header('Indexes',$j,array("table"=>$a),h($a),false,doc_link(array('sql'=>"create-index.html",)));$wd=array_keys($l);if($_POST["add"]){foreach($K["indexes"]as$w=>$u){if($u["columns"][count($u["columns"])]!="")$K["indexes"][$w]["columns"][]="";}$u=end($K["indexes"]);if($u["type"]||array_filter($u["columns"],'strlen'))$K["indexes"][]=array("columns"=>array(1=>""));}if(!$K){foreach($v
as$w=>$u){$v[$w]["name"]=$w;$v[$w]["columns"][]="";}$v[]=array("columns"=>array(1=>""));$K["indexes"]=$v;}$Gf=(JUSH=="sql"||JUSH=="mssql");$oh=driver()->indexOpclasses();$Mj=($_POST?$_POST["options"]:get_setting("index_options"));$Gg=array();foreach($Oe
as$U)$Gg[$U]=str_replace("{table}",$a,adminer()->namePattern($U));echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap odds">
<thead><tr>
<th id="label-type">Index Type
';$Ee=" class='idxopts".($Mj?"":" hidden")."'";if($Me)echo"<th id='label-algorithm'$Ee>".'Algorithm'.doc_link(array('sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'storage-engine-index-types/',));echo'<th><input type="submit" hidden>','Columns'.($Gf?"<span$Ee> (".'length'.")</span>":"");if($Gf||support("descidx"))echo
checkbox("options",1,$Mj,'Options',on('click','indexOptionsShow'),"jsonly")."\n";echo'<th id="label-name">Name
';if(support("partial_indexes"))echo"<th id='label-condition'$Ee>".'Condition';echo'<td><noscript>',icon("plus","add[0]","+",'Add next'),'</noscript>
<tbody>
';if($wi){echo"<tr><td>PRIMARY<td>";foreach($wi["columns"]as$w=>$c)echo
select_input(" disabled",array_combine($wd,$wd),$c),"<label><input disabled type='checkbox'>".'descending'."</label> ";echo"<td><td>\n";}$mf=1;foreach($K["indexes"]as$u){if(!$_POST["drop_col"]||$mf!=key($_POST["drop_col"])){echo"<tr><td>".html_select("indexes[$mf][type]",array(-1=>"")+$Oe,$u["type"],on('change','indexesChangeType',$Gg),"label-type");if($Me)echo"<td$Ee>".html_select("indexes[$mf][algorithm]",array_merge(array(""),$Me),$u['algorithm'],"","label-algorithm");echo"<td>";ksort($u["columns"]);$r=1;foreach($u["columns"]as$w=>$c){echo"<span>".select_input(" name='indexes[$mf][columns][$r]' title='".'Column'."'".on('change','indexesChangeColumn',$Gg),($l&&($c==""||$l[$c])?array_combine($wd,$wd):array()),$c)," <span$Ee>",($Gf?"<input type='number' name='indexes[$mf][lengths][$r]' class='size' value='".h(idx($u["lengths"],$w))."' title='".'Length'."'>":"");if($oh){$nh=idx($u["opclasses"],$w);echo
html_select("indexes[$mf][opclasses][$r]",array(""=>"(".'operator class'.")")+array_combine($oh,$oh)+($nh!=""?array($nh=>$nh):array()),$nh),'';}echo(support("descidx")?checkbox("indexes[$mf][descs][$r]",1,idx($u["descs"],$w),'descending'):""),"<br>","</span></span>";$r++;}echo"<td><input name='indexes[$mf][name]' value='".h($u["name"])."' autocapitalize='off' aria-labelledby='label-name'>\n";if(support("partial_indexes"))echo"<td$Ee><input name='indexes[$mf][partial]' value='".h($u["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>".icon("cross","drop_col[$mf]","x",'Remove',on('click','editingRemoveRow','indexes$1[type]'));}$mf++;}echo'</table>
</div>
<p>
<input type=\'submit\' value=\'Save\'>
',input_token(),'</form>
';}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!$j&&!$_POST["add"]){$B=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),'Database has been dropped.',drop_databases(array(DB)));}elseif($B!==DB){if(DB!=""){$_GET["db"]=$B;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".url_escape($B),'Database has been renamed.',rename_database($B,(string)$K["collation"]));}else{$g=explode("\n",str_replace("\r","",$B));$mk=true;$yf="";foreach($g
as$h){if(count($g)==1||$h!=""){if(!create_database($h,(string)$K["collation"]))$mk=false;$yf=$h;}}restart_session();set_session("dbs",null);queries_redirect(preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($yf),'Database has been created.',$mk);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($B).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),'Database has been altered.');}}$ek=(DB!=""?"alter":"create");page_header(DB!=""?'Alter database':'Create database',$j,array(),h(DB),false,doc_link(array('sql'=>"$ek-database.html",'mariadb'=>(DB!=""?"":"$ek-database"),)));$qb=collations();$B=DB;if($_POST)$B=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,$qb);elseif(JUSH=="sql"){foreach(get_vals("SHOW GRANTS")as$Wd){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$Wd,$A)&&$A[1]){$B=stripcslashes(idf_unescape("`$A[2]`"));break;}}}echo'
<form action="" method="post">
<p>
',($_POST["add"]||strpos($B,"\n")?'<textarea autofocus name="name" rows="10" cols="40">'.h($B).'</textarea><br>':'<input name="name" autofocus value="'.h($B).'" data-maxlength="64" autocapitalize="off">')."\n",($qb?html_select("collation",array(""=>"(".'collation'.")")+$qb,$K["collation"]).doc_link(array('sql'=>"charset-charsets.html",'mariadb'=>"supported-character-sets-and-collations/",)):"")."\n",'<input type=\'submit\' value=\'Save\'>
';if(DB!="")echo"<input type='submit' name='drop' value='".'Drop'."'".confirm(sprintf('Drop %s?',DB)).">\n";elseif(!$_POST["add"]&&$_GET["db"]=="")echo
icon("plus","add[0]","+",'Add next')."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["call"])){$ba=($_GET["name"]?:$_GET["call"]);$jj=(isset($_GET["callf"])?"FUNCTION":"PROCEDURE");$ej=routine($_GET["call"],$jj);page_header('Call'.": ".h($ba),$j,"#routines","",!$ej,(isset($_GET["callf"])?"":doc_link(array('sql'=>"call.html",))));$He=array();$Fh=array();foreach($ej["fields"]as$r=>$k){if(substr($k["inout"],-3)=="OUT"&&JUSH=='sql')$Fh[$r]="@".idf_escape($k["field"])." AS ".idf_escape($k["field"]);if(!$k["inout"]||preg_match('~^(IN|OUTPUT)~',$k["inout"]))$He[]=$r;}if(!$j&&$_POST){$Wa=array();foreach($ej["fields"]as$w=>$k){$X="";if(in_array($w,$He)){$X=process_input($k);if($X===false)$X="''";if(isset($Fh[$w]))connection()->query("SET @".idf_escape($k["field"])." = $X");}if(isset($Fh[$w]))$Wa[]="@".idf_escape($k["field"]);elseif(in_array($w,$He))$Wa[]=$X;}$xa=implode(", ",$Wa);$H=(isset($_GET["callf"])||JUSH!="mssql"?(isset($_GET["callf"])?"SELECT ":"CALL ").(idx($ej["returns"],"type")=="record"?"* FROM ":"").table($ba)."($xa)":"EXEC ".table($ba).($xa!=""?" $xa":""));$dk=microtime(true);$I=connection()->multi_query($H);$la=connection()->affected_rows;echo
adminer()->selectQuery($H,$dk,!$I);if(!$I)echo"<p class='error'>".adminer()->error()."\n";else{$f=connect();if($f)$f->select_db(DB);do{$I=connection()->store_result();if(is_object($I))print_select_result($I,$f);else
echo"<p class='message'>".lang_format(array('Routine has been called, %d row affected.','Routine has been called, %d rows affected.'),$la)." <span class='time'>".@date("H:i:s")."</span>\n";}while(connection()->next_result());if($Fh)print_select_result(connection()->query("SELECT ".implode(", ",$Fh)));}}echo'
<form action="" method="post">
';if($He){echo"<table class='layout'>\n";foreach($He
as$w){$k=$ej["fields"][$w];$B=$k["field"];echo"<tr><th>".adminer()->fieldName($k);$Y=idx($_POST["fields"],$B);if($Y!=""){if($k["type"]=="set")$Y=implode(",",$Y);}input($k,$Y,idx($_POST["function"],$B,""));echo"\n";}echo"</table>\n";}echo'<p>
<input type=\'submit\' value=\'Call\'>
',input_token(),'</form>

',adminer()->commentValue($jj,$ej['comment']);}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$B=$_GET["name"];$K=$_POST;if($_POST&&!$j&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$Ik=array();foreach($K["source"]as$w=>$X)$Ik[$w]=$K["target"][$w];$K["target"]=$Ik;}$Ab=object_name("FOREIGN",$a,$K["source"]);if(JUSH=="sqlite")$I=recreate_table($a,$a,array(),array(),array(" $B"=>($K["drop"]?"":" ".format_foreign_key($K,$Ab))));else{$sa="ALTER TABLE ".table($a);$I=($B==""||queries("$sa DROP ".(JUSH=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($B)));if(!$K["drop"])$I=queries("$sa ADD".format_foreign_key($K,$Ab));}queries_redirect(ME."table=".url_escape($a),($K["drop"]?'Foreign key has been dropped.':($B!=""?'Foreign key has been altered.':'Foreign key has been created.')),$I);if(!$K["drop"])$j='Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.';}$Tg=false;if(!$_POST&&$B!=""){$Jd=foreign_keys($a);$K=idx($Jd,$B,array());$Tg=!$K;}page_header(($B!=""?'Alter foreign key':'Create foreign key'),$j,array("table"=>$a),h($B!=""?$B:$a),$Tg,doc_link(array('sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"foreign-keys/",)));if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=array();else$K["source"][]="";}elseif($B!="")$K["source"][]="";else{$K["table"]=$a;$K["source"]=array("");}echo'
<form action="" method="post">
';$Uj=array_keys(fields($a));if($K["db"]!="")connection()->select_db($K["db"]);if($K["ns"]!=""){$Bh=get_schema();set_schema($K["ns"]);}$Li=array_keys(array_filter(table_status('',true),function(array$S){return!$S["dependent"]&&fk_support($S);}));$Ik=array_keys(fields(in_array($K["table"],$Li)?$K["table"]:reset($Li)));$b=on('change','foreignChange');echo"<p><label>".'Target table'.": ".html_select("table",$Li,$K["table"],$b)."</label>\n";if(JUSH!="sqlite"){$bc=array();foreach(adminer()->databases()as$h){if(!information_schema($h))$bc[]=$h;}echo"<label>".'DB'.": ".html_select("db",$bc,$K["db"]!=""?$K["db"]:$_GET["db"],$b)."</label>";}echo
input_hidden("change-js"),'<noscript><p><input type=\'submit\' name=\'change\' value=\'Change\'></noscript>
<table>
<thead><tr><th id="label-source">Source<th id="label-target">Target<tbody>
';$mf=0;foreach($K["source"]as$w=>$X){echo"<tr>","<td>".html_select("source[".(+$w)."]",array(-1=>"")+$Uj,$X,($mf==count($K["source"])-1?on('change','foreignAddRow'):""),"label-source"),"<td>".html_select("target[".(+$w)."]",$Ik,idx($K["target"],$w),"","label-target");$mf++;}echo'</table>
<p>
<label>ON DELETE: ',html_select("on_delete",array(-1=>"")+explode("|",driver()->onActions),$K["on_delete"]),'</label>
<label>ON UPDATE: ',html_select("on_update",array(-1=>"")+explode("|",driver()->onActions),$K["on_update"]),'</label>
',(support("deferrable")?html_select("deferrable",array('NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'),$K["deferrable"]):''),'<p>
<input type=\'submit\' value=\'Save\'>
<noscript><p><input type=\'submit\' name=\'add\' value=\'Add column\'></noscript>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$Ch="VIEW";if(JUSH=="pgsql"&&$a!=""){$gk=table_status1($a);$Ch=strtoupper($gk["Engine"]);}if($_POST&&!$j){$B=trim($K["name"]);$za=" AS\n$K[select]";$_=ME."table=".url_escape($B);$lg='View has been altered.';$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$B&&JUSH!="sqlite"&&$U=="VIEW"&&$Ch=="VIEW")query_redirect((JUSH=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($B).$za,$_,$lg);else{$Mk="adminer_".uniqid();drop_create("DROP $Ch ".table($a),"CREATE $U ".table($B).$za,"DROP $U ".table($B),"CREATE $U ".table($Mk).$za,"DROP $U ".table($Mk),($_POST["drop"]?substr(ME,0,-1):$_),'View has been dropped.',$lg,'View has been created.',$a,$B);}}$Tg=false;if(!$_POST&&$a!=""){$K=view($a);$Tg=!$K["select"];$K["name"]=$a;$K["materialized"]=($Ch!="VIEW");if(!$j)$j=adminer()->error();}page_header(($a!=""?'Alter view':'Create view'),$j,array("table"=>$a),h($a),$Tg,doc_link(array('sql'=>"create-view.html",)));echo'
<form action="" method="post">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',(support("materializedview")?" ".checkbox("materialized",1,$K["materialized"],'Materialized view'):""),'<p>';textarea("select",$K["select"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["event"])){$aa=$_GET["event"];$Ze=array("YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND");$hk=array("ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE");$K=$_POST;if($_POST&&!$j){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($aa),substr(ME,0,-1),'Event has been dropped.');elseif(in_array($K["INTERVAL_FIELD"],$Ze)&&isset($hk[$K["STATUS"]])){$nj="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($aa!=""?'Event has been altered.':'Event has been created.'),queries(($aa!=""?"ALTER EVENT ".idf_escape($aa).$nj.($aa!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$nj)."\n".$hk[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}$Tg=false;if(!$K&&$aa!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($aa));$Tg=!$L;$K=reset($L);}page_header(($aa!=""?'Alter event'.": ".h($aa):'Create event'),$j,"#events","",$Tg,doc_link(array('sql'=>"create-event.html")));echo'
<form action="" method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="',h($K["EVENT_NAME"]),'" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="',h("$K[EXECUTE_AT]$K[STARTS]"),'">
<tr><th title="datetime">End<td><input name="ENDS" value="',h($K["ENDS"]),'">
<tr><th>Every
<td><input type="number" name="INTERVAL_VALUE" value="',h($K["INTERVAL_VALUE"]),'" class="size"> ',html_select("INTERVAL_FIELD",$Ze,$K["INTERVAL_FIELD"]),'<tr><th>Status<td>',html_select("STATUS",$hk,$K["STATUS"]),'<tr><th>Comment<td><input name="EVENT_COMMENT" value="',h($K["EVENT_COMMENT"]),'" data-maxlength="64">
<tr><th><td>',checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",'On completion preserve'),'</table>
<p>';textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($aa!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$aa)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["procedure"])){$ba=($_GET["name"]?:$_GET["procedure"]);$ej=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])&&!$j){foreach($K["fields"]as$w=>$k){if($k["field"]=="")unset($K["fields"][$w]);}$ih=routine($_GET["procedure"],$ej);$gh=($ih?routine_id($ba,$ih):"");$Mg=routine_id($K["name"],$K);$Kb=create_routine($ej,$K);$_=substr(ME,0,-1);$lg='Routine has been altered.';if(!$_POST["drop"]&&$gh==$Mg&&connection()->flavor!="mysql")queries_redirect($_,$lg,queries(substr_replace($Kb,(JUSH=="mssql"?' OR ALTER':' OR REPLACE'),6,0)));else{$Mk="adminer_".uniqid();drop_create("DROP $ej $gh",$Kb,"DROP $ej $Mg",create_routine($ej,array("name"=>$Mk)+$K),"DROP $ej ".routine_id($Mk,$K),$_,'Routine has been dropped.',$lg,'Routine has been created.',$ba,$K["name"]);}}$Tg=false;if(!$_POST&&$ba!=""){$K=routine($_GET["procedure"],$ej);$Tg=!$K;$K["name"]=$ba;}$hj=strtolower($ej);page_header(($ba!=""?(isset($_GET["function"])?'Alter function':'Alter procedure').": ".h($ba):(isset($_GET["function"])?'Create function':'Create procedure')),$j,"#routines","",$Tg,doc_link(array('sql'=>"create-procedure.html",'mariadb'=>"create-$hj/",)));if(!$_POST&&$ba=="")$K["language"]="sql";$qb=(JUSH=="sql"?flat_collations():array());$fj=routine_languages();echo($qb?"<datalist id='collations'>".optionlist($qb)."</datalist>":""),'
<form action="" method="post" id="form">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',($fj?"<label>".'Language'.": ".html_select("language",array_keys($fj),$K["language"],on('change','routineLanguage',$fj))."</label>\n":""),'<input type=\'submit\' value=\'Save\'>
<div class="scrollable">
<table id="edit-fields" class="nowrap">
';edit_fields($K["fields"],$qb,$ej);if(isset($_GET["function"])){echo"<tr><td>".'Return type';edit_type("returns",(array)$K["returns"],$qb,array(),(JUSH=="pgsql"?array("void","trigger"):array()));}echo'</table>
',script("editFields();"),'</div>
<p>';textarea("definition",$K["definition"],20,80,($fj[$K["language"]]?:JUSH));echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($ba!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$ba)),'>
';$ij=routine_options($ej);if($ij){$th=false;foreach($ij
as$w=>$Rl){$i=($Rl?reset($Rl):"");$K["options"][$w]=idx($K["options"],$w,$i);if($K["options"][$w]!=$i)$th=true;}print_fieldset("options",'Options',$th);echo"<table class='layout'>\n";foreach($ij
as$w=>$Rl){$uf="label-option-$w";$Uk=str_replace("_"," ",$w);$N=array();foreach($Rl
as$Y)$N[$Y]=(strpos($Y,"$Uk ")===0?substr($Y,strlen($Uk)+1):$Y);echo"<tr><th id='$uf'>$Uk<td>".($N?html_select("options[$w]",$N,$K["options"][$w],"",$uf):"<input name='options[$w]' value='".h($K["options"][$w])."' aria-labelledby='$uf' autocapitalize='off'>")."\n";}echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["check"])){$a=$_GET["check"];$B="$_GET[name]";$K=$_POST;if($K&&!$j){$_=ME."table=".url_escape($a);$og='Check has been dropped.';$mg='Check has been altered.';$ng='Check has been created.';if(JUSH=="sqlite")queries_redirect($_,($K["drop"]?$og:($B!=""?$mg:$ng)),recreate_table($a,$a,array(),array(),array(),"",array(),"$B",($K["drop"]?"":$K["clause"])));else{$sa="ALTER TABLE ".table($a);$db=" CHECK ($K[clause])";$Mk="adminer_".uniqid();drop_create("$sa DROP CONSTRAINT ".idf_escape($B),"$sa ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"").$db,"$sa DROP CONSTRAINT ".idf_escape($K["name"]),"$sa ADD CONSTRAINT ".idf_escape($Mk).$db,"$sa DROP CONSTRAINT ".idf_escape($Mk),$_,$og,$mg,$ng,$B,$K["name"]);}}$Tg=false;if(!$K){$gb=driver()->checkConstraints($a);$Tg=($B!=""&&!$gb[$B]);$K=array("name"=>($B!=""?$B:object_name("CHECK",$a,array())),"clause"=>$gb[$B]);}page_header(($B!=""?'Alter check':'Create check'),$j,array("table"=>$a),h($B!=""?$B:$a),$Tg,doc_link(array('sql'=>"create-table-check-constraints.html",'mariadb'=>"constraint/",)));echo'
<form action="" method="post">
';if(JUSH!="sqlite")echo'<p>'.'Name'.': <input name="name" value="'.h($K["name"]).'" data-maxlength="64" autocapitalize="off">';echo'<p>';textarea("clause",$K["clause"]);echo'<p><input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$B="$_GET[name]";$jl=trigger_options();$K=trigger($B,$a);$Tg=($B!=""&&!$K);$Fg=str_replace("{table}",$a,adminer()->namePattern("TRIGGER"));$K+=array("Trigger"=>strtr($Fg,array("{timing}"=>"b","{event}"=>"i","{columns}"=>"","{type}"=>"row")));if($_POST){if(!$j&&in_array($_POST["Timing"],$jl["Timing"])&&in_array($_POST["Event"],$jl["Event"])&&in_array($_POST["Type"],$jl["Type"])){$kh=" ON ".table($a);$Dc="DROP TRIGGER ".idf_escape($B).(JUSH=="pgsql"?$kh:"");$_=ME."table=".url_escape($a);if($_POST["drop"])query_redirect($Dc,$_,'Trigger has been dropped.');else{if($B!="")queries($Dc);queries_redirect($_,($B!=""?'Trigger has been altered.':'Trigger has been created.'),queries(create_trigger($kh,$_POST)));if($B!="")queries(create_trigger($kh,$K+array("Type"=>reset($jl["Type"]))));}}$K=$_POST;}page_header(($B!=""?'Alter trigger':'Create trigger'),$j,array("table"=>$a),h($B!=""?$B:$a),$Tg,doc_link(array('sql'=>"create-trigger.html",)));$Ig=strtr(preg_quote($Fg),array('\{timing\}'=>'[abi]','\{event\}'=>'[iud]*','\{columns\}'=>'.*','\{type\}'=>'(row|statement)'));$il=on('change','triggerChange',"^$Ig$",$Fg);$Zg=on('input','triggerChange',"^$Ig$",$Fg);echo'
<form action="" method="post" id="form">
<table class="layout">
<tr><th>Time
<td>',html_select("Timing",$jl["Timing"],$K["Timing"],$il),'<tr><th>Event<td>',html_select("Event",$jl["Event"],$K["Event"],$il),(in_array("UPDATE OF",$jl["Event"])?" <input name='Of' value='".h($K["Of"])."' class='hidden'$Zg>":""),'<tr><th>Type<td>',html_select("Type",$jl["Type"],$K["Type"],$il),'<tr><th>Name<td><input name="Trigger" value="',h($K["Trigger"]),'" data-maxlength="64" autocapitalize="off">
</table>
',script("fire(qs('#form')['Timing'], 'change');"),'<p>';textarea("Statement",$K["Statement"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["user"])){function
grant($Wd,array$_i,$d,$kh){if(!$_i)return
true;if($_i==array("ALL PRIVILEGES","GRANT OPTION"))return($Wd=="GRANT"?queries("$Wd ALL PRIVILEGES$kh WITH GRANT OPTION"):queries("$Wd ALL PRIVILEGES$kh")&&queries("$Wd GRANT OPTION$kh"));return
queries("$Wd ".preg_replace('~(GRANT OPTION)\([^)]*\)~','\1',implode("$d, ",$_i).$d).$kh);}$da=$_GET["user"];$_i=array(""=>array("All privileges"=>""));foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$Eb)$_i[$Eb=="File access on server"?"Server Admin":$Eb][$K["Privilege"]]=$K["Comment"];}unset($_i["Server Admin"]["Usage"]);foreach($_i["Tables"]as$w=>$X)unset($_i["Databases"][$w]);$Lg=array();if($_POST){foreach($_POST["objects"]as$w=>$X)$Lg[$X]=(array)$Lg[$X]+idx($_POST["grants"],$w,array());}$Xd=array();$I=(isset($_GET["host"])?connection()->query("SHOW GRANTS FOR ".q($da)."@".q($_GET["host"])):null);$Tg=(isset($_GET["host"])&&!$I);if($I){while($K=$I->fetch_row()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$A)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$A[1],$Tf,PREG_SET_ORDER)){foreach($Tf
as$X){if($X[1]!="USAGE")$Xd["$A[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$Xd["$A[2]$X[2]"]["GRANT OPTION"]=true;}}}}if($_POST&&!$j){$jh=(isset($_GET["host"])?q($da)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $jh",ME."privileges=",'User has been dropped.');else{$Pg=q($_POST["user"])."@".q($_POST["host"]);$Wh=$_POST["pass"];$Mb=false;$I=true;if($jh!=$Pg){$Mb=queries("CREATE USER $Pg IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($Wh));$I=$Mb;}elseif($Wh!="")$I=queries("SET PASSWORD FOR $Pg = ".(min_version(8,99)||$_POST["hashed"]?q($Wh):"PASSWORD(".q($Wh).")"));if($I){$aj=array();foreach($Lg
as$Xg=>$Wd){if(isset($_GET["grant"]))$Wd=array_filter($Wd);$Wd=array_keys($Wd);if(isset($_GET["grant"]))$aj=array_diff(array_keys(array_filter($Lg[$Xg],'strlen')),$Wd);elseif($jh==$Pg){$fh=array_keys((array)$Xd[$Xg]);$aj=array_diff($fh,$Wd);$Wd=array_diff($Wd,$fh);unset($Xd[$Xg]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$Xg,$A)&&(!grant("REVOKE",$aj,$A[2]," ON $A[1] FROM $Pg")||!grant("GRANT",$Wd,$A[2]," ON $A[1] TO $Pg"))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($jh!=$Pg)queries("DROP USER $jh");elseif(!isset($_GET["grant"])){foreach($Xd
as$Xg=>$aj){if(preg_match('~^(.+)(\(.*\))?$~U',$Xg,$A))grant("REVOKE",array_keys($aj),$A[2]," ON $A[1] FROM $Pg");}}}if($I&&!Queries::$queries)redirect(ME."privileges=");queries_redirect(ME."privileges=",(isset($_GET["host"])?'User has been altered.':'User has been created.'),$I);if($Mb)connection()->query("DROP USER $Pg");}}page_header((isset($_GET["host"])?'Username'.": ".h("$da@$_GET[host]"):'Create user'),$j,array("privileges"=>array('','Privileges')),"",$Tg,doc_link(array('sql'=>"grant.html",'mariadb'=>"grant")));$K=$_POST;if($K)$Xd=$Lg;else{$K=$_GET+array("host"=>get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)"));$Xd[(DB==""||$Xd?"":idf_escape(addcslashes(DB,"%_\\"))).".*"]=array();}echo'<form action="" method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="',h($K["host"]),'" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="',h($K["user"]),'" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="',h($K["pass"]),'" autocomplete="new-password">
',($K["hashed"]?"":script("typePassword(qs('#pass'));")),(min_version(8,99)?"":checkbox("hashed",1,$K["hashed"],'Hashed',on('click','hashedClick'))),'</table>

',"<table class='odds'>\n","<thead><tr><th colspan='2'>".'Privileges';$r=0;foreach($Xd
as$Xg=>$Wd){echo'<th>'.($Xg!="*.*"?"<input name='objects[$r]' value='".h($Xg)."' size='10' autocapitalize='off'>":input_hidden("objects[$r]","*.*")."*.*");$r++;}echo"<tbody>\n";foreach(array(""=>"","Server Admin"=>'Server',"Databases"=>'Database',"Tables"=>'Table',"Procedures"=>'Routine',)as$Eb=>$kc){foreach((array)$_i[$Eb]as$zi=>$ub){echo"<tr><td".($kc?">$kc<td":" colspan='2'").' lang="en" title="'.h($ub).'">'.h($zi);$r=0;foreach($Xd
as$Xg=>$Wd){$B="'grants[$r][".h(strtoupper($zi))."]'";$Y=$Wd[strtoupper($zi)];if($Eb=="Server Admin"&&$Xg!=(isset($Xd["*.*"])?"*.*":".*"))echo"<td>";elseif(isset($_GET["grant"]))echo"<td><select name=$B><option><option value='1'".($Y?" selected":"").">".'Grant'."<option value='0'".($Y=="0"?" selected":"").">".'Revoke'."</select>";else
echo"<td align='center'><label class='block'>","<input type='checkbox' name=$B value='1'".($Y?" checked":"").($zi=="All privileges"?" id='grants-$r-all'":($zi=="Grant option"?"":on('click','grantsClick',"grants-$r-all"))).">","</label>";$r++;}}}echo"</table>\n",'<p>
<input type=\'submit\' value=\'Save\'>
';if(isset($_GET["host"]))echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',"$da@$_GET[host]")),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST&&!$j){$tf=0;foreach((array)$_POST["kill"]as$X){if(adminer()->killProcess($X))$tf++;}queries_redirect(ME."processlist=",lang_format(array('%d process has been killed.','%d processes have been killed.'),$tf),$tf||!$_POST["kill"]);}}page_header('Process list',$j);echo'
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
',script("tableCheck();");}elseif($_GET["select"]!=""){$a=$_GET["select"];$S=table_status1($a);$v=indexes($a);$l=fields($a);$Jd=column_foreign_keys($a);$eh=$S["Oid"];$cj=array();$d=array();$tj=array();$vh=array();$Pk=null;foreach($l
as$w=>$k){$B=adminer()->fieldName($k);$Hg=html_entity_decode(strip_tags($B),ENT_QUOTES);if(isset($k["privileges"]["select"])&&$B!=""){$d[$w]=$Hg;if(is_shortable($k))$Pk=adminer()->selectLengthProcess();}if(isset($k["privileges"]["where"])&&$B!="")$tj[$w]=$Hg;if(isset($k["privileges"]["order"])&&$B!="")$vh[$w]=$Hg;$cj+=$k["privileges"];}list($N,$q)=adminer()->selectColumnsProcess($d,$v);$N=array_unique($N);$q=array_unique($q);$gf=count($q)<count($N);$Z=adminer()->selectSearchProcess($l,$v,$S);$D=adminer()->selectOrderProcess($l,$v);$y=adminer()->selectLimitProcess();if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$vl=>$K){$za=convert_field($l[key($K)]);$N=array($za?:idf_escape(key($K)));$Z[]=where_check(bracket_escape($vl,true),$l);$J=driver()->select($a,$N,$Z,$N);if($J)echo
first($J->fetch_row());}exit;}$wi=$xl=array();foreach($v
as$u){if($u["type"]=="PRIMARY"){$wi=array_flip($u["columns"]);$xl=($N?$wi:array());foreach($xl
as$w=>$X){if(in_array(idf_escape($w),$N))unset($xl[$w]);}break;}}if($eh&&!$wi){$wi=$xl=array($eh=>0);$v[]=array("type"=>"PRIMARY","columns"=>array($eh));}if($_POST&&!$j){$cm=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$gb=array();foreach($_POST["check"]as$db)$gb[]=where_check($db,$l);$cm[]="((".implode(") OR (",$gb)."))";}$em=$cm;$cm=($cm?"\nWHERE ".implode(" AND ",$cm):"");if($_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers($a);adminer()->dumpTable($a,"");$wj=($N?:array("*"));$Gb=convert_fields($d,$l,$N);if($Gb)$wj[]=substr($Gb,2);$H="";if(is_array($_POST["check"])&&!$wi){$Od=implode(", ",$wj)."\nFROM ".table($a);$ae=($q&&$gf?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):"");$sl=array();foreach($_POST["check"]as$X)$sl[]="(SELECT".limit($Od,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l).$ae,1).")";$H=implode(" UNION ALL ",$sl);}adminer()->dumpData($a,"table",$H,$wj,$em,($gf?$q:array()),$D);adminer()->dumpFooter();exit;}if(!adminer()->selectEmailProcess($Z,$Jd)){if($_POST["save"]||$_POST["delete"]){$I=true;$la=0;$Na=false;$P=array();if(!$_POST["delete"]){foreach($l
as$B=>$X){$t=bracket_escape($B);if(isset($_POST["fields"][$t])||$_FILES["fields-$t"]){$X=process_input($l[$B]);if($X!==null&&($_POST["clone"]||$X!==false))$P[idf_escape($B)]=($X!==false?$X:idf_escape($B));}}}if($_POST["delete"]||$P){$H=($_POST["clone"]?"INTO ".table($a)." (".implode(", ",array_keys($P)).")\nSELECT ".implode(", ",$P)."\nFROM ".table($a):"");if($_POST["all"]||($wi&&is_array($_POST["check"]))||$gf){$I=($_POST["delete"]?driver()->delete($a,$cm):($_POST["clone"]?queries("INSERT $H$cm".driver()->insertReturning($a)):driver()->update($a,$P,$cm)));$la=connection()->affected_rows;if(is_object($I))$la+=$I->num_rows;}else{$Na=count((array)$_POST["check"])>1&&driver()->begin();foreach((array)$_POST["check"]as$X){$bm="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l);$I=($_POST["delete"]?driver()->delete($a,$bm,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$bm)):driver()->update($a,$P,$bm,1)));if(!$I)break;$la+=connection()->affected_rows;}if($Na&&$I&&!driver()->commit())$I=false;}}$lg=lang_format(array('%d item has been affected.','%d items have been affected.'),$la);if($_POST["clone"]&&$I&&$la==1){$_f=last_id($I);if($_f)$lg=sprintf('Item%s has been inserted.'," $_f");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page|next":""),$lg,$I);if($Na)driver()->rollback();if(!$_POST["delete"]){$ni=(array)$_POST["fields"];edit_form($a,array_intersect_key($l,$ni),$ni,!$_POST["clone"],$j);page_footer();exit;}}elseif(!$_POST["import"]){$I=true;$la=0;$Na=count((array)$_POST["val"])>1&&driver()->begin();foreach((array)$_POST["val"]as$vl=>$K){$P=array();foreach($K
as$w=>$X){$w=bracket_escape($w,true);$P[idf_escape($w)]=(preg_match('~char|text~',$l[$w]["type"])||$X!=""?adminer()->processInput($l[$w],$X):"NULL");}$I=driver()->update($a,$P," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check(bracket_escape($vl,true),$l),($gf||$wi?0:1)," ");if(!$I)break;$la+=connection()->affected_rows;}if($Na)$I=$I&&driver()->commit();queries_redirect(remove_from_uri(),lang_format(array('%d item has been affected.','%d items have been affected.'),$la),$I);if($Na)driver()->rollback();}else{save_settings(array("format"=>$_POST["separator"]),"adminer_import");$xd=get_file("csv_file",true);if(!is_string($xd))$j=upload_error($xd);elseif(!preg_match('~~u',$xd))$j='File must be in UTF-8 encoding.';else{$rb=array_keys($l);$Aj=($_POST["separator"]=="csv"?",":($_POST["separator"]=="tsv"?"\t":";"));$Qb=parse_csv($xd,$Aj);$la=count($Qb);driver()->begin();$L=array();foreach($Qb
as$w=>$Rl){if(!$w&&!array_diff($Rl,$rb)){$rb=$Rl;$la--;}else{$P=array();foreach($Rl
as$r=>$nb)$P[idf_escape($rb[$r])]=($nb==""&&$l[$rb[$r]]["null"]?"NULL":q(csv_value($nb)));$L[]=$P;}}$I=(!$L||driver()->insertUpdate($a,$L,$wi));if($I)driver()->commit();queries_redirect(remove_from_uri("page|next"),lang_format(array('%d row has been imported.','%d rows have been imported.'),$la),$I);driver()->rollback();}}}}$yk=adminer()->tableName($S);if(is_ajax()){page_headers();ob_start();}else
page_header('Select'.": $yk",$j,array(),"",(!$l&&support("table")),($l?doc_link(array(JUSH=>driver()->tableHelp($a,is_view($S)))):""));$P=null;if(isset($cj["insert"])||!support("table")){$P="";foreach((array)$_GET["where"]as$X){$Y=$X["val"];if(is_array($Y))$Y=(count($Y)==1&&preg_match('~^val-(.*)~s',reset($Y),$A)?$A[1]:"");if($X["col"]!=""&&$Y!=""&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$Y)))))$P
.="&set[".url_escape(bracket_escape($X["col"]))."]=".url_escape($Y);}}adminer()->selectLinks($S,$P);if(!$d&&support("table"))echo"<p class='error'>".'Unable to select the table.'."\n";else{echo"<form action='' id='form'>\n","<div hidden>";hidden_fields_get();echo(DB!=""?input_hidden("db",DB).(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""):""),input_hidden("select",$a),"</div>\n";adminer()->selectColumnsPrint($N,$d);adminer()->selectSearchPrint($Z,$tj,$v,$S);adminer()->selectOrderPrint($D,$vh,$v);adminer()->selectLimitPrint($y);if($Pk!==null)adminer()->selectLengthPrint($Pk);adminer()->selectActionPrint($v);echo"</form>\n";foreach((array)$_GET["where"]as$X){if($X["op"]=="SQL"&&!in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"))){echo"<p class='error'>".'Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.'."\n";page_footer();exit;}}$E=$_GET["page"];$Md=null;if($E=="last"){$Md=get_val(count_rows($a,$Z,$gf,$q));$E=floor(max(0,intval($Md)-1)/$y);}$vj=$N;$Zd=$q;if(!$vj){$vj[]="*";$Gb=convert_fields($d,$l,$N);if($Gb)$vj[]=substr($Gb,2);}foreach($N
as$w=>$X){$k=$l[idf_unescape($X)];if($k&&($za=convert_field($k)))$vj[$w]="$za AS $X";}if(JUSH=="pgsql"||JUSH=="mssql"){foreach((array)$_GET["columns"]as$w=>$X){if(isset($vj[$w])&&$X["fun"])$vj[$w].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$gf&&$xl){foreach($xl
as$w=>$X){$vj[]=idf_escape($w);if($Zd)$Zd[]=idf_escape($w);}}$I=driver()->select($a,$vj,$Z,$Zd,$D,$y,$E,true);if(!is_object($I))echo"<p class='error'>".(adminer()->error()?:'Unknown error.')."\n";else{if(JUSH=="mssql"&&$E)$I->seek($y*$E);$Oc=array();$L=array();while($K=$I->fetch_assoc()){if($E&&JUSH=="oracle")unset($K["RNUM"]);$L[]=$K;}$ke=($y&&(support("cursor")?$_GET["next"]!="":count($L)>=$y));if(is_ajax()&&$ke)header("X-Next-Page: ".pagination_href($E+1));if($_GET["modify"]&&$L){$cg=max_input_vars(count($L[0])+1,20);echo($cg&&count($L)>$cg?"<p class='error'>".max_input_vars_error()."\n":"");}echo"<form action='' method='post' enctype='multipart/form-data'".on_upload_progress($Cl).">\n";if($_GET["page"]!="last"&&$y&&$q&&$gf&&JUSH=="sql")$Md=get_val(" SELECT FOUND_ROWS()");if(!$L)echo"<p class='message'>".'No rows.'."\n";else{$Ja=adminer()->backwardKeys($a,$yk);$Zi=array();reset($N);foreach($L[0]as$w=>$X){if(!isset($xl[$w])){$X=idx($_GET["columns"],key($N))?:array();$Zi[$w]=array("fun"=>$X["fun"],"col"=>($N?$X["col"]:$w));next($N);}}echo"<div class='scrollable'>","<table id='table' class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown').">\n","<thead><tr>".(!$q&&$N?"":"<td class='hover check sticky'><input type='checkbox' id='all-page' class='jsonly' title='".'All rows on this page'."'".on('click','formCheck','^check').">");$Jg=array();$Ii=1;foreach($Zi
as$w=>$X){$k=$l[$X["col"]];$B=($k?adminer()->fieldName($k,$Ii):($X["fun"]?"*":h($w)));if($B!=""){$Ii++;$Jg[$w]=$B;$c=idf_escape($w);$ze=remove_from_uri('(order|desc)[^=]*|page|next').'&order[0]='.url_escape($w);$kc="&desc[0]=1";$Rj=preg_replace('~ DESC( NULLS LAST)?$~','',$D[0]);$Tj=($Rj==$c||$Rj==$w);echo"<th id='th[".h(bracket_escape($w))."]'".($Tj?" aria-sort='".($Rj==$D[0]?"ascending":"descending")."'":"").">";$Sd=apply_sql_function(h($X["fun"]),$B);$Sj=isset($k["privileges"]["order"])||$X["fun"];echo($Sj?"<a href='".h($ze.($Tj&&$Rj==$D[0]?$kc:''))."'>$Sd</a>":$Sd);$kg=($Sj?"<a href='".h($ze.$kc)."' title='".'descending'."' class='text'> ↓</a>":'');if(!$X["fun"]&&isset($k["privileges"]["where"]))$kg
.="<a href='#fieldset-search' title='".'Search'."' class='text jsonly'".on('click','selectSearch',$w)."> =</a>";echo($kg?"<span class='column'>$kg</span>":"");}}$Gf=array();if($_GET["modify"]){foreach($L
as$K){foreach($K
as$w=>$X)$Gf[$w]=max($Gf[$w],min(40,utf8_length((string)$X)));}}$ue=array();$te=array();foreach((array)$_GET["where"]as$X){$X+=array("col"=>"","op"=>"","val"=>"");$nb=$X["col"];$sj=$X["val"];if(!is_array($sj)&&($sj!=""||preg_match('~NULL$~',$X["op"]))&&(!$X["op"]||in_array($X["op"],adminer()->operators($S)))){$If=strtr(preg_quote($sj),array("%"=>".*?","_"=>"."));$ci=array("LIKE %%"=>$If,"ILIKE %%"=>$If,"REGEXP"=>$sj)+(JUSH=="pgsql"?array("~"=>$sj,"~*"=>$sj):array())+($nb!=""?array():array("="=>'^'.preg_quote($sj).'\z',"IN"=>'^(?:'.implode("|",array_map('preg_quote',array_map('trim',explode(",",$sj)))).')\z',"LIKE"=>"^$If\\z","ILIKE"=>"^$If\\z","FIND_IN_SET"=>'(?<=^|,)'.preg_quote($sj).'(?=,|\z)',));foreach(($nb!=""?array($nb=>$l[$nb]):$l)as$B=>$k){if($nb!=""||is_searchable($k,$X)){$mh=$X["op"]?:(!preg_match('~'.text_type().'~',$k["type"])?"IN":(preg_match('~%~',$sj)?"LIKE":"LIKE %%"));if(isset($ci[$mh])){$jb=preg_match('~^ILIKE|\*$~',$mh)||($mh!="~"&&preg_match('~^(sql|mssql|sqlite)$~',JUSH));$ue[$B][]="(?".($jb?"i":"").":$ci[$mh])";}elseif($mh=="IS NULL"&&$nb=="")$te[$B]=true;}}}}echo($Ja?"<th>".'Relations':"")."<tbody>\n";if(is_ajax())ob_end_clean();foreach(adminer()->rowDescriptions($L,$Jd)as$Dg=>$K){$ul=unique_array($L[$Dg],$v);if(!$ul){$ul=array();foreach($L[$Dg]as$w=>$X){if(!in_array(idx(idx($Zi,$w,array()),"fun"),driver()->grouping))$ul[$w]=$X;}}$vl="";$r=0;foreach($ul
as$w=>$X){$Yi=idx($Zi,$w,array());$Sd=idx($Yi,"fun","");$nb=($Sd?$Yi["col"]:$w);$k=(array)$l[$nb];$ff=is_blob($k);if(!$Sd&&strlen($X)>64&&driver()->md5(idf_escape($nb),$k)){$Sd="md5";$X=md5($ff?(string)driver()->value($X,$k):$X);}if($Sd){$vl
.="&fun[$r]=".url_escape($Sd)."&col[$r]=".url_escape($nb).($X!==null?"&val[$r]=".url_escape($X===false?"f":$X):"");$r++;}else$vl
.="&".($X!==null?"where[".url_escape(bracket_escape($nb))."]=".url_escape($X===false?"f":$X):"null[]=".url_escape($nb));}echo"<tr>".(!$q&&$N?"":"<td class='hover check sticky'>".($gf||information_schema(DB)?"":"<a href='".h(ME."edit=".url_escape($a).$vl)."' class='edit'>".'edit'."</a> ").checkbox("check[]",substr($vl,1),in_array(substr($vl,1),(array)$_POST["check"])));foreach($K
as$w=>$X){if(isset($Jg[$w])){$Sd=$Zi[$w]["fun"];$nb=$Zi[$w]["col"];$k=(array)$l[$w];if($X!=""&&(!isset($Oc[$w])||$Oc[$w]!=""))$Oc[$w]=(is_mail($X)?$Jg[$w]:"");$z="";if(is_blob($k)&&$X!="")$z=ME.'download='.url_escape($a).'&field='.url_escape($w).$vl;if(!$z&&$X!==null){foreach((array)$Jd[$w]as$n){if(count($Jd[$w])==1||end($n["source"])==$w){$z="";foreach($n["source"]as$r=>$Uj)$z
.=where_link($r,$n["target"][$r],$L[$Dg][$Uj]);$z=($n["db"]!=""?preg_replace('~([?&]db=)[^&]+~','\1'.url_escape($n["db"]),ME):ME).'select='.url_escape($n["table"]).$z;if($n["ns"])$z=preg_replace('~([?&]ns=)[^&]+~','\1'.url_escape($n["ns"]),$z);if(count($n["source"])==1)break;}}}if($Sd=="count"&&$nb==""){$z=ME."select=".url_escape($a);$r=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$ul))$z
.=where_link($r++,$W["col"],$W["val"],$W["op"]);}foreach($ul
as$pf=>$W){if(idx(idx($Zi,$pf,array()),"fun")){$z="";break;}$z
.=where_link($r++,$pf,$W);}}$_e=select_value($X,$z,$k,$Pk,($Sd?array():idx($ue,$w,array())));if($X===null&&!$Sd&&isset($te[$w]))$_e="<mark>$_e</mark>";$t=bracket_escape($vl);$s=h("val[$t][".bracket_escape($w)."]");$pi=idx(idx($_POST["val"],$t),bracket_escape($w));$_l=idx($k["privileges"],"update")&&!is_identity_always($k);$Kc=!is_array($K[$w])&&!is_blob($k)&&is_utf8($X)&&$L[$Dg][$w]==$X&&!$Sd&&!$k["generated"]&&$_l;$U=($Sd=="min"||$Sd=="max"?$l[$nb]["type"]:$k["type"]);$Ok=preg_match('~text|json|lob~',$U);$hf=preg_match(number_type(),$U)||preg_match('~^(avg|ceil|char_length|count|count distinct|floor|len|length|round|sum|time_to_sec)$~',$Sd);echo"<td id='$s'".($hf&&($X===null||is_numeric(strip_tags($_e))||$U=="money")?" class='number'":"");if(($_GET["modify"]&&$Kc&&$X!==null)||$pi!==null){$fe=h($pi!==null?$pi:$X);echo">".($Ok?"<textarea name='$s' cols='30' rows='".(substr_count($X,"\n")+1)."'>$fe</textarea>":"<input name='$s' value='$fe' size='$Gf[$w]'>");}else{$Qf=strpos($_e,"<i>…</i>");echo($_l?" data-text='".($Qf?2:($Ok?1:0))."'".($Kc?"":" data-warning='".'Use the edit link to modify this value.'."'"):"").">$_e";}}}if($Ja)echo"<td>";adminer()->backwardKeysPrint($Ja,$L[$Dg]);echo"</tr>\n";}if(is_ajax())exit;echo"</table>\n","</div>\n";}if(!is_ajax()){$ka=get_settings("adminer_import");if($L||$E||$ke){$ed=true;if($_GET["page"]!="last"){if(!$y||(count($L)<$y&&($L||!$E)))$Md=($E?$E*$y:0)+count($L);elseif(JUSH!="sql"||!$gf){$Md=($gf?null:found_rows($S,$Z));$ed=!driver()->hasEstimatedRows();if($Md===null||(!$ed&&$Md<max(1e4,2*($E+1)*$y))){$Md=first(slow_query(count_rows($a,$Z,$gf,$q)));$ed=true;}}}if(!support("cursor"))$ke=(($Md===false?count($L)+1:$Md-$E*$y)>$y);$Ih=($y&&($ke||$E));if($Ih)echo($ke?'<p><a href="'.h(pagination_href($E+1)).'" class="loadmore"'.on('click','selectLoadMore','Loading…').'>'.'Load more data'.'</a>':''),"\n";echo"<div class='footer'><div>\n";if($Ih){$ag=($Md===false?$E+($L?(count($L)>=$y?2:1):0):floor(($Md-1)/$y));echo"<fieldset><legend>".'Page'."</legend>";if(!support("cursor")){echo
pagination(0,$E).($E>5?" …":"");for($r=max(1,$E-4);$r<min($ag,$E+5);$r++)echo
pagination($r,$E);if($ag>0)echo($E+5<$ag?" …":""),($ed&&$Md!==false?pagination($ag,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$ag'>".'last'."</a>");}else
echo
pagination(0,$E).($E>1?" …":""),($E?pagination($E,$E):""),($ke?pagination($E+1,$E)." …":"");echo"</fieldset>\n";}echo"<fieldset>","<legend>".'Whole result'."</legend>";$sc=($ed?"":"~ ").$Md;$uf=($Md!==false?($ed?"":"~ ").lang_format(array('%d row','%d rows'),$Md):"");echo
checkbox("all",1,0,$uf,on('click','countRows',$sc))."\n","</fieldset>\n";if(adminer()->selectCommandPrint())echo'<fieldset',($_GET["modify"]?'':" title='".'Ctrl+click on a value to modify it.'."'"),'>
<legend><a href=\'',h($_GET["modify"]?remove_from_uri("modify"):relative_uri()."&modify=1"),'\'>Modify</a></legend><div>
<input type=\'submit\' id=\'save\' value=\'Save\'',($_GET["modify"]||$_POST["val"]?'':" class='jsonly' disabled"),'>
</div></fieldset>

<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type=\'submit\' name=\'edit\' value=\'Edit\'>
<input type=\'submit\' name=\'clone\' value=\'Clone\'>
<input type=\'submit\' name=\'delete\' value=\'Delete\'',confirm(),'>
</div></fieldset>
';$Kd=adminer()->dumpFormat();foreach((array)$_GET["columns"]as$c){if($c["fun"]){unset($Kd['sql']);break;}}if($Kd){print_fieldset("export",'Export'." <span id='selected2'></span>");$Gh=adminer()->dumpOutput();echo($Gh?html_select("output",$Gh,$ka["output"])." ":""),html_select("format",$Kd,$ka["format"])," <input type='submit' name='export' value='".'Export'."'>\n","</div></fieldset>\n";}adminer()->selectEmailPrint(array_filter($Oc,'strlen'),$d);echo"</div></div>\n";}if(adminer()->selectImportPrint())echo"<p>","<a href='#import' class='toggle'>".'Import'."</a>","<span id='import'".($_POST["import"]?"":" class='hidden'").">: ",($Cl?input_hidden(ini_get("session.upload_progress.name"),$Cl):""),file_input(" name='csv_file'"," ".html_select("separator",array("csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"),$ka["format"])." <input type='submit' name='import' value='".'Import'."'>".($Cl?" <progress class='jsonly hidden' max='1' value='0'></progress>":"")),"</span>";echo
input_token(),"</form>\n",(!$q&&$N?"":script("tableCheck();"));}}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$gk=isset($_GET["status"]);page_header($gk?'Status':'Variables');$Sl=($gk?adminer()->showStatus():adminer()->showVariables());if(!$Sl)echo"<p class='message'>".'No rows.'."\n";else{echo"<table>\n";foreach($Sl
as$K){echo"<tr>";$w=array_shift($K);echo"<th><code class='jush-".JUSH.($gk?"status":"set")."'>".h($w)."</code>";foreach($K
as$X)echo"<td>".nl_br(h($X));}echo"</table>\n";}}elseif(isset($_GET["script"])){header("Content-Type: application/json; charset=utf-8");if($_GET["script"]=="db"){$pk=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach(table_status()as$B=>$S){json_row("Comment-$B",h($S["Comment"]).($S["Error"]?" <span class='error'>".h($S["Error"])."</span>":""));if(!is_view($S)||preg_match('~materialized~i',$S["Engine"])){foreach(array("Engine","Collation")as$w)json_row("$w-$B",h($S[$w]));foreach(array_keys($pk+array("Auto_increment"=>0,"Rows"=>0))as$w){if(array_key_exists($w,$S))json_row("$w-$B",format_status($S,$w));if($S[$w]!=""&&isset($pk[$w]))$pk[$w]+=($S["Engine"]!="InnoDB"||$w!="Data_free"?$S[$w]:0);}}}if(function_exists('Adminer\db_status'))$pk=db_status();foreach($pk
as$w=>$X)json_row("sum-$w",format_number($X));json_row("");}elseif($_GET["script"]=="kill"){if(!$j)connection()->query("KILL ".number($_POST["kill"]));}else{foreach(count_tables(adminer()->databases(false))as$h=>$X){json_row("tables-$h",format_number($X));json_row("size-$h",db_size($h));}json_row("");}exit;}else{if(!isset($_GET["select"])&&support("single_table")){$T=tables_list();if($T)redirect(ME.(support("table")?"table=":"select=").url_escape(key($T)));}$hg=ME.(isset($_GET["select"])?"select=&":"");$Gk=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($Gk&&!$j&&!$_POST["search"]){$I=true;$lg="";if(JUSH=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"]);$lg='Tables have been truncated.';}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$lg='Tables have been moved.';}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$lg='Tables have been copied.';}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$lg='Tables have been dropped.';}elseif(JUSH=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("PRAGMA integrity_check(".q($R).")")as$K)$lg
.="<b>".h($R)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(JUSH=="mssql"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("DBCC CHECKTABLE (".q(table($R)).") WITH TABLERESULTS")as$K)$lg
.="<b>".h($R)."</b>: ".h($K["MessageText"])."<br>";}}elseif(JUSH!="sql"){$I=(JUSH=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),(array)$_POST["tables"]));$lg='Tables have been optimized.';}elseif(!$_POST["tables"])$lg='No tables.';elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('Adminer\idf_escape',$_POST["tables"])))){while($K=$I->fetch_assoc())$lg
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect(relative_uri(),$lg,$I);}page_header(($_GET["ns"]==""?'Database'.": ".h(DB):'Schema'.": ".h($_GET["ns"])),$j,true);if(adminer()->homepage()){if($_GET["ns"]!==""){$D=$_GET["order"];$Pd=($D||support("fast_status"));echo"<div>\n","<h3 id='tables-views'>".'Tables and views'."</h3>\n";$Fk=($Pd?table_status():tables_list());if(!$Fk)echo"<p class='message'>".'No tables.'."\n";else{echo"<form action='' method='post'>\n";if(support("table")){echo"<fieldset><legend>".'Search data in tables'." <span id='selected2'></span></legend><div>",html_select("op",adminer()->operators(),idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%"))," <input type='search' name='query' value='".h($_POST["query"])."'".on('keydown','submitKeydown','search').">"," <input type='submit' name='search' value='".'Search'."'>\n","</div></fieldset>\n";if(!$j&&$_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n",'<thead><tr>','<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="'.'All'.'"'.on('click','formCheck','^(tables|views)\[').'>','<th class="sticky"'.(!$D&&JUSH!='sqlite'?" aria-sort='ascending'":'').'><a href="'.h(substr($hg,0,-1)).'">'.'Table'.'</a>';$d=array("Engine"=>array('Engine'.doc_link(array('sql'=>'storage-engines.html'))));if(collations())$d["Collation"]=array('Collation'.doc_link(array('sql'=>'charset-charsets.html','mariadb'=>'supported-character-sets-and-collations/')));if(function_exists('Adminer\alter_table'))$d["Data_length"]=array('Data Length'.doc_link(array('sql'=>'show-table-status.html',)),"create",'Alter table',);if(support("indexes"))$d["Index_length"]=array('Index Length'.doc_link(array('sql'=>'show-table-status.html',)),"indexes",'Alter indexes',);$d["Data_free"]=array('Data Free'.doc_link(array('sql'=>'show-table-status.html')),"edit",'New item');if(function_exists('Adminer\alter_table'))$d["Auto_increment"]=array('Auto Increment'.doc_link(array('sql'=>'example-auto-increment.html','mariadb'=>'auto_increment/')),"auto_increment=1&create",'Alter table',);$d["Rows"]=array('Rows'.doc_link(array('sql'=>'show-table-status.html',)),"select",'Select data',);if(support("comment"))$d["Comment"]=array('Comment'.doc_link(array('sql'=>'show-table-status.html',)),);$_a=array('Engine','Collation','Comment');foreach($d
as$w=>$c)echo"<th".($D==$w?" aria-sort='".(in_array($w,$_a)?"ascending":"descending")."'":"")."><a href='".h($hg)."order=$w'>$c[0]</a>";echo"<tbody>\n";if($D){uasort($Fk,function($fa,$Ga)use($D,$_a){$J=($fa[$D]<$Ga[$D]?-1:($fa[$D]>$Ga[$D]?1:0));return(in_array($D,$_a)?$J:-$J);});}$T=0;$pk=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach($Fk
as$B=>$gk){$Vl=($Pd?is_view($gk):$gk!==null&&!preg_match('~table|sequence~i',$gk));$gk=($Pd?$gk:array('Engine'=>$gk));$s=h("Table-".$B);echo'<tr><td class="hover">'.checkbox(($Vl?"views[]":"tables[]"),$B,in_array("$B",$Gk,true),"","","",$s),'<th class="sticky">'.(support("table")||support("indexes")?"<a href='".h(ME)."table=".url_escape($B)."' title='".'Show structure'."' id='$s'>".h($B).'</a>':h($B));if($Vl&&!preg_match('~materialized~i',$gk['Engine'])){$Uk='View';echo'<td colspan="'.(count($d)-(support("comment")?2:1)).'">'.(support("view")?"<a href='".h(ME)."view=".url_escape($B)."' title='".'Alter view'."'>$Uk</a>":$Uk),"<td align='right'><a href='".h(ME)."select=".url_escape($B)."' title='".'Select data'."'>?</a>";if(support("comment"))echo'<td>'.h($gk['Comment']);}else{if($Pd){foreach(array_keys($pk)as$w)$pk[$w]+=($gk["Engine"]!="InnoDB"||$w!="Data_free"?idx($gk,$w):0);}foreach($d
as$w=>$c){$s=" id='$w-".h($B)."'";echo($c[1]?"<td align='right'><a href='".h(ME."$c[1]=").url_escape($B)."'$s title='$c[2]'>".format_status($gk,$w)."</a>":"<td$s>".h(idx($gk,$w,'?')).($w=="Comment"&&$gk["Error"]?" <span class='error'>".h($gk["Error"])."</span>":""));}$T++;}echo"\n";}echo"<tr><td class='hover'><th class='sticky'>".sprintf('%d in total',count($Fk)),"<td>".h(JUSH=="sql"?get_val("SELECT @@default_storage_engine"):""),(collations()?"<td>".h(db_collation(DB,collations())):'');if($Pd&&function_exists('Adminer\db_status'))$pk=db_status();foreach($pk
as$w=>$ok)echo($d[$w]?"<td align='right' id='sum-$w'>".($Pd?format_number($ok):""):"");echo"\n","</table>\n",($Pd?'':script("ajaxSetHtml('".js_escape(ME)."script=db');")),"</div>\n";if(!information_schema(DB)){$Ol="<input type='submit' value='".'Vacuum'."'".on_help("VACUUM")."> ";$rh="<input type='submit' name='optimize' value='".'Optimize'."'".on_help(JUSH=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE")."> ";$xi=(JUSH=="sqlite"?$Ol."<input type='submit' name='check' value='".'Check'."'".on_help("PRAGMA integrity_check")."> ":(JUSH=="pgsql"?$Ol.$rh:(JUSH=="mssql"?"<input type='submit' name='check' value='".'Check'."'".on_help("DBCC CHECKTABLE")."> ":(JUSH=="sql"?"<input type='submit' value='".'Analyze'."'".on_help("ANALYZE TABLE")."> ".$rh."<input type='submit' name='check' value='".'Check'."'".on_help("CHECK TABLE")."> "."<input type='submit' name='repair' value='".'Repair'."'".on_help("REPAIR TABLE")."> ":"")))).(function_exists('Adminer\truncate_tables')?"<input type='submit' name='truncate' value='".'Truncate'."'".confirm().on_help(JUSH=="sqlite"?"DELETE":"TRUNCATE".(JUSH=="pgsql"?"":" TABLE"))."> ":"").(function_exists('Adminer\drop_tables')?"<input type='submit' name='drop' value='".'Drop'."'".confirm().on_help("DROP TABLE").">":"");echo($xi?"<div class='footer'><div>\n<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>$xi\n</div></fieldset>\n":"");$g=(support("scheme")?adminer()->schemas():adminer()->databases());if(count($g)!=1&&function_exists('Adminer\move_tables')){echo"<fieldset><legend>".'Move to another database'." <span id='selected3'></span></legend><div>";$h=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($g?html_select("target",$g,$h):'<input name="target" value="'.h($h).'" autocapitalize="off">'),"</label> <input type='submit' name='move' value='".'Move'."'>",(support("copy")?" <input type='submit' name='copy' value='".'Copy'."'> ".checkbox("overwrite",1,$_POST["overwrite"],'overwrite'):""),"</div></fieldset>\n";}echo"<input type='hidden' name='all' value=''".on('click','countTables',$T).">\n",input_token(),"</div></div>\n";}echo"</form>\n",script("tableCheck();");}echo(function_exists('Adminer\alter_table')?"<p class='links hover'><a href='".h(ME)."create='>".'Create table'."</a>\n":''),(support("view")?"<a href='".h(ME)."view='>".'Create view'."</a>\n":""),"</div>\n";if(support("routine")){echo"<div>\n","<h3 id='routines'>".'Routines'."</h3>\n";$kj=routines();if($kj){echo"<table class='odds'>\n",'<thead><tr><th>'.'Name'.'<th>'.'Type'.'<th>'.'Return type'."<td class='hover'><tbody>\n";foreach($kj
as$K){$B=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".url_escape($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').url_escape($K["SPECIFIC_NAME"]).$B).'" title="'.'Call'.'">'.h($K["ROUTINE_NAME"]).'</a>','<td>'.h($K["ROUTINE_TYPE"]),'<td>'.h($K["DTD_IDENTIFIER"]),'<td class="hover"><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').url_escape($K["SPECIFIC_NAME"]).$B).'">'.'Alter'."</a>";}echo"</table>\n";}echo'<p class="links hover">'.(support("procedure")?'<a href="'.h(ME).'procedure=">'.'Create procedure'.'</a>':'').'<a href="'.h(ME).'function=">'.'Create function'."</a>\n","</div>\n";}if(support("event")){echo"<div>\n","<h3 id='events'>".'Events'."</h3>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".'Name'."<th>".'Schedule'."<th>".'Start'."<th>".'End'."<td class='hover'><tbody>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?'At given time'."<td>".h($K["Execute at"]):'Every'." ".h($K["Interval value"])." ".h($K["Interval field"])."<td>".h($K["Starts"])),"<td>".h($K["Ends"]),'<td class="hover"><a href="'.h(ME).'event='.url_escape($K["Name"]).'">'.'Alter'.'</a>';echo"</table>\n";$bd=get_val("SELECT @@event_scheduler");if($bd&&$bd!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($bd)."\n";}echo'<p class="links hover"><a href="'.h(ME).'event=">'.'Create event'."</a>\n","</div>\n";}}}}page_footer();