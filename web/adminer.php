<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 6.2.0
*/namespace
Adminer;
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth_user.php';

if (!isset($_SESSION['admin'])){
    http_response_code(401);
    header('Location: /');
    exit;
}
if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];const
VERSION="6.2.0";error_reporting(24575);set_error_handler(function($ad,$dd){return!!preg_match('~^Undefined (array key|offset|index)~',$dd);},E_WARNING|E_NOTICE);$Fd=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($Fd||ini_get("filter.default_flags")){foreach(array('_GET','_POST','_COOKIE','_SERVER')as$X){$Kl=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($Kl)$$X=$Kl;}}$_COOKIE=array_filter($_COOKIE,'is_scalar');if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");function
connection($f=null){return($f?:Db::$instance);}function
adminer(){return
Adminer::$instance;}function
driver(){return
Driver::$instance;}function
connect(){$Nb=adminer()->credentials();$J=Driver::connect($Nb[0],$Nb[1],$Nb[2]);return(is_object($J)?$J:null);}function
idf_unescape($t){if(!preg_match('~^[`\'"[]~',$t))return$t;$Cf=substr($t,-1);return
str_replace($Cf.$Cf,$Cf,substr($t,1,-1));}function
q($Q){return
connection()->quote($Q);}function
idx($za,$w,$i=null){return($za&&array_key_exists($w,$za)?$za[$w]:$i);}function
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
true;$U=$k["type"];$Ej=$X["val"];$Pa='binary$|bytea|raw|image|bfile|^vector$'.(JUSH=="mssql"?'|^timestamp$':'|^bit').(JUSH=="oracle"?'|^blob|^long|rowid':'');if(preg_match("~$Pa~",$U))return
false;if(preg_match(number_type(),$U)){$dh='-?\d+(\.\d+)?';$Fg='';if(preg_match('~IN$~',$X["op"])){$Ej=preg_replace('~^\s*\(\s*(.*?)\s*\)\s*$~s','\1',$Ej);$Fg="( *, *$dh)*";}elseif($X["op"]=="BETWEEN")$Fg="\\s+AND\\s+$dh";return(bool)preg_match('~^'.$dh.$Fg.'$~i',$Ej);}if(preg_match('~^(small)?date|^timestamp~',$U))return(bool)preg_match('~^\d+-\d+-\d+~',$Ej);if(preg_match('~^time~',$U))return(bool)preg_match('~^\d+:\d+~',$Ej);if(preg_match('~^bool~',$U)||(JUSH=="mssql"&&$U=="bit"))return(bool)preg_match('~^(t|f|true|false|[01])$~i',$Ej);return
true;}function
remove_slashes(array$gm,$Fd=false){$J=array();foreach($gm
as$w=>$X)$J[stripslashes($w)]=(is_array($X)?remove_slashes($X,$Fd):($Fd?$X:stripslashes($X)));return$J;}function
bracket_escape($t,$Ia=false){static$tl=array(':'=>':1',']'=>':2','['=>':3','"'=>':4','='=>':5');return
strtr($t,($Ia?array_flip($tl):$tl));}function
url_escape($Q){static$tl=array();if(!$tl){$tl=array(' '=>'+');foreach(str_split("\"'<>#%&+=?".ini_get("arg_separator.input"))as$bb)$tl[$bb]=sprintf('%%%02X',ord($bb));for($r=0;$r<256;$r++){if($r<32||$r>126)$tl[chr($r)]=sprintf('%%%02X',$r);}}return
strtr((string)$Q,$tl);}function
min_version($jm,$Xf="",$f=null){$f=connection($f);$Tj=$f->server_info;if($Xf&&preg_match('~([\d.]+)-MariaDB~',$Tj,$A)){$Tj=$A[1];$jm=$Xf;}return$jm&&version_compare($Tj,$jm)>=0;}function
charset(Db$e){return(min_version("5.5.3",0,$e)?"utf8mb4":"utf8");}function
ini_set($zh,$Y){return(function_exists('ini_set')?\ini_set($zh,$Y):false);}function
ini_bool($Xe){$X=ini_get($Xe);return(preg_match('~^(on|true|yes)$~i',$X)||(int)$X);}function
ini_bytes($Xe){$X=ini_get($Xe);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
max_input_vars($K,$Mh){$ag=(int)ini_get("max_input_vars");return($ag?(int)floor(($ag-$Mh)/$K):0);}function
max_input_vars_error(){$Xe="max_input_vars";return
sprintf('Maximum number of allowed fields exceeded. Please increase %s.',"<b>$Xe = ".ini_get($Xe)."</b>");}function
sid(){static$J;if($J===null)$J=(SID&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
set_password($im,$O,$V,$F){$_SESSION["pwds"][$im][$O][$V]=($_COOKIE["adminer_key"]&&is_string($F)?array(encrypt_string($F,$_COOKIE["adminer_key"])):$F);}function
get_password(){$J=get_session("pwds");if(is_array($J))$J=($_COOKIE["adminer_key"]?decrypt_string($J[0],$_COOKIE["adminer_key"]):false);return$J;}function
get_val($H,$k=0,$_b=null){$_b=connection($_b);$I=$_b->query($H);if(!is_object($I))return
false;$K=$I->fetch_row();return($K?$K[$k]:false);}function
get_vals($H,$c=0){$J=array();$I=connection()->query($H);if(is_object($I)){while($K=$I->fetch_row())$J[]=$K[$c];}return$J;}function
get_key_vals($H,$f=null,$Wj=true){$f=connection($f);$J=array();$I=$f->query($H);if(is_object($I)){while($K=$I->fetch_row()){if($Wj)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$f=null,$j="<p class='error'>"){$_b=connection($f);$J=array();$I=$_b->query($H);if(is_object($I)){while($K=$I->fetch_assoc())$J[]=$K;}elseif(!$I&&!$f&&$j&&(defined('Adminer\PAGE_HEADER')||$j=="-- "))echo$j.adminer()->error()."\n";return$J;}function
unique_array($K,array$v){foreach($v
as$u){if(preg_match("~^(PRIMARY|UNIQUE)$~",$u["type"])&&!$u["partial"]&&!$u["invalid"]){$J=array();foreach($u["columns"]as$w){if(!isset($K[$w]))continue
2;$J[$w]=$K[$w];}return$J;}}}function
where_function($Xd,$c,array$k){if($Xd=="md5")return
driver()->md5($c,$k)?:$c;return(in_array($Xd,driver()->functions)||in_array($Xd,driver()->grouping)?apply_sql_function($Xd,$c):$c);}function
where(array$Z,array$l=array()){$J=array();foreach((array)$Z["where"]as$w=>$X){$w=bracket_escape($w,true);$c=idf_escape($w);$k=idx($l,$w,array());$_d=$k["type"];$kf=$k&&(is_blob($k)||preg_match('~binary~',$_d));$J[]=$c.($kf&&!is_utf8($X)?" = ".driver()->quoteBinary($X):(JUSH=="sql"&&$_d=="json"?" = CAST(".q($X)." AS JSON)":(JUSH=="pgsql"&&preg_match('~^jsonb?$~',$k["full_type"])?"::jsonb = ".q($X)."::jsonb":(JUSH=="pgsql"&&preg_match('~\.~',$_d)?"::text = ".q($X):(JUSH=="sql"&&is_numeric($X)&&preg_match('~\.~',$X)?" LIKE ".q($X):(JUSH=="mssql"&&strpos($_d,"datetime")===false?" LIKE ".q(preg_replace('~[_%[]~','[\0]',$X)):" = ".unconvert_field($k,q($X))))))));if(JUSH=="sql"&&preg_match('~char|text~',$_d)&&preg_match("~[^ -@]~",$X))$J[]="$c = ".q($X)." COLLATE ".charset(connection())."_bin";}foreach((array)$Z["null"]as$w)$J[]=idf_escape($w)." IS NULL";foreach((array)$Z["col"]as$r=>$ob){$X=idx($Z["val"],$r);$J[]=where_function(idx($Z["fun"],$r),idf_escape($ob),idx($l,$ob,array())).($X!==null?" = ".q($X):" IS NULL");}return
implode(" AND ",$J);}function
where_columns(array$l){$J=array();foreach((array)$_GET["null"]as$w)$J[$w]=true;foreach(array_keys((array)$_GET["where"])as$w)$J[bracket_escape($w,true)]=true;foreach((array)$_GET["col"]as$ob)$J[$ob]=true;return
array_intersect_key($J,$l);}function
where_check($X,array$l=array()){parse_str($X,$eb);remove_slashes(array(&$eb));return
where($eb,$l);}function
where_link($r,$c,$Y,$wh="="){$th=($Y!==null?$wh:"IS NULL");return"&where[$r][col]=".url_escape($c).($th!=first(adminer()->operators())?"&where[$r][op]=".url_escape($th):"")."&where[$r][val]=".url_escape($Y);}function
convert_fields(array$d,array$l,array$N=array()){$J="";foreach($d
as$w=>$X){if($N&&!in_array(idf_escape($w),$N))continue;$_a=convert_field($l[$w]);if($_a)$J
.=", $_a AS ".idf_escape($w);}return$J;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),array(";"=>"%3B",","=>"%2C"));}function
cookie($B,$Y,$Mf=2592000){header("Set-Cookie: $B=".rawurlencode($Y).($Mf?"; expires=".gmdate("D, d M Y H:i:s",time()+$Mf)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"").($B=="adminer_import"?"":"; HttpOnly")."; SameSite=lax",false);}function
php_error_text($j){return(ini_bool("html_errors")?html_entity_decode(strip_tags($j)):$j);}function
get_url($Tl,$Eb){$http_response_header=null;$cd=array();set_error_handler(function($ad,$j)use(&$cd){$cd[]=preg_replace('~^file_get_contents\([^)]*\)( \[[^]]*\])?:\s*~','',php_error_text($j));return
true;});$J=file_get_contents($Tl,false,$Eb);restore_error_handler();$te=(function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header);return
array($J,(preg_match('~^HTTP/[\d.]+ (\d+)~',idx($te,0,''),$A)?$A[1]:''),(array)$te,($J===false?implode("\n",$cd):''),);}function
json_decode_exact($sf){$sf=preg_replace('~"(\\\\u0001(?:[^"\\\\]|\\\\.)*+")|"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)~','"\\\\u0001$1',$sf);return
json_decode(preg_replace('~"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)|-?\d[-+.\deE]*+~','"\\\\u0001$0"',$sf));}function
json_scalar($X){return(is_string($X)&&substr($X,0,1)=="\1"?substr($X,1):$X);}function
json_encode_exact($X,$Id=0){return
preg_replace('~"\\\\u0001(-?\d[^"\\\\]*)"|(")\\\\u0001(\\\\u0001(?:[^"\\\\]|\\\\.)*+")|"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)~','$1$2$3',json_encode($X,$Id));}function
get_settings($Hb){parse_str($_COOKIE[$Hb],$Xj);return$Xj;}function
get_setting($w,$Hb="adminer_settings",$i=null){return
idx(get_settings($Hb),$w,$i);}function
save_settings(array$Xj,$Hb="adminer_settings"){$Y=http_build_query($Xj+get_settings($Hb));cookie($Hb,$Y);$_COOKIE[$Hb]=$Y;}function
restart_session(){if(!ini_bool("session.use_cookies")&&(!function_exists('session_status')||session_status()==PHP_SESSION_NONE))session_start();}function
stop_session($Ld=false){$Wl=ini_bool("session.use_cookies");if(!$Wl||$Ld){session_write_close();if($Wl&&ini_set("session.use_cookies",'0')===false)session_start();}}function&get_session($w){return$_SESSION[$w][DRIVER][SERVER][$_GET["username"]];}function
set_session($w,$X){$_SESSION[$w][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($im,$O,$V,$h=null){$Sl=remove_from_uri(implode("|",array_keys(SqlDriver::$drivers))."|username|ext|".($h!==null?"db|":"").($im=='mssql'||$im=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$Sl,$A);return"$A[1]?".(sid()?SID."&":"").($_GET["ext"]?"ext=".url_escape($_GET["ext"])."&":"").($im!="server"||$O!=""?url_escape($im)."=".url_escape($O)."&":"")."username=".url_escape($V).($h!=""?"&db=".url_escape($h):"").($A[2]?"&$A[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($_,$qg=null){if($qg!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($_!==null?$_:$_SERVER["REQUEST_URI"]))][]=$qg;}if($_!==null){if($_=="")$_=".";header("Location: $_");exit;}}function
query_redirect($H,$_,$qg,$Wi=true,$kd=true,$vd=false,$gl=""){if($kd){$qk=microtime(true);$vd=!connection()->query($H);$gl=format_time($qk);}$kk=($H?adminer()->messageQuery($H,$gl,$vd):"");if($vd){adminer()->error
.=adminer()->error().$kk.script("messagesPrint();")."<br>";return
false;}if($Wi)redirect($_,$qg.$kk);return
true;}class
Queries{static$queries=array();static$start=0;}function
remember_query($H){if(!Queries::$start)Queries::$start=microtime(true);Queries::$queries[]=(driver()->delimiter!=';'?$H:(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";");}function
queries($H){remember_query($H);return
connection()->query($H);}function
apply_queries($H,array$T,$ed='Adminer\table'){foreach($T
as$R){if(!queries("$H ".$ed($R)))return
false;}return
true;}function
queries_redirect($_,$qg,$Wi){$Oi=implode("\n",Queries::$queries);$gl=format_time(Queries::$start);return
query_redirect($Oi,$_,$qg,$Wi,false,!$Wi,$gl);}function
format_time($qk){return
sprintf('%.3f s',max(0,microtime(true)-$qk));}function
relative_uri($Sl=''){return
preg_replace_callback('~^[^?]*~',function($A){return
str_replace(":","%3A",$A[0]);},preg_replace('~^[^?]*/([^?]*)~','\1',($Sl?:$_SERVER["REQUEST_URI"])));}function
remove_from_uri($Rh=""){return
substr(preg_replace("~(?<=[?&])($Rh".(SID?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_files($B,$cc=false){$Bd=$_FILES[$B];if(!$Bd)return
null;foreach($Bd
as$w=>$X)$Bd[$w]=(array)$X;$J=array();foreach($Bd["error"]as$w=>$j){if($j)return$j;$m=$Bd["name"][$w];$ol=$Bd["tmp_name"][$w];$Cb=file_get_contents($cc&&preg_match('~\.gz$~',$m)?"compress.zlib://$ol":$ol);if($cc){$qk=substr($Cb,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$qk))$Cb=iconv("utf-16","utf-8",$Cb);elseif($qk=="\xEF\xBB\xBF")$Cb=substr($Cb,3);}$J[]=array($m,$Cb);}return$J;}function
get_file($w,$cc=false,$kc=""){$Ed=get_files($w,$cc);if(!is_array($Ed))return$Ed;$J='';foreach($Ed
as$Bd){$Cb=$Bd[1];$J
.=$Cb;if($kc)$J
.=(preg_match("($kc\\s*\$)",$Cb)?"":$kc)."\n\n";}return$J;}function
upload_error($j){$ig=($j==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($j?'Unable to upload a file.'.($ig?" ".sprintf('Maximum allowed file size is %sB.',$ig):""):'File does not exist.');}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
utf8_length($X){return
strlen(preg_replace('~[\x80-\xBF]~','',$X));}function
format_number($X){preg_match('~^#+([^#0]+)(?:(#+)\1)?(#*0)$~u','#,##0',$A);$bk=strlen($A[3]);$J=number_format($X,0,".","");$J=preg_replace('~\B(?=(\d{'.(strlen($A[2])?:$bk).'})*\d{'.$bk.'}$)~',$A[1],$J);return
strtr($J,preg_split('~~u','0123456789',-1,PREG_SPLIT_NO_EMPTY));}function
format_status(array$S,$w){$X=idx($S,$w,'?');if(!is_numeric($X))return
h($X);if($X<0)return'?';$wa=($w=="Rows"&&(JUSH=="sqlite"||$S["Engine"]==(JUSH=="pgsql"?"table":"InnoDB")));$X=in_array($w,array("Data_length","Index_length","Data_free"))?adminer()->formatSizeValue($X):format_number($X);return($wa?"~ ":"").$X;}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($R,$wd=false){$J=table_status($R,$wd);return($J?reset($J):array("Name"=>$R));}function
column_foreign_keys($R){$J=array();foreach(adminer()->foreignKeys($R)as$n){foreach($n["source"]as$X)$J[$X][]=$n;}return$J;}function
fields_from_edit(){$J=array();foreach((array)$_POST["field_keys"]as$w=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$w];$_POST["fields"][$X]=$_POST["field_vals"][$w];}}foreach((array)$_POST["fields"]as$w=>$X){$B=bracket_escape($w,true);$J[$B]=array("field"=>$B,"full_type"=>"","type"=>"","privileges"=>array("insert"=>1,"update"=>1,"where"=>1,"order"=>1),"null"=>true,"auto_increment"=>($B==driver()->primary),);}return$J;}function
dump_headers($Ge,$Ig=false){$J=adminer()->dumpHeaders($Ge,$Ig);$Oh=$_POST["output"];if($Oh!="text"||$J=="tar"){$yb=($Oh!="text"&&$Oh!="file"&&preg_match('~^[0-9a-z]+$~',$Oh)?".$Oh":"");header("Content-Disposition: attachment; filename=".adminer()->dumpFilename($Ge).".$J$yb");}session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$J;}function
dump_csv(array$K){$Al=$_POST["format"]=="tsv";foreach($K
as$w=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($Al?'\t':'[,;]|^$').'~',$X))$K[$w]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($Al?"\t":";")),$K)."\r\n";}function
parse_csv($Qb,$Nj){$J=array();preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$Qb,$Yf);foreach($Yf[0]as$K){preg_match_all("~((?>\"[^\"]*\")+|[^$Nj]*)$Nj~",$K.$Nj,$Zf);$J[]=$Zf[1];}return$J;}function
csv_value($X){return(preg_match('~^".*"$~s',$X)?idf_unescape($X):$X);}function
apply_sql_function($p,$c){return($p?($p=="unixepoch"?"DATETIME($c, '$p')":($p=="count distinct"?"COUNT(DISTINCT ":strtoupper("$p("))."$c)"):$c);}function
get_temp_dir(){return
ini_get("upload_tmp_dir")?:sys_get_temp_dir();}function
file_open_lock($m){if(is_link($m))return;$o=@fopen($m,"c+");if(!$o)return;@chmod($m,0660);if(!flock($o,LOCK_EX)){fclose($o);return;}return$o;}function
file_write_unlock($o,$Ub){rewind($o);fwrite($o,$Ub);ftruncate($o,strlen($Ub));file_unlock($o);}function
file_unlock($o){flock($o,LOCK_UN);fclose($o);}function
first(array$za){return
reset($za);}function
password_file($Kb){$m=get_temp_dir()."/adminer.key";if(!$Kb&&!file_exists($m))return'';$o=file_open_lock($m);if(!$o)return'';$J=stream_get_contents($o);if(!$J){$J=rand_string();file_write_unlock($o,$J);}else
file_unlock($o);return$J;}function
rand_string(){return(function_exists('random_bytes')?bin2hex(random_bytes(16)):md5(uniqid(strval(mt_rand()),true)));}function
select_value($X,$z,array$k,$el,array$li=array()){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$wf=array();foreach($X
as$W)$wf+=array_fill_keys(array_keys($W),null);foreach(array_keys($wf)as$uf)$J
.="<th>".h($uf);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($wf,$W)as$cm)$J
.="<td>".select_value($cm,$z,$k,$el,$li);}}else{foreach($X
as$uf=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($uf):"")."<td>".select_value($W,$z,$k,$el,$li);}return"<table>$J</table>";}if(!$z)$z=adminer()->selectLink($X,$k);if($z===null){if(is_mail($X))$z="mailto:$X";if(is_url($X))$z=$X;}$X=driver()->value($X,$k);$J=adminer()->editVal($X,$k);if($J!==null){if(!is_utf8($J))$J="\0";elseif($el!=""&&is_shortable($k))$J=shorten_utf8($J,max(0,+$el),"",$li);else$J=highlight_matches($J,$li);}return
adminer()->selectVal($J,$z,$k,$X);}function
is_blob(array$k){return
preg_match('~blob|bytea|raw|file'.(JUSH=="mssql"?'|binary|image':'').'~',$k["type"])&&!in_array($k["type"],idx(driver()->structuredTypes(),'User types',array()));}function
is_identity_always(array$k){return$k["auto_increment"]&&(JUSH=="mssql"||$k["default"]=="GENERATED ALWAYS AS IDENTITY");}function
is_mail($Pc){$Ba='[-a-z0-9!#$%&\'*+/=?^_`{|}~]';$Bc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';$ki="$Ba+(\\.$Ba+)*@($Bc?\\.)+$Bc";return
is_string($Pc)&&preg_match("(^$ki(,\\s*$ki)*\$)i",$Pc);}function
is_url($Q){$Bc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';return
preg_match("~^((https?):)?//($Bc?\\.)+$Bc(:\\d+)?(/\\S*)?(\\?\\S*)?(#\\S*)?\$~iD",$Q);}function
is_ipv6($ia){$q='[\da-f]{1,4}';$jf='\d{1,3}(\.\d{1,3}){3}';return(bool)preg_match("~^(($q:){7}$q|($q:){6}$jf|(($q:)*$q)?::(($q:)*($q|$jf))?)$~iD",$ia);}function
is_shortable(array$k){return!preg_match('~'.number_type().'|date|time|year~',$k["type"]);}function
url_host($Ce){return(strpos($Ce,":")!==false?"[$Ce]":$Ce);}function
server_parts(array$ei){return
array("scheme"=>(string)$ei["scheme"],"host"=>(string)$ei["host"],"port"=>(string)$ei["port"],"socket"=>(string)$ei["socket"],"path"=>(string)$ei["path"],);}function
parse_server($O){if($O=="")return
server_parts(array());if($O[0]==":"&&!is_ipv6($O)){$jj=substr($O,1);if(preg_match('~^\d+$~D',$jj))return
server_parts(array("port"=>$jj));return(preg_match('~^/[-\w.:/]*$~D',$jj)?server_parts(array("socket"=>$jj)):null);}$Cj="";if(preg_match('~^([-+.\w]+)://~',$O,$A)){$Cj=strtolower($A[1]);$O=substr($O,strlen($A[0]));}if(preg_match('~^\[(.+)](:(\d+))?(/[-\w./]*)?$~D',$O,$A))return(is_ipv6($A[1])?server_parts(array("scheme"=>$Cj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);if(is_ipv6($O))return
server_parts(array("scheme"=>$Cj,"host"=>$O));if(preg_match('~^(/[-\w./]*)(:(\d+))?$~D',$O,$A))return
server_parts(array("scheme"=>$Cj,"host"=>$A[1],"port"=>$A[3]));return(preg_match('~^([-\w.]*)(:(\d+))?(/[-\w./]*)?$~D',$O,$A)?server_parts(array("scheme"=>$Cj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);}function
count_rows($R,array$Z,$lf,array$q){$H=" FROM ".table($R).($Z?" WHERE ".implode(" AND ",$Z):"");return($lf&&(JUSH=="sql"||count($q)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$q).")$H":"SELECT COUNT(*)".($lf?" FROM (SELECT 1$H GROUP BY ".implode(", ",$q).") x":$H));}function
slow_query($H){$h=adminer()->database();$hl=adminer()->queryTimeout();$ck=driver()->slowQuery($H,$hl);$f=null;if(!$ck&&support("kill")){$f=connect();if($f&&($h==""||$f->select_db($h))){$xf=number(get_val(connection_id(),0,$f));echo
script("const timeout = setTimeout(() => { ajax('".js_escape(ME)."script=kill', function () {}, 'kill=$xf&token=".get_token()."'); }, 1000 * $hl);");}}ob_flush();flush();$J=@get_key_vals(($ck?:$H),$f,false);if($f){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$Ti=rand(1,1e6);return($Ti^$_SESSION["token"]).":$Ti";}function
verify_token(){return true;}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Q,$qc=""){$sa=array_flip(str_split(compress_alphabet()));$x=strlen($Q);$em=($x?13*($x-1)/2-$sa[$Q[0]]:0);$Pa="";$jj=0;$kj=0;for($r=1;$r<$x;$r+=2){$jj=($jj<<13)+$sa[$Q[$r]]*93+$sa[$Q[$r+1]];$kj+=13;while($kj>=8&&$em>=8){$kj-=8;$em-=8;$Pa
.=chr($jj>>$kj);$jj&=(1<<$kj)-1;}}if($Pa=="")return"";if($qc!=""&&function_exists('inflate_init'))return
inflate_add(inflate_init(ZLIB_ENCODING_RAW,array('dictionary'=>$qc)),$Pa,ZLIB_FINISH);return($qc==""&&function_exists('gzinflate')?gzinflate($Pa):inflate($Pa,$qc));}function
inflate($Pa,$qc=""){$Jf=array(3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258);$Kf=array(0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0);$uc=array(1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577);$wc=array(0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13);$J=$qc;$G=0;do{$Gd=inflate_bits($Pa,$G,1);$U=inflate_bits($Pa,$G,2);if(!$U){$G=($G+7)&~7;$x=inflate_bits($Pa,$G,16);$G+=16;$J
.=substr($Pa,$G>>3,$x);$G+=$x<<3;}else{if($U==1){$Sf=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$xc=array_fill(0,30,5);}else{$Rf=inflate_bits($Pa,$G,5)+257;$vc=inflate_bits($Pa,$G,5)+1;$D=array(16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15);$xg=array_fill(0,19,0);$wg=inflate_bits($Pa,$G,4)+4;for($r=0;$r<$wg;$r++)$xg[$D[$r]]=inflate_bits($Pa,$G,3);$yg=inflate_table($xg);$Lf=array();while(count($Lf)<$Rf+$vc){$Fk=inflate_symbol($Pa,$G,$yg);if($Fk==16)$Lf=array_merge($Lf,array_fill(0,inflate_bits($Pa,$G,2)+3,end($Lf)));elseif($Fk==17)$Lf=array_merge($Lf,array_fill(0,inflate_bits($Pa,$G,3)+3,0));elseif($Fk==18)$Lf=array_merge($Lf,array_fill(0,inflate_bits($Pa,$G,7)+11,0));else$Lf[]=$Fk;}$Sf=array_slice($Lf,0,$Rf);$xc=array_slice($Lf,$Rf);}$Tf=inflate_table($Sf);$zc=inflate_table($xc);while(($Fk=inflate_symbol($Pa,$G,$Tf))!=256){if($Fk<256)$J
.=chr($Fk);else{$x=$Jf[$Fk-257]+inflate_bits($Pa,$G,$Kf[$Fk-257]);$yc=inflate_symbol($Pa,$G,$zc);$kh=strlen($J)-$uc[$yc]-inflate_bits($Pa,$G,$wc[$yc]);for($r=0;$r<$x;$r++)$J
.=$J[$kh+$r];}}}}while(!$Gd);return($qc==""?$J:substr($J,strlen($qc)));}function
inflate_bits($Pa,&$G,$Jb){$J=0;for($r=0;$r<$Jb;$r++){$J+=((ord($Pa[$G>>3])>>($G&7))&1)<<$r;$G++;}return$J;}function
inflate_table(array$Lf){$R=array();$nb=0;for($Qa=1;$Qa<=max($Lf);$Qa++){foreach($Lf
as$Fk=>$x){if($x==$Qa){$R[$Qa][$nb]=$Fk;$nb++;}}$nb<<=1;}return$R;}function
inflate_symbol($Pa,&$G,array$R){$nb=0;$Qa=0;do{$nb=($nb<<1)+inflate_bits($Pa,$G,1);$Qa++;}while(!isset($R[$Qa][$nb]));return$R[$Qa][$nb];}function
script($hk,$sl="\n"){return"<script".nonce().">$hk</script>$sl";}function
script_src($Tl,$gc=false){return"<script src='".h($Tl)."'".nonce().($gc?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
on($fd,$le,$xa=null){$ya=array();foreach(array_slice(func_get_args(),2)as$X)$ya[]=json_encode($X,256);return" data-on$fd='".str_replace(array('&','<',"'"),array('&amp;','&lt;','&#039;'),"$le(".implode(", ",$ya).")")."'";}function
input_hidden($B,$Y=""){return"<input type='hidden' name='".h($B)."' value='".h($Y)."'>\n";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Q){return
str_replace(array('&','<','"',"'","\0"),array('&amp;','&lt;','&quot;','&#039;','&#0;'),$Q);}function
nl_br($Q){return
str_replace("\n","<br>",$Q);}function
checkbox($B,$Y,$gb,$zf="",$b="",$mb="",$Af=""){$J="<input type='checkbox' name='$B' value='".h($Y)."'".($gb?" checked":"").($zf==""&&$mb?" class='$mb'":"").($Af?" aria-labelledby='$Af'":"").$b.">";return($zf!=""?"<label".($mb?" class='$mb'":"").">$J".h($zf)."</label>":$J);}function
optionlist($C,$Kj=null,$Xl=false){$J="";foreach($C
as$uf=>$W){$Bh=array($uf=>$W);if(is_array($W)){$J
.='<optgroup label="'.h($uf).'">';$Bh=$W;}foreach($Bh
as$w=>$X)$J
.='<option'.($Xl||is_string($w)?' value="'.h($w).'"':'').($Kj!==null&&($Xl||is_string($w)?(string)$w:$X)===$Kj?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
group_system(array$Qg,$Bj=false){$J=array();$Gk=array();foreach($Qg
as$B){if($Bj?driver()->isSystem(DB,$B):driver()->isSystem($B))$Gk[]=$B;else$J[]=$B;}if($Gk)$J[sprintf('System%s','')]=$Gk;return$J;}function
html_select($B,array$C,$Y="",$b="",$Af=""){static$zf=0;$_f="";if(!$Af&&substr($C[""],0,1)=="("){$zf++;$Af="label-$zf";$_f="<option value='' id='$Af'>".h($C[""]);unset($C[""]);}return"<select name='".h($B)."'".($Af?" aria-labelledby='$Af'":"")."$b>".$_f.optionlist($C,$Y)."</select>";}function
html_radios($B,array$C,$Y="",$Nj=""){$J="";foreach($C
as$w=>$X)$J
.="<label><input type='radio' name='".h($B)."' value='".h($w)."'".($w==$Y?" checked":"").">".h($X)."</label>$Nj";return$J;}function
confirm($qg=""){return
on('click','confirmClick',$qg?:'Are you sure?');}function
print_fieldset($s,$If,$mm=false){echo"<fieldset><legend>","<a href='#fieldset-$s' class='toggle'>$If</a>","</legend>","<div id='fieldset-$s'".($mm?"":" class='hidden'").">\n";}function
bold($Ra,$mb=""){return($Ra?" class='active $mb'":($mb?" class='$mb'":""));}function
js_escape($Q){return
str_replace("<","\\x3C",addcslashes($Q,"\r\n'\\"));}function
js_escape_re($Q){return
addcslashes(preg_quote($Q,"/"),"\r\n");}function
pagination_href($E){return
remove_from_uri("page|next").($E?"&page=$E".($_GET["next"]!=""?"&next=".url_escape($_GET["next"]):""):"");}function
pagination($E,$Rb){return" ".($E==$Rb?($E?"<b>".($E+1)."</b>":$E+1):'<a href="'.h(pagination_href($E)).'">'.($E+1)."</a>");}function
hidden_fields(array$Ji,array$Ke=array(),$Ai=''){$J=false;foreach($Ji
as$w=>$X){if(!in_array($w,$Ke)){if(is_array($X))hidden_fields($X,array(),$w);else{$J=true;echo
input_hidden(($Ai?$Ai."[$w]":$w),$X);}}}return$J;}function
hidden_fields_get(){echo(sid()?input_hidden(session_name(),session_id()):''),($_GET["ext"]?input_hidden("ext",$_GET["ext"]):""),(isset($_GET[DRIVER])?input_hidden(DRIVER,SERVER):""),input_hidden("username",$_GET["username"]);}function
on_upload_progress(&$Rl){$Rl=(ini_bool("session.upload_progress.enabled")&&ini_get("session.upload_progress.name")?rand_string():"");return($Rl?on('submit','uploadProgress',ME."upload=$Rl",SESSION_NAME."=$Rl"):"");}function
file_input($b,$jj=""){$cg="max_file_uploads";$dg=ini_get($cg);$ig="upload_max_filesize";$jg=ini_bytes($ig);$xi=ini_bytes("post_max_size");if($xi&&$xi<$jg){$ig="post_max_size";$jg=$xi;}$kg=ini_get($ig);return(ini_bool("file_uploads")?"<input type='file'$b".on('change','fileChange',(int)$dg,sprintf('Increase %s.',"$cg = $dg"),$jg,sprintf('Increase %s.',"$ig = $kg")).">$jj":'File uploads are disabled.');}function
enum_input($U,$b,array$k,$Y,$Sc=""){preg_match_all("~".driver()->enumLength."~",$k["length"],$Yf);$Ai=($k["type"]=="enum"?"val-":"");$gb=(is_array($Y)?in_array("null",$Y):$Y===null);$J=($k["null"]&&$Ai?"<label><input type='$U'$b value='null'".($gb?" checked":"")."><i>$Sc</i></label>":"");foreach($Yf[0]as$X){$X=stripcslashes(idf_unescape($X));$gb=(is_array($Y)?in_array($Ai.$X,$Y):$Y===$X);$J
.=" <label><input type='$U'$b value='".h($Ai.$X)."'".($gb?' checked':'').'>'.h(adminer()->editVal($X,$k)).'</label>';}return$J;}function
input(array$k,$Y,$p,$Ga=false,$Ol=false){$B=h(bracket_escape($k["field"]));echo"<td class='function'>";$Zc=driver()->enumLength($k);if($Zc){$k["type"]="enum";$k["length"]=$Zc;}$C=($k["type"]=="enum"||$k["type"]=="set");if(is_array($Y)&&!$p&&!$C)$p="json";$sf=($p=="json"||preg_match('~^jsonb?$~',$k["full_type"]));if($sf&&$Y!=''&&(JUSH!="pgsql"||$k["type"]!="json")&&(is_array($Y)||!$_POST["save"]))$Y=(is_array($Y)?json_encode($Y,128|64|256):json_encode_exact(json_decode_exact($Y),128|64|256));$ij=($Ol&&is_identity_always($k));if($ij&&!$_POST["save"])$p=null;$Yd=(isset($_GET["select"])||$ij?array("orig"=>'original'):array())+adminer()->editFunctions($k);$b=" name='fields[$B]".($C?"[]":"")."'".($Ga?" autofocus":"");echo
driver()->unconvertFunction($k)." ";$R=$_GET["edit"]?:$_GET["select"];if($k["type"]=="enum")echo
h($Yd[""])."<td>".adminer()->editInput($R,$k,$b,$Y);else{$ne=(in_array($p,$Yd)||isset($Yd[$p]));$Hd=0;foreach($Yd
as$w=>$X){if($w===""||!$X)break;$Hd++;}echo(count($Yd)>1?"<select name='function[$B]'".on('change','functionChange').on_help_value('^SQL$').">".optionlist($Yd,$p===null||$ne?$p:"")."</select>":h(reset($Yd)))."<td".($Hd&&count($Yd)>1?on('input','skipOriginal',$Hd):"").">";$Ze=adminer()->editInput($R,$k,$b,$Y);if($Ze!="")echo$Ze;elseif(preg_match('~bool~',$k["type"]))echo"<input type='hidden'$b value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked":"")."$b value='1'>";elseif($k["type"]=="set")echo
enum_input("checkbox",$b,$k,(is_string($Y)?explode(",",$Y):$Y));elseif(is_blob($k)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$B'>";elseif($sf)echo"<textarea$b cols='50' rows='12' class='jush-json'>\n".h($Y).'</textarea>';elseif(($dl=preg_match('~text|lob|memo~i',$k["type"]))||preg_match("~\n~",$Y)){if($dl&&JUSH!="sqlite")$b
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$b
.=" cols='30' rows='$L'";}echo"<textarea$b>\n".h($Y).'</textarea>';}else{$El=driver()->types();$Cl=$El[$k["type"]];$za=preg_match('~\[]~',$k["full_type"]);if($za)$lg=0;elseif(preg_match('~date|time|year~',$k["type"])){$_i=($k["length"]==""&&JUSH=="pgsql"?6:$k["length"]);$Sd=(preg_match('~time~',$k["type"])&&preg_match('~^[1-9]\d*$~',$_i)?$_i+1:0);$lg=($Cl?$Cl+$Sd:0);}elseif(!preg_match('~int|vector~',$k["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$k["length"],$A))$lg=(preg_match("~binary~",$k["type"])?2:1)*$A[1]+($A[3]?1:0)+($A[2]&&!$k["unsigned"]?1:0);else$lg=($Cl?$Cl+($k["unsigned"]?0:1):0);echo"<input".((!$ne||$p==="")&&preg_match('~^'.int_type().'$~',$k["type"])&&!$za?" type='number'":"")." value='".h($Y)."'".($lg?" data-maxlength='$lg'":"").(preg_match('~char|binary~',$k["type"])&&$lg>20?" size='".($lg>99?60:40)."'":"")."$b>";}echo
adminer()->editHint($R,$k,$Y),(count($Yd)>1?script("fire(qs('select', qsl('td').previousSibling), 'change');",""):"");}}function
process_input(array$k){$t=bracket_escape($k["field"]);$p=idx($_POST["function"],$t);if($p=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?idf_escape($k["field"]):false);if($p=="NULL")return"NULL";if(is_blob($k)&&ini_bool("file_uploads")){$Bd=get_file("fields-$t");if(!is_string($Bd))return
false;return
driver()->quoteBinary($Bd);}$Y=idx($_POST["fields"],$t);if($Y===null)return
false;if($k["type"]=="enum"||driver()->enumLength($k)){$Y=idx($Y,0);if($Y=="orig"||!$Y)return
false;if($Y=="null")return"NULL";$Y=substr($Y,4);}if($k["auto_increment"]&&$Y=="")return
null;if($k["type"]=="set")$Y=implode(",",(array)$Y);if($p=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
adminer()->processInput($k,$Y,$p);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$Mj="<ul>\n";foreach(table_status('',true)as$R=>$S){$B=adminer()->tableName($S);if(isset($S["Engine"])&&$B!=""&&(!$_POST["tables"]||in_array($R,$_POST["tables"]))){$I=connection()->query("SELECT".limit("1 FROM ".table($R)," WHERE ".implode(" AND ",adminer()->selectSearchProcess(fields($R),array(),$S)),1));if(!$I||$I->fetch_row()){$Fi="<a href='".h(ME."select=".url_escape($R)."&where[0][op]=".url_escape($_GET["where"][0]["op"])."&where[0][val]=".url_escape($_GET["where"][0]["val"]))."'>$B</a>";echo"$Mj<li>".($I?$Fi:"<p class='error'>$Fi: ".adminer()->error())."\n";$Mj="";}}}echo($Mj?"<p class='message'>".'No tables.':"</ul>")."\n";}function
on_help($dl,$ak=0){return
on('mouseover','helpMouseover',$dl,$ak).on('mouseout','helpMouseout');}function
on_help_value($dj="",$hj=""){return
on('mouseover','helpValueMouseover',$dj,$hj).on('mouseout','helpMouseout');}function
edit_form($R,array$l,$K,$Ol,$j='',$H='',$gl=''){$Mk=adminer()->tableName(table_status1($R,true));page_header(($Ol?'Edit':'Insert'),$j,array("select"=>array($R,$Mk)),$Mk);adminer()->editRowPrint($R,$l,$K,$Ol,$H,$gl);if($K===false){echo"<p class='error'>".'No rows.'."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$Nc=false;$sm=($Ol&&!isset($_GET["select"])?where_columns($l):array());$Fb=(count($sm)!=count($l));if(!$Fb)$sm=array();if(!$l)echo"<p class='error'>".'You have no privileges to update this table.'."\n";else{echo"<table class='layout nowrap'".on('keydown','editingKeydown').">\n";$Ga=!$_POST;foreach($l
as$B=>$k){echo"<tr".($sm[$B]?on('change','whereChange'):"")."><th>".adminer()->fieldName($k);$i=idx($_GET["set"],bracket_escape($B));if($i===null){$i=$k["default"];if($k["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$i,$fj))$i=$fj[1];if(JUSH=="sql"&&preg_match('~binary~',$k["type"]))$i=bin2hex($i);}$Y=($K!==null?($k["type"]=="set"&&is_array($K[$B])?implode(",",$K[$B]):(is_bool($K[$B])?+$K[$B]:$K[$B])):(!$Ol&&$k["auto_increment"]?"":(isset($_GET["select"])?false:$i)));if(!$_POST["save"]&&is_string($Y))$Y=adminer()->editVal($Y,$k);if(($Ol&&!isset($k["privileges"]["update"]))||$k["generated"])echo"<td class='function'><td>".select_value($Y,'',$k,null);else{$Nc=true;$p=($_POST["save"]?idx($_POST["function"],bracket_escape($B),""):($Ol&&preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$Ol&&$Y==$k["default"]&&($k["default_sql"]||preg_match('~^[\w.]+\(~',$Y)))$p="SQL";if(preg_match("~time~",$k["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$p=(in_array("now",adminer()->editFunctions($k))?"now":"current_timestamp");}if(preg_match("~date~",$k["type"])&&preg_match('~^CURRENT_DATE~i',$Y)){$Y="";$p=(in_array("now",adminer()->editFunctions($k))?"now":"current_date");}if($k["type"]=="uuid"&&$Y=="uuid()"){$Y="";$p="uuid";}if($Ga!==false)$Ga=($k["auto_increment"]||$p=="now"||$p=="uuid"?null:true);input($k,$Y,$p,$Ga,$Ol);if($Ga)$Ga=false;}}if(!fields($R)&&driver()->primary!="")echo"<tr>"."<th><input name='field_keys[]'".on('input','fieldChange').">"."<td class='function'>".html_select("field_funs[]",adminer()->editFunctions(array("null"=>isset($_GET["select"]))))."<td><input name='field_vals[]'>";echo"</table>\n";}echo"<p>\n";if($Nc){echo"<input type='submit' value='".'Save'."'>\n";if(!isset($_GET["select"])&&$Fb){$rc=($sm&&($j!=""||adminer()->error!="")?" disabled":"");echo"<input type='submit' name='insert' value='".($Ol?'Save and continue editing':'Save and insert next')."' title='Ctrl+Shift+Enter'$rc".($Ol?on('click','ajaxForm','Saving…'):"").">\n";}}echo($Ol?"<input type='submit' name='delete' value='".'Delete'."'".confirm().">\n":"");if(isset($_GET["select"]))hidden_fields(array("check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]));echo
input_hidden("referer",(isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"])),input_hidden("save",1),input_token(),"</form>\n";}function
repeat_pattern($ki,$x){return
str_repeat("$ki{0,65535}",$x/65535)."$ki{0,".($x%65535)."}";}function
shorten_utf8($Q,$x=80,$Ak="",array$li=array()){if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$x).")($)?)u",$Q,$A))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$x).")($)?)",$Q,$A);$x=strlen(isset($A[2])?$A[1]:preg_replace('~\n[^\n]*\z~',"\n",$A[1]));return
highlight_matches($Q,$li,$x).$Ak.(isset($A[2])?"":"<i>…</i>");}function
highlight_matches($Q,array$li,$x=null){if($x===null)$x=strlen($Q);$J="";$G=0;if($li&&@preg_match_all("((?|".implode("|",$li)."))su",$Q,$Yf,PREG_OFFSET_CAPTURE)){foreach($Yf[0]as$A){list($dl,$qk)=$A;if($dl!=""&&$qk<$x){$Uc=min($qk+strlen($dl),$x);$J
.=h(substr($Q,$G,$qk-$G))."<mark>".h(substr($Q,$qk,$Uc-$qk))."</mark>";$G=$Uc;}}}return$J.h(substr($Q,$G,$x-$G));}function
icon($Fe,$B,$Ee,$jl,$b=""){return"<button ".($B?"type='submit' name='$B'":"draggable='true' tabindex='-1'")." title='".h($jl)."' class='icon icon-$Fe".($B?"":" jsonly")."'$b><span>$Ee</span></button>";}function
copy_icon(){$Ib='Copy';return"<a href='' class='jsonly icon-copy' title='$Ib'><span>$Ib</span></a>";}if(isset($_GET["file"])){if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){header("HTTP/1.1 304 Not Modified");exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression",'1');if($_GET["file"]=="default.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string(',c(<UcrZ9FloAdLIHT,e^12P>vJpDt~s)jpQ5n*/jNlpj4]q19=eVp+w8a%oBQUTAw)IeW/u7lvs|iu0YUhhis?by`^2y`w^^.@t-OS(=WOR|CP&H43h,8fIox:9}+Dr41T<m4m%{PjoXQ&uX>x]gL$Cv>O&?K0e2t6KKZc]hR1[7PmhM1DHfTAWT:%[6bp<T/81fB
.r29*O)#Tst!3PTs7R./CjgEHA#(jbnyfb<iJlUvk{igwY622G;UTZa%AjWa=un-JD;P"b3c<*O>GW;|by+@<eF.,aIW0o9w#7A?P6hx<(,;THCs"hN+JLZ8"S8uS$H?@Mn-Ex6P@.*Y"8*e4u2{IWt{GJ`8QH/*!]T>-TFc9G$kOi9`vgYxwn
c%g)X-E
)EzoS79gZ,B^(JY>CV+i%U#DXw(U!jShr7E.,O/H=?y_RTf@C
um+TP6JsRX*6UHOW)XH&`0uU-%[B_5b/PGK%V6F"*VOYlO%r~e%7r4zIiZe)OGZ+6pFLA:V,Br4Lp[>S/$K&z12m)VZY9)vPI5?F%YP7Ogq>&-5];3(c`Imh^fc!C3hse,H43n$0!iXAQqbf`(tm1orwwDZb+`#pZ;K#kLB(y*uQDg@<,=2_=(-jcNo-p6l/mQFggd1JnOB>B-p/=ZD<=<t;t.KXa4sgH)Ltuj>H,".5g`>k8G`jTY]_-$T]X^Ytg4B+4Pe5dn[0Jmv0vEi+(l~M}[
k-y`fw,^#?q2G;jQ#Se!4D;B@:uF+ui]U<[N^fhhFUN5c%$N+yT,Q9)A"}di>"HNulNmg7&eH$fncswB:21#8WH~dZ.B4u[RQYCj17"f:w(Ftt+[2v*CdE?AKm]3XAUA/"$*$Hx-`6G@)skomr,jBx3je[fAY!+&;z7"<6tq,{mH[(s
CZ.eg*0.$Z[ctG`C;2yvj};T_:TBs[DdMHH;"3prfxZj3
U3e%Z"
N>j@wO55C[8"f&k2gZj[Q:m;xHD7ZJQBzlbSC;1B87#?eSY06Y65k!fOU6_!*Wx:h2q-(T&.*g0OSNSp]kAIgfoTd7i*{J1gg
0YX[M%&kFnF!owS$[QI4)#U=a"[;5oLjT;#D[I1sFRw#|h/g4eM:m%T,SUdq_rWvCQ+@=3hZVZUnr82v==?Rh?#bz&o4$U_6N6)0WPGCy>@WB2tyW9Rvy4j3+.nL(0e:5hy9?UE[27WG9ri*kXwFC-:iQ$"0?d!DJ_A5FHzo<^X+//:a;o|-:)5067zUG^N#rr$qI"K3Q0Xdjg_CG:s(Arl+%Hr)}I5K}9[CJw8qYgb"JY|H#,CZd;l8ef/9hi$fBrt3InM_uo+%kThD;YF5R-(i$n2Q6WR3DTRd|b"9d;7y~"Gt0x]NewuUp^RT$]H8?OEUD4t1{>B?u3"c&.2So29(`lG5I0F?J]*Ai?TgR)%y}q:BvlK%7lqi8N8aATIByV*OrWO,U-__]e!Ts(.tI!v[=1g-0$D:2Zl^e_7PF>e?,FN4_&RsZ=]c-:?ICbC&Zk+vq2F3iEN9cq*##I=Fy@N>$2/={hUbQ[Q%
9XvUvMh<Njh(4i^u_0%RI(<Q-([F
JO39W9H8brm;?_!1ov4P:i,suDlpy`5!*.#@`/c
*=R>+2ZO$IAog/083.+hmP;I|sToi7w=1k},|0v&jyZf8,e[cHgD[^S,#pE
O7O6$7bWPM&P
#{mOOuY<0N=0/}8;_1*pPz`fF;d*^Xs~E`I$,E.eJh-}gkVW#(ZgifL&ku5^Um"P9?lD/t%YRj1`>H)-*Z(uqlXTp{l|6#"JT_-Glw*koUuh"1oVQ3c:q`e(Ou;1f.ML1?
+s*ZYj[Qyz!@k10A9>P?:uQy3r5lp6SS5Cw&bAcsPdf-?4Pn/lw(zfj$CIY**c+;#Ey#G%A#-LO9qG`;!Q$d?g/RT;.;#Jg-&5#c4+xKX>3aOGIOK5g.1&i1qC=5ctCP5w*GWOJJ3nBlcOUQ&ghC^]Lfk>[1IvfA+@Qnx7x-2e`=V->-`sATlbT;jm&Av`@(2b)[HuUYwi.d@B;[+5H-g?od@X?Ftr1dMb{L5LqO5[MV~]!0.@ZTZ+x,,Q$BARIm
h@hLPR&jgL+kr;%KQu*a&qK*s5;#"v?e%lK;`wo+J_/6Zl%Ks>:|B6rsAu]AJb,A8>>u^neEbPK8e%q{,OYFf,eK2W,e;=Sj&DsZ;rd*+-(ISV"Z<#!
I3)4(/n($*PXlC3"juk.,g[eCju"B]4)(!8
VPkw&`](PnO=i*dE[v51@.@.;&1oaqDR?T09$atN?[#E!0dVODQL/,
BQ{+-4SPW1y""2?.e_JUNua[{yx/]wYvW.gva;M5Kjr^0`w34l^Fz5"HnDC["&M_MH;x3H((+I1?qfqFaH^Dkrty?ZJ]cxZUg3sjkeU/hBvu7)bV^l}^}FKH}:=V)3p;X]T&5ez"GU-^zA@e}K.0,*0/MI^*Lju
2LFaQ"h$(F8fG[_UE)@YLWTw|G=t#[$0E"T)@B=ic;G+?K(Tp$Cn,XyT=ksXNR%A?9z@CSo2]8jb8X>VC#r$}hCoey#e?#n4J-XScK<=W4(k"xtQ`4/kEOsp;H%"k%)8^D2Z123^V%v7=.^EUBwvU:u^Vxlfbtq8-iQ-]7HTcxYjtn?y-u0GTq
wp]^pen
U99`GHj#xFV5MZ%|Mb1:f!McteBgmGctX_uWcZh0yj
-BagRa~aVtIohyF!<DR4Vi8I-a%1FHOiTx1G{?,a.vqBUSHCcy2]/_8k_pB:)0i:h27GmUes4E5LO7V[{uza]nh0MRLc$S<l1/^fkBxj;[)z(DAz$#351rq/r;rEdyx2F/-7%HgbAO#PQvW"HJ1j928ZIG]H:j9g0R#t!ylx;w>w>L/D^.}Gec,dWBgw}i{:}6J+JWK!J2<WI
jx*7H]F[_LodM&3e2V9ClhnH=,#m^MrXCK$==0"+vs,8Fu2^iB}Df1uf5g]2:3tw$x#Mcb{0uCjJ}DGbxZefXH0v
VmmzSOrX?`2%D;)2HK,D9HsXy/-D(@kw?LL*1mf1]TQJ&K-.G)37rIQtuaI&!EF]MjyB5L4k$
sA){bj
SJ$<v0gaAU*Z+=*N`"$xR]y/jlpTj5%6Z0
Z2N[+17YTn!;Btc8Lgk/Siv[hI&on{4Vq)h`rM%LC#x2qih>Lttn-b$b^H
G?G-BFJ,i^
WoKzKO*9JgIyk+#V8.tNx~lh-rc`^4:OIv7=TQyc[8s!wP$IEcfCO$oHtQq+y
5m]^w@4}xOw=jqWP62c1Yl){GG6!Cy"::&FxJInJ-ngn5wksWC48^r;7AcS6KoKne(t)HbCElAdGR$V.3)=[y]Ow#_ai]R$*a}Hz7Ofy"J^l)bw"&@bpAh$F/NKht0@|)zwKoE`)N<O32$?-BgND[uv#=Q-ZoYD|cjeCZ@
|AfVIMj1/+o*,ta,vh;0fWp<eP|%rr<5%105iU]AbU25gR=Q9hCO1thcwVPosG8aOCG*]*d7gu5n4?Yuz_Uxn7t>#yZM.3t38D~Bl5TLlUC?p3tkj2/o-^l(?=zxU
&cpoJ7w4RD;0*X"eL8Bkw^MGYA,[w(YEZ>3>Z)qJ->CJx4V19I]wRubX
_w0Ie3OfT/-9";*19V@l0I-/J/LAym^m&Esv!b9r;rDh[Dm.&a1%fvQ|BYH;`@Tk4!n

v,A?3Hi)3MFc6j6ywXs.%8QTf@8H/3@xo*b]|5zwG1+N7bXn<h++/,3@H%=%yedr{w4J+c:Ope$URGK6UJz+CA^DL/[/GQr4a(Yl;^l`c`6Z__M]e]d>Ix}YXyDm/jH^LgAH0b"<|;*P<@8mask^oIs-bh!G,AqnX*(&L^wXEPx^G2=ATA30gwZ
AM~^5Cz)c`Q+fTVlGqV(24fdqMJkcc
Htc/oh(x^Ho6eVS}4n7l2$Jgl^f^;(vtCzm2m^uzdK2h=$/h2fIx:.tf9Z=VeHNay!XBR=EfYV8s[$x"@?t7!mN8/27#QR^HWlA~[DGw2HCKLlixcs(H>YW;4d8L.(#L@2#W?ej",!%Y:Em6R5
[LDp9.MRw+zi45/lEsrgr&aZ"J`_Jso-&sV>dc{Sg+)Qwm!)k:/a(^#+aNxos+>Nx5UT9Na+YC>lUl&Nndk35j
"sF$v=4|#e:<IaPI+33.`8REe<d4rWs*%:33sR7#ry^!jzVrVoa=+>5bBND8`4+?AP$X:m/E5")Z?@#|5%V`o$^bYCRXeWj/a[x%n{
KLVWhANf_5*_aTkgtBZL5HgL!F@XdOSn.*m#*-jM`*v"8sfTaf//F)kEF4(EWc*U*SLZfNNU2j4j`s#&0Pd)hS5#]+b(C]Jf*H9ekiPhOPS3HJYSAb9v-J*4w8YqF+.F}W0!v,XO,,O?Aal_qb3jxt3nlV{X~
FMxK6`a!sMJFIja+FhrMGMZ
{Yy9v"WU(?s
T&1((n{)}/TcRnu?D6N5c]gUyXN5O9$J#Gs1mG2>Un7H1y5NxEx0
g{JsUdtLh%t;E3<~?=RK%~jH-5vO]~;w_{[ygo.p_yb$@_x/O3Uu8FGQ:?Zs6Q5PM[TSpW&=_%L[NJC$-*y,ObV_#NJURX/}4=d{C$$7]q-$.SLFX!w*M.OcBtmF5,SFx@lQJcBrI6wPAs6iAG4;tjveo)');}elseif($_GET["file"]=="dark.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('(Osc*b3V?$d0U7vYy8!&<6V+#5Y(O]{_E`f_Nnud89X^o)QQ3qBMrdN46cZSQwh=;$0.!*Zw(S_-i>;II-}+0!f6`8I9QVZ;o&E5[c=8273*kW8ozGkc^+xN7#Y.@kai=uf)pXra4.tvBDppE(0O%pyGdHQ;9&iXKL5h"f}sTF^w<u+N`QqVkc//{&C#v2fr*He5[n^S~K}LsnolvKKV$0UQ^5Q%aL,,EO.YcxHfUAFE[fz;NTDXihkd+*v)=*:H<h4m}7:MZ$(T9?)fvQe/+rs-@_!6s.%kFS8BlkTM7:.>T!T1a_Q]r,{G#1K#@DJtTWbKDhYs+d&f5$
e2r-x}$$E]x1jKb?*hC:U6#$nP!Yl_fhiNpqSQn,rFAH^Ej0WL6iVbFm)Dly<^b#KHc5d]rDfY=:)ji_M)GclRh68dd;^5K<_T5a@_N~m<-fPk)n,Yt2%c*H]%J|-hp$0S7[OX%#$N4]]QP3#il!.zdMS9b~OTUbf28N;.2e#?[uF^Zs-}.fOTD2kINN/_9i>)k^J;)<,p0Vjb7{tkV#@~3e%r)x]^Rga3?&.ot]hsKK
0RvY8Rcd$eg%SZw7upRx"vEUKc1n8!TO-X!gT-)*AjLt.nkxls<V_YmQicVt-sXf%[@j#pX#mg5h9(
>WFWD=eQShDcxKsdG*FxQ|on**[[%wssZ|,@a(y#A:>EurcPU7t,+8`@x02UbWotPkWM72h9$VBmG7q6Idd1z"B(
HVL8"i4&o!o;@W$S3NSF
!BELq[+mkj"L<1^->lc)Q6h?EbbC(-no]nk$(P8TBDBS#qatq6(vP68>:4v,f]68(fz%qby9w!D")/mB?*V6=xmTa%JY26w{H/DA0;97<0skOU_*7{taCF#q1&FBZ[^a&(jOS}tC&kDpe1T6J@z)gFAJr|[sNFt-OPIbHH>j62GOYJM]Oh,VmV(uW*f+C^.;Jx1(Ax^ue6[u$/O^15L%NPP|#[ukll8d_fwk[a;
fjC]c/-/7w&n');}elseif($_GET["file"]=="functions.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('!]b]`nsZ32#tW"t=D^f-cSgTKbHU
`OQ{E!:WmYA8@oVFA!5]8%:/.^",RA)Ub_b@nC";#1ls_vRz_}Mm@*^+?S
d)r*3/j:]J$kH:(B;r<jgc|?(dmI@U4)R3n2kL9UT1gEMGg]/WVrMy$/EqLC4/]M~)2pzyAUwk;F*6iAe=Z6}eCL%wk`d_Z,x
a3Dp_48d+,{nQpcZlcB>k2`oF!PH<_u`fkthnQm[G6(H+d_s37zD&)Wj&a/
Bt0uh54EUb@Zr[o):P]Fn
n[JJW(:>>g%@bjvV=7ZbycmJIvr)$.PLTrblmguSSx&2wrv*ScLO<`%=
Sc^(o+VLYb[l7L7Y[d
e
9x0UtD^Q9o}w_;[0S&p]GYqp98YN*XSAJk-`m8}Ja<e91
a]BTN[9]L:cDRf+BNloiH;si{,DA0rBj
UNXpRyi<n?M"D};Gk_m@-nv<GCm!_qO/
p()/cK<Y|UkyUS;4m^(LzJYmV[hMQyrSLskLVl_JupCd!34h;q4x"XaXut5ygpFSt]"+"fz^(;yyE)C5sb3BQei+:m"hjL1hq7Qu4p$u_=7/GdV12T_norT!9nVx$suQ0;G0K@tan[<mk:Yz%ooMsJ;)J%~MQe0y3Tri}/2XU4;*Uv<0,,e+Y+yEj7*)A.!pH%<M6U0Uk#:$6E57
DS7#)]=We<7$[yhZV<kF`Cx3[nmQ%s@|X#e3:<r5VG<~%sNnDA9(vjjfcNT@LLb*>"@?hk;<VH/^`!U6VX4NnkIUN!>}#6v^Zn`i7LsCjW,8hbmT,NdO0CCB^0-R]5*Mow9-GAv!)9Uc+[RqooDSs9Gn/-<8G4]QUA2`AL%,!|!HZet<,pk$k(rQaum?K_YP0NLelj?4ZtD}t_E]u/."4MM|s5S*fY.tgvVjyYegI+IYF!m]+oGsIecU;"1M7y=2x,Q^<EBDnQQJk:p
/T=C*G*g%i3|;:`73~dWY90x@dVS@:$S3h;$Cl,{sy^`Q7k,Nk3z:#tWUTB,q*3Z4q<7np
UPK)?=#,`9mANW%&`cOfDxT!?x&
*gWa892sog;MjK,gLh5#G4CSw5Q?-[r1-TmTGw4V?Ho!eXC)&ZEJ7F3RsoEm}W8uR]fLf37gRVv!]PQ0E"qN4e)V*pgu)"^z!s4uiIum:(sTX"cD-W)2/"%EVROBl-3*8ur6Yc|N=yoN79@"(svOEEQKLqpx[[!$rNPd+]4Rt:Dz"VTx8OhxcTG!hg*%;m,/5.?Z*pFN=)*b#7
9Ju,GqE>@&Du6qx66Z<2dei2mjcpy7;9n*t~u..k."Yf6r!<n0V"iE3T;-oZjQqS3i4Dd)`S/w6F:TZoBO#)D=IzG#MiNBHw,XH}(&-ltd)Q%Gj|UGa(sGJC=1?(faK+T[Tg<Ww)1w,k^j&utOIqp-s1M*nAq]Gn2IK7TZN%3ry0`b827p-YVg5/LdQY-*W<7Rx-:n*UM*8~X$/[5hnP6+x
U`w~aZ<yZbyC_s(a3f75"a,@EMIn6CVo8_,88?K6^exjh0/WtwKR@|1aOFfDgKPW5N*XhZHkF2$Dn%NStlp593m+`[WM&"r*ElnA6h5|kD1VIMaO>=g51`Xp.a$oLUB/2cs-&T/lq,`/Bm!|]-.SKsmE8
8VA0AZ*^C=?UX^D3$se(nfD
:IBR$`VYR%`lV!n{<1CZp<9Lqw:9(jIpN7A/I`$&?z@j#G3:Yr=]0h
EUM:|WoI5p
ZA,s)dx]#ai{x.g2@z%}(b&a-0
3l23J:E%d("5~`E1puIJ7y-Q#8zcI[]E3LA6fbFHBjNcv"KYYN,b0Tq4/BJ)q,lc24r.N
glI
t$>4p[*]tM3SChr_xpe`Lr.a8hP8ar[wDi<X*"=A*h0wtGK:hIjl]C%2*%_u3xO)20r+Ia^^,_JwpCdR+H_!C4q%=jTV
)R(dQV7@"cLg7LtufnQG6f&mcg+:gF(c"t3Cs*W<Nyws,O#Sv|VE8yG0hY]>REONb]F0BokTvuO4Z99$JdH4qmLx1OY
yx$A@K=oP/i8ljb;GbmO^CXao/J%fc,QJtle9G01]81hP?[>y+FP`NG^N1J.lPPJxYT%D[[%eSd.=W;E)y*$?Qjp5^-Xu.t<;},kf>p!`PR3s$Z1t^PopL=[JT@A)W
~YvT,ISw,M[j5"bJ<=eM=NxP)@4IP6}<g#@kdu_F4#CA&V|AD_~7BwY,.d?LHjB+`W0ht8&[-"b##NJ-V>>=7DTjn5?C+uq`i1+L<5
TIX]
m?r67.&nJ9zx`9Ne9N)s`,{VPuLpaM$%0``Z(e7xM734[X(C~T+Gktqn:fT?@Pc^eA7u;6;ez#W%O*U=5b8nr8UZEEM:ja!-
YxO/kprD#QwCNO69pt.@FJ5G"C!jAP8T4<[<v
o`wnrLpo3NdC`-hlt<!S;)?DJ%n~`xNV9DF66Q`rR+P|)j/Ijs@8/3rc%LVjII&".PTx9!KLX*/Zm=4L/)Y(0DThSUl_+]b_!%8}b/5/T-tEGPX&Vl24P
2"H(xb_HFn$!OVcN/1S%KFf7:3C%UR)v[uZ#[NDFE|B{]6_dQ)PTt9dU@i4`pm2bX4LiqWY)!%lje.K=7"OM1vx_V-KdAoINfWp*ZwO).3e1=g07(APZHU&hyECZXXqGoo&oaEA0emN!r
&^QW/<
K>]ub3&c/lbuqvWEj;iBIY
_pGBV:LK@"Qm"aor$MayBb4RS*F`Al;SvUsj1f[^B$%.5#=j]86*R~_nREK?=L:6JFjgT>jJ!u`Q(VU2rV%K_8".40T8kd"kxn(q*X;&"f-D5JD*g!Qzhp[Ud*<T
P[c<xjK"0jxM/&m:TR1N$d(`;N/mArY&ZCub!t/XVG;ZL0=Vq"/Wad~m*5w=r+`[+<Pr<b4*u?z^J;]FkP&sq!@SIL82ye`TDUPhZrk-*f4Qwbk:)9*R[_#1m1_)3o/<ENlMV44c4!)f~t(8XSr#el8:p_Ksbgx@oN3<Z)k@p@uAwAJ!L"B>+=%%!w&+I@[Z3eoFK=kgaM`Pc-UG~0*^jT(.h!tr_=a_NTLk/euq8=
iP>vx0M2*EDb=>lt:#:ad"EK@mK!&NXRQI>iDmgCLo-T</Wki%&91Ioe7
>i9{?[.T`5I<jj7#ufNOb/!C/C5Kt/j?.RJt+8(39JEn`(,-g_SSfuN=8#X`jve3)Drt6SO_[Nb{v!uv"vJr5RFrn>Kr4|&vi1xgwU87*3_9=
AA:b/jdjhvNUtvdP`6nLFiQL>df[v}4Vdwq{[L9Ta-NYaOrWAV!Le2`wG1rs*Tiel@$s!CkcJ)<DU}r"#QtG3`*Z
M"W>8Qu1S#j(xP|
PTj8~n/lfEPH,Vbxk9S%5EL!U_-J&V>,2b8V&2Z7dUH9Im1QZjMG0pGGRLLE
SCYC(KqGW$`G)bO{GeRwqrFhP;^{/uAZGwt[D_w{Ak@0:+Q}hX"1d_A]7/
dT/PV.-rN/QQ2Tv!6YAMiX
VW=h=Sg*)6n*$*]-E0s`P{,-v~CrwI$Fp]=[d%)WGB,!h1AJR(-c"ERDggxxeh_pL#Zd6Sg_:?90,CFyoN$(
xcVRp[+ALsx4lI}Ta:-5M9SSg5H/R!s7M+qyUILe_=P*J"b-P4w7R#KM]orS1
h5>ZjTbit
3j.UuOom3g?_X4Q-m&_<:eZUdG;C5D%8pi-2R[@6:GN`&
81mA6I1(]#*$5Gt9p.MVT:[iY]^5sRGOPJ!VbU?!Zp>gLKrmVB0uGT02`x!,bWy.sfg<Yl>gZ*[a}l?W>p&0%?:(RSf&.;Q1{ASUGQ}u+d
WR/6d_5]]NkzrWxtG.5
o9;Z.[k)_)eOg^v<1{EAY%&~Ya]&h-*U%c@P7(,Zl;M~5T=,c-/*-^bfRb
~`&j|*oRoi|
_?v>Wu_bv8Y=``7@N=bA|M*U4.Jjzf|45RR&w1!
!:r=tMp8OC_#v)EGM1Yw_n!ap$]A{`J+CRid(!Me5;@!9S=
UccJg
?Kq
42dq%_f_.u;S}D|%DL6m`;C^yVAS@c,nmy4tYk~Av-<nB$~uG3co#vu&;pd&KQ`PEE1>0n+OI?&,M=&++m
KN-FmZfhL+E8U2YL`VAFth^d4Mv0r
l&f6E]$4Y@
ue&b7?0ZbLIOEEE[BkMhkHU#c@q5aXBMb_&4LdRH3(#3?H+Xs&)xqc{0i%q3sU7<z@m%jE^S|U^-*l&EJ[G,BphbX2&$jKtF)Gy&A[4DnbZe2iUS5-)+eT2Bj-+bD:frDEDjRqD,$#AN2VBAfWxd4-M?gaf?G:l*.oZ`A.,@8t;eH%2ReQtnUWmcyIyVxl7r^2l.U@D4)pB.H21W#PmDid:>"@h-pPxva#p[{WK/_.PWS/#:c$8BDna![8e_l*Nd;[d08:Gmt1A=Q<#VHcZftj.s{$b9HN($X:K,PX2]X0).DYx81:Q@mVeuTDS9O^}Zj^|_Gg=:=3`P*FaK{+aE%J6Ea#7H.wwc_.;
S#)QiqL$(^LnFH&@R<&)>ChD.Luiw]5E,15[fDfC"j*N|vDqMnKgVy9eV?AiNsg#%H^0U_fZLh,iKb78zdDb6;YXxQ!s[fX0ec9f{y_m.KwL<$
-Wi!tQjfy}5Yd52tq+4.$
LIY1])3s+v6`XY57Mx<J8F]LTD^7xOyi<hxp7r`#Hh(tV]DP_nq*+xYQs*th;bmdY[CjAqW1H`WNU.YU>AxvC!e:-Lxl@ppW$eZ}4`[m;2/?W3TTZ%QRtOu(`L4Lu8tA$LD&Fc;8[`(Fgd>1_Mr
]gkESP8D2c.y9Seg;FS+Y]2)"Dw81:pPey_
K-%+X5:DEgJ%g:N_o-auA<UvH.AD^e8ppd+*OfByyf
;_4"gLicsEj/R#=h>ZTUS;%B$[!h#"y^BPa)m#jE)P~l=hboMBuBguuv;c{C2im"Y_`=ix6F&[a3k[fT+4@@sCjDOF6g5r$G+E&a|[:tC1;5VG=tx3LokL;&#W&,%d0oA)H?}xnR@M<9R11a7sD,@,^lMucPhT}kWBzC#:q7|&Q$_QVuWa{Bq%XVe_pn&B{7/5wA9+~vqmpGZNSQZ,^g(OrCJKi9
Pycb9A!^+2e+JTbLQm,K5jI:/WlZo))M+S%57IQaVcB==|A,a4Mew|m0"WAe
@SK!_rk@JGMUrP*i167A?)XgEw]CD[^H;gW`vN9r,,CMQ/&D#%Vws++BOL5$-ux%9Gs?/ASCa=X-Wx6hgHT8;F&F:FX($rFqDK{;3+"F5(XEPk_#t)42F]%l3LP6@KJ
:pR%fJED-NPV,WFE=q^0|_9K;%tA00h
~HJr7pK=`4Q4Y_5F,"<_Wk=6<:@FJ#P$DgLIp_7f(&W
ux"ye
_E"29*^SBb}L)^`bA
CvEbSL/[nFkwENK,gN6cMS@c
u{#hdr5*ZS`).+2_(?V}BoW_e):gs42ifov!r4kY/P)yN4L*,Og3Of"4pht2bB#^@>OtWn0-Df%xtTm}W$soifY]6/O_0w.9=jw6sW@V++2y+fb?O}I8`8u&7kx_TJ*Lv]j<P<?6sJNg3p:>9$xdp6#GL?u=TO^{,jmK.f";f$J%tHXpt"Nbcn#NPagM@cupRFfT<]+-*{GI&*kP4=5YN~<<Hye_d65<@a2?JG/tWK4m+]0w;f46@cgF&5P)Ao6g0{Rk;f`q.V/e@oKf
/NDtjw;Qi2mM<CO/+Hz5+N^sa7;BSVO"RByUBR}5XOF[46K8hCQ@02`Hn"]7Vvwl2%wmW1wcSycJ6Zy@h+W@2AwR3y[h0tx]_AQGf/CXYP5Qe<&JZD&-":0N)=c%4alPl6`;mx*FN>Ptq0p;g1Y_EF!0+C,V8)g_:>!@"=)UpX3gvMWG&jPfY`n&+vP>U4aw+1t
/S`k-8BW<h<SI`[(`Xd;jRdG;
^?!(uqqn@<<]Ru^A:j2fb7p$ghuu94ZFp?M&TDuJOY,DU`-3<2/,z/KR%0];3Sw`%<XbgATX>,&jLFy;4H29GhZar;:3K4`W85[j^u:@b*>IM)
9o&C7AL@oxp9S8EA"g.>>MT3Fh5|TY24(
f5&9)A2Q4gEqTnb#Ow
WKB3cj8ff?[h/CvL2I$Jx4+h_4RXE>q#@qbCcf-?GdCjE5;$8PB&FJ%jpUW]p-Q(@15K^<[Fzpm[#2QD>D&Qz3yA:ALG*TX0ux1b:[d]wUy2@jc#JU6%eQt[h$Iin=;RVq7nlOJa(aG5)9?63:&9*x2SO[M(G^xc,8Mwo3V)/BRd2X%R~7&9|Ctx,yNLSa~C/a#v=!2ofvn-4av;^F(FEj%Sf]#YVN:N5(@krJfKw]/!q/JDp[9;;Y
gtui:BLAQR@G_AYy0{LbC6792~,?TyS]9*WUN:!:VxG{x*=SeiW?H
Z&j4na?<bLe-Wj]$F}Zw-^&[0z/1b@n"]
x(%ok_Ea]=gI<^2TYPwo9?+ft;Lc
^gjc;cnmx9}1^_`SZ9jlTBP9hu`01-/RE`U
)FRJ(r%hvlxR/OowL+`4}0Rnda<p#Ul>~s31[]YJvp0os$`D!OZlwy^/x@oOd*66dZaSJ//_m*_=xYwsjj:,rjdb.sNrTR-guj!(CI.^<V_+<=s`L9/x,S_5]K,*kAKp"8.RF?2[N<&l6BKkmRQDkvcV-j-J?"G&@crL6N|b4D;pv&IVtVD&fS;2~m(h+#{:|Kp5=ZwEu!^Txl~.B(.1&o5$FM[ROSvGs/ZCp%7P>3VxE
*#$9zv/JX++(id-@&iEI39LXbR$8U
zl`41IbIO
b%p8Bp$NWj!_hW|OqP3ghx*Px*LGH_z=NBWn7HIa0o6F~i$Ho#6"E4Ri@&i6ziQOGi4U.^TdsAwm3Hg2:jrp4%1#YMgW0?Ev)CS"BXa
Bi$+?L,@H)Hfy(nenu+Dz69Nq0
M[?$$fuZCJ+>3?im9eMW_%_#yZxTM?7vD}%[0P_M$
?V`>4s=^Zs?D^aB
:
aqRw%>bAvmQK7[-^.}o|;nO7WaY`Ugq2G)K`hd10qNJv_@pR[%R+55I!@k`s"_UvcBl5H{?/>^DlB*nO:XY`pC!H
$#aj![0u1ynw)?(wLQdvmMHx4BMYwjnHa8`5%r9<&O
)~3h4JQ}?zCQof`J)rRa^6=j2T4(&CV593F^#YsWx$Z/4Kp4^"C4&jPNkS-5*%2--w7QqqDh!Sgdu.]E5)AA_NhHd>F96TZM$_0oIHK{Jo$J^sp^yx@)k)t1Y2%_u#WpfN#H*f^;)PE;vJ@s^FY|)j")L^]`NgAiJ2a>,_afEiahb<L%[uNVrC1gW($/6$P=+4!zq8IkV;CX_=I.7e?R:!CJQ*$,/J4Xd58y_e;Ro%7!Q2)mw
e":nc5v%Qf`Z?Xfd:Sv`G!Wp:+youu6zP-=hm.kGi%_3:a82`[t-nD-B8%4c=SKCTxmmaHBs.zSOr<Mx#rBq7}
bN1h;TM_|k?aN)Ou2JzMsy`s?Ip)KBiJxvTL@Yi0Lh|Z[fn_0<D@3!A]*7*bmEl=jFJJD/iM=ET9,YOxR2i$F#"4G"-kgLf._e]FsL`J4I6n#p~5
fLgRD%N}$8pHl0*!MjWBcMqe!~
gPwTaL{C~TS%Bja3]N
?UjI7{dn&!Y3L;k/k"*NxqZo(HR@4]EO0QYsUt(,!|XC&e0;N7;Vx|)<1.F;i%nRS{bg9/$3#~jRU}8+/9w./kRqOr>9"=7A_?p0ThPrX=!a"Q7m2$T$,6O62QAAU$WD]7kCUPUQGyM}>`f>r>d28GWe0[8tLayzVZe7!OyI+xRBp{BRCj1_I~UVL^2J0*Z`=u7r7]H(=dkR&0x6Y|B&[3
B";<jS2!3-skW]y$AB-Ls:G)XOZU2QYJkxW;KWV
&$?Ex)Pt#4fqZ
2EA0s6{@8Mf8-+26nk5Jd*~jnp_Wk5+F{%NJ6SmI3e(a1BC+s_o2P<CjVU06sRH8UPShlmphPI%.[7
1DnKG11Id[&~cwWQ:^^#xBq[uRk^%H6FL
LfS&,;G{rxQ}XxQ6r)b/I};c(J!5l9cog}j?pOo/[.82y+Qn;Yn7HT1.hj*6ID[!n|W%hW#(6je0ab$UW+33_3f@y)L)t7V7?vgJ%x]$nDLq`;u@WCa+mHm$JTn}V3pHuaLv7e)o9$Z6"sdN3+4^4$,.YvsYZu1i7@
R0k;g71^|6Kf@4x7tY_U9T$][dACxrO
JPZtt^gMPLe:geKfw<5MoKjw2`LAIcb@tJu&LH7R5Ys<k%*F]x,Uw<t*Akqipi!sids/#:.+l3MK<1Qoj8/E@0qQ_lntb<;sFEf>!Y~fL=Msb*mDCn1r!G^auK6UwtI14#[RJ_npL+0d%4%>T&KD}5FoL>M?9@ti^/S1$`<s*<`h^
BngN
2dj8A<)D[?WI?F.>g-X&qVR%jN&yfcuhy%spuruvz!0za[H>!M7W97__
yN8&tfg7xvB*|JNDY8/y=NvK?X8p1RvBQ&Iv6D{"#"qmDV_EjoAb>1}x{K#ceqo0L=lr<Jp^"LLFDs")rXB#1sfB5/uEE#,%jukAK],hv&=<4f&/P(^amoiFSABIMiWyi-7.d=b"HIcKU?!R3!Qt$p0S2YwbV3~Fa4vqiw(^x:}"LA>a8[+Z_,oON#v],8&_p]FJ5l#%_&NT!`%C0eAs5;CImn;HxJi4&ZQ*:O;L5n!E6LwGx(+eq(hTe:d?n$89#5@uHuy:hmKQMDA(TjO)&dJYQQfM-WV1P)^gx6r0Ve1crPEe0-pR%n/@dQ~F>Aiq6?n>)/>E!E*&NBYGhf
o7BCCqv?:?>L9_4{$0hTD9OcT$)`U}X55QTG,$ddm$gL*zZ_9gy`iP4Ae0L`r4GMqIm*@Rstv/$}jj%?#5=HJtNF_iYS[[f;=-g-?U=(LvPSS[xWE8V~cUgpSrkJO]xU"SWAB"$sI7rUTc1"<@*6#n=iru/5u,$W/:#"wW!nd+DwY?saG+f}P_s<-dOi
UHzlVZkEu9`QVtFd57ZkW=J*m0`D4@GD^gJHiPbLHJ;*YO^]Y.r<,VkCOGt7C>>7"k;ey+I9pEESke6$S*gPepmldJq!`<P=vCbQGf*w:lib,4i=aSy&E"VcAG.73G&]`KAr[X>&c4k,}2(QLxhM/&gl$5BeNV>nl)N&9&pAw`%qMws(U?{/vwA^zTZX`XySt.<M6g]^0&xOd=Ok6>|36dQO&ePDKYdU9;12F;!?**z?;Id^c74u;ZfqSn,7V*lm<ULY:S
"VL0f)NH3%XMos#Fb5K*dNg{mFAI:gagdH6Pd:(7]ln`-5)B2ZV<`RU*JiiWq[4wx,>^DTl`Y5TKhQ,^Gdk8Y
%)V0SDB1NEcb8#d%$6^b.GbZg.8tM+pS!5v+9#;Glqxvk?D>)c3o3(-OLp"
3_(BDJ:}gs[@o1IhXhF`iF
o>1_LK+U
%J5m-p.BbAKj`0%23=QoFf`yD[1#VJ/y5`K$>P"E144"qY2fM#JvZXd[WsjG+mt&K$/.8KnC?d]h6HFk3o#QR[rv@)&sX^tD-MRd`~VkR[*I<Ko@,H>s_wrArH8|i2TTd;U9h#u{k=#sT0y7bURXI-/I&#Cfg+.+Bd-*W@h#h>_`t4fU[kCkeOL<l$?w^RExGW/=f~/J,r=%YE:J-HA7pqFb1_/u<$+.]%+)=(`Tf}E`:@
qf}a^badolO`Lwh-uf0$
5[u_/7`(K$<UYHw*g5+Mm.(F<H%o6et%sEe6J*P@)G[WCR5fr/(D[!"#
xN/X[>ghoX8(<+M"WhQ3GwUs_]o-Gn0ZNjFvpN;*v3#&3KBI-sdP*Ls&sPKm3<~oyK9rBgu;1XFv2Q%0OmUQiG3r(^H_]dI,3+7*~!rSx,Ul?CnnDa4.U7mviQ9oj_|;cdq(l6{_bYg3g8b!Rb<-|Lor4E*1(jKUqcNQbq9KkA+key9k{fzw:S2QZqGWc?7MXsX`3$vcEPtkZ]"!R"rQmbpO$k5%GL[z$Tumfo;fA&
Fsj1;"K-(C4C(sD#r_ud?ru5+YJ^GCQVm4rlg}!>T>?]^UDl-fY#3(<h+=TG
~4l%kEq(@$5=@E|H^IVYK#;99Z4QEN+GH#;=Db8]^8]Yf/q<Z:@Y=>4gwAm_{5|Kpx8m%bS_Jtdlbf0u&9%9}(1Z?pm0
Q@k}c_-NkUAK$s-zNxBD/CLkC=rj0-gm!CAyjEa
J~d5Nz9a%f@NlwDnF*Br/O*Yt,.hc%-.;T]g0l:la-vv;ksTg7?3.]&X
.Upr[QM4LfWmzLD;Qswvie+`sR4]AJr)tq6gq>)5[P=>j1>2|g64A(YqtVWhVGARY)K_7Zn50B77]idHYQY:RB9h;7HEdL!>~q}9j5Ad-Crih`c.F2K&h%/[U^MY.(tt=m0kgHEW|6%OaqMsU[NF~`|Mn/fB7]v[r?O?&mxg73co|jNx!@q@Uc{_EH)3&A.T!Ww3Z/rjLfqTZ;zmPm|*s^/]n[{UixmvrpX438UXg%1ZNEk`b(k[6k"QO[NgOF.<+w:&oYYcIkdF1MRk(eIWl!*)mcVC.^kW?4,qd.Pi7Ki^1#s^*K.k]vYYi<xZa@eDxrajuxI#|1*Lx#*Vs^zc,.D1>a4Oic_)TYx#:tj^jZ6HGaG3(.Ig#4(G"DzI$T:%hVs6n(8*Z$|ks)G@a
p6SSjrL!P
7dnKxni&`=}G(Ta.DRId&-wS
m264u%pI7E#;6zaO=^Wf-;"7)fi9L!MHr{sK7MW_5e.(HF84apfaPc)EwptjNcK6M2S/#BKd/209?)DwG0$}R
Ui[0jQrS=IVtVGP_F&tL^q!}CV&;QPdSG>,v:r(M^[.P:*kRv;I)?
L!;,d[w1_m6&p}3lmzFgK(0EdG_0%S
4EZ(ndr3HOq`i;x0O/j:dJwHW`c$K<PT!
f6Qxs_OI(oPe3Y_L)khbx@O.0qrhhbLU63Yz#t4Uo.KT`<|Z192#"G2j7
}Js[-@%yN6pL4;#@12DJvrx68a.nLa$$CAsS~FrvtRE.69FZ]Z{MbS/<:Rte8rqR.[N>^1RDSQ(&+2rTqyckM(`HYG2dEbqMbhcqfFjct^Mhg%ymP0-So%f&4FvA^a;K@@n9`]nlu
JTG]3JQ/""A
WtT^j
z=_yx^E?5V2a2fYDXo(hfx8]X]C9i[2Y`o~r2bm&XQYFx)ekNx)l|2WeViJ>O-}MI`P>;sk#)6XaZ2Lsz
2SMX(-XX+wN7*muu%ND+En92H,~]:R}1?2cimh-4h9w%BJXAIalxj1`q*p+XY1y;HTl&fXp"eIUVP^DfGl_:"@m*/t#fn!3Ga
R@![/k`V}O784*3(~>nPsdwjjhY]tydoswjX%]r
}-tO_+SntrI>3[Qk[,/R2c]m8lItMv(4,s;-ze%lEpMXe=`>0UF<<bx=J?Dx$qIK!WAGI&M01nsm939>uTmfCH)nh.keYiptR,{=d:U*W<5x.C/QOYfY,O3@V0bAZ&2cdNt)_xf5I=o=$-=Q)[w+A"T%fqO?9!CC).;#]@:e.f#={>T`^m/8*yJjN8`c$-S
86Ok&=vpfK]U9[]2-ewMf$#bIW_3Zdiri]AR}auvYco7KaE=q==I/vnyQjA2:JonFMm0!QO_y`3Yue]Zq.B/Ox6bR>mZWR_*78>M/Wcm?:J`%L<,d"!YRs=(jB`p31MG{rwUzPvd]hA5fXc]6:[0
(~w
wZ1,a+<g<5F$7wq+AN?("&_bSo.NR
9hT,QJL/9!yFIOcRJ;I~YVBihywvu&&~Fl/n_&so3p?NW>a$<.6n,qsKI([k7Ge3=Nayr[ctpMwnu"RhGXb{;&m/+#kzht+sih`w42F1XNOX/32z<kP7y
#Qh<;c!PRCO0#T/$0%FMUg>LARsFUCNn6Y^"@;5-v$e]g)1&;0:kDaXYYErWjYJEMN[f_N0/@rSM_^`a4B7%N&"xL/)"`)GHa2sYGGoC?pA58RD+vr8gr!>02wx~FG$1<.Lz4C3Q,cXMkFt!4md<E?x
s#FP_fMpy"_?*rDd2@.4k1#=IJUuk[?E4myz_M/wx_j+)Z(~QFD{-J:.ch)rFh<N#J`Un~7|>M)QQ_wZMu"J!lMY"/w@IJaAey>BPFe:Ff*dMY09fck
8!;qVdecULvxlOwQ#5<>_NHt4;c@oCA2lod9nqq:]-vGE/xU7fj<
HVd6:_GAx>!#X,l%pSz1gby;K2_cLP;#$BRjD3*HB&^:afTmqECAe#/M)
u7<!Ixp7?r:pf<l<n6-J[@~rn,e2xr8P,5^0*84DLQ@OSWNb}-a?{hx(;Dm*uj0knEJ&axyq).?y(rM+`yI%75>!$
j<MY[hNK.y}U$2&Sq>1Eta*pB>gwz;.?+7LkDZ<OrJ(P|Mw!W4EwCQNW*ECd+fX<@]
YMY+Rt2RTEu<!Kx5#ry~V,UR)/f9d:*PoDX~e1V9e-+{PJOI3wXZUP6b#;t)P7%]8sig7T4Gqc:docRqa$c#uz
Pu5Xxr|y@hqt%chw13}[m4e9V2"B)f|)V3j$MrpI>TBl-9DOg^(N[q&m
hW--,OTuM5<Jb"dSgB2MwmAu<b9hS1SB)wvWi~8DSLtCj9`=V>%dox.4SVmbHGb
3-$YRAQ~[&txB4(GTp,bd.b9+ju(p.lJC|
;]6>-Gj^SP,lKWKnY9FB2hn%`#E(XSFO5/u3p5B2GI#N8At/a&bF[Y]Fh*4r3gwHBZT2x:MXKQcZ3?Y":nXX~tBqI]$B/[l55X}d,-A>NN;P|jNE{=SFa6j`B=xJr]s:SF.VK
mJX>A$}O5N"Yekb*}a&G>iSsUyfD5XI]4lE9t<87Z
xqd5LFFTvS|Y1J<+}k9JDh3^%
L;)WJE@yVIoNMCVc|Xq%%<~sWVgEKJkO1O+uR7oyIMU4<cD#:Mjh:(02-uxy[CgZ}d[36%I)zgO7GMmDVl5nW5WP0$Gg<QGig2,c(ooIU<)J=Ovs4FxLd-j,i8,)nnJ)d^#U^,1=GshFvK)5h&h!3kd(X*|qEjqgh#9w#U]x5<RoXYDa7SO?E2<oA*j[EJ9QY@SP]iJ
(V5WaakAZFijXpnVc^pn5)XOr[(H|%r8!6D@/0C@~5Bh-_Z&QYfe,TXL^MaB:([w&sfGMBuwP&swv<yo}nxmvYM3beL,Y_TGM({;%<hRG/}xz[@3rQ=:T#bPE>K$EmbvoIVpOS5v&Dn7G#<#1o1U7-d>6/WdV=#jiHr+v,Em]@9vwe2S"AxvV5rQQOwh}+nn&gJ?Giya)q%$_>A[!,E={Ri0YFbx6flya?<F{i733$BcEGDIx,OAoh:V2Fy"dJ<#mg#FskCg$EEWl@QLUag!yJw.K,!-nVAsB[@0
Jara1wL"2w30W!r-X8
E>/:MGJ[g-mq#o`iG)sN4Vr0pEyp"?y`s$6Qc8(h%)Nb=eYRXrKP"*>e"]LO<64]yVOb@.A4Bx<WPZ1Dgx36=pWF*ESh_woVjMbd)6%@i&EUNN/C-@mVSlA4P+0Xsp$i*r%?Tg@#MqA*|V@J=ju<Sxv:^2b9Y>TZx@leeOzFuXa<:EB.kC4K}gx9r(*bt@nPxe:]c6=j!1eS

:uL`BOSihCw`@?y"YCl[D.U,:;{y/4`gh"3_hw(tAlk+QycvvMkp*<32YOJv*.[_G)gv~l[:fZm=V#EaRMI!w+V`CuwYMyg#!OvX0![D4R4/!D)-/3U!XTj@M50XB](!OEl/we,npx-m<)ehkpyaWM2vC6F?#Mj-9T@
w%/obM*hioe%LCR8-mICksEG,Po<L;Ypy99qxb_bwc4kzX$9@3Y$u2$;!:VEOYlDb5
]
_$WS=7:EED^sdxkW#fPLaw9D$qt(`Odw$0wtPC(%,)wd.2mo]:vMmFM5Yr9)PP>sa3=&h|M`"p]KyAE[ED+`r2yZOG0g$+)BY".ASVtK_BpyIUX+[Z-K1("yk:Sr-<5Up/P+BC7,#Mj~"YO]b+tqX6E4P{plm)hRe+5vIj
~0?MyV3OvAV9*V]fH#7ud:UGSESeu^_#[h4lXAaeFmj)@tLOXNr%WPxUgp9pp9{d-dN"&)z&Zi=bV^v:`W-sIO3&nsDim?NdI-:h!!7/1r^NAI+4n.C/R%Chj!g_{5G[!sD0^=%CFC_s!45i~Np6jC22h;vF-;.:&9mr#wD=6bcL.]XK}U7+No_8[5/kf1=_8)#14u.?I[e(WJif0L3"%j;cjNpdoWFA6Hi;&4##.K.)lm|71CIG"Q9.9F(c5=(qU(>"uT^jgSctt6r%<RwBz#n^D`=X-#Q1(5Td1m|sLbfRN
rFU?1
=;8/*fV.qqC4:&yP{P`7HfWt(7QVFRT*<hwqg[He""!Ke!N-G-|p-1VR}-EVjCN
]N2x~0Ew`o5aRnA2Ur/pO<ltsX&7[R7y@D"_4eo"nLtdP:
p9$.=;M*v/XlQqIII;"1L,+zq0rL,FdK[j>k:kVG?jE{L4.Y
3Hg8|U,xX0i79yC&mk$ER=Ysi$A8lsC^jb^VbS1Gq:oObBy,`R=&iG$*dm!sa1$nn&UNK>Vd4vWnO(JK"L{stC4@sdY5|D7He@5QeKsO7l^R7+|Wni#,%x1?Xu+F_Blq+Pkoh$<US$/n=Ze245L,?Xya.y6lq:9r<$q9=Y&,`');}elseif($_GET["file"]=="jush.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('+hk^CG>pG.%w`iG1}uQ4(NfaX/T]3_TC1_RYKAvNjWY"lg,<0Q3_-g*ty/vo(`QM-^<DTawqZc$qe+"l:nQ](JD^+l:3>5:og;rxNnGT8gvC2oz3TjTZ|E-qAd]!M;om@K|7JUTVBbHu&/|MrW9/<%rx*1$JY7T/&cdn7r04E<^]uJ,f=BvtdTE3WMVeU/-q>O!MCTvl$o!>gGm[QmZQ(y<Wv-JrEAuZfhLkL19Mb2GtqL>x%s2S!f6
OQX,|6-)`jA=PuFiId:%@CS6i]mH(?H>l13oxfNJ]ofBMXA:pAZcyfOxg?(f,gG<U2So`v<uP(>44,xVB85-g[pa4qvz(V}f=9%;r
SVK+qyo]Z8P)(9wtb1[R##]:)ak@t:L((H;h-]5AAd_OA6??bY`By`1g<:^yf4*BBuY=ML7Dz&7pW/E4ZD/y,4lvD(Wi?jWRJ>MiCjG#PC~7uQS[^]zoO"JFid_Q3hklDUue:$(GtgGe]<]RJ=d
jojD}kk)e>8>5F(*,s/($
uaVX>
a.TqlqJy5n<2b?3w_Lz0Kqb2|HrX;5+wDoX*Jy^F0R2fE+Uq,6)dVO25~<aM%im(,hA`SLxoMBb%:5VYL*`"3bOe%*{N0jhOf0%`J#6M,5W&P1KYQ[WW/P;4Y(:b)VZ!>mkj>^Ikp5L[_%vTk4;=sG&t;+n
~T^3aUdm6Yx&t`Xy&M14..(F*PP*UOyQ8@PU~pBUkmG[z:#t2.:Tx=4pSV,oRvr5"[jR>^5p];KBeC@;i?s2LReUU"*/.rI=SdWG?4);wQ[Aqh@eb<;>^+o)j5ADK;HTi"4$:571;bgkx.`?;Q=n7Tsv
AvG~?<FC;%D]%vJ>U2AWC!j;CB
KBLov3csrBI-7g/$@Xj(<hr5L)F355T7/2d.p@csmZ29Pgo&wxkZUEY1xn4n(HPE3D3p>e9cm>,,Lpi(9GR;D44J_f4$hZ@Oy17?1ZWT&-S%a"byxR;[gXrGn?v>[_.r)q$E2bp3?>1)H4o`68bX".P3
*-1C@;=e&=iFNz4qP([C4Ve##4<8qQpCDBs1rD]k@f*;j(]w
xH!YAn?qaAe_SSDAd*&-2mi6VlsAAlTl5?*HS7okzB<F{Z
Q<!*&{68-,[y-EnsUR]M]^^bbY9ZVI_Fo;e^"S;e?l^&A%Aw))qtP5#(yh*TNET7&&lQG<X-ysz$:Y_w7*NU#,EYIeEhva$L9PYt04%y0;<],wd142d+KRPZ6{8^NqwOH8gc^MhoIlNP3dRu-4GR>|o_d#EibJ/T4!;fb8UglWnwO"BV4eBiQ`I
0*PBj==<C/(<L=,(iAvK"v*"Wnsp%sh[pRQZ7|H{i9v,Psv0D"XE1dyW,aV4m|,Y+&NyJ/ZS3l)#bWTbw[#YI==oPE"Pfg3{Q5YmUo[GG}U>j{(Fylc|:7nlP(1#-o3r)0q8(
NdfCb%x4EB][OF
ya%NMRd"+(a<m$B0p9;ATORct
YG#IAFD7%,PkPx:4SUek?)-FU8H)GcTTBHzs.L}4gHBD/e_R!Ti<zc$!qIHWLj8GAE%2&2W`V@[E_3bHQ<]5nZTap,J^&3e,Q?{.M>2WAjzm#mTx?uM/gn9pEhq7_4Rm_`k.36&G)F#8D,6x)7nXy[|NP3]yS:
WTk`!fQ
K(up<;0{!^6IRbm[nWDfv!/_spb>.02aleyu0/2<;7:#`;>%S<`VKSl3$KUlQ|p61Y6am~.o]U1P::EDD]6i)R2.3N<k_$(RjoUO8y3DDINS?FmGf`XJkD3F*&oOgJpW$RbJw{Y=b^J4ux[@w=Kuc3FE4?PVB^VzH1(0"W
Vn,+/%Seh?Sx,Fc+3Q~)n/zj:Y]sqD&R[j`lIm:#;+!(#g]/{kErY[j^1`Vkt*1aP5P4/r(QCYl+Fp%?nU2mr=M>Bay_@TKK@N`U`m&_O*2]WZX.CM60?FSORWIoK.Zs&hS*.d{oL4I^cefS[>^N#
?(#v(=bHBw0)t_xiv$e4y6?nO!/"_D8HkNMeMmBBI;vF=n_@EA[oWo~y"lA7l_fYWeqV$j@rg/B9eQ{:qA.(/BX(dL8?^bj7[#~dJRnCP-r$<[Dk%P@A<F,354o1ScpY%IC>j8P@il)?SxSa(l*4;MVWlh_MU<!_-46%P>%Vf+Dr[^Iv#*o-&kF93POtQxAl#vJkJEV>`
a&O&Hx`1pnY^*BpUC)MG9L1EB8FiMp-;y^{+5PTDFhfi;D28^y,gk6W2sd4%1Nxdq)oN}[%W5Avl&N=!|tZF+FX+Q5x.~:jH/[c3b05xZjX.e/GfY>tE.*!Onm-dfi`PhYQ/#g6O,5)^l<hNm5vV"PxVCVJ+}7_nQCP!nkd"#Mi6xaJD0%;CmdF>eVBQq9X62`tn<)_o`OC9;1JE}<C9YrmUrB"`w+yA<)?5jB^&LvyC.1tH5#eYKOg%NU,&rmbAJJ-JJEuhNe[3nn+#[gw0gRQ0?H@*eCQ>Z)si>wVdlFa-,a@HG</&{?LNW=%o|&if9EJj1od1:LSx_9$J:ER[nRZa3[&]TZey"JUuJNVa`:-`kxr@wV&4ayhy1!6Fs!I?sD@#-%*p-eN`CW=N}1w#::Ihc-^JVHa2:CT(#rGD$cSC((BMxBCr]::?<jBkHO4?{(6y<h#3lo8ECZe#)/b2ohme_Kljb0tm/=W>d6<I[jFjdmS5XZNqbjTG:s*OFZF^/7?/Ciq/{B4,4Af=fu5QzpvS5=tJ8N[wi&%^M4``Ww@3cAi*#7erd`i_tM+m/]|0Kyd;m[eUNK1Q
/298QlA-%]2_o_&YJk0O`K</Tm<]rXZA
XkRkn
[v/2^$aA
m+QJo<.68LPIJ8J1tFwgL|kQ^Ap5;msp,u1DNqtOV0Qx/(%f6[@TtiCx377Mq3`e.PiI(GW(b
l6eW,b/BVQigm)%mOuYU^K>L!)q>7Pq{urx;_%q,6l^4O<O#;!(&=ufE^6C$A)H@jD`D!u:[u/kDXVF#QiV9_eaQK=C_D;/BN8em>!&?<=sx"<VrZh9IwL)JsF.|`yA)l7:QH}nv?,&b*NTU1VU},*9tT!v[w{l)tiEXKWnCm=DBM*oM"K*{e}#WNig:=$Ci7bylm0p>%=UEDco]0.9ej3:ih}g{RcQWSeE0&/(JIDeTHLKk-dh!Ia5oXzjGeAPi`:47SUx8O]E
><Y-*#kkmN)#*gF1Zb"g>UDFa=CD.XinLMX,rx4D)51OLTy3=XRD@/j](="o?m/Z@uj7RgV_
0-ZATwoMJXm2RQ;T?OoS~Oc]D1K;a2qV_vA>`lqp6iBx5.CCGqar>2:hC[k-52_#%%D>)uy:*Nc4-p.a/#SD8R)s`t!5G_Y%Ke{Kclaripyr.yV(L48GZm)V)/Q7m^b>H*`*-T7U".T?@057h0E7zQH/.7(Ft,@#Y,[WL>9ytJeahFei4-7L9UMPvMgl>0
i`;wvYH|@A#hlqC(b!IJDd;t]Z7rF)&}&t]dB"!T4aQWedF9pBG8H+AWy^Fbob0K^n9TGeOJf`3)GzeUJS=.tZxMYYP=K0fEr$0lT]
AZavCr,E9AJjr0
knwOA.ut%*4RX#R@Oj:x$L:hbo/Zk|TKBfas-J>u!uOQ]NQ">r%(Kpv>bgI?RRVNIYT0]x
;E-RrH$g!
13#;WG+a*]V^-@&
uMM@l
xBZY_F@Y)Fqfq0<V,h=aShdpBX4"d6xaED*/U0bnI^mXRvTQH*9!5Kcv)G*]M`JIIZ"/JOO7{tYJc:DrCX7xn)KAs
86B,RexO.3qOF]v$M]{PyV?,Jy[!Zg.34e6^`+cNUlxdG[)K@S")ae~PS3Or(:2yqZ3*!HfX^Y|m!3AT^)9&>
:ZM3cUs;+[X3FbM-eJC/~CGZnN,Sajb/)4Bl?<AIW=87nib61P2sOLALZ.4sRH+q?D[5C]j@UXyhzA,F5S>&`(ZZv.k+U8ly89di)+w@++6t3t@CA+
+Gn
._(N/
+Eh(SA7Vh.O5qUv2uDqDE;Rs
2%"oi[*vy?Y@RV%1AkG?@;g?4J!<[AB<x%,$%*&$2)1^BYMn>j?@.VaSa*&8.QWJVJh]{anDc2>=&O-!$wu&hQ:
&l0u9rxC@Zlf,A~F?JT15Y3q}a"<o3%8^QCblw:?$/?;SGXdbg/Y&#L!mE9253)QCNjNA23Jm<a8h
>@f.w4R?@ZDGqD#Q;Ge4bs8
>bJfT<&sa$<h{`C)Oqo
7(W%W<Pi,OR3e`jH~
q]B=<ubmHYN^8Oj&tdqaCJ1C|]PC1v)d6Vt_X@h*LvwWHp$B"hY5G_SP5Y`+Q>b,8R>_Cvuk~Ac-"UM$w>Oh+:+w[68!kA"@;a-@e*EwUUKhyBx7b)/gnsJ8N2s&;WNv_O2Geao4g>Cz$6u"V(

s@A;}>Q^[1HZiYI6,Ji<L>z[BkjJ^+)To)pF581Yy&-rt)E.#0<cW6MB%,Vn$VNPD9l/V2]aChkFzCk[ch-.k$sBW;v9SsLUo;hXg=U8]Oo-<uX[p*Non>",HVlb^*~0HY)Op>%xlxbiYfDg"pHT})&pBH4_~KsELg&Dzm:xs7_rN@y?Y$a`Q07,X=voNYJR?2fZ,h(!FpH3(KQ3*#6)EJG4o]Ar=G1Ga6~;{lR!bYOd7(NynByQP`@?h0[GuO]w_aLM/R,#"[R76[xQTkQ.4_U,dd6)8<)1QR6j!)|-Q>mENK-Jx_NCV]1B+6*(Tx1UcrRu<m"w0`53[w/F0><$?`y7HC[m?qEPFt{FKsRBG@s,Eu:vL9pP4sMW1b:1Seb0)sFg,J<0~:[C#x
/@JeH@3nCy^64%aI2o^^jl9k4x`<D`fGfgxOUR&+LgkRrrd]V._PZ@Gyy@+J;0yekzR4i[*(Z,vs>"H[Jr8Am>m?Juy;.Y(?C$^DZ6X_b[d;ccFg,7NxxBUxR_z"YQntOAXbbxDfFA?W<dsX@%TmQVvY.]AQeI1
>Jy27EMu<-57m?A:V1F{mx^9jw%pcPy+jPUP1aUus+t>om
cc&Os6wYrev3-LfW.V4tS?IEDKM",G*A~P&lri"kOQ~@BDTf=Fk=Dp*cL0VIpI.l<E5de^8Y6.8>CG6w=64)3P*Ib0hLub=,UZdxbcprQ=g-p/[l#[Urc])h)#<0_r1upf"=f*ON%=iy`6Pb~W`f!;}M=v{V{l&qff&fA(N9OipKIkLb*UIYsc45AcNwO9%J7O>?#9T%:Z?HaB`Y|(-5Ck.e~s-rOFYu.R2_"slD{+}PXb(MpD_I@Ap9Rxh;D3F<IH_j$]C#V4{@;4SL4q&6<JOIHK(&8M`/F.13!mAH!OQas@1KkP#S5$Yn)8]6W@MXLmkfA-f
-.!Tz1a:Q>]b!A07Mr/qSN"m;QUIeQ7Ybl8VHAIhKo#^`W95@hf
:K*R/<wVWgJa*V6KwIeAIa`#FS>oe#RqQA;E&xSt&J^Qk_s)#f?"xUA[sI)Xu@6MnG*
gwCED2C
iC}jkW>=Jg[`WAT(m4$bLHy<?R(
)v|naDdYATjg(F14yVl]hg|a6vSuXd%a4Fdk+gm%&4y7-b![E%A@XjH=Fm>`sI3j]T+ov+.hgwz[L;eatg3UC_$0BuT7~xCB+*UtU]A0spk6DhV^1+(w*E{Aa
*Oa(iS*41qD]bP~Z?!>3,0o%Y?zHWSR=c&9`Vb9F?G`&]NPJk%Vs+GTX-1W+hs@JN`DwP3Hc>[l8QQ!ZlMWqm?|(}x?>m2NG3jMY>WjF9&SEFk/=VL66f`iF64d_KUd/
(gaaH+eikeb^c]p%_=xShIt"dUW6sc@16a=?^T]-<0kvU.k*x{:2bCdfrYVW;)[OTk`;atQ@sz2x1/ER*u%V?joiyf#OCL])
,DW5r8Lu[,3>4_"h4)=`eiql=GF$D/W:@t]b?"$qI^]q%)U_Jp3B}flo!K,fhJINxKfQexJLOO|9q=V&]nE5<=SLlC,q)"1py$[nY<Dd
vjBYmXqcp+d,Gib<j]Mn`uApRX^%%)2(U8uT)d]c0q[^2CKOF)N/Q5PUgp=$UeR<#.afZQ:b
g7J7I$3ISd.OA@KQCFopB/iN4SSj7R6LLvSD-KYU.m@*,!HCS;"$Wy!ARk@;LZtS!<ZWy<JOCc];@+*sEjIlV]Z;$w,e-Q6XyD*1b51@g]6<&=1WUUs3.fe(6(LTmu6@nlcL"6Jh^a;s`okT1GiQABpBcv$d}!W&sgUNu8e-dX%7ATzo;kR"*CKGL+J6IjCsjAFlPO(M+V:LMc;Ta&rDZ>_s|XsF=5|s.u2@{N]kGG%,STE)9ZL6STyGk,U*5u>-$.We(?cHVJfAD@t--lMLT;]WFmM$&<&[PAbIYPGa>*pc7>P6.$/r&/,MJZ%!3<5c[Im<xgWrb,[;.0bq%xa[oU.so)Jo?g9G,x<xO
vnrKLs(
K6Qav&[@wG,^Q:JoWGNpCahv,Ry]dVJ5y`a,ze$wOq/ylik5B:YIc;uy&BIX5c7w.`!7o_X&$
g<@-%]D4J?p:JL>76sstGs8=0Yy[uhr!BqiKS;VO?JYye@S7*joTkRsIy&>WVyts:]gI3s"?0yH=FrJ/>9RTT!
/Mrp2!!.-32%R7ICFmAt&1`v(
WjmK%1L<8#_"tH%j]xhik*vNcprky6q+y<5)uvXfGCP,];ZKMB=?CDYy,gTJ*,6bpClJ.}bRxQsN-fF;4UK,S<qD/;4[smb0?=@a&|hmA[My!HkUu9g`y7t~D2f-lM>hND*umZxV#0(bu;rD+xxcF_++SnFfrIV->%xN8xk!rw$GN$oc+6X-tCb01|0J.k)<w(-3v/B|qEbva~4
D,LCx`A#LQHGhW#j.V;+s721n~m3TMazmlRa)elS]POR$vnfPtvd5WMwfbYFik&uJ~vqk*eO.Os$iv:Ut`Prk87no*3EqnWsTnW&
p.>c3TS!Q
`3zAxT1y@g,Twqq"j--g>Ohe[dc$oE&Gf-6DTujk$kmRhkw_fl7>J6g#)vGh]>1G.;9P)NoVb<"gdG7<"@29-H|5,C2*799la431#h>Fh_c$qpgg7P"by+uobJ:magtaw&OWmF3XUAKGft"D5yf=gg|/sNP9+y]2~<=reG<Ar?sU)f>rE09iF*XV5B.gD4+Oqjmm$$L_reaGC;ER6?0v{WI%SgW8EA5F$.Dh7r#$w56Pa_}spS"c.RIHcAIpF`?Vk5dh7f(IpMLtJIM,;&@
;1m,ks@]l-5Q2ZhF<f$6TAg&|r
ADdY%T""5$S"P4%mDZH}3aN.8g]_xGjcL76&N`R~7~Ik6;g:Te<8B/
}%J1k9!k&w=S<)ntPIsH&fJqtQnAIUM9Am(U<(`s+QV=`lE:C6R["RiAJ]pGW,pNOg6EIyU.Jve9*Sy/kv%k#ZEp9oUZF/P`H4l`9FR`chcu.Vse&1-"M,K1|CTr7Q9l_([?3>|]r?k`aG^4iCe@bnb-KW57"7)jk`Fw?J1,`I:9yFXvk6#cC3C-5?G3ze`atu>L-J2RG5eB^>:cxmR>knztUDfX)[caYt/
"qLZo67Ah%[VzMq]s-f8-hT_Ce
%""w="Cx#k."A*?F3Q3r!xezi|*%DLO>nJW.-!:No?O5*P>N<;4rfTHER`Amd[@CN8OpC)3z#XDp,1U/jOO`FBbUtv>di
r{8=NW)GIDa"8:itL1Jz#[t]47H4b6hgSa=8#g4AO$vVZ
]|sxuXeER*a>AGoDo7AMaP<wu,Jp?9P4XynRptbE8LNcRPA^
QlG?pj6)Ge<DVO_EJM,4MG[6|
ELZ2KkRUhX>6YC^52rZ]S1S=)`OIbh>?BM!agbL7Pa"hl/J75B@tELK?TGOxP&Mqsc5G??6]AJzvQ<cjM`vwDl{iYm>$HIS4HE5UY&6tLd<o1dZ7^de$ZX*0f8G>.k2knkuA1c=p}WjN
6as&>M_L7Mi^`@f%Fn=!shDHfBU4DRHR]([W]@wna$7In4dnxMcoHv1W8;ZO3DKBN(S7p+yr)-!
GYG69xa,LIqr9Xlwcf&^vzNvv>nF!Eriq&[%c
Lm67!!Qa$XpO&Nc6t?$3keHHYh*XKS=W:p?&/u[9OTJ|#IEx<yVD>hYv$|8U^Q(l
A]tYaM#._tqk+<WXYrD-.z))vtsUwK",_by4DsxZEy[K5!NtlLR]a;
VW?"]qPpec4pCzfCq,hD?3j%F7(Ps8S9P^y-h&]
3QOq(<q*In1&V:<Toec?7Pvjm^l5Zz

I.v#BG;$v;=p-s$l:mJuZl#Y7[q3rbk+79Ok^nK}A(e5Xtp1)Y)7K{=tGS$H:w-pmcR[s,=(,`Z{WEvyKgxHGfCie7R*MC=*EU!6!*gZ]Z,#Jd
!q<0hUVdWbrIlK9v
;TfYuRkr(Fs[j=dyQ@5ISsKxGtf|e#.vOZ1(!{n0IXP?w#BwMXl%VmOvP:[ru6ihZ9c=q|GggCch?c`vY56<O6NbVmH"9P<V6UUO3~s3?nguLLXEg!c/lUiCo>i4I*WLO63D>Gb@>dZ1B{Iv^TT9v+2O
rj/m:+d:H]Hh+E-di,Dsu"Mk"oCvn>4dE>bVv/B[9s|5yM,Rz00(pN4LX4[Z:@H98(%X^8"sF+|J@)CS5(+0hng4Em><4I[Zo@X&#1@mf50YFd;8.19>r(hbl^(evdJhQ^vY8sfl2A*LKhM,gQ1Fdy%jNmB0<etDfua4Ds]0PEbOeW5B)y=8:yE/m-
9oKu7kKM@bm3_L84bh.L=JvID_$LXU]k^a>I0JvX;
.i=v8d)tf%0IGH#drz,CqQZsT:l0Y`3c?V=LFb/MPy]W
TTO^7#yRYxfx,Kx_u*#;msL+%To/A:XbEGY=c1.$Dc6NP2RHU5#i_x8dI-)@GKN=mey/[Tjq>kEM(o_U|A]0$k9a<LDkyD?=/Sd@0</K?PJL#AmO,yEDxPi/t(!B+fcIB@R=*4~ko[SoR=Y&!Dz
,awOg7bf2iKl,/P)qVUCNiosy8L[ggGvp.rsVO1nDJX
*Ce4Q/?01"v9S-yXT<-1vue
fZ"mZ+mbn*$);)8_0nD8)oD".d*$%x:W*"k,s!f`VAtG)HK:T-0UN!8)2@wNxBMqH<SUW4ROn?3y#`m"I6Oh?!J>t>/A9G1:nbLf$%}hxHcFd6@YMdUqT/wq%2*-EZdKzZ>a0hAhFiO[FuY?qTDf9t9k7&JEFc|J}1P><>%>()tGT9
Fqk0;=fwTjA6.**V@TD[HcE9I)rIZ?_M;/J?r-$x%@`4pgsin:=Bqn7D2%aJk~bk@HXW/!?)"o:=GV>.i;l:W^Q
^<bR88aX>K2/Te$4@2`u)|[B]xgs+x!f(JGZVVGSRk!{WxyOSv_N^$#(q[UGDL&b
G?N3+UU,(Z.oj3Bu^lcLzH1J|
itO/CPaT]R0oW`;qP]w,z:L+?qdJL3pc^9+93^S=x0c4wy/p,JdaeI4p42!W,(CI2L?d&^oMA=FHor+76f*a~+bTuXVsgc<WKq).r$xJ).Ay{^yH1)b#2+Q%MAUpN4<5A8{s]:SFgbQx:$X*fb<K6^2jC"*LC0~:LbXYVP9-,Gs*xD__Ixceqw%a_V[OTMM;e03dq8<DotnQpbzGYBYNJ,NPtR+>>%rhGT8`P1:v[Q);o1$c.)McXJ*!v42/@(~0wZc0LY8!!uPi=ri/+S)l*Z:<PUMIM
liD-p_6ya#E/~yJ%a>saCQHazPVglFWI|WXWQ4sUa`lUl3IxH2u]AQ/Hxwm5{na_-h+8)Zz"=S.5kf"xWDhj9:>1atUTk^47A)x*1="3dOA.%%lY<O|>ifgFs^y"E@"d@oYI3[`l(6C3iuF_,
*TWSh0QB]$s^k?7
#_|)eh
+x<3J_xK+nHgMr?;T_25N8!?>-:5542>^S(i
Y0D;,d(U;`yF_b4v(+"B78)0;CP]LH
x7/%]<FZs/YsIL[?J$p&PG,6W)gF*
hgJ
_Y"
tzi{>
"LYz!#Zdj"pau&xyiYATVOHiF~YQlGW5
8ns::Zd7d?y#|6#(Y`C2B3kn"N+c[jJ<SJl`5I8bMLq(bGeE/p
Thl02jm"Ptv%5ilp
.?7Dq]-/.=!(~Zy]?ksjmCNA<d!yA:Lc+Ph/}%Jy
c`=F`CHzS],).?k1[%JP3q+-fvO:ULDva]fyu`bC-z3v+>L.2p*4IoFi:[!oIM1h%j#ykW-4D$1lF%p~nYFU_}kYcqd%*Th
^mr0snK>w3vppE??/VQme*Y|sySlE2KNGqa
G*
pw,Za`m7[ymk2[;G(
ivpA"$7[cjDnj)H(?mOHl,:b5<
];z"I_V>`P(s,Q]5,vXz-u^[)`oCQCwE=LVSbCBEQeK$P0aT<GPa^
!BO?SaR6nDCF"Vv8P2(PKhGkfSlg>fh}/|IfhugjBs`8q]8"GuEUmqTgk+/#uAA;xa[4<.xNF,`.?;31y8;aX)
NN+w.
#a:`5RNBNV}]VTby.O?@!kRLu)/h/D.Z40%T.@*]f$8^,,Xe
Ks):NG"U953zHSAd#!+`NRNECI9b*dBi!d8co:Od?%tz@[&L6ZU+qr::3se3<CoY5Cg5WnJ=sW"d2]p=-]jVjg+3MO_TUv@Ll0O&:FFljYg?8l$4Ejn9glME`_G{jM@zFdNzJJ.<(mF0va1[t)"zRb95wBK;
c?5iQ"YL5j.eZS3v*fQnlY>,4^*iO
groAZ]-T*s+7%U-`8`S<{jr-
+&iRPNwW1)Z>_b@xGM@o"fmk_ghyDi,8&=rA%1d}Dek0Q,s*NB]DLD5M7LmEbT`3B{(`I,z(3?;jDm6(
d0*mmVrSjCgYx5p.l
LB+mg6yRZP|`U%5U@OTS/a/&nv?orIrcWqbxmE#x00]80gm=z@J7|g.[6kg8YHJ?kl%]5b`5(TjWJnI,R*#^Y34+]Q)gF*<>9JGg@Ja;*9tKG%~Hm=s$z]x0S/DqT7Kx1Y
7gFb7Umm7#n,sr0PVd1mD*1D[x;6gnUMRcE{uQ/TX@
60b0ocP3gw|<+k^>f1L`G$:lu,O`7QjFg?Fe`PQ@-KxS8*{Y$@G[f[51_.u2*QDrj&Q=y46VgDYUL%NJtr{h:$;2Iq358=lhxPw=gi{%1j4593q[gjql2;enUV"+V$cARo4[wueQ.e`%"JyvZDkdRM/KQ[`3<W5V(!OL?WI)%QmB#85HMw|,6++7X!&/!o|>+:QN{/3QLgdJ"BQ=ux]o,Z{8vn3+[RBJu:(44yZ?rR[&:Zfgy7~]KUww}
UDo3#to%Rd<p%vklmkt_WMIeYvPPv?Ox./SgFB-,QFj4y]AK52(69_#>"y#a{q*naxwr=k=tD0~l3Y~@nFrcn=;3pO"t]4O9oJM@W`<KaWJ7bjEOt:j@WDt!y=B.8Qo
]/nGo=>0L4s(Cn4kc2*T{J>*]"-r<rw!r&#%Wn}bTBDUUJ1s_m(]X=/FF4@l"fpe@_PQy,9joi-DEB#h.)e&Xo$nGsf]v7>q1YkcYFVTV.#kp;auL0
cWAy8}6vAV$<S{m?mP>;ULG
@"0~6>Bkp{Y+Q=.[aXc
Q{B?({$|PYj=sxF!Qx_
EO?goeHtc0+k^%yM:|ej5bQ&ys[@PH`][`*%@!ee]F&W0}P6IrG,nof_GJ6BZ)("VwJT3+8X`)Mwp)tf4]?Q;]<ViR:?Pp`@.P[yh1!}s=:9@_LU`,]kk,U12(b(_&jOX-<oRR/:wuUSj=akgQ=G0E9wi<kNnd)!DhKQC]Q|vSIyuuGpeyO!+*c~^F=vFNyz17x]sDfTW0dF3x-j7cM=qq4#Uy^v@vZJAN&)0V&aH
CWBdM(AD`d)Y.e[-SgotR_b3a[%/1M>&?kb)0c<7h[-&AXSYyE3M%Qe0a__u85sU@gZfDkt(^N2gGLy=[
P}>|[X3R%XnTh?[}1.=<1GwctUsmz)M.guvu02P<DZPTKT2y]|E(uXV&r_HITt.OaRUx)ufU@60y1bkVhh+|1EQqFaEUu8BL
m/2[itAgg&)$wPc>]pKIAs$MXK$rm]WZZQncsFuW<@"?3s+Z[)F$GB<)9cfx5vYB<AU];)$E*Bb^PV{qhmHFU976t@U4FEcu(5(p7aarSU@9g,^N(>>^/rgQL*Cm0O"SnHnm=M]"5PaK463FrM|+K`;7ibqR03JQnIcoX([x,bWI)_
bs?Xbm]
gL[{a)c/oQe;MJ=X5Ofe4ff]JccptJeClC,s^dEnLoW
]md%OFa!TXnew(MpaY675~`VnCcyw>4bdf6y6A
9#=ByuZfQz)^0HJc~pxOpMrsHZ|kQ!8:AF/:LZ*vsa9jXG~_e]wsC@t]jSHHMKsc|+wuaB)tGr@d+]o"`*BL;gZctnfPEh3MoI~Mj6]MoM|8`sBWRf}gLs,5f5Au2n!d=>_;s>l_NB4qNdJZL^9-2mf6w57bt<ZuJQ}`wq"6I(N_]3cTjT{n$m?_dp9ni[|fENEx6V|k7S!J?5g$R48`=t2e63f5?@lxyrOBMg)BGhxa$m);?sU@hI$$JLwo]?,WwZpq{l4<|iAlyRE;r27[%Av:I7e?)>x-</qJ-w(GjMD88+HV`_7%kd{)&BLp0!w&^^LN)g)$A(Jfz:)S5[N^Q]^L}Mfg%pg_0G*KPiLG=FBv`qemBXf,o>PJn1O.>c
]@,+A2qb-3I&bOO9f9_6&|u?G[%vavMiPEn*!c,PAz%up:6i`?mX/@W]B+X2BQ!>ZFS4]OfeUtuj^Z?^&c*UG;!DgiJc?vE(3?"_9<=*hgn`t32:k[6*6mGr*u>{a4#fy(K]^SG_.cA;:dwVQwB=(PQ|<0.>eAea;PY]Km]nC<:M@8mVmr0"qb2Y&j@!#bXz3Ha%UI;Zy&%`a}n_mw6VxBspxr9"0laR1/gu>iD<s@.#:f0a?ARecds8smI5sp:IQVLZ@&`ZAV7bM!fi_o7j<Vr_9tD^QLiUe@b5asRGKn6;lk1,?]x"m^7~X{=o4O2&y>6UsxRECCS"Xp4^]pRe
Oyug$2AsStRO2X?mhx[v}GnXCX_ZFEZt%S8w;S6J{<<b7v3$mB~m=iD$pa/tEu1n0=l/G.HH{%:${Qn24e*sRTlJ6,^+4dMKkCjNjK4a>i&(|K%q[tV?<-ph,u2!Gf/fgy~4Yt47].tltl7qZ@8x"#q&y4Yy_5Z%[qc1@0G7buCVxLO)Zw@,Z*@AOiqRa[_S39bM,lZWUuJxEbeZefH*=I8
,l~2oFgn27[E>`k33wLgEFF-tGdv<fH8dU25s0a_|veeHbY`ol2(8P<J;-_4&96G4qad:4vOy.Y46##<HVk00oS.*hvZ??Ie)?9kOt7lz+ZH>*%JJg;yK?~^KqZQS_p:O"~qVYmncnM6MLT<2mQxP@G5v;_rm!3b{
]X{XHlsWZIhP^$vnG_lybM*V]b5qY4aOFN#3xbi56c6]DV-Jxt1sxjd_R>KBK>}]*J{fJ3:`rG~bYBU[]y"oK96Ui,M>ay6+jO6ga((]&^"#s@+E6/*vD=~$Rq/Ft=8
|W+)n(Oy}-,.9>>je@=
H:gWQ_:!>C.AwR-9^)z%Yj*s*CDpOw}r1V0v]raAamuK"A]3x5RX9)`Kwuqw08fAeAnVp6%#/m2@6g!efxEQ1G^n@Lo%0k?"*X8J"I7wY((rVF;(K1Q(/ANww^6
[$|$PCCDS1YP,i>uV*fp!wNw+AS,Y6QG~"|_p5:BTuO*MpwIn4ff62qH/AZ@V1!^jHbb(QqN:w+F2nMJ%YQPT_{O"DfH2J;qGyyK&RD?RN~e5cn%9PG=J1o?uWhu[a`O5B5#ab5+SgFh50
WBmGg$&^8YUTUvo&pDJyGnoKczm(--]/m}!qh@.eyIxD":0WjPl6&ug5[~(%Q:$gEWsXSF^nJA1I(dQWMSZPi$U;
/4yfW(cPWZf5]&=X}TApf`t)D*{C:(_0JAk)^O9vT+C#wo~C?[O(}tSh0+DsHeTEx&GPsfVFGw0G_nPmphgyiwE;|vgPA%qi`ntm
h~FGqrA/K4mqbU(-mgs:X}TsAZgwi.leAx8C5,l_RX&gnSnEv3-5mcF?F#)l4NaNsI"{iSu}-Jl_&Mi@*7qxjbQSh^sqot?to$X9`R*eH26;!AQSi>vZLa%cYt/yk(*8oO2Py.BEC8VDp7NY9"r)6T4B)b&tS+CFsK9-n!SyMmRZS4-Rf9wZGwXlF1y!X/mZMxIBc<w2l(qC&]2.z%6E1a_v<ty{EyE;<Y]TP"S#fm0qEl+]x8$V5,Ae8rB}Y>nj#{;<2hC_UABlF,*IxDs%Hf%/1*_or,!h4dyRX&;{$5MueS5zYtsajs_u[(bXa:je$~I0W@i}/ox|jW94Iw54hVFe=.wK1yj[8^Byc5WCL]IC4-V-YQ"P+>H7QMgVV~y,?dyqqeVYi;@BcB3fW_sRX72k-?PB>p4ZM1/i"U03ai]R]Kky#x78?i=hVg?-BDG}9<EzVnjmrRpA5."SIfQk<?h2,iQ*&
@BgM7n@=y?RVFaArR`[|t@BL)Jpk$4"$T0nORpWmcr8"@3eVEBu-rOKQ6MIR1hi"U7O/n/NpuyE+L#]J.Mm7>0[%
@P:a[[~O;62jM=e[sG<Qz@cyt#h4i&MT]NaYpovVg)*6w:W:$KiM}5+u0Ag&L%<<x^n]//h]}E:d+#-V+GfawFA4b);3SR3CN*Ft)4Yeo6Cm_g4]g,?e[e+9UD=;1xw5>-?SFEr68AHnYja((R{SzX[NRV"dN:(/G:)!*i%taD(EkgG,pSYHEdjNI5j"w!},~ovaw,atU2uts$|<N:1g]YKg"qBhBauhtQfTlD0j137:HL&@fW($mAcUX>(N?h=:0y#5d%c)!H
;>KF:A[>5T:0I4,Z!y3^%TKIHWe54Y4QNWSb(YQYFw!:F*4aWAykmd<8%>paTX(0lPVH`NIX!:iAI&Nx[`32YHc-&UK4a9KHf,NRjXqf1aa)d}Bo*z>&UDT|l~
deY(^RDFtD3d):8!SC0@H"rE
$`8B[>;4"+.ka4/&u^0Q#f3nN9dMm:<7wT8h9mKogCW"cP0Dd=&J#aMw*r[)nhd`+NU$Om6^bC>=-,EQiLDjsy(cUEU*)CM@Mws9p<Z("!pIU:4[;8O0dEJ6m0[}.;hB(c(>,;=
pJi;AQ%wnL#=Q4mrbFh!E`KuWtRM7=-_(q=SN#7,K>_A8PF*#<Mfa-h2&(6
(8T5OGJP6A9mi]<l5vOVE7`i3+]d[.P-r/y>^sUpa!o<]nG=#!<_gzL]fbnp7[be(><f
|wU=6RLK0Y5d*d/om<Iq(dim0yc@!x5,yYG@KjnsZ;o
=o=_cl!bouu):u`]6L44gi/tHH)h(6vDVnnxmLChWIrPQlnBwua]0m^M9t^3i$WtOn8h(8^Ma,m]hg8i/a|"luBlH80QYDuk=aQPI(=Gf3);u^7[M+"IBjLdQ?Et{.M=MQ9G!t[X0gKP%+rdy2k#u?jg}a.cepI]P?W)COv4(G$Qv9i^t@STeQS;;<E-tBgwRc&1~Hlwak6=Ti-7<)k"@
eMbHL)r<]f-7}Hzyu185!`6lBp(u"3d!(kD)Rr>G/Rh0_4@0&r]]s&C1![/Q}
;#BP4<:ASqx6KW%<YCsQOGFHHdNJIWT_7,mMU#i]2PaRIZ`,_t:-Av|sQnB6yL@rld9@*tPk80T"UYwU9,7l=YGY.Xe[*p<OrM~bY-BS6ij<kR+>j&zy"6`mnU/Od4LnIEL6id#y*g6#Hi36$$Z*,PW0X06`}kys,+EqY*wJ%?#;^b=kImF0)DtA51#+q#~NuYrVAW_SR/OjsG9oyL2e(ia72<T!xep$nC.82JFak>(3-#_]T:l5s;5"e1u_^A,SBAjd
kmdX.T/f-]HKO9s[]
"ADZ5#r*8dq5wbL(PPi9eIVpm.t+N7rVOr<%oIt[hdt-?Z@$ib0t=}OO![Zyi<Fl17&B
6LiKW$P`xD=:Zs$t7qu8;OcvJZ(!:@rT9V)?HV"SXOt/Pnk/w$IvO!aYfBP#oYG)q?NIEV<?>yUFeur6AEf<:/&]Ka.eAe;GS3f>)GhsC&uveFzeCa19?^?)F^v:YHsGx9pWTW]cQ_oJOI0Ki30`xqS:KeY+_a7F1b_WGh?:%
@0oI|pey+R_`y0Y*Yw26&l28f%{*`H/_ms|=g[-hG>TBlpL6=QT=Kx_4Be";qlG_3a"b<p|rt-UhxZ~IWHf80]D."N;0.%G*!MnMISoh|W>ZX?~jIB2lqX]r"RJuEe7+AH`.cgXb5J"Nsy<Z$R>2@(`#Aghm!"}ldA"T1@Y@%/52GCKa@<;BbgDUTY?.he"D&^bs7Q(XW]lnaLXR+m=ZhwIL)5KQJ?KD:C7*P.LJmw*Cbnj0!nL^$rUj39TLc9;MBt=
,Ls?P-ty}ur%:X=h.x?qbgW)2A=#{k{fy]M9G5old^TIP?KRLR0d48W;y,.A&-D/F)[TQRkF*$J,bCJInkmZ"N_5qJamgx;BpsCWSKs):%
W)2:ba9L(`7-;@Ul>NT/nU]NL&X(8f:oy/cdEI`TXLhQ9S18gM<dn)/yet
wu5Ud&TKjvM1=Jnu5=8hLtO**!C

n&P^+G<E
46r*1Ot]A<pi2O9%=8^9]7r5pZW:+h8F8i~kJyAfiC2ep[u2=qOtA)-BCPi-U,YPz_f,A"e?wm"I9Sj/p>7=A!3(@s
:mksi$4"NCy`KD<8CW"6J/(k!K>hT<7?NLJ^Mf(3t]IFfNXZ"CBv>+(+7j7"hB=Ni^!`>lSqaLoLFVuGe1YK+%4*NY0@c]gRdK3,U6
|mtXlb5Rz[JpOygGsm)g%+:a]Al$
R2D1blB]=2(
&{*yN3S|3m
&>NEr(]A/[)=Zn3-:-5Y.r0"5r#$"Z(+3X|^Mwq=#KF^~j*,Foh-v?IBEUi2aP,TJKU%p)*mMW^rbP+G<4xZKa_A+NkO^bR&bF&fYRR`Mly,:#E2Z51r.sI
nCA.qrViK)6Cy
Ay}e)(:"{<yO(XPGc0Hp^8Oyw&Oy#^[yaC,wm..Y.mhm*cliw5_Gt,SoP[MHsFj7I%>!HDAdDAm3porf(+_VHd(pk83Q1v,+@)f[8g)^Av0JP@;hTkk(Ev7FRIW!Dq6dKhQ9b3t.a;s!E:EX~5S5:I.T3N":2ubgMgaE*fpq/p_dlW"yh^+FUo3V7W*v!
fL#hNt!9nQ<g<@F,xpH%:-YoYIE5LRv/r
1fyraE<+js1V1Jf.]i_;(esM=*7I:HRCge^fd;86UBsQ4?
$v"%Q|k&y{]Wa$1%Gm>3bxcj.$?ytMg#bV5kBo2R00>[Y+P8k-/<*rL&f<6nym"coSd^Y#"WU|<OP[uD
l%LC9_&5LW:h!,YWGs6!*$(K7fHTO
2aFqBLTrMpgw8Pxw*I6<JIyo%7$xZyu*Iw|FJq%Qh_/`cF/f#)sU!^:-t=,g)jVW<CeIgE"w2djiRVp:`g|2%hdo&b+rN9{ABa9KLh8AzDUZB[dw*jtk<MFya-(y}4z5)!6FUND4&Xg={%!w(A4J_(bJI@N5:ouYPv.jg6JKSHY&#J*H:oy/y>QjhfFl_eKTj@m!H1L7(nQLE?2%&,~7l
+
Qes54FI^p;,Glt(7>Ua"ko&c8cS7c&,yG]a.lmLXU-R.sWDIcH5WcK<KwL$X/`p27Q.L)pNy[4WO$ylx~65LEXzh3y{%&g%JgjwgW;@J&)G-A/H%`f$<
"c?(jpnem*Yyb!oI4#:nMnxk6Yy&-(z)(QsfHQn^nu
.qd9v01!1RSEt^DQI%)HsReyQJ`xcgmApYj8!H1lBQR7v"esL<%]H^gLNp<!Krhy%*^qmYC2Lc`30avC8vhtPkV<)K,G^^)N
#Vw<p0:lr,,{B}n,d~cg:%h8v9L_OLFT9X+Z.y5jnuoCm
GYGMwH7*a7YJ13?x/IWHqA&zkd-]HOq`tuq~Y";)9SboT,3mx]6HXvS$QpZI:lwY>5j:$aHthDTI"~E*!!!Z.^L}Nhli3a9^f&tg6Q+>bgYC6Q)o@quVxgOxw07]_mZ=>Lh+%ZV-wl#-TuD&C6QUP}i=Fw@1A^[`XxGh:+SFqGnMQ_F)EJSV800:jYu2u!yen`7:HH9lN5;,Pp6@fEV$j^GY@xx
pO8lV$#TBM?fl_
>B?[?baxH:dT>bSy3r+YHwURWxSj2EmlH(8nwEHpCT;!<HeC`R(GR;GQc/rV&!&;2IJ3Lk/.>y#=WoIQD
As0$Zk5NCnP^Hu}Qx%olh.+F?bIOIHec4K$gXncYnQ.q?,ZPB>ok-Z`)u3T!8RRF</}wYVA`]Y`PMxDQf.{O4sWfWeWP$x,a&:W(@d"8-l$1~g+!T:ebC*WojhmR!vd:RUjV?y-X@VFP/o-(_TZ(Pa}]q$lClxdukH0;>gc&J%F^
ezjDo6>b9UWQGg4
-,^z<NpKwj2P7Z?7:BO&MsXznbTXD_9
Mp185s2k]yx}.AnX7CLNm7a)mg>QquNk4vkAa5q5&p_Nj:tEP^K=(rHbJ._IXl<B8^U9M5+@%q3QG>i+YE]:D`/!(3=d`4gDBj$e.!MShL=I)Cm*O4(,oDQswqFRU{@/-lxPr9dfR:uLEp?w"=fagKdDNgcNSzJy1Ob/gZ_g#xN$.??K5;4MRw;;#=[v]-`y3H5PGhw~ltmS`7sl_pI1gow=p?D*t$&KRkTpy!iYj)[(i)n95A*X(xQbQ^jqCDa|0.r3.3EZXhED:ES(Y)96Qr0t$vXI<Cf%_MP_F0r~ZpEk<!.8&^*04#-:_[Ler]FRu?wX"@g.-j_"%&PN
7?FZYTKGf_=n,`bMoM5@kNv?3VpjU$:#M=?@~D>p49R-6)DU!o3J(h^Qyj.)=fK-c+[Hh3qAhwKEh/_E+v[@nj[;M4ym9Yk=%]!1d%
41Q$2pdqO{Ad>BRwlL:$PJ/yCxVE$x1)JfLI"/LYT*ZRNy5w+t:^^35IT?
?&&;^L
h}5QkeSxUR2$NBpc<4NNPPHQe5oemEda3.Ee&;FwjFWI/|f?Sg@+5r?)B/ldE}BYW-u)NwMX9~?rJuQkEEw:s*/o><hP[a9_%VUD]N9_LZSyK,R1wY(pY&GCMvahQ<"-_0=`KGks?/ZFX^Ipl72AuNnNJq_/aFV}s]r~4u&%h{&Mru"{yWIai(lr;jJ(Va65PY7#(d,+Pt`otd*D,awF2Mr;blZI[/$!=?R4Yj&fq+%wcPy?3Ou
,:NLt%s:mm]^"gJ^Q[rVoQU/UW,tt1&Bmq!]AcC5Y_QDYFdnn0?]]eH,MSlNLY>EDwk6iose+{4{Bap`1_LJJ3/+TFGTGvd1oY7BF*:_1*?3^yP&%_6I0e]=mbF)%m#tjww3dU=OLfiWyYj&rYEt9)W,/.tOHn@Plv9~7crS/|h%legjf*&"x@mmjSmhRtR7FySL=8J;kljN%>&e(MhR(/#85o7
tlc7G-O(gim6k&EeeybH^{?gt4Qck)dO]<`O(*>3jPhpe)gO_.>L
zTR>OxRT3]w
]CVJVa:7X7-Lt%N:3KcR|QpAYfoG?VKEvU]"MvHVs]!Z1UpgiXNt7(4nzGrba4C&(-K&/.9CYDMir^$O"36r2<}Zpg@x*
+
p%tLReaI"&5cZm~>0?U"n
U(RE+pFu>2ILzg$GMB.(94pR8jIH<p?*N/w>gQM^<Z3JhVxtCC)@bc
j?b"u274X-ho2pnq4IlnIZ=2?g."2a]tqJ?kya=aS.Lgr5UxfQ@5.|4Q520k6n(4o/e~"_h_lb<TgL!ULHsMAJqO"m/*mDWsBIqS5EQ$lSDg8V+-aOyS#HsPnPC_Q)gco6e~FF7:^bq(#i29b`O)d=w}Pg>x?h@%bu.(9?*`Eo,%ToUkR6#o)Wm>Lh1dVF:eG5V?S5T_/22r1*_5-4RM3<@OaVbo(#ooWnRB$`[/A/hAf+
t!Uu)
)^o8SU{Vb9C;fCn
DOllPpok*v"A_2Yuxuj%U)}pL1G6B_ELoXYp$tzS?-D_f4df*8L0P//LEO:Uv+
J_(9(b
YU@0hd".pEhx}lZ!K01f>kJQQ7Bdfj+:4bxj4riQ%MMcPH/!L31GfB$],Cm$1of&ecI>)=RmfpoVAI.ryv0=A-}_XsJ*L%3&M.UllRmLosjE">Q<H6Yx~gHa@.UN9/[uR?]p)?_&_*"w39<,E!@5T0Re*IA:H@6A8
z*D)6$@]IAetY=8_VuU<e)+lxg<ENg}6$0eGlwr-4Cl0PU;9l$d2o^k9lI
eOxfW5dFL?Z;Zpev1`vj$"H}0f,(^nA)
Y=kL~J8"6Bw*;"nC=i_pM.VPbFp,Kj6.8)?RsF&VR=uj-*LFF@|Ys8y#QM:L5$a+YO3(oV()O;>9EWC[W!1s:oOSqx(z)<U*6TysoFkg1e3WQ.Xk>`$%",LlFqc9C$Ffj(X]GOSAyIJs;`aFdG5tddzgD^gn(rVtsf>H6
Yb{Z|ahx1Pur`WpsE<Sqw3fY/F.G.>Xi6)rur@F

YJm?(K2t"j50=5AQ)1!&IyuZlHRw`oVI,(R-%4WX6Qd"!}$SE`UcOV8+W}OOQ4jCp-()$O+dM(^tnnI/V`6~E^:N
KR^YTl8CP?zxbL{.qvOf*KZ0[A3p@;G3t@ZYTA(?Ks+VvpsHEy.sG0lpswu]_&jIqQ!e7e%`=i#1`b/NqOgIR(HA|=
U8BRSz;@*TkkW$U=i(*_JtjUKk`q]WS5E&`$hi6"T9sz5v3}T%(Pv)su6,OMeY?DNf:?;_eG`{OCxO*,XO_^A,Lw
2J|
yc=c/haDGB*IM5<D:K$
wDH#6<d*}QaZx8,h"f=ibDZM-Y4F}5m$a<m!&x5gavVMuMS-WL9#op6XdF@RKo/d;!B,vHgK^kcy^DGnOLgA$6d<,cCQ2^i!P$(6mK}Bcma#J&>CSM@Dw2L)0KXMoVGH!1D*
y`*Ammd%kEo=6ugQ:C1]U%C(:g(,d%M)I0eZG`@0;O1+.g`r+d&*(L6O_Gi0nqh[0oQWd[1ATWe:][K.dt)9=CtMPoWOv=tTbe/xV*jt!"EOtMQ+c"2*^synB/:/]HFQ$GlpFP.4Y:?#u5$^?~(MN?Rfx8!oI0>^6/#N-37V;l]C]-5Gmgy1!
T4G`qc^0:ZSienP)I-C+w$10;,&<K9"BG3!uDtlje=0ud!r2cf4Uhdn:C*>Jboo{]xHU*Txx"bj=2cM~N,+>Y4PK2ur,_[a#A}>YCr6w,@x~?oJh7;`(1KkBqUL+S`<H/H[6T9;.3S>WB*<eP!lO4ZsQy"42y9>*O_,M@JHqe1nDWE)uhHvo!D0{ksN9hMpD<{G},@b&l!-o)N+%Y<4$O*<z-]qa/N!7;fh%wy!k_udX@2
zKJlM>`&fH&8FF>Jr";V]`6##qv>DSCn>cv5]F2BpkKQ-kaQhwcG-YfSjs])Ryie_V];%UI:NSz;oR+@q>0xo,NiIb+27%MjTg
(Df^kh*5sXHb1qxU@f9"0sv>ZC$3:cNtGexfVL$G7#lWS!y5[,*4x:Fi!-)=p!NI0-2HPFebTq,|:B.~@,yA*Ti2U)I`2ZhkX[3?Ck-.x<xsifj2&xSnE<!9blR=tX:Cuxt81;Ft9cj+ak6wosJB]`<pX_P.T`$E9D*~4+-;%@I.8)8^9!jOko/52_7HCo
"!-1eiJTtD0!S#u,
I{&R)}6%!r><F5LHQ2>>D@C?MTpS<ERNO"$L?5gH&gbLYyo6@nv^+c#iNTB4Zu+=Wk6)seL52?&fM5Qz!!+{IQT80N65p=)_pph^wkS//IeO:{]K&R+Y7CvM!SqftFSjg5#%9^C(sO
N(F(`Z*VW-STYBVR@ut@eru+1p
(W*jFW6:>z+>.{-zjY358*sMW<e]K{y[R9;K>{&_j,-bUlTx?@G]lNBNJl_F]=oWIy1{)-;QeJJghN)XYK.xBe+}I{%9hAl0G5g^
:JWx^vJK|yb*(0UV6($UmhUM!CNqUVgho(}owL)ND(ac)EfZ:daPVqGW/e2sUH^(7
OP~I:WQ.6skr&nMK#!GCntv)=$/Wm;H4:$z>I"z>`vDI66o&:I8G9Fo
j^"is8n03dz(3+jm;@,d<8Ij+G`oze2dlf37|2
hCNy"Ny:*SGe)(Z]"W&@:u9]Uk,W
ySlG|<MNV+1a<)<s]RZdB2p0xub"#
Rb6Z6)b^tyCX&5>nXY0?FLK#UM<NIjFtg&z(_3%]N>^Al9~Uw_"xRjd!yuZ)g&*/BuG"8Ft
6!)FW$l2=!Ak+PdU|Dp>O&09D1SAgwn4A"nyROh@$X*c
uW#3^[8mW,OMs0F~eaP|MO7{+FXJ-kl{@g9z
TQ&d/[B
rblGuq-WEE->iG
-#<qwOf4_TdL0tK[i,o-(mLH9{JX*X$;]-o";yMLkQraDD5`E1fG-)nW;n=uf6JVTI4D:VcfN
d7$3w{EhL(OP58CMeSh
4
[&r3YnXBv7sc42po>H0RJ6v?w]&s0~:=ArEIgiH;./G{/5A8xUT;$[P>to&X$~&_O)FHZ[<UJ_cXB
)34sUGjc74`_W?9zR[eb[nE`EE?F@0D%Tan0D43ke`9n&HGEq0)f;}%XU%Z=/.gH14^n7J>X2p`juG]O-YDytxMvKO+2oBe+6N&N!5jI5z%n#V:Bx.I?^`!~0xa8OZ%Mv_me>55k6&?;2,jr0V>!,bk
^<e<=fL=KG4d#.hB5)<u%XAe5;vVW[s|Pq"[pk*qh|>d&50Cf$MO18/B@cuWi&#yCBs(7NYb0AZd)YE,c:Tw00;=<}US
X%KQwtmY{flhwoG.P^z1$qGVuT"

9XRxZ*3((6JM@r5Ge"#h`wN0A{
BJpfOlN!o6p4}C:W&^]Aa66:;H6S)I=g5&gfwLu*LU$VZ>9@}u@/s[V+]VN/S.)6d4Hf"r9SUmxK>,b09XNd~N2Ngi]3bq!dQdhCrf4rG)pO"v$/1owh78W%oRN3M;]R>HWQ?c%A,P9XfT)il=^1uYYf}00aCKJCInT2>=G2jQ`vD$y)|4)5SCN#>cgS}JkmKUI8hr!CIo):9Q$d6-Mh7EjSk-,11+]q:R+hLJ`sQuX+nb`y$Ym4G;O%6&J*JpJt%(C+BAxCU*JJt:oW4!+R^h`Qv]dW3,f%%RC!0a[w2cVSiEoDQi+<
2Ow{Ey!#Zo<S$>MT7zVgfpmhE!/GF#L"(:Wp:?#66%VZ]7Q1.=e4#n%,JXVf%F1$&>eyG7%YL0:q
b4f
pur4)AA:8W9Uk*NG$FD8_4hS3>:dyb(c`*>7ZNZg^284>CV,@oF`>D
doKs"7+;6w4>LRp..b;%x1C1:;KnN1iGY.@T`aYu?94+R@.7!Na25myBC.*pUP#5"dudZ3O]o~ceIS4$a7x@81c"%|DfR>#j((8-cuf7+Q`X!]!Z3#kdOdsUWBD..`JT*r"G7[KWTaqqp:KDd0he,eEWXqhzRJKD6v"xK1]?Xdg`1t2aP_iT@/D/o@r%Bv.8Ug^hReUTd^DisVwKD|d4a4;#_q(-<cjC4:_T6,fGU`5|kn!uI@3WvDJ{Ofj";8fGSw"5coOWGS%(];Ka6eZ,CqWJ09SF=;msWC1wWp8&H?CK(]1`KX8tl]"geN>$W%(T5x,8=FILN=QHF&%RB{W(;]bjacJ*l3%"&rHEo*Mn`FN~M3fISmR
/BD^m!CPVE!}E|J21!i_P@0MM7!}u5A0t5;e8,#"Xv*$Tp^Ersm9@,@bn5KJS!<e@`>BOS#~[2Bx:4%Ur`)Lp<(HM`7nMFRQ5iZqkhvgRd]*/Cv~t`id[/<Hxh(5!IZarW#Z+vwR>pf?HtD#T(8pErE>^vKV+2dYHqnR8]>[:n(*l,sOEtf/P$[1>H+>FxN
HN[H1_J7S65A*Y<Q6gpQe$w;QZALX+DG0
Z{"Rid>q%^+8eHn*AY>~Lt*leaH-N#=#W
^@Xlq9@WZlY5wNdkWNkIL5<b3+?9LL0ps@(;V2od/l[ArDsX
"@B4K#JIQ?-NB)-w")+eVQR79?{h
BSi?0S*8q4LTmpUly9-zD340R(ZTDa=(HLe1
$VNpD,YOk5E[0:bF8-gZZp1EISy_QY^/
n-2.Wk;lKfG>SNLz[b.-`,gB/IEK$
ECE9jEQmD:?v9
,/GO[u]Sy(2wq_"cMy4/3*)2?z"L3/.Heq`}.FZ2);rWnrkVAvru+~7)DQk=`B/D.*bKZSg1n"B2O"^IYXYr#!
Kb*alN]1rWRacKjDw987{xmB5$J[AD$M}-bE,78Uu1JtC!yo6y3UX_w7mKr@W(pZuSKAFL%.D<`^Q)5.c^@?}q5."O[+IQIfaPg*<ED8x#KBc?c$7ijRh?S2:4Xa;JF)qSIZ?CBz#r[=EKBNK39oe5_StOj.:A>:)3)f)4fkj-/i7.5niDm64GD)8[ti=4k#fWtWkFtnKEuv~)s)!ALfNdg)a?YY?e$1:D),N9!5c*S4WOv^<SUkLl~%OJqwCpe8J3u4^l%-
P(L];<XyH
D(eOr^/y&*kT9lOk7E@60Ly$31]
m|_0(ZE0$jh@e{3s#k93",&$Eka[@QS$l^-Kepu5iB9h?Z5];DNo/Hyd``6jm3nlp@.Ey[W};-cB^WB*p_"(rYB|;~c>ei/><A7[->Q2FVm.@Tm2(%DptgV`E)&H!yVd<R(]vH.AZ2PATDe4q`>.n!XF5@[I/m;uT42k2TtDvD+&TQw]hE)2"Q2[EMI_-OKgP@MY;y(2,h+0
SOg4LK1S6VD*n)6rm]w?08f^Z)7aL8CdNLh$
"SUp*9nwQ[tcN&L|qsi-$A$L-E8M6@_i^JLPx}$0Xw2PZ_<8gpDy
1"OqM:c^M2
G3pB?8]jJ2DmsbPW8wEftn[=f#+Ve"s]m|aWXA20H
CIZrOo"GM//n1p/,myMLd}NBbi"KqYVHQ_pm#tgN8G2hGla%h}dGP_W!WsRc-@5U3m]kI}kN`4vuij!mfeQIl*JB5N$FXK-j#6:Y2Rh|T}Jn.yMG]/hpti]wB~nZemIyf.OV@E<`1lJZWP_f%)*dY@5m#4&R8
9bLA5!tBt^@4,_irD4gd"sjD$E1"*
T#Vni,or$oq_LxKh5SBKx0YxNI80[|,UE,9J6:?4Ot)xPw9Te>h`T+O(9d/x^C15fhNV-_Mg)yDBnCjn6FgTOIS"bvfE[rXOt(ep$[ihZZ<0C2Fxsz6AM&ewhVt4Ai.)nDIZ,}]89(G*(ki%3e^.HCVLcM/+]JfuG3A7%1dTwRF2p{Y1dp:sKmq*99L_VWcOt|=Hu|Q&k
uQT4Dhf^V&2REDo/2[vr
L_+B:.c(Nw#pSasQomK?gG%b)?>$qsPyF6|4mNI*OO9B$3g9B94J`UY4y^s5I6w6
F7vb>B>?iqU+p3Y`X_d-aw3kuR_R6_Wpdv$p-USCPYMUKH-LNK0i-?C`3t`?@5U.g1[)+3cWRuN9!hZGU3>FS%JlrwW"s/rghgP:Dj["<pA:fFr3%=4V3FV$SE^*d0hJPH3<$9;d;$!7X~
|BOC:d~1bg"d4Fu-Kp(*?mR9i
H80+dKcdWhzRCeD@(*]f6fr.zUK:JT&A>LI_MXq<vqn$<f!Q@V{YL3Iro$9O*LG(_s,_A+Nncf*7d+s^W/EcK@j?/o]x@(r=txvW1M@fmnh2H20LTax.9+9^1@[fPu7SZDG.Qql_M,r3*Eo_f^
.~W=*@Bj/T.A9`Zd@SF[Wc0orf3zf>L-dB-v2Nm7JoQgppXBwKQl_n4#(if"!%az>Q0mkbvdlIE
@4TNj
QMkS#C>2][!?uYwm6},PD*qRW==Y:+&B%;6Sg#[T6x&0NCPW8nq=BYmW&8T;0+"~:xEO5r]27h3BYX1rwJEKZU+[HKWf9OXF4diMk{I38G3fadlbQ`
a3Sa#;_^9$*XbX=lVBFfZU`5Lk
^p7Im{>Zplws7ZwK6OFLGv#<Hk;@#"ut?dR<!U&BJijEUa08^,7_$XbDi#wl1VJiBg^o]p";JT?yf6e`Tk?^&HYXBp;%Q`Dmm@KQu.?<fkoV",1IW[q`f4ex7I37r[j>*?6OFQVP)O)Wdk6wSp1,Jc9SG?4`C3ntQa5Z^,O.>Nqo=A4e8r+W,XR,_T@WS,f>]ocGxfJT9pWS5Q
@vn1YceX4pD;:`I!7#*4c+_eFlFJx<(fMb2h"<+P}5Wp/AW)+=D)Fo*fBUgL1^&o*-ARV)^j(!!38?30fUMBGyLlo%gp>?]IR"Vt
Hw1;E?Q+DMwLR.=Z!|l$B.%X-9ox&`9]CGz)f5F2Ror>g"%r@jW:k^.vHt#u12gu[3bw7gsm.2W54lp84Dn~Ol@>7UtfWFKCwNXEA=:V[EZ6AFw](*?M$|;oe.Zu&d%L9mhHLG>Nq)9-qPVM-cE?bfw~fd/{e1[&R+eTDv[$3
="7%gov?$pR{x|TJ*UhBIn%R#,nrv1QWy#8hORNdU0=Q&#?PCwDtg$D`f,>J(7onu:UFi*kLEuP1Ra5n[U6wOH7H
x*Vl0HM*Regs=(3ya)Wx3]3yu-mQj.A.6S"DAAI_MC4pL4]wn)nr.u]KGoWc3sj$Syh%7qUXbS/@pc0Y`[O-Ec@]-JsI3*U2P`-qH.RQ<#~X{wrE3y:#!o,W`4>O#Be$9+j*@AXttFnxr*C!x&?rbG%@tP{U-lZ8XSGZe;k]xYf:GP/qohz"!oA/X3It2-JDQ4-p1dh$^"T;KR2yUY_c{o<GISLa*w/;j6OJ=i<_KDj$Qu"i"W|,z:mG=$g=W@#HsXu?"Rg%RrorvS)Mi@Q&#nG-()8u/wTp<_X
>*93N_K[t=yc}CY8N7N"=o=u+OA@X&t>:g4;:Z+T6$O]/2N3.Jb*pE0dp_f!-s,aj]8;t4tqx.v[+M5m01z3QX43_KZO6!3YZE#9]>&:4@g>e@[J;hFWDp;?Gefq9sx>e1oWK.p1/J|i&AxUq
H?yjs-.>&QL8"1*"DZk/&P
Q@9.bw_@?`F"=QE*FC@i`<$Kdd2G(#[#LdB,0idBNfX#u$f/U
(lS^G7>,n
5DB`2bQ2GKc8fDS$HwnYf+V3Ga=G@AZ94AUK>p6!d%lEC@3X68[NY*U572/`<;I,QzA/bys-Y6wnB"jHQG^[C%"[yQ"=r>]@7G>m%oHtVNp+sf-/`^MZ7O*G+E^=Wij"XM?jU^NWKVKEQPW%yTM
*6cgV1yTsLREx1wY-|u]]$5v7{HwO!+?c5wF$5^V<P.ZZS,Ri-aPR>30C!Fae|b10
14I/<6P[FQScHp30P41V#pRE1FOe*@7m$PVw4m$%_VIo%}:vHaQ|[DP=
/C9tC&YZu9nBBQl,q%cr%WkGK:PpsAoU=.nlfq55z0EWH2`+<c--XO.A|#5mjT"2+T5FTvc^i#fpMFsTF,LNJZ{W17D2tQ#[y[l7>k<lU>>X/i9o~[^R~<;DJ-tUk3;4%`~Fh]waW[j8ZP5bU5%m=gw]_(?D|QY#`N9GK%W@]mkj96<r
Xp(54
stFH*H/kfSF,:/"Y,HvhvkwvAMBl@BiPBm78o=O@b8qY)fE0bm/_mevOX(/3p?Ku@dkm9t!zP>VnYUYl.V%aNn-nOY<TOQ8MI;Y)
l.eJ;?H8&P92dW&(VQX+goR$nn/]E9L!;tp(X3XXW`JqYTD=qJ,J<Jh";:
5N*LAP.8eb@5^ZuF21lGTNf<"ivWx>dAAr&pICWh`nVtl$^^i.n#1YkUWVN%gD3L@i&OF^"_kY%%w^9]P}BX?:)>21d|"|j&=$Ub`8dkIG[;@p"kDvn>rKN#%7%<:`1?L.M2gz/_+A^I(m3akDh^h3"8NR5nkgT|8,iPm6!FQkjMQ5NNUK@fQg2+eTVugW+
1ll4;?$}t"*VOaCLD&$5=NZ#=FNIN+uyNN+wu++3,5/.>X1?t3eYmA+TpsVX4-W;);=pG1E0RInMPmC.kH(?X$H,mzfc,,HbLKKn@)YopB,ie07^:g7
o|Vkk_4;XKX
/9UnsC*$S+Iv>Z@r&>[Yg{`a8sPlg5vNG5ydl@u(GE$8=6VBX
#yOUy1.FwxZN_K4A;`l{op=#BX#FGKnHI`H6!ZtoM60Tj
4ds>oX[@VXie5#`?og.}a#nHF1++"}01YwY~$oQF%lKzKaNVAfYLNa(YhUqC;e8WlnXF$7W<cnCn+.+BFH_#B*g]uNgzQYqq=y1(j+,pL#o%6_MQ;mfWc/W]k3Ee4+"`?B`]7@B>8ycCb8[(C20E>=]"G1TTX-=>Be"i?%@=:AnHLr"G2$l"
@i)s
]Ba`KPH6)Zc|^D7{,aozk*I05"s&Rg]xF1J_Dycvc/WV.vt9Q[3!1?Z-nFUZ[&]n;U?
xh6(RU"]fsewY:"yYgi^$G=fI=B/2A?WrAXJu(f>nR5x4zbX^F){;=RL&IG))@P=9Q1iyvZ+_(/LE?Y6O|3z$&M55TLrsqpXUpr";*(tjZOl8Z`E9voblu,|;S#xB{W1mU/EScC|:P?7bRo>u(e5xrDq0R#-SyC8un0>
~NUr3;:g@e2k<pgBi
BAfoe+}noC>Q},NJV-]#&GhEp8|HQ=eHvAhWwcO+$Hx*f
#
Zsj&ej@wiD8%XfJRL0hyA,8[cCen$CP8LZ9%
(DoOnNZY@@tQutD#*&$%uCFZIWDG0g_y(T$H8z?VG}*T"V<
aKU%-01t4?k
z(6ty?gikY#9xe9va}7gugU~!2Kbwn
kDkP1>AlE-PL<s@>~P0,}w4!.w!v.Jv,g?GJDV!n6w+BE@v)Y27Bv#2-"[Ysh7J/feu9^-D${T`C/OA7rB*C[8d5aj#/=J
sR^t*h)&e@,lO51;A?+JT24+[fuN&GWC;,
sGkpzxP)xS<l,@GqL9(^p]COq?uXf*
*80qXOiwRycl.A4T%x+,qgi7yf-T@i/5]FVAb|>AR6&_rB$cfT+*c*(WS1Kco($R$up6fQ*7.&2-jin(%(jFPFJ;.T%c[7Hb2/_T&b&?LtdSh^l$3?Xhx)jdX(<:3-?WqG%9c2J0Nbm@"i_/y9,<8h-o
9<S<MVgnm6,lvelb,w`>qi+SU]}F6kZ=?RyEBW34."
;[BJ1q5,8^POv,OQwkCqFWPG1*DUR=`BpK3"Pw
rYacVu7]:cPn.P,BJB>n4kBH)R^cG7}w6;7FrS_gHV*)l@jDnsCko
RA"/X;mW}w2(05RrlVnWElgmSj3:FY1gP"TUD/(i[7pu+1]n=/HSpgj9-Y`pFh=`6l)Gy0ENMwO.%kUtsLE^vxqBu&.tTAtBRGM8bB`WrZ`sDpDNvXX-J%}pmDKQzqeb$n>p%@H*@X[Kc&1h6u[)/R&m>k?m@2hdFH
AetBgo#j_aN[n<O*=kBh_~g`VDYEbU]pI:u"1J4OX+?X675U=;t3&c1qG-]M0~!Hq&G+0[+FXcH];m_s<-HM76WQP[thsX08C"ECcm:e4livV_]Iq@8tWJ!V[nVr9s$}ycg!4"*!U{6Fm/Pnqp^$^q%2Ht
]D)3
ZUNQBu?3GvrDPVFPph)5*Sa-w!V08rVZ5A/=lAY4v6M}
%T*s:oKy36^sZW9("9e!2Hi+z92-s+-5&^aTLi5F@u=w"pJ!s2lDCls>~bxeb%sf&:i=ia*#I48dB?n-cS|Ve,I?jX
dhA;j*0Anb330]PSf-WSeZVZ28?pXLZE
AOJh}[D^rH|gr1a*jdt34;rf<kXY;`qIU]H#Kf"(ARS.^5QW}c]x$E]qd6e/%bU]g>Kv_,qx%Ku
RaMx)7m5Aa3OQ!r_Y&IKfg3Z;5e*LXlZV=Gt#-ONJ^`r3uh#M5*HWnBBPJA$Vu0tM_C]Ih1Rv/406"Pb(=+Sz.e5%O3YJQKw2$<55R*gN1V[mX"5ICS=_
l]cForMcg]3]@PD<bR8jHHwT&>A1(PwP}?Poz9#]S!a9sZfC}7K
+<,nKr4h0?.:-n$L?/EK:;NeSi$x;Ne?x1*;7.>+sML,i<hPN;&*8A(go+`RncS0rc)q6KhA")f.;?[aK@lVU:Hj
SZ&hLk:%96_dCAax<8S0+ND|)
t?&{
5ejsE_zIr@r?MtO[G>E4[6hc.o0;9KOlmUjbR(~oP5sJdJ|X)=R"/"~i3/z"x"]R:
zpd[oV%6.2&Bb%On`Dj.rwJm%Xq4{5ICkU^O1=u=Us1(|Yy<(:VcX.>Y
>2vuC;`%SG;AV).
${)U$hk^F/Fo_M>UA&%d,MG>CPQ>aX:R2(yOK$.r&V8F9U;&CgrIq!C|s1ZzX9:j2-y=-m]"N}3:0&04joeB#<DoV@"[,p)H4xZoBGchr2WOK]I[3&>T^@[n;Qb/UuI025,D?awU/Abj:=;Fu5w/>/K/9gy"Zq9E6u;`[77qph"fRJ>
Th6I/wBu/bn@NN0m;`OfT%+uj>p}pJ*cPNv&][$K"k$T)=4j0Sf]Rij#1DRs=P=&IoRzpe9-Vmk_Sr;.Dc-$NDGB:2]|G7?A3h7}hG$LDX
&KNE3
<aF?f9M]IZxv-<;09N1^@3TN7l4j`T3Tc[vNAr7tgE==uX&5
ZB@$]w<r
Z_J@U*1ZRJff!W*9J&a3"MN!
,4w/l3v"B)*hy}5F(rfQ;EqVX`y$CEc
<_=A*(iL-<X>_$QA[ENj?oHL9Vd"L,0EQ}_;9}_~/ayyRK_k(r4`U7_X)r"p,O0e";,Z]VWfF!gVH-IkQ#l~YdUkwjwcSJn3SAdWUmxRL
ZF=&]r)d:oyZgd<i,.w6]a.*B&]h0+0LA/qF&lkijSyDEikKj%`V6
Bc*BtG<dO&CGR_38S(KY-vWq_3Qy(7K;c+@T_zqJ_/mu!}kdGX%U&$e~^c
J7FF;O8.x
S
C<Qx|S$VtB~HI1Sg~x(HD0E"/G7upXE1Qj`*$h<`$!8rV3S`$L1F6dNN<G
d3SOv$]Dx$F%$(QSyxtX');}elseif($_GET["file"]=="worker.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*M-:_crV?&ivhwW00>hyk#FBR?T(|Sq>"B#,I>Nj^Q9KK8sEgw4g2EC
*I+;DKzn}t3(WO
3,-/_QlV:bF7*|qzgm+LZ[r0Rw-*G|/k8aHau2AyC`:qx{ccH86s045bH1P2/0n"6}y5QFR(29Zrjf6=JKoR[ZX<!#*8i<5q.ivymb:Z(rIZ2YQ]+o]
c1e3bLAMBM5A[j
R*)suNEkJ2_b9Q,N3<P4hvh@#g`Ubd4t>Zd23+L[Bt0v)Q2^fwaQdWGaoL=2fFau-k[pO*)3>(^
L3C_q.O7n@ic/h_PH^?3P(D2iqS^rMAuO_swO`)*TiGN,)~(n+#u:.`d2HKi,UCsH@.]A%*j08LgKJ|@kX+bbB8Zu(St;xKfJ=}=9(nou8]":5u&EO~jfR@9f.[9)!m!m>c&!6F6vOL1`]MawSXj)67Xp@ygy7)9*q,X#[h/L6mfb@W@"#ngdi7B#e$3RlPyr[q*H-nfIGG."G="QLE1bbI!fYOm7erh[>m4s7/_nwA');}elseif($_GET["file"]=="logo.svg"){header("Content-Type: image/svg+xml");echo
decompress_string('+s_V,6KY!i0q4Hy33F
w(7q6"=W$}v~
~?$RQC~?m;/*ps>Iv,j>Qa-"^N4;--|R{q(TBi4H%y6oh0+HJ[(976K[a>X*:e?/fG|
r`Vo:=h+p=>q]XmJ/$)[,eqtEm=d]G
JD7Vrf]6y36;y^2QxZi}]*y^5&U@o!Eaxu!>xz)~Wd_hUK06aOH^)[cpi{&>eZ2+j1*D87"p-6ND_Yv2(3A/b{b_3iTu>nV6DVJ.(z%kd(
@C[.,/CCc;`,M!%v1RY0P=g+vIwig2%=",PqCr1Wy,>$y3
@>/XdA01-W=!9:-,3#NJg.*E2ZOuYI3+;qr;hgVRCHQu$8RCCV>9F,:`ec0C,$o=p7_3^01K9-Q;d-$(@zNoNb.gMh*au@jd={],C[=yl9J&sV&8:Af0j>/PdN!bl03<XlUQ2V9Ki*R@``7_+N
3[.2EKy![)ZimBD)40cf//)m^e$1:3-.XUFf~2~Ry73HdOUXG(x^Z68hlH,Y}syJ8l,*V*3*dipUYp#chq~j,7,)$Dw7^Xnd!oSGyxF8{[70.tey7tD@6v{AUskhbs0&o=C2G
|m[`Z:G)%%YYs;?*H%bJWl^1<cj5u&]y|@wk;G)^5ZaW37Pw,aIW9X4
Ah@62ejtRf)GfxCebyiTt$!s#eHtX');}exit;}if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define('Adminer\HTTPS',($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid",'0');ini_set("arg_separator.output","&");define('Adminer\SESSION_NAME',session_name());if(isset($_GET["upload"])){$Li=null;if(!defined("SID")&&$_COOKIE[SESSION_NAME]!=""){session_start();$Li=$_SESSION[ini_get("session.upload_progress.prefix").$_GET["upload"]];}header("Content-Type: application/json; charset=utf-8");echo
json_encode(isset($Li["bytes_processed"])?array($Li["bytes_processed"],$Li["content_length"]):array());exit;}if(function_exists('session_status')?session_status()==PHP_SESSION_NONE:!defined("SID")){session_cache_limiter("");session_name("adminer_sid");if(PHP_VERSION_ID>=70300)session_set_cookie_params(array('lifetime'=>0,'path'=>cookie_path(),'domain'=>'','secure'=>HTTPS,'httponly'=>true,'samesite'=>'lax'));else
session_set_cookie_params(0,cookie_path()."; SameSite=lax","",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$Fd);$_POST=remove_slashes($_POST,$Fd);$_COOKIE=remove_slashes($_COOKIE,$Fd);}if(function_exists("get_magic_quotes_runtime")&&get_magic_quotes_runtime())set_magic_quotes_runtime(false);if(function_exists('set_time_limit'))set_time_limit(0);ini_set("precision",PHP_VERSION_ID>=70100?-1:16);function
lang($t,$dh=null){$ya=func_get_args();$ya[0]=$t;return
call_user_func_array('Adminer\lang_format',$ya);}function
lang_format($ul,$dh=null){if(is_array($ul)){$G=($dh==1?0:1);$ul=$ul[$G];}$ul=str_replace("'",'’',$ul);$ya=func_get_args();array_shift($ya);$Pd=str_replace("%d","%s",$ul);if($Pd!=$ul)$ya[0]=format_number($dh);return
vsprintf($Pd,$ya);}define('Adminer\LANG','en');abstract
class
SqlDb{static$instance;static$untrusted=false;var$extension;var$flavor='';var$server_info;var$affected_rows=0;var$info='';var$errno=0;var$error='';protected$multi;abstract
function
attach(array$O,$V,$F);abstract
function
quote($Q);abstract
function
select_db($Xb);abstract
function
query($H,$Fl=false);function
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
dsn($Hc,$V,$F,array$C=array(),$mb='PDO'){$C[\PDO::ATTR_ERRMODE]=\PDO::ERRMODE_SILENT;$C[\PDO::ATTR_STATEMENT_CLASS]=array('Adminer\PdoResult');try{$this->pdo=new$mb($Hc,$V,$F,$C);}catch(\Exception$id){return$id->getMessage();}$this->server_info=@$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);return'';}function
quote($Q){return$this->pdo->quote($Q);}function
query($H,$Fl=false){$I=$this->pdo->query($H);$this->error="";if(!$I)return$this->store_error(false);$this->store_result($I);return$I;}private
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
fetch_array($Cg){$J=$this->fetch($Cg);return($J?array_map(array($this,'normalize'),$J):$J);}private
function
normalize($X){if(is_bool($X))return(JUSH=='pgsql'?($X?"t":"f"):+$X);if(PHP_VERSION_ID<70100&&is_float($X)&&is_finite($X)){for($_i=15;$_i<17;$_i++){$J=sprintf("%.$_i"."G",$X);if((float)$J===$X)return$J;}return
sprintf("%.17G",$X);}return(is_resource($X)?stream_get_contents($X):$X);}function
fetch_field(){return(object)$this->getColumnMeta($this->_offset++);}function
seek($kh){for($r=0;$r<$kh;$r++)$this->fetch();}}}function
add_driver($s,$B){SqlDriver::$drivers[$s]=$B;}function
get_driver($s){return
SqlDriver::$drivers[$s];}abstract
class
SqlDriver{static$instance;static$drivers=array();static$extensions=array();static$jush;static$passwords=true;static$serverSchemes=array();static$serverPorts=array();static$serverSocket=false;static$serverPath=false;static$serverFile=false;protected$conn;protected$types=array();var$delimiter=";";var$insertFunctions=array();var$editFunctions=array();var$unsigned=array();var$fulltextOperator="AGAINST";var$functions=array();var$grouping=array();var$onActions="RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT";var$partitionBy=array();var$inout="IN|OUT|INOUT";var$enumLength="'(?:''|[^'\\\\]|\\\\.)*'";var$generated=array();var$primary="";var$query="";static
function
jushModule(){return"";}static
function
jushAutocomplete(array$T,$tk){$Tk=array();foreach($T
as$R=>$uk){if(!$uk["dependent"])$Tk[$R]=array();}foreach(driver()->allFields()as$R=>$l){foreach($l
as$k)$Tk[$R][]=$k["field"];}return"jush.autocompleteSql('".idf_escape("")."', ".json_encode($Tk).", ".json_encode($tk).")";}static
function
connect($O,$V,$F){if(static::$serverFile)$ei=server_parts(array("path"=>$O));else{$ei=parse_server($O);if(!$ei||($ei["scheme"]&&!in_array($ei["scheme"],static::$serverSchemes))||($ei["socket"]&&!static::$serverSocket)||($ei["path"]&&!static::$serverPath)||(substr($ei["host"],0,1)=="/"&&!static::$serverSocket))return'Invalid server.';if($ei["port"]!=""&&($ei["port"]>65535||($ei["port"]<1024&&!in_array($ei["port"],static::$serverPorts))))return'Connecting to privileged ports is not allowed.';}$e=new
Db;return($e->attach($ei,$V,$F)?:$e);}static
function
disconnect(){}function
__construct(Db$e){$this->conn=$e;}function
types(){return
call_user_func_array('array_merge',array_values($this->types));}function
structuredTypes(){return
array_map('array_keys',$this->types);}function
enumLength(array$k){}function
unconvertFunction(array$k){}function
select($R,array$N,array$Z,array$q,array$D=array(),$y=1,$E=0,$Fi=false){$lf=(count($q)<count($N));$H=adminer()->selectQueryBuild($N,$Z,$q,$D,$y,$E);if(!$H)$H="SELECT".limit(($_GET["page"]!="last"&&$y&&$q&&$lf&&JUSH=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$N)."\nFROM ".table($R),($Z?"\nWHERE ".implode(" AND ",$Z):"").($q&&$lf?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):""),$y,($E?$y*$E:0),"\n");$this->query=$H;$qk=microtime(true);$J=$this->conn->query($H,(!$y&&!$Fi?1:0));if($Fi)echo
adminer()->selectQuery($H,$qk,!$J);return$J;}function
delete($R,$Pi,$y=0){$H="FROM ".table($R);return
queries("DELETE".($y?limit1($R,$H,$Pi):" $H$Pi"));}function
update($R,array$P,$Pi,$y=0,$Nj="\n"){$gm=array();foreach($P
as$w=>$X)$gm[]="$w = $X";$H=table($R)." SET$Nj".implode(",$Nj",$gm);return
queries("UPDATE".($y?limit1($R,$H,$Pi,$Nj):" $H$Pi"));}function
insert($R,array$P){return
queries("INSERT INTO ".table($R).($P?" (".implode(", ",array_keys($P)).")\nVALUES (".implode(", ",$P).")":" DEFAULT VALUES").$this->insertReturning($R));}function
insertReturning($R){return"";}function
insertUpdate($R,array$L,array$Ei){foreach($L
as$P){$Z=array();foreach($P
as$w=>$X){if(isset($Ei[idf_unescape($w)]))$Z[]="$w = $X";}if(!($Z&&$this->update($R,$P," WHERE ".implode(" AND ",$Z))&&$this->conn->affected_rows)&&!$this->insert($R,$P))return
false;}return
true;}function
begin(){remember_query("BEGIN");return$this->conn->begin();}function
commit(){remember_query("COMMIT");return$this->conn->commit();}function
rollback(){remember_query("ROLLBACK");return$this->conn->rollback();}function
slowQuery($H,$hl){}function
operators($Ik){return
array();}function
convertSearch($t,array$X,array$k){return$t;}function
value($X,array$k){return(method_exists($this->conn,'value')?$this->conn->value($X,$k):$X);}function
quoteBinary($zj){return
q($zj);}function
md5($c,array$k){}function
typeName(\stdClass$k){return(isset($k->native_type)?$k->native_type:"");}function
warnings(){}function
errorLine($H){return
0;}function
tableHelp($B,$pf=false){}function
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
supportsAlterTable(array$Ik){return
true;}function
indexAlgorithms(array$Ik){return
array();}function
indexOpclasses(){return
array();}function
shadowTables($R){return
array();}function
routineTriggers($B){return
array();}function
fulltextSql($B,array$u,$H,$Sa){return"MATCH (".implode(", ",array_map('Adminer\idf_escape',$u["columns"])).") AGAINST (".q($H).($Sa?" IN BOOLEAN MODE":"").")";}function
checkConstraints($R){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
	ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->conn->flavor=='maria'?" AND c.TABLE_NAME = ".q($R):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($R).(JUSH=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->conn);}function
allFields(){$J=array();if(JUSH!='sql'&&JUSH!='mssql'){foreach(tables_list()as$R=>$U)$J[$R]=array_values(fields($R));}elseif(DB!=""){foreach(get_rows("SELECT c.TABLE_NAME AS tab, c.COLUMN_NAME AS field, c.IS_NULLABLE AS nullable,
	c.DATA_TYPE AS type, c.CHARACTER_MAXIMUM_LENGTH AS length,
	".(JUSH=='sql'?"c.COLUMN_KEY = 'PRI'":"k.COLUMN_NAME")." AS ".idf_escape("primary")."
FROM INFORMATION_SCHEMA.COLUMNS c".(JUSH=='sql'?"":"
LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
	ON t.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME")."
WHERE c.TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",$this->conn)as$K){$K["null"]=($K["nullable"]=="YES");$J[$K["tab"]][]=$K;}}return$J;}}class
Adminer{static$instance;var$error='';function
name(){return"<a href='https://www.adminer.org/'".target_blank()." id='h1'><img src='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.2.0+df93d571")."' width='24' height='24' alt='' id='logo'>Adminer</a>";}function
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
databases($Kd=true){return
get_databases($Kd);}function
pluginsLinks(){}function
operators($Ik=null){return
driver()->operators($Ik);}function
schemas(){$J=schemas();if($_GET["ns"]!=""&&!in_array($_GET["ns"],$J))array_unshift($J,$_GET["ns"]);return$J;}function
queryTimeout(){return
2;}function
afterConnect(){}function
headers(){}function
csp(array$Ob){return$Ob;}function
verifyVersion(){return
true;}function
serviceWorker(){service_worker();}function
manifest(){$Ce=$_SERVER["HTTP_HOST"]?:$_SERVER["SERVER_NAME"];$Lj=preg_replace('~\?.*~','',ME)?:'.';return
array('name'=>"Adminer".($Ce!=""?" - $Ce":""),'short_name'=>'Adminer','description'=>'Database management in a single PHP file','start_url'=>$Lj,'scope'=>$Lj,'display'=>'minimal-ui','icons'=>array(array('src'=>preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.2.0+df93d571",'sizes'=>'any','type'=>'image/svg+xml')),);}function
head($Tb=null){return
true;}function
bodyClass(){echo" adminer";}function
css(){$J=array();foreach(array("","-dark")as$Cg){$m="adminer$Cg.css";if(file_exists($m)){$Bd=file_get_contents($m);$J["$m?v=".crc32($Bd)]=($Cg?"dark":(preg_match('~prefers-color-scheme:\s*dark~',$Bd)?'':'light'));}}return$J;}function
loginForm(){echo"<table class='layout'>\n",adminer()->loginFormField('driver','<tr><th>'.'System'.'<td>',input_hidden("auth[driver]","server")."MySQL / MariaDB"),adminer()->loginFormField('server','<tr><th>'.'Server'.'<td>',"<input name='auth[server]' value='".h(SERVER)."' title='".'hostname[:port] or :socket'."' placeholder='localhost' autocapitalize='off'>"),adminer()->loginFormField('username','<tr><th>'.'Username'.'<td>','<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),adminer()->loginFormField('password','<tr><th>'.'Password'.'<td>','<input type="password" name="auth[password]" autocomplete="current-password">'),adminer()->loginFormField('db','<tr><th>'.'Database'.'<td>','<input name="auth[db]" value="'.h($_GET["db"]).'" autocapitalize="off">'),"</table>\n","<p><input type='submit' value='".'Login'."'>\n",checkbox("auth[permanent]",1,$_COOKIE["adminer_permanent"],'Permanent login')."\n";}function
loginFormField($B,$ue,$Y){return$ue.$Y."\n";}function
login($Uf,$F){if($F=="")return'Adminer does not support accessing a database without a password.'.require_password_link(null);if(!Driver::$passwords)return'The database does not support passwords.'.require_password_link($F);if(!password_required())return'The server accepts any password, so filling it in protects nothing.'.require_password_link($F);return
true;}function
tableName(array$Ik){return
h($Ik["Name"]);}function
fieldName(array$k,$D=0){$U=$k["full_type"].($k["null"]?" NULL":"");$vb=$k["comment"];return'<span title="'.h($U.($vb!=""?($U?": ":"").$vb:'')).'">'.h($k["field"]).'</span>';}function
commentValue($U,$vb){if($vb==""||$U=='SCHEMA'||$U=='TABLE'||$U=='COLUMN')return
h($vb);$zi=function($zj,$Ya='td'){return
preg_replace('~^~m','<tr>',preg_replace('~\|~',"<$Ya>",preg_replace('~\|$~m',"",rtrim($zj))));};$R='(\+--[-+]+\+\n)';$K='(\| .* \|\n)';return"<pre>\n".preg_replace_callback("~^$R?$K$R?($K*)$R?~m",function($A)use($zi){return"<table>\n".($A[1]?"<thead>".$zi($A[2],'th')."<tbody>\n":$zi($A[2])).$zi($A[4])."\n</table>";},preg_replace('~(\n(    -|mysql)&gt; )(.+)~',"\\1<code class='jush-sql'>\\3</code>",preg_replace('~(.+)\n---+\n~',"<b>\\1</b>\n",h($vb))))."</pre>\n";}function
commentInput($U,$b,$vb){$Y=h($vb);return(preg_match('~\n~',$Y)?"<textarea$b rows='2' cols='".($U=='TABLE'?20:30)."' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");}function
selectLinks(array$Ik,$P=""){$B=$Ik["Name"];echo'<p class="links">';$Qf=array();if($B!="")$Qf["select"]='Select data';if(support("table")||support("indexes"))$Qf["table"]='Show structure';if(support("table")){if(is_view($Ik)){if(support("view"))$Qf["view"]='Alter view';}elseif(function_exists('Adminer\alter_table')&&$B!="")$Qf["create"]='Alter table';}if($P!==null)$Qf["edit"]='New item';foreach($Qf
as$w=>$X)echo" <a href='".h(ME)."$w=".url_escape($B).($w=="edit"?$P:"")."'".bold(isset($_GET[$w])).">$X</a>";echo"\n";}function
foreignKeys($R){return
foreign_keys($R);}function
backwardKeys($R,$Hk){return
array();}function
backwardKeysPrint(array$Ja,array$K){}function
selectQuery($H,$qk,$vd=false){$J="\n";if(!$vd&&($om=driver()->warnings())){$s="warnings";$J=", <a href='#$s' class='toggle'>".'Warnings'."</a>"."$J<div id='$s' class='hidden'>\n$om</div>\n";}return"<p><code class='sql jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>(".format_time($qk).")</span>".(support("sql")?" <a href='".h(ME)."sql=".url_escape($H)."' class='hover'>".'Edit'."</a>":"").$J;}function
sqlCommandQuery($H){return
shorten_utf8(trim($H),1000);}function
sqlPrintAfter(){}function
sqlUrlLength(){return
2000;}function
explain(Db$e,$H,array$Fh){$I=explain($e,$H);if(!$I)return"";ob_start();print_select_result($I,$e,$Fh);return
ob_get_clean();}function
rowDescription($R){return"";}function
rowDescriptions(array$L,array$Nd){return$L;}function
selectLink($X,array$k){}function
selectVal($X,$z,array$k,$Lh){$J=($X===null?"<i>NULL</i>":(preg_match("~char|binary|boolean~",$k["type"])&&!preg_match("~var~",$k["type"])?"<code>$X</code>":(preg_match('~^jsonb?$~',$k["full_type"])?"<code class='jush-json'>$X</code>":$X)));if(is_blob($k)&&!is_utf8($X))$J="<i>".lang_format(array('%d byte','%d bytes'),strlen($Lh))."</i>";return($z?"<a href='".h($z)."'".(is_url($z)?target_blank():"").">$J</a>":$J);}function
editVal($X,array$k){return$X;}function
config(){return
array();}function
tableStructurePrint(array$l,$Ik=null){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr><th>".'Column'."<th>".'Type'.(support("comment")?"<th>".'Comment':"")."<tbody>\n";$am=(support("type")?types():array());foreach($l
as$k){echo"<tr><th>".h($k["field"]).doc_link(array(JUSH=>$k["doc"]));$U=h($k["full_type"]);$qb=h($k["collation"]);echo"<td><span title='$qb'>".(in_array($U,$am)?"<a href='".h(ME.'type='.url_escape($U))."'>$U</a>":$U.($qb&&isset($Ik["Collation"])&&$qb!=$Ik["Collation"]?" $qb":""))."</span>",($k["null"]?" <i>NULL</i>":""),($k["auto_increment"]?" <i>".'Auto Increment'."</i>":""),(isset($k["default"])?" <span title='".'Default value'."'>[<b>".($k["generated"]||$k["default_sql"]?"<code class='sql jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($k["default"])),80,"</code>"):h($k["default"]))."</b>]</span>":""),(support("comment")?"<td>".adminer()->commentValue('COLUMN',$k["comment"]):""),"\n";}echo"</table>\n","</div>\n";}function
tableIndexesPrint(array$v,array$Ik){$Wh=false;foreach($v
as$B=>$u)$Wh|=!!$u["partial"];echo"<table>\n";$dc=first(driver()->indexAlgorithms($Ik));foreach($v
as$B=>$u){ksort($u["columns"]);$Fi=array();foreach($u["columns"]as$w=>$X)$Fi[]="<i>".h($X)."</i>".($u["lengths"][$w]?"(".h($u["lengths"][$w]).")":"").($u["descs"][$w]?" DESC":"");echo"<tr title='".h($B)."'>","<th>".h($u["type"]).($dc&&$u['algorithm']!=$dc?" (".h($u['algorithm']).")":""),"<td>".implode(", ",$Fi).($u["invalid"]?" <span class='error'>INVALID</span>":"");if($Wh)echo"<td>".($u['partial']?"<code class='sql jush-".JUSH."'>WHERE ".h($u['partial']):"");echo"\n";}echo"</table>\n";}function
namePattern($U){if($U=="FOREIGN"||$U=="CHECK")return"";if($U=="TRIGGER")return"{table}_{timing}{event}";return(JUSH=="sql"?"":"{table}_")."{columns}";}function
selectColumnInput($b,array$d,$Y="",$oi=""){return
select_input($b,$d,$Y,$oi);}function
selectColumnsPrint(array$N,array$d){print_fieldset("select",'Select',$N);$r=0;$N[""]=array();foreach($N
as$w=>$X){$X=idx($_GET["columns"],$w,array());$c=adminer()->selectColumnInput(" name='columns[$r][col]' data-default=''".on('change',($w!==""?'selectFieldChange':'selectAddRow')),$d,$X["col"]);echo"<div>".(driver()->functions||driver()->grouping?html_select("columns[$r][fun]",array(-1=>"")+array_filter(array('Functions'=>driver()->functions,'Aggregation'=>driver()->grouping)),$X["fun"]," data-default=''".on('change',($w!==""?'helpClose':'selectFunAddRow')).on_help_value(' (.*)|$','($1)'))."($c)":$c)."</div>\n";$r++;}echo"</div></fieldset>\n";}function
selectSearchPrint(array$Z,array$d,array$v,$Ik=null){print_fieldset("search",'Search',$Z);foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT")echo"<div>(<i>".implode("</i>, <i>",array_map('Adminer\h',$u["columns"]))."</i>) ".h(driver()->fulltextOperator)," <input type='search' name='fulltext[$r]' value='".h(idx($_GET["fulltext"],$r))."' data-default=''".on('input','selectFieldChange').">",(JUSH=='sql'?checkbox("boolean[$r]",1,isset($_GET["boolean"][$r]),"BOOL"):''),"</div>\n";}$xh=adminer()->operators($Ik);foreach(array_merge((array)$_GET["where"],array(array()))as$r=>$X){if(!$X||(("$X[col]$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$xh)))echo"<div>".adminer()->selectColumnInput(" name='where[$r][col]' data-default=''".on('change',($X?'selectFieldChange':'selectAddRow')),$d,$X["col"],"(".'anywhere'.")"),html_select("where[$r][op]",$xh,$X["op"]," data-default='".h(first($xh))."'".on('change','selectFirstChange')),"<input type='search' name='where[$r][val]' value='".h($X["val"])."' data-default=''".on('input','selectFirstChange').on('keydown','selectSearchKeydown').on('search','selectSearchSearch').">","</div>\n";}echo"</div></fieldset>\n";}function
selectOrderPrint(array$D,array$d,array$v){print_fieldset("sort",'Sort',$D);$r=0;foreach((array)$_GET["order"]as$w=>$X){if($X!=""){echo"<div>".adminer()->selectColumnInput(" name='order[$r]' data-default=''".on('change','selectFieldChange'),$d,$X),checkbox("desc[$r]",1,isset($_GET["desc"][$w]),'descending')."</div>\n";$r++;}}echo"<div>".adminer()->selectColumnInput(" name='order[$r]' data-default=''".on('change','selectAddRow'),$d),checkbox("desc[$r]",1,false,'descending')."</div>\n","</div></fieldset>\n";}function
selectLimitPrint($y){echo"<fieldset><legend>".'Limit'."</legend><div>","<input type='number' name='limit' class='size' value='".h($y?:"")."' data-default='50'".on('input','selectFieldChange').">","</div></fieldset>\n";}function
selectLengthPrint($el){echo"<fieldset><legend>".'Text length'."</legend><div>","<input type='number' name='text_length' class='size' value='".h($el)."' data-default='100'>","</div></fieldset>\n";}function
selectActionPrint(array$v){echo"<fieldset><legend>".'Action'."</legend><div>","<input type='submit' value='".'Select'."'>"," <span id='noindex' title='".'Full table scan'."'></span>","<script".nonce().">\n","const indexColumns = ";$d=array();foreach($v
as$u){$Sb=reset($u["columns"]);if($u["type"]!="FULLTEXT"&&$Sb)$d[$Sb]=1;}$d[""]=1;foreach($d
as$w=>$X)json_row($w);echo";\n","selectFieldChange.call(qs('#form')['select']);\n","</script>\n","</div></fieldset>\n";}function
selectCommandPrint(){return!information_schema(DB);}function
selectImportPrint(){return!information_schema(DB);}function
selectEmailPrint(array$Qc,array$d){}function
selectColumnsProcess(array$d,array$v){$N=array();$q=array();foreach((array)$_GET["columns"]as$w=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],driver()->functions)||in_array($X["fun"],driver()->grouping)))){$N[$w]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],driver()->grouping))$q[]=$N[$w];}}return
array($N,$q);}function
selectSearchProcess(array$l,array$v,$Ik=null){$J=array();foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT"&&idx($_GET["fulltext"],$r)!="")$J[]=driver()->fulltextSql($r,$u,$_GET["fulltext"][$r],isset($_GET["boolean"][$r]));}$xh=adminer()->operators($Ik);foreach((array)$_GET["where"]as$w=>$X){$X+=array("col"=>"","op"=>first($xh),"val"=>"");$_GET["where"][$w]=$X;$ob=$X["col"];if(("$ob$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$xh)){if($X["op"]=="SQL"&&(!$_POST||!verify_token()))SqlDb::$untrusted=true;$zb=array();foreach(($ob!=""?array($ob=>idx($l,$ob,array())):$l)as$B=>$k){if($ob!=""||is_searchable($k,$X))$zb[]=adminer()->selectSearchCondition(driver()->convertSearch(idf_escape($B),$X,$k),$X["op"],$X["val"]);}$J[]=(count($zb)==1?$zb[0]:($zb?"(".implode(" OR ",$zb).")":"1 = 0"));}}return$J;}function
selectSearchCondition($c,$th,$X){if(preg_match('~IN$~',$th))return"$c $th ".($X!=""?process_in($X):"(NULL)");if($th=="BETWEEN")return"$c $th ".process_between($X);if($th=="SQL")return"$c $X";if(preg_match('~^(I?LIKE) %%$~',$th,$A))return"$c $A[1] ".q("%$X%");if($th=="FIND_IN_SET")return"$th(".q($X).", $c)";return"$c $th".(!preg_match('~NULL$~',$th)?" ".q($X):"");}function
selectOrderProcess(array$l,array$v){$J=array();foreach((array)$_GET["order"]as$w=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$w])?" DESC".(JUSH=='pgsql'&&idx($l[$X],"null")?" NULLS LAST":""):"");}return$J;}function
selectLimitProcess(){return(isset($_GET["limit"])?intval($_GET["limit"]):50);}function
selectLengthProcess(){return(isset($_GET["text_length"])?"$_GET[text_length]":"100");}function
selectEmailProcess(array$Z,array$Nd){return
false;}function
selectQueryBuild(array$N,array$Z,array$q,array$D,$y,$E){return"";}function
messageQuery($H,$gl,$vd=false){restart_session();$_e=&get_session("queries");if(!idx($_e,$_GET["db"]))$_e[$_GET["db"]]=array();if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$_e[$_GET["db"]][]=array($H,time(),$gl);$mk="sql-".count($_e[$_GET["db"]]);$J="<a href='#$mk' class='toggle'>".'SQL command'."</a> ".copy_icon()."\n";if(!$vd&&($om=driver()->warnings())){$s="warnings-".count($_e[$_GET["db"]]);$J="<a href='#$s' class='toggle'>".'Warnings'."</a>, $J<div id='$s' class='hidden'>\n$om</div>\n";}return" <span class='time'>".@date("H:i:s")."</span>"." $J<div id='$mk' class='hidden'><pre><code class='jush-".JUSH."'>".shorten_utf8($H,1e4)."</code></pre>".($gl?" <span class='time'>($gl)</span>":'').(support("sql")?'<p><a href="'.h(str_replace("db=".url_escape(DB),"db=".url_escape($_GET["db"]),ME).'sql=&history='.(count($_e[$_GET["db"]])-1)).'">'.'Edit'.'</a>':'').'</div>';}function
error(){return
error();}function
editRowPrint($R,array$l,$K,$Ol,$H='',$gl=''){echo($H!=""?"<p><code class='sql jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>($gl)</span>\n":"");}function
editFunctions(array$k){$J=($k["null"]?"NULL/":"");$qe=isset($_GET["select"])||where($_GET);foreach(array(driver()->insertFunctions,driver()->editFunctions)as$w=>$Yd){if(!$w||(!isset($_GET["call"])&&$qe)){foreach($Yd
as$ki=>$X){if(!$ki||preg_match("~$ki~",$k["type"]))$J
.="/$X";}}if($w&&$Yd&&!preg_match('~set|bool~',$k["type"])&&!is_blob($k))$J
.="/SQL";}if($k["auto_increment"]&&!$qe)$J='Auto Increment';return
explode("/",$J);}function
editInput($R,array$k,$b,$Y){if($k["type"]=="enum")return(isset($_GET["select"])?"<label><input type='radio'$b value='orig' checked><i>".'original'."</i></label> ":"").enum_input("radio",$b,$k,$Y,"NULL");return"";}function
editHint($R,array$k,$Y){return"";}function
processInput(array$k,$Y,$p=""){if($p=="SQL")return$Y;$B=$k["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$p))$J="$p()";elseif(preg_match('~^current_(date|timestamp)$~',$p))$J=$p;elseif(preg_match('~^([+-]|\|\|)$~',$p))$J=idf_escape($B)." $p $J";elseif(preg_match('~^[+-] interval$~',$p))$J=idf_escape($B)." $p ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&JUSH!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$p))$J="$p(".idf_escape($B).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$p))$J="$p($J)";return
unconvert_field($k,$J);}function
dumpOutput(){$J=array('text'=>'open','file'=>'save');if(function_exists('gzencode'))$J['gz']='gzip';return$J;}function
dumpFormat(){return(support("dump")?array('sql'=>'SQL'):array())+array('csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV');}function
dumpPrint(){}function
dumpDatabase($h){}function
dumpTable($R,$zk,$pf=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($zk)dump_csv(array_keys(fields($R)));}else{if($pf==2){$l=array();foreach(fields($R)as$B=>$k)$l[]=idf_escape($B)." ".full_type_sql($k);$Kb="CREATE TABLE ".table($R)." (".implode(", ",$l).")";}else$Kb=create_sql($R,$_POST["auto_increment"],$zk);set_utf8mb4($Kb);if($zk&&$Kb){if(($zk=="DROP+CREATE"&&!function_exists('Adminer\drop_sql'))||$pf==1)echo"DROP ".($pf==2?"VIEW":"TABLE")." IF EXISTS ".table($R).";\n";if($pf==1)$Kb=remove_definer($Kb);echo"$Kb;\n\n";}}}function
dumpData($R,$zk,$H,array$N=array(),array$Z=array(),array$q=array(),array$D=array()){if($zk){$eg=(JUSH=="sqlite"?0:1048576);$l=array();$He=false;if($_POST["format"]=="sql"){if($zk=="TRUNCATE+INSERT"&&!function_exists('Adminer\truncate_all_sql'))echo
truncate_sql($R).";\n";$l=fields($R);if(JUSH=="mssql"){foreach($l
as$k){if($k["auto_increment"]){echo"SET IDENTITY_INSERT ".table($R)." ON;\n";$He=true;break;}}}}$I=($H!=""?connection()->query($H,1):driver()->select($R,($N?:array("*")),$Z,$q,$D,0));if($I){$af="";$Ua="";$wf=array();$Zd=array();$Ak="";$yd=($R!=''?'fetch_assoc':'fetch_row');$Jb=0;while($K=$I->$yd()){if(!$wf){$gm=array();foreach($K
as$X){$k=$I->fetch_field();if(idx($l[$k->name],'generated')){$Zd[$k->name]=true;continue;}$wf[]=$k->name;$w=idf_escape($k->name);$gm[]="$w = VALUES($w)";}$Ak=($zk=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$gm):"").";\n";}foreach($K
as$w=>$X){if(is_array($X))$K[$w]=json_encode($X,256|64);}if($_POST["format"]!="sql"){if($zk=="table"){dump_csv($wf);$zk="INSERT";}dump_csv($K);}else{if(!$af)$af="INSERT INTO ".table($R)." (".implode(", ",array_map('Adminer\idf_escape',$wf)).") VALUES";foreach($K
as$w=>$X){if($Zd[$w]){unset($K[$w]);continue;}$k=$l[$w];$K[$w]=($X===null?"NULL":($X===false?0:unconvert_field($k,preg_match(number_type(),$k["type"])&&!preg_match('~\[~',$k["full_type"])&&is_numeric($X)?$X:(!is_blob($k)||is_utf8($X)?q($X):driver()->quoteBinary($X)))));}$zj=($eg?"\n":" ")."(".implode(",\t",$K).")";if(!$Ua)$Ua=$af.$zj;elseif(JUSH=='mssql'?$Jb%1000!=0:strlen($Ua)+4+strlen($zj)+strlen($Ak)<$eg)$Ua
.=",$zj";else{echo$Ua.$Ak;$Ua=$af.$zj;}}$Jb++;}if($Ua)echo$Ua.$Ak;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",connection()->error)."\n";else
dump_csv(array(connection()->error));if($He)echo"SET IDENTITY_INSERT ".table($R)." OFF;\n";}}function
dumpFilename($Ge){return
friendly_url($Ge!=""?$Ge:(SERVER?:"localhost"));}function
dumpHeaders($Ge,$Ig=false){$Oh=$_POST["output"];$qd=(preg_match('~sql~',$_POST["format"])?"sql":($Ig?"tar":"csv"));header("Content-Type: ".($Oh=="gz"?"application/x-gzip":($qd=="tar"?"application/x-tar":($qd=="sql"||$Oh!="file"?"text/plain":"text/csv")."; charset=utf-8")));if($Oh=="gz"){ob_start(function($Q){return
gzencode($Q);},1e6);}return$qd;}function
dumpFooter(){if($_POST["format"]=="sql")echo"-- ".gmdate("Y-m-d H:i:s e")."\n";}function
importServerPath(){return"adminer.sql";}function
importPrint(){}function
importProcess(){return
false;}function
homepage(){echo'<p class="links">'.($_GET["ns"]==""&&support("database")?'<a href="'.h(ME).'database=">'.'Alter database'."</a>\n":""),(support("scheme")?"<a href='".h(ME)."scheme='>".($_GET["ns"]!=""?'Alter schema':'Create schema')."</a>\n":""),($_GET["ns"]!==""?'<a href="'.h(ME).'schema=">'.'Database schema'."</a>\n":""),(support("privileges")?"<a href='".h(ME)."privileges='>".'Privileges'."</a>\n":"");if($_GET["ns"]!=="")echo(support("routine")?"<a href='#routines'>".'Routines'."</a>\n":""),(support("sequence")?"<a href='#sequences'>".'Sequences'."</a>\n":""),(support("type")?"<a href='#user-types'>".'User types'."</a>\n":""),(support("event")?"<a href='#events'>".'Events'."</a>\n":"");return
true;}function
navigation($Bg){echo"<h1>".adminer()->name()." <span class='version'>".VERSION;$Xg=$_COOKIE["adminer_version"];echo" <a href='https://www.adminer.org/#download'".target_blank()." id='version'>".(version_compare(VERSION,$Xg)<0?h($Xg):"").version_iframe()."</a>","</span></h1>\n";if($Bg=="auth"){$Oh="";foreach((array)$_SESSION["pwds"]as$im=>$Uj){foreach($Uj
as$O=>$bm){$B=h(get_setting("vendor-$im-$O")?:get_driver($im));foreach($bm
as$V=>$F){if($B&&$F!==null){$bc=$_SESSION["db"][$im][$O][$V];foreach(($bc?array_keys($bc):array(""))as$h)$Oh
.="<li><a href='".h(auth_url($im,$O,$V,$h))."'>($B) ".h("$V@").($O!=""?adminer()->serverName($O):"").h($h!=""?" - $h":"")."</a>\n";}}}}if($Oh)echo"<ul id='logins'".on('mouseover','menuOver').on('mouseout','menuOut').">\n$Oh</ul>\n";}else{$T=array();if($_GET["ns"]!==""&&!$Bg&&DB!=""){connection()->select_db(DB);$T=table_status('',true);}adminer()->syntaxHighlighting($T);adminer()->databasesPrint($Bg);$ga=array();if(DB==""||!$Bg){if(support("sql")){$ga['sql']="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".'SQL command'."</a>";$ga['import']="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".'Import'."</a>";}$ga['dump']="<a href='".h(ME)."dump=".url_escape(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".'Export'."</a>";}$Ne=$_GET["ns"]!==""&&!$Bg&&DB!="";if($Ne&&function_exists('Adminer\alter_table'))$ga['create']='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".'Create table'."</a>";$ga=adminer()->menuActions($ga,$Bg);echo($ga?"<p class='links'>\n".implode("\n",$ga)."\n":"");if($Ne){if($T)adminer()->tablesPrint($T);else
echo"<p class='message'>".'No tables.'."</p>\n";}}}function
syntaxHighlighting(array$T){echo
script_src(preg_replace("~\\?.*~","",ME)."?file=jush.js&version=6.2.0+df93d571",true);$Eg=preg_replace('~<(?=/script)~i','<\\',Driver::jushModule());echo($Eg?script("addEventListener('DOMContentLoaded', () => {\n$Eg\n});"):"");if(support("sql")){echo"<script".nonce().">\n";if($T){$Qf=array();foreach($T
as$R=>$U)$Qf[]=js_escape_re($R);echo"var jushLinks = { ".JUSH.":";json_row(js_escape(ME).(support("table")?"table":"select").'=$&','/\b(?<!\$)('.implode('|',$Qf).')(?!\$)\b/g',false);$ok=array("sql","check","event","procedure","trigger","view","type","table","processlist");if(support("routine")&&array_intersect_key($_GET,array_flip($ok))){foreach(routines()as$K)json_row(js_escape(ME).'function='.url_escape($K["SPECIFIC_NAME"]).'&name=$&','/\b'.js_escape_re($K["ROUTINE_NAME"]).'(?=["`\]]?\()/g',false);}json_row('');echo"};\n";foreach(array("bac","bra","sqlite_quo","mssql_bra")as$X)echo"jushLinks.$X = jushLinks.".JUSH.";\n";if(array_intersect_key($_GET,array_flip(array("sql","check","event","procedure","trigger","view")))){$tk=(isset($_GET["trigger"])?array('INSERT INTO','UPDATE','DELETE FROM'):(isset($_GET["check"])?array():(isset($_GET["view"])?array('SELECT'):null)));$Fa=Driver::jushAutocomplete($T,$tk);echo($Fa?"addEventListener('DOMContentLoaded', () => { autocompleter = $Fa; });\n":"");}}echo"</script>\n";}echo
script("syntaxHighlighting('".doc_version()."', '".connection()->flavor."');");}function
databasesPrint($Bg){if(support("single_db"))return;$g=adminer()->databases();if(DB&&$g&&!in_array(DB,$g))array_unshift($g,DB);echo"<form action=''>\n<p id='dbs'>\n";hidden_fields_get();$Yb=on('mousedown','dbMouseDown').on('change','dbChange');echo"<label title='".'Database'."'>".'DB'.": ".($g?html_select("db",array(""=>"")+group_system($g),DB,$Yb):"<input name='db' value='".h(DB)."' autocapitalize='off' size='19'>\n")."</label>","<input type='submit' value='".'Use'."'".($g?" class='hidden'":"").">\n";foreach(array("import","sql","schema","dump","privileges")as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</p></form>\n";}function
menuActions(array$ga,$Bg){return$ga;}function
tablesPrint(array$T){echo"<ul id='tables'".on('mouseover','menuOver').on('mouseout','menuOut').">";foreach($T
as$R=>$uk){$R="$R";$B=adminer()->tableName($uk);if($B!=""&&!$uk["dependent"])echo'<li><a href="'.h(ME).'select='.url_escape($R).'"'.bold($_GET["select"]==$R||$_GET["edit"]==$R,"select hover")." title='".'Select data'."'>".'select'."</a> ",(support("table")||support("indexes")?'<a href="'.h(ME).'table='.url_escape($R).'"'.bold(in_array($R,array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"])),(is_view($uk)?"view":"structure"))." title='".'Show structure'."'>$B</a>":"<span>$B</span>")."\n";}echo"</ul>\n";}function
showVariables(){return
show_variables();}function
showStatus(){return
show_status();}function
processList(){return
process_list();}function
killProcess($s){return
kill_process($s);}function
formatSizeValue($X){return
format_number($X);}}class
Plugins{private
static$append=array('dumpFormat'=>true,'dumpOutput'=>true,'editRowPrint'=>true,'editFunctions'=>true,'config'=>true);var$plugins;var$disabled=array();var$drivers=array();var$driverFiles=array();var$error='';private$hooks=array();function
__construct($si){$Dc=SqlDriver::$drivers;$we=" href='https://www.adminer.org/plugins/#use'".target_blank();if($si===null){$si=array();$Na="adminer-plugins";if(is_dir($Na)){foreach(glob("$Na/*.php")as$m){$Cd=SqlDriver::$drivers;$this->includeOnce($m);foreach(array_diff_key(SqlDriver::$drivers,$Cd)as$s=>$B)$this->driverFiles[$s]=$m;}}if(file_exists("$Na.php")){$Pe=$this->includeOnce("$Na.php");if(is_array($Pe)){foreach($Pe
as$w=>$pi)$si[is_object($pi)?get_class($pi):$w]=$pi;}else$this->error
.=sprintf('%s must <a%s>return an array</a>.',"<b>$Na.php</b>",$we)."<br>";}foreach(get_declared_classes()as$mb){if(!$si[$mb]&&(preg_match('~^Adminer\w~i',$mb)||is_subclass_of($mb,'Adminer\Plugin'))){$bj=new
\ReflectionClass($mb);$Bb=$bj->getConstructor();if($Bb&&$Bb->getNumberOfRequiredParameters())$this->error
.=sprintf('<a%s>Configure</a> %s in %s.',$we,"<b>$mb</b>","<b>$Na.php</b>")."<br>";else$si[$mb]=new$mb;}}}$ff=array_filter($si,function($pi){return!is_object($pi);});if($ff){$this->error
.=sprintf('Every plugin must <a%s>be an object</a>.',$we)."<br>";$si=array_diff_key($si,$ff);}$Ek=get_setting("plugins","adminer_config");foreach($si
as$w=>$pi){if($pi
instanceof
Plugin&&$pi::$optional){$rk=idx($Ek,get_class($pi));if($rk!==null?!$rk:!$pi->enabled){$this->disabled[]=$pi;unset($si[$w]);}}}$this->drivers=array_diff_key(SqlDriver::$drivers,$Dc);$this->plugins=$si;$ja=new
Adminer;$si[]=$ja;$bj=new
\ReflectionObject($ja);foreach($bj->getMethods()as$zg){foreach($si
as$pi){$B=$zg->getName();if(method_exists($pi,$B))$this->hooks[$B][]=$pi;}}}function
includeOnce($m){return
include_once"./$m";}static
function
describe($pi){$mc=(method_exists($pi,'description')?$pi->description():"");if(!$mc){$bj=new
\ReflectionObject($pi);if(preg_match('~^/[\s*]+(.+?)(?:\s*\*/)?$~m',$bj->getDocComment(),$A))$mc=$A[1];}$Dj=(method_exists($pi,'screenshot')?$pi->screenshot():"");return
h($mc).($Dj?($mc?" ":"")."(<a href='".h($Dj)."'".target_blank().">".'screenshot'."</a>)":"");}static
function
checksum($m){$Bd=str_replace("\r","",file_get_contents($m));$Bd=preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s','',$Bd);return
dechex(crc32($Bd));}function
checksums(){$Dd=array_values($this->driverFiles);foreach($this->plugins
as$pi){$bj=new
\ReflectionObject($pi);$Dd[]=$bj->getFileName();}$J=array();foreach($Dd
as$m)$J[basename($m,'.php')]=self::checksum($m);return$J;}static
function
officialChecksums(){return
array('adminer.js'=>'a0599090','backward-keys'=>'1c017d59','before-unload'=>'9674d220','config'=>'bdac2201','dark-switcher'=>'8c201319','database-hide'=>'e304a899','designs'=>'5903cccc','dump-alter'=>'896b579e','dump-bz2'=>'c9229da6','dump-date'=>'512aad63','dump-json'=>'f8aa6b99','dump-xml'=>'aedd2d3a','dump-zip'=>'735d7e2','edit-foreign'=>'d04a0ac8','edit-textarea'=>'a178d7d9','editor-setup'=>'a7dc3a37','editor-views'=>'5c12b185','enum-option'=>'e9005cb0','file-upload'=>'10add0e8','foreign-system'=>'634ce119','frames'=>'b0e1d11a','highlight-codemirror'=>'ada4b569','highlight-monaco'=>'ec1da606','highlight-prism'=>'19933b19','import-csv'=>'bcecfad2','login-ip'=>'4d174fea','login-otp'=>'66bd013d','login-passkey'=>'89783b50','login-password-less'=>'e150daac','login-reverse-proxy'=>'24558ea2','login-servers'=>'19c42e45','login-ssl'=>'2b06a5b7','login-table'=>'811f8cef','menu-links'=>'55b9b15c','name-patterns'=>'84c10d09','remote-color'=>'267580a3','row-numbers'=>'1ad1b6cf','select-email'=>'c33a2661','select-expression'=>'b6a4b3f1','select-foreign'=>'577c07b2','select-image'=>'39ed1607','slugify'=>'7b422f0e','sql-gemini'=>'c60ab309','sql-log'=>'8e435000','sql-url-length'=>'cd70f1a0','table-indexes-structure'=>'e0b1433','table-structure'=>'f71ec61b','tables-filter'=>'2729bf7','timeout'=>'97321caf','version-github'=>'627cadf9','version-noverify'=>'966937e9','clickhouse'=>'51a3d7d5','elastic'=>'9b83a12a','firebird'=>'5f3d9493','igdb'=>'3b00190b','imap'=>'3ba195cd','mongo'=>'235e3ba','redis'=>'41709f61','simpledb'=>'b8e2cc7d',);}function
__call($B,array$Th){$ya=array();foreach($Th
as$w=>$X)$ya[]=&$Th[$w];$J=null;foreach($this->hooks[$B]as$pi){$Y=call_user_func_array(array($pi,$B),$ya);if($Y!==null){if(!self::$append[$B])return$Y;$J=$Y+(array)$J;}}return$J;}}abstract
class
Plugin{static$optional=false;var$enabled=true;protected$translations=array();function
disable(){$this->enabled=false;return$this;}function
description(){return$this->lang('');}function
screenshot(){return"";}protected
function
lang($t,$dh=null){$ya=func_get_args();$ya[0]=idx($this->translations[LANG],$t)?:$t;return
call_user_func_array('Adminer\lang_format',$ya);}}class
Password{private$password_hash;private$password_matches=null;function
__construct($gi){$this->password_hash=$gi;}function
description(){return'Require a password verified by Adminer';}function
credentials(){$F=get_password();return
array(SERVER,$_GET["username"],($this->passwordMatches($F)&&!password_required()?"":$F));}function
login($Uf,$F){if($this->passwordMatches($F))return
true;if(!Driver::$passwords||!password_required())return
false;}protected
function
passwordMatches($F){if($this->password_matches===null)$this->password_matches=(function_exists('password_verify')&&password_verify(strval($F),$this->password_hash));return$this->password_matches;}}Adminer::$instance=(function_exists('adminer_object')?adminer_object():(is_dir("adminer-plugins")||file_exists("adminer-plugins.php")?new
Plugins(null):new
Adminer));SqlDriver::$drivers=array("server"=>"MySQL / MariaDB")+SqlDriver::$drivers;if(!defined('Adminer\DRIVER')){define('Adminer\DRIVER',"server");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){class
Db
extends
\mysqli{static$instance;var$extension="MySQLi",$flavor='';function
__construct(){parent::init();}function
attach(array$O,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);$ti=$O["port"];$Sc=("$O[host]$ti$O[socket]"=="");$pk=adminer()->connectSsl();$Yl=($pk&&($pk['key']||$pk['cert']||$pk['ca']||isset($pk['verify'])));if($Yl)$this->ssl_set($pk['key'],$pk['cert'],$pk['ca'],'','');$J=@$this->real_connect((!$Sc?$O["host"]:ini_get("mysqli.default_host")),(!$Sc||$V!=""?$V:ini_get("mysqli.default_user")),(!$Sc||$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,($ti!=""?intval($ti):ini_get("mysqli.default_port")),($ti!=""?null:$O["socket"]),($Yl?($pk['verify']!==false?MYSQLI_CLIENT_SSL:64):0));$this->options(MYSQLI_OPT_LOCAL_INFILE,0);return($J?'':$this->error);}function
set_charset($cb){if(parent::set_charset($cb))return
true;parent::set_charset('utf8');return$this->query("SET NAMES $cb");}function
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
sprintf('Disable %s or enable the %s or %s extension.',"'mysql.allow_local_infile'","MySQLi","PDO_MySQL");$ti="$O[port]$O[socket]";$B=$O["host"].($ti!=""?":$ti":"");$this->link=@mysql_connect(($B!=""?$B:ini_get("mysql.default_host")),($B.$V!=""?$V:ini_get("mysql.default_user")),($B.$V.$F!=""?$F:ini_get("mysql.default_password")),true,131072);if(!$this->link)return
mysql_error();$this->server_info=mysql_get_server_info($this->link);return'';}function
set_charset($cb){return
mysql_set_charset($cb,$this->link)||mysql_set_charset('utf8',$this->link);}function
quote($Q){return"'".mysql_real_escape_string($Q,$this->link)."'";}function
select_db($Xb){return
mysql_select_db($Xb,$this->link);}function
query($H,$Fl=false){$I=@($Fl?mysql_unbuffered_query($H,$this->link):mysql_query($H,$this->link));$this->error="";if(!$I){$this->errno=mysql_errno($this->link);$this->error=mysql_error($this->link);return
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
attach(array$O,$V,$F){$C=array(\PDO::MYSQL_ATTR_LOCAL_INFILE=>false);if(isset($_GET["select"]))$C[\PDO::MYSQL_ATTR_MULTI_STATEMENTS]=false;$pk=adminer()->connectSsl();if($pk){if($pk['key'])$C[\PDO::MYSQL_ATTR_SSL_KEY]=$pk['key'];if($pk['cert'])$C[\PDO::MYSQL_ATTR_SSL_CERT]=$pk['cert'];if($pk['ca'])$C[\PDO::MYSQL_ATTR_SSL_CA]=$pk['ca'];if(isset($pk['verify']))$C[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=$pk['verify'];}$Ce=$O["host"];$ti=$O["port"];$dk=$O["socket"];return$this->dsn("mysql:charset=utf8".($Ce!=""?";host=$Ce":'').($ti!=""?";port=$ti":($dk!=""?";unix_socket=$dk":"")),$V,$F,$C);}function
set_charset($cb){return$this->query("SET NAMES $cb");}function
select_db($Xb){return$this->query("USE ".idf_escape($Xb));}function
query($H,$Fl=false){$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$Fl);return
parent::query($H,$Fl);}}}class
Driver
extends
SqlDriver{static$extensions=array("MySQLi","MySQL","PDO_MySQL");static$jush="sql";static$serverSocket=true;var$unsigned=array("unsigned","zerofill","unsigned zerofill");var$functions=array("char_length","date","from_unixtime","lower","round","floor","ceil","sec_to_time","time_to_sec","upper");var$grouping=array("avg","count","count distinct","group_concat","max","min","sum");var$partitionBy=array("HASH","LINEAR HASH","KEY","LINEAR KEY","RANGE","LIST");function
operators($Ik){return
array("=","<",">","<=",">=","!=","&","LIKE","LIKE %%","REGEXP","IN","BETWEEN","FIND_IN_SET","IS NULL","NOT LIKE","NOT REGEXP","NOT IN","IS NOT NULL","SQL");}static
function
connect($O,$V,$F){$e=parent::connect($O,$V,$F);if(is_string($e)){if(function_exists('iconv')&&!is_utf8($e)&&strlen($zj=iconv("windows-1252","utf-8//IGNORE",$e))>strlen($e))$e=$zj;return$e;}$e->set_charset(charset($e));$e->query("SET sql_quote_show_create = 1, autocommit = 1");$e->flavor=(preg_match('~MariaDB~',$e->server_info)?'maria':'mysql');add_driver(DRIVER,($e->flavor=='maria'?"MariaDB":"MySQL"));return$e;}function
__construct(Db$e){parent::__construct($e);$this->types=array('Numbers'=>array("tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21),'Date and time'=>array("date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4),'Strings'=>array("char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295),'Lists'=>array("enum"=>65535,"set"=>64),'Binary'=>array("bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295),'Geometry'=>array("geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0),);$this->insertFunctions=array("char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",);$this->editFunctions=array(number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",);if(min_version('5.7.8',10.2,$e))$this->types['Strings']["json"]=4294967295;if(min_version('',10.7,$e)){$this->types['Strings']["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if(min_version('',10.5,$e)){$this->types['Network']["inet6"]=39;if(min_version('','10.10',$e))$this->types['Network']["inet4"]=15;}if(min_version(9,11.7,$e))$this->types['Numbers']["vector"]=16383;if(min_version(5.7,10.2,$e))$this->generated=array("STORED","VIRTUAL");}function
unconvertFunction(array$k){return(preg_match("~binary~",$k["type"])?"<code class='jush-sql'>UNHEX</code>":($k["type"]=="bit"?doc_link(array('sql'=>'bit-value-literals.html'),"<code>b''</code>"):($k["type"]=="vector"?"<code class='jush-sql'>".($this->conn->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."</code>":(preg_match("~geom|point|linestring|polygon~",$k["type"])?"<code class='jush-sql'>GeomFromText</code>":""))));}function
insert($R,array$P){return($P?parent::insert($R,$P):queries("INSERT INTO ".table($R)." ()\nVALUES ()"));}function
insertUpdate($R,array$L,array$Ei){$d=array_keys(reset($L));$Ai="INSERT INTO ".table($R)." (".implode(", ",$d).") VALUES\n";$gm=array();foreach($d
as$w)$gm[$w]="$w = VALUES($w)";$Ak="\nON DUPLICATE KEY UPDATE ".implode(", ",$gm);$gm=array();$x=0;foreach($L
as$P){$Y="(".implode(", ",$P).")";if($gm&&(strlen($Ai)+$x+strlen($Y)+strlen($Ak)>1e6)){if(!queries($Ai.implode(",\n",$gm).$Ak))return
false;$gm=array();$x=0;}$gm[]=$Y;$x+=strlen($Y)+2;}return
queries($Ai.implode(",\n",$gm).$Ak);}function
slowQuery($H,$hl){if(min_version('5.7.8','10.1.2')){if($this->conn->flavor=='maria')return"SET STATEMENT max_statement_time=$hl FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$A))return"$A[1] /*+ MAX_EXECUTION_TIME(".($hl*1000).") */ $A[2]";}}function
convertColumn($t,array$k){if(preg_match("~binary~",$k["type"]))return"HEX($t)";if($k["type"]=="bit")return"BIN($t + 0)";if($k["type"]=="vector")return($this->conn->flavor=='maria'?"VEC_ToText":"VECTOR_TO_STRING")."($t)";if(preg_match("~geom|point|linestring|polygon~",$k["type"]))return(min_version(8)?"ST_":"")."AsWKT($t)";return"";}function
convertSearch($t,array$X,array$k){return($this->convertColumn($t,$k)?:(preg_match('~'.text_type().'~',$k["type"])&&!preg_match("~^utf8~",$k["collation"])&&preg_match('~[\x80-\xFF]~',$X['val'])?"CONVERT($t USING ".charset($this->conn).")":$t));}function
typeName(\stdClass$k){$B=parent::typeName($k);if($B!=""){$El=array("TINY"=>"tinyint","SHORT"=>"smallint","LONG"=>"int","INT24"=>"mediumint","LONGLONG"=>"bigint","NEWDECIMAL"=>"decimal","VAR_STRING"=>"varchar","STRING"=>"char",);return
idx($El,$B,strtolower($B));}$El=array("decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",);$J=idx($El,$k->type,"");return($k->charsetnr==63?str_replace(array("text","varchar","char"),array("blob","varbinary","binary"),$J):$J);}function
quoteBinary($zj){return"X".q(bin2hex($zj));}function
md5($c,array$k){if(is_blob($k)||preg_match('~'.text_type().'~',$k["type"]))return"MD5(".(is_blob($k)||preg_match("~^utf8~",$k["collation"])?$c:"CONVERT($c USING ".charset($this->conn).")").")";}function
warnings(){$I=$this->conn->query("SHOW WARNINGS");if($I&&$I->num_rows){ob_start();print_select_result($I);return
ob_get_clean();}}function
errorLine($H){return(preg_match('~ at line (\d+)$~',$this->conn->error,$A)?(int)$A[1]:0);}function
tableHelp($B,$pf=false){$Wf=($this->conn->flavor=='maria');if(information_schema(DB))return
strtolower(str_replace("_","-",DB)."-".($Wf?"$B-table/":str_replace("_","-",$B)."-table.html"));if(DB=="sys")return($Wf?"sys-schema/":strtolower("sys-".str_replace("_","-",preg_replace('~^x\$~','',$B)).".html"));if(DB=="mysql")return($Wf?"mysql$B-table/":"system-schema.html");}function
partitionsInfo($R){$Td="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($R);$I=$this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $Td ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");$K=($I?$I->fetch_row():null);if(!$K)return
array();$J=array();list($J["partition_by"],$J["partition"],$J["partitions"])=$K;$ci=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $Td AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$J["partition_names"]=array_keys($ci);$J["partition_values"]=array_values($ci);return$J;}function
checkConstraints($R){$J=parent::checkConstraints($R);return($this->conn->flavor=='maria'?$J:array_map('stripslashes',$J));}function
hasCStyleEscapes(){static$Va;if($Va===null){$nk=get_val("SHOW VARIABLES LIKE 'sql_mode'",1,$this->conn);$Va=(strpos($nk,'NO_BACKSLASH_ESCAPES')===false);}return$Va;}function
hasEstimatedRows(){return
true;}function
isSystem($h,$M=""){return
information_schema($h,$M)||in_array($h,array("mysql","sys"));}function
lineComment(){return"#|-- ";}function
engines(){$J=array();foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$J[]=$K["Engine"];}return$J;}function
indexAlgorithms(array$Ik){return(preg_match('~^(MEMORY|NDB)$~',$Ik["Engine"])?array("HASH","BTREE"):array());}}function
idf_escape($t){return"`".str_replace("`","``",$t)."`";}function
table($t){return
idf_escape($t);}function
get_databases($Kd){$J=get_session("dbs");if($J===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$qk=microtime(true);$J=($Kd?slow_query($H):get_vals($H));if(microtime(true)-$qk>0.1){restart_session();set_session("dbs",$J);stop_session();}}return$J;}function
limit($H,$Z,$y,$kh=0,$Nj=" "){return" $H$Z".($y?$Nj."LIMIT $y".($kh?" OFFSET $kh":""):"");}function
limit1($R,$H,$Z,$Nj="\n"){return
limit($H,$Z,1,0,$Nj);}function
db_collation($h,array$rb){$J=null;$Kb=get_val("SHOW CREATE DATABASE ".idf_escape($h),1);if(preg_match('~ COLLATE ([^ ]+)~',$Kb,$A))$J=$A[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$Kb,$A))$J=$rb[$A[1]][-1];return$J;}function
logged_user(){return
get_val("SELECT CURRENT_USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables(array$g){$J=array();foreach($g
as$h)$J[$h]=count(get_vals("SHOW TABLES IN ".idf_escape($h)));return$J;}function
table_status($B="",$wd=false){$J=array();$H="SELECT ENGINE AS Engine, TABLE_NAME AS Name, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($B!=""?"AND TABLE_NAME = ".q($B):"ORDER BY Name");$M=array();foreach(($wd?array():get_rows($H))as$K)$M[$K["Name"]]=$K;$Di=null;foreach(get_rows($wd?$H:"SHOW TABLE STATUS".($B!=""?" LIKE ".q(addcslashes($B,"%_\\")):""))as$K){$Lh=idx($M,$K["Name"]);if($Lh){if($K["Comment"]!==$Lh["Comment"]&&$K["Comment"]!==$Di)$K["Error"]=$K["Comment"];$Di=$K["Comment"];$K["Comment"]=$Lh["Comment"];$K["Engine"]=$Lh["Engine"];}if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($B!="")$K["Name"]=$B;$J[$K["Name"]]=$K;}return$J;}function
is_view(array$S){return$S["Engine"]===null;}function
fk_support(array$S){return
preg_match('~InnoDB|IBMDB2I'.(min_version(5.6)?'|NDB':'').'~i',$S["Engine"]);}function
parse_type($Vd){preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$Vd,$A);return
array($A[1],$A[2],ltrim($A[3].$A[4]));}function
fields($R){$Wf=(connection()->flavor=='maria');$J=array();foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($R)." ORDER BY ORDINAL_POSITION")as$K){$k=$K["COLUMN_NAME"];$U=$K["COLUMN_TYPE"];$ae=$K["GENERATION_EXPRESSION"];$td=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$td,$Zd);list($Dl,$x,$Ml)=parse_type($U);$i=$K["COLUMN_DEFAULT"];$fc=false;if($i!=""){$Si=$Wf;if($Wf)$fc=!preg_match("~^('(?:[^'\\\\]|\\\\.|'')*'|NULL|[-+]?[\\d.]+)$~s",$i);elseif(preg_match('~text|json~',$Dl)||preg_match('~\bDEFAULT_GENERATED\b~',$td)){$i=stripslashes($i);$Si=preg_match("~^(_\\w+)?('(?:[^'\\\\]|\\\\.|'')*')$~s",$i,$A);if($Si)$i=$A[2];else$fc=true;}elseif(preg_match("~^[bx]'[\\da-f]*'$~i",$i))$fc=true;if($Si)$i=($i=="NULL"?null:(preg_match("~^'.*'$~s",$i)?stripslashes(idf_unescape($i)):$i));if(preg_match('~binary~',$Dl)&&preg_match("~^(?:0x(\\w*)|x'(\\w*)')$~",$i,$A)){$i=pack("H*",$A[1].idx($A,2));$fc=false;}}$J[$k]=array("field"=>$k,"full_type"=>$U,"type"=>$Dl,"length"=>$x,"unsigned"=>$Ml,"default"=>($Zd?($Wf?$ae:stripslashes($ae)):$i),"default_sql"=>(!$Zd&&$fc),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($td=="auto_increment"),"on_update"=>(preg_match('~\bon update current_timestamp\b~i',$td)?"CURRENT_TIMESTAMP":""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",","$K[PRIVILEGES],where,order")),"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($Zd[1]=="PERSISTENT"?"STORED":$Zd[1]),);}return$J;}function
indexes($R,$f=null){$J=array();foreach(get_rows("SHOW INDEX FROM ".table($R),$f)as$K){$B=$K["Key_name"];$J[$B]["type"]=($B=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$B]["columns"][]=$K["Column_name"];$J[$B]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$B]["descs"][]=null;$J[$B]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($R){static$ki='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=array();$Lb=get_val("SHOW CREATE TABLE ".table($R),1);if($Lb){preg_match_all("~CONSTRAINT ($ki) FOREIGN KEY ?\\(((?:$ki,? ?)+)\\) REFERENCES ($ki)(?:\\.($ki))? \\(((?:$ki,? ?)+)\\)(?: ON DELETE (".driver()->onActions."))?(?: ON UPDATE (".driver()->onActions."))?~",$Lb,$Yf,PREG_SET_ORDER);foreach($Yf
as$A){preg_match_all("~$ki~",$A[2],$hk);preg_match_all("~$ki~",$A[5],$Xk);$J[idf_unescape($A[1])]=array("db"=>idf_unescape($A[4]!=""?$A[3]:$A[4]),"table"=>idf_unescape($A[4]!=""?$A[4]:$A[3]),"source"=>array_map('Adminer\idf_unescape',$hk[0]),"target"=>array_map('Adminer\idf_unescape',$Xk[0]),"on_delete"=>($A[6]?:"RESTRICT"),"on_update"=>($A[7]?:"RESTRICT"),);}}return$J;}function
view($B){return
array("select"=>preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU','',get_val("SHOW CREATE VIEW ".table($B),1)));}function
collations(){$J=array();foreach(get_rows("SHOW COLLATION")as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$w=>$X)sort($J[$w]);return$J;}function
information_schema($h,$M=""){return($h=="information_schema")||(min_version(5.5)&&$h=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",connection()->error));}function
create_database($h,$qb){return
queries("CREATE DATABASE ".idf_escape($h).($qb?" COLLATE ".q($qb):""));}function
drop_databases(array$g){$J=apply_queries("DROP DATABASE",$g,'Adminer\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($B,$qb){$J=false;if(create_database($B,$qb)){$T=array();$lm=array();foreach(tables_list()as$R=>$U){if($U=='VIEW')$lm[]=$R;else$T[]=$R;}$J=(!$T&&!$lm)||move_tables($T,$lm,$B);drop_databases($J?array(DB):array());}return$J;}function
auto_increment(){$Ea=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$u){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$u["columns"],true)){$Ea="";break;}if($u["type"]=="PRIMARY")$Ea=" UNIQUE";}}return" AUTO_INCREMENT$Ea";}function
alter_table($R,$B,array$l,array$Md,$vb,$Vc,$qb,$Da,$bi){$ta=array();foreach($l
as$k){if($k[1]){$i=$k[1][3];if(preg_match('~ GENERATED~',$i)){$k[1][3]=(connection()->flavor=='maria'?"":$k[1][2]);$k[1][2]=$i;}$ta[]=($R!=""?($k[0]!=""?"CHANGE ".idf_escape($k[0]):"ADD"):" ")." ".implode($k[1]).($R!=""?$k[2]:"");}else$ta[]="DROP ".idf_escape($k[0]);}$ta=array_merge($ta,$Md);$uk=($vb!==null?" COMMENT=".q($vb):"").($Vc?" ENGINE=".q($Vc):"").($qb?" COLLATE ".q($qb):"").($Da!=""?" AUTO_INCREMENT=$Da":"");if($bi){$ci=array();if($bi["partition_by"]=='RANGE'||$bi["partition_by"]=='LIST'){foreach($bi["partition_names"]as$w=>$X){$Y=$bi["partition_values"][$w];$ci[]="\n  PARTITION ".idf_escape($X)." VALUES ".($bi["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$uk
.="\nPARTITION BY $bi[partition_by]($bi[partition])";if($ci)$uk
.=" (".implode(",",$ci)."\n)";elseif($bi["partitions"])$uk
.=" PARTITIONS ".(+$bi["partitions"]);}elseif($bi===null)$uk
.="\nREMOVE PARTITIONING";if($R=="")return
queries("CREATE TABLE ".table($B)." (\n".implode(",\n",$ta)."\n)$uk");if($R!=$B)$ta[]="RENAME TO ".table($B);if($uk)$ta[]=ltrim($uk);return($ta?queries("ALTER TABLE ".table($R)."\n".implode(",\n",$ta)):true);}function
alter_indexes($R,$ta){$ab=array();foreach($ta
as$X)$ab[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return
queries("ALTER TABLE ".table($R).implode(",",$ab));}function
truncate_tables(array$T){return
apply_queries("TRUNCATE TABLE",$T);}function
drop_views(array$lm){return
queries("DROP VIEW ".implode(", ",array_map('Adminer\table',$lm)));}function
drop_tables(array$T){return
queries("DROP TABLE ".implode(", ",array_map('Adminer\table',$T)));}function
move_tables(array$T,array$lm,$Xk){$gj=array();foreach($T
as$R)$gj[]=table($R)." TO ".idf_escape($Xk).".".table($R);if(!$gj||queries("RENAME TABLE ".implode(", ",$gj))){$jc=array();foreach($lm
as$R)$jc[table($R)]=view($R);connection()->select_db($Xk);$h=idf_escape(DB);foreach($jc
as$B=>$km){if(!queries("CREATE VIEW $B AS ".str_replace(" $h."," ",$km["select"]))||!queries("DROP VIEW $h.$B"))return
false;}return
true;}return
false;}function
copy_tables(array$T,array$lm,$Xk){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($T
as$R){$B=($Xk==DB?table("copy_$R"):idf_escape($Xk).".".table($R));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $B"))||!queries("CREATE TABLE $B LIKE ".table($R))||!queries("INSERT INTO $B SELECT * FROM ".table($R)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){$wl=$K["Trigger"];list($fd,$fh)=trigger_event($K);if(!queries("CREATE TRIGGER ".($Xk==DB?idf_escape("copy_$wl"):idf_escape($Xk).".".idf_escape($wl))." $K[Timing] $fd".($fh!=""?" $fh":"")." ON $B FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($lm
as$R){$B=($Xk==DB?table("copy_$R"):idf_escape($Xk).".".table($R));$km=view($R);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $B"))||!queries("CREATE VIEW $B AS $km[select]"))return
false;}return
true;}function
trigger_event(array$K){$hd=explode(",",$K["Event"]);$J=array();foreach(array("DELETE","INSERT","UPDATE")as$fd){if(in_array($fd,$hd))$J[]=$fd;}$J=implode(" OR ",$J);if(in_array("UPDATE",$hd)&&min_version('','12.0.1')&&preg_match('~\s(?:BEFORE|AFTER)\s+(.+?)\s+ON\s~is',get_val("SHOW CREATE TRIGGER ".idf_escape($K["Trigger"]),2),$A)&&preg_match('~\bOF\s+(.+)~is',$A[1],$fh))return
array("$J OF",$fh[1]);return
array($J,"");}function
trigger($B,$R){if($B=="")return
array();$L=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($B));$J=reset($L);if($J)list($J["Event"],$J["Of"])=trigger_event($J);return($J?:array());}function
triggers($R){$J=array();foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){list($fd)=trigger_event($K);$J[$K["Trigger"]]=array($K["Timing"],$fd);}return$J;}function
trigger_options(){return
array("Timing"=>array("BEFORE","AFTER"),"Event"=>(min_version('','12.0.1')?array("INSERT","UPDATE","UPDATE OF","DELETE","INSERT OR UPDATE","INSERT OR UPDATE OF","DELETE OR INSERT","DELETE OR UPDATE","DELETE OR UPDATE OF","DELETE OR INSERT OR UPDATE","DELETE OR INSERT OR UPDATE OF",):array("INSERT","UPDATE","DELETE")),"Type"=>array("FOR EACH ROW"),);}function
routine($B,$U){$L=get_rows("SELECT PARAMETER_NAME, DTD_IDENTIFIER, PARAMETER_MODE, COLLATION_NAME
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($B)."
ORDER BY ORDINAL_POSITION");$l=array();foreach($L
as$K){$Vd=$K["DTD_IDENTIFIER"];list($Dl,$x,$Ml)=parse_type($Vd);$l[]=array("field"=>$K["PARAMETER_NAME"],"type"=>$Dl,"length"=>$x,"unsigned"=>$Ml,"null"=>true,"full_type"=>$Vd,"inout"=>($U=="FUNCTION"?"":$K["PARAMETER_MODE"]),"collation"=>$K["COLLATION_NAME"],);}$J=connection()->query("SELECT
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
routine_options($rj){return
array("DEFINER"=>array(),"DETERMINISTIC"=>array("NOT DETERMINISTIC","DETERMINISTIC"),"SQL_DATA_ACCESS"=>array("CONTAINS SQL","NO SQL","READS SQL DATA","MODIFIES SQL DATA"),"SQL_SECURITY"=>array("SQL SECURITY DEFINER","SQL SECURITY INVOKER"),"COMMENT"=>array(),);}function
routine_id($B,array$K){return
idf_escape($B);}function
last_id($I){return
get_val("SELECT LAST_INSERT_ID()");}function
explain(Db$e,$H){return$e->query("EXPLAIN ".(min_version(5.7)?"":"PARTITIONS ").$H);}function
found_rows(array$S,array$Z){return($Z||$S["Engine"]!="InnoDB"?null:$S["Rows"]);}function
create_sql($R,$Da,$zk){$J=get_val("SHOW CREATE TABLE ".table($R),1);if(!$Da)$J=preg_replace('~(\n\)[^\n]*?) AUTO_INCREMENT=\d+~','\1',$J);return$J;}function
truncate_sql($R){return"TRUNCATE ".table($R);}function
use_sql($Xb,$zk=""){$B=idf_escape($Xb);$J="";if(preg_match('~CREATE~',$zk)&&($Kb=get_val("SHOW CREATE DATABASE $B",1))){set_utf8mb4($Kb);if($zk=="DROP+CREATE")$J="DROP DATABASE IF EXISTS $B;\n";$J
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
unconvert_field(array$k,$J){if(preg_match("~binary~",$k["type"]))$J="UNHEX($J)";if($k["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($k["type"]=="vector")$J=(connection()->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geom|point|linestring|polygon~",$k["type"])){$Ai=(min_version(8)?"ST_":"");$J=$Ai."GeomFromText($J, $Ai"."SRID($k[field]))";}return$J;}function
support($xd){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(min_version(8)?'|descidx':'').(min_version('8.0.16','10.2.1')?'|check':'').(min_version(8,99)?'|fast_status':'').')$~',$xd);}function
kill_process($s){return
queries("KILL ".number($s));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return
get_val("SELECT @@max_connections");}function
types($sd=false){return
array();}function
type_values($s){return"";}function
type_definition($s){return
array("kind"=>"","definition"=>"");}function
schemas(){return
array();}function
get_schema(){return"";}function
set_schema($M,$f=null){return
true;}function
schema_comment($M){return"";}function
set_schema_comment($M,$vb){return
true;}}define('Adminer\JUSH',Driver::$jush);define('Adminer\SERVER',"".$_GET[DRIVER]);define('Adminer\DB',"$_GET[db]");define('Adminer\ME',preg_replace('~\?.*~','',relative_uri()).'?'.(sid()?SID.'&':'').($_GET["ext"]?"ext=".url_escape($_GET["ext"]).'&':'').(isset($_GET[DRIVER])?DRIVER."=".url_escape(SERVER).'&':'').(isset($_GET["username"])?"username=".url_escape($_GET["username"]).'&':'').(isset($_GET["db"])?'db='.url_escape(DB).'&'.(isset($_GET["ns"])?"ns=".url_escape($_GET["ns"])."&":""):''));if(isset($_GET["manifest"])){header("Content-Type: application/manifest+json; charset=utf-8");header("Cache-Control: no-cache");echo
json_encode(adminer()->manifest(),64|256);exit;}function
page_header($jl,$j="",$Ta=array(),$kl="",$ah=false,$_c=""){if($ah){header("HTTP/1.1 404 Not Found");$j=($j?:'Not found.');}page_headers();if(is_ajax()&&$j){page_messages($j);exit;}if(!ob_get_level())ob_start('ob_gzhandler',4096);$ll=$jl.($kl!=""?": $kl":"");$ml=strip_tags($ll.(SERVER!=""&&SERVER!="localhost"?h(" - ".SERVER):"")." - ".adminer()->name());echo'<!DOCTYPE html>
<html lang=\'en\' dir=\'ltr\' class=\'ltr nojs\'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>',$ml,'</title>
<link rel="stylesheet" href="',h(preg_replace("~\\?.*~","",ME)."?file=default.css&version=6.2.0+df93d571"),'">
';$Pb=adminer()->css();if(is_int(key($Pb)))$Pb=array_fill_keys($Pb,'light');$oe=in_array('light',$Pb)||in_array('',$Pb);$me=in_array('dark',$Pb)||in_array('',$Pb);$Tb=($oe?($me?null:false):($me?:null));$ng=" media='(prefers-color-scheme: dark)'";if($Tb!==false)echo"<link rel='stylesheet'".($Tb?"":$ng)." href='".h(preg_replace("~\\?.*~","",ME)."?file=dark.css&version=6.2.0+df93d571")."'>\n";echo"<meta name='color-scheme' content='".($Tb===null?"light dark":($Tb?"dark":"light"))."'>\n",script_src(preg_replace("~\\?.*~","",ME)."?file=functions.js&version=6.2.0+df93d571");if(adminer()->head($Tb))echo"<link rel='icon' href='data:image/gif;base64,"."R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n","<link rel='apple-touch-icon' href='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.2.0+df93d571")."'>\n";if(adminer()->manifest())echo"<link rel='manifest' href='".h(preg_replace('~\?.*~','',ME)."?manifest=")."' crossorigin='use-credentials'>\n";foreach($Pb
as$Tl=>$Cg){$b=($Cg=='dark'&&!$Tb?$ng:($Cg=='light'&&$me?" media='(prefers-color-scheme: light)'":""));echo"<link rel='stylesheet'$b href='".h($Tl)."'>\n";}echo"\n<body class='";adminer()->bodyClass();echo"'>\n",script((isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"onload = partial(verifyVersion, '".VERSION."');\n")."
const offlineMessage = '".js_escape('You are offline.')."';
const numberFormat = '".js_escape('#,##0')."';
const numberDigits = '".js_escape('0123456789')."';
const urlSeparators = '".js_escape(ini_get("arg_separator.input"))."';"),"<div id='help' class='jush-".JUSH." jsonly hidden'".on('mouseover','helpKeep').on('mouseout','helpMouseout')."></div>\n","<div id='content'>\n","<span id='menuopen' class='jsonly'".on('click','menuToggle')."><button title='".'Menu'."' class='icon icon-move' aria-expanded='false'></button></span>\n";if($Ta!==null){$z=substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1);echo'<p id="breadcrumb"><a href="'.h($z?:".").'">'.get_driver(DRIVER).'</a> » ';$z=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);$O=adminer()->serverName(SERVER);$O=($O!=""?$O:'Server');if($Ta===false)echo"$O\n";else{echo"<a href='".h($z.(DB!=""&&support("single_db")?"&db=":""))."' accesskey='1' title='Alt+Shift+1'>$O</a> » ";$Gj="";if(is_string($Ta)){$Gj=$Ta;$Ta=array();}if($_GET["ns"]!=""||(DB!=""&&is_array($Ta))){$Zb="$z&db=".url_escape(DB).(support("scheme")?"&ns=":"").(support("single_table")?"&select=":"");echo'<a href="'.h($Zb.($_GET["ns"]==""?$Gj:"")).'">'.h(DB).'</a> » ';}if(is_array($Ta)){if($_GET["ns"]!="")echo'<a href="'.h(substr(ME,0,-1).$Gj).'">'.h($_GET["ns"]).'</a> » ';foreach($Ta
as$w=>$X){$lc=(is_array($X)?$X[1]:h($X));if($lc!="")echo"<a href='".h(ME."$w=").url_escape(is_array($X)?$X[0]:$X)."'>$lc</a> » ";}}echo"$jl\n";}}echo"<h2>$ll$_c</h2>\n","<div id='ajaxstatus' role='status' class='jsonly'></div>\n";restart_session();page_messages($j);adminer()->serviceWorker();$g=&get_session("dbs");if(DB!=""&&$g&&!in_array(DB,$g,true))$g=null;stop_session();define('Adminer\PAGE_HEADER',1);ob_flush();flush();if($ah){page_footer($ah===true?"":$ah);exit;}}function
service_worker(){$ej=has_passwords();$nb=($ej?"navigator.serviceWorker.register('".js_escape(preg_replace('~\?.*~','',ME)."?file=worker.js&version=6.2.0+df93d571")."', {scope: location.pathname}).catch(() => {});":"navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));");echo
script("if (navigator.serviceWorker) {\n\t$nb\n}");}function
has_passwords(){foreach((array)$_SESSION["pwds"]as$Uj){foreach($Uj
as$bm){foreach($bm
as$F){if($F!==null)return
true;}}}return
false;}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-Frame-Options: deny");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");foreach(adminer()->csp(csp())as$Ob){$se=array();foreach($Ob
as$w=>$X)$se[]="$w $X";header("Content-Security-Policy: ".implode("; ",$se));}adminer()->headers();}function
csp(){return
array(array("script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://www.adminer.org","frame-src"=>"https://www.adminer.org","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",),);}function
design_checksums(){$Zl=array();foreach(array_keys(adminer()->css())as$Tl)$Zl[preg_replace('~\?.*~','',$Tl)]=true;$J=array();foreach(array("adminer.css","adminer-dark.css")as$m){if($Zl[$m]&&file_exists($m)){preg_match('~^/\* Adminer design ([-\w]+) \*/~',file_get_contents($m),$A);$J[$m]=array((string)$A[1],Plugins::checksum($m));}}return$J;}function
official_design_checksums(){return
array('adminer-dark/adminer-dark.css'=>'a26bcd7b','brade/adminer.css'=>'be4161f0','bueltge/adminer.css'=>'1a8f00b4','cpanel/adminer.css'=>'59ce604e','dracula/adminer-dark.css'=>'cfaf61dd','esterka/adminer.css'=>'1f805f36','flat/adminer.css'=>'49a61af9','galkaev/adminer-dark.css'=>'16c46f94','haeckel/adminer.css'=>'147a3565','hever/adminer.css'=>'c9d1d11c','konya/adminer.css'=>'2b409696','lavender-light/adminer.css'=>'bf03f5d7','lucas-sandery/adminer.css'=>'6596353','mancave/adminer-dark.css'=>'d9169f12','mvt/adminer.css'=>'ebd3afdc','nette/adminer.css'=>'1b8ac749','ng9/adminer.css'=>'488583cf','nicu/adminer.css'=>'a38b3b6a','pappu687/adminer.css'=>'b58d128c','paranoiq/adminer.css'=>'64d27e5','pepa-linha/adminer.css'=>'baf25f0','pokorny/adminer.css'=>'ee9eea6d','price/adminer.css'=>'81be9a85','rmsoft/adminer.css'=>'6cd4a237','rmsoft_blue-dark/adminer.css'=>'32102a8','rmsoft_blue/adminer.css'=>'7d8d5b18','win98/adminer.css'=>'e82d63c3',);}function
version_iframe(){return(isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"<noscript><iframe sandbox src='https://www.adminer.org/version/?current=".VERSION."&amp;noscript=1'></iframe></noscript>");}function
get_nonce(){static$Zg;if(!$Zg)$Zg=base64_encode(rand_string());return$Zg;}function
page_messages($j){$Sl=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$ug=idx($_SESSION["messages"],$Sl);if($ug){echo"<div class='message'>".implode("</div>\n<div class='message'>",$ug)."</div>".script("messagesPrint();");unset($_SESSION["messages"][$Sl]);}if($j)echo"<div class='error'>$j</div>\n";if(adminer()->error)echo"<div class='error'>".adminer()->error."</div>\n";}function
page_footer($Bg=""){echo"</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";adminer()->navigation($Bg);echo"</div>\n";if($Bg!="auth")echo'<form action="" method="post">
<p class="logout">
<span title="Username">',h($_GET["username"])."\n",'</span>
<input type=\'submit\' name=\'logout\' value=\'Logout\' id=\'logout\'>
',input_token(),'</form>
';echo"</div>\n\n",script("setupSubmitHighlight(document);");}function
int32($Kg){while($Kg>=2147483648)$Kg-=4294967296;while($Kg<=-2147483649)$Kg+=4294967296;return(int)$Kg;}function
long2str(array$W,$nm){$zj='';foreach($W
as$X)$zj
.=pack('V',$X);if($nm)return
substr($zj,0,end($W));return$zj;}function
str2long($zj,$nm){$W=array_values(unpack('V*',str_pad($zj,4*ceil(strlen($zj)/4),"\0")));if($nm)$W[]=strlen($zj);return$W;}function
xxtea_mx($xm,$wm,$Bk,$uf){return
int32((($xm>>5&0x7FFFFFF)^$wm<<2)+(($wm>>3&0x1FFFFFFF)^$xm<<4))^int32(($Bk^$wm)+($uf^$xm));}function
encrypt_string($wk,$w){if($wk=="")return"";$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($wk,true);$Kg=count($W)-1;$xm=$W[$Kg];$wm=$W[0];$Mi=floor(6+52/($Kg+1));$Bk=0;while($Mi-->0){$Bk=int32($Bk+0x9E3779B9);$Ic=$Bk>>2&3;for($Ph=0;$Ph<$Kg;$Ph++){$wm=$W[$Ph+1];$Jg=xxtea_mx($xm,$wm,$Bk,$w[$Ph&3^$Ic]);$xm=int32($W[$Ph]+$Jg);$W[$Ph]=$xm;}$wm=$W[0];$Jg=xxtea_mx($xm,$wm,$Bk,$w[$Ph&3^$Ic]);$xm=int32($W[$Kg]+$Jg);$W[$Kg]=$xm;}return
long2str($W,false);}function
decrypt_string($wk,$w){if($wk=="")return"";if(!$w)return
false;$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($wk,false);$Kg=count($W)-1;$xm=$W[$Kg];$wm=$W[0];$Mi=floor(6+52/($Kg+1));$Bk=int32($Mi*0x9E3779B9);while($Bk){$Ic=$Bk>>2&3;for($Ph=$Kg;$Ph>0;$Ph--){$xm=$W[$Ph-1];$Jg=xxtea_mx($xm,$wm,$Bk,$w[$Ph&3^$Ic]);$wm=int32($W[$Ph]-$Jg);$W[$Ph]=$wm;}$xm=$W[$Kg];$Jg=xxtea_mx($xm,$wm,$Bk,$w[$Ph&3^$Ic]);$wm=int32($W[0]-$Jg);$W[0]=$wm;$Bk=int32($Bk-0x9E3779B9);}return
long2str($W,true);}$ni=array();if($_COOKIE["adminer_permanent"]){foreach(explode(" ",$_COOKIE["adminer_permanent"])as$X){list($w)=explode(":",$X);$ni[$w]=$X;}}function
add_invalid_login(){$La=get_temp_dir()."/adminer-invalid";foreach(glob("$La*")?:array($La)as$m){$o=file_open_lock($m);if($o)break;}if(!$o)$o=file_open_lock("$La-".rand_string());if(!$o)return;$hf=json_decode(stream_get_contents($o),true);$gl=time();if($hf){foreach($hf
as$if=>$X){if($X[0]<$gl)unset($hf[$if]);}}$ff=&$hf[adminer()->bruteForceKey()];if(!$ff)$ff=array($gl+30*60,0);$ff[1]++;file_write_unlock($o,json_encode($hf));}function
check_invalid_login(array&$ni){$hf=array();foreach(glob(get_temp_dir()."/adminer-invalid*")as$m){$o=file_open_lock($m);if($o){$hf=json_decode(stream_get_contents($o),true);file_unlock($o);break;}}$w=adminer()->bruteForceKey();$ff=idx($hf,$w,array());$Yg=($ff[1]>29?$ff[0]-time():0);if($Yg>0){$j=lang_format(array('Too many unsuccessful logins, try again in %d minute.','Too many unsuccessful logins, try again in %d minutes.'),ceil($Yg/60));if($_SERVER["HTTP_X_FORWARDED_FOR"]!=""&&$w==$_SERVER["REMOTE_ADDR"])$j
.='<br>'.sprintf('Use the %s <a%s>plugin</a> if Adminer runs behind a reverse proxy.','<b>login-reverse-proxy</b>'," href='https://www.adminer.org/plugins/?version=".VERSION."'".target_blank());auth_error($j,$ni,false);}}function
password_required(){static$J;if($J===null){$J=(bool)get_session("password_required");if(!$J){$Nb=adminer()->credentials();$J=!is_object(Driver::connect($Nb[0],$Nb[1],""));if($J)set_session("password_required",true);}}return$J;}function
require_password_link($F){$Gg="<a href='https://www.adminer.org/password/'".target_blank().">".'More options'."</a>";if(!function_exists('password_hash'))return" $Gg";$qi=($F!==null?$F:base64_encode(substr(pack("H*",rand_string()),0,12)));$re=password_hash($qi,PASSWORD_DEFAULT);$m="adminer-plugins.php";$md=file_exists("adminer-plugins.php");if($md)$df=($F!==null?sprintf('Add this line to %s to require the entered password:',"<b>$m</b>"):sprintf('Add this line to %s to require the password %s:',"<b>$m</b>","<b>$qi</b>"));else{$m="<button name='password_less' value='".h($re)."' class='link'>$m</button>";$df=($F!==null?sprintf('Save %s next to Adminer to require the entered password:',$m):sprintf('Save %s next to Adminer to require the password %s:',$m,"<b>$qi</b>"));}$Of="\t<a>new</a> Adminer\\Password(<span class='jush-apo'>'".h($re)."'</span>),";$J="<p>$df
<pre><code class='jush'>".($md?$Of:"&lt;?php\n<a>return</a> <a>array</a>(\n$Of\n);")."</code></pre>
<p>$Gg
";return" <a href='#password-less' class='toggle'>".'Require a password.'."</a>
<div id='password-less' class='hidden'>".($md?$J:"<form action='' method='post'>\n".$J.input_token()."</form>")."</div>";}if(preg_match('~^[-\w$./]+$~',$_POST["password_less"])&&verify_token()){header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=adminer-plugins.php");echo"<?php\nreturn array(\n\tnew Adminer\\Password('$_POST[password_less]'),\n);\n";exit;}$Ca=$_POST["auth"];if($Ca&&(!adminer()->verifyLoginToken()||verify_token())){session_regenerate_id();$im=$Ca["driver"];$O=$Ca["server"];$V=$Ca["username"];$F=(string)$Ca["password"];$h=$Ca["db"];set_password($im,$O,$V,$F);$_SESSION["db"][$im][$O][$V][$h]=true;if($Ca["permanent"]){$w=implode("-",array_map('base64_encode',array($im,$O,$V,$h)));$Gi=adminer()->permanentLogin(true);$ni[$w]="$w:".base64_encode($Gi?encrypt_string($F,$Gi):"");cookie("adminer_permanent",implode(" ",$ni));}if(!array_diff(array_keys($_POST),array("auth","token"))||$im!=DRIVER||$O!=SERVER||$V!==$_GET["username"]||$h!=DB)redirect(auth_url($im,$O,$V,$h));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){Driver::disconnect();foreach(array("pwds","db","dbs","queries")as$w)set_session($w,null);unset_permanent($ni);redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1),'Logout successful.'.' '.str_replace('<a ','<a'.target_blank().' ','Thanks for using Adminer. Consider <a href="https://www.adminer.org/en/donation/">donating</a>.'));}elseif($ni&&!$_SESSION["pwds"]){session_regenerate_id();$Gi=adminer()->permanentLogin();foreach($ni
as$w=>$X){list(,$lb)=explode(":",$X);list($im,$O,$V,$h)=array_map('base64_decode',explode("-",$w));set_password($im,$O,$V,decrypt_string(base64_decode($lb),$Gi));$_SESSION["db"][$im][$O][$V][$h]=true;}}function
unset_permanent(array&$ni){foreach($ni
as$w=>$X){list($im,$O,$V,$h)=array_map('base64_decode',explode("-",$w));if($im==DRIVER&&$O==SERVER&&$V==$_GET["username"]&&$h==DB)unset($ni[$w]);}cookie("adminer_permanent",implode(" ",$ni));}function
auth_error($j,array&$ni,$gf=true){$Vj=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$Vj]||$_GET[$Vj])&&!$_SESSION["token"])$j='Session expired. Please log in again.';elseif($gf&&($F=get_password())!==null){restart_session();add_invalid_login();if($F===false)$j
.=($j?'<br>':'').sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.',target_blank(),'<code>permanentLogin()</code>');set_password(DRIVER,SERVER,$_GET["username"],null);unset_permanent($ni);}}if(!$_COOKIE[$Vj]&&$_GET[$Vj]&&ini_bool("session.use_only_cookies"))$j='Session support must be enabled.';$Th=session_get_cookie_params();cookie("adminer_key",($_COOKIE["adminer_key"]?:rand_string()),$Th["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header('Login',$j,null);echo"<form action='' method='post'>\n","<div>";if(hidden_fields($_POST,array("auth","token")))echo"<p class='message'>".'The action will be performed after successful login with the same credentials.'."\n";echo
input_token(),"</div>\n";adminer()->loginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!class_exists('Adminer\Db')){unset($_SESSION["pwds"][DRIVER]);unset_permanent($ni);page_header('No extension',sprintf('None of the supported PHP extensions (%s) are available.',implode(", ",Driver::$extensions)),false);page_footer("auth");exit;}$e='';if(isset($_GET["username"])&&is_string(get_password())){check_invalid_login($ni);$Nb=adminer()->credentials();$e=Driver::connect($Nb[0],$Nb[1],$Nb[2]);if(is_object($e)){Db::$instance=$e;Driver::$instance=new
Driver($e);if($e->flavor)save_settings(array("vendor-".DRIVER."-".SERVER=>get_driver(DRIVER)));}}$Uf=null;if(!is_object($e)||($Uf=adminer()->login($_GET["username"],get_password()))!==true){$j=(is_string($e)?nl_br(h($e)):(is_string($Uf)?$Uf:'Invalid credentials.')).(preg_match('~^ | $~',get_password())?'<br>'.'There is a space in the entered password, which might be the cause.':'');auth_error($j,$ni);}if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){page_header('Logout','Invalid CSRF token. Submit the form again.');page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($Ca&&$_POST["token"])$_POST["token"]=get_token();$j='';if($_POST){if(!verify_token()){header("HTTP/1.1 403 Forbidden");$j='Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.';}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){header("HTTP/1.1 413 Content Too Large");$j=sprintf('The POST data is too large. Reduce the data or increase the %s configuration directive.',"<b>post_max_size</b>");if(isset($_GET["sql"]))$j
.=' '.'You can upload a large SQL file via FTP and import it from the server.';}function
print_select_result($I,$f=null,array$Fh=array(),&$y=0,&$Jc=false){$Qf=array();$v=array();$d=array();$T=array();$Ei=array();$Nc=array();$El=array();$J=array();$Dg=$Jc;$Jc=false;for($r=0;(!$y||$r<$y)&&is_array($K=$I->fetch_row());$r++){if(!$r){echo"<div class='scrollable'>\n","<table class='nowrap odds'".($Dg?on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown'):"").">\n","<thead><tr>";for($rf=0;$rf<count($K);$rf++){$k=$I->fetch_field();$B=$k->name;$R=(isset($k->table)?$k->table:"");$Eh=(isset($k->orgtable)?$k->orgtable:"");$Dh=(isset($k->orgname)?$k->orgname:$B);$Dl=driver()->typeName($k);if($Fh&&JUSH=="sql")$Qf[$rf]=($B=="table"?"table=":($B=="possible_keys"?"indexes=":null));elseif($Eh!=""){$pa=($R!=""?$R:$Eh);if($R!="")$J[$R]=$Eh;if(!isset($v[$pa])){if(!isset($Ei[$Eh])){$Ei[$Eh]=array();foreach(indexes($Eh,$f)as$u){if($u["type"]=="PRIMARY"){$Ei[$Eh]=array_flip($u["columns"]);break;}}}$T[$pa]=$Eh;$v[$pa]=$Ei[$Eh];$d[$pa]=$Ei[$Eh];}if(isset($d[$pa][$Dh])){unset($d[$pa][$Dh]);$v[$pa][$Dh]=$rf;$Qf[$rf]=$pa;}elseif($Dg&&isset($k->orgname)&&$k->db==DB&&!is_blob(array("type"=>$Dl)))$Nc[$rf]=array($pa,$Dh,preg_match('~text|json|lob~',$Dl));}$El[$rf]=$Dl;echo"<th title='".h(trim(($Eh!=""?"$Eh.$Dh":($k->name!=$Dh?$Dh:""))." ".$Dl))."'>".h($B).($Fh?doc_link(array('sql'=>"explain-output.html#explain_".strtolower($B),'mariadb'=>"explain/#the-columns-in-explain-select",)):"");}foreach($Nc
as$rf=>$Ya){if($d[$Ya[0]])unset($Nc[$rf]);}echo"<tbody>\n";}$Ie=array();foreach($v
as$pa=>$u){if($u&&!$d[$pa]){$t="";foreach($u
as$ob=>$rf){if($K[$rf]===null){$t=null;break;}$t
.="&where[".url_escape(bracket_escape($ob))."]=".url_escape($K[$rf]);}$Ie[$pa]=$t;}}echo"<tr>";foreach($K
as$w=>$X){$z="";if(isset($Qf[$w])){if($Fh&&JUSH=="sql"){$R=$K[array_search("table=",$Qf)];$z=ME.$Qf[$w].url_escape($Fh[$R]!=""?$Fh[$R]:$R);}elseif(idx($Ie,$Qf[$w])!==null)$z=ME."edit=".url_escape($T[$Qf[$w]]).$Ie[$Qf[$w]];}$Ee=select_value($X,$z,array('type'=>(preg_match('~binary~',$El[$w])?'blob':$El[$w])),null);$b="";$Ya=idx($Nc,$w);if($Ya&&idx($Ie,$Ya[0])!==null&&is_utf8($X)){$Jc=true;$b=" data-name='".h("val[".bracket_escape($T[$Ya[0]])."][".bracket_escape(substr($Ie[$Ya[0]],1))."][".bracket_escape($Ya[1])."]")."' data-text='".($Ya[2]?1:0)."'".(html_entity_decode(strip_tags($Ee),ENT_QUOTES,"UTF-8")!==$X?" data-value='".h($X)."'":"");}echo"<td".(preg_match(number_type(),$El[$w])?" class='number'":"")."$b>$Ee";}}$y=$r;echo($r?"</table>\n</div>":"<p class='message'>".'No rows.')."\n";return$J;}function
textarea($B,$Y,$L=10,$sb=80,$tf=JUSH,$bd=0){echo"<textarea name='".h($B)."' rows='$L' cols='$sb' class='sqlarea jush-".h($tf)."' spellcheck='false' wrap='off'".($bd?" data-error-line='$bd'":"").">\n";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($b,array$C,$Y="",$oi=""){if($C&&$Y!=""&&!isset($C[$Y]))$C=array($Y=>$Y)+$C;$Wk=($C?"select":"input");return"<$Wk$b".($C?"><option value=''>$oi".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$oi'>");}function
json_row($w,$X=null,$ed=true){static$Hd=true;if($Hd)echo"{";if($w!=""){echo($Hd?"":",")."\n\t\"".addcslashes($w,"\r\n\t\"\\/").'": '.($X!==null?($ed?'"'.addcslashes($X,"\r\n\"\\/").'"':$X):'null');$Hd=false;}else{echo"\n}\n";$Hd=true;}}function
flat_collations(){$rb=collations();return(is_array(reset($rb))?call_user_func_array('array_merge',array_values($rb)):$rb);}function
edit_type($w,array$k,array$rb,array$Od=array(),array$ud=array()){$U=(string)$k["type"];echo"<td><select name='".h($w)."[type]' class='type' aria-labelledby='label-type'".on_help_value().">";if($U&&!array_key_exists($U,driver()->types())&&!isset($Od[$U])&&!in_array($U,$ud))$ud[]=$U;$xk=driver()->structuredTypes();if($Od)$xk['Foreign keys']=$Od;echo
optionlist(array_merge($ud,$xk),$U),"</select><td>","<input name='".h($w)."[length]' value='".h($k["length"])."' size='3'".(!$k["length"]&&preg_match('~var(char|binary)$~',$U)?" class='required'":"")." aria-labelledby='label-length'>","<td class='options'>",($rb?"<input list='collations' name='".h($w)."[collation]'".option_types($U,'('.text_type().')$')." value='".h($k["collation"])."' placeholder='(".'collation'.")'>":''),(driver()->unsigned?"<select name='".h($w)."[unsigned]'".option_types($U,'^$|'.number_type()).'><option>'.optionlist(driver()->unsigned,$k["unsigned"]).'</select>':''),(isset($k['on_update'])?"<select name='".h($w)."[on_update]'".option_types($U,'timestamp|datetime').'>'.optionlist(array(""=>"(".'ON UPDATE'.")","CURRENT_TIMESTAMP"),(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"CURRENT_TIMESTAMP":$k["on_update"])).'</select>':''),($Od?"<select name='".h($w)."[on_delete]'".option_types($U,'`')."><option value=''>(".'ON DELETE'.")".optionlist(explode("|",driver()->onActions),$k["on_delete"])."</select> ":" ");}function
option_types($U,$El){return" data-types='".h($El)."'".(preg_match("~$El~",$U)?"":" class='hidden'");}function
process_length($x){if(JUSH=="mssql"&&preg_match('~^\s*\(?\s*max\s*\)?\s*$~i',$x))return"(max)";if(JUSH=="oracle"&&preg_match('~^\s*\(?\s*(\d+)\s+(CHAR|BYTE)\s*\)?\s*$~i',$x,$A))return"($A[1] ".strtoupper($A[2]).")";$Yc=driver()->enumLength;return(preg_match("~^\\s*\\(?\\s*$Yc(?:\\s*,\\s*$Yc)*+\\s*\\)?\\s*\$~",$x)&&preg_match_all("~$Yc~",$x,$Yf)?"(".implode(",",$Yf[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$x)));}function
process_in($X){$X=preg_replace('~^\s*\(\s*(.*?)\s*\)\s*$~s','\1',$X);$Si="'(?:[^']|'')*+'";$qf=(preg_match("~^\\s*$Si(?:\\s*,\\s*$Si)*+\\s*\$~",$X)&&preg_match_all("~$Si~",$X,$Yf)?array_map('Adminer\idf_unescape',$Yf[0]):array_map('trim',explode(",",$X)));return"(".implode(", ",array_map('Adminer\q',$qf)).")";}function
process_between($X){$Si="('(?:[^']|'')*+')";$Ui=(preg_match("~^\\s*$Si\\s+AND\\s+$Si\\s*\$~i",$X,$A)?array(idf_unescape($A[1]),idf_unescape($A[2])):preg_split('~\s+AND\s+~i',trim($X)));return(count($Ui)==2?q($Ui[0])." AND ".q($Ui[1]):"NULL AND NULL");}function
process_type(array$k,$pb="COLLATE"){return" ".(is_user_type($k["type"])?idf_escape($k["type"]):$k["type"]).process_length($k["length"]).(preg_match(number_type(),$k["type"])&&in_array($k["unsigned"],driver()->unsigned)?" $k[unsigned]":"").(preg_match('~'.text_type().'~',$k["type"])&&$k["collation"]?" $pb ".(JUSH=="mssql"&&preg_match('~^\w+$~',$k["collation"])?$k["collation"]:q($k["collation"])):"");}function
process_field(array$k,array$Bl){return
array(idf_escape(trim($k["field"])),process_type($Bl),($k["null"]?" NULL":" NOT NULL"),default_value($k),(preg_match('~timestamp|datetime~',$k["type"])&&$k["on_update"]?" ON UPDATE $k[on_update]".process_length($k["length"]):""),(support("comment")&&$k["comment"]!=""?" COMMENT ".q($k["comment"]):""),($k["auto_increment"]?auto_increment():null),);}function
default_value(array$k){if($k["default"]===null)return"";$i=str_replace("\r","",$k["default"]);$Zd=$k["generated"];$Q=!preg_match('~]$~',$k["length"])&&(preg_match('~char|binary|text|clob|json|enum|set|String~',$k["type"])||driver()->enumLength($k));$Uh=(JUSH=="sqlite"||(JUSH=="sql"&&$k["default_sql"]&&!preg_match("~^(current_timestamp(\\(\\d*\\))?|[bx]'[\\da-f]*')$~i",$i)));return(in_array($Zd,driver()->generated)?(JUSH=="mssql"?" AS ($i)".($Zd=="VIRTUAL"?"":" $Zd"):" GENERATED ALWAYS AS ($i) $Zd"):(preg_match('~^GENERATED ~i',$i)?" $i":" DEFAULT ".(!$k["default_sql"]&&($Q||preg_match('~^(?![a-z])~i',$i))?(JUSH=="sql"&&preg_match('~text|json~',$k["type"])?"(".q($i).")":q($i)):($Uh?"($i)":$i))));}function
edit_fields(array$l,array$rb,$U="TABLE",array$Od=array()){$l=array_values($l);$ec=(($_POST?$_POST["defaults"]:get_setting("defaults"))?"":" class='hidden'");$wb=(($_POST?$_POST["comments"]:get_setting("comments"))?"":" class='hidden'");echo"<thead><tr>\n",($U=="PROCEDURE"?"<td>":""),"<th id='label-name'>".($U=="TABLE"?'Column name':'Parameter name'),"<th id='label-type'>".'Type'."<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>".script("qs('#enum-edit').onblur = editingLengthBlur;"),"<th id='label-length'>".'Length',"<th>".'Options';if($U=="TABLE")echo"<th id='label-null'>NULL\n","<th><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='".'Auto Increment'."'>A_I</abbr>",doc_link(array('sql'=>"example-auto-increment.html",'mariadb'=>"auto_increment/",)),"<th id='label-default'$ec>".'Default value',(support("comment")?"<th id='label-comment'$wb>".'Comment':"");$Df=!support("move_col");echo"<td>".icon("plus","add[".($Df?count($l):0)."]","+",'Add next',($Df?on('click','editingAddLastRow'):"")),"<tbody".on('click','editingClick').on('input','editingInput').on('keydown','editingKeydown').">\n";foreach($l
as$r=>$k){$r++;$Gh=$k[($_POST?"orig":"field")];$sc=(isset($_POST["add"][$r-1])||(isset($k["field"])&&!idx($_POST["drop_col"],$r)))&&(support("drop_col")||$Gh=="");echo"<tr".($sc?"":" hidden").">\n",($U=="PROCEDURE"?"<td>".html_select("fields[$r][inout]",explode("|",driver()->inout),$k["inout"]):"")."<th>",(support("move_col")?icon("move","","↕",'Move')." ":"");if($sc)echo"<input name='fields[$r][field]' value='".h($k["field"])."' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'".(isset($_POST["add"][$r-1])?" autofocus":"").">";echo
input_hidden("fields[$r][orig]",$Gh);edit_type("fields[$r]",$k,$rb,$Od);if($U=="TABLE"){echo"<td><label class='block'>".checkbox("fields[$r][null]",1,$k["null"],"","","","label-null")."</label>","<td><label class='block'><input type='radio' name='auto_increment_col' value='$r'".($k["auto_increment"]?" checked":"")." aria-labelledby='label-ai'></label>","<td$ec>".html_select("fields[$r][generated]",array_merge(array("","DEFAULT","DEFAULT SQL"),driver()->generated),$k["generated"],"","label-default")." ";$b=" name='fields[$r][default]' aria-labelledby='label-default'";$Y=h($k["default"]);echo(preg_match('~\n~',$k["default"])?"<textarea$b rows='2' cols='30' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");if(support("comment")){$b=" name='fields[$r][comment]' data-maxlength='".(min_version(5.5)?1024:255)."' aria-labelledby='label-comment'";echo"<td$wb>".adminer()->commentInput('COLUMN',$b,$k["comment"]);}}echo"<td>",(support("move_col")?icon("plus","add[$r]","+",'Add next')." ":""),($Gh==""||support("drop_col")?icon("cross","drop_col[$r]","x",'Remove'):"");}}function
process_fields(array&$l){if($_POST["add"]){$l=array_values($l);array_splice($l,key($_POST["add"]),0,array(array()));}return$_POST["add"]||$_POST["drop_col"];}function
drop_create($Ec,$Kb,$Fc,$cl,$Gc,$_,$tg,$rg,$sg,$oh,$Ug){if($_POST["drop"])query_redirect($Ec,$_,$tg);elseif($oh=="")query_redirect($Kb,$_,$sg);elseif(support("transaction_ddl")){driver()->begin();queries_redirect($_,$rg,queries($Ec)&&queries($Kb)&&driver()->commit());driver()->rollback();}elseif($oh!=$Ug){$Mb=queries($Kb);queries_redirect($_,$rg,$Mb&&queries($Ec));if($Mb&&$Fc)queries($Fc);}else
queries_redirect($_,$rg,queries($cl)&&queries($Gc)&&queries($Ec)&&queries($Kb));}function
create_trigger($rh,array$K){$il=" $K[Timing] $K[Event]".(preg_match('~ OF~',$K["Event"])?" $K[Of]":"");return"CREATE TRIGGER ".idf_escape($K["Trigger"]).(JUSH=="mssql"?$rh.$il:$il.$rh).preg_replace('~[\s;]+$~',''," $K[Type]\n$K[Statement]").";";}function
q_dollar($Q){$kc='$$';while(strpos($Q.$kc,$kc)!=strlen($Q))$kc='$_'.substr($kc,1);return$kc.$Q.$kc;}function
routine_collate($qb){static$db=array();if($qb&&!$db){foreach(collations()as$cb=>$fm){foreach((array)$fm
as$X)$db[$X]=$cb;}}return($db[$qb]?"CHARACTER SET ".q($db[$qb])." ":"")."COLLATE";}function
create_routine($rj,array$K){$P=array();$l=$K["fields"];ksort($l);foreach($l
as$k){if($k["field"]!=""){$Ye=(preg_match("~^(".driver()->inout.")\$~",$k["inout"])?$k["inout"]:"");$P[]="\n  ".(JUSH=="mssql"?"@$k[field]".process_type($k).($Ye?" $Ye":""):($Ye?"$Ye ":"").idf_escape($k["field"]).process_type($k,routine_collate($k["collation"])));}}$hc="";$C=array();foreach(routine_options($rj)as$w=>$gm){$Y=idx($K["options"],$w,"");if($w=="DEFINER")$hc=($Y?" $w=".implode("@",array_map('Adminer\q',explode("@",$Y,2))):"");elseif(!$gm){if($Y!="")$C[]="$w ".q($Y);}elseif($Y!=reset($gm)&&in_array($Y,$gm))$C[]=$Y;}$Bf=$K["language"];$ic=preg_replace('~[\s;]+$~','',$K["definition"]);$Ac=(JUSH=="pgsql"||($Bf&&$Bf!="sql"));$Sh=($P?implode(",",$P)."\n":"");return"CREATE$hc $rj ".table(trim($K["name"])).(JUSH=="mssql"&&$rj=="PROCEDURE"?rtrim($Sh):" ($Sh)").($rj=="FUNCTION"?"\nRETURNS".process_type($K["returns"],routine_collate($K["returns"]["collation"])):"").($Bf?" LANGUAGE $Bf":"").($C?"\n".implode(" ",$C):"").($Ac?" AS ".q_dollar("\n".trim($ic)."\n"):(JUSH=="mssql"?"\nAS":"")."\n$ic;");}function
remove_definer($H){$hc=implode("@",array_map('Adminer\idf_escape',explode("@",logged_user(),2)));return
preg_replace('(^([A-Z =]+) DEFINER='.preg_quote($hc).')','\1',$H);}function
object_name($U,$R,array$d){return
str_replace(array("{table}","{columns}"),array($R,implode("_",$d)),adminer()->namePattern($U));}function
format_foreign_key(array$n,$B=""){$h=$n["db"];$bh=$n["ns"];return($B!=""?" CONSTRAINT ".idf_escape($B):"")." FOREIGN KEY (".implode(", ",array_map('Adminer\idf_escape',$n["source"])).") REFERENCES ".($h!=""&&$h!=$_GET["db"]?idf_escape($h).".":"").($bh!=""&&$bh!=$_GET["ns"]?idf_escape($bh).".":"").idf_escape($n["table"])." (".implode(", ",array_map('Adminer\idf_escape',$n["target"])).")".(preg_match("~^(".driver()->onActions.")\$~",$n["on_delete"])?" ON DELETE $n[on_delete]":"").(preg_match("~^(".driver()->onActions.")\$~",$n["on_update"])?" ON UPDATE $n[on_update]":"").($n["deferrable"]?" $n[deferrable]":"");}function
tar_file($m,$nl){$J=pack("a100a8a8a8a12a12",$m,644,0,0,decoct($nl->size),decoct(time()));$ib=8*32;for($r=0;$r<strlen($J);$r++)$ib+=ord($J[$r]);$J
.=sprintf("%06o",$ib)."\0 ";echo$J,str_repeat("\0",512-strlen($J));$nl->send();echo
str_repeat("\0",511-($nl->size+511)%512);}function
doc_version(){$Tj=connection()->server_info;if(JUSH=='oracle'){preg_match('~(?:.* |^)(\d+)\.\d+\.\d+\.\d+\.\d+~s',$Tj,$A);return($A[1]>=18?$A[1]:"19");}$dj=(JUSH=='sql'||connection()->flavor=='cockroach'?'~^\d+\.\d+~':'~^\d\.?\d~');$jm=(preg_match($dj,$Tj,$A)?$A[0]:"");if(JUSH=='mssql')return($jm>=15?"sql-server-ver$jm":($jm==12?"azuresqldb-current":"sql-server-2017"));return$jm;}function
doc_link(array$ji,$dl="📖"){$jm=doc_version();$Ul=array('sql'=>"https://dev.mysql.com/doc/refman/$jm/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(connection()->flavor=='cockroach'?"current":$jm)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://docs.oracle.com/en/database/oracle/oracle-database/$jm/",);if(connection()->flavor=='maria'){$Ul['sql']="https://mariadb.com/kb/en/";$ji['sql']=($ji['mariadb']?:str_replace(".html","/",$ji['sql']));}if(connection()->flavor=='cockroach'&&$ji['cockroach']){$Ul['pgsql']="https://docs.cockroachlabs.com/docs/v$jm/";$ji['pgsql']=$ji['cockroach'];}return($ji[JUSH]?" <a href='".h($Ul[JUSH].$ji[JUSH].(JUSH=='mssql'?"?view=$jm":""))."'".target_blank()." class='doc' title='".'Documentation'."'>$dl</a>":"");}function
db_size($h){if(!connection()->select_db($h))return"?";$J=0;foreach(table_status()as$S)$J+=$S["Data_length"]+$S["Index_length"];return
adminer()->formatSizeValue($J);}function
set_utf8mb4($Kb){static$P=false;if(!$P&&preg_match('~\butf8mb4~i',$Kb)){$P=true;echo"SET NAMES ".charset(connection()).";\n\n";}}if(DB==""&&isset($_GET["ns"]))redirect(remove_from_uri('ns'));if(!(DB!=""?connection()->select_db(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!="")page_header('Database'.": ".h(DB),adminer()->error(),true,"","db");else{if(!isset($_GET["db"])&&support("single_db")){$g=adminer()->databases();if($g)redirect(ME."db=".url_escape($g[0]));}if($_POST["db"]&&!$j)queries_redirect(substr(ME,0,-1),'Databases have been dropped.',drop_databases($_POST["db"]));page_header('Select database',$j,false);echo"<p class='links'>\n";foreach(array('database'=>'Create database','privileges'=>'Privileges','processlist'=>'Process list','variables'=>'Variables','status'=>'Status',)as$w=>$X){if(support($w))echo"<a href='".h(ME)."$w='>$X</a>\n";}echo"<p>".sprintf('%s version: %s through PHP extension %s',get_driver(DRIVER),"<b>".h(connection()->server_info)."</b>","<b>".connection()->extension."</b>")."\n","<p>".sprintf('Logged in as: %s',"<b>".h(logged_user())."</b>")."\n";$g=adminer()->databases();if($g){$Cj=support("scheme");$rb=collations();echo"<form action='' method='post'>\n","<table class='checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n","<thead><tr>".(support("database")?"<td class='hover'>":"")."<th".(JUSH!='mssql'?" aria-sort='ascending'":"").">".'Database'.(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".'Refresh'."</a>":"")."<th>".'Collation'."<th>".'Tables'."<th>".'Size'." - <a href='".h(ME)."dbsize=1'".on('click','ajaxSetHtml',ME."script=connect").">".'Compute'."</a>"."<tbody>\n";$g=($_GET["dbsize"]?count_tables($g):array_flip($g));foreach($g
as$h=>$T){$qj=h(preg_replace('~&db=[^&]*~','',ME))."db=".url_escape($h);$s=h("Db-".$h);echo"<tr>".(support("database")?"<td class='hover'>".checkbox("db[]",$h,in_array($h,(array)$_POST["db"]),"","","",$s):""),"<th><a href='$qj' id='$s'>".h($h)."</a>";$qb=h(db_collation($h,$rb));echo"<td>".(support("database")?"<a href='$qj".($Cj?"&amp;ns=":"")."&amp;database=' title='".'Alter database'."'>$qb</a>":$qb),"<td align='right'><a href='$qj&amp;schema=' id='tables-".h($h)."' title='".'Database schema'."'>".($_GET["dbsize"]?format_number($T):"?")."</a>","<td align='right' id='size-".h($h)."'>".($_GET["dbsize"]?db_size($h):"?"),"\n";}echo"</table>\n",(support("database")?"<div class='footer'><div>\n"."<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>\n"."<input type='hidden' name='all' value=''".on('click','countDbs').">\n"."<input type='submit' name='drop' value='".'Drop'."'".confirm().">\n"."</div></fieldset>\n"."</div></div>\n":""),input_token(),"</form>\n",script("tableCheck();");}$ja=adminer();$si=($ja
instanceof
Plugins?$ja->plugins:array());$Dc=($ja
instanceof
Plugins?$ja->drivers:array());$pc=design_checksums();if($si||$Dc||$pc){$jb=($ja
instanceof
Plugins?$ja->checksums():array());$hh=Plugins::officialChecksums();$Pl=function($Tl){return" (<a href='$Tl'".target_blank()." class='update'>".VERSION."</a>)";};$ri=function($Bd)use($jb,$hh,$Pl){return($jb[$Bd]&&$hh[$Bd]&&$jb[$Bd]!==$hh[$Bd]?$Pl("https://www.adminer.org/plugins/?version=".VERSION):"");};echo"<div class='plugins'>\n","<h3>".'Loaded plugins'."</h3>\n<ul>\n";foreach($si
as$pi){$bj=new
\ReflectionObject($pi);$mc=Plugins::describe($pi);echo"<li><b>".get_class($pi)."</b>".($mc!=""?": $mc":"").$ri(basename((string)$bj->getFileName(),'.php'))."\n";}foreach($Dc
as$s=>$B)echo"<li><b>".h($s)."</b>: ".h($B).$ri(basename((string)$ja->driverFiles[$s],'.php'))."\n";if($pc){$jh=official_design_checksums();foreach($pc
as$m=>$oc){list($B,$ib)=$oc;$ih=$jh["$B/$m"];echo"<li><b>".h($m)."</b>".h($B?": $B":"").($ih&&$ih!==$ib?$Pl("https://www.adminer.org/?version=".VERSION."#extras"):"")."\n";}}echo"</ul>\n";adminer()->pluginsLinks();echo"</div>\n";}}page_footer("db");exit;}adminer()->afterConnect();class
TmpFile{private$handler;var$size=0;function
__construct(){$this->handler=tmpfile();}function
write($Db){$this->size+=strlen($Db);fwrite($this->handler,$Db);}function
send(){fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}if($_GET["select"]!=""&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$l=fields($a);header("Content-Type: application/octet-stream");$gm=array_merge((array)$_GET["where"],(array)$_GET["val"]);header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$gm)).".".friendly_url($_GET["field"]));$N=array(idf_escape($_GET["field"]));$I=driver()->select($a,$N,array(where($_GET,$l)),$N);$K=($I?$I->fetch_row():array());echo
driver()->value($K[0],$l[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$l=fields($a);if(!$l)$j=adminer()->error();$S=table_status1($a);$B=adminer()->tableName($S);$j=$j?:h($S["Error"]);page_header(($l&&is_view($S)?$S['Engine']=='materialized view'?'Materialized view':'View':'Table').": ".($B!=""?$B:h($a)),$j,array(),"",!$l,($l?doc_link(array(JUSH=>driver()->tableHelp($a,is_view($S)))):""));$pj=array();foreach($l
as$w=>$k)$pj+=$k["privileges"];adminer()->selectLinks($S,(isset($pj["insert"])||!support("table")?"":null));$vb=$S["Comment"];if($vb!="")echo"<p class='nowrap'>".'Comment'.": ".adminer()->commentValue('TABLE',$vb)."\n";if($l)adminer()->tableStructurePrint($l,$S);function
tables_links(array$T){echo"<ul>\n";foreach($T
as$K){$z=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($K["ns"]),ME);echo"<li><a href='".h($z."table=".url_escape($K["table"]))."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>";}echo"</ul>\n";}$We=driver()->inheritsFrom($a);if($We){echo"<h3>".'Inherits from'."</h3>\n";tables_links($We);}if(support("indexes")&&driver()->supportsIndex($S)){echo"<div>\n","<h3 id='indexes'>".'Indexes'."</h3>\n";$v=indexes($a);if($v)adminer()->tableIndexesPrint($v,$S);if(driver()->supportsAlterIndex($S))echo'<p class="links hover"><a href="'.h(ME).'indexes='.url_escape($a).'">'.'Alter indexes'."</a>\n";echo"</div>\n";}if(!is_view($S)&&driver()->supportsAlterTable($S)){if(fk_support($S)){echo"<div>\n","<h3 id='foreign-keys'>".'Foreign keys'."</h3>\n";$Od=foreign_keys($a);$ua=function_exists('Adminer\alter_table');if($Od){echo"<table>\n","<thead><tr><th>".'Source'."<th>".'Target'."<th>".'ON DELETE'."<th>".'ON UPDATE'.($ua?"<td class='hover'>":"")."<tbody>\n";foreach($Od
as$B=>$n){echo"<tr".(!is_int($B)?" title='".h($B)."'":"").">","<th><i>".implode("</i>, <i>",array_map('Adminer\h',$n["source"]))."</i>";$z=($n["db"]!=""?preg_replace('~&db=[^&]*~',"&db=".url_escape($n["db"]),ME):($n["ns"]!=""?preg_replace('~&ns=[^&]*~',"&ns=".url_escape($n["ns"]),ME):ME));echo"<td><a href='".h($z."table=".url_escape($n["table"]))."'>".($n["db"]!=""&&$n["db"]!=DB?"<b>".h($n["db"])."</b>.":"").($n["ns"]!=""&&$n["ns"]!=$_GET["ns"]?"<b>".h($n["ns"])."</b>.":"").h($n["table"])."</a>","(<i>".implode("</i>, <i>",array_map('Adminer\h',$n["target"]))."</i>)","<td>".h($n["on_delete"]),"<td>".h($n["on_update"]),($ua?'<td class="hover"><a href="'.h(ME.'foreign='.url_escape($a).'&name='.url_escape($B)).'">'.'Alter'.'</a>':''),"\n";}echo"</table>\n";}echo($ua?'<p class="links hover"><a href="'.h(ME).'foreign='.url_escape($a).'">'.'Create foreign key'."</a>\n":''),"</div>\n";}if(support("check")){echo"<div>\n","<h3 id='checks'>".'Checks'."</h3>\n";$fb=driver()->checkConstraints($a);if($fb){echo"<table>\n";foreach($fb
as$w=>$X)echo"<tr".($w!=$X?" title='".h($w)."'":"").">","<td><code class='sql jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($X)),80,"</code>"),"<td class='hover'><a href='".h(ME.'check='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>","\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'check='.url_escape($a).'">'.'Create check'."</a>\n","</div>\n";}}if(support(is_view($S)?"view_trigger":"trigger")&&driver()->supportsAlterTable($S)){echo"<div>\n","<h3 id='triggers'>".'Triggers'."</h3>\n";$zl=triggers($a);if($zl){echo"<table>\n";foreach($zl
as$w=>$X){echo"<tr valign='top'><td>".h($X[0])."<td>".h($X[1])."<th>".h($w)."<td class='hover'><a href='".h(ME.'trigger='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>";$rj=$X[2];if($rj){$tj=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($rj["ns"]),ME).'function='.url_escape($rj["function"]).'&name='.url_escape($rj["name"]);echo", <a href='".h($tj)."' title='".h($rj["name"])."'>".'Alter function'."</a>";}echo"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'trigger='.url_escape($a).'">'.'Create trigger'."</a>\n","</div>\n";}$Yj=driver()->shadowTables($a);if($Yj){echo"<h3 id='shadow-tables'>".'Shadow tables'."</h3>\n";tables_links($Yj);}$Ve=driver()->inheritedTables($a);if($Ve){echo"<h3 id='partitions'>".'Inherited by'."</h3>\n";$Xh=driver()->partitionsInfo($a);if($Xh)echo"<p><code class='sql jush-".JUSH."'>BY ".h("$Xh[partition_by]($Xh[partition])")."</code>\n";tables_links($Ve);}}elseif(isset($_GET["schema"])){page_header('Database schema',"",array(),h(DB.($_GET["ns"]?".$_GET[ns]":"")));function
schema_column($R,array$aj,array&$d){if(!isset($d[$R])){$d[$R]=0;foreach((array)idx($aj,$R)as$B=>$cj){if($B!=$R)$d[$R]=max($d[$R],schema_column($B,$aj,$d)+1);}}return$d[$R];}function
type_class($U){foreach(array('char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',)as$w=>$X){if(preg_match("~$w|$X~",$U))return" class='$w'";}}$Nk=array();$Pk=array();$Ok=array();$zd=array();$ca=($_GET["schema"]?:$_COOKIE["adminer_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$ca,$Yf,PREG_SET_ORDER);foreach($Yf
as$r=>$A){$Nk[$A[1]]=array((float)$A[2],(float)$A[3]);$Pk[]="\n\t'".js_escape($A[1])."': [ $A[2], $A[3] ]";}$M=array();$aj=array();$Od=array();$ra=driver()->allFields();$xe=array();$Qk=array();foreach(table_status('',true)as$R=>$S){if(!is_view($S)){if(adminer()->tableName($S)!=""&&!$S["dependent"])$Qk[$R]=$S;else$xe[$R]=true;}}foreach($Qk
as$R=>$S){$G=0;$M[$R]["fields"]=array();foreach($ra[$R]as$k){$G+=1.25;$zd[$R][$k["field"]]=$G;$M[$R]["fields"][$k["field"]]=$k;}foreach(adminer()->foreignKeys($R)as$X){if($X["db"]==""&&$X["ns"]==""&&!$xe[$X["table"]]){$Od[$R][]=$X;$aj[$X["table"]][$R]=array();}}}$d=array();$de=array();$vm=array();$ie=array();foreach(array_keys($M)as$B)schema_column($B,$aj,$d);arsort($d);foreach($d
as$B=>$c){$_g=null;foreach((array)idx($Od,$B)as$X){if($X["table"]!=$B&&$M[$X["table"]])$_g=($_g===null?$d[$X["table"]]:min($_g,$d[$X["table"]]));}$d[$B]=max($c,(int)$_g-1);}$Sk=array();foreach($M
as$B=>$R){$c=$d[$B];$de[$c][]=$B;$fl=.75*strlen($B);foreach($R["fields"]as$k)$fl=max($fl,.65*strlen($k["field"]));$Sk[$B]=ceil($fl)+1;$vm[$c]=max(idx($vm,$c,0),$Sk[$B]);}foreach($Od
as$B=>$fm){foreach($fm
as$X){$he=$d[$B]+(idx($d,$X["table"],$d[$B])>$d[$B]?1:0);$ie[$he]=idx($ie,$he,0)+1;}}ksort($de);$ve=0;$um=0;$tb=0;$Ci=null;$Lk=array();foreach($de
as$c=>$T){if($Ci!==null){$tb=round($tb+$vm[$Ci]+1.7+idx($ie,$c,0)*.1,1);$D=array();foreach($T
as$B){$Bk=0;$Jb=0;$Rg=array_keys((array)idx($aj,$B));foreach((array)idx($Od,$B)as$X)$Rg[]=$X["table"];foreach($Rg
as$Lg){if($M[$Lg]&&$d[$Lg]<$c){$Bk+=$M[$Lg]["pos"][0];$Jb++;}}$D[$B]=($Jb?$Bk/$Jb:$ve);}asort($D);$T=array_keys($D);}$ql=0;foreach($T
as$B){$G=1.25*count($M[$B]["fields"]);$M[$B]["pos"]=($Nk[$B]?:array($ql,$tb));$Lk[$B]=$M[$B]["pos"][1];$ql+=2.5+$G;$ve=max($ve,$M[$B]["pos"][0]+2.5+$G);$um=max($um,round($M[$B]["pos"][1]+$vm[$c],1));if(!$Nk[$B])$Ok[]="\n\t'".js_escape($B)."': [ ".$M[$B]["pos"][0].", ".$M[$B]["pos"][1]." ]";}$Ci=$c;}$Hf=array();$Ma=array();foreach($Od
as$B=>$fm){foreach($fm
as$X){$Yk=idx($Lk,$X["table"],$Lk[$B]);$ik=$Lk[$B]+$vm[$d[$B]];$oj=($Yk-1>$ik);$Ff=($oj?$ik+1:min($Lk[$B],$Yk)-1);$La=idx($Ma,(string)$Ff,0);$Ma[(string)$Ff]=$La+1;$Ff=round($oj?min($Ff+$La*.1,$Yk-1):$Ff-$La*.1,1);while($Hf[(string)$Ff])$Ff-=.0001;$M[$B]["references"][$X["table"]][(string)$Ff]=array($X["source"],$X["target"]);$aj[$X["table"]][$B][(string)$Ff]=$X["target"];$Hf[(string)$Ff]=true;}}echo'<div id="schema" style="height: ',$ve,'em; width: ',$um,'em;">
<script',nonce(),'>
const tablePos = {',implode(",",$Pk)."\n",'};
const tablePosDefault = {',implode(",",$Ok)."\n",'};
const em = qs(\'#schema\').offsetHeight / ',$ve,';
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, \'',js_escape(DB),'\');
</script>
';foreach($M
as$B=>$R){echo"<div class='table'".on('mousedown','schemaMousedown')." style='top: ".$R["pos"][0]."em; left: ".$R["pos"][1]."em; width: ".$Sk[$B]."em;'>",'<a href="'.h(ME).'table='.url_escape($B).'"><b>'.h($B)."</b></a>";foreach($R["fields"]as$k){$X='<span'.type_class($k["type"]).' title="'.h($k["type"].($k["length"]?"($k[length])":"").($k["null"]?" NULL":'')).'">'.h($k["field"]).'</span>';echo"<br>".($k["primary"]?"<i>$X</i>":$X);}foreach((array)$R["references"]as$Zk=>$cj){foreach($cj
as$Ff=>$Xi){$Gf=$Ff-$R["pos"][1];$zk=($Gf>0?"left: 100%; width: calc($Gf"."em - 100%)":"left: $Gf"."em");$um=($Gf>0?"100%":(-$Gf)."em");$r=0;foreach($Xi[0]as$hk)echo"\n<div class='references' title='".h($Zk)."' id='refs$Ff-".($r++)."' style='$zk"."; top: ".$zd[$B][$hk]."em; padding-top: .5em;'>"."<div style='border-top: 1px solid gray; width: $um;'></div></div>";}}foreach((array)$aj[$B]as$Zk=>$cj){foreach($cj
as$Ff=>$al){$Gf=$Ff-$R["pos"][1];$r=0;foreach($al
as$Xk)echo"\n<div class='references arrow' title='".h($Zk)."' id='refd$Ff-".($r++)."' style='left: $Gf"."em; top: ".$zd[$B][$Xk]."em;'>"."<div style='height: .5em; border-bottom: 1px solid gray; width: ".(-$Gf)."em;'></div>"."</div>";}}echo"\n</div>\n";}foreach($M
as$B=>$R){foreach((array)$R["references"]as$Zk=>$cj){if($M[$Zk]){foreach($cj
as$Ff=>$Xi){$Ag=$ve;$gg=-10;foreach($Xi[0]as$w=>$hk){$ui=$R["pos"][0]+$zd[$B][$hk];$vi=$M[$Zk]["pos"][0]+$zd[$Zk][$Xi[1][$w]];$Ag=min($Ag,$ui,$vi);$gg=max($gg,$ui,$vi);}echo"<div class='references' id='refl$Ff' style='left: $Ff"."em; top: $Ag"."em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: ".($gg-$Ag)."em;'></div></div>\n";}}}}echo'</div>
<p class="links"><a href="',h(ME."schema=".url_escape($ca)),'" id="schema-link">Permanent link</a>
';}elseif(isset($_GET["dump"])){$a=$_GET["dump"];if($_POST&&!$j){$i=array("auto_increment"=>'');foreach(array("type","routine","event","trigger")as$Dk){if(support($Dk))$i[$Dk."s"]='';}save_settings(array_intersect_key($_POST+$i,array_flip(array("output","format","db_style","schema_style","table_style","data_style"))+$i),"adminer_export");$qa=(DB==""||$_GET["ns"]==="");$T=array_flip((array)$_POST["tables"])+array_flip((array)$_POST["data"]);$qd=dump_headers((count($T)==1?key($T):DB),($qa||count($T)>1));$nf=preg_match('~sql~',$_POST["format"]);if($nf){echo"-- Adminer ".VERSION." ".get_driver(DRIVER)." ".str_replace("\n"," ",connection()->server_info)." dump\n\n";if(JUSH=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";connection()->query("SET time_zone = '+00:00'");connection()->query("SET sql_mode = ''");}}$zk=$_POST["db_style"];$g=array(DB);if(DB==""){$g=$_POST["databases"];if(is_string($g))$g=explode("\n",rtrim(str_replace("\r","",$g),"\n"));}foreach((array)$g
as$h){adminer()->dumpDatabase($h);if(connection()->select_db($h)){if($nf&&$zk)echo
use_sql($h,$zk).";\n\n";foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?array(""):adminer()->schemas()))as$M){if($M!=""){if(DB==""&&information_schema(DB,$M))continue;set_schema($M);}if($nf&&$_POST["schema_style"]&&function_exists('Adminer\use_schema_sql'))echo
use_schema_sql($_GET["ns"],$_POST["schema_style"]).";\n\n";$vk=($_POST["table_style"]||$_POST["data_style"]?table_status('',true):array());$pd=array();$Wb=array();foreach($vk
as$B=>$S){if($qa||in_array($B,(array)$_POST["tables"]))$pd[$B]=$S;if($qa||in_array($B,(array)$_POST["data"]))$Wb[$B]=$S;}if($nf){if($_POST["table_style"]=="DROP+CREATE"&&function_exists('Adminer\drop_sql'))echo
drop_sql($pd);if($_POST["data_style"]=="TRUNCATE+INSERT"&&function_exists('Adminer\truncate_all_sql')){$_l=array();foreach($Wb
as$B=>$S){if(!is_view($S)&&!($_POST["table_style"]=="DROP+CREATE"&&isset($pd[$B])))$_l[]=$B;}echo
truncate_all_sql($_l);}$Nh="";if($_POST["types"]){foreach(types()as$s=>$U){$ic=type_definition($s);$eh=($ic["kind"]=='d'?"DOMAIN":"TYPE");if($ic["definition"])$Nh
.=($zk!='DROP+CREATE'?"DROP $eh IF EXISTS ".table($U).";;\n":"")."CREATE $eh ".table($U)." $ic[definition];\n\n";else$Nh
.="-- Could not export type ".preg_replace('~[\r\n]+~',' ',$U)."\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$B=$K["ROUTINE_NAME"];$rj=$K["ROUTINE_TYPE"];$Ue=routine($K["SPECIFIC_NAME"],$rj);if($Ue["definition"]!=""){$Kb=create_routine($rj,array("name"=>$B)+$Ue);set_utf8mb4($Kb);$Nh
.=($zk!='DROP+CREATE'?"DROP $rj IF EXISTS ".table($B).";;\n":"")."$Kb;\n\n";}else$Nh
.="-- Could not export ".strtolower($rj)." ".preg_replace('~[\r\n]+~',' ',$B)."\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$Kb=remove_definer(get_val("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($Kb);$Nh
.=($zk!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$Kb;;\n\n";}}echo($Nh&&JUSH=='sql'?"DELIMITER ;;\n\n$Nh"."DELIMITER ;\n\n":$Nh);}if($_POST["table_style"]||$_POST["data_style"]){$lm=array();foreach($vk
as$B=>$S){$R=array_key_exists($B,$pd);$Ub=array_key_exists($B,$Wb);if($R||$Ub){$nl=null;if($qd=="tar"){$nl=new
TmpFile;ob_start(array($nl,'write'),1e5);}adminer()->dumpTable($B,($R?$_POST["table_style"]:""),(is_view($S)?2:0));if(is_view($S))$lm[]=$B;elseif($Ub){$l=fields($B);$N=array("*");$Gb=convert_fields($l,$l);if($Gb)$N[]=substr($Gb,2);adminer()->dumpData($B,$_POST["data_style"],"",$N);}if($nf&&$_POST["triggers"]&&$R&&($zl=trigger_sql($B)))echo"\nDELIMITER ;;\n$zl\nDELIMITER ;\n";if($qd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$h/")."$B.csv",$nl);}elseif($nf)echo"\n";}}if($nf&&$_POST["table_style"]&&function_exists('Adminer\foreign_keys_sql')){foreach($pd
as$B=>$S){if(!is_view($S))echo
foreign_keys_sql($B);}}if($nf){foreach($lm
as$km)adminer()->dumpTable($km,$_POST["table_style"],1);}if($qd=="tar")echo
pack("x1024");}}}}adminer()->dumpFooter();exit;}page_header('Export',$j,($_GET["export"]!=""?array("table"=>$_GET["export"]):array()),h(DB));echo'
<form action="" method="post">
<table class="layout">
';$ac=array('','USE','DROP+CREATE','CREATE');$Aj=(JUSH=="mssql"?array('','DROP+CREATE','CREATE'):$ac);$Rk=array('','DROP+CREATE','CREATE');$Vb=array('','TRUNCATE+INSERT','INSERT');if(JUSH=="sql")$Vb[]='INSERT+UPDATE';$K=get_settings("adminer_export");if(!$K)$K=array("output"=>"text","format"=>"sql","db_style"=>(DB!=""?"":"CREATE"),"schema_style"=>"","table_style"=>"DROP+CREATE","data_style"=>"INSERT");echo"<tr><th>".'Output'."<td>".html_radios("output",adminer()->dumpOutput(),$K["output"])."\n","<tr><th>".'Format'."<td>".html_radios("format",adminer()->dumpFormat(),$K["format"])."\n",(JUSH=="sqlite"?"":"<tr><th>".'Database'."<td>".html_select('db_style',$ac,$K["db_style"]).(support("type")?checkbox("types",1,$K["types"],'User types'):"").(support("routine")?checkbox("routines",1,$K["routines"],'Routines'):"").(support("event")?checkbox("events",1,$K["events"],'Events'):"")),(function_exists('Adminer\use_schema_sql')?"<tr><th>".'Schema'."<td>".html_select('schema_style',$Aj,$K["schema_style"]):""),"<tr><th>".'Tables'."<td>".html_select('table_style',$Rk,$K["table_style"]).checkbox("auto_increment",1,$K["auto_increment"],'Auto Increment').(support("trigger")?checkbox("triggers",1,$K["triggers"],'Triggers'):""),"<tr><th>".'Data'."<td>".html_select('data_style',$Vb,$K["data_style"]),'</table>
';adminer()->dumpPrint();echo'<p><input type=\'submit\' value=\'Export\'>
',input_token(),'
<table',on('click','dumpClick'),'>
';$Bi=array();if($_GET["ns"]===""&&support("scheme")){echo"<thead><tr><th style='text-align: left;'>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly' title='".'All'."'".on('click','formCheck','^schemas\[').">".'Schema'."</label>","<tbody>\n";foreach(adminer()->schemas()as$M){if(!information_schema(DB,$M))echo"<tr><td>".checkbox("schemas[]",$M,true,$M,"","block")."\n";}}elseif(DB!=""){$gb=($a!=""?"":" checked");echo"<thead><tr>","<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$gb class='jsonly' title='".'All'."'".on('click','formCheck','^tables\[').">".'Table'."</label>","<th style='text-align: right;'><label class='block'>".'Data'."<input type='checkbox' id='check-data'$gb class='jsonly' title='".'All'."'".on('click','formCheck','^data\[')."></label>","<tbody>\n";$lm="";$Uk=tables_list();foreach($Uk
as$B=>$U){$Ai=preg_replace('~_.*~','',$B);$gb=($a==""||$a==(substr($a,-1)=="%"?"$Ai%":$B));$Fi="<tr><td>".checkbox("tables[]",$B,$gb,$B,"","block");if($U!==null&&!preg_match('~table~i',$U))$lm
.="$Fi\n";else
echo"$Fi<td align='right'><label class='block'><span id='Rows-".h($B)."'></span>".checkbox("data[]",$B,$gb)."</label>\n";$Bi[$Ai]++;}echo$lm;if($Uk)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$g=adminer()->databases();echo"<thead><tr><th style='text-align: left;'>","<label class='block'>".($g?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly' title='".'All'."'".on('click','formCheck','^databases\[').">":"").'Database'."</label>","<tbody>\n";if($g){foreach($g
as$h){if(!information_schema($h)){$Ai=preg_replace('~_.*~','',$h);echo"<tr><td>".checkbox("databases[]",$h,$a==""||$a=="$Ai%",$h,"","block")."\n";$Bi[$Ai]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo'</table>
</form>
';$Hd=true;foreach($Bi
as$w=>$X){if($w!=""&&$X>1){echo($Hd?"<p>":" ")."<a href='".h(ME)."dump=".url_escape("$w%")."'>".h($w)."</a>";$Hd=false;}}}elseif(isset($_GET["privileges"])){page_header('Privileges');echo'<p class="links"><a href="'.h(ME).'user=">'.'Create user'."</a>";$I=connection()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$be=$I;if(!$I)$I=connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''><p>\n";hidden_fields_get();echo
input_hidden("db",DB),($be?"":input_hidden("grant")),"<table class='odds'>\n","<thead><tr><th>".'Username'."<th>".'Server'."<td class='hover'><tbody>\n";while($K=$I->fetch_assoc())echo'<tr><td>'.h($K["User"]),"<td>".h($K["Host"]),'<td class="hover"><a href="'.h(ME.'user='.url_escape($K["User"]).'&host='.url_escape($K["Host"])).'">'.'Edit'."</a>\n";if(!$be||DB!="")echo"<tr><td><input name='user' autocapitalize='off'>","<td><input name='host' value='localhost' autocapitalize='off'>","<td class='hover'><input type='submit' value='".'Edit'."'>\n";echo"</table>\n","</form>\n";}elseif(isset($_GET["sql"])){if(!$j&&$_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers("sql");if($_POST["format"]=="sql")echo"$_POST[query]\n";else{adminer()->dumpTable("","");adminer()->dumpData("","table",$_POST["query"]);adminer()->dumpFooter();}exit;}if(!$j&&$_POST["val"]){$ma=0;$_k=true;$Za=array();$yj=0;foreach($_POST["val"]as$L)$yj+=count($L);$Oa=$yj>1&&driver()->begin();foreach($_POST["val"]as$Jk=>$L){$R=bracket_escape($Jk,true);$l=fields($R);$Kk=indexes($R);foreach($L
as$t=>$K){parse_str(bracket_escape($t,true),$Z);$Hl=array();foreach($Z["where"]as$w=>$X)$Hl[bracket_escape($w,true)]=$X;if(!$l||$Z["null"]||array_diff_key($Hl,$l)||!unique_array($Hl,$Kk)){$_k=false;break
2;}$P=array();$N=array();foreach($K
as$vf=>$X){$w=bracket_escape($vf,true);$k=idx($l,$w);if(!$k){$_k=false;break
3;}$P[idf_escape($w)]=(preg_match('~char|text~',$k["type"])||$X!=""?adminer()->processInput($k,$X):"NULL");$N[$vf]=$w;}$Ri=where($Z,$l);if(!driver()->update($R,$P," WHERE $Ri",0," ")){$_k=false;break
2;}$ma+=connection()->affected_rows;$d=array();foreach($N
as$w)$d[]=idf_escape($w);$Ql=driver()->select($R,$d,array($Ri),$d);$Vg=($Ql?$Ql->fetch_row():array());$rf=0;foreach($N
as$vf=>$w){$k=$l[$w];$yk=array('type'=>(preg_match('~binary~',$k["type"])?'blob':$k["type"]));$Za["val[$Jk][$t][$vf]"]=select_value(idx($Vg,$rf++),"",$yk,null);}}}if($Oa&&$_k)$_k=driver()->commit();queries_redirect(null,lang_format(array('%d item has been affected.','%d items have been affected.'),$ma),$_k);if($Oa&&!$_k)driver()->rollback();page_headers();page_messages($j);foreach($Za
as$B=>$X)echo"<div data-name='".h($B)."' hidden>$X</div>\n";exit;}restart_session();$Ae=&get_session("queries");$_e=&$Ae[DB];if(!$j&&$_POST["clear"]){$_e=array();redirect(remove_from_uri("history"));}stop_session();$ka=get_settings("adminer_import");if($_POST&&$ka)save_settings($ka,"adminer_import");page_header((isset($_GET["import"])?'Import':'SQL command'),$j);$Pf=driver()->lineComment();$bd=0;function
query_error_line($dl,$kh,$H,$Of){return
substr_count(substr($dl,0,$kh),"\n")+($Of?:substr_count(substr($H,0,strspn($H," \t\r\n")),"\n")+1);}if(!$j&&$_POST&&!(isset($_GET["import"])&&adminer()->importProcess())){$kc=driver()->delimiter;$o=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$lk=adminer()->importServerPath();$o=@fopen((file_exists($lk)?$lk:"compress.zlib://$lk.gz"),"rb");$H=($o?fread($o,1e6):false);}else$H=get_file("sql_file",true,$kc);if(is_string($H)){if(($og=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($og,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$Mi=$H.(preg_match("~$kc\\s*\$~",$H)?"":$kc);if(!$_e||first(end($_e))!=$Mi){restart_session();$_e[]=array($Mi,time());set_session("queries",$Ae);stop_session();}}$jk="(?:\\s|\xEF\xBB\xBF|/\\*[\s\S]*?\\*/|(?:$Pf)[^\n]*\n?|--\r?\n)";$kh=0;$Qi=0;$Sc=true;$Ib=false;$vg=false;$f=connect();if($f&&DB!=""){$f->select_db(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$f);}$ub=0;$cd=array();$Vh='[\'"'.(JUSH=="sql"?'`':(JUSH=="sqlite"?'`[':(JUSH=="mssql"?'[':''))).']|/\*|'.$Pf.'|$'.(JUSH=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$rl=microtime(true);while($H!=""){if(!$kh&&preg_match("~^$jk*+DELIMITER\\s+(\\S+)~i",$H,$A)){$kc=preg_quote($A[1]);$H=substr($H,strlen($A[0]));$Qi+=strlen($A[0]);}elseif(!$kh&&JUSH=='pgsql'&&preg_match("~^($jk*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$A)){$kc="\n\\\\\\.\r?\n";$Ib=true;$kh=strlen($A[0]);}elseif(!$kh&&JUSH=='pgsql'&&preg_match("~^$jk*+\\\\connect[ \t][^\n]*~",$H,$A)){$kc="\n";$vg=true;$kh=strlen($A[0]);}else{preg_match("($kc\\s*|$Vh)",$H,$A,PREG_OFFSET_CAPTURE,$kh);list($Qd,$G)=$A[0];if(!$Qd&&$o&&!feof($o))$H
.=fread($o,1e5);else{if(!$Qd&&rtrim($H)=="")break;$kh=$G+strlen($Qd);if($Qd&&!preg_match("(^$kc)",$Qd)){$Wa=driver()->hasCStyleEscapes()||(JUSH=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$ki=($Qd=='/*'?'\*/':($Qd=='['?']':(preg_match("~^(?:$Pf)~",$Qd)?"\n":preg_quote($Qd).($Wa?'|\\\\.':''))));while(preg_match("($ki|\$)s",$H,$A,PREG_OFFSET_CAPTURE,$kh)){$zj=$A[0][0];if(!$zj&&$o&&!feof($o))$H
.=fread($o,1e5);else{$kh=$A[0][1]+strlen($zj);if(!$zj||$zj[0]!="\\")break;}}}else{$Mi=substr($H,0,$G+($Ib?3:0));$H=substr($H,$kh);$Ni=$Qi;$Qi+=$kh;$kh=0;if($Ib||$vg){$kc=driver()->delimiter;$Ib=false;$vg=false;}$nb="<code class='jush-".JUSH."'>".adminer()->sqlCommandQuery($Mi)."</code>";if(preg_match("~^$jk*+\$~",$Mi)&&!preg_match('~/\*M?!~',$Mi)){echo($_POST["only_errors"]?"":"<pre>$nb</pre>\n");continue;}$Sc=false;$ub++;$Fi="<pre id='sql-$ub'>$nb</pre>\n";if(JUSH=="sqlite"&&preg_match("~^$jk*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$Mi,$A)!==0){echo$Fi,"<p class='error'>".sprintf('%s queries are not supported.',preg_match('~ATTACH~i',$A[1])?'ATTACH':'VACUUM INTO')."\n";$cd[]=" <a href='#sql-$ub'>$ub</a>";if(!$bd&&!isset($_GET["import"]))$bd=query_error_line($_POST["query"],$Ni,$Mi,0);if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$Fi;ob_flush();flush();}$qk=microtime(true);if(JUSH=="pgsql"&&preg_match("~^$jk*+\\\\connect\\s+(\"(?:[^\"]|\"\")+\"|[^\\s\";]+)\\s*;*\\s*\$~",$Mi,$A)){$h=idf_unescape($A[1]);if(connection()->select_db($h)){if($f)$f->select_db($h);echo($_POST["only_errors"]?"":"<p class='message'>".'Database'.": ".h($h)." <span class='time'>(".format_time($qk).")</span>\n");continue;}}elseif(connection()->multi_query($Mi)&&$f&&preg_match("~^$jk*+USE\\b~i",$Mi))$f->query($Mi);do{$I=connection()->store_result();if(connection()->error){echo($_POST["only_errors"]?$Fi:""),"<p class='error'>".'Error in query'.(connection()->errno?" (".connection()->errno.")":"").": ".adminer()->error()."\n";$cd[]=" <a href='#sql-$ub'>$ub</a>";if(!$bd&&!isset($_GET["import"]))$bd=query_error_line($_POST["query"],$Ni,$Mi,driver()->errorLine($Mi));if($_POST["error_stops"])break
2;}else{$z=ME."sql=".url_escape(trim($Mi));$gl=" <span class='time'>(".format_time($qk).")</span>".(strlen($z)<adminer()->sqlUrlLength()-100?" <a href='".h($z)."'>".'Edit'."</a>":"");$ma=connection()->affected_rows;$om=($_POST["only_errors"]?"":driver()->warnings());$pm="warnings-$ub";if($om)$gl
.=", <a href='#$pm' class='toggle'>".'Warnings'."</a>";$nd="";$od="explain-$ub";if(is_object($I)){$y=$_POST["limit"];$ch=$y;$Jc=!$_POST["only_errors"];if($Jc)echo"<form action='' method='post'>\n";$Fh=print_select_result($I,$f,array(),$ch,$Jc);if(!$_POST["only_errors"]){$ch=max($I->num_rows,$ch);echo"<p class='sql-footer'>".($ch?($y&&$ch>$y?sprintf('%d / ',$y):"").lang_format(array('%d row','%d rows'),$ch):""),$gl;if($f&&preg_match("~^($jk|\\()*+SELECT\\b~i",$Mi)&&($nd=adminer()->explain($f,$Mi,$Fh))!="")echo", <a href='#$od' class='toggle'>Explain</a>";if($Jc)echo", <input type='submit' name='save' value='".'Save'."' class='jsonly' disabled"." title='".'Ctrl+click on a value to modify it.'."'".on('click','sqlSave','Saving…').">";$s="export-$ub";echo", <a href='#$s' class='toggle'>".'Export'."</a><span id='$s' class='hidden'>: ".html_select("output",adminer()->dumpOutput(),$ka["output"])." ".html_select("format",adminer()->dumpFormat(),$ka["format"]).input_hidden("query",$Mi)."<input type='submit' name='export' value='".'Export'."'".($y?"":on('click','sqlExport')).">".input_token()."</span>\n"."</form>\n";}}else{if(preg_match("~^$jk*+(CREATE|DROP|ALTER)$jk++(DATABASE|SCHEMA)\\b~i",$Mi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"])echo"<p class='message' title='".h(connection()->info)."'>".lang_format(array('Query executed OK, %d row affected.','Query executed OK, %d rows affected.'),$ma)."$gl\n";}echo($om?"<div id='$pm' class='hidden'>\n$om</div>\n":""),($nd!=""?"<div id='$od' class='hidden explain'>\n$nd</div>\n":"");}$qk=microtime(true);}while(connection()->next_result());}}}}}if($Sc)echo"<p class='message'>".'No commands to execute.'."\n";else{$Oe=connection()->inTransaction();driver()->rollback();if($Oe)echo"<pre><code class='jush-".JUSH."'>ROLLBACK".(JUSH=="mssql"?" TRANSACTION":"")." -- Adminer</code></pre>\n";if($_POST["only_errors"])echo"<p class='message'>".lang_format(array('%d query executed OK.','%d queries executed OK.'),$ub-count($cd))," <span class='time'>(".format_time($rl).")</span>\n";elseif($cd&&$ub>1)echo"<p class='error'>".'Error in query'.": ".implode("",$cd)."\n";}}else
echo"<p class='error'>".upload_error($H)."\n";}echo'
<form action="" method="post" enctype="multipart/form-data" id="form"';$Rl="";if(!isset($_GET["import"]))echo
on('submit','sqlSubmit',remove_from_uri("sql|limit|error_stops|only_errors|history"),adminer()->sqlUrlLength());else
echo
on_upload_progress($Rl);echo'>
';$kd="<input type='submit' value='".'Execute'."' title='Ctrl+Enter'>";if(!isset($_GET["import"])){$Mi=$_GET["sql"];if($_POST)$Mi=$_POST["query"];elseif($_GET["history"]=="all")$Mi=$_e;elseif($_GET["history"]!="")$Mi=idx($_e[$_GET["history"]],0);echo"<p>";textarea("query",$Mi,20,80,JUSH,$bd);echo($_POST?"":script("qs('textarea').focus();")),"<p>";adminer()->sqlPrintAfter();echo"$kd\n",'Limit rows'.": <input type='number' name='limit' class='size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{$je=(extension_loaded("zlib")?"[.gz]":"");echo"<fieldset><legend>".'File upload'."</legend><div>",($Rl?input_hidden(ini_get("session.upload_progress.name"),$Rl):""),"SQL$je: ".file_input(" name='sql_file[]' multiple","\n$kd"),($Rl?" <progress class='jsonly hidden' max='1' value='0'></progress>":""),"</div></fieldset>\n";$Le=adminer()->importServerPath();if($Le)echo"<fieldset><legend>".'From server'."</legend><div>",sprintf('Webserver file %s',"<code>".h($Le)."$je</code>")," <input type='submit' name='webfile' value='".'Run file'."'>","</div></fieldset>\n";adminer()->importPrint();echo"<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),'Stop on error')."\n",checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),'Show only errors')."\n",input_token();if(!isset($_GET["import"])&&$_e){print_fieldset("history",'History',$_GET["history"]!="");for($X=end($_e);$X;$X=prev($_e)){$w=key($_e);list($Mi,$gl,$Oc)=$X;echo'<div><a href="'.h(ME."sql=&history=$w").'" class="hover">'.'Edit'."</a>"." <span class='time' title='".@date('Y-m-d',$gl)."'>".@date("H:i:s",$gl)."</span>"." <code class='sql jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim(preg_replace("~^(?:$Pf).*~m",'',$Mi))),80,"</code>").($Oc?" <span class='time'>($Oc)</span>":"")."</div>\n";}echo"<input type='submit' name='clear' value='".'Clear'."'>\n","<a href='".h(ME."sql=&history=all")."'>".'Edit all'."</a>\n","</div></fieldset>\n";}echo'</form>
';}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$l=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$l):""):where($_GET,$l));$Ol=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($l
as$B=>$k){if((!$Ol&&!isset($k["privileges"]["insert"]))||adminer()->fieldName($k)=="")unset($l[$B]);}if($_POST&&!$j&&!isset($_GET["select"])){$_=relative_uri((string)$_POST["referer"]);if($_POST["insert"])$_=($Ol?null:relative_uri());elseif(!preg_match('~^.+&select=.+$~',$_))$_=ME."select=".url_escape($a);$v=indexes($a);$Il=unique_array($_GET["where"],$v);$Ri="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($_,'Item has been deleted.',driver()->delete($a,$Ri,$Il?0:1));else{$P=array();foreach($l
as$B=>$k){$X=process_input($k);if($X!==false&&$X!==null)$P[idf_escape($B)]=$X;}if($Ol){if(!$P)redirect($_);queries_redirect($_,'Item has been updated.',driver()->update($a,$P,$Ri,$Il?0:1));if(is_ajax()){page_headers();page_messages($j);exit;}}else{$I=driver()->insert($a,$P);$Ef=($I?last_id($I):0);queries_redirect($_,sprintf('Item%s has been inserted.',($Ef?" $Ef":"")),$I);}}}$K=null;$H="";$gl="";if($Z){$N=array();$Jj=array("*");foreach($l
as$B=>$k){if(isset($k["privileges"]["select"])){$_a=($_POST["clone"]&&$k["auto_increment"]?"''":convert_field($k));$c=($_a?"$_a AS ":"").idf_escape($B);$N[]=$c;if($_a)$Jj[]=$c;}}$K=array();if(!support("table")){$N=array("*");$Jj=$N;}if($N){$qk=microtime(true);$I=driver()->select($a,$N,array($Z),$N,array(),(isset($_GET["select"])?2:1));$H=str_replace("SELECT ".implode(", ",$N),"SELECT ".implode(", ",$Jj),driver()->query);$gl=format_time($qk);if(!$I)$j=adminer()->error();else{$K=$I->fetch_assoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetch_assoc()))$K=null;}}if(!$l&&driver()->primary!=""){if(!$Z){$I=driver()->select($a,array("*"),array(),array("*"));$K=($I?$I->fetch_assoc():false);if(!$K)$K=array(driver()->primary=>"");}if($K){foreach($K
as$w=>$X){if(!$Z)$K[$w]=null;$l[$w]=array("field"=>$w,"null"=>($w!=driver()->primary),"auto_increment"=>($w==driver()->primary));}}}if($_POST["save"]){$wi=array();foreach((array)$_POST["fields"]as$w=>$X)$wi[bracket_escape($w,true)]=$X;$K=$wi+($K?$K:array());}edit_form($a,$l,$K,$Ol,$j,$H,$gl);}elseif(isset($_GET["create"])){function
referencable_primary($Lj){$J=array();foreach(table_status('',true)as$Mk=>$R){if($Mk!=$Lj&&!$R["dependent"]&&fk_support($R)){foreach(fields($Mk)as$k){if($k["primary"]){if($J[$Mk]){unset($J[$Mk]);break;}$J[$Mk]=$k;}}}}return$J;}$a=$_GET["create"];$Zh=driver()->partitionBy;$di=($Zh&&$a!=""?driver()->partitionsInfo($a):array());$Zi=referencable_primary($a);$Od=array();foreach($Zi
as$Mk=>$k)$Od[str_replace("`","``",$Mk)."`".str_replace("`","``",$k["field"])]=$Mk;$Ih=array();$S=array();$ah=false;if($a!=""){$Ih=fields($a);$S=table_status1($a);$ah=(count($S)<2);}$ua=($a==""||driver()->supportsAlterTable($S));$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!$j)save_settings(array("comments"=>$_POST["comments"],"defaults"=>$_POST["defaults"]));if($_POST&&!process_fields($K["fields"])&&!$j){if($_POST["drop"])queries_redirect(substr(ME,0,-1),'Table has been dropped.',drop_tables(array($a)));else{$l=array();$ra=array();$Vl=false;$Md=array();$Hh=reset($Ih);$oa=" FIRST";foreach($K["fields"]as$k){$n=$Od[$k["type"]];$Bl=($n!==null?$Zi[$n]:$k);if($k["field"]!=""){if(!$k["generated"])$k["default"]=null;$k["default_sql"]=($k["generated"]=="DEFAULT SQL");$Ki=process_field($k,$Bl);$ra[]=array($k["orig"],$Ki,$oa);if(!$Hh||$Ki!==process_field($Hh,$Hh)){$l[]=array($k["orig"],$Ki,$oa);if($k["orig"]!=""||$oa||(JUSH=="sqlite"&&$k["comment"]!=""))$Vl=true;}if($n!==null)$Md[idf_escape($k["field"])]=($a!=""&&JUSH!="sqlite"?"ADD":" ").format_foreign_key(array('table'=>$Od[$k["type"]],'source'=>array($k["field"]),'target'=>array($Bl["field"]),'on_delete'=>$k["on_delete"],),object_name("FOREIGN",trim($K["name"]),array($k["field"])));$oa=" AFTER ".idf_escape($k["field"]);}elseif($k["orig"]!=""){$Vl=true;$l[]=array($k["orig"]);}if($k["orig"]!=""){$Hh=next($Ih);if(!$Hh)$oa="";}}$bi=array();if(in_array($K["partition_by"],$Zh)){foreach($K
as$w=>$X){if(preg_match('~^partition~',$w))$bi[$w]=$X;}foreach($bi["partition_names"]as$w=>$B){if($B==""){unset($bi["partition_names"][$w]);unset($bi["partition_values"][$w]);}}$bi["partition_names"]=array_values($bi["partition_names"]);$bi["partition_values"]=array_values($bi["partition_values"]);if($bi==$di)$bi=array();}elseif(preg_match("~partitioned~",$S["Create_options"]))$bi=null;$qg='Table has been altered.';if($a==""){cookie("adminer_engine",$K["Engine"]);$qg='Table has been created.';}$B=trim($K["name"]);$_=ME.(support("table")?"table=":"select=").url_escape($B);$vb=($K["Comment"]!=$S["Comment"]?$K["Comment"]:null);$Vc=($K["Engine"]&&$K["Engine"]!=$S["Engine"]?$K["Engine"]:"");$I=alter_table($a,$B,(JUSH=="sqlite"&&($Vl||$Md||$vb!==null||$Vc)?$ra:$l),$Md,$vb,$Vc,($K["Collation"]&&$K["Collation"]!=$S["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$bi);if($I&&!Queries::$queries&&$a!=""&&!$l&&!$Md)redirect($_);queries_redirect($_,$qg,$I);}}$sk=($a!=""?"alter":"create");page_header(($a!=""?'Alter table':'Create table'),$j,array("table"=>$a),h($a),$ah,doc_link(array('sql'=>"$sk-table.html",'mariadb'=>($a!=""?"$sk-table":""),)));if(!$_POST){$El=driver()->types();$K=array("Engine"=>$_COOKIE["adminer_engine"],"fields"=>array(array("field"=>"","type"=>(isset($El["int"])?"int":(isset($El["integer"])?"integer":"")),"on_update"=>"")),"partition_names"=>array(""),);if($a!=""){$K=$S;$K["name"]=$a;$K["fields"]=array();if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($Ih
as$k){if($k["generated"])$k["default"]=ltrim($k["default"]);$k["generated"]=$k["generated"]?:(isset($k["default"])?($k["default_sql"]?"DEFAULT SQL":"DEFAULT"):"");$K["fields"][]=$k;}if($Zh){$K+=$di;$K["partition_names"][]="";$K["partition_values"][]="";}}}$rb=flat_collations();$Wc=driver()->engines();foreach($Wc
as$Vc){if(!strcasecmp($Vc,$K["Engine"])){$K["Engine"]=$Vc;break;}}$bg=max_input_vars(12,20);if($bg){$xe=(count($K["fields"])>$bg?"":" hidden");echo"<p".($xe?" id='max-fields' data-columns='$bg'":"")." class='error$xe'>".max_input_vars_error()."\n";}echo'
<form action="" method="post" id="form">
<p>
';if(support("columns")||$a==""){echo'Table name'.": <input name='name'".($a==""&&!$_POST?" autofocus":"")." data-maxlength='64' value='".h($K["name"])."' autocapitalize='off'>\n",(!$ua?h($S["Engine"])."\n":($Wc?html_select("Engine",array(""=>"(".'engine'.")")+$Wc,$K["Engine"],on('change','helpClose').on_help_value())."\n":""));if($rb)echo"<datalist id='collations'>".optionlist($rb)."</datalist>\n",(preg_match("~sqlite|mssql~",JUSH)?"":"<input list='collations' name='Collation' value='".h($K["Collation"])."' placeholder='(".'collation'.")'>\n");echo"<input type='submit' value='".'Save'."'>\n";}if(support("columns")&&$ua){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$rb,"TABLE",$Od);echo"</table>\n",script("editFields();"),"</div>\n<p>\n",'Auto Increment'.": <input type='number' name='Auto_increment' class='size' value='".h($K["Auto_increment"])."'>\n",checkbox("defaults",1,($_POST?$_POST["defaults"]:get_setting("defaults")),'Default values',on('click','columnShowClick',6),"jsonly");$xb=($_POST?$_POST["comments"]:get_setting("comments"));if(support("comment")){echo
checkbox("comments",1,$xb,'Comment',on('click','editingCommentsClick',true),"jsonly").' ';$b=" name='Comment' data-maxlength='".(min_version(5.5)?2048:60)."'".($xb?"":" class='hidden'");echo
adminer()->commentInput('TABLE',$b,$K["Comment"]);}echo'<p>
<input type=\'submit\' value=\'Save\'>
';}echo'
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';if($Zh&&(JUSH=='sql'||$a=="")){$ai=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset("partition",'Partition by',$K["partition_by"]);echo"<p>".html_select("partition_by",array_merge(array(""),$Zh),$K["partition_by"],on('change','partitionByChange').on_help_value('.','PARTITION BY $&'))."\n","(<input name='partition' value='".h($K["partition"])."'>)\n",'Partitions'.": <input type='number' name='partitions' class='size".($ai||!$K["partition_by"]?" hidden":"")."' value='".h($K["partitions"])."'>\n","<table id='partition-table'".($ai?"":" class='hidden'").">\n","<thead><tr><th>".'Partition name'."<th>".'Values'."<tbody>\n";foreach($K["partition_names"]as$w=>$X)echo'<tr>','<td><input name="partition_names[]" value="'.h($X).'" autocapitalize="off"'.($w==count($K["partition_names"])-1?on('input','partitionNameChange'):'').'>','<td><input name="partition_values[]" value="'.h(idx($K["partition_values"],$w)).'">';echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$Te=array("PRIMARY","UNIQUE","INDEX");$S=table_status1($a,true);$Re=driver()->indexAlgorithms($S);if(preg_match('~MyISAM|M?aria'.(min_version(5.6,'10.0.5')?'|InnoDB':'').'~i',$S["Engine"]))$Te[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.(min_version(5.7,'10.2.2')?'|InnoDB':'').'~i',$S["Engine"]))$Te[]="SPATIAL";if(min_version('',11.7)&&preg_match('~MyISAM|InnoDB~i',$S["Engine"]))$Te[]="VECTOR";$v=indexes($a);$l=fields($a);$Ei=array();if(JUSH=="mongo"){$Ei=$v["_id_"];unset($Te[0]);unset($v["_id_"]);}$K=$_POST;if($K)save_settings(array("index_options"=>$K["options"]));if($_POST&&!$j&&!$_POST["add"]&&!$_POST["drop_col"]){$ta=array();foreach($K["indexes"]as$u){$B=$u["name"];if(in_array($u["type"],$Te)){$d=array();$Lf=array();$nc=array();$vh=array();$Se=(support("partial_indexes")?$u["partial"]:"");$Qe=(in_array($u["algorithm"],$Re)?$u["algorithm"]:"");$P=array();ksort($u["columns"]);foreach($u["columns"]as$w=>$c){if($c!=""){$x=idx($u["lengths"],$w);$lc=idx($u["descs"],$w);$uh=idx($u["opclasses"],$w);$P[]=($l[$c]?idf_escape($c):$c).($x?"(".(+$x).")":"").($uh!=""?" ".idf_escape($uh):"").($lc?" DESC":"");$d[]=$c;$Lf[]=($x?:null);$nc[]=$lc;$vh[]="$uh";}}$ld=$v[$B];if($ld){ksort($ld["columns"]);ksort($ld["lengths"]);ksort($ld["descs"]);if($u["type"]==$ld["type"]&&array_values($ld["columns"])===$d&&(!$ld["lengths"]||array_values($ld["lengths"])===$Lf)&&array_values($ld["descs"])===$nc&&(!$ld["opclasses"]||array_values($ld["opclasses"])===$vh)&&$ld["partial"]==$Se&&(!$Re||$ld["algorithm"]==$Qe)&&!$ld["invalid"]){unset($v[$B]);continue;}}if($d)$ta[]=array($u["type"],$B,$P,$Qe,$Se);}}foreach($v
as$B=>$ld)$ta[]=array($ld["type"],$B,"DROP");if(!$ta)redirect(ME."table=".url_escape($a));queries_redirect(ME."table=".url_escape($a),'Indexes have been altered.',alter_indexes($a,$ta));}page_header('Indexes',$j,array("table"=>$a),h($a),false,doc_link(array('sql'=>"create-index.html",)));$Ad=array_keys($l);if($_POST["add"]){foreach($K["indexes"]as$w=>$u){if($u["columns"][count($u["columns"])]!="")$K["indexes"][$w]["columns"][]="";}$u=end($K["indexes"]);if($u["type"]||array_filter($u["columns"],'strlen'))$K["indexes"][]=array("columns"=>array(1=>""));}if(!$K){foreach($v
as$w=>$u){$v[$w]["name"]=$w;$v[$w]["columns"][]="";}$v[]=array("columns"=>array(1=>""));$K["indexes"]=$v;}$Lf=(JUSH=="sql"||JUSH=="mssql");$vh=driver()->indexOpclasses();$Zj=($_POST?$_POST["options"]:get_setting("index_options"));$Ng=array();foreach($Te
as$U)$Ng[$U]=str_replace("{table}",$a,adminer()->namePattern($U));echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap odds">
<thead><tr>
<th id="label-type">Index Type
';$Je=" class='idxopts".($Zj?"":" hidden")."'";if($Re)echo"<th id='label-algorithm'$Je>".'Algorithm'.doc_link(array('sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'storage-engine-index-types/',));echo'<th><input type="submit" hidden>','Columns'.($Lf?"<span$Je> (".'length'.")</span>":"");if($Lf||support("descidx"))echo
checkbox("options",1,$Zj,'Options',on('click','indexOptionsShow'),"jsonly")."\n";echo'<th id="label-name">Name
';if(support("partial_indexes"))echo"<th id='label-condition'$Je>".'Condition';echo'<td><noscript>',icon("plus","add[0]","+",'Add next'),'</noscript>
<tbody>
';if($Ei){echo"<tr><td>PRIMARY<td>";foreach($Ei["columns"]as$w=>$c)echo
select_input(" disabled",array_combine($Ad,$Ad),$c),"<label><input disabled type='checkbox'>".'descending'."</label> ";echo"<td><td>\n";}$rf=1;foreach($K["indexes"]as$u){if(!$_POST["drop_col"]||$rf!=key($_POST["drop_col"])){echo"<tr><td>".html_select("indexes[$rf][type]",array(-1=>"")+$Te,$u["type"],on('change','indexesChangeType',$Ng),"label-type");if($Re)echo"<td$Je>".html_select("indexes[$rf][algorithm]",array_merge(array(""),$Re),$u['algorithm'],"","label-algorithm");echo"<td>";ksort($u["columns"]);$r=1;foreach($u["columns"]as$w=>$c){echo"<span>".select_input(" name='indexes[$rf][columns][$r]' title='".'Column'."'".on('change','indexesChangeColumn',$Ng),($l&&($c==""||$l[$c])?array_combine($Ad,$Ad):array()),$c)," <span$Je>",($Lf?"<input type='number' name='indexes[$rf][lengths][$r]' class='size' value='".h(idx($u["lengths"],$w))."' title='".'Length'."'>":"");if($vh){$uh=idx($u["opclasses"],$w);echo
html_select("indexes[$rf][opclasses][$r]",array(""=>"(".'operator class'.")")+array_combine($vh,$vh)+($uh!=""?array($uh=>$uh):array()),$uh),'';}echo(support("descidx")?checkbox("indexes[$rf][descs][$r]",1,idx($u["descs"],$w),'descending'):""),"<br>","</span></span>";$r++;}echo"<td><input name='indexes[$rf][name]' value='".h($u["name"])."' autocapitalize='off' aria-labelledby='label-name'>\n";if(support("partial_indexes"))echo"<td$Je><input name='indexes[$rf][partial]' value='".h($u["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>".icon("cross","drop_col[$rf]","x",'Remove',on('click','editingRemoveRow','indexes$1[type]')),(idx($v[$u["name"]],"invalid")?" <span class='error'>INVALID</span>":""),"\n";}$rf++;}echo'</table>
</div>
<p>
<input type=\'submit\' value=\'Save\'>
';if(support("concurrent_indexes"))echo
checkbox("concurrently",1,$_POST["concurrently"],"concurrently").''."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!$j&&!$_POST["add"]){$B=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),'Database has been dropped.',drop_databases(array(DB)));}elseif($B!==DB){if(DB!=""){$_GET["db"]=$B;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".url_escape($B),'Database has been renamed.',rename_database($B,(string)$K["collation"]));}else{$g=explode("\n",str_replace("\r","",$B));$_k=true;$Cf="";foreach($g
as$h){if(count($g)==1||$h!=""){if(!create_database($h,(string)$K["collation"]))$_k=false;$Cf=$h;}}restart_session();set_session("dbs",null);queries_redirect(preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($Cf),'Database has been created.',$_k);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($B).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),'Database has been altered.');}}$sk=(DB!=""?"alter":"create");page_header(DB!=""?'Alter database':'Create database',$j,array(),h(DB),false,doc_link(array('sql'=>"$sk-database.html",'mariadb'=>(DB!=""?"":"$sk-database"),)));$rb=collations();$B=DB;if($_POST)$B=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,$rb);elseif(JUSH=="sql"){foreach(get_vals("SHOW GRANTS")as$be){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$be,$A)&&$A[1]){$B=stripcslashes(idf_unescape("`$A[2]`"));break;}}}echo'
<form action="" method="post">
<p>
',($_POST["add"]||strpos($B,"\n")?"<textarea autofocus name='name' rows='10' cols='40'>\n".h($B).'</textarea><br>':'<input name="name" autofocus value="'.h($B).'" data-maxlength="64" autocapitalize="off">')."\n",($rb?"<datalist id='collations'>".optionlist(flat_collations())."</datalist>"."<input list='collations' name='collation' value='".h($K["collation"])."' placeholder='(".'collation'.")'>".doc_link(array('sql'=>"charset-charsets.html",'mariadb'=>"supported-character-sets-and-collations/",)):"")."\n",'<input type=\'submit\' value=\'Save\'>
';if(DB!="")echo"<input type='submit' name='drop' value='".'Drop'."'".confirm(sprintf('Drop %s?',DB)).">\n";elseif(!$_POST["add"]&&$_GET["db"]=="")echo
icon("plus","add[0]","+",'Add next')."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["call"])){$ba=($_GET["name"]?:$_GET["call"]);$wj=(isset($_GET["callf"])?"FUNCTION":"PROCEDURE");$rj=routine($_GET["call"],$wj);$of=(idx($rj["returns"],"type")=="trigger");page_header(($of?'Triggers':'Call').": ".h($ba),$j,"#routines","",!$rj,(isset($_GET["callf"])?"":doc_link(array('sql'=>"call.html",))));$Me=array();$Nh=array();foreach($rj["fields"]as$r=>$k){if(substr($k["inout"],-3)=="OUT"&&JUSH=='sql')$Nh[$r]="@".idf_escape($k["field"])." AS ".idf_escape($k["field"]);if(!$k["inout"]||preg_match('~^(IN|OUTPUT)~',$k["inout"]))$Me[]=$r;}if(!$j&&$_POST){$Xa=array();foreach($rj["fields"]as$w=>$k){$X="";if(in_array($w,$Me)){$X=process_input($k);if($X===false)$X="''";if(isset($Nh[$w]))connection()->query("SET @".idf_escape($k["field"])." = $X");}if(isset($Nh[$w]))$Xa[]="@".idf_escape($k["field"]);elseif(in_array($w,$Me))$Xa[]=$X;}$ya=implode(", ",$Xa);$H=(isset($_GET["callf"])||JUSH!="mssql"?(isset($_GET["callf"])?"SELECT ":"CALL ").(idx($rj["returns"],"type")=="record"?"* FROM ":"").table($ba)."($ya)":"EXEC ".table($ba).($ya!=""?" $ya":""));$qk=microtime(true);$I=connection()->multi_query($H);$ma=connection()->affected_rows;echo
adminer()->selectQuery($H,$qk,!$I);if(!$I)echo"<p class='error'>".adminer()->error()."\n";else{$f=connect();if($f)$f->select_db(DB);do{$I=connection()->store_result();if(is_object($I))print_select_result($I,$f);else
echo"<p class='message'>".lang_format(array('Routine has been called, %d row affected.','Routine has been called, %d rows affected.'),$ma)." <span class='time'>".@date("H:i:s")."</span>\n";}while(connection()->next_result());if($Nh)print_select_result(connection()->query("SELECT ".implode(", ",$Nh)));}}if($of){$zl=driver()->routineTriggers($_GET["call"]);if($zl){echo"<table>\n";foreach($zl
as$K){$z=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($K["ns"]),ME);echo"<tr><th>".h($K["trigger"])."<td><a href='".h($z."table=".url_escape($K["table"])."#triggers")."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>"."<td class='hover'><a href='".h($z."trigger=".url_escape($K["table"])."&name=".url_escape($K["trigger"]))."'>".'Alter'."</a>\n";}echo"</table>\n";}}else{echo'<form action="" method="post">
';if($Me){echo"<table class='layout'>\n";foreach($Me
as$w){$k=$rj["fields"][$w];$B=$k["field"];echo"<tr><th>".adminer()->fieldName($k);$Y=idx($_POST["fields"],$B);if($Y!=""){if($k["type"]=="set")$Y=implode(",",$Y);}input($k,$Y,idx($_POST["function"],$B,""));echo"\n";}echo"</table>\n";}echo'<p>
<input type=\'submit\' value=\'Call\'>
',input_token(),'</form>
';}echo'
',adminer()->commentValue($wj,$rj['comment']);}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$B=$_GET["name"];$K=$_POST;if($_POST&&!$j&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$Xk=array();foreach($K["source"]as$w=>$X)$Xk[$w]=$K["target"][$w];$K["target"]=$Xk;}$Oa=false;if(JUSH=="sqlite")$I=recreate_table($a,$a,array(),array(),array(" $B"=>($K["drop"]?"":" ".format_foreign_key($K))));else{$Ab=$K["constraint"];$ta="ALTER TABLE ".table($a);$Ec="$ta DROP ".(JUSH=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($B?:'');if($K["drop"])$I=queries($Ec);else{$ha=" ADD".format_foreign_key($K,$Ab);if($B=="")$I=queries($ta.$ha);elseif(JUSH=="pgsql"||(JUSH=="sql"&&$Ab!=$B))$I=queries("$Ec,$ha");else{$Oa=support("transaction_ddl")&&driver()->begin();$I=($Oa||idx(foreign_keys($a),$B)?queries($Ec):true)&&queries($ta.$ha)&&(!$Oa||driver()->commit());}}}queries_redirect(ME."table=".url_escape($a),($K["drop"]?'Foreign key has been dropped.':($B!=""?'Foreign key has been altered.':'Foreign key has been created.')),$I);if($Oa)driver()->rollback();if(!$K["drop"])$j='Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.';}$ah=false;if(!$_POST&&$B!=""){$Od=foreign_keys($a);$K=idx($Od,$B,array());$ah=!$K;}$Mg=str_replace("{table}",$a,adminer()->namePattern("FOREIGN"));$Pg="^".str_replace('\{columns\}','.*',preg_quote($Mg))."$";page_header(($B!=""?'Alter foreign key':'Create foreign key'),$j,array("table"=>$a),h($B!=""?$B:$a),$ah,doc_link(array('sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"foreign-keys/",)));if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=array();else$K["source"][]="";}elseif($B!=""){$K["source"][]="";$K["constraint"]=$B;}else{$K["table"]=$a;$K["source"]=array("");$K["constraint"]=str_replace("{columns}","",$Mg);}echo'
<form action="" method="post">
';$hk=array_keys(fields($a));if($K["db"]!="")connection()->select_db($K["db"]);$Jh=null;if($K["ns"]!=""){$Jh=get_schema();set_schema($K["ns"]);}$Yi=array_keys(array_filter(table_status('',true),function(array$S){return!$S["dependent"]&&fk_support($S);}));$Xk=array_keys(fields(in_array($K["table"],$Yi)?$K["table"]:reset($Yi)));$b=on('change','foreignChange');echo"<p><label>".'Target table'.": ".html_select("table",$Yi,$K["table"],$b)."</label>\n";if(JUSH!="sqlite"){$Kj=($K["db"]!=""?$K["db"]:DB);$bc=array();foreach(adminer()->databases()as$h){if(!driver()->isSystem($h)||$h==$Kj||$h==DB)$bc[]=$h;}echo"<label>".'DB'.": ".html_select("db",$bc,$Kj,$b)."</label>";}echo
input_hidden("change-js"),'<noscript><p><input type=\'submit\' name=\'change\' value=\'Change\'></noscript>
<table',on('change','foreignName',$Pg,$Mg),'>
<thead><tr><th id="label-source">Source<th id="label-target">Target<tbody>
';$rf=0;foreach($K["source"]as$w=>$X){echo"<tr>","<td>".html_select("source[".(+$w)."]",array(-1=>"")+$hk,$X,($rf==count($K["source"])-1?on('change','foreignAddRow'):""),"label-source"),"<td>".html_select("target[".(+$w)."]",$Xk,idx($K["target"],$w),"","label-target");$rf++;}echo'</table>
<p>
<label>ON DELETE: ',html_select("on_delete",array(-1=>"")+explode("|",driver()->onActions),$K["on_delete"]),'</label>
<label>ON UPDATE: ',html_select("on_update",array(-1=>"")+explode("|",driver()->onActions),$K["on_update"]),'</label>
',(support("deferrable")?html_select("deferrable",array('NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'),$K["deferrable"]):''),'<p>
<input type=\'submit\' value=\'Save\'>
<noscript><p><input type=\'submit\' name=\'add\' value=\'Add column\'></noscript>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';if(JUSH!="sqlite"){print_fieldset("options",'Options',$K["constraint"]!=$B&&!preg_match("($Pg)",$K["constraint"]));echo"<label>".'Name'.": <input name='constraint' value='".h($K["constraint"])."' autocapitalize='off'></label>\n","</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$Kh="VIEW";if(JUSH=="pgsql"&&$a!=""){$uk=table_status1($a);$Kh=strtoupper($uk["Engine"]);}if($_POST&&!$j){$B=trim($K["name"]);$_a=" AS\n$K[select]";$_=ME."table=".url_escape($B);$qg='View has been altered.';$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$B&&JUSH!="sqlite"&&$U=="VIEW"&&$Kh=="VIEW")query_redirect((JUSH=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($B).$_a,$_,$qg);else{$bl="adminer_".uniqid();drop_create("DROP $Kh ".table($a),"CREATE $U ".table($B).$_a,"DROP $U ".table($B),"CREATE $U ".table($bl).$_a,"DROP $U ".table($bl),($_POST["drop"]?substr(ME,0,-1):$_),'View has been dropped.',$qg,'View has been created.',$a,$B);}}$ah=false;if(!$_POST&&$a!=""){$K=view($a);$ah=!$K["select"];$K["name"]=$a;$K["materialized"]=($Kh!="VIEW");if(!$j)$j=adminer()->error();}page_header(($a!=""?'Alter view':'Create view'),$j,array("table"=>$a),h($a),$ah,doc_link(array('sql'=>"create-view.html",)));echo'
<form action="" method="post">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',(support("materializedview")?" ".checkbox("materialized",1,$K["materialized"],'Materialized view'):""),'<p>';textarea("select",$K["select"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["event"])){$aa=$_GET["event"];$ef=array("YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND");$vk=array("ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE");$K=$_POST;if($_POST&&!$j){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($aa),substr(ME,0,-1),'Event has been dropped.');elseif(in_array($K["INTERVAL_FIELD"],$ef)&&isset($vk[$K["STATUS"]])){$_j="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($aa!=""?'Event has been altered.':'Event has been created.'),queries(($aa!=""?"ALTER EVENT ".idf_escape($aa).$_j.($aa!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$_j)."\n".$vk[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}$ah=false;if(!$K&&$aa!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($aa));$ah=!$L;$K=reset($L);}page_header(($aa!=""?'Alter event'.": ".h($aa):'Create event'),$j,"#events","",$ah,doc_link(array('sql'=>"create-event.html")));echo'
<form action="" method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="',h($K["EVENT_NAME"]),'" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="',h("$K[EXECUTE_AT]$K[STARTS]"),'">
<tr><th title="datetime">End<td><input name="ENDS" value="',h($K["ENDS"]),'">
<tr><th>Every
<td><input type="number" name="INTERVAL_VALUE" value="',h($K["INTERVAL_VALUE"]),'" class="size"> ',html_select("INTERVAL_FIELD",$ef,$K["INTERVAL_FIELD"]),'<tr><th>Status<td>',html_select("STATUS",$vk,$K["STATUS"]),'<tr><th>Comment<td><input name="EVENT_COMMENT" value="',h($K["EVENT_COMMENT"]),'" data-maxlength="64">
<tr><th><td>',checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",'On completion preserve'),'</table>
<p>';textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($aa!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$aa)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["procedure"])){$ba=($_GET["name"]?:$_GET["procedure"]);$rj=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])&&!$j){foreach($K["fields"]as$w=>$k){if($k["field"]=="")unset($K["fields"][$w]);}$ph=routine($_GET["procedure"],$rj);$nh=($ph?routine_id($ba,$ph):"");$Tg=routine_id($K["name"],$K);$Kb=create_routine($rj,$K);$_=substr(ME,0,-1);$qg='Routine has been altered.';if(!$_POST["drop"]&&$nh==$Tg&&connection()->flavor!="mysql")queries_redirect($_,$qg,queries(substr_replace($Kb,(JUSH=="mssql"?' OR ALTER':' OR REPLACE'),6,0)));else{$bl="adminer_".uniqid();drop_create("DROP $rj $nh",$Kb,"DROP $rj $Tg",create_routine($rj,array("name"=>$bl)+$K),"DROP $rj ".routine_id($bl,$K),$_,'Routine has been dropped.',$qg,'Routine has been created.',$ba,$K["name"]);}}$ah=false;if(!$_POST&&$ba!=""){$K=routine($_GET["procedure"],$rj);$ah=!$K;$K["name"]=$ba;}$uj=strtolower($rj);page_header(($ba!=""?(isset($_GET["function"])?'Alter function':'Alter procedure').": ".h($ba):(isset($_GET["function"])?'Create function':'Create procedure')),$j,"#routines","",$ah,doc_link(array('sql'=>"create-procedure.html",'mariadb'=>"create-$uj/",)));if(!$_POST&&$ba=="")$K["language"]="sql";$rb=(JUSH=="sql"?flat_collations():array());$sj=routine_languages();echo($rb?"<datalist id='collations'>".optionlist($rb)."</datalist>":""),'
<form action="" method="post" id="form">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',($sj?"<label>".'Language'.": ".html_select("language",array_keys($sj),$K["language"],on('change','routineLanguage',$sj))."</label>\n":""),'<input type=\'submit\' value=\'Save\'>
<div class="scrollable">
<table id="edit-fields" class="nowrap">
';edit_fields($K["fields"],$rb,$rj);if(isset($_GET["function"])){echo"<tr><td>".'Return type';edit_type("returns",(array)$K["returns"],$rb,array(),(JUSH=="pgsql"?array("void","trigger"):array()));}echo'</table>
',script("editFields();"),'</div>
<p>';textarea("definition",$K["definition"],20,80,($sj[$K["language"]]?:JUSH));echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($ba!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$ba)),'>
';$vj=routine_options($rj);if($vj){$Ah=false;foreach($vj
as$w=>$gm){$i=($gm?reset($gm):"");$K["options"][$w]=idx($K["options"],$w,$i);if($K["options"][$w]!=$i)$Ah=true;}print_fieldset("options",'Options',$Ah);echo"<table class='layout'>\n";foreach($vj
as$w=>$gm){$zf="label-option-$w";$jl=str_replace("_"," ",$w);$N=array();foreach($gm
as$Y)$N[$Y]=(strpos($Y,"$jl ")===0?substr($Y,strlen($jl)+1):$Y);echo"<tr><th id='$zf'>$jl<td>".($N?html_select("options[$w]",$N,$K["options"][$w],"",$zf):"<input name='options[$w]' value='".h($K["options"][$w])."' aria-labelledby='$zf' autocapitalize='off'>")."\n";}echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["check"])){$a=$_GET["check"];$B="$_GET[name]";$K=$_POST;if($K&&!$j){$_=ME."table=".url_escape($a);$tg='Check has been dropped.';$rg='Check has been altered.';$sg='Check has been created.';if(JUSH=="sqlite"&&!(min_version(3.53)&&($B==""||idx(driver()->checkConstraints($a),$B)!=$B))){$ha=($K["drop"]?"":($K["name"]!=""?"CONSTRAINT ".idf_escape($K["name"])." ":"")."CHECK ($K[clause])");queries_redirect($_,($K["drop"]?$tg:($B!=""?$rg:$sg)),recreate_table($a,$a,array(),array(),array(),"",array(),"$B",$ha));}else{$ta="ALTER TABLE ".table($a);$eb=" CHECK ($K[clause])";$bl="adminer_".uniqid();drop_create("$ta DROP CONSTRAINT ".idf_escape($B),"$ta ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"").$eb,"$ta DROP CONSTRAINT ".idf_escape($K["name"]),"$ta ADD CONSTRAINT ".idf_escape($bl).$eb,"$ta DROP CONSTRAINT ".idf_escape($bl),$_,$tg,$rg,$sg,$B,$K["name"]);}}$ah=false;if(!$K){$hb=driver()->checkConstraints($a);$ah=($B!=""&&!$hb[$B]);$K=array("name"=>($B==""?object_name("CHECK",$a,array()):($hb[$B]!=$B?$B:"")),"clause"=>$hb[$B]);}page_header(($B!=""?'Alter check':'Create check'),$j,array("table"=>$a),h($B!=""?$B:$a),$ah,doc_link(array('sql'=>"create-table-check-constraints.html",'mariadb'=>"constraint/",)));echo'
<form action="" method="post">
<p><label>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off"></label>
<p>';textarea("clause",$K["clause"]);echo'<p><input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$B="$_GET[name]";$yl=trigger_options();$K=trigger($B,$a);$ah=($B!=""&&!$K);$Mg=str_replace("{table}",$a,adminer()->namePattern("TRIGGER"));$K+=array("Trigger"=>strtr($Mg,array("{timing}"=>"b","{event}"=>"i","{columns}"=>"","{type}"=>"row")));if($_POST){if(!$j&&in_array($_POST["Timing"],$yl["Timing"])&&in_array($_POST["Event"],$yl["Event"])&&in_array($_POST["Type"],$yl["Type"])){$rh=" ON ".table($a);$Ec="DROP TRIGGER ".idf_escape($B).(JUSH=="pgsql"?$rh:"");$_=ME."table=".url_escape($a);if($_POST["drop"])query_redirect($Ec,$_,'Trigger has been dropped.');else{if($B!="")queries($Ec);queries_redirect($_,($B!=""?'Trigger has been altered.':'Trigger has been created.'),queries(create_trigger($rh,$_POST)));if($B!="")queries(create_trigger($rh,$K+array("Type"=>reset($yl["Type"]))));}}$K=$_POST;}page_header(($B!=""?'Alter trigger':'Create trigger'),$j,array("table"=>$a),h($B!=""?$B:$a),$ah,doc_link(array('sql'=>"create-trigger.html",)));$Pg=strtr(preg_quote($Mg),array('\{timing\}'=>'[abi]','\{event\}'=>'[iud]*','\{columns\}'=>'.*','\{type\}'=>'(row|statement)'));$xl=on('change','triggerChange',"^$Pg$",$Mg);$gh=on('input','triggerChange',"^$Pg$",$Mg);echo'
<form action="" method="post" id="form">
<table class="layout">
<tr><th>Time
<td>',html_select("Timing",$yl["Timing"],$K["Timing"],$xl),'<tr><th>Event<td>',html_select("Event",$yl["Event"],$K["Event"],$xl),(in_array("UPDATE OF",$yl["Event"])?" <input name='Of' value='".h($K["Of"])."' class='hidden'$gh>":""),'<tr><th>Type<td>',html_select("Type",$yl["Type"],$K["Type"],$xl),'<tr><th>Name<td><input name="Trigger" value="',h($K["Trigger"]),'" data-maxlength="64" autocapitalize="off">
</table>
',script("fire(qs('#form')['Timing'], 'change');"),'<p>';textarea("Statement",$K["Statement"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["user"])){function
grant($be,array$Ii,$d,$rh){if(!$Ii)return
true;if($Ii==array("ALL PRIVILEGES","GRANT OPTION"))return($be=="GRANT"?queries("$be ALL PRIVILEGES$rh WITH GRANT OPTION"):queries("$be ALL PRIVILEGES$rh")&&queries("$be GRANT OPTION$rh"));return
queries("$be ".preg_replace('~(GRANT OPTION)\([^)]*\)~','\1',implode("$d, ",$Ii).$d).$rh);}$da=$_GET["user"];$Ii=array(""=>array("All privileges"=>""));foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$Eb)$Ii[$Eb=="File access on server"?"Server Admin":$Eb][$K["Privilege"]]=$K["Comment"];}unset($Ii["Server Admin"]["Usage"]);foreach($Ii["Tables"]as$w=>$X)unset($Ii["Databases"][$w]);$Sg=array();if($_POST){foreach($_POST["objects"]as$w=>$X)$Sg[$X]=(array)$Sg[$X]+idx($_POST["grants"],$w,array());}$ce=array();$I=(isset($_GET["host"])?connection()->query("SHOW GRANTS FOR ".q($da)."@".q($_GET["host"])):null);$ah=(isset($_GET["host"])&&!$I);if($I){while($K=$I->fetch_row()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$A)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$A[1],$Yf,PREG_SET_ORDER)){foreach($Yf
as$X){if($X[1]!="USAGE")$ce["$A[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$ce["$A[2]$X[2]"]["GRANT OPTION"]=true;}}}}if($_POST&&!$j){$qh=(isset($_GET["host"])?q($da)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $qh",ME."privileges=",'User has been dropped.');else{$Wg=q($_POST["user"])."@".q($_POST["host"]);$fi=$_POST["pass"];$Mb=false;$I=true;if($qh!=$Wg){$Mb=queries("CREATE USER $Wg IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($fi));$I=$Mb;}elseif($fi!="")$I=queries("SET PASSWORD FOR $Wg = ".(min_version(8,99)||$_POST["hashed"]?q($fi):"PASSWORD(".q($fi).")"));if($I){$nj=array();foreach($Sg
as$eh=>$be){if(isset($_GET["grant"]))$be=array_filter($be);$be=array_keys($be);if(isset($_GET["grant"]))$nj=array_diff(array_keys(array_filter($Sg[$eh],'strlen')),$be);elseif($qh==$Wg){$mh=array_keys((array)$ce[$eh]);$nj=array_diff($mh,$be);$be=array_diff($be,$mh);unset($ce[$eh]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$eh,$A)&&(!grant("REVOKE",$nj,$A[2]," ON $A[1] FROM $Wg")||!grant("GRANT",$be,$A[2]," ON $A[1] TO $Wg"))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($qh!=$Wg)queries("DROP USER $qh");elseif(!isset($_GET["grant"])){foreach($ce
as$eh=>$nj){if(preg_match('~^(.+)(\(.*\))?$~U',$eh,$A))grant("REVOKE",array_keys($nj),$A[2]," ON $A[1] FROM $Wg");}}}if($I&&!Queries::$queries)redirect(ME."privileges=");queries_redirect(ME."privileges=",(isset($_GET["host"])?'User has been altered.':'User has been created.'),$I);if($Mb)connection()->query("DROP USER $Wg");}}page_header((isset($_GET["host"])?'Username'.": ".h("$da@$_GET[host]"):'Create user'),$j,array("privileges"=>array('','Privileges')),"",$ah,doc_link(array('sql'=>"grant.html",'mariadb'=>"grant")));$K=$_POST;if($K)$ce=$Sg;else{$K=$_GET+array("host"=>get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)"));$ce[(DB==""||$ce?"":idf_escape(addcslashes(DB,"%_\\"))).".*"]=array();}echo'<form action="" method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="',h($K["host"]),'" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="',h($K["user"]),'" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="',h($K["pass"]),'" autocomplete="new-password">
',($K["hashed"]?"":script("typePassword(qs('#pass'));")),(min_version(8,99)?"":checkbox("hashed",1,$K["hashed"],'Hashed',on('click','hashedClick'))),'</table>

',"<table class='odds'>\n","<thead><tr><th colspan='2'>".'Privileges';$r=0;foreach($ce
as$eh=>$be){echo'<th>'.($eh!="*.*"?"<input name='objects[$r]' value='".h($eh)."' size='10' autocapitalize='off'>":input_hidden("objects[$r]","*.*")."*.*");$r++;}echo"<tbody>\n";foreach(array(""=>"","Server Admin"=>'Server',"Databases"=>'Database',"Tables"=>'Table',"Procedures"=>'Routine',)as$Eb=>$lc){foreach((array)$Ii[$Eb]as$Hi=>$vb){echo"<tr><td".($lc?">$lc<td":" colspan='2'").' lang="en" title="'.h($vb).'">'.h($Hi);$r=0;foreach($ce
as$eh=>$be){$B="'grants[$r][".h(strtoupper($Hi))."]'";$Y=$be[strtoupper($Hi)];if($Eb=="Server Admin"&&$eh!=(isset($ce["*.*"])?"*.*":".*"))echo"<td>";elseif(isset($_GET["grant"]))echo"<td><select name=$B><option><option value='1'".($Y?" selected":"").">".'Grant'."<option value='0'".($Y=="0"?" selected":"").">".'Revoke'."</select>";else
echo"<td align='center'><label class='block'>","<input type='checkbox' name=$B value='1'".($Y?" checked":"").($Hi=="All privileges"?" id='grants-$r-all'":($Hi=="Grant option"?"":on('click','grantsClick',"grants-$r-all"))).">","</label>";$r++;}}}echo"</table>\n",'<p>
<input type=\'submit\' value=\'Save\'>
';if(isset($_GET["host"]))echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',"$da@$_GET[host]")),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST&&!$j){$yf=0;foreach((array)$_POST["kill"]as$X){if(adminer()->killProcess($X))$yf++;}queries_redirect(ME."processlist=",lang_format(array('%d process has been killed.','%d processes have been killed.'),$yf),$yf||!$_POST["kill"]);}}page_header('Process list',$j);echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds"',on('click','tableClick').on('dblclick','tableClick'),'>
';$r=-1;foreach(adminer()->processList()as$r=>$K){if(!$r){echo"<thead><tr lang='en'>".(support("kill")?"<td class='hover'>":"");foreach($K
as$w=>$X)echo"<th>$w".doc_link(array('sql'=>"show-processlist.html#processlist_".strtolower($w),));echo"<tbody>\n";}echo"<tr>".(support("kill")?"<td class='hover'>".checkbox("kill[]",$K[JUSH=="sql"?"Id":"pid"],0):"");foreach($K
as$w=>$X)echo"<td>".($X!=""&&((JUSH=="sql"&&$w=="Info"&&preg_match("~Query|Killed~",$K["Command"]))||(JUSH=="pgsql"&&$w=="query")||(JUSH=="oracle"&&$w=="sql_text"))?"<code class='sql jush-".JUSH."' data-full='".h($X)."'>".shorten_utf8($X,100,"</code>").' <a href="'.h(($K["db"]!=""?preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($K["db"])."&":ME)."sql=".url_escape($X)).'">'.'Clone'.'</a>'.' '.copy_icon():h($X));echo"\n";}echo'</table>
</div>
<p>
',script("copyCode(qsl('table'));");if(support("kill"))echo
format_number($r+1)."/".sprintf('%d in total',max_connections()),"<p><input type='submit' value='".'Kill'."'>\n";echo
input_token(),'</form>
',script("tableCheck();");}elseif($_GET["select"]!=""){$a=$_GET["select"];$S=table_status1($a);$v=indexes($a);$l=fields($a);$Od=column_foreign_keys($a);$lh=$S["Oid"];$pj=array();$d=array();$Fj=array();$Ch=array();$el=null;foreach($l
as$w=>$k){$B=adminer()->fieldName($k);$Og=html_entity_decode(strip_tags($B),ENT_QUOTES);if(isset($k["privileges"]["select"])&&$B!=""){$d[$w]=$Og;if(is_shortable($k))$el=adminer()->selectLengthProcess();}if(isset($k["privileges"]["where"])&&$B!="")$Fj[$w]=$Og;if(isset($k["privileges"]["order"])&&$B!="")$Ch[$w]=$Og;$pj+=$k["privileges"];}list($N,$q)=adminer()->selectColumnsProcess($d,$v);$N=array_unique($N);$q=array_unique($q);$lf=count($q)<count($N);$Z=adminer()->selectSearchProcess($l,$v,$S);$D=adminer()->selectOrderProcess($l,$v);$y=adminer()->selectLimitProcess();if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$Jl=>$K){$_a=convert_field($l[key($K)]);$N=array($_a?:idf_escape(key($K)));$Z[]=where_check(bracket_escape($Jl,true),$l);$J=driver()->select($a,$N,$Z,$N);if($J)echo
first($J->fetch_row());}exit;}$Ei=$Ll=array();foreach($v
as$u){if($u["type"]=="PRIMARY"){$Ei=array_flip($u["columns"]);$Ll=($N?$Ei:array());foreach($Ll
as$w=>$X){if(in_array(idf_escape($w),$N))unset($Ll[$w]);}break;}}if($lh&&!$Ei){$Ei=$Ll=array($lh=>0);$v[]=array("type"=>"PRIMARY","columns"=>array($lh));}if($_POST&&!$j){$rm=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$hb=array();foreach($_POST["check"]as$eb)$hb[]=where_check($eb,$l);$rm[]="((".implode(") OR (",$hb)."))";}$tm=$rm;$rm=($rm?"\nWHERE ".implode(" AND ",$rm):"");if($_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers($a);adminer()->dumpTable($a,"");$Ij=($N?:array("*"));$Gb=convert_fields($d,$l,$N);if($Gb)$Ij[]=substr($Gb,2);$H="";if(is_array($_POST["check"])&&!$Ei){$Td=implode(", ",$Ij)."\nFROM ".table($a);$fe=($q&&$lf?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):"");$Gl=array();foreach($_POST["check"]as$X)$Gl[]="(SELECT".limit($Td,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l).$fe,1).")";$H=implode(" UNION ALL ",$Gl);}adminer()->dumpData($a,"table",$H,$Ij,$tm,($lf?$q:array()),$D);adminer()->dumpFooter();exit;}if(!adminer()->selectEmailProcess($Z,$Od)){if($_POST["save"]||$_POST["delete"]){$I=true;$ma=0;$Oa=false;$P=array();if(!$_POST["delete"]){foreach($l
as$B=>$X){$t=bracket_escape($B);if(isset($_POST["fields"][$t])||$_FILES["fields-$t"]){$X=process_input($l[$B]);if($X!==null&&($_POST["clone"]||$X!==false))$P[idf_escape($B)]=($X!==false?$X:idf_escape($B));}}}if($_POST["delete"]||$P){$H=($_POST["clone"]?"INTO ".table($a)." (".implode(", ",array_keys($P)).")\nSELECT ".implode(", ",$P)."\nFROM ".table($a):"");if($_POST["all"]||($Ei&&is_array($_POST["check"]))||$lf){$I=($_POST["delete"]?driver()->delete($a,$rm):($_POST["clone"]?queries("INSERT $H$rm".driver()->insertReturning($a)):driver()->update($a,$P,$rm)));$ma=connection()->affected_rows;if(is_object($I))$ma+=$I->num_rows;}else{$Oa=count((array)$_POST["check"])>1&&driver()->begin();foreach((array)$_POST["check"]as$X){$qm="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l);$I=($_POST["delete"]?driver()->delete($a,$qm,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$qm)):driver()->update($a,$P,$qm,1)));if(!$I)break;$ma+=connection()->affected_rows;}if($Oa&&$I&&!driver()->commit())$I=false;}}$qg=lang_format(array('%d item has been affected.','%d items have been affected.'),$ma);if($_POST["clone"]&&$I&&$ma==1){$Ef=last_id($I);if($Ef)$qg=sprintf('Item%s has been inserted.'," $Ef");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page|next":""),$qg,$I);if($Oa)driver()->rollback();if(!$_POST["delete"]){$wi=(array)$_POST["fields"];edit_form($a,array_intersect_key($l,$wi),$wi,!$_POST["clone"],$j);page_footer();exit;}}elseif(!$_POST["import"]){$I=true;$ma=0;$Oa=count((array)$_POST["val"])>1&&driver()->begin();foreach((array)$_POST["val"]as$Jl=>$K){$P=array();foreach($K
as$w=>$X){$w=bracket_escape($w,true);$P[idf_escape($w)]=(preg_match('~char|text~',$l[$w]["type"])||$X!=""?adminer()->processInput($l[$w],$X):"NULL");}$I=driver()->update($a,$P," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check(bracket_escape($Jl,true),$l),($lf||$Ei?0:1)," ");if(!$I)break;$ma+=connection()->affected_rows;}if($Oa)$I=$I&&driver()->commit();queries_redirect(remove_from_uri(),lang_format(array('%d item has been affected.','%d items have been affected.'),$ma),$I);if($Oa)driver()->rollback();}else{save_settings(array("format"=>$_POST["separator"]),"adminer_import");$Bd=get_file("csv_file",true);if(!is_string($Bd))$j=upload_error($Bd);elseif(!preg_match('~~u',$Bd))$j='File must be in UTF-8 encoding.';else{$sb=array_keys($l);$Nj=($_POST["separator"]=="csv"?",":($_POST["separator"]=="tsv"?"\t":";"));$Qb=parse_csv($Bd,$Nj);$ma=count($Qb);driver()->begin();$L=array();foreach($Qb
as$w=>$gm){if(!$w&&!array_diff($gm,$sb)){$sb=$gm;$ma--;}else{$P=array();foreach($gm
as$r=>$ob)$P[idf_escape($sb[$r])]=($ob==""&&$l[$sb[$r]]["null"]?"NULL":q(csv_value($ob)));$L[]=$P;}}$I=(!$L||driver()->insertUpdate($a,$L,$Ei));if($I)driver()->commit();queries_redirect(remove_from_uri("page|next"),lang_format(array('%d row has been imported.','%d rows have been imported.'),$ma),$I);driver()->rollback();}}}}$Mk=adminer()->tableName($S);if(is_ajax()){page_headers();ob_start();}else
page_header('Select'.": $Mk",$j,array(),"",(!$l&&support("table")),($l?doc_link(array(JUSH=>driver()->tableHelp($a,is_view($S)))):""));$P=null;if(isset($pj["insert"])||!support("table")){$P="";foreach((array)$_GET["where"]as$X){$Y=$X["val"];if(is_array($Y))$Y=(count($Y)==1&&preg_match('~^val-(.*)~s',reset($Y),$A)?$A[1]:"");if($X["col"]!=""&&$Y!=""&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$Y)))))$P
.="&set[".url_escape(bracket_escape($X["col"]))."]=".url_escape($Y);}}adminer()->selectLinks($S,$P);if(!$d&&support("table"))echo"<p class='error'>".'Unable to select the table.'."\n";else{echo"<form action='' id='form'>\n","<div hidden>";hidden_fields_get();echo(DB!=""?input_hidden("db",DB).(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""):""),input_hidden("select",$a),"</div>\n";adminer()->selectColumnsPrint($N,$d);adminer()->selectSearchPrint($Z,$Fj,$v,$S);adminer()->selectOrderPrint($D,$Ch,$v);adminer()->selectLimitPrint($y);if($el!==null)adminer()->selectLengthPrint($el);adminer()->selectActionPrint($v);echo"</form>\n";foreach((array)$_GET["where"]as$X){if($X["op"]=="SQL"&&!in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"))){echo"<p class='error'>".'Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.'."\n";page_footer();exit;}}$E=$_GET["page"];$Rd=null;if($E=="last"){$Rd=get_val(count_rows($a,$Z,$lf,$q));$E=floor(max(0,intval($Rd)-1)/$y);}$Hj=$N;$ee=$q;if(!$Hj){$Hj[]="*";$Gb=convert_fields($d,$l,$N);if($Gb)$Hj[]=substr($Gb,2);}foreach($N
as$w=>$X){$k=$l[idf_unescape($X)];if($k&&($_a=convert_field($k)))$Hj[$w]="$_a AS $X";}if(JUSH=="pgsql"||JUSH=="mssql"){foreach((array)$_GET["columns"]as$w=>$X){if(isset($Hj[$w])&&$X["fun"])$Hj[$w].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$lf&&$Ll){foreach($Ll
as$w=>$X){$Hj[]=idf_escape($w);if($ee)$ee[]=idf_escape($w);}}$I=driver()->select($a,$Hj,$Z,$ee,$D,$y,$E,true);if(!is_object($I))echo"<p class='error'>".(adminer()->error()?:'Unknown error.')."\n";else{if(JUSH=="mssql"&&$E)$I->seek($y*$E);$Rc=array();$L=array();while($K=$I->fetch_assoc()){if($E&&JUSH=="oracle")unset($K["RNUM"]);$L[]=$K;}$pe=($y&&(support("cursor")?$_GET["next"]!="":count($L)>=$y));if(is_ajax()&&$pe)header("X-Next-Page: ".pagination_href($E+1));if($_GET["modify"]&&$L){$hg=max_input_vars(count($L[0])+1,20);echo($hg&&count($L)>$hg?"<p class='error'>".max_input_vars_error()."\n":"");}echo"<form action='' method='post' enctype='multipart/form-data'".on_upload_progress($Rl).">\n";if($_GET["page"]!="last"&&$y&&$q&&$lf&&JUSH=="sql")$Rd=get_val(" SELECT FOUND_ROWS()");if(!$L)echo"<p class='message'>".'No rows.'."\n";else{$Ka=adminer()->backwardKeys($a,$Mk);$mj=array();reset($N);foreach($L[0]as$w=>$X){if(!isset($Ll[$w])){$X=idx($_GET["columns"],key($N))?:array();$mj[$w]=array("fun"=>$X["fun"],"col"=>($N?$X["col"]:$w));next($N);}}echo"<div class='scrollable'>","<table id='table' class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown').">\n","<thead><tr>".(!$q&&$N?"":"<td class='hover check sticky'><input type='checkbox' id='all-page' class='jsonly' title='".'All rows on this page'."'".on('click','formCheck','^check').">");$Qg=array();$Vi=1;foreach($mj
as$w=>$X){$k=$l[$X["col"]];$B=($k?adminer()->fieldName($k,$Vi):($X["fun"]?($X["col"]!=""?h($X["col"]):"*"):h($w)));if($B!=""){$Vi++;$Qg[$w]=$B;$c=idf_escape($w);$De=remove_from_uri('(order|desc)[^=]*|page|next').'&order[0]='.url_escape($w);$lc="&desc[0]=1";$ek=preg_replace('~ DESC( NULLS LAST)?$~','',$D[0]);$gk=($ek==$c||$ek==$w);echo"<th id='th[".h(bracket_escape($w))."]'".($gk?" aria-sort='".($ek==$D[0]?"ascending":"descending")."'":"").">";$Xd=apply_sql_function(h($X["fun"]),$B);$fk=isset($k["privileges"]["order"])||$X["fun"];echo($fk?"<a href='".h($De.($gk&&$ek==$D[0]?$lc:''))."'>$Xd</a>":$Xd),doc_link(array(JUSH=>$k["doc"]));$pg=($fk?"<a href='".h($De.$lc)."' title='".'descending'."' class='text'> ↓</a>":'');if(!$X["fun"]&&isset($k["privileges"]["where"]))$pg
.="<a href='#fieldset-search' title='".'Search'."' class='text jsonly'".on('click','selectSearch',$w)."> =</a>";echo($pg?"<span class='column'>$pg</span>":"");}}$Lf=array();if($_GET["modify"]){foreach($L
as$K){foreach($K
as$w=>$X)$Lf[$w]=max($Lf[$w],min(40,utf8_length((string)$X)));}}$ze=array();$ye=array();foreach((array)$_GET["where"]as$X){$X+=array("col"=>"","op"=>"","val"=>"");$ob=$X["col"];$Ej=$X["val"];if(!is_array($Ej)&&($Ej!=""||preg_match('~NULL$~',$X["op"]))&&(!$X["op"]||in_array($X["op"],adminer()->operators($S)))){$Nf=strtr(preg_quote($Ej),array("%"=>".*?","_"=>"."));$li=array("LIKE %%"=>$Nf,"ILIKE %%"=>$Nf,"REGEXP"=>$Ej)+(JUSH=="pgsql"?array("~"=>$Ej,"~*"=>$Ej):array())+($ob!=""?array():array("="=>'^'.preg_quote($Ej).'\z',"IN"=>'^(?:'.implode("|",array_map('preg_quote',array_map('trim',explode(",",$Ej)))).')\z',"LIKE"=>"^$Nf\\z","ILIKE"=>"^$Nf\\z","FIND_IN_SET"=>'(?<=^|,)'.preg_quote($Ej).'(?=,|\z)',));foreach(($ob!=""?array($ob=>$l[$ob]):$l)as$B=>$k){if($ob!=""||is_searchable($k,$X)){$th=$X["op"]?:(!preg_match('~'.text_type().'~',$k["type"])?"IN":(preg_match('~%~',$Ej)?"LIKE":"LIKE %%"));if(isset($li[$th])){$kb=preg_match('~^ILIKE|\*$~',$th)||($th!="~"&&preg_match('~^(sql|mssql|sqlite)$~',JUSH));$ze[$B][]="(?".($kb?"i":"").":$li[$th])";}elseif($th=="IS NULL"&&$ob=="")$ye[$B]=true;}}}}echo($Ka?"<th>".'Relations':"")."<tbody>\n";if(is_ajax())ob_end_clean();foreach(adminer()->rowDescriptions($L,$Od)as$Kg=>$K){$Il=unique_array($L[$Kg],$v);if(!$Il){$Il=array();foreach($L[$Kg]as$w=>$X){if(!in_array(idx(idx($mj,$w,array()),"fun"),driver()->grouping))$Il[$w]=$X;}}$Jl="";$r=0;foreach($Il
as$w=>$X){$lj=idx($mj,$w,array());if($N&&$lj&&!isset($l[$lj["col"]]))continue;$Xd=idx($lj,"fun","");$ob=($Xd?$lj["col"]:$w);$k=(array)$l[$ob];$kf=is_blob($k);if(!$Xd&&strlen($X)>64&&driver()->md5(idf_escape($ob),$k)){$Xd="md5";$X=md5($kf?(string)driver()->value($X,$k):$X);}if($Xd){$Jl
.="&fun[$r]=".url_escape($Xd)."&col[$r]=".url_escape($ob).($X!==null?"&val[$r]=".url_escape($X===false?"f":$X):"");$r++;}else$Jl
.="&".($X!==null?"where[".url_escape(bracket_escape($ob))."]=".url_escape($X===false?"f":$X):"null[]=".url_escape($ob));}echo"<tr>".(!$q&&$N?"":"<td class='hover check sticky'>".($lf||information_schema(DB)?"":"<a href='".h(ME."edit=".url_escape($a).$Jl)."' class='edit'>".'edit'."</a> ").checkbox("check[]",substr($Jl,1),in_array(substr($Jl,1),(array)$_POST["check"])));foreach($K
as$w=>$X){if(isset($Qg[$w])){$Xd=$mj[$w]["fun"];$ob=$mj[$w]["col"];$k=(array)$l[$w];if($X!=""&&(!isset($Rc[$w])||$Rc[$w]!=""))$Rc[$w]=(is_mail($X)?$Qg[$w]:"");$z="";if(is_blob($k)&&$X!="")$z=ME.'download='.url_escape($a).'&field='.url_escape($w).$Jl;if(!$z&&$X!==null){foreach((array)$Od[$w]as$n){if(count($Od[$w])==1||end($n["source"])==$w){$z="";foreach($n["source"]as$r=>$hk)$z
.=where_link($r,$n["target"][$r],$L[$Kg][$hk]);$z=($n["db"]!=""?preg_replace('~&db=[^&]*~',"&db=".url_escape($n["db"]),ME):ME).'select='.url_escape($n["table"]).$z;if($n["ns"]!="")$z=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($n["ns"]),$z);if(count($n["source"])==1)break;}}}if($Xd=="count"&&$ob==""){$z=ME."select=".url_escape($a);$r=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$Il))$z
.=where_link($r++,$W["col"],$W["val"],$W["op"]);}foreach($Il
as$uf=>$W){if(idx(idx($mj,$uf,array()),"fun")){$z="";break;}$z
.=where_link($r++,$uf,$W);}}$Ee=select_value($X,$z,$k,$el,($Xd?array():idx($ze,$w,array())));if($X===null&&!$Xd&&isset($ye[$w]))$Ee="<mark>$Ee</mark>";$t=bracket_escape($Jl);$s=h("val[$t][".bracket_escape($w)."]");$yi=idx(idx($_POST["val"],$t),bracket_escape($w));$Ol=idx($k["privileges"],"update")&&!is_identity_always($k);$Mc=$L[$Kg][$w];$Nc=!is_array($K[$w])&&!is_blob($k)&&is_utf8($Mc)&&!$Xd&&!$k["generated"]&&$Ol;$U=($Xd=="min"||$Xd=="max"?$l[$ob]["type"]:$k["type"]);$dl=preg_match('~text|json|lob~',$U);$mf=preg_match(number_type(),$U)||preg_match('~^(avg|ceil|char_length|count|count distinct|floor|len|length|round|sum|time_to_sec)$~',$Xd);echo"<td id='$s'".($mf&&($X===null||is_numeric(strip_tags($Ee))||$U=="money")?" class='number'":"");if(($_GET["modify"]&&$Nc&&$Mc!==null)||$yi!==null){$ke=h($yi!==null?$yi:$Mc);echo">".($dl?"<textarea name='$s' cols='30' rows='".(substr_count($Mc,"\n")+1)."'>\n$ke</textarea>":"<input name='$s' value='$ke' size='$Lf[$w]'>");}else{$Vf=strpos($Ee,"<i>…</i>");$Lc=adminer()->editVal($Mc,$k);echo($Ol?" data-text='".($Vf?2:($dl?1:0))."'".($Nc?"":" data-warning='".'Use the edit link to modify this value.'."'").($Nc&&!$Vf&&html_entity_decode(strip_tags($Ee),ENT_QUOTES,"UTF-8")!==$Lc?" data-value='".h($Lc)."'":""):"").">$Ee";}}}if($Ka)echo"<td>";adminer()->backwardKeysPrint($Ka,$L[$Kg]);echo"</tr>\n";}if(is_ajax())exit;echo"</table>\n","</div>\n";}if(!is_ajax()){$la=get_settings("adminer_import");if($L||$E||$pe){$jd=true;if($_GET["page"]!="last"){if(!$y||(count($L)<$y&&($L||!$E)))$Rd=($E?$E*$y:0)+count($L);elseif(JUSH!="sql"||!$lf){$Rd=($lf?null:found_rows($S,$Z));$jd=!driver()->hasEstimatedRows();if($Rd===null||(!$jd&&$Rd<max(1e4,2*($E+1)*$y))){$Rd=first(slow_query(count_rows($a,$Z,$lf,$q)));$jd=true;}}}if(!support("cursor"))$pe=(($Rd===false?count($L)+1:$Rd-$E*$y)>$y);$Qh=($y&&($pe||$E));if($Qh)echo($pe?'<p><a href="'.h(pagination_href($E+1)).'" class="loadmore"'.on('click','selectLoadMore','Loading…').'>'.'Load more data'.'</a>':''),"\n";echo"<div class='footer'><div>\n";if($Qh){$fg=($Rd===false?$E+($L?(count($L)>=$y?2:1):0):floor(($Rd-1)/$y));echo"<fieldset><legend>".'Page'."</legend>";if(!support("cursor")){echo
pagination(0,$E).($E>5?" …":"");for($r=max(1,$E-4);$r<min($fg,$E+5);$r++)echo
pagination($r,$E);if($fg>0)echo($E+5<$fg?" …":""),($jd&&$Rd!==false?pagination($fg,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$fg'>".'last'."</a>");}else
echo
pagination(0,$E).($E>1?" …":""),($E?pagination($E,$E):""),($pe?pagination($E+1,$E)." …":"");echo"</fieldset>\n";}echo"<fieldset>","<legend>".'Whole result'."</legend>";$tc=($jd?"":"~ ").$Rd;$zf=($Rd!==false?($jd?"":"~ ").lang_format(array('%d row','%d rows'),$Rd):"");echo
checkbox("all",1,0,$zf,on('click','countRows',$tc))."\n","</fieldset>\n";if(adminer()->selectCommandPrint())echo'<fieldset',($_GET["modify"]?'':" title='".'Ctrl+click on a value to modify it.'."'"),'>
<legend><a href=\'',h($_GET["modify"]?remove_from_uri("modify"):relative_uri()."&modify=1"),'\'>Modify</a></legend><div>
<input type=\'submit\' id=\'save\' value=\'Save\'',($_GET["modify"]||$_POST["val"]?'':" class='jsonly' disabled"),'>
</div></fieldset>

<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type=\'submit\' name=\'edit\' value=\'Edit\'>
<input type=\'submit\' name=\'clone\' value=\'Clone\'>
<input type=\'submit\' name=\'delete\' value=\'Delete\'',confirm(),'>
</div></fieldset>
';$Pd=adminer()->dumpFormat();foreach((array)$_GET["columns"]as$c){if($c["fun"]||($c["col"]!=""&&!isset($l[$c["col"]]))){unset($Pd['sql']);break;}}if($Pd){print_fieldset("export",'Export'." <span id='selected2'></span>");$Oh=adminer()->dumpOutput();echo($Oh?html_select("output",$Oh,$la["output"])." ":""),html_select("format",$Pd,$la["format"])," <input type='submit' name='export' value='".'Export'."'>\n","</div></fieldset>\n";}adminer()->selectEmailPrint(array_filter($Rc,'strlen'),$d);echo"</div></div>\n";}if(adminer()->selectImportPrint())echo"<p>","<a href='#import' class='toggle'>".'Import'."</a>","<span id='import'".($_POST["import"]?"":" class='hidden'").">: ",($Rl?input_hidden(ini_get("session.upload_progress.name"),$Rl):""),file_input(" name='csv_file'"," ".html_select("separator",array("csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"),$la["format"])." <input type='submit' name='import' value='".'Import'."'>".($Rl?" <progress class='jsonly hidden' max='1' value='0'></progress>":"")),"</span>";echo
input_token(),"</form>\n",(!$q&&$N?"":script("tableCheck();"));}}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$uk=isset($_GET["status"]);page_header($uk?'Status':'Variables');$hm=($uk?adminer()->showStatus():adminer()->showVariables());if(!$hm)echo"<p class='message'>".'No rows.'."\n";else{echo"<table>\n";foreach($hm
as$K){echo"<tr>";$w=array_shift($K);echo"<th><code class='jush-".JUSH.($uk?"status":"set")."'>".h($w)."</code>";foreach($K
as$X)echo"<td>".nl_br(h($X));}echo"</table>\n";}}elseif(isset($_GET["script"])){header("Content-Type: application/json; charset=utf-8");if($_GET["script"]=="db"){$Ck=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach(table_status()as$B=>$S){json_row("Comment-$B",h($S["Comment"]).($S["Error"]?" <span class='error'>".h($S["Error"])."</span>":""));if(!is_view($S)||preg_match('~materialized~i',$S["Engine"])){foreach(array("Engine","Collation")as$w)json_row("$w-$B",h($S[$w]));foreach(array_keys($Ck+array("Auto_increment"=>0,"Rows"=>0))as$w){if(array_key_exists($w,$S))json_row("$w-$B",format_status($S,$w));if($S[$w]!=""&&isset($Ck[$w]))$Ck[$w]+=($S["Engine"]!="InnoDB"||$w!="Data_free"?$S[$w]:0);}}}if(function_exists('Adminer\db_status'))$Ck=db_status();foreach($Ck
as$w=>$X)json_row("sum-$w",adminer()->formatSizeValue($X));json_row("");}elseif($_GET["script"]=="kill"){if(!$j)connection()->query("KILL ".number($_POST["kill"]));}else{foreach(count_tables(adminer()->databases(false))as$h=>$X){json_row("tables-$h",format_number($X));json_row("size-$h",db_size($h));}json_row("");}exit;}else{if(!isset($_GET["select"])&&support("single_table")){$T=tables_list();if($T)redirect(ME.(support("table")?"table=":"select=").url_escape(key($T)));}$mg=ME.(isset($_GET["select"])?"select=&":"");$Vk=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($Vk&&!$j&&!$_POST["search"]){$I=true;$qg="";if(JUSH=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"]);$qg='Tables have been truncated.';}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$qg='Tables have been moved.';}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$qg='Tables have been copied.';}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$qg='Tables have been dropped.';}elseif(JUSH=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("PRAGMA integrity_check(".q($R).")")as$K)$qg
.="<b>".h($R)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(JUSH=="mssql"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("DBCC CHECKTABLE (".q(table($R)).") WITH TABLERESULTS")as$K)$qg
.="<b>".h($R)."</b>: ".h($K["MessageText"])."<br>";}}elseif(JUSH!="sql"){$I=(JUSH=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),(array)$_POST["tables"]));$qg='Tables have been optimized.';}elseif(!$_POST["tables"])$qg='No tables.';elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('Adminer\idf_escape',$_POST["tables"])))){while($K=$I->fetch_assoc())$qg
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect(relative_uri(),$qg,$I);}page_header(($_GET["ns"]==""?'Database'.": ".h(DB):'Schema'.": ".h($_GET["ns"])),$j,true);if(adminer()->homepage()){if($_GET["ns"]!==""){$D=$_GET["order"];$Ud=($D||support("fast_status"));echo"<div>\n","<h3 id='tables-views'>".'Tables and views'."</h3>\n";$Uk=($Ud?table_status():tables_list());if(!$Uk)echo"<p class='message'>".'No tables.'."\n";else{echo"<form action='' method='post'>\n";if(support("table")){echo"<fieldset><legend>".'Search data in tables'." <span id='selected2'></span></legend><div>",html_select("op",adminer()->operators(),idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%"))," <input type='search' name='query' value='".h($_POST["query"])."'".on('keydown','submitKeydown','search').">"," <input type='submit' name='search' value='".'Search'."'>\n","</div></fieldset>\n";if(!$j&&$_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n",'<thead><tr>','<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="'.'All'.'"'.on('click','formCheck','^(tables|views)\[').'>','<th class="sticky"'.(!$D&&JUSH!='sqlite'?" aria-sort='ascending'":'').'><a href="'.h(substr($mg,0,-1)).'">'.'Table'.'</a>';$d=array("Engine"=>array('Engine'.doc_link(array('sql'=>'storage-engines.html'))));if(collations())$d["Collation"]=array('Collation'.doc_link(array('sql'=>'charset-charsets.html','mariadb'=>'supported-character-sets-and-collations/')));if(function_exists('Adminer\alter_table'))$d["Data_length"]=array('Data Length'.doc_link(array('sql'=>'show-table-status.html',)),"create",'Alter table',);if(support("indexes"))$d["Index_length"]=array('Index Length'.doc_link(array('sql'=>'show-table-status.html',)),"indexes",'Alter indexes',);$d["Data_free"]=array('Data Free'.doc_link(array('sql'=>'show-table-status.html')),"edit",'New item');if(function_exists('Adminer\alter_table'))$d["Auto_increment"]=array('Auto Increment'.doc_link(array('sql'=>'example-auto-increment.html','mariadb'=>'auto_increment/')),"auto_increment=1&create",'Alter table',);$d["Rows"]=array('Rows'.doc_link(array('sql'=>'show-table-status.html',)),"select",'Select data',);if(support("comment"))$d["Comment"]=array('Comment'.doc_link(array('sql'=>'show-table-status.html',)),);$Aa=array('Engine','Collation','Comment');foreach($d
as$w=>$c)echo"<th".($D==$w?" aria-sort='".(in_array($w,$Aa)?"ascending":"descending")."'":"")."><a href='".h($mg)."order=$w'>$c[0]</a>";echo"<tbody>\n";if($D){uasort($Uk,function($fa,$Ha)use($D,$Aa){$J=($fa[$D]<$Ha[$D]?-1:($fa[$D]>$Ha[$D]?1:0));return(in_array($D,$Aa)?$J:-$J);});}$T=0;$Ck=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach($Uk
as$B=>$uk){$km=($Ud?is_view($uk):$uk!==null&&!preg_match('~table|sequence~i',$uk));$uk=($Ud?$uk:array('Engine'=>$uk));$s=h("Table-".$B);echo'<tr><td class="hover">'.checkbox(($km?"views[]":"tables[]"),$B,in_array("$B",$Vk,true),"","","",$s),'<th class="sticky">'.(support("table")||support("indexes")?"<a href='".h(ME)."table=".url_escape($B)."' title='".'Show structure'."' id='$s'>".h($B).'</a>':h($B));if($km&&!preg_match('~materialized~i',$uk['Engine'])){$jl='View';echo'<td colspan="'.(count($d)-(support("comment")?2:1)).'">'.(support("view")?"<a href='".h(ME)."view=".url_escape($B)."' title='".'Alter view'."'>$jl</a>":$jl),"<td align='right'><a href='".h(ME)."select=".url_escape($B)."' title='".'Select data'."'>?</a>";if(support("comment"))echo'<td>'.h($uk['Comment']);}else{if($Ud){foreach(array_keys($Ck)as$w)$Ck[$w]+=($uk["Engine"]!="InnoDB"||$w!="Data_free"?idx($uk,$w):0);}foreach($d
as$w=>$c){$s=" id='$w-".h($B)."'";echo($c[1]?"<td align='right'>".($w=="Index_length"&&$uk["invalid_index"]?"<span class='error'>INVALID</span> ":"")."<a href='".h(ME."$c[1]=").url_escape($B)."'$s title='$c[2]'>".format_status($uk,$w)."</a>":"<td$s>".h(idx($uk,$w,'?')).($w=="Comment"&&$uk["Error"]?" <span class='error'>".h($uk["Error"])."</span>":""));}$T++;}echo"\n";}echo"<tr><td class='hover'><th class='sticky'>".sprintf('%d in total',count($Uk)),"<td>".h(JUSH=="sql"?get_val("SELECT @@default_storage_engine"):""),(collations()?"<td>".h(db_collation(DB,collations())):'');if($Ud&&function_exists('Adminer\db_status'))$Ck=db_status();foreach($Ck
as$w=>$Bk)echo($d[$w]?"<td align='right' id='sum-$w'>".($Ud?adminer()->formatSizeValue($Bk):""):"");echo"\n","</table>\n",($Ud?'':script("ajaxSetHtml('".js_escape(ME)."script=db');")),"</div>\n";if(!information_schema(DB)){$dm="<input type='submit' value='".'Vacuum'."'".on_help("VACUUM")."> ";$yh="<input type='submit' name='optimize' value='".'Optimize'."'".on_help(JUSH=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE")."> ";$Fi=(JUSH=="sqlite"?$dm."<input type='submit' name='check' value='".'Check'."'".on_help("PRAGMA integrity_check")."> ":(JUSH=="pgsql"?$dm.$yh:(JUSH=="mssql"?"<input type='submit' name='check' value='".'Check'."'".on_help("DBCC CHECKTABLE")."> ":(JUSH=="sql"?"<input type='submit' value='".'Analyze'."'".on_help("ANALYZE TABLE")."> ".$yh."<input type='submit' name='check' value='".'Check'."'".on_help("CHECK TABLE")."> "."<input type='submit' name='repair' value='".'Repair'."'".on_help("REPAIR TABLE")."> ":"")))).(function_exists('Adminer\truncate_tables')?"<input type='submit' name='truncate' value='".'Truncate'."'".confirm().on_help(JUSH=="sqlite"?"DELETE":"TRUNCATE".(JUSH=="pgsql"?"":" TABLE"))."> ":"").(function_exists('Adminer\drop_tables')?"<input type='submit' name='drop' value='".'Drop'."'".confirm().on_help("DROP TABLE").">":"");echo($Fi?"<div class='footer'><div>\n<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>$Fi\n</div></fieldset>\n":"");$g=(support("scheme")?adminer()->schemas():adminer()->databases());if(count($g)!=1&&function_exists('Adminer\move_tables')){echo"<fieldset><legend>".'Move to another database'." <span id='selected3'></span></legend><div>";$h=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($g?html_select("target",$g,$h):'<input name="target" value="'.h($h).'" autocapitalize="off">'),"</label> <input type='submit' name='move' value='".'Move'."'>",(support("copy")?" <input type='submit' name='copy' value='".'Copy'."'> ".checkbox("overwrite",1,$_POST["overwrite"],'overwrite'):""),"</div></fieldset>\n";}echo"<input type='hidden' name='all' value=''".on('click','countTables',$T).">\n",input_token(),"</div></div>\n";}echo"</form>\n",script("tableCheck();");}echo(function_exists('Adminer\alter_table')?"<p class='links hover'><a href='".h(ME)."create='>".'Create table'."</a>\n":''),(support("view")?"<a href='".h(ME)."view='>".'Create view'."</a>\n":""),"</div>\n";if(support("routine")){echo"<div>\n","<h3 id='routines'>".'Routines'."</h3>\n";$xj=routines();if($xj){echo"<table class='odds'>\n",'<thead><tr><th>'.'Name'.'<th>'.'Type'.'<th>'.'Return type'."<td class='hover'><tbody>\n";foreach($xj
as$K){$B=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".url_escape($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').url_escape($K["SPECIFIC_NAME"]).$B).'"'.' title="'.($K["DTD_IDENTIFIER"]=="trigger"?'Triggers':'Call').'">'.h($K["ROUTINE_NAME"]).'</a>','<td>'.h($K["ROUTINE_TYPE"]),'<td>'.h($K["DTD_IDENTIFIER"]),'<td class="hover"><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').url_escape($K["SPECIFIC_NAME"]).$B).'">'.'Alter'."</a>";}echo"</table>\n";}echo'<p class="links hover">'.(support("procedure")?'<a href="'.h(ME).'procedure=">'.'Create procedure'.'</a>':'').'<a href="'.h(ME).'function=">'.'Create function'."</a>\n","</div>\n";}if(support("event")){echo"<div>\n","<h3 id='events'>".'Events'."</h3>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".'Name'."<th>".'Schedule'."<th>".'Start'."<th>".'End'."<td class='hover'><tbody>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?'At given time'."<td>".h($K["Execute at"]):'Every'." ".h($K["Interval value"])." ".h($K["Interval field"])."<td>".h($K["Starts"])),"<td>".h($K["Ends"]),'<td class="hover"><a href="'.h(ME).'event='.url_escape($K["Name"]).'">'.'Alter'.'</a>';echo"</table>\n";$gd=get_val("SELECT @@event_scheduler");if($gd&&$gd!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($gd)."\n";}echo'<p class="links hover"><a href="'.h(ME).'event=">'.'Create event'."</a>\n","</div>\n";}}}}page_footer();